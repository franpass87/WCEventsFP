<?php
/**
 * Simple PSR-4 Autoloader for WCEventsFP
 * 
 * @package WCEFP
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple autoloader for WCEventsFP classes
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
     * Initialize the autoloader
     * 
     * @return void
     */
    public static function init() {
        spl_autoload_register([__CLASS__, 'load_class']);
    }
    
    /**
     * Load a class file
     * 
     * @param string $class_name Class name to load
     * @return bool
     */
    public static function load_class($class_name) {
        // Check if this is a WCEFP class
        if (strpos($class_name, 'WCEFP\\') !== 0) {
            return false;
        }
        
        // Convert namespace to file path
        $relative_class = substr($class_name, 6); // Remove 'WCEFP\' prefix
        $file_path = str_replace('\\', '/', $relative_class) . '.php';
        
        // Build full path
        $full_path = WCEFP_PLUGIN_DIR . 'includes/' . $file_path;
        
        // Load the file if it exists
        if (file_exists($full_path)) {
            require_once $full_path;
            return true;
        }
        
        return false;
    }
}

// Initialize autoloader
WCEFP_Autoloader::init();