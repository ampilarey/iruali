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

print_status "🚀 Starting development deployment for iruali..."

# Step 1: Pull latest code
print_status "Pulling latest code from git..."
git pull origin main

# Step 2: Install all dependencies (including dev)
print_status "Installing composer dependencies..."
composer install --optimize-autoloader

# Step 3: Install npm dependencies
print_status "Installing npm dependencies..."
if command -v npm &> /dev/null; then
    npm install
    print_success "npm dependencies installed"
else
    print_warning "npm not found. Skipping npm install."
fi

# Step 4: Build development assets
print_status "Building development assets..."
if command -v npm &> /dev/null; then
    npm run build
    print_success "Assets built successfully"
else
    print_warning "npm not found. Skipping asset build."
fi

# Step 5: Clear all caches
print_status "Clearing all caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

# Step 6: Run storage link
print_status "Creating storage link..."
php artisan storage:link || true

# Step 7: Run migrations safely
print_status "Running database migrations..."
php artisan migrate --force --no-interaction

# Step 8: Seed development data
print_status "Seeding development data..."
php artisan db:seed --force --no-interaction

# Step 9: Generate application key if not set
print_status "Checking application key..."
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    print_warning "Application key not set. Generating new key..."
    php artisan key:generate
fi

# Step 10: Clear caches for development
print_status "Clearing caches for development..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Step 11: Set development permissions
print_status "Setting file permissions..."
chmod -R 755 storage bootstrap/cache
chmod -R 644 storage/logs/*.log 2>/dev/null || true

# Step 12: Health check
print_status "Running development health check..."
if php artisan --version > /dev/null 2>&1; then
    print_success "Laravel application is accessible"
else
    print_error "Laravel application health check failed"
    exit 1
fi

# Step 13: Display development summary
echo ""
print_success "🎉 Development deployment completed successfully!"
echo ""
print_status "Deployment Summary:"
echo "✅ Code updated from git"
echo "✅ All dependencies installed (including dev)"
echo "✅ Assets built for development"
echo "✅ Database migrated safely"
echo "✅ Development data seeded"
echo "✅ Caches cleared for development"
echo "✅ Permissions set correctly"
echo ""
print_status "Development server commands:"
echo "• Start server: php artisan serve"
echo "• Watch assets: npm run dev"
echo "• Run tests: php artisan test"
echo "• Clear caches: php artisan optimize:clear"
echo ""
print_status "Useful development commands:"
echo "• View logs: tail -f storage/logs/laravel.log"
echo "• Tinker: php artisan tinker"
echo "• Route list: php artisan route:list"
echo "• Clear all: php artisan optimize:clear"
echo ""

print_success "Development deployment script completed! 🚀" 