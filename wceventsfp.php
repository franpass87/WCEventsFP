<?php
/**
 * Plugin Name: WCEventsFP
 * Description: Plugin di prenotazione eventi & esperienze avanzato per WooCommerce. Sistema enterprise per competere con RegionDo/Bokun: gestione risorse (guide, attrezzature, veicoli), distribuzione multi-canale (Booking.com, Expedia, GetYourGuide), sistema commissioni/reseller, Google Reviews, tracking avanzato GA4/Meta, automazioni Brevo, AI recommendations, analytics real-time.
 * Version:     2.1.0
 * Author:      Francesco Passeri
 * Text Domain: wceventsfp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.3
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('WCEFP_VERSION', '2.1.0');
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
            
            // Initialize bootstrap plugin with progressive loading
            if (class_exists('WCEFP\\Bootstrap\\Plugin')) {
                $this->plugin = new WCEFP\Bootstrap\Plugin(WCEFP_PLUGIN_FILE);
                $this->plugin->init();
                
                // Initialize progressive loading
                $this->setup_progressive_loading();
                
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
        if (version_compare($wp_version, '5.0', '<')) {
            $this->add_admin_notice('WCEventsFP requires WordPress 5.0 or higher.', 'error');
            return false;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $this->add_admin_notice('WCEventsFP requires PHP 7.4 or higher. Current version: ' . PHP_VERSION, 'error');
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
            
            // Run activation tasks
            $this->create_database_tables();
            $this->set_default_options();
            
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
            'wcefp_log_level' => 'info'
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
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
     * Set up progressive loading of features
     * 
     * @return void
     */
    private function setup_progressive_loading() {
        if (!class_exists('WCEFP\\Core\\ProgressiveLoader')) {
            return;
        }
        
        try {
            $loader = new WCEFP\Core\ProgressiveLoader();
            
            // Add core features with priorities
            $loader->add_feature('logging', function() {
                // Load logging functionality
                if (file_exists(WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-logger.php')) {
                    require_once WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-logger.php';
                }
            }, 1);
            
            $loader->add_feature('cache', function() {
                // Load caching functionality
                if (file_exists(WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-cache.php')) {
                    require_once WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-cache.php';
                }
            }, 2);
            
            $loader->add_feature('enhanced_features', function() {
                // Load enhanced features
                if (file_exists(WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-enhanced-features.php')) {
                    require_once WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-enhanced-features.php';
                }
            }, 3);
            
            // Load first batch immediately
            $loaded = $loader->load_features();
            
            if (!empty($loaded)) {
                error_log('WCEventsFP: Progressive loading initialized. Loaded: ' . implode(', ', $loaded));
            }
            
        } catch (Exception $e) {
            error_log('WCEventsFP: Progressive loading setup failed: ' . $e->getMessage());
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