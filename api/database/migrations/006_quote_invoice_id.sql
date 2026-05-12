ALTER TABLE `quotes`
    ADD COLUMN `invoice_id` BIGINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Invoice created when this quote was converted'
    AFTER `converted_at`;
