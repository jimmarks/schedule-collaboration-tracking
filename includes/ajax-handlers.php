<?php
/**
 * AJAX handlers for Summer Regiment Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Save event via AJAX
 */
function srt_ajax_save_event() {
    check_ajax_referer('srt-nonce', 'nonce');
    
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    
    $event_data = array(
        'title' => sanitize_text_field($_POST['title']),
        'event_date' => sanitize_text_field($_POST['event_date']),
        'location' => sanitize_text_field($_POST['location']),
        'description' => wp_kses_post($_POST['description'])
    );
    
    if ($event_id > 0) {
        // Update existing event
        $result = srt_update_event($event_id, $event_data);
        
        // Delete existing time blocks and flights
        srt_delete_time_blocks_by_event($event_id);
        srt_delete_flights_by_event($event_id);
    } else {
        // Create new event
        $event_id = srt_create_event($event_data);
        $result = $event_id !== false;
    }
    
    if ($result !== false) {
        // Save time blocks
        if (isset($_POST['time_blocks']) && is_array($_POST['time_blocks'])) {
            foreach ($_POST['time_blocks'] as $block) {
                if (!empty($block['block_type']) && !empty($block['start_time']) && !empty($block['end_time'])) {
                    srt_create_time_block(array(
                        'event_id' => $event_id,
                        'block_type' => $block['block_type'],
                        'start_time' => $block['start_time'],
                        'end_time' => $block['end_time'],
                        'notes' => $block['notes'] ?? ''
                    ));
                }
            }
        }
        
        // Save flights
        if (isset($_POST['flights']) && is_array($_POST['flights'])) {
            foreach ($_POST['flights'] as $flight) {
                if (!empty($flight['departure_airport']) && !empty($flight['arrival_airport'])) {
                    srt_create_flight(array(
                        'event_id' => $event_id,
                        'leg_number' => $flight['leg_number'] ?? 1,
                        'departure_airport' => $flight['departure_airport'],
                        'arrival_airport' => $flight['arrival_airport'],
                        'departure_time' => $flight['departure_time'],
                        'arrival_time' => $flight['arrival_time'],
                        'is_booked' => isset($flight['is_booked']) ? 1 : 0,
                        'booking_reference' => $flight['booking_reference'] ?? '',
                        'notes' => $flight['notes'] ?? ''
                    ));
                }
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Event saved successfully!',
            'event_id' => $event_id
        ));
    } else {
        wp_send_json_error(array(
            'message' => 'Failed to save event.'
        ));
    }
}
add_action('wp_ajax_srt_save_event', 'srt_ajax_save_event');
add_action('wp_ajax_nopriv_srt_save_event', 'srt_ajax_save_event');

/**
 * Delete event via AJAX
 */
function srt_ajax_delete_event() {
    check_ajax_referer('srt-nonce', 'nonce');
    
    $event_id = intval($_POST['event_id']);
    
    if ($event_id > 0) {
        $result = srt_delete_event($event_id);
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Event deleted successfully!'
            ));
        }
    }
    
    wp_send_json_error(array(
        'message' => 'Failed to delete event.'
    ));
}
add_action('wp_ajax_srt_delete_event', 'srt_ajax_delete_event');
add_action('wp_ajax_nopriv_srt_delete_event', 'srt_ajax_delete_event');

/**
 * Mark flight as booked via AJAX
 */
function srt_ajax_mark_flight_booked() {
    check_ajax_referer('srt-nonce', 'nonce');
    
    $flight_id = intval($_POST['flight_id']);
    
    if ($flight_id > 0) {
        global $wpdb;
        $flights_table = $wpdb->prefix . 'srt_flights';
        
        $result = $wpdb->update(
            $flights_table,
            array('is_booked' => 1),
            array('id' => $flight_id),
            array('%d'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Flight marked as booked!'
            ));
        }
    }
    
    wp_send_json_error(array(
        'message' => 'Failed to update flight.'
    ));
}
add_action('wp_ajax_srt_mark_flight_booked', 'srt_ajax_mark_flight_booked');
add_action('wp_ajax_nopriv_srt_mark_flight_booked', 'srt_ajax_mark_flight_booked');

/**
 * Get events for calendar month via AJAX
 */
function srt_ajax_get_calendar_month() {
    check_ajax_referer('srt-nonce', 'nonce');
    
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    
    $html = srt_calendar_shortcode(array(
        'month' => $month,
        'year' => $year
    ));
    
    wp_send_json_success(array(
        'html' => $html
    ));
}
add_action('wp_ajax_srt_get_calendar_month', 'srt_ajax_get_calendar_month');
add_action('wp_ajax_nopriv_srt_get_calendar_month', 'srt_ajax_get_calendar_month');
