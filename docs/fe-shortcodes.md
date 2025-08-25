# WCEventsFP Frontend Shortcodes Guide

## Overview

WCEventsFP provides a comprehensive set of shortcodes for displaying experiences, booking widgets, and related functionality on your WordPress frontend.

## Experience Archive & Display

### `[wcefp_experiences_archive]`

Creates a complete archive page for experiences with filtering, search, and pagination.

**Parameters:**
- `per_page` (int) - Number of experiences per page (default: 12)
- `columns` (int) - Grid columns (2, 3, 4, default: 3)
- `show_filters` (yes/no) - Show filter controls (default: yes)
- `show_search` (yes/no) - Show search input (default: yes)
- `layout` (string) - Layout style: `grid`, `list`, `masonry` (default: grid)
- `orderby` (string) - Sort field: `menu_order`, `title`, `price`, `rating`, `date` (default: menu_order)
- `order` (string) - Sort direction: `ASC`, `DESC` (default: ASC)

**Examples:**
```php
// Basic archive
[wcefp_experiences_archive]

// Custom layout with 4 columns, no filters
[wcefp_experiences_archive columns="4" show_filters="no" layout="grid"]

// List layout sorted by price
[wcefp_experiences_archive layout="list" orderby="price" order="ASC"]
```

### `[wcefp_experience_card]`

Displays a single experience as a card (used internally by archive).

**Parameters:**
- `id` (int) - Experience product ID (auto-detected on product pages)
- `show_image` (yes/no) - Show product image (default: yes)
- `show_price` (yes/no) - Show price (default: yes)
- `show_excerpt` (yes/no) - Show excerpt (default: yes)
- `show_rating` (yes/no) - Show rating stars (default: yes)
- `show_duration` (yes/no) - Show duration (default: yes)
- `show_difficulty` (yes/no) - Show difficulty level (default: yes)
- `image_size` (string) - WordPress image size (default: woocommerce_thumbnail)
- `class` (string) - Additional CSS classes

**Examples:**
```php
// Basic card for experience ID 123
[wcefp_experience_card id="123"]

// Minimal card with custom class
[wcefp_experience_card id="123" show_excerpt="no" show_difficulty="no" class="custom-card"]
```

## Booking & Conversion

### `[wcefp_booking_widget_v2]`

Enhanced booking widget with GYG-style layout and trust nudges.

**Parameters:**
- `id` (int) - Product ID (required)
- `layout` (string) - Layout: `gyg-style`, `compact`, `minimal` (default: gyg-style)
- `show_hero` (yes/no) - Show hero section (default: yes)
- `show_trust_badges` (yes/no) - Show trust badges (default: yes)
- `show_social_proof` (yes/no) - Show social proof (default: yes)
- `show_extras` (yes/no) - Show extras selection (default: yes)
- `show_meeting_point` (yes/no) - Show meeting point info (default: yes)
- `show_google_reviews` (yes/no) - Show Google reviews (default: yes)
- `trust_nudges` (string) - Trust nudge level: `none`, `minimal`, `moderate`, `high` (default: moderate)
- `color_scheme` (string) - Color scheme: `default`, `green`, `orange` (default: default)
- `class` (string) - Custom CSS classes

**Examples:**
```php
// Full-featured booking widget
[wcefp_booking_widget_v2 id="123" layout="gyg-style" trust_nudges="high"]

// Minimal booking widget
[wcefp_booking_widget_v2 id="123" layout="minimal" show_trust_badges="no" trust_nudges="none"]
```

### `[wcefp_trust_elements]`

Standalone trust elements for building custom layouts.

**Parameters:**
- `product_id` (int) - Product ID (auto-detected on product pages)
- `elements` (string) - Comma-separated list: `availability`, `recent_bookings`, `policies`, `social_proof`
- `style` (string) - Display style: `default`, `cards`, `minimal`, `badges`
- `layout` (string) - Layout: `vertical`, `horizontal`, `inline`

**Examples:**
```php
// Availability and recent bookings
[wcefp_trust_elements elements="availability,recent_bookings"]

// All trust elements in card style
[wcefp_trust_elements elements="availability,recent_bookings,policies,social_proof" style="cards"]
```

## Reviews & Social Proof

### `[wcefp_google_reviews_v2]`

Enhanced Google Reviews integration with meeting point support.

**Parameters:**
- `place_id` (string) - Google Place ID (uses product/global setting if not provided)
- `limit` (int) - Number of reviews (default: 5)
- `show_rating` (yes/no) - Show star ratings (default: yes)
- `show_avatar` (yes/no) - Show user avatars (default: yes)
- `show_date` (yes/no) - Show review dates (default: yes)
- `min_rating` (int) - Minimum rating to display 1-5 (default: 1)
- `style` (string) - Display style: `cards`, `minimal`, `compact` (default: cards)
- `layout` (string) - Layout: `grid`, `list`, `horizontal` (default: grid)
- `show_overall` (yes/no) - Show overall rating summary (default: yes)
- `show_attribution` (yes/no) - Show Google attribution (default: yes)

**Examples:**
```php
// Basic Google reviews
[wcefp_google_reviews_v2]

// Custom reviews with specific Place ID
[wcefp_google_reviews_v2 place_id="ChIJ..." limit="3" style="minimal"]

// High-rated reviews only
[wcefp_google_reviews_v2 min_rating="4" limit="10" layout="horizontal"]
```

