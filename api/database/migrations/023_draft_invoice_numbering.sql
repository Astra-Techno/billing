-- Draft invoices are working documents and must not consume statutory numbers.
ALTER TABLE `businesses`
    ADD COLUMN `draft_invoice_number_enabled` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Assign official number while invoice is still draft'
    AFTER `quote_prefix`;

ALTER TABLE `invoices`
    MODIFY COLUMN `number` VARCHAR(50) NULL DEFAULT NULL
    COMMENT 'Assigned only when invoice is finalized';

-- Clear old draft numbers without changing issued invoices or audit history.
UPDATE `invoices` i
INNER JOIN `businesses` b ON b.id = i.business_id
SET i.`number` = NULL
WHERE i.`status` = 'draft' AND b.`draft_invoice_number_enabled` = 0;
