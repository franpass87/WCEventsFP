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
        
        // Register frontend classes with the container
        $this->container->singleton('frontend.widgets', function($container) {
            return new WidgetManager($container);
        });
        
        $this->container->singleton('frontend.shortcodes', function($container) {
            return new ShortcodeManager($container);
        });
        
        $this->container->singleton('frontend.ajax', function($container) {
            return new AjaxHandler($container);
        });
        
        $this->container->singleton('frontend.templates', function($container) {
            return new TemplateManager($container);
        });
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
        
        // Initialize frontend services
        $this->container->get('frontend.widgets');
        $this->container->get('frontend.shortcodes');
        $this->container->get('frontend.ajax');
        $this->container->get('frontend.templates');
    }
}