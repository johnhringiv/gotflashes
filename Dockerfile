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

# Install PHP dependencies (production only, optimized, skip scripts)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-cache

# Manually copy Livewire assets to public directory
RUN mkdir -p public/vendor && \
    cp -r vendor/livewire/livewire/dist public/vendor/livewire

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

# Create a non-root user with UID/GID 1000 (commonly used for first non-root user)
# This matches many host systems' first user, reducing permission conflicts
RUN addgroup -g 1000 -S appuser && \
    adduser -u 1000 -S appuser -G appuser && \
    addgroup appuser www-data

# Configure nginx directories for appuser
RUN mkdir -p /var/cache/nginx /var/log/nginx /run && \
    touch /run/nginx.pid && \
    chown -R appuser:appuser /run/nginx.pid /var/cache/nginx /var/log/nginx

# Set working directory and change ownership to appuser
WORKDIR /var/www/html
RUN chown appuser:appuser /var/www/html

# Switch to appuser for all subsequent operations
USER appuser

# Create directory structure as appuser
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache \
    && mkdir -p storage/app storage/logs \
    && mkdir -p bootstrap/cache \
    && mkdir -p database

# Copy application files (owned by appuser since USER is set)
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY artisan composer.json composer.lock ./
COPY database/migrations ./database/migrations
COPY docker/.env.docker ./.env

# Copy dependencies (owned by appuser since USER is set)
COPY --from=php-builder /app/vendor ./vendor
COPY --from=php-builder /app/public/vendor/livewire ./public/vendor/livewire
COPY --from=frontend /app/public/build ./public/build

# Switch back to root to copy config files
USER root

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Copy PHP-FPM configuration
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-custom.conf

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port 8080 (nginx listens on non-privileged port for Synology compatibility)
EXPOSE 8080

# Switch to appuser for runtime
USER appuser

# Use supervisor to run nginx, php-fpm, and queue worker
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
