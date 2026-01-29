<?php
include 'includes/db.php';

echo "<h1>Database Schema Setup</h1>";

try {
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    echo "✅ Disabled foreign key checks<br>";
    
    // Create semesters table if it doesn't exist
    $create_semesters = $conn->query("
        CREATE TABLE IF NOT EXISTS semesters (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            start_date DATE,
            end_date DATE
        )
    ");
    if ($create_semesters) {
        echo "✅ Semesters table created/verified<br>";
    } else {
        echo "❌ Error creating semesters table: " . $conn->error . "<br>";
    }
    
    // Insert default semester
    $insert_semester = $conn->query("INSERT IGNORE INTO semesters (id, name, start_date, end_date) VALUES (1, 'Academic Year 2024-2025', '2024-08-01', '2025-07-31')");
    if ($insert_semester) {
        echo "✅ Default semester created/verified<br>";
    } else {
        echo "❌ Error creating default semester: " . $conn->error . "<br>";
    }
    
    // Check if semester_id column exists in subjects table
    $result = $conn->query("SHOW COLUMNS FROM subjects LIKE 'semester_id'");
    if ($result->num_rows == 0) {
        // Add semester_id column to subjects table
        $add_column = $conn->query("ALTER TABLE subjects ADD COLUMN semester_id INT DEFAULT 1");
        if ($add_column) {
            echo "✅ Added semester_id column to subjects table<br>";
        } else {
            echo "❌ Error adding semester_id column to subjects: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ semester_id column already exists in subjects table<br>";
    }
    
    // Update existing subjects without semester_id
    $update_subjects = $conn->query("UPDATE subjects SET semester_id = 1 WHERE semester_id IS NULL OR semester_id = 0");
    if ($update_subjects) {
        echo "✅ Updated existing subjects with semester_id = 1<br>";
    } else {
        echo "❌ Error updating subjects: " . $conn->error . "<br>";
    }
    
    // Check if semester_id column exists in attendance table
    $result = $conn->query("SHOW COLUMNS FROM attendance LIKE 'semester_id'");
    if ($result->num_rows == 0) {
        // Add semester_id column to attendance table
        $add_column = $conn->query("ALTER TABLE attendance ADD COLUMN semester_id INT DEFAULT 1");
        if ($add_column) {
            echo "✅ Added semester_id column to attendance table<br>";
        } else {
            echo "❌ Error adding semester_id column to attendance: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ semester_id column already exists in attendance table<br>";
    }
    
    // Update existing attendance records without semester_id
    $update_attendance = $conn->query("UPDATE attendance SET semester_id = 1 WHERE semester_id IS NULL OR semester_id = 0");
    if ($update_attendance) {
        echo "✅ Updated existing attendance records with semester_id = 1<br>";
    } else {
        echo "❌ Error updating attendance records: " . $conn->error . "<br>";
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    echo "✅ Re-enabled foreign key checks<br>";
    
    // Add foreign key constraint for subjects.semester_id if it doesn't exist
    $check_constraint = $conn->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'aiesccscdata' 
        AND TABLE_NAME = 'subjects' 
        AND COLUMN_NAME = 'semester_id' 
        AND REFERENCED_TABLE_NAME = 'semesters'
    ");
    
    if ($check_constraint->num_rows == 0) {
        $add_constraint = $conn->query("
            ALTER TABLE subjects 
            ADD CONSTRAINT fk_subjects_semester 
            FOREIGN KEY (semester_id) REFERENCES semesters(id) 
            ON UPDATE CASCADE ON DELETE SET NULL
        ");
        if ($add_constraint) {
            echo "✅ Added foreign key constraint for subjects.semester_id<br>";
        } else {
            echo "❌ Error adding foreign key constraint: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ Foreign key constraint for subjects.semester_id already exists<br>";
    }
    
    // Add foreign key constraint for attendance.semester_id if it doesn't exist
    $check_constraint_att = $conn->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'aiesccscdata' 
        AND TABLE_NAME = 'attendance' 
        AND COLUMN_NAME = 'semester_id' 
        AND REFERENCED_TABLE_NAME = 'semesters'
    ");
    
    if ($check_constraint_att->num_rows == 0) {
        $add_constraint_att = $conn->query("
            ALTER TABLE attendance 
            ADD CONSTRAINT fk_attendance_semester 
            FOREIGN KEY (semester_id) REFERENCES semesters(id) 
            ON UPDATE CASCADE ON DELETE SET NULL
        ");
        if ($add_constraint_att) {
            echo "✅ Added foreign key constraint for attendance.semester_id<br>";
        } else {
            echo "❌ Error adding foreign key constraint for attendance: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ Foreign key constraint for attendance.semester_id already exists<br>";
    }
    
    echo "<h2>✅ Database schema setup completed successfully!</h2>";
    echo "<p>All tables now have proper semester_id columns and foreign key constraints.</p>";
    echo "<p>You can now add subjects without foreign key constraint errors.</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>";
} finally {
    // Always re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
}

$conn->close();
?>
