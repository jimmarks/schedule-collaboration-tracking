#!/bin/bash

###############################################################################
# Automated Release Script for Schedule Collaboration Tracking
# 
# This script automates the entire release process:
# 1. Builds the package (auto-increments version)
# 2. Commits changes
# 3. Creates git tag
# 4. Pushes to GitHub
# 5. Creates GitHub release with ZIP file
###############################################################################

set -e  # Exit on error

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Schedule Collaboration Tracking - Automated Release${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

# Check for uncommitted changes
if [[ -n $(git status -s) ]]; then
    echo -e "${YELLOW}⚠ You have uncommitted changes.${NC}"
    echo ""
    git status -s
    echo ""
    read -p "Commit these changes? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        read -p "Enter commit message: " COMMIT_MSG
        git add .
        git commit -m "$COMMIT_MSG"
        echo -e "${GREEN}✓ Changes committed${NC}"
    else
        echo -e "${RED}✗ Please commit or stash changes before releasing${NC}"
        exit 1
    fi
fi

echo ""
echo -e "${YELLOW}Step 1: Building package...${NC}"
bash build-package.sh

# Extract version from plugin file after build
VERSION=$(grep -oP "Version: \K[0-9]+\.[0-9]+\.[0-9]+" schedule-collaboration-tracking.php)
echo -e "${GREEN}✓ Built version: ${VERSION}${NC}"
echo ""

# Check if version changes were committed
if [[ -n $(git status -s schedule-collaboration-tracking.php) ]]; then
    echo -e "${YELLOW}Step 2: Committing version bump...${NC}"
    git add schedule-collaboration-tracking.php
    git commit -m "Bump version to ${VERSION}"
    echo -e "${GREEN}✓ Version committed${NC}"
else
    echo -e "${GREEN}✓ No version changes to commit${NC}"
fi
echo ""

echo -e "${YELLOW}Step 3: Creating git tag...${NC}"
TAG="v${VERSION}"

# Check if tag already exists
if git rev-parse "$TAG" >/dev/null 2>&1; then
    echo -e "${RED}✗ Tag $TAG already exists${NC}"
    read -p "Delete existing tag and continue? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git tag -d "$TAG"
        git push origin ":refs/tags/$TAG" 2>/dev/null || true
        echo -e "${GREEN}✓ Deleted existing tag${NC}"
    else
        echo -e "${RED}✗ Release cancelled${NC}"
        exit 1
    fi
fi

# Get release notes
echo ""
echo -e "${BLUE}Enter release notes (press Ctrl+D when done):${NC}"
RELEASE_NOTES=$(cat)

if [[ -z "$RELEASE_NOTES" ]]; then
    RELEASE_NOTES="Release ${VERSION}"
fi

git tag -a "$TAG" -m "Release ${VERSION}"
echo -e "${GREEN}✓ Created tag: ${TAG}${NC}"
echo ""

echo -e "${YELLOW}Step 4: Pushing to GitHub...${NC}"
git push origin main
git push origin "$TAG"
echo -e "${GREEN}✓ Pushed to GitHub${NC}"
echo ""

echo -e "${YELLOW}Step 5: Creating GitHub release...${NC}"
ZIP_FILE="download/schedule-collaboration-tracking-v${VERSION}.zip"

if [[ ! -f "$ZIP_FILE" ]]; then
    echo -e "${RED}✗ ZIP file not found: ${ZIP_FILE}${NC}"
    exit 1
fi

gh release create "$TAG" \
    "$ZIP_FILE" \
    --title "${TAG}" \
    --notes "$RELEASE_NOTES" \
    --repo jimmarks/schedule-collaboration-tracking

echo -e "${GREEN}✓ GitHub release created${NC}"
echo ""

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ RELEASE COMPLETE${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "  Version:        ${YELLOW}${VERSION}${NC}"
echo -e "  Tag:            ${YELLOW}${TAG}${NC}"
echo -e "  Package:        ${YELLOW}${ZIP_FILE}${NC}"
echo ""
echo -e "  Release URL:    ${BLUE}https://github.com/jimmarks/schedule-collaboration-tracking/releases/tag/${TAG}${NC}"
echo ""
echo -e "${GREEN}Users with the plugin installed will see the update in WordPress!${NC}"
echo ""
