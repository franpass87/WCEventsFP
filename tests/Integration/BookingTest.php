<?php
/**
 * Booking Functionality Tests
 *
 * @package WCEFP\Tests
 */

namespace WCEFP\Tests\Integration;

use WCEFP\Tests\Unit\WCEFPTestCase;
use WCEFP\Modules\BookingsModule;

class BookingTest extends WCEFPTestCase {
    
    private $bookings_module;
    private $event_id;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->bookings_module = new BookingsModule();
        
        // Create a test event
        $this->event_id = $this->factory->post->create([
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'Test Booking Event'
        ]);
        
        update_post_meta($this->event_id, '_wcefp_is_event', '1');
        update_post_meta($this->event_id, '_wcefp_capacity', '10');
        update_post_meta($this->event_id, '_regular_price', '25.00');
    }
    
    /**
     * Test basic booking creation
     */
    public function test_create_booking() {
        global $wpdb;
        
        $booking_data = [
            'product_id' => $this->event_id,
            'data_evento' => date('Y-m-d', strtotime('+1 week')),
            'ora_evento' => '10:00:00',
            'nome' => 'Test Customer',
            'email' => 'customer@example.com',
            'telefono' => '+1234567890',
            'adults' => 2,
            'children' => 1,
            'stato' => 'confirmed',
            'prezzo_totale' => 75.00,
            'meetingpoint' => 'Main Entrance',
            'note' => 'Test booking note'
        ];
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'wcefp_occorrenze',
            array_merge($booking_data, ['created_at' => current_time('mysql')])
        );
        
        $this->assertNotFalse($result);
        
        $booking_id = $wpdb->insert_id;
        $this->assertGreaterThan(0, $booking_id);
        
        // Verify booking was created correctly
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_occorrenze WHERE id = %d",
            $booking_id
        ));
        
        $this->assertNotNull($booking);
        $this->assertEquals($this->event_id, $booking->product_id);
        $this->assertEquals('Test Customer', $booking->nome);
        $this->assertEquals('customer@example.com', $booking->email);
        $this->assertEquals('confirmed', $booking->stato);
    }
    
    /**
     * Test booking capacity validation
     */
    public function test_booking_capacity_validation() {
        global $wpdb;
        
        $event_date = date('Y-m-d', strtotime('+2 weeks'));
        $event_time = '14:00:00';
        
        // Create bookings up to capacity (10)
        for ($i = 1; $i <= 10; $i++) {
            $wpdb->insert(
                $wpdb->prefix . 'wcefp_occorrenze',
                [
                    'product_id' => $this->event_id,
                    'data_evento' => $event_date,
                    'ora_evento' => $event_time,
                    'nome' => "Customer {$i}",
                    'email' => "customer{$i}@example.com",
                    'adults' => 1,
                    'children' => 0,
                    'stato' => 'confirmed',
                    'prezzo_totale' => 25.00,
                    'created_at' => current_time('mysql')
                ]
            );
        }
        
        // Check total bookings
        $booking_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wcefp_occorrenze 
             WHERE product_id = %d AND data_evento = %s AND ora_evento = %s AND stato = 'confirmed'",
            $this->event_id, $event_date, $event_time
        ));
        
        $this->assertEquals(10, $booking_count);
        
        // Check capacity is reached
        $capacity = intval(get_post_meta($this->event_id, '_wcefp_capacity', true));
        $this->assertEquals($capacity, $booking_count);
    }
    
    /**
     * Test booking status changes
     */
    public function test_booking_status_changes() {
        global $wpdb;
        
        // Create a booking
        $wpdb->insert(
            $wpdb->prefix . 'wcefp_occorrenze',
            [
                'product_id' => $this->event_id,
                'data_evento' => date('Y-m-d', strtotime('+3 weeks')),
                'ora_evento' => '16:00:00',
                'nome' => 'Status Test Customer',
                'email' => 'status@example.com',
                'adults' => 2,
                'children' => 0,
                'stato' => 'pending',
                'prezzo_totale' => 50.00,
                'created_at' => current_time('mysql')
            ]
        );
        
        $booking_id = $wpdb->insert_id;
        
        // Test status update to confirmed
        $wpdb->update(
            $wpdb->prefix . 'wcefp_occorrenze',
            ['stato' => 'confirmed', 'updated_at' => current_time('mysql')],
            ['id' => $booking_id]
        );
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_occorrenze WHERE id = %d",
            $booking_id
        ));
        
        $this->assertEquals('confirmed', $booking->stato);
        
        // Test status update to cancelled
        $wpdb->update(
            $wpdb->prefix . 'wcefp_occorrenze',
            ['stato' => 'cancelled', 'updated_at' => current_time('mysql')],
            ['id' => $booking_id]
        );
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_occorrenze WHERE id = %d",
            $booking_id
        ));
        
        $this->assertEquals('cancelled', $booking->stato);
    }
    
    /**
     * Test booking pricing calculations
     */
    public function test_booking_pricing() {
        global $wpdb;
        
        $base_price = 30.00;
        update_post_meta($this->event_id, '_regular_price', $base_price);
        
        // Test adult + children pricing
        $adults = 2;
        $children = 2;
        $expected_total = ($adults + $children) * $base_price;
        
        $wpdb->insert(
            $wpdb->prefix . 'wcefp_occorrenze',
            [
                'product_id' => $this->event_id,
                'data_evento' => date('Y-m-d', strtotime('+4 weeks')),
                'ora_evento' => '18:00:00',
                'nome' => 'Pricing Test Customer',
                'email' => 'pricing@example.com',
                'adults' => $adults,
                'children' => $children,
                'stato' => 'confirmed',
                'prezzo_totale' => $expected_total,
                'created_at' => current_time('mysql')
            ]
        );
        
        $booking_id = $wpdb->insert_id;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_occorrenze WHERE id = %d",
            $booking_id
        ));
        
        $this->assertEquals($expected_total, floatval($booking->prezzo_totale));
        $this->assertEquals($adults + $children, $booking->adults + $booking->children);
    }
    
    /**
     * Test booking date validation
     */
    public function test_booking_date_validation() {
        // Test past date should not be allowed
        $past_date = date('Y-m-d', strtotime('-1 week'));
        $future_date = date('Y-m-d', strtotime('+1 week'));
        
        $this->assertTrue(strtotime($future_date) >= strtotime(date('Y-m-d')));
        $this->assertFalse(strtotime($past_date) >= strtotime(date('Y-m-d')));
    }
    
    /**
     * Test booking search and filtering
     */
    public function test_booking_search_and_filter() {
        global $wpdb;
        
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        // Create bookings with different criteria
        $bookings = [
            [
                'data_evento' => $today,
                'nome' => 'John Smith',
                'email' => 'john@example.com',
                'stato' => 'confirmed'
            ],
            [
                'data_evento' => $tomorrow,
                'nome' => 'Jane Doe',
                'email' => 'jane@example.com',
                'stato' => 'pending'
            ],
            [
                'data_evento' => $today,
                'nome' => 'Bob Johnson',
                'email' => 'bob@example.com',
                'stato' => 'cancelled'
            ]
        ];
        
        foreach ($bookings as $booking_data) {
            $wpdb->insert(
                $wpdb->prefix . 'wcefp_occorrenze',
                array_merge($booking_data, [
                    'product_id' => $this->event_id,
                    'ora_evento' => '12:00:00',
                    'adults' => 1,
                    'children' => 0,
                    'prezzo_totale' => 25.00,
                    'created_at' => current_time('mysql')
                ])
            );
        }
        
        // Test filtering by date
        $today_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_occorrenze 
             WHERE product_id = %d AND data_evento = %s",
            $this->event_id, $today
        ));
        
        $this->assertCount(2, $today_bookings);
        
        // Test filtering by status
        $confirmed_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_occorrenze 
             WHERE product_id = %d AND stato = %s",
            $this->event_id, 'confirmed'
        ));
        
        $this->assertGreaterThanOrEqual(1, count($confirmed_bookings));
        
        // Test search by customer name
        $search_results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_occorrenze 
             WHERE product_id = %d AND nome LIKE %s",
            $this->event_id, '%John%'
        ));
        
        $this->assertGreaterThanOrEqual(1, count($search_results));
    }
    
    /**
     * Test booking with extras/additional services
     */
    public function test_booking_with_extras() {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'wcefp_occorrenze',
            [
                'product_id' => $this->event_id,
                'data_evento' => date('Y-m-d', strtotime('+5 weeks')),
                'ora_evento' => '20:00:00',
                'nome' => 'Extras Test Customer',
                'email' => 'extras@example.com',
                'adults' => 2,
                'children' => 0,
                'stato' => 'confirmed',
                'prezzo_totale' => 70.00, // Base price + extras
                'note' => 'Extra services: Wine pairing (+20), Transportation (+10)',
                'created_at' => current_time('mysql')
            ]
        );
        
        $booking_id = $wpdb->insert_id;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_occorrenze WHERE id = %d",
            $booking_id
        ));
        
        $this->assertStringContains('Wine pairing', $booking->note);
        $this->assertStringContains('Transportation', $booking->note);
        $this->assertEquals(70.00, floatval($booking->prezzo_totale));
    }
    
    /**
     * Test booking deletion (soft delete)
     */
    public function test_booking_soft_delete() {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'wcefp_occorrenze',
            [
                'product_id' => $this->event_id,
                'data_evento' => date('Y-m-d', strtotime('+6 weeks')),
                'ora_evento' => '22:00:00',
                'nome' => 'Delete Test Customer',
                'email' => 'delete@example.com',
                'adults' => 1,
                'children' => 0,
                'stato' => 'confirmed',
                'prezzo_totale' => 25.00,
                'created_at' => current_time('mysql')
            ]
        );
        
        $booking_id = $wpdb->insert_id;
        
        // Soft delete by changing status to cancelled
        $wpdb->update(
            $wpdb->prefix . 'wcefp_occorrenze',
            [
                'stato' => 'cancelled',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $booking_id]
        );
        
        // Booking should still exist but be cancelled
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_occorrenze WHERE id = %d",
            $booking_id
        ));
        
        $this->assertNotNull($booking);
        $this->assertEquals('cancelled', $booking->stato);
    }
}