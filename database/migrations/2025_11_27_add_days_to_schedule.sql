-- Migration: Add 'days' column to 'schedule'
-- Run this against your database (e.g., via phpMyAdmin or mysql client)

ALTER TABLE `schedule`
  ADD COLUMN `days` VARCHAR(64) NULL AFTER `time_end`;

-- Optional helpful index if you intend to filter by days patterns frequently
-- CREATE INDEX `idx_schedule_days` ON `schedule` (`days`);
