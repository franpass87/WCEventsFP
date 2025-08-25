<?php
/**
 * Frontend Booking Widget v2
 * 
 * Enhanced customer-facing booking interface with GYG/Regiondo-style UX
 * Includes trust elements, social proof, and ethical nudges
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
 * Frontend Booking Widget v2 Class
 * 
 * Enhanced booking widget with modern UX patterns and trust elements
 */
class BookingWidgetV2 {
    
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
     * Initialize widget v2
     * 
     * @return void
     */
    private function init() {
        // Register shortcode
        add_shortcode('wcefp_booking_widget_v2', [$this, 'render_booking_widget_v2']);
        
        // Register Gutenberg block
        add_action('init', [$this, 'register_gutenberg_block_v2']);
        
        // Enqueue assets conditionally
        add_action('wp_enqueue_scripts', [$this, 'enqueue_v2_assets']);
        
        // Add AJAX handlers for enhanced functionality
        add_action('wp_ajax_wcefp_get_availability_v2', [$this, 'ajax_get_availability']);
        add_action('wp_ajax_nopriv_wcefp_get_availability_v2', [$this, 'ajax_get_availability']);
        
        add_action('wp_ajax_wcefp_add_to_cart_v2', [$this, 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_wcefp_add_to_cart_v2', [$this, 'ajax_add_to_cart']);
    }
    
    /**
     * Conditionally enqueue v2 assets
     * 
     * @return void
     */
    public function enqueue_v2_assets() {
        global $post;
        
        $enqueue = false;
        
        // Check if v2 shortcode is used in content
        if ($post && has_shortcode($post->post_content, 'wcefp_booking_widget_v2')) {
            $enqueue = true;
        }
        
        // Check if v2 Gutenberg block is present
        if ($post && has_block('wcefp/booking-widget-v2', $post)) {
            $enqueue = true;
        }
        
        // Check if we're on a product page with event/experience type
        if (is_product()) {
            $product = wc_get_product(get_the_ID());
            if ($product && in_array($product->get_type(), ['evento', 'esperienza'])) {
                $enqueue = true;
            }
        }
        
        if (!$enqueue) {
            return;
        }
        
        // Enqueue v2 booking widget styles
        wp_enqueue_style(
            'wcefp-booking-widget-v2',
            WCEFP_PLUGIN_URL . 'assets/frontend/css/booking-widget-v2.css',
            [],
            WCEFP_VERSION
        );
        
        // Enqueue v2 booking widget script
        wp_enqueue_script(
            'wcefp-booking-widget-v2',
            WCEFP_PLUGIN_URL . 'assets/frontend/js/booking-widget-v2.js',
            ['jquery', 'wp-i18n'],
            WCEFP_VERSION,
            true
        );
        
        // Localize script with enhanced data
        wp_localize_script('wcefp-booking-widget-v2', 'wcefp_booking_v2', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_booking_v2'),
            'currency_symbol' => html_entity_decode(get_woocommerce_currency_symbol()),
            'currency_code' => get_option('woocommerce_currency'),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'trust_settings' => $this->get_trust_settings(),
            'strings' => [
                'loading' => __('Loading...', 'wceventsfp'),
                'select_date' => __('Select Date', 'wceventsfp'),
                'select_time' => __('Select Time', 'wceventsfp'),
                'select_participants' => __('Select Participants', 'wceventsfp'),
                'booking_confirmation' => __('Booking Confirmation', 'wceventsfp'),
                'instant_confirmation' => __('Instant Confirmation', 'wceventsfp'),
                'free_cancellation' => __('Free Cancellation', 'wceventsfp'),
                'secure_payment' => __('Secure Payment', 'wceventsfp'),
                'best_seller' => __('Best Seller', 'wceventsfp'),
                'almost_sold_out' => __('Almost Sold Out', 'wceventsfp'),
                'last_booking' => __('Last booking %s ago', 'wceventsfp'),
                'people_viewing' => __('%d people are viewing this experience', 'wceventsfp'),
                'book_now' => __('Book Now', 'wceventsfp'),
                'add_to_cart' => __('Add to Cart', 'wceventsfp'),
                'booking_error' => __('Booking error occurred', 'wceventsfp'),
                'sold_out' => __('Sold Out', 'wceventsfp'),
                'available_spots' => __('%d spots available', 'wceventsfp')
            ]
        ]);
    }
    
    /**
     * Register Gutenberg block v2
     * 
     * @return void
     */
    public function register_gutenberg_block_v2() {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        register_block_type('wcefp/booking-widget-v2', [
            'attributes' => [
                'productId' => [
                    'type' => 'number',
                    'default' => 0
                ],
                'layout' => [
                    'type' => 'string',
                    'default' => 'gyg-style'
                ],
                'showHero' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showTrustBadges' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showSocialProof' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showExtras' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'showMeetingPoint' => [
                    'type' => 'boolean', 
                    'default' => true
                ],
                'showGoogleReviews' => [
                    'type' => 'boolean',
                    'default' => true
                ],
                'trustNudgesLevel' => [
                    'type' => 'string',
                    'default' => 'moderate'  // none, minimal, moderate, high
                ],
                'colorScheme' => [
                    'type' => 'string',
                    'default' => 'default'
                ]
            ],
            'render_callback' => [$this, 'render_gutenberg_block_v2']
        ]);
    }
    
