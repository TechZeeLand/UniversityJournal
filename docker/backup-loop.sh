#!/bin/sh
# Wakes up hourly; bin/backup.php auto decides (from Tech portal settings) whether a backup is due.
sleep 300
while true; do
  php /var/www/app/bin/backup.php auto || echo "[backup] failed" >&2
  sleep 3600
done
