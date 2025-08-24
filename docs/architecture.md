# WCEventsFP - Architecture Documentation

> **Version**: 2.1.4+  
> **Last Updated**: August 24, 2024  
> **Compatibility**: PHP 8.0+, WordPress 6.5+, WooCommerce 7.0+

---

## 🏗️ System Architecture Overview

WCEventsFP follows a modern, modular architecture designed for enterprise-scale event booking and management. The plugin uses dependency injection, service providers, and a clean separation of concerns to ensure maintainability and extensibility.

```
┌─────────────────────────────────────────────────────────────┐
│                     WCEventsFP                               │
│                 Enterprise Booking Platform                 │
└─────────────────────────────────────────────────────────────┘
                              │
              ┌──────────────┬┴┬──────────────┐
              │              │ │              │
         ┌────▼───┐    ┌─────▼─▼─────┐    ┌───▼────┐
         │Frontend│    │   WordPress  │    │ Admin  │
         │ Layer  │    │   Core API   │    │ Layer  │
         └────┬───┘    └─────┬─┬─────┘    └───┬────┘
              │              │ │              │
              └──────────────┬─┴┬─────────────┘
                             │  │
                      ┌──────▼──▼──────┐
                      │   Plugin Core   │
                      │  (Bootstrap)    │
                      └──────┬──┬──────┘
                             │  │
              ┌──────────────┴──┴──────────────┐
              │                                │
        ┌─────▼─────┐                   ┌─────▼─────┐
        │ Features  │                   │   Data    │
        │ Layer     │                   │  Layer    │
        └───────────┘                   └───────────┘
```

## 🎯 Core Components

### 1. Bootstrap Layer (`includes/Bootstrap/`)

**Purpose**: Plugin initialization and lifecycle management

#### Main Classes:
- `WCEFP\Bootstrap\Plugin` - Primary plugin bootstrap
- `WCEFP\Core\Container` - Dependency injection container  
- `WCEFP\Core\ServiceProvider` - Service registration interface

**Initialization Flow**:
```php
WCEventsFP::instance()
    ├── Load dependencies (autoloader)
    ├── Check WordPress/PHP compatibility
    ├── Initialize Bootstrap\Plugin
    ├── Register service providers
    └── Load all features
```

### 2. Core Services (`includes/Core/`)

| Service | Purpose | Implementation |
|---------|---------|---------------|
| `Container` | Dependency injection | PSR-11 compatible container |
| `HookTimingManager` | WordPress hook management | Proper timing and priority |
| `ServiceProvider` | Feature registration | Modular service loading |
| `Logger` | Debug and error logging | Structured logging with levels |

### 3. Feature Modules (`includes/Features/`)

Each feature module is self-contained with its own service provider:

```
Features/
├── Analytics/                 # KPI tracking and reporting
├── ApiDeveloperExperience/    # Developer tools and docs
├── BookingFeatures/          # Core booking functionality
├── Communication/            # Email, SMS, notifications
├── DataIntegration/          # Calendar, export, Gutenberg
└── Wrappers.php             # Legacy compatibility
```

## 🔄 Data Flow Architecture

### Booking Flow
```
User Request → Frontend → WordPress → WooCommerce → WCEventsFP → Database
     ↓              ↓         ↓           ↓            ↓          ↓
Response ← Frontend ← REST API ← Hooks ← Services ← Data Layer ← Storage
```

### Event Management Flow  
```
Admin Action → Admin Panel → AJAX/REST → Feature Services → Database
      ↓            ↓             ↓            ↓              ↓
   Response ← Admin View ← JSON Response ← Business Logic ← Data Storage
```

## 🗄️ Database Schema

### Core Tables

#### `wp_wcefp_events`
```sql
CREATE TABLE wp_wcefp_events (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    post_id bigint(20) NOT NULL,              -- WooCommerce product ID
    event_date datetime DEFAULT NULL,          -- Event occurrence date
    capacity int(11) DEFAULT 0,               -- Maximum participants
    booked int(11) DEFAULT 0,                 -- Current bookings
    status varchar(20) DEFAULT 'active',      -- active|inactive|cancelled
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY post_id (post_id),
    KEY event_date (event_date),
    KEY status (status)
);
```

