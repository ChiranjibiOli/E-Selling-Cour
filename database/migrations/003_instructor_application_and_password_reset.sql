USE coursehub;

ALTER TABLE instructor_applications
    ADD COLUMN professional_headline VARCHAR(160) DEFAULT NULL AFTER application_note,
    ADD COLUMN expertise VARCHAR(1000) DEFAULT NULL AFTER professional_headline,
    ADD COLUMN teaching_experience VARCHAR(2000) DEFAULT NULL AFTER expertise,
    ADD COLUMN social_profile_url VARCHAR(500) DEFAULT NULL AFTER teaching_experience,
    ADD COLUMN course_subjects VARCHAR(1000) DEFAULT NULL AFTER social_profile_url,
    ADD COLUMN agreed_rules_at DATETIME DEFAULT NULL AFTER course_subjects;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    requested_ip VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY password_reset_token_hash_unique (token_hash),
    KEY password_reset_user_expiry_index (user_id, expires_at, used_at),
    CONSTRAINT password_reset_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;