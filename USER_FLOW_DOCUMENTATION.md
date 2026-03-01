# Complete User Flow Documentation
## Family Travel Tracker: Registration → Stripe → Dashboard

**Last Updated:** March 1, 2026  
**Plugin Version:** 2.0.23

---

## 🎯 Overview

This document explains the complete user journey from initial registration through Stripe billing setup to accessing the personalized dashboard. Understanding this flow is critical for debugging, customization, and feature development.

**Quick Summary:**
1. User visits pricing page (or homepage with "Start Free Trial")
2. User registers account (if new) or logs in (if existing)
3. User is redirected to Stripe Checkout for billing setup
4. Stripe processes payment method (14-day trial, no charge yet)
5. Stripe webhooks update WordPress subscription data
6. User is redirected back to success page
7. User accesses their personalized dashboard

---

## 📊 Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    1. HOMEPAGE / LANDING                         │
│  homepage.md or familytraveltracker.app                         │
│                                                                  │
│  [Start 14-Day Free Trial] button                               │
└─────────────────┬───────────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────────────────┐
│                    2. PRICING PAGE                               │
│  URL: /pricing/                                                  │
│  Template: templates/billing/pricing.php                         │
│  Shortcode: [ftt_pricing_page]                                  │
│                                                                  │
│  • Monthly vs Yearly toggle                                      │
│  • Base: $9.99/mo or $99/yr (1 child)                          │
│  • Add-ons: +$5/mo or +$50/yr per additional child             │
│  • Quantity selector (0-20 additional children)                 │
│                                                                  │
│  IF not logged in:                                              │
│    Button: "Sign Up Free" → /ftt-register/                     │
│  IF logged in:                                                  │
│    Button: "Start Free Trial" → Call REST API                  │
└─────────────────┬───────────────────────────────────────────────┘
                  ↓
         ┌────────┴────────┐
         │                 │
    NOT LOGGED IN     ALREADY LOGGED IN
         │                 │
         ↓                 ↓
┌─────────────────┐  ┌─────────────────────────────────────────────┐
│ 3A. REGISTER    │  │ 3B. DIRECT TO CHECKOUT                       │
│ /ftt-register/  │  │ JavaScript AJAX Call                         │
│                 │  │                                              │
│ Form Fields:    │  │ REST API Endpoint:                           │
│ • First Name    │  │   POST /wp-json/ftt/v1/create-checkout       │
│ • Last Name     │  │                                              │
│ • Email         │  │ Request Body:                                │
│ • Phone         │  │   {                                          │
│ • Password      │  │     "interval": "month",  // or "year"      │
│ • User Type:    │  │     "addon_quantity": 2   // 0-20           │
│   - Child       │  │   }                                          │
│   - Parent      │  │                                              │
│                 │  │ PHP Handler:                                 │
│ Handler:        │  │   includes/rest.php::create_checkout_session │
│ includes/       │  │                                              │
│ registration.   │  │ ↓ Calls FTT_Stripe_Integration               │
│    php          │  │                                              │
│                 │  │                                              │
│ On Submit:      │  │                                              │
│ • Validate data │  │                                              │
│ • Create WP user│  │                                              │
│ • Auto-login    │  │                                              │
│ • Redirect to   │  │                                              │
│   pricing page  │  │                                              │
└─────┬───────────┘  └─────┬───────────────────────────────────────┘
      │                    │
      └────────┬───────────┘
               ↓
