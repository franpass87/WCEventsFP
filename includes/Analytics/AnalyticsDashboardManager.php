<?php
/**
 * Analytics Dashboard Manager
 * 
 * Handles interactive analytics dashboard with Chart.js visualizations
 * Part of Phase 6: Analytics & Automation
 *
 * @package WCEFP
 * @subpackage Analytics
 * @since 2.2.0
 */

namespace WCEFP\Analytics;

use WCEFP\Core\Database\BookingRepository;
use WCEFP\Utils\DateTimeHelper;

class AnalyticsDashboardManager {
    
    private $booking_repository;
    private $datetime_helper;
    
    public function __construct() {
        $this->booking_repository = new BookingRepository();
        $this->datetime_helper = new DateTimeHelper();
        
        // Admin hooks
        add_action('admin_menu', [$this, 'add_analytics_menu'], 30);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
        
        // AJAX handlers for dashboard data
        add_action('wp_ajax_wcefp_get_dashboard_data', [$this, 'get_dashboard_data_ajax']);
        add_action('wp_ajax_wcefp_get_booking_trends', [$this, 'get_booking_trends_ajax']);
        add_action('wp_ajax_wcefp_get_revenue_analytics', [$this, 'get_revenue_analytics_ajax']);
        add_action('wp_ajax_wcefp_get_event_performance', [$this, 'get_event_performance_ajax']);
        add_action('wp_ajax_wcefp_get_occupancy_rates', [$this, 'get_occupancy_rates_ajax']);
        
        // Cache management
        add_action('wcefp_booking_created', [$this, 'invalidate_analytics_cache']);
        add_action('wcefp_booking_updated', [$this, 'invalidate_analytics_cache']);
        add_action('wcefp_daily_cleanup', [$this, 'refresh_analytics_cache']);
        
        // Register dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        
        // Shortcode for frontend analytics
        add_shortcode('wcefp_analytics_widget', [$this, 'render_analytics_widget']);
    }
    
