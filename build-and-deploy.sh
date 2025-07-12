#!/bin/bash

# iruali Local Build and Deploy Script
# This script builds assets locally and prepares for deployment

echo "🔨 Building iruali assets locally..."

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script in your Laravel project root."
    exit 1
fi

# Install/update Node.js dependencies
echo "📦 Installing Node.js dependencies..."
npm install

if [ $? -ne 0 ]; then
    echo "❌ npm install failed"
    exit 1
fi

echo "✅ Node.js dependencies installed"

# Build assets for production
echo "🔨 Building assets for production..."
npm run build

if [ $? -ne 0 ]; then
    echo "❌ Asset build failed"
    exit 1
fi

echo "✅ Assets built successfully"

# Check if build folder exists
if [ -d "public/build" ]; then
    echo "📁 Build folder created at: public/build/"
    echo "📊 Build folder size: $(du -sh public/build | cut -f1)"
    echo ""
    echo "🚀 Ready for deployment!"
    echo ""
    echo "📋 Next steps:"
    echo "1. Commit and push to Git:"
    echo "   git add . && git commit -m 'Build assets for deployment' && git push origin main"
    echo ""
    echo "2. On cPanel server, run:"
    echo "   cd /home/yourcpaneluser/iruali"
    echo "   ./deploy-cpanel.sh"
    echo ""
    echo "3. Or manually upload public/build/ to public_html/build/"
else
    echo "❌ Build folder not found. Build may have failed."
    exit 1
fi 