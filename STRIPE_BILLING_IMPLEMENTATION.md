# Stripe Billing Implementation - Family Travel Tracker

**Document Version:** 2.0  
**Date:** February 26, 2026  
**Status:** Planning / Pre-Implementation  
**Last Updated:** Pricing model simplified to pure add-on model

---

## 📋 Executive Summary

This document outlines the complete Stripe billing integration for Family Travel Tracker, transforming it from a free plugin into a subscription-based SaaS product.

**Key Decisions:**
- **Simple per-child add-on pricing** (no complex family plan tiers)
- **14-day free trial** with upfront payment collection
- **Soft limits** with upgrade prompts
- **7-day grace period** for failed payments
- **Up to 4 parents/guardians** per child account
- **Color-coded calendar** for multi-child families

---

## 💰 Pricing Structure (SIMPLIFIED)

### **Pure Add-On Model**

**Base Price:** $9.99/month ($99/year) - Includes 1 child  
**Each Additional Child:** +$5/month (+$50/year)

| Children | Monthly Price | Yearly Price | Savings vs Monthly |
|----------|--------------|--------------|-------------------|
| 1 | $9.99 | $99.00 | $20.88/yr |
| 2 | $14.99 | $149.00 | $30.88/yr |
| 3 | $19.99 | $199.00 | $40.88/yr |
| 4 | $24.99 | $249.00 | $50.88/yr |
| 5 | $29.99 | $299.00 | $60.88/yr |
| 6 | $34.99 | $349.00 | $70.88/yr |
| 7 | $39.99 | $399.00 | $80.88/yr |

**Parents/Guardians:** Up to 4 per child (included at all levels)

### **Why This Pricing Works**

✅ **Transparent:** Simple math - everyone understands $5 per child  
✅ **Fair:** Pay only for what you use  
✅ **Scales linearly:** No confusing tier jumps  
✅ **No forced upgrades:** 2-child families don't need "family plan"  
✅ **Flexible:** Works for 1 child or 10+ children  
✅ **Better for customers:** $14.99 for 2 kids vs old $24.99 "family plan"  
✅ **Better margins:** Each child costs minimal server resources  

### **Comparison to Old Model**

| Scenario | Old Pricing | New Pricing | Customer Saves |
|----------|-------------|-------------|----------------|
| 2 children | $24.99 (forced family) | $14.99 | **$10/mo!** |
| 3 children | $24.99 (family) | $19.99 | $5/mo |
| 4 children | $24.99 (family) | $24.99 | Same |
| 5 children | $29.99 (family + addon) | $29.99 | Same |

---

## 🎫 Trial & Payment Model

### **14-Day Free Trial**
- ✅ Payment method required at signup
- ✅ Full access to all features during trial
- ✅ First charge occurs on day 15
- ✅ Can cancel anytime during trial with no charge
- ✅ Email reminders at: Day 7, Day 12, Day 14

### **Why Collect Payment Upfront?**
1. **Reduces fraud:** Requires valid payment method
2. **Higher conversion:** Users who enter card info are serious
3. **Standard practice:** Netflix, Spotify, etc. all use this model
4. **Smoother UX:** No interruption at end of trial

### **Cancelation Policy**
```
During Trial (Days 1-14):
  → Cancel anytime, no charge
  → Immediate access removal
  → Confirmation email sent

After Trial (Day 15+):
  → Cancel anytime
  → Access continues until period ends
  → No refunds (industry standard)
```

---

## 🏗️ Stripe Product Setup

### **Products to Create in Stripe Dashboard**

#### **Product 1: Base Subscription (First Child)**
```
Name: Family Travel Tracker - Base Subscription
Description: Track events and travel for your first child

Prices:
  - price_base_monthly: $9.99/month recurring
  - price_base_yearly: $99.00/year recurring

Trial: 14 days
Billing Cycle Anchor: On trial end
```

#### **Product 2: Additional Child (Metered)**
```
Name: Family Travel Tracker - Additional Child
Description: Add another child to your account ($5/month each)

Prices:
  - price_addon_monthly: $5.00/month recurring (metered by quantity)
  - price_addon_yearly: $50.00/year recurring (metered by quantity)

Note: This is quantity-based. If user has 3 children total:
  → Base: $9.99/mo (first child)
  → Add-on: $5/mo × 2 (additional children) = $10/mo
  → Total: $19.99/mo
  
Stripe handles the quantity automatically when we update subscription.
```

### **How Stripe Handles This**

```php
// User starts with 1 child
$subscription = \Stripe\Subscription::create([
    'customer' => $customer_id,
    'items' => [
        ['price' => 'price_base_monthly', 'quantity' => 1], // Always 1
    ],
    'trial_period_days' => 14,
]);

// User adds 2nd child → Add addon line item
\Stripe\Subscription::update($subscription_id, [
    'items' => [
        ['price' => 'price_base_monthly', 'quantity' => 1],
        ['price' => 'price_addon_monthly', 'quantity' => 1], // 1 addon
    ],
    'proration_behavior' => 'always_invoice',
]);

// User adds 3rd child → Increase addon quantity
\Stripe\Subscription::update($subscription_id, [
    'items' => [
        ['price' => 'price_base_monthly', 'quantity' => 1],
        ['price' => 'price_addon_monthly', 'quantity' => 2], // 2 addons
    ],
    'proration_behavior' => 'always_invoice',
]);

// Result:
// 1 child: $9.99
// 2 children: $9.99 + $5 = $14.99
// 3 children: $9.99 + $10 = $19.99
```

---

## 🗄️ Database Schema

### **New User Meta Keys**

