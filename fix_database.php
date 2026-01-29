<?php
// Fix database schema for attendance system
require_once 'includes/db.php';

echo "<h1>Database Fix Script</h1>";

try {
    // Step 1: Check if semesters table exists and has data
    echo "<h2>Step 1: Check Semesters Table</h2>";
    $result = $conn->query("SELECT COUNT(*) as count FROM semesters");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "Semesters found: $count<br>";
        
        if ($count == 0) {
            echo "Creating default semester...<br>";
            $insert = $conn->query("INSERT INTO semesters (id, name, start_date, end_date) VALUES (1, 'Academic Year 2024-2025', '2024-08-01', '2025-07-31')");
            if ($insert) {
                echo "✅ Default semester created!<br>";
            } else {
                echo "❌ Error: " . $conn->error . "<br>";
            }
        }
    } else {
        echo "❌ Error checking semesters: " . $conn->error . "<br>";
    }

    // Step 2: Check if attendance table has semester_id column
    echo "<h2>Step 2: Check Attendance Table Structure</h2>";
    $columns = $conn->query("SHOW COLUMNS FROM attendance LIKE 'semester_id'");
    if ($columns && $columns->num_rows == 0) {
        echo "Adding semester_id column...<br>";
        $alter = $conn->query("ALTER TABLE attendance ADD COLUMN semester_id INT DEFAULT 1 AFTER subject_id");
        if ($alter) {
            echo "✅ semester_id column added!<br>";
        } else {
            echo "❌ Error adding column: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ semester_id column already exists!<br>";
    }

    // Step 3: Add foreign key constraint if it doesn't exist
    echo "<h2>Step 3: Check Foreign Key Constraint</h2>";
    $fk_check = $conn->query("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'aiesccsdata' 
        AND TABLE_NAME = 'attendance' 
        AND COLUMN_NAME = 'semester_id' 
        AND REFERENCED_TABLE_NAME = 'semesters'
    ");
    
    if ($fk_check && $fk_check->num_rows == 0) {
        echo "Adding foreign key constraint...<br>";
        $fk = $conn->query("ALTER TABLE attendance ADD CONSTRAINT fk_attendance_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON UPDATE CASCADE");
        if ($fk) {
            echo "✅ Foreign key constraint added!<br>";
        } else {
            echo "⚠️ Foreign key constraint failed (might already exist): " . $conn->error . "<br>";
        }
    } else {
        echo "✅ Foreign key constraint already exists!<br>";
    }

    // Step 4: Update existing attendance records to have semester_id = 1
    echo "<h2>Step 4: Update Existing Records</h2>";
    $update = $conn->query("UPDATE attendance SET semester_id = 1 WHERE semester_id IS NULL OR semester_id = 0");
    if ($update) {
        echo "✅ Updated existing attendance records!<br>";
    } else {
        echo "❌ Error updating records: " . $conn->error . "<br>";
    }

    echo "<h2>✅ Database Fix Complete!</h2>";
    echo "<p><a href='student_dashboard_lab.php?lab=Computer Lab B'>Test Lab Dashboard</a></p>";

} catch (Exception $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>";
}

$conn->close();
?>
