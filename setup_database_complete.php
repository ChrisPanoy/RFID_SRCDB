<?php
include 'includes/db.php';

echo "<h1>Complete Database Setup Script</h1>";

try {
    // Step 1: Create semesters table if it doesn't exist
    echo "<h2>Step 1: Create Semesters Table</h2>";
    $create_semesters = $conn->query("
        CREATE TABLE IF NOT EXISTS semesters (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            start_date DATE,
            end_date DATE
        )
    ");
    if ($create_semesters) {
        echo "✅ Semesters table created/verified!<br>";
    } else {
        echo "❌ Error creating semesters table: " . $conn->error . "<br>";
    }

    // Step 2: Insert default semester if it doesn't exist
    echo "<h2>Step 2: Create Default Semester</h2>";
    $check_semester = $conn->query("SELECT COUNT(*) as count FROM semesters WHERE id = 1");
    if ($check_semester) {
        $count = $check_semester->fetch_assoc()['count'];
        if ($count == 0) {
            $insert_semester = $conn->query("INSERT INTO semesters (id, name, start_date, end_date) VALUES (1, 'Academic Year 2024-2025', '2024-08-01', '2025-07-31')");
            if ($insert_semester) {
                echo "✅ Default semester created!<br>";
            } else {
                echo "❌ Error creating semester: " . $conn->error . "<br>";
            }
        } else {
            echo "✅ Default semester already exists!<br>";
        }
    }

    // Step 3: Add semester_id column to attendance table if it doesn't exist
    echo "<h2>Step 3: Update Attendance Table</h2>";
    $check_attendance_column = $conn->query("SHOW COLUMNS FROM attendance LIKE 'semester_id'");
    if ($check_attendance_column && $check_attendance_column->num_rows == 0) {
        $add_column = $conn->query("ALTER TABLE attendance ADD COLUMN semester_id INT DEFAULT 1 AFTER subject_id");
        if ($add_column) {
            echo "✅ semester_id column added to attendance table!<br>";
        } else {
            echo "❌ Error adding column: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ semester_id column already exists in attendance table!<br>";
    }

    // Step 4: Add semester_id column to subjects table if it doesn't exist
    echo "<h2>Step 4: Update Subjects Table</h2>";
    $check_subjects_column = $conn->query("SHOW COLUMNS FROM subjects LIKE 'semester_id'");
    if ($check_subjects_column && $check_subjects_column->num_rows == 0) {
        $add_subjects_column = $conn->query("ALTER TABLE subjects ADD COLUMN semester_id INT DEFAULT 1 AFTER teacher_id");
        if ($add_subjects_column) {
            echo "✅ semester_id column added to subjects table!<br>";
        } else {
            echo "❌ Error adding column to subjects: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ semester_id column already exists in subjects table!<br>";
    }

    // Step 5: Add semester_id column to student_subjects table if it doesn't exist
    echo "<h2>Step 5: Update Student_Subjects Table</h2>";
    $check_student_subjects_column = $conn->query("SHOW COLUMNS FROM student_subjects LIKE 'semester_id'");
    if ($check_student_subjects_column && $check_student_subjects_column->num_rows == 0) {
        $add_student_subjects_column = $conn->query("ALTER TABLE student_subjects ADD COLUMN semester_id INT DEFAULT 1 AFTER subject_id");
        if ($add_student_subjects_column) {
            echo "✅ semester_id column added to student_subjects table!<br>";
        } else {
            echo "❌ Error adding column to student_subjects: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ semester_id column already exists in student_subjects table!<br>";
    }

    // Step 6: Add foreign key constraints
    echo "<h2>Step 6: Add Foreign Key Constraints</h2>";
    
    // Check and add foreign key for attendance table
    $fk_attendance_check = $conn->query("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'aiesccscdata' 
        AND TABLE_NAME = 'attendance' 
        AND COLUMN_NAME = 'semester_id' 
        AND REFERENCED_TABLE_NAME = 'semesters'
    ");
    
    if ($fk_attendance_check && $fk_attendance_check->fetch_assoc()['count'] == 0) {
        $add_fk_attendance = $conn->query("ALTER TABLE attendance ADD CONSTRAINT fk_attendance_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON UPDATE CASCADE");
        if ($add_fk_attendance) {
            echo "✅ Foreign key constraint added to attendance table!<br>";
        } else {
            echo "⚠️ Foreign key constraint for attendance might already exist: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ Foreign key constraint already exists for attendance table!<br>";
    }

    // Check and add foreign key for subjects table
    $fk_subjects_check = $conn->query("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'aiesccscdata' 
        AND TABLE_NAME = 'subjects' 
        AND COLUMN_NAME = 'semester_id' 
        AND REFERENCED_TABLE_NAME = 'semesters'
    ");
    
    if ($fk_subjects_check && $fk_subjects_check->fetch_assoc()['count'] == 0) {
        $add_fk_subjects = $conn->query("ALTER TABLE subjects ADD CONSTRAINT fk_subjects_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON UPDATE CASCADE");
        if ($add_fk_subjects) {
            echo "✅ Foreign key constraint added to subjects table!<br>";
        } else {
            echo "⚠️ Foreign key constraint for subjects might already exist: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ Foreign key constraint already exists for subjects table!<br>";
    }

    // Check and add foreign key for student_subjects table
    $fk_student_subjects_check = $conn->query("
        SELECT COUNT(*) as count 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'aiesccscdata' 
        AND TABLE_NAME = 'student_subjects' 
        AND COLUMN_NAME = 'semester_id' 
        AND REFERENCED_TABLE_NAME = 'semesters'
    ");
    
    if ($fk_student_subjects_check && $fk_student_subjects_check->fetch_assoc()['count'] == 0) {
        $add_fk_student_subjects = $conn->query("ALTER TABLE student_subjects ADD CONSTRAINT fk_student_subjects_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON UPDATE CASCADE");
        if ($add_fk_student_subjects) {
            echo "✅ Foreign key constraint added to student_subjects table!<br>";
        } else {
            echo "⚠️ Foreign key constraint for student_subjects might already exist: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ Foreign key constraint already exists for student_subjects table!<br>";
    }

    // Step 7: Update existing records
    echo "<h2>Step 7: Update Existing Records</h2>";
    $update_attendance = $conn->query("UPDATE attendance SET semester_id = 1 WHERE semester_id IS NULL OR semester_id = 0");
    if ($update_attendance) {
        echo "✅ Updated existing attendance records!<br>";
    } else {
        echo "❌ Error updating attendance records: " . $conn->error . "<br>";
    }

    $update_subjects = $conn->query("UPDATE subjects SET semester_id = 1 WHERE semester_id IS NULL OR semester_id = 0");
    if ($update_subjects) {
        echo "✅ Updated existing subjects records!<br>";
    } else {
        echo "❌ Error updating subjects records: " . $conn->error . "<br>";
    }

    $update_student_subjects = $conn->query("UPDATE student_subjects SET semester_id = 1 WHERE semester_id IS NULL OR semester_id = 0");
    if ($update_student_subjects) {
        echo "✅ Updated existing student_subjects records!<br>";
    } else {
        echo "❌ Error updating student_subjects records: " . $conn->error . "<br>";
    }

    echo "<h2>✅ Complete Database Setup Finished!</h2>";
    echo "<p>All tables should now have proper semester_id columns and foreign key constraints.</p>";

} catch (Exception $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>";
}

$conn->close();
?>
