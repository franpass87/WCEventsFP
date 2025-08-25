<?php
/**
 * Menu Manager
 * 
 * @package WCEFP
 * @subpackage Admin
 * @since 2.1.1
 */

namespace WCEFP\Admin;

use WCEFP\Core\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages WordPress admin menus for WCEFP
 */
class MenuManager {
    
    /**
     * DI Container
     * 
     * @var Container
     */
    private $container;
    
    /**
     * Constructor
     * 
     * @param Container $container DI container
     */
    public function __construct(Container $container) {
        $this->container = $container;
        $this->init();
    }
    
    /**
     * Initialize menu hooks
     * 
     * @return void
     */
    private function init() {
        add_action('admin_menu', [$this, 'add_admin_menu'], 10);
        add_action('admin_menu', [$this, 'remove_unwanted_menus'], 99);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts'], 10);
        
        // Add redirect for top-level menu page
        add_action('load-toplevel_page_wcefp-events', [$this, 'redirect_toplevel_to_bookings']);
        
        // Add AJAX handlers for booking quick actions
        add_action('wp_ajax_wcefp_booking_quick_action', [$this, 'handle_booking_quick_action']);
        add_action('wp_ajax_wcefp_get_booking_calendar_events', [$this, 'handle_get_calendar_events']);
        
        // Add AJAX handler for voucher table creation
        add_action('wp_ajax_wcefp_create_voucher_table', [$this, 'handle_create_voucher_table']);
        
        // Add AJAX handlers for voucher actions
        add_action('wp_ajax_wcefp_voucher_action', [$this, 'handle_voucher_action']);
    }
    
    /**
     * Load legacy admin classes
     * 
     * @return void
     */
    private function load_legacy_admin_classes() {
        // Load legacy recurring class
        $recurring_file = WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-recurring.php';
        if (file_exists($recurring_file)) {
            require_once $recurring_file;
        }
        
        // Load legacy closures class
        $closures_file = WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-closures.php';
        if (file_exists($closures_file)) {
            require_once $closures_file;
        }
        
        // Load legacy vouchers admin class
        $vouchers_file = WCEFP_PLUGIN_DIR . 'admin/class-wcefp-vouchers-admin.php';
        if (file_exists($vouchers_file)) {
            require_once $vouchers_file;
            
            // Also load the vouchers table class
            $vouchers_table_file = WCEFP_PLUGIN_DIR . 'admin/class-wcefp-vouchers-table.php';
            if (file_exists($vouchers_table_file)) {
                require_once $vouchers_table_file;
            }
        }
    }
    
    /**
     * Add admin menu items - rationalized structure
     * 
     * @return void
     */
    public function add_admin_menu() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        // Load legacy admin classes if they exist
        $this->load_legacy_admin_classes();
        
        // Main top-level menu page (redirects to Prenotazioni)
        add_menu_page(
            __('WC Events FP', 'wceventsfp'),
            __('WC Events FP', 'wceventsfp'),
            'manage_woocommerce',
            'wcefp-events', // Changed slug for consistency
            [$this, 'redirect_toplevel_to_bookings'], // Direct callback to redirect
            'dashicons-calendar-alt',
            56
        );
        
        // Rationalized submenu structure in requested order
        add_submenu_page(
            'wcefp-events',
            __('Bookings Management', 'wceventsfp'),
            __('Prenotazioni', 'wceventsfp'), // 1st position
            'manage_woocommerce',
            'wcefp-bookings',
            [$this, 'render_bookings_page']
        );
        
        add_submenu_page(
            'wcefp-events',
            __('Vouchers Management', 'wceventsfp'),
            __('Voucher', 'wceventsfp'), // 2nd position
            'manage_woocommerce',
            'wcefp-vouchers',
            [$this, 'render_vouchers_page']
        );
        
        add_submenu_page(
            'wcefp-events',
            __('Closures Management', 'wceventsfp'),
            __('Chiusure', 'wceventsfp'), // 3rd position
            'manage_woocommerce',
            'wcefp-closures',
            [$this, 'render_closures_page']
        );
        
        add_submenu_page(
            'wcefp-events',
            __('Plugin Settings', 'wceventsfp'),
            __('Impostazioni', 'wceventsfp'), // 4th position
            'manage_options',
            'wcefp-settings',
            [$this, 'render_settings_page']
        );
        
        // Optional advanced features (shown conditionally)
        $features_settings = get_option('wcefp_features_settings', []);
        
        if (!empty($features_settings['enable_meeting_points'])) {
            add_submenu_page(
                'wcefp-events',
                __('Meeting Points', 'wceventsfp'),
                __('Meeting Points', 'wceventsfp'), // 5th position if enabled
                'manage_woocommerce',
                'edit.php?post_type=wcefp_meeting_point',
                null // WordPress handles CPT pages automatically
            );
        }
        
        if (defined('WCEFP_SHOW_EXTRAS_MENU')) {
            add_submenu_page(
                'wcefp-events',
                __('Extra Services', 'wceventsfp'),
                __('Extras', 'wceventsfp'), // 6th position if enabled
                'manage_woocommerce',
                'wcefp-extras',
                [$this, 'render_extras_page']
            );
        }
        
        // Hidden booking view page (not shown in menu)
        add_submenu_page(
            null, // Hidden from menu
            __('View Booking Details', 'wceventsfp'),
            __('Visualizza Prenotazione', 'wceventsfp'),
            'manage_woocommerce',
            'wcefp-booking-view',
            [$this, 'render_booking_view_page']
        );
        
