<?php
/**
 * Plugin Name: WCEventsFP
 * Description: Plugin di prenotazione eventi & esperienze avanzato per WooCommerce. Sistema enterprise per competere con RegionDo/Bokun: gestione risorse (guide, attrezzature, veicoli), distribuzione multi-canale (Booking.com, Expedia, GetYourGuide), sistema commissioni/reseller, Google Reviews, tracking avanzato GA4/Meta, automazioni Brevo, AI recommendations, analytics real-time.
 * Version:     2.0.1
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
if (!defined('WCEFP_VERSION')) define('WCEFP_VERSION', '2.0.1');
if (!defined('WCEFP_PLUGIN_FILE')) define('WCEFP_PLUGIN_FILE', __FILE__);
if (!defined('WCEFP_PLUGIN_DIR')) define('WCEFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('WCEFP_PLUGIN_URL')) define('WCEFP_PLUGIN_URL', plugin_dir_url(__FILE__));

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
    
    // Set basic plugin options
    add_option('wcefp_version', '2.0.1');
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
    private $version = '2.0.1';
    
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

// Simplified, bulletproof activation hook
register_activation_hook(__FILE__, function() {
    try {
        // Essential checks only
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            wp_die(sprintf('WCEventsFP requires PHP 7.4 or higher. Current version: %s', PHP_VERSION), 'Plugin Activation Error');
        }
        
        // Don't require WooCommerce during activation - allow graceful degradation
        if (!class_exists('WooCommerce')) {
            // Just log a warning, don't fail activation
            error_log('WCEventsFP: WooCommerce not detected during activation - plugin will run in limited mode');
        }
        
        // Simple activation setup
        wcefp_safe_activation_fallback();
        
    } catch (Exception $e) {
        wp_die('WCEventsFP activation failed: ' . esc_html($e->getMessage()), 'Plugin Activation Error', ['back_link' => true]);
    } catch (Error $e) {
        wp_die('WCEventsFP activation failed with fatal error: ' . esc_html($e->getMessage()), 'Plugin Activation Error', ['back_link' => true]);
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

// Bulletproof plugin initialization - this is where the magic happens
add_action('plugins_loaded', function() {
    try {
        wcefp_debug_log('Starting bulletproof plugin initialization');
        
        // Get our simple, reliable plugin instance
        $plugin = WCEFP();
        
        if (!$plugin) {
            wcefp_emergency_error('Failed to create plugin instance');
            return;
        }
        
        // Initialize the plugin
        if (method_exists($plugin, 'init')) {
            $success = $plugin->init();
            if (!$success) {
                wcefp_debug_log('Plugin initialization returned false - running in limited mode');
            } else {
                wcefp_debug_log('Plugin initialized successfully');
            }
        } else {
            wcefp_debug_log('Plugin instance has no init method - this should not happen');
        }
        
        // Mark plugin as loaded
        if (!defined('WCEFP_PLUGIN_LOADED')) {
            define('WCEFP_PLUGIN_LOADED', true);
        }
        
    } catch (Exception $e) {
        wcefp_debug_log('Plugin initialization exception: ' . $e->getMessage());
        wcefp_emergency_error('Plugin initialization failed: ' . $e->getMessage());
    } catch (Error $e) {
        wcefp_debug_log('Plugin initialization fatal error: ' . $e->getMessage());
        wcefp_emergency_error('Fatal error during plugin initialization: ' . $e->getMessage());
    }
}, 20); // Load after WooCommerce

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