<?php
/**
 * Stock Hold Manager
 * 
 * Manages temporary capacity reservations to prevent overbooking
 * 
 * @package WCEFP
 * @subpackage Services\Domain
 * @since 2.2.0
 */

namespace WCEFP\Services\Domain;

use WCEFP\Core\Database\DatabaseManager;
use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stock Hold Manager Class
 */
class StockHoldManager {
    
    /**
     * Default hold duration in minutes
     */
    const DEFAULT_HOLD_DURATION = 15;
    
    /**
     * Maximum holds per session
     */
    const MAX_HOLDS_PER_SESSION = 10;
    
    /**
     * Concurrency lock timeout in seconds
     */
    const LOCK_TIMEOUT = 5;
    
    /**
     * Create a stock hold for capacity with enhanced concurrency protection
     * 
     * @param int $occurrence_id Occurrence ID
     * @param string $ticket_key Ticket type key
     * @param int $quantity Quantity to hold
     * @param string|null $session_id Session ID (auto-generated if null)
     * @return array Result with success status and hold data
     */
    public function create_hold($occurrence_id, $ticket_key, $quantity, $session_id = null) {
        global $wpdb;
        
        if (!$session_id) {
            $session_id = $this->get_session_id();
        }
        
        // Validate parameters
        if (!$this->validate_hold_request($occurrence_id, $ticket_key, $quantity, $session_id)) {
            Logger::log('warning', 'Invalid hold request parameters', [
                'occurrence_id' => $occurrence_id,
                'ticket_key' => $ticket_key,
                'quantity' => $quantity,
                'session_id' => $session_id
            ]);
            return ['success' => false, 'error' => 'invalid_parameters'];
        }
        
        // Enhanced concurrency protection with database locks
        $lock_name = "wcefp_hold_{$occurrence_id}_{$ticket_key}";
        $lock_acquired = $this->acquire_lock($lock_name);
        
        if (!$lock_acquired) {
            Logger::log('warning', 'Failed to acquire hold lock - high concurrency detected', [
                'lock_name' => $lock_name,
                'session_id' => $session_id
            ]);
            return ['success' => false, 'error' => 'lock_timeout'];
        }
        
        try {
            // Double-check available capacity under lock
            $available_capacity = $this->get_available_capacity($occurrence_id, $ticket_key);
            if ($available_capacity < $quantity) {
                Logger::log('info', 'Insufficient capacity detected', [
                    'occurrence_id' => $occurrence_id,
                    'ticket_key' => $ticket_key,
                    'requested' => $quantity,
                    'available' => $available_capacity,
                    'session_id' => $session_id
                ]);
                return [
                    'success' => false, 
                    'error' => 'insufficient_capacity',
                    'available' => $available_capacity
                ];
            }
            
            // Check session hold limits
            if ($this->get_session_holds_count($session_id) >= self::MAX_HOLDS_PER_SESSION) {
                Logger::log('warning', 'Session hold limit exceeded', [
                    'session_id' => $session_id,
                    'current_holds' => $this->get_session_holds_count($session_id)
                ]);
                return ['success' => false, 'error' => 'max_holds_exceeded'];
            }
            
            $holds_table = DatabaseManager::get_table_name('stock_holds');
            $expires_at = $this->calculate_expiry_time();
            
            // Start transaction with isolation level for better concurrency
            $wpdb->query('START TRANSACTION');
            $wpdb->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            
            // Check if hold already exists for this session/occurrence/ticket
            $existing_hold = $wpdb->get_row($wpdb->prepare("
                SELECT id, quantity, expires_at 
                FROM {$holds_table}
                WHERE session_id = %s AND occurrence_id = %d AND ticket_key = %s
                AND expires_at > NOW()
                FOR UPDATE
            ", $session_id, $occurrence_id, $ticket_key));
            
            if ($existing_hold) {
                // Update existing hold
                $new_quantity = $existing_hold->quantity + $quantity;
                $new_expires_at = max($existing_hold->expires_at, $expires_at);
                
                // Triple-check capacity with existing hold excluded
                $total_available = $this->get_available_capacity($occurrence_id, $ticket_key, $existing_hold->id);
                if ($total_available < $new_quantity) {
                    $wpdb->query('ROLLBACK');
                    Logger::log('warning', 'Insufficient capacity for hold update', [
                        'occurrence_id' => $occurrence_id,
                        'ticket_key' => $ticket_key,
                        'existing_quantity' => $existing_hold->quantity,
                        'additional_quantity' => $quantity,
                        'total_requested' => $new_quantity,
                        'available' => $total_available,
                        'session_id' => $session_id
                    ]);
                    return [
                        'success' => false, 
                        'error' => 'insufficient_capacity_for_update',
                        'available' => $total_available
                    ];
                }
                
                $result = $wpdb->update(
                    $holds_table,
                    [
                        'quantity' => $new_quantity,
                        'expires_at' => $new_expires_at,
                        'updated_at' => current_time('mysql', true)
                    ],
                    ['id' => $existing_hold->id],
                    ['%d', '%s', '%s'],
                    ['%d']
                );
                
                $hold_id = $existing_hold->id;
                $operation = 'updated';
            } else {
                // Create new hold
                $result = $wpdb->insert(
                    $holds_table,
                    [
                        'occurrence_id' => $occurrence_id,
                        'session_id' => $session_id,
                        'user_id' => get_current_user_id() ?: null,
                        'ticket_key' => $ticket_key,
                        'quantity' => $quantity,
                        'expires_at' => $expires_at,
                        'created_at' => current_time('mysql', true),
                        'ip_address' => $this->get_client_ip()
                    ],
                    ['%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s']
                );
                
                $hold_id = $wpdb->insert_id;
                $operation = 'created';
            }
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                Logger::log('error', 'Failed to create/update stock hold', [
                    'occurrence_id' => $occurrence_id,
                    'session_id' => $session_id,
                    'ticket_key' => $ticket_key,
                    'quantity' => $quantity,
                    'operation' => $operation,
                    'db_error' => $wpdb->last_error
                ]);
                return ['success' => false, 'error' => 'database_error'];
            }
            
            $wpdb->query('COMMIT');
            
            // Schedule cleanup job
            $this->schedule_cleanup();
            
            Logger::log('info', "Stock hold {$operation} successfully", [
                'hold_id' => $hold_id,
                'occurrence_id' => $occurrence_id,
                'session_id' => $session_id,
                'ticket_key' => $ticket_key,
                'quantity' => $quantity,
                'expires_at' => $expires_at,
                'operation' => $operation,
                'remaining_capacity' => $this->get_available_capacity($occurrence_id, $ticket_key)
            ]);
            
            // Trigger action for analytics/monitoring
            do_action('wcefp_stock_hold_created', $hold_id, $occurrence_id, $ticket_key, $quantity, $session_id);
            
            return [
                'success' => true,
                'hold_id' => $hold_id,
                'expires_at' => $expires_at,
                'remaining_capacity' => $this->get_available_capacity($occurrence_id, $ticket_key),
                'operation' => $operation
            ];
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            Logger::log('error', 'Stock hold transaction failed: ' . $e->getMessage(), [
                'occurrence_id' => $occurrence_id,
                'session_id' => $session_id,
                'ticket_key' => $ticket_key,
                'quantity' => $quantity,
                'exception' => $e->getTrace()
            ]);
            return ['success' => false, 'error' => 'transaction_failed'];
        } finally {
            // Always release the lock
            $this->release_lock($lock_name);
        }
    }
    
    /**
     * Release a specific hold with enhanced logging
     * 
     * @param int $hold_id Hold ID
     * @param string|null $session_id Session ID for verification
     * @param string $reason Release reason for logging
     * @return bool Success status
     */
    public function release_hold($hold_id, $session_id = null, $reason = 'manual') {
        global $wpdb;
        
        $holds_table = DatabaseManager::get_table_name('stock_holds');
        
        // Get hold details before deletion for logging
        $hold_details = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$holds_table} WHERE id = %d
        ", $hold_id));
        
        $where_clause = ['id' => $hold_id];
        $where_format = ['%d'];
        
        if ($session_id) {
            $where_clause['session_id'] = $session_id;
            $where_format[] = '%s';
        }
        
        $result = $wpdb->delete($holds_table, $where_clause, $where_format);
        
        if ($result !== false && $hold_details) {
            Logger::log('info', 'Stock hold released', [
                'hold_id' => $hold_id, 
                'session_id' => $session_id,
                'occurrence_id' => $hold_details->occurrence_id,
                'ticket_key' => $hold_details->ticket_key,
                'quantity' => $hold_details->quantity,
                'reason' => $reason,
                'was_expired' => strtotime($hold_details->expires_at) < time()
            ]);
            
            // Trigger action for analytics/monitoring
            do_action('wcefp_stock_hold_released', $hold_id, $hold_details, $reason);
        } elseif ($result === false) {
            Logger::log('warning', 'Failed to release stock hold', [
                'hold_id' => $hold_id,
                'session_id' => $session_id,
                'reason' => $reason
            ]);
        }
        
        return $result !== false;
    }
    
