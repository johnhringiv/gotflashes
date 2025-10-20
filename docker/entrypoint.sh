#!/bin/sh
set -e

echo "Starting GOT-FLASHES application..."

# Only handle volume-mounted directories (they override image directories)
# If using volumes, recreate structure and fix permissions
if [ -d "/var/www/html/database" ]; then
    # Ensure database file exists (for volume mounts)
    if [ ! -f /var/www/html/database/database.sqlite ]; then
        echo "Creating SQLite database..."
        touch /var/www/html/database/database.sqlite
    fi
    chown -R www-data:www-data /var/www/html/database
    chmod -R 775 /var/www/html/database
fi

# Run migrations
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
