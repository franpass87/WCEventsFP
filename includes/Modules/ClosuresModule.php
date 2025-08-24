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
        // Hook into WordPress lifecycle
        add_action('init', [$this, 'initialize_cpt'], 20);
        add_action('admin_menu', [$this, 'add_admin_pages'], 15);
        add_action('wp_ajax_wcefp_closure_action', [$this, 'handle_closure_actions']);
        
        Logger::info('Closures module booted successfully');
    }
    
    /**
     * Initialize custom post types
     * 
     * @return void
     */
    public function initialize_cpt(): void {
        register_post_type('wcefp_closure', [
            'labels' => [
                'name' => __('Closures', 'wceventsfp'),
                'singular_name' => __('Closure', 'wceventsfp'),
                'add_new' => __('Add New Closure', 'wceventsfp'),
                'add_new_item' => __('Add New Closure', 'wceventsfp'),
                'edit_item' => __('Edit Closure', 'wceventsfp'),
                'new_item' => __('New Closure', 'wceventsfp'),
                'view_item' => __('View Closure', 'wceventsfp'),
                'search_items' => __('Search Closures', 'wceventsfp'),
                'not_found' => __('No closures found', 'wceventsfp'),
                'not_found_in_trash' => __('No closures found in trash', 'wceventsfp')
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'capability_type' => 'post',
            'capabilities' => [
                'edit_posts' => 'manage_woocommerce',
                'edit_others_posts' => 'manage_woocommerce',
                'publish_posts' => 'manage_woocommerce',
                'read_private_posts' => 'manage_woocommerce',
                'delete_posts' => 'manage_woocommerce',
                'delete_others_posts' => 'manage_woocommerce'
            ],
            'supports' => ['title', 'editor', 'custom-fields'],
            'has_archive' => false,
            'rewrite' => false
        ]);
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
     * Render closures management page
     * 
     * @return void
     */
    public function render_closures_page(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Closures Management', 'wceventsfp') . '</h1>';
        
        // Handle form submission
        if ($_POST && wp_verify_nonce($_POST['wcefp_closure_nonce'] ?? '', 'wcefp_add_closure')) {
            $this->handle_closure_submission();
        }
        
        // Render add closure form
        $this->render_add_closure_form();
        
        // Render existing closures list
        $this->render_closures_list();
        
        echo '</div>';
    }
    
    /**
     * Render add closure form
     * 
     * @return void
     */
    private function render_add_closure_form(): void {
        ?>
        <div class="wcefp-closure-form">
            <h2><?php esc_html_e('Add New Closure', 'wceventsfp'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('wcefp_add_closure', 'wcefp_closure_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="closure_title"><?php esc_html_e('Title', 'wceventsfp'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="closure_title" name="closure_title" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="closure_start_date"><?php esc_html_e('Start Date', 'wceventsfp'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="closure_start_date" name="closure_start_date" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="closure_end_date"><?php esc_html_e('End Date', 'wceventsfp'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="closure_end_date" name="closure_end_date" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="closure_type"><?php esc_html_e('Type', 'wceventsfp'); ?></label>
                        </th>
                        <td>
                            <select id="closure_type" name="closure_type" required>
                                <option value="global"><?php esc_html_e('Global (All Events)', 'wceventsfp'); ?></option>
                                <option value="product"><?php esc_html_e('Specific Product', 'wceventsfp'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="closure_description"><?php esc_html_e('Description', 'wceventsfp'); ?></label>
                        </th>
                        <td>
                            <textarea id="closure_description" name="closure_description" rows="3" class="large-text"></textarea>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Add Closure', 'wceventsfp')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render existing closures list
     * 
     * @return void
     */
    private function render_closures_list(): void {
        $closures = get_posts([
            'post_type' => 'wcefp_closure',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => '_closure_end_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>='
                ]
            ]
        ]);
        
        if (empty($closures)) {
            echo '<p>' . esc_html__('No active closures found.', 'wceventsfp') . '</p>';
            return;
        }
        
        echo '<h2>' . esc_html__('Active Closures', 'wceventsfp') . '</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . esc_html__('Title', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Start Date', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('End Date', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Type', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Actions', 'wceventsfp') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($closures as $closure) {
            $start_date = get_post_meta($closure->ID, '_closure_start_date', true);
            $end_date = get_post_meta($closure->ID, '_closure_end_date', true);
            $type = get_post_meta($closure->ID, '_closure_type', true);
            
            echo '<tr>';
            echo '<td>' . esc_html($closure->post_title) . '</td>';
            echo '<td>' . esc_html($start_date) . '</td>';
            echo '<td>' . esc_html($end_date) . '</td>';
            echo '<td>' . esc_html(ucfirst($type)) . '</td>';
            echo '<td>';
            echo '<a href="#" class="button button-small wcefp-delete-closure" data-closure-id="' . esc_attr($closure->ID) . '">';
            echo esc_html__('Delete', 'wceventsfp');
            echo '</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    /**
     * Handle closure form submission
     * 
     * @return void
     */
    private function handle_closure_submission(): void {
        $title = sanitize_text_field($_POST['closure_title']);
        $start_date = sanitize_text_field($_POST['closure_start_date']);
        $end_date = sanitize_text_field($_POST['closure_end_date']);
        $type = sanitize_text_field($_POST['closure_type']);
        $description = sanitize_textarea_field($_POST['closure_description'] ?? '');
        
        $closure_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $description,
            'post_type' => 'wcefp_closure',
            'post_status' => 'publish'
        ]);
        
        if ($closure_id && !is_wp_error($closure_id)) {
            update_post_meta($closure_id, '_closure_start_date', $start_date);
            update_post_meta($closure_id, '_closure_end_date', $end_date);
            update_post_meta($closure_id, '_closure_type', $type);
            
            // Clear any relevant caches
            $this->clear_closure_cache();
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Closure added successfully.', 'wceventsfp') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to add closure.', 'wceventsfp') . '</p></div>';
        }
    }
    
    /**
     * Handle AJAX closure actions
     * 
     * @return void
     */
    public function handle_closure_actions(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcefp_closure_actions') || 
            !current_user_can('manage_woocommerce')) {
            wp_die(__('Security check failed', 'wceventsfp'));
        }
        
        $action = sanitize_text_field($_POST['closure_action'] ?? '');
        $closure_id = intval($_POST['closure_id'] ?? 0);
        
        if ($action === 'delete' && $closure_id) {
            wp_delete_post($closure_id, true);
            $this->clear_closure_cache();
            
            wp_send_json_success(['message' => __('Closure deleted successfully', 'wceventsfp')]);
        }
        
        wp_send_json_error(['message' => __('Invalid action', 'wceventsfp')]);
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
    }
}