┌─────────────────────────────────────────────────────────────────┐
│         4. CREATE STRIPE CHECKOUT SESSION                        │
│  File: includes/stripe/class-stripe-integration.php              │
│  Method: FTT_Stripe_Integration::create_checkout_session()      │
│                                                                  │
│  Steps:                                                          │
│  1. Get or create Stripe customer for WordPress user            │
│     • Check user_meta: ftt_stripe_customer_id                   │
│     • If not exists: Stripe\Customer::create()                  │
│     • Store customer_id in user meta                            │
│                                                                  │
│  2. Retrieve price IDs from settings                            │
│     Options: ftt_stripe_settings                                │
│     • price_base_monthly = $9.99/mo                             │
│     • price_base_yearly = $99/yr                                │
│     • price_addon_monthly = $5/mo per child                     │
│     • price_addon_yearly = $50/yr per child                     │
│                                                                  │
│  3. Build line items array                                      │
│     Base item (always quantity 1):                              │
│       ['price' => 'price_base_monthly', 'quantity' => 1]       │
│                                                                  │
│     IF addon_quantity > 0:                                      │
│       ['price' => 'price_addon_monthly', 'quantity' => 2]      │
│                                                                  │
│  4. Create Stripe Checkout Session                              │
│     Stripe\Checkout\Session::create([                           │
│       'customer' => $customer_id,                               │
│       'mode' => 'subscription',                                 │
│       'line_items' => $line_items,                              │
│       'subscription_data' => [                                  │
│         'trial_period_days' => 14,                              │
│         'metadata' => [                                         │
│           'wordpress_user_id' => $user_id,                      │
│           'interval' => 'month',                                │
│           'addon_quantity' => 2                                 │
│         ]                                                       │
│       ],                                                        │
│       'success_url' => '/checkout-success/?session_id={...}',  │
│       'cancel_url' => '/checkout-cancel/'                      │
│     ])                                                          │
│                                                                  │
│  5. Store pending session ID in user meta                       │
│     update_user_meta($user_id, 'ftt_pending_checkout_session')│
│                                                                  │
│  6. Return session data to JavaScript                           │
│     {                                                            │
│       "session_id": "cs_test_abc123...",                        │
│       "url": "https://checkout.stripe.com/c/pay/cs_test..."    │
│     }                                                            │
└─────────────────────────────┬───────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│              5. REDIRECT TO STRIPE CHECKOUT                      │
│  window.location.href = session.url;                            │
│                                                                  │
│  User is now on Stripe's hosted checkout page:                  │
│  https://checkout.stripe.com/c/pay/cs_test_abc123...           │
│                                                                  │
│  Stripe Checkout Form:                                          │
│  • Email (pre-filled from customer)                             │
│  • Card Number                                                   │
│  • Expiration Date                                               │
│  • CVC                                                           │
│  • Billing Zip Code                                              │
│                                                                  │
│  Summary Display:                                                │
│  ┌────────────────────────────────────────────┐                 │
│  │ Family Travel Tracker                      │                 │
│  │ Base Subscription            $9.99/month   │                 │
│  │ Additional Children (×2)    $10.00/month   │                 │
│  │                            ────────────     │                 │
│  │ Total today                      $0.00     │                 │
│  │ Starting Mar 15, 2026          $19.99/mo   │                 │
│  │                                             │                 │
│  │ 14-day free trial • Cancel anytime         │                 │
│  └────────────────────────────────────────────┘                 │
│                                                                  │
│  User Actions:                                                   │
│  • [Pay] → Process and redirect to success_url                 │
│  • [Back] or close → Redirect to cancel_url                    │
└─────────────────────┬───────────────────────────────────────────┘
                      ↓
              ┌───────┴───────┐
              │               │
         SUCCESSFUL      USER CANCELED
              │               │
              ↓               ↓
┌─────────────────────┐  ┌──────────────────────────────────────┐
│                     │  │ 6B. CHECKOUT CANCELED                 │
│ 6A. STRIPE          │  │ URL: /checkout-cancel/                │
│     PROCESSES       │  │ Template: billing/checkout-cancel.php │
│     PAYMENT         │  │                                       │
│                     │  │ Message:                              │
│ • Validates card    │  │ "Checkout was canceled"               │
│ • Creates           │  │ [Return to Pricing]                   │
│   subscription      │  │                                       │
│   object            │  │ User can try again                    │
│ • Starts 14-day     │  └───────────────────────────────────────┘
│   trial             │
│ • NO CHARGE YET     │
│ • Subscription      │
│   status:           │
│   "trialing"        │
│                     │
│ Stripe sends        │
│ webhooks to         │
│ WordPress           │
└──────┬──────────────┘
       ↓
