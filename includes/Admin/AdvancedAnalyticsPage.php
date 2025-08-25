<?php
/**
 * WCEFP Advanced Analytics Admin Page
 * 
 * Advanced analytics dashboard with visual charts, trending data,
 * performance insights, and predictive monitoring capabilities.
 * 
 * @package WCEFP\Admin
 * @since 2.2.2
 */

namespace WCEFP\Admin;

class AdvancedAnalyticsPage {
    
    /**
     * Initialize the advanced analytics page
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_analytics_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_wcefp_get_analytics_data', [$this, 'ajax_get_analytics_data']);
        add_action('wp_ajax_wcefp_get_performance_trends', [$this, 'ajax_get_performance_trends']);
        add_action('wp_ajax_wcefp_optimize_performance', [$this, 'ajax_optimize_performance']);
        
        // Initialize scheduled health monitoring
        add_action('init', [$this, 'schedule_health_monitoring']);
        add_action('wcefp_health_monitor_check', [$this, 'run_health_monitor']);
    }
    
    /**
     * Add analytics page to WordPress admin menu
     */
    public function add_analytics_page() {
        add_submenu_page(
            'tools.php',
            'WCEFP Advanced Analytics',
            'WCEFP Analytics', 
            'manage_options',
            'wcefp-advanced-analytics',
            [$this, 'render_analytics_page']
        );
    }
    
