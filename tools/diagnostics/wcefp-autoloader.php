<?php
/**
 * WCEventsFP Diagnostic Autoloader Wrapper (T-02 Consolidated)
 * 
 * This file now serves as a wrapper around the canonical autoloader.
 * It provides enhanced diagnostic capabilities while using the single
 * source of truth for class loading.
 * 
 * @package WCEventsFP
 * @version 2.2.0 (T-02 Consolidated)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent multiple loads
if (defined('WCEFP_DIAGNOSTIC_AUTOLOADER_LOADED')) {
    return;
}
define('WCEFP_DIAGNOSTIC_AUTOLOADER_LOADED', true);

/**
 * Diagnostic Autoloader - wrapper around canonical autoloader
 */
class WCEFP_Diagnostic_Autoloader {
    
    private $base_dir;
    private $debug = false;
    
    public function __construct($base_dir = null) {
        $this->base_dir = $base_dir ?: (defined('WCEFP_PLUGIN_DIR') ? WCEFP_PLUGIN_DIR . 'includes/' : dirname(__DIR__, 1) . '/includes/');
        $this->debug = defined('WP_DEBUG') && WP_DEBUG;
        
        $this->ensure_canonical_autoloader();
    }
    
    private function ensure_canonical_autoloader() {
        if (!defined('WCEFP_CANONICAL_AUTOLOADER_LOADED')) {
            $canonical_path = dirname(__DIR__, 1) . '/includes/autoloader.php';
            if (file_exists($canonical_path)) {
                require_once $canonical_path;
                if ($this->debug) {
                    error_log('WCEFP Diagnostic: Loaded canonical autoloader');
                }
            }
        }
    }
    
    public function register() {
        // T-02: Don't register another autoloader, just enable diagnostics
        if (class_exists('WCEFP_Autoloader')) {
            WCEFP_Autoloader::set_debug(true);
        }
    }
    
    public function unregister() {
        if (class_exists('WCEFP_Autoloader')) {
            WCEFP_Autoloader::set_debug(false);
        }
    }
    
    public function test_class_loading($class_name) {
        $result = [
            'class' => $class_name,
            'loadable' => false,
            'file_exists' => false,
            'file_path' => '',
            'error' => ''
        ];
        
        try {
            if (class_exists($class_name, false)) {
                $result['loadable'] = true;
                $result['already_loaded'] = true;
                return $result;
            }
            
            if (strpos($class_name, 'WCEFP\\') === 0) {
                $relative_class = substr($class_name, 6);
                $file_path = str_replace('\\', '/', $relative_class) . '.php';
                $full_path = $this->base_dir . $file_path;
                $result['file_path'] = $full_path;
                $result['file_exists'] = file_exists($full_path);
            }
            
            if (class_exists($class_name)) {
                $result['loadable'] = true;
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    public function generate_report() {
        $report = "=== WCEFP Autoloader Diagnostic Report (T-02) ===\n\n";
        $report .= "Canonical Autoloader: " . (defined('WCEFP_CANONICAL_AUTOLOADER_LOADED') ? "✅ Loaded" : "❌ Not Loaded") . "\n";
        $report .= "Debug Mode: " . ($this->debug ? "✅ Enabled" : "❌ Disabled") . "\n";
        $report .= "Base Directory: " . $this->base_dir . "\n\n";
        
        $key_classes = [
            'WCEFP\\Bootstrap\\Plugin',
            'WCEFP\\Core\\Container', 
            'WCEFP\\Core\\SecurityManager',
            'WCEFP\\Admin\\MenuManager'
        ];
        
        $report .= "Key Class Tests:\n";
        foreach ($key_classes as $class) {
            $test = $this->test_class_loading($class);
            $status = $test['loadable'] ? "✅" : "❌";
            $report .= "  {$status} {$class}\n";
            if (!empty($test['error'])) {
                $report .= "     Error: {$test['error']}\n";
            }
        }
        
        return $report;
    }
}

/**
 * Initialize diagnostic autoloader wrapper
 */
function wcefp_init_diagnostic_autoloader() {
    return new WCEFP_Diagnostic_Autoloader();
}

// Auto-initialize for backward compatibility
if (!defined('WCEFP_SKIP_AUTO_INIT')) {
    wcefp_init_diagnostic_autoloader();
}