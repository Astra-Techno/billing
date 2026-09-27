SET @db = DATABASE();

-- businesses columns
SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='businesses' AND column_name='inventory_mode';
SET @q = IF(@col=0, "ALTER TABLE businesses ADD COLUMN inventory_mode ENUM('none','warn','strict') NOT NULL DEFAULT 'none' AFTER business_type", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='businesses' AND column_name='inventory_advanced';
SET @q = IF(@col=0, "ALTER TABLE businesses ADD COLUMN inventory_advanced TINYINT(1) NOT NULL DEFAULT 0 AFTER inventory_mode", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

-- products columns
SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='products' AND column_name='track_stock';
SET @q = IF(@col=0, "ALTER TABLE products ADD COLUMN track_stock TINYINT(1) NOT NULL DEFAULT 0 AFTER type", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='products' AND column_name='purchase_price';
SET @q = IF(@col=0, "ALTER TABLE products ADD COLUMN purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER price", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='products' AND column_name='mrp';
SET @q = IF(@col=0, "ALTER TABLE products ADD COLUMN mrp DECIMAL(15,2) NULL AFTER purchase_price", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='products' AND column_name='reorder_level';
SET @q = IF(@col=0, "ALTER TABLE products ADD COLUMN reorder_level DECIMAL(15,3) NOT NULL DEFAULT 0 AFTER mrp", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='products' AND column_name='barcode';
SET @q = IF(@col=0, "ALTER TABLE products ADD COLUMN barcode VARCHAR(100) NULL AFTER sku", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='products' AND column_name='base_unit';
SET @q = IF(@col=0, "ALTER TABLE products ADD COLUMN base_unit VARCHAR(30) NULL AFTER unit", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='products' AND column_name='conversion_factor';
SET @q = IF(@col=0, "ALTER TABLE products ADD COLUMN conversion_factor DECIMAL(15,4) NOT NULL DEFAULT 1 AFTER base_unit", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='products' AND column_name='batch_tracking';
SET @q = IF(@col=0, "ALTER TABLE products ADD COLUMN batch_tracking TINYINT(1) NOT NULL DEFAULT 0 AFTER conversion_factor", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='products' AND column_name='expiry_tracking';
SET @q = IF(@col=0, "ALTER TABLE products ADD COLUMN expiry_tracking TINYINT(1) NOT NULL DEFAULT 0 AFTER batch_tracking", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='products' AND column_name='serial_tracking';
SET @q = IF(@col=0, "ALTER TABLE products ADD COLUMN serial_tracking TINYINT(1) NOT NULL DEFAULT 0 AFTER expiry_tracking", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS inventory_locations (
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

CREATE TABLE IF NOT EXISTS stock_balances (
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

CREATE TABLE IF NOT EXISTS stock_movements (
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

CREATE TABLE IF NOT EXISTS stock_transfers (
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

CREATE TABLE IF NOT EXISTS stock_transfer_items (
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

SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='invoices' AND column_name='location_id';
SET @q = IF(@col=0, "ALTER TABLE invoices ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER business_id", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.statistics WHERE table_schema=@db AND table_name='invoices' AND index_name='invoices_location_fk';
SET @q = IF(@col=0, "ALTER TABLE invoices ADD CONSTRAINT invoices_location_fk FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='purchase_orders' AND column_name='location_id';
SET @q = IF(@col=0, "ALTER TABLE purchase_orders ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER business_id", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @col FROM information_schema.statistics WHERE table_schema=@db AND table_name='purchase_orders' AND index_name='purchase_orders_location_fk';
SET @q = IF(@col=0, "ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_location_fk FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

INSERT IGNORE INTO inventory_locations (business_id, name, type, is_default)
SELECT id, 'Main Shop', 'shop', 1 FROM businesses;
