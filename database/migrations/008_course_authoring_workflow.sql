USE coursehub;

ALTER TABLE contact_messages
    ADD COLUMN reply_subject VARCHAR(200) DEFAULT NULL AFTER status,
    ADD COLUMN reply_message TEXT DEFAULT NULL AFTER reply_subject,
    ADD COLUMN replied_by BIGINT UNSIGNED DEFAULT NULL AFTER reply_message,
    ADD COLUMN replied_at DATETIME DEFAULT NULL AFTER replied_by,
    ADD COLUMN reply_delivery_status ENUM('not_sent', 'sent', 'failed') NOT NULL DEFAULT 'not_sent' AFTER replied_at,
    ADD CONSTRAINT contact_messages_replied_by_fk
        FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE courses
    ADD COLUMN edit_permission_status ENUM('none', 'requested', 'granted', 'denied') NOT NULL DEFAULT 'none' AFTER review_note,
    ADD COLUMN edit_permission_reason VARCHAR(1000) DEFAULT NULL AFTER edit_permission_status,
    ADD COLUMN edit_permission_requested_at DATETIME DEFAULT NULL AFTER edit_permission_reason,
    ADD COLUMN edit_permission_reviewed_by BIGINT UNSIGNED DEFAULT NULL AFTER edit_permission_requested_at,
    ADD COLUMN edit_permission_reviewed_at DATETIME DEFAULT NULL AFTER edit_permission_reviewed_by,
    ADD COLUMN edit_permission_note VARCHAR(1000) DEFAULT NULL AFTER edit_permission_reviewed_at,
    ADD COLUMN content_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER edit_permission_note,
    ADD KEY courses_edit_permission_index (edit_permission_status, edit_permission_requested_at),
    ADD CONSTRAINT courses_edit_permission_reviewer_fk
        FOREIGN KEY (edit_permission_reviewed_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE course_lessons
    MODIFY COLUMN content_type ENUM('text', 'word', 'video', 'pdf', 'audio', 'image', 'link') NOT NULL DEFAULT 'text',
    ADD COLUMN content_name VARCHAR(255) DEFAULT NULL AFTER content_url;

ALTER TABLE course_change_logs
    ADD COLUMN version_number INT UNSIGNED DEFAULT NULL AFTER change_type,
    ADD COLUMN change_summary JSON DEFAULT NULL AFTER after_snapshot,
    ADD COLUMN student_summary VARCHAR(1000) DEFAULT NULL AFTER change_summary;

CREATE TABLE course_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id BIGINT UNSIGNED NOT NULL,
    instructor_id BIGINT UNSIGNED NOT NULL,
    revision_snapshot JSON NOT NULL,
    change_summary JSON NOT NULL,
    student_summary VARCHAR(1000) NOT NULL,
    revision_status ENUM('draft', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
    review_note VARCHAR(1000) DEFAULT NULL,
    reviewed_by BIGINT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY course_revisions_status_index (revision_status, created_at),
    KEY course_revisions_course_index (course_id, created_at),
    CONSTRAINT course_revisions_course_fk
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT course_revisions_instructor_fk
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT course_revisions_reviewer_fk
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
