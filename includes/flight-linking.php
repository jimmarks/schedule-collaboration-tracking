<?php
/**
 * Flight Linking and Price Comparison
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class SRT_Flight_Linking {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Hook into REST API for suggestions
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public static function register_routes() {
        register_rest_route('srt/v1', '/flight-suggestions/(?P<event_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_flight_suggestions'),
            'permission_callback' => array('SRT_REST', 'check_user_permission'),
            'args'                => array(
                'event_id' => array(
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
        
        register_rest_route('srt/v1', '/link-flights', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'link_flights'),
            'permission_callback' => array('SRT_REST', 'check_user_permission'),
            'args'                => array(
                'event_ids' => array(
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_array($param) && count($param) >= 2;
                    },
                ),
                'leg_indices' => array(
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_array($param) && count($param) >= 2;
                    },
                ),
            ),
        ));
        
        register_rest_route('srt/v1', '/unlink-flight', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'unlink_flight'),
            'permission_callback' => array('SRT_REST', 'check_user_permission'),
            'args'                => array(
                'event_id' => array(
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
                'leg_index' => array(
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
        
        register_rest_route('srt/v1', '/flight-group-pricing/(?P<group_id>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_flight_group_pricing'),
            'permission_callback' => array('SRT_REST', 'check_user_permission'),
            'args'                => array(
                'group_id' => array(
                    'required' => true,
                ),
            ),
        ));
    }
    
    /**
     * Find potential flight links for an event
     * Detects reversed routes within 30 days
     */
    public static function get_flight_suggestions($request) {
        $event_id = $request->get_param('event_id');
        
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'srt_event') {
            return new WP_Error('invalid_event', 'Invalid event ID', array('status' => 404));
        }
        
        $travel_legs = json_decode(get_post_meta($event_id, 'travel_legs', true), true);
        if (empty($travel_legs)) {
            return array('suggestions' => array());
        }
        
        $member_id = get_post_meta($event_id, 'member_id', true);
        $suggestions = array();
        
        // For each leg, look for potential matches
        foreach ($travel_legs as $leg_index => $leg) {
            // Skip if already linked
            if (!empty($leg['flight_group_id'])) {
                continue;
            }
            
            // Only check flights
            if ($leg['mode'] !== 'flight' || empty($leg['depart_airport']) || empty($leg['arrive_airport'])) {
                continue;
            }
            
            // Find reversed routes within 30 days
            $matches = self::find_reversed_routes($leg, $member_id, $event_id);
            
            if (!empty($matches)) {
                $suggestions[] = array(
                    'event_id'  => $event_id,
                    'leg_index' => $leg_index,
                    'leg'       => $leg,
                    'matches'   => $matches,
                );
            }
        }
        
        return array('suggestions' => $suggestions);
    }
    
    /**
     * Find reversed routes for a given flight leg
     */
    private static function find_reversed_routes($leg, $member_id, $exclude_event_id = 0) {
        global $wpdb;
        
        $depart_date = strtotime($leg['depart_date']);
        if (!$depart_date) {
            return array();
        }
        
        // Search within 30 days before and after
        $date_min = date('Y-m-d', strtotime('-30 days', $depart_date));
        $date_max = date('Y-m-d', strtotime('+30 days', $depart_date));
        
        // Get all events for this member within date range
        $query = new WP_Query(array(
            'post_type'      => 'srt_event',
            'posts_per_page' => -1,
            'post__not_in'   => array($exclude_event_id),
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => 'member_id',
                    'value'   => $member_id,
                    'compare' => '=',
                ),
                array(
                    'key'     => 'start_datetime',
                    'value'   => array($date_min, $date_max),
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ),
            ),
        ));
        
        $matches = array();
        
        foreach ($query->posts as $event) {
            $event_legs = json_decode(get_post_meta($event->ID, 'travel_legs', true), true);
            if (empty($event_legs)) {
                continue;
            }
            
            foreach ($event_legs as $match_index => $match_leg) {
                // Skip if already linked
                if (!empty($match_leg['flight_group_id'])) {
                    continue;
                }
                
                // Check if it's a reversed route
                if ($match_leg['mode'] === 'flight' &&
                    $match_leg['depart_airport'] === $leg['arrive_airport'] &&
                    $match_leg['arrive_airport'] === $leg['depart_airport']) {
                    
                    $matches[] = array(
                        'event_id'      => $event->ID,
                        'event_title'   => $event->post_title,
                        'leg_index'     => $match_index,
                        'leg'           => $match_leg,
                    );
                }
            }
        }
        
        return $matches;
    }
    
    /**
     * Link multiple flights together into a group
     */
    public static function link_flights($request) {
        $event_ids = $request->get_param('event_ids');
        $leg_indices = $request->get_param('leg_indices');
        
        if (count($event_ids) !== count($leg_indices)) {
            return new WP_Error('invalid_params', 'Event IDs and leg indices must match', array('status' => 400));
        }
        
        // Generate unique group ID
        $group_id = 'fg_' . wp_generate_password(12, false);
        
        $linked_legs = array();
        
        // Update each leg with the group ID
        foreach ($event_ids as $i => $event_id) {
            $event = get_post($event_id);
            if (!$event || $event->post_type !== 'srt_event') {
                continue;
            }
            
            $travel_legs = json_decode(get_post_meta($event_id, 'travel_legs', true), true);
            if (empty($travel_legs)) {
                continue;
            }
            
            $leg_index = $leg_indices[$i];
            if (!isset($travel_legs[$leg_index])) {
                continue;
            }
            
            // Set the group ID
            $travel_legs[$leg_index]['flight_group_id'] = $group_id;
            
            // Save back
            update_post_meta($event_id, 'travel_legs', wp_json_encode($travel_legs));
            
            $linked_legs[] = array(
                'event_id'  => $event_id,
                'leg_index' => $leg_index,
                'leg'       => $travel_legs[$leg_index],
            );
        }
        
        return array(
            'success'     => true,
            'group_id'    => $group_id,
            'linked_legs' => $linked_legs,
        );
    }
    
    /**
     * Unlink a flight from its group
     */
    public static function unlink_flight($request) {
        $event_id = $request->get_param('event_id');
        $leg_index = $request->get_param('leg_index');
        
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'srt_event') {
            return new WP_Error('invalid_event', 'Invalid event ID', array('status' => 404));
        }
        
        $travel_legs = json_decode(get_post_meta($event_id, 'travel_legs', true), true);
        if (empty($travel_legs) || !isset($travel_legs[$leg_index])) {
            return new WP_Error('invalid_leg', 'Invalid leg index', array('status' => 404));
        }
        
        // Remove the group ID
        $travel_legs[$leg_index]['flight_group_id'] = '';
        
        // Save back
        update_post_meta($event_id, 'travel_legs', wp_json_encode($travel_legs));
        
        return array('success' => true);
    }
    
    /**
     * Get pricing comparison for a flight group
     */
    public static function get_flight_group_pricing($request) {
        $group_id = $request->get_param('group_id');
        
        // Find all legs in this group
        $legs = self::get_flight_group_legs($group_id);
        
        if (count($legs) < 2) {
            return new WP_Error('insufficient_legs', 'Group must have at least 2 legs', array('status' => 400));
        }
        
        // Get pricing for each leg individually
        $individual_prices = array();
        $total_individual = 0;
        
        foreach ($legs as $leg_data) {
            $leg = $leg_data['leg'];
            $price_data = self::get_leg_pricing($leg);
            
            if ($price_data) {
                $individual_prices[] = $price_data;
                if (!empty($price_data['best_price'])) {
                    $total_individual += $price_data['best_price'];
                }
            }
        }
        
        // Get round-trip pricing (if exactly 2 legs and they're reversed)
        $round_trip_price = null;
        if (count($legs) === 2) {
            $leg1 = $legs[0]['leg'];
            $leg2 = $legs[1]['leg'];
            
            // Check if reversed
            if ($leg1['depart_airport'] === $leg2['arrive_airport'] &&
                $leg1['arrive_airport'] === $leg2['depart_airport']) {
                
                $round_trip_data = self::get_round_trip_pricing($leg1, $leg2);
                if ($round_trip_data) {
                    $round_trip_price = $round_trip_data['best_price'];
                }
            }
        }
        
        // Calculate savings
        $savings = 0;
        $best_option = 'individual';
        
        if ($round_trip_price && $total_individual > 0) {
            if ($round_trip_price < $total_individual) {
                $savings = $total_individual - $round_trip_price;
                $best_option = 'roundtrip';
            } else {
                $savings = $round_trip_price - $total_individual;
                $best_option = 'individual';
            }
        }
        
        return array(
            'group_id'           => $group_id,
            'legs'               => $legs,
            'individual_prices'  => $individual_prices,
            'total_individual'   => $total_individual,
            'round_trip_price'   => $round_trip_price,
            'best_option'        => $best_option,
            'savings'            => $savings,
        );
    }
    
    /**
     * Get all legs in a flight group
     */
    public static function get_flight_group_legs($group_id) {
        $query = new WP_Query(array(
            'post_type'      => 'srt_event',
            'posts_per_page' => -1,
            'post_status'    => array('publish', 'future', 'draft'),
        ));
        
        $legs = array();
        
        foreach ($query->posts as $event) {
            $travel_legs = json_decode(get_post_meta($event->ID, 'travel_legs', true), true);
            if (empty($travel_legs)) {
                continue;
            }
            
            foreach ($travel_legs as $leg_index => $leg) {
                if (!empty($leg['flight_group_id']) && $leg['flight_group_id'] === $group_id) {
                    $legs[] = array(
                        'event_id'      => $event->ID,
                        'event_title'   => $event->post_title,
                        'leg_index'     => $leg_index,
                        'leg'           => $leg,
                    );
                }
            }
        }
        
        // Sort by departure date
        usort($legs, function($a, $b) {
            return strtotime($a['leg']['depart_date']) - strtotime($b['leg']['depart_date']);
        });
        
        return $legs;
    }
    
    /**
     * Get pricing for a single leg (one-way flight)
     */
    private static function get_leg_pricing($leg) {
        if (empty($leg['depart_airport']) || empty($leg['arrive_airport']) || empty($leg['depart_date'])) {
            return null;
        }
        
        // Get price history from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'srt_price_history';
        
        $prices = $wpdb->get_results($wpdb->prepare(
            "SELECT price, checked_date, trip_type 
             FROM $table_name 
             WHERE origin = %s 
             AND destination = %s 
             AND DATE(travel_date) = %s
             AND price > 0
             ORDER BY checked_date DESC 
             LIMIT 10",
            $leg['depart_airport'],
            $leg['arrive_airport'],
            $leg['depart_date']
        ));
        
        if (empty($prices)) {
            return null;
        }
        
        $best_price = min(array_column($prices, 'price'));
        $latest_price = $prices[0]->price;
        
        return array(
            'origin'       => $leg['depart_airport'],
            'destination'  => $leg['arrive_airport'],
            'travel_date'  => $leg['depart_date'],
            'trip_type'    => 'one-way',
            'best_price'   => $best_price,
            'latest_price' => $latest_price,
            'history'      => $prices,
        );
    }
    
    /**
     * Get round-trip pricing for two legs
     */
    private static function get_round_trip_pricing($outbound_leg, $return_leg) {
        if (empty($outbound_leg['depart_airport']) || 
            empty($outbound_leg['arrive_airport']) || 
            empty($outbound_leg['depart_date']) ||
            empty($return_leg['depart_date'])) {
            return null;
        }
        
        // Get round-trip price history from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'srt_price_history';
        
        $prices = $wpdb->get_results($wpdb->prepare(
            "SELECT price, checked_date 
             FROM $table_name 
             WHERE origin = %s 
             AND destination = %s 
             AND DATE(travel_date) = %s
             AND trip_type = 'round-trip'
             AND price > 0
             ORDER BY checked_date DESC 
             LIMIT 10",
            $outbound_leg['depart_airport'],
            $outbound_leg['arrive_airport'],
            $outbound_leg['depart_date']
        ));
        
        if (empty($prices)) {
            return null;
        }
        
        $best_price = min(array_column($prices, 'price'));
        $latest_price = $prices[0]->price;
        
        return array(
            'origin'         => $outbound_leg['depart_airport'],
            'destination'    => $outbound_leg['arrive_airport'],
            'outbound_date'  => $outbound_leg['depart_date'],
            'return_date'    => $return_leg['depart_date'],
            'trip_type'      => 'round-trip',
            'best_price'     => $best_price,
            'latest_price'   => $latest_price,
            'history'        => $prices,
        );
    }
    
    /**
     * Get all flight groups for a member
     */
    public static function get_member_flight_groups($member_id) {
        $query = new WP_Query(array(
            'post_type'      => 'srt_event',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'member_id',
                    'value'   => $member_id,
                    'compare' => '=',
                ),
            ),
        ));
        
        $groups = array();
        
        foreach ($query->posts as $event) {
            $travel_legs = json_decode(get_post_meta($event->ID, 'travel_legs', true), true);
            if (empty($travel_legs)) {
                continue;
            }
            
            foreach ($travel_legs as $leg_index => $leg) {
                if (!empty($leg['flight_group_id'])) {
                    $group_id = $leg['flight_group_id'];
                    
                    if (!isset($groups[$group_id])) {
                        $groups[$group_id] = array(
                            'group_id' => $group_id,
                            'legs'     => array(),
                        );
                    }
                    
                    $groups[$group_id]['legs'][] = array(
                        'event_id'      => $event->ID,
                        'event_title'   => $event->post_title,
                        'leg_index'     => $leg_index,
                        'leg'           => $leg,
                    );
                }
            }
        }
        
        return array_values($groups);
    }
}

// Initialize
SRT_Flight_Linking::init();
