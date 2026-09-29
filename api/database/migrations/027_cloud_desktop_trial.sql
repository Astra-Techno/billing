-- Cloud-only: add trial support to desktop licensing.
-- Local/desktop migration runners must record this filename without executing it.

ALTER TABLE desktop_activation_requests
  ADD COLUMN `type` ENUM('trial','activation') NOT NULL DEFAULT 'activation' AFTER `device_hmac`,
  ADD COLUMN `requested_years` TINYINT UNSIGNED NULL AFTER `type`;

ALTER TABLE desktop_licenses
  ADD COLUMN `license_type` ENUM('trial','paid') NOT NULL DEFAULT 'paid' AFTER `edition`;
