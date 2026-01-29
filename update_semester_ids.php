<?php
include 'includes/db.php';

echo "<h1>Update Semester IDs Script</h1>";

try {
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Create semesters table if it doesn't exist
    $conn->query("
        CREATE TABLE IF NOT EXISTS semesters (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            start_date DATE,
            end_date DATE
        )
    ");
    
    // Insert default semester
    $conn->query("INSERT IGNORE INTO semesters (id, name, start_date, end_date) VALUES (1, 'Academic Year 2024-2025', '2024-08-01', '2025-07-31')");
    
    // Add semester_id column to attendance if it doesn't exist
    $result = $conn->query("SHOW COLUMNS FROM attendance LIKE 'semester_id'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE attendance ADD COLUMN semester_id INT DEFAULT 1");
        echo "✅ Added semester_id column to attendance table<br>";
    } else {
        echo "✅ semester_id column already exists in attendance table<br>";
    }
    
    // Update all attendance records without semester_id
    $update_result = $conn->query("UPDATE attendance SET semester_id = 1 WHERE semester_id IS NULL OR semester_id = 0");
    if ($update_result) {
        echo "✅ Updated attendance records with semester_id = 1<br>";
    } else {
        echo "❌ Error updating attendance records: " . $conn->error . "<br>";
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<h2>✅ Semester ID update completed!</h2>";
    echo "<p>All attendance records now have semester_id = 1</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>";
}

$conn->close();
?>