```php
// Billing Information
'ftt_stripe_customer_id'       => 'cus_ABC123XYZ'           // Stripe customer ID
'ftt_stripe_subscription_id'   => 'sub_DEF456UVW'           // Current subscription ID
'ftt_subscription_status'      => 'active'                   // active, trialing, past_due, canceled, incomplete
'ftt_subscription_interval'    => 'month'                    // month or year
'ftt_subscription_price'       => '19.99'                    // Current total price
'ftt_base_price'               => '9.99'                     // Base subscription price
'ftt_addon_quantity'           => 2                          // Number of addon children

// Limits & Usage
'ftt_children_limit'           => 4                          // Max children allowed
'ftt_children_count'           => 2                          // Current children added
'ftt_parents_per_child_limit'  => 4                          // Max parents per child

// Dates
'ftt_trial_start'              => '2026-02-26 10:00:00'     // Trial started
'ftt_trial_end'                => '2026-03-12 10:00:00'     // Trial ends (14 days)
'ftt_subscription_start'       => '2026-03-12 10:00:00'     // First billing date
'ftt_current_period_start'     => '2026-03-12 10:00:00'     // Current billing period
'ftt_current_period_end'       => '2026-04-12 10:00:00'     // When next bill due
'ftt_cancel_at_period_end'     => false                      // true if user canceled

// Failed Payment Tracking
'ftt_payment_failed_date'      => '2026-03-15 08:00:00'     // When payment first failed
'ftt_grace_period_end'         => '2026-03-22 08:00:00'     // 7 days from failure
'ftt_payment_retry_count'      => 2                          // Number of retry attempts

// Notifications
'ftt_trial_reminder_sent_7'    => true                       // Reminder sent at day 7
'ftt_trial_reminder_sent_12'   => true                       // Reminder sent at day 12
'ftt_trial_reminder_sent_14'   => true                       // Reminder sent at day 14
'ftt_payment_failed_notified'  => true                       // User notified of failure
```

### **Options Table (Global Settings)**

```php
'ftt_stripe_settings' => array(
    'mode' => 'live',                                        // 'test' or 'live'
    'test_publishable_key' => 'pk_test_xxx',
    'test_secret_key' => 'sk_test_xxx',
    'live_publishable_key' => 'pk_live_xxx',
    'live_secret_key' => 'sk_live_xxx',
    'webhook_secret' => 'whsec_xxx',
    
    // Price IDs from Stripe (Simplified Model)
    'price_base_monthly' => 'price_xxx',                     // $9.99/mo base
    'price_base_yearly' => 'price_yyy',                      // $99/yr base
    'price_addon_monthly' => 'price_zzz',                    // $5/mo per child
    'price_addon_yearly' => 'price_aaa',                     // $50/yr per child
    
    // Feature flags
    'trial_days' => 14,
    'grace_period_days' => 7,
    'max_parents_per_child' => 4,
    
    // Color Palette for Children
    'child_color_palette' => array(
        '#FF6B6B',  // Red
        '#4ECDC4',  // Teal
        '#FFD93D',  // Yellow
        '#95E1D3',  // Mint
        '#A8E6CF',  // Light Green
        '#FF8B94',  // Pink
        '#C7CEEA',  // Lavender
        '#FFDAC1',  // Peach
        '#B4A7D6',  // Purple
        '#89C2D9',  // Sky Blue
    ),
);
```

### **Child-Specific Meta Keys (NEW - Color Coding)**

```php
// Per-child calendar customization
'ftt_calendar_color'           => '#FF6B6B'                  // Hex color for calendar
'ftt_calendar_color_name'      => 'red'                      // Human-readable name
'ftt_calendar_icon'            => '⚽'                        // Optional emoji/icon
'ftt_calendar_visible'         => true                       // Show/hide in parent view
```

---

## 🎨 Color Coding System (Multi-Child Calendar)

### **Problem Statement**

Parents with multiple children viewing a shared calendar need to quickly distinguish which events belong to which child. Without color coding:
- ❌ "Soccer Practice" - Whose practice?
- ❌ "Dance Recital" - Which child's recital?
- ❌ Calendar becomes confusing with 2+ children

### **Solution: Automatic Color Assignment**

Each child is automatically assigned a unique color when added to the system. Events for that child display in their assigned color across all calendar views.

### **Color Assignment Algorithm**

```php
class FTT_Child_Colors {
    
    private static $color_palette = [
        ['hex' => '#FF6B6B', 'name' => 'Red'],
        ['hex' => '#4ECDC4', 'name' => 'Teal'],
        ['hex' => '#FFD93D', 'name' => 'Yellow'],
        ['hex' => '#95E1D3', 'name' => 'Mint'],
        ['hex' => '#A8E6CF', 'name' => 'Light Green'],
        ['hex' => '#FF8B94', 'name' => 'Pink'],
        ['hex' => '#C7CEEA', 'name' => 'Lavender'],
        ['hex' => '#FFDAC1', 'name' => 'Peach'],
        ['hex' => '#B4A7D6', 'name' => 'Purple'],
        ['hex' => '#89C2D9', 'name' => 'Sky Blue'],
    ];
    
    /**
     * Assign color to new child
     */
    public static function assign_color($child_id, $parent_id) {
        // Get existing children for this parent
        $children = SRT_Roles::get_children($parent_id);
        $child_index = count($children) - 1;  // Zero-indexed
        
        // Cycle through palette if more than 10 children
        $color_index = $child_index % count(self::$color_palette);
        $color = self::$color_palette[$color_index];
        
        // Store color info
        update_user_meta($child_id, 'ftt_calendar_color', $color['hex']);
        update_user_meta($child_id, 'ftt_calendar_color_name', $color['name']);
        update_user_meta($child_id, 'ftt_calendar_visible', true);
        
        return $color;
    }
    
    /**
     * Allow parent to change child's color
     */
    public static function update_color($child_id, $new_color_hex) {
        // Validate hex color
        if (!preg_match('/^#[0-9A-F]{6}$/i', $new_color_hex)) {
            return false;
        }
        
        update_user_meta($child_id, 'ftt_calendar_color', $new_color_hex);
        return true;
    }
}
```

### **Calendar Rendering with Colors**

#### **Frontend Calendar (FullCalendar.js)**

```javascript
// When loading events for parent
$('#calendar').fullCalendar({
    events: function(start, end, timezone, callback) {
        $.ajax({
            url: '/wp-json/ftt/v1/events',
            data: {
                start: start.format(),
                end: end.format(),
                include_colors: true,  // Include child colors
            },
            success: function(events) {
                // Events now have color property
                callback(events);
            }
        });
    },
    
    // Apply colors to events
    eventRender: function(event, element) {
        element.css('background-color', event.backgroundColor);
        element.css('border-color', event.borderColor);
        
        // Add child name badge
        const badge = $('<span>')
            .addClass('child-badge')
            .text(event.childName)
            .css('background-color', event.backgroundColor);
        element.find('.fc-title').prepend(badge);
    }
});
```

#### **REST API Response**

