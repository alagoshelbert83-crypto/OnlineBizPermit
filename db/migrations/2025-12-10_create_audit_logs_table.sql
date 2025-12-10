-- Create audit_logs table for tracking user activities
CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    user_role VARCHAR(20) NOT NULL, -- 'admin', 'staff', 'applicant', 'guest'
    action VARCHAR(100) NOT NULL, -- Action performed (e.g., 'login', 'view_application', 'send_message')
    description TEXT, -- Detailed description of the action
    ip_address INET, -- User's IP address
    user_agent TEXT, -- Browser user agent
    session_id VARCHAR(255), -- PHP session ID
    metadata JSONB, -- Additional structured data (e.g., {'application_id': 123, 'old_status': 'pending', 'new_status': 'approved'})
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_role ON audit_logs(user_role);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_logs_ip_address ON audit_logs(ip_address);

-- Create a function to clean old audit logs (optional - keeps last 6 months)
CREATE OR REPLACE FUNCTION clean_old_audit_logs() RETURNS void AS $$
BEGIN
    DELETE FROM audit_logs WHERE created_at < CURRENT_TIMESTAMP - INTERVAL '6 months';
END;
$$ LANGUAGE plpgsql;

-- Grant permissions
GRANT SELECT, INSERT ON audit_logs TO web_user;
GRANT USAGE ON SEQUENCE audit_logs_id_seq TO web_user;