    /**
     * Render v2 shortcode
     * 
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string
     */
    public function render_booking_widget_v2($atts, $content = '') {
        $atts = shortcode_atts([
            'id' => 0,
            'layout' => 'gyg-style',
            'show_hero' => 'yes',
            'show_trust_badges' => 'yes',
            'show_social_proof' => 'yes',
            'show_extras' => 'yes',
            'show_meeting_point' => 'yes',
            'show_google_reviews' => 'yes',
            'trust_nudges' => 'moderate',
            'color_scheme' => 'default',
            'class' => ''
        ], $atts, 'wcefp_booking_widget_v2');
        
        // Convert to boolean for consistency with block attributes
        $normalized_atts = [
            'productId' => absint($atts['id']),
            'layout' => sanitize_text_field($atts['layout']),
            'showHero' => $atts['show_hero'] === 'yes',
            'showTrustBadges' => $atts['show_trust_badges'] === 'yes',
            'showSocialProof' => $atts['show_social_proof'] === 'yes',
            'showExtras' => $atts['show_extras'] === 'yes',
            'showMeetingPoint' => $atts['show_meeting_point'] === 'yes',
            'showGoogleReviews' => $atts['show_google_reviews'] === 'yes',
            'trustNudgesLevel' => sanitize_text_field($atts['trust_nudges']),
            'colorScheme' => sanitize_text_field($atts['color_scheme']),
            'className' => sanitize_text_field($atts['class'])
        ];
        
        return $this->render_widget_v2($normalized_atts);
    }
    
    /**
     * Render Gutenberg block v2
     * 
     * @param array $attributes Block attributes
     * @return string
     */
    public function render_gutenberg_block_v2($attributes) {
        // Set default attributes if not provided
        $attributes = wp_parse_args($attributes, [
            'productId' => 0,
            'layout' => 'gyg-style',
            'showHero' => true,
            'showTrustBadges' => true,
            'showSocialProof' => true,
            'showExtras' => true,
            'showMeetingPoint' => true,
            'showGoogleReviews' => true,
            'trustNudgesLevel' => 'moderate',
            'colorScheme' => 'default',
            'className' => ''
        ]);
        
        return $this->render_widget_v2($attributes);
    }
    
