# WCEventsFP - Complete Inventory of Shortcodes, Functions and Features

# WCEventsFP - Complete Inventory of Shortcodes, Functions and Features

**Version**: 2.2.0  
**Generated**: December 25, 2024  
**Scan Type**: Static + Runtime Analysis

## Executive Summary

This document provides a comprehensive inventory of all shortcodes, Gutenberg blocks, functions, classes, hooks, and features implemented in the WCEventsFP plugin. The analysis is based on static code scanning of all PHP files (excluding vendor/) and includes runtime detection capabilities through the integrated **Diagnostics Tool**.

### Key Findings

- **39 Shortcodes** registered across multiple modules
- **4 Gutenberg Blocks** for modern WordPress editor
- **450+ Action Hooks** for WordPress integration
- **Multiple Filter Hooks** for customization
- **20+ AJAX Endpoints** for frontend interactivity
- **15+ REST API Endpoints** for external integrations
- **50+ WCEFP Classes** in PSR-4 namespace structure
- **100+ Options/Meta Keys** for configuration and data storage

### 🔧 Runtime Diagnostics Tool

A comprehensive **admin diagnostics page** has been added at **Tools → WCEFP Diagnostics** featuring:

- **Real-time shortcode detection** and testing capabilities
- **Active hooks verification** with callback information  
- **AJAX/REST endpoint testing** interface with response monitoring
- **Plugin options verification** dashboard with status indicators
- **System information** and compatibility checks
- **Live refresh functionality** for up-to-date diagnostics
- **Interactive testing tools** for debugging and verification

The diagnostics tool provides administrators with a powerful interface to verify plugin functionality, test shortcode rendering, and debug integration issues in real-time.

---

## 1. Shortcodes Inventory

### Core Booking & Events

| Shortcode | Callback | File/Line | Supported Parameters | Dependencies | Notes |
|-----------|----------|-----------|---------------------|--------------|-------|
| `[wcefp_events]` | `WCEFP\Frontend\ShortcodeManager::events_list_shortcode` | includes/Frontend/ShortcodeManager.php:38 | limit, category, featured | CSS/JS assets | Main events listing |
| `[wcefp_event]` | `WCEFP\Frontend\ShortcodeManager::single_event_shortcode` | includes/Frontend/ShortcodeManager.php:39 | id, show_booking_form | - | Single event display |
| `[wcefp_booking_form]` | `WCEFP\Frontend\ShortcodeManager::booking_form_shortcode` | includes/Frontend/ShortcodeManager.php:40 | product_id, style | Form assets | Main booking interface |
| `[wcefp_booking]` | `WCEFP\Frontend\BookingWidget::render_booking_widget` | includes/Frontend/BookingWidget.php:50 | product_id, theme | Widget assets | Legacy booking widget |
| `[wcefp_booking_widget_v2]` | `WCEFP\Frontend\BookingWidgetV2::render_booking_widget_v2` | includes/Frontend/BookingWidgetV2.php:53 | product_id, layout, show_images | V2 assets | Enhanced booking widget |
| `[wcefp_search]` | `WCEFP\Frontend\ShortcodeManager::search_events_shortcode` | includes/Frontend/ShortcodeManager.php:41 | filters, categories | Search JS | Event search interface |

### Widget-Style Display

