#!/bin/bash

# Configuration
PLUGIN_NAME="caddy-souin-cache-manager"
BUILD_ROOT=".build_root"
PLUGIN_DIR="$BUILD_ROOT/$PLUGIN_NAME"
ZIP_NAME="${PLUGIN_NAME}.zip"

echo "🚀 Starting production build for $PLUGIN_NAME..."

# 1. Build Frontend Assets
echo "📦 Building frontend assets..."
npm run build

if [ $? -ne 0 ]; then
    echo "❌ Frontend build failed. Aborting."
    exit 1
fi

# 2. Prepare Clean Build Directory Structure
echo "📂 Preparing clean build environment..."
rm -rf $BUILD_ROOT
mkdir -p $PLUGIN_DIR

# Copy production files into the plugin sub-folder
cp caddy-souin-cache-manager.php $PLUGIN_DIR/
cp readme.txt $PLUGIN_DIR/
cp license.txt $PLUGIN_DIR/
cp composer.json $PLUGIN_DIR/
cp composer.lock $PLUGIN_DIR/
cp -r src $PLUGIN_DIR/
cp -r dist $PLUGIN_DIR/

# 3. Install Production Dependencies
echo "🐘 Installing production PHP dependencies..."
cd $PLUGIN_DIR
composer install --no-dev --optimize-autoloader --quiet

# Remove composer files from the final package
rm composer.json composer.lock

# 4. Create ZIP Archive (wrapping the plugin folder)
echo "🤐 Creating distributable ZIP..."
cd ../..
rm -f $ZIP_NAME
cd $BUILD_ROOT
zip -r ../$ZIP_NAME $PLUGIN_NAME -x "*.git*" -x "*.DS_Store" -x "*.bak" > /dev/null
cd ..

# 5. Cleanup
echo "🧹 Cleaning up temporary files..."
rm -rf $BUILD_ROOT

echo "✅ Build Complete! Package: $ZIP_NAME"
echo "------------------------------------------------"
ls -lh $ZIP_NAME
