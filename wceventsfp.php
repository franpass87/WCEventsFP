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

// Plugin constants
define('WCEFP_VERSION', '2.0.1');
define('WCEFP_PLUGIN_FILE', __FILE__);
define('WCEFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCEFP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Global error storage for emergency situations
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
 * Safe memory conversion function
 * 
 * @param string $val Memory value (e.g., '128M')
 * @return int Memory in bytes
 */
function wcefp_convert_memory_to_bytes($val) {
    if (empty($val) || !is_string($val)) return 0;
    
    $val = trim($val);
    if (is_numeric($val)) return (int)$val;
    
    $last = strtolower(substr($val, -1));
    $num = (int)$val;
    
    if ($num <= 0) return 0;
    
    switch($last) {
        case 'g': $num *= 1024; // fallthrough
        case 'm': $num *= 1024; // fallthrough  
        case 'k': $num *= 1024; break;
    }
    
    return $num;
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
 * Create minimal plugin instance for emergency situations
 * 
 * @return object
 */
function wcefp_create_minimal_plugin_instance() {
    return new class {
        public function init() {
            // Minimal initialization - just ensure basic legacy support
            wcefp_emergency_error('Plugin running in minimal emergency mode', 'warning');
        }
        
        public function get_version() {
            return '2.0.1';
        }
        
        public function __call($method, $args) {
            // Log method calls for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WCEventsFP Minimal Mode: Method '{$method}' called");
            }
            return null;
        }
    };
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

// Composer autoloader
$autoloader = WCEFP_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Manual fallback class loader for backwards compatibility
spl_autoload_register(function($class_name) {
    if (strpos($class_name, 'WCEFP\\') !== 0) {
        return;
    }
    
    // Convert namespace to file path
    $relative_class = str_replace('WCEFP\\', '', $class_name);
    $file_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';
    $full_path = WCEFP_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $file_path;
    
    if (file_exists($full_path)) {
        require_once $full_path;
    }
});

// Load legacy classes for backwards compatibility
$legacy_classes = [
    'includes/class-wcefp-logger.php' => 'WCEFP_Logger',
    'includes/class-wcefp-cache.php' => 'WCEFP_Cache', 
    'includes/class-wcefp-gift.php' => 'WCEFP_Gift',
    'includes/class-wcefp-frontend.php' => 'WCEFP_Frontend',
    'includes/class-wcefp-closures.php' => 'WCEFP_Closures',
    'includes/class-wcefp-validator.php' => 'WCEFP_Validator',
];

foreach ($legacy_classes as $file => $class_name) {
    if (file_exists(WCEFP_PLUGIN_DIR . $file) && !class_exists($class_name)) {
        try {
            require_once WCEFP_PLUGIN_DIR . $file;
        } catch (Exception $e) {
            wcefp_emergency_error("Failed to load {$file}: " . $e->getMessage());
        }
    }
}

/**
 * Get the main plugin instance
 * 
 * @return \WCEFP\Bootstrap\Plugin|WCEFP_Plugin|null
 */
function WCEFP() {
    static $instance = null;
    
    if ($instance === null) {
        try {
            if (class_exists('\WCEFP\Bootstrap\Plugin')) {
                $instance = new \WCEFP\Bootstrap\Plugin(__FILE__);
            } elseif (class_exists('WCEFP_Plugin')) {
                // Fallback to legacy system
                $instance = new WCEFP_Plugin();
            } else {
                // Emergency fallback - create a safe stub
                wcefp_emergency_error('Plugin bootstrap classes not found - using minimal mode');
                $instance = wcefp_create_minimal_plugin_instance();
            }
        } catch (Exception $e) {
            wcefp_emergency_error('Failed to create plugin instance: ' . $e->getMessage());
            $instance = wcefp_create_minimal_plugin_instance();
        } catch (Error $e) {
            wcefp_emergency_error('Fatal error creating plugin instance: ' . $e->getMessage());
            $instance = wcefp_create_minimal_plugin_instance();
        }
    }
    
    return $instance;
}

// Plugin activation with enhanced WSOD prevention
register_activation_hook(__FILE__, function() {
    // Prevent WSOD by capturing all errors and displaying them properly
    $activation_errors = [];
    
    try {
        // Pre-flight safety checks
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new Exception(sprintf('PHP 7.4+ required. Current: %s', PHP_VERSION));
        }
        
        if (!class_exists('WooCommerce')) {
            throw new Exception('WooCommerce plugin is required and must be activated before WCEventsFP.');
        }
        
        // Try to load the activation handler safely
        if (class_exists('\WCEFP\Core\ActivationHandler')) {
            \WCEFP\Core\ActivationHandler::activate();
        } else {
            // Safe fallback activation without complex dependencies
            wcefp_safe_activation_fallback();
        }
        
    } catch (Exception $e) {
        $activation_errors[] = $e->getMessage();
    } catch (Error $e) {
        $activation_errors[] = 'Fatal error during activation: ' . $e->getMessage();
    } catch (Throwable $e) {
        $activation_errors[] = 'Unexpected error during activation: ' . $e->getMessage();
    }
    
    // If there are errors, display them safely without causing WSOD
    if (!empty($activation_errors)) {
        $error_message = 'WCEventsFP Plugin Activation Failed:<br/><br/>';
        foreach ($activation_errors as $error) {
            $error_message .= '• ' . esc_html($error) . '<br/>';
        }
        $error_message .= '<br/>Please fix these issues and try activating the plugin again.<br/>';
        $error_message .= 'For support, visit the plugin documentation or contact support with these error details.';
        
        wp_die($error_message, 'Plugin Activation Error', [
            'response' => 500,
            'back_link' => true
        ]);
    }
});

// Plugin deactivation  
register_deactivation_hook(__FILE__, function() {
    try {
        if (class_exists('\WCEFP\Core\ActivationHandler')) {
            \WCEFP\Core\ActivationHandler::deactivate();
        }
        flush_rewrite_rules();
    } catch (Exception $e) {
        error_log('WCEventsFP deactivation error: ' . $e->getMessage());
    }
});

// Initialize plugin when WordPress is ready
add_action('plugins_loaded', function() {
    try {
        // Check WooCommerce dependency
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>WCEventsFP:</strong> ' . 
                     esc_html__('WooCommerce is required and must be activated.', 'wceventsfp') . 
                     '</p></div>';
            });
            return;
        }
        
        // Get plugin instance safely
        $plugin = WCEFP();
        if ($plugin && method_exists($plugin, 'init')) {
            $plugin->init();
        } elseif ($plugin) {
            // Plugin instance exists but no init method - that's ok
            wcefp_emergency_error('Plugin instance loaded without init method', 'warning');
        } else {
            // No plugin instance - this should not happen but let's handle it
            wcefp_emergency_error('Failed to get plugin instance during plugins_loaded', 'error');
        }
        
    } catch (Exception $e) {
        wcefp_emergency_error('Plugin initialization failed: ' . $e->getMessage());
    } catch (Error $e) {
        wcefp_emergency_error('Fatal error during plugin initialization: ' . $e->getMessage());
    }
}, 20); // Load after WooCommerce

