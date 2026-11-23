CREATE TABLE settings (
  k VARCHAR(120) NOT NULL PRIMARY KEY,
  v MEDIUMTEXT NULL,
  grp VARCHAR(40) NOT NULL DEFAULT 'general',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE faculties (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  name_en VARCHAR(190) NOT NULL,
  name_bn VARCHAR(190) NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  faculty_id INT UNSIGNED NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  name_en VARCHAR(190) NOT NULL,
  name_bn VARCHAR(190) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- uid = the person's university ID = their username.
-- role: student | teacher | moderator | tech_admin  (more roles, e.g. reviewer, can be added without a schema change)
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uid VARCHAR(40) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  email VARCHAR(190) NULL,
  totp_secret VARCHAR(100) NULL,
  last_login_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_profiles (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  full_name VARCHAR(190) NULL,
  full_name_bn VARCHAR(190) NULL,
  department_id INT UNSIGNED NULL,
  academic_session VARCHAR(40) NULL,
  designation VARCHAR(120) NULL,
  bio TEXT NULL,
  interests VARCHAR(500) NULL,
  photo_path VARCHAR(255) NULL,
  links TEXT NULL,
  profile_complete TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ident VARCHAR(100) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ident_time (ident, created_at),
  INDEX idx_ip_time (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  target VARCHAR(190) NULL,
  detail TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_action (action),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (k, v, grp) VALUES
  ('site_name', 'University Journal', 'branding'),
  ('site_tagline', 'Where our university''s ideas are published', 'branding'),
  ('university_name', 'Our University', 'branding'),
  ('default_lang', 'en', 'general'),
  ('default_theme', 'auto', 'general');

INSERT INTO faculties (slug, name_en, name_bn, sort_order) VALUES
  ('engineering-technology', 'Engineering and Technology', 'প্রকৌশল ও প্রযুক্তি', 1),
  ('science', 'Science', 'বিজ্ঞান', 2),
  ('business-economics', 'Business and Economics', 'ব্যবসা ও অর্থনীতি', 3),
  ('arts-humanities', 'Arts and Humanities', 'কলা ও মানবিক', 4),
  ('law-social-sciences', 'Law and Social Sciences', 'আইন ও সমাজবিজ্ঞান', 5);

INSERT INTO departments (faculty_id, slug, name_en, name_bn, sort_order)
SELECT id, 'computer-science', 'Computer Science and Engineering', 'কম্পিউটার সায়েন্স ও ইঞ্জিনিয়ারিং', 1 FROM faculties WHERE slug = 'engineering-technology';
INSERT INTO departments (faculty_id, slug, name_en, name_bn, sort_order)
SELECT id, 'physics', 'Physics', 'পদার্থবিজ্ঞান', 1 FROM faculties WHERE slug = 'science';
INSERT INTO departments (faculty_id, slug, name_en, name_bn, sort_order)
SELECT id, 'business-administration', 'Business Administration', 'ব্যবসায় প্রশাসন', 1 FROM faculties WHERE slug = 'business-economics';
INSERT INTO departments (faculty_id, slug, name_en, name_bn, sort_order)
SELECT id, 'english', 'English', 'ইংরেজি', 1 FROM faculties WHERE slug = 'arts-humanities';
INSERT INTO departments (faculty_id, slug, name_en, name_bn, sort_order)
SELECT id, 'law', 'Law', 'আইন', 1 FROM faculties WHERE slug = 'law-social-sciences';
