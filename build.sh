#!/bin/bash
#
# Build script for Functionalities plugin
# Creates a clean ZIP file for WordPress.org submission
#

set -e

# Get plugin version from main file
VERSION=$(grep -m1 "Version:" functionalities.php | awk '{print $NF}')
PLUGIN_SLUG="functionalities"
BUILD_DIR="build"
OUTPUT_DIR=".."
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "Building ${PLUGIN_SLUG} v${VERSION}..."

# Clean previous build
rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"

# Files and directories to include
INCLUDE=(
    "assets"
    "includes"
    "languages"
    "functionalities.php"
    "index.php"
    "readme.txt"
    "uninstall.php"
    "exception-urls.json.sample"
)

# Copy files to build directory
for item in "${INCLUDE[@]}"; do
    if [ -e "$item" ]; then
        cp -r "$item" "${BUILD_DIR}/${PLUGIN_SLUG}/"
        echo "  Added: $item"
    fi
done

# Remove any .DS_Store files
find "${BUILD_DIR}" -name ".DS_Store" -type f -delete

# Remove any development/hidden files that might have been copied
find "${BUILD_DIR}" -name "*.map" -type f -delete
find "${BUILD_DIR}" -name ".gitkeep" -type f -delete

# Create ZIP file (output to parent of current working directory)
cd "${BUILD_DIR}"
zip -rq "../../${ZIP_NAME}" "${PLUGIN_SLUG}"
cd ..

# Clean up build directory
rm -rf "${BUILD_DIR}"

# Get file size
SIZE=$(ls -lh "${OUTPUT_DIR}/${ZIP_NAME}" | awk '{print $5}')

echo ""
echo "Build complete!"
echo "  File: ${OUTPUT_DIR}/${ZIP_NAME}"
echo "  Size: ${SIZE}"
echo ""
echo "Ready for WordPress.org submission."