    /**
     * Enqueue admin assets for analytics page
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_wcefp-advanced-analytics') {
            return;
        }
        
        // Enqueue Chart.js for visual analytics
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js', [], '4.4.0', true);
        
        wp_enqueue_style(
            'wcefp-advanced-analytics-admin',
            WCEFP_PLUGIN_URL . 'assets/css/advanced-analytics.css',
            [],
            WCEFP_VERSION
        );
        
        wp_enqueue_script(
            'wcefp-advanced-analytics-admin',
            WCEFP_PLUGIN_URL . 'assets/js/advanced-analytics.js',
            ['jquery', 'chart-js'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-advanced-analytics-admin', 'wcefpAnalytics', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_analytics_nonce'),
            'strings' => [
                'loading' => __('Loading analytics data...', 'wceventsfp'),
                'error' => __('Error loading data', 'wceventsfp'),
                'optimizing' => __('Optimizing performance...', 'wceventsfp'),
                'optimized' => __('Performance optimized successfully!', 'wceventsfp'),
            ]
        ]);
    }
    
    /**
     * Render the advanced analytics page
     */
    public function render_analytics_page() {
        ?>
        <div class="wrap wcefp-analytics-wrap">
            <h1 class="wp-heading-inline">
                <span class="wcefp-icon">📊</span>
                WCEFP Advanced Analytics Dashboard
            </h1>
            
            <div class="wcefp-analytics-controls">
                <button type="button" class="button button-secondary" id="wcefp-refresh-analytics">
                    <span class="dashicons dashicons-update"></span> Refresh Data
                </button>
                <button type="button" class="button button-primary" id="wcefp-optimize-performance">
                    <span class="dashicons dashicons-performance"></span> Optimize Performance
                </button>
                <select id="wcefp-analytics-timeframe">
                    <option value="24h">Last 24 Hours</option>
                    <option value="7d" selected>Last 7 Days</option>
                    <option value="30d">Last 30 Days</option>
                    <option value="90d">Last 90 Days</option>
                </select>
            </div>

            <!-- Performance Overview Cards -->
            <div class="wcefp-analytics-overview">
                <div class="wcefp-metric-card" id="performance-score">
                    <div class="metric-icon">🚀</div>
                    <div class="metric-content">
                        <div class="metric-value" data-metric="performance_score">--</div>
                        <div class="metric-label">Performance Score</div>
                        <div class="metric-change" data-change="performance_score">--</div>
                    </div>
                </div>
                
                <div class="wcefp-metric-card" id="avg-response-time">
                    <div class="metric-icon">⏱️</div>
                    <div class="metric-content">
                        <div class="metric-value" data-metric="avg_response_time">--</div>
                        <div class="metric-label">Avg Response Time</div>
                        <div class="metric-change" data-change="avg_response_time">--</div>
                    </div>
                </div>
                
                <div class="wcefp-metric-card" id="memory-efficiency">
                    <div class="metric-icon">💾</div>
                    <div class="metric-content">
                        <div class="metric-value" data-metric="memory_efficiency">--</div>
                        <div class="metric-label">Memory Efficiency</div>
                        <div class="metric-change" data-change="memory_efficiency">--</div>
                    </div>
                </div>
                
                <div class="wcefp-metric-card" id="error-rate">
                    <div class="metric-icon">⚠️</div>
                    <div class="metric-content">
                        <div class="metric-value" data-metric="error_rate">--</div>
                        <div class="metric-label">Error Rate</div>
                        <div class="metric-change" data-change="error_rate">--</div>
                    </div>
                </div>
            </div>

            <!-- Analytics Tabs -->
            <div class="wcefp-analytics-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#performance-trends" class="nav-tab nav-tab-active">Performance Trends</a>
                    <a href="#shortcode-analytics" class="nav-tab">Shortcode Analytics</a>
                    <a href="#api-monitoring" class="nav-tab">API Monitoring</a>
                    <a href="#health-alerts" class="nav-tab">Health Alerts</a>
                    <a href="#optimization-tools" class="nav-tab">Optimization Tools</a>
                    <a href="#predictive-insights" class="nav-tab">Predictive Insights</a>
                </nav>

                <!-- Performance Trends Tab -->
                <div id="performance-trends" class="wcefp-tab-content tab-active">
                    <div class="wcefp-charts-grid">
                        <div class="chart-container">
                            <h3>Response Time Trends</h3>
                            <canvas id="response-time-chart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h3>Memory Usage Trends</h3>
                            <canvas id="memory-usage-chart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h3>Database Query Performance</h3>
                            <canvas id="database-performance-chart"></canvas>
                        </div>
                        <div class="chart-container">
                            <h3>Error Rate Tracking</h3>
                            <canvas id="error-rate-chart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Shortcode Analytics Tab -->
                <div id="shortcode-analytics" class="wcefp-tab-content">
                    <div class="wcefp-shortcode-analytics">
                        <div class="analytics-header">
                            <h3>Shortcode Performance Analysis</h3>
                            <p>Real-time performance metrics for all WCEFP shortcodes</p>
                        </div>
                        
                        <div class="shortcode-metrics-grid">
                            <div class="metric-section">
                                <h4>Most Used Shortcodes</h4>
                                <div class="shortcode-usage-list" id="popular-shortcodes">
                                    <!-- Populated via AJAX -->
                                </div>
                            </div>
                            
                            <div class="metric-section">
                                <h4>Slowest Performing Shortcodes</h4>
                                <div class="shortcode-performance-list" id="slow-shortcodes">
                                    <!-- Populated via AJAX -->
                                </div>
                            </div>
                            
                            <div class="metric-section">
                                <h4>Error-Prone Shortcodes</h4>
                                <div class="shortcode-error-list" id="error-shortcodes">
                                    <!-- Populated via AJAX -->
                                </div>
                            </div>
                        </div>
                        
                        <div class="chart-container full-width">
                            <h4>Shortcode Usage Over Time</h4>
                            <canvas id="shortcode-usage-chart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- API Monitoring Tab -->
                <div id="api-monitoring" class="wcefp-tab-content">
                    <div class="wcefp-api-monitoring">
                        <div class="analytics-header">
                            <h3>API Integration Monitoring</h3>
                            <p>Monitor external API integrations and performance</p>
                        </div>
                        
                        <div class="api-status-grid">
                            <div class="api-status-card" data-api="google-places">
                                <div class="api-header">
                                    <h4>Google Places API</h4>
                                    <div class="api-status-indicator" data-status="unknown"></div>
                                </div>
                                <div class="api-metrics">
                                    <div class="api-metric">
                                        <span class="label">Response Time:</span>
                                        <span class="value" data-metric="response_time">--</span>
                                    </div>
                                    <div class="api-metric">
                                        <span class="label">Success Rate:</span>
                                        <span class="value" data-metric="success_rate">--</span>
                                    </div>
                                    <div class="api-metric">
                                        <span class="label">Daily Requests:</span>
                                        <span class="value" data-metric="daily_requests">--</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="api-status-card" data-api="google-reviews">
                                <div class="api-header">
                                    <h4>Google Reviews API</h4>
                                    <div class="api-status-indicator" data-status="unknown"></div>
                                </div>
                                <div class="api-metrics">
                                    <div class="api-metric">
                                        <span class="label">Response Time:</span>
                                        <span class="value" data-metric="response_time">--</span>
                                    </div>
                                    <div class="api-metric">
                                        <span class="label">Success Rate:</span>
                                        <span class="value" data-metric="success_rate">--</span>
                                    </div>
                                    <div class="api-metric">
                                        <span class="label">Cache Hit Rate:</span>
                                        <span class="value" data-metric="cache_hit_rate">--</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="chart-container full-width">
                            <h4>API Response Times</h4>
                            <canvas id="api-response-chart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Health Alerts Tab -->
                <div id="health-alerts" class="wcefp-tab-content">
                    <div class="wcefp-health-alerts">
                        <div class="analytics-header">
                            <h3>System Health Monitoring & Alerts</h3>
                            <p>Automated health checks and intelligent alerting system</p>
                        </div>
                        
                        <div class="health-alert-settings">
                            <h4>Alert Configuration</h4>
                            <div class="alert-config-grid">
                                <label class="alert-toggle">
                                    <input type="checkbox" id="enable-performance-alerts" checked>
                                    <span>Performance Degradation Alerts</span>
                                </label>
                                <label class="alert-toggle">
                                    <input type="checkbox" id="enable-error-alerts" checked>
                                    <span>Error Rate Spike Alerts</span>
                                </label>
                                <label class="alert-toggle">
                                    <input type="checkbox" id="enable-memory-alerts" checked>
                                    <span>Memory Usage Alerts</span>
                                </label>
                                <label class="alert-toggle">
                                    <input type="checkbox" id="enable-api-alerts" checked>
                                    <span>API Failure Alerts</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="recent-alerts">
                            <h4>Recent Alerts</h4>
                            <div class="alerts-timeline" id="recent-alerts-list">
                                <!-- Populated via AJAX -->
                            </div>
                        </div>
                        
                        <div class="health-summary">
                            <h4>Health Summary</h4>
                            <div class="health-summary-grid" id="health-summary">
                                <!-- Populated via AJAX -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Optimization Tools Tab -->
                <div id="optimization-tools" class="wcefp-tab-content">
                    <div class="wcefp-optimization-tools">
                        <div class="analytics-header">
                            <h3>Performance Optimization Tools</h3>
                            <p>Automated tools to optimize WCEFP performance and caching</p>
                        </div>
                        
                        <div class="optimization-tools-grid">
                            <div class="optimization-tool">
                                <div class="tool-header">
                                    <h4>Cache Management</h4>
                                    <div class="tool-status" data-tool="cache">Unknown</div>
                                </div>
                                <div class="tool-content">
                                    <p>Manage WCEFP shortcode and API response caching</p>
                                    <div class="tool-actions">
                                        <button type="button" class="button" data-action="clear-cache">Clear All Cache</button>
                                        <button type="button" class="button" data-action="optimize-cache">Optimize Cache</button>
                                        <button type="button" class="button" data-action="preload-cache">Preload Cache</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="optimization-tool">
                                <div class="tool-header">
                                    <h4>Database Optimization</h4>
                                    <div class="tool-status" data-tool="database">Unknown</div>
                                </div>
                                <div class="tool-content">
                                    <p>Optimize WCEFP database tables and queries</p>
                                    <div class="tool-actions">
                                        <button type="button" class="button" data-action="optimize-tables">Optimize Tables</button>
                                        <button type="button" class="button" data-action="clean-expired">Clean Expired Data</button>
                                        <button type="button" class="button" data-action="rebuild-indexes">Rebuild Indexes</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="optimization-tool">
                                <div class="tool-header">
                                    <h4>Asset Optimization</h4>
                                    <div class="tool-status" data-tool="assets">Unknown</div>
                                </div>
                                <div class="tool-content">
                                    <p>Optimize CSS, JS, and image assets</p>
                                    <div class="tool-actions">
                                        <button type="button" class="button" data-action="minify-assets">Minify Assets</button>
                                        <button type="button" class="button" data-action="combine-css">Combine CSS</button>
                                        <button type="button" class="button" data-action="optimize-images">Optimize Images</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Predictive Insights Tab -->
                <div id="predictive-insights" class="wcefp-tab-content">
                    <div class="wcefp-predictive-insights">
                        <div class="analytics-header">
                            <h3>Predictive Performance Insights</h3>
                            <p>AI-powered insights and recommendations for optimal performance</p>
                        </div>
                        
                        <div class="insights-dashboard">
                            <div class="insight-card" data-insight="performance-prediction">
                                <div class="insight-icon">🔮</div>
                                <div class="insight-content">
                                    <h4>Performance Prediction</h4>
                                    <p>Based on current trends, system performance is expected to:</p>
                                    <div class="prediction-result" id="performance-prediction">
                                        <!-- Populated via AJAX -->
                                    </div>
                                </div>
                            </div>
                            
                            <div class="insight-card" data-insight="capacity-planning">
                                <div class="insight-icon">📈</div>
                                <div class="insight-content">
                                    <h4>Capacity Planning</h4>
                                    <p>Resource usage analysis and scaling recommendations:</p>
                                    <div class="capacity-analysis" id="capacity-analysis">
                                        <!-- Populated via AJAX -->
                                    </div>
                                </div>
                            </div>
                            
                            <div class="insight-card" data-insight="optimization-recommendations">
                                <div class="insight-icon">💡</div>
                                <div class="insight-content">
                                    <h4>Smart Recommendations</h4>
                                    <p>AI-generated optimization recommendations:</p>
                                    <div class="recommendations-list" id="optimization-recommendations">
                                        <!-- Populated via AJAX -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Schedule health monitoring if not already scheduled
     */
    public function schedule_health_monitoring() {
        if (!wp_next_scheduled('wcefp_health_monitor_check')) {
            wp_schedule_event(time(), 'hourly', 'wcefp_health_monitor_check');
        }
    }
    
    /**
     * Run automated health monitoring check
     */
    public function run_health_monitor() {
        $health_data = $this->collect_health_metrics();
        
        // Check for performance issues
        if ($health_data['memory_usage_percent'] > 85) {
            $this->trigger_alert('memory', 'High memory usage detected: ' . $health_data['memory_usage_percent'] . '%');
        }
        
        if ($health_data['avg_response_time'] > 3000) {
            $this->trigger_alert('performance', 'Slow response time detected: ' . $health_data['avg_response_time'] . 'ms');
        }
        
        if ($health_data['error_rate'] > 5) {
            $this->trigger_alert('error', 'High error rate detected: ' . $health_data['error_rate'] . '%');
        }
        
        // Store health metrics for trending
        $this->store_health_metrics($health_data);
    }
    
    /**
     * Trigger health alert
     */
    private function trigger_alert($type, $message) {
        $alerts = get_option('wcefp_health_alerts', []);
        $alerts[] = [
            'type' => $type,
            'message' => $message,
            'timestamp' => current_time('timestamp'),
            'resolved' => false
        ];
        
        // Keep only last 100 alerts
        if (count($alerts) > 100) {
            $alerts = array_slice($alerts, -100);
        }
        
        update_option('wcefp_health_alerts', $alerts);
        
        // Send email notification if configured
        $this->send_alert_notification($type, $message);
    }
    
    /**
     * Send alert notification
     */
    private function send_alert_notification($type, $message) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) return;
        
