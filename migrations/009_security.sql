-- Phase 1E-b: two-step sign-in (authenticator app + email code), backup codes, trusted devices, signed-in devices list.

-- One row per person who has set up at least one second step.
-- totp_secret_enc: the authenticator secret, encrypted with APP_KEY (AES-256-GCM). last_totp_step stops a code being used twice.
CREATE TABLE IF NOT EXISTS user_2fa (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  totp_secret_enc VARCHAR(255) NULL,
  totp_enabled_at DATETIME NULL,
  last_totp_step BIGINT UNSIGNED NOT NULL DEFAULT 0,
  email_enabled_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One-time backup codes (only a keyed hash is stored).
CREATE TABLE IF NOT EXISTS user_backup_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id, used_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Codes sent by email. purpose: login | setup. At most one live code per person and purpose.
CREATE TABLE IF NOT EXISTS twofa_email_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  purpose VARCHAR(10) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_purpose (user_id, purpose, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Browsers that may skip the second step for a while. Cookie = "<id>.<token>", only a SHA-256 of the token is stored.
CREATE TABLE IF NOT EXISTS trusted_devices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  label VARCHAR(120) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  INDEX idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every signed-in browser. The session holds a random token; deleting the row signs that browser out.
CREATE TABLE IF NOT EXISTS user_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  label VARCHAR(120) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL,
  UNIQUE KEY uq_token (token_hash),
  INDEX idx_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (k, v, grp) VALUES
  ('twofa_enabled', '1', 'twofa'),
  ('twofa_allow_totp', '1', 'twofa'),
  ('twofa_allow_email', '1', 'twofa'),
  ('twofa_require_tech_admin', '0', 'twofa'),
  ('twofa_require_moderator', '0', 'twofa'),
  ('twofa_require_member', '0', 'twofa'),
  ('twofa_trust_days', '30', 'twofa'),
  ('twofa_code_minutes', '10', 'twofa'),
  ('twofa_backup_count', '10', 'twofa'),
  ('security_alert_email', '1', 'twofa');
