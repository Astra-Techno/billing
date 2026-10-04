-- Per-location GST identity (falls back to business-level when NULL)
ALTER TABLE inventory_locations ADD COLUMN gstin VARCHAR(15) NULL AFTER address;
ALTER TABLE inventory_locations ADD COLUMN state_id INT UNSIGNED NULL AFTER gstin;
ALTER TABLE inventory_locations ADD COLUMN city VARCHAR(120) NULL AFTER state_id;
ALTER TABLE inventory_locations ADD COLUMN pincode VARCHAR(10) NULL AFTER city;

-- Store which location GSTIN was used on each invoice
ALTER TABLE invoices ADD COLUMN location_gstin VARCHAR(15) NULL AFTER location_id;
ALTER TABLE invoices ADD COLUMN location_state_id INT UNSIGNED NULL AFTER location_gstin;
