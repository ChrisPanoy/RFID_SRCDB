<?php
// Test the scanning functionality
require_once 'includes/db.php';

echo "<h2>Database Test</h2>";

// Check attendance table structure
echo "<h3>Attendance Table Structure:</h3>";
$result = $conn->query("DESCRIBE attendance");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Test basic attendance functionality
echo "<h3>Testing Attendance Table:</h3>";
$test = $conn->query("SELECT COUNT(*) as count FROM attendance");
if ($test) {
    $count = $test->fetch_assoc()['count'];
    echo "✅ Attendance table accessible. Records: " . $count;
} else {
    echo "❌ Error accessing attendance table: " . $conn->error;
}

$conn->close();
?>
