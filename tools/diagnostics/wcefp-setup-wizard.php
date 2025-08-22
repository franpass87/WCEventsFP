<?php
/**
 * WCEventsFP Setup Wizard
 * 
 * Interactive installation wizard to prevent WSOD by allowing users to 
 * configure the plugin step-by-step with progressive feature loading.
 * 
 * @package WCEventsFP
 * @version 2.1.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load shared utilities for consistent diagnostics
if (file_exists(__DIR__ . '/wcefp-shared-utilities.php')) {
    require_once __DIR__ . '/wcefp-shared-utilities.php';
}

// Prevent direct access unless called properly
if (!isset($_GET['wcefp_setup']) && !defined('WCEFP_SETUP_WIZARD_ACTIVE')) {
    wp_die('Setup wizard must be accessed through WordPress admin', 'WCEventsFP Setup');
}

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
     * Environment test results
     * @var array
     */
    private $environment_tests = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->current_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'welcome';
        
        // Run environment tests
        $this->run_environment_tests();
        
        // Handle form submissions
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
            <style>
                <?php $this->render_styles(); ?>
            </style>
        </head>
        <body class="wcefp-setup-wizard">
            <div class="wcefp-setup-container">
                <?php $this->render_header(); ?>
                <?php $this->render_progress_bar(); ?>
                <?php $this->render_current_step(); ?>
            </div>
            
            <script>
                <?php $this->render_javascript(); ?>
            </script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render wizard styles
     * @return void
     */
    private function render_styles() {
        ?>
        body.wcefp-setup-wizard {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f1f1;
            margin: 0;
            padding: 20px;
        }
        .wcefp-setup-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .wcefp-setup-header {
            background: #0073aa;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .wcefp-setup-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .wcefp-progress-bar {
            background: #f8f9fa;
            padding: 0;
            margin: 0;
            border-bottom: 1px solid #ddd;
        }
        .wcefp-progress-steps {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .wcefp-progress-step {
            flex: 1;
            padding: 15px 10px;
            text-align: center;
            font-size: 12px;
            position: relative;
            background: #f8f9fa;
            color: #666;
        }
        .wcefp-progress-step.active {
            background: #0073aa;
            color: white;
        }
        .wcefp-progress-step.completed {
            background: #46b450;
            color: white;
        }
        .wcefp-step-content {
            padding: 30px;
        }
        .wcefp-test-result {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            border-left: 4px solid #ccc;
        }
        .wcefp-test-result.success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .wcefp-test-result.warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .wcefp-test-result.error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .wcefp-feature-option {
            border: 1px solid #ddd;
            border-radius: 6px;
            margin: 10px 0;
            padding: 15px;
            background: #fafafa;
        }
        .wcefp-feature-option.enabled {
            border-color: #0073aa;
            background: #e8f4f8;
        }
        .wcefp-feature-option.heavy {
            border-color: #ff9800;
            background: #fff8e1;
        }
        .wcefp-feature-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .wcefp-feature-description {
            color: #666;
            font-size: 14px;
        }
        .wcefp-feature-impact {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        .wcefp-button {
            background: #0073aa;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px 10px 0;
        }
        .wcefp-button:hover {
            background: #005a87;
        }
        .wcefp-button.secondary {
            background: #666;
        }
        .wcefp-button.success {
            background: #46b450;
        }
        .wcefp-performance-meter {
            background: #f0f0f0;
            border-radius: 10px;
            height: 20px;
            margin: 10px 0;
            overflow: hidden;
        }
        .wcefp-performance-fill {
            height: 100%;
            background: #46b450;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .wcefp-performance-fill.warning {
            background: #ffc107;
        }
        .wcefp-performance-fill.danger {
            background: #dc3545;
        }
        .wcefp-recommendation {
            background: #e8f4fd;
            border: 1px solid #0073aa;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }
        .wcefp-recommendation h4 {
            margin: 0 0 10px 0;
            color: #0073aa;
        }
        ?>
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
                <?php
                $step_index = 0;
                $current_index = array_search($this->current_step, array_keys($this->steps));
                
                foreach ($this->steps as $step_key => $step_name) {
                    $class = '';
                    if ($step_index < $current_index) {
                        $class = 'completed';
                    } elseif ($step_index == $current_index) {
                        $class = 'active';
                    }
                    
                    echo '<li class="wcefp-progress-step ' . esc_attr($class) . '">';
                    echo esc_html($step_name);
                    echo '</li>';
                    
                    $step_index++;
                }
                ?>
            </ul>
        </div>
        <?php
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
        ?>
        <div class="wcefp-step-content">
            <h2><?php _e('Welcome to WCEventsFP!', 'wceventsfp'); ?></h2>
            
            <p><?php _e('This wizard will help you configure WCEventsFP safely to prevent any loading issues (WSOD). We\'ll check your environment, let you choose which features to enable, and activate the plugin progressively.', 'wceventsfp'); ?></p>
            
            <div class="wcefp-recommendation">
                <h4><?php _e('💡 Why use this wizard?', 'wceventsfp'); ?></h4>
                <ul>
                    <li><?php _e('Prevents White Screen of Death (WSOD) during activation', 'wceventsfp'); ?></li>
                    <li><?php _e('Tests your environment before loading heavy features', 'wceventsfp'); ?></li>
                    <li><?php _e('Allows you to enable features gradually based on your needs', 'wceventsfp'); ?></li>
                    <li><?php _e('Provides performance recommendations for your server', 'wceventsfp'); ?></li>
                </ul>
            </div>
            
            <h3><?php _e('Quick Environment Check', 'wceventsfp'); ?></h3>
            <?php $this->render_quick_environment_check(); ?>
            
            <form method="post">
                <?php wp_nonce_field('wcefp_setup_wizard', 'wcefp_setup_nonce'); ?>
                <input type="hidden" name="step" value="requirements">
                <button type="submit" class="wcefp-button">
                    <?php _e('Start Setup', 'wceventsfp'); ?>
                </button>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render requirements step
     * @return void
     */
    private function render_requirements_step() {
        ?>
        <div class="wcefp-step-content">
            <h2><?php _e('System Requirements Check', 'wceventsfp'); ?></h2>
            <p><?php _e('Let\'s verify your server meets all requirements for WCEventsFP.', 'wceventsfp'); ?></p>
            
            <?php $this->render_detailed_environment_tests(); ?>
            
            <form method="post">
                <?php wp_nonce_field('wcefp_setup_wizard', 'wcefp_setup_nonce'); ?>
                <input type="hidden" name="step" value="features">
                
                <?php if ($this->can_proceed_to_next_step()): ?>
                    <button type="submit" class="wcefp-button">
                        <?php _e('Continue to Feature Selection', 'wceventsfp'); ?>
                    </button>
                <?php else: ?>
                    <button type="button" class="wcefp-button" onclick="location.reload()">
                        <?php _e('Recheck Requirements', 'wceventsfp'); ?>
                    </button>
                <?php endif; ?>
                
                <button type="submit" name="step" value="welcome" class="wcefp-button secondary">
                    <?php _e('Back', 'wceventsfp'); ?>
                </button>
            </form>
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
            <h2><?php _e('Choose Your Features', 'wceventsfp'); ?></h2>
            <p><?php _e('Select which features you want to enable. You can always change these later from the admin panel.', 'wceventsfp'); ?></p>
            
            <div class="wcefp-recommendation">
                <h4><?php _e('💡 Recommendation', 'wceventsfp'); ?></h4>
                <p><?php _e('For first-time setup, we recommend starting with Core Features only, then enabling additional features once the plugin is working properly.', 'wceventsfp'); ?></p>
            </div>
            
            <form method="post" id="features-form">
                <?php wp_nonce_field('wcefp_setup_wizard', 'wcefp_setup_nonce'); ?>
                <input type="hidden" name="step" value="performance">
                
                <?php $this->render_feature_options(); ?>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="wcefp-button">
                        <?php _e('Continue to Performance Setup', 'wceventsfp'); ?>
                    </button>
                    <button type="submit" name="step" value="requirements" class="wcefp-button secondary">
                        <?php _e('Back', 'wceventsfp'); ?>
                    </button>
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
        ?>
        <div class="wcefp-step-content">
            <h2><?php _e('Performance Configuration', 'wceventsfp'); ?></h2>
            <p><?php _e('Let\'s optimize the plugin settings for your server environment.', 'wceventsfp'); ?></p>
            
            <?php $this->render_performance_analysis(); ?>
            
            <form method="post">
                <?php wp_nonce_field('wcefp_setup_wizard', 'wcefp_setup_nonce'); ?>
                <input type="hidden" name="step" value="activation">
                
                <?php $this->render_performance_options(); ?>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="wcefp-button">
                        <?php _e('Activate Plugin', 'wceventsfp'); ?>
                    </button>
                    <button type="submit" name="step" value="features" class="wcefp-button secondary">
                        <?php _e('Back', 'wceventsfp'); ?>
                    </button>
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
            <h2><?php _e('Activating WCEventsFP...', 'wceventsfp'); ?></h2>
            <p><?php _e('Please wait while we safely activate the plugin with your selected configuration.', 'wceventsfp'); ?></p>
            
            <div id="activation-progress">
                <div class="wcefp-performance-meter">
                    <div class="wcefp-performance-fill" id="progress-fill" style="width: 0%"></div>
                </div>
                <p id="activation-status"><?php _e('Initializing...', 'wceventsfp'); ?></p>
            </div>
            
            <div id="activation-results" style="display: none;">
                <!-- Results will be populated by JavaScript -->
            </div>
            
            <div id="activation-actions" style="display: none;">
                <button type="button" class="wcefp-button success" onclick="location.href='<?php echo admin_url('admin.php?page=wcefp'); ?>'">
                    <?php _e('Go to Plugin Dashboard', 'wceventsfp'); ?>
                </button>
                <button type="button" class="wcefp-button" onclick="location.href='<?php echo $this->get_step_url('complete'); ?>'">
                    <?php _e('Continue', 'wceventsfp'); ?>
                </button>
            </div>
        </div>
        
        <script>
            // Start activation process
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
     * Run environment tests
     * @return void
     */
    private function run_environment_tests() {
        $this->environment_tests = [
            'php_version' => [
                'name' => __('PHP Version', 'wceventsfp'),
                'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'success' : 'error',
                'message' => sprintf(__('PHP %s (Required: 7.4+)', 'wceventsfp'), PHP_VERSION),
                'critical' => true
            ],
            'wp_version' => [
                'name' => __('WordPress Version', 'wceventsfp'),
                'status' => version_compare(get_bloginfo('version'), '5.0', '>=') ? 'success' : 'warning',
                'message' => sprintf(__('WordPress %s (Recommended: 5.0+)', 'wceventsfp'), get_bloginfo('version')),
                'critical' => false
            ],
            'woocommerce' => [
                'name' => __('WooCommerce', 'wceventsfp'),
                'status' => class_exists('WooCommerce') ? 'success' : 'error',
                'message' => class_exists('WooCommerce') ? 
                    sprintf(__('WooCommerce %s Active', 'wceventsfp'), WC()->version) : 
                    __('WooCommerce not found - Required for full functionality', 'wceventsfp'),
                'critical' => true
            ],
            'memory_limit' => [
                'name' => __('Memory Limit', 'wceventsfp'),
                'status' => $this->check_memory_limit(),
                'message' => $this->get_memory_message(),
                'critical' => false
            ],
            'extensions' => [
                'name' => __('PHP Extensions', 'wceventsfp'),
                'status' => $this->check_php_extensions(),
                'message' => $this->get_extensions_message(),
                'critical' => true
            ]
        ];
    }
    
    /**
     * Check memory limit
     * @return string
     */
    private function check_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit === '-1') return 'success';
        
        $memory_bytes = $this->convert_memory_to_bytes($memory_limit);
        if ($memory_bytes >= 268435456) { // 256MB
            return 'success';
        } elseif ($memory_bytes >= 134217728) { // 128MB
            return 'warning';
        } else {
            return 'error';
        }
    }
    
    /**
     * Get memory limit message
     * @return string
     */
    private function get_memory_message() {
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit === '-1') {
            return __('Unlimited (Excellent)', 'wceventsfp');
        }
        
        $memory_bytes = $this->convert_memory_to_bytes($memory_limit);
        $recommended = $memory_bytes >= 268435456 ? __('Excellent', 'wceventsfp') : 
                      ($memory_bytes >= 134217728 ? __('Adequate', 'wceventsfp') : __('Too Low', 'wceventsfp'));
        
        return sprintf(__('%s (%s - Recommended: 256MB+)', 'wceventsfp'), $memory_limit, $recommended);
    }
    
    /**
     * Check PHP extensions
     * @return string
     */
    private function check_php_extensions() {
        $required_extensions = ['mysqli', 'json', 'mbstring', 'curl'];
        $missing = [];
        
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        
        return empty($missing) ? 'success' : 'error';
    }
    
    /**
     * Get extensions message
     * @return string
     */
    private function get_extensions_message() {
        $required_extensions = ['mysqli', 'json', 'mbstring', 'curl'];
        $missing = [];
        
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        
        return empty($missing) ? 
            __('All required extensions available', 'wceventsfp') :
            sprintf(__('Missing: %s', 'wceventsfp'), implode(', ', $missing));
    }
    
    /**
     * Convert memory limit to bytes
     * @param string $val
     * @return int
     */
    private function convert_memory_to_bytes($val) {
        // Use shared utilities if available
        if (function_exists('wcefp_convert_memory_to_bytes')) {
            return wcefp_convert_memory_to_bytes($val);
        }
        
        // Fallback implementation
        $val = trim($val);
        $unit = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;
        
        switch ($unit) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        
        return $val;
    }
    
    /**
     * Render quick environment check
     * @return void
     */
    private function render_quick_environment_check() {
        $critical_issues = 0;
        
        foreach ($this->environment_tests as $test) {
            if ($test['critical'] && $test['status'] === 'error') {
                $critical_issues++;
            }
        }
        
        if ($critical_issues === 0) {
            echo '<div class="wcefp-test-result success">';
            echo '<span>✅ ' . __('Environment looks good for installation', 'wceventsfp') . '</span>';
            echo '</div>';
        } else {
            echo '<div class="wcefp-test-result error">';
            echo '<span>❌ ' . sprintf(_n('%d critical issue found', '%d critical issues found', $critical_issues, 'wceventsfp'), $critical_issues) . '</span>';
            echo '</div>';
        }
    }
    
    /**
     * Render detailed environment tests
     * @return void
     */
    private function render_detailed_environment_tests() {
        foreach ($this->environment_tests as $test) {
            $class = $test['status'];
            $icon = $test['status'] === 'success' ? '✅' : 
                   ($test['status'] === 'warning' ? '⚠️' : '❌');
            
            echo '<div class="wcefp-test-result ' . esc_attr($class) . '">';
            echo '<span>' . $icon . ' ' . esc_html($test['name']) . '</span>';
            echo '<span>' . esc_html($test['message']) . '</span>';
            echo '</div>';
        }
    }
    
    /**
     * Can proceed to next step
     * @return bool
     */
    private function can_proceed_to_next_step() {
        foreach ($this->environment_tests as $test) {
            if ($test['critical'] && $test['status'] === 'error') {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Render feature options
     * @return void
     */
    private function render_feature_options() {
        $features = $this->get_available_features();
        
        foreach ($features as $feature_key => $feature) {
            $class = $feature['recommended'] ? 'enabled' : '';
            if ($feature['impact'] === 'high') $class .= ' heavy';
            
            echo '<div class="wcefp-feature-option ' . esc_attr($class) . '">';
            echo '<div class="wcefp-feature-title">';
            echo '<label>';
            echo '<input type="checkbox" name="features[]" value="' . esc_attr($feature_key) . '"' . 
                 ($feature['recommended'] ? ' checked' : '') . '> ';
            echo esc_html($feature['name']);
            echo '</label>';
            echo '</div>';
            echo '<div class="wcefp-feature-description">' . esc_html($feature['description']) . '</div>';
            echo '<div class="wcefp-feature-impact">';
            echo __('Performance Impact:', 'wceventsfp') . ' ' . esc_html($feature['impact']);
            if ($feature['dependencies']) {
                echo ' • ' . __('Requires:', 'wceventsfp') . ' ' . esc_html(implode(', ', $feature['dependencies']));
            }
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Get available features
     * @return array
     */
    private function get_available_features() {
        return [
            'core' => [
                'name' => __('Core Booking System', 'wceventsfp'),
                'description' => __('Essential booking functionality, basic product types, and WooCommerce integration.', 'wceventsfp'),
                'impact' => __('Low', 'wceventsfp'),
                'recommended' => true,
                'dependencies' => ['WooCommerce'],
                'required' => true
            ],
            'admin_enhanced' => [
                'name' => __('Enhanced Admin Panel', 'wceventsfp'),
                'description' => __('Advanced admin interface with reporting, analytics dashboard, and management tools.', 'wceventsfp'),
                'impact' => __('Medium', 'wceventsfp'),
                'recommended' => true,
                'dependencies' => [],
                'required' => false
            ],
            'resources' => [
                'name' => __('Resource Management', 'wceventsfp'),
                'description' => __('Manage guides, equipment, vehicles and other bookable resources.', 'wceventsfp'),
                'impact' => __('Medium', 'wceventsfp'),
                'recommended' => false,
                'dependencies' => [],
                'required' => false
            ],
            'channels' => [
                'name' => __('Distribution Channels', 'wceventsfp'),
                'description' => __('Integration with Booking.com, Expedia, GetYourGuide and other booking platforms.', 'wceventsfp'),
                'impact' => __('High', 'wceventsfp'),
                'recommended' => false,
                'dependencies' => ['Core Booking System'],
                'required' => false
            ],
            'commissions' => [
                'name' => __('Commission System', 'wceventsfp'),
                'description' => __('Handle commissions, reseller management, and revenue sharing.', 'wceventsfp'),
                'impact' => __('Medium', 'wceventsfp'),
                'recommended' => false,
                'dependencies' => [],
                'required' => false
            ],
            'reviews' => [
                'name' => __('Google Reviews Integration', 'wceventsfp'),
                'description' => __('Automate Google Reviews collection and management.', 'wceventsfp'),
                'impact' => __('Low', 'wceventsfp'),
                'recommended' => false,
                'dependencies' => [],
                'required' => false
            ],
            'tracking' => [
                'name' => __('Advanced Analytics', 'wceventsfp'),
                'description' => __('GA4, Meta Pixel, and advanced conversion tracking.', 'wceventsfp'),
                'impact' => __('Medium', 'wceventsfp'),
                'recommended' => false,
                'dependencies' => [],
                'required' => false
            ],
            'automation' => [
                'name' => __('Marketing Automation', 'wceventsfp'),
                'description' => __('Brevo integration, email campaigns, and customer automation.', 'wceventsfp'),
                'impact' => __('Medium', 'wceventsfp'),
                'recommended' => false,
                'dependencies' => [],
                'required' => false
            ],
            'ai_recommendations' => [
                'name' => __('AI Recommendations', 'wceventsfp'),
                'description' => __('Machine learning powered product recommendations and smart pricing.', 'wceventsfp'),
                'impact' => __('High', 'wceventsfp'),
                'recommended' => false,
                'dependencies' => ['Advanced Analytics'],
                'required' => false
            ],
            'realtime' => [
                'name' => __('Real-time Features', 'wceventsfp'),
                'description' => __('Live availability updates, real-time notifications, and websocket connections.', 'wceventsfp'),
                'impact' => __('High', 'wceventsfp'),
                'recommended' => false,
                'dependencies' => [],
                'required' => false
            ]
        ];
    }
    
    /**
     * Render performance analysis
     * @return void
     */
    private function render_performance_analysis() {
        $server_score = $this->calculate_server_performance_score();
        $recommendations = $this->get_performance_recommendations($server_score);
        
        ?>
        <div class="wcefp-recommendation">
            <h4><?php _e('Server Performance Analysis', 'wceventsfp'); ?></h4>
            <p><?php printf(__('Your server performance score: <strong>%d/100</strong>', 'wceventsfp'), $server_score); ?></p>
            
            <div class="wcefp-performance-meter">
                <div class="wcefp-performance-fill <?php echo $server_score >= 70 ? '' : ($server_score >= 40 ? 'warning' : 'danger'); ?>" 
                     style="width: <?php echo $server_score; ?>%"></div>
            </div>
        </div>
        
        <?php if (!empty($recommendations)): ?>
        <div class="wcefp-recommendation">
            <h4><?php _e('Performance Recommendations', 'wceventsfp'); ?></h4>
            <ul>
                <?php foreach ($recommendations as $rec): ?>
                <li><?php echo esc_html($rec); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif;
    }
    
    /**
     * Calculate server performance score
     * @return int
     */
    private function calculate_server_performance_score() {
        $score = 0;
        
        // PHP version scoring
        if (version_compare(PHP_VERSION, '8.0', '>=')) {
            $score += 25;
        } elseif (version_compare(PHP_VERSION, '7.4', '>=')) {
            $score += 20;
        }
        
        // Memory limit scoring
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit === '-1') {
            $score += 25;
        } else {
            $memory_bytes = $this->convert_memory_to_bytes($memory_limit);
            if ($memory_bytes >= 536870912) { // 512MB+
                $score += 25;
            } elseif ($memory_bytes >= 268435456) { // 256MB+
                $score += 20;
            } elseif ($memory_bytes >= 134217728) { // 128MB+
                $score += 10;
            }
        }
        
        // Max execution time
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time == 0 || $max_execution_time >= 120) {
            $score += 15;
        } elseif ($max_execution_time >= 60) {
            $score += 10;
        } elseif ($max_execution_time >= 30) {
            $score += 5;
        }
        
        // Required extensions
        $required_extensions = ['mysqli', 'json', 'mbstring', 'curl'];
        $loaded_count = 0;
        foreach ($required_extensions as $ext) {
            if (extension_loaded($ext)) $loaded_count++;
        }
        $score += ($loaded_count / count($required_extensions)) * 20;
        
        // WordPress and WooCommerce
        if (class_exists('WooCommerce')) {
            $score += 10;
        }
        
        // Object caching
        if (wp_using_ext_object_cache()) {
            $score += 5;
        }
        
        return min(100, $score);
    }
    
    /**
     * Get performance recommendations
     * @param int $score
     * @return array
     */
    private function get_performance_recommendations($score) {
        $recommendations = [];
        
        if ($score < 70) {
            $recommendations[] = __('Consider upgrading to PHP 8.0+ for better performance', 'wceventsfp');
            
            $memory_limit = ini_get('memory_limit');
            if ($memory_limit !== '-1') {
                $memory_bytes = $this->convert_memory_to_bytes($memory_limit);
                if ($memory_bytes < 268435456) {
                    $recommendations[] = __('Increase PHP memory_limit to at least 256MB', 'wceventsfp');
                }
            }
            
            $recommendations[] = __('Start with Core Features only and enable additional features gradually', 'wceventsfp');
            
            if ($score < 40) {
                $recommendations[] = __('Consider using a higher performance hosting provider', 'wceventsfp');
            }
        }
        
        if (!wp_using_ext_object_cache()) {
            $recommendations[] = __('Enable object caching (Redis/Memcached) for better performance', 'wceventsfp');
        }
        
        return $recommendations;
    }
    
    /**
     * Render performance options
     * @return void
     */
    private function render_performance_options() {
        $server_score = $this->calculate_server_performance_score();
        
        ?>
        <h3><?php _e('Performance Settings', 'wceventsfp'); ?></h3>
        
        <div class="wcefp-feature-option">
            <div class="wcefp-feature-title">
                <label>
                    <input type="radio" name="loading_mode" value="progressive" <?php echo $server_score < 70 ? 'checked' : ''; ?>>
                    <?php _e('Progressive Loading (Recommended)', 'wceventsfp'); ?>
                </label>
            </div>
            <div class="wcefp-feature-description">
                <?php _e('Load features gradually over multiple page loads to prevent timeouts and WSOD.', 'wceventsfp'); ?>
            </div>
        </div>
        
        <div class="wcefp-feature-option">
            <div class="wcefp-feature-title">
                <label>
                    <input type="radio" name="loading_mode" value="standard" <?php echo $server_score >= 70 ? 'checked' : ''; ?>>
                    <?php _e('Standard Loading', 'wceventsfp'); ?>
                </label>
            </div>
            <div class="wcefp-feature-description">
                <?php _e('Load all selected features at once. Suitable for high-performance servers.', 'wceventsfp'); ?>
            </div>
        </div>
        
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
     * Render JavaScript
     * @return void
     */
    private function render_javascript() {
        ?>
        function wcefpStartActivation() {
            let progress = 0;
            const progressFill = document.getElementById('progress-fill');
            const statusEl = document.getElementById('activation-status');
            
            const steps = [
                '<?php _e("Checking environment...", "wceventsfp"); ?>',
                '<?php _e("Loading core system...", "wceventsfp"); ?>',
                '<?php _e("Initializing database...", "wceventsfp"); ?>',
                '<?php _e("Setting up features...", "wceventsfp"); ?>',
                '<?php _e("Finalizing configuration...", "wceventsfp"); ?>',
                '<?php _e("Activation complete!", "wceventsfp"); ?>'
            ];
            
            function updateProgress() {
                if (progress < steps.length - 1) {
                    statusEl.textContent = steps[progress];
                    progressFill.style.width = ((progress + 1) / steps.length * 100) + '%';
                    progress++;
                    setTimeout(updateProgress, 1500);
                } else {
                    statusEl.textContent = steps[progress];
                    progressFill.style.width = '100%';
                    progressFill.classList.add('success');
                    
                    setTimeout(function() {
                        document.getElementById('activation-results').style.display = 'block';
                        document.getElementById('activation-actions').style.display = 'block';
                        document.getElementById('activation-results').innerHTML = 
                            '<div class="wcefp-test-result success"><span>✅ <?php _e("Plugin activated successfully!", "wceventsfp"); ?></span></div>';
                    }, 1000);
                }
            }
            
            setTimeout(updateProgress, 500);
        }
        
        // Feature selection handling
        document.addEventListener('DOMContentLoaded', function() {
            const featureCheckboxes = document.querySelectorAll('input[name="features[]"]');
            featureCheckboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    const option = this.closest('.wcefp-feature-option');
                    if (this.checked) {
                        option.classList.add('enabled');
                    } else {
                        option.classList.remove('enabled');
                    }
                });
            });
        });
        <?php
    }
}

// Initialize and render the wizard if accessed properly
if ((isset($_GET['wcefp_setup']) || defined('WCEFP_SETUP_WIZARD_ACTIVE')) && is_admin()) {
    $wizard = new WCEFP_Setup_Wizard();
    $wizard->render();
    exit;
}