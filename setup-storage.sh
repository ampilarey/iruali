#!/bin/bash

# cPanel Storage Setup Script
# This script creates the necessary storage directories and sets permissions

echo "🔧 Setting up Laravel storage directories for cPanel..."

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "This doesn't appear to be a Laravel project directory!"
    print_error "Please run this script from your Laravel project root."
    exit 1
fi

# Create storage directories
print_status "Creating storage directories..."
mkdir -p storage/app/public
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Create .gitignore files
print_status "Creating .gitignore files..."
echo "*" > storage/app/.gitignore
echo "!.gitignore" >> storage/app/.gitignore

echo "*" > storage/app/public/.gitignore
echo "!.gitignore" >> storage/app/public/.gitignore

echo "*" > storage/framework/.gitignore
echo "!.gitignore" >> storage/framework/.gitignore

echo "*" > storage/framework/cache/.gitignore
echo "!.gitignore" >> storage/framework/cache/.gitignore

echo "*" > storage/framework/sessions/.gitignore
echo "!.gitignore" >> storage/framework/sessions/.gitignore

echo "*" > storage/framework/views/.gitignore
echo "!.gitignore" >> storage/framework/views/.gitignore

echo "*" > storage/logs/.gitignore
echo "!.gitignore" >> storage/logs/.gitignore

echo "*" > bootstrap/cache/.gitignore
echo "!.gitignore" >> bootstrap/cache/.gitignore

# Set permissions
print_status "Setting directory permissions..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache
chmod -R 775 storage/app/public
chmod -R 775 storage/framework/cache
chmod -R 775 storage/framework/sessions
chmod -R 775 storage/framework/views
chmod -R 775 storage/logs

# Create storage link
print_status "Creating storage link..."
php artisan storage:link

# Clear and cache
print_status "Optimizing Laravel..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

print_status "✅ Storage setup completed!"
print_status "📝 Next steps:"
echo "   1. Commit these changes: git add . && git commit -m 'setup: storage directories and permissions'"
echo "   2. Push to GitHub: git push origin main"
echo "   3. In cPanel, click 'Update from Remote'"
echo ""
print_warning "Note: Make sure your cPanel has the correct PHP version (8.1+) for Laravel 12!"

echo ""
print_status "🌐 Your website should now work properly after the Git update!"
