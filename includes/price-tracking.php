<?php
/**
 * Flight Price Tracking
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SRT Price Tracking Class
 */
class FTT_Price_Tracking {
    
    /**
     * Get notification email address from settings
     */
    public static function get_notification_email() {
        $settings = get_option('ftt_settings', array());
        $email = $settings['notification_from_email'] ?? '';
        return !empty($email) ? $email : 'noreply@familytraveltracker.app';
    }
    
    /**
     * Get notification sender name from settings  
     */
    public static function get_notification_from_name() {
        $settings = get_option('ftt_settings', array());
        $name = $settings['notification_from_name'] ?? '';
        return !empty($name) ? $name : get_bloginfo('name');
    }

    /**
     * Get support contact email for user-facing email footers.
     * Configurable via ftt_settings[support_email]; never uses admin_email.
     */
    public static function get_support_email() {
        $settings = get_option('ftt_settings', array());
        $email = $settings['support_email'] ?? '';
        return !empty($email) ? $email : 'support@familytraveltracker.app';
    }

    /**
     * Get the FTT dashboard URL using the Pages system with a slug fallback.
     */
    private static function get_dashboard_url() {
        $url = FTT_Pages::get_page_url('dashboard');
        return $url ?: home_url('/ftt-dashboard/');
    }

    /**
     * Get a user-facing URL for an event in email bodies.
     * Links to the dashboard with ?event_id={id} so the frontend can open the event.
     * Avoids CPT single-post URLs (which have no usable template).
     */
    private static function get_event_email_url($event_id) {
        return add_query_arg('event_id', absint($event_id), self::get_dashboard_url());
    }

    /**
     * Initialize
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'create_tables'));
        add_action('init', array(__CLASS__, 'upgrade_schema'));
        add_action('init', array(__CLASS__, 'handle_alert_deactivation'));
        add_action('ftt_check_flight_prices', array(__CLASS__, 'check_all_prices'));
        add_action('ftt_daily_digest', array(__CLASS__, 'process_daily_digests'));
        add_filter('cron_schedules', array(__CLASS__, 'add_custom_cron_schedule'));
        
        // Reschedule price check if it's still using the old 6-hour interval.
        $existing = wp_next_scheduled('ftt_check_flight_prices');
        if ($existing) {
            $events = _get_cron_array();
            $using_old = false;
            foreach ($events as $time => $hooks) {
                if (isset($hooks['ftt_check_flight_prices'])) {
                    foreach ($hooks['ftt_check_flight_prices'] as $hook) {
                        if (isset($hook['schedule']) && $hook['schedule'] === 'fourtimesdaily') {
                            $using_old = true;
                        }
                    }
                }
            }
            if ($using_old) {
                wp_clear_scheduled_hook('ftt_check_flight_prices');
                $existing = false;
            }
        }
        if (!$existing) {
            wp_schedule_event(time(), 'sixtimesdaily', 'ftt_check_flight_prices');
        }
        
        // Schedule daily digest at 2am
        if (!wp_next_scheduled('ftt_daily_digest')) {
            $tomorrow_2am = strtotime('tomorrow 2:00am');
            wp_schedule_event($tomorrow_2am, 'daily_2am', 'ftt_daily_digest');
        }
    }
    
    /**
     * Upgrade database schema for existing installations
     */
    public static function upgrade_schema() {
        if (get_option('ftt_price_tracking_schema_v2')) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ftt_price_history';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return; // Table hasn't been created yet, create_tables() will handle it
        }
        
        // Check if google_insights column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'google_insights'");
        
        if (empty($column_exists)) {
            // Add google_insights column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN google_insights TEXT DEFAULT NULL AFTER checked_at");
            error_log('SRT: Added google_insights column to price_history table');
        }
        
