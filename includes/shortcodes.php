<?php
/**
 * Shortcodes
 *
 * @package Summer_Regiment_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class SRT_Shortcodes {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_shortcode('srt_calendar', array(__CLASS__, 'render_calendar'));
        add_shortcode('srt_event_form', array(__CLASS__, 'render_event_form'));
        add_shortcode('srt_dashboard', array(__CLASS__, 'render_dashboard'));
        add_shortcode('srt_event_list', array(__CLASS__, 'render_event_list'));
        add_shortcode('srt_calendar_subscribe', array(__CLASS__, 'render_calendar_subscribe'));
        add_shortcode('srt_login', array(__CLASS__, 'render_login'));
        
        // Add login redirect filters
        add_filter('login_redirect', array(__CLASS__, 'custom_login_redirect'), 10, 3);
        add_filter('authenticate', array(__CLASS__, 'custom_authenticate_redirect'), 30, 3);
    }
    
    /**
     * Render calendar shortcode
     */
    public static function render_calendar($atts) {
        // Check permissions
        $settings = get_option('srt_settings', array());
        $require_login = $settings['require_login'] ?? false;
        
        if ($require_login && !is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view the calendar.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        ob_start();
        include SRT_PLUGIN_DIR . 'templates/calendar.php';
        return ob_get_clean();
    }
    
    /**
     * Render event form shortcode
     */
    public static function render_event_form($atts) {
        // Check permissions - only editors and admins
        if (!current_user_can('edit_posts')) {
            return '<p>' . esc_html__('You do not have permission to add or edit events.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        ob_start();
        include SRT_PLUGIN_DIR . 'templates/event-form.php';
        return ob_get_clean();
    }
    
    /**
     * Render dashboard shortcode
     */
    public static function render_dashboard($atts) {
        // Check permissions
        $settings = get_option('srt_settings', array());
        $require_login = $settings['require_login'] ?? false;
        
        if ($require_login && !is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view the dashboard.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        ob_start();
        include SRT_PLUGIN_DIR . 'templates/dashboard.php';
        return ob_get_clean();
    }
    
    /**
     * Render event list shortcode
     */
    public static function render_event_list($atts) {
        // Check permissions
        $settings = get_option('srt_settings', array());
        $require_login = $settings['require_login'] ?? false;
        
        if ($require_login && !is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view events.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        // Parse attributes
        $atts = shortcode_atts(array(
            'limit' => 10,
            'type'  => '',
        ), $atts);
        
        // Query upcoming events
        $args = array(
            'post_type'      => 'srt_event',
            'posts_per_page' => intval($atts['limit']),
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => 'start_datetime',
                    'value'   => current_time('mysql'),
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ),
            ),
            'orderby'        => 'meta_value',
            'meta_key'       => 'start_datetime',
            'order'          => 'ASC',
        );
        
        // Filter by type if specified
        if (!empty($atts['type'])) {
            $args['meta_query'][] = array(
                'key'     => 'event_type',
                'value'   => sanitize_text_field($atts['type']),
                'compare' => '=',
            );
        }
        
        // Filter by member - show only events for logged-in user unless admin
        if (is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            $is_admin = current_user_can('manage_options');
            
            // Non-admins only see their own events
            if (!$is_admin) {
                // Check if user is a parent
                $child_ids = get_user_meta($current_user_id, 'srt_children', true);
                
                if (!empty($child_ids) && is_array($child_ids)) {
                    // Parent - show their children's events
                    $member_ids = $child_ids;
                    $member_ids[] = $current_user_id; // Include parent's own events
                    
                    $args['meta_query'][] = array(
                        'key'     => 'member_id',
                        'value'   => $member_ids,
                        'compare' => 'IN',
                    );
                } else {
                    // Member - show only their events
                    $args['meta_query'][] = array(
                        'key'     => 'member_id',
                        'value'   => $current_user_id,
                        'compare' => '=',
                    );
                }
            }
            // Admins see all events (no filter applied)
        }
        
        $query = new WP_Query($args);
        
        ob_start();
        include SRT_PLUGIN_DIR . 'templates/event-list.php';
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    /**
     * Render calendar subscribe shortcode
     */
    public static function render_calendar_subscribe($atts) {
        $atts = shortcode_atts(array(
            'token' => '', // Optional pre-filled token
        ), $atts);
        
        $settings = get_option('srt_settings', array());
        $enabled = $settings['enable_ical_feed'] ?? false;
        
        if (!$enabled) {
            return '<p>' . esc_html__('Calendar subscription is currently disabled.', 'schedule-collaboration-tracking') . '</p>';
        }
        
        $requires_auth = $settings['ical_require_auth'] ?? false;
        $feed_url = rest_url('srt/v1/calendar.ics');
        
        if ($requires_auth && !empty($atts['token'])) {
            $feed_url .= '?token=' . urlencode($atts['token']);
        }
        
        ob_start();
        include SRT_PLUGIN_DIR . 'templates/calendar-subscribe.php';
        return ob_get_clean();
    }
    
    /**
     * Render login form shortcode
     */
    public static function render_login($atts) {
        ob_start();
        include SRT_PLUGIN_DIR . 'templates/login-form.php';
        return ob_get_clean();
    }
    
    /**
     * Custom login redirect - send users to dashboard instead of wp-admin
     */
    public static function custom_login_redirect($redirect_to, $request, $user) {
        // Check if user has a valid role
        if (isset($user->roles) && is_array($user->roles)) {
            // If user is an admin or editor, let them go to wp-admin if they want
            if (in_array('administrator', $user->roles) || in_array('editor', $user->roles)) {
                // But if they came from our login page, send them to dashboard
                if (strpos($redirect_to, 'sc-dashboard') !== false) {
                    return home_url('/sc-dashboard/');
                }
                return $redirect_to;
            }
            
            // For all other users (srt_member, srt_parent), always go to dashboard
            return home_url('/sc-dashboard/');
        }
        
        return $redirect_to;
    }
    
    /**
     * Handle failed login redirects
     */
    public static function custom_authenticate_redirect($user, $username, $password) {
        // Check if this is from wp-login.php and has errors
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
            if (is_wp_error($user)) {
                // Get the redirect_to parameter if set
                $redirect_to = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '';
                
                // If redirecting to our dashboard, send them to our login page with error
                if (strpos($redirect_to, 'sc-dashboard') !== false) {
                    remove_filter('authenticate', 'wp_authenticate_username_password', 20);
                    
                    $login_url = home_url('/sc-login/');
                    
                    if ($user->get_error_code() === 'invalid_username' || $user->get_error_code() === 'incorrect_password') {
                        $login_url = add_query_arg('login', 'failed', $login_url);
                    } elseif ($user->get_error_code() === 'empty_username' || $user->get_error_code() === 'empty_password') {
                        $login_url = add_query_arg('login', 'empty', $login_url);
                    }
                    
                    wp_redirect($login_url);
                    exit;
                }
            }
        }
        
        return $user;
    }
}

// Initialize
SRT_Shortcodes::init();
