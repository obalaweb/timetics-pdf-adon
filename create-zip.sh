#!/bin/bash

# Script to create clean plugin zip file
# Usage: ./create-zip.sh [version]

VERSION=${1:-$(grep "Version:" timetics-pdf-addon.php | sed 's/.*Version: //')}
ZIP_NAME="timetics-pdf-addon-v${VERSION}.zip"

echo "Creating zip file: $ZIP_NAME"

# Remove old zip if exists
rm -f "../$ZIP_NAME"

# Create zip excluding unwanted files
cd ..
zip -r "$ZIP_NAME" timetics-pdf-addon \
    -x "**/*.zip" \
    -x "**/.git/**" \
    -x "**/vendor/**" \
    -x "**/node_modules/**" \
    -x "**/*.sql" \
    -x "**/test-*.php" \
    -x "**/debug-*.php" \
    -x "**/temp-*" \
    -x "**/*.log" \
    -x "**/.DS_Store" \
    -x "**/Thumbs.db"

echo "✅ Created: $ZIP_NAME"
echo "📁 Location: $(pwd)/$ZIP_NAME"
echo "📦 Size: $(du -h "$ZIP_NAME" | cut -f1)"