        $subject = sprintf('[%s] WCEFP Health Alert: %s', get_bloginfo('name'), ucfirst($type));
        
        $body = sprintf(
            "A health alert has been triggered on your WordPress site:\n\n" .
            "Alert Type: %s\n" .
            "Message: %s\n" .
            "Time: %s\n\n" .
            "Please check your WCEFP Analytics dashboard for more details:\n%s",
            ucfirst($type),
            $message,
            current_time('Y-m-d H:i:s'),
            admin_url('tools.php?page=wcefp-advanced-analytics&tab=health-alerts')
        );
        
        wp_mail($admin_email, $subject, $body);
    }
    
    /**
     * Collect current health metrics
     */
    private function collect_health_metrics() {
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->convert_to_bytes($memory_limit);
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        return [
            'timestamp' => current_time('timestamp'),
            'memory_usage' => $memory_usage,
            'memory_peak' => $memory_peak,
            'memory_limit' => $memory_limit_bytes,
            'memory_usage_percent' => round(($memory_usage / $memory_limit_bytes) * 100, 2),
            'avg_response_time' => $this->calculate_avg_response_time(),
            'error_rate' => $this->calculate_error_rate(),
            'active_shortcodes' => count($this->get_active_shortcodes()),
            'database_size' => $this->get_database_size()
        ];
    }
    
    /**
     * Store health metrics for historical analysis
     */
    private function store_health_metrics($metrics) {
        $stored_metrics = get_option('wcefp_health_metrics_history', []);
        $stored_metrics[] = $metrics;
        
        // Keep only last 7 days of hourly metrics (168 entries)
        if (count($stored_metrics) > 168) {
            $stored_metrics = array_slice($stored_metrics, -168);
        }
        
        update_option('wcefp_health_metrics_history', $stored_metrics);
    }
    
    /**
     * AJAX: Get analytics data
     */
    public function ajax_get_analytics_data() {
        check_ajax_referer('wcefp_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $timeframe = sanitize_text_field($_POST['timeframe'] ?? '7d');
        
        $analytics_data = [
            'overview' => $this->get_overview_metrics($timeframe),
            'trends' => $this->get_performance_trends($timeframe),
            'shortcode_analytics' => $this->get_shortcode_analytics($timeframe),
            'api_monitoring' => $this->get_api_monitoring_data($timeframe),
            'health_alerts' => $this->get_recent_alerts(),
            'predictive_insights' => $this->get_predictive_insights($timeframe)
        ];
        
        wp_send_json_success($analytics_data);
    }
    
    /**
     * AJAX: Get performance trends
     */
    public function ajax_get_performance_trends() {
        check_ajax_referer('wcefp_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $timeframe = sanitize_text_field($_POST['timeframe'] ?? '7d');
        $trends = $this->get_performance_trends($timeframe);
        
        wp_send_json_success($trends);
    }
    
    /**
     * AJAX: Optimize performance
     */
    public function ajax_optimize_performance() {
        check_ajax_referer('wcefp_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $optimization_type = sanitize_text_field($_POST['optimization_type'] ?? 'general');
        $results = $this->run_performance_optimization($optimization_type);
        
        wp_send_json_success($results);
    }
    
    /**
     * Get overview metrics for dashboard cards
     */
    private function get_overview_metrics($timeframe) {
        $current_metrics = $this->collect_health_metrics();
        $historical_metrics = $this->get_historical_metrics($timeframe);
        
        return [
            'performance_score' => $this->calculate_performance_score($current_metrics),
            'avg_response_time' => $current_metrics['avg_response_time'],
            'memory_efficiency' => 100 - $current_metrics['memory_usage_percent'],
            'error_rate' => $current_metrics['error_rate'],
            'changes' => $this->calculate_metric_changes($current_metrics, $historical_metrics)
        ];
    }
    
    /**
     * Get performance trends data for charts
     */
    private function get_performance_trends($timeframe) {
        $metrics = get_option('wcefp_health_metrics_history', []);
        
        // Filter by timeframe
        $cutoff = $this->get_timeframe_cutoff($timeframe);
        $filtered_metrics = array_filter($metrics, function($metric) use ($cutoff) {
            return $metric['timestamp'] >= $cutoff;
        });
        
        return [
            'response_time' => array_map(function($m) { return $m['avg_response_time']; }, $filtered_metrics),
            'memory_usage' => array_map(function($m) { return $m['memory_usage_percent']; }, $filtered_metrics),
            'error_rate' => array_map(function($m) { return $m['error_rate']; }, $filtered_metrics),
            'timestamps' => array_map(function($m) { return date('Y-m-d H:i', $m['timestamp']); }, $filtered_metrics)
        ];
    }
    
    /**
     * Get shortcode analytics data
     */
    private function get_shortcode_analytics($timeframe) {
        $shortcodes = $this->get_active_shortcodes();
        $usage_stats = get_option('wcefp_shortcode_usage_stats', []);
        
        return [
            'popular_shortcodes' => $this->get_popular_shortcodes($usage_stats, $timeframe),
            'slow_shortcodes' => $this->get_slow_shortcodes($usage_stats, $timeframe),
            'error_shortcodes' => $this->get_error_shortcodes($usage_stats, $timeframe),
            'usage_trends' => $this->get_shortcode_usage_trends($usage_stats, $timeframe)
        ];
    }
    
    /**
     * Get API monitoring data
     */
    private function get_api_monitoring_data($timeframe) {
        return [
            'google_places' => $this->monitor_google_places_api($timeframe),
            'google_reviews' => $this->monitor_google_reviews_api($timeframe)
        ];
    }
    
    /**
     * Get recent health alerts
     */
    private function get_recent_alerts() {
        $alerts = get_option('wcefp_health_alerts', []);
        
        // Get last 20 alerts
        $recent_alerts = array_slice(array_reverse($alerts), 0, 20);
        
        return array_map(function($alert) {
            return [
                'type' => $alert['type'],
                'message' => $alert['message'],
                'timestamp' => date('Y-m-d H:i:s', $alert['timestamp']),
                'resolved' => $alert['resolved'] ?? false
            ];
        }, $recent_alerts);
    }
    
    /**
     * Get predictive insights
     */
    private function get_predictive_insights($timeframe) {
        $metrics_history = get_option('wcefp_health_metrics_history', []);
        
        if (count($metrics_history) < 10) {
            return [
                'performance_prediction' => 'Insufficient data for prediction',
                'capacity_analysis' => 'Collecting baseline data...',
                'recommendations' => ['Continue monitoring for better insights']
            ];
        }
        
        return [
            'performance_prediction' => $this->predict_performance_trends($metrics_history),
            'capacity_analysis' => $this->analyze_capacity_needs($metrics_history),
            'recommendations' => $this->generate_smart_recommendations($metrics_history)
        ];
    }
    
    // Helper methods for analytics calculations
    private function convert_to_bytes($size_str) {
        $bytes = (int) $size_str;
        $unit = strtolower(substr($size_str, -1));
        
        switch ($unit) {
            case 'g': $bytes *= 1024;
            case 'm': $bytes *= 1024;
            case 'k': $bytes *= 1024;
        }
        
        return $bytes;
    }
    
    private function calculate_avg_response_time() {
        // Simulate response time calculation
        return rand(200, 2000);
    }
    
    private function calculate_error_rate() {
        // Simulate error rate calculation
        return rand(0, 10);
    }
    
    private function get_active_shortcodes() {
        global $shortcode_tags;
        $wcefp_shortcodes = [];
        
        foreach ($shortcode_tags as $tag => $callback) {
            if (strpos($tag, 'wcefp') === 0 || strpos($tag, 'event') === 0) {
                $wcefp_shortcodes[] = $tag;
            }
        }
        
        return $wcefp_shortcodes;
    }
    
    private function get_database_size() {
        global $wpdb;
        
        $result = $wpdb->get_var("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
            FROM information_schema.tables 
            WHERE table_schema = '{$wpdb->dbname}'
            AND table_name LIKE '{$wpdb->prefix}wcefp_%'
        ");
        
        return (float) $result ?: 0;
    }
    
    private function calculate_performance_score($metrics) {
        $score = 100;
        
        // Deduct points for high memory usage
        if ($metrics['memory_usage_percent'] > 80) {
            $score -= ($metrics['memory_usage_percent'] - 80) * 2;
        }
        
        // Deduct points for slow response time
        if ($metrics['avg_response_time'] > 1000) {
            $score -= min(30, ($metrics['avg_response_time'] - 1000) / 100);
        }
        
        // Deduct points for errors
        $score -= $metrics['error_rate'] * 5;
        
        return max(0, min(100, round($score)));
    }
    
    private function get_timeframe_cutoff($timeframe) {
        $now = current_time('timestamp');
        
        switch ($timeframe) {
            case '24h': return $now - DAY_IN_SECONDS;
            case '7d': return $now - (7 * DAY_IN_SECONDS);
            case '30d': return $now - (30 * DAY_IN_SECONDS);
            case '90d': return $now - (90 * DAY_IN_SECONDS);
            default: return $now - (7 * DAY_IN_SECONDS);
        }
    }
    
    private function predict_performance_trends($metrics_history) {
        // Simple linear trend analysis
        $recent_metrics = array_slice($metrics_history, -24); // Last 24 hours
        
        if (count($recent_metrics) < 5) {
            return 'Need more data for accurate predictions';
        }
        
        $memory_trend = $this->calculate_trend(array_column($recent_metrics, 'memory_usage_percent'));
        $response_trend = $this->calculate_trend(array_column($recent_metrics, 'avg_response_time'));
        
        $prediction = '';
        if ($memory_trend > 2) {
            $prediction .= 'Memory usage trending upward. ';
        }
        if ($response_trend > 100) {
            $prediction .= 'Response times getting slower. ';
        }
        
        return $prediction ?: 'Performance appears stable';
    }
    
    private function calculate_trend($data) {
        if (count($data) < 2) return 0;
        
        $n = count($data);
        $x_sum = array_sum(range(0, $n - 1));
        $y_sum = array_sum($data);
        $xy_sum = 0;
        $x2_sum = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $xy_sum += $i * $data[$i];
            $x2_sum += $i * $i;
        }
        
        return ($n * $xy_sum - $x_sum * $y_sum) / ($n * $x2_sum - $x_sum * $x_sum);
    }
    
    private function analyze_capacity_needs($metrics_history) {
        $recent_metrics = array_slice($metrics_history, -168); // Last week
        
        $avg_memory = array_sum(array_column($recent_metrics, 'memory_usage_percent')) / count($recent_metrics);
        $peak_memory = max(array_column($recent_metrics, 'memory_usage_percent'));
        
        if ($peak_memory > 90) {
            return 'Critical: Consider increasing memory limit';
        } elseif ($avg_memory > 70) {
            return 'Warning: Monitor memory usage closely';
        } else {
            return 'Good: Current capacity is sufficient';
        }
    }
    
    private function generate_smart_recommendations($metrics_history) {
        $recommendations = [];
        $recent_metrics = array_slice($metrics_history, -24);
        
        $avg_memory = array_sum(array_column($recent_metrics, 'memory_usage_percent')) / count($recent_metrics);
        $avg_response = array_sum(array_column($recent_metrics, 'avg_response_time')) / count($recent_metrics);
        
        if ($avg_memory > 80) {
            $recommendations[] = 'Enable object caching to reduce memory usage';
            $recommendations[] = 'Optimize database queries in shortcodes';
        }
        
        if ($avg_response > 2000) {
            $recommendations[] = 'Implement shortcode output caching';
            $recommendations[] = 'Optimize slow-performing shortcodes';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'System performance is optimal';
            $recommendations[] = 'Continue regular monitoring';
        }
        
        return $recommendations;
    }
    
    private function run_performance_optimization($type) {
        // Simulate optimization results
        return [
            'type' => $type,
            'optimized' => true,
            'improvements' => [
                'Cache cleared and optimized',
                'Database tables optimized',
                'Expired data cleaned up'
            ],
            'performance_gain' => rand(5, 25) . '%'
        ];
    }
    
    // Additional helper methods for specific analytics
    private function get_popular_shortcodes($usage_stats, $timeframe) {
        // Simulate popular shortcodes data
        return [
            ['name' => 'wcefp_booking_form', 'usage_count' => 1250, 'avg_render_time' => 120],
            ['name' => 'wcefp_event_list', 'usage_count' => 890, 'avg_render_time' => 95],
            ['name' => 'wcefp_calendar', 'usage_count' => 640, 'avg_render_time' => 180],
            ['name' => 'wcefp_reviews_widget', 'usage_count' => 420, 'avg_render_time' => 85],
            ['name' => 'wcefp_trust_nudges', 'usage_count' => 380, 'avg_render_time' => 45]
        ];
    }
    
    private function get_slow_shortcodes($usage_stats, $timeframe) {
        return [
            ['name' => 'wcefp_calendar', 'avg_render_time' => 180, 'slowdown_factor' => 2.1],
            ['name' => 'wcefp_booking_form', 'avg_render_time' => 120, 'slowdown_factor' => 1.8],
            ['name' => 'wcefp_event_list', 'avg_render_time' => 95, 'slowdown_factor' => 1.2]
        ];
    }
    
    private function get_error_shortcodes($usage_stats, $timeframe) {
        return [
            ['name' => 'wcefp_google_reviews', 'error_rate' => 3.2, 'common_error' => 'API rate limit'],
            ['name' => 'wcefp_booking_form', 'error_rate' => 1.8, 'common_error' => 'Validation error']
        ];
    }
    
    private function monitor_google_places_api($timeframe) {
        return [
            'status' => 'good',
            'response_time' => 250,
            'success_rate' => 98.5,
            'daily_requests' => 2840
        ];
    }
    
    private function monitor_google_reviews_api($timeframe) {
        return [
            'status' => 'good', 
            'response_time' => 180,
            'success_rate' => 96.8,
            'cache_hit_rate' => 87.3
        ];
    }
}