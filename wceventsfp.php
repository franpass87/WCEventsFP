<?php
/**
 * Plugin Name: WCEventsFP
 * Description: Plugin di prenotazione eventi & esperienze avanzato per WooCommerce. Sistema enterprise per competere con RegionDo/Bokun: gestione risorse (guide, attrezzature, veicoli), distribuzione multi-canale (Booking.com, Expedia, GetYourGuide), sistema commissioni/reseller, Google Reviews, tracking avanzato GA4/Meta, automazioni Brevo, AI recommendations, analytics real-time.
 * Version:     2.0.1
 * Author:      Francesco Passeri
 * Text Domain: wceventsfp
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('WCEFP_VERSION', '2.0.1');
define('WCEFP_PLUGIN_FILE', __FILE__);
// Use safe path/URL definitions that work even when WordPress functions aren't loaded
if (function_exists('plugin_dir_path')) {
    define('WCEFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('WCEFP_PLUGIN_URL', plugin_dir_url(__FILE__));
} else {
    // Fallback for when WordPress isn't fully loaded
    define('WCEFP_PLUGIN_DIR', dirname(__FILE__) . '/');
    define('WCEFP_PLUGIN_URL', ''); // Will be set later when WordPress is loaded
}

/**
 * Safe translation function that works even if textdomain isn't loaded
 * Prevents WSOD when translation functions are called too early
 * 
 * @param string $text Text to translate
 * @param string $textdomain Text domain
 * @return string Translated text or fallback
 */
function wcefp_safe_translate($text, $textdomain = 'wceventsfp') {
    if (function_exists('__') && function_exists('is_textdomain_loaded') && function_exists('load_plugin_textdomain') && function_exists('plugin_basename')) {
        // Check if textdomain is loaded, if not, load it
        if (!is_textdomain_loaded($textdomain)) {
            load_plugin_textdomain($textdomain, false, dirname(plugin_basename(__FILE__)) . '/languages');
        }
        return __($text, $textdomain);
    }
    return $text; // Fallback to original text
}

/**
 * Safe sprintf translation function
 * 
 * @param string $text Text to translate with placeholders
 * @param mixed ...$args Arguments for sprintf
 * @return string Translated and formatted text
 */
function wcefp_safe_sprintf($text, ...$args) {
    $translated = wcefp_safe_translate($text);
    return sprintf($translated, ...$args);
}

/**
 * Emergency error display - works even when WordPress admin notices fail
 * Creates a simple HTML error message that's always visible
 * 
 * @param string $message Error message to display
 * @param string $type Error type (error, warning, info)
 */
function wcefp_emergency_error_display($message, $type = 'error') {
    // Store error for later display
    $GLOBALS['wcefp_emergency_errors'] = $GLOBALS['wcefp_emergency_errors'] ?? [];
    $GLOBALS['wcefp_emergency_errors'][] = [
        'message' => $message,
        'type' => $type
    ];
    
    // Hook to display errors as soon as possible (only if WordPress is loaded)
    if (function_exists('has_action') && function_exists('add_action')) {
        if (!has_action('wp_head', 'wcefp_display_emergency_errors')) {
            add_action('wp_head', 'wcefp_display_emergency_errors', 1);
            add_action('admin_head', 'wcefp_display_emergency_errors', 1);
        }
    }
}

/**
 * Display emergency errors in HTML head - always visible
 */
function wcefp_display_emergency_errors() {
    if (empty($GLOBALS['wcefp_emergency_errors'])) return;
    
    echo '<style>
    .wcefp-emergency-error {
        position: fixed !important;
        top: 32px !important;
        left: 0 !important;
        width: 100% !important;
        background: #dc3232 !important;
        color: white !important;
        padding: 15px !important;
        z-index: 999999 !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif !important;
        font-size: 14px !important;
        line-height: 1.4 !important;
        box-shadow: 0 1px 3px rgba(0,0,0,.13) !important;
    }
    .wcefp-emergency-error.warning {
        background: #ffb900 !important;
        color: #000 !important;
    }
    .wcefp-emergency-error strong {
        font-weight: 600 !important;
    }
    .wcefp-emergency-error-close {
        float: right !important;
        background: none !important;
        border: none !important;
        color: inherit !important;
        font-size: 18px !important;
        cursor: pointer !important;
        padding: 0 !important;
        margin-left: 15px !important;
    }
    </style>';
    
    foreach ($GLOBALS['wcefp_emergency_errors'] as $index => $error) {
        $class = 'wcefp-emergency-error ' . $error['type'];
        echo '<div class="' . $class . '" id="wcefp-error-' . $index . '">';
        echo '<button class="wcefp-emergency-error-close" onclick="document.getElementById(\'wcefp-error-' . $index . '\').style.display=\'none\'">&times;</button>';
        echo '<strong>WCEventsFP Plugin Error:</strong> ' . esc_html($error['message']);
        echo '</div>';
    }
}

/**
 * Convert memory limit string to bytes
 * 
 * @param string $val Memory limit value (e.g., '128M', '1G')
 * @return int Memory limit in bytes
 */
function wcefp_convert_memory_to_bytes($val) {
    $val = trim($val);
    if (empty($val) || !is_string($val)) return 0;
    
    // Handle numeric-only values (already in bytes)
    if (is_numeric($val)) {
        return (int)$val;
    }
    
    $last = strtolower($val[strlen($val)-1]);
    $num = (int)$val;
    
    // Validate we have a positive number
    if ($num <= 0) return 0;
    
    switch($last) {
        case 'g':
            $num *= 1024;
            // fallthrough
        case 'm':
            $num *= 1024;
            // fallthrough 
        case 'k':
            $num *= 1024;
            break;
        default:
            // If no suffix, treat as bytes
            break;
    }
    
    return $num;
}

/* ---- Attivazione: tabelle principali ---- */
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, function () {
    try {
        // Early PHP version check to prevent WSOD
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $error_msg = 'WCEventsFP requires PHP 7.4 or higher. Current version: ' . PHP_VERSION;
            error_log('WCEFP activation error: ' . $error_msg);
            // Use safe error display that works even if translations fail
            if (function_exists('wp_die')) {
                wp_die(wcefp_safe_translate($error_msg));
            } else {
                die($error_msg);
            }
        }

        // Check critical PHP extensions
        $required_extensions = ['mysqli', 'json', 'mbstring'];
        $missing_extensions = [];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing_extensions[] = $ext;
            }
        }
        if (!empty($missing_extensions)) {
            $error_msg = 'WCEventsFP requires PHP extensions: ' . implode(', ', $missing_extensions);
            error_log('WCEFP activation error: ' . $error_msg);
            if (function_exists('wp_die')) {
                wp_die(wcefp_safe_translate($error_msg));
            } else {
                die($error_msg);
            }
        }

        // Check if WooCommerce is available before doing anything
        if (!class_exists('WooCommerce') && !function_exists('WC')) {
            // Log the issue but don't prevent activation - user will see admin notice later
            error_log('WCEFP: WooCommerce not found during activation. Plugin activated but requires WooCommerce to function.');
            return;
        }

        global $wpdb;
        
        // Ensure we have database access
        if (!$wpdb || !$wpdb->get_var("SELECT 1")) {
            error_log('WCEFP activation error: Unable to connect to database');
            return;
        }

        $charset = $wpdb->get_charset_collate();

        // Occorrenze
        $tbl1 = $wpdb->prefix . 'wcefp_occurrences';
        $sql1 = "CREATE TABLE IF NOT EXISTS $tbl1 (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          product_id BIGINT UNSIGNED NOT NULL,
          start_datetime DATETIME NOT NULL,
          end_datetime DATETIME NULL,
          capacity INT NOT NULL DEFAULT 0,
          booked INT NOT NULL DEFAULT 0,
          status VARCHAR(20) NOT NULL DEFAULT 'active',
          meta LONGTEXT NULL,
          UNIQUE KEY uniq_prod_start (product_id, start_datetime),
          INDEX (start_datetime),
          INDEX (product_id),
          INDEX (status)
        ) $charset;";

        // Chiusure straordinarie
        $tbl2 = $wpdb->prefix . 'wcefp_closures';
        $sql2 = "CREATE TABLE IF NOT EXISTS $tbl2 (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
          start_date DATE NOT NULL,
          end_date DATE NOT NULL,
          note VARCHAR(255) NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX (product_id),
          INDEX (start_date),
          INDEX (end_date)
        ) $charset;";

        // Voucher regalo
        $tbl3 = $wpdb->prefix . 'wcefp_vouchers';
        $sql3 = "CREATE TABLE IF NOT EXISTS $tbl3 (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          code VARCHAR(48) NOT NULL UNIQUE,
          product_id BIGINT UNSIGNED NOT NULL,
          order_id BIGINT UNSIGNED NOT NULL,
          recipient_name VARCHAR(180) NULL,
          recipient_email VARCHAR(180) NULL,
          message_text TEXT NULL,
          status VARCHAR(20) NOT NULL DEFAULT 'unused',
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          redeemed_at DATETIME NULL,
          INDEX (product_id),
          INDEX (order_id),
          INDEX (status)
        ) $charset;";

        // Check if wp-admin/includes/upgrade.php is available
        if (!function_exists('dbDelta')) {
            $upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
            if (file_exists($upgrade_file)) {
                require_once $upgrade_file;
            } else {
                error_log('WCEFP activation error: Cannot find wp-admin/includes/upgrade.php');
                return;
            }
        }
        
        $table_results = [];
        $table_results['occurrences'] = dbDelta($sql1);
        $table_results['closures'] = dbDelta($sql2);
        $table_results['vouchers'] = dbDelta($sql3);
        
        // Extra associati ai prodotti
        $tbl4 = $wpdb->prefix . 'wcefp_product_extras';
        $sql4 = "CREATE TABLE IF NOT EXISTS $tbl4 (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          product_id BIGINT UNSIGNED NOT NULL,
          extra_id BIGINT UNSIGNED NOT NULL,
          pricing_type VARCHAR(20) NOT NULL DEFAULT 'per_order',
          price DECIMAL(10,2) NOT NULL DEFAULT 0,
          required TINYINT(1) NOT NULL DEFAULT 0,
          max_qty INT UNSIGNED NOT NULL DEFAULT 0,
          stock INT UNSIGNED NOT NULL DEFAULT 0,
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          UNIQUE KEY uniq_prod_extra (product_id,extra_id),
          INDEX (product_id),
          INDEX (extra_id)
        ) $charset;";
        $table_results['product_extras'] = dbDelta($sql4);
        
        // Log successful table creation
        foreach ($table_results as $table_name => $result) {
            if (is_array($result) && !empty($result)) {
                error_log("WCEFP: Successfully processed table '$table_name': " . implode(', ', $result));
            }
        }
        
        // Log any database errors but don't crash
        if (!empty($wpdb->last_error)) {
            error_log('WCEFP activation database error: ' . $wpdb->last_error);
        }
        
    } catch (Exception $e) {
        error_log('WCEFP activation error: ' . $e->getMessage());
        // Don't throw - let WordPress handle the activation gracefully
    }
});

