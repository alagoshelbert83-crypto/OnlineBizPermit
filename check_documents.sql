-- Run this query in your database to see document records
-- This will help us understand why document names aren't showing correctly

-- First, check if document_type column exists
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'documents' 
ORDER BY ordinal_position;

-- Then, see the actual document records
-- Replace 8 with your actual application_id if needed
SELECT 
    id,
    application_id,
    document_name,
    file_path,
    document_type,
    upload_date
FROM documents 
WHERE application_id = 8  -- Change this to your application ID
ORDER BY id;

-- Or see all recent documents
-- SELECT id, application_id, document_name, file_path, document_type, upload_date 
-- FROM documents 
-- ORDER BY id DESC 
-- LIMIT 20;