        update_option('ftt_price_tracking_schema_v2', true);
    }
    
    /**
     * Handle alert deactivation from email link (no login required)
     */
    public static function handle_alert_deactivation() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'ftt_deactivate_alert') {
            return;
        }
        
        if (!isset($_GET['alert_id']) || !isset($_GET['token'])) {
            wp_die('Invalid request', 'Error', array('response' => 400));
        }
        
        $alert_id = intval($_GET['alert_id']);
        $token = sanitize_text_field($_GET['token']);
        
        // Validate token
        if (!self::validate_alert_token($alert_id, $token)) {
            wp_die('Invalid or expired link', 'Error', array('response' => 403));
        }
        
        // Deactivate alert
        global $wpdb;
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
        $result = $wpdb->update(
            $alerts_table,
            array('is_active' => 0),
            array('id' => $alert_id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            wp_die('Failed to deactivate alert', 'Error', array('response' => 500));
        }
        
        // Show success message
        wp_die(
            '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 40px; text-align: center;">' .
            '<h1 style="color: #10b981; font-size: 48px; margin-bottom: 20px;">✓</h1>' .
            '<h2 style="color: #333; font-size: 24px; margin-bottom: 10px;">Price Alert Deactivated</h2>' .
            '<p style="color: #666; font-size: 16px; margin-bottom: 30px;">You will no longer receive price updates for this flight.</p>' .
            '<a href="' . esc_url( self::get_dashboard_url() ) . '" style="display: inline-block; background: #0066cc; color: white; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; font-size: 14px;">Go to Dashboard</a>' .
            '</div>',
            'Alert Deactivated',
            array('response' => 200)
        );
    }
    
    /**
     * Add custom cron schedule for four times daily
     */
    public static function add_custom_cron_schedule($schedules) {
        $schedules['sixtimesdaily'] = array(
            'interval' => 14400, // 4 hours in seconds (24 hours / 6)
            'display'  => __('Six Times Daily (Every 4 Hours)', 'schedule-collaboration-tracking')
        );
        $schedules['daily_2am'] = array(
            'interval' => 86400, // 24 hours in seconds
            'display'  => __('Daily at 2am', 'schedule-collaboration-tracking')
        );
        return $schedules;
    }
    
    /**
     * Create database tables for price tracking
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Price history table
        $table_name = $wpdb->prefix . 'ftt_price_history';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) NOT NULL,
            leg_index int(11) NOT NULL,
            origin varchar(3) NOT NULL,
            destination varchar(3) NOT NULL,
            depart_date date NOT NULL,
            price decimal(10,2) NOT NULL,
            checked_at datetime NOT NULL,
            google_insights TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY event_id (event_id),
            KEY route_date (origin, destination, depart_date),
            KEY checked_at (checked_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Price alerts table
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
        
        $sql_alerts = "CREATE TABLE IF NOT EXISTS $alerts_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            event_id bigint(20) NOT NULL,
            leg_index int(11) NOT NULL,
            alert_type varchar(50) NOT NULL,
            threshold_price decimal(10,2),
            threshold_percent int(11),
            is_active tinyint(1) DEFAULT 1,
            last_triggered datetime,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY event_id (event_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        dbDelta($sql_alerts);
    }
    
    /**
     * Get price history for a route
     */
    public static function get_price_history($origin, $destination, $depart_date, $days = 30) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ftt_price_history';
        
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE origin = %s 
            AND destination = %s 
            AND depart_date = %s
            AND checked_at >= %s
            ORDER BY checked_at ASC",
            $origin,
            $destination,
            $depart_date,
            $since
        ));
        
        return $results;
    }
    
    /**
     * Get price statistics
     */
    public static function get_price_stats($origin, $destination, $depart_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ftt_price_history';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                MIN(price) as min_price,
                MAX(price) as max_price,
                AVG(price) as avg_price,
                COUNT(*) as data_points
            FROM $table_name 
            WHERE origin = %s 
            AND destination = %s 
            AND depart_date = %s
            AND price > 0
            AND checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $origin,
            $destination,
            $depart_date
        ));
        
        return $stats;
    }
    
    /**
     * Record price check
     */
    public static function record_price($event_id, $leg_index, $origin, $destination, $depart_date, $price, $source = 'manual', $raw_data = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ftt_price_history';
        
        // Don't record invalid prices (0 or negative = API error/glitch)
        if (!$price || $price <= 0) {
            error_log("SRT: Skipping invalid price: $price for $origin -> $destination on $depart_date");
            return false;
        }
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            error_log("SRT record_price ERROR: Table $table_name does not exist! Creating tables...");
            self::create_tables();
        }
        
        error_log("SRT record_price: event=$event_id, leg=$leg_index, route=$origin->$destination, date=$depart_date, price=$price, source=$source");
        
        // Extract Google price insights if available in raw_data
        $google_insights = null;
        if ($raw_data && isset($raw_data['price_insights'])) {
            $google_insights = wp_json_encode($raw_data['price_insights']);
        }
        
        $insert_data = array(
            'event_id'     => $event_id,
            'leg_index'    => $leg_index,
            'origin'       => $origin,
            'destination'  => $destination,
            'depart_date'  => $depart_date,
            'price'        => $price,
            'checked_at'   => current_time('mysql'),
        );
        
        $format = array('%d', '%d', '%s', '%s', '%s', '%f', '%s');
        
        if ($google_insights) {
            $insert_data['google_insights'] = $google_insights;
            $format[] = '%s';
        }
        
        $result = $wpdb->insert($table_name, $insert_data, $format);
        
        if ($result === false) {
            error_log("SRT record_price ERROR: " . $wpdb->last_error);
            error_log("SRT record_price QUERY: " . $wpdb->last_query);
            return false;
        }
        
        $insert_id = $wpdb->insert_id;
        error_log("SRT record_price SUCCESS: Inserted with ID $insert_id");
        
        // Check if this triggers any alerts
        self::check_price_alerts($event_id, $leg_index, $price);
        
        return $insert_id;
    }
    
    /**
     * Check linked flights and fetch both round-trip and individual prices
     */
    public static function check_linked_flight_prices($group_id, $api_key) {
        // Get all legs in this group
        $legs = FTT_Flight_Linking::get_flight_group_legs($group_id);
        
        if (count($legs) < 2) {
            return null;
        }
        
        $results = array(
            'group_id' => $group_id,
            'individual_prices' => array(),
            'round_trip_price' => null,
        );
        
        // Fetch individual prices for each leg
        foreach ($legs as $leg_data) {
            $leg = $leg_data['leg'];
            $depart_date = $leg['depart_date'];
            
            if (!empty($leg['depart_airport']) && !empty($leg['arrive_airport']) && !empty($depart_date)) {
                $price_result = self::fetch_flight_price_serpapi_with_key(
                    $api_key,
                    $leg['depart_airport'],
                    $leg['arrive_airport'],
                    $depart_date
                );
                
                $price = is_array($price_result) ? $price_result['price'] : $price_result;
                
                if ($price !== false && $price > 0) {
                    self::record_price(
                        $leg_data['event_id'],
                        $leg_data['leg_index'],
                        $leg['depart_airport'],
                        $leg['arrive_airport'],
                        $depart_date,
                        $price,
                        'serpapi_linked'
                    );
                    
                    $results['individual_prices'][] = array(
                        'event_id' => $leg_data['event_id'],
                        'leg_index' => $leg_data['leg_index'],
                        'price' => $price,
                    );
                }
                
                sleep(1); // Rate limiting
            }
        }
        
        // If exactly 2 legs and they're reversed, check round-trip price
        if (count($legs) === 2) {
            $leg1 = $legs[0]['leg'];
            $leg2 = $legs[1]['leg'];
            
            if ($leg1['depart_airport'] === $leg2['arrive_airport'] &&
                $leg1['arrive_airport'] === $leg2['depart_airport']) {
                
                $price_result = self::fetch_flight_price_serpapi_with_key(
                    $api_key,
                    $leg1['depart_airport'],
                    $leg1['arrive_airport'],
                    $leg1['depart_date'],
                    $leg2['depart_date']
                );
                
                $price = is_array($price_result) ? $price_result['price'] : $price_result;
                
                if ($price !== false && $price > 0) {
                    // Record as round-trip for first leg
                    self::record_price(
                        $legs[0]['event_id'],
                        $legs[0]['leg_index'],
                        $leg1['depart_airport'],
                        $leg1['arrive_airport'],
                        $leg1['depart_date'],
                        $price,
                        'serpapi_roundtrip'
                    );
                    
                    $results['round_trip_price'] = $price;
                }
                
                sleep(1); // Rate limiting
            }
        }
        
        return $results;
    }
    
    /**
     * Check all unbooked flights for price updates
     */
    public static function check_all_prices($source = 'scheduled') {
        $start_time = microtime(true);
        $flights_checked = 0;
        $prices_recorded = 0;
        $had_error = false;
        $error_message = '';
        
        try {
            // Get API credentials
            $settings = get_option('ftt_settings', array());
            $api_key = $settings['serpapi_api_key'] ?? '';
            
            if (empty($api_key)) {
                error_log('SRT Price Tracking: SerpAPI key not configured');
                
                // Log the failure
                $log = get_option('ftt_cron_log', array());
                $log_entry = array(
                    'timestamp' => current_time('mysql'),
                    'type' => $source,
                    'status' => 'failed',
                    'error' => 'SerpAPI key not configured',
                    'flights_checked' => 0,
                    'prices_recorded' => 0,
                    'duration' => 0,
                );
                
                if ($source === 'manual') {
                    $log_entry['user'] = wp_get_current_user()->user_login;
                }
                
                $log[] = $log_entry;
                if (count($log) > 20) {
                    $log = array_slice($log, -20);
                }
                update_option('ftt_cron_log', $log);
                update_option('ftt_cron_last_run', current_time('mysql'));
                
                return;
            }
            
            $type_label = $source === 'manual' ? 'manual' : 'scheduled';
            error_log("SRT: Starting $type_label price check...");
        
        // Base query arguments - get all future flight events
        $args = array(
            'post_type'      => 'ftt_event',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => 'flight_needed',
                    'value'   => '1',
                    'compare' => '=',
                ),
                array(
                    'key'     => 'start_datetime',
                    'value'   => date('Y-m-d'),
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            ),
        );
        
        // Get current user for filtering (if manual check from parent)
        if ($source === 'manual' && is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            $is_parent = FTT_Roles::is_parent($current_user_id);
            
            if ($is_parent) {
                // Include events for all children
                $children = FTT_Roles::get_children($current_user_id);
                
                if (!empty($children)) {
                    // Include events for children, parent, empty, or unassigned
                    $member_ids = array_merge($children, array($current_user_id));
                    
                    $args['meta_query'][] = array(
                        'relation' => 'OR',
                        array(
                            'key'     => 'member_id',
                            'value'   => $member_ids,
                            'compare' => 'IN',
                        ),
                        array(
                            'key'     => 'member_id',
                            'compare' => 'NOT EXISTS',
                        ),
                        array(
                            'key'     => 'member_id',
                            'value'   => '',
                            'compare' => '=',
                        ),
                    );
                    error_log("SRT: Parent $current_user_id checking prices for children: " . implode(', ', $children));
                }
            } else {
                // Member - only their events
                $args['meta_query'][] = array(
                    'key'     => 'member_id',
                    'value'   => $current_user_id,
                    'compare' => '=',
                );
                error_log("SRT: Member $current_user_id checking prices");
            }
        }
        
        $events = get_posts($args);
        
        foreach ($events as $event) {
            $travel_legs = json_decode(get_post_meta($event->ID, 'travel_legs', true) ?: '[]', true);
            $checked_legs = array(); // Track which legs we've checked to avoid duplicates
            
            foreach ($travel_legs as $index => $leg) {
                if (in_array($index, $checked_legs)) {
                    continue; // Skip if already checked as part of round-trip
                }
                
                if ($leg['mode'] === 'fly' && !$leg['booked'] && $leg['depart_airport'] && $leg['arrive_airport']) {
                    $depart_date = $leg['depart_date'] ?? ($leg['depart_datetime'] ? substr($leg['depart_datetime'], 0, 10) : null);
                    
                    if ($depart_date) {
                        // Check if this is part of a round-trip pattern
                        $is_round_trip = false;
                        $return_date = null;
                        
                        // Method 0: Check explicit is_round_trip flag (new preferred method)
                        if (!empty($leg['is_round_trip']) && !empty($leg['return_date'])) {
                            $is_round_trip = true;
                            $return_date = $leg['return_date'];
                            error_log("SRT: Event {$event->ID}, Leg {$index}: Using explicit round-trip flag with return date {$return_date}");
                        }
                        
                        // Method 1: Check if arrive_date spans multiple days (legacy method)
                        if (!$is_round_trip && !empty($leg['arrive_date']) && !empty($leg['depart_date'])) {
                            $depart_time = strtotime($leg['depart_date']);
                            $arrive_time = strtotime($leg['arrive_date']);
                            $days_diff = ($arrive_time - $depart_time) / (60 * 60 * 24);
                            
                            if ($days_diff >= 1) {
                                $is_round_trip = true;
                                $return_date = $leg['arrive_date'];
                                error_log("SRT: Event {$event->ID}, Leg {$index}: Detected round-trip from arrive_date span");
                            }
                        }
                        
                        // Method 2: Look for a return leg (same airports reversed)
                        if (!$is_round_trip) {
                            for ($i = $index + 1; $i < count($travel_legs); $i++) {
                                if ($travel_legs[$i]['mode'] === 'fly' && 
                                    $travel_legs[$i]['depart_airport'] === $leg['arrive_airport'] && 
                                    $travel_legs[$i]['arrive_airport'] === $leg['depart_airport'] &&
                                    !empty($travel_legs[$i]['depart_date'])) {
                                    $is_round_trip = true;
                                    $return_date = $travel_legs[$i]['depart_date'];
                                    $checked_legs[] = $i; // Mark return leg as checked
                                    error_log("SRT: Event {$event->ID}, Leg {$index}: Detected round-trip from reversed leg {$i}");
                                    break;
                                }
                            }
                        }
                        
                        $flights_checked++;
                        
                        // Fetch price with round-trip detection
                        if ($is_round_trip && $return_date) {
                            $price_result = self::fetch_flight_price_serpapi_with_key($api_key, $leg['depart_airport'], $leg['arrive_airport'], $depart_date, $return_date);
                        } else {
                            $price_result = self::fetch_flight_price_serpapi_with_key($api_key, $leg['depart_airport'], $leg['arrive_airport'], $depart_date);
                        }
                        
                        $price = is_array($price_result) ? $price_result['price'] : $price_result;
                        
                        if ($price !== false && $price > 0) {
                            $recorded = self::record_price($event->ID, $index, $leg['depart_airport'], $leg['arrive_airport'], $depart_date, $price, 'serpapi', $price_result);
                            if ($recorded !== false) {
                                $prices_recorded++;
                            }
                        }
                        
                        // Rate limiting - SerpAPI allows 1 search per second on paid plans
                        sleep(1);
                    }
                }
            }
        }
        
        $duration = round(microtime(true) - $start_time, 2);
        error_log("SRT: Price check complete - Flights: $flights_checked, Valid prices: $prices_recorded, Duration: {$duration}s");
        
        // Log to activity log
        $log = get_option('ftt_cron_log', array());
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'type' => $source,
            'status' => 'success',
            'flights_checked' => $flights_checked,
            'prices_recorded' => $prices_recorded,
            'duration' => $duration,
        );
        
        if ($source === 'manual') {
            $log_entry['user'] = wp_get_current_user()->user_login;
        }
        
        $log[] = $log_entry;
        
        // Keep only last 20 entries
        if (count($log) > 20) {
            $log = array_slice($log, -20);
        }
        update_option('ftt_cron_log', $log);
        
        // Update success tracking
        update_option('ftt_cron_last_run', current_time('mysql'));
        update_option('ftt_cron_last_success', current_time('mysql'));
        update_option('ftt_cron_total_runs', get_option('ftt_cron_total_runs', 0) + 1);
        update_option('ftt_cron_last_stats', array(
            'flights_checked' => $flights_checked,
            'prices_recorded' => $prices_recorded,
            'duration' => $duration,
        ));
        
        } catch (Exception $e) {
            // Log the exception
            $duration = round(microtime(true) - $start_time, 2);
            $error_message = $e->getMessage();
            error_log("SRT Price Tracking Error: " . $error_message);
            
            $log = get_option('ftt_cron_log', array());
            $log_entry = array(
                'timestamp' => current_time('mysql'),
                'type' => $source,
                'status' => 'failed',
                'error' => $error_message,
                'flights_checked' => $flights_checked,
                'prices_recorded' => $prices_recorded,
                'duration' => $duration,
            );
            
            if ($source === 'manual') {
                $log_entry['user'] = wp_get_current_user()->user_login;
            }
            
            $log[] = $log_entry;
            if (count($log) > 20) {
                $log = array_slice($log, -20);
            }
            update_option('ftt_cron_log', $log);
            update_option('ftt_cron_last_run', current_time('mysql'));
        }
    }
    
    /**
     * Fetch flight price from SerpAPI (Google Flights) - Public method
     */
    public static function fetch_flight_price_serpapi($origin, $destination, $date, $return_date = null) {
        $settings = get_option('ftt_settings', array());
        $api_key = $settings['serpapi_api_key'] ?? '';
        
        if (empty($api_key)) {
            error_log('SRT Price Tracking: SerpAPI key not configured');
            return null;
        }
        
        return self::fetch_flight_price_serpapi_with_key($api_key, $origin, $destination, $date, $return_date);
    }
    
    /**
     * Fetch flight price from SerpAPI with API key (internal)
     */
    private static function fetch_flight_price_serpapi_with_key($api_key, $origin, $destination, $date, $return_date = null) {
        $url = 'https://serpapi.com/search';
        
        $params = array(
            'engine' => 'google_flights',
            'departure_id' => $origin,
            'arrival_id' => $destination,
            'outbound_date' => $date,
            'type' => $return_date ? '1' : '2', // 1 = round trip, 2 = one way
            'currency' => 'USD',
            'hl' => 'en',
            'api_key' => $api_key,
        );
        
        // Add return date for round-trip
        if ($return_date) {
            $params['return_date'] = $return_date;
        }
        
        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            if ( class_exists('FTT_API_Tracker') ) {
                FTT_API_Tracker::record('serpapi', false);
            }
            return array(
                'price' => false,
                'debug' => array(
                    'error' => $response->get_error_message(),
                    'request' => array(
                        'origin' => $origin,
                        'destination' => $destination,
                        'date' => $date,
                    ),
                ),
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        $debug_info = array(
            'request' => array(
                'origin' => $origin,
                'destination' => $destination,
                'outbound_date' => $date,
                'return_date' => $return_date,
                'trip_type' => $return_date ? 'round-trip' : 'one-way',
                'api_key' => substr($api_key, 0, 8) . '...',
            ),
            'response' => $body,
        );
        
        // Extract price insights from Google Flights response
        $price_insights = isset($body['price_insights']) ? $body['price_insights'] : null;
        
        // SerpAPI returns best flights in 'best_flights' array
        if (isset($body['best_flights'][0]['price'])) {
            if ( class_exists('FTT_API_Tracker') ) {
                FTT_API_Tracker::record('serpapi', true);
            }
            return array(
                'price' => floatval($body['best_flights'][0]['price']),
                'price_insights' => $price_insights,
                'debug' => $debug_info,
            );
        }
        
        // Fallback to other_flights
        if (isset($body['other_flights'][0]['price'])) {
            if ( class_exists('FTT_API_Tracker') ) {
                FTT_API_Tracker::record('serpapi', true);
            }
            return array(
                'price' => floatval($body['other_flights'][0]['price']),
                'price_insights' => $price_insights,
                'debug' => $debug_info,
            );
        }
        
        // Check for errors
        if (isset($body['error'])) {
            $debug_info['serpapi_error'] = $body['error'];
        }
        
        // No price found — API responded but returned no flight data
        if ( class_exists('FTT_API_Tracker') ) {
            FTT_API_Tracker::record('serpapi', false);
        }
        return array(
            'price' => false,
            'price_insights' => $price_insights,
            'debug' => $debug_info,
        );
    }
    
    /**
     * Check if price triggers any user alerts
     */
    public static function check_price_alerts($event_id, $leg_index, $current_price) {
        global $wpdb;
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
        
        // Get active alerts for this flight
        $alerts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $alerts_table 
            WHERE event_id = %d 
            AND leg_index = %d 
            AND is_active = 1",
            $event_id,
            $leg_index
        ));
        
        error_log("SRT: Checking alerts for event $event_id leg $leg_index - Found " . count($alerts) . " active alerts");
        
        foreach ($alerts as $alert) {
            $should_alert = false;
            $alert_reason = '';
            
            switch ($alert->alert_type) {
                case 'price_drop':
                    if ($alert->threshold_price && $current_price <= $alert->threshold_price) {
                        $should_alert = true;
                        $alert_reason = "Price $current_price dropped to/below threshold {$alert->threshold_price}";
                    }
                    break;
                    
                case 'percent_drop':
                    // Get previous price
                    $prev_price = self::get_previous_price($event_id, $leg_index);
                    if ($prev_price) {
                        $percent_drop = (($prev_price - $current_price) / $prev_price) * 100;
                        if ($percent_drop >= $alert->threshold_percent) {
                            $should_alert = true;
                            $alert_reason = sprintf("Price dropped %.1f%% (from $%.2f to $%.2f)", $percent_drop, $prev_price, $current_price);
                        }
                    } else {
                        error_log("SRT: No previous price found for percent_drop alert");
                    }
                    break;
                    
                case 'good_deal':
                    // Good Deal = 15% below average price
                    $event = get_post($event_id);
                    $legs = json_decode(get_post_meta($event_id, 'travel_legs', true) ?: '[]', true);
                    $leg = $legs[$leg_index] ?? null;
                    
                    if ($leg) {
                        $stats = self::get_price_stats($leg['depart_airport'], $leg['arrive_airport'], $leg['depart_date']);
                        if ($stats && $stats->avg_price && $current_price < ($stats->avg_price * 0.85)) {
                            $should_alert = true;
                            $alert_reason = sprintf("Good deal! Price $%.2f is 15%% below avg $%.2f", $current_price, $stats->avg_price);
                        }
                    }
                    break;
            }
            
            if ($should_alert) {
                error_log("SRT: Triggering alert {$alert->id} - $alert_reason");
                self::send_price_alert($alert, $current_price);
                
                // Update last triggered
                $wpdb->update(
                    $alerts_table,
                    array('last_triggered' => current_time('mysql')),
                    array('id' => $alert->id),
                    array('%s'),
                    array('%d')
                );
            } else {
                error_log("SRT: Alert {$alert->id} ({$alert->alert_type}) not triggered for price $current_price");
            }
        }
    }
    
    /**
     * Get previous price
     */
    private static function get_previous_price($event_id, $leg_index) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ftt_price_history';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT price FROM $table_name 
            WHERE event_id = %d 
            AND leg_index = %d 
            ORDER BY checked_at DESC 
            LIMIT 1, 1",
            $event_id,
            $leg_index
        ));
        
        return $result;
    }
    
    /**
     * Send price alert email
     */
    private static function send_price_alert($alert, $current_price) {
        $user = get_user_by('id', $alert->user_id);
        $event = get_post($alert->event_id);
        
        if (!$user || !$event) {
            error_log("SRT: Cannot send alert - User or event not found");
            return;
        }
        
        $legs = json_decode(get_post_meta($alert->event_id, 'travel_legs', true) ?: '[]', true);
        $leg = $legs[$alert->leg_index] ?? null;
        
        if (!$leg) {
            error_log("SRT: Cannot send alert - Leg not found");
            return;
        }
        
        // Get latest price record to check for Google insights
        global $wpdb;
        $table_name = $wpdb->prefix . 'ftt_price_history';
        $latest_record = $wpdb->get_row($wpdb->prepare(
            "SELECT google_insights FROM $table_name 
            WHERE event_id = %d AND leg_index = %d 
            ORDER BY checked_at DESC LIMIT 1",
            $alert->event_id,
            $alert->leg_index
        ));
        
        $google_insights = null;
        if ($latest_record && !empty($latest_record->google_insights)) {
            $google_insights = json_decode($latest_record->google_insights, true);
        }
        
        $subject = sprintf('[%s] Flight Price Alert: %s',
            get_bloginfo('name'),
            $event->post_title
        );
        
        // Build HTML email
        $message = self::build_price_alert_email($user, $event, $leg, $current_price, $google_insights);
        
        error_log("SRT: Sending price alert email to {$user->user_email}");
        
        $from_name = self::get_notification_from_name();
        $from_email = self::get_notification_email();
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
        );
        
        $result = wp_mail($user->user_email, $subject, $message, $headers);
        error_log("SRT: Email send result: " . ($result ? 'success' : 'failed'));
        
        do_action('ftt_price_alert_sent', $alert, $current_price, $user, $event);
    }
    
    /**
     * Build price alert email HTML
     */
    private static function build_price_alert_email($user, $event, $leg, $current_price, $google_insights = null) {
        $site_name = get_bloginfo('name');
        $event_url = self::get_event_email_url($event->ID);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
            
            <div style="background: #fff; border-radius: 8px; padding: 30px; margin-bottom: 20px;">
                <h1 style="color: #0066cc; border-bottom: 3px solid #0066cc; padding-bottom: 10px; margin-top: 0; font-size: 24px;">✈️ Flight Price Alert</h1>
                <p style="margin: 15px 0; font-size: 16px;">Hi <?php echo esc_html($user->display_name); ?>,</p>
                <p style="margin: 15px 0; font-size: 14px; color: #666;">Great news! We found a price update for your flight:</p>
            </div>
            
            <div style="background: #fff; border-radius: 8px; padding: 25px; margin-bottom: 20px;">
                <div style="font-weight: bold; font-size: 18px; color: #333; margin-bottom: 10px;">
                    <?php echo esc_html($event->post_title); ?>
                </div>
                
                <div style="font-size: 14px; color: #666; margin-bottom: 20px;">
                    <strong><?php echo esc_html($leg['depart_airport']); ?></strong> → <strong><?php echo esc_html($leg['arrive_airport']); ?></strong>
                    <br>
                    <?php echo esc_html($leg['depart_date'] ?? 'TBD'); ?>
                </div>
                
                <?php if ($google_insights): ?>
                    <?php 
                    $price_level = isset($google_insights['price_level']) ? strtolower($google_insights['price_level']) : '';
                    $typical_low = isset($google_insights['typical_price_range'][0]) ? $google_insights['typical_price_range'][0] : null;
                    $typical_high = isset($google_insights['typical_price_range'][1]) ? $google_insights['typical_price_range'][1] : null;
                    
                    if ($price_level && $typical_high):
                        $bg_color = $price_level === 'low' ? '#ecfdf5' : ($price_level === 'high' ? '#fef2f2' : '#fffbeb');
                        $border_color = $price_level === 'low' ? '#10b981' : ($price_level === 'high' ? '#ef4444' : '#f59e0b');
                        $text_color = $price_level === 'low' ? '#065f46' : ($price_level === 'high' ? '#991b1b' : '#92400e');
                        $icon = $price_level === 'low' ? '✨' : ($price_level === 'high' ? '⚠️' : 'ℹ️');
                        
                        $savings = $typical_high - $current_price;
                        $message = '';
                        
                        if ($price_level === 'low' && $savings > 0) {
                            $message = sprintf('Prices are currently <strong>low</strong> — $%d cheaper than usual for this route', round($savings));
                        } elseif ($price_level === 'high') {
                            $message = 'Prices are currently <strong>high</strong> — Consider waiting or alternative dates';
                        } else {
                            $message = 'Prices are <strong>typical</strong> for this route';
                        }
                    ?>
                    <div style="background: <?php echo $bg_color; ?>; border-left: 4px solid <?php echo $border_color; ?>; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                        <div style="font-size: 14px; color: <?php echo $text_color; ?>; font-weight: 600; margin-bottom: 6px;">
                            <?php echo $icon; ?> Google Flights Insight
                        </div>
                        <div style="font-size: 13px; color: <?php echo $text_color; ?>;">
                            <?php echo $message; ?>
                        </div>
                        <?php if ($typical_low && $typical_high): ?>
                            <div style="font-size: 12px; color: <?php echo $text_color; ?>; opacity: 0.85; margin-top: 6px;">
                                Typical range: $<?php echo number_format($typical_low, 0); ?>–$<?php echo number_format($typical_high, 0); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div style="background: #f0f9ff; border: 2px solid #0891b2; padding: 20px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 14px; color: #0c5460; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">Current Price</div>
                    <div style="font-size: 36px; font-weight: bold; color: #0891b2;">$<?php echo number_format($current_price, 0); ?></div>
                </div>
                
                <div style="text-align: center; margin-top: 25px;">
                    <a href="<?php echo esc_url($event_url); ?>" style="display: inline-block; background: #0066cc; color: white; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; font-size: 14px;">
                        View Event Details →
                    </a>
                </div>
            </div>
            
            <div style="background: #fff; border-radius: 8px; padding: 20px; text-align: center; font-size: 12px; color: #666;">
                <p style="margin: 10px 0;">You're receiving this because you set up a price alert. Manage your alerts in your <a href="<?php echo esc_url( self::get_dashboard_url() ); ?>" style="color: #0066cc; text-decoration: none;">Member Dashboard</a>.</p>
                <p style="margin: 10px 0;">Need help? Email <a href="mailto:<?php echo esc_attr( self::get_support_email() ); ?>" style="color: #0066cc; text-decoration: none;"><?php echo esc_html( self::get_support_email() ); ?></a></p>
                <p style="margin: 10px 0;">&copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?></p>
            </div>
            
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Create price alert for user
     */
    public static function create_alert($user_id, $event_id, $leg_index, $alert_type, $threshold_price = null, $threshold_percent = null) {
        global $wpdb;
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
        
        $wpdb->insert(
            $alerts_table,
            array(
                'user_id'           => $user_id,
                'event_id'          => $event_id,
                'leg_index'         => $leg_index,
                'alert_type'        => $alert_type,
                'threshold_price'   => $threshold_price,
                'threshold_percent' => $threshold_percent,
                'is_active'         => 1,
                'created_at'        => current_time('mysql'),
            ),
            array('%d', '%d', '%d', '%s', '%f', '%d', '%d', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get user's alerts
     */
    public static function get_user_alerts($user_id, $active_only = true) {
        global $wpdb;
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
        
        $query = "SELECT * FROM $alerts_table WHERE user_id = %d";
        $params = array($user_id);
        
        if ($active_only) {
            $query .= " AND is_active = 1";
        }
        
        $query .= " ORDER BY created_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    /**
     * Generate unsubscribe token for an alert
     */
    private static function generate_alert_token($alert_id, $user_id) {
        return hash_hmac('sha256', $alert_id . '|' . $user_id, AUTH_KEY);
    }
    
    /**
     * Validate unsubscribe token
     */
    public static function validate_alert_token($alert_id, $token) {
        global $wpdb;
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
        
        $alert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $alerts_table WHERE id = %d",
            $alert_id
        ));
        
        if (!$alert) {
            return false;
        }
        
        $expected_token = self::generate_alert_token($alert->id, $alert->user_id);
        return hash_equals($expected_token, $token);
    }
    
    /**
     * Send confirmation email when price alert is created
     */
    public static function send_alert_confirmation($alert_id) {
        global $wpdb;
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
        
        $alert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $alerts_table WHERE id = %d",
            $alert_id
        ));
        
        if (!$alert) {
            return false;
        }
        
        $user = get_userdata($alert->user_id);
        if (!$user) {
            return false;
        }
        
        $event = get_post($alert->event_id);
        if (!$event) {
            return false;
        }
        
        $travel_legs = json_decode(get_post_meta($event->ID, 'travel_legs', true) ?: '[]', true);
        $leg = isset($travel_legs[$alert->leg_index]) ? $travel_legs[$alert->leg_index] : null;
        
        if (!$leg) {
            return false;
        }
        
        $site_name = get_bloginfo('name');
        $from_name = self::get_notification_from_name();
        $from_email = self::get_notification_email();
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
        );
        $subject = FTT_Email_Templates::render_subject('alert_confirmation', [
            'site_name'   => $site_name,
            'event_title' => $event->post_title,
        ]);
        
        $message = self::build_confirmation_email($user, $event, $leg, $alert);
        
        $email_sent = wp_mail($user->user_email, $subject, $message, $headers);
        
        return array(
            'success' => $email_sent,
            'subject' => $subject,
        );
    }
    
    /**
     * Build confirmation email HTML
     */
    private static function build_confirmation_email($user, $event, $leg, $alert) {
        $site_name = get_bloginfo('name');
        $event_url = self::get_event_email_url($event->ID);
        $token = self::generate_alert_token($alert->id, $alert->user_id);
        $unsubscribe_url = add_query_arg(array(
            'action' => 'ftt_deactivate_alert',
            'alert_id' => $alert->id,
            'token' => $token,
        ), home_url());
        
        $alert_type_label = $alert->alert_type === 'daily_digest' ? 'Daily Digest' : 'Price Drop Alert';
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
            
            <div style="background: #fff; border-radius: 8px; padding: 30px; margin-bottom: 20px;">
                <h1 style="color: #0066cc; border-bottom: 3px solid #0066cc; padding-bottom: 10px; margin-top: 0; font-size: 24px;">✅ Price Alert Activated</h1>
                <p style="margin: 15px 0; font-size: 16px;">Hi <?php echo esc_html($user->display_name); ?>,</p>
                <p style="margin: 15px 0; font-size: 14px; color: #666;">Your flight price alert has been successfully set up!</p>
            </div>
            
            <div style="background: #fff; border-radius: 8px; padding: 25px; margin-bottom: 20px;">
                <h2 style="margin-top: 0; font-size: 18px; color: #333;">Alert Details</h2>
                
                <div style="background: #f9fafb; padding: 15px; border-radius: 6px; margin: 15px 0;">
                    <div style="font-weight: bold; font-size: 16px; color: #333; margin-bottom: 8px;">
                        <?php echo esc_html($event->post_title); ?>
                    </div>
                    <div style="font-size: 14px; color: #666; margin-bottom: 8px;">
                        <strong><?php echo esc_html($leg['depart_airport']); ?></strong> → <strong><?php echo esc_html($leg['arrive_airport']); ?></strong>
                        <br>
                        <?php echo esc_html($leg['depart_date'] ?? 'TBD'); ?>
                    </div>
                    <div style="font-size: 13px; color: #666;">
                        Alert Type: <strong><?php echo esc_html($alert_type_label); ?></strong>
                    </div>
                </div>
                
                <p style="font-size: 14px; color: #666; margin: 15px 0;">
                    <?php if ($alert->alert_type === 'daily_digest'): ?>
                        You'll receive a daily email with price updates for this flight.
                    <?php else: ?>
                        You'll be notified when prices drop or change significantly.
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Important: Keep Out of Spam Section -->
            <div style="background: #fffbeb; border: 2px solid #f59e0b; border-radius: 8px; padding: 20px 25px; margin-bottom: 20px;">
                <h2 style="margin: 0 0 15px 0; font-size: 16px; color: #92400e;">
                    <span style="display: inline-block; width: 18px; height: 18px; background: #f59e0b; border-radius: 3px; text-align: center; line-height: 18px; color: white; font-size: 12px; margin-right: 6px;">!</span>
                    Important: Ensure You Receive Our Emails
                </h2>
                <p style="font-size: 13px; color: #78350f; margin: 0 0 15px 0;">
                    To make sure our price alerts don't end up in your spam folder, please add us to your contacts or safe senders list.
                </p>
                
                <!-- Gmail Instructions -->
                <div style="background: white; border-left: 3px solid #ea4335; padding: 12px 15px; margin: 10px 0; border-radius: 4px;">
                    <div style="font-weight: 600; font-size: 14px; color: #374151; margin-bottom: 8px;">Gmail Instructions</div>
                    <div style="font-size: 12px; color: #4b5563; line-height: 1.5;">
                        1. Find this email in your inbox (check Promotions or Spam tabs if needed)<br>
                        2. Drag this email to the "Primary" tab<br>
                        3. Click "Yes" when asked "Do this for future messages from <?php echo esc_html(self::get_notification_email()); ?>?"<br>
                        <span style="font-style: italic; color: #6b7280;">Alternative: Click the three dots (⋮) → Add to Contacts</span>
                    </div>
                </div>
                
                <!-- Outlook Instructions -->
                <div style="background: white; border-left: 3px solid #0078d4; padding: 12px 15px; margin: 10px 0; border-radius: 4px;">
                    <div style="font-weight: 600; font-size: 14px; color: #374151; margin-bottom: 8px;">Outlook/Hotmail Instructions</div>
                    <div style="font-size: 12px; color: #4b5563; line-height: 1.5;">
                        1. Open this email<br>
                        2. Click the three dots (⋯) at the top<br>
                        3. Select "Add to Safe Senders"<br>
                        <span style="font-style: italic; color: #6b7280;">Or: Settings → Mail → Junk email → Safe senders → Add <?php echo esc_html(self::get_notification_email()); ?></span>
                    </div>
                </div>
                
                <!-- Yahoo Instructions -->
                <div style="background: white; border-left: 3px solid #6001d2; padding: 12px 15px; margin: 10px 0; border-radius: 4px;">
                    <div style="font-weight: 600; font-size: 14px; color: #374151; margin-bottom: 8px;">Yahoo Mail Instructions</div>
                    <div style="font-size: 12px; color: #4b5563; line-height: 1.5;">
                        1. Open this email<br>
                        2. Click on our sender name at the top<br>
                        3. Click "Add to Contacts"<br>
                        <span style="font-style: italic; color: #6b7280;">Or: Click the three dots (⋯) → "Mark as Not Spam" if in spam folder</span>
                    </div>
                </div>
                
                <div style="background: #fef3c7; padding: 10px 12px; border-radius: 4px; margin: 15px 0 0 0;">
                    <p style="font-size: 12px; color: #78350f; margin: 0;">
                        <strong>Our emails come from:</strong> <?php echo esc_html(self::get_notification_email()); ?>
                    </p>
                </div>
            </div>
            
            <div style="background: #fff; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px;">
                <a href="<?php echo esc_url($event_url); ?>" style="display: inline-block; background: #0066cc; color: white; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; font-size: 14px; margin: 5px;">
                    View Event Details
                </a>
                <a href="<?php echo esc_url( self::get_dashboard_url() ); ?>" style="display: inline-block; background: #6b7280; color: white; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; font-size: 14px; margin: 5px;">
                    Manage All Alerts
                </a>
            </div>
            
            <div style="background: #fff; border-radius: 8px; padding: 20px; text-align: center; font-size: 12px; color: #666;">
                <p style="margin: 10px 0;">No longer need this alert? <a href="<?php echo esc_url($unsubscribe_url); ?>" style="color: #0066cc; text-decoration: none;">Turn it off</a></p>
                <p style="margin: 10px 0;">Need help? Email <a href="mailto:<?php echo esc_attr( self::get_support_email() ); ?>" style="color: #0066cc; text-decoration: none;"><?php echo esc_html( self::get_support_email() ); ?></a></p>
                <p style="margin: 10px 0;">&copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?></p>
            </div>
            
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Process daily digests for all users
     */
    public static function process_daily_digests() {
        global $wpdb;
        $start_time = microtime(true);
        $emails_sent = 0;
        
        try {
            $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
            
            // FIRST: Run price check to ensure we have fresh data with Google insights
            error_log('[SRT Daily Digest] Running price check before sending emails...');
            self::check_all_prices('digest');
            error_log('[SRT Daily Digest] Price check complete, now sending emails');
            
            // Get all users with active daily digest alerts
            $users = $wpdb->get_results(
                "SELECT DISTINCT user_id FROM $alerts_table WHERE alert_type = 'daily_digest' AND is_active = 1"
            );
            
            error_log(sprintf('[SRT Daily Digest] Processing %d users', count($users)));
            
            foreach ($users as $user) {
                $sent = self::send_daily_digest($user->user_id);
                if ($sent) {
                    $emails_sent++;
                }
            }
            
            // Log success
            $duration = round(microtime(true) - $start_time, 2);
            error_log("[SRT Daily Digest] Complete - {$emails_sent} emails sent, Duration: {$duration}s");
            
            $log = get_option('ftt_cron_log', array());
            $log_entry = array(
                'timestamp' => current_time('mysql'),
                'type' => 'digest',
                'status' => 'success',
                'flights_checked' => count($users),
                'prices_recorded' => $emails_sent,
                'duration' => $duration,
            );
            
            $log[] = $log_entry;
            if (count($log) > 20) {
                $log = array_slice($log, -20);
            }
            update_option('ftt_cron_log', $log);
            
        } catch (Exception $e) {
            // Log the exception
            $duration = round(microtime(true) - $start_time, 2);
            $error_message = $e->getMessage();
            error_log("[SRT Daily Digest] Error: " . $error_message);
            
            $log = get_option('ftt_cron_log', array());
            $log_entry = array(
                'timestamp' => current_time('mysql'),
                'type' => 'digest',
                'status' => 'failed',
                'error' => $error_message,
                'flights_checked' => 0,
                'prices_recorded' => 0,
                'duration' => $duration,
            );
            
            $log[] = $log_entry;
            if (count($log) > 20) {
                $log = array_slice($log, -20);
            }
            update_option('ftt_cron_log', $log);
        }
    }
    
    /**
     * Send daily digest email to user
     */
    public static function send_daily_digest($user_id) {
        global $wpdb;
        
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        // Get all active alerts for this user
        $alerts = self::get_user_alerts($user_id, true);
        
        if (empty($alerts)) {
            return;
        }
        
        $digest_data = array(
            'good_deals' => array(),
            'trending_down' => array(),
            'trending_up' => array(),
            'stable' => array()
        );
        
        // Process each alert
        foreach ($alerts as $alert) {
            // Skip non-flight alerts
            if ($alert->alert_type !== 'daily_digest') {
                continue;
            }
            
            // Get event and leg data
            $event = get_post($alert->event_id);
            if (!$event) {
                continue;
            }
            
            $travel_legs = get_post_meta($alert->event_id, 'travel_legs', true);
            
            // Decode JSON if it's a string
            if (is_string($travel_legs)) {
                $travel_legs = json_decode($travel_legs, true);
            }
            
            if (empty($travel_legs) || !is_array($travel_legs) || !isset($travel_legs[$alert->leg_index])) {
                continue;
            }
            
            $leg = $travel_legs[$alert->leg_index];
            
            // Only process flight legs
            if ($leg['mode'] !== 'fly') {
                continue;
            }
            
            $origin = $leg['depart_airport'] ?? '';
            $destination = $leg['arrive_airport'] ?? '';
            $depart_date = $leg['depart_date'] ?? '';
            
            if (empty($origin) || empty($destination) || empty($depart_date)) {
                continue;
            }
            
            // Get price history
            $history = self::get_price_history($origin, $destination, $depart_date, 7);
            
            if (empty($history)) {
                continue;
            }
            
            // Calculate statistics
            $prices = array_column($history, 'price');
            $current = end($prices);
            $first = reset($prices);
            $avg = array_sum($prices) / count($prices);
            $min = min($prices);
            $max = max($prices);
            
            // Calculate trend
            $trend = 'stable';
            if ($current < $first * 0.95) {
                $trend = 'down';
            } elseif ($current > $first * 1.05) {
                $trend = 'up';
            }
            
            // Calculate days to departure
            $departure_time = strtotime($depart_date);
            $days_to_departure = floor(($departure_time - time()) / 86400);
            
            // Calculate change
            $change = $current - $first;
            $change_percent = ($change / $first) * 100;
            
            // Get Google insights from latest record
            $google_insights = null;
            $latest_record = end($history);
            if ($latest_record && !empty($latest_record->google_insights)) {
                $google_insights = json_decode($latest_record->google_insights, true);
            }
            
            // Build flight info
            $flight_info = array(
                'event_title' => $event->post_title,
                'route' => "$origin → $destination",
                'depart_date' => date('M j, Y', strtotime($depart_date)),
                'current_price' => $current,
                'avg_price' => $avg,
                'min_price' => $min,
                'max_price' => $max,
                'change' => $change,
                'change_percent' => $change_percent,
                'trend' => $trend,
                'days_to_departure' => $days_to_departure,
                'recommendation' => '',
                'google_insights' => $google_insights
            );
            
            // Categorize and add recommendation
            if ($current < $avg * 0.85) {
                // Good deal - 15% below average
                $flight_info['recommendation'] = '✅ Book now - Great price!';
                $digest_data['good_deals'][] = $flight_info;
            } elseif ($trend === 'down' && $days_to_departure > 30) {
                // Trending down with time
                $flight_info['recommendation'] = '⏳ Wait and watch - Prices dropping';
                $digest_data['trending_down'][] = $flight_info;
            } elseif ($trend === 'up' && $days_to_departure < 30) {
                // Trending up, departure soon
                $flight_info['recommendation'] = '⚠️ Book soon - Prices rising';
                $digest_data['trending_up'][] = $flight_info;
            } else {
                // Stable prices
                $flight_info['recommendation'] = '➡️ Monitor - Prices stable';
                $digest_data['stable'][] = $flight_info;
            }
        }
        
        // Send email if there's data
        $total_flights = count($digest_data['good_deals']) + count($digest_data['trending_down']) + 
                        count($digest_data['trending_up']) + count($digest_data['stable']);
        
        if ($total_flights > 0) {
            $email_body = self::build_digest_email($user, $digest_data);
            
            $from_name = self::get_notification_from_name();
            $from_email = self::get_notification_email();
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                sprintf('From: %s <%s>', $from_name, $from_email),
            );
            $subject = FTT_Email_Templates::render_subject('daily_digest', [
                'flight_count' => $total_flights,
            ]);
            
            $sent = wp_mail($user->user_email, $subject, $email_body, $headers);
            
            error_log(sprintf('[SRT Daily Digest] Sent to %s (%d flights)', $user->user_email, $total_flights));
            
            return $sent;
        }
        
        return false;
    }
    
    /**
     * Build digest email HTML
     */
    private static function build_digest_email($user, $digest_data) {
        $site_name = get_bloginfo('name');
        $date = date('l, F j, Y');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
            
            <div style="background: #fff; border-radius: 8px; padding: 30px; margin-bottom: 20px;">
                <h1 style="color: #0066cc; border-bottom: 3px solid #0066cc; padding-bottom: 10px; margin-top: 0; font-size: 24px;">✈️ Daily Flight Price Digest</h1>
                <p style="margin: 15px 0; font-size: 16px;">Hello <?php echo esc_html($user->display_name); ?>,</p>
                <p style="margin: 15px 0; font-size: 14px; color: #666;">Here's your daily flight price update for <?php echo $date; ?>.</p>
            </div>
            
            <?php if (!empty($digest_data['good_deals'])): ?>
                <div style="background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <h2 style="color: #155724; margin-top: 0; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; font-size: 18px;">✅ Good Deals Now</h2>
                    <p style="color: #155724; font-size: 14px; margin: 10px 0;">These flights are at least 15% below their average price. Consider booking!</p>
                    <?php foreach ($digest_data['good_deals'] as $flight): ?>
                        <?php echo self::render_flight_card($flight, 'good'); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($digest_data['trending_up'])): ?>
                <div style="background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <h2 style="color: #721c24; margin-top: 0; padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; border-radius: 4px; font-size: 18px;">⚠️ Trending Up - Book Soon</h2>
                    <p style="color: #721c24; font-size: 14px; margin: 10px 0;">Prices are rising and departure is approaching. Book soon to avoid higher prices.</p>
                    <?php foreach ($digest_data['trending_up'] as $flight): ?>
                        <?php echo self::render_flight_card($flight, 'urgent'); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($digest_data['trending_down'])): ?>
                <div style="background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <h2 style="color: #856404; margin-top: 0; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; font-size: 18px;">📉 Trending Down - Wait and Watch</h2>
                    <p style="color: #856404; font-size: 14px; margin: 10px 0;">Prices are dropping and you have time. Continue monitoring for better deals.</p>
                    <?php foreach ($digest_data['trending_down'] as $flight): ?>
                        <?php echo self::render_flight_card($flight, 'wait'); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($digest_data['stable'])): ?>
                <div style="background: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <h2 style="color: #0c5460; margin-top: 0; padding: 10px; background: #d1ecf1; border-left: 4px solid #17a2b8; border-radius: 4px; font-size: 18px;">➡️ Stable Prices</h2>
                    <p style="color: #0c5460; font-size: 14px; margin: 10px 0;">Prices are holding steady. No immediate action needed.</p>
                    <?php foreach ($digest_data['stable'] as $flight): ?>
                        <?php echo self::render_flight_card($flight, 'stable'); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div style="background: #fff; border-radius: 8px; padding: 20px; margin-top: 30px; text-align: center; font-size: 12px; color: #666;">
                <p style="margin: 10px 0;">This digest is sent daily at 2am. You can manage your price alerts in your <a href="<?php echo esc_url( self::get_dashboard_url() ); ?>" style="color: #0066cc; text-decoration: none;">Member Dashboard</a>.</p>
                <p style="margin: 10px 0;">Need help? Email <a href="mailto:<?php echo esc_attr( self::get_support_email() ); ?>" style="color: #0066cc; text-decoration: none;"><?php echo esc_html( self::get_support_email() ); ?></a></p>
                <p style="margin: 10px 0;">&copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?></p>
            </div>
            
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render a single flight card for email
     */
    private static function render_flight_card($flight, $rec_type) {
        $trend_icons = array('up' => '↗', 'down' => '↘', 'stable' => '→');
        $trend_icon = $trend_icons[$flight['trend']];
        
        // Color schemes for different recommendation types
        $colors = array(
            'good' => array('bg' => '#d4edda', 'border' => '#c3e6cb', 'text' => '#155724'),
            'urgent' => array('bg' => '#f8d7da', 'border' => '#f5c6cb', 'text' => '#721c24'),
            'wait' => array('bg' => '#fff3cd', 'border' => '#ffeeba', 'text' => '#856404'),
            'stable' => array('bg' => '#d1ecf1', 'border' => '#bee5eb', 'text' => '#0c5460')
        );
        
        $color = $colors[$rec_type];
        
        ob_start();
        ?>
        <table width="100%" cellpadding="0" cellspacing="0" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; margin: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <tr>
                <td style="padding: 20px;">
                    <!-- Event Title -->
                    <div style="font-weight: bold; font-size: 16px; color: #333; margin-bottom: 8px;">
                        <?php echo esc_html($flight['event_title']); ?>
                    </div>
                    
                    <!-- Route and Date -->
                    <div style="font-size: 14px; color: #666; margin-bottom: 15px;">
                        <span style="font-weight: 500;"><?php echo esc_html(str_replace('→', 'to', $flight['route'])); ?></span>
                        <span style="color: #999;"> • </span>
                        <span><?php echo esc_html($flight['depart_date']); ?></span>
                        <span style="display: inline-block; margin-left: 10px; padding: 3px 8px; background: <?php echo $color['bg']; ?>; color: <?php echo $color['text']; ?>; border-radius: 12px; font-size: 12px; font-weight: 600;">
                            <?php echo $trend_icon; ?> <?php echo ucfirst($flight['trend']); ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($flight['google_insights'])): ?>
                        <?php 
                        $insights = $flight['google_insights'];
                        $price_level = isset($insights['price_level']) ? strtolower($insights['price_level']) : '';
                        $typical_low = isset($insights['typical_price_range'][0]) ? $insights['typical_price_range'][0] : null;
                        $typical_high = isset($insights['typical_price_range'][1]) ? $insights['typical_price_range'][1] : null;
                        
                        // Google Flights Price Insight Banner
                        if ($price_level && $typical_high):
                            $bg_color = $price_level === 'low' ? '#ecfdf5' : ($price_level === 'high' ? '#fef2f2' : '#fffbeb');
                            $border_color = $price_level === 'low' ? '#10b981' : ($price_level === 'high' ? '#ef4444' : '#f59e0b');
                            $text_color = $price_level === 'low' ? '#065f46' : ($price_level === 'high' ? '#991b1b' : '#92400e');
                            $icon = $price_level === 'low' ? '✨' : ($price_level === 'high' ? '⚠️' : 'ℹ️');
                            
                            // Calculate savings for low prices
                            $savings = $typical_high - $flight['current_price'];
                            $message = '';
                            
                            if ($price_level === 'low' && $savings > 0) {
                                $message = sprintf('Prices are currently <strong>low</strong> — $%d cheaper than usual for this route', round($savings));
                            } elseif ($price_level === 'high') {
                                $message = sprintf('Prices are currently <strong>high</strong> — Consider waiting or checking alternative dates');
                            } else {
                                $message = sprintf('Prices are <strong>typical</strong> for this route');
                            }
                        ?>
                        <!-- Google Flights Price Insight -->
                        <div style="background: <?php echo $bg_color; ?>; border-left: 4px solid <?php echo $border_color; ?>; padding: 12px 15px; margin-bottom: 15px; border-radius: 4px;">
                            <div style="font-size: 13px; color: <?php echo $text_color; ?>; font-weight: 500;">
                                <?php echo $icon; ?> <strong>Google Flights:</strong> <?php echo $message; ?>
                            </div>
                            <?php if ($typical_low && $typical_high): ?>
                                <div style="font-size: 12px; color: <?php echo $text_color; ?>; opacity: 0.85; margin-top: 4px;">
                                    Typical price range: $<?php echo number_format($typical_low, 0); ?>–$<?php echo number_format($typical_high, 0); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Main Price Info -->
                    <table width="100%" cellpadding="0" cellspacing="0" style="background: #f9f9f9; border-radius: 6px; margin: 15px 0;">
                        <tr>
                            <td style="padding: 15px; width: 33%; text-align: center; border-right: 1px solid #e0e0e0;">
                                <div style="font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px;">Current Price</div>
                                <div style="font-size: 20px; font-weight: bold; color: #333;">$<?php echo number_format($flight['current_price'], 0); ?></div>
                            </td>
                            <td style="padding: 15px; width: 34%; text-align: center; border-right: 1px solid #e0e0e0;">
                                <div style="font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px;">7-Day Change</div>
                                <div style="font-size: 18px; font-weight: bold; color: <?php echo $flight['change'] < 0 ? '#28a745' : ($flight['change'] > 0 ? '#dc3545' : '#666'); ?>;">
                                    <?php echo $flight['change'] > 0 ? '+' : ''; ?>$<?php echo number_format(abs($flight['change']), 0); ?>
                                    <span style="font-size: 14px;">(<?php echo $flight['change'] > 0 ? '+' : ''; ?><?php echo number_format($flight['change_percent'], 1); ?>%)</span>
                                </div>
                            </td>
                            <td style="padding: 15px; width: 33%; text-align: center;">
                                <div style="font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px;">Days Left</div>
                                <div style="font-size: 20px; font-weight: bold; color: #333;"><?php echo $flight['days_to_departure']; ?></div>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- 7-Day Statistics -->
                    <table width="100%" cellpadding="0" cellspacing="0" style="background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; margin: 10px 0;">
                        <tr>
                            <td style="padding: 10px; width: 33%; text-align: center; border-right: 1px solid #e0e0e0;">
                                <div style="font-size: 10px; color: #999; text-transform: uppercase; margin-bottom: 3px;">Min</div>
                                <div style="font-size: 14px; font-weight: 600; color: #28a745;">$<?php echo number_format($flight['min_price'], 0); ?></div>
                            </td>
                            <td style="padding: 10px; width: 34%; text-align: center; border-right: 1px solid #e0e0e0;">
                                <div style="font-size: 10px; color: #999; text-transform: uppercase; margin-bottom: 3px;">Avg</div>
                                <div style="font-size: 14px; font-weight: 600; color: #666;">$<?php echo number_format($flight['avg_price'], 0); ?></div>
                            </td>
                            <td style="padding: 10px; width: 33%; text-align: center;">
                                <div style="font-size: 10px; color: #999; text-transform: uppercase; margin-bottom: 3px;">Max</div>
                                <div style="font-size: 14px; font-weight: 600; color: #dc3545;">$<?php echo number_format($flight['max_price'], 0); ?></div>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Recommendation -->
                    <div style="padding: 12px 15px; background: <?php echo $color['bg']; ?>; border: 1px solid <?php echo $color['border']; ?>; border-radius: 6px; margin-top: 15px; font-weight: 600; font-size: 14px; color: <?php echo $color['text']; ?>;">
                        <?php echo $flight['recommendation']; ?>
                    </div>
                </td>
            </tr>
        </table>
        <?php
        return ob_get_clean();
    }
}

// Initialize
FTT_Price_Tracking::init();
