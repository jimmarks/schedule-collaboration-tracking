<?php
/**
 * Flight Search Service - Unified flight price checking
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FTT_Flight_Search_Service {
    
    /**
     * Build search payload from event data
     * 
     * @param int $event_id Event ID
     * @param string $scope 'trip' or 'leg'
     * @param int|null $leg_index Leg index for leg-level searches
     * @return array|WP_Error Search payload or error
     */
    public static function build_search_payload($event_id, $scope = 'trip', $leg_index = null) {
        $travel_legs = json_decode(get_post_meta($event_id, 'travel_legs', true) ?: '[]', true);
        
        if (empty($travel_legs)) {
            return new WP_Error('no_travel_legs', 'Event has no travel legs');
        }
        
        // Filter to only flight legs
        $flight_legs = array();
        foreach ($travel_legs as $idx => $leg) {
            // Extract depart_date - might be 'depart_date' or extracted from 'depart_datetime'
            $depart_date = $leg['depart_date'] ?? null;
            if (empty($depart_date) && !empty($leg['depart_datetime'])) {
                $depart_date = substr($leg['depart_datetime'], 0, 10);
            }
            
            if (!empty($leg['mode']) && $leg['mode'] === 'fly' && 
                !empty($leg['depart_airport']) && !empty($leg['arrive_airport']) &&
                !empty($depart_date)) {
                $flight_legs[] = array(
                    'leg_index' => $idx,
                    'origin' => $leg['depart_airport'],
                    'destination' => $leg['arrive_airport'],
                    'depart_date' => $depart_date,
                    'depart_time_of_day' => $leg['depart_time_of_day'] ?? null,
                    'booked' => $leg['booked'] ?? false,
                );
            }
        }
        
        if (empty($flight_legs)) {
            return new WP_Error('no_flight_legs', 'Event has no valid flight legs');
        }
        
        // Build payload based on scope
        if ($scope === 'leg') {
            // Single leg search
            if ($leg_index === null) {
                return new WP_Error('missing_leg_index', 'Leg index required for leg-level search');
            }
            
            $leg = null;
            foreach ($flight_legs as $flight_leg) {
                if ($flight_leg['leg_index'] === $leg_index) {
                    $leg = $flight_leg;
                    break;
                }
            }
            
            if (!$leg) {
                return new WP_Error('invalid_leg_index', 'Leg index not found');
            }
            
            if ($leg['booked']) {
                return new WP_Error('leg_booked', 'Cannot check price for booked flight');
            }
            
            return array(
                'event_id' => $event_id,
                'scope' => 'leg',
                'provider' => 'google_flights',
                'legs' => array($leg),
            );
            
        } else {
            // Trip-level (round-trip) search
            if (count($flight_legs) !== 2) {
                return new WP_Error('not_round_trip', 'Trip-level search requires exactly 2 flight legs');
            }
            
            // Verify round-trip pattern: A → B, B → A
            if ($flight_legs[0]['origin'] !== $flight_legs[1]['destination'] ||
                $flight_legs[0]['destination'] !== $flight_legs[1]['origin']) {
                return new WP_Error('not_round_trip', 'Legs do not form a round-trip pattern');
            }
            
            if ($flight_legs[0]['booked'] || $flight_legs[1]['booked']) {
                return new WP_Error('flight_booked', 'Cannot check price for booked flights');
            }
            
            return array(
                'event_id' => $event_id,
                'scope' => 'trip',
                'provider' => 'google_flights',
                'legs' => $flight_legs,
            );
        }
    }
    
    /**
     * Build provider-specific query parameters
     * 
     * @param array $payload Search payload
     * @return array Query parameters for SerpAPI
     */
    public static function build_provider_query($payload) {
        $legs = $payload['legs'];
        
        if ($payload['scope'] === 'leg') {
            // One-way search
            $leg = $legs[0];
            return array(
                'engine' => 'google_flights',
                'departure_id' => $leg['origin'],
                'arrival_id' => $leg['destination'],
                'outbound_date' => $leg['depart_date'],
                'type' => '2', // 2 = one way
                'currency' => 'USD',
                'hl' => 'en',
            );
        } else {
            // Round-trip search
            return array(
                'engine' => 'google_flights',
                'departure_id' => $legs[0]['origin'],
                'arrival_id' => $legs[0]['destination'],
                'outbound_date' => $legs[0]['depart_date'],
                'return_date' => $legs[1]['depart_date'],
                'type' => '1', // 1 = round trip
                'currency' => 'USD',
                'hl' => 'en',
            );
        }
    }
    
    /**
     * Run price check
     * 
     * @param array $payload Search payload
     * @param string $check_type 'manual' or 'scheduled'
     * @return array Result with price and metadata
     */
    public static function run_price_check($payload, $check_type = 'manual') {
        // Get API key
        $settings = get_option('ftt_settings', array());
        $api_key = $settings['serpapi_api_key'] ?? '';
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'error' => 'SerpAPI key not configured',
            );
        }
        
        // Build query
        $query = self::build_provider_query($payload);
        $query['api_key'] = $api_key;
        
        // DEBUG: Log the query being sent to SerpAPI
        error_log('🛫 SerpAPI Query: ' . print_r($query, true));
        if (isset($query['type'])) {
            error_log('✈️ Flight Type Parameter: type=' . $query['type'] . ' (1=round-trip, 2=one-way)');
        }
        
        // Make API request
        $url = 'https://serpapi.com/search';
        $response = wp_remote_get(add_query_arg($query, $url), array(
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            if (class_exists('FTT_API_Tracker')) {
                FTT_API_Tracker::record('serpapi', false);
            }
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Extract price
        $price = null;
        $price_insights = $body['price_insights'] ?? null;
        $google_flights_url = $body['search_metadata']['google_flights_url'] ?? null;
        
        // DEBUG: Log what URL SerpAPI returned
        if ($google_flights_url) {
            error_log('🔗 Google Flights URL from SerpAPI: ' . $google_flights_url);
            // Check if URL contains trip type indicators
            if (strpos($google_flights_url, 'tfs=') !== false) {
                error_log('   📋 URL uses encoded tfs parameter (trip type encoded in token)');
            }
            if (preg_match('/type=(\d+)/', $google_flights_url, $matches)) {
                error_log('   🎫 URL contains type=' . $matches[1] . ' parameter');
            }
        } else {
            error_log('⚠️ No google_flights_url in SerpAPI response metadata');
        }
        
        if (isset($body['best_flights'][0]['price'])) {
            $price = floatval($body['best_flights'][0]['price']);
        } elseif (isset($body['other_flights'][0]['price'])) {
            $price = floatval($body['other_flights'][0]['price']);
        }
        
        if ($price) {
            if (class_exists('FTT_API_Tracker')) {
                FTT_API_Tracker::record('serpapi', true);
            }
            
            return array(
                'success' => true,
                'price' => $price,
                'price_insights' => $price_insights,
                'google_flights_url' => $google_flights_url,
                'raw_response' => $body,
                'check_type' => $check_type,
                'checked_at' => current_time('mysql'),
            );
        }
        
        // No price found
        if (class_exists('FTT_API_Tracker')) {
            FTT_API_Tracker::record('serpapi', false);
        }
        
        return array(
            'success' => false,
            'error' => $body['error'] ?? 'No price data returned from API',
            'raw_response' => $body,
        );
    }
    
    /**
     * Save price snapshot
     * 
     * @param array $payload Search payload
     * @param array $result Price check result
     * @return bool Success
     */
    public static function save_price_snapshot($payload, $result) {
        if (!$result['success'] || empty($result['price'])) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ftt_price_history';
        
        $legs = $payload['legs'];
        $google_insights = null;
        $google_flights_url = $result['google_flights_url'] ?? null;
        
        if (!empty($result['price_insights'])) {
            $google_insights = wp_json_encode($result['price_insights']);
        }
        
        if ($payload['scope'] === 'leg') {
            // Save leg-level price
            $leg = $legs[0];
            
            $insert_data = array(
                'scope' => 'leg',
                'event_id' => $payload['event_id'],
                'leg_index' => $leg['leg_index'],
                'origin' => $leg['origin'],
                'destination' => $leg['destination'],
                'depart_date' => $leg['depart_date'],
                'return_date' => null,
                'trip_hash' => null,
                'price' => $result['price'],
                'checked_at' => $result['checked_at'],
            );
            
            $format = array('%s', '%d', '%d', '%s', '%s', '%s', NULL, NULL, '%f', '%s');
            
        } else {
            // Save trip-level price
            $trip_hash = md5(
                $payload['event_id'] . ':' . 
                $legs[0]['origin'] . ':' . 
                $legs[0]['destination'] . ':' . 
                $legs[0]['depart_date'] . ':' . 
                $legs[1]['depart_date']
            );
            
            $insert_data = array(
                'scope' => 'trip',
                'event_id' => $payload['event_id'],
                'leg_index' => null,
                'origin' => $legs[0]['origin'],
                'destination' => $legs[0]['destination'],
                'depart_date' => $legs[0]['depart_date'],
                'return_date' => $legs[1]['depart_date'],
                'trip_hash' => $trip_hash,
                'price' => $result['price'],
                'checked_at' => $result['checked_at'],
            );
            
            $format = array('%s', '%d', NULL, '%s', '%s', '%s', '%s', '%s', '%f', '%s');
        }
        
        if ($google_insights) {
            $insert_data['google_insights'] = $google_insights;
            $format[] = '%s';
        }
        
        if ($google_flights_url) {
            $insert_data['google_flights_url'] = $google_flights_url;
            $format[] = '%s';
        }
        
        $wpdb_result = $wpdb->insert($table_name, $insert_data, $format);
        
        if ($wpdb_result === false) {
            error_log("FTT Flight Search Service: Failed to save price - " . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    /**
     * Evaluate alert rules and send notifications
     * 
     * @param array $payload Search payload
     * @param array $result Price check result
     * @return array Alert evaluation results
     */
    public static function evaluate_alert_rules($payload, $result) {
        if (!$result['success'] || empty($result['price'])) {
            return array('triggered' => array());
        }
        
        global $wpdb;
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
        $current_price = $result['price'];
        $triggered = array();
        
        if ($payload['scope'] === 'leg') {
            // Check leg-level alerts
            $leg = $payload['legs'][0];
            
            $alerts = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $alerts_table 
                WHERE event_id = %d 
                AND leg_index = %d 
                AND scope = 'leg'
                AND is_active = 1",
                $payload['event_id'],
                $leg['leg_index']
            ));
            
        } else {
            // Check trip-level alerts
            $legs = $payload['legs'];
            $trip_hash = md5(
                $payload['event_id'] . ':' . 
                $legs[0]['origin'] . ':' . 
                $legs[0]['destination'] . ':' . 
                $legs[0]['depart_date'] . ':' . 
                $legs[1]['depart_date']
            );
            
            $alerts = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $alerts_table 
                WHERE event_id = %d 
                AND scope = 'trip'
                AND trip_hash = %s
                AND is_active = 1",
                $payload['event_id'],
                $trip_hash
            ));
        }
        
        foreach ($alerts as $alert) {
            $should_alert = false;
            $alert_reason = '';
            
            switch ($alert->alert_type) {
                case 'price_drop':
                    if ($current_price <= $alert->threshold_price) {
                        $should_alert = true;
                        $alert_reason = "Price dropped to $" . number_format($current_price, 2) . " (threshold: $" . number_format($alert->threshold_price, 2) . ")";
                    }
                    break;
                    
                case 'percent_drop':
                    // Get previous price for comparison
                    if ($payload['scope'] === 'leg') {
                        $previous_price = FTT_Price_Tracking::get_previous_price($payload['event_id'], $payload['legs'][0]['leg_index']);
                    } else {
                        $previous_price = FTT_Price_Tracking::get_previous_trip_price(
                            $legs[0]['origin'],
                            $legs[0]['destination'],
                            $legs[0]['depart_date'],
                            $legs[1]['depart_date']
                        );
                    }
                    
                    if ($previous_price && $previous_price > 0) {
                        $percent_change = (($previous_price - $current_price) / $previous_price) * 100;
                        if ($percent_change >= $alert->threshold_percent) {
                            $should_alert = true;
                            $alert_reason = "Price dropped by " . number_format($percent_change, 1) . "% (threshold: {$alert->threshold_percent}%)";
                        }
                    }
                    break;
                    
                case 'good_deal':
                    // Check if 15% below average
                    if ($payload['scope'] === 'leg') {
                        $stats = FTT_Price_Tracking::get_price_stats($payload['event_id'], $payload['legs'][0]['leg_index']);
                    } else {
                        $stats = FTT_Price_Tracking::get_trip_price_stats(
                            $legs[0]['origin'],
                            $legs[0]['destination'],
                            $legs[0]['depart_date'],
                            $legs[1]['depart_date']
                        );
                    }
                    
                    if ($stats && $stats['avg'] > 0) {
                        $threshold = $stats['avg'] * 0.85;
                        if ($current_price <= $threshold) {
                            $should_alert = true;
                            $alert_reason = "Good deal: $" . number_format($current_price, 2) . " (15% below average of $" . number_format($stats['avg'], 2) . ")";
                        }
                    }
                    break;
                    
                case 'daily_digest':
                    // Digest alerts are handled separately by cron
                    break;
            }
            
            if ($should_alert) {
                // Send alert
                if ($payload['scope'] === 'leg') {
                    FTT_Price_Tracking::send_price_alert($payload['event_id'], $payload['legs'][0]['leg_index'], $current_price, $alert, $alert_reason);
                } else {
                    FTT_Price_Tracking::send_trip_price_alert($payload['event_id'], $current_price, $alert, $alert_reason);
                }
                
                $triggered[] = array(
                    'alert_id' => $alert->id,
                    'alert_type' => $alert->alert_type,
                    'reason' => $alert_reason,
                );
            }
        }
        
        return array('triggered' => $triggered);
    }
    
    /**
     * Unified price check - combines all steps
     * 
     * @param int $event_id Event ID
     * @param string $scope 'trip' or 'leg'
     * @param int|null $leg_index Leg index for leg-level
     * @param string $check_type 'manual' or 'scheduled'
     * @return array Complete result
     */
    public static function check_price($event_id, $scope = 'trip', $leg_index = null, $check_type = 'manual') {
        // Build payload
        $payload = self::build_search_payload($event_id, $scope, $leg_index);
        
        if (is_wp_error($payload)) {
            return array(
                'success' => false,
                'error' => $payload->get_error_message(),
            );
        }
        
        // Run price check
        $result = self::run_price_check($payload, $check_type);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Save snapshot
        self::save_price_snapshot($payload, $result);
        
        // Evaluate alerts (only for scheduled checks)
        $alerts = array('triggered' => array());
        if ($check_type === 'scheduled') {
            $alerts = self::evaluate_alert_rules($payload, $result);
        }
        
        // Get statistics
        if ($scope === 'leg') {
            $leg = $payload['legs'][0];
            $stats = FTT_Price_Tracking::get_price_stats(
                $leg['origin'],
                $leg['destination'],
                $leg['depart_date']
            );
        } else {
            $legs = $payload['legs'];
            $stats = FTT_Price_Tracking::get_trip_price_stats(
                $legs[0]['origin'],
                $legs[0]['destination'],
                $legs[0]['depart_date'],
                $legs[1]['depart_date']
            );
        }
        
        // Determine trip type for display
        $trip_type = ($scope === 'leg') ? 'one-way' : 'round-trip';
        
        return array(
            'success' => true,
            'price' => $result['price'],
            'price_insights' => $result['price_insights'],
            'google_flights_url' => $result['google_flights_url'] ?? null,
            'trip_type' => $trip_type,
            'stats' => $stats,
            'alerts' => $alerts,
            'payload' => $payload,
        );
    }
}
