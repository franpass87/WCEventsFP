<?php
/**
 * Cache Busting Test for WCEventsFP
 * 
 * This script demonstrates the cache busting functionality
 * Run this to see how the version changes in development mode
 */

// Mock WordPress functions for testing
function add_action($hook, $callback, $priority = 10) { return true; }
function wp_verify_nonce($nonce, $action) { return true; }
function current_user_can($capability) { return true; }
function wp_die($message) { die($message); }
function get_site_url() { return 'http://localhost:8080'; }
function update_option($option, $value) { return true; }
function get_option($option, $default = '') { return $default; }
function wp_send_json($data) { echo json_encode($data); exit; }
function admin_url($path) { return 'http://localhost/wp-admin/' . $path; }
function wp_nonce_url($url, $action, $name) { return $url . '&' . $name . '=123'; }
function wp_create_nonce($action) { return 'nonce123'; }
function __($text, $domain) { return $text; }
function esc_js($text) { return addslashes($text); }
function do_action($hook) { return true; }

// Simulate WordPress environment
define('ABSPATH', true);
define('WP_DEBUG', true);
define('WCEFP_PLUGIN_FILE', __DIR__ . '/wceventsfp.php');

// Include the cache manager
require_once __DIR__ . '/includes/class-wcefp-cache-manager.php';

echo "=== WCEventsFP Cache Busting Test ===\n\n";

// Test 1: Check development mode detection
$cache_manager = WCEFP_Cache_Manager::get_instance();

echo "1. Development Mode Detection:\n";
echo "   Is Development Mode: " . ($cache_manager->is_development_mode() ? 'YES' : 'NO') . "\n";
echo "   Reason: WP_DEBUG is enabled\n\n";

// Test 2: Version comparison
$base_version = '2.1.1';
$dynamic_version = $cache_manager->get_cache_busting_version($base_version);

echo "2. Version Comparison:\n";
echo "   Base Version: {$base_version}\n";
echo "   Dynamic Version: {$dynamic_version}\n";
echo "   Cache Busting: " . ($base_version !== $dynamic_version ? 'ACTIVE' : 'INACTIVE') . "\n\n";

// Test 3: Simulate file modification
echo "3. Cache Busting Simulation:\n";
echo "   Original dynamic version: {$dynamic_version}\n";

// Wait a moment and get version again (simulating file change)
sleep(1);
$new_version = $cache_manager->get_cache_busting_version($base_version);
echo "   New dynamic version: {$new_version}\n";
echo "   Versions match: " . ($dynamic_version === $new_version ? 'YES (no file changes)' : 'NO (files changed)') . "\n\n";

echo "=== Test Complete ===\n\n";
echo "In a WordPress environment with WP_DEBUG enabled:\n";
echo "- CSS and JS files will have timestamps appended to their version\n";
echo "- Caches will be cleared when plugin files are modified\n";
echo "- Admins will see a 'Clear WCEFP Cache' button in the admin bar\n\n";

echo "To test in WordPress:\n";
echo "1. Enable WP_DEBUG in wp-config.php\n";
echo "2. Activate the plugin\n";
echo "3. Make changes to any PHP file in the plugin\n";
echo "4. Refresh the page - you should see updated assets\n";