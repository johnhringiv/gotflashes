# Multi-stage build for Laravel application

# Stage 1: Build frontend assets
FROM node:alpine AS frontend

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci

# Copy source files needed for build
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public

# Build production assets
RUN npm run build

# Stage 2: Build PHP dependencies and extensions
FROM php:8.4-fpm-alpine AS php-builder

WORKDIR /app

# Install build dependencies (only in builder stage)
RUN apk upgrade --no-cache && apk --no-cache add \
    unzip \
    oniguruma-dev \
    sqlite-dev

# Install PHP extensions (only what's needed)
RUN docker-php-ext-install pdo_sqlite mbstring pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies (production only, optimized)
# Note: We'll regenerate caches in the runtime container with proper .env
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-cache

# Stage 3: Production runtime image
FROM php:8.4-fpm-alpine

# Copy compiled PHP extensions from builder
COPY --from=php-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Install only runtime dependencies (no build deps needed)
RUN apk upgrade --no-cache && apk --no-cache add \
    sqlite-libs \
    nginx \
    supervisor

# Set working directory
WORKDIR /var/www/html

# Create directory structure first (as root, before copying files)
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache \
    && mkdir -p storage/app storage/logs \
    && mkdir -p bootstrap/cache \
    && mkdir -p database

# Copy application files with correct ownership from the start (avoids chown layer bloat)
COPY --chown=www-data:www-data app ./app
COPY --chown=www-data:www-data bootstrap ./bootstrap
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data resources ./resources
COPY --chown=www-data:www-data routes ./routes
COPY --chown=www-data:www-data artisan composer.json composer.lock ./
COPY --chown=www-data:www-data database/migrations ./database/migrations
COPY --chown=www-data:www-data docker/.env.docker ./.env

# Copy dependencies with correct ownership
COPY --from=php-builder --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# Set permissions on writable directories (chmod doesn't duplicate data like chown does)
RUN chmod -R 755 storage bootstrap/cache \
    && touch database/database.sqlite \
    && chmod 775 database \
    && chmod 664 database/database.sqlite

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Copy PHP-FPM configuration
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-custom.conf

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port 80 (HTTP only - SSL handled by HAProxy)
EXPOSE 80

# Use supervisor to run nginx, php-fpm, and queue worker
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
