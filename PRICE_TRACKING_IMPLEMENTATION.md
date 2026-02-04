# Flight Price Tracking Implementation

## Overview

Automated flight price tracking with Amadeus API integration, checking prices 4 times per day (every 6 hours) and sending email alerts when prices meet user-defined criteria.

## What Was Implemented

### 1. Custom Cron Schedule (4x Daily)
**File**: `includes/price-tracking.php`

- Added `fourtimesdaily` custom schedule (every 6 hours)
- Runs at: 12:00 AM, 6:00 AM, 12:00 PM, 6:00 PM
- Scheduled event: `srt_check_flight_prices`

```php
$schedules['fourtimesdaily'] = array(
    'interval' => 21600, // 6 hours
    'display'  => __('Four Times Daily (Every 6 Hours)')
);
```

### 2. Admin Settings for API Keys
**File**: `includes/settings.php`

New settings section: **Flight Price Tracking API**
- Amadeus API Key field (Client ID)
- Amadeus API Secret field (Client Secret)
- Help text with registration link

Located in: **Settings → Regiment Tracker Settings**

### 3. Amadeus API Integration
**File**: `includes/price-tracking.php`

Complete API implementation:

#### OAuth Authentication
```php
get_amadeus_token($api_key, $api_secret)
```
- Endpoint: `https://test.api.amadeus.com/v1/security/oauth2/token`
- Returns access token for API calls

#### Flight Price Fetching
```php
fetch_flight_price($token, $origin, $destination, $date)
```
- Endpoint: `https://test.api.amadeus.com/v2/shopping/flight-offers`
- Returns lowest price for route/date
- Error logging for debugging

#### Price Recording
```php
record_price($event_id, $leg_index, $origin, $destination, $date, $price, 'amadeus')
```
- Saves to `wp_srt_price_history` table
- Automatically triggers alert checking
- Tracks source (amadeus) and timestamp

#### Main Cron Job
```php
check_all_prices()
```
- Queries upcoming events with flights needed
- Loops through unbooked travel legs
- Fetches prices for each route
- Records in database
- Checks alert conditions
- Rate limiting (1 second between calls)

### 4. REST API Endpoint for Price Alerts
**File**: `includes/rest.php`

New endpoint: `POST /wp-json/srt/v1/price-alerts`

**Parameters:**
- `event_id` (required): Event ID
- `leg_index` (required): Travel leg index
- `alert_type` (required): `price_drop`, `percent_drop`, or `good_deal`
- `threshold_price` (optional): Target price in dollars
- `threshold_percent` (optional): Percentage drop threshold

**Response:**
```json
{
  "success": true,
  "alert_id": 123,
  "message": "Price alert created successfully"
}
```

**Validation:**
- Checks user is logged in
- Validates event exists
- Inserts into `wp_srt_price_alerts` table

### 5. Frontend Integration
**File**: `assets/js/main.js`

#### Track Price Button
Added to flight search links for unbooked flights:
- Bell icon (dashicons-bell)
- Captures: event_id, leg_index, origin, destination, date
- Opens price tracking modal

#### Price Tracking Modal
Interactive form with:
- Route display (origin → destination + date)
- Alert type selector (dropdown)
- Conditional threshold fields:
  - **Price Drop**: Target price input
  - **Percentage Drop**: Percent input (1-100)
  - **Good Deal**: No threshold (uses algorithm)
- Create Alert / Cancel buttons

#### AJAX Submission
```javascript
$.ajax({
    url: srtData.restUrl + 'srt/v1/price-alerts',
    method: 'POST',
    data: JSON.stringify({...}),
    headers: { 'X-WP-Nonce': srtData.restNonce }
})
```
- Success: Shows confirmation, closes after 2 seconds
- Error: Displays error message

### 6. Server Cron Setup Script
**File**: `setup-cron.sh`

Automated setup script that:
1. Checks for WP-CLI installation
2. Adds `DISABLE_WP_CRON` to `wp-config.php`
3. Creates crontab entry for 4x daily execution
4. Verifies setup
5. Shows test commands

**Usage:**
```bash
cd /path/to/wordpress
chmod +x setup-cron.sh
./setup-cron.sh
```

