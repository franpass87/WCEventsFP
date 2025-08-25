<?php
/**
 * Module Interface
 * 
 * @package WCEFP\Contracts
 * @since 2.2.0
 */

namespace WCEFP\Contracts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for WCEFP modules
 */
interface ModuleInterface {
    /**
     * Initialize the module
     * 
     * @return void
     */
    public function init(): void;
    
    /**
     * Get module priority for loading order
     * 
     * @return int
     */
    public function get_priority(): int;
    
    /**
     * Get module dependencies
     * 
     * @return array
     */
    public function get_dependencies(): array;
}