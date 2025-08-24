<?php
/**
 * PHP 8.1+ Compatibility Helper
 * 
 * Provides safe string functions and handles deprecated API usage.
 * 
 * @package WCEFP\Utils
 * @since 2.1.4
 */

namespace WCEFP\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PHP Compatibility Helper class
 */
class CompatibilityHelper {
    
    /**
     * Safe strlen() that handles null values
     * 
     * @param string|null $string
     * @return int
     */
    public static function safe_strlen($string): int {
        return $string !== null ? strlen($string) : 0;
    }
    
    /**
     * Safe trim() that handles null values
     * 
     * @param string|null $string
     * @param string $characters
     * @return string
     */
    public static function safe_trim($string, string $characters = " \t\n\r\0\x0B"): string {
        return $string !== null ? trim($string, $characters) : '';
    }
    
    /**
     * Safe strpos() that handles null values
     * 
     * @param string|null $haystack
     * @param string|null $needle
     * @param int $offset
     * @return int|false
     */
    public static function safe_strpos($haystack, $needle, int $offset = 0) {
        if ($haystack === null || $needle === null) {
            return false;
        }
        return strpos($haystack, $needle, $offset);
    }
    
    /**
     * Safe substr() that handles null values
     * 
     * @param string|null $string
     * @param int $start
     * @param int|null $length
     * @return string
     */
    public static function safe_substr($string, int $start, ?int $length = null): string {
        if ($string === null) {
            return '';
        }
        return $length !== null ? substr($string, $start, $length) : substr($string, $start);
    }
    
    /**
     * Safe explode() that handles null values
     * 
     * @param string $delimiter
     * @param string|null $string
     * @param int $limit
     * @return array
     */
    public static function safe_explode(string $delimiter, $string, int $limit = PHP_INT_MAX): array {
        if ($string === null) {
            return [];
        }
        return explode($delimiter, $string, $limit);
    }
    
    /**
     * Safe strtolower() that handles null values
     * 
     * @param string|null $string
     * @return string
     */
    public static function safe_strtolower($string): string {
        return $string !== null ? strtolower($string) : '';
    }
    
    /**
     * Safe strtoupper() that handles null values
     * 
     * @param string|null $string
     * @return string
     */
    public static function safe_strtoupper($string): string {
        return $string !== null ? strtoupper($string) : '';
    }
    
    /**
     * Safe preg_match() that handles null values
     * 
     * @param string $pattern
     * @param string|null $subject
     * @param array|null &$matches
     * @param int $flags
     * @param int $offset
     * @return int|false
     */
    public static function safe_preg_match(string $pattern, $subject, ?array &$matches = null, int $flags = 0, int $offset = 0) {
        if ($subject === null) {
            $matches = [];
            return 0;
        }
        return preg_match($pattern, $subject, $matches, $flags, $offset);
    }
    
    /**
     * Safe preg_replace() that handles null values
     * 
     * @param string|array $pattern
     * @param string|array $replacement
     * @param string|null $subject
     * @param int $limit
     * @param int|null &$count
     * @return string
     */
    public static function safe_preg_replace($pattern, $replacement, $subject, int $limit = -1, ?int &$count = null): string {
        if ($subject === null) {
            return '';
        }
        $result = preg_replace($pattern, $replacement, $subject, $limit, $count);
        return $result !== null ? $result : '';
    }
    
    /**
     * Safe str_replace() that handles null values
     * 
     * @param string|array $search
     * @param string|array $replace
     * @param string|null $subject
     * @param int|null &$count
     * @return string
     */
    public static function safe_str_replace($search, $replace, $subject, ?int &$count = null): string {
        if ($subject === null) {
            return '';
        }
        return str_replace($search, $replace, $subject, $count);
    }
    
    /**
     * Safe array access that handles null arrays
     * 
     * @param array|null $array
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    public static function safe_array_get($array, $key, $default = null) {
        if (!is_array($array)) {
            return $default;
        }
        return $array[$key] ?? $default;
    }
    
    /**
     * Check if running on PHP 8.1+
     * 
     * @return bool
     */
    public static function is_php81_plus(): bool {
        return version_compare(PHP_VERSION, '8.1.0', '>=');
    }
    
    /**
     * Handle deprecated WordPress functions
     * 
     * @param string $function
     * @param array $args
     * @param string|null $replacement
     * @return mixed
     */
    public static function handle_deprecated_function(string $function, array $args = [], ?string $replacement = null) {
        switch ($function) {
            case 'wp_make_content_images_responsive':
                // Deprecated in WordPress 5.5, use wp_filter_content_tags
                if (function_exists('wp_filter_content_tags')) {
                    return wp_filter_content_tags(...$args);
                }
                // Fallback for older WordPress versions
                return function_exists('wp_make_content_images_responsive') ? wp_make_content_images_responsive(...$args) : $args[0] ?? '';
            
            case 'wp_targeted_link_rel':
                // Handle deprecated rel attribute function
                if (function_exists('wp_rel_ugc')) {
                    return wp_rel_ugc(...$args);
                }
                return $args[0] ?? '';
            
            default:
                // Generic fallback
                if (function_exists($function)) {
                    return call_user_func_array($function, $args);
                }
                return null;
        }
    }
    
