<?php
/**
 * Canonical PSR-4 Autoloader for WCEventsFP
 * 
 * Enhanced autoloader with error handling, debug mode, and duplicate prevention.
 * This is the single source of truth for WCEventsFP class loading (T-02).
 * 
 * @package WCEFP
 * @since 2.1.1
 * @version 2.2.0 (T-02 Enhanced)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent multiple autoloader registrations (T-02 consolidation)
if (defined('WCEFP_CANONICAL_AUTOLOADER_LOADED')) {
    return;
}
define('WCEFP_CANONICAL_AUTOLOADER_LOADED', true);

/**
 * Canonical autoloader for WCEventsFP classes
 * 
 * Handles both modern PSR-4 namespaced classes and legacy classes.
 * Enhanced with error handling and debug capabilities.
 */
class WCEFP_Autoloader {
    
    /**
     * Namespace to directory mapping
     * 
     * @var array
     */
    private static $namespace_map = [
        'WCEFP\\' => 'includes/'
    ];
    
    /**
     * Legacy class name to file mapping
     * 
     * @var array
     */
    private static $legacy_classes = [
        'WCEFP_Logger' => 'includes/Legacy/class-wcefp-logger.php',
        'WCEFP_Cache' => 'includes/Legacy/class-wcefp-cache.php',
        'WCEFP_Enhanced_Features' => 'includes/Legacy/class-wcefp-enhanced-features.php',
        'WCEFP_Product_Types' => 'includes/Legacy/class-wcefp-product-types.php',
        'WC_Product_WCEFP_Event' => 'includes/Legacy/class-wcefp-product-types.php',
        'WC_Product_WCEFP_Experience' => 'includes/Legacy/class-wcefp-product-types.php',
        'WCEFP_Closures' => 'includes/Legacy/class-wcefp-closures.php',
        'WCEFP_Validator' => 'includes/Legacy/class-wcefp-validator.php',
        'WCEFP_MeetingPoints_CPT' => 'includes/Legacy/class-wcefp-meeting-points-cpt.php',
        'WCEFP_Extra_Services' => 'includes/Legacy/class-wcefp-extra-services.php'
    ];
    
    /**
     * Loaded classes cache (T-02 enhancement)
     * 
     * @var array
     */
    private static $loaded_classes = [];
    
    /**
     * Debug mode flag
     * 
     * @var bool|null
     */
    private static $debug = null;
    
    /**
     * Initialize the canonical autoloader (T-02)
     * 
     * @return void
     */
    public static function init() {
        // Initialize debug mode
        if (self::$debug === null) {
            self::$debug = defined('WP_DEBUG') && WP_DEBUG;
        }
        
        // Register with high priority to handle WCEFP classes first
        spl_autoload_register([__CLASS__, 'load_class'], true, true);
        
        if (self::$debug) {
            error_log('WCEFP Canonical Autoloader: Registered successfully (T-02)');
        }
    }
    
    /**
     * Load a class file (T-02 enhanced with error handling)
     * 
     * @param string $class_name Class name to load
     * @return bool True if loaded successfully, false otherwise
     */
    public static function load_class($class_name) {
        // Skip if already loaded (performance optimization)
        if (isset(self::$loaded_classes[$class_name])) {
            return self::$loaded_classes[$class_name];
        }
        
        // Input validation (T-02 enhancement)
        if (!is_string($class_name) || empty($class_name)) {
            if (self::$debug) {
                error_log("WCEFP Autoloader: Invalid class name provided: " . var_export($class_name, true));
            }
            return self::$loaded_classes[$class_name] = false;
        }
        
        // Try legacy classes first (backward compatibility)
        if (isset(self::$legacy_classes[$class_name])) {
            $file_path = WCEFP_PLUGIN_DIR . self::$legacy_classes[$class_name];
            
            if (self::load_file($file_path, $class_name)) {
                return self::$loaded_classes[$class_name] = true;
            }
            
            if (self::$debug) {
                error_log("WCEFP Autoloader: Legacy class file not found: {$file_path}");
            }
            return self::$loaded_classes[$class_name] = false;
        }
        
        // Check if this is a WCEFP namespaced class
        if (strpos($class_name, 'WCEFP\\') !== 0) {
            // Not our namespace, let other autoloaders handle it
            return false;
        }
        
        // Convert namespace to file path (PSR-4)
        $relative_class = substr($class_name, 6); // Remove 'WCEFP\' prefix
        $file_path = str_replace('\\', '/', $relative_class) . '.php';
        
        // Build full path
        $full_path = WCEFP_PLUGIN_DIR . 'includes/' . $file_path;
        
        // Load the file if it exists
        if (self::load_file($full_path, $class_name)) {
            return self::$loaded_classes[$class_name] = true;
        }
        
        if (self::$debug) {
            error_log("WCEFP Autoloader: PSR-4 class file not found: {$full_path}");
        }
        
        return self::$loaded_classes[$class_name] = false;
    }
    
    /**
     * Safely load a PHP file (T-02 enhancement)
     * 
     * @param string $file_path Path to the file
     * @param string $class_name Class name being loaded (for debugging)
     * @return bool True if loaded successfully
     */
    private static function load_file($file_path, $class_name) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        try {
            require_once $file_path;
            
            if (self::$debug) {
                error_log("WCEFP Autoloader: Successfully loaded {$class_name} from {$file_path}");
            }
            
            return true;
        } catch (Exception $e) {
            if (self::$debug) {
                error_log("WCEFP Autoloader: Error loading {$class_name} from {$file_path}: " . $e->getMessage());
            }
            return false;
        } catch (ParseError $e) {
            if (self::$debug) {
                error_log("WCEFP Autoloader: Parse error in {$class_name} at {$file_path}: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Get loaded classes (diagnostic method)
     * 
     * @return array
     */
    public static function get_loaded_classes() {
        return self::$loaded_classes;
    }
    
    /**
     * Enable/disable debug mode
     * 
     * @param bool $enable
     */
    public static function set_debug($enable) {
        self::$debug = (bool) $enable;
    }
}

// Initialize autoloader
WCEFP_Autoloader::init();