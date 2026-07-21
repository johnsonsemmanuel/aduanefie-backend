web: bash start.sh
worker: php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=180 --memory=1024
scheduler: php artisan schedule:work
