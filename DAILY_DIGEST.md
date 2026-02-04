# Daily Price Digest Feature

## Overview

The Daily Price Digest automatically sends users a consolidated email every day at 2am with all their tracked flight prices, trends, and actionable recommendations.

## Features

### Automated Email Delivery
- **Schedule**: Runs daily at 2am
- **Cron Hook**: `srt_daily_digest`
- **Recipients**: All users with active `daily_digest` alert type
- **Consolidation**: One email per user with all tracked flights

### Smart Recommendations

The digest analyzes each flight and categorizes it with actionable advice:

| Category | Criteria | Recommendation | Icon |
|----------|----------|----------------|------|
| **Good Deals** | Current price <85% of average | ✅ Book now - Great price! | Green |
| **Trending Up** | Price increasing + <30 days to departure | ⚠️ Book soon - Prices rising | Red |
| **Trending Down** | Price decreasing + >30 days to departure | ⏳ Wait and watch - Prices dropping | Yellow |
| **Stable** | Price within ±5% of 7-day average | ➡️ Monitor - Prices stable | Blue |

### Email Content

Each flight card includes:
- **Event Title** - What trip this flight is for
- **Route** - Origin → Destination with departure date
- **Trend Badge** - Visual indicator (📈 Up, 📉 Down, ➡️ Stable)
- **Current Price** - Latest tracked price
- **7-Day Change** - Dollar and percentage change
- **Days to Departure** - How many days until the flight
- **Statistics** - Min, Avg, Max prices from last 7 days
- **Recommendation** - Color-coded action advice

## Setup

### 1. Enable Daily Digest

Users can enable the daily digest from their Member Dashboard:

1. Navigate to Member Dashboard
2. Scroll to flight with price tracking
3. Click "Track Price" button
4. Select **"📧 Daily Price Digest (2am email)"** from dropdown
5. Click "Create Alert"

### 2. Automatic Scheduling

The cron job is automatically scheduled on plugin activation:

```php
// Scheduled for tomorrow at 2am, then daily
$tomorrow_2am = strtotime('tomorrow 2:00am');
wp_schedule_event($tomorrow_2am, 'daily_2am', 'srt_daily_digest');
```

### 3. Manual Testing

To test the digest without waiting for 2am:

#### Option A: Command Line (WP-CLI)
```bash
wp eval-file test-digest.php
```

#### Option B: Browser
1. Navigate to `yoursite.com/wp-content/plugins/schedule-collaboration-tracking/test-digest.php`
2. Login as admin
3. Click "trigger" link

#### Option C: Direct Action
```php
// In WordPress admin or plugin
do_action('srt_daily_digest');
```

## Technical Implementation

### Database Schema

Daily digest alerts use the same table as other alert types:

```sql
CREATE TABLE wp_srt_price_alerts (
    id bigint(20) PRIMARY KEY AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    event_id bigint(20) NOT NULL,
    leg_index int(11) NOT NULL,
    alert_type varchar(50) NOT NULL,  -- 'daily_digest'
    threshold_price decimal(10,2),    -- NULL for digest
    threshold_percent int(11),        -- NULL for digest
    is_active tinyint(1) DEFAULT 1,
    last_triggered datetime,
    created_at datetime NOT NULL
);
```

### Alert Types

| Type | Description | Requires Threshold |
|------|-------------|-------------------|
| `price_drop` | Alert when price drops below specific amount | ✅ Price |
| `percent_drop` | Alert when price drops by percentage | ✅ Percentage |
| `good_deal` | Alert when price is 15% below average | ❌ Auto-calculated |
| `daily_digest` | Daily consolidated email | ❌ N/A |

### Cron Schedule

Custom cron schedule defined in `add_custom_cron_schedule()`:

```php
$schedules['daily_2am'] = array(
    'interval' => 86400,  // 24 hours in seconds
    'display'  => __('Daily at 2am', 'schedule-collaboration-tracking')
);
```

### Processing Flow

```
1. WordPress Cron triggers 'srt_daily_digest' at 2am
   ↓
2. process_daily_digests() queries all users with active digest alerts
   ↓
3. For each user: send_daily_digest($user_id)
   ↓
4. Query all active alerts for user
   ↓
5. For each flight leg in alerts:
   - Get price history (last 7 days)
   - Calculate statistics (min, max, avg, current)
   - Determine trend (up/down/stable)
   - Categorize by recommendation
   ↓
6. build_digest_email() generates HTML
   ↓
7. wp_mail() sends email to user
   ↓
8. Log result to error_log
```

### Key Functions

#### `process_daily_digests()`
- Entry point for cron job
- Finds all users with digest alerts
- Calls `send_daily_digest()` for each

#### `send_daily_digest($user_id)`
- Main digest generation logic
- Queries alerts for user
- Processes each flight leg
- Calculates trends and recommendations
- Builds email and sends via `wp_mail()`

#### `build_digest_email($user, $digest_data)`
- HTML email template generator
- Renders sections for each category
- Uses `render_flight_card()` for each flight

#### `render_flight_card($flight, $rec_class)`
- Individual flight card HTML
- Shows all price data and recommendation
- Color-coded by category

## Trend Analysis

### Calculation Method

