ALTER TABLE product_locations
  ADD COLUMN tax_rate_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'Shop-specific GST override, NULL = use product default' AFTER price;
