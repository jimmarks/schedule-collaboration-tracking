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

class FTT_REST {
    
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
        register_rest_route('ftt/v1', '/events', array(
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
        register_rest_route('ftt/v1', '/events/(?P<id>\d+)', array(
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
        register_rest_route('ftt/v1', '/events', array(
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
        register_rest_route('ftt/v1', '/events/(?P<id>\d+)', array(
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
        register_rest_route('ftt/v1', '/events/(?P<id>\d+)', array(
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
        register_rest_route('ftt/v1', '/dashboard', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_dashboard'),
            'permission_callback' => array(__CLASS__, 'check_read_permission'),
            'args'                => array(
                'group_id' => array(
                    'type'              => 'integer',
                    'description'       => 'Filter dashboard by group ID (v2.1)',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // Get registration page URL
        register_rest_route('ftt/v1', '/registration-url', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_registration_url'),
            'permission_callback' => '__return_true', // Public endpoint
        ));
        
        // Get flight groups with pricing for current user
        register_rest_route('ftt/v1', '/flight-groups', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_flight_groups'),
            'permission_callback' => array(__CLASS__, 'check_read_permission'),
        ));
        
        // Get specific flight group details
        register_rest_route('ftt/v1', '/flight-group/(?P<group_id>[a-zA-Z0-9_-]+)', array(
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
        register_rest_route('ftt/v1', '/price-alerts', array(
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
        register_rest_route('ftt/v1', '/check-price', array(
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
        register_rest_route('ftt/v1', '/price-history', array(
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
        register_rest_route('ftt/v1', '/my-alerts', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_user_alerts'),
            'permission_callback' => 'is_user_logged_in',
        ));
        
        // Delete price alert
        register_rest_route('ftt/v1', '/price-alerts/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array(__CLASS__, 'delete_price_alert'),
            'permission_callback' => 'is_user_logged_in',
        ));
        
        // Get/Update user preferences
        register_rest_route('ftt/v1', '/user-preferences', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_user_preferences'),
            'permission_callback' => 'is_user_logged_in',
        ));
        
        register_rest_route('ftt/v1', '/user-preferences', array(
            'methods'             => 'POST, PUT',
            'callback'            => array(__CLASS__, 'update_user_preferences'),
            'permission_callback' => 'is_user_logged_in',
        ));
        
        // Update user's primary group
        register_rest_route('ftt/v1', '/user/primary-group', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'update_primary_group'),
            'permission_callback' => 'is_user_logged_in',
            'args'                => array(
                'group_id' => array(
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
        
        // User lookup by email (for adding members to groups)
        register_rest_route('ftt/v1', '/users/lookup', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'lookup_user_by_email'),
            'permission_callback' => 'is_user_logged_in',
            'args'                => array(
                'email' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ),
            ),
        ));
        
        // Public registration endpoint (for sign-up page on www domain)
        register_rest_route('ftt/v1', '/register', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'register_new_user'),
            'permission_callback' => '__return_true', // Public endpoint
        ));
        
        // Billing endpoints
        if (class_exists('FTT_Stripe_Integration')) {
            register_rest_route('ftt/v1', '/create-checkout', array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'create_checkout_session'),
                'permission_callback' => 'is_user_logged_in',
            ));

            register_rest_route('ftt/v1', '/add-child-addon', array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'add_child_addon'),
                'permission_callback' => 'is_user_logged_in',
            ));

            register_rest_route('ftt/v1', '/cancel-subscription', array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'cancel_subscription'),
                'permission_callback' => 'is_user_logged_in',
            ));

            register_rest_route('ftt/v1', '/reactivate-subscription', array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'reactivate_subscription'),
                'permission_callback' => 'is_user_logged_in',
            ));
        }

        // Family management endpoints (independent of Stripe)
        register_rest_route('ftt/v1', '/add-child', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'add_child'),
            'permission_callback' => 'is_user_logged_in',
        ));

        register_rest_route('ftt/v1', '/edit-child', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'edit_child'),
            'permission_callback' => 'is_user_logged_in',
        ));

        register_rest_route('ftt/v1', '/remove-child', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'remove_child'),
            'permission_callback' => 'is_user_logged_in',
        ));
        
        // Sync orphaned children to groups (admin utility)
        register_rest_route('ftt/v1', '/sync-children-to-groups', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'sync_children_to_groups'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));

        register_rest_route('ftt/v1', '/invite-adult', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'invite_adult'),
            'permission_callback' => 'is_user_logged_in',
        ));

        register_rest_route('ftt/v1', '/remove-adult', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'remove_adult'),
            'permission_callback' => 'is_user_logged_in',
        ));

        register_rest_route('ftt/v1', '/cancel-invitation', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'cancel_invitation'),
            'permission_callback' => 'is_user_logged_in',
        ));

        register_rest_route('ftt/v1', '/resend-invitation', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'resend_invitation'),
            'permission_callback' => 'is_user_logged_in',
        ));

        register_rest_route('ftt/v1', '/save-event-preferences', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'save_event_preferences'),
            'permission_callback' => 'is_user_logged_in',
        ));

        register_rest_route('ftt/v1', '/get-family-members', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'get_family_members'),
            'permission_callback' => 'is_user_logged_in',
        ));

        // RESTful child CRUD routes (used by templates/family-management.php)
        register_rest_route('ftt/v1', '/children', array(
            array(
                'methods'             => 'GET',
                'callback'            => array(__CLASS__, 'get_children_list'),
                'permission_callback' => 'is_user_logged_in',
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'add_child'),
                'permission_callback' => 'is_user_logged_in',
            ),
        ));

        register_rest_route('ftt/v1', '/children/(?P<id>\d+)', array(
            array(
                'methods'             => 'PUT',
                'callback'            => array(__CLASS__, 'edit_child'),
                'permission_callback' => 'is_user_logged_in',
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array(__CLASS__, 'remove_child'),
                'permission_callback' => 'is_user_logged_in',
            ),
        ));

        // Track client-side API calls (e.g. Google Places selected by user)
        register_rest_route('ftt/v1', '/track-api-call', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'track_api_call'),
            'permission_callback' => 'is_user_logged_in',
            'args'                => array(
                'api' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'enum'              => array('google_places'),
                    'sanitize_callback' => 'sanitize_key',
                ),
                'success' => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
            ),
        ));
    }
    
    /**
     * Record a client-side API call (currently: google_places only).
     */
    public static function track_api_call( WP_REST_Request $request ) {
        if ( ! class_exists('FTT_API_Tracker') ) {
            return new WP_REST_Response( array( 'ok' => false ), 200 );
        }
        $api     = sanitize_key( $request->get_param('api') );
        $success = (bool) $request->get_param('success');
        // Whitelist enforced by route 'enum' arg; double-check here for safety.
        if ( ! in_array( $api, array('google_places'), true ) ) {
            return new WP_REST_Response( array( 'ok' => false, 'error' => 'unknown api' ), 400 );
        }
        FTT_API_Tracker::record( $api, $success );
        return new WP_REST_Response( array( 'ok' => true ), 200 );
    }

    /**
     * Check read permission
     */
    public static function check_read_permission() {
        $settings = get_option('ftt_settings', array());
        $require_login = $settings['require_login'] ?? false;
        
        if ($require_login && !is_user_logged_in()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check edit permission.
     * Accepts any active FTT user (parent, member/child, or admin).
     * We do NOT use current_user_can('edit_posts') because parent accounts are
     * not granted that WordPress capability — only make_member() accounts are.
     */
    public static function check_edit_permission() {
        $uid = get_current_user_id();
        if (!$uid) return false;
        return current_user_can('manage_options')
            || FTT_Family_Groups::is_parent($uid)
            || FTT_Roles::is_member($uid);
    }

    /**
     * Check delete permission.
     * Same FTT-aware check — any active FTT user may delete their own events.
     * Ownership is validated inside the delete handler.
     */
    public static function check_delete_permission() {
        $uid = get_current_user_id();
        if (!$uid) return false;
        return current_user_can('manage_options')
            || FTT_Family_Groups::is_parent($uid)
            || FTT_Roles::is_member($uid);
    }

    /**
     * Alias used by flight-linking.php routes.
     * Identical to check_edit_permission — any logged-in FTT user.
     */
    public static function check_user_permission() {
        return self::check_edit_permission();
    }
    
    /**
     * Get events list
     */
    public static function get_events($request) {
        $current_user_id = get_current_user_id();
        
        $args = array(
            'post_type'      => 'ftt_event',
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
        $group_id = $request->get_param('group_id');
        
        if ($group_id) {
            // Filter by specific group (v2.1)
            global $wpdb;
            $event_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->prefix}ftt_event_groups WHERE group_id = %d",
                $group_id
            ));
            
            if (!empty($event_ids)) {
                $args['post__in'] = $event_ids;
            } else {
                // No events in this group - return empty
                return rest_ensure_response(array());
            }
        } elseif ($member_id) {
            // Specific member requested
            $meta_query[] = array(
                'key'     => 'member_id',
                'value'   => $member_id,
                'compare' => '=',
            );
        } else {
            // Auto-filter based on user role
            $is_parent = FTT_Family_Groups::is_parent($current_user_id);
            $is_member = FTT_Roles::is_member($current_user_id);
            
            // Debug logging
            error_log("FTT REST get_events - User ID: {$current_user_id}, is_parent: " . ($is_parent ? 'true' : 'false') . ", is_member: " . ($is_member ? 'true' : 'false'));
            
            if ($is_parent) {
                // User is a parent - show all children's events from their groups only
                $children = FTT_Family_Groups::get_user_children($current_user_id, $group_id);
                error_log("FTT REST get_events - Children found: " . print_r($children, true));
                
                if (!empty($children)) {
                    $meta_query[] = array(
                        'key'     => 'member_id',
                        'value'   => $children,
                        'compare' => 'IN',
                    );
                } else {
                    // Parent with no children - return empty
                    error_log("FTT REST get_events - Parent has no children, returning empty");
                    return rest_ensure_response(array());
                }
            } elseif ($is_member) {
                // User is a member - show only their own events
                $meta_query[] = array(
                    'key'     => 'member_id',
                    'value'   => $current_user_id,
                    'compare' => '=',
                );
            } else {
                // Not a parent or member - no access to any events (security)
                error_log("FTT REST get_events - User is neither parent nor member, returning empty");
                return rest_ensure_response(array());
            }
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
        
        if (!$post || $post->post_type !== 'ftt_event') {
            return new WP_Error('not_found', __('Event not found', 'schedule-collaboration-tracking'), array('status' => 404));
        }
        
        return rest_ensure_response(self::format_event($post));
    }
    
    /**
     * Create event
     */
    public static function create_event($request) {
        $post_data = array(
            'post_type'   => 'ftt_event',
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
        
        if (!$post || $post->post_type !== 'ftt_event') {
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
        
        if (!$post || $post->post_type !== 'ftt_event') {
            return new WP_Error('not_found', __('Event not found', 'schedule-collaboration-tracking'), array('status' => 404));
        }
        
        // Delete all price alerts associated with this event
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
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
        $url = FTT_Pages::get_registration_url();
        
        if (!$url) {
            return new WP_Error(
                'no_registration_page',
                __('Registration page not found. Please create a page with the [ftt_register] shortcode.', 'schedule-collaboration-tracking'),
                array('status' => 404)
            );
        }
        
        return rest_ensure_response(array('url' => $url));
    }
    
    /**
     * Get dashboard data
     */
    public static function get_dashboard($request) {
        global $wpdb;

        $current_user_id = get_current_user_id();
        $group_id        = absint($request->get_param('group_id'));
        $now             = current_time('mysql');
        $thirty_days     = date('Y-m-d H:i:s', strtotime('+30 days', current_time('timestamp')));

        error_log('=== DASHBOARD DEBUG ===');
        error_log('Current User ID: ' . $current_user_id);
        error_log('Group ID Filter: ' . ($group_id ?: 'none'));
        error_log('Is Member: '  . (FTT_Roles::is_member($current_user_id)  ? 'yes' : 'no'));
        error_log('Is Parent: '  . (FTT_Family_Groups::is_parent($current_user_id)  ? 'yes' : 'no'));

        // ----------------------------------------------------------------
        // Determine event scope
        // Group mode  → use ftt_event_groups (matches calendar endpoint).
        //               This captures ALL group events regardless of member_id,
        //               including family events (member_id = '').
        // Member mode → single user's own events.
        // Parent mode → children's events via legacy ftt_parent_of meta,
        //               plus unassigned / family events.
        // ----------------------------------------------------------------
        $group_event_ids = null; // non-null only in group mode
        $member_ids      = array();

        if ($group_id && class_exists('FTT_Family_Groups')) {
            // Verify the caller has access to this group.
            if (!FTT_Family_Groups::can_manage_group($group_id, $current_user_id)
                && !current_user_can('manage_options')) {
                return rest_ensure_response(array(
                    'flights_needed'  => array(),
                    'not_booked'      => array(),
                    'upcoming_travel' => array(),
                ));
            }

            // Events explicitly associated with the group (family events, etc.)
            $from_group_table = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->prefix}ftt_event_groups WHERE group_id = %d",
                $group_id
            ));

            // Also collect events via member_id of every group parent's children
            // (covers events that predate or missed the ftt_event_groups insert).
            $from_member_ids = array();
            $group_parents = FTT_Family_Groups::get_group_members($group_id, 'parent');
            foreach ($group_parents as $parent_member) {
                $parent_child_ids = get_user_meta((int) $parent_member->user_id, 'ftt_parent_of', true);
                if (is_array($parent_child_ids)) {
                    foreach ($parent_child_ids as $cid) {
                        $cid = (int) $cid;
                        if ($cid && !in_array($cid, $from_member_ids, true)) {
                            $from_member_ids[] = $cid;
                        }
                    }
                }
            }
            // Also include children formally in group_members
            $group_children = FTT_Family_Groups::get_group_members($group_id, 'child');
            foreach ($group_children as $cm) {
                $cid = (int) $cm->user_id;
                if (!in_array($cid, $from_member_ids, true)) {
                    $from_member_ids[] = $cid;
                }
            }

            // Fetch event IDs for those member_ids
            $member_based_ids = array();
            if (!empty($from_member_ids)) {
                $placeholders     = implode(',', array_fill(0, count($from_member_ids), '%d'));
                $member_based_ids = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                         WHERE meta_key = 'member_id' AND meta_value IN ($placeholders)",
                        $from_member_ids
                    )
                );
            }

            $group_event_ids = array_unique(array_merge(
                array_map('intval', $from_group_table),
                array_map('intval', $member_based_ids)
            ));
            error_log('Group mode: found ' . count($group_event_ids) . ' events (table:' . count($from_group_table) . ' + member_id:' . count($member_based_ids) . ')');

            if (empty($group_event_ids)) {
                error_log('No events in group - returning empty data');
                return rest_ensure_response(array(
                    'flights_needed'  => array(),
                    'not_booked'      => array(),
                    'upcoming_travel' => array(),
                ));
            }

        } elseif (FTT_Roles::is_member($current_user_id)) {
            $member_ids = array($current_user_id);
            error_log('Member mode: showing events for user ' . $current_user_id);

        } elseif (FTT_Family_Groups::is_parent($current_user_id)) {
            $children = FTT_Family_Groups::get_user_children($current_user_id);
            error_log('Parent mode: found ' . count($children) . ' children');

            if (!empty($children)) {
                $member_ids = $children;
                error_log('Member IDs to query: ' . implode(', ', $member_ids));
            }
        }

        // No filter criteria at all → return empty.
        if ($group_event_ids === null && empty($member_ids)) {
            error_log('No filter criteria - returning empty data');
            return rest_ensure_response(array(
                'flights_needed'  => array(),
                'not_booked'      => array(),
                'upcoming_travel' => array(),
            ));
        }

        // ----------------------------------------------------------------
        // Build the member meta query (non-group mode only).
        // ----------------------------------------------------------------
        $member_meta_query = null;
        if ($group_event_ids === null) {
            $member_meta_query = array('relation' => 'OR');
            foreach ($member_ids as $mid) {
                $member_meta_query[] = array(
                    'key'     => 'member_id',
                    'value'   => $mid,
                    'compare' => '=',
                );
            }
            // Parents also see unassigned / family events and their own events.
            if (FTT_Family_Groups::is_parent($current_user_id)) {
                $member_meta_query[] = array('key' => 'member_id', 'compare' => 'NOT EXISTS');
                $member_meta_query[] = array('key' => 'member_id', 'value' => '',                  'compare' => '=');
                $member_meta_query[] = array('key' => 'member_id', 'value' => $current_user_id, 'compare' => '=');
            }
        }

        // ----------------------------------------------------------------
        // Helper: build base WP_Query args, injecting post__in OR meta filter.
        // ----------------------------------------------------------------
        $base_args = array(
            'post_type'      => 'ftt_event',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value',
            'meta_key'       => 'start_datetime',
            'order'          => 'ASC',
        );
        if ($group_event_ids !== null) {
            $base_args['post__in'] = $group_event_ids;
        }

        // ----------------------------------------------------------------
        // Flights needed: future events with at least one unbooked fly leg.
        // ----------------------------------------------------------------
        $flights_meta = array(
            'relation' => 'AND',
            array('key' => 'flight_needed', 'value' => '1', 'compare' => '='),
            array('key' => 'start_datetime', 'value' => $now, 'compare' => '>=', 'type' => 'DATETIME'),
        );
        if ($member_meta_query !== null) {
            $flights_meta[] = $member_meta_query;
        }

        $flights_needed = new WP_Query(array_merge($base_args, array('meta_query' => $flights_meta)));
        error_log('Flights needed query found ' . $flights_needed->found_posts . ' posts');

        $flights_needed_data = array();
        $not_booked_data     = array();

        foreach ($flights_needed->posts as $post) {
            $travel_legs = json_decode(get_post_meta($post->ID, 'travel_legs', true) ?: '[]', true);
            $has_unbooked = false;
            foreach ($travel_legs as $leg) {
                if (isset($leg['mode']) && $leg['mode'] === 'fly' && empty($leg['booked'])) {
                    $has_unbooked = true;
                    break;
                }
            }
            if ($has_unbooked) {
                $event_data            = self::format_event($post);
                $flights_needed_data[] = $event_data;
                $not_booked_data[]     = $event_data;
            }
        }

        // ----------------------------------------------------------------
        // Upcoming travel: next 30 days with travel_needed = 1.
        // ----------------------------------------------------------------
        $travel_meta = array(
            'relation' => 'AND',
            array('key' => 'travel_needed', 'value' => '1', 'compare' => '='),
            array('key' => 'start_datetime', 'value' => $now,          'compare' => '>=', 'type' => 'DATETIME'),
            array('key' => 'start_datetime', 'value' => $thirty_days,  'compare' => '<=', 'type' => 'DATETIME'),
        );
        if ($member_meta_query !== null) {
            $travel_meta[] = $member_meta_query;
        }

        $upcoming_travel = new WP_Query(array_merge($base_args, array('meta_query' => $travel_meta)));

        $upcoming_travel_data = array();
        foreach ($upcoming_travel->posts as $post) {
            $upcoming_travel_data[] = self::format_event($post);
        }

        error_log('=== DASHBOARD RESULTS ===');
        error_log('Flights needed: '   . count($flights_needed_data));
        error_log('Not booked: '       . count($not_booked_data));
        error_log('Upcoming travel: '  . count($upcoming_travel_data));
        error_log('=== END DASHBOARD DEBUG ===');

        return rest_ensure_response(array(
            'flights_needed'  => $flights_needed_data,
            'not_booked'      => $not_booked_data,
            'upcoming_travel' => $upcoming_travel_data,
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
        if (FTT_Family_Groups::is_parent($current_user->ID)) {
            $children = FTT_Family_Groups::get_user_children($current_user->ID);
            if (!empty($children)) {
                $member_ids = array_merge($member_ids, $children);
            }
        }
        
        // Get all flight groups for these members
        $all_groups = array();
        foreach ($member_ids as $member_id) {
            $member_groups = FTT_Flight_Linking::get_member_flight_groups($member_id);
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
            $pricing_response = FTT_Flight_Linking::get_flight_group_pricing(
                new WP_REST_Request('GET', '/ftt/v1/flight-group-pricing/' . $group['group_id'])
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
        $legs = FTT_Flight_Linking::get_flight_group_legs($group_id);
        
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
            'group_id',
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
        
        // Handle group association (v2.1)
        if ($request->has_param('group_id')) {
            $group_id = absint($request->get_param('group_id'));
            if ($group_id > 0) {
                FTT_Family_Groups::add_event_to_group($post_id, $group_id);
            }
        } else {
            // Use existing group_id meta if present, otherwise fall back to primary group.
            $existing_group = absint(get_post_meta($post_id, 'group_id', true));
            if (!$existing_group) {
                $current_user_id = get_current_user_id();
                $primary_group   = get_user_meta($current_user_id, 'ftt_primary_group', true);
                if ($primary_group) {
                    $group_exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}ftt_family_groups WHERE id = %d",
                        $primary_group
                    ));
                    if ($group_exists) {
                        $existing_group = (int) $primary_group;
                        update_post_meta($post_id, 'group_id', $existing_group);
                    }
                }
            }
            // Ensure the ftt_event_groups row exists for this event (idempotent).
            if ($existing_group) {
                FTT_Family_Groups::add_event_to_group($post_id, $existing_group);
            }
        }
        
        // Fire action so other classes (e.g. AI parser) can react after all meta is written.
        do_action( 'ftt_event_meta_saved', $post_id, $request );

        // Check for newly booked flights and delete associated price alerts
        if ($request->has_param('travel_legs')) {
            $raw_legs = $request->get_param('travel_legs');
            $new_travel_legs = is_array($raw_legs) ? $raw_legs : json_decode($raw_legs, true);
            
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
                            $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
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
        if (!$event || $event->post_type !== 'ftt_event') {
            return new WP_Error('invalid_event', 'Invalid event ID', array('status' => 404));
        }
        
        // Insert alert
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
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
        $confirmation = FTT_Price_Tracking::send_alert_confirmation($alert_id);
        
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
        if (!$event || $event->post_type !== 'ftt_event') {
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
        
        // Method 0: Check explicit is_round_trip flag (new preferred method)
        if (!empty($leg['is_round_trip']) && !empty($leg['return_date'])) {
            $is_round_trip = true;
            $return_date = $leg['return_date'];
            error_log("SRT: Using explicit round-trip flag with return date {$return_date}");
        }
        
        // Method 1: Check if this single leg has arrive_date spanning multiple days (legacy method)
        if (!$is_round_trip && !empty($leg['arrive_date']) && !empty($leg['depart_date'])) {
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
            $price_result = FTT_Price_Tracking::fetch_flight_price_serpapi(
                $leg['depart_airport'],
                $leg['arrive_airport'],
                $leg['depart_date'],
                $return_date  // Pass return date for round-trip
            );
        } else {
            $price_result = FTT_Price_Tracking::fetch_flight_price_serpapi(
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
        $recorded_id = FTT_Price_Tracking::record_price(
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
        
        $table_name = $wpdb->prefix . 'ftt_price_history';
        
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
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
        
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
        $alerts_table = $wpdb->prefix . 'ftt_price_alerts';
        
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
            'home_airport' => get_user_meta($user_id, 'ftt_home_airport', true) ?: '',
            'timezone' => get_user_meta($user_id, 'ftt_timezone', true) ?: wp_timezone_string(),
        );
        
        return rest_ensure_response($preferences);
    }
    
    /**
     * Update user preferences
     */
    public static function update_user_preferences($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        // Handle visible event categories
        if (isset($params['visible_categories']) && is_array($params['visible_categories'])) {
            update_user_meta($user_id, 'ftt_visible_event_categories', $params['visible_categories']);
        }
        
        // Handle home_airport from either JSON params or regular params
        $home_airport = isset($params['home_airport']) ? $params['home_airport'] : $request->get_param('home_airport');
        if ($home_airport !== null) {
            update_user_meta($user_id, 'ftt_home_airport', strtoupper(sanitize_text_field($home_airport)));
        }
        
        // Handle timezone from either JSON params or regular params
        $timezone = isset($params['timezone']) ? $params['timezone'] : $request->get_param('timezone');
        if ($timezone !== null) {
            update_user_meta($user_id, 'ftt_timezone', sanitize_text_field($timezone));
        }

        // Handle airport reminder dismissed flag.
        if ( ! empty( $params['airport_reminder_dismissed'] ) ) {
            update_user_meta( $user_id, 'ftt_airport_reminder_dismissed', 1 );
        }

        // Handle AI-suggested home airport save (explicit user preference stated in prompt).
        $save_home_airport = isset( $params['save_home_airport'] ) ? $params['save_home_airport'] : null;
        if ( $save_home_airport !== null ) {
            $clean = strtoupper( preg_replace( '/[^A-Za-z]/', '', $save_home_airport ) );
            if ( strlen( $clean ) === 3 ) {
                update_user_meta( $user_id, 'ftt_home_airport', $clean );
            }
        }
        
        return rest_ensure_response(array('success' => true, 'message' => 'Preferences updated'));
    }
    
    /**
     * Update user's primary group
     */
    public static function update_primary_group($request) {
        $user_id = get_current_user_id();
        $group_id = $request->get_param('group_id');
        
        // Verify user is a member of this group
        $user_groups = FTT_Family_Groups::get_user_groups($user_id);
        $is_member = false;
        
        foreach ($user_groups as $group) {
            if ($group->id == $group_id) {
                $is_member = true;
                break;
            }
        }
        
        if (!$is_member) {
            return new WP_Error('not_member', 'You are not a member of this group', array('status' => 403));
        }
        
        update_user_meta($user_id, 'ftt_primary_group', $group_id);
        
        return rest_ensure_response(array(
            'success' => true, 
            'message' => 'Primary group updated',
            'group_id' => $group_id
        ));
    }
    
    /**
     * Lookup user by email (for adding members to groups)
     */
    public static function lookup_user_by_email($request) {
        $email = $request->get_param('email');
        
        if (empty($email)) {
            return new WP_Error('missing_email', 'Email address is required', array('status' => 400));
        }
        
        $user = get_user_by('email', $email);
        
        if (!$user) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => 'No user found with that email address'
            ));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'user_id' => $user->ID,
            'display_name' => $user->display_name,
            'email' => $user->user_email
        ));
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
    
    /**
     * Register new user and create checkout session
     * Public endpoint for sign-up page on www domain
     */
    public static function register_new_user($request) {
        $params = $request->get_json_params();
        
        // Validate required fields
        $email = sanitize_email($params['email'] ?? '');
        $name = sanitize_text_field($params['name'] ?? '');
        $password = $params['password'] ?? '';
        $interval = $params['interval'] ?? 'month';
        $addon_quantity = (int) ($params['addon_quantity'] ?? 0);
        
        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Please provide a valid email address', ['status' => 400]);
        }
        
        if (empty($name)) {
            return new WP_Error('invalid_name', 'Please provide your name', ['status' => 400]);
        }
        
        if (empty($password) || strlen($password) < 8) {
            return new WP_Error('invalid_password', 'Password must be at least 8 characters', ['status' => 400]);
        }
        
        if (!in_array($interval, ['month', 'year'])) {
            return new WP_Error('invalid_interval', 'Invalid billing interval', ['status' => 400]);
        }
        
        // Check if user already exists
        if (email_exists($email)) {
            return new WP_Error('email_exists', 'An account with this email already exists. Please log in instead.', ['status' => 400]);
        }
        
        // Create WordPress user
        $user_id = wp_create_user($email, $password, $email);
        
        if (is_wp_error($user_id)) {
            return new WP_Error('registration_failed', 'Failed to create account: ' . $user_id->get_error_message(), ['status' => 500]);
        }
        
        // Update user display name
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $name,
        ]);
        
        // Set user role to subscriber
        $user = new WP_User($user_id);
        $user->set_role('subscriber');
        
        // Log user in
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        do_action('ftt_user_registered', $user_id, $email, $name);
        
        // Create Stripe checkout session
        if (!class_exists('FTT_Stripe_Integration')) {
            return new WP_Error('stripe_unavailable', 'Billing is not configured on this site. Please contact support.', ['status' => 503]);
        }
        $session = FTT_Stripe_Integration::create_checkout_session($user_id, $interval, $addon_quantity);
        
        if (!$session) {
            // If checkout fails, still keep the account but return error
            return new WP_Error('checkout_failed', 'Account created but failed to initialize checkout. Please contact support.', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'user_id' => $user_id,
            'checkout_url' => $session['url'],
        ]);
    }
    
    /**
     * Create Stripe checkout session (for logged-in users)
     */
    public static function create_checkout_session($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        $interval = $params['interval'] ?? 'month';
        $addon_quantity = (int) ($params['addon_quantity'] ?? 0);
        
        if (!in_array($interval, ['month', 'year'])) {
            return new WP_Error('invalid_interval', 'Invalid billing interval', ['status' => 400]);
        }
        
        $session = FTT_Stripe_Integration::create_checkout_session($user_id, $interval, $addon_quantity);
        
        if (!$session) {
            return new WP_Error('checkout_failed', 'Failed to create checkout session', ['status' => 500]);
        }
        
        return rest_ensure_response($session);
    }
    
    /**
     * Add child addon to subscription
     */
    public static function add_child_addon($request) {
        $user_id = get_current_user_id();
        
        $success = FTT_Stripe_Integration::add_child_addon($user_id);
        
        if (!$success) {
            return new WP_Error('addon_failed', 'Failed to add child addon', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }
    
    /**
     * Cancel subscription
     */
    public static function cancel_subscription($request) {
        $user_id = get_current_user_id();
        
        $success = FTT_Stripe_Integration::cancel_subscription($user_id);
        
        if (!$success) {
            return new WP_Error('cancel_failed', 'Failed to cancel subscription', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }
    
    /**
     * Reactivate canceled subscription
     */
    public static function reactivate_subscription($request) {
        $user_id = get_current_user_id();
        
        $success = FTT_Stripe_Integration::reactivate_subscription($user_id);
        
        if (!$success) {
            return new WP_Error('reactivate_failed', 'Failed to reactivate subscription', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }
    
    /**
     * Add child to parent account
     */
    public static function add_child($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        error_log('FTT REST: Adding child - User ID: ' . $user_id);
        
        $first_name = sanitize_text_field($params['first_name'] ?? '');
        $last_name = sanitize_text_field($params['last_name'] ?? '');
        $email = sanitize_email($params['email'] ?? '');
        $age = absint($params['age'] ?? 0);
        $grade = sanitize_text_field($params['grade'] ?? '');
        $school = sanitize_text_field($params['school'] ?? '');
        $color = sanitize_hex_color($params['color'] ?? '#2196F3');
        
        if (empty($first_name) || empty($last_name)) {
            return new WP_Error('missing_data', 'First name and last name are required', ['status' => 400]);
        }
        
        $child_id = null;
        
        // If email provided, try to find existing user
        if (!empty($email)) {
            $existing_user = get_user_by('email', $email);
            if ($existing_user) {
                $child_id = $existing_user->ID;
                error_log('FTT REST: Found existing user with email: ' . $email);
            }
        }
        
        // If no existing user, create new one
        if (!$child_id) {
            $username = !empty($email) ? $email : strtolower($first_name . '.' . $last_name) . rand(100, 999);
            $password = wp_generate_password(12, true, true);
            
            $child_id = wp_create_user($username, $password, $email);
            
            if (is_wp_error($child_id)) {
                error_log('FTT REST: Failed to create user: ' . $child_id->get_error_message());
                return $child_id;
            }
            
            // Set user metadata
            wp_update_user([
                'ID' => $child_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $first_name . ' ' . $last_name,
            ]);
            
            // Set role
            $user = new WP_User($child_id);
            $user->set_role('subscriber');
            FTT_Roles::make_member($child_id);

            error_log('FTT REST: Created new user ID: ' . $child_id);
        }

        // Ensure member flag is set for both new and existing users
        FTT_Roles::make_member($child_id);

        // Update child metadata
        if ($age > 0) {
            update_user_meta($child_id, 'child_age', $age);
        }
        if (!empty($grade)) {
            update_user_meta($child_id, 'child_grade', $grade);
        }
        if (!empty($school)) {
            update_user_meta($child_id, 'child_school', $school);
        }
        if (!empty($color) && class_exists('FTT_Child_Colors')) {
            FTT_Child_Colors::update_color($child_id, $color);
        }
        
        // Add child to the correct group (group-based system)
        // This is REQUIRED - all children must belong to a group
        if (class_exists('FTT_Family_Groups')) {
            $group_id_to_use = null;
            
            // Prefer the group explicitly specified by the caller (e.g. ?group=X page).
            $requested_group_id = absint($params['group_id'] ?? 0);
            if ($requested_group_id && FTT_Family_Groups::can_manage_group($requested_group_id, $user_id)) {
                $group_id_to_use = $requested_group_id;
            } 
            // Fall back to the parent's primary group
            else {
                $primary_group_id = get_user_meta($user_id, 'ftt_primary_group_id', true);
                if ($primary_group_id) {
                    $group_id_to_use = (int) $primary_group_id;
                }
            }
            
            // If still no group, try to find any group the parent belongs to
            if (!$group_id_to_use) {
                $parent_groups = FTT_Family_Groups::get_user_groups($user_id);
                if (!empty($parent_groups)) {
                    $group_id_to_use = $parent_groups[0]->id;
                    // Set this as primary group if not set
                    if (!get_user_meta($user_id, 'ftt_primary_group_id', true)) {
                        update_user_meta($user_id, 'ftt_primary_group_id', $group_id_to_use);
                    }
                }
            }
            
            // Add to group if we found one
            if ($group_id_to_use) {
                // Check if child is already in this group to avoid duplicates
                if (!FTT_Family_Groups::is_member($group_id_to_use, $child_id)) {
                    FTT_Family_Groups::add_member($group_id_to_use, $child_id, 'child');
                    error_log("FTT REST: Added child $child_id to group $group_id_to_use");
                }
            } else {
                error_log("FTT REST: WARNING - Child $child_id was added to parent $user_id but no group found. Child may not appear in Family Groups.");
            }
        }
        
        error_log('FTT REST: Child linked successfully');
        
        return rest_ensure_response([
            'success' => true,
            'child_id' => $child_id,
            'message' => 'Child added successfully'
        ]);
    }
    
    /**
     * Edit child information
     */
    public static function edit_child($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        // Accept child_id from URL param (PUT /children/{id}) or JSON body (POST /edit-child)
        $child_id = absint($request->get_param('id') ?: 0);
        if (!$child_id) {
            $child_id = absint($params['child_id'] ?? 0);
        }

        if (!$child_id) {
            return new WP_Error('missing_child_id', 'Child ID is required', ['status' => 400]);
        }

        // Verify parent-child relationship via groups
        $children = FTT_Family_Groups::get_user_children($user_id);
        $can_manage = FTT_Family_Groups::is_parent($user_id) && in_array($child_id, $children);

        if (!$can_manage) {
            return new WP_Error('unauthorized', 'You do not have permission to edit this child', ['status' => 403]);
        }
        
        $first_name = sanitize_text_field($params['first_name'] ?? '');
        $last_name = sanitize_text_field($params['last_name'] ?? '');
        $age = absint($params['age'] ?? 0);
        $grade = sanitize_text_field($params['grade'] ?? '');
        $school = sanitize_text_field($params['school'] ?? '');
        $color = sanitize_hex_color($params['color'] ?? '#2196F3');
        
        // Update user data
        if (!empty($first_name) && !empty($last_name)) {
            wp_update_user([
                'ID' => $child_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $first_name . ' ' . $last_name,
            ]);
        }
        
        // Update metadata
        if ($age > 0) {
            update_user_meta($child_id, 'child_age', $age);
        }
        if (!empty($grade)) {
            update_user_meta($child_id, 'child_grade', $grade);
        }
        if (!empty($school)) {
            update_user_meta($child_id, 'child_school', $school);
        }
        if (!empty($color) && class_exists('FTT_Child_Colors')) {
            FTT_Child_Colors::update_color($child_id, $color);
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Child updated successfully'
        ]);
    }
    
    /**
     * Remove child from parent account
     */
    public static function remove_child($request) {
        $user_id = get_current_user_id();

        // Accept child_id from URL param (DELETE /children/{id}) or JSON body (POST /remove-child)
        $child_id = absint($request->get_param('id') ?: 0);
        if (!$child_id) {
            $params = $request->get_json_params();
            $child_id = absint($params['child_id'] ?? 0);
        }

        if (!$child_id) {
            return new WP_Error('missing_child_id', 'Child ID is required', ['status' => 400]);
        }

        // Verify parent-child relationship via groups
        $children = FTT_Family_Groups::get_user_children($user_id);
        $can_manage = FTT_Family_Groups::is_parent($user_id) && in_array($child_id, $children);
        
        // Get primary group for removal
        $primary_group_id = 0;
        if (class_exists('FTT_Family_Groups')) {
            $primary_group_id = (int) get_user_meta($user_id, 'ftt_primary_group', true);
        }

        if (!$can_manage) {
            return new WP_Error('unauthorized', 'You do not have permission to remove this child', ['status' => 403]);
        }

        // Remove from group (group-based system)
        if ($primary_group_id && class_exists('FTT_Family_Groups')) {
            FTT_Family_Groups::remove_member($primary_group_id, $child_id);
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Child removed successfully'
        ]);
    }
    
    /**
     * Invite adult (co-parent/guardian)
     */
    public static function invite_adult($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        $email = sanitize_email($params['email'] ?? '');
        $relationship = sanitize_text_field($params['relationship'] ?? 'co-parent');
        $group_id = isset($params['group_id']) ? absint($params['group_id']) : 0;
        $can_manage_group = isset($params['can_manage_group']) ? (bool) $params['can_manage_group'] : true;
        
        if (empty($email)) {
            return new WP_Error('missing_email', 'Email address is required', ['status' => 400]);
        }

        // Check if someone with this email already has an account
        $existing_user = get_user_by('email', $email);
        if ($existing_user) {
            // Prevent inviting yourself
            if ($existing_user->ID === $user_id) {
                return new WP_Error('self_invite', 'You cannot invite yourself', ['status' => 400]);
            }

            // If group mode, check for duplicate membership first
            if ($group_id && class_exists('FTT_Family_Groups')) {
                if (FTT_Family_Groups::is_member($group_id, $existing_user->ID)) {
                    return new WP_Error('already_member', 'This user is already a member of the group', ['status' => 400]);
                }
                FTT_Family_Groups::add_member($group_id, $existing_user->ID, 'parent', [
                    'relationship'     => $relationship,
                    'can_manage_group' => $can_manage_group,
                    'added_by'         => $user_id,
                ]);
            }

            // Add adult as parent to all children in user's groups
            $children = FTT_Family_Groups::get_user_children($user_id);
            foreach ($children as $child_id) {
                // Children are already in the same groups through group membership
                // No additional linking needed with group-based architecture
            }

            // Send a simple notification (not a registration link)
            $current_user = wp_get_current_user();
            $dashboard_url = FTT_Pages::get_page_url('dashboard') ?: home_url('/ftt-dashboard/');
            $subject = sprintf('You have been added to %s\'s family calendar', $current_user->display_name);
            $message = sprintf(
                "%s has added you as a %s on their family calendar.\n\n" .
                "Log in to view the shared calendar: %s",
                $current_user->display_name,
                $relationship,
                $dashboard_url
            );
            wp_mail($email, $subject, $message);

            return rest_ensure_response([
                'success' => true,
                'added_directly' => true,
                'message' => 'User already has an account and has been added directly',
            ]);
        }
        
        // User doesn't exist — create a pending invitation and send a registration link
        $settings = get_option('ftt_settings', array());
        $expiration_days = isset($settings['invitation_expiration_days']) ? absint($settings['invitation_expiration_days']) : 7;
        
        // Generate invitation
        $invite_code = wp_generate_password(12, false);
        $created = time();
        $expires = $created + ($expiration_days * DAY_IN_SECONDS);
        
        // Store invitation data
        $invitations = get_user_meta($user_id, 'ftt_adult_invitations', true);
        if (!is_array($invitations)) {
            $invitations = [];
        }
        
        $invitations[$invite_code] = [
            'email' => $email,
            'relationship' => $relationship,
            'group_id' => $group_id,
            'can_manage_group' => $can_manage_group,
            'expires' => $expires,
            'created' => $created,
            'status' => 'pending',
            'inviter_id' => $user_id, // Store inviter ID in invitation data
        ];
        
        update_user_meta($user_id, 'ftt_adult_invitations', $invitations);
        
        // Generate invitation URL - only needs the code now
        $register_url = FTT_Pages::get_page_url('register');
        if (!$register_url) {
            $register_url = home_url('/ftt-register/');
        }
        
        $invite_url = add_query_arg([
            'ftt_invite' => $invite_code,
        ], $register_url);
        
        // Send email
        $current_user = wp_get_current_user();
        $subject = sprintf('Family Calendar Invitation from %s', $current_user->display_name);
        $message = sprintf(
            "You've been invited by %s to share access to their family calendar.\n\n" .
            "Click here to accept: %s\n\n" .
            "This invitation expires in %d days.",
            $current_user->display_name,
            $invite_url,
            $expiration_days
        );
        
        wp_mail($email, $subject, $message);
        
        return rest_ensure_response([
            'success' => true,
            'invite_url' => $invite_url,
            'message' => 'Invitation sent successfully'
        ]);
    }
    
    /**
     * Cancel pending invitation
     */
    public static function cancel_invitation($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        $invite_code = sanitize_text_field($params['invite_code'] ?? '');
        
        if (empty($invite_code)) {
            return new WP_Error('missing_code', 'Invitation code is required', ['status' => 400]);
        }
        
        // Get invitations
        $invitations = get_user_meta($user_id, 'ftt_adult_invitations', true);
        if (!is_array($invitations)) {
            return new WP_Error('not_found', 'Invitation not found', ['status' => 404]);
        }
        
        // Check if invitation exists
        if (!isset($invitations[$invite_code])) {
            return new WP_Error('not_found', 'Invitation not found', ['status' => 404]);
        }
        
        // Remove invitation
        unset($invitations[$invite_code]);
        update_user_meta($user_id, 'ftt_adult_invitations', $invitations);
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Invitation cancelled successfully'
        ]);
    }
    
    /**
     * Resend invitation email
     */
    public static function resend_invitation($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        $invite_code = sanitize_text_field($params['invite_code'] ?? '');
        
        if (empty($invite_code)) {
            return new WP_Error('missing_code', 'Invitation code is required', ['status' => 400]);
        }
        
        // Get invitations
        $invitations = get_user_meta($user_id, 'ftt_adult_invitations', true);
        if (!is_array($invitations) || !isset($invitations[$invite_code])) {
            return new WP_Error('not_found', 'Invitation not found', ['status' => 404]);
        }
        
        $invite = $invitations[$invite_code];
        
        // Check if already expired
        if ($invite['expires'] < time()) {
            return new WP_Error('expired', 'Invitation has expired', ['status' => 400]);
        }
        
        // Get expiration days from settings
        $settings = get_option('ftt_settings', array());
        $expiration_days = isset($settings['invitation_expiration_days']) ? absint($settings['invitation_expiration_days']) : 7;
        
        // Generate invitation URL (same as invite_adult - points to registration page)
        $register_url = FTT_Pages::get_page_url('register');
        if (!$register_url) {
            $register_url = home_url('/ftt-register/');
        }
        $invite_url = add_query_arg([
            'ftt_invite' => $invite_code,
        ], $register_url);
        
        // Send email
        $current_user = wp_get_current_user();
        $subject = sprintf('Family Calendar Invitation from %s (Reminder)', $current_user->display_name);
        $message = sprintf(
            "This is a reminder that you've been invited by %s to share access to their family calendar.\n\n" .
            "Click here to accept: %s\n\n" .
            "This invitation expires on %s.",
            $current_user->display_name,
            $invite_url,
            date_i18n(get_option('date_format'), $invite['expires'])
        );
        
        wp_mail($invite['email'], $subject, $message);
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Invitation resent successfully'
        ]);
    }
    
    /**
     * Remove adult access
     */
    public static function remove_adult($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        $adult_id = absint($params['adult_id'] ?? 0);
        
        if (!$adult_id) {
            return new WP_Error('missing_adult_id', 'Adult ID is required', ['status' => 400]);
        }
        
        // Get current user's children from groups
        $children = FTT_Family_Groups::get_user_children($user_id);
        
        // Remove adult from all groups where these children exist
        // This is handled by FTT_Family_Groups::remove_member() in the calling code
        // Get user's groups and remove the adult from each
        $user_groups = FTT_Family_Groups::get_user_groups($user_id);
        foreach ($user_groups as $group) {
            FTT_Family_Groups::remove_member($group->id, $adult_id);
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Adult access removed successfully'
        ]);
    }
    
    /**
     * Save event preferences (visible categories)
     */
    public static function save_event_preferences($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        $visible_categories = $params['visible_categories'] ?? [];
        
        if (!is_array($visible_categories)) {
            return new WP_Error('invalid_data', 'Visible categories must be an array', ['status' => 400]);
        }
        
        // Sanitize categories
        $visible_categories = array_map('sanitize_text_field', $visible_categories);
        
        // Save to user meta
        update_user_meta($user_id, 'ftt_visible_event_categories', $visible_categories);
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'Preferences saved successfully'
        ]);
    }
    
    /**
     * Get family members (children and adults)
     */
    /**
     * Get current user's children (RESTful GET /children)
     */
    public static function get_children_list($request) {
        $user_id = get_current_user_id();

        // Get children from groups (primary source)
        $child_ids = FTT_Family_Groups::get_user_children($user_id);

        $children = [];
        foreach ($child_ids as $child_id) {
            $child = get_userdata($child_id);
            if ($child) {
                $children[] = [
                    'id'         => $child_id,
                    'name'       => $child->display_name,
                    'first_name' => $child->first_name,
                    'last_name'  => $child->last_name,
                    'email'      => $child->user_email,
                    'age'        => get_user_meta($child_id, 'child_age', true),
                    'grade'      => get_user_meta($child_id, 'child_grade', true),
                    'school'     => get_user_meta($child_id, 'child_school', true),
                    'color'      => get_user_meta($child_id, 'child_color', true),
                ];
            }
        }

        return rest_ensure_response(['children' => $children]);
    }

    /**
     * Get all family members (children and adults)
     */
    public static function get_family_members($request) {
        $user_id = get_current_user_id();
        
        $children = [];
        $child_ids = FTT_Family_Groups::get_user_children($user_id);
        
        foreach ($child_ids as $child_id) {
            $child = get_userdata($child_id);
            if ($child) {
                $children[] = [
                    'id' => $child_id,
                    'name' => $child->display_name,
                    'first_name' => $child->first_name,
                    'last_name' => $child->last_name,
                    'email' => $child->user_email,
                    'age' => get_user_meta($child_id, 'child_age', true),
                    'grade' => get_user_meta($child_id, 'child_grade', true),
                    'school' => get_user_meta($child_id, 'child_school', true),
                    'color' => get_user_meta($child_id, 'child_color', true),
                ];
            }
        }
        
        $adults = [];
        $parent_ids = FTT_Family_Groups::get_user_parents($user_id);
        
        foreach ($parent_ids as $parent_id) {
            if ($parent_id == $user_id) continue; // Skip self
            
            $parent = get_userdata($parent_id);
            if ($parent) {
                $adults[] = [
                    'id' => $parent_id,
                    'name' => $parent->display_name,
                    'email' => $parent->user_email,
                    'relationship' => get_user_meta($parent_id, 'relationship_to_' . $user_id, true),
                ];
            }
        }
        
        return rest_ensure_response([
            'children' => $children,
            'adults' => $adults,
        ]);
    }
    
    /**
     * Sync orphaned children to groups
     * 
     * Finds children that exist in the old parent-child relationship system
     * but are NOT in any Family Group, then adds them to their parent's primary group.
     * 
     * @return WP_REST_Response
     */
    public static function sync_children_to_groups($request) {
        if (!class_exists('FTT_Family_Groups')) {
            return new WP_Error('groups_disabled', 'Family Groups feature not available', ['status' => 400]);
        }
        
        global $wpdb;
        $report = [
            ' synced' => 0,
            'skipped' => 0,
            'errors' => [],
            'details' => [],
        ];
        
        // Get all users who have children in the old system
        $parents_with_children = $wpdb->get_results(
            "SELECT user_id, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'ftt_parent_of'"
        );
        
        foreach ($parents_with_children as $row) {
            $parent_id = $row->user_id;
            $children_ids = maybe_unserialize($row->meta_value);
            
            if (!is_array($children_ids) || empty($children_ids)) {
                continue;
            }
            
            // Get parent's primary group
            $primary_group_id = get_user_meta($parent_id, 'ftt_primary_group', true);
            
            // If no primary group, try to find any group they belong to
            if (!$primary_group_id) {
                $parent_groups = FTT_Family_Groups::get_user_groups($parent_id);
                if (!empty($parent_groups)) {
                    $primary_group_id = $parent_groups[0]->id;
                    update_user_meta($parent_id, 'ftt_primary_group', $primary_group_id);
                }
            }
            
            if (!$primary_group_id) {
                $report['errors'][] = "Parent $parent_id has children but no group";
                $report['skipped'] += count($children_ids);
                continue;
            }
            
            foreach ($children_ids as $child_id) {
                // Check if child user exists
                $child_user = get_userdata($child_id);
                if (!$child_user) {
                    $report['errors'][] = "Child user $child_id does not exist";
                    $report['skipped']++;
                    continue;
                }
                
                // Check if child is already in this group
                if (FTT_Family_Groups::is_member($primary_group_id, $child_id)) {
                    $report['skipped']++;
                    continue;
                }
                
                // Add child to group
                try {
                    FTT_Family_Groups::add_member($primary_group_id, $child_id, 'child');
                    $report['synced']++;
                    $report['details'][] = sprintf(
                        'Added %s to group %d (parent: %s)',
                        $child_user->display_name,
                        $primary_group_id,
                        get_userdata($parent_id)->display_name
                    );
                } catch (Exception $e) {
                    $report['errors'][] = sprintf(
                        'Failed to add child %d to group %d: %s',
                        $child_id,
                        $primary_group_id,
                        $e->getMessage()
                    );
                }
            }
        }
        
        error_log('FTT: Sync children to groups completed - ' . wp_json_encode($report));
        
        return rest_ensure_response($report);
    }
}

