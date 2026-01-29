-- Clean up students table (remove header row)
DELETE FROM students WHERE student_id = 'student_id';

-- Add status column to academic_year if it doesn't exist
ALTER TABLE academic_year ADD COLUMN IF NOT EXISTS status ENUM('Active', 'Inactive') DEFAULT 'Inactive';

-- Add status column to semester if it doesn't exist
ALTER TABLE semester ADD COLUMN IF NOT EXISTS status ENUM('Active', 'Inactive') DEFAULT 'Inactive';

-- Reset any multiple active statuses (just in case)
UPDATE academic_year SET status = 'Inactive';
UPDATE semester SET status = 'Inactive';

-- Set the most recent entries as Active by default
UPDATE academic_year SET status = 'Active' WHERE ay_id = (SELECT MAX(ay_id) FROM (SELECT ay_id FROM academic_year) as t);
UPDATE semester SET status = 'Active' WHERE semester_id = (SELECT MAX(semester_id) FROM (SELECT semester_id FROM semester) as t);

-- Clean up student IDs (Trim whitespace)
UPDATE students SET student_id = TRIM(student_id), rfid_number = TRIM(rfid_number);
UPDATE admission SET student_id = TRIM(student_id);
