<?php
/**
 * CORS Configuration for Dual Domain Setup
 *
 * Handles Cross-Origin Resource Sharing between:
 * - www.familytraveltracker.app (marketing site)
 * - my.familytraveltracker.app (app site)
 *
 * @package FamilyTravelTracker
 * @since 2.0.13
 */

if (!defined('ABSPATH')) {
    exit;
}

class FTT_CORS {
    
    /**
     * Initialize CORS handling
     */
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'add_cors_headers'], 15);
        add_action('init', [__CLASS__, 'handle_preflight']);
        
        // Debug: Log all REST requests
        add_filter('rest_pre_dispatch', [__CLASS__, 'log_rest_dispatch'], 10, 3);
    }
    
    /**
     * Log REST dispatch for debugging
     */
    public static function log_rest_dispatch($result, $server, $request) {
        $route = $request->get_route();
        $method = $request->get_method();
        error_log('FTT REST DISPATCH: ' . $method . ' ' . $route);
        error_log('FTT REST DISPATCH: Already handled: ' . ($result === null ? 'NO' : 'YES'));
        if ($result !== null) {
            error_log('FTT REST DISPATCH: Pre-dispatch result type: ' . gettype($result));
        }
        return $result;
    }
    
    /**
     * Add CORS headers to REST API responses
     */
    public static function add_cors_headers() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', [__CLASS__, 'send_cors_headers']);
    }
    
    /**
     * Send CORS headers
     */
    public static function send_cors_headers($value) {
        $origin = get_http_origin();
        $allowed_origins = self::get_allowed_origins();
        
        // Check if origin is allowed
        if (in_array($origin, $allowed_origins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
            header('Access-Control-Max-Age: 86400'); // Cache preflight for 24 hours
        }
        
        return $value;
    }
    
    /**
     * Handle preflight OPTIONS requests
     */
    public static function handle_preflight() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $origin = get_http_origin();
            $allowed_origins = self::get_allowed_origins();
            
            if (in_array($origin, $allowed_origins)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
                header('Access-Control-Max-Age: 86400');
                status_header(200);
                exit;
            }
        }
    }
    
    /**
     * Get allowed origins
     *
     * @return array List of allowed origins
     */
    private static function get_allowed_origins() {
        // For single-domain dual-subdomain setup, allow both subdomains
        $origins = [];
        
        // If domain routing is active, get both domains
        if (class_exists('FTT_Domain_Routing')) {
            $origins[] = FTT_Domain_Routing::get_marketing_url();
            $origins[] = FTT_Domain_Routing::get_app_url();
        }
        
        // Fallback origins
        $origins[] = 'https://www.familytraveltracker.app';
        $origins[] = 'https://my.familytraveltracker.app';
        $origins[] = 'https://familytraveltracker.app';
        $origins[] = 'http://localhost:3000'; // For local dev
        
        // Remove duplicates
        $origins = array_unique($origins);
        
        // Allow filtering for custom domains
        return apply_filters('ftt_cors_allowed_origins', $origins);
    }
}
