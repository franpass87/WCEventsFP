<?php
/**
 * Main Plugin Bootstrap Class
 * 
 * @package WCEFP
 * @subpackage Bootstrap
 * @since 2.1.1
 */

namespace WCEFP\Bootstrap;

use WCEFP\Core\Container;
use WCEFP\Core\ServiceProvider;
use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin bootstrap class responsible for initialization and dependency management.
 */
class Plugin {
    
    /**
     * Plugin version
     * 
     * @var string
     */
    private $version = '2.1.4'; // x-release-please-version
    
    /**
     * Dependency injection container
     * 
     * @var Container
     */
    private $container;
    
    /**
     * Plugin file path
     * 
     * @var string
     */
    private $plugin_file;
    
    /**
     * Plugin directory path
     * 
     * @var string
     */
    private $plugin_dir;
    
    /**
     * Plugin URL
     * 
     * @var string
     */
    private $plugin_url;
    
    /**
     * Constructor
     * 
     * @param string $plugin_file Main plugin file path
     */
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        
        // Safely get plugin paths
        if (function_exists('plugin_dir_path')) {
            $this->plugin_dir = plugin_dir_path($plugin_file);
        } else {
            $this->plugin_dir = dirname($plugin_file) . '/';
        }
        
        if (function_exists('plugin_dir_url')) {
            $this->plugin_url = plugin_dir_url($plugin_file);
        } else {
            $this->plugin_url = '';
        }
        
        $this->container = new Container();
        
