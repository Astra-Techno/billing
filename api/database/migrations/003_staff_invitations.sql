-- ── Staff Invitations ──────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `invitations` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   BIGINT UNSIGNED NOT NULL,
    `invited_by`    BIGINT UNSIGNED NOT NULL,
    `email`         VARCHAR(191)    NOT NULL,
    `role`          ENUM('admin','accountant','staff') NOT NULL DEFAULT 'staff',
    `token`         VARCHAR(64)     NOT NULL UNIQUE,
    `expires_at`    TIMESTAMP       NOT NULL,
    `accepted_at`   TIMESTAMP       NULL DEFAULT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invited_by`)  REFERENCES `users`(`id`)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
