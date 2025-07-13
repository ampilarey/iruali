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

print_status "🔧 Updating server .env file with OneDrive configuration..."

# Check if .env file exists
if [ ! -f ".env" ]; then
    print_error ".env file not found in current directory"
    exit 1
fi

# Check if OneDrive configuration already exists
if grep -q "ONEDRIVE_CLIENT_ID" .env; then
    print_warning "OneDrive configuration already exists in .env file"
    print_status "Current OneDrive settings:"
    grep -E "^ONEDRIVE_" .env || echo "No OneDrive variables found"
    echo ""
    read -p "Do you want to update existing values? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_status "Skipping OneDrive configuration update"
        exit 0
    fi
fi

# Backup current .env file
print_status "Creating backup of current .env file..."
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)

# Add OneDrive configuration if it doesn't exist
if ! grep -q "ONEDRIVE_CLIENT_ID" .env; then
    print_status "Adding OneDrive configuration to .env file..."
    echo "" >> .env
    echo "# OneDrive Backup Configuration" >> .env
    echo "# Get these credentials from Azure Portal: https://portal.azure.com/" >> .env
    echo "ONEDRIVE_CLIENT_ID=" >> .env
    echo "ONEDRIVE_CLIENT_SECRET=" >> .env
    echo "ONEDRIVE_REFRESH_TOKEN=" >> .env
    echo "ONEDRIVE_TENANT_ID=" >> .env
    echo "ONEDRIVE_REDIRECT_URI=http://localhost:8000/auth/onedrive/callback" >> .env
    print_success "OneDrive configuration variables added to .env file"
else
    print_status "OneDrive configuration already exists, skipping addition"
fi

# Clear Laravel caches to pick up new environment variables
print_status "Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear

print_success "✅ Server .env file updated successfully!"
echo ""
print_status "Next steps:"
echo "1. Edit .env file and add your OneDrive credentials:"
echo "   - ONEDRIVE_CLIENT_ID (from Azure Portal)"
echo "   - ONEDRIVE_CLIENT_SECRET (from Azure Portal)"
echo "   - ONEDRIVE_REFRESH_TOKEN (from OAuth flow)"
echo "   - ONEDRIVE_TENANT_ID (from Azure Portal)"
echo "2. Update ONEDRIVE_REDIRECT_URI for your production domain"
echo "3. Test OneDrive connection: php artisan backup:onedrive --test"
echo ""
print_warning "⚠️  Remember to keep your credentials secure and never commit .env to git!" 