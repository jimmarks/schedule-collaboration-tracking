# Server Cron Setup Guide

This guide explains how to set up proper server cron for automated flight price checking and daily digest emails.

## Why Server Cron?

WordPress Cron (`wp-cron.php`) only runs when someone visits your site. For a regiment tracker with automated price checking and daily emails, this is unreliable:

- **Problem**: If no one visits at 2 AM, digest emails don't send at 2 AM
- **Solution**: Real server cron runs on a schedule regardless of traffic

## Quick Setup (Recommended)

We've provided an automated setup script:

```bash
cd /path/to/wordpress
bash wp-content/plugins/schedule-collaboration-tracking/setup-cron.sh
```

This script will:
1. Add `DISABLE_WP_CRON` to `wp-config.php`
2. Configure crontab to run 5 times daily (12am, 2am, 6am, 12pm, 6pm)
3. Verify WP-CLI is working correctly

## Manual Setup

If you prefer manual configuration:

### Step 1: Disable WordPress Cron

Edit `wp-config.php` and add before "That's all, stop editing!":

```php
define('DISABLE_WP_CRON', true);
```

### Step 2: Add Crontab Entry

Run `crontab -e` and add:

```bash
# Summer Regiment Tracker - WordPress Cron (5x daily)
0 0,2,6,12,18 * * * cd /path/to/wordpress && wp cron event run --due-now >> /dev/null 2>&1
```

Replace `/path/to/wordpress` with your actual WordPress path.

**Schedule Breakdown:**
- `0 0,2,6,12,18 * * *` = Runs at hours 0, 2, 6, 12, 18 (12am, 2am, 6am, noon, 6pm)
- `cd /path/to/wordpress` = Navigate to WordPress directory
- `wp cron event run --due-now` = Run all due WordPress cron events

**What runs when:**
- **12:00 AM** - Price check #1
- **2:00 AM** - Price check #2 + Daily digest emails
- **6:00 AM** - Price check #3
- **12:00 PM** - Price check #4
- **6:00 PM** - Catch any pending WordPress cron tasks

### Step 3: Verify Setup

Test that WP-CLI works:

```bash
cd /path/to/wordpress
wp cron event list
```

You should see:
- `srt_check_flight_prices` scheduled with `fourtimesdaily` recurrence
- `srt_daily_digest` scheduled with `daily_2am` recurrence

## Testing

### Test Price Check

To manually trigger a price check immediately:

```bash
cd /path/to/wordpress
wp cron event run srt_check_flight_prices
```

### Test Daily Digest

To manually trigger the digest email:

```bash
cd /path/to/wordpress
wp cron event run srt_daily_digest
```

Check logs for API activity:
- Success: Prices recorded in database
- Errors: Check `wp-content/debug.log` (if `WP_DEBUG_LOG` is enabled)

## Requirements

### WP-CLI Installation

**Most Hosting Providers:**
WP-CLI is often pre-installed. Check with:
```bash
wp --version
```

**If Not Installed:**

1. Download:
   ```bash
   curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
   ```

2. Make executable:
   ```bash
   chmod +x wp-cli.phar
   sudo mv wp-cli.phar /usr/local/bin/wp
   ```

3. Test:
   ```bash
   wp --info
   ```

See full installation guide: https://wp-cli.org/

### Server Requirements

- **Crontab Access**: Most shared hosting provides this via cPanel
- **PHP CLI**: Server must have PHP available from command line
- **File Permissions**: WordPress files must be readable by cron user

## Hosting-Specific Notes

### cPanel / Shared Hosting

1. Go to cPanel → Cron Jobs
2. Add new cron job with schedule: `0 0,2,6,12,18 * * *`
3. Command: `/usr/local/bin/wp cron event run --due-now --path=/home/username/public_html`
4. Adjust path to match your setup

### Managed WordPress (WP Engine, Kinsta, etc.)

These often disable `wp-cron.php` by default and run server cron automatically. Contact support to:
1. Confirm cron is enabled
2. Request custom schedule (5x daily at 12am, 2am, 6am, 12pm, 6pm)
3. Verify they're running `wp cron event run` or similar

### VPS / Dedicated Server

Use the automated script or manual crontab method. You have full control.

## API Configuration

After cron is set up, configure SerpAPI:

1. Register at https://serpapi.com/users/sign_up
2. Get your API key from https://serpapi.com/manage-api-key
3. In WordPress: **Events → Cron Setup** or **Settings → Regiment Tracker Settings**
4. Enter your API key under "Flight Price Tracking API"

**Free Tier**: 100 searches/month
**Paid Plans**: $75/month for 5,000 searches ($0.015 each)

With 4 price checks per day and 30 flights, that's 120 calls/day = 3,600/month. You'll need a paid plan ($75/month covers 5,000 searches).

## Troubleshooting

### Cron not running?

Check system cron logs:
```bash
# Ubuntu/Debian
sudo grep CRON /var/log/syslog

# CentOS/RHEL
sudo grep CRON /var/log/cron
```

### WP-CLI not found?

Verify path in crontab:
```bash
which wp
```

Use full path in cron job: `/usr/local/bin/wp` instead of just `wp`

### Permission errors?

Ensure cron runs as the correct user:
```bash
# Run as www-data (web server user)
sudo -u www-data wp cron event list --path=/path/to/wordpress
```

### API errors in logs?

Check `wp-content/debug.log`:
- `401 Unauthorized` or `Invalid API key`: Check your SerpAPI key
- `429 Too Many Requests`: Rate limit exceeded (upgrade plan or reduce frequency)
- `400 Bad Request`: Invalid airport codes or date format

Enable debug logging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## Monitoring

### View Scheduled Events

```bash
wp cron event list
```

Shows all scheduled WordPress events including `srt_check_flight_prices`.

### View Price History

Check database table `wp_srt_price_history`:
```sql
SELECT * FROM wp_srt_price_history 
ORDER BY checked_at DESC 
LIMIT 20;
```

### View Active Alerts

Check database table `wp_srt_price_alerts`:
```sql
SELECT * FROM wp_srt_price_alerts 
WHERE is_active = 1;
```

## Performance Tuning

### Reduce API Calls

1. **Check less frequently**: Change to 2x daily
   ```bash
   0 2,18 * * *  # Only 2am (for digest) and 6pm
   ```

2. **Filter events**: Only check events within 60 days
   (Already implemented in `check_all_prices()`)

### Rate Limiting

The plugin includes 1-second sleep between API calls to avoid throttling. To adjust:

Edit `includes/price-tracking.php`:
```php
// Increase from 1 to 2 seconds
sleep(2);
```

## Alternative: WP Control Plugin

For easier management, install **WP Control** plugin:
- GUI for cron events
- View/edit/run scheduled tasks
- No command-line needed

Install: `wp plugin install wp-crontrol --activate`

## Support

If you encounter issues:
1. Check WordPress `debug.log`
2. Test API credentials manually
3. Verify cron is running with `crontab -l`
4. Check `wp cron event list` output

For hosting-specific help, contact your hosting provider's support team.
