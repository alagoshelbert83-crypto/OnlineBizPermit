-- Add document_type column to documents table
-- Run this in your Neon SQL Editor

ALTER TABLE documents 
ADD COLUMN IF NOT EXISTS document_type VARCHAR(100);

-- Add index for faster lookups
CREATE INDEX IF NOT EXISTS idx_documents_document_type ON documents(document_type);

-- Update existing documents with default type if needed (optional)
-- UPDATE documents SET document_type = 'Other' WHERE document_type IS NULL;

