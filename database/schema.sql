CREATE DATABASE IF NOT EXISTS coursehub
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE coursehub;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS unsubscribe_requests;
DROP TABLE IF EXISTS payouts;
DROP TABLE IF EXISTS withdrawal_request_earnings;
DROP TABLE IF EXISTS withdrawal_requests;
DROP TABLE IF EXISTS instructor_earnings;
DROP TABLE IF EXISTS instructor_bank_details;
DROP TABLE IF EXISTS site_settings;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS payment_proofs;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS coupon_courses;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS course_change_logs;
DROP TABLE IF EXISTS course_lessons;
DROP TABLE IF EXISTS course_sections;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS instructor_applications;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    identity_document VARCHAR(255) DEFAULT NULL,
    role ENUM('student', 'instructor', 'admin') NOT NULL DEFAULT 'student',
    status ENUM('inactive', 'active', 'blocked') NOT NULL DEFAULT 'active',
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY users_email_unique (email),
    KEY users_role_status_index (role, status)
) ENGINE=InnoDB;

CREATE TABLE instructor_applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instructor_id BIGINT UNSIGNED NOT NULL,
    application_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    application_note TEXT NOT NULL,
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

CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY categories_name_unique (name),
    UNIQUE KEY categories_slug_unique (slug)
) ENGINE=InnoDB;