┌─────────────────────────────────────────────────────────────────┐
│           7. STRIPE WEBHOOKS (Background Process)                │
│  Endpoint: /wp-json/ftt/v1/stripe-webhook                       │
│  Handler: includes/stripe/class-stripe-webhooks.php             │
│                                                                  │
│  Webhook Event 1: checkout.session.completed                    │
│  ─────────────────────────────────────────────────────────      │
│  Triggered immediately when user completes Stripe checkout      │
│                                                                  │
│  Payload:                                                        │
│    session.id = "cs_test_abc123..."                            │
│    session.customer = "cus_ABC123XYZ"                          │
│    session.subscription = "sub_DEF456UVW"                      │
│    session.metadata.wordpress_user_id = 42                     │
│                                                                  │
│  Handler: handle_checkout_completed()                           │
│    • Store customer ID: ftt_stripe_customer_id                 │
│    • Delete pending session meta                                │
│    • Fire action: do_action('ftt_checkout_completed')          │
│                                                                  │
│  Webhook Event 2: customer.subscription.created                 │
│  ─────────────────────────────────────────────────────────      │
│  Triggered after checkout when subscription is created          │
│                                                                  │
│  Payload:                                                        │
│    subscription.id = "sub_DEF456UVW"                           │
│    subscription.customer = "cus_ABC123XYZ"                     │
│    subscription.status = "trialing"                            │
│    subscription.trial_start = 1709293200 (Mar 1, 2026)        │
│    subscription.trial_end = 1710502800 (Mar 15, 2026)         │
│    subscription.current_period_start = 1709293200              │
│    subscription.current_period_end = 1711885200                │
│    subscription.items.data[0]:                                 │
│      - price.id = "price_base_monthly"                         │
│      - quantity = 1                                             │
│    subscription.items.data[1]:                                 │
│      - price.id = "price_addon_monthly"                        │
│      - quantity = 2                                             │
│    subscription.metadata.wordpress_user_id = 42               │
│                                                                  │
│  Handler: handle_subscription_created()                         │
│    • Find WordPress user by customer ID or metadata            │
│    • Parse line items to count addons                           │
│    • Calculate total price: $9.99 + (2 × $5) = $19.99        │
│    • Update user meta keys:                                     │
│                                                                  │
│      update_user_meta($user_id, 'ftt_stripe_subscription_id',  │
│                       'sub_DEF456UVW');                        │
│      update_user_meta($user_id, 'ftt_subscription_status',     │
│                       'trialing');                              │
│      update_user_meta($user_id, 'ftt_subscription_interval',   │
│                       'month');                                 │
│      update_user_meta($user_id, 'ftt_base_price', '9.99');    │
│      update_user_meta($user_id, 'ftt_addon_quantity', 2);     │
│      update_user_meta($user_id, 'ftt_subscription_price',      │
│                       '19.99');                                 │
│      update_user_meta($user_id, 'ftt_trial_start',            │
│                       '2026-03-01 10:00:00');                  │
│      update_user_meta($user_id, 'ftt_trial_end',              │
│                       '2026-03-15 10:00:00');                  │
│      update_user_meta($user_id, 'ftt_subscription_start',     │
│                       '2026-03-15 10:00:00');                  │
│      update_user_meta($user_id, 'ftt_current_period_start',   │
│                       '2026-03-01 10:00:00');                  │
│      update_user_meta($user_id, 'ftt_current_period_end',     │
│                       '2026-03-31 10:00:00');                  │
│      update_user_meta($user_id, 'ftt_cancel_at_period_end',   │
│                       false);                                   │
│                                                                  │
│    • Send welcome email: send_trial_start_email($user_id)      │
│    • Fire action: do_action('ftt_subscription_created')        │
│                                                                  │
│  These webhooks run in BACKGROUND while user is being          │
│  redirected. Usually complete within 1-3 seconds.              │
└─────────────────────────────────────────────────────────────────┘
       ↓
