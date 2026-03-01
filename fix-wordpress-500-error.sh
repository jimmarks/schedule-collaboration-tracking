#!/bin/bash
###############################################################################
# Fix WordPress 500 error by restoring siteurl/home
###############################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${RED}Fixing WordPress 500 error...${NC}"
echo ""

WP_PATH="/opt/bitnami/wordpress"
cd "$WP_PATH"

# Check error log
echo "Recent Apache errors:"
tail -20 /opt/bitnami/apache2/logs/error_log | grep -i "php\|fatal\|error" || echo "(none)"
echo ""

# Restore database options with proper values
echo "Restoring siteurl and home to database..."
/opt/bitnami/wp-cli/bin/wp option update siteurl "https://www.familytraveltracker.app" --allow-root
/opt/bitnami/wp-cli/bin/wp option update home "https://www.familytraveltracker.app" --allow-root

echo -e "${GREEN}✓ Database options restored${NC}"
echo ""

# The issue is that constants AND missing DB values don't work
# We need to COMMENT OUT the constants in wp-config.php and use ONLY database

echo "Commenting out WP_HOME and WP_SITEURL constants in wp-config.php..."
sed -i "s/^define('WP_HOME'/\/\/ define('WP_HOME'/" "$WP_PATH/wp-config.php"
sed -i "s/^define('WP_SITEURL'/\/\/ define('WP_SITEURL'/" "$WP_PATH/wp-config.php"

echo -e "${GREEN}✓ Constants disabled${NC}"
echo ""

echo "Testing site:"
curl -sI https://www.familytraveltracker.app | head -3
echo ""

echo -e "${YELLOW}WordPress will now use database values.${NC}"
echo -e "${YELLOW}For multi-domain, the PLUGIN must handle redirects, not wp-config.${NC}"
