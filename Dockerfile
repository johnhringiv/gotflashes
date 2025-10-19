# Multi-stage build for Laravel application

# Stage 1: Build frontend assets
FROM node:20-alpine AS frontend

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
FROM php:8.2-fpm-alpine AS php-builder

WORKDIR /app

# Install build dependencies (only in builder stage)
RUN apk add --no-cache \
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
FROM php:8.2-fpm-alpine

# Copy compiled PHP extensions from builder
COPY --from=php-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Install only runtime dependencies (no build deps needed)
RUN apk add --no-cache \
    sqlite-libs \
    nginx \
    supervisor

# Set working directory
WORKDIR /var/www/html

# Copy only essential application directories and files
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY artisan composer.json composer.lock ./
COPY database/migrations ./database/migrations

# Copy PHP dependencies from builder stage
COPY --from=php-builder /app/vendor ./vendor

# Copy built assets from frontend stage
COPY --from=frontend /app/public/build ./public/build

# Create storage directories and set permissions
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache \
    && mkdir -p storage/app storage/logs \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 storage bootstrap/cache

# Create SQLite database directory
RUN mkdir -p /var/www/html/database \
    && touch /var/www/html/database/database.sqlite \
    && chown -R www-data:www-data /var/www/html/database \
    && chmod -R 775 /var/www/html/database

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
