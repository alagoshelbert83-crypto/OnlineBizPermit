-- Add a rating column to the feedback table
ALTER TABLE feedback ADD COLUMN IF NOT EXISTS rating SMALLINT NULL;

-- Optional index to help rating queries
CREATE INDEX IF NOT EXISTS idx_feedback_rating ON feedback(rating);