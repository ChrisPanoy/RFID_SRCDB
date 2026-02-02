<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$rfid = $_POST['rfid'] ?? '';

if (empty($rfid)) {
    echo json_encode(['success' => false, 'message' => 'No RFID provided']);
    exit;
}

// 1) Find student by RFID
$stmt = $conn->prepare("SELECT * FROM students WHERE rfid_number = ? LIMIT 1");
$stmt->bind_param("s", $rfid);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if ($student) {
    // 2) Set session in a way compatible with other student pages
    // Most student pages expect $_SESSION['user'] and sometimes $_SESSION['role']
    $student['role'] = 'student';
    $_SESSION['user'] = $student;
    $_SESSION['student_id'] = $student['student_id'];
    $_SESSION['role'] = 'student';
    
    // Resolve profile pic path
    $photoPath = !empty($student['profile_picture']) ? 'assets/img/' . basename($student['profile_picture']) : 'assets/img/logo.png';

    echo json_encode([
        'success' => true,
        'message' => 'Welcome back, ' . ($student['first_name'] ?? 'Student') . '!',
        'redirect' => 'student/student_dashboard_lab.php', // Let's send them to the lab dashboard for now as it's more active
        'student' => [
            'name' => ($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''),
            'photo' => $photoPath
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Student record not found']);
}
