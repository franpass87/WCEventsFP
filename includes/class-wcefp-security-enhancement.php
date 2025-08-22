<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Security Enhancements
 * Provides rate limiting, CSP headers, and additional security measures
 */
class WCEFP_Security_Enhancement {
    
    private static $instance = null;
    private $rate_limits = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize security features
        add_action('init', [$this, 'init_security_headers']);
        add_action('init', [$this, 'check_host_security']);
        add_action('wp_ajax_wcefp_check_rate_limit', [$this, 'check_rate_limit_before_action']);
        add_action('wp_ajax_nopriv_wcefp_check_rate_limit', [$this, 'check_rate_limit_before_action']);
        
        // Hook into all WCEFP AJAX actions for rate limiting
        $this->setup_rate_limiting();
        
        // Security logging
        add_action('wp_login_failed', [$this, 'log_failed_login']);
        add_action('wp_login', [$this, 'log_successful_login'], 10, 2);
    }
    
    /**
     * Initialize security headers
     */
    public function init_security_headers() {
        if (!is_admin()) {
            add_action('wp_head', [$this, 'add_csp_headers'], 1);
            add_action('wp_head', [$this, 'add_security_headers'], 1);
        }
    }
    
    /**
     * Add Content Security Policy headers
     */
    public function add_csp_headers() {
        $csp_directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://connect.facebook.net https://maps.googleapis.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self' https://www.google-analytics.com https://analytics.google.com",
            "frame-src 'self' https://www.google.com https://maps.google.com"
        ];
        
        $csp_policy = implode('; ', $csp_directives);
        
        // Apply filter for customization
        $csp_policy = apply_filters('wcefp_csp_policy', $csp_policy);
        
        header("Content-Security-Policy: " . $csp_policy);
        header("X-Content-Security-Policy: " . $csp_policy); // Legacy browsers
    }
    
    /**
     * Add additional security headers
     */
    public function add_security_headers() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        // HSTS for HTTPS sites
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
    
    /**
     * Setup rate limiting for AJAX actions
     */
    private function setup_rate_limiting() {
        $ajax_actions = [
            'wcefp_get_calendar',
            'wcefp_book_slot',
            'wcefp_update_occurrence',
            'wcefp_get_recommendations',
            'wcefp_track_behavior',
            'wcefp_get_google_reviews',
            'wcefp_get_advanced_analytics'
        ];
        
        foreach ($ajax_actions as $action) {
            add_action("wp_ajax_$action", [$this, 'enforce_rate_limit'], 1);
            add_action("wp_ajax_nopriv_$action", [$this, 'enforce_rate_limit'], 1);
        }
    }
    
    /**
     * Enforce rate limiting for sensitive actions
     */
    public function enforce_rate_limit() {
        $action = $_POST['action'] ?? '';
        $user_ip = $this->get_client_ip();
        $user_id = get_current_user_id();
        
        // Define rate limits per action
        $limits = [
            'wcefp_book_slot' => ['requests' => 10, 'window' => 300], // 10 requests per 5 minutes
            'wcefp_get_calendar' => ['requests' => 30, 'window' => 60], // 30 requests per minute
            'wcefp_update_occurrence' => ['requests' => 20, 'window' => 300], // 20 requests per 5 minutes
            'wcefp_track_behavior' => ['requests' => 100, 'window' => 300], // 100 requests per 5 minutes
            'default' => ['requests' => 50, 'window' => 300] // Default: 50 requests per 5 minutes
        ];
        
        $limit_config = $limits[$action] ?? $limits['default'];
        
        // Create unique key for this IP/user/action combination
        $rate_key = sprintf('wcefp_rate_%s_%s_%s', 
            md5($user_ip), 
            $user_id ?: 'guest', 
            $action
        );
        
        if ($this->is_rate_limited($rate_key, $limit_config['requests'], $limit_config['window'])) {
            WCEFP_Logger::warning('WCEventsFP Security: Rate limit exceeded', [
                'plugin' => 'WCEventsFP',
                'ip' => $user_ip,
                'user_id' => $user_id,
                'action' => $action,
                'limit' => $limit_config,
                'timestamp' => current_time('c')
            ]);
            
            wp_send_json_error([
                'msg' => __('Too many requests. Please wait before trying again.', 'wceventsfp'),
                'retry_after' => $limit_config['window']
            ], 429);
        }
        
        // Record this request
        $this->record_request($rate_key, $limit_config['window']);
    }
    
    /**
     * Check if IP/user is rate limited
     */
    private function is_rate_limited($key, $max_requests, $window) {
        $requests = get_transient($key) ?: [];
        $current_time = time();
        
        // Remove old requests outside the window
        $requests = array_filter($requests, function($timestamp) use ($current_time, $window) {
            return ($current_time - $timestamp) < $window;
        });
        
        return count($requests) >= $max_requests;
    }
    
    /**
     * Record a request for rate limiting
     */
    private function record_request($key, $window) {
        $requests = get_transient($key) ?: [];
        $requests[] = time();
        
        // Keep only recent requests
        $current_time = time();
        $requests = array_filter($requests, function($timestamp) use ($current_time, $window) {
            return ($current_time - $timestamp) < $window;
        });
        
        set_transient($key, $requests, $window);
    }
    
    /**
     * Get client IP address (handles proxies and CDNs)
     */
    private function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (common with proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Log failed login attempts for security monitoring
     */
    public function log_failed_login($username) {
        WCEFP_Logger::warning('WCEventsFP Security: Failed login attempt', [
            'plugin' => 'WCEventsFP',
            'username' => $username,
            'ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => current_time('c')
        ]);
    }
    
    /**
     * Log successful logins
     */
    public function log_successful_login($user_login, $user) {
        WCEFP_Logger::info('WCEventsFP Security: Successful login', [
            'plugin' => 'WCEventsFP',
            'username' => $user_login,
            'user_id' => $user->ID,
            'ip' => $this->get_client_ip(),
            'timestamp' => current_time('c')
        ]);
    }
    
    /**
     * Security scan for suspicious patterns
     */
    public function security_scan_request() {
        $suspicious_patterns = [
            '/\.\.\//i',                    // Directory traversal
            '/union\s+select/i',            // SQL injection
            '/<script[^>]*>/i',             // XSS attempt
            '/javascript:/i',               // JavaScript injection
            '/vbscript:/i',                 // VBScript injection  
            '/onload\s*=/i',               // Event handler injection
        ];
        
        $request_data = json_encode($_REQUEST);
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $request_data)) {
                WCEFP_Logger::error('WCEventsFP Security: Suspicious request pattern detected', [
                    'plugin' => 'WCEventsFP',
                    'pattern' => $pattern,
                    'ip' => $this->get_client_ip(),
                    'request_data' => $request_data,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                    'timestamp' => current_time('c')
                ]);
                
                // Block the request
                wp_die('Suspicious activity detected', 'Security Warning', ['response' => 403]);
            }
        }
    }

    /**
     * Validate host/domain for security purposes
     * Helps distinguish legitimate domains from suspicious ones
     */
    public function validate_host_security($host) {
        if (empty($host)) {
            return ['valid' => false, 'reason' => 'Empty host'];
        }
        
        // Sanitize the host
        $host = strtolower(trim($host));
        
        // Remove protocol if present
        $host = preg_replace('#^https?://#', '', $host);
        
        // Remove path and query parameters
        $host = parse_url('http://' . $host, PHP_URL_HOST);
        
        if (!$host) {
            return ['valid' => false, 'reason' => 'Invalid host format'];
        }
        
        // Check for suspicious patterns in domain
        $suspicious_domain_patterns = [
            '/\d+\.\d+\.\d+\.\d+/',         // Raw IP addresses (potentially suspicious)
            '/[0-9]+[a-z]+[0-9]+/',         // Mixed numbers and letters in unusual patterns
            '/(.)\1{4,}/',                  // Repeated characters (e.g., aaaa.com)
            '/^[0-9-]+\.[a-z]{2,}$/',      // Only numbers and hyphens in subdomain
        ];
        
        foreach ($suspicious_domain_patterns as $pattern) {
            if (preg_match($pattern, $host)) {
                WCEFP_Logger::warning('WCEventsFP Security: Potentially suspicious host pattern detected', [
                    'plugin' => 'WCEventsFP',
                    'host' => $host,
                    'pattern' => $pattern,
                    'ip' => $this->get_client_ip(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                    'note' => 'This is a security alert, not necessarily malicious',
                    'timestamp' => current_time('c')
                ]);
                return ['valid' => false, 'reason' => 'Suspicious host pattern', 'severity' => 'warning'];
            }
        }
        
        // Check against whitelist of known legitimate domains (for false positive reduction)
        $legitimate_domains = [
            'fattoriadianella.it',          // Legitimate Italian business
            'booking.com',
            'expedia.com', 
            'getyourguide.com',
            'viator.com',
            'airbnb.com',
            'tripadvisor.com',
            'google.com',
            'googleapis.com',
            'facebook.com',
            'instagram.com'
        ];
        
        // Check if it's a known legitimate domain or subdomain
        foreach ($legitimate_domains as $legit_domain) {
            if ($host === $legit_domain || str_ends_with($host, '.' . $legit_domain)) {
                return ['valid' => true, 'reason' => 'Whitelisted legitimate domain'];
            }
        }
        
        // Default to valid for other domains (innocent until proven guilty approach)
        return ['valid' => true, 'reason' => 'No suspicious patterns detected'];
    }

    /**
     * Check if a referrer or host is suspicious and log appropriately
     */
    public function check_host_security() {
        $current_host = $_SERVER['HTTP_HOST'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        
        if ($referrer) {
            $referrer_host = parse_url($referrer, PHP_URL_HOST);
            if ($referrer_host && $referrer_host !== $current_host) {
                $validation = $this->validate_host_security($referrer_host);
                
                if (!$validation['valid'] && $validation['severity'] !== 'warning') {
                    WCEFP_Logger::error('WCEventsFP Security: Suspicious referrer host detected', [
                        'plugin' => 'WCEventsFP',
                        'referrer_host' => $referrer_host,
                        'current_host' => $current_host,
                        'reason' => $validation['reason'],
                        'ip' => $this->get_client_ip(),
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                        'timestamp' => current_time('c')
                    ]);
                }
            }
        }
    }
    
    /**
     * Initialize security features
     */
    public static function init() {
        return self::get_instance();
    }
}

// Initialize security enhancements
WCEFP_Security_Enhancement::init();