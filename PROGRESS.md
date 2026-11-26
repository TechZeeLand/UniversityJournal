# Progress

Last updated: 2026-11-26 (Phase 1E-a written: data folder, branding uploads, public addresses, editable site; Phases 1B, 1B+ and 1E-a are NOT yet tested, the sandbox has no PHP)

## Done: Phase 1C + 1D first slice (2026-11-27) - written, UNTESTED (no PHP in my sandbox; static lint only: brackets in all 110 PHP files, language keys en/bn parity, helper/method names, JS syntax)

**Your 6 requests, in the order you gave them**
1. **GPL**: `LICENSE` is now GPL-3.0 (full text); README, footer ("Source code (GPL-3.0)"), setting hints, Tech portal text and migration 003 comment updated. The source link in the footer is optional now. Note: unlike the AGPL, the GPL does not oblige you to publish a modified version that you only run as a website. Say so if you want the AGPL back.
2. **Home page**: hero (with live numbers), feature cards, then **"The Latest" newspaper section** under the cards: dateline strip with a double rule, a lead story with big serif headline, two side stories, a three-column row with vertical rules, a "More stories" wire list in two columns, and a sidebar with "Most read this month", a write call-to-action and a reader sign-up box. Stories without a cover get a generated colour cover. Count (default 20) and on/off are Tech portal settings (Home tab).
3. **Explore** (`/explore`): big search box; sort chips **Newest, Oldest, Top rated, Most liked, Most read, Most discussed**; filters by type, department, language, period (week/month/year) and a from/to date range; removable active-filter tags; paging. Filters apply as you change them.
4. **Like, dislike, comment, share**: like/dislike without reloading (one reaction per person, press again to undo); comments with one level of replies, delete your own, moderators can hide/show/delete; share bar (native share sheet, copy link, Facebook, X, WhatsApp, Telegram, LinkedIn, email, print); reads counted once per visit; share clicks counted. All switchable in Tech portal > Engagement.
5. **Reader accounts** (role `reader`): `/register` with **plain email** or **Google** (each switchable in Tech portal > Readers, with client ID and secret fields; the secret is write-only; the redirect URI to register with Google is shown on that tab). Readers can read, like, dislike and comment, and have a small `/account` page (name, photo, password). They sign in on the normal login page with their email. A Google sign-in never takes over a university account that has the same email. Moderators have Readers, Comments and Articles tabs.
6. **Design**: new hero, cards that lift on hover, newspaper typography, explore page header, article page (reading-progress bar, drop cap, cover, author card), print styles, mobile layouts. Every page uses the existing colour/font settings.

**Needed first, so also built (this was Phase 1C)**: articles table, 8 article types, writer pages (`/write`: Markdown editor with toolbar, live preview, inline picture upload, cover picture, drafts, unsaved-changes warning), submit for review, moderator review queue (publish with a review label, request changes, reject, archive, feature), author notes. Tech setting "articles must be approved by a moderator" (on by default).

**Demo content**: `php bin/seed-demo.php` adds 24 sample published articles (uses your existing students/teachers as authors); `php bin/seed-demo.php --clear` removes them. Run it once to see the newspaper layout.

**Bugs found after your first test (2026-11-27)**
- Home page returned 500: the home statistics query used `reads` as a column alias, and READS is a reserved word in MySQL/MariaDB. Renamed to `read_total`. Only the home page ran that query.
- Bangla digits in `num()` / `num_short()` were corrupted because `strtr()` with a multibyte replacement string works byte by byte. Now uses an array map.

