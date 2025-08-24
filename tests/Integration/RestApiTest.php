<?php
/**
 * REST API Integration Tests
 *
 * @package WCEFP\Tests
 */

namespace WCEFP\Tests\Integration;

use WCEFP\Tests\Unit\WCEFPTestCase;
use WP_REST_Request;
use WP_REST_Response;
use WCEFP\API\RestApiManager;

class RestApiTest extends WCEFPTestCase {
    
    private $api_manager;
    private $server;
    
    public function setUp(): void {
        parent::setUp();
        
        global $wp_rest_server;
        $this->server = $wp_rest_server = new \WP_REST_Server;
        do_action('rest_api_init');
        
        $this->api_manager = new RestApiManager();
    }
    
    public function tearDown(): void {
        global $wp_rest_server;
        $wp_rest_server = null;
        parent::tearDown();
    }
    
    /**
     * Test REST API registration
     */
    public function test_rest_routes_registered() {
        $routes = rest_get_server()->get_routes();
        
        // Check main endpoints exist
        $this->assertArrayHasKey('/wcefp/v1/bookings', $routes);
        $this->assertArrayHasKey('/wcefp/v1/events', $routes);
        $this->assertArrayHasKey('/wcefp/v1/system/status', $routes);
        $this->assertArrayHasKey('/wcefp/v1/system/health', $routes);
        $this->assertArrayHasKey('/wcefp/v1/export/bookings', $routes);
        $this->assertArrayHasKey('/wcefp/v1/export/calendar', $routes);
    }
    
    /**
     * Test system status endpoint
     */
    public function test_system_status_endpoint() {
        // Create admin user and authenticate
        $admin_user = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_user);
        