### Integration Tables
- Uses WooCommerce `posts`, `postmeta`, `wc_orders`, `wc_order_items`
- WordPress `users`, `usermeta` for customer data
- Custom meta fields for event-specific data

## 🔌 API Architecture

### REST API Structure (`includes/API/`)

```
/wp-json/wcefp/v1/
├── events/                    # Event management
│   ├── GET    /              # List events
│   ├── POST   /              # Create event
│   ├── GET    /{id}          # Get specific event
│   ├── PUT    /{id}          # Update event
│   └── DELETE /{id}          # Delete event
├── bookings/                  # Booking management
│   ├── GET    /              # List bookings
│   ├── POST   /              # Create booking
│   ├── GET    /{id}          # Get specific booking
│   └── PUT    /{id}/status   # Update booking status
├── analytics/                 # Analytics data
│   ├── GET    /kpis          # Key performance indicators
│   ├── GET    /revenue       # Revenue analytics
│   └── GET    /occupancy     # Occupancy rates
└── system/                    # System endpoints
    ├── GET    /health        # Health check
    └── GET    /info          # System information
```

### Authentication & Security
- WordPress nonce verification for admin requests
- API key authentication for external integrations
- Rate limiting via `WCEFP\Features\ApiDeveloperExperience\RateLimiter`
- Capability-based access control

## 🎨 Frontend Architecture

### Asset Management (`includes/Core/Assets/`)

**AssetManager Class**: Handles conditional loading of CSS and JavaScript
- Environment-aware loading (development vs production)
- Dependency management
- Version cache busting
- Minification support

### JavaScript Architecture
```
assets/js/
├── admin/                     # Admin interface scripts
│   ├── dashboard.js          # Dashboard widgets
│   ├── booking-management.js # Booking admin
│   └── settings.js           # Settings panels
├── frontend/                  # Public interface scripts
│   ├── booking-widget.js     # Booking forms
│   ├── calendar.js           # Event calendar
│   └── notifications.js      # User notifications
└── shared/                    # Common utilities
    ├── api-client.js         # REST API wrapper
    └── utils.js              # Utility functions
```

## 🧩 Service Provider Pattern

Each feature implements `WCEFP\Core\ServiceProvider`:

```php
abstract class ServiceProvider {
    abstract public function register(Container $container): void;
    abstract public function boot(): void;
    
    protected function bindSingleton(string $abstract, callable $concrete): void;
    protected function bind(string $abstract, callable $concrete): void;
}
```

**Example Implementation**:
```php
class BookingFeaturesServiceProvider extends ServiceProvider {
    public function register(Container $container): void {
        $this->bindSingleton(BookingManager::class, function() {
            return new BookingManager();
        });
    }
    
    public function boot(): void {
        add_action('init', [$this, 'init_booking_features']);
        add_action('wp_ajax_wcefp_create_booking', [$this, 'handle_booking_ajax']);
    }
}
```

## 🔐 Security Architecture

### Security Layers

1. **Input Validation**
   - Server-side validation for all user inputs
   - Type checking and sanitization
   - Business rule validation

2. **Authentication & Authorization**
   - WordPress capability system integration
   - Role-based access control (RBAC)
   - API key management for external access

3. **Data Protection**  
   - SQL injection prevention (prepared statements)
   - XSS protection (output escaping)
   - CSRF protection (nonce verification)

4. **API Security**
   - Rate limiting per endpoint
   - Request size limits
   - IP-based blocking capability

### Security Implementation Example
```php
// Secure AJAX handler pattern
public function handle_booking_ajax(): void {
    // Verify nonce
    if (!wp_verify_nonce($_POST['_wpnonce'], 'wcefp_booking_action')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    // Check capabilities  
    if (!current_user_can('manage_bookings')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Sanitize input
    $booking_data = [
        'event_id' => intval($_POST['event_id']),
        'participants' => intval($_POST['participants']),
        'customer_email' => sanitize_email($_POST['email'])
    ];
    
    // Process and return escaped output
    $result = $this->create_booking($booking_data);
    wp_send_json_success(esc_html($result));
}
```

## 📊 Performance Architecture

### Caching Strategy

1. **Object Cache**
   - WordPress object cache integration
   - Transient API for temporary data
   - External cache (Redis/Memcached) support

