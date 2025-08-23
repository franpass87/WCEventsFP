<?php
/**
 * Base class for WCEventsFP custom WooCommerce products
 * 
 * @package WCEFP
 * @subpackage WooCommerce
 * @since 2.1.1
 */

namespace WCEFP\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base product class for Events and Experiences
 * 
 * Provides shared functionality for booking-based products.
 * Eliminates duplication between ProductEvento and ProductEsperienza.
 */
abstract class BaseProduct extends \WC_Product {
    
    /**
     * Get experience/event specific data
     * 
     * @return array<string, mixed> Experience data including bookings, availability, etc.
     */
    public function get_experience_data(): array {
        $product_id = $this->get_id();
        
        // Get product meta data
        $experience_data = [
            'type' => $this->get_type(),
            'duration' => get_post_meta($product_id, '_wcefp_duration', true),
            'max_participants' => get_post_meta($product_id, '_wcefp_max_participants', true),
            'location' => get_post_meta($product_id, '_wcefp_location', true),
            'meeting_point' => get_post_meta($product_id, '_wcefp_meeting_point', true),
            'included_services' => get_post_meta($product_id, '_wcefp_included_services', true),
            'excluded_services' => get_post_meta($product_id, '_wcefp_excluded_services', true),
            'requirements' => get_post_meta($product_id, '_wcefp_requirements', true),
            'cancellation_policy' => get_post_meta($product_id, '_wcefp_cancellation_policy', true),
            'booking_settings' => [
                'advance_booking_days' => get_post_meta($product_id, '_wcefp_advance_booking_days', true),
                'min_participants' => get_post_meta($product_id, '_wcefp_min_participants', true),
                'instant_booking' => get_post_meta($product_id, '_wcefp_instant_booking', true) === 'yes',
                'flexible_dates' => get_post_meta($product_id, '_wcefp_flexible_dates', true) === 'yes'
            ]
        ];
        
        return apply_filters('wcefp_experience_data', $experience_data, $product_id, $this);
    }
    
    /**
     * Validate booking data for this product
     * 
     * @param array<string, mixed> $booking_data Booking data to validate
     * @return bool|\WP_Error True if valid, WP_Error if invalid
     */
    public function validate_booking_data(array $booking_data) {
        $errors = new \WP_Error();
        
        // Validate required fields
        if (empty($booking_data['date'])) {
            $errors->add('missing_date', __('Booking date is required.', 'wceventsfp'));
        }
        
        if (empty($booking_data['time'])) {
            $errors->add('missing_time', __('Booking time is required.', 'wceventsfp'));
        }
        
        if (empty($booking_data['participants']) || !is_numeric($booking_data['participants'])) {
            $errors->add('invalid_participants', __('Valid number of participants is required.', 'wceventsfp'));
        }
        
        // Validate participant count
        $max_participants = get_post_meta($this->get_id(), '_wcefp_max_participants', true);
        if ($max_participants && $booking_data['participants'] > $max_participants) {
            $errors->add('too_many_participants', 
                sprintf(__('Maximum %d participants allowed.', 'wceventsfp'), $max_participants)
            );
        }
        
        $min_participants = get_post_meta($this->get_id(), '_wcefp_min_participants', true);
        if ($min_participants && $booking_data['participants'] < $min_participants) {
            $errors->add('too_few_participants',
                sprintf(__('Minimum %d participants required.', 'wceventsfp'), $min_participants)
            );
        }
        
        // Validate date
        if (!empty($booking_data['date'])) {
            $booking_date = strtotime($booking_data['date']);
            $advance_days = get_post_meta($this->get_id(), '_wcefp_advance_booking_days', true);
            
            if ($advance_days && $booking_date < (time() + ($advance_days * DAY_IN_SECONDS))) {
                $errors->add('advance_booking_required',
                    sprintf(__('Booking must be made at least %d days in advance.', 'wceventsfp'), $advance_days)
                );
            }
        }
        
        // Allow other plugins to add validation
        $errors = apply_filters('wcefp_validate_booking_data', $errors, $booking_data, $this);
        
        return $errors->has_errors() ? $errors : true;
    }
    
    /**
     * Get availability for a specific date/time
     * 
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i format
     * @return array Availability data
     */
    public function get_availability($date = '', $time = '') {
        $product_id = $this->get_id();
        
        // Default availability structure
        $availability = [
            'available' => false,
            'spots_remaining' => 0,
            'total_spots' => get_post_meta($product_id, '_wcefp_max_participants', true) ?: 0,
            'next_available' => null,
            'pricing' => []
        ];
        
        if (empty($date)) {
            $date = current_time('Y-m-d');
        }
        
        // Check if date is available
        $occurrences = $this->get_occurrences($date);
        if (!empty($occurrences)) {
            $occurrence = reset($occurrences);
            $availability['available'] = true;
            $availability['spots_remaining'] = max(0, $availability['total_spots'] - $occurrence['booked']);
            $availability['pricing'] = $occurrence['pricing'] ?? [];
        }
        
        return apply_filters('wcefp_product_availability', $availability, $date, $time, $this);
    }
    
    /**
     * Get occurrences for a specific date or date range
     * 
     * @param string $date Date in Y-m-d format
     * @param string $end_date Optional end date for range
     * @return array Array of occurrences
     */
    public function get_occurrences($date = '', $end_date = '') {
        $product_id = $this->get_id();
        
        // This would typically query a custom table or post meta
        // For now, return a basic structure
        $occurrences = [];
        
        // Get occurrences from database or generate them
        $stored_occurrences = get_post_meta($product_id, '_wcefp_occurrences', true);
        if (is_array($stored_occurrences)) {
            foreach ($stored_occurrences as $occurrence) {
                if (empty($date) || $occurrence['date'] === $date || 
                    (!empty($end_date) && $occurrence['date'] >= $date && $occurrence['date'] <= $end_date)) {
                    $occurrences[] = $occurrence;
                }
            }
        }
        
        return apply_filters('wcefp_product_occurrences', $occurrences, $date, $end_date, $this);
    }
    
    // Common WooCommerce overrides for booking products
    
    /**
     * Booking products are not downloadable
     * 
     * @param string $context View context
     * @return bool Always false
     */
    public function get_downloadable($context = 'view') {
        return false;
    }
    
    /**
     * Booking products don't need shipping
     * 
     * @return bool Always false
     */
    public function needs_shipping() {
        return false;
    }
    
    /**
     * Booking products can be purchased multiple times
     * 
     * @param string $context View context
     * @return bool Always false
     */
    public function get_sold_individually($context = 'view') {
        return false;
    }
    
    /**
     * Booking products are always virtual
     * 
     * @param string $context View context
     * @return bool Always true
     */
    public function get_virtual($context = 'view') {
        return true;
    }
}