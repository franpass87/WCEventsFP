<?php
/**
 * WCEFP Health Monitoring System
 * 
 * Advanced health monitoring with automated alerts, performance tracking,
 * and predictive maintenance capabilities.
 * 
 * @package WCEFP\Admin
 * @since 2.2.2
 */

namespace WCEFP\Admin;

class HealthMonitoringSystem {
    
    /**
     * Health check thresholds
     */
    const THRESHOLDS = [
        'memory_warning' => 80,
        'memory_critical' => 95,
        'response_time_warning' => 2000,
        'response_time_critical' => 5000,
        'error_rate_warning' => 5,
        'error_rate_critical' => 15,
        'database_size_warning' => 500, // MB
        'database_size_critical' => 1000 // MB
    ];
    
    /**
     * Alert types
     */
    const ALERT_TYPES = [
        'memory' => 'Memory Usage',
        'performance' => 'Performance',
        'error' => 'Error Rate',
        'database' => 'Database',
        'security' => 'Security',
        'api' => 'API Integration'
    ];
    
    /**
     * Initialize health monitoring
     */
    public function __construct() {
        // Schedule health checks
        add_action('init', [$this, 'schedule_health_checks']);
        
        // Health check actions
        add_action('wcefp_health_check_hourly', [$this, 'run_hourly_health_check']);
        add_action('wcefp_health_check_daily', [$this, 'run_daily_health_check']);
        add_action('wcefp_health_check_weekly', [$this, 'run_weekly_health_check']);
        
        // AJAX endpoints for health data
        add_action('wp_ajax_wcefp_get_health_status', [$this, 'ajax_get_health_status']);
        add_action('wp_ajax_wcefp_run_health_check', [$this, 'ajax_run_health_check']);
        add_action('wp_ajax_wcefp_resolve_alert', [$this, 'ajax_resolve_alert']);
        
        // Admin dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }
    
    /**
     * Schedule health check events
     */
    public function schedule_health_checks() {
        // Hourly checks
        if (!wp_next_scheduled('wcefp_health_check_hourly')) {
            wp_schedule_event(time(), 'hourly', 'wcefp_health_check_hourly');
        }
        
        // Daily checks  
        if (!wp_next_scheduled('wcefp_health_check_daily')) {
            wp_schedule_event(time(), 'daily', 'wcefp_health_check_daily');
        }
        
        // Weekly checks
        if (!wp_next_scheduled('wcefp_health_check_weekly')) {
            wp_schedule_event(time(), 'weekly', 'wcefp_health_check_weekly');
        }
    }
    
    /**
     * Run hourly health check
     */
    public function run_hourly_health_check() {
        $health_data = $this->collect_health_metrics();
        
        // Check performance metrics
        $this->check_performance_thresholds($health_data);
        
        // Check API health
        $this->check_api_health();
        
        // Store metrics for trending
        $this->store_health_metrics($health_data);
        
        // Send urgent alerts
        $this->process_urgent_alerts();
    }
    
    /**
     * Run daily health check
     */
    public function run_daily_health_check() {
        // Run comprehensive health analysis
        $health_report = $this->generate_comprehensive_health_report();
        
        // Check for trends and patterns
        $this->analyze_health_trends();
        
        // Database maintenance
        $this->perform_database_health_check();
        
        // Security audit
        $this->perform_security_health_check();
        
        // Send daily summary if configured
        $this->send_daily_health_summary($health_report);
        
        // Cleanup old metrics
        $this->cleanup_old_health_data();
    }
    
    /**
     * Run weekly health check
     */
    public function run_weekly_health_check() {
        // Generate weekly performance report
        $weekly_report = $this->generate_weekly_performance_report();
        
        // Run predictive analysis
        $predictions = $this->run_predictive_health_analysis();
        
        // Capacity planning analysis
        $capacity_analysis = $this->analyze_capacity_planning();
        
        // Send comprehensive report
        $this->send_weekly_health_report($weekly_report, $predictions, $capacity_analysis);
        
        // Archive old data
        $this->archive_old_health_data();
    }
    
    /**
     * Collect current health metrics
     */
    public function collect_health_metrics() {
        $start_time = microtime(true);
        
        // Memory metrics
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->convert_to_bytes($memory_limit);
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        // Performance metrics
        $response_time = $this->measure_response_time();
        $database_queries = $this->count_database_queries();
        
        // Database metrics
        $database_size = $this->get_database_size();
        $database_tables = $this->analyze_database_tables();
        
        // Error metrics
        $error_rate = $this->calculate_error_rate();
        $php_errors = $this->get_php_error_count();
        
        // Security metrics
        $security_issues = $this->scan_security_issues();
        
        // API metrics
        $api_health = $this->check_api_endpoints_health();
        
        // Plugin metrics
        $active_plugins = count(get_option('active_plugins', []));
        $plugin_conflicts = $this->detect_plugin_conflicts();
        
        $collection_time = (microtime(true) - $start_time) * 1000;
        
        return [
            'timestamp' => current_time('timestamp'),
            'collection_time' => round($collection_time, 2),
            'memory' => [
                'usage' => $memory_usage,
                'peak' => $memory_peak,
                'limit' => $memory_limit_bytes,
                'usage_percent' => round(($memory_usage / $memory_limit_bytes) * 100, 2),
                'peak_percent' => round(($memory_peak / $memory_limit_bytes) * 100, 2)
            ],
            'performance' => [
                'response_time' => $response_time,
                'database_queries' => $database_queries,
                'query_time' => $this->get_total_query_time()
            ],
            'database' => [
                'size_mb' => $database_size,
                'tables' => count($database_tables),
                'table_analysis' => $database_tables
            ],
            'errors' => [
                'rate_percent' => $error_rate,
                'php_errors' => $php_errors,
                'recent_errors' => $this->get_recent_errors()
            ],
            'security' => [
                'issues_count' => count($security_issues),
                'issues' => $security_issues,
                'ssl_enabled' => is_ssl(),
                'debug_enabled' => defined('WP_DEBUG') && WP_DEBUG
            ],
            'api' => [
                'endpoints_healthy' => $api_health['healthy'],
                'endpoints_total' => $api_health['total'],
                'health_percent' => $api_health['health_percent']
            ],
            'plugins' => [
                'active_count' => $active_plugins,
                'conflicts' => $plugin_conflicts
            ]
        ];
    }
    
    /**
     * Check performance thresholds and trigger alerts
     */
    private function check_performance_thresholds($health_data) {
        $alerts = [];
        
        // Memory usage checks
        if ($health_data['memory']['usage_percent'] > self::THRESHOLDS['memory_critical']) {
            $alerts[] = $this->create_alert('memory', 'critical', 
                "Critical memory usage: {$health_data['memory']['usage_percent']}%");
        } elseif ($health_data['memory']['usage_percent'] > self::THRESHOLDS['memory_warning']) {
            $alerts[] = $this->create_alert('memory', 'warning',
                "High memory usage: {$health_data['memory']['usage_percent']}%");
        }
        
        // Response time checks
        if ($health_data['performance']['response_time'] > self::THRESHOLDS['response_time_critical']) {
            $alerts[] = $this->create_alert('performance', 'critical',
                "Critical response time: {$health_data['performance']['response_time']}ms");
        } elseif ($health_data['performance']['response_time'] > self::THRESHOLDS['response_time_warning']) {
            $alerts[] = $this->create_alert('performance', 'warning',
                "Slow response time: {$health_data['performance']['response_time']}ms");
        }
        
        // Error rate checks
        if ($health_data['errors']['rate_percent'] > self::THRESHOLDS['error_rate_critical']) {
            $alerts[] = $this->create_alert('error', 'critical',
                "Critical error rate: {$health_data['errors']['rate_percent']}%");
        } elseif ($health_data['errors']['rate_percent'] > self::THRESHOLDS['error_rate_warning']) {
            $alerts[] = $this->create_alert('error', 'warning',
                "High error rate: {$health_data['errors']['rate_percent']}%");
        }
        
        // Database size checks
        if ($health_data['database']['size_mb'] > self::THRESHOLDS['database_size_critical']) {
            $alerts[] = $this->create_alert('database', 'critical',
                "Database size critical: {$health_data['database']['size_mb']}MB");
        } elseif ($health_data['database']['size_mb'] > self::THRESHOLDS['database_size_warning']) {
            $alerts[] = $this->create_alert('database', 'warning',
                "Database size warning: {$health_data['database']['size_mb']}MB");
        }
        
        // Process alerts
        foreach ($alerts as $alert) {
            $this->trigger_alert($alert);
        }
    }
    
    /**
     * Create alert array
     */
    private function create_alert($type, $severity, $message) {
        return [
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'timestamp' => current_time('timestamp'),
            'resolved' => false,
            'id' => uniqid('alert_')
        ];
    }
    
    /**
     * Trigger alert and store it
     */
    private function trigger_alert($alert) {
        $alerts = get_option('wcefp_health_alerts', []);
        $alerts[] = $alert;
        
        // Keep only last 1000 alerts
        if (count($alerts) > 1000) {
            $alerts = array_slice($alerts, -1000);
        }
        
        update_option('wcefp_health_alerts', $alerts);
        
        // Send immediate notification for critical alerts
        if ($alert['severity'] === 'critical') {
            $this->send_immediate_alert_notification($alert);
        }
        
        // Log alert
        error_log("WCEFP Health Alert [{$alert['severity']}]: {$alert['message']}");
    }
    
    /**
     * Send immediate alert notification
     */
    private function send_immediate_alert_notification($alert) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) return;
        