┌─────────────────────────────────────────────────────────────────┐
│                8. CHECKOUT SUCCESS PAGE                          │
│  URL: /checkout-success/?session_id=cs_test_abc123...          │
│  Template: templates/billing/checkout-success.php               │
│  Shortcode: [ftt_checkout_success]                             │
│                                                                  │
│  Display:                                                        │
│  ┌─────────────────────────────────────────┐                   │
│  │      ✓                                  │                   │
│  │                                         │                   │
│  │  Welcome to Family Travel Tracker!     │                   │
│  │                                         │                   │
│  │  Your free trial has started.          │                   │
│  │  You now have full access to all       │                   │
│  │  features.                              │                   │
│  │                                         │                   │
│  │  Get Started:                           │                   │
│  │  → Add your first child to the system  │                   │
│  │  → Create events on your calendar      │                   │
│  │  → Invite co-parents if needed         │                   │
│  │  → Set up calendar sync on your phone  │                   │
│  │                                         │                   │
│  │  [Go to Dashboard]  [View Subscription]│                   │
│  └─────────────────────────────────────────┘                   │
│                                                                  │
│  Note: At this point, webhooks may still be processing.        │
│  User meta should be updated within seconds.                   │
└─────────────────┬───────────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────────────────┐
│                  9. PERSONALIZED DASHBOARD                       │
│  URL: /ftt-dashboard/                                           │
│  Template: templates/dashboard.php                              │
│  Shortcode: [ftt_dashboard]                                     │
│                                                                  │
│  Dashboard loads and checks:                                     │
│  • is_user_logged_in() → TRUE                                   │
│  • FTT_Roles::is_parent($user_id) → FALSE (new user)           │
│  • FTT_Roles::is_member($user_id) → FALSE (new user)           │
│                                                                  │
│  Subscription Status Check:                                      │
│  $status = get_user_meta($user_id,                             │
│            'ftt_subscription_status', true);                    │
│  → Returns: "trialing"                                          │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ Navigation Banner (Purple gradient)                  │      │
│  │ ✈️ Family Dashboard                                  │      │
│  │ [Calendar] [My Events] [Logout]                     │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ Welcome Banner                                        │      │
│  │ Welcome Back, John Smith!                            │      │
│  │ Trial: 14 days remaining                             │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ Quick Actions                                         │      │
│  │ [+ Add Child]  [Create Event]  [Invite Co-Parent]   │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ Children (Empty State)                                │      │
│  │ No children added yet.                                │      │
│  │ [Add Your First Child]                               │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ Upcoming Events (Empty State)                         │      │
│  │ No events scheduled yet.                              │      │
│  │ [Create First Event]                                 │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
│  ┌──────────────────────────────────────────────────────┐      │
│  │ Subscription Info                                     │      │
│  │ Status: Free Trial (14 days left)                    │      │
│  │ Plan: Base + 2 Additional Children                   │      │
│  │ Starting: $19.99/month on Mar 15, 2026              │      │
│  │ [Manage Subscription]                                │      │
│  └──────────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔑 Key Components

### 1. Registration System
**File:** `includes/registration.php`  
**Class:** `FTT_Registration`

**Core Functions:**
- `registration_shortcode()` - Renders registration form
- `handle_registration()` - Processes form submission
  - Validates email, password (8+ chars), names
  - Creates WordPress user with `wp_create_user()`
  - Auto-logs in user with `wp_set_auth_cookie()`
  - Assigns subscriber role
  - Can designate as Parent or Member (Child)
  - Links parent to child via invite code or email

