<?php
/**
 * Migration Manager
 * 
 * Handles data migration from legacy meta fields to new database structure
 * 
 * @package WCEFP
 * @subpackage Core\Database
 * @since 2.2.0
 */

namespace WCEFP\Core\Database;

use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration Manager Class
 */
class MigrationManager {
    
    /**
     * Migrate data from older version
     * 
     * @param string $from_version Version to migrate from
     * @return bool Success status
     */
    public static function migrate_from_version($from_version) {
        Logger::log('info', "Starting migration from version {$from_version} to " . DatabaseManager::DB_VERSION);
        
        try {
            // Create backup of current meta data
            self::create_meta_backup();
            
            // Migrate product settings
            self::migrate_product_settings();
            
            // Migrate existing bookings
            self::migrate_existing_bookings();
            
            // Clean up old transients
            self::cleanup_old_data();
            
            Logger::log('info', 'Migration completed successfully');
            return true;
            
        } catch (\Exception $e) {
            Logger::log('error', 'Migration failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create backup of existing meta data
     * 
     * @return void
     */
    private static function create_meta_backup() {
        global $wpdb;
        
        // Create backup table for meta data
        $backup_table = DatabaseManager::get_table_name('migration_backup_' . date('Ymd_His'));
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$backup_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            backup_date timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Copy existing WCEFP meta data to backup
        $wpdb->query("
            INSERT INTO {$backup_table} (product_id, meta_key, meta_value)
            SELECT post_id, meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '_wcefp_%'
        ");
        
        Logger::log('info', 'Meta data backup created in table: ' . $backup_table);
    }
    
    /**
     * Migrate product settings to new structure
     * 
     * @return void
     */
    private static function migrate_product_settings() {
        global $wpdb;
        
        // Get all WCEFP products
        $products = $wpdb->get_results("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND pm.meta_key = '_product_type'
            AND pm.meta_value IN ('evento', 'esperienza', 'wcefp_event', 'wcefp_experience')
        ");
        
        foreach ($products as $product) {
            self::migrate_single_product($product->ID);
        }
        
        Logger::log('info', 'Migrated ' . count($products) . ' products');
    }
    
    /**
     * Migrate single product data
     * 
     * @param int $product_id Product ID
     * @return void
     */
    private static function migrate_single_product($product_id) {
        global $wpdb;
        
        // Migrate ticket types
        self::migrate_product_tickets($product_id);
        
        // Migrate extras
        self::migrate_product_extras($product_id);
        
        // Migrate occurrences (if any exist)
        self::migrate_product_occurrences($product_id);
    }
    
    /**
     * Migrate product ticket types
     * 
     * @param int $product_id Product ID
     * @return void
     */
    private static function migrate_product_tickets($product_id) {
        global $wpdb;
        
        $tickets_table = DatabaseManager::get_table_name('tickets');
        
        // Get existing ticket types from meta
        $ticket_types = get_post_meta($product_id, '_wcefp_ticket_types', true);
        
        if (empty($ticket_types) || !is_array($ticket_types)) {
            // Create default ticket types for legacy products
            $default_tickets = [
                [
                    'ticket_key' => 'adult',
                    'label' => __('Adulto', 'wceventsfp'),
                    'price' => (float) get_post_meta($product_id, '_regular_price', true) ?: 0.00,
                    'min_quantity' => 0,
                    'max_quantity' => 10,
                    'is_active' => 1,
                    'sort_order' => 0
                ]
            ];
            
            $child_price = get_post_meta($product_id, '_wcefp_child_price', true);
            if ($child_price && is_numeric($child_price)) {
                $default_tickets[] = [
                    'ticket_key' => 'child',
                    'label' => __('Bambino', 'wceventsfp'),
                    'price' => (float) $child_price,
                    'min_quantity' => 0,
                    'max_quantity' => 10,
                    'age_max' => 12,
                    'is_active' => 1,
                    'sort_order' => 1
                ];
            }
            
            $ticket_types = $default_tickets;
        }
        
        // Insert ticket types into new table
        foreach ($ticket_types as $index => $ticket) {
            $wpdb->insert(
                $tickets_table,
                [
                    'product_id' => $product_id,
                    'ticket_key' => $ticket['ticket_key'] ?? 'ticket_' . $index,
                    'label' => $ticket['label'] ?? __('Biglietto', 'wceventsfp'),
                    'description' => $ticket['description'] ?? null,
                    'price' => (float) ($ticket['price'] ?? 0.00),
                    'min_quantity' => (int) ($ticket['min_quantity'] ?? 0),
                    'max_quantity' => (int) ($ticket['max_quantity'] ?? 10),
                    'capacity_per_slot' => isset($ticket['capacity_per_slot']) ? (int) $ticket['capacity_per_slot'] : null,
                    'age_min' => isset($ticket['age_min']) ? (int) $ticket['age_min'] : null,
                    'age_max' => isset($ticket['age_max']) ? (int) $ticket['age_max'] : null,
                    'pricing_rules' => isset($ticket['pricing_rules']) ? maybe_serialize($ticket['pricing_rules']) : null,
                    'sort_order' => (int) ($ticket['sort_order'] ?? $index),
                    'is_active' => isset($ticket['is_active']) ? (int) $ticket['is_active'] : 1,
                ],
                [
                    '%d', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%d'
                ]
            );
        }
    }
    
    /**
     * Migrate product extras
     * 
     * @param int $product_id Product ID
     * @return void
     */
    private static function migrate_product_extras($product_id) {
        global $wpdb;
        
        $extras_table = DatabaseManager::get_table_name('extras');
        
        // Get existing extras from meta
        $extras = get_post_meta($product_id, '_wcefp_extras', true);
        
        if (!empty($extras) && is_array($extras)) {
            foreach ($extras as $index => $extra) {
                $wpdb->insert(
                    $extras_table,
                    [
                        'product_id' => $product_id,
                        'extra_key' => $extra['extra_key'] ?? 'extra_' . $index,
                        'label' => $extra['label'] ?? __('Extra', 'wceventsfp'),
                        'description' => $extra['description'] ?? null,
                        'price' => (float) ($extra['price'] ?? 0.00),
                        'pricing_type' => $extra['pricing_type'] ?? 'fixed',
                        'is_required' => isset($extra['is_required']) ? (int) $extra['is_required'] : 0,
                        'max_quantity' => isset($extra['max_quantity']) ? (int) $extra['max_quantity'] : null,
                        'stock_quantity' => isset($extra['stock_quantity']) ? (int) $extra['stock_quantity'] : null,
                        'stock_status' => $extra['stock_status'] ?? 'instock',
                        'sort_order' => (int) ($extra['sort_order'] ?? $index),
                        'is_active' => isset($extra['is_active']) ? (int) $extra['is_active'] : 1,
                    ],
                    [
                        '%d', '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%d', '%s', '%d', '%d'
                    ]
                );
            }
        }
    }
    
    /**
     * Migrate product occurrences
     * 
     * @param int $product_id Product ID
     * @return void
     */
    private static function migrate_product_occurrences($product_id) {
        // This would handle existing occurrence data if any exists
        // For now, we'll let the scheduling service generate new occurrences
        Logger::log('info', "Occurrence migration for product {$product_id} - will be generated by scheduling service");
    }
    
    /**
     * Migrate existing bookings to new structure
     * 
     * @return void
     */
    private static function migrate_existing_bookings() {
        global $wpdb;
        
        // Find existing orders with WCEFP products
        $orders = $wpdb->get_results("
            SELECT DISTINCT o.ID as order_id
            FROM {$wpdb->posts} o
            INNER JOIN {$wpdb->postmeta} om ON o.ID = om.post_id
            WHERE o.post_type = 'shop_order'
            AND (
                om.meta_key LIKE '_wcefp_%'
                OR EXISTS (
                    SELECT 1 FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_id = o.ID
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oim.order_item_id = oi.order_item_id
                    WHERE pm.meta_key = '_product_type'
                    AND pm.meta_value IN ('evento', 'esperienza', 'wcefp_event', 'wcefp_experience')
                    AND oim.meta_key = '_product_id'
                    AND oim.meta_value = p.ID
                )
            )
        ");
        
        foreach ($orders as $order) {
            self::migrate_single_order($order->order_id);
        }
        
        Logger::log('info', 'Migrated ' . count($orders) . ' orders');
    }
    
    /**
     * Migrate single order
     * 
     * @param int $order_id Order ID
     * @return void
     */
    private static function migrate_single_order($order_id) {
        // This would handle migration of existing booking data
        // For now, we'll preserve the order meta and let the new system handle future bookings
        Logger::log('info', "Preserving existing booking data for order {$order_id}");
    }
    
    /**
     * Clean up old data and transients
     * 
     * @return void
     */
    private static function cleanup_old_data() {
        global $wpdb;
        
        // Clean up expired transients
        $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_wcefp_%'
            OR option_name LIKE '_transient_timeout_wcefp_%'
        ");
        
        // Clean up old session data
        $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_wp_session_wcefp_%'
            AND option_value < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY))
        ");
        
        Logger::log('info', 'Cleaned up old transient and session data');
    }
    
    /**
     * Rollback migration (if something goes wrong)
     * 
     * @param string $backup_table Backup table name
     * @return bool Success status
     */
    public static function rollback_migration($backup_table) {
        global $wpdb;
        
        try {
            // Restore meta data from backup
            $wpdb->query("
                INSERT IGNORE INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                SELECT product_id, meta_key, meta_value
                FROM {$backup_table}
            ");
            
            // Drop new tables
            DatabaseManager::drop_tables();
            
            Logger::log('info', 'Migration rollback completed');
            return true;
            
        } catch (\Exception $e) {
            Logger::log('error', 'Migration rollback failed: ' . $e->getMessage());
            return false;
        }
    }
}