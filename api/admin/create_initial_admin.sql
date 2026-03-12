SET @role_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'role'
);

SET @role_column_sql := IF(
    @role_column_exists = 0,
    "ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') NOT NULL DEFAULT 'user'",
    "SELECT 1"
);

PREPARE role_stmt FROM @role_column_sql;
EXECUTE role_stmt;
DEALLOCATE PREPARE role_stmt;

CREATE TABLE IF NOT EXISTS verification_requests (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id int(11) NOT NULL,
  document_image longblob NOT NULL,
  document_mime varchar(100) NOT NULL,
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  admin_note text DEFAULT NULL,
  reviewed_by int(11) DEFAULT NULL,
  reviewed_at timestamp NULL DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);
