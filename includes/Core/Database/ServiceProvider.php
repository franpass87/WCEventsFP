<?php
/**
 * Database Service Provider
 * 
 * @package WCEFP
 * @subpackage Core\Database
 * @since 2.0.1
 */

namespace WCEFP\Core\Database;

use WCEFP\Core\ServiceProvider;
use WCEFP\Core\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers database-related services
 */
class DatabaseServiceProvider extends \WCEFP\Core\ServiceProvider {
    
    /**
     * Register services in the container
     * 
     * @return void
     */
    public function register() {
        // Register database models
        $this->container->singleton('db.occurrences', function($container) {
            return new Models\OccurrenceModel();
        });
        
        $this->container->singleton('db.bookings', function($container) {
            return new Models\BookingModel();
        });
        
        $this->container->singleton('db.vouchers', function($container) {
            return new Models\VoucherModel();
        });
        
        $this->container->singleton('db.analytics', function($container) {
            return new Models\AnalyticsModel();
        });
        
        // Register query builder
        $this->container->singleton('db.query_builder', function($container) {
            return new QueryBuilder();
        });
    }
    
    /**
     * Boot services
     * 
     * @return void
     */
    public function boot() {
        // Database services are lazy-loaded when needed
    }
}