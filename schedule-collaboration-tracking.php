<?php
/**
 * Plugin Name: Family Travel Tracker
 * Plugin URI: https://github.com/jimmarks/schedule-collaboration-tracking
 * Description: Multi-child schedule coordination with travel planning, flight tracking, and shared calendars for families. Perfect for busy parents, co-parenting families, and children's activities.
 * Version: 3.0.26
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
define('FTT_VERSION', '3.0.26');
define('FTT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FTT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FTT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('FTT_PLUGIN_FILE', __FILE__);

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
        require_once FTT_PLUGIN_DIR . 'includes/class-password-encryption.php';
        require_once FTT_PLUGIN_DIR . 'includes/registration.php';
        require_once FTT_PLUGIN_DIR . 'includes/invitations.php';
        require_once FTT_PLUGIN_DIR . 'includes/price-tracking.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-api-tracker.php';
        require_once FTT_PLUGIN_DIR . 'includes/cron-setup.php';
        require_once FTT_PLUGIN_DIR . 'includes/flight-linking.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-child-colors.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-family-groups.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-groups-migration.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-migration-admin.php';
        require_once FTT_PLUGIN_DIR . 'includes/admin-group-management.php';
        require_once FTT_PLUGIN_DIR . 'includes/event-migration.php';
        require_once FTT_PLUGIN_DIR . 'includes/cors.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-seo.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-email-templates.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-cookie-consent.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-exit-survey.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-cookie-scanner.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-user-profile.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-external-calendars.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-newsletter-sync.php';
        require_once FTT_PLUGIN_DIR . 'includes/class-ai-event-parser.php';
        // Domain routing removed - single domain setup (www.familytraveltracker.app only)
        // require_once FTT_PLUGIN_DIR . 'includes/domain-routing.php';
        
        // Admin validation page for REST API security testing
        if (is_admin()) {
            require_once FTT_PLUGIN_DIR . 'includes/admin-validation-page.php';
        }
        
        // Stripe Settings (always load - needed to configure API keys)
        require_once FTT_PLUGIN_DIR . 'includes/stripe/class-stripe-settings.php';
        
        // Stripe Integration & Billing (only if Stripe library is available)
        if (file_exists(FTT_PLUGIN_DIR . 'lib/stripe-php/init.php')) {
            require_once FTT_PLUGIN_DIR . 'includes/stripe/class-stripe-integration.php';
            require_once FTT_PLUGIN_DIR . 'includes/stripe/class-stripe-webhooks.php';
            require_once FTT_PLUGIN_DIR . 'includes/billing/class-billing-manager.php';
            require_once FTT_PLUGIN_DIR . 'includes/admin-billing-dashboard.php';
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
        add_action('wp_footer', array($this, 'render_airport_reminder_modal'));
        add_action('wp_login', array($this, 'track_login_count'), 10, 2);
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
        FTT_API_Tracker::init();
        FTT_Cron_Setup::init();
        FTT_Flight_Linking::init();
        FTT_Family_Groups::init();
        FTT_Migration_Admin::init();
        FTT_CORS::init();
        FTT_SEO::init();
        FTT_Email_Templates::init();
        FTT_Cookie_Consent::init();
        FTT_Exit_Survey::init();
        FTT_Cookie_Scanner::init();
        FTT_User_Profile::init();
        FTT_External_Calendars::init();
        FTT_Newsletter_Sync::init();
        FTT_AI_Event_Parser::init();
        // FTT_Domain_Routing::init(); // Disabled - single domain setup
        
        // Load test/debug tools (only if file exists)
        if (file_exists(FTT_PLUGIN_DIR . 'test-invite-validation.php')) {
            require_once FTT_PLUGIN_DIR . 'test-invite-validation.php';
        }
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

        // Migration 2: Flush rewrite rules so our sitemap.xml rewrite takes effect
        // now that the conflicting WP core sitemap is disabled (v2.6.33+)
        if (version_compare($migration_version, '2', '<')) {
            flush_rewrite_rules();
            update_option('ftt_migration_version', '2');
        }

        // Migration 3: Flush rewrite rules for new llms.txt rewrite rule (v2.6.34+)
        if (version_compare($migration_version, '3', '<')) {
            flush_rewrite_rules();
            update_option('ftt_migration_version', '3');
        }

        // Migration 4: Add group_token column to ftt_family_groups for opaque URL tokens (v2.6.36+)
        if (version_compare($migration_version, '4', '<')) {
            global $wpdb;
            $table = $wpdb->prefix . 'ftt_family_groups';
            $col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'group_token'" );
            if ( empty( $col ) ) {
                $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `group_token` VARCHAR(16) NULL UNIQUE AFTER `color`, ADD INDEX `idx_token` (`group_token`)" );
            }
            update_option('ftt_migration_version', '4');
        }

        // Migration 5: Backfill ftt_event_groups for events that were never associated
        // with a group (created before groups existed or before auto-assign was working).
        if (version_compare($migration_version, '5', '<')) {
            global $wpdb;
            $eg_table      = $wpdb->prefix . 'ftt_event_groups';
            $members_table = $wpdb->prefix . 'ftt_group_members';

            // Find all published ftt_event posts not yet in ftt_event_groups.
            $unassociated = $wpdb->get_results(
                "SELECT p.ID,
                        MAX(CASE WHEN pm.meta_key = 'member_id' THEN pm.meta_value END) AS member_id,
                        MAX(CASE WHEN pm.meta_key = 'group_id'  THEN pm.meta_value END) AS group_id_meta
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm
                        ON p.ID = pm.post_id AND pm.meta_key IN ('member_id','group_id')
                 LEFT JOIN {$eg_table} eg ON p.ID = eg.post_id
                 WHERE p.post_type   = 'ftt_event'
                   AND p.post_status = 'publish'
                   AND eg.post_id IS NULL
                 GROUP BY p.ID"
            );

            foreach ($unassociated as $ev) {
                $target_group = null;

                // 1. Prefer explicit group_id post meta.
                if (!empty($ev->group_id_meta)) {
                    $target_group = (int) $ev->group_id_meta;
                }

                // 2. Derive from the assigned member's group membership.
                if (!$target_group && !empty($ev->member_id)) {
                    $target_group = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT group_id FROM {$members_table} WHERE user_id = %d ORDER BY added_at ASC LIMIT 1",
                        (int) $ev->member_id
                    ));
                }

                if ($target_group) {
                    // Insert (ignore duplicate — table has UNIQUE KEY).
                    $wpdb->query($wpdb->prepare(
                        "INSERT IGNORE INTO {$eg_table} (post_id, group_id, created_at) VALUES (%d, %d, %s)",
                        $ev->ID, $target_group, current_time('mysql')
                    ));
                    // Ensure post meta is also consistent.
                    if (empty($ev->group_id_meta)) {
                        update_post_meta($ev->ID, 'group_id', $target_group);
                    }
                }
            }

            update_option('ftt_migration_version', '5');
        }

        // Migration 6: Re-run event backfill with post_author fallback for events
        // whose child (member_id) was never added to wp_ftt_group_members.
        if (version_compare($migration_version, '6', '<')) {
            global $wpdb;
            $eg_table      = $wpdb->prefix . 'ftt_event_groups';
            $members_table = $wpdb->prefix . 'ftt_group_members';

            // Find all published ftt_event posts still not in ftt_event_groups.
            $unassociated = $wpdb->get_results(
                "SELECT p.ID, p.post_author,
                        MAX(CASE WHEN pm.meta_key = 'member_id' THEN pm.meta_value END) AS member_id,
                        MAX(CASE WHEN pm.meta_key = 'group_id'  THEN pm.meta_value END) AS group_id_meta
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm
                        ON p.ID = pm.post_id AND pm.meta_key IN ('member_id','group_id')
                 LEFT JOIN {$eg_table} eg ON p.ID = eg.post_id
                 WHERE p.post_type   = 'ftt_event'
                   AND p.post_status = 'publish'
                   AND eg.post_id IS NULL
                 GROUP BY p.ID"
            );

            foreach ($unassociated as $ev) {
                $target_group = null;

                // 1. Explicit group_id post meta.
                if (!empty($ev->group_id_meta)) {
                    $target_group = (int) $ev->group_id_meta;
                }

                // 2. Child (member_id) is directly in ftt_group_members.
                if (!$target_group && !empty($ev->member_id)) {
                    $target_group = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT group_id FROM {$members_table} WHERE user_id = %d ORDER BY added_at ASC LIMIT 1",
                        (int) $ev->member_id
                    ));
                }

                // 3. Fall back to the post author's group membership.
                //    The author is the parent who created the event, and they ARE in the group.
                if (!$target_group && !empty($ev->post_author)) {
                    $target_group = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT group_id FROM {$members_table} WHERE user_id = %d ORDER BY added_at ASC LIMIT 1",
                        (int) $ev->post_author
                    ));
                }

                if ($target_group) {
                    $wpdb->query($wpdb->prepare(
                        "INSERT IGNORE INTO {$eg_table} (post_id, group_id, created_at) VALUES (%d, %d, %s)",
                        $ev->ID, $target_group, current_time('mysql')
                    ));
                    if (empty($ev->group_id_meta)) {
                        update_post_meta($ev->ID, 'group_id', $target_group);
                    }
                }
            }

            update_option('ftt_migration_version', '6');
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
        // Only enqueue on pages with our shortcodes or on known FTT pages
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }
        
        // Check if this is one of our plugin pages (by page ID)
        $is_ftt_page = FTT_Pages::is_ftt_page();
        
        // Check if page has our shortcodes
        $has_shortcode = false;
        // NOTE: 'ftt_homepage' is intentionally excluded from this list.
        // The homepage is managed as an Elementor Pro page, giving the site owner
        // full control over the layout. The plugin template (templates/homepage.php)
        // is NOT used for the live homepage. The stylesheet is injected directly
        // inside that template file as a fallback for Elementor-rendered pages.
        $shortcodes = array('ftt_calendar', 'ftt_event_form', 'ftt_dashboard', 'ftt_event_list', 'ftt_family_management', 'ftt_login', 'ftt_register', 'ftt_groups', 'ftt_onboarding', 'ftt_trial_expired');
        
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                $has_shortcode = true;
                break;
            }
        }
        
        // Exit if not an FTT page and no shortcode found
        if (!$is_ftt_page && !$has_shortcode) {
            return;
        }
        
        // Enqueue dashicons for front-end use
        wp_enqueue_style('dashicons');
        
        // Enqueue styles
        wp_enqueue_style(
            'ftt-styles',
            FTT_PLUGIN_URL . 'assets/css/styles.css',
            array('dashicons'),
            FTT_VERSION
        );
        
        // Enqueue Astra theme color overrides
        wp_enqueue_style(
            'ftt-astra-colors',
            FTT_PLUGIN_URL . 'assets/css/ftt-astra-colors.css',
            array('ftt-styles'),
            FTT_VERSION
        );
        
        // Add dynamic event type colors (fall back to defaults when settings not yet saved)
        $settings = get_option('ftt_settings', array());
        $event_types = !empty($settings['event_types'])
            ? $settings['event_types']
            : (class_exists('FTT_CPT') ? FTT_CPT::get_default_event_types() : array());
        
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
        $current_uid = get_current_user_id();
        wp_localize_script('ftt-main', 'fttData', array(
            'pluginUrl' => FTT_PLUGIN_URL,
            'restUrl' => rest_url('ftt/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'isAdmin' => current_user_can('edit_posts'),
            'timezone' => wp_timezone_string(),
            'userTimezone' => FTT_User_Profile::get_user_timezone($current_uid),
            'userCalendarView' => get_user_meta($current_uid, 'ftt_calendar_view', true) ?: 'month',
            'eventFormUrl' => FTT_Pages::get_page_url('event_form'),
            'dashboardUrl' => FTT_Pages::get_page_url('dashboard'),
            'geocodingProvider' => $settings['geocoding_provider'] ?? 'none',
            'mapboxApiKey' => $settings['mapbox_api_key'] ?? '',
            'googlePlacesApiKey' => $settings['google_places_api_key'] ?? '',
            'showAirportReminder' => $this->should_show_airport_reminder($current_uid),
            'externalCalendars' => $current_uid ? array_values(
                array_map(
                    function( $f ) { return array( 'label' => $f['label'], 'color' => $f['color'] ); },
                    FTT_External_Calendars::get_feeds( $current_uid )
                )
            ) : array(),
        ));
    }

    /**
     * Increment login count on every successful login.
     * Hooked into wp_login.
     *
     * @param string  $user_login
     * @param WP_User $user
     */
    public function track_login_count( $user_login, $user ) {
        $count = (int) get_user_meta( $user->ID, 'ftt_login_count', true );
        update_user_meta( $user->ID, 'ftt_login_count', $count + 1 );
    }

    /**
     * Decide whether to show the home-airport reminder modal on this page load.
     * Conditions: logged in, airport not set, login count 2–4, not dismissed, not on onboarding.
     *
     * @param int $user_id
     * @return bool
     */
    private function should_show_airport_reminder( $user_id ) {
        if ( ! $user_id ) {
            return false;
        }
        // Already dismissed permanently.
        if ( get_user_meta( $user_id, 'ftt_airport_reminder_dismissed', true ) ) {
            return false;
        }
        // Airport already set — nothing to remind.
        $airport = get_user_meta( $user_id, 'ftt_home_airport', true );
        if ( empty( $airport ) ) {
            $airports_raw = get_user_meta( $user_id, 'ftt_home_airports', true );
            $airports_arr = is_array( $airports_raw ) ? $airports_raw
                          : ( $airports_raw ? json_decode( $airports_raw, true ) : [] );
            $airport = ! empty( $airports_arr[0] ) ? $airports_arr[0] : '';
        }
        if ( ! empty( $airport ) ) {
            return false;
        }
        // Only remind on logins 2, 3, 4 (skip login 1 = just came from onboarding).
        $login_count = (int) get_user_meta( $user_id, 'ftt_login_count', true );
        if ( $login_count < 2 || $login_count > 4 ) {
            return false;
        }
        // Don't show on onboarding pages.
        if ( is_page() ) {
            $onboarding_url = home_url( '/ftt-onboarding/' );
            $current_url    = home_url( add_query_arg( [] ) );
            if ( strpos( $current_url, $onboarding_url ) !== false ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Output the home-airport reminder modal in wp_footer when needed.
     * The modal is invisible until JS shows it.
     */
    public function render_airport_reminder_modal() {
        if ( ! $this->should_show_airport_reminder( get_current_user_id() ) ) {
            return;
        }
        $site_tz        = get_option( 'ftt_settings', [] )['default_timezone'] ?? wp_timezone_string();
        $saved_timezone = get_user_meta( get_current_user_id(), 'ftt_timezone', true ) ?: $site_tz;
        ?>
        <div id="ftt-airport-reminder-modal" class="ftt-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="ftt-airport-reminder-title">
            <div class="ftt-modal-content">
                <span class="ftt-modal-close" id="ftt-airport-reminder-close" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Close', 'schedule-collaboration-tracking' ); ?>">&times;</span>
                <div class="ftt-onboarding-icon">✈️</div>
                <h2 id="ftt-airport-reminder-title"><?php esc_html_e( 'One quick tip', 'schedule-collaboration-tracking' ); ?></h2>
                <p><?php esc_html_e( "Set your home airport and the AI will automatically suggest the right flights when you plan a trip — no need to type it every time.", 'schedule-collaboration-tracking' ); ?></p>

                <div class="ftt-onboard-profile-row">
                    <label for="ftt-reminder-airport" class="ftt-onboard-profile-label"><?php esc_html_e( 'Home Airport (IATA code)', 'schedule-collaboration-tracking' ); ?></label>
                    <input type="text"
                           id="ftt-reminder-airport"
                           class="ftt-onboard-airport-input"
                           placeholder="e.g. BDL"
                           maxlength="3"
                           autocomplete="off" />
                    <p class="description"><?php esc_html_e( '3-letter code — e.g. BOS, JFK, LAX.', 'schedule-collaboration-tracking' ); ?></p>
                </div>

                <div class="ftt-onboard-profile-row">
                    <label for="ftt-reminder-timezone" class="ftt-onboard-profile-label"><?php esc_html_e( 'Your Timezone', 'schedule-collaboration-tracking' ); ?></label>
                    <select id="ftt-reminder-timezone">
                        <?php echo wp_timezone_choice( $saved_timezone, get_user_locale() ); ?>
                    </select>
                </div>

                <div class="ftt-onboarding-actions" style="margin-top:16px;">
                    <button type="button" class="ftt-btn ftt-btn-primary" id="ftt-reminder-save"><?php esc_html_e( 'Save', 'schedule-collaboration-tracking' ); ?></button>
                </div>
                <div id="ftt-reminder-msg" class="ftt-onboard-msg" style="display:none;"></div>

                <div style="text-align:center;margin-top:12px;">
                    <a href="#" id="ftt-reminder-dismiss" style="font-size:0.85em;color:#888;"><?php esc_html_e( "Don't ask again", 'schedule-collaboration-tracking' ); ?></a>
                </div>
            </div>
        </div>
        <?php
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
