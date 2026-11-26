#!/bin/sh
# First-boot setup: init MariaDB, create DB/user, run migrations, seed tech admin, then hand over to supervisord.
set -eu

: "${DB_PASSWORD:?DB_PASSWORD is required}"
export DB_NAME="${DB_NAME:-university_journal}"
export DB_USER="${DB_USER:-journal}"
SOCK=/run/mysqld/mysqld.sock

case "$DB_PASSWORD" in
  *\'*|*\\*) echo "DB_PASSWORD must not contain ' or \\" >&2; exit 1 ;;
esac

# One data folder for everything: /data (mounted from the host, see docker-compose.yml)
mkdir -p /run/mysqld /data/db \
         /data/uploads/public/avatars /data/uploads/public/branding /data/uploads/private \
         /data/runtime/sessions /data/runtime/proxy-cache /data/runtime/logs /data/backups
chown -R mysql:mysql /run/mysqld /data/db
chmod 750 /data/db
chown -R nginx:nginx /data/uploads /data/runtime /data/backups

if [ ! -d /data/db/mysql ]; then
  echo "[init] Creating MariaDB data directory in /data/db ..."
  mariadb-install-db --user=mysql --datadir=/data/db --skip-test-db >/dev/null
fi

mariadbd --user=mysql --datadir=/data/db --skip-networking --socket="$SOCK" &
DBPID=$!

i=0
until mariadb-admin --socket="$SOCK" ping >/dev/null 2>&1; do
  i=$((i+1)); [ "$i" -gt 60 ] && { echo "MariaDB did not start" >&2; exit 1; }
  sleep 1
done

mariadb --socket="$SOCK" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "[init] Running migrations..."
php /var/www/app/bin/migrate.php
php /var/www/app/bin/seed-admin.php

# Render nginx config (fonts/icons upstream hosts and DNS resolver)
FONTS_HOST="${FONTS_HOST:-fonts.rayaz.org}"
ICONS_HOST="${ICONS_HOST:-icons.rayaz.org}"
RESOLVER="${DNS_RESOLVER:-$(awk '/^nameserver/{print $2; exit}' /etc/resolv.conf)}"
RESOLVER="${RESOLVER:-1.1.1.1}"
case "$RESOLVER" in *:*) RESOLVER="[$RESOLVER]" ;; esac
sed -e "s|__FONTS_HOST__|$FONTS_HOST|g" -e "s|__ICONS_HOST__|$ICONS_HOST|g" -e "s|__RESOLVER__|$RESOLVER|g" \
    /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
chown -R nginx:nginx /data/runtime
nginx -t

mariadb-admin --socket="$SOCK" shutdown
wait "$DBPID" 2>/dev/null || true

exec /usr/bin/supervisord -c /etc/supervisord.conf
