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
        
        // Register booking widget v2 (enhanced version)
        $this->container->singleton('frontend.booking_widget_v2', function($container) {
            return new BookingWidgetV2($container);
        });
        
        // Register WooCommerce archive filter
        $this->container->singleton('frontend.archive_filter', function($container) {
            return new WooCommerceArchiveFilter();
        });
        
        // Register enhanced Google Reviews manager
        $this->container->singleton('frontend.google_reviews', function($container) {
            return new GoogleReviewsManager();
        });
        
        // Register trust nudges manager
        $this->container->singleton('frontend.trust_nudges', function($container) {
            return new TrustNudgesManager();
        });
        
        // Register experience archive manager
        $this->container->singleton('frontend.experience_archive', function($container) {
            return new ExperienceArchiveManager();
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
        
        // Initialize booking widget v2 (enhanced version)
        $this->container->get('frontend.booking_widget_v2');
        
        // Initialize archive filter
        $this->container->get('frontend.archive_filter');
        
        // Initialize Google Reviews manager
        $this->container->get('frontend.google_reviews');
        
        // Initialize trust nudges manager
        $this->container->get('frontend.trust_nudges');
        
        // Initialize experience archive manager
        $this->container->get('frontend.experience_archive');
        
        // Initialize shortcode manager if it exists
        if (class_exists('\WCEFP\Frontend\ShortcodeManager')) {
            $this->container->get('frontend.shortcodes');
        }
    }
}