        $request = new WP_REST_Request('GET', '/wcefp/v1/system/status');
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertArrayHasKey('plugin_version', $data);
        $this->assertArrayHasKey('wordpress_version', $data);
        $this->assertArrayHasKey('php_version', $data);
        $this->assertArrayHasKey('database', $data);
        $this->assertArrayHasKey('dependencies', $data);
    }
    
    /**
     * Test system health endpoint
     */
    public function test_system_health_endpoint() {
        $admin_user = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_user);
        
        $request = new WP_REST_Request('GET', '/wcefp/v1/system/health');
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertArrayHasKey('overall_status', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('checked_at', $data);
        
        $this->assertContains($data['overall_status'], ['good', 'warning', 'critical']);
    }
    
    /**
     * Test events endpoint accessibility
     */
    public function test_events_endpoint_public() {
        // Create a test event
        $event_id = $this->factory->post->create([
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Test Event'
        ]);
        
        update_post_meta($event_id, '_wcefp_is_event', '1');
        update_post_meta($event_id, '_wcefp_capacity', '10');
        
        $request = new WP_REST_Request('GET', '/wcefp/v1/events');
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertIsArray($data);
        
        // Should include our test event
        $event_titles = wp_list_pluck($data, 'title');
        $this->assertContains('Test Event', $event_titles);
    }
    
    /**
     * Test single event endpoint
     */
    public function test_single_event_endpoint() {
        $event_id = $this->factory->post->create([
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Test Single Event'
        ]);
        
        update_post_meta($event_id, '_wcefp_is_event', '1');
        update_post_meta($event_id, '_wcefp_capacity', '15');
        
        $request = new WP_REST_Request('GET', "/wcefp/v1/events/{$event_id}");
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertEquals($event_id, $data['id']);
        $this->assertEquals('Test Single Event', $data['title']);
        $this->assertEquals(15, $data['event_meta']['capacity']);
    }
    
    /**
     * Test event occurrences endpoint
     */
    public function test_event_occurrences_endpoint() {
        global $wpdb;
        
        $event_id = $this->factory->post->create([
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Test Occurrences Event'
        ]);
        
        update_post_meta($event_id, '_wcefp_is_event', '1');
        update_post_meta($event_id, '_wcefp_capacity', '20');
        
        // Create test occurrence data
        $wpdb->insert(
            $wpdb->prefix . 'wcefp_occorrenze',
            [
                'product_id' => $event_id,
                'data_evento' => date('Y-m-d', strtotime('+1 week')),
                'ora_evento' => '10:00:00',
                'nome' => 'Test Customer',
                'email' => 'test@example.com',
                'adults' => 2,
                'children' => 0,
                'stato' => 'confirmed',
                'created_at' => current_time('mysql')
            ]
        );
        
        $request = new WP_REST_Request('GET', "/wcefp/v1/events/{$event_id}/occurrences");
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertIsArray($data);
        
        if (!empty($data)) {
            $first_occurrence = $data[0];
            $this->assertArrayHasKey('date', $first_occurrence);
            $this->assertArrayHasKey('time', $first_occurrence);
            $this->assertArrayHasKey('bookings_count', $first_occurrence);
            $this->assertArrayHasKey('capacity', $first_occurrence);
            $this->assertArrayHasKey('available_spots', $first_occurrence);
        }
    }
    
    /**
     * Test bookings endpoint requires authentication
     */
    public function test_bookings_endpoint_requires_auth() {
        $request = new WP_REST_Request('GET', '/wcefp/v1/bookings');
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(401, $response->get_status());
    }
    
    /**
     * Test bookings endpoint with authentication
     */
    public function test_bookings_endpoint_with_auth() {
        $admin_user = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_user);
        
        $request = new WP_REST_Request('GET', '/wcefp/v1/bookings');
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertIsArray($data);
    }
    
    /**
     * Test export bookings endpoint
     */
    public function test_export_bookings_endpoint() {
        global $wpdb;
        
        $admin_user = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_user);
        
        // Create test booking data
        $event_id = $this->factory->post->create([
            'post_type' => 'product',
            'post_status' => 'publish'
        ]);
        
        $wpdb->insert(
            $wpdb->prefix . 'wcefp_occorrenze',
            [
                'product_id' => $event_id,
                'data_evento' => date('Y-m-d'),
                'ora_evento' => '14:00:00',
                'nome' => 'Export Test Customer',
                'email' => 'export@example.com',
                'adults' => 1,
                'children' => 1,
                'stato' => 'confirmed',
                'prezzo_totale' => 50.00,
                'created_at' => current_time('mysql')
            ]
        );
        
        $request = new WP_REST_Request('GET', '/wcefp/v1/export/bookings');
        $request->set_param('format', 'json');
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertArrayHasKey('filename', $data);
        $this->assertArrayHasKey('content', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertStringContains('json', $data['filename']);
    }
    
    /**
     * Test export calendar endpoint
     */
    public function test_export_calendar_endpoint() {
        global $wpdb;
        
        $admin_user = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_user);
        
        // Create test event and booking
        $event_id = $this->factory->post->create([
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Calendar Export Event',
            'post_content' => 'Test event description'
        ]);
        
        $wpdb->insert(
            $wpdb->prefix . 'wcefp_occorrenze',
            [
                'product_id' => $event_id,
                'data_evento' => date('Y-m-d', strtotime('+2 days')),
                'ora_evento' => '16:00:00',
                'nome' => 'Calendar Customer',
                'email' => 'calendar@example.com',
                'adults' => 2,
                'children' => 0,
                'stato' => 'confirmed',
                'meetingpoint' => 'Test Location',
                'created_at' => current_time('mysql')
            ]
        );
        
        $request = new WP_REST_Request('GET', '/wcefp/v1/export/calendar');
        $request->set_param('format', 'ics');
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(200, $response->get_status());
        
        $data = $response->get_data();
        $this->assertArrayHasKey('filename', $data);
        $this->assertArrayHasKey('content', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertStringContains('ics', $data['filename']);
        
        // Verify ICS content structure
        $ics_content = base64_decode($data['content']);
        $this->assertStringContains('BEGIN:VCALENDAR', $ics_content);
        $this->assertStringContains('BEGIN:VEVENT', $ics_content);
        $this->assertStringContains('Calendar Export Event', $ics_content);
        $this->assertStringContains('Test Location', $ics_content);
        $this->assertStringContains('END:VCALENDAR', $ics_content);
    }
    
    /**
     * Test API error handling
     */
    public function test_api_error_handling() {
        // Test non-existent event
        $request = new WP_REST_Request('GET', '/wcefp/v1/events/99999');
        $response = rest_get_server()->dispatch($request);
        
        $this->assertEquals(404, $response->get_status());
        
        $data = $response->get_data();
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('message', $data);
    }
}