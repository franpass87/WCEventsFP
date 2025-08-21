<?php
/**
 * Simple Logger for WCEventsFP
 * Migliora debugging e monitoring del sistema
 *
 * @package WCEventsFP
 * @since 1.7.2
 */

if (!defined('ABSPATH')) exit;

class WCEFP_Logger {
    
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning'; 
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';
    
    private $enabled = true;
    
    public function __construct() {
        $this->enabled = defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * Log an error
     */
    public function error($message, $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log a warning
     */
    public function warning($message, $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log an info message
     */
    public function info($message, $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log a debug message
     */
    public function debug($message, $context = []) {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log a message
     */
    private function log($level, $message, $context = []) {
        if (!$this->enabled) {
            return;
        }
        
        $formatted = sprintf(
            '[WCEventsFP] [%s] %s %s',
            strtoupper($level),
            $message,
            !empty($context) ? json_encode($context) : ''
        );
        
        error_log($formatted);
    }
}