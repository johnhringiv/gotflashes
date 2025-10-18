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
# Create required directories
mkdir -p database storage/app storage/logs

# Build the Docker image
docker build -t gotflashes:latest .

# Run the container
docker run -d \
  --name gotflashes \
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

### Deployment Workflow

```bash
# Initial deployment
mkdir -p database storage/app storage/logs
docker build -t gotflashes:latest .
docker run -d --name gotflashes --restart unless-stopped \
  -p 8080:80 \
  -v $(pwd)/database:/var/www/html/database \
  -v $(pwd)/storage/app:/var/www/html/storage/app \
  -v $(pwd)/storage/logs:/var/www/html/storage/logs \
  --env-file .env \
  gotflashes:latest

# Redeploy (updates)
docker stop gotflashes
docker rm gotflashes
docker build -t gotflashes:latest .
docker run -d --name gotflashes --restart unless-stopped \
  -p 8080:80 \
  -v $(pwd)/database:/var/www/html/database \
  -v $(pwd)/storage/app:/var/www/html/storage/app \
  -v $(pwd)/storage/logs:/var/www/html/storage/logs \
  --env-file .env \
  gotflashes:latest
```

### Resetting Permissions
```bash
sudo chown -R jhring:jhring database/ storage/ bootstrap/cache/
```

---

## Production Deployment

### Behind HAProxy (Recommended)

The Docker container is designed to run behind HAProxy for SSL termination and load balancing.

#### HAProxy Configuration Example

```haproxy
frontend http_front
    bind *:80
    bind *:443 ssl crt /etc/haproxy/certs/your-cert.pem

    # Redirect HTTP to HTTPS
    redirect scheme https code 301 if !{ ssl_fc }

    # Forward to backend
    default_backend gotflashes_backend

backend gotflashes_backend
    balance roundrobin
    option httpchk GET /

    # Forward real IP and protocol
    http-request set-header X-Forwarded-For %[src]
    http-request set-header X-Forwarded-Proto https if { ssl_fc }

    server gotflashes1 127.0.0.1:8080 check
```

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

### Production Checklist

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Generate unique `APP_KEY`
- [ ] Set correct `APP_URL` with your domain
- [ ] Configure mail settings (if using notifications)
- [ ] Set up HAProxy or reverse proxy for SSL
- [ ] Configure firewall rules
- [ ] Set up backups for `database/` and `storage/` directories
- [ ] Monitor logs via `docker logs`

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

### Key Files

- **Dockerfile**: Multi-stage build (Node for frontend, PHP for backend)
- **.env.example**: Environment template - copy to `.env` and configure
- **docker/entrypoint.sh**: Runs migrations and sets up environment on start
- **docker/nginx.conf**: Nginx configuration (trusts HAProxy for SSL)
- **docker/php-fpm.conf**: PHP-FPM performance settings
- **docker/supervisord.conf**: Manages nginx, PHP-FPM, and queue worker

---

## Troubleshooting

### Container Won't Start

Check logs for errors:
```bash
docker logs gotflashes
```

Common issues:
- Missing or invalid `APP_KEY` in `.env`
- Port 8080 already in use (change with `-p XXXX:80`)
- Volume mount directories don't exist (create with `mkdir -p database storage/app storage/logs`)

### View All Logs

```bash
# Application logs (stdout/stderr)
docker logs -f gotflashes

# Nginx access logs
docker exec gotflashes tail -f /var/log/nginx/access.log

# Laravel application logs
docker exec gotflashes tail -f storage/logs/laravel.log
```

### Database Permission Issues

If you see SQLite permission errors:

```bash
docker exec gotflashes chown -R www-data:www-data /var/www/html/database
docker exec gotflashes chmod -R 775 /var/www/html/database
```

### Clear Laravel Caches

```bash
docker exec gotflashes php artisan config:clear
docker exec gotflashes php artisan route:clear
docker exec gotflashes php artisan view:clear
docker exec gotflashes php artisan cache:clear
```

### Rebuild from Scratch

```bash
make clean    # Remove container and image
make deploy   # Rebuild and deploy

# Or manually:
docker stop gotflashes
docker rm gotflashes
docker rmi gotflashes:latest
./deploy.sh
```

### Common Issues

**"Address already in use" error**
- Another service is using port 8080
- Solution: Use different port: `docker run -p 9000:80 ...`

**"Permission denied" on database**
- Container can't write to database file
- Solution: Check file permissions (see Database Permission Issues above)

**Queue jobs not running**
- Queue worker should start automatically
- Check with: `docker exec gotflashes ps aux | grep queue`

---

## Security & Performance

### Security Features

- **No SSL in container**: SSL termination handled by HAProxy
- **Real IP forwarding**: Configured for HAProxy (`X-Forwarded-For`)
- **Security headers**: Set in nginx configuration
- **Production mode**: Debug output disabled
- **Proper permissions**: Files owned by `www-data` user
- **Environment isolation**: Secrets in `.env` (not committed to git)

### Performance Optimizations

- **PHP-FPM**: Dynamic process management (scales with load)
- **OPcache**: PHP opcache enabled for better performance
- **Queue worker**: Runs automatically via supervisor
- **Static asset caching**: 1-year cache headers
- **Optimized autoloader**: Composer `--optimize-autoloader`
- **Cached routes/views/config**: Pre-cached on container start
- **Minimal image**: Alpine Linux base (~150MB total)

### Monitoring

Watch logs in real-time:
```bash
docker logs -f gotflashes
```

Check container health:
```bash
docker ps
docker stats gotflashes
```

Check running processes:
```bash
docker exec gotflashes ps aux
```

---

## Additional Resources

- **Main Documentation**: See `README.md` for application overview
- **Development Setup**: See `README.md` for local development with `composer dev`
- **Contributing**: See `docs/CONTRIBUTING.md`
- **HAProxy Documentation**: https://www.haproxy.org/
- **Docker Documentation**: https://docs.docker.com/

---

## Need Help?

1. Check the troubleshooting section above
2. Review container logs: `docker logs gotflashes`
3. Access container shell for debugging: `docker exec -it gotflashes sh`
4. Check Laravel logs: `docker exec gotflashes tail -f storage/logs/laravel.log`
