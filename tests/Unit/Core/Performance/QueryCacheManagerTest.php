<?php

namespace WCEFP\Tests\Unit\Core\Performance;

use Brain\Monkey\Functions;
use WCEFP\Core\Performance\QueryCacheManager;
use WCEFP\Tests\Unit\WCEFPTestCase;

class QueryCacheManagerTest extends WCEFPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock WordPress cache functions
        Functions\when('wp_cache_get')->returnUsing(function($key, $group) {
            return false; // Cache miss by default
        });
        
        Functions\when('wp_cache_set')->returnUsing(function($key, $data, $group, $duration) {
            return true;
        });
        
        Functions\when('wp_cache_delete')->returnUsing(function($key, $group) {
            return true;
        });
        
        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);
        Functions\when('get_option')->justReturn([]);
        Functions\when('current_user_can')->justReturn(true);
        
        // Reset cache stats for each test
        $reflection = new \ReflectionClass(QueryCacheManager::class);
        $statsProperty = $reflection->getProperty('cache_stats');
        $statsProperty->setAccessible(true);
        $statsProperty->setValue([
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0
        ]);
    }

    public function testGetCachedWithCacheHit()
    {
        // Mock cache hit
        $cached_data = ['test' => 'data'];
        Functions\when('wp_cache_get')->justReturn($cached_data);
        
        $result = QueryCacheManager::get_cached('test_key', 'test_group');
        
        $this->assertEquals($cached_data, $result);
        
        // Check cache stats
        $stats = QueryCacheManager::get_cache_stats();
        $this->assertEquals(1, $stats['hits']);
        $this->assertEquals(0, $stats['misses']);
    }

    public function testGetCachedWithCacheMissAndCallback()
    {
        // Mock cache miss
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        
        $fresh_data = ['fresh' => 'data'];
        $callback = function() use ($fresh_data) {
            return $fresh_data;
        };
        
        $result = QueryCacheManager::get_cached('test_key', 'test_group', $callback);
        
        $this->assertEquals($fresh_data, $result);
        
        // Check cache stats
        $stats = QueryCacheManager::get_cache_stats();
        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertEquals(1, $stats['sets']);
    }

    public function testSetCacheSuccessfully()
    {
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('time')->justReturn(1000);
        
        $result = QueryCacheManager::set_cache('test_key', ['data' => 'value'], 'test_group', 900);
        
        $this->assertTrue($result);
        
        $stats = QueryCacheManager::get_cache_stats();
        $this->assertEquals(1, $stats['sets']);
    }

    public function testDeleteCacheSuccessfully()
    {
        Functions\when('wp_cache_delete')->justReturn(true);
        
        $result = QueryCacheManager::delete_cache('test_key', 'test_group');
        
        $this->assertTrue($result);
        
        $stats = QueryCacheManager::get_cache_stats();
        $this->assertEquals(1, $stats['deletes']);
    }

    public function testCacheCatalogQuery()
    {
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        
        $query_args = [
            'category' => 'outdoor',
            'location' => 'rome',
            'search' => 'tour'
        ];
        
        $expected_data = [
            'products' => [1, 2, 3],
            'total' => 3
        ];
        
        $callback = function() use ($expected_data) {
            return $expected_data;
        };
        
        $result = QueryCacheManager::cache_catalog_query($query_args, $callback);
        
        $this->assertEquals($expected_data, $result);
    }

    public function testCacheAvailabilityQuery()
    {
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        
        $availability_data = [
            'slots' => [
                ['time' => '09:00', 'available' => 5],
                ['time' => '14:00', 'available' => 3]
            ]
        ];
        
        $callback = function() use ($availability_data) {
            return $availability_data;
        };
        
        $result = QueryCacheManager::cache_availability_query(123, '2024-01-15', $callback);
        
        $this->assertEquals($availability_data, $result);
    }

    public function testCachePricingQuery()
    {
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        
        $pricing_context = [
            'date' => '2024-01-15',
            'tickets' => ['adult' => 2],
            'extras' => []
        ];
        
        $pricing_data = [
            'total' => 150.00,
            'breakdown' => ['adult' => 75.00]
        ];
        
        $callback = function() use ($pricing_data) {
            return $pricing_data;
        };
        
        $result = QueryCacheManager::cache_pricing_query(123, $pricing_context, $callback);
        
        $this->assertEquals($pricing_data, $result);
    }

    public function testInvalidateProductCache()
    {
        // Mock post object
        $post = (object) [
            'post_type' => 'product'
        ];
        
        Functions\when('get_transient')->justReturn([]);
        Functions\when('set_transient')->justReturn(true);
        
        // This should not throw any exceptions
        QueryCacheManager::invalidate_product_cache(123, $post);
        
        $this->assertTrue(true); // Test passes if no exception thrown
    }

    public function testInvalidateCapacityCache()
    {
        Functions\when('wc_get_order')->returnUsing(function() {
            $mockOrder = \Mockery::mock();
            $mockItem = \Mockery::mock();
            $mockItem->shouldReceive('get_product_id')->andReturn(123);
            $mockOrder->shouldReceive('get_items')->andReturn([1 => $mockItem]);
            return $mockOrder;
        });
        
        // This should not throw any exceptions
        QueryCacheManager::invalidate_capacity_cache(456, 'test-session');
        
        $this->assertTrue(true); // Test passes if no exception thrown
    }

    public function testCacheStatsCalculation()
    {
        // Simulate some cache operations
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        
        // Generate some hits and misses
        for ($i = 0; $i < 8; $i++) {
            QueryCacheManager::get_cached("key_{$i}", 'test_group', function() {
                return "data_{$i}";
            });
        }
        
        // Mock some hits
        Functions\when('wp_cache_get')->justReturn(['cached' => 'data']);
        for ($i = 0; $i < 2; $i++) {
            QueryCacheManager::get_cached("cached_key_{$i}", 'test_group');
        }
        
        $stats = QueryCacheManager::get_cache_stats();
        
        $this->assertEquals(2, $stats['hits']);
        $this->assertEquals(8, $stats['misses']);
        $this->assertEquals(8, $stats['sets']);
        $this->assertEquals(20.0, $stats['hit_rate']); // 2/(2+8) * 100
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}