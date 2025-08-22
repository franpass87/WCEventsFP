<?php
/**
 * WCEventsFP Plugin Health Check
 * Run this script to diagnose potential issues with the plugin
 * Usage: php wcefp-health-check.php
 */

if (!defined('WCEFP_PLUGIN_DIR')) {
    define('WCEFP_PLUGIN_DIR', dirname(__FILE__) . '/');
}

echo "=== WCEventsFP Plugin Health Check ===\n\n";

// Check PHP version
echo "1. Checking PHP version...\n";
$php_version = phpversion();
echo "   Current PHP version: $php_version\n";
if (version_compare($php_version, '7.4.0', '<')) {
    echo "   ⚠️  WARNING: PHP 7.4+ is recommended\n";
} else {
    echo "   ✅ PHP version OK\n";
}

// Check required files
echo "\n2. Checking required files...\n";
$required_files = [
    'includes/class-wcefp-logger.php',
    'includes/class-wcefp-validator.php', 
    'includes/class-wcefp-cache.php',
    'includes/class-wcefp-recurring.php',
    'includes/class-wcefp-closures.php',
    'includes/class-wcefp-gift.php',
    'includes/class-wcefp-frontend.php',
    'includes/class-wcefp-templates.php',
    'includes/class-wcefp-product-types.php',
    'admin/class-wcefp-admin.php',
    'admin/class-wcefp-admin-settings.php'
];

$missing_files = [];
foreach ($required_files as $file) {
    $full_path = WCEFP_PLUGIN_DIR . $file;
    if (!file_exists($full_path)) {
        $missing_files[] = $file;
        echo "   ❌ MISSING: $file\n";
    } else {
        echo "   ✅ EXISTS:  $file\n";
    }
}

// Check file syntax
echo "\n3. Checking file syntax...\n";
$syntax_errors = [];
foreach ($required_files as $file) {
    $full_path = WCEFP_PLUGIN_DIR . $file;
    if (file_exists($full_path)) {
        $result = shell_exec("php -l '$full_path' 2>&1");
        if (strpos($result, 'No syntax errors') === false) {
            $syntax_errors[] = $file;
            echo "   ❌ SYNTAX ERROR in $file\n";
        } else {
            echo "   ✅ VALID:  $file\n";
        }
    }
}

// Check main plugin file
echo "\n4. Checking main plugin file...\n";
$main_file = WCEFP_PLUGIN_DIR . 'wceventsfp.php';
if (!file_exists($main_file)) {
    echo "   ❌ Main plugin file missing!\n";
} else {
    $result = shell_exec("php -l '$main_file' 2>&1");
    if (strpos($result, 'No syntax errors') === false) {
        echo "   ❌ SYNTAX ERROR in main file\n";
        echo "      $result\n";
    } else {
        echo "   ✅ Main plugin file syntax OK\n";
    }
}

// Check memory and execution limits
echo "\n5. Checking PHP limits...\n";
$memory_limit = ini_get('memory_limit');
$max_execution_time = ini_get('max_execution_time');
echo "   Memory limit: $memory_limit\n";
echo "   Execution time limit: {$max_execution_time}s\n";

$memory_bytes = wp_convert_hr_to_bytes($memory_limit);
if ($memory_bytes < 128 * 1024 * 1024) {
    echo "   ⚠️  WARNING: Consider increasing memory_limit to 128M or higher\n";
} else {
    echo "   ✅ Memory limit OK\n";
}

// Final report
echo "\n=== Health Check Summary ===\n";

$issues = [];
if (!empty($missing_files)) {
    $issues[] = count($missing_files) . " missing files";
}
if (!empty($syntax_errors)) {
    $issues[] = count($syntax_errors) . " syntax errors";
}

if (empty($issues)) {
    echo "✅ Plugin health check PASSED - No issues detected\n";
    exit(0);
} else {
    echo "❌ Plugin health check FAILED\n";
    echo "Issues found:\n";
    foreach ($issues as $issue) {
        echo "- $issue\n";
    }
    echo "\nPlease fix these issues before activating the plugin.\n";
    exit(1);
}

// Helper function to convert human readable sizes to bytes
function wp_convert_hr_to_bytes($size) {
    $size = strtolower($size);
    $bytes = (int) $size;

    if (strpos($size, 'k') !== false) {
        $bytes = intval($size) * 1024;
    } elseif (strpos($size, 'm') !== false) {
        $bytes = intval($size) * 1024 * 1024;
    } elseif (strpos($size, 'g') !== false) {
        $bytes = intval($size) * 1024 * 1024 * 1024;
    }

    return $bytes;
}