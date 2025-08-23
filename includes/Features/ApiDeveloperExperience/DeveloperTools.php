<?php
/**
 * Developer Tools
 * 
 * Advanced developer tools for WCEFP API including debugging utilities,
 * performance monitoring, and development aids.
 *
 * @package WCEFP\Features\ApiDeveloperExperience
 * @since 2.2.0
 */

namespace WCEFP\Features\ApiDeveloperExperience;

use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Developer Tools and Utilities
 */
class DeveloperTools {
    
    /**
     * Initialize developer tools
     */
    public function init() {
        // Only load developer tools for users with appropriate capabilities
        if (!$this->should_load_dev_tools()) {
            return;
        }
        
        // Add developer tools menu
        add_action('admin_menu', [$this, 'add_developer_tools_page']);
        
        // AJAX handlers
        add_action('wp_ajax_wcefp_test_api_endpoint', [$this, 'ajax_test_api_endpoint']);
        add_action('wp_ajax_wcefp_generate_api_key', [$this, 'ajax_generate_api_key']);
        add_action('wp_ajax_wcefp_validate_api_schema', [$this, 'ajax_validate_api_schema']);
        add_action('wp_ajax_wcefp_clear_api_cache', [$this, 'ajax_clear_api_cache']);
        
        // Development mode features
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('rest_api_init', [$this, 'add_debug_headers']);
            add_filter('rest_post_dispatch', [$this, 'add_debug_response_data'], 10, 3);
        }
        
        // API monitoring hooks
        add_action('rest_api_init', [$this, 'start_performance_monitoring']);
        
