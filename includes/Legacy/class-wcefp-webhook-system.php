<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Enhanced Webhook System
 * Advanced webhook management for third-party integrations
 */
class WCEFP_Webhook_System {
    
    private static $instance = null;
    private $registered_webhooks = [];
    private $webhook_queue = [];
    private $retry_attempts = 3;
    private $retry_delay = 300; // 5 minutes
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->schedule_webhook_processor();
    }
    
    /**
     * Initialize hooks and filters
     */
    private function init_hooks() {
        // Admin hooks
        add_action('wp_ajax_wcefp_test_webhook', [$this, 'ajax_test_webhook']);
        add_action('wp_ajax_wcefp_webhook_logs', [$this, 'ajax_webhook_logs']);
        add_action('wp_ajax_wcefp_retry_webhook', [$this, 'ajax_retry_webhook']);
        
        // Booking lifecycle hooks
        add_action('wcefp_booking_created', [$this, 'trigger_booking_created'], 10, 2);
        add_action('wcefp_booking_confirmed', [$this, 'trigger_booking_confirmed'], 10, 2);
        add_action('wcefp_booking_cancelled', [$this, 'trigger_booking_cancelled'], 10, 2);
        add_action('wcefp_booking_completed', [$this, 'trigger_booking_completed'], 10, 2);
        
        // Product/Experience hooks
        add_action('wcefp_product_updated', [$this, 'trigger_product_updated'], 10, 2);
        add_action('wcefp_availability_changed', [$this, 'trigger_availability_changed'], 10, 2);
        
        // Payment hooks
        add_action('wcefp_payment_received', [$this, 'trigger_payment_received'], 10, 2);
        add_action('wcefp_payment_failed', [$this, 'trigger_payment_failed'], 10, 2);
        
        // Review hooks
        add_action('wcefp_review_submitted', [$this, 'trigger_review_submitted'], 10, 2);
        
        // Schedule webhook processor
        add_action('wcefp_process_webhook_queue', [$this, 'process_webhook_queue']);
        
        // Cleanup old webhook logs
        add_action('wcefp_cleanup_webhook_logs', [$this, 'cleanup_old_logs']);
    }
    
    /**
     * Register a webhook endpoint
     */
    public function register_webhook($event, $url, $options = []) {
        $webhook = [
            'event' => $event,
            'url' => $url,
            'method' => $options['method'] ?? 'POST',
            'headers' => $options['headers'] ?? [],
            'secret' => $options['secret'] ?? '',
            'active' => $options['active'] ?? true,
            'timeout' => $options['timeout'] ?? 30,
            'retry_attempts' => $options['retry_attempts'] ?? $this->retry_attempts,
            'created_at' => current_time('mysql')
        ];
        
        $webhook_id = $this->store_webhook($webhook);
        $this->registered_webhooks[$webhook_id] = $webhook;
        
        return $webhook_id;
    }
    
    /**
     * Trigger webhook for specific event
     */
    public function trigger_webhook($event, $data, $context = []) {
        $webhooks = $this->get_webhooks_for_event($event);
        
        foreach ($webhooks as $webhook) {
            if (!$webhook['active']) {
                continue;
            }
            
            $payload = $this->prepare_payload($event, $data, $context, $webhook);
            $this->queue_webhook($webhook, $payload);
        }
        
        // Process queue immediately for high-priority events
        $high_priority_events = ['booking_created', 'payment_received', 'booking_cancelled'];
        if (in_array($event, $high_priority_events)) {
            $this->process_webhook_queue();
        }
    }
    
    /**
     * Prepare webhook payload
     */
    private function prepare_payload($event, $data, $context, $webhook) {
        $payload = [
            'event' => $event,
            'timestamp' => current_time('timestamp'),
            'version' => WCEFP_VERSION,
            'data' => $data,
            'context' => $context
        ];
        
        // Add signature if secret is provided
        if (!empty($webhook['secret'])) {
            $payload['signature'] = $this->generate_signature($payload, $webhook['secret']);
        }
        
        // Add webhook metadata
        $payload['webhook'] = [
            'id' => $webhook['id'] ?? null,
            'event' => $webhook['event'],
            'retry_count' => 0
        ];
        
        return apply_filters('wcefp_webhook_payload', $payload, $event, $webhook);
    }
    
    /**
     * Generate webhook signature
     */
    private function generate_signature($payload, $secret) {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }
    
    /**
     * Queue webhook for processing
     */
    private function queue_webhook($webhook, $payload) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_webhook_queue';
        $this->create_webhook_tables();
        
        $wpdb->insert(
            $table_name,
            [
                'webhook_id' => $webhook['id'] ?? 0,
                'webhook_url' => $webhook['url'],
                'payload' => json_encode($payload),
                'method' => $webhook['method'],
                'headers' => json_encode($webhook['headers']),
                'timeout' => $webhook['timeout'],
                'retry_attempts' => $webhook['retry_attempts'],
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );
        
        // Log the queued webhook
        $this->log_webhook_event('queued', $webhook['url'], $payload);
    }
    
    /**
     * Process webhook queue
     */
    public function process_webhook_queue() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_webhook_queue';
        
        // Get pending webhooks
        $pending_webhooks = $wpdb->get_results(
            "SELECT * FROM $table_name 
             WHERE status IN ('pending', 'failed') 
             AND (next_retry <= NOW() OR next_retry IS NULL)
             ORDER BY created_at ASC 
             LIMIT 10"
        );
        
        foreach ($pending_webhooks as $webhook) {
            $this->send_webhook($webhook);
        }
    }
    
    /**
     * Send individual webhook
     */
    private function send_webhook($webhook_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_webhook_queue';
        
        try {
            $payload = json_decode($webhook_data->payload, true);
            $headers = json_decode($webhook_data->headers, true) ?: [];
            
            // Add content type
            $headers['Content-Type'] = 'application/json';
            
            // Prepare request arguments
            $args = [
                'method' => $webhook_data->method,
                'timeout' => $webhook_data->timeout,
                'headers' => $headers,
                'body' => json_encode($payload),
                'user-agent' => 'WCEventsFP/' . WCEFP_VERSION
            ];
            
            // Send request
            $response = wp_remote_request($webhook_data->webhook_url, $args);
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code >= 200 && $response_code < 300) {
                // Success
                $wpdb->update(
                    $table_name,
                    [
                        'status' => 'completed',
                        'response_code' => $response_code,
                        'response_body' => $response_body,
                        'completed_at' => current_time('mysql')
                    ],
                    ['id' => $webhook_data->id],
                    ['%s', '%d', '%s', '%s'],
                    ['%d']
                );
                
                $this->log_webhook_event('success', $webhook_data->webhook_url, $payload, [
                    'response_code' => $response_code,
                    'response_body' => $response_body
                ]);
                
            } else {
                throw new Exception("HTTP {$response_code}: {$response_body}");
            }
            
        } catch (Exception $e) {
            // Handle failure
            $retry_count = intval($webhook_data->retry_count) + 1;
            
            if ($retry_count < $webhook_data->retry_attempts) {
                // Schedule retry
                $next_retry = date('Y-m-d H:i:s', time() + ($this->retry_delay * $retry_count));
                
                $wpdb->update(
                    $table_name,
                    [
                        'status' => 'failed',
                        'retry_count' => $retry_count,
                        'error_message' => $e->getMessage(),
                        'next_retry' => $next_retry
                    ],
                    ['id' => $webhook_data->id],
                    ['%s', '%d', '%s', '%s'],
                    ['%d']
                );
                
                $this->log_webhook_event('retry_scheduled', $webhook_data->webhook_url, 
                    json_decode($webhook_data->payload, true), [
                        'error' => $e->getMessage(),
                        'retry_count' => $retry_count,
                        'next_retry' => $next_retry
                    ]);
                
            } else {
                // Max retries reached
                $wpdb->update(
                    $table_name,
                    [
                        'status' => 'failed_permanent',
                        'retry_count' => $retry_count,
                        'error_message' => $e->getMessage(),
                        'completed_at' => current_time('mysql')
                    ],
                    ['id' => $webhook_data->id],
                    ['%s', '%d', '%s', '%s'],
                    ['%d']
                );
                
                $this->log_webhook_event('failed_permanent', $webhook_data->webhook_url, 
                    json_decode($webhook_data->payload, true), [
                        'error' => $e->getMessage(),
                        'retry_count' => $retry_count
                    ]);
            }
        }
    }
    
    /**
     * Booking event handlers
     */
    public function trigger_booking_created($booking_id, $booking_data) {
        $this->trigger_webhook('booking_created', [
            'booking_id' => $booking_id,
            'booking' => $booking_data,
            'customer' => $this->get_customer_data($booking_data['user_id'] ?? 0)
        ]);
    }
    
    public function trigger_booking_confirmed($booking_id, $booking_data) {
        $this->trigger_webhook('booking_confirmed', [
            'booking_id' => $booking_id,
            'booking' => $booking_data
        ]);
    }
    
    public function trigger_booking_cancelled($booking_id, $booking_data) {
        $this->trigger_webhook('booking_cancelled', [
            'booking_id' => $booking_id,
            'booking' => $booking_data,
            'cancellation_reason' => $booking_data['cancellation_reason'] ?? ''
        ]);
    }
    
    public function trigger_booking_completed($booking_id, $booking_data) {
        $this->trigger_webhook('booking_completed', [
            'booking_id' => $booking_id,
            'booking' => $booking_data
        ]);
    }
    
    public function trigger_product_updated($product_id, $product_data) {
        $this->trigger_webhook('product_updated', [
            'product_id' => $product_id,
            'product' => $product_data
        ]);
    }
    
    public function trigger_availability_changed($product_id, $availability_data) {
        $this->trigger_webhook('availability_changed', [
            'product_id' => $product_id,
            'availability' => $availability_data
        ]);
    }
    
    public function trigger_payment_received($payment_id, $payment_data) {
        $this->trigger_webhook('payment_received', [
            'payment_id' => $payment_id,
            'payment' => $payment_data
        ]);
    }
    
    public function trigger_payment_failed($payment_id, $payment_data) {
        $this->trigger_webhook('payment_failed', [
            'payment_id' => $payment_id,
            'payment' => $payment_data,
            'error' => $payment_data['error'] ?? ''
        ]);
    }
    
    public function trigger_review_submitted($review_id, $review_data) {
        $this->trigger_webhook('review_submitted', [
            'review_id' => $review_id,
            'review' => $review_data
        ]);
    }
    
    /**
     * Get customer data for webhook payload
     */
    private function get_customer_data($user_id) {
        if (!$user_id) {
            return null;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return null;
        }
        
        return [
            'id' => $user_id,
            'email' => $user->user_email,
            'first_name' => get_user_meta($user_id, 'first_name', true),
            'last_name' => get_user_meta($user_id, 'last_name', true),
            'display_name' => $user->display_name
        ];
    }
    
    /**
     * Get webhooks for specific event
     */
    private function get_webhooks_for_event($event) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_webhooks';
        $this->create_webhook_tables();
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE event = %s AND active = 1",
            $event
        ), ARRAY_A);
    }
    
    /**
     * Store webhook in database
     */
    private function store_webhook($webhook) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_webhooks';
        $this->create_webhook_tables();
        
        $wpdb->insert(
            $table_name,
            [
                'event' => $webhook['event'],
                'url' => $webhook['url'],
                'method' => $webhook['method'],
                'headers' => json_encode($webhook['headers']),
                'secret' => $webhook['secret'],
                'active' => $webhook['active'] ? 1 : 0,
                'timeout' => $webhook['timeout'],
                'retry_attempts' => $webhook['retry_attempts'],
                'created_at' => $webhook['created_at']
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create webhook database tables
     */
    private function create_webhook_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Webhooks table
        $webhooks_table = $wpdb->prefix . 'wcefp_webhooks';
        $sql1 = "CREATE TABLE IF NOT EXISTS $webhooks_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event VARCHAR(100) NOT NULL,
            url TEXT NOT NULL,
            method VARCHAR(10) DEFAULT 'POST',
            headers LONGTEXT,
            secret VARCHAR(255),
            active BOOLEAN DEFAULT TRUE,
            timeout INT DEFAULT 30,
            retry_attempts INT DEFAULT 3,
            created_at DATETIME NOT NULL,
            INDEX (event),
            INDEX (active)
        ) $charset_collate;";
        
        // Webhook queue table
        $queue_table = $wpdb->prefix . 'wcefp_webhook_queue';
        $sql2 = "CREATE TABLE IF NOT EXISTS $queue_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            webhook_id BIGINT UNSIGNED DEFAULT 0,
            webhook_url TEXT NOT NULL,
            payload LONGTEXT NOT NULL,
            method VARCHAR(10) NOT NULL,
            headers LONGTEXT,
            timeout INT DEFAULT 30,
            retry_attempts INT DEFAULT 3,
            retry_count INT DEFAULT 0,
            status ENUM('pending', 'completed', 'failed', 'failed_permanent') DEFAULT 'pending',
            response_code INT,
            response_body LONGTEXT,
            error_message TEXT,
            next_retry DATETIME,
            created_at DATETIME NOT NULL,
            completed_at DATETIME,
            INDEX (status),
            INDEX (next_retry),
            INDEX (created_at)
        ) $charset_collate;";
        
        // Webhook logs table
        $logs_table = $wpdb->prefix . 'wcefp_webhook_logs';
        $sql3 = "CREATE TABLE IF NOT EXISTS $logs_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            webhook_url TEXT NOT NULL,
            payload LONGTEXT,
            response_data LONGTEXT,
            created_at DATETIME NOT NULL,
            INDEX (event_type),
            INDEX (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
    }
    
    /**
     * Log webhook event
     */
    private function log_webhook_event($event_type, $url, $payload, $response = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_webhook_logs';
        $this->create_webhook_tables();
        
        $wpdb->insert(
            $table_name,
            [
                'event_type' => $event_type,
                'webhook_url' => $url,
                'payload' => json_encode($payload),
                'response_data' => $response ? json_encode($response) : null,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Schedule webhook processor
     */
    private function schedule_webhook_processor() {
        if (!wp_next_scheduled('wcefp_process_webhook_queue')) {
            wp_schedule_event(time(), 'every_minute', 'wcefp_process_webhook_queue');
        }
        
        if (!wp_next_scheduled('wcefp_cleanup_webhook_logs')) {
            wp_schedule_event(time(), 'daily', 'wcefp_cleanup_webhook_logs');
        }
        
        // Add custom cron interval
        add_filter('cron_schedules', function($schedules) {
            $schedules['every_minute'] = [
                'interval' => 60,
                'display' => __('Every Minute', 'wceventsfp')
            ];
            return $schedules;
        });
    }
    
    /**
     * Cleanup old webhook logs
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'wcefp_webhook_logs';
        $queue_table = $wpdb->prefix . 'wcefp_webhook_queue';
        
        // Delete logs older than 30 days
        $wpdb->query("DELETE FROM $logs_table WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        // Delete completed queue items older than 7 days
        $wpdb->query("DELETE FROM $queue_table WHERE status = 'completed' AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        
        // Delete permanently failed items older than 30 days
        $wpdb->query("DELETE FROM $queue_table WHERE status = 'failed_permanent' AND completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_test_webhook() {
        check_ajax_referer('wcefp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $url = sanitize_url($_POST['url'] ?? '');
        $event = sanitize_text_field($_POST['event'] ?? '');
        
        if (empty($url) || empty($event)) {
            wp_send_json_error('URL and event are required');
        }
        
        // Send test payload
        $test_payload = [
            'event' => $event,
            'timestamp' => current_time('timestamp'),
            'version' => WCEFP_VERSION,
            'data' => ['test' => true, 'message' => 'This is a test webhook'],
            'context' => ['source' => 'admin_test']
        ];
        
        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($test_payload)
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        wp_send_json_success([
            'response_code' => wp_remote_retrieve_response_code($response),
            'response_body' => wp_remote_retrieve_body($response)
        ]);
    }
    
    public function ajax_webhook_logs() {
        check_ajax_referer('wcefp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_webhook_logs';
        
        $page = intval($_POST['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        wp_send_json_success([
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'total_pages' => ceil($total / $limit)
        ]);
    }
    
    public function ajax_retry_webhook() {
        check_ajax_referer('wcefp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $queue_id = intval($_POST['queue_id'] ?? 0);
        if (!$queue_id) {
            wp_send_json_error('Invalid queue ID');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_webhook_queue';
        
        $wpdb->update(
            $table_name,
            [
                'status' => 'pending',
                'retry_count' => 0,
                'next_retry' => null,
                'error_message' => null
            ],
            ['id' => $queue_id],
            ['%s', '%d', '%s', '%s'],
            ['%d']
        );
        
        wp_send_json_success(['message' => 'Webhook queued for retry']);
    }
    
    /**
     * Get webhook statistics
     */
    public function get_webhook_stats() {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'wcefp_webhook_queue';
        $logs_table = $wpdb->prefix . 'wcefp_webhook_logs';
        
        return [
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'pending'"),
            'completed' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'completed'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status IN ('failed', 'failed_permanent')"),
            'total_logs' => $wpdb->get_var("SELECT COUNT(*) FROM $logs_table"),
            'success_rate' => $this->calculate_success_rate()
        ];
    }
    
    /**
     * Calculate success rate
     */
    private function calculate_success_rate() {
        global $wpdb;
        
        $queue_table = $wpdb->prefix . 'wcefp_webhook_queue';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status != 'pending'");
        $completed = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table WHERE status = 'completed'");
        
        if ($total == 0) {
            return 0;
        }
        
        return round(($completed / $total) * 100, 2);
    }
    
    /**
     * Initialize webhook system
     */
    public static function init() {
        return self::get_instance();
    }
}

// Initialize webhook system
WCEFP_Webhook_System::init();