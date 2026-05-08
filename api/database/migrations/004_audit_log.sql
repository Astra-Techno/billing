-- Audit log: records destructive actions (cancel, delete) on financial records.
-- Gives owners full traceability of who changed what and when.

CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id` BIGINT UNSIGNED NOT NULL,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `action`      VARCHAR(50)     NOT NULL,          -- 'cancel', 'delete', 'update_role', etc.
    `entity_type` VARCHAR(50)     NOT NULL,          -- 'invoice', 'client', 'payment', etc.
    `entity_id`   BIGINT UNSIGNED NOT NULL,
    `snapshot`    JSON            NULL,              -- key fields at the time of action
    `note`        VARCHAR(500)    NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_business_entity` (`business_id`, `entity_type`, `entity_id`),
    INDEX `idx_created_at`      (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