// Only initialize when WordPress is ready and this is not a plugin scanning context
if (defined('ABSPATH') && !defined('WP_INSTALLING') && function_exists('did_action') && did_action('plugins_loaded') === 0) {
    add_action('plugins_loaded', function () {
    try {
        // Load textdomain early and safely
        $textdomain_loaded = load_plugin_textdomain('wceventsfp', false, dirname(plugin_basename(__FILE__)) . '/languages');
        if (!$textdomain_loaded) {
            error_log('WCEFP: Could not load textdomain - translations may not work properly');
        }
        
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function () {
                $message = wcefp_safe_translate('WCEventsFP richiede WooCommerce attivo.', 'wceventsfp');
                echo '<div class="notice notice-error"><p><strong>WCEventsFP:</strong> ' . esc_html($message) . '</p></div>';
            });
            return;
        }

        // Resource availability check to prevent memory issues
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit && $memory_limit !== '-1') {
            try {
                // Convert memory limit to bytes (simplified version of wp_convert_hr_to_bytes)
                $memory_limit_bytes = wcefp_convert_memory_to_bytes($memory_limit);
                $memory_usage = memory_get_usage(true);
                $available_memory = $memory_limit_bytes - $memory_usage;
                
                // If less than 64MB available, show warning but continue
                if ($available_memory < 67108864) { // 64MB
                    error_log('WCEFP Warning: Low memory available (' . round($available_memory / 1024 / 1024, 1) . 'MB). Consider increasing memory_limit.');
                    if (is_admin()) {
                        add_action('admin_notices', function () use ($available_memory) {
                            $message = wcefp_safe_sprintf(
                                'Memoria disponibile bassa (%sMB). Considera di aumentare memory_limit per prestazioni ottimali.',
                                round($available_memory / 1024 / 1024, 1)
                            );
                            echo '<div class="notice notice-warning"><p><strong>WCEventsFP:</strong> ' . esc_html($message) . '</p></div>';
                        });
                    }
                }
            } catch (Exception $e) {
                error_log('WCEFP: Memory check failed: ' . $e->getMessage() . ' - continuing anyway');
            }
        }

    // Include core classes with error handling
    $core_classes = [
        'includes/class-wcefp-logger.php',
        'includes/class-wcefp-validator.php',
        'includes/class-wcefp-cache.php',
        'includes/class-wcefp-recurring.php',
        'includes/class-wcefp-closures.php',
        'includes/class-wcefp-gift.php',
        'includes/class-wcefp-frontend.php',
        'includes/class-wcefp-templates.php',
        'includes/class-wcefp-product-types.php',
        'includes/class-wcefp-enhanced-features.php',
        'includes/class-wcefp-resource-management.php',
        'includes/class-wcefp-channel-management.php',
        'includes/class-wcefp-commission-management.php',
        // Enhanced features
        'includes/class-wcefp-security-enhancement.php',
        'includes/class-wcefp-realtime-features.php',
        'includes/class-wcefp-advanced-monitoring.php',
        'includes/class-wcefp-accessibility-enhancement.php',
        'includes/class-wcefp-performance-optimization.php',
        // System improvements v2.0.1
        'includes/class-wcefp-error-handler.php',
        'includes/class-wcefp-i18n-enhancement.php',
        'includes/class-wcefp-debug-tools.php',
        'includes/class-wcefp-webhook-system.php'
    ];
    
    foreach ($core_classes as $class_file) {
        $file_path = WCEFP_PLUGIN_DIR . $class_file;
        if (file_exists($file_path)) {
            try {
                require_once $file_path;
            } catch (Exception $e) {
                error_log('WCEFP: Failed to load ' . $class_file . ': ' . $e->getMessage());
                add_action('admin_notices', function() use ($class_file, $e) {
                    $message = wcefp_safe_sprintf(
                        'Errore caricamento classe %s: %s',
                        basename($class_file),
                        $e->getMessage()
                    );
                    echo '<div class="notice notice-error"><p><strong>WCEventsFP:</strong> ' . esc_html($message) . '</p></div>';
                });
            }
        } else {
            error_log('WCEFP: Missing required class file: ' . $class_file);
            add_action('admin_notices', function() use ($class_file) {
                $message = wcefp_safe_sprintf('File classe mancante: %s', $class_file);
                echo '<div class="notice notice-error"><p><strong>WCEventsFP:</strong> ' . esc_html($message) . '</p></div>';
            });
        }
    }

    // Include admin classes (only in admin context) with error handling
    if (is_admin()) {
        $admin_classes = [
            'admin/class-wcefp-admin.php',
            'admin/class-wcefp-admin-settings.php',
            'admin/class-wcefp-analytics-dashboard.php',
            'admin/class-wcefp-meetingpoints.php',
            'admin/class-wcefp-vouchers-table.php',
            'admin/class-wcefp-vouchers-admin.php',
            'admin/class-wcefp-orders-bridge.php'
        ];
        
        foreach ($admin_classes as $class_file) {
            $file_path = WCEFP_PLUGIN_DIR . $class_file;
            if (file_exists($file_path)) {
                try {
                    require_once $file_path;
                } catch (Exception $e) {
                    error_log('WCEFP: Failed to load admin class ' . $class_file . ': ' . $e->getMessage());
                }
            } else {
                error_log('WCEFP: Missing admin class file: ' . $class_file);
            }
        }
        
        // Initialize admin classes with error handling
        try {
            if (class_exists('WCEFP_Admin')) WCEFP_Admin::init();
            if (class_exists('WCEFP_Vouchers_Admin')) WCEFP_Vouchers_Admin::init();
            if (class_exists('WCEFP_Orders_Bridge')) WCEFP_Orders_Bridge::init();
        } catch (Exception $e) {
            error_log('WCEFP Admin initialization error: ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                $message = wcefp_safe_sprintf('Errore di inizializzazione: %s', $e->getMessage());
                echo '<div class="notice notice-error"><p><strong>WCEventsFP:</strong> ' . esc_html($message) . '</p></div>';
            });
        }
    }

    // Initialize main plugin with error handling
    try {
        WCEFP()->init();
        // Mark plugin as fully loaded to enable additional hooks
        define('WCEFP_PLUGIN_LOADED', true);
        
        // Register additional hooks that need to be active when plugin is loaded
        add_action('add_meta_boxes', 'wcefp_add_days_metabox', 10, 2);
        add_action('save_post_product', 'wcefp_save_days_metabox');
        
        add_action('admin_head', function(){ ?>
<style>
  #wcefp_product_data .form-field .wrap .wp-editor-wrap{ max-width: 900px; }
  #_wcefp_languages{ max-width: 600px; }
  #_wcefp_languages[data-hint]::after{
    content: attr(data-hint);
    display:block; font-size:12px; opacity:.65; margin-top:4px;
  }
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var el = document.getElementById('_wcefp_languages');
  if(!el) return;
  function hint(){
    var v = (el.value||'').split(',').map(s=>s.trim()).filter(Boolean);
    el.setAttribute('data-hint', v.length ? ('Badge: '+v.join(' · ')) : 'Esempio: IT, EN');
  }
  el.addEventListener('input', hint); hint();
});
</script>
<?php });
        
    } catch (Exception $e) {
        error_log('WCEFP Plugin initialization error: ' . $e->getMessage());
        add_action('admin_notices', function() use ($e) {
            $message = wcefp_safe_sprintf('Errore di inizializzazione plugin: %s', $e->getMessage());
            echo '<div class="notice notice-error"><p><strong>WCEventsFP:</strong> ' . esc_html($message) . '</p></div>';
        });
        return; // Stop further execution
    }
} catch (Error $e) {
    // Handle fatal errors (PHP 7+ Error class)
    $error_msg = 'WCEFP Fatal Error in plugins_loaded: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    error_log($error_msg);
    
    $user_message = wcefp_safe_translate('Errore fatale durante il caricamento del plugin. Controlla i log per dettagli.');
    
    // Show both WordPress admin notice AND emergency error display
    add_action('admin_notices', function() use ($user_message) {
        echo '<div class="notice notice-error"><p><strong>WCEventsFP:</strong> ' . esc_html($user_message) . '</p></div>';
    });
    
    // Emergency error display that works even if admin notices fail
    wcefp_emergency_error_display($user_message . ' Dettagli: ' . $e->getMessage());
    
    // Attempt to deactivate plugin on fatal error to prevent WSOD
    if (function_exists('deactivate_plugins') && is_admin()) {
        deactivate_plugins(plugin_basename(__FILE__));
        if (function_exists('wp_die')) {
            $deactivation_message = wcefp_safe_translate('WCEventsFP è stato disattivato automaticamente a causa di un errore fatale. Controlla i log del server per maggiori dettagli.');
            wp_die($deactivation_message);
        }
    }
    return;
} catch (Exception $e) {
    // Handle regular exceptions
    $error_msg = 'WCEFP Exception in plugins_loaded: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    error_log($error_msg);
    
    $user_message = wcefp_safe_sprintf('Errore durante il caricamento del plugin: %s', $e->getMessage());
    
    // Show both WordPress admin notice AND emergency error display
    add_action('admin_notices', function() use ($user_message) {
        echo '<div class="notice notice-error"><p><strong>WCEventsFP:</strong> ' . esc_html($user_message) . '</p></div>';
    });
    
    // Emergency error display
    wcefp_emergency_error_display($user_message);
    return;
}
});
}

function WCEFP() {
    static $inst = null;
    if ($inst === null) {
        try {
            $inst = new WCEFP_Plugin();
        } catch (Error $e) {
            error_log('WCEFP: Fatal error creating plugin instance: ' . $e->getMessage());
            return null;
        } catch (Exception $e) {
            error_log('WCEFP: Exception creating plugin instance: ' . $e->getMessage());
            return null;
        }
    }
    return $inst;
}

class WCEFP_Plugin {

