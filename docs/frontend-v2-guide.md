# WCEventsFP Frontend v2 - Implementation Guide

## Overview

WCEventsFP Frontend v2 introduces a complete redesign of the booking experience following modern UX patterns inspired by GetYourGuide and Regiondo, while maintaining ethical standards and avoiding dark patterns.

## Key Features

### 🎯 Enhanced Booking Widget v2
- **GYG/Regiondo-style layout** with hero section, step-by-step booking flow
- **Responsive design** optimized for mobile and desktop
- **Accessibility compliant** (WCAG AA standards)
- **Real-time availability** and pricing updates
- **Trust elements** integrated seamlessly

### 🔒 WooCommerce Archive Control
- **Hide events/experiences** from shop pages and search results
- **Optional single product redirects** to dedicated landing pages
- **SEO-friendly** implementation
- **Configurable settings** in WordPress admin

### ⭐ Enhanced Google Reviews Integration
- **Improved caching** system with 4-hour default cache
- **Rate limiting protection** to avoid API quota issues
- **Meeting point integration** for location-based reviews
- **Fallback system** when API is unavailable
- **Schema.org markup** for SEO benefits

### 🛡️ Ethical Trust Nudges
- **Data-driven approach** using real booking information
- **No dark patterns** - transparent and honest
- **Configurable intensity** levels (none, minimal, moderate, high)
- **Privacy-conscious** social proof indicators
- **Policy transparency** elements

## Implementation

### Shortcodes

#### Booking Widget v2
```php
[wcefp_booking_widget_v2 id="123" layout="gyg-style" show_hero="yes" show_trust_badges="yes" trust_nudges="moderate"]
```

**Parameters:**
- `id` - Product ID (required)
- `layout` - Layout style: `gyg-style`, `compact`, `minimal`
- `show_hero` - Show hero section: `yes`/`no`
- `show_trust_badges` - Show trust badges: `yes`/`no` 
- `show_social_proof` - Show social proof: `yes`/`no`
- `show_extras` - Show extras selection: `yes`/`no`
- `show_meeting_point` - Show meeting point: `yes`/`no`
- `show_google_reviews` - Show Google reviews: `yes`/`no`
- `trust_nudges` - Nudge level: `none`, `minimal`, `moderate`, `high`
- `color_scheme` - Color scheme: `default`, `green`, `orange`
- `class` - Custom CSS classes

#### Enhanced Google Reviews
```php
[wcefp_google_reviews_v2 place_id="ChIJ..." limit="5" style="cards" layout="grid"]
```

**Parameters:**
- `place_id` - Google Place ID (optional, uses product/global setting)
- `limit` - Number of reviews to display (default: 5)
- `show_rating` - Show star ratings: `yes`/`no`
- `show_avatar` - Show user avatars: `yes`/`no`
- `show_date` - Show review dates: `yes`/`no`
- `min_rating` - Minimum rating to display (1-5)
- `style` - Display style: `cards`, `minimal`, `compact`
- `layout` - Layout: `grid`, `list`, `horizontal`
- `show_overall` - Show overall rating: `yes`/`no`
- `show_attribution` - Show Google attribution: `yes`/`no`

#### Trust Elements
```php
[wcefp_trust_elements product_id="123" elements="availability,recent_bookings,policies"]
```

**Parameters:**
- `product_id` - Product ID (optional, auto-detected on product pages)
- `elements` - Comma-separated list of elements to show
- `style` - Display style: `default`, `cards`, `minimal`, `badges`
- `layout` - Layout: `vertical`, `horizontal`, `inline`

### Gutenberg Blocks

#### Booking Widget v2 Block
Available in the "WCEventsFP" block category with full visual editing support.

**Block Settings:**
- Product selection dropdown
- Layout options
- Trust element toggles
- Color scheme selector
- Advanced settings panel

### PHP Integration

#### Using the Booking Widget v2 Class
```php
use WCEFP\Frontend\BookingWidgetV2;

// Get instance from container
$widget_v2 = $container->get('frontend.booking_widget_v2');

// Or create directly
$widget_v2 = new BookingWidgetV2($container);
```

#### Using Archive Filter
```php
use WCEFP\Frontend\WooCommerceArchiveFilter;

$archive_filter = new WooCommerceArchiveFilter();
$stats = $archive_filter->get_filtered_product_stats();
```

#### Using Trust Nudges Manager
```php
use WCEFP\Frontend\TrustNudgesManager;

$trust_manager = new TrustNudgesManager();
$trust_html = $trust_manager->render_trust_elements($product_id, ['availability', 'policies']);
```

## Configuration

### Archive Filtering Settings

Navigate to **WCEventsFP Settings > Archive & Search Filtering**:

- **Hide from Shop Archives** - Remove events/experiences from shop page and category archives
- **Hide from Search Results** - Remove from WordPress search results
- **Redirect Single Products** - Redirect single product pages to landing pages

### Trust Nudges Settings

Navigate to **WCEventsFP Settings > Trust & Social Proof**:

- **Nudge Level** - Overall intensity: None, Minimal, Moderate, High
- **Availability Counter** - Show real availability data
- **Recent Bookings** - Show recent booking activity
- **People Viewing** - Show viewing counters (ethical implementation)
- **Best Seller Logic** - Criteria for best seller badges
- **Policy Badges** - Cancellation, confirmation, mobile voucher info

