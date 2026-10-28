```sql
-- ---------------------------------------------------------------
-- Phase 5: Payment & Booking Confirmation
-- InfinityFree-compatible migration
-- ---------------------------------------------------------------

-- Add status column
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND column_name = 'status'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `payments` ADD COLUMN `status` ENUM(''pending'',''paid'',''failed'',''refunded'') NOT NULL DEFAULT ''pending'' AFTER `method`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add reference_no column
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND column_name = 'reference_no'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `payments` ADD COLUMN `reference_no` VARCHAR(100) NULL DEFAULT NULL AFTER `status`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add payment_date column
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND column_name = 'payment_date'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `payments` ADD COLUMN `payment_date` DATETIME NULL DEFAULT NULL AFTER `reference_no`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add gateway column
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND column_name = 'gateway'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `payments` ADD COLUMN `gateway` VARCHAR(30) NOT NULL DEFAULT ''manual'' AFTER `payment_date`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add gateway_txn_id column
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND column_name = 'gateway_txn_id'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `payments` ADD COLUMN `gateway_txn_id` VARCHAR(191) NULL DEFAULT NULL AFTER `gateway`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add verified_by column
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND column_name = 'verified_by'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `payments` ADD COLUMN `verified_by` INT(11) NULL DEFAULT NULL COMMENT ''admin users.id who last verified this payment status'' AFTER `received_by`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add verified_at column
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND column_name = 'verified_at'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `payments` ADD COLUMN `verified_at` DATETIME NULL DEFAULT NULL AFTER `verified_by`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add status index if it does not already exist
SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'payments'
      AND index_name = 'status'
);

SET @sql = IF(
    @index_exists = 0,
    'CREATE INDEX `status` ON `payments` (`status`)',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Mark old payment records as paid.
UPDATE `payments`
SET
    `status` = 'paid',
    `payment_date` = `created_at`
WHERE `created_at` < '2026-08-21 00:00:00'
  AND `payment_date` IS NULL;
```
