# syntax=docker/dockerfile:1

FROM php:8.3-cli-alpine AS base

WORKDIR /var/www/html

# Install PHP extensions
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install pdo_pgsql mbstring zip opcache pcntl

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Copy dependency files first for layer caching
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --quiet || \
    composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application code
COPY . .

# Ensure writable directories
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache public

# Entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
