-- Migration: Add only missing columns (tables already exist)
-- Run in Neon SQL Editor - paste each statement and Execute (NOT Explain)

ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture_path VARCHAR(255);

ALTER TABLE users ADD COLUMN IF NOT EXISTS email_notifications_enabled BOOLEAN DEFAULT TRUE;

ALTER TABLE applications ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW();

ALTER TABLE applications ADD COLUMN IF NOT EXISTS permit_released_at TIMESTAMP WITH TIME ZONE;

ALTER TABLE live_chats ADD COLUMN IF NOT EXISTS staff_id INTEGER REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE live_chats ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP WITH TIME ZONE;

ALTER TABLE live_chats ADD COLUMN IF NOT EXISTS user_is_typing BOOLEAN DEFAULT FALSE;

ALTER TABLE live_chats ADD COLUMN IF NOT EXISTS staff_is_typing BOOLEAN DEFAULT FALSE;
