-- ============================================================
-- BillBook India — Multi-Tenant SaaS Billing
-- Built specifically for Indian businesses
-- ============================================================
--
-- India-specific features:
--  • GST: CGST / SGST / IGST / UTGST
--  • GSTIN, PAN, HSN/SAC codes
--  • INR default currency
--  • UPI payment, bank IFSC
--  • Financial year: April–March
--  • Place of Supply (intra/inter state)
--  • E-Invoice IRN, E-Way Bill
--  • Credit Note / Debit Note
--  • Indian states with GST state codes
--
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- SECTION 0: REFERENCE DATA
-- ─────────────────────────────────────────────────────────────

-- ── Indian States & Union Territories ────────────────────────
-- GST state code used to determine CGST+SGST vs IGST.

CREATE TABLE IF NOT EXISTS `indian_states` (
    `id`         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100)      NOT NULL,
    `code`       CHAR(2)           NOT NULL UNIQUE COMMENT 'GST state code (01–38)',
    `is_ut`      TINYINT(1)        NOT NULL DEFAULT 0 COMMENT '1 = Union Territory (UTGST applies)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `indian_states` (`name`, `code`, `is_ut`) VALUES
('Jammu & Kashmir',         '01', 0),
('Himachal Pradesh',        '02', 0),
('Punjab',                  '03', 0),
('Chandigarh',              '04', 1),
('Uttarakhand',             '05', 0),
('Haryana',                 '06', 0),
('Delhi',                   '07', 1),
('Rajasthan',               '08', 0),
('Uttar Pradesh',           '09', 0),
('Bihar',                   '10', 0),
('Sikkim',                  '11', 0),
('Arunachal Pradesh',       '12', 0),
('Nagaland',                '13', 0),
('Manipur',                 '14', 0),
('Mizoram',                 '15', 0),
('Tripura',                 '16', 0),
('Meghalaya',               '17', 0),
('Assam',                   '18', 0),
('West Bengal',             '19', 0),
('Jharkhand',               '20', 0),
('Odisha',                  '21', 0),
('Chhattisgarh',            '22', 0),
('Madhya Pradesh',          '23', 0),
('Gujarat',                 '24', 0),
('Dadra & Nagar Haveli and Daman & Diu', '26', 1),
('Maharashtra',             '27', 0),
('Andhra Pradesh (New)',    '28', 0),
('Karnataka',               '29', 0),
('Goa',                     '30', 0),
('Lakshadweep',             '31', 1),
('Kerala',                  '32', 0),
('Tamil Nadu',              '33', 0),
('Puducherry',              '34', 1),
('Andaman & Nicobar Islands','35', 1),
('Telangana',               '36', 0),
('Andhra Pradesh (Old)',    '37', 0),
('Ladakh',                  '38', 1);

-- ─────────────────────────────────────────────────────────────
-- SECTION 1: PLATFORM / SAAS LAYER
-- ─────────────────────────────────────────────────────────────