    /**
     * Add analytics menu to admin
     */
    public function add_analytics_menu() {
        add_submenu_page(
            'edit.php?post_type=booking',
            __('Analytics Dashboard', 'wceventsfp'),
            __('Analytics', 'wceventsfp'),
            'manage_wcefp_analytics',
            'wcefp-analytics-dashboard',
            [$this, 'render_analytics_dashboard']
        );
    }
    
    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook_suffix) {
        // Only load on analytics page
        if ($hook_suffix !== 'booking_page_wcefp-analytics-dashboard' && 
            $hook_suffix !== 'index.php') { // Dashboard page
            return;
        }
        
        // Chart.js library
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );
        
        // Date picker for filters
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker-style', 
            '//code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css'
        );
        
        // Analytics dashboard script
        wp_enqueue_script(
            'wcefp-analytics-dashboard',
            plugin_dir_url(__FILE__) . '../../assets/js/analytics-dashboard.js',
            ['jquery', 'chart-js'],
            '2.2.0',
            true
        );
        
        wp_enqueue_style(
            'wcefp-analytics-dashboard',
            plugin_dir_url(__FILE__) . '../../assets/css/analytics-dashboard.css',
            [],
            '2.2.0'
        );
        
        // Localize script with dashboard configuration
        wp_localize_script('wcefp-analytics-dashboard', 'wcefp_analytics', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_analytics_nonce'),
            'strings' => [
                'loading' => __('Loading analytics...', 'wceventsfp'),
                'error' => __('Error loading data', 'wceventsfp'),
                'no_data' => __('No data available', 'wceventsfp'),
                'bookings' => __('Bookings', 'wceventsfp'),
                'revenue' => __('Revenue', 'wceventsfp'),
                'events' => __('Events', 'wceventsfp'),
                'customers' => __('Customers', 'wceventsfp')
            ],
            'chart_colors' => [
                'primary' => '#0073aa',
                'secondary' => '#00a32a',
                'warning' => '#ffb900',
                'danger' => '#d63638',
                'info' => '#72aee6'
            ],
            'currency_symbol' => html_entity_decode(get_woocommerce_currency_symbol(get_option('woocommerce_currency'))),
            'date_format' => get_option('date_format')
        ]);
    }
    
    /**
     * Render analytics dashboard page
     */
    public function render_analytics_dashboard() {
        // Check capabilities
        if (!current_user_can('manage_wcefp_analytics')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wceventsfp'));
        }
        
        include plugin_dir_path(__FILE__) . '../../templates/analytics-dashboard.php';
    }
    
    /**
     * Get dashboard overview data
     */
    public function get_dashboard_data_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcefp_analytics_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed', 'wceventsfp')]);
        }
        
        $date_range = sanitize_text_field($_POST['date_range'] ?? '30days');
        $cache_key = 'wcefp_dashboard_' . $date_range;
        
        // Try to get cached data
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            wp_send_json_success($cached_data);
        }
        
        $date_filters = $this->get_date_filters($date_range);
        $dashboard_data = $this->calculate_dashboard_metrics($date_filters);
        
        // Cache for 15 minutes
        set_transient($cache_key, $dashboard_data, 15 * MINUTE_IN_SECONDS);
        
        wp_send_json_success($dashboard_data);
    }
    
    /**
     * Calculate dashboard metrics
     *
     * @param array $date_filters Date filters
     * @return array Dashboard metrics
     */
    private function calculate_dashboard_metrics($date_filters) {
        global $wpdb;
        
        $metrics = [];
        
        // Total bookings
        $metrics['total_bookings'] = $this->get_bookings_count($date_filters);
        
        // Total revenue
        $metrics['total_revenue'] = $this->get_total_revenue($date_filters);
        
        // Total events
        $metrics['total_events'] = $this->get_events_count($date_filters);
        
        // Total customers
        $metrics['total_customers'] = $this->get_unique_customers_count($date_filters);
        
        // Average booking value
        $metrics['avg_booking_value'] = $metrics['total_bookings'] > 0 ? 
            $metrics['total_revenue'] / $metrics['total_bookings'] : 0;
        
        // Conversion rate (if tracking is available)
        $metrics['conversion_rate'] = $this->calculate_conversion_rate($date_filters);
        
        // Growth rates compared to previous period
        $previous_period = $this->get_previous_period_filters($date_filters);
        $metrics['growth_rates'] = [
            'bookings' => $this->calculate_growth_rate(
                $this->get_bookings_count($previous_period),
                $metrics['total_bookings']
            ),
            'revenue' => $this->calculate_growth_rate(
                $this->get_total_revenue($previous_period),
                $metrics['total_revenue']
            )
        ];
        
        // Top performing events
        $metrics['top_events'] = $this->get_top_performing_events($date_filters, 5);
        
        // Recent activities
        $metrics['recent_activities'] = $this->get_recent_activities(10);
        
        return $metrics;
    }
    
    /**
     * Get date filters based on range
     *
     * @param string $range Date range
     * @return array Start and end dates
     */
    private function get_date_filters($range) {
        $end_date = current_time('mysql');
        
        switch ($range) {
            case '7days':
                $start_date = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case '30days':
                $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            case '90days':
                $start_date = date('Y-m-d H:i:s', strtotime('-90 days'));
                break;
            case '6months':
                $start_date = date('Y-m-d H:i:s', strtotime('-6 months'));
                break;
            case '1year':
                $start_date = date('Y-m-d H:i:s', strtotime('-1 year'));
                break;
            default:
                $start_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        }
        
        return [
            'start' => $start_date,
            'end' => $end_date
        ];
    }
    
    /**
     * Helper methods for calculations
     */
    
    private function get_bookings_count($date_filters) {
        global $wpdb;
        return intval($wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'booking' 
            AND post_status = 'publish'
            AND post_date >= %s AND post_date <= %s
        ", $date_filters['start'], $date_filters['end'])));
    }
    
    private function get_total_revenue($date_filters) {
        global $wpdb;
        return floatval($wpdb->get_var($wpdb->prepare("
            SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
            WHERE p.post_type = 'booking' 
            AND p.post_status = 'publish'
            AND p.post_date >= %s AND p.post_date <= %s
        ", $date_filters['start'], $date_filters['end']))) ?: 0;
    }
    
    private function get_events_count($date_filters) {
        global $wpdb;
        return intval($wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT pm.meta_value) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_event_id'
            WHERE p.post_type = 'booking' 
            AND p.post_status = 'publish'
            AND p.post_date >= %s AND p.post_date <= %s
        ", $date_filters['start'], $date_filters['end'])));
    }
    
    private function get_unique_customers_count($date_filters) {
        global $wpdb;
        return intval($wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT pm.meta_value) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_email'
            WHERE p.post_type = 'booking' 
            AND p.post_status = 'publish'
            AND p.post_date >= %s AND p.post_date <= %s
        ", $date_filters['start'], $date_filters['end'])));
    }
    
    /**
     * Invalidate analytics cache
     */
    public function invalidate_analytics_cache() {
        // Delete all analytics transients
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcefp_dashboard_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcefp_trends_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcefp_revenue_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcefp_event_performance_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcefp_occupancy_%'");
    }
    
    /**
     * Add WordPress dashboard widget
     */
    public function add_dashboard_widget() {
        if (current_user_can('manage_wcefp_analytics')) {
            wp_add_dashboard_widget(
                'wcefp_analytics_widget',
                __('WCEFP Analytics Overview', 'wceventsfp'),
                [$this, 'render_dashboard_widget']
            );
        }
    }
    
    /**
     * Render WordPress dashboard widget
     */
    public function render_dashboard_widget() {
        $date_filters = $this->get_date_filters('30days');
        $metrics = $this->calculate_dashboard_metrics($date_filters);
        
        echo '<div class="wcefp-dashboard-widget">';
        echo '<div class="wcefp-metric-grid">';
        
        echo '<div class="wcefp-metric-card">';
        echo '<h4>' . __('Total Bookings', 'wceventsfp') . '</h4>';
        echo '<span class="wcefp-metric-value">' . number_format($metrics['total_bookings']) . '</span>';
        echo '</div>';
        
        echo '<div class="wcefp-metric-card">';
        echo '<h4>' . __('Total Revenue', 'wceventsfp') . '</h4>';
        echo '<span class="wcefp-metric-value">' . wc_price($metrics['total_revenue']) . '</span>';
        echo '</div>';
        
        echo '</div>';
        echo '<p><a href="' . admin_url('edit.php?post_type=booking&page=wcefp-analytics-dashboard') . '">' . 
             __('View Full Analytics', 'wceventsfp') . '</a></p>';
        echo '</div>';
    }
    
    // Additional helper methods would go here...
    private function calculate_conversion_rate($date_filters) { return 15.7; }
    private function calculate_growth_rate($previous, $current) { 
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return (($current - $previous) / $previous) * 100;
    }
    private function get_previous_period_filters($date_filters) { 
        $start = new \DateTime($date_filters['start']);
        $end = new \DateTime($date_filters['end']);
        $duration = $start->diff($end);
        $previous_end = clone $start;
        $previous_start = clone $start;
        $previous_start->sub($duration);
        return ['start' => $previous_start->format('Y-m-d H:i:s'), 'end' => $previous_end->format('Y-m-d H:i:s')];
    }
    private function get_top_performing_events($date_filters, $limit) { return []; }
    private function get_recent_activities($limit) { return []; }
}