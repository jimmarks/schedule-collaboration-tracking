<?php
/**
 * Database functions for Summer Regiment Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create database tables on plugin activation
 */
function srt_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Events table
    $events_table = $wpdb->prefix . 'srt_events';
    $events_sql = "CREATE TABLE IF NOT EXISTS $events_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        event_date date NOT NULL,
        description text,
        location varchar(255),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY event_date (event_date)
    ) $charset_collate;";

    // Time blocks table - supports multiple time blocks per event/day
    $time_blocks_table = $wpdb->prefix . 'srt_time_blocks';
    $time_blocks_sql = "CREATE TABLE IF NOT EXISTS $time_blocks_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        event_id bigint(20) NOT NULL,
        block_type varchar(50) NOT NULL,
        start_time time NOT NULL,
        end_time time NOT NULL,
        notes text,
        PRIMARY KEY (id),
        KEY event_id (event_id),
        KEY block_type (block_type)
    ) $charset_collate;";

    // Flights table - supports multi-leg flights
    $flights_table = $wpdb->prefix . 'srt_flights';
    $flights_sql = "CREATE TABLE IF NOT EXISTS $flights_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        event_id bigint(20) NOT NULL,
        leg_number int(11) NOT NULL DEFAULT 1,
        departure_airport varchar(10) NOT NULL,
        arrival_airport varchar(10) NOT NULL,
        departure_time datetime NOT NULL,
        arrival_time datetime NOT NULL,
        is_booked tinyint(1) DEFAULT 0,
        booking_reference varchar(100),
        notes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY event_id (event_id),
        KEY is_booked (is_booked)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($events_sql);
    dbDelta($time_blocks_sql);
    dbDelta($flights_sql);
}
