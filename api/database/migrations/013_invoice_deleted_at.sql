-- Soft-delete support for invoices (hide from UI, keep record in DB)

ALTER TABLE `invoices`
    ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Set when invoice is deleted (hidden from lists)'
    AFTER `updated_at`;

ALTER TABLE `invoices`
    ADD INDEX `inv_deleted_at` (`deleted_at`);
