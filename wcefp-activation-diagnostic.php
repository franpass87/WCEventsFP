<?php
/**
 * WCEventsFP Activation-Specific Diagnostic Tool
 * Focuses specifically on plugin activation issues and WSOD
 * Usage: php wcefp-activation-diagnostic.php
 */

if (!defined('WCEFP_PLUGIN_DIR')) {
    define('WCEFP_PLUGIN_DIR', dirname(__FILE__) . '/');
}

echo "=== WCEventsFP Activation Diagnostic Tool ===\n\n";

// Function to simulate activation environment
function simulate_activation() {
    echo "1. Simulating Plugin Activation Environment...\n";
    
    // Check if we can access WordPress functions that would be available during activation
    if (defined('ABSPATH')) {
        echo "   ✅ WordPress context available\n";
        
        // Check critical WordPress functions used during activation
        $required_functions = ['dbDelta', 'get_option', 'wp_die'];
        foreach ($required_functions as $func) {
            if (function_exists($func)) {
                echo "   ✅ Function '$func' available\n";
            } else {
                echo "   ❌ Function '$func' not available\n";
                if ($func === 'dbDelta') {
                    $upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
                    if (file_exists($upgrade_file)) {
                        echo "      → wp-admin/includes/upgrade.php exists, should be loaded during activation\n";
                    } else {
                        echo "      → wp-admin/includes/upgrade.php missing! This will cause activation failure.\n";
                    }
                }
            }
        }
    } else {
        echo "   ⚠️  Running in standalone mode (not in WordPress context)\n";
        echo "   This is normal for command-line diagnostics.\n";
    }
}

// Function to check memory function issue
function check_memory_function() {
    echo "\n2. Checking Memory Function Definition Order...\n";
    
    $main_file = WCEFP_PLUGIN_DIR . 'wceventsfp.php';
    $content = file_get_contents($main_file);
    
    // Find the function definition
    $function_pos = strpos($content, 'function wcefp_convert_memory_to_bytes');
    $usage_pos = strpos($content, 'wcefp_convert_memory_to_bytes($memory_limit)');
    
    if ($function_pos !== false && $usage_pos !== false) {
        if ($function_pos < $usage_pos) {
            echo "   ✅ Function 'wcefp_convert_memory_to_bytes' defined before usage\n";
            echo "      Definition at position: $function_pos\n";
            echo "      First usage at position: $usage_pos\n";
        } else {
            echo "   ❌ Function 'wcefp_convert_memory_to_bytes' used before definition!\n";
            echo "      Definition at position: $function_pos\n";
            echo "      First usage at position: $usage_pos\n";
            echo "      This would cause: Fatal error: Call to undefined function\n";
        }
    } else {
        if ($function_pos === false) {
            echo "   ❌ Function 'wcefp_convert_memory_to_bytes' not found in main file\n";
        }
        if ($usage_pos === false) {
            echo "   ❌ Usage of 'wcefp_convert_memory_to_bytes' not found in main file\n";
        }
    }
}

// Function to check class loading order
function check_class_loading() {
    echo "\n3. Checking Class Loading Dependencies...\n";
    
    $core_classes = [
        'includes/class-wcefp-logger.php',
        'includes/class-wcefp-validator.php',
        'includes/class-wcefp-cache.php',
        'includes/class-wcefp-recurring.php',
        'includes/class-wcefp-closures.php',
        'includes/class-wcefp-gift.php',
        'includes/class-wcefp-frontend.php',
        'includes/class-wcefp-templates.php',
        'includes/class-wcefp-product-types.php',
        'includes/class-wcefp-enhanced-features.php',
        'includes/class-wcefp-resource-management.php',
        'includes/class-wcefp-channel-management.php',
        'includes/class-wcefp-commission-management.php',
        'includes/class-wcefp-security-enhancement.php',
        'includes/class-wcefp-realtime-features.php',
        'includes/class-wcefp-advanced-monitoring.php',
        'includes/class-wcefp-accessibility-enhancement.php',
        'includes/class-wcefp-performance-optimization.php',
        'includes/class-wcefp-error-handler.php',
        'includes/class-wcefp-i18n-enhancement.php',
        'includes/class-wcefp-debug-tools.php',
        'includes/class-wcefp-webhook-system.php'
    ];
    
    $missing_files = 0;
    $syntax_errors = 0;
    
    foreach ($core_classes as $class_file) {
        $full_path = WCEFP_PLUGIN_DIR . $class_file;
        if (!file_exists($full_path)) {
            echo "   ❌ Missing: $class_file\n";
            $missing_files++;
        } else {
            $result = shell_exec("php -l '$full_path' 2>&1");
            if (strpos($result, 'No syntax errors') === false) {
                echo "   ❌ Syntax error in: $class_file\n";
                echo "      " . trim($result) . "\n";
                $syntax_errors++;
            }
        }
    }
    
    if ($missing_files == 0 && $syntax_errors == 0) {
        echo "   ✅ All core classes validated successfully\n";
    } else {
        echo "   ⚠️  Found $missing_files missing files and $syntax_errors files with syntax errors\n";
    }
}

