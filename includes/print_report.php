<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/db.php';

// Session variables
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Academic Year and Semester filters (default to active session)
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

// Fetch attendance
$sql = "
    SELECT
        att.attendance_id,
        att.attendance_date,
        att.time_in,
        att.time_out,
        att.status,
        st.student_id,
        CONCAT(IFNULL(st.last_name,''), ', ', IFNULL(st.first_name,''), ' ', IFNULL(st.middle_name,'')) AS full_name,
        st.gender,
        COALESCE(
            pa.pc_number,
            (SELECT paa.pc_number FROM pc_assignment paa WHERE paa.student_id = st.student_id ORDER BY paa.date_assigned DESC LIMIT 1)
        ) AS pc_number,
        sec.section_name AS section,
        yl.year_name     AS year_level,
        CONCAT(IFNULL(sub.subject_code,''),
               CASE WHEN sub.subject_code IS NOT NULL AND sub.subject_name IS NOT NULL THEN ' - ' ELSE '' END,
               IFNULL(sub.subject_name,'')) AS subject,
        fac.lab_name     AS lab
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
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Report</title>
  <style>
    @media print {
      body { margin: 0; padding: 20px; }
      .no-print { display: none !important; }
      table { page-break-inside: auto; }
      tr { page-break-inside: avoid; page-break-after: auto; }
      thead { display: table-header-group; }
    }
    body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 40px; background: white; color: #333; }
    .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #6366f1; padding-bottom: 20px; }
    .header h1 { color: #6366f1; margin: 0; font-size: 32px; text-transform: uppercase; letter-spacing: 1px; }
    .header .subtitle { color: #666; margin: 8px 0; font-size: 16px; font-weight: 500; }
    .report-info { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 14px; color: #444; background: #f8fafc; padding: 10px 15px; border-radius: 8px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
    th, td { border: 1px solid #cbd5e1; padding: 10px 8px; text-align: center; }
    th { background: #6366f1 !important; color: #fff !important; font-weight: bold; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    tr:nth-child(even) { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .summary { margin-top: 30px; text-align: right; font-size: 14px; color: #444; font-weight: 600; padding-top: 15px; border-top: 1px solid #e2e8f0; }
    .print-btn { position: fixed; top: 20px; right: 20px; background: #6366f1; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.2s; z-index: 1000; }
    .print-btn:hover { background: #4f46e5; transform: translateY(-1px); }
  </style>
</head>
<body>
  <button class="print-btn no-print" onclick="window.print()"> Print Report</button>
  
  <div class="header">
    <h1>Attendance Report</h1>
    <div class="subtitle">SRC Computer Laboratory Attendance Record
    
    </div>
  </div>
  
  <div class="report-info">
    <div><strong>Generated on:</strong> <?= date('F j, Y g:i A') ?></div>
    <div><strong>Filter:</strong> <?= (!$show_all) ? (valid_date($from) ? "$from to $to" : $date) : 'All Time' ?></div>
    <div><strong>Total Records:</strong> <?= count($rows) ?></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Student ID</th>
        <th>Full Name</th>
        <th>Section</th>
        <th>Year</th>
        <th>Gender</th>
        <th>PC #</th>
        <th>Subject</th>
        <th>Lab</th>
        <th>Time In</th>
        <th>Time Out</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($rows): foreach ($rows as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['student_id']) ?></td>
          <td style="text-align: left; font-weight: 500;"><?= htmlspecialchars($row['full_name']) ?></td>
          <td><?= htmlspecialchars($row['section']) ?></td>
          <td><?= htmlspecialchars($row['year_level']) ?></td>
          <td><?= htmlspecialchars($row['gender'] ?? '-') ?></td>
          <td><?= htmlspecialchars($row['pc_number'] ?? '-') ?></td>
          <td style="text-align: left;"><?= htmlspecialchars($row['subject'] ?? '-') ?></td>
          <td><?= htmlspecialchars($row['lab'] ?? '-') ?></td>
          <td><?= $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-' ?></td>
          <td><?= $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-' ?></td>
          <td style="font-weight: bold; color: <?= ($row['status']=='Late') ? '#d97706' : (($row['status']=='Absent') ? '#dc2626' : '#059669') ?>;">
            <?= htmlspecialchars($row['status']) ?>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="11" style="padding:20px; color:#64748b; font-style: italic;">No attendance records found for the selected criteria.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  
  <div class="summary">
    Total Record Count: <?php echo count($rows); ?>
  </div>
  
  <script>
    // Auto-print delay
    window.addEventListener('load', function() {
      setTimeout(function() {
        // window.print(); // Uncomment to enable auto-print on load
      }, 500);
    });
  </script>
</body>
</html>
