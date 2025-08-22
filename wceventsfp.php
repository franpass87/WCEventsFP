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

// ===== BULLETPROOF WSOD PREVENTION SYSTEM =====
// Include WSOD prevention FIRST - this is our safety net
if (!file_exists(__DIR__ . '/wcefp-wsod-preventer.php')) {
    // Emergency fallback if preventer file is missing
    wp_die('WCEventsFP: Critical file missing (wcefp-wsod-preventer.php). Plugin cannot load safely.', 'Plugin Error');
}

require_once __DIR__ . '/wcefp-wsod-preventer.php';

// Verify WSOD protection is active before proceeding
if (!defined('WCEFP_WSOD_PROTECTION_ACTIVE')) {
    return; // Safety abort - preventer handles the error display
}

// Plugin constants - essential definitions only
if (!defined('WCEFP_VERSION')) define('WCEFP_VERSION', '2.1.0');
if (!defined('WCEFP_PLUGIN_FILE')) define('WCEFP_PLUGIN_FILE', __FILE__);
if (!defined('WCEFP_PLUGIN_DIR')) define('WCEFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('WCEFP_PLUGIN_URL')) define('WCEFP_PLUGIN_URL', plugin_dir_url(__FILE__));

// ===== IMPROVED AUTOLOADING SYSTEM =====
// Load our manual autoloader BEFORE any class usage
if (!defined('WCEFP_AUTOLOADER_LOADED')) {
    require_once __DIR__ . '/wcefp-autoloader.php';
}

// Verify autoloader is working
if (!function_exists('wcefp_get_autoloader')) {
    wcefp_emergency_error('Failed to load autoloader system', 'error');
    return;
}

// ===== SERVER RESOURCES MONITORING =====
// Load server monitor for resource-aware initialization
require_once __DIR__ . '/wcefp-server-monitor.php';

// Initialize global error storage
$GLOBALS['wcefp_emergency_errors'] = [];

/**
 * Emergency error handler for critical situations
 * 
 * @param string $message Error message
 * @param string $type Error type (error, warning)
 * @return void
 */
function wcefp_emergency_error($message, $type = 'error') {
    $GLOBALS['wcefp_emergency_errors'][] = [
        'message' => $message,
        'type' => $type,
        'time' => time()
    ];
    
    // Also log to error_log
    error_log("WCEventsFP Emergency [{$type}]: {$message}");
    
    // Hook to display errors in HTML head
    if (!has_action('wp_head', 'wcefp_display_emergency_errors')) {
        add_action('wp_head', 'wcefp_display_emergency_errors', 1);
    }
    if (!has_action('admin_head', 'wcefp_display_emergency_errors')) {
        add_action('admin_head', 'wcefp_display_emergency_errors', 1);
    }
}

/**
 * Display emergency errors
 * 
 * @return void
 */
function wcefp_display_emergency_errors() {
    if (empty($GLOBALS['wcefp_emergency_errors'])) {
        return;
    }
    
    // Load emergency CSS inline
    echo '<style>
    .wcefp-emergency-error {
        position: fixed !important; top: 32px !important; left: 0 !important;
        width: 100% !important; background: #dc3232 !important; color: white !important;
        padding: 15px !important; z-index: 999999 !important; box-shadow: 0 1px 3px rgba(0,0,0,.13) !important;
    }
    .wcefp-emergency-error.warning { background: #ffb900 !important; color: #000 !important; }
    .wcefp-emergency-error-close { float: right !important; background: none !important; border: none !important;
        color: inherit !important; cursor: pointer !important; }
    </style>';
    
    foreach ($GLOBALS['wcefp_emergency_errors'] as $index => $error) {
        $class = 'wcefp-emergency-error ' . esc_attr($error['type']);
        echo '<div class="' . $class . '" id="wcefp-error-' . $index . '">';
        echo '<button class="wcefp-emergency-error-close" onclick="this.parentElement.style.display=\'none\'">&times;</button>';
        echo '<strong>WCEventsFP:</strong> ' . esc_html($error['message']);
        echo '</div>';
    }
}

/**
 * Bulletproof memory conversion - completely safe, no edge cases
 * 
 * @param string|int|null $val Memory value (e.g., '128M', '1G', 256000, null)
 * @return int Memory in bytes, or 0 if invalid/empty
 */
function wcefp_convert_memory_to_bytes($val) {
    // Handle null, false, empty values
    if ($val === null || $val === false || $val === '') {
        return 0;
    }
    
    // Handle numeric values (already in bytes)
    if (is_numeric($val)) {
        $bytes = (int) $val;
        return $bytes < 0 ? 0 : $bytes;
    }
    
    // Handle string values
    if (!is_string($val)) {
        return 0;
    }
    
    $val = trim($val);
    if ($val === '' || $val === '0') {
        return 0;
    }
    
    // Special case: unlimited memory
    if ($val === '-1') {
        return -1;
    }
    
    // Extract numeric part and unit
    if (!preg_match('/^(\d+(?:\.\d+)?)\s*([kmgtKMGT]?)$/i', $val, $matches)) {
        return 0; // Invalid format
    }
    
    $number = (float) $matches[1];
    $unit = isset($matches[2]) ? strtolower($matches[2]) : '';
    
    // Prevent negative or zero values
    if ($number <= 0) {
        return 0;
    }
    
    // Convert based on unit (case insensitive)
    switch ($unit) {
        case 't': $number *= 1024; // fall through
        case 'g': $number *= 1024; // fall through
        case 'm': $number *= 1024; // fall through  
        case 'k': $number *= 1024; break;
        case '':  // No unit = bytes
        default:  // Unknown unit = treat as bytes
            break;
    }
    
    // Ensure we return a positive integer, prevent overflow
    $result = (int) $number;
    return $result < 0 ? 0 : $result; // Handle potential overflow
}

/**
 * Safe activation fallback when main activation handler fails
 * 
 * @return void
 */
function wcefp_safe_activation_fallback() {
    // Only do essential setup without complex dependencies

    wcefp_clear_transients();

    // Set basic plugin options
    add_option('wcefp_version', '2.1.0');
    add_option('wcefp_activated_at', current_time('mysql'));
    add_option('wcefp_activation_mode', 'safe_fallback');
    
    // Log the fallback activation
    error_log('WCEventsFP: Using safe fallback activation - full initialization will happen on plugin load');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Bulletproof main plugin class - single point of control
 */
class WCEFP_Simple_Plugin {
    
    /**
     * Plugin version
     * @var string
     */
    private $version = '2.1.0';
    
    /**
     * Singleton instance
     * @var self|null
     */
    private static $instance = null;
    
    /**
     * Plugin initialization status
     * @var bool
     */
    private $initialized = false;
    
    /**
     * Get singleton instance
     * @return self
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        // Prevent multiple instantiation
    }
    
    /**
     * Initialize the plugin safely
     * @return bool True if successful, false otherwise
     */
    public function init() {
        if ($this->initialized) {
            return true; // Already initialized
        }
        
        try {
            wcefp_debug_log('Starting simple plugin initialization');
            
            // Essential dependency checks first
            if (!$this->check_essential_dependencies()) {
                return false;
            }
            
            // Load only critical components
            $this->load_critical_components();
            
            // Register essential hooks
            $this->register_essential_hooks();
            
            // Mark as initialized
            $this->initialized = true;
            
            wcefp_debug_log('Simple plugin initialization completed successfully');
            return true;
            
        } catch (Exception $e) {
            wcefp_debug_log('Plugin initialization failed: ' . $e->getMessage());
            wcefp_emergency_error('Plugin initialization failed: ' . $e->getMessage());
            return false;
        } catch (Error $e) {
            wcefp_debug_log('Fatal error during plugin initialization: ' . $e->getMessage());
            wcefp_emergency_error('Fatal error during plugin initialization: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check only the most essential dependencies
     * @return bool
     */
    private function check_essential_dependencies() {
        // WordPress loaded check
        if (!function_exists('add_action') || !function_exists('wp_die')) {
            wcefp_emergency_error('WordPress is not properly loaded');
            return false;
        }
        
        // WooCommerce check (but don't fail if missing - degrade gracefully)
        if (!class_exists('WooCommerce')) {
            if (is_admin()) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-warning is-dismissible">';
                    echo '<p><strong>WCEventsFP:</strong> WooCommerce is required for full functionality. ';
                    echo 'Some features will not be available until WooCommerce is activated.</p>';
                    echo '</div>';
                });
            }
            wcefp_debug_log('WooCommerce not found - plugin will run in limited mode');
        }
        
        return true;
    }
    
    /**
     * Load only the most critical components
     * @return void
     */
    private function load_critical_components() {
        // Simple, safe loading without complex dependencies
        $critical_files = [
            'includes/class-wcefp-logger.php'
        ];
        
        foreach ($critical_files as $file) {
            $path = WCEFP_PLUGIN_DIR . $file;
            if (file_exists($path)) {
                try {
                    require_once $path;
                    wcefp_debug_log("Loaded critical file: {$file}");
                } catch (Exception $e) {
                    wcefp_debug_log("Failed to load {$file}: " . $e->getMessage());
                    // Continue loading other files - don't let one failure stop everything
                }
            } else {
                wcefp_debug_log("Critical file not found: {$file} - continuing without it");
            }
        }
    }
    
    /**
     * Register only the most essential hooks
     * @return void
     */
    private function register_essential_hooks() {
        // Only register hooks that are absolutely necessary and safe
        if (class_exists('WooCommerce')) {
            add_action('init', [$this, 'init_woocommerce_integration'], 20);
        }
        
        // Admin-only hooks
        if (is_admin()) {
            add_action('admin_notices', [$this, 'display_admin_notices']);
            
            // Initialize feature manager if admin features are enabled
            if ($this->should_load_feature_manager()) {
                $this->init_feature_manager();
            }
        }
        
        // WooCommerce HPOS compatibility declaration
        add_action('before_woocommerce_init', [$this, 'declare_woo_compatibility']);
    }
    
    /**
     * Initialize WooCommerce integration safely
     * @return void
     */
    public function init_woocommerce_integration() {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        try {
            // Only add the most essential WooCommerce hooks here
            wcefp_debug_log('WooCommerce integration initialized');
        } catch (Exception $e) {
            wcefp_debug_log('WooCommerce integration failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Display any admin notices
     * @return void
     */
    public function display_admin_notices() {
        wcefp_display_emergency_errors();
    }
    
    /**
     * Declare WooCommerce compatibility
     * @return void
     */
    public function declare_woo_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            try {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WCEFP_PLUGIN_FILE, true);
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', WCEFP_PLUGIN_FILE, false);
            } catch (Exception $e) {
                wcefp_debug_log('WooCommerce compatibility declaration failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Get plugin version
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Check if plugin is initialized
     * @return bool
     */
    public function is_initialized() {
        return $this->initialized;
    }
    
    /**
     * Check if we should load the feature manager
     * @return bool
     */
    private function should_load_feature_manager() {
        // Always load feature manager for admins
        return current_user_can('manage_options');
    }
    
    /**
     * Initialize feature manager
     * @return void
     */
    private function init_feature_manager() {
        $feature_manager_file = WCEFP_PLUGIN_DIR . 'includes/Admin/FeatureManager.php';
        
        if (file_exists($feature_manager_file)) {
            try {
                require_once $feature_manager_file;
                
                if (class_exists('WCEFP\\Admin\\FeatureManager')) {
                    new \WCEFP\Admin\FeatureManager();
                    wcefp_debug_log('Feature manager initialized');
                }
            } catch (Exception $e) {
                wcefp_debug_log('Failed to initialize feature manager: ' . $e->getMessage());
            }
        }
    }
}

/**
 * Safe debug logging
 * 
 * @param string $message
 * @return void
 */
function wcefp_debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
        error_log('WCEventsFP Debug: ' . $message);
    }
}

/**
 * Clear all plugin-specific transients and cached data.
 *
 * @return void
 */
function wcefp_clear_transients() {
    $cache_file = WCEFP_PLUGIN_DIR . 'includes/class-wcefp-cache.php';

    if (file_exists($cache_file)) {
        require_once $cache_file;

        if (class_exists('WCEFP_Cache')) {
            WCEFP_Cache::clear_all();
        }
    }
}

/**
 * Flush transients when the plugin version changes.
 *
 * @return void
 */
function wcefp_maybe_clear_transients_on_load() {
    if (get_option('wcefp_version') !== WCEFP_VERSION) {
        wcefp_clear_transients();
        update_option('wcefp_version', WCEFP_VERSION);
    }
}
add_action('plugins_loaded', 'wcefp_maybe_clear_transients_on_load');

/**
 * Get the main plugin instance - simplified, bulletproof approach
 * 
 * @return WCEFP_Simple_Plugin
 */
function WCEFP() {
    return WCEFP_Simple_Plugin::instance();
}

// Check basic requirements early
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    wcefp_emergency_error(sprintf('PHP 7.4+ required. Current: %s', PHP_VERSION));
    return;
}

if (!function_exists('register_activation_hook') || !function_exists('register_deactivation_hook')) {
    wcefp_emergency_error('WordPress core functions not available');
    return;
}

// Enhanced activation hook with wizard integration
register_activation_hook(__FILE__, function() {
    try {
        wcefp_debug_log('Starting enhanced plugin activation');

        wcefp_clear_transients();

        // Essential checks only
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            wp_die(sprintf('WCEventsFP requires PHP 7.4 or higher. Current version: %s', PHP_VERSION), 'Plugin Activation Error');
        }
        
        // ===== RESOURCE-AWARE ACTIVATION =====
        // Check server resources before proceeding
        $recommended_mode = WCEFP_Server_Monitor::get_recommended_loading_mode();
        $resource_score = WCEFP_Server_Monitor::get_resource_score();
        
        wcefp_debug_log("Activation resource analysis: mode={$recommended_mode}, score={$resource_score}");
        
        // For ultra-minimal resources, do minimal setup only
        if ($recommended_mode === WCEFP_Server_Monitor::MODE_ULTRA_MINIMAL) {
            wcefp_ultra_minimal_activation();
            return;
        }
        
        // Load installation manager (with resource awareness)
        if (file_exists(WCEFP_PLUGIN_DIR . 'includes/Core/InstallationManager.php')) {
            require_once WCEFP_PLUGIN_DIR . 'includes/Core/InstallationManager.php';
            
            if (class_exists('WCEFP\\Core\\InstallationManager')) {
                $installation_manager = new \WCEFP\Core\InstallationManager();
                
                // Check if this is first activation or if wizard was skipped before
                $skip_wizard = get_option('wcefp_skip_wizard', false);
                
                // For minimal resources, skip wizard and go straight to minimal mode
                if ($recommended_mode === WCEFP_Server_Monitor::MODE_MINIMAL) {
                    update_option('wcefp_skip_wizard', true);
                    $skip_wizard = true;
                    wcefp_debug_log('Skipping wizard due to limited server resources');
                }
                
                if (!$skip_wizard && $installation_manager->needs_setup_wizard()) {
                    // Set status to require wizard
                    update_option('wcefp_installation_status', 'wizard_required');
                    
                    // Redirect to wizard after activation (will happen on next admin page load)
                    update_option('wcefp_redirect_to_wizard', true);
                    
                    wcefp_debug_log('Activation completed - wizard required');
                    return;
                }
                
                // If wizard was skipped, do progressive installation
                if ($installation_manager->start_progressive_installation()) {
                    wcefp_debug_log('Progressive installation started successfully');
                } else {
                    wcefp_debug_log('Progressive installation failed - falling back to minimal');
                    wcefp_minimal_activation_fallback();
                }
            }
        } else {
            // Fallback activation without installation manager
            wcefp_minimal_activation_fallback();
        }
        
        wcefp_debug_log('Plugin activation completed');
        
    } catch (Exception $e) {
        wcefp_debug_log('Activation exception: ' . $e->getMessage());
        
        // Try minimal activation as fallback
        try {
            wcefp_minimal_activation_fallback();
        } catch (Exception $fallback_e) {
            wp_die('WCEventsFP activation failed: ' . esc_html($e->getMessage()) . 
                   ' (Fallback also failed: ' . esc_html($fallback_e->getMessage()) . ')', 
                   'Plugin Activation Error', ['back_link' => true]);
        }
        
    } catch (Error $e) {
        wp_die('WCEventsFP activation failed with fatal error: ' . esc_html($e->getMessage()), 
               'Plugin Activation Error', ['back_link' => true]);
    }
});

/**
 * Ultra-minimal activation for severely resource-constrained servers
 * 
 * @return void
 */
function wcefp_ultra_minimal_activation() {
    wcefp_debug_log('Starting ultra-minimal activation - emergency mode');

    // Only the most essential options
    add_option('wcefp_version', '2.1.0');
    add_option('wcefp_activated_at', current_time('mysql'));
    add_option('wcefp_activation_mode', 'ultra_minimal');
    add_option('wcefp_installation_status', 'ultra_minimal_complete');
    add_option('wcefp_loading_mode', 'ultra_minimal');
    add_option('wcefp_skip_wizard', true); // Never show wizard in ultra-minimal mode
    
    // Record resource constraints
    $resource_report = WCEFP_Server_Monitor::generate_report();
    update_option('wcefp_resource_constraints', $resource_report);
    
    // No feature installation - just survival mode
    update_option('wcefp_selected_features', ['emergency_only']);
    update_option('wcefp_performance_settings', [
        'loading_mode' => 'ultra_minimal',
        'enable_caching' => false,
        'enable_logging' => false,  // Disable logging to save resources
        'max_features' => 0
    ]);
    
    wcefp_debug_log('Ultra-minimal activation completed - emergency mode configured');
}

/**
 * Minimal activation fallback
 * 
 * @return void
 */
function wcefp_minimal_activation_fallback() {
    wcefp_debug_log('Starting minimal activation fallback');

    wcefp_clear_transients();

    // Set basic plugin options
    add_option('wcefp_version', '2.1.0');
    add_option('wcefp_activated_at', current_time('mysql'));
    add_option('wcefp_activation_mode', 'minimal_fallback');
    add_option('wcefp_installation_status', 'minimal_complete');
    
    // Set minimal feature set
    update_option('wcefp_selected_features', ['core']);
    update_option('wcefp_performance_settings', [
        'loading_mode' => 'minimal',
        'enable_caching' => false,
        'enable_logging' => true
    ]);
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    wcefp_debug_log('Minimal activation fallback completed');
}

// Handle wizard redirect after activation
add_action('admin_init', function() {
    if (get_option('wcefp_redirect_to_wizard') && is_admin() && current_user_can('manage_options')) {
        delete_option('wcefp_redirect_to_wizard');
        
        $wizard_url = admin_url('admin.php?wcefp_setup=1');
        wp_redirect($wizard_url);
        exit;
    }
});

// Add action for progressive installation continuation
add_action('wcefp_continue_installation', function() {
    if (file_exists(WCEFP_PLUGIN_DIR . 'includes/Core/InstallationManager.php')) {
        require_once WCEFP_PLUGIN_DIR . 'includes/Core/InstallationManager.php';
        
        if (class_exists('WCEFP\\Core\\InstallationManager')) {
            $installation_manager = new \WCEFP\Core\InstallationManager();
            $installation_manager->continue_progressive_installation();
        }
    }
});

// Simple deactivation hook
register_deactivation_hook(__FILE__, function() {
    try {
        flush_rewrite_rules();
        wcefp_debug_log('Plugin deactivated successfully');
    } catch (Exception $e) {
        error_log('WCEventsFP deactivation error: ' . $e->getMessage());
    }
});

// Hook for setup wizard access
add_action('admin_init', function() {
    if (isset($_GET['wcefp_setup']) && is_admin()) {
        // Load setup wizard
        define('WCEFP_SETUP_WIZARD_ACTIVE', true);
        require_once WCEFP_PLUGIN_DIR . 'wcefp-setup-wizard.php';
        exit; // Wizard handles its own output
    }
});

// Check if we need installation wizard or progressive loading
add_action('plugins_loaded', function() {
    try {
        wcefp_debug_log('Starting intelligent plugin initialization');
        
        // ===== RESOURCE-AWARE INITIALIZATION =====
        // Check server resources first
        $recommended_mode = WCEFP_Server_Monitor::get_recommended_loading_mode();
        $resource_score = WCEFP_Server_Monitor::get_resource_score();
        
        wcefp_debug_log("Resource analysis: mode={$recommended_mode}, score={$resource_score}");
        
        // Ultra-minimal mode for severely constrained servers
        if ($recommended_mode === WCEFP_Server_Monitor::MODE_ULTRA_MINIMAL) {
            wcefp_ultra_minimal_init();
            return;
        }
        
        // Load installation manager (if resources allow)
        if (file_exists(WCEFP_PLUGIN_DIR . 'includes/Core/InstallationManager.php')) {
            try {
                require_once WCEFP_PLUGIN_DIR . 'includes/Core/InstallationManager.php';
            } catch (Exception $e) {
                wcefp_debug_log('Failed to load InstallationManager: ' . $e->getMessage());
                wcefp_ultra_minimal_init();
                return;
            }
        }
        
        // Check installation status
        if (class_exists('WCEFP\\Core\\InstallationManager')) {
            $installation_manager = new \WCEFP\Core\InstallationManager();
            
            // If setup wizard is needed, show admin notice and minimal functionality
            if ($installation_manager->needs_setup_wizard() && is_admin()) {
                wcefp_show_wizard_notice($installation_manager->get_setup_wizard_url());
                wcefp_minimal_admin_init();
                return;
            }
            
            // Initialize based on installation mode (but respect resource limits)
            $installation_mode = $installation_manager->get_installation_mode();
            
            // Override installation mode if resources are too limited
            if ($recommended_mode === WCEFP_Server_Monitor::MODE_MINIMAL && 
                in_array($installation_mode, ['standard', 'full'])) {
                $installation_mode = 'minimal';
                wcefp_debug_log('Overriding installation mode to minimal due to server resources');
            }
            
            wcefp_debug_log("Plugin installation mode: {$installation_mode}");
            
            switch ($installation_mode) {
                case 'minimal':
                    wcefp_minimal_init($installation_manager);
                    break;
                case 'progressive':
                    wcefp_progressive_init($installation_manager);
                    break;
                case 'standard':
                case 'full':
                default:
                    wcefp_standard_init($installation_manager);
                    break;
            }
        } else {
            // Fallback to simple initialization if InstallationManager not available
            wcefp_fallback_init();
        }
        
    } catch (Exception $e) {
        wcefp_debug_log('Plugin initialization exception: ' . $e->getMessage());
        wcefp_emergency_error('Plugin initialization failed: ' . $e->getMessage());
        wcefp_ultra_minimal_init(); // Ultimate fallback
    } catch (Error $e) {
        wcefp_debug_log('Plugin initialization fatal error: ' . $e->getMessage());
        wcefp_emergency_error('Fatal error during plugin initialization: ' . $e->getMessage());
        wcefp_ultra_minimal_init(); // Ultimate fallback
    }
}, 20); // Load after WooCommerce

/**
 * Ultra-minimal initialization for severely resource-constrained servers
 * This is the absolute minimum required to keep the plugin from causing WSOD
 * 
 * @return void
 */
function wcefp_ultra_minimal_init() {
    wcefp_debug_log('Starting ultra-minimal plugin initialization - emergency mode');
    
    // Only the most essential functionality
    // No feature loading, no admin menus, just basic survival mode
    
    if (is_admin()) {
        // Show a notice that we're running in emergency mode
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>WCEventsFP:</strong> Plugin running in minimal emergency mode due to server resource limitations. ';
            echo 'Contact your hosting provider to increase PHP memory limit and execution time for full functionality.</p>';
            echo '<p><small>Current resources: ' . round(memory_get_usage(true) / 1048576, 1) . 'MB used, ';
            echo ini_get('memory_limit') . ' limit, ' . ini_get('max_execution_time') . 's max execution time</small></p>';
            echo '</div>';
        });
        
        // Minimal admin menu - just a status page
        add_action('admin_menu', function() {
            add_options_page(
                'WCEventsFP Status', 
                'WCEventsFP Status', 
                'manage_options', 
                'wcefp-status', 
                function() {
                    echo '<div class="wrap">';
                    echo '<h1>WCEventsFP Status</h1>';
                    echo '<div class="notice notice-warning"><p><strong>Emergency Mode Active</strong></p>';
                    echo '<p>The plugin is running in ultra-minimal mode due to server limitations.</p></div>';
                    
                    $report = WCEFP_Server_Monitor::generate_report();
                    echo '<h2>Server Resources</h2>';
                    echo '<table class="form-table">';
                    echo '<tr><th>Memory Limit</th><td>' . $report['memory_limit_mb'] . ' MB</td></tr>';
                    echo '<tr><th>Memory Used</th><td>' . $report['memory_usage_mb'] . ' MB</td></tr>';
                    echo '<tr><th>Memory Available</th><td>' . $report['memory_available_mb'] . ' MB</td></tr>';
                    echo '<tr><th>Max Execution Time</th><td>' . $report['execution_time'] . ' seconds</td></tr>';
                    echo '<tr><th>PHP Version</th><td>' . $report['php_version'] . '</td></tr>';
                    echo '<tr><th>Resource Score</th><td>' . $report['resource_score'] . '/100</td></tr>';
                    echo '<tr><th>Status</th><td>' . esc_html($report['status']) . '</td></tr>';
                    echo '</table>';
                    
                    echo '<h3>Recommendations</h3>';
                    echo '<ul>';
                    echo '<li>Increase PHP memory limit to at least 256MB</li>';
                    echo '<li>Increase max execution time to at least 60 seconds</li>';
                    echo '<li>Contact your hosting provider for assistance</li>';
                    echo '</ul>';
                    echo '</div>';
                }
            );
        });
    }
    
    // WooCommerce compatibility (minimal)
    add_action('before_woocommerce_init', function() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            try {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WCEFP_PLUGIN_FILE, true);
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', WCEFP_PLUGIN_FILE, false);
            } catch (Exception $e) {
                wcefp_debug_log('WooCommerce compatibility declaration failed: ' . $e->getMessage());
            }
        }
    });
    
    // Mark plugin as loaded in ultra-minimal mode
    if (!defined('WCEFP_PLUGIN_LOADED')) {
        define('WCEFP_PLUGIN_LOADED', 'ultra_minimal');
    }
    
    // Update status option
    update_option('wcefp_loading_mode', 'ultra_minimal');
    update_option('wcefp_last_load_status', 'emergency');
    
    wcefp_debug_log('Ultra-minimal initialization completed - emergency mode active');
}

