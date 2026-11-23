# Progress

Last updated: 2026-11-24

## Done: Phase 1A (foundation)
- Docker image (Alpine, nginx, PHP-FPM 8.3, MariaDB over a unix socket only, supervisord) with first-boot setup: database and user creation, migrations, optional first tech admin from `INITIAL_ADMIN_UID` / `INITIAL_ADMIN_PASSWORD`.
- `docker-compose.yml` for Portainer, `.env.example`, `.gitignore`, `.dockerignore`, `/health` endpoint and image healthcheck.
- PHP core: front controller, router with CSRF check on every POST, sessions (httponly, samesite, secure behind HTTPS proxy), PDO helper, settings stored in DB, English/Bangla i18n with language switch (`/lang/en`, `/lang/bn`), CSP and security headers.
- Schema 001: settings, faculties, departments, users, user_profiles, login_attempts, audit_logs, plus seed settings and placeholder faculties/departments.
- Design system: light/dark theme (no flash), responsive header and footer, landing page placeholder, "coming soon" and error pages.
- Fonts and icons: cached nginx mirrors of fonts.rayaz.org and icons.rayaz.org (no third-party requests from visitors), `Fonts` class and `/theme.css`, font settings (migration 002). Defaults: Open Sans, Young Serif, Noto Sans Bengali, Noto Naskh Arabic.
- Profile-required-fields settings seeded per role (migration 002); the gate itself is built in 1B.
- Icons still missing from the icon set (user to add): `newspaper`, `quote` (SVGs delivered in new-icons/). Until added, the home page feature card for 'Publish your work' shows a blank icon.

## Licensing
AGPL-3.0-or-later. Still to do: add the `LICENSE` file (download from https://www.gnu.org/licenses/agpl-3.0.txt) and put a licence header in source files. Migration 003 adds the `source_repo_url` setting; the footer shows a source link when it is set.

## NOT tested yet
The sandbox had no PHP or Docker, so none of this has been run. First build may need small fixes (package names, paths). Please build it once and send me any error output.

## Next: Phase 1B (accounts)
- Login page with role-based redirect (moderator portal vs tech portal vs member dashboard), rate limiting and lockout using `login_attempts`, forced password change at first login, logout.
- Admin-created users (single and CSV import), profile editing for students and teachers, required-field gate before publishing, profile photo upload.
- Password-reset request and admin approval flow.
- Then 1C publishing (article types, editor, workflow, moderator portal), 1D public site and legal pages, 1E Tech portal.

## Open items for the user
- Real site name, logo, colours and university name (editable later in Tech portal).
- Tech portal (1E) will need a font picker fed by `/tzl-fonts/api/fonts.json` and checkboxes for required profile fields.
