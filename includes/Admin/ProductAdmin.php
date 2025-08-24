<?php
/**
 * Product Admin Manager
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
 * Manages product administration for WCEFP
 */
class ProductAdmin {
    
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
        $this->load_product_classes();
        $this->init();
    }
    
    /**
     * Load required product classes
     * 
     * @return void
     */
    private function load_product_classes() {
        // Load legacy product classes
        $legacy_file = WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-product-types.php';
        if (file_exists($legacy_file)) {
            require_once $legacy_file;
        }
        
        // Load modern product classes
        $product_files = [
            WCEFP_PLUGIN_DIR . 'includes/WooCommerce/ProductEvento.php',
            WCEFP_PLUGIN_DIR . 'includes/WooCommerce/ProductEsperienza.php'
        ];
        
        foreach ($product_files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        // Register custom product types with WooCommerce
        add_action('init', [$this, 'register_product_types'], 20);
    }
    
    /**
     * Register custom product types with WooCommerce
     * 
     * @return void
     */
    public function register_product_types() {
        // Ensure WooCommerce is loaded
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Register evento product type
        if (!in_array('evento', wc_get_product_types())) {
            // This will be handled by the add_product_types filter
        }
        
        // Register esperienza product type  
        if (!in_array('esperienza', wc_get_product_types())) {
            // This will be handled by the add_product_types filter
        }
    }
    
    /**
     * Initialize product admin hooks
     * 
     * @return void
     */
    private function init() {
        add_filter('product_type_selector', [$this, 'add_product_types']);
        add_filter('woocommerce_product_class', [$this, 'product_class'], 10, 2);
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_product_data_panels']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_data']);
        add_action('save_post', [$this, 'preserve_product_type'], 10, 2);
        add_filter('woocommerce_product_type_query', [$this, 'filter_product_type_query'], 10, 2);
    }
    
    /**
     * Add custom product types
     * 
     * @param array $types Existing product types
     * @return array Modified product types
     */
    public function add_product_types($types) {
        $types['evento'] = __('Evento', 'wceventsfp');
        $types['esperienza'] = __('Esperienza', 'wceventsfp');
        return $types;
    }
    
    /**
     * Map product class based on type
     * 
     * @param string $classname Current class name
     * @param string $product_type Product type
     * @return string Product class name
     */
    public function product_class($classname, $product_type) {
        // Use legacy classes for backward compatibility
        if ($product_type === 'evento' || $product_type === 'wcefp_event') {
            return class_exists('WC_Product_Evento') ? 'WC_Product_Evento' : 'WC_Product_WCEFP_Event';
        }
        if ($product_type === 'esperienza' || $product_type === 'wcefp_experience') {
            return class_exists('WC_Product_Esperienza') ? 'WC_Product_Esperienza' : 'WC_Product_WCEFP_Experience';
        }
        return $classname;
    }
    
    /**
     * Add product data tab
     * 
     * @param array $tabs Existing tabs
     * @return array Modified tabs
     */
    public function add_product_data_tab($tabs) {
        $tabs['wcefp_data'] = [
            'label' => '🎯 ' . __('Eventi & Esperienze', 'wceventsfp'),
            'target' => 'wcefp_product_data',
            'class' => ['show_if_evento', 'show_if_esperienza', 'wcefp_tab'],
        ];
        
        $tabs['wcefp_extra_services'] = [
            'label' => '🎁 ' . __('Servizi Extra', 'wceventsfp'),
            'target' => 'wcefp_extra_services_tab',
            'class' => ['show_if_evento', 'show_if_esperienza', 'wcefp_tab'],
        ];
        
        return $tabs;
    }
    
    /**
     * Add product data panels
     * 
     * @return void
     */
    public function add_product_data_panels() {
        global $post;
        
        echo '<div id="wcefp_product_data" class="panel woocommerce_options_panel">';
        
        // Pricing Section
        echo '<div class="options_group">';
        echo '<h4>💰 ' . __('Configurazione Prezzi', 'wceventsfp') . '</h4>';
        echo '<div class="wcefp-field-row">';
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_price_adult',
            'label' => '👤 ' . __('Prezzo Adulto (€)', 'wceventsfp'),
            'data_type' => 'price',
            'desc_tip' => true,
            'description' => __('Prezzo per partecipante adulto', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_price_child',
            'label' => '🧒 ' . __('Prezzo Bambino (€)', 'wceventsfp'),
            'data_type' => 'price',
            'desc_tip' => true,
            'description' => __('Prezzo ridotto per bambini', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        echo '</div>'; // End field row
        echo '</div>'; // End options_group
        
        // Capacity & Timing Section
        echo '<div class="options_group">';
        echo '<h4>⚙️ ' . __('Configurazione Slot', 'wceventsfp') . '</h4>';
        echo '<div class="wcefp-field-row">';
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_capacity',
            'label' => '👥 ' . __('Capienza per slot', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1', 'max' => '200'],
            'desc_tip' => true,
            'description' => __('Numero massimo di partecipanti per ogni slot temporale', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_duration',
            'label' => '⏱️ ' . __('Durata slot (minuti)', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '15', 'min' => '15', 'max' => '1440'],
            'desc_tip' => true,
            'description' => __('Durata dello slot in minuti (multipli di 15)', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        echo '</div>'; // End field row
        echo '</div>'; // End options_group
        
        // Weekly Schedule & Time Slots Section
        echo '<div class="options_group">';
        echo '<h4>📅 ' . __('Ricorrenze settimanali & Slot', 'wceventsfp') . '</h4>';
        
        // Weekdays selection
        $weekdays = get_post_meta($post->ID, '_wcefp_weekdays', true);
        if (!is_array($weekdays)) {
            $weekdays = [];
        }
        
        $day_labels = [
            1 => __('Lunedì', 'wceventsfp'),
            2 => __('Martedì', 'wceventsfp'), 
            3 => __('Mercoledì', 'wceventsfp'),
            4 => __('Giovedì', 'wceventsfp'),
            5 => __('Venerdì', 'wceventsfp'),
            6 => __('Sabato', 'wceventsfp'),
            0 => __('Domenica', 'wceventsfp')
        ];
        
        echo '<div class="wcefp-weekdays-selection">';
        echo '<label class="wcefp-field-label"><strong>🗓️ ' . __('Giorni della settimana disponibili:', 'wceventsfp') . '</strong></label>';
        echo '<div class="wcefp-checkbox-grid wcefp-weekdays-fixed">';
        foreach ($day_labels as $day_num => $day_name) {
            $checked = in_array($day_num, $weekdays) ? 'checked="checked"' : '';
            $field_id = 'wcefp-weekday-' . $day_num;
            echo '<label class="wcefp-checkbox-item" for="' . esc_attr($field_id) . '">';
            echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="_wcefp_weekdays[]" value="' . $day_num . '" ' . $checked . ' />';
            echo '<span>' . esc_html($day_name) . '</span>';
            echo '</label>';
        }
        echo '</div>';
        echo '<p class="description">' . __('Seleziona i giorni in cui l\'esperienza è disponibile', 'wceventsfp') . '</p>';
        echo '</div>';
        
        // Time slots input
        $time_slots = get_post_meta($post->ID, '_wcefp_time_slots', true);
        echo '<div class="wcefp-time-slots-section">';
        echo '<label class="wcefp-field-label" for="_wcefp_time_slots"><strong>⏰ ' . __('Slot orari:', 'wceventsfp') . '</strong></label>';
        echo '<input type="text" id="_wcefp_time_slots" name="_wcefp_time_slots" value="' . esc_attr($time_slots) . '" class="regular-text" placeholder="09:00, 14:00, 16:30" />';
        echo '<p class="description">' . __('Orari degli slot separati da virgola (formato HH:MM, es: 09:00, 14:00, 16:30)', 'wceventsfp') . '</p>';
        echo '</div>';
        
        echo '</div>'; // End options_group
        
        // Experience Details Section
        echo '<div class="options_group">';
        echo '<h4>🌟 ' . __('Dettagli Esperienza', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_languages',
            'label' => '🌐 ' . __('Lingue disponibili', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Lingue supportate separate da virgola (es: IT, EN, FR)', 'wceventsfp'),
            'placeholder' => 'IT, EN, FR',
            'wrapper_class' => 'form-field wcefp-field-full'
        ]);
        
        // Meeting Point Selection - Enhanced with CPT
        echo '<div class="wcefp-meeting-point-container">';
        echo '<div class="wcefp-field-row">';
        
        // Meeting Point Selector
        if (class_exists('WCEFP_MeetingPoints_CPT')) {
            $meeting_points = WCEFP_MeetingPoints_CPT::get_meeting_points_options();
            $selected_mp = get_post_meta($product_id, '_wcefp_meeting_point_id', true);
            
            woocommerce_wp_select([
                'id' => '_wcefp_meeting_point_id',
                'label' => '📍 ' . __('Punto di ritrovo', 'wceventsfp'),
                'options' => $meeting_points,
                'value' => $selected_mp,
                'desc_tip' => true,
                'description' => __('Seleziona un punto di ritrovo riutilizzabile oppure crea un override personalizzato', 'wceventsfp'),
                'wrapper_class' => 'form-field wcefp-field-half'
            ]);
            
            // Link to add new meeting point
            echo '<div class="wcefp-field-half" style="padding-top: 20px;">';
            echo '<a href="' . admin_url('post-new.php?post_type=wcefp_meeting_point') . '" target="_blank" class="button">';
            echo __('➕ Nuovo Meeting Point', 'wceventsfp');
            echo '</a>';
            echo '</div>';
            echo '</div>'; // End field-row
            
            // Custom override field
            woocommerce_wp_textarea_input([
                'id' => '_wcefp_meeting_point_custom',
                'label' => '📝 ' . __('Override personalizzato', 'wceventsfp'),
                'desc_tip' => true,
                'description' => __('Testo personalizzato che sovrascrive il meeting point selezionato (opzionale)', 'wceventsfp'),
                'placeholder' => 'Es. Ritrovo eccezionale in Via Speciale 456...',
                'rows' => 2,
                'wrapper_class' => 'form-field wcefp-field-full'
            ]);
            
        } else {
            // Fallback to old text field if CPT class is not available
            woocommerce_wp_text_input([
                'id' => '_wcefp_meeting_point',
                'label' => '📍 ' . __('Punto di ritrovo', 'wceventsfp'),
                'desc_tip' => true,
                'description' => __('Indirizzo o descrizione del punto di ritrovo per l\'esperienza', 'wceventsfp'),
                'placeholder' => 'Via Roma 123, Milano',
                'wrapper_class' => 'form-field wcefp-field-full'
            ]);
        }
        
        echo '</div>'; // End meeting-point-container
        
        echo '</div>'; // End options_group
        
        // What's Included/Excluded Section
        echo '<div class="options_group">';
        echo '<h4>📋 ' . __('Cosa è Incluso/Escluso', 'wceventsfp') . '</h4>';
        echo '<div class="wcefp-field-row">';
        
        woocommerce_wp_textarea_input([
            'id' => '_wcefp_included',
            'label' => '✅ ' . __('Incluso nel prezzo', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Elenca tutto ciò che è incluso nell\'esperienza', 'wceventsfp'),
            'placeholder' => 'Degustazione vini, assaggi di formaggi locali, guida esperta...',
            'rows' => 4,
            'wrapper_class' => 'form-field'
        ]);
        
        woocommerce_wp_textarea_input([
            'id' => '_wcefp_excluded',
            'label' => '❌ ' . __('Non incluso', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Elenca ciò che NON è incluso nel prezzo', 'wceventsfp'),
            'placeholder' => 'Trasporto, bevande aggiuntive, pranzo completo...',
            'rows' => 4,
            'wrapper_class' => 'form-field'
        ]);
        
        echo '</div>'; // End field row
        echo '</div>'; // End options_group
        
        // Cancellation Policy Section
        echo '<div class="options_group">';
        echo '<h4>📄 ' . __('Politiche e Condizioni', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_textarea_input([
            'id' => '_wcefp_cancellation',
            'label' => '📋 ' . __('Politica di cancellazione', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Condizioni per cancellazione e rimborso dell\'esperienza', 'wceventsfp'),
            'placeholder' => 'Cancellazione gratuita fino a 24h prima. Rimborso 50% fino a 12h prima...',
            'rows' => 5,
            'wrapper_class' => 'form-field wcefp-field-full'
        ]);
        
        echo '</div>'; // End options_group
        echo '</div>'; // End wcefp_product_data
        
        // Enhanced field behavior is now handled by admin.js
    }
    
    /**
     * Save product data
     * 
     * @param \WC_Product $product Product object
     * @return void
     */
    public function save_product_data($product) {
        $fields = [
            '_wcefp_price_adult',
            '_wcefp_price_child', 
            '_wcefp_capacity',
            '_wcefp_duration',
            '_wcefp_languages',
            '_wcefp_meeting_point',
            '_wcefp_meeting_point_id',
            '_wcefp_meeting_point_custom',
            '_wcefp_included',
            '_wcefp_excluded',
            '_wcefp_cancellation',
            '_wcefp_time_slots'
        ];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                if (in_array($field, ['_wcefp_included', '_wcefp_excluded', '_wcefp_cancellation', '_wcefp_meeting_point_custom'])) {
                    $value = sanitize_textarea_field($_POST[$field]);
                }
                $product->update_meta_data($field, $value);
            }
        }
        
        // Handle weekdays array separately
        if (isset($_POST['_wcefp_weekdays']) && is_array($_POST['_wcefp_weekdays'])) {
            $weekdays = array_map('intval', $_POST['_wcefp_weekdays']);
            $product->update_meta_data('_wcefp_weekdays', $weekdays);
        } else {
            $product->update_meta_data('_wcefp_weekdays', []);
        }
        
        // Ensure product type is preserved for evento/esperienza products
        $product_type = $product->get_type();
        if (in_array($product_type, ['evento', 'esperienza'])) {
            $product->update_meta_data('_wcefp_product_type', $product_type);
            
            // Force the product type to be saved correctly
            if (isset($_POST['product-type'])) {
                $_POST['product-type'] = $product_type;
            }
        }
    }
    
    /**
     * Preserve product type on save to prevent reverting to simple product
     * 
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     * @return void
     */
    public function preserve_product_type($post_id, $post) {
        if ($post->post_type !== 'product') {
            return;
        }
        
        // Get the stored product type
        $stored_type = get_post_meta($post_id, '_wcefp_product_type', true);
        
        if ($stored_type && in_array($stored_type, ['evento', 'esperienza'])) {
            // Update the product type term
            wp_set_object_terms($post_id, $stored_type, 'product_type');
            
            // Ensure the meta is preserved
            update_post_meta($post_id, '_wcefp_product_type', $stored_type);
        }
    }
    
    /**
     * Filter product type query to handle our custom types
     * 
     * @param string $query_type Query type
     * @param int $product_id Product ID
     * @return string Product type
     */
    public function filter_product_type_query($query_type, $product_id) {
        $stored_type = get_post_meta($product_id, '_wcefp_product_type', true);
        
        if ($stored_type && in_array($stored_type, ['evento', 'esperienza'])) {
            return $stored_type;
        }
        
        return $query_type;
    }
}