**Test checklist**
- [ ] Migration 007 applies on start (new tables, `users.google_sub`, `users.has_password`, new settings).
- [ ] `php bin/seed-demo.php`, then open `/`: newspaper section shows; try `/explore` with every sort and filter.
- [ ] Sign in as a student: `/write/new`, write, insert a picture, preview, save draft, submit. Profile must be complete to submit.
- [ ] Moderator: Articles tab, open the article, publish with a label. It appears on `/` and `/explore`.
- [ ] Open the article signed out: like gives the sign-in page; after sign-in you return to the article.
- [ ] `/register` with email; log out; sign in with the email; like, dislike, comment, reply, delete own comment.
- [ ] Tech portal > Readers: set Google client ID/secret, turn Google on, register the shown redirect URI in Google Cloud Console, try "Continue with Google".
- [ ] Moderator > Comments: hide, show, delete. Moderator > Readers: suspend, reset password.
- [ ] Bangla interface: digits, month names, article cards. An Arabic article reads right to left.

**Known gaps / assumptions (tell me if you disagree)**
- Reader emails are **not verified** yet (no email sending until the SMTP tab exists). Readers cannot reset a forgotten password themselves yet; a moderator can issue a temporary one. Both come with the SMTP phase.
- "Most rated" = likes minus dislikes ("Top rated") plus a separate "Most liked".
- Published articles cannot be edited by the author yet (ask a moderator to archive); versioning and peer-review rounds are later.
- No tags, bookmarks, notifications, comment likes, or report button yet. No full-text search engine; search is title/summary/author name.
- New icon needed in your icon service: `thumbs-down` (SVG in `new-icons-2/thumbs-down.svg`). Until it is added, the dislike button shows no icon.

## Previous: Phase 1E-a (2026-11-26) - written, UNTESTED (static lint only: brackets, imports, method names, language keys en/bn parity, SQL quotes)
**Your requests**
1. **Logo and icon editable from the Tech portal**: Settings > Branding has upload cards for logo, dark-theme logo (optional), site icon (favicon) and share picture; plus names in English and Bangla, brand colours for light and dark, "show name next to logo". The header, favicon, theme colour and share tags use them.
2. **Public URLs**: `/students/<university ID>` and `/teachers/<public number>` by default; switchable under Settings > Public addresses (students: university ID, number or random code; teachers: number, university ID or random code). Custom address per person on the person's page (tech admin only). Public profile page and `/authors` directory built so the addresses resolve. People can hide their page on their profile; pages show only for complete profiles. Teachers' number is a separate serial (1, 2, 3 ...), not the sign-in login number.
3. **Data folder**: `data/` in the project root holds database, uploads, backups and runtime files (see PROJECT.md decision 25 and `data/README.md`). docker-compose mounts `${DATA_DIR:-./data}:/data`; entrypoint, MariaDB, nginx, sessions and uploads all use it.
4. **Design consistent**: new CSS reuses the existing components. Fixed a bug: number/URL inputs in the settings pages were unstyled.
5. **Everything public editable from the Tech portal**: Site content > Pages (about, contact, privacy, terms and your own, Markdown, EN/BN, draft/published), Menus (header and footer), Texts (every public wording, EN/BN, empty = default); Settings tabs Fonts, Home page, Contact and social, Profile rules (the required-fields checkboxes), Backups.

**Also built (improvements)**: Fonts tab with picker (suggestions from the font service); required profile fields editable (decision 11); automatic and manual database backups with download; Open Graph tags; sr-only brand name option; `Paths`, `Theme`, `Markdown` core classes; `/theme.css` now also carries brand colours.

**Not built yet (still planned)**: Email (SMTP) settings tab with test button, 2FA (Phase 1E-b), publishing (1C), search/RSS/sitemap/PWA (1D). Pages cannot upload images yet (use the `/uploads/...` path or a web address).

