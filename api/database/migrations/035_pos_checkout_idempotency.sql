-- A cashier retry after a LAN timeout must return the original sale.
ALTER TABLE invoices ADD COLUMN pos_request_id CHAR(36) NULL AFTER device_id;
ALTER TABLE invoices ADD UNIQUE KEY invoices_pos_request_unique (business_id, pos_request_id);