/**
 * Show setup wizard notice to administrators
 * 
 * @param string $wizard_url
 * @return void
 */
function wcefp_show_wizard_notice($wizard_url) {
    add_action('admin_notices', function() use ($wizard_url) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="notice notice-info is-dismissible">
            <h3><?php _e('WCEventsFP Setup Required', 'wceventsfp'); ?></h3>
            <p><?php _e('Welcome to WCEventsFP! To prevent any loading issues and configure the plugin safely, please run the setup wizard.', 'wceventsfp'); ?></p>
            <p>
                <a href="<?php echo esc_url($wizard_url); ?>" class="button button-primary">
                    <?php _e('🚀 Start Setup Wizard', 'wceventsfp'); ?>
                </a>
                <button type="button" class="button" onclick="jQuery(this).closest('.notice').fadeOut();">
                    <?php _e('Skip for Now', 'wceventsfp'); ?>
                </button>
            </p>
            <p><small><?php _e('The wizard will guide you through safe plugin activation and feature selection to prevent WSOD.', 'wceventsfp'); ?></small></p>
        </div>
        <?php
    });
}

/**
 * Minimal admin initialization for wizard mode
 * 
 * @return void
 */
function wcefp_minimal_admin_init() {
    // Only load essential admin functionality
    add_action('admin_menu', function() {
        add_options_page(
            __('WCEventsFP Setup', 'wceventsfp'),
            __('WCEventsFP Setup', 'wceventsfp'),
            'manage_options',
            'wcefp-setup',
            function() {
                $wizard_url = admin_url('admin.php?wcefp_setup=1');
                wp_redirect($wizard_url);
                exit;
            }
        );
    });
    
    wcefp_debug_log('Minimal admin initialization completed - wizard mode');
}

