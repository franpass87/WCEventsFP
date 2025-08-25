# 🗂️ WCEventsFP Complete Options & Meta Keys Map

> **Document Version**: 1.0  
> **Last Updated**: August 25, 2024  
> **Plugin Version**: 2.2.0  

---

## 📊 Overview

This document provides a comprehensive mapping of all WordPress options, WooCommerce product meta keys, and configuration values used throughout WCEventsFP. This serves as a reference for developers, integrators, and system administrators.

---

## 🔧 WordPress Options (wp_options table)

### **General Settings** (`wcefp_general_settings_group`)

| Option Name | Type | Default | Description |
|-------------|------|---------|-------------|
| `wcefp_general_settings` | Array | `[]` | General plugin settings container |
| `wcefp_default_capacity` | Integer | `10` | Default event capacity when not specified |
| `wcefp_default_currency` | String | WC Default | Default currency for pricing |
| `wcefp_timezone` | String | WP Default | Plugin timezone setting |
| `wcefp_date_format` | String | WP Default | Date format for displays |
| `wcefp_time_format` | String | WP Default | Time format for displays |

### **Archive & Search Filtering** (`wcefp_archive_settings`)

| Option Name | Type | Default | Description |
|-------------|------|---------|-------------|
| `wcefp_hide_from_shop` | Boolean | `true` | Hide experiences from shop archives |
| `wcefp_hide_from_search` | Boolean | `true` | Hide from WordPress search results |
| `wcefp_hide_from_feeds` | Boolean | `true` | Hide from RSS/Atom feeds |
| `wcefp_hide_from_sitemaps` | Boolean | `true` | Hide from XML sitemaps |
| `wcefp_hide_from_rest` | Boolean | `true` | Hide from WC REST API listings |
| `wcefp_redirect_single_product` | Boolean | `false` | Redirect single products to landing pages |

### **Trust & Social Proof** (`wcefp_trust_settings`)

| Option Name | Type | Default | Description |
|-------------|------|---------|-------------|
| `wcefp_trust_availability_counter` | Boolean | `true` | Show availability counters |
| `wcefp_trust_availability_high` | Integer | `10` | High availability threshold |
| `wcefp_trust_availability_low` | Integer | `3` | Low availability threshold |
| `wcefp_trust_recent_bookings` | Boolean | `true` | Show recent booking activity |
| `wcefp_trust_recent_timeframe` | Integer | `24` | Recent bookings timeframe (hours) |
| `wcefp_trust_show_locations` | Boolean | `false` | Show booking locations |
| `wcefp_trust_people_viewing` | Boolean | `false` | Show people viewing counters |
| `wcefp_trust_viewing_method` | String | `conservative` | Viewing count calculation method |
| `wcefp_trust_viewing_min` | Integer | `2` | Minimum viewing count |
| `wcefp_trust_viewing_max` | Integer | `8` | Maximum viewing count |
| `wcefp_trust_best_seller` | Boolean | `true` | Show best seller badges |
| `wcefp_trust_best_seller_threshold` | Integer | `10` | Best seller booking threshold |
| `wcefp_trust_best_seller_period` | Integer | `30` | Best seller evaluation period (days) |
| `wcefp_trust_cancellation_policy` | Boolean | `true` | Show cancellation policy info |
| `wcefp_trust_cancellation_hours` | Integer | `24` | Free cancellation hours |
| `wcefp_trust_instant_confirmation` | Boolean | `true` | Show instant confirmation badge |
| `wcefp_trust_mobile_voucher` | Boolean | `true` | Show mobile voucher badge |
| `wcefp_trust_price_breakdown` | Boolean | `true` | Show price breakdown |
| `wcefp_trust_no_hidden_fees` | Boolean | `true` | Show no hidden fees badge |
| `wcefp_trust_currency_disclaimer` | Boolean | `false` | Show currency conversion disclaimer |
| `wcefp_trust_nudge_level` | String | `moderate` | Overall nudge intensity level |

### **Google Integration** (`wcefp_google_settings`)

| Option Name | Type | Default | Description |
|-------------|------|---------|-------------|
| `wcefp_google_places_api_key` | String | `''` | Google Places API key |
| `wcefp_google_place_id` | String | `''` | Global Google Place ID |
| `wcefp_google_maps_api_key` | String | `''` | Google Maps API key |
| `wcefp_google_reviews_cache_duration` | Integer | `14400` | Reviews cache duration (seconds) |

### **Notification & Email Settings** (`wcefp_notification_settings`)

