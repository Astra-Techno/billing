ALTER TABLE `products`
    ADD COLUMN `pos_category` VARCHAR(100) NULL
    COMMENT 'Simple cashier-facing POS category'
    AFTER `description`;

CREATE INDEX `products_pos_category_index`
    ON `products` (`business_id`, `pos_category`);
