```sql
-- ---------------------------------------------------------------
-- Phase: Room Management & Availability
-- InfinityFree-compatible migration
-- ---------------------------------------------------------------

-- Add capacity column if it does not already exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'room_categories'
      AND column_name = 'capacity'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `room_categories` ADD COLUMN `capacity` INT(11) NOT NULL DEFAULT 2 COMMENT ''Max guests for this room type'' AFTER `price`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- Add description column if it does not already exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'room_categories'
      AND column_name = 'description'
);

SET @sql = IF(
    @column_exists = 0,
    'ALTER TABLE `room_categories` ADD COLUMN `description` TEXT NULL AFTER `capacity`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- ---------------------------------------------------------------
-- Room category gallery images
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `room_category_images` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `category_id` INT(11) NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------
-- Amenities
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `amenities` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------
-- Room category / amenities relationship
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `room_category_amenities` (
    `category_id` INT(11) NOT NULL,
    `amenity_id` INT(11) NOT NULL,
    PRIMARY KEY (`category_id`, `amenity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------
-- Default amenities
-- ---------------------------------------------------------------

INSERT IGNORE INTO `amenities` (`name`) VALUES
('Free Wi-Fi'),
('Air Conditioning'),
('Flat-screen TV'),
('Mini Bar'),
('Room Service'),
('Breakfast Included'),
('Balcony'),
('Sea View'),
('Bathtub'),
('Hair Dryer'),
('Safe Deposit Box'),
('Coffee Maker');


-- ---------------------------------------------------------------
-- Existing room types
-- ---------------------------------------------------------------

UPDATE `room_categories`
SET `capacity` = 2
WHERE `capacity` = 0;
```
