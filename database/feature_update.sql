-- SME 2027 pending-feature update migration
-- Run this once in phpMyAdmin / MySQL before uploading the new PHP files.

-- 1) Content media support.
-- If this column already exists, phpMyAdmin may show a duplicate-column error; that is safe to ignore.
ALTER TABLE content_posts ADD COLUMN media_json LONGTEXT NULL AFTER featured_image;

-- 2) Notification log table for email/SMS attempts.
CREATE TABLE IF NOT EXISTS notification_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  channel VARCHAR(30) NOT NULL,
  recipient VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NULL,
  message LONGTEXT NOT NULL,
  reference VARCHAR(80) NULL,
  was_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_channel (channel),
  INDEX idx_reference (reference),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Allowed user roles already use the existing users.role column:
-- super_admin, content_editor, application_reviewer, community_coordinator, data_viewer
-- Example role changes:
-- UPDATE users SET role = 'super_admin' WHERE email = 'admin@example.com';
-- UPDATE users SET role = 'content_editor' WHERE email = 'editor@example.com';
