<?php
/**
 * API Version Manager - Centralized API versioning and namespace management
 * 
 * T-04: This class provides a single source of truth for API versioning,
 * ensuring consistent namespace usage across all REST endpoints.
 * 
 * @package WCEFP\API
 * @since 2.2.0 (T-04)
 */

namespace WCEFP\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Version Manager
 * 
 * Centralizes API version configuration and provides helper methods
 * for consistent versioning across all REST endpoints.
 */
class ApiVersionManager {
    
    /**
     * Current API version (primary)
     * @var string
     */
    const CURRENT_VERSION = 'v2';
    
    /**
     * Legacy API version (deprecated but supported)
     * @var string
     */
    const LEGACY_VERSION = 'v1';
    
    /**
     * Base namespace
     * @var string
     */
    const BASE_NAMESPACE = 'wcefp';
    
    /**
     * Get current API namespace
     * 
     * @return string
     */
    public static function get_current_namespace(): string {
        return self::BASE_NAMESPACE . '/' . self::CURRENT_VERSION;
    }
    
    /**
     * Get legacy API namespace
     * 
     * @return string
     */
    public static function get_legacy_namespace(): string {
        return self::BASE_NAMESPACE . '/' . self::LEGACY_VERSION;
    }
    
    /**
     * Get all supported namespaces
     * 
     * @return array
     */
    public static function get_all_namespaces(): array {
        return [
            'current' => self::get_current_namespace(),
            'legacy' => self::get_legacy_namespace()
        ];
    }
    
    /**
     * Check if a namespace is deprecated
     * 
     * @param string $namespace
     * @return bool
     */
    public static function is_deprecated(string $namespace): bool {
        return $namespace === self::get_legacy_namespace();
    }
    
    /**
     * Get deprecation headers for legacy endpoints
     * 
     * @return array
     */
    public static function get_deprecation_headers(): array {
        return [
            'X-WP-Deprecated' => 'This API version is deprecated. Please use ' . self::CURRENT_VERSION . '.',
            'X-WP-Deprecated-New' => str_replace(self::LEGACY_VERSION, self::CURRENT_VERSION, $_SERVER['REQUEST_URI'] ?? ''),
            'X-WP-Deprecated-Version' => self::CURRENT_VERSION
        ];
    }
    
    /**
     * Add deprecation headers to a REST response
     * 
     * @param \WP_REST_Response $response
     * @param string $namespace
     * @return \WP_REST_Response
     */
    public static function add_deprecation_headers($response, string $namespace) {
        if (self::is_deprecated($namespace)) {
            foreach (self::get_deprecation_headers() as $header => $value) {
                $response->header($header, $value);
            }
        }
        
        return $response;
    }
    
    /**
     * Get recommended API URL for documentation
     * 
     * @param string $endpoint
     * @return string
     */
    public static function get_api_url(string $endpoint = ''): string {
        $namespace = self::get_current_namespace();
        return rest_url($namespace . ($endpoint ? '/' . ltrim($endpoint, '/') : ''));
    }
    
    /**
     * Validate API version format
     * 
     * @param string $version
     * @return bool
     */
    public static function is_valid_version(string $version): bool {
        return preg_match('/^v\d+$/', $version);
    }
    
    /**
     * Get version number from namespace
     * 
     * @param string $namespace
     * @return int|null
     */
    public static function extract_version_number(string $namespace): ?int {
        if (preg_match('/v(\d+)$/', $namespace, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }
    
    /**
     * Compare API versions
     * 
     * @param string $version1
     * @param string $version2
     * @return int (-1, 0, 1)
     */
    public static function compare_versions(string $version1, string $version2): int {
        $v1 = self::extract_version_number($version1);
        $v2 = self::extract_version_number($version2);
        
        if ($v1 === null || $v2 === null) {
            return 0;
        }
        
        return $v1 <=> $v2;
    }
}