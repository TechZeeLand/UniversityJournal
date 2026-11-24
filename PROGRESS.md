# Progress

Last updated: 2026-11-24 (Phase 1B written, not yet built or tested)

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
- Tech portal (1E) will need a font picker fed by `/tzl-fonts/api/fonts.json` and checkboxes for required profile fields.
