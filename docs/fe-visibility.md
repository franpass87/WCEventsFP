# WCEventsFP Frontend Visibility Control Guide

## Overview

WCEventsFP provides comprehensive control over where experiences appear in your WooCommerce store, allowing you to create dedicated experience sections while hiding them from standard shop pages.

## Archive Filtering System

### What Gets Hidden

By default, WCEventsFP hides experiences from:

1. **Shop page** (`/shop/`)
2. **Product category archives** (`/product-category/*/`)
3. **Product tag archives** (`/product-tag/*/`)
4. **WooCommerce search results**
5. **WordPress search results**
6. **Related products suggestions**
7. **Cross-sell and up-sell recommendations**
8. **Product widgets** (Recent Products, Featured Products, etc.)

### What Remains Visible

Experiences remain accessible through:

1. **Direct URLs** - Single product pages are still accessible
2. **Experience Archive** - Special `/esperienze/` archive page
3. **Custom landing pages** - Pages with experience shortcodes
4. **Admin area** - Full visibility for management
5. **WooCommerce admin reports** - Included in sales data

## Configuration

### Admin Settings

Navigate to **WooCommerce > Settings > WCEventsFP > Archive & Search Filtering**:

#### Hide from Shop Archives
- **Enabled** - Experiences hidden from shop page and category/tag archives
- **Disabled** - Experiences appear in normal WooCommerce archives
- **Default**: Enabled

#### Hide from Search Results  
- **Enabled** - Experiences hidden from WordPress and WooCommerce search
- **Disabled** - Experiences appear in search results
- **Default**: Enabled

#### Redirect Single Products
- **Enabled** - Single experience pages redirect to custom landing pages
- **Disabled** - Single experience pages work normally
- **Default**: Disabled (not recommended)

### Programmatic Configuration

```php
// Enable/disable archive filtering
update_option('wcefp_hide_from_shop_archives', 'yes'); // or 'no'
update_option('wcefp_hide_from_search', 'yes'); // or 'no'  
update_option('wcefp_redirect_single_products', 'no'); // or 'yes'

// Get current settings
$hide_from_shop = get_option('wcefp_hide_from_shop_archives', 'yes');
$hide_from_search = get_option('wcefp_hide_from_search', 'yes');
$redirect_singles = get_option('wcefp_redirect_single_products', 'no');
```

## How Visibility Filtering Works

### Product Identification

Experiences are identified by:
```php
// Meta key method (recommended)
get_post_meta($product_id, '_wcefp_is_experience', true) === '1'

// Product type method (alternative)
$product->get_type() === 'esperienza'
```

### Query Modification

The system modifies WordPress queries using:

```php
// Meta query exclusion
$meta_query[] = [
    'key' => '_wcefp_is_experience',
    'value' => '1',
    'compare' => '!='
];
```

### Hook Points

Key hooks used for filtering:

- `pre_get_posts` - Main query modification
- `woocommerce_product_query` - WooCommerce-specific queries
- `woocommerce_output_related_products_args` - Related products
- `woocommerce_cart_crosssell_ids` - Cross-sells
- `woocommerce_products_widget_query_args` - Product widgets

## Advanced Filtering

### Custom Query Modification

```php
// Add custom experience filtering to queries
add_action('pre_get_posts', function($query) {
    // Only modify main queries on frontend
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    
    // Hide experiences from category archives
    if ($query->is_tax('product_cat')) {
        $meta_query = $query->get('meta_query', []);
        $meta_query['relation'] = 'AND';
        $meta_query[] = [
            'key' => '_wcefp_is_experience',
            'value' => '1',
            'compare' => '!='
        ];
        $query->set('meta_query', $meta_query);
    }
});
```

### Override Filtering for Specific Pages

```php
// Allow experiences on specific category pages
add_action('pre_get_posts', function($query) {
    if ($query->is_tax('product_cat', 'experiences-category')) {
        // Remove the experience filter for this category
        $meta_query = $query->get('meta_query', []);
        
        // Filter out the experience exclusion query
        $meta_query = array_filter($meta_query, function($clause) {
            return !isset($clause['key']) || $clause['key'] !== '_wcefp_is_experience';
        });
        
        $query->set('meta_query', $meta_query);
    }
}, 25); // Higher priority to run after the filter
```

