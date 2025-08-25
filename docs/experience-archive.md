# WCEventsFP Experience Archive Implementation Guide

## Overview

The Experience Archive (`/esperienze/`) provides a dedicated section for browsing experiences, separate from the main WooCommerce shop. This creates a focused user experience while maintaining clean separation of product types.

## Architecture

### URL Structure

- **Archive URL**: `https://yoursite.com/esperienze/`
- **Paginated URLs**: `https://yoursite.com/esperienze/page/2/`
- **Filtered URLs**: `https://yoursite.com/esperienze/?search=wine&duration=1-3h`

### Rewrite Rules

The system adds custom rewrite rules:

```php
// Main archive
^esperienze/?$ → index.php?wcefp_experience_archive=1

// Pagination  
^esperienze/page/([0-9]+)/?$ → index.php?wcefp_experience_archive=1&paged=$matches[1]
```

### Query Handling

Custom query variables:
- `wcefp_experience_archive` - Identifies archive requests
- `paged` - Handles pagination
- Standard filters passed via URL parameters

## Installation & Setup

### Automatic Setup

The archive is automatically activated when the plugin loads:

```php
// Triggered on plugin activation
ExperienceArchiveManager::activate();

// Rewrite rules are flushed
flush_rewrite_rules();
```

### Manual Setup

If rewrite rules aren't working:

```php
// In wp-admin or via code
flush_rewrite_rules();

// Or trigger flush on next load
update_option('wcefp_experience_archive_flush_needed', true);
```

### Verify Installation

Check if the archive is working:

1. Visit `/esperienze/` on your site
2. Should display experience archive or 404 if no experiences exist
3. Check permalink structure in WP Admin > Settings > Permalinks

## Template System

### Template Hierarchy

WCEventsFP looks for templates in this order:

1. **Theme Override**: `theme/wcefp/archive-experiences.php`
2. **Theme Alternative**: `theme/wcefp/experiences-archive.php`  
3. **Theme Root**: `theme/archive-experiences.php`
4. **Plugin Template**: `plugins/wceventsfp/templates/archive-experiences.php`
5. **Fallback**: Generated template in `/tmp/`

### Creating Custom Templates

#### Method 1: Theme Directory

Create `your-theme/wcefp/archive-experiences.php`:

```php
<?php
get_header();

// Get current query
global $wp_query;
?>

<div class="custom-experience-archive">
    <h1>Our Experiences</h1>
    
    <div class="experiences-grid">
        <?php if ($wp_query->have_posts()): ?>
            <?php while ($wp_query->have_posts()): $wp_query->the_post(); ?>
                <div class="experience-item">
                    <?php echo do_shortcode('[wcefp_experience_card id="' . get_the_ID() . '"]'); ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No experiences found.</p>
        <?php endif; ?>
    </div>
    
    <?php
    // Pagination
    the_posts_pagination([
        'prev_text' => '← Previous',
        'next_text' => 'Next →'
    ]);
    ?>
</div>

<?php get_footer(); ?>
```

#### Method 2: Filter Hook

```php
// In your theme's functions.php
add_filter('wcefp_experience_archive_template', function($template_path) {
    $custom_template = get_stylesheet_directory() . '/templates/experiences.php';
    if (file_exists($custom_template)) {
        return $custom_template;
    }
    return $template_path;
});
```

## Shortcode Integration

### Archive Shortcode

Display the archive anywhere using:

```php
[wcefp_experiences_archive per_page="12" columns="3" show_filters="yes"]
```

### Page Integration

Create a custom page with the shortcode:

1. Create new page in WP Admin
2. Add the shortcode to page content
3. Optionally set as static front page or custom landing

### Widget Integration

```php
// Add to sidebar or widget area
echo do_shortcode('[wcefp_experiences_archive per_page="6" columns="2" show_search="no"]');
```

## Filtering & Search

### Available Filters

#### Duration Filter
- `1-3h` - 1-3 hours
- `3-6h` - 3-6 hours  
- `6h+` - Over 6 hours
- `full-day` - Full day
- `multi-day` - Multiple days

#### Price Filter  
- `0-25` - €0-25
- `25-50` - €25-50
- `50-100` - €50-100
- `100-200` - €100-200
- `200+` - €200+

