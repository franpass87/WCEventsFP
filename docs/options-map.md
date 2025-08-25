# Options Map - WCEventsFP Settings Canonical Reference

**Task ID:** T-01 (part of audit deliverables)  
**Date:** 2024-08-25  
**Version:** 2.2.0 → Overhaul v1  
**Status:** 📋 **IN PROGRESS**

---

## Executive Summary

This document provides a comprehensive mapping of all WordPress options, settings, and configuration points used by WCEventsFP. It serves as the canonical reference for avoiding duplications and ensuring consistent settings management across the overhaul (T-01 through T-11).

## WordPress Options Inventory

### **Core Plugin Settings** (`wp_options` table)

| Option Key | Type | Purpose | Autoload | Default Value | Location/Usage | Status |
|------------|------|---------|----------|---------------|---------------|---------|
| `wcefp_settings` | array | Main plugin configuration | yes | `[]` | Core settings page | ✅ Active |
| `wcefp_version` | string | Version tracking for migrations | yes | `'2.2.0'` | Bootstrap/Plugin.php:33 | ✅ Active |
| `wcefp_booking_settings` | array | Booking engine configuration | yes | `[]` | BookingFeaturesServiceProvider | ✅ Active |
| `wcefp_pricing_settings` | array | Pricing and discount rules | yes | `[]` | Advanced pricing features | ✅ Active |
| `wcefp_email_stats` | array | Email automation statistics | no | `[]` | EmailManager.php | ✅ Active |
| `wcefp_automation_settings` | array | Automation workflows config | yes | `[]` | AutomationManager.php | ✅ Active |
| `wcefp_developer_settings` | array | API and developer features | yes | `[]` | ApiDeveloperExperience | ✅ Active |

### **Specific Setting Keys** (within arrays above)

#### Core Settings (`wcefp_settings`)
```php
// Canonical structure for wcefp_settings option
$wcefp_settings = [
    // T-05: Experience Gating Settings  
    'woo_loop_gating' => [
        'hide_from_shop' => 'yes',           // Hide experiences from shop page
        'hide_from_category' => 'yes',       // Hide from category pages
        'hide_from_search' => 'yes',         // Hide from search results  
        'hide_from_related' => 'yes',        // Hide from related products
        'redirect_single_to_catalog' => 'no' // Redirect to custom catalog
    ],
    
    // T-09: Performance Settings
    'performance' => [
        'conditional_assets' => 'yes',        // Load assets only when needed
        'cache_enabled' => 'yes',            // Enable fragment caching
        'query_optimization' => 'yes',       // Optimize database queries
        'lazy_load_admin' => 'no'            // Lazy load admin data tables
    ],
    
    // T-06/T-07: Frontend Settings  
    'frontend' => [
        'catalog_page_id' => 0,              // Custom catalog page ID
        'catalog_per_page' => 12,            // Items per page in catalog
        'enable_filters' => 'yes',           // Enable category/date filters
        'enable_sorting' => 'yes',           // Enable price/date sorting
        'experience_layout' => 'card'        // Layout: 'card', 'list', 'grid'
    ],
    
    // T-08: Booking Engine Settings
    'booking' => [
        'enable_capacity_hold' => 'yes',     // Hold capacity during booking
        'hold_duration_minutes' => 15,       // How long to hold capacity
        'allow_overbooking' => 'no',         // Allow booking beyond capacity
        'require_phone' => 'no',             // Require phone number
        'enable_extras' => 'yes'             // Enable extra services
    ]
];
```

#### Booking Settings (`wcefp_booking_settings`)
```php
$wcefp_booking_settings = [
    'booking_flow_type' => 'standard',       // 'standard', 'express', 'multi_step'
    'max_events_per_booking' => 5,          // Maximum events in single booking
    'enable_digital_checkin' => 'yes',      // QR code check-in system
    'checkin_cutoff_hours' => 2,            // Hours before event for check-in
    'enable_resource_management' => 'yes',   // Guides, equipment, vehicles
    'auto_assign_resources' => 'no'         // Automatically assign available resources
];
```

## WordPress Transients (Cache Layer)

### **Performance & Optimization Transients**

