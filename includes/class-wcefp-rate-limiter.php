<?php
/**
 * Simple Rate Limiter per WCEventsFP
 * Protegge endpoint AJAX da abusi e migliora sicurezza
 *
 * @package WCEventsFP
 * @since 1.7.2
 */

if (!defined('ABSPATH')) exit;

class WCEFP_RateLimiter {
    
    const TRANSIENT_PREFIX = 'wcefp_rate_limit_';
    
    /**
     * Check if request is rate limited
     * 
     * @param string $action AJAX action name
     * @param int $limit Max requests per window
     * @param int $window Time window in seconds
     * @return bool True if rate limited
     */
    public static function is_limited($action, $limit = 60, $window = 60) {
        $user_id = get_current_user_id();
        $ip = self::get_client_ip();
        
        // Create unique key per user/IP and action
        $key = self::TRANSIENT_PREFIX . md5($action . '_' . $user_id . '_' . $ip);
        
        $requests = get_transient($key);
        $requests = $requests ? (int)$requests : 0;
        
        if ($requests >= $limit) {
            // Log rate limit exceeded
            $logger = new WCEFP_Logger();
            $logger->warning('Rate limit exceeded', [
                'action' => $action,
                'user_id' => $user_id,
                'ip' => $ip,
                'requests' => $requests,
                'limit' => $limit
            ]);
            return true;
        }
        
        // Increment counter
        set_transient($key, $requests + 1, $window);
        
        return false;
    }
    
    /**
     * Check rate limit and send error if exceeded
     * 
     * @param string $action AJAX action name
     * @param int $limit Max requests per window  
     * @param int $window Time window in seconds
     */
    public static function check_or_die($action, $limit = 60, $window = 60) {
        if (self::is_limited($action, $limit, $window)) {
            wp_send_json_error([
                'msg' => __('Troppe richieste. Riprova più tardi.', 'wceventsfp'),
                'code' => 'rate_limit_exceeded'
            ]);
        }
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim($_SERVER[$key]);
                // Take first IP if comma-separated
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                    $ip = trim($ip);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Clear rate limit for user/IP and action (admin only)
     */
    public static function clear_limit($action, $user_id = null, $ip = null) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $user_id = $user_id ?: get_current_user_id();
        $ip = $ip ?: self::get_client_ip();
        
        $key = self::TRANSIENT_PREFIX . md5($action . '_' . $user_id . '_' . $ip);
        delete_transient($key);
        
        return true;
    }
    
    /**
     * Get current request count for debugging
     */
    public static function get_current_count($action, $user_id = null, $ip = null) {
        if (!current_user_can('manage_options')) {
            return 0;
        }
        
        $user_id = $user_id ?: get_current_user_id();
        $ip = $ip ?: self::get_client_ip();
        
        $key = self::TRANSIENT_PREFIX . md5($action . '_' . $user_id . '_' . $ip);
        return (int)get_transient($key);
    }
}