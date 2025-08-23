<?php
/**
 * Features Service Provider
 * 
 * @package WCEFP
 * @subpackage Features
 * @since 2.1.1
 */

namespace WCEFP\Features;

use WCEFP\Core\ServiceProvider;
use WCEFP\Core\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers feature-related services
 */
class FeaturesServiceProvider extends \WCEFP\Core\ServiceProvider {
    
    /**
     * Register services in the container
     * 
     * @return void
     */
    public function register() {
        // Load wrapper classes for legacy functionality
        require_once __DIR__ . '/Wrappers.php';
        
        // Register wrappers for existing legacy classes
        $this->container->singleton('features.vouchers', function($container) {
            return new VoucherManager();
        });
        
        $this->container->singleton('features.caching', function($container) {
            return new CacheManager();
        });
    }
    
    /**
     * Boot services
     * 
     * @return void
     */
    public function boot() {
        // Initialize cache wrapper (always available)
        if ($this->container->has('features.caching')) {
            $cache_manager = $this->container->get('features.caching');
            if ($cache_manager->is_available()) {
                // Cache manager is ready
            }
        }
        
        // Initialize voucher wrapper only if legacy class is available
        if ($this->container->has('features.vouchers')) {
            $voucher_manager = $this->container->get('features.vouchers');
            if ($voucher_manager->is_available()) {
                // Voucher manager is ready
            }
        }
    }
}