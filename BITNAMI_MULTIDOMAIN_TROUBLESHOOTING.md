# Bitnami Multi-Domain Redirection Troubleshooting

## The Problem You're Experiencing

ChatGPT keeps pointing you to Apache configuration, but that's only **Layer 1** of a 3-layer problem. You need all three layers working together:

```
Layer 1: Apache Configuration  ← ChatGPT focused here
         ↓
Layer 2: WordPress Core        ← YOU'RE MISSING THIS!
         ↓
Layer 3: Plugin Logic          ← Already written in domain-routing.php
```

## Why Apache Alone Isn't Enough

**What Apache does:**
- Accepts requests from both www.familytraveltracker.app and my.familytraveltracker.app
- Routes both to the same DocumentRoot (/opt/bitnami/wordpress)
- Handles SSL certificates

**What Apache DOESN'T do:**
- Tell WordPress it's okay to respond to multiple domains
- Handle application-level redirects
- Route pages to correct subdomains

## The Missing Piece: wp-config.php

Your WordPress installation has hardcoded URLs in two places:

1. **wp-config.php** - PHP constants
2. **Database** - wp_options table

When WordPress loads, it checks these settings. If they don't match the domain in the browser, you get:
- Redirect loops
- "Too many redirects" errors  
- Blank pages
- 404 errors on one domain but not the other

## Solution Steps

### Step 1: Run Diagnostics

On your Bitnami server:

```bash
# Upload and run the diagnostic script
bash check-bitnami-setup.sh
```

This will check:
- ✓ Apache configuration
- ✓ SSL certificates
- ✓ WordPress wp-config.php settings
- ✓ Database URL settings
- ✓ Plugin installation
- ✓ DNS resolution

**Look for this output:**
```
3. WORDPRESS CONFIGURATION
--------------------------
✗ Missing dynamic domain configuration in wp-config.php
   This is likely your problem!
```

### Step 2: Fix WordPress Configuration

```bash
# Run the fix script
bash fix-wordpress-multidomain.sh
```

This script will:
1. Backup your current wp-config.php
2. Add these critical lines BEFORE `require_once(ABSPATH . 'wp-settings.php');`:

```php
/* Support multiple domains - BEGIN */
// Dynamic domain support for www and my subdomains
define('WP_HOME', 'https://' . $_SERVER['HTTP_HOST']);
define('WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST']);

// Force HTTPS
define('FORCE_SSL_ADMIN', true);

// Handle proxy/load balancer HTTPS detection
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// CloudFlare support
if (isset($_SERVER['HTTP_CF_VISITOR'])) {
    $cf_visitor = json_decode($_SERVER['HTTP_CF_VISITOR']);
    if (isset($cf_visitor->scheme) && $cf_visitor->scheme === 'https') {
        $_SERVER['HTTPS'] = 'on';
    }
}
/* Support multiple domains - END */
```

3. Test Apache configuration
4. Restart Apache

### Step 3: Verify Plugin is Active

```bash
# Check plugin status
cd /opt/bitnami/wordpress
sudo -u daemon /opt/bitnami/wp-cli/bin/wp plugin list

# If inactive, activate it
sudo -u daemon /opt/bitnami/wp-cli/bin/wp plugin activate schedule-collaboration-tracking
```

### Step 4: Test Both Domains

Open in your browser:

1. **www domain:** https://www.familytraveltracker.app
   - Should show marketing homepage
   - Should NOT redirect

2. **my domain:** https://my.familytraveltracker.app  
   - Should show marketing homepage (redirected to www)
   - If logged in, should show dashboard

3. **App page on www:** https://www.familytraveltracker.app/ftt-dashboard/
   - Should redirect to: https://my.familytraveltracker.app/ftt-dashboard/

4. **Login page:** https://www.familytraveltracker.app/wp-login.php
   - Should redirect to: https://my.familytraveltracker.app/wp-login.php

## Common Issues After Applying Fix

### Issue 1: Still Getting Redirect Loops

**Cause:** Browser has cached old redirects

**Fix:**
1. Clear browser cache completely
2. Try in incognito/private window
3. Try different browser
4. Clear CloudFlare cache if using CloudFlare

### Issue 2: One Domain Works, Other Doesn't

**Cause:** DNS not properly configured

**Fix:**
```bash
# Check both domains resolve to same IP
nslookup www.familytraveltracker.app
nslookup my.familytraveltracker.app

# Should return identical A records
```

### Issue 3: SSL Certificate Errors

**Cause:** Certificate doesn't cover both subdomains

**Fix:**
```bash
# Check certificate SANs (Subject Alternative Names)
sudo openssl x509 -in /opt/bitnami/apache2/conf/www.familytraveltracker.app.crt -noout -text | grep DNS

# Should show:
#   DNS:www.familytraveltracker.app
#   DNS:my.familytraveltracker.app
```

