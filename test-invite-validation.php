<?php
/**
 * Test script for invitation validation
 * 
 * Usage: Add ?test_invite=YOUR_CODE to any page URL
 */

add_action('init', function() {
    if (!isset($_GET['test_invite']) || !current_user_can('manage_options')) {
        return;
    }
    
    $code = sanitize_text_field($_GET['test_invite']);
    
    echo '<pre style="background: #f0f0f0; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
    echo "=== INVITATION VALIDATION TEST ===\n\n";
    echo "Code: " . esc_html($code) . "\n\n";
    
    // Check if it looks like a member code
    if (strpos($code, 'M-') === 0) {
        echo "Type: Member Code\n";
    } elseif (strpos($code, 'INV-') === 0) {
        echo "Type: Invitation Code\n";
    } else {
        echo "Type: Adult Invitation (alphanumeric)\n";
    }
    
    // Search for adult invitations
    echo "\n--- Searching for Adult Invitations ---\n";
    $users = get_users(array('meta_key' => 'ftt_adult_invitations'));
    echo "Users with adult invitations: " . count($users) . "\n\n";
    
    foreach ($users as $user) {
        echo "User: {$user->display_name} (ID: {$user->ID})\n";
        $invitations = get_user_meta($user->ID, 'ftt_adult_invitations', true);
        
        if (is_array($invitations)) {
            echo "  Total invitations: " . count($invitations) . "\n";
            
            foreach ($invitations as $inv_code => $inv_data) {
                echo "  - Code: $inv_code\n";
                echo "    Email: {$inv_data['email']}\n";
                echo "    Relationship: {$inv_data['relationship']}\n";
                echo "    Status: {$inv_data['status']}\n";
                echo "    Expires: " . date('Y-m-d H:i:s', $inv_data['expires']) . "\n";
                echo "    Match: " . ($inv_code === $code ? 'YES ✓' : 'no') . "\n\n";
            }
        } else {
            echo "  No valid invitation data\n\n";
        }
    }
    
    // Test REST API endpoint
    echo "\n--- Testing REST API Endpoint ---\n";
    $api_url = rest_url('ftt/v1/invite/' . $code . '/validate');
    echo "URL: $api_url\n\n";
    
    $response = wp_remote_get($api_url, array('timeout' => 10));
    
    if (is_wp_error($response)) {
        echo "ERROR: " . $response->get_error_message() . "\n";
    } else {
        echo "Response Code: " . wp_remote_retrieve_response_code($response) . "\n";
        echo "Response Body:\n";
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        print_r($data);
    }
    
    // Also test direct method call
    echo "\n--- Testing Direct Method Call ---\n";
    try {
        $request = new WP_REST_Request('GET', '/ftt/v1/invite/' . $code . '/validate');
        $request->set_param('code', $code);
        $result = FTT_Invitations::validate_invite_code($request);
        echo "Direct call result:\n";
        print_r($result);
    } catch (Exception $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
    // Check recent error logs
    echo "\n--- Recent Error Log Entries (FTT related) ---\n";
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file)) {
            echo "Log file: $log_file\n\n";
            $log_contents = file_get_contents($log_file);
            $lines = explode("\n", $log_contents);
            $relevant_lines = array_filter($lines, function($line) {
                return stripos($line, 'FTT') !== false || stripos($line, 'invitation') !== false;
            });
            $recent = array_slice($relevant_lines, -20); // Last 20 FTT-related lines
            foreach ($recent as $line) {
                echo $line . "\n";
            }
        } else {
            echo "Debug log file not found at: $log_file\n";
            echo "WP_DEBUG_LOG is enabled but file doesn't exist yet.\n";
        }
    } else {
        echo "WP_DEBUG_LOG is not enabled.\n";
        echo "To enable, add this to wp-config.php:\n";
        echo "define('WP_DEBUG', true);\n";
        echo "define('WP_DEBUG_LOG', true);\n";
        echo "define('WP_DEBUG_DISPLAY', false);\n";
    }
    
    echo "\n=== END TEST ===";
    echo '</pre>';
    exit;
}, 5);
