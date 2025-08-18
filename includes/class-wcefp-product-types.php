<?php
if (!defined('ABSPATH')) exit;

/**
 * Dichiarazioni dei tipi prodotto personalizzati.
 * Caricato solo se WooCommerce è attivo.
 */
if (class_exists('WC_Product_Simple')) {

    if (!class_exists('WC_Product_WCEFP_Event')) {
        class WC_Product_WCEFP_Event extends WC_Product_Simple {
            public function get_type(){ return 'wcefp_event'; }
        }
    }

    if (!class_exists('WC_Product_WCEFP_Experience')) {
        class WC_Product_WCEFP_Experience extends WC_Product_Simple {
            public function get_type(){ return 'wcefp_experience'; }
        }
    }
}
