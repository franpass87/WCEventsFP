<?php
/**
 * Database Helper per WCEventsFP
 * Migliora gestione query e sicurezza database
 *
 * @package WCEventsFP
 * @since 1.7.2
 */

if (!defined('ABSPATH')) exit;

class WCEFP_Database {
    
    private $wpdb;
    private $logger;
    
    public function __construct($wpdb = null, $logger = null) {
        global $wpdb as $global_wpdb;
        $this->wpdb = $wpdb ?: $global_wpdb;
        $this->logger = $logger ?: new WCEFP_Logger();
    }
    
    /**
     * Get table name with prefix
     */
    public function table($name) {
        return $this->wpdb->prefix . 'wcefp_' . $name;
    }
    
    /**
     * Safe query execution with logging
     */
    public function query($sql, $params = []) {
        try {
            $prepared = empty($params) ? $sql : $this->wpdb->prepare($sql, $params);
            $result = $this->wpdb->query($prepared);
            
            if ($this->wpdb->last_error) {
                $this->logger->error('Database query failed', [
                    'error' => $this->wpdb->last_error,
                    'sql' => $prepared
                ]);
                return false;
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Database query exception', [
                'message' => $e->getMessage(),
                'sql' => $sql
            ]);
            return false;
        }
    }
    
    /**
     * Get single row
     */
    public function get_row($sql, $params = [], $output = ARRAY_A) {
        try {
            $prepared = empty($params) ? $sql : $this->wpdb->prepare($sql, $params);
            return $this->wpdb->get_row($prepared, $output);
        } catch (Exception $e) {
            $this->logger->error('Database get_row exception', [
                'message' => $e->getMessage(),
                'sql' => $sql
            ]);
            return null;
        }
    }
    
    /**
     * Get multiple rows
     */
    public function get_results($sql, $params = [], $output = ARRAY_A) {
        try {
            $prepared = empty($params) ? $sql : $this->wpdb->prepare($sql, $params);
            return $this->wpdb->get_results($prepared, $output);
        } catch (Exception $e) {
            $this->logger->error('Database get_results exception', [
                'message' => $e->getMessage(),
                'sql' => $sql
            ]);
            return [];
        }
    }
    
    /**
     * Get single variable
     */
    public function get_var($sql, $params = []) {
        try {
            $prepared = empty($params) ? $sql : $this->wpdb->prepare($sql, $params);
            return $this->wpdb->get_var($prepared);
        } catch (Exception $e) {
            $this->logger->error('Database get_var exception', [
                'message' => $e->getMessage(),
                'sql' => $sql
            ]);
            return null;
        }
    }
    
    /**
     * Insert record
     */
    public function insert($table, $data, $format = null) {
        $table_name = $this->table($table);
        $result = $this->wpdb->insert($table_name, $data, $format);
        
        if ($result === false) {
            $this->logger->error('Database insert failed', [
                'table' => $table_name,
                'error' => $this->wpdb->last_error
            ]);
        }
        
        return $result;
    }
    
    /**
     * Update record
     */
    public function update($table, $data, $where, $format = null, $where_format = null) {
        $table_name = $this->table($table);
        $result = $this->wpdb->update($table_name, $data, $where, $format, $where_format);
        
        if ($result === false) {
            $this->logger->error('Database update failed', [
                'table' => $table_name,
                'error' => $this->wpdb->last_error
            ]);
        }
        
        return $result;
    }
    
    /**
     * Delete record
     */
    public function delete($table, $where, $where_format = null) {
        $table_name = $this->table($table);
        $result = $this->wpdb->delete($table_name, $where, $where_format);
        
        if ($result === false) {
            $this->logger->error('Database delete failed', [
                'table' => $table_name,
                'error' => $this->wpdb->last_error
            ]);
        }
        
        return $result;
    }
}