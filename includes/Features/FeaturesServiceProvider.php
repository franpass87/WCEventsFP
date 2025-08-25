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
        
        // Register Phase 4: API & Developer Experience features
        $this->register_api_developer_experience_services();
        
        // Register Phase 1 Overhaul: Visibility & Gating features
        $this->register_visibility_services();
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
     * Register Phase 4 API & Developer Experience services
     */
    private function register_api_developer_experience_services() {
        // Load API & Developer Experience classes
        require_once __DIR__ . '/ApiDeveloperExperience/EnhancedRestApiManager.php';
        require_once __DIR__ . '/ApiDeveloperExperience/RoleManager.php';
        require_once __DIR__ . '/ApiDeveloperExperience/RateLimiter.php';
        require_once __DIR__ . '/ApiDeveloperExperience/DocumentationManager.php';
        require_once __DIR__ . '/ApiDeveloperExperience/DeveloperTools.php';
        require_once __DIR__ . '/ApiDeveloperExperience/ApiDeveloperExperienceServiceProvider.php';
        
        // Register API & Developer Experience Service Provider
        $api_dev_provider = new \WCEFP\Features\ApiDeveloperExperience\ApiDeveloperExperienceServiceProvider($this->container);
        $api_dev_provider->register();
        
        // Store provider reference for booting
        $this->container->singleton('api_dev_provider', function() use ($api_dev_provider) {
            return $api_dev_provider;
        });
    }
    
    /**
     * Register Phase 1 Overhaul: Visibility & Gating features
     */
    private function register_visibility_services() {
        // Load visibility classes
        require_once __DIR__ . '/Visibility/ExperienceGating.php';
        
        // Register Experience Gating service
        $this->container->singleton('features.experience_gating', function() {
            return new \WCEFP\Features\Visibility\ExperienceGating();
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
        
        // Boot Phase 4: API & Developer Experience features
        $this->boot_api_developer_experience_services();
        
        // Boot Phase 1 Overhaul: Visibility & Gating features
        $this->boot_visibility_services();
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
    
    /**
     * Boot Phase 4 API & Developer Experience services
     */
    private function boot_api_developer_experience_services() {
        if ($this->container->has('api_dev_provider')) {
            $api_dev_provider = $this->container->get('api_dev_provider');
            $api_dev_provider->boot();
        }
    }
    
    /**
     * Boot Phase 1 Overhaul: Visibility & Gating services
     */
    private function boot_visibility_services() {
        // Initialize Experience Gating feature
        \WCEFP\Features\Visibility\ExperienceGating::init();
    }
}