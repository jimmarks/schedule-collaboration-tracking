<?php
/**
 * Plugin Name: Family Travel Tracker
 * Plugin URI: https://github.com/jimmarks/schedule-collaboration-tracking
 * Description: Multi-child schedule coordination with travel planning, flight tracking, and shared calendars for families. Perfect for busy parents, co-parenting families, and children's activities.
 * Version: 1.0.23
 * Author: Jim Marks
 * Author URI: https://github.com/jimmarks
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: schedule-collaboration-tracking
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SRT_VERSION', '1.0.23');
define('SRT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SRT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SRT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Initialize Plugin Update Checker
require_once SRT_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$srtUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/jimmarks/schedule-collaboration-tracking/',
    __FILE__,
    'schedule-collaboration-tracking'
);

// Use release assets from GitHub releases
$srtUpdateChecker->getVcsApi()->enableReleaseAssets();

// Set the branch to check (defaults to 'main')
$srtUpdateChecker->setBranch('main');

/**
 * Main plugin class
 */
class Summer_Regiment_Tracker {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once SRT_PLUGIN_DIR . 'includes/cpt.php';
        require_once SRT_PLUGIN_DIR . 'includes/meta.php';
        require_once SRT_PLUGIN_DIR . 'includes/rest.php';
        require_once SRT_PLUGIN_DIR . 'includes/settings.php';
        require_once SRT_PLUGIN_DIR . 'includes/shortcodes.php';
        require_once SRT_PLUGIN_DIR . 'includes/pages.php';
        require_once SRT_PLUGIN_DIR . 'includes/ical.php';
        require_once SRT_PLUGIN_DIR . 'includes/menu.php';
        require_once SRT_PLUGIN_DIR . 'includes/roles.php';
        require_once SRT_PLUGIN_DIR . 'includes/registration.php';
        require_once SRT_PLUGIN_DIR . 'includes/invitations.php';
        require_once SRT_PLUGIN_DIR . 'includes/price-tracking.php';
        require_once SRT_PLUGIN_DIR . 'includes/cron-setup.php';
        require_once SRT_PLUGIN_DIR . 'includes/flight-linking.php';
        require_once SRT_PLUGIN_DIR . 'includes/class-child-colors.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('plugins_loaded', array($this, 'check_pages'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_notices', array('SRT_Pages', 'get_missing_pages_notice'));
        add_action('admin_init', array('SRT_Pages', 'handle_recreate_pages'));
        
        // Initialize all component classes
        SRT_CPT::init();
        SRT_Meta::init();
        SRT_REST::init();
        SRT_Settings::init();
        SRT_Shortcodes::init();
        SRT_iCal::init();
        SRT_Menu::init();
        SRT_Roles::init();
        SRT_Registration::init();
        SRT_Invitations::init();
        SRT_Price_Tracking::init();
        SRT_Cron_Setup::init();
        SRT_Flight_Linking::init();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Register CPT
        SRT_CPT::register_post_type();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set default options
        $defaults = array(
            'require_login' => false,
            'default_home_airport' => '',
            'default_timezone' => wp_timezone_string(),
            'event_types' => SRT_Settings::get_default_event_types(),
        );
        
        if (!get_option('srt_settings')) {
            add_option('srt_settings', $defaults);
        }
        
        // Create plugin pages
        SRT_Pages::create_pages();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Check and create missing pages
     */
    public function check_pages() {
        // Only run for admins to avoid unnecessary checks
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if we need to create any missing pages
        if (!SRT_Pages::pages_exist()) {
            SRT_Pages::create_pages();
            flush_rewrite_rules();
        }
        
        // Migrate existing events to have member_id
        $this->migrate_member_ids();
        
        // Upgrade member capabilities (v1.0.8+)
        $this->upgrade_member_capabilities();
    }
    
    /**
     * Migrate existing events to populate member_id field
     */
    private function migrate_member_ids() {
        // Check if migration already done
        if (get_option('srt_member_id_migration_done')) {
            return;
        }
        
        // Get all events without member_id
        $events = get_posts(array(
            'post_type' => 'srt_event',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => 'member_id',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        ));
        
        foreach ($events as $event) {
            // Set member_id to post author (best guess for existing events)
            update_post_meta($event->ID, 'member_id', $event->post_author);
        }
        
        // Mark migration as done
        update_option('srt_member_id_migration_done', true);
    }
    
    /**
     * Upgrade member capabilities to include full post editing
     * Added in v1.0.8 to allow members to add/edit events
     */
    private function upgrade_member_capabilities() {
        // Check if upgrade already done
        if (get_option('srt_member_caps_upgraded_v1_0_8')) {
            return;
        }
        
        // Get all members
        $members = get_users(array(
            'meta_key' => 'srt_is_member',
            'meta_value' => '1',
        ));
        
        foreach ($members as $member) {
            // Add all necessary capabilities for post editing
            $member->add_cap('read');
            $member->add_cap('edit_posts');
            $member->add_cap('edit_published_posts');
            $member->add_cap('publish_posts');
            $member->add_cap('delete_posts');
            $member->add_cap('delete_published_posts');
            $member->add_cap('upload_files');
        }
        
        // Mark upgrade as done
        update_option('srt_member_caps_upgraded_v1_0_8', true);
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('schedule-collaboration-tracking', false, dirname(SRT_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on pages with our shortcodes
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }
        
        $has_shortcode = false;
        $shortcodes = array('srt_calendar', 'srt_event_form', 'srt_dashboard', 'srt_event_list');
        
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                $has_shortcode = true;
                break;
            }
        }
        
        if (!$has_shortcode) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'srt-styles',
            SRT_PLUGIN_URL . 'assets/css/styles.css',
            array(),
            SRT_VERSION
        );
        
        // Add dynamic event type colors
        $settings = get_option('srt_settings', array());
        $event_types = $settings['event_types'] ?? array();
        
        if (!empty($event_types)) {
            $custom_css = '';
            foreach ($event_types as $key => $type) {
                $color = $type['color'] ?? '#2196F3';
                $custom_css .= ".srt-event-type-{$key} { background-color: {$color}; border-color: {$color}; }\n";
                $custom_css .= ".srt-legend-color.srt-event-type-{$key} { background-color: {$color}; }\n";
            }
            wp_add_inline_style('srt-styles', $custom_css);
        }
        
        // Enqueue FullCalendar from CDN
        wp_enqueue_style(
            'srt-fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
            array(),
            '6.1.10'
        );
        
        wp_enqueue_script(
            'srt-fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
            array(),
            '6.1.10',
            true
        );
        
        // Enqueue main script
        wp_enqueue_script(
            'srt-main',
            SRT_PLUGIN_URL . 'assets/js/main.js',
            array('jquery', 'srt-fullcalendar'),
            SRT_VERSION,
            true
        );
        
        // Localize script
        $settings = get_option('srt_settings', array());
        wp_localize_script('srt-main', 'srtData', array(
            'pluginUrl' => SRT_PLUGIN_URL,
            'restUrl' => rest_url('srt/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'isAdmin' => current_user_can('edit_posts'),
            'timezone' => wp_timezone_string(),
            'eventFormUrl' => SRT_Pages::get_page_url('event_form'),
            'geocodingProvider' => $settings['geocoding_provider'] ?? 'none',
            'mapboxApiKey' => $settings['mapbox_api_key'] ?? '',
            'googlePlacesApiKey' => $settings['google_places_api_key'] ?? '',
        ));
    }
}

/**
 * Initialize the plugin
 */
function srt_init() {
    return Summer_Regiment_Tracker::get_instance();
}

// Start the plugin
srt_init();
