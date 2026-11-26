-- Phase 1E-a: public addresses, editable pages / menus / texts.

-- Public numbers for students and teachers (used in /teachers/12). Not the sign-in login number.
-- public_slug is an optional custom address, set by a tech admin for one person.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS public_no BIGINT UNSIGNED NULL AFTER public_id,
  ADD COLUMN IF NOT EXISTS public_slug VARCHAR(40) NULL AFTER public_no;
ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uq_users_public_no (public_no);
ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uq_users_public_slug (public_slug);

SET @p := (SELECT COALESCE(MAX(public_no), 0) FROM users);
UPDATE users SET public_no = (@p := @p + 1) WHERE role IN ('student', 'teacher') AND public_no IS NULL ORDER BY id;
INSERT IGNORE INTO counters (k, v) SELECT 'public_no', COALESCE(MAX(public_no), 0) + 1 FROM users;

-- People can hide their own public profile page.
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 1 AFTER links;

-- Editable pages (about, contact, privacy, terms and any new page).
CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL UNIQUE,
  title_en VARCHAR(190) NOT NULL,
  title_bn VARCHAR(190) NULL,
  body_en MEDIUMTEXT NULL,
  body_bn MEDIUMTEXT NULL,
  status ENUM('published','draft') NOT NULL DEFAULT 'published',
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  updated_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Header and footer links.
CREATE TABLE IF NOT EXISTS menu_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  location ENUM('header','footer') NOT NULL,
  label_en VARCHAR(80) NOT NULL,
  label_bn VARCHAR(80) NULL,
  url VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_menu (location, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Any interface text can be overridden here (Tech portal > Site content > Texts); the language files stay the defaults.
CREATE TABLE IF NOT EXISTS text_overrides (
  locale VARCHAR(5) NOT NULL,
  k VARCHAR(120) NOT NULL,
  v TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (locale, k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO menu_items (location, label_en, label_bn, url, sort_order) VALUES
  ('header', 'Home', 'হোম', '/', 10),
  ('header', 'Explore', 'অন্বেষণ', '/explore', 20),
  ('header', 'Authors', 'লেখক', '/authors', 30),
  ('header', 'Departments', 'বিভাগসমূহ', '/departments', 40),
  ('header', 'Collections', 'সংকলন', '/collections', 50),
  ('header', 'About', 'আমাদের সম্পর্কে', '/about', 60),
  ('footer', 'About', 'আমাদের সম্পর্কে', '/about', 10),
  ('footer', 'Contact', 'যোগাযোগ', '/contact', 20),
  ('footer', 'Advertise', 'বিজ্ঞাপন', '/advertise', 30),
  ('footer', 'Privacy Policy', 'গোপনীয়তা নীতি', '/privacy', 40),
  ('footer', 'Terms and Conditions', 'শর্তাবলি', '/terms', 50);

INSERT INTO pages (slug, title_en, title_bn, body_en, body_bn, status, is_system) VALUES
('about', 'About us', 'আমাদের সম্পর্কে',
'**{{site_name}}** is a student-run publication of {{university_name}}. It is a home for research, essays, tutorials, stories and ideas written by students and teachers.

## What we publish
- Research notes and papers
- Essays, opinion pieces and creative writing
- Tutorials and project write-ups

## Who runs it
A team of students, with editors who check every article before it goes live. Every author has a profile, so readers can see who wrote what.

## Get involved
Accounts are created by the editors. If you would like to write for us, please get in touch.',
'**{{site_name}}** হলো {{university_name}}-এর শিক্ষার্থীদের পরিচালিত একটি প্রকাশনা। এখানে শিক্ষার্থী ও শিক্ষকদের গবেষণা, প্রবন্ধ, টিউটোরিয়াল, গল্প ও ভাবনা প্রকাশিত হয়।

## আমরা কী প্রকাশ করি
- গবেষণা-নোট ও গবেষণাপত্র
- প্রবন্ধ, মতামত ও সৃজনশীল লেখা
- টিউটোরিয়াল ও প্রকল্পের বিবরণ

## কারা পরিচালনা করেন
একদল শিক্ষার্থী, সঙ্গে সম্পাদকেরা, যাঁরা প্রতিটি লেখা প্রকাশের আগে যাচাই করেন। প্রত্যেক লেখকের নিজস্ব প্রোফাইল আছে, তাই পাঠক জানতে পারেন কে কী লিখেছেন।

## যুক্ত হোন
অ্যাকাউন্ট তৈরি করেন সম্পাদকেরা। আমাদের জন্য লিখতে চাইলে যোগাযোগ করুন।',
'published', 1),
('contact', 'Contact', 'যোগাযোগ',
'We would love to hear from you.

- Email: {{contact_email}}
- Phone: {{contact_phone}}
- Address: {{contact_address}}

To report a mistake in a published article, please write to the editors and include the link to the article.',
'আপনার কথা শুনতে আমরা আগ্রহী।

- ইমেইল: {{contact_email}}
- ফোন: {{contact_phone}}
- ঠিকানা: {{contact_address}}

প্রকাশিত কোনো লেখায় ভুল থাকলে সম্পাদকদের লিখুন এবং লেখাটির লিংক জানান।',
'published', 1),
('privacy', 'Privacy Policy', 'গোপনীয়তা নীতি',
'*Draft template. Replace this text with the policy approved by your university.*

## What we store
- Account details: your name, university ID or login number, department and the profile information you choose to add.
- Security records: sign-in attempts and the IP address they came from, kept to protect accounts from guessing attacks.
- Your own writing and comments, once those features are available.

## What visitors see
Only profiles that are complete and that the owner has not hidden are public. Email addresses are never shown publicly.

## Cookies
We use cookies only to keep you signed in and to remember your language and theme. There are no advertising or tracking cookies.

## Third parties
Fonts and icons are served by this site itself, so your browser does not contact other companies to display it.

## Your choices
You can edit or hide your profile at any time. To ask for your data to be corrected or removed, contact us: {{contact_email}}',
'*খসড়া টেমপ্লেট। এই লেখার জায়গায় আপনার বিশ্ববিদ্যালয়ের অনুমোদিত নীতি বসান।*

## আমরা কী সংরক্ষণ করি
- অ্যাকাউন্টের তথ্য: আপনার নাম, বিশ্ববিদ্যালয়ের আইডি বা লগইন নম্বর, বিভাগ এবং আপনার দেওয়া প্রোফাইল তথ্য।
- নিরাপত্তা রেকর্ড: সাইন ইনের চেষ্টা ও তার আইপি ঠিকানা, যা অ্যাকাউন্টকে অনুমান-আক্রমণ থেকে রক্ষা করে।
- আপনার নিজের লেখা ও মন্তব্য, সেই সুবিধা চালু হলে।

## দর্শকেরা কী দেখেন
শুধু সম্পূর্ণ এবং মালিক লুকিয়ে না রাখা প্রোফাইল প্রকাশ্য। ইমেইল ঠিকানা কখনোই প্রকাশ্যে দেখানো হয় না।

## কুকি
আপনাকে সাইন ইন রাখতে এবং ভাষা ও থিম মনে রাখতে শুধু কুকি ব্যবহার করা হয়। বিজ্ঞাপন বা ট্র্যাকিং কুকি নেই।

## তৃতীয় পক্ষ
ফন্ট ও আইকন এই সাইট নিজেই সরবরাহ করে, তাই এটি দেখাতে আপনার ব্রাউজার অন্য কোনো কোম্পানির সঙ্গে যোগাযোগ করে না।

## আপনার পছন্দ
আপনি যেকোনো সময় প্রোফাইল সম্পাদনা বা লুকিয়ে রাখতে পারেন। তথ্য সংশোধন বা মুছে ফেলতে চাইলে যোগাযোগ করুন: {{contact_email}}',
'published', 1),
('terms', 'Terms and Conditions', 'শর্তাবলি',
'*Draft template. Replace this text with the terms approved by your university.*

## Using the site
Reading is open to everyone. Writing requires an account created by the editors.

## Your content
You remain the author of what you write. By publishing here you allow {{site_name}} to display it on this site. Do not publish work that you do not have the right to share.

## Conduct
Be respectful. Do not publish hateful, abusive, misleading or unlawful content. Editors may decline, edit, unpublish or remove content, and may suspend accounts that break these rules.

## No guarantees
Articles express the views of their authors, not of {{university_name}}. The site is provided as is.

## Changes
These terms may change. The latest version is always on this page.',
'*খসড়া টেমপ্লেট। এই লেখার জায়গায় আপনার বিশ্ববিদ্যালয়ের অনুমোদিত শর্তাবলি বসান।*

## সাইট ব্যবহার
পড়ার সুযোগ সবার জন্য খোলা। লিখতে হলে সম্পাদকদের তৈরি করা অ্যাকাউন্ট লাগে।

## আপনার লেখা
আপনি যা লেখেন তার লেখক আপনিই থাকেন। এখানে প্রকাশ করে আপনি {{site_name}}-কে এই সাইটে তা দেখানোর অনুমতি দেন। যে লেখা শেয়ার করার অধিকার আপনার নেই তা প্রকাশ করবেন না।

## আচরণ
সম্মানজনক থাকুন। ঘৃণামূলক, আক্রমণাত্মক, বিভ্রান্তিকর বা বেআইনি লেখা প্রকাশ করবেন না। সম্পাদকেরা লেখা প্রত্যাখ্যান, সম্পাদনা, প্রকাশ বন্ধ বা অপসারণ এবং নিয়ম ভাঙলে অ্যাকাউন্ট স্থগিত করতে পারেন।

## কোনো নিশ্চয়তা নেই
লেখাগুলো লেখকদের মত, {{university_name}}-এর নয়। সাইটটি যেমন আছে তেমনভাবে দেওয়া হয়।

## পরিবর্তন
এই শর্তাবলি বদলাতে পারে। সর্বশেষ সংস্করণ সবসময় এই পাতায় থাকবে।',
'published', 1);