```php
// In SRT_REST::get_events()
$events_data = array();
foreach ($events as $event) {
    $member_id = get_post_meta($event->ID, 'member_id', true);
    $child = get_userdata($member_id);
    $child_color = get_user_meta($member_id, 'ftt_calendar_color', true) ?: '#808080';
    $child_name = $child ? $child->display_name : 'Unknown';
    
    $events_data[] = array(
        'id' => $event->ID,
        'title' => $event->post_title,
        'start' => get_post_meta($event->ID, 'start_datetime', true),
        'end' => get_post_meta($event->ID, 'end_datetime', true),
        
        // Color info
        'backgroundColor' => $child_color,
        'borderColor' => $child_color,
        'textColor' => self::get_contrast_color($child_color), // White or black
        
        // Child info
        'childId' => $member_id,
        'childName' => $child_name,
        'childColor' => $child_color,
    );
}
```

### **iCal Export with Colors**

```php
// In SRT_iCal::generate_feed()
foreach ($events as $event) {
    $member_id = get_post_meta($event->ID, 'member_id', true);
    $child = get_userdata($member_id);
    $child_name = $child ? $child->display_name : '';
    $child_color = get_user_meta($member_id, 'ftt_calendar_color', true);
    
    $ical_event = "BEGIN:VEVENT\n";
    $ical_event .= "UID:" . $event->ID . "@familytraveltracker.app\n";
    
    // Prepend child name to title for clarity in external calendars
    $ical_event .= "SUMMARY:[{$child_name}] " . $event->post_title . "\n";
    
    // Color support (works in Apple Calendar, Google Calendar)
    if ($child_color) {
        $ical_event .= "COLOR:{$child_color}\n";
    }
    
    // ... rest of event properties
    $ical_event .= "END:VEVENT\n";
}
```

### **Child Filter UI**

#### **Design**

```html
<!-- Sidebar or header filter -->
<div class="ftt-child-filter">
    <h4>Show Events For:</h4>
    <div class="ftt-child-toggles">
        <!-- Auto-generated per child -->
        <label class="ftt-child-toggle">
            <input type="checkbox" 
                   data-child-id="1" 
                   checked 
                   class="ftt-filter-checkbox">
            <span class="ftt-color-dot" style="background: #FF6B6B;"></span>
            <span class="ftt-child-name">Emma</span>
            <span class="ftt-event-count">(12)</span>
        </label>
        
        <label class="ftt-child-toggle">
            <input type="checkbox" 
                   data-child-id="2" 
                   checked 
                   class="ftt-filter-checkbox">
            <span class="ftt-color-dot" style="background: #4ECDC4;"></span>
            <span class="ftt-child-name">Lily</span>
            <span class="ftt-event-count">(8)</span>
        </label>
    </div>
    
    <div class="ftt-filter-actions">
        <button class="ftt-show-all">Show All</button>
        <button class="ftt-hide-all">Hide All</button>
    </div>
</div>

<style>
.ftt-child-toggle {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    margin: 4px 0;
    cursor: pointer;
    border-radius: 4px;
    transition: background 0.2s;
}

.ftt-child-toggle:hover {
    background: #f5f5f5;
}

.ftt-color-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    margin: 0 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.ftt-child-name {
    flex: 1;
    font-weight: 500;
}

.ftt-event-count {
    color: #666;
    font-size: 0.9em;
}
</style>
```

#### **Filter Logic**

```javascript
// Toggle child visibility
$('.ftt-filter-checkbox').on('change', function() {
    const childId = $(this).data('child-id');
    const isVisible = $(this).prop('checked');
    
    // Update user preference
    $.post(ajaxurl, {
        action: 'ftt_update_child_visibility',
        child_id: childId,
        visible: isVisible
    });
    
    // Filter calendar
    $('#calendar').fullCalendar('rerenderEvents');
});

// Filter events on calendar
$('#calendar').fullCalendar({
    eventRender: function(event, element) {
        // Check if this child is filtered out
        const checkbox = $('.ftt-filter-checkbox[data-child-id="' + event.childId + '"]');
        if (!checkbox.prop('checked')) {
            return false;  // Don't render this event
        }
        return true;
    }
});
```

### **Settings: Color Customization**

Parents can optionally change assigned colors:

```html
<!-- In account settings -->
<div class="ftt-child-colors-settings">
    <h3>Child Calendar Colors</h3>
    <p>Customize how each child's events appear on your calendar.</p>
    
    <div class="ftt-color-settings-list">
        <div class="ftt-color-setting">
            <span class="ftt-child-name">Emma</span>
            <input type="color" 
                   value="#FF6B6B" 
                   data-child-id="1"
                   class="ftt-color-picker">
            <span class="ftt-color-label">Red</span>
        </div>
        
        <div class="ftt-color-setting">
            <span class="ftt-child-name">Lily</span>
            <input type="color" 
                   value="#4ECDC4" 
                   data-child-id="2"
                   class="ftt-color-picker">
            <span class="ftt-color-label">Teal</span>
        </div>
    </div>
    
    <button class="button" id="save-colors">Save Colors</button>
</div>
```

### **Visual Examples**

#### **Before Color Coding** ❌
```
March 2026
│ Mon  │ Tue  │ Wed  │ Thu  │ Fri  │
│  10  │  11  │  12  │  13  │  14  │
│      │ Soccer│Dance │Hockey│Soccer│
│      │ 3pm  │ 4pm  │ 6pm  │ 9am  │

Problem: Which child has which activity?
```

#### **After Color Coding** ✅
```
March 2026
│ Mon  │ Tue  │ Wed  │ Thu  │ Fri  │
│  10  │  11  │  12  │  13  │  14  │
│      │🔴Soccer│🟡Dance│🔴Hockey│🟡Soccer│
│      │Emma 3pm│Lily 4pm│Emma 6pm│Lily 9am│

Legend: 🔴 Emma | 🟡 Lily | ☑️ Show/Hide
```

### **Implementation Priority**

**Phase 1: Core Color System** (Week 1)
- ✅ Color assignment on child creation
- ✅ Store colors in user meta
- ✅ Update REST API with color data

**Phase 2: Calendar Integration** (Week 2)
- ✅ FullCalendar color rendering
- ✅ iCal export with colors
- ✅ Child name in event titles

**Phase 3: UI Controls** (Week 3)
- ✅ Filter sidebar
- ✅ Toggle visibility
- ✅ Show/hide all buttons

**Phase 4: Customization** (Future)
- ⏳ Color picker in settings
- ⏳ Custom icons/emojis per child
- ⏳ Export color legend

---

## 🔄 User Flow Diagrams

### **Flow 1: New User Registration**