    /**
     * Release all holds for a session
     * 
     * @param string $session_id Session ID
     * @return int Number of holds released
     */
    public function release_session_holds($session_id) {
        global $wpdb;
        
        $holds_table = DatabaseManager::get_table_name('stock_holds');
        
        $result = $wpdb->delete(
            $holds_table,
            ['session_id' => $session_id],
            ['%s']
        );
        
        if ($result !== false) {
            Logger::log('info', 'Session holds released', ['session_id' => $session_id, 'count' => $result]);
        }
        
        return $result ?: 0;
    }
    
    /**
     * Convert holds to confirmed bookings
     * 
     * @param string $session_id Session ID
     * @param int $order_id WooCommerce order ID
     * @return bool Success status
     */
    public function convert_holds_to_bookings($session_id, $order_id) {
        global $wpdb;
        
        $holds_table = DatabaseManager::get_table_name('stock_holds');
        $occurrences_table = DatabaseManager::get_table_name('occurrences');
        $booking_items_table = DatabaseManager::get_table_name('booking_items');
        
        // Get all active holds for this session
        $holds = $wpdb->get_results($wpdb->prepare("
            SELECT h.*, o.product_id 
            FROM {$holds_table} h
            INNER JOIN {$occurrences_table} o ON h.occurrence_id = o.id
            WHERE h.session_id = %s AND h.expires_at > NOW()
        ", $session_id));
        
        if (empty($holds)) {
            return true; // No holds to convert
        }
        
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($holds as $hold) {
                // Get order item ID for this product
                $order_item_id = $this->get_order_item_id($order_id, $hold->product_id);
                
                if (!$order_item_id) {
                    continue; // Skip if no matching order item found
                }
                
                // Create booking item
                $wpdb->insert(
                    $booking_items_table,
                    [
                        'occurrence_id' => $hold->occurrence_id,
                        'order_id' => $order_id,
                        'order_item_id' => $order_item_id,
                        'ticket_key' => $hold->ticket_key,
                        'ticket_label' => $this->get_ticket_label($hold->product_id, $hold->ticket_key),
                        'quantity' => $hold->quantity,
                        'unit_price' => $this->get_ticket_price($hold->product_id, $hold->ticket_key),
                        'total_price' => $this->get_ticket_price($hold->product_id, $hold->ticket_key) * $hold->quantity,
                        'status' => 'confirmed'
                    ],
                    ['%d', '%d', '%d', '%s', '%s', '%d', '%f', '%f', '%s']
                );
                
                // Update occurrence booked count
                $wpdb->query($wpdb->prepare("
                    UPDATE {$occurrences_table} 
                    SET booked = booked + %d
                    WHERE id = %d
                ", $hold->quantity, $hold->occurrence_id));
            }
            
            // Delete all holds for this session
            $wpdb->delete($holds_table, ['session_id' => $session_id], ['%s']);
            
            $wpdb->query('COMMIT');
            
            Logger::log('info', 'Holds converted to bookings', [
                'session_id' => $session_id,
                'order_id' => $order_id,
                'holds_count' => count($holds)
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            Logger::log('error', 'Failed to convert holds to bookings: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get available capacity for occurrence and ticket type
     * 
     * @param int $occurrence_id Occurrence ID
     * @param string $ticket_key Ticket key
     * @param int|null $exclude_hold_id Hold ID to exclude from calculation
     * @return int Available capacity
     */
    public function get_available_capacity($occurrence_id, $ticket_key, $exclude_hold_id = null) {
        global $wpdb;
        
        $occurrences_table = DatabaseManager::get_table_name('occurrences');
        $holds_table = DatabaseManager::get_table_name('stock_holds');
        $tickets_table = DatabaseManager::get_table_name('tickets');
        
        // Get occurrence capacity and current bookings
        $occurrence = $wpdb->get_row($wpdb->prepare("
            SELECT capacity, booked 
            FROM {$occurrences_table}
            WHERE id = %d
        ", $occurrence_id));
        
        if (!$occurrence) {
            return 0;
        }
        
        // Get ticket-specific capacity limit (if any)
        $ticket_capacity = $wpdb->get_var($wpdb->prepare("
            SELECT capacity_per_slot
            FROM {$tickets_table} t
            INNER JOIN {$occurrences_table} o ON t.product_id = o.product_id
            WHERE o.id = %d AND t.ticket_key = %s
        ", $occurrence_id, $ticket_key));
        
        // Use the more restrictive capacity
        $max_capacity = $ticket_capacity ? min($occurrence->capacity, $ticket_capacity) : $occurrence->capacity;
        
        // Calculate currently held quantity for this ticket type
        $held_query = "
            SELECT COALESCE(SUM(quantity), 0)
            FROM {$holds_table}
            WHERE occurrence_id = %d 
            AND ticket_key = %s
            AND expires_at > NOW()
        ";
        $held_params = [$occurrence_id, $ticket_key];
        
        if ($exclude_hold_id) {
            $held_query .= " AND id != %d";
            $held_params[] = $exclude_hold_id;
        }
        
        $currently_held = $wpdb->get_var($wpdb->prepare($held_query, $held_params));
        
        return max(0, $max_capacity - $occurrence->booked - $currently_held);
    }
    
    /**
     * Get session holds count
     * 
     * @param string $session_id Session ID
     * @return int Holds count
     */
    private function get_session_holds_count($session_id) {
        global $wpdb;
        
        $holds_table = DatabaseManager::get_table_name('stock_holds');
        
        return (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$holds_table}
            WHERE session_id = %s AND expires_at > NOW()
        ", $session_id));
    }
    
    /**
     * Clean up expired holds
     * 
     * @return int Number of holds cleaned up
     */
    public function cleanup_expired_holds() {
        global $wpdb;
        
        $holds_table = DatabaseManager::get_table_name('stock_holds');
        
        $result = $wpdb->query("
            DELETE FROM {$holds_table}
            WHERE expires_at <= NOW()
        ");
        
        if ($result > 0) {
            Logger::log('info', 'Expired holds cleaned up', ['count' => $result]);
        }
        
        return $result ?: 0;
    }
    
    /**
     * Schedule cleanup job
     * 
     * @return void
     */
    private function schedule_cleanup() {
        if (!wp_next_scheduled('wcefp_cleanup_expired_holds')) {
            wp_schedule_event(time() + 300, 'wcefp_5_minutes', 'wcefp_cleanup_expired_holds');
        }
    }
    
    /**
     * Get or create session ID
     * 
     * @return string Session ID
     */
    private function get_session_id() {
        if (session_id()) {
            return session_id();
        }
        
        // Use WooCommerce session if available
        if (function_exists('WC') && WC()->session) {
            return WC()->session->get_customer_id();
        }
        
        // Fallback to WordPress user session
        $session_token = wp_get_session_token();
        if ($session_token) {
            return $session_token;
        }
        
        // Last resort: create unique identifier
        return 'guest_' . uniqid();
    }
    
    /**
     * Calculate hold expiry time with configurable duration
     * 
     * @return string MySQL datetime format
     */
    private function calculate_expiry_time() {
        $hold_duration = $this->get_hold_duration();
        return gmdate('Y-m-d H:i:s', time() + ($hold_duration * 60));
    }
    
    /**
     * Get configurable hold duration
     * 
     * @return int Hold duration in minutes
     */
    private function get_hold_duration() {
        $options = get_option('wcefp_options', []);
        $duration = (int) ($options['stock_hold_duration'] ?? self::DEFAULT_HOLD_DURATION);
        
        // Ensure reasonable limits (5 minutes to 2 hours)
        return max(5, min(120, $duration));
    }
    
    /**
     * Acquire a database lock for concurrency protection
     * 
     * @param string $lock_name Lock name
     * @return bool Success status
     */
    private function acquire_lock($lock_name) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare("
            SELECT GET_LOCK(%s, %d)
        ", $lock_name, self::LOCK_TIMEOUT));
        
        return $result === '1';
    }
    
    /**
     * Release a database lock
     * 
     * @param string $lock_name Lock name
     * @return bool Success status
     */
    private function release_lock($lock_name) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare("
            SELECT RELEASE_LOCK(%s)
        ", $lock_name));
        
        return $result === '1';
    }
    
    /**
     * Get client IP address for logging
     * 
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (forwarded headers)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Validate hold request parameters
     * 
     * @param int $occurrence_id Occurrence ID
     * @param string $ticket_key Ticket key
     * @param int $quantity Quantity
     * @param string $session_id Session ID
     * @return bool Valid status
     */
    private function validate_hold_request($occurrence_id, $ticket_key, $quantity, $session_id) {
        return $occurrence_id > 0 && 
               !empty($ticket_key) && 
               $quantity > 0 && 
               $quantity <= 50 && // Reasonable max per request
               !empty($session_id);
    }
    
    /**
     * Get order item ID for product
     * 
     * @param int $order_id Order ID
     * @param int $product_id Product ID
     * @return int|null Order item ID
     */
    private function get_order_item_id($order_id, $product_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }
        
        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_product_id() == $product_id) {
                return $item_id;
            }
        }
        
        return null;
    }
    
    /**
     * Get ticket label for display
     * 
     * @param int $product_id Product ID
     * @param string $ticket_key Ticket key
     * @return string Ticket label
     */
    private function get_ticket_label($product_id, $ticket_key) {
        global $wpdb;
        
        $tickets_table = DatabaseManager::get_table_name('tickets');
        
        $label = $wpdb->get_var($wpdb->prepare("
            SELECT label
            FROM {$tickets_table}
            WHERE product_id = %d AND ticket_key = %s
        ", $product_id, $ticket_key));
        
        return $label ?: ucfirst($ticket_key);
    }
    
    /**
     * Get ticket price
     * 
     * @param int $product_id Product ID
     * @param string $ticket_key Ticket key
     * @return float Ticket price
     */
    private function get_ticket_price($product_id, $ticket_key) {
        global $wpdb;
        
        $tickets_table = DatabaseManager::get_table_name('tickets');
        
        $price = $wpdb->get_var($wpdb->prepare("
            SELECT price
            FROM {$tickets_table}
            WHERE product_id = %d AND ticket_key = %s
        ", $product_id, $ticket_key));
        
        return (float) ($price ?: 0);
    }
    
    /**
     * Test concurrency handling by simulating multiple simultaneous holds
     * 
     * @param int $occurrence_id Occurrence ID
     * @param string $ticket_key Ticket key
     * @param int $quantity Quantity per request
     * @param int $concurrent_requests Number of concurrent requests to simulate
     * @return array Test results
     */
    public function test_concurrency($occurrence_id, $ticket_key, $quantity, $concurrent_requests = 5) {
        if (!defined('WCEFP_TESTING_MODE') || !WCEFP_TESTING_MODE) {
            return ['error' => 'Testing mode not enabled'];
        }
        
        $results = [];
        $successful_holds = 0;
        $failed_holds = 0;
        
        // Get initial capacity
        $initial_capacity = $this->get_available_capacity($occurrence_id, $ticket_key);
        
        Logger::log('info', 'Starting concurrency test', [
            'occurrence_id' => $occurrence_id,
            'ticket_key' => $ticket_key,
            'quantity_per_request' => $quantity,
            'concurrent_requests' => $concurrent_requests,
            'initial_capacity' => $initial_capacity
        ]);
        
        // Simulate concurrent requests
        for ($i = 1; $i <= $concurrent_requests; $i++) {
            $session_id = "test_session_{$i}_" . uniqid();
            $result = $this->create_hold($occurrence_id, $ticket_key, $quantity, $session_id);
            
            $results[] = [
                'session_id' => $session_id,
                'request_num' => $i,
                'result' => $result
            ];
            
            if ($result['success']) {
                $successful_holds++;
            } else {
                $failed_holds++;
            }
        }
        
        $final_capacity = $this->get_available_capacity($occurrence_id, $ticket_key);
        
        $test_summary = [
            'initial_capacity' => $initial_capacity,
            'final_capacity' => $final_capacity,
            'capacity_held' => $initial_capacity - $final_capacity,
            'successful_holds' => $successful_holds,
            'failed_holds' => $failed_holds,
            'expected_capacity_held' => $successful_holds * $quantity,
            'concurrency_test_passed' => ($initial_capacity - $final_capacity) === ($successful_holds * $quantity),
            'details' => $results
        ];
        
        Logger::log('info', 'Concurrency test completed', $test_summary);
        
        return $test_summary;
    }
    
    /**
     * Get comprehensive hold statistics for monitoring
     * 
     * @return array Hold statistics
     */
    public function get_hold_statistics() {
        global $wpdb;
        
        $holds_table = DatabaseManager::get_table_name('stock_holds');
        
        $stats = [
            'active_holds' => (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$holds_table} WHERE expires_at > NOW()
            "),
            'expired_holds' => (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$holds_table} WHERE expires_at <= NOW()
            "),
            'holds_by_session' => $wpdb->get_results("
                SELECT session_id, COUNT(*) as hold_count, SUM(quantity) as total_quantity
                FROM {$holds_table} 
                WHERE expires_at > NOW()
                GROUP BY session_id
                ORDER BY hold_count DESC
                LIMIT 10
            "),
            'holds_by_occurrence' => $wpdb->get_results("
                SELECT occurrence_id, ticket_key, COUNT(*) as hold_count, SUM(quantity) as total_quantity
                FROM {$holds_table} 
                WHERE expires_at > NOW()
                GROUP BY occurrence_id, ticket_key
                ORDER BY total_quantity DESC
                LIMIT 10
            "),
            'average_hold_duration' => $this->get_hold_duration(),
            'cleanup_needed' => (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$holds_table} WHERE expires_at <= NOW()
            ")
        ];
        
        return $stats;
    }
    
    /**
     * Create table for stock holds with enhanced schema
     * 
     * @return void
     */
    public static function create_tables() {
        global $wpdb;
        
        $holds_table = DatabaseManager::get_table_name('stock_holds');
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$holds_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            occurrence_id bigint(20) UNSIGNED NOT NULL,
            session_id varchar(128) NOT NULL,
            user_id bigint(20) UNSIGNED NULL,
            ticket_key varchar(50) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY occurrence_ticket (occurrence_id, ticket_key),
            KEY session_id (session_id),
            KEY expires_at (expires_at),
            KEY user_id (user_id),
            UNIQUE KEY unique_active_hold (occurrence_id, ticket_key, session_id, expires_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        Logger::log('info', 'Stock holds table created/updated');
    }
}