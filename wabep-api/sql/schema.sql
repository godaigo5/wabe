CREATE TABLE IF NOT EXISTS licenses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    license_key VARCHAR(191) NOT NULL,
    plan ENUM('free','advanced','pro') NOT NULL DEFAULT 'free',
    status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    domain_limit INT UNSIGNED NOT NULL DEFAULT 1,
    customer_email VARCHAR(191) DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_checked_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_license_key (license_key),
    KEY idx_plan (plan),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS license_activations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    license_id BIGINT UNSIGNED NOT NULL,
    domain VARCHAR(191) NOT NULL,
    plugin VARCHAR(191) DEFAULT NULL,
    version VARCHAR(50) DEFAULT NULL,
    activated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_license_domain (license_id, domain),
    KEY idx_license_id (license_id),
    CONSTRAINT fk_license_activations_license
        FOREIGN KEY (license_id) REFERENCES licenses(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
