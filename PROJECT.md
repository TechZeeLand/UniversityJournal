# University Journal: project summary

An open-source (GPL-3.0-or-later) article publishing platform for a university, started as a student initiative (no official university involvement yet). Students and professors publish articles. Moderators review and approve. A separate Tech portal controls every technical setting.

## Stack and hosting
- Plain PHP 8.3 (no framework, small custom core in `app/Core`), MariaDB, nginx. One Docker image runs all three under supervisord.
- Code lives in a GitHub repo (backup and deployment). Portainer: Stacks > Repository, pointing at `docker-compose.yml`. Set the environment variables listed in `.env.example` in Portainer.
- ALL data lives in ONE folder, the data folder `data/` in the project root (host variable `DATA_DIR`, mounted at `/data`): `db/`, `uploads/`, `backups/`, `runtime/`. See decision 25.
- Icons: TechZeeLand Icons (icons.rayaz.org). Fonts: TechZeeLand Fonts (fonts.rayaz.org). Both are mirrored and cached by this site's own nginx (`/icons-cdn/`, `/tzl-fonts/`, cache kept on the storage volume, 7 day refresh, serves stale if the services are down). Visitors make no third-party requests. Hosts are set by `ICONS_HOST` / `FONTS_HOST`.
- Icons not yet in the set are listed to the user, who adds them to the icon service. No local icon files are kept.

## Decisions made (from the start)
1. Open source under GPL-3.0-or-later (closed-source until 2026-11-24, AGPL-3.0 until 2026-11-26, then switched to the GPL at your request). GitHub repo, deployed via Portainer repository option. Optional footer link to the source via setting `source_repo_url`. Never commit secrets; `.env` is git-ignored.
2. Languages: html, css, js, php, sql, MariaDB. MariaDB and nginx are baked into the image.
3. Accounts are created manually by an admin. Username = university ID. Users edit their own profile afterwards. Authors must complete required profile fields before they can publish.
4. Two admin areas, one login page, redirect by role: Moderator portal (review, approve, manage content and users) and Tech portal (all technical settings, pages, university details, so code rarely needs touching).
5. Dark and light themes. Interface languages: English and Bangla. Articles can be written in English, Bangla or Arabic (RTL per article).
6. Build phase by phase with check-ins: 1A foundation, 1B accounts, 1C publishing, 1D public site, 1E Tech portal. Phase 2: discussions, ads, reviewers, citations, PDF, analytics. Phase 3: ORCID, AI assistance, collaboration, etc. (from the ideas file).
7. Defaults approved: placeholder branding "University Journal" (editable in Tech portal); SMTP configured in Tech portal; self-managed ad slots; optional TOTP 2FA for tech admin; configurable upload limits (default 5 MB images, 20 MB attachments); password resets approved by an admin (email if SMTP set); Phase 1 has editor screening plus review labels, full peer review in Phase 2; placeholder faculties and departments.
8. Roles are stored as a string (student, teacher, moderator, tech_admin) so reviewer, editor and department admin can be added later without schema changes.
9. Student IDs stay private by default (ideas file principle).
10. Fonts are chosen in the Tech portal (settings: body, heading, Bangla, Arabic, weights), loaded via the cached mirror, and applied through `/theme.css`. Per-glyph fallback order: Latin font, Bangla, Arabic.
11. Required profile fields before publishing are configurable per role (student, teacher) in the Tech portal (settings `profile_required_student` / `_teacher`).
12. Process rules from the user: warn before the chat context runs out; keep this file and PROGRESS.md up to date.

## Repository layout
`app/Core` (Env, DB, Router, Session, Csrf, I18n, Settings, View, Migrator, Request, Auth, Flash, Audit, Password, Identity, Fonts), `app/Controllers`, `app/Services` (Users, Profile, Avatar, TechSettings), `app/routes.php`, `app/lang` (en, bn), `app/views`, `bin` (migrate, seed-admin), `migrations` (numbered .sql files, applied once at container start), `public` (front controller and assets), `docker` (nginx, php-fpm, mariadb, supervisor, entrypoint).

## Decisions added with Phase 1B
13. Tech admins can use both portals and manage every role; moderators manage students and teachers only. Nobody can suspend or change the role of their own account in the portal.
14. Passwords are never chosen by admins: they are generated, shown once, and must be changed at first login. Imports cannot carry passwords.
15. Lockout is deliberately three-scoped so an attacker cannot easily lock a real user out of their own account.
16. Public URLs will use `users.public_id`, never the university ID.
17. `TRUSTED_PROXIES` controls which proxies' `X-Forwarded-For` is believed (default private networks); set it if your reverse proxy has a public address.
18. Language strings live in `app/lang/<locale>.php` plus `app/lang/<locale>/*.php` (merged).

