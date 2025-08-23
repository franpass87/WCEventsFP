<?php
/**
 * Hook Timing and Lifecycle Manager
 * 
 * Manages WordPress hook execution timing and plugin lifecycle events.
 * Fixes common timing issues and ensures proper initialization order.
 * 
 * @package WCEFP
 * @subpackage Core
 * @since 2.1.4
 */

namespace WCEFP\Core;

use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hook Timing Manager
 */
class HookTimingManager {
    
    /**
     * Plugin initialized flag
     * 
     * @var bool
     */
    private static $initialized = false;
    
    /**
     * Hook execution log
     * 
     * @var array
     */
    private static $hook_log = [];
    
    /**
     * Critical hooks that must execute in order
     * 
     * @var array
     */
    private static $critical_hooks = [
        'after_setup_theme',
        'init',
        'wp_loaded',
        'admin_init',
        'wp_enqueue_scripts',
        'admin_enqueue_scripts'
    ];
    
    /**
     * Plugin hooks with proper timing
     * 
     * @var array
     */
    private static $plugin_hooks = [
        // Text domain loading - must be early
        'plugins_loaded' => [
            'priority' => 1,
            'methods' => ['load_textdomain']
        ],
        
        // Core initialization
        'init' => [
            'priority' => 10,
            'methods' => ['register_post_types', 'register_taxonomies', 'init_rest_api']
        ],
        
        // After WordPress is fully loaded
        'wp_loaded' => [
            'priority' => 10,
            'methods' => ['init_shortcodes', 'init_frontend']
        ],
        
        // Admin initialization
        'admin_init' => [
            'priority' => 10,
            'methods' => ['init_admin', 'register_settings', 'maybe_upgrade_db']
        ],
        
        // Asset enqueuing
        'wp_enqueue_scripts' => [
            'priority' => 10,
            'methods' => ['enqueue_frontend_assets']
        ],
        
        'admin_enqueue_scripts' => [
            'priority' => 10,
            'methods' => ['enqueue_admin_assets']
        ],
        
        // Late initialization for integrations
        'wp_loaded' => [
            'priority' => 20,
            'methods' => ['init_integrations']
        ]
    ];
    
    /**
     * Initialize hook timing management
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        self::$initialized = true;
        
        // Register timing debug hooks if WP_DEBUG is enabled
        if (WP_DEBUG && defined('WCEFP_DEBUG_HOOKS') && WCEFP_DEBUG_HOOKS) {
            self::register_debug_hooks();
        }
        
        // Register proper plugin hooks
        self::register_plugin_hooks();
        
        // Add activation/deactivation hooks with proper timing
        self::register_lifecycle_hooks();
        
        // Check for common timing issues
        add_action('wp_loaded', [__CLASS__, 'check_timing_issues'], 999);
    }
    
    /**
     * Register debug hooks for timing analysis
     */
    private static function register_debug_hooks() {
        foreach (self::$critical_hooks as $hook) {
            add_action($hook, function() use ($hook) {
                self::log_hook_execution($hook);
            }, 1);
        }
        
        // Log when plugins are loaded
        add_action('plugins_loaded', function() {
            self::log_hook_execution('plugins_loaded', ['loaded_plugins' => get_option('active_plugins')]);
        }, 1);
        
        // Log theme setup
        add_action('after_setup_theme', function() {
            self::log_hook_execution('after_setup_theme', ['theme' => get_stylesheet()]);
        }, 1);
    }
    
