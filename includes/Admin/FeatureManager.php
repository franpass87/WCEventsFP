<?php
/**
 * Feature Management Dashboard
 * 
 * @package WCEFP
 * @subpackage Admin
 * @since 2.1.1
 */

namespace WCEFP\Admin;

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
        add_action('admin_menu', [$this, 'add_admin_menu'], 20); // Lower priority to load after main menu
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Add admin menu
     * 
     * @return void
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wcefp',
            __('WCEventsFP Dashboard', 'wceventsfp'),
            __('Dashboard', 'wceventsfp'),
            'manage_options',
            'wcefp-dashboard',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'wcefp',
            __('Performance Monitor', 'wceventsfp'),
            __('Performance', 'wceventsfp'),
            'manage_options',
            'wcefp-performance',
            [$this, 'render_performance_monitor']
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
        
        // Only load basic dashboard styles
        wp_enqueue_style(
            'wcefp-dashboard',
            WCEFP_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WCEFP_VERSION
        );
    }
    
    /**
     * Render main dashboard
     * 
     * @return void
     */
    public function render_dashboard() {
        $performance_score = $this->calculate_performance_score();
        $all_features = $this->get_all_features(); // All features always active
        
        ?>
        <div class="wrap">
            <h1><?php _e('WCEventsFP Dashboard', 'wceventsfp'); ?></h1>
            
            <!-- Status Overview -->
            <div class="wcefp-dashboard-grid">
                <div class="wcefp-status-card">
                    <h3><?php _e('Plugin Status', 'wceventsfp'); ?></h3>
                    <div class="status-indicator status-active">
                        <?php _e('✅ Fully Active', 'wceventsfp'); ?>
                    </div>
                </div>
                
                <div class="wcefp-status-card">
                    <h3><?php _e('Performance Score', 'wceventsfp'); ?></h3>
                    <div class="performance-score">
                        <span class="score-number"><?php echo esc_html($performance_score); ?></span>
                        <span class="score-label">/100</span>
                        <div class="score-bar">
                            <div class="score-fill" style="width: <?php echo esc_html($performance_score); ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <div class="wcefp-status-card">
                    <h3><?php _e('Active Features', 'wceventsfp'); ?></h3>
                    <div class="features-count">
                        <span class="count"><?php echo count($all_features); ?></span>
                        <span class="label"><?php _e('All Features Active', 'wceventsfp'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Features List -->
            <div class="wcefp-features-overview">
                <h2><?php _e('Active Features', 'wceventsfp'); ?></h2>
                <div class="features-grid">
                    <?php foreach ($all_features as $feature_key => $feature): ?>
                    <div class="feature-item">
                        <div class="feature-icon">✅</div>
                        <div class="feature-info">
                            <h4><?php echo esc_html($feature['name']); ?></h4>
                            <p><?php echo esc_html($feature['description']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="wcefp-quick-actions">
                <h2><?php _e('Quick Actions', 'wceventsfp'); ?></h2>
                <div class="action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=wcefp-performance'); ?>" class="button button-primary">
                        <?php _e('Performance Monitor', 'wceventsfp'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=woocommerce'); ?>" class="button">
                        <?php _e('WooCommerce Settings', 'wceventsfp'); ?>
                    </a>
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
        // Use shared utilities if available 
        if (function_exists('wcefp_convert_memory_to_bytes')) {
            return wcefp_convert_memory_to_bytes($val);
        }
        
        // Fallback implementation
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