<?php
/**
 * WCEventsFP Plugin Load Test
 * Tests if the plugin can be loaded without fatal errors
 * Usage: php wcefp-load-test.php
 */

echo "=== WCEventsFP Plugin Load Test ===\n\n";

// Set up minimal WordPress-like environment
define('ABSPATH', dirname(__FILE__) . '/');
define('WPINC', 'wp-includes');

// Mock WordPress functions that are used during plugin initialization
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'https://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        echo "   → add_action('$hook') called successfully\n";
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        echo "   → add_filter('$hook') called successfully\n";  
        return true;
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        echo "   → register_activation_hook() called successfully\n";
        return true;
    }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain($domain, $deprecated, $plugin_rel_path) {
        echo "   → load_plugin_textdomain('$domain') called successfully\n";
        return true;
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return false; // Simulate frontend load
    }
}

if (!function_exists('class_exists')) {
    function class_exists($class_name) {
        if ($class_name === 'WooCommerce') {
            return true; // Simulate WooCommerce being available
        }
        return class_exists($class_name);
    }
}

echo "1. Testing plugin file inclusion...\n";

// Test if we can include the main plugin file without errors
try {
    ob_start(); // Capture any output
    include dirname(__FILE__) . '/wceventsfp.php';
    $output = ob_get_clean();
    
    echo "   ✅ Main plugin file loaded successfully\n";
    
    if (!empty($output)) {
        echo "   Output during load:\n";
        echo "   " . str_replace("\n", "\n   ", trim($output)) . "\n";
    }
    
} catch (ParseError $e) {
    echo "   ❌ Parse Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
} catch (Error $e) {
    echo "   ❌ Fatal Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit(1);
}

echo "\n2. Testing function availability...\n";

// Test if our memory function is available
if (function_exists('wcefp_convert_memory_to_bytes')) {
    echo "   ✅ wcefp_convert_memory_to_bytes() function available\n";
    
    // Test the function with various inputs
    $test_cases = ['128M', '256M', '1G', '512K', '64'];
    foreach ($test_cases as $test) {
        $result = wcefp_convert_memory_to_bytes($test);
        echo "   → $test = " . number_format($result) . " bytes\n";
    }
} else {
    echo "   ❌ wcefp_convert_memory_to_bytes() function not available\n";
}

echo "\n3. Testing class availability...\n";

// Test if main plugin class is available
if (function_exists('WCEFP')) {
    echo "   ✅ WCEFP() function available\n";
    
    try {
        $plugin_instance = WCEFP();
        if (is_object($plugin_instance)) {
            echo "   ✅ WCEFP_Plugin instance created successfully\n";
            echo "   → Class: " . get_class($plugin_instance) . "\n";
        } else {
            echo "   ❌ WCEFP() did not return an object\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error creating WCEFP instance: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ❌ WCEFP() function not available\n";
}

echo "\n4. Testing constants...\n";

$required_constants = ['WCEFP_VERSION', 'WCEFP_PLUGIN_FILE', 'WCEFP_PLUGIN_DIR', 'WCEFP_PLUGIN_URL'];
foreach ($required_constants as $constant) {
    if (defined($constant)) {
        echo "   ✅ $constant = " . constant($constant) . "\n";
    } else {
        echo "   ❌ $constant not defined\n";
    }
}

echo "\n=== Plugin Load Test Complete ===\n";
echo "✅ Plugin loaded successfully without fatal errors!\n\n";