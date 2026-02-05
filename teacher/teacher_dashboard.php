<?php
session_start();
include '../includes/db.php';

// Redirect if not logged in as teacher
if (!isset($_SESSION['teacher_id'])) {
    header("Location: teacher_login.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id']; // this is employees.employee_id
$teacher_name = $_SESSION['teacher_name'];
$teacher_id_int = (int)$teacher_id;

// Fetch teacher profile picture from employees table (teacher_id holds employee_id)
$profile_pic = null;
$profile_stmt = $conn->prepare("SELECT profile_pic FROM employees WHERE employee_id = ?");
$profile_stmt->bind_param("s", $teacher_id);
$profile_stmt->execute();
$profile_stmt->bind_result($profile_pic);
$profile_stmt->fetch();
$profile_stmt->close();

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
    $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
    $new_pic = uniqid('teacher_', true) . '.' . $ext;
    move_uploaded_file($_FILES['profile_pic']['tmp_name'], '../assets/img/' . $new_pic);
    // Update profile picture in employees table
    $update_pic = $conn->prepare("UPDATE employees SET profile_pic=? WHERE employee_id=?");
    $update_pic->bind_param("ss", $new_pic, $teacher_id);

    $update_pic->execute();
    $profile_pic = $new_pic;
}

// -------------------------------
// DATA USING src_db SCHEMA
// -------------------------------

// Detect if 'days' column exists in schedule table
$dayColumn = 'schedule_days';
$checkCol = $conn->query("SHOW COLUMNS FROM schedule LIKE 'days'");
if ($checkCol && $checkCol->num_rows > 0) {
    $dayColumn = 'days';
}

// Get active session
$ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
$sem_id = (int)($_SESSION['active_sem_id'] ?? 0);

// Get teacher's schedules and group them by subject
$subjects_sql = "
    SELECT 
        sc.schedule_id,
        sc.lab_id,
        subj.subject_id,
        subj.subject_code,
        subj.subject_name,
        sc.time_start AS start_time,
        sc.time_end   AS end_time,
        sc.$dayColumn AS schedule_days,
        fac.lab_name  AS lab_name,
        (SELECT COUNT(DISTINCT student_id) FROM admissions WHERE schedule_id = sc.schedule_id) AS total_students
    FROM schedule sc
    JOIN subjects subj   ON sc.subject_id = subj.subject_id
    LEFT JOIN facilities fac ON sc.lab_id = fac.lab_id
    WHERE sc.employee_id = ? 
      AND sc.academic_year_id = ? 
      AND sc.semester_id = ?
    ORDER BY subj.subject_name, sc.schedule_id
";
$subjects_query = $conn->prepare($subjects_sql);
$subjects_query->bind_param("iii", $teacher_id_int, $ay_id, $sem_id);
$subjects_query->execute();
$subjects_res = $subjects_query->get_result();

$grouped_subjects = [];
while ($row = $subjects_res->fetch_assoc()) {
    $sid = $row['subject_id'];
    if (!isset($grouped_subjects[$sid])) {
        $grouped_subjects[$sid] = [
            'subject_id' => $sid,
            'subject_code' => $row['subject_code'],
            'subject_name' => $row['subject_name'],
            'schedules' => []
        ];
    }
    $grouped_subjects[$sid]['schedules'][] = $row;
}
$subjects_list = array_values($grouped_subjects);
$subject_count = count($subjects_list);

// Determine the initial subject index: prefer today's classes (ongoing now first); else earliest today; else fallback to any ongoing/earliest
$initial_subject_index = 0;
$nowTime = date('H:i:s');
$bestStartAny = null; $bestIdxAny = 0; $foundOngoingAny = false;
$bestStartToday = null; $bestIdxToday = null; $foundOngoingToday = false;
$todayAbbrev = date('D'); // Mon, Tue, ...

foreach ($subjects_list as $idx => $s) {
    $st = $s['start_time'] ?? null;
    $en = $s['end_time'] ?? null;
    $daysStr = $s['schedule_days'] ?? '';
    $days = array_filter(array_map('trim', explode(',', (string)$daysStr)));
    $isToday = empty($days) ? true : in_array($todayAbbrev, $days, true);

    if ($st && $en) {
        $isOngoing = ($en < $st && ($nowTime >= $st || $nowTime <= $en)) || ($en >= $st && $nowTime >= $st && $nowTime <= $en);

        // Track any
        if ($isOngoing && !$foundOngoingAny) { $foundOngoingAny = true; $initial_subject_index = $idx; }
        if ($bestStartAny === null || ($st < $bestStartAny)) { $bestStartAny = $st; $bestIdxAny = $idx; }

        // Track today
        if ($isToday) {
            if ($isOngoing && !$foundOngoingToday) { $foundOngoingToday = true; $initial_subject_index = $idx; }
            if ($bestStartToday === null || ($st < $bestStartToday)) { $bestStartToday = $st; $bestIdxToday = $idx; }
        }
    }
}

if ($foundOngoingToday) {
    // initial_subject_index already set to ongoing today
} elseif ($bestIdxToday !== null) {
    $initial_subject_index = $bestIdxToday;
} elseif ($foundOngoingAny) {
    // initial_subject_index already set to any ongoing
} else {
    $initial_subject_index = $bestIdxAny;
}

// Load all labs from facility so teachers can reassign labs per schedule
$labs_options = [];
if ($labs_res = $conn->query("SELECT lab_id, lab_name FROM facilities ORDER BY lab_name")) {
    while ($lab_row = $labs_res->fetch_assoc()) {
        $labs_options[] = $lab_row;
    }
}

// Get today's attendance statistics using attendance_date/time_in/time_out
$today = date('Y-m-d');
$attendance_stats = [
    'Time In' => 0,
    'Time Out' => 0,
];

$attendance_overview_sql = "
    SELECT 
        SUM(CASE WHEN a.time_in IS NOT NULL THEN 1 ELSE 0 END) AS time_in_count,
        SUM(CASE WHEN a.time_out IS NOT NULL THEN 1 ELSE 0 END) AS time_out_count
    FROM attendance a
    JOIN admissions adm ON a.admission_id = adm.admission_id
    JOIN schedule sc ON adm.schedule_id = sc.schedule_id
    WHERE a.attendance_date = ?
      AND sc.employee_id = ?
";
$attendance_overview_query = $conn->prepare($attendance_overview_sql);
$attendance_overview_query->bind_param('si', $today, $teacher_id_int);
$attendance_overview_query->execute();
$overview_row = $attendance_overview_query->get_result()->fetch_assoc();
if ($overview_row) {
    $attendance_stats['Time In'] = (int)($overview_row['time_in_count'] ?? 0);
    $attendance_stats['Time Out'] = (int)($overview_row['time_out_count'] ?? 0);
}

// Get attendance for this week (Mon-Fri)
$weekly_trends = [];
$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
foreach ($days as $day) {
    $date = date('Y-m-d', strtotime($day . ' this week'));
    $trend_sql = "
        SELECT 
            SUM(CASE WHEN a.time_in IS NOT NULL THEN 1 ELSE 0 END) AS signed_in,
            SUM(CASE WHEN a.time_out IS NOT NULL THEN 1 ELSE 0 END) AS signed_out
        FROM attendance a
        JOIN admissions adm ON a.admission_id = adm.admission_id
        JOIN schedule sc ON adm.schedule_id = sc.schedule_id
        WHERE a.attendance_date = ?
          AND sc.employee_id = ?
    ";
    $trend_query = $conn->prepare($trend_sql);
    $trend_query->bind_param('si', $date, $teacher_id_int);
    $trend_query->execute();
    $result = $trend_query->get_result()->fetch_assoc();
    $weekly_trends[$day] = [
        'signed_in' => (int)($result['signed_in'] ?? 0),
        'signed_out' => (int)($result['signed_out'] ?? 0),
    ];
}

$ay_id  = (int)($_SESSION['active_ay_id'] ?? 0);
$sem_id = (int)($_SESSION['active_sem_id'] ?? 0);

$subject_distribution_sql = "
    SELECT 
        subj.subject_name,
        COUNT(DISTINCT adm.student_id) AS student_count
    FROM schedule sc
    JOIN subjects subj ON sc.subject_id = subj.subject_id
    LEFT JOIN admissions adm ON adm.schedule_id = sc.schedule_id
    WHERE sc.employee_id = ? AND sc.academic_year_id = ? AND sc.semester_id = ?
    GROUP BY subj.subject_id, subj.subject_name
    ORDER BY student_count DESC
    LIMIT 4
";
$subject_distribution_query = $conn->prepare($subject_distribution_sql);
$subject_distribution_query->bind_param('iii', $teacher_id_int, $ay_id, $sem_id);
$subject_distribution_query->execute();
$subject_distribution = $subject_distribution_query->get_result();

$subject_labels = [];
$subject_data = [];
while ($row = $subject_distribution->fetch_assoc()) {
    $subject_labels[] = $row['subject_name'];
    $subject_data[] = (int)$row['student_count'];
}

// Present count per subject for today (split by gender)
$subject_gender_labels = [];
$subject_male_counts = [];
$subject_female_counts = [];

// Seed with all subjects so charts show zero bars when no attendance yet
foreach ($subjects_list as $s) {
    $nm = $s['subject_name'] ?? null;
    if ($nm && !in_array($nm, $subject_gender_labels, true)) {
        $subject_gender_labels[] = $nm;
        $subject_male_counts[] = 0;
        $subject_female_counts[] = 0;
    }
}

$present_sql = "
    SELECT 
        subj.subject_name,
        LOWER(TRIM(st.gender)) AS gkey,
        SUM(CASE WHEN a.attendance_id IS NOT NULL THEN 1 ELSE 0 END) AS present_count
    FROM schedule sc
    JOIN subjects subj ON sc.subject_id = subj.subject_id
    LEFT JOIN admissions adm ON adm.schedule_id = sc.schedule_id
    LEFT JOIN students st ON adm.student_id = st.student_id
    LEFT JOIN attendance a 
        ON a.admission_id = adm.admission_id 
       AND a.attendance_date = ? 
       AND a.status IN ('Present','Late')
    WHERE sc.employee_id = ? AND sc.academic_year_id = ? AND sc.semester_id = ?
    GROUP BY subj.subject_id, subj.subject_name, LOWER(TRIM(st.gender))
    ORDER BY subj.subject_name ASC
";
$present_stmt = $conn->prepare($present_sql);
$present_stmt->bind_param('siii', $today, $teacher_id_int, $ay_id, $sem_id);

$present_stmt->execute();
$present_res = $present_stmt->get_result();
while ($r = $present_res->fetch_assoc()) {
    $subject = $r['subject_name'];
    $gkey    = $r['gkey'] ?? '';
    $cnt     = (int)($r['present_count'] ?? 0);

    $bucket = null;
    if ($gkey === 'male' || $gkey === 'm') {
        $bucket = 'male';
    } elseif ($gkey === 'female' || $gkey === 'f') {
        $bucket = 'female';
    }
    if ($bucket === null) {
        continue;
    }

    $index = array_search($subject, $subject_gender_labels, true);
    if ($index === false) {
        $subject_gender_labels[] = $subject;
        $subject_male_counts[] = 0;
        $subject_female_counts[] = 0;
        $index = count($subject_gender_labels) - 1;
    }

    if ($bucket === 'male') {
        $subject_male_counts[$index] += $cnt;
    } elseif ($bucket === 'female') {
        $subject_female_counts[$index] += $cnt;
    }
}
$present_stmt->close();

$gender_sql = "
    SELECT 
        LOWER(TRIM(st.gender)) AS gkey,
        COUNT(DISTINCT adm.student_id) AS cnt
    FROM attendance a
    JOIN admissions adm ON a.admission_id = adm.admission_id
    JOIN students st ON adm.student_id = st.student_id
    JOIN schedule sc ON adm.schedule_id = sc.schedule_id
    WHERE a.attendance_date = ?
      AND a.status IN ('Present','Late')
      AND sc.employee_id = ?
    GROUP BY LOWER(TRIM(st.gender))
";

$present_gender = ['boys' => 0, 'girls' => 0];
$gender_stmt = $conn->prepare($gender_sql);
if ($gender_stmt) {
    $gender_stmt->bind_param('si', $today, $teacher_id_int);
    if ($gender_stmt->execute()) {
        $g_res = $gender_stmt->get_result();
        while ($g_row = $g_res->fetch_assoc()) {
            $gkey = $g_row['gkey'] ?? '';
            $cnt  = (int)($g_row['cnt'] ?? 0);
            if ($gkey === 'male' || $gkey === 'm') {
                $present_gender['boys'] += $cnt;
            } elseif ($gkey === 'female' || $gkey === 'f') {
                $present_gender['girls'] += $cnt;
            }
        }
    }
    $gender_stmt->close();
}

$today_attendance = $attendance_stats['Time In'] + $attendance_stats['Time Out'];

// Total distinct students under this teacher (across all schedules)
$total_students = 0;
$total_students_sql = "
    SELECT COUNT(DISTINCT adm.student_id) AS count
    FROM admissions adm
    JOIN schedule sc ON adm.schedule_id = sc.schedule_id
    WHERE sc.employee_id = ? AND sc.academic_year_id = ? AND sc.semester_id = ?
";
$total_students_query = $conn->prepare($total_students_sql);
$total_students_query->bind_param('iii', $teacher_id_int, $ay_id, $sem_id);
$total_students_query->execute();
$total_students_result = $total_students_query->get_result();
if ($total_students_row = $total_students_result->fetch_assoc()) {
    $total_students = (int)$total_students_row['count'];
}
$total_students_query->close();

// Subject count = number of schedules for this employee
$subject_count = count($subjects_list);

// Handle dual schedule update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
    
    // Process up to 2 schedules
    for ($i = 1; $i <= 2; $i++) {
        $sched_id = isset($_POST["sched_id_$i"]) ? (int)$_POST["sched_id_$i"] : 0;
        $start_time = $_POST["start_time_$i"] ?? null;
        $end_time = $_POST["end_time_$i"] ?? null;
        $lab_id = isset($_POST["lab_id_$i"]) ? (int)$_POST["lab_id_$i"] : 0;
        $days = isset($_POST["schedule_days_$i"]) && is_array($_POST["schedule_days_$i"]) ? implode(',', $_POST["schedule_days_$i"]) : '';

        if ($sched_id > 0) {
            // Update existing - validate lab_id
            if ($lab_id <= 0) {
                // Get first available lab as fallback
                $lab_res = $conn->query("SELECT lab_id FROM facilities LIMIT 1");
                if ($lab_res && $lab_row = $lab_res->fetch_assoc()) {
                    $lab_id = (int)$lab_row['lab_id'];
                }
            }
            
            $dayCol = 'schedule_days';
            if ($chk = $conn->query("SHOW COLUMNS FROM schedule LIKE 'days'")) {
                if ($chk->num_rows > 0) $dayCol = 'days';
            }
            $upd = $conn->prepare("UPDATE schedule SET time_start=?, time_end=?, $dayCol=?, lab_id=? WHERE schedule_id=? AND employee_id=?");
            $upd->bind_param("sssiii", $start_time, $end_time, $days, $lab_id, $sched_id, $teacher_id_int);
            $upd->execute();
            $upd->close();
        } elseif (!empty($start_time) && !empty($end_time) && $subject_id > 0) {
            // Add new if slot was empty - validate lab_id
            if ($lab_id <= 0) {
                // Get first available lab as fallback
                $lab_res = $conn->query("SELECT lab_id FROM facility LIMIT 1");
                if ($lab_res && $lab_row = $lab_res->fetch_assoc()) {
                    $lab_id = (int)$lab_row['lab_id'];
                } else {
                    // Skip this schedule if no labs exist
                    continue;
                }
            }
            
            $dayCol = 'schedule_days';
            if ($chk = $conn->query("SHOW COLUMNS FROM schedule LIKE 'days'")) {
                if ($chk->num_rows > 0) $dayCol = 'days';
            }
            $ay_id = (int)($_SESSION['active_ay_id'] ?? 0);
            $sem_id = (int)($_SESSION['active_sem_id'] ?? 0);
            $ins = $conn->prepare("INSERT INTO schedule (lab_id, subject_id, employee_id, time_start, time_end, $dayCol, academic_year_id, semester_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("iiisssii", $lab_id, $subject_id, $teacher_id_int, $start_time, $end_time, $days, $ay_id, $sem_id);
            $ins->execute();
            $ins->close();
        }
    }
    $_SESSION['success_message'] = "Schedules updated successfully!";
    header("Location: teacher_dashboard.php");
    exit();
}

// Fetch the latest attendance scan using attendance/admission/schedule/subject/students
$studentName = 'No recent scan';

$studentId = '';
$section = '';
$yearLevel = '';
$status = '';
$subjectName = '';
$profilePicPath = '../assets/img/logo.png';
$scanTimeDisplay = '--:--:--';

$latest_stmt = $conn->prepare("
    SELECT 
        a.*, 
        st.student_id,
        st.first_name,
        st.middle_name,
        st.last_name,
        st.profile_picture,
        subj.subject_name,
        sec.section_name,
        yl.year_name,
        pa.pc_number
    FROM attendance a
    JOIN admissions adm ON a.admission_id = adm.admission_id
    JOIN students st ON adm.student_id = st.student_id
    JOIN schedule sc ON adm.schedule_id = sc.schedule_id
    JOIN subjects subj ON sc.subject_id = subj.subject_id
    LEFT JOIN sections sec ON adm.section_id = sec.section_id
    LEFT JOIN year_levels yl ON adm.year_level_id = yl.year_id
    LEFT JOIN pc_assignment pa ON pa.student_id = st.student_id AND pa.lab_id = sc.lab_id
    WHERE sc.employee_id = ?
    ORDER BY a.attendance_date DESC, COALESCE(a.time_out, a.time_in) DESC
    LIMIT 1
");
if ($latest_stmt) {
    $latest_stmt->bind_param('i', $teacher_id_int);
    $latest_stmt->execute();
    $latest_res = $latest_stmt->get_result();
    if ($latest_res && $row = $latest_res->fetch_assoc()) {
        $full_name_parts = [
            trim($row['first_name'] ?? ''),
            trim($row['middle_name'] ?? ''),
            trim($row['last_name'] ?? ''),
        ];
        $studentName = trim(implode(' ', array_filter($full_name_parts))) ?: 'No recent scan';
        $studentId = $row['student_id'] ?? '';
        $section = $row['section_name'] ?? '';
        $yearLevel = $row['year_name'] ?? '';
        $status = $row['status'] ?? '';
        $subjectName = $row['subject_name'] ?? '';
        $student_pic = $row['profile_picture'] ?? '';
        $profilePicPath = !empty($student_pic) ? '../assets/img/' . $student_pic : '../assets/img/logo.png';
        $pcNumber = $row['pc_number'] ?? '';

        $time_component = $row['time_out'] ?? $row['time_in'] ?? null;
        if (!empty($row['attendance_date']) && $time_component) {
            $scanTimestamp = $row['attendance_date'] . ' ' . $time_component;
            $scanTimeDisplay = date('g:i:s A', strtotime($scanTimestamp));
        } else {
            $scanTimeDisplay = '--:--:--';
        }
    }
    $latest_stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Attendance System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Teacher specific adjustments */
        .status-badge { padding: 0.45rem 0.9rem; border-radius: 9999px; color: #fff; font-weight: 700; display: inline-block; }
        .status-present { background: linear-gradient(90deg,#06b6d4 0%,#10b981 100%); }
        .status-signedout { background: linear-gradient(90deg,#fb7185 0%,#ef4444 100%); }
        .status-other { background: linear-gradient(90deg,#9ca3af 0%,#6b7280 100%); }
        
        .pro-header {
            background: #2563eb !important; /* Royal Blue / Strong Blue */
            background-image: none !important;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex">
    <!-- Shared sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 min-h-screen main-content lg:ml-80 transition-all duration-300">
        <header class="shadow-xl sticky top-0 z-10 w-full backdrop-blur-sm pro-header">
            <div class="w-full px-4 sm:px-6 lg:px-8 py-4 relative">
                <div class="w-full px-6 sm:px-8 lg:px-12 flex flex-col justify-center items-center py-4 relative">
                    <h1 class="text-3xl font-bold text-white" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"> Welcome, Faculty <?= htmlspecialchars($teacher_name) ?> </h1>
                    <div class="hidden sm:flex items-center gap-2 bg-white/90 text-blue-700 rounded-full px-4 py-1 absolute right-6 top-1/2 -translate-y-1/2 shadow">
                        <i class="fas fa-clock"></i>
                        <span id="top-clock" class="font-semibold">--:--:-- --</span>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $_SESSION['success_message'] ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $_SESSION['error_message'] ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Left Column: Analytics -->
                <div class="lg:w-10/12 overflow-hidden">
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-2 xl:grid-cols-3 gap-6 justify-center items-stretch py-6 px-6 bg-gradient-to-br from-purple-50 to-blue-50 rounded-2xl shadow-inner mb-6">
                        <div class="flex flex-col items-start justify-between bg-white rounded-2xl shadow-lg p-6 min-h-[160px] border border-blue-100 transform transition-all duration-300 hover:scale-105 hover:shadow-xl">
                            <div class="mb-2 flex items-center justify-center w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg text-white text-xl shadow">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="font-semibold text-blue-700 mb-1">Total Students</div>
                            <div class="text-3xl font-bold text-gray-800 mt-auto"><?= $total_students ?></div>
                        </div>
                        <div class="flex flex-col items-start justify-between bg-white rounded-2xl shadow-lg p-6 min-h-[160px] border border-blue-100">
                            <div class="mb-2 flex items-center justify-center w-10 h-10 bg-gradient-to-r from-green-500 to-green-600 rounded-lg text-white text-xl shadow">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="font-semibold text-blue-700 mb-1">Time in Time Out</div>
                            <div id="todayAttendance" class="text-3xl font-bold text-gray-800 mt-auto"><?= $today_attendance ?></div>
                        </div>
                        <div class="flex flex-col items-start justify-between bg-white rounded-2xl shadow-lg p-6 min-h-[160px] border border-blue-100">
                            <div class="mb-2 flex items-center justify-center w-10 h-10 bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg text-white text-xl shadow">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="font-semibold text-blue-700 mb-1">My Subjects</div>
                            <div class="text-3xl font-bold text-gray-800 mt-auto"><?= $subject_count ?></div>
                        </div>
                    </div>

               

                    <!-- Analytics Overview -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <!-- Attendance Overview Card -->
                        <div class="bg-white rounded-xl shadow-lg p-4 border border-gray-200">

                            <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                                <i class="fas fa-chart-pie text-blue-500 text-lg"></i>
                                <span class="bg-gradient-to-r from-blue-500 to-indigo-500 bg-clip-text text-transparent">Attendance Overview</span>
                            </h3>
                            <div class="h-[520px] flex items-center justify-center p-4 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg">
                                <canvas id="attendanceChart" height="420" style="max-height:420px; width:100%;"></canvas>
                            </div>
                        </div>

                        <!-- Present Per Subject Card -->
                        <div class="bg-white rounded-2xl shadow-lg p-6 border border-gray-200">

                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                                    <i class="fas fa-pie-chart text-green-500"></i> Present Per Subject (Today)
                                </h3>
                                <div class="text-sm text-gray-600">
                                    <div id="currentDate" class="font-medium"></div>
                                </div>
                            </div>
                            <div class="h-[520px] flex items-center justify-center">
                                <canvas id="trendsChart" height="420" style="max-height:420px; width:100%;"></canvas>
                            </div>
                        </div>
                    </div>

                    <script>
                        let attendanceChart, trendsChart, subjectChart;
                        const POLL_INTERVAL_MS = 2000;
                        let pollDelay = POLL_INTERVAL_MS;

                        // Common chart options
                        const commonChartOptions = {

                            responsive: true,
                            maintainAspectRatio: false,
                            layout: {
                                padding: { top: 10, right: 8, bottom: 10, left: 8 }
                            },
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    align: 'center',
                                    labels: {
                                        padding: 12,
                                        boxWidth: 16,
                                        boxHeight: 8,
                                        usePointStyle: true,
                                        pointStyle: 'rectRounded',
                                        font: {
                                            size: 12,
                                            family: '"Inter", "Segoe UI", Arial, sans-serif',
                                            weight: 500
                                        }
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                                    titleColor: '#1e40af',
                                    titleFont: {
                                        size: 14,
                                        weight: 'bold'
                                    },
                                    bodyColor: '#334155',
                                    bodyFont: {
                                        size: 13
                                    },
                                    borderColor: '#e2e8f0',
                                    borderWidth: 1,
                                    padding: 12,
                                    boxPadding: 6,
                                    usePointStyle: true
                                }
                            }
                        };

                        // Center text plugin for doughnut charts
                        const centerTextPlugin = {
                            id: 'centerText',
                            afterDraw(chart, args, pluginOptions) {
                                const text = chart.config._centerText;
                                if (!text) return;
                                const {ctx, chartArea} = chart;
                                const cx = (chartArea.left + chartArea.right) / 2;
                                const cy = (chartArea.top + chartArea.bottom) / 2;
                                ctx.save();
                                ctx.font = '600 16px "Inter", "Segoe UI", Arial, sans-serif';
                                ctx.fillStyle = '#1f2937';
                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'middle';
                                ctx.fillText(String(text), cx, cy);
                                ctx.restore();
                            }
                        };
                        if (window.Chart && Chart.register) { Chart.register(centerTextPlugin); }

                        // Guard: if Chart.js failed to load, show message and skip
                        if (!window.Chart) {
                            const trendsWrap = document.getElementById('trendsChart')?.parentElement;
                            if (trendsWrap) {
                                trendsWrap.innerHTML = '<div class="text-gray-600">Chart library failed to load.</div>';
                            }
                            throw new Error('Chart.js not available');
                        }

                        // Attendance Overview - Time In vs Time Out (Doughnut)
                        attendanceChart = new Chart(document.getElementById('attendanceChart'), {
                            type: 'doughnut',
                            data: {
                                labels: ['Time In', 'Time Out'],
                                datasets: [{
                                    label: 'Count',
                                    data: [<?= (int)($attendance_stats['Time In'] ?? 0) ?>, <?= (int)($attendance_stats['Time Out'] ?? 0) ?>],
                                    backgroundColor: [
                                        'rgba(16, 185, 129, 0.85)', // emerald
                                        'rgba(59, 130, 246, 0.85)'   // blue
                                    ],
                                    borderColor: [
                                        'rgba(16, 185, 129, 1)',
                                        'rgba(59, 130, 246, 1)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                ...commonChartOptions,
                                cutout: '55%',
                                plugins: {
                                    ...commonChartOptions.plugins,
                                    legend: {
                                        ...commonChartOptions.plugins.legend,
                                        position: 'bottom'
                                    }
                                }
                            }
                        });

                        // Present Per Subject (Today) - Grouped Bar (Male/Female)
                        (function(){
                            const subjectLabels = <?= json_encode($subject_gender_labels) ?>;
                            const male = <?= json_encode($subject_male_counts) ?>;
                            const female = <?= json_encode($subject_female_counts) ?>;
                            try {
                                const tc = document.getElementById('trendsChart');
                                if (tc) { tc.style.height = '420px'; tc.style.width = '100%'; }
                                console.debug('Trends init (grouped bar)', {subjectLabels, male, female});
                                trendsChart = new Chart(tc, {
                                    type: 'bar',
                                    data: {
                                        labels: subjectLabels,
                                        datasets: [
                                            { label: 'Male', data: male, backgroundColor: 'rgba(59, 130, 246, 0.85)' },
                                            { label: 'Female', data: female, backgroundColor: 'rgba(236, 72, 153, 0.85)' }
                                        ]
                                    },
                                    options: {
                                        ...commonChartOptions,
                                        scales: {
                                            y: { beginAtZero: true, ticks: { precision: 0 } }
                                        }
                                    }
                                });
                            } catch (e) {
                                console.error('trendsChart init error', e);
                            }
                        })();

                        // Function to update charts with new data
                        function updateCharts() {
                            if (!navigator.onLine) {
                                setTimeout(updateCharts, pollDelay);
                                return;
                            }
                            const url = new URL('../ajax/dashboard_data.php', window.location.href);
                            url.searchParams.set('ts', Date.now());
                            fetch(url.toString(), { credentials: 'same-origin' })
                                .then(response => response.json())

                                .then(data => {
                                    pollDelay = POLL_INTERVAL_MS; // reset backoff on success

                                    // Update attendance chart (Time In vs Time Out)
                                    if (attendanceChart && attendanceChart.data && attendanceChart.data.datasets && attendanceChart.data.datasets.length >= 1) {
                                        var as = data.attendance_stats || {};
                                        var tIn = (as['Time In'] !== undefined ? as['Time In'] : 0);
                                        var tOut = (as['Time Out'] !== undefined ? as['Time Out'] : 0);
                                        attendanceChart.data.datasets[0].data = [tIn || 0, tOut || 0];
                                        attendanceChart.update();
                                    }

                                    // Update grouped bar (Male/Female)
                                    try {
                                        if (trendsChart && data.subject_gender_labels && data.subject_male_counts && data.subject_female_counts) {
                                            const labels = data.subject_gender_labels || [];
                                            const male = data.subject_male_counts || [];
                                            const female = data.subject_female_counts || [];
                                            console.debug('Trends update (grouped bar)', {labels, male, female});
                                            trendsChart.data.labels = labels;
                                            if (!trendsChart.data.datasets[0] || !trendsChart.data.datasets[1]) {
                                                trendsChart.data.datasets = [
                                                    { label: 'Male', data: male, backgroundColor: 'rgba(59, 130, 246, 0.85)' },
                                                    { label: 'Female', data: female, backgroundColor: 'rgba(236, 72, 153, 0.85)' }
                                                ];
                                            } else {
                                                trendsChart.data.datasets[0].data = male;
                                                trendsChart.data.datasets[1].data = female;
                                            }
                                            trendsChart.update();
                                        }
                                    } catch (e) {
                                        console.error('trendsChart update error', e);
                                    }

                                    // Update today's attendance count
                                    var tEl = document.getElementById('todayAttendance');
                                    if (tEl) tEl.textContent = data.today_attendance || 0;

                                    // Update current date display
                                    var cEl = document.getElementById('currentDate');
                                    if (cEl) cEl.textContent = (data.current_date ? data.current_date + ' • ' : '') + (data.current_time || '');

                                    // Update Latest Scan card if provided
                                    if (data.latest_scan) {
                                        var ls = data.latest_scan;
                                        var mappings = [
                                            {pic: 'latest-scan-pic', name: 'latest-scan-name', meta: 'latest-scan-meta', status: 'latest-scan-status', subject: 'latest-scan-subject', time: 'latest-scan-time'},
                                            {pic: 'latest-scan-pic-large', name: 'latest-scan-name-large', meta: 'latest-scan-meta-large', status: 'latest-scan-status-large', subject: 'latest-scan-subject-large', time: 'latest-scan-time-large'}
                                        ];

                                        mappings.forEach(function(map) {
                                            var pic = document.getElementById(map.pic);
                                            var nameEl = document.getElementById(map.name);
                                            var metaEl = document.getElementById(map.meta);
                                            var statusEl = document.getElementById(map.status);
                                            var subjectEl = document.getElementById(map.subject);
                                            var timeEl = document.getElementById(map.time);

                                            if (pic && ls.student_pic) pic.src = ls.student_pic;
                                            if (nameEl) nameEl.textContent = ls.student_name || 'No recent scan';
                                            if (metaEl) metaEl.textContent = (ls.student_id || '') 
                                                + (ls.section ? ' · ' + ls.section : '') 
                                                + (ls.year_level ? ' · ' + ls.year_level : '')
                                                + (ls.pc_number ? ' · PC ' + ls.pc_number : '');
                                            if (statusEl) {
                                                statusEl.textContent = ls.status || '';
                                                statusEl.classList.remove('status-present','status-signedout','status-other');
                                                if (!statusEl.classList.contains('status-badge')) statusEl.classList.add('status-badge');
                                                const st = (ls.status || '').toLowerCase();
                                                if (st.includes('present') || st.includes('sign in') || st.includes('time in')) {
                                                    statusEl.classList.add('status-present');
                                                } else if (st.includes('signed out') || st.includes('sign out') || st.includes('time out')) {
                                                    statusEl.classList.add('status-signedout');
                                                } else {
                                                    statusEl.classList.add('status-other');
                                                }
                                            }
                                            if (subjectEl) subjectEl.textContent = ls.subject_name || '';
                                            if (timeEl) timeEl.textContent = ls.scan_time || '';
                                        });
                                    }
                                })
                                .catch(error => {
                                    console.error('Error updating charts:', error);
                                    // backoff to reduce console noise when offline
                                    pollDelay = Math.min(pollDelay * 2, 30000);
                                });
                        }

                        // Initial update and auto-refresh using configurable interval
                        updateCharts();
                        setInterval(updateCharts, POLL_INTERVAL_MS);
                    </script>

                </div>
                
                <!-- Right Column: My Subjects (Carousel) -->
                <div class="lg:w-3/12">
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-blue-100 hover:border-blue-300 transition-all duration-300 h-[850px] flex flex-col">

                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-2xl font-bold flex items-center gap-3">
                                <i class="fas fa-book-open text-blue-500 text-2xl"></i>
                                <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">My Subjects</span>
                            </h2>
                            <div class="text-sm text-gray-600"><span id="subjectCounter">1</span>/<?= (int)$subject_count ?></div>
                        </div>

                        <?php if ($subject_count > 0): ?>
                            <div class="flex items-center justify-between mb-3">
                                <button id="prevSubject" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm hover:bg-gray-50"><i class="fas fa-chevron-left"></i> Prev</button>
                                <span id="subjectBadge" class="hidden text-xs font-bold px-2.5 py-1 rounded-full bg-green-100 text-green-700">Current</span>
                                <button id="nextSubject" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm hover:bg-gray-50">Next <i class="fas fa-chevron-right"></i></button>
                            </div>

                            <div id="subjectsCarousel" class="relative flex-1">

                                <?php foreach ($subjects_list as $idx => $subj): ?>
                                    <!-- START of Subject Card -->
                                    <div class="subject-card hidden absolute inset-0 overflow-auto bg-white rounded-xl shadow-md border border-blue-200 p-6">
                                        <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                                            <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($subj['subject_name']) ?></h3>
                                            <span class="bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full"><?= htmlspecialchars($subj['subject_code']) ?></span>
                                        </div>

                                        <form method="POST" class="space-y-6">
                                            <input type="hidden" name="update_schedule" value="1">
                                            <input type="hidden" name="subject_id" value="<?= $subj['subject_id'] ?>">

                                            <?php for ($i = 0; $i < 2; $i++): 
                                                $s = $subj['schedules'][$i] ?? null;
                                                $slot_title = ($i == 0) ? "Primary Session" : "Additional Session";
                                                $bgColor = ($i == 0) ? "bg-blue-50/50" : "bg-purple-50/50";
                                                $borderColor = ($i == 0) ? "border-blue-100" : "border-purple-100";
                                                $accentColor = ($i == 0) ? "text-blue-600" : "text-purple-600";
                                            ?>
                                            <div class="<?= $bgColor ?> p-4 rounded-xl border <?= $borderColor ?>">
                                                <input type="hidden" name="sched_id_<?= $i+1 ?>" value="<?= $s['schedule_id'] ?? '' ?>">
                                                <div class="flex items-center justify-between mb-3">
                                                    <h4 class="text-sm font-bold <?= $accentColor ?> uppercase tracking-wider"><?= $slot_title ?></h4>
                                                    <?php if ($s): ?>
                                                        <span class="text-[10px] bg-white px-2 py-0.5 rounded border border-gray-200 text-gray-500">
                                                            ID: <?= $s['schedule_id'] ?> | Stu: <?= $s['total_students'] ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="grid grid-cols-2 gap-3 mb-3">
                                                    <div>
                                                        <label class="text-[10px] uppercase text-gray-500 font-bold mb-1 block">Start Time</label>
                                                        <input type="time" name="start_time_<?= $i+1 ?>" value="<?= $s['start_time'] ?? '' ?>" class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5 focus:border-blue-500 transition-all">
                                                    </div>
                                                    <div>
                                                        <label class="text-[10px] uppercase text-gray-500 font-bold mb-1 block">End Time</label>
                                                        <input type="time" name="end_time_<?= $i+1 ?>" value="<?= $s['end_time'] ?? '' ?>" class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5 focus:border-blue-500 transition-all">
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="text-[10px] uppercase text-gray-500 font-bold mb-1 block">Lab / Room</label>
                                                    <select name="lab_id_<?= $i+1 ?>" class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5 focus:border-blue-500">
                                                        <option value="">Select Lab</option>
                                                        <?php foreach ($labs_options as $lab_opt): ?>
                                                            <option value="<?= (int)$lab_opt['lab_id'] ?>" <?= (isset($s['lab_id']) && (int)$s['lab_id'] === (int)$lab_opt['lab_id']) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($lab_opt['lab_name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="flex flex-wrap gap-1">
                                                    <?php 
                                                    $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
                                                    $sel_days = !empty($s['schedule_days']) ? explode(',', $s['schedule_days']) : [];
                                                    foreach ($days as $day): ?>
                                                        <label class="flex-1 min-w-[40px]">
                                                            <input type="checkbox" name="schedule_days_<?= $i+1 ?>[]" value="<?= $day ?>" <?= in_array($day, $sel_days) ? 'checked' : '' ?> class="hidden peer">
                                                            <span class="block text-center py-1 rounded border border-gray-300 text-[10px] font-bold peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 transition cursor-pointer">
                                                                <?= $day ?>
                                                            </span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <?php endfor; ?>

                                            <div class="flex flex-col gap-2 pt-2">
                                                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 transition-colors shadow-lg">
                                                    <i class="fas fa-save mr-2"></i> Update Schedules
                                                </button>
                                                <div class="grid grid-cols-2 gap-2">
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    <!-- END of Subject Card -->
                                <?php endforeach; ?>
                            </div>

                            <script>
                                (function(){
                                    const cards = Array.from(document.querySelectorAll('.subject-card'));
                                    let current = <?= (int)$initial_subject_index ?>;
                                    const total = cards.length;
                                    const counter = document.getElementById('subjectCounter');
                                    const badge = document.getElementById('subjectBadge');
                                    function inRangeNow(cardIdx){
                                        try {
                                            const s = <?= json_encode(array_values($subjects_list)) ?>[cardIdx] || {};
                                            const st = s.start_time, en = s.end_time;
                                            if (!st || !en) return false;
                                            const today = '<?= date('D') ?>';
                                            const daysStr = (s.schedule_days || '').toString();
                                            const days = daysStr.split(',').map(d => d.trim()).filter(Boolean);
                                            const isToday = days.length === 0 ? true : days.includes(today);
                                            if (!isToday) return false;
                                            const now = '<?= date('H:i:s') ?>';
                                            if (en < st) { return (now >= st || now <= en); }
                                            return now >= st && now <= en;
                                        } catch(e){ return false; }
                                    }
                                    function render(){
                                        cards.forEach((c,i)=>{ c.classList.toggle('hidden', i!==current); });
                                        if (counter) counter.textContent = (current+1);
                                        if (badge) badge.classList.toggle('hidden', !inRangeNow(current));
                                    }
                                    render();
                                    document.getElementById('prevSubject').addEventListener('click', function(){ current = (current-1+total)%total; render(); });
                                    document.getElementById('nextSubject').addEventListener('click', function(){ current = (current+1)%total; render(); });
                                })();
                            </script>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-book-open text-4xl text-gray-400 mb-4"></i>
                                <p class="text-gray-600">No subjects assigned yet.</p>
                                <p class="text-sm text-gray-500">Contact the administrator to assign subjects.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                });
            }
        });

        // Top Clock Update
        function updateTopClock() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            let ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;
            const clockEl = document.getElementById('top-clock');
            if (clockEl) clockEl.innerText = timeString;
        }
        setInterval(updateTopClock, 1000);
        updateTopClock();
    </script>
</body>
</html>


