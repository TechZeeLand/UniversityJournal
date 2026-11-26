# University Journal: full summary

Last updated: 2026-11-26. Status: Phase 1A built and deployed; Phases 1B, 1B+ and 1E-a (data folder, branding uploads, public addresses, editable site content) written, awaiting your build and test.

## 1. What the project is
An article publishing site for your university where students and professors publish their work. It is a student initiative; university officials are not involved yet. Licence: **AGPL-3.0-or-later** (open source). Code lives on GitHub (also the backup) and is deployed by pulling the repo into Docker through Portainer's repository option, like your other projects.

## 2. Your requests (original brief)
**Stack:** HTML, CSS, JS, PHP, SQL, MariaDB and whatever else is needed. MariaDB and nginx are baked into the container.

**Pages and features wanted:**
1. A striking landing page
2. Articles
3. An advertisement page
4. Basic pages (about, contact, etc.)
5. Legal pages (privacy policy, terms and conditions, etc.)
6. Admin panel
7. Login system
8. Community discussions
9. A profile for every teacher and student
10. Article types
11. Rich article editing
12. Other useful things (including your ideas file, with improvements and bug fixes from me)

**Two separate admin areas**, both reached through the same login page and separated by credentials:
- **Moderator portal:** the university magazine and article group reviews, approves and manages everything.
- **Tech portal (you):** every technical setting, so you rarely touch code. It also edits all pages and university details.

**Accounts:** created manually by an admin, later edited by the owner. The university ID is the username. Authors must complete the required profile fields before they can publish.

**Other requirements:** dark and light themes; writing in English, Bangla and Arabic; icons from your icon service, with me listing any new icons so you can add them (no local icon files kept).

## 3. Rules you set for me
1. Tell you before this chat's memory is nearly full so you can move to a new chat.
2. Before running out, provide a progress file and a brief markdown file covering what the project is, what was done, what's next and every decision since the beginning.

I can't see my own token count, so I refresh `PROJECT.md` and `PROGRESS.md` after each phase and will warn you early.

## 4. Decisions made
- **Backend:** plain PHP 8.3, no framework; custom small core. One image runs nginx, PHP-FPM and MariaDB under supervisord. MariaDB is reachable only through a local socket.
- **Build style:** phase by phase, checking in with you between phases.
- **Languages:** interface in English and Bangla; articles can be written in English, Bangla or Arabic (RTL per article).
- **Fonts:** from your fonts service, mirrored and cached by the site's own nginx (`/tzl-fonts/`). Chosen in the Tech portal (body, heading, Bangla, Arabic, weights). Defaults: Open Sans, Young Serif, Noto Sans Bengali, Noto Naskh Arabic.
- **Icons:** from your icons service through a cached mirror (`/icons-cdn/`). Visitors make no third-party requests.
- **Required profile fields:** configurable per role in the Tech portal. Defaults: full name, department, photo, bio, plus session (students) or designation (teachers).
- **Licence:** AGPL-3.0-or-later. Footer shows a "Source code" link when the `source_repo_url` setting is set (AGPL section 13). Never commit secrets; `.env` is git-ignored.
- **Approved defaults:** placeholder branding "University Journal" (editable in the Tech portal); SMTP configured in the Tech portal; self-managed ad slots; optional TOTP 2FA for the tech admin; configurable upload limits (default 5 MB images, 20 MB attachments); password resets approved by an admin (email if SMTP is set); editor screening with review labels in Phase 1, full peer review in Phase 2; placeholder faculties and departments.
- **Roles** are stored as text (student, teacher, moderator, tech_admin) so reviewer, editor and department admin can be added later without a schema change.
- **Student IDs** stay private by default (from your ideas file).
- **Sign-in ID system (2026-11-25):** switchable in the Tech portal between the university ID (default, one unique ID per user) and a fixed login number given at creation, counting up from 0 (unique, never reused). Every account always has a login number, so switching works both ways; switching back is refused while any account has no university ID.
- **2FA (2026-11-25):** email code and authenticator app wanted; scheduled after SMTP settings exist (Phase 1E-b). You will set up SMTP in the Tech portal later.
- **Modern ease-of-use features (2026-11-25):** wanted for a polished feel; the proposed list is in PROGRESS.md.
- **The Tech portal can do everything (2026-11-25):** all texts, settings, limits, pages, toggles and all project fonts are editable there; each new feature ships with its settings.
- **Working rules (2026-11-25):** I may make improvements, fix bugs along the way, ask questions, and I keep giving progress updates.

- **One data folder (2026-11-26):** everything (database, uploads, backups) lives in `data/` in the project root (host variable `DATA_DIR`), so the project is portable and easy to back up. Never committed to git.
- **Public addresses (2026-11-26):** students `/students/<university ID>`, teachers `/teachers/<public number>` (own serial, not the login number); switchable per role in the Tech portal, custom address per person possible. This replaces the earlier "IDs stay private" decision.
- **Everything public is editable in the Tech portal (2026-11-26):** logo, icons, colours, fonts, home page, contact, menus, pages, and every text.
- **Design stays consistent (2026-11-26).**

