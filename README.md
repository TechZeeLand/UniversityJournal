# University Journal

**An open-source publishing platform where the students and teachers of a university publish their own articles, and where anyone can read, react to and discuss them.**

Think of it as a campus magazine that runs itself: writers write, moderators review, the public reads. It is built by students, for a university, and it is free software (GPL-3.0-or-later), so any other university or school can run its own copy.

> "University Journal" is only a placeholder name. The site name, logo, colours, fonts, menus, pages and every public text are changed from the administration screens, not from the code.

---

## 1. What you can do with it

### If you are a reader (no account needed)
- Read the latest stories on a newspaper-style home page, or search and filter everything on the **Explore** page: newest, oldest, top rated, most liked, most read, most discussed; by type, department, language, period or date range.
- Open an author's public page to see who they are and everything they have published (`/students/<university ID>` and `/teachers/<number>`), or browse the **Authors** directory.
- Share an article (share sheet, copy link, Facebook, X, WhatsApp, Telegram, LinkedIn, email, print) and follow the site by **RSS** (`/feed.xml`).
- Use the site in **English or Bangla**, in a **light or dark** theme. Articles can be written in English, Bangla or Arabic (Arabic is shown right to left).
- Create a free **reader account** (email, or Google if the site offers it) to like, dislike and comment.

### If you are a student or a teacher (author)
- A moderator creates your account. Your university ID is your username; you choose a new password at your first sign-in.
- Fill in your profile (name, department, photo, bio, links). Some fields are required before you may publish; the administrators decide which.
- Write in the built-in editor: Markdown with a toolbar, live preview, pictures, a cover image, drafts and an "unsaved changes" warning. Pick an article type (research paper, essay, opinion, tutorial, campus news, interview, review, creative writing), a department and a language.
- Submit for review. A moderator publishes it (optionally with a review label), asks for changes (with a note) or declines it. You are emailed at each decision.
- Protect your account with **two-step sign-in** (authenticator app or email code), backup codes, trusted browsers, and a list of every browser you are signed in on.

### If you are a moderator (the magazine team)
- Create and manage student and teacher accounts (one by one, or by importing a CSV file), approve password resets, review submitted articles, feature stories, and moderate comments and reader accounts.

### If you are the technical administrator
- A separate portal for **everything technical**, so the code rarely needs touching: branding (logo, favicon), the colour theme (palettes, light and dark, every surface and text colour), fonts, home page, menus, your own pages (About, Contact, Privacy, Terms and more, in English and Bangla), every wording on the site, sign-in rules, upload limits, email (SMTP), reader sign-up and Google sign-in, two-step sign-in rules, search and RSS, backups, and an audit log of who changed what.

---

## 2. How it is made

The aim was a site that a small volunteer team can understand, host cheaply and keep running for years. So it is deliberately plain.

| Part | Choice | Why |
|---|---|---|
| Language | **PHP 8.3**, no framework (a small custom core in `app/Core`) | Easy to read and host, no dependency churn |
| Database | **MariaDB** | Reliable and free; reachable only from inside the container |
| Web server | **nginx** + PHP-FPM | Fast; also caches the font and icon files |
| Front end | Plain **HTML, CSS and JavaScript**, no build step | Nothing to compile; pages work before any script runs |
| Packaging | **One Docker image** running nginx, PHP-FPM and MariaDB together (supervisord) | One thing to deploy, one thing to back up |
| Fonts and icons | Self-hosted, cached copies of the TechZeeLand font and icon services | Visitors make no requests to third parties |
| Email | A small built-in SMTP client | Uses your own mail server; no outside service |

Rules you will see throughout:
- **Privacy first.** No analytics, trackers or third-party scripts. Passwords are hashed, authenticator secrets are encrypted, login attempts are rate limited, and every form is protected against forged requests.
- **Everything editable.** If a visitor can see it, an administrator can change it without code, including the whole colour theme.
- **Two languages.** Every screen exists in English and Bangla.
- **One data folder.** Everything the site stores is in a single folder (section 4).

### Folder map
```
app/          the PHP code: Core (engine), Controllers, Services, views (pages), lang (English and Bangla texts)
public/       what the web server serves: CSS, JavaScript, the entry file index.php
migrations/   numbered SQL files that build and upgrade the database automatically
docker/       nginx, PHP, MariaDB and supervisor settings, start-up script
bin/          command-line helpers (demo content, password and two-step reset, backup)
tools/        static-lint.py, a checker to run before publishing a change
data/         ALL stored data (database, uploads, backups); never committed to Git
```

The database upgrades itself: when the container starts, every new file in `migrations/` is applied once, in order.

---

## 3. How to run it

You need a server or computer with **Docker**. A small virtual machine is enough.

