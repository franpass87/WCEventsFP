<?php
/**
 * String Safety Functions
 * 
 * Provides PHP 8.1 compatible string functions that handle null values safely
 * to prevent deprecation warnings.
 * 
 * @package WCEFP
 * @subpackage Support
 * @since 2.2.0
 */

namespace WCEFP\Support;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Safe string conversion that handles null values
 * 
 * @param mixed $value Value to convert to string
 * @return string Safe string value
 */
function safe_str($value): string {
    if ($value === null) {
        return '';
    }
    
    if (is_scalar($value)) {
        return (string) $value;
    }
    
    if (is_object($value) && method_exists($value, '__toString')) {
        return (string) $value;
    }
    
    // For arrays, objects without __toString, etc.
    return '';
}

/**
 * Safe str_replace that handles null subject values
 * 
 * @param string|array $search String or array of strings to search for
 * @param string|array $replace String or array of replacement strings
 * @param string|array|null $subject Subject to perform replacement on
 * @param int|null $count Reference to count of replacements performed
 * @return string|array Safe replacement result
 */
function safe_str_replace($search, $replace, $subject, ?int &$count = null) {
    // Handle null subject
    if ($subject === null) {
        if ($count !== null) {
            $count = 0;
        }
        return '';
    }
    
    // Handle array subjects
    if (is_array($subject)) {
        $result = [];
        $total_count = 0;
        
        foreach ($subject as $key => $value) {
            $item_count = 0;
            $result[$key] = safe_str_replace($search, $replace, $value, $item_count);
            $total_count += $item_count;
        }
        
        if ($count !== null) {
            $count = $total_count;
        }
        
        return $result;
    }
    
    // Convert subject to string safely
    $safe_subject = safe_str($subject);
    
    // Perform replacement
    return str_replace($search, $replace, $safe_subject, $count);
}

/**
 * Check if the global functions are not already defined and define them
 * These provide convenient global access to the namespaced functions
 */
if (!function_exists('wcefp_safe_str')) {
    /**
     * Global helper function for safe string conversion
     * 
     * @param mixed $value Value to convert to string
     * @return string Safe string value
     */
    function wcefp_safe_str($value): string {
        return \WCEFP\Support\safe_str($value);
    }
}

if (!function_exists('wcefp_safe_str_replace')) {
    /**
     * Global helper function for safe str_replace
     * 
     * @param string|array $search String or array of strings to search for
     * @param string|array $replace String or array of replacement strings
     * @param string|array|null $subject Subject to perform replacement on
     * @param int|null $count Reference to count of replacements performed
     * @return string|array Safe replacement result
     */
    function wcefp_safe_str_replace($search, $replace, $subject, ?int &$count = null) {
        return \WCEFP\Support\safe_str_replace($search, $replace, $subject, $count);
    }
}