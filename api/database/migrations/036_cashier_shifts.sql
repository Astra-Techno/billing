CREATE TABLE IF NOT EXISTS cashier_shifts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  counter_id BIGINT UNSIGNED NOT NULL,
  device_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NULL,
  cashier_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  opening_cash DECIMAL(14,2) NOT NULL DEFAULT 0,
  invoice_count INT UNSIGNED NOT NULL DEFAULT 0,
  cash_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
  upi_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
  card_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
  other_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
  expected_cash DECIMAL(14,2) NULL,
  counted_cash DECIMAL(14,2) NULL,
  cash_variance DECIMAL(14,2) NULL,
  closing_note VARCHAR(500) NULL,
  opened_at DATETIME NOT NULL,
  closed_at DATETIME NULL,
  closed_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY cashier_shift_open_index (business_id,device_id,status,opened_at),
  KEY cashier_shift_counter_index (business_id,counter_id,opened_at),
  CONSTRAINT cashier_shift_business_fk FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT cashier_shift_counter_fk FOREIGN KEY (counter_id) REFERENCES billing_counters(id) ON DELETE RESTRICT,
  CONSTRAINT cashier_shift_device_fk FOREIGN KEY (device_id) REFERENCES business_devices(id) ON DELETE RESTRICT,
  CONSTRAINT cashier_shift_location_fk FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL,
  CONSTRAINT cashier_shift_cashier_fk FOREIGN KEY (cashier_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT cashier_shift_closer_fk FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE invoices ADD COLUMN shift_id BIGINT UNSIGNED NULL AFTER pos_request_id;
ALTER TABLE invoices ADD COLUMN cashier_user_id BIGINT UNSIGNED NULL AFTER shift_id;
ALTER TABLE invoices ADD CONSTRAINT invoices_shift_fk FOREIGN KEY (shift_id) REFERENCES cashier_shifts(id) ON DELETE SET NULL;
ALTER TABLE invoices ADD CONSTRAINT invoices_cashier_fk FOREIGN KEY (cashier_user_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE invoices ADD INDEX invoices_shift_index (business_id,shift_id,created_at);

ALTER TABLE payments ADD COLUMN shift_id BIGINT UNSIGNED NULL AFTER device_id;
ALTER TABLE payments ADD COLUMN cashier_user_id BIGINT UNSIGNED NULL AFTER shift_id;
ALTER TABLE payments ADD CONSTRAINT payments_shift_fk FOREIGN KEY (shift_id) REFERENCES cashier_shifts(id) ON DELETE SET NULL;
ALTER TABLE payments ADD CONSTRAINT payments_cashier_fk FOREIGN KEY (cashier_user_id) REFERENCES users(id) ON DELETE SET NULL;
