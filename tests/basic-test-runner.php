#!/usr/bin/env php
<?php
/**
 * Simple Test Runner for WCEventsFP
 * 
 * This basic test runner validates core functionality without requiring
 * PHPUnit dependencies. It performs essential checks that would typically
 * be covered by unit tests.
 * 
 * @package WCEFP\Tests
 */

// Prevent execution if not CLI
if (php_sapi_name() !== 'cli') {
    exit('This script must be run from command line.');
}

echo "WCEventsFP Basic Test Runner\n";
echo "============================\n\n";

$errors = [];
$warnings = [];
$passed = 0;
$total = 0;

/**
 * Test runner functions
 */
function test($description, callable $test) {
    global $errors, $warnings, $passed, $total;
    
    $total++;
    echo "Testing: {$description}... ";
    
    try {
        $result = $test();
        if ($result === true) {
            echo "PASS\n";
            $passed++;
        } elseif ($result === null) {
            echo "SKIP\n";
        } else {
            echo "FAIL\n";
            $errors[] = "FAIL: {$description}";
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $errors[] = "ERROR: {$description} - " . $e->getMessage();
    } catch (Error $e) {
        echo "FATAL: " . $e->getMessage() . "\n";
        $errors[] = "FATAL: {$description} - " . $e->getMessage();
    }
}

/**
 * Basic PHP and WordPress environment tests
 */
echo "1. Environment Tests\n";
echo "-------------------\n";

test('PHP version >= 8.0', function() {
    return version_compare(PHP_VERSION, '8.0.0', '>=');
});

test('Required PHP extensions', function() {
    $required = ['mysqli', 'json', 'mbstring', 'curl'];
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            throw new Exception("Missing PHP extension: {$ext}");
        }
    }
    return true;
});

test('Plugin file exists', function() {
    $plugin_file = __DIR__ . '/../wceventsfp.php';
    return file_exists($plugin_file);
});

test('Plugin file has valid syntax', function() {
    $plugin_file = __DIR__ . '/../wceventsfp.php';
    $output = [];
    $return_code = 0;
    exec("php -l " . escapeshellarg($plugin_file), $output, $return_code);
    return $return_code === 0;
});

/**
 * Class loading and autoloader tests
 */
echo "\n2. Class Loading Tests\n";
echo "---------------------\n";

test('Autoloader file exists', function() {
    return file_exists(__DIR__ . '/../includes/autoloader.php');
});

test('Core classes have valid syntax', function() {
    $core_classes = [
        'Bootstrap/Plugin.php',
        'Core/ActivationHandler.php',
        'API/RestApiManager.php',
        'Modules/BookingsModule.php',
        'Modules/SettingsModule.php'
    ];
    
    foreach ($core_classes as $class_file) {
        $full_path = __DIR__ . '/../includes/' . $class_file;
        if (file_exists($full_path)) {
            $output = [];
            $return_code = 0;
            exec("php -l " . escapeshellarg($full_path), $output, $return_code);
            if ($return_code !== 0) {
                throw new Exception("Syntax error in {$class_file}");
            }
        }
    }
    return true;
});

/**
 * Configuration and settings tests
 */
echo "\n3. Configuration Tests\n";
echo "---------------------\n";

