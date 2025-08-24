<?php
/**
 * Meeting Points CPT Management
 * 
 * @package WCEFP
 * @since 2.1.4
 */

if (!defined('ABSPATH')) exit;

/**
 * Meeting Points Custom Post Type and Management
 */
class WCEFP_MeetingPoints_CPT {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_boxes']);
        add_action('admin_menu', [$this, 'add_admin_menu'], 25);
        add_filter('manage_wcefp_meeting_point_posts_columns', [$this, 'set_custom_edit_columns']);
        add_action('manage_wcefp_meeting_point_posts_custom_column', [$this, 'custom_column_content'], 10, 2);
        add_action('wp_ajax_wcefp_get_meeting_points', [$this, 'ajax_get_meeting_points']);
    }
    
    /**
     * Register the meeting points custom post type
     */
    public function register_post_type() {
        $labels = [
            'name'                  => _x('Meeting Points', 'Post Type General Name', 'wceventsfp'),
            'singular_name'         => _x('Meeting Point', 'Post Type Singular Name', 'wceventsfp'),
            'menu_name'             => __('Meeting Points', 'wceventsfp'),
            'name_admin_bar'        => __('Meeting Point', 'wceventsfp'),
            'archives'              => __('Meeting Point Archives', 'wceventsfp'),
            'attributes'            => __('Meeting Point Attributes', 'wceventsfp'),
            'parent_item_colon'     => __('Parent Meeting Point:', 'wceventsfp'),
            'all_items'             => __('All Meeting Points', 'wceventsfp'),
            'add_new_item'          => __('Add New Meeting Point', 'wceventsfp'),
            'add_new'               => __('Add New', 'wceventsfp'),
            'new_item'              => __('New Meeting Point', 'wceventsfp'),
            'edit_item'             => __('Edit Meeting Point', 'wceventsfp'),
            'update_item'           => __('Update Meeting Point', 'wceventsfp'),
            'view_item'             => __('View Meeting Point', 'wceventsfp'),
            'view_items'            => __('View Meeting Points', 'wceventsfp'),
            'search_items'          => __('Search Meeting Point', 'wceventsfp'),
            'not_found'             => __('Not found', 'wceventsfp'),
            'not_found_in_trash'    => __('Not found in Trash', 'wceventsfp'),
            'featured_image'        => __('Featured Image', 'wceventsfp'),
            'set_featured_image'    => __('Set featured image', 'wceventsfp'),
            'remove_featured_image' => __('Remove featured image', 'wceventsfp'),
            'use_featured_image'    => __('Use as featured image', 'wceventsfp'),
            'insert_into_item'      => __('Insert into meeting point', 'wceventsfp'),
            'uploaded_to_this_item' => __('Uploaded to this meeting point', 'wceventsfp'),
            'items_list'            => __('Meeting points list', 'wceventsfp'),
            'items_list_navigation' => __('Meeting points list navigation', 'wceventsfp'),
            'filter_items_list'     => __('Filter meeting points list', 'wceventsfp'),
        ];
        
        $args = [
            'label'                 => __('Meeting Point', 'wceventsfp'),
            'description'           => __('Reusable meeting points for events', 'wceventsfp'),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'thumbnail'],
            'taxonomies'            => [],
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => false, // We'll add it manually to wcefp menu
            'menu_position'         => 5,
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'rewrite'               => false,
            'capability_type'       => 'page',
            'capabilities'          => [
                'edit_post'          => 'manage_woocommerce',
                'read_post'          => 'manage_woocommerce',
                'delete_post'        => 'manage_woocommerce',
                'edit_posts'         => 'manage_woocommerce',
                'edit_others_posts'  => 'manage_woocommerce',
                'delete_posts'       => 'manage_woocommerce',
                'publish_posts'      => 'manage_woocommerce',
                'read_private_posts' => 'manage_woocommerce',
            ],
        ];
        
        register_post_type('wcefp_meeting_point', $args);
    }
    
    /**
     * Add admin menu under WCEFP
     */
    public function add_admin_menu() {
        add_submenu_page(
            'wcefp',
            __('Meeting Points', 'wceventsfp'),
            __('Meeting Points', 'wceventsfp'),
            'manage_woocommerce',
            'edit.php?post_type=wcefp_meeting_point'
        );
    }
    
    /**
     * Add meta boxes for meeting point details
     */
    public function add_meta_boxes() {
        add_meta_box(
            'wcefp_meeting_point_details',
            __('Meeting Point Details', 'wceventsfp'),
            [$this, 'meeting_point_details_callback'],
            'wcefp_meeting_point',
            'normal',
            'high'
        );
    }
    
    /**
     * Meeting point details meta box callback
     */
    public function meeting_point_details_callback($post) {
        wp_nonce_field(basename(__FILE__), 'wcefp_meeting_point_nonce');
        
        $address = get_post_meta($post->ID, '_wcefp_mp_address', true);
        $city = get_post_meta($post->ID, '_wcefp_mp_city', true);
        $lat = get_post_meta($post->ID, '_wcefp_mp_lat', true);
        $lng = get_post_meta($post->ID, '_wcefp_mp_lng', true);
        $notes = get_post_meta($post->ID, '_wcefp_mp_notes', true);
        ?>
        
        <table class="form-table wcefp-meeting-point-meta">
            <tr>
                <th><label for="wcefp_mp_address"><?php _e('Address', 'wceventsfp'); ?></label></th>
                <td>
                    <input type="text" id="wcefp_mp_address" name="wcefp_mp_address" 
                           value="<?php echo esc_attr($address); ?>" class="regular-text" 
                           placeholder="<?php esc_attr_e('Via Example, 123', 'wceventsfp'); ?>" />
                    <p class="description"><?php _e('Full street address of the meeting point', 'wceventsfp'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wcefp_mp_city"><?php _e('City', 'wceventsfp'); ?></label></th>
                <td>
                    <input type="text" id="wcefp_mp_city" name="wcefp_mp_city" 
                           value="<?php echo esc_attr($city); ?>" class="regular-text" 
                           placeholder="<?php esc_attr_e('Roma, 00100', 'wceventsfp'); ?>" />
                    <p class="description"><?php _e('City and postal code', 'wceventsfp'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wcefp_mp_coordinates"><?php _e('Coordinates', 'wceventsfp'); ?></label></th>
                <td>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <div>
                            <label for="wcefp_mp_lat"><?php _e('Lat:', 'wceventsfp'); ?></label>
                            <input type="text" id="wcefp_mp_lat" name="wcefp_mp_lat" 
                                   value="<?php echo esc_attr($lat); ?>" class="small-text" 
                                   placeholder="41.9028" />
                        </div>
                        <div>
                            <label for="wcefp_mp_lng"><?php _e('Lng:', 'wceventsfp'); ?></label>
                            <input type="text" id="wcefp_mp_lng" name="wcefp_mp_lng" 
                                   value="<?php echo esc_attr($lng); ?>" class="small-text" 
                                   placeholder="12.4964" />
                        </div>
                        <button type="button" id="wcefp-geocode-btn" class="button">
                            <?php _e('Get Coordinates', 'wceventsfp'); ?>
                        </button>
                    </div>
                    <p class="description"><?php _e('GPS coordinates for map display. Use "Get Coordinates" to auto-geocode from address.', 'wceventsfp'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="wcefp_mp_notes"><?php _e('Notes', 'wceventsfp'); ?></label></th>
                <td>
                    <textarea id="wcefp_mp_notes" name="wcefp_mp_notes" rows="3" class="large-text"
                              placeholder="<?php esc_attr_e('Additional notes, parking info, landmarks...', 'wceventsfp'); ?>"><?php echo esc_textarea($notes); ?></textarea>
                    <p class="description"><?php _e('Additional information for participants', 'wceventsfp'); ?></p>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(function($){
            $('#wcefp-geocode-btn').on('click', function(){
                var address = $('#wcefp_mp_address').val() + ', ' + $('#wcefp_mp_city').val();
                if (!address.trim()) {
                    alert('<?php esc_js_e('Please enter an address first', 'wceventsfp'); ?>');
                    return;
                }
                
                // Simple geocoding using a public service (for demo - in production use Google Maps API)
                var geocodeUrl = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(address);
                
                $(this).prop('disabled', true).text('<?php esc_js_e('Loading...', 'wceventsfp'); ?>');
                
                $.getJSON(geocodeUrl)
                    .done(function(data){
                        if (data && data.length > 0) {
                            $('#wcefp_mp_lat').val(parseFloat(data[0].lat).toFixed(6));
                            $('#wcefp_mp_lng').val(parseFloat(data[0].lon).toFixed(6));
                            alert('<?php esc_js_e('Coordinates updated!', 'wceventsfp'); ?>');
                        } else {
                            alert('<?php esc_js_e('Address not found. Please enter coordinates manually.', 'wceventsfp'); ?>');
                        }
                    })
                    .fail(function(){
                        alert('<?php esc_js_e('Geocoding failed. Please enter coordinates manually.', 'wceventsfp'); ?>');
                    })
                    .always(function(){
                        $('#wcefp-geocode-btn').prop('disabled', false).text('<?php esc_js_e('Get Coordinates', 'wceventsfp'); ?>');
                    });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save meta box data
     */
    public function save_meta_boxes($post_id) {
        if (!isset($_POST['wcefp_meeting_point_nonce']) || 
            !wp_verify_nonce($_POST['wcefp_meeting_point_nonce'], basename(__FILE__))) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('manage_woocommerce')) return;
        
        $meta_fields = [
            'wcefp_mp_address' => '_wcefp_mp_address',
            'wcefp_mp_city' => '_wcefp_mp_city', 
            'wcefp_mp_lat' => '_wcefp_mp_lat',
            'wcefp_mp_lng' => '_wcefp_mp_lng',
            'wcefp_mp_notes' => '_wcefp_mp_notes'
        ];
        
        foreach ($meta_fields as $form_field => $meta_key) {
            if (isset($_POST[$form_field])) {
                $value = sanitize_text_field($_POST[$form_field]);
                update_post_meta($post_id, $meta_key, $value);
            }
        }
    }
    
    /**
     * Set custom columns for meeting points list
     */
    public function set_custom_edit_columns($columns) {
        unset($columns['date']);
        $columns['address'] = __('Address', 'wceventsfp');
        $columns['city'] = __('City', 'wceventsfp');
        $columns['coordinates'] = __('Coordinates', 'wceventsfp');
        $columns['date'] = __('Date', 'wceventsfp');
        return $columns;
    }
    
    /**
     * Custom column content for meeting points list
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'address':
                echo esc_html(get_post_meta($post_id, '_wcefp_mp_address', true));
                break;
            case 'city':
                echo esc_html(get_post_meta($post_id, '_wcefp_mp_city', true));
                break;
            case 'coordinates':
                $lat = get_post_meta($post_id, '_wcefp_mp_lat', true);
                $lng = get_post_meta($post_id, '_wcefp_mp_lng', true);
                if ($lat && $lng) {
                    echo esc_html($lat . ', ' . $lng);
                } else {
                    echo '—';
                }
                break;
        }
    }
    
    /**
     * AJAX handler to get meeting points for product selector
     */
    public function ajax_get_meeting_points() {
        check_ajax_referer('wcefp_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => 'Unauthorized']);
        }
        
        $meeting_points = get_posts([
            'post_type' => 'wcefp_meeting_point',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        $points = [];
        foreach ($meeting_points as $point) {
            $address = get_post_meta($point->ID, '_wcefp_mp_address', true);
            $city = get_post_meta($point->ID, '_wcefp_mp_city', true);
            $lat = get_post_meta($point->ID, '_wcefp_mp_lat', true);
            $lng = get_post_meta($point->ID, '_wcefp_mp_lng', true);
            
            $points[] = [
                'id' => $point->ID,
                'title' => $point->post_title,
                'address' => $address,
                'city' => $city,
                'lat' => $lat,
                'lng' => $lng,
                'full_address' => trim($address . ', ' . $city, ', ')
            ];
        }
        
        wp_send_json_success(['points' => $points]);
    }
    
    /**
     * Get all meeting points as options array
     * 
     * @return array Meeting points for select fields
     */
    public static function get_meeting_points_options() {
        $meeting_points = get_posts([
            'post_type' => 'wcefp_meeting_point',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        $options = ['' => __('Select a meeting point...', 'wceventsfp')];
        foreach ($meeting_points as $point) {
            $address = get_post_meta($point->ID, '_wcefp_mp_address', true);
            $city = get_post_meta($point->ID, '_wcefp_mp_city', true);
            $full_address = trim($address . ', ' . $city, ', ');
            $options[$point->ID] = $point->post_title . ($full_address ? ' (' . $full_address . ')' : '');
        }
        
        return $options;
    }
    
    /**
     * Get meeting point data by ID
     * 
     * @param int $meeting_point_id Meeting point post ID
     * @return array|false Meeting point data or false if not found
     */
    public static function get_meeting_point_data($meeting_point_id) {
        $point = get_post($meeting_point_id);
        if (!$point || $point->post_type !== 'wcefp_meeting_point') {
            return false;
        }
        
        return [
            'id' => $point->ID,
            'title' => $point->post_title,
            'description' => $point->post_content,
            'address' => get_post_meta($point->ID, '_wcefp_mp_address', true),
            'city' => get_post_meta($point->ID, '_wcefp_mp_city', true),
            'lat' => get_post_meta($point->ID, '_wcefp_mp_lat', true),
            'lng' => get_post_meta($point->ID, '_wcefp_mp_lng', true),
            'notes' => get_post_meta($point->ID, '_wcefp_mp_notes', true)
        ];
    }
}

// Initialize the meeting points CPT system
new WCEFP_MeetingPoints_CPT();