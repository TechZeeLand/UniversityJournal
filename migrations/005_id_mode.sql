-- Switchable sign-in ID system (Tech portal > Settings > Accounts).
--   university : the person's university ID (users.uid) is the username (default).
--   sequential : a fixed number given when the account was created, counting up from 0 (users.serial_no).
-- serial_no is assigned to EVERY account, in both modes, and is never reused, so the mode can be switched at any time.
-- uid becomes optional (needed in university mode only); several NULLs are allowed under the UNIQUE key.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS serial_no BIGINT UNSIGNED NULL AFTER uid,
  MODIFY uid VARCHAR(40) NULL;

SET @n := (SELECT COALESCE(MAX(serial_no), -1) FROM users);
UPDATE users SET serial_no = (@n := @n + 1) WHERE serial_no IS NULL ORDER BY id;

ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uq_users_serial_no (serial_no);

-- Next number to hand out. Kept in its own row so numbers are never reused, even after an account is deleted.
CREATE TABLE IF NOT EXISTS counters (
  k VARCHAR(60) NOT NULL PRIMARY KEY,
  v BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO counters (k, v) SELECT 'serial_no', COALESCE(MAX(serial_no) + 1, 0) FROM users;
INSERT IGNORE INTO settings (k, v, grp) VALUES ('id_mode', 'university', 'accounts');
