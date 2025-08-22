<?php
/**
 * WCEventsFP WSOD Prevention System
 * 
 * This file provides bulletproof WSOD prevention for the WCEventsFP plugin.
 * It's designed to be included BEFORE any complex plugin initialization.
 * 
 * Usage: Include this file at the very beginning of wceventsfp.php
 * 
 * @package WCEventsFP
 * @version 2.0.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure we haven't already loaded this prevention system
if (defined('WCEFP_WSOD_PREVENTER_LOADED')) {
    return;
}
define('WCEFP_WSOD_PREVENTER_LOADED', true);

/**
 * Emergency error display system that works even when WordPress fails
 * 
 * @param string $message Error message
 * @param array $details Additional error details
 * @return void
 */
function wcefp_emergency_display($message, $details = []) {
    // Try WordPress notice first
    if (function_exists('add_action') && function_exists('is_admin') && is_admin()) {
        add_action('admin_notices', function() use ($message) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>WCEventsFP Error:</strong> ' . esc_html($message) . '</p>';
            echo '<p><small>Il plugin è stato automaticamente disattivato per prevenire problemi. Contatta il supporto.</small></p>';
            echo '</div>';
        }, 1);
    } else {
        // Fallback: Direct output with basic HTML
        $safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html><head><title>WCEventsFP Plugin Error</title>';
        echo '<style>body{font-family:Arial,sans-serif;margin:40px;} .error{background:#f8d7da;color:#721c24;padding:20px;border:1px solid #f5c6cb;border-radius:5px;}</style>';
        echo '</head><body><div class="error">';
        echo '<h2>❌ WCEventsFP Plugin Error</h2>';
        echo '<p><strong>Errore:</strong> ' . $safe_message . '</p>';
        echo '<p>Il plugin è stato disattivato automaticamente per prevenire problemi con il sito.</p>';
        if (!empty($details)) {
            echo '<details><summary>Dettagli tecnici</summary><pre>' . htmlspecialchars(print_r($details, true)) . '</pre></details>';
        }
        echo '<p><strong>Soluzioni:</strong></p>';
        echo '<ul><li>Verifica che WooCommerce sia attivo</li>';
        echo '<li>Controlla i limiti di memoria PHP (raccomandati: 256MB+)</li>';
        echo '<li>Contatta il supporto con questi dettagli</li></ul>';
        echo '</div></body></html>';
        exit;
    }
}

/**
 * Safe plugin deactivation to prevent WSOD
 * 
 * @param string $reason Reason for deactivation
 * @return void
 */
function wcefp_safe_deactivate($reason) {
    if (function_exists('deactivate_plugins')) {
        $plugin_file = defined('WCEFP_PLUGIN_FILE') ? WCEFP_PLUGIN_FILE : __FILE__;
        deactivate_plugins(plugin_basename($plugin_file));
        
        error_log("WCEventsFP: Plugin auto-deactivated - {$reason}");
        
        wcefp_emergency_display("Plugin disattivato automaticamente: {$reason}");
    }
}

/**
 * Ultra-safe environment check before any plugin operations
 * 
 * @return bool True if environment is safe, false otherwise
 */
function wcefp_ultra_safe_environment_check() {
    $errors = [];
    
    // PHP version check
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $errors[] = "PHP 7.4+ richiesto. Versione attuale: " . PHP_VERSION;
    }
    
    // WordPress check
    if (!defined('ABSPATH') || !function_exists('wp_die')) {
        $errors[] = "WordPress non rilevato o non completamente caricato";
    }
    
    // WooCommerce check
    if (!class_exists('WooCommerce')) {
        $errors[] = "WooCommerce non è attivo o non installato";
    }
    
    // Memory check
    $memory_limit = ini_get('memory_limit');
    if ($memory_limit !== '-1') {
        $memory_bytes = wcefp_safe_memory_conversion($memory_limit);
        if ($memory_bytes > 0 && $memory_bytes < 134217728) { // 128MB
            $errors[] = "Memoria PHP insufficiente. Minimo raccomandato: 256MB, attuale: {$memory_limit}";
        }
    }
    
    // Required extensions
    $required_exts = ['mysqli', 'json'];
    foreach ($required_exts as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "Estensione PHP mancante: {$ext}";
        }
    }
    
    if (!empty($errors)) {
        wcefp_emergency_display("Controlli ambiente falliti", $errors);
        wcefp_safe_deactivate("Ambiente non compatibile");
        return false;
    }
    
    return true;
}

