# Family Travel Tracker - Dual Domain Architecture

## 🌐 Domain Structure

### **www.familytraveltracker.app** (Marketing Site)
**Purpose:** Public-facing marketing, sign-ups, selling
**WordPress:** Standard WordPress installation (minimal plugins)
**Theme:** Astra + Custom landing pages

**Pages:**
- `/` - Homepage (features, benefits, social proof)
- `/features/` - Feature details
- `/pricing/` - Pricing information (view-only)
- `/about/` - About us
- `/support/` - FAQ/Support
- `/sign-up/` - **Registration + Billing Entry**
- `/login/` - Login form (redirects to my.ftt.app)
- `/privacy/` - Privacy policy
- `/terms/` - Terms of service

**Key Point:** NO plugin installed here. Just marketing content + sign-up form.

---

### **my.familytraveltracker.app** (Application)
**Purpose:** Authenticated user application, full functionality
**WordPress:** WordPress + Family Travel Tracker plugin
**Theme:** Astra (simplified header/footer)

**Pages:**
- `/dashboard/` - Main user dashboard
- `/calendar/` - Interactive calendar
- `/events/` - Event management
- `/settings/` - User settings
- `/billing/` - Subscription management
- `/invitations/` - Invite other adults

**Key Point:** Plugin installed here. All app functionality.

---

## 🔄 User Flows

### First-Time User (Sign Up)

1. **Visit:** `www.familytraveltracker.app/sign-up`
2. **See form with:**
   - Email address
   - Full name
   - Password
   - Pricing selector:
     - Monthly ($9.99) vs Yearly ($89.99)
     - Number of children (0-20, $5/mo or $50/yr each)
   - Total shown: "$14.99/month (14-day free trial)"
3. **Click "Start Free Trial"**
4. **JavaScript:**
   ```javascript
   // POST to my.familytraveltracker.app REST API
   POST https://my.familytraveltracker.app/wp-json/ftt/v1/register
   Body: {
     email: "user@example.com",
     name: "John Doe",
     password: "SecurePass123",
     interval: "month",
     addon_quantity: 2
   }
   ```
5. **API Response:**
   ```json
   {
     "success": true,
     "user_id": 123,
     "checkout_url": "https://checkout.stripe.com/c/pay/cs_123..."
   }
   ```
6. **Redirect to Stripe Checkout**
7. **User enters payment info** (Stripe hosted page)
8. **Payment succeeds** → Stripe redirects to:
   `https://my.familytraveltracker.app/checkout-success/`
9. **User sees:** "Welcome! Your 14-day trial has started"
10. **Automatic redirect to:** `https://my.familytraveltracker.app/dashboard/`

**Result:** 
- ✅ WordPress account created on `my.ftt.app`
- ✅ Stripe customer created
- ✅ Subscription created with 14-day trial
- ✅ User logged in and ready to use app

---

### Returning User (Login)

1. **Visit:** `www.familytraveltracker.app/login`
2. **Enter credentials**
3. **Form submits to:** `my.familytraveltracker.app/wp-login.php`
4. **Success → Redirect to:** `https://my.familytraveltracker.app/dashboard/`

**Alternative:** Login link can go directly to my.ftt.app login

---

### Invited Adult (Multi-Adult Access)

1. **Primary user** (in app) → Invitations → Enter spouse email
2. **Spouse receives email:** "John invited you to FamilyTravelTracker"
3. **Click link:** `my.familytraveltracker.app/accept-invite?token=abc123`
4. **Create password:** (email pre-filled from invitation)
5. **Submit → Account created, linked to primary's subscription**
6. **Redirect to dashboard**

---

## 🔧 Technical Implementation

### Marketing Site (www.ftt.app)

**Sign-Up Form HTML:**
```html
<form id="ftt-signup-form">
  <input type="email" name="email" required>
  <input type="text" name="name" required>
  <input type="password" name="password" required>
  
  <div class="pricing-selector">
    <label>
      <input type="radio" name="interval" value="month" checked>
      Monthly - $9.99/mo
    </label>
    <label>
      <input type="radio" name="interval" value="year">
      Yearly - $89.99/yr (Save $31!)
    </label>
  </div>
  
  <div class="addon-selector">
    <label>Number of children:</label>
    <select name="addon_quantity">
      <option value="0">Just browsing (0 children)</option>
      <option value="1">1 child - +$5/mo</option>
      <option value="2" selected>2 children - +$10/mo</option>
      <option value="3">3 children - +$15/mo</option>
      <!-- ... up to 20 -->
    </select>
  </div>
  
  <div class="total-price">
    Total: <strong>$19.99/month</strong>
    <span class="trial-notice">14-day free trial, cancel anytime</span>
  </div>
  
  <button type="submit">Start Free Trial</button>
</form>
```

**Sign-Up JavaScript:**
```javascript
jQuery('#ftt-signup-form').on('submit', function(e) {
  e.preventDefault();
  
  const formData = {
    email: $(this).find('[name="email"]').val(),
    name: $(this).find('[name="name"]').val(),
    password: $(this).find('[name="password"]').val(),
    interval: $(this).find('[name="interval"]:checked').val(),
    addon_quantity: parseInt($(this).find('[name="addon_quantity"]').val())
  };
  
  // Call app domain API
  $.ajax({
    url: 'https://my.familytraveltracker.app/wp-json/ftt/v1/register',
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify(formData),
    success: function(response) {
      // Redirect to Stripe Checkout
      window.location.href = response.checkout_url;
    },
    error: function(xhr) {
      alert('Sign up failed: ' + xhr.responseJSON.message);
    }
  });
});
```

---

### Application Site (my.ftt.app)

**New REST Endpoint:** `/wp-json/ftt/v1/register`