### Test checklist for 1E-a
1. Prepare the data folder: on Portainer set `DATA_DIR` to an absolute host folder (e.g. `/srv/university-journal/data`, create it). The first start with an empty folder creates a NEW database; see README to copy the old volumes or start fresh. Rebuild/redeploy; migration 006 runs.
2. Sign in as tech admin. `/tech` shows "Data folder" and cards for Site content and Backups. Check that `data/db`, `data/uploads`, `data/runtime` appeared on the host.
3. Settings > Branding: upload a logo (SVG and PNG), a dark logo, an icon; change the colours; reload the site in light and dark. Remove one and see the built-in return.
4. Settings > Fonts: change the body font, save, check `/theme.css`. Settings > Home page, Contact and social: fill and check the home page and footer.
5. Site content > Menus: rename a link, add one, remove one. Pages: edit About, add a page `faq`, view `/faq`. Texts: change the hero title in English, check the home page; empty the box to restore.
6. Complete a student profile (all required fields), then open `/students/<their ID>` and `/authors`. Switch Public addresses to the number mode and see the address change. Give someone a custom address on their user page.
7. Profile rules tab: untick a field, save, check that a profile counts as complete.
8. Backups: "Back up now", download the file, delete it. Check `data/backups` on the host.

## Previous: Phase 1B+ (2026-11-25) - written, UNTESTED
**Switchable sign-in ID system** (your request): Tech portal > Settings > Accounts and security.
- Mode `university` (default): the person's university ID is the username, one unique ID per user, as before.
- Mode `sequential`: the username is a fixed login number given when the account is created, counting up from 0 (0, 1, 2 ...), unique, never reused (even if an account is deleted later).
- Every account always gets a login number (`users.serial_no`), in both modes, so you can switch in either direction at any time. Existing accounts were numbered by creation order (your first admin is 0).
- University ID becomes optional in login-number mode (still checked for format and uniqueness if given). Switching back to university IDs is refused while any account has no university ID; the page says how many and links to a filtered list (`/moderator/users?noid=1`).
- Login and "forgot password" pages change their label and hint to match the mode. Admin screens (user list, user page, reset queue, import report, temporary-password box) show the sign-in ID of the current mode; the user page shows both. Search finds either.
- CSV import: in login-number mode the `uid` column is optional, and the downloadable report lists each new login number (this is how you learn them).
- Switching signs nobody out; it asks for confirmation and writes an audit entry. Emergency CLI reset accepts either kind of ID.
- Technical: migration `005_id_mode.sql` (adds `serial_no`, makes `uid` nullable, `counters` table, setting `id_mode`), new `app/Core/Identity.php`, number handed out inside the creation transaction with a row lock.

**Tech portal settings framework** (first slice of Phase 1E): `/tech/settings` with tabs, defined in `app/Services/TechSettings.php` (type-checked fields, all-or-nothing save, audit entry with old and new values, English and Bangla labels). Tabs so far: Accounts and security (ID system, password length, lockout, idle timeout), Uploads, Branding (site name, tagline, university name, source link), General (default language and theme). New tabs are added by adding an entry there.

### Test checklist for 1B+
1. Redeploy (migration 005 runs). Open `/tech/settings/accounts`: ID system card shows the mode, account count and next login number.
2. Create a user in university mode (works as before); the user page shows both IDs.
3. Switch to login numbers (confirm dialog). Sign out; the login page now asks for a login number. Sign in with your admin's number (0 if it is the first account).
4. Create a user without a university ID: the temporary-password box shows the new login number. Import a CSV without a `uid` column and download the report.
5. Try switching back: it must refuse while an account has no university ID; add one, then it works.
6. Change a setting on each tab; try an invalid value (error text shows, nothing saved); check `/tech/audit`.