/**
 * Minimal plugin initialization
 * 
 * @param \WCEFP\Core\InstallationManager $installation_manager
 * @return void
 */
function wcefp_minimal_init($installation_manager) {
    wcefp_debug_log('Starting minimal plugin initialization');
    
    // Load only core functionality
    $core_files = [
        'includes/Utils/Logger.php',
        'includes/class-wcefp-logger.php'
    ];
    
    foreach ($core_files as $file) {
        $path = WCEFP_PLUGIN_DIR . $file;
        if (file_exists($path)) {
            try {
                require_once $path;
            } catch (Exception $e) {
                wcefp_debug_log("Failed to load minimal file {$file}: " . $e->getMessage());
            }
        }
    }
    
    // Create simple plugin instance with minimal features
    $plugin = WCEFP();
    if ($plugin && method_exists($plugin, 'init')) {
        $plugin->init();
    }
    
    // Mark plugin as loaded in minimal mode
    if (!defined('WCEFP_PLUGIN_LOADED')) {
        define('WCEFP_PLUGIN_LOADED', 'minimal');
    }
    
    wcefp_debug_log('Minimal initialization completed');
}

/**
 * Progressive plugin initialization
 * 
 * @param \WCEFP\Core\InstallationManager $installation_manager
 * @return void
 */
