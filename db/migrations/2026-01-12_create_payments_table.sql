-- Create payments table to record payment receipts and verification

CREATE TABLE IF NOT EXISTS payments (
    id SERIAL PRIMARY KEY,
    application_id INTEGER NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    amount NUMERIC(12,2) NULL,
    or_number VARCHAR(100) NULL,
    method VARCHAR(50) NULL,
    uploaded_by INTEGER NULL REFERENCES users(id),
    document_id INTEGER NULL REFERENCES documents(id) ON DELETE SET NULL,
    metadata JSONB NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending / verified / rejected
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP WITH TIME ZONE NULL,
    verified_by INTEGER NULL REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_payments_application_id ON payments(application_id);
