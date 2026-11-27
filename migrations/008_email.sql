-- Phase 1E-a2: outgoing email (SMTP), email verification, email password reset, notifications, sitemap/feed switches.

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS pref_lang VARCHAR(5) NULL,
  ADD COLUMN IF NOT EXISTS email_notify TINYINT(1) NOT NULL DEFAULT 1;

-- Google already proved the address.
UPDATE users SET email_verified_at = created_at WHERE google_sub IS NOT NULL AND email_verified_at IS NULL;

-- One-time links sent by email. Only a SHA-256 of the token is stored. kind: verify | reset
CREATE TABLE IF NOT EXISTS email_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  kind VARCHAR(20) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  email VARCHAR(190) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_token (token_hash),
  INDEX idx_user_kind (user_id, kind, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- What was sent and whether it worked (no message bodies: they contain one-time links).
CREATE TABLE IF NOT EXISTS email_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(190) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  kind VARCHAR(30) NOT NULL DEFAULT 'other',
  status ENUM('sent','failed') NOT NULL,
  error VARCHAR(400) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (k, v, grp) VALUES
  ('smtp_enabled', '0', 'email'),
  ('smtp_host', '', 'email'),
  ('smtp_port', '587', 'email'),
  ('smtp_security', 'starttls', 'email'),
  ('smtp_user', '', 'email'),
  ('smtp_password', '', 'email'),
  ('smtp_verify_cert', '1', 'email'),
  ('mail_from_email', '', 'email'),
  ('mail_from_name', '', 'email'),
  ('mail_reply_to', '', 'email'),
  ('reader_verify_email', '1', 'email'),
  ('reader_require_verified', '0', 'email'),
  ('email_reset_readers', '1', 'email'),
  ('email_reset_university', '0', 'email'),
  ('notify_author_status', '1', 'email'),
  ('notify_comments', '1', 'email'),
  ('seo_indexing', '1', 'seo'),
  ('feed_enabled', '1', 'seo'),
  ('feed_items', '30', 'seo'),
  ('sitemap_enabled', '1', 'seo');