function wcefp_progressive_init($installation_manager) {
    wcefp_debug_log('Starting progressive plugin initialization');
    
    // Start with minimal init
    wcefp_minimal_init($installation_manager);
    
    // Load additional features based on what's enabled and installed
    $enabled_features = $installation_manager->get_enabled_features();
    $batch_size = 2; // Load 2 features at a time to prevent overload
    $loaded_count = 0;
    
    foreach ($enabled_features as $feature) {
        if ($loaded_count >= $batch_size) {
            // Schedule remaining features for next page load
            wcefp_schedule_feature_loading($enabled_features, $loaded_count);
            break;
        }
        
        try {
            wcefp_load_feature($feature);
            $loaded_count++;
        } catch (Exception $e) {
            wcefp_debug_log("Failed to load feature {$feature}: " . $e->getMessage());
        }
    }
    
    // Continue installation if needed
    if ($installation_manager->get_installation_status() === 'in_progress') {
        $installation_manager->continue_progressive_installation();
    }
    
    // Mark plugin as loaded in progressive mode
    if (!defined('WCEFP_PLUGIN_LOADED')) {
        define('WCEFP_PLUGIN_LOADED', 'progressive');
    }
    
    wcefp_debug_log('Progressive initialization completed');
}

/**
 * Standard plugin initialization
 * 
 * @param \WCEFP\Core\InstallationManager $installation_manager
 * @return void
 */
