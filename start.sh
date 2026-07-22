#!/bin/bash
set -e

echo "============================================"
echo "  Aduanefie Marketplace — Backend Starting"
echo "============================================"
echo "PORT:             ${PORT:-8080}"
echo "APP_ENV:          ${APP_ENV:-not set}"
echo "DB_HOST:          ${DB_HOST:-not set}"
echo "RAILWAY_RUN_AS:   ${RAILWAY_RUN_AS:-web}"
echo ""

# -------------------------------------------
# 1. Guard: APP_KEY must exist
# -------------------------------------------
if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set. Generate one with:"
    echo "  php artisan key:generate"
    echo "Then set it as an environment variable in Railway."
    exit 1
fi

# -------------------------------------------
# 2. Force production mode
# -------------------------------------------
export APP_ENV=production
export APP_DEBUG="${APP_DEBUG:-false}"

# -------------------------------------------
# 3. Clear all caches (clean slate)
# -------------------------------------------
echo "--- Clearing caches..."
php artisan config:clear  2>/dev/null || true
php artisan route:clear   2>/dev/null || true
php artisan event:clear   2>/dev/null || true
php artisan view:clear    2>/dev/null || true

# -------------------------------------------
# 4. Cache for production
# -------------------------------------------
echo "--- Caching config & routes..."
php artisan config:clear 2>/dev/null || true
php artisan route:cache  && echo "  route:cache  ✓" || echo "  WARNING: route:cache failed"
php artisan event:cache  && echo "  event:cache  ✓" || echo "  WARNING: event:cache failed"

# -------------------------------------------
# 5. Storage symlink
# -------------------------------------------
echo "--- Creating storage symlink..."
php artisan storage:link --force 2>/dev/null && echo "  storage:link ✓" || echo "  WARNING: storage:link failed"

chmod -R 775 storage 2>/dev/null || true
chmod -R 775 bootstrap/cache 2>/dev/null || true

# -------------------------------------------
# 6. Run migrations (web service only)
# -------------------------------------------
if [ "${RAILWAY_RUN_AS}" != "worker" ] && [ "${RAILWAY_RUN_AS}" != "scheduler" ]; then
    echo "--- Running database migrations..."
    if php artisan migrate --force 2>&1; then
        echo "  Migrations complete ✓"
    else
        echo "  WARNING: Migrations failed — continuing anyway"
    fi
fi

# -------------------------------------------
# 7. Seed module statuses (idempotent)
# -------------------------------------------
if [ "${RAILWAY_RUN_AS}" != "worker" ] && [ "${RAILWAY_RUN_AS}" != "scheduler" ]; then
    echo "--- Ensuring module statuses..."
    php artisan module:list 2>/dev/null || true
fi

# -------------------------------------------
# 8. Verify app boots
# -------------------------------------------
echo "--- Verifying app boot..."
if php artisan about --only=environment 2>/dev/null; then
    echo "  App boot verified ✓"
else
    echo "  WARNING: App boot check failed — clearing caches and retrying..."
    php artisan config:clear 2>/dev/null || true
    php artisan route:clear  2>/dev/null || true
    php artisan event:clear  2>/dev/null || true
    php artisan config:cache 2>/dev/null || true
    php artisan route:cache  2>/dev/null || true
fi

# -------------------------------------------
# 9. Branch by role
# -------------------------------------------
case "${RAILWAY_RUN_AS}" in

    worker)
        echo ""
        echo "--- Starting queue worker..."
        exec php artisan queue:work \
            --sleep=3 --tries=3 --max-jobs=500 \
            --max-time=3600 --timeout=180 --memory=1024
        ;;

    scheduler)
        echo ""
        echo "--- Starting scheduler..."
        exec php artisan schedule:work
        ;;

    *)
        # Default: web service
        echo ""
        echo "--- Starting web server on port ${PORT:-8080}..."

        # Inject Railway PORT into nginx config
        sed -i "s/__PORT__/${PORT:-8080}/g" /etc/nginx/nginx.conf

        # Test PHP-FPM config
        php-fpm -t 2>/dev/null || { echo "ERROR: PHP-FPM config invalid"; exit 1; }

        # Start PHP-FPM in background
        php-fpm -D 2>/dev/null

        # Wait for FPM socket
        for i in $(seq 1 30); do
            if nc -z 127.0.0.1 9000 2>/dev/null; then
                echo "  PHP-FPM ready ✓"
                break
            fi
            sleep 0.5
        done

        # Start Nginx in foreground
        exec nginx -g 'daemon off;'
        ;;
esac
