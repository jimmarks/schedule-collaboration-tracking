<?php
/**
 * iCalendar Feed Generation
 *
 * @package Summer_Regiment_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for generating iCalendar feeds
 */
class SRT_ICal {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_action('template_redirect', array(__CLASS__, 'handle_calendar_request'));
    }
    
    /**
     * Handle direct calendar feed requests
     */
    public static function handle_calendar_request() {
        // Check if this is a calendar feed request
        if (!isset($_GET['srt_calendar']) || $_GET['srt_calendar'] != '1') {
            return;
        }
        
        // Get token and user ID from URL
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        // Validate token
        if (empty($token) || empty($user_id)) {
            wp_die('Invalid calendar subscription link. Please generate a new link from the calendar page.', 'Authentication Required', array('response' => 401));
        }
        
        // Check if user exists and token matches
        $stored_token = get_user_meta($user_id, 'srt_calendar_token', true);
        
        if ($token !== $stored_token) {
            wp_die('Invalid or expired calendar token. Please generate a new subscription link from the calendar page.', 'Invalid Token', array('response' => 403));
        }
        
        // Valid token - generate calendar feed
        // Get user to filter events
        $user = get_userdata($user_id);
        if (!$user) {
            wp_die('Invalid user', 'User Not Found', array('response' => 404));
        }
        
        // Get events for this user
        $args = array(
            'post_type' => 'srt_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => 'start_datetime',
            'order' => 'ASC',
        );
        
        // Filter events by user (unless admin)
        if (!user_can($user_id, 'manage_options')) {
            // Check if user is a parent
            $child_ids = get_user_meta($user_id, 'srt_children', true);
            
            if (!empty($child_ids) && is_array($child_ids)) {
                // Parent - show their children's events
                $member_ids = $child_ids;
                $member_ids[] = $user_id; // Include parent's own events
                
                $args['meta_query'] = array(
                    array(
                        'key'     => 'member_id',
                        'value'   => $member_ids,
                        'compare' => 'IN',
                    ),
                );
            } else {
                // Member - show only their events
                $args['meta_query'] = array(
                    array(
                        'key'     => 'member_id',
                        'value'   => $user_id,
                        'compare' => '=',
                    ),
                );
            }
        }
        // Admins get all events (no filter)
        
        $events = get_posts($args);
        
        // Generate iCal content
        $ical = self::generate_ical($events);
        
        // Set headers for .ics file
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="schedule.ics"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo $ical;
        exit;
    }
    
    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Public calendar feed
        register_rest_route('srt/v1', '/calendar.ics', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_calendar_feed'),
            'permission_callback' => array(__CLASS__, 'check_calendar_permission'),
        ));
        
        // Generate new calendar token (admin only)
        register_rest_route('srt/v1', '/calendar/token', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'generate_calendar_token'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));
    }
    
    /**
     * Check calendar permission
     */
    public static function check_calendar_permission($request) {
        $settings = get_option('srt_settings', array());
        
        // Check if calendar feed is enabled
        if (empty($settings['enable_ical_feed'])) {
            return new WP_Error('calendar_disabled', 'Calendar feed is disabled', array('status' => 403));
        }
        
        // Check if authentication is required
        if (!empty($settings['ical_require_auth'])) {
            $token = $request->get_param('token');
            
            if (empty($token)) {
                return new WP_Error('auth_required', 'Authentication token required', array('status' => 401));
            }
            
            $valid_tokens = get_option('srt_calendar_tokens', array());
            
            if (!in_array($token, $valid_tokens)) {
                return new WP_Error('invalid_token', 'Invalid authentication token', array('status' => 403));
            }
        }
        
        return true;
    }
    
    /**
     * Get calendar feed
     */
    public static function get_calendar_feed($request) {
        // Get all published events
        $args = array(
            'post_type' => 'srt_event',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => 'start_datetime',
            'order' => 'ASC',
        );
        
        $events = get_posts($args);
        
        // Generate iCal content
        $ical = self::generate_ical($events);
        
        // Set headers for .ics file
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="schedule.ics"');
        
        echo $ical;
        exit;
    }
    
    /**
     * Generate iCalendar content
     */
    public static function generate_ical($events) {
        $settings = get_option('srt_settings', array());
        $timezone = $settings['default_timezone'] ?? wp_timezone_string();
        $site_name = get_bloginfo('name');
        
        // Start iCal file
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//" . self::escape_string($site_name) . "//Schedule Tracker//EN\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "X-WR-CALNAME:" . self::escape_string($site_name . " - Schedule") . "\r\n";
        $ical .= "X-WR-TIMEZONE:" . $timezone . "\r\n";
        $ical .= "X-WR-CALDESC:" . self::escape_string("Official schedule for " . $site_name) . "\r\n";
        
        // Add timezone component
        $ical .= self::generate_vtimezone($timezone);
        
        // Add events
        foreach ($events as $event) {
            $ical .= self::generate_vevent($event, $timezone);
        }
        
        // End iCal file
        $ical .= "END:VCALENDAR\r\n";
        
        return $ical;
    }
    
    /**
     * Generate VTIMEZONE component
     */
    private static function generate_vtimezone($timezone_name) {
        // For simplicity, we'll use UTC offset. 
        // Full VTIMEZONE with DST rules would be more complex
        $tz = new DateTimeZone($timezone_name);
        $now = new DateTime('now', $tz);
        $offset = $tz->getOffset($now);
        
        $hours = floor($offset / 3600);
        $minutes = abs(($offset % 3600) / 60);
        $offset_string = sprintf('%+03d%02d', $hours, $minutes);
        
        return "BEGIN:VTIMEZONE\r\n" .
               "TZID:" . $timezone_name . "\r\n" .
               "BEGIN:STANDARD\r\n" .
               "DTSTART:19700101T000000\r\n" .
               "TZOFFSETFROM:" . $offset_string . "\r\n" .
               "TZOFFSETTO:" . $offset_string . "\r\n" .
               "END:STANDARD\r\n" .
               "END:VTIMEZONE\r\n";
    }
    
    /**
     * Generate VEVENT component
     */
    private static function generate_vevent($event, $timezone) {
        $post_id = $event->ID;
        
        // Get event metadata
        $start_datetime = get_post_meta($post_id, 'start_datetime', true);
        $end_datetime = get_post_meta($post_id, 'end_datetime', true);
        $location_name = get_post_meta($post_id, 'location_name', true);
        $location_address = get_post_meta($post_id, 'location_address', true);
        $event_type = get_post_meta($post_id, 'event_type', true);
        $time_blocks = json_decode(get_post_meta($post_id, 'time_blocks', true), true);
        $travel_needed = get_post_meta($post_id, 'travel_needed', true);
        $travel_legs = json_decode(get_post_meta($post_id, 'travel_legs', true), true);
        $notes = get_post_meta($post_id, 'notes', true);
        
        // Build location string (name + address)
        $location = '';
        if (!empty($location_name)) {
            $location = $location_name;
            if (!empty($location_address)) {
                $location .= ', ' . $location_address;
            }
        } elseif (!empty($location_address)) {
            $location = $location_address;
        }
        
        // If we have flight data, use first leg's destination as location
        if (!empty($travel_legs) && is_array($travel_legs)) {
            foreach ($travel_legs as $leg) {
                if (!empty($leg['mode']) && $leg['mode'] === 'fly') {
                    if (!empty($leg['arrive_airport'])) {
                        $location = $leg['arrive_airport'];
                        break;
                    }
                }
            }
        }
        
        // Format dates to iCal format (YYYYMMDDTHHMMSS)
        $dtstart = self::format_datetime($start_datetime, $timezone);
        $dtend = self::format_datetime($end_datetime, $timezone);
        $dtstamp = gmdate('Ymd\THis\Z');
        
        // Create unique ID
        $uid = $post_id . '@' . parse_url(home_url(), PHP_URL_HOST);
        
        // Get event type label
        $event_types = SRT_CPT::get_event_types();
        $type_label = $event_types[$event_type] ?? 'Event';
        
        // Build description
        $description = '';
        
        if (!empty($notes)) {
            $description .= $notes . "\n\n";
        }
        
        // Add flight details at the top of description
        if (!empty($travel_legs) && is_array($travel_legs)) {
            foreach ($travel_legs as $index => $leg) {
                if (!empty($leg['mode']) && $leg['mode'] === 'fly') {
                    $description .= "FLIGHT #" . ($index + 1) . ":\n";
                    
                    // Route
                    if (!empty($leg['depart_airport']) && !empty($leg['arrive_airport'])) {
                        $description .= sprintf("Route: %s → %s\n", 
                            strtoupper($leg['depart_airport']), 
                            strtoupper($leg['arrive_airport'])
                        );
                    }
                    
                    // Date/Time
                    if (!empty($leg['depart_date'])) {
                        $description .= "Date: " . $leg['depart_date'];
                        if (!empty($leg['depart_time'])) {
                            $description .= " at " . $leg['depart_time'];
                        }
                        $description .= "\n";
                    }
                    
                    // Flight details if booked
                    if (!empty($leg['booked'])) {
                        if (!empty($leg['airline'])) {
                            $description .= "Airline: " . $leg['airline'] . "\n";
                        }
                        if (!empty($leg['flight_number'])) {
                            $description .= "Flight: " . $leg['flight_number'] . "\n";
                        }
                        if (!empty($leg['confirmation'])) {
                            $description .= "Confirmation: " . $leg['confirmation'] . "\n";
                        }
                    }
                    
                    // Baggage
                    if (!empty($leg['baggage']) && is_array($leg['baggage'])) {
                        $description .= "Baggage: " . implode(', ', $leg['baggage']) . "\n";
                    }
                    
                    $description .= "\n";
                }
            }
        }
        
        // Add time blocks
        if (!empty($time_blocks) && is_array($time_blocks)) {
            $description .= "Schedule:\n";
            foreach ($time_blocks as $block) {
                if (!empty($block['start_time']) && !empty($block['end_time'])) {
                    $description .= sprintf(
                        "- %s: %s - %s",
                        $block['type'] ?? 'Activity',
                        $block['start_time'],
                        $block['end_time']
                    );
                    if (!empty($block['description'])) {
                        $description .= ' (' . $block['description'] . ')';
                    }
                    $description .= "\n";
                }
            }
            $description .= "\n";
        }
        
        // Add travel info
        if ($travel_needed) {
            $description .= "Travel Required\n\n";
        }
        
        // Add view link
        $description .= "View full details: " . get_permalink($post_id);
        
        // Build VEVENT
        $vevent = "BEGIN:VEVENT\r\n";
        $vevent .= "UID:" . $uid . "\r\n";
        $vevent .= "DTSTAMP:" . $dtstamp . "\r\n";
        $vevent .= "DTSTART;TZID=" . $timezone . ":" . $dtstart . "\r\n";
        $vevent .= "DTEND;TZID=" . $timezone . ":" . $dtend . "\r\n";
        $vevent .= "SUMMARY:" . self::escape_string($event->post_title) . "\r\n";
        
        if (!empty($location)) {
            $vevent .= "LOCATION:" . self::escape_string($location) . "\r\n";
        }
        
        if (!empty($description)) {
            $vevent .= "DESCRIPTION:" . self::escape_string($description) . "\r\n";
        }
        
        $vevent .= "CATEGORIES:" . self::escape_string($type_label) . "\r\n";
        $vevent .= "STATUS:CONFIRMED\r\n";
        $vevent .= "SEQUENCE:0\r\n";
        $vevent .= "END:VEVENT\r\n";
        
        return $vevent;
    }
    
    /**
     * Format datetime for iCal
     */
    private static function format_datetime($datetime, $timezone) {
        if (empty($datetime)) {
            return gmdate('Ymd\THis');
        }
        
        $dt = new DateTime($datetime, new DateTimeZone($timezone));
        return $dt->format('Ymd\THis');
    }
    
    /**
     * Escape string for iCal
     */
    private static function escape_string($string) {
        // Remove any existing line breaks
        $string = str_replace(array("\r\n", "\n", "\r"), ' ', $string);
        
        // Escape special characters
        $string = str_replace(array('\\', ',', ';'), array('\\\\', '\\,', '\\;'), $string);
        
        // Fold long lines (max 75 characters per RFC 5545)
        return self::fold_line($string);
    }
    
    /**
     * Fold long lines for iCal format
     */
    private static function fold_line($string, $max_length = 75) {
        $lines = array();
        $current_line = '';
        $words = explode(' ', $string);
        
        foreach ($words as $word) {
            if (strlen($current_line . ' ' . $word) > $max_length) {
                if (!empty($current_line)) {
                    $lines[] = $current_line;
                    $current_line = ' ' . $word; // Continuation lines start with space
                } else {
                    $lines[] = $word;
                }
            } else {
                $current_line .= (empty($current_line) ? '' : ' ') . $word;
            }
        }
        
        if (!empty($current_line)) {
            $lines[] = $current_line;
        }
        
        return implode("\r\n", $lines);
    }
    
    /**
     * Generate calendar token
     */
    public static function generate_calendar_token($request) {
        $token = wp_generate_password(32, false);
        
        $tokens = get_option('srt_calendar_tokens', array());
        $tokens[] = $token;
        
        update_option('srt_calendar_tokens', $tokens);
        
        return rest_ensure_response(array(
            'success' => true,
            'token' => $token,
            'url' => rest_url('srt/v1/calendar.ics') . '?token=' . $token,
        ));
    }
    
    /**
     * Delete calendar token
     */
    public static function delete_calendar_token($token) {
        $tokens = get_option('srt_calendar_tokens', array());
        $tokens = array_filter($tokens, function($t) use ($token) {
            return $t !== $token;
        });
        
        update_option('srt_calendar_tokens', array_values($tokens));
        
        return true;
    }
    
    /**
     * Get all calendar tokens
     */
    public static function get_calendar_tokens() {
        return get_option('srt_calendar_tokens', array());
    }
}

// Initialize
SRT_ICal::init();
