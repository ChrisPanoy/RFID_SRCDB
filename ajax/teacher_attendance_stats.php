<?php
session_start();
include '../includes/db.php';

date_default_timezone_set('Asia/Manila');

// Require teacher login
if (!isset($_SESSION['teacher_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$subject_filter = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (!$subject_filter) {
    echo json_encode(['error' => 'Subject ID required']);
    exit();
}

// Validate subject belongs to teacher
$sub_stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ? AND teacher_id = (SELECT id FROM teachers WHERE teacher_id = ?)");
$sub_stmt->bind_param("ii", $subject_filter, $teacher_id);
$sub_stmt->execute();
$sub_res = $sub_stmt->get_result();

if ($sub_res->num_rows === 0) {
    echo json_encode(['error' => 'Subject not found or not assigned to you']);
    exit();
}

$subject_row = $sub_res->fetch_assoc();

// Get students assigned to this subject
$students = [];
$stmt = $conn->prepare("
    SELECT st.* 
    FROM students st 
    JOIN student_subjects ss ON st.student_id = ss.student_id 
    WHERE ss.subject_id = ? AND st.course = 'BSIS' 
    ORDER BY st.year_level, st.section, st.name
");
$stmt->bind_param("i", $subject_filter);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $students[$row['student_id']] = $row;
}

// Get first scan times per student for that subject+date
$first_times = [];
if (!empty($students)) {
    $att_stmt = $conn->prepare("
        SELECT student_id, MIN(scan_time) AS first_time 
        FROM attendance 
        WHERE subject_id = ? AND DATE(scan_time) = ? 
        GROUP BY student_id
    ");
    $att_stmt->bind_param("is", $subject_filter, $date_filter);
    $att_stmt->execute();
    $att_res = $att_stmt->get_result();
    while ($a = $att_res->fetch_assoc()) {
        $first_times[$a['student_id']] = $a['first_time'];
    }
}

// Get all scans for this subject+date to determine Time Out
$scans_by_student = [];
if (!empty($students)) {
    $all_stmt = $conn->prepare("
        SELECT student_id, scan_time 
        FROM attendance 
        WHERE subject_id = ? AND DATE(scan_time) = ? 
        ORDER BY student_id, scan_time ASC
    ");
    $all_stmt->bind_param('is', $subject_filter, $date_filter);
    $all_stmt->execute();
    $all_res = $all_stmt->get_result();
    while ($rowScan = $all_res->fetch_assoc()) {
        $sid = $rowScan['student_id'];
        if (!isset($scans_by_student[$sid])) $scans_by_student[$sid] = [];
        $scans_by_student[$sid][] = $rowScan['scan_time'];
    }
}

// Determine Time Out only if a scan occurred at or after (end_time - 10 minutes)
$second_times = [];
$subject_end_time   = $subject_row['end_time'] ?? null;
$end_ts            = $subject_end_time ? strtotime($date_filter . ' ' . $subject_end_time) : null;
$timeout_cutoff_ts = $end_ts ? ($end_ts - (10 * 60)) : null;

foreach ($scans_by_student as $sid => $times) {
    $second_times[$sid] = null;
    if (!$timeout_cutoff_ts) continue; // if no end time configured, do not infer timeout

    $eligible_second = null;
    foreach ($times as $t) {
        $ts = strtotime($t);
        if ($ts >= $timeout_cutoff_ts) {
            $eligible_second = $t; // keep latest eligible
        }
    }
    if ($eligible_second !== null) {
        $second_times[$sid] = $eligible_second;
    }
}

// Compute status (Present/Late/Absent)
$totals = ['total' => count($students), 'present' => 0, 'late' => 0, 'absent' => 0, 'timeout' => 0];
$subject_start_time = $subject_row['start_time'];

foreach ($students as $sid => $s) {
    if (isset($first_times[$sid]) && $first_times[$sid]) {
        $first = $first_times[$sid];
        $second = $second_times[$sid] ?? null;

        $start_dt = strtotime($date_filter . ' ' . $subject_start_time);
        // Present <= +10min, Late <= +15min, else Absent
        $present_deadline = $start_dt + (10 * 60);
        $late_deadline = $start_dt + (15 * 60);

        $first_ts = strtotime($first);

        if ($first_ts <= $present_deadline) {
            $status = 'Present';
            $totals['present']++;
        } elseif ($first_ts <= $late_deadline) {
            $status = 'Late';
            $totals['late']++;
        } else {
            $status = 'Absent';
            $totals['absent']++;
        }

        if ($second) {
            $totals['timeout']++;
        }
    } else {
        $totals['absent']++;
    }
}

// Gender groups for detailed stats
$gender_groups = ['Male' => [], 'Female' => [], 'Other' => []];
foreach ($students as $sid => $s) {
    $g = isset($s['gender']) ? strtolower(trim($s['gender'])) : '';
    if ($g === 'male' || $g === 'm') {
        $gender_groups['Male'][$sid] = $s;
    } elseif ($g === 'female' || $g === 'f') {
        $gender_groups['Female'][$sid] = $s;
    } else {
        $gender_groups['Other'][$sid] = $s;
    }
}

// Calculate group stats
$groups = [];
foreach ($gender_groups as $glabel => $gstudents) {
    if (empty($gstudents)) continue;
    
    $g_tot = ['total' => count($gstudents), 'present' => 0, 'late' => 0, 'absent' => 0, 'timeout' => 0];
    foreach ($gstudents as $gs_id => $gs) {
        if (isset($first_times[$gs_id]) && $first_times[$gs_id]) {
            $first = $first_times[$gs_id];
            $start_dt = strtotime($date_filter . ' ' . $subject_start_time);
            // Present <= +10min, Late <= +15min, else Absent
            $present_deadline = $start_dt + (10 * 60);
            $late_deadline = $start_dt + (15 * 60);
            $first_ts = strtotime($first);

            if ($first_ts <= $present_deadline) {
                $g_tot['present']++;
            } elseif ($first_ts <= $late_deadline) {
                $g_tot['late']++;
            } else {
                $g_tot['absent']++;
            }

            if (!empty($second_times[$gs_id])) {
                $g_tot['timeout']++;
            }
        } else {
            $g_tot['absent']++;
        }
    }
    
    $groups[] = [
        'label' => $glabel,
        'present' => $g_tot['present'],
        'late' => $g_tot['late'],
        'absent' => $g_tot['absent'],
        'timeout' => $g_tot['timeout']
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'totals' => $totals,
    'groups' => $groups,
    'subject_name' => $subject_row['subject_name'],
    'subject_code' => $subject_row['subject_code'],
    'date' => $date_filter,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
