# Family Travel Tracker v2.0.14 - Quick Setup Summary

## What's New: Single WordPress, Dual-Domain Magic ✨

**One WordPress. Two domains. Automatic routing.**

---

## The Setup

### Architecture:
```
Single WordPress Installation
├── Database: One MySQL database
├── Users: Shared across both domains
├── Theme: Astra (same branding)
├── Plugin: Family Travel Tracker v2.0.14
│
├── www.familytraveltracker.app (marketing)
│   ├── Homepage
│   ├── Features
│   ├── Pricing
│   ├── Sign Up
│   └── About/Support
│
└── my.familytraveltracker.app (app)
    ├── Dashboard (/ftt-dashboard/)
    ├── Calendar (/ftt-calendar/)
    ├── Events (/ftt-events/)
    └── Billing (/ftt-billing/)
```

---

## Quick Start (5 Steps)

### 1. DNS Setup
```
A Record: www.familytraveltracker.app → Your Server IP
A Record: my.familytraveltracker.app  → Your Server IP (same)
```

### 2. WordPress Configuration

Add to `wp-config.php`:
```php
define('WP_HOME', 'https://' . $_SERVER['HTTP_HOST']);
define('WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST']);
```

### 3. Install Plugin

Upload `schedule-collaboration-tracking-v2.0.14.zip` and activate.

### 4. Create Content

**Marketing pages** (you create):
- Home, Features, Pricing, About, Sign Up

**App pages** (auto-created):
- Dashboard, Calendar, Events, Billing

### 5. Configure Menus

- **Marketing Menu**: Home, Features, Pricing, About, Sign Up, Login
- **App Menu**: Dashboard, Calendar, Events, Billing, Logout

Plugin auto-switches menus based on domain!

---

## How It Works

### User visits www:
```
www.familytraveltracker.app
↓
Marketing homepage loads
↓
Marketing navigation shows
↓
User clicks "Sign Up"
```

### User signs up:
```
Registration form (on www)
↓
Account created in WordPress
↓
Stripe checkout opens
↓
Payment succeeds
↓
Redirect to my.familytraveltracker.app/ftt-dashboard/
↓
App navigation shows
```

### User returns:
```
User types www.familytraveltracker.app
↓
Already logged in (shared session!)
↓
Marketing pages load
↓
Clicks "Dashboard"
↓
Auto-redirects to my.familytraveltracker.app/ftt-dashboard/
```

---

## Key Features

### ✅ Automatic Routing
- Marketing pages → www domain
- App pages → my domain
- Wrong domain? → Auto-redirect

### ✅ Shared Users
- Register once
- Login works on both domains
- Same account, same data

### ✅ Menu Switching
- On www → Marketing menu
- On my → App menu
- Switches automatically!

### ✅ No Config Needed
- Plugin detects domains automatically
- No manual URL configuration
- Just works!

---

## Files Included

### Deploy These:
- `schedule-collaboration-tracking-v2.0.14.zip` - **Main plugin**
- `apply-astra-colors.php` - **Brand colors** (run once, then delete)
- `cleanup-old-data.sql` - **DB cleanup** (if upgrading)

### Read These:
- `SINGLE_WORDPRESS_SETUP.md` - **Complete setup guide**
- `CHANGELOG.md` - **What's new**

---

## Web Server Config

### Apache:
```apache
<VirtualHost *:443>
    ServerName www.familytraveltracker.app
    ServerAlias my.familytraveltracker.app
    DocumentRoot /var/www/familytraveltracker/public_html
    # ... SSL config ...
</VirtualHost>
```

### Nginx:
```nginx
server {
    listen 443 ssl;
    server_name www.familytraveltracker.app my.familytraveltracker.app;
    root /var/www/familytraveltracker/public_html;
    # ... SSL config ...
}
```

---

## Testing Checklist

After setup:

- [ ] Visit www.familytraveltracker.app → Marketing homepage
- [ ] Visit my.familytraveltracker.app → Redirect to www (if logged out)
- [ ] Complete sign-up from www
- [ ] Redirect to my.familytraveltracker.app after payment
- [ ] Navigate to dashboard
- [ ] Log out
- [ ] Visit www again → Marketing pages
- [ ] Log in → Stay on my domain
- [ ] Try to visit my.familytraveltracker.app/ → Redirect to www

All working? ✅ You're done!

---

## Advantages

| Single WordPress | Separate WordPress |
|-----------------|-------------------|
| ✅ 1 database | ❌ 2 databases |
| ✅ Shared users | ❌ Separate users |
| ✅ 1 theme install | ❌ 2 theme installs |
| ✅ 1 backup | ❌ 2 backups |
| ✅ Update once | ❌ Update twice |
| ✅ Lower cost | ❌ Higher cost |

---

## Support

**Full documentation:** `SINGLE_WORDPRESS_SETUP.md`

**Need help?**
- Check WordPress error log
- Enable `WP_DEBUG` in `wp-config.php`
- Verify plugin version is 2.0.14+

---

## Summary

**Single WordPress + Dual Domains = Best of Both Worlds**

- Professional separation (www vs my)
- Simple maintenance (one install)
- Shared users and branding
- Automatic routing
- Lower costs

This is the **recommended setup** for production deployments.

**Ready to deploy? Start with `SINGLE_WORDPRESS_SETUP.md`!** 🚀