CREATE TABLE courses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instructor_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED DEFAULT NULL,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    short_description VARCHAR(500) NOT NULL,
    full_description MEDIUMTEXT NOT NULL,
    thumbnail VARCHAR(255) DEFAULT NULL,
    price DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
    level ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'beginner',
    language VARCHAR(60) NOT NULL DEFAULT 'English',
    duration VARCHAR(80) DEFAULT NULL,
    status ENUM('draft', 'pending', 'published', 'rejected', 'archived') NOT NULL DEFAULT 'draft',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    submitted_at DATETIME DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    reviewed_by BIGINT UNSIGNED DEFAULT NULL,
    review_note VARCHAR(1000) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY courses_slug_unique (slug),
    KEY courses_status_featured_index (status, is_featured),
    KEY courses_instructor_index (instructor_id),
    CONSTRAINT courses_instructor_fk
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT courses_category_fk
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT courses_reviewer_fk
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE course_sections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY sections_course_order_index (course_id, sort_order),
    CONSTRAINT sections_course_fk
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE course_lessons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    content_type ENUM('text', 'pdf', 'word', 'video', 'link') NOT NULL DEFAULT 'text',
    content_url VARCHAR(500) DEFAULT NULL,
    content_text MEDIUMTEXT DEFAULT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_preview TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY lessons_section_order_index (section_id, sort_order),
    CONSTRAINT lessons_section_fk
        FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE course_change_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    instructor_id BIGINT UNSIGNED NOT NULL,
    change_type VARCHAR(60) NOT NULL DEFAULT 'lesson_update',
    before_snapshot JSON DEFAULT NULL,
    after_snapshot JSON NOT NULL,
    previous_status VARCHAR(30) NOT NULL,
    new_status VARCHAR(30) NOT NULL,
    reviewed_by BIGINT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY course_change_course_created_index (course_id, created_at),
    KEY course_change_review_index (reviewed_at, created_at),
    CONSTRAINT course_change_course_fk
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT course_change_instructor_fk
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT course_change_reviewer_fk
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cart (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY cart_student_course_unique (student_id, course_id),
    CONSTRAINT cart_student_fk
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT cart_course_fk
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE coupons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    discount_type ENUM('fixed', 'percent') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(12,2) UNSIGNED NOT NULL,
    min_order_amount DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
    max_discount DECIMAL(12,2) UNSIGNED DEFAULT NULL,
    usage_limit INT UNSIGNED DEFAULT NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    valid_from DATETIME DEFAULT NULL,
    valid_until DATETIME DEFAULT NULL,
    status ENUM('active', 'inactive', 'expired') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY coupons_code_unique (code),
    CONSTRAINT coupons_creator_fk
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE coupon_courses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coupon_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY coupon_courses_unique (coupon_id, course_id),
    CONSTRAINT coupon_courses_coupon_fk
        FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    CONSTRAINT coupon_courses_course_fk
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    coupon_id BIGINT UNSIGNED DEFAULT NULL,
    original_amount DECIMAL(12,2) UNSIGNED NOT NULL,
    discount_amount DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
    final_amount DECIMAL(12,2) UNSIGNED NOT NULL,
    order_status ENUM('pending', 'paid', 'failed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY orders_student_status_index (student_id, order_status),
    CONSTRAINT orders_student_fk
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT orders_coupon_fk
        FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    instructor_id BIGINT UNSIGNED NOT NULL,
    course_price DECIMAL(12,2) UNSIGNED NOT NULL,
    discount_amount DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0,
    final_price DECIMAL(12,2) UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY order_items_order_course_unique (order_id, course_id),
    KEY order_items_instructor_index (instructor_id),
    CONSTRAINT order_items_order_fk
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT order_items_course_fk
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
    CONSTRAINT order_items_instructor_fk
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    payment_method VARCHAR(30) NOT NULL,
    payment_type VARCHAR(30) NOT NULL DEFAULT 'manual',
    transaction_id VARCHAR(150) NOT NULL,
    paid_amount DECIMAL(12,2) UNSIGNED NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'rejected', 'refunded') NOT NULL DEFAULT 'pending',
    verified_by BIGINT UNSIGNED DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY payments_order_unique (order_id),
    UNIQUE KEY payments_transaction_unique (transaction_id),
    KEY payments_status_index (payment_status),
    CONSTRAINT payments_order_fk
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT payments_student_fk
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT payments_verifier_fk
        FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE payment_proofs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    proof_image VARCHAR(255) NOT NULL,
    note VARCHAR(1000) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY payment_proofs_payment_unique (payment_id),
    CONSTRAINT payment_proofs_payment_fk
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    access_type ENUM('lifetime', 'limited') NOT NULL DEFAULT 'lifetime',
    status ENUM('active', 'revoked', 'refunded') NOT NULL DEFAULT 'active',
    granted_by BIGINT UNSIGNED DEFAULT NULL,
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_by_admin BIGINT UNSIGNED DEFAULT NULL,
    revoked_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY enrollments_student_course_unique (student_id, course_id),
    KEY enrollments_course_status_index (course_id, status),
    CONSTRAINT enrollments_student_fk
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT enrollments_course_fk
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
    CONSTRAINT enrollments_order_fk
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    CONSTRAINT enrollments_payment_fk
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE RESTRICT,
    CONSTRAINT enrollments_granter_fk
        FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT enrollments_revoker_fk
        FOREIGN KEY (revoked_by_admin) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    review_text TEXT DEFAULT NULL,
    status ENUM('visible', 'hidden') NOT NULL DEFAULT 'visible',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY reviews_course_student_unique (course_id, student_id),
    KEY reviews_course_status_index (course_id, status),
    CONSTRAINT reviews_course_fk
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT reviews_student_fk
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE unsubscribe_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    reason TEXT DEFAULT NULL,
    request_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deadline_at DATETIME NOT NULL,
    processed_by BIGINT UNSIGNED DEFAULT NULL,
    processed_at DATETIME DEFAULT NULL,
    KEY unsubscribe_status_index (request_status, deadline_at),
    CONSTRAINT unsubscribe_enrollment_fk
        FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
    CONSTRAINT unsubscribe_student_fk
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT unsubscribe_processor_fk
        FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE site_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_settings_key_unique (setting_key)
) ENGINE=InnoDB;

