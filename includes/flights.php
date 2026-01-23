<?php
/**
 * Flight tracking functions for Summer Regiment Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get flights for an event
 */
function srt_get_flights($event_id) {
    global $wpdb;
    $flights_table = $wpdb->prefix . 'srt_flights';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $flights_table WHERE event_id = %d ORDER BY leg_number ASC",
        $event_id
    ));
}

/**
 * Get all flights
 */
function srt_get_all_flights($args = array()) {
    global $wpdb;
    $flights_table = $wpdb->prefix . 'srt_flights';
    
    $defaults = array(
        'is_booked' => null,
        'orderby' => 'departure_time',
        'order' => 'ASC'
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $where = '';
    if ($args['is_booked'] !== null) {
        $where = $wpdb->prepare(' WHERE is_booked = %d', $args['is_booked']);
    }
    
    $query = "SELECT f.*, e.title as event_title, e.event_date 
              FROM $flights_table f 
              LEFT JOIN {$wpdb->prefix}srt_events e ON f.event_id = e.id
              $where
              ORDER BY {$args['orderby']} {$args['order']}";
    
    return $wpdb->get_results($query);
}

/**
 * Create flight
 */
function srt_create_flight($data) {
    global $wpdb;
    $flights_table = $wpdb->prefix . 'srt_flights';
    
    $result = $wpdb->insert(
        $flights_table,
        array(
            'event_id' => intval($data['event_id']),
            'leg_number' => intval($data['leg_number']),
            'departure_airport' => sanitize_text_field($data['departure_airport']),
            'arrival_airport' => sanitize_text_field($data['arrival_airport']),
            'departure_time' => sanitize_text_field($data['departure_time']),
            'arrival_time' => sanitize_text_field($data['arrival_time']),
            'is_booked' => isset($data['is_booked']) ? intval($data['is_booked']) : 0,
            'booking_reference' => sanitize_text_field($data['booking_reference'] ?? ''),
            'notes' => wp_kses_post($data['notes'] ?? '')
        ),
        array('%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
    );
    
    if ($result) {
        return $wpdb->insert_id;
    }
    
    return false;
}

/**
 * Update flight
 */
function srt_update_flight($flight_id, $data) {
    global $wpdb;
    $flights_table = $wpdb->prefix . 'srt_flights';
    
    return $wpdb->update(
        $flights_table,
        array(
            'leg_number' => intval($data['leg_number']),
            'departure_airport' => sanitize_text_field($data['departure_airport']),
            'arrival_airport' => sanitize_text_field($data['arrival_airport']),
            'departure_time' => sanitize_text_field($data['departure_time']),
            'arrival_time' => sanitize_text_field($data['arrival_time']),
            'is_booked' => intval($data['is_booked']),
            'booking_reference' => sanitize_text_field($data['booking_reference'] ?? ''),
            'notes' => wp_kses_post($data['notes'] ?? '')
        ),
        array('id' => $flight_id),
        array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s'),
        array('%d')
    );
}

/**
 * Delete flight
 */
function srt_delete_flight($flight_id) {
    global $wpdb;
    $flights_table = $wpdb->prefix . 'srt_flights';
    
    return $wpdb->delete(
        $flights_table,
        array('id' => $flight_id),
        array('%d')
    );
}

/**
 * Delete flights by event ID
 */
function srt_delete_flights_by_event($event_id) {
    global $wpdb;
    $flights_table = $wpdb->prefix . 'srt_flights';
    
    return $wpdb->delete(
        $flights_table,
        array('event_id' => $event_id),
        array('%d')
    );
}

/**
 * Get unbooked flights count
 */
function srt_get_unbooked_flights_count() {
    global $wpdb;
    $flights_table = $wpdb->prefix . 'srt_flights';
    
    return $wpdb->get_var(
        "SELECT COUNT(*) FROM $flights_table WHERE is_booked = 0"
    );
}

/**
 * Get flights needing booking
 */
function srt_get_flights_needing_booking() {
    global $wpdb;
    $flights_table = $wpdb->prefix . 'srt_flights';
    
    return $wpdb->get_results(
        "SELECT f.*, e.title as event_title, e.event_date 
         FROM $flights_table f 
         LEFT JOIN {$wpdb->prefix}srt_events e ON f.event_id = e.id
         WHERE f.is_booked = 0 
         ORDER BY f.departure_time ASC"
    );
}
