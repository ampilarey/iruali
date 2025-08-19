#!/bin/bash

# cPanel Git Deployment Script
# This script deploys your Laravel app to cPanel via Git

echo "🚀 Starting cPanel deployment..."

# Configuration
REPO_URL="https://github.com/ampilarey/iruali.git"
DEPLOY_DIR="public_html"
BRANCH="main"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
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
    exit 1
fi

# Build production assets
print_status "Building production assets..."
npm run build

if [ $? -ne 0 ]; then
    print_error "Asset build failed!"
    exit 1
fi

# Clear and cache Laravel
print_status "Optimizing Laravel for production..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Commit the built assets
print_status "Committing built assets..."
git add .
git commit -m "build: production assets for cPanel deployment"

# Push to GitHub
print_status "Pushing to GitHub..."
git push origin main

if [ $? -ne 0 ]; then
    print_error "Failed to push to GitHub!"
    exit 1
fi

print_status "✅ Deployment script completed!"
print_status "📝 Next steps:"
echo "   1. In cPanel, go to 'Git Version Control'"
echo "   2. Clone: $REPO_URL"
echo "   3. Set directory to: $DEPLOY_DIR"
echo "   4. Enable 'Auto Deploy'"
echo "   5. Click 'Update from Remote'"
echo ""
print_warning "Note: Make sure your cPanel has Git access enabled!"

echo ""
print_status "🌐 Your website should be live after the Git update in cPanel!"
