-- Add an auto-incrementing primary key to the documents table
-- This is crucial for proper table function and to prevent transaction errors.
ALTER TABLE documents ADD COLUMN IF NOT EXISTS id SERIAL PRIMARY KEY;

-- Add an index on application_id for faster lookups of documents related to an application.
CREATE INDEX IF NOT EXISTS idx_documents_application_id ON documents(application_id);

-- Add a column to track when a document was uploaded.
ALTER TABLE documents ADD COLUMN IF NOT EXISTS upload_date TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP;