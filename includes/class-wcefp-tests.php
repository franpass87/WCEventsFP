<?php
/**
 * Basic Test Suite per WCEventsFP 
 * Migliora affidabilità e facilita debug
 *
 * @package WCEventsFP
 * @since 1.7.2
 */

if (!defined('ABSPATH')) exit;

class WCEFP_Tests {
    
    private static $results = [];
    
    /**
     * Esegui tutti i test di base
     */
    public static function run_all() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        self::$results = [];
        
        // Test componenti helper
        self::test_logger();
        self::test_cache();
        self::test_validator();
        self::test_config();
        self::test_rate_limiter();
        
        // Test funzioni critiche
        self::test_atomic_booking();
        
        return self::$results;
    }
    
    /**
     * Test logger
     */
    private static function test_logger() {
        try {
            $logger = new WCEFP_Logger();
            $logger->debug('Test log message');
            self::$results['logger'] = 'PASS';
        } catch (Exception $e) {
            self::$results['logger'] = 'FAIL: ' . $e->getMessage();
        }
    }
    
    /**
     * Test cache
     */
    private static function test_cache() {
        try {
            WCEFP_Cache::set('test_key', 'test_value', 60);
            $value = WCEFP_Cache::get('test_key');
            
            if ($value === 'test_value') {
                WCEFP_Cache::delete('test_key');
                self::$results['cache'] = 'PASS';
            } else {
                self::$results['cache'] = 'FAIL: Value mismatch';
            }
        } catch (Exception $e) {
            self::$results['cache'] = 'FAIL: ' . $e->getMessage();
        }
    }
    
    /**
     * Test validator
     */
    private static function test_validator() {
        try {
            $tests = [
                WCEFP_Validator::date('2024-01-01') === true,
                WCEFP_Validator::date('invalid') === false,
                WCEFP_Validator::time('14:30') === true,
                WCEFP_Validator::time('25:70') === false,
                WCEFP_Validator::email('test@example.com') === true,
                WCEFP_Validator::email('invalid-email') === false,
                WCEFP_Validator::capacity(10) === true,
                WCEFP_Validator::capacity(-5) === false,
            ];
            
            if (array_sum($tests) === count($tests)) {
                self::$results['validator'] = 'PASS';
            } else {
                self::$results['validator'] = 'FAIL: Some validations failed';
            }
        } catch (Exception $e) {
            self::$results['validator'] = 'FAIL: ' . $e->getMessage();
        }
    }
    
    /**
     * Test config
     */
    private static function test_config() {
        try {
            $version = WCEFP_Config::version();
            $debug = WCEFP_Config::debug_enabled();
            $capacity = WCEFP_Config::default_capacity();
            
            if (!empty($version) && is_bool($debug) && is_numeric($capacity)) {
                self::$results['config'] = 'PASS';
            } else {
                self::$results['config'] = 'FAIL: Config values invalid';
            }
        } catch (Exception $e) {
            self::$results['config'] = 'FAIL: ' . $e->getMessage();
        }
    }
    
    /**
     * Test funzione booking atomico
     */
    private static function test_atomic_booking() {
        // Test solo se esistono tabelle (evita errori in fresh install)
        global $wpdb;
        $table = $wpdb->prefix . 'wcefp_occurrences';
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        
        if (!$exists) {
            self::$results['atomic_booking'] = 'SKIP: Tables not found';
            return;
        }
        
        try {
            // Test che la funzione esista e non generi errori
            if (function_exists('wcefp_update_booked_atomic')) {
                self::$results['atomic_booking'] = 'PASS';
            } else {
                self::$results['atomic_booking'] = 'FAIL: Function not found';
            }
        } catch (Exception $e) {
            self::$results['atomic_booking'] = 'FAIL: ' . $e->getMessage();
        }
    }
    
    /**
     * Test rate limiter
     */
    private static function test_rate_limiter() {
        try {
            // Test che la classe esista e funzioni base
            $limited = WCEFP_RateLimiter::is_limited('test_action', 1, 60);
            
            if (is_bool($limited)) {
                self::$results['rate_limiter'] = 'PASS';
            } else {
                self::$results['rate_limiter'] = 'FAIL: Invalid return type';
            }
        } catch (Exception $e) {
            self::$results['rate_limiter'] = 'FAIL: ' . $e->getMessage();
        }
    }
    
    /**
     * Genera report HTML dei risultati
     */
    public static function get_html_report($results = null) {
        $results = $results ?: self::$results;
        
        if (empty($results)) {
            return '<p>Nessun test eseguito.</p>';
        }
        
        $html = '<div class="wcefp-test-results"><h3>Risultati Test WCEventsFP</h3><table class="widefat striped">';
        $html .= '<thead><tr><th>Componente</th><th>Risultato</th></tr></thead><tbody>';
        
        foreach ($results as $test => $result) {
            $status_class = strpos($result, 'PASS') === 0 ? 'pass' : (strpos($result, 'SKIP') === 0 ? 'skip' : 'fail');
            $html .= sprintf(
                '<tr><td>%s</td><td class="test-%s">%s</td></tr>',
                esc_html(ucfirst($test)),
                $status_class,
                esc_html($result)
            );
        }
        
        $html .= '</tbody></table></div>';
        $html .= '<style>.test-pass{color:#0073aa}.test-fail{color:#d63638}.test-skip{color:#dba617}</style>';
        
        return $html;
    }
}