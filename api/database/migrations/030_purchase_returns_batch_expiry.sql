-- Purchase returns and batch/expiry tracking
-- Run: mysql -u root billing < api/database/migrations/030_purchase_returns_batch_expiry.sql

-- Batch and expiry tracking on purchase items
ALTER TABLE purchase_invoice_items ADD COLUMN batch_no VARCHAR(100) NULL AFTER sort_order;
ALTER TABLE purchase_invoice_items ADD COLUMN expiry_date DATE NULL AFTER batch_no;

ALTER TABLE purchase_order_items ADD COLUMN batch_no VARCHAR(100) NULL AFTER sort_order;
ALTER TABLE purchase_order_items ADD COLUMN expiry_date DATE NULL AFTER batch_no;

-- Purchase Returns (returns to suppliers, reduces amount_due on PI)
CREATE TABLE IF NOT EXISTS purchase_returns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    pi_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NULL,
    number VARCHAR(50) NOT NULL,
    reason ENUM('defective','expired','excess','wrong_item','other') NOT NULL DEFAULT 'other',
    return_date DATE NOT NULL,
    supply_type ENUM('intra','inter') NOT NULL DEFAULT 'intra',
    place_of_supply INT UNSIGNED NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    cgst_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    sgst_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    igst_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('draft','issued','adjusted') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pr_business (business_id),
    INDEX idx_pr_pi (pi_id),
    INDEX idx_pr_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_return_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pr_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    hsn_sac VARCHAR(20) NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'Nos',
    quantity DECIMAL(10,3) NOT NULL DEFAULT 1,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    taxable_amt DECIMAL(14,2) NOT NULL DEFAULT 0,
    gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    cgst_amt DECIMAL(14,2) NOT NULL DEFAULT 0,
    sgst_amt DECIMAL(14,2) NOT NULL DEFAULT 0,
    igst_amt DECIMAL(14,2) NOT NULL DEFAULT 0,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    batch_no VARCHAR(100) NULL,
    expiry_date DATE NULL,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_pri_pr (pr_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