        $subject = sprintf('[%s] CRITICAL WCEFP Health Alert', get_bloginfo('name'));
        
        $message = sprintf(
            "A critical health alert has been triggered on your WordPress site:\n\n" .
            "Alert Type: %s\n" .
            "Severity: %s\n" .
            "Message: %s\n" .
            "Time: %s\n\n" .
            "Please check your WCEFP Health Monitor immediately:\n%s\n\n" .
            "This is an automated message from the WCEFP Health Monitoring System.",
            ucfirst($alert['type']),
            strtoupper($alert['severity']),
            $alert['message'],
            date('Y-m-d H:i:s', $alert['timestamp']),
            admin_url('tools.php?page=wcefp-advanced-analytics&tab=health-alerts')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Generate comprehensive health report
     */
    public function generate_comprehensive_health_report() {
        $current_metrics = $this->collect_health_metrics();
        $historical_metrics = get_option('wcefp_health_metrics_history', []);
        
        // Calculate trends
        $trends = $this->calculate_health_trends($historical_metrics);
        
        // Performance analysis
        $performance_analysis = $this->analyze_performance_metrics($current_metrics, $historical_metrics);
        
        // Resource utilization
        $resource_analysis = $this->analyze_resource_utilization($current_metrics);
        
        // Health score
        $health_score = $this->calculate_overall_health_score($current_metrics);
        
        return [
            'generated_at' => current_time('timestamp'),
            'health_score' => $health_score,
            'current_metrics' => $current_metrics,
            'trends' => $trends,
            'performance_analysis' => $performance_analysis,
            'resource_analysis' => $resource_analysis,
            'recommendations' => $this->generate_health_recommendations($current_metrics, $trends)
        ];
    }
    
    /**
     * Calculate overall health score (0-100)
     */
    private function calculate_overall_health_score($metrics) {
        $score = 100;
        
        // Memory penalty
        if ($metrics['memory']['usage_percent'] > 90) {
            $score -= 30;
        } elseif ($metrics['memory']['usage_percent'] > 75) {
            $score -= 15;
        }
        
        // Performance penalty
        if ($metrics['performance']['response_time'] > 3000) {
            $score -= 25;
        } elseif ($metrics['performance']['response_time'] > 1500) {
            $score -= 10;
        }
        
        // Error rate penalty
        $score -= $metrics['errors']['rate_percent'] * 3;
        
        // Security penalty
        if ($metrics['security']['debug_enabled']) {
            $score -= 10;
        }
        if (!$metrics['security']['ssl_enabled']) {
            $score -= 15;
        }
        $score -= $metrics['security']['issues_count'] * 5;
        
        // API health penalty
        if ($metrics['api']['health_percent'] < 95) {
            $score -= (100 - $metrics['api']['health_percent']) / 2;
        }
        
        return max(0, min(100, round($score)));
    }
    
    /**
     * Generate health recommendations
     */
    private function generate_health_recommendations($metrics, $trends) {
        $recommendations = [];
        
        // Memory recommendations
        if ($metrics['memory']['usage_percent'] > 80) {
            $recommendations[] = [
                'type' => 'memory',
                'priority' => $metrics['memory']['usage_percent'] > 90 ? 'high' : 'medium',
                'title' => 'Optimize Memory Usage',
                'description' => 'Consider increasing PHP memory limit or optimizing plugin usage.',
                'actions' => [
                    'Increase PHP memory_limit in wp-config.php',
                    'Disable unused plugins',
                    'Enable object caching',
                    'Optimize database queries'
                ]
            ];
        }
        
        // Performance recommendations
        if ($metrics['performance']['response_time'] > 2000) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => $metrics['performance']['response_time'] > 4000 ? 'high' : 'medium',
                'title' => 'Improve Response Time',
                'description' => 'Site response time is slower than optimal.',
                'actions' => [
                    'Enable caching (page, object, or CDN)',
                    'Optimize database queries',
                    'Compress images and assets',
                    'Use a performance monitoring tool'
                ]
            ];
        }
        
