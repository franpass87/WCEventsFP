<?php
/**
 * Closures Module
 * 
 * @package WCEFP
 * @subpackage Modules
 * @since 2.1.4
 */

namespace WCEFP\Modules;

use WCEFP\Core\ServiceProvider;
use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Closures management module
 */
class ClosuresModule extends ServiceProvider {
    
    /**
     * Register module services
     * 
     * @return void
     */
    public function register(): void {
        // Register closure services
    }
    
    /**
     * Boot module services
     * 
     * @return void
     */
    public function boot(): void {
        // Hook into WordPress lifecycle - no CPT needed, using database table
        add_action('admin_menu', [$this, 'add_admin_pages'], 15);
        
        // AJAX handlers for closures management
        add_action('wp_ajax_wcefp_add_closure', [$this, 'ajax_add_closure']);
        add_action('wp_ajax_wcefp_delete_closure', [$this, 'ajax_delete_closure']);
        add_action('wp_ajax_wcefp_list_closures', [$this, 'ajax_list_closures']);
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Hook into availability system to apply closures
        add_filter('wcefp_product_availability', [$this, 'apply_closures_to_availability'], 10, 4);
        add_filter('wcefp_product_occurrences', [$this, 'apply_closures_to_occurrences'], 10, 4);
        
        Logger::info('Closures module booted successfully');
    }
    
