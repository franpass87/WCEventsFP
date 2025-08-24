<?php
/**
 * Frontend Booking Widget
 * 
 * Handles the customer-facing booking interface via shortcode and Gutenberg block
 * 
 * @package WCEFP
 * @subpackage Frontend
 * @since 2.2.0
 */

namespace WCEFP\Frontend;

use WCEFP\Core\Container;
use WCEFP\Core\SecurityManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend Booking Widget Class
 */
class BookingWidget {
    
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
        $this->init();
    }
    
    /**
     * Initialize widget
     * 
     * @return void
     */
    private function init() {
        // Register shortcode
        add_shortcode('wcefp_booking', [$this, 'render_booking_widget']);
        
        // Register Gutenberg block
        add_action('init', [$this, 'register_gutenberg_block']);
        
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_wcefp_get_occurrences', [$this, 'ajax_get_occurrences']);
        add_action('wp_ajax_nopriv_wcefp_get_occurrences', [$this, 'ajax_get_occurrences']);
        
        add_action('wp_ajax_wcefp_calculate_booking_price', [$this, 'ajax_calculate_price']);
        add_action('wp_ajax_nopriv_wcefp_calculate_booking_price', [$this, 'ajax_calculate_price']);
        
        add_action('wp_ajax_wcefp_add_booking_to_cart', [$this, 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_wcefp_add_booking_to_cart', [$this, 'ajax_add_to_cart']);
    }
    
    /**
     * Enqueue frontend assets conditionally
     * 
     * @return void
     */
    public function enqueue_assets() {
        global $post;
        
        $enqueue = false;
        
        // Check if shortcode is used in content
        if ($post && has_shortcode($post->post_content, 'wcefp_booking')) {
            $enqueue = true;
        }
        
        // Check if Gutenberg block is present
        if ($post && has_block('wcefp/booking-widget', $post)) {
            $enqueue = true;
        }
        
        // Check if we're on a product page with event/experience
        if (is_product()) {
            $product = wc_get_product(get_the_ID());
            if ($product && in_array($product->get_type(), ['evento', 'esperienza'])) {
                $enqueue = true;
            }
        }
        
        if (!$enqueue) {
            return;
        }
        
        // Enqueue booking widget styles
        wp_enqueue_style(
            'wcefp-booking-widget',
            WCEFP_PLUGIN_URL . 'assets/frontend/css/booking-widget.css',
            [],
            WCEFP_VERSION
        );
        
        // Enqueue booking widget script
        wp_enqueue_script(
            'wcefp-booking-widget',
            WCEFP_PLUGIN_URL . 'assets/frontend/js/booking-widget.js',
            ['jquery', 'wp-api-fetch'],
            WCEFP_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wcefp-booking-widget', 'wcefp_booking', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('wcefp/v1/'),
            'nonce' => wp_create_nonce('wcefp_booking_nonce'),
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'i18n' => [
                'loading' => __('Caricamento...', 'wceventsfp'),
                'select_date' => __('Seleziona una data', 'wceventsfp'),
                'select_time' => __('Seleziona un orario', 'wceventsfp'),
                'select_tickets' => __('Seleziona i biglietti', 'wceventsfp'),
                'no_availability' => __('Nessuna disponibilità per la data selezionata', 'wceventsfp'),
                'add_to_cart' => __('Aggiungi al Carrello', 'wceventsfp'),
                'calculating' => __('Calcolo in corso...', 'wceventsfp'),
                'total' => __('Totale', 'wceventsfp'),
                'quantity' => __('Quantità', 'wceventsfp'),
                'price' => __('Prezzo', 'wceventsfp'),
                'required_field' => __('Campo obbligatorio', 'wceventsfp'),
                'min_participants' => __('Numero minimo di partecipanti richiesto', 'wceventsfp'),
                'max_capacity' => __('Capacità massima superata', 'wceventsfp'),
                'booking_success' => __('Prenotazione aggiunta al carrello!', 'wceventsfp'),
                'booking_error' => __('Errore durante la prenotazione', 'wceventsfp')
            ]
        ]);
    }
    
    /**
     * Register Gutenberg block
     * 
     * @return void
     */
    public function register_gutenberg_block() {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        register_block_type('wcefp/booking-widget', [
            'attributes' => [
                'productId' => [
                    'type' => 'number',
                    'default' => 0
                ],
                'layout' => [
                    'type' => 'string',
                    'default' => 'default'
                ],
                'showExtras' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showMeetingPoint' => [
                    'type' => 'boolean', 
                    'default' => true
                ]
            ],
            'render_callback' => [$this, 'render_gutenberg_block']
        ]);
    }
    
    /**
     * Render shortcode
     * 
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string HTML output
     */
    public function render_booking_widget($atts = [], $content = '') {
        $atts = shortcode_atts([
            'product_id' => get_the_ID(),
            'layout' => 'default',
            'show_extras' => 'yes',
            'show_meeting_point' => 'yes',
            'class' => ''
        ], $atts, 'wcefp_booking');
        
        $product_id = (int) $atts['product_id'];
        
        if (!$product_id) {
            return '<div class="wcefp-error">' . __('ID prodotto mancante', 'wceventsfp') . '</div>';
        }
        
        return $this->render_widget([
            'product_id' => $product_id,
            'layout' => $atts['layout'],
            'show_extras' => $atts['show_extras'] === 'yes',
            'show_meeting_point' => $atts['show_meeting_point'] === 'yes',
            'class' => $atts['class']
        ]);
    }
    
    /**
     * Render Gutenberg block
     * 
     * @param array $attributes Block attributes
     * @return string HTML output
     */
    public function render_gutenberg_block($attributes) {
        $product_id = (int) ($attributes['productId'] ?? get_the_ID());
        
        if (!$product_id) {
            return '<div class="wcefp-error">' . __('ID prodotto mancante', 'wceventsfp') . '</div>';
        }
        
        return $this->render_widget([
            'product_id' => $product_id,
            'layout' => $attributes['layout'] ?? 'default',
            'show_extras' => $attributes['showExtras'] ?? true,
            'show_meeting_point' => $attributes['showMeetingPoint'] ?? true,
            'class' => 'wp-block-wcefp-booking-widget'
        ]);
    }
    
    /**
     * Render the booking widget
     * 
     * @param array $config Widget configuration
     * @return string HTML output
     */
    private function render_widget($config) {
        $product_id = $config['product_id'];
        
        // Verify product exists and is valid
        $product = wc_get_product($product_id);
        if (!$product || !in_array($product->get_type(), ['evento', 'esperienza'])) {
            return '<div class="wcefp-error">' . __('Prodotto non trovato o non valido', 'wceventsfp') . '</div>';
        }
        
        // Start output buffering
        ob_start();
        
        $widget_class = 'wcefp-booking-widget';
        $widget_class .= ' wcefp-layout-' . sanitize_html_class($config['layout']);
        $widget_class .= ' ' . sanitize_html_class($config['class']);
        
        ?>
        <div class="<?php echo esc_attr($widget_class); ?>" data-product-id="<?php echo esc_attr($product_id); ?>">
            <div class="wcefp-booking-form">
                
                <!-- Date Selection -->
                <div class="wcefp-section wcefp-date-selection">
                    <h3><?php _e('📅 Seleziona Data e Orario', 'wceventsfp'); ?></h3>
                    
                    <div class="wcefp-date-picker">
                        <input type="date" 
                               id="wcefp-date-input-<?php echo $product_id; ?>" 
                               class="wcefp-date-input" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+1 year')); ?>"
                               aria-label="<?php _e('Seleziona data', 'wceventsfp'); ?>">
                    </div>
                    
                    <div class="wcefp-time-slots" id="wcefp-time-slots-<?php echo $product_id; ?>">
                        <p class="wcefp-placeholder"><?php _e('Seleziona una data per vedere gli orari disponibili', 'wceventsfp'); ?></p>
                    </div>
                </div>
                
                <!-- Ticket Selection -->
                <div class="wcefp-section wcefp-ticket-selection">
                    <h3><?php _e('🎫 Seleziona Biglietti', 'wceventsfp'); ?></h3>
                    
                    <div class="wcefp-tickets" id="wcefp-tickets-<?php echo $product_id; ?>">
                        <?php echo $this->render_ticket_types($product_id); ?>
                    </div>
                </div>
                
                <!-- Extras Selection -->
                <?php if ($config['show_extras']): ?>
                <div class="wcefp-section wcefp-extras-selection">
                    <h3><?php _e('🎁 Servizi Extra', 'wceventsfp'); ?></h3>
                    
                    <div class="wcefp-extras" id="wcefp-extras-<?php echo $product_id; ?>">
                        <?php echo $this->render_extras($product_id); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Meeting Point -->
                <?php if ($config['show_meeting_point']): ?>
                <div class="wcefp-section wcefp-meeting-point">
                    <h3><?php _e('📍 Punto di Ritrovo', 'wceventsfp'); ?></h3>
                    
                    <div class="wcefp-meeting-point-info" id="wcefp-meeting-point-<?php echo $product_id; ?>">
                        <?php echo $this->render_meeting_point($product_id); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Booking Summary -->
                <div class="wcefp-section wcefp-booking-summary">
                    <h3><?php _e('📋 Riepilogo Prenotazione', 'wceventsfp'); ?></h3>
                    
                    <div class="wcefp-summary" id="wcefp-summary-<?php echo $product_id; ?>">
                        <div class="wcefp-summary-empty">
                            <p><?php _e('Seleziona data, orario e biglietti per vedere il riepilogo', 'wceventsfp'); ?></p>
                        </div>
                    </div>
                    
                    <div class="wcefp-total">
                        <div class="wcefp-total-amount">
                            <span class="wcefp-total-label"><?php _e('Totale:', 'wceventsfp'); ?></span>
                            <span class="wcefp-total-value" id="wcefp-total-<?php echo $product_id; ?>">
                                <?php echo wc_price(0); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="wcefp-section wcefp-booking-actions">
                    <button type="button" 
                            class="wcefp-add-to-cart-btn btn button" 
                            id="wcefp-add-to-cart-<?php echo $product_id; ?>"
                            disabled>
                        <span class="wcefp-btn-text"><?php _e('Aggiungi al Carrello', 'wceventsfp'); ?></span>
                        <span class="wcefp-btn-loading" style="display: none;"><?php _e('Aggiunta in corso...', 'wceventsfp'); ?></span>
                    </button>
                </div>
                
                <!-- Loading State -->
                <div class="wcefp-loading" id="wcefp-loading-<?php echo $product_id; ?>" style="display: none;">
                    <div class="wcefp-spinner"></div>
                    <p><?php _e('Caricamento...', 'wceventsfp'); ?></p>
                </div>
                
                <!-- Error Messages -->
                <div class="wcefp-messages" id="wcefp-messages-<?php echo $product_id; ?>"></div>
                
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render ticket types for product
     * 
     * @param int $product_id Product ID
     * @return string HTML output
     */
    private function render_ticket_types($product_id) {
        $product = wc_get_product($product_id);
        
        // Get ticket types from meta or default
        $ticket_types = get_post_meta($product_id, '_wcefp_ticket_types', true) ?: [];
        
        if (empty($ticket_types)) {
            $ticket_types = [
                [
                    'ticket_key' => 'adult',
                    'label' => __('Adulto', 'wceventsfp'),
                    'price' => (float) $product->get_regular_price(),
                    'min_quantity' => 0,
                    'max_quantity' => 10
                ]
            ];
            
            $child_price = get_post_meta($product_id, '_wcefp_child_price', true);
            if ($child_price) {
                $ticket_types[] = [
                    'ticket_key' => 'child',
                    'label' => __('Bambino (0-12 anni)', 'wceventsfp'),
                    'price' => (float) $child_price,
                    'min_quantity' => 0,
                    'max_quantity' => 10
                ];
            }
        }
        
        ob_start();
        
        foreach ($ticket_types as $ticket) {
            if (empty($ticket['is_active']) && isset($ticket['is_active'])) {
                continue;
            }
            
            $ticket_key = $ticket['ticket_key'];
            $label = $ticket['label'];
            $price = (float) $ticket['price'];
            $min_qty = (int) ($ticket['min_quantity'] ?? 0);
            $max_qty = (int) ($ticket['max_quantity'] ?? 10);
            ?>
            <div class="wcefp-ticket-row" data-ticket-key="<?php echo esc_attr($ticket_key); ?>">
                <div class="wcefp-ticket-info">
                    <div class="wcefp-ticket-label">
                        <strong><?php echo esc_html($label); ?></strong>
                        <?php if (!empty($ticket['description'])): ?>
                        <p class="wcefp-ticket-description"><?php echo esc_html($ticket['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="wcefp-ticket-price">
                        <?php echo wc_price($price); ?>
                    </div>
                </div>
                
                <div class="wcefp-ticket-quantity">
                    <label for="wcefp-ticket-<?php echo esc_attr($ticket_key); ?>-<?php echo $product_id; ?>" class="screen-reader-text">
                        <?php printf(__('Quantità %s', 'wceventsfp'), $label); ?>
                    </label>
                    <div class="wcefp-quantity-controls">
                        <button type="button" class="wcefp-qty-minus" aria-label="<?php _e('Riduci quantità', 'wceventsfp'); ?>">-</button>
                        <input type="number" 
                               id="wcefp-ticket-<?php echo esc_attr($ticket_key); ?>-<?php echo $product_id; ?>"
                               class="wcefp-ticket-qty" 
                               name="tickets[<?php echo esc_attr($ticket_key); ?>]"
                               value="0" 
                               min="<?php echo $min_qty; ?>" 
                               max="<?php echo $max_qty; ?>"
                               data-price="<?php echo $price; ?>">
                        <button type="button" class="wcefp-qty-plus" aria-label="<?php _e('Aumenta quantità', 'wceventsfp'); ?>">+</button>
                    </div>
                </div>
            </div>
            <?php
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render extras for product
     * 
     * @param int $product_id Product ID
     * @return string HTML output
     */
    private function render_extras($product_id) {
        $extras = get_post_meta($product_id, '_wcefp_extras', true) ?: [];
        
        if (empty($extras)) {
            return '<p class="wcefp-no-extras">' . __('Nessun servizio extra disponibile', 'wceventsfp') . '</p>';
        }
        
        ob_start();
        
        foreach ($extras as $extra) {
            if (empty($extra['is_active']) && isset($extra['is_active'])) {
                continue;
            }
            
            $extra_key = $extra['extra_key'];
            $label = $extra['label'];
            $price = (float) $extra['price'];
            $pricing_type = $extra['pricing_type'] ?? 'fixed';
            $is_required = !empty($extra['is_required']);
            $max_qty = (int) ($extra['max_quantity'] ?? 10);
            ?>
            <div class="wcefp-extra-row" data-extra-key="<?php echo esc_attr($extra_key); ?>">
                <div class="wcefp-extra-info">
                    <div class="wcefp-extra-label">
                        <strong><?php echo esc_html($label); ?></strong>
                        <?php if ($is_required): ?>
                        <span class="wcefp-required"><?php _e('(Obbligatorio)', 'wceventsfp'); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($extra['description'])): ?>
                        <p class="wcefp-extra-description"><?php echo esc_html($extra['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="wcefp-extra-price">
                        <?php echo wc_price($price); ?>
                        <?php if ($pricing_type === 'per_person'): ?>
                        <small><?php _e('per persona', 'wceventsfp'); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="wcefp-extra-quantity">
                    <label for="wcefp-extra-<?php echo esc_attr($extra_key); ?>-<?php echo $product_id; ?>" class="screen-reader-text">
                        <?php printf(__('Quantità %s', 'wceventsfp'), $label); ?>
                    </label>
                    <div class="wcefp-quantity-controls">
                        <button type="button" class="wcefp-qty-minus" <?php echo $is_required ? 'disabled' : ''; ?> aria-label="<?php _e('Riduci quantità', 'wceventsfp'); ?>">-</button>
                        <input type="number" 
                               id="wcefp-extra-<?php echo esc_attr($extra_key); ?>-<?php echo $product_id; ?>"
                               class="wcefp-extra-qty" 
                               name="extras[<?php echo esc_attr($extra_key); ?>]"
                               value="<?php echo $is_required ? '1' : '0'; ?>" 
                               min="<?php echo $is_required ? '1' : '0'; ?>" 
                               max="<?php echo $max_qty; ?>"
                               data-price="<?php echo $price; ?>"
                               data-pricing-type="<?php echo esc_attr($pricing_type); ?>"
                               <?php echo $is_required ? 'required' : ''; ?>>
                        <button type="button" class="wcefp-qty-plus" aria-label="<?php _e('Aumenta quantità', 'wceventsfp'); ?>">+</button>
                    </div>
                </div>
            </div>
            <?php
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render meeting point information
     * 
     * @param int $product_id Product ID
     * @return string HTML output
     */
    private function render_meeting_point($product_id) {
        $meeting_point_id = get_post_meta($product_id, '_wcefp_meeting_point_id', true);
        
        if (!$meeting_point_id) {
            return '<p class="wcefp-no-meeting-point">' . __('Punto di ritrovo da definire', 'wceventsfp') . '</p>';
        }
        
        $meeting_point = \WCEFP\Admin\MeetingPointsManager::get_meeting_point_data($meeting_point_id);
        
        if (!$meeting_point) {
            return '<p class="wcefp-no-meeting-point">' . __('Informazioni punto di ritrovo non disponibili', 'wceventsfp') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="wcefp-meeting-point-details">
            <h4><?php echo esc_html($meeting_point['title']); ?></h4>
            
            <?php if ($meeting_point['address']): ?>
            <div class="wcefp-address">
                <strong><?php _e('Indirizzo:', 'wceventsfp'); ?></strong>
                <span><?php echo esc_html($meeting_point['address']); ?></span>
                <?php if ($meeting_point['city']): ?>
                <span>, <?php echo esc_html($meeting_point['city']); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($meeting_point['instructions']): ?>
            <div class="wcefp-instructions">
                <strong><?php _e('Istruzioni:', 'wceventsfp'); ?></strong>
                <p><?php echo wp_kses_post($meeting_point['instructions']); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="wcefp-accessibility-info">
                <?php if ($meeting_point['wheelchair_accessible']): ?>
                <span class="wcefp-accessible">♿ <?php _e('Accessibile', 'wceventsfp'); ?></span>
                <?php endif; ?>
                <?php if ($meeting_point['public_transport']): ?>
                <span class="wcefp-transport">🚌 <?php _e('Mezzi Pubblici', 'wceventsfp'); ?></span>
                <?php endif; ?>
                <?php if ($meeting_point['parking_available']): ?>
                <span class="wcefp-parking">🅿️ <?php _e('Parcheggio', 'wceventsfp'); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if ($meeting_point['latitude'] && $meeting_point['longitude']): ?>
            <div class="wcefp-map-link">
                <a href="https://www.google.com/maps?q=<?php echo esc_attr($meeting_point['latitude']); ?>,<?php echo esc_attr($meeting_point['longitude']); ?>" 
                   target="_blank" class="wcefp-map-button">
                    🗺️ <?php _e('Visualizza su Mappa', 'wceventsfp'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * AJAX: Get occurrences for date
     * 
     * @return void
     */
    public function ajax_get_occurrences() {
        check_ajax_referer('wcefp_booking_nonce', 'nonce');
        
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? '');
        
        if (!$product_id || !$date) {
            wp_send_json_error(__('Parametri mancanti', 'wceventsfp'));
        }
        
        $scheduling_service = $this->container->get('scheduling_service');
        $occurrences = $scheduling_service->get_available_slots($product_id, $date);
        
        wp_send_json_success([
            'occurrences' => $occurrences,
            'date' => $date
        ]);
    }
    
    /**
     * AJAX: Calculate booking price
     * 
     * @return void
     */
    public function ajax_calculate_price() {
        check_ajax_referer('wcefp_booking_nonce', 'nonce');
        
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $tickets = $_POST['tickets'] ?? [];
        $extras = $_POST['extras'] ?? [];
        $date = sanitize_text_field($_POST['date'] ?? '');
        
        if (!$product_id) {
            wp_send_json_error(__('ID prodotto mancante', 'wceventsfp'));
        }
        
        // Use REST API endpoint for calculation
        $request = new \WP_REST_Request('POST', '/wcefp/v1/calculate-price');
        $request->set_param('product_id', $product_id);
        $request->set_param('tickets', $tickets);
        $request->set_param('extras', $extras);
        $request->set_param('date', $date);
        
        $response = rest_do_request($request);
        
        if ($response->is_error()) {
            wp_send_json_error($response->as_error()->get_error_message());
        }
        
        wp_send_json_success($response->get_data());
    }
    
    /**
     * AJAX: Add booking to cart
     * 
     * @return void
     */
    public function ajax_add_to_cart() {
        check_ajax_referer('wcefp_booking_nonce', 'nonce');
        
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $occurrence_id = $_POST['occurrence_id'] ? (int) $_POST['occurrence_id'] : null;
        $tickets = $_POST['tickets'] ?? [];
        $extras = $_POST['extras'] ?? [];
        
        if (!$product_id) {
            wp_send_json_error(__('ID prodotto mancante', 'wceventsfp'));
        }
        
        // Use REST API endpoint for cart addition
        $request = new \WP_REST_Request('POST', '/wcefp/v1/cart/add');
        $request->set_param('product_id', $product_id);
        $request->set_param('occurrence_id', $occurrence_id);
        $request->set_param('tickets', $tickets);
        $request->set_param('extras', $extras);
        
        $response = rest_do_request($request);
        
        if ($response->is_error()) {
            wp_send_json_error($response->as_error()->get_error_message());
        }
        
        wp_send_json_success($response->get_data());
    }
}