2. **Database Optimization**
   - Query caching for expensive operations
   - Index optimization for large datasets
   - Batch operations for bulk updates

3. **Asset Optimization**
   - Conditional loading based on page context
   - Minification and compression
   - CDN integration support

### Performance Monitoring
```php
class PerformanceMonitor {
    public static function startTimer(string $operation): void;
    public static function endTimer(string $operation): float;
    public static function logSlowQuery(string $sql, float $time): void;
    public static function getPerformanceReport(): array;
}
```

## 🔌 Integration Points

### WordPress Integrations
- **Hooks**: 150+ action and filter hooks
- **Post Types**: Extends WooCommerce products  
- **Taxonomies**: Custom event categories and tags
- **Admin Menus**: Native WordPress admin integration
- **Widgets**: Gutenberg blocks and legacy widgets

### WooCommerce Integrations
- **Product Types**: Event product type extension
- **Order Process**: Custom order item handling
- **Payment Gateways**: All WooCommerce gateways supported
- **Shipping**: Event-specific shipping rules
- **Taxes**: Location-based tax calculations

### Third-party Integrations
- **Analytics**: Google Analytics 4, Meta Pixel
- **Communication**: Brevo (Sendinblue), SMTP
- **Calendar**: iCal generation, Google Calendar
- **Payment**: Stripe, PayPal additional features

## 🔄 Event-Driven Architecture

### Core Events
```php
// Event creation
do_action('wcefp_event_created', $event_id, $event_data);
do_action('wcefp_occurrences_created', $product_id, $occurrence_count);

// Booking lifecycle  
do_action('wcefp_booking_created', $booking_id, $booking_data);
do_action('wcefp_booking_confirmed', $booking_id);
do_action('wcefp_booking_cancelled', $booking_id, $reason);

// Communication
do_action('wcefp_send_notification', $type, $recipient, $data);
do_action('wcefp_email_sent', $email_id, $status);

// Analytics
do_action('wcefp_track_event', $event_name, $properties);
do_action('wcefp_kpi_calculated', $kpi_type, $value, $period);
```

### Filter Hooks for Customization
```php  
// Data modification
$payload = apply_filters('wcefp_brevo_order_payload', $payload, $order);
$is_valid = apply_filters('wcefp_validate_voucher', true, $code, $voucher_data);

// UI customization
$booking_form = apply_filters('wcefp_booking_form_html', $html, $event_id);
$dashboard_widgets = apply_filters('wcefp_dashboard_widgets', $widgets);

// Business logic
$pricing = apply_filters('wcefp_calculate_pricing', $base_price, $modifiers);
$capacity = apply_filters('wcefp_event_capacity', $default_capacity, $event_id);
```

## 🚀 Deployment Architecture

### Development Environment
- Local WordPress/WooCommerce setup
- Docker support via wp-env
- Hot reloading for asset development
- Database seeding for testing

### Staging Environment  
- Production-like configuration
- Full integration testing
- Performance benchmarking
- Security scanning

### Production Environment
- Optimized autoloading
- Asset minification and compression
- Database optimization
- Monitoring and logging

### CI/CD Pipeline
```yaml
Build → Test → Security Scan → Performance Check → Deploy
  │       │           │              │             │
  ├── Syntax    ├── Unit Tests   ├── Lighthouse   ├── Blue/Green
  ├── PHPCS     ├── Integration  └── Load Test    └── Rollback
  └── Asset     └── E2E Tests                     
      Build
```

---

## 🔧 Configuration Management

### Environment Configuration
```php
// wp-config.php additions
define('WCEFP_DEBUG', true);
define('WCEFP_API_RATE_LIMIT', 1000);
define('WCEFP_CACHE_DURATION', 3600);
define('WCEFP_LOG_LEVEL', 'info');
```

### Feature Toggles
```php
// Plugin options
wcefp_get_option('enable_analytics', true);
wcefp_get_option('enable_email_automation', true);
wcefp_get_option('enable_api_access', false);
wcefp_get_option('performance_mode', 'balanced');
```

This architecture provides a solid foundation for enterprise-scale event management while maintaining WordPress best practices and ensuring scalability, security, and maintainability.