<?php
/**
 * Plugin Name: Summer Regiment Tracker
 * Plugin URI: https://github.com/jimmarks/phantom-regiment-tracker
 * Description: Manage summer drum corps schedule with front-end calendar, event editor, and flight tracking. No wp-admin required.
 * Version: 1.0.0
 * Author: Jim Marks
 * License: GPL v2 or later
 * Text Domain: summer-regiment-tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SRT_VERSION', '1.0.0');
define('SRT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SRT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once SRT_PLUGIN_DIR . 'includes/database.php';
require_once SRT_PLUGIN_DIR . 'includes/events.php';
require_once SRT_PLUGIN_DIR . 'includes/flights.php';
require_once SRT_PLUGIN_DIR . 'includes/shortcodes.php';
require_once SRT_PLUGIN_DIR . 'includes/ajax-handlers.php';

/**
 * Activation hook - create database tables
 */
function srt_activate() {
    srt_create_tables();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'srt_activate');

/**
 * Deactivation hook
 */
function srt_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'srt_deactivate');

/**
 * Enqueue scripts and styles
 */
function srt_enqueue_assets() {
    // CSS
    wp_enqueue_style('srt-styles', SRT_PLUGIN_URL . 'assets/css/styles.css', array(), SRT_VERSION);
    
    // JavaScript
    wp_enqueue_script('srt-scripts', SRT_PLUGIN_URL . 'assets/js/scripts.js', array('jquery'), SRT_VERSION, true);
    
    // Localize script for AJAX
    wp_localize_script('srt-scripts', 'srtAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('srt-nonce')
    ));
}
add_action('wp_enqueue_scripts', 'srt_enqueue_assets');

/**
 * Initialize plugin
 */
function srt_init() {
    // Register shortcodes
    srt_register_shortcodes();
}
add_action('init', 'srt_init');
