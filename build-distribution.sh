#!/bin/bash

# WCEventsFP - Build Distribution ZIP
# 
# This script creates a distribution-ready ZIP file that can be uploaded
# directly to WordPress without needing Composer or any build tools.
# 
# Usage: ./build-distribution.sh [version]

set -e

# Configuration
PLUGIN_SLUG="wceventsfp"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Get version from plugin file or parameter
if [ -n "$1" ]; then
    VERSION="$1"
else
    VERSION=$(grep "^ \* Version:" wceventsfp.php | head -1 | awk '{print $3}' | tr -d ' ')
fi

if [ -z "$VERSION" ]; then
    echo "❌ Unable to determine plugin version"
    echo "Usage: $0 [version]"
    exit 1
fi

DIST_DIR="dist"
PLUGIN_DIR="$DIST_DIR/$PLUGIN_SLUG"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "🚀 Building WCEventsFP Distribution v${VERSION}"
echo "=================================="

# Clean and create distribution directory
echo "📁 Setting up distribution directory..."
rm -rf "$DIST_DIR"
mkdir -p "$PLUGIN_DIR"

# Copy all necessary files
echo "📋 Copying plugin files..."
rsync -av \
    --exclude='.git*' \
    --exclude='.github/' \
    --exclude='dist/' \
    --exclude='tests/' \
    --exclude='node_modules/' \
    --exclude='.idea/' \
    --exclude='.vscode/' \
    --exclude='*.log' \
    --exclude='phpunit.xml' \
    --exclude='phpcs.xml' \
    --exclude='phpstan.neon' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='webpack.config.js' \
    --exclude='DEVELOPMENT.md' \
    --exclude='CONTRIBUTING.md' \
    --exclude='tools/backups/' \
    --exclude='build-distribution.sh' \
    . "$PLUGIN_DIR/"

# Ensure vendor dependencies are present
echo "📦 Ensuring dependencies are included..."
mkdir -p "$PLUGIN_DIR/vendor"
if [ -f "vendor/wcefp-fpdf.php" ]; then
    cp "vendor/wcefp-fpdf.php" "$PLUGIN_DIR/vendor/"
    echo "   ✅ PDF library included"
else
    echo "   ⚠️  PDF library not found - plugin will still work but without PDF features"
fi

# Verify critical files
echo "🔍 Verifying plugin structure..."
CRITICAL_FILES=(
    "$PLUGIN_DIR/wceventsfp.php"
    "$PLUGIN_DIR/includes/autoloader.php"
)

for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✅ $(basename "$file")"
    else
        echo "   ❌ Missing: $(basename "$file")"
        exit 1
    fi
done

# Create ZIP
echo "📦 Creating distribution ZIP..."
cd "$DIST_DIR"
zip -q -r "../$ZIP_NAME" "$PLUGIN_SLUG"
cd ..

# Final verification
if [ -f "$ZIP_NAME" ]; then
    FILE_SIZE=$(du -h "$ZIP_NAME" | cut -f1)
    FILE_COUNT=$(unzip -l "$ZIP_NAME" | tail -1 | awk '{print $2}')
    
    echo "✅ Distribution created successfully!"
    echo ""
    echo "📋 Distribution Details:"
    echo "   File: $ZIP_NAME"
    echo "   Size: $FILE_SIZE"
    echo "   Files: $FILE_COUNT"
    echo ""
    echo "🚀 Ready to upload to WordPress!"
    echo "   1. Go to WordPress Admin → Plugins → Add New"
    echo "   2. Click 'Upload Plugin'"
    echo "   3. Select: $ZIP_NAME"
    echo "   4. Install and activate"
    echo ""
    echo "⚠️  For non-programmers: Use this ZIP file instead of"
    echo "    downloading from GitHub's 'Code → Download ZIP'"
else
    echo "❌ Failed to create distribution ZIP"
    exit 1
fi

# Cleanup
rm -rf "$DIST_DIR"

echo "🎉 Build completed successfully!"