<?php
/**
 * Test Daily Digest
 * 
 * Run this file from command line to manually trigger the daily digest:
 * wp eval-file wp-content/plugins/schedule-collaboration-tracking/test-digest.php
 * 
 * Or via browser (be sure to protect this file in production!)
 */

// Load WordPress if not already loaded (for browser access)
if (!defined('ABSPATH')) {
    // Try to find wp-load.php
    $wp_load = dirname(__FILE__) . '/../../../wp-load.php';
    if (file_exists($wp_load)) {
        require_once($wp_load);
    } else {
        die('Could not find WordPress. Make sure this file is in wp-content/plugins/schedule-collaboration-tracking/');
    }
}

// Check if running from CLI or browser
if (php_sapi_name() !== 'cli') {
    // Browser - require admin
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    echo '<pre>';
}

echo "=== Testing Daily Digest System ===\n\n";

// Check if cron is scheduled
$timestamp = wp_next_scheduled('srt_daily_digest');
if ($timestamp) {
    echo "✅ Daily digest cron is scheduled\n";
    echo "   Next run: " . date('Y-m-d H:i:s', $timestamp) . "\n\n";
} else {
    echo "❌ Daily digest cron is NOT scheduled\n";
    echo "   Scheduling now...\n";
    $tomorrow_2am = strtotime('tomorrow 2:00am');
    wp_schedule_event($tomorrow_2am, 'daily_2am', 'srt_daily_digest');
    echo "   Scheduled for: " . date('Y-m-d H:i:s', $tomorrow_2am) . "\n\n";
}

// Check for custom schedule
$schedules = wp_get_schedules();
if (isset($schedules['daily_2am'])) {
    echo "✅ Custom schedule 'daily_2am' registered\n";
    echo "   Interval: " . $schedules['daily_2am']['interval'] . " seconds (24 hours)\n\n";
} else {
    echo "❌ Custom schedule 'daily_2am' not found\n\n";
}

// Find users with digest alerts
global $wpdb;
$alerts_table = $wpdb->prefix . 'ftt_price_alerts';

$digest_users = $wpdb->get_results(
    "SELECT DISTINCT user_id, COUNT(*) as alert_count 
    FROM $alerts_table 
    WHERE alert_type = 'daily_digest' AND is_active = 1 
    GROUP BY user_id"
);

if (empty($digest_users)) {
    echo "⚠️  No users have active daily digest alerts\n";
    echo "   Create a digest alert first to test email generation.\n\n";
} else {
    echo "✅ Found " . count($digest_users) . " user(s) with digest alerts:\n";
    foreach ($digest_users as $user_data) {
        $user = get_userdata($user_data->user_id);
        echo "   - {$user->display_name} ({$user->user_email}) - {$user_data->alert_count} alert(s)\n";
    }
    echo "\n";
}

// Ask if user wants to trigger manually
if (php_sapi_name() === 'cli') {
    echo "Trigger digest now? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $response = trim(strtolower($line));
    fclose($handle);
} else {
    // Browser - check for trigger param
    $response = isset($_GET['trigger']) ? 'y' : 'n';
    if ($response === 'n') {
        echo '<a href="?trigger=1">Click here to trigger digest now</a>' . "\n\n";
    }
}

if ($response === 'y') {
    echo "\n=== Triggering Daily Digest ===\n\n";
    
    // Manually trigger the digest
    do_action('srt_daily_digest');
    
    echo "✅ Digest processing complete\n";
    echo "   Check your email and WordPress error logs for results\n";
    echo "   Error log location: " . ini_get('error_log') . "\n";
} else {
    echo "Digest not triggered.\n";
}

echo "\n=== Test Complete ===\n";

if (php_sapi_name() !== 'cli') {
    echo '</pre>';
}