    public function init() {
        // Initialize database schema with error handling
        $this->ensure_db_schema();

        // Register hooks with error handling
        try {
            add_action('init', [$this, 'register_extra_cpt']);

            /* Tipi prodotto */
            add_filter('product_type_selector', [$this, 'register_product_types']);
            add_filter('woocommerce_product_class', [$this, 'map_product_class'], 10, 2);

            /* Tab prodotto & salvataggio */
            add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
            add_action('woocommerce_product_data_panels', [$this, 'render_product_data_panel']);
            add_action('woocommerce_admin_process_product_object', [$this, 'save_product_fields']);

            /* Esclusione archivi Woo */
            add_action('pre_get_posts', [$this, 'hide_from_archives']);

        /* Frontend assets */
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);

        /* GA4/DTL eventi base */
        add_action('woocommerce_thankyou', [$this, 'push_purchase_event_to_datalayer'], 20);

        /* Iniezione GA4/GTM + Meta Pixel */
        add_action('wp_head', [$this, 'inject_ga_scripts'], 5);
        add_action('wp_head', [$this, 'inject_meta_pixel_base'], 6);
        add_action('woocommerce_thankyou', [$this, 'meta_pixel_purchase'], 25);

        /* ICS su thank-you */
        add_action('woocommerce_thankyou', [$this, 'render_ics_downloads'], 30);

        /* Render su pagina prodotto */
        add_action('woocommerce_single_product_summary', ['WCEFP_Frontend','render_product_details'], 15);
        add_action('woocommerce_single_product_summary', ['WCEFP_Frontend','render_booking_widget_auto'], 35);

        /* Brevo */
        add_action('woocommerce_order_status_completed', [$this, 'brevo_on_completed']);

        /* Disattiva email Woo (solo eventi/esperienze) */
        add_filter('woocommerce_email_enabled_customer_processing_order', [$this,'maybe_disable_wc_mail'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order',  [$this,'maybe_disable_wc_mail'], 10, 2);
        add_filter('woocommerce_email_enabled_customer_on_hold_order',    [$this,'maybe_disable_wc_mail'], 10, 2);

        /* AJAX Admin (restano qui) */
        add_action('wp_ajax_wcefp_get_bookings', [$this, 'ajax_get_bookings']);
        add_action('wp_ajax_wcefp_get_calendar', [$this, 'ajax_get_calendar']);
        add_action('wp_ajax_wcefp_generate_occurrences', ['WCEFP_Recurring', 'ajax_generate_occurrences']);
        add_action('wp_ajax_wcefp_update_occurrence', [$this, 'ajax_update_occurrence']);
        add_action('wp_ajax_wcefp_add_closure', ['WCEFP_Closures', 'ajax_add_closure']);
        add_action('wp_ajax_wcefp_delete_closure', ['WCEFP_Closures', 'ajax_delete_closure']);
        add_action('wp_ajax_wcefp_list_closures', ['WCEFP_Closures', 'ajax_list_closures']);

        /* Export CSV */
        add_action('admin_post_wcefp_export_occurrences', [$this, 'export_occurrences_csv']);
        add_action('admin_post_wcefp_export_bookings',    [$this, 'export_bookings_csv']);

        /* Shortcode + AJAX pubblici */
        add_shortcode('wcefp_booking', ['WCEFP_Frontend', 'shortcode_booking']);
        add_action('wp_ajax_nopriv_wcefp_public_occurrences', ['WCEFP_Frontend', 'ajax_public_occurrences']);
        add_action('wp_ajax_wcefp_public_occurrences', ['WCEFP_Frontend', 'ajax_public_occurrences']);
        add_action('wp_ajax_nopriv_wcefp_add_to_cart', ['WCEFP_Frontend', 'ajax_add_to_cart']);
        add_action('wp_ajax_wcefp_add_to_cart', ['WCEFP_Frontend', 'ajax_add_to_cart']);
        
        /* Analytics tracking AJAX */
        add_action('wp_ajax_wcefp_track_analytics', [$this, 'ajax_track_analytics']);
        add_action('wp_ajax_nopriv_wcefp_track_analytics', [$this, 'ajax_track_analytics']);

        /* Prezzo dinamico + meta */
        add_action('woocommerce_before_calculate_totals', ['WCEFP_Frontend', 'apply_dynamic_price']);
        add_action('woocommerce_checkout_create_order_line_item', ['WCEFP_Frontend', 'add_line_item_meta'], 10, 4);

        /* Anti-overbooking */
        add_action('woocommerce_order_status_processing', [$this, 'allocate_seats_on_status']);
        add_action('woocommerce_order_status_completed',  [$this, 'allocate_seats_on_status']);
        add_action('woocommerce_order_status_refunded',   [$this, 'release_seats_on_status']);
        add_action('woocommerce_order_status_cancelled',  [$this, 'release_seats_on_status']);
        add_action('woocommerce_order_status_failed',     [$this, 'release_seats_on_status']);

        add_action('woocommerce_order_status_processing', [$this, 'allocate_extras_on_status']);
        add_action('woocommerce_order_status_completed',  [$this, 'allocate_extras_on_status']);
        add_action('woocommerce_order_status_refunded',   [$this, 'release_extras_on_status']);
        add_action('woocommerce_order_status_cancelled',  [$this, 'release_extras_on_status']);
        add_action('woocommerce_order_status_failed',     [$this, 'release_extras_on_status']);

        /* ICS routing */
        add_action('init', [$this, 'serve_ics']);

        /* Gift */
        WCEFP_Gift::init();
        
        } catch (Exception $e) {
            error_log('WCEFP Hook registration error: ' . $e->getMessage());
            throw $e; // Re-throw to be caught by the main initialization handler
        }
    }

    private function ensure_db_schema(){
        global $wpdb;
        
        try {
            $charset = $wpdb->get_charset_collate();
            
            // Check if main table exists before trying to modify it
            $tbl = $wpdb->prefix.'wcefp_occurrences';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$tbl'");
            
            if ($table_exists) {
                // Only try to get columns if table exists
                $cols = $wpdb->get_results("SHOW COLUMNS FROM $tbl", ARRAY_A);
                if ($cols) {
                    $names = array_map(function($c){ return $c['Field']; }, $cols);
                    if (!in_array('status', $names, true)) {
                        $wpdb->query("ALTER TABLE $tbl ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
                        // Check if index creation is needed and safe
                        $indices = $wpdb->get_results("SHOW INDEX FROM $tbl WHERE Key_name = 'status'");
                        if (empty($indices)) {
                            $wpdb->query("CREATE INDEX status ON $tbl (status)");
                        }
                    }
                }
            }
            
            // Create additional tables with error handling
            $tables_to_create = [
                'wcefp_closures' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_closures (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    note VARCHAR(255) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX (product_id), INDEX (start_date), INDEX (end_date)
                ) $charset",
                
                'wcefp_vouchers' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_vouchers (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(48) NOT NULL UNIQUE,
                    product_id BIGINT UNSIGNED NOT NULL,
                    order_id BIGINT UNSIGNED NOT NULL,
                    recipient_name VARCHAR(180) NULL,
                    recipient_email VARCHAR(180) NULL,
                    message_text TEXT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'unused',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    redeemed_at DATETIME NULL,
                    INDEX (product_id), INDEX (order_id), INDEX (status)
                ) $charset",
                
                'wcefp_product_extras' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wcefp_product_extras (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    product_id BIGINT UNSIGNED NOT NULL,
                    extra_id BIGINT UNSIGNED NOT NULL,
                    pricing_type VARCHAR(20) NOT NULL DEFAULT 'per_order',
                    price DECIMAL(10,2) NOT NULL DEFAULT 0,
                    required TINYINT(1) NOT NULL DEFAULT 0,
                    max_qty INT UNSIGNED NOT NULL DEFAULT 0,
                    stock INT UNSIGNED NOT NULL DEFAULT 0,
                    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                    UNIQUE KEY uniq_prod_extra (product_id,extra_id),
                    INDEX (product_id), INDEX (extra_id)
                ) $charset"
            ];
            
            foreach ($tables_to_create as $table_name => $sql) {
                $result = $wpdb->query($sql);
                if ($result === false && !empty($wpdb->last_error)) {
                    error_log("WCEFP: Failed to create/update table $table_name: " . $wpdb->last_error);
                }
            }
            
        } catch (Exception $e) {
            error_log('WCEFP Database schema error: ' . $e->getMessage());
            // Continue execution - don't crash the plugin
        }
    }

    public function register_extra_cpt(){
        register_post_type('wcefp_extra', [
            'label' => __('Extra','wceventsfp'),
            'public' => false,
            'show_ui' => true,
            'supports' => ['title','editor']
        ]);
    }

    /* ---------- Product Types ---------- */
    public function register_product_types($types) {
        $types['wcefp_event'] = __('Evento', 'wceventsfp');
        $types['wcefp_experience'] = __('Esperienza', 'wceventsfp');
        return $types;
    }
    public function map_product_class($classname, $type) {
        if ($type === 'wcefp_event') return 'WC_Product_WCEFP_Event';
        if ($type === 'wcefp_experience') return 'WC_Product_WCEFP_Experience';
        return $classname;
    }

    /* ---------- Product Tab ---------- */
    public function add_product_data_tab($tabs) {
        $tabs['wcefp_tab'] = [
            'label'    => __('Eventi/Esperienze', 'wceventsfp'),
            'target'   => 'wcefp_product_data',
            'class'    => ['show_if_wcefp_event','show_if_wcefp_experience'],
            'priority' => 21,
        ];
        return $tabs;
    }

    public function render_product_data_panel() {
        global $post; ?>
        <div id="wcefp_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <h3><?php _e('Prezzi e capacità', 'wceventsfp'); ?></h3>
                <?php
                woocommerce_wp_text_input(['id'=>'_wcefp_price_adult','label'=>__('Prezzo Adulto (€)','wceventsfp'),'type'=>'number','custom_attributes'=>['step'=>'0.01','min'=>'0']]);
                woocommerce_wp_text_input(['id'=>'_wcefp_price_child','label'=>__('Prezzo Bambino (€)','wceventsfp'),'type'=>'number','custom_attributes'=>['step'=>'0.01','min'=>'0']]);
                woocommerce_wp_text_input(['id'=>'_wcefp_capacity_per_slot','label'=>__('Capienza per slot','wceventsfp'),'type'=>'number','custom_attributes'=>['step'=>'1','min'=>'0']]);
                woocommerce_wp_text_input(['id'=>'_wcefp_duration_minutes','label'=>__('Durata slot (minuti)','wceventsfp'),'type'=>'number','custom_attributes'=>['step'=>'5','min'=>'0']]);
                ?>
            </div>

            <div class="options_group">
                <h3><?php _e('Info esperienza', 'wceventsfp'); ?></h3>
                <?php
                // Lingue (CSV)
                woocommerce_wp_text_input([
                    'id'          => '_wcefp_languages',
                    'label'       => __('Lingue (es. IT, EN)', 'wceventsfp'),
                    'type'        => 'text',
                    'placeholder' => 'IT, EN',
                    'description' => __('Separa con virgola. Verranno mostrati come badge nel frontend.', 'wceventsfp'),
                    'desc_tip'    => true,
                ]);

                // Meeting point (logica invariata)
                $points = get_option('wcefp_meetingpoints', []);
                $points = is_array($points) ? $points : [];
                $selected_point = get_post_meta($post->ID, '_wcefp_meeting_point', true);
                if (!empty($points)) {
                    echo '<p class="form-field"><label>' . __('Meeting point','wceventsfp') . '</label><span>';
                    foreach ($points as $pt) {
                        $addr = is_array($pt) ? ( $pt['address'] ?? '' ) : $pt;
                        echo '<label><input type="radio" name="wcefp_meeting_point" value="' . esc_attr($addr) . '" ' . checked($selected_point, $addr, false) . '> ' . esc_html($addr) . '</label><br>';
                    }
                    echo '</span></p>';
                }

                // Mini editor per contenuti elenco
                $fields = [
                    '_wcefp_includes'     => __('Incluso', 'wceventsfp'),
                    '_wcefp_excludes'     => __('Escluso', 'wceventsfp'),
                    '_wcefp_cancellation' => __('Politica di cancellazione', 'wceventsfp'),
                ];
                foreach ($fields as $fid => $flabel) {
                    $val = get_post_meta($post->ID, $fid, true);
                    echo '<p class="form-field"><label>' . esc_html($flabel) . '</label><span class="wrap">';
                    wp_editor(
                        $val,
                        $fid,
                        [
                            'textarea_name' => $fid,
                            'media_buttons' => false,
                            'teeny'         => true,
                            'quicktags'     => true,
                            'textarea_rows' => 4,
                            'tinymce'       => [
                                'toolbar1' => 'bold,italic,undo,redo,bullist,numlist,link,unlink',
                                'toolbar2' => '',
                            ],
                        ]
                    );
                    echo '<em style="opacity:.7;display:block;margin-top:4px;">' . esc_html(wcefp_safe_translate('Suggerimento: usa elenchi puntati e frasi brevi.')) . '</em>';
                    echo '</span></p>';
                }
                ?>
            </div>

            <div class="options_group">
                <h3><?php _e('Extra opzionali', 'wceventsfp'); ?></h3>
                <?php
                    global $wpdb;
                    $tbl = $wpdb->prefix.'wcefp_product_extras';
                    $rows = $wpdb->get_results($wpdb->prepare("SELECT pe.*, p.post_title FROM $tbl pe LEFT JOIN {$wpdb->posts} p ON p.ID=pe.extra_id WHERE pe.product_id=%d ORDER BY pe.sort_order ASC", $post->ID), ARRAY_A);
                    $extras = [];
                    foreach ($rows as $r) {
                        $extras[] = [
                            'id'=>intval($r['extra_id']),
                            'name'=>$r['post_title'],
                            'pricing_type'=>$r['pricing_type'],
                            'price'=>floatval($r['price']),
                            'required'=>intval($r['required'])?1:0,
                            'max_qty'=>intval($r['max_qty']),
                            'stock'=>intval($r['stock'])
                        ];
                    }
                    $all = get_posts(['post_type'=>'wcefp_extra','numberposts'=>-1,'post_status'=>'publish']);
                ?>
                <table class="wcefp-extra-table widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Nome','wceventsfp'); ?></th>
                            <th><?php _e('Tariffazione','wceventsfp'); ?></th>
                            <th><?php _e('Prezzo (€)','wceventsfp'); ?></th>
                            <th><?php _e('Obbligatorio','wceventsfp'); ?></th>
                            <th><?php _e('Max QTY','wceventsfp'); ?></th>
                            <th><?php _e('Stock','wceventsfp'); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="wcefp-extra-rows">
                        <?php foreach ($extras as $i => $ex): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="_wcefp_extras[<?php echo esc_attr($i); ?>][id]" value="<?php echo esc_attr($ex['id']); ?>" />
                                <input type="text" list="wcefp-extra-list" name="_wcefp_extras[<?php echo esc_attr($i); ?>][name]" value="<?php echo esc_attr($ex['name']); ?>" />
                            </td>
                            <td>
                                <select name="_wcefp_extras[<?php echo esc_attr($i); ?>][pricing_type]">
                                    <option value="per_order" <?php selected($ex['pricing_type'],'per_order'); ?>><?php _e('Per ordine','wceventsfp'); ?></option>
                                    <option value="per_person" <?php selected($ex['pricing_type'],'per_person'); ?>><?php _e('Per persona','wceventsfp'); ?></option>
                                    <option value="per_child" <?php selected($ex['pricing_type'],'per_child'); ?>><?php _e('Solo bambino','wceventsfp'); ?></option>
                                    <option value="per_adult" <?php selected($ex['pricing_type'],'per_adult'); ?>><?php _e('Solo adulto','wceventsfp'); ?></option>
                                </select>
                            </td>
                            <td><input type="number" step="0.01" min="0" name="_wcefp_extras[<?php echo esc_attr($i); ?>][price]" value="<?php echo esc_attr($ex['price']); ?>" /></td>
                            <td><input type="checkbox" name="_wcefp_extras[<?php echo esc_attr($i); ?>][required]" <?php checked($ex['required'],1); ?> /></td>
                            <td><input type="number" step="1" min="0" name="_wcefp_extras[<?php echo esc_attr($i); ?>][max_qty]" value="<?php echo esc_attr($ex['max_qty']); ?>" /></td>
                            <td><input type="number" step="1" min="0" name="_wcefp_extras[<?php echo esc_attr($i); ?>][stock]" value="<?php echo esc_attr($ex['stock']); ?>" /></td>
                            <td><button type="button" class="button wcefp-remove-extra">&times;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <datalist id="wcefp-extra-list">
                    <?php foreach ($all as $e) echo '<option value="'.esc_attr($e->post_title).'">'; ?>
                </datalist>
                <p><button type="button" class="button wcefp-add-extra"><?php _e('Aggiungi extra','wceventsfp'); ?></button></p>
                <script type="text/html" id="wcefp-extra-row-template">
                    <tr>
                        <td><input type="hidden" name="_wcefp_extras[{{INDEX}}][id]" value="" /><input type="text" list="wcefp-extra-list" name="_wcefp_extras[{{INDEX}}][name]" /></td>
                        <td><select name="_wcefp_extras[{{INDEX}}][pricing_type]"><option value="per_order"><?php _e('Per ordine','wceventsfp'); ?></option><option value="per_person"><?php _e('Per persona','wceventsfp'); ?></option><option value="per_child"><?php _e('Solo bambino','wceventsfp'); ?></option><option value="per_adult"><?php _e('Solo adulto','wceventsfp'); ?></option></select></td>
                        <td><input type="number" step="0.01" min="0" name="_wcefp_extras[{{INDEX}}][price]" /></td>
                        <td><input type="checkbox" name="_wcefp_extras[{{INDEX}}][required]" /></td>
                        <td><input type="number" step="1" min="0" name="_wcefp_extras[{{INDEX}}][max_qty]" /></td>
                        <td><input type="number" step="1" min="0" name="_wcefp_extras[{{INDEX}}][stock]" /></td>
                        <td><button type="button" class="button wcefp-remove-extra">&times;</button></td>
                    </tr>
                </script>
            </div>

            <div class="options_group">
                <h3><?php _e('Ricorrenze settimanali & Slot', 'wceventsfp'); ?></h3>
               <p class="form-field">
    <label><?php _e('Giorni','wceventsfp'); ?></label>
    <?php wp_nonce_field('wcefp_weekdays','wcefp_weekdays_nonce'); ?>
    <?php
    $days = get_post_meta($post->ID, '_wcefp_weekdays', true);
    $days = is_array($days) ? array_map('intval', $days) : [];

    $raw_labels = wcefp_get_weekday_labels();
    $labels = array_combine(
        array_keys($raw_labels),
        array_map('esc_html', $raw_labels)
    );
    ?>
    <span class="wrap wcefp-weekdays-grid">
        <?php foreach ($labels as $val => $label): ?>
            <label class="wcefp-weekday">
                <input type="checkbox" name="_wcefp_weekdays[]" value="<?php echo esc_attr($val); ?>"
                    <?php checked(in_array($val, $days, true), true); ?> />
                <?php echo $label; ?>
            </label>
        <?php endforeach; ?>
    </span>
</p>
                <p class="form-field">
                    <label for="_wcefp_time_slots"><?php _e('Slot (HH:MM, separati da virgola)','wceventsfp'); ?></label>
                    <input type="text" id="_wcefp_time_slots" name="_wcefp_time_slots" style="width:100%;" placeholder="11:00, 13:00, 19:30" value="<?php echo esc_attr(get_post_meta($post->ID, '_wcefp_time_slots', true)); ?>" />
                </p>
                <p class="form-field">
                    <label><?php _e('Genera occorrenze','wceventsfp'); ?></label>
                    <input type="date" id="wcefp_generate_from" /> →
                    <input type="date" id="wcefp_generate_to" />
                    <button class="button" type="button" id="wcefp-generate" data-product="<?php echo esc_attr($post->ID); ?>"><?php _e('Genera','wceventsfp'); ?></button>
                </p>
                <div id="wcefp-generate-result"></div>
            </div>
        </div>
        <?php
    }

    public function save_product_fields($product) {
        if (!isset($_POST['wcefp_weekdays_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcefp_weekdays_nonce'])), 'wcefp_weekdays')) {
            return;
        }
        $pid = $product->get_id();

        $allowed = [
            'a' => [ 'href'=>[], 'title'=>[], 'target'=>[] ],
            'strong'=>[], 'em'=>[],
            'ul'=>[], 'ol'=>[], 'li'=>[],
            'p'=>[], 'br'=>[]
        ];

        // Campi plain
        $keys_plain = ['_wcefp_price_adult','_wcefp_price_child','_wcefp_capacity_per_slot','_wcefp_time_slots','_wcefp_duration_minutes','_wcefp_languages'];
        foreach ($keys_plain as $k) {
            if (isset($_POST[$k])) {
                update_post_meta($pid, $k, wp_unslash($_POST[$k]));
            }
        }

        // Campi con HTML leggero
        $keys_html = ['_wcefp_includes','_wcefp_excludes','_wcefp_cancellation'];
        foreach ($keys_html as $k) {
            if (isset($_POST[$k])) {
                $raw = wp_unslash($_POST[$k]);
                $clean = wp_kses($raw, $allowed);
                update_post_meta($pid, $k, $clean);
            }
        }

        if (isset($_POST['wcefp_meeting_point'])) {
            update_post_meta($pid, '_wcefp_meeting_point', sanitize_text_field(wp_unslash($_POST['wcefp_meeting_point'])));
        } else {
            delete_post_meta($pid, '_wcefp_meeting_point');
        }

        // Extra opzionali
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_product_extras';
        $wpdb->delete($tbl, ['product_id'=>$pid]);
        if (isset($_POST['_wcefp_extras']) && is_array($_POST['_wcefp_extras'])) {
            $order = 0;
            foreach ($_POST['_wcefp_extras'] as $ex) {
                $name = sanitize_text_field($ex['name'] ?? '');
                if ($name === '') continue;
                $price = isset($ex['price']) ? floatval($ex['price']) : 0;
                $pricing = sanitize_key($ex['pricing_type'] ?? 'per_order');
                $required = isset($ex['required']) ? 1 : 0;
                $max_qty = isset($ex['max_qty']) ? intval($ex['max_qty']) : 0;
                $stock   = isset($ex['stock']) ? intval($ex['stock']) : 0;
                $extra_id = intval($ex['id'] ?? 0);
                if (!$extra_id) {
                    $existing = get_page_by_title($name, OBJECT, 'wcefp_extra');
                    if ($existing) $extra_id = $existing->ID;
                }
                if (!$extra_id) {
                    $extra_id = wp_insert_post(['post_type'=>'wcefp_extra','post_title'=>$name,'post_status'=>'publish']);
                } else {
                    wp_update_post(['ID'=>$extra_id,'post_title'=>$name]);
                }
                $wpdb->insert($tbl, [
                    'product_id'=>$pid,
                    'extra_id'=>$extra_id,
                    'pricing_type'=>$pricing,
                    'price'=>$price,
                    'required'=>$required,
                    'max_qty'=>$max_qty,
                    'stock'=>$stock,
                    'sort_order'=>$order++
                ], ['%d','%d','%s','%f','%d','%d','%d','%d']);
            }
        }
        $days = isset($_POST['_wcefp_weekdays']) ? array_map('intval',(array)$_POST['_wcefp_weekdays']) : [];
        update_post_meta($pid, '_wcefp_weekdays', $days);
    }

