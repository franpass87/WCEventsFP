<?php
/**
 * Experience Archive Template
 * 
 * Template for displaying the /esperienze/ archive page
 * 
 * @package WCEFP
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Archive configuration
$archive_title = __('Esperienze', 'wceventsfp');
$archive_description = get_option('wcefp_experience_archive_description', 
    __('Scopri le nostre esperienze uniche e prenota la tua avventura ideale.', 'wceventsfp')
);

// Get current query
global $wp_query;
$experiences = $wp_query;
?>

<div class="wcefp-experiences-archive">
    <div class="container">
        <!-- Archive Header -->
        <header class="archive-header">
            <h1 class="archive-title"><?php echo esc_html($archive_title); ?></h1>
            
            <?php if ($archive_description): ?>
                <p class="archive-description"><?php echo esc_html($archive_description); ?></p>
            <?php endif; ?>
            
            <!-- Archive Stats -->
            <div class="archive-stats">
                <?php
                $total_experiences = $experiences->found_posts;
                printf(
                    _n(
                        '%d esperienza disponibile',
                        '%d esperienze disponibili',
                        $total_experiences,
                        'wceventsfp'
                    ),
                    $total_experiences
                );
                ?>
            </div>
        </header>

        <!-- Filters and Search -->
        <div class="wcefp-archive-filters">
            <div class="wcefp-archive-search">
                <label for="wcefp-search-input" class="sr-only">
                    <?php _e('Cerca esperienze', 'wceventsfp'); ?>
                </label>
                <input type="search" 
                       id="wcefp-search-input"
                       class="wcefp-search-input" 
                       placeholder="<?php esc_attr_e('Cerca esperienze...', 'wceventsfp'); ?>"
                       value="<?php echo esc_attr(get_query_var('search', '')); ?>">
            </div>
            
            <div class="wcefp-archive-filter-controls">
                <!-- Duration Filter -->
                <div class="wcefp-filter-group">
                    <label for="wcefp-duration-filter"><?php _e('Durata:', 'wceventsfp'); ?></label>
                    <select id="wcefp-duration-filter" class="wcefp-filter-select">
                        <option value=""><?php _e('Tutte le durate', 'wceventsfp'); ?></option>
                        <option value="1-3h"><?php _e('1-3 ore', 'wceventsfp'); ?></option>
                        <option value="3-6h"><?php _e('3-6 ore', 'wceventsfp'); ?></option>
                        <option value="6h+"><?php _e('Oltre 6 ore', 'wceventsfp'); ?></option>
                        <option value="full-day"><?php _e('Giornata intera', 'wceventsfp'); ?></option>
                        <option value="multi-day"><?php _e('Più giorni', 'wceventsfp'); ?></option>
                    </select>
                </div>

                <!-- Price Filter -->
                <div class="wcefp-filter-group">
                    <label for="wcefp-price-filter"><?php _e('Prezzo:', 'wceventsfp'); ?></label>
                    <select id="wcefp-price-filter" class="wcefp-filter-select">
                        <option value=""><?php _e('Tutti i prezzi', 'wceventsfp'); ?></option>
                        <option value="0-25"><?php _e('€ 0-25', 'wceventsfp'); ?></option>
                        <option value="25-50"><?php _e('€ 25-50', 'wceventsfp'); ?></option>
                        <option value="50-100"><?php _e('€ 50-100', 'wceventsfp'); ?></option>
                        <option value="100-200"><?php _e('€ 100-200', 'wceventsfp'); ?></option>
                        <option value="200+"><?php _e('€ 200+', 'wceventsfp'); ?></option>
                    </select>
                </div>

                <!-- Category Filter (if product categories are used) -->
                <?php
                $experience_categories = get_terms([
                    'taxonomy' => 'product_cat',
                    'hide_empty' => true,
                    'meta_query' => [
                        [
                            'key' => '_wcefp_category_for_experiences',
                            'value' => '1',
                            'compare' => '='
                        ]
                    ]
                ]);

                if (!empty($experience_categories) && !is_wp_error($experience_categories)):
                ?>
                    <div class="wcefp-filter-group">
                        <label for="wcefp-category-filter"><?php _e('Categoria:', 'wceventsfp'); ?></label>
                        <select id="wcefp-category-filter" class="wcefp-filter-select">
                            <option value=""><?php _e('Tutte le categorie', 'wceventsfp'); ?></option>
                            <?php foreach ($experience_categories as $category): ?>
                                <option value="<?php echo esc_attr($category->slug); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Sort Filter -->
                <div class="wcefp-filter-group">
                    <label for="wcefp-sort-filter"><?php _e('Ordina per:', 'wceventsfp'); ?></label>
                    <select id="wcefp-sort-filter" class="wcefp-filter-select">
                        <option value="menu_order"><?php _e('Posizione', 'wceventsfp'); ?></option>
                        <option value="title"><?php _e('Nome A-Z', 'wceventsfp'); ?></option>
                        <option value="title_desc"><?php _e('Nome Z-A', 'wceventsfp'); ?></option>
                        <option value="price"><?php _e('Prezzo crescente', 'wceventsfp'); ?></option>
                        <option value="price_desc"><?php _e('Prezzo decrescente', 'wceventsfp'); ?></option>
                        <option value="rating"><?php _e('Migliore valutazione', 'wceventsfp'); ?></option>
                        <option value="popularity"><?php _e('Più popolari', 'wceventsfp'); ?></option>
                        <option value="date"><?php _e('Più recenti', 'wceventsfp'); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Experiences Grid -->
        <div class="wcefp-experiences-grid wcefp-layout-grid wcefp-columns-3">
            <?php if ($experiences->have_posts()): ?>
                <?php while ($experiences->have_posts()): $experiences->the_post(); ?>
                    <?php
                    $product = wc_get_product(get_the_ID());
                    if (!$product) continue;
                    ?>
                    <div class="wcefp-experience-item">
                        <article class="wcefp-experience-card" 
                                 data-product-id="<?php echo esc_attr(get_the_ID()); ?>"
                                 tabindex="0"
                                 role="article"
                                 aria-labelledby="experience-title-<?php echo esc_attr(get_the_ID()); ?>">
                            
                            <!-- Card Image -->
                            <div class="wcefp-card-image">
                                <a href="<?php echo esc_url(get_permalink()); ?>" 
                                   aria-label="<?php printf(__('Vai a %s', 'wceventsfp'), get_the_title()); ?>">
                                    <?php
                                    if (has_post_thumbnail()) {
                                        the_post_thumbnail('woocommerce_thumbnail', [
                                            'alt' => get_the_title(),
                                            'loading' => 'lazy'
                                        ]);
                                    } else {
                                        echo '<div class="wcefp-no-image">' . __('Nessuna immagine', 'wceventsfp') . '</div>';
                                    }
                                    ?>
                                </a>
                                
                                <?php
                                // Show badges (bestseller, new, etc.)
                                $badges = [];
                                
                                // Bestseller badge
                                if (get_post_meta(get_the_ID(), '_wcefp_is_bestseller', true) === 'yes') {
                                    $badges[] = '<span class="wcefp-badge wcefp-badge-bestseller">' . __('Bestseller', 'wceventsfp') . '</span>';
                                }
                                
                                // New experience badge
                                $post_date = get_the_date('U');
                                $days_old = (current_time('timestamp') - $post_date) / DAY_IN_SECONDS;
                                if ($days_old <= 30) {
                                    $badges[] = '<span class="wcefp-badge wcefp-badge-new">' . __('Nuovo', 'wceventsfp') . '</span>';
                                }
                                
                                // Sale badge
                                if ($product->is_on_sale()) {
                                    $badges[] = '<span class="wcefp-badge wcefp-badge-sale">' . __('Offerta', 'wceventsfp') . '</span>';
                                }
                                
                                if (!empty($badges)):
                                ?>
                                    <div class="wcefp-card-badges">
                                        <?php echo implode('', $badges); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Card Content -->
                            <div class="wcefp-card-content">
                                <h3 class="wcefp-card-title" id="experience-title-<?php echo esc_attr(get_the_ID()); ?>">
                                    <a href="<?php echo esc_url(get_permalink()); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h3>

                                <?php if (get_the_excerpt()): ?>
                                    <div class="wcefp-card-excerpt">
                                        <?php the_excerpt(); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Card Meta -->
                                <div class="wcefp-card-meta">
                                    <div class="wcefp-card-price">
                                        <?php echo $product->get_price_html(); ?>
                                    </div>

                                    <?php
                                    // Duration
                                    $duration = get_post_meta(get_the_ID(), '_wcefp_duration', true);
                                    if ($duration):
                                    ?>
                                        <div class="wcefp-card-duration">
                                            <span class="wcefp-duration-icon" aria-hidden="true">🕐</span>
                                            <span><?php echo esc_html($duration); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php
                                    // Rating
                                    if ($product->get_average_rating()):
                                    ?>
                                        <div class="wcefp-card-rating">
                                            <?php echo wc_get_rating_html($product->get_average_rating()); ?>
                                            <span class="wcefp-rating-count">
                                                (<?php echo $product->get_review_count(); ?>)
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <?php
                                    // Difficulty level
                                    $difficulty = get_post_meta(get_the_ID(), '_wcefp_difficulty_level', true);
                                    if ($difficulty):
                                        $difficulty_levels = [
                                            'easy' => __('Facile', 'wceventsfp'),
                                            'moderate' => __('Moderato', 'wceventsfp'),
                                            'hard' => __('Difficile', 'wceventsfp'),
                                            'expert' => __('Esperto', 'wceventsfp')
                                        ];
                                        $difficulty_label = $difficulty_levels[$difficulty] ?? $difficulty;
                                    ?>
                                        <div class="wcefp-card-difficulty">
                                            <span class="wcefp-difficulty-icon" aria-hidden="true">⭐</span>
                                            <span><?php echo esc_html($difficulty_label); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Card Actions -->
                                <div class="wcefp-card-actions">
                                    <a href="<?php echo esc_url(get_permalink()); ?>" 
                                       class="wcefp-view-experience-btn">
                                        <?php _e('Scopri di più', 'wceventsfp'); ?>
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endwhile; ?>
                
            <?php else: ?>
                <div class="wcefp-no-experiences">
                    <p><?php _e('Nessuna esperienza trovata con i filtri selezionati.', 'wceventsfp'); ?></p>
                    <p><?php _e('Prova a modificare i criteri di ricerca o', 'wceventsfp'); ?> 
                       <a href="<?php echo esc_url(remove_query_arg(['search', 'duration', 'price', 'sort'])); ?>">
                           <?php _e('visualizza tutte le esperienze', 'wceventsfp'); ?>
                       </a>.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($experiences->max_num_pages > 1): ?>
            <div class="wcefp-archive-pagination">
                <?php
                $pagination_args = [
                    'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                    'format' => '?paged=%#%',
                    'current' => max(1, get_query_var('paged')),
                    'total' => $experiences->max_num_pages,
                    'prev_text' => __('« Precedente', 'wceventsfp'),
                    'next_text' => __('Successivo »', 'wceventsfp'),
                    'type' => 'array',
                    'end_size' => 1,
                    'mid_size' => 2,
                ];

                $pagination_links = paginate_links($pagination_args);

                if ($pagination_links):
                ?>
                    <nav class="wcefp-pagination-nav" role="navigation" 
                         aria-label="<?php esc_attr_e('Navigazione esperienze', 'wceventsfp'); ?>">
                        <ul class="wcefp-pagination-list">
                            <?php foreach ($pagination_links as $link): ?>
                                <li class="wcefp-pagination-item">
                                    <?php echo $link; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Archive Footer -->
        <footer class="archive-footer">
            <div class="archive-cta">
                <h2><?php _e('Non trovi quello che cerchi?', 'wceventsfp'); ?></h2>
                <p><?php _e('Contattaci per esperienze personalizzate o per maggiori informazioni.', 'wceventsfp'); ?></p>
                
                <?php
                $contact_page_id = get_option('wcefp_contact_page_id');
                if ($contact_page_id):
                ?>
                    <a href="<?php echo esc_url(get_permalink($contact_page_id)); ?>" 
                       class="wcefp-contact-btn">
                        <?php _e('Contattaci', 'wceventsfp'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </footer>
    </div>
</div>

<!-- Schema.org structured data -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "CollectionPage",
    "name": "<?php echo esc_js($archive_title); ?>",
    "description": "<?php echo esc_js($archive_description); ?>",
    "url": "<?php echo esc_js(home_url('/esperienze/')); ?>",
    "mainEntity": {
        "@type": "ItemList",
        "numberOfItems": <?php echo (int)$experiences->found_posts; ?>,
        "itemListElement": [
            <?php
            $items = [];
            if ($experiences->have_posts()):
                $position = 1;
                while ($experiences->have_posts()): $experiences->the_post();
                    $product = wc_get_product(get_the_ID());
                    if (!$product) continue;
                    
                    $items[] = '{
                        "@type": "ListItem",
                        "position": ' . $position . ',
                        "item": {
                            "@type": "Event",
                            "name": "' . esc_js(get_the_title()) . '",
                            "url": "' . esc_js(get_permalink()) . '",
                            "offers": {
                                "@type": "Offer",
                                "price": "' . esc_js($product->get_price()) . '",
                                "priceCurrency": "EUR"
                            }
                        }
                    }';
                    $position++;
                endwhile;
            endif;
            echo implode(',', $items);
            ?>
        ]
    }
}
</script>

<?php
wp_reset_postdata();
get_footer();
?>