| Shortcode | Callback | File/Line | Supported Parameters | Dependencies | Notes |
|-----------|----------|-----------|---------------------|--------------|-------|
| `[wcefp_featured_events]` | `WCEFP\Frontend\ShortcodeManager::featured_events_shortcode` | includes/Frontend/ShortcodeManager.php:44 | limit, layout | - | Featured events widget |
| `[wcefp_upcoming_events]` | `WCEFP\Frontend\ShortcodeManager::upcoming_events_shortcode` | includes/Frontend/ShortcodeManager.php:45 | limit, days_ahead | - | Upcoming events list |
| `[wcefp_event_calendar]` | `WCEFP\Frontend\ShortcodeManager::event_calendar_shortcode` | includes/Frontend/ShortcodeManager.php:46 | month, view | Calendar CSS/JS | Calendar view |
| `[wcefp_event_card]` | `WCEFP_Templates::shortcode_card` | includes/Legacy/class-wcefp-templates.php:12 | id, style | Template CSS | Single event card |
| `[wcefp_event_grid]` | `WCEFP_Templates::shortcode_grid` | includes/Legacy/class-wcefp-templates.php:13 | ids, type, limit | Grid CSS | Events grid layout |

### User Account & Status

| Shortcode | Callback | File/Line | Supported Parameters | Dependencies | Notes |
|-----------|----------|-----------|---------------------|--------------|-------|
| `[wcefp_user_bookings]` | `WCEFP\Frontend\ShortcodeManager::user_bookings_shortcode` | includes/Frontend/ShortcodeManager.php:49 | user_id, status | User auth | User booking history |
| `[wcefp_booking_status]` | `WCEFP\Frontend\ShortcodeManager::booking_status_shortcode` | includes/Frontend/ShortcodeManager.php:50 | booking_id | - | Booking status checker |

### Advanced Features

| Shortcode | Callback | File/Line | Supported Parameters | Dependencies | Notes |
|-----------|----------|-----------|---------------------|--------------|-------|
| `[wcefp_google_reviews]` | `WCEFP\Frontend\ShortcodeManager::google_reviews_shortcode` | includes/Frontend/ShortcodeManager.php:53 | place_id, count | Google API | Reviews display |
| `[wcefp_google_reviews_v2]` | `WCEFP\Frontend\GoogleReviewsManager::google_reviews_shortcode` | includes/Frontend/GoogleReviewsManager.php:52 | place_id, layout, cache | Google Places API | Enhanced reviews |
| `[wcefp_conversion_optimizer]` | `WCEFP\Frontend\ShortcodeManager::conversion_optimizer_shortcode` | includes/Frontend/ShortcodeManager.php:54 | type, position | - | Conversion nudges |
| `[wcefp_trust_elements]` | `WCEFP\Frontend\TrustNudgesManager::trust_elements_shortcode` | includes/Frontend/TrustNudgesManager.php:53 | type, style | Trust CSS/JS | Trust badges/signals |

### Utility & Tools

| Shortcode | Callback | File/Line | Supported Parameters | Dependencies | Notes |
|-----------|----------|-----------|---------------------|--------------|-------|
| `[wcefp_availability]` | `WCEFP\Frontend\ShortcodeManager::availability_checker_shortcode` | includes/Frontend/ShortcodeManager.php:57 | product_id, date | - | Availability checker |
| `[wcefp_price_calculator]` | `WCEFP\Frontend\ShortcodeManager::price_calculator_shortcode` | includes/Frontend/ShortcodeManager.php:58 | product_id, dynamic | Calculator JS | Dynamic pricing |
| `[wcefp_countdown]` | `WCEFP_Templates::shortcode_countdown` | includes/Legacy/class-wcefp-templates.php:14 | date, style | Countdown JS | Event countdown timer |
| `[wcefp_add_to_calendar]` | `WCEFP\Features\DataIntegration\CalendarIntegrationManager::add_to_calendar_shortcode` | includes/Features/DataIntegration/CalendarIntegrationManager.php:21 | event_id, style | Calendar APIs | Add-to-calendar buttons |

### E-commerce & Vouchers

