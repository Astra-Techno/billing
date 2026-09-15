-- Cloud-only desktop activation and licence management.
-- Local/desktop migration runners must record this filename without executing it.

CREATE TABLE IF NOT EXISTS `desktop_activation_requests` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `request_uuid`        CHAR(36)        NOT NULL,
    `request_secret_hash` CHAR(64)        NOT NULL,
    `user_id`             BIGINT UNSIGNED DEFAULT NULL,
    `business_id`         BIGINT UNSIGNED DEFAULT NULL,
    `device_hmac`         CHAR(64)        NOT NULL,
    `encrypted_payload`   MEDIUMTEXT      NOT NULL COMMENT 'Versioned AES-256-GCM envelope',
    `status`              ENUM('pending','approved','rejected','expired','consumed') NOT NULL DEFAULT 'pending',
    `expires_at`          DATETIME        NOT NULL,
    `attempt_count`       INT UNSIGNED    NOT NULL DEFAULT 0,
    `approved_by`         BIGINT UNSIGNED DEFAULT NULL,
    `approved_at`         DATETIME        DEFAULT NULL,
    `rejected_at`         DATETIME        DEFAULT NULL,
    `rejection_reason`    VARCHAR(500)    DEFAULT NULL,
    `consumed_at`         DATETIME        DEFAULT NULL,
    `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `desktop_activation_request_uuid_unique` (`request_uuid`),
    UNIQUE KEY `desktop_activation_secret_hash_unique` (`request_secret_hash`),
    KEY `desktop_activation_status_expires_index` (`status`, `expires_at`),
    KEY `desktop_activation_device_index` (`device_hmac`),
    KEY `desktop_activation_user_index` (`user_id`),
    KEY `desktop_activation_business_index` (`business_id`),
    CONSTRAINT `desktop_activation_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `desktop_activation_business_fk` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `desktop_activation_approver_fk` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `desktop_licenses` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `license_uuid`          CHAR(36)        NOT NULL,
    `activation_request_id` BIGINT UNSIGNED DEFAULT NULL,
    `user_id`               BIGINT UNSIGNED DEFAULT NULL,
    `business_id`           BIGINT UNSIGNED DEFAULT NULL,
    `device_hmac`           CHAR(64)        NOT NULL,
    `encrypted_customer`    MEDIUMTEXT      NOT NULL COMMENT 'Versioned AES-256-GCM envelope',
    `encrypted_device`      MEDIUMTEXT      NOT NULL COMMENT 'Versioned AES-256-GCM envelope',
    `status`                ENUM('active','suspended','revoked','expired','transferred') NOT NULL DEFAULT 'active',
    `edition`               VARCHAR(50)     NOT NULL DEFAULT 'offline-single-pc',
    `issued_at`             DATETIME        NOT NULL,
    `expires_at`            DATETIME        DEFAULT NULL,
    `grace_ends_at`         DATETIME        DEFAULT NULL,
    `max_version`           VARCHAR(50)     DEFAULT NULL,
    `license_document_hash` CHAR(64)        NOT NULL,
    `last_cloud_contact_at` DATETIME        DEFAULT NULL,
    `approved_by`           BIGINT UNSIGNED DEFAULT NULL,
    `revoked_by`            BIGINT UNSIGNED DEFAULT NULL,
    `revoked_at`            DATETIME        DEFAULT NULL,
    `revocation_reason`     VARCHAR(500)    DEFAULT NULL,
    `transferred_from_id`   BIGINT UNSIGNED DEFAULT NULL,
    `replacement_license_id` BIGINT UNSIGNED DEFAULT NULL,
    `transfer_count`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `desktop_license_uuid_unique` (`license_uuid`),
    UNIQUE KEY `desktop_license_request_unique` (`activation_request_id`),
    KEY `desktop_license_status_expiry_index` (`status`, `expires_at`),
    KEY `desktop_license_device_index` (`device_hmac`),
    KEY `desktop_license_user_index` (`user_id`),
    KEY `desktop_license_business_index` (`business_id`),
    CONSTRAINT `desktop_license_request_fk` FOREIGN KEY (`activation_request_id`) REFERENCES `desktop_activation_requests` (`id`) ON DELETE SET NULL,
    CONSTRAINT `desktop_license_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `desktop_license_business_fk` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `desktop_license_approver_fk` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `desktop_license_revoker_fk` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `desktop_license_previous_fk` FOREIGN KEY (`transferred_from_id`) REFERENCES `desktop_licenses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `desktop_license_replacement_fk` FOREIGN KEY (`replacement_license_id`) REFERENCES `desktop_licenses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `desktop_license_events` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `license_id`            BIGINT UNSIGNED DEFAULT NULL,
    `activation_request_id` BIGINT UNSIGNED DEFAULT NULL,
    `action`                VARCHAR(50)     NOT NULL,
    `encrypted_metadata`    MEDIUMTEXT      DEFAULT NULL COMMENT 'Versioned AES-256-GCM envelope',
    `actor_admin_id`        BIGINT UNSIGNED DEFAULT NULL,
    `ip_address`            VARCHAR(45)     DEFAULT NULL,
    `user_agent`            VARCHAR(500)    DEFAULT NULL,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `desktop_license_event_license_index` (`license_id`, `created_at`),
    KEY `desktop_license_event_request_index` (`activation_request_id`, `created_at`),
    KEY `desktop_license_event_action_index` (`action`, `created_at`),
    CONSTRAINT `desktop_license_event_license_fk` FOREIGN KEY (`license_id`) REFERENCES `desktop_licenses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `desktop_license_event_request_fk` FOREIGN KEY (`activation_request_id`) REFERENCES `desktop_activation_requests` (`id`) ON DELETE SET NULL,
    CONSTRAINT `desktop_license_event_actor_fk` FOREIGN KEY (`actor_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS `desktop_license_events_no_update`;
CREATE TRIGGER `desktop_license_events_no_update`
BEFORE UPDATE ON `desktop_license_events`
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Desktop licence audit events are immutable';

DROP TRIGGER IF EXISTS `desktop_license_events_no_delete`;
CREATE TRIGGER `desktop_license_events_no_delete`
BEFORE DELETE ON `desktop_license_events`
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Desktop licence audit events are immutable';
