<?php
/**
 * Meta Field Registration and Sanitization
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FTT_Meta {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_meta_fields'));
    }
    
    /**
     * Register meta fields
     */
    public static function register_meta_fields() {
        $meta_fields = array(
            // Core event fields
            'start_datetime' => array(
                'type'              => 'string',
                'description'       => 'Event start date and time (ISO8601)',
                'single'            => true,
                'sanitize_callback' => array(__CLASS__, 'sanitize_datetime'),
                'show_in_rest'      => true,
            ),
            'end_datetime' => array(
                'type'              => 'string',
                'description'       => 'Event end date and time (ISO8601)',
                'single'            => true,
                'sanitize_callback' => array(__CLASS__, 'sanitize_datetime'),
                'show_in_rest'      => true,
            ),
            'timezone' => array(
                'type'              => 'string',
                'description'       => 'Event timezone',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
                'default'           => wp_timezone_string(),
            ),
            'all_day' => array(
                'type'              => 'boolean',
                'description'       => 'All day event',
                'single'            => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'show_in_rest'      => true,
                'default'           => false,
            ),
            'member_id' => array(
                'type'              => 'integer',
                'description'       => 'User ID of the member this event belongs to',
                'single'            => true,
                'sanitize_callback' => 'absint',
                'show_in_rest'      => true,
                'default'           => 0,
            ),
            'event_type' => array(
                'type'              => 'string',
                'description'       => 'Type of event',
                'single'            => true,
                'sanitize_callback' => array(__CLASS__, 'sanitize_event_type'),
                'show_in_rest'      => true,
            ),
            'location_name' => array(
                'type'              => 'string',
                'description'       => 'Location name',
                'single'            => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest'      => true,
            ),
            'location_address' => array(
                'type'              => 'string',
                'description'       => 'Location address',
                'single'            => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'show_in_rest'      => true,
            ),
            'location_latitude' => array(
                'type'              => 'number',
                'description'       => 'Location latitude',
                'single'            => true,
                'sanitize_callback' => 'floatval',
                'show_in_rest'      => true,
            ),
            'location_longitude' => array(
                'type'              => 'number',
                'description'       => 'Location longitude',
                'single'            => true,
                'sanitize_callback' => 'floatval',
                'show_in_rest'      => true,
            ),
            'notes' => array(
                'type'              => 'string',
                'description'       => 'Event notes',
                'single'            => true,
                'sanitize_callback' => 'wp_kses_post',
                'show_in_rest'      => true,
            ),
            
            // Time blocks (JSON array)
            'time_blocks' => array(
                'type'              => 'string',
                'description'       => 'Time blocks (JSON array)',
                'single'            => true,
                'sanitize_callback' => array(__CLASS__, 'sanitize_time_blocks'),
                'show_in_rest'      => true,
            ),
            
            // Travel fields
            'travel_needed' => array(
                'type'              => 'boolean',
                'description'       => 'Travel needed',
                'single'            => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'show_in_rest'      => true,
                'default'           => false,
            ),
            'travel_mode' => array(
                'type'              => 'string',
                'description'       => 'Primary travel mode',
                'single'            => true,
                'sanitize_callback' => array(__CLASS__, 'sanitize_travel_mode'),
                'show_in_rest'      => true,
            ),
            'flight_needed' => array(
                'type'              => 'boolean',
                'description'       => 'Flight needed',
                'single'            => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'show_in_rest'      => true,
                'default'           => false,
            ),
            
            // Travel legs (JSON array)
            'travel_legs' => array(
                'type'              => 'string',
                'description'       => 'Travel legs (JSON array)',
                'single'            => true,
                'sanitize_callback' => array(__CLASS__, 'sanitize_travel_legs'),
                'show_in_rest'      => true,
            ),
        );
        
        foreach ($meta_fields as $key => $args) {
            register_post_meta('ftt_event', $key, $args);
        }
    }
    
    /**
     * Sanitize datetime string
     */
    public static function sanitize_datetime($value) {
        if (empty($value)) {
            return '';
        }
        
        // Validate ISO8601 format
        $datetime = date_create($value);
        if ($datetime === false) {
            return '';
        }
        
        return sanitize_text_field($value);
    }
    
    /**
     * Sanitize event type
     */
    public static function sanitize_event_type($value) {
        $valid_types = array_keys(FTT_CPT::get_event_types());
        
        if (in_array($value, $valid_types, true)) {
            return $value;
        }
        
        return 'other';
    }
    
    /**
     * Sanitize travel mode
     */
    public static function sanitize_travel_mode($value) {
        $valid_modes = array_keys(FTT_CPT::get_travel_modes());
        
        if (in_array($value, $valid_modes, true)) {
            return $value;
        }
        
        return 'other';
    }
    
    /**
     * Sanitize time blocks
     */
    public static function sanitize_time_blocks($value) {
        if (empty($value)) {
            return '[]';
        }
        
        // Decode JSON
        $blocks = json_decode($value, true);
        
        if (!is_array($blocks)) {
            return '[]';
        }
        
        $sanitized = array();
        $valid_block_types = array_keys(FTT_CPT::get_block_types());
        
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            
            $sanitized_block = array(
                'block_type'     => in_array($block['block_type'] ?? '', $valid_block_types) ? $block['block_type'] : 'other',
                'start_datetime' => self::sanitize_datetime($block['start_datetime'] ?? ''),
                'end_datetime'   => self::sanitize_datetime($block['end_datetime'] ?? ''),
                'title'          => sanitize_text_field($block['title'] ?? ''),
                'notes'          => sanitize_textarea_field($block['notes'] ?? ''),
            );
            
            // Validate times
            if (empty($sanitized_block['start_datetime']) || empty($sanitized_block['end_datetime'])) {
                continue;
            }
            
            $start = strtotime($sanitized_block['start_datetime']);
            $end = strtotime($sanitized_block['end_datetime']);
            
            if ($end <= $start) {
                continue;
            }
            
            $sanitized[] = $sanitized_block;
        }
        
        return wp_json_encode($sanitized);
    }
    
    /**
     * Sanitize travel legs
     */
    public static function sanitize_travel_legs($value) {
        if (empty($value)) {
            return '[]';
        }
        
        // Decode JSON
        $legs = json_decode($value, true);
        
        if (!is_array($legs)) {
            return '[]';
        }
        
        $sanitized = array();
        $valid_modes = array_keys(FTT_CPT::get_travel_modes());
        $valid_baggage = array_keys(FTT_CPT::get_baggage_types());
        
        foreach ($legs as $leg) {
            if (!is_array($leg)) {
                continue;
            }
            
            // Sanitize baggage array
            $baggage = array();
            if (isset($leg['baggage']) && is_array($leg['baggage'])) {
                foreach ($leg['baggage'] as $item) {
                    if (in_array($item, $valid_baggage, true)) {
                        $baggage[] = $item;
                    }
                }
            }
            
            $valid_times = array('morning', 'midday', 'evening', 'night');
            
            $sanitized_leg = array(
                'leg_name'            => sanitize_text_field($leg['leg_name'] ?? ''),
                'mode'                => in_array($leg['mode'] ?? '', $valid_modes) ? $leg['mode'] : 'other',
                'depart_location'     => sanitize_text_field($leg['depart_location'] ?? ''),
                'depart_airport'      => strtoupper(sanitize_text_field($leg['depart_airport'] ?? '')),
                'arrive_location'     => sanitize_text_field($leg['arrive_location'] ?? ''),
                'arrive_airport'      => strtoupper(sanitize_text_field($leg['arrive_airport'] ?? '')),
                'depart_date'         => sanitize_text_field($leg['depart_date'] ?? ''),
                'depart_time_of_day'  => in_array($leg['depart_time_of_day'] ?? '', $valid_times) ? $leg['depart_time_of_day'] : '',
                'arrive_date'         => sanitize_text_field($leg['arrive_date'] ?? ''),
                'arrive_time_of_day'  => in_array($leg['arrive_time_of_day'] ?? '', $valid_times) ? $leg['arrive_time_of_day'] : '',
                'airline'             => sanitize_text_field($leg['airline'] ?? ''),
                'flight_number'       => sanitize_text_field($leg['flight_number'] ?? ''),
                'booked'              => rest_sanitize_boolean($leg['booked'] ?? false),
                'confirmation'        => sanitize_text_field($leg['confirmation'] ?? ''),
                'baggage'             => $baggage,
                'pickup_plan'         => sanitize_textarea_field($leg['pickup_plan'] ?? ''),
                'notes'               => sanitize_textarea_field($leg['notes'] ?? ''),
                'flight_group_id'     => sanitize_text_field($leg['flight_group_id'] ?? ''),
            );
            
            // Validate dates
            if (!empty($sanitized_leg['depart_date']) && !empty($sanitized_leg['arrive_date'])) {
                $depart = strtotime($sanitized_leg['depart_date']);
                $arrive = strtotime($sanitized_leg['arrive_date']);
                
                if ($arrive < $depart) {
                    continue;
                }
            }
            
            $sanitized[] = $sanitized_leg;
        }
        
        return wp_json_encode($sanitized);
    }
}

// Initialize
FTT_Meta::init();
