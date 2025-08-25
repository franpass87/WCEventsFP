<?php
/**
 * Experience Single Page Template
 * 
 * Enhanced single experience page with hero section, highlights, 
 * meeting point, policy, and integrated booking widget
 *
 * @package WCEFP\Frontend\Templates
 * @since 2.2.0
 */

namespace WCEFP\Frontend\Templates;

use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Experience Single Page Template class
 */
class ExperienceSingle {
    
    /**
     * Initialize experience single page features
     */
    public static function init() {
        // Hook into WordPress template loading
        add_filter('template_include', [__CLASS__, 'template_include']);
        add_filter('wc_get_template', [__CLASS__, 'override_woocommerce_template'], 10, 3);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        
        // Add custom hooks for experience single page
        add_action('wcefp_experience_hero', [__CLASS__, 'render_hero_section']);
        add_action('wcefp_experience_content', [__CLASS__, 'render_content_sections']);
        add_action('wcefp_experience_sidebar', [__CLASS__, 'render_booking_widget']);
        
        DiagnosticLogger::instance()->debug('Experience Single template initialized', [], 'templates');
    }
    
    /**
     * Include custom template for experience products
     * 
     * @param string $template Template path
     * @return string Modified template path
     */
    public static function template_include($template) {
        if (is_product()) {
            global $post;
            $product = wc_get_product($post->ID);
            
            if ($product && self::is_experience($product)) {
                $custom_template = self::locate_template('single-experience.php');
                if ($custom_template) {
                    return $custom_template;
                }
            }
        }
        
        return $template;
    }
    
    /**
     * Override WooCommerce templates for experiences
     * 
     * @param string $template Template path
     * @param string $template_name Template name
     * @param array $args Template arguments
     * @return string Modified template path
     */
    public static function override_woocommerce_template($template, $template_name, $args) {
        if (is_product() && $template_name === 'single-product.php') {
            global $post;
            $product = wc_get_product($post->ID);
            
            if ($product && self::is_experience($product)) {
                $custom_template = self::locate_template('woocommerce/single-experience.php');
                if ($custom_template) {
                    return $custom_template;
                }
            }
        }
        
        return $template;
    }
    
    /**
     * Locate template file
     * 
     * @param string $template_name Template name
     * @return string|false Template path or false
     */
    private static function locate_template($template_name) {
        // Check theme directory first
        $theme_template = get_stylesheet_directory() . '/wcefp/' . $template_name;
        if (file_exists($theme_template)) {
            return $theme_template;
        }
        
        // Check parent theme directory
        $parent_template = get_template_directory() . '/wcefp/' . $template_name;
        if (file_exists($parent_template)) {
            return $parent_template;
        }
        
        // Check plugin templates directory
        $plugin_template = WCEFP_PLUGIN_DIR . 'templates/' . $template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return false;
    }
    
    /**
     * Check if product is an experience
     * 
     * @param \WC_Product $product Product object
     * @return bool True if experience
     */
    public static function is_experience($product) {
        return in_array($product->get_type(), ['experience', 'esperienza']) ||
               get_post_meta($product->get_id(), '_wcefp_is_experience', true) === '1';
    }
    
    /**
     * Enqueue assets for experience single page
     */
    public static function enqueue_assets() {
        if (is_product()) {
            global $post;
            $product = wc_get_product($post->ID);
            
            if ($product && self::is_experience($product)) {
                wp_enqueue_style(
                    'wcefp-single',
                    WCEFP_PLUGIN_URL . 'assets/fe/single.css',
                    ['woocommerce-general'],
                    WCEFP_VERSION
                );
                
                wp_enqueue_script(
                    'wcefp-single',
                    WCEFP_PLUGIN_URL . 'assets/fe/single.js',
                    ['jquery', 'wc-single-product'],
                    WCEFP_VERSION,
                    true
                );
                
                wp_localize_script('wcefp-single', 'wcefp_single', [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wcefp_single_nonce'),
                    'product_id' => $product->get_id(),
                    'strings' => [
                        'loading' => __('Loading...', 'wceventsfp'),
                        'select_date' => __('Please select a date', 'wceventsfp'),
                        'select_time' => __('Please select a time', 'wceventsfp'),
                        'add_to_cart' => __('Add to Cart', 'wceventsfp'),
                        'book_now' => __('Book Now', 'wceventsfp'),
                        'sold_out' => __('Sold Out', 'wceventsfp'),
                        'price_from' => __('From', 'wceventsfp'),
                        'per_person' => __('per person', 'wceventsfp'),
                    ]
                ]);
            }
        }
    }
    