### 7. Documentation
**File**: `CRON_SETUP.md`

Complete guide covering:
- Why server cron (vs WordPress cron)
- Quick setup (automated script)
- Manual setup instructions
- WP-CLI installation
- Hosting-specific notes (cPanel, managed WordPress, VPS)
- API configuration steps
- Troubleshooting
- Monitoring commands
- Performance tuning

## Database Schema

### Price History Table
**Table**: `wp_srt_price_history`

```sql
CREATE TABLE wp_srt_price_history (
    id bigint(20) AUTO_INCREMENT PRIMARY KEY,
    event_id bigint(20) NOT NULL,
    leg_index int(11) NOT NULL,
    origin varchar(3) NOT NULL,
    destination varchar(3) NOT NULL,
    depart_date date NOT NULL,
    price decimal(10,2) NOT NULL,
    currency varchar(3) DEFAULT 'USD',
    source varchar(50) NOT NULL,
    checked_at datetime NOT NULL,
    raw_data longtext,
    KEY event_id (event_id),
    KEY route_date (origin, destination, depart_date),
    KEY checked_at (checked_at)
);
```

### Price Alerts Table
**Table**: `wp_srt_price_alerts`

```sql
CREATE TABLE wp_srt_price_alerts (
    id bigint(20) AUTO_INCREMENT PRIMARY KEY,
    user_id bigint(20) NOT NULL,
    event_id bigint(20) NOT NULL,
    leg_index int(11) NOT NULL,
    alert_type varchar(50) NOT NULL,
    threshold_price decimal(10,2),
    threshold_percent int(11),
    is_active tinyint(1) DEFAULT 1,
    last_triggered datetime,
    created_at datetime NOT NULL,
    KEY user_id (user_id),
    KEY event_id (event_id),
    KEY is_active (is_active)
);
```

## User Workflow

### Creating a Price Alert

1. User views event details (modal)
2. Sees unbooked flight with Track Price button
3. Clicks button → Price tracking modal opens
4. Selects alert type:
   - **Any Price Drop**: Alert on any decrease
   - **Percentage Drop**: Alert when price drops X%
   - **Good Deal Alert**: Algorithm determines good price
5. Enters threshold (if applicable)
6. Clicks "Create Alert"
7. Receives confirmation message

### Receiving Alerts

1. Cron runs 4x daily
2. Fetches current prices via Amadeus API
3. Compares with thresholds in database
4. If conditions met:
   - Email sent to user
   - Alert marked as triggered
   - Can create new alert after

## Configuration Steps

### 1. Get Amadeus API Credentials

1. Go to https://developers.amadeus.com/register
2. Create account
3. Create new app
4. Copy API Key (Client ID)
5. Copy API Secret (Client Secret)

**Free Tier**: 2,000 API calls/month

### 2. Configure Plugin

1. WordPress Admin → Settings → Regiment Tracker Settings
2. Scroll to "Flight Price Tracking API"
3. Enter API Key
4. Enter API Secret
5. Click "Save Settings"

### 3. Setup Server Cron

**Automated:**
```bash
cd /path/to/wordpress
./setup-cron.sh
```

**Manual:**
```bash
# Edit wp-config.php
define('DISABLE_WP_CRON', true);

# Add to crontab
crontab -e
0 0,6,12,18 * * * cd /path/to/wordpress && wp cron event run --due-now
```

### 4. Test

```bash
# Manually trigger price check
wp cron event run srt_check_flight_prices

# Check for errors
tail -f wp-content/debug.log
```

## API Usage Estimates

### Calculations

**Assumptions:**
- 30 unbooked flights per season
- 4 checks per day
- 60 days of tracking

**Daily**: 30 flights × 4 checks = 120 API calls/day

**Monthly**: 120 × 30 days = 3,600 API calls/month

**Conclusion**: Free tier (2,000/month) is insufficient. Need paid tier or reduce frequency.

### Optimization Options

1. **Reduce frequency**: 2x daily = 1,800/month (fits free tier)
2. **Filter by date**: Only check flights within 30 days = 50% reduction
3. **User-triggered**: Only check when alert created (manual)

## Testing Checklist

