#!/bin/sh
set -e

echo "Starting G.O.T. Flashes application..."

# Ensure database file exists if it doesn't already
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "Creating SQLite database..."
    touch /var/www/html/database/database.sqlite
    chmod 664 /var/www/html/database/database.sqlite
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
