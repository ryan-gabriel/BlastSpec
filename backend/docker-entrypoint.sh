#!/bin/sh
set -e

cd /var/www/html

# Generate app key if missing
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Run migrations
php artisan migrate --force 2>/dev/null || true

# Seed if database is empty
php artisan db:seed --force 2>/dev/null || true

# Cache config and routes
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true

exec "$@"