function wcefp_standard_init($installation_manager) {
    wcefp_debug_log('Starting standard plugin initialization');
    
    // Get our plugin instance
    $plugin = WCEFP();
    
    if (!$plugin) {
        wcefp_emergency_error('Failed to create plugin instance');
        return;
    }
    
    // Initialize the plugin
    if (method_exists($plugin, 'init')) {
        $success = $plugin->init();
        if (!$success) {
            wcefp_debug_log('Plugin initialization returned false - falling back to minimal mode');
            wcefp_minimal_init($installation_manager);
            return;
        }
    }
    
    // Load all enabled features
    $enabled_features = $installation_manager->get_enabled_features();
    foreach ($enabled_features as $feature) {
        try {
            wcefp_load_feature($feature);
        } catch (Exception $e) {
            wcefp_debug_log("Failed to load feature {$feature}: " . $e->getMessage());
        }
    }
    
    // Mark plugin as loaded
    if (!defined('WCEFP_PLUGIN_LOADED')) {
        define('WCEFP_PLUGIN_LOADED', 'standard');
    }
    
    wcefp_debug_log('Standard initialization completed');
}

/**
 * Fallback initialization when InstallationManager is not available
 * 
 * @return void
 */
function wcefp_fallback_init() {
    wcefp_debug_log('Starting fallback plugin initialization');
    
    // Create simple plugin instance
    $plugin = WCEFP();
    
    if ($plugin && method_exists($plugin, 'init')) {
        $success = $plugin->init();
        if (!$success) {
            wcefp_debug_log('Fallback initialization returned false - running in emergency mode');
        }
    }
    
    // Mark plugin as loaded in fallback mode
    if (!defined('WCEFP_PLUGIN_LOADED')) {
        define('WCEFP_PLUGIN_LOADED', 'fallback');
    }
    
    wcefp_debug_log('Fallback initialization completed');
}