- [ ] API credentials save correctly
- [ ] Cron event scheduled (wp cron event list)
- [ ] Manual trigger works (wp cron event run srt_check_flight_prices)
- [ ] Prices recorded in database (wp_srt_price_history)
- [ ] Track Price button appears on unbooked flights
- [ ] Modal opens with correct route info
- [ ] Alert types show correct threshold fields
- [ ] Alert creation succeeds with success message
- [ ] Alert saved in database (wp_srt_price_alerts)
- [ ] Email sent when alert triggered
- [ ] Error handling works (invalid credentials, API down)

## Monitoring

### View Scheduled Events
```bash
wp cron event list
```

### Check Last Run
```bash
wp cron event list --format=table | grep srt_check_flight_prices
```

### View Recent Prices
```sql
SELECT 
    e.post_title,
    ph.origin,
    ph.destination,
    ph.depart_date,
    ph.price,
    ph.checked_at
FROM wp_srt_price_history ph
JOIN wp_posts e ON ph.event_id = e.ID
ORDER BY ph.checked_at DESC
LIMIT 20;
```

### View Active Alerts
```sql
SELECT 
    u.user_login,
    e.post_title,
    pa.alert_type,
    pa.threshold_price,
    pa.threshold_percent,
    pa.created_at
FROM wp_srt_price_alerts pa
JOIN wp_users u ON pa.user_id = u.ID
JOIN wp_posts e ON pa.event_id = e.ID
WHERE pa.is_active = 1
ORDER BY pa.created_at DESC;
```

### Check Debug Log
```bash
tail -100 wp-content/debug.log | grep "SRT"
```

## Troubleshooting

### No prices being recorded?

1. Check API credentials in settings
2. Verify cron is running: `wp cron event list`
3. Check debug log for errors
4. Test manually: `wp cron event run srt_check_flight_prices`

### API errors (401 Unauthorized)?

- Invalid API credentials
- Test with: https://developers.amadeus.com/self-service/apis-docs/guides/developer-guides/authentication/

### Rate limiting (429 Too Many Requests)?

- Free tier limit exceeded
- Reduce check frequency
- Upgrade to paid tier

### Cron not running?

- Check `DISABLE_WP_CRON` is set
- Verify crontab entry: `crontab -l`
- Check cron logs: `grep CRON /var/log/syslog`

### WP-CLI not found?

- Install WP-CLI: https://wp-cli.org/
- Use full path in crontab: `/usr/local/bin/wp`

## Future Enhancements

### Pending Features

1. **Price History Chart**: Chart.js visualization showing 30-day trends
2. **Admin Alerts Page**: View/manage all active alerts
3. **Email Templates**: Customizable alert email design
4. **Good Deal Algorithm**: Intelligent pricing threshold calculation
5. **Multi-API Support**: Fallback to SerpAPI or Skyscanner
6. **Batch API Calls**: Group similar flights to reduce API usage
7. **User Dashboard Widget**: Show price trends on dashboard
8. **SMS Alerts**: Optional Twilio integration
9. **Price Drop History**: Track all triggered alerts
10. **Performance Metrics**: API success rate, average prices, savings

### Code Architecture

- All price tracking in `includes/price-tracking.php`
- REST endpoints in `includes/rest.php`
- Settings in `includes/settings.php`
- Frontend JS in `assets/js/main.js`
- Database tables created on plugin activation
- Cron scheduled on plugin init

## Version History

**v0.3.14** (Current)
- ✅ Four times daily cron schedule
- ✅ Amadeus API integration
- ✅ Admin settings for API keys
- ✅ REST endpoint for creating alerts
- ✅ Frontend Track Price button and modal
- ✅ Server cron setup script
- ✅ Complete documentation

## Resources

- **Amadeus API Docs**: https://developers.amadeus.com/self-service
- **WP-CLI**: https://wp-cli.org/
- **WordPress Cron**: https://developer.wordpress.org/plugins/cron/
- **WP Crontrol Plugin**: https://wordpress.org/plugins/wp-crontrol/

## Support

For issues or questions:
1. Check `wp-content/debug.log`
2. Review `CRON_SETUP.md` documentation
3. Test with manual cron trigger
4. Verify API credentials at developers.amadeus.com