    /* ---------- Esclusione archivi ---------- */
    public function hide_from_archives($q) {
        if (is_admin() || !$q->is_main_query()) return;
        if (!(is_shop() || is_product_category() || is_product_tag() || is_post_type_archive('product'))) return;
        $tax_q = (array) $q->get('tax_query');
        $tax_q[] = [
            'taxonomy' => 'product_type',
            'field'    => 'slug',
            'terms'    => ['wcefp_event','wcefp_experience'],
            'operator' => 'NOT IN',
        ];
        $q->set('tax_query', $tax_q);
    }

    /* ---------- Frontend & GA4 ---------- */
    public function enqueue_frontend() {
        wp_register_style('wcefp-frontend', WCEFP_PLUGIN_URL.'assets/css/frontend.css', [], WCEFP_VERSION);
        wp_register_script('wcefp-frontend', WCEFP_PLUGIN_URL.'assets/js/frontend.js', ['jquery'], WCEFP_VERSION, true);
        wp_register_script('wcefp-advanced', WCEFP_PLUGIN_URL.'assets/js/advanced-features.js', ['jquery', 'wcefp-frontend'], WCEFP_VERSION, true);
        
        // Enhanced UI Components and Features
        wp_register_style('wcefp-modern-components', WCEFP_PLUGIN_URL.'assets/css/modern-components.css', ['wcefp-frontend'], WCEFP_VERSION);
        wp_register_style('wcefp-google-reviews', WCEFP_PLUGIN_URL.'assets/css/google-reviews.css', ['wcefp-frontend'], WCEFP_VERSION);
        
        wp_register_script('wcefp-ai-recommendations', WCEFP_PLUGIN_URL.'assets/js/ai-recommendations.js', ['jquery', 'wcefp-frontend'], WCEFP_VERSION, true);
        wp_register_script('wcefp-google-reviews', WCEFP_PLUGIN_URL.'assets/js/google-reviews.js', ['jquery', 'wcefp-frontend'], WCEFP_VERSION, true);
        
        // Conversion optimization assets
        wp_register_style('wcefp-conversion', WCEFP_PLUGIN_URL.'assets/css/conversion-optimization.css', ['wcefp-frontend'], WCEFP_VERSION);
        wp_register_script('wcefp-conversion', WCEFP_PLUGIN_URL.'assets/js/conversion-optimization.js', ['jquery', 'wcefp-advanced'], WCEFP_VERSION, true);
        
        wp_localize_script('wcefp-frontend', 'WCEFPData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wcefp_public'),
            'ga4_enabled' => (get_option('wcefp_ga4_enable', '1') === '1'),
            'meta_pixel_id' => sanitize_text_field(get_option('wcefp_meta_pixel_id','')),
            'google_ads_id' => sanitize_text_field(get_option('wcefp_google_ads_id','')),
            'enable_server_analytics' => (get_option('wcefp_enable_server_analytics', false) === true),
            'conversion_optimization' => (get_option('wcefp_conversion_optimization', true) === true),
            'enable_gamification' => (get_option('wcefp_enable_gamification', true) === true),
            'enable_ai_recommendations' => (get_option('wcefp_enable_ai_recommendations', true) === true),
            'enable_dark_theme' => (get_option('wcefp_enable_dark_theme', true) === true),
            'locale' => str_replace('_', '-', get_locale()),
            'currency' => get_woocommerce_currency(),
        ]);

        $leaflet_css_url = apply_filters('wcefp_leaflet_url', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', 'css');
        $leaflet_js_url  = apply_filters('wcefp_leaflet_url', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', 'js');

        wp_register_style('leaflet', $leaflet_css_url, [], '1.9.4');
        wp_register_script('leaflet', $leaflet_js_url, [], '1.9.4', true);

        // Enqueue core assets
        wp_enqueue_style('wcefp-frontend');
        wp_enqueue_script('wcefp-frontend');
        wp_enqueue_script('wcefp-advanced');
        
        // Enqueue enhanced features
        wp_enqueue_style('wcefp-modern-components');
        wp_enqueue_style('wcefp-google-reviews');
        wp_enqueue_script('wcefp-google-reviews');
        
        if (get_option('wcefp_enable_ai_recommendations', true)) {
            wp_enqueue_script('wcefp-ai-recommendations');
        }
        
        // Enqueue conversion optimization if enabled
        if (get_option('wcefp_conversion_optimization', true)) {
            wp_enqueue_style('wcefp-conversion');
            wp_enqueue_script('wcefp-conversion');
        }
        
        wp_enqueue_style('leaflet');
        wp_enqueue_script('leaflet');
    }

    /* Iniezione GA4/GTM (se impostati) */
    public function inject_ga_scripts() {
        if (is_admin()) return;
        $gtm_id = trim(get_option('wcefp_gtm_id',''));
        $ga4_id = trim(get_option('wcefp_ga4_id',''));
        $google_ads_id = trim(get_option('wcefp_google_ads_id',''));
        $ga4_enabled = (get_option('wcefp_ga4_enable','1') === '1');
        if (!$ga4_enabled) return;

        if ($gtm_id) {
            ?>
            <!-- Google Tag Manager (WCEventsFP) -->
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');</script>
            <!-- End Google Tag Manager -->
            <?php
        } elseif ($ga4_id) {
            ?>
            <!-- Google Analytics 4 (WCEventsFP) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_js($ga4_id); ?>"></script>
            <script>
              window.dataLayer = window.dataLayer || [];
              function gtag(){dataLayer.push(arguments);}
              gtag('js', new Date());
              gtag('config', '<?php echo esc_js($ga4_id); ?>', {
                enhanced_conversions: true,
                automatic_event_tracking: true,
                send_page_view: true
              });
              
              <?php if ($google_ads_id): ?>
              // Google Ads tracking
              gtag('config', '<?php echo esc_js($google_ads_id); ?>', {
                enhanced_conversions: true,
                allow_enhanced_conversions: true
              });
              <?php endif; ?>
            </script>
            <!-- End GA4 -->
            <?php
        }
        
        // Add Google Ads conversion tracking if separate from GA4
        if ($google_ads_id && !$ga4_id) {
            ?>
            <!-- Google Ads Conversion Tracking (WCEventsFP) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_js($google_ads_id); ?>"></script>
            <script>
              window.dataLayer = window.dataLayer || [];
              function gtag(){dataLayer.push(arguments);}
              gtag('js', new Date());
              gtag('config', '<?php echo esc_js($google_ads_id); ?>', {
                enhanced_conversions: true,
                allow_enhanced_conversions: true
              });
            </script>
            <!-- End Google Ads -->
            <?php
        }
    }

    public function push_purchase_event_to_datalayer($order_id) {
        $order = wc_get_order($order_id); if(!$order) return;
        $items = [];
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product(); if(!$product) continue;
            
            // Get additional product data for enhanced tracking
            $category = '';
            $terms = get_the_terms($product->get_id(), 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                $category = $terms[0]->name;
            }
            
            $items[] = [
                'item_id'      => (string)$product->get_id(),
                'item_name'    => $product->get_name(),
                'item_category'=> $product->get_type(),
                'item_category2'=> $category,
                'item_brand'   => get_bloginfo('name'),
                'quantity'     => (int)$item->get_quantity(),
                'price'        => (float)$order->get_item_total($item, false),
                'item_variant' => $this->get_booking_variant($item),
                'affiliation'  => get_bloginfo('name'),
                'coupon'       => $order->get_coupon_codes() ? implode(',', $order->get_coupon_codes()) : '',
                'discount'     => (float)$order->get_total_discount()
            ];
        }
        
        // Enhanced purchase event data
        $purchase_data = [
            'event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => (string)$order->get_order_number(),
                'affiliation' => get_bloginfo('name'),
                'value' => (float)$order->get_total(),
                'tax' => (float)$order->get_total_tax(),
                'shipping' => (float)$order->get_shipping_total(),
                'currency' => $order->get_currency(),
                'coupon' => $order->get_coupon_codes() ? implode(',', $order->get_coupon_codes()) : '',
                'payment_type' => $order->get_payment_method_title(),
                'items' => $items,
            ],
            // Additional custom parameters
            'customer_type' => $this->get_customer_type($order),
            'booking_lead_time' => $this->get_booking_lead_time($order),
            'total_participants' => $this->get_total_participants($order)
        ];
        
        // Output enhanced dataLayer event
        echo "<script>window.dataLayer=window.dataLayer||[];dataLayer.push(".wp_json_encode($purchase_data).");</script>";
        
        // Google Ads enhanced conversion
        $google_ads_id = trim(get_option('wcefp_google_ads_id',''));
        if ($google_ads_id && function_exists('gtag')) {
            echo "<script>";
            echo "if(typeof gtag !== 'undefined') {";
            echo "gtag('event', 'conversion', {";
            echo "'send_to': '" . esc_js($google_ads_id) . "',";
            echo "'value': " . (float)$order->get_total() . ",";
            echo "'currency': '" . esc_js($order->get_currency()) . "',";
            echo "'transaction_id': '" . esc_js($order->get_order_number()) . "'";
            echo "});";
            echo "}";
            echo "</script>";
        }
    }

    private function get_booking_variant($item) {
        // Extract booking details from order item meta
        $adults = $item->get_meta('_wcefp_adults', true) ?: 0;
        $children = $item->get_meta('_wcefp_children', true) ?: 0;
        $date = $item->get_meta('_wcefp_date', true) ?: '';
        $time = $item->get_meta('_wcefp_time', true) ?: '';
        
        return "{$adults}A{$children}C_{$date}_{$time}";
    }

    private function get_customer_type($order) {
        $customer_id = $order->get_customer_id();
        if (!$customer_id) return 'guest';
        
        // Check if returning customer
        $customer_orders = wc_get_orders([
            'customer_id' => $customer_id,
            'status' => ['completed', 'processing'],
            'limit' => 2
        ]);
        
        return count($customer_orders) > 1 ? 'returning' : 'new';
    }

    private function get_booking_lead_time($order) {
        // Calculate days between order date and experience date
        $order_date = $order->get_date_created();
        $experience_date = null;
        
        foreach ($order->get_items() as $item) {
            $date = $item->get_meta('_wcefp_date', true);
            if ($date) {
                $experience_date = new DateTime($date);
                break;
            }
        }
        
        if ($experience_date && $order_date) {
            $diff = $order_date->diff($experience_date);
            return $diff->days;
        }
        
        return 0;
    }

    private function get_total_participants($order) {
        $total = 0;
        foreach ($order->get_items() as $item) {
            $adults = (int)$item->get_meta('_wcefp_adults', true);
            $children = (int)$item->get_meta('_wcefp_children', true);
            $total += ($adults + $children) * $item->get_quantity();
        }
        return $total;
    }

    /* ---------- Meta Pixel ---------- */
    public function inject_meta_pixel_base() {
        if (is_admin()) return;
        $pixel_id = trim(get_option('wcefp_meta_pixel_id',''));
        if (!$pixel_id) return;
        ?>
        <!-- Meta Pixel Code (WCEventsFP) -->
        <script>
          !function(f,b,e,v,n,t,s)
          {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
          n.callMethod.apply(n,arguments):n.queue.push(arguments)};
          if(!f._fbq)f._fbq=n; n.push=n; n.loaded=!0; n.version='2.0';
          n.queue=[]; t=b.createElement(e); t.async=!0;
          t.src=v; s=b.getElementsByTagName(e)[0];
          s.parentNode.insertBefore(t,s)}(window, document,'script',
          'https://connect.facebook.net/en_US/fbevents.js');
          fbq('init', '<?php echo esc_js($pixel_id); ?>');
          fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none"
          src="https://www.facebook.com/tr?id=<?php echo esc_attr($pixel_id); ?>&ev=PageView&noscript=1"
        /></noscript>
        <!-- End Meta Pixel Code -->
        <?php
    }

    public function meta_pixel_purchase($order_id) {
        $pixel_id = trim(get_option('wcefp_meta_pixel_id',''));
        if (!$pixel_id) return;
        $order = wc_get_order($order_id); if(!$order) return;
        
        $value = (float)$order->get_total();
        $currency = $order->get_currency();
        
        // Get product details for enhanced tracking
        $content_ids = [];
        $contents = [];
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $content_ids[] = (string)$product->get_id();
                $contents[] = [
                    'id' => (string)$product->get_id(),
                    'quantity' => (int)$item->get_quantity(),
                    'item_price' => (float)$order->get_item_total($item, false)
                ];
            }
        }
        
        // Enhanced Meta Pixel Purchase event
        echo "<script>";
        echo "if(window.fbq){ ";
        echo "fbq('track','Purchase', {";
        echo "value: " . wp_json_encode($value) . ",";
        echo "currency: " . wp_json_encode($currency) . ",";
        echo "content_ids: " . wp_json_encode($content_ids) . ",";
        echo "content_type: 'product',";
        echo "contents: " . wp_json_encode($contents) . ",";
        echo "num_items: " . count($content_ids);
        echo "}); ";
        
        // Also track as CompleteRegistration for experience bookings
        echo "fbq('track','CompleteRegistration', {";
        echo "content_name: 'Experience Booking',";
        echo "value: " . wp_json_encode($value) . ",";
        echo "currency: " . wp_json_encode($currency);
        echo "}); ";
        
        echo "}";
        echo "</script>";
    }

    /* ---------- ICS ---------- */
    public function render_ics_downloads($order_id) {
        $order = wc_get_order($order_id); if(!$order) return;
        $ics = [];
        foreach ($order->get_items() as $item) {
            $pid = $item->get_product_id();
            $occId = $item->get_meta('Occorrenza');
            if (!$occId) continue;
            $ics_url = add_query_arg([
                'wcefp_ics' => 1,
                'order'     => $order_id,
                'item'      => $item->get_id(),
                'pid'       => $pid,
                'occ'       => $occId,
            ], home_url('/'));
            $ics[] = ['title'=> $item->get_name(), 'url'=>$ics_url];
        }
        if (!$ics) return;
        echo '<div class="wcefp-ics"><h3>'.esc_html(wcefp_safe_translate('Aggiungi al calendario')).'</h3><ul>';
        foreach ($ics as $row) {
            printf('<li><a class="button" href="%s">%s</a></li>', esc_url($row['url']), esc_html($row['title']));
        }
        echo '</ul></div>';
    }

    public function serve_ics() {
        if (!isset($_GET['wcefp_ics'])) return;
        $order_id = intval($_GET['order'] ?? 0);
        $item_id  = intval($_GET['item'] ?? 0);
        $pid      = intval($_GET['pid'] ?? 0);
        $occ_id   = intval($_GET['occ'] ?? 0);
        if (!$order_id || !$item_id || !$pid || !$occ_id) return;

        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $row = $wpdb->get_row($wpdb->prepare("SELECT start_datetime,end_datetime FROM $tbl WHERE id=%d AND product_id=%d", $occ_id, $pid));
        if (!$row) return;

        $title = get_the_title($pid);
        $loc = get_bloginfo('name');
        $desc = wp_strip_all_tags(get_post_field('post_content', $pid));
        $uid = 'wcefp-'.md5($occ_id.'-'.$pid.'-'.$order_id).'@'.parse_url(home_url(), PHP_URL_HOST);

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="event-'.$occ_id.'.ics"');

        $dtstart = gmdate('Ymd\THis\Z', strtotime($row->start_datetime));
        $dtend   = gmdate('Ymd\THis\Z', strtotime($row->end_datetime ?: $row->start_datetime.' +2 hours'));

        echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//WCEFP//ICS 1.0//EN\r\nBEGIN:VEVENT\r\nUID:$uid\r\nDTSTAMP:".gmdate('Ymd\THis\Z')."\r\nDTSTART:$dtstart\r\nDTEND:$dtend\r\nSUMMARY:".self::esc_ics($title)."\r\nDESCRIPTION:".self::esc_ics($desc)."\r\nLOCATION:".self::esc_ics($loc)."\r\nEND:VEVENT\r\nEND:VCALENDAR";
        exit;
    }
    private static function esc_ics($s){ return preg_replace('/([,;])/','\\\$1', str_replace("\n",'\\n', $s)); }

    /* ---------- Brevo ---------- */
    public function brevo_on_completed($order_id) {
        $order = wc_get_order($order_id); if(!$order) return;

        $event_item = null;
        foreach ($order->get_items() as $item) {
            $p = $item->get_product(); if(!$p) continue;
            if (in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) { $event_item = $item; break; }
        }
        if (!$event_item) return;

        $api_key = trim(get_option('wcefp_brevo_api_key',''));
        if (!$api_key) return;

        $email = $order->get_billing_email();
        $firstname = $order->get_billing_first_name();
        $product_name = $event_item->get_name();
        $occ = (string)$event_item->get_meta('Occorrenza');
        $ad  = intval($event_item->get_meta('Adulti'));
        $ch  = intval($event_item->get_meta('Bambini'));

        $locale = get_locale();
        $is_it = (stripos($locale, 'it_') === 0 || stripos($locale, 'it') === 0);
        $list_it = intval(get_option('wcefp_brevo_list_it', 0));
        $list_en = intval(get_option('wcefp_brevo_list_en', 0));
        $list_id = $is_it ? $list_it : $list_en;
        $extra_tag = trim(get_option('wcefp_brevo_tag', ''));
        $tags = ['WCEFP'];
        if ($extra_tag !== '') { $tags[] = $extra_tag; }

        // Upsert contatto
        $this->brevo_request('https://api.brevo.com/v3/contacts', 'POST', [
            'email' => $email,
            'attributes' => [
                'FIRSTNAME'   => $firstname,
                'ORDER_ID'    => (string)$order->get_order_number(),
                'TOTAL'       => (float)$order->get_total(),
                'LANG'        => $is_it ? 'IT' : 'EN',
                'ProductName' => $product_name,
                'OccDate'     => $occ,
                'Adults'      => $ad,
                'Children'    => $ch,
            ],
            'listIds' => $list_id ? [$list_id] : [],
            'updateEnabled' => true,
            'tags' => $tags,
        ], $api_key);

        // Transazionale (se Template ID)
        $tpl_id = intval(get_option('wcefp_brevo_template_id', 0));
        $from_email = sanitize_email(get_option('wcefp_brevo_from_email', get_bloginfo('admin_email')));
        $from_name  = sanitize_text_field(get_option('wcefp_brevo_from_name', get_bloginfo('name')));

        $items_html = '';
        foreach ($order->get_items() as $item) {
            $p = $item->get_product(); if(!$p || !in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;
            $occ = esc_html($item->get_meta('Occorrenza'));
            $ad  = intval($item->get_meta('Adulti'));
            $ch  = intval($item->get_meta('Bambini'));
            $items_html .= '<li><strong>'.esc_html($item->get_name()).'</strong> – Occorrenza: '.$occ.' – Adulti: '.$ad.' – Bambini: '.$ch.'</li>';
        }
        $ics_note = __('Trovi il link per aggiungere al calendario nella pagina di conferma ordine.', 'wceventsfp');

        if ($tpl_id > 0) {
            $payload = [
                'to' => [['email'=>$email, 'name'=>$firstname]],
                'templateId' => $tpl_id,
                'params' => [
                    'ORDER_NUMBER' => (string)$order->get_order_number(),
                    'ORDER_TOTAL'  => (float)$order->get_total(),
                    'ORDER_ITEMS_HTML' => $items_html,
                    'ICS_NOTE' => $ics_note,
                    'FIRSTNAME' => $firstname,
                ],
                'sender' => ['email'=>$from_email, 'name'=>$from_name],
            ];
        } else {
            $payload = [
                'to' => [['email'=>$email, 'name'=>$firstname]],
                'subject' => wcefp_safe_sprintf('Conferma prenotazione #%s', $order->get_order_number()),
                'htmlContent' => '<h2>'.esc_html(wcefp_safe_translate('Grazie per la prenotazione')).'</h2><ul>'.$items_html.'</ul><p>'.$ics_note.'</p>',
                'sender' => ['email'=>$from_email, 'name'=>$from_name],
            ];
        }
        $this->brevo_request('https://api.brevo.com/v3/smtp/email', 'POST', $payload, $api_key);
    }

    private function brevo_request($url,$method='POST',$body=[],$api_key=null) {
        $key = $api_key ?: trim(get_option('wcefp_brevo_api_key',''));
        if (!$key) return false;
        $args = [
            'headers'=>['accept'=>'application/json','api-key'=>$key,'content-type'=>'application/json'],
            'method'=>$method,'body'=>!empty($body)?wp_json_encode($body):null,'timeout'=>20,
        ];
        $res = wp_remote_request($url,$args);
        if (is_wp_error($res)) error_log('WCEFP Brevo error: '.$res->get_error_message());
        return $res;
    }

    /* ---------- Disattiva email Woo (se solo eventi) ---------- */
    public function maybe_disable_wc_mail($enabled, $order) {
        $flag = get_option('wcefp_disable_wc_emails_for_events', '0') === '1';
        if (!$flag || !$order instanceof WC_Order) return $enabled;

        $only_events = true;
        foreach ($order->get_items() as $item) {
            $p = $item->get_product(); if(!$p) continue;
            if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) { $only_events = false; break; }
        }
        return $only_events ? false : $enabled;
    }

    /* ---------- AJAX admin base ---------- */
    public function ajax_get_bookings() {
        check_ajax_referer('wcefp_admin','nonce');
        $orders = wc_get_orders(['limit'=>200,'type'=>'shop_order','status'=>['wc-processing','wc-completed','wc-on-hold'],'date_created'=>'>='. (new DateTime('-120 days'))->format('Y-m-d')]);
        $rows = [];
        foreach ($orders as $order) {
            foreach ($order->get_items() as $it) {
                $p = $it->get_product(); if(!$p) continue;
                if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;
                $rows[] = [
                    'order'   => $order->get_order_number(),
                    'status'  => wc_get_order_status_name( $order->get_status() ),
                    'date'    => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '',
                    'product' => $p->get_name(),
                    'qty'     => (int)$it->get_quantity(),
                    'total'   => (float)$order->get_item_total($it, false),
                ];
            }
        }
        wp_send_json_success(['rows'=>$rows]);
    }

    public function ajax_get_calendar() {
        check_ajax_referer('wcefp_admin','nonce');
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : (new DateTime('-7 days'))->format('Y-m-d');
        $to   = isset($_POST['to'])   ? sanitize_text_field($_POST['to'])   : (new DateTime('+60 days'))->format('Y-m-d');
        $pid  = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if ($pid) {
            $evts = $wpdb->get_results($wpdb->prepare(
                "SELECT id,product_id,start_datetime,end_datetime,capacity,booked,status FROM $tbl WHERE product_id=%d AND start_datetime BETWEEN %s AND %s ORDER BY start_datetime ASC",
                $pid, "$from 00:00:00", "$to 23:59:59"
            ), ARRAY_A);
        } else {
            $evts = $wpdb->get_results($wpdb->prepare(
                "SELECT id,product_id,start_datetime,end_datetime,capacity,booked,status FROM $tbl WHERE start_datetime BETWEEN %s AND %s ORDER BY start_datetime ASC",
                "$from 00:00:00", "$to 23:59:59"
            ), ARRAY_A);
        }

        $events = [];
        foreach ($evts as $e) {
            $events[] = [
                'id'    => (int)$e['id'],
                'title' => get_the_title((int)$e['product_id'])." (".intval($e['booked'])."/".intval($e['capacity']).")",
                'start' => $e['start_datetime'],
                'end'   => $e['end_datetime'] ?: null,
                'color' => ($e['status'] === 'cancelled') ? '#d1d5db' : '',
                'extendedProps' => [
                    'product_id' => (int)$e['product_id'],
                    'capacity'   => (int)$e['capacity'],
                    'booked'     => (int)$e['booked'],
                    'status'     => $e['status'],
                ],
            ];
        }
        wp_send_json_success(['events'=>$events]);
    }

    public function ajax_update_occurrence() {
        check_ajax_referer('wcefp_admin','nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error(['msg'=>'No perms']);
        $occ = intval($_POST['occ'] ?? 0);
        $cap = isset($_POST['capacity']) ? max(0, intval($_POST['capacity'])) : null;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
        if (!$occ) wp_send_json_error(['msg'=>'ID mancante']);

        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $data = []; $fmt = [];
        if ($cap !== null) { $data['capacity'] = $cap; $fmt[] = '%d'; }
        if ($status !== null && in_array($status, ['active','cancelled'], true)) { $data['status'] = $status; $fmt[] = '%s'; }
        if (!$data) wp_send_json_error(['msg'=>'Nessun dato da aggiornare']);

        $res = $wpdb->update($tbl, $data, ['id'=>$occ], $fmt, ['%d']);
        if ($res === false) wp_send_json_error(['msg'=>'Errore aggiornamento']);
        wp_send_json_success(['ok'=>true]);
    }

    /* ---------- Export CSV ---------- */
    public function export_occurrences_csv() {
        if (!current_user_can('manage_woocommerce') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'wcefp_export')) wp_die('Not allowed');
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        $rows = $wpdb->get_results("SELECT * FROM $tbl ORDER BY start_datetime DESC", ARRAY_A);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=wcefp_occurrences.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','product_id','product_name','start','end','capacity','booked','available','status']);
        foreach ($rows as $r) {
            $avail = max(0, intval($r['capacity']) - intval($r['booked']));
            fputcsv($out, [
                $r['id'], $r['product_id'], get_the_title((int)$r['product_id']),
                $r['start_datetime'], $r['end_datetime'], $r['capacity'], $r['booked'], $avail, $r['status']
            ]);
        }
        fclose($out); exit;
    }

    public function export_bookings_csv() {
        if (!current_user_can('manage_woocommerce') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'wcefp_export')) wp_die('Not allowed');
        $orders = wc_get_orders(['limit'=>-1,'type'=>'shop_order','status'=>array_keys(wc_get_order_statuses()),'date_created'=>'>='. (new DateTime('-365 days'))->format('Y-m-d')]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=wcefp_bookings.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['order_number','status','date','customer','email','product','occurrence_id','adults','children','extras','line_total']);
        foreach ($orders as $order) {
            foreach ($order->get_items() as $it) {
                $p = $it->get_product(); if(!$p) continue;
                if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;
                $extras = $it->get_meta('Extra');
                fputcsv($out, [
                    $order->get_order_number(),
                    wc_get_order_status_name( $order->get_status() ),
                    $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '',
                    trim($order->get_billing_first_name().' '.$order->get_billing_last_name()),
                    $order->get_billing_email(),
                    $p->get_name(),
                    $it->get_meta('Occorrenza'),
                    intval($it->get_meta('Adulti')),
                    intval($it->get_meta('Bambini')),
                    is_string($extras) ? $extras : '',
                    number_format($order->get_item_total($it, false), 2, '.', ''),
                ]);
            }
        }
        fclose($out); exit;
    }

    /* ---------- Allocazione / Rilascio posti ---------- */
    public function allocate_seats_on_status($order_id){
        $order = wc_get_order($order_id); if(!$order) return;
        $todo = WCEFP_OrderSeatOps::get_items_to_alloc($order);
        foreach ($todo as $row){
            $ok = wcefp_update_booked_atomic($row['occ'], $row['qty']);
            if ($ok) {
                $order->add_order_note("Posti allocati per \"{$row['name']}\" ( +{$row['qty']} ).");
            } else {
                $order->add_order_note("ATTENZIONE: capienza insufficiente per \"{$row['name']}\". Verificare.");
                $order->update_status('on-hold');
            }
        }
    }
    public function release_seats_on_status($order_id){
        $order = wc_get_order($order_id); if(!$order) return;
        $todo = WCEFP_OrderSeatOps::get_items_to_alloc($order);
        foreach ($todo as $row){
            $ok = wcefp_update_booked_atomic($row['occ'], -$row['qty']);
            if ($ok) {
                $order->add_order_note("Posti rilasciati per \"{$row['name']}\" ( -{$row['qty']} ).");
            }
        }
    }

    public function allocate_extras_on_status($order_id){
        $order = wc_get_order($order_id); if(!$order) return;
        $todo = WCEFP_OrderExtraOps::get_items_to_alloc($order);
        foreach ($todo as $row){
            $ok = wcefp_adjust_extra_stock_atomic($row['product'], $row['extra'], -$row['qty']);
            if ($ok) {
                $order->add_order_note("Extra allocato per \"{$row['name']}\" ( -{$row['qty']} ).");
            } else {
                $order->add_order_note("ATTENZIONE: stock insufficiente per extra \"{$row['name']}\".");
                $order->update_status('on-hold');
            }
        }
    }
    public function release_extras_on_status($order_id){
        $order = wc_get_order($order_id); if(!$order) return;
        $todo = WCEFP_OrderExtraOps::get_items_to_alloc($order);
        foreach ($todo as $row){
            $ok = wcefp_adjust_extra_stock_atomic($row['product'], $row['extra'], $row['qty']);
            if ($ok) {
                $order->add_order_note("Extra rilasciato per \"{$row['name']}\" ( +{$row['qty']} ).");
            }
        }
    }

    /* ---------- Advanced Analytics Tracking ---------- */
    public function ajax_track_analytics() {
        // Verify nonce for security
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'wcefp_public')) {
            wp_die('Accesso negato.');
        }

        $event_data = json_decode(sanitize_textarea_field(wp_unslash($_POST['event_data'] ?? '{}')), true);
        
        if (!$event_data || !isset($event_data['event'])) {
            wp_send_json_error('Dati evento non validi.');
            return;
        }

        // Store analytics event in custom table for advanced reporting
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_analytics';
        
        // Create analytics table if it doesn't exist
        $this->maybe_create_analytics_table();

        // Store the event
        $result = $wpdb->insert(
            $table_name,
            [
                'event_name' => sanitize_text_field($event_data['event']),
                'event_data' => wp_json_encode($event_data),
                'session_id' => sanitize_text_field($event_data['session_id'] ?? ''),
                'user_id' => sanitize_text_field($event_data['user_id'] ?? ''),
                'product_id' => isset($event_data['product_id']) ? absint($event_data['product_id']) : null,
                'page_url' => esc_url_raw($event_data['page_url'] ?? ''),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'ip_address' => sanitize_text_field($this->get_client_ip()),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        if ($result !== false) {
            // Trigger advanced processing for specific events
            $this->process_advanced_analytics($event_data);
            wp_send_json_success('Evento tracciato con successo.');
        } else {
            wp_send_json_error('Errore nel salvataggio dell\'evento.');
        }
    }

    private function maybe_create_analytics_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_analytics';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_name VARCHAR(100) NOT NULL,
            event_data LONGTEXT NULL,
            session_id VARCHAR(100) NULL,
            user_id VARCHAR(100) NULL,
            product_id BIGINT UNSIGNED NULL,
            page_url TEXT NULL,
            user_agent TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_name (event_name),
            INDEX idx_session_id (session_id),
            INDEX idx_user_id (user_id),
            INDEX idx_product_id (product_id),
            INDEX idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function process_advanced_analytics($event_data) {
        $event_name = $event_data['event'];
        
        // Advanced processing for specific events
        switch ($event_name) {
            case 'core_web_vital':
                $this->process_performance_metric($event_data);
                break;
                
            case 'booking_attempt':
                $this->process_conversion_event($event_data);
                break;
                
            case 'session_end':
                $this->process_session_completion($event_data);
                break;
        }
    }

    private function process_performance_metric($event_data) {
        // Store performance metrics for dashboard
        $metric = $event_data['metric'] ?? '';
        $value = $event_data['value'] ?? 0;
        $rating = $event_data['rating'] ?? 'unknown';
        
        // Could be used to trigger alerts for poor performance
        if ($rating === 'poor') {
            // Log performance issue
            error_log("WCEFP Performance Alert: {$metric} = {$value}ms (rating: {$rating})");
        }
    }

    private function process_conversion_event($event_data) {
        // Advanced conversion tracking
        $product_id = $event_data['product_id'] ?? 0;
        $funnel_completion = $event_data['funnel_completion'] ?? 0;
        
        // Store conversion funnel data for optimization
        update_option("wcefp_funnel_data_{$product_id}", [
            'last_attempt' => current_time('mysql'),
            'completion_rate' => $funnel_completion,
            'session_id' => $event_data['session_id'] ?? ''
        ]);
    }

    private function process_session_completion($event_data) {
        // Process session completion for user journey analysis
        $session_duration = $event_data['session_duration'] ?? 0;
        $funnel_completion = $event_data['funnel_completion'] ?? 0;
        $final_step = $event_data['final_step'] ?? '';
        
        // Store session insights
        $session_data = [
            'duration' => $session_duration,
            'completion' => $funnel_completion,
            'final_step' => $final_step,
            'timestamp' => current_time('mysql')
        ];
        
        $sessions = get_option('wcefp_session_insights', []);
        $sessions[] = $session_data;
        
        // Keep only last 1000 sessions
        if (count($sessions) > 1000) {
            $sessions = array_slice($sessions, -1000);
        }
        
        update_option('wcefp_session_insights', $sessions);
    }

    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER[$key])));
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    }
}

