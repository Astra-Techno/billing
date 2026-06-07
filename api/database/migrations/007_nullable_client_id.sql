-- Make client_id nullable on invoices (allow invoices without a client)
ALTER TABLE invoices MODIFY client_id INT UNSIGNED NULL DEFAULT NULL;

-- Drop the existing foreign key first, then re-add with ON DELETE SET NULL
ALTER TABLE invoices DROP FOREIGN KEY invoices_ibfk_2;
ALTER TABLE invoices ADD CONSTRAINT invoices_ibfk_2 FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL;
