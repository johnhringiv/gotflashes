# Docker Deployment

Operational guide for deploying and managing G.O.T. Flashes with Docker.

## Configuration

Most environment variables are pre-configured in `docker/.env.docker`. You only need to set:

**Required:**
- `APP_KEY` - Generate with: `docker run --rm php:8.2-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"`
- `TRUSTED_PROXY_IP` - IP of reverse proxy (e.g., HAProxy) to trust X-Forwarded-* headers for correct client IP and HTTPS detection
- `RESEND_KEY` - API key for [Resend](https://resend.com) email service. Required for email verification and notifications. Get your key from the Resend dashboard.

**Optional:**
- `BASIC_AUTH_USERNAME` - HTTP basic auth for staging/preview environments
- `BASIC_AUTH_PASSWORD` - Leave both empty to disable
- `START_YEAR` - Controls grace period logic for logging previous year entries (default: `2026`). The January grace period (allowing previous year entries) only applies when current year > START_YEAR. Example: With `START_YEAR=2026`, January 2026 allows only 2026 entries, but January 2027+ allows December 2026 entries.

The container runs on port 8080 (HTTP only). Put a reverse proxy in front for SSL termination.

## Container Startup

The entrypoint automatically:
1. Creates storage directories
2. Runs database migrations
3. Caches routes/config/views
4. Starts nginx, PHP-FPM, queue worker, and scheduler via supervisor

## Management Commands

To Clear rebuild and run
```bash
docker stop gotflashes && docker rm gotflashes
docker build -t gotflashes:latest .
docker run -d --name gotflashes -p 8080:8080 \
  -e DB_DATABASE=/var/www/html/database/data/database.sqlite \
  -v $(pwd)/database/data:/var/www/html/database/data \
  -v $(pwd)/storage/logs:/var/www/html/storage/logs \
  -v $(pwd)/backups:/var/www/html/storage/app/backups \
  --env-file .env gotflashes:latest
```

To Save
```bash
docker save -o gotflashes.img gotflashes:latest
```

Misc

```bash
# View logs
docker logs -f gotflashes

# Restart container
docker restart gotflashes

# Run artisan commands
docker exec gotflashes php artisan tinker
docker exec gotflashes php artisan migrate:status
docker exec gotflashes php artisan queue:work

# Check container health
docker ps -f name=gotflashes
docker stats gotflashes

# Access shell
docker exec -it gotflashes sh
```

## Architecture

- **Alpine Linux + PHP 8.4**: ~175MB image
- **Multi-stage build**: Node build → production image
- **Supervisor**: Manages nginx, PHP-FPM, queue worker, scheduler
- **SQLite**: Persisted via volume mounts

**Volume mounts (persistent data):**
- `./database/data` → `/var/www/html/database/data` (SQLite database + WAL files)
- `./storage/logs` → `/var/www/html/storage/logs` (application logs)
- `./backups` → `/var/www/html/storage/app/backups` (daily database backups, 90-day retention)

**Why mount `database/data` instead of `database`?**
- Mounting `./database` would overwrite the container's migrations folder
- Using a `data` subdirectory keeps database files persistent while preserving migrations in the container
- SQLite WAL files (`.sqlite-wal`, `.sqlite-shm`) automatically persist alongside the main database file
