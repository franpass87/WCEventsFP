<?php
/**
 * Intelligent Occurrence Management
 * 
 * Handles auto-generation of future event occurrences with WP-Cron automation
 * Part of Phase 6: Analytics & Automation
 *
 * @package WCEFP
 * @subpackage Analytics
 * @since 2.2.0
 */

namespace WCEFP\Analytics;

use WCEFP\Utils\DateTimeHelper;

class IntelligentOccurrenceManager {
    
    private $datetime_helper;
    
    public function __construct() {
        $this->datetime_helper = new DateTimeHelper();
        
        // WP-Cron hooks
        add_action('init', [$this, 'schedule_occurrence_generation']);
        add_action('wcefp_generate_occurrences', [$this, 'generate_pending_occurrences']);
        add_action('wcefp_daily_cleanup', [$this, 'cleanup_expired_occurrences']);
        
        // Admin hooks
        add_action('admin_menu', [$this, 'add_occurrence_menu'], 35);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_occurrence_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_wcefp_generate_occurrences_manual', [$this, 'generate_occurrences_manual_ajax']);
        add_action('wp_ajax_wcefp_get_occurrence_status', [$this, 'get_occurrence_status_ajax']);
        add_action('wp_ajax_wcefp_update_occurrence_settings', [$this, 'update_occurrence_settings_ajax']);
        
        // Event hooks
        add_action('save_post', [$this, 'trigger_occurrence_generation'], 10, 2);
        add_action('wcefp_event_updated', [$this, 'update_event_occurrences'], 10, 1);
        
        // Register custom post status for occurrences
        add_action('init', [$this, 'register_occurrence_status']);
    }
    
    /**
     * Schedule automatic occurrence generation
     */
    public function schedule_occurrence_generation() {
        if (!wp_next_scheduled('wcefp_generate_occurrences')) {
            wp_schedule_event(time(), 'daily', 'wcefp_generate_occurrences');
        }
    }
    
    /**
     * Generate pending event occurrences
     */
    public function generate_pending_occurrences() {
        $this->log_occurrence_event('Starting automatic occurrence generation');
        
        $events_to_process = $this->get_events_needing_occurrences();
        $generated_count = 0;
        $error_count = 0;
        
        foreach ($events_to_process as $event_id) {
            try {
                $result = $this->generate_event_occurrences($event_id);
                
                if ($result['success']) {
                    $generated_count += $result['generated'];
                } else {
                    $error_count++;
                    $this->log_occurrence_event("Error generating occurrences for event {$event_id}: " . $result['message']);
                }
                
            } catch (Exception $e) {
                $error_count++;
                $this->log_occurrence_event("Exception generating occurrences for event {$event_id}: " . $e->getMessage());
            }
        }
        
        $this->log_occurrence_event("Occurrence generation completed: {$generated_count} generated, {$error_count} errors");
        
        // Update generation statistics
        update_option('wcefp_occurrence_stats', [
            'last_run' => current_time('mysql'),
            'generated_count' => $generated_count,
            'error_count' => $error_count,
            'events_processed' => count($events_to_process)
        ]);
    }
    
    /**
     * Generate occurrences for a specific event
     *
     * @param int $event_id Event ID
     * @return array Generation result
     */
    public function generate_event_occurrences($event_id) {
        $event = get_post($event_id);
        
        if (!$event || $event->post_type !== 'event') {
            return [
                'success' => false,
                'message' => 'Invalid event ID',
                'generated' => 0
            ];
        }
        
        $occurrence_settings = $this->get_event_occurrence_settings($event_id);
        
        if (!$occurrence_settings['enabled']) {
            return [
                'success' => true,
                'message' => 'Occurrence generation disabled for this event',
                'generated' => 0
            ];
        }
        
        $existing_occurrences = $this->get_existing_occurrences($event_id);
        $rolling_window_days = get_option('wcefp_occurrence_rolling_window', 60);
        
        $end_date = new \DateTime("+{$rolling_window_days} days");
        $start_date = $this->get_last_occurrence_date($event_id) ?: new \DateTime();
        
        $new_occurrences = $this->calculate_occurrence_dates(
            $start_date,
            $end_date,
            $occurrence_settings
        );
        
        $generated_count = 0;
        
        foreach ($new_occurrences as $occurrence_date) {
            // Check for duplicates
            if ($this->occurrence_exists($event_id, $occurrence_date)) {
                continue;
            }
            
            $occurrence_id = $this->create_occurrence($event_id, $occurrence_date, $occurrence_settings);
            
            if ($occurrence_id && !is_wp_error($occurrence_id)) {
                $generated_count++;
            }
        }
        
        return [
            'success' => true,
            'message' => "Generated {$generated_count} occurrences",
            'generated' => $generated_count
        ];
    }
    
