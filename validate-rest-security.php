#!/usr/bin/env php
<?php
/**
 * REST API Security Code Validation Script
 * 
 * Validates the REST API refactoring changes without requiring WordPress
 * Checks code structure, security patterns, and endpoint configurations
 *
 * Usage: 
 *   CLI: php validate-rest-security.php
 *   Web: Navigate to script URL in browser
 *
 * @package Family_Travel_Tracker
 */

// Detect execution mode
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    // Web mode - output HTML
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>REST API Security Validation</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 20px;
                min-height: 100vh;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                font-size: 32px;
                margin-bottom: 10px;
            }
            .header p {
                font-size: 16px;
                opacity: 0.9;
            }
            .content {
                padding: 30px;
            }
            .test-section {
                margin-bottom: 30px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                overflow: hidden;
            }
            .test-section-header {
                background: #f7fafc;
                padding: 15px 20px;
                font-weight: 600;
                font-size: 18px;
                color: #2d3748;
                border-bottom: 2px solid #e2e8f0;
            }
            .test-results {
                padding: 15px 20px;
            }
            .test-item {
                padding: 10px 0;
                border-bottom: 1px solid #f7fafc;
                display: flex;
                align-items: flex-start;
            }
            .test-item:last-child {
                border-bottom: none;
            }
            .test-icon {
                margin-right: 12px;
                font-size: 20px;
                flex-shrink: 0;
            }
            .test-message {
                flex: 1;
                line-height: 1.6;
            }
            .pass { color: #48bb78; }
            .fail { color: #f56565; }
            .warn { color: #ed8936; }
            .info { color: #4299e1; }
            .summary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                border-radius: 8px;
                margin: 30px 0;
            }
            .summary-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .stat-card {
                background: rgba(255,255,255,0.1);
                padding: 20px;
                border-radius: 8px;
                text-align: center;
            }
            .stat-number {
                font-size: 48px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .stat-label {
                font-size: 14px;
                opacity: 0.9;
            }
            .next-steps {
                background: #f7fafc;
                padding: 20px;
                border-radius: 8px;
                margin-top: 20px;
            }
            .next-steps h3 {
                margin-bottom: 15px;
                color: #2d3748;
            }
            .next-steps ol {
                margin-left: 20px;
            }
            .next-steps li {
                margin-bottom: 8px;
                color: #4a5568;
            }
            .badge {
                display: inline-block;
                padding: 5px 15px;
                border-radius: 20px;
                font-size: 14px;
                font-weight: 600;
                margin-top: 10px;
            }
            .badge-success {
                background: #c6f6d5;
                color: #22543d;
            }
            .badge-warning {
                background: #feebc8;
                color: #7c2d12;
            }
            .badge-error {
                background: #fed7d7;
                color: #742a2a;
            }
            .timestamp {
                text-align: center;
                color: #a0aec0;
                font-size: 14px;
                margin-top: 20px;
                padding: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔒 REST API Security Validation</h1>
                <p>Code Analysis & Security Pattern Verification</p>
            </div>
            <div class="content">
    <?php
}

$errors = 0;
$warnings = 0;
$passes = 0;

function test_section($title) {
    global $is_cli;
    if ($is_cli) {
        echo "\n" . str_repeat("─", 60) . "\n";
        echo "  {$title}\n";
        echo str_repeat("─", 60) . "\n";
    } else {
        echo '<div class="test-section">';
        echo '<div class="test-section-header">' . htmlspecialchars($title) . '</div>';
        echo '<div class="test-results">';
    }
}

function test_section_end() {
    global $is_cli;
    if (!$is_cli) {
        echo '</div></div>';
    }
}

function pass($message) {
    global $passes, $is_cli;
    if ($is_cli) {
        echo "  ✅ PASS: {$message}\n";
    } else {
        echo '<div class="test-item">';
        echo '<span class="test-icon pass">✅</span>';
        echo '<span class="test-message"><strong>PASS:</strong> ' . htmlspecialchars($message) . '</span>';
        echo '</div>';
    }
    $passes++;
}

function fail($message) {
    global $errors, $is_cli;
    if ($is_cli) {
        echo "  ❌ FAIL: {$message}\n";
    } else {
        echo '<div class="test-item">';
        echo '<span class="test-icon fail">❌</span>';
        echo '<span class="test-message"><strong>FAIL:</strong> ' . htmlspecialchars($message) . '</span>';
        echo '</div>';
    }
    $errors++;
}

function warn($message) {
    global $warnings, $is_cli;
    if ($is_cli) {
        echo "  ⚠️  WARN: {$message}\n";
    } else {
        echo '<div class="test-item">';
        echo '<span class="test-icon warn">⚠️</span>';
        echo '<span class="test-message"><strong>WARN:</strong> ' . htmlspecialchars($message) . '</span>';
        echo '</div>';
    }
    $warnings++;
}

function info($message) {
    global $is_cli;
    if ($is_cli) {
        echo "  ℹ️  INFO: {$message}\n";
    } else {
        echo '<div class="test-item">';
        echo '<span class="test-icon info">ℹ️</span>';
        echo '<span class="test-message"><strong>INFO:</strong> ' . htmlspecialchars($message) . '</span>';
        echo '</div>';
    }
}

// Start output
if ($is_cli) {
    echo "\n";
    echo "================================================================\n";
    echo "  REST API Security Validation - Code Analysis\n";
    echo "================================================================\n\n";
}

// Test 1: Verify REST API file exists and is readable
test_section("Test 1: REST API File Structure");

$rest_file = __DIR__ . '/includes/rest.php';
if (file_exists($rest_file)) {
    pass("REST API file exists: includes/rest.php");
    $rest_content = file_get_contents($rest_file);
} else {
    fail("REST API file not found: includes/rest.php");
    exit(1);
}
test_section_end();

// Test 2: Check for permission callbacks on all endpoints
test_section("Test 2: Permission Callbacks on Endpoints");

$endpoint_pattern = '/register_rest_route\s*\(\s*[\'"]ftt\/v1[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*,\s*array\s*\((.*?)\)\s*\)/s';
preg_match_all($endpoint_pattern, $rest_content, $endpoints, PREG_SET_ORDER);

info("Found " . count($endpoints) . " REST endpoint registrations");

$endpoints_without_permission = [];
foreach ($endpoints as $endpoint) {
    $route = $endpoint[1];
    $args = $endpoint[2];
    
    if (strpos($args, 'permission_callback') !== false) {
        pass("Endpoint /{$route} has permission_callback");
    } else {
        fail("Endpoint /{$route} MISSING permission_callback");
        $endpoints_without_permission[] = $route;
    }
}

if (count($endpoints_without_permission) > 0) {
    info("Endpoints without permission callbacks: " . implode(', ', $endpoints_without_permission));
}
test_section_end();

// Test 3: Check for is_user_logged_in usage
test_section("Test 3: Authentication Requirements");

if (preg_match_all('/"permission_callback"\s*=>\s*[\'"]is_user_logged_in[\'"]/', $rest_content, $matches)) {
    $count = count($matches[0]);
    pass("Found {$count} endpoints requiring user login (is_user_logged_in)");
} else {
    warn("No endpoints found using is_user_logged_in permission callback");
}

if (preg_match_all('/permission_callback.*check_read_permission/', $rest_content, $matches)) {
    $count = count($matches[0]);
    pass("Found {$count} endpoints using check_read_permission");
} else {
    warn("No endpoints found using check_read_permission");
}

if (preg_match_all('/permission_callback.*check_edit_permission/', $rest_content, $matches)) {
    $count = count($matches[0]);
    pass("Found {$count} endpoints using check_edit_permission");
} else {
    warn("No endpoints found using check_edit_permission");
}
test_section_end();

// Test 4: Check for group-based filtering in get_events
test_section("Test 4: Group-Based Data Filtering (get_events)");

if (preg_match('/function\s+get_events\s*\([^)]*\)\s*\{/s', $rest_content, $match, PREG_OFFSET_CAPTURE)) {
    $start = $match[0][1];
    // Find the closing brace (simplified - just get a large chunk)
    $method_content = substr($rest_content, $start, 5000);
    
    // Check for critical security patterns
    if (strpos($method_content, 'FTT_Family_Groups::get_user_children') !== false) {
        pass("get_events() uses FTT_Family_Groups::get_user_children() for filtering");
    } else {
        fail("get_events() does NOT use FTT_Family_Groups::get_user_children()");
    }
    
    if (strpos($method_content, 'is_parent') !== false) {
        pass("get_events() checks is_parent role");
    } else {
        warn("get_events() may not check is_parent role");
    }
    
    if (strpos($method_content, 'is_member') !== false) {
        pass("get_events() checks is_member role");
    } else {
        warn("get_events() may not check is_member role");
    }
    
    // Check for admin bypass (should NOT exist)
    if (preg_match('/current_user_can\s*\(\s*[\'"]manage_options[\'"]/', $method_content)) {
        fail("get_events() contains admin bypass using current_user_can('manage_options')");
    } else {
        pass("get_events() does NOT contain admin bypass");
    }
    
    // Check for proper empty returns
    if (strpos($method_content, 'return rest_ensure_response(array())') !== false) {
        pass("get_events() returns empty arrays for unauthorized access");
    } else {
        warn("get_events() may not properly return empty arrays");
    }
} else {
    fail("get_events() method not found");
}
test_section_end();

// Test 5: Check get_children_list endpoint
test_section("Test 5: Children Endpoint Security (get_children_list)");

if (preg_match('/function\s+get_children_list\s*\([^)]*\)\s*\{/s', $rest_content, $match, PREG_OFFSET_CAPTURE)) {
    $start = $match[0][1];
    $method_content = substr($rest_content, $start, 3000);
    
    if (strpos($method_content, 'get_current_user_id()') !== false) {
        pass("get_children_list() uses get_current_user_id()");
    } else {
        fail("get_children_list() does NOT use get_current_user_id()");
    }
    
    if (strpos($method_content, 'FTT_Family_Groups::get_user_children') !== false) {
        pass("get_children_list() uses FTT_Family_Groups::get_user_children()");
    } else {
        fail("get_children_list() does NOT use FTT_Family_Groups::get_user_children()");
    }
    
    // Check for admin bypass (should NOT exist)
    if (preg_match('/current_user_can\s*\(\s*[\'"]manage_options[\'"]/', $method_content)) {
        fail("get_children_list() contains admin bypass");
    } else {
        pass("get_children_list() does NOT contain admin bypass");
    }
} else {
    fail("get_children_list() method not found");
}
test_section_end();

// Test 6: Check new endpoints added in refactoring
test_section("Test 6: New REST Endpoints");

$required_endpoints = [
    '/groups' => 'GET /ftt/v1/groups',
    '/dashboard-context' => 'GET /ftt/v1/dashboard-context',
];

foreach ($required_endpoints as $route => $description) {
    if (preg_match('/register_rest_route\s*\(\s*[\'"]ftt\/v1[\'"]\s*,\s*[\'"]\s*' . preg_quote($route, '/') . '/', $rest_content)) {
        pass("Endpoint registered: {$description}");
    } else {
        fail("Endpoint NOT found: {$description}");
    }
}
test_section_end();

// Test 7: Check get_user_groups endpoint
test_section("Test 7: Groups Endpoint (get_user_groups)");

if (preg_match('/function\s+get_user_groups\s*\([^)]*\)\s*\{/s', $rest_content, $match, PREG_OFFSET_CAPTURE)) {
    $start = $match[0][1];
    $method_content = substr($rest_content, $start, 3000);
    
    if (strpos($method_content, 'get_current_user_id()') !== false) {
        pass("get_user_groups() uses get_current_user_id()");
    } else {
        fail("get_user_groups() does NOT use get_current_user_id()");
    }
    
    if (strpos($method_content, 'FTT_Family_Groups::get_user_groups') !== false) {
        pass("get_user_groups() uses FTT_Family_Groups::get_user_groups()");
    } else {
        fail("get_user_groups() does NOT use FTT_Family_Groups::get_user_groups()");
    }
    
    // Check returns billing info
    if (preg_match('/billing_owner|can_manage/', $method_content)) {
        pass("get_user_groups() includes additional group metadata");
    } else {
        warn("get_user_groups() may not include billing/permission metadata");
    }
} else {
    fail("get_user_groups() method not found");
}
test_section_end();

// Test 8: Check dashboard-context endpoint
test_section("Test 8: Dashboard Context Endpoint");

if (preg_match('/function\s+get_dashboard_context\s*\([^)]*\)\s*\{/s', $rest_content, $match, PREG_OFFSET_CAPTURE)) {
    $start = $match[0][1];
    $method_content = substr($rest_content, $start, 5000);
    
    pass("get_dashboard_context() method exists");
    
    if (preg_match('/is_member|is_parent/', $method_content)) {
        pass("get_dashboard_context() checks user roles");
    } else {
        fail("get_dashboard_context() does NOT check user roles");
    }
    
    if (preg_match('/FTT_Family_Groups::get_user_children/', $method_content)) {
        pass("get_dashboard_context() gets children via FTT_Family_Groups");
    } else {
        warn("get_dashboard_context() may not retrieve children");
    }
    
    if (preg_match('/billing|FTT_Billing_Manager/', $method_content)) {
        pass("get_dashboard_context() includes billing information");
    } else {
        warn("get_dashboard_context() may not include billing info");
    }
} else {
    fail("get_dashboard_context() method not found");
}
test_section_end();

// Test 9: Check format_event includes group data
test_section("Test 9: Event Formatting (format_event)");

if (preg_match('/function\s+format_event\s*\([^)]*\)\s*\{/s', $rest_content, $match, PREG_OFFSET_CAPTURE)) {
    $start = $match[0][1];
    $method_content = substr($rest_content, $start, 3000);
    
    pass("format_event() method exists");
    
    if (preg_match('/group_id|group_name/', $method_content)) {
        pass("format_event() includes group_id/group_name");
    } else {
        fail("format_event() does NOT include group information");
    }
    
    if (preg_match('/FTT_Family_Groups::get_group/', $method_content)) {
        pass("format_event() fetches group details");
    } else {
        warn("format_event() may not fetch full group details");
    }
} else {
    fail("format_event() method not found");
}
test_section_end();

// Test 10: Check template files for direct DB calls
test_section("Test 10: Template Refactoring Verification");

$templates_to_check = [
    'templates/event-form.php' => 'Event Form',
    'templates/calendar.php' => 'Calendar',
    'templates/dashboard.php' => 'Dashboard',
    'templates/trial-expired.php' => 'Trial Expired',
    'templates/event-view.php' => 'Event View',
    'templates/onboarding.php' => 'Onboarding',
];

foreach ($templates_to_check as $file => $name) {
    $filepath = __DIR__ . '/' . $file;
    if (file_exists($filepath)) {
        $template_content = file_get_contents($filepath);
        
        // Check for admin bypass
        if (preg_match('/current_user_can\s*\(\s*[\'"]manage_options[\'"].*&&.*get|FTT_Family_Groups/', $template_content)) {
            warn("{$name}: May contain admin checks - review manually");
        }
        
        // Check for REST API usage in JavaScript
        if (preg_match('/fttData\.restUrl|fetch\(.*\/ftt\/v1\//', $template_content)) {
            pass("{$name}: Uses REST API calls");
        } else {
            info("{$name}: May use PHP data loading (acceptable for some templates)");
        }
    } else {
        warn("{$name}: Template file not found at {$file}");
    }
}
test_section_end();

// Test 11: Check JavaScript files for proper REST usage
test_section("Test 11: JavaScript REST API Usage");

$js_file = __DIR__ . '/assets/js/main.js';
if (file_exists($js_file)) {
    $js_content = file_get_contents($js_file);
    
    if (preg_match_all('/fetch\([^)]*fttData\.restUrl/', $js_content, $matches)) {
        $count = count($matches[0]);
        pass("Found {$count} REST API fetch calls in main.js");
    } else {
        warn("No REST API fetch calls found in main.js");
    }
    
    if (preg_match_all('/X-WP-Nonce[\'"]:\s*fttData\.nonce/', $js_content, $matches)) {
        $count = count($matches[0]);
        pass("Found {$count} nonce headers in API calls");
    } else {
        fail("No nonce headers found in API calls");
    }
    
    // Check for new methods
    if (preg_match('/loadEventFormData|loadChildrenFilter/', $js_content)) {
        pass("New REST loading methods found (loadEventFormData, loadChildrenFilter)");
    } else {
        warn("New REST loading methods may not be implemented");
    }
} else {
    warn("JavaScript file not found: assets/js/main.js");
}
test_section_end();

// Test 12: Security pattern analysis
test_section("Test 12: Security Pattern Analysis");

// Check for dangerous patterns that should NOT exist
$dangerous_patterns = [
    '/(\/\*.*?\*\/|\/\/[^\n]*)?current_user_can\s*\(\s*[\'"]manage_options[\'"]\s*\)\s*\)\s*\{[^}]*get.*children/s' => 'Admin bypass pattern in conditional',
    '/SELECT\s+\*\s+FROM.*WHERE\s+1\s*=\s*1/i' => 'Potentially unsafe SQL query',
];

foreach ($dangerous_patterns as $pattern => $description) {
    if (preg_match($pattern, $rest_content)) {
        fail("Found dangerous pattern: {$description}");
    } else {
        pass("No dangerous pattern found: {$description}");
    }
}
test_section_end();

// Test 13: Check for proper error handling
test_section("Test 13: Error Handling");

if (preg_match_all('/new WP_Error\(/', $rest_content, $matches)) {
    $count = count($matches[0]);
    pass("Found {$count} WP_Error instances for proper error handling");
} else {
    warn("No WP_Error instances found - errors may not be handled properly");
}

if (preg_match_all('/rest_ensure_response/', $rest_content, $matches)) {
    $count = count($matches[0]);
    pass("Found {$count} rest_ensure_response() calls");
} else {
    fail("No rest_ensure_response() calls found");
}
test_section_end();

// Test 14: Input sanitization
test_section("Test 14: Input Sanitization");

if (preg_match_all('/sanitize_callback/', $rest_content, $matches)) {
    $count = count($matches[0]);
    pass("Found {$count} sanitize_callback definitions");
} else {
    warn("No sanitize_callback found - inputs may not be sanitized");
}

$sanitization_functions = ['absint', 'sanitize_text_field', 'sanitize_email', 'esc_sql'];
foreach ($sanitization_functions as $func) {
    if (strpos($rest_content, $func) !== false) {
        pass("Uses {$func}() for sanitization");
    }
}
test_section_end();

// Final Summary
test_section("VALIDATION SUMMARY");

$total = $passes + $warnings + $errors;

if ($is_cli) {
    echo "\n";
    echo "  Total Tests:    {$total}\n";
    echo "  ✅ Passed:      {$passes}\n";
    echo "  ⚠️  Warnings:    {$warnings}\n";
    echo "  ❌ Failed:      {$errors}\n";
    echo "\n";

    if ($errors === 0 && $warnings === 0) {
        echo "  🎉 ALL TESTS PASSED! Security implementation looks solid.\n";
        $exit_code = 0;
    } elseif ($errors === 0) {
        echo "  ✅ No critical failures, but {$warnings} warnings to review.\n";
        $exit_code = 0;
    } else {
        echo "  ❌ {$errors} CRITICAL FAILURES detected. Review immediately!\n";
        $exit_code = 1;
    }

    echo "\n";
    echo "================================================================\n";
    echo "  Validation Complete\n";
    echo "================================================================\n\n";

    echo "Next Steps:\n";
    echo "1. Review any failed tests above\n";
    echo "2. Check warnings for potential issues\n";
    echo "3. Run functional tests in WordPress environment\n";
    echo "4. Test with real user data (user ID 2, children 50, 51)\n";
    echo "5. Verify browser console shows correct REST calls\n";
    echo "\n";
} else {
    // Web mode - nice HTML summary
    echo '<div class="summary-stats">';
    echo '<div class="stat-card">';
    echo '<div class="stat-number">' . $total . '</div>';
    echo '<div class="stat-label">Total Tests</div>';
    echo '</div>';
    echo '<div class="stat-card">';
    echo '<div class="stat-number pass">' . $passes . '</div>';
    echo '<div class="stat-label">✅ Passed</div>';
    echo '</div>';
    echo '<div class="stat-card">';
    echo '<div class="stat-number warn">' . $warnings . '</div>';
    echo '<div class="stat-label">⚠️ Warnings</div>';
    echo '</div>';
    echo '<div class="stat-card">';
    echo '<div class="stat-number fail">' . $errors . '</div>';
    echo '<div class="stat-label">❌ Failed</div>';
    echo '</div>';
    echo '</div>';
    
    if ($errors === 0 && $warnings === 0) {
        echo '<div class="badge badge-success">🎉 ALL TESTS PASSED! Security implementation looks solid.</div>';
        $exit_code = 0;
    } elseif ($errors === 0) {
        echo '<div class="badge badge-warning">✅ No critical failures, but ' . $warnings . ' warnings to review.</div>';
        $exit_code = 0;
    } else {
        echo '<div class="badge badge-error">❌ ' . $errors . ' CRITICAL FAILURES detected. Review immediately!</div>';
        $exit_code = 1;
    }
}
test_section_end();

if (!$is_cli) {
    ?>
                <div class="next-steps">
                    <h3>🚀 Next Steps</h3>
                    <ol>
                        <li>Review any failed tests above</li>
                        <li>Check warnings for potential issues</li>
                        <li>Run functional tests in WordPress environment</li>
                        <li>Test with real user data (user ID 2, children 50, 51)</li>
                        <li>Verify browser console shows correct REST calls</li>
                        <li>Check error_log for security debug messages</li>
                        <li>Validate event form works (create/edit events)</li>
                        <li>Confirm calendar displays only user's children</li>
                    </ol>
                </div>
                
                <div class="timestamp">
                    <?php echo 'Validation completed: ' . date('F j, Y g:i:s A'); ?>
                    <br>
                    Branch: rest-refactor | Plugin: Family Travel Tracker v3.0.16
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

exit($exit_code);