## Legacy Shortcodes (Still Supported)

### `[wcefp_events]`

Lists events/experiences (legacy version, use `wcefp_experiences_archive` for new implementations).

### `[wcefp_booking_form]`

Basic booking form (legacy version, use `wcefp_booking_widget_v2` for enhanced features).

### `[wcefp_google_reviews]`

Basic Google reviews (legacy version, use `wcefp_google_reviews_v2` for enhanced features).

## Styling & Customization

### CSS Classes

All shortcodes use BEM methodology for CSS classes:

```css
/* Experience Archive */
.wcefp-experiences-archive { }
.wcefp-experiences-grid { }
.wcefp-experience-card { }

/* Booking Widget v2 */
.wcefp-booking-widget-v2 { }
.wcefp-booking-widget-v2__hero { }
.wcefp-booking-widget-v2__form { }

/* Trust Elements */
.wcefp-trust-elements { }
.wcefp-trust-element { }
.wcefp-trust-element--availability { }

/* Google Reviews v2 */
.wcefp-google-reviews-v2 { }
.wcefp-review-item { }
.wcefp-stars { }
```

### Custom Styling Examples

```css
/* Custom experience archive styling */
.wcefp-experiences-archive {
    --primary-color: #your-brand-color;
    --accent-color: #your-accent-color;
}

.wcefp-experience-card {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Custom booking widget colors */
.wcefp-booking-widget-v2.custom-brand {
    --primary-color: #ff6b35;
    --secondary-color: #f7931e;
}
```

## JavaScript Interaction

### Global Functions

```javascript
// Refresh experience archive
window.wcefpExperienceArchive.refresh();

// Set archive filters programmatically
window.wcefpExperienceArchive.setFilters({
    search: 'wine',
    duration: '1-3h',
    price: '50-100'
});

// Get current filters
const filters = window.wcefpExperienceArchive.getFilters();
```

### Events

```javascript
// Listen for filter changes
$(document).on('wcefp_archive_filtered', function(event, data) {
    console.log('Archive filtered:', data.filters, data.results);
});

// Listen for booking widget interactions
$(document).on('wcefp_booking_step_changed', function(event, step) {
    console.log('Booking step changed to:', step);
});
```

## Accessibility Features

All shortcodes are built with WCAG AA compliance:

- **Screen reader support** - Proper ARIA labels and announcements
- **Keyboard navigation** - Full keyboard accessibility
- **High contrast mode** - Compatible with high contrast displays  
- **Focus management** - Proper focus indicators and management
- **Alternative text** - Images include appropriate alt text

### Screen Reader Announcements

The experience archive automatically announces:
- Filter results: "X experiences found"  
- Loading states: "Loading experiences..."
- Error states: "Error loading experiences"

## Integration Examples

### Theme Integration

```php
// In your theme template
echo do_shortcode('[wcefp_experiences_archive columns="3" show_filters="yes"]');

// With PHP attributes
$shortcode_atts = [
    'columns' => get_theme_mod('experience_columns', 3),
    'layout' => get_theme_mod('experience_layout', 'grid'),
    'show_search' => get_theme_mod('show_experience_search', 'yes')
];

echo do_shortcode('[wcefp_experiences_archive ' . 
    implode(' ', array_map(function($key, $value) {
        return $key . '="' . esc_attr($value) . '"';
    }, array_keys($shortcode_atts), $shortcode_atts)) . 
    ']');
```

### Page Builder Integration

Most page builders support shortcodes directly:

**Elementor:** Use the "Shortcode" widget
**Gutenberg:** Use the "Shortcode" block or dedicated WCEventsFP blocks
**Visual Composer:** Use the "Raw HTML" element

### Custom Post Templates

```php
// single-product.php for experiences
if (get_post_meta(get_the_ID(), '_wcefp_is_experience', true) === '1') {
    // Show experience-specific booking widget
    echo do_shortcode('[wcefp_booking_widget_v2 id="' . get_the_ID() . '" layout="gyg-style"]');
    
    // Show Google reviews if meeting point is set
    $meeting_point_id = get_post_meta(get_the_ID(), '_wcefp_meeting_point_id', true);
    if ($meeting_point_id) {
        echo do_shortcode('[wcefp_google_reviews_v2 limit="5" style="cards"]');
    }
}
```

## Performance Notes

- **Conditional loading** - Assets only load when shortcodes are used
- **Caching** - Google Reviews and availability data are cached
- **Lazy loading** - Images use native lazy loading
- **Minification** - CSS/JS are minified in production

## Troubleshooting

### Common Issues

1. **Shortcode not displaying**
   - Check product ID validity
   - Verify shortcode syntax
   - Check browser console for errors

2. **Google Reviews not loading**
   - Verify API key in WCEventsFP Settings
   - Check Place ID format (should start with "ChIJ")
   - Monitor API quota usage

3. **Styling issues**
   - Check for theme CSS conflicts
   - Verify custom CSS syntax
   - Test with default WordPress theme

### Debug Mode

Enable debug mode to see detailed console logging:

```javascript
wcefpBookingV2.debug = true;
```

Or add to your theme's functions.php:

```php
add_action('wp_footer', function() {
    if (current_user_can('manage_options')) {
        echo '<script>
            if (typeof wcefpBookingV2 !== "undefined") {
                wcefpBookingV2.debug = true;
            }
        </script>';
    }
});
```