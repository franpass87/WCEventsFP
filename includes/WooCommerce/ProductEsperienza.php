<?php
/**
 * WooCommerce Product Esperienza Class
 * 
 * @package WCEFP
 * @subpackage WooCommerce
 * @since 2.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Product class for Esperienza (Experience) type
 */
class WC_Product_Esperienza extends WC_Product {
    
    /**
     * Product type
     * 
     * @var string
     */
    protected $product_type = 'esperienza';
    
    /**
     * Initialize product
     * 
     * @param mixed $product Product ID or object
     */
    public function __construct($product = 0) {
        $this->supports[] = 'ajax_add_to_cart';
        parent::__construct($product);
    }
    
    /**
     * Get product type
     * 
     * @return string
     */
    public function get_type() {
        return 'esperienza';
    }
    
    /**
     * Check if product is virtual
     * Virtual products don't need shipping
     * 
     * @param string $context Context of request
     * @return bool
     */
    public function get_virtual($context = 'view') {
        return true;
    }
    
    /**
     * Check if product is downloadable
     * 
     * @param string $context Context of request
     * @return bool
     */
    public function get_downloadable($context = 'view') {
        return false;
    }
    
    /**
     * Check if product needs shipping
     * 
     * @return bool
     */
    public function needs_shipping() {
        return false;
    }
    
    /**
     * Check if product is sold individually (no quantities)
     * 
     * @param string $context Context of request
     * @return bool
     */
    public function get_sold_individually($context = 'view') {
        return false;
    }
    
    /**
     * Check if product is purchasable
     * 
     * @return bool
     */
    public function is_purchasable() {
        return true;
    }
    
    /**
     * Get experience capacity
     * 
     * @return int
     */
    public function get_capacity() {
        return (int) $this->get_meta('_wcefp_capacity', true, 'edit');
    }
    
    /**
     * Get experience duration in minutes
     * 
     * @return int
     */
    public function get_duration() {
        return (int) $this->get_meta('_wcefp_duration', true, 'edit');
    }
    
    /**
     * Get adult price
     * 
     * @return float
     */
    public function get_adult_price() {
        return (float) $this->get_meta('_wcefp_price_adult', true, 'edit');
    }
    
    /**
     * Get child price
     * 
     * @return float
     */
    public function get_child_price() {
        return (float) $this->get_meta('_wcefp_price_child', true, 'edit');
    }
    
    /**
     * Get languages available
     * 
     * @return string
     */
    public function get_languages() {
        return $this->get_meta('_wcefp_languages', true, 'edit');
    }
    
    /**
     * Get meeting point
     * 
     * @return string
     */
    public function get_meeting_point() {
        return $this->get_meta('_wcefp_meeting_point', true, 'edit');
    }
    
    /**
     * Get what's included
     * 
     * @return string
     */
    public function get_included() {
        return $this->get_meta('_wcefp_included', true, 'edit');
    }
    
    /**
     * Get what's excluded
     * 
     * @return string
     */
    public function get_excluded() {
        return $this->get_meta('_wcefp_excluded', true, 'edit');
    }
    
    /**
     * Get cancellation policy
     * 
     * @return string
     */
    public function get_cancellation_policy() {
        return $this->get_meta('_wcefp_cancellation', true, 'edit');
    }
}