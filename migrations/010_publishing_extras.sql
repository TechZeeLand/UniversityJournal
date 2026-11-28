-- Phase 1F: reading list (bookmarks), reports on comments, new settings.

CREATE TABLE IF NOT EXISTS article_bookmarks (
  user_id INT UNSIGNED NOT NULL,
  article_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, article_id),
  INDEX idx_article (article_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One report per person per comment. A moderator "dismisses" the reports by clearing them.
CREATE TABLE IF NOT EXISTS comment_reports (
  comment_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  reason VARCHAR(20) NOT NULL DEFAULT 'other',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (comment_id, user_id),
  INDEX idx_user (user_id),
  FOREIGN KEY (comment_id) REFERENCES article_comments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO settings (k, v, grp) VALUES
  ('autosave_seconds', '8', 'engagement'),
  ('bookmarks_enabled', '1', 'engagement'),
  ('comment_reports_enabled', '1', 'engagement'),
  ('notify_moderators_submit', '1', 'email');