// Legacy support - maintain existing global function for backwards compatibility
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

// Legacy admin metabox support
add_action('add_meta_boxes', function($post_type, $post) {
    if ($post_type === 'product') {
        add_meta_box(
            'wcefp_days_metabox',
            __('Giorni disponibili', 'wceventsfp'),
            'wcefp_add_days_metabox',
            'product',
            'normal',
            'default'
        );
    }
}, 10, 2);

function wcefp_add_days_metabox($post) {
    // Legacy metabox implementation
    $days = get_post_meta($post->ID, '_wcefp_days', true) ?: [];
    wp_nonce_field('wcefp_days_nonce', 'wcefp_days_nonce');
    
    echo '<div class="wcefp-weekdays-grid">';
    foreach (wcefp_get_weekday_labels() as $key => $label) {
        $checked = in_array($key, $days) ? 'checked' : '';
        echo "<div class='wcefp-weekday'>";
        echo "<input type='checkbox' name='_wcefp_days[]' value='{$key}' {$checked} id='wcefp_day_{$key}'>";
        echo "<label for='wcefp_day_{$key}'>{$label}</label>";
        echo "</div>";
    }
    echo '</div>';
}

// Save legacy metabox data
add_action('woocommerce_admin_process_product_object', function($product) {
    if (isset($_POST['wcefp_days_nonce']) && wp_verify_nonce($_POST['wcefp_days_nonce'], 'wcefp_days_nonce')) {
        $days = isset($_POST['_wcefp_days']) ? array_map('sanitize_text_field', $_POST['_wcefp_days']) : [];
        $product->update_meta_data('_wcefp_days', $days);
    }
});

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false);
    }
});