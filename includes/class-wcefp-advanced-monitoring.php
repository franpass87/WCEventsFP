<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Advanced Monitoring System
 * Provides comprehensive monitoring, alerting, and health checks
 */
class WCEFP_Advanced_Monitoring {
    
    private static $instance = null;
    private $monitors = [];
    private $alerts = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize monitoring
        add_action('init', [$this, 'init_monitors']);
        add_action('wp_ajax_wcefp_get_monitoring_data', [$this, 'get_monitoring_data']);
        add_action('wp_ajax_wcefp_trigger_health_check', [$this, 'trigger_health_check']);
        add_action('wp_ajax_wcefp_configure_alerts', [$this, 'configure_alerts']);
        
        // Schedule monitoring tasks
        add_action('wp', [$this, 'schedule_monitoring']);
        add_action('wcefp_monitoring_check', [$this, 'run_monitoring_checks']);
        add_action('wcefp_daily_monitoring_report', [$this, 'send_daily_report']);
        
        // Hook into critical events
        add_action('wcefp_booking_failed', [$this, 'alert_booking_failure'], 10, 2);
        add_action('wcefp_payment_failed', [$this, 'alert_payment_failure'], 10, 2);
        add_action('wcefp_system_error', [$this, 'alert_system_error'], 10, 2);
        
