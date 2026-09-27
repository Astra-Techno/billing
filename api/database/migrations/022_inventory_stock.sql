ALTER TABLE businesses ADD COLUMN inventory_mode ENUM('none','warn','strict') NOT NULL DEFAULT 'none' AFTER business_type;
ALTER TABLE businesses ADD COLUMN inventory_advanced TINYINT(1) NOT NULL DEFAULT 0 AFTER inventory_mode;

ALTER TABLE products ADD COLUMN track_stock TINYINT(1) NOT NULL DEFAULT 0 AFTER type;
ALTER TABLE products ADD COLUMN purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER price;
ALTER TABLE products ADD COLUMN mrp DECIMAL(15,2) NULL AFTER purchase_price;
ALTER TABLE products ADD COLUMN reorder_level DECIMAL(15,3) NOT NULL DEFAULT 0 AFTER mrp;
ALTER TABLE products ADD COLUMN barcode VARCHAR(100) NULL AFTER sku;
ALTER TABLE products ADD COLUMN base_unit VARCHAR(30) NULL AFTER unit;
ALTER TABLE products ADD COLUMN conversion_factor DECIMAL(15,4) NOT NULL DEFAULT 1 AFTER base_unit;
ALTER TABLE products ADD COLUMN batch_tracking TINYINT(1) NOT NULL DEFAULT 0 AFTER conversion_factor;
ALTER TABLE products ADD COLUMN expiry_tracking TINYINT(1) NOT NULL DEFAULT 0 AFTER batch_tracking;
ALTER TABLE products ADD COLUMN serial_tracking TINYINT(1) NOT NULL DEFAULT 0 AFTER expiry_tracking;

CREATE TABLE inventory_locations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  type ENUM('shop','godown','damaged') NOT NULL DEFAULT 'shop',
  address VARCHAR(500) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inventory_location_name (business_id, name),
  INDEX idx_inventory_location_business (business_id, active),
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_balances (
  business_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
  reserved_quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
  average_cost DECIMAL(15,4) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (business_id, location_id, product_id),
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('opening','purchase','sale','sale_return','purchase_return','transfer_out','transfer_in','adjustment','damage','count') NOT NULL,
  quantity DECIMAL(15,3) NOT NULL COMMENT 'Positive is stock in; negative is stock out',
  unit_cost DECIMAL(15,4) NOT NULL DEFAULT 0,
  balance_after DECIMAL(15,3) NOT NULL,
  reference_type VARCHAR(40) NULL,
  reference_id BIGINT UNSIGNED NULL,
  reversal_of_id BIGINT UNSIGNED NULL,
  batch_no VARCHAR(100) NULL,
  expiry_date DATE NULL,
  serial_numbers TEXT NULL,
  note VARCHAR(500) NULL,
  occurred_at DATETIME NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_stock_movement_lookup (business_id, product_id, location_id, occurred_at),
  INDEX idx_stock_movement_reference (business_id, reference_type, reference_id),
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  FOREIGN KEY (location_id) REFERENCES inventory_locations(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (reversal_of_id) REFERENCES stock_movements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_transfers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  transfer_no VARCHAR(50) NOT NULL,
  from_location_id BIGINT UNSIGNED NOT NULL,
  to_location_id BIGINT UNSIGNED NOT NULL,
  status ENUM('draft','dispatched','received','cancelled') NOT NULL DEFAULT 'draft',
  transfer_date DATE NOT NULL,
  notes VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  received_by BIGINT UNSIGNED NULL,
  dispatched_at DATETIME NULL,
  received_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_stock_transfer_no (business_id, transfer_no),
  FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  FOREIGN KEY (from_location_id) REFERENCES inventory_locations(id),
  FOREIGN KEY (to_location_id) REFERENCES inventory_locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_transfer_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transfer_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(15,3) NOT NULL,
  received_quantity DECIMAL(15,3) NULL,
  batch_no VARCHAR(100) NULL,
  expiry_date DATE NULL,
  serial_numbers TEXT NULL,
  FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE invoices ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER business_id;
ALTER TABLE invoices ADD CONSTRAINT invoices_location_fk FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL;
ALTER TABLE purchase_orders ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER business_id;
ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_location_fk FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL;

INSERT INTO inventory_locations (business_id, name, type, is_default)
SELECT id, 'Main Shop', 'shop', 1 FROM businesses;
