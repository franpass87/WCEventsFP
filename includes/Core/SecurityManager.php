<?php
/**
 * Security Manager
 * 
 * Comprehensive security implementation with capabilities, nonce validation,
 * input sanitization, output escaping, and query preparation.
 * 
 * @package WCEFP\Core
 * @since 2.1.4
 */

namespace WCEFP\Core;

use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Manager class
 */
class SecurityManager {
    
    /**
     * Initialize security manager
     * 
     * @return void
     */
    public function init(): void {
        // Hook into WordPress security lifecycle
        add_action('init', [$this, 'register_capabilities'], 1);
        add_action('wp_loaded', [$this, 'validate_security_configuration']);
        
        // Add security headers
        add_action('send_headers', [$this, 'add_security_headers']);
        
        // Validate nonces on admin actions
        add_action('admin_init', [$this, 'validate_admin_nonces']);
        
        // Sanitize REST API inputs
        add_filter('rest_pre_dispatch', [$this, 'sanitize_rest_inputs'], 10, 3);
        
        Logger::info('Security Manager initialized');
    }
    
    /**
     * Register WCEFP capabilities
     * 
     * @return void
     */
    public function register_capabilities(): void {
        $capabilities = [
            // Booking capabilities
            'view_wcefp_bookings' => __('View WCEventsFP bookings', 'wceventsfp'),
            'edit_wcefp_bookings' => __('Edit WCEventsFP bookings', 'wceventsfp'),
            'delete_wcefp_bookings' => __('Delete WCEventsFP bookings', 'wceventsfp'),
            'manage_wcefp_bookings' => __('Manage all WCEventsFP bookings', 'wceventsfp'),
            
            // Event capabilities
            'manage_wcefp_events' => __('Manage WCEventsFP events', 'wceventsfp'),
            'publish_wcefp_events' => __('Publish WCEventsFP events', 'wceventsfp'),
            
            // Settings capabilities
            'manage_wcefp_settings' => __('Manage WCEventsFP settings', 'wceventsfp'),
            'manage_wcefp_integrations' => __('Manage WCEventsFP integrations', 'wceventsfp'),
            
            // Voucher capabilities
            'manage_wcefp_vouchers' => __('Manage WCEventsFP vouchers', 'wceventsfp'),
            'view_wcefp_vouchers' => __('View WCEventsFP vouchers', 'wceventsfp'),
            
            // Export capabilities
            'export_wcefp_data' => __('Export WCEventsFP data', 'wceventsfp'),
            
            // System capabilities
            'view_wcefp_system_status' => __('View WCEventsFP system status', 'wceventsfp')
        ];
        
        // Get admin role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities as $cap => $description) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Get shop manager role (WooCommerce)
        $shop_manager_role = get_role('shop_manager');
        if ($shop_manager_role) {
            $shop_manager_caps = [
                'view_wcefp_bookings',
                'edit_wcefp_bookings',
                'manage_wcefp_bookings',
                'manage_wcefp_events',
                'publish_wcefp_events',
                'view_wcefp_vouchers',
                'export_wcefp_data'
            ];
            
            foreach ($shop_manager_caps as $cap) {
                $shop_manager_role->add_cap($cap);
            }
        }
        
        Logger::info('WCEFP capabilities registered');
    }
    
    /**
     * Check if current user has WCEFP capability
     * 
     * @param string $capability
     * @return bool
     */
    public static function current_user_can(string $capability): bool {
        // Fallback to WooCommerce capabilities if WCEFP capability not found
        $fallback_mapping = [
            'view_wcefp_bookings' => 'manage_woocommerce',
            'edit_wcefp_bookings' => 'manage_woocommerce',
            'delete_wcefp_bookings' => 'manage_woocommerce',
            'manage_wcefp_bookings' => 'manage_woocommerce',
            'manage_wcefp_events' => 'manage_woocommerce',
            'publish_wcefp_events' => 'manage_woocommerce',
            'manage_wcefp_settings' => 'manage_options',
            'manage_wcefp_integrations' => 'manage_options',
            'manage_wcefp_vouchers' => 'manage_woocommerce',
            'view_wcefp_vouchers' => 'manage_woocommerce',
            'export_wcefp_data' => 'manage_woocommerce',
            'view_wcefp_system_status' => 'manage_options'
        ];
        
        if (current_user_can($capability)) {
            return true;
        }
        
        if (isset($fallback_mapping[$capability])) {
            return current_user_can($fallback_mapping[$capability]);
        }
        
        return false;
    }
    
