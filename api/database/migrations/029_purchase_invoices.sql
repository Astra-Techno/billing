-- ============================================================
-- Migration 029: Purchase Invoices (Bills from Suppliers)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `purchase_invoices` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`     BIGINT UNSIGNED NOT NULL,
    `created_by`      BIGINT UNSIGNED NOT NULL,
    `supplier_id`     BIGINT UNSIGNED NOT NULL COMMENT 'references clients table',
    `po_id`           BIGINT UNSIGNED DEFAULT NULL COMMENT 'linked purchase order',
    `location_id`     BIGINT UNSIGNED DEFAULT NULL,
    `number`          VARCHAR(50)     NOT NULL COMMENT 'auto-generated PI/2024-25/0001',
    `supplier_inv_no` VARCHAR(100)    DEFAULT NULL COMMENT 'supplier original invoice number',
    `status`          ENUM('draft','recorded','paid','partial','cancelled') NOT NULL DEFAULT 'draft',
    `invoice_date`    DATE            NOT NULL,
    `due_date`        DATE            DEFAULT NULL,
    `financial_year`  CHAR(7)         NOT NULL COMMENT '2024-25',
    -- GST
    `supply_type`     ENUM('intra','inter') NOT NULL DEFAULT 'intra',
    `place_of_supply` SMALLINT UNSIGNED DEFAULT NULL,
    `reverse_charge`  TINYINT(1)      NOT NULL DEFAULT 0,
    -- Amounts
    `subtotal`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `cgst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `sgst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `igst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `tax_total`       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `discount`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `round_off`       DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `total`           DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `amount_paid`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `amount_due`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    -- Meta
    `notes`           TEXT            DEFAULT NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`      TIMESTAMP       NULL DEFAULT NULL,
    UNIQUE KEY `pi_biz_number` (`business_id`, `number`),
    FOREIGN KEY (`business_id`)     REFERENCES `businesses`(`id`)       ON DELETE CASCADE,
    FOREIGN KEY (`supplier_id`)     REFERENCES `clients`(`id`)          ON DELETE RESTRICT,
    FOREIGN KEY (`po_id`)           REFERENCES `purchase_orders`(`id`)  ON DELETE SET NULL,
    FOREIGN KEY (`location_id`)     REFERENCES `inventory_locations`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`)      REFERENCES `users`(`id`)            ON DELETE RESTRICT,
    FOREIGN KEY (`place_of_supply`) REFERENCES `indian_states`(`id`)    ON DELETE RESTRICT,
    INDEX `pi_business_id` (`business_id`),
    INDEX `pi_supplier_id` (`supplier_id`),
    INDEX `pi_status`      (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_invoice_items` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `pi_id`        BIGINT UNSIGNED NOT NULL,
    `product_id`   BIGINT UNSIGNED DEFAULT NULL,
    `description`  TEXT            NOT NULL,
    `hsn_sac`      VARCHAR(10)     DEFAULT NULL,
    `unit`         VARCHAR(20)     DEFAULT 'Nos',
    `quantity`     DECIMAL(15,3)   NOT NULL DEFAULT 1.000,
    `unit_price`   DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `discount_pct` DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `discount_amt` DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `taxable_amt`  DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `gst_rate`     DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `cgst_rate`    DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `sgst_rate`    DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `igst_rate`    DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `cgst_amt`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `sgst_amt`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `igst_amt`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `total`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `sort_order`   SMALLINT        NOT NULL DEFAULT 0,
    FOREIGN KEY (`pi_id`)      REFERENCES `purchase_invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments made TO suppliers (outward payments)
CREATE TABLE IF NOT EXISTS `purchase_payments` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   BIGINT UNSIGNED NOT NULL,
    `pi_id`         BIGINT UNSIGNED NOT NULL,
    `supplier_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
    `recorded_by`   BIGINT UNSIGNED DEFAULT NULL,
    `amount`        DECIMAL(15,2)   NOT NULL,
    `method`        ENUM('cash','upi','neft','rtgs','imps','cheque','card','netbanking','other')
                        NOT NULL DEFAULT 'cash',
    `reference`     VARCHAR(255)    DEFAULT NULL,
    `utr_number`    VARCHAR(50)     DEFAULT NULL,
    `payment_date`  DATE            NOT NULL,
    `note`          TEXT            DEFAULT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`)  REFERENCES `businesses`(`id`)         ON DELETE CASCADE,
    FOREIGN KEY (`pi_id`)        REFERENCES `purchase_invoices`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`supplier_id`)  REFERENCES `clients`(`id`)            ON DELETE SET NULL,
    INDEX `pp_business_id` (`business_id`),
    INDEX `pp_pi_id`       (`pi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add 'purchase_invoice' to sequences type if needed (handled by Sequence Task auto-create)
-- Add 'pi' type to the Sequence validator

SET FOREIGN_KEY_CHECKS = 1;
