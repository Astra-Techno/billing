CREATE TABLE IF NOT EXISTS product_locations (
  product_id  BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  price       DECIMAL(15,2)   NULL COMMENT 'Shop-specific price override, NULL = use product default',
  PRIMARY KEY (product_id, location_id),
  FOREIGN KEY (product_id)  REFERENCES products(id)            ON DELETE CASCADE,
  FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
