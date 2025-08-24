<?php
/**
 * Frontend Service Provider
 * 
 * @package WCEFP
 * @subpackage Frontend
 * @since 2.1.1
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
        
        // Register booking widget
        $this->container->singleton('frontend.booking_widget', function($container) {
            return new BookingWidget($container);
        });
        
        // Register shortcode manager
        $this->container->singleton('frontend.shortcodes', function($container) {
            return new ShortcodeManager($container);
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
        
        // Initialize booking widget
        $this->container->get('frontend.booking_widget');
        
        // Initialize shortcode manager if it exists
        if (class_exists('\WCEFP\Frontend\ShortcodeManager')) {
            $this->container->get('frontend.shortcodes');
        }
    }
}