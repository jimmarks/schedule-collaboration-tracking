# Bitnami Apache Dual-Domain Setup Guide

## Understanding Bitnami's Apache Structure

Bitnami WordPress uses a specific Apache configuration structure:

```
/opt/bitnami/apache2/conf/
├── httpd.conf                          # Main Apache config (don't modify)
├── bitnami/
│   ├── bitnami.conf                   # HTTP (port 80) config
│   └── bitnami-ssl.conf               # HTTPS (port 443) config
└── vhosts/                            # Custom VirtualHost configs
    └── myapp-https-vhost.conf         # Your custom SSL config goes here
```

## The Problem

When you run `bn-cert` multiple times or for multiple domains, it can:
- Create conflicting `ServerName` directives
- Generate multiple VirtualHost blocks
- Cause redirect loops between domains
- Mix certificate references

## The Solution: Clean Configuration

### Step 1: Check Current Configuration

```bash
# Check what's in bitnami-ssl.conf
sudo cat /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf | grep -A 3 "ServerName"

# Check which certificates actually exist
sudo ls -la /opt/bitnami/apache2/conf/*.crt
sudo ls -la /opt/bitnami/apache2/conf/*.key

# Check if custom vhost exists
ls -la /opt/bitnami/apache2/conf/vhosts/
```

**Important:** Look at the certificate files that actually exist. After running `bn-cert`, you should see files like:
- `www.familytraveltracker.app.crt`
- `www.familytraveltracker.app.key`
- `www.familytraveltracker.app.issuer.crt`

Use these exact filenames in your configuration!

### Step 2: Backup Current Configuration

```bash
sudo cp /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf.backup
sudo cp /opt/bitnami/apache2/conf/bitnami/bitnami.conf /opt/bitnami/apache2/conf/bitnami/bitnami.conf.backup
```

### Step 3: Clean Up bitnami-ssl.conf

The `bitnami-ssl.conf` file should handle **both** domains in a **single VirtualHost**:

**Edit `/opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf`:**

```bash
sudo nano /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf
```

**Replace the `<VirtualHost *:443>` block with this:**

```apache
<VirtualHost *:443>
  # Primary domain
  ServerName www.familytraveltracker.app
  
  # Additional domains (same VirtualHost)
  ServerAlias my.familytraveltracker.app
  ServerAlias familytraveltracker.app
  
  # Document root
  DocumentRoot "/opt/bitnami/wordpress"
  
  # SSL Configuration (paths after running bn-cert)
  SSLEngine on
  SSLCertificateFile "/opt/bitnami/apache2/conf/www.familytraveltracker.app.crt"
  SSLCertificateKeyFile "/opt/bitnami/apache2/conf/www.familytraveltracker.app.key"
  SSLCACertificateFile "/opt/bitnami/apache2/conf/www.familytraveltracker.app.issuer.crt"
  
  # NOTE: If you haven't run bn-cert yet and are using self-signed certs:
  # SSLCertificateFile "/opt/bitnami/apache2/conf/server.crt"
  # SSLCertificateKeyFile "/opt/bitnami/apache2/conf/server.key"
  
  # Modern SSL settings
  SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
  SSLCipherSuite HIGH:!aNULL:!MD5:!3DES
  SSLHonorCipherOrder on
  
  # Directory permissions
  <Directory "/opt/bitnami/wordpress">
    Options +FollowSymLinks +MultiViews
    AllowOverride All
    Require all granted
    
    # WordPress pretty permalinks
    <IfModule mod_rewrite.c>
      RewriteEngine On
      RewriteBase /
      RewriteRule ^index\.php$ - [L]
      RewriteCond %{REQUEST_FILENAME} !-f
      RewriteCond %{REQUEST_FILENAME} !-d
      RewriteRule . /index.php [L]
    </IfModule>
  </Directory>
  
  # Proxy settings (if needed for PHP-FPM)
  <IfModule proxy_fcgi_module>
    <FilesMatch \.php$>
      SetHandler "proxy:unix:/opt/bitnami/php/var/run/www.sock|fcgi://localhost/"
    </FilesMatch>
  </IfModule>
  
  # Log files
  ErrorLog "/opt/bitnami/apache2/logs/error_log"
  CustomLog "/opt/bitnami/apache2/logs/access_log" combined
  
  # Bitnami banner (remove if you want)
  Include "/opt/bitnami/apache2/conf/bitnami/bitnami-apps-prefix.conf"
</VirtualHost>
```

