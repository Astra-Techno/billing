-- Add is_super_admin flag to users table
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'users'
    AND COLUMN_NAME  = 'is_super_admin'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0',
  'DO 1'
);
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;