## Backlog from your requests (decisions recorded, NOT built yet)
- **2FA with email code and authenticator app (TOTP)**: you asked me to note it and choose the phase. Plan: Phase 1E-b "Security", right after the Email (SMTP) settings tab exists, because email codes need SMTP. SMTP will be configured by you later in the Tech portal. Scope: choose method per account, optional or required per role (setting), backup codes, trusted devices, a signed-in devices list with "sign out others", admin 2FA reset, audit entries. Passkeys (WebAuthn) as a possible later extra.
- **All the other important modern features for ease of use and a polished feel** (my proposal, in rough order): toasts and inline form feedback, keyboard shortcuts and a command palette in the portals, search-as-you-type, autosave drafts and an unsaved-changes warning in the editor, reading time and progress bar, copy-link and share buttons, bookmarks and a reading list, notifications bell plus email notifications (needs SMTP), installable app (PWA) with offline reading, social share cards and rich link previews, sitemap and RSS feed, print and PDF-friendly article view, empty states and skeleton loaders, maintenance-mode switch, backup and export from the Tech portal, a health dashboard.
- **Fonts: all project fonts editable in the Tech portal.** Planned as the Fonts tab in Phase 1E: pickers fed by `/tzl-fonts/api/fonts.json` for body, headings, Bangla, Arabic, plus weights and sizes, with a live preview; later the article and editor fonts too.
- **Principle: the Tech portal can do everything.** Any text, setting, limit, page, font, toggle or feature switch should be editable there instead of in code. Every new feature from now on ships with its Tech settings tab or section.

## Suggested next phases
1. **1E-a (done, see top)**. Remaining from it: Email (SMTP) tab with a test-email button.
2. **1E-b Security**: 2FA (email and app), devices list, trusted devices.
3. **1C Publishing (first slice done, see top)**. Remaining: autosave, versions/editing after publish, peer-review rounds, tags, drafts shared with co-authors.
4. **1D Public site (home, explore, article page done)**. Remaining: RSS, sitemap, PWA, per-author pages listing their articles, better search.
I will ask you to confirm this order before starting.

## Done: Phase 1A (foundation)
- Docker image (Alpine, nginx, PHP-FPM 8.3, MariaDB over a unix socket only, supervisord) with first-boot setup: database and user creation, migrations, optional first tech admin from `INITIAL_ADMIN_UID` / `INITIAL_ADMIN_PASSWORD`.
- `docker-compose.yml` for Portainer, `.env.example`, `.gitignore`, `.dockerignore`, `/health` endpoint and image healthcheck.
- PHP core: front controller, router with CSRF check on every POST, sessions (httponly, samesite, secure behind HTTPS proxy), PDO helper, settings stored in DB, English/Bangla i18n with language switch (`/lang/en`, `/lang/bn`), CSP and security headers.
- Schema 001: settings, faculties, departments, users, user_profiles, login_attempts, audit_logs, plus seed settings and placeholder faculties/departments.
- Design system: light/dark theme (no flash), responsive header and footer, landing page placeholder, "coming soon" and error pages.
- Fonts and icons: cached nginx mirrors of fonts.rayaz.org and icons.rayaz.org (no third-party requests from visitors), `Fonts` class and `/theme.css`, font settings (migration 002). Defaults: Open Sans, Young Serif, Noto Sans Bengali, Noto Naskh Arabic.
- Profile-required-fields settings seeded per role (migration 002); the gate itself is built in 1B.
- Icons still missing from the icon set (user to add): `newspaper`, `quote` (SVGs delivered in new-icons/). Until added, the home page feature card for 'Publish your work' shows a blank icon.