        DiagnosticLogger::instance()->debug('Developer Tools initialized', [], 'api_features');
    }
    
    /**
     * Check if developer tools should be loaded
     */
    private function should_load_dev_tools() {
        return current_user_can('manage_options') || current_user_can('use_wcefp_dev_tools');
    }
    
    /**
     * Add developer tools admin page
     */
    public function add_developer_tools_page() {
        add_submenu_page(
            'wcefp-dashboard',
            __('Developer Tools', 'wceventsfp'),
            __('Dev Tools', 'wceventsfp'),
            'use_wcefp_dev_tools',
            'wcefp-dev-tools',
            [$this, 'render_developer_tools_page']
        );
    }
    
    /**
     * Render developer tools page
     */
    public function render_developer_tools_page() {
        $api_base_url = rest_url('wcefp/v2/');
        $api_logs = $this->get_recent_api_logs();
        $performance_stats = $this->get_performance_statistics();
        
        ?>
        <div class="wrap wcefp-dev-tools">
            <h1><?php _e('WCEventsFP Developer Tools', 'wceventsfp'); ?></h1>
            
            <div class="dev-tools-nav">
                <nav class="nav-tab-wrapper">
                    <a href="#api-tester" class="nav-tab nav-tab-active"><?php _e('API Tester', 'wceventsfp'); ?></a>
                    <a href="#performance" class="nav-tab"><?php _e('Performance', 'wceventsfp'); ?></a>
                    <a href="#logs" class="nav-tab"><?php _e('API Logs', 'wceventsfp'); ?></a>
                    <a href="#schema-validator" class="nav-tab"><?php _e('Schema Validator', 'wceventsfp'); ?></a>
                    <a href="#cache-tools" class="nav-tab"><?php _e('Cache Tools', 'wceventsfp'); ?></a>
                </nav>
            </div>
            
            <!-- API Tester Tab -->
            <div id="api-tester" class="tab-content active">
                <h2><?php _e('API Endpoint Tester', 'wceventsfp'); ?></h2>
                <p><?php _e('Test API endpoints directly from the admin panel with real-time responses and debugging information.', 'wceventsfp'); ?></p>
                
                <div class="api-tester-form">
                    <form id="api-test-form">
                        <div class="form-row">
                            <label><?php _e('HTTP Method:', 'wceventsfp'); ?></label>
                            <select id="api-method">
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <label><?php _e('Endpoint:', 'wceventsfp'); ?></label>
                            <input type="text" id="api-endpoint" value="/bookings" placeholder="/bookings">
                            <span class="description"><?php printf(__('Base URL: %s', 'wceventsfp'), esc_html($api_base_url)); ?></span>
                        </div>
                        
                        <div class="form-row">
                            <label><?php _e('Headers:', 'wceventsfp'); ?></label>
                            <textarea id="api-headers" rows="4" placeholder='{"X-WCEFP-API-Key": "your-api-key"}'></textarea>
                        </div>
                        
                        <div class="form-row" id="api-body-row" style="display:none;">
                            <label><?php _e('Request Body:', 'wceventsfp'); ?></label>
                            <textarea id="api-body" rows="6" placeholder='{"event_id": 123, "customer_email": "test@example.com"}'></textarea>
                        </div>
                        
                        <div class="form-row">
                            <button type="submit" class="button button-primary"><?php _e('Test Endpoint', 'wceventsfp'); ?></button>
                            <button type="button" class="button" id="clear-test"><?php _e('Clear', 'wceventsfp'); ?></button>
                        </div>
                    </form>
                </div>
                
                <div id="api-test-results">
                    <h3><?php _e('Response', 'wceventsfp'); ?></h3>
                    <div id="api-response-content">
                        <p class="no-results"><?php _e('No test results yet. Use the form above to test an API endpoint.', 'wceventsfp'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Performance Tab -->
            <div id="performance" class="tab-content">
                <h2><?php _e('API Performance Monitoring', 'wceventsfp'); ?></h2>
                
                <div class="performance-stats">
                    <div class="stat-box">
                        <h3><?php _e('Average Response Time', 'wceventsfp'); ?></h3>
                        <span class="stat-number"><?php echo esc_html($performance_stats['avg_response_time']); ?>ms</span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Total Requests (24h)', 'wceventsfp'); ?></h3>
                        <span class="stat-number"><?php echo esc_html($performance_stats['total_requests']); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Error Rate', 'wceventsfp'); ?></h3>
                        <span class="stat-number"><?php echo esc_html($performance_stats['error_rate']); ?>%</span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Cache Hit Rate', 'wceventsfp'); ?></h3>
                        <span class="stat-number"><?php echo esc_html($performance_stats['cache_hit_rate']); ?>%</span>
                    </div>
                </div>
                
                <div class="performance-chart">
                    <h3><?php _e('Response Time Trends', 'wceventsfp'); ?></h3>
                    <canvas id="performance-chart" width="800" height="300"></canvas>
                </div>
                
                <div class="slow-queries">
                    <h3><?php _e('Slowest API Endpoints (Last 24h)', 'wceventsfp'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Endpoint', 'wceventsfp'); ?></th>
                                <th><?php _e('Average Time', 'wceventsfp'); ?></th>
                                <th><?php _e('Max Time', 'wceventsfp'); ?></th>
                                <th><?php _e('Request Count', 'wceventsfp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performance_stats['slow_endpoints'] as $endpoint): ?>
                            <tr>
                                <td><code><?php echo esc_html($endpoint['path']); ?></code></td>
                                <td><?php echo esc_html($endpoint['avg_time']); ?>ms</td>
                                <td><?php echo esc_html($endpoint['max_time']); ?>ms</td>
                                <td><?php echo esc_html($endpoint['count']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- API Logs Tab -->
            <div id="logs" class="tab-content">
                <h2><?php _e('API Request Logs', 'wceventsfp'); ?></h2>
                
                <div class="logs-filters">
                    <select id="log-level-filter">
                        <option value=""><?php _e('All Levels', 'wceventsfp'); ?></option>
                        <option value="error"><?php _e('Errors Only', 'wceventsfp'); ?></option>
                        <option value="warning"><?php _e('Warnings Only', 'wceventsfp'); ?></option>
                        <option value="info"><?php _e('Info Only', 'wceventsfp'); ?></option>
                    </select>
                    
                    <select id="log-endpoint-filter">
                        <option value=""><?php _e('All Endpoints', 'wceventsfp'); ?></option>
                        <option value="/bookings">/bookings</option>
                        <option value="/analytics">/analytics</option>
                        <option value="/export">/export</option>
                    </select>
                    
                    <button class="button" id="refresh-logs"><?php _e('Refresh', 'wceventsfp'); ?></button>
                    <button class="button" id="clear-logs"><?php _e('Clear Logs', 'wceventsfp'); ?></button>
                </div>
                
                <div class="api-logs-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Timestamp', 'wceventsfp'); ?></th>
                                <th><?php _e('Level', 'wceventsfp'); ?></th>
                                <th><?php _e('Method', 'wceventsfp'); ?></th>
                                <th><?php _e('Endpoint', 'wceventsfp'); ?></th>
                                <th><?php _e('Status', 'wceventsfp'); ?></th>
                                <th><?php _e('Response Time', 'wceventsfp'); ?></th>
                                <th><?php _e('Details', 'wceventsfp'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="api-logs-tbody">
                            <?php foreach ($api_logs as $log): ?>
                            <tr class="log-entry log-<?php echo esc_attr($log['level']); ?>">
                                <td><?php echo esc_html($log['timestamp']); ?></td>
                                <td><span class="log-level-badge log-<?php echo esc_attr($log['level']); ?>"><?php echo esc_html(strtoupper($log['level'])); ?></span></td>
                                <td><?php echo esc_html($log['method']); ?></td>
                                <td><code><?php echo esc_html($log['endpoint']); ?></code></td>
                                <td><?php echo esc_html($log['status']); ?></td>
                                <td><?php echo esc_html($log['response_time']); ?>ms</td>
                                <td>
                                    <button class="button button-small toggle-details" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                        <?php _e('Details', 'wceventsfp'); ?>
                                    </button>
                                </td>
                            </tr>
                            <tr class="log-details" id="log-details-<?php echo esc_attr($log['id']); ?>" style="display:none;">
                                <td colspan="7">
                                    <pre><?php echo esc_html(json_encode($log['details'], JSON_PRETTY_PRINT)); ?></pre>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Schema Validator Tab -->
            <div id="schema-validator" class="tab-content">
                <h2><?php _e('API Schema Validator', 'wceventsfp'); ?></h2>
                <p><?php _e('Validate API requests and responses against the OpenAPI schema.', 'wceventsfp'); ?></p>
                
                <div class="schema-validator-form">
                    <form id="schema-validation-form">
                        <div class="form-row">
                            <label><?php _e('Validation Type:', 'wceventsfp'); ?></label>
                            <select id="validation-type">
                                <option value="request"><?php _e('Request Validation', 'wceventsfp'); ?></option>
                                <option value="response"><?php _e('Response Validation', 'wceventsfp'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <label><?php _e('Endpoint:', 'wceventsfp'); ?></label>
                            <select id="validation-endpoint">
                                <option value="/bookings">/bookings</option>
                                <option value="/analytics">/analytics</option>
                                <option value="/export">/export</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <label><?php _e('HTTP Method:', 'wceventsfp'); ?></label>
                            <select id="validation-method">
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <label><?php _e('JSON Data:', 'wceventsfp'); ?></label>
                            <textarea id="validation-data" rows="8" placeholder='{"event_id": 123, "customer_email": "test@example.com"}'></textarea>
                        </div>
                        
                        <div class="form-row">
                            <button type="submit" class="button button-primary"><?php _e('Validate Schema', 'wceventsfp'); ?></button>
                        </div>
                    </form>
                </div>
                
                <div id="validation-results">
                    <h3><?php _e('Validation Results', 'wceventsfp'); ?></h3>
                    <div id="validation-content">
                        <p class="no-results"><?php _e('No validation results yet.', 'wceventsfp'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Cache Tools Tab -->
            <div id="cache-tools" class="tab-content">
                <h2><?php _e('API Cache Management', 'wceventsfp'); ?></h2>
                
                <div class="cache-stats">
                    <div class="stat-box">
                        <h3><?php _e('Cache Hit Rate', 'wceventsfp'); ?></h3>
                        <span class="stat-number"><?php echo esc_html($performance_stats['cache_hit_rate']); ?>%</span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Cached Objects', 'wceventsfp'); ?></h3>
                        <span class="stat-number"><?php echo esc_html($this->get_cache_object_count()); ?></span>
                    </div>
                    <div class="stat-box">
                        <h3><?php _e('Cache Size', 'wceventsfp'); ?></h3>
                        <span class="stat-number"><?php echo esc_html($this->get_cache_size_formatted()); ?></span>
                    </div>
                </div>
                
                <div class="cache-actions">
                    <h3><?php _e('Cache Actions', 'wceventsfp'); ?></h3>
                    <button class="button button-primary" id="clear-api-cache"><?php _e('Clear API Cache', 'wceventsfp'); ?></button>
                    <button class="button" id="warm-cache"><?php _e('Warm Cache', 'wceventsfp'); ?></button>
                    <button class="button" id="cache-stats-refresh"><?php _e('Refresh Stats', 'wceventsfp'); ?></button>
                </div>
                
                <div class="cached-objects">
                    <h3><?php _e('Cached API Objects', 'wceventsfp'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Cache Key', 'wceventsfp'); ?></th>
                                <th><?php _e('Type', 'wceventsfp'); ?></th>
                                <th><?php _e('Size', 'wceventsfp'); ?></th>
                                <th><?php _e('TTL', 'wceventsfp'); ?></th>
                                <th><?php _e('Actions', 'wceventsfp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->get_cached_api_objects() as $cache_item): ?>
                            <tr>
                                <td><code><?php echo esc_html($cache_item['key']); ?></code></td>
                                <td><?php echo esc_html($cache_item['type']); ?></td>
                                <td><?php echo esc_html($cache_item['size']); ?></td>
                                <td><?php echo esc_html($cache_item['ttl']); ?>s</td>
                                <td>
                                    <button class="button button-small delete-cache-item" data-cache-key="<?php echo esc_attr($cache_item['key']); ?>">
                                        <?php _e('Delete', 'wceventsfp'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <script>
        // Tab switching functionality
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                const target = this.getAttribute('href').substring(1);
                
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('nav-tab-active'));
                this.classList.add('nav-tab-active');
                
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(target).classList.add('active');
            });
        });
        
        // API Tester functionality
        document.getElementById('api-method').addEventListener('change', function() {
            const bodyRow = document.getElementById('api-body-row');
            bodyRow.style.display = ['POST', 'PUT', 'PATCH'].includes(this.value) ? 'block' : 'none';
        });
        
        document.getElementById('api-test-form').addEventListener('submit', function(e) {
            e.preventDefault();
            testApiEndpoint();
        });
        
        document.getElementById('clear-test').addEventListener('click', function() {
            document.getElementById('api-endpoint').value = '/bookings';
            document.getElementById('api-headers').value = '';
            document.getElementById('api-body').value = '';
            document.getElementById('api-response-content').innerHTML = '<p class="no-results">No test results yet.</p>';
        });
        
        // Schema validation
        document.getElementById('schema-validation-form').addEventListener('submit', function(e) {
            e.preventDefault();
            validateApiSchema();
        });
        
        // Cache management
        document.getElementById('clear-api-cache').addEventListener('click', function() {
            clearApiCache();
        });
        
        // Log management
        document.querySelectorAll('.toggle-details').forEach(button => {
            button.addEventListener('click', function() {
                const logId = this.dataset.logId;
                const detailsRow = document.getElementById('log-details-' + logId);
                detailsRow.style.display = detailsRow.style.display === 'none' ? 'table-row' : 'none';
            });
        });
        
        async function testApiEndpoint() {
            const method = document.getElementById('api-method').value;
            const endpoint = document.getElementById('api-endpoint').value;
            const headersText = document.getElementById('api-headers').value;
            const bodyText = document.getElementById('api-body').value;
            
            const formData = new FormData();
            formData.append('action', 'wcefp_test_api_endpoint');
            formData.append('method', method);
            formData.append('endpoint', endpoint);
            formData.append('headers', headersText);
            formData.append('body', bodyText);
            formData.append('_wpnonce', '<?php echo wp_create_nonce('wcefp_dev_tools'); ?>');
            
            try {
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                displayApiTestResults(result);
            } catch (error) {
                displayApiTestResults({success: false, data: {error: error.message}});
            }
        }
        
        function displayApiTestResults(result) {
            const container = document.getElementById('api-response-content');
            
            if (result.success) {
                const data = result.data;
                container.innerHTML = 
                    '<div class="test-result success">' +
                    '<h4>Success - Status: ' + data.status + '</h4>' +
                    '<p><strong>Response Time:</strong> ' + data.response_time + 'ms</p>' +
                    '<p><strong>Headers:</strong></p>' +
                    '<pre>' + JSON.stringify(data.headers, null, 2) + '</pre>' +
                    '<p><strong>Response Body:</strong></p>' +
                    '<pre>' + JSON.stringify(data.body, null, 2) + '</pre>' +
                    '</div>';
            } else {
                container.innerHTML = 
                    '<div class="test-result error">' +
                    '<h4>Error</h4>' +
                    '<pre>' + JSON.stringify(result.data, null, 2) + '</pre>' +
                    '</div>';
            }
        }
        
        async function validateApiSchema() {
            // Implementation for schema validation
            console.log('Validating API schema...');
        }
        
        async function clearApiCache() {
            const formData = new FormData();
            formData.append('action', 'wcefp_clear_api_cache');
            formData.append('_wpnonce', '<?php echo wp_create_nonce('wcefp_dev_tools'); ?>');
            
            try {
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('API cache cleared successfully');
                    location.reload();
                } else {
                    alert('Failed to clear cache: ' + result.data);
                }
            } catch (error) {
                alert('Error clearing cache: ' + error.message);
            }
        }
        </script>
        
        <style>
        .wcefp-dev-tools .dev-tools-nav {
            margin: 20px 0;
        }
        
        .wcefp-dev-tools .tab-content {
            display: none;
            padding: 20px 0;
        }
        
        .wcefp-dev-tools .tab-content.active {
            display: block;
        }
        
        .wcefp-dev-tools .form-row {
            margin: 15px 0;
        }
        
        .wcefp-dev-tools .form-row label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .wcefp-dev-tools .form-row input,
        .wcefp-dev-tools .form-row select,
        .wcefp-dev-tools .form-row textarea {
            width: 100%;
            max-width: 600px;
        }
        
        .wcefp-dev-tools .performance-stats,
        .wcefp-dev-tools .cache-stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .wcefp-dev-tools .stat-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            flex: 1;
        }
        
        .wcefp-dev-tools .stat-box h3 {
            margin-top: 0;
            color: #666;
            font-size: 14px;
        }
        
        .wcefp-dev-tools .stat-box .stat-number {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .wcefp-dev-tools .test-result {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .wcefp-dev-tools .test-result.success {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        
        .wcefp-dev-tools .test-result.error {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .wcefp-dev-tools .log-level-badge {
            padding: 2px 6px;
            border-radius: 3px;
            color: white;
            font-size: 11px;
            font-weight: bold;
        }
        
        .wcefp-dev-tools .log-level-badge.log-error {
            background: #dc3545;
        }
        
        .wcefp-dev-tools .log-level-badge.log-warning {
            background: #ffc107;
            color: #333;
        }
        
        .wcefp-dev-tools .log-level-badge.log-info {
            background: #17a2b8;
        }
        
        .wcefp-dev-tools .log-level-badge.log-debug {
            background: #6c757d;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for testing API endpoints
     */
    public function ajax_test_api_endpoint() {
        check_ajax_referer('wcefp_dev_tools', '_wpnonce');
        
        if (!current_user_can('use_wcefp_dev_tools')) {
            wp_send_json_error(__('Permission denied', 'wceventsfp'));
        }
        
        $method = sanitize_text_field($_POST['method']);
        $endpoint = sanitize_text_field($_POST['endpoint']);
        $headers_json = sanitize_textarea_field($_POST['headers']);
        $body_json = sanitize_textarea_field($_POST['body']);
        
        // Parse headers
        $headers = [];
        if (!empty($headers_json)) {
            $parsed_headers = json_decode($headers_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $headers = $parsed_headers;
            }
        }
        
        // Build full URL
        $base_url = rest_url('wcefp/v2');
        $full_url = trailingslashit($base_url) . ltrim($endpoint, '/');
        
        // Prepare request arguments
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
        ];
        
        // Add body for POST/PUT requests
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($body_json)) {
            $args['body'] = $body_json;
            if (!isset($headers['Content-Type'])) {
                $args['headers']['Content-Type'] = 'application/json';
            }
        }
        
        $start_time = microtime(true);
        
        try {
            $response = wp_remote_request($full_url, $args);
            $end_time = microtime(true);
            
            if (is_wp_error($response)) {
                wp_send_json_error([
                    'error' => $response->get_error_message(),
                    'url' => $full_url,
                    'method' => $method
                ]);
            }
            
            $status = wp_remote_retrieve_response_code($response);
            $response_headers = wp_remote_retrieve_headers($response);
            $body = wp_remote_retrieve_body($response);
            
            // Try to decode JSON response
            $decoded_body = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded_body = $body;
            }
            
            wp_send_json_success([
                'status' => $status,
                'headers' => $response_headers->getAll(),
                'body' => $decoded_body,
                'response_time' => round(($end_time - $start_time) * 1000, 2),
                'url' => $full_url,
                'method' => $method
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'error' => $e->getMessage(),
                'url' => $full_url,
                'method' => $method
            ]);
        }
    }
    
    /**
     * AJAX handler for generating API keys
     */
    public function ajax_generate_api_key() {
        check_ajax_referer('wcefp_api_key', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wceventsfp'));
        }
        
        $user_id = get_current_user_id();
        $api_key = wp_generate_password(32, false);
        
        // Store API key for current user
        update_user_meta($user_id, 'wcefp_api_key', $api_key);
        
        DiagnosticLogger::instance()->info('API key generated', [
            'user_id' => $user_id,
            'generated_at' => current_time('mysql')
        ], 'api_auth');
        
        wp_send_json_success([
            'api_key' => $api_key,
            'user_id' => $user_id
        ]);
    }
    
    /**
     * AJAX handler for clearing API cache
     */
    public function ajax_clear_api_cache() {
        check_ajax_referer('wcefp_dev_tools', '_wpnonce');
        
        if (!current_user_can('use_wcefp_dev_tools')) {
            wp_send_json_error(__('Permission denied', 'wceventsfp'));
        }
        
        // Clear various caches
        wp_cache_flush();
        delete_transient('wcefp_openapi_spec');
        
        // Clear rate limit caches
        global $wp_object_cache;
        if (isset($wp_object_cache->cache)) {
            foreach ($wp_object_cache->cache as $group => $cache_group) {
                foreach ($cache_group as $key => $value) {
                    if (strpos($key, 'wcefp_rate_limit_') === 0) {
                        wp_cache_delete($key);
                    }
                }
            }
        }
        
        DiagnosticLogger::instance()->info('API cache cleared via developer tools', [
            'user_id' => get_current_user_id()
        ], 'api_cache');
        
        wp_send_json_success(__('API cache cleared successfully', 'wceventsfp'));
    }
    
    /**
     * Get recent API logs for display
     */
    private function get_recent_api_logs($limit = 50) {
        $logs = DiagnosticLogger::instance()->get_recent_logs('api_requests', 24, $limit);
        
        $formatted_logs = [];
        foreach ($logs as $log) {
            $formatted_logs[] = [
                'id' => hash('crc32', serialize($log)),
                'timestamp' => $log['timestamp'],
                'level' => $log['level'],
                'method' => $log['context']['method'] ?? 'GET',
                'endpoint' => $log['context']['route'] ?? '/',
                'status' => $log['context']['status'] ?? 200,
                'response_time' => $log['context']['data_size'] ?? 0, // Placeholder
                'details' => $log['context']
            ];
        }
        
        return $formatted_logs;
    }
    
    /**
     * Get API performance statistics
     */
    private function get_performance_statistics() {
        // This would typically come from stored performance data
        // For now, return mock data
        
        return [
            'avg_response_time' => 245,
            'total_requests' => 1247,
            'error_rate' => 2.1,
            'cache_hit_rate' => 78,
            'slow_endpoints' => [
                [
                    'path' => '/analytics',
                    'avg_time' => 892,
                    'max_time' => 1450,
                    'count' => 45
                ],
                [
                    'path' => '/export/csv',
                    'avg_time' => 756,
                    'max_time' => 1200,
                    'count' => 12
                ],
                [
                    'path' => '/bookings',
                    'avg_time' => 234,
                    'max_time' => 450,
                    'count' => 890
                ]
            ]
        ];
    }
    
    /**
     * Get cached API objects
     */
    private function get_cached_api_objects() {
        // Mock data - in real implementation, would scan cache for WCEFP objects
        return [
            [
                'key' => 'wcefp_rate_limit_user_1',
                'type' => 'Rate Limit',
                'size' => '1.2KB',
                'ttl' => 3456
            ],
            [
                'key' => 'wcefp_openapi_spec',
                'type' => 'Documentation',
                'size' => '45.6KB',
                'ttl' => 2890
            ],
            [
                'key' => 'wcefp_bookings_query_cache',
                'type' => 'Query Cache',
                'size' => '12.3KB',
                'ttl' => 567
            ]
        ];
    }
    
    /**
     * Get cache object count
     */
    private function get_cache_object_count() {
        // Mock implementation
        return 24;
    }
    
    /**
     * Get formatted cache size
     */
    private function get_cache_size_formatted() {
        // Mock implementation
        return '156KB';
    }
    
    /**
     * Add debug headers to API responses
     */
    public function add_debug_headers() {
        add_filter('rest_post_dispatch', function($response, $server, $request) {
            if (strpos($request->get_route(), '/wcefp/') === 0) {
                $response->header('X-Debug-Mode', 'true');
                $response->header('X-Debug-Time', current_time('c'));
                $response->header('X-Debug-Memory', size_format(memory_get_usage(true)));
            }
            return $response;
        }, 10, 3);
    }
    
    /**
     * Add debug data to API responses
     */
    public function add_debug_response_data($response, $server, $request) {
        if (strpos($request->get_route(), '/wcefp/') === 0 && $response instanceof \WP_REST_Response) {
            $data = $response->get_data();
            
            if (is_array($data)) {
                $data['_debug'] = [
                    'timestamp' => current_time('c'),
                    'memory_usage' => memory_get_usage(true),
                    'query_count' => get_num_queries(),
                    'cache_hits' => wp_cache_get_stats()
                ];
                
                $response->set_data($data);
            }
        }
        
        return $response;
    }
    
    /**
     * Start performance monitoring for API requests
     */
    public function start_performance_monitoring() {
        // Hook into the beginning of REST API dispatch to start timing
        add_action('rest_dispatch_request', function($dispatch_result, $request, $route, $handler) {
            if (strpos($route, '/wcefp/') === 0) {
                $GLOBALS['wcefp_api_start_time'] = microtime(true);
            }
            return $dispatch_result;
        }, 10, 4);
    }
}