<?php
/**
 * Shortcode Manager
 * 
 * Handles all shortcodes for WCEventsFP plugin frontend components.
 * 
 * @package WCEFP
 * @subpackage Frontend
 * @since 2.1.4
 */

namespace WCEFP\Frontend;

use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode Manager class
 */
class ShortcodeManager {
    
    /**
     * Initialize shortcodes
     */
    public function __construct() {
        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_shortcode_assets']);
    }
    
    /**
     * Register all shortcodes
     */
    public function register_shortcodes() {
        // Main shortcodes
        add_shortcode('wcefp_events', [$this, 'events_list_shortcode']);
        add_shortcode('wcefp_experiences', [$this, 'experiences_catalog_shortcode']);
        add_shortcode('wcefp_event', [$this, 'single_event_shortcode']);
        add_shortcode('wcefp_booking_form', [$this, 'booking_form_shortcode']);
        add_shortcode('wcefp_search', [$this, 'search_events_shortcode']);
        
        // Widget-style shortcodes
        add_shortcode('wcefp_featured_events', [$this, 'featured_events_shortcode']);
        add_shortcode('wcefp_upcoming_events', [$this, 'upcoming_events_shortcode']);
        add_shortcode('wcefp_event_calendar', [$this, 'event_calendar_shortcode']);
        
        // User account shortcodes
        add_shortcode('wcefp_user_bookings', [$this, 'user_bookings_shortcode']);
        
        // Experience page v2 (GYG-style)
        add_shortcode('wcefp_experience_page_v2', [$this, 'experience_page_v2_shortcode']);
        add_shortcode('wcefp_booking_status', [$this, 'booking_status_shortcode']);
        
        // Integration shortcodes
        add_shortcode('wcefp_google_reviews', [$this, 'google_reviews_shortcode']);
        add_shortcode('wcefp_conversion_optimizer', [$this, 'conversion_optimizer_shortcode']);
        
        // Utility shortcodes
        add_shortcode('wcefp_availability', [$this, 'availability_checker_shortcode']);
        add_shortcode('wcefp_price_calculator', [$this, 'price_calculator_shortcode']);
    }
    
