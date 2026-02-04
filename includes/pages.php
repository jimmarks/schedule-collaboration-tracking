<?php
/**
 * Page Management Functions
 *
 * @package Summer_Regiment_Tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for managing plugin pages
 */
class SRT_Pages {
    
    /**
     * Page definitions
     */
    private static function get_page_definitions() {
        return array(
            'dashboard' => array(
                'title' => 'Schedule Dashboard',
                'shortcode' => '[srt_dashboard]',
                'slug' => 'sc-dashboard',
                'menu_order' => 1,
                'description' => 'Main hub for schedule members, parents, and admins',
            ),
            'calendar' => array(
                'title' => 'Schedule Events Calendar',
                'shortcode' => '[srt_calendar]',
                'slug' => 'sc-calendar',
                'menu_order' => 2,
                'description' => 'Visual calendar view of all events',
            ),
            'event_list' => array(
                'title' => 'Schedule Events List',
                'shortcode' => '[srt_event_list]',
                'slug' => 'sc-events',
                'menu_order' => 3,
                'description' => 'List view of all schedule events',
            ),
            'event_form' => array(
                'title' => 'Schedule Events Manager',
                'shortcode' => '[srt_event_form]',
                'slug' => 'sc-manage-events',
                'menu_order' => 4,
                'description' => 'Create and edit events (admin only)',
            ),
            'register' => array(
                'title' => 'Schedule Registration',
                'shortcode' => '[srt_register]',
                'slug' => 'sc-register',
                'menu_order' => 5,
                'description' => 'User registration page',
            ),
            'login' => array(
                'title' => 'Schedule Login',
                'shortcode' => '[srt_login]',
                'slug' => 'sc-login',
                'menu_order' => 6,
                'description' => 'Custom login page for schedule users',
            ),
        );
    }
    
    /**
     * Create all plugin pages
     */
    public static function create_pages() {
        $pages = self::get_page_definitions();
        $page_ids = get_option('srt_page_ids', array());
        
        foreach ($pages as $key => $page_data) {
            // Check if page already exists by slug
            $existing_page = get_page_by_path($page_data['slug']);
            
            if ($existing_page) {
                $page_ids[$key] = $existing_page->ID;
                continue;
            }
            
            // Check if we have an old page ID stored
            if (isset($page_ids[$key]) && get_post($page_ids[$key])) {
                // Update existing page with new slug
                wp_update_post(array(
                    'ID' => $page_ids[$key],
                    'post_name' => $page_data['slug'],
                ));
                continue;
            }
            
            // Create the page
            $page_id = wp_insert_post(array(
                'post_title' => $page_data['title'],
                'post_content' => $page_data['shortcode'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => $page_data['slug'],
                'menu_order' => $page_data['menu_order'],
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ));
            
            if ($page_id && !is_wp_error($page_id)) {
                $page_ids[$key] = $page_id;
                
                // Add page meta to identify as plugin page
                update_post_meta($page_id, '_srt_page', true);
                
                // Set dashboard as front page (home page)
                if ($key === 'dashboard') {
                    update_option('show_on_front', 'page');
                    update_option('page_on_front', $page_id);
                }
            }
        }
        
        // Store page IDs in options
        update_option('srt_page_ids', $page_ids);
        
        return $page_ids;
    }
    
    /**
     * Delete all plugin pages
     */
    public static function delete_pages() {
        $page_ids = get_option('srt_page_ids', array());
        
        foreach ($page_ids as $page_id) {
            // Only delete if it's still marked as our page
            if (get_post_meta($page_id, '_srt_page', true)) {
                wp_delete_post($page_id, true);
            }
        }
        
        delete_option('srt_page_ids');
    }
    
    /**
     * Get page URL by key
     *
     * @param string $page_key The page key (calendar, event_form, dashboard, event_list)
     * @return string|false The page URL or false if not found
     */
    public static function get_page_url($page_key) {
        $page_ids = get_option('srt_page_ids', array());
        
        if (!isset($page_ids[$page_key])) {
            return false;
        }
        
        $url = get_permalink($page_ids[$page_key]);
        
        return $url ? $url : false;
    }
    
    /**
     * Get page ID by key
     *
     * @param string $page_key The page key
     * @return int|false The page ID or false if not found
     */
    public static function get_page_id($page_key) {
        $page_ids = get_option('srt_page_ids', array());
        return isset($page_ids[$page_key]) ? $page_ids[$page_key] : false;
    }
    
    /**
     * Check if plugin pages exist
     *
     * @return bool True if all pages exist
     */
    public static function pages_exist() {
        $page_ids = get_option('srt_page_ids', array());
        $pages = self::get_page_definitions();
        
        if (count($page_ids) !== count($pages)) {
            return false;
        }
        
        foreach ($page_ids as $page_id) {
            if (!get_post($page_id)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get admin notice for missing pages
     *
     * @return string HTML for admin notice
     */
    public static function get_missing_pages_notice() {
        $pages = self::get_page_definitions();
        $page_ids = get_option('srt_page_ids', array());
        $missing = array();
        
        foreach ($pages as $key => $page_data) {
            if (!isset($page_ids[$key]) || !get_post($page_ids[$key])) {
                $missing[] = $page_data['title'];
            }
        }
        
        if (empty($missing)) {
            return;
        }
        
        $recreate_url = wp_nonce_url(
            add_query_arg('srt_recreate_pages', '1'),
            'srt_recreate_pages'
        );
        
        echo sprintf(
            '<div class="notice notice-warning"><p><strong>Schedule Tracker:</strong> Some plugin pages are missing (%s). <a href="%s">Recreate Pages</a></p></div>',
            esc_html(implode(', ', $missing)),
            esc_url($recreate_url)
        );
    }
    
    /**
     * Handle page recreation request
     */
    public static function handle_recreate_pages() {
        if (!isset($_GET['srt_recreate_pages'])) {
            return;
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'srt_recreate_pages')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        self::create_pages();
        
        wp_redirect(remove_query_arg(array('srt_recreate_pages', '_wpnonce')));
        exit;
    }
    
    /**
     * Get registration page URL
     *
     * @return string|null Registration URL or null if page doesn't exist
     */
    public static function get_registration_url() {
        $page_ids = get_option('srt_page_ids', array());
        
        // Try stored page ID first
        if (isset($page_ids['register']) && get_post($page_ids['register'])) {
            return get_permalink($page_ids['register']);
        }
        
        // Fallback: search for page with registration shortcode
        $pages = get_pages(array('meta_key' => '_srt_page'));
        foreach ($pages as $page) {
            if (has_shortcode($page->post_content, 'srt_register')) {
                return get_permalink($page->ID);
            }
        }
        
        // Last resort: search all pages
        $all_pages = get_pages();
        foreach ($all_pages as $page) {
            if (has_shortcode($page->post_content, 'srt_register')) {
                return get_permalink($page->ID);
            }
        }
        
        return null;
    }
}
