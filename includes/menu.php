<?php
/**
 * Menu Management - Login/Logout Links
 *
 * @package Family_Travel_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for managing login/logout menu items
 */
class FTT_Menu {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'add_menu_meta_box'));
        add_filter('default_hidden_meta_boxes', array(__CLASS__, 'unhide_meta_box'), 10, 2);
        add_filter('wp_nav_menu_items', array(__CLASS__, 'add_login_logout_link'), 10, 2);
        add_filter('wp_nav_menu_items', array(__CLASS__, 'add_billing_links'), 20, 2);
        add_filter('wp_setup_nav_menu_item', array(__CLASS__, 'setup_nav_menu_item'));
        add_action('after_setup_theme', array(__CLASS__, 'register_member_menu_location'));
        add_filter('wp_nav_menu_args', array(__CLASS__, 'swap_menu_for_members'));
    }

    /**
     * Register the members-only nav menu locations.
     *
     * primary_member      – desktop logged-in nav (swaps the 'primary' location)
     * mobile_member       – mobile logged-in nav (swaps whatever Astra uses for mobile)
     */
    public static function register_member_menu_location() {
        register_nav_menus( array(
            'primary_member' => __('Primary Navigation (Members)', 'schedule-collaboration-tracking'),
            'mobile_member'  => __('Mobile Navigation (Members)', 'schedule-collaboration-tracking'),
        ) );
    }

    /**
     * Swap nav menu locations to member-specific menus when logged in.
     *
     * Desktop: 'primary'                   → 'primary_member'
     * Mobile:  [configured mobile slug]    → 'mobile_member'
     *          Falls back to 'primary_member' if no mobile_member menu assigned.
     */
    public static function swap_menu_for_members( $args ) {
        if ( ! is_user_logged_in() ) {
            return $args;
        }

        if ( ! isset( $args['theme_location'] ) ) {
            return $args;
        }

        $locations   = get_nav_menu_locations();
        $location    = $args['theme_location'];

        // ── Desktop swap: primary → primary_member ──────────────────────
        if ( $location === 'primary' ) {
            if ( ! empty( $locations['primary_member'] ) ) {
                $args['theme_location'] = 'primary_member';
            }
            return $args;
        }

        // ── Mobile swap: [configured slug] → mobile_member ──────────────
        $settings     = get_option( 'ftt_settings', array() );
        $mobile_slugs = array_filter( array_map(
            'trim',
            explode( ',', $settings['mobile_menu_location'] ?? '' )
        ) );

        if ( ! empty( $mobile_slugs ) && in_array( $location, $mobile_slugs, true ) ) {
            if ( ! empty( $locations['mobile_member'] ) ) {
                // Use dedicated mobile logged-in menu
                $args['theme_location'] = 'mobile_member';
            } elseif ( ! empty( $locations['primary_member'] ) ) {
                // No mobile menu assigned; fall back to desktop member menu
                $args['theme_location'] = 'primary_member';
            }
        }

        return $args;
    }

    /**
     * Ensure our meta box is never hidden by default in Screen Options.
     */
    public static function unhide_meta_box( $hidden, $screen ) {
        if ( isset( $screen->id ) && $screen->id === 'nav-menus' ) {
            $hidden = array_diff( $hidden, array( 'ftt-login-logout-menu' ) );
        }
        return $hidden;
    }
    
    /**
     * Add meta box to menu admin
     */
    public static function add_menu_meta_box() {
        add_meta_box(
            'ftt-login-logout-menu',
            __('Schedule Login/Logout', 'schedule-collaboration-tracking'),
            array(__CLASS__, 'render_menu_meta_box'),
            'nav-menus',
            'side',
            'default'
        );
    }
    
    /**
     * Render menu meta box
     */
    public static function render_menu_meta_box() {
        $settings = get_option('ftt_settings', array());
        $enabled  = $settings['enable_login_menu'] ?? false;

        global $_nav_menu_placeholder, $nav_menu_selected_id;
        $_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;

        if ( ! $enabled ) {
            echo '<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:3px;padding:8px 10px;margin-bottom:10px;font-size:12px;">';
            echo '<strong>' . esc_html__( 'Note:', 'schedule-collaboration-tracking' ) . '</strong> ';
            echo esc_html__( 'Feature is disabled. ', 'schedule-collaboration-tracking' );
            echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=ftt_event&page=ftt-settings&tab=general' ) ) . '">' . esc_html__( 'Enable in Settings → General', 'schedule-collaboration-tracking' ) . '</a>';
            echo '</div>';
        }
        ?>
        <div id="ftt-login-logout" class="posttypediv">
            <div id="tabs-panel-ftt-login-logout" class="tabs-panel tabs-panel-active">
                <ul id="ftt-login-logout-checklist" class="categorychecklist form-no-clear">
                    <li>
                        <label class="menu-item-title">
                            <input type="checkbox" class="menu-item-checkbox"
                                name="menu-item[<?php echo (int) $_nav_menu_placeholder; ?>][menu-item-object-id]"
                                value="-1" />
                            <?php esc_html_e( 'Login / Logout', 'schedule-collaboration-tracking' ); ?>
                        </label>
                        <input type="hidden" class="menu-item-type"
                            name="menu-item[<?php echo (int) $_nav_menu_placeholder; ?>][menu-item-type]"
                            value="custom" />
                        <input type="hidden" class="menu-item-title"
                            name="menu-item[<?php echo (int) $_nav_menu_placeholder; ?>][menu-item-title]"
                            value="<?php esc_attr_e( 'Login', 'schedule-collaboration-tracking' ); ?>" />
                        <input type="hidden" class="menu-item-url"
                            name="menu-item[<?php echo (int) $_nav_menu_placeholder; ?>][menu-item-url]"
                            value="#ftt-login-logout" />
                        <input type="hidden" class="menu-item-classes"
                            name="menu-item[<?php echo (int) $_nav_menu_placeholder; ?>][menu-item-classes]"
                            value="ftt-login-logout-item" />
                    </li>
                </ul>
            </div>
            <p class="button-controls">
                <span class="add-to-menu">
                    <input type="submit"
                        <?php wp_nav_menu_disabled_check( $nav_menu_selected_id ); ?>
                        class="button-secondary submit-add-to-menu right"
                        value="<?php esc_attr_e( 'Add to Menu', 'schedule-collaboration-tracking' ); ?>"
                        name="add-srt-login-logout-menu-item"
                        id="submit-ftt-login-logout" />
                    <span class="spinner"></span>
                </span>
            </p>
        </div>
        <p class="description">
            <?php esc_html_e( 'Dynamically shows "Login" to guests and "Logout" to signed-in users.', 'schedule-collaboration-tracking' ); ?>
        </p>
        <?php
    }

    /**
     * Setup nav menu item
     */
    public static function setup_nav_menu_item($menu_item) {
        // Mark our custom menu items
        if (isset($menu_item->classes) && is_array($menu_item->classes) && in_array('ftt-login-logout-item', $menu_item->classes)) {
            $menu_item->ftt_login_logout = true;
        }
        
        return $menu_item;
    }
    
    /**
     * Add login/logout link to menu
     */
    public static function add_login_logout_link($items, $args) {
        $settings = get_option('ftt_settings', array());
        $enabled = $settings['enable_login_menu'] ?? false;
        
        if (!$enabled) {
            return $items;
        }
        
        $mode = $settings['login_menu_mode'] ?? 'both';
        $is_logged_in = is_user_logged_in();
        
        // Check if our login/logout item exists in the menu
        if (strpos($items, 'ftt-login-logout-item') === false) {
            return $items;
        }
        
        // Use regex to find and replace the login/logout menu item
        // Match the entire <li> element with class ftt-login-logout-item
        $pattern = '/<li[^>]*\bftt-login-logout-item\b[^>]*>.*?<\/li>/s';
        
        $replacement = '';
        
        // Determine what to show based on mode
        if ($mode === 'login_only') {
            // Only show if NOT logged in
            if (!$is_logged_in) {
                $replacement = self::generate_login_item();
            }
            // If logged in, replacement stays empty (removes the item)
        } else {
            // Show login or logout based on status
            if ($is_logged_in) {
                $replacement = self::generate_logout_item();
            } else {
                $replacement = self::generate_login_item();
            }
        }
        
        // Replace all instances of the login/logout menu item
        $items = preg_replace($pattern, $replacement, $items);
        
        return $items;
    }
    
    /**
     * Generate login menu item HTML
     */
    private static function generate_login_item() {
        // Use custom login page instead of wp-login.php
        $login_url = home_url('/ftt-login/');
        
        // Preserve current URL for redirect after login
        $current_url = self::get_current_url();
        if ($current_url && $current_url !== home_url('/ftt-login/')) {
            $login_url = add_query_arg('redirect_to', urlencode($current_url), $login_url);
        }
        
        $login_text = __('Login', 'schedule-collaboration-tracking');
        
        return sprintf(
            '<li class="menu-item ftt-login-logout-item ftt-login-item"><a href="%s">%s</a></li>',
            esc_url($login_url),
            esc_html($login_text)
        );
    }
    
    /**
     * Generate logout menu item HTML
     */
    private static function generate_logout_item() {
        $logout_url = wp_logout_url( home_url('/') );
        $logout_text = __('Logout', 'schedule-collaboration-tracking');
        
        // Get current user
        $current_user = wp_get_current_user();
        $display_name = $current_user->display_name;
        
        // Option to show username
        $show_username = apply_filters('ftt_show_username_in_logout', false);
        
        if ($show_username && $display_name) {
            $logout_text = sprintf(__('Logout (%s)', 'schedule-collaboration-tracking'), $display_name);
        }
        
        return sprintf(
            '<li class="menu-item ftt-login-logout-item ftt-logout-item"><a href="%s">%s</a></li>',
            esc_url($logout_url),
            esc_html($logout_text)
        );
    }
    
    /**
     * Get current URL for redirect
     */
    private static function get_current_url() {
        global $wp;
        return home_url(add_query_arg(array(), $wp->request));
    }
    
    /**
     * Add billing page links to navigation menu
     */
    public static function add_billing_links($items, $args) {
        // Only add to primary menu (you can customize this)
        if (!isset($args->theme_location) || $args->theme_location !== 'primary') {
            return $items;
        }
        
        // Only for logged-in users
        if (!is_user_logged_in()) {
            return $items;
        }
        
        $billing_links = '';
        
        // Check if Stripe is configured
        $stripe_settings = get_option('ftt_stripe_settings', array());
        $is_configured = !empty($stripe_settings['test_publishable_key']) || !empty($stripe_settings['live_publishable_key']);
        
        if ($is_configured) {
            // Add "Manage Subscription" link
            $manage_url = home_url('/manage-subscription/');
            $billing_links .= sprintf(
                '<li class="menu-item ftt-billing-item"><a href="%s">%s</a></li>',
                esc_url($manage_url),
                esc_html__('My Subscription', 'schedule-collaboration-tracking')
            );
        } else {
            // Add "Pricing" link if not configured yet
            $pricing_url = home_url('/pricing/');
            $billing_links .= sprintf(
                '<li class="menu-item ftt-pricing-item"><a href="%s">%s</a></li>',
                esc_url($pricing_url),
                esc_html__('Pricing', 'schedule-collaboration-tracking')
            );
        }
        
        // Insert before logout link if it exists, otherwise append
        if (strpos($items, 'ftt-logout-item') !== false) {
            $items = str_replace(
                '<li class="menu-item ftt-login-logout-item ftt-logout-item">',
                $billing_links . '<li class="menu-item ftt-login-logout-item ftt-logout-item">',
                $items
            );
        } else {
            $items .= $billing_links;
        }
        
        return $items;
    }
}

// Initialize
FTT_Menu::init();