#### Category Filter
Based on WooCommerce product categories marked for experiences.

#### Sort Options
- `menu_order` - Custom order (default)
- `title` / `title_desc` - Name A-Z / Z-A
- `price` / `price_desc` - Price low to high / high to low
- `rating` - Best rated first
- `popularity` - Most popular first
- `date` - Newest first

### Custom Filters

Add custom filters programmatically:

```php
// Add custom duration options
add_filter('wcefp_experience_duration_options', function($options) {
    $options['custom-duration'] = __('Custom Duration', 'wceventsfp');
    return $options;
});

// Handle custom filter logic
add_action('wcefp_filter_experiences_query', function($query, $filters) {
    if (!empty($filters['custom_filter'])) {
        $meta_query = $query->get('meta_query', []);
        $meta_query[] = [
            'key' => '_custom_meta_key',
            'value' => sanitize_text_field($filters['custom_filter']),
            'compare' => '='
        ];
        $query->set('meta_query', $meta_query);
    }
}, 10, 2);
```

## AJAX Implementation

### Frontend JavaScript

The archive includes AJAX filtering:

```javascript
// Trigger custom filter
$('.custom-filter').on('change', function() {
    wcefpExperienceArchive.setFilters({
        custom_filter: $(this).val()
    });
});

// Listen for results
$(document).on('wcefp_archive_filtered', function(event, data) {
    console.log('Filtered results:', data);
});
```

### Server-Side Handler

```php
// Add AJAX endpoint
add_action('wp_ajax_wcefp_filter_experiences', 'handle_experience_filter');
add_action('wp_ajax_nopriv_wcefp_filter_experiences', 'handle_experience_filter');

function handle_experience_filter() {
    check_ajax_referer('wcefp_archive_nonce', 'nonce');
    
    // Get filters
    $search = sanitize_text_field($_POST['search'] ?? '');
    $duration = sanitize_text_field($_POST['duration'] ?? '');
    $price = sanitize_text_field($_POST['price'] ?? '');
    $sort = sanitize_text_field($_POST['sort'] ?? 'menu_order');
    
    // Build query
    $query_args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 12,
        'meta_query' => [
            [
                'key' => '_wcefp_is_experience',
                'value' => '1',
                'compare' => '='
            ]
        ]
    ];
    
    // Add search
    if ($search) {
        $query_args['s'] = $search;
    }
    
    // Add sorting
    switch ($sort) {
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
        case 'title':
            $query_args['orderby'] = 'title';
            $query_args['order'] = 'ASC';
            break;
        // Add more sorting options
    }
    
    // Execute query
    $experiences = new WP_Query($query_args);
    
    // Render results
    ob_start();
    if ($experiences->have_posts()) {
        while ($experiences->have_posts()) {
            $experiences->the_post();
            echo do_shortcode('[wcefp_experience_card id="' . get_the_ID() . '"]');
        }
    } else {
        echo '<p>No experiences found.</p>';
    }
    $html = ob_get_clean();
    
    wp_send_json_success([
        'html' => $html,
        'count' => $experiences->found_posts,
        'pagination' => generate_pagination_html($experiences)
    ]);
}
```

## Breadcrumb Navigation

### Automatic Breadcrumbs

WCEventsFP automatically modifies WooCommerce breadcrumbs:

**Before**: Home > Shop > Experience Name  
**After**: Home > Esperienze > Experience Name

### Custom Breadcrumbs

```php
// Override breadcrumb modification
add_filter('wcefp_experience_breadcrumbs', function($crumbs, $product_id) {
    // Custom breadcrumb logic
    $custom_crumbs = [
        [__('Home', 'wceventsfp'), home_url()],
        [__('Our Experiences', 'wceventsfp'), home_url('/esperienze/')],
        [get_the_title($product_id), '']  // Current page, no link
    ];
    
    return $custom_crumbs;
}, 10, 2);
```

### Theme Integration

For themes that don't use WooCommerce breadcrumbs:

