<?php
include 'includes/db.php';

echo "<h1>Emergency Database Fix</h1>";

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

// Insert default semester (ignore if exists)
$conn->query("INSERT IGNORE INTO semesters (id, name, start_date, end_date) VALUES (1, 'Academic Year 2024-2025', '2024-08-01', '2025-07-31')");

// Add semester_id column to attendance if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM attendance LIKE 'semester_id'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE attendance ADD COLUMN semester_id INT DEFAULT 1");
}

// Add semester_id column to subjects if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM subjects LIKE 'semester_id'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE subjects ADD COLUMN semester_id INT DEFAULT 1");
}

// Add semester_id column to student_subjects if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM student_subjects LIKE 'semester_id'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE student_subjects ADD COLUMN semester_id INT DEFAULT 1");
}

// Update all existing records to have semester_id = 1
$conn->query("UPDATE attendance SET semester_id = 1 WHERE semester_id IS NULL OR semester_id = 0");
$conn->query("UPDATE subjects SET semester_id = 1 WHERE semester_id IS NULL OR semester_id = 0");
$conn->query("UPDATE student_subjects SET semester_id = 1 WHERE semester_id IS NULL OR semester_id = 0");

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "<h2>✅ Emergency fix completed!</h2>";
echo "<p>Database structure has been updated and all records have been assigned to semester 1.</p>";
echo "<p><strong>You can now try scanning again.</strong></p>";

$conn->close();
?>
