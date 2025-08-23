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
        // Product classes are now handled by the main autoloader
        // Legacy product types (wcefp_event, wcefp_experience) are loaded as needed
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
        if ($product_type === 'wcefp_event') {
            return 'WC_Product_WCEFP_Event';
        }
        if ($product_type === 'wcefp_experience') {
            return 'WC_Product_WCEFP_Experience';
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
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_meeting_point',
            'label' => '📍 ' . __('Punto di ritrovo', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Indirizzo o descrizione del punto di ritrovo per l\'esperienza', 'wceventsfp'),
            'placeholder' => 'Via Roma 123, Milano',
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
        
        // Add inline script for enhanced field behavior
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Add enhanced validation and visual feedback
            $('#wcefp_product_data input, #wcefp_product_data textarea').on('blur', function() {
                var $field = $(this).closest('.form-field');
                var value = $(this).val();
                
                // Remove existing validation classes
                $field.removeClass('has-error has-success');
                
                // Add validation feedback based on field requirements
                if ($(this).attr('required') || $(this).closest('[data-required]').length) {
                    if (value.trim() === '') {
                        $field.addClass('has-error');
                    } else {
                        $field.addClass('has-success');
                    }
                }
                
                // Specific validations
                if ($(this).attr('data-type') === 'price' && value) {
                    if (isNaN(parseFloat(value)) || parseFloat(value) <= 0) {
                        $field.addClass('has-error');
                    } else {
                        $field.addClass('has-success');
                    }
                }
                
                if ($(this).attr('type') === 'number' && value) {
                    var min = parseInt($(this).attr('min'));
                    var max = parseInt($(this).attr('max'));
                    var val = parseInt(value);
                    
                    if (isNaN(val) || (min && val < min) || (max && val > max)) {
                        $field.addClass('has-error');
                    } else {
                        $field.addClass('has-success');
                    }
                }
            });
            
            // Enhance language input with tags-like behavior
            $('#_wcefp_languages').on('keyup', function() {
                var value = $(this).val();
                if (value) {
                    // Auto-uppercase and format
                    var formatted = value.toUpperCase().replace(/\s*,\s*/g, ', ');
                    if (formatted !== value) {
                        $(this).val(formatted);
                    }
                }
            });
            
            // Real-time price formatting
            $('input[data-type="price"]').on('keyup', function() {
                var value = $(this).val();
                if (value && !isNaN(parseFloat(value))) {
                    $(this).closest('.form-field').addClass('has-success').removeClass('has-error');
                }
            });
        });
        </script>
        <?php
    
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
            '_wcefp_included',
            '_wcefp_excluded',
            '_wcefp_cancellation'
        ];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                if (in_array($field, ['_wcefp_included', '_wcefp_excluded', '_wcefp_cancellation'])) {
                    $value = sanitize_textarea_field($_POST[$field]);
                }
                $product->update_meta_data($field, $value);
            }
        }
    }
}