<?php
/**
 * Analytics Service Provider
 * 
 * Initializes and manages Analytics & Automation features
 * Part of Phase 6: Analytics & Automation
 *
 * @package WCEFP
 * @subpackage Analytics
 * @since 2.2.0
 */

namespace WCEFP\Analytics;

use WCEFP\Analytics\AnalyticsDashboardManager;
use WCEFP\Analytics\IntelligentOccurrenceManager;

class AnalyticsServiceProvider {
    
    private $dashboard_manager;
    private $occurrence_manager;
    
    public function __construct() {
        add_action('init', [$this, 'initialize'], 25);
        add_action('admin_init', [$this, 'initialize_admin']);
        add_action('wp_loaded', [$this, 'register_capabilities']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Initialize Analytics & Automation features
     */
    public function initialize() {
        // Initialize core managers
        $this->dashboard_manager = new AnalyticsDashboardManager();
        $this->occurrence_manager = new IntelligentOccurrenceManager();
        
        // Register custom post types and statuses
        $this->register_post_types();
        
        // Set up cron schedules
        $this->setup_cron_schedules();
        
        // Add feature availability checks
        add_filter('wcefp_available_features', [$this, 'add_available_features']);
        
        // Initialize integration hooks
        $this->initialize_integration_hooks();
        
        // Performance optimizations
        $this->optimize_performance();
    }
    
    /**
     * Initialize admin-specific functionality
     */
    public function initialize_admin() {
        if (!is_admin()) {
            return;
        }
        
        // Add admin notices for feature status
        add_action('admin_notices', [$this, 'admin_feature_notices']);
        
        // Add settings sections
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add meta boxes for events
        add_action('add_meta_boxes', [$this, 'add_event_meta_boxes']);
        
        // Save event meta data
        add_action('save_post', [$this, 'save_event_meta_data'], 10, 2);
        
        // Add admin columns
        add_filter('manage_event_posts_columns', [$this, 'add_event_columns']);
        add_action('manage_event_posts_custom_column', [$this, 'display_event_columns'], 10, 2);
        
        // Add bulk actions
        add_filter('bulk_actions-edit-event', [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-edit-event', [$this, 'handle_bulk_actions'], 10, 3);
    }
    
    /**
     * Register custom capabilities
     */
    public function register_capabilities() {
        // Get administrator role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_wcefp_analytics');
            $admin_role->add_cap('manage_wcefp_occurrences');
        }
        
        // Add capabilities to custom roles created in Phase 4
        $event_manager_role = get_role('event_manager');
        if ($event_manager_role) {
            $event_manager_role->add_cap('manage_wcefp_analytics');
            $event_manager_role->add_cap('manage_wcefp_occurrences');
        }
        
        $advanced_event_manager_role = get_role('advanced_event_manager');
        if ($advanced_event_manager_role) {
            $advanced_event_manager_role->add_cap('manage_wcefp_analytics');
            $advanced_event_manager_role->add_cap('manage_wcefp_occurrences');
        }
    }
    
    /**
     * Register custom post types
     */
    private function register_post_types() {
        // Event occurrences are already registered in IntelligentOccurrenceManager
        // Add any additional post types here if needed
    }
    
    /**
     * Set up custom cron schedules
     */
    private function setup_cron_schedules() {
        add_filter('cron_schedules', function($schedules) {
            // Add custom intervals for analytics and occurrence management
            $schedules['wcefp_hourly'] = [
                'interval' => HOUR_IN_SECONDS,
                'display' => __('WCEFP Every Hour', 'wceventsfp')
            ];
            
            $schedules['wcefp_twice_daily'] = [
                'interval' => 12 * HOUR_IN_SECONDS,
                'display' => __('WCEFP Twice Daily', 'wceventsfp')
            ];
            
            return $schedules;
        });
        
        // Schedule analytics cache refresh
        if (!wp_next_scheduled('wcefp_refresh_analytics_cache')) {
            wp_schedule_event(time(), 'wcefp_twice_daily', 'wcefp_refresh_analytics_cache');
        }
        
        // Schedule occurrence generation
        if (!wp_next_scheduled('wcefp_generate_occurrences')) {
            wp_schedule_event(time(), 'daily', 'wcefp_generate_occurrences');
        }
        
        // Schedule data cleanup
        if (!wp_next_scheduled('wcefp_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wcefp_daily_cleanup');
        }
    }
    
    /**
     * Initialize integration hooks
     */
    private function initialize_integration_hooks() {
        // Analytics integration hooks
        add_action('wcefp_booking_created', [$this->dashboard_manager, 'invalidate_analytics_cache']);
        add_action('wcefp_booking_updated', [$this->dashboard_manager, 'invalidate_analytics_cache']);
        add_action('wcefp_refresh_analytics_cache', [$this->dashboard_manager, 'refresh_analytics_cache']);
        
        // Occurrence integration hooks
        add_action('wcefp_event_updated', [$this->occurrence_manager, 'update_event_occurrences'], 10, 1);
        add_action('wcefp_daily_cleanup', [$this->occurrence_manager, 'cleanup_expired_occurrences']);
        
        // Email integration for occurrence notifications
        add_action('wcefp_occurrence_created', [$this, 'send_occurrence_notification'], 10, 3);
        
        // Performance monitoring hooks
        add_action('wcefp_slow_query_detected', [$this, 'log_slow_query'], 10, 2);
    }
    
    /**
     * Performance optimizations
     */
    private function optimize_performance() {
        // Database query optimization
        add_action('pre_get_posts', [$this, 'optimize_event_queries']);
        
        // Cache optimization
        add_action('init', [$this, 'setup_object_cache']);
        
        // Lazy loading for analytics
        add_action('wp_enqueue_scripts', [$this, 'optimize_frontend_loading']);
    }
    
    /**
     * Add meta boxes for events
     */
    public function add_event_meta_boxes() {
        add_meta_box(
            'wcefp-occurrence-settings',
            __('Occurrence Generation Settings', 'wceventsfp'),
            [$this, 'render_occurrence_settings_meta_box'],
            'event',
            'side',
            'default'
        );
        
        add_meta_box(
            'wcefp-analytics-summary',
            __('Event Analytics', 'wceventsfp'),
            [$this, 'render_analytics_summary_meta_box'],
            'event',
            'side',
            'default'
        );
    }
    
    /**
     * Render occurrence settings meta box
     */
    public function render_occurrence_settings_meta_box($post) {
        wp_nonce_field('wcefp_occurrence_settings', 'wcefp_occurrence_nonce');
        
        $settings = get_post_meta($post->ID, '_wcefp_occurrence_settings', true) ?: [];
        $defaults = [
            'enabled' => false,
            'pattern' => 'weekly',
            'time' => '10:00',
            'excluded_days' => []
        ];
        
        $settings = wp_parse_args($settings, $defaults);
        
        ?>
        <table class="form-table">
            <tr>
                <td>
                    <label>
                        <input type="checkbox" name="occurrence_enabled" value="yes" <?php checked($settings['enabled'], true); ?>>
                        <?php _e('Enable automatic occurrence generation', 'wceventsfp'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="occurrence_pattern"><?php _e('Pattern', 'wceventsfp'); ?></label></th>
                <td>
                    <select name="occurrence_pattern" id="occurrence_pattern">
                        <option value="daily" <?php selected($settings['pattern'], 'daily'); ?>><?php _e('Daily', 'wceventsfp'); ?></option>
                        <option value="weekly" <?php selected($settings['pattern'], 'weekly'); ?>><?php _e('Weekly', 'wceventsfp'); ?></option>
                        <option value="monthly" <?php selected($settings['pattern'], 'monthly'); ?>><?php _e('Monthly', 'wceventsfp'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="occurrence_time"><?php _e('Time', 'wceventsfp'); ?></label></th>
                <td>
                    <input type="time" name="occurrence_time" id="occurrence_time" value="<?php echo esc_attr($settings['time']); ?>">
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render analytics summary meta box
     */
    public function render_analytics_summary_meta_box($post) {
        // Get basic analytics for this event
        $bookings_count = $this->get_event_bookings_count($post->ID);
        $revenue = $this->get_event_revenue($post->ID);
        $next_occurrence = $this->get_next_occurrence_date($post->ID);
        
        ?>
        <div class="wcefp-analytics-summary">
            <p><strong><?php _e('Total Bookings:', 'wceventsfp'); ?></strong> <?php echo esc_html($bookings_count); ?></p>
            <p><strong><?php _e('Total Revenue:', 'wceventsfp'); ?></strong> <?php echo wc_price($revenue); ?></p>
            <?php if ($next_occurrence): ?>
            <p><strong><?php _e('Next Occurrence:', 'wceventsfp'); ?></strong> <?php echo esc_html($next_occurrence); ?></p>
            <?php endif; ?>
            <p>
                <a href="<?php echo admin_url('edit.php?post_type=booking&page=wcefp-analytics-dashboard&event_id=' . $post->ID); ?>" class="button">
                    <?php _e('View Detailed Analytics', 'wceventsfp'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Save event meta data
     */
    public function save_event_meta_data($post_id, $post) {
        if ($post->post_type !== 'event' || !current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save occurrence settings
        if (isset($_POST['wcefp_occurrence_nonce']) && wp_verify_nonce($_POST['wcefp_occurrence_nonce'], 'wcefp_occurrence_settings')) {
            $occurrence_settings = [
                'enabled' => isset($_POST['occurrence_enabled']),
                'pattern' => sanitize_text_field($_POST['occurrence_pattern'] ?? 'weekly'),
                'time' => sanitize_text_field($_POST['occurrence_time'] ?? '10:00'),
            ];
            
            update_post_meta($post_id, '_wcefp_occurrence_settings', $occurrence_settings);
            update_post_meta($post_id, '_wcefp_occurrence_enabled', $occurrence_settings['enabled'] ? 'yes' : 'no');
        }
    }
    
    /**
     * Add event columns
     */
    public function add_event_columns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add analytics columns after title
            if ($key === 'title') {
                $new_columns['bookings_count'] = __('Bookings', 'wceventsfp');
                $new_columns['revenue'] = __('Revenue', 'wceventsfp');
                $new_columns['next_occurrence'] = __('Next Occurrence', 'wceventsfp');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Display event columns
     */
    public function display_event_columns($column, $post_id) {
        switch ($column) {
            case 'bookings_count':
                echo esc_html($this->get_event_bookings_count($post_id));
                break;
                
            case 'revenue':
                echo wc_price($this->get_event_revenue($post_id));
                break;
                
            case 'next_occurrence':
                $next = $this->get_next_occurrence_date($post_id);
                echo esc_html($next ?: __('None scheduled', 'wceventsfp'));
                break;
        }
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Analytics settings
        register_setting('wcefp_analytics_settings', 'wcefp_analytics_cache_duration');
        register_setting('wcefp_analytics_settings', 'wcefp_analytics_track_visitors');
        register_setting('wcefp_analytics_settings', 'wcefp_analytics_email_reports');
        
        // Occurrence settings
        register_setting('wcefp_occurrence_settings', 'wcefp_occurrence_rolling_window');
        register_setting('wcefp_occurrence_settings', 'wcefp_occurrence_cleanup');
        register_setting('wcefp_occurrence_settings', 'wcefp_occurrence_logging');
        register_setting('wcefp_occurrence_settings', 'wcefp_occurrence_retention_days');
    }
    
    /**
     * Add available features to the features list
     */
    public function add_available_features($features) {
        $features['phase6'] = [
            'name' => __('Phase 6: Analytics & Automation', 'wceventsfp'),
            'description' => __('Interactive analytics dashboard and intelligent occurrence management', 'wceventsfp'),
            'version' => '2.2.0',
            'components' => [
                'analytics_dashboard' => __('Interactive Analytics Dashboard', 'wceventsfp'),
                'occurrence_management' => __('Intelligent Occurrence Management', 'wceventsfp')
            ]
        ];
        
        return $features;
    }
    
    /**
     * Plugin activation hook
     */
    public function activate() {
        // Create database tables if needed
        $this->create_analytics_tables();
        
        // Set default options
        add_option('wcefp_analytics_cache_duration', 15); // 15 minutes
        add_option('wcefp_analytics_track_visitors', 'no');
        add_option('wcefp_analytics_email_reports', 'weekly');
        add_option('wcefp_occurrence_rolling_window', 60); // 60 days
        add_option('wcefp_occurrence_cleanup', 'yes');
        add_option('wcefp_occurrence_logging', 'yes');
        add_option('wcefp_occurrence_retention_days', 30);
        
        // Schedule initial jobs
        wp_schedule_single_event(time() + 300, 'wcefp_refresh_analytics_cache'); // 5 minutes delay
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('wcefp_refresh_analytics_cache');
        wp_clear_scheduled_hook('wcefp_generate_occurrences');
        wp_clear_scheduled_hook('wcefp_daily_cleanup');
        
        // Clear analytics cache
        $this->dashboard_manager->invalidate_analytics_cache();
    }
    
    /**
     * Create analytics database tables
     */
    private function create_analytics_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Analytics cache table for better performance
        $cache_table = $wpdb->prefix . 'wcefp_analytics_cache';
        $cache_sql = "CREATE TABLE $cache_table (
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            expiry_time datetime NOT NULL,
            PRIMARY KEY (cache_key),
            KEY expiry_time (expiry_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($cache_sql);
    }
    
    /**
     * Helper methods
     */
    
    private function get_event_bookings_count($event_id) {
        global $wpdb;
        return intval($wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_event_id'
            WHERE p.post_type = 'booking'
            AND p.post_status = 'publish'
            AND pm.meta_value = %d
        ", $event_id)));
    }
    
    private function get_event_revenue($event_id) {
        global $wpdb;
        return floatval($wpdb->get_var($wpdb->prepare("
            SELECT SUM(CAST(pm_total.meta_value AS DECIMAL(10,2)))
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_event ON p.ID = pm_event.post_id AND pm_event.meta_key = '_event_id'
            INNER JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
            WHERE p.post_type = 'booking'
            AND p.post_status = 'publish'
            AND pm_event.meta_value = %d
        ", $event_id))) ?: 0;
    }
    
    private function get_next_occurrence_date($event_id) {
        global $wpdb;
        
        $next_date = $wpdb->get_var($wpdb->prepare("
            SELECT MIN(pm.meta_value)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_occurrence_date'
            WHERE p.post_parent = %d
            AND p.post_type = 'event_occurrence'
            AND p.post_status = 'publish'
            AND pm.meta_value > NOW()
        ", $event_id));
        
        return $next_date ? date('M j, Y g:i A', strtotime($next_date)) : null;
    }
    
    public function admin_feature_notices() {
        // Show analytics status notices if needed
    }
    
    public function add_bulk_actions($bulk_actions) {
        $bulk_actions['generate_occurrences'] = __('Generate Occurrences', 'wceventsfp');
        return $bulk_actions;
    }
    
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if ($doaction === 'generate_occurrences') {
            foreach ($post_ids as $post_id) {
                $this->occurrence_manager->generate_event_occurrences($post_id);
            }
            $redirect_to = add_query_arg('bulk_occurrences_generated', count($post_ids), $redirect_to);
        }
        return $redirect_to;
    }
    
    // Additional optimization and monitoring methods...
    public function optimize_event_queries($query) { /* Implementation */ }
    public function setup_object_cache() { /* Implementation */ }
    public function optimize_frontend_loading() { /* Implementation */ }
    public function send_occurrence_notification($occurrence_id, $event_id, $date) { /* Implementation */ }
    public function log_slow_query($query, $execution_time) { /* Implementation */ }
}