-- ── Plans (INR pricing) ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS `plans` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`                   VARCHAR(100)    NOT NULL,
    `slug`                   VARCHAR(100)    NOT NULL UNIQUE,
    `description`            TEXT            DEFAULT NULL,
    `price_monthly`          DECIMAL(10,2)   NOT NULL DEFAULT 0.00  COMMENT 'INR per month',
    `price_yearly`           DECIMAL(10,2)   NOT NULL DEFAULT 0.00  COMMENT 'INR per year',
    -- Limits (NULL = unlimited)
    `max_users`              INT             DEFAULT 1,
    `max_clients`            INT             DEFAULT 10,
    `max_invoices_month`     INT             DEFAULT 10,
    `max_storage_mb`         INT             DEFAULT 100,
    -- Features
    `feature_quotes`         TINYINT(1)      NOT NULL DEFAULT 0,
    `feature_recurring`      TINYINT(1)      NOT NULL DEFAULT 0,
    `feature_reports`        TINYINT(1)      NOT NULL DEFAULT 0,
    `feature_einvoice`       TINYINT(1)      NOT NULL DEFAULT 0  COMMENT 'e-Invoice IRN generation',
    `feature_ewaybill`       TINYINT(1)      NOT NULL DEFAULT 0,
    `feature_credit_note`    TINYINT(1)      NOT NULL DEFAULT 1,
    `feature_whatsapp`       TINYINT(1)      NOT NULL DEFAULT 0  COMMENT 'WhatsApp share',
    `feature_custom_logo`    TINYINT(1)      NOT NULL DEFAULT 0,
    `feature_multi_user`     TINYINT(1)      NOT NULL DEFAULT 0,
    `feature_api`            TINYINT(1)      NOT NULL DEFAULT 0,
    `is_public`              TINYINT(1)      NOT NULL DEFAULT 1,
    `sort_order`             INT             NOT NULL DEFAULT 0,
    `created_at`             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `plans`
    (`id`,`name`,`slug`,`price_monthly`,`price_yearly`,`max_users`,`max_clients`,
     `max_invoices_month`,`max_storage_mb`,
     `feature_quotes`,`feature_recurring`,`feature_reports`,`feature_einvoice`,
     `feature_ewaybill`,`feature_whatsapp`,`feature_custom_logo`,`feature_multi_user`,
     `feature_api`,`is_public`,`sort_order`)
VALUES
-- Free: small shops, freelancers just starting
(1,'Free',      'free',    0,     0,      1,  10,  10,  50,   0,0,0,0,0,0,0,0,0, 1,1),
-- Basic: ₹199/mo — small traders, consultants
(2,'Basic',     'basic',   199,   1999,   1,  100, 50,  500,  1,0,0,0,0,1,1,0,0, 1,2),
-- Business: ₹499/mo — growing businesses
(3,'Business',  'business',499,   4999,   5,  NULL,NULL,2000, 1,1,1,1,0,1,1,1,0, 1,3),
-- Pro: ₹999/mo — large businesses with e-invoicing
(4,'Pro',       'pro',     999,   9999,   NULL,NULL,NULL,NULL, 1,1,1,1,1,1,1,1,1, 1,4);

-- ── Global Users ──────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `users` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`               VARCHAR(255)    NOT NULL,
    `email`              VARCHAR(255)    NOT NULL UNIQUE,
    `password`           VARCHAR(255)    NOT NULL,
    `mobile`             VARCHAR(15)     DEFAULT NULL COMMENT 'Indian mobile number',
    `avatar`             VARCHAR(500)    DEFAULT NULL,
    `email_verified_at`  TIMESTAMP       NULL DEFAULT NULL,
    `mobile_verified_at` TIMESTAMP       NULL DEFAULT NULL,
    `verification_token` VARCHAR(100)    DEFAULT NULL,
    `active`             TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Businesses (Tenants) ──────────────────────────────────────
-- Complete Indian business identity