### 2. Pricing Page
**File:** `templates/billing/pricing.php`  
**Shortcode:** `[ftt_pricing_page]`

**Features:**
- Toggle between Monthly/Yearly billing
- Base subscription: $9.99/mo or $99/yr (1 child)
- Add-on selector: $5/mo or $50/yr per additional child
- Quantity controls (0-20 additional children)
- JavaScript calculates totals in real-time
- Calls REST API when "Start Free Trial" clicked

### 3. REST API Endpoints
**File:** `includes/rest.php`  
**Class:** `FTT_REST`

**Key Endpoints:**

#### `POST /wp-json/ftt/v1/create-checkout`
- **Auth:** Requires logged-in user
- **Request Body:**
  ```json
  {
    "interval": "month",
    "addon_quantity": 2
  }
  ```
- **Response:**
  ```json
  {
    "session_id": "cs_test_abc123...",
    "url": "https://checkout.stripe.com/..."
  }
  ```

#### `POST /wp-json/ftt/v1/register`
- **Auth:** Public (no login required)
- **Purpose:** Combined registration + checkout
- **Process:**
  1. Create WordPress user
  2. Log them in
  3. Create Stripe checkout session
  4. Return checkout URL

### 4. Stripe Integration
**File:** `includes/stripe/class-stripe-integration.php`  
**Class:** `FTT_Stripe_Integration`

**Core Methods:**

#### `get_or_create_customer($user_id)`
- Checks user_meta for existing Stripe customer ID
- If not found, creates new Stripe customer
- Stores customer ID in `ftt_stripe_customer_id`

#### `create_checkout_session($user_id, $interval, $addon_quantity)`
- Retrieves price IDs from settings
- Builds line items (base + addons)
- Creates Stripe Checkout Session with:
  - 14-day trial period
  - Success/cancel URLs
  - User metadata
- Returns session ID and checkout URL

### 5. Webhook Handler
**File:** `includes/stripe/class-stripe-webhooks.php`  
**Class:** `FTT_Stripe_Webhooks`

**Endpoint:** `/wp-json/ftt/v1/stripe-webhook`  
**Signature Verification:** Uses webhook secret from settings

**Handled Events:**

#### `checkout.session.completed`
- Fired when user completes Stripe checkout
- Stores customer ID in user meta
- Cleans up pending session data

#### `customer.subscription.created`
- Fired when subscription is created (after checkout)
- **Most important webhook** - Sets up all subscription data
- Parses subscription object for:
  - Status (trialing, active, etc.)
  - Billing interval (month/year)
  - Addon count
  - Trial dates
  - Current period dates
- Updates 11+ user meta keys
- Sends welcome email

#### `customer.subscription.updated`
- Fired when subscription changes
- Updates subscription status
- Recalculates pricing if addons changed

#### `invoice.payment_succeeded`
- Fired when trial ends and first payment succeeds
- Updates status to "active"

#### `invoice.payment_failed`
- Fired if payment fails
- Sets status to "past_due"
- Starts 7-day grace period

### 6. Dashboard
**File:** `templates/dashboard.php`  
**Shortcode:** `[ftt_dashboard]`

**Access Control:**
- Checks `is_user_logged_in()`
- Checks subscription status via user meta
- Redirects to pricing if no subscription

**Displays:**
- Welcome banner with user name
- Trial countdown if in trial
- Children list (if any)
- Upcoming events (if any)
- Quick action buttons
- Subscription status card

**Role-Based Views:**
- **Parent:** See all their children's events
- **Member (Child):** See only their own events
- **Admin:** See everything, manage all users
- **New User:** Empty states with CTAs to add data

---

## 📝 User Meta Keys Reference

After successful subscription, these user meta keys are populated:

