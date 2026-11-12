FROM php:8.2-fpm

# Install system dependencies & Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    zip \
    unzip \
    curl \
    git \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure & install PHP extensions required by 6amMart & Supabase
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        bcmath \
        intl \
        opcache \
        exif \
    && pecl install redis \
    && docker-php-ext-enable redis

# Copy Nginx, PHP-FPM & OPcache configuration
COPY docker-nginx.conf /etc/nginx/conf.d/default.conf
COPY docker-php-fpm-www.conf /usr/local/etc/php-fpm.d/zz-www-custom.conf
COPY docker-opcache.ini /usr/local/etc/php/conf.d/zz-opcache-custom.ini

# Set working directory
WORKDIR /app

# Copy application files
COPY . /app

# Run composer install to ensure vendor directory is populated & optimized
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# Configure storage symlink and permissions
RUN ln -sfn /app/storage/app/public /app/public/storage \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /app/vendor /app/public 2>/dev/null || true \
    && chmod -R 775 /app/storage /app/bootstrap/cache 2>/dev/null || true

# Copy entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["docker-entrypoint.sh"]
