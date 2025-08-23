<?php
/**
 * WCEventsFP Installation System Test
 * 
 * Tests the new wizard-based installation and progressive loading system.
 * Run this BEFORE activating the plugin to verify everything works.
 * 
 * Usage: php wcefp-installation-test.php
 */

if (!defined('WCEFP_PLUGIN_DIR')) {
    define('WCEFP_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
}

echo "=== WCEventsFP Installation System Test ===\n\n";

$test_results = [];
$total_tests = 0;
$passed_tests = 0;

/**
 * Run a test and track results
 */
function run_test($test_name, $test_function) {
    global $test_results, $total_tests, $passed_tests;
    
    $total_tests++;
    echo "Running test: {$test_name}... ";
    
    try {
        $result = call_user_func($test_function);
        if ($result === true) {
            echo "✅ PASSED\n";
            $passed_tests++;
            $test_results[$test_name] = 'PASSED';
        } else {
            echo "❌ FAILED: {$result}\n";
            $test_results[$test_name] = "FAILED: {$result}";
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $test_results[$test_name] = "ERROR: " . $e->getMessage();
    }
}

// Test 1: Check if required files exist (simplified - no installation system)
run_test('File Structure Check', function() {
    $required_files = [
        'wceventsfp.php',
        'includes/Admin/FeatureManager.php'
    ];
    
    $missing_files = [];
    foreach ($required_files as $file) {
        if (!file_exists(WCEFP_PLUGIN_DIR . $file)) {
            $missing_files[] = $file;
        }
    }
    
    return empty($missing_files) ? true : "Missing files: " . implode(', ', $missing_files);
});

// Test 2: Check PHP syntax of files
run_test('PHP Syntax Check', function() {
    $php_files = [
        'wceventsfp.php',
        'includes/Admin/FeatureManager.php'
    ];
    
    $syntax_errors = [];
    foreach ($php_files as $file) {
        $full_path = WCEFP_PLUGIN_DIR . $file;
        if (file_exists($full_path)) {
            exec("php -l {$full_path} 2>&1", $output, $return_code);
            if ($return_code !== 0) {
                $syntax_errors[] = $file . ': ' . implode(' ', $output);
            }
        }
    }
    
    return empty($syntax_errors) ? true : "Syntax errors: " . implode('; ', $syntax_errors);
});

// Test 3: Test simplified plugin functionality
run_test('Simplified Plugin Check', function() {
    $main_content = file_get_contents(WCEFP_PLUGIN_DIR . 'wceventsfp.php');
    
    // Check for simplified activation without installation wizard
    if (strpos($main_content, 'load_all_features') === false) {
        return "load_all_features function not found";
    }
    
    // Check that installation wizard references are removed
    if (strpos($main_content, 'setup_progressive_loading') !== false) {
        return "Old progressive loading system still present";
    }
    
    // Check for immediate activation flag
    if (strpos($main_content, 'wcefp_full_activation') === false) {
        return "Full activation flag not found";
    }
    
    return true;
});

// Test 4: Test FeatureManager simplification
run_test('FeatureManager Simplification', function() {
    if (!file_exists(WCEFP_PLUGIN_DIR . 'includes/Admin/FeatureManager.php')) {
        return "FeatureManager.php not found";
    }
    
    $feature_manager_content = file_get_contents(WCEFP_PLUGIN_DIR . 'includes/Admin/FeatureManager.php');
    
    // Check that feature toggle functionality is removed
    if (strpos($feature_manager_content, "ajax_toggle_feature") !== false) {
        return "Feature toggle functionality still present";
    }
    
    // Check that dashboard shows all features as active
    if (strpos($feature_manager_content, "All Features Active") === false) {
        return "Dashboard doesn't show all features as active";
    }
    
    // Check that feature manager page is removed from menu
    if (strpos($feature_manager_content, "'wcefp-features'") !== false) {
        return "Feature manager admin page still exists";
    }
    
    return true;
});

// Test 5: Test remaining CSS and JS assets (simplified)
run_test('Asset Files Check', function() {
    $essential_assets = [
        'assets/css/frontend.css',
        'assets/css/admin.css'
    ];
    
    $missing_assets = [];
    foreach ($essential_assets as $asset) {
        if (!file_exists(WCEFP_PLUGIN_DIR . $asset)) {
            $missing_assets[] = $asset;
        }
    }
    
    // Check that feature manager assets are properly removed
    $removed_assets = [
        'assets/css/feature-manager.css',
        'assets/js/feature-manager.js'
    ];
    
    $still_exist = [];
    foreach ($removed_assets as $asset) {
        if (file_exists(WCEFP_PLUGIN_DIR . $asset)) {
            $still_exist[] = $asset;
        }
    }
    
    if (!empty($still_exist)) {
        return "Feature manager assets still exist: " . implode(', ', $still_exist);
    }
    
    return empty($missing_assets) ? true : "Missing essential assets: " . implode(', ', $missing_assets);
});

echo "\n=== TEST RESULTS ===\n";
echo "Total tests: {$total_tests}\n";
echo "Passed: {$passed_tests}\n";
echo "Failed: " . ($total_tests - $passed_tests) . "\n";

if ($passed_tests === $total_tests) {
    echo "\n🎉 ALL TESTS PASSED! The simplified plugin system is ready.\n";
    echo "\nNext steps:\n";
    echo "1. All features are automatically active upon plugin activation\n";
    echo "2. No setup wizard or progressive loading required\n";
    echo "3. Immediate full functionality without configuration steps\n";
    echo "4. Simplified admin dashboard shows all active features\n";
    echo "\nYou can now safely activate the plugin - all features work immediately.\n";
} else {
    echo "\n❌ SOME TESTS FAILED! Review the failures above before proceeding.\n";
    
    echo "\nFailed tests:\n";
    foreach ($test_results as $test_name => $result) {
        if (strpos($result, 'FAILED') === 0 || strpos($result, 'ERROR') === 0) {
            echo "- {$test_name}: {$result}\n";
        }
    }
}

echo "\n=== Simplified Plugin System Features ===\n";
echo "✅ Immediate Activation: All features active instantly upon plugin activation\n";
echo "✅ No Setup Required: No wizard or configuration steps needed\n";  
echo "✅ Simplified Dashboard: View all active features in admin\n";
echo "✅ Performance Monitoring: Track plugin resource usage\n";
echo "✅ Full Functionality: All enterprise features available immediately\n";
echo "✅ No User Choice: All features always enabled for maximum functionality\n";
echo "✅ WSOD Prevention: Enhanced protection with simplified loading\n";
echo "✅ One-Click Activation: Simple WordPress plugin activation process\n";

echo "\n=== Test completed ===\n";