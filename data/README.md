# data/ : everything this site stores

This one folder holds ALL the data of the journal. Copy it and you have copied the whole site; mount it on a new server and the site comes back as it was.

| Folder | What is in it | Back up? |
|---|---|---|
| `db/` | the MariaDB database files | yes (stop the container first, or use `backups/`) |
| `uploads/public/` | profile photos, logo, icons, anything visitors can open | yes |
| `uploads/private/` | files only signed-in people may get (later) | yes |
| `backups/` | database dumps `db-YYYYmmdd-HHMMSS.sql.gz`, made automatically and from Tech portal > Backups | yes |
| `runtime/` | sessions, font and icon cache, logs | no, disposable |

Never commit this folder to GitHub (the `.gitignore` already prevents it): it contains password hashes and personal data.

Moving to a new server: copy this folder, deploy the project, set `DATA_DIR` to the copied folder. Restoring from a dump instead: see Tech portal > Backups.
