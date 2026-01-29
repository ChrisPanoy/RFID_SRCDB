<?php
// Handles lab-specific scan submissions: POST { barcode, lab }
// Maps to the active subject in the specified lab based on schedule and records attendance.

date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/kiosk_config.php';

// Check database connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database connection failed']);
    exit();
}

// Set charset for proper encoding
$conn->set_charset('utf8mb4');

// Auth: allow either a logged-in session or a valid kiosk token via POST param 'kiosk'
$kiosk = isset($_POST['kiosk']) ? trim($_POST['kiosk']) : '';
if (!isset($_SESSION['user']) && (!defined('KIOSK_TOKEN') || $kiosk !== KIOSK_TOKEN)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit();
}

$barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '';
$lab = isset($_POST['lab']) ? trim($_POST['lab']) : '';

if ($barcode === '' || $lab === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing barcode or lab']);
    exit();
}

// Helper: check if Time Out is allowed based on subject schedule (10 minutes before end)
function is_timeout_allowed_for_subject($subject) {
    if (!$subject || empty($subject['start_time']) || empty($subject['end_time'])) return false;
    $nowTime = date('H:i:s');
    $endTime = $subject['end_time'];
    $endDT = DateTime::createFromFormat('H:i:s', $endTime);
    if (!$endDT) return false;
    $tenMinBefore = clone $endDT;
    $tenMinBefore->modify('-10 minutes');
    return ($nowTime >= $tenMinBefore->format('H:i:s'));
}

