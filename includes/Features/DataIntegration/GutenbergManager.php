<?php
/**
 * Gutenberg Block Manager
 * 
 * Manages custom Gutenberg blocks for WCEventsFP booking forms.
 * 
 * @package WCEFP\Features\DataIntegration
 * @since 2.2.0
 */

namespace WCEFP\Features\DataIntegration;

class GutenbergManager {
    
    /**
     * Initialize Gutenberg block manager
     */
    public function init() {
        // Register blocks
        add_action('init', [$this, 'register_blocks']);
        
        // Enqueue block editor assets
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
        
        // Enqueue frontend assets
        add_action('enqueue_block_assets', [$this, 'enqueue_block_assets']);
        
        // Add block category
        add_filter('block_categories_all', [$this, 'add_block_category'], 10, 2);
        
        // Register REST API endpoints for block editor
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    /**
     * Register custom blocks
     */
    public function register_blocks() {
        // Register booking form block
        register_block_type('wcefp/booking-form', [
            'editor_script' => 'wcefp-block-editor',
            'editor_style' => 'wcefp-block-editor',
            'style' => 'wcefp-block-frontend',
            'render_callback' => [$this, 'render_booking_form_block'],
            'attributes' => [
                'productId' => [
                    'type' => 'number',
                    'default' => 0,
                ],
                'showTitle' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showDescription' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showPrice' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showImages' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'className' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'align' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);
        
        // Register event list block
        register_block_type('wcefp/event-list', [
            'editor_script' => 'wcefp-block-editor',
            'editor_style' => 'wcefp-block-editor',
            'style' => 'wcefp-block-frontend',
            'render_callback' => [$this, 'render_event_list_block'],
            'attributes' => [
                'numberOfEvents' => [
                    'type' => 'number',
                    'default' => 5,
                ],
                'showFeaturedImage' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showExcerpt' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showPrice' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showBookButton' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'className' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);
        
        // Register experiences catalog block (NEW)
        register_block_type('wcefp/experiences-catalog', [
            'editor_script' => 'wcefp-block-editor',
            'editor_style' => 'wcefp-block-editor',
            'style' => 'wcefp-experiences-catalog',
            'render_callback' => [$this, 'render_experiences_catalog_block'],
            'attributes' => [
                'limit' => [
                    'type' => 'number',
                    'default' => 12,
                ],
                'category' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'showFilters' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showMap' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
                'layout' => [
                    'type' => 'string',
                    'default' => 'grid',
                ],
                'columns' => [
                    'type' => 'number',
                    'default' => 3,
                ],
                'orderBy' => [
                    'type' => 'string',
                    'default' => 'date',
                ],
                'orderDir' => [
                    'type' => 'string',
                    'default' => 'DESC',
                ],
                'showPrice' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showRating' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showDuration' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'showLocation' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'className' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'wcefp-block-editor',
            WCEFP_PLUGIN_URL . 'assets/js/blocks/editor.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            WCEFP_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wcefp-block-editor',
            WCEFP_PLUGIN_URL . 'assets/css/blocks/editor.css',
            ['wp-edit-blocks'],
            WCEFP_VERSION
        );
        
        // Localize script with data for block editor
        wp_localize_script('wcefp-block-editor', 'wcefpBlocks', [
            'apiUrl' => rest_url('wcefp/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'strings' => [
                'selectEvent' => __('Select an event', 'wceventsfp'),
                'noEvents' => __('No events found', 'wceventsfp'),
                'loading' => __('Loading...', 'wceventsfp'),
                'error' => __('Error loading events', 'wceventsfp'),
            ],
        ]);
    }
    
    /**
     * Enqueue frontend block assets
     */
    public function enqueue_block_assets() {
        // Only enqueue on pages with WCEFP blocks
        if (!$this->has_wcefp_blocks()) {
            return;
        }
        
        wp_enqueue_style(
            'wcefp-block-frontend',
            WCEFP_PLUGIN_URL . 'assets/css/blocks/frontend.css',
            ['wcefp-widget'],
            WCEFP_VERSION
        );
        
        wp_enqueue_script(
            'wcefp-block-frontend',
            WCEFP_PLUGIN_URL . 'assets/js/blocks/frontend.js',
            ['jquery', 'wcefp-modals'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-block-frontend', 'wcefpBlocksFrontend', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_booking'),
        ]);
    }
    
    /**
     * Add WCEFP block category
     */
    public function add_block_category($categories, $post) {
        return array_merge(
            $categories,
            [
                [
                    'slug' => 'wcefp',
                    'title' => __('WCEventsFP', 'wceventsfp'),
                    'icon' => 'calendar-alt',
                ],
            ]
        );
    }
    
    /**
     * Register REST API routes for block editor
     */
    public function register_rest_routes() {
        register_rest_route('wcefp/v1', '/events', [
            'methods' => 'GET',
            'callback' => [$this, 'get_events_for_blocks'],
            'permission_callback' => [$this, 'check_block_permissions'],
        ]);
        
        register_rest_route('wcefp/v1', '/events/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_event_for_block'],
            'permission_callback' => [$this, 'check_block_permissions'],
        ]);
        
        // Add experiences endpoint for the catalog block
        register_rest_route('wcefp/v1', '/experiences', [
            'methods' => 'GET',
            'callback' => [$this, 'get_experiences_for_block'],
            'permission_callback' => [$this, 'check_block_permissions'],
        ]);
    }
    
    /**
     * Check permissions for block API endpoints
     */
    public function check_block_permissions() {
        return current_user_can('edit_posts');
    }
    
    /**
     * Get events for block editor
     */
    public function get_events_for_blocks($request) {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'meta_query' => [
                [
                    'key' => '_wcefp_is_event',
                    'value' => 'yes',
                    'compare' => '=',
                ],
            ],
        ];
        
        $events = get_posts($args);
        $formatted_events = [];
        
        foreach ($events as $event) {
            $formatted_events[] = [
                'id' => $event->ID,
                'title' => $event->post_title,
                'excerpt' => wp_trim_words($event->post_excerpt ?: $event->post_content, 20),
                'price' => get_post_meta($event->ID, '_price', true),
                'currency' => get_option('woocommerce_currency'),
                'featured_image' => get_the_post_thumbnail_url($event->ID, 'medium'),
            ];
        }
        
        return rest_ensure_response($formatted_events);
    }
    
    /**
     * Get single event for block editor
     */
    public function get_event_for_block($request) {
        $event_id = absint($request['id']);
        $event = get_post($event_id);
        
        if (!$event || $event->post_type !== 'product') {
            return new \WP_Error('event_not_found', __('Event not found', 'wceventsfp'), ['status' => 404]);
        }
        
        $product = wc_get_product($event_id);
        
        return rest_ensure_response([
            'id' => $event->ID,
            'title' => $event->post_title,
            'content' => $event->post_content,
            'excerpt' => $event->post_excerpt,
            'price' => $product->get_price(),
            'sale_price' => $product->get_sale_price(),
            'currency' => get_option('woocommerce_currency'),
            'featured_image' => get_the_post_thumbnail_url($event->ID, 'large'),
            'gallery' => $this->get_product_gallery_images($product),
            'meta' => [
                'duration' => get_post_meta($event->ID, '_wcefp_duration', true),
                'location' => get_post_meta($event->ID, '_wcefp_location', true),
                'max_participants' => get_post_meta($event->ID, '_wcefp_max_participants', true),
            ],
        ]);
    }
    
    /**
     * Get experiences for block editor
     */
    public function get_experiences_for_block($request) {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 50,
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
        
        $experiences = get_posts($args);
        $formatted_experiences = [];
        
        foreach ($experiences as $experience) {
            $product = wc_get_product($experience->ID);
            if (!$product) continue;
            
            $formatted_experiences[] = [
                'id' => $experience->ID,
                'title' => $experience->post_title,
                'excerpt' => wp_trim_words($experience->post_excerpt ?: $experience->post_content, 20),
                'price' => $product->get_price(),
                'currency' => get_woocommerce_currency_symbol(),
                'featured_image' => get_the_post_thumbnail_url($experience->ID, 'medium'),
                'categories' => wp_get_post_terms($experience->ID, 'product_cat', ['fields' => 'names']),
                'rating' => $product->get_average_rating(),
                'review_count' => $product->get_review_count(),
            ];
        }
        
        return rest_ensure_response($formatted_experiences);
    }
    
    /**
     * Render booking form block
     */
    public function render_booking_form_block($attributes, $content) {
        $product_id = absint($attributes['productId'] ?? 0);
        
        if (!$product_id) {
            return $this->render_block_placeholder(__('Please select an event to display the booking form.', 'wceventsfp'));
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product || get_post_meta($product_id, '_wcefp_is_event', true) !== 'yes') {
            return $this->render_block_placeholder(__('Selected event not found or is not a valid event product.', 'wceventsfp'));
        }
        
        // Start output buffering
        ob_start();
        
        $css_classes = ['wcefp-booking-form-block', 'wcefp-widget'];
        if (!empty($attributes['className'])) {
            $css_classes[] = sanitize_html_class($attributes['className']);
        }
        if (!empty($attributes['align'])) {
            $css_classes[] = 'align' . sanitize_html_class($attributes['align']);
        }
        
        ?>
        <div class="<?php echo esc_attr(esc_attr(implode(' ', $css_classes))); ?>">
            <?php if ($attributes['showTitle'] ?? true): ?>
                <h3 class="wcefp-block-title"><?php echo esc_html($product->get_name()); ?></h3>
            <?php endif; ?>
            
            <?php if ($attributes['showImages'] ?? false): ?>
                <?php $this->render_product_images($product); ?>
            <?php endif; ?>
            
            <?php if ($attributes['showDescription'] ?? true): ?>
                <div class="wcefp-block-description">
                    <?php echo wp_kses_post($product->get_short_description() ?: wp_trim_words($product->get_description(), 30)); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($attributes['showPrice'] ?? true): ?>
                <div class="wcefp-block-price">
                    <?php echo wp_kses_post($product->get_price_html()); ?>
                </div>
            <?php endif; ?>
            
            <div class="wcefp-block-form">
                <?php
                // Use existing shortcode functionality
                echo do_shortcode("[wcefp_booking_form product_id='{$product_id}']");
                ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render event list block
     */
    public function render_event_list_block($attributes, $content) {
        $number_of_events = absint($attributes['numberOfEvents'] ?? 5);
        
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $number_of_events,
            'meta_query' => [
                [
                    'key' => '_wcefp_is_event',
                    'value' => 'yes',
                    'compare' => '=',
                ],
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        $events = get_posts($args);
        
        if (empty($events)) {
            return $this->render_block_placeholder(__('No events found.', 'wceventsfp'));
        }
        
        // Start output buffering
        ob_start();
        
        $css_classes = ['wcefp-event-list-block', 'wcefp-widget'];
        if (!empty($attributes['className'])) {
            $css_classes[] = sanitize_html_class($attributes['className']);
        }
        
        ?>
        <div class="<?php echo esc_attr(esc_attr(implode(' ', $css_classes))); ?>">
            <?php foreach ($events as $event): ?>
                <?php 
                $product = wc_get_product($event->ID);
                if (!$product) continue;
                ?>
                <div class="wcefp-event-item">
                    <?php if ($attributes['showFeaturedImage'] ?? true): ?>
                        <?php if (has_post_thumbnail($event->ID)): ?>
                            <div class="wcefp-event-image">
                                <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                                    <?php echo get_the_post_thumbnail($event->ID, 'medium', ['alt' => esc_attr($event->post_title)]); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="wcefp-event-content">
                        <h3 class="wcefp-event-title">
                            <a href="<?php echo esc_url(get_permalink($event->ID)); ?>">
                                <?php echo esc_html($event->post_title); ?>
                            </a>
                        </h3>
                        
                        <?php if ($attributes['showExcerpt'] ?? true): ?>
                            <div class="wcefp-event-excerpt">
                                <?php echo wp_kses_post(wp_trim_words($event->post_excerpt ?: $event->post_content, 20)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="wcefp-event-meta">
                            <?php if ($attributes['showPrice'] ?? true): ?>
                                <div class="wcefp-event-price">
                                    <?php echo wp_kses_post($product->get_price_html()); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($attributes['showBookButton'] ?? true): ?>
                                <div class="wcefp-event-actions">
                                    <a href="<?php echo esc_url(get_permalink($event->ID)); ?>" class="wcefp-btn wcefp-btn-primary">
                                        <?php esc_html_e('Book Now', 'wceventsfp'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Helper methods
     */
    
    private function has_wcefp_blocks() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return has_block('wcefp/booking-form', $post) || has_block('wcefp/event-list', $post);
    }
    
    private function render_block_placeholder($message) {
        return sprintf(
            '<div class="wcefp-block-placeholder"><p>%s</p></div>',
            esc_html($message)
        );
    }
    
    private function render_product_images($product) {
        $image_ids = $product->get_gallery_image_ids();
        
        if ($product->get_image_id()) {
            array_unshift($image_ids, $product->get_image_id());
        }
        
        if (empty($image_ids)) {
            return;
        }
        
        echo '<div class="wcefp-block-images">';
        foreach (array_slice($image_ids, 0, 3) as $image_id) {
            echo wp_get_attachment_image($image_id, 'medium', false, [
                'class' => 'wcefp-block-image',
                'alt' => esc_attr($product->get_name()),
            ]);
        }
        echo '</div>';
    }
    
    private function get_product_gallery_images($product) {
        $image_ids = $product->get_gallery_image_ids();
        $images = [];
        
        foreach ($image_ids as $image_id) {
            $images[] = [
                'id' => $image_id,
                'url' => wp_get_attachment_image_url($image_id, 'large'),
                'thumbnail' => wp_get_attachment_image_url($image_id, 'thumbnail'),
                'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
            ];
        }
        
        return $images;
    }
    
    /**
     * Render experiences catalog block
     */
    public function render_experiences_catalog_block($attributes, $content) {
        // Convert block attributes to shortcode attributes
        $shortcode_atts = [
            'limit' => intval($attributes['limit'] ?? 12),
            'category' => sanitize_text_field($attributes['category'] ?? ''),
            'show_filters' => ($attributes['showFilters'] ?? true) ? 'yes' : 'no',
            'show_map' => ($attributes['showMap'] ?? false) ? 'yes' : 'no',
            'layout' => sanitize_text_field($attributes['layout'] ?? 'grid'),
            'columns' => intval($attributes['columns'] ?? 3),
            'order' => sanitize_text_field($attributes['orderBy'] ?? 'date'),
            'order_dir' => sanitize_text_field($attributes['orderDir'] ?? 'DESC'),
            'show_price' => ($attributes['showPrice'] ?? true) ? 'yes' : 'no',
            'show_rating' => ($attributes['showRating'] ?? true) ? 'yes' : 'no',
            'show_duration' => ($attributes['showDuration'] ?? true) ? 'yes' : 'no',
            'show_location' => ($attributes['showLocation'] ?? true) ? 'yes' : 'no',
            'class' => sanitize_html_class($attributes['className'] ?? ''),
        ];
        
        // Use the existing shortcode manager to render the catalog
        $shortcode_manager = new \WCEFP\Frontend\ShortcodeManager();
        return $shortcode_manager->experiences_catalog_shortcode($shortcode_atts);
    }
}