/**
 * Bulletproof memory limit conversion - matches main file version
 * 
 * @param string|int|null $val Memory value from ini_get
 * @return int Memory in bytes or 0 if invalid
 */
function wcefp_safe_memory_conversion($val) {
    // Handle null, false, empty values
    if ($val === null || $val === false || $val === '') {
        return 0;
    }
    
    // Handle numeric values (already in bytes)
    if (is_numeric($val)) {
        $bytes = (int) $val;
        return $bytes < 0 ? 0 : $bytes;
    }
    
    // Handle string values
    if (!is_string($val)) {
        return 0;
    }
    
    $val = trim($val);
    if ($val === '' || $val === '0') {
        return 0;
    }
    
    // Special case: unlimited memory
    if ($val === '-1') {
        return -1;
    }
    
    // Extract numeric part and unit - more robust pattern
    if (!preg_match('/^(\d+(?:\.\d+)?)\s*([kmgtKMGT]?)$/i', $val, $matches)) {
        return 0; // Invalid format
    }
    
    $number = (float) $matches[1];
    $unit = isset($matches[2]) ? strtolower($matches[2]) : '';
    
    // Prevent negative or zero values
    if ($number <= 0) {
        return 0;
    }
    
    // Convert based on unit
    switch ($unit) {
        case 't': $number *= 1024; // fall through
        case 'g': $number *= 1024; // fall through
        case 'm': $number *= 1024; // fall through
        case 'k': $number *= 1024; break;
        case '':  // No unit = bytes
        default:  // Unknown unit = treat as bytes
            break;
    }
    
    // Ensure positive integer result
    $result = (int) $number;
    return $result < 0 ? 0 : $result;
}

/**
 * Register emergency shutdown handler
 * This catches fatal errors that would cause WSOD
 * 
 * @return void
 */
function wcefp_register_emergency_shutdown() {
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING])) {
            // A fatal error occurred - try to provide helpful information
            
            $message = "Errore fatale PHP durante il caricamento del plugin";
            $details = [
                'error' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ];
            
            // Try to log the error
            error_log("WCEventsFP Fatal Error: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
            
            // If we can still output, show error
            if (!headers_sent()) {
                wcefp_emergency_display($message, $details);
            }
            
            // Try to deactivate the plugin
            wcefp_safe_deactivate("Fatal PHP Error");
        }
    });
}

/**
 * Initialize the WSOD prevention system
 * 
 * @return bool True if safe to continue, false to abort plugin loading
 */
function wcefp_init_wsod_prevention() {
    // Set up emergency shutdown handler first
    wcefp_register_emergency_shutdown();
    
    // Check if environment is safe
    if (!wcefp_ultra_safe_environment_check()) {
        return false;
    }
    
    // Set up error handling to prevent WSOD
    set_error_handler(function($severity, $message, $file, $line) {
        // Don't handle fatal errors here (shutdown function will handle them)
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error_msg = "WCEventsFP Error [{$severity}]: {$message} in {$file}:{$line}";
        error_log($error_msg);
        
        // For non-fatal errors, continue execution but log them
        return false;
    });
    
    return true;
}

// Initialize the prevention system immediately when this file is included
if (!wcefp_init_wsod_prevention()) {
    // Environment check failed, plugin loading aborted
    return;
}

// Mark that WSOD prevention is active
define('WCEFP_WSOD_PROTECTION_ACTIVE', true);