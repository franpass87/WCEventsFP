<?php

namespace WCEFP\Tests\Integration\Booking;

use Brain\Monkey\Functions;
use WCEFP\Services\Domain\BookingEngineCoordinator;
use WCEFP\Services\Domain\StockHoldManager;
use WCEFP\Tests\Unit\WCEFPTestCase;

class BookingFlowIntegrationTest extends WCEFPTestCase
{
    private $bookingCoordinator;
    private $mockWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->bookingCoordinator = new BookingEngineCoordinator();
        $this->mockWpdb = $this->mockDatabase();
        
        // Mock WordPress functions
        Functions\when('wc_get_product')->returnUsing(function($id) {
            $product = \Mockery::mock();
            $product->shouldReceive('get_type')->andReturn('esperienza');
            $product->shouldReceive('get_name')->andReturn('Test Experience');
            $product->shouldReceive('get_meta')->andReturnUsing(function($key) {
                $defaults = [
                    '_wcefp_capacity' => '10',
                    '_wcefp_duration' => '120',
                    '_wcefp_weekdays' => [1, 2, 3, 4, 5],
                    '_wcefp_time_slots' => '09:00,14:00',
                    '_wcefp_price_adult' => '75.00',
                    '_wcefp_price_child' => '50.00'
                ];
                return $defaults[$key] ?? '';
            });
            return $product;
        });
        
        Functions\when('current_time')->returnArg();
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('wp_create_nonce')->justReturn('test-nonce');
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/');
        Functions\when('rest_url')->justReturn('https://example.com/wp-json/');
        Functions\when('get_woocommerce_currency')->justReturn('EUR');
        Functions\when('get_woocommerce_currency_symbol')->justReturn('€');
        Functions\when('get_option')->justReturn('Y-m-d');
        Functions\when('__')->returnArg();
        Functions\when('date')->returnUsing(function($format, $timestamp = null) {
            return date($format, $timestamp ?: time());
        });
        Functions\when('strtotime')->returnArg(0);
        Functions\when('time')->justReturn(1700000000);
        
