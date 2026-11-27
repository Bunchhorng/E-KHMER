#!/usr/bin/env sh
set -eu

cd /var/www

# The application may use Neon PgBouncer for normal HTTP traffic, but schema
# changes need a session-capable direct Neon endpoint. Keeping this separate
# also prevents an invalid migration from putting the web service in a restart
# loop.
if [ -z "${DB_MIGRATION_URL:-}" ]; then
    echo "[render-migrate] DB_MIGRATION_URL is required and must be Neon’s direct (non-pooler) PostgreSQL URI." >&2
    exit 64
fi

case "$DB_MIGRATION_URL" in
    *-pooler.*|*pooler.*)
        echo "[render-migrate] DB_MIGRATION_URL points at a pooled endpoint. Use Neon’s direct URI for migrations." >&2
        exit 64
        ;;
esac

# Do not use config cached with the pooled URL. The process-scoped override
# leaves the web process configuration untouched.
export DB_URL="$DB_MIGRATION_URL"

php artisan config:clear --no-interaction
php artisan migrate --force --no-interaction