| Transient Key | Purpose | Expiry | Used By | Notes |
|---------------|---------|--------|---------|-------|
| `wcefp_openapi_spec` | Cached API documentation | 1 hour | DocumentationManager | T-04: API docs caching |
| `wcefp_rate_limit_{ip}` | Rate limiting tracking | Dynamic | SecurityValidator | T-04: API security |
| `wcefp_automation_processed_{id}` | Duplicate prevention | 1 hour | AutomationManager | Prevent duplicate automations |
| `wcefp_catalog_query_{hash}` | Catalog query results | 15 minutes | Frontend catalog | T-06/T-09: Performance |
| `wcefp_experience_data_{id}` | Experience detail cache | 1 hour | Experience pages | T-07/T-09: Performance |

## Custom Post Meta (Product/Event Configuration)

### **WooCommerce Product Meta** (`postmeta` table)

| Meta Key | Purpose | Data Type | Used By | Example Value |
|----------|---------|-----------|---------|---------------|
| `_wcefp_is_experience` | Mark as experience product | boolean | T-05: Gating | `'yes'` |
| `_wcefp_hide_from_loop` | Override loop visibility | boolean | T-05: Gating | `'yes'` |
| `_wcefp_meeting_point` | Meeting point details | array | T-07: Experience page | `{name: "...", address: "..."}` |
| `_wcefp_duration` | Event duration | string | T-07: Experience page | `"2 hours"` |
| `_wcefp_capacity` | Maximum participants | integer | T-08: Booking engine | `20` |
| `_wcefp_extras_enabled` | Enable extra services | boolean | T-08: Booking engine | `'yes'` |
| `_wcefp_booking_window` | Advance booking window | string | T-08: Booking engine | `"7 days"` |

### **Meeting Points CPT Meta**

| Meta Key | Purpose | Data Type | Used By | Status |
|----------|---------|-----------|---------|---------|
| `_meeting_point_address` | Physical address | string | Legacy CPT | ✅ Keep |
| `_meeting_point_coordinates` | GPS coordinates | string | Maps integration | 🔄 Enhance for T-07 |
| `_meeting_point_google_place_id` | Google Place ID | string | T-07: Google integration | ❌ **MISSING** (add) |

## User Meta (Customer & Staff Settings)

### **Customer Booking Preferences**

| Meta Key | Purpose | Data Type | Notes |
|----------|---------|-----------|-------|
| `wcefp_preferred_language` | Customer language | string | For T-11: i18n |
| `wcefp_booking_history` | Booking count/stats | array | Analytics |
| `wcefp_notifications_opt_in` | Email preferences | boolean | Communication |

### **Staff/Admin Settings**

| Meta Key | Purpose | Data Type | Notes |
|----------|---------|-----------|-------|
| `wcefp_admin_role` | Custom admin capabilities | string | Security/roles |
| `wcefp_api_key` | Personal API access | string | T-04: API access |

## Database Tables Schema

### **Custom Tables** (plugin-specific data)

| Table Name | Purpose | Key Fields | Status | Tasks |
|------------|---------|------------|--------|-------|
| `{prefix}wcefp_events` | Event occurrences | `id`, `product_id`, `start_date`, `capacity`, `bookings_count` | ✅ Active | T-08: Enhance |
| `{prefix}wcefp_vouchers` | Voucher management | `id`, `code`, `status`, `order_id` | ✅ Active | Keep as-is |
| `{prefix}wcefp_voucher_usage` | Usage tracking | `id`, `voucher_code`, `used_date` | ✅ Active | Keep as-is |
| `{prefix}wcefp_booking_holds` | Capacity holds | `id`, `event_id`, `quantity`, `expires` | ❌ **MISSING** | T-08: Create |
| `{prefix}wcefp_resources` | Resources (guides/equipment) | `id`, `type`, `name`, `availability` | 🔄 Partial | T-08: Enhance |

## Settings Pages & Admin Interface

### **Admin Menu Structure** (canonical hierarchy)

```
WC Events FP (wcefp-events) [manage_woocommerce]
├── Dashboard (wcefp-dashboard) [manage_woocommerce] 
├── Esperienze (wcefp-experiences) [manage_woocommerce] ← T-06/T-07
├── Prenotazioni (wcefp-bookings) [manage_woocommerce] ← T-08  
├── Calendario (wcefp-calendar) [manage_woocommerce]
├── Voucher (wcefp-vouchers) [manage_woocommerce]
├── Impostazioni (wcefp-settings) [manage_woocommerce]
│   ├── Generali (general)
│   ├── Prenotazioni (booking) ← T-08 settings
│   ├── Frontend (frontend) ← T-06/T-07 settings  
│   ├── Performance (performance) ← T-09 settings
│   └── API (api) ← T-04 settings
└── Sistema (wcefp-system-status) [manage_options]
```

