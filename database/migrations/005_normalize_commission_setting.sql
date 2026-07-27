USE coursehub;

INSERT INTO site_settings (setting_key, setting_value)
VALUES ('platform_commission_rate', '20')
ON DUPLICATE KEY UPDATE
    setting_key = VALUES(setting_key);

INSERT INTO site_settings (setting_key, setting_value)
SELECT 'platform_commission_rate', setting_value
FROM site_settings
WHERE setting_key = 'admin_commission_rate'
LIMIT 1
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value);

DELETE FROM site_settings
WHERE setting_key = 'admin_commission_rate';
