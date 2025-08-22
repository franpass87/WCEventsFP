<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Real-time Features
 * Provides real-time updates for bookings, availability, and notifications
 */
class WCEFP_Realtime_Features {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX handlers for real-time features
        add_action('wp_ajax_wcefp_realtime_connect', [$this, 'handle_realtime_connect']);
        add_action('wp_ajax_nopriv_wcefp_realtime_connect', [$this, 'handle_realtime_connect']);
        add_action('wp_ajax_wcefp_get_realtime_updates', [$this, 'get_realtime_updates']);
        add_action('wp_ajax_nopriv_wcefp_get_realtime_updates', [$this, 'get_realtime_updates']);
        add_action('wp_ajax_wcefp_push_notification', [$this, 'push_notification']);
        
        // Hooks for real-time events
        add_action('wcefp_booking_created', [$this, 'broadcast_booking_update'], 10, 2);
        add_action('wcefp_occurrence_updated', [$this, 'broadcast_availability_update'], 10, 2);
        
        // Schedule cleanup of old realtime data
        add_action('wp', [$this, 'schedule_cleanup']);
        add_action('wcefp_cleanup_realtime_data', [$this, 'cleanup_realtime_data']);
        
        // Enqueue real-time JavaScript
        add_action('wp_enqueue_scripts', [$this, 'enqueue_realtime_assets']);
    }
    
    /**
     * Enqueue real-time JavaScript assets
     */
    public function enqueue_realtime_assets() {
        wp_register_script(
            'wcefp-realtime',
            WCEFP_PLUGIN_URL . 'assets/js/realtime-features.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-realtime', 'WCEFPRealtime', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_realtime'),
            'pollInterval' => apply_filters('wcefp_realtime_poll_interval', 5000), // 5 seconds default
            'maxReconnectAttempts' => apply_filters('wcefp_realtime_max_reconnects', 5),
            'reconnectDelay' => apply_filters('wcefp_realtime_reconnect_delay', 1000)
        ]);
        
        wp_enqueue_script('wcefp-realtime');
    }
    
    /**
     * Handle real-time connection establishment
     */
    public function handle_realtime_connect() {
        check_ajax_referer('wcefp_realtime', 'nonce');
        
        $session_id = $this->generate_session_id();
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        
        // Store connection info
        $connection_data = [
            'session_id' => $session_id,
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'connected_at' => current_time('mysql'),
            'last_seen' => current_time('mysql'),
            'status' => 'active'
        ];
        
        set_transient("wcefp_realtime_conn_$session_id", $connection_data, 3600);
        
        // Log connection
        WCEFP_Logger::info('Real-time connection established', [
            'session_id' => $session_id,
            'user_id' => $user_id,
            'ip' => $ip_address
        ]);
        
        wp_send_json_success([
            'session_id' => $session_id,
            'message' => __('Real-time connection established', 'wceventsfp')
        ]);
    }
    
    /**
     * Get real-time updates for a session
     */
    public function get_realtime_updates() {
        check_ajax_referer('wcefp_realtime', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        
        if (empty($session_id)) {
            wp_send_json_error(['msg' => __('Invalid session', 'wceventsfp')]);
        }
        
        // Verify session exists
        $connection_data = get_transient("wcefp_realtime_conn_$session_id");
        if (!$connection_data) {
            wp_send_json_error(['msg' => __('Session expired', 'wceventsfp')]);
        }
        
        // Update last seen
        $connection_data['last_seen'] = current_time('mysql');
        set_transient("wcefp_realtime_conn_$session_id", $connection_data, 3600);
        
        // Get pending updates for this session
        $updates = $this->get_pending_updates($session_id);
        
        // Get global updates (for all sessions)
        $global_updates = $this->get_global_updates();
        
        $all_updates = array_merge($updates, $global_updates);
        
        wp_send_json_success([
            'updates' => $all_updates,
            'timestamp' => current_time('mysql'),
            'session_id' => $session_id
        ]);
    }
    
    /**
     * Broadcast booking update to all connected sessions
     */
    public function broadcast_booking_update($booking_id, $booking_data) {
        $update = [
            'type' => 'booking_update',
            'booking_id' => $booking_id,
            'product_id' => $booking_data['product_id'] ?? null,
            'occurrence_id' => $booking_data['occurrence_id'] ?? null,
            'status' => $booking_data['status'] ?? 'confirmed',
            'message' => sprintf(
                __('New booking received for %s', 'wceventsfp'),
                get_the_title($booking_data['product_id'] ?? '')
            ),
            'timestamp' => current_time('mysql')
        ];
        
        $this->queue_global_update($update);
        
        // Also trigger availability update
        if (!empty($booking_data['occurrence_id'])) {
            $this->broadcast_availability_update($booking_data['occurrence_id'], null);
        }
    }
    
    /**
     * Broadcast availability update
     */
    public function broadcast_availability_update($occurrence_id, $occurrence_data) {
        global $wpdb;
        
        // Get updated occurrence data
        $occurrence = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_occurrences WHERE id = %d",
            $occurrence_id
        ));
        
        if ($occurrence) {
            $available_spots = $occurrence->capacity - $occurrence->booked;
            
            $update = [
                'type' => 'availability_update',
                'occurrence_id' => $occurrence_id,
                'product_id' => $occurrence->product_id,
                'capacity' => $occurrence->capacity,
                'booked' => $occurrence->booked,
                'available' => $available_spots,
                'status' => $occurrence->status,
                'timestamp' => current_time('mysql')
            ];
            
            $this->queue_global_update($update);
        }
    }
    
    /**
     * Push notification to specific users or sessions
     */
    public function push_notification() {
        check_ajax_referer('wcefp_realtime', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => __('No permission', 'wceventsfp')]);
        }
        
        $message = sanitize_text_field($_POST['message'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'info');
        $target_sessions = $_POST['target_sessions'] ?? [];
        
        if (empty($message)) {
            wp_send_json_error(['msg' => __('Message is required', 'wceventsfp')]);
        }
        
        $notification = [
            'type' => 'notification',
            'notification_type' => $type,
            'message' => $message,
            'timestamp' => current_time('mysql'),
            'id' => uniqid('wcefp_notif_')
        ];
        
        if (empty($target_sessions)) {
            // Broadcast to all sessions
            $this->queue_global_update($notification);
        } else {
            // Send to specific sessions
            foreach ($target_sessions as $session_id) {
                $this->queue_session_update($session_id, $notification);
            }
        }
        
        wp_send_json_success(['message' => __('Notification sent', 'wceventsfp')]);
    }
    
    /**
     * Queue update for all sessions
     */
    private function queue_global_update($update) {
        $global_updates = get_option('wcefp_global_realtime_updates', []);
        $global_updates[] = $update;
        
        // Keep only last 50 updates to prevent memory issues
        if (count($global_updates) > 50) {
            $global_updates = array_slice($global_updates, -50);
        }
        
        update_option('wcefp_global_realtime_updates', $global_updates);
    }
    
    /**
     * Queue update for specific session
     */
    private function queue_session_update($session_id, $update) {
        $updates = get_transient("wcefp_realtime_updates_$session_id") ?: [];
        $updates[] = $update;
        
        // Keep only last 20 updates per session
        if (count($updates) > 20) {
            $updates = array_slice($updates, -20);
        }
        
        set_transient("wcefp_realtime_updates_$session_id", $updates, 1800); // 30 minutes
    }
    
    /**
     * Get pending updates for session
     */
    private function get_pending_updates($session_id) {
        $updates = get_transient("wcefp_realtime_updates_$session_id") ?: [];
        
        // Clear updates after retrieval
        delete_transient("wcefp_realtime_updates_$session_id");
        
        return $updates;
    }
    
    /**
     * Get global updates (newer than last check)
     */
    private function get_global_updates() {
        $global_updates = get_option('wcefp_global_realtime_updates', []);
        
        // Filter updates from last 5 minutes to avoid sending old data
        $cutoff_time = strtotime('-5 minutes');
        $recent_updates = array_filter($global_updates, function($update) use ($cutoff_time) {
            return strtotime($update['timestamp']) > $cutoff_time;
        });
        
        return array_values($recent_updates);
    }
    
    /**
     * Generate unique session ID
     */
    private function generate_session_id() {
        return wp_generate_uuid4();
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Schedule cleanup of old realtime data
     */
    public function schedule_cleanup() {
        if (!wp_next_scheduled('wcefp_cleanup_realtime_data')) {
            wp_schedule_event(time(), 'hourly', 'wcefp_cleanup_realtime_data');
        }
    }
    
    /**
     * Cleanup old realtime data
     */
    public function cleanup_realtime_data() {
        // Clean up global updates older than 1 hour
        $global_updates = get_option('wcefp_global_realtime_updates', []);
        $cutoff_time = strtotime('-1 hour');
        
        $recent_updates = array_filter($global_updates, function($update) use ($cutoff_time) {
            return strtotime($update['timestamp']) > $cutoff_time;
        });
        
        update_option('wcefp_global_realtime_updates', array_values($recent_updates));
        
        WCEFP_Logger::info('Realtime data cleanup completed', [
            'removed_updates' => count($global_updates) - count($recent_updates)
        ]);
    }
    
    /**
     * Get active connections count
     */
    public function get_active_connections_count() {
        global $wpdb;
        
        // This is a simplified version - in a real implementation you'd query
        // active transients or use a proper database table
        $count = 0;
        
        // Get all connection transients (this is not efficient for large scale)
        $transients = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wcefp_realtime_conn_%'"
        );
        
        foreach ($transients as $transient) {
            $session_id = str_replace('_transient_wcefp_realtime_conn_', '', $transient->option_name);
            $data = get_transient("wcefp_realtime_conn_$session_id");
            
            if ($data && strtotime($data['last_seen']) > strtotime('-5 minutes')) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Initialize real-time features
     */
    public static function init() {
        return self::get_instance();
    }
}

// Initialize real-time features
WCEFP_Realtime_Features::init();