    /**
     * Core widget v2 rendering logic
     * 
     * @param array $attributes Widget attributes
     * @return string
     */
    private function render_widget_v2($attributes) {
        $product_id = $attributes['productId'];
        
        if (!$product_id) {
            if (current_user_can('edit_posts')) {
                return '<div class="wcefp-error wcefp-widget-v2-error">' . 
                       esc_html__('Please specify a product ID for the booking widget.', 'wceventsfp') . 
                       '</div>';
            }
            return '';
        }
        
        $product = wc_get_product($product_id);
        if (!$product || !in_array($product->get_type(), ['evento', 'esperienza'])) {
            if (current_user_can('edit_posts')) {
                return '<div class="wcefp-error wcefp-widget-v2-error">' . 
                       esc_html__('Invalid product or product is not an event/experience.', 'wceventsfp') . 
                       '</div>';
            }
            return '';
        }
        
        // Start output buffering
        ob_start();
        
        $widget_classes = [
            'wcefp-booking-widget-v2',
            'wcefp-layout-' . $attributes['layout'],
            'wcefp-color-scheme-' . $attributes['colorScheme'],
            'wcefp-nudges-' . $attributes['trustNudgesLevel']
        ];
        
        if (!empty($attributes['className'])) {
            $widget_classes[] = $attributes['className'];
        }
        
        ?>
        <div class="<?php echo esc_attr(implode(' ', $widget_classes)); ?>" 
             data-product-id="<?php echo esc_attr($product_id); ?>"
             data-attributes="<?php echo esc_attr(wp_json_encode($attributes)); ?>">
             
            <?php if ($attributes['showHero']): ?>
                <?php $this->render_hero_section($product, $attributes); ?>
            <?php endif; ?>
            
            <div class="wcefp-booking-form-wrapper">
                <?php $this->render_booking_form_v2($product, $attributes); ?>
            </div>
            
            <?php if ($attributes['showTrustBadges']): ?>
                <?php $this->render_trust_badges($attributes); ?>
            <?php endif; ?>
            
            <?php if ($attributes['showSocialProof']): ?>
                <?php $this->render_social_proof($product, $attributes); ?>
            <?php endif; ?>
            
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render hero section
     * 
     * @param \WC_Product $product Product object
     * @param array $attributes Widget attributes
     */
    private function render_hero_section($product, $attributes) {
        $featured_image = get_the_post_thumbnail_url($product->get_id(), 'large');
        $gallery_images = $this->get_product_gallery($product);
        
        ?>
        <div class="wcefp-hero-section">
            <div class="wcefp-hero-images">
                <?php if ($featured_image): ?>
                    <div class="wcefp-hero-main-image">
                        <img src="<?php echo esc_url($featured_image); ?>" 
                             alt="<?php echo esc_attr($product->get_name()); ?>"
                             loading="lazy" />
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($gallery_images)): ?>
                    <div class="wcefp-hero-gallery">
                        <?php foreach (array_slice($gallery_images, 0, 4) as $image): ?>
                            <div class="wcefp-gallery-thumb">
                                <img src="<?php echo esc_url($image['thumb']); ?>" 
                                     alt="<?php echo esc_attr($image['alt']); ?>"
                                     data-full="<?php echo esc_url($image['full']); ?>"
                                     loading="lazy" />
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="wcefp-hero-content">
                <h1 class="wcefp-hero-title"><?php echo esc_html($product->get_name()); ?></h1>
                
                <?php if ($product->get_short_description()): ?>
                    <div class="wcefp-hero-description">
                        <?php echo wp_kses_post($product->get_short_description()); ?>
                    </div>
                <?php endif; ?>
                
                <?php $this->render_quick_info($product); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render enhanced booking form
     * 
     * @param \WC_Product $product Product object
     * @param array $attributes Widget attributes
     */
    private function render_booking_form_v2($product, $attributes) {
        ?>
        <form class="wcefp-booking-form-v2" method="post">
            <?php wp_nonce_field('wcefp_booking_v2', 'wcefp_booking_nonce'); ?>
            <input type="hidden" name="product_id" value="<?php echo esc_attr($product->get_id()); ?>" />
            
            <div class="wcefp-form-sections">
                
                <!-- Date Selection -->
                <div class="wcefp-form-section wcefp-date-section">
                    <h3 class="wcefp-section-title">
                        <span class="wcefp-step-number">1</span>
                        <?php esc_html_e('Select Date', 'wceventsfp'); ?>
                    </h3>
                    
                    <div class="wcefp-date-picker-container">
                        <input type="date" 
                               name="booking_date" 
                               class="wcefp-date-input"
                               min="<?php echo esc_attr(date('Y-m-d')); ?>"
                               required />
                    </div>
                </div>
                
                <!-- Time Selection -->
                <div class="wcefp-form-section wcefp-time-section">
                    <h3 class="wcefp-section-title">
                        <span class="wcefp-step-number">2</span>
                        <?php esc_html_e('Select Time', 'wceventsfp'); ?>
                    </h3>
                    
                    <div class="wcefp-time-slots-container">
                        <div class="wcefp-loading-slots">
                            <?php esc_html_e('Select a date to see available times', 'wceventsfp'); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Participants Selection -->
                <div class="wcefp-form-section wcefp-participants-section">
                    <h3 class="wcefp-section-title">
                        <span class="wcefp-step-number">3</span>
                        <?php esc_html_e('Participants', 'wceventsfp'); ?>
                    </h3>
                    
                    <?php $this->render_participants_selector($product); ?>
                </div>
                
                <!-- Extras -->
                <?php if ($attributes['showExtras']): ?>
                    <div class="wcefp-form-section wcefp-extras-section">
                        <h3 class="wcefp-section-title">
                            <span class="wcefp-step-number">4</span>
                            <?php esc_html_e('Add-ons (Optional)', 'wceventsfp'); ?>
                        </h3>
                        
                        <?php $this->render_extras_selector($product); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Meeting Point -->
                <?php if ($attributes['showMeetingPoint']): ?>
                    <div class="wcefp-form-section wcefp-meeting-point-section">
                        <h3 class="wcefp-section-title">
                            <?php esc_html_e('Meeting Point', 'wceventsfp'); ?>
                        </h3>
                        
                        <?php $this->render_meeting_point($product, $attributes); ?>
                    </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Booking Summary and CTA -->
            <div class="wcefp-booking-summary">
                <?php $this->render_booking_summary($product, $attributes); ?>
            </div>
            
        </form>
        <?php
    }
    
    /**
     * Get trust settings from options
     * 
     * @return array
     */
    private function get_trust_settings() {
        return [
            'show_availability_counter' => get_option('wcefp_trust_availability_counter', true),
            'show_recent_bookings' => get_option('wcefp_trust_recent_bookings', true),
            'show_people_viewing' => get_option('wcefp_trust_people_viewing', false),
            'show_best_seller' => get_option('wcefp_trust_best_seller', true),
            'booking_threshold' => get_option('wcefp_trust_booking_threshold', 10),
            'viewing_range' => [
                get_option('wcefp_trust_viewing_min', 2),
                get_option('wcefp_trust_viewing_max', 8)
            ]
        ];
    }
    
    /**
     * Get product gallery images
     * 
     * @param \WC_Product $product Product object
     * @return array
     */
    private function get_product_gallery($product) {
        $gallery_images = [];
        $attachment_ids = $product->get_gallery_image_ids();
        
        foreach ($attachment_ids as $attachment_id) {
            $gallery_images[] = [
                'full' => wp_get_attachment_image_url($attachment_id, 'large'),
                'thumb' => wp_get_attachment_image_url($attachment_id, 'medium'),
                'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true)
            ];
        }
        
        return $gallery_images;
    }
    
    /**
     * Render quick info section
     * 
     * @param \WC_Product $product Product object
     */
    private function render_quick_info($product) {
        $duration = get_post_meta($product->get_id(), '_wcefp_duration', true);
        $location = get_post_meta($product->get_id(), '_wcefp_location', true);
        $max_participants = get_post_meta($product->get_id(), '_wcefp_max_participants', true);
        
        ?>
        <div class="wcefp-quick-info">
            <?php if ($duration): ?>
                <div class="wcefp-info-item">
                    <span class="wcefp-info-icon">🕒</span>
                    <span class="wcefp-info-text"><?php echo esc_html($duration); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($location): ?>
                <div class="wcefp-info-item">
                    <span class="wcefp-info-icon">📍</span>
                    <span class="wcefp-info-text"><?php echo esc_html($location); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($max_participants): ?>
                <div class="wcefp-info-item">
                    <span class="wcefp-info-icon">👥</span>
                    <span class="wcefp-info-text"><?php 
                        printf(esc_html__('Max %d participants', 'wceventsfp'), $max_participants); 
                    ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render participants selector
     * 
     * @param \WC_Product $product Product object
     */
    private function render_participants_selector($product) {
        $adult_price = get_post_meta($product->get_id(), '_wcefp_price_adult', true);
        $child_price = get_post_meta($product->get_id(), '_wcefp_price_child', true);
        $max_participants = get_post_meta($product->get_id(), '_wcefp_max_participants', true) ?: 10;
        
        ?>
        <div class="wcefp-participants-selector">
            <div class="wcefp-participant-type">
                <div class="wcefp-participant-info">
                    <span class="wcefp-participant-label"><?php esc_html_e('Adults', 'wceventsfp'); ?></span>
                    <?php if ($adult_price): ?>
                        <span class="wcefp-participant-price"><?php echo wc_price($adult_price); ?></span>
                    <?php endif; ?>
                </div>
                <div class="wcefp-quantity-selector">
                    <button type="button" class="wcefp-qty-btn wcefp-qty-minus" data-target="adults">−</button>
                    <input type="number" name="adult_qty" value="1" min="0" max="<?php echo esc_attr($max_participants); ?>" class="wcefp-qty-input" />
                    <button type="button" class="wcefp-qty-btn wcefp-qty-plus" data-target="adults">+</button>
                </div>
            </div>
            
            <?php if ($child_price): ?>
                <div class="wcefp-participant-type">
                    <div class="wcefp-participant-info">
                        <span class="wcefp-participant-label"><?php esc_html_e('Children', 'wceventsfp'); ?></span>
                        <span class="wcefp-participant-price"><?php echo wc_price($child_price); ?></span>
                    </div>
                    <div class="wcefp-quantity-selector">
                        <button type="button" class="wcefp-qty-btn wcefp-qty-minus" data-target="children">−</button>
                        <input type="number" name="child_qty" value="0" min="0" max="<?php echo esc_attr($max_participants); ?>" class="wcefp-qty-input" />
                        <button type="button" class="wcefp-qty-btn wcefp-qty-plus" data-target="children">+</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render extras selector
     * 
     * @param \WC_Product $product Product object
     */
    private function render_extras_selector($product) {
        // Get extras from product meta or related extras CPT
        $extras = get_post_meta($product->get_id(), '_wcefp_extras', true);
        
        if (empty($extras)) {
            return;
        }
        
        ?>
        <div class="wcefp-extras-selector">
            <?php foreach ($extras as $extra): ?>
                <div class="wcefp-extra-item">
                    <label class="wcefp-extra-label">
                        <input type="checkbox" name="extras[]" value="<?php echo esc_attr($extra['id']); ?>" />
                        <span class="wcefp-extra-checkmark"></span>
                        <span class="wcefp-extra-name"><?php echo esc_html($extra['name']); ?></span>
                        <span class="wcefp-extra-price"><?php echo wc_price($extra['price']); ?></span>
                    </label>
                    <?php if (!empty($extra['description'])): ?>
                        <div class="wcefp-extra-description"><?php echo esc_html($extra['description']); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render meeting point with Google Reviews if enabled
     * 
     * @param \WC_Product $product Product object
     * @param array $attributes Widget attributes
     */
    private function render_meeting_point($product, $attributes) {
        $meeting_point = get_post_meta($product->get_id(), '_wcefp_meeting_point', true);
        
        if (!$meeting_point) {
            return;
        }
        
        ?>
        <div class="wcefp-meeting-point">
            <div class="wcefp-meeting-point-info">
                <h4><?php echo esc_html($meeting_point['name'] ?? __('Meeting Point', 'wceventsfp')); ?></h4>
                <?php if (!empty($meeting_point['address'])): ?>
                    <p class="wcefp-meeting-address"><?php echo esc_html($meeting_point['address']); ?></p>
                <?php endif; ?>
                <?php if (!empty($meeting_point['instructions'])): ?>
                    <p class="wcefp-meeting-instructions"><?php echo esc_html($meeting_point['instructions']); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($attributes['showGoogleReviews'] && !empty($meeting_point['place_id'])): ?>
                <div class="wcefp-meeting-reviews">
                    <?php echo do_shortcode('[wcefp_google_reviews place_id="' . esc_attr($meeting_point['place_id']) . '" limit="3" style="compact"]'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render trust badges
     * 
     * @param array $attributes Widget attributes
     */
    private function render_trust_badges($attributes) {
        $badges = [
            'instant_confirmation' => [
                'icon' => '⚡',
                'text' => __('Instant Confirmation', 'wceventsfp'),
                'enabled' => get_option('wcefp_trust_instant_confirmation', true)
            ],
            'free_cancellation' => [
                'icon' => '🔄',
                'text' => __('Free Cancellation', 'wceventsfp'),
                'enabled' => get_option('wcefp_trust_free_cancellation', true)
            ],
            'secure_payment' => [
                'icon' => '🔒',
                'text' => __('Secure Payment', 'wceventsfp'),
                'enabled' => get_option('wcefp_trust_secure_payment', true)
            ],
            'mobile_ticket' => [
                'icon' => '📱',
                'text' => __('Mobile Ticket', 'wceventsfp'),
                'enabled' => get_option('wcefp_trust_mobile_ticket', true)
            ]
        ];
        
        $enabled_badges = array_filter($badges, function($badge) {
            return $badge['enabled'];
        });
        
        if (empty($enabled_badges)) {
            return;
        }
        
        ?>
        <div class="wcefp-trust-badges">
            <?php foreach ($enabled_badges as $badge): ?>
                <div class="wcefp-trust-badge">
                    <span class="wcefp-badge-icon"><?php echo esc_html($badge['icon']); ?></span>
                    <span class="wcefp-badge-text"><?php echo esc_html($badge['text']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render social proof elements
     * 
     * @param \WC_Product $product Product object
     * @param array $attributes Widget attributes
     */
    private function render_social_proof($product, $attributes) {
        $trust_settings = $this->get_trust_settings();
        
        ?>
        <div class="wcefp-social-proof">
            <?php if ($trust_settings['show_recent_bookings']): ?>
                <div class="wcefp-recent-bookings" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                    <!-- Populated via JavaScript to ensure real-time data -->
                </div>
            <?php endif; ?>
            
            <?php if ($trust_settings['show_people_viewing']): ?>
                <div class="wcefp-people-viewing">
                    <!-- Populated via JavaScript with ethical random numbers -->
                </div>
            <?php endif; ?>
            
            <?php if ($this->is_best_seller($product)): ?>
                <div class="wcefp-best-seller-badge">
                    <span class="wcefp-badge-icon">⭐</span>
                    <span class="wcefp-badge-text"><?php esc_html_e('Best Seller', 'wceventsfp'); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render booking summary
     * 
     * @param \WC_Product $product Product object
     * @param array $attributes Widget attributes
     */
    private function render_booking_summary($product, $attributes) {
        ?>
        <div class="wcefp-booking-summary-card">
            <div class="wcefp-summary-content">
                <div class="wcefp-price-display">
                    <span class="wcefp-from-price"><?php esc_html_e('From', 'wceventsfp'); ?></span>
                    <span class="wcefp-total-price"><?php echo wc_price($product->get_price()); ?></span>
                    <span class="wcefp-per-person"><?php esc_html_e('per person', 'wceventsfp'); ?></span>
                </div>
                
                <div class="wcefp-booking-details">
                    <div class="wcefp-selected-date"></div>
                    <div class="wcefp-selected-time"></div>
                    <div class="wcefp-selected-participants"></div>
                </div>
                
                <button type="submit" class="wcefp-book-now-btn wcefp-btn-primary">
                    <?php esc_html_e('Book Now', 'wceventsfp'); ?>
                </button>
            </div>
            
            <div class="wcefp-availability-indicator">
                <!-- Populated via JavaScript -->
            </div>
        </div>
        <?php
    }
    
    /**
     * Check if product is a best seller based on actual data
     * 
     * @param \WC_Product $product Product object
     * @return bool
     */
    private function is_best_seller($product) {
        // Get actual booking data from last 30 days
        global $wpdb;
        
        $bookings_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders wo
             JOIN {$wpdb->prefix}woocommerce_order_items woi ON wo.id = woi.order_id
             JOIN {$wpdb->prefix}woocommerce_order_itemmeta woim ON woi.order_item_id = woim.order_item_id
             WHERE woim.meta_key = '_product_id' 
             AND woim.meta_value = %d
             AND wo.status IN ('wc-processing', 'wc-completed')
             AND wo.date_created_gmt >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $product->get_id()
        ));
        
        $threshold = get_option('wcefp_best_seller_threshold', 10);
        return $bookings_count >= $threshold;
    }
    
    /**
     * AJAX handler for availability check
     */
    public function ajax_get_availability() {
        check_ajax_referer('wcefp_booking_v2', 'nonce');
        
        $product_id = absint($_POST['product_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? '');
        
        if (!$product_id || !$date) {
            wp_send_json_error(__('Missing required parameters.', 'wceventsfp'));
        }
        
        // Get availability data (this would integrate with your existing availability system)
        $availability = $this->get_date_availability($product_id, $date);
        
        wp_send_json_success($availability);
    }
    
    /**
     * AJAX handler for adding to cart
     */
    public function ajax_add_to_cart() {
        check_ajax_referer('wcefp_booking_v2', 'nonce');
        
        $product_id = absint($_POST['product_id'] ?? 0);
        $booking_date = sanitize_text_field($_POST['booking_date'] ?? '');
        $booking_time = sanitize_text_field($_POST['booking_time'] ?? '');
        $adult_qty = absint($_POST['adult_qty'] ?? 0);
        $child_qty = absint($_POST['child_qty'] ?? 0);
        $extras = array_map('absint', $_POST['extras'] ?? []);
        
        if (!$product_id || !$booking_date || !$booking_time || !$adult_qty) {
            wp_send_json_error(__('Missing required booking information.', 'wceventsfp'));
        }
        
        // Add to cart with booking meta (integrate with existing cart system)
        $cart_item_data = [
            'wcefp_booking_date' => $booking_date,
            'wcefp_booking_time' => $booking_time,
            'wcefp_adult_qty' => $adult_qty,
            'wcefp_child_qty' => $child_qty,
            'wcefp_extras' => $extras
        ];
        
        $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);
        
        if ($cart_item_key) {
            wp_send_json_success([
                'message' => __('Added to cart successfully!', 'wceventsfp'),
                'cart_url' => wc_get_cart_url()
            ]);
        } else {
            wp_send_json_error(__('Failed to add to cart.', 'wceventsfp'));
        }
    }
    
    /**
     * Get availability for a specific date
     * 
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return array
     */
    private function get_date_availability($product_id, $date) {
        // This would integrate with your existing availability system
        // For now, returning sample data structure
        return [
            'slots' => [
                [
                    'time' => '09:00',
                    'available' => 8,
                    'total' => 10,
                    'price' => get_post_meta($product_id, '_wcefp_price_adult', true)
                ],
                [
                    'time' => '14:00',
                    'available' => 3,
                    'total' => 10,
                    'price' => get_post_meta($product_id, '_wcefp_price_adult', true)
                ],
                [
                    'time' => '17:00',
                    'available' => 0,
                    'total' => 10,
                    'price' => get_post_meta($product_id, '_wcefp_price_adult', true)
                ]
            ]
        ];
    }
}