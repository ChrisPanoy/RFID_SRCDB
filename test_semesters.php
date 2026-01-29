<?php
include 'includes/db.php';

echo "<h2>Testing Semesters Table</h2>";

// Check if semesters table exists and has data
try {
    $result = $conn->query("SELECT * FROM semesters");
    if ($result) {
        echo "<h3>Semesters found:</h3>";
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . $row['id'] . " - Name: " . $row['name'] . "<br>";
        }
        if ($result->num_rows == 0) {
            echo "No semesters found in the table.<br>";
            
            // Try to create default semester
            echo "<h3>Creating default semester...</h3>";
            $insert = $conn->query("INSERT INTO semesters (id, name, start_date, end_date) VALUES (1, 'Academic Year 2024-2025', '2024-08-01', '2025-07-31')");
            if ($insert) {
                echo "✅ Default semester created!<br>";
            } else {
                echo "❌ Error creating semester: " . $conn->error . "<br>";
            }
        }
    } else {
        echo "❌ Error accessing semesters table: " . $conn->error . "<br>";
        
        // Try to create the semesters table
        echo "<h3>Creating semesters table...</h3>";
        $create_table = $conn->query("
            CREATE TABLE IF NOT EXISTS semesters (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                start_date DATE,
                end_date DATE
            )
        ");
        if ($create_table) {
            echo "✅ Semesters table created!<br>";
            
            // Insert default semester
            $insert = $conn->query("INSERT INTO semesters (id, name, start_date, end_date) VALUES (1, 'Academic Year 2024-2025', '2024-08-01', '2025-07-31')");
            if ($insert) {
                echo "✅ Default semester created!<br>";
            } else {
                echo "❌ Error creating semester: " . $conn->error . "<br>";
            }
        } else {
            echo "❌ Error creating semesters table: " . $conn->error . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

$conn->close();
?>
