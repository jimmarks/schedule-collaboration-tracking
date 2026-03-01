<?php
/**
 * Cron Setup Admin Page
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FTT_Cron_Setup {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_page'));
        add_action('admin_notices', array(__CLASS__, 'cron_status_notice'));
        add_action('admin_post_srt_setup_cron', array(__CLASS__, 'handle_setup_cron'));
        add_action('admin_post_srt_manual_price_check', array(__CLASS__, 'handle_manual_price_check'));
    }
    
    /**
     * Add admin menu page
     */
    public static function add_admin_page() {
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            'Cron Setup',
            'Cron Setup',
            'manage_options',
            'ftt-cron-setup',
            array(__CLASS__, 'render_page')
        );
        
        // Add documentation page
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            'Cron Documentation',
            'Cron Docs',
            'manage_options',
            'ftt-cron-docs',
            array(__CLASS__, 'render_docs_page')
        );
    }
    
    /**
     * Check cron status
     */
    public static function get_cron_status() {
        $status = array(
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'wp_cli_available' => false,
            'cron_scheduled' => false,
            'digest_scheduled' => false,
            'price_tracking_enabled' => false,
            'last_run' => get_option('srt_cron_last_run'),
            'last_run_success' => get_option('srt_cron_last_success'),
            'total_runs' => get_option('srt_cron_total_runs', 0),
        );
        
        // Check if WP-CLI is available
        $wp_cli_check = shell_exec('which wp 2>/dev/null');
        $status['wp_cli_available'] = !empty($wp_cli_check);
        
        // Check if our cron events are scheduled
        $timestamp = wp_next_scheduled('srt_check_flight_prices');
        $status['cron_scheduled'] = ($timestamp !== false);
        $status['next_run'] = $timestamp;
        
        $digest_timestamp = wp_next_scheduled('srt_daily_digest');
        $status['digest_scheduled'] = ($digest_timestamp !== false);
        $status['digest_next_run'] = $digest_timestamp;
        
        // Check if SerpAPI key is configured
        $settings = get_option('ftt_settings', array());
        $status['price_tracking_enabled'] = !empty($settings['serpapi_api_key']);
        
        return $status;
    }
    

    
    /**
     * Handle manual price check
     */
    public static function handle_manual_price_check() {
        check_admin_referer('ftt_manual_check', 'srt_check_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Trigger the price check with manual flag
        FTT_Price_Tracking::check_all_prices('manual');
        
        wp_redirect(add_query_arg(array(
            'page' => 'ftt-cron-setup',
            'manual_check' => 'success',
        ), admin_url('edit.php?post_type=ftt_event')));
        exit;
    }
    
    /**
     * Show admin notice about cron status
     */
    public static function cron_status_notice() {
        $screen = get_current_screen();
        if ($screen->post_type !== 'ftt_event' && $screen->id !== 'ftt_event_page_ftt-cron-setup') {
            return;
        }
        
        $status = self::get_cron_status();
        
        // If price tracking is enabled but server cron is not set up
        if ($status['price_tracking_enabled'] && !$status['wp_cron_disabled']) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong>Price Tracking Setup:</strong> 
                    For reliable automated price checking, we recommend setting up server cron. 
                    <a href="<?php echo admin_url('edit.php?post_type=ftt_event&page=ftt-cron-setup'); ?>">
                        Click here to set up server cron
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Render admin page
     */
    public static function render_page() {
        $status = self::get_cron_status();
        $script_path = FTT_PLUGIN_DIR . 'setup-cron.sh';
        $wp_path = ABSPATH;
        
        ?>
        <div class="wrap">
            <h1>Cron Setup & Monitoring</h1>
            
            <?php if (isset($_GET['manual_check']) && $_GET['manual_check'] === 'success'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>✓ Manual price check completed successfully!</strong></p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Current Status</h2>
                <table class="widefat">
                    <tr>
                        <td><strong>WordPress Cron Disabled:</strong></td>
                        <td>
                            <?php if ($status['wp_cron_disabled']): ?>
                                <span style="color: green;">✓ Yes (Good - using server cron)</span>
                            <?php else: ?>
                                <span style="color: orange;">⚠ No (WordPress cron is active)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>WP-CLI Available:</strong></td>
                        <td>
                            <?php if ($status['wp_cli_available']): ?>
                                <span style="color: green;">✓ Yes</span>
                            <?php else: ?>
                                <span style="color: red;">✗ No</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Price Check Scheduled:</strong></td>
                        <td>
                            <?php if ($status['cron_scheduled']): ?>
                                <span style="color: green;">✓ Yes (next run: <?php echo date('Y-m-d H:i:s', $status['next_run']); ?>)</span>
                            <?php else: ?>
                                <span style="color: red;">✗ Not scheduled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Daily Digest Scheduled:</strong></td>
                        <td>
                            <?php if ($status['digest_scheduled']): ?>
                                <span style="color: green;">✓ Yes (next run: <?php echo date('Y-m-d H:i:s', $status['digest_next_run']); ?>)</span>
                            <?php else: ?>
                                <span style="color: red;">✗ Not scheduled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>SerpAPI Configured:</strong></td>
                        <td>
                            <?php if ($status['price_tracking_enabled']): ?>
                                <span style="color: green;">✓ Yes</span>
                            <?php else: ?>
                                <span style="color: orange;">⚠ No API key set</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Last Run:</strong></td>
                        <td>
                            <?php if ($status['last_run']): ?>
                                <?php echo esc_html($status['last_run']); ?>
                            <?php else: ?>
                                <span style="color: #999;">Never</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Last Success:</strong></td>
                        <td>
                            <?php if ($status['last_run_success']): ?>
                                <span style="color: green;">✓ <?php echo esc_html($status['last_run_success']); ?></span>
                            <?php else: ?>
                                <span style="color: #999;">No successful runs yet</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Total Runs:</strong></td>
                        <td><?php echo intval($status['total_runs']); ?></td>
                    </tr>
                    <?php
                    $last_stats = get_option('srt_cron_last_stats');
                    if ($last_stats):
                    ?>
                    <tr>
                        <td><strong>Last Run Stats:</strong></td>
                        <td>
                            ✈️ <?php echo intval($last_stats['flights_checked']); ?> flights checked<br>
                            💾 <?php echo intval($last_stats['prices_recorded']); ?> prices recorded<br>
                            ⏱️ <?php echo floatval($last_stats['duration']); ?>s duration
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <div class="card">
                <h2>Manual Testing</h2>
                <p>Trigger a price check right now to test the system:</p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top: 15px;">
                    <?php wp_nonce_field('ftt_manual_check', 'srt_check_nonce'); ?>
                    <input type="hidden" name="action" value="ftt_manual_price_check">
                    <button type="submit" class="button button-primary button-large">
                        🔍 Run Price Check Now
                    </button>
                </form>
                <p style="color: #666; font-size: 0.9em; margin-top: 10px;">
                    <em>This will check all upcoming unbooked flights and trigger any price alerts.</em>
                </p>
            </div>
            
            <div class="card">
                <h2>Recent Activity Log</h2>
                <?php
                $log = get_option('srt_cron_log', array());
                if (!empty($log)):
                    $log = array_reverse($log); // Show newest first
                ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Type</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($log as $entry): ?>
                                <tr>
                                    <td><?php echo esc_html($entry['timestamp']); ?></td>
                                    <td>
                                        <?php if ($entry['type'] === 'manual'): ?>
                                            <span style="color: #0073aa;">👤 Manual</span>
                                        <?php else: ?>
                                            <span style="color: #46b450;">⏰ Scheduled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo isset($entry['user']) ? esc_html($entry['user']) : '<span style="color: #999;">—</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #666;">No activity logged yet. Cron has not run or manual check has not been triggered.</p>
                <?php endif; ?>
            </div>
            
            <?php if ($status['wp_cli_available'] && file_exists($script_path)): ?>
                <div class="card">
                    <h2>Automated Setup</h2>
                    <p>Click the button below to automatically configure server cron for price tracking and daily digest emails.</p>
                    <p><strong>This will:</strong></p>
                    <ul>
                        <li>Add <code>DISABLE_WP_CRON</code> to wp-config.php</li>
                        <li>Schedule cron to run 5 times daily (12am, 2am, 6am, 12pm, 6pm)</li>
                        <li>This enables both price checking (4x daily) and digest emails (at 2am)</li>
                        <li>Verify the setup is working</li>
                    </ul>
                    
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('ftt_setup_cron', 'srt_cron_nonce'); ?>
                        <input type="hidden" name="action" value="ftt_setup_cron">
                        <button type="submit" class="button button-primary button-large">
                            🚀 Run Automated Setup
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Manual Setup Instructions</h2>
                
                <?php if (file_exists($script_path)): ?>
                    <h3>Option 1: Run Setup Script</h3>
                    <p>SSH into your server and run:</p>
                    <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">cd <?php echo esc_html($wp_path); ?>
bash wp-content/plugins/summer-regiment-tracker/setup-cron.sh</pre>
                <?php endif; ?>
                
                <h3>Option 2: Manual Configuration</h3>
                <p><strong>Step 1:</strong> Edit <code>wp-config.php</code> and add before "That's all, stop editing!":</p>
                <pre style="background: #f0f0f0; padding: 10px;">define('DISABLE_WP_CRON', true);</pre>
                
                <p><strong>Step 2:</strong> Add to your server's crontab (<code>crontab -e</code>):</p>
                <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">0 0,2,6,12,18 * * * cd <?php echo esc_html($wp_path); ?> && wp cron event run --due-now >> /dev/null 2>&1</pre>
                
                <p><strong>Step 3:</strong> Verify setup:</p>
                <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">wp cron event list</pre>
                
                <p>
                    <a href="<?php echo admin_url('edit.php?post_type=ftt_event&page=ftt-cron-docs'); ?>" class="button">
                        📖 View Full Documentation
                    </a>
                </p>
            </div>
            
            <div class="card">
                <h2>Testing Cron Events</h2>
                <p>To manually trigger a price check right now:</p>
                <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">cd <?php echo esc_html($wp_path); ?>
wp cron event run srt_check_flight_prices</pre>
                
                <p>To manually trigger the daily digest email:</p>
                <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">cd <?php echo esc_html($wp_path); ?>
wp cron event run srt_daily_digest</pre>
                
                <p>Check price results in the database table <code>wp_ftt_price_history</code>.</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle automated cron setup
     */
    public static function handle_setup_cron() {
        // Verify nonce and permissions
        if (!isset($_POST['srt_cron_nonce']) || !wp_verify_nonce($_POST['srt_cron_nonce'], 'ftt_setup_cron')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $script_path = FTT_PLUGIN_DIR . 'setup-cron.sh';
        
        if (!file_exists($script_path)) {
            wp_die('Setup script not found');
        }
        
        // Execute the setup script
        $output = array();
        $return_var = 0;
        
        // Change to WordPress directory and run script
        $command = sprintf(
            'cd %s && bash %s 2>&1',
            escapeshellarg(ABSPATH),
            escapeshellarg($script_path)
        );
        
        exec($command, $output, $return_var);
        
        // Redirect back with result
        $redirect_url = add_query_arg(
            array(
                'page' => 'ftt-cron-setup',
                'setup_result' => $return_var === 0 ? 'success' : 'error',
                'output' => urlencode(implode("\n", $output))
            ),
            admin_url('edit.php?post_type=ftt_event')
        );
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Render documentation page
     */
    public static function render_docs_page() {
        ?>
        <div class="wrap">
            <h1>🕐 Cron Setup Documentation</h1>
            
            <div class="card">
                <h2>Why Server Cron?</h2>
                <p>WordPress Cron (<code>wp-cron.php</code>) only runs when someone visits your site. For automated price checking and daily digest emails, this is unreliable:</p>
                <ul>
                    <li><strong>Problem:</strong> If no one visits at 2 AM, digest emails don't send at 2 AM</li>
                    <li><strong>Solution:</strong> Real server cron runs on a schedule regardless of traffic</li>
                </ul>
            </div>
            
            <div class="card">
                <h2>Quick Setup (Recommended)</h2>
                <p>We've provided an automated setup script:</p>
                <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">cd <?php echo ABSPATH; ?>
bash wp-content/plugins/schedule-collaboration-tracking/setup-cron.sh</pre>
                
                <p>This script will:</p>
                <ol>
                    <li>Add <code>DISABLE_WP_CRON</code> to <code>wp-config.php</code></li>
                    <li>Configure crontab to run 5 times daily (12am, 2am, 6am, 12pm, 6pm)</li>
                    <li>Verify WP-CLI is working correctly</li>
                </ol>
            </div>
            
            <div class="card">
                <h2>What Runs When</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Task</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>12:00 AM</strong></td>
                            <td>Price Check #1</td>
                            <td>First price check of the day</td>
                        </tr>
                        <tr>
                            <td><strong>2:00 AM</strong></td>
                            <td>Price Check #2 + <strong>Daily Digest</strong></td>
                            <td>Morning price check + send digest emails to users</td>
                        </tr>
                        <tr>
                            <td><strong>6:00 AM</strong></td>
                            <td>Price Check #3</td>
                            <td>Morning price check</td>
                        </tr>
                        <tr>
                            <td><strong>12:00 PM</strong></td>
                            <td>Price Check #4</td>
                            <td>Afternoon price check</td>
                        </tr>
                        <tr>
                            <td><strong>6:00 PM</strong></td>
                            <td>Cron Runner</td>
                            <td>Catch any pending WordPress cron tasks</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <h2>Manual Setup Instructions</h2>
                
                <h3>Step 1: Disable WordPress Cron</h3>
                <p>Edit <code>wp-config.php</code> and add before "That's all, stop editing!":</p>
                <pre style="background: #f0f0f0; padding: 10px;">define('DISABLE_WP_CRON', true);</pre>
                
                <h3>Step 2: Add Crontab Entry</h3>
                <p>Run <code>crontab -e</code> and add:</p>
                <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;"># Family Travel Tracker - WordPress Cron (5x daily)
0 0,2,6,12,18 * * * cd <?php echo ABSPATH; ?> && wp cron event run --due-now >> /dev/null 2>&1</pre>
                
                <p><strong>Schedule Breakdown:</strong></p>
                <ul>
                    <li><code>0 0,2,6,12,18 * * *</code> = Runs at hours 0, 2, 6, 12, 18</li>
                    <li><code>cd <?php echo ABSPATH; ?></code> = Navigate to WordPress directory</li>
                    <li><code>wp cron event run --due-now</code> = Run all due WordPress cron events</li>
                </ul>
                
                <h3>Step 3: Verify Setup</h3>
                <p>Test that WP-CLI works:</p>
                <pre style="background: #f0f0f0; padding: 10px;">cd <?php echo ABSPATH; ?>
wp cron event list</pre>
                
                <p>You should see:</p>
                <ul>
                    <li><code>srt_check_flight_prices</code> scheduled with <code>fourtimesdaily</code> recurrence</li>
                    <li><code>srt_daily_digest</code> scheduled with <code>daily_2am</code> recurrence</li>
                </ul>
            </div>
            
            <div class="card">
                <h2>Testing</h2>
                
                <h3>Test Price Check</h3>
                <pre style="background: #f0f0f0; padding: 10px;">cd <?php echo ABSPATH; ?>
wp cron event run srt_check_flight_prices</pre>
                
                <h3>Test Daily Digest</h3>
                <pre style="background: #f0f0f0; padding: 10px;">cd <?php echo ABSPATH; ?>
wp cron event run srt_daily_digest</pre>
            </div>
            
            <div class="card">
                <h2>Hosting-Specific Notes</h2>
                
                <h3>cPanel / Shared Hosting</h3>
                <ol>
                    <li>Go to cPanel → Cron Jobs</li>
                    <li>Add new cron job with schedule: <code>0 0,2,6,12,18 * * *</code></li>
                    <li>Command: <code>/usr/local/bin/wp cron event run --due-now --path=<?php echo ABSPATH; ?></code></li>
                    <li>Adjust path to match your setup</li>
                </ol>
                
                <h3>Managed WordPress (WP Engine, Kinsta, etc.)</h3>
                <p>These often disable <code>wp-cron.php</code> by default and run server cron automatically. Contact support to:</p>
                <ol>
                    <li>Confirm cron is enabled</li>
                    <li>Request custom schedule (5x daily at 12am, 2am, 6am, 12pm, 6pm)</li>
                    <li>Verify they're running <code>wp cron event run</code> or similar</li>
                </ol>
                
                <h3>VPS / Dedicated Server</h3>
                <p>Use the automated script or manual crontab method. You have full control.</p>
            </div>
            
            <div class="card">
                <h2>Requirements</h2>
                
                <h3>WP-CLI Installation</h3>
                <p><strong>Most Hosting Providers:</strong> WP-CLI is often pre-installed. Check with:</p>
                <pre style="background: #f0f0f0; padding: 10px;">wp --version</pre>
                
                <p><strong>If Not Installed:</strong></p>
                <ol>
                    <li>Download: <code>curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar</code></li>
                    <li>Make executable: <code>chmod +x wp-cli.phar && sudo mv wp-cli.phar /usr/local/bin/wp</code></li>
                    <li>Test: <code>wp --info</code></li>
                </ol>
                <p>See full guide: <a href="https://wp-cli.org/" target="_blank">https://wp-cli.org/</a></p>
                
                <h3>Server Requirements</h3>
                <ul>
                    <li><strong>Crontab Access:</strong> Most shared hosting provides this via cPanel</li>
                    <li><strong>PHP CLI:</strong> Server must have PHP available from command line</li>
                    <li><strong>File Permissions:</strong> WordPress files must be readable by cron user</li>
                </ul>
            </div>
            
            <div class="card">
                <h2>API Configuration</h2>
                <p>After cron is set up, configure SerpAPI:</p>
                <ol>
                    <li>Register at <a href="https://serpapi.com/users/sign_up" target="_blank">https://serpapi.com/users/sign_up</a></li>
                    <li>Get your API key from <a href="https://serpapi.com/manage-api-key" target="_blank">https://serpapi.com/manage-api-key</a></li>
                    <li>In WordPress: <strong>Events → Cron Setup</strong> or <strong>Settings → Regiment Tracker Settings</strong></li>
                    <li>Enter your API key under "Flight Price Tracking API"</li>
                </ol>
                
                <p><strong>Pricing:</strong></p>
                <ul>
                    <li><strong>Free Tier:</strong> 100 searches/month</li>
                    <li><strong>Paid Plans:</strong> $75/month for 5,000 searches ($0.015 each)</li>
                </ul>
                
                <p>With 4 price checks per day and 30 flights, that's 120 calls/day = 3,600/month. You'll need a paid plan.</p>
            </div>
            
            <div class="card">
                <h2>Troubleshooting</h2>
                
                <h3>Cron not running?</h3>
                <p>Check system cron logs:</p>
                <pre style="background: #f0f0f0; padding: 10px;"># Ubuntu/Debian
sudo grep CRON /var/log/syslog

# CentOS/RHEL
sudo grep CRON /var/log/cron</pre>
                
                <h3>WP-CLI not found?</h3>
                <p>Verify path in crontab:</p>
                <pre style="background: #f0f0f0; padding: 10px;">which wp</pre>
                <p>Use full path in cron job: <code>/usr/local/bin/wp</code> instead of just <code>wp</code></p>
                
                <h3>Permission errors?</h3>
                <p>Ensure cron runs as the correct user:</p>
                <pre style="background: #f0f0f0; padding: 10px;"># Run as www-data (web server user)
sudo -u www-data wp cron event list --path=<?php echo ABSPATH; ?></pre>
                
                <h3>API errors in logs?</h3>
                <p>Check <code>wp-content/debug.log</code>:</p>
                <ul>
                    <li><code>401 Unauthorized</code> - Check your SerpAPI key</li>
                    <li><code>429 Too Many Requests</code> - Rate limit exceeded (upgrade plan)</li>
                    <li><code>400 Bad Request</code> - Invalid airport codes or date format</li>
                </ul>
                
                <p>Enable debug logging in <code>wp-config.php</code>:</p>
                <pre style="background: #f0f0f0; padding: 10px;">define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);</pre>
            </div>
            
            <div class="card">
                <h2>Monitoring</h2>
                
                <h3>View Scheduled Events</h3>
                <pre style="background: #f0f0f0; padding: 10px;">wp cron event list</pre>
                <p>Shows all scheduled WordPress events including <code>srt_check_flight_prices</code> and <code>srt_daily_digest</code>.</p>
                
                <h3>View Price History</h3>
                <p>Check database table <code>wp_ftt_price_history</code>:</p>
                <pre style="background: #f0f0f0; padding: 10px;">SELECT * FROM wp_ftt_price_history 
ORDER BY checked_at DESC 
LIMIT 20;</pre>
                
                <h3>View Active Alerts</h3>
                <p>Check database table <code>wp_ftt_price_alerts</code>:</p>
                <pre style="background: #f0f0f0; padding: 10px;">SELECT * FROM wp_ftt_price_alerts 
WHERE is_active = 1;</pre>
            </div>
            
            <div class="card">
                <h2>Performance Tuning</h2>
                
                <h3>Reduce API Calls</h3>
                <p><strong>Check less frequently:</strong> Change to 2x daily</p>
                <pre style="background: #f0f0f0; padding: 10px;">0 2,18 * * *  # Only 2am (for digest) and 6pm</pre>
                
                <p><strong>Filter events:</strong> Only check events within 60 days (already implemented)</p>
                
                <h3>Rate Limiting</h3>
                <p>The plugin includes 1-second sleep between API calls to avoid throttling. To adjust, edit <code>includes/price-tracking.php</code>:</p>
                <pre style="background: #f0f0f0; padding: 10px;">// Increase from 1 to 2 seconds
sleep(2);</pre>
            </div>
            
            <div class="card">
                <h2>Alternative: WP Control Plugin</h2>
                <p>For easier management, install <strong>WP Crontrol</strong> plugin:</p>
                <ul>
                    <li>GUI for cron events</li>
                    <li>View/edit/run scheduled tasks</li>
                    <li>No command-line needed</li>
                </ul>
                <p>Install: <code>wp plugin install wp-crontrol --activate</code></p>
            </div>
            
            <p style="text-align: center; margin-top: 30px;">
                <a href="<?php echo admin_url('edit.php?post_type=ftt_event&page=ftt-cron-setup'); ?>" class="button button-primary">
                    ← Back to Cron Setup
                </a>
            </p>
        </div>
        <?php
    }
}
