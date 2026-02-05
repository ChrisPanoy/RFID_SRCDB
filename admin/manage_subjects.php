<?php
include '../includes/db.php';

// Ensure session is started before checking auth so header redirects work
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../includes/header.php';

$message = '';
$error = '';

// Load active session context
$ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
$sem_id = (int)($_SESSION['active_sem_id'] ?? 0);

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    $delete_stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
    $delete_stmt->bind_param("i", $delete_id);
    
    if ($delete_stmt->execute()) {
        $message = "Subject deleted successfully!";
    } else {
        $error = "Error deleting subject: " . $conn->error;
    }
    $delete_stmt->close();
}

// Handle edit action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_subject'])) {
    $edit_id = $_POST['edit_id'];
    $subject_code = trim($_POST['subject_code']);
    $subject_name = trim($_POST['subject_name']);
    $teacher_id = trim($_POST['teacher_id']);
    
    if (empty($subject_code) || empty($subject_name)) {
        $error = "Subject Code and Subject Name are required!";
    } else {
        // Check if subject_code already exists for other subjects
        $check = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ? AND subject_id != ?");
        $check->bind_param("si", $subject_code, $edit_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Subject Code already exists!";
        } else {
            $teacher_id = empty($teacher_id) ? null : (int)$teacher_id;
            // NOTE: normalized schema stores faculty assignment in schedule (employee_id),
            // so here we update core subject fields.
            $update_stmt = $conn->prepare("UPDATE subjects SET subject_code = ?, subject_name = ? WHERE subject_id = ?");
            $update_stmt->bind_param("ssi", $subject_code, $subject_name, $edit_id);
            
            if ($update_stmt->execute()) {
                // Also update the schedule table if a teacher is assigned
                // This updates ALL schedules for this subject in the CURRENT session
                if ($teacher_id) {
                    // 1. Try to update existing schedules
                    $upd_sched = $conn->prepare("UPDATE schedule SET employee_id = ? WHERE subject_id = ? AND academic_year_id = ? AND semester_id = ?");
                    $upd_sched->bind_param("iiii", $teacher_id, $edit_id, $ay_id, $sem_id);
                    $upd_sched->execute();
                    
                    // 2. If no schedules existed for this session, create a default one
                    if ($upd_sched->affected_rows == 0) {
                        $check_sched = $conn->prepare("SELECT schedule_id FROM schedule WHERE subject_id = ? AND academic_year_id = ? AND semester_id = ?");
                        $check_sched->bind_param("iii", $edit_id, $ay_id, $sem_id);
                        $check_sched->execute();
                        if ($check_sched->get_result()->num_rows == 0) {
                             $lab_res = $conn->query("SELECT lab_id FROM facilities LIMIT 1");
                             $lab_id = ($lab_res && $row = $lab_res->fetch_assoc()) ? $row['lab_id'] : 1;
                             
                             $dayCol = 'schedule_days';
                             $chkDays = $conn->query("SHOW COLUMNS FROM schedule LIKE 'days'");
                             if ($chkDays && $chkDays->num_rows > 0) $dayCol = 'days';
                             
                             $ins_sched = $conn->prepare("INSERT INTO schedule (lab_id, subject_id, employee_id, time_start, time_end, $dayCol, academic_year_id, semester_id) VALUES (?, ?, ?, '08:00:00', '09:00:00', 'Mon', ?, ?)");
                             $ins_sched->bind_param("iiiii", $lab_id, $edit_id, $teacher_id, $ay_id, $sem_id);
                             $ins_sched->execute();
                             $ins_sched->close();
                        }
                        $check_sched->close();
                    }
                    $upd_sched->close();
                }
                $message = "Subject and assignment updated successfully!";
            } else {
                $error = "Error updating subject: " . $conn->error;
            }
            $update_stmt->close();
        }
        $check->close();
    }
}

// Handle filtering and sorting
$teacher_filter = isset($_GET['teacher']) ? intval($_GET['teacher']) : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'subject_code';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Validate sort column
$allowed_sorts = ['subject_code', 'subject_name', 'teacher_name'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'subject_code';
}

// Build the query with filtering and sorting.
// ($ay_id and $sem_id already defined at top)

