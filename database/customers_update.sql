-- ---------------------------------------------------------------
-- Phase 2: Customer authentication
--
-- This is a migration, not a full dump. Run it against the SAME
-- database used for hotel_db.sql (default database name: hotel_db).
--
-- In phpMyAdmin: select the hotel_db database > Import > choose this file.
-- Or from the command line:
--   mysql -u root hotel_db < database/customers_update.sql
--
-- Safe to re-run — uses CREATE TABLE IF NOT EXISTS.
--
-- NOTE: This is intentionally a separate table from the existing
-- `users` table (which is for admin/staff logins, type 1=admin,
-- 2=staff). Customer accounts are a different concept, so a new
-- `customers` table is added instead of overloading `users`.
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = active, 0 = disabled',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
