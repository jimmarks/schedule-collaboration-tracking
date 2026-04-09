# Single WordPress Dual-Domain Setup Guide

## Overview

Family Travel Tracker v2.0.14+ supports running both your marketing site (www) and app site (my) from a **single WordPress installation** with **automatic domain-based routing**.

This approach provides:
- ✅ **One database** - Shared users, consistent branding
- ✅ **One WordPress install** - Easier updates and maintenance
- ✅ **Shared authentication** - Users register once, access both domains
- ✅ **Automatic routing** - Pages appear on correct domain automatically
- ✅ **Lower costs** - Single hosting plan
- ✅ **Consistent branding** - Same theme, same colors

---

## How It Works

### User Experience:
1. User visits **www.familytraveltracker.app** → Sees marketing pages
2. User clicks "Sign Up" → Still on www domain
3. User completes registration → Redirected to **my.familytraveltracker.app/ftt-dashboard/**
4. Different navigation appears → App menu (Dashboard, Calendar, etc.)
5. User logs out, returns to www → Marketing menu reappears

### Technical Implementation:
- Single WordPress installation
- Single database
- Two DNS records pointing to same server
- Domain-based page routing (handled automatically by plugin)
- Conditional navigation menus

---

## Directory Structure

```
/var/www/familytraveltracker/
└── public_html/                    # Single WordPress root
    ├── wp-config.php
    ├── wp-content/
    │   ├── plugins/
    │   │   └── schedule-collaboration-tracking/  # Plugin installed here
    │   └── themes/
    │       └── astra/              # Theme for both domains
    ├── index.php
    └── ...
```

---

## Server Setup

### Step 1: DNS Configuration

Both domains point to the **same IP address**:

```
A Record: www.familytraveltracker.app  → 1.2.3.4
A Record: my.familytraveltracker.app   → 1.2.3.4
```

### Step 2: Web Server Configuration

**Apache VirtualHost:**

```apache
<VirtualHost *:443>
    ServerName www.familytraveltracker.app
    ServerAlias my.familytraveltracker.app
    DocumentRoot /var/www/familytraveltracker/public_html
    
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/familytraveltracker.app/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/familytraveltracker.app/privkey.pem
    
    <Directory /var/www/familytraveltracker/public_html>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx:**

```nginx
server {
    listen 443 ssl http2;
    server_name www.familytraveltracker.app my.familytraveltracker.app;
    root /var/www/familytraveltracker/public_html;
    
    ssl_certificate /etc/letsencrypt/live/familytraveltracker.app/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/familytraveltracker.app/privkey.pem;
    
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
    
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### Step 3: SSL Certificate (Let's Encrypt)

Get a wildcard certificate or list both subdomains:

```bash
sudo certbot certonly --webroot \
  -w /var/www/familytraveltracker/public_html \
  -d www.familytraveltracker.app \
  -d my.familytraveltracker.app
```

Or use wildcard:
```bash
sudo certbot certonly --dns-cloudflare \
  -d familytraveltracker.app \
  -d *.familytraveltracker.app
```

### Step 4: WordPress Configuration

In `wp-config.php`, add **before** `require_once(ABSPATH . 'wp-settings.php');`:

```php
// Support multiple domains
define('WP_HOME', 'https://' . $_SERVER['HTTP_HOST']);
define('WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST']);

// Optional: Force HTTPS
if ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
    $_SERVER['HTTPS'] = 'on';
}
```

---

## WordPress Setup

### Step 1: Install WordPress

1. Upload WordPress files to `/var/www/familytraveltracker/public_html/`
2. Create MySQL database
3. Run WordPress installer using **either domain** (www or my)
4. Complete basic WordPress setup

### Step 2: Install Theme & Plugin

1. **Install Astra Theme:**
   - Go to Appearance → Themes → Add New
   - Search for "Astra"
   - Install and activate

2. **Apply Brand Colors:**
   - Upload [apply-astra-colors.php](apply-astra-colors.php) to WordPress root
   - Visit `https://www.familytraveltracker.app/apply-astra-colors.php`
   - Colors automatically applied
   - Delete the file immediately

3. **Install Family Travel Tracker Plugin:**
   - Go to Plugins → Add New → Upload Plugin
   - Upload `schedule-collaboration-tracking-v2.0.14.zip`
   - Activate plugin

### Step 3: Configure Stripe

1. Go to **FTT → Stripe Settings**
2. Configure API keys (test mode first)
3. Set price IDs
4. Leave "App Domain" **blank** (plugin will auto-detect from domain routing)
5. Save settings

### Step 4: Create Pages

The plugin auto-creates these pages on activation:

**App Pages (appear on my.familytraveltracker.app):**
- `/ftt-dashboard/` - User dashboard
- `/ftt-calendar/` - Interactive calendar
- `/ftt-events/` - Event list
- `/ftt-billing/` - Manage subscription
- `/ftt-checkout-success/` - Post-checkout success
- `/ftt-checkout-cancel/` - Checkout canceled

**Marketing Pages (create these manually on www):**
1. Create Page: "Home"
   - Slug: `/` (set as homepage in Settings → Reading)
   - Content: Marketing content, hero section, features overview

2. Create Page: "Features"
   - Slug: `/features/`
   - Content: Detailed feature list

3. Create Page: "Pricing"
   - Slug: `/pricing/`
   - Content: Pricing information

4. Create Page: "Sign Up"
   - Slug: `/sign-up/`
   - Content: Paste HTML from [templates/signup-page.html](templates/signup-page.html)
   - Template: Full Width

5. Create Page: "About"
   - Slug: `/about/`
   - Content: About your service

6. Create Page: "Support"
   - Slug: `/support/`
   - Content: FAQ, contact form

### Step 5: Configure Navigation Menus

1. Go to **Appearance → Menus**

2. **Create Marketing Menu:**
   - Name: "Marketing Primary"
   - Add pages: Home, Features, Pricing, About, Support
   - Add custom link: "Sign Up" → `/sign-up/`
   - Add custom link: "Log In" → `https://my.familytraveltracker.app/wp-login.php`
   - Assign to: "Marketing Primary Menu" location

3. **Create App Menu:**
   - Name: "App Primary"
   - Add pages: Dashboard, Calendar, Events, Billing (use /ftt-* pages)
   - Assign to: "App Primary Menu" location

The plugin will automatically show the correct menu based on which domain the user is visiting.

---

## How Domain Routing Works

### Automatic Redirects

The plugin handles these redirects automatically:

| Current URL | User on www | User on my |
|------------|-------------|-----------|
| `/` (homepage) | ✅ Show marketing homepage | 🔄 Redirect to www |
| `/ftt-dashboard/` | 🔄 Redirect to my (login if needed) | ✅ Show dashboard |
| `/ftt-calendar/` | 🔄 Redirect to my | ✅ Show calendar |
| `/wp-admin/` | 🔄 Redirect to my | ✅ Show admin |

### Domain Detection Functions

Available in theme/custom code:

```php
// Check which domain user is on
if (FTT_Domain_Routing::is_marketing_domain()) {
    // User is on www.familytraveltracker.app
    echo 'Marketing site';
}

if (FTT_Domain_Routing::is_app_domain()) {
    // User is on my.familytraveltracker.app
    echo 'App site';
}

// Get URLs
$marketing_url = FTT_Domain_Routing::get_marketing_url();  // https://www...
$app_url = FTT_Domain_Routing::get_app_url();              // https://my...

// Create cross-domain links
$link = FTT_Domain_Routing::get_cross_domain_url('/ftt-dashboard/', 'app');
```

### Body Classes

The plugin adds body classes for domain-specific styling:

```css
/* Styles that only apply on marketing domain */
.ftt-marketing-domain .header {
    /* Marketing-specific header styles */
}