CREATE TABLE IF NOT EXISTS `businesses` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `owner_id`         BIGINT UNSIGNED NOT NULL,
    -- Basic info
    `name`             VARCHAR(255)    NOT NULL  COMMENT 'Business / shop / firm name',
    `slug`             VARCHAR(100)    NOT NULL UNIQUE,
    `business_type`    ENUM('proprietorship','partnership','llp','private_ltd','public_ltd','trust','society','other')
                                       NOT NULL DEFAULT 'proprietorship',
    -- Indian tax identity
    `gstin`            VARCHAR(15)     DEFAULT NULL COMMENT '15-digit GSTIN',
    `pan`              VARCHAR(10)     DEFAULT NULL COMMENT 'Business PAN',
    `cin`              VARCHAR(21)     DEFAULT NULL COMMENT 'Company Identification Number (if Ltd)',
    `is_gst_registered` TINYINT(1)    NOT NULL DEFAULT 0,
    `gst_registered_date` DATE        DEFAULT NULL,
    -- Contact
    `email`            VARCHAR(255)    DEFAULT NULL,
    `mobile`           VARCHAR(15)     DEFAULT NULL,
    `phone`            VARCHAR(15)     DEFAULT NULL,
    `website`          VARCHAR(255)    DEFAULT NULL,
    -- Address (Indian format)
    `address_line1`    VARCHAR(255)    DEFAULT NULL,
    `address_line2`    VARCHAR(255)    DEFAULT NULL,
    `city`             VARCHAR(100)    DEFAULT NULL,
    `state_id`         SMALLINT UNSIGNED DEFAULT NULL,
    `pincode`          VARCHAR(10)     DEFAULT NULL,
    -- Bank details for invoice footer & UPI payments
    `bank_name`        VARCHAR(100)    DEFAULT NULL,
    `bank_account_no`  VARCHAR(30)     DEFAULT NULL,
    `bank_ifsc`        VARCHAR(15)     DEFAULT NULL,
    `bank_account_name` VARCHAR(255)   DEFAULT NULL,
    `upi_id`           VARCHAR(100)    DEFAULT NULL COMMENT 'e.g. mybusiness@upi',
    -- Branding & preferences
    `logo`             VARCHAR(500)    DEFAULT NULL,
    `currency`         VARCHAR(5)      NOT NULL DEFAULT 'INR',
    `timezone`         VARCHAR(100)    NOT NULL DEFAULT 'Asia/Kolkata',
    `date_format`      VARCHAR(20)     NOT NULL DEFAULT 'd/m/Y',
    `financial_year_start` TINYINT     NOT NULL DEFAULT 4 COMMENT 'Month: 4=April (Indian FY)',
    `invoice_prefix`   VARCHAR(20)     NOT NULL DEFAULT 'INV',
    `quote_prefix`     VARCHAR(20)     NOT NULL DEFAULT 'QTE',
    `invoice_terms`    TEXT            DEFAULT NULL COMMENT 'Default terms & conditions',
    `invoice_notes`    TEXT            DEFAULT NULL COMMENT 'Default footer notes',
    `signature`        VARCHAR(500)    DEFAULT NULL COMMENT 'Digital signature image path',
    `active`           TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`)  REFERENCES `users`(`id`)          ON DELETE RESTRICT,
    FOREIGN KEY (`state_id`)  REFERENCES `indian_states`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Business Users (Members / Staff) ─────────────────────────

CREATE TABLE IF NOT EXISTS `business_users` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   BIGINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `role`          ENUM('owner','admin','accountant','staff') NOT NULL DEFAULT 'staff',
    `invited_by`    BIGINT UNSIGNED DEFAULT NULL,
    `accepted_at`   TIMESTAMP       NULL DEFAULT NULL,
    `active`        TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `biz_user_unique` (`business_id`, `user_id`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Subscriptions ─────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`          BIGINT UNSIGNED NOT NULL,
    `plan_id`              BIGINT UNSIGNED NOT NULL,
    `status`               ENUM('trialing','active','past_due','cancelled','expired') NOT NULL DEFAULT 'trialing',
    `billing_cycle`        ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    `trial_ends_at`        DATE            NULL DEFAULT NULL,
    `current_period_start` DATE            NULL DEFAULT NULL,
    `current_period_end`   DATE            NULL DEFAULT NULL,
    `cancelled_at`         TIMESTAMP       NULL DEFAULT NULL,
    `ends_at`              DATE            NULL DEFAULT NULL,
    `payment_method`       VARCHAR(50)     DEFAULT NULL COMMENT 'razorpay|upi|manual',
    `external_id`          VARCHAR(255)    DEFAULT NULL COMMENT 'Razorpay subscription ID',
    `created_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`plan_id`)     REFERENCES `plans`(`id`)      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Personal Access Tokens ────────────────────────────────────

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tokenable_type` VARCHAR(255)    NOT NULL DEFAULT 'App\\Tables\\User',
    `tokenable_id`   BIGINT UNSIGNED NOT NULL,
    `business_id`    BIGINT UNSIGNED DEFAULT NULL,
    `name`           VARCHAR(255)    NOT NULL,
    `token`          VARCHAR(64)     NOT NULL UNIQUE,
    `abilities`      TEXT            DEFAULT NULL,
    `last_used_at`   TIMESTAMP       NULL DEFAULT NULL,
    `expires_at`     TIMESTAMP       NULL DEFAULT NULL,
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    INDEX `pat_tokenable` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Invitations ───────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `invitations` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  BIGINT UNSIGNED NOT NULL,
    `invited_by`   BIGINT UNSIGNED NOT NULL,
    `email`        VARCHAR(255)    NOT NULL,
    `mobile`       VARCHAR(15)     DEFAULT NULL,
    `role`         ENUM('admin','accountant','staff') NOT NULL DEFAULT 'staff',
    `token`        VARCHAR(100)    NOT NULL UNIQUE,
    `accepted_at`  TIMESTAMP       NULL DEFAULT NULL,
    `expires_at`   TIMESTAMP       NOT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invited_by`)  REFERENCES `users`(`id`)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Usage Logs ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `usage_logs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  BIGINT UNSIGNED NOT NULL,
    `metric`       VARCHAR(50)     NOT NULL COMMENT 'invoices_created|storage_mb|clients',
    `period`       CHAR(7)         NOT NULL COMMENT 'YYYY-MM',
    `value`        BIGINT          NOT NULL DEFAULT 0,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `usage_biz_metric_period` (`business_id`, `metric`, `period`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- SECTION 2: BILLING DATA (scoped by business_id)
-- ─────────────────────────────────────────────────────────────

-- ── GST Tax Rates ─────────────────────────────────────────────
-- Standard GST slabs: 0%, 5%, 12%, 18%, 28%
-- CGST = SGST = GST/2 for intra-state
-- IGST = full GST rate for inter-state

CREATE TABLE IF NOT EXISTS `tax_rates` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  BIGINT UNSIGNED NOT NULL,
    `name`         VARCHAR(100)    NOT NULL COMMENT 'e.g. GST 18%, GST 5%',
    `rate`         DECIMAL(6,2)    NOT NULL DEFAULT 0.00 COMMENT 'Total GST %',
    `cgst_rate`    DECIMAL(6,2)    NOT NULL DEFAULT 0.00 COMMENT 'rate/2',
    `sgst_rate`    DECIMAL(6,2)    NOT NULL DEFAULT 0.00 COMMENT 'rate/2',
    `igst_rate`    DECIMAL(6,2)    NOT NULL DEFAULT 0.00 COMMENT 'same as rate',
    `utgst_rate`   DECIMAL(6,2)    NOT NULL DEFAULT 0.00 COMMENT 'for UTs instead of SGST',
    `is_default`   TINYINT(1)      NOT NULL DEFAULT 0,
    `active`       TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    INDEX `tr_business_id` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Products & Services ───────────────────────────────────────
-- HSN for goods, SAC for services — mandatory on GST invoices

CREATE TABLE IF NOT EXISTS `products` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  BIGINT UNSIGNED NOT NULL,
    `type`         ENUM('product','service') NOT NULL DEFAULT 'service',
    `name`         VARCHAR(255)    NOT NULL,
    `description`  TEXT            DEFAULT NULL,
    `hsn_sac`      VARCHAR(10)     DEFAULT NULL COMMENT 'HSN code (goods) or SAC code (services)',
    `unit`         VARCHAR(20)     DEFAULT 'Nos' COMMENT 'Nos, Kg, Ltr, Hrs, Pcs, Mtr...',
    `price`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `tax_rate_id`  BIGINT UNSIGNED DEFAULT NULL,
    `sku`          VARCHAR(100)    DEFAULT NULL,
    `active`       TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates`(`id`)  ON DELETE SET NULL,
    INDEX `prod_business_id` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Clients (Customers / Parties) ────────────────────────────

CREATE TABLE IF NOT EXISTS `clients` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`    BIGINT UNSIGNED NOT NULL,
    `type`           ENUM('business','individual') NOT NULL DEFAULT 'business',
    `name`           VARCHAR(255)    NOT NULL  COMMENT 'Party / customer name',
    `company`        VARCHAR(255)    DEFAULT NULL,
    `gstin`          VARCHAR(15)     DEFAULT NULL COMMENT 'Client GSTIN (for B2B invoices)',
    `pan`            VARCHAR(10)     DEFAULT NULL,
    `email`          VARCHAR(255)    DEFAULT NULL,
    `mobile`         VARCHAR(15)     DEFAULT NULL,
    `phone`          VARCHAR(15)     DEFAULT NULL,
    -- Address
    `address_line1`  VARCHAR(255)    DEFAULT NULL,
    `address_line2`  VARCHAR(255)    DEFAULT NULL,
    `city`           VARCHAR(100)    DEFAULT NULL,
    `state_id`       SMALLINT UNSIGNED DEFAULT NULL COMMENT 'For CGST/SGST vs IGST determination',
    `pincode`        VARCHAR(10)     DEFAULT NULL,
    -- Billing preferences
    `currency`       VARCHAR(5)      NOT NULL DEFAULT 'INR',
    `credit_limit`   DECIMAL(15,2)   DEFAULT NULL COMMENT 'Outstanding credit limit',
    `credit_days`    TINYINT UNSIGNED DEFAULT 30 COMMENT 'Default payment due days',
    -- Portal access (client can view their invoices)
    `portal_token`   VARCHAR(100)    DEFAULT NULL UNIQUE,
    `notes`          TEXT            DEFAULT NULL,
    `active`         TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`state_id`)    REFERENCES `indian_states`(`id`) ON DELETE SET NULL,
    INDEX `clients_business_id` (`business_id`),
    INDEX `clients_gstin`       (`gstin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Client Contacts ──────────────────────────────────────────
-- Multiple contacts per client (purchase manager, accounts, owner, etc.)

CREATE TABLE IF NOT EXISTS `client_contacts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `client_id`    BIGINT UNSIGNED NOT NULL,
    `business_id`  BIGINT UNSIGNED NOT NULL,
    `name`         VARCHAR(255)    NOT NULL,
    `designation`  VARCHAR(100)    DEFAULT NULL COMMENT 'Owner, Accounts Manager, Purchase Manager...',
    `email`        VARCHAR(255)    DEFAULT NULL,
    `mobile`       VARCHAR(15)     DEFAULT NULL,
    `phone`        VARCHAR(15)     DEFAULT NULL,
    `whatsapp`     VARCHAR(15)     DEFAULT NULL COMMENT 'WhatsApp number if different from mobile',
    `is_primary`   TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Primary contact for invoices/reminders',
    `send_invoice` TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Send invoice copies to this contact',
    `notes`        VARCHAR(500)    DEFAULT NULL,
    `active`       TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`)   REFERENCES `clients`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    INDEX `cc_client_id`   (`client_id`),
    INDEX `cc_business_id` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Number Sequences (per business, per FY) ───────────────────

CREATE TABLE IF NOT EXISTS `sequences` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  BIGINT UNSIGNED NOT NULL,
    `type`         VARCHAR(20)     NOT NULL COMMENT 'invoice|quote|credit_note|debit_note',
    `financial_year` CHAR(7)       NOT NULL COMMENT '2024-25, 2025-26 etc.',
    `prefix`       VARCHAR(30)     NOT NULL DEFAULT '',
    `next_number`  BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `padding`      TINYINT         NOT NULL DEFAULT 4,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `seq_biz_type_fy` (`business_id`, `type`, `financial_year`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Quotes / Proforma Invoices ────────────────────────────────

CREATE TABLE IF NOT EXISTS `quotes` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`     BIGINT UNSIGNED NOT NULL,
    `created_by`      BIGINT UNSIGNED NOT NULL,
    `client_id`       BIGINT UNSIGNED NOT NULL,
    `number`          VARCHAR(50)     NOT NULL,
    `type`            ENUM('quote','proforma') NOT NULL DEFAULT 'quote',
    `status`          ENUM('draft','sent','accepted','declined','expired','converted') NOT NULL DEFAULT 'draft',
    `issue_date`      DATE            NOT NULL,
    `valid_until`     DATE            DEFAULT NULL,
    `financial_year`  CHAR(7)         NOT NULL COMMENT '2024-25',
    -- GST fields
    `supply_type`     ENUM('intra','inter') NOT NULL DEFAULT 'intra'
                          COMMENT 'intra=CGST+SGST, inter=IGST',
    `place_of_supply` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'indian_states.id',
    -- Amounts (INR)
    `subtotal`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `cgst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `sgst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `igst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `utgst_total`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `tax_total`       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `discount`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `total`           DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    -- Meta
    `notes`           TEXT            DEFAULT NULL,
    `terms`           TEXT            DEFAULT NULL,
    `sent_at`         TIMESTAMP       NULL DEFAULT NULL,
    `accepted_at`     TIMESTAMP       NULL DEFAULT NULL,
    `converted_at`    TIMESTAMP       NULL DEFAULT NULL COMMENT 'when converted to invoice',
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `quote_biz_number` (`business_id`, `number`),
    FOREIGN KEY (`business_id`)     REFERENCES `businesses`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`client_id`)       REFERENCES `clients`(`id`)       ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`)      REFERENCES `users`(`id`)         ON DELETE RESTRICT,
    FOREIGN KEY (`place_of_supply`) REFERENCES `indian_states`(`id`) ON DELETE RESTRICT,
    INDEX `quotes_business_id` (`business_id`),
    INDEX `quotes_client_id`   (`client_id`),
    INDEX `quotes_status`      (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Quote Items ───────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `quote_items` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `quote_id`     BIGINT UNSIGNED NOT NULL,
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
    FOREIGN KEY (`quote_id`)   REFERENCES `quotes`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Invoices ──────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `invoices` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`     BIGINT UNSIGNED NOT NULL,
    `created_by`      BIGINT UNSIGNED NOT NULL,
    `client_id`       BIGINT UNSIGNED NOT NULL,
    `quote_id`        BIGINT UNSIGNED DEFAULT NULL,
    `number`          VARCHAR(50)     NOT NULL,
    `invoice_type`    ENUM('tax_invoice','bill_of_supply','export','retail') NOT NULL DEFAULT 'tax_invoice'
                          COMMENT 'tax_invoice=GST registered, bill_of_supply=unregistered/exempt',
    `status`          ENUM('draft','sent','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    `issue_date`      DATE            NOT NULL,
    `due_date`        DATE            NOT NULL,
    `financial_year`  CHAR(7)         NOT NULL,
    -- GST details
    `supply_type`     ENUM('intra','inter','export') NOT NULL DEFAULT 'intra',
    `place_of_supply` SMALLINT UNSIGNED DEFAULT NULL,
    `reverse_charge`  TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Reverse charge mechanism',
    -- Amounts
    `subtotal`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `cgst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `sgst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `igst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `utgst_total`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `tax_total`       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `discount`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `round_off`       DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `total`           DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `amount_paid`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `amount_due`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    -- Recurring
    `is_recurring`    TINYINT(1)      NOT NULL DEFAULT 0,
    `recur_every`     TINYINT UNSIGNED DEFAULT NULL,
    `recur_period`    ENUM('week','month','quarter','year') DEFAULT NULL,
    `recur_ends_at`   DATE            DEFAULT NULL,
    `next_recur_date` DATE            DEFAULT NULL,
    -- e-Invoice (for businesses >₹5cr turnover)
    `irn`             VARCHAR(64)     DEFAULT NULL COMMENT 'Invoice Reference Number',
    `irn_generated_at` TIMESTAMP      NULL DEFAULT NULL,
    `qr_code`         TEXT            DEFAULT NULL COMMENT 'e-Invoice QR code data',
    `ack_no`          VARCHAR(20)     DEFAULT NULL,
    `ack_date`        TIMESTAMP       NULL DEFAULT NULL,
    -- e-Way Bill
    `ewaybill_no`     VARCHAR(20)     DEFAULT NULL,
    `ewaybill_date`   TIMESTAMP       NULL DEFAULT NULL,
    -- Meta
    `notes`           TEXT            DEFAULT NULL,
    `terms`           TEXT            DEFAULT NULL,
    `sent_at`         TIMESTAMP       NULL DEFAULT NULL,
    `paid_at`         TIMESTAMP       NULL DEFAULT NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `inv_biz_number` (`business_id`, `number`),
    FOREIGN KEY (`business_id`)     REFERENCES `businesses`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`client_id`)       REFERENCES `clients`(`id`)       ON DELETE RESTRICT,
    FOREIGN KEY (`quote_id`)        REFERENCES `quotes`(`id`)        ON DELETE SET NULL,
    FOREIGN KEY (`created_by`)      REFERENCES `users`(`id`)         ON DELETE RESTRICT,
    FOREIGN KEY (`place_of_supply`) REFERENCES `indian_states`(`id`) ON DELETE RESTRICT,
    INDEX `inv_business_id` (`business_id`),
    INDEX `inv_client_id`   (`client_id`),
    INDEX `inv_status`      (`status`),
    INDEX `inv_due_date`    (`due_date`),
    INDEX `inv_fy`          (`financial_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Invoice Items ─────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `invoice_id`   BIGINT UNSIGNED NOT NULL,
    `product_id`   BIGINT UNSIGNED DEFAULT NULL,
    `description`  TEXT            NOT NULL,
    `hsn_sac`      VARCHAR(10)     DEFAULT NULL COMMENT 'Mandatory for GST invoices',
    `unit`         VARCHAR(20)     DEFAULT 'Nos',
    `quantity`     DECIMAL(15,3)   NOT NULL DEFAULT 1.000,
    `unit_price`   DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `discount_pct` DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `discount_amt` DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `taxable_amt`  DECIMAL(15,2)   NOT NULL DEFAULT 0.00 COMMENT 'after discount',
    `gst_rate`     DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `cgst_rate`    DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `sgst_rate`    DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `igst_rate`    DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `utgst_rate`   DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `cgst_amt`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `sgst_amt`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `igst_amt`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `utgst_amt`    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `total`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `sort_order`   SMALLINT        NOT NULL DEFAULT 0,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Payments Received ─────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `payments` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   BIGINT UNSIGNED NOT NULL,
    `invoice_id`    BIGINT UNSIGNED NOT NULL,
    `client_id`     BIGINT UNSIGNED NOT NULL,
    `recorded_by`   BIGINT UNSIGNED DEFAULT NULL,
    `amount`        DECIMAL(15,2)   NOT NULL,
    `method`        ENUM('cash','upi','neft','rtgs','imps','cheque','card','netbanking','other')
                        NOT NULL DEFAULT 'cash',
    `reference`     VARCHAR(255)    DEFAULT NULL COMMENT 'UTR, cheque no, UPI ref, txn ID',
    `utr_number`    VARCHAR(50)     DEFAULT NULL COMMENT 'Unique Transaction Reference for NEFT/RTGS',
    `payment_date`  DATE            NOT NULL,
    `note`          TEXT            DEFAULT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invoice_id`)  REFERENCES `invoices`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`client_id`)   REFERENCES `clients`(`id`)    ON DELETE RESTRICT,
    INDEX `pay_business_id` (`business_id`),
    INDEX `pay_invoice_id`  (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Credit Notes ──────────────────────────────────────────────
-- Issued when goods are returned or invoice is corrected downward

CREATE TABLE IF NOT EXISTS `credit_notes` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`     BIGINT UNSIGNED NOT NULL,
    `created_by`      BIGINT UNSIGNED NOT NULL,
    `invoice_id`      BIGINT UNSIGNED NOT NULL COMMENT 'original invoice',
    `client_id`       BIGINT UNSIGNED NOT NULL,
    `number`          VARCHAR(50)     NOT NULL,
    `reason`          ENUM('return','discount','correction','other') NOT NULL DEFAULT 'return',
    `issue_date`      DATE            NOT NULL,
    `supply_type`     ENUM('intra','inter') NOT NULL DEFAULT 'intra',
    `place_of_supply` SMALLINT UNSIGNED DEFAULT NULL,
    `subtotal`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `cgst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `sgst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `igst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `tax_total`       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `total`           DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `status`          ENUM('draft','issued','adjusted','refunded') NOT NULL DEFAULT 'draft',
    `notes`           TEXT            DEFAULT NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `cn_biz_number` (`business_id`, `number`),
    FOREIGN KEY (`business_id`)     REFERENCES `businesses`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`invoice_id`)      REFERENCES `invoices`(`id`)      ON DELETE RESTRICT,
    FOREIGN KEY (`client_id`)       REFERENCES `clients`(`id`)       ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`)      REFERENCES `users`(`id`)         ON DELETE RESTRICT,
    FOREIGN KEY (`place_of_supply`) REFERENCES `indian_states`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Credit Note Items ─────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `credit_note_items` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `credit_note_id` BIGINT UNSIGNED NOT NULL,
    `product_id`     BIGINT UNSIGNED DEFAULT NULL,
    `description`    TEXT            NOT NULL,
    `hsn_sac`        VARCHAR(10)     DEFAULT NULL,
    `unit`           VARCHAR(20)     DEFAULT 'Nos',
    `quantity`       DECIMAL(15,3)   NOT NULL DEFAULT 1.000,
    `unit_price`     DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `taxable_amt`    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `gst_rate`       DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `cgst_amt`       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `sgst_amt`       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `igst_amt`       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `total`          DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    FOREIGN KEY (`credit_note_id`) REFERENCES `credit_notes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`)     REFERENCES `products`(`id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Debit Notes ───────────────────────────────────────────────
