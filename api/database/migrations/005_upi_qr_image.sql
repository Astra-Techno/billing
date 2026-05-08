-- Add UPI QR code image column to businesses.
-- Businesses can upload their bank-generated QR code as an alternative
-- to (or alongside) entering a plain UPI ID.

ALTER TABLE `businesses`
    ADD COLUMN `upi_qr_image` MEDIUMTEXT NULL DEFAULT NULL
        COMMENT 'Base64 data-URL of uploaded UPI QR code image'
    AFTER `upi_id`;
