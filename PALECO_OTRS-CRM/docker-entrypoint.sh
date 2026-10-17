#!/bin/bash
set -e

echo "=== PALECO OTRS-CRM Container Initialization ==="

# Execute database migrations
echo "Executing database migrations against TiDB Cloud..."
php artisan migrate --force

# Optimize Laravel for production
echo "Caching configuration, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Apache in the foreground
echo "Starting Apache web server on port ${PORT:-80}..."
exec apache2-foreground