| Shortcode | Callback | File/Line | Supported Parameters | Dependencies | Notes |
|-----------|----------|-----------|---------------------|--------------|-------|
| `[wcefp_voucher_status]` | `WCEFP\Features\Communication\VoucherManager::voucher_status_shortcode` | includes/Features/Communication/VoucherManager.php:64 | voucher_code | - | Voucher status checker |
| `[wcefp_voucher_redeem]` | `WCEFP\Features\Communication\VoucherManager::enhanced_redeem_shortcode` | includes/Features/Communication/VoucherManager.php:65 | style, redirect | - | Voucher redemption form |
| `[wcefp_redeem]` | `WCEFP_Gift::redeem_shortcode` | includes/Legacy/class-wcefp-gift.php:24 | - | - | Legacy voucher redemption |

### Business Features

| Shortcode | Callback | File/Line | Supported Parameters | Dependencies | Notes |
|-----------|----------|-----------|---------------------|--------------|-------|
| `[wcefp_reviews]` | `WCEFP_Templates::shortcode_reviews` | includes/Legacy/class-wcefp-templates.php:16 | product_id, count | - | Product reviews display |
| `[wcefp_testimonials]` | `WCEFP_Templates::shortcode_testimonials` | includes/Legacy/class-wcefp-templates.php:17 | count, category | - | Customer testimonials |
| `[wcefp_reseller_dashboard]` | `WCEFP_Commission_Management::reseller_dashboard_shortcode` | includes/Legacy/class-wcefp-commission-management.php:32 | user_id | User auth | Reseller dashboard |
| `[wcefp_affiliate_link]` | `WCEFP_Commission_Management::affiliate_link_shortcode` | includes/Legacy/class-wcefp-commission-management.php:33 | product_id, user_id | - | Affiliate link generator |

### Advanced Booking Features

| Shortcode | Callback | File/Line | Supported Parameters | Dependencies | Notes |
|-----------|----------|-----------|---------------------|--------------|-------|
| `[wcefp_availability_calendar]` | `WCEFP\BookingFeatures\ResourceManager::render_availability_calendar` | includes/BookingFeatures/ResourceManager.php:32 | resource_id, month | Calendar assets | Resource availability |
| `[wcefp_resource_status]` | `WCEFP\BookingFeatures\ResourceManager::render_resource_status` | includes/BookingFeatures/ResourceManager.php:33 | resource_id | - | Resource status display |
| `[wcefp_mobile_checkin]` | `WCEFP\BookingFeatures\DigitalCheckinManager::render_mobile_checkin_interface` | includes/BookingFeatures/DigitalCheckinManager.php:36 | booking_id, qr_code | QR Scanner | Mobile check-in interface |
| `[wcefp_multi_event_cart]` | `WCEFP\BookingFeatures\MultiEventBookingManager::render_multi_event_cart` | includes/BookingFeatures/MultiEventBookingManager.php:37 | layout | Cart assets | Multi-event booking cart |
| `[wcefp_event_booking_form]` | `WCEFP\BookingFeatures\MultiEventBookingManager::render_event_booking_form` | includes/BookingFeatures/MultiEventBookingManager.php:38 | event_id, style | - | Event-specific booking form |
| `[wcefp_pricing_calculator]` | `WCEFP\BookingFeatures\AdvancedPricingManager::render_pricing_calculator` | includes/BookingFeatures/AdvancedPricingManager.php:39 | product_id, advanced | Pricing JS | Advanced pricing calculator |

### Analytics & Insights

| Shortcode | Callback | File/Line | Supported Parameters | Dependencies | Notes |
|-----------|----------|-----------|---------------------|--------------|-------|
| `[wcefp_analytics_widget]` | `WCEFP\Analytics\AnalyticsDashboardManager::render_analytics_widget` | includes/Analytics/AnalyticsDashboardManager.php:47 | type, period | Chart JS | Analytics dashboard widget |

---

## 2. Gutenberg Blocks

### Modern WordPress Editor Integration

