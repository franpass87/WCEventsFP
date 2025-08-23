<?php
/**
 * Phase 5 Service Provider
 * 
 * Initializes and manages Advanced Booking Features
 * Part of Phase 5: Advanced Booking Features
 *
 * @package WCEFP
 * @subpackage BookingFeatures
 * @since 2.2.0
 */

namespace WCEFP\BookingFeatures;

use WCEFP\BookingFeatures\DigitalCheckinManager;
use WCEFP\BookingFeatures\ResourceManager;
use WCEFP\BookingFeatures\MultiEventBookingManager;
use WCEFP\BookingFeatures\AdvancedPricingManager;

class BookingFeaturesServiceProvider {
    
    private $digital_checkin_manager;
    private $resource_manager;
    private $multi_event_booking_manager;
    private $advanced_pricing_manager;
    
    public function __construct() {
        add_action('init', [$this, 'initialize'], 20);
        add_action('admin_init', [$this, 'initialize_admin']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Initialize Phase 5 features
     */
    public function initialize() {
        // Check if dependencies are loaded
        if (!class_exists('WCEFP\Core\Database\BookingRepository')) {
            add_action('admin_notices', [$this, 'dependency_notice']);
            return;
        }
        
        // Initialize core managers
        $this->digital_checkin_manager = new DigitalCheckinManager();
        $this->resource_manager = new ResourceManager();
        $this->multi_event_booking_manager = new MultiEventBookingManager();
        $this->advanced_pricing_manager = new AdvancedPricingManager();
        
        // Add plugin update hooks
        add_action('plugins_loaded', [$this, 'check_version_update']);
        add_action('wp_loaded', [$this, 'register_endpoints']);
        
        // Add feature availability checks
        add_filter('wcefp_available_features', [$this, 'add_available_features']);
        
        // Register custom post statuses for bookings
        $this->register_booking_statuses();
        
        // Initialize feature-specific hooks
        $this->initialize_feature_hooks();
    }
    
    /**
     * Initialize admin-specific functionality
     */
    public function initialize_admin() {
        if (!is_admin()) {
            return;
        }
        
        // Add admin menu items
        add_action('admin_menu', [$this, 'add_admin_menus'], 25);
        
        // Add settings sections
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Add admin notices for feature status
        add_action('admin_notices', [$this, 'admin_feature_notices']);
    }
    
    /**
     * Register custom endpoints for Phase 5 features
     */
    public function register_endpoints() {
        // Check-in endpoint
        add_rewrite_rule(
            '^wcefp-checkin/?$',
            'index.php?wcefp_checkin=1',
            'top'
        );
        
        add_rewrite_tag('%wcefp_checkin%', '([^&]+)');
        
        // Availability calendar endpoint
        add_rewrite_rule(
            '^wcefp-availability/([^/]+)/?$',
            'index.php?wcefp_availability=1&event_id=$matches[1]',
            'top'
        );
        
        add_rewrite_tag('%wcefp_availability%', '([^&]+)');
        add_rewrite_tag('%event_id%', '([^&]+)');
        
        // Handle template redirects
        add_action('template_redirect', [$this, 'handle_custom_endpoints']);
    }
    
    /**
     * Handle custom endpoint templates
     */
    public function handle_custom_endpoints() {
        global $wp_query;
        
        if (isset($wp_query->query_vars['wcefp_checkin'])) {
            $this->load_checkin_template();
            exit;
        }
        
        if (isset($wp_query->query_vars['wcefp_availability'])) {
            $this->load_availability_template();
            exit;
        }
    }
    
    /**
     * Load check-in template
     */
    private function load_checkin_template() {
        // Get token and booking ID from URL
        $token = sanitize_text_field($_GET['token'] ?? '');
        $booking_id = absint($_GET['booking'] ?? 0);
        
        // Set up template variables
        $template_vars = [
            'token' => $token,
            'booking_id' => $booking_id,
            'auto_checkin' => !empty($token) && !empty($booking_id)
        ];
        
        // Load template
        include plugin_dir_path(__FILE__) . '../../templates/checkin-page.php';
    }
    
    /**
     * Load availability template
     */
    private function load_availability_template() {
        global $wp_query;
        
        $event_id = absint($wp_query->query_vars['event_id'] ?? 0);
        
        if (!$event_id) {
            wp_die(__('Invalid event ID', 'wceventsfp'));
        }
        
        $template_vars = [
            'event_id' => $event_id,
            'event' => get_post($event_id)
        ];
        
        include plugin_dir_path(__FILE__) . '../../templates/availability-page.php';
    }
    
    /**
     * Register custom booking statuses
     */
    private function register_booking_statuses() {
        register_post_status('checked_in', [
            'label' => __('Checked In', 'wceventsfp'),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'Checked In <span class="count">(%s)</span>',
                'Checked In <span class="count">(%s)</span>',
                'wceventsfp'
            ),
        ]);
        
        register_post_status('no_show', [
            'label' => __('No Show', 'wceventsfp'),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'No Show <span class="count">(%s)</span>',
                'No Show <span class="count">(%s)</span>',
                'wceventsfp'
            ),
        ]);
    }
    
