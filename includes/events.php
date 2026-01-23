<?php
/**
 * Event management functions for Summer Regiment Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get all events
 */
function srt_get_events($args = array()) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'srt_events';
    
    $defaults = array(
        'orderby' => 'event_date',
        'order' => 'ASC',
        'limit' => -1
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $query = "SELECT * FROM $events_table ORDER BY {$args['orderby']} {$args['order']}";
    
    if ($args['limit'] > 0) {
        $query .= " LIMIT {$args['limit']}";
    }
    
    return $wpdb->get_results($query);
}

/**
 * Get single event by ID
 */
function srt_get_event($event_id) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'srt_events';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $events_table WHERE id = %d",
        $event_id
    ));
}

/**
 * Create new event
 */
function srt_create_event($data) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'srt_events';
    
    $result = $wpdb->insert(
        $events_table,
        array(
            'title' => sanitize_text_field($data['title']),
            'event_date' => sanitize_text_field($data['event_date']),
            'description' => wp_kses_post($data['description']),
            'location' => sanitize_text_field($data['location'])
        ),
        array('%s', '%s', '%s', '%s')
    );
    
    if ($result) {
        return $wpdb->insert_id;
    }
    
    return false;
}

/**
 * Update event
 */
function srt_update_event($event_id, $data) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'srt_events';
    
    return $wpdb->update(
        $events_table,
        array(
            'title' => sanitize_text_field($data['title']),
            'event_date' => sanitize_text_field($data['event_date']),
            'description' => wp_kses_post($data['description']),
            'location' => sanitize_text_field($data['location'])
        ),
        array('id' => $event_id),
        array('%s', '%s', '%s', '%s'),
        array('%d')
    );
}

/**
 * Delete event and all associated data
 */
function srt_delete_event($event_id) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'srt_events';
    
    // Delete associated time blocks
    srt_delete_time_blocks_by_event($event_id);
    
    // Delete associated flights
    srt_delete_flights_by_event($event_id);
    
    // Delete event
    return $wpdb->delete(
        $events_table,
        array('id' => $event_id),
        array('%d')
    );
}

/**
 * Get time blocks for an event
 */
function srt_get_time_blocks($event_id) {
    global $wpdb;
    $time_blocks_table = $wpdb->prefix . 'srt_time_blocks';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $time_blocks_table WHERE event_id = %d ORDER BY start_time ASC",
        $event_id
    ));
}

/**
 * Create time block
 */
function srt_create_time_block($data) {
    global $wpdb;
    $time_blocks_table = $wpdb->prefix . 'srt_time_blocks';
    
    $result = $wpdb->insert(
        $time_blocks_table,
        array(
            'event_id' => intval($data['event_id']),
            'block_type' => sanitize_text_field($data['block_type']),
            'start_time' => sanitize_text_field($data['start_time']),
            'end_time' => sanitize_text_field($data['end_time']),
            'notes' => wp_kses_post($data['notes'])
        ),
        array('%d', '%s', '%s', '%s', '%s')
    );
    
    if ($result) {
        return $wpdb->insert_id;
    }
    
    return false;
}

/**
 * Delete time blocks by event ID
 */
function srt_delete_time_blocks_by_event($event_id) {
    global $wpdb;
    $time_blocks_table = $wpdb->prefix . 'srt_time_blocks';
    
    return $wpdb->delete(
        $time_blocks_table,
        array('event_id' => $event_id),
        array('%d')
    );
}

/**
 * Delete single time block
 */
function srt_delete_time_block($block_id) {
    global $wpdb;
    $time_blocks_table = $wpdb->prefix . 'srt_time_blocks';
    
    return $wpdb->delete(
        $time_blocks_table,
        array('id' => $block_id),
        array('%d')
    );
}

/**
 * Get events for calendar month
 */
function srt_get_events_for_month($year, $month) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'srt_events';
    
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $events_table 
         WHERE event_date >= %s AND event_date <= %s 
         ORDER BY event_date ASC",
        $start_date,
        $end_date
    ));
}
