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
        
        // AJAX handlers for gamification
        add_action('wp_ajax_wcefp_get_user_gamification_data', [__CLASS__, 'ajax_get_user_gamification_data']);
        add_action('wp_ajax_wcefp_award_points', [__CLASS__, 'ajax_award_points']);
        add_action('wp_ajax_wcefp_get_leaderboard', [__CLASS__, 'ajax_get_leaderboard']);
        add_action('wp_ajax_wcefp_save_user_theme', [__CLASS__, 'ajax_save_user_theme']);
        
        // AJAX handlers for advanced analytics
        add_action('wp_ajax_wcefp_get_advanced_analytics', [__CLASS__, 'ajax_get_advanced_analytics']);
        add_action('wp_ajax_wcefp_get_realtime_metrics', [__CLASS__, 'ajax_get_realtime_metrics']);
        add_action('wp_ajax_wcefp_export_analytics', [__CLASS__, 'ajax_export_analytics']);
        
        // Hook into WooCommerce order completion for gamification
        add_action('woocommerce_order_status_completed', [__CLASS__, 'on_order_completed']);
        
        // Database table creation
        add_action('init', [__CLASS__, 'create_enhanced_tables']);
    }

    public static function create_enhanced_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // User behavior tracking table
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
        
        // User gamification data table
        $table_gamification = $wpdb->prefix . 'wcefp_user_gamification';
        $sql_gamification = "CREATE TABLE IF NOT EXISTS $table_gamification (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points int(11) DEFAULT 0,
            level int(11) DEFAULT 1,
            total_bookings int(11) DEFAULT 0,
            total_spent decimal(10,2) DEFAULT 0.00,
            badges text,
            achievements text,
            last_login datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        // Points history table
        $table_points_history = $wpdb->prefix . 'wcefp_points_history';
        $sql_points_history = "CREATE TABLE IF NOT EXISTS $table_points_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points int(11) NOT NULL,
            action_type varchar(50) NOT NULL,
            action_data text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action_type (action_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_behavior);
        dbDelta($sql_gamification);
        dbDelta($sql_points_history);
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

    // Gamification Methods
    public static function ajax_get_user_gamification_data() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['msg' => 'User not logged in']);
        }
        
        check_ajax_referer('wcefp_public', 'nonce');
        
        $user_id = get_current_user_id();
        $data = self::get_user_gamification_data($user_id);
        
        wp_send_json_success($data);
    }

    public static function ajax_award_points() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['msg' => 'User not logged in']);
        }
        
        check_ajax_referer('wcefp_public', 'nonce');
        
        $user_id = get_current_user_id();
        $points = intval($_POST['points'] ?? 0);
        $action_type = sanitize_text_field($_POST['action_type'] ?? '');
        $action_data = sanitize_textarea_field($_POST['action_data'] ?? '');
        
        if ($points <= 0 || empty($action_type)) {
            wp_send_json_error(['msg' => 'Invalid data']);
        }
        
        $result = self::award_points($user_id, $points, $action_type, $action_data);
        
        wp_send_json_success($result);
    }

    public static function ajax_get_leaderboard() {
        check_ajax_referer('wcefp_public', 'nonce');
        
        $period = sanitize_text_field($_POST['period'] ?? 'weekly');
        $leaderboard = self::get_leaderboard($period);
        
        wp_send_json_success($leaderboard);
    }

    public static function ajax_save_user_theme() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['msg' => 'User not logged in']);
        }
        
        check_ajax_referer('wcefp_public', 'nonce');
        
        $user_id = get_current_user_id();
        $theme = sanitize_text_field($_POST['theme'] ?? 'light');
        
        if (!in_array($theme, ['light', 'dark'])) {
            wp_send_json_error(['msg' => 'Invalid theme']);
        }
        
        update_user_meta($user_id, 'wcefp_preferred_theme', $theme);
        
        wp_send_json_success(['theme_saved' => $theme]);
    }

    public static function get_user_gamification_data($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcefp_user_gamification';
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        if (!$data) {
            // Create new gamification data for user
            $wpdb->insert(
                $table,
                ['user_id' => $user_id, 'points' => 0, 'level' => 1],
                ['%d', '%d', '%d']
            );
            
            $data = [
                'user_id' => $user_id,
                'points' => 0,
                'level' => 1,
                'total_bookings' => 0,
                'total_spent' => 0.00,
                'badges' => '[]',
                'achievements' => '[]'
            ];
        }
        
        return [
            'points' => intval($data['points']),
            'level' => intval($data['level']),
            'total_bookings' => intval($data['total_bookings']),
            'total_spent' => floatval($data['total_spent']),
            'badges' => json_decode($data['badges'] ?? '[]', true),
            'achievements' => json_decode($data['achievements'] ?? '[]', true)
        ];
    }

    public static function award_points($user_id, $points, $action_type, $action_data = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'wcefp_user_gamification';
        $history_table = $wpdb->prefix . 'wcefp_points_history';
        
        // Get current data
        $current_data = self::get_user_gamification_data($user_id);
        $old_level = $current_data['level'];
        $new_points = $current_data['points'] + $points;
        
        // Calculate new level
        $new_level = self::calculate_level($new_points);
        
        // Update gamification data
        $wpdb->replace(
            $table,
            [
                'user_id' => $user_id,
                'points' => $new_points,
                'level' => $new_level,
                'total_bookings' => $current_data['total_bookings'],
                'total_spent' => $current_data['total_spent'],
                'badges' => json_encode($current_data['badges']),
                'achievements' => json_encode($current_data['achievements'])
            ],
            ['%d', '%d', '%d', '%d', '%f', '%s', '%s']
        );
        
        // Add to points history
        $wpdb->insert(
            $history_table,
            [
                'user_id' => $user_id,
                'points' => $points,
                'action_type' => $action_type,
                'action_data' => $action_data
            ],
            ['%d', '%d', '%s', '%s']
        );
        
        // Check for new achievements and badges
        $achievements = self::check_achievements($user_id, $action_type);
        $badges = self::check_badges($user_id, $action_type);
        
        return [
            'total_points' => $new_points,
            'old_level' => $old_level,
            'new_level' => $new_level,
            'achievements' => $achievements,
            'badges' => $badges
        ];
    }

    private static function calculate_level($points) {
        $thresholds = [0, 100, 250, 500, 1000, 1800, 3000, 4500, 6500, 9000, 12000, 16000, 21000, 27000, 34000, 42000, 52000, 64000, 78000, 95000];
        
        for ($i = count($thresholds) - 1; $i >= 0; $i--) {
            if ($points >= $thresholds[$i]) {
                return $i + 1;
            }
        }
        
        return 1;
    }

    private static function check_achievements($user_id, $action_type) {
        // Implementation would check for specific achievement conditions
        // and return any newly unlocked achievements
        return [];
    }

    private static function check_badges($user_id, $action_type) {
        // Implementation would check for specific badge conditions
        // and return any newly earned badges
        return [];
    }

    public static function get_leaderboard($period = 'weekly', $limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcefp_user_gamification';
        $users_table = $wpdb->users;
        
        $date_condition = '';
        switch ($period) {
            case 'weekly':
                $date_condition = "AND g.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'monthly':
                $date_condition = "AND g.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'all-time':
            default:
                $date_condition = '';
                break;
        }
        
        $current_user_id = get_current_user_id();
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT g.*, u.display_name as name,
                   (g.user_id = %d) as is_current_user
            FROM $table g 
            LEFT JOIN $users_table u ON u.ID = g.user_id 
            WHERE g.points > 0 $date_condition
            ORDER BY g.points DESC, g.level DESC 
            LIMIT %d
        ", $current_user_id, $limit), ARRAY_A);
        
        foreach ($results as &$result) {
            $result['is_current_user'] = (bool) $result['is_current_user'];
            $result['avatar'] = get_avatar_url($result['user_id']);
        }
        
        return $results;
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
    public static function on_order_completed($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $user_id = $order->get_user_id();
        if (!$user_id) return;
        
        // Check if order contains events/experiences
        $has_events = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && in_array($product->get_type(), ['wcefp_event', 'wcefp_experience'])) {
                $has_events = true;
                break;
            }
        }
        
        if ($has_events) {
            // Award points for booking completion
            $points = 10; // Base points
            $order_total = $order->get_total();
            
            // Bonus points for higher value orders
            if ($order_total > 100) $points += 5;
            if ($order_total > 200) $points += 10;
            
            self::award_points($user_id, $points, 'booking_completed', json_encode([
                'order_id' => $order_id,
                'order_total' => $order_total
            ]));
        }
    }

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