### Option A: Portainer, straight from GitHub (recommended)
1. Put this project in a GitHub repository (public is fine; **never** commit a real `.env`).
2. In Portainer: **Stacks, Add stack, Repository**.
   - Repository URL: your repository. Reference: `refs/heads/main`. Compose path: `docker-compose.yml`.
   - Private repository: switch on Authentication and use a GitHub token that can read it.
3. In **Environment variables** add (all of them are listed in `.env.example`):

   | Variable | Meaning |
   |---|---|
   | `APP_URL` | The public address, e.g. `https://journal.example.edu` |
   | `APP_KEY` | A long random secret. **Keep a copy**: authenticator secrets are encrypted with it |
   | `DB_PASSWORD` | Password for the internal database (no quotes or backslashes) |
   | `INITIAL_ADMIN_UID` / `INITIAL_ADMIN_PASSWORD` | The first technical administrator (used once, on first start) |
   | `DATA_DIR` | An **absolute** folder on the host for all data, e.g. `/srv/university-journal/data`; create it first with `mkdir -p` |
   | `HTTP_PORT` | Port on the host (default `8090`) |
   | `TRUSTED_PROXIES` | Only if your reverse proxy has a public IP address (see Troubleshooting) |

4. Deploy. The first build takes a few minutes; the first start creates the database and the first administrator.
5. Put a reverse proxy with **HTTPS** (Nginx Proxy Manager, Caddy, Traefik, a Cloudflare Tunnel and so on) in front of the host port.
6. **To update:** push to GitHub, then in Portainer open the stack and choose **Pull and redeploy** with **Re-build image** switched on.

### Option B: your own computer
```bash
cp .env.example .env     # edit it: APP_URL, APP_KEY, DB_PASSWORD, INITIAL_ADMIN_*
docker compose --env-file .env up --build
```
Open `http://localhost:8090` and sign in with the administrator you chose.

### First steps after it starts
1. Sign in as the technical administrator and choose a new password.
2. **Settings > Branding:** name, university, logo, colours. **Settings > Email:** your SMTP details, then press "Send test email".
3. Create a moderator, then the student and teacher accounts (or import a CSV).
4. Optional: `docker exec -it university-journal php /var/www/app/bin/seed-demo.php` adds 24 sample articles so you can see the layout; add `--clear` to remove them.

---

## 4. Your data and backups

Everything the site stores is in **one folder** on the host (`DATA_DIR`, default `./data`):

| Folder | Contents |
|---|---|
| `db/` | the database files |
| `uploads/` | profile photos, logo, article pictures |
| `backups/` | database dumps, made automatically and on demand |
| `runtime/` | sessions and caches (disposable) |

- **Back up** by copying that folder (and keep a copy of `APP_KEY`). Automatic dumps are set up under **Settings > Backups** in the technical portal, where you can also download them.
- **Move to a new server:** deploy the project there, point `DATA_DIR` at the copied folder, done.
- Backups contain personal data and the saved SMTP password: keep them private.

---

## 5. Accounts and recovery

- Moderators create the accounts of students and teachers; there is no public sign-up for those roles. People outside the university can create **reader** accounts if the administrator allows it.
- Forgotten password: readers get an email link. Students and teachers ask a moderator, who approves a new temporary password (an administrator can also allow the email link for them).
- The only technical administrator is locked out:
  `docker exec -it university-journal php /var/www/app/bin/reset-password.php <university-id>`
- Someone lost their phone and their backup codes:
  `docker exec -it university-journal php /var/www/app/bin/reset-2fa.php <university-id>`

---

## 6. Troubleshooting

- **Fonts or icons do not appear:** the server must reach `fonts.rayaz.org` and `icons.rayaz.org` once (it caches them afterwards).
- **Everyone gets "too many attempts" after a few wrong passwords:** the site only sees the proxy's address. Set `TRUSTED_PROXIES` to your reverse proxy's IP or network.
- **The "Share" button of your phone does not appear:** browsers offer the native share sheet only on HTTPS. Use the site through its HTTPS address (copy buttons also work on plain HTTP).
- **Emails are not sent:** check **Settings > Email**; it shows what the mail server answered. Gmail needs an app password (port 587, STARTTLS).
- **Something looks old after an update:** hard refresh the browser (Ctrl+Shift+R).

---

## 7. Contributing and licence

Bug reports, translations and improvements are welcome. Before sending a change, run `python3 tools/static-lint.py`; it catches typing slips (unmatched brackets, texts missing in one language, unknown methods or icons).

The project's status, decisions and history, phase by phase, are in `PROJECT.md` and `PROGRESS.md`.

**Licence:** GNU General Public License, version 3 or (at your option) any later version. You may use, study, share and change this software. If you give copies to others (a download, a Docker image) you must give them the source code under the same licence. See `LICENSE`.

Icons and fonts come from the TechZeeLand icon and font services.
