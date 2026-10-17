#!/bin/sh
set -eu
umask 077
test -d /backups
export MYSQL_PWD="$MYSQL_PASSWORD"
backup_name="ecommerce-$(date -u +%Y%m%dT%H%M%SZ)"
mysqldump --host=mysql --user="$MYSQL_USER" --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE" > "/backups/$backup_name.sql"
gzip "/backups/$backup_name.sql"
tar -czf "/backups/$backup_name-media.tar.gz" -C /media public private
gzip -t "/backups/$backup_name.sql.gz"
tar -tzf "/backups/$backup_name-media.tar.gz" >/dev/null
printf '%s\n' "$backup_name" > /backups/latest
printf 'Backup verified: %s\n' "$backup_name"