### Step 4: Configure HTTP to HTTPS Redirect

**Edit `/opt/bitnami/apache2/conf/bitnami/bitnami.conf`:**

```bash
sudo nano /opt/bitnami/apache2/conf/bitnami/bitnami.conf
```

**Replace the `<VirtualHost *:80>` block with this:**

```apache
<VirtualHost *:80>
  ServerName www.familytraveltracker.app
  ServerAlias my.familytraveltracker.app familytraveltracker.app
  
  DocumentRoot "/opt/bitnami/wordpress"
  
  # Redirect all HTTP to HTTPS
  RewriteEngine On
  RewriteCond %{HTTPS} !=on
  RewriteRule ^/?(.*) https://%{SERVER_NAME}/$1 [R=301,L]
  
  # If the above doesn't work, use this simpler version:
  # Redirect permanent / https://www.familytraveltracker.app/
</VirtualHost>
```

### Step 5: Let's Encrypt Certificate Setup

If you want to use Let's Encrypt certificates with proper paths:

**Run bn-cert for the primary domain only:**

```bash
sudo /opt/bitnami/bncert-tool
```

**Configuration during bn-cert:**
- Domain list: `www.familytraveltracker.app my.familytraveltracker.app familytraveltracker.app`
- Enable HTTP to HTTPS redirection: `Yes`
- Enable non-www to www redirection: `No` (we want both www and my to work)
- Enable www to non-www redirection: `No`

**Important:** The bn-cert tool will modify your config files. After it completes, you may need to edit `bitnami-ssl.conf` again to ensure it matches the structure above.

### Step 6: SSL Certificate Paths After bn-cert

After running bn-cert, your SSL certificate paths in `bitnami-ssl.conf` will be updated to:

```apache
SSLCertificateFile "/opt/bitnami/apache2/conf/www.familytraveltracker.app.crt"
SSLCertificateKeyFile "/opt/bitnami/apache2/conf/www.familytraveltracker.app.key"
SSLCACertificateFile "/opt/bitnami/apache2/conf/www.familytraveltracker.app.issuer.crt"
```

This is correct! The certificate includes all domains you specified (called Subject Alternative Names).

### Step 7: Test Configuration

```bash
# Test Apache configuration syntax
sudo /opt/bitnami/apache2/bin/apachectl configtest

# Should output: Syntax OK
```

### Step 8: Restart Apache

```bash
# Restart just Apache
sudo /opt/bitnami/ctlscript.sh restart apache

# Or restart all Bitnami services
sudo /opt/bitnami/ctlscript.sh restart
```

### Step 9: Verify DNS

Make sure both domains point to your server:

```bash
# From your local machine, not the server
nslookup www.familytraveltracker.app
nslookup my.familytraveltracker.app
```

Both should return the same IP address as your server.

---

## Common Issues & Fixes

### Issue 1: "Too many redirects" / Redirect Loop

**Symptom:** Browser shows "ERR_TOO_MANY_REDIRECTS"

**Causes:**
1. WordPress URL settings don't match domain
2. Conflicting redirect rules
3. Load balancer/proxy forwarding issues

**Fix:**

```bash
# Edit wp-config.php
sudo nano /opt/bitnami/wordpress/wp-config.php
```

Add this **before** `require_once(ABSPATH . 'wp-settings.php');`:

```php
// Dynamic domain support
define('WP_HOME', 'https://' . $_SERVER['HTTP_HOST']);
define('WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST']);

// Force HTTPS
define('FORCE_SSL_ADMIN', true);

// Handle proxy/load balancer
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// CloudFlare support
if (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false) {
    $_SERVER['HTTPS'] = 'on';
}
```

### Issue 2: DNS_PROBE_FINISHED_NXDOMAIN for my.familytraveltracker.app

**Cause:** DNS A record doesn't exist

**Fix:** Add DNS record at your DNS provider:
```
Type: A
Name: my
Value: [Your Server IP]
TTL: 300
```

### Issue 3: Certificate Mismatch Warning (ERR_CERT_COMMON_NAME_INVALID)

