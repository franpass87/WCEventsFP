<?php
/**
 * Plugin Name: WCEventsFP
 * Description: Plugin di prenotazione eventi & esperienze avanzato per WooCommerce. Sistema enterprise per competere con RegionDo/Bokun: gestione risorse (guide, attrezzature, veicoli), distribuzione multi-canale (Booking.com, Expedia, GetYourGuide), sistema commissioni/reseller, Google Reviews, tracking avanzato GA4/Meta, automazioni Brevo, AI recommendations, analytics real-time.
 * Version:     2.2.0 // x-release-please-version
 * Author:      Francesco Passeri
 * Author URI:  https://github.com/franpass87
 * Plugin URI:  https://github.com/franpass87/WCEventsFP
 * Text Domain: wceventsfp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.7.1
 * Requires PHP: 8.0
 * WC requires at least: 5.0
 * WC tested up to: 9.4
 * Network: false
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * 
 * WCEventsFP is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 * 
 * WCEventsFP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with WCEventsFP. If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 * 
 * @package   WCEventsFP
 * @author    Francesco Passeri
 * @copyright 2024 Francesco Passeri
 * @license   GPL-3.0+
 * @link      https://github.com/franpass87/WCEventsFP
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('WCEFP_VERSION', '2.2.0'); // x-release-please-version
define('WCEFP_PLUGIN_FILE', __FILE__);
define('WCEFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCEFP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class - singleton pattern
 */
class WCEventsFP {
    
    /**
     * Plugin instance
     * 
     * @var WCEventsFP|null
     */
    private static $instance = null;
    
    /**
     * Bootstrap plugin instance
     * 
     * @var WCEFP\Bootstrap\Plugin|null
     */
    private $plugin = null;
    
