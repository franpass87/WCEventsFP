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
        
        // Activation and deactivation hooks - unified under ActivationHandler
        register_activation_hook(__FILE__, ['WCEFP\\Core\\ActivationHandler', 'activate']);
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
            
        } catch (\Throwable $e) {
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
                throw new \Exception('Bootstrap Plugin class not found');
            }
            
        } catch (\Throwable $e) {
            error_log('[WCEFP] Bootstrap error: ' . $e->getMessage());
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
     * @deprecated 2.2.0 Use WCEFP\Core\ActivationHandler::activate() instead
     * @return void
     */
    public function activate() {
        // This method is deprecated and no longer used
        // All activation logic has been moved to WCEFP\Core\ActivationHandler::activate()
        // This method is kept for backward compatibility but should not be called
        
        error_log('WCEventsFP: Deprecated activate() method called. Use ActivationHandler::activate() instead.');
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
            
        } catch (\Throwable $e) {
            error_log('WCEventsFP deactivation error: ' . $e->getMessage());
        }
    }
    
    /**
     * Create database tables
     * 
     * @deprecated 2.2.0 Use WCEFP\Core\ActivationHandler::create_database_tables() instead
     * @return void
     */
    private function create_database_tables() {
        error_log('WCEventsFP: Deprecated create_database_tables() method called. Use ActivationHandler instead.');
        // Method kept for backward compatibility but functionality moved to ActivationHandler
    }
    
    /**
     * Set default plugin options
     * 
     * @deprecated 2.2.0 Use WCEFP\Core\ActivationHandler::set_default_options() instead
     * @return void
     */
    private function set_default_options() {
        error_log('WCEventsFP: Deprecated set_default_options() method called. Use ActivationHandler instead.');
        // Method kept for backward compatibility but functionality moved to ActivationHandler
    }
    
    /**
     * Clean up old installation system options
     * 
     * @deprecated 2.2.0 Use WCEFP\Core\ActivationHandler::cleanup_installation_options() instead
     * @return void
     */
    private function cleanup_installation_options() {
        error_log('WCEventsFP: Deprecated cleanup_installation_options() method called. Use ActivationHandler instead.');
        // Method kept for backward compatibility but functionality moved to ActivationHandler
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

        } catch (\Throwable $e) {
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