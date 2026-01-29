<?php
// Test script for debugging lab scan functionality
date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) session_start();

echo "<h2>Lab Scan Test Script</h2>\n";
echo "<p>Testing database connectivity and lab scan functionality...</p>\n";

// Test 1: Database Connection
echo "<h3>1. Database Connection Test</h3>\n";
try {
    require_once __DIR__ . '/includes/db.php';
    echo "✅ Database connection successful<br>\n";
    echo "Database name: " . $conn->get_server_info() . "<br>\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>\n";
    exit();
}

// Test 2: Check if required tables exist
echo "<h3>2. Table Structure Test</h3>\n";
$tables = ['students', 'subjects', 'attendance', 'student_subjects'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Table '$table' exists<br>\n";
    } else {
        echo "❌ Table '$table' missing<br>\n";
    }
}

// Test 3: Check for sample data
echo "<h3>3. Sample Data Test</h3>\n";
$studentCount = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$subjectCount = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];
echo "Students in database: $studentCount<br>\n";
echo "Subjects in database: $subjectCount<br>\n";

// Test 4: Test lab scan endpoint
echo "<h3>4. Lab Scan Endpoint Test</h3>\n";
echo "<form method='POST' action='ajax/lab_scan.php'>\n";
echo "Barcode: <input type='text' name='barcode' placeholder='Enter test barcode' required><br><br>\n";
echo "Lab: <input type='text' name='lab' placeholder='Computer Lab A' required><br><br>\n";
echo "Kiosk Token: <input type='text' name='kiosk' value='lab-kiosk-public-token-change-me-2025'><br><br>\n";
echo "<input type='submit' value='Test Scan'>\n";
echo "</form>\n";

// Test 5: Show recent attendance
echo "<h3>5. Recent Attendance Records</h3>\n";
try {
    $recent = $conn->query("SELECT a.*, s.name FROM attendance a LEFT JOIN students s ON a.student_id = s.student_id ORDER BY a.scan_time DESC LIMIT 5");
    if ($recent && $recent->num_rows > 0) {
        echo "<table border='1'>\n";
        echo "<tr><th>Student</th><th>Subject</th><th>Status</th><th>Scan Time</th></tr>\n";
        while ($row = $recent->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['name'] ?? $row['student_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['subject_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
            echo "<td>" . htmlspecialchars($row['scan_time']) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "No attendance records found.<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Error fetching attendance: " . $e->getMessage() . "<br>\n";
}

// Test 6: Show active subjects
echo "<h3>6. Active Subjects Today</h3>\n";
$today = date('D'); // Mon, Tue, etc.
$now = date('H:i:s');
try {
    $activeSubjects = $conn->query("SELECT * FROM subjects WHERE FIND_IN_SET('$today', schedule_days) > 0 AND start_time IS NOT NULL AND end_time IS NOT NULL");
    if ($activeSubjects && $activeSubjects->num_rows > 0) {
        echo "<table border='1'>\n";
        echo "<tr><th>Subject</th><th>Lab</th><th>Time</th><th>Days</th><th>Currently Active</th></tr>\n";
        while ($row = $activeSubjects->fetch_assoc()) {
            $isActive = ($row['start_time'] <= $now && $row['end_time'] >= $now) ? '✅' : '❌';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['subject_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['lab']) . "</td>";
            echo "<td>" . htmlspecialchars($row['start_time'] . ' - ' . $row['end_time']) . "</td>";
            echo "<td>" . htmlspecialchars($row['schedule_days']) . "</td>";
            echo "<td>$isActive</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "No subjects scheduled for today ($today).<br>\n";
    }
} catch (Exception $e) {
    echo "❌ Error fetching subjects: " . $e->getMessage() . "<br>\n";
}

echo "<hr>\n";
echo "<p><strong>Current time:</strong> " . date('Y-m-d H:i:s') . " (Day: $today)</p>\n";
echo "<p><a href='student_dashboard_lab.php?lab=Computer Lab A'>Test Student Dashboard</a></p>\n";
?>