```
1. User lands on familytraveltracker.app
   ↓
2. Clicks "Start Free Trial"
   ↓
3. Registration Form (New User)
   - Email, Password, Name
   - "I am registering as: [Child] or [Parent]"
   ↓
4. Select Plan Page
   - Base Subscription: $9.99/mo or $89.99/yr
   - Each additional child: +$5/mo or +$50/yr
   - Toggle: [Monthly] / [Yearly]
   - Shows: "14-day free trial, cancel anytime"
   - Note: "Start with 1 child, add more anytime"
   ↓
5. Redirect to Stripe Checkout (Hosted)
   - Stripe collects payment info
   - User enters card details on Stripe.com (secure!)
   - Shows trial end date prominently
   ↓
6. Stripe processes → Webhook fires
   ↓
7. WordPress receives webhook: checkout.session.completed
   - Creates/Updates user account
   - Stores stripe_customer_id and subscription_id
   - Sets status: 'trialing'
   - Records trial_end date
   ↓
8. User redirected to familytraveltracker.app/welcome
   - "Welcome! Your 14-day trial has started"
   - Dashboard tour
   - Prompt to add first child/event
```

### **Flow 2: Adding Additional Children (Simple Add-On)**

```
User has: Base subscription ($9.99/mo), 1 child added
   ↓
User clicks: "Add Child" button
   ↓
Show Confirmation Prompt:
  ╔══════════════════════════════════════════════════╗
  ║  ➕ Add Another Child                            ║
  ║                                                  ║
  ║  Current: $9.99/mo for 1 child                  ║
  ║  Adding: +$5/mo for 2nd child                   ║
  ║                                                  ║
  ║  New Total: $14.99/mo                           ║
  ║                                                  ║
  ║  ✓ Each additional child is $5/month            ║
  ║  ✓ Prorated charge today: ~$2.50                ║
  ║  ✓ Cancel anytime                               ║
  ║                                                  ║
  ║  [Add Child - $5/mo] [Cancel]                   ║
  ╚══════════════════════════════════════════════════╝
   ↓
If "Add Child":
  1. User enters child's name, info
  2. System assigns color automatically (e.g., Teal 🟡)
  
  3. Call Stripe API: Update subscription quantity
     → Add add-on line item or increase quantity
     → Prorated charge applied automatically
     
  4. Webhook: customer.subscription.updated
     → WordPress updates addon_quantity: 1
     → New price: $14.99/mo
     
  5. Child is added to account
     → Color assigned
     → Events will show in child's color
     → Parent sees confirmation

Additional children work the same way:
  - 3rd child: +$5 = $19.99/mo total
  - 4th child: +$5 = $24.99/mo total
  - 5th child: +$5 = $29.99/mo total
  - No limits, scales infinitely
```

### **Flow 2b: Child Gets Their Own Color**

```
After child is added:
   ↓
System auto-assigns from palette:
  1st child: 🔴 Red (#FF6B6B)
  2nd child: 🟡 Teal (#4ECDC4)
  3rd child: 🟨 Yellow (#FFD93D)
  ... and so on
   ↓
Parent's calendar now shows:
  - Emma's events in Red
  - Lily's events in Teal
  - Legend with toggle switches
   ↓
Parent can:
  - Toggle each child on/off
  - Change colors (optional)
  - See at a glance who has what
```

### **Flow 3: Trial Ending (Day 14)**

```
Day 7: Email reminder
  Subject: "Your trial ends in 7 days"
  Body: "You have 7 days left in your free trial. 
         First charge will be $9.99 on March 12.
         Cancel anytime with no charge."
  
Day 12: Email reminder
  Subject: "2 days left in your trial"
  Body: Similar to day 7
  
Day 14 (Morning): Final email
  Subject: "Your trial ends today"
  Body: "Your free trial ends today at 10:00 AM.
         To continue using Family Travel Tracker, 
         no action needed - we'll bill your card on file.
         To cancel, click here: [Cancel Subscription]"

Day 14 (10:00 AM): Stripe auto-charges
  → Webhook: invoice.paid
  → WordPress updates:
    - status: 'trialing' → 'active'
    - subscription_start: current_time
    - current_period_end: +1 month
  → Email confirmation:
    "Your subscription is now active! 
     Next billing date: April 12, 2026"
```

### **Flow 4: Payment Failure (After Trial)**

```
Day 0: Payment fails (e.g., expired card)
  → Stripe attempts automatic retry
  → Webhook: invoice.payment_failed
  → WordPress records:
    - payment_failed_date
    - grace_period_end (7 days from now)
    - status: 'past_due'
  → Email sent immediately:
    Subject: "Payment Failed - Update Required"
    Body: "We couldn't process your payment.
           Please update your payment method.
           You have 7 days to update before access is suspended."
  → Dashboard banner shown:
    ⚠️ Payment failed. Update payment method before March 22.

Day 2: Stripe auto-retries
  → If succeeds: problem solved, webhook updates status
  → If fails: Continue to day 4

Day 4: Stripe auto-retries again
  → Same as day 2

Day 5: Reminder email
  Subject: "2 days left to update payment"
  
Day 7: Grace period ends
  → Webhook: customer.subscription.deleted (Stripe cancels)
  → WordPress:
    - status: 'canceled'
    - Access restrictions enabled
  → Email:
    Subject: "Subscription suspended"
    Body: "Your subscription has been suspended due to 
           payment failure. Update payment to reactivate."
  → User sees at login:
    "Your subscription is inactive. Please update payment."
    [Update Payment Method] button
```

### **Flow 5: User Cancels Subscription**

```
User clicks: "Cancel Subscription" in Account page
   ↓
Show confirmation modal:
  ╔══════════════════════════════════════════════════╗
  ║  Are you sure you want to cancel?               ║
  ║                                                  ║
  ║  • Your subscription ends: April 12, 2026       ║
  ║  • You can use all features until then          ║
  ║  • No refund for current period                 ║
  ║  • You can reactivate anytime                   ║
  ║                                                  ║
  ║  [Yes, Cancel Subscription] [Keep Subscription] ║
  ╚══════════════════════════════════════════════════╝
   ↓
If confirmed:
  → WordPress calls Stripe API:
    $stripe->subscriptions->update($sub_id, [
      'cancel_at_period_end' => true
    ]);
  → Webhook: customer.subscription.updated
  → WordPress updates:
    - cancel_at_period_end: true
  → Email confirmation:
    "Your subscription will end on April 12, 2026.
     You can reactivate anytime before then."
  → Dashboard shows:
    ℹ️ Your subscription ends April 12. Reactivate anytime.
```