    /**
     * Get plugin instance
     * 
     * @return WCEventsFP
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the plugin
     * 
     * @return void
     */
    private function init() {
        // Load dependencies safely
        if (!$this->load_dependencies()) {
            return;
        }
        
        // Initialize plugin on WordPress init
        add_action('plugins_loaded', [$this, 'plugins_loaded'], 10);
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Load required dependencies
     * 
     * @return bool
     */
    private function load_dependencies() {
        try {
            // Load custom autoloader
            $autoloader_path = WCEFP_PLUGIN_DIR . 'includes/autoloader.php';
            if (file_exists($autoloader_path)) {
                require_once $autoloader_path;
            } else {
                // Fallback: load core classes manually
                $this->load_core_classes();
            }
            
            // Load string safety functions for PHP 8.1 compatibility
            $strings_path = WCEFP_PLUGIN_DIR . 'includes/Support/strings.php';
            if (file_exists($strings_path)) {
                require_once $strings_path;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->handle_error('Failed to load dependencies: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Load core classes manually (fallback)
     * 
     * @return void
     */
    private function load_core_classes() {
        $core_classes = [
            'includes/Core/Container.php',
            'includes/Core/ServiceProvider.php',
            'includes/Bootstrap/Plugin.php',
            'includes/Utils/Logger.php'
        ];
        
        foreach ($core_classes as $class_file) {
            $path = WCEFP_PLUGIN_DIR . $class_file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
    
    /**
     * Initialize plugin when WordPress is loaded
     * 
     * @return void
     */
    public function plugins_loaded() {
        try {
            // Check dependencies
            if (!$this->check_dependencies()) {
                return;
            }
            
            // Initialize bootstrap plugin directly - no progressive loading
            if (class_exists('WCEFP\\Bootstrap\\Plugin')) {
                $this->plugin = new WCEFP\Bootstrap\Plugin(WCEFP_PLUGIN_FILE);
                $this->plugin->init();
                
                // Load all features immediately
                $this->load_all_features();
                
            } else {
                throw new Exception('Bootstrap Plugin class not found');
            }
            
        } catch (Exception $e) {
            $this->handle_error('Plugin initialization failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Check plugin dependencies
     * 
     * @return bool
     */
    private function check_dependencies() {
        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '6.5', '<')) {
            $this->add_admin_notice('WCEventsFP requires WordPress 6.5 or higher.', 'error');
            return false;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $this->add_admin_notice('WCEventsFP requires PHP 8.0 or higher. Current version: ' . PHP_VERSION, 'error');
            return false;
        }
        
        // Check WooCommerce
        if (!class_exists('WooCommerce')) {
            $this->add_admin_notice('WCEventsFP requires WooCommerce to be installed and activated.', 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Plugin activation
     * 
     * @return void
     */
    public function activate() {
        try {
            // Check dependencies on activation
            if (!$this->check_dependencies()) {
                wp_die('WCEventsFP cannot be activated due to missing dependencies.');
            }
            
            // Run activation tasks directly
            $this->create_database_tables();
            $this->set_default_options();
            
            // Clean up any old installation system options
            $this->cleanup_installation_options();
            
            // Flush rewrite rules for calendar feeds (Phase 3: Data & Integration)
            flush_rewrite_rules();
            
            // Clear any cached data
            wp_cache_flush();
            
        } catch (Exception $e) {
            wp_die('WCEventsFP activation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Plugin deactivation
     * 
     * @return void
     */
    public function deactivate() {
        try {
            // Clean up scheduled events
            wp_clear_scheduled_hook('wcefp_daily_cleanup');
            wp_clear_scheduled_hook('wcefp_continue_installation');
            
            // Clear cache
            wp_cache_flush();
            
        } catch (Exception $e) {
            error_log('WCEventsFP deactivation error: ' . $e->getMessage());
        }
    }
    
    /**
     * Create database tables
     * 
     * @return void
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Events table
        $table_name = $wpdb->prefix . 'wcefp_events';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            event_date datetime DEFAULT NULL,
            capacity int(11) DEFAULT 0,
            booked int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY event_date (event_date),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options
     * 
     * @return void
     */
    private function set_default_options() {
        $defaults = [
            'wcefp_version' => WCEFP_VERSION,
            'wcefp_installed' => time(),
            'wcefp_enable_logging' => 'yes',
            'wcefp_log_level' => 'info',
            'wcefp_full_activation' => 'yes' // Mark as fully activated without installation steps
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Clean up old installation system options
     * 
     * @return void
     */
    private function cleanup_installation_options() {
        // Remove old installation system options
        $old_options = [
            'wcefp_installation_status',
            'wcefp_installation_mode', 
            'wcefp_performance_settings',
            'wcefp_selected_features',
            'wcefp_installed_features',
            'wcefp_core_installed',
            'wcefp_redirect_to_wizard',
            'wcefp_setup_wizard_complete'
        ];
        
        foreach ($old_options as $option) {
            delete_option($option);
        }
        
        // Clear any scheduled installation events
        wp_clear_scheduled_hook('wcefp_continue_installation');
    }
    
    /**
     * Handle plugin errors
     * 
     * @param string $message Error message
     * @return void
     */
    private function handle_error($message) {
        error_log('WCEventsFP Error: ' . $message);
        
        if (is_admin()) {
            $this->add_admin_notice($message, 'error');
        }
    }
    
    /**
     * Add admin notice
     * 
     * @param string $message Notice message
     * @param string $type Notice type (error, warning, info, success)
     * @return void
     */
    private function add_admin_notice($message, $type = 'info') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s"><p><strong>WCEventsFP:</strong> %s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }
    
    /**
     * Get bootstrap plugin instance
     * 
     * @return WCEFP\Bootstrap\Plugin|null
     */
    public function get_plugin() {
        return $this->plugin;
    }
    
    /**
     * Get plugin version
     * 
     * @return string
     */
    public function get_version() {
        return WCEFP_VERSION;
    }
    
    /**
     * Load all plugin features immediately
     * 
     * @return void
     */
    private function load_all_features() {
        try {
            // Load all legacy functionality immediately
            if (file_exists(WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-logger.php')) {
                require_once WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-logger.php';
            }

            if (file_exists(WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-cache.php')) {
                require_once WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-cache.php';
            }

            if (file_exists(WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-enhanced-features.php')) {
                require_once WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-enhanced-features.php';
            }

            // Initialize essential legacy classes if they exist            
            if (class_exists('WCEFP_Cache')) {
                $GLOBALS['wcefp_cache'] = new WCEFP_Cache();
            }
            
            if (class_exists('WCEFP_Enhanced_Features')) {
                $GLOBALS['wcefp_enhanced_features'] = new WCEFP_Enhanced_Features();
            }

            // Initialize Phase 5: Advanced Booking Features
            if (file_exists(WCEFP_PLUGIN_DIR . 'includes/BookingFeatures/BookingFeaturesServiceProvider.php')) {
                require_once WCEFP_PLUGIN_DIR . 'includes/BookingFeatures/BookingFeaturesServiceProvider.php';
                
                if (class_exists('WCEFP\\BookingFeatures\\BookingFeaturesServiceProvider')) {
                    $GLOBALS['wcefp_booking_features'] = new WCEFP\BookingFeatures\BookingFeaturesServiceProvider();
                }
            }

            // Initialize Phase 6: Analytics & Automation
            if (file_exists(WCEFP_PLUGIN_DIR . 'includes/Analytics/AnalyticsServiceProvider.php')) {
                require_once WCEFP_PLUGIN_DIR . 'includes/Analytics/AnalyticsServiceProvider.php';
                
                if (class_exists('WCEFP\\Analytics\\AnalyticsServiceProvider')) {
                    $GLOBALS['wcefp_analytics'] = new WCEFP\Analytics\AnalyticsServiceProvider();
                }
            }

        } catch (Exception $e) {
            error_log('WCEventsFP: Feature loading failed: ' . $e->getMessage());
        }
    }
}

/**
 * Initialize the plugin
 * 
 * @return WCEventsFP
 */
function WCEFP() {
    return WCEventsFP::instance();
}

// Start the plugin
WCEFP();