#!/bin/bash
set -e

echo "=== PALECO OTRS-CRM Container Initialization ==="

# Discover packages with runtime environment
echo "Discovering Laravel packages..."
php artisan package:discover --ansi

# Execute database migrations
echo "Executing database migrations against TiDB Cloud..."
php artisan migrate --force

# Optimize Laravel for production
echo "Caching configuration, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Configure Apache port at runtime based on Render's dynamic $PORT
PORT_TO_USE="${PORT:-80}"
echo "Configuring Apache to listen on port ${PORT_TO_USE}..."
sed -i "s/Listen [0-9]\+/Listen ${PORT_TO_USE}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]\+>/<VirtualHost \*:${PORT_TO_USE}>/" /etc/apache2/sites-available/*.conf

# Start Apache in the foreground
echo "Starting Apache web server on port ${PORT_TO_USE}..."
exec apache2-foreground