CREATE TABLE instructor_bank_details (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instructor_id BIGINT UNSIGNED NOT NULL,
    bank_name VARCHAR(150) DEFAULT NULL,
    account_name VARCHAR(150) DEFAULT NULL,
    account_number VARCHAR(100) DEFAULT NULL,
    branch_name VARCHAR(150) DEFAULT NULL,
    esewa_number VARCHAR(30) DEFAULT NULL,
    khalti_number VARCHAR(30) DEFAULT NULL,
    qr_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY instructor_bank_details_instructor_unique (instructor_id),
    CONSTRAINT instructor_bank_details_instructor_fk
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE instructor_earnings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instructor_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    gross_amount DECIMAL(12,2) UNSIGNED NOT NULL,
    commission_rate DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 20,
    commission_amount DECIMAL(12,2) UNSIGNED NOT NULL,
    instructor_amount DECIMAL(12,2) UNSIGNED NOT NULL,
    earning_status ENUM('pending', 'available', 'withdraw_requested', 'paid', 'cancelled', 'refunded') NOT NULL DEFAULT 'available',
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY instructor_earnings_order_item_unique (order_item_id),
    KEY instructor_earnings_status_index (instructor_id, earning_status),
    CONSTRAINT earnings_instructor_fk
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT earnings_course_fk
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
    CONSTRAINT earnings_student_fk
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT earnings_order_fk
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    CONSTRAINT earnings_order_item_fk
        FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE RESTRICT,
    CONSTRAINT earnings_payment_fk
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE withdrawal_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instructor_id BIGINT UNSIGNED NOT NULL,
    requested_amount DECIMAL(12,2) UNSIGNED NOT NULL,
    payment_method ENUM('bank', 'esewa', 'khalti') NOT NULL,
    account_name VARCHAR(150) DEFAULT NULL,
    account_number VARCHAR(100) DEFAULT NULL,
    bank_name VARCHAR(150) DEFAULT NULL,
    esewa_number VARCHAR(30) DEFAULT NULL,
    khalti_number VARCHAR(30) DEFAULT NULL,
    request_status ENUM('pending', 'approved', 'paid', 'rejected') NOT NULL DEFAULT 'pending',
    instructor_note VARCHAR(1000) DEFAULT NULL,
    admin_note VARCHAR(1000) DEFAULT NULL,
    processed_by BIGINT UNSIGNED DEFAULT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    KEY withdrawal_requests_status_index (request_status, requested_at),
    CONSTRAINT withdrawal_requests_instructor_fk
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT withdrawal_requests_processor_fk
        FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE withdrawal_request_earnings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    withdrawal_request_id BIGINT UNSIGNED NOT NULL,
    earning_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY withdrawal_earning_unique (withdrawal_request_id, earning_id),
    UNIQUE KEY withdrawal_earning_single_request_unique (earning_id),
    CONSTRAINT withdrawal_map_request_fk
        FOREIGN KEY (withdrawal_request_id) REFERENCES withdrawal_requests(id) ON DELETE CASCADE,
    CONSTRAINT withdrawal_map_earning_fk
        FOREIGN KEY (earning_id) REFERENCES instructor_earnings(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE payouts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    withdrawal_request_id BIGINT UNSIGNED DEFAULT NULL,
    order_id BIGINT UNSIGNED DEFAULT NULL,
    payment_id BIGINT UNSIGNED DEFAULT NULL,
    payout_source ENUM('withdrawal', 'direct_order') NOT NULL DEFAULT 'withdrawal',
    instructor_id BIGINT UNSIGNED NOT NULL,
    paid_amount DECIMAL(12,2) UNSIGNED NOT NULL,
    payment_method ENUM('bank', 'esewa', 'khalti') NOT NULL,
    transaction_reference VARCHAR(150) NOT NULL,
    proof_image VARCHAR(255) DEFAULT NULL,
    payout_status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'paid',
    paid_by BIGINT UNSIGNED DEFAULT NULL,
    admin_note VARCHAR(1000) DEFAULT NULL,
    paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY payouts_instructor_index (instructor_id, paid_at),
    CONSTRAINT payouts_withdrawal_fk
        FOREIGN KEY (withdrawal_request_id) REFERENCES withdrawal_requests(id) ON DELETE SET NULL,
    CONSTRAINT payouts_order_fk
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    CONSTRAINT payouts_payment_fk
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    CONSTRAINT payouts_instructor_fk
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT payouts_admin_fk
        FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    message VARCHAR(1000) NOT NULL,
    notification_type VARCHAR(50) NOT NULL DEFAULT 'general',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL,
    KEY notifications_user_read_index (user_id, is_read, created_at),
    CONSTRAINT notifications_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE contact_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    subject VARCHAR(200) DEFAULT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY contact_messages_status_index (status, created_at)
) ENGINE=InnoDB;
