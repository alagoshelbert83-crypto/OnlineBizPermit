-- Add 'complete' status to applications_status_check constraint
-- This allows applications to have a 'complete' status when permits are released

-- First, drop the existing constraint
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.table_constraints 
        WHERE constraint_name = 'applications_status_check'
        AND table_name = 'applications'
    ) THEN
        ALTER TABLE applications DROP CONSTRAINT applications_status_check;
    END IF;
END $$;

-- Add the updated constraint with 'complete' included
ALTER TABLE applications 
ADD CONSTRAINT applications_status_check 
CHECK (status IN ('pending', 'approved', 'rejected', 'processing', 'complete'));

-- Verify the constraint was added
SELECT 
    constraint_name,
    check_clause
FROM information_schema.check_constraints
WHERE constraint_name = 'applications_status_check';
