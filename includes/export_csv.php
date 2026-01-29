<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/db.php';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d_H-i-s') . '.csv"');
header('Cache-Control: max-age=0');

// Create output stream
$output = fopen('php://output', 'w');

// Get filters
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';
$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';

// Validate date helper
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
    $wheres[] = "DATE(a.scan_time) BETWEEN '$from' AND '$to'";
  } elseif (valid_date($date)) {
    $wheres[] = "DATE(a.scan_time) = '$date'";
  }
}

if ($gender_filter) {
  $g = $conn->real_escape_string($gender_filter);
  $wheres[] = "s.gender = '$g'";
}

$where_sql = count($wheres) ? 'WHERE ' . implode(' AND ', $wheres) : '';

// Fetch attendance with subjects + labs
$sql = "SELECT a.*, s.name, s.section, s.year_level, s.pc_number, s.gender,
               sub.subject_name, sub.id AS subject_id, sub.lab
        FROM attendance a
        LEFT JOIN students s ON s.student_id = a.student_id
        LEFT JOIN subjects sub ON sub.id = a.subject_id
        $where_sql
        ORDER BY a.student_id, a.scan_time ASC";
$res = $conn->query($sql);

// Pair scans (time in/out)
function build_pairs($rows){
  $by_student = [];
  foreach($rows as $r){ $by_student[$r['student_id']][] = $r; }

  $pairs = [];
  foreach($by_student as $sid => $list){
    usort($list, fn($a,$b)=>strtotime($a['scan_time'])<=>strtotime($b['scan_time']));
    $used = [];
    for($i=0;$i<count($list);$i++){
      if(isset($used[$i])) continue;
      $in = $list[$i]; $used[$i]=true;
      $out = null;
      for($j=$i+1;$j<count($list);$j++){
        if(isset($used[$j])) continue;
        if($list[$j]['subject_id']==$in['subject_id']){
          $out=$list[$j]; $used[$j]=true; break;
        }
      }
      $pairs[]=[
        'student_id'=>$sid,
        'name'=>$in['name'],
        'section'=>$in['section'],
        'year_level'=>$in['year_level'],
        'pc_number'=>$in['pc_number'],
        'gender'=>$in['gender'],
        'subject'=>$out? $out['subject_name']:$in['subject_name'],
        'lab'=>$out? $out['lab']:$in['lab'],
        'time_in'=>$in['scan_time'],
        'time_out'=>$out? $out['scan_time']:null
      ];
    }
  }
  return $pairs;
}

$rows=[];
if($res){ while($r=$res->fetch_assoc()){ $rows[]=$r; } }

$pairs = build_pairs($rows);

// Write CSV headers
fputcsv($output, [
    'Student ID',
    'Name', 
    'Section',
    'Year Level',
    'Gender',
    'PC Number',
    'Subject',
    'Lab',
    'Time In',
    'Time Out'
]);

// Write CSV data
if (!empty($pairs)) {
    foreach ($pairs as $row) {
        fputcsv($output, [
            $row['student_id'],
            $row['name'],
            $row['section'],
            $row['year_level'],
            $row['gender'] ?? '-',
            $row['pc_number'] ?? '-',
            $row['subject'] ?? '-',
            $row['lab'] ?? '-',
            $row['time_in'] ? date('Y-m-d h:i:s A', strtotime($row['time_in'])) : '-',
            $row['time_out'] ? date('Y-m-d h:i:s A', strtotime($row['time_out'])) : '-'
        ]);
    }
} else {
    fputcsv($output, ['No attendance records found', '', '', '', '', '', '', '', '', '']);
}

// Close output stream
fclose($output);
?>




