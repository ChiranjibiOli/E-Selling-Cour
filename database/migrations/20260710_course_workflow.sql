USE coursehub;

ALTER TABLE courses
    ADD COLUMN submitted_at DATETIME DEFAULT NULL AFTER is_featured,
    ADD COLUMN reviewed_at DATETIME DEFAULT NULL AFTER submitted_at,
    ADD COLUMN reviewed_by BIGINT UNSIGNED DEFAULT NULL AFTER reviewed_at,
    ADD COLUMN review_note VARCHAR(1000) DEFAULT NULL AFTER reviewed_by,
    ADD CONSTRAINT courses_reviewer_fk
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL;

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
