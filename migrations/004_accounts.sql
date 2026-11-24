-- Phase 1B: accounts.
-- public_id: random, non-guessable identifier used in public URLs, so a university ID is never exposed.
-- auth_epoch: bumped on password change/reset; sessions with an older epoch are signed out.
ALTER TABLE users
  ADD COLUMN public_id VARCHAR(16) NULL AFTER uid,
  ADD COLUMN auth_epoch INT UNSIGNED NOT NULL DEFAULT 1,
  ADD COLUMN password_changed_at DATETIME NULL,
  ADD UNIQUE KEY uq_users_public_id (public_id);

UPDATE users SET public_id = LOWER(SUBSTRING(MD5(CONCAT(id, '-', uid, '-', RAND())), 1, 12)) WHERE public_id IS NULL;

-- Password resets are requested by the user and approved by a moderator (or tech admin).
CREATE TABLE password_reset_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  note VARCHAR(500) NULL,
  contact VARCHAR(190) NULL,
  ip VARCHAR(45) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  handled_by INT UNSIGNED NULL,
  handled_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reset_status (status, created_at),
  INDEX idx_reset_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Security and upload limits (edited in the Tech portal from Phase 1E on).
INSERT INTO settings (k, v, grp) VALUES
  ('password_min_length', '8', 'security'),
  ('login_max_attempts', '5', 'security'),
  ('login_lockout_minutes', '15', 'security'),
  ('session_idle_minutes', '120', 'security'),
  ('upload_max_image_mb', '5', 'uploads'),
  ('upload_max_attach_mb', '20', 'uploads');
