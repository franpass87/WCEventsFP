<?php
/**
 * WCEFP Improvement Test Script
 * 
 * Tests the new autoloader, server monitor, and initialization improvements
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/fake-wp/');
}
if (!defined('WCEFP_PLUGIN_DIR')) {
    define('WCEFP_PLUGIN_DIR', __DIR__ . '/');
}

echo "=== WCEventsFP Improvement Test ===\n\n";

// Test 1: Autoloader
echo "1. Testing New Autoloader System...\n";
require_once __DIR__ . '/wcefp-autoloader.php';

$test_classes = [
    'WCEFP\\Admin\\ServiceProvider',
    'WCEFP\\Frontend\\ServiceProvider', 
    'WCEFP\\Features\\ServiceProvider',
    'WCEFP\\Core\\Database\\ServiceProvider',
    'WCEFP\\Core\\Container',
    'WCEFP\\Bootstrap\\Plugin'
];

foreach ($test_classes as $class) {
    $exists = class_exists($class, true);
    echo "   " . ($exists ? "✅" : "❌") . " {$class}\n";
}

// Test 2: Server Monitor
echo "\n2. Testing Server Resource Monitor...\n";
require_once __DIR__ . '/wcefp-server-monitor.php';

$mode = WCEFP_Server_Monitor::get_recommended_loading_mode();
$score = WCEFP_Server_Monitor::get_resource_score();
$report = WCEFP_Server_Monitor::generate_report();

echo "   ✅ Server monitor loaded\n";
echo "   📊 Recommended mode: {$mode}\n";
echo "   🎯 Resource score: {$score}/100\n";
echo "   💾 Memory limit: {$report['memory_limit_mb']} MB\n";
echo "   ⏱️  Execution time: {$report['execution_time']} seconds\n";
echo "   📝 Status: {$report['status']}\n";

// Test 3: Feature Limits
echo "\n3. Testing Feature Limits System...\n";
$limits = WCEFP_Server_Monitor::get_feature_limits();
echo "   📈 Max features: " . ($limits['max_features'] === -1 ? 'Unlimited' : $limits['max_features']) . "\n";
echo "   🔄 Features per load: {$limits['features_per_load']}\n";
echo "   ⏳ Load delay: {$limits['load_delay_ms']}ms\n";
echo "   💾 Caching enabled: " . ($limits['enable_caching'] ? 'Yes' : 'No') . "\n";

// Test 4: Safety Checks
echo "\n4. Testing Safety Functions...\n";
$can_activate = wcefp_can_activate_safely();
echo "   🛡️  Can activate safely: " . ($can_activate ? 'Yes' : 'No') . "\n";

$can_standard = WCEFP_Server_Monitor::can_handle_standard_loading();
echo "   ⚡ Can handle standard loading: " . ($can_standard ? 'Yes' : 'No') . "\n";

// Test 5: WSOD Prevention
echo "\n5. Testing WSOD Prevention...\n";
if (file_exists(__DIR__ . '/wcefp-wsod-preventer.php')) {
    require_once __DIR__ . '/wcefp-wsod-preventer.php';
    echo "   ✅ WSOD preventer loaded\n";
    if (defined('WCEFP_WSOD_PREVENTER_LOADED')) {
        echo "   ✅ WSOD prevention active\n";
    } else {
        echo "   ⚠️  WSOD prevention not active\n";
    }
} else {
    echo "   ❌ WSOD preventer file missing\n";
}

echo "\n=== Test Complete ===\n\n";
echo "Summary:\n";
echo "- Autoloader: " . (class_exists('WCEFP\\Core\\Container') ? "Working ✅" : "Failed ❌") . "\n";
echo "- Server Monitor: Working ✅\n";
echo "- Recommended Mode: {$mode}\n";
echo "- Safe for Activation: " . ($can_activate ? "Yes ✅" : "No ❌") . "\n";

if (!$can_activate) {
    echo "\n⚠️  WARNING: Server resources are very limited.\n";
    echo "The plugin will run in ultra-minimal emergency mode.\n";
    echo "Consider upgrading server resources for full functionality.\n";
}