-- Issued when additional amount is to be charged (price increase, shortage)

CREATE TABLE IF NOT EXISTS `debit_notes` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`     BIGINT UNSIGNED NOT NULL,
    `created_by`      BIGINT UNSIGNED NOT NULL,
    `invoice_id`      BIGINT UNSIGNED NOT NULL,
    `client_id`       BIGINT UNSIGNED NOT NULL,
    `number`          VARCHAR(50)     NOT NULL,
    `reason`          ENUM('price_revision','shortage','other') NOT NULL DEFAULT 'other',
    `issue_date`      DATE            NOT NULL,
    `supply_type`     ENUM('intra','inter') NOT NULL DEFAULT 'intra',
    `place_of_supply` SMALLINT UNSIGNED DEFAULT NULL,
    `subtotal`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `cgst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `sgst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `igst_total`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `tax_total`       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `total`           DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `status`          ENUM('draft','issued') NOT NULL DEFAULT 'draft',
    `notes`           TEXT            DEFAULT NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `dn_biz_number` (`business_id`, `number`),
    FOREIGN KEY (`business_id`)     REFERENCES `businesses`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`invoice_id`)      REFERENCES `invoices`(`id`)      ON DELETE RESTRICT,
    FOREIGN KEY (`client_id`)       REFERENCES `clients`(`id`)       ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`)      REFERENCES `users`(`id`)         ON DELETE RESTRICT,
    FOREIGN KEY (`place_of_supply`) REFERENCES `indian_states`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Expenses ──────────────────────────────────────────────────
