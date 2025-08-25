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
                <button type="button" class="button button-secondary" id="wcefp-refresh-diagnostics">
                    <?php _e('Refresh Data', 'wceventsfp'); ?>
                </button>
                <span class="wcefp-last-updated">
                    <?php printf(__('Last updated: %s', 'wceventsfp'), current_time('Y-m-d H:i:s')); ?>
                </span>
            </div>
            
            <div class="wcefp-diagnostics-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#shortcodes" class="nav-tab nav-tab-active"><?php _e('Shortcodes', 'wceventsfp'); ?></a>
                    <a href="#hooks" class="nav-tab"><?php _e('Hooks', 'wceventsfp'); ?></a>
                    <a href="#endpoints" class="nav-tab"><?php _e('AJAX/REST', 'wceventsfp'); ?></a>
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
}

// Initialize the diagnostics page if we're in the admin area
if (is_admin()) {
    new DiagnosticsPage();
}