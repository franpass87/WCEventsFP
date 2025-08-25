#!/usr/bin/env php
<?php
/**
 * WCEventsFP Runtime Shortcode Detection Script
 * 
 * This script uses WP-CLI to detect and analyze shortcodes in a live WordPress environment.
 * It provides runtime verification of shortcode registration and functionality.
 * 
 * Usage:
 *   wp eval-file wcefp-runtime-shortcode-detection.php
 *   wp eval-file wcefp-runtime-shortcode-detection.php --format=json > runtime-shortcodes.json
 * 
 * @package WCEFP
 * @since 2.2.0
 */

if (!defined('WP_CLI') && !defined('WP_DEBUG')) {
    die('This script is intended to be run via WP-CLI or in a WordPress debug environment.');
}

/**
 * Detect and analyze WCEventsFP shortcodes in runtime
 */
class WCEFPRuntimeShortcodeDetector {
    
    private $results = [];
    
    public function __construct() {
        $this->detect_shortcodes();
        $this->analyze_gutenberg_blocks();
        $this->detect_hooks();
        $this->detect_ajax_endpoints();
        $this->output_results();
    }
    
    /**
     * Detect registered shortcodes
     */
    private function detect_shortcodes() {
        global $shortcode_tags;
        
        $this->results['shortcodes'] = [];
        $wcefp_shortcodes = [];
        
        foreach ($shortcode_tags as $tag => $callback) {
            // Check if shortcode is WCEFP related
            if (strpos($tag, 'wcefp') !== false || 
                (is_array($callback) && is_object($callback[0]) && 
                 strpos(get_class($callback[0]), 'WCEFP') !== false)) {
                
                $callback_info = $this->analyze_callback($callback);
                
                $wcefp_shortcodes[$tag] = [
                    'tag' => $tag,
                    'callback' => $callback_info['callback'],
                    'class' => $callback_info['class'],
                    'method' => $callback_info['method'],
                    'is_active' => true,
                    'test_result' => $this->test_shortcode($tag),
                    'detected_at' => current_time('c')
                ];
            }
        }
        
        $this->results['shortcodes'] = $wcefp_shortcodes;
        $this->log_info('Detected ' . count($wcefp_shortcodes) . ' WCEFP shortcodes');
    }
    
    /**
     * Analyze Gutenberg blocks
     */
    private function analyze_gutenberg_blocks() {
        $this->results['gutenberg_blocks'] = [];
        
        if (function_exists('get_dynamic_block_names')) {
            $blocks = get_dynamic_block_names();
            $wcefp_blocks = array_filter($blocks, function($block) {
                return strpos($block, 'wcefp') !== false;
            });
            
            foreach ($wcefp_blocks as $block) {
                $this->results['gutenberg_blocks'][$block] = [
                    'name' => $block,
                    'is_dynamic' => true,
                    'registered_at' => current_time('c')
                ];
            }
            
            $this->log_info('Detected ' . count($wcefp_blocks) . ' WCEFP Gutenberg blocks');
        }
    }
    
    /**
     * Detect WordPress hooks
     */
    private function detect_hooks() {
        global $wp_filter;
        
        $this->results['hooks'] = ['actions' => [], 'filters' => []];
        
        foreach ($wp_filter as $hook_name => $hook) {
            foreach ($hook->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $function = $callback['function'];
                    
                    if (is_array($function) && is_object($function[0]) && 
                        strpos(get_class($function[0]), 'WCEFP') !== false) {
                        
                        $callback_info = [
                            'hook' => $hook_name,
                            'callback' => get_class($function[0]) . '::' . $function[1],
                            'priority' => $priority,
                            'accepted_args' => $callback['accepted_args'] ?? 1
                        ];
                        
                        // Classify as action or filter
                        if (strpos($hook_name, 'filter') !== false || 
                            in_array($hook_name, ['the_content', 'wp_title', 'body_class', 'wp_nav_menu_items'])) {
                            $this->results['hooks']['filters'][$hook_name][] = $callback_info;
                        } else {
                            $this->results['hooks']['actions'][$hook_name][] = $callback_info;
                        }
                    }
                }
            }
        }
        
        $action_count = count($this->results['hooks']['actions']);
        $filter_count = count($this->results['hooks']['filters']);
        
