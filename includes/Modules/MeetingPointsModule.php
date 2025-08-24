<?php
/**
 * Meeting Points Module
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
 * Meeting Points management module
 */
class MeetingPointsModule extends ServiceProvider {
    
    /**
     * Register module services
     * 
     * @return void
     */
    public function register(): void {
        // Register meeting points services
    }
    
    /**
     * Boot module services
     * 
     * @return void
     */
    public function boot(): void {
        // Hook into WordPress lifecycle
        add_action('init', [$this, 'initialize_cpt'], 20);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meeting_point_meta']);
        
        Logger::info('Meeting Points module booted successfully');
    }
    
    /**
     * Initialize custom post types
     * 
     * @return void
     */
    public function initialize_cpt(): void {
        register_post_type('wcefp_meeting_point', [
            'labels' => [
                'name' => __('Meeting Points', 'wceventsfp'),
                'singular_name' => __('Meeting Point', 'wceventsfp'),
                'add_new' => __('Add New Meeting Point', 'wceventsfp'),
                'add_new_item' => __('Add New Meeting Point', 'wceventsfp'),
                'edit_item' => __('Edit Meeting Point', 'wceventsfp'),
                'new_item' => __('New Meeting Point', 'wceventsfp'),
                'view_item' => __('View Meeting Point', 'wceventsfp'),
                'search_items' => __('Search Meeting Points', 'wceventsfp'),
                'not_found' => __('No meeting points found', 'wceventsfp'),
                'not_found_in_trash' => __('No meeting points found in trash', 'wceventsfp')
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'wcefp-events',
            'capability_type' => 'post',
            'capabilities' => [
                'edit_posts' => 'manage_woocommerce',
                'edit_others_posts' => 'manage_woocommerce',
                'publish_posts' => 'manage_woocommerce',
                'read_private_posts' => 'manage_woocommerce',
                'delete_posts' => 'manage_woocommerce',
                'delete_others_posts' => 'manage_woocommerce'
            ],
            'supports' => ['title', 'editor', 'thumbnail'],
            'has_archive' => false,
            'rewrite' => false,
            'menu_icon' => 'dashicons-location-alt'
        ]);
    }
    
    /**
     * Add meta boxes for meeting point details
     * 
     * @return void
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'wcefp_meeting_point_details',
            __('Meeting Point Details', 'wceventsfp'),
            [$this, 'render_meeting_point_meta_box'],
            'wcefp_meeting_point',
            'normal',
            'high'
        );
    }
    
    /**
     * Render meeting point meta box
     * 
     * @param \WP_Post $post
     * @return void
     */
    public function render_meeting_point_meta_box($post): void {
        wp_nonce_field('wcefp_meeting_point_meta', 'wcefp_meeting_point_nonce');
        
        $address = get_post_meta($post->ID, '_meeting_point_address', true);
        $latitude = get_post_meta($post->ID, '_meeting_point_latitude', true);
        $longitude = get_post_meta($post->ID, '_meeting_point_longitude', true);
        $instructions = get_post_meta($post->ID, '_meeting_point_instructions', true);
        $contact_info = get_post_meta($post->ID, '_meeting_point_contact', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="meeting_point_address"><?php esc_html_e('Address', 'wceventsfp'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="meeting_point_address" 
                           name="meeting_point_address" 
                           value="<?php echo esc_attr($address); ?>" 
                           class="large-text" />
                    <p class="description"><?php esc_html_e('Full address of the meeting point', 'wceventsfp'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="meeting_point_latitude"><?php esc_html_e('Latitude', 'wceventsfp'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="meeting_point_latitude" 
                           name="meeting_point_latitude" 
                           value="<?php echo esc_attr($latitude); ?>" 
                           step="any" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="meeting_point_longitude"><?php esc_html_e('Longitude', 'wceventsfp'); ?></label>
                </th>
                <td>
                    <input type="number" 
                           id="meeting_point_longitude" 
                           name="meeting_point_longitude" 
                           value="<?php echo esc_attr($longitude); ?>" 
                           step="any" 
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="meeting_point_instructions"><?php esc_html_e('Instructions', 'wceventsfp'); ?></label>
                </th>
                <td>
                    <textarea id="meeting_point_instructions" 
                              name="meeting_point_instructions" 
                              rows="4" 
                              class="large-text"><?php echo esc_textarea($instructions); ?></textarea>
                    <p class="description"><?php esc_html_e('Special instructions for finding the meeting point', 'wceventsfp'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="meeting_point_contact"><?php esc_html_e('Contact Information', 'wceventsfp'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="meeting_point_contact" 
                           name="meeting_point_contact" 
                           value="<?php echo esc_attr($contact_info); ?>" 
                           class="large-text" />
                    <p class="description"><?php esc_html_e('Phone number or contact info for assistance', 'wceventsfp'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save meeting point meta data
     * 
     * @param int $post_id
     * @return void
     */
    public function save_meeting_point_meta($post_id): void {
        // Verify nonce
        if (!isset($_POST['wcefp_meeting_point_nonce']) || 
            !wp_verify_nonce($_POST['wcefp_meeting_point_nonce'], 'wcefp_meeting_point_meta')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check post type
        if (get_post_type($post_id) !== 'wcefp_meeting_point') {
            return;
        }
        
        // Save meta fields
        $fields = [
            'meeting_point_address' => 'sanitize_text_field',
            'meeting_point_latitude' => 'floatval',
            'meeting_point_longitude' => 'floatval',
            'meeting_point_instructions' => 'sanitize_textarea_field',
            'meeting_point_contact' => 'sanitize_text_field'
        ];
        
        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = $sanitize_callback($_POST[$field]);
                update_post_meta($post_id, '_' . $field, $value);
            }
        }
    }
    
    /**
     * Get meeting points for dropdown
     * 
     * @return array
     */
    public function get_meeting_points_for_select(): array {
        $meeting_points = get_posts([
            'post_type' => 'wcefp_meeting_point',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        $options = [];
        foreach ($meeting_points as $point) {
            $options[$point->ID] = $point->post_title;
        }
        
        return $options;
    }
    
    /**
     * Get meeting point data for frontend display
     * 
     * @param int $meeting_point_id
     * @return array|null
     */
    public function get_meeting_point_data($meeting_point_id): ?array {
        $post = get_post($meeting_point_id);
        
        if (!$post || $post->post_type !== 'wcefp_meeting_point') {
            return null;
        }
        
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'address' => get_post_meta($post->ID, '_meeting_point_address', true),
            'latitude' => get_post_meta($post->ID, '_meeting_point_latitude', true),
            'longitude' => get_post_meta($post->ID, '_meeting_point_longitude', true),
            'instructions' => get_post_meta($post->ID, '_meeting_point_instructions', true),
            'contact' => get_post_meta($post->ID, '_meeting_point_contact', true)
        ];
    }
}