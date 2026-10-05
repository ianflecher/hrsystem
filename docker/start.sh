#!/bin/sh
# Runs on every start of the container: listen on Render's port, make sure the
# database exists, bring its tables up to date, and cache the configuration.
set -e
cd /var/www/html

PORT="${PORT:-10000}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# SQLite: the file lives on the persistent disk (mounted at database/data).
if [ "${DB_CONNECTION}" = "sqlite" ]; then
    DB_DATABASE="${DB_DATABASE:-/var/www/html/database/data/database.sqlite}"
    export DB_DATABASE
    mkdir -p "$(dirname "$DB_DATABASE")"
    [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
    chown -R www-data:www-data "$(dirname "$DB_DATABASE")"
fi

chown -R www-data:www-data storage bootstrap/cache

php artisan package:discover --ansi
php artisan storage:link || true
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
