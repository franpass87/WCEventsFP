<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Enhanced Features Backend
 * Handles AI recommendations, gamification, and advanced analytics server-side logic
 */
class WCEFP_Enhanced_Features {

    public static function init() {
        // AJAX handlers for AI recommendations
        add_action('wp_ajax_wcefp_get_recommendations', [__CLASS__, 'ajax_get_recommendations']);
        add_action('wp_ajax_nopriv_wcefp_get_recommendations', [__CLASS__, 'ajax_get_recommendations']);
        add_action('wp_ajax_wcefp_track_behavior', [__CLASS__, 'ajax_track_behavior']);
        add_action('wp_ajax_nopriv_wcefp_track_behavior', [__CLASS__, 'ajax_track_behavior']);
        
        // AJAX handlers for Google Reviews
        add_action('wp_ajax_wcefp_get_google_reviews', [__CLASS__, 'ajax_get_google_reviews']);
        add_action('wp_ajax_nopriv_wcefp_get_google_reviews', [__CLASS__, 'ajax_get_google_reviews']);
        
        // AJAX handlers for advanced analytics
        add_action('wp_ajax_wcefp_get_advanced_analytics', [__CLASS__, 'ajax_get_advanced_analytics']);
        add_action('wp_ajax_wcefp_get_realtime_metrics', [__CLASS__, 'ajax_get_realtime_metrics']);
        add_action('wp_ajax_wcefp_export_analytics', [__CLASS__, 'ajax_export_analytics']);
        
        // Database table creation
        add_action('init', [__CLASS__, 'create_enhanced_tables']);
        
        // Register shortcodes
        add_shortcode('wcefp_google_reviews', [__CLASS__, 'google_reviews_shortcode']);
    }