$query = "
    SELECT 
        s.subject_id,
        s.subject_id AS id,
        s.subject_code,
        s.subject_name,
        GROUP_CONCAT(DISTINCT CONCAT(e.lastname, ', ', e.firstname) SEPARATOR ', ') AS teacher_name,
        GROUP_CONCAT(DISTINCT e.employee_id SEPARATOR ', ') AS teacher_code
    FROM subjects s
    LEFT JOIN schedule sc ON sc.subject_id = s.subject_id AND sc.academic_year_id = ? AND sc.semester_id = ?
    LEFT JOIN employees e ON sc.employee_id = e.employee_id
    LEFT JOIN roles r ON e.role_id = r.role_id AND LOWER(r.role_name) IN ('dean','faculty')
";

$params = [$ay_id, $sem_id];
$types = 'ii';

if ($teacher_filter > 0) {
    // Filter subjects that have at least one schedule with this employee as faculty in CURRENT session
    $query .= " WHERE sc.employee_id = ? AND sc.academic_year_id = ? AND sc.semester_id = ?";
    $params[] = $teacher_filter;
    $params[] = $ay_id;
    $params[] = $sem_id;
    $types .= 'iii';
} elseif ($teacher_filter === -1) {
    // Subjects with no faculty/schedule in current session
    $query .= " WHERE sc.employee_id IS NULL";
}

// Group by subject so aggregated teacher_name / teacher_code work correctly
$query .= " GROUP BY s.subject_id, s.subject_code, s.subject_name";

$query .= " ORDER BY " . $sort_by . " " . $sort_order;

$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$subjects = $stmt->get_result();

