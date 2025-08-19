#!/bin/bash

# Fix Vite manifest location for Laravel compatibility
# This script copies the manifest.json from .vite subfolder to build folder

echo "🔧 Fixing Vite manifest location..."

if [ -f "public/build/.vite/manifest.json" ]; then
    cp public/build/.vite/manifest.json public/build/manifest.json
    echo "✅ Manifest copied successfully"
else
    echo "❌ Manifest not found in .vite subfolder"
fi

echo "🎯 Ready to serve!"
