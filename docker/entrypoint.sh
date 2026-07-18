#!/bin/sh
set -e

echo "Starting G.O.T. Flashes application..."

# Ensure the SQLite database file exists. DB_DATABASE is only in the shell
# environment when passed at runtime (--env-file); the default must match the
# baked .env (docker/.env.docker). In production the file already exists on
# the bind mount, so this only fires on fresh/ephemeral runs (e.g. CI smoke).
DB_FILE="${DB_DATABASE:-/var/www/html/database/data/database.sqlite}"
if [ ! -f "$DB_FILE" ]; then
    echo "Creating SQLite database at $DB_FILE..."
    mkdir -p "$(dirname "$DB_FILE")"
    touch "$DB_FILE"
    chmod 664 "$DB_FILE"
fi

# Run migrations (includes reference data seeding)
echo "Running database migrations..."
if ! php artisan migrate --force --no-interaction; then
    echo "Migration failed! Check your database configuration."
    exit 1
fi

# Optimize Laravel
echo "Optimizing application..."
php artisan optimize

echo "Application ready!"

exec "$@"
