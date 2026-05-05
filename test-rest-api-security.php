<?php
/**
 * REST API Security & Functionality Validation Script
 * 
 * Tests all REST API endpoints for:
 * - Authentication requirements
 * - Group-based data filtering
 * - Complete data responses
 * - Security isolation between families
 *
 * Usage:
 * 1. Via CLI: php test-rest-api-security.php
 * 2. Via URL: https://yoursite.com/test-rest-api-security.php
 * 3. With WordPress: Load in wp-admin for live testing
 *
 * @package Family_Travel_Tracker
 */

// Detect execution context
$is_cli = php_sapi_name() === 'cli';
$in_wordpress = function_exists('add_action') && defined('ABSPATH');

if (!$is_cli) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><title>REST API Security Tests</title>';
    echo '<style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .pass { color: #4ec9b0; }
        .fail { color: #f48771; }
        .warn { color: #dcdcaa; }
        .section { border-left: 3px solid #569cd6; padding-left: 15px; margin: 20px 0; }
        pre { background: #2d2d2d; padding: 10px; overflow-x: auto; }
        h1 { color: #569cd6; }
        h2 { color: #4ec9b0; border-bottom: 2px solid #4ec9b0; padding-bottom: 5px; }
    </style></head><body>';
}

if (!$in_wordpress && $is_cli) {
    echo "\n⚠️  This script requires WordPress context for full testing.\n";
    echo "Loading WordPress...\n\n";
    
    // Try to locate wp-load.php
    $wp_load_paths = [
        __DIR__ . '/wp-load.php',
        __DIR__ . '/../wp-load.php',
        __DIR__ . '/../../wp-load.php',
        __DIR__ . '/../../../wp-load.php',
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        echo "❌ Could not locate WordPress. Please run from WordPress root or specify path.\n";
        exit(1);
    }
}

echo $is_cli ? "\n" : "";
echo $is_cli ? "╔════════════════════════════════════════════════════════════════╗\n" : "<h1>";
echo $is_cli ? "║  REST API Security & Functionality Tests                      ║\n" : "REST API Security & Functionality Tests";
echo $is_cli ? "╚════════════════════════════════════════════════════════════════╝\n" : "</h1>";
echo $is_cli ? "\n" : "";

// Test tracking
$tests_passed = 0;
$tests_failed = 0;
$tests_warning = 0;
$test_results = [];
$security_issues = [];

/**
 * Output helper
 */
function output($msg, $status = 'info') {
    global $is_cli;
    
    if ($is_cli) {
        echo $msg . "\n";
    } else {
        $class = $status === 'pass' ? 'pass' : ($status === 'fail' ? 'fail' : ($status === 'warn' ? 'warn' : ''));
        echo "<div class='$class'>" . esc_html($msg) . "</div>";
    }
}

/**
 * Section header
 */
function section($title) {
    global $is_cli;
    
    if ($is_cli) {
        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "  {$title}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    } else {
        echo "<h2>{$title}</h2><div class='section'>";
    }
}

/**
 * Test helper function
 */
function run_test($name, $callback, $critical = false) {
    global $tests_passed, $tests_failed, $tests_warning, $test_results, $security_issues;
    
    output("Testing: {$name}... ", 'info');
    
    try {
        $result = $callback();
        
        if ($result === true) {
            output("   ✅ PASS", 'pass');
            $tests_passed++;
            $test_results[] = ['name' => $name, 'status' => 'pass', 'critical' => $critical];
        } else if (is_array($result) && isset($result['warning'])) {
            output("   ⚠️  WARNING: " . $result['warning'], 'warn');
            $tests_warning++;
            $test_results[] = ['name' => $name, 'status' => 'warning', 'message' => $result['warning']];
        } else {
            output("   ❌ FAIL", 'fail');
            $reason = is_string($result) ? $result : 'Test returned false';
            output("      Reason: {$reason}", 'fail');
            $tests_failed++;
            $test_results[] = ['name' => $name, 'status' => 'fail', 'reason' => $reason, 'critical' => $critical];
            
            if ($critical) {
                $security_issues[] = $name . ': ' . $reason;
            }
        }
    } catch (Exception $e) {
        output("   ❌ EXCEPTION: " . $e->getMessage(), 'fail');
        $tests_failed++;
        $test_results[] = ['name' => $name, 'status' => 'exception', 'reason' => $e->getMessage()];
        
        if ($critical) {
            $security_issues[] = $name . ': ' . $e->getMessage();
        }
    }
}

// ============================================================================
// SECTION 1: Verify REST Endpoint Registration
// ============================================================================
section('REST Endpoint Registration');

run_test('FTT_REST class exists', function() {
    return class_exists('FTT_REST');
});

run_test('REST routes are registered', function() {
    $routes = rest_get_server()->get_routes();
    $ftt_routes = array_filter(array_keys($routes), function($route) {
        return strpos($route, '/ftt/v1/') === 0;
    });
    
    if (count($ftt_routes) === 0) {
        return 'No FTT routes found';
    }
    
    output("      Found " . count($ftt_routes) . " FTT REST routes", 'info');
    return true;
});

run_test('GET /ftt/v1/children endpoint exists', function() {
    $routes = rest_get_server()->get_routes();
    return isset($routes['/ftt/v1/children']);
});

run_test('GET /ftt/v1/events endpoint exists', function() {
    $routes = rest_get_server()->get_routes();
    return isset($routes['/ftt/v1/events']);
});

run_test('GET /ftt/v1/groups endpoint exists', function() {
    $routes = rest_get_server()->get_routes();
    return isset($routes['/ftt/v1/groups']);
});

run_test('GET /ftt/v1/dashboard-context endpoint exists', function() {
    $routes = rest_get_server()->get_routes();
    return isset($routes['/ftt/v1/dashboard-context']);
});

run_test('GET /ftt/v1/user-preferences endpoint exists', function() {
    $routes = rest_get_server()->get_routes();
    return isset($routes['/ftt/v1/user-preferences']);
});

// ============================================================================
// SECTION 2: Authentication Requirements (CRITICAL SECURITY)
// ============================================================================
section('Authentication & Authorization');

run_test('Children endpoint requires authentication', function() {
    $routes = rest_get_server()->get_routes();
    $route = $routes['/ftt/v1/children'] ?? null;
    
    if (!$route) return 'Route not found';
    
    foreach ($route as $endpoint) {
        if ($endpoint['methods']['GET']) {
            $callback = $endpoint['permission_callback'];
            if ($callback === '__return_true') {
                return 'SECURITY ISSUE: Endpoint allows unauthenticated access';
            }
            if ($callback === 'is_user_logged_in' || is_callable($callback)) {
                return true;
            }
        }
    }
    
    return 'Could not verify permission callback';
}, true); // Critical security test

run_test('Events endpoint requires authentication', function() {
    $routes = rest_get_server()->get_routes();
    $route = $routes['/ftt/v1/events'] ?? null;
    
    if (!$route) return 'Route not found';
    
    foreach ($route as $endpoint) {
        if (isset($endpoint['methods']['GET'])) {
            $callback = $endpoint['permission_callback'];
            if ($callback === '__return_true') {
                return 'SECURITY ISSUE: Endpoint allows unauthenticated access';
            }
            return true;
        }
    }
    
    return 'Could not verify permission callback';
}, true);

run_test('Groups endpoint requires authentication', function() {
    $routes = rest_get_server()->get_routes();
    $route = $routes['/ftt/v1/groups'] ?? null;
    
    if (!$route) return 'Route not found';
    
    foreach ($route as $endpoint) {
        if (isset($endpoint['methods']['GET'])) {
            $callback = $endpoint['permission_callback'];
            if ($callback === '__return_true') {
                return 'SECURITY ISSUE: Endpoint allows unauthenticated access';
            }
            if ($callback === 'is_user_logged_in') {
                return true;
            }
        }
    }
    
    return 'Could not verify authentication requirement';
}, true);

run_test('Dashboard-context endpoint requires authentication', function() {
    $routes = rest_get_server()->get_routes();
    $route = $routes['/ftt/v1/dashboard-context'] ?? null;
    
    if (!$route) return 'Route not found';
    
    foreach ($route as $endpoint) {
        if (isset($endpoint['methods']['GET'])) {
            $callback = $endpoint['permission_callback'];
            if ($callback === '__return_true') {
                return 'SECURITY ISSUE: Endpoint allows unauthenticated access';
            }
            if ($callback === 'is_user_logged_in') {
                return true;
            }
        }
    }
    
    return 'Could not verify authentication requirement';
}, true);

// ============================================================================
// SECTION 3: Test With Actual Users (IF WordPress loaded)
// ============================================================================
section('Live User Testing (Group-Based Security)');

if (!is_user_logged_in()) {
    output("⚠️  No user logged in - skipping live API tests", 'warn');
    output("   To test with real data, access this page while logged in", 'warn');
    $tests_warning++;
} else {
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    
    output("Testing as: {$current_user->display_name} (ID: {$current_user_id})", 'info');
    
    // Test 1: GET /ftt/v1/children
    run_test('GET /ftt/v1/children returns data', function() use ($current_user_id) {
        $request = new WP_REST_Request('GET', '/ftt/v1/children');
        $response = rest_do_request($request);
        
        if ($response->is_error()) {
            return 'API returned error: ' . $response->as_error()->get_error_message();
        }
        
        $data = $response->get_data();
        
        if (!isset($data['children'])) {
            return 'Response missing children array';
        }
        
        $children = $data['children'];
        output("      Found " . count($children) . " children", 'info');
        
        // Verify children belong to user's groups
        if (!empty($children)) {
            $child_ids = array_column($children, 'id');
            $user_children = FTT_Family_Groups::get_user_children($current_user_id);
            
            foreach ($child_ids as $child_id) {
                if (!in_array($child_id, $user_children)) {
                    return "SECURITY ISSUE: Child ID {$child_id} not in user's groups";
                }
            }
            
            output("      ✓ All children verified in user's groups", 'pass');
        }
        
        return true;
    }, true);
    
    // Test 2: GET /ftt/v1/groups
    run_test('GET /ftt/v1/groups returns data', function() use ($current_user_id) {
        $request = new WP_REST_Request('GET', '/ftt/v1/groups');
        $response = rest_do_request($request);
        
        if ($response->is_error()) {
            return 'API returned error: ' . $response->as_error()->get_error_message();
        }
        
        $data = $response->get_data();
        
        if (!isset($data['groups'])) {
            return 'Response missing groups array';
        }
        
        $groups = $data['groups'];
        output("      Found " . count($groups) . " groups", 'info');
        
        // Verify group structure
        if (!empty($groups)) {
            $first_group = $groups[0];
            $required_fields = ['id', 'name', 'child_count', 'parent_count', 'is_primary', 'can_manage'];
            
            foreach ($required_fields as $field) {
                if (!isset($first_group[$field])) {
                    return "Missing required field: {$field}";
                }
            }
            
            output("      ✓ Group data structure complete", 'pass');
        }
        
        if (isset($data['primary_group_id'])) {
            output("      Primary group ID: " . $data['primary_group_id'], 'info');
        }
        
        return true;
    });
    
    // Test 3: GET /ftt/v1/dashboard-context
    run_test('GET /ftt/v1/dashboard-context returns comprehensive data', function() use ($current_user_id) {
        $request = new WP_REST_Request('GET', '/ftt/v1/dashboard-context');
        $response = rest_do_request($request);
        
        if ($response->is_error()) {
            return 'API returned error: ' . $response->as_error()->get_error_message();
        }
        
        $data = $response->get_data();
        
        // Check required top-level keys
        $required_keys = ['user', 'groups', 'children', 'primary_group_id'];
        foreach ($required_keys as $key) {
            if (!isset($data[$key])) {
                return "Missing required key: {$key}";
            }
        }
        
        // Check user object
        if (!isset($data['user']['id']) || !isset($data['user']['is_parent']) || !isset($data['user']['is_member'])) {
            return 'User object missing required fields';
        }
        
        output("      User roles: parent=" . ($data['user']['is_parent'] ? 'yes' : 'no') . 
               ", member=" . ($data['user']['is_member'] ? 'yes' : 'no'), 'info');
        output("      Groups: " . count($data['groups']), 'info');
        output("      Children: " . count($data['children']), 'info');
        
        // Verify children data completeness
        if (!empty($data['children'])) {
            $first_child = $data['children'][0];
            $child_fields = ['id', 'display_name', 'email'];
            
            foreach ($child_fields as $field) {
                if (!isset($first_child[$field])) {
                    return "Child data missing field: {$field}";
                }
            }
            
            output("      ✓ Children data structure complete", 'pass');
        }
        
        return true;
    });
    
    // Test 4: GET /ftt/v1/events
    run_test('GET /ftt/v1/events enforces group-based filtering', function() use ($current_user_id) {
        $request = new WP_REST_Request('GET', '/ftt/v1/events');
        $response = rest_do_request($request);
        
        if ($response->is_error()) {
            return 'API returned error: ' . $response->as_error()->get_error_message();
        }
        
        $events = $response->get_data();
        output("      Found " . count($events) . " events", 'info');
        
        if (empty($events)) {
            return ['warning' => 'No events found (user may not have any)'];
        }
        
        // Verify event structure includes group info
        $first_event = $events[0];
        
        // Check for essential fields
        if (!isset($first_event['id']) || !isset($first_event['title'])) {
            return 'Event missing basic fields (id, title)';
        }
        
        // Check for new group fields
        if (!isset($first_event['group_id']) && !isset($first_event['member_id'])) {
            return 'Event missing group_id or member_id';
        }
        
        output("      ✓ Events include group/member data", 'pass');
        
        // Verify all events belong to user's children or self
        $is_parent = FTT_Family_Groups::is_parent($current_user_id);
        $is_member = FTT_Roles::is_member($current_user_id);
        
        if ($is_parent) {
            $user_children = FTT_Family_Groups::get_user_children($current_user_id);
            
            foreach ($events as $event) {
                if (isset($event['member_id']) && $event['member_id']) {
                    if (!in_array($event['member_id'], $user_children)) {
                        return "SECURITY ISSUE: Event for child ID {$event['member_id']} not in user's groups";
                    }
                }
            }
            
            output("      ✓ All events belong to user's children", 'pass');
        } elseif ($is_member) {
            foreach ($events as $event) {
                if (isset($event['member_id']) && $event['member_id'] != $current_user_id) {
                    return "SECURITY ISSUE: Member seeing event for different user ID {$event['member_id']}";
                }
            }
            
            output("      ✓ All events belong to current member", 'pass');
        }
        
        return true;
    }, true);
    
    // Test 5: GET /ftt/v1/user-preferences
    run_test('GET /ftt/v1/user-preferences returns user data', function() {
        $request = new WP_REST_Request('GET', '/ftt/v1/user-preferences');
        $response = rest_do_request($request);
        
        if ($response->is_error()) {
            return 'API returned error: ' . $response->as_error()->get_error_message();
        }
        
        $data = $response->get_data();
        
        // Check for expected fields (may be empty)
        if (!isset($data['home_airport']) && !isset($data['timezone'])) {
            return ['warning' => 'No preference fields returned'];
        }
        
        output("      Home airport: " . ($data['home_airport'] ?? 'not set'), 'info');
        output("      Timezone: " . ($data['timezone'] ?? 'not set'), 'info');
        
        return true;
    });
}

// ============================================================================
// SECTION 4: Code-Level Security Verification
// ============================================================================
section('Code-Level Security Checks');

run_test('Templates do not bypass REST API security', function() {
    $templates_to_check = [
        'templates/event-form.php',
        'templates/calendar.php',
        'templates/dashboard.php',
    ];
    
    $security_bypasses = [];
    
    foreach ($templates_to_check as $template) {
        $file = __DIR__ . '/' . $template;
        if (!file_exists($file)) continue;
        
        $content = file_get_contents($file);
        
        // Check for admin bypass patterns
        if (preg_match('/current_user_can\s*\(\s*[\'"]manage_options[\'"]\s*\).*get_all/i', $content)) {
            $security_bypasses[] = $template . ': Admin capability check with get_all pattern';
        }
        
        // Check for direct database queries bypassing groups
        if (preg_match('/\$wpdb->get_(?:results|col|var|row).*wp_users.*ftt_/i', $content)) {
            // This might be a direct query bypassing group filtering
            if (!preg_match('/FTT_Family_Groups::/i', $content)) {
                $security_bypasses[] = $template . ': Direct database query without group filtering';
            }
        }
    }
    
    if (!empty($security_bypasses)) {
        return 'Found potential security bypasses: ' . implode(', ', $security_bypasses);
    }
    
    return true;
}, true);

run_test('REST API enforces group-based filtering in code', function() {
    $rest_file = __DIR__ . '/includes/rest.php';
    if (!file_exists($rest_file)) return 'rest.php not found';
    
    $content = file_get_contents($rest_file);
    
    // Check get_events method has group filtering
    if (!preg_match('/function\s+get_events.*?get_user_children/s', $content)) {
        return 'get_events() may not use get_user_children() for filtering';
    }
    
    // Check for is_parent and is_member logic
    if (!preg_match('/is_parent.*?is_member/s', $content) && !preg_match('/is_member.*?is_parent/s', $content)) {
        return 'REST API may not check both parent and member roles';
    }
    
    // Check that admin bypass is NOT present
    if (preg_match('/current_user_can\s*\(\s*[\'"]manage_options[\'"]\s*\).*?return.*?get_all/is', $content)) {
        return 'SECURITY ISSUE: Admin bypass found in REST API';
    }
    
    return true;
}, true);

run_test('format_event() includes group information', function() {
    $rest_file = __DIR__ . '/includes/rest.php';
    if (!file_exists($rest_file)) return 'rest.php not found';
    
    $content = file_get_contents($rest_file);
    
    // Check that format_event adds group_id and group_name
    if (!preg_match('/group_id.*?group_name/s', $content) && !preg_match('/group_name.*?group_id/s', $content)) {
        return 'format_event() may not include group information';
    }
    
    return true;
});

run_test('Dashboard template removes admin frontend bypass', function() {
    $file = __DIR__ . '/templates/dashboard.php';
    if (!file_exists($file)) return 'dashboard.php not found';
    
    $content = file_get_contents($file);
    
    // Check that is_admin is not set via manage_options
    if (preg_match('/\$is_admin\s*=.*?current_user_can\s*\(\s*[\'"]manage_options[\'"]\s*\)/i', $content)) {
        return 'SECURITY ISSUE: Dashboard still has admin frontend bypass';
    }
    
    // Verify it's either false or not checking manage_options
    if (preg_match('/\$is_admin\s*=\s*false/i', $content)) {
        return true;
    }
    
    // Or verify it's commented out
    if (preg_match('/\/\/.*\$is_admin.*manage_options/i', $content)) {
        return true;
    }
    
    return ['warning' => 'Could not verify admin bypass removal'];
});

// ============================================================================
// SECTION 5: JavaScript REST API Usage
// ============================================================================
section('JavaScript REST API Integration');

run_test('main.js uses REST API for children', function() {
    $file = __DIR__ . '/assets/js/main.js';
    if (!file_exists($file)) return 'main.js not found';
    
    $content = file_get_contents($file);
    
    // Check for loadChildrenFilter or loadEventFormData
    if (!preg_match('/(loadChildrenFilter|loadEventFormData)/i', $content)) {
        return 'main.js may not have REST API loading methods';
    }
    
    // Check for REST API calls to /children
    if (!preg_match('/fttData\.restUrl.*?[\'"]children[\'"]/i', $content)) {
        return 'main.js may not call /children endpoint';
    }
    
    // Check for nonce header
    if (!preg_match('/X-WP-Nonce.*?fttData\.nonce/i', $content)) {
        return 'main.js may not include nonce in requests';
    }
    
    return true;
});

run_test('Event form loads data via REST', function() {
    $file = __DIR__ . '/templates/event-form.php';
    if (!file_exists($file)) return 'event-form.php not found';
    
    $content = file_get_contents($file);
    
    // Should NOT have direct PHP get_user_children calls
    if (preg_match('/FTT_Family_Groups::get_user_children.*?foreach/is', $content)) {
        return 'event-form.php still uses direct PHP to load children';
    }
    
    // Should have empty containers for JS to populate
    if (!preg_match('/id=[\'"]ftt-member-checkboxes/i', $content)) {
        return 'event-form.php missing JavaScript target containers';
    }
    
    return true;
});

// ============================================================================
// FINAL SUMMARY
// ============================================================================
if (!$is_cli) {
    echo "</div>"; // Close last section
}

section('Test Results Summary');

$total_tests = $tests_passed + $tests_failed + $tests_warning;

output("Total Tests Run: {$total_tests}", 'info');
output("✅ Passed: {$tests_passed}", 'pass');
output("❌ Failed: {$tests_failed}", 'fail');
output("⚠️  Warnings: {$tests_warning}", 'warn');

$pass_rate = $total_tests > 0 ? round(($tests_passed / $total_tests) * 100, 1) : 0;
output("Pass Rate: {$pass_rate}%", $pass_rate >= 90 ? 'pass' : 'fail');

if (!empty($security_issues)) {
    output("\n🚨 CRITICAL SECURITY ISSUES FOUND:", 'fail');
    foreach ($security_issues as $issue) {
        output("   - {$issue}", 'fail');
    }
    output("\n⚠️  DO NOT MERGE TO PRODUCTION UNTIL SECURITY ISSUES ARE RESOLVED", 'fail');
}

// Recommendations
output("\n", 'info');
section('Recommendations');

if ($tests_failed === 0 && empty($security_issues)) {
    output("✅ All critical tests passed!", 'pass');
    output("✅ No security issues detected", 'pass');
    output("✅ REST API refactoring is ready for production", 'pass');
} else if (!empty($security_issues)) {
    output("🚨 CRITICAL: Address security issues before deploying", 'fail');
    output("   Review failed tests above and fix security bypasses", 'fail');
} else if ($tests_failed > 0) {
    output("⚠️  Some tests failed - review before deploying", 'warn');
    output("   Check failed tests above for details", 'warn');
}

if ($tests_warning > 0) {
    output("\nℹ️  {$tests_warning} warning(s) - review for potential improvements", 'warn');
}

// Exit code for CI/CD
if ($is_cli) {
    if (!empty($security_issues) || $tests_failed > 0) {
        exit(1); // Fail
    } else {
        exit(0); // Success
    }
}

if (!$is_cli) {
    echo '</body></html>';
}
