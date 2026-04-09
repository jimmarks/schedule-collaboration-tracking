# ✅ Family Travel Tracker v2.0.12 - Deployment Checklist

## 🎯 Overview
This is the **complete FTT rebrand** with Stripe billing. This is a **CLEAN INSTALL** - all old SRT/SC data will be removed.

---

## ⚠️ Pre-Deployment Requirements

### Server Requirements
- [ ] PHP 8.0 or higher
- [ ] WordPress 6.0 or higher
- [ ] MySQL 5.7+ or MariaDB 10.3+
- [ ] HTTPS enabled (required for Stripe)
- [ ] Pretty permalinks enabled (required)
- [ ] 128MB+ PHP memory limit
- [ ] `curl` PHP extension enabled
- [ ] SSH/SFTP access to server

### Stripe Requirements
- [ ] Stripe account created
- [ ] Test mode keys available
- [ ] Production mode keys available
- [ ] Webhook endpoint configured
- [ ] Payment methods enabled (card)

### WordPress Configuration
- [ ] REST API enabled
- [ ] User registration allowed (Settings → General)
- [ ] Anyone can register: YES
- [ ] Default user role: Subscriber
- [ ] Timezone configured correctly

---

## 📦 Deployment Files Prepared

✅ **Plugin Package:** `download/schedule-collaboration-tracking-v2.0.12.zip` (896KB, 533 files)
✅ **SQL Cleanup:** `cleanup-old-data.sql`
✅ **Astra Colors:** `apply-astra-colors.php`
✅ **Brand Assets:** `Icon.png` (logo)

---

## 🚀 Deployment Steps

