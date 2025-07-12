#!/bin/bash
set -e

echo "[iruali] Pulling latest code from git..."
git pull origin main

echo "[iruali] Installing composer dependencies..."
composer install --no-dev --optimize-autoloader

echo "[iruali] Running migrations..."
php artisan migrate --force

echo "[iruali] Running storage:link..."
php artisan storage:link || true

echo "[iruali] Clearing and optimizing caches..."
php artisan cache:clear
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan view:clear
php artisan view:cache

# Delete existing build folder from public_html and copy new build assets
echo "[iruali] Removing old build assets from public_html..."
rm -rf ../public_html/build

echo "[iruali] Copying build assets to public_html..."
mkdir -p ../public_html/build
cp -r public/build/* ../public_html/build/

# Set permissions (adjust paths as needed)
echo "[iruali] Setting permissions..."
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs/*.log 2>/dev/null || true

echo "[iruali] Deployment completed successfully!" 