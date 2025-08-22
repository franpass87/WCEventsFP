<?php
/**
 * WCEventsFP Manual Autoloader
 * 
 * Bulletproof PSR-4 autoloader that works without composer.
 * Handles the WCEFP namespace and provides fallback for missing classes.
 * 
 * @package WCEventsFP
 * @version 2.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent multiple loads
if (defined('WCEFP_AUTOLOADER_LOADED')) {
    return;
}
define('WCEFP_AUTOLOADER_LOADED', true);

/**
 * WCEFP Manual Autoloader Class
 */
class WCEFP_Autoloader {
    
    /**
     * Namespace prefix
     * @var string
     */
    private $namespace_prefix = 'WCEFP\\';
    
    /**
     * Base directory for namespace files
     * @var string
     */
    private $base_dir;
    
    /**
     * Class map for direct file mappings (fallback)
     * @var array
     */
    private $class_map = [];
    
    /**
     * Loaded classes cache
     * @var array
     */
    private $loaded_classes = [];
    
    /**
     * Debug mode
     * @var bool
     */
    private $debug = false;
    
    /**
     * Constructor
     * 
     * @param string $base_dir Base directory for the namespace
     */
    public function __construct($base_dir = null) {
        $this->base_dir = $base_dir ?: (defined('WCEFP_PLUGIN_DIR') ? WCEFP_PLUGIN_DIR . 'includes/' : dirname(__FILE__) . '/includes/');
        $this->debug = defined('WP_DEBUG') && WP_DEBUG;
        
        $this->init_class_map();
        $this->register();
    }
    
    /**
     * Register the autoloader
     */
    public function register() {
        spl_autoload_register([$this, 'load_class'], true, true);
        
        if ($this->debug) {
            error_log('WCEFP Autoloader: Registered successfully');
        }
    }
    
    /**
     * Unregister the autoloader
     */
    public function unregister() {
        spl_autoload_unregister([$this, 'load_class']);
    }
    
    /**
     * Load a class file
     * 
     * @param string $class_name The fully-qualified class name
     * @return bool True if loaded, false otherwise
     */
    public function load_class($class_name) {
        // Skip if already loaded
        if (isset($this->loaded_classes[$class_name])) {
            return $this->loaded_classes[$class_name];
        }
        
        // Only handle our namespace
        if (strpos($class_name, $this->namespace_prefix) !== 0) {
            return false;
        }
        
        // Try PSR-4 loading first
        $result = $this->load_psr4_class($class_name);
        
        // Fallback to class map
        if (!$result) {
            $result = $this->load_from_class_map($class_name);
        }
        
        // Cache result
        $this->loaded_classes[$class_name] = $result;
        
        if ($this->debug) {
            error_log(sprintf('WCEFP Autoloader: %s loading %s', 
                $result ? 'Successfully loaded' : 'Failed to load', 
                $class_name
            ));
        }
        
        return $result;
    }
    
