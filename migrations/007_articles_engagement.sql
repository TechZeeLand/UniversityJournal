-- Phase 1C/1D (first slice): articles, article types, reactions, comments, reader accounts.

CREATE TABLE IF NOT EXISTS article_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL UNIQUE,
  name_en VARCHAR(100) NOT NULL,
  name_bn VARCHAR(100) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO article_types (slug, name_en, name_bn, sort_order) VALUES
  ('research', 'Research paper', 'গবেষণাপত্র', 10),
  ('essay', 'Essay', 'প্রবন্ধ', 20),
  ('opinion', 'Opinion', 'মতামত', 30),
  ('tutorial', 'Tutorial', 'টিউটোরিয়াল', 40),
  ('campus-news', 'Campus news', 'ক্যাম্পাস সংবাদ', 50),
  ('interview', 'Interview', 'সাক্ষাৎকার', 60),
  ('review', 'Review', 'রিভিউ', 70),
  ('creative', 'Creative writing', 'সৃজনশীল লেখা', 80);

-- status: draft -> submitted -> published | changes_requested | rejected ; published -> archived
-- The like / dislike / comment / view counters are kept on the row so lists can sort by them quickly.
CREATE TABLE IF NOT EXISTS articles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id INT UNSIGNED NOT NULL,
  type_id INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL,
  lang ENUM('en','bn','ar') NOT NULL DEFAULT 'en',
  title VARCHAR(220) NOT NULL,
  slug VARCHAR(160) NOT NULL DEFAULT '',
  summary VARCHAR(500) NULL,
  body MEDIUMTEXT NULL,
  cover_path VARCHAR(255) NULL,
  status ENUM('draft','submitted','changes_requested','published','rejected','archived') NOT NULL DEFAULT 'draft',
  review_label ENUM('none','editor_reviewed','peer_reviewed') NOT NULL DEFAULT 'none',
  review_note TEXT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  like_count INT UNSIGNED NOT NULL DEFAULT 0,
  dislike_count INT UNSIGNED NOT NULL DEFAULT 0,
  comment_count INT UNSIGNED NOT NULL DEFAULT 0,
  view_count INT UNSIGNED NOT NULL DEFAULT 0,
  share_count INT UNSIGNED NOT NULL DEFAULT 0,
  reading_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  submitted_at DATETIME NULL,
  published_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status_pub (status, published_at),
  INDEX idx_author (author_id),
  INDEX idx_type (type_id),
  INDEX idx_dept (department_id),
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (type_id) REFERENCES article_types(id) ON DELETE SET NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One reaction per person per article: 1 = like, -1 = dislike.
CREATE TABLE IF NOT EXISTS article_reactions (
  article_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  value TINYINT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (article_id, user_id),
  INDEX idx_user (user_id),
  FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comments; replies are one level deep (parent_id points to a top-level comment).
CREATE TABLE IF NOT EXISTS article_comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  status ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_article (article_id, status, created_at),
  INDEX idx_user (user_id, created_at),
  FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES article_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reader accounts (role 'reader'): people outside the university. They sign in with their email,
-- or with Google. google_sub is Google's stable user id. has_password = 0 for Google-only accounts.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS google_sub VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS has_password TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uq_users_google_sub (google_sub);

INSERT IGNORE INTO settings (k, v, grp) VALUES
  ('reader_signup', '1', 'readers'),
  ('reader_email_signup', '1', 'readers'),
  ('reader_google', '0', 'readers'),
  ('google_client_id', '', 'readers'),
  ('google_client_secret', '', 'readers'),
  ('engage_reactions', '1', 'engagement'),
  ('engage_comments', '1', 'engagement'),
  ('engage_share', '1', 'engagement'),
  ('comment_max_len', '1500', 'engagement'),
  ('comment_cooldown_sec', '20', 'engagement'),
  ('publish_needs_review', '1', 'engagement'),
  ('home_latest_count', '20', 'home'),
  ('home_show_latest', '1', 'home'),
  ('explore_per_page', '12', 'home');