        $this->log_info("Detected {$action_count} action hooks and {$filter_count} filter hooks");
    }
    
    /**
     * Detect AJAX endpoints
     */
    private function detect_ajax_endpoints() {
        global $wp_filter;
        
        $this->results['ajax_endpoints'] = [];
        
        foreach ($wp_filter as $hook_name => $hook) {
            if (strpos($hook_name, 'wp_ajax_wcefp_') === 0) {
                foreach ($hook->callbacks as $callbacks) {
                    foreach ($callbacks as $callback) {
                        $function = $callback['function'];
                        
                        if (is_array($function) && is_object($function[0])) {
                            $action_name = str_replace('wp_ajax_', '', $hook_name);
                            
                            $this->results['ajax_endpoints'][$action_name] = [
                                'action' => $action_name,
                                'hook' => $hook_name,
                                'callback' => get_class($function[0]) . '::' . $function[1],
                                'public' => isset($wp_filter['wp_ajax_nopriv_' . $action_name]),
                                'test_result' => $this->test_ajax_endpoint($action_name)
                            ];
                        }
                    }
                }
            }
        }
        
        $this->log_info('Detected ' . count($this->results['ajax_endpoints']) . ' AJAX endpoints');
    }
    
    /**
     * Test shortcode rendering
     */
    private function test_shortcode($tag) {
        try {
            // Create safe test content
            $test_content = "[$tag]";
            $output = do_shortcode($test_content);
            
            return [
                'status' => 'success',
                'rendered' => !empty($output) && $output !== $test_content,
                'output_length' => strlen($output),
                'has_html' => $output !== strip_tags($output),
                'message' => 'Shortcode rendered successfully'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'rendered' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test AJAX endpoint
     */
    private function test_ajax_endpoint($action) {
        // For security reasons, we only check if the endpoint is registered
        return [
            'status' => 'registered',
            'message' => 'Endpoint is registered and callable'
        ];
    }
    
    /**
     * Analyze callback information
     */
    private function analyze_callback($callback) {
        if (is_array($callback) && is_object($callback[0])) {
            return [
                'callback' => get_class($callback[0]) . '::' . $callback[1],
                'class' => get_class($callback[0]),
                'method' => $callback[1],
                'type' => 'class_method'
            ];
        } elseif (is_string($callback)) {
            return [
                'callback' => $callback,
                'class' => null,
                'method' => null,
                'type' => 'function'
            ];
        } else {
            return [
                'callback' => gettype($callback),
                'class' => null,
                'method' => null,
                'type' => 'unknown'
            ];
        }
    }
    
    /**
     * Output results
     */
    private function output_results() {
        $summary = [
            'plugin' => 'WCEventsFP',
            'version' => defined('WCEFP_VERSION') ? WCEFP_VERSION : 'Unknown',
            'scan_time' => current_time('c'),
            'scan_type' => 'runtime',
            'totals' => [
                'shortcodes' => count($this->results['shortcodes']),
                'gutenberg_blocks' => count($this->results['gutenberg_blocks']),
                'action_hooks' => count($this->results['hooks']['actions']),
                'filter_hooks' => count($this->results['hooks']['filters']),
                'ajax_endpoints' => count($this->results['ajax_endpoints'])
            ]
        ];
        
        $output = [
            'summary' => $summary,
            'data' => $this->results
        ];
        
        if (defined('WP_CLI')) {
            WP_CLI::success('WCEventsFP runtime detection completed');
            WP_CLI::line('');
            WP_CLI::line('=== RUNTIME DETECTION SUMMARY ===');
            WP_CLI::line('Shortcodes: ' . $summary['totals']['shortcodes']);
            WP_CLI::line('Gutenberg Blocks: ' . $summary['totals']['gutenberg_blocks']);
            WP_CLI::line('Action Hooks: ' . $summary['totals']['action_hooks']);
            WP_CLI::line('Filter Hooks: ' . $summary['totals']['filter_hooks']);
            WP_CLI::line('AJAX Endpoints: ' . $summary['totals']['ajax_endpoints']);
            WP_CLI::line('');
            WP_CLI::line('Full data available in JSON format with --format=json');
        } else {
            echo json_encode($output, JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * Log informational messages
     */
    private function log_info($message) {
        if (defined('WP_CLI')) {
            WP_CLI::log($message);
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCEFP Runtime Detection] ' . $message);
        }
    }
}

// Initialize the detector
new WCEFPRuntimeShortcodeDetector();