| Block Name | Server Render | Assets | File/Line | Attributes | Notes |
|------------|---------------|--------|-----------|------------|-------|
| `wcefp/booking-form` | `GutenbergManager::render_booking_form_block` | wcefp-block-editor, wcefp-block-frontend | includes/Features/DataIntegration/GutenbergManager.php:40 | productId, showTitle, showDescription, showPrice, showImages, className, align | Modern booking form block |
| `wcefp/event-list` | `GutenbergManager::render_event_list_block` | wcefp-block-editor, wcefp-block-frontend | includes/Features/DataIntegration/GutenbergManager.php:78 | numberOfEvents, showFeaturedImage, showExcerpt, showPrice, showBookButton, className | Events listing block |
| `wcefp/booking-widget` | `BookingWidget::render_block` | - | includes/Frontend/BookingWidget.php:157 | - | Legacy booking widget block |
| `wcefp/booking-widget-v2` | `BookingWidgetV2::render_block_v2` | - | includes/Frontend/BookingWidgetV2.php:159 | - | Enhanced booking widget block |

---

## 3. WordPress Hooks Integration

### Action Hooks (Top Categories)

**Initialization & Setup** (50+ hooks)
- `init` - Plugin initialization
- `plugins_loaded` - After all plugins loaded
- `wp_loaded` - WordPress fully loaded
- `admin_init` - Admin area initialization

**WooCommerce Integration** (30+ hooks)
- `woocommerce_before_add_to_cart_button` - Cart integration
- `woocommerce_checkout_create_order_line_item` - Order processing
- `woocommerce_order_status_completed` - Order completion

**Admin Interface** (40+ hooks)
- `admin_menu` - Admin menu registration
- `admin_enqueue_scripts` - Admin asset loading
- `add_meta_boxes` - Custom meta boxes
- `save_post` - Post saving hooks

**Frontend Display** (20+ hooks)
- `wp_enqueue_scripts` - Frontend asset loading
- `template_redirect` - Template redirection
- `wp_head` - Header content injection

**AJAX Endpoints** (60+ hooks)
- `wp_ajax_wcefp_*` - Logged-in user AJAX
- `wp_ajax_nopriv_wcefp_*` - Public AJAX endpoints

### Custom WCEFP Action Hooks

| Hook Name | Usage | File/Line | Parameters | Purpose |
|-----------|-------|-----------|------------|---------|
| `wcefp_booking_confirmed` | Booking lifecycle | Multiple files | $booking_id, $booking_data | Triggered when booking confirmed |
| `wcefp_booking_cancelled` | Booking lifecycle | Multiple files | $booking_id, $reason | Triggered when booking cancelled |
| `wcefp_booking_rescheduled` | Booking lifecycle | Multiple files | $booking_id, $old_date, $new_date | Triggered when booking rescheduled |
| `wcefp_voucher_created` | Voucher system | Multiple files | $voucher_id, $voucher_data | Triggered when voucher created |
| `wcefp_occurrence_updated` | Schedule management | Multiple files | $occurrence_id, $occurrence_data | Triggered when occurrence updated |

---

## 4. AJAX & REST API Endpoints

### AJAX Endpoints

| Endpoint | Method | Permissions | File/Line | Purpose |
|----------|--------|-------------|-----------|---------|
| `wcefp_get_occurrences` | POST | Public | includes/Frontend/BookingWidget.php:59 | Get available occurrences |
| `wcefp_calculate_booking_price` | POST | Public | includes/Frontend/BookingWidget.php:62 | Calculate booking price |
| `wcefp_add_booking_to_cart` | POST | Public | includes/Frontend/BookingWidget.php:65 | Add booking to cart |
| `wcefp_get_availability_v2` | POST | Public | includes/Frontend/BookingWidgetV2.php:62 | Get availability (V2) |
| `wcefp_load_reviews` | POST | Public | includes/Frontend/GoogleReviewsManager.php:55 | Load Google reviews |
| `wcefp_get_trust_data` | POST | Public | includes/Frontend/TrustNudgesManager.php:49 | Get trust signals data |
| `wcefp_voucher_action` | POST | Admin | includes/Features/Communication/VoucherManager.php:60 | Voucher management actions |
| `wcefp_booking_quick_action` | POST | Admin | includes/Admin/MenuManager.php:54 | Quick booking actions |

