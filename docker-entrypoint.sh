#!/bin/bash
set -e

# Replace nginx port with PORT env if set by Railway
PORT="${PORT:-8080}"
sed -i "s/listen 8080/listen ${PORT}/g" /etc/nginx/conf.d/default.conf

# Ensure storage link exists
ln -sfn /app/storage/app/public /app/public/storage 2>/dev/null || true

# Symlink public/public -> public as extra fallback
ln -sfn /app/public /app/public/public 2>/dev/null || true

# Fix permissions
chown -R www-data:www-data /app/storage /app/bootstrap/cache /app/public 2>/dev/null || true
chmod -R 775 /app/storage /app/bootstrap/cache 2>/dev/null || true

# Ensure Laravel Passport keys exist with strict 600 permissions for www-data
if [ ! -f /app/storage/oauth-private.key ] || [ ! -f /app/storage/oauth-public.key ]; then
    php artisan passport:keys --no-interaction 2>/dev/null || true
fi
chown www-data:www-data /app/storage/oauth-*.key 2>/dev/null || true
chmod 600 /app/storage/oauth-*.key 2>/dev/null || true

# Cache static configuration to avoid per-request config bootstrap overhead
php artisan config:cache 2>/dev/null || true

# Start php-fpm in background
php-fpm -D

# Warm critical read caches right after Nginx begins listening to prevent cold-boot bursts
(
    sleep 3
    for path in /api/v1/module /api/v1/config /api/v1/react-landing-page /api/v1/banners /api/v1/advertisement/list /api/v1/app-download-section; do
        curl -sf --max-time 25 "http://127.0.0.1:${PORT}${path}" >/dev/null 2>&1 || true
    done
    for path in \
        "/api/v1/module" \
        "/api/v1/banners" \
        "/api/v1/advertisement/list" \
        "/api/v1/stores/latest?type=all" \
        "/api/v1/stores/get-stores/all?offset=1&limit=12&store_type=all" \
        "/api/v1/items/popular?limit=10&offset=1&type=all" \
        "/api/v1/items/discounted?limit=15&offset=1" \
        "/api/v1/items/most-reviewed?type=all"; do
        curl -sf --max-time 30 -H "zoneId: [1]" -H "moduleId: 2" "http://127.0.0.1:${PORT}${path}" >/dev/null 2>&1 || true
    done
) &

# Start nginx in foreground
echo "Starting Nginx on port ${PORT}..."
exec nginx -g "daemon off;"
