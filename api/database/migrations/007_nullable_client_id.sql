-- Drop any existing FK on client_id (name varies per server)
SET @fk = (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'client_id' AND REFERENCED_TABLE_NAME = 'clients' LIMIT 1);
SET @drop_sql = IF(@fk IS NOT NULL, CONCAT('ALTER TABLE invoices DROP FOREIGN KEY `', @fk, '`'), 'DO 1');
PREPARE s FROM @drop_sql;
EXECUTE s;
DEALLOCATE PREPARE s;

-- Make client_id nullable — must match clients.id type exactly (BIGINT UNSIGNED)
ALTER TABLE invoices MODIFY client_id BIGINT UNSIGNED NULL DEFAULT NULL;

-- Re-add FK with ON DELETE SET NULL (skip if already exists)
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND CONSTRAINT_NAME = 'invoices_client_fk' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @add_sql = IF(@exists = 0, 'ALTER TABLE invoices ADD CONSTRAINT invoices_client_fk FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL', 'DO 1');
PREPARE s FROM @add_sql;
EXECUTE s;
DEALLOCATE PREPARE s;
