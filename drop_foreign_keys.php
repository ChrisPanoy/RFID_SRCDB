<?php
include 'includes/db.php';

echo "<h1>Drop Foreign Key Constraints</h1>";

try {
    // Disable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    echo "<h2>Dropping Foreign Key Constraints...</h2>";
    
    // Drop foreign key constraint from attendance table
    $drop_attendance_fk = $conn->query("ALTER TABLE attendance DROP FOREIGN KEY IF EXISTS fk_attendance_semester");
    if ($drop_attendance_fk) {
        echo "✅ Dropped fk_attendance_semester constraint<br>";
    } else {
        echo "⚠️ fk_attendance_semester constraint might not exist: " . $conn->error . "<br>";
    }
    
    // Drop foreign key constraint from subjects table
    $drop_subjects_fk = $conn->query("ALTER TABLE subjects DROP FOREIGN KEY IF EXISTS fk_subjects_semester");
    if ($drop_subjects_fk) {
        echo "✅ Dropped fk_subjects_semester constraint<br>";
    } else {
        echo "⚠️ fk_subjects_semester constraint might not exist: " . $conn->error . "<br>";
    }
    
    // Drop foreign key constraint from student_subjects table
    $drop_student_subjects_fk = $conn->query("ALTER TABLE student_subjects DROP FOREIGN KEY IF EXISTS fk_student_subjects_semester");
    if ($drop_student_subjects_fk) {
        echo "✅ Dropped fk_student_subjects_semester constraint<br>";
    } else {
        echo "⚠️ fk_student_subjects_semester constraint might not exist: " . $conn->error . "<br>";
    }
    
    echo "<h2>Making semester_id columns nullable...</h2>";
    
    // Make semester_id nullable in attendance table
    $modify_attendance = $conn->query("ALTER TABLE attendance MODIFY COLUMN semester_id INT NULL");
    if ($modify_attendance) {
        echo "✅ Made semester_id nullable in attendance table<br>";
    } else {
        echo "❌ Error modifying attendance table: " . $conn->error . "<br>";
    }
    
    // Make semester_id nullable in subjects table
    $modify_subjects = $conn->query("ALTER TABLE subjects MODIFY COLUMN semester_id INT NULL");
    if ($modify_subjects) {
        echo "✅ Made semester_id nullable in subjects table<br>";
    } else {
        echo "❌ Error modifying subjects table: " . $conn->error . "<br>";
    }
    
    // Make semester_id nullable in student_subjects table
    $modify_student_subjects = $conn->query("ALTER TABLE student_subjects MODIFY COLUMN semester_id INT NULL");
    if ($modify_student_subjects) {
        echo "✅ Made semester_id nullable in student_subjects table<br>";
    } else {
        echo "❌ Error modifying student_subjects table: " . $conn->error . "<br>";
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<h2>✅ Foreign key constraints removed!</h2>";
    echo "<p><strong>Teacher scan should work now without foreign key errors.</strong></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>";
}

$conn->close();
?>
