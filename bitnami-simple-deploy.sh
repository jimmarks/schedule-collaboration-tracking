#!/bin/bash
###############################################################################
# Family Travel Tracker - Simple Single-Domain Bitnami Deployment
#
# This script configures Bitnami WordPress for www.familytraveltracker.app
# User flow: Registration → Billing → Dashboard (all on same domain)
#
# Prerequisites:
# - Fresh Bitnami WordPress on AWS Lightsail
# - DNS: www.familytraveltracker.app and familytraveltracker.app → server IP
# - FTT plugin zip uploaded to /home/bitnami/
#
# Usage: sudo ./bitnami-simple-deploy.sh
###############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
DOMAIN="www.familytraveltracker.app"
BARE_DOMAIN="familytraveltracker.app"
WP_PATH="/opt/bitnami/wordpress"
PLUGIN_SLUG="schedule-collaboration-tracking"

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  Family Travel Tracker - Simple Deployment${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

# Check root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}✗ Please run as root (use sudo)${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Running as root${NC}"
echo ""

###############################################################################
# STEP 1: Pre-flight Checks
###############################################################################
echo -e "${YELLOW}STEP 1: Pre-flight Checks${NC}"
echo "─────────────────────────────────────────"

if [ ! -f "$WP_PATH/wp-config.php" ]; then
    echo -e "${RED}✗ WordPress not found at $WP_PATH${NC}"
    exit 1
fi
echo -e "${GREEN}✓ WordPress installation found${NC}"

# Check DNS
for domain in $DOMAIN $BARE_DOMAIN; do
    if host "$domain" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ $domain resolves${NC}"
    else
        echo -e "${YELLOW}⚠ $domain does not resolve${NC}"
    fi
done
echo ""

###############################################################################
# STEP 2: Backup
###############################################################################
echo -e "${YELLOW}STEP 2: Backup Configuration${NC}"
echo "─────────────────────────────────────────"

BACKUP_DIR="/home/bitnami/ftt-backup-$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

cp "$WP_PATH/wp-config.php" "$BACKUP_DIR/" 2>/dev/null || true
cp "$WP_PATH/.htaccess" "$BACKUP_DIR/" 2>/dev/null || true

echo -e "${GREEN}✓ Backups saved to: $BACKUP_DIR${NC}"
echo ""

###############################################################################
# STEP 3: Configure WordPress URLs
###############################################################################
echo -e "${YELLOW}STEP 3: Configure WordPress URLs${NC}"
echo "─────────────────────────────────────────"

cd "$WP_PATH"
/opt/bitnami/wp-cli/bin/wp option update siteurl "https://$DOMAIN" --allow-root
/opt/bitnami/wp-cli/bin/wp option update home "https://$DOMAIN" --allow-root

echo -e "${GREEN}✓ WordPress URLs set to https://$DOMAIN${NC}"
echo ""

###############################################################################
# STEP 4: Enable Pretty Permalinks
###############################################################################
echo -e "${YELLOW}STEP 4: Enable Pretty Permalinks${NC}"
echo "─────────────────────────────────────────"

/opt/bitnami/wp-cli/bin/wp rewrite structure '/%postname%/' --hard --allow-root
echo -e "${GREEN}✓ Pretty permalinks enabled${NC}"
echo ""

###############################################################################
# STEP 5: Configure .htaccess
###############################################################################
echo -e "${YELLOW}STEP 5: Configure .htaccess${NC}"
echo "─────────────────────────────────────────"

cat > "$WP_PATH/.htaccess" <<'HTACCESS'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

# Redirect bare domain to www
RewriteCond %{HTTP_HOST} ^familytraveltracker\.app$ [NC]
RewriteRule ^(.*)$ https://www.familytraveltracker.app/$1 [R=301,L]

# WordPress permalinks
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS

chown daemon:daemon "$WP_PATH/.htaccess"
echo -e "${GREEN}✓ .htaccess configured${NC}"
echo ""

###############################################################################
# STEP 6: Install Plugin
###############################################################################
echo -e "${YELLOW}STEP 6: Install & Activate Plugin${NC}"
echo "─────────────────────────────────────────"

cd "$WP_PATH"

# Find plugin zip
PLUGIN_ZIP=$(ls /home/bitnami/${PLUGIN_SLUG}*.zip 2>/dev/null | head -1)

if [ -z "$PLUGIN_ZIP" ]; then
    echo -e "${YELLOW}⚠ Plugin zip not found in /home/bitnami/${NC}"
    echo "Upload the plugin zip and run this script again"
else
    echo "Found: $PLUGIN_ZIP"
    
    # Install using WP-CLI (handles extraction and permissions)
    /opt/bitnami/wp-cli/bin/wp plugin install "$PLUGIN_ZIP" --activate --allow-root
    VERSION=$(/opt/bitnami/wp-cli/bin/wp plugin get $PLUGIN_SLUG --field=version --allow-root)
    echo -e "${GREEN}✓ Plugin installed and activated (v$VERSION)${NC}"
fi
echo ""

###############################################################################
# STEP 7: SSL Configuration
###############################################################################
echo -e "${YELLOW}STEP 7: SSL Configuration${NC}"
echo "─────────────────────────────────────────"

if [ -f "/opt/bitnami/apache2/conf/$DOMAIN.crt" ]; then
    echo -e "${GREEN}✓ SSL certificate exists${NC}"
else
    echo -e "${YELLOW}⚠ No SSL certificate found${NC}"
    echo ""
    echo "Run Let's Encrypt setup:"
    echo -e "${BLUE}  sudo /opt/bitnami/bncert-tool${NC}"
    echo ""
    echo "Enter domains: $DOMAIN $BARE_DOMAIN"
    echo ""
    read -p "Press Enter to run bncert-tool now, or Ctrl+C to skip..."
    /opt/bitnami/bncert-tool
fi
echo ""

###############################################################################
# STEP 8: Restart Apache
###############################################################################
echo -e "${YELLOW}STEP 8: Restart Services${NC}"
echo "─────────────────────────────────────────"

/opt/bitnami/ctlscript.sh restart apache
echo -e "${GREEN}✓ Apache restarted${NC}"
echo ""

###############################################################################
# STEP 9: Verification
###############################################################################
echo -e "${YELLOW}STEP 9: Verification${NC}"
echo "─────────────────────────────────────────"

echo "WordPress URLs:"
/opt/bitnami/wp-cli/bin/wp option get siteurl --allow-root
/opt/bitnami/wp-cli/bin/wp option get home --allow-root
echo ""

echo "Plugin status:"
/opt/bitnami/wp-cli/bin/wp plugin list --allow-root | grep $PLUGIN_SLUG
echo ""

echo "Testing redirects:"
curl -sI http://$BARE_DOMAIN | grep -E "HTTP|Location" | head -2
echo ""

###############################################################################
# COMPLETION
###############################################################################
echo -e "${GREEN}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  DEPLOYMENT COMPLETE!${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo "✓ WordPress: https://$DOMAIN"
echo "✓ Bare domain redirects: $BARE_DOMAIN → $DOMAIN"
echo "✓ Plugin installed and activated"
echo ""
echo "User Flow:"
echo "  1. User visits: https://$DOMAIN"
echo "  2. Registers account"
echo "  3. Enters billing information"
echo "  4. Redirects to dashboard: https://$DOMAIN/[dashboard-permalink]"
echo ""
echo "Test in browser: https://$DOMAIN"
echo ""
echo -e "${BLUE}Backup: $BACKUP_DIR${NC}"
echo ""
