# AWS Bitnami WordPress Deployment for Family Travel Tracker

## Overview

This script automates the complete configuration of a Bitnami WordPress installation on AWS to run the Family Travel Tracker plugin with dual-domain routing.

## Quick Start

### 1. Prerequisites

Before running this script, ensure you have:

- ✅ **AWS Bitnami WordPress Stack** running
- ✅ **DNS records** configured:
  - `A` record: `www.familytraveltracker.app` → your server IP
  - `A` record: `my.familytraveltracker.app` → your server IP  
  - `A` record: `familytraveltracker.app` → your server IP
- ✅ **SSH access** to your server as `bitnami` user
- ✅ **Plugin zip file** ready to upload

### 2. Upload Script and Plugin

From your local machine:

```bash
# Upload deployment script
scp bitnami-ftt-deploy.sh bitnami@YOUR_SERVER_IP:~/

# Upload plugin zip
scp package/schedule-collaboration-tracking/schedule-collaboration-tracking-v*.zip bitnami@YOUR_SERVER_IP:/tmp/
```

### 3. Run Deployment

SSH into your server and run:

```bash
ssh bitnami@YOUR_SERVER_IP

# Make executable
chmod +x bitnami-ftt-deploy.sh

# Run as root
sudo ./bitnami-ftt-deploy.sh
```

The script will:
1. ✅ Check WordPress installation
2. ✅ Backup all existing configs
3. ✅ Configure WordPress for multi-domain
4. ✅ Update Apache VirtualHost
5. ✅ Run Let's Encrypt setup (interactive)
6. ✅ Install and activate plugin
7. ✅ Configure permalinks
8. ✅ Create .htaccess with redirects
9. ✅ Restart services
10. ✅ Verify configuration

## What the Script Does

### WordPress Configuration

Adds to `wp-config.php`:

```php
define('WP_HOME', 'https://' . $_SERVER['HTTP_HOST']);
define('WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST']);
define('FORCE_SSL_ADMIN', true);
```

This allows WordPress to respond correctly to all three domains.

### Apache Configuration

Updates `/opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf`:

```apache
ServerName www.familytraveltracker.app
ServerAlias my.familytraveltracker.app
ServerAlias familytraveltracker.app
DocumentRoot "/opt/bitnami/wordpress"
```

### .htaccess Rules

Creates optimized .htaccess with:
- Force HTTPS redirect
- Bare domain → www redirect
- WordPress permalinks

### Plugin Activation

- Extracts plugin to `wp-content/plugins/`
- Activates via WP-CLI
- Verifies activation

### Permalink Structure

Sets permalink structure to `/%postname%/` which is required for domain routing to work.

## Expected Behavior After Deployment

| URL | What Happens |
|-----|--------------|
| `http://familytraveltracker.app` | → Redirects to `https://www.familytraveltracker.app` |
| `https://www.familytraveltracker.app` | Shows marketing homepage |
| `https://my.familytraveltracker.app` | Shows WordPress (redirects homepage to www) |
| `https://www.familytraveltracker.app/ftt-dashboard/` | → Redirects to `https://my.familytraveltracker.app/ftt-dashboard/` |
| `https://my.familytraveltracker.app/ftt-dashboard/` | Shows dashboard (after login) |

## Troubleshooting

### Script Fails at Step X

Each step creates backups. To restore:

```bash
# Script shows backup location, usually:
# /home/bitnami/ftt-backup-YYYYMMDD_HHMMSS/

# Restore wp-config.php
sudo cp /home/bitnami/ftt-backup-*/wp-config.php.backup /opt/bitnami/wordpress/wp-config.php

# Restore Apache configs
sudo cp /home/bitnami/ftt-backup-*/bitnami-ssl.conf.backup /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf

# Restart Apache
sudo /opt/bitnami/ctlscript.sh restart apache
```

### DNS Not Resolving

If domains don't resolve yet:

