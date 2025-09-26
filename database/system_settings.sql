-- System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
-- General Settings
('company_name', 'Water Purifier ERP', 'Company name'),
('company_address', '', 'Company address'),
('company_phone', '', 'Company phone number'),
('company_email', '', 'Company email address'),
('company_website', '', 'Company website URL'),
('timezone', 'Asia/Kolkata', 'System timezone'),
('currency', 'INR', 'Default currency'),
('tax_rate', '18', 'Default tax rate percentage'),
('invoice_prefix', 'INV', 'Invoice number prefix'),
('invoice_number_start', '1001', 'Starting invoice number'),

-- Email Settings
('smtp_host', '', 'SMTP server host'),
('smtp_port', '587', 'SMTP server port'),
('smtp_username', '', 'SMTP username'),
('smtp_password', '', 'SMTP password'),
('smtp_encryption', 'tls', 'SMTP encryption type'),
('from_email', '', 'Default from email address'),
('from_name', 'Water Purifier ERP', 'Default from name'),

-- SMS Settings
('sms_provider', 'twilio', 'SMS service provider'),
('sms_api_key', '', 'SMS API key'),
('sms_api_secret', '', 'SMS API secret'),
('sms_sender_id', '', 'SMS sender ID'),

-- Firebase Settings
('firebase_api_key', '', 'Firebase API key'),
('firebase_auth_domain', '', 'Firebase auth domain'),
('firebase_database_url', '', 'Firebase database URL'),
('firebase_project_id', '', 'Firebase project ID'),
('firebase_storage_bucket', '', 'Firebase storage bucket'),
('firebase_messaging_sender_id', '', 'Firebase messaging sender ID'),
('firebase_app_id', '', 'Firebase app ID'),

-- Additional Settings
('maintenance_mode', '0', 'Maintenance mode (0=off, 1=on)'),
('max_login_attempts', '5', 'Maximum login attempts before lockout'),
('session_timeout', '3600', 'Session timeout in seconds'),
('password_min_length', '8', 'Minimum password length'),
('require_2fa', '0', 'Require two-factor authentication (0=off, 1=on)'),
('backup_frequency', 'daily', 'Backup frequency (daily, weekly, monthly)'),
('log_retention_days', '90', 'Log retention period in days'),
('notification_email', '', 'Notification email address'),
('support_email', '', 'Support email address'),
('support_phone', '', 'Support phone number')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);