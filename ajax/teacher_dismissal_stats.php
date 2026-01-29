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
$today = date('Y-m-d');

// Get teacher's subjects with current attendance counts
$subjects_query = $conn->prepare("
    SELECT s.*, 
           COUNT(DISTINCT ss.student_id) as total_enrolled,
           (SELECT COUNT(DISTINCT a1.student_id) 
            FROM attendance a1 
            WHERE a1.subject_id = s.id 
            AND DATE(a1.scan_time) = ? 
            AND a1.status = 'Present'
            AND a1.student_id NOT IN (
                SELECT a2.student_id 
                FROM attendance a2 
                WHERE a2.subject_id = s.id 
                AND DATE(a2.scan_time) = ? 
                AND a2.status = 'Signed Out'
            )
           ) as currently_present
    FROM subjects s
    LEFT JOIN student_subjects ss ON s.id = ss.subject_id
    WHERE s.teacher_id = (SELECT id FROM teachers WHERE teacher_id = ?)
    GROUP BY s.id
    ORDER BY s.subject_name
");
$subjects_query->bind_param("sss", $today, $today, $teacher_id);
$subjects_query->execute();
$subjects_result = $subjects_query->get_result();

$subjects_data = [];
while ($subject = $subjects_result->fetch_assoc()) {
    $subjects_data[] = [
        'id' => $subject['id'],
        'subject_name' => $subject['subject_name'],
        'subject_code' => $subject['subject_code'],
        'total_enrolled' => (int)$subject['total_enrolled'],
        'currently_present' => (int)$subject['currently_present'],
        'schedule_days' => $subject['schedule_days'],
        'start_time' => $subject['start_time'],
        'end_time' => $subject['end_time']
    ];
}

// Get overall statistics
$total_subjects = count($subjects_data);
$total_present = array_sum(array_column($subjects_data, 'currently_present'));
$total_enrolled = array_sum(array_column($subjects_data, 'total_enrolled'));

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'subjects' => $subjects_data,
    'statistics' => [
        'total_subjects' => $total_subjects,
        'total_present' => $total_present,
        'total_enrolled' => $total_enrolled
    ],
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