1. Wait 5-15 minutes for DNS propagation
2. Run script again (it's idempotent - safe to re-run)
3. You can still proceed - Let's Encrypt will fail but you can re-run just that step

To re-run just Let's Encrypt:

```bash
sudo /opt/bitnami/bncert-tool
```

### Plugin Not Found

If you forgot to upload the plugin zip:

```bash
# Upload it
scp schedule-collaboration-tracking-v*.zip bitnami@YOUR_SERVER_IP:/tmp/

# Re-run just the plugin section (as root)
cd /opt/bitnami/wordpress
unzip /tmp/schedule-collaboration-tracking-v*.zip -d wp-content/plugins/
chown -R daemon:daemon wp-content/plugins/schedule-collaboration-tracking
su -s /bin/bash daemon -c "/opt/bitnami/wp-cli/bin/wp plugin activate schedule-collaboration-tracking"
```

### Redirect Loop

If you see "too many redirects":

1. **Clear browser cache/cookies** (this is usually the issue)
2. Try in incognito/private window
3. Check .htaccess has correct rules
4. Verify wp-config.php has dynamic domain support

### SSL Certificate Issues

If Let's Encrypt fails:

```bash
# Check DNS first
host www.familytraveltracker.app
host my.familytraveltracker.app
host familytraveltracker.app

# All three should return your server IP

# If DNS is good, manually run bn-cert
sudo /opt/bitnami/bncert-tool

# When prompted, enter:
# www.familytraveltracker.app my.familytraveltracker.app familytraveltracker.app

# Enable redirects: Yes
# Enable non-www redirect: No (we want both www and my to work)
```

### Plugin Not Redirecting

If domain redirects aren't working:

```bash
# Check plugin is active
cd /opt/bitnami/wordpress
su -s /bin/bash daemon -c "/opt/bitnami/wp-cli/bin/wp plugin status schedule-collaboration-tracking"

# Check permalinks are enabled
su -s /bin/bash daemon -c "/opt/bitnami/wp-cli/bin/wp option get permalink_structure"
# Should output: /%postname%/

# Flush rewrite rules
su -s /bin/bash daemon -c "/opt/bitnami/wp-cli/bin/wp rewrite flush --hard"

# Check for PHP errors
sudo tail -50 /opt/bitnami/apache2/logs/error_log
```

## Manual Steps After Deployment

### 1. Create WordPress Pages

Log into WordPress admin (`https://www.familytraveltracker.app/wp-admin`) and create:

**Marketing Pages (for www domain):**
- Home (set as homepage in Settings → Reading)
- Features
- Pricing
- About
- Support
- Sign Up (use template from `templates/signup-page.html`)

**App Pages (auto-created by plugin):**
- ftt-dashboard
- ftt-calendar
- ftt-events
- ftt-billing

### 2. Configure Navigation Menus

Go to **Appearance → Menus**:

1. **Create "Marketing Primary" menu:**
   - Add: Home, Features, Pricing, About, Support
   - Add custom link: "Sign Up" → `/sign-up/`
   - Add custom link: "Log In" → `https://my.familytraveltracker.app/wp-login.php`
   - Assign to: "Marketing Primary Menu"

2. **Create "App Primary" menu:**
   - Add: Dashboard, Calendar, Events, Billing
   - Assign to: "App Primary Menu"

### 3. Configure Stripe (if using billing)

Go to **FTT → Stripe Settings**:
- Add API keys (test mode first)
- Set price IDs
- Leave "App Domain" blank (auto-detected)

## Checking Logs

```bash
# Apache error log
sudo tail -f /opt/bitnami/apache2/logs/error_log

# Apache access log
sudo tail -f /opt/bitnami/apache2/logs/access_log

# WordPress debug log (if WP_DEBUG enabled)
sudo tail -f /opt/bitnami/wordpress/wp-content/debug.log
```

## Re-running the Script

The script is **idempotent** - safe to run multiple times. It will:
- Skip steps that are already configured
- Create new backups each time
- Show status of existing configuration

## Uninstalling / Starting Over

To completely reset:

```bash
# Restore from backup
BACKUP_DIR="/home/bitnami/ftt-backup-YYYYMMDD_HHMMSS"  # Find latest

sudo cp $BACKUP_DIR/wp-config.php.backup /opt/bitnami/wordpress/wp-config.php
sudo cp $BACKUP_DIR/bitnami-ssl.conf.backup /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf
sudo cp $BACKUP_DIR/bitnami.conf.backup /opt/bitnami/apache2/conf/bitnami/bitnami.conf
sudo cp $BACKUP_DIR/htaccess.backup /opt/bitnami/wordpress/.htaccess

# Deactivate and remove plugin
cd /opt/bitnami/wordpress
su -s /bin/bash daemon -c "/opt/bitnami/wp-cli/bin/wp plugin deactivate schedule-collaboration-tracking"
sudo rm -rf wp-content/plugins/schedule-collaboration-tracking

# Restart Apache
sudo /opt/bitnami/ctlscript.sh restart apache
```

## Getting Help

If you encounter issues not covered here:

1. Check the backup directory for original configs
2. Review Apache error logs
3. Test with `curl` to see actual redirects:

```bash
# Test bare domain
curl -I http://familytraveltracker.app

# Test www
curl -Ik https://www.familytraveltracker.app

# Test my subdomain
curl -Ik https://my.familytraveltracker.app

# Test app page redirect
curl -Ik https://www.familytraveltracker.app/ftt-dashboard/
```

## Script Locations

After deployment:
- **Deployment script**: `/home/bitnami/bitnami-ftt-deploy.sh`
- **Backups**: `/home/bitnami/ftt-backup-YYYYMMDD_HHMMSS/`
- **WordPress**: `/opt/bitnami/wordpress/`
- **Apache configs**: `/opt/bitnami/apache2/conf/bitnami/`
- **Plugin**: `/opt/bitnami/wordpress/wp-content/plugins/schedule-collaboration-tracking/`
