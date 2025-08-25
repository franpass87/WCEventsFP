<?php
/**
 * Experience Single Shortcode
 * 
 * Allows embedding a single experience anywhere via [wcefp_experience id="123"]
 *
 * @package WCEFP\Frontend\Shortcodes
 * @since 2.2.0
 */

namespace WCEFP\Frontend\Shortcodes;

use WCEFP\Frontend\Templates\ExperienceSingle;
use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Experience Single Shortcode class
 */
class ExperienceSingleShortcode {
    
    /**
     * Initialize shortcode
     */
    public static function init() {
        add_shortcode('wcefp_experience', [__CLASS__, 'render']);
        add_shortcode('wcefp_experience_card', [__CLASS__, 'render_card']);
        
        DiagnosticLogger::instance()->debug('Experience Single shortcode initialized', [], 'shortcodes');
    }
    
    /**
     * Render single experience shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'show_hero' => true,
            'show_booking' => true,
            'show_description' => true,
            'show_highlights' => true,
            'show_included' => true,
            'show_meeting_point' => true,
            'show_policies' => true,
            'show_reviews' => true,
            'layout' => 'full', // full, compact, card
            'class' => ''
        ], $atts);
        
        $product_id = intval($atts['id']);
        if (!$product_id) {
            return '<div class="wcefp-error">' . esc_html__('Experience ID is required.', 'wceventsfp') . '</div>';
        }
        
        $experience_product = wc_get_product($product_id);
        if (!$experience_product || !ExperienceSingle::is_experience($experience_product)) {
            return '<div class="wcefp-error">' . esc_html__('Experience not found.', 'wceventsfp') . '</div>';
        }
        
        // Set global product for template functions
        global $post, $product;
        $original_product = $product;
        $product = $experience_product;
        
        ob_start();
        
        $css_class = 'wcefp-experience-shortcode layout-' . sanitize_html_class($atts['layout']);
        if (!empty($atts['class'])) {
            $css_class .= ' ' . sanitize_html_class($atts['class']);
        }
        
        ?>
        <div class="<?php echo esc_attr($css_class); ?>" data-product-id="<?php echo esc_attr($product_id); ?>">
            
            <?php if ($atts['show_hero']): ?>
                <div class="wcefp-shortcode-hero">
                    <?php ExperienceSingle::render_hero_section(); ?>
                </div>
            <?php endif; ?>
            
            <div class="wcefp-shortcode-content">
                <div class="wcefp-shortcode-main">
                    
                    <?php if ($atts['show_description'] || $atts['show_highlights'] || $atts['show_included'] || $atts['show_meeting_point'] || $atts['show_policies'] || $atts['show_reviews']): ?>
                        <div class="wcefp-content-sections">
                            
                            <?php if ($atts['show_description']): ?>
                                <section class="wcefp-section wcefp-description">
                                    <h2><?php esc_html_e('About this experience', 'wceventsfp'); ?></h2>
                                    <div class="wcefp-content">
                                        <?php echo wpautop($product->get_description()); ?>
                                    </div>
                                </section>
                            <?php endif; ?>
                            
                            <?php if ($atts['show_highlights']): ?>
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
                                                    <polyline points="20,6 9,17 4,12" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                                <?php echo esc_html($highlight); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </section>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($atts['show_included']): ?>
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
                            <?php endif; ?>
                            
                        </div>
                    <?php endif; ?>
                    
                </div>
                
                <?php if ($atts['show_booking']): ?>
                    <div class="wcefp-shortcode-sidebar">
                        <?php ExperienceSingle::render_booking_widget(); ?>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
        <?php
        
        // Restore original product
        $product = $original_product;
        
        return ob_get_clean();
    }
    
    /**
     * Render experience card shortcode (compact version)
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_card($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'show_image' => true,
            'show_rating' => true,
            'show_price' => true,
            'show_location' => true,
            'show_duration' => true,
            'show_button' => true,
            'button_text' => __('View Details', 'wceventsfp'),
            'target' => '_self',
            'class' => ''
        ], $atts);
        
        $product_id = intval($atts['id']);
        if (!$product_id) {
            return '<div class="wcefp-error">' . esc_html__('Experience ID is required.', 'wceventsfp') . '</div>';
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return '<div class="wcefp-error">' . esc_html__('Experience not found.', 'wceventsfp') . '</div>';
        }
        
        $permalink = get_permalink($product_id);
        $thumbnail = get_the_post_thumbnail($product_id, 'medium');
        $rating = $product->get_average_rating();
        $review_count = $product->get_review_count();
        $location = get_post_meta($product_id, '_wcefp_location', true);
        $duration = get_post_meta($product_id, '_wcefp_duration', true);
        
        $css_class = 'wcefp-experience-card-shortcode';
        if (!empty($atts['class'])) {
            $css_class .= ' ' . sanitize_html_class($atts['class']);
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($css_class); ?>">
            
            <?php if ($atts['show_image'] && $thumbnail): ?>
                <div class="wcefp-card-image">
                    <a href="<?php echo esc_url($permalink); ?>" target="<?php echo esc_attr($atts['target']); ?>">
                        <?php echo $thumbnail; ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="wcefp-card-content">
                
                <div class="wcefp-card-header">
                    <?php if ($atts['show_location'] && $location): ?>
                        <div class="wcefp-location">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" stroke="currentColor" stroke-width="1.5"/>
                                <circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <span><?php echo esc_html($location); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_rating'] && $rating > 0): ?>
                        <div class="wcefp-rating">
                            <div class="wcefp-stars">
                                <?php echo self::render_star_rating($rating); ?>
                            </div>
                            <?php if ($review_count > 0): ?>
                                <span class="wcefp-review-count">(<?php echo esc_html($review_count); ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h3 class="wcefp-card-title">
                    <a href="<?php echo esc_url($permalink); ?>" target="<?php echo esc_attr($atts['target']); ?>">
                        <?php echo esc_html($product->get_name()); ?>
                    </a>
                </h3>
                
                <div class="wcefp-card-excerpt">
                    <?php echo wp_trim_words($product->get_short_description(), 15, '...'); ?>
                </div>
                
                <?php if ($atts['show_duration'] && $duration): ?>
                    <div class="wcefp-card-meta">
                        <div class="wcefp-meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <span><?php echo esc_html(self::format_duration($duration)); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="wcefp-card-footer">
                    <?php if ($atts['show_price']): ?>
                        <div class="wcefp-price">
                            <?php echo $product->get_price_html(); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_button']): ?>
                        <a href="<?php echo esc_url($permalink); ?>" 
                           target="<?php echo esc_attr($atts['target']); ?>"
                           class="wcefp-btn wcefp-btn-primary wcefp-btn-sm">
                            <?php echo esc_html($atts['button_text']); ?>
                        </a>
                    <?php endif; ?>
                </div>
                
            </div>
            
        </div>
        <?php
        
        return ob_get_clean();
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
                $stars .= '<svg class="wcefp-star filled" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>';
            } elseif ($i - 0.5 <= $rating) {
                $stars .= '<svg class="wcefp-star half" width="12" height="12" viewBox="0 0 24 24"><defs><linearGradient id="half-fill-' . uniqid() . '"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="transparent"/></linearGradient></defs><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" fill="url(#half-fill)" stroke="currentColor" stroke-width="1"/></svg>';
            } else {
                $stars .= '<svg class="wcefp-star empty" width="12" height="12" viewBox="0 0 24 24" fill="none"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="1"/></svg>';
            }
        }
        
        return $stars;
    }
}