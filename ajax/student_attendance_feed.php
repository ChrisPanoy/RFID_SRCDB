<?php
// Feed for recent student attendance scans (returns JSON)
// Returns: id, student_id, name, status, scan_time, subject_name, photo, course, year_level

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

// Include DB
$dbPath = __DIR__ . '/../includes/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => 'DB file missing']);
    exit();
}
require_once $dbPath;
// Include kiosk token (for public kiosk access)
require_once __DIR__ . '/../includes/kiosk_config.php';

// Auth: allow either a logged-in session or a valid kiosk token via GET param 'kiosk'
$kiosk = isset($_GET['kiosk']) ? trim($_GET['kiosk']) : '';
if (!isset($_SESSION['user']) && (!defined('KIOSK_TOKEN') || $kiosk !== KIOSK_TOKEN)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Optional role check
$allowed_roles = ['student', 'teacher', 'admin'];
// If using kiosk token, skip role enforcement; otherwise enforce as before
if (!isset($kiosk) || $kiosk === '' || (defined('KIOSK_TOKEN') && $kiosk !== KIOSK_TOKEN)) {
    if (isset($_SESSION['user']['role']) && !in_array($_SESSION['user']['role'], $allowed_roles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit();
    }
}

// Optional lab filter (lab name as used in facility.lab_name)
$lab = isset($_GET['lab']) ? trim($_GET['lab']) : '';

// Preferred filters
$lab_id = isset($_GET['lab_id']) ? (int)$_GET['lab_id'] : 0;
$schedule_id = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;

// Base SQL using current schema:
// attendance (per day per admission/schedule), admissions, schedule, subjects, students, facilities, courses, year_levels
// We build a "scan_time" by combining attendance_date with time_out if present, otherwise time_in.

if ($schedule_id > 0) {
    // Filter by schedule_id (most accurate: locks feed to the current subject schedule)
    $sql = "SELECT 
                a.attendance_id AS id,
                stu.student_id AS student_id,
                CONCAT(COALESCE(stu.last_name, ''), ', ', COALESCE(stu.first_name, ''), ' ', COALESCE(stu.middle_name, '')) AS name,
                a.status,
                CASE 
                    WHEN a.time_out IS NOT NULL AND a.time_out <> '' THEN CONCAT(a.attendance_date, ' ', a.time_out)
                    ELSE CONCAT(a.attendance_date, ' ', a.time_in)
                END AS scan_time,
                sub.subject_name,
                stu.profile_picture AS profile_picture,
                c.course_code AS course,
                yl.year_name AS year_level,
                sec.section_name AS section,
                pa.pc_number AS pc_number
            FROM attendance a
            JOIN admissions adm      ON a.admission_id = adm.admission_id
            JOIN students stu       ON adm.student_id = stu.student_id
            JOIN schedule sch       ON a.schedule_id = sch.schedule_id
            JOIN subjects sub        ON sch.subject_id = sub.subject_id
            LEFT JOIN courses c      ON adm.course_id = c.course_id
            LEFT JOIN year_levels yl ON adm.year_level_id = yl.year_id
            LEFT JOIN sections sec   ON adm.section_id = sec.section_id
            LEFT JOIN pc_assignment pa ON pa.student_id = stu.student_id AND pa.lab_id = sch.lab_id
            WHERE a.schedule_id = ?
              AND a.attendance_date = CURDATE()
            ORDER BY scan_time DESC
            LIMIT 50";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare schedule query: ' . $conn->error);
    }
    $stmt->bind_param('i', $schedule_id);
} elseif ($lab_id > 0) {
    // Filter by lab_id (schedule.lab_id)
    $sql = "SELECT 
                a.attendance_id AS id,
                stu.student_id AS student_id,
                CONCAT(COALESCE(stu.last_name, ''), ', ', COALESCE(stu.first_name, ''), ' ', COALESCE(stu.middle_name, '')) AS name,
                a.status,
                CASE 
                    WHEN a.time_out IS NOT NULL AND a.time_out <> '' THEN CONCAT(a.attendance_date, ' ', a.time_out)
                    ELSE CONCAT(a.attendance_date, ' ', a.time_in)
                END AS scan_time,
                sub.subject_name,
                stu.profile_picture AS profile_picture,
                c.course_code AS course,
                yl.year_name AS year_level,
                sec.section_name AS section,
                pa.pc_number AS pc_number
            FROM attendance a
            JOIN admissions adm      ON a.admission_id = adm.admission_id
            JOIN students stu       ON adm.student_id = stu.student_id
            JOIN schedule sch       ON a.schedule_id = sch.schedule_id
            JOIN subjects sub        ON sch.subject_id = sub.subject_id
            LEFT JOIN courses c      ON adm.course_id = c.course_id
            LEFT JOIN year_levels yl ON adm.year_level_id = yl.year_id
            LEFT JOIN sections sec   ON adm.section_id = sec.section_id
            LEFT JOIN pc_assignment pa ON pa.student_id = stu.student_id AND pa.lab_id = sch.lab_id
            WHERE sch.lab_id = ?
              AND a.attendance_date = CURDATE()
            ORDER BY scan_time DESC
            LIMIT 50";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare lab_id query: ' . $conn->error);
    }
    $stmt->bind_param('i', $lab_id);
} elseif ($lab !== '') {
    // Filter by lab name (facility.lab_name) and only show today's records for that lab
    $sql = "SELECT 
                a.attendance_id AS id,
                stu.student_id AS student_id,
                CONCAT(COALESCE(stu.last_name, ''), ', ', COALESCE(stu.first_name, ''), ' ', COALESCE(stu.middle_name, '')) AS name,
                a.status,
                CASE 
                    WHEN a.time_out IS NOT NULL AND a.time_out <> '' THEN CONCAT(a.attendance_date, ' ', a.time_out)
                    ELSE CONCAT(a.attendance_date, ' ', a.time_in)
                END AS scan_time,
                sub.subject_name,
                stu.profile_picture AS profile_picture,
                c.course_code AS course,
                yl.year_name AS year_level,
                sec.section_name AS section,
                pa.pc_number AS pc_number
            FROM attendance a
            JOIN admissions adm      ON a.admission_id = adm.admission_id
            JOIN students stu       ON adm.student_id = stu.student_id
            JOIN schedule sch       ON a.schedule_id = sch.schedule_id
            JOIN subjects sub        ON sch.subject_id = sub.subject_id
            LEFT JOIN facilities fac  ON sch.lab_id = fac.lab_id
            LEFT JOIN courses c      ON adm.course_id = c.course_id
            LEFT JOIN year_levels yl ON adm.year_level_id = yl.year_id
            LEFT JOIN sections sec   ON adm.section_id = sec.section_id
            LEFT JOIN pc_assignment pa ON pa.student_id = stu.student_id AND pa.lab_id = sch.lab_id
            WHERE fac.lab_name = ?
              AND a.attendance_date = CURDATE()
            ORDER BY scan_time DESC
            LIMIT 50";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare lab query: ' . $conn->error);
    }
    $stmt->bind_param('s', $lab);
} else {
    // Default: last 50 attendance for today (all labs)
    $sql = "SELECT 
                a.attendance_id AS id,
                stu.student_id AS student_id,
                CONCAT(COALESCE(stu.last_name, ''), ', ', COALESCE(stu.first_name, ''), ' ', COALESCE(stu.middle_name, '')) AS name,
                a.status,
                CASE 
                    WHEN a.time_out IS NOT NULL AND a.time_out <> '' THEN CONCAT(a.attendance_date, ' ', a.time_out)
                    ELSE CONCAT(a.attendance_date, ' ', a.time_in)
                END AS scan_time,
                sub.subject_name,
                stu.profile_picture AS profile_picture,
                c.course_code AS course,
                yl.year_name AS year_level,
                sec.section_name AS section,
                pa.pc_number AS pc_number
            FROM attendance a
            JOIN admissions adm      ON a.admission_id = adm.admission_id
            JOIN students stu       ON adm.student_id = stu.student_id
            JOIN schedule sch       ON a.schedule_id = sch.schedule_id
            JOIN subjects sub        ON sch.subject_id = sub.subject_id
            LEFT JOIN courses c      ON adm.course_id = c.course_id
            LEFT JOIN year_levels yl ON adm.year_level_id = yl.year_id
            LEFT JOIN sections sec   ON adm.section_id = sec.section_id
            LEFT JOIN pc_assignment pa ON pa.student_id = stu.student_id AND pa.lab_id = sch.lab_id
            WHERE a.attendance_date = CURDATE()
            ORDER BY scan_time DESC
            LIMIT 50";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare default query: ' . $conn->error);
    }
}

try {
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception('Failed to get result: ' . $conn->error);
    }

    $rows = [];
    while ($r = $result->fetch_assoc()) {
        // Build safe relative photo path (assets/img/<filename>) if profile_picture present
        $photo = '';
        if (!empty($r['profile_picture'])) {
            $photoFilename = basename($r['profile_picture']);
            // Ensure file exists before returning path (optional; avoids broken URLs)
            $candidate = __DIR__ . '/../assets/img/' . $photoFilename;
            if (file_exists($candidate)) {
                $photo = 'assets/img/' . $photoFilename;
            } else {
                // still return constructed path (in case files are stored differently)
                $photo = 'assets/img/' . $photoFilename;
            }
        }

        $rows[] = [
            'id' => $r['id'],
            'student_id' => $r['student_id'],
            'name' => $r['name'],
            'status' => $r['status'],
            'scan_time' => $r['scan_time'],
            'subject_name' => $r['subject_name'],
            'photo' => $photo,
            'course' => $r['course'],
            'year_level' => $r['year_level'],
            'section' => $r['section'],
            'pc_number' => $r['pc_number']
        ];
    }

    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
}
?>
