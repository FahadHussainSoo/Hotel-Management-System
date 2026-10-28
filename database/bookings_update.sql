```sql
-- ---------------------------------------------------------------
-- Phase 3: Hotel Booking System
-- InfinityFree-compatible migration
-- ---------------------------------------------------------------

-- Add guests column if it does not already exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'checked'
      AND column_name = 'guests'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `checked` ADD COLUMN `guests` INT(11) NOT NULL DEFAULT 1 AFTER `booked_cid`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add nights column if it does not already exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'checked'
      AND column_name = 'nights'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `checked` ADD COLUMN `nights` INT(11) NOT NULL DEFAULT 1 AFTER `guests`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add total_price column if it does not already exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'checked'
      AND column_name = 'total_price'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `checked` ADD COLUMN `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `nights`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add booking_status column if it does not already exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'checked'
      AND column_name = 'booking_status'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `checked` ADD COLUMN `booking_status` ENUM(''pending'',''approved'',''rejected'',''cancelled'') NOT NULL DEFAULT ''pending'' AFTER `status`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add cancel_reason column if it does not already exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'checked'
      AND column_name = 'cancel_reason'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `checked` ADD COLUMN `cancel_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `booking_status`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Existing demo rows with status 1 or 2 are already approved.
UPDATE `checked`
SET `booking_status` = 'approved'
WHERE `status` IN (1, 2)
  AND `booking_status` = 'pending';
```