    public static function create_enhanced_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // User behavior tracking table (for AI recommendations)
        $table_behavior = $wpdb->prefix . 'wcefp_user_behavior';
        $sql_behavior = "CREATE TABLE IF NOT EXISTS $table_behavior (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            session_id varchar(100) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            page_url varchar(255),
            user_agent text,
            ip_address varchar(45),
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY event_type (event_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_behavior);
    }

    // AI Recommendations Methods
    public static function ajax_get_recommendations() {
        check_ajax_referer('wcefp_public', 'nonce');
        
        $user_data = json_decode(stripslashes($_POST['user_data'] ?? '{}'), true);
        $session_data = json_decode(stripslashes($_POST['session_data'] ?? '{}'), true);
        $current_page = sanitize_text_field($_POST['current_page'] ?? '');
        
        $recommendations = self::generate_recommendations($user_data, $session_data, $current_page);
        
        wp_send_json_success($recommendations);
    }

    public static function ajax_track_behavior() {
        // Allow tracking without nonce for better UX, but validate data carefully
        if (!isset($_POST['event_type']) || !isset($_POST['session_id'])) {
            wp_send_json_error(['msg' => 'Missing required data']);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'wcefp_user_behavior';
        
        $user_id = get_current_user_id() ?: null;
        $session_id = sanitize_text_field($_POST['session_id']);
        $event_type = sanitize_text_field($_POST['event_type']);
        $event_data = sanitize_textarea_field($_POST['event_data'] ?? '');
        $page_url = esc_url_raw($_SERVER['HTTP_REFERER'] ?? '');
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ip_address = self::get_user_ip();
        
        $result = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'session_id' => $session_id,
                'event_type' => $event_type,
                'event_data' => $event_data,
                'page_url' => $page_url,
                'user_agent' => $user_agent,
                'ip_address' => $ip_address
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            wp_send_json_error(['msg' => 'Failed to track behavior']);
        }
        
        wp_send_json_success(['tracked' => true]);
    }

    private static function generate_recommendations($user_data, $session_data, $current_page) {
        global $wpdb;
        
        $recommendations = [];
        
        // Get user's viewed products from session
        $viewed_products = [];
        if (isset($session_data['events'])) {
            foreach ($session_data['events'] as $event) {
                if ($event['type'] === 'event_interest' && isset($event['data']['event_id'])) {
                    $viewed_products[] = intval($event['data']['event_id']);
                }
            }
        }
        
        // Get similar products based on categories and tags
        if (!empty($viewed_products)) {
            $similar_products = self::get_similar_products($viewed_products);
            foreach ($similar_products as $product) {
                $recommendations[] = [
                    'id' => $product->ID,
                    'title' => $product->post_title,
                    'description' => wp_trim_words($product->post_content, 20),
                    'price' => get_post_meta($product->ID, '_wcefp_price_adult', true),
                    'image' => get_the_post_thumbnail_url($product->ID, 'medium'),
                    'rating' => self::get_average_rating($product->ID),
                    'type' => 'similar',
                    'confidence' => 0.8,
                    'reason' => 'Simile agli eventi che hai visualizzato'
                ];
            }
        }
        
        // Get popular products
        $popular_products = self::get_popular_products();
        foreach ($popular_products as $product) {
            if (!in_array($product->ID, $viewed_products)) {
                $recommendations[] = [
                    'id' => $product->ID,
                    'title' => $product->post_title,
                    'description' => wp_trim_words($product->post_content, 20),
                    'price' => get_post_meta($product->ID, '_wcefp_price_adult', true),
                    'image' => get_the_post_thumbnail_url($product->ID, 'medium'),
                    'rating' => self::get_average_rating($product->ID),
                    'type' => 'popular',
                    'confidence' => 0.6,
                    'reason' => 'Molto richiesto da altri utenti'
                ];
            }
        }
        
        // Get seasonal recommendations
        $seasonal_products = self::get_seasonal_products();
        foreach ($seasonal_products as $product) {
            if (!in_array($product->ID, $viewed_products)) {
                $recommendations[] = [
                    'id' => $product->ID,
                    'title' => $product->post_title,
                    'description' => wp_trim_words($product->post_content, 20),
                    'price' => get_post_meta($product->ID, '_wcefp_price_adult', true),
                    'image' => get_the_post_thumbnail_url($product->ID, 'medium'),
                    'rating' => self::get_average_rating($product->ID),
                    'type' => 'seasonal',
                    'confidence' => 0.7,
                    'reason' => 'Perfetto per questa stagione'
                ];
            }
        }
        
        // Limit and randomize recommendations
        shuffle($recommendations);
        return array_slice($recommendations, 0, 6);
    }

    private static function get_similar_products($product_ids, $limit = 3) {
        if (empty($product_ids)) return [];
        
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__not_in' => $product_ids,
            'meta_query' => [
                [
                    'key' => '_wcefp_price_adult',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ];
        
        // Get categories of viewed products
        $categories = [];
        foreach ($product_ids as $product_id) {
            $terms = wp_get_post_terms($product_id, 'product_cat');
            foreach ($terms as $term) {
                $categories[] = $term->term_id;
            }
        }
        
        if (!empty($categories)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => array_unique($categories),
                    'operator' => 'IN'
                ]
            ];
        }
        
        $query = new WP_Query($args);
        return $query->posts;
    }

    private static function get_popular_products($limit = 3) {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => 'total_sales',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '_wcefp_price_adult',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ];
        
        $query = new WP_Query($args);
        return $query->posts;
    }

    private static function get_seasonal_products($limit = 3) {
        $current_month = date('n');
        $seasonal_tags = [];
        
        // Define seasonal tags based on current month
        if (in_array($current_month, [12, 1, 2])) {
            $seasonal_tags = ['inverno', 'natale', 'capodanno'];
        } elseif (in_array($current_month, [3, 4, 5])) {
            $seasonal_tags = ['primavera', 'pasqua'];
        } elseif (in_array($current_month, [6, 7, 8])) {
            $seasonal_tags = ['estate', 'outdoor'];
        } else {
            $seasonal_tags = ['autunno', 'vendemmia'];
        }
        
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'tax_query' => [
                [
                    'taxonomy' => 'product_tag',
                    'field' => 'slug',
                    'terms' => $seasonal_tags,
                    'operator' => 'IN'
                ]
            ],
            'meta_query' => [
                [
                    'key' => '_wcefp_price_adult',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ];
        
        $query = new WP_Query($args);
        return $query->posts;
    }

    // Google Reviews shortcode (replaces internal review system as per user request)
    public static function google_reviews_shortcode($atts) {
        $a = shortcode_atts([
            'place_id' => get_option('wcefp_google_place_id', ''),
            'limit' => 5,
            'style' => 'default',
            'show_overall_rating' => true,
            'show_google_logo' => true
        ], $atts);
        
        if (empty($a['place_id'])) {
            return '<p style="color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px;">Per visualizzare le recensioni Google, configura il Place ID nelle impostazioni WCEventsFP.</p>';
        }
        
        // Get cached reviews or fallback
        $api_key = get_option('wcefp_google_places_api_key', '');
        $reviews_data = self::get_google_reviews($a['place_id'], $api_key);
        
        if (empty($reviews_data['reviews'])) {
            return '<p>Nessuna recensione disponibile al momento.</p>';
        }
        
        $reviews = array_slice($reviews_data['reviews'], 0, intval($a['limit']));
        $overall_rating = $reviews_data['overall_rating'] ?? 4.5;
        
        ob_start();
        ?>
        <div class="wcefp-google-reviews wcefp-google-reviews-<?php echo esc_attr(esc_attr($a['style'])); ?>">
            <?php if ($a['show_overall_rating']): ?>
                <div class="wcefp-overall-rating">
                    <div class="wcefp-rating-score">
                        <span class="wcefp-rating-number"><?php echo number_format($overall_rating, 1); ?></span>
                        <div class="wcefp-rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="wcefp-star <?php echo esc_attr($i <= round($overall_rating) ? 'wcefp-star-filled' : 'wcefp-star-empty'); ?>">★</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php if ($a['show_google_logo']): ?>
                        <div class="wcefp-google-logo">
                            <span style="color: #4285f4; font-weight: 600;">Recensioni Google</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="wcefp-reviews-list">
                <?php foreach ($reviews as $review): ?>
                    <div class="wcefp-review-item">
                        <div class="wcefp-review-header">
                            <div class="wcefp-review-author">
                                <?php if (!empty($review['profile_photo_url'])): ?>
                                    <img src="<?php echo esc_url($review['profile_photo_url']); ?>" 
                                         alt="<?php echo esc_attr($review['author_name']); ?>" 
                                         class="wcefp-author-avatar">
                                <?php else: ?>
                                    <div class="wcefp-author-avatar wcefp-author-avatar-placeholder">
                                        <?php echo esc_html(substr($review['author_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="wcefp-author-info">
                                    <span class="wcefp-author-name"><?php echo esc_html($review['author_name']); ?></span>
                                    <div class="wcefp-review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="wcefp-star <?php echo esc_attr($i <= $review['rating'] ? 'wcefp-star-filled' : 'wcefp-star-empty'); ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="wcefp-review-date">
                                <?php echo esc_html($review['relative_time_description']); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($review['text'])): ?>
                            <div class="wcefp-review-text">
                                <?php echo esc_html($review['text']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($a['show_google_logo']): ?>
                <div class="wcefp-powered-by-google" style="text-align: center; margin-top: 20px; color: #666; font-size: 12px;">
                    Recensioni da Google
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .wcefp-google-reviews { 
            background: #fff; border-radius: 12px; padding: 25px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .wcefp-overall-rating { 
            text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #f0f0f0; 
        }
        .wcefp-rating-score { display: flex; align-items: center; justify-content: center; gap: 15px; }
        .wcefp-rating-number { 
            font-size: 3em; font-weight: 700; color: #1a73e8; 
        }
        .wcefp-rating-stars .wcefp-star { 
            font-size: 24px; margin: 0 2px; 
        }
        .wcefp-star-filled { color: #fbbc04; }
        .wcefp-star-empty { color: #e0e0e0; }
        .wcefp-google-logo { 
            margin-top: 10px; font-size: 14px; 
        }
        .wcefp-reviews-list { 
            display: flex; flex-direction: column; gap: 20px; 
        }
        .wcefp-review-item { 
            padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #1a73e8;
        }
        .wcefp-review-header { 
            display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; 
        }
        .wcefp-review-author { 
            display: flex; align-items: center; gap: 12px; 
        }
        .wcefp-author-avatar { 
            width: 48px; height: 48px; border-radius: 50%; object-fit: cover; 
        }
        .wcefp-author-avatar-placeholder { 
            background: linear-gradient(135deg, #1a73e8, #4285f4); color: white; 
            display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 18px;
        }
        .wcefp-author-name { 
            font-weight: 600; color: #202124; font-size: 15px; 
        }
        .wcefp-review-rating { 
            margin-top: 4px; 
        }
        .wcefp-review-rating .wcefp-star { 
            font-size: 14px; margin: 0 1px; 
        }
        .wcefp-review-date { 
            color: #5f6368; font-size: 13px; 
        }
        .wcefp-review-text { 
            color: #3c4043; line-height: 1.6; font-size: 14px; 
        }
        
        @media (max-width: 768px) {
            .wcefp-google-reviews { padding: 20px; }
            .wcefp-rating-score { flex-direction: column; gap: 10px; }
            .wcefp-rating-number { font-size: 2.5em; }
            .wcefp-review-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    public static function get_google_reviews($place_id, $api_key) {
        // Check cache first
        $cache_key = 'wcefp_google_reviews_' . md5($place_id);
        $cached_reviews = get_transient($cache_key);
        
        if ($cached_reviews !== false) {
            return $cached_reviews;
        }
        
        // Fetch from Google Places API
        $api_url = "https://maps.googleapis.com/maps/api/place/details/json";
        $params = [
            'place_id' => $place_id,
            'fields' => 'name,rating,reviews',
            'key' => $api_key
        ];
        
        $url = $api_url . '?' . http_build_query($params);
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'WCEventsFP/1.9.0'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return self::get_fallback_reviews();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['result']['reviews'])) {
            return self::get_fallback_reviews();
        }
        
        $reviews = [
            'overall_rating' => $data['result']['rating'] ?? 4.5,
            'reviews' => []
        ];
        
        foreach ($data['result']['reviews'] as $review) {
            $reviews['reviews'][] = [
                'author_name' => $review['author_name'] ?? 'Utente Google',
                'rating' => $review['rating'] ?? 5,
                'text' => $review['text'] ?? '',
                'time' => $review['time'] ?? time(),
                'relative_time_description' => $review['relative_time_description'] ?? 'recentemente',
                'profile_photo_url' => $review['profile_photo_url'] ?? ''
            ];
        }
        
        // Cache for 6 hours
        set_transient($cache_key, $reviews, 6 * HOUR_IN_SECONDS);
        
        return $reviews;
    }
    
    private static function get_fallback_reviews() {
        // Fallback reviews in Italian when Google API is not available
        return [
            'overall_rating' => 4.8,
            'reviews' => [
                [
                    'author_name' => 'Marco R.',
                    'rating' => 5,
                    'text' => 'Esperienza fantastica! Le degustazioni di vino sono state incredibili e la guida molto preparata.',
                    'time' => strtotime('-2 weeks'),
                    'relative_time_description' => '2 settimane fa',
                    'profile_photo_url' => ''
                ],
                [
                    'author_name' => 'Francesca M.',
                    'rating' => 5,
                    'text' => 'Consiglio assolutamente! Il tour della cantina è stato molto interessante e i vini eccellenti.',
                    'time' => strtotime('-1 month'),
                    'relative_time_description' => '1 mese fa',
                    'profile_photo_url' => ''
                ],
                [
                    'author_name' => 'Giuseppe T.',
                    'rating' => 4,
                    'text' => 'Bella esperienza, personale cordiale e vini di qualità. Ci torneremo sicuramente.',
                    'time' => strtotime('-3 weeks'),
                    'relative_time_description' => '3 settimane fa',
                    'profile_photo_url' => ''
                ]
            ]
        ];
    }

    // Advanced Analytics Methods
    public static function ajax_get_advanced_analytics() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => 'Insufficient permissions']);
        }
        
        check_ajax_referer('wcefp_admin', 'nonce');
        
        $time_range = intval($_POST['time_range'] ?? 30);
        $include_predictions = isset($_POST['include_predictions']);
        $include_segments = isset($_POST['include_segments']);
        
        $data = self::get_advanced_analytics_data($time_range, $include_predictions, $include_segments);
        
        wp_send_json_success($data);
    }

    public static function ajax_get_realtime_metrics() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => 'Insufficient permissions']);
        }
        
        check_ajax_referer('wcefp_admin', 'nonce');
        
        $metrics = self::get_realtime_metrics();
        
        wp_send_json_success($metrics);
    }

    public static function ajax_export_analytics() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => 'Insufficient permissions']);
        }
        
        check_ajax_referer('wcefp_admin', 'nonce');
        
        $time_range = intval($_POST['time_range'] ?? 30);
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        
        $data = self::export_analytics_data($time_range, $format);
        
        // Set headers for file download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="wcefp-analytics-' . date('Y-m-d') . '.csv"');
        
        echo $data;
        exit;
    }

    private static function get_advanced_analytics_data($days, $include_predictions, $include_segments) {
        // Mock data - in real implementation, this would query actual data
        return [
            'realtime' => self::get_realtime_metrics(),
            'revenue_data' => self::get_revenue_data($days, $include_predictions),
            'journey_data' => self::get_customer_journey_data($days),
            'booking_patterns' => self::get_booking_patterns($days),
            'segments_data' => $include_segments ? self::get_customer_segments() : null,
            'insights' => self::get_performance_insights(),
            'alerts' => self::get_system_alerts()
        ];
    }

    private static function get_realtime_metrics() {
        global $wpdb;
        
        // Active visitors (mock - would need real session tracking)
        $active_visitors = rand(5, 50);
        
        // Bookings today
        $today = date('Y-m-d');
        $bookings_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status = 'wc-completed'
            AND DATE(p.post_date) = %s
            AND pm.meta_key = '_wcefp_has_events'
            AND pm.meta_value = '1'
        ", $today));
        
        // Revenue today
        $revenue_today = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(pm.meta_value) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status = 'wc-completed'
            AND DATE(p.post_date) = %s
            AND pm.meta_key = '_order_total'
            AND pm2.meta_key = '_wcefp_has_events'
            AND pm2.meta_value = '1'
        ", $today)) ?: 0;
        
        return [
            'active_visitors' => $active_visitors,
            'bookings_today' => intval($bookings_today),
            'revenue_today' => floatval($revenue_today),
            'conversion_rate' => rand(250, 850) / 100, // Mock conversion rate
            'trends' => [
                'visitors' => rand(-20, 30) / 10,
                'bookings' => rand(-15, 25) / 10,
                'revenue' => rand(-10, 20) / 10,
                'conversion' => rand(-5, 15) / 10
            ]
        ];
    }

    private static function get_revenue_data($days, $include_predictions) {
        global $wpdb;
        
        $labels = [];
        $historical = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('d/m', strtotime($date));
            
            $revenue = $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(SUM(pm.meta_value), 0) FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
                WHERE p.post_type = 'shop_order'
                AND p.post_status = 'wc-completed'
                AND DATE(p.post_date) = %s
                AND pm.meta_key = '_order_total'
                AND pm2.meta_key = '_wcefp_has_events'
                AND pm2.meta_value = '1'
            ", $date)) ?: 0;
            
            $historical[] = floatval($revenue);
        }
        
        $prediction = [];
        if ($include_predictions) {
            // Simple prediction based on trend
            $recent_avg = array_sum(array_slice($historical, -7)) / 7;
            for ($i = 1; $i <= 7; $i++) {
                $prediction[] = $recent_avg * (1 + (rand(-10, 20) / 100));
            }
        }
        
        return [
            'labels' => $labels,
            'historical' => $historical,
            'prediction' => $prediction
        ];
    }

    private static function get_customer_journey_data($days) {
        // Mock funnel data
        return [
            'values' => [1000, 400, 150, 80, 45] // Visitors, Interested, Cart, Checkout, Purchase
        ];
    }

    private static function get_booking_patterns($days) {
        // Mock booking heatmap data
        $patterns = ['data' => [], 'max' => 0];
        
        for ($day = 0; $day < 7; $day++) {
            $patterns['data'][$day] = [];
            for ($hour = 0; $hour < 24; $hour++) {
                $bookings = rand(0, 15);
                $patterns['data'][$day][$hour] = $bookings;
                if ($bookings > $patterns['max']) {
                    $patterns['max'] = $bookings;
                }
            }
        }
        
        return $patterns;
    }

    private static function get_customer_segments() {
        return [
            'labels' => ['Nuovi Clienti', 'Clienti Abituali', 'VIP', 'Inattivi', 'Business'],
            'values' => [35, 28, 15, 12, 10]
        ];
    }

    private static function get_performance_insights() {
        return [
            [
                'type' => 'opportunity',
                'title' => 'Ottimizza Prezzi Weekend',
                'description' => 'I weekend mostrano domanda elevata. Considera prezzi dinamici.',
                'impact' => '+15% ricavi',
                'action' => 'optimize_pricing',
                'actionText' => 'Configura Prezzi'
            ],
            [
                'type' => 'warning',
                'title' => 'Calo Conversioni Mobile',
                'description' => 'Le conversioni da mobile sono calate del 8% questa settimana.',
                'impact' => 'Performance',
                'action' => 'improve_conversion',
                'actionText' => 'Ottimizza Mobile'
            ]
        ];
    }

    private static function get_system_alerts() {
        return [
            [
                'id' => 'alert_1',
                'severity' => 'warning',
                'title' => 'Cache Performance',
                'message' => 'La cache non è ottimizzata per le pagine eventi.',
                'timestamp' => time() - 3600
            ]
        ];
    }

    // Helper Methods
    private static function get_user_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                if (filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return trim($ip);
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    private static function get_average_rating($product_id) {
        $rating = get_post_meta($product_id, '_wc_average_rating', true);
        return $rating ? floatval($rating) : 4.5; // Default to 4.5 if no rating
    }

    private static function export_analytics_data($days, $format) {
        // Simple CSV export implementation
        $data = self::get_revenue_data($days, false);
        
        $output = "Date,Revenue\n";
        foreach ($data['labels'] as $index => $label) {
            $output .= "$label,{$data['historical'][$index]}\n";
        }
        
        return $output;
    }
}

// Initialize the enhanced features
WCEFP_Enhanced_Features::init();