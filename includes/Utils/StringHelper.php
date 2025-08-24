<?php
/**
 * String Helper Utilities
 * 
 * Safe string functions to prevent PHP 8.1+ deprecation warnings
 * when null or non-string values are passed to string functions.
 *
 * @package WCEFP\Utils
 * @since 2.2.1
 */

namespace WCEFP\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * String Helper class for safe string operations
 */
class StringHelper {
    
    /**
     * Safely convert value to string
     * 
     * @param mixed $value Value to convert to string
     * @return string Safe string representation
     */
    public static function safe_str($value): string {
        if (is_string($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return (string) $value;
        }
        
        return '';
    }
    
    /**
     * Safe strlen implementation
     * 
     * @param mixed $value Value to get length of
     * @return int String length
     */
    public static function safe_strlen($value): int {
        return strlen(self::safe_str($value));
    }
    
    /**
     * Safe trim implementation
     * 
     * @param mixed $value Value to trim
     * @param string $characters Characters to trim
     * @return string Trimmed string
     */
    public static function safe_trim($value, $characters = " \t\n\r\0\x0B"): string {
        return trim(self::safe_str($value), $characters);
    }
    
    /**
     * Safe ltrim implementation
     * 
     * @param mixed $value Value to left trim
     * @param string $characters Characters to trim
     * @return string Left-trimmed string
     */
    public static function safe_ltrim($value, $characters = " \t\n\r\0\x0B"): string {
        return ltrim(self::safe_str($value), $characters);
    }
    
    /**
     * Safe rtrim implementation
     * 
     * @param mixed $value Value to right trim
     * @param string $characters Characters to trim
     * @return string Right-trimmed string
     */
    public static function safe_rtrim($value, $characters = " \t\n\r\0\x0B"): string {
        return rtrim(self::safe_str($value), $characters);
    }
    
    /**
     * Safe strpos implementation
     * 
     * @param mixed $haystack String to search in
     * @param mixed $needle String to search for
     * @param int $offset Starting position
     * @return int|false Position of needle or false if not found
     */
    public static function safe_strpos($haystack, $needle, $offset = 0) {
        return strpos(self::safe_str($haystack), self::safe_str($needle), $offset);
    }
    
    /**
     * Safe stripos implementation
     * 
     * @param mixed $haystack String to search in
     * @param mixed $needle String to search for
     * @param int $offset Starting position
     * @return int|false Position of needle or false if not found
     */
    public static function safe_stripos($haystack, $needle, $offset = 0) {
        return stripos(self::safe_str($haystack), self::safe_str($needle), $offset);
    }
    
    /**
     * Safe strtolower implementation
     * 
     * @param mixed $value Value to convert to lowercase
     * @return string Lowercase string
     */
    public static function safe_strtolower($value): string {
        return strtolower(self::safe_str($value));
    }
    
    /**
     * Safe strtoupper implementation
     * 
     * @param mixed $value Value to convert to uppercase
     * @return string Uppercase string
     */
    public static function safe_strtoupper($value): string {
        return strtoupper(self::safe_str($value));
    }
    
    /**
     * Safe preg_match implementation
     * 
     * @param string $pattern Regular expression pattern
     * @param mixed $subject Subject to search in
     * @param array|null $matches Array to store matches
     * @param int $flags Optional flags
     * @param int $offset Starting offset
     * @return int|false Number of matches or false on error
     */
    public static function safe_preg_match($pattern, $subject, &$matches = null, $flags = 0, $offset = 0) {
        return preg_match($pattern, self::safe_str($subject), $matches, $flags, $offset);
    }
    
    /**
     * Safe preg_replace implementation
     * 
     * @param string|array $pattern Regular expression pattern(s)
     * @param string|array $replacement Replacement string(s)
     * @param mixed $subject Subject to replace in
     * @param int $limit Maximum replacements
     * @param int|null $count Variable to store replacement count
     * @return string|null Replaced string
     */
    public static function safe_preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null) {
        return preg_replace($pattern, $replacement, self::safe_str($subject), $limit, $count);
    }
}

// Global helper functions for backward compatibility and ease of use
if (!function_exists('wcefp_safe_str')) {
    /**
     * Global helper function for safe string conversion
     * 
     * @param mixed $value Value to convert
     * @return string Safe string
     */
    function wcefp_safe_str($value): string {
        return \WCEFP\Utils\StringHelper::safe_str($value);
    }
}

if (!function_exists('wcefp_safe_strlen')) {
    /**
     * Global helper function for safe strlen
     * 
     * @param mixed $value Value to get length of
     * @return int String length
     */
    function wcefp_safe_strlen($value): int {
        return \WCEFP\Utils\StringHelper::safe_strlen($value);
    }
}

if (!function_exists('wcefp_safe_strtoupper')) {
    /**
     * Global helper function for safe strtoupper
     * 
     * @param mixed $value Value to convert to uppercase
     * @return string Uppercase string
     */
    function wcefp_safe_strtoupper($value): string {
        return \WCEFP\Utils\StringHelper::safe_strtoupper($value);
    }
}

if (!function_exists('wcefp_safe_strtolower')) {
    /**
     * Global helper function for safe strtolower
     * 
     * @param mixed $value Value to convert to lowercase
     * @return string Lowercase string
     */
    function wcefp_safe_strtolower($value): string {
        return \WCEFP\Utils\StringHelper::safe_strtolower($value);
    }
}

if (!function_exists('wcefp_safe_strpos')) {
    /**
     * Global helper function for safe strpos
     * 
     * @param mixed $haystack String to search in
     * @param mixed $needle String to search for
     * @param int $offset Starting position
     * @return int|false Position or false if not found
     */
    function wcefp_safe_strpos($haystack, $needle, $offset = 0) {
        return \WCEFP\Utils\StringHelper::safe_strpos($haystack, $needle, $offset);
    }
}

if (!function_exists('wcefp_safe_trim')) {
    /**
     * Global helper function for safe trim
     * 
     * @param mixed $value Value to trim
     * @param string $characters Characters to trim
     * @return string Trimmed string
     */
    function wcefp_safe_trim($value, $characters = " \t\n\r\0\x0B"): string {
        return \WCEFP\Utils\StringHelper::safe_trim($value, $characters);
    }
}

if (!function_exists('wcefp_safe_preg_match')) {
    /**
     * Global helper function for safe preg_match
     * 
     * @param string $pattern Regular expression pattern
     * @param mixed $subject Subject to search in
     * @param array|null $matches Array to store matches
     * @param int $flags Optional flags
     * @param int $offset Starting offset
     * @return int|false Number of matches or false on error
     */
    function wcefp_safe_preg_match($pattern, $subject, &$matches = null, $flags = 0, $offset = 0) {
        return \WCEFP\Utils\StringHelper::safe_preg_match($pattern, $subject, $matches, $flags, $offset);
    }
}