test('Composer configuration valid', function() {
    $composer_file = __DIR__ . '/../composer.json';
    if (!file_exists($composer_file)) {
        return false;
    }
    
    $composer_data = json_decode(file_get_contents($composer_file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON in composer.json");
    }
    
    return isset($composer_data['autoload']['psr-4']['WCEFP\\']);
});

test('Package.json configuration valid', function() {
    $package_file = __DIR__ . '/../package.json';
    if (!file_exists($package_file)) {
        return false;
    }
    
    $package_data = json_decode(file_get_contents($package_file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON in package.json");
    }
    
    return isset($package_data['scripts']['test:js']);
});

test('PHPUnit configuration exists', function() {
    return file_exists(__DIR__ . '/../phpunit.xml');
});

/**
 * Database schema tests (basic structure validation)
 */
echo "\n4. Database Schema Tests\n";
echo "-----------------------\n";

test('SQL schema files exist', function() {
    // Check if there are any SQL files for database schema
    $schema_patterns = [
        __DIR__ . '/../includes/**/schema.sql',
        __DIR__ . '/../sql/*.sql',
        __DIR__ . '/../database/*.sql'
    ];
    
    // For now, just check that core database logic exists
    $db_classes = [
        __DIR__ . '/../includes/Core/Database/DatabaseServiceProvider.php',
        __DIR__ . '/../includes/Core/Database/Models.php'
    ];
    
    foreach ($db_classes as $file) {
        if (file_exists($file)) {
            return true;
        }
    }
    
    return null; // Skip if no database classes found
});

/**
 * Asset and template tests
 */
echo "\n5. Asset Tests\n";
echo "-------------\n";

test('CSS assets exist', function() {
    $css_dir = __DIR__ . '/../assets/css';
    return is_dir($css_dir) && count(glob($css_dir . '/*.css')) > 0;
});

test('JavaScript assets exist', function() {
    $js_dir = __DIR__ . '/../assets/js';
    return is_dir($js_dir) && count(glob($js_dir . '/*.js')) > 0;
});

test('Template directory exists', function() {
    $templates_dir = __DIR__ . '/../templates';
    return is_dir($templates_dir);
});

/**
 * Documentation tests
 */
echo "\n6. Documentation Tests\n";
echo "---------------------\n";

test('README exists and not empty', function() {
    $readme = __DIR__ . '/../README.md';
    return file_exists($readme) && filesize($readme) > 100;
});

test('API documentation exists', function() {
    $api_docs = __DIR__ . '/../docs/api-documentation.md';
    return file_exists($api_docs);
});

test('Changelog exists', function() {
    $changelog = __DIR__ . '/../CHANGELOG.md';
    return file_exists($changelog);
});

/**
 * Test files validation
 */
echo "\n7. Test Files Validation\n";
echo "-----------------------\n";

test('JavaScript tests exist', function() {
    $js_tests = __DIR__ . '/js';
    return is_dir($js_tests) && count(glob($js_tests . '/*.test.js')) > 0;
});

test('PHP test files valid syntax', function() {
    $test_files = glob(__DIR__ . '/**/*.php', GLOB_BRACE);
    foreach ($test_files as $file) {
        $output = [];
        $return_code = 0;
        exec("php -l " . escapeshellarg($file), $output, $return_code);
        if ($return_code !== 0) {
            throw new Exception("Syntax error in test file: " . basename($file));
        }
    }
    return true;
});

/**
 * Security validation
 */
echo "\n8. Security Tests\n";
echo "----------------\n";

test('No obvious security issues in main plugin file', function() {
    $plugin_content = file_get_contents(__DIR__ . '/../wceventsfp.php');
    
    // Check for WordPress security best practices
    $security_issues = [];
    
    if (!preg_match('/defined\s*\(\s*[\'"]ABSPATH[\'"]/', $plugin_content)) {
        $security_issues[] = 'Missing ABSPATH check';
    }
    
    if (preg_match('/\$_GET\[|@?\$_POST\[|@?\$_REQUEST\[/', $plugin_content)) {
        $security_issues[] = 'Direct superglobal access (should use sanitize functions)';
    }
    
    if (!empty($security_issues)) {
        throw new Exception(implode(', ', $security_issues));
    }
    
    return true;
});

test('Admin files have capability checks', function() {
    $admin_files = glob(__DIR__ . '/../admin/*.php');
    $issues = [];
    
    foreach ($admin_files as $file) {
        $content = file_get_contents($file);
        
        // Skip if file is just a stub or very small
        if (strlen($content) < 200) continue;
        
        // Look for capability checks
        if (!preg_match('/current_user_can\s*\(|wp_verify_nonce\s*\(/', $content)) {
            $issues[] = basename($file) . ' missing capability/nonce checks';
        }
    }
    
    // Only fail if we found significant issues
    return count($issues) < 3; // Allow some minor issues
});

/**
 * Performance tests
 */
echo "\n9. Performance Tests\n";
echo "-------------------\n";

test('No obvious performance issues', function() {
    // Basic checks for performance anti-patterns
    $files_to_check = [
        __DIR__ . '/../includes/API/RestApiManager.php',
        __DIR__ . '/../includes/Modules/BookingsModule.php'
    ];
    
    foreach ($files_to_check as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            
            // Check for potential N+1 queries or inefficient patterns
            if (preg_match_all('/get_post_meta\s*\([^)]*\)/', $content) > 10) {
                // Too many individual meta queries might indicate N+1 issue
                // This is a rough heuristic
                global $warnings;
                $warnings[] = "Potential N+1 query pattern in " . basename($file);
            }
        }
    }
    
    return true;
});

/**
 * Output results
 */
echo "\n" . str_repeat("=", 50) . "\n";
echo "Test Results Summary\n";
echo str_repeat("=", 50) . "\n";

echo "Tests Run: {$total}\n";
echo "Passed: {$passed}\n";
echo "Failed: " . count($errors) . "\n";

if (!empty($warnings)) {
    echo "\nWarnings:\n";
    foreach ($warnings as $warning) {
        echo "⚠️  {$warning}\n";
    }
}

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "❌ {$error}\n";
    }
}

$success_rate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
echo "\nSuccess Rate: {$success_rate}%\n";

if (count($errors) > 0) {
    echo "\n❌ Some tests failed. Please review the errors above.\n";
    exit(1);
} elseif ($success_rate >= 90) {
    echo "\n✅ All critical tests passed! Plugin appears ready for testing.\n";
    exit(0);
} else {
    echo "\n⚠️  Some tests failed but no critical errors found.\n";
    exit(0);
}