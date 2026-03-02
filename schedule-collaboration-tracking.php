<?php
/**
 * Plugin Name: Family Travel Tracker
 * Plugin URI: https://github.com/jimmarks/schedule-collaboration-tracking
 * Description: Multi-child schedule coordination with travel planning, flight tracking, and shared calendars for families. Perfect for busy parents, co-parenting families, and children's activities.
 * Version: 2.0.56
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
define('FTT_VERSION', '2.0.18');
define('FTT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FTT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FTT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Initialize Plugin Update Checker
require_once FTT_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$fttUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/jimmarks/schedule-collaboration-tracking/',
    __FILE__,
    'schedule-collaboration-tracking'
);

// Use release assets from GitHub releases
$fttUpdateChecker->getVcsApi()->enableReleaseAssets();

// Set the branch to check (defaults to 'main')
$fttUpdateChecker->setBranch('main');

/**
 * Main plugin class
 */
class Family_Travel_Tracker {
    
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
        require_once FTT_PLUGIN_DIR . 'includes/cpt.php';
        require_once FTT_PLUGIN_DIR . 'includes/meta.php';
        require_once FTT_PLUGIN_DIR . 'includes/rest.php';
        require_once FTT_PLUGIN_DIR . 'includes/settings.php';
        require_once FTT_PLUGIN_DIR . 'includes/shortcodes.php';
        require_once FTT_PLUGIN_DIR . 'includes/pages.php';
        require_once FTT_PLUGIN_DIR . 'includes/ical.php';
        require_once FTT_PLUGIN_DIR . 'includes/menu.php';
        require_once FTT_PLUGIN_DIR . 'includes/roles.php';
        require_once FTT_PLUGIN_DIR . 'includes/registration.php';
        require_once FTT_PLUGIN_DIR . 'includes/invitations.php';
        require_once FTT_PLUGIN_DIR . 'includes/price-tracking.php';
        require_once FTT_PLUGIN_DIR . 'includes/cron-setup.php';
        require_once FTT_PLUGIN_DIR . 'includes/flight-linking.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-child-colors.php';
        require_once FTT_PLUGIN_DIR . 'includes/cors.php';
        // Domain routing removed - single domain setup (www.familytraveltracker.app only)
        // require_once FTT_PLUGIN_DIR . 'includes/domain-routing.php';
        
        // Stripe Settings (always load - needed to configure API keys)
        require_once FTT_PLUGIN_DIR . 'includes/stripe/class-stripe-settings.php';
        
        // Stripe Integration & Billing (only if Stripe library is available)
        if (file_exists(FTT_PLUGIN_DIR . 'lib/stripe-php/init.php')) {
            require_once FTT_PLUGIN_DIR . 'includes/stripe/class-stripe-integration.php';
            require_once FTT_PLUGIN_DIR . 'includes/stripe/class-stripe-webhooks.php';
            require_once FTT_PLUGIN_DIR . 'includes/billing/class-billing-manager.php';
        }
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
        add_action('admin_notices', array('FTT_Pages', 'get_missing_pages_notice'));
        add_action('admin_init', array('FTT_Pages', 'handle_recreate_pages'));
        add_action('admin_init', array($this, 'run_migrations'));
        
        // Initialize all component classes
        FTT_CPT::init();
        FTT_Meta::init();
        FTT_REST::init();
        FTT_Settings::init();
        FTT_Shortcodes::init();
        FTT_iCal::init();
        FTT_Menu::init();
        FTT_Roles::init();
        FTT_Registration::init();
        FTT_Invitations::init();
        FTT_Price_Tracking::init();
        FTT_Cron_Setup::init();
        FTT_Flight_Linking::init();
        FTT_CORS::init();
        // FTT_Domain_Routing::init(); // Disabled - single domain setup
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Register CPT
        FTT_CPT::register_post_type();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set default options
        $defaults = array(
            'require_login' => false,
            'default_timezone' => wp_timezone_string(),
            'event_types' => FTT_Settings::get_default_event_types(),
        );
        
        if (!get_option('ftt_settings')) {
            add_option('ftt_settings', $defaults);
        }
        
        // Create plugin pages
        FTT_Pages::create_pages();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Run database migrations for version updates
     */
    public function run_migrations() {
        // Get current migration version
        $migration_version = get_option('ftt_migration_version', '0');
        
        // Migration 1: Add user_type to existing parent accounts (v2.0.48+)
        if (version_compare($migration_version, '1', '<')) {
            $this->migrate_user_types();
            update_option('ftt_migration_version', '1');
        }
    }
    
