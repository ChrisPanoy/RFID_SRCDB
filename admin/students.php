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

// Simple CRUD aligned with src_db.students schema
// students: student_id, rfid_number, profile_picture, first_name, middle_name, last_name, suffix, gender
$msg = '';

// Fetch choices for enrollment
$years_list = $conn->query("SELECT year_id, year_name FROM year_levels ORDER BY level");
$sections_list = $conn->query("SELECT section_id, section_name, level FROM sections WHERE section_name LIKE '%A' OR section_name LIKE '%B' ORDER BY level, section_name");
$course_bsis = $conn->query("SELECT course_id FROM courses WHERE course_code = 'BSIS' LIMIT 1")->fetch_assoc();
$bsis_id = $course_bsis ? (int)$course_bsis['course_id'] : 1;

// Handle CSV Import for basic student fields
if (isset($_POST['import_students'])) {
    if (!isset($_FILES['students_csv']) || $_FILES['students_csv']['error'] !== UPLOAD_ERR_OK) {
        $msg = "<span class='text-red-600 font-semibold'>❌ Upload failed. Please choose a CSV file.</span>";
    } else {
        $tmp = $_FILES['students_csv']['tmp_name'];
        $isCsv = false;
        if (isset($_FILES['students_csv']['name'])) {
            $ext = strtolower(pathinfo($_FILES['students_csv']['name'], PATHINFO_EXTENSION));
            $isCsv = ($ext === 'csv');
        }
        if (!$isCsv) {
            $msg = "<span class='text-red-600 font-semibold'>❌ Invalid file type. Please upload a .csv file.</span>";
        } else {
            $inserted = 0; $updated = 0; $skipped = 0; $line = 0;
            if (($handle = fopen($tmp, 'r')) !== false) {
                // Expected header: student_id, rfid_number, first_name, middle_name, last_name, suffix, gender
                $header = fgetcsv($handle);
                $line++;
                $map = [];
                if ($header) {
                    foreach ($header as $i => $h) {
                        $map[strtolower(trim($h))] = $i;
                    }
                }
                $requiredCols = ['student_id','rfid_number','first_name','last_name','gender'];
                $hasAll = true;
                foreach ($requiredCols as $col) {
                    if (!array_key_exists($col, $map)) { $hasAll = false; break; }
                }
                if (!$hasAll) {
                    $msg = "<span class='text-red-600 font-semibold'>❌ CSV header mismatch. Required: student_id, rfid_number, first_name, last_name, gender.</span>";
                } else {
                    $checkStmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
                    $insertStmt = $conn->prepare("INSERT INTO students (student_id, rfid_number, first_name, middle_name, last_name, suffix, gender) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $updateStmt = $conn->prepare("UPDATE students SET rfid_number = ?, first_name = ?, middle_name = ?, last_name = ?, suffix = ?, gender = ? WHERE student_id = ?");

                    while (($row = fgetcsv($handle)) !== false) {
                        $line++;
                        $student_id  = trim((string)($row[$map['student_id']] ?? ''));
                        $rfid_number = trim((string)($row[$map['rfid_number']] ?? ''));
                        $first_name  = trim((string)($row[$map['first_name']] ?? ''));
                        $middle_name = trim((string)($row[$map['middle_name']] ?? ''));
                        $last_name   = trim((string)($row[$map['last_name']] ?? ''));
                        $suffix      = trim((string)($row[$map['suffix']] ?? ''));
                        $gender      = trim((string)($row[$map['gender']] ?? ''));

                        if ($student_id === '' || $rfid_number === '' || $first_name === '' || $last_name === '' || $gender === '') {
                            $skipped++;
                            continue;
                        }

                        $checkStmt->bind_param('s', $student_id);
                        $checkStmt->execute();
                        $checkStmt->store_result();
                        if ($checkStmt->num_rows > 0) {
                            $updateStmt->bind_param('sssssss', $rfid_number, $first_name, $middle_name, $last_name, $suffix, $gender, $student_id);
                            $updateStmt->execute();
                            $updated++;
                        } else {
                            $insertStmt->bind_param('sssssss', $student_id, $rfid_number, $first_name, $middle_name, $last_name, $suffix, $gender);
                            $insertStmt->execute();
                            $inserted++;
                        }
                    }
                    $checkStmt->close();
                    $insertStmt->close();
                    $updateStmt->close();
                    fclose($handle);
                    $msg = "<span class='text-green-700 font-semibold'>✅ Import done. Inserted: $inserted, Updated: $updated, Skipped: $skipped.</span>";
                }
            } else {
                $msg = "<span class='text-red-600 font-semibold'>❌ Could not open uploaded file.</span>";
            }
        }
    }
}

// Handle Add Student
if (isset($_POST['add'])) {
    $student_id  = trim($_POST['student_id'] ?? '');
    $rfid_number = trim($_POST['rfid_number'] ?? '');
    $first_name  = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $suffix      = trim($_POST['suffix'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');
    $year_level_id = (int)($_POST['year_level_id'] ?? 0);
    $section_id    = (int)($_POST['section_id'] ?? 0);
    $profile_picture = null;

    if ($student_id === '' || $rfid_number === '' || $first_name === '' || $last_name === '' || $gender === '' || $year_level_id === 0 || $section_id === 0) {
        $msg = "<span class='text-red-600 font-semibold'>❌ Please fill in all required fields including Year Level and Section.</span>";
    } else {
        // Check uniqueness of student_id and rfid_number
        $check = $conn->prepare("SELECT 1 FROM students WHERE student_id = ? OR rfid_number = ?");
        $check->bind_param('ss', $student_id, $rfid_number);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $msg = "<span class='text-red-600 font-semibold'>❌ Student ID or RFID already exists.</span>";
        } else {
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $profile_picture = uniqid('student_', true) . '.' . $ext;
                move_uploaded_file($_FILES['profile_picture']['tmp_name'], '../assets/img/' . $profile_picture);
            }
            $stmt = $conn->prepare("INSERT INTO students (student_id, rfid_number, profile_picture, first_name, middle_name, last_name, suffix, gender) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssss', $student_id, $rfid_number, $profile_picture, $first_name, $middle_name, $last_name, $suffix, $gender);
            
            if ($stmt->execute()) {
                // Now also create the admission record using the Active Session
                $ay_id  = isset($_SESSION['active_ay_id']) ? (int)$_SESSION['active_ay_id'] : null;
                $sem_id = isset($_SESSION['active_sem_id']) ? (int)$_SESSION['active_sem_id'] : null;
                
                if ($ay_id && $sem_id) {
                    $insAdm = $conn->prepare("INSERT INTO admissions (student_id, academic_year_id, semester_id, section_id, year_level_id, course_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $insAdm->bind_param('siiiii', $student_id, $ay_id, $sem_id, $section_id, $year_level_id, $bsis_id);
                    $insAdm->execute();
                    $insAdm->close();
                }
                $msg = "<span class='text-green-600 font-semibold'>✅ Student registered and enrolled successfully!</span>";
            } else {
                $msg = "<span class='text-red-600 font-semibold'>❌ Registration failed.</span>";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Handle Edit Student
if (isset($_POST['update'])) {
    $original_student_id = trim($_POST['original_student_id'] ?? '');
    $student_id  = trim($_POST['student_id'] ?? '');
    $rfid_number = trim($_POST['rfid_number'] ?? '');
    $first_name  = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $suffix      = trim($_POST['suffix'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');

    $year_level_id = (int)($_POST['year_level_id'] ?? 0);
    $section_id    = (int)($_POST['section_id'] ?? 0);

    if ($student_id === '' || $rfid_number === '' || $first_name === '' || $last_name === '' || $gender === '') {
        $msg = "<span class='text-red-600 font-semibold'>❌ Please fill in all required fields.</span>";
    } else {
        // Check uniqueness of student_id and rfid_number excluding this record
        $check = $conn->prepare("SELECT 1 FROM students WHERE (student_id = ? OR rfid_number = ?) AND student_id != ?");
        $check->bind_param('sss', $student_id, $rfid_number, $original_student_id);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $msg = "<span class='text-red-600 font-semibold'>❌ Student ID or RFID already exists for another student.</span>";
        } else {
            $profile_picture = null;
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $profile_picture = uniqid('student_', true) . '.' . $ext;
                move_uploaded_file($_FILES['profile_picture']['tmp_name'], '../assets/img/' . $profile_picture);
            }

            if ($profile_picture) {
                $stmt = $conn->prepare("UPDATE students SET student_id = ?, rfid_number = ?, profile_picture = ?, first_name = ?, middle_name = ?, last_name = ?, suffix = ?, gender = ? WHERE student_id = ?");
                $stmt->bind_param('sssssssss', $student_id, $rfid_number, $profile_picture, $first_name, $middle_name, $last_name, $suffix, $gender, $original_student_id);
            } else {
                $stmt = $conn->prepare("UPDATE students SET student_id = ?, rfid_number = ?, first_name = ?, middle_name = ?, last_name = ?, suffix = ?, gender = ? WHERE student_id = ?");
                $stmt->bind_param('ssssssss', $student_id, $rfid_number, $first_name, $middle_name, $last_name, $suffix, $gender, $original_student_id);
            }
            if ($stmt->execute()) {
                // Update or Insert admission for the active session
                $ay_id  = isset($_SESSION['active_ay_id']) ? (int)$_SESSION['active_ay_id'] : null;
                $sem_id = isset($_SESSION['active_sem_id']) ? (int)$_SESSION['active_sem_id'] : null;
                if ($ay_id && $sem_id && $year_level_id > 0 && $section_id > 0) {
                    // Update all current session records for this student
                    $updAdm = $conn->prepare("UPDATE admissions SET section_id = ?, year_level_id = ? WHERE student_id = ? AND academic_year_id = ? AND semester_id = ?");
                    $updAdm->bind_param('iissi', $section_id, $year_level_id, $student_id, $ay_id, $sem_id);
                    $updAdm->execute();
                    
                    // If no records existed at all, create an initial one
                    if ($updAdm->affected_rows == 0) {
                        $chk = $conn->prepare("SELECT 1 FROM admissions WHERE student_id = ? AND academic_year_id = ? AND semester_id = ? LIMIT 1");
                        $chk->bind_param('sii', $student_id, $ay_id, $sem_id);
                        $chk->execute();
                        if ($chk->get_result()->num_rows == 0) {
                            $insAdm = $conn->prepare("INSERT INTO admissions (student_id, academic_year_id, semester_id, section_id, year_level_id, course_id) VALUES (?, ?, ?, ?, ?, ?)");
                            $insAdm->bind_param('siiiii', $student_id, $ay_id, $sem_id, $section_id, $year_level_id, $bsis_id);
                            $insAdm->execute();
                            $insAdm->close();
                        }
                        $chk->close();
                    }
                    $updAdm->close();
                }
                $msg = "<span class='text-green-600 font-semibold'>✅ Student updated successfully!</span>";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Handle Delete Student (by student_id)
if (isset($_GET['delete'])) {
    $delete_sid = trim($_GET['delete']);
    if ($delete_sid !== '') {
        $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
        $stmt->bind_param('s', $delete_sid);
        $stmt->execute();
        $msg = "<span class='text-green-600 font-semibold'>✅ Student deleted successfully!</span>";
        $stmt->close();
    }
}

// Get student data for editing
$edit_student = null;
if (isset($_GET['edit'])) {
    $edit_sid = trim($_GET['edit']);
    if ($edit_sid !== '') {
        $stmt = $conn->prepare("SELECT s.*, 
                                     (SELECT year_level_id FROM admissions WHERE student_id = s.student_id AND academic_year_id = ? AND semester_id = ? LIMIT 1) as cur_year,
                                     (SELECT section_id FROM admissions WHERE student_id = s.student_id AND academic_year_id = ? AND semester_id = ? LIMIT 1) as cur_section
                                FROM students s 
                                WHERE s.student_id = ?");
        $ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
        $sem_id = (int)($_SESSION['active_sem_id'] ?? 0);
        $stmt->bind_param('iiiis', $ay_id, $sem_id, $ay_id, $sem_id, $edit_sid);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_student = $result->fetch_assoc();
        $stmt->close();
    }
}

// Pagination settings for students table
$per_page = 15; // students per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

// Get total students and gender breakdown for statistics & pagination
$stats_sql = "SELECT 
        COUNT(*) AS total_students,
        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) AS male_count,
        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) AS female_count
    FROM students";
$stats_result = $conn->query($stats_sql);
$total_students = 0;
$male_count = 0;
$female_count = 0;
if ($stats_result && $row_stats = $stats_result->fetch_assoc()) {
    $total_students = (int)$row_stats['total_students'];
    $male_count = (int)$row_stats['male_count'];
    $female_count = (int)$row_stats['female_count'];
}

$total_pages = $total_students > 0 ? (int)ceil($total_students / $per_page) : 1;
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}
$offset = ($current_page - 1) * $per_page;

// Fetch students for current page ordered by last name then first name
$students_page = $conn->query("SELECT * FROM students ORDER BY last_name, first_name, student_id LIMIT $per_page OFFSET $offset");
?>

<style>
    /* Maximized content layout - full width utilization */
    .app-content {
        margin-left: 20rem; /* match sidebar width */
        padding: 3rem 1rem 2rem 1rem;
        min-height: 100vh;
        box-sizing: border-box;
        background: linear-gradient(135deg, #0f52d8 0%, #0f52d8 100%);
    }
    .app-container {
        width: 100%; /* Maximize width */
        margin: 0;
        background: #ffffff;
        box-shadow: 0 25px 60px rgba(16,24,40,0.15);
        border-radius: 1.5rem;
        padding: 2.5rem;
        /* Remove max-width restriction for full utilization */
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
    }
    .year-breakdown {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
    }
    .section-breakdown {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.75rem;
    }
    /* Force gradient backgrounds to display properly */
    .stats-card {
        background: linear-gradient(135deg, var(--from-color), var(--to-color)) !important;
        color: white !important;
    }
    .stats-card-purple { --from-color: #7c3aed; --to-color: #4338ca; }
    .stats-card-blue { --from-color: #0f52d8; --to-color: #0f52d8; }
    .stats-card-pink { --from-color: #db2777; --to-color: #be185d; }
    .stats-card-green { --from-color: #059669; --to-color: #047857; }
    .stats-card-orange { --from-color: #ea580c; --to-color: #dc2626; }
    .stats-card-violet { --from-color: #7c2d12; --to-color: #7c3aed; }
    @media (max-width: 900px) {
        .app-content { margin-left: 0; padding: 1.5rem 0.5rem; }
        .app-container { padding: 1.5rem; border-radius: 1rem; }
        .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
    }
</style>

<div class="app-content">
    <div class="app-container">
        <h2 class="text-5xl font-extrabold mb-10 text-center text-indigo-700 tracking-tight">Students Management</h2>

        <?php if (!empty($msg)) echo "<p class='mb-6 text-center text-lg'>$msg</p>"; ?>

        <!-- Simple Statistics -->
        <div class="bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 rounded-3xl p-8 mb-10 border-2 border-indigo-200 shadow-2xl">
            <div class="stats-grid mb-2">
                <div class="stats-card stats-card-purple bg-gradient-to-br from-purple-600 to-indigo-700 rounded-2xl p-6 text-center shadow-2xl text-white transform hover:scale-105 transition-all duration-300 border-2 border-purple-300" style="background: linear-gradient(135deg, #0f52d8, #0f52d8) !important;">
                    <div class="text-5xl font-black mb-2"><?= $total_students ?></div>
                    <div class="text-lg font-bold opacity-95">Total Students</div>
                </div>
                <div class="stats-card stats-card-blue bg-gradient-to-br from-blue-600 to-cyan-700 rounded-2xl p-6 text-center shadow-2xl text-white transform hover:scale-105 transition-all duration-300 border-2 border-blue-300" style="background: linear-gradient(135deg, #0d49c2, #0d49c2) !important;">
                    <div class="text-5xl font-black mb-2"><?= $male_count ?></div>
                    <div class="text-lg font-bold opacity-95">Male</div>
                </div>
                <div class="stats-card stats-card-pink bg-gradient-to-br from-pink-600 to-rose-700 rounded-2xl p-6 text-center shadow-2xl text-white transform hover:scale-105 transition-all duration-300 border-2 border-pink-300" style="background: linear-gradient(135deg, 
#0a3997, 
#0a3997) !important;">
                    <div class="text-5xl font-black mb-2"><?= $female_count ?></div>
                    <div class="text-lg font-bold opacity-95">Female</div>
                </div>
            </div>
        </div>

        <!-- Import Students CSV -->
        <div class="bg-white border border-indigo-100 rounded-2xl p-6 mb-10 shadow-sm">
            <h3 class="text-xl font-bold text-indigo-800 mb-4">Import Students from Excel (CSV)</h3>
            <p class="text-sm text-gray-600 mb-4">Required headings: student_id, rfid_number, first_name, middle_name, last_name, suffix, gender.</p>
            <form method="post" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-4 items-start sm:items-end">
                <input type="file" name="students_csv" accept=".csv" required class="p-3 border-2 border-indigo-200 rounded-lg shadow-sm w-full sm:w-auto">
                <button name="import_students" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-semibold shadow">Import CSV</button>
                <a href="download_students_template.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-semibold shadow">Download Template</a>
            </form>
        </div>

        <!-- Add/Edit Student Form -->
        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-2xl p-8 mb-12 border border-indigo-100">
            <h3 class="text-2xl font-bold text-indigo-800 mb-6 text-center"><?= $edit_student ? 'Edit Student' : 'Add New Student' ?></h3>
            <form method="post" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if ($edit_student): ?>
                    <input type="hidden" name="original_student_id" value="<?= htmlspecialchars($edit_student['student_id']) ?>">
                <?php endif; ?>
                <input type="text" name="student_id" placeholder="Student ID" required class="p-5 border-2 border-indigo-200 rounded-xl shadow-lg focus:ring-2 focus:ring-indigo-400 focus:border-indigo-500 text-lg" value="<?= $edit_student ? htmlspecialchars($edit_student['student_id']) : '' ?>">
                <input type="text" name="rfid_number" placeholder="RFID / Card Number" required class="p-5 border-2 border-indigo-200 rounded-xl shadow-lg focus:ring-2 focus:ring-indigo-400 focus:border-indigo-500 text-lg" value="<?= $edit_student ? htmlspecialchars($edit_student['rfid_number']) : '' ?>">
                <input type="text" name="last_name" placeholder="Last Name" required class="p-5 border-2 border-indigo-200 rounded-xl shadow-lg focus:ring-2 focus:ring-indigo-400 focus:border-indigo-500 text-lg" value="<?= $edit_student ? htmlspecialchars($edit_student['last_name'] ?? '') : '' ?>">
                <input type="text" name="first_name" placeholder="First Name" required class="p-5 border-2 border-indigo-200 rounded-xl shadow-lg focus:ring-2 focus:ring-indigo-400 focus:border-indigo-500 text-lg" value="<?= $edit_student ? htmlspecialchars($edit_student['first_name'] ?? '') : '' ?>">
                <input type="text" name="middle_name" placeholder="Middle Name" class="p-5 border-2 border-indigo-200 rounded-xl shadow-lg focus:ring-2 focus:ring-indigo-400 focus:border-indigo-500 text-lg" value="<?= $edit_student ? htmlspecialchars($edit_student['middle_name'] ?? '') : '' ?>">
                <select name="gender" required class="p-5 border-2 border-indigo-200 rounded-xl shadow-lg focus:ring-2 focus:ring-indigo-400 focus:border-indigo-500 text-lg">
                    <option value="">Select Gender</option>
                    <option value="Male" <?= ($edit_student && $edit_student['gender'] == 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($edit_student && $edit_student['gender'] == 'Female') ? 'selected' : '' ?>>Female</option>
                </select>
                <select name="year_level_id" required class="p-5 border-2 border-indigo-200 rounded-xl shadow-lg focus:ring-2 focus:ring-indigo-400 focus:border-indigo-500 text-lg">
                    <option value="">Select Year Level</option>
                    <?php if ($years_list): while ($y = $years_list->fetch_assoc()): ?>
                        <option value="<?= $y['year_id'] ?>" <?= ($edit_student && isset($edit_student['cur_year']) && $edit_student['cur_year'] == $y['year_id']) ? 'selected' : '' ?>><?= htmlspecialchars($y['year_name']) ?></option>
                    <?php endwhile; endif; ?>
                </select>
                <select name="section_id" required class="p-5 border-2 border-indigo-200 rounded-xl shadow-lg focus:ring-2 focus:ring-indigo-400 focus:border-indigo-500 text-lg">
                    <option value="">Select Section</option>
                    <?php if ($sections_list): while ($s = $sections_list->fetch_assoc()): ?>
                        <option value="<?= $s['section_id'] ?>" data-level="<?= $s['level'] ?>" <?= ($edit_student && isset($edit_student['cur_section']) && $edit_student['cur_section'] == $s['section_id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['section_name']) ?></option>
                    <?php endwhile; endif; ?>
                </select>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Profile Picture (optional)</label>
                    <input type="file" name="profile_picture" accept="image/*" class="p-3 border-2 border-indigo-200 rounded-xl shadow-lg text-sm bg-white" />
                    <?php if ($edit_student && !empty($edit_student['profile_picture'])): ?>
                        <div class="mt-1 flex items-center gap-3">
                            <span class="text-xs text-gray-500">Current:</span>
                            <img src="../assets/img/<?= htmlspecialchars($edit_student['profile_picture']) ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover border border-indigo-200">
                        </div>
                    <?php endif; ?>
                </div>
                <?php
                    // Edit state is handled directly in the form field values using $edit_student
                ?>
                
                <div class="col-span-full flex justify-center gap-6 mt-6">
                    <?php if ($edit_student): ?>
                        <button name="update" class="bg-green-600 hover:bg-green-700 text-white px-8 py-4 rounded-xl shadow-lg text-xl font-bold transition transform hover:scale-105">Update Student</button>
                    <?php else: ?>
                        <button name="add" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-4 rounded-xl shadow-lg text-xl font-bold transition transform hover:scale-105">Add Student</button>
                    <?php endif; ?>
                    <a href="students.php" class="bg-gray-500 hover:bg-gray-600 text-white px-8 py-4 rounded-xl shadow-lg text-xl font-bold transition transform hover:scale-105">Cancel</a>
                </div>
            </form>
        </div>

        <!-- Search Bar -->
        <div class="bg-white p-6 rounded-2xl shadow-xl mb-8 border border-indigo-100">
            <h3 class="text-xl font-bold text-indigo-800 mb-4">Search Students by Last Name</h3>
            <div class="flex flex-col md:flex-row gap-4">
                <input type="text" id="searchInput" placeholder="Enter last name..." 
                       class="flex-grow p-4 border-2 border-indigo-200 rounded-xl shadow-md focus:ring-2 focus:ring-indigo-400 focus:border-indigo-500 text-lg"
                       onkeyup="searchStudents()">
                <button onclick="searchStudents()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-4 rounded-xl font-semibold shadow-lg transition transform hover:scale-105">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
                <button onclick="resetSearch()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-4 rounded-xl font-semibold shadow-lg transition transform hover:scale-105">
                    <i class="fas fa-undo mr-2"></i> Reset
                </button>
            </div>
            <div id="searchResults" class="mt-4"></div>
        </div>


        <!-- Student Records (simple table) -->
        <div class="space-y-6" id="studentsList">
            <div class="border-2 border-indigo-200 rounded-2xl shadow-xl bg-gradient-to-r from-indigo-50 to-purple-50">
                <div class="w-full flex justify-between items-center px-8 py-6 font-bold text-indigo-800 text-xl rounded-t-2xl">
                    <span>All Students</span>
                </div>
                <div class="p-6">
                    <div class="table-responsive border border-indigo-100 rounded-xl overflow-hidden shadow-lg bg-white">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-indigo-600">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-50 uppercase tracking-wider">Profile</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-50 uppercase tracking-wider">Student ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-50 uppercase tracking-wider">RFID</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-50 uppercase tracking-wider">Last Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-50 uppercase tracking-wider">First Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-50 uppercase tracking-wider">Middle Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-50 uppercase tracking-wider">Gender</th>
                                    <th class="px-6 py-3 text-center text-xs font-semibold text-indigo-50 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php if ($students_page && $students_page->num_rows > 0): ?>
                                    <?php while ($row = $students_page->fetch_assoc()): ?>
                                        <tr class="hover:bg-indigo-50 transition-colors">
                                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">
                                                <?php if (!empty($row['profile_picture'])): ?>
                                                    <img src="../assets/img/<?= htmlspecialchars($row['profile_picture']) ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border border-indigo-200">
                                                <?php else: ?>
                                                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-xs text-gray-500">N/A</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($row['student_id']) ?>
                                            </td>
                                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($row['rfid_number']) ?>
                                            </td>
                                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($row['last_name']) ?>
                                            </td>
                                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($row['first_name']) ?>
                                            </td>
                                            <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($row['middle_name']) ?>
                                            </td>
                                            <td class="px-6 py-3 whitespace-nowrap text-sm">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                                                    <?= strcasecmp($row['gender'], 'Male') === 0 ? 'bg-blue-100 text-blue-700' : (strcasecmp($row['gender'], 'Female') === 0 ? 'bg-pink-100 text-pink-700' : 'bg-gray-100 text-gray-700') ?>
                                                ">
                                                    <?= htmlspecialchars($row['gender']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-3 whitespace-nowrap text-sm text-center">
                                                <a href="students.php?edit=<?= urlencode($row['student_id']) ?>" class="inline-flex items-center px-3 py-1.5 rounded-full bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-semibold mr-2 border border-indigo-200">
                                                    <i class="fas fa-edit mr-1"></i> Edit
                                                </a>
                                                <a href="students.php?delete=<?= urlencode($row['student_id']) ?>" class="inline-flex items-center px-3 py-1.5 rounded-full bg-red-50 text-red-700 hover:bg-red-100 text-xs font-semibold border border-red-200" onclick="return confirm('Are you sure you want to delete this student?');">
                                                    <i class="fas fa-trash mr-1"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-6 text-center text-gray-500 text-sm">No students found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="text-sm text-gray-600">
                                Showing
                                <span class="font-semibold"><?= $total_students > 0 ? ($offset + 1) : 0 ?></span>
                                to
                                <span class="font-semibold"><?= min($offset + $per_page, $total_students) ?></span>
                                of
                                <span class="font-semibold"><?= $total_students ?></span>
                                students
                            </div>
                            <nav class="inline-flex rounded-lg shadow-sm" aria-label="Pagination">
                                <a href="<?= $current_page > 1 ? 'students.php?page=' . ($current_page - 1) : 'javascript:void(0);' ?>"
                                   class="relative inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-l-lg <?= $current_page == 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                                    ‹ Prev
                                </a>
                                <?php
                                // Simple windowed pagination
                                $window = 2;
                                $start = max(1, $current_page - $window);
                                $end = min($total_pages, $current_page + $window);
                                if ($start > 1) {
                                    echo '<a href="students.php?page=1" class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                                    if ($start > 2) {
                                        echo '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-400">...</span>';
                                    }
                                }
                                for ($i = $start; $i <= $end; $i++) {
                                    if ($i == $current_page) {
                                        echo '<span class="relative inline-flex items-center px-3 py-2 border border-indigo-500 bg-indigo-50 text-sm font-semibold text-indigo-700">' . $i . '</span>';
                                    } else {
                                        echo '<a href="students.php?page=' . $i . '" class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $i . '</a>';
                                    }
                                }
                                if ($end < $total_pages) {
                                    if ($end < $total_pages - 1) {
                                        echo '<span class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-400">...</span>';
                                    }
                                    echo '<a href="students.php?page=' . $total_pages . '" class="relative inline-flex items-center px-3 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $total_pages . '</a>';
                                }
                                ?>
                                <a href="<?= $current_page < $total_pages ? 'students.php?page=' . ($current_page + 1) : 'javascript:void(0);' ?>"
                                   class="relative inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-r-lg <?= $current_page == $total_pages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50' ?>">
                                    Next ›
                                </a>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Simple show/hide for the students list container
function showAll() {
    var list = document.getElementById('studentsList');
    if (list) list.style.display = 'block';
}

function hideAll() {
    var list = document.getElementById('studentsList');
    if (list) list.style.display = 'none';
}

// Search functionality
function searchStudents() {
    const searchTerm = document.getElementById('searchInput').value.trim().toLowerCase();
    const resultsContainer = document.getElementById('searchResults');
    
    if (searchTerm === '') {
        resultsContainer.innerHTML = '<div class="text-red-500">Please enter a last name to search.</div>';
        return;
    }

    // Show loading state
    resultsContainer.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-2xl text-indigo-600"></i><p class="mt-2">Searching...</p></div>';

    // Create AJAX request
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'search_students.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        if (this.status === 200) {
            try {
                const response = JSON.parse(this.responseText);
                displaySearchResults(response);
            } catch (e) {
                resultsContainer.innerHTML = '<div class="text-red-500">Error processing results. Please try again.</div>';
                console.error('Error parsing JSON:', e);
            }
        } else {
            resultsContainer.innerHTML = '<div class="text-red-500">Error: ' + this.statusText + '</div>';
        }
    };
    
    xhr.onerror = function() {
        resultsContainer.innerHTML = '<div class="text-red-500">Network error. Please check your connection.</div>';
    };
    
    xhr.send('last_name=' + encodeURIComponent(searchTerm));
}

function displaySearchResults(students) {
    const resultsContainer = document.getElementById('searchResults');
    
    if (students.error) {
        resultsContainer.innerHTML = '<div class="text-red-500">' + students.error + '</div>';
        return;
    }
    
    if (!Array.isArray(students) || students.length === 0) {
        resultsContainer.innerHTML = '<div class="text-gray-600 text-center py-4">No students found with that last name.</div>';
        return;
    }
    
    let html = `
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-indigo-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Student ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">RFID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
    `;
    
    students.forEach(student => {
        const lastName = student.last_name || '';
        const firstName = student.first_name || '';
        const middleName = student.middle_name || '';
        const rfid = student.rfid_number || '';
        const sid = student.student_id || '';

        html += `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${sid}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${rfid}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <div class="font-medium">${lastName}, ${firstName} ${middleName}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <a href="students.php?edit=${encodeURIComponent(sid)}" class="text-indigo-600 hover:text-indigo-900 mr-4">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="../student/student_profile.php?id=${encodeURIComponent(sid)}" class="text-blue-600 hover:text-blue-900">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div class="mt-4 text-sm text-gray-500">
            Found ${students.length} student(s) matching your search.
        </div>
    `;
    
    resultsContainer.innerHTML = html;
}

function resetSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('searchResults').innerHTML = '';
    showAll();
}

// Add keyboard event listener for Enter key
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchStudents();
    }
});
// Section filtering by Year Level
document.addEventListener('DOMContentLoaded', function() {
    const yearSelect = document.querySelector('select[name="year_level_id"]');
    const sectionSelect = document.querySelector('select[name="section_id"]');
    
    if (yearSelect && sectionSelect) {
        yearSelect.addEventListener('change', function() {
            const selectedLevel = this.value; 
            const sections = sectionSelect.querySelectorAll('option');
            
            sections.forEach(opt => {
                if (opt.value === "") {
                    opt.style.display = 'block';
                    return;
                }
                const sectionLevel = opt.getAttribute('data-level');
                if (selectedLevel === "" || sectionLevel === selectedLevel) {
                    opt.style.display = 'block';
                } else {
                    opt.style.display = 'none';
                }
            });
            
            // Reset section if current selection is now hidden
            const currentOption = sectionSelect.options[sectionSelect.selectedIndex];
            if (currentOption && currentOption.style.display === 'none') {
                sectionSelect.value = "";
            }
        });
        
        // Trigger once for Edit mode
        if (yearSelect.value !== "") {
            yearSelect.dispatchEvent(new Event('change'));
        }
    }
});
</script>

<!-- JsBarcode -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode/dist/JsBarcode.all.min.js"></script>
<script>
<?php
$students = $conn->query("SELECT * FROM students");
while ($row = $students->fetch_assoc()) {
    echo "JsBarcode('#barcode-{$row['id']}', '{$row['barcode']}', {format: 'CODE128', width:2, height:40, displayValue:true});\n";
}
?>
</script>
<?php include '../includes/footer.php'; ?>