    /**
     * Initialize compatibility fixes
     * 
     * @return void
     */
    public static function init(): void {
        // Register global helper functions if needed
        if (!function_exists('wcefp_safe_str')) {
            /**
             * Global helper for safe string operations
             * 
             * @param string|null $string
             * @return string
             */
            function wcefp_safe_str($string): string {
                return CompatibilityHelper::safe_trim($string);
            }
        }
        
        // Handle WordPress deprecated notices in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('deprecated_function_run', [self::class, 'log_deprecated_function'], 10, 3);
            add_action('deprecated_file_included', [self::class, 'log_deprecated_file'], 10, 4);
            add_action('deprecated_argument_run', [self::class, 'log_deprecated_argument'], 10, 3);
        }
    }
    
    /**
     * Log deprecated function usage
     * 
     * @param string $function
     * @param string $replacement
     * @param string $version
     * @return void
     */
    public static function log_deprecated_function(string $function, string $replacement, string $version): void {
        if (strpos($function, 'wcefp') !== false || strpos(wp_debug_backtrace_summary(), 'wcefp') !== false) {
            Logger::warning('Deprecated function used', [
                'function' => $function,
                'replacement' => $replacement,
                'version' => $version,
                'backtrace' => wp_debug_backtrace_summary()
            ]);
        }
    }
    
    /**
     * Log deprecated file inclusion
     * 
     * @param string $file
     * @param string $replacement
     * @param string $version
     * @param string $message
     * @return void
     */
    public static function log_deprecated_file(string $file, string $replacement, string $version, string $message): void {
        if (strpos($file, 'wcefp') !== false) {
            Logger::warning('Deprecated file included', [
                'file' => $file,
                'replacement' => $replacement,
                'version' => $version,
                'message' => $message
            ]);
        }
    }
    
    /**
     * Log deprecated argument usage
     * 
     * @param string $function
     * @param string $message
     * @param string $version
     * @return void
     */
    public static function log_deprecated_argument(string $function, string $message, string $version): void {
        if (strpos($function, 'wcefp') !== false || strpos(wp_debug_backtrace_summary(), 'wcefp') !== false) {
            Logger::warning('Deprecated argument used', [
                'function' => $function,
                'message' => $message,
                'version' => $version,
                'backtrace' => wp_debug_backtrace_summary()
            ]);
        }
    }
    
    /**
     * Safe array merge that handles null values
     * 
     * @param array|null ...$arrays
     * @return array
     */
    public static function safe_array_merge(...$arrays): array {
        $result = [];
        foreach ($arrays as $array) {
            if (is_array($array)) {
                $result = array_merge($result, $array);
            }
        }
        return $result;
    }
    
    /**
     * Safe JSON decode that handles null values
     * 
     * @param string|null $json
     * @param bool $associative
     * @param int $depth
     * @param int $flags
     * @return mixed
     */
    public static function safe_json_decode($json, bool $associative = false, int $depth = 512, int $flags = 0) {
        if ($json === null || $json === '') {
            return $associative ? [] : null;
        }
        
        $result = json_decode($json, $associative, $depth, $flags);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::warning('JSON decode error', [
                'error' => json_last_error_msg(),
                'json' => substr($json, 0, 100) . (strlen($json) > 100 ? '...' : '')
            ]);
            return $associative ? [] : null;
        }
        
        return $result;
    }
    
    /**
     * Type-safe number formatting
     * 
     * @param mixed $number
     * @param int $decimals
     * @param string $decimal_separator
     * @param string $thousands_separator
     * @return string
     */
    public static function safe_number_format($number, int $decimals = 0, string $decimal_separator = '.', string $thousands_separator = ','): string {
        if (!is_numeric($number)) {
            return '0';
        }
        return number_format((float)$number, $decimals, $decimal_separator, $thousands_separator);
    }
    
    /**
     * Safe date formatting
     * 
     * @param string $format
     * @param mixed $timestamp
     * @return string
     */
    public static function safe_date_format(string $format, $timestamp = null): string {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        if (is_string($timestamp)) {
            $timestamp = strtotime($timestamp);
        }
        
        if ($timestamp === false || !is_numeric($timestamp)) {
            return '';
        }
        
        return date($format, (int)$timestamp);
    }
}