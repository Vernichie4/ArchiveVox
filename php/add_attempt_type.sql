-- Add attempt_number and attempt_type fields to track practice vs final attempts
-- Run this in MySQL Workbench to update your database schema
-- This script can be run multiple times safely

-- Add attempt_number to quiz_attempt table (for tracking which attempt this is)
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'quiz_attempt'
  AND COLUMN_NAME = 'attempt_number';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE quiz_attempt ADD COLUMN attempt_number INT DEFAULT 1 COMMENT ''Attempt number (1-3, 3 is final)''',
    'SELECT ''Column attempt_number already exists in quiz_attempt'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add attempt_type to quiz_attempt table
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'quiz_attempt'
  AND COLUMN_NAME = 'attempt_type';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE quiz_attempt ADD COLUMN attempt_type ENUM(''practice'', ''final'') DEFAULT ''practice'' COMMENT ''Type of attempt: practice (1st/2nd) or final (3rd)''',
    'SELECT ''Column attempt_type already exists in quiz_attempt'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add attempt_type to reading_activity table
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'reading_activity'
  AND COLUMN_NAME = 'attempt_type';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE reading_activity ADD COLUMN attempt_type ENUM(''practice'', ''final'') DEFAULT ''practice'' COMMENT ''Type of attempt: practice (1st/2nd) or final (3rd)''',
    'SELECT ''Column attempt_type already exists in reading_activity'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Initialize attempt_number for existing quiz_attempt records
-- For existing records, we'll set attempt_number to 1 by default
-- New attempts will be numbered correctly by the application logic
UPDATE quiz_attempt SET attempt_number = 1 WHERE attempt_id > 0 AND (attempt_number IS NULL OR attempt_number = 0);

-- Reset reading_activity attempt_number to 1 for all records
-- This fixes the issue where old records have high attempt numbers
UPDATE reading_activity SET attempt_number = 1 WHERE activity_id > 0;

-- Update existing records to set attempt_type based on attempt_number
-- attempt 1 and 2 = practice, attempt 3 = final
UPDATE quiz_attempt SET attempt_type =
    CASE
        WHEN attempt_number = 3 THEN 'final'
        ELSE 'practice'
    END
WHERE attempt_id > 0;

UPDATE reading_activity SET attempt_type =
    CASE
        WHEN attempt_number = 3 THEN 'final'
        ELSE 'practice'
    END
WHERE activity_id > 0;