    /**
     * Render experience hero section
     */
    public static function render_hero_section() {
        global $post, $product;
        
        if (!$product) {
            $product = wc_get_product($post->ID);
        }
        
        if (!$product || !self::is_experience($product)) {
            return;
        }
        
        $gallery_ids = $product->get_gallery_image_ids();
        $featured_image = get_the_post_thumbnail_url($product->get_id(), 'full');
        $images = [];
        
        // Add featured image
        if ($featured_image) {
            $images[] = $featured_image;
        }
        
        // Add gallery images
        foreach ($gallery_ids as $image_id) {
            $images[] = wp_get_attachment_url($image_id);
        }
        
        // Experience metadata
        $location = get_post_meta($product->get_id(), '_wcefp_location', true);
        $duration = get_post_meta($product->get_id(), '_wcefp_duration', true);
        $difficulty = get_post_meta($product->get_id(), '_wcefp_difficulty_level', true);
        $rating = $product->get_average_rating();
        $review_count = $product->get_review_count();
        
        ?>
        <section class="wcefp-experience-hero">
            <div class="wcefp-hero-gallery">
                <?php if (!empty($images)): ?>
                    <div class="wcefp-gallery-main">
                        <img src="<?php echo esc_url($images[0]); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" class="wcefp-hero-image">
                        <?php if (count($images) > 1): ?>
                            <button type="button" class="wcefp-gallery-trigger" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <rect x="3" y="3" width="7" height="7" stroke="currentColor" stroke-width="1.5"/>
                                    <rect x="14" y="3" width="7" height="7" stroke="currentColor" stroke-width="1.5"/>
                                    <rect x="3" y="14" width="7" height="7" stroke="currentColor" stroke-width="1.5"/>
                                    <rect x="14" y="14" width="7" height="7" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                                <?php echo sprintf(__('View all %d photos', 'wceventsfp'), count($images)); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($images) > 1): ?>
                        <div class="wcefp-gallery-thumbs">
                            <?php for ($i = 1; $i < min(5, count($images)); $i++): ?>
                                <div class="wcefp-thumb">
                                    <img src="<?php echo esc_url($images[$i]); ?>" alt="<?php echo esc_attr($product->get_name() . ' - ' . ($i + 1)); ?>">
                                    <?php if ($i === 4 && count($images) > 5): ?>
                                        <div class="wcefp-thumb-overlay">
                                            +<?php echo (count($images) - 5); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="wcefp-hero-placeholder">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="1.5"/>
                            <circle cx="8.5" cy="8.5" r="1.5" stroke="currentColor" stroke-width="1.5"/>
                            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" stroke="currentColor" stroke-width="1.5"/>
                        </svg>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="wcefp-hero-content">
                <div class="wcefp-hero-header">
                    <?php if ($location): ?>
                        <div class="wcefp-hero-location">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" stroke="currentColor" stroke-width="1.5"/>
                                <circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <span><?php echo esc_html($location); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($rating > 0): ?>
                        <div class="wcefp-hero-rating">
                            <div class="wcefp-rating-stars">
                                <?php echo self::render_star_rating($rating); ?>
                            </div>
                            <span class="wcefp-rating-text">
                                <?php echo number_format($rating, 1); ?>
                                <?php if ($review_count > 0): ?>
                                    (<?php echo sprintf(_n('%d review', '%d reviews', $review_count, 'wceventsfp'), $review_count); ?>)
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h1 class="wcefp-hero-title"><?php echo esc_html($product->get_name()); ?></h1>
                