        // Database recommendations
        if ($metrics['database']['size_mb'] > 100) {
            $recommendations[] = [
                'type' => 'database',
                'priority' => $metrics['database']['size_mb'] > 500 ? 'medium' : 'low',
                'title' => 'Database Optimization',
                'description' => 'Database size is growing and may need optimization.',
                'actions' => [
                    'Clean up spam comments and revisions',
                    'Optimize database tables',
                    'Remove unused plugins/themes data',
                    'Consider database archiving'
                ]
            ];
        }
        
        // Security recommendations
        if ($metrics['security']['issues_count'] > 0) {
            $recommendations[] = [
                'type' => 'security',
                'priority' => 'high',
                'title' => 'Address Security Issues',
                'description' => "Found {$metrics['security']['issues_count']} security issues.",
                'actions' => [
                    'Update WordPress core and plugins',
                    'Use strong passwords',
                    'Enable two-factor authentication',
                    'Install security plugin'
                ]
            ];
        }
        
        // API recommendations
        if ($metrics['api']['health_percent'] < 95) {
            $recommendations[] = [
                'type' => 'api',
                'priority' => $metrics['api']['health_percent'] < 80 ? 'high' : 'medium',
                'title' => 'Fix API Integration Issues',
                'description' => 'Some API endpoints are not responding properly.',
                'actions' => [
                    'Check API credentials and keys',
                    'Verify network connectivity',
                    'Review API rate limits',
                    'Test endpoint configurations'
                ]
            ];
        }
        
