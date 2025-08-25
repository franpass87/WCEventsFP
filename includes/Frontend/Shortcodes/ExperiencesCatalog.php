<?php
/**
 * Experiences Catalog Shortcode
 * 
 * Displays a catalog of experiences with filtering, search, and pagination
 * Style: Get Your Guide (GYG) inspired design
 *
 * @package WCEFP\Frontend\Shortcodes
 * @since 2.2.0
 */

namespace WCEFP\Frontend\Shortcodes;

use WCEFP\Features\Visibility\ExperienceGating;
use WCEFP\Utils\DiagnosticLogger;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Experience Catalog Shortcode class
 */
class ExperiencesCatalog {
    
    /**
     * Initialize shortcode
     */
    public static function init() {
        add_shortcode('wcefp_experiences', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_wcefp_filter_experiences', [__CLASS__, 'ajax_filter_experiences']);
        add_action('wp_ajax_nopriv_wcefp_filter_experiences', [__CLASS__, 'ajax_filter_experiences']);
        
        DiagnosticLogger::instance()->debug('Experiences Catalog shortcode initialized', [], 'shortcodes');
    }
    
    /**
     * Render experiences catalog shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render($atts) {
        $atts = shortcode_atts([
            'limit' => 12,
            'columns' => 3,
            'category' => '',
            'featured_only' => false,
            'show_filters' => true,
            'show_search' => true,
            'show_sorting' => true,
            'show_pagination' => true,
            'layout' => 'grid', // grid or list
            'class' => ''
        ], $atts);
        
        // Enqueue assets for this specific instance
        self::enqueue_assets_inline();
        
        // Start output buffering
        ob_start();
        
        // Generate unique ID for this catalog instance
        $catalog_id = 'wcefp-catalog-' . wp_generate_password(6, false, false);
        
        ?>
        <div id="<?php echo esc_attr($catalog_id); ?>" class="wcefp-experiences-catalog <?php echo esc_attr($atts['class']); ?>" data-attributes="<?php echo esc_attr(json_encode($atts)); ?>">
            
            <?php if ($atts['show_filters'] || $atts['show_search'] || $atts['show_sorting']): ?>
            <div class="wcefp-catalog-controls">
                <div class="wcefp-controls-row">
                    
                    <?php if ($atts['show_search']): ?>
                    <div class="wcefp-search-wrapper">
                        <input type="search" 
                               class="wcefp-experience-search" 
                               placeholder="<?php esc_attr_e('Search experiences...', 'wceventsfp'); ?>"
                               aria-label="<?php esc_attr_e('Search experiences', 'wceventsfp'); ?>">
                        <svg class="wcefp-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_filters']): ?>
                    <div class="wcefp-filters-wrapper">
                        <?php echo self::render_category_filter($atts['category']); ?>
                        <?php echo self::render_difficulty_filter(); ?>
                        <?php echo self::render_duration_filter(); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_sorting']): ?>
                    <div class="wcefp-sort-wrapper">
                        <select class="wcefp-sort-select" aria-label="<?php esc_attr_e('Sort experiences', 'wceventsfp'); ?>">
                            <option value="popularity"><?php esc_html_e('Most Popular', 'wceventsfp'); ?></option>
                            <option value="price_asc"><?php esc_html_e('Price: Low to High', 'wceventsfp'); ?></option>
                            <option value="price_desc"><?php esc_html_e('Price: High to Low', 'wceventsfp'); ?></option>
                            <option value="rating"><?php esc_html_e('Highest Rated', 'wceventsfp'); ?></option>
                            <option value="duration_asc"><?php esc_html_e('Duration: Short to Long', 'wceventsfp'); ?></option>
                            <option value="newest"><?php esc_html_e('Newest First', 'wceventsfp'); ?></option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="wcefp-layout-toggle">
                        <button type="button" class="wcefp-layout-btn <?php echo $atts['layout'] === 'grid' ? 'active' : ''; ?>" data-layout="grid" aria-label="<?php esc_attr_e('Grid view', 'wceventsfp'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <rect x="3" y="3" width="7" height="7" stroke="currentColor" stroke-width="1.5"/>
                                <rect x="14" y="3" width="7" height="7" stroke="currentColor" stroke-width="1.5"/>
                                <rect x="3" y="14" width="7" height="7" stroke="currentColor" stroke-width="1.5"/>
                                <rect x="14" y="14" width="7" height="7" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                        </button>
                        <button type="button" class="wcefp-layout-btn <?php echo $atts['layout'] === 'list' ? 'active' : ''; ?>" data-layout="list" aria-label="<?php esc_attr_e('List view', 'wceventsfp'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <rect x="3" y="5" width="18" height="4" stroke="currentColor" stroke-width="1.5"/>
                                <rect x="3" y="13" width="18" height="4" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                        </button>
                    </div>
                    
                </div>
            </div>
            <?php endif; ?>
            
            <div class="wcefp-catalog-results">
                <div class="wcefp-loading" style="display: none;">
                    <div class="wcefp-spinner"></div>
                    <p><?php esc_html_e('Loading experiences...', 'wceventsfp'); ?></p>
                </div>
                
                <div class="wcefp-experiences-grid layout-<?php echo esc_attr($atts['layout']); ?> columns-<?php echo esc_attr($atts['columns']); ?>">
                    <?php echo self::render_experiences($atts); ?>
                </div>
                
                <div class="wcefp-no-results" style="display: none;">
                    <div class="wcefp-no-results-content">
                        <svg class="wcefp-no-results-icon" width="48" height="48" viewBox="0 0 24 24" fill="none">
                            <path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3><?php esc_html_e('No experiences found', 'wceventsfp'); ?></h3>
                        <p><?php esc_html_e('Try adjusting your search or filter criteria.', 'wceventsfp'); ?></p>
                        <button type="button" class="wcefp-btn wcefp-clear-filters"><?php esc_html_e('Clear Filters', 'wceventsfp'); ?></button>
                    </div>
                </div>
            </div>
            
            <?php if ($atts['show_pagination']): ?>
            <div class="wcefp-pagination-wrapper">
                <!-- Pagination will be populated via JavaScript -->
            </div>
            <?php endif; ?>
            
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render experiences based on parameters
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    private static function render_experiences($atts) {
        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
        
        $query_args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'paged' => $paged,
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => ['experience', 'esperienza'],
                    'operator' => 'IN'
                ]
            ],
            'meta_query' => [
                [
                    'key' => '_stock_status',
                    'value' => 'instock',
                    'compare' => '='
                ]
            ]
        ];
        
        // Category filter
        if (!empty($atts['category'])) {
            $query_args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => explode(',', $atts['category']),
                'operator' => 'IN'
            ];
        }
        
        // Featured only
        if ($atts['featured_only']) {
            $query_args['meta_query'][] = [
                'key' => '_featured',
                'value' => 'yes',
                'compare' => '='
            ];
        }
        
        $experiences_query = new WP_Query($query_args);
        
        $output = '';
        
        if ($experiences_query->have_posts()) {
            while ($experiences_query->have_posts()) {
                $experiences_query->the_post();
                $output .= self::render_experience_card(get_post(), $atts['layout']);
            }
            wp_reset_postdata();
        } else {
            $output = '<div class="wcefp-no-experiences">' . 
                      '<p>' . esc_html__('No experiences available at the moment.', 'wceventsfp') . '</p>' .
                      '</div>';
        }
        
        return $output;
    }
    
    /**
     * Render individual experience card
     * 
     * @param WP_Post $post Experience post
     * @param string $layout Layout type (grid or list)
     * @return string HTML output
     */
    private static function render_experience_card($post, $layout = 'grid') {
        $product = wc_get_product($post->ID);
        if (!$product) {
            return '';
        }
        
        $thumbnail = get_the_post_thumbnail($post->ID, 'medium_large');
        $price = $product->get_price_html();
        $rating = $product->get_average_rating();
        $review_count = $product->get_review_count();
        
        // Experience metadata
        $duration = get_post_meta($post->ID, '_wcefp_duration', true);
        $location = get_post_meta($post->ID, '_wcefp_location', true);
        $difficulty = get_post_meta($post->ID, '_wcefp_difficulty_level', true);
        $capacity = get_post_meta($post->ID, '_wcefp_capacity', true);
        
        $card_class = "wcefp-experience-card layout-{$layout}";
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($card_class); ?>" data-experience-id="<?php echo esc_attr($post->ID); ?>">
            
            <div class="wcefp-card-image">
                <?php if ($thumbnail): ?>
                    <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" aria-label="<?php echo esc_attr(get_the_title($post->ID)); ?>">
                        <?php echo $thumbnail; ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" class="wcefp-placeholder-image" aria-label="<?php echo esc_attr(get_the_title($post->ID)); ?>">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="1.5"/>
                            <circle cx="8.5" cy="8.5" r="1.5" stroke="currentColor" stroke-width="1.5"/>
                            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" stroke="currentColor" stroke-width="1.5"/>
                        </svg>
                    </a>
                <?php endif; ?>
                
                <?php if ($product->is_on_sale()): ?>
                    <span class="wcefp-sale-badge"><?php esc_html_e('Sale', 'wceventsfp'); ?></span>
                <?php endif; ?>
                
                <?php if (get_post_meta($post->ID, '_featured', true) === 'yes'): ?>
                    <span class="wcefp-featured-badge"><?php esc_html_e('Featured', 'wceventsfp'); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="wcefp-card-content">
                
                <div class="wcefp-card-header">
                    <?php if ($location): ?>
                        <div class="wcefp-location">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" stroke="currentColor" stroke-width="1.5"/>
                                <circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <span><?php echo esc_html($location); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($rating > 0): ?>
                        <div class="wcefp-rating">
                            <div class="wcefp-stars" aria-label="<?php echo esc_attr(sprintf(__('%s out of 5 stars', 'wceventsfp'), $rating)); ?>">
                                <?php echo self::render_star_rating($rating); ?>
                            </div>
                            <?php if ($review_count > 0): ?>
                                <span class="wcefp-review-count">(<?php echo esc_html($review_count); ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h3 class="wcefp-card-title">
                    <a href="<?php echo esc_url(get_permalink($post->ID)); ?>">
                        <?php echo esc_html(get_the_title($post->ID)); ?>
                    </a>
                </h3>
                
                <div class="wcefp-card-excerpt">
                    <?php echo wp_trim_words(get_the_excerpt($post->ID), 20, '...'); ?>
                </div>
                
                <div class="wcefp-card-meta">
                    <?php if ($duration): ?>
                        <div class="wcefp-meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
                                <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <span><?php echo esc_html(self::format_duration($duration)); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($difficulty): ?>
                        <div class="wcefp-meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <span><?php echo esc_html(self::get_difficulty_label($difficulty)); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($capacity): ?>
                        <div class="wcefp-meta-item">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.5"/>
                                <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="1.5"/>
                            </svg>
                            <span><?php echo esc_html(sprintf(__('Up to %d people', 'wceventsfp'), $capacity)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="wcefp-card-footer">
                    <div class="wcefp-price">
                        <?php echo $price; ?>
                    </div>
                    <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" class="wcefp-btn wcefp-btn-primary">
                        <?php esc_html_e('View Details', 'wceventsfp'); ?>
                    </a>
                </div>
                
            </div>
            
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render category filter dropdown
     * 
     * @param string $selected Selected category slug
     * @return string HTML output
     */
    private static function render_category_filter($selected = '') {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'meta_query' => [
                [
                    'key' => 'wcefp_experience_category',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);
        
        if (empty($categories) || is_wp_error($categories)) {
            return '';
        }
        
        ob_start();
        ?>
        <select class="wcefp-filter-select" data-filter="category" aria-label="<?php esc_attr_e('Filter by category', 'wceventsfp'); ?>">
            <option value=""><?php esc_html_e('All Categories', 'wceventsfp'); ?></option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo esc_attr($category->slug); ?>" <?php selected($selected, $category->slug); ?>>
                    <?php echo esc_html($category->name); ?> (<?php echo esc_html($category->count); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render difficulty filter dropdown
     * 
     * @return string HTML output
     */
    private static function render_difficulty_filter() {
        $difficulties = [
            'easy' => __('Easy', 'wceventsfp'),
            'moderate' => __('Moderate', 'wceventsfp'),
            'challenging' => __('Challenging', 'wceventsfp'),
            'difficult' => __('Difficult', 'wceventsfp')
        ];
        
        ob_start();
        ?>
        <select class="wcefp-filter-select" data-filter="difficulty" aria-label="<?php esc_attr_e('Filter by difficulty', 'wceventsfp'); ?>">
            <option value=""><?php esc_html_e('All Difficulties', 'wceventsfp'); ?></option>
            <?php foreach ($difficulties as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render duration filter dropdown
     * 
     * @return string HTML output
     */
    private static function render_duration_filter() {
        $durations = [
            '0-60' => __('Up to 1 hour', 'wceventsfp'),
            '60-180' => __('1-3 hours', 'wceventsfp'),
            '180-360' => __('3-6 hours', 'wceventsfp'),
            '360-999' => __('6+ hours', 'wceventsfp')
        ];
        
        ob_start();
        ?>
        <select class="wcefp-filter-select" data-filter="duration" aria-label="<?php esc_attr_e('Filter by duration', 'wceventsfp'); ?>">
            <option value=""><?php esc_html_e('Any Duration', 'wceventsfp'); ?></option>
            <?php foreach ($durations as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
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
                $stars .= '<svg class="wcefp-star filled" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>';
            } elseif ($i - 0.5 <= $rating) {
                $stars .= '<svg class="wcefp-star half" width="12" height="12" viewBox="0 0 24 24"><defs><linearGradient id="half-fill"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="transparent"/></linearGradient></defs><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" fill="url(#half-fill)" stroke="currentColor" stroke-width="1"/></svg>';
            } else {
                $stars .= '<svg class="wcefp-star empty" width="12" height="12" viewBox="0 0 24 24" fill="none"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26" stroke="currentColor" stroke-width="1"/></svg>';
            }
        }
        
        return $stars;
    }
    
    /**
     * AJAX handler for filtering experiences
     */
    public static function ajax_filter_experiences() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcefp_catalog_nonce')) {
            wp_die(__('Security check failed', 'wceventsfp'));
        }
        
        $filters = $_POST['filters'] ?? [];
        $search = sanitize_text_field($_POST['search'] ?? '');
        $sort = sanitize_text_field($_POST['sort'] ?? 'popularity');
        $layout = sanitize_text_field($_POST['layout'] ?? 'grid');
        $page = max(1, intval($_POST['page'] ?? 1));
        $limit = max(1, min(50, intval($_POST['limit'] ?? 12)));
        
        // Build query arguments
        $query_args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'paged' => $page,
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => ['experience', 'esperienza'],
                    'operator' => 'IN'
                ]
            ],
            'meta_query' => [
                [
                    'key' => '_stock_status',
                    'value' => 'instock',
                    'compare' => '='
                ]
            ]
        ];
        
        // Search
        if (!empty($search)) {
            $query_args['s'] = $search;
        }
        
        // Category filter
        if (!empty($filters['category'])) {
            $query_args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => sanitize_text_field($filters['category']),
                'operator' => 'IN'
            ];
        }
        
        // Difficulty filter
        if (!empty($filters['difficulty'])) {
            $query_args['meta_query'][] = [
                'key' => '_wcefp_difficulty_level',
                'value' => sanitize_text_field($filters['difficulty']),
                'compare' => '='
            ];
        }
        
        // Duration filter
        if (!empty($filters['duration'])) {
            $duration_range = explode('-', sanitize_text_field($filters['duration']));
            if (count($duration_range) === 2) {
                $query_args['meta_query'][] = [
                    'key' => '_wcefp_duration',
                    'value' => [intval($duration_range[0]), intval($duration_range[1])],
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN'
                ];
            }
        }
        
        // Sorting
        switch ($sort) {
            case 'price_asc':
                $query_args['orderby'] = 'meta_value_num';
                $query_args['meta_key'] = '_price';
                $query_args['order'] = 'ASC';
                break;
            case 'price_desc':
                $query_args['orderby'] = 'meta_value_num';
                $query_args['meta_key'] = '_price';
                $query_args['order'] = 'DESC';
                break;
            case 'rating':
                $query_args['orderby'] = 'meta_value_num';
                $query_args['meta_key'] = '_wc_average_rating';
                $query_args['order'] = 'DESC';
                break;
            case 'duration_asc':
                $query_args['orderby'] = 'meta_value_num';
                $query_args['meta_key'] = '_wcefp_duration';
                $query_args['order'] = 'ASC';
                break;
            case 'newest':
                $query_args['orderby'] = 'date';
                $query_args['order'] = 'DESC';
                break;
            default: // popularity
                $query_args['orderby'] = 'meta_value_num';
                $query_args['meta_key'] = 'total_sales';
                $query_args['order'] = 'DESC';
                break;
        }
        
        $experiences_query = new WP_Query($query_args);
        
        $html = '';
        if ($experiences_query->have_posts()) {
            while ($experiences_query->have_posts()) {
                $experiences_query->the_post();
                $html .= self::render_experience_card(get_post(), $layout);
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success([
            'html' => $html,
            'found_posts' => $experiences_query->found_posts,
            'max_num_pages' => $experiences_query->max_num_pages,
            'current_page' => $page
        ]);
    }
    
    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_scripts() {
        // Only enqueue on pages that use the shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'wcefp_experiences')) {
            self::enqueue_assets();
        }
    }
    
    /**
     * Enqueue assets for the catalog
     */
    private static function enqueue_assets() {
        wp_enqueue_style(
            'wcefp-catalog',
            WCEFP_PLUGIN_URL . 'assets/fe/catalog.css',
            [],
            WCEFP_VERSION
        );
        
        wp_enqueue_script(
            'wcefp-catalog',
            WCEFP_PLUGIN_URL . 'assets/fe/catalog.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-catalog', 'wcefp_catalog', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_catalog_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'wceventsfp'),
                'no_results' => __('No experiences found.', 'wceventsfp'),
                'error' => __('An error occurred while loading experiences.', 'wceventsfp'),
                'prev_page' => __('Previous', 'wceventsfp'),
                'next_page' => __('Next', 'wceventsfp'),
            ]
        ]);
    }
    
