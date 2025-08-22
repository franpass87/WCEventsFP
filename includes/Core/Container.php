<?php
/**
 * Dependency Injection Container
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
 * Simple dependency injection container
 */
class Container {
    
    /**
     * Registered services
     * 
     * @var array
     */
    private $services = [];
    
    /**
     * Singleton instances
     * 
     * @var array
     */
    private $instances = [];
    
    /**
     * Register a service
     * 
     * @param string $name Service name
     * @param mixed $definition Service definition
     * @param bool $singleton Whether service is singleton
     * @return void
     */
    public function set($name, $definition, $singleton = false) {
        $this->services[$name] = [
            'definition' => $definition,
            'singleton' => $singleton
        ];
        
        // Clear existing instance if redefining
        unset($this->instances[$name]);
    }
    
    /**
     * Register a singleton service
     * 
     * @param string $name Service name
     * @param mixed $definition Service definition
     * @return void
     */
    public function singleton($name, $definition) {
        $this->set($name, $definition, true);
    }
    
    /**
     * Get a service
     * 
     * @param string $name Service name
     * @return mixed
     * @throws \Exception If service not found
     */
    public function get($name) {
        if (!$this->has($name)) {
            throw new \Exception("Service '{$name}' not found in container");
        }
        
        $service = $this->services[$name];
        
        // Return singleton instance if exists
        if ($service['singleton'] && isset($this->instances[$name])) {
            return $this->instances[$name];
        }
        
        // Create instance
        $instance = $this->create_instance($service['definition']);
        
        // Store singleton instance
        if ($service['singleton']) {
            $this->instances[$name] = $instance;
        }
        
        return $instance;
    }
    
    /**
     * Check if service is registered
     * 
     * @param string $name Service name
     * @return bool
     */
    public function has($name) {
        return isset($this->services[$name]);
    }
    
    /**
     * Create service instance
     * 
     * @param mixed $definition Service definition
     * @return mixed
     */
    private function create_instance($definition) {
        if (is_callable($definition)) {
            return $definition($this);
        }
        
        if (is_string($definition) && class_exists($definition)) {
            return new $definition();
        }
        
        if (is_object($definition)) {
            return $definition;
        }
        
        return $definition;
    }
    
    /**
     * Get all registered service names
     * 
     * @return array
     */
    public function get_services() {
        return array_keys($this->services);
    }
}