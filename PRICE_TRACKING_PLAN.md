# Flight Price Tracking & User Notification System

## 🎯 What We've Built (Phase 1 - Foundation)

### 1. **Custom WordPress Roles**
Two new user roles have been created:

**Regiment Member**
- Can view all events
- Can manage their own travel details
- Can receive price alerts
- Access to personal dashboard

**Regiment Parent**
- Can view all events
- Can view travel information
- Can receive price alerts
- Read-only access to child's travel

### 2. **Price Tracking Database**
Two new database tables:

**`wp_srt_price_history`**
- Stores historical flight prices
- Tracks: event_id, route, date, price, timestamp
- Used for trend analysis and statistics

**`wp_srt_price_alerts`**
- Stores user alert preferences
- Alert types: price_drop, percent_drop, good_deal
- Tracks when alerts were last triggered

### 3. **Price Monitoring System**
- Automated cron job (runs twice daily)
- Checks all unbooked flights
- Records prices in history table
- Triggers alerts when conditions met

### 4. **Alert Types**
- **Price Drop**: Alert when price drops below threshold
- **Percent Drop**: Alert when price drops by X%
- **Good Deal**: Alert when price is 10%+ below 30-day average

---

## 📊 How It Works (Like Hopper)

### Price Analysis
```
Current Price: $450
30-Day Average: $520
Status: 🟢 GOOD DEAL (13% below average)
Alert: Send notifications to subscribed users
```

### Color Coding System
- 🟢 **Green (Great Deal)**: 10%+ below average
- 🟡 **Yellow (Fair Price)**: Within 10% of average  
- 🔴 **Red (High Price)**: 10%+ above average

### Price Trend Indicators
- ⬇️ **Dropping**: Price decreased in last 24 hours
- ⬆️ **Rising**: Price increased in last 24 hours
- ➡️ **Stable**: Price unchanged

---

## 🚀 Phase 2: Next Steps

### A. Add Flight Price API Integration
**Recommended: Amadeus API** (Free tier available)
- Register at https://developers.amadeus.com
- Get API credentials
- Implement price fetching
- Alternative: Manual price entry for now

### B. User Interface Additions

**1. Event Form - Add Travelers Section**
```javascript
<div class="srt-travelers">
    <h3>Travelers</h3>
    <select multiple name="travelers[]">
        <!-- List all Regiment Members/Parents -->
    </select>
    <label>
        <input type="checkbox" name="notify_travelers">
        Send price alerts to travelers
    </label>
</div>
```

**2. Price History Chart**
- Show 30-day price graph on event modal
- Display min/max/average prices
- Color-coded current price indicator

**3. User Dashboard**
- "My Trips" section
- Active price alerts
- Recent price changes
- Upcoming travel

**4. Alert Management Page**
- View all active alerts
- Set custom price thresholds
- Enable/disable alerts
- Email notification preferences

### C. Email Notifications
Templates needed:
- Price drop alert
- Good deal alert
- Booking reminder (7 days before)
- Event update notification

---

## 💻 Implementation Checklist

### Immediate (Can Do Now)
- [ ] Add travelers field to event form
- [ ] Add user subscription to events
- [ ] Create alert management UI
- [ ] Add manual price entry form (for testing)

### Short Term (Need API Keys)
- [ ] Register for Amadeus API
- [ ] Implement API integration
- [ ] Test automated price checking
- [ ] Verify alert system

### Medium Term (Polish)
- [ ] Build price history charts
- [ ] Create user dashboard
- [ ] Add email templates
- [ ] Mobile optimization

---

## 🔧 API Integration Options

### Option 1: Amadeus (Recommended)
**Pros:**
- Free tier: 2,000 API calls/month
- Reliable, official data
- Good documentation

**Cons:**
- Requires registration
- Rate limits on free tier

**Setup:**
```php
// In includes/price-tracking.php
public static function fetch_amadeus_price($origin, $dest, $date) {
    $api_key = get_option('srt_amadeus_api_key');
    $api_secret = get_option('srt_amadeus_api_secret');
    
    // Implement Amadeus API call
}
```

### Option 2: Manual Entry (For Now)
- Admin enters prices manually
- Still builds history
- Alerts still work
- Good for testing

---

## 📝 Usage Example

### For Admins:
1. Create event with travel leg
2. Add travelers to event
3. System automatically monitors prices
4. Twice daily price checks
5. Alerts sent when conditions met

### For Members/Parents:
1. Subscribe to event notifications
2. Set alert preferences
3. Receive email when prices drop
4. View price history on dashboard
5. Click through to book when ready

---

## 🎨 UI Mockup - Event Modal with Price Info

```
┌─────────────────────────────────────┐
│ April Camp                      [X] │
├─────────────────────────────────────┤
│ Type: Camp Weekend                  │
│ Date: Apr 12-18, 2026              │
│                                     │
│ ✈️ Travel                           │
│ ┌─────────────────────────────────┐ │
│ │ BDL → ORD                       │ │
│ │ April 12, 2026 (morning)       │ │
│ │                                 │ │
│ │ Current Price: $450 🟢          │ │
│ │ 30-Day Average: $520            │ │
│ │ ⬇️ Down $25 in last 24 hours    │ │
│ │                                 │ │
│ │ [View Price History]            │ │
│ │ [Set Price Alert]               │ │
│ │ ✗ Not Booked                    │ │
│ │                                 │ │
│ │ 🔍 Search Flights:              │ │
│ │ [Google] [Kayak] [Southwest]   │ │
│ └─────────────────────────────────┘ │
│                                     │
│ 👥 Travelers (3):                   │
│ • John Smith (alerts: ON)           │
│ • Jane Doe (alerts: ON)             │
│ • Bob Johnson (alerts: OFF)         │
│                                     │
│ [Edit Event]                        │
└─────────────────────────────────────┘
```

---

## ⚙️ Configuration Needed

Add to Settings page:
- [ ] Amadeus API credentials
- [ ] Default alert thresholds
- [ ] Email notification preferences
- [ ] Price check frequency
- [ ] Data retention period (default: 90 days)

---

## 🎯 Next Immediate Action

**What should we do next?**

1. **Add travelers field to events** (UI update)
2. **Create alert management page** (new admin page)
3. **Test with manual prices** (no API needed)
4. **Get Amadeus API key** (for automation)

Which would you like to tackle first?
