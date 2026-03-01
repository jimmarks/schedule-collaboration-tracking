#!/bin/bash
###############################################################################
# Show and Fix Apache Rewrite Rules
###############################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SSL_CONF="/opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf"
BACKUP_FILE="/home/bitnami/bitnami-ssl.conf.backup-$(date +%Y%m%d_%H%M%S)"

echo -e "${YELLOW}Analyzing Apache SSL Configuration${NC}"
echo "═══════════════════════════════════════════"
echo ""

# Show the ENTIRE SSL VirtualHost config
echo -e "${BLUE}Current SSL VirtualHost Configuration:${NC}"
echo "─────────────────────────────────────────"
cat "$SSL_CONF"
echo ""
echo "═══════════════════════════════════════════"
echo ""

# Backup
cp "$SSL_CONF" "$BACKUP_FILE"
echo -e "${GREEN}✓ Backed up to: $BACKUP_FILE${NC}"
echo ""

# Show specific rewrite rules
echo -e "${YELLOW}RewriteRule lines found:${NC}"
grep -n "RewriteRule\|RewriteCond\|RewriteEngine" "$SSL_CONF" || echo "  None found"
echo ""

read -p "Do you want to remove ALL RewriteRule/RewriteCond lines from Apache SSL config? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    echo -e "${YELLOW}Removing all rewrite directives from Apache SSL config...${NC}"
    
    # Remove all rewrite-related lines
    sed -i '/RewriteEngine/d' "$SSL_CONF"
    sed -i '/RewriteCond/d' "$SSL_CONF"
    sed -i '/RewriteRule/d' "$SSL_CONF"
    
    echo -e "${GREEN}✓ Removed all rewrite directives${NC}"
    echo ""
    
    # Test config
    echo "Testing Apache configuration..."
    if /opt/bitnami/apache2/bin/apachectl configtest 2>&1 | grep -q "Syntax OK"; then
        echo -e "${GREEN}✓ Apache configuration is valid${NC}"
        echo ""
        
        echo "Restarting Apache..."
        /opt/bitnami/ctlscript.sh restart apache
        echo -e "${GREEN}✓ Apache restarted${NC}"
        echo ""
        
        echo -e "${BLUE}Testing redirects:${NC}"
        echo "  my.familytraveltracker.app → $(curl -sIk https://my.familytraveltracker.app 2>&1 | grep -E '(HTTP|Location)' | head -2)"
        echo ""
        
        echo -e "${GREEN}✓ Fix applied!${NC}"
        echo ""
        echo "All domain-level redirects are now handled by .htaccess only."
        echo "Test in browser: https://my.familytraveltracker.app"
    else
        echo -e "${RED}✗ Apache config error, restoring backup...${NC}"
        cp "$BACKUP_FILE" "$SSL_CONF"
        /opt/bitnami/apache2/bin/apachectl configtest
        exit 1
    fi
else
    echo "Skipped. No changes made."
fi
