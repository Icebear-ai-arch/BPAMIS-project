-- Migration: create notifications_trash table
-- Creates a trash table with the same structure as `notifications`
-- and adds metadata columns for when and who trashed the row.

CREATE TABLE IF NOT EXISTS `notifications_trash` LIKE `notifications`;

-- Add trashed metadata if not already present
ALTER TABLE `notifications_trash`
    ADD COLUMN IF NOT EXISTS `trashed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `created_at`,
    ADD COLUMN IF NOT EXISTS `trashed_by` INT NULL AFTER `trashed_at`;

-- Index trashed_by for quick lookups (optional)
CREATE INDEX IF NOT EXISTS `idx_notifications_trash_trashed_by` ON `notifications_trash` (`trashed_by`);

-- Note: Run this file in your MySQL client (phpMyAdmin or CLI) once.
