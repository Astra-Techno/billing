-- Walk-in / retail invoices have no client; payments must allow NULL client_id too.

SET @fk = (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'client_id' AND REFERENCED_TABLE_NAME = 'clients' LIMIT 1);
SET @drop_sql = IF(@fk IS NOT NULL, CONCAT('ALTER TABLE payments DROP FOREIGN KEY `', @fk, '`'), 'DO 1');
PREPARE s FROM @drop_sql;
EXECUTE s;
DEALLOCATE PREPARE s;

ALTER TABLE payments MODIFY client_id BIGINT UNSIGNED NULL DEFAULT NULL;

SET @exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND CONSTRAINT_NAME = 'payments_client_fk' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
SET @add_sql = IF(@exists = 0, 'ALTER TABLE payments ADD CONSTRAINT payments_client_fk FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL', 'DO 1');
PREPARE s FROM @add_sql;
EXECUTE s;
DEALLOCATE PREPARE s;