/* ---------- Helper allocazione ---------- */
if (!function_exists('wcefp_update_booked_atomic')) {
    function wcefp_update_booked_atomic($occ_id, $delta){
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_occurrences';
        if ($delta > 0) {
            $sql = $wpdb->prepare("UPDATE $tbl SET booked = booked + %d WHERE id=%d AND status='active' AND (capacity - booked) >= %d", $delta, $occ_id, $delta);
        } else {
            $sql = $wpdb->prepare("UPDATE $tbl SET booked = GREATEST(0, booked + %d) WHERE id=%d", $delta, $occ_id);
        }
        $res = $wpdb->query($sql);
        return ($res && intval($res) > 0);
    }
}

if (!function_exists('wcefp_adjust_extra_stock_atomic')) {
    function wcefp_adjust_extra_stock_atomic($product_id, $extra_id, $delta){
        global $wpdb; $tbl = $wpdb->prefix.'wcefp_product_extras';
        if ($delta < 0) {
            $delta = absint($delta);
            $sql = $wpdb->prepare("UPDATE $tbl SET stock = stock - %d WHERE product_id=%d AND extra_id=%d AND stock >= %d AND stock > 0", $delta, $product_id, $extra_id, $delta);
        } else {
            $delta = absint($delta);
            $sql = $wpdb->prepare("UPDATE $tbl SET stock = stock + %d WHERE product_id=%d AND extra_id=%d AND stock > 0", $delta, $product_id, $extra_id);
        }
        $res = $wpdb->query($sql);
        return ($res && intval($res) > 0);
    }
}