## Decisions added 2026-11-25
19. **Sign-in ID system is switchable** in Tech portal > Settings > Accounts and security (setting `id_mode`). `university` (default): the university ID is the username, unique per user. `sequential`: a fixed login number assigned at account creation, counting up from 0, unique, never reused. Every account always has a login number (`users.serial_no`, counter in table `counters`), so switching works both ways. `users.uid` is optional in login-number mode. Switching back to university IDs is refused while any account lacks a university ID. All code asks `App\Core\Identity` for the label, the lookup column and the value to show; never read `uid` directly for sign-in.
20. **2FA wanted**: email code and authenticator app (TOTP). Not built yet; scheduled for Phase 1E-b "Security", after the Email (SMTP) settings tab exists. SMTP is configured by the owner later in the Tech portal.
21. **Modern ease-of-use and polish features wanted** (toasts, shortcuts, command palette, autosave, PWA, notifications, share cards and so on). Backlog is in PROGRESS.md.
22. **The Tech portal can do everything.** Every text, setting, limit, page, font, toggle and feature switch is editable there rather than in code; each new feature ships with its Tech settings tab or section. Tabs are declared in `app/Services/TechSettings.php`.
23. **All project fonts are editable from the Tech portal** (Fonts tab with pickers fed by the fonts service; body, headings, Bangla, Arabic, weights; later article and editor fonts).
24. The owner allows me to make improvements, fix bugs along the way, ask questions when needed, and keep giving progress updates.


## Decisions added 2026-11-26 (Phase 1E-a)
25. **One data folder.** `data/` in the project root (override with `DATA_DIR`, mounted at `/data` in the container) holds everything: `db/` (MariaDB), `uploads/public` and `uploads/private`, `backups/`, `runtime/` (sessions, caches, logs; disposable). No named Docker volumes any more. Code asks `App\Core\Paths` for locations, never hard-coded paths. `data/*` is git-ignored. Portable and backup-able by copying the folder. On Portainer, set `DATA_DIR` to an absolute host path.
26. **Public profile addresses** (supersedes decisions 9 and 16, which kept IDs out of URLs): students: `/students/<university ID>` by default, teachers: `/teachers/<public number>` (a plain unique serial 1, 2, 3 ..., `users.public_no`, never reused, separate from the sign-in login number). Switchable in Tech portal > Settings > Public addresses: students university_id | serial | random; teachers serial | university_id | random. A tech admin can give one person a custom address (`users.public_slug`, must start with a letter, unique, wins over the mode). Collision-free by design: separate `/students/` and `/teachers/` prefixes; a person without a university ID falls back to their random code. A public page shows only when the role switch is on, the account is active, the profile is complete and the person has not hidden it (`user_profiles.is_public`). Changing the mode breaks old links (no legacy redirects, deliberately, so a number can never lead to the wrong person).
27. **Everything public is editable from the Tech portal.** Logo, dark logo, favicon, share picture and brand colours (Branding tab); fonts; home page (button links, feature icons, show/hide); contact details and social links; header and footer menus (Site content > Menus); pages written in Markdown, English and Bangla, with tokens like `{{site_name}}` (Site content > Pages); and ANY interface text through `text_overrides` (Site content > Texts), where the language files stay the defaults. Rule for the future: every new public string or setting must be reachable here.
28. **Branding uploads** are re-encoded with GD; SVGs are sanitised (scripts, event handlers, outside references removed) and `/uploads/` is served with a sandboxing CSP.
29. **Backups**: automatic gzip'd SQL dumps into `data/backups/` (supervisor program, on/off, interval and count in Tech portal), plus a Backups page (create, download, delete, restore command).
30. **Design consistency**: new screens reuse the existing components (`.card`, `.form`, `.field`, `.portal-nav`, `.tbl`, `.badge`, `.alert`); colours come only from CSS variables so the brand colour setting reaches every screen in both themes.

## Decisions added 2026-11-27 (Phase 1C/1D first slice)
- **Licence is the GPL** (see decision 1). Consequence accepted: running a modified copy only as a website does not require publishing it.
- **Home page**: latest articles (about 20, Tech setting) sit **below the feature cards**, in a newspaper layout (lead story, side stories, three columns, "more stories" list, most-read sidebar).
- **Explore** has sorts (newest, oldest, top rated, most liked, most read, most discussed) and filters (type, department, language, period, date range, search). "Top rated" = likes minus dislikes.
- **Engagement**: like, dislike (one reaction per person), comments with one reply level, share buttons. Moderators can hide/delete comments. Everything switchable in the Tech portal.
- **Reader accounts** (role `reader`) for people outside the university: read, like, dislike, comment only. Sign-up by **plain email** or **Google OAuth**, both configurable (on/off, Google client ID/secret) from the Tech portal. Readers sign in with their email on the normal login page. They have no profile page, cannot write.
- **Publishing workflow**: draft, submitted, changes requested, published, rejected, archived. Tech setting decides whether moderator approval is required (default yes). Moderators can attach a review label (editor reviewed, peer reviewed) and feature an article.
- **Design goal**: every page attractive, the home page especially.
