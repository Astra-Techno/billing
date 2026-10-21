ALTER TABLE products
  ADD COLUMN price_includes_tax TINYINT(1) NOT NULL DEFAULT 0 AFTER price;
