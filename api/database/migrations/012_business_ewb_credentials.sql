-- ============================================================
-- Migration 012: Per-business E-way Bill GSP credentials
-- ============================================================
-- All GSP/NIC credentials are stored per-business so that
-- each tenant on the SaaS platform uses their own account.
-- ============================================================

-- ewb_username / ewb_password = business owner's login on ewaybillgst.gov.in
-- GSP app credentials (client_id, client_secret, gsp_url) are platform-level → .env
ALTER TABLE `businesses`
    ADD COLUMN `ewb_username` VARCHAR(100) DEFAULT NULL COMMENT 'NIC EWB portal username (ewaybillgst.gov.in)' AFTER `upi_id`,
    ADD COLUMN `ewb_password` VARCHAR(255) DEFAULT NULL COMMENT 'NIC EWB portal password'                      AFTER `ewb_username`;
