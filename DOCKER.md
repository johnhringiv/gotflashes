# Docker Deployment

Operational guide for deploying and managing GOT-FLASHES with Docker.

## Configuration

Most environment variables are pre-configured in `docker/.env.docker`. You only need to set:

**Required:**
- `APP_KEY` - Generate with: `docker run --rm php:8.2-cli php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"`

**Optional:**
- `BASIC_AUTH_USERNAME` - HTTP basic auth for staging/preview environments
- `BASIC_AUTH_PASSWORD` - Leave both empty to disable

The container runs on port 8080 (HTTP only). Put a reverse proxy in front for SSL termination.

## Container Startup

The entrypoint automatically:
1. Creates storage directories
2. Runs database migrations
3. Caches routes/config/views
4. Starts nginx, PHP-FPM, and queue worker via supervisor

## Management Commands

To Clear rebuild and run
```bash
docker stop gotflashes && docker rm gotflashes
docker build -t gotflashes:latest .
docker run -d --name gotflashes -p 8080:8080 -v $(pwd)/database:/var/www/html/database -v $(pwd)/storage/logs:/var/www/html/storage/logs --env-file .env gotflashes:latest
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
- **Supervisor**: Manages nginx, PHP-FPM, queue worker
- **SQLite**: Persisted via volume mounts

**Volume mounts (persistent data):**
- `./database` → `/var/www/html/database` (SQLite database)
- `./storage/logs` → `/var/www/html/storage/logs` (application logs)
