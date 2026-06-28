-- ============================================================
-- Migration 011: E-way Bills
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── E-way Bills ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `eway_bills` (
    `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  BIGINT UNSIGNED  NOT NULL,
    `created_by`   BIGINT UNSIGNED  NOT NULL,
    `invoice_id`   BIGINT UNSIGNED  NOT NULL,
    `ewb_number`   VARCHAR(30)      NOT NULL COMMENT 'NIC 12-digit number, or 99xxxxxxxxxx for simulation',
    `status`       ENUM('active','cancelled','expired') NOT NULL DEFAULT 'active',
    `mode`         ENUM('road','rail','air','ship')     NOT NULL DEFAULT 'road',
    `distance`     DECIMAL(8,2)     NOT NULL DEFAULT 0.00 COMMENT 'distance in km',
    `vehicle_no`   VARCHAR(20)      DEFAULT NULL,
    `vehicle_type` VARCHAR(30)      NOT NULL DEFAULT 'Regular',
    `transporter`  VARCHAR(100)     DEFAULT NULL,
    `valid_from`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `valid_until`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invoice_id`)  REFERENCES `invoices`(`id`)   ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)      ON DELETE RESTRICT,
    INDEX `ewb_business_id`  (`business_id`),
    INDEX `ewb_invoice_id`   (`invoice_id`),
    INDEX `ewb_status`       (`status`),
    INDEX `ewb_valid_until`  (`valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
