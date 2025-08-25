<?php
/**
 * Experience Archive Manager
 * 
 * Manages the special archive for experiences (/esperienze/) with custom endpoint,
 * filtering, and template rendering
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
 * Experience Archive Manager Class
 * 
 * Handles the special experience archive functionality
 */
class ExperienceArchiveManager {
    
    /**
     * Archive slug
     */
    const ARCHIVE_SLUG = 'esperienze';
    
    /**
     * Items per page
     */
    const ITEMS_PER_PAGE = 12;
    
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
        // Add rewrite rules for the archive
        add_action('init', [$this, 'add_rewrite_rules']);
        
        // Handle archive queries
        add_action('pre_get_posts', [$this, 'modify_archive_query']);
        
        // Handle template loading
        add_action('template_redirect', [$this, 'handle_archive_template']);
        
        // Add shortcode for archive display
        add_shortcode('wcefp_experiences_archive', [$this, 'archive_shortcode']);
        
        // Enqueue archive assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_archive_assets']);
        
        // Add breadcrumb navigation
        add_filter('woocommerce_get_breadcrumb', [$this, 'add_experience_breadcrumbs'], 10, 2);
        
        // Add AJAX handlers for filtering
        add_action('wp_ajax_wcefp_filter_experiences', [$this, 'handle_ajax_filter']);
        add_action('wp_ajax_nopriv_wcefp_filter_experiences', [$this, 'handle_ajax_filter']);
    }
    
    /**
     * Add rewrite rules for experience archive
     * 
     * @return void
     */
    public function add_rewrite_rules() {
        // Add rewrite rule for /esperienze/
        add_rewrite_rule(
            '^' . self::ARCHIVE_SLUG . '/?$',
            'index.php?wcefp_experience_archive=1',
            'top'
        );
        
        // Add rewrite rule for /esperienze/page/2/
        add_rewrite_rule(
            '^' . self::ARCHIVE_SLUG . '/page/([0-9]+)/?$',
            'index.php?wcefp_experience_archive=1&paged=$matches[1]',
            'top'
        );
        
        // Add rewrite tag
        add_rewrite_tag('%wcefp_experience_archive%', '([^&]+)');
        
        // Flush rewrite rules if needed
        if (get_option('wcefp_experience_archive_flush_needed', false)) {
            flush_rewrite_rules();
            delete_option('wcefp_experience_archive_flush_needed');
        }
    }
    
    /**
     * Handle archive template loading
     * 
     * @return void
     */
    public function handle_archive_template() {
        if (!get_query_var('wcefp_experience_archive')) {
            return;
        }
        
        // Set up the custom query for experiences
        global $wp_query;
        
        // Get current page
        $paged = get_query_var('paged', 1);
        
        // Create new query for experiences
        $experience_query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => self::ITEMS_PER_PAGE,
            'paged' => $paged,
            'meta_query' => [
                [
                    'key' => '_wcefp_is_experience',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'orderby' => 'menu_order title',
            'order' => 'ASC'
        ]);
        
        // Replace global query
        $wp_query = $experience_query;
        
        // Load template
        $template = $this->locate_archive_template();
        include $template;
        exit;
    }
    
    /**
     * Locate archive template
     * 
     * @return string Template path
     */
    private function locate_archive_template() {
        // Look for theme override first
        $theme_template = locate_template([
            'wcefp/archive-experiences.php',
            'wcefp/experiences-archive.php',
            'archive-experiences.php'
        ]);
        
        if ($theme_template) {
            return $theme_template;
        }
        
        // Use plugin template
        $plugin_template = dirname(__FILE__) . '/../../templates/archive-experiences.php';
        
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        // Fallback to generic template
        return $this->create_fallback_template();
    }
    
    /**
     * Create fallback template content
     * 
     * @return string Template path
     */
    private function create_fallback_template() {
        $fallback_path = '/tmp/wcefp-archive-experiences.php';
        
        if (!file_exists($fallback_path)) {
            $template_content = $this->get_fallback_template_content();
            file_put_contents($fallback_path, $template_content);
        }
        
        return $fallback_path;
    }
    
    /**
     * Get fallback template content
     * 
     * @return string Template HTML
     */
    private function get_fallback_template_content() {
        return '<?php
get_header();

// Title and description
$archive_title = __("Esperienze", "wceventsfp");
$archive_description = __("Scopri le nostre esperienze uniche", "wceventsfp");
?>

<div class="wcefp-experiences-archive">
    <div class="container">
        <header class="archive-header">
            <h1 class="archive-title"><?php echo esc_html($archive_title); ?></h1>
            <?php if ($archive_description): ?>
                <p class="archive-description"><?php echo esc_html($archive_description); ?></p>
            <?php endif; ?>
        </header>
        
        <div class="experiences-grid">
            <?php if (have_posts()): ?>
                <?php while (have_posts()): the_post(); ?>
                    <div class="experience-card">
                        <?php echo do_shortcode("[wcefp_experience_card id=\"" . get_the_ID() . "\"]"); ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p><?php _e("Nessuna esperienza trovata.", "wceventsfp"); ?></p>
            <?php endif; ?>
        </div>
        
        <?php
        // Pagination
        the_posts_pagination([
            "prev_text" => __("« Precedente", "wceventsfp"),
            "next_text" => __("Successivo »", "wceventsfp"),
        ]);
        ?>
    </div>
</div>

<?php get_footer(); ?>';
    }
    
    /**
     * Archive shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function archive_shortcode($atts) {
        $atts = shortcode_atts([
            'per_page' => self::ITEMS_PER_PAGE,
            'columns' => 3,
            'show_filters' => 'yes',
            'show_search' => 'yes',
            'layout' => 'grid', // grid, list, masonry
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ], $atts);
        
        // Get experiences
        $experiences = $this->get_experiences_query($atts);
        
        if (!$experiences->have_posts()) {
            return '<p class="wcefp-no-experiences">' . __('Nessuna esperienza disponibile.', 'wceventsfp') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="wcefp-experiences-archive-shortcode">
            <?php if ($atts['show_filters'] === 'yes' || $atts['show_search'] === 'yes'): ?>
                <div class="wcefp-archive-filters">
                    <?php if ($atts['show_search'] === 'yes'): ?>
                        <div class="wcefp-archive-search">
                            <input type="search" placeholder="<?php esc_attr_e('Cerca esperienze...', 'wceventsfp'); ?>" 
                                   class="wcefp-search-input">
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_filters'] === 'yes'): ?>
                        <div class="wcefp-archive-filter-controls">
                            <?php echo $this->render_filter_controls(); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="wcefp-experiences-grid wcefp-layout-<?php echo esc_attr($atts['layout']); ?> wcefp-columns-<?php echo esc_attr($atts['columns']); ?>">
                <?php while ($experiences->have_posts()): $experiences->the_post(); ?>
                    <div class="wcefp-experience-item">
                        <?php echo $this->render_experience_card(get_the_ID()); ?>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <?php if ($experiences->max_num_pages > 1): ?>
                <div class="wcefp-archive-pagination">
                    <?php echo $this->render_pagination($experiences); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    /**
     * Get experiences query
     * 
     * @param array $atts Query parameters
     * @return \WP_Query
     */
    private function get_experiences_query($atts) {
        $query_args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['per_page']),
            'meta_query' => [
                [
                    'key' => '_wcefp_is_experience',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'orderby' => sanitize_text_field($atts['orderby']),
            'order' => sanitize_text_field($atts['order'])
        ];
        
        // Handle pagination for shortcode
        if (get_query_var('paged')) {
            $query_args['paged'] = get_query_var('paged');
        }
        
        return new \WP_Query($query_args);
    }
    
    /**
     * Render experience card
     * 
     * @param int $product_id Product ID
     * @return string
     */
    private function render_experience_card($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="wcefp-experience-card" data-product-id="<?php echo esc_attr($product_id); ?>">
            <div class="wcefp-card-image">
                <a href="<?php echo esc_url(get_permalink($product_id)); ?>">
                    <?php echo $product->get_image('woocommerce_thumbnail'); ?>
                </a>
            </div>
            
            <div class="wcefp-card-content">
                <h3 class="wcefp-card-title">
                    <a href="<?php echo esc_url(get_permalink($product_id)); ?>">
                        <?php echo esc_html($product->get_name()); ?>
                    </a>
                </h3>
                
                <?php if ($product->get_short_description()): ?>
                    <div class="wcefp-card-excerpt">
                        <?php echo wp_kses_post($product->get_short_description()); ?>
                    </div>
                <?php endif; ?>
                
                <div class="wcefp-card-meta">
                    <div class="wcefp-card-price">
                        <?php echo $product->get_price_html(); ?>
                    </div>
                    
                    <?php
                    // Show duration if available
                    $duration = get_post_meta($product_id, '_wcefp_duration', true);
                    if ($duration):
                    ?>
                        <div class="wcefp-card-duration">
                            <span class="wcefp-duration-icon">🕐</span>
                            <?php echo esc_html($duration); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php
                    // Show rating if available
                    if ($product->get_average_rating()):
                    ?>
                        <div class="wcefp-card-rating">
                            <?php echo wc_get_rating_html($product->get_average_rating()); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="wcefp-card-actions">
                    <a href="<?php echo esc_url(get_permalink($product_id)); ?>" 
                       class="wcefp-view-experience-btn">
                        <?php _e('Scopri di più', 'wceventsfp'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render filter controls
     * 
     * @return string
     */
    private function render_filter_controls() {
        ob_start();
        ?>
        <div class="wcefp-filter-group">
            <label for="wcefp-duration-filter"><?php _e('Durata:', 'wceventsfp'); ?></label>
            <select id="wcefp-duration-filter" class="wcefp-filter-select">
                <option value=""><?php _e('Tutte le durate', 'wceventsfp'); ?></option>
                <option value="1-3h"><?php _e('1-3 ore', 'wceventsfp'); ?></option>
                <option value="3-6h"><?php _e('3-6 ore', 'wceventsfp'); ?></option>
                <option value="full-day"><?php _e('Giornata intera', 'wceventsfp'); ?></option>
            </select>
        </div>
        
        <div class="wcefp-filter-group">
            <label for="wcefp-price-filter"><?php _e('Prezzo:', 'wceventsfp'); ?></label>
            <select id="wcefp-price-filter" class="wcefp-filter-select">
                <option value=""><?php _e('Tutti i prezzi', 'wceventsfp'); ?></option>
                <option value="0-50"><?php _e('€ 0-50', 'wceventsfp'); ?></option>
                <option value="50-100"><?php _e('€ 50-100', 'wceventsfp'); ?></option>
                <option value="100+"><?php _e('€ 100+', 'wceventsfp'); ?></option>
            </select>
        </div>
        
        <div class="wcefp-filter-group">
            <label for="wcefp-sort-filter"><?php _e('Ordina per:', 'wceventsfp'); ?></label>
            <select id="wcefp-sort-filter" class="wcefp-filter-select">
                <option value="menu_order"><?php _e('Posizione', 'wceventsfp'); ?></option>
                <option value="title"><?php _e('Nome A-Z', 'wceventsfp'); ?></option>
                <option value="price"><?php _e('Prezzo', 'wceventsfp'); ?></option>
                <option value="rating"><?php _e('Valutazione', 'wceventsfp'); ?></option>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render pagination
     * 
     * @param \WP_Query $query Query object
     * @return string
     */
    private function render_pagination($query) {
        $current_page = max(1, get_query_var('paged'));
        $total_pages = $query->max_num_pages;
        
        if ($total_pages <= 1) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="wcefp-pagination">
            <?php if ($current_page > 1): ?>
                <a href="<?php echo esc_url(get_pagenum_link($current_page - 1)); ?>" 
                   class="wcefp-pagination-prev">
                    <?php _e('« Precedente', 'wceventsfp'); ?>
                </a>
            <?php endif; ?>
            
            <span class="wcefp-pagination-info">
                <?php printf(
                    __('Pagina %d di %d', 'wceventsfp'), 
                    $current_page, 
                    $total_pages
                ); ?>
            </span>
            
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo esc_url(get_pagenum_link($current_page + 1)); ?>" 
                   class="wcefp-pagination-next">
                    <?php _e('Successivo »', 'wceventsfp'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Add experience breadcrumbs
     * 
     * @param array $crumbs Existing breadcrumbs
     * @param object $breadcrumb Breadcrumb instance
     * @return array
     */
    public function add_experience_breadcrumbs($crumbs, $breadcrumb) {
        // Check if we're on a single experience product page
        if (!is_product()) {
            return $crumbs;
        }
        
        global $post;
        $product = wc_get_product($post->ID);
        
        if (!$product || get_post_meta($post->ID, '_wcefp_is_experience', true) !== '1') {
            return $crumbs;
        }
        
        // Find the position to insert the experiences archive link
        $shop_index = -1;
        foreach ($crumbs as $index => $crumb) {
            if (isset($crumb[1]) && $crumb[1] === wc_get_page_permalink('shop')) {
                $shop_index = $index;
                break;
            }
        }
        
        // Insert experiences archive breadcrumb
        $experiences_crumb = [
            __('Esperienze', 'wceventsfp'),
            home_url('/' . self::ARCHIVE_SLUG . '/')
        ];
        
        if ($shop_index !== -1) {
            // Replace shop with experiences archive
            $crumbs[$shop_index] = $experiences_crumb;
        } else {
            // Insert before product name (usually last item)
            array_splice($crumbs, -1, 0, [$experiences_crumb]);
        }
        
        return $crumbs;
    }
    
    /**
     * Enqueue archive assets
     * 
     * @return void
     */
    public function enqueue_archive_assets() {
        // Only enqueue on archive pages or when shortcode is used
        if (!get_query_var('wcefp_experience_archive') && 
            !$this->has_experience_archive_shortcode()) {
            return;
        }
        
        wp_enqueue_style(
            'wcefp-experience-archive',
            plugin_dir_url(__FILE__) . '../../assets/frontend/css/experience-archive.css',
            [],
            '2.2.0'
        );
        
        wp_enqueue_script(
            'wcefp-experience-archive',
            plugin_dir_url(__FILE__) . '../../assets/frontend/js/experience-archive.js',
            ['jquery'],
            '2.2.0',
            true
        );
        
        wp_localize_script('wcefp-experience-archive', 'wcefp_archive', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_archive_nonce'),
            'i18n' => [
                'loading' => __('Caricamento...', 'wceventsfp'),
                'no_results' => __('Nessun risultato trovato.', 'wceventsfp'),
                'error' => __('Errore nel caricamento.', 'wceventsfp')
            ]
        ]);
    }
    
    /**
     * Check if current page/post has experience archive shortcode
     * 
     * @return bool
     */
    private function has_experience_archive_shortcode() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return has_shortcode($post->post_content, 'wcefp_experiences_archive');
    }
    
    /**
     * Modify archive query
     * 
     * @param \WP_Query $query Query object
     * @return void
     */
    public function modify_archive_query($query) {
        if (!is_admin() && $query->is_main_query() && get_query_var('wcefp_experience_archive')) {
            // This is handled in handle_archive_template method
            return;
        }
    }
    
    /**
     * Get archive URL
     * 
     * @return string
     */
    public static function get_archive_url() {
        return home_url('/' . self::ARCHIVE_SLUG . '/');
    }
    
    /**
     * Get filtered experience count
     * 
     * @return int
     */
    public function get_experience_count() {
        $count_query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_wcefp_is_experience',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ]);
        
        return $count_query->found_posts;
    }
    
    /**
     * Install/activate archive functionality
     * 
     * @return void
     */
    public static function activate() {
        // Trigger rewrite rule flush on next load
        update_option('wcefp_experience_archive_flush_needed', true);
        
        DiagnosticLogger::instance()->log_integration('success', 'Experience archive activated', 'archive', [
            'archive_slug' => self::ARCHIVE_SLUG
        ]);
    }
    
    /**
     * Deactivate archive functionality
     * 
     * @return void
     */
    public static function deactivate() {
        flush_rewrite_rules();
        delete_option('wcefp_experience_archive_flush_needed');
        
        DiagnosticLogger::instance()->log_integration('info', 'Experience archive deactivated', 'archive');
    }
    
    /**
     * Handle AJAX filtering request
     * 
     * @return void
     */
    public function handle_ajax_filter() {
        // Verify nonce
        if (!check_ajax_referer('wcefp_archive_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'wceventsfp')]);
        }
        
        // Get filter parameters
        $search = sanitize_text_field($_POST['search'] ?? '');
        $duration = sanitize_text_field($_POST['duration'] ?? '');
        $price = sanitize_text_field($_POST['price'] ?? '');
        $sort = sanitize_text_field($_POST['sort'] ?? 'menu_order');
        $category = sanitize_text_field($_POST['category'] ?? '');
        $page = max(1, intval($_POST['page'] ?? 1));
        
        // Build query arguments
        $query_args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => self::ITEMS_PER_PAGE,
            'paged' => $page,
            'meta_query' => [
                [
                    'key' => '_wcefp_is_experience',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ];
        
        // Add search
        if (!empty($search)) {
            $query_args['s'] = $search;
        }
        
        // Add category filter
        if (!empty($category)) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $category
                ]
            ];
        }
        
        // Add duration filter
        if (!empty($duration)) {
            $duration_meta = $this->get_duration_meta_query($duration);
            if ($duration_meta) {
                $query_args['meta_query'][] = $duration_meta;
            }
        }
        
        // Add price filter
        if (!empty($price)) {
            $price_meta = $this->get_price_meta_query($price);
            if ($price_meta) {
                $query_args['meta_query'][] = $price_meta;
            }
        }
        
        // Add sorting
        $this->apply_sorting($query_args, $sort);
        
        // Execute query
        $experiences = new \WP_Query($query_args);
        
        // Render results
        ob_start();
        if ($experiences->have_posts()) {
            while ($experiences->have_posts()) {
                $experiences->the_post();
                echo '<div class="wcefp-experience-item">';
                echo $this->render_experience_card(get_the_ID());
                echo '</div>';
            }
        } else {
            echo '<div class="wcefp-no-experiences">';
            echo '<p>' . __('No experiences found with the current filters.', 'wceventsfp') . '</p>';
            echo '</div>';
        }
        $html = ob_get_clean();
        
        // Generate pagination
        $pagination = $this->generate_ajax_pagination($experiences);
        
        wp_reset_postdata();
        
        wp_send_json_success([
            'html' => $html,
            'count' => $experiences->found_posts,
            'pagination' => $pagination,
            'total_pages' => $experiences->max_num_pages
        ]);
    }
    
    /**
     * Get duration meta query
     * 
     * @param string $duration Duration filter value
     * @return array|null Meta query array or null
     */
    private function get_duration_meta_query($duration) {
        switch ($duration) {
            case '1-3h':
                return [
                    'key' => '_wcefp_duration',
                    'value' => [60, 180], // 1-3 hours in minutes
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN'
                ];
            case '3-6h':
                return [
                    'key' => '_wcefp_duration',
                    'value' => [180, 360], // 3-6 hours in minutes
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN'
                ];
            case '6h+':
                return [
                    'key' => '_wcefp_duration',
                    'value' => 360, // More than 6 hours
                    'type' => 'NUMERIC',
                    'compare' => '>='
                ];
            case 'full-day':
                return [
                    'key' => '_wcefp_duration',
                    'value' => [480, 720], // 8-12 hours
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN'
                ];
            case 'multi-day':
                return [
                    'key' => '_wcefp_duration',
                    'value' => 1440, // More than 24 hours
                    'type' => 'NUMERIC',
                    'compare' => '>='
                ];
        }
        return null;
    }
    
    /**
     * Get price meta query
     * 
     * @param string $price Price filter value
     * @return array|null Meta query array or null
     */
    private function get_price_meta_query($price) {
        switch ($price) {
            case '0-25':
                return [
                    'key' => '_price',
                    'value' => [0, 25],
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN'
                ];
            case '25-50':
                return [
                    'key' => '_price',
                    'value' => [25, 50],
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN'
                ];
            case '50-100':
                return [
                    'key' => '_price',
                    'value' => [50, 100],
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN'
                ];
            case '100-200':
                return [
                    'key' => '_price',
                    'value' => [100, 200],
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN'
                ];
            case '200+':
                return [
                    'key' => '_price',
                    'value' => 200,
                    'type' => 'NUMERIC',
                    'compare' => '>='
                ];
        }
        return null;
    }
    
    /**
     * Apply sorting to query arguments
     * 
     * @param array $query_args Query arguments to modify
     * @param string $sort Sort parameter
     * @return void
     */
    private function apply_sorting(&$query_args, $sort) {
        switch ($sort) {
            case 'title':
                $query_args['orderby'] = 'title';
                $query_args['order'] = 'ASC';
                break;
            case 'title_desc':
                $query_args['orderby'] = 'title';
                $query_args['order'] = 'DESC';
                break;
            case 'price':
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
            case 'popularity':
                $query_args['orderby'] = 'meta_value_num';
                $query_args['meta_key'] = 'total_sales';
                $query_args['order'] = 'DESC';
                break;
            case 'date':
                $query_args['orderby'] = 'date';
                $query_args['order'] = 'DESC';
                break;
            case 'menu_order':
            default:
                $query_args['orderby'] = 'menu_order title';
                $query_args['order'] = 'ASC';
                break;
        }
    }
    
    /**
     * Generate AJAX pagination HTML
     * 
     * @param \WP_Query $query Query object
     * @return string Pagination HTML
     */
    private function generate_ajax_pagination($query) {
        if ($query->max_num_pages <= 1) {
            return '';
        }
        
        $current_page = max(1, $query->get('paged'));
        $total_pages = $query->max_num_pages;
        
        ob_start();
        ?>
        <div class="wcefp-ajax-pagination">
            <?php if ($current_page > 1): ?>
                <button type="button" class="wcefp-ajax-page-btn" data-page="<?php echo ($current_page - 1); ?>">
                    <?php _e('« Previous', 'wceventsfp'); ?>
                </button>
            <?php endif; ?>
            
            <span class="wcefp-pagination-info">
                <?php printf(__('Page %d of %d', 'wceventsfp'), $current_page, $total_pages); ?>
            </span>
            
            <?php if ($current_page < $total_pages): ?>
                <button type="button" class="wcefp-ajax-page-btn" data-page="<?php echo ($current_page + 1); ?>">
                    <?php _e('Next »', 'wceventsfp'); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
}