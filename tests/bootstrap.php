<?php
/**
 * PHPUnit bootstrap file for WCEventsFP tests.
 */

// Define test environment constants
define( 'WCEFP_TESTS_DIR', __DIR__ );
define( 'WCEFP_PLUGIN_DIR', dirname( __DIR__ ) );

// Load Composer autoloader
require_once WCEFP_PLUGIN_DIR . '/vendor/autoload.php';

// Load Brain Monkey for WordPress function mocking
\Brain\Monkey\setUp();

// Mock WordPress globals that might be needed
$GLOBALS['wpdb'] = \Mockery::mock( 'wpdb' );

// Clean up after each test
register_shutdown_function( function() {
    \Brain\Monkey\tearDown();
} );