### Selective Visibility

```php
// Show only specific experiences in shop
add_action('pre_get_posts', function($query) {
    if ($query->is_shop() && $query->is_main_query()) {
        // Allow featured experiences in shop
        $meta_query = $query->get('meta_query', []);
        
        // Modify the experience filter to allow featured ones
        foreach ($meta_query as &$clause) {
            if (isset($clause['key']) && $clause['key'] === '_wcefp_is_experience') {
                $clause = [
                    'relation' => 'OR',
                    [
                        'key' => '_wcefp_is_experience',
                        'value' => '1',
                        'compare' => '!='
                    ],
                    [
                        'relation' => 'AND',
                        [
                            'key' => '_wcefp_is_experience',
                            'value' => '1',
                            'compare' => '='
                        ],
                        [
                            'key' => '_featured',
                            'value' => 'yes',
                            'compare' => '='
                        ]
                    ]
                ];
                break;
            }
        }
        
        $query->set('meta_query', $meta_query);
    }
}, 25);
```

## Search Integration

### WordPress Search

Experiences are filtered from WordPress search results but can be included in custom searches:

```php
// Custom search that includes experiences
$search_args = [
    'post_type' => 'product',
    's' => $search_term,
    'meta_query' => [
        [
            'key' => '_wcefp_is_experience',
            'value' => '1',
            'compare' => '='
        ]
    ]
];

$experience_search = new WP_Query($search_args);
```

### WooCommerce Search

Override WooCommerce search to include experiences:

```php
add_action('pre_get_posts', function($query) {
    if ($query->is_search() && isset($_GET['post_type']) && $_GET['post_type'] === 'product') {
        // Include experiences in WooCommerce product search
        $query->set('meta_query', []);
    }
});
```

### AJAX Search Integration

```php
// Add AJAX endpoint for experience search
add_action('wp_ajax_search_experiences', 'handle_experience_search');
add_action('wp_ajax_nopriv_search_experiences', 'handle_experience_search');

function handle_experience_search() {
    check_ajax_referer('wcefp_search_nonce');
    
    $search_term = sanitize_text_field($_POST['search']);
    
    $experiences = get_posts([
        'post_type' => 'product',
        'posts_per_page' => 10,
        's' => $search_term,
        'meta_query' => [
            [
                'key' => '_wcefp_is_experience',
                'value' => '1',
                'compare' => '='
            ]
        ]
    ]);
    
    wp_send_json_success([
        'results' => array_map(function($experience) {
            return [
                'id' => $experience->ID,
                'title' => get_the_title($experience->ID),
                'url' => get_permalink($experience->ID),
                'image' => get_the_post_thumbnail_url($experience->ID, 'thumbnail')
            ];
        }, $experiences)
    ]);
}
```

## Theme Integration

### Template Hierarchy

WCEventsFP respects WordPress template hierarchy. For experiences:

1. `single-product-esperienza.php` (if product type is 'esperienza')
2. `single-product.php` (standard WooCommerce template)
3. `single.php` (WordPress fallback)

### Custom Templates

```php
// In your theme's functions.php
add_filter('template_include', function($template) {
    if (is_product()) {
        global $post;
        if (get_post_meta($post->ID, '_wcefp_is_experience', true) === '1') {
            $experience_template = locate_template(['single-experience.php']);
            if ($experience_template) {
                return $experience_template;
            }
        }
    }
    return $template;
});
```

### Conditional Content

```php
// In template files
if (get_post_meta(get_the_ID(), '_wcefp_is_experience', true) === '1') {
    // Experience-specific content
    echo do_shortcode('[wcefp_booking_widget_v2 id="' . get_the_ID() . '"]');
} else {
    // Regular product content
    woocommerce_template_single_add_to_cart();
}
```

## SEO Considerations

### Structured Data

Experiences automatically include appropriate Schema.org markup:

