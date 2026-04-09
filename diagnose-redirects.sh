#!/bin/bash
###############################################################################
# Diagnose Redirect Issues
###############################################################################

echo "Testing URL redirects..."
echo ""

echo "1. Testing my.familytraveltracker.app (HTTP):"
curl -sIL http://my.familytraveltracker.app 2>&1 | grep -E "(HTTP|Location)"
echo ""

echo "2. Testing my.familytraveltracker.app (HTTPS):"
curl -sILk https://my.familytraveltracker.app 2>&1 | grep -E "(HTTP|Location)"
echo ""

echo "3. Testing www.familytraveltracker.app (HTTPS):"
curl -sILk https://www.familytraveltracker.app 2>&1 | grep -E "(HTTP|Location)"
echo ""

echo "4. Checking WordPress site_url and home_url options:"
cd /opt/bitnami/wordpress
/opt/bitnami/wp-cli/bin/wp option get siteurl --allow-root 2>/dev/null || echo "Could not get siteurl"
/opt/bitnami/wp-cli/bin/wp option get home --allow-root 2>/dev/null || echo "Could not get home"
echo ""

echo "5. Checking Apache ServerName/ServerAlias in SSL config:"
grep -E "(ServerName|ServerAlias)" /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf
echo ""

echo "6. Checking .htaccess:"
cat /opt/bitnami/wordpress/.htaccess
echo ""

echo "7. Checking if plugin is active:"
/opt/bitnami/wp-cli/bin/wp plugin list --allow-root | grep schedule-collaboration-tracking
