#!/bin/bash
###############################################################################
# Clear WordPress siteurl and home options from database
###############################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${YELLOW}Fixing WordPress siteurl/home database options${NC}"
echo "═══════════════════════════════════════════"
echo ""

WP_PATH="/opt/bitnami/wordpress"
cd "$WP_PATH"

# Check current values
echo -e "${BLUE}Current WordPress URL options in database:${NC}"
/opt/bitnami/wp-cli/bin/wp option get siteurl --allow-root
/opt/bitnami/wp-cli/bin/wp option get home --allow-root
echo ""

echo -e "${YELLOW}These should be deleted so wp-config.php dynamic values are used instead.${NC}"
echo ""

read -p "Delete siteurl and home from database? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    echo "Deleting siteurl option..."
    /opt/bitnami/wp-cli/bin/wp option delete siteurl --allow-root || echo "  (already deleted or doesn't exist)"
    
    echo "Deleting home option..."
    /opt/bitnami/wp-cli/bin/wp option delete home --allow-root || echo "  (already deleted or doesn't exist)"
    
    echo ""
    echo -e "${GREEN}✓ Database options cleared${NC}"
    echo ""
    
    echo "Now WordPress will use the wp-config.php dynamic definitions:"
    echo "  WP_HOME = 'https://' . \$_SERVER['HTTP_HOST']"
    echo "  WP_SITEURL = 'https://' . \$_SERVER['HTTP_HOST']"
    echo ""
    
    echo "Flushing WordPress cache..."
    /opt/bitnami/wp-cli/bin/wp cache flush --allow-root 2>/dev/null || echo "  (no object cache configured)"
    
    echo ""
    echo -e "${BLUE}Testing redirects:${NC}"
    echo "  my.familytraveltracker.app:"
    curl -sI https://my.familytraveltracker.app 2>&1 | grep -E "(HTTP|Location|X-Redirect)" | head -5
    echo ""
    
    echo "  www.familytraveltracker.app:"
    curl -sIk https://www.familytraveltracker.app 2>&1 | grep -E "(HTTP|Location|X-Redirect)" | head -3
    echo ""
    
    echo -e "${GREEN}✓ Fix complete!${NC}"
    echo ""
    echo "Test in browser (incognito window):"
    echo "  https://my.familytraveltracker.app"
    echo "  https://www.familytraveltracker.app"
else
    echo "Skipped."
fi
