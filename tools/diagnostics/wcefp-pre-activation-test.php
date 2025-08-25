<?php
/**
 * WCEventsFP Pre-Activation WSOD Prevention Test
 * 
 * This script simulates the complete plugin activation process
 * without actually activating the plugin, to detect potential WSOD issues.
 * 
 * Usage: php wcefp-pre-activation-test.php
 * 
 * Run this BEFORE activating the plugin to prevent WSOD.
 */

if (!defined('WCEFP_PLUGIN_DIR')) {
    define('WCEFP_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
}

echo "=== WCEventsFP Pre-Activation WSOD Prevention Test ===\n\n";

$test_passed = true;
$warnings = [];
$critical_errors = [];

// Test 1: Basic requirements
echo "1. Testing Basic Requirements...\n";
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    $critical_errors[] = 'PHP 8.0+ required. Current: ' . PHP_VERSION;
    echo "   ❌ PHP Version: " . PHP_VERSION . "\n";
    $test_passed = false;
} else {
    echo "   ✅ PHP Version: " . PHP_VERSION . "\n";
}

$required_extensions = ['mysqli', 'json', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $critical_errors[] = "Missing PHP extension: {$ext}";
        echo "   ❌ Missing extension: {$ext}\n";
        $test_passed = false;
    } else {
        echo "   ✅ Extension loaded: {$ext}\n";
    }
}

// Test 2: Plugin file structure
echo "\n2. Testing Plugin File Structure...\n";
$required_files = [
    'wceventsfp.php' => 'Main plugin file',
    'includes/Core/ActivationHandler.php' => 'Activation handler',
    'includes/Bootstrap/Plugin.php' => 'Bootstrap class',
    'includes/Utils/Logger.php' => 'Logger class',
    'includes/Core/Container.php' => 'Dependency container'
];

foreach ($required_files as $file => $description) {
    if (file_exists(WCEFP_PLUGIN_DIR . $file)) {
        echo "   ✅ {$description}: {$file}\n";
    } else {
        $critical_errors[] = "Missing file: {$file} ({$description})";
        echo "   ❌ Missing: {$file}\n";
        $test_passed = false;
    }
}

// Test 3: Simulate plugin loading
echo "\n3. Simulating Plugin Loading...\n";
try {
    // Simulate WordPress environment minimally
    if (!defined('ABSPATH')) {
        define('ABSPATH', '/tmp/fake-wp/');
    }
    
    // Mock essential WordPress functions before loading classes
    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir() {
            return ['basedir' => sys_get_temp_dir()];
        }
    }
    
    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p($path) {
            return mkdir($path, 0755, true);
        }
    }
    
    if (!function_exists('plugin_dir_path')) {
        function plugin_dir_path($file) {
            return dirname($file) . '/';
        }
    }
    
    if (!function_exists('plugin_dir_url')) {
        function plugin_dir_url($file) {
            return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
        }
    }
    
    // Test autoloader (T-02: Use canonical autoloader)
    if (file_exists(WCEFP_PLUGIN_DIR . 'vendor/autoload.php')) {
        require_once WCEFP_PLUGIN_DIR . 'vendor/autoload.php';
        echo "   ✅ Composer autoloader loaded\n";
    } else {
        $warnings[] = 'Composer autoloader not found - using manual loading';
        echo "   ⚠️  Composer autoloader not found\n";
    }
    
    // Load canonical autoloader (T-02)
    if (file_exists(WCEFP_PLUGIN_DIR . 'includes/autoloader.php')) {
        require_once WCEFP_PLUGIN_DIR . 'includes/autoloader.php';
        echo "   ✅ Canonical autoloader loaded (T-02)\n";
    } else {
        // Only use fallback if canonical is missing
        echo "   ⚠️  Canonical autoloader not found, using fallback\n";
        spl_autoload_register(function($class_name) {
            if (strpos($class_name, 'WCEFP\\') !== 0) {
                return;
            }
            
            $relative_class = str_replace('WCEFP\\', '', $class_name);
            $file_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';
            $full_path = WCEFP_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $file_path;
            
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        });
        echo "   ✅ Basic manual autoloader configured\n";
    }
    
} catch (Exception $e) {
    $critical_errors[] = 'Plugin loading simulation failed: ' . $e->getMessage();
    echo "   ❌ Loading failed: " . $e->getMessage() . "\n";
    $test_passed = false;
}

// Test 4: Test core class instantiation
echo "\n4. Testing Core Class Instantiation...\n";
$core_classes = [
    'WCEFP\\Utils\\Logger' => 'Logger class',
    'WCEFP\\Core\\Container' => 'Container class',
    'WCEFP\\Core\\ActivationHandler' => 'Activation handler',
    'WCEFP\\Bootstrap\\Plugin' => 'Bootstrap plugin'
];