    /**
     * Initialize feature-specific hooks
     */
    private function initialize_feature_hooks() {
        // Digital check-in hooks
        add_action('wcefp_booking_confirmed', [$this->digital_checkin_manager, 'generate_checkin_qr_code'], 10, 1);
        
        // Resource management hooks
        add_action('wcefp_before_booking_save', [$this->resource_manager, 'validate_resource_availability'], 10, 2);
        
        // Multi-event booking hooks
        add_filter('woocommerce_add_cart_item_data', [$this->multi_event_booking_manager, 'add_cart_item_data'], 10, 3);
        
        // Advanced pricing hooks
        add_filter('wcefp_booking_price', [$this->advanced_pricing_manager, 'calculate_dynamic_price'], 10, 3);
        
        // Email integration hooks
        add_filter('wcefp_email_template_vars', [$this, 'add_checkin_email_vars'], 10, 3);
        
        // Admin column hooks
        add_filter('manage_booking_posts_columns', [$this, 'add_booking_columns']);
        add_action('manage_booking_posts_custom_column', [$this, 'display_booking_columns'], 10, 2);
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menus() {
        // Check-in management submenu
        add_submenu_page(
            'edit.php?post_type=booking',
            __('Check-in Management', 'wceventsfp'),
            __('Check-in', 'wceventsfp'),
            'manage_wcefp_bookings',
            'wcefp-checkin-management',
            [$this, 'render_checkin_management_page']
        );
        
        // Resource management submenu
        add_submenu_page(
            'edit.php?post_type=booking',
            __('Resource Management', 'wceventsfp'),
            __('Resources', 'wceventsfp'),
            'manage_wcefp_resources',
            'wcefp-resource-management',
            [$this, 'render_resource_management_page']
        );
        
        // Availability calendar submenu
        add_submenu_page(
            'edit.php?post_type=booking',
            __('Availability Calendar', 'wceventsfp'),
            __('Availability', 'wceventsfp'),
            'manage_wcefp_bookings',
            'wcefp-availability-calendar',
            [$this, 'render_availability_calendar_page']
        );
    }
    
    /**
     * Register Phase 5 settings
     */
    public function register_settings() {
        // Check-in settings
        register_setting('wcefp_checkin_settings', 'wcefp_checkin_notifications');
        register_setting('wcefp_checkin_settings', 'wcefp_qr_code_expiry_days');
        register_setting('wcefp_checkin_settings', 'wcefp_checkin_buffer_minutes');
        
        // Resource management settings
        register_setting('wcefp_resource_settings', 'wcefp_resource_conflict_handling');
        register_setting('wcefp_resource_settings', 'wcefp_resource_utilization_alerts');
        register_setting('wcefp_resource_settings', 'wcefp_availability_cache_minutes');
        
        // Multi-event booking settings
        register_setting('wcefp_booking_settings', 'wcefp_multi_event_discount');
        register_setting('wcefp_booking_settings', 'wcefp_booking_flow_type');
        register_setting('wcefp_booking_settings', 'wcefp_max_events_per_booking');
        
        // Advanced pricing settings
        register_setting('wcefp_pricing_settings', 'wcefp_dynamic_pricing_enabled');
        register_setting('wcefp_pricing_settings', 'wcefp_group_discount_tiers');
        register_setting('wcefp_pricing_settings', 'wcefp_seasonal_pricing_rules');
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on relevant admin pages
        $relevant_pages = [
            'edit.php',
            'post.php',
            'post-new.php',
            'booking_page_wcefp-checkin-management',
            'booking_page_wcefp-resource-management',
            'booking_page_wcefp-availability-calendar'
        ];
        
        if (!in_array($hook_suffix, $relevant_pages)) {
            return;
        }
        
        // Check if we're on booking-related pages
        global $post_type;
        if ($hook_suffix === 'edit.php' && $post_type !== 'booking') {
            return;
        }
        
        wp_enqueue_script(
            'wcefp-phase5-admin',
            plugin_dir_url(__FILE__) . '../../assets/js/phase5-admin.js',
            ['jquery', 'wp-api-fetch'],
            '2.2.0',
            true
        );
        
        wp_enqueue_style(
            'wcefp-phase5-admin',
            plugin_dir_url(__FILE__) . '../../assets/css/phase5-admin.css',
            [],
            '2.2.0'
        );
        
        // Localize script with admin data
        wp_localize_script('wcefp-phase5-admin', 'wcefp_phase5', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('wcefp/v2/'),
            'nonce' => wp_create_nonce('wcefp_phase5_nonce'),
            'strings' => [
                'generating_qr' => __('Generating QR code...', 'wceventsfp'),
                'checking_availability' => __('Checking availability...', 'wceventsfp'),
                'processing_checkin' => __('Processing check-in...', 'wceventsfp'),
                'confirm_action' => __('Are you sure you want to perform this action?', 'wceventsfp')
            ],
            'features_enabled' => [
                'digital_checkin' => true,
                'resource_management' => true,
                'multi_event_booking' => true,
                'advanced_pricing' => true
            ]
        ]);
    }
    
