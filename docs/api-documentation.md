# WCEventsFP REST API Documentation

## Overview

WCEventsFP provides a comprehensive REST API for managing events, bookings, and integrations. The API follows WordPress REST API conventions and includes proper authentication, validation, and error handling.

**Base URL:** `[your-site.com]/wp-json/wcefp/v1`

## Authentication

The API supports multiple authentication methods:

1. **WordPress Authentication** - Standard WordPress user authentication
2. **API Key Authentication** - Pass `api_key` parameter in GET/POST requests
3. **Cookie Authentication** - For admin panel AJAX requests

### API Key Setup

```php
// Set API key in WordPress admin
update_option('wcefp_api_key', 'your-secure-api-key-here');
```

## Endpoints

### Bookings

#### GET /bookings

Retrieve a list of bookings with filtering and pagination.

**Parameters:**
- `page` (integer) - Current page (default: 1)
- `per_page` (integer) - Items per page (default: 20, max: 100)
- `status` (string) - Filter by booking status
- `event_id` (integer) - Filter by specific event
- `date_from` (date) - Start date filter (YYYY-MM-DD)
- `date_to` (date) - End date filter (YYYY-MM-DD)

**Response:**
```json
[
  {
    "id": 123,
    "product_id": 456,
    "customer_name": "John Doe",
    "customer_email": "john@example.com",
    "booking_date": "2024-03-15",
    "booking_time": "10:00:00",
    "participants": 2,
    "status": "confirmed",
    "total_price": 50.00,
    "created_at": "2024-03-01 09:30:00"
  }
]
```

#### GET /bookings/{id}

Get a single booking by ID.

#### POST /bookings

Create a new booking.

**Required Parameters:**
- `event_id` (integer) - ID of the event to book
- `customer_email` (string) - Customer email address
- `booking_date` (date) - Event date (YYYY-MM-DD)
- `participants` (integer) - Number of participants

**Optional Parameters:**
- `customer_name` (string) - Customer full name
- `customer_phone` (string) - Customer phone number
- `booking_time` (time) - Specific event time (HH:MM:SS)
- `special_requests` (string) - Special requests or notes

#### PUT /bookings/{id}

Update an existing booking.

#### DELETE /bookings/{id}

Cancel a booking (sets status to cancelled).

### Events

#### GET /events

Retrieve a list of events.

**Parameters:**
- `page` (integer) - Current page (default: 1)
- `per_page` (integer) - Items per page (default: 20, max: 100)
- `category` (string) - Filter by event category
- `search` (string) - Search in event title/content
- `date_from` (date) - Filter events available from date
- `orderby` (string) - Sort by field (default: date)
- `order` (string) - Sort order: ASC or DESC (default: DESC)

**Response:**
```json
[
  {
    "id": 456,
    "title": "Wine Tasting Experience",
    "slug": "wine-tasting-experience",
    "status": "publish",
    "featured_image": "https://example.com/wine-image.jpg",
    "price": 25.00,
    "categories": ["Wine Tours", "Food & Drink"],
    "event_meta": {
      "capacity": 12,
      "duration": 120,
      "location": "Vineyard Estate",
      "meeting_point": "Main Entrance"
    }
  }
]
```

#### GET /events/{id}

Get detailed information about a single event.

#### GET /events/{id}/occurrences

Get available dates and booking information for a specific event.

**Parameters:**
- `date_from` (date) - Start date (default: today)
- `date_to` (date) - End date
- `include_bookings` (boolean) - Include individual booking details (default: false)
- `page` (integer) - Current page
- `per_page` (integer) - Items per page (default: 50, max: 100)

**Response:**
```json
[
  {
    "date": "2024-03-15",
    "time": "10:00:00",
    "datetime": "2024-03-15 10:00:00",
    "bookings_count": 8,
    "active_bookings": 8,
    "total_participants": 15,
    "capacity": 20,
    "available_spots": 5,
    "is_full": false,
    "bookings": [] // Included if include_bookings=true
  }
]
```

### Export

#### GET /export/bookings

Export bookings data in CSV or JSON format.

**Parameters:**
- `format` (string) - Export format: 'csv' or 'json' (default: csv)
- `date_from` (date) - Start date filter
- `date_to` (date) - End date filter
- `status` (string) - Filter by booking status
- `event_id` (integer) - Filter by specific event

**Response:**
```json
{
  "filename": "wcefp-bookings-2024-03-01-14-30-00.csv",
  "content_type": "text/csv",
  "content": "[base64-encoded-content]",
  "size": 2048,
  "count": 25
}
```

#### GET /export/calendar

Export calendar data in ICS or JSON format.

