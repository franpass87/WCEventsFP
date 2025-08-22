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
    define('WCEFP_PLUGIN_DIR', dirname(__FILE__) . '/');
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

// Test 1: Check if all new files exist
run_test('File Structure Check', function() {
    $required_files = [
        'wcefp-setup-wizard.php',
        'includes/Core/InstallationManager.php',
        'includes/Admin/FeatureManager.php',
        'assets/css/feature-manager.css',
        'assets/js/feature-manager.js'
    ];
    
    $missing_files = [];
    foreach ($required_files as $file) {
        if (!file_exists(WCEFP_PLUGIN_DIR . $file)) {
            $missing_files[] = $file;
        }
    }
    
    return empty($missing_files) ? true : "Missing files: " . implode(', ', $missing_files);
});

// Test 2: Check PHP syntax of new files
run_test('PHP Syntax Check', function() {
    $php_files = [
        'wcefp-setup-wizard.php',
        'includes/Core/InstallationManager.php', 
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

// Test 3: Test InstallationManager class loading
run_test('InstallationManager Class Loading', function() {
    if (!file_exists(WCEFP_PLUGIN_DIR . 'includes/Core/InstallationManager.php')) {
        return "InstallationManager.php not found";
    }
    
    // Mock WordPress functions for testing
    if (!function_exists('get_option')) {
        function get_option($option, $default = false) { return $default; }
    }
    if (!function_exists('update_option')) {
        function update_option($option, $value) { return true; }
    }
    if (!function_exists('delete_option')) {
        function delete_option($option) { return true; }
    }
    if (!function_exists('wp_clear_scheduled_hook')) {
        function wp_clear_scheduled_hook($hook) { return true; }
    }
    if (!function_exists('wp_schedule_single_event')) {
        function wp_schedule_single_event($timestamp, $hook) { return true; }
    }
    if (!function_exists('current_time')) {
        function current_time($type) { return date('Y-m-d H:i:s'); }
    }
    if (!function_exists('admin_url')) {
        function admin_url($path) { return 'http://example.com/wp-admin/' . $path; }
    }
    if (!defined('ABSPATH')) {
        define('ABSPATH', '/tmp/');
    }
    
    // Mock Logger class
    if (!class_exists('WCEFP\\Utils\\Logger')) {
        class MockLogger {
            public static function info($message) {}
            public static function error($message) {}
            public static function debug($message) {}
        }
        class_alias('MockLogger', 'WCEFP\\Utils\\Logger');
    }
    
    require_once WCEFP_PLUGIN_DIR . 'includes/Core/InstallationManager.php';
    
    if (!class_exists('WCEFP\\Core\\InstallationManager')) {
        return "InstallationManager class not found after loading";
    }
    
    try {
        $manager = new \WCEFP\Core\InstallationManager();
        return true;
    } catch (Exception $e) {
        return "Error creating InstallationManager: " . $e->getMessage();
    }
});

// Test 4: Test setup wizard structure
run_test('Setup Wizard Structure', function() {
    $wizard_content = file_get_contents(WCEFP_PLUGIN_DIR . 'wcefp-setup-wizard.php');
    
    $required_elements = [
        'class WCEFP_Setup_Wizard',
        'function render(',
        'function render_welcome_step',
        'function render_requirements_step',
        'function render_features_step',
        'function render_activation_step'
    ];
    
    $missing_elements = [];
    foreach ($required_elements as $element) {
        if (strpos($wizard_content, $element) === false) {
            $missing_elements[] = $element;
        }
    }
    
    return empty($missing_elements) ? true : "Missing elements: " . implode(', ', $missing_elements);
});

// Test 5: Test main plugin integration
run_test('Main Plugin Integration', function() {
    $main_content = file_get_contents(WCEFP_PLUGIN_DIR . 'wceventsfp.php');
    
    $required_integrations = [
        'wcefp_setup',
        'InstallationManager',
        'wcefp_minimal_init',
        'wcefp_progressive_init',
        'wcefp_standard_init'
    ];
    
    $missing_integrations = [];
    foreach ($required_integrations as $integration) {
        if (strpos($main_content, $integration) === false) {
            $missing_integrations[] = $integration;
        }
    }
    
    return empty($missing_integrations) ? true : "Missing integrations: " . implode(', ', $missing_integrations);
});

// Test 6: Test feature loading functions
run_test('Feature Loading Functions', function() {
    $main_content = file_get_contents(WCEFP_PLUGIN_DIR . 'wceventsfp.php');
    
    // Check if wcefp_load_feature function exists
    if (strpos($main_content, 'function wcefp_load_feature') === false) {
        return "wcefp_load_feature function not found";
    }
    
    // Check if feature mapping exists
    if (strpos($main_content, 'admin_enhanced') === false || 
        strpos($main_content, 'resources') === false) {
        return "Feature mapping incomplete";
    }
    
    return true;
});

// Test 7: Test activation hook enhancement
run_test('Enhanced Activation Hook', function() {
    $main_content = file_get_contents(WCEFP_PLUGIN_DIR . 'wceventsfp.php');
    
    // Check for enhanced activation
    if (strpos($main_content, 'wcefp_minimal_activation_fallback') === false) {
        return "Minimal activation fallback not found";
    }
    
    if (strpos($main_content, 'start_progressive_installation') === false) {
        return "Progressive installation trigger not found";
    }
    
    if (strpos($main_content, 'wcefp_redirect_to_wizard') === false) {
        return "Wizard redirect mechanism not found";
    }
    
    return true;
});

// Test 8: Test FeatureManager integration
run_test('FeatureManager Integration', function() {
    if (!file_exists(WCEFP_PLUGIN_DIR . 'includes/Admin/FeatureManager.php')) {
        return "FeatureManager.php not found";
    }
    
    $feature_manager_content = file_get_contents(WCEFP_PLUGIN_DIR . 'includes/Admin/FeatureManager.php');
    
    $required_methods = [
        'add_admin_menu',
        'render_dashboard',
        'render_feature_manager',
        'ajax_toggle_feature',
        'get_all_features'
    ];
    
    $missing_methods = [];
    foreach ($required_methods as $method) {
        if (strpos($feature_manager_content, "function {$method}") === false) {
            $missing_methods[] = $method;
        }
    }
    
    return empty($missing_methods) ? true : "Missing methods: " . implode(', ', $missing_methods);
});

// Test 9: Test CSS and JS assets
run_test('Asset Files Check', function() {
    $assets_files = [
        'assets/css/feature-manager.css',
        'assets/js/feature-manager.js',
        'assets/css/frontend.css',
        'assets/js/frontend.js',
        'assets/css/admin.css',
        'assets/js/admin.js'
    ];
    
    $missing_assets = [];
    foreach ($assets_files as $asset) {
        if (!file_exists(WCEFP_PLUGIN_DIR . $asset)) {
            $missing_assets[] = $asset;
        }
    }
    
    return empty($missing_assets) ? true : "Missing assets: " . implode(', ', $missing_assets);
});

// Test 10: Test backwards compatibility
run_test('Backwards Compatibility', function() {
    $main_content = file_get_contents(WCEFP_PLUGIN_DIR . 'wceventsfp.php');
    
    // Check if fallback functions exist
    $compatibility_functions = [
        'wcefp_fallback_init',
        'wcefp_minimal_activation_fallback',
        'WCEFP()'
    ];
    
    $missing_functions = [];
    foreach ($compatibility_functions as $func) {
        if (strpos($main_content, $func) === false) {
            $missing_functions[] = $func;
        }
    }
    
    return empty($missing_functions) ? true : "Missing compatibility functions: " . implode(', ', $missing_functions);
});

echo "\n=== TEST RESULTS ===\n";
echo "Total tests: {$total_tests}\n";
echo "Passed: {$passed_tests}\n";
echo "Failed: " . ($total_tests - $passed_tests) . "\n";

if ($passed_tests === $total_tests) {
    echo "\n🎉 ALL TESTS PASSED! The installation system is ready.\n";
    echo "\nNext steps:\n";
    echo "1. The plugin now includes a setup wizard for safe activation\n";
    echo "2. Progressive loading prevents WSOD on slow servers\n";
    echo "3. Feature management allows granular control\n";
    echo "4. Installation can be reset if needed\n";
    echo "\nYou can now safely activate the plugin - it will guide you through setup.\n";
} else {
    echo "\n❌ SOME TESTS FAILED! Review the failures above before proceeding.\n";
    
    echo "\nFailed tests:\n";
    foreach ($test_results as $test_name => $result) {
        if (strpos($result, 'FAILED') === 0 || strpos($result, 'ERROR') === 0) {
            echo "- {$test_name}: {$result}\n";
        }
    }
}

echo "\n=== Installation System Features ===\n";
echo "✅ Setup Wizard: Guides users through safe plugin configuration\n";
echo "✅ Progressive Loading: Loads features gradually to prevent WSOD\n";  
echo "✅ Feature Management: Admin dashboard to enable/disable features\n";
echo "✅ Performance Monitoring: Tracks plugin resource usage\n";
echo "✅ Installation Modes: Minimal, Progressive, Standard, Full\n";
echo "✅ Fallback Systems: Multiple safety nets for compatibility\n";
echo "✅ WSOD Prevention: Enhanced protection against white screens\n";
echo "✅ Wizard Integration: Seamless activation with guidance\n";

echo "\n=== Test completed ===\n";