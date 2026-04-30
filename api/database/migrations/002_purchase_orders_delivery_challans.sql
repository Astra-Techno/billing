-- ============================================================
-- Migration 002: Purchase Orders & Delivery Challans
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Purchase Orders ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   BIGINT UNSIGNED NOT NULL,
    `created_by`    BIGINT UNSIGNED NOT NULL,
    `supplier_id`   BIGINT UNSIGNED NOT NULL COMMENT 'references clients table',
    `number`        VARCHAR(50)     NOT NULL,
    `status`        ENUM('draft','sent','received','cancelled') NOT NULL DEFAULT 'draft',
    `order_date`    DATE            NOT NULL,
    `expected_date` DATE            DEFAULT NULL,
    `subtotal`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `tax_total`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `total`         DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `notes`         TEXT            DEFAULT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `po_biz_number` (`business_id`, `number`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`supplier_id`) REFERENCES `clients`(`id`)    ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)      ON DELETE RESTRICT,
    INDEX `po_business_id` (`business_id`),
    INDEX `po_supplier_id` (`supplier_id`),
    INDEX `po_status`      (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Purchase Order Items ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `po_id`       BIGINT UNSIGNED NOT NULL,
    `product_id`  BIGINT UNSIGNED DEFAULT NULL,
    `description` TEXT            NOT NULL,
    `hsn_sac`     VARCHAR(10)     DEFAULT NULL,
    `unit`        VARCHAR(20)     DEFAULT 'Nos',
    `quantity`    DECIMAL(15,3)   NOT NULL DEFAULT 1.000,
    `unit_price`  DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `gst_rate`    DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `total`       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `sort_order`  SMALLINT        NOT NULL DEFAULT 0,
    FOREIGN KEY (`po_id`)      REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Delivery Challans ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `delivery_challans` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  BIGINT UNSIGNED NOT NULL,
    `created_by`   BIGINT UNSIGNED NOT NULL,
    `client_id`    BIGINT UNSIGNED NOT NULL,
    `number`       VARCHAR(50)     NOT NULL,
    `status`       ENUM('draft','issued','delivered','cancelled') NOT NULL DEFAULT 'draft',
    `challan_date` DATE            NOT NULL,
    `vehicle_no`   VARCHAR(30)     DEFAULT NULL,
    `driver_name`  VARCHAR(100)    DEFAULT NULL,
    `destination`  VARCHAR(255)    DEFAULT NULL,
    `notes`        TEXT            DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `dc_biz_number` (`business_id`, `number`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`)   REFERENCES `clients`(`id`)    ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)      ON DELETE RESTRICT,
    INDEX `dc_business_id` (`business_id`),
    INDEX `dc_client_id`   (`client_id`),
    INDEX `dc_status`      (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Delivery Challan Items ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `delivery_challan_items` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `dc_id`       BIGINT UNSIGNED NOT NULL,
    `product_id`  BIGINT UNSIGNED DEFAULT NULL,
    `description` TEXT            NOT NULL,
    `hsn_sac`     VARCHAR(10)     DEFAULT NULL,
    `unit`        VARCHAR(20)     DEFAULT 'Nos',
    `quantity`    DECIMAL(15,3)   NOT NULL DEFAULT 1.000,
    `sort_order`  SMALLINT        NOT NULL DEFAULT 0,
    FOREIGN KEY (`dc_id`)      REFERENCES `delivery_challans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