    /**
     * Enqueue assets inline (for dynamic shortcode instances)
     */
    private static function enqueue_assets_inline() {
        static $assets_enqueued = false;
        
        if (!$assets_enqueued) {
            add_action('wp_footer', function() {
                // Inline CSS and JS will be added here if external files don't exist
                $css_file = WCEFP_PLUGIN_DIR . 'assets/fe/catalog.css';
                $js_file = WCEFP_PLUGIN_DIR . 'assets/fe/catalog.js';
                
                if (!file_exists($css_file)) {
                    echo '<style id="wcefp-catalog-inline-css">' . self::get_inline_css() . '</style>';
                }
                
                if (!file_exists($js_file)) {
                    echo '<script id="wcefp-catalog-inline-js">' . self::get_inline_js() . '</script>';
                }
            });
            $assets_enqueued = true;
        }
    }
    
    /**
     * Get inline CSS as fallback
     * 
     * @return string CSS content
     */
    private static function get_inline_css() {
        return '
.wcefp-experiences-catalog {
    margin: 2rem 0;
}

.wcefp-catalog-controls {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.wcefp-controls-row {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.wcefp-search-wrapper {
    position: relative;
    flex: 1;
    min-width: 200px;
}

.wcefp-experience-search {
    width: 100%;
    padding: 0.75rem 2.5rem 0.75rem 1rem;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    font-size: 14px;
}

.wcefp-search-icon {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

.wcefp-filters-wrapper {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.wcefp-filter-select, .wcefp-sort-select {
    padding: 0.5rem 0.75rem;
    border: 2px solid #e1e5e9;
    border-radius: 6px;
    background: white;
    min-width: 150px;
}

.wcefp-layout-toggle {
    display: flex;
    gap: 0.25rem;
}

.wcefp-layout-btn {
    padding: 0.5rem;
    border: 2px solid #e1e5e9;
    background: white;
    border-radius: 6px;
    cursor: pointer;
}

.wcefp-layout-btn.active {
    background: #007cba;
    border-color: #007cba;
    color: white;
}

.wcefp-experiences-grid {
    display: grid;
    gap: 2rem;
}

.wcefp-experiences-grid.columns-1 { grid-template-columns: 1fr; }
.wcefp-experiences-grid.columns-2 { grid-template-columns: repeat(2, 1fr); }
.wcefp-experiences-grid.columns-3 { grid-template-columns: repeat(3, 1fr); }
.wcefp-experiences-grid.columns-4 { grid-template-columns: repeat(4, 1fr); }

.wcefp-experiences-grid.layout-list {
    grid-template-columns: 1fr;
}

.wcefp-experience-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.wcefp-experience-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}

.wcefp-experience-card.layout-list {
    display: flex;
}

.wcefp-experience-card.layout-list .wcefp-card-image {
    flex: 0 0 300px;
}

.wcefp-card-image {
    position: relative;
    aspect-ratio: 16/10;
    overflow: hidden;
}

.wcefp-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.wcefp-placeholder-image {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    background: #f0f0f0;
    color: #999;
}

.wcefp-sale-badge, .wcefp-featured-badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.wcefp-sale-badge {
    background: #e74c3c;
}

.wcefp-featured-badge {
    background: #f39c12;
}

.wcefp-card-content {
    padding: 1.5rem;
}

.wcefp-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.wcefp-location {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: #6c757d;
    font-size: 14px;
}

.wcefp-rating {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.wcefp-stars {
    display: flex;
    gap: 2px;
}

.wcefp-star.filled {
    color: #ffc107;
}

.wcefp-star.half {
    color: #ffc107;
}

.wcefp-star.empty {
    color: #e9ecef;
}

.wcefp-review-count {
    color: #6c757d;
    font-size: 14px;
}

.wcefp-card-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 0.75rem 0;
}

.wcefp-card-title a {
    color: inherit;
    text-decoration: none;
}

.wcefp-card-excerpt {
    color: #6c757d;
    margin-bottom: 1rem;
    line-height: 1.5;
}

.wcefp-card-meta {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.wcefp-meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: #6c757d;
    font-size: 14px;
}

.wcefp-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.wcefp-price {
    font-size: 1.25rem;
    font-weight: 600;
    color: #28a745;
}

.wcefp-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    cursor: pointer;
    border: none;
    transition: background-color 0.2s;
}

.wcefp-btn-primary {
    background: #007cba;
    color: white;
}

.wcefp-btn-primary:hover {
    background: #005a87;
    color: white;
}

.wcefp-loading {
    text-align: center;
    padding: 3rem;
}

.wcefp-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007cba;
    border-radius: 50%;
    animation: wcefp-spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes wcefp-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.wcefp-no-results {
    text-align: center;
    padding: 3rem;
}

.wcefp-no-results-icon {
    color: #6c757d;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .wcefp-controls-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .wcefp-filters-wrapper {
        order: 1;
    }
    
    .wcefp-search-wrapper {
        order: 2;
    }
    
    .wcefp-experiences-grid.columns-2,
    .wcefp-experiences-grid.columns-3,
    .wcefp-experiences-grid.columns-4 {
        grid-template-columns: 1fr;
    }
    
    .wcefp-experience-card.layout-list {
        flex-direction: column;
    }
    
    .wcefp-experience-card.layout-list .wcefp-card-image {
        flex: none;
    }
}
        ';
    }
    
    /**
     * Get inline JavaScript as fallback
     * 
     * @return string JavaScript content
     */
    private static function get_inline_js() {
        return '
(function($) {
    "use strict";
    
    var WCEFPCatalog = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Search functionality
            $(document).on("input", ".wcefp-experience-search", function() {
                var $catalog = $(this).closest(".wcefp-experiences-catalog");
                self.debounce(function() {
                    self.filterExperiences($catalog);
                }, 500)();
            });
            
            // Filter dropdowns
            $(document).on("change", ".wcefp-filter-select", function() {
                var $catalog = $(this).closest(".wcefp-experiences-catalog");
                self.filterExperiences($catalog);
            });
            
            // Sort dropdown
            $(document).on("change", ".wcefp-sort-select", function() {
                var $catalog = $(this).closest(".wcefp-experiences-catalog");
                self.filterExperiences($catalog);
            });
            
            // Layout toggle
            $(document).on("click", ".wcefp-layout-btn", function() {
                var $catalog = $(this).closest(".wcefp-experiences-catalog");
                var layout = $(this).data("layout");
                
                $(this).addClass("active").siblings().removeClass("active");
                $catalog.find(".wcefp-experiences-grid")
                    .removeClass("layout-grid layout-list")
                    .addClass("layout-" + layout);
                
                $catalog.find(".wcefp-experience-card")
                    .removeClass("layout-grid layout-list")
                    .addClass("layout-" + layout);
            });
            
            // Clear filters
            $(document).on("click", ".wcefp-clear-filters", function() {
                var $catalog = $(this).closest(".wcefp-experiences-catalog");
                $catalog.find(".wcefp-experience-search").val("");
                $catalog.find(".wcefp-filter-select").val("");
                $catalog.find(".wcefp-sort-select").val("popularity");
                self.filterExperiences($catalog);
            });
        },
        
        filterExperiences: function($catalog) {
            var self = this;
            var $results = $catalog.find(".wcefp-catalog-results");
            var $grid = $catalog.find(".wcefp-experiences-grid");
            var $loading = $catalog.find(".wcefp-loading");
            var $noResults = $catalog.find(".wcefp-no-results");
            
            // Collect filter data
            var filters = {};
            $catalog.find(".wcefp-filter-select").each(function() {
                var filter = $(this).data("filter");
                var value = $(this).val();
                if (value) {
                    filters[filter] = value;
                }
            });
            
            var data = {
                action: "wcefp_filter_experiences",
                nonce: wcefp_catalog.nonce,
                search: $catalog.find(".wcefp-experience-search").val(),
                sort: $catalog.find(".wcefp-sort-select").val(),
                layout: $catalog.find(".wcefp-layout-btn.active").data("layout"),
                filters: filters,
                page: 1,
                limit: JSON.parse($catalog.data("attributes") || "{}").limit || 12
            };
            
            // Show loading
            $grid.hide();
            $noResults.hide();
            $loading.show();
            
            $.post(wcefp_catalog.ajaxurl, data)
                .done(function(response) {
                    if (response.success) {
                        $grid.html(response.data.html);
                        
                        if (response.data.found_posts > 0) {
                            $grid.show();
                            $noResults.hide();
                        } else {
                            $grid.hide();
                            $noResults.show();
                        }
                    } else {
                        self.showError($catalog);
                    }
                })
                .fail(function() {
                    self.showError($catalog);
                })
                .always(function() {
                    $loading.hide();
                });
        },
        
        showError: function($catalog) {
            var $grid = $catalog.find(".wcefp-experiences-grid");
            $grid.html("<div class=\"wcefp-error\">" + wcefp_catalog.strings.error + "</div>");
        },
        
        debounce: function(func, wait) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        }
    };
    
    $(document).ready(function() {
        WCEFPCatalog.init();
    });
    
})(jQuery);
        ';
    }
}