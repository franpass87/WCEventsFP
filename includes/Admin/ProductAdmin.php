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
        
        $tabs['wcefp_scheduling'] = [
            'label' => '📅 ' . __('Pianificazione', 'wceventsfp'),
            'target' => 'wcefp_scheduling_tab',
            'class' => ['show_if_evento', 'show_if_esperienza', 'wcefp_tab'],
        ];
        
        $tabs['wcefp_tickets'] = [
            'label' => '🎫 ' . __('Biglietti', 'wceventsfp'),
            'target' => 'wcefp_tickets_tab',
            'class' => ['show_if_evento', 'show_if_esperienza', 'wcefp_tab'],
        ];
        
        $tabs['wcefp_capacity'] = [
            'label' => '👥 ' . __('Capienza', 'wceventsfp'),
            'target' => 'wcefp_capacity_tab',
            'class' => ['show_if_evento', 'show_if_esperienza', 'wcefp_tab'],
        ];
        
        $tabs['wcefp_extra_services'] = [
            'label' => '🎁 ' . __('Servizi Extra', 'wceventsfp'),
            'target' => 'wcefp_extra_services_tab',
            'class' => ['show_if_evento', 'show_if_esperienza', 'wcefp_tab'],
        ];
        
        $tabs['wcefp_meeting_points'] = [
            'label' => '📍 ' . __('Punti di Ritrovo', 'wceventsfp'),
            'target' => 'wcefp_meeting_points_tab',
            'class' => ['show_if_evento', 'show_if_esperienza', 'wcefp_tab'],
        ];
        
        $tabs['wcefp_policies'] = [
            'label' => '📋 ' . __('Politiche', 'wceventsfp'),
            'target' => 'wcefp_policies_tab',
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
        
        // Main Events & Experiences Tab (Basic Configuration)
        $this->render_main_product_tab($post);
        
        // Scheduling Tab
        $this->render_scheduling_tab($post);
        
        // Tickets Tab
        $this->render_tickets_tab($post);
        
        // Capacity Tab
        $this->render_capacity_tab($post);
        
        // Extra Services Tab
        $this->render_extra_services_tab($post);
        
        // Meeting Points Tab
        $this->render_meeting_points_tab($post);
        
        // Policies Tab
        $this->render_policies_tab($post);
    }
    
    /**
     * Render main product tab (basic configuration)
     * 
     * @param \WP_Post $post Post object
     * @return void
     */
    private function render_main_product_tab($post) {
        echo '<div id="wcefp_product_data" class="panel woocommerce_options_panel">';
        
        // Basic Pricing Section
        echo '<div class="options_group">';
        echo '<h4>💰 ' . __('Configurazione Prezzi Base', 'wceventsfp') . '</h4>';
        echo '<div class="wcefp-field-row">';
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_price_adult',
            'label' => '👤 ' . __('Prezzo Adulto (€)', 'wceventsfp'),
            'data_type' => 'price',
            'desc_tip' => true,
            'description' => __('Prezzo base per partecipante adulto', 'wceventsfp'),
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
        
        echo '</div>'; // End wcefp_product_data
    }
    
    /**
     * Render scheduling tab
     * 
     * @param \WP_Post $post Post object
     * @return void
     */
    private function render_scheduling_tab($post) {
        echo '<div id="wcefp_scheduling_tab" class="panel woocommerce_options_panel">';
        
        // Schedule Pattern Section
        echo '<div class="options_group">';
        echo '<h4>📅 ' . __('Schema di Pianificazione', 'wceventsfp') . '</h4>';
        
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
        
        echo '</div>'; // End options_group
        
        // Time Slots Section
        echo '<div class="options_group">';
        echo '<h4>⏰ ' . __('Slot Temporali', 'wceventsfp') . '</h4>';
        
        $time_slots = get_post_meta($post->ID, '_wcefp_time_slots', true);
        echo '<div class="wcefp-time-slots-section">';
        echo '<label class="wcefp-field-label" for="_wcefp_time_slots"><strong>' . __('Slot orari:', 'wceventsfp') . '</strong></label>';
        echo '<input type="text" id="_wcefp_time_slots" name="_wcefp_time_slots" value="' . esc_attr($time_slots) . '" class="regular-text" placeholder="09:00, 14:00, 16:30" />';
        echo '<p class="description">' . __('Orari degli slot separati da virgola (formato HH:MM, es: 09:00, 14:00, 16:30)', 'wceventsfp') . '</p>';
        echo '</div>';
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_duration',
            'label' => '⏱️ ' . __('Durata slot (minuti)', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '15', 'min' => '15', 'max' => '1440'],
            'desc_tip' => true,
            'description' => __('Durata dello slot in minuti (multipli di 15)', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        echo '</div>'; // End options_group
        
        // Advanced Scheduling Options
        echo '<div class="options_group">';
        echo '<h4>⚙️ ' . __('Opzioni Avanzate', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_auto_release_enabled',
            'label' => __('Auto-rilascio prenotazioni', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Rilascia automaticamente slot non confermati dopo 15 minuti', 'wceventsfp')
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_booking_window_start',
            'label' => '📅 ' . __('Finestra prenotazione - Inizio', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '0', 'max' => '365'],
            'desc_tip' => true,
            'description' => __('Giorni di anticipo minimo per prenotare', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_booking_window_end',
            'label' => '📅 ' . __('Finestra prenotazione - Fine', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1', 'max' => '365'],
            'desc_tip' => true,
            'description' => __('Giorni di anticipo massimo per prenotare', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        echo '</div>'; // End options_group
        
        echo '</div>'; // End wcefp_scheduling_tab
    }
    
    /**
     * Render tickets tab
     * 
     * @param \WP_Post $post Post object
     * @return void
     */
    private function render_tickets_tab($post) {
        echo '<div id="wcefp_tickets_tab" class="panel woocommerce_options_panel">';
        
        // Ticket Types Section
        echo '<div class="options_group">';
        echo '<h4>🎫 ' . __('Tipi di Biglietto', 'wceventsfp') . '</h4>';
        
        $ticket_types = get_post_meta($post->ID, '_wcefp_ticket_types', true) ?: [];
        
        echo '<div id="wcefp-ticket-types-container">';
        echo '<div class="wcefp-ticket-types-list">';
        
        // Display existing ticket types or default ones
        if (empty($ticket_types)) {
            $this->render_default_ticket_types();
        } else {
            foreach ($ticket_types as $index => $ticket_type) {
                $this->render_ticket_type_row($ticket_type, $index);
            }
        }
        
        echo '</div>';
        
        echo '<div class="wcefp-ticket-actions">';
        echo '<button type="button" class="button wcefp-add-ticket-type">' . __('+ Aggiungi Tipo Biglietto', 'wceventsfp') . '</button>';
        echo '</div>';
        
        echo '</div>'; // End ticket-types-container
        
        echo '</div>'; // End options_group
        
        // Dynamic Pricing Options
        echo '<div class="options_group">';
        echo '<h4>💰 ' . __('Prezzi Dinamici', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_seasonal_pricing_enabled',
            'label' => __('Prezzi stagionali', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Abilita aggiustamenti automatici dei prezzi basati sulla stagionalità', 'wceventsfp')
        ]);
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_demand_pricing_enabled',
            'label' => __('Prezzi basati sulla domanda', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Abilita aggiustamenti automatici dei prezzi basati sulla domanda', 'wceventsfp')
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_group_discount_threshold',
            'label' => '👥 ' . __('Soglia sconto gruppo', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '2', 'max' => '50'],
            'desc_tip' => true,
            'description' => __('Numero minimo di partecipanti per attivare lo sconto gruppo', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_group_discount_percentage',
            'label' => '💰 ' . __('Percentuale sconto gruppo (%)', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1', 'max' => '50'],
            'desc_tip' => true,
            'description' => __('Percentuale di sconto per prenotazioni di gruppo', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        echo '</div>'; // End options_group
        
        echo '</div>'; // End wcefp_tickets_tab
    }
    
    /**
     * Render capacity tab
     * 
     * @param \WP_Post $post Post object
     * @return void
     */
    private function render_capacity_tab($post) {
        echo '<div id="wcefp_capacity_tab" class="panel woocommerce_options_panel">';
        
        // Basic Capacity Configuration
        echo '<div class="options_group">';
        echo '<h4>👥 ' . __('Configurazione Capienza', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_capacity',
            'label' => '👥 ' . __('Capienza per slot', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1', 'max' => '500'],
            'desc_tip' => true,
            'description' => __('Numero massimo di partecipanti per ogni slot temporale', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_min_capacity',
            'label' => '👤 ' . __('Capienza minima', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1', 'max' => '50'],
            'desc_tip' => true,
            'description' => __('Numero minimo di partecipanti richiesto per confermare l\'esperienza', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        echo '</div>'; // End options_group
        
        // Overbooking Protection
        echo '<div class="options_group">';
        echo '<h4>⚠️ ' . __('Protezione Overbooking', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_overbooking_enabled',
            'label' => __('Consenti overbooking controllato', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Permette prenotazioni oltre la capienza per compensare possibili cancellazioni', 'wceventsfp')
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_overbooking_percentage',
            'label' => '📈 ' . __('Percentuale overbooking (%)', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '5', 'min' => '100', 'max' => '150'],
            'desc_tip' => true,
            'description' => __('Percentuale massima di overbooking consentito (es: 110% = 10% di overbooking)', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        echo '</div>'; // End options_group
        
        // Waitlist Configuration
        echo '<div class="options_group">';
        echo '<h4>📝 ' . __('Lista d\'Attesa', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_waitlist_enabled',
            'label' => __('Abilita lista d\'attesa', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Consente ai clienti di iscriversi alla lista d\'attesa quando l\'esperienza è al completo', 'wceventsfp')
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_waitlist_threshold',
            'label' => '📊 ' . __('Soglia lista d\'attesa (%)', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '5', 'min' => '80', 'max' => '100'],
            'desc_tip' => true,
            'description' => __('Percentuale di riempimento alla quale attivare la lista d\'attesa', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        echo '</div>'; // End options_group
        
        // Capacity Alerts
        echo '<div class="options_group">';
        echo '<h4>🔔 ' . __('Alert di Capienza', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_low_availability_threshold',
            'label' => '⚠️ ' . __('Soglia bassa disponibilità (%)', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '5', 'min' => '50', 'max' => '95'],
            'desc_tip' => true,
            'description' => __('Percentuale di riempimento alla quale inviare alert di bassa disponibilità', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_nearly_full_threshold',
            'label' => '🚨 ' . __('Soglia quasi completo (%)', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '5', 'min' => '85', 'max' => '100'],
            'desc_tip' => true,
            'description' => __('Percentuale di riempimento alla quale inviare alert di esperienza quasi completa', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        echo '</div>'; // End options_group
        
        echo '</div>'; // End wcefp_capacity_tab
    }
    
    /**
     * Render extra services tab
     * 
     * @param \WP_Post $post Post object
     * @return void
     */
    private function render_extra_services_tab($post) {
        echo '<div id="wcefp_extra_services_tab" class="panel woocommerce_options_panel">';
        
        // Product-Specific Extras Section
        echo '<div class="options_group">';
        echo '<h4>🎁 ' . __('Extra Specifici del Prodotto', 'wceventsfp') . '</h4>';
        
        $product_extras = get_post_meta($post->ID, '_wcefp_product_extras', true) ?: [];
        
        echo '<div id="wcefp-product-extras-container">';
        echo '<div class="wcefp-product-extras-list">';
        
        if (!empty($product_extras)) {
            foreach ($product_extras as $index => $extra) {
                $this->render_product_extra_row($extra, $index);
            }
        } else {
            echo '<div class="wcefp-no-extras">';
            echo '<p>' . __('Nessun extra specifico configurato per questo prodotto.', 'wceventsfp') . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
        
        echo '<div class="wcefp-extra-actions">';
        echo '<button type="button" class="button wcefp-add-product-extra">' . __('+ Aggiungi Extra Prodotto', 'wceventsfp') . '</button>';
        echo '</div>';
        
        echo '</div>'; // End product-extras-container
        
        echo '</div>'; // End options_group
        
        // Reusable Extras Section
        echo '<div class="options_group">';
        echo '<h4>🔗 ' . __('Extra Riutilizzabili', 'wceventsfp') . '</h4>';
        
        $linked_extras = get_post_meta($post->ID, '_wcefp_linked_extras', true) ?: [];
        
        echo '<div class="wcefp-linked-extras-section">';
        echo '<p class="description">' . __('Collega extra riutilizzabili da utilizzare con questo prodotto.', 'wceventsfp') . '</p>';
        
        // Available extras selector
        $available_extras_posts = get_posts([
            'post_type' => 'wcefp_extra',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ]);
        
        if (!empty($available_extras_posts)) {
            echo '<div class="wcefp-extras-selector">';
            echo '<label for="wcefp_available_extras"><strong>' . __('Extra disponibili:', 'wceventsfp') . '</strong></label>';
            echo '<select id="wcefp_available_extras" multiple="multiple" data-placeholder="' . __('Seleziona extra da collegare...', 'wceventsfp') . '">';
            
            foreach ($available_extras_posts as $extra_post) {
                $selected = in_array($extra_post->ID, $linked_extras) ? 'selected="selected"' : '';
                echo '<option value="' . $extra_post->ID . '" ' . $selected . '>';
                echo esc_html($extra_post->post_title);
                echo '</option>';
            }
            
            echo '</select>';
            echo '</div>';
            
            // Hidden input for form submission
            echo '<input type="hidden" name="_wcefp_linked_extras" id="wcefp_linked_extras_input" value="' . esc_attr(implode(',', $linked_extras)) . '" />';
            
        } else {
            echo '<div class="wcefp-no-extras-available">';
            echo '<p>' . __('Nessun extra riutilizzabile disponibile.', 'wceventsfp') . '</p>';
            echo '<a href="' . admin_url('post-new.php?post_type=wcefp_extra') . '" class="button">';
            echo __('Crea Nuovo Extra', 'wceventsfp');
            echo '</a>';
            echo '</div>';
        }
        
        echo '</div>'; // End linked-extras-section
        
        echo '</div>'; // End options_group
        
        // Extra Display Options
        echo '<div class="options_group">';
        echo '<h4>🎨 ' . __('Opzioni Visualizzazione', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_extras_required_selection',
            'label' => __('Selezione extra richiesta', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Richiede ai clienti di selezionare almeno un extra prima di procedere', 'wceventsfp')
        ]);
        
        woocommerce_wp_select([
            'id' => '_wcefp_extras_display_style',
            'label' => '🎨 ' . __('Stile visualizzazione', 'wceventsfp'),
            'options' => [
                'list' => __('Lista', 'wceventsfp'),
                'cards' => __('Cards', 'wceventsfp'),
                'dropdown' => __('Dropdown', 'wceventsfp'),
                'modal' => __('Modal popup', 'wceventsfp')
            ],
            'desc_tip' => true,
            'description' => __('Come visualizzare gli extra nel frontend', 'wceventsfp')
        ]);
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_show_extra_images',
            'label' => __('Mostra immagini extra', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Visualizza le immagini degli extra nel widget di prenotazione', 'wceventsfp')
        ]);
        
        echo '</div>'; // End options_group
        
        echo '</div>'; // End wcefp_extra_services_tab
    }
    
    /**
     * Render meeting points tab
     * 
     * @param \WP_Post $post Post object
     * @return void
     */
    private function render_meeting_points_tab($post) {
        echo '<div id="wcefp_meeting_points_tab" class="panel woocommerce_options_panel">';
        
        // Meeting Point Selection Section
        echo '<div class="options_group">';
        echo '<h4>📍 ' . __('Punto di Ritrovo Principale', 'wceventsfp') . '</h4>';
        
        // Get available meeting points from the service
        $meeting_points_options = $this->get_meeting_points_options();
        $selected_mp = get_post_meta($post->ID, '_wcefp_meeting_point_id', true);
        
        woocommerce_wp_select([
            'id' => '_wcefp_meeting_point_id',
            'label' => '📍 ' . __('Seleziona punto di ritrovo', 'wceventsfp'),
            'options' => $meeting_points_options,
            'value' => $selected_mp,
            'desc_tip' => true,
            'description' => __('Seleziona un punto di ritrovo riutilizzabile per questo prodotto', 'wceventsfp')
        ]);
        
        // Quick actions
        echo '<div class="wcefp-meeting-point-actions">';
        echo '<a href="' . admin_url('post-new.php?post_type=wcefp_meeting_point') . '" target="_blank" class="button">';
        echo __('➕ Nuovo Meeting Point', 'wceventsfp');
        echo '</a>';
        
        if ($selected_mp) {
            echo '<a href="' . admin_url('post.php?post=' . $selected_mp . '&action=edit') . '" target="_blank" class="button">';
            echo __('✏️ Modifica Meeting Point', 'wceventsfp');
            echo '</a>';
        }
        echo '</div>';
        
        echo '</div>'; // End options_group
        
        // Custom Override Section
        echo '<div class="options_group">';
        echo '<h4>📝 ' . __('Override Personalizzato', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_textarea_input([
            'id' => '_wcefp_meeting_point_custom',
            'label' => '📝 ' . __('Testo personalizzato', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Testo che sovrascrive il meeting point selezionato (opzionale). Utile per istruzioni specifiche per questo prodotto.', 'wceventsfp'),
            'placeholder' => __('Es. Per questa esperienza particolare, il ritrovo sarà in Via Speciale 456 anziché nella sede principale...', 'wceventsfp'),
            'rows' => 4,
            'wrapper_class' => 'form-field wcefp-field-full'
        ]);
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_meeting_point_override_always',
            'label' => __('Usa sempre l\'override personalizzato', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Se abilitato, il testo personalizzato verrà sempre mostrato indipendentemente dal meeting point selezionato', 'wceventsfp')
        ]);
        
        echo '</div>'; // End options_group
        
        // Alternative Meeting Points
        echo '<div class="options_group">';
        echo '<h4>🔄 ' . __('Meeting Point Alternativi', 'wceventsfp') . '</h4>';
        
        $alternative_meeting_points = get_post_meta($post->ID, '_wcefp_alternative_meeting_points', true) ?: [];
        
        echo '<div id="wcefp-alternative-meeting-points">';
        echo '<p class="description">' . __('Configura meeting point alternativi per condizioni speciali (maltempo, eventi, ecc.)', 'wceventsfp') . '</p>';
        
        if (!empty($alternative_meeting_points)) {
            foreach ($alternative_meeting_points as $index => $alt_mp) {
                $this->render_alternative_meeting_point_row($alt_mp, $index);
            }
        } else {
            echo '<div class="wcefp-no-alternatives">';
            echo '<p>' . __('Nessun meeting point alternativo configurato.', 'wceventsfp') . '</p>';
            echo '</div>';
        }
        
        echo '<div class="wcefp-alternative-actions">';
        echo '<button type="button" class="button wcefp-add-alternative-mp">' . __('+ Aggiungi Alternativo', 'wceventsfp') . '</button>';
        echo '</div>';
        
        echo '</div>'; // End alternative-meeting-points
        
        echo '</div>'; // End options_group
        
        // Meeting Point Display Options
        echo '<div class="options_group">';
        echo '<h4>🎨 ' . __('Opzioni Visualizzazione', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_show_meeting_point_map',
            'label' => __('Mostra mappa', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Visualizza una mappa del meeting point nel widget di prenotazione', 'wceventsfp')
        ]);
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_show_meeting_point_image',
            'label' => __('Mostra immagine', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Visualizza l\'immagine del meeting point se disponibile', 'wceventsfp')
        ]);
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_show_accessibility_info',
            'label' => __('Mostra info accessibilità', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Visualizza le informazioni di accessibilità del meeting point', 'wceventsfp')
        ]);
        
        echo '</div>'; // End options_group
        
        echo '</div>'; // End wcefp_meeting_points_tab
    }
    
    /**
     * Render policies tab
     * 
     * @param \WP_Post $post Post object
     * @return void
     */
    private function render_policies_tab($post) {
        echo '<div id="wcefp_policies_tab" class="panel woocommerce_options_panel">';
        
        // Cancellation Policy Section
        echo '<div class="options_group">';
        echo '<h4>🔄 ' . __('Politica di Cancellazione', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_custom_cancellation_enabled',
            'label' => __('Usa politica personalizzata', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Abilita per sovrascrivere la politica globale con regole specifiche per questo prodotto', 'wceventsfp')
        ]);
        
        echo '<div id="wcefp-cancellation-rules">';
        
        $cancellation_rules = get_post_meta($post->ID, '_wcefp_cancellation_rules', true) ?: [];
        
        echo '<div class="wcefp-rules-container">';
        
        if (!empty($cancellation_rules)) {
            foreach ($cancellation_rules as $index => $rule) {
                $this->render_cancellation_rule_row($rule, $index);
            }
        } else {
            // Show default rule
            $this->render_cancellation_rule_row([
                'timeframe' => '24h',
                'refund_percentage' => 100,
                'description' => __('Cancellazione gratuita fino a 24 ore prima', 'wceventsfp')
            ], 0);
        }
        
        echo '</div>'; // End rules-container
        
        echo '<div class="wcefp-rule-actions">';
        echo '<button type="button" class="button wcefp-add-cancellation-rule">' . __('+ Aggiungi Regola', 'wceventsfp') . '</button>';
        echo '</div>';
        
        echo '</div>'; // End cancellation-rules
        
        woocommerce_wp_textarea_input([
            'id' => '_wcefp_cancellation_custom_text',
            'label' => '📋 ' . __('Testo personalizzato', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Testo aggiuntivo per la politica di cancellazione', 'wceventsfp'),
            'placeholder' => __('Eventuali condizioni aggiuntive o eccezioni...', 'wceventsfp'),
            'rows' => 3,
            'wrapper_class' => 'form-field wcefp-field-full'
        ]);
        
        echo '</div>'; // End options_group
        
        // Rescheduling Policy Section
        echo '<div class="options_group">';
        echo '<h4>📅 ' . __('Politica di Riprogrammazione', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_rescheduling_enabled',
            'label' => __('Consenti riprogrammazione', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Permette ai clienti di riprogrammare la loro prenotazione', 'wceventsfp')
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_rescheduling_deadline',
            'label' => '⏰ ' . __('Termine per riprogrammazione', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Tempo minimo prima dell\'esperienza per consentire riprogrammazioni (es: 12h, 2d, 1w)', 'wceventsfp'),
            'placeholder' => '12h',
            'wrapper_class' => 'form-field'
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_rescheduling_fee_percentage',
            'label' => '💰 ' . __('Commissione riprogrammazione (%)', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '0', 'max' => '50'],
            'desc_tip' => true,
            'description' => __('Percentuale del prezzo da trattenere come commissione per la riprogrammazione', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_rescheduling_max_times',
            'label' => '🔄 ' . __('Max riprogrammazioni', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1', 'max' => '10'],
            'desc_tip' => true,
            'description' => __('Numero massimo di volte che una prenotazione può essere riprogrammata', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        echo '</div>'; // End options_group
        
        // Weather Policy Section
        echo '<div class="options_group">';
        echo '<h4>🌦️ ' . __('Politica Maltempo', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_weather_cancellation_enabled',
            'label' => __('Cancellazione per maltempo', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Consente cancellazioni automatiche in caso di maltempo estremo', 'wceventsfp')
        ]);
        
        woocommerce_wp_select([
            'id' => '_wcefp_weather_policy_type',
            'label' => '☔ ' . __('Tipo politica maltempo', 'wceventsfp'),
            'options' => [
                'full_refund' => __('Rimborso completo', 'wceventsfp'),
                'reschedule_only' => __('Solo riprogrammazione', 'wceventsfp'),
                'voucher' => __('Voucher validità estesa', 'wceventsfp'),
                'partial_refund' => __('Rimborso parziale', 'wceventsfp')
            ],
            'desc_tip' => true,
            'description' => __('Cosa offriamo in caso di cancellazione per maltempo', 'wceventsfp')
        ]);
        
        woocommerce_wp_textarea_input([
            'id' => '_wcefp_weather_policy_text',
            'label' => '🌧️ ' . __('Testo politica maltempo', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Descrizione dettagliata della politica in caso di maltempo', 'wceventsfp'),
            'placeholder' => __('In caso di condizioni meteorologiche avverse...', 'wceventsfp'),
            'rows' => 3,
            'wrapper_class' => 'form-field wcefp-field-full'
        ]);
        
        echo '</div>'; // End options_group
        
        // Health & Safety Policy Section
        echo '<div class="options_group">';
        echo '<h4>🛡️ ' . __('Politica Salute e Sicurezza', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_textarea_input([
            'id' => '_wcefp_health_safety_requirements',
            'label' => '⚕️ ' . __('Requisiti salute e sicurezza', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Requisiti specifici di salute e sicurezza per questo prodotto', 'wceventsfp'),
            'placeholder' => __('Età minima, condizioni fisiche richieste, restrizioni mediche...', 'wceventsfp'),
            'rows' => 4,
            'wrapper_class' => 'form-field wcefp-field-full'
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_min_age_requirement',
            'label' => '👶 ' . __('Età minima', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '0', 'max' => '100'],
            'desc_tip' => true,
            'description' => __('Età minima richiesta per partecipare (0 = nessun limite)', 'wceventsfp'),
            'wrapper_class' => 'form-field'
        ]);
        
        woocommerce_wp_checkbox([
            'id' => '_wcefp_requires_parental_consent',
            'label' => __('Richiede consenso genitoriale', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('I minori devono essere accompagnati o avere un consenso firmato', 'wceventsfp')
        ]);
        
        echo '</div>'; // End options_group
        
        echo '</div>'; // End wcefp_policies_tab
    }
    
    /**
     * Render default ticket types
     * 
     * @return void
     */
    private function render_default_ticket_types() {
        $default_types = [
            [
                'type' => 'adult',
                'label' => __('Adulto', 'wceventsfp'),
                'price' => '',
                'min_quantity' => 1,
                'max_quantity' => 10,
                'enabled' => true
            ],
            [
                'type' => 'child',
                'label' => __('Bambino', 'wceventsfp'),
                'price' => '',
                'min_quantity' => 0,
                'max_quantity' => 8,
                'enabled' => true
            ]
        ];
        
        foreach ($default_types as $index => $ticket_type) {
            $this->render_ticket_type_row($ticket_type, $index);
        }
    }
    
    /**
     * Render ticket type row
     * 
     * @param array $ticket_type Ticket type data
     * @param int $index Row index
     * @return void
     */
    private function render_ticket_type_row($ticket_type, $index) {
        echo '<div class="wcefp-ticket-type-row" data-index="' . $index . '">';
        echo '<div class="wcefp-ticket-fields">';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Tipo:', 'wceventsfp') . '</label>';
        echo '<input type="text" name="_wcefp_ticket_types[' . $index . '][type]" value="' . esc_attr($ticket_type['type'] ?? '') . '" class="regular-text" />';
        echo '</div>';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Etichetta:', 'wceventsfp') . '</label>';
        echo '<input type="text" name="_wcefp_ticket_types[' . $index . '][label]" value="' . esc_attr($ticket_type['label'] ?? '') . '" class="regular-text" />';
        echo '</div>';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Prezzo (€):', 'wceventsfp') . '</label>';
        echo '<input type="number" step="0.01" name="_wcefp_ticket_types[' . $index . '][price]" value="' . esc_attr($ticket_type['price'] ?? '') . '" class="small-text" />';
        echo '</div>';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Min:', 'wceventsfp') . '</label>';
        echo '<input type="number" name="_wcefp_ticket_types[' . $index . '][min_quantity]" value="' . esc_attr($ticket_type['min_quantity'] ?? 0) . '" class="small-text" />';
        echo '</div>';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Max:', 'wceventsfp') . '</label>';
        echo '<input type="number" name="_wcefp_ticket_types[' . $index . '][max_quantity]" value="' . esc_attr($ticket_type['max_quantity'] ?? 10) . '" class="small-text" />';
        echo '</div>';
        
        echo '<div class="wcefp-field-group">';
        echo '<label><input type="checkbox" name="_wcefp_ticket_types[' . $index . '][enabled]" value="1" ' . checked(!empty($ticket_type['enabled']), true, false) . ' /> ' . __('Attivo', 'wceventsfp') . '</label>';
        echo '</div>';
        
        echo '<div class="wcefp-field-actions">';
        echo '<button type="button" class="button wcefp-remove-ticket-type" title="' . __('Rimuovi', 'wceventsfp') . '">✕</button>';
        echo '</div>';
        
        echo '</div>'; // End ticket-fields
        echo '</div>'; // End ticket-type-row
    }
    
    /**
     * Render product extra row
     * 
     * @param array $extra Extra data
     * @param int $index Row index
     * @return void
     */
    private function render_product_extra_row($extra, $index) {
        echo '<div class="wcefp-product-extra-row" data-index="' . $index . '">';
        
        echo '<div class="wcefp-extra-fields">';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Nome:', 'wceventsfp') . '</label>';
        echo '<input type="text" name="_wcefp_product_extras[' . $index . '][name]" value="' . esc_attr($extra['name'] ?? '') . '" class="regular-text" />';
        echo '</div>';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Prezzo (€):', 'wceventsfp') . '</label>';
        echo '<input type="number" step="0.01" name="_wcefp_product_extras[' . $index . '][price]" value="' . esc_attr($extra['price'] ?? '') . '" class="small-text" />';
        echo '</div>';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Tipo prezzo:', 'wceventsfp') . '</label>';
        echo '<select name="_wcefp_product_extras[' . $index . '][pricing_type]">';
        $pricing_types = [
            'fixed' => __('Fisso', 'wceventsfp'),
            'per_person' => __('Per persona', 'wceventsfp'),
            'per_adult' => __('Per adulto', 'wceventsfp'),
            'per_child' => __('Per bambino', 'wceventsfp')
        ];
        foreach ($pricing_types as $value => $label) {
            $selected = selected($extra['pricing_type'] ?? 'fixed', $value, false);
            echo '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '<div class="wcefp-field-actions">';
        echo '<button type="button" class="button wcefp-remove-product-extra" title="' . __('Rimuovi', 'wceventsfp') . '">✕</button>';
        echo '</div>';
        
        echo '</div>'; // End extra-fields
        echo '</div>'; // End product-extra-row
    }
    
    /**
     * Get meeting points options for select field
     * 
     * @return array Meeting points options
     */
    private function get_meeting_points_options() {
        $options = ['' => __('Seleziona un meeting point...', 'wceventsfp')];
        
        // Get meeting points from CPT if available
        $meeting_points = get_posts([
            'post_type' => 'wcefp_meeting_point',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        foreach ($meeting_points as $mp) {
            $city = get_post_meta($mp->ID, '_wcefp_mp_city', true);
            $label = $mp->post_title;
            if ($city) {
                $label .= ' - ' . $city;
            }
            $options[$mp->ID] = $label;
        }
        
        return $options;
    }
    
    /**
     * Render alternative meeting point row
     * 
     * @param array $alt_mp Alternative meeting point data
     * @param int $index Row index
     * @return void
     */
    private function render_alternative_meeting_point_row($alt_mp, $index) {
        echo '<div class="wcefp-alternative-mp-row" data-index="' . $index . '">';
        
        echo '<div class="wcefp-alt-mp-fields">';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Condizione:', 'wceventsfp') . '</label>';
        echo '<input type="text" name="_wcefp_alternative_meeting_points[' . $index . '][condition]" value="' . esc_attr($alt_mp['condition'] ?? '') . '" placeholder="' . __('Es. Maltempo', 'wceventsfp') . '" class="regular-text" />';
        echo '</div>';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Meeting Point:', 'wceventsfp') . '</label>';
        $options = $this->get_meeting_points_options();
        echo '<select name="_wcefp_alternative_meeting_points[' . $index . '][meeting_point_id]">';
        foreach ($options as $value => $label) {
            $selected = selected($alt_mp['meeting_point_id'] ?? '', $value, false);
            echo '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '<div class="wcefp-field-actions">';
        echo '<button type="button" class="button wcefp-remove-alternative-mp" title="' . __('Rimuovi', 'wceventsfp') . '">✕</button>';
        echo '</div>';
        
        echo '</div>'; // End alt-mp-fields
        echo '</div>'; // End alternative-mp-row
    }
    
    /**
     * Render cancellation rule row
     * 
     * @param array $rule Cancellation rule data
     * @param int $index Row index
     * @return void
     */
    private function render_cancellation_rule_row($rule, $index) {
        echo '<div class="wcefp-cancellation-rule-row" data-index="' . $index . '">';
        
        echo '<div class="wcefp-rule-fields">';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Tempo limite:', 'wceventsfp') . '</label>';
        echo '<input type="text" name="_wcefp_cancellation_rules[' . $index . '][timeframe]" value="' . esc_attr($rule['timeframe'] ?? '') . '" placeholder="24h" class="small-text" />';
        echo '<span class="description">' . __('(es: 24h, 2d, 1w)', 'wceventsfp') . '</span>';
        echo '</div>';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Rimborso (%):', 'wceventsfp') . '</label>';
        echo '<input type="number" min="0" max="100" name="_wcefp_cancellation_rules[' . $index . '][refund_percentage]" value="' . esc_attr($rule['refund_percentage'] ?? '') . '" class="small-text" />';
        echo '</div>';
        
        echo '<div class="wcefp-field-group">';
        echo '<label>' . __('Descrizione:', 'wceventsfp') . '</label>';
        echo '<input type="text" name="_wcefp_cancellation_rules[' . $index . '][description]" value="' . esc_attr($rule['description'] ?? '') . '" class="regular-text" />';
        echo '</div>';
        
        echo '<div class="wcefp-field-actions">';
        echo '<button type="button" class="button wcefp-remove-cancellation-rule" title="' . __('Rimuovi', 'wceventsfp') . '">✕</button>';
        echo '</div>';
        
        echo '</div>'; // End rule-fields
        echo '</div>'; // End cancellation-rule-row
    }
    
    /**
     * Save product data
     * 
     * @param \WC_Product $product Product object
     * @return void
     */
    public function save_product_data($product) {
        // Basic fields
        $fields = [
            '_wcefp_price_adult',
            '_wcefp_price_child', 
            '_wcefp_capacity',
            '_wcefp_min_capacity',
            '_wcefp_duration',
            '_wcefp_languages',
            '_wcefp_meeting_point',
            '_wcefp_meeting_point_id',
            '_wcefp_meeting_point_custom',
            '_wcefp_included',
            '_wcefp_excluded',
            '_wcefp_cancellation',
            '_wcefp_time_slots',
            '_wcefp_booking_window_start',
            '_wcefp_booking_window_end'
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
        
        // Handle checkboxes
        $checkboxes = [
            '_wcefp_auto_release_enabled',
            '_wcefp_seasonal_pricing_enabled',
            '_wcefp_demand_pricing_enabled',
            '_wcefp_overbooking_enabled',
            '_wcefp_waitlist_enabled',
            '_wcefp_extras_required_selection',
            '_wcefp_show_extra_images',
            '_wcefp_meeting_point_override_always',
            '_wcefp_show_meeting_point_map',
            '_wcefp_show_meeting_point_image',
            '_wcefp_show_accessibility_info',
            '_wcefp_custom_cancellation_enabled',
            '_wcefp_rescheduling_enabled',
            '_wcefp_weather_cancellation_enabled',
            '_wcefp_requires_parental_consent'
        ];
        
        foreach ($checkboxes as $checkbox) {
            $product->update_meta_data($checkbox, !empty($_POST[$checkbox]));
        }
        
        // Handle numeric fields
        $numeric_fields = [
            '_wcefp_group_discount_threshold',
            '_wcefp_group_discount_percentage',
            '_wcefp_overbooking_percentage',
            '_wcefp_waitlist_threshold',
            '_wcefp_low_availability_threshold',
            '_wcefp_nearly_full_threshold',
            '_wcefp_rescheduling_fee_percentage',
            '_wcefp_rescheduling_max_times',
            '_wcefp_min_age_requirement'
        ];
        
        foreach ($numeric_fields as $field) {
            if (isset($_POST[$field])) {
                $product->update_meta_data($field, (int) $_POST[$field]);
            }
        }
        
        // Handle select fields
        $select_fields = [
            '_wcefp_extras_display_style',
            '_wcefp_weather_policy_type'
        ];
        
        foreach ($select_fields as $field) {
            if (isset($_POST[$field])) {
                $product->update_meta_data($field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Handle text fields with special characters
        $text_fields = [
            '_wcefp_rescheduling_deadline',
            '_wcefp_weather_policy_text',
            '_wcefp_health_safety_requirements',
            '_wcefp_cancellation_custom_text'
        ];
        
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                $product->update_meta_data($field, sanitize_textarea_field($_POST[$field]));
            }
        }
        
        // Handle complex arrays
        
        // Ticket types
        if (isset($_POST['_wcefp_ticket_types']) && is_array($_POST['_wcefp_ticket_types'])) {
            $ticket_types = [];
            foreach ($_POST['_wcefp_ticket_types'] as $ticket_type) {
                if (!empty($ticket_type['type'])) {
                    $ticket_types[] = [
                        'type' => sanitize_text_field($ticket_type['type']),
                        'label' => sanitize_text_field($ticket_type['label'] ?? ''),
                        'price' => (float) ($ticket_type['price'] ?? 0),
                        'min_quantity' => max(0, (int) ($ticket_type['min_quantity'] ?? 0)),
                        'max_quantity' => max(1, (int) ($ticket_type['max_quantity'] ?? 10)),
                        'enabled' => !empty($ticket_type['enabled'])
                    ];
                }
            }
            $product->update_meta_data('_wcefp_ticket_types', $ticket_types);
        }
        
        // Product extras
        if (isset($_POST['_wcefp_product_extras']) && is_array($_POST['_wcefp_product_extras'])) {
            $product_extras = [];
            foreach ($_POST['_wcefp_product_extras'] as $extra) {
                if (!empty($extra['name'])) {
                    $product_extras[] = [
                        'name' => sanitize_text_field($extra['name']),
                        'price' => (float) ($extra['price'] ?? 0),
                        'pricing_type' => sanitize_text_field($extra['pricing_type'] ?? 'fixed')
                    ];
                }
            }
            $product->update_meta_data('_wcefp_product_extras', $product_extras);
        }
        
        // Linked extras
        if (isset($_POST['_wcefp_linked_extras'])) {
            $linked_extras = array_filter(array_map('intval', explode(',', $_POST['_wcefp_linked_extras'])));
            $product->update_meta_data('_wcefp_linked_extras', $linked_extras);
        }
        
        // Alternative meeting points
        if (isset($_POST['_wcefp_alternative_meeting_points']) && is_array($_POST['_wcefp_alternative_meeting_points'])) {
            $alt_meeting_points = [];
            foreach ($_POST['_wcefp_alternative_meeting_points'] as $alt_mp) {
                if (!empty($alt_mp['condition'])) {
                    $alt_meeting_points[] = [
                        'condition' => sanitize_text_field($alt_mp['condition']),
                        'meeting_point_id' => (int) ($alt_mp['meeting_point_id'] ?? 0)
                    ];
                }
            }
            $product->update_meta_data('_wcefp_alternative_meeting_points', $alt_meeting_points);
        }
        
        // Cancellation rules
        if (isset($_POST['_wcefp_cancellation_rules']) && is_array($_POST['_wcefp_cancellation_rules'])) {
            $cancellation_rules = [];
            foreach ($_POST['_wcefp_cancellation_rules'] as $rule) {
                if (!empty($rule['timeframe'])) {
                    $cancellation_rules[] = [
                        'timeframe' => sanitize_text_field($rule['timeframe']),
                        'refund_percentage' => max(0, min(100, (int) ($rule['refund_percentage'] ?? 0))),
                        'description' => sanitize_text_field($rule['description'] ?? '')
                    ];
                }
            }
            $product->update_meta_data('_wcefp_cancellation_rules', $cancellation_rules);
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