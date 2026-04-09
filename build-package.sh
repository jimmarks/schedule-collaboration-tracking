#!/bin/bash

###############################################################################
# Schedule Collaboration Tracking - WordPress Plugin Packaging Script
# 
# This script packages the plugin for WordPress distribution
# - Validates PHP syntax
# - Updates version numbers
# - Creates clean package directory
# - Generates versioned zip file
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Schedule Collaboration Tracking - WordPress Plugin Packager${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

###############################################################################
# Step 1: Extract current version
###############################################################################

echo -e "${YELLOW}Step 1: Checking current version...${NC}"

# Extract version, strip any 'v' prefix if present
CURRENT_VERSION=$(grep -oP "Version: \Kv?[0-9]+\.[0-9]+\.[0-9]+" schedule-collaboration-tracking.php | sed 's/^v//')

if [ -z "$CURRENT_VERSION" ]; then
    echo -e "${RED}✗ Failed to extract current version from plugin file${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Current version: ${CURRENT_VERSION}${NC}"
echo ""

###############################################################################
# Step 2: Determine new version (applied AFTER lint passes)
###############################################################################

echo -e "${YELLOW}Step 2: Determining new version...${NC}"

if [ "$#" -eq 1 ]; then
    # Strip 'v' prefix if user provided it
    NEW_VERSION=$(echo "$1" | sed 's/^v//')
    echo -e "${GREEN}✓ Using specified version: ${NEW_VERSION}${NC}"
else
    # Auto-increment patch version
    IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"
    PATCH=$((PATCH + 1))
    NEW_VERSION="${MAJOR}.${MINOR}.${PATCH}"
    echo -e "${GREEN}✓ Will auto-increment version: ${CURRENT_VERSION} → ${NEW_VERSION}${NC}"
fi

# Validate version format
if ! [[ "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo -e "${RED}✗ Invalid version format: ${NEW_VERSION}${NC}"
    echo -e "${RED}  Version must be in format: X.Y.Z (e.g., 2.1.0)${NC}"
    exit 1
fi

VERSION="$NEW_VERSION"
echo ""

###############################################################################
# Step 3: Lint check all PHP files
###############################################################################

echo -e "${YELLOW}Step 3: Running PHP lint checks...${NC}"

LINT_FAILED=0

# Check main plugin file
if php -l schedule-collaboration-tracking.php > /dev/null 2>&1; then
    echo -e "${GREEN}✓ schedule-collaboration-tracking.php${NC}"
else
    echo -e "${RED}✗ schedule-collaboration-tracking.php - SYNTAX ERROR${NC}"
    LINT_FAILED=1
fi

# Check includes (top-level + subdirectories in one pass, avoiding subshell)
while IFS= read -r file; do
    if php -l "$file" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ $file${NC}"
    else
        echo -e "${RED}✗ $file - SYNTAX ERROR${NC}"
        LINT_FAILED=1
    fi
done < <(find includes templates -type f -name "*.php")

if [ $LINT_FAILED -eq 1 ]; then
    echo -e "${RED}✗ Lint check FAILED. Version has NOT been changed. Fix syntax errors and re-run.${NC}"
    exit 1
fi

echo -e "${GREEN}✓ All PHP files passed syntax check${NC}"
echo ""

###############################################################################
# Step 3b: Apply version bump (lint passed — safe to write)
###############################################################################

echo -e "${YELLOW}Step 3b: Applying version bump...${NC}"

# Update version in plugin file (handle both with and without 'v' prefix)
sed -i "s/Version: v\?${CURRENT_VERSION}/Version: ${NEW_VERSION}/" schedule-collaboration-tracking.php
sed -i "s/define('FTT_VERSION', '${CURRENT_VERSION}');/define('FTT_VERSION', '${NEW_VERSION}');/" schedule-collaboration-tracking.php

# Verify the update worked
NEW_FILE_VERSION=$(grep -oP "Version: \Kv?[0-9]+\.[0-9]+\.[0-9]+" schedule-collaboration-tracking.php | sed 's/^v//')
if [ "$NEW_FILE_VERSION" != "$NEW_VERSION" ]; then
    echo -e "${RED}✗ Failed to update version in plugin file${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Version updated: ${CURRENT_VERSION} → ${NEW_VERSION}${NC}"
echo ""

###############################################################################
# Step 4: Create package directory
###############################################################################

echo -e "${YELLOW}Step 4: Creating package directory...${NC}"

PACKAGE_DIR="package"
PLUGIN_DIR="$PACKAGE_DIR/schedule-collaboration-tracking"

# Remove old package if exists
if [ -d "$PACKAGE_DIR" ]; then
    rm -rf "$PACKAGE_DIR"
fi

# Create fresh package directory
mkdir -p "$PLUGIN_DIR"

echo -e "${GREEN}✓ Package directory created${NC}"
echo ""

###############################################################################
# Step 5: Copy plugin files
###############################################################################

echo -e "${YELLOW}Step 5: Copying plugin files...${NC}"

# Copy main plugin file
cp schedule-collaboration-tracking.php "$PLUGIN_DIR/"
echo -e "${GREEN}✓ Copied main plugin file${NC}"

# Copy test runner if present (temporary — delete from server after testing)
if [ -f "ftt-test-runner.php" ]; then
    cp ftt-test-runner.php "$PLUGIN_DIR/"
    echo -e "${YELLOW}✓ Copied ftt-test-runner.php (TEMPORARY — delete after testing)${NC}"
fi

# Copy directories
cp -r includes "$PLUGIN_DIR/"
echo -e "${GREEN}✓ Copied includes/${NC}"

cp -r lib "$PLUGIN_DIR/"
echo -e "${GREEN}✓ Copied lib/${NC}"

# Strip production-irrelevant files from vendored libraries.
# We exclude by CATEGORY (not by filename) so future Stripe API additions
# are automatically included — only things that are structurally useless in
# a production WordPress plugin are removed here.

# stripe-php: remove test helpers (never run in production), the bundled CA
# cert bundle (WordPress/PHP uses its own), and markdown docs/metadata files.
rm -rf  "$PLUGIN_DIR/lib/stripe-php/lib/TestHelpers"
rm -rf  "$PLUGIN_DIR/lib/stripe-php/lib/Service/TestHelpers"
# Patch init.php: remove require lines for both TestHelpers directories so PHP
# doesn't fatal when the files are missing at runtime.
sed -i '/TestHelpers/d' "$PLUGIN_DIR/lib/stripe-php/init.php"
rm -f   "$PLUGIN_DIR/lib/stripe-php/data/ca-certificates.crt"
rm -f   "$PLUGIN_DIR/lib/stripe-php/CHANGELOG.md"
rm -f   "$PLUGIN_DIR/lib/stripe-php/README.md"
rm -f   "$PLUGIN_DIR/lib/stripe-php/OPENAPI_VERSION"
rm -f   "$PLUGIN_DIR/lib/stripe-php/.gitignore"

# plugin-update-checker: remove .po source translation files (the compiled
# .mo files are what PHP actually loads, and even those are unused since this
# plugin ships in English only).
find "$PLUGIN_DIR/lib/plugin-update-checker/languages" -name "*.po" -delete
rm -f "$PLUGIN_DIR/lib/plugin-update-checker/README.md"

echo -e "${GREEN}✓ Stripped vendor docs/test files${NC}"

cp -r assets "$PLUGIN_DIR/"
echo -e "${GREEN}✓ Copied assets/${NC}"

cp -r templates "$PLUGIN_DIR/"
echo -e "${GREEN}✓ Copied templates/${NC}"

# Copy documentation
cp LICENSE "$PLUGIN_DIR/"
echo -e "${GREEN}✓ Copied LICENSE${NC}"

cp PLUGIN_README.md "$PLUGIN_DIR/README.md"
echo -e "${GREEN}✓ Copied README (from PLUGIN_README.md)${NC}"

cp setup-cron.sh "$PLUGIN_DIR/"
chmod +x "$PLUGIN_DIR/setup-cron.sh"
echo -e "${GREEN}✓ Copied setup-cron.sh${NC}"

# Copy test utility
if [ -f "test-digest.php" ]; then
    cp test-digest.php "$PLUGIN_DIR/"
    echo -e "${GREEN}✓ Copied test-digest.php${NC}"
fi

if [ -f "test-invite-validation.php" ]; then
    cp test-invite-validation.php "$PLUGIN_DIR/"
    echo -e "${GREEN}✓ Copied test-invite-validation.php${NC}"
fi

echo ""

###############################################################################
# Step 6: Create zip file
###############################################################################

echo -e "${YELLOW}Step 6: Creating zip file...${NC}"

# Create download directory if it doesn't exist
DOWNLOAD_DIR="download"
mkdir -p "$DOWNLOAD_DIR"

ZIP_NAME="schedule-collaboration-tracking-v${VERSION}.zip"
ZIP_PATH="$DOWNLOAD_DIR/$ZIP_NAME"

# Remove old zip if exists
if [ -f "$ZIP_PATH" ]; then
    rm "$ZIP_PATH"
fi

# Create zip from package directory
cd "$PACKAGE_DIR"
zip -r "../$ZIP_PATH" schedule-collaboration-tracking/ > /dev/null 2>&1
cd ..

echo -e "${GREEN}✓ Created: ${ZIP_PATH}${NC}"
echo ""

###############################################################################
# Step 7: Package summary
###############################################################################

echo -e "${YELLOW}Step 7: Package summary...${NC}"

ZIP_SIZE=$(du -h "$ZIP_PATH" | cut -f1)
FILE_COUNT=$(unzip -l "$ZIP_PATH" | tail -1 | awk '{print $2}')

echo -e "${GREEN}✓ Package size: ${ZIP_SIZE}${NC}"
echo -e "${GREEN}✓ Total files: ${FILE_COUNT}${NC}"
echo ""

###############################################################################
# Summary
###############################################################################

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ PACKAGE CREATED SUCCESSFULLY${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "  Plugin Version: ${YELLOW}${VERSION}${NC}"
echo -e "  Package File:   ${YELLOW}${ZIP_PATH}${NC}"
echo -e "  Package Size:   ${YELLOW}${ZIP_SIZE}${NC}"
echo -e "  Files:          ${YELLOW}${FILE_COUNT}${NC}"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo -e "  1. Test installation: Upload ${ZIP_PATH} to WordPress"
echo -e "  2. Verify functionality in test environment"
echo -e "  3. Deploy to production when ready"
echo ""
echo -e "${BLUE}Package contents:${NC}"
echo -e "  - ${ZIP_PATH}        (WordPress plugin zip)"
echo -e "  - package/           (Build directory)"
echo -e "  - download/          (Release files)"
echo ""

###############################################################################
# Optional: List package contents
###############################################################################

if [ "$2" == "--list" ]; then
    echo -e "${BLUE}Package file listing:${NC}"
    unzip -l "$ZIP_PATH"
    echo ""
fi

echo -e "${GREEN}Done! 🎺${NC}"
