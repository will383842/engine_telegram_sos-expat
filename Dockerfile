FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    $PHPIZE_DEPS

# Configure GD with freetype/jpeg support
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    zip \
    intl \
    mbstring \
    bcmath \
    opcache \
    pcntl \
    gd

# Install Redis + gRPC extensions
RUN pecl install redis grpc && docker-php-ext-enable redis grpc

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock ./

# Install dependencies (no dev)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-req=ext-grpc

# Copy application code
COPY . .

# Generate autoloader
RUN composer dump-autoload --optimize

# Create shared public directory
RUN mkdir -p /var/www/html/public-shared

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public-shared \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Entrypoint
COPY deploy/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# PHP-FPM config
RUN echo '[www]' > /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo 'pm = dynamic' >> /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo 'pm.max_children = 20' >> /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo 'pm.start_servers = 4' >> /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo 'pm.min_spare_servers = 2' >> /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo 'pm.max_spare_servers = 6' >> /usr/local/etc/php-fpm.d/zz-custom.conf

# PHP upload/memory limits (for ZIP import up to 100MB)
RUN echo 'upload_max_filesize=128M' > /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'post_max_size=128M' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'memory_limit=256M' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'max_execution_time=300' >> /usr/local/etc/php/conf.d/uploads.ini

# OPcache config
RUN echo 'opcache.enable=1' > /usr/local/etc/php/conf.d/opcache-custom.ini \
    && echo 'opcache.memory_consumption=128' >> /usr/local/etc/php/conf.d/opcache-custom.ini \
    && echo 'opcache.max_accelerated_files=10000' >> /usr/local/etc/php/conf.d/opcache-custom.ini \
    && echo 'opcache.validate_timestamps=1' >> /usr/local/etc/php/conf.d/opcache-custom.ini \
    && echo 'opcache.revalidate_freq=2' >> /usr/local/etc/php/conf.d/opcache-custom.ini

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
