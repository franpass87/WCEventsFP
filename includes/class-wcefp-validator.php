<?php
/**
 * Validation Helper per WCEventsFP
 * Migliora sicurezza e consistenza validazione input
 *
 * @package WCEventsFP
 * @since 1.7.2
 */

if (!defined('ABSPATH')) exit;

class WCEFP_Validator {
    
    /**
     * Validate product ID
     */
    public static function product_id($value) {
        $id = intval($value);
        if ($id <= 0) {
            return false;
        }
        
        $product = wc_get_product($id);
        return $product !== false;
    }
    
    /**
     * Validate date format (Y-m-d)
     */
    public static function date($value) {
        if (empty($value)) {
            return false;
        }
        
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }
    
    /**
     * Validate datetime format (Y-m-d H:i:s)
     */
    public static function datetime($value) {
        if (empty($value)) {
            return false;
        }
        
        $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return $datetime && $datetime->format('Y-m-d H:i:s') === $value;
    }
    
    /**
     * Validate time format (H:i)
     */
    public static function time($value) {
        if (empty($value)) {
            return false;
        }
        
        $time = DateTime::createFromFormat('H:i', $value);
        return $time && $time->format('H:i') === $value;
    }
    
    /**
     * Validate capacity (positive integer)
     */
    public static function capacity($value) {
        $capacity = intval($value);
        return $capacity > 0;
    }
    
    /**
     * Validate price (positive float)
     */
    public static function price($value) {
        $price = floatval($value);
        return $price >= 0;
    }
    
    /**
     * Validate email address
     */
    public static function email($value) {
        return is_email($value);
    }
    
    /**
     * Validate gift message length
     */
    public static function gift_message($value) {
        return strlen($value) <= WCEFP_Config::MAX_GIFT_MESSAGE_LENGTH;
    }
    
    /**
     * Validate occurrence ID
     */
    public static function occurrence_id($value) {
        $id = intval($value);
        if ($id <= 0) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'wcefp_occurrences';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE id = %d",
            $id
        ));
        
        return intval($exists) > 0;
    }
    
    /**
     * Validate voucher code format
     */
    public static function voucher_code($value) {
        // Alphanumeric, dashes, 8-50 characters
        return preg_match('/^[A-Z0-9\-]{8,50}$/', $value);
    }
    
    /**
     * Sanitize and validate rich text content
     */
    public static function rich_text($value) {
        return wp_kses($value, WCEFP_Config::allowed_html_tags());
    }
    
    /**
     * Validate multiple values using a callback
     */
    public static function array_values(array $values, callable $validator) {
        foreach ($values as $value) {
            if (!$validator($value)) {
                return false;
            }
        }
        return true;
    }
}