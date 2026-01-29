-- Migration: Add lecture/lab/total units to 'subject' table (normalized)
-- Run this against your database (e.g., via phpMyAdmin or mysql client)

ALTER TABLE `subject`
  ADD COLUMN `units_lec` DECIMAL(4,2) NULL AFTER `subject_name`,
  ADD COLUMN `units_lab` DECIMAL(4,2) NULL AFTER `units_lec`,
  ADD COLUMN `units_total` DECIMAL(4,2) NULL AFTER `units_lab`;

-- Optional: compute total for existing rows if lec/lab populated later
-- UPDATE `subject` SET units_total = COALESCE(units_lec,0) + COALESCE(units_lab,0);