/**
 * Load individual feature based on key
 * 
 * @param string $feature_key
 * @return void
 */
function wcefp_load_feature($feature_key) {
    $feature_files = [
        'admin_enhanced' => [
            'includes/Admin/MenuManager.php',
            'includes/Admin/ProductAdmin.php'
        ],
        'resources' => [
            'includes/class-wcefp-resource-management.php'
        ],
        'channels' => [
            'includes/class-wcefp-channel-management.php'
        ],
        'commissions' => [
            'includes/class-wcefp-commission-management.php'
        ],
        'reviews' => [
            'includes/class-wcefp-reviews.php'
        ],
        'tracking' => [
            'includes/class-wcefp-tracking.php'
        ],
        'automation' => [
            'includes/class-wcefp-automation.php'
        ],
        'ai_recommendations' => [
            'includes/class-wcefp-ai-recommendations.php'
        ],
        'realtime' => [
            'includes/class-wcefp-realtime-features.php'
        ]
    ];
    
    if (!isset($feature_files[$feature_key])) {
        wcefp_debug_log("Unknown feature key: {$feature_key}");
        return;
    }
    
    foreach ($feature_files[$feature_key] as $file) {
        $path = WCEFP_PLUGIN_DIR . $file;
        if (file_exists($path)) {
            try {
                require_once $path;
                wcefp_debug_log("Loaded feature file: {$file}");
            } catch (Exception $e) {
                throw new Exception("Failed to load feature file {$file}: " . $e->getMessage());
            }
        } else {
            wcefp_debug_log("Feature file not found: {$file}");
        }
    }
}

/**
 * Schedule feature loading for next page load
 * 
 * @param array $features
 * @param int $start_index
 * @return void
 */
function wcefp_schedule_feature_loading($features, $start_index) {
    $remaining_features = array_slice($features, $start_index);
    update_option('wcefp_pending_features', $remaining_features);
    
    wcefp_debug_log('Scheduled remaining features for next load: ' . implode(', ', $remaining_features));
}

// === Legacy Compatibility Functions ===
// Keep only essential legacy functions for backward compatibility

if (!function_exists('wcefp_get_weekday_labels')) {
    function wcefp_get_weekday_labels() {
        return [
            'monday' => __('Lunedì', 'wceventsfp'),
            'tuesday' => __('Martedì', 'wceventsfp'), 
            'wednesday' => __('Mercoledì', 'wceventsfp'),
            'thursday' => __('Giovedì', 'wceventsfp'),
            'friday' => __('Venerdì', 'wceventsfp'),
            'saturday' => __('Sabato', 'wceventsfp'),
            'sunday' => __('Domenica', 'wceventsfp')
        ];
    }
}