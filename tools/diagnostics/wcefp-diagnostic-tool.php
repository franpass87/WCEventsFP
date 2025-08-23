<?php
/**
 * WCEventsFP Advanced Diagnostic Tool
 * Enhanced version for troubleshooting WSOD and other issues
 * Usage: php wcefp-diagnostic-tool.php
 */

if (!defined('WCEFP_PLUGIN_DIR')) {
    define('WCEFP_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
}

echo "=== WCEventsFP Advanced Diagnostic Tool ===\n\n";

// Function to check WordPress environment
function check_wp_environment() {
    echo "1. WordPress Environment Check...\n";
    
    // Check if we're in a WordPress context
    if (defined('ABSPATH')) {
        echo "   ✅ WordPress context detected\n";
        echo "   WordPress Path: " . ABSPATH . "\n";
        
        if (function_exists('get_option')) {
            $wp_version = get_option('wp_db_version');
            if ($wp_version) {
                echo "   WordPress DB Version: $wp_version\n";
            }
        }
    } else {
        echo "   ⚠️  Not running in WordPress context (standalone diagnostic)\n";
    }
    
    // Check WooCommerce
    if (class_exists('WooCommerce')) {
        echo "   ✅ WooCommerce is active\n";
        if (defined('WC_VERSION')) {
            echo "   WooCommerce Version: " . WC_VERSION . "\n";
        }
    } else {
        echo "   ❌ WooCommerce not found - Plugin requires WooCommerce!\n";
    }
}

// Function to check database connectivity
function check_database() {
    echo "\n2. Database Connectivity Check...\n";
    
    if (defined('ABSPATH') && function_exists('wp_check_mysql_version')) {
        global $wpdb;
        
        if (isset($wpdb)) {
            echo "   ✅ WordPress database connection active\n";
            
            // Test a simple query
            $result = $wpdb->get_var("SELECT 1 AS test");
            if ($result == 1) {
                echo "   ✅ Database query test successful\n";
            } else {
                echo "   ❌ Database query test failed\n";
                if (!empty($wpdb->last_error)) {
                    echo "   Error: " . $wpdb->last_error . "\n";
                }
            }
            
            // Check plugin tables
            $tables_to_check = [
                'wcefp_occurrences',
                'wcefp_closures', 
                'wcefp_vouchers',
                'wcefp_product_extras'
            ];
            
            foreach ($tables_to_check as $table) {
                $full_table = $wpdb->prefix . $table;
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'");
                if ($exists) {
                    echo "   ✅ Table exists: $full_table\n";
                } else {
                    echo "   ⚠️  Table missing: $full_table (will be created on activation)\n";
                }
            }
            
        } else {
            echo "   ❌ No WordPress database connection\n";
        }
    } else {
        echo "   ⚠️  Cannot check database (not in WordPress context)\n";
    }
}

// Function to check memory and performance
function check_performance() {
    echo "\n3. Performance & Resource Check...\n";
    
    $memory_limit = ini_get('memory_limit');
    $memory_usage = memory_get_usage(true);
    $memory_peak = memory_get_peak_usage(true);
    $execution_time = ini_get('max_execution_time');
    
    echo "   Memory Limit: $memory_limit\n";
    echo "   Current Usage: " . format_bytes($memory_usage) . "\n";
    echo "   Peak Usage: " . format_bytes($memory_peak) . "\n";
    echo "   Max Execution Time: {$execution_time}s\n";
    
    // Check if memory limit is sufficient
    $memory_limit_bytes = return_bytes($memory_limit);
    if ($memory_limit_bytes > 0 && $memory_limit_bytes < 134217728) { // 128MB
        echo "   ⚠️  WARNING: Memory limit may be too low for complex plugins\n";
        echo "      Recommended: 128M or higher\n";
    } else {
        echo "   ✅ Memory limit appears sufficient\n";
    }
}

// Function to validate plugin files
function validate_plugin_files() {
    echo "\n4. Plugin Files Validation...\n";
    
    $main_file = WCEFP_PLUGIN_DIR . 'wceventsfp.php';
    
    if (!file_exists($main_file)) {
        echo "   ❌ Main plugin file missing: wceventsfp.php\n";
        return;
    }
    
    // Check main file syntax
    $result = shell_exec("php -l '$main_file' 2>&1");
    if (strpos($result, 'No syntax errors') !== false) {
        echo "   ✅ Main plugin file syntax OK\n";
    } else {
        echo "   ❌ Main plugin file has syntax errors:\n";
        echo "   " . trim($result) . "\n";
        return;
    }
    
    // Check required includes exist and have valid syntax
    $required_files = [
        'includes/class-wcefp-logger.php',
        'includes/Legacy/class-wcefp-validator.php',
        'includes/Legacy/class-wcefp-cache.php',
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
        'includes/class-wcefp-webhook-system.php',
        'admin/class-wcefp-admin.php',
        'admin/class-wcefp-admin-settings.php',
        'admin/class-wcefp-analytics-dashboard.php',
        'admin/class-wcefp-meetingpoints.php',
        'admin/class-wcefp-vouchers-table.php',
        'admin/class-wcefp-vouchers-admin.php',
        'admin/class-wcefp-orders-bridge.php'
    ];
    
    $missing_files = 0;
    $syntax_errors = 0;
    
    foreach ($required_files as $file) {
        $full_path = WCEFP_PLUGIN_DIR . $file;
        if (!file_exists($full_path)) {
            echo "   ❌ Missing: $file\n";
            $missing_files++;
        } else {
            $result = shell_exec("php -l '$full_path' 2>&1");
            if (strpos($result, 'No syntax errors') === false) {
                echo "   ❌ Syntax error in: $file\n";
                echo "      " . trim($result) . "\n";
                $syntax_errors++;
            }
        }
    }
    
    if ($missing_files == 0 && $syntax_errors == 0) {
        echo "   ✅ All required plugin files validated successfully\n";
    } else {
        echo "   ⚠️  Found $missing_files missing files and $syntax_errors files with syntax errors\n";
    }
}

// Function to check file permissions
function check_permissions() {
    echo "\n5. File Permissions Check...\n";
    
    $critical_files = [
        'wceventsfp.php',
        'includes/',
        'admin/',
        'assets/',
        'languages/'
    ];
    
    foreach ($critical_files as $file) {
        $full_path = WCEFP_PLUGIN_DIR . $file;
        if (file_exists($full_path)) {
            $perms = substr(sprintf('%o', fileperms($full_path)), -4);
            $readable = is_readable($full_path);
            $writable = is_writable($full_path);
            
            echo "   $file: $perms";
            if ($readable) echo " (readable)";
            if ($writable) echo " (writable)";
            echo "\n";
            
            if (!$readable) {
                echo "   ⚠️  File/directory is not readable\n";
            }
        } else {
            echo "   ❌ Missing: $file\n";
        }
    }
}

// Function to generate recommendations
function generate_recommendations() {
    echo "\n6. Troubleshooting Recommendations...\n";
    
    echo "   If you experience WSOD (White Screen of Death):\n";
    echo "   \n";
    echo "   1. Enable WordPress Debug Mode:\n";
    echo "      - Add to wp-config.php: define('WP_DEBUG', true);\n";
    echo "      - Add to wp-config.php: define('WP_DEBUG_LOG', true);\n";
    echo "      - Check wp-content/debug.log for errors\n";
    echo "   \n";
    echo "   2. Check Server Error Logs:\n";
    echo "      - Check your hosting provider's error logs\n";
    echo "      - Look for PHP fatal errors or memory limit exceeded\n";
    echo "   \n";
    echo "   3. Increase PHP Limits (in .htaccess or php.ini):\n";
    echo "      - memory_limit = 256M\n";
    echo "      - max_execution_time = 300\n";
    echo "      - max_input_vars = 3000\n";
    echo "   \n";
    echo "   4. Ensure WooCommerce is Active:\n";
    echo "      - WCEventsFP requires WooCommerce to be installed and activated\n";
    echo "   \n";
    echo "   5. Check Plugin Conflicts:\n";
    echo "      - Temporarily deactivate other plugins\n";
    echo "      - Switch to a default WordPress theme\n";
    echo "   \n";
    echo "   6. Database Issues:\n";
    echo "      - Ensure database user has CREATE, ALTER, INDEX permissions\n";
    echo "      - Check database server storage space\n";
    echo "   \n";
    echo "   7. Recovery Mode:\n";
    echo "      - If plugin causes WSOD, access via FTP/cPanel\n";
    echo "      - Rename plugin folder to deactivate temporarily\n";
    echo "      - Fix underlying issues before reactivating\n";
}

// Helper functions
function format_bytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}

// Run all checks
check_wp_environment();
check_database();
check_performance();
validate_plugin_files();
check_permissions();
generate_recommendations();

echo "\n=== Diagnostic Complete ===\n";
echo "If issues persist, please share this diagnostic output with support.\n";