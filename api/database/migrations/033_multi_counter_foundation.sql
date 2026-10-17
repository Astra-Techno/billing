-- Local/LAN multi-counter foundation. These tables are shared by cloud and desktop
-- schemas so backups can move safely between editions.

CREATE TABLE IF NOT EXISTS billing_counters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NULL,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(100) NOT NULL,
  invoice_prefix VARCHAR(20) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY billing_counter_code_unique (business_id, code),
  KEY billing_counter_location_index (business_id, location_id, active),
  CONSTRAINT billing_counter_business_fk FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT billing_counter_location_fk FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL,
  CONSTRAINT billing_counter_creator_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  counter_id BIGINT UNSIGNED NULL,
  device_uuid CHAR(36) NOT NULL,
  device_fingerprint_hash CHAR(64) NOT NULL,
  device_name VARCHAR(120) NOT NULL,
  device_type ENUM('main','counter','manager') NOT NULL DEFAULT 'counter',
  platform VARCHAR(80) NULL,
  app_version VARCHAR(40) NULL,
  credential_hash CHAR(64) NULL,
  status ENUM('pending','active','inactive','revoked') NOT NULL DEFAULT 'pending',
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  last_seen_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY business_device_uuid_unique (business_id, device_uuid),
  KEY business_device_status_index (business_id, status, last_seen_at),
  CONSTRAINT business_device_business_fk FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT business_device_counter_fk FOREIGN KEY (counter_id) REFERENCES billing_counters(id) ON DELETE SET NULL,
  CONSTRAINT business_device_approver_fk FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS counter_pairing_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  request_uuid CHAR(36) NOT NULL,
  business_id BIGINT UNSIGNED NOT NULL,
  pairing_code_hash CHAR(64) NOT NULL,
  request_secret_hash CHAR(64) NULL,
  status ENUM('open','pending','approved','rejected','expired','consumed') NOT NULL DEFAULT 'open',
  requested_device_id BIGINT UNSIGNED NULL,
  expires_at DATETIME NOT NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  consumed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY counter_pairing_uuid_unique (request_uuid),
  KEY counter_pairing_business_status_index (business_id, status, expires_at),
  CONSTRAINT counter_pairing_business_fk FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT counter_pairing_device_fk FOREIGN KEY (requested_device_id) REFERENCES business_devices(id) ON DELETE SET NULL,
  CONSTRAINT counter_pairing_approver_fk FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS device_audit_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_id BIGINT UNSIGNED NOT NULL,
  device_id BIGINT UNSIGNED NULL,
  action VARCHAR(50) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  metadata JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY device_audit_business_index (business_id, created_at),
  KEY device_audit_device_index (device_id, created_at),
  CONSTRAINT device_audit_business_fk FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT device_audit_device_fk FOREIGN KEY (device_id) REFERENCES business_devices(id) ON DELETE SET NULL,
  CONSTRAINT device_audit_actor_fk FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS device_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  device_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  last_seen_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY device_session_token_unique (token_hash),
  KEY device_session_expiry_index (device_id, expires_at),
  CONSTRAINT device_session_device_fk FOREIGN KEY (device_id) REFERENCES business_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE invoices ADD COLUMN counter_id BIGINT UNSIGNED NULL AFTER location_id;
ALTER TABLE invoices ADD COLUMN device_id BIGINT UNSIGNED NULL AFTER counter_id;
ALTER TABLE invoices ADD CONSTRAINT invoices_counter_fk FOREIGN KEY (counter_id) REFERENCES billing_counters(id) ON DELETE SET NULL;
ALTER TABLE invoices ADD CONSTRAINT invoices_device_fk FOREIGN KEY (device_id) REFERENCES business_devices(id) ON DELETE SET NULL;
ALTER TABLE invoices ADD INDEX invoices_counter_date_index (business_id, counter_id, issue_date);

ALTER TABLE payments ADD COLUMN counter_id BIGINT UNSIGNED NULL AFTER business_id;
ALTER TABLE payments ADD COLUMN device_id BIGINT UNSIGNED NULL AFTER counter_id;
ALTER TABLE payments ADD CONSTRAINT payments_counter_fk FOREIGN KEY (counter_id) REFERENCES billing_counters(id) ON DELETE SET NULL;
ALTER TABLE payments ADD CONSTRAINT payments_device_fk FOREIGN KEY (device_id) REFERENCES business_devices(id) ON DELETE SET NULL;