// Get all faculty (employees) for dropdown (Dean/Faculty roles)
$teachers = $conn->query("SELECT e.employee_id, e.firstname, e.lastname, r.role_name 
                          FROM employees e 
                          JOIN roles r ON e.role_id = r.role_id 
                          WHERE LOWER(r.role_name) IN ('dean','faculty') 
                          ORDER BY e.lastname, e.firstname");
?>

<style>
    .app-content { margin-left: 20rem; padding: 3.5rem 2rem; min-height:100vh; box-sizing:border-box; background: linear-gradient(90deg,#ebf8ff 0%, #f0f4ff 100%); }
    .app-container { max-width: 2100px; margin: 0 auto; background: #fff; border-radius: 1rem; padding: 2.5rem; box-shadow: 0 12px 40px rgba(2,6,23,0.06); }
    @media (max-width:900px){ .app-content{margin-left:0;padding:2rem;} .app-container{padding:1rem;} }
</style>
<div class="app-content">
    <div class="app-container">
        <div class="flex flex-col sm:flex-row justify-between items-center border-b-2 border-blue-100 pb-5 mb-10 gap-5">
            <h2 class="text-3xl font-bold text-blue-900 flex items-center gap-3">
                Manage Subjects
            </h2>
            <a href="add_subject.php" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition shadow-lg font-bold flex items-center gap-2">
                <i class="fas fa-plus"></i> Add New Subject
            </a>
        </div>

        <!-- Filter and Sort Controls -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Teacher</label>
                    <select name="teacher" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="0">All Teachers</option>
                        <option value="-1" <?= $teacher_filter === -1 ? 'selected' : '' ?>>Unassigned Subjects</option>
                        <?php 
                        $teachers->data_seek(0);
                        while ($teacher = $teachers->fetch_assoc()): 
                        ?>
                            <option value="<?= $teacher['employee_id'] ?>" <?= $teacher_filter == $teacher['employee_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($teacher['lastname'] . ', ' . $teacher['firstname']) ?> (<?= htmlspecialchars($teacher['role']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="flex-1 min-w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Sort by</label>
                    <select name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="subject_code" <?= $sort_by === 'subject_code' ? 'selected' : '' ?>>Subject Code</option>
                        <option value="subject_name" <?= $sort_by === 'subject_name' ? 'selected' : '' ?>>Subject Name</option>
                        <option value="teacher_name" <?= $sort_by === 'teacher_name' ? 'selected' : '' ?>>Teacher Name</option>
                    </select>
                </div>
                
                <div class="flex-1 min-w-32">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Order</label>
                    <select name="order" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="asc" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        <option value="desc" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Apply Filter
                    </button>
                    <a href="manage_subjects.php" class="ml-2 px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Results Summary -->
        <div class="mb-4">
            <p class="text-sm text-gray-600">
                <?php 
                $total_subjects = $subjects->num_rows;
                if ($teacher_filter > 0) {
                    $teacher_name = '';
                    $teachers->data_seek(0);
                    while ($t = $teachers->fetch_assoc()) {
                        if ($t['employee_id'] == $teacher_filter) {
                            $teacher_name = $t['lastname'] . ', ' . $t['firstname'];
                            break;
                        }
                    }
                    echo "Showing {$total_subjects} subject(s) assigned to <strong>" . htmlspecialchars($teacher_name) . "</strong>";
                } elseif ($teacher_filter === -1) {
                    echo "Showing {$total_subjects} unassigned subject(s)";
                } else {
                    echo "Showing all {$total_subjects} subject(s)";
                }
                
                if ($sort_by !== 'subject_code' || $sort_order !== 'ASC') {
                    echo " • Sorted by " . ucwords(str_replace('_', ' ', $sort_by)) . " (" . ($sort_order === 'ASC' ? 'A-Z' : 'Z-A') . ")";
                }
                ?>
            </p>
        </div>

        <?php if ($message): ?>
            <div class="alert success">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <table class="w-full border border-blue-200 rounded-lg overflow-hidden">
                <thead class="bg-blue-600 text-white">
                    <tr>
                        <th class="px-5 py-3 text-lg">
                            <a href="?teacher=<?= $teacher_filter ?>&sort=subject_code&order=<?= $sort_by === 'subject_code' && $sort_order === 'ASC' ? 'desc' : 'asc' ?>" class="text-white hover:text-blue-200 flex items-center gap-1">
                                Subject Code
                                <?php if ($sort_by === 'subject_code'): ?>
                                    <span class="text-xs"><?= $sort_order === 'ASC' ? '▲' : '▼' ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="px-5 py-3 text-lg">
                            <a href="?teacher=<?= $teacher_filter ?>&sort=subject_name&order=<?= $sort_by === 'subject_name' && $sort_order === 'ASC' ? 'desc' : 'asc' ?>" class="text-white hover:text-blue-200 flex items-center gap-1">
                                Subject Name
                                <?php if ($sort_by === 'subject_name'): ?>
                                    <span class="text-xs"><?= $sort_order === 'ASC' ? '▲' : '▼' ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="px-5 py-3 text-lg">
                            <a href="?teacher=<?= $teacher_filter ?>&sort=teacher_name&order=<?= $sort_by === 'teacher_name' && $sort_order === 'ASC' ? 'desc' : 'asc' ?>" class="text-white hover:text-blue-200 flex items-center gap-1">
                                Teacher
                                <?php if ($sort_by === 'teacher_name'): ?>
                                    <span class="text-xs"><?= $sort_order === 'ASC' ? '▲' : '▼' ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="px-5 py-3 text-lg">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($subjects->num_rows > 0): ?>
                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                            <tr class="border-b border-blue-100 hover:bg-blue-50 text-lg">
                                <td class="px-5 py-3"><?= htmlspecialchars($subject['subject_code']) ?></td>
                                <td class="px-5 py-3"><?= htmlspecialchars($subject['subject_name']) ?></td>
                                <td class="px-5 py-3">
                                    <?php if ($subject['teacher_name']): ?>
                                        <?= htmlspecialchars($subject['teacher_name']) ?> 
                                        <small>(<?= htmlspecialchars($subject['teacher_code']) ?>)</small>
                                    <?php else: ?>
                                        <span class="no-teacher">No teacher assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 actions">
                                    <?php 
                                        $first_teacher_id = '';
                                        if (!empty($subject['teacher_code'])) {
                                            $codes = explode(',', $subject['teacher_code']);
                                            $first_teacher_id = trim($codes[0]);
                                        }
                                    ?>
                                    <button class="btn-edit" onclick="editSubject(<?= $subject['subject_id'] ?>, '<?= htmlspecialchars($subject['subject_code']) ?>', '<?= htmlspecialchars($subject['subject_name']) ?>', '<?= $first_teacher_id ?>')">Edit</button>
                                    <a href="?delete=<?= $subject['subject_id'] ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this subject?')">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="no-data">No subjects found. <a href="add_subject.php">Add your first subject</a></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Subject</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" class="edit-form">
            <input type="hidden" name="edit_id" id="edit_id">
            <input type="hidden" name="edit_subject" value="1">
            
            <div class="form-group">
                <label for="edit_subject_code">Subject Code *</label>
                <input type="text" id="edit_subject_code" name="subject_code" required>
            </div>

            <div class="form-group">
                <label for="edit_subject_name">Subject Name *</label>
                <input type="text" id="edit_subject_name" name="subject_name" required>
            </div>

            <div class="form-group">
                <label for="edit_teacher_id">Assigned Teacher</label>
                <select id="edit_teacher_id" name="teacher_id">
                    <option value="">Select Teacher (Optional)</option>
                    <?php 
                    $teachers->data_seek(0); // Reset pointer to beginning
                    while ($teacher = $teachers->fetch_assoc()): 
                        $tid = $teacher['employee_id'];
                        $tname = $teacher['lastname'] . ', ' . $teacher['firstname'];
                    ?>
                        <option value="<?= $tid ?>">
                            <?= htmlspecialchars($tname) ?> (<?= htmlspecialchars($teacher['role']) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Save Changes</button>
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
    body {
        font-family: 'Segoe UI', sans-serif;
        margin: 0;
        padding: 0;
        background: linear-gradient(to right, #74ebd5, #ACB6E5);
        color: #333;
    }

    .container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
    }

    .manage-container {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }

    .manage-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 15px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .manage-header h2 {
        margin: 0;
        color: #003366;
    }

    .header-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .btn-add,
    .btn-back {
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-add {
        background: #28a745;
        color: white;
        border: 1px solid #28a745;
    }

    .btn-add:hover {
        background: #218838;
        transform: translateY(-2px);
    }

    .btn-back {
        background: #0074D9;
        color: white;
        border: 1px solid #0074D9;
    }

    .btn-back:hover {
        background: #005fa3;
        transform: translateY(-2px);
    }

    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 500;
    }

    .alert.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .table-container {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    thead {
        background-color: #0074D9;
        color: white;
    }

    th, td {
        padding: 12px;
        border: 1px solid #ddd;
        text-align: left;
    }

    th {
        font-weight: 600;
    }

    .no-teacher {
        color: #6c757d;
        font-style: italic;
    }

    .actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .btn-edit,
    .btn-delete {
        padding: 10px 16px;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
        text-align: center;
        width: 100%;
    }

    .btn-edit {
        background: #ffc107;
        color: #212529;
    }

    .btn-edit:hover {
        background: #e0a800;
        transform: translateY(-1px);
    }

    .btn-delete {
        background: #dc3545;
        color: white;
    }

    .btn-delete:hover {
        background: #c82333;
        transform: translateY(-1px);
    }

    .no-data {
        text-align: center;
        color: #6c757d;
        font-style: italic;
    }

    .no-data a {
        color: #0074D9;
        text-decoration: none;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 0;
        border-radius: 15px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 30px;
        border-bottom: 2px solid #f0f0f0;
    }

    .modal-header h3 {
        margin: 0;
        color: #003366;
    }

    .close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover {
        color: #000;
    }

    .edit-form {
        padding: 30px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 12px;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.3s ease;
        box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #0074D9;
        box-shadow: 0 0 0 3px rgba(0, 116, 217, 0.1);
    }

    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }

    .btn-primary,
    .btn-secondary {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        flex: 1;
    }

    .btn-primary {
        background: #0074D9;
        color: white;
    }

    .btn-primary:hover {
        background: #005fa3;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background: #545b62;
    }

    @media screen and (max-width: 768px) {
        .container {
            margin: 15px auto;
        }

        .manage-container {
            padding: 20px;
        }

        .manage-header {
            flex-direction: column;
            text-align: center;
        }

        .header-actions {
            justify-content: center;
        }

        .actions {
            flex-direction: column;
        }

        .modal-content {
            margin: 10% auto;
            width: 95%;
        }

        .form-actions {
            flex-direction: column;
        }
    }
</style>

<script>
    function editSubject(id, subjectCode, subjectName, teacherId) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_subject_code').value = subjectCode;
        document.getElementById('edit_subject_name').value = subjectName;
        document.getElementById('edit_teacher_id').value = teacherId;
        
        document.getElementById('editModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // Auto-submit filter form when selections change
    document.addEventListener('DOMContentLoaded', function() {
        const filterForm = document.querySelector('form[method="GET"]');
        const selects = filterForm.querySelectorAll('select');
        
        selects.forEach(select => {
            select.addEventListener('change', function() {
                filterForm.submit();
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>



