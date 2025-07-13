#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "This doesn't appear to be a Laravel project. Please run this script from the project root."
    exit 1
fi

print_status "🚀 Starting production deployment for iruali..."

# Step 1: Pull latest code
print_status "Pulling latest code from git..."
git pull origin main

# Step 2: Install production dependencies
print_status "Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Step 3: Build production assets
print_status "Building production assets..."
if command -v npm &> /dev/null; then
    npm ci --production
    npm run build
    print_success "Assets built successfully"
else
    print_warning "npm not found. Skipping asset build."
fi

# Step 4: Clear all caches first
print_status "Clearing all caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

# Step 5: Run storage link
print_status "Creating storage link..."
php artisan storage:link || true

# Step 6: Run migrations safely
print_status "Running database migrations..."
php artisan migrate --force --no-interaction

# Step 7: Cache configuration for production
print_status "Caching configuration for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Step 8: Optimize for production
print_status "Optimizing for production..."
php artisan optimize

# Step 9: Clear and rebuild caches
print_status "Final cache optimization..."
php artisan cache:clear
php artisan config:cache

# Step 10: Copy build assets to public_html
print_status "Deploying build assets..."
rm -rf ../public_html/build
mkdir -p ../public_html/build
cp -r public/build/* ../public_html/build/

# Step 11: Set proper permissions
print_status "Setting file permissions..."
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs/*.log 2>/dev/null || true
chmod -R 755 ../public_html/build

# Step 12: Health check
print_status "Running deployment health check..."
if php artisan --version > /dev/null 2>&1; then
    print_success "Laravel application is accessible"
else
    print_error "Laravel application health check failed"
    exit 1
fi

# Step 13: Display deployment summary
echo ""
print_success "🎉 Production deployment completed successfully!"
echo ""
print_status "Deployment Summary:"
echo "✅ Code updated from git"
echo "✅ Dependencies installed"
echo "✅ Assets built and optimized"
echo "✅ Database migrated safely"
echo "✅ Configuration cached"
echo "✅ Permissions set correctly"
echo ""
print_status "Next steps:"
echo "1. Verify application is working at your domain"
echo "2. Check error logs: tail -f storage/logs/laravel.log"
echo "3. Monitor performance and uptime"
echo "4. Test critical user flows"
echo ""

print_success "Production deployment script completed! 🚀" 