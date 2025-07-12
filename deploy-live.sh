#!/bin/bash

# iruali Live Deployment Script for cPanel
# Run this script in your cPanel terminal or SSH

echo "🚀 Starting iruali deployment..."

# Navigate to your repository directory
cd /home/$(whoami)/repositories/iruali

# Pull latest changes from Git
echo "📥 Pulling latest changes from Git..."
git pull origin main

# Install/update PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Clear Laravel caches
echo "🧹 Clearing Laravel caches..."
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Optimize for production
echo "⚡ Optimizing for production..."
php artisan optimize

# Copy build assets from iruali/public to public_html
echo "📁 Copying build assets to public_html..."
cp -r public/build/* /home/$(whoami)/public_html/build/

# Set proper permissions
echo "🔐 Setting permissions..."
chmod -R 755 /home/$(whoami)/public_html/build/
chmod -R 775 storage bootstrap/cache

echo "✅ Deployment completed successfully!"
echo "🌐 Your live site should now be updated with the latest changes." 