**Cause:** Apache `ServerName` doesn't match certificate Common Name

**Example:** Your certificate CN is `familytraveltracker.app` but Apache `ServerName` is set to `www.familytraveltracker.app`

**Fix Option 1 - Match Apache to Certificate:**

Check your certificate CN:
```bash
sudo openssl x509 -in /opt/bitnami/apache2/conf/familytraveltracker.app.crt -noout -subject
```

Then update `bitnami-ssl.conf` to match:
```apache
<VirtualHost *:443>
  ServerName familytraveltracker.app
  ServerAlias www.familytraveltracker.app my.familytraveltracker.app
  
  SSLCertificateFile "/opt/bitnami/apache2/conf/familytraveltracker.app.crt"
  SSLCertificateKeyFile "/opt/bitnami/apache2/conf/familytraveltracker.app.key"
  SSLCACertificateFile "/opt/bitnami/apache2/conf/familytraveltracker.app.issuer.crt"
  # ... rest
</VirtualHost>
```

**Fix Option 2 - Re-issue Certificate:**

Re-run bn-cert with desired primary domain first:
```bash
sudo /opt/bitnami/bncert-tool
# Enter: www.familytraveltracker.app my.familytraveltracker.app familytraveltracker.app
```

### Issue 4: Apache Won't Start After Configuration Change

**Check logs:**
```bash
sudo tail -f /opt/bitnami/apache2/logs/error_log
```

**Common causes:**
- Syntax error in config: `sudo apachectl configtest`
- Port already in use: `sudo netstat -tlnp | grep :443`
- Certificate file not found: Check paths in bitnami-ssl.conf

### Issue 5: Only One Domain Works

**Check ServerAlias:**
```bash
sudo grep -n "ServerName\|ServerAlias" /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf
```

Should show:
```
ServerName www.familytraveltracker.app
ServerAlias my.familytraveltracker.app familytraveltracker.app
```

---

## Debugging Commands

### Check Current VirtualHost Configuration
```bash
sudo /opt/bitnami/apache2/bin/httpd -S
```

### Check Which Certificates Are Installed
```bash
sudo ls -la /opt/bitnami/apache2/conf/*.crt
sudo ls -la /opt/bitnami/apache2/conf/*.key
```

### Test SSL Certificate Details
```bash
openssl s_client -connect www.familytraveltracker.app:443 -servername www.familytraveltracker.app < /dev/null 2>/dev/null | openssl x509 -noout -text | grep -A 2 "Subject Alternative Name"
```

Should show all three domains in the certificate.

### Check Apache Is Listening on Port 443
```bash
sudo netstat -tlnp | grep :443
```

### View Real-Time Apache Logs
```bash
# Error log
sudo tail -f /opt/bitnami/apache2/logs/error_log

# Access log
sudo tail -f /opt/bitnami/apache2/logs/access_log
```

---

## Complete Configuration Example

### bitnami-ssl.conf (After bn-cert)

```apache
# Bitnami WordPress HTTPS VirtualHost Configuration
<VirtualHost *:443>
  ServerName www.familytraveltracker.app
  ServerAlias my.familytraveltracker.app
  ServerAlias familytraveltracker.app
  
  DocumentRoot "/opt/bitnami/wordpress"
  
  SSLEngine on
  SSLCertificateFile "/opt/bitnami/apache2/conf/www.familytraveltracker.app.crt"
  SSLCertificateKeyFile "/opt/bitnami/apache2/conf/www.familytraveltracker.app.key"
  SSLCACertificateFile "/opt/bitnami/apache2/conf/www.familytraveltracker.app.issuer.crt"
  
  SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
  SSLCipherSuite HIGH:!aNULL:!MD5:!3DES
  SSLHonorCipherOrder on
  
  <Directory "/opt/bitnami/wordpress">
    Options +FollowSymLinks +MultiViews
    AllowOverride All
    Require all granted
    
    <IfModule mod_rewrite.c>
      RewriteEngine On
      RewriteBase /
      RewriteRule ^index\.php$ - [L]
      RewriteCond %{REQUEST_FILENAME} !-f
      RewriteCond %{REQUEST_FILENAME} !-d
      RewriteRule . /index.php [L]
    </IfModule>
  </Directory>
  
  <IfModule proxy_fcgi_module>
    <FilesMatch \.php$>
      SetHandler "proxy:unix:/opt/bitnami/php/var/run/www.sock|fcgi://localhost/"
    </FilesMatch>
  </IfModule>
  
  ErrorLog "/opt/bitnami/apache2/logs/error_log"
  CustomLog "/opt/bitnami/apache2/logs/access_log" combined
  
  Include "/opt/bitnami/apache2/conf/bitnami/bitnami-apps-prefix.conf"
</VirtualHost>
```

