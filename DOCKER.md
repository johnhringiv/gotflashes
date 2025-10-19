# Docker Deployment Guide

Complete guide for deploying the GOT-FLASHES application using Docker.

## Table of Contents

- [Quick Start](#quick-start)
- [Architecture](#architecture)
- [Management Commands](#management-commands)
- [Production Deployment](#production-deployment)
- [Configuration Files](#configuration-files)
- [Troubleshooting](#troubleshooting)
- [Security & Performance](#security--performance)

---

## Quick Start

Deploy in 3 simple steps - no PHP/Composer/Node required on host machine!

### 1. Configure Environment

```bash
# Copy environment template
cp .env.example .env

# Generate APP_KEY (edit .env and paste this value)
docker run --rm php:8.2-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

Edit `.env` and update:
- `APP_KEY` - paste the generated key
- `APP_URL` - set to your domain (e.g., `https://gotflashes.yourdomain.com`)
- `APP_ENV` - set to `production` for production deployments
- `APP_DEBUG` - set to `false` for production deployments
- Mail settings (optional, for notifications)

### 2. Build and Deploy

```bash
docker stop gotflashes && docker rm gotflashes
docker build -t gotflashes:latest .
docker run -d --name gotflashes \
  -p 8080:80 \
  -v $(pwd)/database:/var/www/html/database \
  -v $(pwd)/storage/app:/var/www/html/storage/app \
  -v $(pwd)/storage/logs:/var/www/html/storage/logs \
  --env-file .env \
  gotflashes:latest
```

The container automatically:
- Runs database migrations on startup
- Starts nginx, PHP-FPM, and queue worker
- Exposes on port 8080

### 3. Access

Open: http://localhost:8080

---

## Architecture

The Docker setup includes:

- **Multi-stage build**: Separates frontend build from production image
- **Alpine-based PHP 8.2**: Minimal footprint (~150MB final image)
- **Nginx**: Web server configured for Laravel
- **PHP-FPM**: PHP FastCGI Process Manager
- **Supervisor**: Process manager running nginx, PHP-FPM, and queue worker
- **SQLite**: Persisted via volume mounts

### What Happens on Container Start

The entrypoint script automatically:
1. Creates required storage directories
2. Creates SQLite database file if it doesn't exist
3. Sets correct file permissions
4. **Runs database migrations** (safe to run on every startup)
5. Caches routes, config, and views for performance
6. Starts nginx, PHP-FPM, and queue worker via supervisor

**You don't need to run migrations manually** - they happen automatically!

### Volume Mounts (Persistent Data)

These directories are mounted and persisted:

```bash
./database                → /var/www/html/database        # SQLite database
./storage/app            → /var/www/html/storage/app     # Uploaded files
./storage/logs           → /var/www/html/storage/logs    # Application logs
```

---

## Management Commands

### Container Management

```bash
# View logs
docker logs -f gotflashes

# Stop/start container
docker stop gotflashes
docker start gotflashes

# Graceful shutdown (see Graceful Shutdown section)
docker stop --time=3630 gotflashes

# Remove container
docker rm gotflashes

# Access shell
docker exec -it gotflashes sh

# Run artisan commands
docker exec gotflashes php artisan tinker
docker exec gotflashes php artisan cache:clear
docker exec gotflashes php artisan migrate:status

# Check container health
docker ps -f name=gotflashes
docker stats gotflashes

# Check running processes
docker exec gotflashes ps aux
```

### Resetting Permissions
```bash
sudo chown -R jhring:jhring database/ storage/ bootstrap/cache/
```

---

## Production Deployment

### Behind HAProxy (Recommended)

The Docker container is designed to run behind HAProxy for SSL termination

#### Production Environment Variables

Required settings in `.env`:

```bash
APP_NAME="GOT-FLASHES"
APP_ENV=production
APP_KEY="base64:..."              # Generate with: php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://your-domain.com   # Your actual domain

DB_CONNECTION=sqlite
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

LOG_CHANNEL=stack
LOG_LEVEL=info

# Mail configuration (optional)
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS="noreply@gotflashes.yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Port Configuration

- **Container port**: 80 (HTTP only - no SSL in container)
- **Host port**: Map to any available port (default: 8080)
- **Change port**: Use `-p YOUR_PORT:80` when running the container
---

## Configuration Files

### Project Structure

```
/
├── Dockerfile              # Multi-stage build definition
├── .dockerignore           # Files excluded from build
├── .env.example            # Environment template
└── docker/                 # Docker configuration files
    ├── entrypoint.sh       # Container startup script
    ├── nginx.conf          # Nginx configuration
    ├── php-fpm.conf        # PHP-FPM tuning
    └── supervisord.conf    # Process manager config
```

---

## Troubleshooting

### Clear Laravel Caches

```bash
docker exec gotflashes php artisan config:clear
docker exec gotflashes php artisan route:clear
docker exec gotflashes php artisan view:clear
docker exec gotflashes php artisan cache:clear
```
---

## Security & Performance

### Security Features

- **No SSL in container**: SSL termination handled by HAProxy
- **Real IP forwarding**: Configured for HAProxy (`X-Forwarded-For`)
- **Security headers**: Set in nginx configuration
- **Production mode**: Debug output disabled
- **Proper permissions**: Files owned by `www-data` user
- **Environment isolation**: Secrets in `.env` (not committed to git)
