<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/db.php';

// Get filters
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';

function valid_date($d){
  if (!$d) return false;
  $dt = DateTime::createFromFormat('Y-m-d', $d);
  return $dt && $dt->format('Y-m-d') === $d;
}

$wheres = [];

// Date filter
if (!(isset($_GET['show_all']) && $_GET['show_all'] == '1')) {
  if (valid_date($from) && valid_date($to)) {
    if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }
    $wheres[] = "DATE(a.scan_time) BETWEEN '" . $from . "' AND '" . $to . "'";
  } else {
    if (valid_date($date)) {
      $wheres[] = "DATE(a.scan_time)='" . $date . "'";
    }
  }
}

// Gender filter
if ($gender_filter) {
  $g = $conn->real_escape_string($gender_filter);
  $wheres[] = "s.gender = '" . $g . "'";
}

$where_sql = count($wheres) ? ' WHERE ' . implode(' AND ', $wheres) : '';

// Fetch records with subject & lab
$sql = "SELECT a.*, s.name, s.section, s.year_level, s.pc_number, s.gender, 
               sub.subject_name, sub.id AS subject_id, sub.lab 
        FROM attendance a
        LEFT JOIN students s ON s.student_id = a.student_id
        LEFT JOIN subjects sub ON sub.id = a.subject_id 
        $where_sql
        ORDER BY a.student_id, a.scan_time ASC";

$res = $conn->query($sql);
$rows = [];
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
  }
}

// Build paired records like in main page
function build_pairs_by_subject_all($rows) {
  $by_student = [];
  foreach ($rows as $r) {
    $sid = $r['student_id'];
    if (!isset($by_student[$sid])) $by_student[$sid] = [];
    $by_student[$sid][] = $r;
  }

  $pairs = [];
  foreach ($by_student as $sid => $list) {
    usort($list, function($a, $b){ return strtotime($a['scan_time']) <=> strtotime($b['scan_time']); });
    $n = count($list);
    $used = array_fill(0, $n, false);
    for ($i = 0; $i < $n; $i++) {
      if ($used[$i]) continue;
      $in = $list[$i];
      $used[$i] = true;
      $out = null;
      for ($j = $i + 1; $j < $n; $j++) {
        if ($used[$j]) continue;
        if ($list[$j]['subject_id'] == $in['subject_id']) {
          $out = $list[$j];
          $used[$j] = true;
          break;
        }
      }
      $pairs[] = [
        'student_id' => $sid,
        'name' => $in['name'] ?? null,
        'section' => $in['section'] ?? null,
        'year_level' => $in['year_level'] ?? null,
        'pc_number' => $in['pc_number'] ?? null,
        'gender' => $in['gender'] ?? null,
        'time_in' => $in['scan_time'],
        'time_out' => $out ? $out['scan_time'] : null,
        'subject_in' => $in['subject_name'] ?? null,
        'subject_out' => $out ? $out['subject_name'] : null,
        'lab_in' => $in['lab'] ?? null,
        'lab_out' => $out ? $out['lab'] : null,
      ];
    }
  }
  return $pairs;
}

$pairs = build_pairs_by_subject_all($rows);
?>
<!DOCTYPE html>
<html>
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
      tfoot { display: table-footer-group; }
    }
    
    body { 
      font-family: Arial, sans-serif; 
      margin: 0;
      padding: 20px;
      background: white;
    }
    
    .header {
      text-align: center;
      margin-bottom: 30px;
      border-bottom: 2px solid #6366f1;
      padding-bottom: 15px;
    }
    
    .header h1 {
      color: #6366f1;
      margin: 0;
      font-size: 28px;
      font-weight: bold;
    }
    
    .header .subtitle {
      color: #666;
      margin: 5px 0;
      font-size: 14px;
    }
    
    .report-info {
      display: flex;
      justify-content: space-between;
      margin-bottom: 20px;
      font-size: 12px;
      color: #666;
    }
    
    table { 
      width: 100%; 
      border-collapse: collapse; 
      margin-top: 10px;
      font-size: 12px;
    }
    
    th, td { 
      border: 1px solid #333; 
      padding: 6px 4px; 
      text-align: center; 
      vertical-align: middle;
    }
    
    th { 
      background: #6366f1 !important; 
      color: #fff !important;
      font-weight: bold;
      font-size: 11px;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    
    tr:nth-child(even) { 
      background: #f8f9fa !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    
    .summary {
      margin-top: 20px;
      text-align: center;
      font-size: 12px;
      color: #666;
      border-top: 1px solid #ddd;
      padding-top: 10px;
    }
    
    .print-btn {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #6366f1;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      z-index: 1000;
    }
    
    .print-btn:hover {
      background: #5856eb;
    }
  </style>
</head>
<body>
  <button class="print-btn no-print" onclick="window.print()">🖨️ Print Report</button>
  
  <div class="header">
    <h1>Attendance Report</h1>
    <div class="subtitle">Computer Laboratory Management System</div>
  </div>
  
  <div class="report-info">
    <div>Generated on: <?= date('F j, Y g:i A') ?></div>
    <div>Total Records: <?= count($pairs) ?></div>
  </div>
  <table>
    <thead>
      <tr>
        <th style="width: 12%;">Student ID</th>
        <th style="width: 12%;">Name</th>
        <th style="width: 10%;">Section</th>
        <th style="width: 8%;">Year</th>
        <th style="width: 8%;">Gender</th>
        <th style="width: 8%;">PC #</th>
        <th style="width: 15%;">Subject</th>
        <th style="width: 10%;">Lab</th>
        <th style="width: 17%;">Time In</th>
        <th style="width: 17%;">Time Out</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($pairs): foreach ($pairs as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['student_id']) ?></td>
          <td style="text-align: left; padding-left: 8px;"><?= htmlspecialchars($row['name']) ?></td>
          <td><?= htmlspecialchars($row['section']) ?></td>
          <td><?= htmlspecialchars($row['year_level']) ?></td>
          <td><?= htmlspecialchars($row['gender'] ?? '-') ?></td>
          <td><?= htmlspecialchars($row['pc_number'] ?? '-') ?></td>
          <td style="text-align: left; padding-left: 8px;"><?= htmlspecialchars($row['subject_out'] ?? $row['subject_in'] ?? '-') ?></td>
          <td><?= htmlspecialchars($row['lab_out'] ?? $row['lab_in'] ?? '-') ?></td>
          <td><?= $row['time_in'] ? date('m/d/Y g:i A', strtotime($row['time_in'])) : '-' ?></td>
          <td><?= $row['time_out'] ? date('m/d/Y g:i A', strtotime($row['time_out'])) : '-' ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="10" style="padding:12px;color:#888;">No attendance records found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  
  <div class="summary">
    <p>End of Report - <?= count($pairs) ?> record(s) found</p>
  </div>
  
  <script>
    // Auto-print when page loads (with slight delay for rendering)
    window.addEventListener('load', function() {
      setTimeout(function() {
        window.print();
      }, 500);
    });
  </script>
</body>
</html>
