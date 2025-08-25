<?php

namespace WCEFP\Tests\Unit\Services\Domain;

use Brain\Monkey\Functions;
use WCEFP\Services\Domain\StockHoldManager;
use WCEFP\Tests\Unit\WCEFPTestCase;

class StockHoldManagerTest extends WCEFPTestCase
{
    private $holdManager;
    private $mockWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->holdManager = new StockHoldManager();
        $this->mockWpdb = $this->mockDatabase();
        
        // Mock WordPress functions
        Functions\when('current_time')->returnArg();
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('wp_create_nonce')->justReturn('test-nonce');
        
        // Mock database table names
        Functions\when('DatabaseManager::get_table_name')->returnUsing(function($table) {
            return "wp_wcefp_{$table}";
        });
    }

    public function testCreateHoldSuccessfully()
    {
        // Mock successful capacity check
        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/SELECT.*capacity/'))
            ->andReturn('10'); // Available capacity

        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/GET_LOCK/'))
            ->andReturn('1'); // Lock acquired

        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/RELEASE_LOCK/'))
            ->andReturn('1'); // Lock released

        $this->mockWpdb->shouldReceive('get_row')
            ->andReturn(null); // No existing hold

        $this->mockWpdb->shouldReceive('insert')
            ->andReturn(true);

        $this->mockWpdb->shouldReceive('query')
            ->andReturn(true);

        $this->mockWpdb->insert_id = 123;

        $result = $this->holdManager->create_hold(1, 'adult', 2, 'test-session');

        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['hold_id']);
        $this->assertArrayHasKey('expires_at', $result);
    }

    public function testCreateHoldFailsWhenInsufficientCapacity()
    {
        // Mock insufficient capacity
        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/SELECT.*capacity/'))
            ->andReturn('0'); // No capacity

        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/GET_LOCK/'))
            ->andReturn('1'); // Lock acquired

        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/RELEASE_LOCK/'))
            ->andReturn('1'); // Lock released

        $result = $this->holdManager->create_hold(1, 'adult', 5, 'test-session');

        $this->assertFalse($result['success']);
        $this->assertEquals('insufficient_capacity', $result['error']);
        $this->assertEquals(0, $result['available']);
    }

    public function testCreateHoldFailsWhenLockTimeout()
    {
        // Mock lock timeout
        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/GET_LOCK/'))
            ->andReturn('0'); // Lock timeout

        $result = $this->holdManager->create_hold(1, 'adult', 2, 'test-session');

        $this->assertFalse($result['success']);
        $this->assertEquals('lock_timeout', $result['error']);
    }

    public function testReleaseHoldSuccessfully()
    {
        // Mock existing hold
        $hold_details = (object) [
            'id' => 123,
            'occurrence_id' => 1,
            'ticket_key' => 'adult',
            'quantity' => 2,
            'expires_at' => date('Y-m-d H:i:s', time() + 900)
        ];

        $this->mockWpdb->shouldReceive('get_row')
            ->andReturn($hold_details);

        $this->mockWpdb->shouldReceive('delete')
            ->andReturn(1); // Successfully deleted

        $result = $this->holdManager->release_hold(123, 'test-session', 'manual');

        $this->assertTrue($result);
    }

    public function testConvertHoldsToBookingsSuccessfully()
    {
        // Mock holds for session
        $holds = [
            (object) [
                'id' => 123,
                'occurrence_id' => 1,
                'ticket_key' => 'adult',
                'quantity' => 2,
                'product_id' => 10
            ]
        ];

        $this->mockWpdb->shouldReceive('get_results')
            ->andReturn($holds);

        $this->mockWpdb->shouldReceive('insert')
            ->andReturn(true);

        $this->mockWpdb->shouldReceive('query')
            ->andReturn(true);

        $this->mockWpdb->shouldReceive('delete')
            ->andReturn(1);

        // Mock order item lookup
        Functions\when('wc_get_order')->returnUsing(function() {
            $mockOrder = \Mockery::mock();
            $mockItem = \Mockery::mock();
            $mockItem->shouldReceive('get_product_id')->andReturn(10);
            $mockOrder->shouldReceive('get_items')->andReturn([1 => $mockItem]);
            return $mockOrder;
        });

        $result = $this->holdManager->convert_holds_to_bookings('test-session', 456);

        $this->assertTrue($result);
    }

    public function testGetAvailableCapacityWithHolds()
    {
        // Mock occurrence data
        $occurrence = (object) [
            'capacity' => 10,
            'booked' => 5
        ];

        $this->mockWpdb->shouldReceive('get_row')
            ->andReturn($occurrence);

        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/ticket_capacity/'))
            ->andReturn(null); // No specific ticket capacity

        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/held_quantity/'))
            ->andReturn('2'); // 2 currently held

        $available = $this->holdManager->get_available_capacity(1, 'adult');

        $this->assertEquals(3, $available); // 10 - 5 - 2 = 3
    }

    public function testCleanupExpiredHolds()
    {
        $this->mockWpdb->shouldReceive('query')
            ->with(\Mockery::pattern('/DELETE.*expires_at <= NOW/'))
            ->andReturn(5); // 5 expired holds cleaned

        $result = $this->holdManager->cleanup_expired_holds();

        $this->assertEquals(5, $result);
    }

    public function testConcurrencyTestSimulation()
    {
        // Enable testing mode
        if (!defined('WCEFP_TESTING_MODE')) {
            define('WCEFP_TESTING_MODE', true);
        }

        // Mock initial capacity
        $this->mockWpdb->shouldReceive('get_var')
            ->andReturn('10'); // Available capacity

        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/GET_LOCK/'))
            ->andReturn('1');

        $this->mockWpdb->shouldReceive('get_var')
            ->with(\Mockery::pattern('/RELEASE_LOCK/'))
            ->andReturn('1');

        $this->mockWpdb->shouldReceive('get_row')
            ->andReturn(null);

        $this->mockWpdb->shouldReceive('insert')
            ->andReturn(true);

        $this->mockWpdb->shouldReceive('query')
            ->andReturn(true);

        $this->mockWpdb->insert_id = 123;

        $result = $this->holdManager->test_concurrency(1, 'adult', 2, 3);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('successful_holds', $result);
        $this->assertArrayHasKey('failed_holds', $result);
        $this->assertArrayHasKey('concurrency_test_passed', $result);
    }

    public function testGetHoldStatistics()
    {
        $this->mockWpdb->shouldReceive('get_var')
            ->andReturnUsing(function($query) {
                if (strpos($query, 'expires_at > NOW') !== false) {
                    return '15'; // Active holds
                } else {
                    return '5'; // Expired holds
                }
            });

        $this->mockWpdb->shouldReceive('get_results')
            ->andReturn([
                (object) ['session_id' => 'session1', 'hold_count' => 3, 'total_quantity' => 6],
                (object) ['session_id' => 'session2', 'hold_count' => 2, 'total_quantity' => 4]
            ]);

        $stats = $this->holdManager->get_hold_statistics();

        $this->assertIsArray($stats);
        $this->assertEquals(15, $stats['active_holds']);
        $this->assertEquals(5, $stats['expired_holds']);
        $this->assertArrayHasKey('holds_by_session', $stats);
        $this->assertArrayHasKey('holds_by_occurrence', $stats);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}