    /**
     * Register plugin hooks with proper timing
     */
    private static function register_plugin_hooks() {
        // Text domain loading - critical timing
        add_action('plugins_loaded', [__CLASS__, 'load_textdomain'], 1);
        
        // Core initialization
        add_action('init', [__CLASS__, 'init_core'], 10);
        
        // WordPress fully loaded
        add_action('wp_loaded', [__CLASS__, 'init_loaded'], 10);
        
        // Admin initialization
        add_action('admin_init', [__CLASS__, 'init_admin'], 10);
        
        // Late initialization
        add_action('wp_loaded', [__CLASS__, 'init_late'], 20);
        
        // Asset enqueuing
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend'], 10);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin'], 10);
    }
    
    /**
     * Register activation/deactivation hooks
     */
    private static function register_lifecycle_hooks() {
        register_activation_hook(WCEFP_PLUGIN_FILE, [__CLASS__, 'on_activation']);
        register_deactivation_hook(WCEFP_PLUGIN_FILE, [__CLASS__, 'on_deactivation']);
        
        // Add upgrade check
        add_action('plugins_loaded', [__CLASS__, 'check_plugin_version'], 5);
    }
    
    /**
     * Load plugin text domain at the right time
     */
    public static function load_textdomain() {
        if (function_exists('load_plugin_textdomain')) {
            $loaded = load_plugin_textdomain(
                'wceventsfp',
                false,
                dirname(plugin_basename(WCEFP_PLUGIN_FILE)) . '/languages'
            );
            
            if (!$loaded && WP_DEBUG) {
                DiagnosticLogger::instance()->warning('Failed to load text domain', [
                    'domain' => 'wceventsfp',
                    'path' => dirname(plugin_basename(WCEFP_PLUGIN_FILE)) . '/languages'
                ]);
            }
            
            self::log_hook_execution('textdomain_loaded', ['success' => $loaded]);
        }
    }
    
    /**
     * Initialize core functionality
     */
    public static function init_core() {
        // Register post types and taxonomies
        if (function_exists('register_post_type')) {
            self::register_post_types();
        }
        
        // Initialize REST API
        if (class_exists('WP_REST_Server')) {
            self::init_rest_api();
        }
        
        // Register WP-CLI commands
        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            self::init_cli_commands();
        }
        
        self::log_hook_execution('core_initialized');
    }
    
    /**
     * Initialize when WordPress is fully loaded
     */
    public static function init_loaded() {
        // Initialize shortcodes
        if (function_exists('add_shortcode')) {
            self::init_shortcodes();
        }
        
        // Initialize frontend functionality
        if (!is_admin()) {
            self::init_frontend();
        }
        
        self::log_hook_execution('loaded_initialized');
    }
    
    /**
     * Initialize admin functionality
     */
    public static function init_admin() {
        if (!is_admin()) {
            return;
        }
        
        // Initialize admin components
        self::init_admin_components();
        
        // Register settings
        self::register_settings();
        
        // Check for database upgrades
        self::maybe_upgrade_database();
        
        self::log_hook_execution('admin_initialized');
    }
    
    /**
     * Late initialization for integrations
     */
    public static function init_late() {
        // Initialize third-party integrations
        self::init_integrations();
        
        // Run any post-load optimizations
        self::optimize_post_load();
        
        self::log_hook_execution('late_initialized');
    }
    
    /**
     * Enqueue frontend assets
     */
    public static function enqueue_frontend() {
        if (is_admin()) {
            return;
        }
        
        // Conditional asset loading
        self::enqueue_conditional_assets();
        
        self::log_hook_execution('frontend_assets_enqueued');
    }
    
    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin() {
        if (!is_admin()) {
            return;
        }
        
        $screen = get_current_screen();
        
        // Only load on relevant screens
        if (self::is_plugin_screen($screen)) {
            self::enqueue_admin_assets();
        }
        
        self::log_hook_execution('admin_assets_enqueued', ['screen' => $screen->id ?? 'unknown']);
    }
    
    /**
     * Plugin activation handler
     */
    public static function on_activation() {
        try {
            // Set activation transient for welcome redirect
            set_transient('wcefp_activation_redirect', true, 30);
            
            // Create database tables
            self::create_database_tables();
            
            // Add user capabilities
            if (class_exists('\WCEFP\Admin\RolesCapabilities')) {
                \WCEFP\Admin\RolesCapabilities::add_capabilities();
            }
            
            // Set initial options
            self::set_default_options();
            
            // Schedule cron events
            self::schedule_cron_events();
            
            // Log activation
            DiagnosticLogger::instance()->info('Plugin activated successfully', [
                'version' => WCEFP_VERSION,
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION
            ]);
            
        } catch (\Exception $e) {
            DiagnosticLogger::instance()->error('Plugin activation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't let activation fail silently
            wp_die(
                sprintf(__('WCEventsFP activation failed: %s', 'wceventsfp'), $e->getMessage()),
                __('Plugin Activation Error', 'wceventsfp'),
                ['back_link' => true]
            );
        }
    }
    
    /**
     * Plugin deactivation handler
     */
    public static function on_deactivation() {
        try {
            // Clear scheduled events
            self::clear_cron_events();
            
            // Clear transients
            self::clear_plugin_transients();
            
            // Optionally remove capabilities
            $keep_settings = get_option('wcefp_keep_data_on_deactivation', true);
            
            if (!$keep_settings) {
                if (class_exists('\WCEFP\Admin\RolesCapabilities')) {
                    \WCEFP\Admin\RolesCapabilities::remove_capabilities();
                }
            }
            
            // Log deactivation
            DiagnosticLogger::instance()->info('Plugin deactivated', [
                'keep_data' => $keep_settings
            ]);
            
        } catch (\Exception $e) {
            DiagnosticLogger::instance()->error('Plugin deactivation error', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check plugin version and run upgrades
     */
    public static function check_plugin_version() {
        $current_version = get_option('wcefp_version', '');
        
        if (version_compare($current_version, WCEFP_VERSION, '<')) {
            self::upgrade_plugin($current_version, WCEFP_VERSION);
            update_option('wcefp_version', WCEFP_VERSION);
        }
    }
    
    /**
     * Check for common timing issues
     */
    public static function check_timing_issues() {
        $issues = [];
        
        // Check if text domain was loaded too late
        if (!is_textdomain_loaded('wceventsfp')) {
            $issues[] = 'Text domain not loaded properly';
        }
        
        // Check if WooCommerce is available when needed
        if (!class_exists('WooCommerce') && self::requires_woocommerce()) {
            $issues[] = 'WooCommerce not available when required';
        }
        
        // Check if admin functions are called too early
        if (!is_admin() && did_action('admin_init') && !did_action('wp_loaded')) {
            $issues[] = 'Admin functions may have been called too early';
        }
        
        // Log timing issues
        if (!empty($issues)) {
            DiagnosticLogger::instance()->warning('Hook timing issues detected', [
                'issues' => $issues,
                'hook_log' => self::$hook_log
            ]);
        }
    }
    
    // Private implementation methods
    
    private static function register_post_types() {
        // This would register custom post types
        // For now, we're using WooCommerce products as events
        do_action('wcefp_register_post_types');
    }
    
    private static function init_rest_api() {
        if (class_exists('\WCEFP\API\RestApiManager')) {
            new \WCEFP\API\RestApiManager();
        }
    }
    
    private static function init_cli_commands() {
        if (class_exists('\WCEFP\CLI\Commands')) {
            // Commands are auto-registered in the CLI class
        }
    }
    
    private static function init_shortcodes() {
        if (class_exists('\WCEFP\Frontend\ShortcodeManager')) {
            new \WCEFP\Frontend\ShortcodeManager();
        }
    }
    
    private static function init_frontend() {
        // Initialize frontend-specific functionality
        do_action('wcefp_init_frontend');
    }
    
    private static function init_admin_components() {
        // Initialize admin classes
        if (class_exists('\WCEFP\Admin\Onboarding')) {
            new \WCEFP\Admin\Onboarding();
        }
        
        if (class_exists('\WCEFP\Admin\SystemStatus')) {
            new \WCEFP\Admin\SystemStatus();
        }
    }
    
    private static function register_settings() {
        // Settings are registered in the settings classes
        do_action('wcefp_register_settings');
    }
    
    private static function maybe_upgrade_database() {
        $db_version = get_option('wcefp_db_version', '');
        
        if (version_compare($db_version, WCEFP_VERSION, '<')) {
            self::upgrade_database($db_version);
            update_option('wcefp_db_version', WCEFP_VERSION);
        }
    }
    
    private static function init_integrations() {
        // Initialize third-party integrations
        do_action('wcefp_init_integrations');
    }
    
    private static function optimize_post_load() {
        // Run post-load optimizations
        wp_cache_flush_group('wcefp');
    }
    
    private static function enqueue_conditional_assets() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        // Check if post contains our shortcodes
        $shortcode_manager = class_exists('\WCEFP\Frontend\ShortcodeManager') 
            ? new \WCEFP\Frontend\ShortcodeManager() 
            : null;
            
        if ($shortcode_manager && method_exists($shortcode_manager, 'enqueue_shortcode_assets')) {
            $shortcode_manager->enqueue_shortcode_assets();
        }
    }
    
    private static function enqueue_admin_assets() {
        wp_enqueue_style(
            'wcefp-admin',
            WCEFP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WCEFP_VERSION
        );
        
        wp_enqueue_script(
            'wcefp-admin',
            WCEFP_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-admin', 'wcefp_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_admin'),
            'version' => WCEFP_VERSION
        ]);
    }
    
    private static function is_plugin_screen($screen) {
        if (!$screen) {
            return false;
        }
        
        return strpos($screen->id, 'wcefp') !== false ||
               in_array($screen->id, ['edit-product', 'product', 'edit-shop_order', 'shop_order']);
    }
    
    private static function create_database_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Example table - this would be expanded based on needs
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_bookings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id mediumint(9) NOT NULL,
            event_id mediumint(9) NOT NULL,
            booking_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            participants int(11) DEFAULT 1 NOT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY event_id (event_id),
            KEY booking_date (booking_date),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    private static function set_default_options() {
        $defaults = [
            'wcefp_version' => WCEFP_VERSION,
            'wcefp_db_version' => WCEFP_VERSION,
            'wcefp_default_capacity' => 10,
            'wcefp_disable_wc_emails_for_events' => false,
            'wcefp_ga4_enable' => true,
            'wcefp_conversion_optimization' => true,
            'wcefp_keep_data_on_uninstall' => true
        ];
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    private static function schedule_cron_events() {
        // Schedule cleanup events
        if (!wp_next_scheduled('wcefp_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wcefp_daily_cleanup');
        }
        
        // Schedule integration sync
        if (!wp_next_scheduled('wcefp_hourly_sync')) {
            wp_schedule_event(time(), 'hourly', 'wcefp_hourly_sync');
        }
    }
    
    private static function clear_cron_events() {
        wp_clear_scheduled_hook('wcefp_daily_cleanup');
        wp_clear_scheduled_hook('wcefp_hourly_sync');
    }
    
    private static function clear_plugin_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wcefp_%' 
             OR option_name LIKE '_transient_timeout_wcefp_%'"
        );
    }
    
    private static function upgrade_plugin($from_version, $to_version) {
        DiagnosticLogger::instance()->info('Plugin upgrade started', [
            'from' => $from_version,
            'to' => $to_version
        ]);
        
        // Version-specific upgrade routines would go here
        
        do_action('wcefp_plugin_upgraded', $from_version, $to_version);
    }
    
    private static function upgrade_database($from_version) {
        // Database upgrade routines would go here
        do_action('wcefp_database_upgraded', $from_version);
    }
    
    private static function requires_woocommerce() {
        // Check if current context requires WooCommerce
        return is_admin() && (
            isset($_GET['page']) && strpos($_GET['page'], 'wcefp') !== false ||
            get_current_screen() && strpos(get_current_screen()->id, 'product') !== false
        );
    }
    
    private static function log_hook_execution($hook, $context = []) {
        if (!WP_DEBUG || !defined('WCEFP_DEBUG_HOOKS') || !WCEFP_DEBUG_HOOKS) {
            return;
        }
        
        self::$hook_log[] = [
            'hook' => $hook,
            'time' => microtime(true),
            'memory' => memory_get_usage(true),
            'context' => $context
        ];
        
        DiagnosticLogger::instance()->debug("Hook executed: $hook", array_merge([
            'memory_usage' => size_format(memory_get_usage(true)),
            'time_since_start' => microtime(true) - (defined('WP_START_TIMESTAMP') ? WP_START_TIMESTAMP : $_SERVER['REQUEST_TIME_FLOAT'])
        ], $context));
    }
}