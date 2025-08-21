<?php
/**
 * Configuration Helper per WCEventsFP
 * Centralizza configurazioni e migliora manutenibilità
 *
 * @package WCEventsFP
 * @since 1.7.2
 */

if (!defined('ABSPATH')) exit;

class WCEFP_Config {
    
    // Cache settings
    const CACHE_DEFAULT_EXPIRATION = HOUR_IN_SECONDS;
    const CACHE_KPI_EXPIRATION = 15 * MINUTE_IN_SECONDS;
    const CACHE_OCCURRENCES_EXPIRATION = 30 * MINUTE_IN_SECONDS;
    
    // Database settings
    const DB_MAX_RETRIES = 3;
    const DB_RETRY_DELAY = 1; // seconds
    
    // Security settings
    const AJAX_RATE_LIMIT = 60; // requests per minute
    const MAX_GIFT_MESSAGE_LENGTH = 300;
    
    // Performance settings
    const KPI_REFRESH_INTERVAL = 15 * MINUTE_IN_SECONDS;
    const MAX_OCCURRENCES_PER_REQUEST = 100;
    
    /**
     * Get plugin version
     */
    public static function version() {
        return WCEFP_VERSION;
    }
    
    /**
     * Get debug mode status
     */
    public static function debug_enabled() {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * Get allowed HTML tags for content fields
     */
    public static function allowed_html_tags() {
        return [
            'p' => [],
            'br' => [],
            'strong' => [],
            'em' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'a' => [
                'href' => true,
                'target' => true,
                'rel' => true
            ]
        ];
    }
    
    /**
     * Get default capacity for new events
     */
    public static function default_capacity() {
        return get_option('wcefp_default_capacity', 10);
    }
    
    /**
     * Check if WooCommerce emails should be disabled for events
     */
    public static function disable_wc_emails() {
        return get_option('wcefp_disable_wc_emails_for_events', '0') === '1';
    }
    
    /**
     * Get supported languages
     */
    public static function supported_languages() {
        return ['IT', 'EN', 'FR', 'DE', 'ES'];
    }
}