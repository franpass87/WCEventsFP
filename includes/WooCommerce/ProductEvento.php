<?php
/**
 * WooCommerce Product Evento Class
 * 
 * @package WCEFP
 * @subpackage WooCommerce
 * @since 2.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Product class for Evento (Event) type
 */
class WC_Product_Evento extends WC_Product {
    
    /**
     * Product type
     * 
     * @var string
     */
    protected $product_type = 'evento';
    
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
        return 'evento';
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
     * Get event capacity
     * 
     * @return int
     */
    public function get_capacity() {
        return (int) $this->get_meta('_wcefp_capacity', true, 'edit');
    }
    
    /**
     * Get event duration in minutes
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
}