| Option Name | Type | Default | Description |
|-------------|------|---------|-------------|
| `wcefp_notification_settings` | Array | `[]` | Notification system configuration |
| `wcefp_contact_phone` | String | `''` | Business contact phone |
| `wcefp_contact_email` | String | Admin Email | Business contact email |
| `wcefp_admin_booking_notifications_enabled` | Boolean | `true` | Admin booking notifications |
| `wcefp_admin_notification_emails` | Array | `[admin_email]` | Admin notification recipients |
| `wcefp_daily_admin_summary_enabled` | Boolean | `false` | Daily admin summary emails |
| `wcefp_capacity_alert_threshold` | Integer | `80` | Capacity alert threshold (%) |
| `wcefp_webhook_urls` | Array | `[]` | Webhook notification URLs |

### **Feature Flags** (`wcefp_features_settings`)

| Option Name | Type | Default | Description |
|-------------|------|---------|-------------|
| `wcefp_features_settings` | Array | `[]` | Feature flags container |
| `wcefp_analytics_dashboard` | Boolean | `false` | Analytics dashboard enabled |
| `wcefp_advanced_pricing` | Boolean | `true` | Advanced pricing engine |
| `wcefp_multi_day_events` | Boolean | `true` | Multi-day event support |
| `wcefp_capacity_management` | Boolean | `true` | Advanced capacity management |
| `wcefp_stock_holds_enabled` | Boolean | `true` | TTL-based stock holds |
| `wcefp_extras_system` | Boolean | `true` | Extras and add-ons system |
| `wcefp_meeting_points_system` | Boolean | `true` | Meeting points system |

### **Global Policies & Rules** 

| Option Name | Type | Default | Description |
|-------------|------|---------|-------------|
| `wcefp_global_policies` | Array | `[]` | Global cancellation/refund policies |
| `wcefp_global_closures` | Array | `[]` | Global closure dates/periods |

### **Database & System**

| Option Name | Type | Default | Description |
|-------------|------|---------|-------------|
| `wcefp_services_db_version` | String | Plugin Version | Services database version |
| `wcefp_phase2_notice_dismissed` | Boolean | `false` | Phase 2 upgrade notice dismissed |
| `wcefp_automation_settings` | Array | `[]` | Automation system settings |

---

## 🛍️ WooCommerce Product Meta Keys (wp_postmeta table)

### **Experience/Event Identification**

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_wcefp_is_experience` | String ('1'/empty) | Mark product as experience/event |
| `_wcefp_product_type` | String | Experience type classification |
| `_wcefp_experience_category` | String | Experience category |

### **Capacity & Stock Management**

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_wcefp_capacity` | Integer | Base capacity per occurrence |
| `_wcefp_capacity_config` | Array | Advanced capacity configuration |
| `_wcefp_allow_overbooking` | Boolean | Allow overbooking flag |
| `_wcefp_overbooking_percentage` | Integer | Overbooking percentage allowed |

### **Scheduling & Time Configuration**

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_wcefp_scheduling_type` | String | Scheduling pattern type |
| `_wcefp_recurring_pattern` | Array | Recurring schedule configuration |
| `_wcefp_occurrence_duration` | Integer | Event duration in minutes |
| `_wcefp_time_slots` | Array | Available time slots |
| `_wcefp_booking_window_days` | Integer | Booking window in days |
| `_wcefp_booking_deadline_hours` | Integer | Booking deadline hours |

### **Pricing & Financial**

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_wcefp_pricing_rules` | Array | Dynamic pricing rules |
| `_wcefp_early_bird_discount` | Array | Early bird pricing config |
| `_wcefp_last_minute_pricing` | Array | Last minute pricing config |
| `_wcefp_seasonal_pricing` | Array | Seasonal pricing rules |
| `_wcefp_group_discounts` | Array | Group discount tiers |

### **Extras & Add-ons**

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_wcefp_product_extras` | Array | Product-specific extras |
| `_wcefp_linked_extras` | Array | Linked reusable extras |
| `_wcefp_extras_required` | Array | Required extras configuration |

### **Meeting Points & Location**

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_wcefp_meeting_points` | Array | Available meeting points |
| `_wcefp_default_meeting_point` | Integer | Default meeting point ID |
| `_wcefp_location_data` | Array | Geographic location data |
| `_wcefp_address_components` | Array | Structured address data |

### **Policies & Rules**

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_wcefp_policies` | Array | Product-specific policies |
| `_wcefp_cancellation_policy` | Array | Cancellation policy config |
| `_wcefp_weather_policy` | Array | Weather-related policies |
| `_wcefp_age_restrictions` | Array | Age restriction rules |

### **Trust Elements & Social Proof**

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_wcefp_is_best_seller` | Boolean | Best seller flag |
| `_wcefp_free_cancellation` | Boolean | Free cancellation available |
| `_wcefp_instant_confirmation` | Boolean | Instant confirmation available |
| `_wcefp_mobile_voucher` | Boolean | Mobile voucher supported |
| `_wcefp_accessibility_features` | Array | Accessibility features |