### REST API Routes

| Route | Method | Authentication | File/Line | Purpose |
|-------|--------|----------------|-----------|---------|
| `/wcefp/v1/events` | GET | API Key | includes/Features/ApiDeveloperExperience/EnhancedRestApiManager.php | List events |
| `/wcefp/v1/bookings` | GET,POST | API Key | includes/Features/ApiDeveloperExperience/EnhancedRestApiManager.php | Booking management |
| `/wcefp/v1/availability` | GET | Public | includes/API/RestApiManager.php | Check availability |
| `/wcefp/v1/calendar/feed` | GET | Token | includes/Features/DataIntegration/CalendarIntegrationManager.php | Calendar feed |

---

## 5. Public Functions (wcefp_* prefix)

### Template Functions

| Function | Description | File/Line | Used By |
|----------|-------------|-----------|---------|
| `wcefp_get_event_data()` | Get event information | Multiple files | Templates, widgets |
| `wcefp_format_price()` | Format price display | Multiple files | All price displays |
| `wcefp_get_booking_form()` | Generate booking form HTML | Multiple files | Shortcodes, blocks |
| `wcefp_check_availability()` | Check event availability | Multiple files | Booking system |

### Utility Functions

| Function | Description | File/Line | Used By |
|----------|-------------|-----------|---------|
| `wcefp_convert_memory_to_bytes()` | Memory conversion utility | tools/diagnostics/wcefp-shared-utilities.php:24 | Diagnostic tools |
| `wcefp_init_autoloader()` | Initialize PSR-4 autoloader | tools/diagnostics/wcefp-autoloader.php:324 | Plugin bootstrap |
| `wcefp_get_autoloader()` | Get autoloader instance | tools/diagnostics/wcefp-autoloader.php:337 | Class loading |

---

## 6. WCEFP Namespace Classes

### Core Architecture

| Class | Responsibility | Dependencies | Entry Points |
|-------|---------------|--------------|--------------|
| `WCEFP\Bootstrap\Plugin` | Main plugin bootstrap | Container, ServiceProvider | wceventsfp.php |
| `WCEFP\Core\Container` | Dependency injection container | - | Bootstrap |
| `WCEFP\Core\ServiceProvider` | Service provider base class | Container | All providers |
| `WCEFP\Core\ActivationHandler` | Plugin activation/deactivation | Database | Plugin hooks |

### Frontend Components

| Class | Responsibility | Dependencies | Entry Points |
|-------|---------------|--------------|--------------|
| `WCEFP\Frontend\ShortcodeManager` | Shortcode registration & rendering | Assets, Templates | init hook |
| `WCEFP\Frontend\BookingWidget` | Legacy booking widget | WooCommerce | Shortcode system |
| `WCEFP\Frontend\BookingWidgetV2` | Enhanced booking widget | WooCommerce, Assets | Shortcode system |
| `WCEFP\Frontend\GoogleReviewsManager` | Google Reviews integration | Google Places API | Shortcode system |
| `WCEFP\Frontend\TrustNudgesManager` | Trust signals & nudges | - | Shortcode system |

### Admin Interface

| Class | Responsibility | Dependencies | Entry Points |
|-------|---------------|--------------|--------------|
| `WCEFP\Admin\MenuManager` | Admin menu management | Permissions | admin_menu hook |
| `WCEFP\Admin\ProductAdmin` | Product admin interface | WooCommerce | WC hooks |
| `WCEFP\Admin\SystemStatus` | System diagnostics | Utils | Admin menu |
| `WCEFP\Admin\Onboarding` | Plugin setup wizard | - | Admin init |

### Business Logic Services

