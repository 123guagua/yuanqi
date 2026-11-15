CREATE TABLE IF NOT EXISTS proxies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    host VARCHAR(45) NOT NULL,
    port SMALLINT UNSIGNED NOT NULL,
    username VARCHAR(191) NOT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('published') NOT NULL DEFAULT 'published',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status_id (status, id),
    KEY idx_host (host),
    KEY idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