/* Styles that only apply on app domain */
.ftt-app-domain .header {
    /* App-specific header styles */
}
```

---

## User Flows

### New User Sign-Up

1. User visits `www.familytraveltracker.app`
2. Clicks "Sign Up" or `/sign-up/` page
3. Enters email, name, password, selects plan
4. Clicks "Start Free Trial"
5. Form posts to `/wp-json/ftt/v1/register` (same domain!)
6. WordPress account created
7. User auto-logged in
8. Stripe checkout session created
9. User redirected to Stripe-hosted checkout
10. After payment, redirected to `my.familytraveltracker.app/ftt-checkout-success/`
11. User now on app domain, sees app navigation
12. Click "Go to Dashboard" → `my.familytraveltracker.app/ftt-dashboard/`

### Returning User Login

1. User visits `www.familytraveltracker.app`
2. Clicks "Log In" → Redirected to `my.familytraveltracker.app/wp-login.php`
3. Enters credentials
4. After login → `my.familytraveltracker.app/ftt-dashboard/`
5. User sees app navigation menu

### Accessing Marketing Pages While Logged In

1. User logged in, on `my.familytraveltracker.app/ftt-dashboard/`
2. Types `www.familytraveltracker.app` in browser
3. Sees marketing homepage (still logged in!)
4. Navigation shows marketing menu
5. Can browse features, pricing, etc.
6. Clicks "Dashboard" → Redirected back to `my.`

---

## Customization

### Custom Page Routing

Add custom pages to routing in `functions.php`:

```php
add_filter('ftt_marketing_pages', function($pages) {
    $pages[] = '/blog/';
    $pages[] = '/testimonials/';
    return $pages;
});