```json
{
  "@type": "Event",
  "name": "Experience Name",
  "offers": {
    "@type": "Offer",
    "price": "50.00"
  }
}
```

### XML Sitemaps

Ensure experiences are included in your XML sitemap:

```php
// Add experiences to Yoast SEO sitemap
add_filter('wpseo_sitemap_exclude_post_type', function($excluded, $post_type) {
    if ($post_type === 'product') {
        return false; // Include all products (including experiences)
    }
    return $excluded;
}, 10, 2);
```

### Meta Descriptions

```php
// Custom meta descriptions for experiences
add_filter('wpseo_metadesc', function($description) {
    if (is_product() && get_post_meta(get_the_ID(), '_wcefp_is_experience', true) === '1') {
        $custom_description = get_post_meta(get_the_ID(), '_wcefp_meta_description', true);
        if ($custom_description) {
            return $custom_description;
        }
    }
    return $description;
});
```

## Performance Impact

### Query Optimization

The filtering system adds minimal overhead:

- **Meta queries** are optimized with proper indexing
- **Caching** reduces repeated database queries
- **Conditional execution** only runs when needed

### Database Indexes

Ensure optimal performance with proper indexes:

```sql
-- Recommended database indexes
ALTER TABLE wp_postmeta ADD INDEX wcefp_experience_index (meta_key, meta_value);
```

### Caching Compatibility

The system works with popular caching plugins:

- **WP Rocket** - Automatically detects dynamic content
- **W3 Total Cache** - Compatible with page caching
- **LiteSpeed Cache** - Works with object caching

Exclude these pages from caching:
- `/esperienze/` (if dynamic filtering is used)
- Product pages with booking widgets
- AJAX endpoints

## Troubleshooting

### Common Issues

#### Experiences Still Appearing in Shop
- Check if archive filtering is enabled in settings
- Clear any object caching
- Verify the `_wcefp_is_experience` meta key is set to '1'

#### Search Results Empty
- Check if search filtering is too restrictive
- Verify WooCommerce search settings
- Test with default theme to rule out theme conflicts

#### Performance Issues
- Enable object caching (Redis/Memcached)
- Optimize database with proper indexes
- Consider limiting simultaneous filter queries

### Debug Mode

```php
// Enable visibility filtering debug mode
define('WCEFP_DEBUG_FILTERING', true);

// This will log all query modifications
add_action('wp', function() {
    if (defined('WCEFP_DEBUG_FILTERING') && WCEFP_DEBUG_FILTERING) {
        add_action('pre_get_posts', function($query) {
            error_log('WCEventsFP Query Debug: ' . print_r([
                'query_vars' => $query->query_vars,
                'meta_query' => $query->get('meta_query'),
                'is_shop' => $query->is_shop(),
                'is_search' => $query->is_search()
            ], true));
        }, 999);
    }
});
```

### Testing Checklist

- [ ] Shop page doesn't show experiences
- [ ] Category archives exclude experiences  
- [ ] Site search excludes experiences
- [ ] WooCommerce search excludes experiences
- [ ] Related products don't suggest experiences
- [ ] Single experience pages still accessible
- [ ] `/esperienze/` archive works correctly
- [ ] Breadcrumbs show correct navigation
- [ ] Admin area shows all products
- [ ] Reports include experience data

## Migration Notes

### From v1 to v2

If migrating from an older version:

1. **Backup database** before enabling filtering
2. **Test on staging** to verify expected behavior
3. **Update custom code** that relied on experiences appearing in shop
4. **Redirect old URLs** if experience locations changed

### Theme Compatibility

Popular themes tested and confirmed compatible:

- **Storefront** - Full compatibility
- **Astra** - Works with Pro features  
- **OceanWP** - Compatible with WooCommerce extension
- **GeneratePress** - Works with GP Premium

### Plugin Compatibility

Known compatibility with:

- **WooCommerce Subscriptions** - Experiences can be subscription products
- **WooCommerce Bookings** - Can coexist with booking system
- **WPML** - Multilingual experience support
- **Polylang** - Alternative multilingual support