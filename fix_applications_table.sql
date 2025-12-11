-- Fix Applications Table for Neon PostgreSQL
-- Run this in your Neon SQL Editor

-- First, check current structure (this will just show info, won't change anything)
SELECT 
    column_name, 
    data_type, 
    character_maximum_length,
    is_nullable,
    column_default
FROM information_schema.columns 
WHERE table_name = 'applications' 
ORDER BY ordinal_position;

-- Add missing columns if they don't exist (PostgreSQL syntax)
-- This is safe to run multiple times - IF NOT EXISTS prevents errors

-- Ensure all required columns exist
DO $$ 
BEGIN
    -- Add business_address if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'applications' AND column_name = 'business_address'
    ) THEN
        ALTER TABLE applications ADD COLUMN business_address TEXT;
    END IF;

    -- Add type_of_business if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'applications' AND column_name = 'type_of_business'
    ) THEN
        ALTER TABLE applications ADD COLUMN type_of_business VARCHAR(255);
    END IF;

    -- Add form_details if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'applications' AND column_name = 'form_details'
    ) THEN
        ALTER TABLE applications ADD COLUMN form_details TEXT;
    END IF;

    -- Add submitted_at if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'applications' AND column_name = 'submitted_at'
    ) THEN
        ALTER TABLE applications ADD COLUMN submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
    END IF;

    -- Add status if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'applications' AND column_name = 'status'
    ) THEN
        ALTER TABLE applications ADD COLUMN status VARCHAR(20) DEFAULT 'pending';
    END IF;

    -- Add business_name if missing (should always exist, but just in case)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'applications' AND column_name = 'business_name'
    ) THEN
        ALTER TABLE applications ADD COLUMN business_name VARCHAR(255) NOT NULL DEFAULT '';
    END IF;

    -- Add user_id foreign key if missing
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'applications' AND column_name = 'user_id'
    ) THEN
        ALTER TABLE applications ADD COLUMN user_id INTEGER;
        -- Add foreign key constraint
        ALTER TABLE applications 
        ADD CONSTRAINT fk_applications_user_id 
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
    END IF;

END $$;

-- Add status constraint if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints 
        WHERE constraint_name = 'applications_status_check'
        AND table_name = 'applications'
    ) THEN
        ALTER TABLE applications 
        ADD CONSTRAINT applications_status_check 
        CHECK (status IN ('pending', 'approved', 'rejected', 'processing', 'complete'));
    END IF;
END $$;

-- Verify the structure after changes
SELECT 
    column_name, 
    data_type, 
    character_maximum_length,
    is_nullable,
    column_default
FROM information_schema.columns 
WHERE table_name = 'applications' 
ORDER BY ordinal_position;