---

## 🔧 Technical Implementation

### **New Files to Create**

```
includes/
  ├── stripe/
  │   ├── class-stripe-integration.php    (Main Stripe API wrapper)
  │   ├── class-stripe-webhooks.php       (Webhook handler)
  │   ├── class-stripe-subscriptions.php  (Subscription management)
  │   └── class-stripe-plans.php          (Plan logic & calculations)
  │
  ├── billing/
  │   ├── class-billing-limits.php        (Enforce child limits)
  │   ├── class-billing-notifications.php (Trial reminders, etc.)
  │   └── class-billing-access-control.php (Restrict features)
  │
  └── stripe-init.php                      (Load all Stripe classes)

templates/
  ├── billing/
  │   ├── pricing.php                     (Public pricing page)
  │   ├── checkout-success.php            (After Stripe checkout)
  │   ├── checkout-cancel.php             (User canceled checkout)
  │   ├── account-billing.php             (Manage subscription)
  │   ├── upgrade-prompt.php              (Child limit modal)
  │   └── payment-failed.php              (Update payment banner)

assets/
  ├── js/
  │   └── stripe-billing.js               (Stripe.js integration)
  └── css/
      └── billing.css                     (Billing UI styles)
```

### **Class: Stripe_Integration**

```php
<?php
/**
 * Main Stripe Integration Class
 */
class FTT_Stripe_Integration {
    
    private $stripe;
    private $settings;
    
    public function __construct() {
        $this->load_settings();
        $this->init_stripe();
    }
    
    /**
     * Initialize Stripe SDK
     */
    private function init_stripe() {
        require_once FTT_PLUGIN_DIR . 'vendor/stripe/stripe-php/init.php';
        
        $secret_key = $this->settings['mode'] === 'live' 
            ? $this->settings['live_secret_key']
            : $this->settings['test_secret_key'];
            
        \Stripe\Stripe::setApiKey($secret_key);
    }
    
    /**
     * Create Stripe Checkout Session
     */
    public function create_checkout_session($user_id, $plan, $interval = 'month') {
        $user = get_userdata($user_id);
        $price_id = $this->get_price_id($plan, $interval);
        
        $session = \Stripe\Checkout\Session::create([
            'customer_email' => $user->user_email,
            'client_reference_id' => $user_id,
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $price_id,
                'quantity' => 1,
            ]],
            'subscription_data' => [
                'trial_period_days' => 14,
                'metadata' => [
                    'user_id' => $user_id,
                    'plan' => $plan,
                    'interval' => $interval,
                ],
            ],
            'success_url' => home_url('/billing/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => home_url('/pricing?canceled=1'),
        ]);
        
        return $session;
    }
    
    /**
     * Update subscription (upgrade/downgrade)
     */
    public function update_subscription($user_id, $new_plan, $new_interval) {
        $subscription_id = get_user_meta($user_id, 'ftt_stripe_subscription_id', true);
        $new_price_id = $this->get_price_id($new_plan, $new_interval);
        
        $subscription = \Stripe\Subscription::retrieve($subscription_id);
        
        \Stripe\Subscription::update($subscription_id, [
            'items' => [[
                'id' => $subscription->items->data[0]->id,
                'price' => $new_price_id,
            ]],
            'proration_behavior' => 'always_invoice', // Charge/credit immediately
        ]);
    }
    
    /**
     * Cancel subscription at period end
     */
    public function cancel_subscription($user_id) {
        $subscription_id = get_user_meta($user_id, 'ftt_stripe_subscription_id', true);
        
        \Stripe\Subscription::update($subscription_id, [
            'cancel_at_period_end' => true,
        ]);
    }
    
    /**
     * Reactivate canceled subscription
     */
    public function reactivate_subscription($user_id) {
        $subscription_id = get_user_meta($user_id, 'ftt_stripe_subscription_id', true);
        
        \Stripe\Subscription::update($subscription_id, [
            'cancel_at_period_end' => false,
        ]);
    }
    
    /**
     * Create customer portal session (for updating payment methods)
     */
    public function create_portal_session($user_id) {
        $customer_id = get_user_meta($user_id, 'ftt_stripe_customer_id', true);
        
        $session = \Stripe\BillingPortal\Session::create([
            'customer' => $customer_id,
            'return_url' => home_url('/account/billing'),
        ]);
        
        return $session;
    }
}
```

### **Class: Stripe_Webhooks**

