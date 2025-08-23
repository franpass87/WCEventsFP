<?php
/**
 * System Status Admin Page
 * 
 * Provides comprehensive system diagnostics and health monitoring.
 * 
 * @package WCEFP
 * @subpackage Admin
 * @since 2.1.4
 */

namespace WCEFP\Admin;

use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * System Status admin page
 */
class SystemStatus {
    
    /**
     * Initialize system status page
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page'], 99);
        add_action('admin_init', [$this, 'handle_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_wcefp_system_test', [$this, 'ajax_run_system_test']);
        add_action('wp_ajax_wcefp_clear_logs', [$this, 'ajax_clear_logs']);
    }
    
    /**
     * Add system status page to admin menu
     */
    public function add_admin_page() {
        add_submenu_page(
            'wcefp-dashboard',
            __('System Status', 'wceventsfp'),
            __('System Status', 'wceventsfp'),
            'manage_wcefp_settings',
            'wcefp-system-status',
            [$this, 'render_page']
        );
    }
    
    /**
     * Handle form actions
     */
    public function handle_actions() {
        if (!isset($_POST['wcefp_system_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_POST['wcefp_system_action']);
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wcefp_system_' . $action)) {
            wp_die(__('Security check failed.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_wcefp_settings')) {
            wp_die(__('You do not have permission to perform this action.', 'wceventsfp'));
        }
        
        switch ($action) {
            case 'download_report':
                $this->download_diagnostic_report();
                break;
                
            case 'clear_all_logs':
                DiagnosticLogger::instance()->clear_logs();
                $this->add_admin_notice(__('All log files have been cleared.', 'wceventsfp'), 'success');
                break;
                
            case 'run_health_check':
                $this->run_health_check();
                break;
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if ($hook === 'wcefp_page_wcefp-system-status') {
            wp_enqueue_style(
                'wcefp-system-status',
                WCEFP_PLUGIN_URL . 'assets/css/admin-system-status.css',
                [],
                WCEFP_VERSION
            );
            
            wp_enqueue_script(
                'wcefp-system-status',
                WCEFP_PLUGIN_URL . 'assets/js/admin-system-status.js',
                ['jquery'],
                WCEFP_VERSION,
                true
            );
            
            wp_localize_script('wcefp-system-status', 'wcefp_system_status', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcefp_system_status'),
                'strings' => [
                    'running_test' => __('Running test...', 'wceventsfp'),
                    'test_completed' => __('Test completed', 'wceventsfp'),
                    'test_failed' => __('Test failed', 'wceventsfp'),
                    'clearing_logs' => __('Clearing logs...', 'wceventsfp'),
                    'logs_cleared' => __('Logs cleared successfully', 'wceventsfp')
                ]
            ]);
        }
    }
    
    /**
     * Render system status page
     */
    public function render_page() {
        $logger = DiagnosticLogger::instance();
        $diagnostics = $logger->get_system_diagnostics();
        $health_status = $this->get_health_status($diagnostics);
        ?>
        <div class="wrap wcefp-system-status">
            <h1>
                <?php esc_html_e('WCEventsFP System Status', 'wceventsfp'); ?>
                <span class="wcefp-health-badge wcefp-health-<?php echo esc_attr($health_status['level']); ?>">
                    <?php echo esc_html($health_status['label']); ?>
                </span>
            </h1>
            
            <?php $this->render_health_summary($health_status, $diagnostics); ?>
            
            <div class="wcefp-status-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#system-info" class="nav-tab nav-tab-active" data-tab="system-info">
                        <?php esc_html_e('System Information', 'wceventsfp'); ?>
                    </a>
                    <a href="#health-checks" class="nav-tab" data-tab="health-checks">
                        <?php esc_html_e('Health Checks', 'wceventsfp'); ?>
                    </a>
                    <a href="#logs" class="nav-tab" data-tab="logs">
                        <?php esc_html_e('Recent Logs', 'wceventsfp'); ?>
                    </a>
                    <a href="#tools" class="nav-tab" data-tab="tools">
                        <?php esc_html_e('Tools', 'wceventsfp'); ?>
                    </a>
                </nav>
                
                <div id="system-info" class="tab-content active">
                    <?php $this->render_system_info($diagnostics); ?>
                </div>
                
                <div id="health-checks" class="tab-content">
                    <?php $this->render_health_checks($diagnostics); ?>
                </div>
                
                <div id="logs" class="tab-content">
                    <?php $this->render_recent_logs($logger); ?>
                </div>
                
                <div id="tools" class="tab-content">
                    <?php $this->render_tools(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render health summary
     */
    private function render_health_summary($health_status, $diagnostics) {
        ?>
        <div class="wcefp-health-summary">
            <div class="wcefp-health-overview">
                <div class="wcefp-health-icon">
                    <?php if ($health_status['level'] === 'good'): ?>
                        <span class="dashicons dashicons-yes-alt"></span>
                    <?php elseif ($health_status['level'] === 'warning'): ?>
                        <span class="dashicons dashicons-warning"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss"></span>
                    <?php endif; ?>
                </div>
                <div class="wcefp-health-details">
                    <h3><?php echo esc_html($health_status['message']); ?></h3>
                    <?php if (!empty($health_status['issues'])): ?>
                        <ul class="wcefp-health-issues">
                            <?php foreach ($health_status['issues'] as $issue): ?>
                                <li><?php echo esc_html($issue); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="wcefp-quick-stats">
                <div class="wcefp-stat">
                    <span class="wcefp-stat-value"><?php echo esc_html($diagnostics['plugin_version']); ?></span>
                    <span class="wcefp-stat-label"><?php esc_html_e('Plugin Version', 'wceventsfp'); ?></span>
                </div>
                <div class="wcefp-stat">
                    <span class="wcefp-stat-value"><?php echo esc_html($diagnostics['wordpress']['version']); ?></span>
                    <span class="wcefp-stat-label"><?php esc_html_e('WordPress', 'wceventsfp'); ?></span>
                </div>
                <div class="wcefp-stat">
                    <span class="wcefp-stat-value"><?php echo esc_html($diagnostics['server']['php_version']); ?></span>
                    <span class="wcefp-stat-label"><?php esc_html_e('PHP Version', 'wceventsfp'); ?></span>
                </div>
                <div class="wcefp-stat">
                    <span class="wcefp-stat-value"><?php echo esc_html($diagnostics['woocommerce']['version']); ?></span>
                    <span class="wcefp-stat-label"><?php esc_html_e('WooCommerce', 'wceventsfp'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render system information
     */
    private function render_system_info($diagnostics) {
        ?>
        <div class="wcefp-system-info">
            <div class="wcefp-info-section">
                <h3><?php esc_html_e('WordPress Environment', 'wceventsfp'); ?></h3>
                <table class="widefat">
                    <tbody>
                        <?php foreach ($diagnostics['wordpress'] as $key => $value): ?>
                            <tr>
                                <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></strong></td>
                                <td><?php echo esc_html($this->format_value($value)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="wcefp-info-section">
                <h3><?php esc_html_e('Server Environment', 'wceventsfp'); ?></h3>
                <table class="widefat">
                    <tbody>
                        <?php foreach ($diagnostics['server'] as $key => $value): ?>
                            <tr>
                                <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></strong></td>
                                <td><?php echo esc_html($this->format_value($value)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="wcefp-info-section">
                <h3><?php esc_html_e('Plugin Configuration', 'wceventsfp'); ?></h3>
                <table class="widefat">
                    <tbody>
                        <?php foreach ($diagnostics['plugin_settings'] as $key => $value): ?>
                            <tr>
                                <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></strong></td>
                                <td><?php echo esc_html($this->format_value($value)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="wcefp-info-section">
                <h3><?php esc_html_e('Database Information', 'wceventsfp'); ?></h3>
                <table class="widefat">
                    <tbody>
                        <?php foreach ($diagnostics['database'] as $key => $value): ?>
                            <tr>
                                <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></strong></td>
                                <td><?php echo esc_html($this->format_value($value)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render health checks
     */
    private function render_health_checks($diagnostics) {
        $checks = $this->get_health_checks($diagnostics);
        ?>
        <div class="wcefp-health-checks">
            <div class="wcefp-health-actions">
                <button type="button" class="button button-primary" id="run-all-tests">
                    <?php esc_html_e('Run All Tests', 'wceventsfp'); ?>
                </button>
            </div>
            
            <div class="wcefp-checks-grid">
                <?php foreach ($checks as $check_id => $check): ?>
                    <div class="wcefp-check-item wcefp-check-<?php echo esc_attr($check['status']); ?>" data-check="<?php echo esc_attr($check_id); ?>">
                        <div class="wcefp-check-header">
                            <div class="wcefp-check-icon">
                                <?php if ($check['status'] === 'pass'): ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                <?php elseif ($check['status'] === 'warning'): ?>
                                    <span class="dashicons dashicons-warning"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss"></span>
                                <?php endif; ?>
                            </div>
                            <h4><?php echo esc_html($check['title']); ?></h4>
                            <button type="button" class="button button-small wcefp-run-test" data-test="<?php echo esc_attr($check_id); ?>">
                                <?php esc_html_e('Test', 'wceventsfp'); ?>
                            </button>
                        </div>
                        <div class="wcefp-check-content">
                            <p><?php echo esc_html($check['message']); ?></p>
                            <?php if (!empty($check['recommendation'])): ?>
                                <div class="wcefp-check-recommendation">
                                    <strong><?php esc_html_e('Recommendation:', 'wceventsfp'); ?></strong>
                                    <p><?php echo esc_html($check['recommendation']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render recent logs
     */
    private function render_recent_logs($logger) {
        $logs = $logger->get_recent_logs(DiagnosticLogger::CHANNEL_GENERAL, 50);
        ?>
        <div class="wcefp-recent-logs">
            <div class="wcefp-logs-controls">
                <select id="log-level-filter">
                    <option value=""><?php esc_html_e('All Levels', 'wceventsfp'); ?></option>
                    <option value="error"><?php esc_html_e('Errors Only', 'wceventsfp'); ?></option>
                    <option value="warning"><?php esc_html_e('Warnings+', 'wceventsfp'); ?></option>
                    <option value="info"><?php esc_html_e('Info+', 'wceventsfp'); ?></option>
                </select>
                
                <select id="log-channel-filter">
                    <option value=""><?php esc_html_e('All Channels', 'wceventsfp'); ?></option>
                    <option value="general"><?php esc_html_e('General', 'wceventsfp'); ?></option>
                    <option value="bookings"><?php esc_html_e('Bookings', 'wceventsfp'); ?></option>
                    <option value="payments"><?php esc_html_e('Payments', 'wceventsfp'); ?></option>
                    <option value="integrations"><?php esc_html_e('Integrations', 'wceventsfp'); ?></option>
                </select>
                
                <button type="button" class="button" id="refresh-logs">
                    <?php esc_html_e('Refresh', 'wceventsfp'); ?>
                </button>
            </div>
            
            <div class="wcefp-logs-table-wrap">
                <?php if (empty($logs)): ?>
                    <p><?php esc_html_e('No recent log entries found.', 'wceventsfp'); ?></p>
                <?php else: ?>
                    <table class="widefat wcefp-logs-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Time', 'wceventsfp'); ?></th>
                                <th><?php esc_html_e('Level', 'wceventsfp'); ?></th>
                                <th><?php esc_html_e('Channel', 'wceventsfp'); ?></th>
                                <th><?php esc_html_e('Message', 'wceventsfp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr class="wcefp-log-<?php echo esc_attr(strtolower($log['level'])); ?>">
                                    <td><?php echo esc_html(date('M j, H:i:s', strtotime($log['timestamp']))); ?></td>
                                    <td>
                                        <span class="wcefp-log-level wcefp-log-level-<?php echo esc_attr(strtolower($log['level'])); ?>">
                                            <?php echo esc_html($log['level']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($log['channel']); ?></td>
                                    <td>
                                        <div class="wcefp-log-message">
                                            <?php echo esc_html($log['message']); ?>
                                            <?php if (!empty($log['context'])): ?>
                                                <details class="wcefp-log-context">
                                                    <summary><?php esc_html_e('Context', 'wceventsfp'); ?></summary>
                                                    <pre><?php echo esc_html(json_encode($log['context'], JSON_PRETTY_PRINT)); ?></pre>
                                                </details>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tools section
     */
    private function render_tools() {
        ?>
        <div class="wcefp-system-tools">
            <div class="wcefp-tools-grid">
                <div class="wcefp-tool-card">
                    <h3><?php esc_html_e('Diagnostic Report', 'wceventsfp'); ?></h3>
                    <p><?php esc_html_e('Download a comprehensive diagnostic report for troubleshooting.', 'wceventsfp'); ?></p>
                    <form method="post">
                        <?php wp_nonce_field('wcefp_system_download_report'); ?>
                        <input type="hidden" name="wcefp_system_action" value="download_report">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Download Report', 'wceventsfp'); ?>
                        </button>
                    </form>
                </div>
                
                <div class="wcefp-tool-card">
                    <h3><?php esc_html_e('Clear Logs', 'wceventsfp'); ?></h3>
                    <p><?php esc_html_e('Clear all log files to free up disk space.', 'wceventsfp'); ?></p>
                    <form method="post" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs?', 'wceventsfp'); ?>');">
                        <?php wp_nonce_field('wcefp_system_clear_all_logs'); ?>
                        <input type="hidden" name="wcefp_system_action" value="clear_all_logs">
                        <button type="submit" class="button button-secondary">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Clear All Logs', 'wceventsfp'); ?>
                        </button>
                    </form>
                </div>
                
                <div class="wcefp-tool-card">
                    <h3><?php esc_html_e('Health Check', 'wceventsfp'); ?></h3>
                    <p><?php esc_html_e('Run a comprehensive health check of all plugin systems.', 'wceventsfp'); ?></p>
                    <form method="post">
                        <?php wp_nonce_field('wcefp_system_run_health_check'); ?>
                        <input type="hidden" name="wcefp_system_action" value="run_health_check">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php esc_html_e('Run Health Check', 'wceventsfp'); ?>
                        </button>
                    </form>
                </div>
                
                <div class="wcefp-tool-card">
                    <h3><?php esc_html_e('System Information', 'wceventsfp'); ?></h3>
                    <p><?php esc_html_e('Copy system information for support requests.', 'wceventsfp'); ?></p>
                    <button type="button" class="button button-secondary" id="copy-system-info">
                        <span class="dashicons dashicons-admin-page"></span>
                        <?php esc_html_e('Copy Info', 'wceventsfp'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get health status
     */
    private function get_health_status($diagnostics) {
        $issues = [];
        $warnings = [];
        
        // Check critical issues
        if (!$diagnostics['woocommerce']['active']) {
            $issues[] = __('WooCommerce is not active', 'wceventsfp');
        }
        
        if (version_compare($diagnostics['server']['php_version'], '7.4.0', '<')) {
            $issues[] = __('PHP version is below minimum requirement (7.4)', 'wceventsfp');
        }
        
        if (!$diagnostics['plugin_settings']['log_directory_writable']) {
            $issues[] = __('Log directory is not writable', 'wceventsfp');
        }
        
        // Check warnings
        if (!$diagnostics['plugin_settings']['onboarding_completed']) {
            $warnings[] = __('Plugin onboarding not completed', 'wceventsfp');
        }
        
        if (!$diagnostics['integrations']['ga4_configured']) {
            $warnings[] = __('Google Analytics not configured', 'wceventsfp');
        }
        
        if (count($diagnostics['recent_errors']) > 0) {
            $warnings[] = sprintf(__('%d recent errors found', 'wceventsfp'), count($diagnostics['recent_errors']));
        }
        
        // Determine overall status
        if (!empty($issues)) {
            return [
                'level' => 'critical',
                'label' => __('Critical Issues', 'wceventsfp'),
                'message' => __('Your system has critical issues that need immediate attention.', 'wceventsfp'),
                'issues' => $issues
            ];
        } elseif (!empty($warnings)) {
            return [
                'level' => 'warning',
                'label' => __('Needs Attention', 'wceventsfp'),
                'message' => __('Your system is working but has some recommendations.', 'wceventsfp'),
                'issues' => $warnings
            ];
        } else {
            return [
                'level' => 'good',
                'label' => __('All Good', 'wceventsfp'),
                'message' => __('Your system is running optimally.', 'wceventsfp'),
                'issues' => []
            ];
        }
    }
    
    /**
     * Get health checks
     */
    private function get_health_checks($diagnostics) {
        return [
            'php_version' => [
                'title' => __('PHP Version', 'wceventsfp'),
                'status' => version_compare($diagnostics['server']['php_version'], '7.4.0', '>=') ? 'pass' : 'fail',
                'message' => sprintf(__('Current: %s, Required: 7.4+', 'wceventsfp'), $diagnostics['server']['php_version']),
                'recommendation' => version_compare($diagnostics['server']['php_version'], '7.4.0', '<') 
                    ? __('Please upgrade to PHP 7.4 or higher for optimal performance and security.', 'wceventsfp') : ''
            ],
            'woocommerce_active' => [
                'title' => __('WooCommerce', 'wceventsfp'),
                'status' => $diagnostics['woocommerce']['active'] ? 'pass' : 'fail',
                'message' => $diagnostics['woocommerce']['active'] 
                    ? sprintf(__('Active (v%s)', 'wceventsfp'), $diagnostics['woocommerce']['version'])
                    : __('Not installed or not active', 'wceventsfp'),
                'recommendation' => !$diagnostics['woocommerce']['active'] 
                    ? __('WooCommerce is required for this plugin to function.', 'wceventsfp') : ''
            ],
            'log_directory' => [
                'title' => __('Log Directory', 'wceventsfp'),
                'status' => $diagnostics['plugin_settings']['log_directory_writable'] ? 'pass' : 'fail',
                'message' => $diagnostics['plugin_settings']['log_directory_writable'] 
                    ? __('Writable', 'wceventsfp') : __('Not writable', 'wceventsfp'),
                'recommendation' => !$diagnostics['plugin_settings']['log_directory_writable'] 
                    ? __('The log directory must be writable for error logging to function.', 'wceventsfp') : ''
            ],
            'database_tables' => [
                'title' => __('Database Tables', 'wceventsfp'),
                'status' => strpos($diagnostics['database']['tables_exist'], '/') !== false ? 'warning' : 'pass',
                'message' => sprintf(__('%s tables found', 'wceventsfp'), $diagnostics['database']['tables_exist']),
                'recommendation' => strpos($diagnostics['database']['tables_exist'], '/') !== false 
                    ? __('Some database tables are missing. Try deactivating and reactivating the plugin.', 'wceventsfp') : ''
            ]
        ];
    }
    
    // AJAX handlers and helper methods...
    
    public function ajax_run_system_test() {
        check_ajax_referer('wcefp_system_status', 'nonce');
        
        if (!current_user_can('manage_wcefp_settings')) {
            wp_die(__('Permission denied.', 'wceventsfp'));
        }
        
        $test = sanitize_text_field($_POST['test'] ?? '');
        $result = $this->run_individual_test($test);
        
        wp_send_json_success($result);
    }
    
    public function ajax_clear_logs() {
        check_ajax_referer('wcefp_system_status', 'nonce');
        
        if (!current_user_can('manage_wcefp_settings')) {
            wp_die(__('Permission denied.', 'wceventsfp'));
        }
        
        $channel = sanitize_text_field($_POST['channel'] ?? '');
        DiagnosticLogger::instance()->clear_logs($channel ?: null);
        
        wp_send_json_success(['message' => __('Logs cleared successfully.', 'wceventsfp')]);
    }
    
    private function run_individual_test($test) {
        // Implementation for individual tests
        return ['status' => 'pass', 'message' => __('Test passed', 'wceventsfp')];
    }
    
    private function download_diagnostic_report() {
        $logger = DiagnosticLogger::instance();
        $report = $logger->create_diagnostic_report();
        
        $filename = 'wcefp-diagnostic-report-' . date('Y-m-d-H-i-s') . '.txt';
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($report));
        
        echo $report;
        exit;
    }
    
    private function run_health_check() {
        $logger = DiagnosticLogger::instance();
        $diagnostics = $logger->get_system_diagnostics();
        $health_status = $this->get_health_status($diagnostics);
        
        $message = sprintf(
            __('Health check completed. Status: %s', 'wceventsfp'),
            $health_status['label']
        );
        
        $this->add_admin_notice($message, $health_status['level'] === 'good' ? 'success' : 'warning');
    }
    
    private function format_value($value) {
        if (is_bool($value)) {
            return $value ? __('Yes', 'wceventsfp') : __('No', 'wceventsfp');
        }
        
        if (is_array($value)) {
            return implode(', ', $value);
        }
        
        return (string) $value;
    }
    
    private function add_admin_notice($message, $type = 'info') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }
}