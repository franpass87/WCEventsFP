<?php
/**
 * Meeting Points Manager
 * 
 * Manages the wcefp_meeting_point Custom Post Type and related functionality
 * 
 * @package WCEFP
 * @subpackage Admin
 * @since 2.2.0
 */

namespace WCEFP\Admin;

use WCEFP\Core\SecurityManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meeting Points Manager Class
 */
class MeetingPointsManager {
    
    /**
     * Post type name
     */
    const POST_TYPE = 'wcefp_meeting_point';
    
    /**
     * Initialize meeting points management
     * 
     * @return void
     */
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meeting_point'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // AJAX handlers for frontend
        add_action('wp_ajax_wcefp_get_meeting_points', [$this, 'ajax_get_meeting_points']);
        add_action('wp_ajax_nopriv_wcefp_get_meeting_points', [$this, 'ajax_get_meeting_points']);
    }
    
    /**
     * Register meeting point post type
     * 
     * @return void
     */
    public function register_post_type() {
        $labels = [
            'name' => __('Punti di Ritrovo', 'wceventsfp'),
            'singular_name' => __('Punto di Ritrovo', 'wceventsfp'),
            'menu_name' => __('Punti di Ritrovo', 'wceventsfp'),
            'add_new' => __('Aggiungi Nuovo', 'wceventsfp'),
            'add_new_item' => __('Aggiungi Nuovo Punto di Ritrovo', 'wceventsfp'),
            'edit_item' => __('Modifica Punto di Ritrovo', 'wceventsfp'),
            'new_item' => __('Nuovo Punto di Ritrovo', 'wceventsfp'),
            'view_item' => __('Visualizza Punto di Ritrovo', 'wceventsfp'),
            'search_items' => __('Cerca Punti di Ritrovo', 'wceventsfp'),
            'not_found' => __('Nessun punto di ritrovo trovato', 'wceventsfp'),
            'not_found_in_trash' => __('Nessun punto di ritrovo nel cestino', 'wceventsfp'),
        ];
        
        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=product',
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'rewrite' => false,
            'capability_type' => 'product',
            'capabilities' => [
                'create_posts' => 'manage_woocommerce',
                'edit_posts' => 'manage_woocommerce',
                'edit_others_posts' => 'manage_woocommerce',
                'publish_posts' => 'manage_woocommerce',
                'read_private_posts' => 'manage_woocommerce',
                'delete_posts' => 'manage_woocommerce',
                'delete_private_posts' => 'manage_woocommerce',
                'delete_published_posts' => 'manage_woocommerce',
                'delete_others_posts' => 'manage_woocommerce',
                'edit_private_posts' => 'manage_woocommerce',
                'edit_published_posts' => 'manage_woocommerce',
            ],
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-location',
        ];
        
        register_post_type(self::POST_TYPE, $args);
    }
    
    /**
     * Add meta boxes for meeting point details
     * 
     * @return void
     */
    public function add_meta_boxes() {
        add_meta_box(
            'wcefp_meeting_point_details',
            '📍 ' . __('Dettagli Punto di Ritrovo', 'wceventsfp'),
            [$this, 'render_details_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
        
        add_meta_box(
            'wcefp_meeting_point_location',
            '🗺️ ' . __('Posizione e Mappa', 'wceventsfp'),
            [$this, 'render_location_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
        
        add_meta_box(
            'wcefp_meeting_point_accessibility',
            '♿ ' . __('Accessibilità', 'wceventsfp'),
            [$this, 'render_accessibility_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }
    
    /**
     * Render details meta box
     * 
     * @param \WP_Post $post Post object
     * @return void
     */
    public function render_details_meta_box($post) {
        wp_nonce_field('wcefp_meeting_point_nonce', 'wcefp_meeting_point_nonce');
        
        $address = get_post_meta($post->ID, '_wcefp_address', true);
        $city = get_post_meta($post->ID, '_wcefp_city', true);
        $country = get_post_meta($post->ID, '_wcefp_country', true);
        $postal_code = get_post_meta($post->ID, '_wcefp_postal_code', true);
        $contact_phone = get_post_meta($post->ID, '_wcefp_contact_phone', true);
        $contact_email = get_post_meta($post->ID, '_wcefp_contact_email', true);
        $instructions = get_post_meta($post->ID, '_wcefp_instructions', true);
        
        echo '<table class="form-table">';
        
        // Address
        echo '<tr>';
        echo '<th><label for="wcefp_address">' . __('Indirizzo', 'wceventsfp') . '</label></th>';
        echo '<td><input type="text" id="wcefp_address" name="_wcefp_address" value="' . esc_attr($address) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        // City
        echo '<tr>';
        echo '<th><label for="wcefp_city">' . __('Città', 'wceventsfp') . '</label></th>';
        echo '<td><input type="text" id="wcefp_city" name="_wcefp_city" value="' . esc_attr($city) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        // Country  
        echo '<tr>';
        echo '<th><label for="wcefp_country">' . __('Paese', 'wceventsfp') . '</label></th>';
        echo '<td><input type="text" id="wcefp_country" name="_wcefp_country" value="' . esc_attr($country) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        // Postal Code
        echo '<tr>';
        echo '<th><label for="wcefp_postal_code">' . __('Codice Postale', 'wceventsfp') . '</label></th>';
        echo '<td><input type="text" id="wcefp_postal_code" name="_wcefp_postal_code" value="' . esc_attr($postal_code) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        // Contact Phone
        echo '<tr>';
        echo '<th><label for="wcefp_contact_phone">' . __('Telefono di Contatto', 'wceventsfp') . '</label></th>';
        echo '<td><input type="tel" id="wcefp_contact_phone" name="_wcefp_contact_phone" value="' . esc_attr($contact_phone) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        // Contact Email
        echo '<tr>';
        echo '<th><label for="wcefp_contact_email">' . __('Email di Contatto', 'wceventsfp') . '</label></th>';
        echo '<td><input type="email" id="wcefp_contact_email" name="_wcefp_contact_email" value="' . esc_attr($contact_email) . '" class="regular-text" /></td>';
        echo '</tr>';
        
        // Instructions
        echo '<tr>';
        echo '<th><label for="wcefp_instructions">' . __('Istruzioni per Raggiungere', 'wceventsfp') . '</label></th>';
        echo '<td><textarea id="wcefp_instructions" name="_wcefp_instructions" rows="4" class="large-text">' . esc_textarea($instructions) . '</textarea>';
        echo '<p class="description">' . __('Istruzioni dettagliate su come raggiungere il punto di ritrovo', 'wceventsfp') . '</p></td>';
        echo '</tr>';
        
        echo '</table>';
    }
    
    /**
     * Render location meta box
     * 
     * @param \WP_Post $post Post object
     * @return void
     */
    public function render_location_meta_box($post) {
        $latitude = get_post_meta($post->ID, '_wcefp_latitude', true);
        $longitude = get_post_meta($post->ID, '_wcefp_longitude', true);
        $map_zoom = get_post_meta($post->ID, '_wcefp_map_zoom', true) ?: 15;
        
        echo '<table class="form-table">';
        
        // Latitude
        echo '<tr>';
        echo '<th><label for="wcefp_latitude">' . __('Latitudine', 'wceventsfp') . '</label></th>';
        echo '<td><input type="text" id="wcefp_latitude" name="_wcefp_latitude" value="' . esc_attr($latitude) . '" class="regular-text" step="any" />';
        echo '<p class="description">' . __('Esempio: 41.9028', 'wceventsfp') . '</p></td>';
        echo '</tr>';
        
        // Longitude
        echo '<tr>';
        echo '<th><label for="wcefp_longitude">' . __('Longitudine', 'wceventsfp') . '</label></th>';
        echo '<td><input type="text" id="wcefp_longitude" name="_wcefp_longitude" value="' . esc_attr($longitude) . '" class="regular-text" step="any" />';
        echo '<p class="description">' . __('Esempio: 12.4964', 'wceventsfp') . '</p></td>';
        echo '</tr>';
        
        // Map Zoom
        echo '<tr>';
        echo '<th><label for="wcefp_map_zoom">' . __('Livello Zoom Mappa', 'wceventsfp') . '</label></th>';
        echo '<td><input type="number" id="wcefp_map_zoom" name="_wcefp_map_zoom" value="' . esc_attr($map_zoom) . '" min="1" max="20" class="small-text" />';
        echo '<p class="description">' . __('Da 1 (mondiale) a 20 (dettaglio strada)', 'wceventsfp') . '</p></td>';
        echo '</tr>';
        
        echo '</table>';
        
        // Map preview (if coordinates are available)
        if ($latitude && $longitude) {
            echo '<div id="wcefp-map-preview" style="height: 300px; margin-top: 20px; border: 1px solid #ddd; background: #f9f9f9; display: flex; align-items: center; justify-content: center;">';
            echo '<p>' . __('Anteprima mappa disponibile con integrazione Google Maps', 'wceventsfp') . '</p>';
            echo '</div>';
        }
        
        echo '<div class="wcefp-map-actions" style="margin-top: 10px;">';
        echo '<button type="button" class="button" id="wcefp-geocode-address">' . __('📍 Geocodifica Indirizzo', 'wceventsfp') . '</button>';
        echo '<button type="button" class="button" id="wcefp-locate-me">' . __('📍 Usa la Mia Posizione', 'wceventsfp') . '</button>';
        echo '</div>';
    }
    
    /**
     * Render accessibility meta box
     * 
     * @param \WP_Post $post Post object
     * @return void
     */
    public function render_accessibility_meta_box($post) {
        $wheelchair_accessible = get_post_meta($post->ID, '_wcefp_wheelchair_accessible', true);
        $public_transport = get_post_meta($post->ID, '_wcefp_public_transport', true);
        $parking_available = get_post_meta($post->ID, '_wcefp_parking_available', true);
        $accessibility_notes = get_post_meta($post->ID, '_wcefp_accessibility_notes', true);
        
        // Wheelchair Accessible
        echo '<p>';
        echo '<label><input type="checkbox" name="_wcefp_wheelchair_accessible" value="1"' . checked($wheelchair_accessible, 1, false) . '>';
        echo ' ' . __('Accessibile in sedia a rotelle', 'wceventsfp') . '</label>';
        echo '</p>';
        
        // Public Transport
        echo '<p>';
        echo '<label><input type="checkbox" name="_wcefp_public_transport" value="1"' . checked($public_transport, 1, false) . '>';
        echo ' ' . __('Raggiungibile con mezzi pubblici', 'wceventsfp') . '</label>';
        echo '</p>';
        
        // Parking Available
        echo '<p>';
        echo '<label><input type="checkbox" name="_wcefp_parking_available" value="1"' . checked($parking_available, 1, false) . '>';
        echo ' ' . __('Parcheggio disponibile', 'wceventsfp') . '</label>';
        echo '</p>';
        
        // Accessibility Notes
        echo '<p>';
        echo '<label for="wcefp_accessibility_notes">' . __('Note di Accessibilità', 'wceventsfp') . '</label>';
        echo '<textarea id="wcefp_accessibility_notes" name="_wcefp_accessibility_notes" rows="4" class="widefat">' . esc_textarea($accessibility_notes) . '</textarea>';
        echo '</p>';
    }
    
    /**
     * Save meeting point data
     * 
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     * @return void
     */
    public function save_meeting_point($post_id, $post) {
        // Verify nonce and permissions
        if (!isset($_POST['wcefp_meeting_point_nonce']) || 
            !wp_verify_nonce($_POST['wcefp_meeting_point_nonce'], 'wcefp_meeting_point_nonce')) {
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        if ($post->post_type !== self::POST_TYPE) {
            return;
        }
        
        // Avoid infinite loops
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Save location details
        $fields = [
            '_wcefp_address',
            '_wcefp_city', 
            '_wcefp_country',
            '_wcefp_postal_code',
            '_wcefp_contact_phone',
            '_wcefp_contact_email',
            '_wcefp_instructions',
            '_wcefp_latitude',
            '_wcefp_longitude',
            '_wcefp_map_zoom',
            '_wcefp_accessibility_notes'
        ];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Save checkbox fields
        $checkbox_fields = [
            '_wcefp_wheelchair_accessible',
            '_wcefp_public_transport', 
            '_wcefp_parking_available'
        ];
        
        foreach ($checkbox_fields as $field) {
            update_post_meta($post_id, $field, isset($_POST[$field]) ? 1 : 0);
        }
    }
    
    /**
     * Enqueue admin scripts for meeting points
     * 
     * @param string $hook Current admin page
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if ($post_type === self::POST_TYPE && in_array($hook, ['post.php', 'post-new.php'])) {
            wp_enqueue_script('wcefp-meeting-points-admin', 
                WCEFP_PLUGIN_URL . 'assets/admin/js/meeting-points.js', 
                ['jquery'], WCEFP_VERSION, true);
            
            wp_localize_script('wcefp-meeting-points-admin', 'wcefp_mp_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcefp_meeting_points_nonce'),
                'i18n' => [
                    'geocoding_error' => __('Errore nella geocodificazione dell\'indirizzo', 'wceventsfp'),
                    'location_error' => __('Impossibile ottenere la posizione', 'wceventsfp'),
                    'location_success' => __('Posizione aggiornata con successo', 'wceventsfp')
                ]
            ]);
        }
    }
    
    /**
     * AJAX handler to get meeting points for product selectors
     * 
     * @return void
     */
    public function ajax_get_meeting_points() {
        check_ajax_referer('wcefp_meeting_points_nonce', 'nonce');
        
        $meeting_points = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        $formatted_points = [];
        foreach ($meeting_points as $point) {
            $formatted_points[] = [
                'id' => $point->ID,
                'title' => $point->post_title,
                'address' => get_post_meta($point->ID, '_wcefp_address', true),
                'city' => get_post_meta($point->ID, '_wcefp_city', true),
                'country' => get_post_meta($point->ID, '_wcefp_country', true),
                'latitude' => get_post_meta($point->ID, '_wcefp_latitude', true),
                'longitude' => get_post_meta($point->ID, '_wcefp_longitude', true),
                'wheelchair_accessible' => get_post_meta($point->ID, '_wcefp_wheelchair_accessible', true),
                'public_transport' => get_post_meta($point->ID, '_wcefp_public_transport', true),
                'parking_available' => get_post_meta($point->ID, '_wcefp_parking_available', true)
            ];
        }
        
        wp_send_json_success($formatted_points);
    }
    
    /**
     * Get formatted meeting point data
     * 
     * @param int $meeting_point_id Meeting point ID
     * @return array|null Meeting point data or null if not found
     */
    public static function get_meeting_point_data($meeting_point_id) {
        $post = get_post($meeting_point_id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return null;
        }
        
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'description' => $post->post_content,
            'address' => get_post_meta($post->ID, '_wcefp_address', true),
            'city' => get_post_meta($post->ID, '_wcefp_city', true),
            'country' => get_post_meta($post->ID, '_wcefp_country', true),
            'postal_code' => get_post_meta($post->ID, '_wcefp_postal_code', true),
            'contact_phone' => get_post_meta($post->ID, '_wcefp_contact_phone', true),
            'contact_email' => get_post_meta($post->ID, '_wcefp_contact_email', true),
            'instructions' => get_post_meta($post->ID, '_wcefp_instructions', true),
            'latitude' => get_post_meta($post->ID, '_wcefp_latitude', true),
            'longitude' => get_post_meta($post->ID, '_wcefp_longitude', true),
            'map_zoom' => get_post_meta($post->ID, '_wcefp_map_zoom', true),
            'wheelchair_accessible' => get_post_meta($post->ID, '_wcefp_wheelchair_accessible', true),
            'public_transport' => get_post_meta($post->ID, '_wcefp_public_transport', true),
            'parking_available' => get_post_meta($post->ID, '_wcefp_parking_available', true),
            'accessibility_notes' => get_post_meta($post->ID, '_wcefp_accessibility_notes', true)
        ];
    }
}