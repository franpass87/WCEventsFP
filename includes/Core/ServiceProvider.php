<?php
/**
 * Base Service Provider Class
 * 
 * @package WCEFP
 * @subpackage Core
 * @since 2.0.1
 */

namespace WCEFP\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for service providers
 */
abstract class ServiceProvider {
    
    /**
     * Dependency injection container
     * 
     * @var Container
     */
    protected $container;
    
    /**
     * Constructor
     * 
     * @param Container $container Dependency injection container
     */
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    /**
     * Register services in the container
     * 
     * @return void
     */
    abstract public function register();
    
    /**
     * Boot services (called after all providers are registered)
     * 
     * @return void
     */
    public function boot() {
        // Override in subclasses if needed
    }
}