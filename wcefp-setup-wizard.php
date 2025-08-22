<?php
/**
 * WCEventsFP Setup Wizard - Optimized Version
 * 
 * Interactive installation wizard to prevent WSOD by allowing users to 
 * configure the plugin step-by-step with progressive feature loading.
 * 
 * @package WCEventsFP
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Prevent direct access unless called properly
if (!isset($_GET['wcefp_setup']) && !defined('WCEFP_SETUP_WIZARD_ACTIVE')) {
    wp_die('Setup wizard must be accessed through WordPress admin', 'WCEventsFP Setup');
}

// Load required utilities
require_once WCEFP_PLUGIN_DIR . 'includes/Utils/Environment.php';

use WCEFP\Utils\Environment;

/**
 * WCEventsFP Setup Wizard Class
 */
class WCEFP_Setup_Wizard {
    
    /**
     * Current setup step
     * @var string
     */
    private $current_step = 'welcome';
    
    /**
     * Setup steps configuration
     * @var array
     */
    private $steps = [
        'welcome' => 'Welcome & Environment Check',
        'requirements' => 'System Requirements',
        'features' => 'Feature Selection',
        'performance' => 'Performance Configuration', 
        'activation' => 'Plugin Activation',
        'complete' => 'Setup Complete'
    ];
    