```php
<?php
/**
 * Stripe Webhook Handler
 */
class FTT_Stripe_Webhooks {
    
    /**
     * Handle incoming webhook
     */
    public function handle_webhook() {
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $webhook_secret = get_option('ftt_stripe_webhook_secret');
        
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, 
                $sig_header, 
                $webhook_secret
            );
        } catch(\Exception $e) {
            http_response_code(400);
            exit();
        }
        
        // Route to appropriate handler
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handle_checkout_completed($event->data->object);
                break;
                
            case 'customer.subscription.updated':
                $this->handle_subscription_updated($event->data->object);
                break;
                
            case 'customer.subscription.deleted':
                $this->handle_subscription_deleted($event->data->object);
                break;
                
            case 'invoice.paid':
                $this->handle_invoice_paid($event->data->object);
                break;
                
            case 'invoice.payment_failed':
                $this->handle_payment_failed($event->data->object);
                break;
        }
        
        http_response_code(200);
    }
    
    /**
     * Handle successful checkout
     */
    private function handle_checkout_completed($session) {
        $user_id = $session->client_reference_id;
        $subscription = \Stripe\Subscription::retrieve($session->subscription);
        
        // Store Stripe IDs
        update_user_meta($user_id, 'ftt_stripe_customer_id', $session->customer);
        update_user_meta($user_id, 'ftt_stripe_subscription_id', $session->subscription);
        update_user_meta($user_id, 'ftt_subscription_status', $subscription->status);
        
        // Store trial dates
        update_user_meta($user_id, 'ftt_trial_start', current_time('mysql'));
        update_user_meta($user_id, 'ftt_trial_end', 
            date('Y-m-d H:i:s', $subscription->trial_end));
        
        // Store plan details
        update_user_meta($user_id, 'ftt_subscription_plan', 
            $subscription->metadata->plan);
        update_user_meta($user_id, 'ftt_subscription_interval', 
            $subscription->metadata->interval);
        
        // Set limits based on plan
        $limits = $this->get_plan_limits($subscription->metadata->plan);
        update_user_meta($user_id, 'ftt_children_limit', $limits['children']);
        
        // Send welcome email
        $this->send_trial_welcome_email($user_id);
    }
    
    /**
     * Handle invoice paid (billing successful)
     */
    private function handle_invoice_paid($invoice) {
        $customer_id = $invoice->customer;
        $user = $this->get_user_by_stripe_customer($customer_id);
        
        if (!$user) return;
        
        // Update status to active
        update_user_meta($user->ID, 'ftt_subscription_status', 'active');
        
        // Update billing period
        $subscription = \Stripe\Subscription::retrieve($invoice->subscription);
        update_user_meta($user->ID, 'ftt_current_period_start', 
            date('Y-m-d H:i:s', $subscription->current_period_start));
        update_user_meta($user->ID, 'ftt_current_period_end', 
            date('Y-m-d H:i:s', $subscription->current_period_end));
        
        // Clear any failed payment flags
        delete_user_meta($user->ID, 'ftt_payment_failed_date');
        delete_user_meta($user->ID, 'ftt_grace_period_end');
        delete_user_meta($user->ID, 'ftt_payment_retry_count');
        
        // Send receipt email
        $this->send_payment_success_email($user->ID, $invoice->amount_paid / 100);
    }
    
    /**
     * Handle payment failure
     */
    private function handle_payment_failed($invoice) {
        $customer_id = $invoice->customer;
        $user = $this->get_user_by_stripe_customer($customer_id);
        
        if (!$user) return;
        
        // Update status
        update_user_meta($user->ID, 'ftt_subscription_status', 'past_due');
        
        // Set grace period (7 days from now)
        $failed_date = current_time('mysql');
        $grace_end = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        update_user_meta($user->ID, 'ftt_payment_failed_date', $failed_date);
        update_user_meta($user->ID, 'ftt_grace_period_end', $grace_end);
        
        // Increment retry count
        $retry_count = get_user_meta($user->ID, 'ftt_payment_retry_count', true) ?: 0;
        update_user_meta($user->ID, 'ftt_payment_retry_count', $retry_count + 1);
        
        // Send urgent email
        $this->send_payment_failed_email($user->ID, $grace_end);
    }
}
```

### **Class: Billing_Limits**

```php
<?php
/**
 * Enforce subscription limits
 */
class FTT_Billing_Limits {
    
    /**
     * Check if user can add another child
     */
    public static function can_add_child($user_id) {
        // Get limits
        $limit = (int) get_user_meta($user_id, 'ftt_children_limit', true);
        $current = (int) get_user_meta($user_id, 'ftt_children_count', true);
        
        // Check subscription status
        $status = get_user_meta($user_id, 'ftt_subscription_status', true);
        
        if (!in_array($status, ['active', 'trialing'])) {
            return [
                'allowed' => false,
                'reason' => 'subscription_inactive',
                'message' => 'Your subscription is not active. Please update payment.',
            ];
        }
        
        if ($current >= $limit) {
            return [
                'allowed' => false,
                'reason' => 'limit_reached',
                'message' => 'You\'ve reached your child limit. Upgrade to add more.',
                'upgrade_available' => true,
            ];
        }
        
        return [
            'allowed' => true,
        ];
    }
    
    /**
     * Check if user can link another parent to child
     */
    public static function can_add_parent($child_id) {
        $max_parents = 4; // From settings
        
        // Count existing parents
        $parents = SRT_Roles::get_parents($child_id);
        
        if (count($parents) >= $max_parents) {
            return [
                'allowed' => false,
                'reason' => 'parent_limit_reached',
                'message' => 'Maximum 4 parents per child allowed.',
            ];
        }
        
        return [
            'allowed' => true,
        ];
    }
    
    /**
     * Get suggested upgrade plan
     */
    public static function get_upgrade_suggestion($user_id) {
        $addon_quantity = (int) get_user_meta($user_id, 'ftt_addon_quantity', true);
        $children_count = (int) get_user_meta($user_id, 'ftt_children_count', true);
        $is_annual = get_user_meta($user_id, 'ftt_billing_period', true) === 'annual';
        
        // Calculate new price when adding another child
        $new_addon_qty = $addon_quantity + 1;
        $base_price = $is_annual ? 89.99 : 9.99;
        $addon_price = $is_annual ? 50.00 : 5.00;
        $new_total = $base_price + ($new_addon_qty * $addon_price);
        
        return [
            'addon_quantity' => $new_addon_qty,
            'name' => 'Additional Child Add-On',
            'price' => '$' . number_format($new_total, 2) . ($is_annual ? '/yr' : '/mo'),
            'addon_cost' => '$' . number_format($addon_price, 2) . ($is_annual ? '/yr' : '/mo'),
            'children_total' => $children_count + 1,
            'note' => 'Each additional child is ' . ($is_annual ? '$50/year' : '$5/month'),
        ];
    }
}
```

---

## 🔐 Security Checklist

### **API Key Protection**

```php
// Store in wp-config.php (NOT in database for production)
define('STRIPE_LIVE_SECRET_KEY', getenv('STRIPE_LIVE_SECRET_KEY'));
define('STRIPE_LIVE_PUBLISHABLE_KEY', getenv('STRIPE_LIVE_PUB_KEY'));
define('STRIPE_WEBHOOK_SECRET', getenv('STRIPE_WEBHOOK_SECRET'));
```

### **Webhook Signature Verification**

```php
// ALWAYS verify webhooks came from Stripe
try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, 
        $signature, 
        $webhook_secret
    );
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    error_log('Invalid webhook signature: ' . $e->getMessage());
    http_response_code(400);
    exit();
}
```

### **User Permission Checks**

```php
// Only allow users to manage their own subscription
public function cancel_subscription_endpoint() {
    $user_id = get_current_user_id();
    $requested_user_id = $_POST['user_id'];
    
    if ($user_id !== (int)$requested_user_id && !current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }
    
    // Proceed with cancellation...
}
```

### **Rate Limiting**

```php
// Prevent brute force attacks on checkout endpoints
public function check_rate_limit($user_id) {
    $attempts = get_transient('checkout_attempts_' . $user_id);
    
    if ($attempts && $attempts > 5) {
        return new WP_Error('rate_limit', 'Too many attempts. Try again in 1 hour.');
    }
    
    set_transient('checkout_attempts_' . $user_id, ($attempts ?: 0) + 1, HOUR_IN_SECONDS);
    return true;
}
```