    /**
     * Validate nonce for WCEFP actions
     * 
     * @param string $nonce
     * @param string $action
     * @return bool
     */
    public static function verify_nonce(string $nonce, string $action): bool {
        if (!wp_verify_nonce($nonce, $action)) {
            Logger::warning('Nonce verification failed', [
                'action' => $action,
                'user_id' => get_current_user_id(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Create nonce for WCEFP actions
     * 
     * @param string $action
     * @return string
     */
    public static function create_nonce(string $action): string {
        return wp_create_nonce($action);
    }
    
    /**
     * Sanitize input data
     * 
     * @param mixed $data
     * @param string $type
     * @return mixed
     */
    public static function sanitize_input($data, string $type = 'text') {
        switch ($type) {
            case 'text':
                return sanitize_text_field($data);
            
            case 'textarea':
                return sanitize_textarea_field($data);
            
            case 'email':
                return sanitize_email($data);
            
            case 'url':
                return esc_url_raw($data);
            
            case 'int':
                return absint($data);
            
            case 'float':
                return floatval($data);
            
            case 'array':
                return is_array($data) ? array_map(['self', 'sanitize_text_field'], $data) : [];
            
            case 'json':
                if (is_string($data)) {
                    $decoded = json_decode($data, true);
                    return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
                }
                return is_array($data) ? $data : [];
            
            case 'html':
                return wp_kses_post($data);
            
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * Escape output data
     * 
     * @param mixed $data
     * @param string $context
     * @return string
     */
    public static function escape_output($data, string $context = 'html'): string {
        switch ($context) {
            case 'html':
                return esc_html($data);
            
            case 'attr':
                return esc_attr($data);
            
            case 'url':
                return esc_url($data);
            
            case 'js':
                return esc_js($data);
            
            case 'textarea':
                return esc_textarea($data);
            
            default:
                return esc_html($data);
        }
    }
    
    /**
     * Prepare SQL query safely
     * 
     * @param string $query
     * @param array $args
     * @return string
     */
    public static function prepare_query(string $query, array $args = []): string {
        global $wpdb;
        
        if (empty($args)) {
            return $query;
        }
        
        return $wpdb->prepare($query, $args);
    }
    
    /**
     * Validate admin nonces on form submissions
     * 
     * @return void
     */
    public function validate_admin_nonces(): void {
        // Check for WCEFP admin actions
        if (isset($_POST['action']) && strpos($_POST['action'], 'wcefp_') === 0) {
            $action = sanitize_text_field($_POST['action']);
            $nonce_field = $action . '_nonce';
            
            if (isset($_POST[$nonce_field])) {
                if (!self::verify_nonce($_POST[$nonce_field], $action)) {
                    wp_die(__('Security check failed. Please try again.', 'wceventsfp'), 403);
                }
            }
        }
    }
    
    /**
     * Add security headers
     * 
     * @return void
     */
    public function add_security_headers(): void {
        // Only add headers in admin area for WCEFP pages
        if (!is_admin() || !isset($_GET['page']) || strpos($_GET['page'], 'wcefp') !== 0) {
            return;
        }
        
        // Content Security Policy
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    
    /**
     * Sanitize REST API inputs
     * 
     * @param mixed $result
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public function sanitize_rest_inputs($result, $server, $request) {
        // Only process WCEFP REST routes
        if (strpos($request->get_route(), '/wcefp/') !== 0) {
            return $result;
        }
        
        // Validate and sanitize request parameters
        $params = $request->get_params();
        $sanitized_params = [];
        
        foreach ($params as $key => $value) {
            $sanitized_params[$key] = $this->sanitize_rest_param($key, $value);
        }
        
        // Update request with sanitized parameters
        foreach ($sanitized_params as $key => $value) {
            $request->set_param($key, $value);
        }
        
        return $result;
    }
    
    /**
     * Sanitize individual REST parameter
     * 
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    private function sanitize_rest_param(string $key, $value) {
        // Define parameter sanitization rules
        $sanitization_rules = [
            'email' => 'email',
            'customer_email' => 'email',
            'url' => 'url',
            'event_id' => 'int',
            'product_id' => 'int',
            'booking_id' => 'int',
            'id' => 'int',
            'page' => 'int',
            'per_page' => 'int',
            'participants' => 'int',
            'status' => 'text',
            'date_from' => 'text',
            'date_to' => 'text',
            'booking_date' => 'text',
            'customer_name' => 'text',
            'category' => 'text'
        ];
        
        $type = $sanitization_rules[$key] ?? 'text';
        return self::sanitize_input($value, $type);
    }
    
    /**
     * Validate security configuration
     * 
     * @return void
     */
    public function validate_security_configuration(): void {
        $issues = [];
        
        // Check if WordPress is up to date
        if (version_compare(get_bloginfo('version'), '6.5.0', '<')) {
            $issues[] = __('WordPress version is outdated. Please update to 6.5+', 'wceventsfp');
        }
        
        // Check if WooCommerce is active and up to date
        if (!class_exists('WooCommerce')) {
            $issues[] = __('WooCommerce is not active', 'wceventsfp');
        } elseif (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0.0', '<')) {
            $issues[] = __('WooCommerce version is outdated. Please update to 8.0+', 'wceventsfp');
        }
        
        // Check SSL
        if (!is_ssl()) {
            $issues[] = __('SSL certificate not detected. HTTPS is recommended for security', 'wceventsfp');
        }
        
        // Check file permissions
        if (is_writable(WCEFP_PLUGIN_DIR)) {
            $issues[] = __('Plugin directory is writable. Consider setting proper file permissions', 'wceventsfp');
        }
        
        // Log security issues
        if (!empty($issues)) {
            Logger::warning('Security configuration issues detected', [
                'issues' => $issues
            ]);
        }
    }
    
    /**
     * Generate secure random token
     * 
     * @param int $length
     * @return string
     */
    public static function generate_token(int $length = 32): string {
        return wp_generate_password($length, false);
    }
    
    /**
     * Hash sensitive data
     * 
     * @param string $data
     * @return string
     */
    public static function hash_data(string $data): string {
        return wp_hash($data);
    }
    
    /**
     * Verify hashed data
     * 
     * @param string $data
     * @param string $hash
     * @return bool
     */
    public static function verify_hash(string $data, string $hash): bool {
        return hash_equals(self::hash_data($data), $hash);
    }
    
    /**
     * Log security event
     * 
     * @param string $event
     * @param array $context
     * @return void
     */
    public static function log_security_event(string $event, array $context = []): void {
        Logger::warning('Security Event: ' . $event, array_merge($context, [
            'user_id' => get_current_user_id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => current_time('c')
        ]));
    }
    
    /**
     * Rate limiting for sensitive operations
     * 
     * @param string $action
     * @param int $limit
     * @param int $window_seconds
     * @return bool
     */
    public static function check_rate_limit(string $action, int $limit = 5, int $window_seconds = 300): bool {
        $user_id = get_current_user_id() ?: 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "wcefp_rate_limit_{$action}_{$user_id}_{$ip}";
        
        $attempts = get_transient($key) ?: 0;
        
        if ($attempts >= $limit) {
            self::log_security_event('Rate limit exceeded', [
                'action' => $action,
                'attempts' => $attempts,
                'limit' => $limit
            ]);
            return false;
        }
        
        set_transient($key, $attempts + 1, $window_seconds);
        return true;
    }
    
    /**
     * Validate file upload security
     * 
     * @param array $file
     * @return bool
     */
    public static function validate_file_upload(array $file): bool {
        // Check file size
        $max_size = wp_max_upload_size();
        if ($file['size'] > $max_size) {
            return false;
        }
        
        // Check file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/csv'];
        if (!in_array($file['type'], $allowed_types)) {
            return false;
        }
        
        // Check file extension
        $file_info = pathinfo($file['name']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'csv'];
        if (!in_array(strtolower($file_info['extension']), $allowed_extensions)) {
            return false;
        }
        
        return true;
    }
}