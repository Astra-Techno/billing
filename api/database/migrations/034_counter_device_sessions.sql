-- Upgrade existing multi-counter installations with short-lived browser sessions.
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
