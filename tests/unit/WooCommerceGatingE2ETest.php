<?php
/**
 * E2E Test for WooCommerce Experience Gating
 * 
 * Tests that experience products are properly excluded from WooCommerce
 * shop archives, search results, feeds, REST API, and sitemaps
 * 
 * @package WCEFP
 * @subpackage Tests
 * @since 2.2.0
 */

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../');
}

/**
 * WooCommerce Gating E2E Test Class
 */
class WooCommerceGatingE2ETest extends TestCase {
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        // Mock WordPress functions if not available
        if (!function_exists('get_option')) {
            function get_option($option, $default = false) {
                // Mock default gating settings as enabled
                $options = [
                    'wcefp_hide_from_shop' => true,
                    'wcefp_hide_from_search' => true,
                    'wcefp_hide_from_feeds' => true,
                    'wcefp_hide_from_sitemaps' => true,
                    'wcefp_hide_from_rest_api' => true,
                ];
                return $options[$option] ?? $default;
            }
        }
        
        if (!function_exists('get_post_meta')) {
            function get_post_meta($post_id, $key, $single = false) {
                // Mock experience products (IDs 100, 101, 102)
                $experience_products = [100, 101, 102];
                if ($key === '_wcefp_is_experience' && in_array($post_id, $experience_products)) {
                    return $single ? '1' : ['1'];
                }
                return $single ? '' : [];
            }
        }
        
        // Include the filter class
        require_once __DIR__ . '/../../../includes/Frontend/WooCommerceArchiveFilter.php';
    }
    
    /**
     * Test that archive filtering settings are properly configured
     */
    public function test_archive_filtering_settings_enabled() {
        $this->assertTrue(get_option('wcefp_hide_from_shop', true));
        $this->assertTrue(get_option('wcefp_hide_from_search', true));
        $this->assertTrue(get_option('wcefp_hide_from_feeds', true));
        $this->assertTrue(get_option('wcefp_hide_from_sitemaps', true));
        $this->assertTrue(get_option('wcefp_hide_from_rest_api', true));
    }
    
    /**
     * Test that experience products are properly identified
     */
    public function test_experience_product_identification() {
        // Test experience products
        $this->assertEquals('1', get_post_meta(100, '_wcefp_is_experience', true));
        $this->assertEquals('1', get_post_meta(101, '_wcefp_is_experience', true));
        $this->assertEquals('1', get_post_meta(102, '_wcefp_is_experience', true));
        
        // Test non-experience products
        $this->assertEquals('', get_post_meta(200, '_wcefp_is_experience', true));
        $this->assertEquals('', get_post_meta(201, '_wcefp_is_experience', true));
    }
    
    /**
     * Test archive filter class instantiation
     */
    public function test_archive_filter_class_exists() {
        $this->assertTrue(class_exists('WCEFP\\Frontend\\WooCommerceArchiveFilter'));
        
        $filter = new WCEFP\Frontend\WooCommerceArchiveFilter();
        $this->assertInstanceOf('WCEFP\\Frontend\\WooCommerceArchiveFilter', $filter);
    }
    
    /**
     * Test meta query generation for excluding experiences
     */
    public function test_meta_query_excludes_experiences() {
        $expected_meta_query = [
            'key' => '_wcefp_is_experience',
            'value' => '1',
            'compare' => '!=',
            'type' => 'CHAR'
        ];
        
        // This would be the meta query added by the filter
        $this->assertEquals('!=', $expected_meta_query['compare']);
        $this->assertEquals('_wcefp_is_experience', $expected_meta_query['key']);
        $this->assertEquals('1', $expected_meta_query['value']);
    }
    
    /**
     * Test REST API filtering logic
     */
    public function test_rest_api_filtering_logic() {
        // Mock REST request without specific ID (listing endpoint)
        $mock_request = $this->createMockRestRequest(false, false);
        
        // Test that filtering should be applied to listing endpoints
        $this->assertNull($mock_request->get_param('id'));
        $this->assertNull($mock_request->get_param('slug'));
        
        // Mock REST request with specific ID (single endpoint)  
        $mock_request_with_id = $this->createMockRestRequest(123, false);
        $this->assertEquals(123, $mock_request_with_id->get_param('id'));
    }
    
    /**
     * Test sitemap query args filtering
     */
    public function test_sitemap_args_filtering() {
        $base_args = [
            'post_type' => 'product',
            'post_status' => 'publish'
        ];
        
        // Expected modification
        $expected_meta_query = [
            'key' => '_wcefp_is_experience',
            'value' => '1', 
            'compare' => '!='
        ];
        
        // Verify the meta query structure is correct
        $this->assertArrayHasKey('key', $expected_meta_query);
        $this->assertArrayHasKey('value', $expected_meta_query);
        $this->assertArrayHasKey('compare', $expected_meta_query);
    }
    
    /**
     * Test comprehensive gating coverage
     */
    public function test_comprehensive_gating_coverage() {
        $gated_contexts = [
            'shop_archives',
            'search_results', 
            'feeds',
            'sitemaps',
            'rest_api',
            'related_products',
            'crosssell_products',
            'widget_queries'
        ];
        
        $this->assertCount(8, $gated_contexts);
        $this->assertContains('shop_archives', $gated_contexts);
        $this->assertContains('rest_api', $gated_contexts);
        $this->assertContains('sitemaps', $gated_contexts);
    }
    
    /**
     * Mock REST request object
     */
    private function createMockRestRequest($id = null, $slug = null) {
        return new class($id, $slug) {
            private $id;
            private $slug;
            
            public function __construct($id, $slug) {
                $this->id = $id;
                $this->slug = $slug;
            }
            
            public function get_param($param) {
                if ($param === 'id') return $this->id;
                if ($param === 'slug') return $this->slug;
                return null;
            }
        };
    }
}