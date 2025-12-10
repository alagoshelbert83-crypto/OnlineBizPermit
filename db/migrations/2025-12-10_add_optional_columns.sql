-- Migration: add optional columns to avoid SQLSTATE[42703] undefined column errors
-- Date: 2025-12-10
-- This migration is written for PostgreSQL (preferred in this repo). Comments include a MySQL variant.

-- NOTE: BACKUP your database before running migrations.

-- Add profile_picture_path to users
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS profile_picture_path VARCHAR(255);

-- Add email_notifications_enabled to users (boolean; default true)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email_notifications_enabled BOOLEAN DEFAULT TRUE;

-- Add updated_at to applications (timestamp with timezone)
ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW();

-- Add permit_released_at to applications (nullable timestamp with timezone)
ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS permit_released_at TIMESTAMP WITH TIME ZONE;

-- Add staff_id to live_chats (nullable integer, references users.id)
ALTER TABLE live_chats
    ADD COLUMN IF NOT EXISTS staff_id INTEGER REFERENCES users(id) ON DELETE SET NULL;

-- Add closed_at to live_chats (nullable timestamp)
ALTER TABLE live_chats
    ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP WITH TIME ZONE;

-- Add user_is_typing and staff_is_typing to live_chats (boolean flags for typing indicators)
ALTER TABLE live_chats
    ADD COLUMN IF NOT EXISTS user_is_typing BOOLEAN DEFAULT FALSE;

ALTER TABLE live_chats
    ADD COLUMN IF NOT EXISTS staff_is_typing BOOLEAN DEFAULT FALSE;

-- Optional: populate updated_at where NULL using submitted_at (if that column exists)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='applications' AND column_name='submitted_at') THEN
        UPDATE applications SET updated_at = submitted_at WHERE updated_at IS NULL AND submitted_at IS NOT NULL;
    END IF;
END$$;

-- MySQL-compatible notes (run only if your DB is MySQL/MariaDB):
-- ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `profile_picture_path` VARCHAR(255);
-- ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email_notifications_enabled` TINYINT(1) DEFAULT 1;
-- ALTER TABLE `applications` ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
-- ALTER TABLE `applications` ADD COLUMN IF NOT EXISTS `permit_released_at` TIMESTAMP NULL;
-- ALTER TABLE `live_chats` ADD COLUMN IF NOT EXISTS `staff_id` INT NULL REFERENCES users(id) ON DELETE SET NULL;
-- ALTER TABLE `live_chats` ADD COLUMN IF NOT EXISTS `closed_at` TIMESTAMP NULL;
-- ALTER TABLE `live_chats` ADD COLUMN IF NOT EXISTS `user_is_typing` TINYINT(1) DEFAULT 0;
-- ALTER TABLE `live_chats` ADD COLUMN IF NOT EXISTS `staff_is_typing` TINYINT(1) DEFAULT 0;

-- End of migration
