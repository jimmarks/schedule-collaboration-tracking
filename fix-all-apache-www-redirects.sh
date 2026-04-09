#!/bin/bash
###############################################################################
# Remove www redirects from ALL Apache config files
###############################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${YELLOW}Fixing ALL Apache configs with www redirects${NC}"
echo "═══════════════════════════════════════════"
echo ""

FILES=(
    "/opt/bitnami/apache/conf/bitnami/bitnami.conf"
    "/opt/bitnami/apache/conf/vhosts/wordpress-vhost.conf"
    "/opt/bitnami/apache/conf/vhosts/wordpress-https-vhost.conf"
)

BACKUP_DIR="/home/bitnami/apache-config-backup-$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

for FILE in "${FILES[@]}"; do
    if [ -f "$FILE" ]; then
        echo -e "${BLUE}Checking: $FILE${NC}"
        
        # Backup
        BASENAME=$(basename "$FILE")
        cp "$FILE" "$BACKUP_DIR/$BASENAME"
        echo "  ✓ Backed up to $BACKUP_DIR/$BASENAME"
        
        # Show www-related lines before
        echo "  Current www rules:"
        grep -n "www\." "$FILE" | head -5 || echo "    (none found)"
        
        # Remove www redirect rules
        sed -i '/RewriteEngine On/!b; :a; n; /RewriteCond.*!^www\./d; /RewriteCond.*!^localhost/d; /RewriteCond.*\[0-9\]\+\.\[0-9\]\+\.\[0-9\]\+\.\[0-9\]/d; /RewriteCond.*REQUEST_URI.*well-known/d; /RewriteRule.*https:\/\/www\./d; ta' "$FILE"
        sed -i '/RewriteCond.*!^www\./d' "$FILE"
        sed -i '/RewriteRule.*www\.%{HTTP_HOST}/d' "$FILE"
        sed -i '/RewriteRule.*https:\/\/www\./d' "$FILE"
        
        echo -e "  ${GREEN}✓ Removed www redirect rules${NC}"
        echo ""
    else
        echo -e "${YELLOW}  File not found: $FILE${NC}"
        echo ""
    fi
done

echo -e "${GREEN}All configs updated!${NC}"
echo ""

# Test Apache config
echo "Testing Apache configuration..."
if /opt/bitnami/apache2/bin/apachectl configtest 2>&1 | grep -q "Syntax OK"; then
    echo -e "${GREEN}✓ Apache configuration is valid${NC}"
    echo ""
    
    echo "Restarting Apache..."
    /opt/bitnami/ctlscript.sh restart apache
    echo -e "${GREEN}✓ Apache restarted${NC}"
    echo ""
    
    echo -e "${BLUE}Testing redirects:${NC}"
    echo "  my.familytraveltracker.app:"
    curl -sIL https://my.familytraveltracker.app 2>&1 | grep -E "(HTTP|Location)" | head -3
    echo ""
    
    echo "  www.familytraveltracker.app:"
    curl -sILk https://www.familytraveltracker.app 2>&1 | grep -E "(HTTP|Location)" | head -2
    echo ""
    
    echo -e "${GREEN}✓ Fix complete!${NC}"
    echo ""
    echo "Test in browser (incognito window):"
    echo "  https://my.familytraveltracker.app"
    echo "  https://www.familytraveltracker.app"
else
    echo -e "${RED}✗ Apache config error!${NC}"
    /opt/bitnami/apache2/bin/apachectl configtest
    echo ""
    echo -e "${YELLOW}Restoring backups...${NC}"
    for FILE in "${FILES[@]}"; do
        BASENAME=$(basename "$FILE")
        if [ -f "$BACKUP_DIR/$BASENAME" ]; then
            cp "$BACKUP_DIR/$BASENAME" "$FILE"
        fi
    done
    exit 1
fi

echo "Backup location: $BACKUP_DIR"
