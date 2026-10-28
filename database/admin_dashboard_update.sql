-- ---------------------------------------------------------------
-- Phase 4: Admin Dashboard & Management
--
-- This is a migration, not a full dump. Run it against the SAME
-- database used for the other migrations (default name: hotel_db).
--
-- In phpMyAdmin: select the hotel_db database > Import > choose this file.
-- Or from the command line:
--   mysql -u root hotel_db < database/admin_dashboard_update.sql
--
-- Safe to re-run - every statement uses IF NOT EXISTS.
--
-- NOTE on design: everything the dashboard/reports need (room counts,
-- booking counts by status, revenue from `checked.total_price`,
-- customer counts) already exists in the tables from earlier phases
-- and is just queried/aggregated - no new tables needed for those.
--
-- Two genuinely new tables are added because nothing suitable already
-- existed for them:
--   - `payments`: a ledger of amounts actually collected against a
--     booking. `checked.total_price` is what's owed; this table is
--     what's been paid. Kept separate (rather than a single
--     "amount_paid" column) so partial/multiple payments and a
--     payment method/note per entry can be recorded.
--   - `contact_messages`: the public "Contact Us" form has to write
--     somewhere before "Manage contact messages" can mean anything.
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `booking_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` VARCHAR(50) NOT NULL DEFAULT 'cash',
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `received_by` INT(11) NULL DEFAULT NULL COMMENT 'admin users.id who recorded this payment',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(200) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('new','read','replied') NOT NULL DEFAULT 'new',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