```php
// Add breadcrumbs to single experience pages
add_action('wcefp_single_experience_breadcrumbs', function() {
    if (is_product() && get_post_meta(get_the_ID(), '_wcefp_is_experience', true) === '1') {
        ?>
        <nav class="experience-breadcrumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'wceventsfp'); ?>">
            <ol>
                <li><a href="<?php echo esc_url(home_url()); ?>"><?php _e('Home', 'wceventsfp'); ?></a></li>
                <li><a href="<?php echo esc_url(home_url('/esperienze/')); ?>"><?php _e('Esperienze', 'wceventsfp'); ?></a></li>
                <li aria-current="page"><?php the_title(); ?></li>
            </ol>
        </nav>
        <?php
    }
});
```

## SEO Optimization

### Archive Meta Data

```php
// Set archive page meta
add_action('wp_head', function() {
    if (get_query_var('wcefp_experience_archive')) {
        $title = __('Esperienze - Discover Unique Experiences', 'wceventsfp');
        $description = __('Browse our collection of unique experiences and book your next adventure.', 'wceventsfp');
        
        echo '<title>' . esc_html($title) . '</title>' . "\n";
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(home_url('/esperienze/')) . '">' . "\n";
    }
});
```

### Structured Data

The archive automatically includes Schema.org markup:

```json
{
  "@context": "https://schema.org",
  "@type": "CollectionPage",
  "name": "Esperienze",
  "url": "https://yoursite.com/esperienze/",
  "mainEntity": {
    "@type": "ItemList",
    "itemListElement": [
      {
        "@type": "Event",
        "name": "Experience Name",
        "offers": {
          "@type": "Offer",
          "price": "50.00"
        }
      }
    ]
  }
}
```

### XML Sitemap

Ensure the archive is included in your sitemap:

```php
// Add to Yoast SEO sitemap
add_filter('wpseo_sitemap_exclude_taxonomy', function($excluded, $taxonomy) {
    if ($taxonomy === 'wcefp_experience_archive') {
        return false;
    }
    return $excluded;
}, 10, 2);

// Or add manually to sitemap
add_action('init', function() {
    if (function_exists('wp_sitemaps_get_server')) {
        wp_sitemaps_get_server()->registry->add_provider('experiences', new WP_Sitemaps_Posts());
    }
});
```

## Performance Optimization

### Caching Strategy

```php
// Cache archive queries
$cache_key = 'wcefp_experience_archive_' . md5(serialize($query_args));
$cached_results = wp_cache_get($cache_key, 'wcefp_experiences');

if ($cached_results === false) {
    $experiences = new WP_Query($query_args);
    wp_cache_set($cache_key, $experiences, 'wcefp_experiences', 15 * MINUTE_IN_SECONDS);
} else {
    $experiences = $cached_results;
}
```

### Database Optimization

Optimize database queries with proper indexes:

```sql
-- Improve experience filtering performance
ALTER TABLE wp_postmeta ADD INDEX wcefp_experience_idx (meta_key, meta_value, post_id);
ALTER TABLE wp_posts ADD INDEX wcefp_product_idx (post_type, post_status, menu_order);
```

### Lazy Loading

```php
// Implement lazy loading for experience cards
add_filter('wcefp_experience_card_image_attributes', function($attributes) {
    $attributes['loading'] = 'lazy';
    $attributes['decoding'] = 'async';
    return $attributes;
});
```

## Analytics & Tracking

### Archive Usage Tracking

```php
// Track archive visits
add_action('template_redirect', function() {
    if (get_query_var('wcefp_experience_archive')) {
        // Google Analytics event
        ?>
        <script>
            gtag('event', 'page_view', {
                'page_title': 'Experience Archive',
                'page_location': window.location.href,
                'custom_map': {'custom_parameter_1': 'experience_archive'}
            });
        </script>
        <?php
        
        // Internal tracking
        $current_count = get_option('wcefp_archive_views', 0);
        update_option('wcefp_archive_views', $current_count + 1);
    }
});
```

### Filter Usage Tracking

```javascript
// Track filter usage
$(document).on('wcefp_archive_filtered', function(event, data) {
    // Google Analytics
    gtag('event', 'filter_used', {
        'event_category': 'Experience Archive',
        'event_label': Object.keys(data.filters).join(','),
        'custom_map': {'custom_parameter_2': 'archive_filter'}
    });
    
    // Send to custom tracking
    $.post(wcefp_archive.ajax_url, {
        action: 'wcefp_track_filter_usage',
        filters: data.filters,
        results: data.count,
        nonce: wcefp_archive.nonce
    });
});
```

