<?php
/**
 * Extras Module
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
 * Extras (additional services) management module
 */
class ExtrasModule extends ServiceProvider {
    
    /**
     * Register module services
     * 
     * @return void
     */
    public function register(): void {
        // Register extras services
    }
    
    /**
     * Boot module services
     * 
     * @return void
     */
    public function boot(): void {
        // Hook into WordPress lifecycle
        add_action('init', [$this, 'initialize_features'], 20);
        add_action('add_meta_boxes', [$this, 'add_product_meta_boxes']);
        add_action('save_post', [$this, 'save_product_extras']);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_extras_frontend']);
        add_action('woocommerce_add_cart_item_data', [$this, 'add_extras_to_cart'], 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_extras_to_order'], 10, 4);
        
        // AJAX handlers for dynamic pricing
        add_action('wp_ajax_wcefp_calculate_extras_price', [$this, 'ajax_calculate_extras_price']);
        add_action('wp_ajax_nopriv_wcefp_calculate_extras_price', [$this, 'ajax_calculate_extras_price']);
        
        Logger::info('Extras module booted successfully');
    }
    
    /**
     * Initialize extra features
     * 
     * @return void
     */
    public function initialize_features(): void {
        // Enqueue frontend scripts for extras calculation
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }
    
    /**
     * Add meta boxes for product extras
     * 
     * @return void
     */
    public function add_product_meta_boxes(): void {
        $product_types = ['wcefp_event', 'wcefp_experience'];
        
        foreach ($product_types as $product_type) {
            add_meta_box(
                'wcefp_product_extras',
                __('Additional Services (Extras)', 'wceventsfp'),
                [$this, 'render_extras_meta_box'],
                'product',
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render extras meta box
     * 
     * @param \WP_Post $post
     * @return void
     */
    public function render_extras_meta_box($post): void {
        wp_nonce_field('wcefp_extras_meta', 'wcefp_extras_nonce');
        
        $extras = get_post_meta($post->ID, '_wcefp_extras', true);
        $extras = is_array($extras) ? $extras : [];
        
        ?>
        <div id="wcefp-extras-container">
            <div class="wcefp-extras-header">
                <h4><?php esc_html_e('Additional Services Configuration', 'wceventsfp'); ?></h4>
                <button type="button" class="button wcefp-add-extra">
                    <?php esc_html_e('Add Extra Service', 'wceventsfp'); ?>
                </button>
            </div>
            
            <div class="wcefp-extras-list">
                <?php if (empty($extras)): ?>
                    <p class="wcefp-no-extras"><?php esc_html_e('No extra services configured.', 'wceventsfp'); ?></p>
                <?php else: ?>
                    <?php foreach ($extras as $index => $extra): ?>
                        <?php $this->render_extra_row($index, $extra); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <script type="text/template" id="wcefp-extra-row-template">
                <?php $this->render_extra_row('{{INDEX}}', []); ?>
            </script>
        </div>
        
        <style>
            .wcefp-extras-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
            .wcefp-extra-row { border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; background: #f9f9f9; }
            .wcefp-extra-row .form-table { margin: 0; }
            .wcefp-extra-row .form-table th { width: 150px; }
            .wcefp-remove-extra { color: #a00; }
            .wcefp-remove-extra:hover { color: #dc3232; }
        </style>
        
        <script>
            jQuery(document).ready(function($) {
                let extraIndex = <?php echo count($extras); ?>;
                
                $('.wcefp-add-extra').on('click', function() {
                    let template = $('#wcefp-extra-row-template').html();
                    template = template.replace(/{{INDEX}}/g, extraIndex);
                    $('.wcefp-extras-list').append(template);
                    $('.wcefp-no-extras').hide();
                    extraIndex++;
                });
                
                $(document).on('click', '.wcefp-remove-extra', function(e) {
                    e.preventDefault();
                    $(this).closest('.wcefp-extra-row').remove();
                    if ($('.wcefp-extra-row').length === 0) {
                        $('.wcefp-no-extras').show();
                    }
                });
            });
        </script>
        <?php
    }
    
    /**
     * Render single extra row
     * 
     * @param int|string $index
     * @param array $extra
     * @return void
     */
    private function render_extra_row($index, $extra): void {
        $name = $extra['name'] ?? '';
        $description = $extra['description'] ?? '';
        $price = $extra['price'] ?? '';
        $max_quantity = $extra['max_quantity'] ?? '1';
        $required = !empty($extra['required']);
        
        ?>
        <div class="wcefp-extra-row">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <h4><?php esc_html_e('Extra Service', 'wceventsfp'); ?> #<?php echo esc_html($index + 1); ?></h4>
                <a href="#" class="wcefp-remove-extra"><?php esc_html_e('Remove', 'wceventsfp'); ?></a>
            </div>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Service Name', 'wceventsfp'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="wcefp_extras[<?php echo esc_attr($index); ?>][name]" 
                               value="<?php echo esc_attr($name); ?>" 
                               class="regular-text" 
                               required />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Description', 'wceventsfp'); ?></label>
                    </th>
                    <td>
                        <textarea name="wcefp_extras[<?php echo esc_attr($index); ?>][description]" 
                                  class="large-text" 
                                  rows="2"><?php echo esc_textarea($description); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Price', 'wceventsfp'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               name="wcefp_extras[<?php echo esc_attr($index); ?>][price]" 
                               value="<?php echo esc_attr($price); ?>" 
                               step="0.01" 
                               min="0" 
                               class="small-text" />
                        <span class="description"><?php echo get_woocommerce_currency_symbol(); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Max Quantity', 'wceventsfp'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               name="wcefp_extras[<?php echo esc_attr($index); ?>][max_quantity]" 
                               value="<?php echo esc_attr($max_quantity); ?>" 
                               min="1" 
                               max="100" 
                               class="small-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Required', 'wceventsfp'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="wcefp_extras[<?php echo esc_attr($index); ?>][required]" 
                                   value="1" 
                                   <?php checked($required, true); ?> />
                            <?php esc_html_e('This service is required', 'wceventsfp'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Save product extras meta
     * 
     * @param int $post_id
     * @return void
     */
    public function save_product_extras($post_id): void {
        // Verify nonce
        if (!isset($_POST['wcefp_extras_nonce']) || 
            !wp_verify_nonce($_POST['wcefp_extras_nonce'], 'wcefp_extras_meta')) {
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
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        
        $extras = $_POST['wcefp_extras'] ?? [];
        $sanitized_extras = [];
        
        foreach ($extras as $index => $extra) {
            if (empty($extra['name'])) {
                continue; // Skip empty extras
            }
            
            $sanitized_extras[] = [
                'name' => sanitize_text_field($extra['name']),
                'description' => sanitize_textarea_field($extra['description'] ?? ''),
                'price' => floatval($extra['price'] ?? 0),
                'max_quantity' => absint($extra['max_quantity'] ?? 1),
                'required' => !empty($extra['required'])
            ];
        }
        
        update_post_meta($post_id, '_wcefp_extras', $sanitized_extras);
    }
    
    /**
     * Display extras on frontend product page
     * 
     * @return void
     */
    public function display_extras_frontend(): void {
        global $product;
        
        if (!$product || !in_array($product->get_type(), ['wcefp_event', 'wcefp_experience'])) {
            return;
        }
        
        $extras = get_post_meta($product->get_id(), '_wcefp_extras', true);
        
        if (empty($extras)) {
            return;
        }
        
        ?>
        <div class="wcefp-extras-selection">
            <h3><?php esc_html_e('Additional Services', 'wceventsfp'); ?></h3>
            
            <?php foreach ($extras as $index => $extra): ?>
                <div class="wcefp-extra-item" data-price="<?php echo esc_attr($extra['price']); ?>">
                    <div class="wcefp-extra-header">
                        <h4><?php echo esc_html($extra['name']); ?></h4>
                        <span class="wcefp-extra-price">
                            <?php echo wc_price($extra['price']); ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($extra['description'])): ?>
                        <p class="wcefp-extra-description">
                            <?php echo esc_html($extra['description']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="wcefp-extra-quantity">
                        <label for="wcefp_extra_<?php echo esc_attr($index); ?>">
                            <?php esc_html_e('Quantity:', 'wceventsfp'); ?>
                        </label>
                        <select name="wcefp_extras[<?php echo esc_attr($index); ?>]" 
                                id="wcefp_extra_<?php echo esc_attr($index); ?>" 
                                class="wcefp-extra-select"
                                <?php echo $extra['required'] ? 'required' : ''; ?>>
                            
                            <?php if (!$extra['required']): ?>
                                <option value="0"><?php esc_html_e('None', 'wceventsfp'); ?></option>
                            <?php endif; ?>
                            
                            <?php for ($i = ($extra['required'] ? 1 : 0); $i <= $extra['max_quantity']; $i++): ?>
                                <option value="<?php echo esc_attr($i); ?>">
                                    <?php echo esc_html($i); ?>
                                    <?php if ($i > 0): ?>
                                        (<?php echo wc_price($extra['price'] * $i); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="wcefp-extras-total">
                <strong><?php esc_html_e('Extras Total:', 'wceventsfp'); ?> 
                    <span class="wcefp-extras-total-amount">0</span>
                </strong>
            </div>
        </div>
        
        <style>
            .wcefp-extras-selection { margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; }
            .wcefp-extra-item { margin-bottom: 15px; padding: 10px; background: white; border: 1px solid #eee; }
            .wcefp-extra-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
            .wcefp-extra-header h4 { margin: 0; }
            .wcefp-extra-price { font-weight: bold; color: #333; }
            .wcefp-extra-description { margin: 5px 0; color: #666; font-size: 0.9em; }
            .wcefp-extra-quantity { margin-top: 10px; }
            .wcefp-extras-total { margin-top: 15px; text-align: right; font-size: 1.1em; }
        </style>
        <?php
    }
    
    /**
     * Add extras data to cart item
     * 
     * @param array $cart_item_data
     * @param int $product_id
     * @param int $variation_id
     * @return array
     */
    public function add_extras_to_cart($cart_item_data, $product_id, $variation_id): array {
        if (empty($_POST['wcefp_extras'])) {
            return $cart_item_data;
        }
        
        $extras_data = [];
        $extras_config = get_post_meta($product_id, '_wcefp_extras', true);
        $total_extras_price = 0;
        
        foreach ($_POST['wcefp_extras'] as $index => $quantity) {
            $quantity = absint($quantity);
            
            if ($quantity > 0 && isset($extras_config[$index])) {
                $extra = $extras_config[$index];
                $line_total = $extra['price'] * $quantity;
                
                $extras_data[] = [
                    'name' => $extra['name'],
                    'quantity' => $quantity,
                    'unit_price' => $extra['price'],
                    'line_total' => $line_total
                ];
                
                $total_extras_price += $line_total;
            }
        }
        
        if (!empty($extras_data)) {
            $cart_item_data['wcefp_extras'] = $extras_data;
            $cart_item_data['wcefp_extras_price'] = $total_extras_price;
        }
        
        return $cart_item_data;
    }
    
    /**
     * Add extras to order line item
     * 
     * @param \WC_Order_Item_Product $item
     * @param string $cart_item_key
     * @param array $values
     * @param \WC_Order $order
     * @return void
     */
    public function add_extras_to_order($item, $cart_item_key, $values, $order): void {
        if (!empty($values['wcefp_extras'])) {
            $item->add_meta_data('_wcefp_extras', $values['wcefp_extras']);
            $item->add_meta_data('_wcefp_extras_price', $values['wcefp_extras_price']);
            
            // Add individual extras for display
            foreach ($values['wcefp_extras'] as $extra) {
                $item->add_meta_data(
                    $extra['name'],
                    sprintf('%d × %s', $extra['quantity'], wc_price($extra['unit_price']))
                );
            }
        }
    }
    
    /**
     * AJAX handler for calculating extras price
     * 
     * @return void
     */
    public function ajax_calculate_extras_price(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcefp_extras_nonce')) {
            wp_die(__('Security check failed', 'wceventsfp'));
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        $extras_selection = $_POST['extras'] ?? [];
        
        $extras_config = get_post_meta($product_id, '_wcefp_extras', true);
        $total = 0;
        
        foreach ($extras_selection as $index => $quantity) {
            $quantity = absint($quantity);
            
            if ($quantity > 0 && isset($extras_config[$index])) {
                $total += $extras_config[$index]['price'] * $quantity;
            }
        }
        
        wp_send_json_success([
            'total' => $total,
            'formatted_total' => wc_price($total)
        ]);
    }
    
    /**
     * Enqueue frontend scripts
     * 
     * @return void
     */
    public function enqueue_frontend_scripts(): void {
        if (!is_product()) {
            return;
        }
        
        wp_enqueue_script(
            'wcefp-extras',
            plugin_dir_url(__FILE__) . '../../assets/js/extras-frontend.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('wcefp-extras', 'wcefp_extras', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_extras_nonce'),
            'currency_symbol' => get_woocommerce_currency_symbol()
        ]);
    }
}