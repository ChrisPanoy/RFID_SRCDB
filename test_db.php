<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'includes/db.php';

echo "<h2>Database Connection Test</h2>";
if ($conn->ping()) {
    echo "✅ Database connection successful<br>";
} else {
    echo "❌ Database connection failed<br>";
}

echo "<h2>Students Table Structure</h2>";
$result = $conn->query("DESCRIBE students");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Error getting table structure: " . $conn->error;
}

echo "<h2>Sample Students Data</h2>";
$result = $conn->query("SELECT * FROM students LIMIT 3");
if ($result) {
    echo "<table border='1'>";
    if ($result->num_rows > 0) {
        $first = true;
        while ($row = $result->fetch_assoc()) {
            if ($first) {
                echo "<tr>";
                foreach ($row as $key => $value) {
                    echo "<th>" . $key . "</th>";
                }
                echo "</tr>";
                $first = false;
            }
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
    }
    echo "</table>";
} else {
    echo "❌ Error getting students data: " . $conn->error;
}
?>

