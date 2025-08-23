<?php
/**
 * WooCommerce Experience Product Type
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
 * Experience product class extending BaseProduct
 */
class ProductEsperienza extends BaseProduct {
    
    /**
     * Get product type
     * 
     * @return string
     */
    public function get_type() {
        return 'esperienza';
    }
    
    /**
     * Experience products are virtual by default
     * 
     * @param string $context Context
     * @return bool
     */
    public function get_virtual($context = 'view') {
        return true;
    }
    
    /**
     * Experience products don't need shipping
     * 
     * @return bool
     */
    public function needs_shipping() {
        return false;
    }
    
    /**
     * Experience products are purchasable
     * 
     * @return bool
     */
    public function is_purchasable() {
        return true;
    }
    
    /**
     * Check if experience supports booking
     * 
     * @return bool
     */
    public function supports_booking() {
        return true;
    }
    
    /**
     * Get experience-specific metadata
     * 
     * @return array
     */
    public function get_experience_settings() {
        $product_id = $this->get_id();
        
        return [
            'experience_type' => 'esperienza',
            'duration' => get_post_meta($product_id, '_wcefp_duration', true),
            'guide_required' => get_post_meta($product_id, '_wcefp_guide_required', true) === 'yes',
            'equipment_included' => get_post_meta($product_id, '_wcefp_equipment_included', true) === 'yes',
            'difficulty_level' => get_post_meta($product_id, '_wcefp_difficulty_level', true),
            'min_age' => get_post_meta($product_id, '_wcefp_min_age', true)
        ];
    }
    
    /**
     * Check if experience has flexible pricing
     * 
     * @return bool
     */
    public function has_flexible_pricing() {
        return get_post_meta($this->get_id(), '_wcefp_flexible_pricing', true) === 'yes';
    }
}

// Register the class with WooCommerce if WC is active
if (class_exists('WooCommerce')) {
    /**
     * Legacy WC_Product wrapper for compatibility
     */
    if (!class_exists('WC_Product_Esperienza')) {
        class WC_Product_Esperienza extends ProductEsperienza {
            // Provides legacy compatibility
        }
    }
}