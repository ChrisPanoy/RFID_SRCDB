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

// Fetch available roles
$roleQuery = $conn->query("SELECT * FROM roles ORDER BY role_name ASC");
$roles = [];
while($r = $roleQuery->fetch_assoc()) {
    $roles[] = $r;
}

include '../includes/header.php';

$message = '';
$error = '';

// Utility: check if a table exists in current DB
function tableExists($conn, $tableName) {
    $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    // Check if teacher has assigned schedules
    $check_subjects = $conn->prepare("SELECT COUNT(*) as count FROM schedule WHERE employee_id = ?");
    $check_subjects->bind_param("i", $delete_id);
    $check_subjects->execute();
    $subject_count = $check_subjects->get_result()->fetch_assoc()['count'];
    
    if ($subject_count > 0) {
        $error = "Cannot delete teacher. They have assigned subjects. Please reassign or delete the subjects first.";
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $message = "Teacher deleted successfully!";
        } else {
            $error = "Error deleting teacher: " . $conn->error;
        }
        $delete_stmt->close();
    }
    $check_subjects->close();
}

// Handle edit action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_teacher'])) {
    $edit_id = $_POST['edit_id'];
    $teacher_id = trim($_POST['teacher_id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $department = 'College of Computing Studies'; // Always fixed
    $role_id = intval($_POST['role_id'] ?? 0);
    $new_password = trim($_POST['new_password']);
    $existing_profile_pic = isset($_POST['existing_profile_pic']) ? trim($_POST['existing_profile_pic']) : '';
    
    if (empty($email) || empty($name)) {
        $error = "Email and Name are required!";
    } else {
        // Get current teacher email
        $old_email = '';
        $old_email_stmt = $conn->prepare("SELECT email FROM employees WHERE employee_id = ?");
        $old_email_stmt->bind_param("i", $edit_id);
        $old_email_stmt->execute();
        $row = $old_email_stmt->get_result()->fetch_assoc();
        if ($row) {
            $old_email = $row['email'];
        }
        $old_email_stmt->close();

        // Check duplicate email
        $dupExists = false;
        if (tableExists($conn, 'users')) {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'teacher' AND email != ?");
            $check->bind_param("ss", $email, $old_email);
            $check->execute();
            $result = $check->get_result();
            $dupExists = ($result->num_rows > 0);
        } else {
            $check = $conn->prepare("SELECT employee_id FROM employees WHERE email = ? AND employee_id != ?");
            $check->bind_param("si", $email, $edit_id);
            $check->execute();
            $result = $check->get_result();
            $dupExists = ($result->num_rows > 0);
        }
        if ($dupExists) {
            $error = "Email already exists!";
        } else {
            // Password validation if new password entered
            if (!empty($new_password)) {
                if (strlen($new_password) < 8 || !preg_match('/[^a-zA-Z0-9]/', $new_password)) {
                    $error = "Password must be at least 8 characters long and contain at least one special character.";
                }
            }
        }

        if (empty($error)) {
            // Start transaction
            $conn->begin_transaction();
            try {
                // Handle profile picture
                $profile_pic_to_save = $existing_profile_pic;
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                    $cur = $conn->prepare("SELECT profile_pic FROM employees WHERE employee_id = ?");
                    $cur->bind_param("i", $edit_id);
                    $cur->execute();
                    $cur_row = $cur->get_result()->fetch_assoc();
                    $cur->close();
                    if ($cur_row && !empty($cur_row['profile_pic']) && file_exists(__DIR__ . '/../assets/img/' . $cur_row['profile_pic'])) {
                        @unlink(__DIR__ . '/../assets/img/' . $cur_row['profile_pic']);
                    }
                    $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                    $profile_pic_to_save = uniqid('teacher_', true) . '.' . $ext;
                    move_uploaded_file($_FILES['profile_pic']['tmp_name'], __DIR__ . '/../assets/img/' . $profile_pic_to_save);
                }

                // Update employee email, profile picture, and role_id
                $update_stmt = $conn->prepare("UPDATE employees SET email = ?, profile_pic = ?, role_id = ? WHERE employee_id = ?");
                $update_stmt->bind_param("ssii", $email, $profile_pic_to_save, $role_id, $edit_id);
                $update_stmt->execute();
                $update_stmt->close();

                // Update user info (only if users table exists)
                if (!empty($old_email) && tableExists($conn, 'users')) {
                    $user_update_stmt = $conn->prepare("UPDATE users SET email = ?, name = ? WHERE email = ? AND role = 'teacher'");
                    $user_update_stmt->bind_param("sss", $email, $name, $old_email);
                    $user_update_stmt->execute();
                    $user_update_stmt->close();
                }

                // Update password if valid
                if (!empty($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update employees table
                    $emp_pw_stmt = $conn->prepare("UPDATE employees SET password = ? WHERE employee_id = ?");
                    $emp_pw_stmt->bind_param("si", $hashed_password, $edit_id);
                    $emp_pw_stmt->execute();
                    $emp_pw_stmt->close();

                    // Update users table if it exists
                    if (tableExists($conn, 'users')) {
                        $password_update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'teacher'");
                        $password_update_stmt->bind_param("ss", $hashed_password, $email);
                        $password_update_stmt->execute();
                        $password_update_stmt->close();
                    }
                }

                $conn->commit();
                $message = "Teacher updated successfully!" . (!empty($new_password) ? " Password has been updated." : "");
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error updating teacher: " . $e->getMessage();
            }
        }
        if (isset($check) && $check instanceof mysqli_stmt) { @($check->close()); }
    }
}

// Handle reset password action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_teacher_id']) && isset($_POST['reset_new_password'])) {
    $reset_id = intval($_POST['reset_teacher_id']);
    $reset_new_password = trim($_POST['reset_new_password']);
    if (!empty($reset_new_password)) {
        // Validate password
        if (strlen($reset_new_password) < 8 || !preg_match('/[^a-zA-Z0-9]/', $reset_new_password)) {
            $error = "Password must be at least 8 characters long and contain at least one special character.";
        } else {
            $res = $conn->query("SELECT email FROM employees WHERE employee_id = $reset_id");
            if ($row = $res->fetch_assoc()) {
                $reset_email = $row['email'];
                $hashed_password = password_hash($reset_new_password, PASSWORD_DEFAULT);
                
                // Update employees table
                $upd_emp = $conn->prepare("UPDATE employees SET password = ? WHERE employee_id = ?");
                $upd_emp->bind_param("si", $hashed_password, $reset_id);
                $upd_emp->execute();
                $upd_emp->close();

                // Update users table if it exists
                if (tableExists($conn, 'users')) {
                    $update = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'teacher'");
                    $update->bind_param("ss", $hashed_password, $reset_email);
                    $update->execute();
                    $update->close();
                }
                
                $message = "Password reset successfully for Email: <strong>" . htmlspecialchars($reset_email) . "</strong>.";
            } else {
                $error = "Teacher not found.";
            }
        }
    } else {
        $error = "Please enter a new password.";
    }
}

