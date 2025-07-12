#!/bin/bash

# iruali cPanel Deployment Script
# This script updates the website from Git and rebuilds assets

echo "🚀 Starting iruali deployment..."

# Set the Laravel application directory
LARAVEL_DIR="/home/$(whoami)/iruali"
PUBLIC_DIR="/home/$(whoami)/public_html"

# Check if Laravel directory exists
if [ ! -d "$LARAVEL_DIR" ]; then
    echo "❌ Laravel directory not found: $LARAVEL_DIR"
    echo "Please make sure your Laravel app is in the correct location"
    exit 1
fi

# Navigate to Laravel directory
cd "$LARAVEL_DIR"

echo "📁 Working directory: $(pwd)"

# Pull latest changes from Git
echo "📥 Pulling latest changes from Git..."
git pull origin main

if [ $? -ne 0 ]; then
    echo "❌ Git pull failed"
    exit 1
fi

echo "✅ Git pull successful"

# Clear all Laravel caches
echo "🧹 Clearing Laravel caches..."
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

echo "✅ Caches cleared"

# Install/update Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

if [ $? -ne 0 ]; then
    echo "❌ Composer install failed"
    exit 1
fi

echo "✅ Composer dependencies installed"

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

if [ $? -ne 0 ]; then
    echo "❌ Database migration failed"
    exit 1
fi

echo "✅ Database migrations completed"

# Check if Node.js is available
if command -v node &> /dev/null && command -v npm &> /dev/null; then
    echo "📦 Installing Node.js dependencies..."
    npm install
    
    if [ $? -ne 0 ]; then
        echo "❌ npm install failed"
        exit 1
    fi
    
    echo "✅ Node.js dependencies installed"
    
    echo "🔨 Building assets..."
    npm run build
    
    if [ $? -ne 0 ]; then
        echo "❌ Asset build failed"
        exit 1
    fi
    
    echo "✅ Assets built successfully"
else
    echo "⚠️ Node.js not available on server"
    echo "📤 Please upload the public/build/ folder manually"
fi

# Set proper permissions
echo "🔐 Setting permissions..."
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs/*.log 2>/dev/null || true

echo "✅ Permissions set"

# Create storage link if it doesn't exist
if [ ! -L "$PUBLIC_DIR/storage" ]; then
    echo "🔗 Creating storage link..."
    php artisan storage:link
    echo "✅ Storage link created"
fi

echo "🎉 Deployment completed successfully!"
echo "🌐 Your website should now be updated with the latest changes"
echo "📱 Don't forget to test the new header layout on both desktop and mobile!" 