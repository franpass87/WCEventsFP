<?php
/**
 * Enhanced Google Reviews Manager
 * 
 * Improved Google Reviews integration with better caching, Place ID management,
 * and meeting point integration for booking widgets
 * 
 * @package WCEFP
 * @subpackage Frontend
 * @since 2.2.0
 */

namespace WCEFP\Frontend;

use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced Google Reviews Manager Class
 * 
 * Handles Google Reviews display with improved caching and error handling
 */
class GoogleReviewsManager {
    
    /**
     * Cache duration in seconds (4 hours)
     */
    const CACHE_DURATION = 4 * HOUR_IN_SECONDS;
    
    /**
     * API rate limit cache duration (1 hour)
     */
    const RATE_LIMIT_CACHE = HOUR_IN_SECONDS;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize hooks
     * 
     * @return void
     */
    private function init() {
        // Enhanced shortcode
        add_shortcode('wcefp_google_reviews_v2', [$this, 'google_reviews_shortcode']);
        
        // AJAX handlers for dynamic loading
        add_action('wp_ajax_wcefp_load_reviews', [$this, 'ajax_load_reviews']);
        add_action('wp_ajax_nopriv_wcefp_load_reviews', [$this, 'ajax_load_reviews']);
        
        // Admin settings
        add_action('admin_init', [$this, 'register_admin_settings']);
        
        // Cleanup expired cache
        add_action('wp_scheduled_delete', [$this, 'cleanup_expired_cache']);
        
        // Enqueue scripts conditionally
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_scripts']);
    }
    
    /**
     * Enhanced Google Reviews shortcode
     * 
     * @param array $atts Shortcode attributes
     * @param string $content Shortcode content
     * @return string
     */
    public function google_reviews_shortcode($atts, $content = '') {
        $atts = shortcode_atts([
            'place_id' => '',
            'limit' => 5,
            'show_rating' => 'yes',
            'show_avatar' => 'yes',
            'show_date' => 'yes',
            'min_rating' => 1,
            'style' => 'cards',
            'layout' => 'grid',
            'class' => '',
            'loading' => 'lazy',
            'show_overall' => 'yes',
            'show_attribution' => 'yes',
            'cache_duration' => self::CACHE_DURATION
        ], $atts, 'wcefp_google_reviews_v2');
        
        // Get Place ID (from shortcode, options, or meeting point)
        $place_id = $this->get_place_id($atts['place_id']);
        
        if (empty($place_id)) {
            if (current_user_can('manage_options')) {
                return '<div class="wcefp-reviews-error">' . 
                       esc_html__('Google Reviews: No Place ID configured. Please check plugin settings.', 'wceventsfp') . 
                       '</div>';
            }
            return '';
        }
        
        // Check if API key is configured
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            if (current_user_can('manage_options')) {
                return '<div class="wcefp-reviews-error">' . 
                       esc_html__('Google Reviews: No API key configured. Please check plugin settings.', 'wceventsfp') . 
                       '</div>';
            }
            return $this->render_fallback_reviews($atts);
        }
        
        // Get reviews data
        $reviews_data = $this->get_reviews_data($place_id, $api_key, $atts);
        
        if (empty($reviews_data)) {
            return $this->render_fallback_reviews($atts);
        }
        