### **HTTPS Enforcement**

```php
// Force HTTPS on billing pages
add_action('template_redirect', function() {
    if (is_page('pricing') || is_page('billing')) {
        if (!is_ssl()) {
            wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301);
            exit();
        }
    }
});
```

---

## 🧪 Testing Plan

### **Test Mode (Stripe Test Keys)**

```
Test Cards (Stripe provides):
  - Success: 4242 4242 4242 4242
  - Decline: 4000 0000 0000 0002
  - Requires Auth: 4000 0027 6000 3184
  - Expired: 4000 0000 0000 0069
  
Use any future expiration date and any 3-digit CVC.
```

### **Test Scenarios**

**1. Happy Path**
- [ ] Create account with SingleChild plan (monthly)
- [ ] Verify trial starts correctly (14 days)
- [ ] Add first child successfully
- [ ] Receive day 7 trial reminder email
- [ ] Receive day 12 trial reminder email
- [ ] Receive day 14 final reminder email
- [ ] Payment processes on day 15
- [ ] Status changes to 'active'
- [ ] Access continues uninterrupted

**2. Add-On Flow**
- [ ] Start with Base Subscription
- [ ] Add first child (included in base)
- [ ] Add second child
- [ ] See add-on confirmation ($5/mo more)
- [ ] Confirm and add child
- [ ] Verify prorated charge (~$2.50 mid-month)
- [ ] Second child appears with color assigned
- [ ] Can add unlimited children at $5 each

**3. Cancellation**
- [ ] User cancels during trial
- [ ] Payment method not charged
- [ ] Access immediately removed
- [ ] Confirmation email sent
- [ ] User cancels after trial
- [ ] Access continues until period end
- [ ] No refund issued
- [ ] Can reactivate before period ends

**4. Payment Failure**
- [ ] Use declined test card
- [ ] Payment fails
- [ ] Status changes to 'past_due'
- [ ] Immediate email notification
- [ ] 7-day grace period starts
- [ ] User can update payment method
- [ ] After 7 days, subscription canceled
- [ ] Access restricted

**5. Yearly Plan**
- [ ] Select yearly billing
- [ ] Trial works same as monthly
- [ ] Charged $89.99 after trial
- [ ] Next billing in 12 months

**6. Multi-Parent Access**
- [ ] Child invites parent
- [ ] Parent accepts invitation
- [ ] Both see same child's events
- [ ] Second parent invites third parent
- [ ] All three have access
- [ ] Cannot add 5th parent (limit 4)

---

## 📧 Email Templates

### **Trial Started (Day 0)**

```
Subject: Welcome to Family Travel Tracker! Your trial has started

Hi [Name],

Welcome to Family Travel Tracker! 🎉

Your 14-day free trial started today. Here's what you need to know:

✅ Full access to all features
✅ Track unlimited events for [X] children
✅ Flight price alerts and travel tracking
✅ Calendar sync with your devices

TRIAL DETAILS:
• Started: February 26, 2026
• Ends: March 12, 2026
• First charge: $9.99 on March 12
• Cancel anytime before then - no charge

NEXT STEPS:
1. Add your first child → [Add Child]
2. Create an event → [Add Event]
3. Set up calendar sync → [Calendar]

Questions? Reply to this email anytime.

Happy tracking!
The Family Travel Tracker Team

---
Cancel anytime: [Cancel Subscription Link]
```

### **Trial Reminder (Day 7)**

```
Subject: 7 days left in your free trial

Hi [Name],

You have 7 days left in your free trial of Family Travel Tracker.

TRIAL STATUS:
• Days remaining: 7
• Trial ends: March 12, 2026
• First charge: $9.99 on March 12

Your subscription will automatically start after your trial ends. 
No action needed if you'd like to continue.

To cancel: [Cancel Subscription]

Questions? Just reply to this email.

Thanks,
Family Travel Tracker Team
```

### **Payment Failed (Immediate)**

```
Subject: ⚠️ Payment Failed - Action Required

Hi [Name],

We tried to process your payment but it didn't go through.

WHAT HAPPENED:
Your payment method was declined. This could be due to:
• Expired card
• Insufficient funds
• Bank blocking the charge

WHAT YOU NEED TO DO:
Update your payment method in the next 7 days to keep your access.

[Update Payment Method] ← Click here

GRACE PERIOD:
• Today: March 15, 2026
• Deadline: March 22, 2026 (7 days)
• After deadline: Access will be suspended

Need help? Reply to this email.

Family Travel Tracker Team

---
We'll automatically retry your payment a few times over the next week.
```

---

## 🚀 Go-Live Checklist

### **Pre-Launch (Test Mode)**

- [ ] Stripe account created and verified
- [ ] Business info completed in Stripe
- [ ] Bank account connected for payouts
- [ ] All products created in Stripe
- [ ] Test mode API keys configured
- [ ] Webhook endpoint configured (test mode)
- [ ] All code implemented and tested
- [ ] Email templates configured
- [ ] Test all flows with test cards
- [ ] Legal pages updated (Terms, Privacy, Refund Policy)

### **Go Live**

- [ ] Switch to live API keys
- [ ] Create products in live mode (same as test)
- [ ] Configure webhook endpoint (live mode)
- [ ] Update website to show pricing
- [ ] Test live checkout (small amount)
- [ ] Verify webhook receives live events
- [ ] Monitor Stripe dashboard for first real customers
- [ ] Set up Stripe email notifications for yourself

### **Post-Launch**

- [ ] Monitor error logs daily for first week
- [ ] Check webhook delivery success rate
- [ ] Review subscription metrics in Stripe
- [ ] Set up automated reports (MRR, churn, etc.)
- [ ] Create help docs for common billing questions

---

## 📊 Metrics to Track

**Key SaaS Metrics:**

```
MRR (Monthly Recurring Revenue)
  = Sum of all active monthly subscriptions
  
ARR (Annual Recurring Revenue)
  = MRR × 12
  
Churn Rate (Monthly)
  = (Cancellations this month / Active at start) × 100
  
LTV (Lifetime Value)
  = Average subscription price × Average months retained
  
CAC (Customer Acquisition Cost)
  = Marketing spend / New customers
  
Trial Conversion Rate
  = (Trials that convert / Total trials started) × 100
  
Target: 40-60% trial conversion is good
```

**Stripe Dashboard Shows:**
- Total customers
- MRR and growth
- Failed payments
- Churn rate
- Lifetime value
- Most popular plans