    /**
     * Add available features to the features list
     */
    public function add_available_features($features) {
        $features['phase5'] = [
            'name' => __('Phase 5: Advanced Booking Features', 'wceventsfp'),
            'description' => __('Digital check-in, resource management, multi-event booking, and advanced pricing', 'wceventsfp'),
            'version' => '2.2.0',
            'components' => [
                'digital_checkin' => __('Digital Check-in System', 'wceventsfp'),
                'resource_management' => __('Resource Management & Conflict Detection', 'wceventsfp'),
                'multi_event_booking' => __('Multi-Event Booking Flow', 'wceventsfp'),
                'advanced_pricing' => __('Advanced Pricing Features', 'wceventsfp')
            ]
        ];
        
        return $features;
    }
    
    /**
     * Add check-in related variables to email templates
     */
    public function add_checkin_email_vars($template_vars, $template_type, $booking_id) {
        if (in_array($template_type, ['booking_confirmation', 'booking_reminder'])) {
            $qr_code = get_post_meta($booking_id, '_wcefp_checkin_qr_code', true);
            $token = get_post_meta($booking_id, '_wcefp_checkin_token', true);
            
            if ($qr_code && $token) {
                $checkin_url = add_query_arg([
                    'wcefp_checkin' => 1,
                    'token' => $token,
                    'booking' => $booking_id
                ], home_url('/wcefp-checkin/'));
                
                $template_vars['checkin_qr_code'] = $qr_code;
                $template_vars['checkin_url'] = $checkin_url;
                $template_vars['checkin_instructions'] = __(
                    'Use this QR code or link to check in at the event location.',
                    'wceventsfp'
                );
            }
        }
        
        return $template_vars;
    }
    
    /**
     * Add custom columns to booking list
     */
    public function add_booking_columns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add checkin status after title
            if ($key === 'title') {
                $new_columns['checkin_status'] = __('Check-in Status', 'wceventsfp');
            }
            