    /**
     * Environment test results (cached)
     * @var array
     */
    private $environment_tests = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->current_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'welcome';
        $this->handle_form_submission();
    }
    
    /**
     * Render the setup wizard
     * @return void
     */
    public function render() {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php _e('WCEventsFP Setup Wizard', 'wceventsfp'); ?></title>
            <link rel="stylesheet" href="<?php echo WCEFP_PLUGIN_URL; ?>assets/css/setup-wizard.css?v=<?php echo WCEFP_VERSION; ?>">
        </head>
        <body class="wcefp-setup-wizard">
            <div class="wcefp-setup-container">
                <?php $this->render_header(); ?>
                <?php $this->render_progress_bar(); ?>
                <?php $this->render_current_step(); ?>
            </div>
            
            <script src="<?php echo WCEFP_PLUGIN_URL; ?>assets/js/setup-wizard.js?v=<?php echo WCEFP_VERSION; ?>"></script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render wizard header
     * @return void
     */
    private function render_header() {
        ?>
        <div class="wcefp-setup-header">
            <h1><?php _e('WCEventsFP Setup Wizard', 'wceventsfp'); ?></h1>
            <p><?php _e('Safe, step-by-step plugin configuration to prevent WSOD', 'wceventsfp'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Render progress bar
     * @return void
     */
    private function render_progress_bar() {
        ?>
        <div class="wcefp-progress-bar">
            <ul class="wcefp-progress-steps">
                <?php foreach ($this->steps as $step_key => $step_name): ?>
                    <li class="wcefp-progress-step <?php 
                        echo $this->get_step_class($step_key); 
                    ?>">
                        <?php echo esc_html($step_name); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Get CSS class for step based on current progress
     * @param string $step_key
     * @return string
     */
    private function get_step_class($step_key) {
        $step_keys = array_keys($this->steps);
        $current_index = array_search($this->current_step, $step_keys);
        $step_index = array_search($step_key, $step_keys);
        
        if ($step_index < $current_index) {
            return 'completed';
        } elseif ($step_index === $current_index) {
            return 'active';
        } else {
            return '';
        }
    }
    
    /**
     * Render current step content
     * @return void
     */
    private function render_current_step() {
        switch ($this->current_step) {
            case 'welcome':
                $this->render_welcome_step();
                break;
            case 'requirements':
                $this->render_requirements_step();
                break;
            case 'features':
                $this->render_features_step();
                break;
            case 'performance':
                $this->render_performance_step();
                break;
            case 'activation':
                $this->render_activation_step();
                break;
            case 'complete':
                $this->render_complete_step();
                break;
            default:
                $this->render_welcome_step();
        }
    }
    
    /**
     * Render welcome step
     * @return void
     */
    private function render_welcome_step() {
        // Get environment tests using the shared utility
        $this->environment_tests = Environment::run_full_tests();
        $can_install = Environment::is_installation_possible();
        $recommended_mode = Environment::get_recommended_mode();
        
        ?>
        <div class="wcefp-step-content">
            <h2><?php _e('🚀 Welcome to WCEventsFP!', 'wceventsfp'); ?></h2>
            <p><?php _e('This setup wizard will safely configure your plugin to prevent White Screen of Death (WSOD) issues.', 'wceventsfp'); ?></p>
            
            <h3><?php _e('Environment Check', 'wceventsfp'); ?></h3>
            
            <?php foreach ($this->environment_tests as $test): ?>
                <div class="wcefp-test-result <?php echo esc_attr($test['status']); ?>">
                    <span><?php echo esc_html($test['name']); ?>: <?php echo esc_html($test['message']); ?></span>
                    <span><?php echo $this->get_status_icon($test['status']); ?></span>
                </div>
            <?php endforeach; ?>
            
            <?php if ($can_install): ?>
                <div class="wcefp-recommendation">
                    <h4><?php _e('✅ Ready to Install!', 'wceventsfp'); ?></h4>
                    <p><?php printf(__('Recommended installation mode: <strong>%s</strong>', 'wceventsfp'), ucfirst($recommended_mode)); ?></p>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="<?php echo $this->get_step_url('requirements'); ?>" class="wcefp-button success">
                        <?php _e('Continue Setup', 'wceventsfp'); ?> →
                    </a>
                </div>
            <?php else: ?>
                <div class="wcefp-test-result error">
                    <span><?php _e('❌ Cannot proceed - Critical requirements not met', 'wceventsfp'); ?></span>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="button" class="wcefp-button secondary" onclick="location.reload();">
                        <?php _e('↻ Re-check', 'wceventsfp'); ?>
                    </button>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="wcefp-button">
                        <?php _e('← Back to Plugins', 'wceventsfp'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render requirements step
     * @return void
     */
    private function render_requirements_step() {
        $performance_score = Environment::get_performance_score();
        
        ?>
        <div class="wcefp-step-content">
            <h2><?php _e('📊 System Performance Analysis', 'wceventsfp'); ?></h2>
            <p><?php _e('Based on your server environment, here are our recommendations:', 'wceventsfp'); ?></p>
            
            <div class="performance-score" style="text-align: center; margin: 30px 0;">
                <div class="score-number"><?php echo $performance_score; ?>/100</div>
                <div class="score-label"><?php _e('Performance Score', 'wceventsfp'); ?></div>
                <div class="score-bar">
                    <div class="score-fill" style="width: <?php echo $performance_score; ?>%;"></div>
                </div>
            </div>
            
            <?php $this->render_recommendations($performance_score); ?>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="<?php echo $this->get_step_url('features'); ?>" class="wcefp-button success">
                    <?php _e('Select Features', 'wceventsfp'); ?> →
                </a>
                <a href="<?php echo $this->get_step_url('welcome'); ?>" class="wcefp-button secondary">
                    ← <?php _e('Back', 'wceventsfp'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render features selection step
     * @return void
     */
    private function render_features_step() {
        ?>
        <div class="wcefp-step-content">
            <h2><?php _e('🎛️ Feature Selection', 'wceventsfp'); ?></h2>
            <p><?php _e('Choose which features to enable. You can always change this later in the dashboard.', 'wceventsfp'); ?></p>
            
            <form method="post" action="<?php echo $this->get_step_url('performance'); ?>">
                <?php wp_nonce_field('wcefp_setup_wizard', 'wcefp_setup_nonce'); ?>
                <input type="hidden" name="step" value="features">
                
                <?php $this->render_feature_options(); ?>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="wcefp-button success">
                        <?php _e('Configure Performance', 'wceventsfp'); ?> →
                    </button>
                    <a href="<?php echo $this->get_step_url('requirements'); ?>" class="wcefp-button secondary">
                        ← <?php _e('Back', 'wceventsfp'); ?>
                    </a>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render performance configuration step
     * @return void
     */
    private function render_performance_step() {
        $recommended_mode = Environment::get_recommended_mode();
        
        ?>
        <div class="wcefp-step-content">
            <h2><?php _e('⚡ Performance Configuration', 'wceventsfp'); ?></h2>
            <p><?php _e('Configure how the plugin should load to optimize performance for your server.', 'wceventsfp'); ?></p>
            
            <form method="post" action="<?php echo $this->get_step_url('activation'); ?>">
                <?php wp_nonce_field('wcefp_setup_wizard', 'wcefp_setup_nonce'); ?>
                <input type="hidden" name="step" value="activation">
                
                <?php $this->render_loading_mode_options($recommended_mode); ?>
                <?php $this->render_performance_options(); ?>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="wcefp-button success">
                        <?php _e('Activate Plugin', 'wceventsfp'); ?> →
                    </button>
                    <a href="<?php echo $this->get_step_url('features'); ?>" class="wcefp-button secondary">
                        ← <?php _e('Back', 'wceventsfp'); ?>
                    </a>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render activation step
     * @return void
     */
    private function render_activation_step() {
        ?>
        <div class="wcefp-step-content">
            <h2><?php _e('🚀 Plugin Activation', 'wceventsfp'); ?></h2>
            <p><?php _e('Activating your plugin with the selected configuration...', 'wceventsfp'); ?></p>
            
            <div class="progress-container">
                <div class="progress-bar">
                    <div id="progress-fill" class="progress-fill"></div>
                </div>
                <div id="activation-status" class="activation-status">
                    <?php _e('Preparing activation...', 'wceventsfp'); ?>
                </div>
            </div>
            
            <div id="activation-results" class="activation-results"></div>
            
            <div id="activation-actions" class="activation-actions">
                <a href="<?php echo $this->get_step_url('complete'); ?>" class="wcefp-button success">
                    <?php _e('Complete Setup', 'wceventsfp'); ?> →
                </a>
            </div>
        </div>
        
        <script>
            // Start activation process when page loads
            document.addEventListener('DOMContentLoaded', function() {
                wcefpStartActivation();
            });
        </script>
        <?php
    }
    
    /**
     * Render completion step
     * @return void
     */
    private function render_complete_step() {
        ?>
        <div class="wcefp-step-content">
            <h2><?php _e('🎉 Setup Complete!', 'wceventsfp'); ?></h2>
            <p><?php _e('WCEventsFP has been successfully configured and activated. Your plugin is ready to use!', 'wceventsfp'); ?></p>
            
            <div class="wcefp-recommendation">
                <h4><?php _e('What\'s Next?', 'wceventsfp'); ?></h4>
                <ul>
                    <li><strong><?php _e('Plugin Dashboard:', 'wceventsfp'); ?></strong> <?php _e('Monitor performance and manage features', 'wceventsfp'); ?></li>
                    <li><strong><?php _e('Feature Management:', 'wceventsfp'); ?></strong> <?php _e('Enable additional features as needed', 'wceventsfp'); ?></li>
                    <li><strong><?php _e('Documentation:', 'wceventsfp'); ?></strong> <?php _e('Learn about all available features', 'wceventsfp'); ?></li>
                </ul>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <button type="button" class="wcefp-button success" onclick="location.href='<?php echo admin_url('admin.php?page=wcefp'); ?>'">
                    <?php _e('Open Plugin Dashboard', 'wceventsfp'); ?>
                </button>
                <button type="button" class="wcefp-button" onclick="location.href='<?php echo admin_url('plugins.php'); ?>'">
                    <?php _e('Return to Plugins', 'wceventsfp'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get status icon for test results
     * @param string $status
     * @return string
     */
    private function get_status_icon($status) {
        switch ($status) {
            case 'success':
                return '✅';
            case 'warning':
                return '⚠️';
            case 'error':
                return '❌';
            default:
                return '❓';
        }
    }
    
    /**
     * Render performance recommendations
     * @param int $score
     */
    private function render_recommendations($score) {
        if ($score >= 80) {
            ?>
            <div class="wcefp-recommendation">
                <h4><?php _e('🚀 Excellent Performance', 'wceventsfp'); ?></h4>
                <p><?php _e('Your server can handle all features. We recommend the "Standard" loading mode.', 'wceventsfp'); ?></p>
            </div>
            <?php
        } elseif ($score >= 60) {
            ?>
            <div class="wcefp-recommendation">
                <h4><?php _e('⚡ Good Performance', 'wceventsfp'); ?></h4>
                <p><?php _e('Your server should work well. We recommend "Progressive" loading for best stability.', 'wceventsfp'); ?></p>
            </div>
            <?php
        } else {
            ?>
            <div class="wcefp-test-result warning">
                <h4><?php _e('⚠️ Limited Performance', 'wceventsfp'); ?></h4>
                <p><?php _e('Your server has limitations. We recommend "Minimal" loading mode to prevent issues.', 'wceventsfp'); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * Render feature selection options
     */
    private function render_feature_options() {
        $available_features = $this->get_available_features();
        
        foreach ($available_features as $feature_key => $feature) {
            ?>
            <div class="wcefp-feature-option <?php echo $feature['weight'] === 'heavy' ? 'heavy' : ''; ?>">
                <div class="wcefp-feature-title">
                    <label>
                        <input type="checkbox" name="features[]" value="<?php echo esc_attr($feature_key); ?>" 
                               <?php echo $feature['default'] ? 'checked' : ''; ?>>
                        <?php echo esc_html($feature['name']); ?>
                        <?php if ($feature['weight'] === 'heavy'): ?>
                            <span style="color: #ff9800; font-weight: normal;">(Resource Intensive)</span>
                        <?php endif; ?>
                    </label>
                </div>
                <div class="wcefp-feature-description">
                    <?php echo esc_html($feature['description']); ?>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render loading mode options
     * @param string $recommended_mode
     */
    private function render_loading_mode_options($recommended_mode) {
        $modes = [
            'minimal' => [
                'name' => __('Minimal Loading', 'wceventsfp'),
                'description' => __('Load only essential features. Best for shared hosting or limited servers.', 'wceventsfp')
            ],
            'progressive' => [
                'name' => __('Progressive Loading', 'wceventsfp'),
                'description' => __('Load features gradually over multiple requests. Recommended for most servers.', 'wceventsfp')
            ],
            'standard' => [
                'name' => __('Standard Loading', 'wceventsfp'),
                'description' => __('Load all features efficiently. For well-equipped servers.', 'wceventsfp')
            ],
            'full' => [
                'name' => __('Full Loading', 'wceventsfp'),
                'description' => __('Load all features at once. Only for high-performance servers.', 'wceventsfp')
            ]
        ];
        
        foreach ($modes as $mode_key => $mode) {
            $is_recommended = ($mode_key === $recommended_mode);
            ?>
            <div class="wcefp-feature-option <?php echo $is_recommended ? 'enabled' : ''; ?>">
                <div class="wcefp-feature-title">
                    <label>
                        <input type="radio" name="loading_mode" value="<?php echo esc_attr($mode_key); ?>" 
                               <?php echo $is_recommended ? 'checked' : ''; ?>>
                        <?php echo esc_html($mode['name']); ?>
                        <?php if ($is_recommended): ?>
                            <span style="color: #0073aa; font-weight: normal;">(Recommended)</span>
                        <?php endif; ?>
                    </label>
                </div>
                <div class="wcefp-feature-description">
                    <?php echo esc_html($mode['description']); ?>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render performance options
     */
    private function render_performance_options() {
        ?>
        <div class="wcefp-feature-option">
            <div class="wcefp-feature-title">
                <label>
                    <input type="checkbox" name="enable_caching" value="1" checked>
                    <?php _e('Enable Plugin Caching', 'wceventsfp'); ?>
                </label>
            </div>
            <div class="wcefp-feature-description">
                <?php _e('Cache plugin data to improve performance and reduce database queries.', 'wceventsfp'); ?>
            </div>
        </div>
        
        <div class="wcefp-feature-option">
            <div class="wcefp-feature-title">
                <label>
                    <input type="checkbox" name="enable_logging" value="1" checked>
                    <?php _e('Enable Debug Logging', 'wceventsfp'); ?>
                </label>
            </div>
            <div class="wcefp-feature-description">
                <?php _e('Log plugin activity for troubleshooting. Can be disabled later for production.', 'wceventsfp'); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle form submissions
     * @return void
     */
    private function handle_form_submission() {
        if (!isset($_POST['wcefp_setup_nonce']) || !wp_verify_nonce($_POST['wcefp_setup_nonce'], 'wcefp_setup_wizard')) {
            return;
        }
        
        $step = isset($_POST['step']) ? sanitize_key($_POST['step']) : '';
        
        switch ($step) {
            case 'features':
                $this->save_feature_selection();
                break;
            case 'activation':
                $this->save_performance_settings();
                break;
        }
    }
    
    /**
     * Save feature selection
     * @return void
     */
    private function save_feature_selection() {
        $selected_features = isset($_POST['features']) ? array_map('sanitize_key', $_POST['features']) : [];
        update_option('wcefp_selected_features', $selected_features);
    }
    
    /**
     * Save performance settings
     * @return void
     */
    private function save_performance_settings() {
        $settings = [
            'loading_mode' => isset($_POST['loading_mode']) ? sanitize_key($_POST['loading_mode']) : 'progressive',
            'enable_caching' => isset($_POST['enable_caching']) ? 1 : 0,
            'enable_logging' => isset($_POST['enable_logging']) ? 1 : 0,
        ];
        
        update_option('wcefp_performance_settings', $settings);
    }
    
    /**
     * Get step URL
     * @param string $step
     * @return string
     */
    private function get_step_url($step) {
        return add_query_arg(['wcefp_setup' => '1', 'step' => $step], admin_url('admin.php'));
    }
    
    /**
     * Get available features configuration
     * @return array
     */
    private function get_available_features() {
        return [
            'bookings' => [
                'name' => __('Event Bookings', 'wceventsfp'),
                'description' => __('Core booking functionality for events and experiences.', 'wceventsfp'),
                'default' => true,
                'weight' => 'normal'
            ],
            'resources' => [
                'name' => __('Resource Management', 'wceventsfp'),
                'description' => __('Manage guides, equipment, and vehicles.', 'wceventsfp'),
                'default' => true,
                'weight' => 'normal'
            ],
            'distribution' => [
                'name' => __('Multi-Channel Distribution', 'wceventsfp'),
                'description' => __('Integration with Booking.com, Expedia, GetYourGuide.', 'wceventsfp'),
                'default' => false,
                'weight' => 'heavy'
            ],
            'commissions' => [
                'name' => __('Commission System', 'wceventsfp'),
                'description' => __('Reseller and commission management.', 'wceventsfp'),
                'default' => false,
                'weight' => 'normal'
            ],
            'reviews' => [
                'name' => __('Google Reviews Integration', 'wceventsfp'),
                'description' => __('Automated review collection and management.', 'wceventsfp'),
                'default' => true,
                'weight' => 'normal'
            ],
            'analytics' => [
                'name' => __('Advanced Analytics', 'wceventsfp'),
                'description' => __('GA4, Meta Pixel, and real-time reporting.', 'wceventsfp'),
                'default' => false,
                'weight' => 'heavy'
            ],
            'automations' => [
                'name' => __('Marketing Automations', 'wceventsfp'),
                'description' => __('Brevo integration and email campaigns.', 'wceventsfp'),
                'default' => false,
                'weight' => 'normal'
            ]
        ];
    }
}

// Initialize and render the wizard if accessed properly
if ((isset($_GET['wcefp_setup']) || defined('WCEFP_SETUP_WIZARD_ACTIVE')) && is_admin()) {
    $wizard = new WCEFP_Setup_Wizard();
    $wizard->render();
    exit;
}