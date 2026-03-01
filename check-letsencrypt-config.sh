#!/bin/bash
###############################################################################
# Check Let's Encrypt Include Files
###############################################################################

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${YELLOW}Checking Let's Encrypt configuration files...${NC}"
echo ""

LETSENCRYPT_PREFIX="/opt/bitnami/apps/letsencrypt/conf/httpd-prefix.conf"

if [ -f "$LETSENCRYPT_PREFIX" ]; then
    echo -e "${BLUE}Contents of $LETSENCRYPT_PREFIX:${NC}"
    echo "═══════════════════════════════════════════"
    cat "$LETSENCRYPT_PREFIX"
    echo "═══════════════════════════════════════════"
    echo ""
    
    # Check for www redirect rules
    if grep -i "www" "$LETSENCRYPT_PREFIX"; then
        echo -e "${RED}Found www-related rules in Let's Encrypt config!${NC}"
        echo ""
        
        BACKUP="/home/bitnami/letsencrypt-httpd-prefix.backup-$(date +%Y%m%d_%H%M%S)"
        cp "$LETSENCRYPT_PREFIX" "$BACKUP"
        echo -e "${GREEN}✓ Backed up to: $BACKUP${NC}"
        echo ""
        
        read -p "Remove www redirect rules from this file? (y/n) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            # Remove the RewriteRule that adds www
            sed -i '/RewriteRule.*www\./d' "$LETSENCRYPT_PREFIX"
            sed -i '/RewriteCond.*!^www\./d' "$LETSENCRYPT_PREFIX"
            
            echo -e "${GREEN}✓ Removed www redirect rules${NC}"
            echo ""
            echo "New contents:"
            cat "$LETSENCRYPT_PREFIX"
            echo ""
            
            echo "Restarting Apache..."
            /opt/bitnami/ctlscript.sh restart apache
            echo -e "${GREEN}✓ Apache restarted${NC}"
            echo ""
            
            echo "Testing redirect:"
            curl -IL https://my.familytraveltracker.app 2>&1 | grep -E "(HTTP|Location)" | head -3
        fi
    else
        echo -e "${GREEN}No www-related rules found in Let's Encrypt config${NC}"
    fi
else
    echo -e "${YELLOW}Let's Encrypt prefix file not found${NC}"
fi

echo ""
echo "Also checking for any other included configs..."
find /opt/bitnami -name "*.conf" -type f 2>/dev/null | while read conf; do
    if grep -l "RewriteRule.*www\." "$conf" 2>/dev/null; then
        echo -e "${YELLOW}Found www RewriteRule in: $conf${NC}"
    fi
done
