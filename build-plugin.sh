#!/bin/bash

# Local WordPress Plugin Build Script
# This script mimics the GitHub Actions workflow for local testing

set -e

PLUGIN_NAME="pc-volontari-abruzzo"
BUILD_DIR="build"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
VERSION="local-$TIMESTAMP"

echo "🔨 Building WordPress Plugin: $PLUGIN_NAME"
echo "📦 Version: $VERSION"
echo ""

# Clean and create build directory
if [ -d "$BUILD_DIR" ]; then
    echo "🧹 Cleaning existing build directory..."
    rm -rf "$BUILD_DIR"
fi

mkdir -p "$BUILD_DIR"
PLUGIN_DIR="$BUILD_DIR/$PLUGIN_NAME"

echo "📁 Creating plugin directory structure..."
mkdir -p "$PLUGIN_DIR"

# Copy plugin files
echo "📋 Copying plugin files..."

# Required files
for file in "pc-volontari-abruzzo.php" "README.md"; do
    if [ -f "$file" ]; then
        cp "$file" "$PLUGIN_DIR/"
        echo "  ✅ $file"
    else
        echo "  ❌ Missing: $file"
        exit 1
    fi
done

# Required directories
for dir in "assets" "data"; do
    if [ -d "$dir" ]; then
        cp -r "$dir" "$PLUGIN_DIR/"
        echo "  ✅ $dir/"
    else
        echo "  ❌ Missing: $dir/"
        exit 1
    fi
done

# Create build info file
echo "📝 Creating build info..."
cat > "$PLUGIN_DIR/plugin-info.txt" << EOF
Plugin: PC Volontari Abruzzo
Version: $VERSION
Build Date: $(date)
Build Type: Local Development
Git Commit: $(git rev-parse HEAD 2>/dev/null || echo "N/A")
Git Branch: $(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "N/A")
EOF

# Validate structure
echo ""
echo "🔍 Validating plugin structure..."
REQUIRED_FILES=(
    "$PLUGIN_DIR/pc-volontari-abruzzo.php"
    "$PLUGIN_DIR/assets/css/frontend.css"
    "$PLUGIN_DIR/assets/js/frontend.js"
    "$PLUGIN_DIR/data/comuni_abruzzo.json"
    "$PLUGIN_DIR/README.md"
)

ALL_PRESENT=true
for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✅ $(basename "$file")"
    else
        echo "  ❌ Missing: $(basename "$file")"
        ALL_PRESENT=false
    fi
done

if [ "$ALL_PRESENT" = false ]; then
    echo ""
    echo "❌ Build failed: Required files are missing"
    exit 1
fi

# Create ZIP
echo ""
echo "📦 Creating ZIP archive..."
cd "$BUILD_DIR"
ZIP_NAME="${PLUGIN_NAME}-${VERSION}.zip"
zip -r "$ZIP_NAME" "$PLUGIN_NAME" > /dev/null

echo "  ✅ Created: $ZIP_NAME"

# Show ZIP contents and size
echo ""
echo "📋 ZIP Contents:"
unzip -l "$ZIP_NAME" | grep -E "^\s*[0-9]" | head -20

if [ $(unzip -l "$ZIP_NAME" | grep -c "^-") -gt 20 ]; then
    echo "  ... and more files"
fi

echo ""
echo "📊 ZIP Information:"
echo "  Size: $(ls -lh "$ZIP_NAME" | awk '{print $5}')"
echo "  Files: $(unzip -l "$ZIP_NAME" | grep -c "^\s*[0-9]")"
echo "  Path: $(pwd)/$ZIP_NAME"

cd ..

echo ""
echo "🎉 Build completed successfully!"
echo ""
echo "To install in WordPress:"
echo "1. Go to WordPress Admin → Plugins → Add New → Upload Plugin"
echo "2. Upload: $BUILD_DIR/$ZIP_NAME"
echo "3. Activate the plugin"
echo ""
echo "To clean up: rm -rf $BUILD_DIR"