<?php
include 'db.php';

// Get current date
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// Get total students and present count
$total_students = $conn->query("SELECT COUNT(*) as cnt FROM students")->fetch_assoc()['cnt'];
$present_today = $conn->query("SELECT COUNT(DISTINCT student_id) as cnt FROM attendance WHERE DATE(scan_time)='$today' AND status='Present'")->fetch_assoc()['cnt'];

// Get sign in/out counts by year level
$signin_counts = [];
$signout_counts = [];
for($i = 1; $i <= 4; $i++) {
    $signin_counts[] = $conn->query("SELECT COUNT(DISTINCT student_id) as cnt FROM attendance a 
        JOIN students s ON a.student_id = s.student_id 
        WHERE s.year_level = $i AND DATE(a.scan_time)='$today' 
        AND a.status='Present'")->fetch_assoc()['cnt'];
    
    $signout_counts[] = $conn->query("SELECT COUNT(DISTINCT student_id) as cnt FROM attendance a 
        JOIN students s ON a.student_id = s.student_id 
        WHERE s.year_level = $i AND DATE(a.scan_time)='$today' 
        AND a.status='Signed Out'")->fetch_assoc()['cnt'];
}

// Get attendance by year level
$year_query = $conn->query("
    SELECT 
        s.year_level,
        COUNT(DISTINCT s.student_id) as total_students,
        COUNT(DISTINCT CASE WHEN a.status = 'Present' AND DATE(a.scan_time) = '$today' THEN s.student_id END) as present_count
    FROM students s
    LEFT JOIN attendance a ON s.student_id = a.student_id
    GROUP BY s.year_level
    ORDER BY s.year_level
");

$year_data = [];
while ($row = $year_query->fetch_assoc()) {
    $year_data[] = [
        'year' => $row['year_level'],
        'total' => (int)$row['total_students'],
        'present' => (int)$row['present_count']
    ];
}

// Get attendance by section
$section_query = $conn->query("
    SELECT 
        s.section,
        COUNT(DISTINCT s.student_id) as total_students,
        COUNT(DISTINCT CASE WHEN a.status = 'Present' AND DATE(a.scan_time) = '$today' THEN s.student_id END) as present_count
    FROM students s
    LEFT JOIN attendance a ON s.student_id = a.student_id
    GROUP BY s.section
    ORDER BY s.section
");

$section_data = [];
while ($row = $section_query->fetch_assoc()) {
    $section_data[] = [
        'section' => $row['section'],
        'total' => (int)$row['total_students'],
        'present' => (int)$row['present_count']
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'totalStudents' => $total_students,
    'presentCount' => $present_today,
    'yearLevels' => $year_data,
    'sections' => $section_data,
    'signin_counts' => $signin_counts,
    'signout_counts' => $signout_counts,
    'year_level_counts' => array_map(function($y) { return $y['total']; }, $year_data),
    'section_counts' => array_map(function($s) { return $s['total']; }, $section_data)
]);