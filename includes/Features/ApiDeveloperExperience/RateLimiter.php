<?php
/**
 * Rate Limiter
 * 
 * Advanced rate limiting system for WCEFP API with configurable limits,
 * sliding window algorithm, and comprehensive monitoring.
 *
 * @package WCEFP\Features\ApiDeveloperExperience
 * @since 2.2.0
 */

namespace WCEFP\Features\ApiDeveloperExperience;

use WCEFP\Utils\DiagnosticLogger;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate Limiter with sliding window implementation
 */
class RateLimiter {
    
    /**
     * Default rate limits per client type
     */
    const DEFAULT_LIMITS = [
        'anonymous' => ['requests' => 100, 'window' => 3600], // 100 requests per hour
        'authenticated' => ['requests' => 1000, 'window' => 3600], // 1000 requests per hour
        'api_key' => ['requests' => 5000, 'window' => 3600], // 5000 requests per hour
        'premium' => ['requests' => 10000, 'window' => 3600], // 10000 requests per hour
    ];
    
    /**
     * Endpoint-specific limits
     */
    const ENDPOINT_LIMITS = [
        '/bookings' => ['requests' => 500, 'window' => 3600],
        '/analytics' => ['requests' => 100, 'window' => 3600],
        '/export' => ['requests' => 10, 'window' => 3600],
    ];
    
    /**
     * Cache key prefix
     */
    const CACHE_PREFIX = 'wcefp_rate_limit_';
    
    /**
     * Initialize rate limiter
     */
    public function init() {
        // Setup scheduled cleanup
        if (!wp_next_scheduled('wcefp_cleanup_rate_limit_data')) {
            wp_schedule_event(time(), 'hourly', 'wcefp_cleanup_rate_limit_data');
        }
        
        add_action('wcefp_cleanup_rate_limit_data', [$this, 'cleanup_expired_data']);
        
        // Admin page for rate limit management
        add_action('admin_menu', [$this, 'add_rate_limit_admin_page']);
        add_action('wp_ajax_wcefp_update_rate_limits', [$this, 'ajax_update_rate_limits']);
        
        DiagnosticLogger::instance()->debug('Rate Limiter initialized', [], 'api_features');
    }
    
    /**
     * Check rate limit for a client
     * 
     * @param string $client_id
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function check_rate_limit($client_id, WP_REST_Request $request) {
        $endpoint = $this->get_endpoint_from_request($request);
        $client_type = $this->get_client_type($client_id);
        
        // Get applicable limits
        $limits = $this->get_limits_for_client_and_endpoint($client_type, $endpoint);
        
        $window_start = time() - $limits['window'];
        $cache_key = self::CACHE_PREFIX . hash('sha256', $client_id . '_' . $endpoint);
        
        // Get current request data
        $request_data = wp_cache_get($cache_key);
        if (!$request_data) {
            $request_data = [];
        }
        
        // Clean old requests (sliding window)
        $request_data = array_filter($request_data, function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        // Check if limit exceeded
        $current_requests = count($request_data);
        if ($current_requests >= $limits['requests']) {
            DiagnosticLogger::instance()->warning('Rate limit exceeded', [
                'client_id' => $client_id,
                'client_type' => $client_type,
                'endpoint' => $endpoint,
                'current_requests' => $current_requests,
                'limit' => $limits['requests'],
                'window' => $limits['window']
            ], 'api_rate_limit');
            
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Rate limit exceeded. Maximum %d requests per %d seconds allowed.', 'wceventsfp'),
                    $limits['requests'],
                    $limits['window']
                ),
                [
                    'status' => 429,
                    'rate_limit' => [
                        'limit' => $limits['requests'],
                        'remaining' => 0,
                        'reset_time' => $window_start + $limits['window'],
                        'retry_after' => $limits['window']
                    ]
                ]
            );
        }
        
        // Record this request
        $request_data[] = time();
        wp_cache_set($cache_key, $request_data, '', $limits['window']);
        
        // Calculate remaining requests and reset time
        $remaining = $limits['requests'] - count($request_data);
        $oldest_request = min($request_data);
        $reset_time = $oldest_request + $limits['window'];
        
        return [
            'allowed' => true,
            'limit' => $limits['requests'],
            'remaining' => $remaining,
            'reset_time' => $reset_time,
            'window' => $limits['window']
        ];
    }
    
    /**
     * Get endpoint from request
     * 
     * @param WP_REST_Request $request
     * @return string
     */
    private function get_endpoint_from_request(WP_REST_Request $request) {
        $route = $request->get_route();
        
        // Extract base endpoint (remove IDs and query params)
        $endpoint = preg_replace('/\/\d+/', '', $route);
        $endpoint = preg_replace('/^\/wcefp\/v[12]/', '', $endpoint);
        
        return $endpoint ?: '/';
    }
    
