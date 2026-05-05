<?php
/**
 * REST API Security Validation Admin Page
 * 
 * Provides a WordPress admin interface for validating REST API security
 * Accessible from: WordPress Admin > Tools > REST API Validation
 *
 * @package Family_Travel_Tracker
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Register the validation admin page
 */
function ftt_register_validation_page() {
    add_management_page(
        'REST API Validation',           // Page title
        'REST API Validation',           // Menu title
        'manage_options',                // Capability required
        'ftt-rest-validation',          // Menu slug
        'ftt_render_validation_page'    // Callback function
    );
}
add_action('admin_menu', 'ftt_register_validation_page');

/**
 * Render the validation page
 */
function ftt_render_validation_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .ftt-validation-container {
                background: white;
                margin: 20px 0;
                padding: 0;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .ftt-validation-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
                margin: 0;
            }
            .ftt-validation-header h1 {
                font-size: 32px;
                margin: 0 0 10px 0;
                color: white;
            }
            .ftt-validation-header p {
                font-size: 16px;
                opacity: 0.9;
                margin: 0;
            }
            .ftt-validation-content {
                padding: 30px;
            }
            .ftt-test-section {
                margin-bottom: 30px;
                border: 1px solid #e2e8f0;
                border-radius: 4px;
                overflow: hidden;
            }
            .ftt-test-section-header {
                background: #f7fafc;
                padding: 15px 20px;
                font-weight: 600;
                font-size: 16px;
                color: #2d3748;
                border-bottom: 2px solid #e2e8f0;
            }
            .ftt-test-results {
                padding: 15px 20px;
            }
            .ftt-test-item {
                padding: 10px 0;
                border-bottom: 1px solid #f7fafc;
                display: flex;
                align-items: flex-start;
            }
            .ftt-test-item:last-child {
                border-bottom: none;
            }
            .ftt-test-icon {
                margin-right: 12px;
                font-size: 18px;
                flex-shrink: 0;
            }
            .ftt-test-message {
                flex: 1;
                line-height: 1.6;
                font-size: 14px;
            }
            .ftt-pass { color: #48bb78; }
            .ftt-fail { color: #f56565; }
            .ftt-warn { color: #ed8936; }
            .ftt-info { color: #4299e1; }
            .ftt-summary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                border-radius: 4px;
                margin: 30px 0;
            }
            .ftt-summary-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .ftt-stat-card {
                background: rgba(255,255,255,0.1);
                padding: 20px;
                border-radius: 4px;
                text-align: center;
            }
            .ftt-stat-number {
                font-size: 48px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .ftt-stat-label {
                font-size: 14px;
                opacity: 0.9;
            }
            .ftt-badge {
                display: inline-block;
                padding: 8px 20px;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 600;
                margin-top: 15px;
            }
            .ftt-badge-success {
                background: #c6f6d5;
                color: #22543d;
            }
            .ftt-badge-warning {
                background: #feebc8;
                color: #7c2d12;
            }
            .ftt-badge-error {
                background: #fed7d7;
                color: #742a2a;
            }
            .ftt-next-steps {
                background: #f7fafc;
                padding: 20px;
                border-radius: 4px;
                margin-top: 20px;
            }
            .ftt-next-steps h3 {
                margin: 0 0 15px 0;
                color: #2d3748;
                font-size: 16px;
            }
            .ftt-next-steps ol {
                margin: 0 0 0 20px;
                padding: 0;
            }
            .ftt-next-steps li {
                margin-bottom: 8px;
                color: #4a5568;
                font-size: 14px;
            }
            .ftt-timestamp {
                text-align: center;
                color: #a0aec0;
                font-size: 13px;
                margin-top: 20px;
                padding: 20px;
                border-top: 1px solid #e2e8f0;
            }
        </style>
    </head>
    <body>
        <div class="wrap">
            <div class="ftt-validation-container">
                <div class="ftt-validation-header">
                    <h1>🔒 REST API Security Validation</h1>
                    <p>Code Analysis & Security Pattern Verification</p>
                </div>
                <div class="ftt-validation-content">
                    <?php
                    // Run the validation
                    ftt_run_validation_tests();
                    ?>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Run all validation tests
 */
function ftt_run_validation_tests() {
    $errors = 0;
    $warnings = 0;
    $passes = 0;
    
    // Test 1: REST API File Structure
    ftt_test_section("Test 1: REST API File Structure");
    
    $rest_file = plugin_dir_path(dirname(__FILE__)) . 'includes/rest.php';
    if (file_exists($rest_file)) {
        ftt_pass("REST API file exists: includes/rest.php", $passes);
        $rest_content = file_get_contents($rest_file);
    } else {
        ftt_fail("REST API file not found: includes/rest.php", $errors);
        return;
    }
    ftt_test_section_end();
    
    // Test 2: Permission Callbacks
    ftt_test_section("Test 2: Permission Callbacks on Endpoints");
    
    $endpoint_pattern = '/register_rest_route\s*\(\s*[\'"]ftt\/v1[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*,\s*array\s*\((.*?)\)\s*\)/s';
    preg_match_all($endpoint_pattern, $rest_content, $endpoints, PREG_SET_ORDER);
    
    ftt_info("Found " . count($endpoints) . " REST endpoint registrations");
    
    foreach ($endpoints as $endpoint) {
        $route = $endpoint[1];
        $args = $endpoint[2];
        
        if (strpos($args, 'permission_callback') !== false) {
            ftt_pass("Endpoint /{$route} has permission_callback", $passes);
        } else {
            ftt_fail("Endpoint /{$route} MISSING permission_callback", $errors);
        }
    }
    ftt_test_section_end();
    
    // Test 3: Authentication Requirements
    ftt_test_section("Test 3: Authentication Requirements");
    
    if (preg_match_all('/permission_callback.*check_read_permission/', $rest_content, $matches)) {
        ftt_pass("Found " . count($matches[0]) . " endpoints using check_read_permission", $passes);
    }
    
    if (preg_match_all('/permission_callback.*check_edit_permission/', $rest_content, $matches)) {
        ftt_pass("Found " . count($matches[0]) . " endpoints using check_edit_permission", $passes);
    }
    ftt_test_section_end();
    
    // Test 4: Group-Based Filtering
    ftt_test_section("Test 4: Group-Based Data Filtering (get_events)");
    
    if (preg_match('/function\s+get_events\s*\([^)]*\)\s*\{/s', $rest_content, $match, PREG_OFFSET_CAPTURE)) {
        $start = $match[0][1];
        $method_content = substr($rest_content, $start, 5000);
        
        if (strpos($method_content, 'FTT_Family_Groups::get_user_children') !== false) {
            ftt_pass("get_events() uses FTT_Family_Groups::get_user_children() for filtering", $passes);
        } else {
            ftt_fail("get_events() does NOT use FTT_Family_Groups::get_user_children()", $errors);
        }
        
        if (strpos($method_content, 'is_parent') !== false && strpos($method_content, 'is_member') !== false) {
            ftt_pass("get_events() checks user roles (is_parent, is_member)", $passes);
        }
        
        if (preg_match('/current_user_can\s*\(\s*[\'"]manage_options[\'"]/', $method_content)) {
            ftt_fail("get_events() contains ADMIN BYPASS using current_user_can('manage_options')", $errors);
        } else {
            ftt_pass("get_events() does NOT contain admin bypass ✓ CRITICAL SECURITY FIX", $passes);
        }
    }
    ftt_test_section_end();
    
    // Test 5: Children Endpoint Security
    ftt_test_section("Test 5: Children Endpoint Security (get_children_list)");
    
    if (preg_match('/function\s+get_children_list\s*\([^)]*\)\s*\{/s', $rest_content, $match, PREG_OFFSET_CAPTURE)) {
        $start = $match[0][1];
        $method_content = substr($rest_content, $start, 3000);
        
        if (strpos($method_content, 'get_current_user_id()') !== false) {
            ftt_pass("get_children_list() uses get_current_user_id()", $passes);
        }
        
        if (strpos($method_content, 'FTT_Family_Groups::get_user_children') !== false) {
            ftt_pass("get_children_list() uses FTT_Family_Groups::get_user_children()", $passes);
        }
        
        if (preg_match('/current_user_can\s*\(\s*[\'"]manage_options[\'"]/', $method_content)) {
            ftt_fail("get_children_list() contains admin bypass", $errors);
        } else {
            ftt_pass("get_children_list() does NOT contain admin bypass", $passes);
        }
    }
    ftt_test_section_end();
    
    // Test 6: New Endpoints
    ftt_test_section("Test 6: New REST Endpoints");
    
    $required_endpoints = [
        '/groups' => 'GET /ftt/v1/groups',
        '/dashboard-context' => 'GET /ftt/v1/dashboard-context',
    ];
    
    foreach ($required_endpoints as $route => $description) {
        if (preg_match('/register_rest_route\s*\(\s*[\'"]ftt\/v1[\'"]\s*,\s*[\'"]\s*' . preg_quote($route, '/') . '/', $rest_content)) {
            ftt_pass("Endpoint registered: {$description}", $passes);
        } else {
            ftt_fail("Endpoint NOT found: {$description}", $errors);
        }
    }
    ftt_test_section_end();
    
    // Test 7: JavaScript REST Usage
    ftt_test_section("Test 7: JavaScript REST API Usage");
    
    $js_file = plugin_dir_path(dirname(__FILE__)) . 'assets/js/main.js';
    if (file_exists($js_file)) {
        $js_content = file_get_contents($js_file);
        
        if (preg_match_all('/fetch\([^)]*fttData\.restUrl/', $js_content, $matches)) {
            ftt_pass("Found " . count($matches[0]) . " REST API fetch calls in main.js", $passes);
        }
        
        if (preg_match_all('/X-WP-Nonce[\'"]:\s*fttData\.nonce/', $js_content, $matches)) {
            ftt_pass("Found " . count($matches[0]) . " nonce headers in API calls ✓ SECURITY", $passes);
        }
    }
    ftt_test_section_end();
    
    // Test 8: Security Patterns
    ftt_test_section("Test 8: Security Pattern Analysis");
    
    $dangerous_patterns = [
        '/current_user_can\s*\(\s*[\'"]manage_options[\'"]\s*\)\s*\)\s*\{[^}]*get.*children/s' => 'Admin bypass in conditional',
    ];
    
    foreach ($dangerous_patterns as $pattern => $description) {
        if (preg_match($pattern, $rest_content)) {
            ftt_fail("Found dangerous pattern: {$description}", $errors);
        } else {
            ftt_pass("No dangerous pattern found: {$description}", $passes);
        }
    }
    ftt_test_section_end();
    
    // Test 9: Error Handling
    ftt_test_section("Test 9: Error Handling");
    
    if (preg_match_all('/new WP_Error\(/', $rest_content, $matches)) {
        ftt_pass("Found " . count($matches[0]) . " WP_Error instances for proper error handling", $passes);
    }
    
    if (preg_match_all('/rest_ensure_response/', $rest_content, $matches)) {
        ftt_pass("Found " . count($matches[0]) . " rest_ensure_response() calls", $passes);
    }
    ftt_test_section_end();
    
    // Summary
    ftt_summary($passes, $warnings, $errors);
}

function ftt_test_section($title) {
    echo '<div class="ftt-test-section">';
    echo '<div class="ftt-test-section-header">' . esc_html($title) . '</div>';
    echo '<div class="ftt-test-results">';
}

function ftt_test_section_end() {
    echo '</div></div>';
}

function ftt_pass($message, &$passes) {
    echo '<div class="ftt-test-item">';
    echo '<span class="ftt-test-icon ftt-pass">✅</span>';
    echo '<span class="ftt-test-message"><strong>PASS:</strong> ' . esc_html($message) . '</span>';
    echo '</div>';
    $passes++;
}

function ftt_fail($message, &$errors) {
    echo '<div class="ftt-test-item">';
    echo '<span class="ftt-test-icon ftt-fail">❌</span>';
    echo '<span class="ftt-test-message"><strong>FAIL:</strong> ' . esc_html($message) . '</span>';
    echo '</div>';
    $errors++;
}

function ftt_warn($message, &$warnings) {
    echo '<div class="ftt-test-item">';
    echo '<span class="ftt-test-icon ftt-warn">⚠️</span>';
    echo '<span class="ftt-test-message"><strong>WARN:</strong> ' . esc_html($message) . '</span>';
    echo '</div>';
    $warnings++;
}

function ftt_info($message) {
    echo '<div class="ftt-test-item">';
    echo '<span class="ftt-test-icon ftt-info">ℹ️</span>';
    echo '<span class="ftt-test-message"><strong>INFO:</strong> ' . esc_html($message) . '</span>';
    echo '</div>';
}

function ftt_summary($passes, $warnings, $errors) {
    $total = $passes + $warnings + $errors;
    
    echo '<div class="ftt-test-section">';
    echo '<div class="ftt-test-section-header">VALIDATION SUMMARY</div>';
    echo '<div class="ftt-test-results">';
    
    echo '<div class="ftt-summary-stats">';
    echo '<div class="ftt-stat-card">';
    echo '<div class="ftt-stat-number">' . $total . '</div>';
    echo '<div class="ftt-stat-label">Total Tests</div>';
    echo '</div>';
    echo '<div class="ftt-stat-card">';
    echo '<div class="ftt-stat-number ftt-pass">' . $passes . '</div>';
    echo '<div class="ftt-stat-label">✅ Passed</div>';
    echo '</div>';
    echo '<div class="ftt-stat-card">';
    echo '<div class="ftt-stat-number ftt-warn">' . $warnings . '</div>';
    echo '<div class="ftt-stat-label">⚠️ Warnings</div>';
    echo '</div>';
    echo '<div class="ftt-stat-card">';
    echo '<div class="ftt-stat-number ftt-fail">' . $errors . '</div>';
    echo '<div class="ftt-stat-label">❌ Failed</div>';
    echo '</div>';
    echo '</div>';
    
    if ($errors === 0 && $warnings === 0) {
        echo '<div class="ftt-badge ftt-badge-success">🎉 ALL TESTS PASSED! Security implementation looks solid.</div>';
    } elseif ($errors === 0) {
        echo '<div class="ftt-badge ftt-badge-warning">✅ No critical failures, but ' . $warnings . ' warnings to review.</div>';
    } else {
        echo '<div class="ftt-badge ftt-badge-error">❌ ' . $errors . ' CRITICAL FAILURES detected. Review immediately!</div>';
    }
    
    echo '</div></div>';
    
    ?>
    <div class="ftt-next-steps">
        <h3>🚀 Next Steps</h3>
        <ol>
            <li>Review any failed tests above</li>
            <li>Test with real user: Login as admin (ID=2), verify only children [50, 51] shown</li>
            <li>Navigate to /ftt-manage-events/ and verify children dropdown is restricted</li>
            <li>Check browser console (F12) for REST API calls with correct data</li>
            <li>Verify calendar shows only user's children in filter</li>
            <li>Check error_log for security debug messages</li>
            <li>Create/edit an event to test form functionality</li>
            <li>If all manual tests pass, merge rest-refactor branch to main</li>
        </ol>
    </div>
    
    <div class="ftt-timestamp">
        <?php echo 'Validation completed: ' . current_time('mysql'); ?>
        <br>
        Branch: rest-refactor | Plugin: Family Travel Tracker v3.0.16
    </div>
    <?php
}