### **Settings Fields Mapping** (avoid duplications)

| Field Name | Page/Tab | Option Key | Purpose | Priority |
|------------|----------|------------|---------|----------|
| `hide_from_shop` | Frontend | `wcefp_settings[woo_loop_gating][hide_from_shop]` | T-05: Gating | High |
| `catalog_page_id` | Frontend | `wcefp_settings[frontend][catalog_page_id]` | T-06: Catalog | High |
| `conditional_assets` | Performance | `wcefp_settings[performance][conditional_assets]` | T-09: Assets | High |
| `api_version` | API | `wcefp_settings[api][default_version]` | T-04: Versioning | High |
| `booking_flow_type` | Booking | `wcefp_booking_settings[booking_flow_type]` | T-08: Flow | Medium |

## Shortcode Parameters (T-06/T-07 Frontend)

### **Catalog Shortcode** (`[wcefp_catalog]`)
```php
// Canonical parameter structure
$shortcode_defaults = [
    'per_page' => 12,
    'category' => '',
    'location' => '', 
    'sort' => 'date',      // date, price, popularity, title
    'layout' => 'grid',    // grid, list, cards
    'filters' => 'true',   // show filter controls
    'pagination' => 'true' // show pagination
];
```

### **Experience Detail** (`[wcefp_experience]`)  
```php
$shortcode_defaults = [
    'id' => 0,             // product/experience ID (required)
    'show_booking' => 'true', // show booking form
    'show_reviews' => 'true', // show reviews section
    'show_gallery' => 'true', // show image gallery
    'layout' => 'default'     // layout template
];
```

## REST API Settings (T-04)

### **API Versioning & Endpoints**

| Setting Key | Purpose | Default | Location |
|-------------|---------|---------|----------|
| `api_enabled` | Enable REST API | `true` | wcefp_settings |
| `api_default_version` | Default API version | `'v1'` | wcefp_settings |
| `api_rate_limit` | Requests per minute | `60` | wcefp_settings |
| `api_auth_required` | Require authentication | `false` | wcefp_settings |

### **API Namespace Structure**
```
wp-json/wcefp/v1/
├── experiences/           # GET, POST (T-06/T-07)
├── bookings/             # GET, POST, PUT (T-08) 
├── calendar/             # GET (availability data)
├── system/               # GET (status, health)
└── webhooks/             # POST (external integrations)
```

## Performance Settings (T-09)

### **Caching Configuration**

| Setting | Purpose | Default | Impact |
|---------|---------|---------|--------|
| `cache_experiences` | Cache experience queries | `true` | T-06/T-07 performance |
| `cache_calendar` | Cache availability data | `true` | T-08 performance |
| `cache_duration` | Cache expiry (minutes) | `15` | Balance freshness vs speed |
| `purge_on_booking` | Clear cache on new booking | `true` | Data consistency |

## Security & Permissions (T-04)

### **Capability Mapping**

| Capability | WordPress Equivalent | Purpose |
|------------|---------------------|---------|
| `manage_wcefp_events` | `manage_woocommerce` | Edit experiences |
| `view_wcefp_bookings` | `manage_woocommerce` | View booking data |  
| `manage_wcefp_settings` | `manage_options` | Change plugin settings |
| `access_wcefp_api` | `edit_posts` | API access rights |

## Migration & Compatibility

### **Version Migration Flags**

| Flag | Purpose | Action Needed |
|------|---------|---------------|
| `wcefp_v2_migration_done` | Track major migrations | Set to `true` after overhaul |
| `wcefp_gating_migrated` | T-05 gating applied | Track loop exclusion |
| `wcefp_assets_optimized` | T-09 optimization done | Track performance updates |

---

## **Actions Required (T-01 outcomes)**

### **Immediate Consolidation** ✅
1. **Single settings array** - Consolidate scattered options into `wcefp_settings`
2. **Remove duplications** - Eliminate redundant option keys
3. **Canonical naming** - Standardize option key conventions

### **Task-Specific Additions** 📋  
- **T-04:** Add API versioning settings
- **T-05:** Add WooCommerce gating controls  
- **T-06/T-07:** Add frontend/catalog settings
- **T-08:** Add booking engine configuration
- **T-09:** Add performance optimization flags

### **Rollback Strategy** 🔄
All setting changes tracked in git commits. Rollback via:
```bash
git checkout includes/settings/ admin/settings/
```

---

*This options map serves as the single source of truth for all WCEventsFP configuration throughout the overhaul process (T-01 through T-11).*