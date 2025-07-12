#!/bin/bash

# iruali cPanel Deployment Script
# Run this script in your Laravel project directory on cPanel

echo "🚀 Starting iruali cPanel deployment..."

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script in your Laravel project root."
    exit 1
fi

# Install Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

# Set proper permissions
echo "🔐 Setting file permissions..."
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs/*.log 2>/dev/null || true

# Clear all caches
echo "🧹 Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Generate app key if not exists
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    echo "🔑 Generating application key..."
    php artisan key:generate
fi

# Create storage link
echo "🔗 Creating storage symlink..."
php artisan storage:link

# Run migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Build assets if Node.js is available
if command -v npm &> /dev/null; then
    echo "🎨 Building frontend assets..."
    npm install --production
    npm run build
else
    echo "⚠️ Node.js not found. Please build assets locally and upload public/build/ to public_html/"
fi

echo "✅ Deployment completed successfully!"
echo ""
echo "📋 Next steps:"
echo "1. Copy contents of public/ folder to public_html/"
echo "2. Edit public_html/index.php to point to your Laravel app"
echo "3. Configure your .env file with production settings"
echo "4. Test your website"
echo ""
echo "🔧 If you encounter issues, check storage/logs/laravel.log" 