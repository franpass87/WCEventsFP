<?php
/**
 * WCEventsFP Cache Manager
 * 
 * Handles cache busting and development mode detection
 * Provides methods to clear all caches when plugin is modified
 * 
 * @package WCEFP
 * @since 2.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCEFP_Cache_Manager {
    
    /**
     * Singleton instance
     * 
     * @var self|null
     */
    private static $instance = null;
    
    /**
     * Plugin file path for modification time checking
     * 
     * @var string
     */
    private $plugin_file;
    
    /**
     * Development mode flag
     * 
     * @var bool
     */
    private $is_development_mode;
    
    /**
     * Get singleton instance
     * 
     * @return self
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->plugin_file = WCEFP_PLUGIN_FILE ?? __FILE__;
        $this->is_development_mode = $this->detect_development_mode();
        
        // Add hooks for cache management
        add_action('init', [$this, 'maybe_clear_caches_on_update'], 5);
        add_action('wp_ajax_wcefp_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('admin_bar_menu', [$this, 'add_cache_clear_to_admin_bar'], 999);
    }
    
    /**
     * Detect if we're in development mode
     * 
     * @return bool
     */
    private function detect_development_mode() {
        // Check various development indicators
        return (
            (defined('WP_DEBUG') && WP_DEBUG) ||
            (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ||
            (defined('WCEFP_DEV_MODE') && WCEFP_DEV_MODE) ||
            strpos(get_site_url(), '.local') !== false ||
            strpos(get_site_url(), 'localhost') !== false ||
            strpos(get_site_url(), '.dev') !== false ||
            strpos(get_site_url(), '.test') !== false
        );
    }
    
    /**
     * Generate dynamic version for cache busting
     * 
     * @param string $base_version Base version number
     * @return string Enhanced version for cache busting
     */
    public function get_cache_busting_version($base_version = '2.1.0') {
        if (!$this->is_development_mode) {
            return $base_version;
        }
        
        // In development, append file modification time
        $plugin_mtime = filemtime($this->plugin_file);
        $includes_mtime = $this->get_includes_modification_time();
        
        // Use the most recent modification time
        $latest_mtime = max($plugin_mtime, $includes_mtime);
        
        return $base_version . '.' . $latest_mtime;
    }
    
    /**
     * Get the most recent modification time from includes directory
     * 
     * @return int Latest modification time
     */
    private function get_includes_modification_time() {
        $includes_dir = dirname($this->plugin_file) . '/includes';
        if (!is_dir($includes_dir)) {
            return filemtime($this->plugin_file);
        }
        
        $latest_mtime = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($includes_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $mtime = $file->getMTime();
                if ($mtime > $latest_mtime) {
                    $latest_mtime = $mtime;
                }
            }
        }
        
        return $latest_mtime ?: filemtime($this->plugin_file);
    }
    
    /**
     * Clear all plugin caches
     * 
     * @return bool Success status
     */
    public function clear_all_caches() {
        $cleared = true;
        
        // Clear WordPress transients
        $this->clear_plugin_transients();
        
        // Clear object cache if available
        if (wp_using_ext_object_cache()) {
            wp_cache_flush_group('wcefp');
        }
        
        // Clear file-based caches
        $this->clear_file_caches();
        
        // Clear performance optimization caches
        if (class_exists('WCEFP_Performance_Optimization')) {
            $perf_optimizer = WCEFP_Performance_Optimization::get_instance();
            if (method_exists($perf_optimizer, 'clear_all_caches')) {
                $perf_optimizer->clear_all_caches();
            }
        }
        
        // Update version check timestamp
        update_option('wcefp_last_cache_clear', time());
        
        do_action('wcefp_caches_cleared');
        
        return $cleared;
    }
    
    /**
     * Clear all plugin transients
     */
    private function clear_plugin_transients() {
        global $wpdb;
        
        // Delete transients that start with wcefp_
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            OR option_name LIKE %s
        ", '_transient_wcefp_%', '_transient_timeout_wcefp_%'));
    }
    
    /**
     * Clear file-based caches
     */
    private function clear_file_caches() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/wcefp-cache/';
        
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Maybe clear caches when plugin is updated
     */
    public function maybe_clear_caches_on_update() {
        $current_version = $this->get_cache_busting_version();
        $stored_version = get_option('wcefp_cached_version', '');
        
        if ($current_version !== $stored_version) {
            $this->clear_all_caches();
            update_option('wcefp_cached_version', $current_version);
            
            // Log the cache clear
            if (function_exists('wcefp_debug_log')) {
                wcefp_debug_log("Cache cleared due to version change: {$stored_version} -> {$current_version}");
            }
        }
    }
    
    /**
     * AJAX handler for manual cache clearing
     */
    public function ajax_clear_cache() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcefp_clear_cache') || 
            !current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'wceventsfp'));
        }
        
        $success = $this->clear_all_caches();
        
        wp_send_json([
            'success' => $success,
            'message' => $success ? 
                __('All caches cleared successfully!', 'wceventsfp') : 
                __('Failed to clear some caches.', 'wceventsfp')
        ]);
    }
    
    /**
     * Add cache clear button to admin bar
     * 
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function add_cache_clear_to_admin_bar($wp_admin_bar) {
        // Only show in development mode or to administrators
        if (!$this->is_development_mode && !current_user_can('manage_options')) {
            return;
        }
        
        $wp_admin_bar->add_node([
            'id' => 'wcefp-clear-cache',
            'title' => '<span class="ab-icon dashicons-update" style="font-family: dashicons;"></span> Clear WCEFP Cache',
            'href' => wp_nonce_url(
                admin_url('admin-ajax.php?action=wcefp_clear_cache'), 
                'wcefp_clear_cache', 
                'nonce'
            ),
            'meta' => [
                'title' => __('Clear WCEventsFP caches', 'wceventsfp'),
                'onclick' => 'return confirm("' . esc_js(__('Clear all WCEventsFP caches?', 'wceventsfp')) . '");'
            ]
        ]);
    }
    
    /**
     * Check if we're in development mode
     * 
     * @return bool
     */
    public function is_development_mode() {
        return $this->is_development_mode;
    }
}

// Initialize cache manager
WCEFP_Cache_Manager::get_instance();