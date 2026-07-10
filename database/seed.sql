USE coursehub;

INSERT INTO categories (name, slug, status) VALUES
    ('Web Development', 'web-development', 'active'),
    ('Programming', 'programming', 'active'),
    ('Cybersecurity', 'cybersecurity', 'active'),
    ('Networking', 'networking', 'active'),
    ('Design', 'design', 'active'),
    ('Business', 'business', 'active')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = VALUES(status);

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('site_name', 'CourseHub'),
    ('site_email', 'support@example.com'),
    ('site_phone', '+977-9800000000'),
    ('site_address', 'Kathmandu, Nepal'),
    ('admin_commission_rate', '20'),
    ('esewa_id', ''),
    ('khalti_id', ''),
    ('bank_name', ''),
    ('bank_account_name', ''),
    ('bank_account_number', ''),
    ('payment_instructions', 'Pay with one of the listed methods, enter the transaction reference, and upload a clear proof. Access is activated only after admin verification.'),
    ('terms_url', ''),
    ('privacy_url', '')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value);
