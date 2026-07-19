USE coursehub;

CREATE TABLE IF NOT EXISTS instructor_applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instructor_id BIGINT UNSIGNED NOT NULL,
    application_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    application_note TEXT NOT NULL,
    professional_headline VARCHAR(160) DEFAULT NULL,
    expertise VARCHAR(1000) DEFAULT NULL,
    teaching_experience VARCHAR(2000) DEFAULT NULL,
    social_profile_url VARCHAR(500) DEFAULT NULL,
    course_subjects VARCHAR(1000) DEFAULT NULL,
    agreed_rules_at DATETIME DEFAULT NULL,
    review_note VARCHAR(1000) DEFAULT NULL,
    reviewed_by BIGINT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY instructor_applications_instructor_unique (instructor_id),
    KEY instructor_applications_status_index (application_status, created_at),
    CONSTRAINT instructor_applications_instructor_fk
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT instructor_applications_reviewer_fk
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'instructor_applications' AND column_name = 'professional_headline'),
    'SELECT 1',
    'ALTER TABLE instructor_applications ADD COLUMN professional_headline VARCHAR(160) DEFAULT NULL AFTER application_note'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'instructor_applications' AND column_name = 'expertise'),
    'SELECT 1',
    'ALTER TABLE instructor_applications ADD COLUMN expertise VARCHAR(1000) DEFAULT NULL AFTER professional_headline'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'instructor_applications' AND column_name = 'teaching_experience'),
    'SELECT 1',
    'ALTER TABLE instructor_applications ADD COLUMN teaching_experience VARCHAR(2000) DEFAULT NULL AFTER expertise'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'instructor_applications' AND column_name = 'social_profile_url'),
    'SELECT 1',
    'ALTER TABLE instructor_applications ADD COLUMN social_profile_url VARCHAR(500) DEFAULT NULL AFTER teaching_experience'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'instructor_applications' AND column_name = 'course_subjects'),
    'SELECT 1',
    'ALTER TABLE instructor_applications ADD COLUMN course_subjects VARCHAR(1000) DEFAULT NULL AFTER social_profile_url'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'instructor_applications' AND column_name = 'agreed_rules_at'),
    'SELECT 1',
    'ALTER TABLE instructor_applications ADD COLUMN agreed_rules_at DATETIME DEFAULT NULL AFTER course_subjects'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

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
