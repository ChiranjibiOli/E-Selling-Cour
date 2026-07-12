-- Bring an older `coursehub` database up to the minimum schema required by
-- the current application without deleting existing users, courses, orders,
-- payments, or enrollments.
--
-- Target: MariaDB 10.4+ / MySQL-compatible server on port 3307.
-- Back up the database before running this file.

USE coursehub;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------------
-- Core account and course workflow columns
-- -------------------------------------------------------------------------

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS identity_document VARCHAR(255) DEFAULT NULL AFTER profile_image,
    ADD COLUMN IF NOT EXISTS last_login_at DATETIME DEFAULT NULL AFTER status;

ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1)
        AS (CASE WHEN status = 'active' THEN 1 ELSE 0 END) STORED AFTER status,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE courses
    MODIFY COLUMN status ENUM('draft','pending','published','rejected','archived')
        NOT NULL DEFAULT 'draft',
    ADD COLUMN IF NOT EXISTS submitted_at DATETIME DEFAULT NULL AFTER is_featured,
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME DEFAULT NULL AFTER submitted_at,
    ADD COLUMN IF NOT EXISTS reviewed_by BIGINT UNSIGNED DEFAULT NULL AFTER reviewed_at,
    ADD COLUMN IF NOT EXISTS review_note VARCHAR(1000) DEFAULT NULL AFTER reviewed_by;

ALTER TABLE course_sections
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE course_lessons
    MODIFY COLUMN content_type ENUM('text','pdf','word','video','link')
        NOT NULL DEFAULT 'text',
    MODIFY COLUMN content_url VARCHAR(500) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS notification_type VARCHAR(50) NOT NULL DEFAULT 'general' AFTER message,
    ADD COLUMN IF NOT EXISTS read_at DATETIME DEFAULT NULL AFTER is_read;

-- -------------------------------------------------------------------------
-- Order, payment, and enrollment compatibility
-- -------------------------------------------------------------------------

ALTER TABLE orders
    MODIFY COLUMN order_status ENUM('pending','paid','failed','cancelled','refunded')
        NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER final_price;

ALTER TABLE payments
    MODIFY COLUMN payment_status ENUM('pending','paid','failed','rejected','refunded')
        NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE payment_proofs
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER note;

ALTER TABLE enrollments
    MODIFY COLUMN access_type ENUM('lifetime','limited') NOT NULL DEFAULT 'lifetime',
    MODIFY COLUMN status ENUM('active','revoked','refunded') NOT NULL DEFAULT 'active',
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE instructor_bank_details
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER qr_image;

-- -------------------------------------------------------------------------
-- Workflow and finance tables used by the instructor/admin panels
-- These tables intentionally omit cross-table foreign keys so the migration
-- works with both older INT identifiers and newer BIGINT identifiers. The
-- application still validates ownership and state transitions transactionally.
-- -------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS course_change_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    course_id BIGINT UNSIGNED NOT NULL,
    instructor_id BIGINT UNSIGNED NOT NULL,
    change_type VARCHAR(60) NOT NULL DEFAULT 'lesson_update',
    before_snapshot LONGTEXT DEFAULT NULL,
    after_snapshot LONGTEXT NOT NULL,
    previous_status VARCHAR(30) NOT NULL,
    new_status VARCHAR(30) NOT NULL,
    reviewed_by BIGINT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY course_change_course_created_index (course_id, created_at),
    KEY course_change_review_index (reviewed_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY site_settings_key_unique (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS instructor_earnings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    instructor_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    gross_amount DECIMAL(12,2) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    commission_amount DECIMAL(12,2) NOT NULL,
    instructor_amount DECIMAL(12,2) NOT NULL,
    earning_status ENUM('pending','available','withdraw_requested','paid','cancelled','refunded')
        NOT NULL DEFAULT 'available',
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY instructor_earnings_order_item_unique (order_item_id),
    KEY instructor_earnings_status_index (instructor_id, earning_status),
    KEY instructor_earnings_order_payment_index (order_id, payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    instructor_id BIGINT UNSIGNED NOT NULL,
    requested_amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('bank','esewa','khalti') NOT NULL,
    account_name VARCHAR(150) DEFAULT NULL,
    account_number VARCHAR(100) DEFAULT NULL,
    bank_name VARCHAR(150) DEFAULT NULL,
    esewa_number VARCHAR(30) DEFAULT NULL,
    khalti_number VARCHAR(30) DEFAULT NULL,
    request_status ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
    instructor_note VARCHAR(1000) DEFAULT NULL,
    admin_note VARCHAR(1000) DEFAULT NULL,
    processed_by BIGINT UNSIGNED DEFAULT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY withdrawal_requests_status_index (request_status, requested_at),
    KEY withdrawal_requests_instructor_index (instructor_id, request_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS withdrawal_request_earnings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    withdrawal_request_id BIGINT UNSIGNED NOT NULL,
    earning_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY withdrawal_earning_unique (withdrawal_request_id, earning_id),
    UNIQUE KEY withdrawal_earning_single_request_unique (earning_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payouts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    withdrawal_request_id BIGINT UNSIGNED DEFAULT NULL,
    order_id BIGINT UNSIGNED DEFAULT NULL,
    payment_id BIGINT UNSIGNED DEFAULT NULL,
    payout_source ENUM('withdrawal','direct_order') NOT NULL DEFAULT 'withdrawal',
    instructor_id BIGINT UNSIGNED NOT NULL,
    paid_amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('bank','esewa','khalti') NOT NULL,
    transaction_reference VARCHAR(150) NOT NULL,
    proof_image VARCHAR(255) DEFAULT NULL,
    payout_status ENUM('pending','paid','failed') NOT NULL DEFAULT 'paid',
    paid_by BIGINT UNSIGNED DEFAULT NULL,
    admin_note VARCHAR(1000) DEFAULT NULL,
    paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY payouts_instructor_index (instructor_id, paid_at),
    KEY payouts_order_payment_index (order_id, payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

SET FOREIGN_KEY_CHECKS = 1;
