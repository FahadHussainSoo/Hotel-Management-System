```sql
-- ---------------------------------------------------------------
-- Phase 6: Staff Management
-- InfinityFree-compatible migration
-- ---------------------------------------------------------------

-- Add status column if it does not already exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'status'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = active, 0 = disabled'' AFTER `type`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add created_by column if it does not already exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'created_by'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `created_by` INT(11) NULL DEFAULT NULL COMMENT ''users.id of the admin who created this account'' AFTER `status`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add created_at column if it does not already exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'created_at'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `created_by`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Make sure existing users are active
UPDATE `users`
SET `status` = 1
WHERE `status` IS NULL;
```