## Troubleshooting

### Archive Not Found (404)

1. **Check rewrite rules**: Go to Settings > Permalinks and click "Save"
2. **Verify activation**: Ensure `ExperienceArchiveManager::activate()` was called
3. **Check .htaccess**: Ensure WordPress can write to `.htaccess`
4. **Flush manually**: Call `flush_rewrite_rules()` in code

### No Experiences Showing

1. **Verify meta key**: Check if `_wcefp_is_experience = '1'` is set
2. **Check query**: Use WP Query Monitor to debug database queries
3. **Product status**: Ensure experiences are published
4. **Permissions**: Verify user can view product posts

### Filtering Not Working

1. **JavaScript errors**: Check browser console for errors
2. **AJAX endpoint**: Verify the AJAX handler is registered
3. **Nonce verification**: Check AJAX security nonces
4. **Server errors**: Check PHP error logs

### Template Issues

1. **Template location**: Verify template file exists and is readable
2. **PHP errors**: Check for syntax errors in custom templates
3. **Theme conflicts**: Test with default WordPress theme
4. **Plugin conflicts**: Deactivate other plugins to test

### Performance Issues

1. **Database queries**: Use Query Monitor to identify slow queries
2. **Caching**: Implement appropriate caching strategies
3. **Image optimization**: Ensure images are properly sized
4. **AJAX rate limiting**: Implement rate limiting for frequent requests

## Migration & Updates

### Version Updates

The archive system handles updates gracefully:

```php
// Version check and migration
$current_version = get_option('wcefp_archive_version', '1.0.0');
if (version_compare($current_version, '2.2.0', '<')) {
    // Run migration
    ExperienceArchiveManager::migrate_from_v1();
    update_option('wcefp_archive_version', '2.2.0');
}
```

### Data Migration

When migrating from other systems:

```php
// Migrate existing experience data
function migrate_experience_data() {
    $products = get_posts([
        'post_type' => 'product',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => '_old_experience_flag',
                'value' => 'yes',
                'compare' => '='
            ]
        ]
    ]);
    
    foreach ($products as $product) {
        // Set new experience flag
        update_post_meta($product->ID, '_wcefp_is_experience', '1');
        
        // Remove old flag
        delete_post_meta($product->ID, '_old_experience_flag');
    }
}
```

### Backup Recommendations

Before major changes:

1. **Database backup** - Full database export
2. **File backup** - Template and custom code files
3. **Settings export** - WCEventsFP settings export
4. **Test on staging** - Verify changes on staging site

## Extension Points

### Custom Archive Types

Create additional archives for different experience types:

```php
// Register wine experience archive
class WineExperienceArchiveManager extends ExperienceArchiveManager {
    const ARCHIVE_SLUG = 'wine-experiences';
    
    protected function get_experiences_query($atts) {
        $query = parent::get_experiences_query($atts);
        
        // Add wine-specific filtering
        $meta_query = $query->get('meta_query', []);
        $meta_query[] = [
            'key' => '_experience_category',
            'value' => 'wine',
            'compare' => '='
        ];
        $query->set('meta_query', $meta_query);
        
        return $query;
    }
}

new WineExperienceArchiveManager();
```

### Custom Filters

Add business-specific filters:

```php
// Add location filter
add_action('wcefp_archive_filter_controls', function() {
    $locations = get_terms([
        'taxonomy' => 'experience_location',
        'hide_empty' => true
    ]);
    
    if ($locations): ?>
        <div class="wcefp-filter-group">
            <label for="wcefp-location-filter"><?php _e('Location:', 'wceventsfp'); ?></label>
            <select id="wcefp-location-filter" class="wcefp-filter-select">
                <option value=""><?php _e('All Locations', 'wceventsfp'); ?></option>
                <?php foreach ($locations as $location): ?>
                    <option value="<?php echo esc_attr($location->slug); ?>">
                        <?php echo esc_html($location->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif;
});
```