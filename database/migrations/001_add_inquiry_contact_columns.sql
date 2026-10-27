-- =====================================================================
-- KúkiCakes — Database Migration: 001_add_inquiry_contact_columns.sql
-- Adds nullable guest/customer contact details to `inquiry` table.
-- =====================================================================

USE `kukicakes_db`;

ALTER TABLE `inquiry`
    ADD COLUMN `contact_name` VARCHAR(100) NULL DEFAULT NULL AFTER `customer_id`,
    ADD COLUMN `contact_phone` VARCHAR(20) NULL DEFAULT NULL AFTER `contact_name`,
    ADD COLUMN `contact_email` VARCHAR(150) NULL DEFAULT NULL AFTER `contact_phone`;
