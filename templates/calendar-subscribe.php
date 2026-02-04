<?php
/**
 * Template: Calendar Subscription Instructions
 *
 * @package Summer_Regiment_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('srt_settings', array());
$requires_auth = $settings['ical_require_auth'] ?? false;
?>

<div class="srt-container srt-subscribe-page">
    <h2><?php esc_html_e('Subscribe to Schedule', 'schedule-collaboration-tracking'); ?></h2>
    
    <p><?php esc_html_e('Subscribe to the schedule in your calendar app to automatically receive updates when events are added or changed.', 'schedule-collaboration-tracking'); ?></p>
    
    <?php if ($requires_auth && empty($feed_url)) : ?>
        <div class="srt-notice srt-notice-info">
            <p><strong><?php esc_html_e('Authentication Required', 'schedule-collaboration-tracking'); ?></strong></p>
            <p><?php esc_html_e('This calendar requires an authentication token. Please contact your administrator for a calendar subscription URL.', 'schedule-collaboration-tracking'); ?></p>
        </div>
    <?php else : ?>
        
        <div class="srt-subscribe-url-box">
            <label><strong><?php esc_html_e('Calendar URL:', 'schedule-collaboration-tracking'); ?></strong></label>
            <div class="srt-url-copy">
                <input type="text" readonly value="<?php echo esc_attr($feed_url); ?>" id="srt-calendar-url" onclick="this.select();">
                <button type="button" class="button button-primary" onclick="srtCopyUrl()"><?php esc_html_e('Copy URL', 'schedule-collaboration-tracking'); ?></button>
            </div>
        </div>
        
        <div class="srt-instructions">
            <h3><?php esc_html_e('How to Subscribe:', 'schedule-collaboration-tracking'); ?></h3>
            
            <!-- iOS / iPhone / iPad -->
            <details class="srt-device-instructions">
                <summary><strong>📱 iPhone / iPad (Apple Calendar)</strong></summary>
                <ol>
                    <li><?php esc_html_e('Copy the calendar URL above', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Open the Settings app', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Tap "Calendar" → "Accounts" → "Add Account"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Tap "Other" → "Add Subscribed Calendar"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Paste the calendar URL', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Tap "Next" → "Save"', 'schedule-collaboration-tracking'); ?></li>
                </ol>
                <p class="srt-note"><?php esc_html_e('The calendar will now appear in your Calendar app and sync automatically.', 'schedule-collaboration-tracking'); ?></p>
            </details>
            
            <!-- Android / Google Calendar -->
            <details class="srt-device-instructions">
                <summary><strong>📱 Android (Google Calendar)</strong></summary>
                <ol>
                    <li><?php esc_html_e('Copy the calendar URL above', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Open Google Calendar on your computer (calendar.google.com)', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Click the "+" next to "Other calendars"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Select "From URL"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Paste the calendar URL', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Click "Add calendar"', 'schedule-collaboration-tracking'); ?></li>
                </ol>
                <p class="srt-note"><?php esc_html_e('The calendar will sync to all devices signed into your Google account.', 'schedule-collaboration-tracking'); ?></p>
            </details>
            
            <!-- macOS Calendar -->
            <details class="srt-device-instructions">
                <summary><strong>💻 Mac (Calendar App)</strong></summary>
                <ol>
                    <li><?php esc_html_e('Copy the calendar URL above', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Open Calendar app', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Go to File → New Calendar Subscription', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Paste the calendar URL', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Click "Subscribe"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Configure refresh frequency and click "OK"', 'schedule-collaboration-tracking'); ?></li>
                </ol>
                <p class="srt-note"><?php esc_html_e('Calendar will sync with iCloud if enabled.', 'schedule-collaboration-tracking'); ?></p>
            </details>
            
            <!-- Outlook -->
            <details class="srt-device-instructions">
                <summary><strong>💼 Outlook (Windows / macOS / Web)</strong></summary>
                <ol>
                    <li><?php esc_html_e('Copy the calendar URL above', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('In Outlook, go to Calendar view', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Right-click "My Calendars" → "Add Calendar" → "From Internet"', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Paste the calendar URL', 'schedule-collaboration-tracking'); ?></li>
                    <li><?php esc_html_e('Click "OK"', 'schedule-collaboration-tracking'); ?></li>
                </ol>
                <p class="srt-note"><?php esc_html_e('Outlook checks for updates every few hours.', 'schedule-collaboration-tracking'); ?></p>
            </details>
        </div>
        
        <div class="srt-faq">
            <h3><?php esc_html_e('Frequently Asked Questions', 'schedule-collaboration-tracking'); ?></h3>
            
            <details>
                <summary><?php esc_html_e('How often does the calendar update?', 'schedule-collaboration-tracking'); ?></summary>
                <p><?php esc_html_e('Most calendar apps check for updates every few hours to once per day. Changes to the schedule will appear automatically on your device.', 'schedule-collaboration-tracking'); ?></p>
            </details>
            
            <details>
                <summary><?php esc_html_e('Can I edit events in my calendar app?', 'schedule-collaboration-tracking'); ?></summary>
                <p><?php esc_html_e('No, subscribed calendars are read-only. Any changes must be made on the website. Your edits would be overwritten on the next sync.', 'schedule-collaboration-tracking'); ?></p>
            </details>
            
            <details>
                <summary><?php esc_html_e('How do I unsubscribe?', 'schedule-collaboration-tracking'); ?></summary>
                <p><?php esc_html_e('In your calendar app, find the "Schedule" calendar and delete/remove it from your subscriptions.', 'schedule-collaboration-tracking'); ?></p>
            </details>
            
            <details>
                <summary><?php esc_html_e('Will this use my phone data?', 'schedule-collaboration-tracking'); ?></summary>
                <p><?php esc_html_e('Calendar syncing uses very little data (typically a few KB per update). It syncs in the background when you have an internet connection.', 'schedule-collaboration-tracking'); ?></p>
            </details>
        </div>
        
    <?php endif; ?>
</div>

<style>
.srt-subscribe-page {
    max-width: 800px;
}

.srt-subscribe-url-box {
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.srt-subscribe-url-box label {
    display: block;
    margin-bottom: 10px;
}

.srt-url-copy {
    display: flex;
    gap: 10px;
}

.srt-url-copy input {
    flex: 1;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
    font-size: 14px;
}

.srt-device-instructions {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 10px;
}

.srt-device-instructions summary {
    cursor: pointer;
    padding: 5px;
    font-size: 16px;
}

.srt-device-instructions summary:hover {
    background: #f9f9f9;
}

.srt-device-instructions ol {
    margin: 15px 0 10px 20px;
}

.srt-device-instructions li {
    margin: 8px 0;
}

.srt-note {
    background: #e7f5ff;
    border-left: 4px solid #2196F3;
    padding: 10px 15px;
    margin: 10px 0;
    font-style: italic;
}

.srt-faq {
    margin-top: 30px;
}

.srt-faq details {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 10px;
}

.srt-faq summary {
    cursor: pointer;
    font-weight: bold;
    padding: 5px;
}

.srt-faq summary:hover {
    background: #f9f9f9;
}

.srt-faq p {
    margin: 10px 0 0 0;
    padding-left: 10px;
}

.srt-notice {
    padding: 15px;
    border-radius: 4px;
    margin: 20px 0;
}

.srt-notice-info {
    background: #e7f5ff;
    border-left: 4px solid #2196F3;
}
</style>

<script>
function srtCopyUrl() {
    var input = document.getElementById('srt-calendar-url');
    input.select();
    input.setSelectionRange(0, 99999); // For mobile
    
    navigator.clipboard.writeText(input.value).then(function() {
        var btn = event.target;
        var originalText = btn.textContent;
        btn.textContent = '<?php esc_js(__('Copied!', 'schedule-collaboration-tracking')); ?>';
        btn.style.background = '#4CAF50';
        
        setTimeout(function() {
            btn.textContent = originalText;
            btn.style.background = '';
        }, 2000);
    });
}
</script>
