<?php
/**
 * Domain Routing for Dual-Domain Single WordPress Setup
 *
 * Handles routing between marketing domain (www) and app domain (my)
 * within a single WordPress installation.
 *
 * @package FamilyTravelTracker
 * @since 2.0.14
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTT_Domain_Routing {
    
    /**
     * Marketing domain prefix
     */
    const MARKETING_PREFIX = 'www.';
    
    /**
     * App domain prefix
     */
    const APP_PREFIX = 'my.';
    
    /**
     * Initialize domain routing
     */
    public static function init() {
        // Domain-based redirects
        add_action('template_redirect', [__CLASS__, 'handle_domain_redirects'], 1);
        
        // Filter navigation menus based on domain
        add_filter('wp_nav_menu_args', [__CLASS__, 'filter_menu_by_domain']);
        
        // Add body class for domain-specific styling
        add_filter('body_class', [__CLASS__, 'add_domain_body_class']);
        
        // Hide admin bar on marketing domain for non-admins
        add_filter('show_admin_bar', [__CLASS__, 'filter_admin_bar']);
        
        // Register navigation menus
        add_action('after_setup_theme', [__CLASS__, 'register_menus']);
    }
    
    /**
     * Get current domain
     *
     * @return string Current domain with protocol
     */
    public static function get_current_domain() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'];
    }
    
    /**
     * Get current host
     *
     * @return string Current host without protocol
     */
    public static function get_current_host() {
        return $_SERVER['HTTP_HOST'];
    }
    
    /**
     * Check if current domain is marketing domain
     *
     * @return bool True if on marketing domain
     */
    public static function is_marketing_domain() {
        $host = self::get_current_host();
        return strpos($host, self::MARKETING_PREFIX) === 0;
    }
    
    /**
     * Check if current domain is app domain
     *
     * @return bool True if on app domain
     */
    public static function is_app_domain() {
        $host = self::get_current_host();
        return strpos($host, self::APP_PREFIX) === 0;
    }
    
    /**
     * Get marketing domain URL
     *
     * @return string Marketing domain URL
     */
    public static function get_marketing_url() {
        $host = self::get_current_host();
        $base_domain = str_replace([self::MARKETING_PREFIX, self::APP_PREFIX], '', $host);
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . self::MARKETING_PREFIX . $base_domain;
    }
    
    /**
     * Get app domain URL
     *
     * @return string App domain URL
     */
    public static function get_app_url() {
        $host = self::get_current_host();
        $base_domain = str_replace([self::MARKETING_PREFIX, self::APP_PREFIX], '', $host);
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . self::APP_PREFIX . $base_domain;
    }
    
    /**
     * Handle domain-based redirects
     */
    public static function handle_domain_redirects() {
        $current_uri = $_SERVER['REQUEST_URI'];
        $parsed_uri = parse_url($current_uri, PHP_URL_PATH);
        
        // Define marketing pages (should be on www)
        $marketing_pages = [
            '/',
            '/features/',
            '/pricing/',
            '/about/',
            '/support/',
            '/contact/',
            '/privacy/',
            '/terms/',
        ];
        
        // Define app pages (should be on my)
        $app_pages = [
            '/ftt-dashboard/',
            '/ftt-calendar/',
            '/ftt-events/',
            '/ftt-event/',
            '/ftt-billing/',
            '/ftt-subscribe/',
            '/ftt-checkout-success/',
            '/ftt-checkout-cancel/',
        ];
        
        // Allow filtering of page lists
        $marketing_pages = apply_filters('ftt_marketing_pages', $marketing_pages);
        $app_pages = apply_filters('ftt_app_pages', $app_pages);
        
        // Redirect marketing pages to www domain
        if (self::is_app_domain() && in_array($parsed_uri, $marketing_pages)) {
            wp_redirect(self::get_marketing_url() . $current_uri, 301);
            exit;
        }
        
        // Redirect app pages to my domain
        if (self::is_marketing_domain() && in_array($parsed_uri, $app_pages)) {
            if (is_user_logged_in()) {
                // Logged in users go directly to app
                wp_redirect(self::get_app_url() . $current_uri, 301);
            } else {
                // Non-logged in users go to login with redirect
                $redirect_to = urlencode($current_uri);
                wp_redirect(self::get_app_url() . '/wp-login.php?redirect_to=' . $redirect_to, 302);
            }
            exit;
        }
        
        // Redirect wp-admin access to app domain
        if (self::is_marketing_domain() && strpos($current_uri, '/wp-admin') === 0) {
            wp_redirect(self::get_app_url() . $current_uri, 301);
            exit;
        }
        
        // Handle login redirects
        if (strpos($current_uri, '/wp-login.php') !== false) {
            // Always handle login on app domain
            if (self::is_marketing_domain()) {
                wp_redirect(self::get_app_url() . $current_uri, 301);
                exit;
            }
        }
    }
    
    /**
     * Register navigation menus
     */
    public static function register_menus() {
        register_nav_menus([
            'marketing-primary' => __('Marketing Primary Menu', 'schedule-collaboration-tracking'),
            'app-primary' => __('App Primary Menu', 'schedule-collaboration-tracking'),
        ]);
    }
    
    /**
     * Filter menu based on current domain
     *
     * @param array $args Menu arguments
     * @return array Modified arguments
     */
    public static function filter_menu_by_domain($args) {
        // If theme_location is 'primary', change it based on domain
        if ($args['theme_location'] === 'primary' || empty($args['theme_location'])) {
            if (self::is_marketing_domain()) {
                $args['theme_location'] = 'marketing-primary';
            } elseif (self::is_app_domain()) {
                $args['theme_location'] = 'app-primary';
            }
        }
        
        return $args;
    }
    
    /**
     * Add body class for domain-specific styling
     *
     * @param array $classes Existing body classes
     * @return array Modified classes
     */
    public static function add_domain_body_class($classes) {
        if (self::is_marketing_domain()) {
            $classes[] = 'ftt-marketing-domain';
        } elseif (self::is_app_domain()) {
            $classes[] = 'ftt-app-domain';
        }
        
        return $classes;
    }
    
    /**
     * Filter admin bar visibility
     *
     * @param bool $show_admin_bar Current setting
     * @return bool Modified setting
     */
    public static function filter_admin_bar($show_admin_bar) {
        // Hide admin bar on marketing domain for non-admins
        if (self::is_marketing_domain() && !current_user_can('manage_options')) {
            return false;
        }
        
        return $show_admin_bar;
    }
    
    /**
     * Get the appropriate domain for a given page type
     *
     * @param string $page_type 'marketing' or 'app'
     * @return string Domain URL
     */
    public static function get_domain_for_page($page_type) {
        if ($page_type === 'marketing') {
            return self::get_marketing_url();
        } elseif ($page_type === 'app') {
            return self::get_app_url();
        }
        
        return home_url();
    }
    
    /**
     * Helper function to create cross-domain links
     *
     * @param string $path URL path
     * @param string $domain 'marketing' or 'app'
     * @return string Full URL
     */
    public static function get_cross_domain_url($path, $domain = 'app') {
        $base_url = $domain === 'marketing' ? self::get_marketing_url() : self::get_app_url();
        return rtrim($base_url, '/') . '/' . ltrim($path, '/');
    }
}