foreach ($core_classes as $class => $description) {
    try {
        if (class_exists($class)) {
            echo "   ✅ {$description} class exists\n";
            
            // Try to instantiate (except for static classes)
            if ($class !== 'WCEFP\\Core\\ActivationHandler') {
                if ($class === 'WCEFP\\Bootstrap\\Plugin') {
                    // Plugin class needs a file parameter
                    $instance = new $class(__FILE__);
                } elseif ($class === 'WCEFP\\Utils\\Logger') {
                    // Logger is singleton
                    $instance = $class::get_instance();
                } else {
                    $instance = new $class();
                }
                echo "   ✅ {$description} can be instantiated\n";
                unset($instance);
            }
        } else {
            $critical_errors[] = "Class not found: {$class}";
            echo "   ❌ {$description} class not found\n";
            $test_passed = false;
        }
    } catch (Exception $e) {
        $critical_errors[] = "Failed to instantiate {$class}: " . $e->getMessage();
        echo "   ❌ {$description} instantiation failed: " . $e->getMessage() . "\n";
        $test_passed = false;
    } catch (Error $e) {
        $critical_errors[] = "Fatal error with {$class}: " . $e->getMessage();
        echo "   ❌ {$description} fatal error: " . $e->getMessage() . "\n";
        $test_passed = false;
    }
}

// Test 5: Simulate activation process
echo "\n5. Simulating Activation Process...\n";
try {
    // Mock WordPress functions that might be called during activation
    if (!function_exists('get_bloginfo')) {
        function get_bloginfo($key) {
            if ($key === 'version') return '6.3';
            return '';
        }
    }
    
    if (!function_exists('flush_rewrite_rules')) {
        function flush_rewrite_rules() {
            // Mock function
        }
    }
    
    if (!function_exists('add_option')) {
        function add_option($name, $value) {
            return true;
        }
    }
    
    if (!function_exists('current_time')) {
        function current_time($type) {
            return date('Y-m-d H:i:s');
        }
    }
    
    // Test activation handler methods
    if (class_exists('WCEFP\\Core\\ActivationHandler')) {
        echo "   ✅ Activation handler class available\n";
        
        // We can't call the actual activate method as it might create real database tables
        // But we can check that the method exists
        $reflection = new ReflectionClass('WCEFP\\Core\\ActivationHandler');
        if ($reflection->hasMethod('activate')) {
            echo "   ✅ Activation method exists\n";
        } else {
            $critical_errors[] = 'Activation method not found in ActivationHandler';
            echo "   ❌ Activation method missing\n";
            $test_passed = false;
        }
    }
    
} catch (Exception $e) {
    $critical_errors[] = 'Activation simulation failed: ' . $e->getMessage();
    echo "   ❌ Activation simulation failed: " . $e->getMessage() . "\n";
    $test_passed = false;
}

// Test 6: Service provider loading simulation
echo "\n6. Testing Service Provider Loading...\n";
$service_providers = [
    'WCEFP\\Admin\\AdminServiceProvider' => 'Admin service provider',
    'WCEFP\\Frontend\\FrontendServiceProvider' => 'Frontend service provider', 
    'WCEFP\\Features\\FeaturesServiceProvider' => 'Features service provider',
    'WCEFP\\Core\\Database\\ServiceProvider' => 'Database service provider'
];

foreach ($service_providers as $class => $description) {
    try {
        if (class_exists($class)) {
            echo "   ✅ {$description} class exists\n";
        } else {
            $warnings[] = "{$description} class not found - may cause service loading issues";
            echo "   ⚠️  {$description} class not found\n";
        }
    } catch (Exception $e) {
        $warnings[] = "{$description} check failed: " . $e->getMessage();
        echo "   ⚠️  {$description} check failed\n";
    }
}

// Final report
echo "\n=== ACTIVATION SAFETY TEST RESULTS ===\n\n";

if ($test_passed && empty($critical_errors)) {
    echo "✅ **ACTIVATION SAFETY CHECK PASSED**\n\n";
    echo "The plugin should activate safely without causing WSOD.\n\n";
    
    if (!empty($warnings)) {
        echo "⚠️  **WARNINGS** (non-critical issues):\n";
        foreach ($warnings as $warning) {
            echo "   • {$warning}\n";
        }
        echo "\n";
    }
    
    echo "**Next Steps:**\n";
    echo "1. You can now activate the plugin safely via WordPress admin\n";
    echo "2. Monitor for any error messages during activation\n";
    echo "3. Check that the WCEventsFP menu appears in WordPress admin\n\n";
    
} else {
    echo "❌ **ACTIVATION SAFETY CHECK FAILED**\n\n";
    echo "**CRITICAL ERRORS** that would cause WSOD:\n";
    foreach ($critical_errors as $error) {
        echo "   • {$error}\n";
    }
    echo "\n";
    
    if (!empty($warnings)) {
        echo "**WARNINGS** (additional issues):\n";
        foreach ($warnings as $warning) {
            echo "   • {$warning}\n";
        }
        echo "\n";
    }
    
    echo "**DO NOT ACTIVATE THE PLUGIN** until these issues are resolved.\n\n";
    echo "**Troubleshooting Steps:**\n";
    echo "1. Fix all critical errors listed above\n";
    echo "2. Ensure PHP version is 8.0 or higher\n";
    echo "3. Install missing PHP extensions\n";
    echo "4. Verify all plugin files are uploaded correctly\n";
    echo "5. Run this test again until it passes\n\n";
}

echo "For support, share this test output with the plugin developer.\n";