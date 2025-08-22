<?php
/**
 * Feature Management Dashboard
 * 
 * @package WCEFP
 * @subpackage Admin
 * @since 2.1.0
 */

namespace WCEFP\Admin;

use WCEFP\Core\InstallationManager;
use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages feature enabling/disabling and provides performance insights
 */
class FeatureManager {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_wcefp_toggle_feature', [$this, 'ajax_toggle_feature']);
        add_action('wp_ajax_wcefp_run_wizard', [$this, 'ajax_run_wizard']);
        add_action('wp_ajax_wcefp_reset_installation', [$this, 'ajax_reset_installation']);
    }
    
    /**
     * Add admin menu
     * 
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
            __('WCEventsFP Dashboard', 'wceventsfp'),
            __('WCEventsFP', 'wceventsfp'),
            'manage_options',
            'wcefp',
            [$this, 'render_dashboard'],
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'wcefp',
            __('Feature Manager', 'wceventsfp'),
            __('Features', 'wceventsfp'),
            'manage_options',
            'wcefp-features',
            [$this, 'render_feature_manager']
        );
        
        add_submenu_page(
            'wcefp',
            __('Performance Monitor', 'wceventsfp'),
            __('Performance', 'wceventsfp'),
            'manage_options',
            'wcefp-performance',
            [$this, 'render_performance_monitor']
        );
        
        add_submenu_page(
            'wcefp',
            __('Installation Status', 'wceventsfp'),
            __('Installation', 'wceventsfp'),
            'manage_options',
            'wcefp-installation',
            [$this, 'render_installation_status']
        );
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook
     * @return void
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'wcefp') === false) {
            return;
        }
        
        wp_enqueue_style(
            'wcefp-feature-manager',
            WCEFP_PLUGIN_URL . 'assets/css/feature-manager.css',
            [],
            WCEFP_VERSION
        );
        
        wp_enqueue_script(
            'wcefp-feature-manager',
            WCEFP_PLUGIN_URL . 'assets/js/feature-manager.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-feature-manager', 'wcefp_feature_manager', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_feature_manager'),
            'strings' => [
                'confirm_disable' => __('Are you sure you want to disable this feature?', 'wceventsfp'),
                'confirm_reset' => __('Are you sure you want to reset the installation? This will disable all features and require running the setup wizard again.', 'wceventsfp'),
                'loading' => __('Loading...', 'wceventsfp'),
                'error' => __('An error occurred. Please try again.', 'wceventsfp')
            ]
        ]);
    }
    
    /**
     * Render main dashboard
     * 
     * @return void
     */
    public function render_dashboard() {
        $installation_manager = new InstallationManager();
        $performance_score = $this->calculate_performance_score();
        $enabled_features = $installation_manager->get_enabled_features();
        $installation_status = $installation_manager->get_installation_status();
        
        ?>
        <div class="wrap">
            <h1><?php _e('WCEventsFP Dashboard', 'wceventsfp'); ?></h1>
            
            <!-- Status Overview -->
            <div class="wcefp-dashboard-grid">
                <div class="wcefp-status-card">
                    <h3><?php _e('Installation Status', 'wceventsfp'); ?></h3>
                    <div class="status-indicator status-<?php echo esc_attr($installation_status); ?>">
                        <?php echo $this->get_status_display($installation_status); ?>
                    </div>
                </div>
                
                <div class="wcefp-status-card">
                    <h3><?php _e('Performance Score', 'wceventsfp'); ?></h3>
                    <div class="performance-score">
                        <span class="score-number"><?php echo $performance_score; ?></span>
                        <span class="score-label">/100</span>
                        <div class="score-bar">
                            <div class="score-fill" style="width: <?php echo $performance_score; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <div class="wcefp-status-card">
                    <h3><?php _e('Active Features', 'wceventsfp'); ?></h3>
                    <div class="features-count">
                        <span class="count"><?php echo count($enabled_features); ?></span>
                        <span class="label"><?php _e('Features', 'wceventsfp'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="wcefp-quick-actions">
                <h2><?php _e('Quick Actions', 'wceventsfp'); ?></h2>
                <div class="action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=wcefp-features'); ?>" class="button button-primary">
                        <?php _e('Manage Features', 'wceventsfp'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wcefp-performance'); ?>" class="button">
                        <?php _e('Performance Monitor', 'wceventsfp'); ?>
                    </a>
                    <?php if ($installation_manager->needs_setup_wizard() || $installation_status !== 'completed'): ?>
                    <a href="<?php echo $installation_manager->get_setup_wizard_url(); ?>" class="button button-secondary">
                        <?php _e('Run Setup Wizard', 'wceventsfp'); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="wcefp-recent-activity">
                <h2><?php _e('Recent Activity', 'wceventsfp'); ?></h2>
                <?php $this->render_recent_activity(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render feature manager
     * 
     * @return void
     */
    public function render_feature_manager() {
        $installation_manager = new InstallationManager();
        $all_features = $this->get_all_features();
        $enabled_features = $installation_manager->get_enabled_features();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Feature Manager', 'wceventsfp'); ?></h1>
            <p><?php _e('Enable or disable plugin features. Changes take effect immediately.', 'wceventsfp'); ?></p>
            
            <div class="wcefp-feature-grid">
                <?php foreach ($all_features as $feature_key => $feature): ?>
                <div class="wcefp-feature-card <?php echo in_array($feature_key, $enabled_features) ? 'enabled' : 'disabled'; ?>" 
                     data-feature="<?php echo esc_attr($feature_key); ?>">
                    <div class="feature-header">
                        <h3><?php echo esc_html($feature['name']); ?></h3>
                        <div class="feature-toggle">
                            <label class="switch">
                                <input type="checkbox" 
                                       <?php checked(in_array($feature_key, $enabled_features)); ?>
                                       <?php disabled($feature['required']); ?>
                                       data-feature="<?php echo esc_attr($feature_key); ?>">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="feature-content">
                        <p class="feature-description"><?php echo esc_html($feature['description']); ?></p>
                        
                        <div class="feature-meta">
                            <span class="impact impact-<?php echo esc_attr(strtolower($feature['impact'])); ?>">
                                <?php printf(__('Impact: %s', 'wceventsfp'), $feature['impact']); ?>
                            </span>
                            
                            <?php if (!empty($feature['dependencies'])): ?>
                            <span class="dependencies">
                                <?php printf(__('Requires: %s', 'wceventsfp'), implode(', ', $feature['dependencies'])); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($feature['required']): ?>
                        <div class="required-notice">
                            <?php _e('This feature is required and cannot be disabled.', 'wceventsfp'); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
            .wcefp-feature-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            .wcefp-feature-card {
                background: white;
                border: 2px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                transition: all 0.3s ease;
            }
            .wcefp-feature-card.enabled {
                border-color: #0073aa;
                background: #f8f9fa;
            }
            .wcefp-feature-card.disabled {
                opacity: 0.7;
            }
            .feature-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
            }
            .feature-header h3 {
                margin: 0;
                color: #333;
            }
            .feature-description {
                margin: 10px 0;
                color: #666;
                line-height: 1.5;
            }
            .feature-meta {
                margin: 15px 0;
            }
            .feature-meta span {
                display: inline-block;
                margin-right: 15px;
                font-size: 12px;
            }
            .impact {
                padding: 3px 8px;
                border-radius: 12px;
                font-weight: bold;
            }
            .impact-low { background: #d4edda; color: #155724; }
            .impact-medium { background: #fff3cd; color: #856404; }
            .impact-high { background: #f8d7da; color: #721c24; }
            .dependencies {
                color: #666;
                font-style: italic;
            }
            .required-notice {
                background: #e3f2fd;
                border: 1px solid #2196f3;
                border-radius: 4px;
                padding: 8px;
                font-size: 12px;
                color: #1976d2;
            }
            
            /* Toggle Switch Styles */
            .switch {
                position: relative;
                display: inline-block;
                width: 60px;
                height: 34px;
            }
            .switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                transition: .4s;
                border-radius: 34px;
            }
            .slider:before {
                position: absolute;
                content: "";
                height: 26px;
                width: 26px;
                left: 4px;
                bottom: 4px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
            }
            input:checked + .slider {
                background-color: #0073aa;
            }
            input:focus + .slider {
                box-shadow: 0 0 1px #0073aa;
            }
            input:checked + .slider:before {
                transform: translateX(26px);
            }
            input:disabled + .slider {
                background-color: #e0e0e0;
                cursor: not-allowed;
            }
        </style>
        
        <script>
            jQuery(document).ready(function($) {
                $('.wcefp-feature-card input[type="checkbox"]').change(function() {
                    const feature = $(this).data('feature');
                    const enabled = $(this).is(':checked');
                    const card = $(this).closest('.wcefp-feature-card');
                    
                    // Update UI immediately
                    card.toggleClass('enabled', enabled);
                    card.toggleClass('disabled', !enabled);
                    
                    // Send AJAX request
                    $.ajax({
                        url: wcefp_feature_manager.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'wcefp_toggle_feature',
                            feature: feature,
                            enabled: enabled ? 1 : 0,
                            nonce: wcefp_feature_manager.nonce
                        },
                        success: function(response) {
                            if (!response.success) {
                                alert(response.data || wcefp_feature_manager.strings.error);
                                // Revert UI changes
                                $(this).prop('checked', !enabled);
                                card.toggleClass('enabled', !enabled);
                                card.toggleClass('disabled', enabled);
                            }
                        },
                        error: function() {
                            alert(wcefp_feature_manager.strings.error);
                            // Revert UI changes
                            $(this).prop('checked', !enabled);
                            card.toggleClass('enabled', !enabled);
                            card.toggleClass('disabled', enabled);
                        }
                    });
                });
            });
        </script>
        <?php
    }
    
    /**
     * Render performance monitor
     * 
     * @return void
     */
    public function render_performance_monitor() {
        $performance_data = $this->get_performance_data();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Performance Monitor', 'wceventsfp'); ?></h1>
            <p><?php _e('Monitor plugin performance and resource usage.', 'wceventsfp'); ?></p>
            
            <?php $this->render_performance_overview($performance_data); ?>
            <?php $this->render_feature_performance($performance_data); ?>
            <?php $this->render_server_recommendations(); ?>
        </div>
        <?php
    }
    
    /**
     * Render installation status
     * 
     * @return void
     */
    public function render_installation_status() {
        $installation_manager = new InstallationManager();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Installation Status', 'wceventsfp'); ?></h1>
            
            <div class="wcefp-installation-info">
                <table class="form-table">
                    <tr>
                        <th><?php _e('Current Status', 'wceventsfp'); ?></th>
                        <td><?php echo $this->get_status_display($installation_manager->get_installation_status()); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Installation Mode', 'wceventsfp'); ?></th>
                        <td><?php echo esc_html($installation_manager->get_installation_mode()); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Enabled Features', 'wceventsfp'); ?></th>
                        <td><?php echo implode(', ', $installation_manager->get_enabled_features()); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Plugin Version', 'wceventsfp'); ?></th>
                        <td><?php echo esc_html(get_option('wcefp_version', 'Unknown')); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="wcefp-installation-actions">
                <h2><?php _e('Installation Actions', 'wceventsfp'); ?></h2>
                
                <div class="action-buttons">
                    <a href="<?php echo $installation_manager->get_setup_wizard_url(); ?>" class="button button-secondary">
                        <?php _e('Run Setup Wizard Again', 'wceventsfp'); ?>
                    </a>
                    
                    <button type="button" class="button button-secondary" id="reset-installation">
                        <?php _e('Reset Installation', 'wceventsfp'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                $('#reset-installation').click(function() {
                    if (confirm(wcefp_feature_manager.strings.confirm_reset)) {
                        $.ajax({
                            url: wcefp_feature_manager.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'wcefp_reset_installation',
                                nonce: wcefp_feature_manager.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert(response.data || wcefp_feature_manager.strings.error);
                                }
                            },
                            error: function() {
                                alert(wcefp_feature_manager.strings.error);
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for toggling features
     * 
     * @return void
     */
    public function ajax_toggle_feature() {
        check_ajax_referer('wcefp_feature_manager', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wceventsfp'));
        }
        
        $feature = sanitize_key($_POST['feature'] ?? '');
        $enabled = (bool) ($_POST['enabled'] ?? false);
        
        if (empty($feature)) {
            wp_send_json_error(__('Invalid feature', 'wceventsfp'));
        }
        
        try {
            $installation_manager = new InstallationManager();
            $current_features = $installation_manager->get_enabled_features();
            
            if ($enabled && !in_array($feature, $current_features)) {
                $current_features[] = $feature;
            } elseif (!$enabled && in_array($feature, $current_features)) {
                $current_features = array_diff($current_features, [$feature]);
            }
            
            update_option('wcefp_selected_features', array_values($current_features));
            
            Logger::info("Feature {$feature} " . ($enabled ? 'enabled' : 'disabled') . " by user " . get_current_user_id());
            
            wp_send_json_success();
            
        } catch (Exception $e) {
            Logger::error("Failed to toggle feature {$feature}: " . $e->getMessage());
            wp_send_json_error(__('Failed to update feature', 'wceventsfp'));
        }
    }
    
    /**
     * AJAX handler for running wizard
     * 
     * @return void
     */
    public function ajax_run_wizard() {
        check_ajax_referer('wcefp_feature_manager', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wceventsfp'));
        }
        
        $installation_manager = new InstallationManager();
        $installation_manager->force_wizard_mode();
        
        wp_send_json_success(['redirect' => $installation_manager->get_setup_wizard_url()]);
    }
    
    /**
     * AJAX handler for resetting installation
     * 
     * @return void
     */
    public function ajax_reset_installation() {
        check_ajax_referer('wcefp_feature_manager', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wceventsfp'));
        }
        
        try {
            $installation_manager = new InstallationManager();
            $installation_manager->reset_installation();
            
            Logger::info("Installation reset by user " . get_current_user_id());
            
            wp_send_json_success();
            
        } catch (Exception $e) {
            Logger::error("Failed to reset installation: " . $e->getMessage());
            wp_send_json_error(__('Failed to reset installation', 'wceventsfp'));
        }
    }
    
    /**
     * Get all available features
     * 
     * @return array
     */
    private function get_all_features() {
        return [
            'core' => [
                'name' => __('Core Booking System', 'wceventsfp'),
                'description' => __('Essential booking functionality, basic product types, and WooCommerce integration.', 'wceventsfp'),
                'impact' => __('Low', 'wceventsfp'),
                'dependencies' => ['WooCommerce'],
                'required' => true
            ],
            'admin_enhanced' => [
                'name' => __('Enhanced Admin Panel', 'wceventsfp'),
                'description' => __('Advanced admin interface with reporting, analytics dashboard, and management tools.', 'wceventsfp'),
                'impact' => __('Medium', 'wceventsfp'),
                'dependencies' => [],
                'required' => false
            ],
            'resources' => [
                'name' => __('Resource Management', 'wceventsfp'),
                'description' => __('Manage guides, equipment, vehicles and other bookable resources.', 'wceventsfp'),
                'impact' => __('Medium', 'wceventsfp'),
                'dependencies' => [],
                'required' => false
            ],
            'channels' => [
                'name' => __('Distribution Channels', 'wceventsfp'),
                'description' => __('Integration with Booking.com, Expedia, GetYourGuide and other booking platforms.', 'wceventsfp'),
                'impact' => __('High', 'wceventsfp'),
                'dependencies' => ['Core Booking System'],
                'required' => false
            ],
            'commissions' => [
                'name' => __('Commission System', 'wceventsfp'),
                'description' => __('Handle commissions, reseller management, and revenue sharing.', 'wceventsfp'),
                'impact' => __('Medium', 'wceventsfp'),
                'dependencies' => [],
                'required' => false
            ],
            'reviews' => [
                'name' => __('Google Reviews Integration', 'wceventsfp'),
                'description' => __('Automate Google Reviews collection and management.', 'wceventsfp'),
                'impact' => __('Low', 'wceventsfp'),
                'dependencies' => [],
                'required' => false
            ],
            'tracking' => [
                'name' => __('Advanced Analytics', 'wceventsfp'),
                'description' => __('GA4, Meta Pixel, and advanced conversion tracking.', 'wceventsfp'),
                'impact' => __('Medium', 'wceventsfp'),
                'dependencies' => [],
                'required' => false
            ],
            'automation' => [
                'name' => __('Marketing Automation', 'wceventsfp'),
                'description' => __('Brevo integration, email campaigns, and customer automation.', 'wceventsfp'),
                'impact' => __('Medium', 'wceventsfp'),
                'dependencies' => [],
                'required' => false
            ],
            'ai_recommendations' => [
                'name' => __('AI Recommendations', 'wceventsfp'),
                'description' => __('Machine learning powered product recommendations and smart pricing.', 'wceventsfp'),
                'impact' => __('High', 'wceventsfp'),
                'dependencies' => ['Advanced Analytics'],
                'required' => false
            ],
            'realtime' => [
                'name' => __('Real-time Features', 'wceventsfp'),
                'description' => __('Live availability updates, real-time notifications, and websocket connections.', 'wceventsfp'),
                'impact' => __('High', 'wceventsfp'),
                'dependencies' => [],
                'required' => false
            ]
        ];
    }
    
    /**
     * Calculate overall performance score
     * 
     * @return int
     */
    private function calculate_performance_score() {
        $score = 0;
        
        // PHP version (25 points)
        if (version_compare(PHP_VERSION, '8.0', '>=')) {
            $score += 25;
        } elseif (version_compare(PHP_VERSION, '7.4', '>=')) {
            $score += 20;
        }
        
        // Memory limit (25 points)
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit === '-1') {
            $score += 25;
        } else {
            $memory_bytes = $this->convert_memory_to_bytes($memory_limit);
            if ($memory_bytes >= 536870912) {
                $score += 25;
            } elseif ($memory_bytes >= 268435456) {
                $score += 20;
            } elseif ($memory_bytes >= 134217728) {
                $score += 10;
            }
        }
        
        // Object caching (15 points)
        if (wp_using_ext_object_cache()) {
            $score += 15;
        }
        
        // WooCommerce active (10 points)
        if (class_exists('WooCommerce')) {
            $score += 10;
        }
        
        // Extension availability (15 points)
        $extensions = ['curl', 'json', 'mbstring', 'mysqli'];
        $loaded = 0;
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) $loaded++;
        }
        $score += ($loaded / count($extensions)) * 15;
        
        // Execution time (10 points)
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time == 0 || $max_execution_time >= 60) {
            $score += 10;
        } elseif ($max_execution_time >= 30) {
            $score += 5;
        }
        
        return min(100, round($score));
    }
    
    /**
     * Convert memory limit to bytes
     * 
     * @param string $val
     * @return int
     */
    private function convert_memory_to_bytes($val) {
        $val = trim($val);
        if (empty($val)) return 0;
        
        $unit = strtolower(substr($val, -1));
        $val = (int) $val;
        
        switch ($unit) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
    
    /**
     * Get status display text
     * 
     * @param string $status
     * @return string
     */
    private function get_status_display($status) {
        $statuses = [
            'completed' => '✅ ' . __('Fully Installed', 'wceventsfp'),
            'in_progress' => '🔄 ' . __('Installation in Progress', 'wceventsfp'),
            'wizard_required' => '🧙 ' . __('Setup Wizard Required', 'wceventsfp'),
            'not_started' => '❓ ' . __('Not Started', 'wceventsfp'),
            'failed' => '❌ ' . __('Installation Failed', 'wceventsfp'),
            'minimal_complete' => '⚠️ ' . __('Minimal Mode Active', 'wceventsfp')
        ];
        
        return $statuses[$status] ?? $status;
    }
    
    /**
     * Get performance data
     * 
     * @return array
     */
    private function get_performance_data() {
        return [
            'overall_score' => $this->calculate_performance_score(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'execution_time' => ini_get('max_execution_time'),
            'php_version' => PHP_VERSION
        ];
    }
    
    /**
     * Render performance overview
     * 
     * @param array $data
     * @return void
     */
    private function render_performance_overview($data) {
        ?>
        <div class="wcefp-performance-overview">
            <h2><?php _e('Performance Overview', 'wceventsfp'); ?></h2>
            <!-- Add performance overview content -->
        </div>
        <?php
    }
    
    /**
     * Render feature performance
     * 
     * @param array $data
     * @return void
     */
    private function render_feature_performance($data) {
        ?>
        <div class="wcefp-feature-performance">
            <h2><?php _e('Feature Performance Impact', 'wceventsfp'); ?></h2>
            <!-- Add feature performance content -->
        </div>
        <?php
    }
    
    /**
     * Render server recommendations
     * 
     * @return void
     */
    private function render_server_recommendations() {
        ?>
        <div class="wcefp-server-recommendations">
            <h2><?php _e('Server Recommendations', 'wceventsfp'); ?></h2>
            <!-- Add server recommendations content -->
        </div>
        <?php
    }
    
    /**
     * Render recent activity
     * 
     * @return void
     */
    private function render_recent_activity() {
        ?>
        <div class="activity-list">
            <p><?php _e('No recent activity to show.', 'wceventsfp'); ?></p>
        </div>
        <?php
    }
}