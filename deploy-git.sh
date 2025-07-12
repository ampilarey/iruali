#!/bin/bash

# Git Deployment Script for iruali Laravel E-commerce
# This script builds assets, commits changes, and pushes to GitHub

set -e  # Exit on any error

echo "🚀 Starting Git deployment for iruali..."

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

# Step 1: Build production assets
print_status "Building production assets..."
if command -v npm &> /dev/null; then
    npm run build
    print_success "Assets built successfully"
else
    print_warning "npm not found. Skipping asset build."
fi

# Step 2: Check Git status
print_status "Checking Git status..."
if [ ! -d ".git" ]; then
    print_error "Git repository not found. Please initialize Git first."
    exit 1
fi

# Step 3: Add all changes
print_status "Adding changes to Git..."
git add .

# Step 4: Check if there are changes to commit
if git diff --cached --quiet; then
    print_warning "No changes to commit. Everything is up to date."
else
    # Step 5: Commit changes
    print_status "Committing changes..."
    if [ -n "$1" ]; then
        commit_message="$1"
    else
        commit_message="Update: $(date '+%Y-%m-%d %H:%M:%S')"
    fi
    
    git commit -m "$commit_message"
    print_success "Changes committed: $commit_message"
fi

# Step 6: Check if remote is configured
if ! git remote get-url origin &> /dev/null; then
    print_error "No remote repository configured. Please add your GitHub repository:"
    echo "git remote add origin https://github.com/yourusername/iruali.git"
    exit 1
fi

# Step 7: Push to remote
print_status "Pushing to remote repository..."
if git push origin main; then
    print_success "Successfully pushed to GitHub!"
else
    print_error "Failed to push to GitHub. Please check your remote configuration."
    exit 1
fi

# Step 8: Display next steps
echo ""
print_success "🎉 Local deployment completed successfully!"
echo ""
print_status "Next steps for cPanel deployment:"
echo "1. Go to cPanel → Git Version Control"
echo "2. Create new repository or update existing one"
echo "3. Set repository URL to: $(git remote get-url origin)"
echo "4. Set branch to: main"
echo "5. Click 'Create' or 'Update'"
echo ""
print_status "After Git is set up in cPanel:"
echo "1. Copy public/ contents to public_html/"
echo "2. Update public_html/index.php to point to your Laravel app"
echo "3. Run: composer install --no-dev --optimize-autoloader"
echo "4. Set up .env file and run migrations"
echo "5. Set proper permissions: chmod -R 755 storage bootstrap/cache"
echo ""

print_success "Deployment script completed! 🚀" 