### Google Reviews Settings

Navigate to **WCEventsFP Settings > Google Integration**:

- **API Key** - Google Places API key
- **Default Place ID** - Global Place ID for reviews
- **Cache Duration** - Review cache time (default: 4 hours)
- **Fallback Mode** - Show placeholder when API unavailable

## Styling and Customization

### CSS Classes

The new frontend v2 system uses BEM methodology for CSS classes:

```css
.wcefp-booking-widget-v2 { }
.wcefp-booking-widget-v2__hero { }
.wcefp-booking-widget-v2__form { }
.wcefp-booking-widget-v2__summary { }

.wcefp-trust-elements { }
.wcefp-trust-element { }
.wcefp-trust-element--availability { }

.wcefp-google-reviews-v2 { }
.wcefp-review-item { }
.wcefp-stars { }
```

### Color Schemes

Built-in color schemes are available:

- **Default** - Blue (#007cba)
- **Green** - Green (#28a745) 
- **Orange** - Orange (#fd7e14)

### Responsive Breakpoints

- **Desktop** - 1024px+
- **Tablet** - 768px - 1023px
- **Mobile** - 480px - 767px
- **Small Mobile** - < 480px

## Advanced Features

### Schema.org Markup

Frontend v2 automatically includes structured data:

```json
{
  "@type": "Event",
  "name": "Experience Name",
  "offers": {
    "@type": "Offer",
    "price": "50.00",
    "priceCurrency": "EUR"
  },
  "location": {
    "@type": "Place",
    "name": "Meeting Point Name"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "reviewCount": "127"
  }
}
```

### AJAX Endpoints

Frontend v2 provides several AJAX endpoints:

- `wcefp_get_availability_v2` - Get real-time availability
- `wcefp_add_to_cart_v2` - Enhanced cart addition
- `wcefp_load_reviews` - Dynamic review loading
- `wcefp_get_trust_data` - Trust element updates

### Accessibility Features

- **ARIA labels** and roles for screen readers
- **Keyboard navigation** support
- **High contrast mode** compatibility
- **Screen reader announcements** for dynamic content
- **Focus management** for form interactions

## Migration from v1

### Compatibility

Frontend v2 is **fully backwards compatible** with existing v1 implementations:

- Original shortcodes continue to work
- Existing Gutenberg blocks remain functional
- No breaking changes to public APIs
- v1 and v2 can coexist on the same site

### Gradual Migration

1. **Install v2 alongside v1** - No immediate changes required
2. **Test v2 widgets** on development/staging sites
3. **Gradually replace v1 widgets** with v2 versions
4. **Update templates** to use new features
5. **Configure archive filtering** as needed

### Migration Checklist

- [ ] Update shortcodes to v2 versions
- [ ] Configure archive filtering settings
- [ ] Set up Google Reviews API integration
- [ ] Configure trust nudges settings
- [ ] Test responsive design on all devices
- [ ] Verify accessibility compliance
- [ ] Update custom CSS if needed

## Performance Considerations

### Conditional Loading

Scripts and styles are only loaded when needed:

- Presence of v2 shortcodes
- v2 Gutenberg blocks on page
- Product pages with events/experiences
- Pages with Google Reviews integration

### Caching Strategy

- **Google Reviews** cached for 4 hours
- **Availability data** cached for 10 minutes  
- **Trust data** cached for 30 minutes
- **Rate limiting** protection for APIs

### Optimization Tips

1. **Use specific Place IDs** instead of global settings when possible
2. **Set appropriate cache durations** based on content frequency
3. **Enable fallback modes** for better resilience
4. **Monitor API usage** to stay within quotas

## Troubleshooting

### Common Issues

#### Widget Not Displaying
- Check if product ID is valid
- Verify shortcode parameters
- Ensure scripts are enqueued
- Check console for JavaScript errors

#### Google Reviews Not Loading  
- Verify API key is valid
- Check Place ID format
- Monitor API quota usage
- Enable fallback mode

#### Trust Elements Not Showing
- Check nudge level setting
- Verify product has required data
- Check cache expiration
- Review trust settings configuration

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Browser Console

Frontend v2 includes detailed console logging in debug mode:

```javascript
wcefpBookingV2.debug = true; // Enable debug logging
```

## Best Practices

### Trust Elements

1. **Use real data only** - Never fake social proof
2. **Be transparent** - Clearly explain policies
3. **Respect privacy** - Anonymize user information
4. **Avoid pressure tactics** - Focus on genuine value
5. **Test different levels** - Find what works for your audience

### Performance

1. **Cache aggressively** - Use appropriate cache durations
2. **Load conditionally** - Only include what's needed
3. **Optimize images** - Use appropriate sizes and formats
4. **Monitor metrics** - Track loading times and conversions

### Accessibility

1. **Test with screen readers** - Verify all content is accessible
2. **Use proper headings** - Maintain logical hierarchy
3. **Provide alt text** - For all images and icons
4. **Test keyboard navigation** - Ensure all features work
5. **Check color contrast** - Meet WCAG standards

## Support and Updates

Frontend v2 is actively maintained and updated. For support:

1. **Check documentation** - This guide and inline code comments
2. **Review error logs** - WordPress debug logs and browser console
3. **Test on staging** - Before making changes to production
4. **Follow best practices** - As outlined in this guide

For feature requests and bug reports, please follow the project's contribution guidelines.