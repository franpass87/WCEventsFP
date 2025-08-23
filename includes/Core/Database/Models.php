<?php
/**
 * Database model stubs for missing classes
 * 
 * @package WCEFP
 * @subpackage Core\Database\Models
 * @since 2.1.1
 */

namespace WCEFP\Core\Database\Models;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Model class
 */
abstract class BaseModel {
    
    protected $table_name;
    protected $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Get all records
     * 
     * @param array $args Query arguments
     * @return array
     */
    public function get_all($args = []) {
        $sql = "SELECT * FROM {$this->table_name}";
        
        if (!empty($args['limit'])) {
            $sql .= ' LIMIT ' . intval($args['limit']);
        }
        
        return $this->wpdb->get_results($sql, ARRAY_A);
    }
    
    /**
     * Get record by ID
     * 
     * @param int $id Record ID
     * @return array|null
     */
    public function get_by_id($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
            ARRAY_A
        );
    }
}

/**
 * Occurrence Model stub
 */
class OccurrenceModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'wcefp_occurrences';
    }
    
    /**
     * Get occurrences for a product
     * 
     * @param int $product_id Product ID
     * @param array $args Additional arguments
     * @return array
     */
    public function get_by_product($product_id, $args = []) {
        $where = ['product_id = %d'];
        $params = [$product_id];
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        
        $sql = "SELECT * FROM {$this->table_name} WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY start_datetime ASC";
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );
    }
}

/**
 * Booking Model stub
 */
class BookingModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'wcefp_bookings';
    }
    
    /**
     * Get bookings for occurrence
     * 
     * @param int $occurrence_id Occurrence ID
     * @return array
     */
    public function get_by_occurrence($occurrence_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE occurrence_id = %d", $occurrence_id),
            ARRAY_A
        );
    }
    
    /**
     * Get bookings by status
     * 
     * @param string $status Booking status
     * @return array
     */
    public function get_by_status($status) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE status = %s ORDER BY created_at DESC", $status),
            ARRAY_A
        );
    }
}

/**
 * Voucher Model stub
 */
class VoucherModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'wcefp_vouchers';
    }
    
    /**
     * Get active vouchers
     * 
     * @return array
     */
    public function get_active() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE is_used = 0 AND status = 'active' ORDER BY created_at DESC",
            ARRAY_A
        );
    }
    
    /**
     * Get voucher by code
     * 
     * @param string $code Voucher code
     * @return array|null
     */
    public function get_by_code($code) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE code = %s", $code),
            ARRAY_A
        );
    }
}

/**
 * Analytics Model stub
 */
class AnalyticsModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'wcefp_analytics';
    }
}