class WCEFP_OrderSeatOps {
    public static function get_items_to_alloc($order){
        $rows = [];
        foreach ($order->get_items() as $it) {
            $p = $it->get_product(); if(!$p) continue;
            if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;
            $occId = intval($it->get_meta('Occorrenza'));
            $ad = intval($it->get_meta('Adulti'));
            $ch = intval($it->get_meta('Bambini'));
            $qty = max(0, $ad + $ch);
            if ($occId && $qty>0) $rows[] = ['occ'=>$occId,'qty'=>$qty,'name'=>$p->get_name()];
        }
        return $rows;
    }
}

class WCEFP_OrderExtraOps {
    public static function get_items_to_alloc($order){
        $rows = [];
        foreach ($order->get_items() as $it){
            $p = $it->get_product(); if(!$p) continue;
            if (!in_array($p->get_type(), ['wcefp_event','wcefp_experience'], true)) continue;
            $ad = intval($it->get_meta('Adulti'));
            $ch = intval($it->get_meta('Bambini'));
            $json = $it->get_meta('_wcefp_extras_data');
            if(!$json) continue;
            $extras = json_decode($json, true);
            if(!is_array($extras)) continue;
            foreach ($extras as $ex){
                $id = intval($ex['id'] ?? 0);
                $qty = intval($ex['qty'] ?? 0);
                $pricing = $ex['pricing'] ?? 'per_order';
                if($id && $qty>0){
                    $mult = $qty;
                    if($pricing === 'per_person') $mult *= ($ad + $ch);
                    elseif($pricing === 'per_child') $mult *= $ch;
                    elseif($pricing === 'per_adult') $mult *= $ad;
                    $rows[] = ['product'=>$p->get_id(),'extra'=>$id,'qty'=>$mult,'name'=>$ex['name'] ?? 'extra'];
                }
            }
        }
        return $rows;
    }
}

