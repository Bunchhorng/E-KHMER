#!/bin/sh
# Run only against a disposable database. This script refuses the live DB name.
set -eu
umask 077
test "${RESTORE_DATABASE:-}" = "ecommerce_restore_check" || { echo "RESTORE_DATABASE must be ecommerce_restore_check"; exit 1; }
test -n "${RESTORE_HOST:-}" || { echo "Set RESTORE_HOST to the disposable restore server"; exit 1; }
test "$RESTORE_HOST" != mysql || { echo "Refusing the live mysql host"; exit 1; }
export MYSQL_PWD="${RESTORE_PASSWORD:?Set the disposable restore password}"
restore_user="${RESTORE_USER:-root}"
backup_name="$(cat /backups/latest)"
case "$backup_name" in ecommerce-[0-9]*T[0-9]*Z) ;; *) echo "Invalid backup name"; exit 1;; esac
gzip -t "/backups/$backup_name.sql.gz"
mysql --host="$RESTORE_HOST" --user="$restore_user" -e 'CREATE DATABASE ecommerce_restore_check'
gunzip -c "/backups/$backup_name.sql.gz" > /tmp/restore-check.sql
mysql --host="$RESTORE_HOST" --user="$restore_user" ecommerce_restore_check < /tmp/restore-check.sql
mysql --host="$RESTORE_HOST" --user="$restore_user" ecommerce_restore_check -e 'SELECT COUNT(*) AS restored_migrations FROM migrations; SELECT COUNT(*) AS restored_orders FROM orders; SELECT COUNT(*) AS restored_products FROM products'
tar -tzf "/backups/$backup_name-media.tar.gz" >/dev/null
echo "Restore check complete. The live database was not changed."