**Handler in includes/rest.php:**
```php
public static function register_new_user($request) {
    $params = $request->get_json_params();
    
    // Validate
    $email = sanitize_email($params['email']);
    $name = sanitize_text_field($params['name']);
    $password = $params['password']; // Don't sanitize passwords
    $interval = $params['interval']; // 'month' or 'year'
    $addon_quantity = (int) $params['addon_quantity'];
    
    // Check if user already exists
    if (email_exists($email)) {
        return new WP_Error('email_exists', 'An account with this email already exists.', ['status' => 400]);
    }
    
    // Create WordPress user
    $user_id = wp_create_user($email, $password, $email);
    if (is_wp_error($user_id)) {
        return new WP_Error('registration_failed', $user_id->get_error_message(), ['status' => 500]);
    }
    
    // Set user name
    wp_update_user([
        'ID' => $user_id,
        'display_name' => $name,
        'first_name' => $name,
    ]);
    
    // Set role
    $user = new WP_User($user_id);
    $user->set_role('subscriber');
    
    // Log user in
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);
    
    // Create Stripe checkout session
    $session = FTT_Stripe_Integration::create_checkout_session($user_id, $interval, $addon_quantity);
    
    if (!$session) {
        // Roll back user creation
        wp_delete_user($user_id);
        return new WP_Error('checkout_failed', 'Failed to create checkout session', ['status' => 500]);
    }
    
    return rest_ensure_response([
        'success' => true,
        'user_id' => $user_id,
        'checkout_url' => $session['url'],
    ]);
}
```

**Register the route:**
```php
register_rest_route('ftt/v1', '/register', [
    'methods' => 'POST',
    'callback' => [__CLASS__, 'register_new_user'],
    'permission_callback' => '__return_true', // Public endpoint
]);
```

---

### Stripe Checkout Configuration

**Update create_checkout_session() in class-stripe-integration.php:**

```php
public static function create_checkout_session($user_id, $interval = 'month', $addon_quantity = 0) {
    // ... existing code ...
    
    $session = \Stripe\Checkout\Session::create([
        'customer' => $customer_id,
        'mode' => 'subscription',
        'line_items' => $line_items,
        'success_url' => 'https://my.familytraveltracker.app/checkout-success/?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'https://my.familytraveltracker.app/checkout-cancel/',
        'subscription_data' => [
            'trial_period_days' => $trial_days,
            'metadata' => [
                'wordpress_user_id' => $user_id,
            ],
        ],
    ]);
    
    return [
        'url' => $session->url,
        'session_id' => $session->id,
    ];
}
```

---

## 🔐 CORS Configuration

Since www.ftt.app (marketing) is calling API on my.ftt.app (app), you need CORS headers.

**Add to my.ftt.app functions.php or plugin:**

```php
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        header('Access-Control-Allow-Origin: https://www.familytraveltracker.app');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        return $value;
    });
}, 15);
```

---

## 📧 Email Configuration

**Welcome Email** (sent after trial starts):
- **Trigger:** Webhook `customer.subscription.created` with trial
- **Subject:** "Welcome to Family Travel Tracker!"
- **Body:**
  ```
  Hi {name},
  
  Your 14-day free trial has started! 🎉
  
  Access your dashboard: https://my.familytraveltracker.app/dashboard/
  
  Email: {email}
  Password: (the one you created)
  
  Need help? Reply to this email!
  ```

**Trial Ending Soon** (sent 3 days before trial ends):
- **Trigger:** Webhook `customer.subscription.trial_will_end`
- **Subject:** "Your trial ends in 3 days"

---

## 🎨 Branding Consistency

Both domains should have:
- Same logo
- Same color scheme (Plum + Coral)
- Same fonts
- Different nav menus:
  - **www:** Home, Features, Pricing, About, Support, Login/Sign Up
  - **my:** Dashboard, Calendar, Events, Settings, Billing, Logout

---

## 🚀 Deployment Checklist

### Marketing Site (www.ftt.app)
- [ ] Install WordPress
- [ ] Install Astra theme
- [ ] Apply brand colors
- [ ] Create pages: Home, Features, Pricing, About, Support, Sign-up, Login
- [ ] Add sign-up form with JavaScript
- [ ] Test sign-up flow to app domain

### App Site (my.ftt.app)
- [ ] Install WordPress
- [ ] Install Family Travel Tracker plugin v2.0.12+
- [ ] Configure Stripe settings
- [ ] Add CORS headers for www.ftt.app
- [ ] Test REST API endpoint `/register`
- [ ] Configure email sending (WP Mail SMTP)
- [ ] Test complete flow: sign-up → checkout → trial

### DNS Configuration
- [ ] www.familytraveltracker.app → Marketing server IP
- [ ] my.familytraveltracker.app → App server IP
- [ ] SSL certificates for both domains

### Testing
- [ ] Sign up as new user from www
- [ ] Complete Stripe checkout
- [ ] Verify account created on my
- [ ] Verify trial started (14 days)
- [ ] Login from www → redirects to my
- [ ] Test invitation flow
- [ ] Test subscription management

---

## 💡 Future Enhancements

1. **Single Sign-On (SSO)** between domains
2. **OAuth login** (Google, Facebook)
3. **Mobile app** pointing to my.ftt.app API
4. **White-label** option for schools/organizations
5. **Referral program** on marketing site

---

## 🆘 Troubleshooting

**CORS errors:**
- Check Access-Control-Allow-Origin header
- Ensure credentials: true in both AJAX and server

**Checkout not creating account:**
- Check webhook secret is configured
- Verify webhook is receiving events in Stripe Dashboard

**User can't access app after payment:**
- Check subscription status in user meta
- Verify trial period is set correctly
- Check redirect URLs in Stripe settings