---

## 🔧 Configuration Files

### **Settings Page (WordPress Admin)**

```
WordPress Admin → Family Travel Tracker → Billing Settings

┌─────────────────────────────────────────────────────────┐
│ STRIPE CONFIGURATION                                    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Mode: ● Test  ○ Live                                    │
│                                                         │
│ Test Publishable Key: pk_test_xxxxx                     │
│ Test Secret Key: sk_test_xxxxx (hidden)                │
│                                                         │
│ Live Publishable Key: pk_live_xxxxx                     │
│ Live Secret Key: sk_live_xxxxx (hidden)                │
│                                                         │
│ Webhook Secret: whsec_xxxxx (hidden)                    │
│                                                         │
├─────────────────────────────────────────────────────────┤
│ PRICE IDS (From Stripe Dashboard)                      │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Single Child Monthly: price_xxxxx                       │
│ Base Subscription Monthly: price_xxxxx                  │
│ Base Subscription Yearly: price_xxxxx                   │
│ Additional Child Monthly: price_xxxxx ($5)              │
│ Additional Child Yearly: price_xxxxx ($50)              │
│                                                         │
├─────────────────────────────────────────────────────────┤
│ TRIAL & LIMITS                                          │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Trial Period (days): [14]                              │
│ Grace Period for Failed Payments (days): [7]           │
│ Max Parents per Child: [4]                             │
│                                                         │
│ [Save Settings]                                         │
└─────────────────────────────────────────────────────────┘
```

---

## ⚠️ Edge Cases & Solutions

### **1. User cancels during trial then wants to reactivate**

**Solution:** Allow reactivation if within trial period.
```php
if ($status === 'canceled' && time() < strtotime($trial_end)) {
    // Reactivate without charge
    $stripe->subscriptions->update($sub_id, ['cancel_at_period_end' => false]);
}
```

### **2. User tries to game system (cancel/resubscribe for multiple trials)**

**Solution:** Track user by email, only one trial per email ever.
```php
$previous_trial = get_user_meta($user_id, 'ftt_trial_used', true);
if ($previous_trial) {
    return new WP_Error('trial_used', 'Trial already used for this account.');
}
```

### **3. Payment fails during trial (before first charge)**

**Solution:** Trial shouldn't fail. Stripe only validates card, doesn't charge.
```php
// If this happens, it's a card validation failure
// Ask user to enter different card
```

### **4. User upgrades from monthly to yearly mid-cycle**

**Solution:** Stripe handles prorating automatically.
```php
// Stripe credits unused monthly time
// Charges prorated yearly amount
// Example: $9.99/mo, 15 days left = $4.99 credit
//          $89.99/yr - $4.99 = $84.99 charged
```

### **5. Divorced parents both try to pay for same child**

**Solution:** Only the account creator (child or first parent) manages billing.
```php
// Other parents have view-only access
// Cannot modify subscription
// Child's account holder controls billing
```

### **6. User's card expires during active subscription**

**Solution:** Stripe sends email to customer about card expiry.
```php
// Webhook: customer.source.expiring (30 days before)
// Send reminder email to update card
// Show banner in dashboard
```

---

## 🎯 Success Criteria

**Launch is successful if:**
- ✅ Trial signup flow works smoothly
- ✅ Payments process correctly (0% failed webhooks)
- ✅ Trial conversion rate > 40%
- ✅ No security vulnerabilities
- ✅ Customer support tickets < 5% of users
- ✅ Upgrade prompts work (soft limits effective)
- ✅ Emails deliver reliably
- ✅ $1,000 MRR in first 2 months

---

## 📅 Implementation Timeline

**Phase 1: Core Integration (Week 1)**
- Day 1-2: Set up Stripe account, create products
- Day 3-4: Build Stripe_Integration class
- Day 5-6: Build webhook handler
- Day 7: Testing with test cards

**Phase 2: UI & Flows (Week 2)**
- Day 8-9: Pricing page
- Day 10-11: Account billing page
- Day 12-13: Upgrade prompts & limits
- Day 14: Email templates

**Phase 3: Testing & Polish (Week 3)**
- Day 15-16: Full flow testing
- Day 17-18: Edge case testing
- Day 19-20: Security audit
- Day 21: Soft launch (invite only)

**Phase 4: Launch (Week 4)**
- Day 22-23: Monitor soft launch
- Day 24: Go live publicly
- Day 25-28: Monitor, fix issues, iterate

---

## 💬 Support & FAQs

**Customer FAQ Page (to create):**

**Q: When will I be charged?**
A: Your 14-day free trial starts today. Your first charge will be on [DATE], 14 days from now. You can cancel anytime before then with no charge.

**Q: Can I cancel anytime?**
A: Yes! Cancel anytime. During your trial, you won't be charged. After trial, you keep access until your current billing period ends.

**Q: What if my payment fails?**
A: We'll email you immediately. You have 7 days to update your payment method before access is suspended. We'll retry automatically a few times.

**Q: Can I switch from monthly to yearly?**
A: Yes! Upgrade anytime. We'll prorate your current plan and charge the difference.

**Q: How do I add more children?**
A: Click "Add Child" anytime. Your first child is included in your $9.99/mo base subscription. Each additional child is just $5/month or $50/year. No limits!

**Q: Can both divorced parents access the same child's calendar?**
A: Yes! That's what we're built for. Up to 4 parents/guardians can link to each child's account and see their schedule.

**Q: Is my payment information secure?**
A: Absolutely. We use Stripe, which processes billions in payments securely. We never see or store your credit card number.

---

## ✅ Ready to Build?

This document covers:
- ✅ Complete pricing structure
- ✅ Detailed user flows
- ✅ Technical architecture
- ✅ Security best practices
- ✅ Testing plan
- ✅ Email templates
- ✅ Go-live checklist
- ✅ Edge cases
- ✅ Success metrics

**Next Step:** Review this document, make any adjustments, then we start building!

**Estimated Development Time:** 3-4 weeks for complete implementation and testing.

**Questions to resolve before starting:**
1. ✅ Pricing structure - CONFIRMED
2. ✅ Trial length - CONFIRMED (14 days)
3. ✅ Payment collection timing - CONFIRMED (upfront)
4. ✅ Child limits enforcement - CONFIRMED (soft with prompts)
5. ✅ Failed payment grace period - CONFIRMED (7 days)

**Status:** Ready to implement! 🚀
