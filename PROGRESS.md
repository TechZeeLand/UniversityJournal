# Progress

Last updated: 2026-11-25 (Phase 1B+ written: switchable sign-in ID system and the first Tech portal settings; Phase 1B and 1B+ not yet tested)

## Done: Phase 1B+ (2026-11-25) - written, UNTESTED (the sandbox has no PHP; only a syntax lint and a language-key check were run)
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
1. **1E-a Tech portal, part 2**: Fonts tab, Email (SMTP, with a test-email button), required-profile-fields tab, university details and the editable pages (about, contact, privacy, terms).
2. **1E-b Security**: 2FA (email and app), devices list, trusted devices.
3. **1C Publishing**: article types, rich editor with autosave, review workflow, moderator tools.
4. **1D Public site**: landing page polish, article pages, search, legal pages, share cards, RSS, PWA.
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
AGPL-3.0-or-later. Still to do: add the `LICENSE` file (download from https://www.gnu.org/licenses/agpl-3.0.txt) and put a licence header in source files. Migration 003 adds the `source_repo_url` setting; the footer shows a source link when it is set.

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
- Provide `source_repo_url` (needed by the AGPL for a public site).
- Real site name, logo, colours and university name (editable later in Tech portal).
- The Fonts tab (font pickers fed by `/tzl-fonts/api/fonts.json`) and the required-profile-fields checkboxes are the next Tech portal tabs (see Suggested next phases).
