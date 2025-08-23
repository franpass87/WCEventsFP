<?php
/**
 * Frontend Service Provider
 * 
 * @package WCEFP
 * @subpackage Frontend
 * @since 2.0.1
 */

namespace WCEFP\Frontend;

use WCEFP\Core\ServiceProvider;
use WCEFP\Core\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers frontend-related services
 */
class FrontendServiceProvider extends \WCEFP\Core\ServiceProvider {
    
    /**
     * Register services in the container
     * 
     * @return void
     */
    public function register() {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        // Load stub classes
        require_once __DIR__ . '/Stubs.php';
        
        // Only register functional frontend services when implemented
        // TODO: Implement and register when ready:
        // - WidgetManager
        // - ShortcodeManager  
        // - AjaxHandler
        // - TemplateManager
    }
    
    /**
     * Boot services
     * 
     * @return void
     */
    public function boot() {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        // Initialize only functional frontend services
        // (Currently none implemented)
    }
}