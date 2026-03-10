FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    zip \
    intl \
    mbstring \
    bcmath \
    opcache \
    pcntl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock ./

# Install dependencies (no dev)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY . .

# Generate autoloader & cache
RUN composer dump-autoload --optimize \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# PHP-FPM config
RUN echo '[www]' > /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo 'pm = dynamic' >> /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo 'pm.max_children = 20' >> /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo 'pm.start_servers = 4' >> /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo 'pm.min_spare_servers = 2' >> /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo 'pm.max_spare_servers = 6' >> /usr/local/etc/php-fpm.d/zz-custom.conf

# OPcache config
RUN echo 'opcache.enable=1' > /usr/local/etc/php/conf.d/opcache-custom.ini \
    && echo 'opcache.memory_consumption=128' >> /usr/local/etc/php/conf.d/opcache-custom.ini \
    && echo 'opcache.max_accelerated_files=10000' >> /usr/local/etc/php/conf.d/opcache-custom.ini \
    && echo 'opcache.validate_timestamps=0' >> /usr/local/etc/php/conf.d/opcache-custom.ini

EXPOSE 9000

CMD ["php-fpm"]
