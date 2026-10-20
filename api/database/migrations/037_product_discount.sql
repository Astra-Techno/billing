ALTER TABLE products
  ADD COLUMN discount_type ENUM('percent','amount') NOT NULL DEFAULT 'percent' AFTER price,
  ADD COLUMN discount_value DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER discount_type;