        // Register core services
        $this->register_core_services();
    }
    
    /**
     * Initialize the plugin
     * 
     * @return void
     */
    public function init() {
        try {
            // Check dependencies
            if (!$this->check_dependencies()) {
                return;
            }
            
            // Initialize services (modules will load textdomain on init)
            $this->init_services();
            
            // Register hooks
            $this->register_hooks();
            
            Logger::info('WCEventsFP plugin initialized successfully');
            
        } catch (\Exception $e) {
            Logger::error('Failed to initialize WCEventsFP plugin: ' . $e->getMessage());
            $this->handle_initialization_error($e);
        }
    }
    
    /**
     * Get plugin version
     * 
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Get plugin directory path
     * 
     * @return string
     */
    public function get_plugin_dir() {
        return $this->plugin_dir;
    }
    
    /**
     * Get plugin URL
     * 
     * @return string
     */
    public function get_plugin_url() {
        return $this->plugin_url;
    }
    
    /**
     * Get dependency injection container
     * 
     * @return Container
     */
    public function get_container() {
        return $this->container;
    }
    
    /**
     * Register core services in the container
     * 
     * @return void
     */
    private function register_core_services() {
        $this->container->singleton('plugin', $this);
        $this->container->singleton('logger', Logger::class);
    }
    
    /**
     * Check plugin dependencies
     * 
     * @return bool
     */
    private function check_dependencies() {
        // Check WooCommerce
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return false;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            add_action('admin_notices', [$this, 'php_version_notice']);
            return false;
        }
        
        return true;
    }
    
    /**
     * Load plugin textdomain - moved to init hook via ModulesServiceProvider
     * 
     * @return void
     * @deprecated 2.1.4 Moved to ModulesServiceProvider::load_textdomain()
     */
    private function load_textdomain() {
        // Textdomain loading moved to ModulesServiceProvider on init hook
        // This method kept for backward compatibility but is no longer called
    }
    
    /**
     * Initialize plugin services
     * 
     * @return void
     */
    private function init_services() {
        $service_providers = [
            \WCEFP\Modules\ModulesServiceProvider::class, // New centralized modules - priority loading
            \WCEFP\Admin\AdminServiceProvider::class,
            \WCEFP\Frontend\FrontendServiceProvider::class,
            \WCEFP\Core\Database\DatabaseServiceProvider::class,
            \WCEFP\Features\FeaturesServiceProvider::class,
        ];
        
        foreach ($service_providers as $provider_class) {
            try {
                if (class_exists($provider_class)) {
                    $provider = new $provider_class($this->container);
                    if ($provider instanceof ServiceProvider) {
                        $provider->register();
                        $provider->boot();
                        Logger::debug("Service provider {$provider_class} loaded successfully");
                    }
                } else {
                    Logger::warning("Service provider {$provider_class} not found - skipping");
                }
            } catch (\Exception $e) {
                Logger::error("Failed to initialize service provider {$provider_class}: " . $e->getMessage());
                
                // Don't let service provider failures break the entire plugin
                // Add admin notice for debugging but continue loading
                if (is_admin()) {
                    add_action('admin_notices', function() use ($provider_class, $e) {
                        if (current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG) {
                            echo '<div class="notice notice-warning"><p>';
                            echo '<strong>WCEventsFP Debug:</strong> Service provider ' . esc_html($provider_class) . ' failed to load: ';
                            echo esc_html($e->getMessage());
                            echo '</p></div>';
                        }
                    });
                }
            } catch (\Error $e) {
                Logger::error("Fatal error in service provider {$provider_class}: " . $e->getMessage());
                
                // Handle fatal errors in service providers
                if (is_admin() && current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG) {
                    add_action('admin_notices', function() use ($provider_class, $e) {
                        echo '<div class="notice notice-error"><p>';
                        echo '<strong>WCEventsFP Debug:</strong> Fatal error in ' . esc_html($provider_class) . ': ';
                        echo esc_html($e->getMessage());
                        echo '</p></div>';
                    });
                }
            }
        }
    }
    
    /**
     * Register WordPress hooks
     * 
     * @return void
     */
    private function register_hooks() {
        add_action('init', [$this, 'init_hook']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Handle initialization hook
     * 
     * @return void
     */
    public function init_hook() {
        // Register custom post types, taxonomies, etc.
        do_action('wcefp_init', $this);
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @return void
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'wcefp-frontend',
            $this->plugin_url . 'assets/css/frontend.css',
            [],
            $this->version
        );
        
        wp_enqueue_script(
            'wcefp-frontend',
            $this->plugin_url . 'assets/js/frontend.js',
            ['jquery'],
            $this->version,
            true
        );
    }
    
    /**
     * Enqueue admin assets
     * 
     * @return void
     */
    public function enqueue_admin_assets() {
        $screen = get_current_screen();
        
        // Only load on relevant admin pages
        if (strpos($screen->id, 'wcefp') !== false || $screen->post_type === 'product') {
            wp_enqueue_style(
                'wcefp-admin',
                $this->plugin_url . 'assets/css/admin.css',
                [],
                $this->version
            );
            
            wp_enqueue_script(
                'wcefp-admin',
                $this->plugin_url . 'assets/js/admin.js',
                ['jquery'],
                $this->version,
                true
            );
        }
    }
    
    /**
     * Handle initialization errors
     * 
     * @param \Exception $e Exception that occurred during initialization
     * @return void
     */
    private function handle_initialization_error(\Exception $e) {
        if (is_admin()) {
            add_action('admin_notices', function() use ($e) {
                $message = sprintf(
                    __('WCEventsFP initialization error: %s', 'wceventsfp'),
                    $e->getMessage()
                );
                echo '<div class="notice notice-error"><p><strong>' . esc_html($message) . '</strong></p></div>';
            });
        }
    }
    
    /**
     * Display WooCommerce missing notice
     * 
     * @return void
     */
    public function woocommerce_missing_notice() {
        $message = __('WCEventsFP requires WooCommerce to be installed and activated.', 'wceventsfp');
        echo '<div class="notice notice-error"><p><strong>' . esc_html($message) . '</strong></p></div>';
    }
    
    /**
     * Display PHP version notice
     * 
     * @return void
     */
    public function php_version_notice() {
        $message = sprintf(
            __('WCEventsFP requires PHP 7.4 or higher. Current version: %s', 'wceventsfp'),
            PHP_VERSION
        );
        echo '<div class="notice notice-error"><p><strong>' . esc_html($message) . '</strong></p></div>';
    }
}