-- Keep stored invoice types aligned with the Bill Type options shown in the UI.
ALTER TABLE `invoices`
    MODIFY COLUMN `invoice_type`
    ENUM('tax_invoice','bill_of_supply','export','retail','proforma')
    NOT NULL DEFAULT 'tax_invoice'
    COMMENT 'Determines the heading and tax presentation used in print output';
