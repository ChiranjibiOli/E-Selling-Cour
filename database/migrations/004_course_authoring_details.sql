USE coursehub;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'subtitle'),
    'SELECT 1',
    'ALTER TABLE courses ADD COLUMN subtitle VARCHAR(240) DEFAULT NULL AFTER title'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'discount_price'),
    'SELECT 1',
    'ALTER TABLE courses ADD COLUMN discount_price DECIMAL(12,2) UNSIGNED DEFAULT NULL AFTER price'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'learning_outcomes'),
    'SELECT 1',
    'ALTER TABLE courses ADD COLUMN learning_outcomes JSON DEFAULT NULL AFTER full_description'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'requirements'),
    'SELECT 1',
    'ALTER TABLE courses ADD COLUMN requirements JSON DEFAULT NULL AFTER learning_outcomes'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'target_audience'),
    'SELECT 1',
    'ALTER TABLE courses ADD COLUMN target_audience JSON DEFAULT NULL AFTER requirements'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'tags'),
    'SELECT 1',
    'ALTER TABLE courses ADD COLUMN tags VARCHAR(500) DEFAULT NULL AFTER target_audience'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'courses' AND column_name = 'intro_video_url'),
    'SELECT 1',
    'ALTER TABLE courses ADD COLUMN intro_video_url VARCHAR(500) DEFAULT NULL AFTER thumbnail'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
