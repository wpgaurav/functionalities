#!/bin/bash
#
# Build script for Functionalities plugin
# Creates a clean ZIP file with the plugin in a 'functionalities' folder
#
# Usage: ./build.sh
#
# The output ZIP will be created in the current directory.
# The ZIP contains: functionalities/ (plugin folder)
#

set -e

# Get plugin version from main file
VERSION=$(grep -m1 "Version:" functionalities.php | awk '{print $NF}')
PLUGIN_SLUG="functionalities"
BUILD_DIR="build"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "=========================================="
echo "  Functionalities Build Script"
echo "=========================================="
echo ""
echo "Building ${PLUGIN_SLUG} v${VERSION}..."
echo ""

# Clean previous build and old ZIP
rm -rf "${BUILD_DIR}"
rm -f "${ZIP_NAME}"
mkdir -p "${BUILD_DIR}/${PLUGIN_SLUG}"

# Assemble with the same exclude list the release workflow uses, so a local
# build and a tagged release cannot drift apart.
echo "Copying files (excluding .distignore entries)..."
rsync -a \
    --exclude-from=".distignore" \
    --exclude="${BUILD_DIR}" \
    . "${BUILD_DIR}/${PLUGIN_SLUG}/"

# Fail loudly if something the plugin needs at runtime did not make it in.
REQUIRED=(
    "functionalities.php"
    "index.php"
    "readme.txt"
    "uninstall.php"
    "includes/core/class-module-registry.php"
    "includes/core/class-wordpress-7-integration.php"
    "includes/storage/class-data-directory.php"
    "assets/js/wp7-admin.js"
    "assets/vendor/prism/prism.min.js"
    "assets/blocks/svg-icon/block.json"
)
for required in "${REQUIRED[@]}"; do
    if [ ! -f "${BUILD_DIR}/${PLUGIN_SLUG}/${required}" ]; then
        echo "  ERROR: missing ${required}" >&2
        exit 1
    fi
done

# Remove any .DS_Store files
find "${BUILD_DIR}" -name ".DS_Store" -type f -delete 2>/dev/null || true

# Remove any development/hidden files that might have been copied
find "${BUILD_DIR}" -name "*.map" -type f -delete 2>/dev/null || true
find "${BUILD_DIR}" -name ".gitkeep" -type f -delete 2>/dev/null || true
find "${BUILD_DIR}" -name "*.md" -type f -delete 2>/dev/null || true

# Remove CLAUDE.md files from includes
find "${BUILD_DIR}" -name "CLAUDE.md" -type f -delete 2>/dev/null || true

# Create ZIP file in current directory
echo ""
echo "Creating ZIP archive..."
cd "${BUILD_DIR}"
zip -rq "../${ZIP_NAME}" "${PLUGIN_SLUG}"
cd ..

# Clean up build directory
rm -rf "${BUILD_DIR}"

# Get file size
SIZE=$(ls -lh "${ZIP_NAME}" | awk '{print $5}')

# Count files in zip
FILE_COUNT=$(unzip -l "${ZIP_NAME}" | tail -1 | awk '{print $2}')

echo ""
echo "=========================================="
echo "  Build Complete!"
echo "=========================================="
echo ""
echo "  Output:  ./${ZIP_NAME}"
echo "  Size:    ${SIZE}"
echo "  Files:   ${FILE_COUNT}"
echo ""
echo "The ZIP contains the plugin in a '${PLUGIN_SLUG}/' folder,"
echo "ready for distribution or manual installation."
echo ""

# Show first few entries of the ZIP
echo "ZIP contents preview:"
unzip -l "${ZIP_NAME}" | head -20
echo "  ..."
echo ""