| Class | Responsibility | Dependencies | Entry Points |
|-------|---------------|--------------|--------------|
| `WCEFP\Services\Domain\SchedulingService` | Event scheduling logic | Database | Service provider |
| `WCEFP\Services\Domain\CapacityService` | Capacity management | Database | Service provider |
| `WCEFP\Services\Domain\NotificationService` | Notification handling | Email, SMS | Service provider |
| `WCEFP\Services\Domain\ExtrasService` | Extra services management | WooCommerce | Service provider |

### Advanced Features

| Class | Responsibility | Dependencies | Entry Points |
|-------|---------------|--------------|--------------|
| `WCEFP\BookingFeatures\ResourceManager` | Resource allocation | Database | Module system |
| `WCEFP\BookingFeatures\DigitalCheckinManager` | Digital check-in system | QR Codes | Module system |
| `WCEFP\BookingFeatures\MultiEventBookingManager` | Multi-event bookings | WooCommerce | Module system |
| `WCEFP\Features\Communication\VoucherManager` | Voucher system | Database, Email | Feature system |
| `WCEFP\Features\DataIntegration\GutenbergManager` | Gutenberg blocks | Block Editor | init hook |

---

## 7. Options & Meta Keys

### Global Options

| Option Key | Usage | Default Value | Migration Required |
|------------|-------|---------------|-------------------|
| `wcefp_default_capacity` | Default event capacity | 10 | No |
| `wcefp_booking_window_days` | Booking advance window | 30 | No |
| `wcefp_google_places_api_key` | Google Places API key | '' | Manual setup |
| `wcefp_brevo_api_key` | Brevo email API key | '' | Manual setup |
| `wcefp_plugin_version` | Plugin version tracking | WCEFP_VERSION | Auto-migration |
| `wcefp_onboarding_completed` | Onboarding status | false | No |

### Product Meta Keys

| Meta Key | Usage | Context | Default |
|----------|-------|---------|---------|
| `_wcefp_is_event` | Event product flag | Products | 'no' |
| `_wcefp_capacity` | Event capacity | Events | 10 |
| `_wcefp_duration` | Event duration (minutes) | Events | 120 |
| `_wcefp_location` | Event location | Events | '' |
| `_wcefp_meeting_point_id` | Meeting point ID | Events | null |
| `_wcefp_ticket_types` | Ticket type configuration | Events | [] |
| `_wcefp_extras` | Extra services | Products | [] |

### Booking Meta Keys

| Meta Key | Usage | Context | Default |
|----------|-------|---------|---------|
| `_wcefp_booking_date` | Booking date/time | Bookings | null |
| `_wcefp_participants` | Number of participants | Bookings | 1 |
| `_wcefp_checkin_status` | Check-in status | Bookings | 'pending' |
| `_wcefp_voucher_code` | Associated voucher | Bookings | '' |
| `_wcefp_special_requests` | Special requests | Bookings | '' |

---

## 8. Feature Status Analysis

### ✅ Fully Implemented Features

**Booking Widget v2**
- Status: **COMPLETE** ✅
- Files: `includes/Frontend/BookingWidgetV2.php`
- Shortcode: `[wcefp_booking_widget_v2]`
- Features: Enhanced UI, better mobile support, improved accessibility

**Google Reviews Integration** 
- Status: **COMPLETE** ✅
- Files: `includes/Frontend/GoogleReviewsManager.php`
- Shortcode: `[wcefp_google_reviews_v2]`
- Features: Place ID integration, caching, rate limiting

**Trust Nudges System**
- Status: **COMPLETE** ✅  
- Files: `includes/Frontend/TrustNudgesManager.php`
- Shortcode: `[wcefp_trust_elements]`
- Features: Scarcity indicators, social proof, trust badges

**Digital Check-in**
- Status: **COMPLETE** ✅
- Files: `includes/BookingFeatures/DigitalCheckinManager.php`  
- Shortcode: `[wcefp_mobile_checkin]`
- Features: QR codes, mobile interface, status tracking

