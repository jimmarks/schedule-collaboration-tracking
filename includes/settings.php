<?php
/**
 * Plugin Settings
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FTT_Settings {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_settings_page'));
        add_action('admin_menu', array(__CLASS__, 'add_combined_pages'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('wp_dashboard_setup', array(__CLASS__, 'register_dashboard_widget'));
    }
    
    /**
     * Add settings page to admin menu
     */
    public static function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            __('FTT Settings', 'schedule-collaboration-tracking'),
            __('Settings', 'schedule-collaboration-tracking'),
            'manage_options',
            'ftt-settings',
            array(__CLASS__, 'render_settings_page')
        );

        // Remove the built-in "Add New" submenu — admins should direct
        // users to add events within their own groups/dashboards.
        remove_submenu_page( 'edit.php?post_type=ftt_event', 'post-new.php?post_type=ftt_event' );
    }

    /**
     * Register combined pages (Users & Groups, System)
     */
    public static function add_combined_pages() {
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            __('Users & Groups', 'schedule-collaboration-tracking'),
            __('Users & Groups', 'schedule-collaboration-tracking'),
            'manage_options',
            'ftt-users-groups',
            array(__CLASS__, 'render_users_groups_page')
        );
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            __('System', 'schedule-collaboration-tracking'),
            __('System', 'schedule-collaboration-tracking'),
            'manage_options',
            'ftt-system',
            array(__CLASS__, 'render_system_page')
        );
    }

    /**
     * Register the billing summary dashboard widget
     */
    public static function register_dashboard_widget() {
        if ( ! current_user_can('manage_options') ) return;
        wp_add_dashboard_widget(
            'ftt_billing_summary',
            __('FTT Billing Overview', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_dashboard_widget')
        );
    }

    /**
     * Render the billing summary dashboard widget
     */
    public static function render_dashboard_widget() {
        if ( ! class_exists('FTT_Admin_Billing_Dashboard') ) {
            echo '<p>' . esc_html__('Billing module not available (Stripe library missing).', 'schedule-collaboration-tracking') . '</p>';
            return;
        }

        global $wpdb;
        $groups_table   = $wpdb->prefix . 'ftt_family_groups';
        $members_table  = $wpdb->prefix . 'ftt_family_group_members';

        $groups = $wpdb->get_results( "SELECT subscription_status, subscription_interval FROM {$groups_table} WHERE is_archived = 0" );

        $base_monthly = 9.99;
        $addon_monthly = 5.00;
        $base_annual  = 99.00;
        $addon_annual  = 50.00;

        $total_mrr       = 0;
        $active          = 0;
        $trialing        = 0;
        $past_due        = 0;
        $canceled        = 0;

        foreach ( $groups as $g ) {
            switch ( $g->subscription_status ) {
                case 'active':
                    $active++;
                    $child_count = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$members_table} m
                         INNER JOIN {$groups_table} gr ON m.group_id = gr.id
                         WHERE gr.subscription_status = %s AND gr.is_archived = 0",
                        'active'
                    ));
                    if ( $g->subscription_interval === 'year' ) {
                        $total_mrr += ( $base_annual + max(0, $child_count - 1) * $addon_annual ) / 12;
                    } else {
                        $total_mrr += $base_monthly + max(0, $child_count - 1) * $addon_monthly;
                    }
                    break;
                case 'trialing': $trialing++; break;
                case 'past_due': $past_due++;  break;
                case 'canceled': $canceled++;  break;
            }
        }

        $full_url  = admin_url('edit.php?post_type=ftt_event&page=ftt-settings&tab=billing');
        $api_url   = admin_url('edit.php?post_type=ftt_event&page=ftt-settings&tab=billing');
        $api_sum   = class_exists('FTT_API_Tracker') ? FTT_API_Tracker::get_summary() : null;
        ?>
        <style>
        .ftt-dw-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px; }
        .ftt-dw-stat { background:#f6f7f7; border-radius:6px; padding:12px 14px; text-align:center; }
        .ftt-dw-stat .ftt-dw-value { font-size:28px; font-weight:700; line-height:1.1; color:#1d2327; }
        .ftt-dw-stat .ftt-dw-label { font-size:12px; color:#646970; margin-top:3px; }
        .ftt-dw-stat.ftt-dw-mrr .ftt-dw-value { color:#2a9d49; }
        .ftt-dw-stat.ftt-dw-pastdue .ftt-dw-value { color:<?php echo $past_due > 0 ? '#d63638' : '#1d2327'; ?>; }
        .ftt-dw-footer { font-size:12px; color:#646970; display:flex; justify-content:space-between; align-items:center; }
        .ftt-dw-divider { border:none; border-top:1px solid #e2e4e7; margin:14px 0 10px; }
        .ftt-dw-section-label { font-size:11px; font-weight:600; color:#3c434a; text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; }
        .ftt-dw-api-row { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:4px; }
        .ftt-dw-api-cell { background:#f6f7f7; border-radius:4px; padding:8px 10px; }
        .ftt-dw-api-name { font-size:11px; font-weight:600; color:#3c434a; margin-bottom:4px; }
        .ftt-dw-api-stats { font-size:12px; color:#1d2327; }
        .ftt-dw-api-stats span { color:#646970; }
        .ftt-dw-api-err { color:#d63638 !important; }
        </style>
        <div class="ftt-dw-grid">
            <div class="ftt-dw-stat ftt-dw-mrr">
                <div class="ftt-dw-value">$<?php echo number_format($total_mrr, 2); ?></div>
                <div class="ftt-dw-label">Est. Monthly Revenue</div>
            </div>
            <div class="ftt-dw-stat">
                <div class="ftt-dw-value"><?php echo $active; ?></div>
                <div class="ftt-dw-label">Active Subscriptions</div>
            </div>
            <div class="ftt-dw-stat">
                <div class="ftt-dw-value"><?php echo $trialing; ?></div>
                <div class="ftt-dw-label">In Trial</div>
            </div>
            <div class="ftt-dw-stat ftt-dw-pastdue">
                <div class="ftt-dw-value"><?php echo $past_due; ?></div>
                <div class="ftt-dw-label">Past Due<?php echo $past_due > 0 ? ' ⚠️' : ''; ?></div>
            </div>
        </div>

        <?php if ( $api_sum ) : ?>
        <hr class="ftt-dw-divider">
        <div class="ftt-dw-section-label">API Usage</div>
        <div class="ftt-dw-api-row">
            <div class="ftt-dw-api-cell">
                <div class="ftt-dw-api-name">SerpAPI (Flight Prices)</div>
                <div class="ftt-dw-api-stats">
                    <strong><?php echo (int) $api_sum['serpapi']['today']; ?></strong> <span>today</span>
                    &nbsp;·&nbsp;
                    <strong><?php echo (int) $api_sum['serpapi']['last_7']; ?></strong> <span>/ 7d</span>
                    &nbsp;·&nbsp;
                    <strong><?php echo (int) $api_sum['serpapi']['last_30']; ?></strong> <span>/ 30d</span>
                    <?php if ( $api_sum['serpapi']['errors_7'] > 0 ) : ?>
                        <br><span class="ftt-dw-api-err">⚠ <?php echo (int) $api_sum['serpapi']['errors_7']; ?> error<?php echo $api_sum['serpapi']['errors_7'] !== 1 ? 's' : ''; ?> this week</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ftt-dw-api-cell">
                <div class="ftt-dw-api-name">Google Places</div>
                <div class="ftt-dw-api-stats">
                    <strong><?php echo (int) $api_sum['google_places']['today']; ?></strong> <span>today</span>
                    &nbsp;·&nbsp;
                    <strong><?php echo (int) $api_sum['google_places']['last_7']; ?></strong> <span>/ 7d</span>
                    &nbsp;·&nbsp;
                    <strong><?php echo (int) $api_sum['google_places']['last_30']; ?></strong> <span>/ 30d</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="ftt-dw-footer" style="margin-top:10px;">
            <span><?php echo count($groups); ?> total groups</span>
            <a href="<?php echo esc_url($full_url); ?>"><?php esc_html_e('Full Dashboard →', 'schedule-collaboration-tracking'); ?></a>
        </div>
        <?php
    }

    /**
     * Call a render callback and strip its outer <div class="wrap"> and first <h1> so
     * it embeds cleanly inside a combined tabbed page.
     */
    private static function render_embedded( $callback ) {
        ob_start();
        call_user_func( $callback );
        $html = ob_get_clean();
        // Remove leading <div class="wrap"> and its matching closing </div>
        $html = preg_replace( '/^\s*<div[^>]*class="wrap"[^>]*>\s*/i', '', $html, 1 );
        // Remove the last </div> (the closing wrap div)
        $pos  = strrpos( $html, '</div>' );
        if ( $pos !== false ) {
            $html = substr( $html, 0, $pos ) . substr( $html, $pos + 6 );
        }
        // Remove the first <h1>…</h1> (page title, already shown by parent)
        $html = preg_replace( '/<h1[^>]*>.*?<\/h1>/is', '', $html, 1 );
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized render output
    }

    /**
     * Render the combined Users & Groups page
     */
    public static function render_users_groups_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'users';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Users & Groups', 'schedule-collaboration-tracking' ); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?post_type=ftt_event&page=ftt-users-groups&tab=users"
                   class="nav-tab <?php echo $tab === 'users' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'Manage Users', 'schedule-collaboration-tracking' ); ?>
                </a>
                <a href="?post_type=ftt_event&page=ftt-users-groups&tab=groups"
                   class="nav-tab <?php echo $tab === 'groups' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Manage Groups', 'schedule-collaboration-tracking' ); ?>
                </a>
            </h2>
            <div style="margin-top:20px;">
            <?php
            if ( $tab === 'groups' ) {
                self::render_embedded( array( 'FTT_Admin_Group_Management', 'render_page' ) );
            } else {
                self::render_embedded( array( 'FTT_Roles', 'render_admin_page' ) );
            }
            ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the combined System page (Cron + Migrate)
     */
    public static function render_system_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'cron';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'System', 'schedule-collaboration-tracking' ); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?post_type=ftt_event&page=ftt-system&tab=cron"
                   class="nav-tab <?php echo $tab === 'cron' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Cron Setup', 'schedule-collaboration-tracking' ); ?>
                </a>
                <a href="?post_type=ftt_event&page=ftt-system&tab=docs"
                   class="nav-tab <?php echo $tab === 'docs' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-book-alt"></span> <?php esc_html_e( 'Cron Docs', 'schedule-collaboration-tracking' ); ?>
                </a>
                <a href="?post_type=ftt_event&page=ftt-system&tab=migrate"
                   class="nav-tab <?php echo $tab === 'migrate' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-migrate"></span> <?php esc_html_e( 'Migrate Events', 'schedule-collaboration-tracking' ); ?>
                </a>
            </h2>
            <div style="margin-top:20px;">
            <?php
            switch ( $tab ) {
                case 'docs':
                    self::render_embedded( array( 'FTT_Cron_Setup', 'render_docs_page' ) );
                    break;
                case 'migrate':
                    if ( class_exists('FTT_Event_Migration') ) {
                        self::render_embedded( array( 'FTT_Event_Migration', 'render_page' ) );
                    } else {
                        echo '<p>' . esc_html__('Migration module not available.', 'schedule-collaboration-tracking') . '</p>';
                    }
                    break;
                case 'cron':
                default:
                    self::render_embedded( array( 'FTT_Cron_Setup', 'render_page' ) );
                    break;
            }
            ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting(
            'ftt_settings_group',
            'ftt_settings',
            array(
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
            )
        );
        
        // TAB 1: GENERAL
        add_settings_section(
            'ftt_general_section',
            __('General Settings', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_general_section'),
            'ftt-settings-general'
        );
        
        add_settings_field(
            'require_login',
            __('Require Login', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_require_login_field'),
            'ftt-settings-general',
            'ftt_general_section'
        );
        
        add_settings_field(
            'default_timezone',
            __('Default Timezone', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_default_timezone_field'),
            'ftt-settings-general',
            'ftt_general_section'
        );
        
        add_settings_field(
            'enable_login_menu',
            __('Login/Logout Menu', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_login_menu_field'),
            'ftt-settings-general',
            'ftt_general_section'
        );
        
        add_settings_field(
            'login_menu_mode',
            __('Menu Display Mode', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_login_menu_mode_field'),
            'ftt-settings-general',
            'ftt_general_section'
        );

        add_settings_field(
            'mobile_menu_location',
            __('Mobile Menu Location Slug', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_mobile_menu_location_field'),
            'ftt-settings-general',
            'ftt_general_section'
        );
        
        add_settings_field(
            'notification_from_email',
            __('Notification From Email', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_notification_from_email_field'),
            'ftt-settings-general',
            'ftt_general_section'
        );
        
        add_settings_field(
            'notification_from_name',
            __('Notification From Name', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_notification_from_name_field'),
            'ftt-settings-general',
            'ftt_general_section'
        );
        
        add_settings_field(
            'invitation_expiration_days',
            __('Adult Invitation Expiration', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_invitation_expiration_field'),
            'ftt-settings-general',
            'ftt_general_section'
        );
        
        // TAB 2: API KEYS
        add_settings_section(
            'ftt_api_section',
            __('API Configuration', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_api_section'),
            'ftt-settings-api'
        );
        
        add_settings_field(
            'geocoding_provider',
            __('Address Autocomplete Provider', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_geocoding_provider_field'),
            'ftt-settings-api',
            'ftt_api_section'
        );
        
        add_settings_field(
            'mapbox_api_key',
            __('Mapbox API Key', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_mapbox_api_key_field'),
            'ftt-settings-api',
            'ftt_api_section'
        );
        
        add_settings_field(
            'google_places_api_key',
            __('Google Places API Key', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_google_places_api_key_field'),
            'ftt-settings-api',
            'ftt_api_section'
        );
        
        add_settings_field(
            'serpapi_api_key',
            __('SerpAPI Key (Flight Pricing)', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_serpapi_api_key_field'),
            'ftt-settings-api',
            'ftt_api_section'
        );

        add_settings_field(
            'openai_api_key',
            __('OpenAI API Key (AI Event Parser)', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_openai_api_key_field'),
            'ftt-settings-api',
            'ftt_api_section'
        );

        // TAB 3: EVENTS
        add_settings_section(
            'ftt_event_categories_section',
            __('Event Categories', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_event_categories_section'),
            'ftt-settings-events'
        );
        
        add_settings_field(
            'event_categories',
            __('Manage Categories', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_event_categories_field'),
            'ftt-settings-events',
            'ftt_event_categories_section'
        );
        
        add_settings_section(
            'ftt_event_types_section',
            __('Event Types', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_event_types_section'),
            'ftt-settings-events'
        );
        
        add_settings_field(
            'event_types',
            __('Manage Event Types', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_event_types_field'),
            'ftt-settings-events',
            'ftt_event_types_section'
        );
        
        // TAB 4: CALENDAR
        add_settings_section(
            'ftt_calendar_section',
            __('Calendar Subscription', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_calendar_section'),
            'ftt-settings-calendar'
        );
        
        add_settings_field(
            'enable_ical_feed',
            __('Enable iCal Feed', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_enable_ical_field'),
            'ftt-settings-calendar',
            'ftt_calendar_section'
        );
        
        add_settings_field(
            'ical_require_auth',
            __('Require Authentication', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_ical_auth_field'),
            'ftt-settings-calendar',
            'ftt_calendar_section'
        );
        
        add_settings_field(
            'calendar_tokens',
            __('Calendar Tokens', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_calendar_tokens_field'),
            'ftt-settings',
            'ftt_calendar_section'
        );
        
        // TAB 6: NEWSLETTER SYNC
        if ( class_exists('FTT_Newsletter_Sync') ) {
            FTT_Newsletter_Sync::register_settings_fields();
        }

        // TAB 5: SECURITY
        add_settings_section(
            'ftt_security_section',
            __('Security & Spam Protection', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_security_section'),
            'ftt-settings-security'
        );
        
        add_settings_field(
            'enable_recaptcha',
            __('Enable Google reCAPTCHA v3', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_enable_recaptcha_field'),
            'ftt-settings-security',
            'ftt_security_section'
        );
        
        add_settings_field(
            'recaptcha_site_key',
            __('reCAPTCHA Site Key', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_recaptcha_site_key_field'),
            'ftt-settings-security',
            'ftt_security_section'
        );
        
        add_settings_field(
            'recaptcha_secret_key',
            __('reCAPTCHA Secret Key', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_recaptcha_secret_key_field'),
            'ftt-settings-security',
            'ftt_security_section'
        );
    }
    
    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        // Get existing settings and merge with new input
        $existing = get_option('ftt_settings', array());
        $sanitized = $existing; // Start with existing settings
        
        // Only update fields that are present in input (current tab)
        if (isset($input['require_login'])) {
            $sanitized['require_login'] = (bool) $input['require_login'];
        }
        if (isset($input['default_timezone'])) {
            $sanitized['default_timezone'] = sanitize_text_field($input['default_timezone']);
        }
        if (isset($input['enable_login_menu'])) {
            $sanitized['enable_login_menu'] = (bool) $input['enable_login_menu'];
        }
        if (isset($input['login_menu_mode'])) {
            $sanitized['login_menu_mode'] = in_array($input['login_menu_mode'], array('login_only', 'both')) ? 
                $input['login_menu_mode'] : 'both';
        }
        if (isset($input['mobile_menu_location'])) {
            // Comma-separated slugs — sanitize each one
            $slugs = array_map( 'sanitize_key', explode( ',', $input['mobile_menu_location'] ) );
            $sanitized['mobile_menu_location'] = implode( ',', array_filter( $slugs ) );
        }
        if (isset($input['notification_from_email'])) {
            $sanitized['notification_from_email'] = sanitize_email($input['notification_from_email']);
        }
        if (isset($input['notification_from_name'])) {
            $sanitized['notification_from_name'] = sanitize_text_field($input['notification_from_name']);
        }
        if (isset($input['invitation_expiration_days'])) {
            $days = absint($input['invitation_expiration_days']);
            $sanitized['invitation_expiration_days'] = max(1, min(90, $days)); // Between 1 and 90 days
        }
        if (isset($input['geocoding_provider'])) {
            $sanitized['geocoding_provider'] = in_array($input['geocoding_provider'], array('none', 'mapbox', 'google')) ? 
                $input['geocoding_provider'] : 'none';
        }
        if (isset($input['mapbox_api_key'])) {
            $sanitized['mapbox_api_key'] = sanitize_text_field($input['mapbox_api_key']);
        }
        if (isset($input['google_places_api_key'])) {
            $sanitized['google_places_api_key'] = sanitize_text_field($input['google_places_api_key']);
        }
        if (isset($input['serpapi_api_key'])) {
            $sanitized['serpapi_api_key'] = sanitize_text_field($input['serpapi_api_key']);
        }
        if (isset($input['openai_api_key'])) {
            $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        }
        // Sanitize event categories
        if (isset($input['event_categories']) && is_array($input['event_categories'])) {
            $sanitized['event_categories'] = array();
            foreach ($input['event_categories'] as $key => $category) {
                if (!empty($category['label'])) {
                    $sanitized_key = sanitize_key($key);
                    $sanitized['event_categories'][$sanitized_key] = array(
                        'label' => sanitize_text_field($category['label']),
                        'icon' => wp_kses_post($category['icon'] ?? '📁'),
                    );
                }
            }
        }
        
        // Sanitize event types
        if (isset($input['event_types']) && is_array($input['event_types'])) {
            $sanitized['event_types'] = array();
            foreach ($input['event_types'] as $key => $type) {
                if (!empty($type['label'])) {
                    $sanitized_key = sanitize_key($key);
                    $sanitized['event_types'][$sanitized_key] = array(
                        'label' => sanitize_text_field($type['label']),
                        'color' => sanitize_hex_color($type['color'] ?? '#2196F3'),
                        'category' => !empty($type['category']) ? sanitize_key($type['category']) : '',
                    );
                }
            }
        }
        
        // Sanitize calendar subscription settings
        if (isset($input['enable_ical_feed'])) {
            $sanitized['enable_ical_feed'] = (bool) $input['enable_ical_feed'];
        }
        if (isset($input['ical_require_auth'])) {
            $sanitized['ical_require_auth'] = (bool) $input['ical_require_auth'];
        }
        
        // Sanitize reCAPTCHA settings
        if (isset($input['enable_recaptcha'])) {
            $sanitized['enable_recaptcha'] = (bool) $input['enable_recaptcha'];
        }
        if (isset($input['recaptcha_site_key'])) {
            $sanitized['recaptcha_site_key'] = sanitize_text_field($input['recaptcha_site_key']);
        }
        if (isset($input['recaptcha_secret_key'])) {
            $sanitized['recaptcha_secret_key'] = sanitize_text_field($input['recaptcha_secret_key']);
        }

        // Policy & Communications keys (written by FTT_Email_Templates::handle_save_policy).
        // Must be listed here because register_setting() attaches this callback to
        // sanitize_option_ftt_settings, which fires on every update_option() call.
        if (isset($input['policy_privacy_page'])) {
            $sanitized['policy_privacy_page'] = absint($input['policy_privacy_page']);
        }
        if (isset($input['policy_terms_page'])) {
            $sanitized['policy_terms_page'] = absint($input['policy_terms_page']);
        }
        if (isset($input['policy_cookie_page'])) {
            $sanitized['policy_cookie_page'] = absint($input['policy_cookie_page']);
        }
        if (isset($input['policy_sms_page'])) {
            $sanitized['policy_sms_page'] = absint($input['policy_sms_page']);
        }
        if (isset($input['policy_acceptance_wording'])) {
            $sanitized['policy_acceptance_wording'] = wp_kses_post($input['policy_acceptance_wording']);
        }
        if (isset($input['email_test_address'])) {
            $sanitized['email_test_address'] = sanitize_email($input['email_test_address']);
        }

        // Newsletter sync settings (delegated to the Newsletter sync class).
        if ( class_exists('FTT_Newsletter_Sync') ) {
            $sanitized = FTT_Newsletter_Sync::sanitize_settings_fields( $input, $sanitized );
        }

        return $sanitized;
    }
    
    /**
     * Render general section description
     */
    public static function render_general_section() {
        echo '<p>' . esc_html__('Configure general settings for the Schedule Tracker.', 'schedule-collaboration-tracking') . '</p>';
    }
    
    /**
     * Render API section description
     */
    public static function render_api_section() {
        echo '<p>' . esc_html__('Configure API keys for external services used by the plugin.', 'schedule-collaboration-tracking') . '</p>';
    }
    
    /**
     * Render event categories section description
     */
    public static function render_event_categories_section() {
        echo '<p>' . esc_html__('Manage event categories that organize your event types. Users can filter their calendar by these categories.', 'schedule-collaboration-tracking') . '</p>';
    }
    
    /**
     * Render event types section description
     */
    public static function render_event_types_section() {
        echo '<p>' . esc_html__('Manage event types available on your site. Assign each type to a category and customize colors.', 'schedule-collaboration-tracking') . '</p>';
    }
    
    /**
     * Render require login field
     */
    public static function render_require_login_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['require_login'] ?? false;
        ?>
        <label>
            <input type="checkbox" name="ftt_settings[require_login]" value="1" <?php checked($value, true); ?>>
            <?php esc_html_e('Require users to be logged in to view the schedule', 'schedule-collaboration-tracking'); ?>
        </label>
        <?php
    }
    
    /**
     * Render default timezone field
     */
    public static function render_default_timezone_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['default_timezone'] ?? wp_timezone_string();
        $timezones = timezone_identifiers_list();
        ?>
        <select name="ftt_settings[default_timezone]" class="regular-text">
            <?php foreach ($timezones as $timezone) : ?>
                <option value="<?php echo esc_attr($timezone); ?>" <?php selected($value, $timezone); ?>>
                    <?php echo esc_html($timezone); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Default timezone for new events.', 'schedule-collaboration-tracking'); ?></p>
        <?php
    }
    
    /**
     * Render login menu field
     */
    public static function render_login_menu_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['enable_login_menu'] ?? false;
        ?>
        <label>
            <input type="checkbox" name="ftt_settings[enable_login_menu]" value="1" <?php checked($value, true); ?>>
            <?php esc_html_e('Enable Login/Logout menu items', 'schedule-collaboration-tracking'); ?>
        </label>
        <p class="description"><?php esc_html_e('Add login and/or logout links to your WordPress menus. Use the menu item "Schedule Login/Logout" in Appearance → Menus.', 'schedule-collaboration-tracking'); ?></p>
        <?php
    }
    
    /**
     * Render login menu mode field
     */
    public static function render_login_menu_mode_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['login_menu_mode'] ?? 'both';
        $enabled = $settings['enable_login_menu'] ?? false;
        ?>
        <select name="ftt_settings[login_menu_mode]" <?php disabled(!$enabled); ?>>
            <option value="login_only" <?php selected($value, 'login_only'); ?>><?php esc_html_e('Login Only (hide when logged in)', 'schedule-collaboration-tracking'); ?></option>
            <option value="both" <?php selected($value, 'both'); ?>><?php esc_html_e('Login/Logout (show both)', 'schedule-collaboration-tracking'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Choose whether to show only login link or both login/logout links in menus.', 'schedule-collaboration-tracking'); ?></p>
        <?php
    }
    
    /**
     * Render mobile menu location slug field
     */
    public static function render_mobile_menu_location_field() {
        $settings = get_option( 'ftt_settings', array() );
        $value    = $settings['mobile_menu_location'] ?? '';
        ?>
        <input type="text"
               name="ftt_settings[mobile_menu_location]"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text"
               placeholder="e.g. mobile_header" />
        <p class="description">
            <?php esc_html_e( 'The WordPress nav menu location slug that your theme uses for its mobile/off-canvas menu. When a logged-in user visits the site, this location will be swapped to the "Mobile Navigation (Members)" menu (Appearance → Menus). Leave blank if your mobile menu uses the same location as desktop (Primary).', 'schedule-collaboration-tracking' ); ?><br><br>
            <strong><?php esc_html_e( 'How to find the slug:', 'schedule-collaboration-tracking' ); ?></strong> <?php esc_html_e( 'On your site\'s front-end, right-click the mobile hamburger nav and choose Inspect. Look for a <nav> element with an aria-label or class that includes the location name. Common Astra slugs: ', 'schedule-collaboration-tracking' ); ?>
            <code>primary</code>, <code>mobile_header</code>, <code>handheld</code>. <?php esc_html_e( 'You can enter multiple slugs separated by commas.', 'schedule-collaboration-tracking' ); ?>
        </p>
        <?php
    }

    /**
     * Render notification from email field
     */
    public static function render_notification_from_email_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['notification_from_email'] ?? '';
        $default = get_option('admin_email');
        ?>
        <input type="email" name="ftt_settings[notification_from_email]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php echo esc_attr($default); ?>">
        <p class="description">
            <?php esc_html_e('Email address to use as the "From" address for price alerts and notifications. Leave blank to use WordPress admin email.', 'schedule-collaboration-tracking'); ?>
            <br><strong><?php esc_html_e('Note:', 'schedule-collaboration-tracking'); ?></strong> <?php esc_html_e('If using an SMTP plugin, configure it to send from this address for best deliverability.', 'schedule-collaboration-tracking'); ?>
        </p>
        <?php
    }
    
    /**
     * Render notification from name field
     */
    public static function render_notification_from_name_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['notification_from_name'] ?? '';
        $default = get_bloginfo('name');
        ?>
        <input type="text" name="ftt_settings[notification_from_name]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php echo esc_attr($default); ?>">
        <p class="description">
            <?php esc_html_e('Name to display as the email sender. Leave blank to use site name.', 'schedule-collaboration-tracking'); ?>
        </p>
        <?php
    }
    
    /**
     * Render invitation expiration field
     */
    public static function render_invitation_expiration_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['invitation_expiration_days'] ?? 7;
        ?>
        <input type="number" name="ftt_settings[invitation_expiration_days]" value="<?php echo esc_attr($value); ?>" min="1" max="90" class="small-text"> <?php esc_html_e('days', 'schedule-collaboration-tracking'); ?>
        <p class="description">
            <?php esc_html_e('Number of days before adult invitations expire. Default is 7 days.', 'schedule-collaboration-tracking'); ?>
        </p>
        <?php
    }
    
    /**
     * Render geocoding provider field
     */
    public static function render_geocoding_provider_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['geocoding_provider'] ?? 'none';
        ?>
        <select name="ftt_settings[geocoding_provider]" id="ftt_geocoding_provider">
            <option value="none" <?php selected($value, 'none'); ?>><?php esc_html_e('None (Manual Entry Only)', 'schedule-collaboration-tracking'); ?></option>
            <option value="google" <?php selected($value, 'google'); ?>><?php esc_html_e('Google Places (Best for Schools/Venues)', 'schedule-collaboration-tracking'); ?></option>
            <option value="mapbox" <?php selected($value, 'mapbox'); ?>><?php esc_html_e('Mapbox (Good for Addresses)', 'schedule-collaboration-tracking'); ?></option>
        </select>
        <p class="description">
            <?php esc_html_e('Choose which geocoding service to use for address autocomplete. Google Places has better coverage of schools and local venues.', 'schedule-collaboration-tracking'); ?>
        </p>
        <?php
    }
    
    /**
     * Render Mapbox API key field
     */
    public static function render_mapbox_api_key_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['mapbox_api_key'] ?? '';
        $provider = $settings['geocoding_provider'] ?? 'none';
        ?>
        <input type="text" name="ftt_settings[mapbox_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="pk.eyJ1..." <?php disabled($provider !== 'mapbox'); ?>>
        <p class="description">
            <?php 
            echo wp_kses_post(
                sprintf(
                    __('Get your <a href="%s" target="_blank">Mapbox API key</a>. Free tier: 100,000 requests/month.', 'schedule-collaboration-tracking'),
                    'https://account.mapbox.com/access-tokens/'
                )
            ); 
            ?>
        </p>
        <?php
    }
    
    /**
     * Render Google Places API key field
     */
    public static function render_google_places_api_key_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['google_places_api_key'] ?? '';
        $provider = $settings['geocoding_provider'] ?? 'none';
        ?>
        <input type="text" name="ftt_settings[google_places_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="AIza..." <?php disabled($provider !== 'google'); ?>>
        <p class="description">
            <?php 
            echo wp_kses_post(
                sprintf(
                    __('Get your <a href="%s" target="_blank">Google Places API key</a>. <strong>Best for finding schools and venues.</strong> Cost: ~$0.02 per search (~$2/season for 30 events).', 'schedule-collaboration-tracking'),
                    'https://console.cloud.google.com/google/maps-apis/credentials'
                )
            ); 
            ?>
        </p>
        <?php
    }
    
    /**
     * Render event categories field
     */
    public static function render_event_categories_field() {
        $settings = get_option('ftt_settings', array());
        $categories = $settings['event_categories'] ?? self::get_default_event_categories();
        ?>
        <style>
            .ftt-category-row { margin-bottom: 15px; display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9; }
            .ftt-category-row input[type="text"].category-label { width: 250px; }
            .ftt-category-row input[type="text"].category-icon { width: 60px; text-align: center; font-size: 18px; }
            .ftt-category-key { width: 150px; font-family: monospace; color: #666; font-weight: 500; }
            .ftt-category-remove { color: #dc3232; cursor: pointer; text-decoration: none; padding: 0 10px; }
            .ftt-category-remove:hover { color: #a00; }
            #ftt-add-category { margin-top: 10px; }
            .ftt-categories-container { margin-bottom: 20px; }
        </style>
        <div class="ftt-categories-container" id="ftt-categories-container">
            <?php foreach ($categories as $key => $category) : ?>
                <div class="ftt-category-row" data-key="<?php echo esc_attr($key); ?>">
                    <span class="ftt-category-key"><?php echo esc_html($key); ?></span>
                    <input type="text" 
                           name="ftt_settings[event_categories][<?php echo esc_attr($key); ?>][label]" 
                           value="<?php echo esc_attr($category['label']); ?>" 
                           placeholder="Category Label"
                           class="category-label">
                    <input type="text" 
                           name="ftt_settings[event_categories][<?php echo esc_attr($key); ?>][icon]" 
                           value="<?php echo esc_attr($category['icon']); ?>" 
                           placeholder="📚"
                           class="category-icon"
                           title="Use an emoji or icon">
                    <a href="#" class="ftt-category-remove" onclick="return fttRemoveCategory(this);">✕ Remove</a>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="ftt-add-category" class="button">+ Add Category</button>
        <p class="description"><?php esc_html_e('Manage event categories. Use emojis or symbols for icons. Key names should be lowercase with underscores (e.g., my_category).', 'schedule-collaboration-tracking'); ?></p>
        
        <script>
        jQuery(document).ready(function($) {
            // Add new category
            $('#ftt-add-category').on('click', function() {
                var key = prompt('Enter category key (lowercase with underscores, e.g., "my_category"):');
                if (!key) return;
                
                key = key.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                
                if ($('.ftt-category-row[data-key="' + key + '"]').length > 0) {
                    alert('Category key already exists!');
                    return;
                }
                
                var row = $('<div class="ftt-category-row" data-key="' + key + '">' +
                    '<span class="ftt-category-key">' + key + '</span>' +
                    '<input type="text" name="ftt_settings[event_categories][' + key + '][label]" value="" placeholder="Category Label" class="category-label">' +
                    '<input type="text" name="ftt_settings[event_categories][' + key + '][icon]" value="📁" placeholder="📚" class="category-icon" title="Use an emoji or icon">' +
                    '<a href="#" class="ftt-category-remove" onclick="return fttRemoveCategory(this);">✕ Remove</a>' +
                    '</div>');
                
                $('#ftt-categories-container').append(row);
            });
        });
        
        function fttRemoveCategory(el) {
            if (confirm('Remove this category? Event types using this category will need to be reassigned.')) {
                jQuery(el).closest('.ftt-category-row').remove();
            }
            return false;
        }
        </script>
        <?php
    }
    
    /**
     * Render event types field
     */
    public static function render_event_types_field() {
        $settings = get_option('ftt_settings', array());
        $event_types = $settings['event_types'] ?? self::get_default_event_types();
        $categories = $settings['event_categories'] ?? self::get_default_event_categories();
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        ?>
        <style>
            .ftt-event-type-row { margin-bottom: 15px; display: flex; align-items: center; gap: 10px; padding: 8px; border: 1px solid #e0e0e0; border-radius: 4px; background: #fafafa; }
            .ftt-event-type-row input[type="text"].type-label { width: 200px; }
            .ftt-event-type-row select.type-category { width: 150px; }
            .ftt-event-type-row .wp-picker-container { display: inline-block; }
            .ftt-event-type-key { width: 150px; font-family: monospace; color: #666; font-weight: 500; }
            .ftt-event-type-remove { color: #dc3232; cursor: pointer; text-decoration: none; padding: 0 10px; }
            .ftt-event-type-remove:hover { color: #a00; }
            #ftt-add-event-type { margin-top: 10px; }
        </style>
        <div id="ftt-event-types-container">
            <?php foreach ($event_types as $key => $type) : ?>
                <div class="ftt-event-type-row" data-key="<?php echo esc_attr($key); ?>">
                    <span class="ftt-event-type-key"><?php echo esc_html($key); ?></span>
                    <input type="text" 
                           name="ftt_settings[event_types][<?php echo esc_attr($key); ?>][label]" 
                           value="<?php echo esc_attr($type['label']); ?>" 
                           placeholder="Event Type Label"
                           class="type-label">
                    <select name="ftt_settings[event_types][<?php echo esc_attr($key); ?>][category]" class="type-category">
                        <option value=""><?php esc_html_e('No Category', 'schedule-collaboration-tracking'); ?></option>
                        <?php foreach ($categories as $cat_key => $category) : ?>
                            <option value="<?php echo esc_attr($cat_key); ?>" <?php selected($type['category'] ?? '', $cat_key); ?>>
                                <?php echo esc_html($category['icon'] . ' ' . $category['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" 
                           name="ftt_settings[event_types][<?php echo esc_attr($key); ?>][color]" 
                           value="<?php echo esc_attr($type['color']); ?>" 
                           class="ftt-color-picker">
                    <a href="#" class="ftt-event-type-remove" onclick="return fttRemoveEventType(this);">✕ Remove</a>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="ftt-add-event-type" class="button">+ Add Event Type</button>
        <p class="description"><?php esc_html_e('Customize event types, assign them to categories, and set their calendar colors. Key names should be lowercase with underscores (e.g., camp_weekend).', 'schedule-collaboration-tracking'); ?></p>
        
        <script>
        var fttCategoriesOptions = <?php echo json_encode(array_map(function($key, $cat) {
            return array('key' => $key, 'label' => $cat['icon'] . ' ' . $cat['label']);
        }, array_keys($categories), $categories)); ?>;
        
        jQuery(document).ready(function($) {
            // Initialize color pickers
            $('.ftt-color-picker').wpColorPicker();
            
            // Add new event type
            $('#ftt-add-event-type').on('click', function() {
                var key = prompt('Enter event type key (lowercase with underscores, e.g., "my_event"):');
                if (!key) return;
                
                key = key.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                
                if ($('.ftt-event-type-row[data-key="' + key + '"]').length > 0) {
                    alert('Event type key already exists!');
                    return;
                }
                
                // Build category options
                var categoryOptions = '<option value="">No Category</option>';
                fttCategoriesOptions.forEach(function(cat) {
                    categoryOptions += '<option value="' + cat.key + '">' + cat.label + '</option>';
                });
                
                var row = $('<div class="ftt-event-type-row" data-key="' + key + '">' +
                    '<span class="ftt-event-type-key">' + key + '</span>' +
                    '<input type="text" name="ftt_settings[event_types][' + key + '][label]" value="" placeholder="Event Type Label" class="type-label">' +
                    '<select name="ftt_settings[event_types][' + key + '][category]" class="type-category">' + categoryOptions + '</select>' +
                    '<input type="text" name="ftt_settings[event_types][' + key + '][color]" value="#2196F3" class="ftt-color-picker">' +
                    '<a href="#" class="ftt-event-type-remove" onclick="return fttRemoveEventType(this);">✕ Remove</a>' +
                    '</div>');
                
                $('#ftt-event-types-container').append(row);
                row.find('.ftt-color-picker').wpColorPicker();
            });
        });
        
        function fttRemoveEventType(el) {
            if (confirm('Remove this event type?')) {
                jQuery(el).closest('.ftt-event-type-row').remove();
            }
            return false;
        }
        </script>
        <?php
    }
    
    /**
     * Get default event categories
     */
    public static function get_default_event_categories() {
        return array(
            'education' => array(
                'label' => __('Education', 'schedule-collaboration-tracking'),
                'icon' => '🎓',
            ),
            'sports' => array(
                'label' => __('Sports & Activities', 'schedule-collaboration-tracking'),
                'icon' => '⚽',
            ),
            'arts' => array(
                'label' => __('Arts & Music', 'schedule-collaboration-tracking'),
                'icon' => '🎨',
            ),
            'health' => array(
                'label' => __('Health & Medical', 'schedule-collaboration-tracking'),
                'icon' => '⚕️',
            ),
            'social' => array(
                'label' => __('Social & Family', 'schedule-collaboration-tracking'),
                'icon' => '👨‍👩‍👧‍👦',
            ),
            'transportation' => array(
                'label' => __('Transportation & Travel', 'schedule-collaboration-tracking'),
                'icon' => '✈️',
            ),
            'administrative' => array(
                'label' => __('Administrative', 'schedule-collaboration-tracking'),
                'icon' => '📋',
            ),
            'travel' => array(
                'label' => __('Travel & Vacation', 'schedule-collaboration-tracking'),
                'icon' => '🏖️',
            ),
        );
    }
    
    /**
     * Get default event types
     */
    public static function get_default_event_types() {
        return array(
            // Education
            'school_event' => array(
                'label' => __('School Event', 'schedule-collaboration-tracking'),
                'color' => '#2196F3',
                'category' => 'education',
            ),
            'parent_teacher' => array(
                'label' => __('Parent-Teacher Conference', 'schedule-collaboration-tracking'),
                'color' => '#1976D2',
                'category' => 'education',
            ),
            'exam' => array(
                'label' => __('Exam/Test', 'schedule-collaboration-tracking'),
                'color' => '#1565C0',
                'category' => 'education',
            ),
            'field_trip' => array(
                'label' => __('Field Trip', 'schedule-collaboration-tracking'),
                'color' => '#42A5F5',
                'category' => 'education',
            ),
            'graduation' => array(
                'label' => __('Graduation', 'schedule-collaboration-tracking'),
                'color' => '#64B5F6',
                'category' => 'education',
            ),
            'tutoring' => array(
                'label' => __('Tutoring', 'schedule-collaboration-tracking'),
                'color' => '#1E88E5',
                'category' => 'education',
            ),
            'study_group' => array(
                'label' => __('Study Group', 'schedule-collaboration-tracking'),
                'color' => '#0D47A1',
                'category' => 'education',
            ),
            'science_fair' => array(
                'label' => __('Science Fair', 'schedule-collaboration-tracking'),
                'color' => '#2979FF',
                'category' => 'education',
            ),
            'school_play' => array(
                'label' => __('School Play', 'schedule-collaboration-tracking'),
                'color' => '#448AFF',
                'category' => 'education',
            ),
            'open_house' => array(
                'label' => __('Open House', 'schedule-collaboration-tracking'),
                'color' => '#82B1FF',
                'category' => 'education',
            ),
            
            // Sports
            'sports_practice' => array(
                'label' => __('Sports Practice', 'schedule-collaboration-tracking'),
                'color' => '#4CAF50',
                'category' => 'sports',
            ),
            'sports_game' => array(
                'label' => __('Sports Game', 'schedule-collaboration-tracking'),
                'color' => '#388E3C',
                'category' => 'sports',
            ),
            'tournament' => array(
                'label' => __('Tournament', 'schedule-collaboration-tracking'),
                'color' => '#2E7D32',
                'category' => 'sports',
            ),
            'team_meeting' => array(
                'label' => __('Team Meeting', 'schedule-collaboration-tracking'),
                'color' => '#66BB6A',
                'category' => 'sports',
            ),
            'sports_physical' => array(
                'label' => __('Sports Physical', 'schedule-collaboration-tracking'),
                'color' => '#1B5E20',
                'category' => 'sports',
            ),
            'equipment_fitting' => array(
                'label' => __('Equipment Fitting', 'schedule-collaboration-tracking'),
                'color' => '#43A047',
                'category' => 'sports',
            ),
            'awards_ceremony' => array(
                'label' => __('Awards Ceremony', 'schedule-collaboration-tracking'),
                'color' => '#81C784',
                'category' => 'sports',
            ),
            
            // Arts & Music
            'music_lesson' => array(
                'label' => __('Music Lesson', 'schedule-collaboration-tracking'),
                'color' => '#9C27B0',
                'category' => 'arts',
            ),
            'music_performance' => array(
                'label' => __('Music Performance', 'schedule-collaboration-tracking'),
                'color' => '#7B1FA2',
                'category' => 'arts',
            ),
            'dance_class' => array(
                'label' => __('Dance Class', 'schedule-collaboration-tracking'),
                'color' => '#AB47BC',
                'category' => 'arts',
            ),
            'dance_recital' => array(
                'label' => __('Dance Recital', 'schedule-collaboration-tracking'),
                'color' => '#8E24AA',
                'category' => 'arts',
            ),
            'art_class' => array(
                'label' => __('Art Class', 'schedule-collaboration-tracking'),
                'color' => '#BA68C8',
                'category' => 'arts',
            ),
            'theater_rehearsal' => array(
                'label' => __('Theater Rehearsal', 'schedule-collaboration-tracking'),
                'color' => '#CE93D8',
                'category' => 'arts',
            ),
            'theater_performance' => array(
                'label' => __('Theater Performance', 'schedule-collaboration-tracking'),
                'color' => '#6A1B9A',
                'category' => 'arts',
            ),
            'club_meeting' => array(
                'label' => __('Club Meeting', 'schedule-collaboration-tracking'),
                'color' => '#E1BEE7',
                'category' => 'arts',
            ),
            'art_show' => array(
                'label' => __('Art Show', 'schedule-collaboration-tracking'),
                'color' => '#D81B60',
                'category' => 'arts',
            ),
            'photography_class' => array(
                'label' => __('Photography Class', 'schedule-collaboration-tracking'),
                'color' => '#AD1457',
                'category' => 'arts',
            ),
            'pottery_class' => array(
                'label' => __('Pottery Class', 'schedule-collaboration-tracking'),
                'color' => '#F48FB1',
                'category' => 'arts',
            ),
            'voice_lesson' => array(
                'label' => __('Voice Lesson', 'schedule-collaboration-tracking'),
                'color' => '#880E4F',
                'category' => 'arts',
            ),
            'instrument_rental' => array(
                'label' => __('Instrument Rental', 'schedule-collaboration-tracking'),
                'color' => '#F8BBD0',
                'category' => 'arts',
            ),
            
            // Health
            'doctor_appointment' => array(
                'label' => __('Doctor Appointment', 'schedule-collaboration-tracking'),
                'color' => '#F44336',
                'category' => 'health',
            ),
            'dentist' => array(
                'label' => __('Dentist', 'schedule-collaboration-tracking'),
                'color' => '#E53935',
                'category' => 'health',
            ),
            'orthodontist' => array(
                'label' => __('Orthodontist', 'schedule-collaboration-tracking'),
                'color' => '#D32F2F',
                'category' => 'health',
            ),
            'therapist' => array(
                'label' => __('Therapist/Counselor', 'schedule-collaboration-tracking'),
                'color' => '#EF5350',
                'category' => 'health',
            ),
            'medication_reminder' => array(
                'label' => __('Medication Reminder', 'schedule-collaboration-tracking'),
                'color' => '#FF5252',
                'category' => 'health',
            ),
            'vaccination' => array(
                'label' => __('Vaccination', 'schedule-collaboration-tracking'),
                'color' => '#C62828',
                'category' => 'health',
            ),
            
            // Social
            'birthday_party' => array(
                'label' => __('Birthday Party', 'schedule-collaboration-tracking'),
                'color' => '#FF9800',
                'category' => 'social',
            ),
            'playdate' => array(
                'label' => __('Playdate', 'schedule-collaboration-tracking'),
                'color' => '#FB8C00',
                'category' => 'social',
            ),
            'family_gathering' => array(
                'label' => __('Family Gathering', 'schedule-collaboration-tracking'),
                'color' => '#F57C00',
                'category' => 'social',
            ),
            'sleepover' => array(
                'label' => __('Sleepover', 'schedule-collaboration-tracking'),
                'color' => '#FFB74D',
                'category' => 'social',
            ),
            'school_dance' => array(
                'label' => __('School Dance', 'schedule-collaboration-tracking'),
                'color' => '#EF6C00',
                'category' => 'social',
            ),
            'prom' => array(
                'label' => __('Prom', 'schedule-collaboration-tracking'),
                'color' => '#E65100',
                'category' => 'social',
            ),
            'homecoming' => array(
                'label' => __('Homecoming', 'schedule-collaboration-tracking'),
                'color' => '#FF6F00',
                'category' => 'social',
            ),
            'holiday_party' => array(
                'label' => __('Holiday Party', 'schedule-collaboration-tracking'),
                'color' => '#FF8A65',
                'category' => 'social',
            ),
            'summer_bbq' => array(
                'label' => __('Summer BBQ', 'schedule-collaboration-tracking'),
                'color' => '#FFAB91',
                'category' => 'social',
            ),
            
            // Transportation
            'pickup' => array(
                'label' => __('Pickup', 'schedule-collaboration-tracking'),
                'color' => '#607D8B',
                'category' => 'transportation',
            ),
            'dropoff' => array(
                'label' => __('Drop-off', 'schedule-collaboration-tracking'),
                'color' => '#546E7A',
                'category' => 'transportation',
            ),
            'carpool' => array(
                'label' => __('Carpool', 'schedule-collaboration-tracking'),
                'color' => '#455A64',
                'category' => 'transportation',
            ),
            'bus_schedule' => array(
                'label' => __('Bus Schedule', 'schedule-collaboration-tracking'),
                'color' => '#78909C',
                'category' => 'transportation',
            ),
            'rideshare' => array(
                'label' => __('Rideshare', 'schedule-collaboration-tracking'),
                'color' => '#37474F',
                'category' => 'transportation',
            ),
            'train_subway' => array(
                'label' => __('Train/Subway', 'schedule-collaboration-tracking'),
                'color' => '#90A4AE',
                'category' => 'transportation',
            ),
            
            // Administrative
            'registration_deadline' => array(
                'label' => __('Registration Deadline', 'schedule-collaboration-tracking'),
                'color' => '#795548',
                'category' => 'administrative',
            ),
            'payment_due' => array(
                'label' => __('Payment Due', 'schedule-collaboration-tracking'),
                'color' => '#6D4C41',
                'category' => 'administrative',
            ),
            'forms_due' => array(
                'label' => __('Forms Due', 'schedule-collaboration-tracking'),
                'color' => '#5D4037',
                'category' => 'administrative',
            ),
            'college_visit' => array(
                'label' => __('College Visit', 'schedule-collaboration-tracking'),
                'color' => '#8D6E63',
                'category' => 'administrative',
            ),
            'college_application' => array(
                'label' => __('College Application', 'schedule-collaboration-tracking'),
                'color' => '#A1887F',
                'category' => 'administrative',
            ),
            'insurance_deadline' => array(
                'label' => __('Insurance Deadline', 'schedule-collaboration-tracking'),
                'color' => '#4E342E',
                'category' => 'administrative',
            ),
            'scholarship_application' => array(
                'label' => __('Scholarship Application', 'schedule-collaboration-tracking'),
                'color' => '#BCAAA4',
                'category' => 'administrative',
            ),
            'financial_aid_forms' => array(
                'label' => __('Financial Aid Forms', 'schedule-collaboration-tracking'),
                'color' => '#D7CCC8',
                'category' => 'administrative',
            ),
            
            // Travel
            'travel_day' => array(
                'label' => __('Travel Day', 'schedule-collaboration-tracking'),
                'color' => '#00BCD4',
                'category' => 'travel',
            ),
            'flight_only' => array(
                'label' => __('Flight Only', 'schedule-collaboration-tracking'),
                'color' => '#0097A7',
                'category' => 'travel',
            ),
            
            // Other
            'other' => array(
                'label' => __('Other', 'schedule-collaboration-tracking'),
                'color' => '#9E9E9E',
                'category' => '',
            ),
        );
    }
    
    /**
     * Render calendar section description
     */
    public static function render_calendar_section() {
        echo '<p>' . esc_html__('Allow users to subscribe to the schedule in their calendar apps (iOS Calendar, Google Calendar, Outlook, etc.). Changes to the schedule will automatically sync.', 'schedule-collaboration-tracking') . '</p>';
    }
    
    /**
     * Render enable iCal field
     */
    public static function render_enable_ical_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['enable_ical_feed'] ?? false;
        ?>
        <label>
            <input type="checkbox" name="ftt_settings[enable_ical_feed]" value="1" <?php checked($value, true); ?>>
            <?php esc_html_e('Enable calendar subscription feed', 'schedule-collaboration-tracking'); ?>
        </label>
        <p class="description"><?php esc_html_e('Allow users to subscribe to the schedule in their calendar applications.', 'schedule-collaboration-tracking'); ?></p>
        <?php
        
        if ($value) {
            $feed_url = rest_url('ftt/v1/calendar.ics');
            ?>
            <p><strong><?php esc_html_e('Public Feed URL:', 'schedule-collaboration-tracking'); ?></strong><br>
            <code><?php echo esc_url($feed_url); ?></code>
            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($feed_url); ?>'); this.textContent='Copied!';"><?php esc_html_e('Copy', 'schedule-collaboration-tracking'); ?></button>
            </p>
            <?php
        }
    }
    
    /**
     * Render iCal auth field
     */
    public static function render_ical_auth_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['ical_require_auth'] ?? false;
        $enabled = $settings['enable_ical_feed'] ?? false;
        ?>
        <label>
            <input type="checkbox" name="ftt_settings[ical_require_auth]" value="1" <?php checked($value, true); ?> <?php disabled(!$enabled); ?>>
            <?php esc_html_e('Require authentication token for calendar access', 'schedule-collaboration-tracking'); ?>
        </label>
        <p class="description"><?php esc_html_e('Recommended for private schedules. Users will need a token to subscribe to the calendar.', 'schedule-collaboration-tracking'); ?></p>
        <?php
    }
    
    /**
     * Render calendar tokens field
     */
    public static function render_calendar_tokens_field() {
        $settings = get_option('ftt_settings', array());
        $enabled = $settings['enable_ical_feed'] ?? false;
        $requires_auth = $settings['ical_require_auth'] ?? false;
        
        if (!$enabled || !$requires_auth) {
            echo '<p class="description">' . esc_html__('Enable authentication to manage calendar tokens.', 'schedule-collaboration-tracking') . '</p>';
            return;
        }
        
        $tokens = FTT_ICal::get_calendar_tokens();
        
        ?>
        <div id="ftt-calendar-tokens">
            <?php if (empty($tokens)) : ?>
                <p><?php esc_html_e('No calendar tokens generated yet.', 'schedule-collaboration-tracking'); ?></p>
            <?php else : ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Token', 'schedule-collaboration-tracking'); ?></th>
                            <th><?php esc_html_e('Calendar URL', 'schedule-collaboration-tracking'); ?></th>
                            <th><?php esc_html_e('Actions', 'schedule-collaboration-tracking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tokens as $token) : 
                            $token_url = rest_url('ftt/v1/calendar.ics') . '?token=' . $token;
                        ?>
                            <tr>
                                <td><code><?php echo esc_html(substr($token, 0, 8) . '...'); ?></code></td>
                                <td>
                                    <input type="text" readonly value="<?php echo esc_attr($token_url); ?>" style="width: 100%; max-width: 400px;" onclick="this.select();">
                                    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($token_url); ?>'); this.textContent='Copied!';"><?php esc_html_e('Copy', 'schedule-collaboration-tracking'); ?></button>
                                </td>
                                <td>
                                    <button type="button" class="button button-small" onclick="fttDeleteToken('<?php echo esc_js($token); ?>')"><?php esc_html_e('Delete', 'schedule-collaboration-tracking'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <p style="margin-top: 10px;">
                <button type="button" class="button" id="ftt-generate-token"><?php esc_html_e('Generate New Token', 'schedule-collaboration-tracking'); ?></button>
            </p>
            
            <p class="description"><?php esc_html_e('Each token provides access to subscribe to the calendar. Share these URLs with authorized users only.', 'schedule-collaboration-tracking'); ?></p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#ftt-generate-token').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('Generating...');
                
                $.ajax({
                    url: '<?php echo esc_js(rest_url('ftt/v1/calendar/token')); ?>',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                    },
                    success: function(response) {
                        alert('New token generated! Save this page to keep the token.');
                        location.reload();
                    },
                    error: function() {
                        alert('Error generating token. Please try again.');
                        button.prop('disabled', false).text('<?php esc_js(__('Generate New Token', 'schedule-collaboration-tracking')); ?>');
                    }
                });
            });
        });
        
        function fttDeleteToken(token) {
            if (!confirm('Delete this calendar token? Users with this URL will lose access.')) {
                return;
            }
            
            // For now, we'll handle this via page reload
            // In a full implementation, you'd want a proper REST endpoint
            alert('Token deletion requires a custom implementation. Please remove manually from database if needed.');
        }
        </script>
        <?php
    }
    
    /**
     * Render security section description
     */
    public static function render_security_section() {
        echo '<p>' . esc_html__('Protect your registration and login forms from spam and bots using Google reCAPTCHA v3.', 'schedule-collaboration-tracking') . '</p>';
        echo '<p>' . wp_kses_post(
            sprintf(
                __('Get your free keys at <a href="%s" target="_blank">Google reCAPTCHA Admin Console</a>. Choose <strong>reCAPTCHA v3</strong> and add your domain.', 'schedule-collaboration-tracking'),
                'https://www.google.com/recaptcha/admin/create'
            )
        ) . '</p>';
    }
    
    /**
     * Render enable reCAPTCHA field
     */
    public static function render_enable_recaptcha_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['enable_recaptcha'] ?? false;
        ?>
        <label>
            <input type="checkbox" name="ftt_settings[enable_recaptcha]" value="1" <?php checked($value, true); ?>>
            <?php esc_html_e('Enable Google reCAPTCHA v3 on registration and login forms', 'schedule-collaboration-tracking'); ?>
        </label>
        <p class="description"><?php esc_html_e('Invisible bot protection — no checkbox shown to users. Scores each submission 0.0–1.0; submissions below 0.5 are rejected.', 'schedule-collaboration-tracking'); ?></p>
        <?php
    }
    
    /**
     * Render reCAPTCHA site key field
     */
    public static function render_recaptcha_site_key_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['recaptcha_site_key'] ?? '';
        $enabled = $settings['enable_recaptcha'] ?? false;
        ?>
        <input type="text" 
               name="ftt_settings[recaptcha_site_key]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="6Le..."
               <?php disabled(!$enabled); ?>>
        <p class="description">
            <?php esc_html_e('Your reCAPTCHA v3 Site Key (public). Loaded on the page — safe to expose.', 'schedule-collaboration-tracking'); ?>
        </p>
        <?php
    }
    
    /**
     * Render reCAPTCHA secret key field
     */
    public static function render_recaptcha_secret_key_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['recaptcha_secret_key'] ?? '';
        $enabled = $settings['enable_recaptcha'] ?? false;
        ?>
        <input type="password" 
               name="ftt_settings[recaptcha_secret_key]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="6Le..."
               <?php disabled(!$enabled); ?>>
        <p class="description">
            <?php esc_html_e('Your reCAPTCHA v3 Secret Key (private). Never expose this publicly.', 'schedule-collaboration-tracking'); ?>
        </p>
        <?php
    }
    
    /**
     * Render price tracking section
     */
    
    /**
     * Render SerpAPI key field
     */
    public static function render_serpapi_api_key_field() {
        $settings = get_option('ftt_settings', array());
        $value = isset($settings['serpapi_api_key']) ? $settings['serpapi_api_key'] : '';
        ?>
        <input type="text" 
               name="ftt_settings[serpapi_api_key]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="<?php echo esc_attr__('Enter your SerpAPI Key', 'schedule-collaboration-tracking'); ?>">
        <p class="description">
            <?php _e('For automated flight price checking. Sign up at <a href="https://serpapi.com/users/sign_up" target="_blank">SerpAPI</a>', 'schedule-collaboration-tracking'); ?><br>
            <strong><?php _e('Free:', 'schedule-collaboration-tracking'); ?></strong> <?php _e('100 searches/month', 'schedule-collaboration-tracking'); ?> &nbsp; 
            <strong><?php _e('Paid:', 'schedule-collaboration-tracking'); ?></strong> <?php _e('$75/month for 5,000 searches', 'schedule-collaboration-tracking'); ?>
        </p>
        <?php
    }
    
    /**
     * Render OpenAI API key field
     */
    public static function render_openai_api_key_field() {
        $settings = get_option('ftt_settings', array());
        $value    = $settings['openai_api_key'] ?? '';
        ?>
        <input type="password"
               name="ftt_settings[openai_api_key]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="sk-...">
        <p class="description">
            <?php _e('Used by the AI Event Parser to convert natural-language prompts into structured events. Get a key at <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>.', 'schedule-collaboration-tracking'); ?><br>
            <?php esc_html_e('Model: gpt-4o-mini (~$0.002 per 1,000 prompts).', 'schedule-collaboration-tracking'); ?>
        </p>
        <?php
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get active tab
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        
        // Handle messages
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'ftt_messages',
                'ftt_message',
                __('Settings saved.', 'schedule-collaboration-tracking'),
                'updated'
            );
        }
        
        settings_errors('ftt_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?post_type=ftt_event&page=ftt-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span> <?php _e('General', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="?post_type=ftt_event&page=ftt-settings&tab=api" class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-network"></span> <?php _e('API Keys', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="?post_type=ftt_event&page=ftt-settings&tab=events" class="nav-tab <?php echo $active_tab === 'events' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-calendar-alt"></span> <?php _e('Events', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="?post_type=ftt_event&page=ftt-settings&tab=calendar" class="nav-tab <?php echo $active_tab === 'calendar' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-rss"></span> <?php _e('Calendar', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="?post_type=ftt_event&page=ftt-settings&tab=security" class="nav-tab <?php echo $active_tab === 'security' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-shield"></span> <?php _e('Security', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="?post_type=ftt_event&page=ftt-settings&tab=billing-settings" class="nav-tab <?php echo $active_tab === 'billing-settings' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> <?php _e('Billing Settings', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="?post_type=ftt_event&page=ftt-settings&tab=billing" class="nav-tab <?php echo $active_tab === 'billing' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-chart-bar"></span> <?php _e('Billing Dashboard', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="?post_type=ftt_event&page=ftt-settings&tab=seo" class="nav-tab <?php echo $active_tab === 'seo' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-search"></span> <?php _e('SEO', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="?post_type=ftt_event&page=ftt-settings&tab=policy-comms" class="nav-tab <?php echo $active_tab === 'policy-comms' ? 'nav-tab-active' : ''; ?>">
                                <span class="dashicons dashicons-shield"></span> <?php _e('Policy &amp; Communications', 'schedule-collaboration-tracking'); ?>
                                </a>
                <a href="?post_type=ftt_event&page=ftt-settings&tab=newsletter" class="nav-tab <?php echo $active_tab === 'newsletter' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-email-alt"></span> <?php _e('Newsletter', 'schedule-collaboration-tracking'); ?>
                </a>
            </h2>

            <form method="post" action="options.php">
                <?php
                settings_fields('ftt_settings_group');
                
                // Display the appropriate settings page
                switch ($active_tab) {
                    case 'api':
                        do_settings_sections('ftt-settings-api');
                        break;
                    case 'events':
                        do_settings_sections('ftt-settings-events');
                        break;
                    case 'calendar':
                        do_settings_sections('ftt-settings-calendar');
                        break;
                    case 'security':
                        do_settings_sections('ftt-settings-security');
                        break;
                    case 'billing-settings':
                        // Stripe settings has its own form — close ours first.
                        // render_embedded() strips the nested <div class="wrap"> and <h1>
                        // so the content sits cleanly inside our outer wrap.
                        echo '</form>';
                        if ( class_exists('FTT_Stripe_Settings') ) {
                            self::render_embedded( array( 'FTT_Stripe_Settings', 'render_settings_page' ) );
                        } else {
                            echo '<p>' . esc_html__('Stripe library not available.', 'schedule-collaboration-tracking') . '</p>';
                        }
                        echo '<form style="display:none">';
                        break;
                    case 'billing':
                        echo '</form>';
                        if ( class_exists('FTT_Admin_Billing_Dashboard') ) {
                            self::render_embedded( array( 'FTT_Admin_Billing_Dashboard', 'render_dashboard' ) );
                        } else {
                            echo '<p>' . esc_html__('Stripe library not available.', 'schedule-collaboration-tracking') . '</p>';
                        }
                        echo '<form style="display:none">';
                        break;
                    case 'seo':
                        echo '</form>';
                        if ( class_exists('FTT_SEO') ) {
                            self::render_embedded( array( 'FTT_SEO', 'render_settings_page' ) );
                        }
                        echo '<form style="display:none">';
                        break;
                    case 'policy-comms':
                        echo '</form>';
                        if ( class_exists('FTT_Email_Templates') ) {
                            self::render_embedded( array( 'FTT_Email_Templates', 'render_settings_page' ) );
                        }
                        if ( class_exists('FTT_Cookie_Consent') ) {
                            FTT_Cookie_Consent::render_settings_section();
                        }
                        echo '<form style="display:none">';
                        break;
                    case 'newsletter':
                        do_settings_sections('ftt-settings-newsletter');
                        break;
                    case 'general':
                    default:
                        do_settings_sections('ftt-settings-general');
                        break;
                }
                
                if ( ! in_array( $active_tab, array( 'billing-settings', 'billing', 'seo', 'policy-comms' ) ) ) { // phpcs:ignore
                    submit_button(__('Save Settings', 'schedule-collaboration-tracking'));
                }
                ?>
            </form>
            
            <?php if ($active_tab === 'api'): ?>
            <script>
            jQuery(document).ready(function($) {
                var providerSelect = $('#ftt_geocoding_provider');
                var mapboxInput = $('input[name="ftt_settings[mapbox_api_key]"]');
                var googleInput = $('input[name="ftt_settings[google_places_api_key]"]');
                
                function updateApiKeyFields() {
                    var provider = providerSelect.val();
                    
                    // Enable/disable Mapbox field
                    if (provider === 'mapbox') {
                        mapboxInput.prop('disabled', false);
                    } else {
                        mapboxInput.prop('disabled', true);
                    }
                    
                    // Enable/disable Google Places field
                    if (provider === 'google') {
                        googleInput.prop('disabled', false);
                    } else {
                        googleInput.prop('disabled', true);
                    }
                }
                
                // Update on page load
                updateApiKeyFields();
                
                // Update on change
                providerSelect.on('change', updateApiKeyFields);
            });
            </script>
            <?php endif; ?>
            
            <?php if ($active_tab === 'general'): ?>
            <hr style="margin-top: 30px;">
            <h2><?php esc_html_e('Shortcodes', 'schedule-collaboration-tracking'); ?></h2>
            <p><?php esc_html_e('Use these shortcodes to display different views on your pages:', 'schedule-collaboration-tracking'); ?></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><code>[ftt_login]</code> - <?php esc_html_e('Display the custom login form', 'schedule-collaboration-tracking'); ?></li>
                <li><code>[ftt_calendar]</code> - <?php esc_html_e('Display the calendar view', 'schedule-collaboration-tracking'); ?></li>
                <li><code>[ftt_event_form]</code> - <?php esc_html_e('Display the event add/edit form (admin only)', 'schedule-collaboration-tracking'); ?></li>
                <li><code>[ftt_dashboard]</code> - <?php esc_html_e('Display the dashboard with flights and travel overview', 'schedule-collaboration-tracking'); ?></li>
                <li><code>[ftt_event_list]</code> - <?php esc_html_e('Display a simple list of upcoming events', 'schedule-collaboration-tracking'); ?></li>
            </ul>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialize
FTT_Settings::init();