    /**
     * Enqueue assets for shortcodes
     */
    public function enqueue_shortcode_assets() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        // Check if page contains WCEFP shortcodes
        if ($this->has_wcefp_shortcodes($post->post_content)) {
            wp_enqueue_style(
                'wcefp-shortcodes',
                WCEFP_PLUGIN_URL . 'assets/css/shortcodes.css',
                [],
                WCEFP_VERSION
            );
            
            wp_enqueue_script(
                'wcefp-shortcodes',
                WCEFP_PLUGIN_URL . 'assets/js/shortcodes.js',
                ['jquery'],
                WCEFP_VERSION,
                true
            );
            
            wp_localize_script('wcefp-shortcodes', 'wcefp_shortcodes', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcefp_shortcode'),
                'currency' => get_option('woocommerce_currency'),
                'currency_symbol' => html_entity_decode(get_woocommerce_currency_symbol(get_option('woocommerce_currency'))),
                'date_format' => get_option('date_format'),
                'strings' => [
                    'loading' => __('Loading...', 'wceventsfp'),
                    'error' => __('An error occurred. Please try again.', 'wceventsfp'),
                    'no_events' => __('No events found.', 'wceventsfp'),
                    'book_now' => __('Book Now', 'wceventsfp'),
                    'select_date' => __('Select Date', 'wceventsfp'),
                    'select_participants' => __('Select Participants', 'wceventsfp'),
                    'total_price' => __('Total Price', 'wceventsfp'),
                    'add_to_cart' => __('Add to Cart', 'wceventsfp')
                ]
            ]);
        }
    }
    
    /**
     * Events list shortcode
     * 
     * Usage: [wcefp_events limit="10" category="tours" show_price="yes" show_excerpt="yes"]
     */
    public function events_list_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 10,
            'category' => '',
            'show_price' => 'yes',
            'show_excerpt' => 'yes',
            'show_image' => 'yes',
            'columns' => 3,
            'orderby' => 'date',
            'order' => 'DESC',
            'class' => ''
        ], $atts, 'wcefp_events');
        
        try {
            $args = [
                'post_type' => 'product',
                'posts_per_page' => intval($atts['limit']),
                'post_status' => 'publish',
                'orderby' => sanitize_text_field($atts['orderby']),
                'order' => sanitize_text_field($atts['order']),
                'meta_query' => [
                    [
                        'key' => '_wcefp_is_event',
                        'value' => '1',
                        'compare' => '='
                    ]
                ]
            ];
            
            // Add category filter
            if (!empty($atts['category'])) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => sanitize_text_field($atts['category'])
                    ]
                ];
            }
            
            $events = get_posts($args);
            
            if (empty($events)) {
                return '<div class="wcefp-no-events">' . esc_html__('No events found.', 'wceventsfp') . '</div>';
            }
            
            $columns = max(1, min(6, intval($atts['columns'])));
            $css_class = 'wcefp-events-grid wcefp-columns-' . $columns;
            
            if (!empty($atts['class'])) {
                $css_class .= ' ' . sanitize_html_class($atts['class']);
            }
            
            ob_start();
            ?>
            <div class="<?php echo esc_attr(esc_attr($css_class)); ?>">
                <?php foreach ($events as $event): ?>
                    <div class="wcefp-event-item">
                        <?php if ($atts['show_image'] === 'yes' && has_post_thumbnail($event->ID)): ?>
                            <div class="wcefp-event-image">
                                <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                                    <?php echo get_the_post_thumbnail($event->ID, 'medium'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="wcefp-event-content">
                            <h3 class="wcefp-event-title">
                                <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                                    <?php echo esc_html($event->post_title); ?>
                                </a>
                            </h3>
                            
                            <?php if ($atts['show_excerpt'] === 'yes' && !empty($event->post_excerpt)): ?>
                                <div class="wcefp-event-excerpt">
                                    <?php echo wp_kses_post($event->post_excerpt); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($atts['show_price'] === 'yes'): ?>
                                <div class="wcefp-event-price">
                                    <?php echo $this->get_event_price_html($event->ID); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="wcefp-event-meta">
                                <?php echo $this->get_event_meta($event->ID); ?>
                            </div>
                            
                            <div class="wcefp-event-actions">
                                <a href="<?php echo esc_url(get_permalink($event->ID)); ?>" class="wcefp-btn wcefp-btn-primary">
                                    <?php esc_html_e('View Details', 'wceventsfp'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
            
            return ob_get_clean();
            
        } catch (\Exception $e) {
            DiagnosticLogger::instance()->error('Shortcode error: wcefp_events', [
                'error' => $e->getMessage(),
                'atts' => $atts
            ], DiagnosticLogger::CHANNEL_GENERAL);
            
            return '<div class="wcefp-error">' . esc_html__('Unable to load events.', 'wceventsfp') . '</div>';
        }
    }
    
    /**
     * Experiences catalog shortcode for marketplace-style display
     * 
     * Usage: [wcefp_experiences filters="location,duration,price,rating,date" view="grid|list" map="on|off" per_page="12"]
     */
    public function experiences_catalog_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 12,
            'per_page' => 12, // Alternative to limit
            'category' => '',
            'filters' => 'location,duration,price,rating,category', // Available filters
            'show_filters' => 'yes',
            'show_map' => 'no',
            'map' => 'off', // Alternative to show_map (on|off)
            'layout' => 'grid', // grid, list, masonry
            'view' => 'grid', // Alternative to layout (grid|list)
            'columns' => 3,
            'orderby' => 'date', // date, popularity, rating, price, title
            'order' => 'DESC',
            'show_price' => 'yes',
            'show_rating' => 'yes',
            'show_duration' => 'yes',
            'show_location' => 'yes',
            'show_date' => 'yes',
            'skeleton' => 'yes', // Show skeleton loading
            'ajax' => 'yes', // Enable AJAX pagination
            'class' => ''
        ], $atts, 'wcefp_experiences');

        // Normalize parameters
        $limit = !empty($atts['per_page']) ? intval($atts['per_page']) : intval($atts['limit']);
        $show_map = ($atts['map'] === 'on') ? 'yes' : $atts['show_map'];
        $layout = !empty($atts['view']) ? $atts['view'] : $atts['layout'];
        $available_filters = array_map('trim', explode(',', $atts['filters']));

        try {
            // Query experiences/events with proper WooCommerce integration
            $args = [
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'orderby' => $this->get_experiences_orderby($atts['orderby']),
                'order' => strtoupper($atts['order']),
                'meta_query' => [
                    'relation' => 'OR',
                    [
                        'key' => '_wcefp_is_experience',
                        'value' => '1',
                        'compare' => '='
                    ],
                    [
                        'key' => '_wcefp_product_type',
                        'value' => ['evento', 'esperienza'],
                        'compare' => 'IN'
                    ]
                ]
            ];

            // Add category filter if specified
            if (!empty($atts['category'])) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'product_cat',
                        'field'    => 'slug',
                        'terms'    => sanitize_text_field($atts['category'])
                    ]
                ];
            }

            $experiences = get_posts($args);

            if (empty($experiences)) {
                return '<div class="wcefp-experiences-empty">' . esc_html__('Nessuna esperienza trovata.', 'wceventsfp') . '</div>';
            }

            $css_class = 'wcefp-experiences-catalog wcefp-layout-' . sanitize_html_class($layout);
            $css_class .= ' wcefp-columns-' . intval($atts['columns']);
            if (!empty($atts['class'])) {
                $css_class .= ' ' . sanitize_html_class($atts['class']);
            }
            if ($atts['ajax'] === 'yes') {
                $css_class .= ' wcefp-ajax-enabled';
            }

            ob_start();
            ?>
            <div class="<?php echo esc_attr($css_class); ?>" 
                 data-layout="<?php echo esc_attr($layout); ?>"
                 data-ajax="<?php echo esc_attr($atts['ajax']); ?>"
                 data-per-page="<?php echo esc_attr($limit); ?>">
                
                <?php if ($atts['show_filters'] === 'yes'): ?>
                <div class="wcefp-experiences-filters">
                    <div class="wcefp-filter-row">
                        
                        <?php if (in_array('search', $available_filters) || in_array('category', $available_filters)): ?>
                        <div class="wcefp-search-field">
                            <span class="wcefp-search-icon">🔍</span>
                            <input type="text" class="wcefp-search-input" placeholder="<?php esc_attr_e('Cerca esperienze...', 'wceventsfp'); ?>">
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('category', $available_filters)): ?>
                        <select class="wcefp-filter-select wcefp-filter-category">
                            <option value=""><?php esc_html_e('Tutte le categorie', 'wceventsfp'); ?></option>
                            <?php 
                            $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true]);
                            foreach ($categories as $cat) {
                                echo '<option value="' . esc_attr($cat->slug) . '">' . esc_html($cat->name) . '</option>';
                            }
                            ?>
                        </select>
                        <?php endif; ?>
                        
                        <?php if (in_array('location', $available_filters)): ?>
                        <select class="wcefp-filter-select wcefp-filter-location">
                            <option value=""><?php esc_html_e('Tutte le destinazioni', 'wceventsfp'); ?></option>
                            <?php echo $this->get_location_filter_options(); ?>
                        </select>
                        <?php endif; ?>
                        
                        <?php if (in_array('duration', $available_filters)): ?>
                        <select class="wcefp-filter-select wcefp-filter-duration">
                            <option value=""><?php esc_html_e('Tutte le durate', 'wceventsfp'); ?></option>
                            <option value="0-2"><?php esc_html_e('Fino a 2 ore', 'wceventsfp'); ?></option>
                            <option value="2-4"><?php esc_html_e('2-4 ore', 'wceventsfp'); ?></option>
                            <option value="4-8"><?php esc_html_e('4-8 ore', 'wceventsfp'); ?></option>
                            <option value="8+"><?php esc_html_e('Oltre 8 ore', 'wceventsfp'); ?></option>
                        </select>
                        <?php endif; ?>
                        
                        <?php if (in_array('price', $available_filters)): ?>
                        <select class="wcefp-filter-select wcefp-filter-price">
                            <option value=""><?php esc_html_e('Tutti i prezzi', 'wceventsfp'); ?></option>
                            <option value="0-50">€0 - €50</option>
                            <option value="50-100">€50 - €100</option>
                            <option value="100-200">€100 - €200</option>
                            <option value="200+">€200+</option>
                        </select>
                        <?php endif; ?>
                        
                        <?php if (in_array('rating', $available_filters)): ?>
                        <select class="wcefp-filter-select wcefp-filter-rating">
                            <option value=""><?php esc_html_e('Tutte le recensioni', 'wceventsfp'); ?></option>
                            <option value="4+">4+ ⭐</option>
                            <option value="3+">3+ ⭐</option>
                            <option value="2+">2+ ⭐</option>
                        </select>
                        <?php endif; ?>
                        
                        <?php if (in_array('date', $available_filters)): ?>
                        <input type="date" class="wcefp-filter-date" min="<?php echo esc_attr(date('Y-m-d')); ?>" placeholder="<?php esc_attr_e('Seleziona data', 'wceventsfp'); ?>">
                        <?php endif; ?>
                        
                        <div class="wcefp-sort-controls">
                            <select class="wcefp-sort-select">
                                <option value="date-desc"><?php esc_html_e('Più recenti', 'wceventsfp'); ?></option>
                                <option value="popularity-desc"><?php esc_html_e('Più popolari', 'wceventsfp'); ?></option>
                                <option value="rating-desc"><?php esc_html_e('Migliori recensioni', 'wceventsfp'); ?></option>
                                <option value="price-asc"><?php esc_html_e('Prezzo: dal più basso', 'wceventsfp'); ?></option>
                                <option value="price-desc"><?php esc_html_e('Prezzo: dal più alto', 'wceventsfp'); ?></option>
                                <option value="title-asc"><?php esc_html_e('Nome A-Z', 'wceventsfp'); ?></option>
                            </select>
                        </div>
                        
                        <button class="wcefp-clear-filters"><?php esc_html_e('Cancella filtri', 'wceventsfp'); ?></button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($show_map === 'yes'): ?>
                <div class="wcefp-experiences-map" id="wcefp-map-<?php echo esc_attr(uniqid()); ?>">
                    <div class="wcefp-map-placeholder">
                        <p><?php esc_html_e('Mappa delle esperienze (richiede Google Maps API)', 'wceventsfp'); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="wcefp-experiences-grid">
                    <?php if ($atts['skeleton'] === 'yes'): ?>
                    <div class="wcefp-skeleton-loader" style="display: none;">
                        <?php for ($i = 0; $i < $limit; $i++): ?>
                        <div class="wcefp-skeleton-card">
                            <div class="wcefp-skeleton-image"></div>
                            <div class="wcefp-skeleton-content">
                                <div class="wcefp-skeleton-title"></div>
                                <div class="wcefp-skeleton-meta"></div>
                                <div class="wcefp-skeleton-text"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="wcefp-experiences-items" aria-live="polite">
                        <?php foreach ($experiences as $experience): ?>
                            <?php echo $this->render_experience_card($experience, $atts); ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($atts['ajax'] === 'yes'): ?>
                <div class="wcefp-experiences-pagination">
                    <div class="wcefp-pagination-info">
                        <span class="wcefp-results-count"><?php printf(esc_html__('Mostrando %d di %d esperienze', 'wceventsfp'), count($experiences), count($experiences)); ?></span>
                    </div>
                    <div class="wcefp-pagination-controls">
                        <button class="wcefp-load-more" data-page="1" style="display: none;">
                            <span class="wcefp-load-text"><?php esc_html_e('Carica altre esperienze', 'wceventsfp'); ?></span>
                            <span class="wcefp-load-spinner" style="display: none;">⏳</span>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php

            return ob_get_clean();

        } catch (\Exception $e) {
            DiagnosticLogger::instance()->error('Shortcode error: wcefp_experiences', [
                'error' => $e->getMessage(),
                'atts' => $atts
            ], DiagnosticLogger::CHANNEL_GENERAL);

            return '<div class="wcefp-error">' . esc_html__('Impossibile caricare le esperienze.', 'wceventsfp') . '</div>';
        }
    }
    
    /**
     * Single event shortcode
     * 
     * Usage: [wcefp_event id="123" show_booking_form="yes"]
     */
    public function single_event_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'show_booking_form' => 'no',
            'show_gallery' => 'yes',
            'show_description' => 'yes',
            'class' => ''
        ], $atts, 'wcefp_event');
        
        $event_id = intval($atts['id']);
        
        if (!$event_id) {
            return '<div class="wcefp-error">' . esc_html__('Event ID required.', 'wceventsfp') . '</div>';
        }
        
        $event = get_post($event_id);
        
        if (!$event || $event->post_type !== 'product') {
            return '<div class="wcefp-error">' . esc_html__('Event not found.', 'wceventsfp') . '</div>';
        }
        
        // Check if it's actually an event
        if (get_post_meta($event_id, '_wcefp_is_event', true) !== '1') {
            return '<div class="wcefp-error">' . esc_html__('Product is not an event.', 'wceventsfp') . '</div>';
        }
        
        $css_class = 'wcefp-single-event';
        if (!empty($atts['class'])) {
            $css_class .= ' ' . sanitize_html_class($atts['class']);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr(esc_attr($css_class)); ?>">
            <div class="wcefp-event-header">
                <h2 class="wcefp-event-title"><?php echo esc_html($event->post_title); ?></h2>
                
                <?php if ($atts['show_gallery'] === 'yes'): ?>
                    <div class="wcefp-event-gallery">
                        <?php echo $this->get_event_gallery($event_id); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="wcefp-event-body">
                <div class="wcefp-event-main">
                    <?php if ($atts['show_description'] === 'yes'): ?>
                        <div class="wcefp-event-description">
                            <?php echo wp_kses_post($event->post_content); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="wcefp-event-details">
                        <?php echo $this->get_event_details($event_id); ?>
                    </div>
                </div>
                
                <div class="wcefp-event-sidebar">
                    <div class="wcefp-event-pricing">
                        <?php echo $this->get_event_pricing($event_id); ?>
                    </div>
                    
                    <?php if ($atts['show_booking_form'] === 'yes'): ?>
                        <div class="wcefp-booking-form-container">
                            <?php echo $this->booking_form_shortcode(['event_id' => $event_id]); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Booking form shortcode
     * 
     * Usage: [wcefp_booking_form event_id="123" style="modern"]
     */
    public function booking_form_shortcode($atts) {
        $atts = shortcode_atts([
            'event_id' => 0,
            'style' => 'default',
            'show_calendar' => 'yes',
            'show_participants' => 'yes',
            'class' => ''
        ], $atts, 'wcefp_booking_form');
        
        $event_id = intval($atts['event_id']);
        
        if (!$event_id) {
            // Try to get event ID from current post
            global $post;
            if ($post && $post->post_type === 'product') {
                $event_id = $post->ID;
            }
        }
        
        if (!$event_id) {
            return '<div class="wcefp-error">' . esc_html__('Event ID required for booking form.', 'wceventsfp') . '</div>';
        }
        
        $event = get_post($event_id);
        if (!$event || get_post_meta($event_id, '_wcefp_is_event', true) !== '1') {
            return '<div class="wcefp-error">' . esc_html__('Invalid event for booking.', 'wceventsfp') . '</div>';
        }
        
        $capacity = get_post_meta($event_id, '_wcefp_capacity', true) ?: 10;
        $price = get_post_meta($event_id, '_regular_price', true) ?: 0;
        
        $css_class = 'wcefp-booking-form wcefp-form-style-' . sanitize_html_class($atts['style']);
        if (!empty($atts['class'])) {
            $css_class .= ' ' . sanitize_html_class($atts['class']);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr(esc_attr($css_class)); ?>" data-="<?php echo esc_attr(esc_attr(esc_attr($event_id))); ?>">
            <h3 class="wcefp-form-title"><?php esc_html_e('Book This Event', 'wceventsfp'); ?></h3>
            
            <form class="wcefp-booking-form-inner" method="post" action="">
                <?php wp_nonce_field('wcefp_booking_form', '_wcefp_nonce'); ?>
                <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                
                <?php if ($atts['show_calendar'] === 'yes'): ?>
                    <div class="wcefp-form-field">
                        <label for="wcefp_booking_date"><?php esc_html_e('Select Date', 'wceventsfp'); ?> *</label>
                        <input type="date" id="wcefp_booking_date" name="booking_date" required 
                               min="<?php echo esc_attr(date('Y-m-d')); ?>" 
                               class="wcefp-datepicker">
                        <div class="wcefp-availability-info"></div>
                    </div>
                <?php endif; ?>
                
                <div class="wcefp-form-field">
                    <label for="wcefp_booking_time"><?php esc_html_e('Select Time', 'wceventsfp'); ?></label>
                    <select id="wcefp_booking_time" name="booking_time" class="wcefp-time-select">
                        <option value=""><?php esc_html_e('Select time slot', 'wceventsfp'); ?></option>
                        <?php echo $this->get_available_time_slots($event_id); ?>
                    </select>
                </div>
                
                <?php if ($atts['show_participants'] === 'yes'): ?>
                    <div class="wcefp-form-field">
                        <label for="wcefp_participants"><?php esc_html_e('Number of Participants', 'wceventsfp'); ?> *</label>
                        <select id="wcefp_participants" name="participants" required class="wcefp-participants-select">
                            <?php for ($i = 1; $i <= min(10, $capacity); $i++): ?>
                                <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="wcefp-form-field wcefp-customer-info" style="display: none;">
                    <h4><?php esc_html_e('Your Information', 'wceventsfp'); ?></h4>
                    
                    <label for="wcefp_customer_name"><?php esc_html_e('Full Name', 'wceventsfp'); ?> *</label>
                    <input type="text" id="wcefp_customer_name" name="customer_name" required>
                    
                    <label for="wcefp_customer_email"><?php esc_html_e('Email', 'wceventsfp'); ?> *</label>
                    <input type="email" id="wcefp_customer_email" name="customer_email" required>
                    
                    <label for="wcefp_customer_phone"><?php esc_html_e('Phone', 'wceventsfp'); ?></label>
                    <input type="tel" id="wcefp_customer_phone" name="customer_phone">
                    
                    <label for="wcefp_special_requests"><?php esc_html_e('Special Requests', 'wceventsfp'); ?></label>
                    <textarea id="wcefp_special_requests" name="special_requests" rows="3"></textarea>
                </div>
                
                <div class="wcefp-pricing-summary">
                    <div class="wcefp-price-breakdown">
                        <div class="wcefp-price-line">
                            <span class="wcefp-price-label"><?php esc_html_e('Price per person:', 'wceventsfp'); ?></span>
                            <span class="wcefp-price-value" data-="<?php echo esc_attr(esc_attr($price)); ?>">
                                <?php echo wc_price($price); ?>
                            </span>
                        </div>
                        <div class="wcefp-price-line wcefp-total-line">
                            <span class="wcefp-price-label"><?php esc_html_e('Total:', 'wceventsfp'); ?></span>
                            <span class="wcefp-total-price"><?php echo wc_price($price); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="wcefp-form-actions">
                    <button type="submit" class="wcefp-btn wcefp-btn-primary wcefp-book-now-btn">
                        <span class="wcefp-btn-text"><?php esc_html_e('Book Now', 'wceventsfp'); ?></span>
                        <span class="wcefp-btn-loader" style="display: none;"><?php esc_html_e('Processing...', 'wceventsfp'); ?></span>
                    </button>
                </div>
            </form>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Search events shortcode
     * 
     * Usage: [wcefp_search show_filters="yes" show_map="no"]
     */
    public function search_events_shortcode($atts) {
        $atts = shortcode_atts([
            'show_filters' => 'yes',
            'show_map' => 'no',
            'show_results' => 'yes',
            'default_view' => 'grid',
            'class' => ''
        ], $atts, 'wcefp_search');
        
        $css_class = 'wcefp-search-events';
        if (!empty($atts['class'])) {
            $css_class .= ' ' . sanitize_html_class($atts['class']);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr(esc_attr($css_class)); ?>">
            <div class="wcefp-search-form">
                <form method="get" class="wcefp-search-form-inner">
                    <div class="wcefp-search-fields">
                        <div class="wcefp-search-field wcefp-search-text">
                            <input type="text" name="s" placeholder="<?php esc_attr_e('Search events...', 'wceventsfp'); ?>" 
                                   value="<?php echo esc_attr(get_search_query()); ?>" class="wcefp-search-input">
                        </div>
                        
                        <div class="wcefp-search-field wcefp-search-category">
                            <?php
                            $categories = get_terms([
                                'taxonomy' => 'product_cat',
                                'hide_empty' => true,
                                'meta_query' => [
                                    [
                                        'key' => '_wcefp_has_events',
                                        'value' => '1',
                                        'compare' => '='
                                    ]
                                ]
                            ]);
                            
                            if (!empty($categories) && !is_wp_error($categories)):
                            ?>
                                <select name="category" class="wcefp-category-select">
                                    <option value=""><?php esc_html_e('All Categories', 'wceventsfp'); ?></option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo esc_attr($category->slug); ?>" 
                                                <?php selected($_GET['category'] ?? '', $category->slug); ?>>
                                            <?php echo esc_html($category->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        
                        <div class="wcefp-search-field wcefp-search-date">
                            <input type="date" name="date_from" placeholder="<?php esc_attr_e('From date', 'wceventsfp'); ?>" 
                                   value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" min="<?php echo esc_attr(date('Y-m-d')); ?>">
                        </div>
                        
                        <div class="wcefp-search-field wcefp-search-submit">
                            <button type="submit" class="wcefp-btn wcefp-btn-primary">
                                <?php esc_html_e('Search', 'wceventsfp'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($atts['show_filters'] === 'yes'): ?>
                        <div class="wcefp-search-filters" style="display: none;">
                            <div class="wcefp-filter-group">
                                <label><?php esc_html_e('Price Range', 'wceventsfp'); ?></label>
                                <div class="wcefp-price-range">
                                    <input type="number" name="price_min" placeholder="<?php esc_attr_e('Min', 'wceventsfp'); ?>" 
                                           value="<?php echo esc_attr($_GET['price_min'] ?? ''); ?>">
                                    <span>-</span>
                                    <input type="number" name="price_max" placeholder="<?php esc_attr_e('Max', 'wceventsfp'); ?>" 
                                           value="<?php echo esc_attr($_GET['price_max'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="wcefp-filter-group">
                                <label><?php esc_html_e('Duration', 'wceventsfp'); ?></label>
                                <select name="duration">
                                    <option value=""><?php esc_html_e('Any duration', 'wceventsfp'); ?></option>
                                    <option value="0-60" <?php selected($_GET['duration'] ?? '', '0-60'); ?>>
                                        <?php esc_html_e('Up to 1 hour', 'wceventsfp'); ?>
                                    </option>
                                    <option value="60-180" <?php selected($_GET['duration'] ?? '', '60-180'); ?>>
                                        <?php esc_html_e('1-3 hours', 'wceventsfp'); ?>
                                    </option>
                                    <option value="180-480" <?php selected($_GET['duration'] ?? '', '180-480'); ?>>
                                        <?php esc_html_e('3-8 hours', 'wceventsfp'); ?>
                                    </option>
                                    <option value="480+" <?php selected($_GET['duration'] ?? '', '480+'); ?>>
                                        <?php esc_html_e('Full day+', 'wceventsfp'); ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="button" class="wcefp-toggle-filters">
                            <?php esc_html_e('More Filters', 'wceventsfp'); ?>
                        </button>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if ($atts['show_results'] === 'yes'): ?>
                <div class="wcefp-search-results">
                    <div class="wcefp-results-header">
                        <div class="wcefp-results-info">
                            <span class="wcefp-results-count"><?php esc_html_e('Loading...', 'wceventsfp'); ?></span>
                        </div>
                        
                        <div class="wcefp-results-controls">
                            <div class="wcefp-view-toggle">
                                <button type="button" class="wcefp-view-btn <?php echo esc_attr($atts['default_view'] === 'grid' ? 'active' : ''); ?>" 
                                        data-view="grid" title="<?php esc_attr_e('Grid View', 'wceventsfp'); ?>">
                                    <span class="dashicons dashicons-grid-view"></span>
                                </button>
                                <button type="button" class="wcefp-view-btn <?php echo esc_attr($atts['default_view'] === 'list' ? 'active' : ''); ?>" 
                                        data-view="list" title="<?php esc_attr_e('List View', 'wceventsfp'); ?>">
                                    <span class="dashicons dashicons-list-view"></span>
                                </button>
                            </div>
                            
                            <select class="wcefp-sort-select">
                                <option value="date"><?php esc_html_e('Sort by Date', 'wceventsfp'); ?></option>
                                <option value="title"><?php esc_html_e('Sort by Name', 'wceventsfp'); ?></option>
                                <option value="price"><?php esc_html_e('Sort by Price', 'wceventsfp'); ?></option>
                                <option value="popularity"><?php esc_html_e('Sort by Popularity', 'wceventsfp'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="wcefp-results-container" data-="<?php echo esc_attr(esc_attr($atts['default_view'])); ?>">
                        <div class="wcefp-loading"><?php esc_html_e('Loading events...', 'wceventsfp'); ?></div>
                    </div>
                    
                    <div class="wcefp-results-pagination"></div>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_map'] === 'yes'): ?>
                <div class="wcefp-events-map">
                    <div id="wcefp-map-container" style="height: 400px;"></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Google Reviews shortcode
     * 
     * Usage: [wcefp_google_reviews limit="5" show_rating="yes" show_avatar="yes"]
     */
    public function google_reviews_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 5,
            'show_rating' => 'yes',
            'show_avatar' => 'yes',
            'show_date' => 'yes',
            'min_rating' => 3,
            'style' => 'cards',
            'class' => ''
        ], $atts, 'wcefp_google_reviews');
        
        // Check if Google Reviews is configured
        $api_key = get_option('wcefp_google_places_api_key');
        $place_id = get_option('wcefp_google_place_id');
        
        if (!$api_key || !$place_id) {
            if (current_user_can('manage_options')) {
                return '<div class="wcefp-error">' . 
                       esc_html__('Google Reviews not configured. Please check plugin settings.', 'wceventsfp') . 
                       '</div>';
            }
            return '';
        }
        
        // Get cached reviews or fetch new ones
        $reviews = $this->get_google_reviews($api_key, $place_id, $atts);
        
        if (empty($reviews)) {
            return '<div class="wcefp-no-reviews">' . esc_html__('No reviews available.', 'wceventsfp') . '</div>';
        }
        
        $css_class = 'wcefp-google-reviews wcefp-reviews-' . sanitize_html_class($atts['style']);
        if (!empty($atts['class'])) {
            $css_class .= ' ' . sanitize_html_class($atts['class']);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr(esc_attr($css_class)); ?>">
            <div class="wcefp-reviews-header">
                <h3><?php esc_html_e('Customer Reviews', 'wceventsfp'); ?></h3>
                <div class="wcefp-reviews-powered">
                    <?php esc_html_e('Powered by Google', 'wceventsfp'); ?>
                </div>
            </div>
            
            <div class="wcefp-reviews-container">
                <?php foreach ($reviews as $review): ?>
                    <div class="wcefp-review-item">
                        <?php if ($atts['show_avatar'] === 'yes' && !empty($review['profile_photo_url'])): ?>
                            <div class="wcefp-review-avatar">
                                <img src="<?php echo esc_url($review['profile_photo_url']); ?>" 
                                     alt="<?php echo esc_attr($review['author_name']); ?>"
                                     loading="lazy">
                            </div>
                        <?php endif; ?>
                        
                        <div class="wcefp-review-content">
                            <div class="wcefp-review-header">
                                <div class="wcefp-review-author">
                                    <strong><?php echo esc_html($review['author_name']); ?></strong>
                                </div>
                                
                                <?php if ($atts['show_rating'] === 'yes'): ?>
                                    <div class="wcefp-review-rating">
                                        <?php echo $this->get_star_rating($review['rating']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($atts['show_date'] === 'yes' && !empty($review['time'])): ?>
                                    <div class="wcefp-review-date">
                                        <?php echo esc_html(date_i18n(get_option('date_format'), $review['time'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="wcefp-review-text">
                                <?php echo wp_kses_post($review['text']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    // Helper methods for shortcodes...
    
    private function has_wcefp_shortcodes($content) {
        return has_shortcode($content, 'wcefp_events') ||
               has_shortcode($content, 'wcefp_experiences') ||
               has_shortcode($content, 'wcefp_event') ||
               has_shortcode($content, 'wcefp_booking_form') ||
               has_shortcode($content, 'wcefp_search') ||
               has_shortcode($content, 'wcefp_featured_events') ||
               has_shortcode($content, 'wcefp_upcoming_events') ||
               has_shortcode($content, 'wcefp_event_calendar') ||
               has_shortcode($content, 'wcefp_user_bookings') ||
               has_shortcode($content, 'wcefp_google_reviews');
    }
    
    private function get_event_price_html($event_id) {
        $product = wc_get_product($event_id);
        if (!$product) {
            return '';
        }
        
        return $product->get_price_html();
    }
    
    private function get_event_meta($event_id) {
        $meta = [];
        
        $duration = get_post_meta($event_id, '_wcefp_duration', true);
        if ($duration) {
            $hours = floor($duration / 60);
            $minutes = $duration % 60;
            
            if ($hours > 0 && $minutes > 0) {
                $meta[] = sprintf(__('%dh %dm', 'wceventsfp'), $hours, $minutes);
            } elseif ($hours > 0) {
                $meta[] = sprintf(__('%dh', 'wceventsfp'), $hours);
            } else {
                $meta[] = sprintf(__('%dm', 'wceventsfp'), $minutes);
            }
        }
        
        $capacity = get_post_meta($event_id, '_wcefp_capacity', true);
        if ($capacity) {
            $meta[] = sprintf(__('Max %d people', 'wceventsfp'), $capacity);
        }
        
        $location = get_post_meta($event_id, '_wcefp_location', true);
        if ($location) {
            $meta[] = esc_html($location);
        }
        
        return !empty($meta) ? '<span class="wcefp-meta">' . implode(' • ', $meta) . '</span>' : '';
    }
    
    private function get_event_gallery($event_id) {
        $gallery = get_post_meta($event_id, '_product_image_gallery', true);
        
        if (!$gallery) {
            // Fallback to featured image
            if (has_post_thumbnail($event_id)) {
                return get_the_post_thumbnail($event_id, 'large', ['class' => 'wcefp-main-image']);
            }
            return '';
        }
        
        $gallery_ids = explode(',', $gallery);
        
        ob_start();
        ?>
        <div class="wcefp-event-gallery-slider">
            <?php foreach ($gallery_ids as $attachment_id): ?>
                <?php echo wp_get_attachment_image($attachment_id, 'large', false, ['class' => 'wcefp-gallery-image']); ?>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    private function get_event_details($event_id) {
        $details = [];
        
        $duration = get_post_meta($event_id, '_wcefp_duration', true);
        if ($duration) {
            $hours = floor($duration / 60);
            $minutes = $duration % 60;
            $duration_text = $hours > 0 ? sprintf(__('%dh %dm', 'wceventsfp'), $hours, $minutes) : sprintf(__('%dm', 'wceventsfp'), $minutes);
            $details[] = ['label' => __('Duration', 'wceventsfp'), 'value' => $duration_text];
        }
        
        $capacity = get_post_meta($event_id, '_wcefp_capacity', true);
        if ($capacity) {
            $details[] = ['label' => __('Maximum Participants', 'wceventsfp'), 'value' => $capacity];
        }
        
        $location = get_post_meta($event_id, '_wcefp_location', true);
        if ($location) {
            $details[] = ['label' => __('Location', 'wceventsfp'), 'value' => $location];
        }
        
        // Meeting Point - Enhanced with CPT support
        $meeting_point_text = '';
        
        // Check for custom override first
        $custom_meeting_point = get_post_meta($event_id, '_wcefp_meeting_point_custom', true);
        if ($custom_meeting_point) {
            $meeting_point_text = $custom_meeting_point;
        } else {
            // Check for selected meeting point ID
            $meeting_point_id = get_post_meta($event_id, '_wcefp_meeting_point_id', true);
            if ($meeting_point_id && class_exists('WCEFP_MeetingPoints_CPT')) {
                $mp_data = WCEFP_MeetingPoints_CPT::get_meeting_point_data($meeting_point_id);
                if ($mp_data) {
                    $meeting_point_text = $mp_data['title'];
                    if ($mp_data['address'] || $mp_data['city']) {
                        $meeting_point_text .= ' - ' . trim($mp_data['address'] . ', ' . $mp_data['city'], ', ');
                    }
                    if ($mp_data['notes']) {
                        $meeting_point_text .= "\n" . $mp_data['notes'];
                    }
                }
            } else {
                // Fallback to old meeting point field
                $meeting_point_text = get_post_meta($event_id, '_wcefp_meeting_point', true);
            }
        }
        
        if ($meeting_point_text) {
            $details[] = ['label' => __('Meeting Point', 'wceventsfp'), 'value' => nl2br(esc_html($meeting_point_text))];
        }
        
        if (empty($details)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="wcefp-event-details-list">
            <?php foreach ($details as $detail): ?>
                <div class="wcefp-detail-item">
                    <span class="wcefp-detail-label"><?php echo esc_html($detail['label']); ?>:</span>
                    <span class="wcefp-detail-value"><?php echo esc_html($detail['value']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    private function get_event_pricing($event_id) {
        $product = wc_get_product($event_id);
        if (!$product) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="wcefp-event-pricing-box">
            <div class="wcefp-price-display">
                <?php echo $product->get_price_html(); ?>
                <span class="wcefp-price-unit"><?php esc_html_e('per person', 'wceventsfp'); ?></span>
            </div>
            
            <?php if ($product->is_on_sale()): ?>
                <div class="wcefp-sale-badge">
                    <?php esc_html_e('On Sale!', 'wceventsfp'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    private function get_available_time_slots($event_id) {
        // This would typically connect to a booking system
        // For now, return some default time slots
        $time_slots = [
            '09:00' => __('9:00 AM', 'wceventsfp'),
            '10:00' => __('10:00 AM', 'wceventsfp'),
            '11:00' => __('11:00 AM', 'wceventsfp'),
            '14:00' => __('2:00 PM', 'wceventsfp'),
            '15:00' => __('3:00 PM', 'wceventsfp'),
            '16:00' => __('4:00 PM', 'wceventsfp')
        ];
        
        $options = '';
        foreach ($time_slots as $value => $label) {
            $options .= sprintf('<option value="%s">%s</option>', esc_attr($value), esc_html($label));
        }
        
        return $options;
    }
    
    private function get_google_reviews($api_key, $place_id, $atts) {
        $cache_key = 'wcefp_google_reviews_' . md5($place_id . serialize($atts));
        $reviews = get_transient($cache_key);
        
        if ($reviews !== false) {
            return $reviews;
        }
        
        // Fetch from Google Places API
        $url = add_query_arg([
            'place_id' => $place_id,
            'key' => $api_key,
            'fields' => 'reviews',
            'language' => substr(get_locale(), 0, 2)
        ], 'https://maps.googleapis.com/maps/api/place/details/json');
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            DiagnosticLogger::instance()->log_integration('error', 'Google Reviews API error', 'google-reviews', [
                'error' => $response->get_error_message()
            ]);
            return [];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($data['result']['reviews'])) {
            return [];
        }
        
        $reviews = array_filter($data['result']['reviews'], function($review) use ($atts) {
            return $review['rating'] >= intval($atts['min_rating']);
        });
        
        // Limit results
        $reviews = array_slice($reviews, 0, intval($atts['limit']));
        
        // Cache for 6 hours
        set_transient($cache_key, $reviews, 6 * HOUR_IN_SECONDS);
        
        return $reviews;
    }
    
    private function get_star_rating($rating) {
        $full_stars = floor($rating);
        $half_star = ($rating - $full_stars) >= 0.5;
        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
        
        $output = '<div class="wcefp-star-rating" title="' . esc_attr(sprintf(__('%s out of 5 stars', 'wceventsfp'), $rating)) . '">';
        
        for ($i = 0; $i < $full_stars; $i++) {
            $output .= '<span class="wcefp-star wcefp-star-full">★</span>';
        }
        
        if ($half_star) {
            $output .= '<span class="wcefp-star wcefp-star-half">★</span>';
        }
        
        for ($i = 0; $i < $empty_stars; $i++) {
            $output .= '<span class="wcefp-star wcefp-star-empty">☆</span>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Experience Page v2 shortcode - GYG-style single experience layout
     * 
     * Usage: [wcefp_experience_page_v2 id="123" show_reviews="yes" show_booking_widget="yes"]
     */
    public function experience_page_v2_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'show_hero' => 'yes',
            'show_gallery' => 'yes', 
            'show_trust_badges' => 'yes',
            'show_social_proof' => 'yes',
            'show_highlights' => 'yes',
            'show_included' => 'yes',
            'show_itinerary' => 'yes',
            'show_meeting_point' => 'yes',
            'show_booking_widget' => 'yes',
            'show_reviews' => 'yes',
            'show_faq' => 'yes',
            'show_policies' => 'yes',
            'show_schema' => 'yes',
            'widget_position' => 'sticky', // sticky, inline, bottom
            'class' => ''
        ], $atts, 'wcefp_experience_page_v2');
        
        $experience_id = intval($atts['id']);
        
        if (!$experience_id) {
            // Try to get from current post
            global $post;
            if ($post && $post->post_type === 'product') {
                $experience_id = $post->ID;
            }
        }
        
        if (!$experience_id) {
            return '<div class="wcefp-error">' . esc_html__('Experience ID required.', 'wceventsfp') . '</div>';
        }
        
        $product = wc_get_product($experience_id);
        if (!$product) {
            return '<div class="wcefp-error">' . esc_html__('Experience not found.', 'wceventsfp') . '</div>';
        }
        
        // Check if it's an experience
        $is_experience = get_post_meta($experience_id, '_wcefp_is_experience', true);
        if ($is_experience !== '1') {
            return '<div class="wcefp-error">' . esc_html__('Product is not an experience.', 'wceventsfp') . '</div>';
        }
        
        $css_class = 'wcefp-experience-page-v2 wcefp-gyg-style';
        $css_class .= ' wcefp-widget-' . sanitize_html_class($atts['widget_position']);
        if (!empty($atts['class'])) {
            $css_class .= ' ' . sanitize_html_class($atts['class']);
        }
        
        // Get experience data
        $experience_data = $this->get_experience_page_data($experience_id);
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($css_class); ?>" data-experience-id="<?php echo esc_attr($experience_id); ?>">
            
            <?php if ($atts['show_hero'] === 'yes'): ?>
            <!-- Hero Section -->
            <section class="wcefp-hero-section">
                <div class="wcefp-hero-content">
                    <div class="wcefp-hero-main">
                        <h1 class="wcefp-hero-title"><?php echo esc_html($product->get_name()); ?></h1>
                        
                        <?php if ($atts['show_trust_badges'] === 'yes'): ?>
                        <div class="wcefp-hero-trust-badges">
                            <?php echo $this->get_experience_trust_badges($experience_id); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="wcefp-hero-rating">
                            <?php if ($experience_data['rating'] > 0): ?>
                                <span class="wcefp-rating-stars"><?php echo $this->get_star_rating_html($experience_data['rating']); ?></span>
                                <span class="wcefp-rating-text"><?php printf(esc_html__('%s (%d recensioni)', 'wceventsfp'), $experience_data['rating'], $experience_data['review_count']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($atts['show_social_proof'] === 'yes' && !empty($experience_data['bookings_yesterday'])): ?>
                        <div class="wcefp-social-proof">
                            <span class="wcefp-social-proof-icon">🔥</span>
                            <span><?php printf(esc_html__('%d persone hanno prenotato ieri', 'wceventsfp'), $experience_data['bookings_yesterday']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="wcefp-hero-meta">
                        <?php if (!empty($experience_data['duration'])): ?>
                        <div class="wcefp-meta-item">
                            <span class="wcefp-meta-icon">⏱</span>
                            <span><?php echo esc_html($experience_data['duration']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($experience_data['location'])): ?>
                        <div class="wcefp-meta-item">
                            <span class="wcefp-meta-icon">📍</span>
                            <span><?php echo esc_html($experience_data['location']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($experience_data['capacity_remaining']) && $experience_data['capacity_remaining'] < 10): ?>
                        <div class="wcefp-meta-item wcefp-limited-availability">
                            <span class="wcefp-meta-icon">🔥</span>
                            <span><?php printf(esc_html__('Solo %d posti rimasti', 'wceventsfp'), $experience_data['capacity_remaining']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <div class="wcefp-experience-layout">
                <!-- Main Content -->
                <div class="wcefp-experience-main">
                    
                    <?php if ($atts['show_gallery'] === 'yes'): ?>
                    <!-- Gallery Section -->
                    <section class="wcefp-gallery-section">
                        <?php echo $this->render_experience_gallery($experience_id); ?>
                    </section>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_highlights'] === 'yes'): ?>
                    <!-- Highlights Section -->
                    <section class="wcefp-highlights-section">
                        <h2><?php esc_html_e('Punti salienti', 'wceventsfp'); ?></h2>
                        <?php echo $this->render_experience_highlights($experience_id); ?>
                    </section>
                    <?php endif; ?>
                    
                    <!-- Description -->
                    <section class="wcefp-description-section">
                        <h2><?php esc_html_e('Descrizione', 'wceventsfp'); ?></h2>
                        <div class="wcefp-description-content">
                            <?php echo wp_kses_post($product->get_description()); ?>
                        </div>
                    </section>
                    
                    <?php if ($atts['show_included'] === 'yes'): ?>
                    <!-- What's Included / Not Included -->
                    <section class="wcefp-included-section">
                        <div class="wcefp-included-grid">
                            <div class="wcefp-included-column">
                                <h3><?php esc_html_e('Cosa è incluso', 'wceventsfp'); ?></h3>
                                <?php echo $this->render_included_items($experience_id, true); ?>
                            </div>
                            <div class="wcefp-not-included-column">
                                <h3><?php esc_html_e('Cosa non è incluso', 'wceventsfp'); ?></h3>
                                <?php echo $this->render_included_items($experience_id, false); ?>
                            </div>
                        </div>
                    </section>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_itinerary'] === 'yes'): ?>
                    <!-- Itinerary -->
                    <section class="wcefp-itinerary-section">
                        <h2><?php esc_html_e('Itinerario', 'wceventsfp'); ?></h2>
                        <?php echo $this->render_experience_itinerary($experience_id); ?>
                    </section>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_meeting_point'] === 'yes'): ?>
                    <!-- Meeting Point -->
                    <section class="wcefp-meeting-point-section">
                        <h2><?php esc_html_e('Punto di ritrovo', 'wceventsfp'); ?></h2>
                        <?php echo $this->render_meeting_point($experience_id); ?>
                    </section>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_reviews'] === 'yes'): ?>
                    <!-- Reviews -->
                    <section class="wcefp-reviews-section">
                        <h2><?php esc_html_e('Recensioni', 'wceventsfp'); ?></h2>
                        <?php echo $this->render_experience_reviews($experience_id); ?>
                    </section>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_faq'] === 'yes'): ?>
                    <!-- FAQ -->
                    <section class="wcefp-faq-section">
                        <h2><?php esc_html_e('Domande frequenti', 'wceventsfp'); ?></h2>
                        <?php echo $this->render_experience_faq($experience_id); ?>
                    </section>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_policies'] === 'yes'): ?>
                    <!-- Policies -->
                    <section class="wcefp-policies-section">
                        <h2><?php esc_html_e('Politiche di cancellazione', 'wceventsfp'); ?></h2>
                        <?php echo $this->render_experience_policies($experience_id); ?>
                    </section>
                    <?php endif; ?>
                    
                </div>
                
                <!-- Sidebar / Booking Widget -->
                <?php if ($atts['show_booking_widget'] === 'yes'): ?>
                <aside class="wcefp-experience-sidebar">
                    <div class="wcefp-booking-widget-sticky">
                        <?php echo $this->render_booking_widget_v2($experience_id, $atts); ?>
                    </div>
                </aside>
                <?php endif; ?>
                
            </div>
            
            <?php if ($atts['show_schema'] === 'yes'): ?>
            <!-- Schema.org Markup -->
            <?php echo $this->render_experience_schema($experience_id); ?>
            <?php endif; ?>
            
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render individual experience card for catalog
     * 
     * @param WP_Post $experience Experience post object
     * @param array $atts Shortcode attributes
     * @return string HTML output for experience card
     */
    private function render_experience_card($experience, $atts) {
        $product = wc_get_product($experience->ID);
        if (!$product) {
            return '';
        }

        $card_classes = 'wcefp-experience-card';
        $card_classes .= ' wcefp-product-type-' . sanitize_html_class($product->get_type());

        // Get experience data
        $permalink = get_permalink($experience->ID);
        $title = get_the_title($experience->ID);
        $excerpt = wp_trim_words(get_the_excerpt($experience->ID), 20, '...');
        $thumbnail = get_the_post_thumbnail($experience->ID, 'medium', ['class' => 'wcefp-card-image']);
        $price_html = $atts['show_price'] === 'yes' ? $product->get_price_html() : '';

        // Get rating data if enabled
        $rating_html = '';
        if ($atts['show_rating'] === 'yes') {
            $average_rating = $product->get_average_rating();
            $review_count = $product->get_review_count();
            if ($average_rating > 0) {
                $rating_html = $this->get_star_rating_html($average_rating) . ' <span class="wcefp-review-count">(' . $review_count . ')</span>';
            }
        }

        // Get duration if available
        $duration_html = '';
        if ($atts['show_duration'] === 'yes') {
            $duration = get_post_meta($experience->ID, '_wcefp_duration', true);
            if ($duration) {
                $duration_html = '<span class="wcefp-duration">⏱ ' . esc_html($duration) . '</span>';
            }
        }

        // Get location if available  
        $location_html = '';
        if ($atts['show_location'] === 'yes') {
            $location = get_post_meta($experience->ID, '_wcefp_meeting_point_address', true);
            if ($location) {
                $location_html = '<span class="wcefp-location">📍 ' . esc_html($location) . '</span>';
            }
        }

        // Trust badges and availability
        $trust_badges = $this->get_experience_trust_badges($experience->ID);

        ob_start();
        ?>
        <article class="<?php echo esc_attr($card_classes); ?>" data-experience-id="<?php echo esc_attr($experience->ID); ?>">
            <div class="wcefp-card-inner">
                
                <!-- Hero Image with Trust Badges -->
                <div class="wcefp-card-media">
                    <a href="<?php echo esc_url($permalink); ?>" class="wcefp-card-image-link">
                        <?php echo $thumbnail; ?>
                    </a>
                    
                    <?php if (!empty($trust_badges)): ?>
                    <div class="wcefp-card-badges">
                        <?php echo $trust_badges; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($price_html): ?>
                    <div class="wcefp-card-price">
                        <?php echo $price_html; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Content Section -->
                <div class="wcefp-card-content">
                    <header class="wcefp-card-header">
                        <h3 class="wcefp-card-title">
                            <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
                        </h3>
                        
                        <?php if ($rating_html): ?>
                        <div class="wcefp-card-rating">
                            <?php echo $rating_html; ?>
                        </div>
                        <?php endif; ?>
                    </header>

                    <div class="wcefp-card-meta">
                        <?php echo $duration_html; ?>
                        <?php echo $location_html; ?>
                    </div>

                    <?php if ($excerpt): ?>
                    <div class="wcefp-card-excerpt">
                        <p><?php echo esc_html($excerpt); ?></p>
                    </div>
                    <?php endif; ?>

                    <footer class="wcefp-card-actions">
                        <a href="<?php echo esc_url($permalink); ?>" class="wcefp-btn wcefp-btn-primary">
                            <?php esc_html_e('Scopri di più', 'wceventsfp'); ?>
                        </a>
                    </footer>
                </div>
            </div>
        </article>
        <?php

        return ob_get_clean();
    }

    /**
     * Get trust badges for experience
     * 
     * @param int $experience_id Experience post ID
     * @return string HTML trust badges
     */
    private function get_experience_trust_badges($experience_id) {
        $badges = [];
        
        // Best seller badge
        $is_best_seller = get_post_meta($experience_id, '_wcefp_is_best_seller', true);
        if ($is_best_seller) {
            $badges[] = '<span class="wcefp-badge wcefp-badge-bestseller">🏆 ' . esc_html__('Best Seller', 'wceventsfp') . '</span>';
        }

        // Free cancellation
        $free_cancellation = get_post_meta($experience_id, '_wcefp_free_cancellation', true);
        if ($free_cancellation) {
            $badges[] = '<span class="wcefp-badge wcefp-badge-cancellation">✅ ' . esc_html__('Cancellazione gratuita', 'wceventsfp') . '</span>';
        }

        // Instant confirmation
        $instant_confirmation = get_post_meta($experience_id, '_wcefp_instant_confirmation', true);
        if ($instant_confirmation) {
            $badges[] = '<span class="wcefp-badge wcefp-badge-confirmation">⚡ ' . esc_html__('Conferma immediata', 'wceventsfp') . '</span>';
        }

        // Limited availability
        $stock_status = get_post_meta($experience_id, '_stock_status', true);
        $stock_quantity = get_post_meta($experience_id, '_stock', true);
        if ($stock_status === 'instock' && $stock_quantity && $stock_quantity < 5) {
            $badges[] = '<span class="wcefp-badge wcefp-badge-limited">🔥 ' . sprintf(esc_html__('Solo %d posti rimasti', 'wceventsfp'), $stock_quantity) . '</span>';
        }

        return implode('', $badges);
    }

    /**
     * Get orderby parameter for experiences query
     * 
     * @param string $orderby Orderby parameter
     * @return string WordPress orderby parameter
     */
    private function get_experiences_orderby($orderby) {
        switch ($orderby) {
            case 'popularity':
                return 'meta_value_num';
            case 'rating':
                return 'meta_value_num';
            case 'price':
                return 'meta_value_num';
            case 'title':
                return 'title';
            case 'date':
            default:
                return 'date';
        }
    }

    /**
     * Get location filter options from meeting points
     * 
     * @return string HTML options for location filter
     */
    private function get_location_filter_options() {
        global $wpdb;
        
        $locations = $wpdb->get_results(
            "SELECT DISTINCT meta_value as location 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_wcefp_meeting_point_address' 
             AND meta_value != '' 
             ORDER BY meta_value ASC"
        );
        
        $options = '';
        foreach ($locations as $location) {
            $options .= sprintf(
                '<option value="%s">%s</option>',
                esc_attr($location->location),
                esc_html($location->location)
            );
        }
        
        return $options;
    }

    /**
     * Get star rating HTML for display
     * 
     * @param float $rating Average rating
     * @return string HTML star rating
     */
    private function get_star_rating_html($rating) {
        $full_stars = floor($rating);
        $half_star = ($rating - $full_stars) >= 0.5;
        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
        
        $output = '<div class="wcefp-star-rating" title="' . esc_attr(sprintf(__('%s out of 5 stars', 'wceventsfp'), $rating)) . '">';
        
        for ($i = 0; $i < $full_stars; $i++) {
            $output .= '<span class="wcefp-star wcefp-star-full">★</span>';
        }
        
        if ($half_star) {
            $output .= '<span class="wcefp-star wcefp-star-half">★</span>';
        }
        
        for ($i = 0; $i < $empty_stars; $i++) {
            $output .= '<span class="wcefp-star wcefp-star-empty">☆</span>';
        }
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Get experience page data for GYG-style layout
     * 
     * @param int $experience_id Experience ID
     * @return array Experience data
     */
    private function get_experience_page_data($experience_id) {
        $product = wc_get_product($experience_id);
        
        return [
            'rating' => $product->get_average_rating(),
            'review_count' => $product->get_review_count(),
            'duration' => get_post_meta($experience_id, '_wcefp_duration', true),
            'location' => get_post_meta($experience_id, '_wcefp_meeting_point_address', true),
            'capacity_remaining' => $this->get_capacity_remaining($experience_id),
            'bookings_yesterday' => get_post_meta($experience_id, '_wcefp_bookings_yesterday', true) ?: 0
        ];
    }

    /**
     * Render experience gallery slider
     * 
     * @param int $experience_id Experience ID
     * @return string HTML gallery
     */
    private function render_experience_gallery($experience_id) {
        $gallery_ids = get_post_meta($experience_id, '_product_image_gallery', true);
        $attachment_ids = !empty($gallery_ids) ? explode(',', $gallery_ids) : [];
        
        // Add featured image as first image
        if (has_post_thumbnail($experience_id)) {
            array_unshift($attachment_ids, get_post_thumbnail_id($experience_id));
        }
        
        if (empty($attachment_ids)) {
            return '<div class="wcefp-no-gallery">' . esc_html__('Nessuna immagine disponibile', 'wceventsfp') . '</div>';
        }
        
        ob_start();
        ?>
        <div class="wcefp-gallery-slider" data-gallery-id="<?php echo esc_attr($experience_id); ?>">
            <div class="wcefp-gallery-main">
                <?php foreach ($attachment_ids as $index => $attachment_id): ?>
                    <?php $image = wp_get_attachment_image($attachment_id, 'full', false, ['class' => 'wcefp-gallery-image', 'loading' => 'lazy']); ?>
                    <div class="wcefp-gallery-slide <?php echo $index === 0 ? 'active' : ''; ?>">
                        <?php echo $image; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($attachment_ids) > 1): ?>
            <div class="wcefp-gallery-thumbs">
                <?php foreach ($attachment_ids as $index => $attachment_id): ?>
                    <?php $thumb = wp_get_attachment_image($attachment_id, 'thumbnail', false, ['class' => 'wcefp-gallery-thumb']); ?>
                    <button class="wcefp-gallery-thumb-btn <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo esc_attr($index); ?>">
                        <?php echo $thumb; ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Render experience highlights
     * 
     * @param int $experience_id Experience ID
     * @return string HTML highlights
     */
    private function render_experience_highlights($experience_id) {
        $highlights = get_post_meta($experience_id, '_wcefp_highlights', true);
        
        if (empty($highlights)) {
            return '<p>' . esc_html__('Nessun punto saliente disponibile.', 'wceventsfp') . '</p>';
        }
        
        if (is_string($highlights)) {
            $highlights = array_filter(array_map('trim', explode("\n", $highlights)));
        }
        
        if (empty($highlights)) {
            return '<p>' . esc_html__('Nessun punto saliente disponibile.', 'wceventsfp') . '</p>';
        }
        
        ob_start();
        ?>
        <ul class="wcefp-highlights-list">
            <?php foreach ($highlights as $highlight): ?>
                <li class="wcefp-highlight-item">
                    <span class="wcefp-highlight-icon">✓</span>
                    <span class="wcefp-highlight-text"><?php echo esc_html($highlight); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Render included/not included items
     * 
     * @param int $experience_id Experience ID
     * @param bool $included True for included items, false for not included
     * @return string HTML items list
     */
    private function render_included_items($experience_id, $included = true) {
        $meta_key = $included ? '_wcefp_included_items' : '_wcefp_not_included_items';
        $items = get_post_meta($experience_id, $meta_key, true);
        
        if (empty($items)) {
            $message = $included ? 
                esc_html__('Nessun elemento specificato.', 'wceventsfp') : 
                esc_html__('Nessuna esclusione specificata.', 'wceventsfp');
            return '<p class="wcefp-no-items">' . $message . '</p>';
        }
        
        if (is_string($items)) {
            $items = array_filter(array_map('trim', explode("\n", $items)));
        }
        
        $icon_class = $included ? 'wcefp-included-icon' : 'wcefp-not-included-icon';
        $icon = $included ? '✓' : '✗';
        
        ob_start();
        ?>
        <ul class="wcefp-items-list <?php echo esc_attr($icon_class); ?>">
            <?php foreach ($items as $item): ?>
                <li class="wcefp-item">
                    <span class="wcefp-item-icon"><?php echo $icon; ?></span>
                    <span class="wcefp-item-text"><?php echo esc_html($item); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Render experience itinerary
     * 
     * @param int $experience_id Experience ID
     * @return string HTML itinerary
     */
    private function render_experience_itinerary($experience_id) {
        $itinerary = get_post_meta($experience_id, '_wcefp_itinerary', true);
        
        if (empty($itinerary)) {
            return '<p>' . esc_html__('Nessun itinerario disponibile.', 'wceventsfp') . '</p>';
        }
        
        if (is_string($itinerary)) {
            // Try to parse as JSON first
            $decoded = json_decode($itinerary, true);
            if ($decoded) {
                $itinerary = $decoded;
            } else {
                // Fallback to simple text
                return '<div class="wcefp-itinerary-text">' . wp_kses_post(wpautop($itinerary)) . '</div>';
            }
        }
        
        ob_start();
        ?>
        <div class="wcefp-itinerary-timeline">
            <?php foreach ($itinerary as $index => $step): ?>
                <div class="wcefp-itinerary-step">
                    <div class="wcefp-step-number"><?php echo esc_html($index + 1); ?></div>
                    <div class="wcefp-step-content">
                        <?php if (!empty($step['time'])): ?>
                            <div class="wcefp-step-time"><?php echo esc_html($step['time']); ?></div>
                        <?php endif; ?>
                        <h4 class="wcefp-step-title"><?php echo esc_html($step['title'] ?? 'Step ' . ($index + 1)); ?></h4>
                        <?php if (!empty($step['description'])): ?>
                            <div class="wcefp-step-description"><?php echo wp_kses_post($step['description']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Render meeting point with map
     * 
     * @param int $experience_id Experience ID
     * @return string HTML meeting point
     */
    private function render_meeting_point($experience_id) {
        $address = get_post_meta($experience_id, '_wcefp_meeting_point_address', true);
        $instructions = get_post_meta($experience_id, '_wcefp_meeting_point_instructions', true);
        $lat = get_post_meta($experience_id, '_wcefp_meeting_point_lat', true);
        $lng = get_post_meta($experience_id, '_wcefp_meeting_point_lng', true);
        
        ob_start();
        ?>
        <div class="wcefp-meeting-point">
            <?php if ($address): ?>
                <div class="wcefp-meeting-address">
                    <span class="wcefp-address-icon">📍</span>
                    <span class="wcefp-address-text"><?php echo esc_html($address); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($instructions): ?>
                <div class="wcefp-meeting-instructions">
                    <?php echo wp_kses_post(wpautop($instructions)); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($lat && $lng): ?>
                <div class="wcefp-meeting-map" data-lat="<?php echo esc_attr($lat); ?>" data-lng="<?php echo esc_attr($lng); ?>">
                    <p><?php esc_html_e('Mappa del punto di ritrovo (richiede Google Maps API)', 'wceventsfp'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Get remaining capacity for experience
     * 
     * @param int $experience_id Experience ID
     * @return int Remaining capacity
     */
    private function get_capacity_remaining($experience_id) {
        $product = wc_get_product($experience_id);
        
        if (!$product->managing_stock()) {
            return 999; // Unlimited
        }
        
        return max(0, $product->get_stock_quantity());
    }

    /**
     * Render booking widget v2 for experience page
     * 
     * @param int $experience_id Experience ID
     * @param array $atts Shortcode attributes
     * @return string HTML booking widget
     */
    private function render_booking_widget_v2($experience_id, $atts) {
        $product = wc_get_product($experience_id);
        
        ob_start();
        ?>
        <div class="wcefp-booking-widget-v2" data-experience-id="<?php echo esc_attr($experience_id); ?>">
            <div class="wcefp-widget-header">
                <div class="wcefp-widget-price">
                    <?php echo $product->get_price_html(); ?>
                </div>
                <div class="wcefp-widget-per-person">
                    <?php esc_html_e('per persona', 'wceventsfp'); ?>
                </div>
            </div>
            
            <div class="wcefp-widget-form">
                <!-- Date Selector -->
                <div class="wcefp-form-field">
                    <label><?php esc_html_e('Seleziona data', 'wceventsfp'); ?></label>
                    <input type="date" class="wcefp-date-picker" min="<?php echo esc_attr(date('Y-m-d')); ?>">
                </div>
                
                <!-- Time Slots -->
                <div class="wcefp-form-field wcefp-time-slots" style="display: none;">
                    <label><?php esc_html_e('Orario', 'wceventsfp'); ?></label>
                    <div class="wcefp-time-grid">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>
                
                <!-- Participants -->
                <div class="wcefp-form-field">
                    <label><?php esc_html_e('Partecipanti', 'wceventsfp'); ?></label>
                    <div class="wcefp-quantity-controls">
                        <button type="button" class="wcefp-qty-btn wcefp-qty-minus">-</button>
                        <input type="number" class="wcefp-quantity-input" value="2" min="1" max="20">
                        <button type="button" class="wcefp-qty-btn wcefp-qty-plus">+</button>
                    </div>
                </div>
                
                <!-- Add to Cart / Book Now -->
                <div class="wcefp-widget-actions">
                    <button class="wcefp-book-now-btn" disabled>
                        <span class="wcefp-btn-text"><?php esc_html_e('Prenota ora', 'wceventsfp'); ?></span>
                        <span class="wcefp-btn-loading" style="display: none;">⏳</span>
                    </button>
                </div>
                
                <!-- Trust Elements -->
                <div class="wcefp-widget-trust">
                    <div class="wcefp-trust-item">
                        <span class="wcefp-trust-icon">✅</span>
                        <span><?php esc_html_e('Cancellazione gratuita', 'wceventsfp'); ?></span>
                    </div>
                    <div class="wcefp-trust-item">
                        <span class="wcefp-trust-icon">⚡</span>
                        <span><?php esc_html_e('Conferma immediata', 'wceventsfp'); ?></span>
                    </div>
                    <div class="wcefp-trust-item">
                        <span class="wcefp-trust-icon">🔒</span>
                        <span><?php esc_html_e('Pagamento sicuro', 'wceventsfp'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Render experience reviews section
     * 
     * @param int $experience_id Experience ID
     * @return string HTML reviews
     */
    private function render_experience_reviews($experience_id) {
        $product = wc_get_product($experience_id);
        $reviews = get_comments([
            'post_id' => $experience_id,
            'comment_type' => 'review',
            'status' => 'approve',
            'number' => 5
        ]);
        
        ob_start();
        ?>
        <div class="wcefp-reviews-container">
            <div class="wcefp-reviews-summary">
                <div class="wcefp-rating-overview">
                    <div class="wcefp-rating-score"><?php echo number_format($product->get_average_rating(), 1); ?></div>
                    <div class="wcefp-rating-details">
                        <div class="wcefp-rating-stars"><?php echo $this->get_star_rating_html($product->get_average_rating()); ?></div>
                        <div class="wcefp-rating-count"><?php printf(esc_html__('%d recensioni', 'wceventsfp'), $product->get_review_count()); ?></div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($reviews)): ?>
            <div class="wcefp-reviews-list">
                <?php foreach ($reviews as $review): ?>
                    <div class="wcefp-review-item">
                        <div class="wcefp-review-header">
                            <div class="wcefp-reviewer-name"><?php echo esc_html($review->comment_author); ?></div>
                            <div class="wcefp-review-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($review->comment_date))); ?></div>
                        </div>
                        <div class="wcefp-review-rating">
                            <?php 
                            $rating = get_comment_meta($review->comment_ID, 'rating', true);
                            if ($rating) echo $this->get_star_rating_html($rating);
                            ?>
                        </div>
                        <div class="wcefp-review-content">
                            <?php echo wp_kses_post($review->comment_content); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Render experience FAQ
     * 
     * @param int $experience_id Experience ID
     * @return string HTML FAQ
     */
    private function render_experience_faq($experience_id) {
        $faq = get_post_meta($experience_id, '_wcefp_faq', true);
        
        if (empty($faq)) {
            return '<p>' . esc_html__('Nessuna domanda frequente disponibile.', 'wceventsfp') . '</p>';
        }
        
        if (is_string($faq)) {
            $faq = json_decode($faq, true) ?: [];
        }
        
        if (empty($faq)) {
            return '<p>' . esc_html__('Nessuna domanda frequente disponibile.', 'wceventsfp') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="wcefp-faq-list">
            <?php foreach ($faq as $index => $item): ?>
                <div class="wcefp-faq-item">
                    <button class="wcefp-faq-question" aria-expanded="false" data-target="faq-<?php echo esc_attr($index); ?>">
                        <span><?php echo esc_html($item['question'] ?? ''); ?></span>
                        <span class="wcefp-faq-toggle">+</span>
                    </button>
                    <div class="wcefp-faq-answer" id="faq-<?php echo esc_attr($index); ?>" style="display: none;">
                        <?php echo wp_kses_post($item['answer'] ?? ''); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Render experience policies
     * 
     * @param int $experience_id Experience ID
     * @return string HTML policies
     */
    private function render_experience_policies($experience_id) {
        $policies = get_post_meta($experience_id, '_wcefp_cancellation_policy', true);
        
        if (empty($policies)) {
            $policies = esc_html__('Cancellazione gratuita fino a 24 ore prima dell\'inizio dell\'esperienza.', 'wceventsfp');
        }
        
        ob_start();
        ?>
        <div class="wcefp-policies-content">
            <?php echo wp_kses_post(wpautop($policies)); ?>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Render Schema.org markup for experience
     * 
     * @param int $experience_id Experience ID
     * @return string Schema markup
     */
    private function render_experience_schema($experience_id) {
        $product = wc_get_product($experience_id);
        $experience_data = $this->get_experience_page_data($experience_id);
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->get_name(),
            'description' => wp_strip_all_tags($product->get_description()),
            'sku' => $product->get_sku(),
            'offers' => [
                '@type' => 'Offer',
                'price' => $product->get_price(),
                'priceCurrency' => get_woocommerce_currency(),
                'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'
            ]
        ];
        
        // Add rating if available
        if ($experience_data['rating'] > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $experience_data['rating'],
                'reviewCount' => $experience_data['review_count']
            ];
        }
        
        // Add event properties
        $schema['@type'] = ['Product', 'Event'];
        if (!empty($experience_data['location'])) {
            $schema['location'] = [
                '@type' => 'Place',
                'address' => $experience_data['location']
            ];
        }
        
        return '<script type="application/ld+json">' . wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>';
    }
}