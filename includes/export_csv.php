<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/db.php';

// Session variables for default filters
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get filters (match includes/all_attendance.php)
$ay_filter = isset($_GET['ay_id']) ? (int)$_GET['ay_id'] : ($_SESSION['active_ay_id'] ?? 0);
$sem_filter = isset($_GET['semester_id']) ? (int)$_GET['semester_id'] : ($_SESSION['active_sem_id'] ?? 0);
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';
$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';

// validate date helper
function valid_date($d){
  if (!$d) return false;
  $dt = DateTime::createFromFormat('Y-m-d', $d);
  return $dt && $dt->format('Y-m-d') === $d;
}

// Build WHERE clauses
$wheres = [];
if (!$show_all) {
  if (valid_date($from) && valid_date($to)) {
    if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }
    $wheres[] = "att.attendance_date BETWEEN '$from' AND '$to'";
  } elseif (valid_date($date)) {
    $wheres[] = "att.attendance_date = '$date'";
  }
}
if ($gender_filter) {
  $g = $conn->real_escape_string($gender_filter);
  $wheres[] = "st.gender = '$g'";
}
if ($sem_filter > 0) {
  $wheres[] = "a.semester_id = $sem_filter";
}
if ($ay_filter > 0) {
  $wheres[] = "a.academic_year_id = $ay_filter";
}

$where_sql = count($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d_H-i-s') . '.csv"');
header('Cache-Control: max-age=0');

// Create output stream
$output = fopen('php://output', 'w');

// Write CSV headers
fputcsv($output, [
    'Student ID',
    'Section',
    'Year Level',
    'Gender',
    'PC Number',
    'Subject',
    'Lab',
    'Time In',
    'Time Out',
    'Status'
]);

// Fetch attendance using current schema
$sql = "
    SELECT
        st.student_id,
        sec.section_name AS section,
        yl.year_name     AS year_level,
        st.gender,
        COALESCE(
            pa.pc_number,
            (SELECT paa.pc_number FROM pc_assignment paa WHERE paa.student_id = st.student_id ORDER BY paa.date_assigned DESC LIMIT 1)
        ) AS pc_number,
        CONCAT(IFNULL(sub.subject_code,''),
               CASE WHEN sub.subject_code IS NOT NULL AND sub.subject_name IS NOT NULL THEN ' - ' ELSE '' END,
               IFNULL(sub.subject_name,'')) AS subject,
        fac.lab_name     AS lab,
        att.attendance_date,
        att.time_in,
        att.time_out,
        att.status
    FROM attendance att
    JOIN admissions a   ON att.admission_id = a.admission_id
    JOIN students st   ON a.student_id     = st.student_id
    LEFT JOIN sections sec     ON a.section_id     = sec.section_id
    LEFT JOIN year_levels yl   ON a.year_level_id  = yl.year_id
    LEFT JOIN subjects sub     ON a.subject_id     = sub.subject_id
    LEFT JOIN schedule sc     ON att.schedule_id  = sc.schedule_id
    LEFT JOIN facilities fac    ON sc.lab_id        = fac.lab_id
    LEFT JOIN pc_assignment pa ON pa.student_id   = st.student_id AND pa.lab_id = fac.lab_id
    $where_sql
    ORDER BY att.attendance_date DESC, st.student_id ASC, att.time_in ASC
";

$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['student_id'],
            $row['section'],
            $row['year_level'],
            $row['gender'] ?? '-',
            $row['pc_number'] ?? '-',
            $row['subject'] ?? '-',
            $row['lab'] ?? '-',
            $row['time_in'] ? date('Y-m-d H:i:s A', strtotime($row['attendance_date'] . ' ' . $row['time_in'])) : '-',
            $row['time_out'] ? date('Y-m-d H:i:s A', strtotime($row['attendance_date'] . ' ' . $row['time_out'])) : '-',
            $row['status']
        ]);
    }
} else {
    // Optional: write a row indicating no records
}

fclose($output);
exit();
?>