        // Performance monitoring
        add_action('shutdown', [$this, 'record_performance_metrics']);
    }
    
    /**
     * Initialize monitoring systems
     */
    public function init_monitors() {
        $this->monitors = [
            'database_health' => new WCEFP_Database_Monitor(),
            'performance' => new WCEFP_Performance_Monitor(),
            'booking_system' => new WCEFP_Booking_Monitor(),
            'security' => new WCEFP_Security_Monitor(),
            'integration' => new WCEFP_Integration_Monitor(),
            'resource_usage' => new WCEFP_Resource_Monitor()
        ];
    }
    
    /**
     * Schedule monitoring tasks
     */
    public function schedule_monitoring() {
        // Every 5 minutes monitoring
        if (!wp_next_scheduled('wcefp_monitoring_check')) {
            wp_schedule_event(time(), 'wcefp_5min', 'wcefp_monitoring_check');
        }
        
        // Daily monitoring report
        if (!wp_next_scheduled('wcefp_daily_monitoring_report')) {
            wp_schedule_event(strtotime('tomorrow 8:00'), 'daily', 'wcefp_daily_monitoring_report');
        }
        
        // Add custom cron interval
        add_filter('cron_schedules', [$this, 'add_monitoring_intervals']);
    }
    
    /**
     * Add custom monitoring intervals
     */
    public function add_monitoring_intervals($schedules) {
        $schedules['wcefp_5min'] = [
            'interval' => 300, // 5 minutes
            'display' => __('Every 5 minutes', 'wceventsfp')
        ];
        
        $schedules['wcefp_15min'] = [
            'interval' => 900, // 15 minutes
            'display' => __('Every 15 minutes', 'wceventsfp')
        ];
        
        return $schedules;
    }
    
    /**
     * Run monitoring checks
     */
    public function run_monitoring_checks() {
        $results = [];
        $alerts_triggered = [];
        
        foreach ($this->monitors as $name => $monitor) {
            try {
                $result = $monitor->check();
                $results[$name] = $result;
                
                // Check if alerts should be triggered
                $alert = $this->evaluate_alert_conditions($name, $result);
                if ($alert) {
                    $alerts_triggered[] = $alert;
                }
                
            } catch (Exception $e) {
                WCEFP_Logger::error("Monitor '$name' failed", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                $results[$name] = [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        // Store monitoring results
        update_option('wcefp_monitoring_results', $results);
        update_option('wcefp_monitoring_last_check', current_time('mysql'));
        
        // Send alerts if any were triggered
        foreach ($alerts_triggered as $alert) {
            $this->send_alert($alert);
        }
        
        WCEFP_Logger::info('Monitoring check completed', [
            'monitors_checked' => count($this->monitors),
            'alerts_triggered' => count($alerts_triggered)
        ]);
    }
    
    /**
     * Evaluate alert conditions
     */
    private function evaluate_alert_conditions($monitor_name, $result) {
        $alert_config = get_option('wcefp_alert_config', []);
        $monitor_config = $alert_config[$monitor_name] ?? [];
        
        if (empty($monitor_config['enabled'])) {
            return null;
        }
        
        $alert = null;
        
        // Check various alert conditions
        switch ($result['status']) {
            case 'critical':
                if ($monitor_config['alert_on_critical'] ?? true) {
                    $alert = [
                        'level' => 'critical',
                        'monitor' => $monitor_name,
                        'message' => $result['message'] ?? 'Critical issue detected',
                        'data' => $result
                    ];
                }
                break;
                
            case 'warning':
                if ($monitor_config['alert_on_warning'] ?? true) {
                    $alert = [
                        'level' => 'warning',
                        'monitor' => $monitor_name,
                        'message' => $result['message'] ?? 'Warning condition detected',
                        'data' => $result
                    ];
                }
                break;
        }
        
        // Check threshold-based alerts
        if (isset($result['metrics'])) {
            foreach ($result['metrics'] as $metric => $value) {
                $threshold_config = $monitor_config['thresholds'][$metric] ?? null;
                if ($threshold_config && $value > $threshold_config['critical']) {
                    $alert = [
                        'level' => 'critical',
                        'monitor' => $monitor_name,
                        'message' => sprintf("Metric '%s' exceeded critical threshold: %s > %s", 
                            $metric, $value, $threshold_config['critical']),
                        'data' => $result
                    ];
                    break;
                } elseif ($threshold_config && $value > $threshold_config['warning']) {
                    $alert = [
                        'level' => 'warning',
                        'monitor' => $monitor_name,
                        'message' => sprintf("Metric '%s' exceeded warning threshold: %s > %s", 
                            $metric, $value, $threshold_config['warning']),
                        'data' => $result
                    ];
                }
            }
        }
        
        return $alert;
    }
    
    /**
     * Send alert notification
     */
    private function send_alert($alert) {
        $alert_config = get_option('wcefp_alert_config', []);
        $notification_config = $alert_config['notifications'] ?? [];
        
        // Prevent spam - check if similar alert was sent recently
        if ($this->is_alert_throttled($alert)) {
            return;
        }
        
        // Record alert
        $this->record_alert($alert);
        
        // Send email notifications
        if ($notification_config['email']['enabled'] ?? false) {
            $this->send_email_alert($alert, $notification_config['email']);
        }
        
        // Send Slack notifications
        if ($notification_config['slack']['enabled'] ?? false) {
            $this->send_slack_alert($alert, $notification_config['slack']);
        }
        
        // Send webhook notifications
        if ($notification_config['webhook']['enabled'] ?? false) {
            $this->send_webhook_alert($alert, $notification_config['webhook']);
        }
        
        // Store in real-time system for dashboard
        if (class_exists('WCEFP_Realtime_Features')) {
            $realtime = WCEFP_Realtime_Features::get_instance();
            $realtime->queue_global_update([
                'type' => 'monitoring_alert',
                'level' => $alert['level'],
                'monitor' => $alert['monitor'],
                'message' => $alert['message'],
                'timestamp' => current_time('mysql')
            ]);
        }
    }
    
    /**
     * Check if alert is throttled (to prevent spam)
     */
    private function is_alert_throttled($alert) {
        $throttle_key = sprintf('wcefp_alert_throttle_%s_%s', 
            $alert['monitor'], 
            md5($alert['message'])
        );
        
        $last_sent = get_transient($throttle_key);
        if ($last_sent) {
            return true; // Alert was sent recently
        }
        
        // Set throttle (prevent same alert for 30 minutes)
        $throttle_duration = apply_filters('wcefp_alert_throttle_duration', 1800);
        set_transient($throttle_key, time(), $throttle_duration);
        
        return false;
    }
    
    /**
     * Record alert in database
     */
    private function record_alert($alert) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wcefp_monitoring_alerts';
        
        // Create table if it doesn't exist
        $this->create_alerts_table();
        
        $wpdb->insert($table, [
            'level' => $alert['level'],
            'monitor' => $alert['monitor'],
            'message' => $alert['message'],
            'data' => json_encode($alert['data']),
            'created_at' => current_time('mysql'),
            'status' => 'active'
        ]);
    }
    
    /**
     * Send email alert
     */
    private function send_email_alert($alert, $email_config) {
        $to = $email_config['recipients'] ?? [get_option('admin_email')];
        $subject = sprintf('[WCEventsFP %s Alert] %s', 
            strtoupper($alert['level']), 
            $alert['monitor']
        );
        
        $message = sprintf(
            "A %s level alert has been triggered in WCEventsFP monitoring.\n\n" .
            "Monitor: %s\n" .
            "Message: %s\n" .
            "Time: %s\n\n" .
            "Additional Data:\n%s\n\n" .
            "Please investigate this issue promptly.",
            $alert['level'],
            $alert['monitor'],
            $alert['message'],
            current_time('mysql'),
            json_encode($alert['data'], JSON_PRETTY_PRINT)
        );
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        foreach ($to as $recipient) {
            wp_mail($recipient, $subject, $message, $headers);
        }
    }
    
    /**
     * Send Slack alert
     */
    private function send_slack_alert($alert, $slack_config) {
        $webhook_url = $slack_config['webhook_url'] ?? '';
        if (empty($webhook_url)) {
            return;
        }
        
        $color = $alert['level'] === 'critical' ? 'danger' : 'warning';
        
        $payload = [
            'channel' => $slack_config['channel'] ?? '#monitoring',
            'username' => 'WCEventsFP Monitor',
            'icon_emoji' => ':warning:',
            'attachments' => [[
                'color' => $color,
                'title' => sprintf('%s Alert: %s', strtoupper($alert['level']), $alert['monitor']),
                'text' => $alert['message'],
                'fields' => [
                    [
                        'title' => 'Monitor',
                        'value' => $alert['monitor'],
                        'short' => true
                    ],
                    [
                        'title' => 'Level',
                        'value' => strtoupper($alert['level']),
                        'short' => true
                    ],
                    [
                        'title' => 'Time',
                        'value' => current_time('mysql'),
                        'short' => false
                    ]
                ],
                'timestamp' => time()
            ]]
        ];
        
        wp_remote_post($webhook_url, [
            'body' => json_encode($payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 10
        ]);
    }
    
    /**
     * Get monitoring data for dashboard
     */
    public function get_monitoring_data() {
        check_ajax_referer('wcefp_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => __('No permission', 'wceventsfp')]);
        }
        
        $results = get_option('wcefp_monitoring_results', []);
        $last_check = get_option('wcefp_monitoring_last_check');
        $recent_alerts = $this->get_recent_alerts(50);
        
        // Get system metrics
        $system_metrics = [
            'php_memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'php_memory_limit' => ini_get('memory_limit'),
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => WCEFP_VERSION,
            'active_plugins' => count(get_option('active_plugins', [])),
            'database_size' => $this->get_database_size(),
            'cache_status' => $this->get_cache_status()
        ];
        
        wp_send_json_success([
            'monitoring_results' => $results,
            'last_check' => $last_check,
            'recent_alerts' => $recent_alerts,
            'system_metrics' => $system_metrics,
            'health_score' => $this->calculate_health_score($results)
        ]);
    }
    
    /**
     * Calculate overall health score
     */
    private function calculate_health_score($results) {
        $total_monitors = count($results);
        if ($total_monitors === 0) {
            return 0;
        }
        
        $healthy_monitors = 0;
        foreach ($results as $result) {
            if ($result['status'] === 'healthy' || $result['status'] === 'ok') {
                $healthy_monitors++;
            }
        }
        
        return round(($healthy_monitors / $total_monitors) * 100);
    }
    
    /**
     * Get recent alerts
     */
    private function get_recent_alerts($limit = 20) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wcefp_monitoring_alerts';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
        
        return $results ?: [];
    }
    
    /**
     * Create alerts table
     */
    private function create_alerts_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wcefp_monitoring_alerts';
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            monitor varchar(50) NOT NULL,
            message text NOT NULL,
            data longtext,
            created_at datetime NOT NULL,
            resolved_at datetime NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY (id),
            KEY level (level),
            KEY monitor (monitor),
            KEY created_at (created_at),
            KEY status (status)
        ) $charset;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get database size
     */
    private function get_database_size() {
        global $wpdb;
        
        $result = $wpdb->get_var(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' 
             FROM information_schema.tables 
             WHERE table_schema='{$wpdb->dbname}'"
        );
        
        return $result ? $result . ' MB' : 'Unknown';
    }
    
    /**
     * Get cache status
     */
    private function get_cache_status() {
        if (function_exists('wp_cache_get')) {
            return 'Object Cache Active';
        } elseif (get_transient('wcefp_cache_test') !== false) {
            return 'Transient Cache Active';
        } else {
            set_transient('wcefp_cache_test', true, 60);
            return 'Basic Cache Available';
        }
    }
    
    /**
     * Record performance metrics
     */
    public function record_performance_metrics() {
        $metrics = [
            'memory_peak' => memory_get_peak_usage(true),
            'memory_current' => memory_get_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'query_count' => get_num_queries(),
            'timestamp' => time()
        ];
        
        // Store in transient for short-term tracking
        $recent_metrics = get_transient('wcefp_performance_metrics') ?: [];
        $recent_metrics[] = $metrics;
        
        // Keep only last 100 requests
        if (count($recent_metrics) > 100) {
            $recent_metrics = array_slice($recent_metrics, -100);
        }
        
        set_transient('wcefp_performance_metrics', $recent_metrics, 3600);
    }
    
    /**
     * Initialize monitoring system
     */
    public static function init() {
        return self::get_instance();
    }
}

// Initialize monitoring system
WCEFP_Advanced_Monitoring::init();