    /**
     * Get events that need occurrence generation
     *
     * @return array Event IDs
     */
    private function get_events_needing_occurrences() {
        global $wpdb;
        
        $rolling_window_days = get_option('wcefp_occurrence_rolling_window', 60);
        $threshold_date = date('Y-m-d H:i:s', strtotime("+{$rolling_window_days} days"));
        
        // Find events with occurrence generation enabled that need more occurrences
        $sql = $wpdb->prepare("
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_enabled ON p.ID = pm_enabled.post_id 
                AND pm_enabled.meta_key = '_wcefp_occurrence_enabled'
                AND pm_enabled.meta_value = 'yes'
            LEFT JOIN {$wpdb->posts} occurrences ON occurrences.post_parent = p.ID 
                AND occurrences.post_type = 'event_occurrence'
                AND occurrences.post_status = 'publish'
            LEFT JOIN {$wpdb->postmeta} pm_occ_date ON occurrences.ID = pm_occ_date.post_id 
                AND pm_occ_date.meta_key = '_occurrence_date'
                AND pm_occ_date.meta_value >= %s
            WHERE p.post_type = 'event'
            AND p.post_status = 'publish'
            GROUP BY p.ID
            HAVING COUNT(occurrences.ID) < 10 OR MAX(pm_occ_date.meta_value) < %s
        ", $threshold_date, $threshold_date);
        
        $results = $wpdb->get_col($sql);
        
        return array_map('intval', $results);
    }
    
    /**
     * Calculate occurrence dates based on settings
     *
     * @param DateTime $start_date Start date
     * @param DateTime $end_date End date
     * @param array $settings Occurrence settings
     * @return array Array of DateTime objects
     */
    private function calculate_occurrence_dates($start_date, $end_date, $settings) {
        $occurrences = [];
        $current_date = clone $start_date;
        
        // Skip to next day if start date is in the past
        if ($current_date < new \DateTime()) {
            $current_date = new \DateTime('tomorrow');
        }
        
        switch ($settings['pattern']) {
            case 'daily':
                $interval = new \DateInterval('P1D');
                break;
            case 'weekly':
                $interval = new \DateInterval('P7D');
                break;
            case 'monthly':
                $interval = new \DateInterval('P1M');
                break;
            case 'custom':
                $interval = new \DateInterval('P' . $settings['interval_days'] . 'D');
                break;
            default:
                return $occurrences;
        }
        
        while ($current_date <= $end_date && count($occurrences) < 50) {
            // Check if date matches occurrence criteria
            if ($this->date_matches_occurrence_rules($current_date, $settings)) {
                $occurrence_date = clone $current_date;
                
                // Set specific time if configured
                if (!empty($settings['time'])) {
                    $time_parts = explode(':', $settings['time']);
                    $occurrence_date->setTime(
                        intval($time_parts[0]),
                        intval($time_parts[1] ?? 0)
                    );
                }
                
                $occurrences[] = $occurrence_date;
            }
            
            $current_date->add($interval);
        }
        
        return $occurrences;
    }
    
    /**
     * Check if date matches occurrence rules
     *
     * @param DateTime $date Date to check
     * @param array $settings Occurrence settings
     * @return bool True if matches
     */
    private function date_matches_occurrence_rules($date, $settings) {
        // Check excluded days of week
        if (!empty($settings['excluded_days'])) {
            $day_of_week = $date->format('w');
            if (in_array($day_of_week, $settings['excluded_days'])) {
                return false;
            }
        }
        
        // Check specific excluded dates
        if (!empty($settings['excluded_dates'])) {
            $date_string = $date->format('Y-m-d');
            if (in_array($date_string, $settings['excluded_dates'])) {
                return false;
            }
        }
        
        // Check seasonal restrictions
        if (!empty($settings['seasons'])) {
            $month = intval($date->format('n'));
            if (!in_array($month, $settings['seasons'])) {
                return false;
            }
        }
        
        // Check capacity limits (if tracking is enabled)
        if (!empty($settings['max_occurrences_per_day'])) {
            $existing_count = $this->get_occurrences_count_for_date($date);
            if ($existing_count >= $settings['max_occurrences_per_day']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create a new occurrence
     *
     * @param int $event_id Parent event ID
     * @param DateTime $occurrence_date Occurrence date
     * @param array $settings Occurrence settings
     * @return int|WP_Error Occurrence ID or error
     */
    private function create_occurrence($event_id, $occurrence_date, $settings) {
        $parent_event = get_post($event_id);
        
        $occurrence_data = [
            'post_type' => 'event_occurrence',
            'post_status' => 'publish',
            'post_title' => $parent_event->post_title . ' - ' . $occurrence_date->format('M j, Y'),
            'post_content' => $parent_event->post_content,
            'post_parent' => $event_id,
            'meta_input' => [
                '_occurrence_date' => $occurrence_date->format('Y-m-d H:i:s'),
                '_parent_event_id' => $event_id,
                '_occurrence_status' => 'auto_generated',
                '_max_capacity' => get_post_meta($event_id, '_max_capacity', true),
                '_price' => get_post_meta($event_id, '_price', true),
                '_duration' => get_post_meta($event_id, '_duration', true),
                '_generated_at' => current_time('mysql'),
                '_occurrence_settings' => json_encode($settings)
            ]
        ];
        
        $occurrence_id = wp_insert_post($occurrence_data, true);
        
        if (!is_wp_error($occurrence_id)) {
            // Copy relevant meta data from parent event
            $this->copy_event_meta_to_occurrence($event_id, $occurrence_id);
            
            // Trigger action for integrations
            do_action('wcefp_occurrence_created', $occurrence_id, $event_id, $occurrence_date);
            
            $this->log_occurrence_event("Created occurrence {$occurrence_id} for event {$event_id} on " . $occurrence_date->format('Y-m-d H:i:s'));
        }
        
        return $occurrence_id;
    }
    
    /**
     * Copy meta data from parent event to occurrence
     *
     * @param int $event_id Parent event ID
     * @param int $occurrence_id Occurrence ID
     */
    private function copy_event_meta_to_occurrence($event_id, $occurrence_id) {
        $meta_to_copy = [
            '_event_category',
            '_event_location',
            '_event_description',
            '_required_resources',
            '_event_tags',
            '_booking_requirements',
            '_cancellation_policy',
            '_featured_image_id'
        ];
        
        foreach ($meta_to_copy as $meta_key) {
            $meta_value = get_post_meta($event_id, $meta_key, true);
            if (!empty($meta_value)) {
                update_post_meta($occurrence_id, $meta_key, $meta_value);
            }
        }
        
        // Copy taxonomies
        $taxonomies = get_object_taxonomies('event');
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($event_id, $taxonomy, ['fields' => 'ids']);
            if (!empty($terms) && !is_wp_error($terms)) {
                wp_set_object_terms($occurrence_id, $terms, $taxonomy);
            }
        }
    }
    
    /**
     * Get event occurrence settings
     *
     * @param int $event_id Event ID
     * @return array Occurrence settings
     */
    private function get_event_occurrence_settings($event_id) {
        $defaults = [
            'enabled' => false,
            'pattern' => 'weekly',
            'interval_days' => 7,
            'time' => '10:00',
            'excluded_days' => [],
            'excluded_dates' => [],
            'seasons' => [],
            'max_occurrences_per_day' => null
        ];
        
        $saved_settings = get_post_meta($event_id, '_wcefp_occurrence_settings', true);
        
        return wp_parse_args($saved_settings ?: [], $defaults);
    }
    
    /**
     * Check if occurrence exists for date
     *
     * @param int $event_id Event ID
     * @param DateTime $date Date to check
     * @return bool True if exists
     */
    private function occurrence_exists($event_id, $date) {
        global $wpdb;
        
        $date_string = $date->format('Y-m-d H:i:s');
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_occurrence_date'
            WHERE p.post_parent = %d
            AND p.post_type = 'event_occurrence'
            AND pm.meta_value = %s
        ", $event_id, $date_string));
        
        return $count > 0;
    }
    
    /**
     * Get existing occurrences for event
     *
     * @param int $event_id Event ID
     * @return array Occurrence data
     */
    private function get_existing_occurrences($event_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, pm.meta_value as occurrence_date
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_occurrence_date'
            WHERE p.post_parent = %d
            AND p.post_type = 'event_occurrence'
            AND p.post_status = 'publish'
            ORDER BY pm.meta_value ASC
        ", $event_id), ARRAY_A);
    }
    
    /**
     * Get last occurrence date for event
     *
     * @param int $event_id Event ID
     * @return DateTime|null Last occurrence date
     */
    private function get_last_occurrence_date($event_id) {
        global $wpdb;
        
        $last_date = $wpdb->get_var($wpdb->prepare("
            SELECT MAX(pm.meta_value)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_occurrence_date'
            WHERE p.post_parent = %d
            AND p.post_type = 'event_occurrence'
            AND p.post_status = 'publish'
        ", $event_id));
        
        return $last_date ? new \DateTime($last_date) : null;
    }
    
    /**
     * Get occurrences count for specific date
     *
     * @param DateTime $date Date to check
     * @return int Count of occurrences
     */
    private function get_occurrences_count_for_date($date) {
        global $wpdb;
        
        $date_string = $date->format('Y-m-d');
        
        return intval($wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_occurrence_date'
            WHERE p.post_type = 'event_occurrence'
            AND p.post_status = 'publish'
            AND DATE(pm.meta_value) = %s
        ", $date_string)));
    }
    
    /**
     * Log occurrence generation events
     *
     * @param string $message Log message
     */
    private function log_occurrence_event($message) {
        $log_enabled = get_option('wcefp_occurrence_logging', 'yes');
        
        if ($log_enabled === 'yes') {
            $log_entry = [
                'timestamp' => current_time('mysql'),
                'message' => $message,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];
            
            // Store in occurrence log table or option
            $existing_logs = get_option('wcefp_occurrence_logs', []);
            $existing_logs[] = $log_entry;
            
            // Keep only last 100 log entries
            if (count($existing_logs) > 100) {
                $existing_logs = array_slice($existing_logs, -100);
            }
            
            update_option('wcefp_occurrence_logs', $existing_logs);
            
            // Also log to WordPress debug if enabled
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[WCEFP Occurrences] ' . $message);
            }
        }
    }
    
    /**
     * Cleanup expired occurrences
     */
    public function cleanup_expired_occurrences() {
        global $wpdb;
        
        $cleanup_enabled = get_option('wcefp_occurrence_cleanup', 'yes');
        if ($cleanup_enabled !== 'yes') {
            return;
        }
        
        $retention_days = get_option('wcefp_occurrence_retention_days', 30);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Delete expired occurrences
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE p, pm
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = '_occurrence_date'
            WHERE p.post_type = 'event_occurrence'
            AND pm_date.meta_value < %s
        ", $cutoff_date));
        
        $this->log_occurrence_event("Cleanup completed: {$deleted} expired occurrences deleted");
    }
    
    /**
     * Trigger occurrence generation when event is saved
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function trigger_occurrence_generation($post_id, $post) {
        if ($post->post_type !== 'event' || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Check if occurrence generation is enabled
        $settings = $this->get_event_occurrence_settings($post_id);
        if ($settings['enabled']) {
            // Schedule background generation
            wp_schedule_single_event(time() + 30, 'wcefp_generate_single_event_occurrences', [$post_id]);
        }
    }
    
    /**
     * AJAX handlers
     */
    
    public function generate_occurrences_manual_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'wcefp_occurrence_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed', 'wceventsfp')]);
        }
        
        if (!current_user_can('manage_wcefp_events')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'wceventsfp')]);
        }
        
        $event_id = absint($_POST['event_id'] ?? 0);
        
        if ($event_id) {
            // Generate for specific event
            $result = $this->generate_event_occurrences($event_id);
            wp_send_json_success($result);
        } else {
            // Generate for all events
            $this->generate_pending_occurrences();
            $stats = get_option('wcefp_occurrence_stats', []);
            wp_send_json_success($stats);
        }
    }
    
    /**
     * Add occurrence management menu
     */
    public function add_occurrence_menu() {
        add_submenu_page(
            'edit.php?post_type=event',
            __('Occurrence Management', 'wceventsfp'),
            __('Occurrences', 'wceventsfp'),
            'manage_wcefp_events',
            'wcefp-occurrence-management',
            [$this, 'render_occurrence_management_page']
        );
    }
    
    /**
     * Register occurrence post status
     */
    public function register_occurrence_status() {
        register_post_status('auto_generated', [
            'label' => __('Auto Generated', 'wceventsfp'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'Auto Generated <span class="count">(%s)</span>',
                'Auto Generated <span class="count">(%s)</span>',
                'wceventsfp'
            )
        ]);
        
        register_post_type('event_occurrence', [
            'labels' => [
                'name' => __('Event Occurrences', 'wceventsfp'),
                'singular_name' => __('Event Occurrence', 'wceventsfp')
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor'],
            'hierarchical' => true
        ]);
    }
    
    /**
     * Enqueue occurrence management assets
     */
    public function enqueue_occurrence_assets($hook_suffix) {
        if ($hook_suffix !== 'event_page_wcefp-occurrence-management') {
            return;
        }
        
        wp_enqueue_script(
            'wcefp-occurrence-management',
            plugin_dir_url(__FILE__) . '../../assets/js/occurrence-management.js',
            ['jquery'],
            '2.2.0',
            true
        );
        
        wp_localize_script('wcefp-occurrence-management', 'wcefp_occurrences', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_occurrence_nonce'),
            'strings' => [
                'generating' => __('Generating occurrences...', 'wceventsfp'),
                'success' => __('Occurrences generated successfully', 'wceventsfp'),
                'error' => __('Error generating occurrences', 'wceventsfp')
            ]
        ]);
    }
    
    /**
     * Render occurrence management page
     */
    public function render_occurrence_management_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Intelligent Occurrence Management', 'wceventsfp') . '</h1>';
        echo '<p>' . __('Automated generation and management of event occurrences.', 'wceventsfp') . '</p>';
        
        // Show generation statistics
        $stats = get_option('wcefp_occurrence_stats', []);
        if ($stats) {
            echo '<div class="notice notice-info"><p>';
            echo sprintf(__('Last run: %s | Generated: %d | Errors: %d', 'wceventsfp'), 
                $stats['last_run'] ?? 'Never',
                $stats['generated_count'] ?? 0,
                $stats['error_count'] ?? 0
            );
            echo '</p></div>';
        }
        
        echo '<button id="generate-occurrences" class="button button-primary">' . 
             __('Generate Occurrences Now', 'wceventsfp') . '</button>';
        echo '</div>';
    }
}