## 5. Phase plan
| Phase | Scope | Status |
|---|---|---|
| 1A | Foundation: Docker, core PHP, schema, theme, i18n, fonts and icons mirrors | Done, deployed |
| 1B+ | Switchable sign-in ID (university ID or login number from 0), Tech portal settings framework with Accounts, Uploads, Branding, General tabs | Written, needs your test |
| 1B | Login with role redirect, lockout, forced password change, admin-created users (single and CSV), profile editing, required-fields gate, photos, password-reset approval | Written, needs your test |
| 1E-a | One data folder, logo/icon uploads, brand colours, fonts tab, public addresses and profile pages, editable pages/menus/texts, profile rules, backups | Written, needs your test |
| 1C | Article types, rich editor, submission workflow, moderator portal | Planned |
| 1D | Landing page polish, article pages, search, about, contact, legal pages | Planned |
| 1E-a2 | Email (SMTP) tab with test button | Planned |
| 1E-b | Security: 2FA by email code and authenticator app, devices list, trusted devices | Planned (you asked me to note it and pick the phase) |
| 2 | Community discussions, ads page, reviewer assignment, citations, versions, PDF export, analytics | Planned |
| 3 | Further ideas from your ideas file (collections and journals, ORCID, collaboration, etc.) | Planned |

## 6. What exists now (Phase 1A + 1B)
Phase 1B adds: sign-in/out with lockout and real-IP detection behind proxies, forced password change, profile editing with photos and the required-fields gate, the moderator portal (users, CSV import, password-request approvals), a basic Tech portal (system info, audit log), migration 004 and an emergency CLI password reset. See PROGRESS.md for details and the test checklist.

Phase 1A:
- Docker image, `docker-compose.yml` for Portainer, `.env.example`, health check.
- First-boot setup: creates the database and user, runs migrations, creates the first tech admin from `INITIAL_ADMIN_UID` and `INITIAL_ADMIN_PASSWORD`.
- Router with CSRF checks, secure sessions, settings stored in the database, security headers and a strict same-origin content policy.
- Database: settings, faculties, departments, users, profiles, login attempts, audit logs, with seed data. Migrations 001 to 003.
- Design: light and dark themes without flash, responsive header and footer, placeholder landing page, "Coming soon" and error pages, favicon.
- Language switching (`/lang/en`, `/lang/bn`).

## 7. Bugs found while testing, and fixes
1. nginx proxy variables were set after `rewrite ... break`, so fonts and icons returned an error page. Fixed by moving `set` before `rewrite`.
2. `image:` in the compose file made Portainer try to pull from Docker Hub. Removed; the stack is build-only. Use "Re-build image" when redeploying.
3. A bad CSS edit hid every icon (`.zi,.only-dark{display:none}`). Fixed in `app.css`. **Needs a push and redeploy to take effect.**
4. Favicon 404 fixed with a favicon link.
5. Browser console messages about an inline script from "sandbox eval code", the `file:///` warning and Open Sans "kern table" warnings are harmless and not caused by the site.

## 8. Deploy steps (brief)
1. Add the AGPL `LICENSE` file (`curl -o LICENSE https://www.gnu.org/licenses/agpl-3.0.txt`), push the repo to GitHub.
2. Portainer: Stacks, Add stack, Repository. Reference `refs/heads/main`, compose path `docker-compose.yml`.
3. Set `APP_URL`, `APP_KEY`, `DB_PASSWORD`, `INITIAL_ADMIN_UID`, `INITIAL_ADMIN_PASSWORD`.
4. Put an HTTPS reverse proxy in front of `HTTP_PORT` (default 8090).
5. To update: push, then "Pull and redeploy" with Re-build image on.
Data lives in the volumes `journal-db` and `journal-storage`; back them up.

## 9. Still to do or open
- Push and redeploy the icon CSS fix, then confirm all icons show.
- Add `newspaper` and `quote` to your icon service (SVGs delivered earlier). The "Publish your work" card stays blank until `newspaper` exists.
- Add the `LICENSE` file and licence headers in source files.
- Set `source_repo_url` (database for now; Tech portal later).
- Provide real site name, logo, colours and university name (editable later in the Tech portal).
- Tech portal needs a font picker fed by the fonts service and checkboxes for required profile fields.
- Phase 1B onward as in the table above.
- Untested areas so far: everything in Phase 1B (login, uploads, CSV import, resets), SMTP and 2FA (not built yet).
- Test Phase 1B with the checklist in PROGRESS.md and send me any error output.
- Add the 18 icons in new-icons-1b/ that your set lacks.
