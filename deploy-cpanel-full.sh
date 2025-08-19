#!/bin/bash

# Complete cPanel Deployment Script for iruali
# This script handles the full deployment process

echo "🚀 Starting complete cPanel deployment for iruali..."

# Configuration
REPO_URL="https://github.com/ampilarey/iruali.git"
DEPLOY_DIR="public_html"
BRANCH="main"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "This doesn't appear to be a Laravel project directory!"
    print_error "Please run this script from your Laravel project root."
    exit 1
fi

# Step 1: Build production assets
print_step "Step 1: Building production assets..."
npm run build

if [ $? -ne 0 ]; then
    print_error "Failed to build assets! Please check your npm configuration."
    exit 1
fi

print_status "Assets built successfully!"

# Step 2: Clear and rebuild Laravel caches
print_step "Step 2: Clearing and rebuilding Laravel caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Rebuild caches for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

print_status "Laravel caches cleared and rebuilt!"

# Step 3: Check if build directory exists
print_step "Step 3: Verifying build directory..."
if [ ! -d "public/build" ]; then
    print_error "Build directory not found! Something went wrong with the build process."
    exit 1
fi

print_status "Build directory verified!"

# Step 4: Create deployment package
print_step "Step 4: Creating deployment package..."
DEPLOY_PACKAGE="iruali-deploy-$(date +%Y%m%d-%H%M%S).tar.gz"

# Create tar.gz of the entire project (excluding unnecessary files)
tar --exclude='.git' \
    --exclude='node_modules' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='.env.example' \
    --exclude='README.md' \
    --exclude='*.log' \
    -czf "$DEPLOY_PACKAGE" .

if [ $? -ne 0 ]; then
    print_error "Failed to create deployment package!"
    exit 1
fi

print_status "Deployment package created: $DEPLOY_PACKAGE"

# Step 5: Display deployment instructions
print_step "Step 5: Deployment Instructions"
echo ""
echo "📋 Manual Deployment Steps for cPanel:"
echo ""
echo "1. Upload the file '$DEPLOY_PACKAGE' to your cPanel File Manager"
echo "2. Extract it in your 'public_html' directory"
echo "3. Set proper permissions:"
echo "   - chmod -R 755 storage"
echo "   - chmod -R 755 bootstrap/cache"
echo "   - chmod -R 775 storage/app/public"
echo "   - chmod -R 775 storage/framework/cache"
echo "   - chmod -R 775 storage/framework/sessions"
echo "   - chmod -R 775 storage/framework/views"
echo "   - chmod -R 775 storage/logs"
echo ""
echo "4. Copy your .env file to the extracted directory"
echo "5. Update your .env file with production settings:"
echo "   - APP_ENV=production"
echo "   - APP_DEBUG=false"
echo "   - APP_URL=https://iruali.mv"
echo ""
echo "6. Test your website at https://iruali.mv"
echo ""

# Step 6: Git operations
print_step "Step 6: Preparing for Git deployment..."
git add .
git commit -m "feat(deployment): complete cPanel deployment setup with Vite configuration

- Add custom Vite service provider for cPanel asset paths
- Update vite.config.js for production deployment
- Create proper .htaccess with security headers
- Add comprehensive deployment script
- Fix asset manifest path issues for cPanel deployment"

print_status "Changes committed to Git!"

# Step 7: Push to GitHub
print_step "Step 7: Pushing to GitHub..."
git push origin main

if [ $? -eq 0 ]; then
    print_status "Successfully pushed to GitHub!"
else
    print_warning "Git push failed. You may need to pull changes first."
    print_warning "Run: git pull origin main"
fi

# Step 8: Final instructions
print_step "Step 8: Next Steps"
echo ""
echo "✅ Local deployment package created: $DEPLOY_PACKAGE"
echo "✅ Changes committed and pushed to GitHub"
echo ""
echo "🚀 To deploy to cPanel:"
echo "1. Go to cPanel → Git Version Control"
echo "2. Update your repository from remote"
echo "3. Or manually upload and extract $DEPLOY_PACKAGE"
echo ""
echo "🔧 If you still get asset errors:"
echo "1. Check that the build folder is in public_html/build"
echo "2. Verify manifest.json exists and has correct paths"
echo "3. Check file permissions (755 for folders, 644 for files)"
echo ""

print_status "Deployment preparation complete! 🎉"
print_status "Package: $DEPLOY_PACKAGE"
print_status "Git: Pushed to origin/main"
print_status "Ready for cPanel deployment!"
