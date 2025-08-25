<?php
/**
 * WCEFP Diagnostics Admin Page
 * 
 * Admin page that displays shortcodes, hooks, endpoints and other plugin features
 * for runtime diagnostics and feature verification.
 * 
 * @package WCEFP\Admin
 * @since 2.2.0
 */

namespace WCEFP\Admin;

class DiagnosticsPage {
    
    /**
     * Initialize the diagnostics page
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_diagnostics_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_wcefp_refresh_diagnostics', [$this, 'ajax_refresh_diagnostics']);
        add_action('wp_ajax_wcefp_export_diagnostics', [$this, 'ajax_export_diagnostics']);
    }
    
    /**
     * Add diagnostics page to Tools menu
     */
    public function add_diagnostics_page() {
        add_management_page(
            'WCEFP Diagnostics',
            'WCEFP Diagnostics', 
            'manage_options',
            'wcefp-diagnostics',
            [$this, 'render_diagnostics_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'tools_page_wcefp-diagnostics') {
            return;
        }
        
        wp_enqueue_style(
            'wcefp-diagnostics-admin',
            WCEFP_PLUGIN_URL . 'assets/css/admin-diagnostics.css',
            [],
            WCEFP_VERSION
        );
        
        wp_enqueue_script(
            'wcefp-diagnostics-admin',
            WCEFP_PLUGIN_URL . 'assets/js/admin-diagnostics.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-diagnostics-admin', 'wcefp_diagnostics', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_diagnostics_nonce'),
            'refresh_text' => __('Refreshing...', 'wceventsfp'),
        ]);
    }
    
    /**
     * Render the diagnostics page
     */
    public function render_diagnostics_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php _e('Runtime diagnostics and feature verification for WCEventsFP plugin.', 'wceventsfp'); ?></p>
            
