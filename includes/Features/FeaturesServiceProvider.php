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
        
        // Register Phase 2: Communication & Automation features
        $this->register_communication_services();
        
        // Register Phase 3: Data & Integration features
        $this->register_data_integration_services();
    }
    
    /**
     * Register Phase 2 communication services
     */
    private function register_communication_services() {
        // Load communication classes
        require_once __DIR__ . '/Communication/EmailManager.php';
        require_once __DIR__ . '/Communication/VoucherManager.php';
        require_once __DIR__ . '/Communication/AutomationManager.php';
        require_once __DIR__ . '/Communication/CommunicationServiceProvider.php';
        
        // Register Communication Service Provider
        $communication_provider = new \WCEFP\Features\Communication\CommunicationServiceProvider($this->container);
        $communication_provider->register();
        
        // Store provider reference for booting
        $this->container->singleton('communication_provider', function() use ($communication_provider) {
            return $communication_provider;
        });
    }
    
    /**
     * Register Phase 3 data integration services
     */
    private function register_data_integration_services() {
        // Load data integration classes
        require_once __DIR__ . '/DataIntegration/ExportManager.php';
        require_once __DIR__ . '/DataIntegration/GutenbergManager.php';
        require_once __DIR__ . '/DataIntegration/DataIntegrationServiceProvider.php';
        
        // Register Data Integration Service Provider
        $data_integration_provider = new \WCEFP\Features\DataIntegration\DataIntegrationServiceProvider($this->container);
        $data_integration_provider->register();
        
        // Store provider reference for booting
        $this->container->singleton('data_integration_provider', function() use ($data_integration_provider) {
            return $data_integration_provider;
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
        
        // Boot Phase 2: Communication & Automation features
        $this->boot_communication_services();
        
        // Boot Phase 3: Data & Integration features
        $this->boot_data_integration_services();
    }
    
    /**
     * Boot Phase 2 communication services
     */
    private function boot_communication_services() {
        if ($this->container->has('communication_provider')) {
            $communication_provider = $this->container->get('communication_provider');
            $communication_provider->boot();
        }
    }
    
    /**
     * Boot Phase 3 data integration services
     */
    private function boot_data_integration_services() {
        if ($this->container->has('data_integration_provider')) {
            $data_integration_provider = $this->container->get('data_integration_provider');
            $data_integration_provider->boot();
        }
    }
}