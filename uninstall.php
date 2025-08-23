<?php
/**
 * Uninstall script for WCEventsFP Plugin
 * 
 * This file is executed when the plugin is deleted through WordPress admin.
 * It provides options for data cleanup with user consent.
 * 
 * @package WCEFP
 * @since 2.1.4
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load plugin constants if not already loaded
if (!defined('WCEFP_VERSION')) {
    define('WCEFP_VERSION', '2.1.4');
}

/**
 * WCEventsFP Uninstall Handler
 */
class WCEFP_Uninstaller {
    
    /**
     * Run uninstall process
     */
    public static function uninstall() {
        // Check user permissions
        if (!current_user_can('delete_plugins')) {
            wp_die(__('You do not have permission to delete plugins.', 'wceventsfp'));
        }
        
        // Get uninstall options (user preference for data retention)
        $keep_data = get_option('wcefp_keep_data_on_uninstall', false);
        
        if (!$keep_data) {
            self::cleanup_database();
            self::cleanup_files();
            self::cleanup_user_meta();
        }
        
        // Always clean up transients and temporary data
        self::cleanup_transients();
        
        // Log uninstall
        if (function_exists('error_log')) {
            error_log('WCEventsFP Plugin uninstalled at ' . current_time('mysql'));
        }
    }
    
    /**
     * Clean up plugin options and custom tables
     */
    private static function cleanup_database() {
        global $wpdb;
        
        // Remove plugin options
        $options_to_delete = [
            'wcefp_version',
            'wcefp_settings',
            'wcefp_admin_settings',
            'wcefp_analytics_settings',
            'wcefp_google_reviews_settings',
            'wcefp_conversion_settings',
            'wcefp_commission_settings',
            'wcefp_channel_settings',
            'wcefp_resource_settings',
            'wcefp_voucher_settings',
            'wcefp_meetingpoint_settings',
            'wcefp_onboarding_completed',
            'wcefp_activation_time',
            'wcefp_db_version',
            'wcefp_keep_data_on_uninstall'
        ];
        
        foreach ($options_to_delete as $option) {
            delete_option($option);
            delete_site_option($option); // For multisite
        }
        
        // Remove custom tables if they exist
        $custom_tables = [
            $wpdb->prefix . 'wcefp_bookings',
            $wpdb->prefix . 'wcefp_resources',
            $wpdb->prefix . 'wcefp_channels',
            $wpdb->prefix . 'wcefp_commissions',
            $wpdb->prefix . 'wcefp_analytics',
            $wpdb->prefix . 'wcefp_vouchers',
            $wpdb->prefix . 'wcefp_meetingpoints'
        ];
        
        foreach ($custom_tables as $table) {
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $table));
        }
        
        // Remove custom post types and their meta
        $post_types = ['wcefp_booking', 'wcefp_resource', 'wcefp_voucher'];
        
        foreach ($post_types as $post_type) {
            $posts = get_posts([
                'post_type' => $post_type,
                'numberposts' => -1,
                'post_status' => 'any'
            ]);
            
            foreach ($posts as $post) {
                wp_delete_post($post->ID, true);
            }
        }
    }
    
    /**
     * Clean up user metadata related to plugin
     */
    private static function cleanup_user_meta() {
        global $wpdb;
        
        $user_meta_keys = [
            'wcefp_user_preferences',
            'wcefp_dashboard_widgets',
            'wcefp_admin_notices_dismissed',
            'wcefp_onboarding_step'
        ];
        
        foreach ($user_meta_keys as $meta_key) {
            $wpdb->delete(
                $wpdb->usermeta,
                ['meta_key' => $meta_key],
                ['%s']
            );
        }
    }
    
    /**
     * Clean up transients and temporary data
     */
    private static function cleanup_transients() {
        global $wpdb;
        
        // Delete plugin transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wcefp_%' 
             OR option_name LIKE '_transient_timeout_wcefp_%'"
        );
        
        // Delete site transients for multisite
        $wpdb->query(
            "DELETE FROM {$wpdb->sitemeta} 
             WHERE meta_key LIKE '_site_transient_wcefp_%' 
             OR meta_key LIKE '_site_transient_timeout_wcefp_%'"
        );
        
        // Clear object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Clean up uploaded files and directories
     */
    private static function cleanup_files() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/wcefp/';
        
        if (is_dir($plugin_upload_dir)) {
            self::delete_directory($plugin_upload_dir);
        }
        
        // Clean up logs
        $log_dir = WP_CONTENT_DIR . '/wcefp-logs/';
        if (is_dir($log_dir)) {
            self::delete_directory($log_dir);
        }
    }
    
    /**
     * Recursively delete directory and contents
     * 
     * @param string $dir Directory path
     * @return bool Success status
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Create backup of plugin data before uninstall (optional)
     * 
     * @return string|false Backup file path or false on failure
     */
    private static function create_backup() {
        $backup_data = [
            'version' => WCEFP_VERSION,
            'timestamp' => current_time('mysql'),
            'options' => [],
            'user_meta' => [],
            'posts' => []
        ];
        
        // Collect options
        $options = [
            'wcefp_settings',
            'wcefp_admin_settings',
            'wcefp_analytics_settings'
        ];
        
        foreach ($options as $option) {
            $value = get_option($option);
            if ($value !== false) {
                $backup_data['options'][$option] = $value;
            }
        }
        
        // Create backup file
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/wcefp-backups/';
        
        if (!is_dir($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $backup_file = $backup_dir . 'wcefp-backup-' . date('Y-m-d-H-i-s') . '.json';
        
        if (file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT))) {
            return $backup_file;
        }
        
        return false;
    }
}

// Run the uninstall process
WCEFP_Uninstaller::uninstall();