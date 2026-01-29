-- Migration: Make subject_id and schedule_id nullable in admission so enrollment can be created without schedule/subject initially
-- Run this in your database (phpMyAdmin or mysql client)

-- If foreign keys exist, no need to drop them to change NULLability, but some MySQL versions require dropping and re-adding. Try the simple ALTER first.
ALTER TABLE `admission`
  MODIFY `subject_id` INT NULL,
  MODIFY `schedule_id` INT NULL;

-- If the above fails due to constraints, use this fallback (uncomment and run):
-- SET FOREIGN_KEY_CHECKS=0;
-- ALTER TABLE `admission` MODIFY `subject_id` INT NULL, MODIFY `schedule_id` INT NULL;
-- SET FOREIGN_KEY_CHECKS=1;
