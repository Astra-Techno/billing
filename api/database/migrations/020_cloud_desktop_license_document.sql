-- Cloud-only signed licence document storage, added after migration 019 was deployed.
ALTER TABLE `desktop_licenses`
    ADD COLUMN `license_document` MEDIUMTEXT NULL AFTER `license_document_hash`;