try {
    // Find student by barcode
    $stuStmt = $conn->prepare('SELECT student_id, name, profile_pic, course, year_level, section FROM students WHERE barcode = ?');
    if (!$stuStmt) {
        throw new Exception('Failed to prepare student query: ' . $conn->error);
    }
    $stuStmt->bind_param('s', $barcode);
    if (!$stuStmt->execute()) {
        throw new Exception('Failed to execute student query: ' . $stuStmt->error);
    }
    $student = $stuStmt->get_result()->fetch_assoc();
    $stuStmt->close();

    if (!$student) {
        echo json_encode(['ok' => false, 'message' => 'Student not found']);
        exit();
    }

    $student_id = $student['student_id'];
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $nowTime = date('H:i:s');
    $dayShort = date('D'); // Mon, Tue, ...

    // Find active subject in this lab right now
    // Note: schedule_days stores tokens like Mon,Tue,...; we use FIND_IN_SET with dayShort
    $subSql = "SELECT id, subject_name, start_time, end_time, schedule_days, lab
               FROM subjects
               WHERE lab = ?
                 AND (FIND_IN_SET(?, schedule_days) > 0)
                 AND start_time IS NOT NULL AND end_time IS NOT NULL
                 AND (
                        (start_time <= end_time AND start_time <= ? AND end_time >= ?)
                     OR (start_time > end_time AND ( ? >= start_time OR ? <= end_time ))
                 )
               ORDER BY end_time ASC
               LIMIT 1";
    $subStmt = $conn->prepare($subSql);
    if (!$subStmt) {
        throw new Exception('Failed to prepare subject query: ' . $conn->error);
    }
    $subStmt->bind_param('ssssss', $lab, $dayShort, $nowTime, $nowTime, $nowTime, $nowTime);
    if (!$subStmt->execute()) {
        throw new Exception('Failed to execute subject query: ' . $subStmt->error);
    }
    $active = $subStmt->get_result()->fetch_assoc();
    $subStmt->close();

    if (!$active) {
        echo json_encode(['ok' => false, 'message' => 'No active subject in this lab right now', 'student' => [
            'student_id' => $student['student_id'],
            'name' => $student['name'] ?? '',
            'profile_pic' => !empty($student['profile_pic']) ? ('assets/img/' . basename($student['profile_pic'])) : '',
            'course' => $student['course'] ?? '',
            'year_level' => $student['year_level'] ?? '',
            'section' => $student['section'] ?? ''
        ]]);
        exit();
    }

    $subject_id = (int)$active['id'];
    $subject_name = $active['subject_name'];


    // Check registration in this subject
    $reg = $conn->prepare('SELECT COUNT(*) AS c FROM student_subjects WHERE student_id = ? AND subject_id = ?');
    $reg->bind_param('si', $student_id, $subject_id);
    $reg->execute();
    $c = $reg->get_result()->fetch_assoc()['c'] ?? 0;
    $reg->close();

    if ((int)$c === 0) {
        echo json_encode(['ok' => false, 'message' => 'Student is not registered in the active subject', 'subject' => $subject_name, 'student' => [
            'student_id' => $student['student_id'],
            'name' => $student['name'] ?? '',
            'profile_pic' => !empty($student['profile_pic']) ? ('assets/img/' . basename($student['profile_pic'])) : '',
            'course' => $student['course'] ?? '',
            'year_level' => $student['year_level'] ?? '',
            'section' => $student['section'] ?? ''
        ]]);
        exit();
    }

    // Dedupe guard
    $dedupe_seconds = 5;
    $dedupe = $conn->prepare('SELECT scan_time, status FROM attendance WHERE student_id = ? AND subject_id = ? ORDER BY scan_time DESC LIMIT 1');
    $dedupe->bind_param('si', $student_id, $subject_id);
    $dedupe->execute();
    $dr = $dedupe->get_result()->fetch_assoc();
    $dedupe->close();
    $allowInsert = true;
    if ($dr) {
        $lastScan = strtotime($dr['scan_time']);
        if (time() - $lastScan <= $dedupe_seconds) {
            $allowInsert = false;
        }
    }
    if (!$allowInsert) {
        echo json_encode(['ok' => true, 'message' => 'Please wait before scanning again', 'subject' => $subject_name, 'student' => [
            'student_id' => $student['student_id'],
            'name' => $student['name'] ?? '',
            'profile_pic' => !empty($student['profile_pic']) ? ('assets/img/' . basename($student['profile_pic'])) : '',
            'course' => $student['course'] ?? '',
            'year_level' => $student['year_level'] ?? '',
            'section' => $student['section'] ?? ''
        ]]);
        exit();
    }

    // Determine if we should Time In or Time Out
    // If no Present yet today for this subject -> Time In
    $presentChk = $conn->prepare("SELECT COUNT(*) AS c FROM attendance WHERE student_id = ? AND subject_id = ? AND DATE(scan_time) = ? AND status = 'Present'");
    $presentChk->bind_param('sis', $student_id, $subject_id, $today);
    $presentChk->execute();
    $presentCount = $presentChk->get_result()->fetch_assoc()['c'] ?? 0;
    $presentChk->close();

    if ((int)$presentCount === 0) {
        $ins = $conn->prepare("INSERT INTO attendance (student_id, subject_id, subject_name, semester_id, scan_time, status) VALUES (?, ?, ?, ?, ?, 'Present')");
        $ins->bind_param('ssiss', $student_id, $subject_id, $subject_name, 1, $now);
        $ins->execute();
        $ins->close();
        echo json_encode(['ok' => true, 'message' => 'Time In recorded', 'subject' => $subject_name, 'student' => [
            'student_id' => $student['student_id'],
            'name' => $student['name'] ?? '',
            'profile_pic' => !empty($student['profile_pic']) ? ('assets/img/' . basename($student['profile_pic'])) : '',
            'course' => $student['course'] ?? '',
            'year_level' => $student['year_level'] ?? '',
            'section' => $student['section'] ?? ''
        ]]);
        exit();
    }

    // Else attempt Time Out if allowed and not already signed out today
    $signedOutChk = $conn->prepare("SELECT COUNT(*) AS c FROM attendance WHERE student_id = ? AND subject_id = ? AND DATE(scan_time) = ? AND status = 'Signed Out'");
    $signedOutChk->bind_param('sis', $student_id, $subject_id, $today);
    $signedOutChk->execute();
    $signedOutCount = $signedOutChk->get_result()->fetch_assoc()['c'] ?? 0;
    $signedOutChk->close();

    if ((int)$signedOutCount > 0) {
        echo json_encode(['ok' => true, 'message' => 'Already completed attendance for this subject today', 'subject' => $subject_name, 'student' => [
            'student_id' => $student['student_id'],
            'name' => $student['name'] ?? '',
            'profile_pic' => !empty($student['profile_pic']) ? ('assets/img/' . basename($student['profile_pic'])) : '',
            'course' => $student['course'] ?? '',
            'year_level' => $student['year_level'] ?? '',
            'section' => $student['section'] ?? ''
        ]]);
        exit();
    }

    if (!is_timeout_allowed_for_subject($active)) {
        echo json_encode(['ok' => false, 'message' => 'Time Out only allowed in the last 10 minutes or after class end', 'subject' => $subject_name, 'student' => [
            'student_id' => $student['student_id'],
            'name' => $student['name'] ?? '',
            'profile_pic' => !empty($student['profile_pic']) ? ('assets/img/' . basename($student['profile_pic'])) : '',
            'course' => $student['course'] ?? '',
            'year_level' => $student['year_level'] ?? '',
            'section' => $student['section'] ?? ''
        ]]);
        exit();
    }

    $ins2 = $conn->prepare("INSERT INTO attendance (student_id, subject_id, subject_name, semester_id, scan_time, status) VALUES (?, ?, ?, ?, ?, 'Signed Out')");
    $ins2->bind_param('ssiss', $student_id, $subject_id, $subject_name, 1, $now);
    $ins2->execute();
    $ins2->close();

    echo json_encode(['ok' => true, 'message' => 'Time Out recorded', 'subject' => $subject_name, 'student' => [
        'student_id' => $student['student_id'],
        'name' => $student['name'] ?? '',
        'profile_pic' => !empty($student['profile_pic']) ? ('assets/img/' . basename($student['profile_pic'])) : '',
        'course' => $student['course'] ?? '',
        'year_level' => $student['year_level'] ?? '',
        'section' => $student['section'] ?? ''
    ]]);
} catch (mysqli_sql_exception $e) {
    error_log('Lab scan SQL error: ' . $e->getMessage() . ' | Barcode: ' . $barcode . ' | Lab: ' . $lab);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database error - please try again']);
} catch (Exception $e) {
    error_log('Lab scan error: ' . $e->getMessage() . ' | Barcode: ' . $barcode . ' | Lab: ' . $lab);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error - please try again']);
} catch (Throwable $e) {
    error_log('Lab scan fatal error: ' . $e->getMessage() . ' | Barcode: ' . $barcode . ' | Lab: ' . $lab);
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'System error - please contact support']);
}
