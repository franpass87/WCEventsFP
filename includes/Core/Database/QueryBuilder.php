<?php
/**
 * Query Builder
 * 
 * @package WCEFP
 * @subpackage Core\Database
 * @since 2.1.1
 */

namespace WCEFP\Core\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple Query Builder for basic database operations
 */
class QueryBuilder {
    
    /**
     * WordPress database object
     * 
     * @var \wpdb
     */
    private $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Get occurrences for a product
     * 
     * @param int $product_id Product ID
     * @param array $args Additional query arguments
     * @return array
     */
    public function get_occurrences($product_id, $args = []) {
        $table = $this->wpdb->prefix . 'wcefp_occurrences';
        
        $where = ['product_id = %d'];
        $params = [$product_id];
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        
        if (!empty($args['from_date'])) {
            $where[] = 'start_datetime >= %s';
            $params[] = $args['from_date'];
        }
        
        if (!empty($args['to_date'])) {
            $where[] = 'start_datetime <= %s';
            $params[] = $args['to_date'];
        }
        
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY start_datetime ASC";
        
        if (!empty($args['limit'])) {
            $sql .= ' LIMIT ' . intval($args['limit']);
        }
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );
    }
    
    /**
     * Get bookings for occurrence
     * 
     * @param int $occurrence_id Occurrence ID
     * @return array
     */
    public function get_bookings($occurrence_id = null) {
        $table = $this->wpdb->prefix . 'wcefp_bookings';
        
        if ($occurrence_id) {
            return $this->wpdb->get_results(
                $this->wpdb->prepare("SELECT * FROM {$table} WHERE occurrence_id = %d", $occurrence_id),
                ARRAY_A
            );
        }
        
        return $this->wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A);
    }
    
    /**
     * Get vouchers
     * 
     * @param array $args Query arguments
     * @return array
     */
    public function get_vouchers($args = []) {
        $table = $this->wpdb->prefix . 'wcefp_vouchers';
        
        $where = ['1=1'];
        $params = [];
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        
        if (!empty($args['is_used'])) {
            $where[] = 'is_used = %d';
            $params[] = $args['is_used'] ? 1 : 0;
        }
        
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY created_at DESC";
        
        if (!empty($args['limit'])) {
            $sql .= ' LIMIT ' . intval($args['limit']);
        }
        
        if (empty($params)) {
            return $this->wpdb->get_results($sql, ARRAY_A);
        }
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );
    }
}