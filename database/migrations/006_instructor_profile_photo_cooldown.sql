USE coursehub;

SET @migration_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'users'
          AND column_name = 'profile_image_changed_at'
    ),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN profile_image_changed_at DATETIME DEFAULT NULL AFTER profile_image'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

UPDATE users u
INNER JOIN instructor_applications a ON a.instructor_id = u.id
SET u.profile_image_changed_at = COALESCE(a.reviewed_at, u.updated_at, NOW())
WHERE u.role = 'instructor'
  AND u.status = 'active'
  AND u.profile_image IS NOT NULL
  AND u.profile_image_changed_at IS NULL
  AND a.application_status = 'approved';