        // Render reviews
        return $this->render_reviews($reviews_data, $atts);
    }
    
    /**
     * Get Place ID from various sources
     * 
     * @param string $place_id_param Place ID from shortcode
     * @return string
     */
    private function get_place_id($place_id_param = '') {
        // Priority: shortcode > product meta > global setting
        if (!empty($place_id_param)) {
            return sanitize_text_field($place_id_param);
        }
        
        // Try to get from current product's meeting point
        if (is_product()) {
            global $post;
            $meeting_point = get_post_meta($post->ID, '_wcefp_meeting_point', true);
            if (!empty($meeting_point['place_id'])) {
                return sanitize_text_field($meeting_point['place_id']);
            }
        }
        
        // Fall back to global setting
        return get_option('wcefp_google_place_id', '');
    }
    
    /**
     * Get API key
     * 
     * @return string
     */
    private function get_api_key() {
        return get_option('wcefp_google_places_api_key', '');
    }
    
    /**
     * Get reviews data with caching
     * 
     * @param string $place_id Place ID
     * @param string $api_key API key
     * @param array $atts Shortcode attributes
     * @return array|false
     */
    private function get_reviews_data($place_id, $api_key, $atts) {
        $cache_key = 'wcefp_reviews_' . md5($place_id . serialize($atts));
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Check rate limiting
        $rate_limit_key = 'wcefp_reviews_rate_limit_' . md5($place_id);
        if (get_transient($rate_limit_key)) {
            // Use stale cache if available
            $stale_cache = get_option($cache_key . '_backup');
            if ($stale_cache) {
                return $stale_cache;
            }
            return false;
        }
        
        // Fetch from Google Places API
        $reviews_data = $this->fetch_reviews_from_api($place_id, $api_key, $atts);
        
        if ($reviews_data) {
            // Cache successful response
            set_transient($cache_key, $reviews_data, intval($atts['cache_duration']));
            update_option($cache_key . '_backup', $reviews_data); // Backup for rate limiting
            
            DiagnosticLogger::instance()->log_integration('success', 'Google Reviews fetched', 'google-reviews', [
                'place_id' => $place_id,
                'reviews_count' => count($reviews_data['reviews'] ?? [])
            ]);
        } else {
            // Set rate limit on API failure
            set_transient($rate_limit_key, true, self::RATE_LIMIT_CACHE);
            
            DiagnosticLogger::instance()->log_integration('error', 'Google Reviews API failed', 'google-reviews', [
                'place_id' => $place_id
            ]);
        }
        
        return $reviews_data;
    }
    
    /**
     * Fetch reviews from Google Places API
     * 
     * @param string $place_id Place ID
     * @param string $api_key API key
     * @param array $atts Shortcode attributes
     * @return array|false
     */
    private function fetch_reviews_from_api($place_id, $api_key, $atts) {
        $url = add_query_arg([
            'place_id' => $place_id,
            'key' => $api_key,
            'fields' => 'name,rating,user_ratings_total,reviews,formatted_address',
            'language' => substr(get_locale(), 0, 2),
            'reviews_sort' => 'newest'
        ], 'https://maps.googleapis.com/maps/api/place/details/json');
        
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'user-agent' => 'WCEventsFP/' . WCEFP_VERSION . ' WordPress Plugin',
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['status']) || $data['status'] !== 'OK') {
            return false;
        }
        
        $result = $data['result'] ?? [];
        
        // Process and filter reviews
        $reviews = $result['reviews'] ?? [];
        $filtered_reviews = array_filter($reviews, function($review) use ($atts) {
            return ($review['rating'] ?? 0) >= intval($atts['min_rating']);
        });
        
        // Limit number of reviews
        $filtered_reviews = array_slice($filtered_reviews, 0, intval($atts['limit']));
        
        return [
            'place_name' => $result['name'] ?? '',
            'place_address' => $result['formatted_address'] ?? '',
            'overall_rating' => $result['rating'] ?? 0,
            'total_ratings' => $result['user_ratings_total'] ?? 0,
            'reviews' => $filtered_reviews,
            'fetched_at' => current_time('timestamp')
        ];
    }
    
    /**
     * Render reviews HTML
     * 
     * @param array $reviews_data Reviews data
     * @param array $atts Shortcode attributes
     * @return string
     */
    private function render_reviews($reviews_data, $atts) {
        if (empty($reviews_data['reviews'])) {
            return $this->render_fallback_reviews($atts);
        }
        
        ob_start();
        
        $wrapper_classes = [
            'wcefp-google-reviews-v2',
            'wcefp-reviews-style-' . sanitize_html_class($atts['style']),
            'wcefp-reviews-layout-' . sanitize_html_class($atts['layout'])
        ];
        
        if (!empty($atts['class'])) {
            $wrapper_classes[] = sanitize_html_class($atts['class']);
        }
        
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>" 
             data-place-id="<?php echo esc_attr($reviews_data['place_name']); ?>"
             itemscope itemtype="https://schema.org/LocalBusiness">
             
            <?php if ($atts['show_overall'] === 'yes' && !empty($reviews_data['overall_rating'])): ?>
                <?php $this->render_overall_rating($reviews_data, $atts); ?>
            <?php endif; ?>
            
            <div class="wcefp-reviews-list">
                <?php foreach ($reviews_data['reviews'] as $review): ?>
                    <?php $this->render_single_review($review, $atts); ?>
                <?php endforeach; ?>
            </div>
            
            <?php if ($atts['show_attribution'] === 'yes'): ?>
                <?php $this->render_attribution($reviews_data); ?>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render overall rating section
     * 
     * @param array $reviews_data Reviews data
     * @param array $atts Shortcode attributes
     */
    private function render_overall_rating($reviews_data, $atts) {
        ?>
        <div class="wcefp-reviews-overall" itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating">
            <div class="wcefp-overall-content">
                <div class="wcefp-overall-rating">
                    <span class="wcefp-rating-number" itemprop="ratingValue"><?php echo esc_html(number_format($reviews_data['overall_rating'], 1)); ?></span>
                    <div class="wcefp-rating-stars">
                        <?php $this->render_star_rating($reviews_data['overall_rating']); ?>
                    </div>
                </div>
                <div class="wcefp-overall-meta">
                    <span class="wcefp-reviews-count">
                        <?php 
                        printf(
                            esc_html(_n('%d review', '%d reviews', $reviews_data['total_ratings'], 'wceventsfp')),
                            $reviews_data['total_ratings']
                        ); 
                        ?>
                    </span>
                    <?php if (!empty($reviews_data['place_name'])): ?>
                        <span class="wcefp-place-name" itemprop="name"><?php echo esc_html($reviews_data['place_name']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <meta itemprop="bestRating" content="5">
            <meta itemprop="worstRating" content="1">
            <meta itemprop="reviewCount" content="<?php echo esc_attr($reviews_data['total_ratings']); ?>">
        </div>
        <?php
    }
    
    /**
     * Render single review
     * 
     * @param array $review Review data
     * @param array $atts Shortcode attributes
     */
    private function render_single_review($review, $atts) {
        ?>
        <div class="wcefp-review-item" itemscope itemtype="https://schema.org/Review">
            <div class="wcefp-review-header">
                <?php if ($atts['show_avatar'] === 'yes' && !empty($review['profile_photo_url'])): ?>
                    <div class="wcefp-review-avatar">
                        <img src="<?php echo esc_url($review['profile_photo_url']); ?>" 
                             alt="<?php echo esc_attr($review['author_name'] ?? ''); ?>"
                             loading="<?php echo esc_attr($atts['loading']); ?>" />
                    </div>
                <?php endif; ?>
                
                <div class="wcefp-review-meta">
                    <div class="wcefp-review-author" itemprop="author" itemscope itemtype="https://schema.org/Person">
                        <span itemprop="name"><?php echo esc_html($review['author_name'] ?? ''); ?></span>
                    </div>
                    
                    <?php if ($atts['show_rating'] === 'yes'): ?>
                        <div class="wcefp-review-rating" itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
                            <?php $this->render_star_rating($review['rating'] ?? 0); ?>
                            <meta itemprop="ratingValue" content="<?php echo esc_attr($review['rating'] ?? 0); ?>">
                            <meta itemprop="bestRating" content="5">
                            <meta itemprop="worstRating" content="1">
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_date'] === 'yes' && !empty($review['time'])): ?>
                        <div class="wcefp-review-date">
                            <time itemprop="datePublished" datetime="<?php echo esc_attr(date('Y-m-d', $review['time'])); ?>">
                                <?php echo esc_html(human_time_diff($review['time']) . ' ago'); ?>
                            </time>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($review['text'])): ?>
                <div class="wcefp-review-content">
                    <div class="wcefp-review-text" itemprop="reviewBody">
                        <?php echo esc_html(wp_trim_words($review['text'], 30)); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render star rating
     * 
     * @param float $rating Rating value
     */
    private function render_star_rating($rating) {
        $rating = floatval($rating);
        $full_stars = floor($rating);
        $has_half_star = ($rating - $full_stars) >= 0.5;
        $empty_stars = 5 - $full_stars - ($has_half_star ? 1 : 0);
        
        echo '<div class="wcefp-stars" aria-label="' . sprintf(esc_attr__('%s out of 5 stars', 'wceventsfp'), $rating) . '">';
        
        // Full stars
        for ($i = 0; $i < $full_stars; $i++) {
            echo '<span class="wcefp-star wcefp-star-full">★</span>';
        }
        
        // Half star
        if ($has_half_star) {
            echo '<span class="wcefp-star wcefp-star-half">★</span>';
        }
        
        // Empty stars
        for ($i = 0; $i < $empty_stars; $i++) {
            echo '<span class="wcefp-star wcefp-star-empty">☆</span>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render attribution
     * 
     * @param array $reviews_data Reviews data
     */
    private function render_attribution($reviews_data) {
        ?>
        <div class="wcefp-reviews-attribution">
            <div class="wcefp-powered-by">
                <span><?php esc_html_e('Reviews powered by', 'wceventsfp'); ?></span>
                <img src="<?php echo esc_url(WCEFP_PLUGIN_URL . 'assets/images/google-logo.png'); ?>" 
                     alt="Google" class="wcefp-google-logo" />
            </div>
            <div class="wcefp-last-updated">
                <?php 
                printf(
                    esc_html__('Last updated: %s', 'wceventsfp'),
                    human_time_diff($reviews_data['fetched_at']) . ' ago'
                );
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render fallback reviews when API is unavailable
     * 
     * @param array $atts Shortcode attributes
     * @return string
     */
    private function render_fallback_reviews($atts) {
        // Only show fallback in specific cases and if enabled
        if (!get_option('wcefp_reviews_fallback_enabled', false)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="wcefp-google-reviews-fallback wcefp-reviews-style-<?php echo esc_attr($atts['style']); ?>">
            <div class="wcefp-fallback-notice">
                <?php esc_html_e('Customer reviews will appear here when available.', 'wceventsfp'); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for loading reviews dynamically
     */
    public function ajax_load_reviews() {
        check_ajax_referer('wcefp_reviews', 'nonce');
        
        $place_id = sanitize_text_field($_POST['place_id'] ?? '');
        $atts = array_map('sanitize_text_field', $_POST['atts'] ?? []);
        
        if (empty($place_id)) {
            wp_send_json_error(__('Invalid place ID', 'wceventsfp'));
        }
        
        // Render reviews
        $reviews_html = $this->google_reviews_shortcode(array_merge($atts, ['place_id' => $place_id]));
        
        wp_send_json_success(['html' => $reviews_html]);
    }
    
    /**
     * Maybe enqueue scripts
     */
    public function maybe_enqueue_scripts() {
        global $post;
        
        $should_load = false;
        
        // Check if shortcode is present
        if ($post && (has_shortcode($post->post_content, 'wcefp_google_reviews') || 
                     has_shortcode($post->post_content, 'wcefp_google_reviews_v2'))) {
            $should_load = true;
        }
        
        // Check if booking widget v2 with reviews is present
        if ($post && has_shortcode($post->post_content, 'wcefp_booking_widget_v2')) {
            $should_load = true;
        }
        
        if ($should_load) {
            wp_enqueue_style(
                'wcefp-google-reviews',
                WCEFP_PLUGIN_URL . 'assets/frontend/css/google-reviews.css',
                [],
                WCEFP_VERSION
            );
            
            wp_enqueue_script(
                'wcefp-google-reviews',
                WCEFP_PLUGIN_URL . 'assets/frontend/js/google-reviews.js',
                ['jquery'],
                WCEFP_VERSION,
                true
            );
            
            wp_localize_script('wcefp-google-reviews', 'wcefp_reviews', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcefp_reviews')
            ]);
        }
    }
    
    /**
     * Register admin settings
     */
    public function register_admin_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_settings_field(
            'wcefp_reviews_fallback_enabled',
            __('Reviews Fallback', 'wceventsfp'),
            [$this, 'render_fallback_setting'],
            'wcefp_settings',
            'wcefp_google_settings'
        );
        
        register_setting('wcefp_settings', 'wcefp_reviews_fallback_enabled', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
    }
    
    /**
     * Render fallback setting
     */
    public function render_fallback_setting() {
        $enabled = get_option('wcefp_reviews_fallback_enabled', false);
        ?>
        <label>
            <input type="checkbox" name="wcefp_reviews_fallback_enabled" value="1" <?php checked($enabled); ?> />
            <?php esc_html_e('Show placeholder when reviews cannot be loaded', 'wceventsfp'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Display a placeholder message when Google Reviews API is unavailable or rate limited.', 'wceventsfp'); ?>
        </p>
        <?php
    }
    
    /**
     * Cleanup expired cache
     */
    public function cleanup_expired_cache() {
        global $wpdb;
        
        // Clean up backup cache older than 1 week
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             AND UNIX_TIMESTAMP(STR_TO_DATE(option_value, '%%Y-%%m-%%d %%H:%%i:%%s')) < %d",
            'wcefp_reviews_%_backup',
            time() - WEEK_IN_SECONDS
        ));
    }
}