// Function to check activation hook structure
function check_activation_hook() {
    echo "\n4. Checking Activation Hook Structure...\n";
    
    $main_file = WCEFP_PLUGIN_DIR . 'wceventsfp.php';
    $content = file_get_contents($main_file);
    
    // Check if activation hook is wrapped in try-catch
    if (strpos($content, 'register_activation_hook(__FILE__, function () {') !== false) {
        echo "   ✅ Activation hook found\n";
        
        if (strpos($content, 'try {') !== false && strpos($content, '} catch (Exception $e) {') !== false) {
            echo "   ✅ Activation hook has exception handling\n";
        } else {
            echo "   ⚠️  Activation hook lacks proper exception handling\n";
        }
        
        // Check for WooCommerce dependency check
        if (strpos($content, "class_exists('WooCommerce')") !== false) {
            echo "   ✅ WooCommerce dependency check present\n";
        } else {
            echo "   ⚠️  Missing WooCommerce dependency check in activation\n";
        }
        
        // Check for database availability check
        if (strpos($content, '$wpdb->get_var("SELECT 1")') !== false) {
            echo "   ✅ Database connectivity check present\n";
        } else {
            echo "   ⚠️  Missing database connectivity check\n";
        }
    } else {
        echo "   ❌ Activation hook not found or malformed\n";
    }
}

// Function to simulate database table creation
function simulate_table_creation() {
    echo "\n5. Simulating Database Table Creation...\n";
    
    if (defined('ABSPATH') && function_exists('dbDelta')) {
        global $wpdb;
        
        echo "   ✅ Database functions available\n";
        echo "   Database prefix: " . $wpdb->prefix . "\n";
        
        // Check table names that would be created
        $tables = [
            'wcefp_occurrences',
            'wcefp_closures', 
            'wcefp_vouchers',
            'wcefp_product_extras'
        ];
        
        foreach ($tables as $table) {
            $full_table = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'");
            if ($exists) {
                echo "   ✅ Table '$full_table' exists\n";
                
                // Check table structure
                $columns = $wpdb->get_results("SHOW COLUMNS FROM $full_table", ARRAY_A);
                echo "      Columns: " . count($columns) . "\n";
            } else {
                echo "   ⚠️  Table '$full_table' does not exist (would be created during activation)\n";
            }
        }
    } else {
        echo "   ⚠️  Cannot simulate table creation (not in WordPress context)\n";
    }
}

// Function to check plugins_loaded action issues
function check_plugins_loaded() {
    echo "\n6. Checking plugins_loaded Action Issues...\n";
    
    $main_file = WCEFP_PLUGIN_DIR . 'wceventsfp.php';
    $content = file_get_contents($main_file);
    
    // Check for proper error handling in plugins_loaded
    if (strpos($content, "add_action('plugins_loaded', function () {") !== false) {
        echo "   ✅ plugins_loaded action found\n";
        
        // Check for WooCommerce check
        if (strpos($content, "if (!class_exists('WooCommerce')) {") !== false) {
            echo "   ✅ WooCommerce availability check present\n";
        } else {
            echo "   ⚠️  Missing WooCommerce check in plugins_loaded\n";
        }
        
        // Check for memory function call
        $memory_call_pos = strpos($content, 'wcefp_convert_memory_to_bytes($memory_limit)');
        $plugins_loaded_pos = strpos($content, "add_action('plugins_loaded'");
        
        if ($memory_call_pos !== false && $plugins_loaded_pos !== false) {
            if ($memory_call_pos > $plugins_loaded_pos) {
                echo "   ✅ Memory function called within plugins_loaded context\n";
            } else {
                echo "   ❌ Memory function called before plugins_loaded (this could cause issues)\n";
            }
        }
    } else {
        echo "   ❌ plugins_loaded action not found\n";
    }
}

// Run all diagnostic checks
simulate_activation();
check_memory_function();
check_class_loading();
check_activation_hook();
simulate_table_creation();
check_plugins_loaded();

echo "\n=== Activation Diagnostic Complete ===\n";
echo "If activation issues persist after reviewing this output, consider:\n";
echo "1. Increasing PHP memory_limit to 256M or higher\n";
echo "2. Enabling WordPress debug mode to capture specific error messages\n";
echo "3. Checking server error logs during activation attempt\n";
echo "4. Temporarily deactivating other plugins to avoid conflicts\n";
echo "5. Ensuring WooCommerce is active before activating WCEventsFP\n\n";