# Registration Flow Testing Guide

## Changes Made

### 1. **Fixed Pricing Page Bug**
- [templates/billing/pricing.php](templates/billing/pricing.php)
- Lines 91 & 145: Changed `wp_registration_url()` → `home_url('/ftt-register/')`
- **Result**: "Sign Up Free" button now goes to custom registration (not wp-login)

### 2. **Enhanced Registration Form**
- [templates/registration-form.php](templates/registration-form.php)
- **Removed**: User type toggle, member fields, invite codes, phone field
- **Added**: Child quantity selector (1-6), live pricing preview, trial notice
- **Simplified**: Parent-only registration focused on starting trials
- **Styled**: Modern Astra theme colors (Purple #6A3E8E, Coral #F05A5A)
- **Redirect**: Changed from `/` to `/pricing/` after registration

### 3. **Updated Registration Handler**
- [includes/registration.php](includes/registration.php)
- Fixed button name check: `srt_register_submit` → `ftt_register_submit`
- Added `planned_children` capture and user meta storage
- Made phone field optional (removed from form)

---

## Complete Registration Flow Test

### Step 1: Homepage → Pricing
1. Go to homepage at `/`
2. Click any "Start Free Trial" or "Get Started" button
3. ✅ **Verify**: You land on `/pricing/` page
4. ✅ **Verify**: You see pricing cards with trial details

### Step 2: Pricing → Registration
1. On pricing page, ensure you're **logged out**
2. Click **"Sign Up Free"** button
3. ✅ **Verify**: You go to `/ftt-register/` (NOT wp-login.php)
4. ✅ **Verify**: Form shows modern design with:
   - "Start Your Free Trial" header
   - Personal info fields (first, last, email, passwords)
   - Child quantity dropdown (1-6)
   - Live pricing preview box
   - Trial notice banner

### Step 3: Test Pricing Calculator
1. In registration form, change child count dropdown
2. Select **3 children**
3. ✅ **Verify**: Pricing updates to show:
   - Base subscription (1 child): $9.99/mo
   - 2 additional children × $5: $10.00/mo
   - Monthly Total: $19.99/mo
4. Select **1 child**
5. ✅ **Verify**: Pricing shows:
   - Base subscription (1 child): $9.99/mo
   - Additional children: $0.00/mo
   - Monthly Total: $9.99/mo

### Step 4: Complete Registration
1. Fill out the form:
   - First Name: Test
   - Last Name: Parent
   - Email: testparent@example.com (use unique email)
   - Password: TestPassword123!
   - Confirm Password: TestPassword123!
   - Child Count: 3
   - Check: "I agree to receive email notifications"
2. Click **"Create Account & Continue"**
3. ✅ **Verify**: Form submits successfully
4. ✅ **Verify**: You're auto-logged in
5. ✅ **Verify**: You're redirected to `/pricing/` page

### Step 5: Pricing Page (Logged In)
1. Now that you're logged in, check pricing page
2. ✅ **Verify**: Buttons now say **"Start Free Trial"** (not "Sign Up Free")
3. ✅ **Verify**: Buttons have `onclick="createCheckout('monthly')"`
4. Select child count: **3 children** (to match registration)
5. Click **"Start Free Trial"** on Monthly plan

### Step 6: Stripe Checkout
1. ✅ **Verify**: REST API creates checkout session
2. ✅ **Verify**: You're redirected to Stripe Checkout
3. ✅ **Verify**: Checkout shows:
   - Family Travel Tracker Subscription: $9.99/mo
   - Additional Child × 2: $10.00/mo
   - Total: $19.99/mo after 14-day trial
4. ✅ **Verify**: Trial notice: "Starting Nov XX, 2024"

### Step 7: Complete Checkout (Test Mode)
1. Use Stripe test card: `4242 4242 4242 4242`
2. Expiry: Any future date (12/25)
3. CVC: Any 3 digits (123)
4. Complete checkout
5. ✅ **Verify**: Redirected to `/checkout-success/`

### Step 8: Dashboard Access
1. Go to `/ftt-dashboard/`
2. ✅ **Verify**: Dashboard shows:
   - Subscription status: Active (Trial)
   - Current plan: Monthly ($19.99/mo)
   - Trial ends: [Date]
3. ✅ **Verify**: Calendar loads
4. ✅ **Verify**: Can create events

---

## Database Verification

### Check User Meta
```sql
-- Find the new test user
SELECT ID, user_login, user_email FROM wp_users WHERE user_email = 'testparent@example.com';

-- Check user meta (replace USER_ID with actual ID)
SELECT meta_key, meta_value FROM wp_usermeta WHERE user_id = USER_ID;
```

**Expected Meta Keys:**
- `first_name`: Test
- `last_name`: Parent
- `planned_children`: 3
- `stripe_customer_id`: cus_xxx
- `stripe_subscription_id`: sub_xxx
- `subscription_status`: active
- `subscription_plan`: monthly
- `trial_end`: [timestamp]

---

## Troubleshooting

### Registration Form Not Showing
- **Check**: `/ftt-register/` page exists and is published
- **Check**: Page template uses `[ftt_register]` shortcode
- **Clear**: WordPress cache and browser cache

### Pricing Doesn't Update
- **Open**: Browser console (F12)
- **Check**: jQuery is loaded (no errors)
- **Verify**: JavaScript is executing (add `console.log` in updatePricing())

### Registration Fails
- **Check**: PHP error logs
- **Verify**: Nonce is correct
- **Test**: Direct form submission in browser network tab
- **Check**: User doesn't already exist with that email

### Redirect to Wrong Page
- **Check**: `redirect_to` hidden field value
- **Verify**: `/pricing/` page exists and is published
- **Check**: WordPress permalink settings

### Stripe Checkout Doesn't Open
- **Check**: Browser console for JavaScript errors
- **Verify**: User is logged in (check `$user_id` in pricing.php)
- **Test**: REST API endpoint: `/wp-json/ftt/v1/create-checkout`
- **Check**: Stripe API keys are correct in settings

### Wrong Child Count in Stripe
- **Issue**: Child addon quantity doesn't match registration
- **Debug**: REST API receives `addon_quantity` from JavaScript
- **Fix**: Make sure pricing.js passes correct quantity based on form selection

---

## Next Steps After Testing

1. **If all tests pass**:
   - Build new plugin version: `v2.0.24`
   - Deploy to server
   - Test on production with real Stripe account

2. **Update documentation**:
   - Add registration flow to [USER_FLOW_DOCUMENTATION.md](USER_FLOW_DOCUMENTATION.md)
   - Update [QUICK_REFERENCE.md](QUICK_REFERENCE.md) with new fields

3. **Optional enhancements**:
   - Add password strength indicator
   - Add email validation during typing
   - Add loading spinner on submit
   - Add Google Analytics tracking for conversions

---

## Files Modified

| File | Changes |
|------|---------|
| [templates/billing/pricing.php](templates/billing/pricing.php) | Fixed 2 registration links (lines 91, 145) |
| [templates/registration-form.php](templates/registration-form.php) | Complete rewrite with modern design |
| [includes/registration.php](includes/registration.php) | Fixed button check, added planned_children |

---

## Testing Checklist

- [ ] Homepage links to pricing page
- [ ] Pricing page "Sign Up Free" goes to `/ftt-register/`
- [ ] Registration form loads with modern design
- [ ] Child selector updates pricing preview
- [ ] Form validation works (password match, required fields)
- [ ] Registration creates user account
- [ ] Auto-login works after registration
- [ ] Redirect to pricing page works
- [ ] Pricing page shows "Start Free Trial" for logged-in users
- [ ] Stripe checkout creates with correct addon quantity
- [ ] Trial period applies correctly
- [ ] Checkout success redirects properly
- [ ] Dashboard shows subscription info
- [ ] User meta contains `planned_children` value

---

**Last Updated**: Session ending - registration enhancement complete
**Status**: Ready for testing
