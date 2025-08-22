<?php
if (!defined('ABSPATH')) exit;

/**
 * Enhanced input validation and sanitization for WCEventsFP
 * 
 * @since 1.7.2
 */
class WCEFP_Validator {
    
    /**
     * Validate and sanitize product ID
     *
     * @param mixed $product_id
     * @return int|false
     */
    public static function validate_product_id($product_id) {
        $id = intval($product_id);
        
        if ($id <= 0) {
            WCEFP_Logger::warning('Invalid product ID provided', ['value' => $product_id]);
            return false;
        }
        
        $product = wc_get_product($id);
        if (!$product || !in_array($product->get_type(), ['wcefp_event', 'wcefp_experience'], true)) {
            WCEFP_Logger::warning('Product not found or invalid type', ['id' => $id]);
            return false;
        }
        
        return $id;
    }
    
    /**
     * Validate datetime format
     *
     * @param string $datetime
     * @param string $format Expected format (default: 'Y-m-d H:i:s')
     * @return string|false
     */
    public static function validate_datetime($datetime, $format = 'Y-m-d H:i:s') {
        $datetime = sanitize_text_field($datetime);
        
        if (empty($datetime)) {
            return false;
        }
        
        $d = DateTime::createFromFormat($format, $datetime);
        if ($d && $d->format($format) === $datetime) {
            return $datetime;
        }
        
        WCEFP_Logger::warning('Invalid datetime format', [
            'value' => $datetime,
            'expected_format' => $format
        ]);
        
        return false;
    }
    
    /**
     * Validate date format (Y-m-d)
     *
     * @param string $date
     * @return string|false
     */
    public static function validate_date($date) {
        return self::validate_datetime($date, 'Y-m-d');
    }
    
    /**
     * Validate capacity value
     *
     * @param mixed $capacity
     * @param int   $min_capacity Minimum allowed capacity
     * @param int   $max_capacity Maximum allowed capacity
     * @return int|false
     */
    public static function validate_capacity($capacity, $min_capacity = 0, $max_capacity = 1000) {
        $capacity = intval($capacity);
        
        if ($capacity < $min_capacity || $capacity > $max_capacity) {
            WCEFP_Logger::warning('Capacity out of range', [
                'value' => $capacity,
                'min' => $min_capacity,
                'max' => $max_capacity
            ]);
            return false;
        }
        
        return $capacity;
    }
    
    /**
     * Validate occurrence status
     *
     * @param string $status
     * @return string|false
     */
    public static function validate_status($status) {
        $valid_statuses = ['active', 'cancelled', 'full'];
        $status = sanitize_text_field($status);
        
        if (!in_array($status, $valid_statuses, true)) {
            WCEFP_Logger::warning('Invalid status provided', [
                'value' => $status,
                'valid_options' => $valid_statuses
            ]);
            return false;
        }
        
        return $status;
    }
    
    /**
     * Validate email address
     *
     * @param string $email
     * @return string|false
     */
    public static function validate_email($email) {
        $email = sanitize_email($email);
        
        if (!is_email($email)) {
            WCEFP_Logger::warning('Invalid email format', ['email' => $email]);
            return false;
        }
        
        return $email;
    }
    
    /**
     * Validate voucher code format
     *
     * @param string $code
     * @return string|false
     */
    public static function validate_voucher_code($code) {
        $code = strtoupper(sanitize_text_field($code));
        
        // Voucher code should be alphanumeric, 8-20 characters
        if (!preg_match('/^[A-Z0-9]{8,20}$/', $code)) {
            WCEFP_Logger::warning('Invalid voucher code format', ['code' => $code]);
            return false;
        }
        
        return $code;
    }
    
    /**
     * Validate quantity (adults/children)
     *
     * @param mixed $quantity
     * @param int   $max_quantity Maximum allowed quantity per booking
     * @return int|false
     */
    public static function validate_quantity($quantity, $max_quantity = 50) {
        $quantity = intval($quantity);
        
        if ($quantity < 0 || $quantity > $max_quantity) {
            WCEFP_Logger::warning('Invalid quantity', [
                'value' => $quantity,
                'max_allowed' => $max_quantity
            ]);
            return false;
        }
        
        return $quantity;
    }
    
    /**
     * Validate price value
     *
     * @param mixed $price
     * @return float|false
     */
    public static function validate_price($price) {
        $price = floatval($price);
        
        if ($price < 0 || $price > 99999.99) {
            WCEFP_Logger::warning('Invalid price value', ['price' => $price]);
            return false;
        }
        
        return round($price, 2);
    }
    
    /**
     * Validate and sanitize text field with length limit
     *
     * @param string $text
     * @param int    $max_length
     * @return string|false
     */
    public static function validate_text($text, $max_length = 255) {
        $text = sanitize_text_field($text);
        
        if (strlen($text) > $max_length) {
            WCEFP_Logger::warning('Text too long', [
                'length' => strlen($text),
                'max_length' => $max_length
            ]);
            return false;
        }
        
        return $text;
    }
    
    /**
     * Validate textarea content
     *
     * @param string $content
     * @param int    $max_length
     * @return string|false
     */
    public static function validate_textarea($content, $max_length = 5000) {
        $content = sanitize_textarea_field($content);
        
        if (strlen($content) > $max_length) {
            WCEFP_Logger::warning('Textarea content too long', [
                'length' => strlen($content),
                'max_length' => $max_length
            ]);
            return false;
        }
        
        return $content;
    }
    
    /**
     * Validate occurrence ID and check if it exists
     *
     * @param mixed $occurrence_id
     * @return int|false
     */
    public static function validate_occurrence_id($occurrence_id) {
        global $wpdb;
        
        $id = intval($occurrence_id);
        if ($id <= 0) {
            return false;
        }
        
        $table = $wpdb->prefix . 'wcefp_occurrences';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE id = %d",
            $id
        ));
        
        if (!$exists) {
            WCEFP_Logger::warning('Occurrence not found', ['id' => $id]);
            return false;
        }
        
        return $id;
    }
    
    /**
     * Bulk validation helper
     *
     * @param array $data       Array of data to validate
     * @param array $validators Array of validation rules
     * @return array|false      Validated data or false on failure
     */
    public static function validate_bulk($data, $validators) {
        $validated = [];
        
        foreach ($validators as $field => $config) {
            $value = $data[$field] ?? null;
            $method = $config['method'] ?? null;
            $required = $config['required'] ?? false;
            $args = $config['args'] ?? [];
            
            if ($required && ($value === null || $value === '')) {
                WCEFP_Logger::error("Required field missing: {$field}");
                return false;
            }
            
            if ($value !== null && $value !== '' && $method && method_exists(__CLASS__, $method)) {
                $validated_value = call_user_func_array([__CLASS__, $method], array_merge([$value], $args));
                if ($validated_value === false) {
                    WCEFP_Logger::error("Validation failed for field: {$field}");
                    return false;
                }
                $validated[$field] = $validated_value;
            } elseif ($value !== null) {
                $validated[$field] = $value;
            }
        }
        
        return $validated;
    }
}