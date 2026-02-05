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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_code = trim($_POST['subject_code'] ?? '');
    $subject_name = trim($_POST['subject_name'] ?? '');
    
    // Check if subject_code already exists
    $check = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ?");
    $check->bind_param("s", $subject_code);
    $check->execute();
    $result = $check->get_result();
    
    $new_subject_id = null;
    $subject_already_exists = false;

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $new_subject_id = $row['subject_id'];
        $subject_already_exists = true;

        // Update subject name and units if they changed
        $lec = (float)($_POST['units_lec'] ?? 0);
        $lab = (float)($_POST['units_lab'] ?? 0);
        $total = $lec + $lab;
        
        $sql = "UPDATE subjects SET subject_name = ?, units = ?, lecture = ?, laboratory = ? WHERE subject_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdddi", $subject_name, $total, $lec, $lab, $new_subject_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Prepare units
        $lec = (float)($_POST['units_lec'] ?? 0);
        $lab = (float)($_POST['units_lab'] ?? 0);
        $total = $lec + $lab;
        
        $sql = "INSERT INTO subjects (subject_code, subject_name, units, lecture, laboratory) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssddd", $subject_code, $subject_name, $total, $lec, $lab);

        if ($stmt->execute()) {
            $new_subject_id = $conn->insert_id;
        } else {
            $error = "Error adding subject: " . $conn->error;
        }
        $stmt->close();
    }

    if ($new_subject_id && empty($error)) {
        // Process Schedule 1 and Schedule 2 (Dual Setup)
        $schedules_to_add = [];
        
        // Schedule 1
        if (!empty($_POST['teacher_id_1'])) {
            $days1 = isset($_POST['schedule_days_1']) && is_array($_POST['schedule_days_1']) ? implode(',', $_POST['schedule_days_1']) : 'Mon';
            $lab1 = !empty($_POST['lab_id_1']) ? $_POST['lab_id_1'] : 1; 
            $start1 = !empty($_POST['start_time_1']) ? $_POST['start_time_1'] : '08:00';
            $end1 = !empty($_POST['end_time_1']) ? $_POST['end_time_1'] : '09:00';
            
            $schedules_to_add[] = [
                't' => $_POST['teacher_id_1'], 'l' => $lab1,
                's' => $start1, 'e' => $end1, 'd' => $days1
            ];
        }

        // Schedule 2
        if (!empty($_POST['teacher_id_2'])) {
            $days2 = isset($_POST['schedule_days_2']) && is_array($_POST['schedule_days_2']) ? implode(',', $_POST['schedule_days_2']) : 'Mon';
            $lab2 = !empty($_POST['lab_id_2']) ? $_POST['lab_id_2'] : 1;
            $start2 = !empty($_POST['start_time_2']) ? $_POST['start_time_2'] : '09:30';
            $end2 = !empty($_POST['end_time_2']) ? $_POST['end_time_2'] : '10:30';
            
            $schedules_to_add[] = [
                't' => $_POST['teacher_id_2'], 'l' => $lab2,
                's' => $start2, 'e' => $end2, 'd' => $days2
            ];
        }

        if (!empty($schedules_to_add)) {
            $dayCol = 'schedule_days';
            $chk = $conn->query("SHOW COLUMNS FROM schedule LIKE 'days'");
            if ($chk && $chk->num_rows > 0) $dayCol = 'days';

            $ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
            $sem_id = (int)($_SESSION['active_sem_id'] ?? 0);

            $sched_ins = $conn->prepare("INSERT INTO schedule (lab_id, subject_id, employee_id, time_start, time_end, $dayCol, academic_year_id, semester_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($schedules_to_add as $s) {
                $l_id = (int)$s['l']; $t_id = (int)$s['t'];
                $sched_ins->bind_param("iiisssii", $l_id, $new_subject_id, $t_id, $s['s'], $s['e'], $s['d'], $ay_id, $sem_id);
                $sched_ins->execute();
            }
            $sched_ins->close();
        }

        $message = ($subject_already_exists ? "Schedules updated" : "Subject and schedules created") . " successfully!";
        $_POST = []; // Clear form
    }
    $check->close();
}