-- Track business expenses for P&L reporting

CREATE TABLE IF NOT EXISTS `expense_categories` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  BIGINT UNSIGNED NOT NULL,
    `name`         VARCHAR(100)    NOT NULL COMMENT 'Rent, Salary, Purchases, Travel...',
    `sort_order`   SMALLINT        NOT NULL DEFAULT 0,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenses` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   BIGINT UNSIGNED NOT NULL,
    `category_id`   BIGINT UNSIGNED DEFAULT NULL,
    `recorded_by`   BIGINT UNSIGNED DEFAULT NULL,
    `vendor_name`   VARCHAR(255)    DEFAULT NULL,
    `description`   TEXT            NOT NULL,
    `amount`        DECIMAL(15,2)   NOT NULL,
    `gst_amount`    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `total_amount`  DECIMAL(15,2)   NOT NULL,
    `expense_date`  DATE            NOT NULL,
    `method`        ENUM('cash','upi','neft','rtgs','card','cheque','other') DEFAULT 'cash',
    `reference`     VARCHAR(255)    DEFAULT NULL,
    `receipt`       VARCHAR(500)    DEFAULT NULL COMMENT 'file path',
    `financial_year` CHAR(7)        NOT NULL,
    `notes`         TEXT            DEFAULT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`)  REFERENCES `businesses`(`id`)          ON DELETE CASCADE,
    FOREIGN KEY (`category_id`)  REFERENCES `expense_categories`(`id`)  ON DELETE SET NULL,
    INDEX `exp_business_id`  (`business_id`),
    INDEX `exp_date`         (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Business Settings ─────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `settings` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  BIGINT UNSIGNED NOT NULL,
    `key`          VARCHAR(100)    NOT NULL,
    `value`        TEXT            DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `settings_biz_key` (`business_id`, `key`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Media / File Attachments ──────────────────────────────────

CREATE TABLE IF NOT EXISTS `media` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   BIGINT UNSIGNED NOT NULL,
    `uploaded_by`   BIGINT UNSIGNED DEFAULT NULL,
    `model_type`    VARCHAR(50)     DEFAULT NULL COMMENT 'invoice|client|expense|business',
    `model_id`      BIGINT UNSIGNED DEFAULT NULL,
    `disk`          ENUM('local','ftp') NOT NULL DEFAULT 'local',
    `path`          VARCHAR(1000)   NOT NULL,
    `filename`      VARCHAR(255)    NOT NULL,
    `original_name` VARCHAR(255)    DEFAULT NULL,
    `mime_type`     VARCHAR(100)    DEFAULT NULL,
    `size`          BIGINT UNSIGNED DEFAULT NULL COMMENT 'bytes',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    INDEX `media_business_id` (`business_id`),
    INDEX `media_model`       (`model_type`, `model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Activity / Audit Log ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  BIGINT UNSIGNED NOT NULL,
    `user_id`      BIGINT UNSIGNED DEFAULT NULL,
    `action`       VARCHAR(100)    NOT NULL COMMENT 'invoice.created|payment.recorded|client.added',
    `model_type`   VARCHAR(50)     DEFAULT NULL,
    `model_id`     BIGINT UNSIGNED DEFAULT NULL,
    `description`  TEXT            DEFAULT NULL,
    `ip_address`   VARCHAR(45)     DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    INDEX `al_business_id` (`business_id`),
    INDEX `al_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notifications ─────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `notifications` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`  BIGINT UNSIGNED NOT NULL,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `type`         VARCHAR(100)    NOT NULL COMMENT 'payment_received|invoice_overdue|trial_ending',
    `title`        VARCHAR(255)    NOT NULL,
    `message`      TEXT            DEFAULT NULL,
    `data`         JSON            DEFAULT NULL,
    `read_at`      TIMESTAMP       NULL DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)      ON DELETE CASCADE,
    INDEX `notif_user_id`  (`user_id`),
    INDEX `notif_read_at`  (`read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
