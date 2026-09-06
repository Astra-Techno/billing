-- Invoice-level discount: store type (percent/amount) and value on the invoice header.

ALTER TABLE invoices
  ADD COLUMN discount_type  VARCHAR(10) NOT NULL DEFAULT 'percent' AFTER tax_total,
  ADD COLUMN discount_value DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER discount_type;