// Get all faculty (employees joined with roles)
$teachers = $conn->query("
    SELECT 
        e.employee_id AS id,
        e.employee_id AS teacher_id,
        CONCAT(e.lastname, ', ', e.firstname) AS name,
        e.email,
        'College of Computing Studies' AS department,
        e.profile_pic,
        e.role_id,
        r.role_name
    FROM employees e
    LEFT JOIN roles r ON e.role_id = r.role_id
    ORDER BY e.lastname, e.firstname
");
?>

<style>
    .app-content { margin-left: 20rem; padding: 3.5rem 2rem; min-height:200vh; box-sizing:border-box; background: linear-gradient(90deg,#ebf8ff 0%, #f0f4ff 100%); }
    .app-container { max-width: 2100px; margin: 0 auto; background: #fff; border-radius: 1rem; padding: 2.5rem; box-shadow: 0 12px 40px rgba(2,6,23,0.06); }
    @media (max-width:900px){ .app-content{margin-left:0;padding:2rem;} .app-container{padding:1rem;} }
    
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

    .actions {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        min-width: 120px;
    }

    .btn-edit,
    .btn-delete,
    .btn-reset {
        padding: 10px 16px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s ease;
        text-align: center;
        min-width:88px;
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

    .btn-reset {
        background: #17a2b8;
        color: white;
        border: 1px solid #17a2b8;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-reset:hover {
        background: #138496;
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

    /* Modal Styles - Made wider */
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
        margin: 3% auto;
        padding: 0;
        border-radius: 15px;
        width: 90%;
        max-width: 800px; /* Made wider */
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 30px;
        border-bottom: 2px solid #f0f0f0;
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
    }

    .modal-header h3 {
        margin: 0;
        color: #003366;
        font-size: 1.5rem;
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

    .form-help {
        display: block;
        margin-top: 5px;
        font-size: 0.85rem;
        color: #6c757d;
    }

    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        justify-content: flex-end;
    }

    .btn-primary,
    .btn-secondary {
        padding: 14px 26px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 140px;
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

    /* Two-column layout for form fields */
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
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
            margin: 5% auto;
            width: 95%;
            max-width: 95%;
        }

        .form-actions {
            flex-direction: column;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="app-content">
    <div class="app-container">
        <div class="flex flex-col sm:flex-row justify-between items-center border-b-2 border-blue-100 pb-5 mb-10 gap-5">
            <h2 class="text-4xl font-bold text-blue-900 flex items-center gap-3">
                Manage Faculty
            </h2>
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

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Teacher ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($teachers->num_rows > 0): ?>
                        <?php while ($teacher = $teachers->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($teacher['profile_pic']) && file_exists(__DIR__ . '/../assets/img/' . $teacher['profile_pic'])): ?>
                                        <img src="../assets/img/<?= htmlspecialchars($teacher['profile_pic']) ?>" alt="Profile" style="width:100px;height:100px;object-fit:cover;border-radius:8px;" />
                                    <?php else: ?>
                                        <div style="width:48px;height:48px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#888">N/A</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($teacher['teacher_id']) ?></td>
                                <td><?= htmlspecialchars($teacher['name']) ?></td>
                                <td><?= htmlspecialchars($teacher['email'] ?? '-') ?></td>
                                <td><span class="badge badge-info" style="background:#e1f5fe; color:#01579b; padding:4px 8px; border-radius:4px; font-weight:600;"><?= htmlspecialchars(ucfirst($teacher['role_name'] ?? 'N/A')) ?></span></td>
                                <td><?= htmlspecialchars($teacher['department'] ?? '-') ?></td>
                                <td class="actions">
                                    <div style="display: flex; flex-direction: column; gap: 5px;">
                                        <button class="btn-edit" onclick="editTeacher(<?= $teacher['id'] ?>, '<?= htmlspecialchars($teacher['teacher_id']) ?>', '<?= htmlspecialchars($teacher['name']) ?>', '<?= htmlspecialchars($teacher['email'] ?? '') ?>', '<?= htmlspecialchars($teacher['department'] ?? '') ?>', '<?= htmlspecialchars($teacher['profile_pic'] ?? '') ?>', '<?= $teacher['role_id'] ?>')">Edit</button>
                                        <a href="?delete=<?= $teacher['id'] ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this teacher?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-data">No teachers found. <a href="add_teacher.php">Add your first teacher</a></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal - Made wider -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Teacher</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" enctype="multipart/form-data" class="edit-form">
            <input type="hidden" name="edit_id" id="edit_id">
            <input type="hidden" name="edit_teacher" value="1">
            <input type="hidden" name="existing_profile_pic" id="existing_profile_pic">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_teacher_id">Teacher ID *</label>
                    <input type="text" id="edit_teacher_id" name="teacher_id" required>
                </div>

                <div class="form-group">
                    <label for="edit_name">Full Name *</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit_email">Email *</label>
                    <input type="email" id="edit_email" name="email" required>
                    <small class="form-help">This will be used as the login email</small>
                </div>

                <div class="form-group">
                    <label for="edit_role_id">Role *</label>
                    <select id="edit_role_id" name="role_id" required>
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars(ucfirst($role['role_name'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- phone removed -->
                <div class="form-group">
                    <label for="edit_department">Department</label>
                    <input type="text" id="edit_department" name="department" value="College of Computing Studies" readonly class="bg-gray-100">
                    <input type="hidden" name="department" value="College of Computing Studies">
                    <small class="form-help">Department is fixed to College of Computing Studies</small>
                </div>
            </div>

            <div class="form-group">
                <label for="edit_profile_pic">Profile Picture</label>
                <input type="file" id="edit_profile_pic" name="profile_pic" accept="image/*">
                <small class="form-help">Upload a new picture to replace current one. Leave blank to keep existing.</small>
                <div id="currentPicPreview" style="margin-top:8px"></div>
            </div>

            <div class="form-group">
                <label for="edit_new_password">New Password (leave blank to keep current)</label>
                <input type="password" id="edit_new_password" name="new_password" placeholder="Enter new password">
                <small class="form-help">Only fill this if you want to change the password</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Save Changes</button>
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>


<script>
    function editTeacher(id, teacherId, name, email, department, profilePic, roleId) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_teacher_id').value = teacherId;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_department').value = department;
        document.getElementById('edit_role_id').value = roleId || '';
        document.getElementById('existing_profile_pic').value = profilePic || '';
        
        const preview = document.getElementById('currentPicPreview');
        if (profilePic) {
            preview.innerHTML = '<p>Current Picture:</p><img src="../assets/img/' + profilePic + '" style="width:96px;height:96px;object-fit:cover;border-radius:8px;" />';
        } else {
            preview.innerHTML = '<div style="width:96px;height:96px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#888">No Image</div>';
        }
        
        document.getElementById('editModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    function openResetPasswordModal(id, teacherId, name) {
        document.getElementById('reset_teacher_id').value = id;
        document.getElementById('reset_teacher_name').value = teacherId + ' - ' + name;
        document.getElementById('reset_new_password').value = '';
        document.getElementById('resetPasswordModal').style.display = 'block';
    }

    function closeResetPasswordModal() {
        document.getElementById('resetPasswordModal').style.display = 'none';
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        const resetModal = document.getElementById('resetPasswordModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
        if (event.target == resetModal) {
            resetModal.style.display = 'none';
        }
    }
</script>

<?php include '../includes/footer.php'; ?>