    /**
     * Migration: Set user_type for existing parent accounts
     * Fixes accounts created before v2.0.48
     */
    private function migrate_user_types() {
        global $wpdb;
        
        // Find users who have children but no user_type set
        $users_with_children = $wpdb->get_results("
            SELECT DISTINCT user_id 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'ftt_children' 
            AND meta_value != ''
        ");
        
        $migrated = 0;
        foreach ($users_with_children as $row) {
            $user_id = $row->user_id;
            
            // Check if user_type already exists
            $existing_type = get_user_meta($user_id, 'user_type', true);
            if (empty($existing_type)) {
                // Set as parent
                update_user_meta($user_id, 'user_type', 'parent');
                $migrated++;
            }
        }
        
        // Also check users who have planned_children (from registration) but no user_type
        $users_with_planned = $wpdb->get_results("
            SELECT DISTINCT user_id 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'planned_children' 
            AND meta_value > 0
        ");
        
        foreach ($users_with_planned as $row) {
            $user_id = $row->user_id;
            
            // Check if user_type already exists
            $existing_type = get_user_meta($user_id, 'user_type', true);
            if (empty($existing_type)) {
                // Set as parent
                update_user_meta($user_id, 'user_type', 'parent');
                $migrated++;
            }
        }
        
        if ($migrated > 0) {
            error_log("FTT Migration: Set user_type='parent' for {$migrated} existing users");
        }
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
        if (!FTT_Pages::pages_exist()) {
            FTT_Pages::create_pages();
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
        if (get_option('ftt_member_id_migration_done')) {
            return;
        }
        
        // Get all events without member_id
        $events = get_posts(array(
            'post_type' => 'ftt_event',
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
        update_option('ftt_member_id_migration_done', true);
    }
    
    /**
     * Upgrade member capabilities to include full post editing
     * Added in v1.0.8 to allow members to add/edit events
     */
    private function upgrade_member_capabilities() {
        // Check if upgrade already done
        if (get_option('ftt_member_caps_upgraded_v1_0_8')) {
            return;
        }
        
        // Get all members
        $members = get_users(array(
            'meta_key' => 'ftt_is_member',
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
        update_option('ftt_member_caps_upgraded_v1_0_8', true);
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('schedule-collaboration-tracking', false, dirname(FTT_PLUGIN_BASENAME) . '/languages');
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
        $shortcodes = array('ftt_calendar', 'ftt_event_form', 'ftt_dashboard', 'ftt_event_list');
        
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
            'ftt-styles',
            FTT_PLUGIN_URL . 'assets/css/styles.css',
            array(),
            FTT_VERSION
        );
        
        // Enqueue Astra theme color overrides
        wp_enqueue_style(
            'ftt-astra-colors',
            FTT_PLUGIN_URL . 'assets/css/ftt-astra-colors.css',
            array('ftt-styles'),
            FTT_VERSION
        );
        
        // Add dynamic event type colors
        $settings = get_option('ftt_settings', array());
        $event_types = $settings['event_types'] ?? array();
        
        if (!empty($event_types)) {
            $custom_css = '';
            foreach ($event_types as $key => $type) {
                $color = $type['color'] ?? '#2196F3';
                $custom_css .= ".ftt-event-type-{$key} { background-color: {$color}; border-color: {$color}; }\n";
                $custom_css .= ".ftt-legend-color.ftt-event-type-{$key} { background-color: {$color}; }\n";
            }
            wp_add_inline_style('ftt-styles', $custom_css);
        }
        
        // Enqueue FullCalendar from CDN
        wp_enqueue_style(
            'ftt-fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css',
            array(),
            '6.1.10'
        );
        
        wp_enqueue_script(
            'ftt-fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js',
            array(),
            '6.1.10',
            true
        );
        
        // Enqueue main script
        wp_enqueue_script(
            'ftt-main',
            FTT_PLUGIN_URL . 'assets/js/main.js',
            array('jquery', 'ftt-fullcalendar'),
            FTT_VERSION,
            true
        );
        
        // Localize script
        $settings = get_option('ftt_settings', array());
        wp_localize_script('ftt-main', 'fttData', array(
            'pluginUrl' => FTT_PLUGIN_URL,
            'restUrl' => rest_url('ftt/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'isAdmin' => current_user_can('edit_posts'),
            'timezone' => wp_timezone_string(),
            'eventFormUrl' => FTT_Pages::get_page_url('event_form'),
            'geocodingProvider' => $settings['geocoding_provider'] ?? 'none',
            'mapboxApiKey' => $settings['mapbox_api_key'] ?? '',
            'googlePlacesApiKey' => $settings['google_places_api_key'] ?? '',
        ));
    }
}

/**
 * Initialize the plugin
 */
function ftt_init() {
    return Family_Travel_Tracker::get_instance();
}

// Start the plugin
ftt_init();
