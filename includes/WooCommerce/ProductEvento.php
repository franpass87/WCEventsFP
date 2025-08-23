<?php
/**
 * WooCommerce Event Product Type
 * 
 * @package WCEFP
 * @subpackage WooCommerce
 * @since 2.1.3
 */

namespace WCEFP\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Event product class extending BaseProduct
 */
class ProductEvento extends BaseProduct {
    
    /**
     * Get product type
     * 
     * @return string
     */
    public function get_type() {
        return 'evento';
    }
    
    /**
     * Event products are virtual by default
     * 
     * @param string $context Context
     * @return bool
     */
    public function get_virtual($context = 'view') {
        return true;
    }
    
    /**
     * Event products don't need shipping
     * 
     * @return bool
     */
    public function needs_shipping() {
        return false;
    }
    
    /**
     * Event products are purchasable
     * 
     * @return bool
     */
    public function is_purchasable() {
        return true;
    }
    
    /**
     * Check if event supports booking
     * 
     * @return bool
     */
    public function supports_booking() {
        return true;
    }
    
    /**
     * Get event-specific metadata
     * 
     * @return array
     */
    public function get_event_settings() {
        $product_id = $this->get_id();
        
        return [
            'event_type' => 'evento',
            'capacity' => get_post_meta($product_id, '_wcefp_capacity', true),
            'recurring' => get_post_meta($product_id, '_wcefp_recurring', true) === 'yes',
            'advance_booking_days' => get_post_meta($product_id, '_wcefp_advance_booking_days', true),
            'cancellation_policy' => get_post_meta($product_id, '_wcefp_cancellation_policy', true)
        ];
    }
}

// Register the class with WooCommerce if WC is active
if (class_exists('WooCommerce')) {
    /**
     * Legacy WC_Product wrapper for compatibility
     */
    if (!class_exists('WC_Product_Evento')) {
        class WC_Product_Evento extends ProductEvento {
            // Provides legacy compatibility
        }
    }
}