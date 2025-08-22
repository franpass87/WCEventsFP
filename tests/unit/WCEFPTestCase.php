<?php

namespace WCEFP\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for WCEventsFP unit tests
 */
abstract class WCEFPTestCase extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
        
        // Common WordPress function mocks
        Functions\when( 'plugin_dir_path' )->justReturn( '/mock/path/' );
        Functions\when( 'plugin_dir_url' )->justReturn( 'https://mock.url/' );
        Functions\when( 'wp_upload_dir' )->justReturn( [
            'basedir' => '/mock/uploads',
            'baseurl' => 'https://mock.url/uploads'
        ] );
    }

    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Helper method to mock WordPress database operations
     */
    protected function mockDatabase() {
        global $wpdb;
        $wpdb = \Mockery::mock( 'wpdb' );
        $wpdb->prefix = 'wp_';
        return $wpdb;
    }
}