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
        add_shortcode('wcefp_event', [$this, 'single_event_shortcode']);
        add_shortcode('wcefp_booking_form', [$this, 'booking_form_shortcode']);
        add_shortcode('wcefp_search', [$this, 'search_events_shortcode']);
        
        // Widget-style shortcodes
        add_shortcode('wcefp_featured_events', [$this, 'featured_events_shortcode']);
        add_shortcode('wcefp_upcoming_events', [$this, 'upcoming_events_shortcode']);
        add_shortcode('wcefp_event_calendar', [$this, 'event_calendar_shortcode']);
        
        // User account shortcodes
        add_shortcode('wcefp_user_bookings', [$this, 'user_bookings_shortcode']);
        add_shortcode('wcefp_booking_status', [$this, 'booking_status_shortcode']);
        
        // Integration shortcodes
        add_shortcode('wcefp_google_reviews', [$this, 'google_reviews_shortcode']);
        add_shortcode('wcefp_conversion_optimizer', [$this, 'conversion_optimizer_shortcode']);
        
        // Experience-specific shortcodes
        add_shortcode('wcefp_experience_card', [$this, 'experience_card_shortcode']);
        
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
               has_shortcode($content, 'wcefp_event') ||
               has_shortcode($content, 'wcefp_booking_form') ||
               has_shortcode($content, 'wcefp_search') ||
               has_shortcode($content, 'wcefp_featured_events') ||
               has_shortcode($content, 'wcefp_upcoming_events') ||
               has_shortcode($content, 'wcefp_event_calendar') ||
               has_shortcode($content, 'wcefp_user_bookings') ||
               has_shortcode($content, 'wcefp_google_reviews') ||
               has_shortcode($content, 'wcefp_experience_card') ||
               has_shortcode($content, 'wcefp_experiences_archive');
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
     * Experience card shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function experience_card_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'show_image' => 'yes',
            'show_price' => 'yes',
            'show_excerpt' => 'yes',
            'show_rating' => 'yes',
            'show_duration' => 'yes',
            'show_difficulty' => 'yes',
            'image_size' => 'woocommerce_thumbnail',
            'class' => ''
        ], $atts, 'wcefp_experience_card');
        
        $product_id = intval($atts['id']);
        
        // Auto-detect product ID on single product pages
        if (!$product_id && is_product()) {
            global $post;
            $product_id = $post->ID;
        }
        
        if (!$product_id) {
            return '<div class="wcefp-error">' . __('Experience ID is required.', 'wceventsfp') . '</div>';
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product || get_post_meta($product_id, '_wcefp_is_experience', true) !== '1') {
            return '<div class="wcefp-error">' . __('Product is not a valid experience.', 'wceventsfp') . '</div>';
        }
        
        $css_class = 'wcefp-experience-card wcefp-single-card';
        if (!empty($atts['class'])) {
            $css_class .= ' ' . sanitize_html_class($atts['class']);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($css_class); ?>" data-product-id="<?php echo esc_attr($product_id); ?>">
            <?php if ($atts['show_image'] === 'yes'): ?>
                <div class="wcefp-card-image">
                    <a href="<?php echo esc_url(get_permalink($product_id)); ?>" 
                       aria-label="<?php printf(__('View %s', 'wceventsfp'), get_the_title($product_id)); ?>">
                        <?php
                        if (has_post_thumbnail($product_id)) {
                            echo get_the_post_thumbnail($product_id, $atts['image_size'], [
                                'alt' => get_the_title($product_id),
                                'loading' => 'lazy'
                            ]);
                        } else {
                            echo '<div class="wcefp-no-image">' . __('No image available', 'wceventsfp') . '</div>';
                        }
                        ?>
                    </a>
                    
                    <?php
                    // Show badges
                    $badges = [];
                    
                    if (get_post_meta($product_id, '_wcefp_is_bestseller', true) === 'yes') {
                        $badges[] = '<span class="wcefp-badge wcefp-badge-bestseller">' . __('Bestseller', 'wceventsfp') . '</span>';
                    }
                    
                    if ($product->is_on_sale()) {
                        $badges[] = '<span class="wcefp-badge wcefp-badge-sale">' . __('On Sale', 'wceventsfp') . '</span>';
                    }
                    
                    // New product badge (within 30 days)
                    $post_date = get_the_date('U', $product_id);
                    $days_old = (current_time('timestamp') - $post_date) / DAY_IN_SECONDS;
                    if ($days_old <= 30) {
                        $badges[] = '<span class="wcefp-badge wcefp-badge-new">' . __('New', 'wceventsfp') . '</span>';
                    }
                    
                    if (!empty($badges)):
                    ?>
                        <div class="wcefp-card-badges">
                            <?php echo implode('', $badges); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="wcefp-card-content">
                <h3 class="wcefp-card-title">
                    <a href="<?php echo esc_url(get_permalink($product_id)); ?>">
                        <?php echo esc_html(get_the_title($product_id)); ?>
                    </a>
                </h3>
                
                <?php if ($atts['show_excerpt'] === 'yes' && get_the_excerpt($product_id)): ?>
                    <div class="wcefp-card-excerpt">
                        <?php echo wp_kses_post(get_the_excerpt($product_id)); ?>
                    </div>
                <?php endif; ?>
                
                <div class="wcefp-card-meta">
                    <?php if ($atts['show_price'] === 'yes'): ?>
                        <div class="wcefp-card-price">
                            <?php echo $product->get_price_html(); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_duration'] === 'yes'): ?>
                        <?php 
                        $duration = get_post_meta($product_id, '_wcefp_duration', true);
                        if ($duration):
                        ?>
                            <div class="wcefp-card-duration">
                                <span class="wcefp-duration-icon" aria-hidden="true">🕐</span>
                                <span><?php echo esc_html($duration); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_rating'] === 'yes' && $product->get_average_rating()): ?>
                        <div class="wcefp-card-rating">
                            <?php echo wc_get_rating_html($product->get_average_rating()); ?>
                            <span class="wcefp-rating-count">
                                (<?php echo $product->get_review_count(); ?>)
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_difficulty'] === 'yes'): ?>
                        <?php 
                        $difficulty = get_post_meta($product_id, '_wcefp_difficulty_level', true);
                        if ($difficulty):
                            $difficulty_levels = [
                                'easy' => __('Easy', 'wceventsfp'),
                                'moderate' => __('Moderate', 'wceventsfp'),
                                'hard' => __('Hard', 'wceventsfp'),
                                'expert' => __('Expert', 'wceventsfp')
                            ];
                            $difficulty_label = $difficulty_levels[$difficulty] ?? $difficulty;
                        ?>
                            <div class="wcefp-card-difficulty">
                                <span class="wcefp-difficulty-icon" aria-hidden="true">⭐</span>
                                <span><?php echo esc_html($difficulty_label); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="wcefp-card-actions">
                    <a href="<?php echo esc_url(get_permalink($product_id)); ?>" 
                       class="wcefp-view-experience-btn">
                        <?php _e('Learn More', 'wceventsfp'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    // Additional shortcode methods would continue here...
    // Including featured_events_shortcode, upcoming_events_shortcode, user_bookings_shortcode, etc.
}