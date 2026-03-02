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
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }
    
    /**
     * Add settings page to admin menu
     */
    public static function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=ftt_event',
            __('Schedule Settings', 'schedule-collaboration-tracking'),
            __('Settings', 'schedule-collaboration-tracking'),
            'manage_options',
            'ftt-settings',
            array(__CLASS__, 'render_settings_page')
        );
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
        
        // TAB 5: SECURITY
        add_settings_section(
            'ftt_security_section',
            __('Security & Spam Protection', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_security_section'),
            'ftt-settings-security'
        );
        
        add_settings_field(
            'enable_hcaptcha',
            __('Enable hCaptcha', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_enable_hcaptcha_field'),
            'ftt-settings-security',
            'ftt_security_section'
        );
        
        add_settings_field(
            'hcaptcha_site_key',
            __('hCaptcha Site Key', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_hcaptcha_site_key_field'),
            'ftt-settings-security',
            'ftt_security_section'
        );
        
        add_settings_field(
            'hcaptcha_secret_key',
            __('hCaptcha Secret Key', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_hcaptcha_secret_key_field'),
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
        
        // Sanitize hCaptcha settings
        if (isset($input['enable_hcaptcha'])) {
            $sanitized['enable_hcaptcha'] = (bool) $input['enable_hcaptcha'];
        }
        if (isset($input['hcaptcha_site_key'])) {
            $sanitized['hcaptcha_site_key'] = sanitize_text_field($input['hcaptcha_site_key']);
        }
        if (isset($input['hcaptcha_secret_key'])) {
            $sanitized['hcaptcha_secret_key'] = sanitize_text_field($input['hcaptcha_secret_key']);
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
            // Education category
            'graduation' => array(
                'label' => __('Graduation', 'schedule-collaboration-tracking'),
                'color' => '#7E57C2',
                'category' => 'education',
            ),
            'college_visit' => array(
                'label' => __('College Visit', 'schedule-collaboration-tracking'),
                'color' => '#5C6BC0',
                'category' => 'education',
            ),
            'college_orientation' => array(
                'label' => __('College Orientation', 'schedule-collaboration-tracking'),
                'color' => '#42A5F5',
                'category' => 'education',
            ),
            'internship' => array(
                'label' => __('Internship', 'schedule-collaboration-tracking'),
                'color' => '#26A69A',
                'category' => 'education',
            ),
            'volunteer_work' => array(
                'label' => __('Volunteer Work', 'schedule-collaboration-tracking'),
                'color' => '#66BB6A',
                'category' => 'education',
            ),
            
            // Sports & Activities category
            'camp_weekend' => array(
                'label' => __('Camp Weekend', 'schedule-collaboration-tracking'),
                'color' => '#2196F3',
                'category' => 'sports',
            ),
            'sports_camp' => array(
                'label' => __('Sports Camp', 'schedule-collaboration-tracking'),
                'color' => '#66BB6A',
                'category' => 'sports',
            ),
            'summer_camp' => array(
                'label' => __('Summer Camp', 'schedule-collaboration-tracking'),
                'color' => '#4DB6AC',
                'category' => 'sports',
            ),
            
            // Arts & Music category
            'rehearsal_block' => array(
                'label' => __('Rehearsal Block', 'schedule-collaboration-tracking'),
                'color' => '#9C27B0',
                'category' => 'arts',
            ),
            'performance_day' => array(
                'label' => __('Performance Day', 'schedule-collaboration-tracking'),
                'color' => '#E91E63',
                'category' => 'arts',
            ),
            'music_camp' => array(
                'label' => __('Music Camp', 'schedule-collaboration-tracking'),
                'color' => '#AB47BC',
                'category' => 'arts',
            ),
            
            // Health & Medical category
            'medical' => array(
                'label' => __('Medical Appointment', 'schedule-collaboration-tracking'),
                'color' => '#EF5350',
                'category' => 'health',
            ),
            
            // Social & Family category
            'family_vacation' => array(
                'label' => __('Family Vacation', 'schedule-collaboration-tracking'),
                'color' => '#29B6F6',
                'category' => 'social',
            ),
            'family_reunion' => array(
                'label' => __('Family Reunion', 'schedule-collaboration-tracking'),
                'color' => '#FFA726',
                'category' => 'social',
            ),
            'birthday' => array(
                'label' => __('Birthday', 'schedule-collaboration-tracking'),
                'color' => '#FF7043',
                'category' => 'social',
            ),
            
            // Transportation & Travel category
            'move_in' => array(
                'label' => __('Move In', 'schedule-collaboration-tracking'),
                'color' => '#4CAF50',
                'category' => 'transportation',
            ),
            'move_out' => array(
                'label' => __('Move Out', 'schedule-collaboration-tracking'),
                'color' => '#F44336',
                'category' => 'transportation',
            ),
            'travel_day' => array(
                'label' => __('Travel Day', 'schedule-collaboration-tracking'),
                'color' => '#FF9800',
                'category' => 'transportation',
            ),
            'housing_checkin' => array(
                'label' => __('Housing Check-In', 'schedule-collaboration-tracking'),
                'color' => '#00BCD4',
                'category' => 'transportation',
            ),
            
            // Administrative category
            'uniform_fitting' => array(
                'label' => __('Uniform/Fitting', 'schedule-collaboration-tracking'),
                'color' => '#607D8B',
                'category' => 'administrative',
            ),
            'admin_deadline' => array(
                'label' => __('Deadline', 'schedule-collaboration-tracking'),
                'color' => '#F44336',
                'category' => 'administrative',
            ),
            'meeting' => array(
                'label' => __('Meeting', 'schedule-collaboration-tracking'),
                'color' => '#78909C',
                'category' => 'administrative',
            ),
            'other' => array(
                'label' => __('Other', 'schedule-collaboration-tracking'),
                'color' => '#9E9E9E',
                'category' => 'administrative',
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
        echo '<p>' . esc_html__('Protect your registration and login forms from spam and bots using hCaptcha.', 'schedule-collaboration-tracking') . '</p>';
        echo '<p>' . wp_kses_post(
            sprintf(
                __('Get your free hCaptcha keys at <a href="%s" target="_blank">hCaptcha.com</a>. Free tier includes unlimited requests.', 'schedule-collaboration-tracking'),
                'https://dashboard.hcaptcha.com/signup'
            )
        ) . '</p>';
    }
    
    /**
     * Render enable hCaptcha field
     */
    public static function render_enable_hcaptcha_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['enable_hcaptcha'] ?? false;
        ?>
        <label>
            <input type="checkbox" name="ftt_settings[enable_hcaptcha]" value="1" <?php checked($value, true); ?>>
            <?php esc_html_e('Enable hCaptcha on registration and login forms', 'schedule-collaboration-tracking'); ?>
        </label>
        <p class="description"><?php esc_html_e('Protects against spam and bot registrations.', 'schedule-collaboration-tracking'); ?></p>
        <?php
    }
    
    /**
     * Render hCaptcha site key field
     */
    public static function render_hcaptcha_site_key_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['hcaptcha_site_key'] ?? '';
        $enabled = $settings['enable_hcaptcha'] ?? false;
        ?>
        <input type="text" 
               name="ftt_settings[hcaptcha_site_key]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="10000000-ffff-ffff-ffff-000000000001"
               <?php disabled(!$enabled); ?>>
        <p class="description">
            <?php esc_html_e('Your hCaptcha Site Key (public key). Found in your hCaptcha dashboard under Settings.', 'schedule-collaboration-tracking'); ?>
        </p>
        <?php
    }
    
    /**
     * Render hCaptcha secret key field
     */
    public static function render_hcaptcha_secret_key_field() {
        $settings = get_option('ftt_settings', array());
        $value = $settings['hcaptcha_secret_key'] ?? '';
        $enabled = $settings['enable_hcaptcha'] ?? false;
        ?>
        <input type="password" 
               name="ftt_settings[hcaptcha_secret_key]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="0x0000000000000000000000000000000000000000"
               <?php disabled(!$enabled); ?>>
        <p class="description">
            <?php esc_html_e('Your hCaptcha Secret Key (private key). Keep this secure and never share it publicly.', 'schedule-collaboration-tracking'); ?>
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
            </h2>
            
            <form action="options.php" method="post">
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
                    case 'general':
                    default:
                        do_settings_sections('ftt-settings-general');
                        break;
                }
                
                submit_button(__('Save Settings', 'schedule-collaboration-tracking'));
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
