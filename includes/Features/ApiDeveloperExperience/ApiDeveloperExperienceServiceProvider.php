<?php
/**
 * API & Developer Experience Service Provider
 * 
 * Implements Phase 4 of the WCEventsFP Feature Pack:
 * - Enhanced REST API with rate limiting and OpenAPI documentation
 * - Role-Based Access Control with custom event_manager role
 * - Developer tools and improved API experience
 *
 * @package WCEFP\Features\ApiDeveloperExperience
 * @since 2.2.0
 */

namespace WCEFP\Features\ApiDeveloperExperience;

use WCEFP\Core\ServiceProvider;
use WCEFP\Core\Container;
use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API & Developer Experience Service Provider
 */
class ApiDeveloperExperienceServiceProvider extends ServiceProvider {
    
    /**
     * Register services
     */
    public function register() {
        // Register enhanced API manager
        $this->container->singleton('api.enhanced_manager', function($container) {
            return new EnhancedRestApiManager();
        });
        
        // Register role manager
        $this->container->singleton('api.role_manager', function($container) {
            return new RoleManager();
        });
        
        // Register rate limiter
        $this->container->singleton('api.rate_limiter', function($container) {
            return new RateLimiter();
        });
        
        // Register documentation generator
        $this->container->singleton('api.documentation', function($container) {
            return new DocumentationManager();
        });
        
        // Register developer tools
        $this->container->singleton('api.developer_tools', function($container) {
            return new DeveloperTools();
        });
    }
    
    /**
     * Boot services
     */
    public function boot() {
        // Initialize role manager first
        if ($this->container->has('api.role_manager')) {
            $role_manager = $this->container->get('api.role_manager');
            $role_manager->init();
        }
        
        // Initialize enhanced API manager
        if ($this->container->has('api.enhanced_manager')) {
            $enhanced_api = $this->container->get('api.enhanced_manager');
            $enhanced_api->init();
        }
        
        // Initialize rate limiter
        if ($this->container->has('api.rate_limiter')) {
            $rate_limiter = $this->container->get('api.rate_limiter');
            $rate_limiter->init();
        }
        
        // Initialize documentation
        if ($this->container->has('api.documentation')) {
            $documentation = $this->container->get('api.documentation');
            $documentation->init();
        }
        
        // Initialize developer tools
        if ($this->container->has('api.developer_tools')) {
            $developer_tools = $this->container->get('api.developer_tools');
            $developer_tools->init();
        }
        
        // Hook into WordPress
        add_action('init', [$this, 'init_phase_4_features']);
        add_action('wp_loaded', [$this, 'check_role_upgrades']);
        
        DiagnosticLogger::instance()->debug('Phase 4: API & Developer Experience initialized', [], 'api_features');
    }
    
    /**
     * Initialize Phase 4 features
     */
    public function init_phase_4_features() {
        // Add admin notices for successful initialization
        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_notices', function() {
                $dismissed = get_user_meta(get_current_user_id(), 'wcefp_phase4_notice_dismissed', true);
                
                if (!$dismissed && isset($_GET['page']) && strpos($_GET['page'], 'wcefp') !== false) {
                    echo '<div class="notice notice-success is-dismissible" data-dismiss-key="phase4">
                        <p><strong>WCEventsFP Phase 4:</strong> ' . 
                        __('API & Developer Experience features are now active! Enhanced REST API, role management, and developer tools are available.', 'wceventsfp') . 
                        '</p>
                    </div>';
                }
            });
        }
        
        // Register AJAX handlers for dismissing notices
        add_action('wp_ajax_wcefp_dismiss_phase4_notice', [$this, 'dismiss_phase4_notice']);
    }
    
    /**
     * Check for role upgrades
     */
    public function check_role_upgrades() {
        $role_manager = $this->container->get('api.role_manager');
        $role_manager->maybe_upgrade_roles();
    }
    
    /**
     * Dismiss Phase 4 notice
     */
    public function dismiss_phase4_notice() {
        check_ajax_referer('wcefp_admin_nonce', '_wpnonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'wceventsfp'));
        }
        
        update_user_meta(get_current_user_id(), 'wcefp_phase4_notice_dismissed', true);
        wp_send_json_success();
    }
}