**Parameters:**
- `format` (string) - Export format: 'ics' or 'json' (default: ics)
- `event_id` (integer) - Export specific event only
- `date_from` (date) - Start date (default: today)
- `date_to` (date) - End date (default: +6 months)

### System

#### GET /system/status

Get system information and plugin status.

**Response:**
```json
{
  "plugin_version": "2.1.4",
  "wordpress_version": "6.4.0",
  "php_version": "8.1.0",
  "database": {
    "bookings_count": 150,
    "events_count": 25,
    "tables_exist": true
  },
  "dependencies": {
    "woocommerce_active": true,
    "woocommerce_version": "8.5.0"
  }
}
```

#### GET /system/health

Perform health checks on the system.

**Response:**
```json
{
  "overall_status": "good",
  "checks": {
    "database": {"status": "good", "message": "All tables exist"},
    "file_permissions": {"status": "good", "message": "Permissions correct"},
    "memory_usage": {"status": "good", "message": "Memory usage: 45% of 512M"},
    "external_connections": {"status": "good", "message": "3 of 3 connections successful"},
    "cron_jobs": {"status": "good", "message": "5 WCEFP scheduled events"}
  },
  "checked_at": "2024-03-01T14:30:00+00:00"
}
```

### Integrations

#### POST /integrations/test/{service}

Test integration with external services.

**Supported Services:**
- `brevo` - Brevo email service
- `google-analytics` - Google Analytics integration
- `google-reviews` - Google Reviews integration
- `meta-pixel` - Meta Pixel integration

### Webhooks

#### POST /webhooks/booking-created

Public webhook endpoint for external booking notifications.

**Required Parameters:**
- `secret` (string) - Webhook verification secret

## Error Handling

The API uses standard HTTP status codes and returns errors in this format:

```json
{
  "code": "booking_not_found",
  "message": "Booking not found",
  "data": {
    "status": 404
  }
}
```

### Common Error Codes

- `400` - Bad Request (missing/invalid parameters)
- `401` - Unauthorized (invalid API key/permissions)
- `404` - Not Found (resource doesn't exist)
- `500` - Internal Server Error

## Rate Limiting

API requests are rate-limited to prevent abuse:
- **Authenticated requests:** 1000 requests per hour
- **Unauthenticated requests:** 100 requests per hour

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 995
X-RateLimit-Reset: 1709298000
```

## Pagination

List endpoints support pagination with these headers:

```
X-WP-Total: 150
X-WP-TotalPages: 8
```

## Custom Fields

The API extends WooCommerce REST API endpoints with WCEFP-specific fields:

### Products (Events)
- `wcefp_event_data` - Event-specific metadata

### Orders (Bookings)
- `wcefp_booking_data` - Booking-specific metadata

## Examples

### Create a Booking
```bash
curl -X POST https://yoursite.com/wp-json/wcefp/v1/bookings \
  -H "Content-Type: application/json" \
  -d '{
    "event_id": 456,
    "customer_email": "john@example.com",
    "customer_name": "John Doe",
    "booking_date": "2024-03-15",
    "participants": 2,
    "special_requests": "Vegetarian meal please"
  }' \
  --user admin:your-app-password
```

### Get Event Occurrences
```bash
curl "https://yoursite.com/wp-json/wcefp/v1/events/456/occurrences?date_from=2024-03-01&include_bookings=true" \
  --user admin:your-app-password
```

### Export Bookings CSV
```bash
curl "https://yoursite.com/wp-json/wcefp/v1/export/bookings?format=csv&date_from=2024-03-01" \
  --user admin:your-app-password
```

### Export Calendar ICS
```bash
curl "https://yoursite.com/wp-json/wcefp/v1/export/calendar?format=ics&event_id=456" \
  --user admin:your-app-password
```

## WordPress Integration

The API integrates seamlessly with WordPress:

- Uses WordPress nonce verification for admin requests
- Respects WordPress user capabilities
- Follows WordPress coding standards
- Supports WordPress REST API discovery
- Compatible with WordPress multisite

## Development & Testing

### Enable API Debugging
```php
// In wp-config.php
define('WCEFP_API_DEBUG', true);
```

### Test Endpoints
Use WP-CLI or browser developer tools to test API endpoints:

```bash
wp rest <endpoint> --user=admin
```

## Changelog

### Version 2.1.4
- Added event occurrences endpoint
- Enhanced export functionality with CSV and ICS support
- Added comprehensive system health checks
- Improved error handling and logging
- Added rate limiting and authentication options

### Version 2.1.0
- Initial REST API implementation
- Basic CRUD operations for bookings and events
- System status and health endpoints
- Integration testing capabilities