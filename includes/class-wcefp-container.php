<?php
/**
 * Simple Service Container for WCEventsFP
 * Migliora la gestione delle dipendenze e la testabilità
 *
 * @package WCEventsFP
 * @since 1.7.2
 */

if (!defined('ABSPATH')) exit;

class WCEFP_Container {
    
    private static $instance = null;
    private $services = [];
    private $singletons = [];
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register a service factory
     */
    public function register($name, callable $factory) {
        $this->services[$name] = $factory;
    }
    
    /**
     * Register a singleton service
     */
    public function singleton($name, callable $factory) {
        $this->services[$name] = $factory;
        $this->singletons[$name] = true;
    }
    
    /**
     * Get a service instance
     */
    public function get($name) {
        if (!isset($this->services[$name])) {
            throw new Exception("Service '{$name}' not found");
        }
        
        $factory = $this->services[$name];
        
        // Return singleton if exists
        if (isset($this->singletons[$name])) {
            if (!isset($this->instances[$name])) {
                $this->instances[$name] = $factory($this);
            }
            return $this->instances[$name];
        }
        
        // Return new instance
        return $factory($this);
    }
    
    /**
     * Check if service exists
     */
    public function has($name) {
        return isset($this->services[$name]);
    }
}