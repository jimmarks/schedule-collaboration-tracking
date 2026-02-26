<?php
/**
 * REST API Endpoints
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class SRT_REST {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    
    /**
     * Register REST routes
     */
    public static function register_routes() {
        // Get events list
        register_rest_route('srt/v1', '/events', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_events'),
            'permission_callback' => array(__CLASS__, 'check_read_permission'),
            'args'                => array(
                'start_date' => array(
                    'type'              => 'string',
                    'description'       => 'Filter events starting from this date (ISO8601)',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'end_date' => array(
                    'type'              => 'string',
                    'description'       => 'Filter events up to this date (ISO8601)',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'event_type' => array(
                    'type'              => 'string',
                    'description'       => 'Filter by event type',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'member_id' => array(
                    'type'              => 'integer',
                    'description'       => 'Filter by member ID',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // Get single event
        register_rest_route('srt/v1', '/events/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_event'),
            'permission_callback' => array(__CLASS__, 'check_read_permission'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
        
        // Create event
        register_rest_route('srt/v1', '/events', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'create_event'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
            'args'                => array(
                'title' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // Update event
        register_rest_route('srt/v1', '/events/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array(__CLASS__, 'update_event'),
            'permission_callback' => array(__CLASS__, 'check_edit_permission'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
        
        // Delete event
        register_rest_route('srt/v1', '/events/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array(__CLASS__, 'delete_event'),
            'permission_callback' => array(__CLASS__, 'check_delete_permission'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));
        
        // Get dashboard data
        register_rest_route('srt/v1', '/dashboard', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_dashboard'),
            'permission_callback' => array(__CLASS__, 'check_read_permission'),
        ));
        
        // Get registration page URL
        register_rest_route('srt/v1', '/registration-url', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_registration_url'),
            'permission_callback' => '__return_true', // Public endpoint
        ));
        
        // Get flight groups with pricing for current user
        register_rest_route('srt/v1', '/flight-groups', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_flight_groups'),
            'permission_callback' => array(__CLASS__, 'check_read_permission'),
        ));
        
        // Get specific flight group details
        register_rest_route('srt/v1', '/flight-group/(?P<group_id>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_flight_group'),
            'permission_callback' => array(__CLASS__, 'check_read_permission'),
            'args'                => array(
                'group_id' => array(
                    'required' => true,
                ),
            ),
        ));
        
        // Create price alert
        register_rest_route('srt/v1', '/price-alerts', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'create_price_alert'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'event_id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'validate_callback' => function($param) { return is_numeric($param); },
                ),
                'leg_index' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'validate_callback' => function($param) { return is_numeric($param); },
                ),
                'alert_type' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'enum'              => array('price_drop', 'percent_drop', 'good_deal', 'daily_digest'),
                ),
                'threshold_price' => array(
                    'type'              => 'number',
                    'required'          => false,
                ),
                'threshold_percent' => array(
                    'type'              => 'integer',
                    'required'          => false,
                ),
            ),
        ));
        
        // Manual price check endpoint
        register_rest_route('srt/v1', '/check-price', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'manual_price_check'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'event_id' => array(
                    'required' => true,
                    'type'     => 'integer',
                ),
                'leg_index' => array(
                    'required' => true,
                    'type'     => 'integer',
                ),
            ),
        ));
        
        // Get price history endpoint
        register_rest_route('srt/v1', '/price-history', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_price_history'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'event_id' => array(
                    'required' => true,
                    'type'     => 'integer',
                ),
                'leg_index' => array(
                    'required' => true,
                    'type'     => 'integer',
                ),
            ),
        ));
        
        // Get user's price alerts
        register_rest_route('srt/v1', '/my-alerts', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_user_alerts'),
            'permission_callback' => 'is_user_logged_in',
        ));
        
        // Delete price alert
        register_rest_route('srt/v1', '/price-alerts/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array(__CLASS__, 'delete_price_alert'),
            'permission_callback' => 'is_user_logged_in',
        ));
        
        // Get/Update user preferences
        register_rest_route('srt/v1', '/user-preferences', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_user_preferences'),
            'permission_callback' => 'is_user_logged_in',
        ));
        
        register_rest_route('srt/v1', '/user-preferences', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'update_user_preferences'),
            'permission_callback' => 'is_user_logged_in',
        ));
    }
    
    /**
     * Check read permission
     */
    public static function check_read_permission() {
        $settings = get_option('srt_settings', array());
        $require_login = $settings['require_login'] ?? false;
        
        if ($require_login && !is_user_logged_in()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check edit permission
     */
    public static function check_edit_permission() {
        return current_user_can('edit_posts');
    }
    
    /**
     * Check delete permission
     */
    public static function check_delete_permission() {
        return current_user_can('delete_posts');
    }
    
    /**
     * Get events list
     */
    public static function get_events($request) {
        $current_user_id = get_current_user_id();
        
        $args = array(
            'post_type'      => 'srt_event',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value',
            'meta_key'       => 'start_datetime',
            'order'          => 'ASC',
        );
        
        // Date range filter
        $meta_query = array();
        
        // Member filtering logic
        $member_id = $request->get_param('member_id');
        if ($member_id) {
            // Specific member requested
            $meta_query[] = array(
                'key'     => 'member_id',
                'value'   => $member_id,
                'compare' => '=',
            );
        } else {
            // Auto-filter based on user role
            if (SRT_Roles::is_parent($current_user_id)) {
                // User is a parent - show all children's events
                $children = SRT_Roles::get_children($current_user_id);
                if (!empty($children)) {
                    // get_children() already returns an array of user IDs
                    $meta_query[] = array(
                        'key'     => 'member_id',
                        'value'   => $children,
                        'compare' => 'IN',
                    );
                }
            } elseif (SRT_Roles::is_member($current_user_id)) {
                // User is a member - show only their own events
                $meta_query[] = array(
                    'key'     => 'member_id',
                    'value'   => $current_user_id,
                    'compare' => '=',
                );
            }
            // Admin/others with no filtering - shows all events
        }
        
        if ($request->get_param('start_date')) {
            $meta_query[] = array(
                'key'     => 'start_datetime',
                'value'   => $request->get_param('start_date'),
                'compare' => '>=',
                'type'    => 'DATETIME',
            );
        }
        
        if ($request->get_param('end_date')) {
            $meta_query[] = array(
                'key'     => 'end_datetime',
                'value'   => $request->get_param('end_date'),
                'compare' => '<=',
                'type'    => 'DATETIME',
            );
        }
        
        if ($request->get_param('event_type')) {
            $meta_query[] = array(
                'key'     => 'event_type',
                'value'   => $request->get_param('event_type'),
                'compare' => '=',
            );
        }
        
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        
        $query = new WP_Query($args);
        $events = array();
        
        foreach ($query->posts as $post) {
            $events[] = self::format_event($post);
        }
        
        return rest_ensure_response($events);
    }
    
    /**
     * Get single event
     */
    public static function get_event($request) {
        $post = get_post($request['id']);
        
        if (!$post || $post->post_type !== 'srt_event') {
            return new WP_Error('not_found', __('Event not found', 'schedule-collaboration-tracking'), array('status' => 404));
        }
        
        return rest_ensure_response(self::format_event($post));
    }
    
    /**
     * Create event
     */
    public static function create_event($request) {
        $post_data = array(
            'post_type'   => 'srt_event',
            'post_title'  => $request->get_param('title'),
            'post_status' => 'publish',
        );
        
        if ($request->get_param('content')) {
            $post_data['post_content'] = wp_kses_post($request->get_param('content'));
        }
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Update meta fields
        self::update_event_meta($post_id, $request);
        
        $post = get_post($post_id);
        return rest_ensure_response(self::format_event($post));
    }
    
    /**
     * Update event
     */
    public static function update_event($request) {
        $post = get_post($request['id']);
        
        if (!$post || $post->post_type !== 'srt_event') {
            return new WP_Error('not_found', __('Event not found', 'schedule-collaboration-tracking'), array('status' => 404));
        }
        
        $post_data = array(
            'ID' => $post->ID,
        );
        
        if ($request->get_param('title')) {
            $post_data['post_title'] = $request->get_param('title');
        }
        
        if ($request->get_param('content')) {
            $post_data['post_content'] = wp_kses_post($request->get_param('content'));
        }
        
        wp_update_post($post_data);
        
        // Update meta fields
        self::update_event_meta($post->ID, $request);
        
        $post = get_post($post->ID);
        return rest_ensure_response(self::format_event($post));
    }
    
    /**
     * Delete event
     */
    public static function delete_event($request) {
        global $wpdb;
        
        $post = get_post($request['id']);
        
        if (!$post || $post->post_type !== 'srt_event') {
            return new WP_Error('not_found', __('Event not found', 'schedule-collaboration-tracking'), array('status' => 404));
        }
        
        // Delete all price alerts associated with this event
        $alerts_table = $wpdb->prefix . 'srt_price_alerts';
        $deleted_alerts = $wpdb->delete(
            $alerts_table,
            array('event_id' => $post->ID),
            array('%d')
        );
        
        if ($deleted_alerts) {
            error_log("SRT: Deleted $deleted_alerts price alert(s) for deleted event {$post->ID}");
        }
        
        $result = wp_delete_post($post->ID, true);
        
        if (!$result) {
            return new WP_Error('delete_failed', __('Failed to delete event', 'schedule-collaboration-tracking'), array('status' => 500));
        }
        
        return rest_ensure_response(array('deleted' => true, 'id' => $post->ID));
    }
    
    /**
     * Get registration page URL
     */
    public static function get_registration_url($request) {
        $url = SRT_Pages::get_registration_url();
        
        if (!$url) {
            return new WP_Error(
                'no_registration_page',
                __('Registration page not found. Please create a page with the [srt_register] shortcode.', 'schedule-collaboration-tracking'),
                array('status' => 404)
            );
        }
        
        return rest_ensure_response(array('url' => $url));
    }
    
    /**
     * Get dashboard data
     */
    public static function get_dashboard($request) {
        $current_user_id = get_current_user_id();
        $now = current_time('mysql');
        $two_weeks = date('Y-m-d H:i:s', strtotime('+14 days', current_time('timestamp')));
        
        error_log('=== DASHBOARD DEBUG ===');
        error_log('Current User ID: ' . $current_user_id);
        error_log('Is Member: ' . (SRT_Roles::is_member($current_user_id) ? 'yes' : 'no'));
        error_log('Is Parent: ' . (SRT_Roles::is_parent($current_user_id) ? 'yes' : 'no'));
        
        // Determine which member IDs to query for
        $member_ids = array();
        
        if (SRT_Roles::is_member($current_user_id)) {
            // Members see only their own events
            $member_ids = array($current_user_id);
            error_log('Member mode: showing events for user ' . $current_user_id);
        } elseif (SRT_Roles::is_parent($current_user_id)) {
            // Parents see their children's events
            $children = SRT_Roles::get_children($current_user_id);
            error_log('Parent mode: found ' . count($children) . ' children');
            if (!empty($children)) {
                // get_children() already returns an array of user IDs
                $member_ids = $children;
                error_log('Member IDs to query: ' . implode(', ', $member_ids));
            }
        }
        
        // If no member IDs, return empty data
        if (empty($member_ids)) {
            error_log('No member IDs found - returning empty data');
            return rest_ensure_response(array(
                'flights_needed'  => array(),
                'not_booked'      => array(),
                'upcoming_travel' => array(),
            ));
        }
        
        // Build meta query for member_id filtering
        $member_meta_query = array(
            'relation' => 'OR',
        );
        
        // Include events for each child
        foreach ($member_ids as $member_id) {
            $member_meta_query[] = array(
                'key'     => 'member_id',
                'value'   => $member_id,
                'compare' => '=',
            );
        }
        
        // For parents, also include unassigned events and events they created themselves
        if (SRT_Roles::is_parent($current_user_id)) {
            // Include events with no member_id set
            $member_meta_query[] = array(
                'key'     => 'member_id',
                'compare' => 'NOT EXISTS',
            );
            // Include events with empty member_id
            $member_meta_query[] = array(
                'key'     => 'member_id',
                'value'   => '',
                'compare' => '=',
            );
            // Include events assigned to parent themselves (backward compatibility)
            $member_meta_query[] = array(
                'key'     => 'member_id',
                'value'   => $current_user_id,
                'compare' => '=',
            );
        }
        
        error_log('Meta query built for member_ids: ' . print_r($member_meta_query, true));
        
        // Flights needed (future events with flight_needed = true)
        $flights_needed = new WP_Query(array(
            'post_type'      => 'srt_event',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                'relation' => 'AND',
                $member_meta_query,
                array(
                    'key'     => 'flight_needed',
                    'value'   => '1',
                    'compare' => '=',
                ),
                array(
                    'key'     => 'start_datetime',
                    'value'   => $now,
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ),
            ),
            'orderby'        => 'meta_value',
            'meta_key'       => 'start_datetime',
            'order'          => 'ASC',
        ));
        
        error_log('Flights needed query found ' . $flights_needed->found_posts . ' posts');
        error_log('SQL: ' . $flights_needed->request);
        
        $flights_needed_data = array();
        $not_booked_data = array();
        
        foreach ($flights_needed->posts as $post) {
            $member_id = get_post_meta($post->ID, 'member_id', true);
            error_log('Event ID ' . $post->ID . ' (' . $post->post_title . ') has member_id: ' . ($member_id ?: 'NOT SET'));
            
            // Check if any legs are not booked
            $travel_legs = json_decode(get_post_meta($post->ID, 'travel_legs', true) ?: '[]', true);
            $has_unbooked = false;
            
            foreach ($travel_legs as $leg) {
                if ($leg['mode'] === 'fly' && !$leg['booked']) {
                    $has_unbooked = true;
                    break;
                }
            }
            
            // Only add to flights_needed if there's at least one unbooked leg
            if ($has_unbooked) {
                $event_data = self::format_event($post);
                $flights_needed_data[] = $event_data;
                $not_booked_data[] = $event_data;
            }
        }
        
        // Upcoming travel (next 30 days with travel_needed = true)
        $upcoming_travel = new WP_Query(array(
            'post_type'      => 'srt_event',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                'relation' => 'AND',
                $member_meta_query,
                array(
                    'key'     => 'travel_needed',
                    'value'   => '1',
                    'compare' => '=',
                ),
                array(
                    'key'     => 'start_datetime',
                    'value'   => $now,
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ),
                array(
                    'key'     => 'start_datetime',
                    'value'   => $thirty_days,
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ),
            ),
            'orderby'        => 'meta_value',
            'meta_key'       => 'start_datetime',
            'order'          => 'ASC',
        ));
        
        $upcoming_travel_data = array();
        foreach ($upcoming_travel->posts as $post) {
            $upcoming_travel_data[] = self::format_event($post);
        }
        
        error_log('=== DASHBOARD RESULTS ===');
        error_log('Flights needed: ' . count($flights_needed_data));
        error_log('Not booked: ' . count($not_booked_data));
        error_log('Upcoming travel: ' . count($upcoming_travel_data));
        error_log('=== END DASHBOARD DEBUG ===');
        
        return rest_ensure_response(array(
            'flights_needed'  => $flights_needed_data,
            'not_booked'      => $not_booked_data,
            'upcoming_travel' => $upcoming_travel_data,
            'debug' => array(
                'user_id' => $current_user_id,
                'is_member' => SRT_Roles::is_member($current_user_id),
                'is_parent' => SRT_Roles::is_parent($current_user_id),
                'member_ids' => $member_ids,
                'children_count' => SRT_Roles::is_parent($current_user_id) ? count(SRT_Roles::get_children($current_user_id)) : 0,
                'query_found_posts' => $flights_needed->found_posts,
            ),
        ));
    }
    
    /**
     * Get flight groups with pricing for current user
     */
    public static function get_flight_groups($request) {
        $current_user = wp_get_current_user();
        if (!$current_user || !$current_user->ID) {
            return new WP_Error('unauthorized', 'Must be logged in', array('status' => 401));
        }
        
        // Determine which member IDs to check
        $member_ids = array($current_user->ID);
        
        // If parent, include children
        if (SRT_Roles::is_parent($current_user->ID)) {
            $children = SRT_Roles::get_children($current_user->ID);
            if (!empty($children)) {
                $member_ids = array_merge($member_ids, $children);
            }
        }
        
        // Get all flight groups for these members
        $all_groups = array();
        foreach ($member_ids as $member_id) {
            $member_groups = SRT_Flight_Linking::get_member_flight_groups($member_id);
            $all_groups = array_merge($all_groups, $member_groups);
        }
        
        // Deduplicate by group_id
        $unique_groups = array();
        foreach ($all_groups as $group) {
            $unique_groups[$group['group_id']] = $group;
        }
        
        // Get pricing for each group
        $groups_with_pricing = array();
        foreach ($unique_groups as $group) {
            $pricing_response = SRT_Flight_Linking::get_flight_group_pricing(
                new WP_REST_Request('GET', '/srt/v1/flight-group-pricing/' . $group['group_id'])
            );
            
            if (!is_wp_error($pricing_response)) {
                $group['pricing'] = $pricing_response;
                $groups_with_pricing[] = $group;
            }
        }
        
        return rest_ensure_response($groups_with_pricing);
    }
    
    /**
     * Get specific flight group details
     */
    public static function get_flight_group($request) {
        $group_id = $request->get_param('group_id');
        
        // Get all legs in this group
        $legs = SRT_Flight_Linking::get_flight_group_legs($group_id);
        
        if (empty($legs)) {
            return new WP_Error('not_found', 'Flight group not found', array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'group_id' => $group_id,
            'legs'     => $legs,
        ));
    }
    
    /**
     * Update event meta fields
     */
    private static function update_event_meta($post_id, $request) {
        global $wpdb;
        
        $meta_fields = array(
            'member_id',
            'start_datetime',
            'end_datetime',
            'timezone',
            'all_day',
            'event_type',
            'location_name',
            'location_address',
            'location_latitude',
            'location_longitude',
            'notes',
            'time_blocks',
            'travel_needed',
            'travel_mode',
            'flight_needed',
            'travel_legs',
        );
        
        // Get old travel_legs before updating to check for newly booked flights
        $old_travel_legs = array();
        if ($request->has_param('travel_legs')) {
            $old_travel_legs_json = get_post_meta($post_id, 'travel_legs', true);
            $old_travel_legs = json_decode($old_travel_legs_json ?: '[]', true);
        }
        
        foreach ($meta_fields as $field) {
            if ($request->has_param($field)) {
                update_post_meta($post_id, $field, $request->get_param($field));
            }
        }
        
        // If member_id not provided, default to current user (for backward compatibility)
        if (!$request->has_param('member_id') && !get_post_meta($post_id, 'member_id', true)) {
            update_post_meta($post_id, 'member_id', get_current_user_id());
        }
        
        // Check for newly booked flights and delete associated price alerts
        if ($request->has_param('travel_legs')) {
            $new_travel_legs = json_decode($request->get_param('travel_legs'), true);
            
            if (is_array($new_travel_legs) && is_array($old_travel_legs)) {
                foreach ($new_travel_legs as $index => $new_leg) {
                    // Check if this is a flight leg
                    if (isset($new_leg['mode']) && $new_leg['mode'] === 'fly' && isset($new_leg['booked']) && $new_leg['booked']) {
                        // Check if it wasn't booked before (or didn't exist before)
                        $was_booked = isset($old_travel_legs[$index]) && 
                                     isset($old_travel_legs[$index]['booked']) && 
                                     $old_travel_legs[$index]['booked'];
                        
                        if (!$was_booked) {
                            // This leg was just marked as booked - delete all price alerts for it
                            $alerts_table = $wpdb->prefix . 'srt_price_alerts';
                            $deleted = $wpdb->delete(
                                $alerts_table,
                                array(
                                    'event_id' => $post_id,
                                    'leg_index' => $index,
                                ),
                                array('%d', '%d')
                            );
                            
                            if ($deleted) {
                                error_log("SRT: Deleted $deleted price alert(s) for event $post_id, leg $index (marked as booked)");
                            }
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Create price alert
     */
    public static function create_price_alert($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'You must be logged in to create price alerts', array('status' => 401));
        }
        
        $event_id = $request->get_param('event_id');
        $leg_index = $request->get_param('leg_index');
        $alert_type = $request->get_param('alert_type');
        $threshold_price = $request->get_param('threshold_price');
        $threshold_percent = $request->get_param('threshold_percent');
        
        // Validate event exists
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'srt_event') {
            return new WP_Error('invalid_event', 'Invalid event ID', array('status' => 404));
        }
        
        // Insert alert
        $alerts_table = $wpdb->prefix . 'srt_price_alerts';
        $result = $wpdb->insert($alerts_table, array(
            'user_id' => $user_id,
            'event_id' => $event_id,
            'leg_index' => $leg_index,
            'alert_type' => $alert_type,
            'threshold_price' => $threshold_price,
            'threshold_percent' => $threshold_percent,
            'is_active' => 1,
            'created_at' => current_time('mysql'),
        ), array('%d', '%d', '%d', '%s', '%f', '%d', '%d', '%s'));
        
        if ($result === false) {
            return new WP_Error('insert_failed', 'Failed to create price alert', array('status' => 500));
        }
        
        $alert_id = $wpdb->insert_id;
        
        // Send confirmation email
        $confirmation = SRT_Price_Tracking::send_alert_confirmation($alert_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'alert_id' => $alert_id,
            'message' => 'Price alert created successfully. Check your email for confirmation.',
            'email_sent' => $confirmation['success'] ?? false,
            'email_subject' => $confirmation['subject'] ?? '',
        ));
    }
    
    /**
     * Manual price check
     */
    public static function manual_price_check($request) {
        $event_id = $request->get_param('event_id');
        $leg_index = $request->get_param('leg_index');
        
        // Validate event exists
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'srt_event') {
            return new WP_Error('invalid_event', 'Invalid event ID', array('status' => 404));
        }
        
        // Get travel legs
        $legs = json_decode(get_post_meta($event_id, 'travel_legs', true) ?: '[]', true);
        if (!isset($legs[$leg_index])) {
            return new WP_Error('invalid_leg', 'Invalid leg index', array('status' => 404));
        }
        
        $leg = $legs[$leg_index];
        
        // Check if flight data is present
        if (empty($leg['depart_airport']) || empty($leg['arrive_airport']) || empty($leg['depart_date'])) {
            return new WP_Error('incomplete_data', 'Missing flight information', array('status' => 400));
        }
        
        // Check if this is part of a round-trip pattern
        $is_round_trip = false;
        $return_leg_index = null;
        $return_date = null;
        
        // Method 1: Check if this single leg has arrive_date spanning multiple days (likely return date)
        if (!empty($leg['arrive_date']) && !empty($leg['depart_date'])) {
            $depart_time = strtotime($leg['depart_date']);
            $arrive_time = strtotime($leg['arrive_date']);
            $days_diff = ($arrive_time - $depart_time) / (60 * 60 * 24);
            
            // If arrive_date is 1+ days after depart_date, treat it as return date for round-trip
            if ($days_diff >= 1) {
                $is_round_trip = true;
                $return_date = $leg['arrive_date'];
            }
        }
        
        // Method 2: Look for a return leg in subsequent legs (same airports reversed)
        if (!$is_round_trip) {
            for ($i = $leg_index + 1; $i < count($legs); $i++) {
                if ($legs[$i]['mode'] === 'fly' && 
                    $legs[$i]['depart_airport'] === $leg['arrive_airport'] && 
                    $legs[$i]['arrive_airport'] === $leg['depart_airport'] &&
                    !empty($legs[$i]['depart_date'])) {
                    $is_round_trip = true;
                    $return_leg_index = $i;
                    $return_date = $legs[$i]['depart_date'];
                    break;
                }
            }
        }
        
        // Fetch current price (round-trip or one-way)
        if ($is_round_trip) {
            $price_result = SRT_Price_Tracking::fetch_flight_price_serpapi(
                $leg['depart_airport'],
                $leg['arrive_airport'],
                $leg['depart_date'],
                $return_date  // Pass return date for round-trip
            );
        } else {
            $price_result = SRT_Price_Tracking::fetch_flight_price_serpapi(
                $leg['depart_airport'],
                $leg['arrive_airport'],
                $leg['depart_date']
            );
        }
        
        // Handle both old return format (just price) and new format (array with price and debug)
        $price = is_array($price_result) ? $price_result['price'] : $price_result;
        $debug_info = is_array($price_result) ? $price_result['debug'] : null;
        
        if ($price === null) {
            $response = array(
                'success' => false,
                'price' => null,
                'message' => 'SerpAPI key not configured. Please add your API key in Settings.',
            );
            if ($debug_info) {
                $response['debug'] = $debug_info;
            }
            return rest_ensure_response($response);
        }
        
        if ($price === false) {
            // No flights found - return success with null price and helpful message
            $response = array(
                'success' => false,
                'price' => null,
                'message' => 'No flights found for this route/date. This may be because: 1) The route is not served, 2) The date is too far in the future, or 3) No direct flights available.',
                'suggestions' => array(
                    'Check that airport codes are correct',
                    'Try a different date',
                    'Check if direct flights exist for this route'
                ),
            );
            if ($debug_info) {
                $response['debug'] = $debug_info;
            }
            return rest_ensure_response($response);
        }
        
        // Record the price
        $trip_type = $is_round_trip ? 'manual_roundtrip' : 'manual';
        error_log("SRT: Recording price check - Event: $event_id, Leg: $leg_index, Price: $price, Type: $trip_type");
        $recorded_id = SRT_Price_Tracking::record_price(
            $event_id,
            $leg_index,
            $leg['depart_airport'],
            $leg['arrive_airport'],
            $leg['depart_date'],
            $price,
            $trip_type,
            $price_result
        );
        error_log("SRT: Price recorded with ID: $recorded_id");
        
        // Extract Google price insights if available
        $google_insights = is_array($price_result) && isset($price_result['price_insights']) ? $price_result['price_insights'] : null;
        
        // Build Google Flights verification link
        $google_flights_url = 'https://www.google.com/travel/flights/search?tfs=CBwQAhokag0IAhIJL20vMDFfajBjEgoyMDI2LTA0LTIzcg0IAhIJL20vMDFfZDRnGgA';
        if ($is_round_trip && $return_date) {
            // Round-trip format
            $google_flights_url = sprintf(
                'https://www.google.com/travel/flights?q=Flights%%20from%%20%s%%20to%%20%s%%20on%%20%s%%20returning%%20%s',
                $leg['depart_airport'],
                $leg['arrive_airport'],
                $leg['depart_date'],
                $return_date
            );
        } else {
            // One-way format  
            $google_flights_url = sprintf(
                'https://www.google.com/travel/flights?q=Flights%%20from%%20%s%%20to%%20%s%%20on%%20%s',
                $leg['depart_airport'],
                $leg['arrive_airport'],
                $leg['depart_date']
            );
        }
        
        $response_data = array(
            'success' => true,
            'price' => $price,
            'currency' => 'USD',
            'checked_at' => current_time('mysql'),
            'trip_type' => $is_round_trip ? 'round-trip' : 'one-way',
            'return_date' => $return_date,
            'google_flights_url' => $google_flights_url,
            'debug' => $debug_info,
        );
        
        if ($google_insights) {
            $response_data['google_insights'] = $google_insights;
        }
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Get price history
     */
    public static function get_price_history($request) {
        global $wpdb;
        
        $event_id = $request->get_param('event_id');
        $leg_index = $request->get_param('leg_index');
        
        $table_name = $wpdb->prefix . 'srt_price_history';
        
        error_log("SRT get_price_history: event=$event_id, leg=$leg_index");
        
        // First, check if the table has any data for this event at all
        $total_for_event = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE event_id = %d",
            $event_id
        ));
        error_log("SRT get_price_history: Total records for event $event_id: $total_for_event");
        
        // Check specific leg
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT price, checked_at, google_insights 
            FROM $table_name 
            WHERE event_id = %d 
            AND leg_index = %d 
            AND price > 0
            ORDER BY checked_at ASC",
            $event_id,
            $leg_index
        ));
        
        error_log("SRT get_price_history: Found " . count($history) . " records for event=$event_id, leg=$leg_index");
        error_log("SRT get_price_history: Last query: " . $wpdb->last_query);
        
        if ($wpdb->last_error) {
            error_log("SRT get_price_history ERROR: " . $wpdb->last_error);
        }
        
        // If no results, let's see what legs exist for this event
        if (empty($history) && $total_for_event > 0) {
            $legs_in_db = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT leg_index, origin, destination FROM $table_name WHERE event_id = %d",
                $event_id
            ));
            error_log("SRT get_price_history: Legs in database for event $event_id: " . print_r($legs_in_db, true));
        }
        
        if (empty($history)) {
            return rest_ensure_response(array(
                'prices' => array(),
                'stats' => null,
            ));
        }
        
        // Calculate stats (only from valid prices > 0)
        $prices = array_column($history, 'price');
        $prices = array_filter($prices, function($p) { return $p > 0; });
        
        if (empty($prices)) {
            return rest_ensure_response(array(
                'prices' => array(),
                'stats' => null,
            ));
        }
        
        $stats = array(
            'min' => min($prices),
            'max' => max($prices),
            'avg' => round(array_sum($prices) / count($prices), 2),
            'current' => end($prices),
            'first' => reset($prices),
            'count' => count($prices),
        );
        
        // Calculate trend
        $stats['trend'] = $stats['current'] < $stats['first'] ? 'down' : 
                         ($stats['current'] > $stats['first'] ? 'up' : 'stable');
        $stats['change'] = round($stats['current'] - $stats['first'], 2);
        $stats['change_percent'] = $stats['first'] > 0 ? 
            round((($stats['current'] - $stats['first']) / $stats['first']) * 100, 1) : 0;
        
        // Get Google insights from most recent check
        $google_insights = null;
        $latest_record = end($history);
        if ($latest_record && !empty($latest_record->google_insights)) {
            $google_insights = json_decode($latest_record->google_insights, true);
        }
        
        $response = array(
            'prices' => $history,
            'stats' => $stats,
        );
        
        if ($google_insights) {
            $response['google_insights'] = $google_insights;
        }
        
        return rest_ensure_response($response);
    }
    
    /**
     * Get user's price alerts
     */
    public static function get_user_alerts($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $alerts_table = $wpdb->prefix . 'srt_price_alerts';
        
        $alerts = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, p.post_title as event_title 
            FROM $alerts_table a
            LEFT JOIN {$wpdb->posts} p ON a.event_id = p.ID
            WHERE a.user_id = %d
            ORDER BY a.created_at DESC",
            $user_id
        ));
        
        // Enrich alerts with leg info
        foreach ($alerts as $alert) {
            $legs = json_decode(get_post_meta($alert->event_id, 'travel_legs', true) ?: '[]', true);
            if (isset($legs[$alert->leg_index])) {
                $leg = $legs[$alert->leg_index];
                $alert->route = ($leg['depart_airport'] ?? '') . ' → ' . ($leg['arrive_airport'] ?? '');
                $alert->depart_date = $leg['depart_date'] ?? '';
            } else {
                $alert->route = 'Unknown';
                $alert->depart_date = '';
            }
        }
        
        return rest_ensure_response($alerts);
    }
    
    /**
     * Delete price alert
     */
    public static function delete_price_alert($request) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $alert_id = $request['id'];
        $alerts_table = $wpdb->prefix . 'srt_price_alerts';
        
        // Verify ownership
        $alert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $alerts_table WHERE id = %d",
            $alert_id
        ));
        
        if (!$alert) {
            return new WP_Error('not_found', 'Alert not found', array('status' => 404));
        }
        
        if ($alert->user_id != $user_id) {
            return new WP_Error('forbidden', 'Not authorized to delete this alert', array('status' => 403));
        }
        
        $result = $wpdb->delete($alerts_table, array('id' => $alert_id), array('%d'));
        
        if ($result === false) {
            return new WP_Error('delete_failed', 'Failed to delete alert', array('status' => 500));
        }
        
        return rest_ensure_response(array('success' => true, 'message' => 'Alert deleted'));
    }
    
    /**
     * Get user preferences
     */
    public static function get_user_preferences($request) {
        $user_id = get_current_user_id();
        
        $preferences = array(
            'home_airport' => get_user_meta($user_id, 'srt_home_airport', true) ?: '',
            'timezone' => get_user_meta($user_id, 'srt_timezone', true) ?: wp_timezone_string(),
        );
        
        return rest_ensure_response($preferences);
    }
    
    /**
     * Update user preferences
     */
    public static function update_user_preferences($request) {
        $user_id = get_current_user_id();
        
        $home_airport = $request->get_param('home_airport');
        $timezone = $request->get_param('timezone');
        
        if ($home_airport !== null) {
            update_user_meta($user_id, 'srt_home_airport', strtoupper(sanitize_text_field($home_airport)));
        }
        
        if ($timezone !== null) {
            update_user_meta($user_id, 'srt_timezone', sanitize_text_field($timezone));
        }
        
        return rest_ensure_response(array('success' => true, 'message' => 'Preferences updated'));
    }
    
    /**
     * Format event for API response
     */
    private static function format_event($post) {
        $meta_fields = array(
            'start_datetime',
            'end_datetime',
            'timezone',
            'all_day',
            'event_type',
            'location_name',
            'location_address',
            'location_latitude',
            'location_longitude',
            'notes',
            'travel_needed',
            'travel_mode',
            'flight_needed',
        );
        
        $event = array(
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'content' => $post->post_content,
        );
        
        foreach ($meta_fields as $field) {
            $value = get_post_meta($post->ID, $field, true);
            
            // Convert boolean strings to actual booleans
            if (in_array($field, array('all_day', 'travel_needed', 'flight_needed'))) {
                $value = (bool) $value;
            }
            
            $event[$field] = $value ?: null;
        }
        
        // Decode JSON fields
        $event['time_blocks'] = json_decode(get_post_meta($post->ID, 'time_blocks', true) ?: '[]', true);
        $event['travel_legs'] = json_decode(get_post_meta($post->ID, 'travel_legs', true) ?: '[]', true);
        
        // Add member information
        $member_id = get_post_meta($post->ID, 'member_id', true);
        if ($member_id) {
            $member = get_userdata($member_id);
            if ($member) {
                $event['member_id'] = $member_id;
                $event['member_name'] = $member->display_name;
                
                // Add color information for calendar display
                if (class_exists('FTT_Child_Colors')) {
                    $color = FTT_Child_Colors::get_child_color($member_id);
                    if ($color) {
                        $event['color'] = $color['hex'];
                        $event['textColor'] = $color['text'];
                        $event['className'] = 'child-' . $member_id;
                    }
                }
            } else {
                $event['member_id'] = $member_id;
                $event['member_name'] = null;
            }
        } else {
            $event['member_id'] = null;
            $event['member_name'] = null;
        }
        
        return $event;
    }
}

// Initialize
SRT_REST::init();