    /**
     * Load class using PSR-4 standard
     * 
     * @param string $class_name
     * @return bool
     */
    private function load_psr4_class($class_name) {
        // Remove the namespace prefix
        $relative_class = substr($class_name, strlen($this->namespace_prefix));
        
        // Convert namespace separators to directory separators
        $file_path = str_replace('\\', '/', $relative_class) . '.php';
        
        // Build the full file path
        $full_path = $this->base_dir . $file_path;
        
        // Check if file exists and load it
        if (file_exists($full_path) && is_readable($full_path)) {
            try {
                require_once $full_path;
                return class_exists($class_name) || interface_exists($class_name) || trait_exists($class_name);
            } catch (Exception $e) {
                if ($this->debug) {
                    error_log('WCEFP Autoloader: Error loading ' . $full_path . ' - ' . $e->getMessage());
                }
                return false;
            } catch (Error $e) {
                if ($this->debug) {
                    error_log('WCEFP Autoloader: Fatal error loading ' . $full_path . ' - ' . $e->getMessage());
                }
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Load class from class map (fallback)
     * 
     * @param string $class_name
     * @return bool
     */
    private function load_from_class_map($class_name) {
        if (!isset($this->class_map[$class_name])) {
            return false;
        }
        
        $file_path = $this->base_dir . $this->class_map[$class_name];
        
        if (file_exists($file_path) && is_readable($file_path)) {
            try {
                require_once $file_path;
                return class_exists($class_name) || interface_exists($class_name) || trait_exists($class_name);
            } catch (Exception $e) {
                if ($this->debug) {
                    error_log('WCEFP Autoloader: Error loading from class map ' . $file_path . ' - ' . $e->getMessage());
                }
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Initialize class map for direct mappings (fallback system)
     */
    private function init_class_map() {
        $this->class_map = [
            // Core classes
            'WCEFP\\Core\\Container' => 'Core/Container.php',
            'WCEFP\\Core\\ServiceProvider' => 'Core/ServiceProvider.php',
            'WCEFP\\Core\\InstallationManager' => 'Core/InstallationManager.php',
            'WCEFP\\Core\\ActivationHandler' => 'Core/ActivationHandler.php',
            
            // Bootstrap classes
            'WCEFP\\Bootstrap\\Plugin' => 'Bootstrap/Plugin.php',
            
            // Utils classes
            'WCEFP\\Utils\\Logger' => 'Utils/Logger.php',
            
            // Admin classes - support both naming conventions
            'WCEFP\\Admin\\ServiceProvider' => 'Admin/ServiceProvider.php',
            'WCEFP\\Admin\\AdminServiceProvider' => 'Admin/ServiceProvider.php',
            'WCEFP\\Admin\\MenuManager' => 'Admin/MenuManager.php',
            'WCEFP\\Admin\\ProductAdmin' => 'Admin/ProductAdmin.php',
            'WCEFP\\Admin\\FeatureManager' => 'Admin/FeatureManager.php',
            
            // Frontend classes - support both naming conventions
            'WCEFP\\Frontend\\ServiceProvider' => 'Frontend/ServiceProvider.php',
            'WCEFP\\Frontend\\FrontendServiceProvider' => 'Frontend/ServiceProvider.php',
            
            // Features classes - support both naming conventions
            'WCEFP\\Features\\ServiceProvider' => 'Features/ServiceProvider.php',
            'WCEFP\\Features\\FeaturesServiceProvider' => 'Features/ServiceProvider.php',
            
            // Database classes - support both naming conventions
            'WCEFP\\Core\\Database\\ServiceProvider' => 'Core/Database/ServiceProvider.php',
            'WCEFP\\Core\\Database\\DatabaseServiceProvider' => 'Core/Database/ServiceProvider.php',
            'WCEFP\\Core\\Database\\Models' => 'Core/Database/Models.php',
            'WCEFP\\Core\\Database\\QueryBuilder' => 'Core/Database/QueryBuilder.php',
            
            // Asset management
            'WCEFP\\Core\\Assets\\AssetManager' => 'Core/Assets/AssetManager.php',
        ];
        
        // Add any additional classes found in the directory
        $this->scan_for_additional_classes();
    }
    
    /**
     * Scan directories for additional PSR-4 classes
     */
    private function scan_for_additional_classes() {
        $directories = [
            'Admin',
            'Bootstrap', 
            'Core',
            'Frontend',
            'Features',
            'Utils'
        ];
        
        foreach ($directories as $dir) {
            $full_dir = $this->base_dir . $dir;
            if (is_dir($full_dir)) {
                $this->scan_directory($dir, $full_dir);
            }
        }
    }
    
    /**
     * Recursively scan a directory for PHP classes
     * 
     * @param string $namespace_part
     * @param string $directory
     */
    private function scan_directory($namespace_part, $directory) {
        $files = glob($directory . '/*.php');
        foreach ($files as $file) {
            $basename = basename($file, '.php');
            $class_name = 'WCEFP\\' . str_replace('/', '\\', $namespace_part) . '\\' . $basename;
            $relative_path = $namespace_part . '/' . basename($file);
            
            if (!isset($this->class_map[$class_name])) {
                $this->class_map[$class_name] = $relative_path;
            }
        }
        
        // Scan subdirectories
        $subdirs = glob($directory . '/*', GLOB_ONLYDIR);
        foreach ($subdirs as $subdir) {
            $subdir_name = basename($subdir);
            $new_namespace_part = $namespace_part . '/' . $subdir_name;
            $this->scan_directory($new_namespace_part, $subdir);
        }
    }
    
    /**
     * Get list of discoverable classes
     * 
     * @return array
     */
    public function get_discovered_classes() {
        return array_keys($this->class_map);
    }
    
    /**
     * Check if a class can be loaded
     * 
     * @param string $class_name
     * @return bool
     */
    public function can_load_class($class_name) {
        if (isset($this->loaded_classes[$class_name])) {
            return $this->loaded_classes[$class_name];
        }
        
        // Check if we have PSR-4 path or class map entry
        if (strpos($class_name, $this->namespace_prefix) !== 0) {
            return false;
        }
        
        // PSR-4 check
        $relative_class = substr($class_name, strlen($this->namespace_prefix));
        $file_path = str_replace('\\', '/', $relative_class) . '.php';
        $full_path = $this->base_dir . $file_path;
        
        if (file_exists($full_path)) {
            return true;
        }
        
        // Class map check
        return isset($this->class_map[$class_name]) && 
               file_exists($this->base_dir . $this->class_map[$class_name]);
    }
}

/**
 * Initialize the WCEFP autoloader
 * 
 * @return WCEFP_Autoloader
 */
function wcefp_init_autoloader() {
    static $autoloader = null;
    
    if ($autoloader === null) {
        $autoloader = new WCEFP_Autoloader();
    }
    
    return $autoloader;
}

/**
 * Get the autoloader instance
 * 
 * @return WCEFP_Autoloader|null
 */
function wcefp_get_autoloader() {
    return wcefp_init_autoloader();
}

// Initialize autoloader immediately
wcefp_init_autoloader();