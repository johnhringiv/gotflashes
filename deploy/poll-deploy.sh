#!/bin/sh
# Poll the deploy image tag and redeploy when its digest changes — and
# self-heal: an unchanged tag with the container not running (typical after a
# host reboot, before Docker's `restart unless-stopped` has settled) also
# triggers a redeploy.
#
# Runs from a scheduler on the production host (Synology DSM Task Scheduler,
# every 5 minutes, as root; elsewhere: cron). The host sits behind
# pfSense/Cloudflare with no inbound access, so deploys are pull-based: CI
# publishes to GHCR and this poller converges on it. An unchanged healthy tag
# costs one manifest check and one edge /up probe; layers only download when
# CI has published a new :latest, i.e. after a push to main. This is
# continuous deploy from main.
#
#   sh poll-deploy.sh [env-file]
#
# Exit codes: 0 = no-op or successful deploy; 1 = unhealthy (a deploy ran but
# the edge never came up, or the container is running yet the edge is
# unreachable — Cloudflare/HAProxy/DNS trouble a redeploy wouldn't fix).
# Configure the scheduler to notify on abnormal termination.
set -e

ENV_FILE="${1:-gotflashes.env}"
DIR=$(dirname "$0")

[ -f "$ENV_FILE" ] || { echo "missing $ENV_FILE"; exit 1; }

env_get() {
    grep -E "^$1=" "$ENV_FILE" | tail -1 | cut -d= -f2-
}

IMAGE="${IMAGE:-$(env_get IMAGE)}"
IMAGE="${IMAGE:-ghcr.io/johnhringiv/gotflashes:latest}"
CONTAINER="${CONTAINER:-$(env_get CONTAINER)}"
CONTAINER="${CONTAINER:-gotflashes}"
PUBLIC_URL="${PUBLIC_URL:-$(env_get PUBLIC_URL)}"

container_running() {
    [ "$(docker inspect "$1" --format '{{.State.Running}}' 2>/dev/null)" = "true" ]
}

# Three attempts, 5s apart — one blip shouldn't page or churn the container.
edge_healthy() {
    [ -n "$PUBLIC_URL" ] || return 0
    i=0
    while [ $i -lt 3 ]; do
        curl -sf -m 5 "$PUBLIC_URL/up" >/dev/null 2>&1 && return 0
        i=$((i + 1))
        sleep 5
    done
    return 1
}

# Before CI's first publish the tag may not exist; that is not an error.
if ! docker pull -q "$IMAGE" >/dev/null 2>&1; then
    echo "$(date -u +%FT%TZ) pull failed for $IMAGE (tag not published yet?); skipping"
    exit 0
fi

latest=$(docker image inspect "$IMAGE" --format '{{.Id}}')
running=$(docker inspect "$CONTAINER" --format '{{.Image}}' 2>/dev/null || echo none)

if [ "$latest" = "$running" ]; then
    if container_running "$CONTAINER"; then
        if edge_healthy; then
            exit 0
        fi
        echo "$(date -u +%FT%TZ) UNHEALTHY: $CONTAINER running but ${PUBLIC_URL}/up unreachable (Cloudflare/HAProxy/DNS?); not redeploying"
        exit 1
    fi
    echo "$(date -u +%FT%TZ) $CONTAINER not running on unchanged image (host rebooted?); redeploying"
else
    echo "$(date -u +%FT%TZ) new image for $IMAGE (running=$running -> $latest); deploying"
fi

# deploy.sh health-gates the new container on localhost:$PORT/up.
sh "$DIR/deploy.sh" "$ENV_FILE"

# Confirm the edge too (deploy.sh only checks localhost).
if [ -n "$PUBLIC_URL" ]; then
    if edge_healthy; then
        echo "$(date -u +%FT%TZ) deploy healthy at edge"
        exit 0
    fi
    echo "$(date -u +%FT%TZ) DEPLOY UNHEALTHY: ${PUBLIC_URL}/up not responding"
    exit 1
fi
echo "$(date -u +%FT%TZ) deployed (no PUBLIC_URL; edge check skipped)"
