CREATE TABLE IF NOT EXISTS thermal_print_jobs (
    token_hash CHAR(64) NOT NULL PRIMARY KEY,
    business_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thermal_print_jobs_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