add_filter('ftt_app_pages', function($pages) {
    $pages[] = '/ftt-settings/';
    $pages[] = '/ftt-invitations/';
    return $pages;
});
```

### Hide Elements Based on Domain

In your theme templates:

```php
<?php if (FTT_Domain_Routing::is_marketing_domain()): ?>
    <!-- Only show on marketing domain -->
    <div class="hero-section">
        <h1>Welcome to Family Travel Tracker!</h1>
    </div>
<?php endif; ?>

<?php if (FTT_Domain_Routing::is_app_domain()): ?>
    <!-- Only show on app domain -->
    <div class="user-welcome">
        <p>Welcome back, <?php echo wp_get_current_user()->display_name; ?>!</p>
    </div>
<?php endif; ?>
```

---

## Testing

### Test Checklist

**Domain Routing:**
- [ ] Visit `www.familytraveltracker.app` → See marketing homepage
- [ ] Visit `my.familytraveltracker.app` → Redirect to www (if not logged in)
- [ ] Login → Automatically stay on my domain
- [ ] Visit `www.familytraveltracker.app/ftt-dashboard/` → Redirect to my
- [ ] Visit `my.familytraveltracker.app/` → Redirect to www

**Sign-Up Flow:**
- [ ] Complete sign-up form on www domain
- [ ] Redirected to Stripe checkout
- [ ] Complete payment with test card `4242424242424242`
- [ ] Redirected to `my.familytraveltracker.app/ftt-checkout-success/`
- [ ] Account created and logged in
- [ ] Can access dashboard, calendar, events

**Navigation:**
- [ ] On www: See marketing menu (Home, Features, Pricing, Sign Up, Login)
- [ ] On my: See app menu (Dashboard, Calendar, Events, Billing, Logout)
- [ ] Menu switches automatically when changing domains

**Admin Access:**
- [ ] Visit `www.familytraveltracker.app/wp-admin/` → Redirect to my domain
- [ ] Admin bar hidden on www for non-admins
- [ ] Admin bar visible on my for admins

---

##Troubleshooting

### "Too many redirects" Error

**Cause:** Redirect loop between domains

**Fix:**
1. Check `wp-config.php` has correct domain handling code
2. Verify both DNS records point to same server
3. Clear browser cache and cookies
4. Check `.htaccess` doesn't have conflicting redirects

### Wrong domain in emails

**Fix:** Set proper `WP_HOME` and `WP_SITEURL` in `wp-config.php` as shown above

### Can't access wp-admin

**Fix:** Go directly to `my.familytraveltracker.app/wp-admin/`

### Pages showing on wrong domain

**Fix:** Check page slugs match the routing rules in `FTT_Domain_Routing::handle_domain_redirects()`

---

## Production Deployment Checklist

- [ ] Both DNS records created and propagated
- [ ] SSL certificates installed for both subdomains
- [ ] WordPress installed with correct `wp-config.php` settings
- [ ] Astra theme installed and colors applied
- [ ] Family Travel Tracker plugin v2.0.14+ installed and activated
- [ ] Stripe configured (live mode keys)
- [ ] All marketing pages created
- [ ] Both navigation menus created and assigned
- [ ] Sign-up page tested end-to-end
- [ ] Login tested from both domains
- [ ] Domain redirects tested
- [ ] Admin access tested
- [ ] Test subscription completed successfully

---

## Advantages Over Separate WordPress Installs

| Feature | Single WordPress | Two Separate WordPress |
|---------|-----------------|----------------------|
| **User accounts** | Shared (register once) | Separate (register twice?) |
| **Theme/branding** | Always consistent | Must update both |
| **Plugin updates** | Update once | Update on both sites |
| **Database backups** | One backup | Two backups needed |
| **Hosting cost** | One server | Two servers or larger plan |
| **SSL certificates** | One cert (wildcard or multi-domain) | Two certs (can use same wildcard) |
| **WordPress updates** | Update once | Update twice |
| **Content consistency** | Automatic | Must sync manually |
| **Login state** | Shared across domains | Separate sessions |

---

## Migration from Dual WordPress Setup

If you previously had separate WordPress installations:

1. **Backup both sites**
2. **Export marketing site content** (pages, posts)
3. **Keep app site as primary** (has plugin and user accounts)
4. **Import marketing content** into app site
5. **Update DNS** to point both domains to single WordPress
6. **Configure domain routing** (plugin handles automatically)
7. **Test thoroughly**
8. **Decommission old marketing WordPress**

---

## Support

For issues with domain routing:
- Check WordPress error log
- Enable `WP_DEBUG` in `wp-config.php`
- Check plugin version is 2.0.14 or higher
- Verify `FTT_Domain_Routing` class is initialized

---

## Summary

**Single WordPress, Dual-Domain Architecture** gives you:
- ✅ Professional separation (www vs my)
- ✅ Simple maintenance (one install)
- ✅ Shared user accounts
- ✅ Automatic routing
- ✅ Lower costs
- ✅ Consistent branding

This is the **recommended setup** for Family Travel Tracker SaaS deployments.
