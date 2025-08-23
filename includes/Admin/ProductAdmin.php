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
        // Ensure WooCommerce product classes exist
        if (!class_exists('WC_Product_Evento')) {
            $evento_path = WCEFP_PLUGIN_DIR . 'includes/WooCommerce/ProductEvento.php';
            if (file_exists($evento_path)) {
                require_once $evento_path;
            }
        }
        
        if (!class_exists('WC_Product_Esperienza')) {
            $esperienza_path = WCEFP_PLUGIN_DIR . 'includes/WooCommerce/ProductEsperienza.php';
            if (file_exists($esperienza_path)) {
                require_once $esperienza_path;
            }
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
        if ($product_type === 'evento' || $product_type === 'esperienza') {
            return 'WC_Product_' . ucfirst($product_type);
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
            'label' => __('Eventi & Esperienze', 'wceventsfp'),
            'target' => 'wcefp_product_data',
            'class' => ['show_if_evento', 'show_if_esperienza'],
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
        echo '<div class="options_group">';
        
        // Price fields
        woocommerce_wp_text_input([
            'id' => '_wcefp_price_adult',
            'label' => __('Prezzo Adulto (€)', 'wceventsfp'),
            'data_type' => 'price',
            'desc_tip' => true,
            'description' => __('Prezzo per adulto', 'wceventsfp')
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_price_child',
            'label' => __('Prezzo Bambino (€)', 'wceventsfp'),
            'data_type' => 'price',
            'desc_tip' => true,
            'description' => __('Prezzo per bambino', 'wceventsfp')
        ]);
        
        // Capacity
        woocommerce_wp_text_input([
            'id' => '_wcefp_capacity',
            'label' => __('Capienza per slot', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1'],
            'desc_tip' => true,
            'description' => __('Numero massimo di partecipanti per slot', 'wceventsfp')
        ]);
        
        // Duration
        woocommerce_wp_text_input([
            'id' => '_wcefp_duration',
            'label' => __('Durata slot (minuti)', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => ['step' => '15', 'min' => '15'],
            'desc_tip' => true,
            'description' => __('Durata dello slot in minuti', 'wceventsfp')
        ]);
        
        echo '</div>';
        
        echo '<div class="options_group">';
        echo '<h4>' . __('Info esperienza', 'wceventsfp') . '</h4>';
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_languages',
            'label' => __('Lingue (es. IT, EN)', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Lingue disponibili separate da virgola', 'wceventsfp')
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_meeting_point',
            'label' => __('Meeting point', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Punto di ritrovo per l\'esperienza', 'wceventsfp')
        ]);
        
        woocommerce_wp_textarea_input([
            'id' => '_wcefp_included',
            'label' => __('Incluso', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Cosa è incluso nell\'esperienza', 'wceventsfp')
        ]);
        
        woocommerce_wp_textarea_input([
            'id' => '_wcefp_excluded',
            'label' => __('Escluso', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Cosa non è incluso nell\'esperienza', 'wceventsfp')
        ]);
        
        woocommerce_wp_textarea_input([
            'id' => '_wcefp_cancellation',
            'label' => __('Politica di cancellazione', 'wceventsfp'),
            'desc_tip' => true,
            'description' => __('Politica di cancellazione per l\'esperienza', 'wceventsfp')
        ]);
        
        echo '</div>';
        echo '</div>';
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