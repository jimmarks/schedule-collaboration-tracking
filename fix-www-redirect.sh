#!/bin/bash
###############################################################################
# Fix www redirect issue in Apache SSL config
###############################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

SSL_CONF="/opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf"
BACKUP_FILE="/home/bitnami/bitnami-ssl.conf.pre-www-fix-$(date +%Y%m%d_%H%M%S)"

echo -e "${YELLOW}Fixing www redirect issue...${NC}"
echo ""

# Backup
cp "$SSL_CONF" "$BACKUP_FILE"
echo -e "${GREEN}✓ Backed up to: $BACKUP_FILE${NC}"

# Check for problematic redirect rules
echo ""
echo "Checking for www redirect rules in SSL config:"
grep -n "www\." "$SSL_CONF" || echo "  No www redirect rules found"
echo ""

# Remove any RewriteCond/RewriteRule that adds www to all domains
# Keep only specific redirect for bare domain
sed -i '/RewriteCond.*HTTP_HOST.*!^www\./d' "$SSL_CONF"
sed -i '/RewriteRule.*www\.%{HTTP_HOST}/d' "$SSL_CONF"
sed -i '/RewriteRule.*https:\/\/www\./d' "$SSL_CONF"

echo -e "${GREEN}✓ Removed generic www redirect rules${NC}"
echo ""

# Ensure we have the correct redirect only for bare domain
# This should be in .htaccess, not Apache config
echo "Verifying Apache config..."
/opt/bitnami/apache2/bin/apachectl configtest

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Apache configuration is valid${NC}"
    echo ""
    echo "Restarting Apache..."
    /opt/bitnami/ctlscript.sh restart apache
    echo -e "${GREEN}✓ Apache restarted${NC}"
    echo ""
    
    echo "Testing redirects..."
    echo "  my.familytraveltracker.app → $(curl -sIk https://my.familytraveltracker.app 2>&1 | grep -i location | head -1)"
    echo ""
    
    echo -e "${GREEN}✓ Fix complete!${NC}"
    echo ""
    echo "Test in browser: https://my.familytraveltracker.app"
else
    echo -e "${RED}✗ Apache config error, restoring backup...${NC}"
    cp "$BACKUP_FILE" "$SSL_CONF"
    exit 1
fi
