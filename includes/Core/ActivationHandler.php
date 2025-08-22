<?php
/**
 * Plugin Activation Handler
 * 
 * @package WCEFP
 * @subpackage Core
 * @since 2.0.1
 */

namespace WCEFP\Core;

use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles plugin activation, deactivation, and database setup
 */
class ActivationHandler {
    
    /**
     * Run activation procedures
     * 
     * @return void
     */
    public static function activate() {
        try {
            // Check system requirements
            self::check_system_requirements();
            
            // Create database tables
            self::create_database_tables();
            
            // Set default options
            self::set_default_options();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            Logger::info('WCEventsFP plugin activated successfully');
            
        } catch (\Exception $e) {
            Logger::error('Plugin activation failed: ' . $e->getMessage());
            
            // Deactivate plugin on critical errors
            if (function_exists('wp_die')) {
                wp_die(
                    sprintf(__('WCEventsFP activation failed: %s', 'wceventsfp'), $e->getMessage()),
                    __('Plugin Activation Error', 'wceventsfp')
                );
            } else {
                die('WCEventsFP activation failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Run deactivation procedures
     * 
     * @return void
     */
    public static function deactivate() {
        try {
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Clean up scheduled events
            wp_clear_scheduled_hook('wcefp_cleanup_expired_sessions');
            wp_clear_scheduled_hook('wcefp_process_notifications');
            
            Logger::info('WCEventsFP plugin deactivated successfully');
            
        } catch (\Exception $e) {
            Logger::error('Plugin deactivation error: ' . $e->getMessage());
        }
    }
    
    /**
     * Check system requirements
     * 
     * @return void
     * @throws \Exception If requirements not met
     */
    private static function check_system_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new \Exception(
                sprintf('PHP 7.4 or higher required. Current version: %s', PHP_VERSION)
            );
        }
        
        // Check required PHP extensions
        $required_extensions = ['mysqli', 'json', 'mbstring'];
        $missing_extensions = [];
        
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $missing_extensions[] = $extension;
            }
        }
        
        if (!empty($missing_extensions)) {
            throw new \Exception(
                sprintf('Required PHP extensions missing: %s', implode(', ', $missing_extensions))
            );
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            throw new \Exception(
                sprintf('WordPress 5.0 or higher required. Current version: %s', get_bloginfo('version'))
            );
        }
        
        // Check WooCommerce
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce plugin is required and must be activated');
        }
        
        // Check database connection
        global $wpdb;
        if (!$wpdb || $wpdb->last_error) {
            throw new \Exception('Database connection error: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Create database tables
     * 
     * @return void
     */
    private static function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Define table creation SQL
        $tables = [
            'occurrences' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_occurrences (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id BIGINT UNSIGNED NOT NULL,
                start_datetime DATETIME NOT NULL,
                end_datetime DATETIME NULL,
                capacity INT NOT NULL DEFAULT 10,
                booked_seats INT NOT NULL DEFAULT 0,
                price_adult DECIMAL(10,2) NULL,
                price_child DECIMAL(10,2) NULL,
                status ENUM('active','inactive','cancelled','completed') DEFAULT 'active',
                guide_id BIGINT UNSIGNED NULL,
                notes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_product_id (product_id),
                INDEX idx_start_datetime (start_datetime),
                INDEX idx_status (status)
            ) {$charset_collate};",
            
            'bookings' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_bookings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id BIGINT UNSIGNED NOT NULL,
                occurrence_id BIGINT UNSIGNED NOT NULL,
                customer_name VARCHAR(255) NOT NULL,
                customer_email VARCHAR(255) NOT NULL,
                adults INT NOT NULL DEFAULT 0,
                children INT NOT NULL DEFAULT 0,
                total_price DECIMAL(10,2) NOT NULL,
                status ENUM('pending','confirmed','cancelled','completed','refunded') DEFAULT 'pending',
                booking_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                special_requests TEXT NULL,
                voucher_code VARCHAR(50) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_order_id (order_id),
                INDEX idx_occurrence_id (occurrence_id),
                INDEX idx_customer_email (customer_email),
                INDEX idx_status (status),
                INDEX idx_booking_date (booking_date)
            ) {$charset_collate};",
            
            'vouchers' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_vouchers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) UNIQUE NOT NULL,
                product_id BIGINT UNSIGNED NULL,
                value DECIMAL(10,2) NOT NULL,
                type ENUM('fixed','percentage') DEFAULT 'fixed',
                status ENUM('unused','used','expired') DEFAULT 'unused',
                valid_from DATETIME NULL,
                valid_until DATETIME NULL,
                usage_limit INT DEFAULT 1,
                used_count INT DEFAULT 0,
                created_by BIGINT UNSIGNED NULL,
                redeemed_by BIGINT UNSIGNED NULL,
                redeemed_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_code (code),
                INDEX idx_product_id (product_id),
                INDEX idx_status (status),
                INDEX idx_valid_until (valid_until)
            ) {$charset_collate};",
            
            'product_extras' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_product_extras (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id BIGINT UNSIGNED NOT NULL,
                extra_id BIGINT UNSIGNED NOT NULL,
                pricing_type ENUM('per_person','per_booking','per_unit') DEFAULT 'per_person',
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                required BOOLEAN DEFAULT FALSE,
                max_qty INT NULL,
                stock INT NULL,
                sort_order INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_product_extra (product_id, extra_id),
                INDEX idx_product_id (product_id),
                INDEX idx_extra_id (extra_id),
                INDEX idx_sort_order (sort_order)
            ) {$charset_collate};",
            
            'closures' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_closures (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id BIGINT UNSIGNED NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                note TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_product_id (product_id),
                INDEX idx_date_range (start_date, end_date)
            ) {$charset_collate};",
            
            'analytics' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_analytics (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_name VARCHAR(100) NOT NULL,
                event_data LONGTEXT NULL,
                session_id VARCHAR(100) NULL,
                user_id VARCHAR(100) NULL,
                product_id BIGINT UNSIGNED NULL,
                page_url TEXT NULL,
                user_agent TEXT NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_name (event_name),
                INDEX idx_session_id (session_id),
                INDEX idx_user_id (user_id),
                INDEX idx_product_id (product_id),
                INDEX idx_created_at (created_at)
            ) {$charset_collate};"
        ];
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table_name => $sql) {
            $result = dbDelta($sql);
            if (!empty($result)) {
                Logger::info("Database table '{$table_name}' processed successfully");
            }
        }
        
        // Log any database errors
        if (!empty($wpdb->last_error)) {
            Logger::error('Database table creation error: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Set default plugin options
     * 
     * @return void
     */
    private static function set_default_options() {
        $default_options = [
            'wcefp_default_capacity' => 10,
            'wcefp_disable_woo_emails' => false,
            'wcefp_ga4_enabled' => false,
            'wcefp_version' => '2.0.1',
            'wcefp_activated_at' => current_time('mysql')
        ];
        
        foreach ($default_options as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }
    }
}