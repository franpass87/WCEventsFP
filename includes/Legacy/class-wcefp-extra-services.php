<?php
/**
 * Extra Services Management
 * 
 * @package WCEFP
 * @since 2.1.4
 */

if (!defined('ABSPATH')) exit;

/**
 * Extra Services Management Class
 */
class WCEFP_Extra_Services {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('woocommerce_product_data_panels', [$this, 'add_extra_services_panel']);
        add_action('woocommerce_process_product_meta', [$this, 'save_extra_services']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_extra_services']);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_extra_services_to_cart'], 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_item_from_session'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_extra_services_in_cart'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'calculate_extra_services_price']);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_extra_services_to_order'], 10, 4);
        
        // AJAX handlers
        add_action('wp_ajax_wcefp_update_extra_services_price', [$this, 'ajax_update_price']);
        add_action('wp_ajax_nopriv_wcefp_update_extra_services_price', [$this, 'ajax_update_price']);
    }
    
    /**
     * Add extra services panel to product data tabs
     */
    public function add_extra_services_panel() {
        global $post;
        
        $product = wc_get_product($post->ID);
        if (!$product || !in_array($product->get_type(), ['wcefp_event', 'wcefp_experience'])) {
            return;
        }
        
        echo '<div id="wcefp_extra_services_tab" class="panel woocommerce_options_panel hidden">';
        echo '<div class="options_group">';
        echo '<h4>🎁 ' . __('Servizi Extra','wceventsfp') . '</h4>';
        echo '<p class="description">' . __('Aggiungi servizi extra opzionali che i clienti possono selezionare durante la prenotazione.', 'wceventsfp') . '</p>';
        
        $extras = $this->get_product_extras($post->ID);
        ?>
        
        <div class="wcefp-extras-container">
            <div class="wcefp-extras-header">
                <div class="wcefp-extra-col wcefp-extra-name"><?php _e('Nome Servizio', 'wceventsfp'); ?></div>
                <div class="wcefp-extra-col wcefp-extra-description"><?php _e('Descrizione', 'wceventsfp'); ?></div>
                <div class="wcefp-extra-col wcefp-extra-price"><?php _e('Prezzo (€)', 'wceventsfp'); ?></div>
                <div class="wcefp-extra-col wcefp-extra-required"><?php _e('Obbligatorio', 'wceventsfp'); ?></div>
                <div class="wcefp-extra-col wcefp-extra-max"><?php _e('Qty Max', 'wceventsfp'); ?></div>
                <div class="wcefp-extra-col wcefp-extra-actions"><?php _e('Azioni', 'wceventsfp'); ?></div>
            </div>
            
            <div class="wcefp-extras-list" id="wcefp-extras-list">
                <?php if (!empty($extras)): ?>
                    <?php foreach ($extras as $index => $extra): ?>
                        <div class="wcefp-extra-row" data-index="<?php echo esc_attr($index); ?>">
                            <div class="wcefp-extra-col wcefp-extra-name">
                                <input type="text" name="wcefp_extras[<?php echo esc_attr($index); ?>][name]" 
                                       value="<?php echo esc_attr($extra['name'] ?? ''); ?>" 
                                       placeholder="<?php esc_attr_e('Es. Degustazione vini', 'wceventsfp'); ?>" />
                            </div>
                            <div class="wcefp-extra-col wcefp-extra-description">
                                <textarea name="wcefp_extras[<?php echo esc_attr($index); ?>][description]" 
                                          placeholder="<?php esc_attr_e('Descrizione opzionale...', 'wceventsfp'); ?>"><?php echo esc_textarea($extra['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="wcefp-extra-col wcefp-extra-price">
                                <input type="number" name="wcefp_extras[<?php echo esc_attr($index); ?>][price]" 
                                       value="<?php echo esc_attr($extra['price'] ?? ''); ?>" 
                                       step="0.01" min="0" placeholder="0.00" />
                            </div>
                            <div class="wcefp-extra-col wcefp-extra-required">
                                <input type="checkbox" name="wcefp_extras[<?php echo esc_attr($index); ?>][required]" 
                                       value="1" <?php checked(!empty($extra['required'])); ?> />
                            </div>
                            <div class="wcefp-extra-col wcefp-extra-max">
                                <input type="number" name="wcefp_extras[<?php echo esc_attr($index); ?>][max_qty]" 
                                       value="<?php echo esc_attr($extra['max_qty'] ?? '1'); ?>" 
                                       min="1" max="99" />
                            </div>
                            <div class="wcefp-extra-col wcefp-extra-actions">
                                <button type="button" class="button wcefp-remove-extra">🗑️</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="wcefp-extras-footer">
                <button type="button" id="wcefp-add-extra" class="button button-primary">
                    ➕ <?php _e('Aggiungi Servizio Extra', 'wceventsfp'); ?>
                </button>
            </div>
        </div>
        
        <script type="text/html" id="wcefp-extra-row-template">
            <div class="wcefp-extra-row" data-index="{{INDEX}}">
                <div class="wcefp-extra-col wcefp-extra-name">
                    <input type="text" name="wcefp_extras[{{INDEX}}][name]" placeholder="<?php esc_attr_e('Es. Degustazione vini', 'wceventsfp'); ?>" />
                </div>
                <div class="wcefp-extra-col wcefp-extra-description">
                    <textarea name="wcefp_extras[{{INDEX}}][description]" placeholder="<?php esc_attr_e('Descrizione opzionale...', 'wceventsfp'); ?>"></textarea>
                </div>
                <div class="wcefp-extra-col wcefp-extra-price">
                    <input type="number" name="wcefp_extras[{{INDEX}}][price]" step="0.01" min="0" placeholder="0.00" />
                </div>
                <div class="wcefp-extra-col wcefp-extra-required">
                    <input type="checkbox" name="wcefp_extras[{{INDEX}}][required]" value="1" />
                </div>
                <div class="wcefp-extra-col wcefp-extra-max">
                    <input type="number" name="wcefp_extras[{{INDEX}}][max_qty]" value="1" min="1" max="99" />
                </div>
                <div class="wcefp-extra-col wcefp-extra-actions">
                    <button type="button" class="button wcefp-remove-extra">🗑️</button>
                </div>
            </div>
        </script>
        
        <script>
        jQuery(function($){
            var extraIndex = <?php echo count($extras); ?>;
            
            // Add extra service
            $('#wcefp-add-extra').on('click', function(){
                var template = $('#wcefp-extra-row-template').html();
                var html = template.replace(/{{INDEX}}/g, extraIndex);
                $('#wcefp-extras-list').append(html);
                extraIndex++;
            });
            
            // Remove extra service
            $(document).on('click', '.wcefp-remove-extra', function(){
                if (confirm('<?php esc_js_e('Sei sicuro di voler rimuovere questo servizio extra?', 'wceventsfp'); ?>')) {
                    $(this).closest('.wcefp-extra-row').remove();
                }
            });
        });
        </script>
        
        <?php
        echo '</div>'; // End options_group
        echo '</div>'; // End panel
    }
    
    /**
     * Save extra services data
     */
    public function save_extra_services($post_id) {
        if (!current_user_can('manage_woocommerce')) return;
        
        $extras = [];
        if (isset($_POST['wcefp_extras']) && is_array($_POST['wcefp_extras'])) {
            foreach ($_POST['wcefp_extras'] as $extra) {
                if (!empty($extra['name']) && isset($extra['price'])) {
                    $extras[] = [
                        'name' => sanitize_text_field($extra['name']),
                        'description' => sanitize_textarea_field($extra['description'] ?? ''),
                        'price' => floatval($extra['price']),
                        'required' => !empty($extra['required']),
                        'max_qty' => absint($extra['max_qty'] ?? 1)
                    ];
                }
            }
        }
        
        update_post_meta($post_id, '_wcefp_extra_services', $extras);
    }
    
    /**
     * Get product extras
     */
    public function get_product_extras($product_id) {
        return get_post_meta($product_id, '_wcefp_extra_services', true) ?: [];
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        if (is_product() || is_shop() || is_product_category()) {
            wp_enqueue_script(
                'wcefp-extra-services',
                WCEFP_PLUGIN_URL . 'assets/js/extra-services.js',
                ['jquery'],
                WCEFP_VERSION,
                true
            );
            
            wp_localize_script('wcefp-extra-services', 'WCEFPExtras', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcefp_extras'),
                'currency_symbol' => get_woocommerce_currency_symbol()
            ]);
        }
    }
    
    /**
     * Display extra services on product page
     */
    public function display_extra_services() {
        global $product;
        
        if (!$product || !in_array($product->get_type(), ['wcefp_event', 'wcefp_experience'])) {
            return;
        }
        
        $extras = $this->get_product_extras($product->get_id());
        if (empty($extras)) {
            return;
        }
        
        echo '<div class="wcefp-extra-services-wrapper">';
        echo '<h3>' . __('Servizi Extra', 'wceventsfp') . '</h3>';
        echo '<div class="wcefp-extra-services">';
        
        foreach ($extras as $index => $extra) {
            $field_id = 'wcefp_extra_' . $index;
            $price_display = wc_price($extra['price']);
            
            echo '<div class="wcefp-extra-service" data-index="' . esc_attr($index) . '" data-price="' . esc_attr($extra['price']) . '">';
            echo '<div class="wcefp-extra-header">';
            echo '<label for="' . esc_attr($field_id) . '">';
            echo '<span class="wcefp-extra-name">' . esc_html($extra['name']) . '</span>';
            echo '<span class="wcefp-extra-price">' . $price_display . '</span>';
            echo '</label>';
            echo '</div>';
            
            if (!empty($extra['description'])) {
                echo '<p class="wcefp-extra-description">' . esc_html($extra['description']) . '</p>';
            }
            
            echo '<div class="wcefp-extra-controls">';
            
            if ($extra['required']) {
                echo '<input type="hidden" name="wcefp_extras[' . esc_attr($index) . ']" value="1" />';
                echo '<span class="wcefp-required-badge">' . __('Obbligatorio', 'wceventsfp') . '</span>';
            } else {
                if ($extra['max_qty'] == 1) {
                    echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="wcefp_extras[' . esc_attr($index) . ']" value="1" class="wcefp-extra-checkbox" />';
                    echo '<label for="' . esc_attr($field_id) . '">' . __('Aggiungi', 'wceventsfp') . '</label>';
                } else {
                    echo '<label for="' . esc_attr($field_id) . '">' . __('Quantità:', 'wceventsfp') . '</label>';
                    echo '<select id="' . esc_attr($field_id) . '" name="wcefp_extras[' . esc_attr($index) . ']" class="wcefp-extra-select">';
                    echo '<option value="0">' . __('Nessuno', 'wceventsfp') . '</option>';
                    for ($i = 1; $i <= $extra['max_qty']; $i++) {
                        echo '<option value="' . esc_attr($i) . '">' . esc_html($i) . '</option>';
                    }
                    echo '</select>';
                }
            }
            
            echo '</div>'; // End controls
            echo '</div>'; // End extra service
        }
        
        echo '</div>'; // End extra services
        echo '<div class="wcefp-extra-total" style="display:none;">';
        echo '<strong>' . __('Totale servizi extra: ', 'wceventsfp') . '<span class="wcefp-extra-total-amount">€0,00</span></strong>';
        echo '</div>';
        echo '</div>'; // End wrapper
    }
    
    /**
     * Add extra services to cart
     */
    public function add_extra_services_to_cart($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['wcefp_extras']) && is_array($_POST['wcefp_extras'])) {
            $product = wc_get_product($product_id);
            if (!$product || !in_array($product->get_type(), ['wcefp_event', 'wcefp_experience'])) {
                return $cart_item_data;
            }
            
            $product_extras = $this->get_product_extras($product_id);
            $selected_extras = [];
            $extra_total = 0;
            
            foreach ($_POST['wcefp_extras'] as $index => $quantity) {
                $quantity = absint($quantity);
                if ($quantity > 0 && isset($product_extras[$index])) {
                    $extra = $product_extras[$index];
                    $selected_extras[$index] = [
                        'name' => $extra['name'],
                        'price' => $extra['price'],
                        'quantity' => $quantity
                    ];
                    $extra_total += $extra['price'] * $quantity;
                }
            }
            
            if (!empty($selected_extras)) {
                $cart_item_data['wcefp_extras'] = $selected_extras;
                $cart_item_data['wcefp_extra_total'] = $extra_total;
            }
        }
        
        return $cart_item_data;
    }
    
    /**
     * Get cart item from session
     */
    public function get_cart_item_from_session($item, $values, $key) {
        if (isset($values['wcefp_extras'])) {
            $item['wcefp_extras'] = $values['wcefp_extras'];
            $item['wcefp_extra_total'] = $values['wcefp_extra_total'] ?? 0;
        }
        return $item;
    }
    
    /**
     * Display extra services in cart
     */
    public function display_extra_services_in_cart($item_data, $cart_item) {
        if (isset($cart_item['wcefp_extras']) && !empty($cart_item['wcefp_extras'])) {
            foreach ($cart_item['wcefp_extras'] as $extra) {
                $item_data[] = [
                    'name' => $extra['name'],
                    'value' => $extra['quantity'] > 1 ? 
                        sprintf('%s x %d', wc_price($extra['price']), $extra['quantity']) : 
                        wc_price($extra['price'])
                ];
            }
        }
        return $item_data;
    }
    
    /**
     * Calculate extra services price
     */
    public function calculate_extra_services_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['wcefp_extra_total']) && $cart_item['wcefp_extra_total'] > 0) {
                $extra_price = floatval($cart_item['wcefp_extra_total']);
                $cart_item['data']->set_price($cart_item['data']->get_price() + $extra_price);
            }
        }
    }
    
    /**
     * Add extra services to order
     */
    public function add_extra_services_to_order($item, $cart_item_key, $values, $order) {
        if (isset($values['wcefp_extras']) && !empty($values['wcefp_extras'])) {
            $extras_text = [];
            foreach ($values['wcefp_extras'] as $extra) {
                $extras_text[] = $extra['quantity'] > 1 ? 
                    sprintf('%s x%d', $extra['name'], $extra['quantity']) :
                    $extra['name'];
            }
            $item->add_meta_data(__('Servizi Extra', 'wceventsfp'), implode(', ', $extras_text));
            $item->add_meta_data('_wcefp_extras_data', $values['wcefp_extras']);
        }
    }
    
    /**
     * AJAX handler for price updates
     */
    public function ajax_update_price() {
        check_ajax_referer('wcefp_extras', 'nonce');
        
        $product_id = absint($_POST['product_id'] ?? 0);
        $extras_data = $_POST['extras'] ?? [];
        
        if (!$product_id) {
            wp_send_json_error(['msg' => 'Invalid product']);
        }
        
        $product_extras = $this->get_product_extras($product_id);
        $total = 0;
        
        foreach ($extras_data as $index => $quantity) {
            $quantity = absint($quantity);
            if ($quantity > 0 && isset($product_extras[$index])) {
                $total += $product_extras[$index]['price'] * $quantity;
            }
        }
        
        wp_send_json_success([
            'total' => $total,
            'formatted_total' => wc_price($total)
        ]);
    }
}

// Initialize Extra Services
new WCEFP_Extra_Services();