// Data for dropdowns
$teachers = $conn->query("SELECT e.employee_id, e.firstname, e.lastname, r.role_name 
                          FROM employees e 
                          JOIN roles r ON e.role_id = r.role_id 
                          WHERE LOWER(r.role_name) IN ('dean','faculty') 
                          ORDER BY e.lastname, e.firstname");
$labs = $conn->query("SELECT lab_id, lab_name FROM facilities ORDER BY lab_name");
?>

<div class="app-content">
    <div class="app-container">
        <div class="flex flex-col sm:flex-row justify-between items-center border-b-2 border-blue-100 pb-5 mb-10 gap-5">
            <h2 class="text-3xl font-bold text-blue-900 flex items-center gap-3">
                <i class="fas fa-book-medical"></i> Add New Subject
            </h2>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <div class="bg-gray-50 p-8 rounded-2xl border border-gray-100">
                <h3 class="text-xl font-bold text-blue-800 mb-6 border-b pb-2"><i class="fas fa-info-circle"></i> Subject Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label class="form-label">Subject Code *</label>
                        <input type="text" id="subject_code" name="subject_code" required class="form-input" placeholder="e.g. COMP101">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subject Name *</label>
                        <input type="text" id="subject_name" name="subject_name" required class="form-input" placeholder="e.g. Computer Science 101">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lecture Units</label>
                        <input type="number" step="1" id="units_lec" name="units_lec" class="form-input" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Laboratory Units</label>
                        <input type="number" step="1" id="units_lab" name="units_lab" class="form-input" placeholder="0">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Schedule Selection 1 -->
                <div class="bg-blue-50/50 p-8 rounded-2xl border border-blue-100">
                    <h3 class="text-xl font-bold text-blue-800 mb-6 border-b border-blue-200 pb-2"><i class="fas fa-clock"></i> Schedule 1</h3>
                    <div class="space-y-4">
                        <div class="form-group">
                            <label class="form-label">Teacher</label>
                            <select name="teacher_id_1" class="form-select">
                                <option value="">Select Teacher</option>
                                <?php $teachers->data_seek(0); while($t = $teachers->fetch_assoc()): ?>
                                    <option value="<?= $t['employee_id'] ?>"><?= htmlspecialchars($t['lastname'].', '.$t['firstname']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Lab / Room</label>
                            <select name="lab_id_1" class="form-select">
                                <option value="">Select Lab</option>
                                <?php $labs->data_seek(0); while($l = $labs->fetch_assoc()): ?>
                                    <option value="<?= $l['lab_id'] ?>"><?= htmlspecialchars($l['lab_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label text-sm">Start Time</label>
                                <input type="time" name="start_time_1" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label text-sm">End Time</label>
                                <input type="time" name="end_time_1" class="form-input">
                            </div>
                        </div>
                        <label class="form-label text-sm">Days</label>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
                                <label class="flex items-center gap-1 bg-white px-3 py-1 rounded-full border border-gray-200 text-sm cursor-pointer hover:bg-blue-50 transition">
                                    <input type="checkbox" name="schedule_days_1[]" value="<?= $d ?>"> <?= $d ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Schedule Selection 2 -->
                <div class="bg-purple-50/50 p-8 rounded-2xl border border-purple-100">
                    <h3 class="text-xl font-bold text-purple-800 mb-6 border-b border-purple-200 pb-2"><i class="fas fa-plus-circle"></i> Schedule 2</h3>
                    <div class="space-y-4">
                        <div class="form-group">
                            <label class="form-label">Teacher</label>
                            <select name="teacher_id_2" class="form-select">
                                <option value="">Select Teacher (Optional)</option>
                                <?php $teachers->data_seek(0); while($t = $teachers->fetch_assoc()): ?>
                                    <option value="<?= $t['employee_id'] ?>"><?= htmlspecialchars($t['lastname'].', '.$t['firstname']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Lab / Room</label>
                            <select name="lab_id_2" class="form-select">
                                <option value="">Select Lab (Optional)</option>
                                <?php $labs->data_seek(0); while($l = $labs->fetch_assoc()): ?>
                                    <option value="<?= $l['lab_id'] ?>"><?= htmlspecialchars($l['lab_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label text-sm">Start Time</label>
                                <input type="time" name="start_time_2" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label text-sm">End Time</label>
                                <input type="time" name="end_time_2" class="form-input">
                            </div>
                        </div>
                        <label class="form-label text-sm">Days</label>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
                                <label class="flex items-center gap-1 bg-white px-3 py-1 rounded-full border border-gray-200 text-sm cursor-pointer hover:bg-purple-50 transition">
                                    <input type="checkbox" name="schedule_days_2[]" value="<?= $d ?>"> <?= $d ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-8">
                <button type="submit" class="btn btn-primary px-12 py-4 text-xl shadow-lg">
                    <i class=""></i> Save Subject and Schedules
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .app-content { margin-left: 20rem; padding: 3rem 1.5rem; min-height:100vh; background: #fbfcfe; }
    .app-container { max-width: 1200px; margin: 0 auto; background: #fff; border-radius: 1.5rem; padding: 3rem; box-shadow: 0 10px 40px rgba(0,0,0,0.03); }
    .form-label { color: #475569; font-weight: 600; font-size: 0.95rem; }
    .form-input, .form-select { border: 1.5px solid #e2e8f0; padding: 0.8rem 1rem; border-radius: 0.8rem; width: 100%; transition: all 0.2s; background: white; }
    .form-input:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); outline: none; }
    .btn { cursor: pointer; border-radius: 0.8rem; font-weight: 700; transition: all 0.2s; }
    .btn-primary { background: #1d4ed8; color: white; border: none; }
    .btn-primary:hover { background: #1e40af; transform: translateY(-2px); }
    .btn-secondary { background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; }
    .btn-secondary:hover { background: #e2e8f0; }
    @media (max-width: 1024px) { .app-content { margin-left: 0; padding: 1rem; } .app-container { padding: 1.5rem; } }
</style>

<script>
    document.getElementById('subject_code').addEventListener('input', function() { this.value = this.value.toUpperCase(); });
</script>

<?php include '../includes/footer.php'; ?>