    /**
     * Get client type from client ID
     * 
     * @param string $client_id
     * @return string
     */
    private function get_client_type($client_id) {
        if (strpos($client_id, 'user_') === 0) {
            $user_id = str_replace('user_', '', $client_id);
            $user = get_user_by('id', $user_id);
            
            if ($user && $user->has_cap('manage_wcefp_api_keys')) {
                return 'premium';
            } elseif ($user && $user->has_cap('access_wcefp_api')) {
                return 'authenticated';
            }
        } elseif (strpos($client_id, 'api_key_') === 0) {
            return 'api_key';
        }
        
        return 'anonymous';
    }
    
    /**
     * Get rate limits for client type and endpoint
     * 
     * @param string $client_type
     * @param string $endpoint
     * @return array
     */
    private function get_limits_for_client_and_endpoint($client_type, $endpoint) {
        // Start with default limits for client type
        $limits = self::DEFAULT_LIMITS[$client_type] ?? self::DEFAULT_LIMITS['anonymous'];
        
        // Apply endpoint-specific limits if they're more restrictive
        if (isset(self::ENDPOINT_LIMITS[$endpoint])) {
            $endpoint_limits = self::ENDPOINT_LIMITS[$endpoint];
            $limits['requests'] = min($limits['requests'], $endpoint_limits['requests']);
        }
        
        // Allow customization via options
        $custom_limits = get_option('wcefp_rate_limits', []);
        if (isset($custom_limits[$client_type])) {
            $limits = array_merge($limits, $custom_limits[$client_type]);
        }
        
        // Apply filters for extensibility
        return apply_filters('wcefp_rate_limits', $limits, $client_type, $endpoint);
    }
    
    /**
     * Get current rate limit status for a client
     * 
     * @param string $client_id
     * @param string $endpoint
     * @return array
     */
    public function get_rate_limit_status($client_id, $endpoint = '/') {
        $client_type = $this->get_client_type($client_id);
        $limits = $this->get_limits_for_client_and_endpoint($client_type, $endpoint);
        
        $window_start = time() - $limits['window'];
        $cache_key = self::CACHE_PREFIX . hash('sha256', $client_id . '_' . $endpoint);
        
        $request_data = wp_cache_get($cache_key) ?: [];
        $request_data = array_filter($request_data, function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        $current_requests = count($request_data);
        $remaining = max(0, $limits['requests'] - $current_requests);
        
        $reset_time = !empty($request_data) ? min($request_data) + $limits['window'] : time() + $limits['window'];
        
        return [
            'limit' => $limits['requests'],
            'remaining' => $remaining,
            'reset_time' => $reset_time,
            'window' => $limits['window'],
            'client_type' => $client_type,
            'current_requests' => $current_requests
        ];
    }
    
    /**
     * Reset rate limit for a client (admin function)
     * 
     * @param string $client_id
     * @param string $endpoint
     * @return bool
     */
    public function reset_rate_limit($client_id, $endpoint = null) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        if ($endpoint) {
            $cache_key = self::CACHE_PREFIX . hash('sha256', $client_id . '_' . $endpoint);
            wp_cache_delete($cache_key);
        } else {
            // Reset all endpoints for this client
            global $wp_object_cache;
            $pattern = self::CACHE_PREFIX . hash('sha256', $client_id);
            
            if (isset($wp_object_cache->cache)) {
                foreach ($wp_object_cache->cache as $group => $cache_group) {
                    foreach ($cache_group as $key => $value) {
                        if (strpos($key, $pattern) === 0) {
                            wp_cache_delete($key);
                        }
                    }
                }
            }
        }
        
        DiagnosticLogger::instance()->info('Rate limit reset', [
            'client_id' => $client_id,
            'endpoint' => $endpoint,
            'reset_by' => get_current_user_id()
        ], 'api_rate_limit');
        
        return true;
    }
    
