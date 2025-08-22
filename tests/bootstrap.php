<?php
/**
 * PHPUnit bootstrap file for WCEventsFP tests.
 */

// Define test environment constants
define( 'WCEFP_TESTS_DIR', __DIR__ );
define( 'WCEFP_PLUGIN_DIR', dirname( __DIR__ ) );

// Provide minimal WordPress constant to prevent direct access exits
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', WCEFP_PLUGIN_DIR . '/' );
}

// Load Composer autoloader
require_once WCEFP_PLUGIN_DIR . '/vendor/autoload.php';

// Load Brain Monkey for WordPress function mocking
\Brain\Monkey\setUp();

// Mock WordPress globals that might be needed
$GLOBALS['wpdb'] = \Mockery::mock( 'wpdb' );

// Provide minimal logger stub to satisfy class references during tests
if ( ! class_exists( 'WCEFP_Logger' ) ) {
    class WCEFP_Logger {
        public static function debug( $message, $context = [] ) {}
        public static function info( $message, $context = [] ) {}
        public static function warning( $message, $context = [] ) {}
        public static function error( $message, $context = [] ) {}
    }
}

// Clean up after each test
register_shutdown_function( function() {
    \Brain\Monkey\tearDown();
} );