            <div class="wcefp-diagnostics-header">
                <div class="wcefp-header-actions">
                    <button type="button" class="button button-secondary" id="wcefp-refresh-diagnostics">
                        <?php _e('Refresh Data', 'wceventsfp'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="wcefp-export-diagnostics" data-format="json">
                        <?php _e('Export JSON', 'wceventsfp'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="wcefp-export-diagnostics-txt" data-format="txt">
                        <?php _e('Export TXT', 'wceventsfp'); ?>
                    </button>
                </div>
                <span class="wcefp-last-updated">
                    <?php printf(__('Last updated: %s', 'wceventsfp'), current_time('Y-m-d H:i:s')); ?>
                </span>
            </div>
            
            <div class="wcefp-diagnostics-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#shortcodes" class="nav-tab nav-tab-active"><?php _e('Shortcodes', 'wceventsfp'); ?></a>
                    <a href="#hooks" class="nav-tab"><?php _e('Hooks', 'wceventsfp'); ?></a>
                    <a href="#endpoints" class="nav-tab"><?php _e('AJAX/REST', 'wceventsfp'); ?></a>
                    <a href="#performance" class="nav-tab"><?php _e('Performance', 'wceventsfp'); ?></a>
                    <a href="#database" class="nav-tab"><?php _e('Database', 'wceventsfp'); ?></a>
                    <a href="#security" class="nav-tab"><?php _e('Security', 'wceventsfp'); ?></a>
                    <a href="#options" class="nav-tab"><?php _e('Options', 'wceventsfp'); ?></a>
                    <a href="#system" class="nav-tab"><?php _e('System Info', 'wceventsfp'); ?></a>
                </nav>
                
                <div class="tab-content">
                    <div id="shortcodes" class="tab-pane active">
                        <?php $this->render_shortcodes_section(); ?>
                    </div>
                    
                    <div id="hooks" class="tab-pane">
                        <?php $this->render_hooks_section(); ?>
                    </div>
                    
                    <div id="endpoints" class="tab-pane">
                        <?php $this->render_endpoints_section(); ?>
                    </div>
                    
                    <div id="performance" class="tab-pane">
                        <?php $this->render_performance_section(); ?>
                    </div>
                    
                    <div id="database" class="tab-pane">
                        <?php $this->render_database_section(); ?>
                    </div>
                    
                    <div id="security" class="tab-pane">
                        <?php $this->render_security_section(); ?>
                    </div>
                    
                    <div id="options" class="tab-pane">
                        <?php $this->render_options_section(); ?>
                    </div>
                    
                    <div id="system" class="tab-pane">
                        <?php $this->render_system_info(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render registered shortcodes section
     */
    private function render_shortcodes_section() {
        global $shortcode_tags;
        
        $wcefp_shortcodes = [];
        foreach ($shortcode_tags as $tag => $callback) {
            if (strpos($tag, 'wcefp') !== false || 
                (is_array($callback) && is_object($callback[0]) && 
                 strpos(get_class($callback[0]), 'WCEFP') !== false)) {
                $wcefp_shortcodes[$tag] = $callback;
            }
        }
        
        ?>
        <div class="wcefp-section">
            <h3><?php _e('Registered WCEFP Shortcodes', 'wceventsfp'); ?> 
                <span class="count">(<?php echo count($wcefp_shortcodes); ?>)</span>
            </h3>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Shortcode', 'wceventsfp'); ?></th>
                        <th><?php _e('Callback', 'wceventsfp'); ?></th>
                        <th><?php _e('Status', 'wceventsfp'); ?></th>
                        <th><?php _e('Test', 'wceventsfp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($wcefp_shortcodes)): ?>
                        <tr>
                            <td colspan="4"><?php _e('No WCEFP shortcodes found.', 'wceventsfp'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($wcefp_shortcodes as $tag => $callback): ?>
                            <tr>
                                <td><code>[<?php echo esc_html($tag); ?>]</code></td>
                                <td>
                                    <?php 
                                    if (is_array($callback)) {
                                        $class = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
                                        echo esc_html($class . '::' . $callback[1]);
                                    } else {
                                        echo esc_html(is_string($callback) ? $callback : gettype($callback));
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="status-indicator status-active">
                                        <?php _e('Active', 'wceventsfp'); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button button-small test-shortcode" 
                                            data-shortcode="<?php echo esc_attr($tag); ?>">
                                        <?php _e('Test Render', 'wceventsfp'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render hooks section  
     */
    private function render_hooks_section() {
        global $wp_filter;
        
        $wcefp_actions = [];
        $wcefp_filters = [];
        
        foreach ($wp_filter as $hook_name => $hook) {
            foreach ($hook->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $function = $callback['function'];
                    if (is_array($function) && is_object($function[0]) && 
                        strpos(get_class($function[0]), 'WCEFP') !== false) {
                        
                        if (strpos($hook_name, 'filter') !== false || 
                            in_array($hook_name, ['the_content', 'wp_title', 'body_class'])) {
                            $wcefp_filters[$hook_name][] = [
                                'callback' => get_class($function[0]) . '::' . $function[1],
                                'priority' => $priority
                            ];
                        } else {
                            $wcefp_actions[$hook_name][] = [
                                'callback' => get_class($function[0]) . '::' . $function[1],
                                'priority' => $priority
                            ];
                        }
                    }
                }
            }
        }
        
        ?>
        <div class="wcefp-section">
            <h3><?php _e('WCEFP Action Hooks', 'wceventsfp'); ?> 
                <span class="count">(<?php echo count($wcefp_actions); ?>)</span>
            </h3>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Hook Name', 'wceventsfp'); ?></th>
                        <th><?php _e('Callbacks', 'wceventsfp'); ?></th>
                        <th><?php _e('Priority', 'wceventsfp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($wcefp_actions)): ?>
                        <tr>
                            <td colspan="3"><?php _e('No WCEFP action hooks found.', 'wceventsfp'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($wcefp_actions as $hook => $callbacks): ?>
                            <tr>
                                <td><code><?php echo esc_html($hook); ?></code></td>
                                <td>
                                    <?php foreach ($callbacks as $callback): ?>
                                        <div><?php echo esc_html($callback['callback']); ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php foreach ($callbacks as $callback): ?>
                                        <div><?php echo esc_html($callback['priority']); ?></div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="wcefp-section">
            <h3><?php _e('WCEFP Filter Hooks', 'wceventsfp'); ?> 
                <span class="count">(<?php echo count($wcefp_filters); ?>)</span>
            </h3>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Hook Name', 'wceventsfp'); ?></th>
                        <th><?php _e('Callbacks', 'wceventsfp'); ?></th>
                        <th><?php _e('Priority', 'wceventsfp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($wcefp_filters)): ?>
                        <tr>
                            <td colspan="3"><?php _e('No WCEFP filter hooks found.', 'wceventsfp'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($wcefp_filters as $hook => $callbacks): ?>
                            <tr>
                                <td><code><?php echo esc_html($hook); ?></code></td>
                                <td>
                                    <?php foreach ($callbacks as $callback): ?>
                                        <div><?php echo esc_html($callback['callback']); ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php foreach ($callbacks as $callback): ?>
                                        <div><?php echo esc_html($callback['priority']); ?></div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render AJAX/REST endpoints section
     */
    private function render_endpoints_section() {
        global $wp_filter;
        
        $ajax_endpoints = [];
        $rest_routes = [];
        
        // Scan for AJAX endpoints
        foreach ($wp_filter as $hook_name => $hook) {
            if (strpos($hook_name, 'wp_ajax_wcefp_') === 0) {
                foreach ($hook->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        $function = $callback['function'];
                        if (is_array($function) && is_object($function[0])) {
                            $ajax_endpoints[$hook_name] = [
                                'callback' => get_class($function[0]) . '::' . $function[1],
                                'public' => strpos($hook_name, 'wp_ajax_nopriv_') === 0
                            ];
                        }
                    }
                }
            }
        }
        
        // Get REST routes
        $rest_server = rest_get_server();
        if ($rest_server) {
            $routes = $rest_server->get_routes();
            foreach ($routes as $route => $handlers) {
                if (strpos($route, '/wcefp') === 0) {
                    $rest_routes[$route] = $handlers;
                }
            }
        }
        
        ?>
        <div class="wcefp-section">
            <h3><?php _e('AJAX Endpoints', 'wceventsfp'); ?> 
                <span class="count">(<?php echo count($ajax_endpoints); ?>)</span>
            </h3>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Action', 'wceventsfp'); ?></th>
                        <th><?php _e('Callback', 'wceventsfp'); ?></th>
                        <th><?php _e('Access', 'wceventsfp'); ?></th>
                        <th><?php _e('Test', 'wceventsfp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ajax_endpoints)): ?>
                        <tr>
                            <td colspan="4"><?php _e('No AJAX endpoints found.', 'wceventsfp'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ajax_endpoints as $action => $data): ?>
                            <tr>
                                <td><code><?php echo esc_html(str_replace('wp_ajax_', '', $action)); ?></code></td>
                                <td><?php echo esc_html($data['callback']); ?></td>
                                <td>
                                    <?php if ($data['public']): ?>
                                        <span class="status-indicator status-public">
                                            <?php _e('Public', 'wceventsfp'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-indicator status-private">
                                            <?php _e('Admin Only', 'wceventsfp'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small test-ajax" 
                                            data-action="<?php echo esc_attr(str_replace('wp_ajax_', '', $action)); ?>">
                                        <?php _e('Test', 'wceventsfp'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="wcefp-section">
            <h3><?php _e('REST API Routes', 'wceventsfp'); ?> 
                <span class="count">(<?php echo count($rest_routes); ?>)</span>
            </h3>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Route', 'wceventsfp'); ?></th>
                        <th><?php _e('Methods', 'wceventsfp'); ?></th>
                        <th><?php _e('Callback', 'wceventsfp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rest_routes)): ?>
                        <tr>
                            <td colspan="3"><?php _e('No REST routes found.', 'wceventsfp'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rest_routes as $route => $handlers): ?>
                            <tr>
                                <td><code><?php echo esc_html($route); ?></code></td>
                                <td>
                                    <?php 
                                    $methods = [];
                                    foreach ($handlers as $handler) {
                                        $methods = array_merge($methods, $handler['methods']);
                                    }
                                    echo esc_html(implode(', ', array_unique($methods)));
                                    ?>
                                </td>
                                <td>
                                    <?php foreach ($handlers as $handler): ?>
                                        <?php if (isset($handler['callback'])): ?>
                                            <div><?php echo esc_html(is_array($handler['callback']) ? get_class($handler['callback'][0]) . '::' . $handler['callback'][1] : $handler['callback']); ?></div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render options verification section
     */
    private function render_options_section() {
        $key_options = [
            'wcefp_default_capacity' => get_option('wcefp_default_capacity', 0),
            'wcefp_booking_window_days' => get_option('wcefp_booking_window_days', 30),
            'wcefp_google_places_api_key' => get_option('wcefp_google_places_api_key', ''),
            'wcefp_brevo_api_key' => get_option('wcefp_brevo_api_key', ''),
            'wcefp_plugin_version' => get_option('wcefp_plugin_version', ''),
            'wcefp_onboarding_completed' => get_option('wcefp_onboarding_completed', false),
        ];
        
        ?>
        <div class="wcefp-section">
            <h3><?php _e('Key Plugin Options', 'wceventsfp'); ?></h3>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Option Name', 'wceventsfp'); ?></th>
                        <th><?php _e('Value', 'wceventsfp'); ?></th>
                        <th><?php _e('Status', 'wceventsfp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($key_options as $option => $value): ?>
                        <tr>
                            <td><code><?php echo esc_html($option); ?></code></td>
                            <td>
                                <?php 
                                if (strpos($option, 'api_key') !== false && !empty($value)) {
                                    echo esc_html(substr($value, 0, 10) . '...');
                                } elseif (is_bool($value)) {
                                    echo $value ? __('Yes', 'wceventsfp') : __('No', 'wceventsfp');
                                } else {
                                    echo esc_html($value ?: __('(empty)', 'wceventsfp'));
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($value)): ?>
                                    <span class="status-indicator status-active">
                                        <?php _e('Set', 'wceventsfp'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-indicator status-inactive">
                                        <?php _e('Empty', 'wceventsfp'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render system information
     */
    private function render_system_info() {
        global $wpdb;
        
        ?>
        <div class="wcefp-section">
            <h3><?php _e('System Information', 'wceventsfp'); ?></h3>
            
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <th><?php _e('Plugin Version', 'wceventsfp'); ?></th>
                        <td><?php echo esc_html(WCEFP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('WordPress Version', 'wceventsfp'); ?></th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('PHP Version', 'wceventsfp'); ?></th>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('WooCommerce Version', 'wceventsfp'); ?></th>
                        <td><?php echo esc_html(defined('WC_VERSION') ? WC_VERSION : __('Not installed', 'wceventsfp')); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Database Tables', 'wceventsfp'); ?></th>
                        <td>
                            <?php 
                            $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}wcefp_%'");
                            echo count($tables) . ' ' . __('tables found', 'wceventsfp');
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Active Theme', 'wceventsfp'); ?></th>
                        <td><?php echo esc_html(wp_get_theme()->get('Name')); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Memory Limit', 'wceventsfp'); ?></th>
                        <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Upload Max Filesize', 'wceventsfp'); ?></th>
                        <td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for refreshing diagnostics data
     */
    public function ajax_refresh_diagnostics() {
        check_ajax_referer('wcefp_diagnostics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'wceventsfp'));
        }
        
        // Clear any relevant caches
        wp_cache_flush();
        
        wp_send_json_success([
            'message' => __('Diagnostics data refreshed successfully.', 'wceventsfp'),
            'timestamp' => current_time('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * AJAX handler for exporting diagnostics report
     */
    public function ajax_export_diagnostics() {
        check_ajax_referer('wcefp_diagnostics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'wceventsfp'));
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'json');
        $report = $this->generate_diagnostic_report();
        
        if ($format === 'json') {
            wp_send_json_success([
                'data' => $report,
                'filename' => 'wcefp-diagnostics-' . date('Y-m-d-H-i-s') . '.json',
                'content_type' => 'application/json'
            ]);
        } else {
            // Generate text format
            $text_report = $this->format_report_as_text($report);
            wp_send_json_success([
                'data' => $text_report,
                'filename' => 'wcefp-diagnostics-' . date('Y-m-d-H-i-s') . '.txt',
                'content_type' => 'text/plain'
            ]);
        }
    }
    
    /**
     * Format diagnostic report as readable text
     */
    private function format_report_as_text($report) {
        $text = "WCEFP Diagnostics Report\n";
        $text .= "Generated: " . $report['generated_at'] . "\n";
        $text .= "Generation Time: " . $report['generation_time'] . "\n";
        $text .= str_repeat("=", 50) . "\n\n";
        
        // Plugin Information
        $text .= "PLUGIN INFORMATION\n";
        $text .= str_repeat("-", 20) . "\n";
        foreach ($report['plugin_info'] as $key => $value) {
            $text .= ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
        }
        $text .= "\n";
        
        // Shortcodes
        $text .= "SHORTCODES\n";
        $text .= str_repeat("-", 10) . "\n";
        $text .= "Total: " . $report['shortcodes']['total_shortcodes'] . "\n";
        $text .= "Average Execution Time: " . $report['shortcodes']['average_execution'] . "\n\n";
        
        // Performance
        $text .= "PERFORMANCE\n";
        $text .= str_repeat("-", 11) . "\n";
        $text .= "Memory Usage: " . $report['performance']['memory_usage']['current'] . 
                " (Peak: " . $report['performance']['memory_usage']['peak'] . ")\n";
        $text .= "Database Queries: " . $report['performance']['database']['queries'] . "\n";
        $text .= "Execution Time: " . $report['performance']['execution_time']['elapsed'] . "\n\n";
        
        // Database
        $text .= "DATABASE\n";
        $text .= str_repeat("-", 8) . "\n";
        $text .= "Total Tables: " . $report['database']['total_tables'] . "\n";
        $text .= "Charset: " . $report['database']['charset'] . "\n";
        $text .= "Collation: " . $report['database']['collate'] . "\n\n";
        
        // Security
        $text .= "SECURITY CHECKS\n";
        $text .= str_repeat("-", 15) . "\n";
        foreach ($report['security'] as $check => $data) {
            $text .= ucfirst(str_replace('_', ' ', $check)) . ": " . $data['value'] . " (" . $data['status'] . ")\n";
        }
        
        return $text;
    }
    
    /**
     * Generate comprehensive diagnostic report
     * 
     * @return array Diagnostic report data
     */
    private function generate_diagnostic_report() {
        $start_time = microtime(true);
        
        $report = [
            'generated_at' => current_time('c'),
            'plugin_info' => [
                'version' => WCEFP_VERSION,
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'Not installed'
            ],
            'shortcodes' => $this->get_shortcode_analysis(),
            'hooks' => $this->get_hooks_analysis(), 
            'performance' => $this->get_performance_metrics(),
            'database' => $this->get_database_analysis(),
            'security' => $this->perform_security_checks(),
            'compatibility' => $this->check_plugin_compatibility()
        ];
        
        $report['generation_time'] = round((microtime(true) - $start_time) * 1000, 2) . 'ms';
        
        return $report;
    }
    
    /**
     * Get shortcode performance analysis
     */
    private function get_shortcode_analysis() {
        global $shortcode_tags;
        
        $wcefp_shortcodes = [];
        $performance_data = [];
        
        foreach ($shortcode_tags as $tag => $callback) {
            if (strpos($tag, 'wcefp') === 0 || strpos($tag, 'event') === 0) {
                $wcefp_shortcodes[$tag] = $callback;
                
                // Test shortcode performance
                $start_time = microtime(true);
                ob_start();
                do_shortcode("[{$tag}]");
                ob_get_clean();
                $execution_time = (microtime(true) - $start_time) * 1000;
                
                $performance_data[$tag] = [
                    'execution_time' => round($execution_time, 2) . 'ms',
                    'callback' => is_array($callback) ? get_class($callback[0]) . '::' . $callback[1] : (string)$callback,
                    'status' => $execution_time < 100 ? 'good' : ($execution_time < 500 ? 'warning' : 'critical')
                ];
            }
        }
        
        return [
            'total_shortcodes' => count($wcefp_shortcodes),
            'performance' => $performance_data,
            'average_execution' => count($performance_data) ? round(array_sum(array_column($performance_data, 'execution_time')) / count($performance_data), 2) . 'ms' : '0ms'
        ];
    }
    
    /**
     * Get hooks analysis with priority information
     */
    private function get_hooks_analysis() {
        global $wp_filter;
        
        $wcefp_hooks = [];
        $hook_count = 0;
        
        foreach ($wp_filter as $hook_name => $hook) {
            if (strpos($hook_name, 'wcefp') !== false || strpos($hook_name, 'wceventsfp') !== false) {
                $callbacks = [];
                foreach ($hook->callbacks as $priority => $functions) {
                    foreach ($functions as $function_name => $function_data) {
                        $callback_info = is_array($function_data['function']) 
                            ? get_class($function_data['function'][0]) . '::' . $function_data['function'][1]
                            : (string)$function_data['function'];
                        
                        $callbacks[] = [
                            'priority' => $priority,
                            'callback' => $callback_info,
                            'accepted_args' => $function_data['accepted_args']
                        ];
                        $hook_count++;
                    }
                }
                
                if (!empty($callbacks)) {
                    $wcefp_hooks[$hook_name] = $callbacks;
                }
            }
        }
        
        return [
            'total_hooks' => count($wcefp_hooks),
            'total_callbacks' => $hook_count,
            'hooks' => $wcefp_hooks
        ];
    }
    
    /**
     * Get performance metrics
     */
    private function get_performance_metrics() {
        return [
            'memory_usage' => [
                'current' => size_format(memory_get_usage(true)),
                'peak' => size_format(memory_get_peak_usage(true)),
                'limit' => ini_get('memory_limit')
            ],
            'execution_time' => [
                'script_start' => $_SERVER['REQUEST_TIME_FLOAT'] ?? 0,
                'current_time' => microtime(true),
                'elapsed' => round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? 0)) * 1000, 2) . 'ms'
            ],
            'database' => [
                'queries' => get_num_queries(),
                'query_time' => function_exists('timer_stop') ? timer_stop() . 's' : 'N/A'
            ]
        ];
    }
    
    /**
     * Analyze database tables
     */
    private function get_database_analysis() {
        global $wpdb;
        
        // Get WCEFP tables
        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}wcefp_%'");
        $table_analysis = [];
        
        foreach ($tables as $table) {
            $table_name = array_values((array)$table)[0];
            $status = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table_name'");
            
            $table_analysis[$table_name] = [
                'rows' => number_format($status->Rows ?? 0),
                'size' => size_format($status->Data_length + $status->Index_length),
                'engine' => $status->Engine ?? 'Unknown',
                'collation' => $status->Collation ?? 'Unknown'
            ];
        }
        
        return [
            'total_tables' => count($tables),
            'tables' => $table_analysis,
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate
        ];
    }
    
    /**
     * Perform basic security checks
     */
    private function perform_security_checks() {
        $checks = [];
        
        // Check if debug mode is enabled
        $checks['debug_mode'] = [
            'status' => defined('WP_DEBUG') && WP_DEBUG ? 'warning' : 'good',
            'value' => defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled',
            'description' => 'WordPress debug mode status'
        ];
        
        // Check file permissions
        $uploads_dir = wp_upload_dir();
        $checks['uploads_writable'] = [
            'status' => is_writable($uploads_dir['basedir']) ? 'good' : 'critical',
            'value' => is_writable($uploads_dir['basedir']) ? 'Writable' : 'Not writable', 
            'description' => 'Uploads directory permissions'
        ];
        
        // Check SSL
        $checks['ssl_enabled'] = [
            'status' => is_ssl() ? 'good' : 'warning',
            'value' => is_ssl() ? 'Enabled' : 'Disabled',
            'description' => 'SSL/HTTPS status'
        ];
        
        // Check API keys are not empty but don't reveal them
        $api_keys = [
            'google_places' => get_option('wcefp_google_places_api_key', ''),
            'brevo' => get_option('wcefp_brevo_api_key', '')
        ];
        
        foreach ($api_keys as $key => $value) {
            $checks["api_key_{$key}"] = [
                'status' => !empty($value) ? 'good' : 'warning',
                'value' => !empty($value) ? 'Configured' : 'Not configured',
                'description' => ucfirst($key) . ' API key status'
            ];
        }
        
        return $checks;
    }
    
    /**
     * Check plugin compatibility
     */
    private function check_plugin_compatibility() {
        $active_plugins = get_option('active_plugins', []);
        $compatibility_issues = [];
        
        // Known conflicting plugins
        $known_conflicts = [
            'wp-rocket/wp-rocket.php' => 'WP Rocket caching may interfere with dynamic content',
            'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache may cache shortcode output',
            'wp-super-cache/wp-super-cache.php' => 'WP Super Cache may affect dynamic content'
        ];
        
        foreach ($known_conflicts as $plugin => $issue) {
            if (in_array($plugin, $active_plugins)) {
                $compatibility_issues[] = [
                    'plugin' => $plugin,
                    'issue' => $issue,
                    'severity' => 'warning'
                ];
            }
        }
        
        // Check for required plugins
        $required_plugins = [
            'woocommerce/woocommerce.php' => 'WooCommerce is required for core functionality'
        ];
        
        foreach ($required_plugins as $plugin => $requirement) {
            if (!in_array($plugin, $active_plugins)) {
                $compatibility_issues[] = [
                    'plugin' => $plugin,
                    'issue' => $requirement,
                    'severity' => 'critical'
                ];
            }
        }
        
        return [
            'total_active_plugins' => count($active_plugins),
            'potential_conflicts' => count($compatibility_issues),
            'issues' => $compatibility_issues
        ];
    }
    
    /**
     * Render performance monitoring section
     */
    private function render_performance_section() {
        $performance_data = $this->get_performance_metrics();
        $shortcode_analysis = $this->get_shortcode_analysis();
        
        ?>
        <div class="wcefp-section">
            <h3><?php _e('Performance Metrics', 'wceventsfp'); ?></h3>
            
            <div class="wcefp-performance-grid">
                <div class="wcefp-performance-card">
                    <h4><?php _e('Memory Usage', 'wceventsfp'); ?></h4>
                    <div class="metric-value"><?php echo esc_html($performance_data['memory_usage']['current']); ?></div>
                    <div class="metric-label">
                        <?php printf(__('Peak: %s | Limit: %s', 'wceventsfp'), 
                            esc_html($performance_data['memory_usage']['peak']),
                            esc_html($performance_data['memory_usage']['limit'])
                        ); ?>
                    </div>
                </div>
                
                <div class="wcefp-performance-card">
                    <h4><?php _e('Database Queries', 'wceventsfp'); ?></h4>
                    <div class="metric-value"><?php echo esc_html($performance_data['database']['queries']); ?></div>
                    <div class="metric-label"><?php printf(__('Time: %s', 'wceventsfp'), esc_html($performance_data['database']['query_time'])); ?></div>
                </div>
                
                <div class="wcefp-performance-card">
                    <h4><?php _e('Shortcode Performance', 'wceventsfp'); ?></h4>
                    <div class="metric-value"><?php echo esc_html($shortcode_analysis['average_execution']); ?></div>
                    <div class="metric-label"><?php printf(__('Average across %d shortcodes', 'wceventsfp'), $shortcode_analysis['total_shortcodes']); ?></div>
                </div>
                
                <div class="wcefp-performance-card">
                    <h4><?php _e('Execution Time', 'wceventsfp'); ?></h4>
                    <div class="metric-value"><?php echo esc_html($performance_data['execution_time']['elapsed']); ?></div>
                    <div class="metric-label"><?php _e('Since request start', 'wceventsfp'); ?></div>
                </div>
            </div>
            
            <?php if (!empty($shortcode_analysis['performance'])): ?>
            <h4><?php _e('Shortcode Performance Details', 'wceventsfp'); ?></h4>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Shortcode', 'wceventsfp'); ?></th>
                        <th><?php _e('Execution Time', 'wceventsfp'); ?></th>
                        <th><?php _e('Status', 'wceventsfp'); ?></th>
                        <th><?php _e('Callback', 'wceventsfp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shortcode_analysis['performance'] as $shortcode => $data): ?>
                        <tr>
                            <td><code><?php echo esc_html($shortcode); ?></code></td>
                            <td><?php echo esc_html($data['execution_time']); ?></td>
                            <td>
                                <span class="status-indicator status-<?php echo esc_attr($data['status']); ?>">
                                    <?php echo esc_html(ucfirst($data['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($data['callback']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render database analysis section
     */
    private function render_database_section() {
        $database_data = $this->get_database_analysis();
        
        ?>
        <div class="wcefp-section">
            <h3><?php _e('Database Analysis', 'wceventsfp'); ?></h3>
            
            <div class="wcefp-database-summary">
                <div class="summary-stat">
                    <span class="stat-number"><?php echo esc_html($database_data['total_tables']); ?></span>
                    <span class="stat-label"><?php _e('WCEFP Tables', 'wceventsfp'); ?></span>
                </div>
                <div class="summary-stat">
                    <span class="stat-number"><?php echo esc_html($database_data['charset']); ?></span>
                    <span class="stat-label"><?php _e('Charset', 'wceventsfp'); ?></span>
                </div>
                <div class="summary-stat">
                    <span class="stat-number"><?php echo esc_html($database_data['collate']); ?></span>
                    <span class="stat-label"><?php _e('Collation', 'wceventsfp'); ?></span>
                </div>
            </div>
            
            <?php if (!empty($database_data['tables'])): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Table Name', 'wceventsfp'); ?></th>
                        <th><?php _e('Rows', 'wceventsfp'); ?></th>
                        <th><?php _e('Size', 'wceventsfp'); ?></th>
                        <th><?php _e('Engine', 'wceventsfp'); ?></th>
                        <th><?php _e('Collation', 'wceventsfp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($database_data['tables'] as $table_name => $info): ?>
                        <tr>
                            <td><code><?php echo esc_html($table_name); ?></code></td>
                            <td><?php echo esc_html($info['rows']); ?></td>
                            <td><?php echo esc_html($info['size']); ?></td>
                            <td><?php echo esc_html($info['engine']); ?></td>
                            <td><?php echo esc_html($info['collation']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="wcefp-notice notice-info">
                    <p><?php _e('No WCEFP database tables found.', 'wceventsfp'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render security audit section
     */
    private function render_security_section() {
        $security_checks = $this->perform_security_checks();
        
        ?>
        <div class="wcefp-section">
            <h3><?php _e('Security Audit', 'wceventsfp'); ?></h3>
            
            <div class="wcefp-security-overview">
                <?php 
                $good_count = count(array_filter($security_checks, function($check) { return $check['status'] === 'good'; }));
                $warning_count = count(array_filter($security_checks, function($check) { return $check['status'] === 'warning'; }));
                $critical_count = count(array_filter($security_checks, function($check) { return $check['status'] === 'critical'; }));
                ?>
                
                <div class="security-summary">
                    <div class="summary-item status-good">
                        <span class="count"><?php echo $good_count; ?></span>
                        <span class="label"><?php _e('Passed', 'wceventsfp'); ?></span>
                    </div>
                    <div class="summary-item status-warning">
                        <span class="count"><?php echo $warning_count; ?></span>
                        <span class="label"><?php _e('Warnings', 'wceventsfp'); ?></span>
                    </div>
                    <div class="summary-item status-critical">
                        <span class="count"><?php echo $critical_count; ?></span>
                        <span class="label"><?php _e('Critical', 'wceventsfp'); ?></span>
                    </div>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Security Check', 'wceventsfp'); ?></th>
                        <th><?php _e('Status', 'wceventsfp'); ?></th>
                        <th><?php _e('Value', 'wceventsfp'); ?></th>
                        <th><?php _e('Description', 'wceventsfp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($security_checks as $check_name => $check_data): ?>
                        <tr>
                            <td><?php echo esc_html(str_replace('_', ' ', ucwords($check_name))); ?></td>
                            <td>
                                <span class="status-indicator status-<?php echo esc_attr($check_data['status']); ?>">
                                    <?php echo esc_html(ucfirst($check_data['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($check_data['value']); ?></td>
                            <td><?php echo esc_html($check_data['description']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

// Initialize the diagnostics page if we're in the admin area
if (is_admin()) {
    new DiagnosticsPage();
}