## Done: Phase 1B (accounts) - written, UNTESTED
The sandbox has no PHP, so this was checked only by a static linter (brackets, method names, language keys). Build it and report any error output.
- Sign-in at `/login` with role redirect (students/teachers `/dashboard`, moderators `/moderator`, tech admins `/tech`), safe `?next=` return, sign-out (POST), forced password change at first login and after resets.
- Lockout with three scopes (ID+IP, ID anywhere, IP anywhere) from `login_attempts`; constant-time check for unknown IDs; suspended accounts only revealed after a correct password.
- Real client IP behind reverse proxies via `TRUSTED_PROXIES` (default: private networks). Without this every visitor would share one IP and IP lockout would hit everyone.
- Sessions: id regenerated on login, idle timeout (setting `session_idle_minutes`, 120), `auth_epoch` so a password change/reset or suspension signs the user out everywhere. Fixed PHP's 24-minute session garbage collection.
- Password rules (min length setting, common-password list, not equal to ID, max 72 bytes), generated temporary passwords without look-alike characters.
- Profile editing for everyone: photo upload (validated, EXIF-rotated, re-encoded as 512x512 JPEG), bio, links, email, department; required-field gate computed per role from `profile_required_*` settings (`profile_complete`), shown on the dashboard. Publishing (1C) will check it.
- Moderator portal: overview, user list (search, filters, paging), create user, edit user, suspend/reactivate, reset password (shown once), CSV import (UTF-8, max 300 rows, generated passwords, downloadable report, spreadsheet-formula safe), password-reset request queue with approve/reject.
- Tech portal (basic): system info, audit log viewer with search. Full settings editor stays in 1E. Tech admins can also use the moderator portal and manage moderators/tech admins; moderators manage only students and teachers.
- Password reset requests: `/forgot` (same reply whether or not the ID exists, 5 per hour per IP), approved by a moderator/tech admin.
- Audit log entries for logins, password changes/resets, user changes, imports.
- Migration 004: `users.public_id` (random, for public URLs so IDs stay private), `auth_epoch`, `password_changed_at`, `password_reset_requests`, security/upload settings.
- Emergency CLI reset: `docker exec -it university-journal php /var/www/app/bin/reset-password.php <id>`.
- Other fixes: database session time zone now matches the site (times were UTC), HEAD requests work, CSP allows `blob:` images for the photo preview, `php83-exif` added.
- New icons used (SVGs in `new-icons-1b/`, add those you do not have): log-in, log-out, user, users, user-plus, key-round, eye, eye-off, upload, download, circle-check, circle-alert, triangle-alert, info, search, layout-dashboard, scroll-text, copy.

## First-run test checklist for 1B
1. Rebuild/redeploy (migration 004 runs on start). Sign in as the tech admin; you must change the password.
2. `/tech` loads; create a moderator and a student at `/moderator/users/new`; copy the temporary passwords.
3. Sign in as the student: forced password change, then edit profile with a photo, check the dashboard banner clears when required fields are filled.
4. Try 5 wrong passwords: lockout message. Try `/forgot`, approve it as moderator, sign in with the new temporary password.
5. Import `users-template.csv` (edit a few rows), download the report.
6. Open `/tech/audit` and confirm entries.

## Licensing
GPL-3.0-or-later (switched from AGPL-3.0 on 2026-11-26/27). `LICENSE` is the GPL-3.0 text. Still to do: licence header in source files. Migration 003 adds the `source_repo_url` setting; the footer shows a source link when it is set (optional under the GPL).

## Test results so far
User built it in Portainer: site loads. Bug found and fixed: in nginx, `set` must come before `rewrite ... break` (otherwise the proxy variable is empty), so fonts/icons did not load. Browser console messages about an inline script from 'sandbox eval code' and 'file:///' come from a browser extension, not the site.

Second bug fixed: `image:` in docker-compose.yml made Portainer's 'Pull and redeploy' try to pull from Docker Hub. Removed it, so the stack is build-only. In Portainer, enable 'Re-build image' (or equivalent) when redeploying after pushes.

## Earlier note (superseded)
The sandbox had no PHP or Docker, so none of this has been run. First build may need small fixes (package names, paths). Please build it once and send me any error output.

## Earlier plan for 1B (now done, see above)
- Login page with role-based redirect (moderator portal vs tech portal vs member dashboard), rate limiting and lockout using `login_attempts`, forced password change at first login, logout.
- Admin-created users (single and CSV import), profile editing for students and teachers, required-field gate before publishing, profile photo upload.
- Password-reset request and admin approval flow.
## Next
- Phase 1C publishing (article types, editor, workflow, moderator portal), 1D public site and legal pages, 1E Tech portal.

## Open items for the user
- `source_repo_url` is optional now (GPL); set it if you want a footer link to the code.
- Real site name, logo, colours and university name (editable later in Tech portal).
- The Fonts tab (font pickers fed by `/tzl-fonts/api/fonts.json`) and the required-profile-fields checkboxes are the next Tech portal tabs (see Suggested next phases).
