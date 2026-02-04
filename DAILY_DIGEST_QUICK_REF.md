# Daily Price Digest - Quick Reference

## What It Does
Sends users a consolidated email every day at 2am with all tracked flight prices, trends, and recommendations.

## User Setup (3 Steps)
1. Go to Member Dashboard
2. Find a flight and click "Track Price"
3. Select **"📧 Daily Price Digest (2am email)"**

## Email Categories

| Category | When | Action | Color |
|----------|------|--------|-------|
| ✅ **Good Deals** | Price 15%+ below average | Book now! | Green |
| ⚠️ **Trending Up** | Price rising + <30 days | Book soon | Red |
| ⏳ **Trending Down** | Price falling + >30 days | Wait & watch | Yellow |
| ➡️ **Stable** | Price steady | Monitor | Blue |

## Testing

### Quick Test
```bash
wp eval-file test-digest.php
```

### Manual Trigger
```php
do_action('srt_daily_digest');
```

### Check Schedule
```php
$next = wp_next_scheduled('srt_daily_digest');
echo date('Y-m-d H:i:s', $next);
```

## What's Included in Email

Each flight shows:
- Event title and route
- Current price with trend icon (📈📉➡️)
- 7-day change ($ and %)
- Days until departure
- Min/Avg/Max prices
- Color-coded recommendation

## Technical Details

- **Cron**: `srt_daily_digest` hook, runs daily at 2am
- **Schedule**: `daily_2am` (86400 seconds)
- **Alert Type**: `daily_digest` (no threshold required)
- **Email**: HTML via `wp_mail()`
- **Logging**: Error log with `[SRT Daily Digest]` prefix

## Troubleshooting

### No email received?
1. Check cron is scheduled: `wp cron event list`
2. Verify active alerts: `SELECT * FROM wp_srt_price_alerts WHERE alert_type='daily_digest' AND is_active=1`
3. Check error log: `grep "SRT Daily Digest" error.log`
4. Manually trigger to test

### Empty email?
- Ensure price history exists (prices checked at least once)
- Verify airport codes are saved in flight legs
- Check event is accessible to user

## Files Modified (v0.9.71)

- `includes/price-tracking.php` - Core digest logic (+280 lines)
- `includes/rest.php` - Added 'daily_digest' to enum
- `assets/js/main.js` - Added digest option to UI
- `schedule-collaboration-tracking.php` - Version bump to 0.9.71
- `test-digest.php` - NEW testing utility
- `DAILY_DIGEST.md` - NEW comprehensive docs

## Next Steps

1. Test digest with a real user account
2. Create actual price history data (wait for cron or manual check)
3. Trigger digest and verify email
4. Monitor error logs for issues

For full documentation, see [DAILY_DIGEST.md](DAILY_DIGEST.md)
