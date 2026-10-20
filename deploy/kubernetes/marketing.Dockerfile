FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

FROM node:20-bookworm-slim AS assets
WORKDIR /app
COPY package*.json ./
RUN npm install --no-audit --no-fund
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

FROM php:8.2-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libsqlite3-dev unzip \
    && docker-php-ext-install pdo_sqlite zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs database \
    && touch database/database.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache database

RUN printf '%s\n' \
    '#!/bin/sh' \
    'set -e' \
    'cd /var/www/html' \
    'PORT="${PORT:-10000}"' \
    'sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf' \
    'sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf' \
    'mkdir -p "$(dirname "${DB_DATABASE:-/var/www/html/database/database.sqlite}")"' \
    'touch "${DB_DATABASE:-/var/www/html/database/database.sqlite}"' \
    'chown -R www-data:www-data storage bootstrap/cache database' \
    'php artisan storage:link || true' \
    'php artisan migrate --force --no-interaction' \
    'php artisan config:cache' \
    'php artisan route:cache' \
    'php artisan view:cache' \
    'exec apache2-foreground' \
    > /usr/local/bin/start-marketing.sh \
    && chmod +x /usr/local/bin/start-marketing.sh

ENV PORT=10000
EXPOSE 10000
CMD ["/usr/local/bin/start-marketing.sh"]