| Meta Key | Example Value | Description |
|----------|---------------|-------------|
| `ftt_stripe_customer_id` | `cus_ABC123XYZ` | Stripe customer ID |
| `ftt_stripe_subscription_id` | `sub_DEF456UVW` | Stripe subscription ID |
| `ftt_subscription_status` | `trialing` | Status: trialing, active, past_due, canceled |
| `ftt_subscription_interval` | `month` | Billing interval: month or year |
| `ftt_base_price` | `9.99` | Base subscription price |
| `ftt_addon_quantity` | `2` | Number of additional children |
| `ftt_subscription_price` | `19.99` | Total monthly/yearly price |
| `ftt_trial_start` | `2026-03-01 10:00:00` | When trial started |
| `ftt_trial_end` | `2026-03-15 10:00:00` | When trial ends (14 days) |
| `ftt_subscription_start` | `2026-03-15 10:00:00` | When billing starts |
| `ftt_current_period_start` | `2026-03-01 10:00:00` | Current billing period start |
| `ftt_current_period_end` | `2026-03-31 10:00:00` | Current billing period end |
| `ftt_cancel_at_period_end` | `false` | Will cancel at end of period? |

---

## 🎨 Stripe Product Configuration

These products/prices must be created in Stripe Dashboard:

### Products

#### 1. Base Subscription
- **Name:** Family Travel Tracker - Base Subscription
- **Description:** Track events and travel for your first child
- **Prices:**
  - `price_base_monthly`: $9.99/month recurring
  - `price_base_yearly`: $99.00/year recurring

#### 2. Additional Child
- **Name:** Family Travel Tracker - Additional Child
- **Description:** Add another child to your account
- **Type:** Metered (quantity-based)
- **Prices:**
  - `price_addon_monthly`: $5.00/month recurring
  - `price_addon_yearly`: $50.00/year recurring

### WordPress Settings

These price IDs must be configured in:  
**Admin → Events → Settings → Stripe**

```php
'ftt_stripe_settings' => [
    'mode' => 'live',  // or 'test'
    'test_publishable_key' => 'pk_test_...',
    'test_secret_key' => 'sk_test_...',
    'live_publishable_key' => 'pk_live_...',
    'live_secret_key' => 'sk_live_...',
    'webhook_secret' => 'whsec_...',
    
    // Price IDs from Stripe
    'price_base_monthly' => 'price_xxx',
    'price_base_yearly' => 'price_yyy',
    'price_addon_monthly' => 'price_zzz',
    'price_addon_yearly' => 'price_aaa',
    
    'trial_days' => 14,
    'grace_period_days' => 7,
]
```

---

## 🔐 Security Considerations

### Webhook Verification
- All webhooks require valid Stripe signature
- Signature verified using webhook secret
- Invalid signatures return 400 error

### Access Control
- Registration endpoint is public
- Checkout creation requires login
- Dashboard requires active subscription
- Admins bypass all restrictions

### CSRF Protection
- All REST API calls require WP nonce
- Form submissions use `wp_nonce_field()`
- Nonces verified on server side

---

## 🐛 Common Issues & Troubleshooting

### Issue: "Webhooks not updating user meta"

**Symptoms:**
- User completes checkout
- Sees success page
- Dashboard shows "no subscription" or redirects to pricing

**Causes:**
1. Webhooks not configured in Stripe
2. Webhook secret incorrect
3. WordPress REST API blocked
4. User ID not in metadata

**Debug:**
```php
// Check webhook logs
get_option('ftt_webhook_logs');

// Check user meta manually
$user_id = 42;
print_r(get_user_meta($user_id));

// Test webhook endpoint
curl -X POST https://familytraveltracker.app/wp-json/ftt/v1/stripe-webhook \
  -H "stripe-signature: test"
```

**Fix:**
1. Go to Stripe Dashboard → Developers → Webhooks
2. Add endpoint: `https://familytraveltracker.app/wp-json/ftt/v1/stripe-webhook`
3. Select events: `checkout.session.completed`, `customer.subscription.*`, `invoice.*`
4. Copy signing secret → WordPress Settings
5. Test webhook from Stripe Dashboard