```php
// Get 7 days of price history
$history = get_price_history($origin, $destination, $depart_date, 7);
$prices = array_column($history, 'price');

// Calculate statistics
$current = end($prices);      // Most recent price
$first = reset($prices);      // Oldest price (7 days ago)
$avg = array_sum($prices) / count($prices);
$min = min($prices);
$max = max($prices);

// Determine trend (5% threshold)
if ($current < $first * 0.95) {
    $trend = 'down';  // 5%+ decrease
} elseif ($current > $first * 1.05) {
    $trend = 'up';    // 5%+ increase
} else {
    $trend = 'stable'; // Within ±5%
}
```

### Recommendation Logic

```php
// Good Deal: 15% below average
if ($current < $avg * 0.85) {
    $category = 'good_deals';
    $recommendation = '✅ Book now - Great price!';
}

// Urgent: Rising prices with <30 days
elseif ($trend === 'up' && $days_to_departure < 30) {
    $category = 'trending_up';
    $recommendation = '⚠️ Book soon - Prices rising';
}

// Wait: Falling prices with >30 days buffer
elseif ($trend === 'down' && $days_to_departure > 30) {
    $category = 'trending_down';
    $recommendation = '⏳ Wait and watch - Prices dropping';
}

// Monitor: Stable or unclear
else {
    $category = 'stable';
    $recommendation = '➡️ Monitor - Prices stable';
}
```

## Email Template

### HTML Structure

```html
<html>
  <body>
    <h1>✈️ Daily Flight Price Digest</h1>
    <p>Hello [User Name],</p>
    <p>Here's your daily flight price update for [Date].</p>
    
    <h2>✅ Good Deals Now</h2>
    <p>These flights are at least 15% below their average price...</p>
    [Flight Cards]
    
    <h2>⚠️ Trending Up - Book Soon</h2>
    <p>Prices are rising and departure is approaching...</p>
    [Flight Cards]
    
    <h2>📉 Trending Down - Wait and Watch</h2>
    <p>Prices are dropping and you have time...</p>
    [Flight Cards]
    
    <h2>➡️ Stable Prices</h2>
    <p>Prices are holding steady...</p>
    [Flight Cards]
    
    <div class="footer">
      <p>Manage your price alerts in your Member Dashboard</p>
    </div>
  </body>
</html>
```

### CSS Styling

- **Flight Cards**: White background, rounded corners, shadow
- **Price Info**: Flexbox grid with labels and values
- **Recommendations**: Color-coded backgrounds
  - Green: Good deals (book now)
  - Red: Urgent (prices rising)
  - Yellow: Wait (prices falling)
  - Blue: Stable (monitor)
- **Trend Badges**: Small colored pills with icons

## Troubleshooting

### Digest Not Sending

1. **Check if cron is scheduled**:
   ```php
   $timestamp = wp_next_scheduled('srt_daily_digest');
   echo date('Y-m-d H:i:s', $timestamp);
   ```

2. **Verify custom schedule exists**:
   ```php
   $schedules = wp_get_schedules();
   var_dump($schedules['daily_2am']);
   ```

3. **Check for active digest alerts**:
   ```sql
   SELECT user_id, COUNT(*) 
   FROM wp_srt_price_alerts 
   WHERE alert_type = 'daily_digest' AND is_active = 1 
   GROUP BY user_id;
   ```

4. **Check WordPress error log**:
   ```bash
   tail -f /path/to/error.log | grep "SRT Daily Digest"
   ```

### No Flights in Digest

Possible reasons:
- No price history exists (prices haven't been checked yet)
- Flight legs missing airport codes
- Event not accessible to user
- All alerts are inactive

### Testing Locally

```bash
# 1. Create a digest alert
# 2. Ensure price history exists
# 3. Manually trigger
wp eval 'do_action("srt_daily_digest");'

# 4. Check email in MailHog/MailCatcher
# http://localhost:8025
```

## Future Enhancements

### Planned Features
- [ ] Weekly digest option (summary of week's trends)
- [ ] User preference for digest time (2am, 8am, 6pm)
- [ ] Digest preview in dashboard
- [ ] Unsubscribe link in email
- [ ] Historical accuracy tracking

### Possible Improvements
- Machine learning price prediction
- Personalized booking recommendations based on history
- Multi-currency support
- Mobile-optimized email template
- Push notification option

## Performance Considerations

### Scaling
- Current: Processes all users sequentially
- For >1000 users: Consider batch processing
- For >10000 users: Consider queuing system (Action Scheduler)

### Database Queries
- One query per user to get alerts
- One query per alert to get price history
- Cached post data reduces queries
- Consider caching price statistics

### Email Delivery
- Uses WordPress `wp_mail()` (SMTP recommended)
- No rate limiting currently
- Consider SendGrid/Mailgun for high volume
- Batch emails in groups of 100 for large user bases

## Related Documentation

- [PRICE_TRACKING_PLAN.md](PRICE_TRACKING_PLAN.md) - Overall price tracking strategy
- [PRICE_TRACKING_IMPLEMENTATION.md](PRICE_TRACKING_IMPLEMENTATION.md) - Technical implementation
- [CRON_SETUP.md](CRON_SETUP.md) - Cron configuration and troubleshooting
- [CHANGELOG.md](CHANGELOG.md) - Version history and changes
