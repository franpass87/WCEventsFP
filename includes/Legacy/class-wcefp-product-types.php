<?php
/**
 * WCEventsFP Custom Product Types
 * 
 * @package WCEFP
 * @since 1.0.0
 * @version 2.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Product Type Declarations
 * Loaded only if WooCommerce is active
 */
if (class_exists('WC_Product_Simple')) {

    if (!class_exists('WC_Product_WCEFP_Event')) {
        /**
         * WooCommerce Event Product Class
         * 
         * @since 1.0.0
         */
        class WC_Product_WCEFP_Event extends WC_Product_Simple {
            
            /**
             * Get product type
             * 
             * @return string
             */
            public function get_type() {
                return 'wcefp_event';
            }
            
            /**
             * Event products are virtual (no shipping needed)
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
        }
    }

    if (!class_exists('WC_Product_WCEFP_Experience')) {
        /**
         * WooCommerce Experience Product Class
         * 
         * @since 1.0.0
         */
        class WC_Product_WCEFP_Experience extends WC_Product_Simple {
            
            /**
             * Get product type
             * 
             * @return string
             */
            public function get_type() {
                return 'wcefp_experience';
            }
            
            /**
             * Experience products are virtual (no shipping needed)
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
        }
    }
}