### Issue: "Checkout session creation fails"

**Symptoms:**
- JavaScript error on pricing page
- Button disabled after click
- No redirect to Stripe

**Debug:**
```javascript
// Check browser console for error
// Look for 500 error from REST API

// Check PHP error log
tail -f /var/log/php_errors.log

// Test REST API manually
curl -X POST https://familytraveltracker.app/wp-json/ftt/v1/create-checkout \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{"interval":"month","addon_quantity":0}'
```

**Common Fixes:**
1. Verify Stripe API keys are set correctly
2. Check price IDs exist in Stripe
3. Ensure user is logged in
4. Verify REST API is enabled

### Issue: "User stuck on checkout success page"

**Symptoms:**
- User sees success message
- Dashboard still shows empty/no subscription
- Subscription appears in Stripe but not WordPress

**Cause:** Webhook processed but user meta not saved

**Fix:**
```php
// Manually sync subscription from Stripe
$user_id = 42;
$subscription_id = 'sub_DEF456UVW'; // Get from Stripe

\Stripe\Stripe::setApiKey('sk_live_...');
$subscription = \Stripe\Subscription::retrieve($subscription_id);

// Manually call webhook handler
FTT_Stripe_Webhooks::handle_subscription_created($subscription);
```

---

## 📚 Related Documentation

- [STRIPE_BILLING_IMPLEMENTATION.md](STRIPE_BILLING_IMPLEMENTATION.md) - Technical implementation details
- [ONBOARDING_ROADMAP.md](ONBOARDING_ROADMAP.md) - Planned onboarding flow (Phase 2)
- [STRIPE_SETUP.md](STRIPE_SETUP.md) - Stripe account configuration
- [STRIPE_CONFIGURATION_GUIDE.md](STRIPE_CONFIGURATION_GUIDE.md) - WordPress settings

---

## 🔄 Sequence Diagram (Alternative View)

```
User             WordPress          Stripe API        Stripe Webhooks
 │                    │                  │                    │
 │ Visit Pricing Page │                  │                    │
 ├───────────────────>│                  │                    │
 │                    │                  │                    │
 │  Register/Login    │                  │                    │
 ├───────────────────>│                  │                    │
 │                    │                  │                    │
 │ Click "Start Trial"│                  │                    │
 ├───────────────────>│                  │                    │
 │                    │                  │                    │
 │                    │ Create Customer  │                    │
 │                    ├─────────────────>│                    │
 │                    │<─────────────────┤                    │
 │                    │  customer_id     │                    │
 │                    │                  │                    │
 │                    │ Create Checkout  │                    │
 │                    │ Session          │                    │
 │                    ├─────────────────>│                    │
 │                    │<─────────────────┤                    │
 │                    │  session_url     │                    │
 │                    │                  │                    │
 │  Redirect to Stripe│                  │                    │
 │<───────────────────┤                  │                    │
 │                    │                  │                    │
 │ Enter Card Info    │                  │                    │
 ├────────────────────────────────────────>                   │
 │                    │                  │                    │
 │                    │                  │ checkout.session   │
 │                    │                  │  .completed        │
 │                    │<───────────────────────────────────────┤
 │                    │ (Store customer_id)                   │
 │                    │                  │                    │
 │                    │                  │ subscription       │
 │                    │                  │  .created          │
 │                    │<───────────────────────────────────────┤
 │                    │ (Store ALL subscription data)         │
 │                    │                  │                    │
 │ Redirect to Success│                  │                    │
 │<───────────────────┤                  │                    │
 │                    │                  │                    │
 │ View Dashboard     │                  │                    │
 ├───────────────────>│                  │                    │
 │<───────────────────┤                  │                    │
 │  (Subscription     │                  │                    │
 │   data loaded      │                  │                    │
 │   from user_meta)  │                  │                    │
```

---

**End of Documentation**

For questions or updates, contact the development team or refer to inline code comments in the files listed above.
