-- SME 2027 Events Calendar migration
-- Run this once in phpMyAdmin before uploading/testing the events patch.

SET @db_name = DATABASE();

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE content_posts ADD COLUMN event_date DATETIME NULL AFTER published_at',
    'SELECT "event_date already exists" AS message'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'content_posts'
    AND COLUMN_NAME = 'event_date'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE content_posts ADD COLUMN event_location VARCHAR(255) NULL AFTER event_date',
    'SELECT "event_location already exists" AS message'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'content_posts'
    AND COLUMN_NAME = 'event_location'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE content_posts ADD INDEX idx_content_events (type, is_published, event_date)',
    'SELECT "idx_content_events already exists" AS message'
  )
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'content_posts'
    AND INDEX_NAME = 'idx_content_events'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
