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
        add_action('add_meta_boxes', [$this, 'add_product_meeting_point_meta_box']);
        add_action('save_post', [$this, 'save_meeting_point_meta']);
        add_action('save_post', [$this, 'save_product_meeting_point_meta']);
        
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
        
        $address = get_post_meta($post->ID, '_wcefp_address', true);
        $latitude = get_post_meta($post->ID, '_wcefp_latitude', true);
        $longitude = get_post_meta($post->ID, '_wcefp_longitude', true);
        $contact_name = get_post_meta($post->ID, '_wcefp_contact_name', true);
        $contact_phone = get_post_meta($post->ID, '_wcefp_contact_phone', true);
        $contact_email = get_post_meta($post->ID, '_wcefp_contact_email', true);
        $instructions = get_post_meta($post->ID, '_wcefp_instructions', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="wcefp_address"><?php esc_html_e('Address', 'wceventsfp'); ?></label>
                </th>
                <td>
                    <textarea id="wcefp_address" name="wcefp_address" rows="3" class="large-text"><?php echo esc_textarea($address); ?></textarea>
                    <p class="description"><?php esc_html_e('Full address of the meeting point', 'wceventsfp'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wcefp_latitude"><?php esc_html_e('Latitude', 'wceventsfp'); ?></label>
                </th>
                <td>
                    <input type="number" step="any" id="wcefp_latitude" name="wcefp_latitude" value="<?php echo esc_attr($latitude); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('GPS latitude coordinate (e.g., 45.4642)', 'wceventsfp'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wcefp_longitude"><?php esc_html_e('Longitude', 'wceventsfp'); ?></label>
                </th>
                <td>
                    <input type="number" step="any" id="wcefp_longitude" name="wcefp_longitude" value="<?php echo esc_attr($longitude); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('GPS longitude coordinate (e.g., 9.1900)', 'wceventsfp'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wcefp_contact_name"><?php esc_html_e('Contact Person', 'wceventsfp'); ?></label>
                </th>
                <td>
                    <input type="text" id="wcefp_contact_name" name="wcefp_contact_name" value="<?php echo esc_attr($contact_name); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wcefp_contact_phone"><?php esc_html_e('Contact Phone', 'wceventsfp'); ?></label>
                </th>
                <td>
                    <input type="tel" id="wcefp_contact_phone" name="wcefp_contact_phone" value="<?php echo esc_attr($contact_phone); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wcefp_contact_email"><?php esc_html_e('Contact Email', 'wceventsfp'); ?></label>
                </th>
                <td>
                    <input type="email" id="wcefp_contact_email" name="wcefp_contact_email" value="<?php echo esc_attr($contact_email); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="wcefp_instructions"><?php esc_html_e('Special Instructions', 'wceventsfp'); ?></label>
                </th>
                <td>
                    <textarea id="wcefp_instructions" name="wcefp_instructions" rows="4" class="large-text"><?php echo esc_textarea($instructions); ?></textarea>
                    <p class="description"><?php esc_html_e('Additional instructions for participants (parking, access, etc.)', 'wceventsfp'); ?></p>
                </td>
            </tr>
        </table>
        
        <div id="wcefp-map-container" style="margin-top: 20px;">
            <h4><?php esc_html_e('Location Preview', 'wceventsfp'); ?></h4>
            <div id="wcefp-map" style="height: 300px; width: 100%; border: 1px solid #ddd;"></div>
            <p class="description"><?php esc_html_e('Map will display when coordinates are provided', 'wceventsfp'); ?></p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Simple coordinates validation and map preview
            $('#wcefp_latitude, #wcefp_longitude').on('input', function() {
                var lat = parseFloat($('#wcefp_latitude').val());
                var lng = parseFloat($('#wcefp_longitude').val());
                
                if (lat && lng && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    $('#wcefp-map').html('<p style="text-align: center; padding: 100px;">' +
                        '📍 Coordinates: ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</p>');
                } else {
                    $('#wcefp-map').html('<p style="text-align: center; padding: 100px; color: #999;">' +
                        '<?php esc_html_e('Enter valid coordinates to preview location', 'wceventsfp'); ?></p>');
                }
            });
            
            // Trigger initial map update
            $('#wcefp_latitude').trigger('input');
        });
        </script>
        <?php
    }
    
    /**
     * Save meeting point meta data
     * 
     * @param int $post_id
     * @return void
     */
    public function save_meeting_point_meta($post_id): void {
        if (!isset($_POST['wcefp_meeting_point_nonce']) || 
            !wp_verify_nonce($_POST['wcefp_meeting_point_nonce'], 'wcefp_meeting_point_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (get_post_type($post_id) !== 'wcefp_meeting_point') {
            return;
        }
        
        // Sanitize and save meta fields
        $meta_fields = [
            '_wcefp_address' => 'sanitize_textarea_field',
            '_wcefp_latitude' => 'floatval',
            '_wcefp_longitude' => 'floatval',
            '_wcefp_contact_name' => 'sanitize_text_field',
            '_wcefp_contact_phone' => 'sanitize_text_field',
            '_wcefp_contact_email' => 'sanitize_email',
            '_wcefp_instructions' => 'sanitize_textarea_field'
        ];
        
        foreach ($meta_fields as $meta_key => $sanitize_callback) {
            $field_name = str_replace('_wcefp_', 'wcefp_', $meta_key);
            $value = $_POST[$field_name] ?? '';
            
            if (is_callable($sanitize_callback)) {
                $value = call_user_func($sanitize_callback, $value);
            }
            
            if (!empty($value)) {
                update_post_meta($post_id, $meta_key, $value);
            } else {
                delete_post_meta($post_id, $meta_key);
            }
        }
    }
    
    /**
     * Get all meeting points
     * 
     * @return array
     */
    public function get_meeting_points(): array {
        $posts = get_posts([
            'post_type' => 'wcefp_meeting_point',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        $meeting_points = [];
        foreach ($posts as $post) {
            $meeting_points[$post->ID] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'address' => get_post_meta($post->ID, '_wcefp_address', true),
                'latitude' => get_post_meta($post->ID, '_wcefp_latitude', true),
                'longitude' => get_post_meta($post->ID, '_wcefp_longitude', true),
                'contact_name' => get_post_meta($post->ID, '_wcefp_contact_name', true),
                'contact_phone' => get_post_meta($post->ID, '_wcefp_contact_phone', true),
                'contact_email' => get_post_meta($post->ID, '_wcefp_contact_email', true),
                'instructions' => get_post_meta($post->ID, '_wcefp_instructions', true)
            ];
        }
        
        return $meeting_points;
    }
    
    /**
     * Add meeting point selection to product meta boxes
     * 
     * @return void
     */
    public function add_product_meeting_point_meta_box(): void {
        $product_types = ['wcefp_event', 'wcefp_experience'];
        
        foreach ($product_types as $product_type) {
            add_meta_box(
                'wcefp_product_meeting_point',
                __('Meeting Point', 'wceventsfp'),
                [$this, 'render_product_meeting_point_meta_box'],
                'product',
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render product meeting point selection
     * 
     * @param \WP_Post $post
     * @return void
     */
    public function render_product_meeting_point_meta_box($post): void {
        wp_nonce_field('wcefp_product_meeting_point_meta', 'wcefp_product_meeting_point_nonce');
        
        $selected_meeting_point = get_post_meta($post->ID, '_wcefp_meeting_point_id', true);
        $meeting_points = $this->get_meeting_points();
        
        ?>
        <p>
            <label for="wcefp_meeting_point_id"><?php esc_html_e('Select Meeting Point', 'wceventsfp'); ?></label>
            <select id="wcefp_meeting_point_id" name="wcefp_meeting_point_id" class="widefat">
                <option value=""><?php esc_html_e('No meeting point', 'wceventsfp'); ?></option>
                <?php foreach ($meeting_points as $mp): ?>
                    <option value="<?php echo esc_attr($mp['id']); ?>" <?php selected($selected_meeting_point, $mp['id']); ?>>
                        <?php echo esc_html($mp['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <?php if (!empty($meeting_points)): ?>
        <div id="wcefp-meeting-point-preview" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa;">
            <p><strong><?php esc_html_e('Meeting Point Details:', 'wceventsfp'); ?></strong></p>
            <div id="wcefp-mp-details" style="display: none;">
                <p><span id="wcefp-mp-address"></span></p>
                <p><small id="wcefp-mp-contact"></small></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var meetingPoints = <?php echo wp_json_encode($meeting_points); ?>;
            
            $('#wcefp_meeting_point_id').on('change', function() {
                var selectedId = $(this).val();
                var $details = $('#wcefp-mp-details');
                
                if (selectedId && meetingPoints[selectedId]) {
                    var mp = meetingPoints[selectedId];
                    $('#wcefp-mp-address').text(mp.address || '<?php esc_html_e('No address specified', 'wceventsfp'); ?>');
                    $('#wcefp-mp-contact').text(mp.contact_name ? 
                        '<?php esc_html_e('Contact:', 'wceventsfp'); ?> ' + mp.contact_name + 
                        (mp.contact_phone ? ' - ' + mp.contact_phone : '') : '');
                    $details.show();
                } else {
                    $details.hide();
                }
            });
            
            // Trigger initial update
            $('#wcefp_meeting_point_id').trigger('change');
        });
        </script>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Save product meeting point meta
     * 
     * @param int $post_id
     * @return void
     */
    public function save_product_meeting_point_meta($post_id): void {
        if (!isset($_POST['wcefp_product_meeting_point_nonce']) || 
            !wp_verify_nonce($_POST['wcefp_product_meeting_point_nonce'], 'wcefp_product_meeting_point_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $meeting_point_id = absint($_POST['wcefp_meeting_point_id'] ?? 0);
        
        if ($meeting_point_id > 0) {
            update_post_meta($post_id, '_wcefp_meeting_point_id', $meeting_point_id);
        } else {
            delete_post_meta($post_id, '_wcefp_meeting_point_id');
        }
    }
}