# Stripe PHP Library Setup

The Family Travel Tracker billing system requires the Stripe PHP library.

## Installation Options

### Option 1: Composer (Recommended)

```bash
cd /workspaces/phantom-regiment-tracker
composer require stripe/stripe-php
```

This will install the library to `vendor/stripe/stripe-php/`.

### Option 2: Manual Download

1. Download from: https://github.com/stripe/stripe-php/releases
2. Extract to: `lib/stripe-php/`
3. Ensure `init.php` exists at: `lib/stripe-php/init.php`

### Option 3: Package Build

The library will be automatically included when creating a release package using `build-package.sh`.

## Verification

After installation, the plugin will automatically load Stripe classes if the library is detected at:
- `vendor/stripe/stripe-php/init.php` (Composer)
- `lib/stripe-php/init.php` (Manual)

Check PHP error logs for any loading issues.

## Configuration

Once installed, configure Stripe credentials in:
**WordPress Admin → Settings → Stripe Billing**

Required settings:
- Test/Live API keys
- Webhook signing secret
- Product/Price IDs

## Documentation

Full billing implementation guide: `STRIPE_BILLING_IMPLEMENTATION.md`