    /**
     * Get event products for selection
     * 
     * @return array
     */
    private function get_event_products(): array {
        $products = [];
        
        // Get WooCommerce products that are events
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_wcefp_is_event',
                    'value' => 'yes',
                    'compare' => '='
                ]
            ]
        ];
        
        $query = new \WP_Query($args);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $products[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title()
                ];
            }
        }
        wp_reset_postdata();
        
        return $products;
    }
    
    /**
     * Add admin menu pages - handled by central MenuManager
     * 
     * @return void
     */
    public function add_admin_pages(): void {
        // Menu registration moved to MenuManager for centralized control
        // This method kept for module compatibility but no longer adds menus
    }
    
    /**
     * Enqueue admin assets
     * 
     * @return void
     */
    public function enqueue_admin_assets($hook): void {
        // Only enqueue on closures page
        if (strpos($hook, 'wcefp-closures') === false) {
            return;
        }
        
        // Enqueue closures JavaScript
        wp_enqueue_script(
            'wcefp-closures',
            WCEFP_PLUGIN_URL . 'assets/js/closures.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        // Enqueue closures CSS
        wp_enqueue_style(
            'wcefp-admin-closures',
            WCEFP_PLUGIN_URL . 'assets/css/admin-closures.css',
            ['wp-admin'],
            WCEFP_VERSION
        );
        
        // Localize script with data
        wp_localize_script('wcefp-closures', 'WCEFPClose', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_admin'),
            'products' => $this->get_event_products(),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this closure?', 'wceventsfp'),
                'addingClosure' => __('Adding closure...', 'wceventsfp'),
                'loadingClosures' => __('Loading closures...', 'wceventsfp'),
                'selectDates' => __('Please select both start and end dates.', 'wceventsfp'),
                'invalidDates' => __('Start date must be before or equal to end date.', 'wceventsfp'),
                'networkError' => __('Network error. Please try again.', 'wceventsfp')
            ]
        ]);
    }
    
    /**
     * Render closures management page
     * 
     * @return void
     */
    public function render_closures_page(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Chiusure Straordinarie', 'wceventsfp') . '</h1>';
        
        // Add closure form card
        $this->render_add_closure_card();
        
        // Closures list section
        echo '<h2>' . esc_html__('Elenco chiusure', 'wceventsfp') . '</h2>';
        echo '<div id="wcefp-closures-list">';
        echo '<p>' . esc_html__('Caricamento...', 'wceventsfp') . '</p>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render add closure form card
     * 
     * @return void
     */
    private function render_add_closure_card(): void {
        ?>
        <div class="card wcefp-closures-form-card">
            <h2><?php esc_html_e('Aggiungi chiusura', 'wceventsfp'); ?></h2>
            <div class="wcefp-closures-form">
                <label>
                    <?php esc_html_e('Prodotto', 'wceventsfp'); ?><br/>
                    <select id="wcefp-close-product">
                        <option value="0"><?php esc_html_e('Tutti gli eventi/esperienze', 'wceventsfp'); ?></option>
                        <!-- Products populated by JavaScript -->
                    </select>
                </label>
                <label>
                    <?php esc_html_e('Dal', 'wceventsfp'); ?><br/>
                    <input type="date" id="wcefp-close-from" />
                </label>
                <label>
                    <?php esc_html_e('Al', 'wceventsfp'); ?><br/>
                    <input type="date" id="wcefp-close-to" />
                </label>
                <label class="wcefp-closures-note">
                    <?php esc_html_e('Nota (opzionale)', 'wceventsfp'); ?><br/>
                    <input type="text" id="wcefp-close-note" placeholder="<?php esc_attr_e('Es. Manutenzione, festività…', 'wceventsfp'); ?>" />
                </label>
                <div>
                    <button class="button button-primary" id="wcefp-add-closure"><?php esc_html_e('Aggiungi', 'wceventsfp'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler: Add closure
     * 
     * @return void
     */
    public function ajax_add_closure(): void {
        try {
            // Check nonce and capabilities
            check_ajax_referer('wcefp_admin', 'nonce');
            if (!current_user_can('manage_woocommerce')) {
                throw new \Exception(__('Insufficient permissions', 'wceventsfp'));
            }
            
            // Validate input
            $product_id = intval($_POST['product_id'] ?? 0);
            $from = sanitize_text_field($_POST['from'] ?? '');
            $to = sanitize_text_field($_POST['to'] ?? '');
            $note = sanitize_textarea_field($_POST['note'] ?? '');
            
            if (empty($from) || empty($to)) {
                throw new \Exception(__('Start and end dates are required', 'wceventsfp'));
            }
            
            if ($from > $to) {
                throw new \Exception(__('Start date must be before or equal to end date', 'wceventsfp'));
            }
            
            // Insert into database
            global $wpdb;
            $result = $wpdb->insert(
                $wpdb->prefix . 'wcefp_closures',
                [
                    'product_id' => $product_id ?: null,
                    'start_date' => $from,
                    'end_date' => $to,
                    'note' => $note,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%s']
            );
            
            if ($result === false) {
                throw new \Exception(__('Database error', 'wceventsfp'));
            }
            
            // Clear caches
            $this->clear_closure_cache();
            
            wp_send_json_success([
                'msg' => __('Closure added successfully', 'wceventsfp'),
                'id' => $wpdb->insert_id
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['msg' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX handler: Delete closure
     * 
     * @return void
     */
    public function ajax_delete_closure(): void {
        try {
            // Check nonce and capabilities
            check_ajax_referer('wcefp_admin', 'nonce');
            if (!current_user_can('manage_woocommerce')) {
                throw new \Exception(__('Insufficient permissions', 'wceventsfp'));
            }
            
            $closure_id = intval($_POST['id'] ?? 0);
            if (!$closure_id) {
                throw new \Exception(__('Invalid closure ID', 'wceventsfp'));
            }
            
            // Delete from database
            global $wpdb;
            $result = $wpdb->delete(
                $wpdb->prefix . 'wcefp_closures',
                ['id' => $closure_id],
                ['%d']
            );
            
            if ($result === false) {
                throw new \Exception(__('Database error', 'wceventsfp'));
            }
            
            // Clear caches
            $this->clear_closure_cache();
            
            wp_send_json_success(['msg' => __('Closure deleted successfully', 'wceventsfp')]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['msg' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX handler: List closures
     * 
     * @return void
     */
    public function ajax_list_closures(): void {
        try {
            // Check nonce and capabilities
            check_ajax_referer('wcefp_admin', 'nonce');
            if (!current_user_can('manage_woocommerce')) {
                throw new \Exception(__('Insufficient permissions', 'wceventsfp'));
            }
            
            global $wpdb;
            
            // Get closures from database
            $closures = $wpdb->get_results("
                SELECT 
                    c.*,
                    COALESCE(p.post_title, '" . esc_sql(__('All Events', 'wceventsfp')) . "') as product_title
                FROM {$wpdb->prefix}wcefp_closures c
                LEFT JOIN {$wpdb->posts} p ON c.product_id = p.ID
                ORDER BY c.created_at DESC
            ");
            
            $rows = [];
            foreach ($closures as $closure) {
                $rows[] = [
                    'id' => $closure->id,
                    'product' => $closure->product_title,
                    'from' => $closure->start_date,
                    'to' => $closure->end_date,
                    'note' => $closure->note,
                    'created_at' => mysql2date(get_option('date_format'), $closure->created_at)
                ];
            }
            
            wp_send_json_success(['rows' => $rows]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['msg' => $e->getMessage()]);
        }
    }
    
    /**
     * Check if a date is closed for a specific product
     * 
     * @param string $date Date in Y-m-d format
     * @param int $product_id Product ID (0 for any product)
     * @return bool
     */
    public function is_date_closed(string $date, int $product_id = 0): bool {
        global $wpdb;
        
        $sql = "
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}wcefp_closures 
            WHERE %s BETWEEN start_date AND end_date 
            AND (product_id IS NULL OR product_id = 0" . ($product_id ? " OR product_id = %d" : "") . ")
        ";
        
        if ($product_id) {
            $count = $wpdb->get_var($wpdb->prepare($sql, $date, $product_id));
        } else {
            $count = $wpdb->get_var($wpdb->prepare($sql, $date));
        }
        
        return $count > 0;
    }
    
    /**
     * Get active closures for a product
     * 
     * @param int $product_id Product ID (0 for global closures)
     * @return array
     */
    public function get_active_closures(int $product_id = 0): array {
        global $wpdb;
        
        $sql = "
            SELECT * 
            FROM {$wpdb->prefix}wcefp_closures 
            WHERE end_date >= CURDATE()
        ";
        
        if ($product_id) {
            $sql .= " AND (product_id IS NULL OR product_id = 0 OR product_id = %d)";
            $closures = $wpdb->get_results($wpdb->prepare($sql, $product_id));
        } else {
            $sql .= " AND (product_id IS NULL OR product_id = 0)";
            $closures = $wpdb->get_results($sql);
        }
        
        return $closures ?: [];
    }
    
    /**
     * Clear closure-related caches
     * 
     * @return void
     */
    private function clear_closure_cache(): void {
        // Clear any transients or caches related to closures and availability
        delete_transient('wcefp_available_slots');
        delete_transient('wcefp_closure_cache');
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        Logger::info('Closure caches cleared');
    }
    
    /**
     * Apply closures to product availability
     * 
     * @param array $availability Current availability data
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i format
     * @param object $product Product instance
     * @return array Modified availability data
     */
    public function apply_closures_to_availability(array $availability, string $date, string $time, $product): array {
        if (empty($date)) {
            return $availability;
        }
        
        $product_id = $product->get_id();
        
        // Check if date is closed
        if ($this->is_date_closed($date, $product_id)) {
            $availability['available'] = false;
            $availability['spots_remaining'] = 0;
            $availability['closure_reason'] = __('Date is closed due to extraordinary closure', 'wceventsfp');
        }
        
        return $availability;
    }
    
    /**
     * Apply closures to product occurrences
     * 
     * @param array $occurrences Current occurrences
     * @param string $date Date in Y-m-d format
     * @param string $end_date End date in Y-m-d format
     * @param object $product Product instance
     * @return array Filtered occurrences (closed dates removed)
     */
    public function apply_closures_to_occurrences(array $occurrences, string $date, string $end_date, $product): array {
        if (empty($occurrences)) {
            return $occurrences;
        }
        
        $product_id = $product->get_id();
        $filtered_occurrences = [];
        
        foreach ($occurrences as $occurrence) {
            $occurrence_date = $occurrence['date'] ?? '';
            
            // Skip occurrences on closed dates
            if ($occurrence_date && !$this->is_date_closed($occurrence_date, $product_id)) {
                $filtered_occurrences[] = $occurrence;
            }
        }
        
        return $filtered_occurrences;
    }
}