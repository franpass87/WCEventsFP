<?php
/**
 * Google Reviews Service
 * 
 * Handles integration with Google Places API for reviews and ratings
 * 
 * @package WCEFP
 * @subpackage Services\Integration
 * @since 2.2.0
 */

namespace WCEFP\Services\Integration;

use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Google Reviews Service Class
 * 
 * Manages Google Places API integration for reviews and ratings
 */
class GoogleReviewsService {
    
    /**
     * Cache durations
     */
    const CACHE_RATING_DURATION = 12 * HOUR_IN_SECONDS; // 12 hours
    const CACHE_REVIEWS_DURATION = 6 * HOUR_IN_SECONDS;  // 6 hours
    
    /**
     * API configuration
     */
    private $api_key;
    private $api_base_url = 'https://maps.googleapis.com/maps/api/place';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('wcefp_google_places_api_key');
    }
    
    /**
     * Get rating summary for a place
     * 
     * @param string $place_id Google Place ID
     * @return array Rating summary data
     */
    public function get_rating_summary($place_id) {
        if (empty($this->api_key) || empty($place_id)) {
            return $this->get_fallback_rating_data();
        }
        
        $cache_key = 'wcefp_rating_summary_' . md5($place_id);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        try {
            $url = add_query_arg([
                'place_id' => $place_id,
                'key' => $this->api_key,
                'fields' => 'rating,user_ratings_total,name',
                'language' => $this->get_locale_code()
            ], $this->api_base_url . '/details/json');
            
            $response = wp_remote_get($url, [
                'timeout' => 10,
                'user-agent' => 'WCEventsFP/' . WCEFP_VERSION
            ]);
            
            if (is_wp_error($response)) {
                $this->log_error('API request failed', $response->get_error_message(), $place_id);
                return $this->get_fallback_rating_data();
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (empty($data['result'])) {
                $this->log_error('Empty API response', $body, $place_id);
                return $this->get_fallback_rating_data();
            }
            
            $result = $data['result'];
            $rating_data = [
                'rating' => floatval($result['rating'] ?? 0),
                'total_reviews' => intval($result['user_ratings_total'] ?? 0),
                'place_name' => sanitize_text_field($result['name'] ?? ''),
                'has_data' => true,
                'last_updated' => time()
            ];
            
            // Cache the result
            set_transient($cache_key, $rating_data, self::CACHE_RATING_DURATION);
            
            $this->log_success('Rating summary retrieved', $place_id, $rating_data['rating']);
            
            return $rating_data;
            
        } catch (\Exception $e) {
            $this->log_error('Exception during API call', $e->getMessage(), $place_id);
            return $this->get_fallback_rating_data();
        }
    }
    
    /**
     * Get recent reviews for a place
     * 
     * @param string $place_id Google Place ID
     * @param int $limit Maximum number of reviews to return
     * @return array Reviews data
     */
    public function get_recent_reviews($place_id, $limit = 5) {
        if (empty($this->api_key) || empty($place_id)) {
            return $this->get_fallback_reviews_data();
        }
        
        $cache_key = 'wcefp_recent_reviews_' . md5($place_id . $limit);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        try {
            $url = add_query_arg([
                'place_id' => $place_id,
                'key' => $this->api_key,
                'fields' => 'reviews',
                'language' => $this->get_locale_code()
            ], $this->api_base_url . '/details/json');
            
            $response = wp_remote_get($url, [
                'timeout' => 15,
                'user-agent' => 'WCEventsFP/' . WCEFP_VERSION
            ]);
            
            if (is_wp_error($response)) {
                $this->log_error('Reviews API request failed', $response->get_error_message(), $place_id);
                return $this->get_fallback_reviews_data();
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (empty($data['result']['reviews'])) {
                $this->log_error('No reviews found', $body, $place_id);
                return $this->get_fallback_reviews_data();
            }
            
            $reviews = array_slice($data['result']['reviews'], 0, $limit);
            $formatted_reviews = [];
            
            foreach ($reviews as $review) {
                $formatted_reviews[] = [
                    'author_name' => sanitize_text_field($review['author_name'] ?? 'Anonymous'),
                    'rating' => floatval($review['rating'] ?? 0),
                    'text' => sanitize_textarea_field($review['text'] ?? ''),
                    'time' => intval($review['time'] ?? 0),
                    'relative_time' => sanitize_text_field($review['relative_time_description'] ?? ''),
                    'profile_photo_url' => esc_url_raw($review['profile_photo_url'] ?? '')
                ];
            }
            
            $reviews_data = [
                'reviews' => $formatted_reviews,
                'count' => count($formatted_reviews),
                'has_data' => !empty($formatted_reviews),
                'last_updated' => time()
            ];
            
            // Cache the result
            set_transient($cache_key, $reviews_data, self::CACHE_REVIEWS_DURATION);
            
            $this->log_success('Reviews retrieved', $place_id, count($formatted_reviews));
            
            return $reviews_data;
            
        } catch (\Exception $e) {
            $this->log_error('Exception during reviews API call', $e->getMessage(), $place_id);
            return $this->get_fallback_reviews_data();
        }
    }
    
    /**
     * Test connection to Google Places API with a place ID
     * 
     * @param string $place_id Google Place ID
     * @return array Test result
     */
    public function test_place_id_connection($place_id) {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => __('Google Places API key not configured', 'wceventsfp'),
                'error' => 'no_api_key'
            ];
        }
        
        if (empty($place_id)) {
            return [
                'success' => false,
                'message' => __('Place ID is required', 'wceventsfp'),
                'error' => 'no_place_id'
            ];
        }
        
        try {
            $url = add_query_arg([
                'place_id' => $place_id,
                'key' => $this->api_key,
                'fields' => 'place_id,name,rating,user_ratings_total',
            ], $this->api_base_url . '/details/json');
            
            $response = wp_remote_get($url, [
                'timeout' => 10,
                'user-agent' => 'WCEventsFP/' . WCEFP_VERSION
            ]);
            
            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => sprintf(__('API request failed: %s', 'wceventsfp'), $response->get_error_message()),
                    'error' => 'request_failed'
                ];
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data['status'] === 'OK' && !empty($data['result'])) {
                $result = $data['result'];
                return [
                    'success' => true,
                    'message' => sprintf(
                        __('✅ Place ID is valid! Found: %s (Rating: %s, Reviews: %d)', 'wceventsfp'),
                        $result['name'] ?? 'Unknown',
                        $result['rating'] ?? 'N/A',
                        $result['user_ratings_total'] ?? 0
                    ),
                    'place_data' => $result
                ];
            } else {
                return [
                    'success' => false,
                    'message' => sprintf(
                        __('Invalid Place ID or API error: %s', 'wceventsfp'),
                        $data['status'] ?? 'Unknown error'
                    ),
                    'error' => 'invalid_place_id',
                    'api_status' => $data['status'] ?? null
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Exception: %s', 'wceventsfp'), $e->getMessage()),
                'error' => 'exception'
            ];
        }
    }
    
    /**
     * Get formatted reviews HTML for display
     * 
     * @param string $place_id Google Place ID
     * @param array $args Display arguments
     * @return string HTML output
     */
    public function get_reviews_html($place_id, $args = []) {
        $args = wp_parse_args($args, [
            'limit' => 3,
            'show_photos' => true,
            'show_rating' => true,
            'show_date' => true,
            'excerpt_length' => 150,
            'class' => 'wcefp-google-reviews'
        ]);
        
        $rating_summary = $this->get_rating_summary($place_id);
        $reviews_data = $this->get_recent_reviews($place_id, $args['limit']);
        
        if (!$rating_summary['has_data'] && !$reviews_data['has_data']) {
            return '<div class="wcefp-no-reviews">' . 
                   '<p>' . esc_html__('Nessuna recensione Google disponibile.', 'wceventsfp') . '</p>' .
                   '</div>';
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($args['class']); ?>">
            
            <?php if ($rating_summary['has_data']): ?>
            <div class="wcefp-google-rating-summary">
                <div class="wcefp-google-rating-score">
                    <span class="wcefp-rating-value"><?php echo number_format($rating_summary['rating'], 1); ?></span>
                    <div class="wcefp-rating-stars">
                        <?php echo $this->render_star_rating($rating_summary['rating']); ?>
                    </div>
                </div>
                <div class="wcefp-google-rating-meta">
                    <span class="wcefp-reviews-count">
                        <?php printf(
                            esc_html(_n('%d recensione Google', '%d recensioni Google', $rating_summary['total_reviews'], 'wceventsfp')),
                            $rating_summary['total_reviews']
                        ); ?>
                    </span>
                    <?php if (!empty($rating_summary['place_name'])): ?>
                        <span class="wcefp-place-name"><?php echo esc_html($rating_summary['place_name']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($reviews_data['has_data'] && !empty($reviews_data['reviews'])): ?>
            <div class="wcefp-google-reviews-list">
                <?php foreach ($reviews_data['reviews'] as $review): ?>
                    <div class="wcefp-google-review-item">
                        <div class="wcefp-review-header">
                            <div class="wcefp-reviewer-info">
                                <?php if ($args['show_photos'] && !empty($review['profile_photo_url'])): ?>
                                    <img src="<?php echo esc_url($review['profile_photo_url']); ?>" 
                                         alt="<?php echo esc_attr($review['author_name']); ?>"
                                         class="wcefp-reviewer-photo" />
                                <?php endif; ?>
                                <span class="wcefp-reviewer-name"><?php echo esc_html($review['author_name']); ?></span>
                            </div>
                            
                            <div class="wcefp-review-meta">
                                <?php if ($args['show_rating'] && $review['rating'] > 0): ?>
                                    <div class="wcefp-review-rating">
                                        <?php echo $this->render_star_rating($review['rating']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($args['show_date'] && !empty($review['relative_time'])): ?>
                                    <span class="wcefp-review-date"><?php echo esc_html($review['relative_time']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($review['text'])): ?>
                            <div class="wcefp-review-text">
                                <?php 
                                $text = $review['text'];
                                if (strlen($text) > $args['excerpt_length']) {
                                    $text = wp_trim_words($text, $args['excerpt_length'] / 6);
                                }
                                echo esc_html($text);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Clear all cached data for a place ID
     * 
     * @param string $place_id Google Place ID
     */
    public function clear_cache($place_id) {
        $cache_keys = [
            'wcefp_rating_summary_' . md5($place_id),
            'wcefp_recent_reviews_' . md5($place_id . '5'),
            'wcefp_recent_reviews_' . md5($place_id . '3'),
            'wcefp_recent_reviews_' . md5($place_id . '10')
        ];
        
        foreach ($cache_keys as $key) {
            delete_transient($key);
        }
    }
    
    /**
     * Get fallback rating data when API is unavailable
     * 
     * @return array
     */
    private function get_fallback_rating_data() {
        return [
            'rating' => 0,
            'total_reviews' => 0,
            'place_name' => '',
            'has_data' => false,
            'last_updated' => 0
        ];
    }
    
    /**
     * Get fallback reviews data when API is unavailable
     * 
     * @return array
     */
    private function get_fallback_reviews_data() {
        return [
            'reviews' => [],
            'count' => 0,
            'has_data' => false,
            'last_updated' => 0
        ];
    }
    
    /**
     * Get locale code for API requests
     * 
     * @return string
     */
    private function get_locale_code() {
        $locale = get_locale();
        return substr($locale, 0, 2); // Convert 'it_IT' to 'it'
    }
    
    /**
     * Render star rating HTML
     * 
     * @param float $rating Rating value
     * @return string HTML
     */
    private function render_star_rating($rating) {
        $full_stars = floor($rating);
        $half_star = ($rating - $full_stars) >= 0.5;
        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
        
        $output = '<div class="wcefp-google-stars" title="' . esc_attr(sprintf(__('%s out of 5 stars', 'wceventsfp'), $rating)) . '">';
        
        for ($i = 0; $i < $full_stars; $i++) {
            $output .= '<span class="wcefp-star wcefp-star-full">★</span>';
        }
        
        if ($half_star) {
            $output .= '<span class="wcefp-star wcefp-star-half">☆</span>';
        }
        
        for ($i = 0; $i < $empty_stars; $i++) {
            $output .= '<span class="wcefp-star wcefp-star-empty">☆</span>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Log success message
     * 
     * @param string $message
     * @param string $place_id
     * @param mixed $data
     */
    private function log_success($message, $place_id, $data = null) {
        DiagnosticLogger::instance()->info($message, [
            'place_id' => $place_id,
            'data' => $data
        ], DiagnosticLogger::CHANNEL_INTEGRATION);
    }
    
    /**
     * Log error message
     * 
     * @param string $message
     * @param mixed $error
     * @param string $place_id
     */
    private function log_error($message, $error, $place_id = '') {
        DiagnosticLogger::instance()->error($message, [
            'place_id' => $place_id,
            'error' => $error
        ], DiagnosticLogger::CHANNEL_INTEGRATION);
    }
}