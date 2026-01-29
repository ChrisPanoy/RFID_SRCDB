<?php
session_start();
include '../includes/db.php';

date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// Require teacher login (keeps same behavior as dashboard)
if (!isset($_SESSION['teacher_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_id_int = (int)$teacher_id;

// Attendance overview (Time In / Time Out) for this teacher using src_db schema
$attendance_stats = [
    'Time In' => 0,
    'Time Out' => 0,
];

$attendance_overview_sql = "\n    SELECT \n        SUM(CASE WHEN a.time_in IS NOT NULL THEN 1 ELSE 0 END) AS time_in_count,\n        SUM(CASE WHEN a.time_out IS NOT NULL THEN 1 ELSE 0 END) AS time_out_count\n    FROM attendance a\n    JOIN admission adm ON a.admission_id = adm.admission_id\n    JOIN schedule sc ON adm.schedule_id = sc.schedule_id\n    WHERE a.attendance_date = ?\n      AND sc.employee_id = ?\n";
$attendance_overview_query = $conn->prepare($attendance_overview_sql);
$attendance_overview_query->bind_param('si', $today, $teacher_id_int);
$attendance_overview_query->execute();
$overview_row = $attendance_overview_query->get_result()->fetch_assoc();
if ($overview_row) {
    $attendance_stats['Time In'] = (int)($overview_row['time_in_count'] ?? 0);
    $attendance_stats['Time Out'] = (int)($overview_row['time_out_count'] ?? 0);
}

// Weekly trends (Mon-Fri) using attendance_date/time_in/time_out
$weekly_trends = [];
$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
foreach ($days as $day) {
    $date = date('Y-m-d', strtotime($day . ' this week'));
    $trend_sql = "\n        SELECT \n            SUM(CASE WHEN a.time_in IS NOT NULL THEN 1 ELSE 0 END) AS signed_in,\n            SUM(CASE WHEN a.time_out IS NOT NULL THEN 1 ELSE 0 END) AS signed_out\n        FROM attendance a\n        JOIN admission adm ON a.admission_id = adm.admission_id\n        JOIN schedule sc ON adm.schedule_id = sc.schedule_id\n        WHERE a.attendance_date = ?\n          AND sc.employee_id = ?\n    ";
    $trend_query = $conn->prepare($trend_sql);
    $trend_query->bind_param('si', $date, $teacher_id_int);
    $trend_query->execute();
    $result = $trend_query->get_result()->fetch_assoc();
    $weekly_trends[$day] = [
        'signed_in' => (int)($result['signed_in'] ?? 0),
        'signed_out' => (int)($result['signed_out'] ?? 0),
    ];
}

// Today's total attendance (from existing overview)
$today_attendance = array_sum($attendance_stats);

// Present today by gender (boys vs girls) for this teacher (src_db joins)
// We treat male/m as Boys, female/f as Girls; anything else is ignored for the chart
$gender_sql = "\n    SELECT \n        LOWER(TRIM(st.gender)) AS gkey,\n        COUNT(DISTINCT adm.student_id) AS cnt\n    FROM attendance a\n    JOIN admission adm ON a.admission_id = adm.admission_id\n    JOIN students st ON adm.student_id = st.student_id\n    JOIN schedule sc ON adm.schedule_id = sc.schedule_id\n    WHERE a.attendance_date = ?\n      AND a.status IN ('Present','Late')\n      AND sc.employee_id = ?\n    GROUP BY LOWER(TRIM(st.gender))\n";

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

// Seed subject labels with all teacher subjects to show zero bars when no attendance
$subject_gender_labels = [];
$subject_male_counts = [];
$subject_female_counts = [];
$subj_sql = "SELECT subj.subject_name FROM schedule sc JOIN subject subj ON sc.subject_id=subj.subject_id WHERE sc.employee_id=? GROUP BY subj.subject_id, subj.subject_name ORDER BY subj.subject_name";
if ($subj_stmt = $conn->prepare($subj_sql)) {
    $subj_stmt->bind_param('i', $teacher_id_int);
    $subj_stmt->execute();
    $subj_res = $subj_stmt->get_result();
    while ($sr = $subj_res->fetch_assoc()) {
        $nm = $sr['subject_name'] ?? null;
        if ($nm) { $subject_gender_labels[] = $nm; $subject_male_counts[] = 0; $subject_female_counts[] = 0; }
    }
    $subj_stmt->close();
}

// Present count per subject for today, split by gender, using LEFT JOIN to keep zero-attendance subjects
$present_sql = "\n    SELECT \n        subj.subject_name,\n        LOWER(TRIM(st.gender)) AS gkey,\n        SUM(CASE WHEN a.attendance_id IS NOT NULL THEN 1 ELSE 0 END) AS present_count\n    FROM schedule sc\n    JOIN subject subj ON sc.subject_id = subj.subject_id\n    LEFT JOIN admission adm ON adm.schedule_id = sc.schedule_id\n    LEFT JOIN students st ON adm.student_id = st.student_id\n    LEFT JOIN attendance a \n        ON a.admission_id = adm.admission_id\n       AND a.attendance_date = ?\n       AND a.status IN ('Present','Late')\n    WHERE sc.employee_id = ?\n    GROUP BY subj.subject_id, subj.subject_name, LOWER(TRIM(st.gender))\n    ORDER BY subj.subject_name ASC\n";

$present_stmt = $conn->prepare($present_sql);
$present_stmt->bind_param('si', $today, $teacher_id_int);
$present_stmt->execute();
$present_res = $present_stmt->get_result();
while ($r = $present_res->fetch_assoc()) {
    $subject = $r['subject_name'];
    $gkey    = $r['gkey'] ?? '';
    $cnt     = (int)($r['present_count'] ?? 0);

    $bucket = null;
    if ($gkey === 'male' || $gkey === 'm') { $bucket = 'male'; }
    elseif ($gkey === 'female' || $gkey === 'f') { $bucket = 'female'; }
    if ($bucket === null) { continue; }

    $index = array_search($subject, $subject_gender_labels, true);
    if ($index === false) { // in case a new subject slips in, append
        $subject_gender_labels[] = $subject;
        $subject_male_counts[] = 0;
        $subject_female_counts[] = 0;
        $index = count($subject_gender_labels) - 1;
    }

    if ($bucket === 'male') { $subject_male_counts[$index] += $cnt; }
    elseif ($bucket === 'female') { $subject_female_counts[$index] += $cnt; }
}
$present_stmt->close();

// Current time/date info
$current_day = date('D');
$current_time = date('g:i A');
$current_date = date('F j, Y');

// Latest scan (most recent attendance record) - include student info and subject using src_db schema
$latest_scan = null;
$latest_stmt = $conn->prepare("\n    SELECT \n        a.attendance_date, a.time_in, a.time_out, a.status,\n        st.student_id, st.first_name, st.middle_name, st.last_name, st.profile_picture,\n        subj.subject_name, sec.section_name, yl.year_name, pa.pc_number\n    FROM attendance a\n    JOIN admission adm ON a.admission_id = adm.admission_id\n    JOIN students st ON adm.student_id = st.student_id\n    JOIN schedule sc ON adm.schedule_id = sc.schedule_id\n    JOIN subject subj ON sc.subject_id = subj.subject_id\n    LEFT JOIN section sec ON adm.section_id = sec.section_id\n    LEFT JOIN year_level yl ON adm.year_level_id = yl.year_id\n    LEFT JOIN pc_assignment pa ON pa.student_id = st.student_id AND pa.lab_id = sc.lab_id\n    WHERE sc.employee_id = ?\n    ORDER BY a.attendance_date DESC, COALESCE(a.time_out, a.time_in) DESC\n    LIMIT 1\n");
if ($latest_stmt) {
    $latest_stmt->bind_param('i', $teacher_id_int);
    $latest_stmt->execute();
    $res = $latest_stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $full_name_parts = [
            trim($row['first_name'] ?? ''),
            trim($row['middle_name'] ?? ''),
            trim($row['last_name'] ?? ''),
        ];
        $student_name = trim(implode(' ', array_filter($full_name_parts)));

        $time_component = $row['time_out'] ?? $row['time_in'] ?? null;
        $scan_time_fmt = null;
        if (!empty($row['attendance_date']) && $time_component) {
            $scanTimestamp = $row['attendance_date'] . ' ' . $time_component;
            $scan_time_fmt = date('g:i:s A', strtotime($scanTimestamp));
        }

        $latest_scan = [
            'student_name' => $student_name ?: null,
            'student_id' => $row['student_id'] ?? null,
            'section' => $row['section_name'] ?? null,
            'year_level' => $row['year_name'] ?? null,
            'status' => $row['status'] ?? null,
            'subject_name' => $row['subject_name'] ?? null,
            'student_pic' => !empty($row['profile_picture']) ? 'assets/img/' . $row['profile_picture'] : 'assets/img/logo.png',
            'scan_time' => $scan_time_fmt,
            'raw_scan_time' => $scan_time_fmt,
            'pc_number' => $row['pc_number'] ?? null,
        ];
    }
    $latest_stmt->close();
}

// Build response
$response = [
    'attendance_stats' => $attendance_stats,
    'weekly_trends' => $weekly_trends,
    'today_attendance' => $today_attendance,
    'subject_gender_labels' => $subject_gender_labels,
    'subject_male_counts' => $subject_male_counts,
    'subject_female_counts' => $subject_female_counts,
    'present_gender' => $present_gender,
    'current_day' => $current_day,
    'current_time' => $current_time,
    'current_date' => $current_date,
    'latest_scan' => $latest_scan
];

header('Content-Type: application/json');
echo json_encode($response);
?>
