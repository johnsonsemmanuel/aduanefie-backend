# ==============================================================================
# Aduanefie Marketplace — Backend (Laravel 12) Dockerfile
# Multi-stage build: Builder → Runtime (PHP 8.3 + Nginx)
# Optimized for Railway deployment
# ==============================================================================

# -----------------------------------------------
# Stage 1: Builder — install deps, compile assets
# -----------------------------------------------
FROM php:8.3-fpm AS builder

WORKDIR /build

# System deps needed only at build time
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl zip unzip \
        libonig-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libwebp-dev \
        libxml2-dev libcurl4-openssl-dev libssl-dev libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql pdo_pgsql pgsql mbstring zip bcmath gd xml simplexml opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Install PHP deps first (layer cache)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --optimize-autoloader

# Copy full source
COPY . .

# Post-compile scripts
RUN php artisan package:discover --ansi 2>/dev/null || true

# Install Node deps and build frontend assets (Inertia/Vite)
RUN npm ci --prefer-offline 2>/dev/null && npm run build:prod 2>/dev/null || echo "Frontend build skipped"

# -----------------------------------------------
# Stage 2: Runtime — lean production image
# -----------------------------------------------
FROM php:8.3-fpm

LABEL maintainer="Aduanefie Marketplace"

# Runtime system deps
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl zip unzip \
        libonig-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libwebp7 libwebpdemux2 libwebpmux3 \
        nginx \
        supervisor \
        libicu-dev \
        libxml2-dev \
        libcurl4 \
        libpng16-16 \
        libjpeg62-turbo \
        libfreetype6 \
        libonig5 \
        libpq-dev \
        default-mysql-client \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql pdo_pgsql pgsql mbstring zip bcmath gd xml simplexml opcache intl \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP-FPM tuning — pool directives + PHP ini overrides
RUN { \
        echo '[www]'; \
        echo 'pm = dynamic'; \
        echo 'pm.max_children = 20'; \
        echo 'pm.start_servers = 4'; \
        echo 'pm.min_spare_servers = 2'; \
        echo 'pm.max_spare_servers = 8'; \
        echo 'pm.max_requests = 500'; \
        echo 'request_terminate_timeout = 60s'; \
        echo 'php_admin_value[upload_max_filesize] = 64M'; \
        echo 'php_admin_value[post_max_size] = 64M'; \
        echo 'php_admin_value[memory_limit] = 512M'; \
        echo 'php_admin_value[max_execution_time] = 60'; \
    } > /usr/local/etc/php-fpm.d/zz-tuning.conf

# Opcache for production
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.save_comments=1'; \
        echo 'opcache.jit=1255'; \
        echo 'opcache.jit_buffer_size=128M'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# PHP CLI overrides
RUN { \
        echo 'memory_limit = 1G'; \
        echo 'max_execution_time = 0'; \
    } > /usr/local/etc/php/conf.d/cli-overrides.ini

WORKDIR /var/www/html

# Copy production artifacts from builder
COPY --from=builder /build/artisan /var/www/html/artisan
COPY --from=builder /build/vendor /var/www/html/vendor
COPY --from=builder /build/app /var/www/html/app
COPY --from=builder /build/bootstrap /var/www/html/bootstrap
COPY --from=builder /build/config /var/www/html/config
COPY --from=builder /build/database /var/www/html/database
COPY --from=builder /build/public /var/www/html/public
COPY --from=builder /build/resources /var/www/html/resources
COPY --from=builder /build/routes /var/www/html/routes
COPY --from=builder /build/storage /var/www/html/storage
COPY --from=builder /build/Modules /var/www/html/Modules
COPY --from=builder /build/composer.json /var/www/html/composer.json
COPY --from=builder /build/composer.lock /var/www/html/composer.lock
COPY --from=builder /build/modules_statuses.json /var/www/html/modules_statuses.json
COPY --from=builder /build/start.sh /var/www/html/start.sh
COPY --from=builder /build/installation /var/www/html/installation
COPY --from=builder /build/docker/nginx.conf /etc/nginx/nginx.conf
COPY --from=builder /build/docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Storage setup
RUN mkdir -p storage/framework/{sessions,views,cache,testing} \
        storage/logs storage/app/public storage/app/private \
    && touch storage/logs/laravel.log \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod +x /var/www/html/start.sh

# Fix PHP-FPM listen for Nginx
RUN sed -i 's|^listen = .*|listen = 127.0.0.1:9000|' /usr/local/etc/php-fpm.d/www.conf

# Force rebuild for proxy header + asset path fixes

EXPOSE 8080

CMD ["sh", "start.sh"]
