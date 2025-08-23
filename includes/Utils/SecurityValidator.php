<?php
/**
 * Security Validator Utility
 * 
 * Handles security validation, IP detection, and rate limiting
 * Part of Phase 5: Advanced Booking Features
 *
 * @package WCEFP
 * @subpackage Utils
 * @since 2.2.0
 */

namespace WCEFP\Utils;

class SecurityValidator {
    
    private $rate_limit_cache;
    
    public function __construct() {
        $this->rate_limit_cache = [];
    }
    
    /**
     * Get client IP address with proxy support
     *
     * @return string Client IP address
     */
    public function get_client_ip() {
        // Check for IP from shared internet
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check for IP passed from proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        // Check for IP from remote address
        else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        
        // Handle comma-separated IPs from proxies
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        
        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '127.0.0.1';
        }
        
        return $ip;
    }
    
    /**
     * Validate check-in request security
     *
     * @param array $request_data Request data
     * @return true|WP_Error Validation result
     */
    public function validate_checkin_request($request_data) {
        // Rate limiting check
        $rate_limit_result = $this->check_rate_limit('checkin', $this->get_client_ip(), 10, 300); // 10 attempts per 5 minutes
        if (is_wp_error($rate_limit_result)) {
            return $rate_limit_result;
        }
        
        // Validate required fields
        if (empty($request_data['token'])) {
            return new \WP_Error('missing_token', __('Check-in token is required', 'wceventsfp'));
        }
        
        if (empty($request_data['booking_id'])) {
            return new \WP_Error('missing_booking_id', __('Booking ID is required', 'wceventsfp'));
        }
        
        // Validate token format
        if (!preg_match('/^[a-zA-Z0-9]{32}$/', $request_data['token'])) {
            return new \WP_Error('invalid_token_format', __('Invalid token format', 'wceventsfp'));
        }
        
        // Validate booking ID
        if (!is_numeric($request_data['booking_id']) || $request_data['booking_id'] <= 0) {
            return new \WP_Error('invalid_booking_id', __('Invalid booking ID', 'wceventsfp'));
        }
        
        // Validate optional location field
        if (!empty($request_data['location'])) {
            if (strlen($request_data['location']) > 255) {
                return new \WP_Error('location_too_long', __('Location field is too long', 'wceventsfp'));
            }
            
            // Check for suspicious patterns
            if ($this->contains_suspicious_content($request_data['location'])) {
                return new \WP_Error('suspicious_location', __('Location contains invalid content', 'wceventsfp'));
            }
        }
        
        // Validate optional notes field
        if (!empty($request_data['notes'])) {
            if (strlen($request_data['notes']) > 1000) {
                return new \WP_Error('notes_too_long', __('Notes field is too long', 'wceventsfp'));
            }
            
            if ($this->contains_suspicious_content($request_data['notes'])) {
                return new \WP_Error('suspicious_notes', __('Notes contain invalid content', 'wceventsfp'));
            }
        }
        
        return true;
    }
    
    /**
     * Check rate limiting for specific action
     *
     * @param string $action Action name
     * @param string $identifier Identifier (IP, user ID, etc.)
     * @param int $limit Max attempts
     * @param int $window Time window in seconds
     * @return true|WP_Error Rate limit result
     */
    public function check_rate_limit($action, $identifier, $limit, $window) {
        $cache_key = "wcefp_rate_limit_{$action}_{$identifier}";
        
        // Get current attempts from transient
        $attempts = get_transient($cache_key);
        if ($attempts === false) {
            $attempts = [];
        }
        
        // Clean old attempts outside the window
        $current_time = time();
        $attempts = array_filter($attempts, function($timestamp) use ($current_time, $window) {
            return ($current_time - $timestamp) < $window;
        });
        
        // Check if limit exceeded
        if (count($attempts) >= $limit) {
            return new \WP_Error('rate_limit_exceeded', 
                sprintf(__('Too many attempts. Please wait %d seconds before trying again.', 'wceventsfp'), $window)
            );
        }
        
        // Add current attempt
        $attempts[] = $current_time;
        
        // Save back to transient
        set_transient($cache_key, $attempts, $window);
        
        return true;
    }
    
    /**
     * Check if content contains suspicious patterns
     *
     * @param string $content Content to check
     * @return bool True if suspicious
     */
    private function contains_suspicious_content($content) {
        // Common malicious patterns
        $suspicious_patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/\bexec\b/i',
            '/\beval\b/i',
            '/\bsystem\b/i',
            '/\bshell_exec\b/i',
            '/\bpassthru\b/i',
            '/\bunion\b.*\bselect\b/i',
            '/\bdrop\b.*\btable\b/i',
            '/\binsert\b.*\binto\b/i',
            '/\bupdate\b.*\bset\b/i',
            '/\bdelete\b.*\bfrom\b/i'
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate secure token
     *
     * @param int $length Token length
     * @return string Secure token
     */
    public function generate_secure_token($length = 32) {
        return wp_generate_password($length, false);
    }
    
    /**
     * Validate token format
     *
     * @param string $token Token to validate
     * @param int $expected_length Expected token length
     * @return bool True if valid format
     */
    public function validate_token_format($token, $expected_length = 32) {
        if (empty($token) || !is_string($token)) {
            return false;
        }
        
        if (strlen($token) !== $expected_length) {
            return false;
        }
        
        // Check if token contains only alphanumeric characters
        return preg_match('/^[a-zA-Z0-9]+$/', $token);
    }
    
    /**
     * Sanitize check-in data
     *
     * @param array $data Raw check-in data
     * @return array Sanitized data
     */
    public function sanitize_checkin_data($data) {
        $sanitized = [];
        
        // Sanitize token
        if (isset($data['token'])) {
            $sanitized['token'] = sanitize_text_field($data['token']);
        }
        
        // Sanitize booking ID
        if (isset($data['booking_id'])) {
            $sanitized['booking_id'] = absint($data['booking_id']);
        }
        
        // Sanitize location
        if (isset($data['location'])) {
            $sanitized['location'] = sanitize_text_field(wp_strip_all_tags($data['location']));
        }
        
        // Sanitize notes
        if (isset($data['notes'])) {
            $sanitized['notes'] = sanitize_textarea_field(wp_strip_all_tags($data['notes']));
        }
        
        // Add security metadata
        $sanitized['ip_address'] = $this->get_client_ip();
        $sanitized['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? 
            sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $sanitized['timestamp'] = current_time('mysql');
        $sanitized['user_id'] = get_current_user_id();
        
        return $sanitized;
    }
    
    /**
     * Log security event
     *
     * @param string $event_type Event type
     * @param array $event_data Event data
     */
    public function log_security_event($event_type, $event_data = []) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'event_type' => $event_type,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'user_id' => get_current_user_id(),
            'data' => json_encode($event_data)
        ];
        
        // Store in security log (could be implemented as custom table or WordPress log)
        do_action('wcefp_security_event', $log_entry);
        
        // Log to WordPress debug if enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log(sprintf(
                '[WCEFP Security] %s: %s from %s',
                $event_type,
                json_encode($event_data),
                $this->get_client_ip()
            ));
        }
    }
    
    /**
     * Check if IP is in whitelist
     *
     * @param string $ip IP address
     * @return bool True if whitelisted
     */
    public function is_ip_whitelisted($ip) {
        $whitelist = get_option('wcefp_ip_whitelist', []);
        
        if (empty($whitelist)) {
            return true; // No whitelist means all IPs allowed
        }
        
        foreach ($whitelist as $allowed_ip) {
            if ($this->ip_matches_pattern($ip, $allowed_ip)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP matches pattern (supports wildcards)
     *
     * @param string $ip IP to check
     * @param string $pattern Pattern to match against
     * @return bool True if matches
     */
    private function ip_matches_pattern($ip, $pattern) {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }
        
        // Wildcard support (e.g., 192.168.1.*)
        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace('*', '\d+', preg_quote($pattern, '/')) . '$/';
            return preg_match($regex, $ip);
        }
        
        // CIDR notation support (basic)
        if (strpos($pattern, '/') !== false) {
            return $this->ip_in_cidr($ip, $pattern);
        }
        
        return false;
    }
    
    /**
     * Check if IP is in CIDR range
     *
     * @param string $ip IP to check
     * @param string $cidr CIDR notation
     * @return bool True if in range
     */
    private function ip_in_cidr($ip, $cidr) {
        list($subnet, $bits) = explode('/', $cidr);
        
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || 
            !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        
        return ($ip & $mask) == ($subnet & $mask);
    }
}