            // Add resource conflicts after date
            if ($key === 'date') {
                $new_columns['resource_status'] = __('Resources', 'wceventsfp');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Display custom booking columns
     */
    public function display_booking_columns($column, $post_id) {
        switch ($column) {
            case 'checkin_status':
                $status = get_post_meta($post_id, '_wcefp_checkin_status', true);
                $checkin_time = get_post_meta($post_id, '_wcefp_checkin_time', true);
                
                switch ($status) {
                    case 'checked_in':
                        echo '<span class="wcefp-status checked-in" title="' . 
                             esc_attr($checkin_time) . '">' . 
                             __('Checked In', 'wceventsfp') . '</span>';
                        break;
                    case 'no_show':
                        echo '<span class="wcefp-status no-show">' . 
                             __('No Show', 'wceventsfp') . '</span>';
                        break;
                    default:
                        echo '<span class="wcefp-status pending">' . 
                             __('Pending', 'wceventsfp') . '</span>';
                        break;
                }
                break;
                
            case 'resource_status':
                $event_id = get_post_meta($post_id, '_event_id', true);
                $date_time = get_post_meta($post_id, '_event_date_time', true);
                
                if ($event_id && $date_time) {
                    $availability = $this->resource_manager->check_availability($event_id, $date_time);
                    
                    if (is_array($availability)) {
                        if ($availability['available']) {
                            echo '<span class="wcefp-resource-status available">' . 
                                 __('Available', 'wceventsfp') . '</span>';
                        } else {
                            $conflict_count = count($availability['conflicts']);
                            echo '<span class="wcefp-resource-status conflict" title="' . 
                                 sprintf(_n('%d conflict', '%d conflicts', $conflict_count, 'wceventsfp'), $conflict_count) . 
                                 '">' . __('Conflict', 'wceventsfp') . '</span>';
                        }
                    }
                }
                break;
        }
    }
    
    /**
     * Plugin activation hook
     */
    public function activate() {
        // Create database tables
        DigitalCheckinManager::create_checkin_tables();
        ResourceManager::create_resource_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set default options
        add_option('wcefp_checkin_notifications', 'yes');
        add_option('wcefp_qr_code_expiry_days', 30);
        add_option('wcefp_checkin_buffer_minutes', 15);
        add_option('wcefp_resource_conflict_handling', 'block');
        add_option('wcefp_resource_utilization_alerts', 'yes');
        add_option('wcefp_availability_cache_minutes', 15);
        
        // Schedule cleanup cron jobs
        if (!wp_next_scheduled('wcefp_cleanup_expired_tokens')) {
            wp_schedule_event(time(), 'daily', 'wcefp_cleanup_expired_tokens');
        }
        
        if (!wp_next_scheduled('wcefp_cleanup_qr_cache')) {
            wp_schedule_event(time(), 'weekly', 'wcefp_cleanup_qr_cache');
        }
    }
    
    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('wcefp_cleanup_expired_tokens');
        wp_clear_scheduled_hook('wcefp_cleanup_qr_cache');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Check for version updates and run migrations
     */
    public function check_version_update() {
        $current_version = get_option('wcefp_phase5_version', '0.0.0');
        $plugin_version = '2.2.0';
        
        if (version_compare($current_version, $plugin_version, '<')) {
            // Run migrations if needed
            $this->run_migrations($current_version, $plugin_version);
            
            // Update version
            update_option('wcefp_phase5_version', $plugin_version);
        }
    }
    
    /**
     * Run database migrations
     */
    private function run_migrations($from_version, $to_version) {
        // Add migration logic here as needed
        // Example: if (version_compare($from_version, '2.1.0', '<')) { ... }
    }
    
    /**
     * Display dependency notice
     */
    public function dependency_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('WCEFP Phase 5: Advanced Booking Features requires the core WCEFP plugin to be active and up to date.', 'wceventsfp');
        echo '</p></div>';
    }
    
    /**
     * Display admin feature notices
     */
    public function admin_feature_notices() {
        // Display status of Phase 5 features
        $notices = [];
        
        // Check database tables
        global $wpdb;
        $tables_exist = [
            'checkin_tokens' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wcefp_checkin_tokens'"),
            'resource_bookings' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wcefp_resource_bookings'")
        ];
        
        if (!$tables_exist['checkin_tokens'] || !$tables_exist['resource_bookings']) {
            $notices[] = [
                'type' => 'warning',
                'message' => __('Some Phase 5 database tables are missing. Please deactivate and reactivate the plugin.', 'wceventsfp')
            ];
        }
        
        foreach ($notices as $notice) {
            echo "<div class='notice notice-{$notice['type']}'><p>{$notice['message']}</p></div>";
        }
    }
    
    /**
     * Render admin pages (placeholders for now)
     */
    public function render_checkin_management_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Check-in Management', 'wceventsfp') . '</h1>';
        echo '<p>' . __('Phase 5: Digital Check-in management interface will be implemented here.', 'wceventsfp') . '</p>';
        echo '</div>';
    }
    
    public function render_resource_management_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Resource Management', 'wceventsfp') . '</h1>';
        echo '<p>' . __('Phase 5: Resource management interface will be implemented here.', 'wceventsfp') . '</p>';
        echo '</div>';
    }
    
    public function render_availability_calendar_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Availability Calendar', 'wceventsfp') . '</h1>';
        echo '<p>' . __('Phase 5: Availability calendar interface will be implemented here.', 'wceventsfp') . '</p>';
        echo '</div>';
    }
}