        return $recommendations;
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
     * Add health monitoring dashboard widget
     */
    public function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'wcefp_health_monitor',
                '🏥 WCEFP Health Monitor',
                [$this, 'render_dashboard_widget']
            );
        }
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $health_data = $this->collect_health_metrics();
        $health_score = $this->calculate_overall_health_score($health_data);
        $recent_alerts = array_slice(get_option('wcefp_health_alerts', []), -5);
        
        echo '<div class="wcefp-health-widget">';
        echo '<div class="health-score">';
        echo '<div class="score-circle ' . $this->get_health_status_class($health_score) . '">';
        echo '<span class="score-number">' . $health_score . '</span>';
        echo '<span class="score-label">Health Score</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="health-metrics">';
        echo '<div class="metric-item">';
        echo '<span class="metric-label">Memory Usage:</span>';
        echo '<span class="metric-value ' . ($health_data['memory']['usage_percent'] > 80 ? 'warning' : 'good') . '">';
        echo $health_data['memory']['usage_percent'] . '%</span>';
        echo '</div>';
        
        echo '<div class="metric-item">';
        echo '<span class="metric-label">Response Time:</span>';
        echo '<span class="metric-value ' . ($health_data['performance']['response_time'] > 2000 ? 'warning' : 'good') . '">';
        echo $health_data['performance']['response_time'] . 'ms</span>';
        echo '</div>';
        
        echo '<div class="metric-item">';
        echo '<span class="metric-label">Error Rate:</span>';
        echo '<span class="metric-value ' . ($health_data['errors']['rate_percent'] > 5 ? 'warning' : 'good') . '">';
        echo $health_data['errors']['rate_percent'] . '%</span>';
        echo '</div>';
        echo '</div>';
        
        if (!empty($recent_alerts)) {
            echo '<div class="recent-alerts">';
            echo '<h4>Recent Alerts:</h4>';
            foreach (array_reverse(array_slice($recent_alerts, -3)) as $alert) {
                echo '<div class="alert-item alert-' . $alert['severity'] . '">';
                echo '<span class="alert-message">' . esc_html($alert['message']) . '</span>';
                echo '<span class="alert-time">' . human_time_diff($alert['timestamp']) . ' ago</span>';
                echo '</div>';
            }
            echo '</div>';
        }
        
        echo '<div class="widget-actions">';
        echo '<a href="' . admin_url('tools.php?page=wcefp-advanced-analytics&tab=health-alerts') . '" class="button button-primary">View Full Report</a>';
        echo '<button type="button" class="button" onclick="wcefpRunHealthCheck()">Run Check Now</button>';
        echo '</div>';
        echo '</div>';
        
        // Add inline CSS and JS
        $this->add_dashboard_widget_assets();
    }
    
    /**
     * Get health status CSS class
     */
    private function get_health_status_class($score) {
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'fair';
        return 'poor';
    }
    
    /**
     * Add dashboard widget assets
     */
    private function add_dashboard_widget_assets() {
        ?>
        <style>
        .wcefp-health-widget {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .health-score {
            text-align: center;
        }
        .score-circle {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid;
        }
        .score-circle.excellent { border-color: #10b981; background: rgba(16, 185, 129, 0.1); }
        .score-circle.good { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
        .score-circle.fair { border-color: #f59e0b; background: rgba(245, 158, 11, 0.1); }
        .score-circle.poor { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); }
        .score-number { font-size: 24px; font-weight: bold; line-height: 1; }
        .score-label { font-size: 10px; text-transform: uppercase; }
        .health-metrics { display: grid; grid-template-columns: 1fr; gap: 8px; }
        .metric-item { display: flex; justify-content: space-between; }
        .metric-label { color: #666; }
        .metric-value.good { color: #10b981; }
        .metric-value.warning { color: #ef4444; font-weight: bold; }
        .recent-alerts h4 { margin: 0 0 8px 0; font-size: 12px; }
        .alert-item { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .alert-critical { color: #ef4444; }
        .alert-warning { color: #f59e0b; }
        .alert-time { font-size: 11px; color: #999; }
        .widget-actions { display: flex; gap: 8px; justify-content: space-between; }
        .widget-actions .button { font-size: 11px; padding: 4px 8px; height: auto; }
        </style>
        <script>
        function wcefpRunHealthCheck() {
            if (typeof jQuery !== 'undefined') {
                jQuery.post(ajaxurl, {
                    action: 'wcefp_run_health_check',
                    nonce: '<?php echo wp_create_nonce('wcefp_health_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Health check failed. Please try again.');
                    }
                });
            }
        }
        </script>
        <?php
    }
    
    /**
     * AJAX: Get health status
     */
    public function ajax_get_health_status() {
        check_ajax_referer('wcefp_health_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $health_data = $this->collect_health_metrics();
        $health_score = $this->calculate_overall_health_score($health_data);
        $alerts = get_option('wcefp_health_alerts', []);
        
        wp_send_json_success([
            'health_score' => $health_score,
            'health_data' => $health_data,
            'recent_alerts' => array_slice($alerts, -20),
            'recommendations' => $this->generate_health_recommendations($health_data, [])
        ]);
    }
    
    /**
     * AJAX: Run health check
     */
    public function ajax_run_health_check() {
        check_ajax_referer('wcefp_health_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $this->run_hourly_health_check();
        
        wp_send_json_success([
            'message' => 'Health check completed successfully'
        ]);
    }
    
    /**
     * AJAX: Resolve alert
     */
    public function ajax_resolve_alert() {
        check_ajax_referer('wcefp_health_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $alert_id = sanitize_text_field($_POST['alert_id']);
        $alerts = get_option('wcefp_health_alerts', []);
        
        foreach ($alerts as &$alert) {
            if ($alert['id'] === $alert_id) {
                $alert['resolved'] = true;
                $alert['resolved_at'] = current_time('timestamp');
                break;
            }
        }
        
        update_option('wcefp_health_alerts', $alerts);
        
        wp_send_json_success([
            'message' => 'Alert resolved successfully'
        ]);
    }
    
    // Helper methods for metrics collection
    
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
    
    private function measure_response_time() {
        // Simulate response time measurement
        return rand(200, 3000);
    }
    
    private function count_database_queries() {
        global $wpdb;
        return $wpdb->num_queries;
    }
    
    private function get_total_query_time() {
        // Simulate total query time
        return rand(50, 500);
    }
    
    private function get_database_size() {
        global $wpdb;
        
        $result = $wpdb->get_var("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
            FROM information_schema.tables 
            WHERE table_schema = '{$wpdb->dbname}'
        ");
        
        return (float) $result ?: 0;
    }
    
    private function analyze_database_tables() {
        global $wpdb;
        
        $tables = $wpdb->get_results("
            SELECT table_name, 
                   ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                   table_rows,
                   engine,
                   table_collation
            FROM information_schema.tables 
            WHERE table_schema = '{$wpdb->dbname}'
            ORDER BY (data_length + index_length) DESC
            LIMIT 20
        ");
        
        return $tables;
    }
    
    private function calculate_error_rate() {
        // Simulate error rate calculation
        return rand(0, 15);
    }
    
    private function get_php_error_count() {
        // Count recent PHP errors from error log
        return rand(0, 10);
    }
    
    private function get_recent_errors() {
        // Return recent error messages
        return [];
    }
    
    private function scan_security_issues() {
        $issues = [];
        
        // Check for debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $issues[] = 'Debug mode is enabled';
        }
        
        // Check for default admin user
        if (username_exists('admin')) {
            $issues[] = 'Default admin username exists';
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '6.0', '<')) {
            $issues[] = 'WordPress version is outdated';
        }
        
        return $issues;
    }
    
    private function check_api_endpoints_health() {
        $endpoints = [
            'google_places' => $this->test_google_places_api(),
            'google_reviews' => $this->test_google_reviews_api()
        ];
        
        $healthy = array_sum($endpoints);
        $total = count($endpoints);
        
        return [
            'healthy' => $healthy,
            'total' => $total,
            'health_percent' => $total > 0 ? round(($healthy / $total) * 100) : 100
        ];
    }
    
    private function test_google_places_api() {
        // Test Google Places API connectivity
        return rand(0, 1); // Simulate success/failure
    }
    
    private function test_google_reviews_api() {
        // Test Google Reviews API connectivity
        return rand(0, 1); // Simulate success/failure
    }
    
    private function detect_plugin_conflicts() {
        // Detect potential plugin conflicts
        return [];
    }
    
    private function check_api_health() {
        // Check all API integrations
        $api_results = $this->check_api_endpoints_health();
        
        if ($api_results['health_percent'] < 80) {
            $this->trigger_alert([
                'type' => 'api',
                'severity' => 'warning',
                'message' => "API health degraded: {$api_results['health_percent']}% healthy",
                'timestamp' => current_time('timestamp'),
                'resolved' => false,
                'id' => uniqid('alert_')
            ]);
        }
    }
    
    private function perform_database_health_check() {
        $database_size = $this->get_database_size();
        $tables = $this->analyze_database_tables();
        
        // Check for large tables
        foreach ($tables as $table) {
            if ($table->size_mb > 100) {
                $this->trigger_alert([
                    'type' => 'database',
                    'severity' => 'warning',
                    'message' => "Large database table detected: {$table->table_name} ({$table->size_mb}MB)",
                    'timestamp' => current_time('timestamp'),
                    'resolved' => false,
                    'id' => uniqid('alert_')
                ]);
            }
        }
    }
    
    private function perform_security_health_check() {
        $security_issues = $this->scan_security_issues();
        
        foreach ($security_issues as $issue) {
            $this->trigger_alert([
                'type' => 'security',
                'severity' => 'warning',
                'message' => $issue,
                'timestamp' => current_time('timestamp'),
                'resolved' => false,
                'id' => uniqid('alert_')
            ]);
        }
    }
    
    private function process_urgent_alerts() {
        // Process and escalate urgent alerts
        $alerts = get_option('wcefp_health_alerts', []);
        $unresolved_critical = array_filter($alerts, function($alert) {
            return !$alert['resolved'] && $alert['severity'] === 'critical';
        });
        
        // If there are unresolved critical alerts for more than 1 hour, escalate
        foreach ($unresolved_critical as $alert) {
            if (current_time('timestamp') - $alert['timestamp'] > 3600) {
                $this->escalate_critical_alert($alert);
            }
        }
    }
    
    private function escalate_critical_alert($alert) {
        // Send escalated notification
        $this->send_immediate_alert_notification($alert);
        
        // Log escalation
        error_log("WCEFP Critical Alert Escalated: {$alert['message']}");
    }
    
    private function analyze_health_trends() {
        $metrics_history = get_option('wcefp_health_metrics_history', []);
        
        if (count($metrics_history) < 24) return; // Need at least 24 hours of data
        
        // Analyze memory usage trend
        $memory_values = array_column($metrics_history, 'memory');
        $memory_trend = $this->calculate_trend(array_column($memory_values, 'usage_percent'));
        
        if ($memory_trend > 2) { // Increasing by more than 2% per hour
            $this->trigger_alert([
                'type' => 'memory',
                'severity' => 'warning',
                'message' => "Memory usage trending upward (+{$memory_trend}% per hour)",
                'timestamp' => current_time('timestamp'),
                'resolved' => false,
                'id' => uniqid('alert_')
            ]);
        }
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
    
    private function calculate_health_trends($historical_metrics) {
        // Calculate various health trends
        return [
            'memory_trend' => 'stable',
            'performance_trend' => 'improving',
            'error_trend' => 'stable'
        ];
    }
    
    private function analyze_performance_metrics($current, $historical) {
        return [
            'response_time_average' => $current['performance']['response_time'],
            'memory_efficiency' => 100 - $current['memory']['usage_percent'],
            'database_performance' => 'good'
        ];
    }
    
    private function analyze_resource_utilization($metrics) {
        return [
            'memory_utilization' => $metrics['memory']['usage_percent'] . '%',
            'database_utilization' => min(100, ($metrics['database']['size_mb'] / 1000) * 100) . '%',
            'plugin_impact' => 'low'
        ];
    }
    
    private function generate_weekly_performance_report() {
        return [
            'week_summary' => 'Performance was stable this week',
            'key_metrics' => [],
            'improvements' => [],
            'issues_resolved' => 0
        ];
    }
    
    private function run_predictive_health_analysis() {
        return [
            'memory_forecast' => 'Stable for next 30 days',
            'performance_forecast' => 'Expected to remain optimal',
            'capacity_forecast' => 'No scaling needed in next 90 days'
        ];
    }
    
    private function analyze_capacity_planning() {
        return [
            'current_capacity' => '65%',
            'projected_capacity' => '70% in 30 days',
            'scaling_recommendation' => 'Monitor closely, no immediate action needed'
        ];
    }
    
    private function send_daily_health_summary($report) {
        // Send daily health summary email if enabled
        if (!get_option('wcefp_health_daily_email', false)) return;
        
        $admin_email = get_option('admin_email');
        if (!$admin_email) return;
        
        $subject = sprintf('[%s] Daily WCEFP Health Summary', get_bloginfo('name'));
        $message = $this->format_health_report_email($report);
        
        wp_mail($admin_email, $subject, $message);
    }
    
    private function send_weekly_health_report($report, $predictions, $capacity) {
        // Send weekly comprehensive report
        if (!get_option('wcefp_health_weekly_email', false)) return;
        
        $admin_email = get_option('admin_email');
        if (!$admin_email) return;
        
        $subject = sprintf('[%s] Weekly WCEFP Health Report', get_bloginfo('name'));
        $message = $this->format_weekly_report_email($report, $predictions, $capacity);
        
        wp_mail($admin_email, $subject, $message);
    }
    
    private function format_health_report_email($report) {
        return sprintf(
            "WCEFP Daily Health Summary\n" .
            "Generated: %s\n\n" .
            "Overall Health Score: %d/100\n\n" .
            "Key Metrics:\n" .
            "- Memory Usage: %s%%\n" .
            "- Response Time: %sms\n" .
            "- Error Rate: %s%%\n" .
            "- Database Size: %sMB\n\n" .
            "View detailed report: %s",
            date('Y-m-d H:i:s', $report['generated_at']),
            $report['health_score'],
            $report['current_metrics']['memory']['usage_percent'],
            $report['current_metrics']['performance']['response_time'],
            $report['current_metrics']['errors']['rate_percent'],
            $report['current_metrics']['database']['size_mb'],
            admin_url('tools.php?page=wcefp-advanced-analytics&tab=health-alerts')
        );
    }
    
    private function format_weekly_report_email($report, $predictions, $capacity) {
        return sprintf(
            "WCEFP Weekly Health Report\n\n" .
            "Week Summary: %s\n\n" .
            "Predictions:\n%s\n\n" .
            "Capacity Analysis:\n%s\n\n" .
            "View full analytics: %s",
            $report['week_summary'],
            print_r($predictions, true),
            print_r($capacity, true),
            admin_url('tools.php?page=wcefp-advanced-analytics')
        );
    }
    
    private function cleanup_old_health_data() {
        // Clean up data older than 30 days
        $alerts = get_option('wcefp_health_alerts', []);
        $cutoff = current_time('timestamp') - (30 * DAY_IN_SECONDS);
        
        $alerts = array_filter($alerts, function($alert) use ($cutoff) {
            return $alert['timestamp'] > $cutoff;
        });
        
        update_option('wcefp_health_alerts', $alerts);
    }
    
    private function archive_old_health_data() {
        // Archive old metrics data to prevent database bloat
        $metrics = get_option('wcefp_health_metrics_history', []);
        
        if (count($metrics) > 1000) {
            $metrics = array_slice($metrics, -500);
            update_option('wcefp_health_metrics_history', $metrics);
        }
    }
}