# University Journal

An open-source article publishing platform for a university, licensed under the GNU AGPL-3.0-or-later.
See PROJECT.md for the overview and PROGRESS.md for status.

## Licence
Copyright (C) the University Journal contributors. This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. It is distributed WITHOUT ANY WARRANTY. See https://www.gnu.org/licenses/agpl-3.0.html.

Because this is AGPL software, if you run a modified version as a public website you must offer your visitors the source code of your version. Set the repository address under Settings > Legal (`source_repo_url`) and the footer will link to it.

## Deploy with Portainer (from GitHub)
1. Push this repo to GitHub (public is fine; never commit a real `.env`).
2. Portainer > Stacks > Add stack > Repository.
   - Repository URL: your GitHub repo URL. Reference: `refs/heads/main`. Compose path: `docker-compose.yml`.
   - Private repo: turn on Authentication and use a GitHub fine-grained token with read access to this repo.
3. In "Environment variables" add at least: `APP_URL`, `APP_KEY`, `DB_PASSWORD`, `INITIAL_ADMIN_UID`, `INITIAL_ADMIN_PASSWORD` (see `.env.example`).
4. Deploy the stack. The first build takes a few minutes; the first start creates the database and the tech admin.
5. Point your reverse proxy (with HTTPS) at the host port `HTTP_PORT` (default 8090).
6. Updates: push to GitHub, then in Portainer open the stack and choose "Pull and redeploy". Optionally turn on the stack's GitHub webhook or automatic updates.

### Where the data lives
ALL data (database, uploads, backups) is in one folder on the host, `DATA_DIR` (default: the `data/` folder next to `docker-compose.yml`). See `data/README.md`. Copy that folder to back up or move the whole site. The database is also dumped automatically into `data/backups/` (Tech portal > Settings > Backups).

**Portainer:** a relative `./data` points inside Portainer's own checkout of the repository, which is not a good place for data. Set the stack variable `DATA_DIR` to an absolute folder on the host, for example `/srv/university-journal/data`, and create it first (`mkdir -p`).

**Coming from the old layout** (named volumes `journal-db` and `journal-storage`)? Copy their content once: `docker run --rm -v journal-db:/from -v /srv/university-journal/data/db:/to alpine cp -a /from/. /to/` and `docker run --rm -v journal-storage:/from -v /srv/university-journal/data:/to alpine sh -c 'mkdir -p /to/uploads && cp -a /from/uploads/public /to/uploads/'`. Or start fresh if you only have test data.

## Local run
`docker compose --env-file .env up --build`

## Accounts and recovery
- The first tech admin is created from `INITIAL_ADMIN_UID` / `INITIAL_ADMIN_PASSWORD` and must change the password at first sign-in.
- If the only tech admin is locked out: `docker exec -it university-journal php /var/www/app/bin/reset-password.php <university-id>` prints a temporary password.
- Behind a reverse proxy, set `TRUSTED_PROXIES` to the proxy's IP/CIDR if it is not on a private network, so login lockout sees real visitor IPs.
