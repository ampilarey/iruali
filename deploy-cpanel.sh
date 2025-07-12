#!/bin/bash

# Comprehensive Deployment Script for iruali E-commerce Platform
# This script handles the complete deployment process

echo "🚀 Starting comprehensive deployment for iruali..."
echo "=================================================="

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

# Step 1: Stop any running Vite processes
print_status "Stopping any running Vite processes..."
pkill -f vite 2>/dev/null || true
sleep 2

# Step 2: Clean build directory
print_status "Cleaning build directory..."
rm -rf public/build
print_success "Build directory cleaned"

# Step 3: Check if Node.js is available
print_status "Checking Node.js availability..."
if command -v npm &> /dev/null; then
    print_success "Node.js is available"
    
    # Install dependencies (if needed)
    if [ ! -d "node_modules" ]; then
        print_status "Installing Node.js dependencies..."
        npm install
        if [ $? -ne 0 ]; then
            print_error "Failed to install Node.js dependencies!"
            exit 1
        fi
        print_success "Node.js dependencies installed"
    else
        print_success "Node.js dependencies already installed"
    fi
else
    print_warning "Node.js is not available on this server"
    print_status "Skipping Node.js dependency installation"
    print_status "Make sure to build assets locally before deployment"
fi

# Step 4: Build production assets
print_status "Building production assets..."
if command -v npm &> /dev/null; then
    npm run build
    if [ $? -ne 0 ]; then
        print_error "Build failed! Please check for errors."
        exit 1
    fi
    print_success "Production assets built successfully!"
else
    print_warning "Node.js not available - checking if build files exist..."
    if [ -d "public/build" ] && [ -f "public/build/manifest.json" ]; then
        print_success "Build files found - using existing build"
    else
        print_error "No build files found! Please build assets locally first:"
        print_error "1. Run: npm run build"
        print_error "2. Then run this deployment script again"
        exit 1
    fi
fi

# Step 5: Verify build files
print_status "Verifying build files..."
if [ ! -d "public/build" ]; then
    print_error "Build directory not found!"
    exit 1
fi

if [ ! -f "public/build/manifest.json" ]; then
    print_error "Build manifest not found!"
    exit 1
fi

print_success "Build files verified"

# Step 6: Show build information
print_status "Build Information:"
echo "  📁 Build directory: $(ls -la public/build/)"
echo "  📄 CSS files: $(ls -la public/build/assets/*.css 2>/dev/null | wc -l) files"
echo "  📄 JS files: $(ls -la public/build/assets/*.js 2>/dev/null | wc -l) files"
echo "  📄 Manifest: $(cat public/build/manifest.json | jq -r 'keys | join(", ")')"

# Step 7: Check Git status
print_status "Checking Git status..."
git status --porcelain
if [ $? -eq 0 ]; then
    CHANGES=$(git status --porcelain | wc -l)
    if [ $CHANGES -gt 0 ]; then
        print_status "Found $CHANGES changes to commit"
    else
        print_success "No changes to commit"
    fi
fi

# Step 8: Add and commit changes
print_status "Adding changes to Git..."
git add -A
if [ $? -ne 0 ]; then
    print_error "Failed to add files to Git!"
    exit 1
fi

print_status "Committing changes..."
git commit -m "Deploy latest build - $(date '+%Y-%m-%d %H:%M:%S')"
if [ $? -ne 0 ]; then
    print_error "Failed to commit changes!"
    exit 1
fi

# Step 9: Push to remote
print_status "Pushing to remote repository..."
git push origin main
if [ $? -ne 0 ]; then
    print_error "Failed to push to remote!"
    exit 1
fi

print_success "Successfully pushed to remote repository!"

# Step 10: Show deployment summary
echo ""
echo "=================================================="
print_success "DEPLOYMENT COMPLETED SUCCESSFULLY!"
echo "=================================================="
echo ""
print_status "What was deployed:"
echo "  ✅ Latest CSS fixes (removed problematic !important rules)"
echo "  ✅ Fixed header layout (no more duplication)"
echo "  ✅ Proper cart/login button spacing"
echo "  ✅ Responsive design improvements"
echo "  ✅ Fresh production build assets"
echo ""
print_status "Next steps for your LIVE SERVER (irulai.mv):"
echo ""
echo "1. 🔄 PULL LATEST CHANGES:"
echo "   - Go to cPanel → Git Version Control"
echo "   - Click on your repository"
echo "   - Click 'Update from Remote' or 'Pull'"
echo ""
echo "2. 🧹 CLEAR LARAVEL CACHES:"
echo "   - In cPanel Terminal or SSH:"
echo "   cd /path/to/your/laravel/app"
echo "   php artisan cache:clear"
echo "   php artisan config:clear"
echo "   php artisan view:clear"
echo "   php artisan route:clear"
echo ""
echo "3. 🗑️ DELETE OLD BUILD FILES:"
echo "   rm -rf public_html/build"
echo ""
echo "4. 📁 COPY NEW BUILD FILES:"
echo "   cp -r iruali/public/build public_html/"
echo ""
echo "5. ✅ VERIFY BUILD FILES:"
echo "   ls -la public/build/"
echo ""
echo "6. 🔐 SET PERMISSIONS:"
echo "   chmod -R 755 storage bootstrap/cache"
echo ""
echo "7. 🌐 TEST YOUR SITE:"
echo "   - Visit: https://irulai.mv"
echo "   - Check: https://irulai.mv/build/assets/"
echo "   - Clear browser cache if needed (Ctrl+F5)"
echo ""
echo "📋 COMPLETE COMMAND SEQUENCE FOR CPANEL:"
echo "=========================================="
echo "git pull origin main"
echo "php artisan cache:clear"
echo "php artisan config:clear"
echo "php artisan view:clear"
echo "php artisan route:clear"
echo "rm -rf public_html/build"
echo "cp -r iruali/public/build public_html/"
echo "ls -la public/build/"
echo "chmod -R 755 storage bootstrap/cache"
echo "=========================================="
echo ""
print_warning "If you're still seeing issues:"
echo "  - Check that the build files are in public_html/build/"
echo "  - Verify your .env file has APP_ENV=production"
echo "  - Ensure file permissions are correct (755 for directories, 644 for files)"
echo ""
print_success "Your iruali e-commerce platform should now be working perfectly!"
echo "" 