USE coursehub;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'email_verified_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN email_verified_at DATETIME DEFAULT NULL AFTER email'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

UPDATE users
SET email_verified_at = COALESCE(email_verified_at, created_at)
WHERE role = 'student' AND status = 'active';

CREATE TABLE IF NOT EXISTS email_verification_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    purpose ENUM('student_registration', 'student_password_reset') NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    requested_ip VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY email_code_user_purpose_index (user_id, purpose, expires_at, used_at),
    CONSTRAINT email_code_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
