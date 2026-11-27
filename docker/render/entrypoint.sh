#!/usr/bin/env sh
set -eu

cd /var/www

PORT="${PORT:-10000}"
sed "s/__PORT__/${PORT}/g" /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ ! -e public/storage ] && [ ! -L public/storage ]; then
    php artisan storage:link --relative --no-interaction
fi

chown -R www-data:www-data storage bootstrap/cache

# Ensure runtime env vars take effect without stale build-time caches.
php artisan config:clear --no-interaction || true
php artisan route:clear --no-interaction || true
php artisan view:clear --no-interaction || true

if [ "${RUN_MIGRATIONS_ON_BOOT:-false}" = "true" ]; then
    echo "[render-entrypoint] RUN_MIGRATIONS_ON_BOOT is deprecated; using the direct migration connection."
    /usr/local/bin/render-migrate.sh
fi

if [ "${SEED_ESSENTIALS_ON_BOOT:-true}" = "true" ]; then
    echo "[render-entrypoint] Seeding essential platform settings..."
    php artisan db:seed --class=SettingSeeder --force --no-interaction
fi

if [ "${SEED_DEMO_ON_BOOT:-false}" = "true" ] && [ "${APP_ENV:-production}" != "production" ]; then
    echo "[render-entrypoint] Seeding local/staging Cambodian demo dataset..."
    php artisan db:seed --class=DemoMarketplaceSeeder --force --no-interaction
fi

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

if [ "${RUN_EMBEDDED_WORKER:-false}" = "true" ]; then
    echo "[render-entrypoint] Starting embedded queue worker & scheduler loop..."
    (
        while true; do
            su -s /bin/sh www-data -c "php /var/www/artisan queue:work --sleep=5 --tries=3 --timeout=90 --max-time=3600 --no-interaction" || true
            sleep 5
        done
    ) &
    (
        while true; do
            su -s /bin/sh www-data -c "php /var/www/artisan schedule:run --no-interaction" || true
            sleep 60
        done
    ) &
fi

php-fpm -D
exec nginx -g "daemon off;"