### 🔧 Partially Implemented Features

**Special Archive System**
- Status: **PARTIAL** 🔧
- Files: `includes/Frontend/WooCommerceArchiveFilter.php`
- Issue: Archive filtering exists but lacks dedicated landing pages
- Missing: Custom archive templates, SEO optimization

**Voucher System**
- Status: **PARTIAL** 🔧  
- Files: `includes/Features/Communication/VoucherManager.php`
- Implemented: Generation, redemption, status checking
- Missing: Advanced reporting, bulk operations

### ❌ Missing/Incomplete Features

**Unified Booking Shortcode**
- Status: **MISSING** ❌
- Current: Multiple booking shortcodes (`[wcefp_booking]`, `[wcefp_booking_widget_v2]`)
- Needed: Single "approved" shortcode with feature detection

**Advanced Analytics Dashboard**  
- Status: **INCOMPLETE** ❌
- Files: `includes/Analytics/AnalyticsDashboardManager.php` (basic)
- Missing: Real-time metrics, conversion tracking, revenue analytics

---

## 9. Implementation Gaps & Recommendations

### Priority 1: Critical Gaps

1. **Unified Booking Experience**
   - Consolidate booking widgets into single, feature-rich shortcode
   - Implement automatic fallback for older implementations
   - Add comprehensive parameter support

2. **Special Archive Completion**
   - Complete custom archive template system
   - Add SEO-optimized landing pages
   - Implement archive-specific shortcodes

3. **Enhanced Documentation**
   - Add inline code documentation
   - Create developer API documentation
   - Implement shortcode parameter validation

### Priority 2: Feature Enhancement

1. **Google Reviews Caching**
   - Implement intelligent cache refresh
   - Add backup data sources
   - Improve rate limit handling

2. **Trust Nudges Data Integration**
   - Connect to real booking data
   - Add configurable display rules  
   - Implement A/B testing capabilities

3. **Advanced Pricing System**
   - Complete dynamic pricing implementation
   - Add demand-based pricing
   - Implement group discount system

### Priority 3: Technical Improvements  

1. **Performance Optimization**
   - Implement shortcode output caching
   - Optimize database queries
   - Add asset loading optimization

2. **Security Enhancements**
   - Add shortcode parameter sanitization
   - Implement nonce validation for all AJAX
   - Add rate limiting to public endpoints

---

## 10. Developer Quick Reference

### Adding New Shortcodes

1. Register in appropriate manager class (e.g., `ShortcodeManager.php`)
2. Add to static scan regex patterns
3. Update this documentation
4. Add to runtime detection script

### Creating New AJAX Endpoints

1. Register with `add_action('wp_ajax_*')` and `wp_ajax_nopriv_*` 
2. Implement nonce validation
3. Add to endpoint inventory
4. Test with frontend components

### Extending Gutenberg Integration

1. Add block definition in `GutenbergManager.php`
2. Create block.json file (if needed)
3. Implement server-side render callback
4. Add to block inventory

---

## Conclusion

WCEventsFP demonstrates a comprehensive booking and events management system with extensive shortcode support, modern WordPress integration, and advanced booking features. The plugin architecture supports both legacy PHP templates and modern Gutenberg blocks, providing flexibility for different WordPress setups.

**Key Strengths:**
- Extensive shortcode library (39 total)
- Modern Gutenberg block support
- Comprehensive AJAX/REST API integration  
- Robust service-oriented architecture
- Advanced booking features (multi-event, digital check-in, vouchers)

**Areas for Improvement:**
- Consolidation of booking interfaces
- Completion of special archive system
- Enhanced caching and performance
- Improved documentation and developer experience

This inventory serves as the foundation for future development planning and feature gap analysis.

---

*Generated by WCEventsFP Static Analysis Tool - Version 2.2.0*