### **SEO & Visibility**

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_wcefp_seo_title` | String | Custom SEO title |
| `_wcefp_seo_description` | String | Custom SEO description |
| `_wcefp_landing_page_id` | Integer | Custom landing page ID |
| `_wcefp_hide_from_search` | Boolean | Hide from search (product-level) |

---

## 🎫 Extras (Custom Post Type) Meta Keys

### **Extra Product Configuration**

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_wcefp_extra_price` | Float | Extra price |
| `_wcefp_extra_pricing_type` | String | Pricing type (fixed/percentage/per_person) |
| `_wcefp_extra_max_quantity` | Integer | Maximum quantity allowed |
| `_wcefp_extra_required` | Boolean | Required extra flag |
| `_wcefp_extra_stock` | Integer | Stock quantity |
| `_wcefp_extra_manage_stock` | Boolean | Manage stock flag |
| `_wcefp_extra_category` | String | Extra category |
| `_wcefp_extra_conditions` | Array | Availability conditions |

---

## 📍 Meeting Points (Custom Post Type) Meta Keys

### **Location Data**

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_wcefp_meeting_point_address` | String | Full address |
| `_wcefp_meeting_point_lat` | Float | Latitude coordinate |
| `_wcefp_meeting_point_lng` | Float | Longitude coordinate |
| `_wcefp_meeting_point_google_place_id` | String | Google Place ID |
| `_wcefp_meeting_point_accessibility` | Array | Accessibility features |
| `_wcefp_meeting_point_transport` | Array | Transportation info |
| `_wcefp_meeting_point_instructions` | Text | Meeting instructions |

---

## 🏗️ Database Tables Custom Columns

### **wcefp_occurrences**
- `occurrence_id`, `product_id`, `occurrence_date`, `start_time`, `end_time`, `capacity`, `booked`, `status`, `created_at`

### **wcefp_tickets**  
- `ticket_id`, `product_id`, `ticket_type`, `price`, `capacity`, `age_min`, `age_max`, `created_at`

### **wcefp_booking_items**
- `item_id`, `occurrence_id`, `order_item_id`, `ticket_type`, `quantity`, `unit_price`, `total_price`, `customer_data`

### **wcefp_extras**
- `extra_id`, `product_id`, `extra_post_id`, `quantity`, `unit_price`, `total_price`

### **wcefp_stock_holds**
- `hold_id`, `occurrence_id`, `ticket_type`, `quantity`, `session_id`, `expires_at`, `created_at`

---

## 🎯 Configuration Patterns & Best Practices

### **Settings Hierarchy**
1. **Global Options** (wp_options) - Site-wide defaults
2. **Product Meta** (wp_postmeta) - Product-specific overrides  
3. **Runtime Calculations** - Dynamic values based on data

### **Feature Flag Strategy**
- Enable/disable entire subsystems via feature flags
- Graceful degradation when features are disabled
- Performance optimization through conditional loading

### **Caching Strategy**
- Transient caching for expensive API calls
- Object caching for frequently accessed meta data
- Database query optimization via proper indexing

### **Security Considerations**
- All options sanitized on input
- Capability checks for sensitive settings
- Nonce verification for all admin actions
- SQL injection prevention via prepared statements

---

## 🔧 Developer Integration Examples

### **Getting Settings Programmatically**
```php
// Get trust settings
$trust_settings = (new WCEFP\Frontend\TrustNudgesManager())->get_trust_settings();

// Get archive filtering status
$hide_from_shop = get_option('wcefp_hide_from_shop', true);

// Get product-specific settings
$is_experience = get_post_meta($product_id, '_wcefp_is_experience', true);
$capacity = get_post_meta($product_id, '_wcefp_capacity', true);
```

### **Modifying Settings via Hooks**
```php
// Filter default trust settings
add_filter('wcefp_trust_default_settings', function($defaults) {
    $defaults['nudge_level'] = 'high';
    return $defaults;
});

// Override archive filtering
add_filter('wcefp_archive_filtering_enabled', function($enabled, $context) {
    return $context === 'search' ? false : $enabled;
}, 10, 2);
```

---

## 📈 Migration & Versioning

### **Legacy Compatibility**
- Old meta keys maintained for backward compatibility
- Migration hooks for smooth upgrades
- Version checks prevent data loss

### **Future Extensibility**  
- Structured data formats (arrays/JSON) for complex settings
- Hook-based architecture for third-party extensions
- Database versioning for schema changes

---

*This options map serves as the definitive reference for all configurable aspects of WCEventsFP, ensuring consistent implementation across development teams and integration projects.*