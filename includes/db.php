<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "src_lab";

// Enable mysqli exception mode for better error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset('utf8mb4');

    // Auto-load Active Academic Year and Semester into Session
    if (session_status() !== PHP_SESSION_NONE) {
        // We only fetch if not already set, to allow manual overrides if needed,
        // or we can refresh every time. Let's refresh every time to ensure consistency.
        
        $resAy = $conn->query("SELECT ay_id, ay_name FROM academic_years WHERE status = 'Active' LIMIT 1");
        if ($rowAy = $resAy->fetch_assoc()) {
            $_SESSION['active_ay_id'] = (int)$rowAy['ay_id'];
            $_SESSION['active_ay_name'] = $rowAy['ay_name'];
        }

        $resSem = $conn->query("SELECT semester_id, semester_now FROM semesters WHERE status = 'Active' LIMIT 1");
        if ($rowSem = $resSem->fetch_assoc()) {
            $_SESSION['active_sem_id'] = (int)$rowSem['semester_id'];
            $_SESSION['active_sem_now'] = $rowSem['semester_now'];
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        die("Database connection failed. Check your configuration.\n");
    } else {
        http_response_code(500);
        die("Database connection failed. Please try again later.");
    }
}
?>