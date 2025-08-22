<?php

namespace WCEFP\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Tests for WCEFP_Cache class
 */
class CacheTest extends WCEFPTestCase {

    public function setUp(): void {
        parent::setUp();
        
        // Mock WordPress caching functions
        Functions\when( 'wp_cache_get' )->justReturn( false );
        Functions\when( 'wp_cache_set' )->justReturn( true );
        Functions\when( 'wp_cache_delete' )->justReturn( true );
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'set_transient' )->justReturn( true );
        Functions\when( 'delete_transient' )->justReturn( true );
        
        // Include the cache class
        require_once WCEFP_PLUGIN_DIR . '/includes/class-wcefp-cache.php';
    }

    public function test_set_and_get_cache() {
        $test_data = ['test' => 'value'];
        
        // Mock successful cache operations
        Functions\when( 'wp_cache_set' )->justReturn( true );
        Functions\when( 'wp_cache_get' )->justReturn( $test_data );
        
        $set_result = \WCEFP_Cache::set( 'test_key', $test_data, 3600 );
        $get_result = \WCEFP_Cache::get( 'test_key' );
        
        $this->assertTrue( $set_result );
        $this->assertEquals( $test_data, $get_result );
    }

    public function test_delete_cache() {
        Functions\when( 'wp_cache_delete' )->justReturn( true );
        
        $result = \WCEFP_Cache::delete( 'test_key' );
        
        $this->assertTrue( $result );
    }

    public function test_get_kpi_data_caching() {
        $mock_kpi_data = [
            'total_orders' => 100,
            'total_revenue' => 5000,
            'avg_capacity' => 75
        ];
        
        // Mock the KPI data retrieval
        Functions\when( 'wp_cache_get' )->justReturn( $mock_kpi_data );
        
        $result = \WCEFP_Cache::get_kpi_data( 30 );
        
        $this->assertEquals( $mock_kpi_data, $result );
    }

    public function test_invalidate_product_cache() {
        $product_id = 123;
        
        Functions\when( 'wp_cache_delete' )->justReturn( true );
        Functions\when( 'delete_transient' )->justReturn( true );
        
        // This should not throw any errors
        \WCEFP_Cache::invalidate_product_cache( $product_id );
        
        // Since the method doesn't return anything, just assert it runs
        $this->assertTrue( true );
    }

    public function test_clear_all_cache() {
        Functions\when( 'wp_cache_flush' )->justReturn( true );
        
        $result = \WCEFP_Cache::clear_all();
        
        $this->assertTrue( $result );
    }
}