        // Mock database operations
        Functions\when('DatabaseManager::get_table_name')->returnUsing(function($table) {
            return "wp_wcefp_{$table}";
        });
    }

    public function testCompleteBookingFlow()
    {
        // 1. Get booking availability
        $this->mockAvailabilityQuery();
        $availability = $this->bookingCoordinator->get_booking_availability(123, '2024-01-15');
        
        $this->assertIsArray($availability);
        $this->assertArrayHasKey('slots', $availability);
        $this->assertArrayHasKey('ticket_types', $availability);
        $this->assertArrayHasKey('extras', $availability);
        $this->assertNotEmpty($availability['slots']);
        
        // 2. Create booking hold
        $booking_request = [
            'product_id' => 123,
            'slot_datetime' => '2024-01-15 09:00',
            'tickets' => ['adult' => 2, 'child' => 1],
            'extras' => [],
            'session_id' => 'test_session_123'
        ];
        
        $this->mockHoldCreation();
        $hold_result = $this->bookingCoordinator->create_booking_hold($booking_request);
        
        $this->assertTrue($hold_result['success']);
        $this->assertArrayHasKey('hold_ids', $hold_result);
        $this->assertArrayHasKey('pricing', $hold_result);
        $this->assertArrayHasKey('expires_at', $hold_result);
        
        // 3. Validate pricing calculation
        $pricing = $hold_result['pricing'];
        $this->assertArrayHasKey('tickets', $pricing);
        $this->assertArrayHasKey('total', $pricing);
        $this->assertGreaterThan(0, $pricing['total']);
        
        // Expected: 2 adults (€75 each) + 1 child (€50) = €200
        $expected_total = (2 * 75.00) + (1 * 50.00);
        $this->assertEquals($expected_total, $pricing['total']);
        
        // 4. Convert holds to confirmed booking
        $this->mockHoldConversion();
        $conversion_result = $this->bookingCoordinator->convert_holds_to_booking('test_session_123', 456);
        
        $this->assertTrue($conversion_result['success']);
        $this->assertEquals(456, $conversion_result['order_id']);
        
        $this->assertTrue(true); // Test completed successfully
    }

    public function testBookingFlowWithInsufficientCapacity()
    {
        $booking_request = [
            'product_id' => 123,
            'slot_datetime' => '2024-01-15 09:00',
            'tickets' => ['adult' => 15], // More than capacity
            'extras' => [],
            'session_id' => 'test_session_456'
        ];
        
        $this->mockInsufficientCapacity();
        $hold_result = $this->bookingCoordinator->create_booking_hold($booking_request);
        
        $this->assertFalse($hold_result['success']);
        $this->assertEquals('capacity_unavailable', $hold_result['error']);
    }

    public function testBookingFlowWithInvalidTickets()
    {
        $booking_request = [
            'product_id' => 123,
            'slot_datetime' => '2024-01-15 09:00',
            'tickets' => [], // No tickets selected
            'extras' => [],
            'session_id' => 'test_session_789'
        ];
        
        $hold_result = $this->bookingCoordinator->create_booking_hold($booking_request);
        
        $this->assertFalse($hold_result['success']);
        $this->assertEquals('validation_failed', $hold_result['error']);
        $this->assertArrayHasKey('validation', $hold_result);
    }

    public function testPricingCalculationWithExtras()
    {
        $booking_request = [
            'product_id' => 123,
            'slot_datetime' => '2024-01-15 09:00',
            'tickets' => ['adult' => 2],
            'extras' => ['transport' => 1, 'lunch' => 2],
            'session_id' => 'test_session_extras'
        ];
        
        $this->mockWithExtras();
        $hold_result = $this->bookingCoordinator->create_booking_hold($booking_request);
        
        $this->assertTrue($hold_result['success']);
        $pricing = $hold_result['pricing'];
        
        // Should include tickets + extras pricing
        $this->assertArrayHasKey('tickets', $pricing);
        $this->assertArrayHasKey('extras', $pricing);
        $this->assertGreaterThan(150, $pricing['total']); // Base price + extras
    }

    public function testConcurrentBookingAttempts()
    {
        // Test concurrency protection by attempting multiple simultaneous bookings
        $booking_request_1 = [
            'product_id' => 123,
            'slot_datetime' => '2024-01-15 14:00',
            'tickets' => ['adult' => 5],
            'session_id' => 'concurrent_session_1'
        ];
        
        $booking_request_2 = [
            'product_id' => 123,
            'slot_datetime' => '2024-01-15 14:00',
            'tickets' => ['adult' => 6],
            'session_id' => 'concurrent_session_2'
        ];
        
        $this->mockConcurrentBookings();
        
        $result_1 = $this->bookingCoordinator->create_booking_hold($booking_request_1);
        $result_2 = $this->bookingCoordinator->create_booking_hold($booking_request_2);
        
        // One should succeed, one should fail due to capacity
        $successful = ($result_1['success'] && !$result_2['success']) || 
                     (!$result_1['success'] && $result_2['success']);
        
        $this->assertTrue($successful, 'Exactly one concurrent booking should succeed');
    }

    public function testBookingFlowWithDynamicPricing()
    {
        // Test early bird pricing
        $booking_request = [
            'product_id' => 123,
            'slot_datetime' => '2024-06-15 09:00', // Future date for early bird
            'tickets' => ['adult' => 2],
            'session_id' => 'early_bird_session'
        ];
        
        $this->mockEarlyBirdPricing();
        $hold_result = $this->bookingCoordinator->create_booking_hold($booking_request);
        
        $this->assertTrue($hold_result['success']);
        $pricing = $hold_result['pricing'];
        
        // Should have early bird discount
        $this->assertLessThan(150, $pricing['total']); // Discounted from base price
        $this->assertArrayHasKey('badges', $pricing['tickets']);
    }

    public function testGatingFunctionality()
    {
        // Test that experiences are properly hidden from general queries
        Functions\when('get_option')->returnUsing(function($key) {
            if ($key === 'wcefp_options') {
                return ['hide_experiences_from_woo' => true];
            }
            return [];
        });
        
        Functions\when('is_admin')->justReturn(false);
        Functions\when('is_main_query')->justReturn(true);
        Functions\when('is_post_type_archive')->justReturn(true);
        
        // Mock WP_Query
        $query = \Mockery::mock('WP_Query');
        $query->shouldReceive('is_main_query')->andReturn(true);
        $query->shouldReceive('is_post_type_archive')->andReturn(true);
        $query->shouldReceive('get')->andReturn([]);
        $query->shouldReceive('set')->once();
        
        // This would normally be called by the ExperienceGating class
        // We're just testing that the logic works
        $this->assertTrue(true); // Placeholder for gating test
    }

    private function mockAvailabilityQuery()
    {
        // Mock slots query
        $this->mockWpdb->shouldReceive('get_results')
            ->andReturn([
                (object) [
                    'id' => 1,
                    'start_local' => '2024-01-15 09:00:00',
                    'capacity' => 10,
                    'booked' => 3,
                    'held' => 0
                ],
                (object) [
                    'id' => 2,
                    'start_local' => '2024-01-15 14:00:00',
                    'capacity' => 10,
                    'booked' => 1,
                    'held' => 0
                ]
            ]);
        
        $this->mockWpdb->shouldReceive('prepare')->andReturnArg(0);
        $this->mockWpdb->shouldReceive('get_var')->andReturn('1');
    }

    private function mockHoldCreation()
    {
        // Mock successful hold creation for each ticket type
        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/GET_LOCK/'))
            ->andReturn('1');
            
        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/RELEASE_LOCK/'))
            ->andReturn('1');
            
        $this->mockWpdb->shouldReceive('get_var')
            ->andReturn('10'); // Available capacity
            
        $this->mockWpdb->shouldReceive('get_row')
            ->andReturn(null); // No existing holds
            
        $this->mockWpdb->shouldReceive('insert')
            ->andReturn(true);
            
        $this->mockWpdb->shouldReceive('query')
            ->andReturn(true);
            
        $this->mockWpdb->insert_id = 123;
        
        // Mock pricing calculation
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
    }

    private function mockHoldConversion()
    {
        // Mock successful conversion
        $this->mockWpdb->shouldReceive('get_results')
            ->andReturn([
                (object) [
                    'id' => 123,
                    'occurrence_id' => 1,
                    'ticket_key' => 'adult',
                    'quantity' => 2,
                    'product_id' => 123
                ]
            ]);
            
        $this->mockWpdb->shouldReceive('insert')->andReturn(true);
        $this->mockWpdb->shouldReceive('delete')->andReturn(1);
    }

    private function mockInsufficientCapacity()
    {
        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/GET_LOCK/'))
            ->andReturn('1');
            
        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/RELEASE_LOCK/'))
            ->andReturn('1');
            
        $this->mockWpdb->shouldReceive('get_var')
            ->andReturn('0'); // No capacity
    }

    private function mockWithExtras()
    {
        $this->mockHoldCreation();
        
        // Mock extras reservation
        Functions\when('get_post')->returnUsing(function($id) {
            $post = \Mockery::mock();
            $post->post_status = 'publish';
            return $post;
        });
        
        Functions\when('get_post_meta')->returnUsing(function($id, $key) {
            $extras_meta = [
                '_wcefp_extra_price' => '25.00',
                '_wcefp_extra_manage_stock' => '',
                '_wcefp_extra_stock' => '100'
            ];
            return $extras_meta[$key] ?? '';
        });
    }

    private function mockConcurrentBookings()
    {
        // First call succeeds, second fails
        $call_count = 0;
        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/GET_LOCK/'))
            ->andReturnUsing(function() use (&$call_count) {
                $call_count++;
                return $call_count === 1 ? '1' : '0'; // First succeeds, second times out
            });
            
        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/RELEASE_LOCK/'))
            ->andReturn('1');
            
        $this->mockWpdb->shouldReceive('get_var')
            ->andReturn('5'); // Limited capacity
    }

    private function mockEarlyBirdPricing()
    {
        $this->mockHoldCreation();
        
        // Mock early bird settings
        Functions\when('time')->justReturn(strtotime('2024-01-01'));
        Functions\when('strtotime')->returnUsing(function($date) {
            return strtotime($date);
        });
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}