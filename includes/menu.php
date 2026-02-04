<?php
/**
 * Menu Management - Login/Logout Links
 *
 * @package Summer_Regiment_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for managing login/logout menu items
 */
class SRT_Menu {
    
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'add_menu_meta_box'));
        add_filter('wp_nav_menu_items', array(__CLASS__, 'add_login_logout_link'), 10, 2);
        add_filter('wp_setup_nav_menu_item', array(__CLASS__, 'setup_nav_menu_item'));
    }
    
    /**
     * Add meta box to menu admin
     */
    public static function add_menu_meta_box() {
        add_meta_box(
            'srt-login-logout-menu',
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
        $settings = get_option('srt_settings', array());
        $enabled = $settings['enable_login_menu'] ?? false;
        
        if (!$enabled) {
            ?>
            <p><?php esc_html_e('Login/Logout menu is disabled.', 'schedule-collaboration-tracking'); ?></p>
            <p><a href="<?php echo admin_url('edit.php?post_type=srt_event&page=srt-settings'); ?>"><?php esc_html_e('Enable in Settings', 'schedule-collaboration-tracking'); ?></a></p>
            <?php
            return;
        }
        
        global $_nav_menu_placeholder, $nav_menu_selected_id;
        $_nav_menu_placeholder = 0 > $_nav_menu_placeholder ? $_nav_menu_placeholder - 1 : -1;
        ?>
        <div id="srt-login-logout" class="posttypediv">
            <div id="tabs-panel-srt-login-logout" class="tabs-panel tabs-panel-active">
                <ul id="srt-login-logout-checklist" class="categorychecklist form-no-clear">
                    <li>
                        <label class="menu-item-title">
                            <input type="checkbox" class="menu-item-checkbox" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-object-id]" value="-1" /> 
                            <?php esc_html_e('Login / Logout', 'schedule-collaboration-tracking'); ?>
                        </label>
                        <input type="hidden" class="menu-item-type" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-type]" value="custom" />
                        <input type="hidden" class="menu-item-title" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-title]" value="<?php esc_attr_e('Login', 'schedule-collaboration-tracking'); ?>" />
                        <input type="hidden" class="menu-item-url" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-url]" value="#srt-login-logout" />
                        <input type="hidden" class="menu-item-classes" name="menu-item[<?php echo $_nav_menu_placeholder; ?>][menu-item-classes]" value="srt-login-logout-item" />
                    </li>
                </ul>
            </div>
            <p class="button-controls">
                <span class="add-to-menu">
                    <input type="submit"<?php wp_nav_menu_disabled_check($nav_menu_selected_id); ?> class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e('Add to Menu', 'schedule-collaboration-tracking'); ?>" name="add-srt-login-logout-menu-item" id="submit-srt-login-logout" />
                    <span class="spinner"></span>
                </span>
            </p>
        </div>
        <p class="description">
            <?php esc_html_e('This menu item will automatically display "Login" or "Logout" based on the user\'s authentication status.', 'schedule-collaboration-tracking'); ?>
        </p>
        <?php
    }
    
    /**
     * Setup nav menu item
     */
    public static function setup_nav_menu_item($menu_item) {
        // Mark our custom menu items
        if (isset($menu_item->classes) && is_array($menu_item->classes) && in_array('srt-login-logout-item', $menu_item->classes)) {
            $menu_item->srt_login_logout = true;
        }
        
        return $menu_item;
    }
    
    /**
     * Add login/logout link to menu
     */
    public static function add_login_logout_link($items, $args) {
        $settings = get_option('srt_settings', array());
        $enabled = $settings['enable_login_menu'] ?? false;
        
        if (!$enabled) {
            return $items;
        }
        
        $mode = $settings['login_menu_mode'] ?? 'both';
        $is_logged_in = is_user_logged_in();
        
        // Parse menu items
        $items_array = explode('</li>', $items);
        $new_items = array();
        
        foreach ($items_array as $item) {
            if (empty($item)) {
                continue;
            }
            
            // Check if this is our login/logout item
            if (strpos($item, 'srt-login-logout-item') !== false) {
                // Determine what to show
                if ($mode === 'login_only') {
                    // Only show if NOT logged in
                    if (!$is_logged_in) {
                        $new_items[] = self::generate_login_item();
                    }
                    // Skip adding item if logged in
                } else {
                    // Show login or logout based on status
                    if ($is_logged_in) {
                        $new_items[] = self::generate_logout_item();
                    } else {
                        $new_items[] = self::generate_login_item();
                    }
                }
            } else {
                // Regular menu item, keep it
                $new_items[] = $item . '</li>';
            }
        }
        
        return implode('', $new_items);
    }
    
    /**
     * Generate login menu item HTML
     */
    private static function generate_login_item() {
        // Use custom login page instead of wp-login.php
        $login_url = home_url('/sc-login/');
        
        // Preserve current URL for redirect after login
        $current_url = self::get_current_url();
        if ($current_url && $current_url !== home_url('/sc-login/')) {
            $login_url = add_query_arg('redirect_to', urlencode($current_url), $login_url);
        }
        
        $login_text = __('Login', 'schedule-collaboration-tracking');
        
        return sprintf(
            '<li class="menu-item srt-login-logout-item srt-login-item"><a href="%s">%s</a></li>',
            esc_url($login_url),
            esc_html($login_text)
        );
    }
    
    /**
     * Generate logout menu item HTML
     */
    private static function generate_logout_item() {
        $logout_url = wp_logout_url(self::get_current_url());
        $logout_text = __('Logout', 'schedule-collaboration-tracking');
        
        // Get current user
        $current_user = wp_get_current_user();
        $display_name = $current_user->display_name;
        
        // Option to show username
        $show_username = apply_filters('srt_show_username_in_logout', false);
        
        if ($show_username && $display_name) {
            $logout_text = sprintf(__('Logout (%s)', 'schedule-collaboration-tracking'), $display_name);
        }
        
        return sprintf(
            '<li class="menu-item srt-login-logout-item srt-logout-item"><a href="%s">%s</a></li>',
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
}

// Initialize
SRT_Menu::init();