If not, re-run bn-cert with both domains:
```bash
sudo /opt/bitnami/bncert-tool
# Enter: www.familytraveltracker.app my.familytraveltracker.app
```

### Issue 4: 404 on Plugin Pages

**Cause:** Permalink structure not flushed

**Fix:**
```bash
# Flush rewrite rules
cd /opt/bitnami/wordpress
sudo -u daemon /opt/bitnami/wp-cli/bin/wp rewrite flush
```

Or in WordPress admin:
- Go to Settings → Permalinks
- Click "Save Changes" (don't change anything)

### Issue 5: Plugin Not Redirecting

**Cause:** Plugin pages not created

**Fix:**
1. Log into WordPress admin: https://my.familytraveltracker.app/wp-admin
2. Look for admin notice: "FTT plugin pages are missing"
3. Click "Recreate Pages"

Or via WP-CLI:
```bash
# Check for plugin pages
cd /opt/bitnami/wordpress
sudo -u daemon /opt/bitnami/wp-cli/bin/wp post list --post_type=page --name=ftt-dashboard

# If empty, deactivate and reactivate plugin
sudo -u daemon /opt/bitnami/wp-cli/bin/wp plugin deactivate schedule-collaboration-tracking
sudo -u daemon /opt/bitnami/wp-cli/bin/wp plugin activate schedule-collaboration-tracking
```

## Manual Configuration (If Scripts Don't Work)

### 1. Edit wp-config.php Manually

```bash
# Backup first
sudo cp /opt/bitnami/wordpress/wp-config.php /opt/bitnami/wordpress/wp-config.php.backup

# Edit with nano
sudo nano /opt/bitnami/wordpress/wp-config.php
```

Find this line near the end:
```php
require_once ABSPATH . 'wp-settings.php';
```

**Add the code block above BEFORE that line** (see Step 2 for the code)

Save with `Ctrl+O`, exit with `Ctrl+X`

### 2. Verify Apache Configuration

```bash
# Check bitnami-ssl.conf
sudo cat /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf | grep -A 3 ServerName
```

Should look like:
```apache
ServerName www.familytraveltracker.app
ServerAlias my.familytraveltracker.app
ServerAlias familytraveltracker.app
```

If not, edit:
```bash
sudo nano /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf
```

Find the `<VirtualHost *:443>` block and ensure ServerName/ServerAlias are correct.

### 3. Restart Services

```bash
# Test configuration first
sudo /opt/bitnami/apache2/bin/apachectl configtest

# If OK, restart
sudo /opt/bitnami/ctlscript.sh restart apache
```

## Understanding How Domain Routing Works

Once configured, here's what happens:

1. User visits **www.familytraveltracker.app**
   ↓
2. Apache accepts request (Layer 1 ✓)
   ↓
3. Apache routes to /opt/bitnami/wordpress
   ↓
4. WordPress loads with `WP_HOME = 'https://www.familytraveltracker.app'` (Layer 2 ✓)
   ↓
5. WordPress loads FTT plugin
   ↓
6. Plugin checks current domain with `$_SERVER['HTTP_HOST']`
   ↓
7. Plugin sees user is on 'www' domain
   ↓
8. If page is in `$app_pages` array, plugin redirects to 'my' domain (Layer 3 ✓)
   ↓
9. User sees correct page on correct domain

## Debugging Commands

```bash
# Check what domain WordPress thinks it's on
cd /opt/bitnami/wordpress
sudo -u daemon /opt/bitnami/wp-cli/bin/wp option get siteurl
sudo -u daemon /opt/bitnami/wp-cli/bin/wp option get home

# Check if plugin is active
sudo -u daemon /opt/bitnami/wp-cli/bin/wp plugin status schedule-collaboration-tracking

# Check for PHP errors
sudo tail -50 /opt/bitnami/apache2/logs/error_log

# Check for redirect loops in access log
sudo tail -100 /opt/bitnami/apache2/logs/access_log | grep -E "301|302"

# Test domain resolution
curl -I https://www.familytraveltracker.app
curl -I https://my.familytraveltracker.app
```

## Still Having Issues?

If after all this you're still having problems, gather this info:

```bash
# Create a debug report
cat > /tmp/debug-report.txt <<EOF
=== APACHE CONFIG ===
$(sudo grep -A 10 "ServerName" /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf)

=== WP-CONFIG ===
$(sudo grep -C 3 "WP_HOME\|WP_SITEURL" /opt/bitnami/wordpress/wp-config.php)

=== PLUGIN STATUS ===
$(sudo -u daemon /opt/bitnami/wp-cli/bin/wp plugin status schedule-collaboration-tracking)

=== RECENT ERRORS ===
$(sudo tail -20 /opt/bitnami/apache2/logs/error_log)

=== DNS CHECK ===
$(dig +short www.familytraveltracker.app A)
$(dig +short my.familytraveltracker.app A)
EOF

cat /tmp/debug-report.txt
```

Share this output for further debugging.
