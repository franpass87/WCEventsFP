# WCEventsFP API Documentation

This document provides comprehensive API documentation for WCEventsFP plugin developers and integrators.

## Table of Contents

1. [Authentication](#authentication)
2. [Real-time API](#real-time-api)
3. [Booking API](#booking-api)
4. [Monitoring API](#monitoring-api)
5. [Security Features](#security-features)
6. [PHP Classes](#php-classes)
7. [JavaScript API](#javascript-api)
8. [Hooks & Filters](#hooks--filters)
9. [Database Schema](#database-schema)

## Authentication

All AJAX endpoints require proper WordPress nonces for security:

```php
wp_nonce_field('wcefp_admin', 'nonce');
```

JavaScript:
```javascript
$.post(ajaxurl, {
    action: 'wcefp_action_name',
    nonce: WCEFPAdmin.nonce,
    // ... other data
});
```

## Real-time API

### Establishing Connection

**Endpoint:** `wcefp_realtime_connect`

**Parameters:**
- `nonce` (string, required): WordPress nonce for security

**Response:**
```json
{
    "success": true,
    "data": {
        "session_id": "uuid-string",
        "message": "Real-time connection established"
    }
}
```

### Getting Updates

**Endpoint:** `wcefp_get_realtime_updates`

**Parameters:**
- `session_id` (string, required): Session ID from connection
- `nonce` (string, required): WordPress nonce

**Response:**
```json
{
    "success": true,
    "data": {
        "updates": [
            {
                "type": "booking_update",
                "booking_id": 123,
                "product_id": 456,
                "message": "New booking received",
                "timestamp": "2025-08-22 10:30:00"
            }
        ],
        "timestamp": "2025-08-22 10:30:00",
        "session_id": "uuid-string"
    }
}
```

### JavaScript Real-time Client

```javascript
// Initialize real-time client
const client = new WCEFPRealtimeClient();

// Listen for events
client.on('booking_update', function(data) {
    console.log('New booking:', data);
});

client.on('availability_update', function(data) {
    console.log('Availability changed:', data);
});

client.on('notification', function(notification) {
    console.log('Notification:', notification.message);
});
```

## Booking API

### Create Booking

**Endpoint:** `wcefp_book_slot`

**Parameters:**
- `product_id` (int, required): Product ID
- `occurrence_id` (int, required): Occurrence ID  
- `quantity` (int, required): Number of spots to book
- `customer_data` (array, optional): Customer information

**Response:**
```json
{
    "success": true,
    "data": {
        "booking_id": 123,
        "order_id": 456,
        "status": "confirmed"
    }
}
```

### Update Occurrence

**Endpoint:** `wcefp_update_occurrence`

**Parameters:**
- `occ` (int, required): Occurrence ID
- `capacity` (int, optional): New capacity
- `status` (string, optional): New status ('active', 'cancelled')

## Monitoring API

### Get Monitoring Data

**Endpoint:** `wcefp_get_monitoring_data`

**Response:**
```json
{
    "success": true,
    "data": {
        "monitoring_results": {
            "database_health": {
                "status": "healthy",
                "message": "Database is responding normally",
                "metrics": {
                    "query_time": 0.05,
                    "connection_count": 12
                }
            }
        },
        "last_check": "2025-08-22 10:30:00",
        "health_score": 95,
        "system_metrics": {
            "php_memory_usage": "128 MB",
            "php_memory_limit": "256M",
            "php_version": "8.1.0"
        }
    }
}
```

## Security Features

### Rate Limiting

The plugin automatically applies rate limiting to sensitive AJAX endpoints:

- Booking actions: 10 requests per 5 minutes
- Calendar requests: 30 requests per minute  
- Admin actions: 20 requests per 5 minutes

Rate limits are applied per IP address and user combination.

### Content Security Policy

CSP headers are automatically added to frontend pages:

```http
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://www.googletagmanager.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com
```

## PHP Classes

### WCEFP_Security_Enhancement

Provides security features like rate limiting and CSP headers.

**Methods:**
- `enforce_rate_limit()`: Apply rate limiting to current request
- `add_csp_headers()`: Add Content Security Policy headers
- `security_scan_request()`: Scan request for suspicious patterns

### WCEFP_Realtime_Features

Handles real-time communication and updates.

**Methods:**
- `handle_realtime_connect()`: Establish real-time connection
- `broadcast_booking_update($booking_id, $data)`: Broadcast booking updates
- `push_notification($message, $type, $targets)`: Send notifications

**Usage:**
```php
// Broadcast a booking update
$realtime = WCEFP_Realtime_Features::get_instance();
$realtime->broadcast_booking_update($booking_id, [
    'product_id' => 123,
    'status' => 'confirmed'
]);
```

### WCEFP_Advanced_Monitoring

Provides system monitoring and alerting.

**Methods:**
- `run_monitoring_checks()`: Execute all monitoring checks
- `send_alert($alert)`: Send alert notification
- `get_health_score()`: Calculate overall system health

**Usage:**
```php
// Get monitoring instance
$monitoring = WCEFP_Advanced_Monitoring::get_instance();

// Trigger manual health check
$monitoring->run_monitoring_checks();
```

### WCEFP_Logger

Enhanced logging system with multiple levels.

**Methods:**
- `error($message, $context)`: Log error message
- `warning($message, $context)`: Log warning message  
- `info($message, $context)`: Log info message
- `debug($message, $context)`: Log debug message

**Usage:**
```php
// Log with context
WCEFP_Logger::error('Payment failed', [
    'order_id' => 123,
    'error_code' => 'CARD_DECLINED',
    'user_id' => 456
]);
```

### WCEFP_Validator

Input validation and sanitization.

**Methods:**
- `validate_product_id($id)`: Validate product ID
- `validate_email($email)`: Validate email address
- `validate_capacity($capacity, $min, $max)`: Validate capacity value
- `validate_bulk($data, $rules)`: Bulk validation

**Usage:**
```php
// Validate multiple fields
$validated = WCEFP_Validator::validate_bulk($_POST, [
    'product_id' => ['method' => 'validate_product_id', 'required' => true],
    'email' => ['method' => 'validate_email', 'required' => false]
]);
```

### WCEFP_Cache

Intelligent caching system.

**Methods:**
- `get($key)`: Get cached value
- `set($key, $value, $expiration)`: Set cached value
- `delete($key)`: Delete cached value
- `clear_all()`: Clear all cache

**Usage:**
```php
// Cache expensive operation
$data = WCEFP_Cache::get('expensive_operation');
if ($data === false) {
    $data = perform_expensive_operation();
    WCEFP_Cache::set('expensive_operation', $data, 3600);
}
```

## JavaScript API

### WCEFPRealtimeClient

Real-time communication client.

**Events:**
- `connected`: Connection established
- `disconnected`: Connection lost
- `booking_update`: New booking received
- `availability_update`: Availability changed
- `notification`: New notification

**Methods:**
- `on(event, handler)`: Listen for events
- `off(event, handler)`: Remove event listener
- `isRealtimeConnected()`: Check connection status

### WCEFPNotifications

Advanced notification system.

**Methods:**
- `show(message, type, duration)`: Show notification
- `dismiss(id)`: Dismiss specific notification

**Usage:**
```javascript
// Show success notification
WCEFPNotifications.show('Booking confirmed!', 'success', 5000);

// Show error notification  
WCEFPNotifications.show('Booking failed', 'error', 8000);
```

## Hooks & Filters

### Action Hooks

**wcefp_booking_created**
Fired when a new booking is created.
```php
do_action('wcefp_booking_created', $booking_id, $booking_data);
```

**wcefp_occurrence_updated**
Fired when an occurrence is updated.
```php
do_action('wcefp_occurrence_updated', $occurrence_id, $updated_data);
```

**wcefp_monitoring_check**
Scheduled monitoring check.
```php
do_action('wcefp_monitoring_check');
```

### Filter Hooks

**wcefp_csp_policy**
Modify Content Security Policy.
```php
$csp = apply_filters('wcefp_csp_policy', $default_csp);
```

**wcefp_rate_limit_config**
Modify rate limiting configuration.
```php
$config = apply_filters('wcefp_rate_limit_config', $default_config);
```

**wcefp_realtime_poll_interval**
Modify real-time polling interval.
```php
$interval = apply_filters('wcefp_realtime_poll_interval', 5000);
```

## Database Schema

### wcefp_monitoring_alerts

Stores monitoring alerts and notifications.

```sql
CREATE TABLE wp_wcefp_monitoring_alerts (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    level varchar(20) NOT NULL,
    monitor varchar(50) NOT NULL,
    message text NOT NULL,
    data longtext,
    created_at datetime NOT NULL,
    resolved_at datetime NULL,
    status varchar(20) NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    KEY level (level),
    KEY monitor (monitor),
    KEY created_at (created_at)
);
```

### Existing Tables

The plugin also uses existing WCEventsFP tables:
- `wp_wcefp_occurrences`: Event occurrences
- `wp_wcefp_closures`: Extraordinary closures
- Additional tables for resources, channels, etc.

## Error Handling

All API endpoints follow consistent error response format:

```json
{
    "success": false,
    "data": {
        "msg": "Error message",
        "code": "ERROR_CODE", 
        "details": {}
    }
}
```

Common error codes:
- `INVALID_INPUT`: Input validation failed
- `PERMISSION_DENIED`: User lacks required permissions
- `RATE_LIMITED`: Too many requests
- `SESSION_EXPIRED`: Real-time session expired
- `INTERNAL_ERROR`: Server-side error occurred

## Examples

### Complete Booking Flow

```javascript
// 1. Initialize real-time connection
const rtClient = new WCEFPRealtimeClient();

// 2. Listen for availability updates
rtClient.on('availability_update', function(data) {
    if (data.available <= 0) {
        $('#book-button').prop('disabled', true);
    }
});

// 3. Make booking request
$('#book-button').on('click', function() {
    $.post(ajaxurl, {
        action: 'wcefp_book_slot',
        nonce: WCEFPAdmin.nonce,
        product_id: 123,
        occurrence_id: 456,
        quantity: 2
    })
    .done(function(response) {
        if (response.success) {
            WCEFPNotifications.show('Booking confirmed!', 'success');
        }
    })
    .fail(function() {
        WCEFPNotifications.show('Booking failed', 'error');
    });
});
```

This API documentation provides the foundation for extending and integrating with WCEventsFP. For more specific examples or advanced usage, refer to the source code and additional documentation files.