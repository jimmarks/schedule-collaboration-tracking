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

class SRT_Settings {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_settings_page'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }
    
    /**
     * Add settings page to admin menu
     */
    public static function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=srt_event',
            __('Schedule Settings', 'schedule-collaboration-tracking'),
            __('Settings', 'schedule-collaboration-tracking'),
            'manage_options',
            'srt-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting(
            'srt_settings_group',
            'srt_settings',
            array(
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
            )
        );
        
        // TAB 1: GENERAL
        add_settings_section(
            'srt_general_section',
            __('General Settings', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_general_section'),
            'srt-settings-general'
        );
        
        add_settings_field(
            'require_login',
            __('Require Login', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_require_login_field'),
            'srt-settings-general',
            'srt_general_section'
        );
        
        add_settings_field(
            'default_home_airport',
            __('Default Home Airport', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_default_airport_field'),
            'srt-settings-general',
            'srt_general_section'
        );
        
        add_settings_field(
            'default_timezone',
            __('Default Timezone', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_default_timezone_field'),
            'srt-settings-general',
            'srt_general_section'
        );
        
        add_settings_field(
            'enable_login_menu',
            __('Login/Logout Menu', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_login_menu_field'),
            'srt-settings-general',
            'srt_general_section'
        );
        
        add_settings_field(
            'login_menu_mode',
            __('Menu Display Mode', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_login_menu_mode_field'),
            'srt-settings-general',
            'srt_general_section'
        );
        
        add_settings_field(
            'notification_from_email',
            __('Notification From Email', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_notification_from_email_field'),
            'srt-settings-general',
            'srt_general_section'
        );
        
        add_settings_field(
            'notification_from_name',
            __('Notification From Name', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_notification_from_name_field'),
            'srt-settings-general',
            'srt_general_section'
        );
        
        // TAB 2: API KEYS
        add_settings_section(
            'srt_api_section',
            __('API Configuration', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_api_section'),
            'srt-settings-api'
        );
        
        add_settings_field(
            'geocoding_provider',
            __('Address Autocomplete Provider', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_geocoding_provider_field'),
            'srt-settings-api',
            'srt_api_section'
        );
        
        add_settings_field(
            'mapbox_api_key',
            __('Mapbox API Key', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_mapbox_api_key_field'),
            'srt-settings-api',
            'srt_api_section'
        );
        
        add_settings_field(
            'google_places_api_key',
            __('Google Places API Key', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_google_places_api_key_field'),
            'srt-settings-api',
            'srt_api_section'
        );
        
        add_settings_field(
            'serpapi_api_key',
            __('SerpAPI Key (Flight Pricing)', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_serpapi_api_key_field'),
            'srt-settings-api',
            'srt_api_section'
        );
        
        // TAB 3: EVENTS
        add_settings_section(
            'srt_event_types_section',
            __('Event Types', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_event_types_section'),
            'srt-settings-events'
        );
        
        add_settings_field(
            'event_types',
            __('Custom Event Types', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_event_types_field'),
            'srt-settings-events',
            'srt_event_types_section'
        );
        
        // TAB 4: CALENDAR
        add_settings_section(
            'srt_calendar_section',
            __('Calendar Subscription', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_calendar_section'),
            'srt-settings-calendar'
        );
        
        add_settings_field(
            'enable_ical_feed',
            __('Enable iCal Feed', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_enable_ical_field'),
            'srt-settings-calendar',
            'srt_calendar_section'
        );
        
        add_settings_field(
            'ical_require_auth',
            __('Require Authentication', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_ical_auth_field'),
            'srt-settings-calendar',
            'srt_calendar_section'
        );
        
        add_settings_field(
            'calendar_tokens',
            __('Calendar Tokens', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_calendar_tokens_field'),
            'srt-settings',
            'srt_calendar_section'
        );
    }
    
    /**
     * Sanitize settings
     */
    public static function sanitize_settings($input) {
        // Get existing settings and merge with new input
        $existing = get_option('srt_settings', array());
        $sanitized = $existing; // Start with existing settings
        
        // Only update fields that are present in input (current tab)
        if (isset($input['require_login'])) {
            $sanitized['require_login'] = (bool) $input['require_login'];
        }
        if (isset($input['default_home_airport'])) {
            $sanitized['default_home_airport'] = strtoupper(sanitize_text_field($input['default_home_airport']));
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
        if (isset($input['notification_from_email'])) {
            $sanitized['notification_from_email'] = sanitize_email($input['notification_from_email']);
        }
        if (isset($input['notification_from_name'])) {
            $sanitized['notification_from_name'] = sanitize_text_field($input['notification_from_name']);
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
        
        // Sanitize event types
        if (isset($input['event_types']) && is_array($input['event_types'])) {
            $sanitized['event_types'] = array();
            foreach ($input['event_types'] as $key => $type) {
                if (!empty($type['label'])) {
                    $sanitized_key = sanitize_key($key);
                    $sanitized['event_types'][$sanitized_key] = array(
                        'label' => sanitize_text_field($type['label']),
                        'color' => sanitize_hex_color($type['color'] ?? '#2196F3'),
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
     * Render event types section description
     */
    public static function render_event_types_section() {
        echo '<p>' . esc_html__('Customize the event types used in your schedule. Each type can have a custom label and color for the calendar.', 'schedule-collaboration-tracking') . '</p>';
    }
    
    /**
     * Render require login field
     */
    public static function render_require_login_field() {
        $settings = get_option('srt_settings', array());
        $value = $settings['require_login'] ?? false;
        ?>
        <label>
            <input type="checkbox" name="srt_settings[require_login]" value="1" <?php checked($value, true); ?>>
            <?php esc_html_e('Require users to be logged in to view the schedule', 'schedule-collaboration-tracking'); ?>
        </label>
        <?php
    }
    
    /**
     * Render default airport field
     */
    public static function render_default_airport_field() {
        $settings = get_option('srt_settings', array());
        $value = $settings['default_home_airport'] ?? '';
        ?>
        <input type="text" name="srt_settings[default_home_airport]" value="<?php echo esc_attr($value); ?>" class="regular-text" maxlength="3" placeholder="ORD">
        <p class="description"><?php esc_html_e('IATA airport code (e.g., ORD for Chicago O\'Hare). Used as default for travel forms.', 'schedule-collaboration-tracking'); ?></p>
        <?php
    }
    
    /**
     * Render default timezone field
     */
    public static function render_default_timezone_field() {
        $settings = get_option('srt_settings', array());
        $value = $settings['default_timezone'] ?? wp_timezone_string();
        $timezones = timezone_identifiers_list();
        ?>
        <select name="srt_settings[default_timezone]" class="regular-text">
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
        $settings = get_option('srt_settings', array());
        $value = $settings['enable_login_menu'] ?? false;
        ?>
        <label>
            <input type="checkbox" name="srt_settings[enable_login_menu]" value="1" <?php checked($value, true); ?>>
            <?php esc_html_e('Enable Login/Logout menu items', 'schedule-collaboration-tracking'); ?>
        </label>
        <p class="description"><?php esc_html_e('Add login and/or logout links to your WordPress menus. Use the menu item "Schedule Login/Logout" in Appearance → Menus.', 'schedule-collaboration-tracking'); ?></p>
        <?php
    }
    
    /**
     * Render login menu mode field
     */
    public static function render_login_menu_mode_field() {
        $settings = get_option('srt_settings', array());
        $value = $settings['login_menu_mode'] ?? 'both';
        $enabled = $settings['enable_login_menu'] ?? false;
        ?>
        <select name="srt_settings[login_menu_mode]" <?php disabled(!$enabled); ?>>
            <option value="login_only" <?php selected($value, 'login_only'); ?>><?php esc_html_e('Login Only (hide when logged in)', 'schedule-collaboration-tracking'); ?></option>
            <option value="both" <?php selected($value, 'both'); ?>><?php esc_html_e('Login/Logout (show both)', 'schedule-collaboration-tracking'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Choose whether to show only login link or both login/logout links in menus.', 'schedule-collaboration-tracking'); ?></p>
        <?php
    }
    
    /**
     * Render notification from email field
     */
    public static function render_notification_from_email_field() {
        $settings = get_option('srt_settings', array());
        $value = $settings['notification_from_email'] ?? '';
        $default = get_option('admin_email');
        ?>
        <input type="email" name="srt_settings[notification_from_email]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php echo esc_attr($default); ?>">
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
        $settings = get_option('srt_settings', array());
        $value = $settings['notification_from_name'] ?? '';
        $default = get_bloginfo('name');
        ?>
        <input type="text" name="srt_settings[notification_from_name]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php echo esc_attr($default); ?>">
        <p class="description">
            <?php esc_html_e('Name to display as the email sender. Leave blank to use site name.', 'schedule-collaboration-tracking'); ?>
        </p>
        <?php
    }
    
    /**
     * Render geocoding provider field
     */
    public static function render_geocoding_provider_field() {
        $settings = get_option('srt_settings', array());
        $value = $settings['geocoding_provider'] ?? 'none';
        ?>
        <select name="srt_settings[geocoding_provider]" id="srt_geocoding_provider">
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
        $settings = get_option('srt_settings', array());
        $value = $settings['mapbox_api_key'] ?? '';
        $provider = $settings['geocoding_provider'] ?? 'none';
        ?>
        <input type="text" name="srt_settings[mapbox_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="pk.eyJ1..." <?php disabled($provider !== 'mapbox'); ?>>
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
        $settings = get_option('srt_settings', array());
        $value = $settings['google_places_api_key'] ?? '';
        $provider = $settings['geocoding_provider'] ?? 'none';
        ?>
        <input type="text" name="srt_settings[google_places_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="AIza..." <?php disabled($provider !== 'google'); ?>>
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
     * Render event types field
     */
    public static function render_event_types_field() {
        $settings = get_option('srt_settings', array());
        $event_types = $settings['event_types'] ?? self::get_default_event_types();
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        ?>
        <style>
            .srt-event-type-row { margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
            .srt-event-type-row input[type="text"] { width: 200px; }
            .srt-event-type-row .wp-picker-container { display: inline-block; }
            .srt-event-type-key { width: 150px; font-family: monospace; color: #666; }
            .srt-event-type-remove { color: #dc3232; cursor: pointer; text-decoration: none; }
            .srt-event-type-remove:hover { color: #a00; }
            #srt-add-event-type { margin-top: 10px; }
        </style>
        <div id="srt-event-types-container">
            <?php foreach ($event_types as $key => $type) : ?>
                <div class="srt-event-type-row" data-key="<?php echo esc_attr($key); ?>">
                    <span class="srt-event-type-key"><?php echo esc_html($key); ?></span>
                    <input type="text" 
                           name="srt_settings[event_types][<?php echo esc_attr($key); ?>][label]" 
                           value="<?php echo esc_attr($type['label']); ?>" 
                           placeholder="Event Type Label">
                    <input type="text" 
                           name="srt_settings[event_types][<?php echo esc_attr($key); ?>][color]" 
                           value="<?php echo esc_attr($type['color']); ?>" 
                           class="srt-color-picker">
                    <a href="#" class="srt-event-type-remove" onclick="return srtRemoveEventType(this);">✕ Remove</a>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="srt-add-event-type" class="button">+ Add Event Type</button>
        <p class="description"><?php esc_html_e('Customize event types and their calendar colors. Key names should be lowercase with underscores (e.g., camp_weekend).', 'schedule-collaboration-tracking'); ?></p>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize color pickers
            $('.srt-color-picker').wpColorPicker();
            
            // Add new event type
            $('#srt-add-event-type').on('click', function() {
                var key = prompt('Enter event type key (lowercase with underscores, e.g., "my_event"):');
                if (!key) return;
                
                key = key.toLowerCase().replace(/[^a-z0-9_]/g, '_');
                
                if ($('.srt-event-type-row[data-key="' + key + '"]').length > 0) {
                    alert('Event type key already exists!');
                    return;
                }
                
                var row = $('<div class="srt-event-type-row" data-key="' + key + '">' +
                    '<span class="srt-event-type-key">' + key + '</span>' +
                    '<input type="text" name="srt_settings[event_types][' + key + '][label]" value="" placeholder="Event Type Label">' +
                    '<input type="text" name="srt_settings[event_types][' + key + '][color]" value="#2196F3" class="srt-color-picker">' +
                    '<a href="#" class="srt-event-type-remove" onclick="return srtRemoveEventType(this);">✕ Remove</a>' +
                    '</div>');
                
                $('#srt-event-types-container').append(row);
                row.find('.srt-color-picker').wpColorPicker();
            });
        });
        
        function srtRemoveEventType(el) {
            if (confirm('Remove this event type?')) {
                jQuery(el).closest('.srt-event-type-row').remove();
            }
            return false;
        }
        </script>
        <?php
    }
    
    /**
     * Get default event types
     */
    public static function get_default_event_types() {
        return array(
            // Activity-specific events
            'move_in' => array(
                'label' => __('Move In', 'schedule-collaboration-tracking'),
                'color' => '#4CAF50',
            ),
            'move_out' => array(
                'label' => __('Move Out', 'schedule-collaboration-tracking'),
                'color' => '#F44336',
            ),
            'camp_weekend' => array(
                'label' => __('Camp Weekend', 'schedule-collaboration-tracking'),
                'color' => '#2196F3',
            ),
            'rehearsal_block' => array(
                'label' => __('Rehearsal Block', 'schedule-collaboration-tracking'),
                'color' => '#9C27B0',
            ),
            'travel_day' => array(
                'label' => __('Travel Day', 'schedule-collaboration-tracking'),
                'color' => '#FF9800',
            ),
            'performance_day' => array(
                'label' => __('Performance Day', 'schedule-collaboration-tracking'),
                'color' => '#E91E63',
            ),
            'housing_checkin' => array(
                'label' => __('Housing Check-In', 'schedule-collaboration-tracking'),
                'color' => '#00BCD4',
            ),
            
            // General summer events
            'summer_camp' => array(
                'label' => __('Summer Camp', 'schedule-collaboration-tracking'),
                'color' => '#4DB6AC',
            ),
            'sports_camp' => array(
                'label' => __('Sports Camp', 'schedule-collaboration-tracking'),
                'color' => '#66BB6A',
            ),
            'music_camp' => array(
                'label' => __('Music Camp', 'schedule-collaboration-tracking'),
                'color' => '#AB47BC',
            ),
            'college_visit' => array(
                'label' => __('College Visit', 'schedule-collaboration-tracking'),
                'color' => '#5C6BC0',
            ),
            'college_orientation' => array(
                'label' => __('College Orientation', 'schedule-collaboration-tracking'),
                'color' => '#42A5F5',
            ),
            'internship' => array(
                'label' => __('Internship', 'schedule-collaboration-tracking'),
                'color' => '#26A69A',
            ),
            'volunteer_work' => array(
                'label' => __('Volunteer Work', 'schedule-collaboration-tracking'),
                'color' => '#66BB6A',
            ),
            'family_vacation' => array(
                'label' => __('Family Vacation', 'schedule-collaboration-tracking'),
                'color' => '#29B6F6',
            ),
            'family_reunion' => array(
                'label' => __('Family Reunion', 'schedule-collaboration-tracking'),
                'color' => '#FFA726',
            ),
            'birthday' => array(
                'label' => __('Birthday', 'schedule-collaboration-tracking'),
                'color' => '#FF7043',
            ),
            'graduation' => array(
                'label' => __('Graduation', 'schedule-collaboration-tracking'),
                'color' => '#7E57C2',
            ),
            
            // Administrative
            'medical' => array(
                'label' => __('Medical Appointment', 'schedule-collaboration-tracking'),
                'color' => '#EF5350',
            ),
            'uniform_fitting' => array(
                'label' => __('Uniform/Fitting', 'schedule-collaboration-tracking'),
                'color' => '#607D8B',
            ),
            'admin_deadline' => array(
                'label' => __('Deadline', 'schedule-collaboration-tracking'),
                'color' => '#F44336',
            ),
            'meeting' => array(
                'label' => __('Meeting', 'schedule-collaboration-tracking'),
                'color' => '#78909C',
            ),
            'other' => array(
                'label' => __('Other', 'schedule-collaboration-tracking'),
                'color' => '#9E9E9E',
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
        $settings = get_option('srt_settings', array());
        $value = $settings['enable_ical_feed'] ?? false;
        ?>
        <label>
            <input type="checkbox" name="srt_settings[enable_ical_feed]" value="1" <?php checked($value, true); ?>>
            <?php esc_html_e('Enable calendar subscription feed', 'schedule-collaboration-tracking'); ?>
        </label>
        <p class="description"><?php esc_html_e('Allow users to subscribe to the schedule in their calendar applications.', 'schedule-collaboration-tracking'); ?></p>
        <?php
        
        if ($value) {
            $feed_url = rest_url('srt/v1/calendar.ics');
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
        $settings = get_option('srt_settings', array());
        $value = $settings['ical_require_auth'] ?? false;
        $enabled = $settings['enable_ical_feed'] ?? false;
        ?>
        <label>
            <input type="checkbox" name="srt_settings[ical_require_auth]" value="1" <?php checked($value, true); ?> <?php disabled(!$enabled); ?>>
            <?php esc_html_e('Require authentication token for calendar access', 'schedule-collaboration-tracking'); ?>
        </label>
        <p class="description"><?php esc_html_e('Recommended for private schedules. Users will need a token to subscribe to the calendar.', 'schedule-collaboration-tracking'); ?></p>
        <?php
    }
    
    /**
     * Render calendar tokens field
     */
    public static function render_calendar_tokens_field() {
        $settings = get_option('srt_settings', array());
        $enabled = $settings['enable_ical_feed'] ?? false;
        $requires_auth = $settings['ical_require_auth'] ?? false;
        
        if (!$enabled || !$requires_auth) {
            echo '<p class="description">' . esc_html__('Enable authentication to manage calendar tokens.', 'schedule-collaboration-tracking') . '</p>';
            return;
        }
        
        $tokens = SRT_ICal::get_calendar_tokens();
        
        ?>
        <div id="srt-calendar-tokens">
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
                            $token_url = rest_url('srt/v1/calendar.ics') . '?token=' . $token;
                        ?>
                            <tr>
                                <td><code><?php echo esc_html(substr($token, 0, 8) . '...'); ?></code></td>
                                <td>
                                    <input type="text" readonly value="<?php echo esc_attr($token_url); ?>" style="width: 100%; max-width: 400px;" onclick="this.select();">
                                    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($token_url); ?>'); this.textContent='Copied!';"><?php esc_html_e('Copy', 'schedule-collaboration-tracking'); ?></button>
                                </td>
                                <td>
                                    <button type="button" class="button button-small" onclick="srtDeleteToken('<?php echo esc_js($token); ?>')"><?php esc_html_e('Delete', 'schedule-collaboration-tracking'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <p style="margin-top: 10px;">
                <button type="button" class="button" id="srt-generate-token"><?php esc_html_e('Generate New Token', 'schedule-collaboration-tracking'); ?></button>
            </p>
            
            <p class="description"><?php esc_html_e('Each token provides access to subscribe to the calendar. Share these URLs with authorized users only.', 'schedule-collaboration-tracking'); ?></p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#srt-generate-token').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('Generating...');
                
                $.ajax({
                    url: '<?php echo esc_js(rest_url('srt/v1/calendar/token')); ?>',
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
        
        function srtDeleteToken(token) {
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
     * Render price tracking section
     */
    
    /**
     * Render SerpAPI key field
     */
    public static function render_serpapi_api_key_field() {
        $settings = get_option('srt_settings', array());
        $value = isset($settings['serpapi_api_key']) ? $settings['serpapi_api_key'] : '';
        ?>
        <input type="text" 
               name="srt_settings[serpapi_api_key]" 
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
                'srt_messages',
                'srt_message',
                __('Settings saved.', 'schedule-collaboration-tracking'),
                'updated'
            );
        }
        
        settings_errors('srt_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?post_type=srt_event&page=srt-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span> <?php _e('General', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="?post_type=srt_event&page=srt-settings&tab=api" class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-network"></span> <?php _e('API Keys', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="?post_type=srt_event&page=srt-settings&tab=events" class="nav-tab <?php echo $active_tab === 'events' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-calendar-alt"></span> <?php _e('Events', 'schedule-collaboration-tracking'); ?>
                </a>
                <a href="?post_type=srt_event&page=srt-settings&tab=calendar" class="nav-tab <?php echo $active_tab === 'calendar' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-rss"></span> <?php _e('Calendar', 'schedule-collaboration-tracking'); ?>
                </a>
            </h2>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('srt_settings_group');
                
                // Display the appropriate settings page
                switch ($active_tab) {
                    case 'api':
                        do_settings_sections('srt-settings-api');
                        break;
                    case 'events':
                        do_settings_sections('srt-settings-events');
                        break;
                    case 'calendar':
                        do_settings_sections('srt-settings-calendar');
                        break;
                    case 'general':
                    default:
                        do_settings_sections('srt-settings-general');
                        break;
                }
                
                submit_button(__('Save Settings', 'schedule-collaboration-tracking'));
                ?>
            </form>
            
            <?php if ($active_tab === 'api'): ?>
            <script>
            jQuery(document).ready(function($) {
                var providerSelect = $('#srt_geocoding_provider');
                var mapboxInput = $('input[name="srt_settings[mapbox_api_key]"]');
                var googleInput = $('input[name="srt_settings[google_places_api_key]"]');
                
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
                <li><code>[srt_login]</code> - <?php esc_html_e('Display the custom login form', 'schedule-collaboration-tracking'); ?></li>
                <li><code>[srt_calendar]</code> - <?php esc_html_e('Display the calendar view', 'schedule-collaboration-tracking'); ?></li>
                <li><code>[srt_event_form]</code> - <?php esc_html_e('Display the event add/edit form (admin only)', 'schedule-collaboration-tracking'); ?></li>
                <li><code>[srt_dashboard]</code> - <?php esc_html_e('Display the dashboard with flights and travel overview', 'schedule-collaboration-tracking'); ?></li>
                <li><code>[srt_event_list]</code> - <?php esc_html_e('Display a simple list of upcoming events', 'schedule-collaboration-tracking'); ?></li>
            </ul>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialize
SRT_Settings::init();
