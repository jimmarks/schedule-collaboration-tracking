<?php
/**
 * WordPress Domain Diagnostic
 * Place in DocumentRoot and access via browser
 */

// Check if we can access WordPress constants
$wp_config = '/opt/bitnami/wordpress/wp-config.php';
if (file_exists($wp_config)) {
    require_once($wp_config);
}

header('Content-Type: text/plain');

echo "==================================================\n";
echo "WORDPRESS DOMAIN DIAGNOSTIC\n";
echo "==================================================\n\n";

echo "SERVER VARIABLES:\n";
echo "─────────────────────────────────────────────────\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'not set') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "\n";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'not set') . "\n";
echo "REQUEST_SCHEME: " . ($_SERVER['REQUEST_SCHEME'] ?? 'not set') . "\n";
echo "\n";

echo "WORDPRESS CONSTANTS:\n";
echo "─────────────────────────────────────────────────\n";
echo "WP_HOME: " . (defined('WP_HOME') ? WP_HOME : 'not defined') . "\n";
echo "WP_SITEURL: " . (defined('WP_SITEURL') ? WP_SITEURL : 'not defined') . "\n";
echo "FORCE_SSL_ADMIN: " . (defined('FORCE_SSL_ADMIN') ? (FORCE_SSL_ADMIN ? 'true' : 'false') : 'not defined') . "\n";
echo "\n";

// Try to get WordPress options if possible
if (defined('DB_NAME')) {
    try {
        $mysqli = new mysqli('localhost', DB_USER, DB_PASSWORD, DB_NAME);
        
        if (!$mysqli->connect_error) {
            $table_prefix = isset($table_prefix) ? $table_prefix : 'wp_';
            
            echo "DATABASE OPTIONS:\n";
            echo "─────────────────────────────────────────────────\n";
            
            $result = $mysqli->query("SELECT option_name, option_value FROM {$table_prefix}options WHERE option_name IN ('siteurl', 'home')");
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    echo $row['option_name'] . ": " . $row['option_value'] . "\n";
                }
            }
            
            echo "\n";
            $mysqli->close();
        }
    } catch (Exception $e) {
        echo "Could not connect to database\n\n";
    }
}

echo "EXPECTED BEHAVIOR:\n";
echo "─────────────────────────────────────────────────\n";
echo "• my.familytraveltracker.app should stay on my\n";
echo "• www.familytraveltracker.app should stay on www\n";
echo "• familytraveltracker.app should redirect to www\n";
echo "\n";

echo "CURRENT REQUEST:\n";
echo "─────────────────────────────────────────────────\n";
$current_url = ($_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
echo "Full URL: $current_url\n";
echo "\n";

echo "Test this URL from command line:\n";
echo "curl -IL $current_url\n";
