<?php
/**
 * Flight Price Tracking
 *
 * @package Summer_Regiment_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SRT Price Tracking Class
 */
class SRT_Price_Tracking {
    
    /**
     * Initialize
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'create_tables'));
        add_action('srt_check_flight_prices', array(__CLASS__, 'check_all_prices'));
        add_action('srt_daily_digest', array(__CLASS__, 'process_daily_digests'));
        add_filter('cron_schedules', array(__CLASS__, 'add_custom_cron_schedule'));
        
        // Schedule cron job if not already scheduled
        if (!wp_next_scheduled('srt_check_flight_prices')) {
            wp_schedule_event(time(), 'fourtimesdaily', 'srt_check_flight_prices');
        }
        
        // Schedule daily digest at 2am
        if (!wp_next_scheduled('srt_daily_digest')) {
            $tomorrow_2am = strtotime('tomorrow 2:00am');
            wp_schedule_event($tomorrow_2am, 'daily_2am', 'srt_daily_digest');
        }
    }
    
    /**
     * Add custom cron schedule for four times daily
     */
    public static function add_custom_cron_schedule($schedules) {
        $schedules['fourtimesdaily'] = array(
            'interval' => 21600, // 6 hours in seconds (24 hours / 4)
            'display'  => __('Four Times Daily (Every 6 Hours)', 'schedule-collaboration-tracking')
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
        $table_name = $wpdb->prefix . 'srt_price_history';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) NOT NULL,
            leg_index int(11) NOT NULL,
            origin varchar(3) NOT NULL,
            destination varchar(3) NOT NULL,
            depart_date date NOT NULL,
            price decimal(10,2) NOT NULL,
            checked_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY event_id (event_id),
            KEY route_date (origin, destination, depart_date),
            KEY checked_at (checked_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Price alerts table
        $alerts_table = $wpdb->prefix . 'srt_price_alerts';
        
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
        $table_name = $wpdb->prefix . 'srt_price_history';
        
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
        $table_name = $wpdb->prefix . 'srt_price_history';
        
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
        $table_name = $wpdb->prefix . 'srt_price_history';
        
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
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'event_id'     => $event_id,
                'leg_index'    => $leg_index,
                'origin'       => $origin,
                'destination'  => $destination,
                'depart_date'  => $depart_date,
                'price'        => $price,
                'checked_at'   => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%f', '%s')
        );
        
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
        $legs = SRT_Flight_Linking::get_flight_group_legs($group_id);
        
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
        
        // Get API credentials
        $settings = get_option('srt_settings', array());
        $api_key = $settings['serpapi_api_key'] ?? '';
        
        if (empty($api_key)) {
            error_log('SRT Price Tracking: SerpAPI key not configured');
            return;
        }
        
        $type_label = $source === 'manual' ? 'manual' : 'scheduled';
        error_log("SRT: Starting $type_label price check...");
        
        // Base query arguments - get all future flight events
        $args = array(
            'post_type'      => 'srt_event',
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
            $is_parent = SRT_Roles::is_parent($current_user_id);
            
            if ($is_parent) {
                // Include events for all children
                $children = SRT_Roles::get_children($current_user_id);
                
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
                        
                        // Method 1: Check if arrive_date spans multiple days (likely return date)
                        if (!empty($leg['arrive_date']) && !empty($leg['depart_date'])) {
                            $depart_time = strtotime($leg['depart_date']);
                            $arrive_time = strtotime($leg['arrive_date']);
                            $days_diff = ($arrive_time - $depart_time) / (60 * 60 * 24);
                            
                            if ($days_diff >= 1) {
                                $is_round_trip = true;
                                $return_date = $leg['arrive_date'];
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
                            $recorded = self::record_price($event->ID, $index, $leg['depart_airport'], $leg['arrive_airport'], $depart_date, $price, 'serpapi');
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
        $log = get_option('srt_cron_log', array());
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'type' => $source,
        );
        
        if ($source === 'manual') {
            $log_entry['user'] = wp_get_current_user()->user_login;
        }
        
        $log[] = $log_entry;
        
        // Keep only last 20 entries
        if (count($log) > 20) {
            $log = array_slice($log, -20);
        }
        update_option('srt_cron_log', $log);
        
        // Update success tracking
        update_option('srt_cron_last_run', current_time('mysql'));
        update_option('srt_cron_last_success', current_time('mysql'));
        update_option('srt_cron_total_runs', get_option('srt_cron_total_runs', 0) + 1);
        update_option('srt_cron_last_stats', array(
            'flights_checked' => $flights_checked,
            'prices_recorded' => $prices_recorded,
            'duration' => $duration,
        ));
    }
    
    /**
     * Fetch flight price from SerpAPI (Google Flights) - Public method
     */
    public static function fetch_flight_price_serpapi($origin, $destination, $date, $return_date = null) {
        $settings = get_option('srt_settings', array());
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
        
        // SerpAPI returns best flights in 'best_flights' array
        if (isset($body['best_flights'][0]['price'])) {
            return array(
                'price' => floatval($body['best_flights'][0]['price']),
                'debug' => $debug_info,
            );
        }
        
        // Fallback to other_flights
        if (isset($body['other_flights'][0]['price'])) {
            return array(
                'price' => floatval($body['other_flights'][0]['price']),
                'debug' => $debug_info,
            );
        }
        
        // Check for errors
        if (isset($body['error'])) {
            $debug_info['serpapi_error'] = $body['error'];
        }
        
        return array(
            'price' => false,
            'debug' => $debug_info,
        );
    }
    
    /**
     * Check if price triggers any user alerts
     */
    public static function check_price_alerts($event_id, $leg_index, $current_price) {
        global $wpdb;
        $alerts_table = $wpdb->prefix . 'srt_price_alerts';
        
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
        $table_name = $wpdb->prefix . 'srt_price_history';
        
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
        
        $subject = sprintf('[%s] Flight Price Alert: %s',
            get_bloginfo('name'),
            $event->post_title
        );
        
        $message = sprintf(
            "Hi %s,\n\nGood news! We found a price update for your flight:\n\n" .
            "Event: %s\n" .
            "Route: %s (%s) → %s (%s)\n" .
            "Date: %s\n" .
            "Current Price: $%s\n\n" .
            "View event: %s\n\n" .
            "Happy travels!\n%s",
            $user->display_name,
            $event->post_title,
            $leg['depart_location'],
            $leg['depart_airport'],
            $leg['arrive_location'],
            $leg['arrive_airport'],
            $leg['depart_date'] ?? 'TBD',
            number_format($current_price, 2),
            get_permalink($event->ID),
            get_bloginfo('name')
        );
        
        error_log("SRT: Sending price alert email to {$user->user_email}");
        $result = wp_mail($user->user_email, $subject, $message);
        error_log("SRT: Email send result: " . ($result ? 'success' : 'failed'));
        
        do_action('srt_price_alert_sent', $alert, $current_price, $user, $event);
    }
    
    /**
     * Create price alert for user
     */
    public static function create_alert($user_id, $event_id, $leg_index, $alert_type, $threshold_price = null, $threshold_percent = null) {
        global $wpdb;
        $alerts_table = $wpdb->prefix . 'srt_price_alerts';
        
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
        $alerts_table = $wpdb->prefix . 'srt_price_alerts';
        
        $where = $wpdb->prepare("WHERE user_id = %d", $user_id);
        if ($active_only) {
            $where .= " AND is_active = 1";
        }
        
        return $wpdb->get_results("SELECT * FROM $alerts_table $where ORDER BY created_at DESC");
    }
    
    /**
     * Process daily digests for all users
     */
    public static function process_daily_digests() {
        global $wpdb;
        $alerts_table = $wpdb->prefix . 'srt_price_alerts';
        
        // Get all users with active daily digest alerts
        $users = $wpdb->get_results(
            "SELECT DISTINCT user_id FROM $alerts_table WHERE alert_type = 'daily_digest' AND is_active = 1"
        );
        
        error_log(sprintf('[SRT Daily Digest] Processing %d users', count($users)));
        
        foreach ($users as $user) {
            self::send_daily_digest($user->user_id);
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
                'recommendation' => ''
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
            
            $headers = array('Content-Type: text/html; charset=UTF-8');
            $subject = sprintf('Daily Flight Price Digest - %d flights tracked', $total_flights);
            
            wp_mail($user->user_email, $subject, $email_body, $headers);
            
            error_log(sprintf('[SRT Daily Digest] Sent to %s (%d flights)', $user->user_email, $total_flights));
        }
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
                <p style="margin: 10px 0;">This digest is sent daily at 2am. You can manage your price alerts in your <a href="<?php echo home_url('/member-dashboard/'); ?>" style="color: #0066cc; text-decoration: none;">Member Dashboard</a>.</p>
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
SRT_Price_Tracking::init();
