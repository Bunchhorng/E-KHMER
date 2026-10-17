FROM node:22-alpine AS frontend
WORKDIR /frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ ./
ENV VITE_API_URL=/api
RUN npm run build

FROM php:8.3-fpm AS app
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libzip-dev libonig-dev libxml2-dev libicu-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd zip intl \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY --chown=www-data:www-data backend/ ./
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
RUN composer dump-autoload --no-dev --optimize --no-interaction --no-scripts \
    && php artisan package:discover --no-interaction \
    && mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
ENV APP_ENV=production APP_DEBUG=false
CMD ["php-fpm"]

FROM nginx:alpine AS web
COPY docker/production/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /frontend/dist /srv/frontend
COPY --from=app /var/www/public /var/www/public

