#!/bin/sh
set -e

# Copy public files to shared volume for Nginx
cp -r /var/www/html/public/* /var/www/html/public-shared/ 2>/dev/null || true

# Cache config on startup (uses runtime env vars)
php artisan config:cache
php artisan route:cache

exec "$@"
