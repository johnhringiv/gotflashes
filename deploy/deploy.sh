#!/bin/sh
# Single-container deploy for gotflashes (no compose).
#
# Deploys the newest main build (:latest, published by CI on every push to
# main). Secrets and host-specific settings live in an env file next to this
# script (copy gotflashes.env.example and fill it in, chmod 600); everything
# else is baked into the image (docker/.env.docker).
#
# Usage:
#   sudo sh deploy.sh [env-file]                 # pull IMAGE and replace the container
#   sudo sh deploy.sh [env-file] <image-tarball> # load a saved image instead of pulling (offline)
#
# The app runs as ONE container (its SQLite database can't be shared by two
# instances mid-migration), so a deploy is a brief outage while the new
# container boots and migrates — the Cloudflare maintenance Worker shows the
# branded "Careening the Hull" page for those seconds. The new container is
# health-gated on /up: if it never comes up, this script exits non-zero.
#
# Idempotent: re-running replaces the container; the database, logs, and
# backups live in DATA_DIR bind mounts and persist across deploys.
set -e

ENV_FILE="${1:-gotflashes.env}"
IMAGE_TAR="${2:-}"

[ -f "$ENV_FILE" ] || { echo "missing $ENV_FILE (copy gotflashes.env.example and fill it in)"; exit 1; }

env_get() {
    grep -E "^$1=" "$ENV_FILE" | tail -1 | cut -d= -f2-
}

# Each setting: $VAR from the environment > VAR= line in the env file > default.
# The env override enables one-offs, e.g. roll back with
#   sudo IMAGE=ghcr.io/johnhringiv/gotflashes:sha-abc123 sh deploy.sh
IMAGE="${IMAGE:-$(env_get IMAGE)}"
IMAGE="${IMAGE:-ghcr.io/johnhringiv/gotflashes:latest}"
CONTAINER="${CONTAINER:-$(env_get CONTAINER)}"
CONTAINER="${CONTAINER:-gotflashes}"
PORT="${PORT:-$(env_get PORT)}"
PORT="${PORT:-8087}"   # HAProxy backend port
DATA_DIR="${DATA_DIR:-$(env_get DATA_DIR)}"
[ -n "$DATA_DIR" ] || { echo "DATA_DIR is not set in $ENV_FILE"; exit 1; }

if [ -n "$IMAGE_TAR" ]; then
    docker load -i "$IMAGE_TAR"
else
    docker pull "$IMAGE" || echo "pull failed; will use local image $IMAGE if present"
fi

# Persistent data lives on the host; the container runs as UID/GID 1000
# (appuser), so newly created dirs must be chowned to it. Existing dirs are
# left alone — they already carry live data with correct ownership.
for d in "$DATA_DIR/data" "$DATA_DIR/logs" "$DATA_DIR/backup"; do
    if [ ! -d "$d" ]; then
        mkdir -p "$d"
        chown 1000:1000 "$d"
    fi
done

echo "$(date -u +%FT%TZ) recreating $CONTAINER on :$PORT from $IMAGE"
# Graceful stop first (SIGTERM -> supervisord shuts its children down and exits
# 0) so Synology's Container Manager doesn't report "stopped unexpectedly" (a
# SIGKILL exit, 137). The -t 30 is load-bearing: supervisord's orderly shutdown
# takes ~2s, but the container's default stop grace is only ~1s, so the stock
# `docker stop` SIGKILLs it mid-shutdown. rm -f then removes the stopped container.
docker stop -t 30 "$CONTAINER" >/dev/null 2>&1 || true
docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
docker run -d --name "$CONTAINER" --restart unless-stopped \
    `# no --cpus: DSM kernels lack the CFS scheduler` \
    `# --stop-timeout 30: bake the grace into the container so a stop from the` \
    `# Synology GUI (or any bare 'docker stop') also gets a clean exit 0, not 137` \
    --stop-timeout 30 \
    -p "$PORT:8080" \
    -v "$DATA_DIR/data:/var/www/html/database/data" \
    -v "$DATA_DIR/logs:/var/www/html/storage/logs" \
    -v "$DATA_DIR/backup:/var/www/html/storage/app/backups" \
    --env-file "$ENV_FILE" \
    "$IMAGE" >/dev/null

# Health-gate: the entrypoint runs migrations before nginx starts serving, so
# give it up to ~2 min. /up is Laravel's health endpoint (basic-auth exempt).
i=0
while [ $i -lt 24 ]; do
    if curl -sf -m 5 "http://localhost:$PORT/up" >/dev/null 2>&1; then
        echo "$(date -u +%FT%TZ) $CONTAINER healthy on :$PORT"
        docker image prune -f >/dev/null 2>&1 || true
        docker ps --filter "name=$CONTAINER" --format '{{.Names}}: {{.Status}}'
        echo "$(date -u +%FT%TZ) deployed $IMAGE"
        PUBLIC_URL="${PUBLIC_URL:-$(env_get PUBLIC_URL)}"
        [ -n "$PUBLIC_URL" ] && echo "verify: curl $PUBLIC_URL/up"
        exit 0
    fi
    i=$((i + 1))
    sleep 5
done

echo "$(date -u +%FT%TZ) FAILED: $CONTAINER did not become healthy on :$PORT"
docker logs "$CONTAINER" 2>&1 | tail -20
exit 1
