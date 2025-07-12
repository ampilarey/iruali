#!/bin/bash

# Live Deployment Script for iruali
echo "🚀 Starting live deployment for iruali..."

# Step 1: Build production assets
echo "📦 Building production assets..."
npm run build

if [ $? -ne 0 ]; then
    echo "❌ Build failed! Please check for errors."
    exit 1
fi

echo "✅ Assets built successfully!"

# Step 2: Check if build files exist
if [ ! -d "public/build" ]; then
    echo "❌ Build directory not found!"
    exit 1
fi

# Step 3: Show build information
echo "📋 Build Information:"
echo "Build directory: $(ls -la public/build/)"
echo "CSS files: $(ls -la public/build/assets/*.css 2>/dev/null || echo 'No CSS files found')"
echo "JS files: $(ls -la public/build/assets/*.js 2>/dev/null || echo 'No JS files found')"

# Step 4: Commit changes
echo "💾 Committing changes..."
git add .
git commit -m "Deploy latest assets with CSS fixes - $(date)"

# Step 5: Push to remote
echo "📤 Pushing to remote repository..."
git push origin main

if [ $? -eq 0 ]; then
    echo "✅ Successfully pushed to remote!"
    echo ""
    echo "🔧 Next steps on your live server:"
    echo "1. Pull the latest changes: git pull origin main"
    echo "2. Clear Laravel caches:"
    echo "   php artisan cache:clear"
    echo "   php artisan config:clear"
    echo "   php artisan view:clear"
    echo "   php artisan route:clear"
    echo "3. Update public_html with new build files"
    echo "4. Visit your site and hard refresh (Ctrl+F5)"
    echo ""
    echo "🔍 If issues persist, visit: yourdomain.com/debug-css.php"
else
    echo "❌ Failed to push to remote!"
    exit 1
fi

echo "🎉 Deployment script completed!" 