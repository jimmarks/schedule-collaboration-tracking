#!/bin/bash
#
# Setup Server Cron for Summer Regiment Tracker
#
# This script sets up proper server cron for WordPress instead of relying on
# the unreliable WordPress Cron system (which only runs on pageviews).
#
# This enables:
#   - Price checking 4x daily (12am, 6am, 12pm, 6pm)
#   - Daily digest emails at 2am
#
# Usage:
#   1. Upload this script to your WordPress root directory
#   2. Run: chmod +x setup-cron.sh
#   3. Run: ./setup-cron.sh
#

set -e

echo "========================================="
echo "Summer Regiment Tracker - Cron Setup"
echo "========================================="
echo ""

# Check if we have WP-CLI
if ! command -v wp &> /dev/null; then
    echo "ERROR: WP-CLI is not installed."
    echo ""
    echo "WP-CLI is required for proper cron management."
    echo "Install it from: https://wp-cli.org/"
    echo ""
    exit 1
fi

# Get WordPress path
WP_PATH=$(pwd)
echo "WordPress Path: $WP_PATH"
echo ""

# Check if wp-config.php exists
if [ ! -f "$WP_PATH/wp-config.php" ]; then
    echo "ERROR: wp-config.php not found in current directory."
    echo "Please run this script from your WordPress root directory."
    exit 1
fi

echo "Step 1: Disable WordPress Cron (DISABLE_WP_CRON)"
echo "-----------------------------------------------"

# Check if DISABLE_WP_CRON is already set
if grep -q "DISABLE_WP_CRON" "$WP_PATH/wp-config.php"; then
    echo "✓ DISABLE_WP_CRON already exists in wp-config.php"
else
    # Add before "That's all, stop editing!"
    sed -i "/\/\* That's all, stop editing/i define('DISABLE_WP_CRON', true);" "$WP_PATH/wp-config.php"
    echo "✓ Added DISABLE_WP_CRON to wp-config.php"
fi
echo ""

echo "Step 2: Setup Cron Job (5 times per day)"
echo "-----------------------------------------------"

# Create the cron command
CRON_CMD="cd $WP_PATH && wp cron event run --due-now --path=$WP_PATH >> /dev/null 2>&1"

# Check current crontab
CURRENT_CRON=$(crontab -l 2>/dev/null || echo "")

if echo "$CURRENT_CRON" | grep -q "$WP_PATH.*wp cron event run"; then
    echo "✓ Cron job already exists"
else
    # Add new cron jobs - 5 times per day at 12am, 2am, 6am, 12pm, 6pm
    (crontab -l 2>/dev/null; echo "# Summer Regiment Tracker - WordPress Cron (5x daily)") | crontab -
    (crontab -l 2>/dev/null; echo "0 0,2,6,12,18 * * * $CRON_CMD") | crontab -
    echo "✓ Added cron job (runs at 12am, 2am, 6am, 12pm, 6pm)"
fi
echo ""

echo "Step 3: Verify Setup"
echo "-----------------------------------------------"
echo "Current crontab:"
crontab -l | grep -A 1 "Summer Regiment Tracker" || echo "(No SRT cron entries found)"
echo ""

echo "Testing WP-CLI cron command..."
if wp cron event list --path="$WP_PATH" &> /dev/null; then
    echo "✓ WP-CLI cron commands work correctly"
    echo ""
    echo "Upcoming scheduled events:"
    wp cron event list --path="$WP_PATH" | grep "srt_check_flight_prices" || echo "(No SRT events scheduled yet)"
else
    echo "✗ WP-CLI cron command failed - check permissions"
fi
echo ""

echo "========================================="
echo "Setup Complete!"
echo "========================================="
echo ""
echo "Automated tasks will now run:"
echo ""
echo "Price Checking - 4 times per day:"
echo "  - 12:00 AM (midnight)"
echo "  - 6:00 AM"
echo "  - 12:00 PM (noon)"
echo "  - 6:00 PM"
echo ""
echo "Daily Digest Emails - Once per day:"
echo "  - 2:00 AM (sends to users with active price alerts)"
echo ""
echo "Server cron runs 5 times daily at: 12am, 2am, 6am, 12pm, 6pm"
echo "This ensures both price checks and digest emails run on schedule."
echo ""
echo "To manually trigger a price check:"
echo "  cd $WP_PATH"
echo "  wp cron event run srt_check_flight_prices"
echo ""
echo "To view scheduled events:"
echo "  wp cron event list"
echo ""
echo "IMPORTANT: Make sure your SerpAPI key"
echo "is configured in Settings > Regiment Tracker Settings"
echo ""
