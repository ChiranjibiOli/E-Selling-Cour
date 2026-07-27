CREATE TABLE IF NOT EXISTS oauth_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(32) NOT NULL,
    provider_user_id VARCHAR(191) NOT NULL,
    provider_email VARCHAR(191) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY oauth_provider_identity_unique (provider, provider_user_id),
    UNIQUE KEY oauth_user_provider_unique (user_id, provider),
    KEY oauth_user_index (user_id),
    CONSTRAINT oauth_accounts_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