    /**
     * Get rate limit statistics
     * 
     * @return array
     */
    public function get_rate_limit_statistics() {
        global $wpdb;
        
        // This would require storing rate limit data in database for persistent statistics
        // For now, return basic stats from transients
        
        $stats = [
            'total_clients' => 0,
            'blocked_requests_today' => 0,
            'top_clients' => [],
            'top_endpoints' => [],
            'client_types' => [
                'anonymous' => 0,
                'authenticated' => 0,
                'api_key' => 0,
                'premium' => 0
            ]
        ];
        
        // Get stats from diagnostic logs
        $log_entries = DiagnosticLogger::instance()->get_recent_logs('api_rate_limit', 24); // Last 24 hours
        
        foreach ($log_entries as $entry) {
            if (isset($entry['context']['client_id'])) {
                $stats['total_clients']++;
                
                if ($entry['level'] === 'warning') {
                    $stats['blocked_requests_today']++;
                }
                
                $client_type = $entry['context']['client_type'] ?? 'unknown';
                if (isset($stats['client_types'][$client_type])) {
                    $stats['client_types'][$client_type]++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Cleanup expired rate limit data
     */
    public function cleanup_expired_data() {
        // This is automatically handled by WordPress cache expiration
        // But we can also clean up persistent storage if implemented
        
        DiagnosticLogger::instance()->debug('Rate limit data cleanup completed', [], 'api_rate_limit');
    }
    
    /**
     * Add rate limit management page to admin
     */
    public function add_rate_limit_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_submenu_page(
            'wcefp-dashboard',
            __('API Rate Limits', 'wceventsfp'),
            __('Rate Limits', 'wceventsfp'),
            'manage_options',
            'wcefp-rate-limits',
            [$this, 'render_rate_limit_admin_page']
        );
    }
    
    /**
     * Render rate limit admin page
     */
    public function render_rate_limit_admin_page() {
        // Enqueue admin scripts and styles
        wp_enqueue_script('wcefp-admin-settings', WCEFP_PLUGIN_URL . 'assets/js/admin-settings.js', ['jquery'], WCEFP_VERSION, true);
        wp_enqueue_style('wcefp-admin-rate-limits', WCEFP_PLUGIN_URL . 'assets/css/admin-rate-limits.css', [], WCEFP_VERSION);
        wp_localize_script('wcefp-admin-settings', 'wcefpSettings', [
            'nonce' => wp_create_nonce('wcefp_rate_limits'),
            'strings' => [
                'resetRateLimitsConfirm' => __('Reset all rate limits to default values?', 'wceventsfp')
            ]
        ]);
        
        $stats = $this->get_rate_limit_statistics();
        $current_limits = get_option('wcefp_rate_limits', []);
        
        ?>
        <div class="wrap wcefp-rate-limits">
            <h1><?php _e('API Rate Limit Management', 'wceventsfp'); ?></h1>
            
            <div class="wcefp-rate-limit-stats">
                <h2><?php _e('Current Statistics', 'wceventsfp'); ?></h2>
                <div class="stat-boxes">
                    <div class="stat-box">
                        <h3><?php _e('Total Clients (24h)', 'wceventsfp'); ?></h3>
                        <span class="stat-number"><?php echo esc_html($stats['total_clients']); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Blocked Requests (24h)', 'wceventsfp'); ?></h3>
                        <span class="stat-number"><?php echo esc_html($stats['blocked_requests_today']); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Anonymous Requests', 'wceventsfp'); ?></h3>
                        <span class="stat-number"><?php echo esc_html($stats['client_types']['anonymous']); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Authenticated Requests', 'wceventsfp'); ?></h3>
                        <span class="stat-number"><?php echo esc_html($stats['client_types']['authenticated']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="wcefp-rate-limit-settings">
                <h2><?php _e('Rate Limit Configuration', 'wceventsfp'); ?></h2>
                <form method="post" action="" id="wcefp-rate-limits-form">
                    <?php wp_nonce_field('wcefp_rate_limits', '_wpnonce'); ?>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Client Type', 'wceventsfp'); ?></th>
                                <th><?php _e('Requests per Hour', 'wceventsfp'); ?></th>
                                <th><?php _e('Window (seconds)', 'wceventsfp'); ?></th>
                                <th><?php _e('Status', 'wceventsfp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (self::DEFAULT_LIMITS as $type => $default_limit): ?>
                            <?php
                            $current_limit = $current_limits[$type] ?? $default_limit;
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $type))); ?></strong></td>
                                <td>
                                    <input type="number" 
                                           name="limits[<?php echo esc_attr($type); ?>][requests]" 
                                           value="<?php echo esc_attr($current_limit['requests']); ?>"
                                           min="1" 
                                           max="100000" 
                                           class="small-text">
                                </td>
                                <td>
                                    <input type="number" 
                                           name="limits[<?php echo esc_attr($type); ?>][window]" 
                                           value="<?php echo esc_attr($current_limit['window']); ?>"
                                           min="60" 
                                           max="86400" 
                                           class="small-text">
                                </td>
                                <td>
                                    <span class="status-indicator status-active"><?php _e('Active', 'wceventsfp'); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="save-rate-limits">
                            <?php _e('Save Rate Limits', 'wceventsfp'); ?>
                        </button>
                        <button type="button" class="button" id="reset-rate-limits">
                            <?php _e('Reset to Defaults', 'wceventsfp'); ?>
                        </button>
                    </p>
                </form>
            </div>
            
            <div class="wcefp-endpoint-limits">
                <h2><?php _e('Endpoint-Specific Limits', 'wceventsfp'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Endpoint', 'wceventsfp'); ?></th>
                            <th><?php _e('Requests per Hour', 'wceventsfp'); ?></th>
                            <th><?php _e('Description', 'wceventsfp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (self::ENDPOINT_LIMITS as $endpoint => $limit): ?>
                        <tr>
                            <td><code><?php echo esc_html($endpoint); ?></code></td>
                            <td><?php echo esc_html($limit['requests']); ?></td>
                            <td><?php echo esc_html($this->get_endpoint_description($endpoint)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
        .wcefp-rate-limits .stat-boxes {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .wcefp-rate-limits .stat-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            flex: 1;
        }
        
        .wcefp-rate-limits .stat-box h3 {
            margin-top: 0;
            color: #666;
            font-size: 14px;
        }
        
        .wcefp-rate-limits .stat-box .stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .wcefp-rate-limits .status-indicator {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .wcefp-rate-limits .status-active {
            background: #d4edda;
            color: #155724;
        }
        </style>
        <?php
    }
    
    /**
     * Get endpoint description for admin display
     * 
     * @param string $endpoint
     * @return string
     */
    private function get_endpoint_description($endpoint) {
        $descriptions = [
            '/bookings' => __('Booking management operations', 'wceventsfp'),
            '/analytics' => __('Analytics and reporting data', 'wceventsfp'),
            '/export' => __('Data export operations (resource intensive)', 'wceventsfp'),
        ];
        
        return $descriptions[$endpoint] ?? __('API endpoint', 'wceventsfp');
    }
    
    /**
     * AJAX handler for updating rate limits
     */
    public function ajax_update_rate_limits() {
        check_ajax_referer('wcefp_rate_limits', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wceventsfp'));
        }
        
        if (isset($_POST['reset'])) {
            delete_option('wcefp_rate_limits');
            wp_send_json_success(__('Rate limits reset to defaults', 'wceventsfp'));
            return;
        }
        
        $limits = $_POST['limits'] ?? [];
        $sanitized_limits = [];
        
        foreach ($limits as $type => $limit) {
            if (isset(self::DEFAULT_LIMITS[$type])) {
                $sanitized_limits[$type] = [
                    'requests' => max(1, absint($limit['requests'])),
                    'window' => max(60, absint($limit['window']))
                ];
            }
        }
        
        update_option('wcefp_rate_limits', $sanitized_limits, false);
        
        DiagnosticLogger::instance()->info('Rate limits updated', [
            'new_limits' => $sanitized_limits,
            'updated_by' => get_current_user_id()
        ], 'api_rate_limit');
        
        wp_send_json_success(__('Rate limits updated successfully', 'wceventsfp'));
    }
}