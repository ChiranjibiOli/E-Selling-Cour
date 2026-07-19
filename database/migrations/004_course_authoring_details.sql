USE coursehub;

ALTER TABLE courses
    ADD COLUMN subtitle VARCHAR(240) DEFAULT NULL AFTER title,
    ADD COLUMN discount_price DECIMAL(12,2) UNSIGNED DEFAULT NULL AFTER price,
    ADD COLUMN learning_outcomes JSON DEFAULT NULL AFTER full_description,
    ADD COLUMN requirements JSON DEFAULT NULL AFTER learning_outcomes,
    ADD COLUMN target_audience JSON DEFAULT NULL AFTER requirements,
    ADD COLUMN tags VARCHAR(500) DEFAULT NULL AFTER target_audience,
    ADD COLUMN intro_video_url VARCHAR(500) DEFAULT NULL AFTER thumbnail;