### wp-config.php (Critical Settings)

```php
<?php
// Dynamic domain support - ADD THIS AT THE TOP
define('WP_HOME', 'https://' . $_SERVER['HTTP_HOST']);
define('WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST']);
define('FORCE_SSL_ADMIN', true);

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// ... rest of your wp-config.php ...
```

---

## Step-by-Step Checklist

### DNS Setup
- [ ] A Record: `www.familytraveltracker.app` → Server IP
- [ ] A Record: `my.familytraveltracker.app` → Server IP
- [ ] (Optional) A Record: `familytraveltracker.app` → Server IP
- [ ] DNS propagated (test with `nslookup` or `dig`)

### Apache Configuration
- [ ] Backed up existing configs
- [ ] Single `<VirtualHost *:443>` in bitnami-ssl.conf
- [ ] `ServerName` set to primary domain (www)
- [ ] `ServerAlias` includes my and apex domains
- [ ] HTTP to HTTPS redirect in bitnami.conf
- [ ] No conflicting VirtualHost blocks
- [ ] Configuration test passes: `sudo apachectl configtest`

### SSL Certificate
- [ ] Ran bn-cert with all three domains
- [ ] Certificate paths correct in bitnami-ssl.conf
- [ ] Certificate includes all domains (check with openssl)
- [ ] Auto-renewal configured by bn-cert

### WordPress Configuration
- [ ] wp-config.php has dynamic domain support
- [ ] No hardcoded URLs in database (or plugin handles it)
- [ ] Family Travel Tracker plugin installed and activated
- [ ] Plugin domain routing active

### Testing
- [ ] Apache restart successful
- [ ] https://www.familytraveltracker.app works
- [ ] https://my.familytraveltracker.app works
- [ ] HTTP redirects to HTTPS
- [ ] No certificate warnings
- [ ] No redirect loops
- [ ] WordPress loads correctly on both domains

---

## Quick Fix Script

If you want to start fresh, save this as `fix-apache.sh`:

```bash
#!/bin/bash
# Quick fix for Bitnami Apache dual-domain setup

echo "Backing up current configuration..."
sudo cp /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf /opt/bitnami/apache2/conf/bitnami/bitnami-ssl.conf.backup.$(date +%Y%m%d-%H%M%S)

echo "Testing Apache configuration..."
sudo /opt/bitnami/apache2/bin/apachectl configtest

if [ $? -eq 0 ]; then
    echo "✓ Configuration syntax is valid"
    echo "Restarting Apache..."
    sudo /opt/bitnami/ctlscript.sh restart apache
    echo "✓ Apache restarted"
    echo ""
    echo "Check logs:"
    echo "  sudo tail -f /opt/bitnami/apache2/logs/error_log"
else
    echo "✗ Configuration has errors. Check output above."
    exit 1
fi
```

Run it:
```bash
chmod +x fix-apache.sh
./fix-apache.sh
```

---

## Next Steps After Apache is Fixed

Once Apache is working correctly with both domains:

1. **Install Family Travel Tracker Plugin v2.0.18**
   - Upload the plugin zip
   - Activate it
   - The plugin will automatically handle domain routing

2. **Configure WordPress**
   - Keep wp-config.php dynamic domain settings
   - Let the plugin handle page routing

3. **Test Everything**
   - Sign up flow
   - Login from both domains
   - Dashboard access
   - Domain switching

---

## Need More Help?

If you're still stuck, provide:
1. Output of: `sudo apachectl configtest`
2. Output of: `sudo /opt/bitnami/apache2/bin/httpd -S`
3. Contents of: `bitnami-ssl.conf` VirtualHost block
4. Browser error message or behavior description

The most common issue is having **multiple VirtualHost blocks** when you should have **one block with ServerAlias**.
