-- Add business_id column to applications table
-- This migration adds a nullable Business ID and a supporting index.

ALTER TABLE applications ADD COLUMN IF NOT EXISTS business_id VARCHAR(100);

-- Create an index to speed up lookups by business_id
CREATE INDEX IF NOT EXISTS idx_applications_business_id ON applications (business_id);
