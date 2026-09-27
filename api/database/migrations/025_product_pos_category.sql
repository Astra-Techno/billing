SET @db = DATABASE();
SELECT COUNT(*) INTO @col FROM information_schema.columns WHERE table_schema=@db AND table_name='products' AND column_name='pos_category';
SET @q = IF(@col=0, "ALTER TABLE products ADD COLUMN pos_category VARCHAR(100) NULL COMMENT 'Simple cashier-facing POS category' AFTER description", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;

SELECT COUNT(*) INTO @idx FROM information_schema.statistics WHERE table_schema=@db AND table_name='products' AND index_name='products_pos_category_index';
SET @q = IF(@idx=0, "CREATE INDEX products_pos_category_index ON products (business_id, pos_category)", 'SELECT 1');
PREPARE st FROM @q; EXECUTE st; DEALLOCATE PREPARE st;