                <div class="wcefp-hero-meta">
                    <?php if ($duration): ?>
                        <div class="wcefp-meta-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <span><?php echo esc_html(self::format_duration($duration)); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($difficulty): ?>
                        <div class="wcefp-meta-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <span><?php echo esc_html(self::get_difficulty_label($difficulty)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="wcefp-hero-price">
                    <?php echo $product->get_price_html(); ?>
                </div>
            </div>
        </section>
        <?php
    }
    
    /**
     * Render main content sections
     */
    public static function render_content_sections() {
        global $post, $product;
        
        if (!$product) {
            $product = wc_get_product($post->ID);
        }
        
        if (!$product || !self::is_experience($product)) {
            return;
        }
        
        ?>
        <div class="wcefp-experience-content">
            
            <!-- Description Section -->
            <section class="wcefp-section wcefp-description">
                <h2><?php esc_html_e('About this experience', 'wceventsfp'); ?></h2>
                <div class="wcefp-content">
                    <?php echo wpautop($product->get_description()); ?>
                </div>
            </section>
            
            <!-- Highlights Section -->
            <?php
            $highlights = get_post_meta($product->get_id(), '_wcefp_highlights', true);
            if (!empty($highlights)):
            ?>
            <section class="wcefp-section wcefp-highlights">
                <h2><?php esc_html_e('Highlights', 'wceventsfp'); ?></h2>
                <ul class="wcefp-highlights-list">
                    <?php foreach ($highlights as $highlight): ?>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <polyline points="20,6 9,17 4,12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php echo esc_html($highlight); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>
            
            <!-- What's Included Section -->
            <?php
            $included = get_post_meta($product->get_id(), '_wcefp_included', true);
            $not_included = get_post_meta($product->get_id(), '_wcefp_not_included', true);
            if (!empty($included) || !empty($not_included)):
            ?>
            <section class="wcefp-section wcefp-included">
                <h2><?php esc_html_e("What's included", 'wceventsfp'); ?></h2>
                
                <?php if (!empty($included)): ?>
                    <div class="wcefp-included-list">
                        <h3><?php esc_html_e('Included', 'wceventsfp'); ?></h3>
                        <ul>
                            <?php foreach ($included as $item): ?>
                                <li>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="wcefp-icon-check">
                                        <polyline points="20,6 9,17 4,12" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    <?php echo esc_html($item); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($not_included)): ?>
                    <div class="wcefp-not-included-list">
                        <h3><?php esc_html_e('Not included', 'wceventsfp'); ?></h3>
                        <ul>
                            <?php foreach ($not_included as $item): ?>
                                <li>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="wcefp-icon-x">
                                        <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2"/>
                                        <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                    <?php echo esc_html($item); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            
            <!-- Meeting Point Section -->
            <?php
            $meeting_point = get_post_meta($product->get_id(), '_wcefp_meeting_point', true);
            $meeting_instructions = get_post_meta($product->get_id(), '_wcefp_meeting_instructions', true);
            if (!empty($meeting_point) || !empty($meeting_instructions)):
            ?>
            <section class="wcefp-section wcefp-meeting-point">
                <h2><?php esc_html_e('Meeting point', 'wceventsfp'); ?></h2>
                
                <?php if (!empty($meeting_point)): ?>
                    <div class="wcefp-meeting-location">
                        <div class="wcefp-location-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" stroke="currentColor" stroke-width="1.5"/>
                                <circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                        </div>
                        <div class="wcefp-location-details">
                            <h3><?php esc_html_e('Address', 'wceventsfp'); ?></h3>
                            <p><?php echo esc_html($meeting_point); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($meeting_instructions)): ?>
                    <div class="wcefp-meeting-instructions">
                        <h3><?php esc_html_e('Instructions', 'wceventsfp'); ?></h3>
                        <div class="wcefp-content">
                            <?php echo wpautop($meeting_instructions); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            
            <!-- Policies Section -->
            <?php
            $cancellation_policy = get_post_meta($product->get_id(), '_wcefp_cancellation_policy', true);
            $terms_conditions = get_post_meta($product->get_id(), '_wcefp_terms_conditions', true);
            if (!empty($cancellation_policy) || !empty($terms_conditions)):
            ?>
            <section class="wcefp-section wcefp-policies">
                <h2><?php esc_html_e('Important information', 'wceventsfp'); ?></h2>
                
                <?php if (!empty($cancellation_policy)): ?>
                    <div class="wcefp-policy-item">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
                                <path d="m15 9-6 6" stroke="currentColor" stroke-width="1.5"/>
                                <path d="m9 9 6 6" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <?php esc_html_e('Cancellation policy', 'wceventsfp'); ?>
                        </h3>
                        <div class="wcefp-content">
                            <?php echo wpautop($cancellation_policy); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($terms_conditions)): ?>
                    <div class="wcefp-policy-item">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="1.5"/>
                                <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <?php esc_html_e('Terms & Conditions', 'wceventsfp'); ?>
                        </h3>
                        <div class="wcefp-content">
                            <?php echo wpautop($terms_conditions); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            
            <!-- Reviews Section -->
            <?php if ($product->get_review_count() > 0): ?>
            <section class="wcefp-section wcefp-reviews">
                <h2>
                    <?php echo sprintf(_n('Reviews (%d)', 'Reviews (%d)', $product->get_review_count(), 'wceventsfp'), $product->get_review_count()); ?>
                </h2>
                
                <div class="wcefp-reviews-summary">
                    <div class="wcefp-rating-large">
                        <span class="wcefp-rating-score"><?php echo number_format($product->get_average_rating(), 1); ?></span>
                        <div class="wcefp-rating-stars">
                            <?php echo self::render_star_rating($product->get_average_rating()); ?>
                        </div>
                        <span class="wcefp-rating-count">
                            <?php echo sprintf(_n('%d review', '%d reviews', $product->get_review_count(), 'wceventsfp'), $product->get_review_count()); ?>
                        </span>
                    </div>
                </div>
                
                <div class="wcefp-reviews-list">
                    <?php
                    // Get recent reviews
                    $reviews = get_comments([
                        'post_id' => $product->get_id(),
                        'status' => 'approve',
                        'type' => 'review',
                        'number' => 5,
                        'orderby' => 'comment_date_gmt',
                        'order' => 'DESC'
                    ]);
                    
                    foreach ($reviews as $review):
                        $rating = get_comment_meta($review->comment_ID, 'rating', true);
                    ?>
                        <div class="wcefp-review-item">
                            <div class="wcefp-review-header">
                                <div class="wcefp-reviewer-info">
                                    <span class="wcefp-reviewer-name"><?php echo esc_html($review->comment_author); ?></span>
                                    <span class="wcefp-review-date"><?php echo esc_html(human_time_diff(strtotime($review->comment_date), current_time('timestamp')) . ' ago'); ?></span>
                                </div>
                                <?php if ($rating): ?>
                                    <div class="wcefp-review-rating">
                                        <?php echo self::render_star_rating($rating); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="wcefp-review-content">
                                <?php echo wpautop($review->comment_content); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($product->get_review_count() > 5): ?>
                    <button type="button" class="wcefp-btn wcefp-btn-outline wcefp-show-all-reviews">
                        <?php esc_html_e('Show all reviews', 'wceventsfp'); ?>
                    </button>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    /**
     * Render booking widget sidebar
     */
    public static function render_booking_widget() {
        global $post, $product;
        
        if (!$product) {
            $product = wc_get_product($post->ID);
        }
        
        if (!$product || !self::is_experience($product)) {
            return;
        }
        
        // Use BookingWidgetV2 if available, fallback to basic implementation
        if (class_exists('\WCEFP\Frontend\BookingWidgetV2')) {
            $widget = new \WCEFP\Frontend\BookingWidgetV2();
            echo $widget->render($product->get_id());
            return;
        }
        
        // Basic booking widget implementation
        ?>
        <div class="wcefp-booking-widget">
            <div class="wcefp-widget-header">
                <div class="wcefp-price-display">
                    <?php echo $product->get_price_html(); ?>
                </div>
            </div>
            
            <form class="wcefp-booking-form" method="post" action="<?php echo esc_url(wc_get_cart_url()); ?>">
                <div class="wcefp-form-group">
                    <label for="wcefp-booking-date"><?php esc_html_e('Select Date', 'wceventsfp'); ?></label>
                    <input type="date" id="wcefp-booking-date" name="booking_date" required 
                           min="<?php echo date('Y-m-d'); ?>" class="wcefp-form-control">
                </div>
                
                <div class="wcefp-form-group">
                    <label for="wcefp-booking-time"><?php esc_html_e('Select Time', 'wceventsfp'); ?></label>
                    <select id="wcefp-booking-time" name="booking_time" required class="wcefp-form-control">
                        <option value=""><?php esc_html_e('Choose time...', 'wceventsfp'); ?></option>
                        <option value="09:00">09:00</option>
                        <option value="14:00">14:00</option>
                        <option value="16:00">16:00</option>
                    </select>
                </div>
                
                <div class="wcefp-form-group">
                    <label for="wcefp-participants"><?php esc_html_e('Participants', 'wceventsfp'); ?></label>
                    <div class="wcefp-quantity-selector">
                        <button type="button" class="wcefp-qty-minus" data-target="wcefp-participants">-</button>
                        <input type="number" id="wcefp-participants" name="participants" value="2" min="1" max="10" class="wcefp-form-control">
                        <button type="button" class="wcefp-qty-plus" data-target="wcefp-participants">+</button>
                    </div>
                </div>
                
                <div class="wcefp-form-group">
                    <button type="submit" class="wcefp-btn wcefp-btn-primary wcefp-btn-block">
                        <?php esc_html_e('Book Now', 'wceventsfp'); ?>
                    </button>
                </div>
                
                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>">
                <?php wp_nonce_field('wcefp_booking_nonce', 'wcefp_nonce'); ?>
            </form>
            
            <div class="wcefp-widget-footer">
                <ul class="wcefp-features-list">
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <polyline points="20,6 9,17 4,12" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <?php esc_html_e('Free cancellation', 'wceventsfp'); ?>
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <polyline points="20,6 9,17 4,12" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <?php esc_html_e('Instant confirmation', 'wceventsfp'); ?>
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <polyline points="20,6 9,17 4,12" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <?php esc_html_e('Mobile tickets', 'wceventsfp'); ?>
                    </li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Format duration in minutes to human readable format
     * 
     * @param int $minutes Duration in minutes
     * @return string Formatted duration
     */
    private static function format_duration($minutes) {
        $minutes = intval($minutes);
        
        if ($minutes < 60) {
            return sprintf(_n('%d min', '%d mins', $minutes, 'wceventsfp'), $minutes);
        }
        
        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;
        
        if ($remaining_minutes === 0) {
            return sprintf(_n('%d hour', '%d hours', $hours, 'wceventsfp'), $hours);
        }
        
        return sprintf(__('%d h %d min', 'wceventsfp'), $hours, $remaining_minutes);
    }
    
    /**
     * Get difficulty level label
     * 
     * @param string $level Difficulty level
     * @return string Label
     */
    private static function get_difficulty_label($level) {
        $labels = [
            'easy' => __('Easy', 'wceventsfp'),
            'moderate' => __('Moderate', 'wceventsfp'),
            'challenging' => __('Challenging', 'wceventsfp'),
            'difficult' => __('Difficult', 'wceventsfp')
        ];
        
        return $labels[$level] ?? ucfirst($level);
    }
    
    /**
     * Render star rating HTML
     * 
     * @param float $rating Rating value (0-5)
     * @return string HTML output
     */
    private static function render_star_rating($rating) {
        $rating = floatval($rating);
        $stars = '';
        
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $stars .= '<svg class="wcefp-star filled" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>';
            } elseif ($i - 0.5 <= $rating) {
                $stars .= '<svg class="wcefp-star half" width="16" height="16" viewBox="0 0 24 24"><defs><linearGradient id="half-fill-' . uniqid() . '"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="transparent"/></linearGradient></defs><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" fill="url(#half-fill)" stroke="currentColor" stroke-width="1"/></svg>';
            } else {
                $stars .= '<svg class="wcefp-star empty" width="16" height="16" viewBox="0 0 24 24" fill="none"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="1"/></svg>';
            }
        }
        
        return $stars;
    }
}