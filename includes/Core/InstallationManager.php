<?php
/**
 * Installation Manager - Handles Progressive Plugin Activation
 * 
 * @package WCEFP
 * @subpackage Core
 * @since 2.1.0
 */

namespace WCEFP\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages progressive plugin installation and feature loading
 */
class InstallationManager {
    
    /**
     * Installation modes
     */
    const MODE_MINIMAL = 'minimal';
    const MODE_STANDARD = 'standard';
    const MODE_PROGRESSIVE = 'progressive';
    const MODE_FULL = 'full';
    
    /**
     * Current installation mode
     * @var string
     */
    private $installation_mode;
    
    /**
     * Features enabled for this installation
     * @var array
     */
    private $enabled_features = [];
    
    /**
     * Performance settings
     * @var array
     */
    private $performance_settings = [];
    
    /**
     * Installation status
     * @var string
     */
    private $installation_status = 'not_started';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_installation_config();
        $this->determine_installation_mode();
    }
    
    /**
     * Get current installation mode
     * @return string
     */
    public function get_installation_mode() {
        return $this->installation_mode;
    }
    
    /**
     * Check if feature is enabled
     * @param string $feature_key
     * @return bool
     */
    public function is_feature_enabled($feature_key) {
        return in_array($feature_key, $this->enabled_features);
    }
    
    /**
     * Get enabled features
     * @return array
     */
    public function get_enabled_features() {
        return $this->enabled_features;
    }
    
    /**
     * Get installation status
     * @return string
     */
    public function get_installation_status() {
        return $this->installation_status;
    }
    
    /**
     * Check if plugin is fully installed
     * @return bool
     */
    public function is_fully_installed() {
        return $this->installation_status === 'completed';
    }
    
    /**
     * Check if wizard setup is needed
     * @return bool
     */
    public function needs_setup_wizard() {
        return $this->installation_status === 'not_started' || 
               $this->installation_status === 'wizard_required';
    }
    
    /**
     * Get setup wizard URL
     * @return string
     */
    public function get_setup_wizard_url() {
        return admin_url('admin.php?wcefp_setup=1');
    }
    
    /**
     * Start progressive installation
     * @return bool
     */
    public function start_progressive_installation() {
        try {
            $this->safe_log('Starting progressive installation');
            
            // Update status
            $this->installation_status = 'in_progress';
            update_option('wcefp_installation_status', $this->installation_status);
            
            // Install core features first
            $this->install_core_features();
            
            // Schedule next phase if needed
            if ($this->installation_mode === self::MODE_PROGRESSIVE) {
                $this->schedule_next_installation_phase();
            }
            
            $this->safe_log('Progressive installation phase completed');
            return true;
            
        } catch (\Exception $e) {
            $this->safe_log('Progressive installation failed: ' . $e->getMessage(), 'error');
            $this->installation_status = 'failed';
            update_option('wcefp_installation_status', $this->installation_status);
            return false;
        }
    }
    
    /**
     * Install core features only
     * @return void
     */
    private function install_core_features() {
        $this->safe_log('Installing core features', 'debug');
        
        // Essential database tables
        $this->create_essential_tables();
        
        // Basic options
        $this->set_essential_options();
        
        // Mark core as installed
        update_option('wcefp_core_installed', true);
        
        $this->safe_log('Core features installed successfully', 'debug');
    }
    
    /**
     * Create essential database tables
     * @return void
     */
    private function create_essential_tables() {
        global $wpdb;
        
        // Only create most essential tables first
        $tables = [
            'wcefp_bookings' => $this->get_bookings_table_sql(),
            'wcefp_settings' => $this->get_settings_table_sql()
        ];
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table_name => $sql) {
            try {
                dbDelta($sql);
                $this->safe_log("Created table: {$table_name}", 'debug');
            } catch (\Exception $e) {
                $this->safe_log("Failed to create table {$table_name}: " . $e->getMessage(), 'error');
                throw $e;
            }
        }
    }
    
    /**
     * Get bookings table SQL
     * @return string
     */
    private function get_bookings_table_sql() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_bookings';
        
        return "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            booking_date datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY booking_date (booking_date),
            KEY status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    }
    
    /**
     * Get settings table SQL
     * @return string
     */
    private function get_settings_table_sql() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_settings';
        
        return "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL,
            setting_value longtext,
            autoload varchar(20) NOT NULL DEFAULT 'yes',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key),
            KEY autoload (autoload)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    }
    
    /**
     * Set essential options
     * @return void
     */
    private function set_essential_options() {
        $essential_options = [
            'wcefp_version' => '2.1.0',
            'wcefp_installation_mode' => $this->installation_mode,
            'wcefp_enabled_features' => $this->enabled_features,
            'wcefp_performance_settings' => $this->performance_settings,
            'wcefp_core_installed_at' => current_time('mysql'),
        ];
        
        foreach ($essential_options as $option_name => $option_value) {
            update_option($option_name, $option_value);
        }
    }
    
    /**
     * Schedule next installation phase
     * @return void
     */
    private function schedule_next_installation_phase() {
        // Schedule installation of additional features
        if (!wp_next_scheduled('wcefp_continue_installation')) {
            wp_schedule_single_event(time() + 30, 'wcefp_continue_installation');
            $this->safe_log('Scheduled next installation phase in 30 seconds', 'debug');
        }
    }
    
    /**
     * Continue progressive installation
     * @return void
     */
    public function continue_progressive_installation() {
        try {
            Logger::info('Continuing progressive installation');
            
            // Install next batch of features
            $this->install_next_feature_batch();
            
            // Check if more phases needed
            if ($this->has_more_features_to_install()) {
                $this->schedule_next_installation_phase();
            } else {
                $this->complete_installation();
            }
            
        } catch (\Exception $e) {
            Logger::error('Progressive installation continuation failed: ' . $e->getMessage());
            $this->installation_status = 'failed';
            update_option('wcefp_installation_status', $this->installation_status);
        }
    }
    
    /**
     * Install next batch of features
     * @return void
     */
    private function install_next_feature_batch() {
        $installed_features = get_option('wcefp_installed_features', []);
        $batch_size = $this->get_installation_batch_size();
        $current_batch = 0;
        
        foreach ($this->enabled_features as $feature) {
            if (in_array($feature, $installed_features)) {
                continue; // Already installed
            }
            
            if ($current_batch >= $batch_size) {
                break; // Limit batch size
            }
            
            try {
                $this->install_feature($feature);
                $installed_features[] = $feature;
                $current_batch++;
                
                Logger::debug("Installed feature: {$feature}");
                
            } catch (\Exception $e) {
                Logger::error("Failed to install feature {$feature}: " . $e->getMessage());
                // Continue with other features
            }
        }
        
        update_option('wcefp_installed_features', $installed_features);
    }
    
    /**
     * Install individual feature
     * @param string $feature
     * @return void
     */
    private function install_feature($feature) {
        switch ($feature) {
            case 'admin_enhanced':
                $this->install_admin_features();
                break;
            case 'resources':
                $this->install_resource_management();
                break;
            case 'channels':
                $this->install_distribution_channels();
                break;
            // Add other features as needed
            default:
                Logger::debug("Feature {$feature} has no specific installation requirements");
        }
    }
    
    /**
     * Install admin features
     * @return void
     */
    private function install_admin_features() {
        // Create admin-specific tables if needed
        // Set admin-specific options
        Logger::debug('Admin features installed');
    }
    
    /**
     * Install resource management
     * @return void
     */
    private function install_resource_management() {
        global $wpdb;
        
        // Create resources table
        $table_name = $wpdb->prefix . 'wcefp_resources';
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            capacity int(11) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Install distribution channels
     * @return void
     */
    private function install_distribution_channels() {
        // Create channels configuration
        $channels_config = [
            'booking_com' => ['enabled' => false],
            'expedia' => ['enabled' => false],
            'getyourguide' => ['enabled' => false]
        ];
        
        update_option('wcefp_distribution_channels', $channels_config);
    }
    
    /**
     * Get installation batch size based on server performance
     * @return int
     */
    private function get_installation_batch_size() {
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit === '-1') {
            return 5; // Unlimited memory
        }
        
        $memory_bytes = $this->convert_memory_to_bytes($memory_limit);
        
        if ($memory_bytes >= 536870912) { // 512MB+
            return 4;
        } elseif ($memory_bytes >= 268435456) { // 256MB+
            return 3;
        } elseif ($memory_bytes >= 134217728) { // 128MB+
            return 2;
        } else {
            return 1; // Very limited memory
        }
    }
    
    /**
     * Check if there are more features to install
     * @return bool
     */
    private function has_more_features_to_install() {
        $installed_features = get_option('wcefp_installed_features', []);
        return count($installed_features) < count($this->enabled_features);
    }
    
    /**
     * Complete installation process
     * @return void
     */
    private function complete_installation() {
        $this->installation_status = 'completed';
        update_option('wcefp_installation_status', $this->installation_status);
        update_option('wcefp_installation_completed_at', current_time('mysql'));
        
        // Clean up scheduled events
        wp_clear_scheduled_hook('wcefp_continue_installation');
        
        Logger::info('Progressive installation completed successfully');
        
        // Trigger completion hook
        do_action('wcefp_installation_completed', $this);
    }
    
    /**
     * Load installation configuration
     * @return void
     */
    private function load_installation_config() {
        $this->enabled_features = get_option('wcefp_selected_features', ['core']);
        $this->performance_settings = get_option('wcefp_performance_settings', [
            'loading_mode' => self::MODE_PROGRESSIVE,
            'enable_caching' => true,
            'enable_logging' => true
        ]);
        $this->installation_status = get_option('wcefp_installation_status', 'not_started');
    }
    
    /**
     * Determine installation mode
     * @return void
     */
    private function determine_installation_mode() {
        // Check if we're in setup wizard
        if ($this->installation_status === 'not_started' && !get_option('wcefp_skip_wizard')) {
            $this->installation_mode = 'wizard_required';
            return;
        }
        
        // Use saved performance settings
        $this->installation_mode = $this->performance_settings['loading_mode'] ?? self::MODE_PROGRESSIVE;
        
        // Override based on environment if needed
        if ($this->should_force_minimal_mode()) {
            $this->installation_mode = self::MODE_MINIMAL;
        }
    }
    
    /**
     * Check if should force minimal mode
     * @return bool
     */
    private function should_force_minimal_mode() {
        // Check memory constraints
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit !== '-1') {
            $memory_bytes = $this->convert_memory_to_bytes($memory_limit);
            if ($memory_bytes < 134217728) { // Less than 128MB
                return true;
            }
        }
        
        // Check execution time constraints
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time > 0 && $max_execution_time < 30) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert memory limit to bytes
     * @param string $val
     * @return int
     */
    private function convert_memory_to_bytes($val) {
        $val = trim($val);
        if (empty($val)) return 0;
        
        $unit = strtolower(substr($val, -1));
        $val = (int) $val;
        
        switch ($unit) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
    
    /**
     * Get loading mode recommendations
     * @return array
     */
    public function get_loading_mode_recommendations() {
        $memory_score = $this->calculate_memory_score();
        $execution_score = $this->calculate_execution_score();
        $overall_score = ($memory_score + $execution_score) / 2;
        
        if ($overall_score >= 80) {
            return [
                'recommended_mode' => self::MODE_STANDARD,
                'message' => __('Your server can handle standard loading mode.', 'wceventsfp'),
                'features_limit' => 10
            ];
        } elseif ($overall_score >= 60) {
            return [
                'recommended_mode' => self::MODE_PROGRESSIVE,
                'message' => __('Progressive loading recommended for your server.', 'wceventsfp'),
                'features_limit' => 6
            ];
        } else {
            return [
                'recommended_mode' => self::MODE_MINIMAL,
                'message' => __('Minimal mode recommended due to server limitations.', 'wceventsfp'),
                'features_limit' => 3
            ];
        }
    }
    
    /**
     * Calculate memory score
     * @return int
     */
    private function calculate_memory_score() {
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit === '-1') return 100;
        
        $memory_bytes = $this->convert_memory_to_bytes($memory_limit);
        
        if ($memory_bytes >= 536870912) return 100;      // 512MB+
        if ($memory_bytes >= 268435456) return 80;       // 256MB+
        if ($memory_bytes >= 134217728) return 60;       // 128MB+
        if ($memory_bytes >= 67108864) return 40;        // 64MB+
        
        return 20;
    }
    
    /**
     * Calculate execution time score
     * @return int
     */
    private function calculate_execution_score() {
        $max_execution_time = ini_get('max_execution_time');
        
        if ($max_execution_time == 0) return 100; // Unlimited
        if ($max_execution_time >= 120) return 90;
        if ($max_execution_time >= 60) return 70;
        if ($max_execution_time >= 30) return 50;
        
        return 20;
    }
    
    /**
     * Force wizard mode for troubleshooting
     * @return void
     */
    public function force_wizard_mode() {
        update_option('wcefp_installation_status', 'wizard_required');
        $this->installation_status = 'wizard_required';
    }
    
    /**
     * Reset installation
     * @return void
     */
    public function reset_installation() {
        // Clear installation options
        delete_option('wcefp_installation_status');
        delete_option('wcefp_selected_features');
        delete_option('wcefp_performance_settings');
        delete_option('wcefp_installed_features');
        delete_option('wcefp_core_installed');
        
        // Clear scheduled events
        wp_clear_scheduled_hook('wcefp_continue_installation');
        
        // Reset internal state
        $this->installation_status = 'not_started';
        $this->enabled_features = ['core'];
        
        $this->safe_log('Installation reset completed');
    }
    
    /**
     * Safe logging method that doesn't depend on Logger class
     * 
     * @param string $message Log message
     * @param string $level Log level (info, error, debug)
     * @return void
     */
    private function safe_log($message, $level = 'info') {
        // Use the existing wcefp_debug_log function which is already loaded
        if (function_exists('wcefp_debug_log')) {
            wcefp_debug_log("[InstallationManager][{$level}] {$message}");
        } elseif (function_exists('error_log')) {
            error_log("WCEventsFP InstallationManager [{$level}]: {$message}");
        }
    }
}