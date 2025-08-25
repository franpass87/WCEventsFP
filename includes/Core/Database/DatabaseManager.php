<?php
/**
 * Database Manager
 * 
 * Handles database table creation and schema management
 * 
 * @package WCEFP
 * @subpackage Core\Database
 * @since 2.2.0
 */

namespace WCEFP\Core\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Manager Class
 */
class DatabaseManager {
    
    /**
     * Database version
     */
    const DB_VERSION = '2.2.0';
    
    /**
     * Database version option name
     */
    const DB_VERSION_OPTION = 'wcefp_db_version';
    
    /**
     * Initialize database tables
     * 
     * @return void
     */
    public static function init_tables() {
        $current_version = get_option(self::DB_VERSION_OPTION, '0.0.0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::create_tables();
            self::create_indexes();
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
            
            // Run migration if needed
            if (version_compare($current_version, '1.0.0', '>')) {
                self::run_migration($current_version);
            }
        }
    }
    
    /**
     * Create database tables
     * 
     * @return void
     */
    private static function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Occurrences table - stores individual event/experience time slots
        $sql_occurrences = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_occurrences (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            start_utc datetime NOT NULL,
            end_utc datetime NOT NULL,
            start_local datetime NOT NULL,
            end_local datetime NOT NULL,
            timezone_string varchar(50) NOT NULL DEFAULT 'UTC',
            capacity int(11) unsigned NOT NULL DEFAULT 10,
            booked int(11) unsigned NOT NULL DEFAULT 0,
            held int(11) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            meeting_point_id bigint(20) unsigned NULL,
            meeting_point_override text NULL,
            meta longtext NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY start_utc (start_utc),
            KEY status (status),
            KEY meeting_point_id (meeting_point_id)
        ) $charset_collate;";
        
        // Ticket types table - stores ticket type configurations per product
        $sql_tickets = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_tickets (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            ticket_key varchar(50) NOT NULL,
            label varchar(255) NOT NULL,
            description text NULL,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            min_quantity int(11) unsigned NOT NULL DEFAULT 0,
            max_quantity int(11) unsigned NOT NULL DEFAULT 10,
            capacity_per_slot int(11) unsigned NULL,
            age_min int(11) unsigned NULL,
            age_max int(11) unsigned NULL,
            pricing_rules longtext NULL,
            sort_order int(11) unsigned NOT NULL DEFAULT 0,
            is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_product_ticket (product_id, ticket_key),
            KEY product_id (product_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Booking items table - stores individual ticket bookings
        $sql_booking_items = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_booking_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            occurrence_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            order_item_id bigint(20) unsigned NOT NULL,
            ticket_key varchar(50) NOT NULL,
            ticket_label varchar(255) NOT NULL,
            quantity int(11) unsigned NOT NULL DEFAULT 1,
            unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
            total_price decimal(10,2) NOT NULL DEFAULT 0.00,
            participant_data longtext NULL,
            status varchar(20) NOT NULL DEFAULT 'confirmed',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY occurrence_id (occurrence_id),
            KEY order_id (order_id),
            KEY order_item_id (order_item_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Extra services table - stores configurable extras per product
        $sql_extras = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_extras (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            extra_key varchar(50) NOT NULL,
            label varchar(255) NOT NULL,
            description text NULL,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            pricing_type varchar(20) NOT NULL DEFAULT 'fixed',
            is_required tinyint(1) unsigned NOT NULL DEFAULT 0,
            max_quantity int(11) unsigned NULL,
            stock_quantity int(11) NULL,
            stock_status varchar(20) NOT NULL DEFAULT 'instock',
            sort_order int(11) unsigned NOT NULL DEFAULT 0,
            is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_product_extra (product_id, extra_key),
            KEY product_id (product_id),
            KEY is_active (is_active),
            KEY stock_status (stock_status)
        ) $charset_collate;";
        
        // Stock holds table - manages temporary capacity reservations
        $sql_holds = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_stock_holds (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            occurrence_id bigint(20) unsigned NOT NULL,
            session_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned NULL,
            ticket_key varchar(50) NOT NULL,
            quantity int(11) unsigned NOT NULL DEFAULT 1,
            expires_at datetime NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_session_occurrence_ticket (session_id, occurrence_id, ticket_key),
            KEY occurrence_id (occurrence_id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        dbDelta($sql_occurrences);
        dbDelta($sql_tickets);
        dbDelta($sql_booking_items);
        dbDelta($sql_extras);
        dbDelta($sql_holds);
    }
    
    /**
     * Create database indexes for performance
     * 
     * @return void
     */
    private static function create_indexes() {
        global $wpdb;
        
        // Additional indexes for query optimization
        $indexes = [
            // Occurrences indexes
            "CREATE INDEX IF NOT EXISTS idx_wcefp_occurrences_product_date 
             ON {$wpdb->prefix}wcefp_occurrences (product_id, start_utc, status)",
            
            "CREATE INDEX IF NOT EXISTS idx_wcefp_occurrences_capacity 
             ON {$wpdb->prefix}wcefp_occurrences (capacity, booked, held)",
            
            // Booking items indexes
            "CREATE INDEX IF NOT EXISTS idx_wcefp_booking_items_occurrence_status 
             ON {$wpdb->prefix}wcefp_booking_items (occurrence_id, status)",
            
            // Stock holds indexes for cleanup
            "CREATE INDEX IF NOT EXISTS idx_wcefp_stock_holds_expires 
             ON {$wpdb->prefix}wcefp_stock_holds (expires_at)",
        ];
        
        foreach ($indexes as $index_sql) {
            $wpdb->query($index_sql);
        }
    }
    
    /**
     * Run migration from older versions
     * 
     * @param string $from_version Current version
     * @return void
     */
    private static function run_migration($from_version) {
        // Migration will be implemented in MigrationManager
        if (class_exists('\WCEFP\Core\Database\MigrationManager')) {
            \WCEFP\Core\Database\MigrationManager::migrate_from_version($from_version);
        }
    }
    
    /**
     * Drop all plugin tables (for uninstall)
     * 
     * @return void
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            "{$wpdb->prefix}wcefp_occurrences",
            "{$wpdb->prefix}wcefp_tickets", 
            "{$wpdb->prefix}wcefp_booking_items",
            "{$wpdb->prefix}wcefp_extras",
            "{$wpdb->prefix}wcefp_stock_holds"
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        delete_option(self::DB_VERSION_OPTION);
    }
    
    /**
     * Get table name with WordPress prefix
     * 
     * @param string $table_name Base table name without prefix
     * @return string Full table name
     */
    public static function get_table_name($table_name) {
        global $wpdb;
        return $wpdb->prefix . 'wcefp_' . $table_name;
    }
}