        // Hidden booking calendar (accessible via bookings page)
        add_submenu_page(
            null, // Hidden from main menu - accessible via bookings page
            __('Booking Calendar', 'wceventsfp'),
            __('Calendario Prenotazioni', 'wceventsfp'),
            'manage_woocommerce',
            'wcefp-booking-calendar',
            [$this, 'render_booking_calendar_page']
        );
    }
    
    /**
     * Enqueue admin scripts
     * 
     * @return void
     */
    public function enqueue_admin_scripts() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'wcefp') === false) {
            return;
        }
        
        // Enqueue modal system first
        wp_enqueue_script(
            'wcefp-modals',
            WCEFP_PLUGIN_URL . 'assets/js/wcefp-modals.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        // Enqueue settings page assets when on settings page
        if (strpos($screen->id, 'wcefp-settings') !== false) {
            $this->enqueue_settings_assets();
        }
        
        // Enqueue closures page assets when on closures page  
        if (strpos($screen->id, 'wcefp-closures') !== false) {
            wp_enqueue_style(
                'wcefp-admin-closures',
                WCEFP_PLUGIN_URL . 'assets/css/admin-closures.css',
                ['wp-admin'],
                WCEFP_VERSION
            );
        }
        
        // Enqueue booking calendar assets when on calendar page
        if (strpos($screen->id, 'wcefp-booking-calendar') !== false) {
            $this->enqueue_calendar_assets();
        }
    }
    
    /**
     * Enqueue settings page specific assets
     * 
     * @return void
     */
    private function enqueue_settings_assets() {
        // Enqueue admin settings CSS
        $settings_css = WCEFP_PLUGIN_URL . 'assets/css/admin-settings.css';
        if (file_exists(WCEFP_PLUGIN_DIR . 'assets/css/admin-settings.css')) {
            wp_enqueue_style(
                'wcefp-admin-settings',
                $settings_css,
                ['dashicons'],
                WCEFP_VERSION
            );
        }
        
        // Enqueue admin settings JS
        $settings_js = WCEFP_PLUGIN_URL . 'assets/js/admin-settings.js';
        if (file_exists(WCEFP_PLUGIN_DIR . 'assets/js/admin-settings.js')) {
            wp_enqueue_script(
                'wcefp-admin-settings',
                $settings_js,
                ['jquery', 'wp-util'],
                WCEFP_VERSION,
                true
            );
            
            // Localize script with settings data
            wp_localize_script('wcefp-admin-settings', 'wcefpSettings', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => rest_url('wcefp/v1/'),
                'nonce' => wp_create_nonce('wcefp_admin_settings'),
                'rest_nonce' => wp_create_nonce('wp_rest'),
                'strings' => [
                    'saving' => __('Saving...', 'wceventsfp'),
                    'saved' => __('Settings saved successfully', 'wceventsfp'),
                    'error' => __('Error saving settings', 'wceventsfp'),
                    'confirm_reset' => __('Are you sure you want to reset these settings?', 'wceventsfp'),
                ]
            ]);
        }
    }
    
    /**
     * Enqueue calendar assets
     * 
     * @return void
     */
    private function enqueue_calendar_assets() {
        // Calendar assets are handled in render_booking_calendar_page method
        // This is just a placeholder for future calendar-specific assets
    }
    
    /**
     * Remove unwanted menu items (already prevented in add_admin_menu)
     * 
     * @return void
     */
    public function remove_unwanted_menus() {
        // Dashboard and Performance pages no longer added - clean menu structure achieved
        // Remove any legacy pages that might still be registered by other plugins/features
        remove_submenu_page('wcefp-events', 'wcefp-dashboard');
        remove_submenu_page('wcefp-events', 'wcefp-performance'); 
        remove_submenu_page('wcefp-events', 'wcefp-occurrences'); // Remove old occurrences page
        
        // Also clean up any old menu structure references
        remove_submenu_page('wcefp', 'wcefp-dashboard');
        remove_submenu_page('wcefp', 'wcefp-performance');
    }
    
    /**
     * Redirect top-level menu page to Prenotazioni
     * 
     * @return void
     */
    public function redirect_toplevel_to_bookings() {
        // Direct redirect to bookings page as requested
        if (isset($_GET['page']) && $_GET['page'] === 'wcefp-events') {
            $redirect_url = admin_url('admin.php?page=wcefp-bookings');
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Render main admin page - should never be called due to redirect
     * 
     * @return void
     */
    public function render_main_page() {
        // This should not be reached due to load-toplevel_page_wcefp redirect
        wp_safe_redirect(admin_url('admin.php?page=wcefp-bookings'));
        exit;
    }
    
    /**
     * Render occurrences page
     * 
     * @return void
     */
    public function render_occurrences_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Occorrenze', 'wceventsfp') . '</h1>';
        
        // Check if we have access to occurrence management systems
        $has_recurring = class_exists('WCEFP_Recurring');
        $has_query_builder = false;
        
        try {
            $query_builder = $this->container->get('db.query_builder');
            $has_query_builder = ($query_builder !== null);
        } catch (\Exception $e) {
            // Query builder not available
        }
        
        if ($has_recurring || $has_query_builder) {
            echo '<div class="wcefp-occurrences-manager">';
            
            if ($has_recurring) {
                $this->render_occurrence_interface();
            }
            
            if ($has_query_builder) {
                echo '<h2>' . esc_html__('Existing Occurrences', 'wceventsfp') . '</h2>';
                try {
                    $occurrences = $query_builder->get_occurrences();
                    $this->render_occurrences_list($occurrences);
                } catch (\Exception $e) {
                    echo '<p class="notice notice-warning"><strong>' . esc_html__('Warning:', 'wceventsfp') . '</strong> ' . esc_html($e->getMessage()) . '</p>';
                }
            }
            
            echo '</div>';
        } else {
            echo '<div class="wcefp-occurrences-placeholder">';
            echo '<h2>' . esc_html__('Occurrence Management', 'wceventsfp') . '</h2>';
            echo '<p>' . esc_html__('The occurrence management system allows you to generate and manage event dates and times.', 'wceventsfp') . '</p>';
            echo '<p class="description">' . esc_html__('This feature is currently being loaded. Please ensure all plugin components are properly installed.', 'wceventsfp') . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render bookings page (legacy implementation)
     * 
     * @return void
     */
    public function render_bookings_page_legacy() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Prenotazioni', 'wceventsfp') . '</h1>';
        
        try {
            // Use the new WP_List_Table implementation for better UI/UX
            if (class_exists('WCEFP\Admin\Tables\BookingsListTable')) {
                $bookings_table = new \WCEFP\Admin\Tables\BookingsListTable();
                $bookings_table->prepare_items();
                
                echo '<form method="get" id="wcefp-bookings-filter">';
                echo '<input type="hidden" name="page" value="wcefp-bookings" />';
                $bookings_table->search_box(__('Search', 'wceventsfp'), 'bookings');
                $bookings_table->display();
                echo '</form>';
                
            } else {
                // Fallback to existing implementation
                $query_builder = $this->container->get('db.query_builder');
                if ($query_builder && method_exists($query_builder, 'get_bookings')) {
                    $bookings = $query_builder->get_bookings();
                    $this->render_bookings_list($bookings);
                } else {
                    echo '<div class="wcefp-bookings-placeholder">';
                    echo '<h2>' . esc_html__('Booking Management', 'wceventsfp') . '</h2>';
                    echo '<p>' . esc_html__('The booking management system displays and manages customer reservations.', 'wceventsfp') . '</p>';
                    echo '<p class="description">' . esc_html__('Query builder service not available. Please check plugin configuration.', 'wceventsfp') . '</p>';
                    echo '</div>';
                }
            }
        } catch (\Exception $e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . esc_html__('Error loading bookings:', 'wceventsfp') . '</strong> ' . esc_html($e->getMessage()) . '</p>';
            echo '<p class="description">' . esc_html__('This may be due to database connection issues or missing plugin components.', 'wceventsfp') . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render vouchers page with WP_List_Table (legacy implementation)
     * 
     * @return void
     */
    public function render_vouchers_page_legacy() {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wceventsfp'));
        }
        
        // Handle voucher actions
        $this->handle_voucher_page_actions();
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Vouchers', 'wceventsfp') . '</h1>';
        
        // Check if voucher table exists
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_vouchers';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($table_exists) {
            // Check if voucher admin classes are available
            if (class_exists('WCEFP_Vouchers_Admin') && method_exists('WCEFP_Vouchers_Admin', 'dispatch')) {
                // Use existing voucher system
                echo '</div>'; // Close our wrap
                WCEFP_Vouchers_Admin::dispatch();
                return;
            }
            
            // Use our enhanced WP_List_Table implementation
            $this->render_voucher_list_table();
            
        } else {
            // Show onboarding interface
            $this->render_voucher_onboarding();
        }
        
        echo '</div>';
    }
    
    /**
     * Handle voucher page actions
     * 
     * @return void
     */
    private function handle_voucher_page_actions() {
        if (!isset($_GET['action']) || !isset($_GET['voucher'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['action']);
        $voucher_id = absint($_GET['voucher']);
        
        // Verify nonce based on action
        $nonce_action = 'wcefp_voucher_' . $action . '_' . $voucher_id;
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', $nonce_action)) {
            wp_die(__('Security check failed.', 'wceventsfp'));
        }
        
        switch ($action) {
            case 'regen':
                $this->handle_voucher_regenerate($voucher_id);
                break;
            case 'resend':
                $this->handle_voucher_resend($voucher_id);
                break;
        }
    }
    
    /**
     * Render voucher list table
     * 
     * @return void
     */
    private function render_voucher_list_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_vouchers';
        
        // Get current page
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 20;
        $offset = ($current_page - 1) * $per_page;
        
        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Get vouchers for current page
        $vouchers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ), ARRAY_A);
        
        // Display the table
        ?>
        <div class="wcefp-voucher-list-container">
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'wceventsfp'); ?></label>
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk Actions', 'wceventsfp'); ?></option>
                        <option value="export"><?php _e('Export Selected', 'wceventsfp'); ?></option>
                        <option value="resend"><?php _e('Resend Selected', 'wceventsfp'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'wceventsfp'); ?>">
                </div>
                <?php
                // Pagination
                if ($total_items > $per_page) {
                    $page_links = paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => ceil($total_items / $per_page),
                        'current' => $current_page,
                    ]);
                    
                    if ($page_links) {
                        echo '<div class="tablenav-pages">';
                        echo '<span class="displaying-num">' . 
                             sprintf(_n('1 item', '%s items', $total_items, 'wceventsfp'), number_format_i18n($total_items)) . 
                             '</span>';
                        echo $page_links;
                        echo '</div>';
                    }
                }
                ?>
            </div>
            
            <table class="wp-list-table widefat fixed striped vouchers">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" />
                        </td>
                        <th class="manage-column column-code"><?php _e('Codice', 'wceventsfp'); ?></th>
                        <th class="manage-column column-order"><?php _e('Ordine', 'wceventsfp'); ?></th>
                        <th class="manage-column column-recipient"><?php _e('Destinatario', 'wceventsfp'); ?></th>
                        <th class="manage-column column-status"><?php _e('Stato', 'wceventsfp'); ?></th>
                        <th class="manage-column column-date"><?php _e('Data invio', 'wceventsfp'); ?></th>
                        <th class="manage-column column-actions"><?php _e('Azioni', 'wceventsfp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vouchers)): ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="7">
                            <?php _e('Nessun voucher trovato.', 'wceventsfp'); ?>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($vouchers as $voucher): ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="voucher[]" value="<?php echo esc_attr($voucher['id']); ?>" />
                            </th>
                            <td class="column-code">
                                <strong><?php echo esc_html($voucher['code']); ?></strong>
                            </td>
                            <td class="column-order">
                                <?php if ($voucher['order_id']): ?>
                                    <a href="<?php echo admin_url('post.php?post=' . $voucher['order_id'] . '&action=edit'); ?>">
                                        #<?php echo esc_html($voucher['order_id']); ?>
                                    </a>
                                <?php else: ?>
                                    <?php _e('N/A', 'wceventsfp'); ?>
                                <?php endif; ?>
                            </td>
                            <td class="column-recipient">
                                <?php echo esc_html($voucher['recipient_name'] ?: __('N/A', 'wceventsfp')); ?>
                                <?php if ($voucher['recipient_email']): ?>
                                    <br><small><?php echo esc_html($voucher['recipient_email']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="column-status">
                                <?php
                                $status_class = 'wcefp-status-' . sanitize_html_class($voucher['status']);
                                $status_text = ucfirst($voucher['status']);
                                ?>
                                <span class="wcefp-status-badge <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_text); ?>
                                </span>
                            </td>
                            <td class="column-date">
                                <?php 
                                if ($voucher['created_at']) {
                                    echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($voucher['created_at'])));
                                } else {
                                    _e('N/A', 'wceventsfp');
                                }
                                ?>
                            </td>
                            <td class="column-actions">
                                <?php
                                // Generate secure action URLs
                                $regen_url = wp_nonce_url(
                                    admin_url('admin.php?page=wcefp-vouchers&action=regen&voucher=' . $voucher['id']),
                                    'wcefp_voucher_regen_' . $voucher['id']
                                );
                                $resend_url = wp_nonce_url(
                                    admin_url('admin.php?page=wcefp-vouchers&action=resend&voucher=' . $voucher['id']),
                                    'wcefp_voucher_resend_' . $voucher['id']
                                );
                                ?>
                                <div class="row-actions">
                                    <span class="regen">
                                        <a href="<?php echo esc_url($regen_url); ?>" 
                                           onclick="return confirm('<?php echo esc_js(__('Rigenerare questo voucher?', 'wceventsfp')); ?>')">
                                            <?php _e('Rigenera', 'wceventsfp'); ?>
                                        </a>
                                    </span>
                                    <span class="resend"> |
                                        <a href="<?php echo esc_url($resend_url); ?>"
                                           onclick="return confirm('<?php echo esc_js(__('Reinviare questo voucher via email?', 'wceventsfp')); ?>')">
                                            <?php _e('Reinvia', 'wceventsfp'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="tablenav bottom">
                <?php
                // Bottom pagination
                if ($total_items > $per_page && $page_links) {
                    echo '<div class="tablenav-pages">';
                    echo '<span class="displaying-num">' . 
                         sprintf(_n('1 item', '%s items', $total_items, 'wceventsfp'), number_format_i18n($total_items)) . 
                         '</span>';
                    echo $page_links;
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        
        <!-- Add voucher styles -->
        <style>
        .wcefp-voucher-list-container {
            margin-top: 20px;
        }
        .wcefp-status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            color: white;
        }
        .wcefp-status-active { background-color: #48bb78; }
        .wcefp-status-pending { background-color: #f6ad55; }
        .wcefp-status-used { background-color: #4299e1; }
        .wcefp-status-expired { background-color: #f56565; }
        .wcefp-status-cancelled { background-color: #a0aec0; }
        .column-code { width: 120px; }
        .column-order { width: 80px; }
        .column-status { width: 80px; }
        .column-date { width: 140px; }
        .column-actions { width: 150px; }
        </style>
        <?php
    }
    
    /**
     * Handle voucher regeneration
     * 
     * @param int $voucher_id
     * @return void
     */
    private function handle_voucher_regenerate($voucher_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_vouchers';
        
        // Get voucher details
        $voucher = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $voucher_id), ARRAY_A);
        if (!$voucher) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Voucher non trovato.', 'wceventsfp') . '</p></div>';
            });
            return;
        }
        
        // Generate new code
        $new_code = 'WCEFP-' . strtoupper(wp_generate_password(8, false));
        
        // Update voucher
        $updated = $wpdb->update(
            $table_name,
            ['code' => $new_code, 'updated_at' => current_time('mysql')],
            ['id' => $voucher_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($updated) {
            add_action('admin_notices', function() use ($new_code) {
                echo '<div class="notice notice-success"><p>' . 
                     sprintf(__('Voucher rigenerato con successo. Nuovo codice: <strong>%s</strong>', 'wceventsfp'), $new_code) . 
                     '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Errore durante la rigenerazione del voucher.', 'wceventsfp') . '</p></div>';
            });
        }
    }
    
    /**
     * Handle voucher resend
     * 
     * @param int $voucher_id
     * @return void
     */
    private function handle_voucher_resend($voucher_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_vouchers';
        
        // Get voucher details
        $voucher = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $voucher_id), ARRAY_A);
        if (!$voucher) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Voucher non trovato.', 'wceventsfp') . '</p></div>';
            });
            return;
        }
        
        if (empty($voucher['recipient_email'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Impossibile inviare: email destinatario mancante.', 'wceventsfp') . '</p></div>';
            });
            return;
        }
        
        // Send email (placeholder implementation)
        $subject = sprintf(__('Il tuo voucher %s', 'wceventsfp'), $voucher['code']);
        $message = sprintf(
            __("Ciao %s,\n\nEcco il tuo voucher: %s\n\nValore: %s\n\nGrazie!", 'wceventsfp'),
            $voucher['recipient_name'] ?: __('Cliente', 'wceventsfp'),
            $voucher['code'],
            wc_price($voucher['value'])
        );
        
        $sent = wp_mail($voucher['recipient_email'], $subject, $message);
        
        if ($sent) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Voucher reinviato con successo via email.', 'wceventsfp') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Errore durante l\'invio dell\'email.', 'wceventsfp') . '</p></div>';
            });
        }
    }
    
    /**
     * Render basic voucher interface when full system not available
     * 
     * @return void
     */
    private function render_basic_voucher_interface() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_vouchers';
        
        // Get voucher count
        $voucher_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        echo '<div class="wcefp-voucher-interface">';
        echo '<div class="wcefp-voucher-stats">';
        echo '<div class="wcefp-stat-card">';
        echo '<h3>' . esc_html__('Total Vouchers', 'wceventsfp') . '</h3>';
        echo '<div class="wcefp-stat-number">' . absint($voucher_count) . '</div>';
        echo '</div>';
        
        // Get recent vouchers
        $recent_vouchers = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10",
            ARRAY_A
        );
        
        if (!empty($recent_vouchers)) {
            echo '<div class="wcefp-stat-card">';
            echo '<h3>' . esc_html__('Recent Vouchers', 'wceventsfp') . '</h3>';
            echo '<div class="wcefp-recent-vouchers">';
            foreach ($recent_vouchers as $voucher) {
                $status_class = 'wcefp-status-' . sanitize_html_class($voucher['status'] ?? 'pending');
                echo '<div class="wcefp-voucher-item">';
                echo '<strong>' . esc_html($voucher['code'] ?? 'N/A') . '</strong>';
                echo '<span class="wcefp-voucher-meta">';
                echo esc_html($voucher['recipient_name'] ?? 'N/A') . ' | ';
                echo '<span class="' . esc_attr($status_class) . '">' . esc_html(ucfirst($voucher['status'] ?? 'pending')) . '</span>';
                echo '</span>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        
        // Voucher actions
        echo '<div class="wcefp-voucher-actions">';
        echo '<h3>' . esc_html__('Voucher Actions', 'wceventsfp') . '</h3>';
        echo '<div class="wcefp-action-buttons">';
        
        // Generate new voucher button
        echo '<button type="button" class="button button-primary" onclick="wcefpGenerateVoucher()">';
        echo esc_html__('Generate New Voucher', 'wceventsfp');
        echo '</button>';
        
        // Export vouchers button
        echo '<button type="button" class="button button-secondary" onclick="wcefpExportVouchers()">';
        echo esc_html__('Export Vouchers', 'wceventsfp') . '</button>';
        
        echo '</div>';
        echo '</div>';
        
        // Status notice
        echo '<div class="notice notice-info">';
        echo '<p><strong>' . esc_html__('Voucher System Active', 'wceventsfp') . '</strong></p>';
        echo '<p>' . sprintf(
            esc_html__('The voucher database table exists with %d vouchers. The full voucher management interface will be available once all components are loaded.', 'wceventsfp'),
            absint($voucher_count)
        ) . '</p>';
        echo '</div>';
        
        echo '</div>';
        
        // Add basic JavaScript for voucher actions
        $this->add_voucher_scripts();
    }
    
    /**
     * Render voucher onboarding interface
     * 
     * @return void
     */
    private function render_voucher_onboarding() {
        echo '<div class="wcefp-voucher-onboarding">';
        echo '<div class="wcefp-onboarding-header">';
        echo '<h2>🎁 ' . esc_html__('Voucher System', 'wceventsfp') . '</h2>';
        echo '<p class="wcefp-onboarding-intro">' . esc_html__('Enable gift vouchers for your events and experiences to increase sales and customer satisfaction.', 'wceventsfp') . '</p>';
        echo '</div>';
        
        echo '<div class="wcefp-onboarding-features">';
        echo '<div class="wcefp-feature-grid">';
        
        // Feature cards
        $features = [
            [
                'icon' => '🎫',
                'title' => __('Digital Vouchers', 'wceventsfp'),
                'description' => __('Generate beautiful PDF vouchers with QR codes for easy redemption.', 'wceventsfp')
            ],
            [
                'icon' => '💳',
                'title' => __('Custom Values', 'wceventsfp'),
                'description' => __('Create vouchers with fixed or custom amounts for any experience.', 'wceventsfp')
            ],
            [
                'icon' => '📧',
                'title' => __('Email Delivery', 'wceventsfp'),
                'description' => __('Automatically send vouchers via email to recipients.', 'wceventsfp')
            ],
            [
                'icon' => '📊',
                'title' => __('Usage Tracking', 'wceventsfp'),
                'description' => __('Track voucher usage, redemption rates, and expiration dates.', 'wceventsfp')
            ]
        ];
        
        foreach ($features as $feature) {
            echo '<div class="wcefp-feature-card">';
            echo '<div class="wcefp-feature-icon">' . $feature['icon'] . '</div>';
            echo '<h4>' . esc_html($feature['title']) . '</h4>';
            echo '<p>' . esc_html($feature['description']) . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        echo '<div class="wcefp-onboarding-actions">';
        echo '<h3>' . esc_html__('How to Enable Vouchers', 'wceventsfp') . '</h3>';
        echo '<div class="wcefp-setup-steps">';
        
        echo '<div class="wcefp-setup-step">';
        echo '<div class="wcefp-step-number">1</div>';
        echo '<div class="wcefp-step-content">';
        echo '<h4>' . esc_html__('Create Voucher Database Table', 'wceventsfp') . '</h4>';
        echo '<p>' . esc_html__('The voucher system requires a database table to store voucher information.', 'wceventsfp') . '</p>';
        echo '<button type="button" class="button button-primary" onclick="wcefpCreateVoucherTable()">';
        echo esc_html__('Create Voucher Table', 'wceventsfp') . '</button>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="wcefp-setup-step">';
        echo '<div class="wcefp-step-number">2</div>';
        echo '<div class="wcefp-step-content">';
        echo '<h4>' . esc_html__('Configure Settings', 'wceventsfp') . '</h4>';
        echo '<p>' . esc_html__('Set up voucher expiration periods, email templates, and other preferences.', 'wceventsfp') . '</p>';
        echo '<a href="' . admin_url('admin.php?page=wcefp-settings&tab=integrations') . '" class="button button-secondary">';
        echo esc_html__('Go to Settings', 'wceventsfp') . '</a>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="wcefp-setup-step">';
        echo '<div class="wcefp-step-number">3</div>';
        echo '<div class="wcefp-step-content">';
        echo '<h4>' . esc_html__('Add Voucher Products', 'wceventsfp') . '</h4>';
        echo '<p>' . esc_html__('Create voucher products in WooCommerce that customers can purchase.', 'wceventsfp') . '</p>';
        echo '<a href="' . admin_url('post-new.php?post_type=product') . '" class="button button-secondary">';
        echo esc_html__('Add Voucher Product', 'wceventsfp') . '</a>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Add onboarding scripts
        $this->add_onboarding_scripts();
    }
    
    /**
     * Add voucher management scripts
     * 
     * @return void
     */
    private function add_voucher_scripts() {
        ?>
        <script type="text/javascript">
        function wcefpGenerateVoucher() {
            if (confirm('<?php echo esc_js(__("Generate a new voucher code?", "wceventsfp")); ?>')) {
                // Implementation would go here
                alert('<?php echo esc_js(__("Voucher generation feature will be available in the full interface.", "wceventsfp")); ?>');
            }
        }
        
        function wcefpExportVouchers() {
            // Implementation would go here
            alert('<?php echo esc_js(__("Voucher export feature will be available in the full interface.", "wceventsfp")); ?>');
        }
        </script>
        
        <style>
        .wcefp-voucher-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .wcefp-stat-card {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .wcefp-stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
        }
        .wcefp-voucher-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .wcefp-voucher-meta {
            font-size: 0.9em;
            color: #666;
        }
        .wcefp-action-buttons {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }
        </style>
        <?php
    }
    
    /**
     * Add onboarding scripts
     * 
     * @return void
     */
    private function add_onboarding_scripts() {
        ?>
        <script type="text/javascript">
        function wcefpCreateVoucherTable() {
            if (confirm('<?php echo esc_js(__("Create the voucher database table? This action cannot be undone.", "wceventsfp")); ?>')) {
                jQuery.post(ajaxurl, {
                    action: 'wcefp_create_voucher_table',
                    nonce: '<?php echo wp_create_nonce("wcefp_voucher_setup"); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('<?php echo esc_js(__("Voucher table created successfully! Please refresh the page.", "wceventsfp")); ?>');
                        location.reload();
                    } else {
                        alert('<?php echo esc_js(__("Error creating voucher table: ", "wceventsfp")); ?>' + (response.data || 'Unknown error'));
                    }
                });
            }
        }
        </script>
        
        <style>
        .wcefp-onboarding-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .wcefp-onboarding-intro {
            font-size: 1.1em;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }
        .wcefp-feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .wcefp-feature-card {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            text-align: center;
        }
        .wcefp-feature-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        .wcefp-setup-steps {
            max-width: 800px;
        }
        .wcefp-setup-step {
            display: flex;
            align-items: flex-start;
            margin: 20px 0;
            padding: 20px;
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .wcefp-step-number {
            background: #0073aa;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 20px;
            flex-shrink: 0;
        }
        .wcefp-step-content {
            flex-grow: 1;
        }
        .wcefp-step-content h4 {
            margin-top: 0;
        }
        </style>
        <?php
    }
    
    /**
     * Render closures page
     * 
     * @return void
     */
    public function render_closures_page_legacy() {
        if (class_exists('WCEFP_Closures') && method_exists('WCEFP_Closures', 'render_admin_page')) {
            WCEFP_Closures::render_admin_page();
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Chiusure', 'wceventsfp') . '</h1>';
            echo '<div class="wcefp-closures-placeholder">';
            echo '<p>' . esc_html__('Closure management system is being loaded...', 'wceventsfp') . '</p>';
            echo '<p class="description">' . esc_html__('This feature manages extraordinary closures for events and experiences.', 'wceventsfp') . '</p>';
            
            // Provide a basic interface placeholder
            echo '<h2>' . esc_html__('Temporary Closures Interface', 'wceventsfp') . '</h2>';
            echo '<p>' . esc_html__('Coming soon: Interface for managing exceptional closure dates.', 'wceventsfp') . '</p>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Render settings page with WordPress Settings API
     * 
     * @return void
     */
    public function render_settings_page_legacy() {
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wceventsfp'));
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Impostazioni WCEventsFP', 'wceventsfp') . '</h1>';
        
        // Register settings if not already done
        $this->register_wcefp_settings();
        
        // Load the WCEFP_Admin_Settings class if it exists
        $settings_file = WCEFP_PLUGIN_DIR . 'admin/class-wcefp-admin-settings.php';
        if (file_exists($settings_file)) {
            require_once $settings_file;
        }
        
        // Try to use the existing settings system first
        if (class_exists('WCEFP_Admin_Settings')) {
            try {
                $settings = WCEFP_Admin_Settings::get_instance();
                if (method_exists($settings, 'render_settings_page')) {
                    // Remove the wrap div since the settings class includes its own
                    echo '</div>';
                    $settings->render_settings_page();
                    return;
                }
            } catch (\Exception $e) {
                // Log error but continue with fallback
                error_log('WCEventsFP: Settings rendering error: ' . $e->getMessage());
                
                // Show debug error if WP_DEBUG is enabled
                if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
                    echo '<div class="notice notice-warning"><p>';
                    echo '<strong>WCEFP Debug:</strong> Advanced settings interface not available: ' . esc_html($e->getMessage());
                    echo '<br>Using server-rendered fallback interface.';
                    echo '</p></div>';
                }
            }
        }
        
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        $tabs = $this->get_settings_tabs();
        
        // Settings form with tabs
        ?>
        <form method="post" action="options.php" class="wcefp-settings-form">
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper wp-clearfix">
                <?php foreach ($tabs as $tab_key => $tab_info): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wcefp-settings&tab=' . $tab_key)); ?>" 
                   class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr($tab_info['icon']); ?>"></span>
                    <?php echo esc_html($tab_info['title']); ?>
                </a>
                <?php endforeach; ?>
            </nav>
            
            <!-- Tab Content -->
            <div class="wcefp-tab-content">
                <?php
                settings_fields('wcefp_settings_' . $current_tab);
                do_settings_sections('wcefp_settings_' . $current_tab);
                ?>
            </div>
            
            <?php submit_button(__('Salva Impostazioni', 'wceventsfp')); ?>
        </form>
        
        <!-- Enhancement Notice -->
        <div class="notice notice-info">
            <h3><?php _e('Interfaccia Impostazioni', 'wceventsfp'); ?></h3>
            <p><?php _e('Questa è l\'interfaccia server-rendered per le impostazioni. Tutte le modifiche vengono salvate immediatamente nel database WordPress.', 'wceventsfp'); ?></p>
            <p><?php _e('Per funzionalità avanzate come l\'interfaccia React, assicurarsi che tutti i componenti JavaScript siano caricati correttamente.', 'wceventsfp'); ?></p>
        </div>
        
        <?php
        echo '</div>';
    }
    
    /**
     * Get settings tabs configuration
     * 
     * @return array
     */
    private function get_settings_tabs() {
        return [
            'general' => [
                'title' => __('Generali', 'wceventsfp'),
                'icon' => 'dashicons-admin-generic'
            ],
            'email' => [
                'title' => __('Email/Notifiche', 'wceventsfp'),
                'icon' => 'dashicons-email-alt'
            ],
            'features' => [
                'title' => __('Feature Flags', 'wceventsfp'),
                'icon' => 'dashicons-admin-tools'
            ],
            'integrations' => [
                'title' => __('Integrazioni', 'wceventsfp'),
                'icon' => 'dashicons-admin-plugins'
            ]
        ];
    }
    
    /**
     * Register WCEFP settings using WordPress Settings API
     * 
     * @return void
     */
    private function register_wcefp_settings() {
        // Register settings for each tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        // General settings
        if ($current_tab === 'general') {
            register_setting('wcefp_settings_general', 'wcefp_options', [$this, 'sanitize_settings']);
            
            add_settings_section(
                'wcefp_general_section',
                __('Impostazioni Generali', 'wceventsfp'),
                [$this, 'render_general_section_info'],
                'wcefp_settings_general'
            );
            
            add_settings_field(
                'default_capacity',
                __('Capacità Predefinita', 'wceventsfp'),
                [$this, 'render_number_field'],
                'wcefp_settings_general',
                'wcefp_general_section',
                [
                    'option_name' => 'wcefp_options',
                    'field_name' => 'default_capacity',
                    'default' => 10,
                    'description' => __('Numero predefinito di posti disponibili per ogni slot.', 'wceventsfp')
                ]
            );
            
            add_settings_field(
                'safe_mode',
                __('Modalità Sicura', 'wceventsfp'),
                [$this, 'render_checkbox_field'],
                'wcefp_settings_general',
                'wcefp_general_section',
                [
                    'option_name' => 'wcefp_options',
                    'field_name' => 'safe_mode',
                    'description' => __('Attiva la modalità sicura per disabilitare funzionalità avanzate.', 'wceventsfp')
                ]
            );
            
            add_settings_field(
                'accent_color',
                __('Colore Accent', 'wceventsfp'),
                [$this, 'render_color_field'],
                'wcefp_settings_general',
                'wcefp_general_section',
                [
                    'option_name' => 'wcefp_options',
                    'field_name' => 'accent_color',
                    'default' => '#0073aa',
                    'description' => __('Colore principale per l\'interfaccia del plugin.', 'wceventsfp')
                ]
            );
        }
        
        // Email settings
        if ($current_tab === 'email') {
            register_setting('wcefp_settings_email', 'wcefp_email_options', [$this, 'sanitize_email_settings']);
            
            add_settings_section(
                'wcefp_email_section',
                __('Impostazioni Email', 'wceventsfp'),
                [$this, 'render_email_section_info'],
                'wcefp_settings_email'
            );
            
            add_settings_field(
                'sender_name',
                __('Nome Mittente', 'wceventsfp'),
                [$this, 'render_text_field'],
                'wcefp_settings_email',
                'wcefp_email_section',
                [
                    'option_name' => 'wcefp_email_options',
                    'field_name' => 'sender_name',
                    'default' => get_bloginfo('name'),
                    'description' => __('Nome che apparirà come mittente delle email.', 'wceventsfp')
                ]
            );
            
            add_settings_field(
                'sender_email',
                __('Email Mittente', 'wceventsfp'),
                [$this, 'render_email_field'],
                'wcefp_settings_email',
                'wcefp_email_section',
                [
                    'option_name' => 'wcefp_email_options',
                    'field_name' => 'sender_email',
                    'default' => get_option('admin_email'),
                    'description' => __('Indirizzo email mittente per le notifiche.', 'wceventsfp')
                ]
            );
        }
        
        // Feature flags
        if ($current_tab === 'features') {
            register_setting('wcefp_settings_features', 'wcefp_feature_flags', [$this, 'sanitize_feature_flags']);
            
            add_settings_section(
                'wcefp_features_section',
                __('Attivazione Funzionalità', 'wceventsfp'),
                [$this, 'render_features_section_info'],
                'wcefp_settings_features'
            );
            
            $features = [
                'vouchers' => __('Sistema Voucher', 'wceventsfp'),
                'closures' => __('Chiusure Straordinarie', 'wceventsfp'),
                'analytics' => __('Analytics Avanzate', 'wceventsfp'),
                'integrations' => __('Integrazioni Esterne', 'wceventsfp')
            ];
            
            foreach ($features as $feature_key => $feature_name) {
                add_settings_field(
                    'enable_' . $feature_key,
                    $feature_name,
                    [$this, 'render_checkbox_field'],
                    'wcefp_settings_features',
                    'wcefp_features_section',
                    [
                        'option_name' => 'wcefp_feature_flags',
                        'field_name' => 'enable_' . $feature_key,
                        'description' => sprintf(__('Attiva %s', 'wceventsfp'), strtolower($feature_name))
                    ]
                );
            }
        }
        
        // Integrations
        if ($current_tab === 'integrations') {
            register_setting('wcefp_settings_integrations', 'wcefp_integrations', [$this, 'sanitize_integrations']);
            
            add_settings_section(
                'wcefp_integrations_section',
                __('Configurazione Integrazioni', 'wceventsfp'),
                [$this, 'render_integrations_section_info'],
                'wcefp_settings_integrations'
            );
            
            add_settings_field(
                'google_api_key',
                __('Google API Key', 'wceventsfp'),
                [$this, 'render_password_field'],
                'wcefp_settings_integrations',
                'wcefp_integrations_section',
                [
                    'option_name' => 'wcefp_integrations',
                    'field_name' => 'google_api_key',
                    'description' => __('Chiave API Google per servizi Maps e Calendar.', 'wceventsfp')
                ]
            );
            
            add_settings_field(
                'booking_channel',
                __('Canale Prenotazioni', 'wceventsfp'),
                [$this, 'render_select_field'],
                'wcefp_settings_integrations',
                'wcefp_integrations_section',
                [
                    'option_name' => 'wcefp_integrations',
                    'field_name' => 'booking_channel',
                    'options' => [
                        'direct' => __('Diretto', 'wceventsfp'),
                        'booking.com' => __('Booking.com', 'wceventsfp'),
                        'expedia' => __('Expedia', 'wceventsfp')
                    ],
                    'description' => __('Canale principale per le prenotazioni.', 'wceventsfp')
                ]
            );
        }
    }
    
    /**
     * Sanitize general settings
     * 
     * @param array $input
     * @return array
     */
    public function sanitize_settings($input) {
        $output = [];
        
        if (isset($input['default_capacity'])) {
            $output['default_capacity'] = absint($input['default_capacity']);
        }
        
        if (isset($input['safe_mode'])) {
            $output['safe_mode'] = !empty($input['safe_mode']) ? 1 : 0;
        }
        
        if (isset($input['accent_color'])) {
            $output['accent_color'] = sanitize_hex_color($input['accent_color']) ?: '#0073aa';
        }
        
        // T-05: Experience Gating setting
        if (isset($input['hide_experiences_from_woo'])) {
            $output['hide_experiences_from_woo'] = !empty($input['hide_experiences_from_woo']) ? 1 : 0;
        } else {
            // Default: ON (hide experiences from WooCommerce loops)
            $output['hide_experiences_from_woo'] = 1;
        }
        
        return $output;
    }
    
    /**
     * Sanitize email settings
     * 
     * @param array $input
     * @return array
     */
    public function sanitize_email_settings($input) {
        $output = [];
        
        if (isset($input['sender_name'])) {
            $output['sender_name'] = sanitize_text_field($input['sender_name']);
        }
        
        if (isset($input['sender_email'])) {
            $output['sender_email'] = sanitize_email($input['sender_email']);
        }
        
        return $output;
    }
    
    /**
     * Sanitize feature flags
     * 
     * @param array $input
     * @return array
     */
    public function sanitize_feature_flags($input) {
        $output = [];
        
        $features = ['vouchers', 'closures', 'analytics', 'integrations'];
        foreach ($features as $feature) {
            $key = 'enable_' . $feature;
            $output[$key] = !empty($input[$key]) ? 1 : 0;
        }
        
        return $output;
    }
    
    /**
     * Sanitize integrations settings
     * 
     * @param array $input
     * @return array
     */
    public function sanitize_integrations($input) {
        $output = [];
        
        if (isset($input['google_api_key'])) {
            $output['google_api_key'] = sanitize_text_field($input['google_api_key']);
        }
        
        if (isset($input['booking_channel'])) {
            $allowed_channels = ['direct', 'booking.com', 'expedia'];
            $channel = sanitize_text_field($input['booking_channel']);
            $output['booking_channel'] = in_array($channel, $allowed_channels) ? $channel : 'direct';
        }
        
        return $output;
    }
    
    // Render field methods
    public function render_general_section_info() {
        echo '<p>' . __('Configurazioni di base per il plugin WCEventsFP.', 'wceventsfp') . '</p>';
    }
    
    public function render_email_section_info() {
        echo '<p>' . __('Impostazioni per l\'invio di email e notifiche.', 'wceventsfp') . '</p>';
    }
    
    public function render_features_section_info() {
        echo '<p>' . __('Attiva o disattiva funzionalità specifiche del plugin.', 'wceventsfp') . '</p>';
    }
    
    public function render_integrations_section_info() {
        echo '<p>' . __('Configurazione delle integrazioni con servizi esterni.', 'wceventsfp') . '</p>';
    }
    
    public function render_number_field($args) {
        $options = get_option($args['option_name'], []);
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : $args['default'];
        
        printf(
            '<input type="number" name="%s[%s]" value="%s" class="regular-text" min="0" />',
            esc_attr($args['option_name']),
            esc_attr($args['field_name']),
            esc_attr($value)
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function render_text_field($args) {
        $options = get_option($args['option_name'], []);
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : ($args['default'] ?? '');
        
        printf(
            '<input type="text" name="%s[%s]" value="%s" class="regular-text" />',
            esc_attr($args['option_name']),
            esc_attr($args['field_name']),
            esc_attr($value)
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function render_email_field($args) {
        $options = get_option($args['option_name'], []);
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : ($args['default'] ?? '');
        
        printf(
            '<input type="email" name="%s[%s]" value="%s" class="regular-text" />',
            esc_attr($args['option_name']),
            esc_attr($args['field_name']),
            esc_attr($value)
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function render_password_field($args) {
        $options = get_option($args['option_name'], []);
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : '';
        
        printf(
            '<input type="password" name="%s[%s]" value="%s" class="regular-text" />',
            esc_attr($args['option_name']),
            esc_attr($args['field_name']),
            esc_attr($value)
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function render_checkbox_field($args) {
        $options = get_option($args['option_name'], []);
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : 0;
        
        printf(
            '<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label>',
            esc_attr($args['option_name']),
            esc_attr($args['field_name']),
            checked($value, 1, false),
            esc_html($args['description'] ?? '')
        );
    }
    
    public function render_color_field($args) {
        $options = get_option($args['option_name'], []);
        $value = isset($options[$args['field_name']]) ? $options[$args['field_name']] : ($args['default'] ?? '#0073aa');
        
        printf(
            '<input type="color" name="%s[%s]" value="%s" />',
            esc_attr($args['option_name']),
            esc_attr($args['field_name']),
            esc_attr($value)
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function render_select_field($args) {
        $options = get_option($args['option_name'], []);
        $selected = isset($options[$args['field_name']]) ? $options[$args['field_name']] : '';
        
        printf(
            '<select name="%s[%s]" class="regular-text">',
            esc_attr($args['option_name']),
            esc_attr($args['field_name'])
        );
        
        foreach ($args['options'] as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($selected, $value, false),
                esc_html($label)
            );
        }
        
        echo '</select>';
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    /**
     * Handle voucher actions via AJAX
     * 
     * @return void
     */
    public function handle_voucher_action() {
        // Security checks
        if (!check_ajax_referer('wcefp_voucher_action', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions.', 'wceventsfp'));
        }
        
        $action = sanitize_text_field($_POST['voucher_action'] ?? '');
        $voucher_id = absint($_POST['voucher_id'] ?? 0);
        
        if (!$voucher_id || !$action) {
            wp_send_json_error(__('Invalid parameters.', 'wceventsfp'));
        }
        
        switch ($action) {
            case 'regenerate':
                $this->handle_voucher_regenerate($voucher_id);
                wp_send_json_success(__('Voucher code regenerated successfully.', 'wceventsfp'));
                break;
                
            case 'resend':
                $this->handle_voucher_resend($voucher_id);
                wp_send_json_success(__('Voucher email resent successfully.', 'wceventsfp'));
                break;
                
            default:
                wp_send_json_error(__('Unknown action.', 'wceventsfp'));
        }
    }
    
    /**
     * Render occurrence management interface
     * 
     * @return void
     */
    private function render_occurrence_interface() {
        echo '<div class="wcefp-occurrence-generator">';
        echo '<h3>' . esc_html__('Genera Occorrenze', 'wceventsfp') . '</h3>';
        echo '<p>' . esc_html__('Use the recurring events system to generate occurrences.', 'wceventsfp') . '</p>';
        
        // Basic form to interface with WCEFP_Recurring
        echo '<form method="post" action="admin-ajax.php" class="wcefp-occurrence-form">';
        wp_nonce_field('wcefp_admin', 'nonce');
        echo '<input type="hidden" name="action" value="wcefp_generate_occurrences" />';
        
        echo '<table class="form-table">';
        echo '<tr><th><label for="product_id">' . esc_html__('Product ID:', 'wceventsfp') . '</label></th>';
        echo '<td><input type="number" name="product_id" id="product_id" class="regular-text" /></td></tr>';
        
        echo '<tr><th><label for="from_date">' . esc_html__('From Date:', 'wceventsfp') . '</label></th>';
        echo '<td><input type="date" name="from" id="from_date" class="regular-text" /></td></tr>';
        
        echo '<tr><th><label for="to_date">' . esc_html__('To Date:', 'wceventsfp') . '</label></th>';
        echo '<td><input type="date" name="to" id="to_date" class="regular-text" /></td></tr>';
        echo '</table>';
        
        echo '<p class="submit"><input type="submit" value="' . esc_attr__('Generate Occurrences', 'wceventsfp') . '" class="button-primary" /></p>';
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Render bookings list
     * 
     * @param array $bookings List of bookings
     * @return void
     */
    private function render_bookings_list($bookings) {
        if (empty($bookings)) {
            echo '<p>' . esc_html__('No bookings found.', 'wceventsfp') . '</p>';
            return;
        }
        
        echo '<div class="wcefp-bookings-list">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('ID', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Customer', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Occurrence', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Status', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Date', 'wceventsfp') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($bookings as $booking) {
            echo '<tr>';
            echo '<td>' . esc_html($booking['id'] ?? '') . '</td>';
            echo '<td>' . esc_html($booking['customer_name'] ?? __('N/A', 'wceventsfp')) . '</td>';
            echo '<td>' . esc_html($booking['occurrence_id'] ?? __('N/A', 'wceventsfp')) . '</td>';
            echo '<td>' . esc_html($booking['status'] ?? __('N/A', 'wceventsfp')) . '</td>';
            echo '<td>' . esc_html($booking['created_at'] ?? __('N/A', 'wceventsfp')) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    
    /**
     * Render occurrences list
     * 
     * @param array $occurrences List of occurrences
     * @return void
     */
    private function render_occurrences_list($occurrences) {
        if (empty($occurrences)) {
            echo '<p>' . esc_html__('No occurrences found.', 'wceventsfp') . '</p>';
            return;
        }
        
        echo '<div class="wcefp-occurrences-list">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('ID', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Product', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Date & Time', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Capacity', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Booked', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Status', 'wceventsfp') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($occurrences as $occurrence) {
            echo '<tr>';
            echo '<td>' . esc_html($occurrence['id'] ?? '') . '</td>';
            echo '<td>' . esc_html($occurrence['product_title'] ?? get_the_title($occurrence['product_id'] ?? 0)) . '</td>';
            echo '<td>' . esc_html($occurrence['start_datetime'] ?? __('N/A', 'wceventsfp')) . '</td>';
            echo '<td>' . esc_html($occurrence['capacity'] ?? '0') . '</td>';
            echo '<td>' . esc_html($occurrence['booked'] ?? '0') . '</td>';
            echo '<td>' . esc_html($occurrence['status'] ?? __('N/A', 'wceventsfp')) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    
    /**
     * Render booking view page
     * 
     * @return void
     */
    public function render_booking_view_page() {
        // Security check
        if (!current_user_can('manage_wcefp_bookings')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wceventsfp'));
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wcefp_view_booking')) {
            wp_die(__('Security check failed.', 'wceventsfp'));
        }
        
        $booking_id = absint($_GET['booking_id'] ?? 0);
        if (!$booking_id) {
            wp_die(__('Invalid booking ID.', 'wceventsfp'));
        }
        
        // Get booking data (placeholder - would connect to actual data source)
        $booking_data = $this->get_booking_details($booking_id);
        if (!$booking_data) {
            wp_die(__('Booking not found.', 'wceventsfp'));
        }
        
        ?>
        <div class="wrap">
            <div class="wcefp-page-header">
                <h1><?php printf(__('Prenotazione #%d', 'wceventsfp'), $booking_id); ?></h1>
                <a href="<?php echo admin_url('admin.php?page=wcefp-bookings'); ?>" class="page-title-action">
                    <?php _e('← Torna alle Prenotazioni', 'wceventsfp'); ?>
                </a>
            </div>
            
            <div class="wcefp-booking-view">
                <!-- Customer Information -->
                <div class="wcefp-section">
                    <h2><?php _e('Informazioni Cliente', 'wceventsfp'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Nome:', 'wceventsfp'); ?></th>
                            <td><?php echo esc_html($booking_data['customer_name']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Email:', 'wceventsfp'); ?></th>
                            <td><a href="mailto:<?php echo esc_attr($booking_data['customer_email']); ?>"><?php echo esc_html($booking_data['customer_email']); ?></a></td>
                        </tr>
                        <tr>
                            <th><?php _e('Telefono:', 'wceventsfp'); ?></th>
                            <td><?php echo esc_html($booking_data['customer_phone'] ?? __('N/A', 'wceventsfp')); ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- Event Information -->
                <div class="wcefp-section">
                    <h2><?php _e('Dettagli Evento', 'wceventsfp'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Evento:', 'wceventsfp'); ?></th>
                            <td>
                                <strong><?php echo esc_html($booking_data['event_title']); ?></strong>
                                <br><small>ID: <?php echo absint($booking_data['event_id']); ?></small>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Data e Ora:', 'wceventsfp'); ?></th>
                            <td><?php echo esc_html($booking_data['occurrence_datetime']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Partecipanti:', 'wceventsfp'); ?></th>
                            <td><?php echo absint($booking_data['participants']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Importo:', 'wceventsfp'); ?></th>
                            <td><strong><?php echo wc_price($booking_data['total_amount']); ?></strong></td>
                        </tr>
                    </table>
                </div>
                
                <!-- Booking Status -->
                <div class="wcefp-section">
                    <h2><?php _e('Stato e Note', 'wceventsfp'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Stato:', 'wceventsfp'); ?></th>
                            <td>
                                <span class="wcefp-status-badge wcefp-status-<?php echo esc_attr($booking_data['status']); ?>">
                                    <?php echo esc_html(ucfirst($booking_data['status'])); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Note:', 'wceventsfp'); ?></th>
                            <td><?php echo nl2br(esc_html($booking_data['notes'] ?? __('Nessuna nota', 'wceventsfp'))); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Check-in:', 'wceventsfp'); ?></th>
                            <td>
                                <?php if ($booking_data['checkin_status'] === 'checked_in'): ?>
                                    <span class="wcefp-status-badge wcefp-status-checked-in">
                                        <?php printf(__('✓ Check-in effettuato: %s', 'wceventsfp'), $booking_data['checkin_time']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="wcefp-status-badge wcefp-status-pending">
                                        <?php _e('In attesa di check-in', 'wceventsfp'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Quick Actions -->
                <div class="wcefp-section">
                    <h2><?php _e('Azioni Rapide', 'wceventsfp'); ?></h2>
                    <div class="wcefp-quick-actions">
                        <?php if ($booking_data['checkin_status'] !== 'checked_in'): ?>
                            <button type="button" 
                                    class="button button-primary wcefp-quick-action" 
                                    data-action="checkin" 
                                    data-booking-id="<?php echo $booking_id; ?>">
                                <?php _e('Segna come Check-in', 'wceventsfp'); ?>
                            </button>
                        <?php endif; ?>
                        
                        <button type="button" 
                                class="button button-secondary wcefp-quick-action" 
                                data-action="resend_email" 
                                data-booking-id="<?php echo $booking_id; ?>">
                            <?php _e('Reinvia Email Conferma', 'wceventsfp'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Extra & Voucher Information -->
                <?php if (!empty($booking_data['extras']) || !empty($booking_data['voucher_code'])): ?>
                <div class="wcefp-section">
                    <h2><?php _e('Extra e Voucher', 'wceventsfp'); ?></h2>
                    <table class="form-table">
                        <?php if (!empty($booking_data['extras'])): ?>
                        <tr>
                            <th><?php _e('Extra:', 'wceventsfp'); ?></th>
                            <td><?php echo esc_html($booking_data['extras']); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($booking_data['voucher_code'])): ?>
                        <tr>
                            <th><?php _e('Voucher:', 'wceventsfp'); ?></th>
                            <td><?php echo esc_html($booking_data['voucher_code']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add JavaScript for quick actions -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.wcefp-quick-action').on('click', function() {
                var $button = $(this);
                var action = $button.data('action');
                var bookingId = $button.data('booking-id');
                
                if (!confirm('<?php _e("Sei sicuro di voler eseguire questa azione?", "wceventsfp"); ?>')) {
                    return;
                }
                
                $button.prop('disabled', true).text('<?php _e("Elaborazione...", "wceventsfp"); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wcefp_booking_quick_action',
                        booking_action: action,
                        booking_id: bookingId,
                        nonce: '<?php echo wp_create_nonce("wcefp_quick_action"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data || '<?php _e("Errore durante l\\'operazione", "wceventsfp"); ?>');
                            $button.prop('disabled', false).text($button.data('original-text'));
                        }
                    },
                    error: function() {
                        alert('<?php _e("Errore di connessione", "wceventsfp"); ?>');
                        $button.prop('disabled', false).text($button.data('original-text'));
                    }
                });
            });
            
            // Store original button text
            $('.wcefp-quick-action').each(function() {
                $(this).data('original-text', $(this).text());
            });
        });
        </script>
        
        <style>
        .wcefp-booking-view {
            background: #fff;
            padding: 20px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            margin-top: 20px;
        }
        .wcefp-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f1;
        }
        .wcefp-section:last-child {
            border-bottom: none;
        }
        .wcefp-section h2 {
            margin-top: 0;
            color: #1e1e1e;
        }
        .wcefp-quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .wcefp-status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        .wcefp-status-confirmed { background-color: #48bb78; }
        .wcefp-status-pending { background-color: #f56565; }
        .wcefp-status-completed { background-color: #38b2ac; }
        .wcefp-status-cancelled { background-color: #a0aec0; }
        .wcefp-status-checked-in { background-color: #48bb78; }
        </style>
        <?php
    }
    
    /**
     * Get booking details for view page
     * 
     * @param int $booking_id
     * @return array|false
     */
    private function get_booking_details($booking_id) {
        // Placeholder implementation - in real application this would query the database
        // For now, return sample data that matches the booking structure
        
        $sample_bookings = [
            1 => [
                'id' => 1,
                'customer_name' => 'Mario Rossi',
                'customer_email' => 'mario@example.com',
                'customer_phone' => '+39 123 456 7890',
                'event_title' => 'Wine Tasting Experience',
                'event_id' => 123,
                'occurrence_datetime' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime('+7 days 18:00')),
                'participants' => 2,
                'status' => 'confirmed',
                'total_amount' => 89.90,
                'notes' => 'Preferisce vini rossi. Anniversario di matrimonio.',
                'checkin_status' => 'pending',
                'checkin_time' => null,
                'extras' => 'Degustazione formaggi (+15€)',
                'voucher_code' => 'WELCOME2024',
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
            ],
            2 => [
                'id' => 2,
                'customer_name' => 'Anna Bianchi',
                'customer_email' => 'anna@example.com',
                'customer_phone' => '+39 098 765 4321',
                'event_title' => 'Cooking Class',
                'event_id' => 124,
                'occurrence_datetime' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime('+14 days 19:30')),
                'participants' => 4,
                'status' => 'pending',
                'total_amount' => 179.80,
                'notes' => 'Gruppo vegetariano, nessuna allergia.',
                'checkin_status' => 'pending',
                'checkin_time' => null,
                'extras' => null,
                'voucher_code' => null,
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ]
        ];
        
        return $sample_bookings[$booking_id] ?? false;
    }
    
    /**
     * Handle booking quick actions via AJAX
     * 
     * @return void
     */
    public function handle_booking_quick_action() {
        // Security checks
        if (!check_ajax_referer('wcefp_quick_action', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_wcefp_bookings')) {
            wp_send_json_error(__('Insufficient permissions.', 'wceventsfp'));
        }
        
        $booking_id = absint($_POST['booking_id'] ?? 0);
        $booking_action = sanitize_text_field($_POST['booking_action'] ?? '');
        
        if (!$booking_id || !$booking_action) {
            wp_send_json_error(__('Invalid parameters.', 'wceventsfp'));
        }
        
        switch ($booking_action) {
            case 'checkin':
                $result = $this->mark_booking_checkin($booking_id);
                break;
                
            case 'resend_email':
                $result = $this->resend_booking_email($booking_id);
                break;
                
            default:
                wp_send_json_error(__('Unknown action.', 'wceventsfp'));
        }
        
        if ($result) {
            wp_send_json_success(__('Action completed successfully.', 'wceventsfp'));
        } else {
            wp_send_json_error(__('Action failed.', 'wceventsfp'));
        }
    }
    
    /**
     * Mark booking as checked in
     * 
     * @param int $booking_id
     * @return bool
     */
    private function mark_booking_checkin($booking_id) {
        // Placeholder implementation - would update database
        // For now, just simulate success
        
        // In real implementation:
        // update_post_meta($booking_id, '_wcefp_checkin_status', 'checked_in');
        // update_post_meta($booking_id, '_wcefp_checkin_time', current_time('mysql'));
        
        return true;
    }
    
    /**
     * Resend booking confirmation email
     * 
     * @param int $booking_id
     * @return bool
     */
    private function resend_booking_email($booking_id) {
        // Placeholder implementation - would send email
        // For now, just simulate success
        
        // In real implementation:
        // $booking_data = $this->get_booking_details($booking_id);
        // $email_manager = new EmailManager();
        // return $email_manager->send_booking_confirmation($booking_id, $booking_data);
        
        return true;
    }
    
    /**
     * Render booking calendar page
     * 
     * @return void
     */
    public function render_booking_calendar_page() {
        // Security check
        if (!current_user_can('manage_wcefp_bookings')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wceventsfp'));
        }
        
        ?>
        <div class="wrap">
            <div class="wcefp-page-header">
                <h1><?php _e('Calendario Prenotazioni', 'wceventsfp'); ?></h1>
                <p class="wcefp-page-description">
                    <?php _e('Visualizza tutte le prenotazioni in un\'interfaccia calendario intuitiva.', 'wceventsfp'); ?>
                </p>
                <div class="wcefp-page-meta">
                    <span class="wcefp-page-badge">
                        📅 <?php _e('Vista Calendario', 'wceventsfp'); ?>
                    </span>
                    <span class="wcefp-page-badge">
                        📋 <?php _e('Gestione Prenotazioni', 'wceventsfp'); ?>
                    </span>
                </div>
            </div>
            
            <div class="wcefp-calendar-container">
                <div class="wcefp-calendar-toolbar">
                    <div class="wcefp-calendar-nav">
                        <button type="button" class="button" id="wcefp-calendar-prev">
                            <?php _e('← Precedente', 'wceventsfp'); ?>
                        </button>
                        <button type="button" class="button" id="wcefp-calendar-today">
                            <?php _e('Oggi', 'wceventsfp'); ?>
                        </button>
                        <button type="button" class="button" id="wcefp-calendar-next">
                            <?php _e('Successivo →', 'wceventsfp'); ?>
                        </button>
                    </div>
                    
                    <div class="wcefp-calendar-views">
                        <button type="button" class="button button-primary wcefp-calendar-view" data-view="month">
                            <?php _e('Mese', 'wceventsfp'); ?>
                        </button>
                        <button type="button" class="button wcefp-calendar-view" data-view="agendaWeek">
                            <?php _e('Settimana', 'wceventsfp'); ?>
                        </button>
                        <button type="button" class="button wcefp-calendar-view" data-view="agendaDay">
                            <?php _e('Giorno', 'wceventsfp'); ?>
                        </button>
                    </div>
                </div>
                
                <div id="wcefp-booking-calendar" class="wcefp-calendar-widget"></div>
            </div>
            
            <!-- Booking Legend -->
            <div class="wcefp-calendar-legend">
                <h3><?php _e('Legenda:', 'wceventsfp'); ?></h3>
                <div class="wcefp-legend-items">
                    <span class="wcefp-legend-item">
                        <span class="wcefp-color-box" style="background-color: #48bb78;"></span>
                        <?php _e('Confermato', 'wceventsfp'); ?>
                    </span>
                    <span class="wcefp-legend-item">
                        <span class="wcefp-color-box" style="background-color: #f56565;"></span>
                        <?php _e('In Attesa', 'wceventsfp'); ?>
                    </span>
                    <span class="wcefp-legend-item">
                        <span class="wcefp-color-box" style="background-color: #38b2ac;"></span>
                        <?php _e('Completato', 'wceventsfp'); ?>
                    </span>
                    <span class="wcefp-legend-item">
                        <span class="wcefp-color-box" style="background-color: #a0aec0;"></span>
                        <?php _e('Annullato', 'wceventsfp'); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Load FullCalendar if not already loaded -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialize FullCalendar for bookings
            var calendarEl = document.getElementById('wcefp-booking-calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'it',
                firstDay: 1, // Monday
                headerToolbar: false, // We use custom toolbar
                height: 'auto',
                events: function(fetchInfo, successCallback, failureCallback) {
                    // Get booking events via AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wcefp_get_booking_calendar_events',
                            start: fetchInfo.startStr,
                            end: fetchInfo.endStr,
                            nonce: '<?php echo wp_create_nonce("wcefp_calendar_events"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                successCallback(response.data);
                            } else {
                                failureCallback(response.data || 'Error loading events');
                            }
                        },
                        error: function() {
                            failureCallback('Network error');
                        }
                    });
                },
                eventClick: function(info) {
                    // Open booking details
                    if (info.event.extendedProps.booking_id) {
                        var viewUrl = '<?php echo admin_url("admin.php?page=wcefp-booking-view&booking_id="); ?>' + 
                                     info.event.extendedProps.booking_id + 
                                     '&_wpnonce=<?php echo wp_create_nonce("wcefp_view_booking"); ?>';
                        window.location.href = viewUrl;
                    }
                },
                eventDidMount: function(info) {
                    // Add tooltips with booking details
                    $(info.el).attr('title', info.event.extendedProps.tooltip || info.event.title);
                }
            });
            
            calendar.render();
            
            // Custom toolbar handlers
            $('#wcefp-calendar-prev').on('click', function() {
                calendar.prev();
            });
            
            $('#wcefp-calendar-next').on('click', function() {
                calendar.next();
            });
            
            $('#wcefp-calendar-today').on('click', function() {
                calendar.today();
            });
            
            $('.wcefp-calendar-view').on('click', function() {
                var view = $(this).data('view');
                $('.wcefp-calendar-view').removeClass('button-primary').addClass('button-secondary');
                $(this).removeClass('button-secondary').addClass('button-primary');
                calendar.changeView(view);
            });
        });
        </script>
        
        <style>
        .wcefp-calendar-container {
            background: #fff;
            padding: 20px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            margin-top: 20px;
        }
        .wcefp-calendar-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f1;
        }
        .wcefp-calendar-nav {
            display: flex;
            gap: 5px;
        }
        .wcefp-calendar-views {
            display: flex;
            gap: 5px;
        }
        .wcefp-calendar-widget {
            min-height: 600px;
        }
        .wcefp-calendar-legend {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .wcefp-legend-items {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .wcefp-legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        .wcefp-color-box {
            width: 16px;
            height: 16px;
            border-radius: 3px;
        }
        .wcefp-page-header {
            margin-bottom: 0;
        }
        .wcefp-page-description {
            font-size: 14px;
            color: #646970;
            margin: 10px 0;
        }
        .wcefp-page-meta {
            display: flex;
            gap: 10px;
            margin: 10px 0;
        }
        .wcefp-page-badge {
            background: #f0f0f1;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            color: #50575e;
        }
        </style>
        <?php
    }
    
    /**
     * Handle calendar events AJAX request
     * 
     * @return void
     */
    public function handle_get_calendar_events() {
        // Security checks
        if (!check_ajax_referer('wcefp_calendar_events', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_wcefp_bookings')) {
            wp_send_json_error(__('Insufficient permissions.', 'wceventsfp'));
        }
        
        $start = sanitize_text_field($_POST['start'] ?? '');
        $end = sanitize_text_field($_POST['end'] ?? '');
        
        // Get booking events for calendar (placeholder implementation)
        $events = $this->get_booking_calendar_events($start, $end);
        
        wp_send_json_success($events);
    }
    
    /**
     * Get booking events for calendar display
     * 
     * @param string $start Start date
     * @param string $end End date
     * @return array
     */
    private function get_booking_calendar_events($start, $end) {
        // Placeholder implementation - would query actual booking data
        $sample_events = [
            [
                'id' => 'booking-1',
                'title' => 'Wine Tasting - Mario Rossi (2 pax)',
                'start' => date('Y-m-d\TH:i:s', strtotime('+7 days 18:00')),
                'backgroundColor' => '#48bb78',
                'borderColor' => '#38a169',
                'extendedProps' => [
                    'booking_id' => 1,
                    'customer_name' => 'Mario Rossi',
                    'status' => 'confirmed',
                    'participants' => 2,
                    'tooltip' => 'Wine Tasting Experience - Mario Rossi (2 partecipanti) - Confermato'
                ]
            ],
            [
                'id' => 'booking-2',
                'title' => 'Cooking Class - Anna Bianchi (4 pax)',
                'start' => date('Y-m-d\TH:i:s', strtotime('+14 days 19:30')),
                'backgroundColor' => '#f56565',
                'borderColor' => '#e53e3e',
                'extendedProps' => [
                    'booking_id' => 2,
                    'customer_name' => 'Anna Bianchi',
                    'status' => 'pending',
                    'participants' => 4,
                    'tooltip' => 'Cooking Class - Anna Bianchi (4 partecipanti) - In Attesa'
                ]
            ]
        ];
        
        // Filter events by date range if provided
        if ($start && $end) {
            $start_timestamp = strtotime($start);
            $end_timestamp = strtotime($end);
            
            $sample_events = array_filter($sample_events, function($event) use ($start_timestamp, $end_timestamp) {
                $event_timestamp = strtotime($event['start']);
                return $event_timestamp >= $start_timestamp && $event_timestamp <= $end_timestamp;
            });
        }
        
        return array_values($sample_events);
    }
    
    /**
     * Handle voucher table creation via AJAX
     * 
     * @return void
     */
    public function handle_create_voucher_table() {
        // Security checks
        if (!check_ajax_referer('wcefp_voucher_setup', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions.', 'wceventsfp'));
        }
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wcefp_vouchers';
            
            // Check if table already exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                wp_send_json_error(__('Voucher table already exists.', 'wceventsfp'));
            }
            
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                code varchar(50) NOT NULL UNIQUE,
                order_id bigint(20) DEFAULT NULL,
                product_id bigint(20) DEFAULT NULL,
                recipient_name varchar(255) DEFAULT NULL,
                recipient_email varchar(255) DEFAULT NULL,
                sender_name varchar(255) DEFAULT NULL,
                sender_email varchar(255) DEFAULT NULL,
                message text,
                value decimal(10,2) NOT NULL DEFAULT 0.00,
                currency char(3) DEFAULT 'EUR',
                status varchar(20) DEFAULT 'pending',
                expiry_date datetime DEFAULT NULL,
                used_date datetime DEFAULT NULL,
                used_order_id bigint(20) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY code (code),
                KEY order_id (order_id),
                KEY status (status),
                KEY expiry_date (expiry_date)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                wp_send_json_success(__('Voucher table created successfully.', 'wceventsfp'));
            } else {
                wp_send_json_error(__('Failed to create voucher table.', 'wceventsfp'));
            }
            
        } catch (\Exception $e) {
            wp_send_json_error(__('Database error: ', 'wceventsfp') . $e->getMessage());
        }
    }
    
    // ========== Delegate Methods to Modules ==========
    
    /**
     * Delegate to BookingsModule for rendering
     * 
     * @return void
     */
    public function render_bookings_page() {
        $module = $this->get_module('bookings');
        if ($module && method_exists($module, 'render_bookings_page')) {
            $module->render_bookings_page();
        } else {
            $this->render_fallback_page(__('Bookings Management', 'wceventsfp'), 
                __('Booking management system is being loaded...', 'wceventsfp'));
        }
    }
    
    /**
     * Delegate to VouchersModule for rendering
     * 
     * @return void
     */
    public function render_vouchers_page() {
        $module = $this->get_module('vouchers');
        if ($module && method_exists($module, 'render_vouchers_page')) {
            $module->render_vouchers_page();
        } else {
            // Keep existing voucher rendering logic as fallback
            $this->render_existing_voucher_page();
        }
    }
    
    /**
     * Delegate to ClosuresModule for rendering
     * 
     * @return void
     */
    public function render_closures_page() {
        $module = $this->get_module('closures');
        if ($module && method_exists($module, 'render_closures_page')) {
            $module->render_closures_page();
        } else {
            $this->render_fallback_page(__('Closures Management', 'wceventsfp'),
                __('Closure management system is being loaded...', 'wceventsfp'));
        }
    }
    
    /**
     * Delegate to SettingsModule for rendering
     * 
     * @return void
     */
    public function render_settings_page() {
        $module = $this->get_module('settings');
        if ($module && method_exists($module, 'render_settings_page')) {
            $module->render_settings_page();
        } else {
            $this->render_fallback_page(__('Plugin Settings', 'wceventsfp'),
                __('Settings system is being loaded...', 'wceventsfp'));
        }
    }
    
    /**
     * Delegate to ExtrasModule for rendering
     * 
     * @return void
     */
    public function render_extras_page() {
        $module = $this->get_module('extras');
        if ($module && method_exists($module, 'render_extras_page')) {
            $module->render_extras_page();
        } else {
            $this->render_fallback_page(__('Extra Services', 'wceventsfp'),
                __('Extra services management is being loaded...', 'wceventsfp'));
        }
    }
    
    /**
     * Get module from container
     * 
     * @param string $module_key
     * @return mixed|null
     */
    private function get_module($module_key) {
        try {
            return $this->container->get("modules.{$module_key}");
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Render existing voucher page as fallback
     * 
     * @return void
     */
    private function render_existing_voucher_page() {
        // Delegate to the legacy voucher implementation which is functional
        $this->render_vouchers_page_legacy();
    }
    
    /**
     * Render fallback page when module is not available
     * 
     * @param string $title
     * @param string $message
     * @return void
     */
    private function render_fallback_page($title, $message) {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<div class="notice notice-info">';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
        echo '</div>';
    }
}