/**
 * Return weekday labels indexed by PHP's date('w') value.
 *
 * @return array<int,string>
 */
function wcefp_get_weekday_labels() {
    return [
        1 => __('Lunedì', 'wceventsfp'),
        2 => __('Martedì', 'wceventsfp'),
        3 => __('Mercoledì', 'wceventsfp'),
        4 => __('Giovedì', 'wceventsfp'),
        5 => __('Venerdì', 'wceventsfp'),
        6 => __('Sabato', 'wceventsfp'),
        0 => __('Domenica', 'wceventsfp'),
    ];
}

/* ---- Meta box: Giorni disponibili ---- */

/**
 * Register meta box for available weekdays.
 *
 * @param string   $post_type Current post type.
 * @param WP_Post  $post      Current post object.
 */
function wcefp_add_days_metabox($post_type, $post){
    if ($post_type !== 'product') return;
    $product = wc_get_product($post->ID);
    if (!$product || !in_array($product->get_type(), ['wcefp_event','wcefp_experience'], true)) return;

    add_meta_box(
        'wcefp_days_meta',
        __('Giorni disponibili', 'wceventsfp'),
        'wcefp_render_days_metabox',
        'product',
        'side',
        'default'
    );
}

/**
 * Render the weekdays checkboxes.
 *
 * @param WP_Post $post Current post.
 */
