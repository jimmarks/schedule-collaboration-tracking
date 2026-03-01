# Stripe Configuration Guide

## Overview
This guide walks you through configuring Stripe integration for Family Travel Tracker. You'll need to:
1. Configure API keys in WordPress admin
2. Create products in Stripe
3. Set up webhooks
4. Test the integration

---

## Step 1: Configure API Keys

### 1.1 Access the Settings Page
1. Log in to your WordPress admin panel
2. Go to **Events → Billing** in the left sidebar
3. You'll see a new "Billing Settings" page with 4 tabs

### 1.2 Add Your API Keys
1. Stay on the **Configuration** tab
2. Keep **Operating Mode** set to "Test Mode" for now
3. Go to [Stripe Dashboard - Test API Keys](https://dashboard.stripe.com/test/apikeys)
4. Copy the **Publishable key** (starts with `pk_test_`) → Paste into "Test Publishable Key"
5. Copy the **Secret key** (starts with `sk_test_`) → Paste into "Test Secret Key"
6. Click **Test Connection** button to verify it works

### 1.3 Add Live Keys (Later)
> ⚠️ **Do NOT switch to live mode until testing is complete!**

When ready for production:
1. Go to [Stripe Dashboard - Live API Keys](https://dashboard.stripe.com/apikeys)
2. Copy your live keys into the "Live Mode API Keys" section
3. Save settings

---

## Step 2: Create Products & Prices in Stripe

### 2.1 Create Base Subscription Product
1. Go to [Stripe Dashboard - Products](https://dashboard.stripe.com/test/products)
2. Click **"+ Add product"**
3. Fill in:
   - **Name**: `Base Subscription`
   - **Description**: `Family Travel Tracker base subscription plan`
   - **Pricing model**: `Standard pricing`

4. **Add Monthly Price**:
   - Click **"Add price"**
   - **Price**: `$9.99`
   - **Billing period**: `Monthly`
   - Mark as **Recurring**
   - Click **"Add price"**

5. **Add Yearly Price**:
   - Click **"Add another price"**
   - **Price**: `$99.00`
   - **Billing period**: `Yearly`
   - Mark as **Recurring**
   - Click **"Add price"**

### 2.2 Create Additional Child Product
1. Still in Products, click **"+ Add product"** again
2. Fill in:
   - **Name**: `Additional Child`
   - **Description**: `Add-on subscription for additional children`
   - **Pricing model**: `Standard pricing`

3. **Add Monthly Price**:
   - **Price**: `$5.00`
   - **Billing period**: `Monthly`
   - Mark as **Recurring**

4. **Add Yearly Price**:
   - **Price**: `$50.00`
   - **Billing period**: `Yearly`
   - Mark as **Recurring**

### 2.3 Copy Price IDs
1. Go back to [Products](https://dashboard.stripe.com/test/products)
2. Click on **"Base Subscription"**
3. You'll see two prices listed. Click on the **Monthly** price:
   - Copy the Price ID (starts with `price_`)
   - Go to WordPress → **Events → Billing → Pricing tab**
   - Paste into **"Monthly ($9.99/mo)"** field
4. Repeat for the **Yearly** price
5. Repeat for both **Additional Child** prices

**OR** use the auto-fetch button:
- Click **"Auto-Fetch Prices"** in the Pricing tab
- It will automatically populate all 4 price IDs if products match exactly
- Click **"Save Changes"**

---

## Step 3: Configure Webhooks

### 3.1 Get Your Webhook URL
1. In WordPress, go to **Events → Billing → Webhooks tab**
2. You'll see your webhook URL displayed. Example:
   ```
   https://yourdomain.com/wp-json/ftt/v1/stripe-webhook
   ```
3. Click **Copy** to copy the URL

### 3.2 Create Webhook in Stripe
1. Go to [Stripe Dashboard - Webhooks](https://dashboard.stripe.com/test/webhooks)
2. Click **"+ Add endpoint"**
3. Paste your webhook URL
4. Click **"Select events"**
5. Add these events:
   - `checkout.session.completed`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `customer.subscription.trial_will_end`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
6. Click **"Add endpoint"**

### 3.3 Get Signing Secret
1. After creating the endpoint, click on it to open details
2. In the **"Signing secret"** section, click **"Reveal"**
3. Copy the signing secret (starts with `whsec_`)
4. Go back to WordPress → **Events → Billing → Webhooks tab**
5. Paste into **"Webhook Signing Secret"** field
6. Click **"Save Changes"**

---

## Step 4: Configure Advanced Settings

1. Go to **Events → Billing → Advanced tab**
2. Configure:
   - **Free Trial Period**: 14 days (default)
   - **Payment Failure Grace Period**: 7 days (default)
   - **Max Parents per Child**: 4 (default)
3. Review the color palette for calendar differentiation
4. Click **"Save Changes"**

---

## Step 5: Test the Integration

### 5.1 Test Stripe Connection
1. **Configuration tab** → Click **"Test Connection"**
2. Should show: ✓ Connected! with your account name

### 5.2 Test Checkout Flow
1. Log out of WordPress admin
2. Go to your pricing page: `/pricing/` (or wherever you embedded `[ftt_pricing_page]`)
3. Click **"Start Free Trial"** on Monthly plan
4. You'll be redirected to Stripe Checkout
5. Use test card: `4242 4242 4242 4242`
   - Expiry: Any future date
   - CVC: Any 3 digits
   - ZIP: Any 5 digits
6. Complete checkout
7. You should be redirected to success page
8. Check WordPress admin → Users → Your account → should see subscription

### 5.3 Test Webhook Reception
1. Go to [Stripe Webhooks](https://dashboard.stripe.com/test/webhooks)
2. Click on your endpoint
3. Check the **"Events"** tab
4. Should see recent events with ✓ green checkmarks
5. If you see ❌ red X, click to view error details

### 5.4 Test Add Child Add-on
1. Log in as test user
2. Go to `/manage-subscription/`
3. Click **"Add Child Slot"**
4. Should create a new subscription item
5. Verify in Stripe Dashboard → Subscriptions

### 5.5 Test Cancellation
1. On manage subscription page, click **"Cancel Subscription"**
2. Confirm cancellation
3. Should see status change to "Active (Cancels on [date])"
4. Test reactivation: Click **"Reactivate Subscription"**

---

## Step 6: Go Live (When Ready)

### 6.1 Create Live Products
1. Switch Stripe dashboard to **Live mode** (toggle in top-right)
2. Repeat Step 2 to create products in live mode
3. Copy live price IDs

### 6.2 Update WordPress Settings
1. Go to **Events → Billing → Configuration**
2. Add your **Live Mode API Keys**
3. Go to **Pricing tab**, update with live price IDs
4. Go to **Webhooks tab**, create new webhook in Stripe (live mode)
5. Copy live webhook signing secret
6. Change **Operating Mode** to "Live Mode"
7. **Save Changes** - confirm the warning dialog

### 6.3 Final Checks
- Test connection with live keys
- Verify webhook endpoint works
- Test with real card (small amount)
- Verify emails are being sent

---

## Troubleshooting

### Connection Test Fails
- **Check API keys**: Make sure they match test/live mode
- **Check key format**: `pk_test_` or `sk_test_` for test mode
- **Check permissions**: Keys need read/write access

### Webhooks Not Working
- **Check URL**: Must be publicly accessible (not localhost)
- **Check signing secret**: Must match webhook endpoint in Stripe
- **Check events**: Ensure all 7 events are selected
- **Check logs**: Stripe shows webhook delivery attempts with error details

### Checkout Redirects to 404
- **Check pages exist**: Go to Pages → verify "Checkout Success" and "Checkout Cancel" exist
- **Flush permalinks**: Settings → Permalinks → Save Changes

### Price IDs Not Working
- **Check format**: Should start with `price_` not `prod_`
- **Check mode**: Test prices won't work in live mode (and vice versa)
- **Check currency**: Must be USD
- **Check billing interval**: Must be `month` or `year` (not `week` or `day`)

### Trial Not Starting
- **Check trial_days**: Advanced tab → should be 14
- **Check Stripe product**: Trial period can be set in Stripe OR at checkout
- Our implementation sets it at checkout time

### Grace Period Not Working
- **Check cron**: Run `wp cron event list` to verify `ftt_check_grace_period_expiry` exists
- **Check cron execution**: Trigger manually: `wp cron event run ftt_check_grace_period_expiry`

---

## Next Steps After Configuration

1. **Test thoroughly** in test mode
2. **Customize email notifications** (optional - see templates/billing/)
3. **Update pricing page content** (add your branding, FAQ, etc.)
4. **Add billing links to navigation** (Pricing, Manage Subscription)
5. **Enable user registration** if not already enabled
6. **Go live** when ready

---

## Important Notes

- **Always test in test mode first** - Never skip testing
- **Keep secret keys secret** - Never commit to git, never share
- **Use webhook signing** - Already implemented for security
- **Monitor failed payments** - Set up Stripe email alerts
- **Plan for disputes** - Have a refund policy ready
- **GDPR compliance** - Stripe is GDPR compliant, but you need a privacy policy

---

## Support Resources

- **Stripe Documentation**: https://stripe.com/docs
- **Stripe API Reference**: https://stripe.com/docs/api
- **Stripe Testing**: https://stripe.com/docs/testing
- **Stripe Support**: https://support.stripe.com/

---

## Summary Checklist

- [ ] Test API keys configured
- [ ] Live API keys configured (when ready)
- [ ] 2 products created (Base + Add-on)
- [ ] 4 prices created (2 monthly, 2 yearly)
- [ ] Price IDs copied to WordPress
- [ ] Webhook endpoint created
- [ ] 7 webhook events selected
- [ ] Webhook signing secret added
- [ ] Connection test passed
- [ ] Checkout flow tested
- [ ] Webhooks receiving events
- [ ] Add-on tested
- [ ] Cancellation tested
- [ ] Ready to go live

Once all boxes are checked, you're ready for production! 🚀
