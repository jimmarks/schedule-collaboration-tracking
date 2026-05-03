#!/usr/bin/env php
<?php
/**
 * Functional Test - Group-Based System Validation
 * 
 * Tests the new FTT_Family_Groups system after legacy removal
 * Runs as standalone script without requiring full WordPress installation
 *
 * Usage: php test-groups-functionality.php
 * 
 * @package Family_Travel_Tracker
 */

// Check if running in WordPress context
$in_wordpress = function_exists('add_action') && defined('ABSPATH');

if (!$in_wordpress) {
    echo "ℹ️  Running in standalone mode (no WordPress detected)\n\n";
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  FTT Group-Based System - Functional Tests                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$tests_passed = 0;
$tests_failed = 0;
$test_results = [];

/**
 * Test helper function
 */
function run_test($name, $callback) {
    global $tests_passed, $tests_failed, $test_results;
    
    echo "Testing: {$name}... ";
    
    try {
        $result = $callback();
        if ($result === true) {
            echo "✅ PASS\n";
            $tests_passed++;
            $test_results[] = ['name' => $name, 'status' => 'pass'];
        } else {
            echo "❌ FAIL\n";
            if (is_string($result)) {
                echo "   Reason: {$result}\n";
            }
            $tests_failed++;
            $test_results[] = ['name' => $name, 'status' => 'fail', 'reason' => $result];
        }
    } catch (Exception $e) {
        echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
        $tests_failed++;
        $test_results[] = ['name' => $name, 'status' => 'exception', 'reason' => $e->getMessage()];
    }
}

// ============================================================================
// TEST 1: FTT_Family_Groups Class File Exists
// ============================================================================
run_test('FTT_Family_Groups class file exists', function() {
    $file = __DIR__ . '/includes/class-family-groups.php';
    return file_exists($file);
});

// ============================================================================
// TEST 2: New Methods Exist in Code
// ============================================================================
run_test('get_user_children() method exists in code', function() {
    $file = __DIR__ . '/includes/class-family-groups.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    return strpos($content, 'function get_user_children(') !== false;
});

run_test('get_user_parents() method exists in code', function() {
    $file = __DIR__ . '/includes/class-family-groups.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    return strpos($content, 'function get_user_parents(') !== false;
});

run_test('is_parent() method exists in code', function() {
    $file = __DIR__ . '/includes/class-family-groups.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    // Look for the static method definition
    return preg_match('/public\s+static\s+function\s+is_parent\s*\(/', $content) > 0;
});

run_test('is_child() method exists in code', function() {
    $file = __DIR__ . '/includes/class-family-groups.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    return preg_match('/public\s+static\s+function\s+is_child\s*\(/', $content) > 0;
});

run_test('get_primary_group() method exists in code', function() {
    $file = __DIR__ . '/includes/class-family-groups.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    return strpos($content, 'function get_primary_group(') !== false;
});

run_test('get_all_children() method exists in code', function() {
    $file = __DIR__ . '/includes/class-family-groups.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    return preg_match('/public\s+static\s+function\s+get_all_children\s*\(/', $content) > 0;
});

run_test('get_all_parents() method exists in code', function() {
    $file = __DIR__ . '/includes/class-family-groups.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    return preg_match('/public\s+static\s+function\s+get_all_parents\s*\(/', $content) > 0;
});

// ============================================================================
// TEST 3: Verify No Legacy Code is Called
// ============================================================================
run_test('Templates use FTT_Family_Groups (event-form)', function() {
    $file = __DIR__ . '/templates/event-form.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    
    // Check for new methods
    $has_new = strpos($content, 'FTT_Family_Groups::get_user_children') !== false;
    
    // Check for absence of old methods
    $has_old = strpos($content, 'FTT_Roles::get_children') !== false;
    
    if ($has_old) return 'Still using FTT_Roles::get_children()';
    if (!$has_new) return 'Not using FTT_Family_Groups::get_user_children()';
    
    return true;
});

run_test('Templates use FTT_Family_Groups (calendar)', function() {
    $file = __DIR__ . '/templates/calendar.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    $has_old = strpos($content, 'FTT_Roles::get_children') !== false;
    
    if ($has_old) return 'Still using FTT_Roles::get_children()';
    
    return true;
});

run_test('REST API uses FTT_Family_Groups', function() {
    $file = __DIR__ . '/includes/rest.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    
    // Check for new methods in get_events
    $has_new = strpos($content, 'FTT_Family_Groups::get_user_children') !== false;
    
    // Count occurrences of old methods (should only be in comments)
    $old_count = substr_count($content, 'FTT_Roles::get_children(');
    $comment_count = substr_count($content, '* Replaces FTT_Roles::');
    
    if ($old_count > $comment_count) return "Still has {$old_count} calls to FTT_Roles::get_children()";
    if (!$has_new) return 'Not using FTT_Family_Groups methods';
    
    return true;
});

// ============================================================================
// TEST 4: Check Core Include Files Use New Methods
// ============================================================================
run_test('invitations.php uses group-based methods', function() {
    $file = __DIR__ . '/includes/invitations.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    
    // Should use add_member or get_primary_group
    $has_group_methods = strpos($content, 'FTT_Family_Groups::add_member') !== false ||
                         strpos($content, 'FTT_Family_Groups::get_primary_group') !== false;
    
    // Check for legacy calls (not in comments)
    $lines = explode("\n", $content);
    $legacy_calls = 0;
    foreach ($lines as $line) {
        if (strpos($line, '//') === false &&  strpos($line, '/*') === false &&
            strpos($line, 'FTT_Roles::add_parent_child') !== false) {
            $legacy_calls++;
        }
    }
    
    if ($legacy_calls > 0) return "Still has {$legacy_calls} legacy add_parent_child() calls";
    if (!$has_group_methods) return 'Not using group-based methods';
    
    return true;
});

run_test('registration.php uses group-based methods', function() {
    $file = __DIR__ . '/includes/registration.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    
    // Should use create_group or add_member
    $has_create_group = strpos($content, 'FTT_Family_Groups::create_group') !== false;
    
    return $has_create_group ? true : 'Not using FTT_Family_Groups::create_group()';
});

run_test('billing manager uses group-based methods', function() {
    $file = __DIR__ . '/includes/billing/class-billing-manager.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    
    // Should use group-based methods: get_primary_group, add_member, remove_member, get_member_count, etc.
    $has_group_methods = strpos($content, 'FTT_Family_Groups::get_primary_group') !== false &&
                        strpos($content, 'FTT_Family_Groups::add_member') !== false;
    
    return $has_group_methods ? true : 'Not using FTT_Family_Groups methods';
});

run_test('AI event parser uses group-based methods', function() {
    $file = __DIR__ . '/includes/class-ai-event-parser.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    
    // Should use get_all_children or get_user_children
    $has_children = strpos($content, 'FTT_Family_Groups::get_all_children') !== false ||
                    strpos($content, 'FTT_Family_Groups::get_user_children') !== false;
    
    return $has_children ? true : 'Not using FTT_Family_Groups child methods';
});

// ============================================================================
// TEST 5: Template Files Use New Methods
// ============================================================================
run_test('dashboard.php uses group-based methods', function() {
    $file = __DIR__ . '/templates/dashboard.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    
    // Should use is_parent or get_user_children
    $has_new = strpos($content, 'FTT_Family_Groups::is_parent') !== false ||
               strpos($content, 'FTT_Family_Groups::get_user_children') !== false;
    
    // Check for legacy
    $has_old = strpos($content, 'FTT_Roles::get_children') !== false;
    
    if ($has_old) return 'Still using FTT_Roles::get_children()';
    if (!$has_new) return 'Not using FTT_Family_Groups methods';
    
    return true;
});

run_test('family-management.php removed legacy mode', function() {
    $file = __DIR__ . '/templates/family-management.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    
    // Should NOT have "legacy mode" references for family relationships
    // Note: "fallback" for child_color metadata is OK (that's not family relationships)
    $has_legacy_mode = stripos($content, 'legacy mode') !== false;
    
    if ($has_legacy_mode) return 'Still has legacy mode references';
    
    return true;
});

// ============================================================================
// TEST 6: Verify Legacy Methods Still Exist (for backwards compatibility)
// ============================================================================
run_test('FTT_Roles class file exists', function() {
    $file = __DIR__ . '/includes/roles.php';
    return file_exists($file);
});

run_test('FTT_Roles::is_member() still exists in code', function() {
    $file = __DIR__ . '/includes/roles.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    return strpos($content, 'function is_member(') !== false;
});

run_test('FTT_Roles::make_member() still exists in code', function() {
    $file = __DIR__ . '/includes/roles.php';
    if (!file_exists($file)) return 'File not found';
    
    $content = file_get_contents($file);
    return strpos($content, 'function make_member(') !== false;
});

// ============================================================================
// TEST 7: Check No Syntax Errors in Modified Files
// ============================================================================
run_test('class-family-groups.php has no syntax errors', function() {
    $file = __DIR__ . '/includes/class-family-groups.php';
    if (!file_exists($file)) return 'File not found';
    
    $output = shell_exec("php -l " . escapeshellarg($file) . " 2>&1");
    return strpos($output, 'No syntax errors') !== false;
});

run_test('rest.php has no syntax errors', function() {
    $file = __DIR__ . '/includes/rest.php';
    if (!file_exists($file)) return 'File not found';
    
    $output = shell_exec("php -l " . escapeshellarg($file) . " 2>&1");
    return strpos($output, 'No syntax errors') !== false;
});

run_test('invitations.php has no syntax errors', function() {
    $file = __DIR__ . '/includes/invitations.php';
    if (!file_exists($file)) return 'File not found';
    
    $output = shell_exec("php -l " . escapeshellarg($file) . " 2>&1");
    return strpos($output, 'No syntax errors') !== false;
});

run_test('registration.php has no syntax errors', function() {
    $file = __DIR__ . '/includes/registration.php';
    if (!file_exists($file)) return 'File not found';
    
    $output = shell_exec("php -l " . escapeshellarg($file) . " 2>&1");
    return strpos($output, 'No syntax errors') !== false;
});

run_test('billing manager has no syntax errors', function() {
    $file = __DIR__ . '/includes/billing/class-billing-manager.php';
    if (!file_exists($file)) return 'File not found';
    
    $output = shell_exec("php -l " . escapeshellarg($file) . " 2>&1");
    return strpos($output, 'No syntax errors') !== false;
});

// ============================================================================
// SUMMARY
// ============================================================================
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  Test Summary                                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Total Tests: " . ($tests_passed + $tests_failed) . "\n";
echo "✅ Passed: {$tests_passed}\n";
echo "❌ Failed: {$tests_failed}\n";
echo "\n";

if ($tests_failed > 0) {
    echo "Failed Tests:\n";
    foreach ($test_results as $result) {
        if ($result['status'] !== 'pass') {
            echo "  • {$result['name']}\n";
            if (isset($result['reason'])) {
                echo "    Reason: {$result['reason']}\n";
            }
        }
    }
    echo "\n";
}

// Exit with appropriate code
exit($tests_failed > 0 ? 1 : 0);