function wcefp_render_days_metabox($post){
    $saved = (array) get_post_meta($post->ID, '_wcefp_days', true);
    $days = [
        'mon' => __('Lunedì', 'wceventsfp'),
        'tue' => __('Martedì', 'wceventsfp'),
        'wed' => __('Mercoledì', 'wceventsfp'),
        'thu' => __('Giovedì', 'wceventsfp'),
        'fri' => __('Venerdì', 'wceventsfp'),
        'sat' => __('Sabato', 'wceventsfp'),
        'sun' => __('Domenica', 'wceventsfp'),
    ];
    echo '<div class="wcefp-weekdays-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;">';
    foreach ($days as $key => $label) {
        printf(
            '<label style="display:flex;align-items:center;gap:2px;"><input type="checkbox" name="wcefp_days[]" value="%s" %s /> %s</label>',
            esc_attr($key),
            checked(in_array($key, $saved, true), true, false),
            esc_html($label)
        );
    }
    echo '</div>';
    wp_nonce_field('wcefp_save_days', 'wcefp_days_nonce');
}

/**
 * Save selected weekdays.
 *
 * @param int $post_id Product ID.
 */
function wcefp_save_days_metabox($post_id){
    if (!isset($_POST['wcefp_days_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcefp_days_nonce'])), 'wcefp_save_days')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $days = isset($_POST['wcefp_days']) ? array_map('sanitize_text_field', (array)$_POST['wcefp_days']) : [];
    $valid = ['mon','tue','wed','thu','fri','sat','sun'];
    $days = array_values(array_intersect($valid, $days));

    if (!empty($days)) {
        update_post_meta($post_id, '_wcefp_days', $days);
    } else {
        delete_post_meta($post_id, '_wcefp_days');
    }
}

} // Close the activation hook conditional

