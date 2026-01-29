<?php
session_start();
include 'includes/db.php';

date_default_timezone_set('Asia/Manila');

echo "<h2>Testing Teacher Attendance Database Connection</h2>";

// Test 1: Check database connection
if ($conn) {
    echo "<p style='color: green;'>✅ Database connection successful</p>";
} else {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
    exit();
}

// Test 2: Check if attendance table exists and has data
$test_query = "SELECT COUNT(*) as total FROM attendance WHERE DATE(scan_time) = CURDATE()";
$result = $conn->query($test_query);
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p style='color: green;'>✅ Attendance table accessible - Today's records: " . $row['total'] . "</p>";
} else {
    echo "<p style='color: red;'>❌ Error accessing attendance table: " . $conn->error . "</p>";
}

// Test 3: Check recent attendance records
echo "<h3>Recent Attendance Records (Last 10):</h3>";
$recent_query = "
    SELECT a.*, s.name as student_name, sub.subject_name 
    FROM attendance a 
    LEFT JOIN students s ON a.student_id = s.student_id 
    LEFT JOIN subjects sub ON a.subject_id = sub.id 
    ORDER BY a.scan_time DESC 
    LIMIT 10
";
$recent_result = $conn->query($recent_query);

if ($recent_result && $recent_result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Student ID</th><th>Student Name</th><th>Subject</th><th>Status</th><th>Scan Time</th></tr>";
    
    while ($record = $recent_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($record['student_id']) . "</td>";
        echo "<td>" . htmlspecialchars($record['student_name'] ?? 'Unknown') . "</td>";
        echo "<td>" . htmlspecialchars($record['subject_name'] ?? 'Unknown') . "</td>";
        echo "<td>" . htmlspecialchars($record['status']) . "</td>";
        echo "<td>" . htmlspecialchars($record['scan_time']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p style='color: green;'>✅ Recent attendance records found</p>";
} else {
    echo "<p style='color: orange;'>⚠️ No recent attendance records found</p>";
}

// Test 4: Check if teacher session exists
if (isset($_SESSION['teacher_id'])) {
    echo "<p style='color: green;'>✅ Teacher session active: " . htmlspecialchars($_SESSION['teacher_name']) . " (ID: " . htmlspecialchars($_SESSION['teacher_id']) . ")</p>";
    
    // Test teacher's subjects
    $teacher_subjects_query = $conn->prepare("
        SELECT s.* FROM subjects s 
        WHERE s.teacher_id = (SELECT id FROM teachers WHERE teacher_id = ?)
    ");
    $teacher_subjects_query->bind_param("s", $_SESSION['teacher_id']);
    $teacher_subjects_query->execute();
    $subjects_result = $teacher_subjects_query->get_result();
    
    echo "<h3>Teacher's Subjects:</h3>";
    if ($subjects_result->num_rows > 0) {
        while ($subject = $subjects_result->fetch_assoc()) {
            echo "<p>• " . htmlspecialchars($subject['subject_name']) . " (" . htmlspecialchars($subject['subject_code']) . ")</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ No subjects assigned to this teacher</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ No teacher session active - please login first</p>";
}

// Test 5: Real-time data test
echo "<h3>Real-time Data Test:</h3>";
echo "<div id='realtime-test'>Loading...</div>";

echo "<script>
function testRealTimeData() {
    fetch('ajax/dashboard_data.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('realtime-test').innerHTML = 
                '<p style=\"color: green;\">✅ Real-time AJAX working</p>' +
                '<p>Today\\'s attendance: ' + (data.today_attendance || 0) + '</p>' +
                '<p>Time In: ' + (data.attendance_stats ? data.attendance_stats['Time In'] || 0 : 0) + '</p>' +
                '<p>Time Out: ' + (data.attendance_stats ? data.attendance_stats['Time Out'] || 0 : 0) + '</p>' +
                '<p>Current time: ' + (data.current_time || 'Unknown') + '</p>';
        })
        .catch(error => {
            document.getElementById('realtime-test').innerHTML = 
                '<p style=\"color: red;\">❌ Real-time AJAX failed: ' + error.message + '</p>';
        });
}

// Test immediately and every 3 seconds
testRealTimeData();
setInterval(testRealTimeData, 3000);
</script>";

echo "<br><p><a href='teacher_dashboard.php'>← Back to Teacher Dashboard</a></p>";
?>