### Step 1: Backup Current Site
- [ ] **Backup database** via phpMyAdmin or hosting panel
- [ ] **Backup wp-content/plugins/** folder
- [ ] **Backup wp-content/uploads/** folder
- [ ] Store backups in safe location

### Step 2: Clean Old Data
- [ ] Download `cleanup-old-data.sql` to your computer
- [ ] Open phpMyAdmin
- [ ] Select your WordPress database
- [ ] Click "SQL" tab
- [ ] Paste contents of `cleanup-old-data.sql`
- [ ] Click "Go" to execute
- [ ] Verify: Check that `wp_posts` has no `srt_event` or `ftt_event` entries

### Step 3: Remove Old Plugin
- [ ] Go to WordPress Admin → Plugins
- [ ] **Deactivate** "Family Travel Tracker" (if active)
- [ ] **Delete** the plugin completely
- [ ] Via FTP/SSH: Verify `/wp-content/plugins/schedule-collaboration-tracking/` is gone

### Step 4: Install New Plugin
- [ ] Go to Plugins → Add New → Upload Plugin
- [ ] Choose `schedule-collaboration-tracking-v2.0.12.zip`
- [ ] Click "Install Now"
- [ ] Click "Activate Plugin"
- [ ] ✅ Success message should appear

**Automatic Actions on Activation:**
- Creates user role: "Family Member"
- Creates pages: `/ftt-dashboard/`, `/ftt-calendar/`, `/ftt-events/`, etc.
- Initializes settings
- Registers custom post type: `ftt_event`

### Step 5: Configure Stripe Settings
- [ ] Go to **FTT → Stripe Settings**
- [ ] Toggle **Test Mode: ON**
- [ ] Enter **Test Publishable Key** from Stripe dashboard
- [ ] Enter **Test Secret Key** from Stripe dashboard
- [ ] Enter **Test Price ID** for base subscription
- [ ] Enter **Test Price ID** for child add-on
- [ ] Copy **Webhook URL** shown on settings page
- [ ] Click **Save Settings**

### Step 6: Configure Stripe Webhook
- [ ] Log into Stripe Dashboard → Developers → Webhooks
- [ ] Click "Add endpoint"
- [ ] Enter webhook URL: `https://yoursite.com/wp-json/ftt/v1/stripe/webhook`
- [ ] Click "Select events"
- [ ] Add these events:
  - `customer.subscription.created`
  - `customer.subscription.updated`
  - `customer.subscription.deleted`
  - `customer.subscription.trial_will_end`
  - `invoice.payment_succeeded`
  - `invoice.payment_failed`
- [ ] Click "Add events"
- [ ] Copy **Signing secret**
- [ ] Go back to **FTT → Stripe Settings**
- [ ] Paste **Webhook Secret**
- [ ] Click **Save Settings**

### Step 7: Test Stripe Integration
- [ ] Visit `/ftt-billing/` page
- [ ] Click "Select Plan" for monthly
- [ ] Add 1 child
- [ ] Click "Subscribe Now"
- [ ] Use Stripe test card: `4242 4242 4242 4242`
- [ ] Any future expiry date
- [ ] Any CVC
- [ ] Complete checkout
- [ ] ✅ Should redirect to `/ftt-checkout-success/`
- [ ] Verify subscription in **FTT → Manage Users**
- [ ] Check Stripe Dashboard → Customers (should see test customer)

### Step 8: Apply Astra Theme Colors
- [ ] Upload `apply-astra-colors.php` to WordPress root directory (same folder as `wp-config.php`)
- [ ] Visit `https://yoursite.com/apply-astra-colors.php` (logged in as admin)
- [ ] ✅ Should show "All colors applied successfully!"
- [ ] **Delete** `apply-astra-colors.php` from server immediately
- [ ] Visit your site homepage to see brand colors

### Step 9: Verify Core Functionality
- [ ] **Dashboard:** Visit `/ftt-dashboard/` (logged in)
- [ ] **Calendar:** Visit `/ftt-calendar/` (should load)
- [ ] **Create Event:** Test creating a new event
- [ ] **Edit Event:** Test editing an event
- [ ] **Delete Event:** Test deleting an event
- [ ] **iCal Subscribe:** Test calendar subscription link
- [ ] **Logout/Login:** Test login form at `/ftt-login/`
- [ ] **Registration:** Test new user registration at `/ftt-register/`

### Step 10: Upload Logo
- [ ] Go to Appearance → Customize → Site Identity
- [ ] Click "Select Logo"
- [ ] Upload `Icon.png`
- [ ] Crop if needed
- [ ] Click "Publish"

---

## 🎨 Post-Deployment: Design Polish

### Typography (Optional)
If you want to add custom fonts:
- [ ] Choose font pairing (e.g., Poppins + Inter)
- [ ] Add fonts via Astra → Customize → Global → Typography
- [ ] Set heading font: Medium/Semibold weight
- [ ] Set body font: Regular weight

### Astra Theme Settings
- [ ] **Layout:** Full-width for billing, dashboard, calendar pages
- [ ] **Header:** Add navigation menu with Dashboard, Calendar, Events links
- [ ] **Footer:** Add copyright notice, privacy policy link
- [ ] **Colors:** Already applied via PHP script ✅
- [ ] **Buttons:** Verify coral color (#F05A5A) appears correctly

---

## 🔥 Go Live: Switch to Production

### When Ready for Real Customers

#### Switch Stripe to Live Mode
- [ ] Go to **FTT → Stripe Settings**
- [ ] Toggle **Test Mode: OFF**
- [ ] Enter **Live Publishable Key**
- [ ] Enter **Live Secret Key**
- [ ] Enter **Live Price ID** for base subscription
- [ ] Enter **Live Price ID** for child add-on
- [ ] Click **Save Settings**

#### Update Live Webhook
- [ ] Stripe Dashboard → Developers → Webhooks
- [ ] Add NEW endpoint for live mode
- [ ] URL: `https://yoursite.com/wp-json/ftt/v1/stripe/webhook`
- [ ] Add same 6 events as test mode
- [ ] Copy **Live Signing Secret**
- [ ] Update **FTT → Stripe Settings** with live webhook secret
- [ ] Click **Save Settings**

#### Final Production Checks
- [ ] Test live checkout with real card (then refund)
- [ ] Verify emails are sending (subscription confirmations)
- [ ] Test trial reminder emails (if enabled)
- [ ] Check webhook events in Stripe Dashboard
- [ ] Monitor first few real subscriptions closely

---

## 🎯 Marketing Pages to Create

### Recommended WordPress Pages
- [ ] **Home** - Landing page with value proposition
- [ ] **Pricing** - Link to `/ftt-billing/` or embed `[ftt_pricing_page]`
- [ ] **Features** - What makes your tracker awesome
- [ ] **About** - Your story, why you built this
- [ ] **Support/FAQ** - Common questions
- [ ] **Privacy Policy** - Required for payment processing
- [ ] **Terms of Service** - User agreement

---

## 📊 Post-Launch Monitoring

### Week 1 Checklist
- [ ] Monitor Stripe Dashboard daily
- [ ] Check webhook delivery (should be 100% success)
- [ ] Review subscription sign-ups
- [ ] Test user-reported issues
- [ ] Monitor server error logs
- [ ] Check email deliverability

### Ongoing Maintenance
- [ ] Weekly: Review subscriptions and churn
- [ ] Monthly: Update WordPress core, plugins, theme
- [ ] Monthly: Review Stripe fees and revenue
- [ ] Quarterly: Backup database
- [ ] As needed: Add features based on user feedback

---

## 🆘 Troubleshooting Common Issues

### "Page not found" for /ftt-dashboard/
**Fix:** Go to Settings → Permalinks → Click "Save Changes" (flushes rewrite rules)

### Stripe checkout fails
**Fix:** Verify webhook secret is correct, check FTT → Logs for errors

### Colors not applying
**Fix:** Clear browser cache, check Astra is active theme, re-run `apply-astra-colors.php`

### Emails not sending
**Fix:** Install WP Mail SMTP plugin, configure with Gmail/SendGrid

### Webhooks failing
**Fix:** Check webhook URL is publicly accessible (not localhost), verify SSL certificate is valid

---

## ✅ Deployment Complete!

**Version:** 2.0.12  
**Plugin Name:** Family Travel Tracker  
**Shortcodes:** `[ftt_calendar]`, `[ftt_dashboard]`, `[ftt_pricing_page]`, `[ftt_manage_subscription]`  
**Primary Color:** Plum (#6A3E8E)  
**Accent Color:** Coral (#F05A5A)  
**Subscription Model:** $9.99/mo + $5/child  

**Next:** Start inviting beta users and gather feedback! 🎉
