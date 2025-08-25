# REST API Documentation - WCEventsFP

**Task ID:** T-04 (REST & AJAX Alignment)  
**Date:** 2024-08-25  
**Version:** 2.2.0 → Overhaul v1  
**Status:** 📋 **IN PROGRESS**

---

## Executive Summary

This document provides comprehensive documentation for WCEventsFP's REST API and AJAX endpoints, establishing standardized versioning, permissions, and response formats as part of the T-04 overhaul task.

## Current API Analysis

### **Version Distribution (BEFORE T-04)**
```bash
# Inconsistent API versioning found:
wcefp/v1: 13 endpoints
wcefp/v2: 13 endpoints
# Mix of versions across different managers
```

### **API Architecture Issues**
1. **Inconsistent Versioning**: Both v1 and v2 used without clear strategy
2. **Scattered Endpoints**: Multiple managers registering different versions  
3. **Permission Inconsistency**: Various capability checks across endpoints
4. **Mixed AJAX/REST**: Some functionality duplicated between AJAX and REST

## Standardized API Structure (T-04 Target)

### **Base URL Structure**
```
https://yoursite.com/wp-json/wcefp/v1/
https://yoursite.com/wp-json/wcefp/v2/
```

### **API Versioning Strategy**
- **v1**: Legacy/stable endpoints (maintain for backward compatibility)
- **v2**: Current/enhanced endpoints (default for new development)
- **Deprecation**: v1 endpoints will show deprecation headers but remain functional

### **Authentication & Permissions**

#### **Authentication Methods**
1. **WordPress Cookie Auth** (for admin/logged users)
2. **API Key Auth** (for external integrations)
3. **Nonce Verification** (for AJAX requests)

#### **Permission Levels**
| Capability | Purpose | WordPress Equivalent |
|------------|---------|---------------------|
| `view_wcefp_data` | Read public data | `read` |
| `manage_wcefp_bookings` | Manage bookings | `manage_woocommerce` |
| `manage_wcefp_events` | Manage events | `manage_woocommerce` |
| `manage_wcefp_settings` | Change settings | `manage_options` |

## REST API Endpoints

### **v2 Endpoints (Current/Recommended)**

#### **Events & Experiences**

##### `GET /wcefp/v2/experiences`
Get list of experiences with filtering and pagination.

**Parameters:**
- `page` (int): Page number (default: 1)
- `per_page` (int): Items per page (max: 100, default: 10)  
- `category` (string): Filter by category slug
- `location` (string): Filter by location
- `available_from` (string): Filter by availability date (YYYY-MM-DD)
- `search` (string): Text search in title/description
- `orderby` (string): Sort field (date, title, price, popularity)
- `order` (string): Sort direction (ASC, DESC)

**Response:**
```json
{
  "experiences": [
    {
      "id": 123,
      "title": "Wine Tasting Experience",
      "slug": "wine-tasting-experience",
      "description": "Discover local wines...",
      "excerpt": "Short description...",
      "price": "50.00",
      "currency": "EUR",
      "duration": "2 hours",
      "capacity": 20,
      "category": "food-and-drink",
      "location": {
        "name": "Tuscany Winery",
        "address": "123 Vineyard Lane, Tuscany",
        "coordinates": {
          "lat": 43.7696,
          "lng": 11.2558
        }
      },
      "images": [
        {
          "id": 456,
          "url": "https://example.com/image.jpg",
          "alt": "Wine tasting setup"
        }
      ],
      "availability": {
        "has_dates": true,
        "next_available": "2024-08-30",
        "total_slots": 15
      },
      "links": {
        "self": "/wp-json/wcefp/v2/experiences/123",
        "book": "/wp-json/wcefp/v2/experiences/123/book",
        "availability": "/wp-json/wcefp/v2/experiences/123/availability"
      }
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 10,
    "total": 45,
    "total_pages": 5,
    "links": {
      "next": "/wp-json/wcefp/v2/experiences?page=2",
      "prev": null
    }
  }
}
```

##### `GET /wcefp/v2/experiences/{id}`
Get single experience details.

**Permission:** Public  
**Response:** Single experience object (same structure as above)

##### `GET /wcefp/v2/experiences/{id}/availability`
Get availability calendar for an experience.

**Parameters:**
- `from` (string): Start date (YYYY-MM-DD)
- `to` (string): End date (YYYY-MM-DD, max 90 days from start)

**Response:**
```json
{
  "experience_id": 123,
  "availability": [
    {
      "date": "2024-08-30",
      "slots": [
        {
          "time": "10:00",
          "available": 15,
          "capacity": 20,
          "price": "50.00",
          "bookable": true
        },
        {
          "time": "14:00",
          "available": 8,
          "capacity": 20,
          "price": "50.00",
          "bookable": true
        }
      ]
    }
  ]
}
```

#### **Bookings**

##### `POST /wcefp/v2/experiences/{id}/book`
Create a new booking for an experience.

**Permission:** `manage_wcefp_bookings` or booking capability  
**Request Body:**
```json
{
  "date": "2024-08-30",
  "time": "10:00",
  "quantity": 2,
  "customer": {
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890"
  },
  "extras": [
    {
      "id": 789,
      "quantity": 2
    }
  ],
  "special_requirements": "Vegetarian options needed"
}
```

**Response:**
```json
{
  "booking_id": "wcefp_123456",
  "status": "confirmed",
  "experience": {
    "id": 123,
    "title": "Wine Tasting Experience"
  },
  "details": {
    "date": "2024-08-30",
    "time": "10:00",
    "quantity": 2,
    "total_price": "120.00",
    "currency": "EUR"
  },
  "customer": {
    "name": "John Doe",
    "email": "john@example.com"
  },
  "confirmation": {
    "code": "WCEFP-ABC123",
    "qr_code_url": "/wp-json/wcefp/v2/bookings/wcefp_123456/qr"
  },
  "links": {
    "self": "/wp-json/wcefp/v2/bookings/wcefp_123456",
    "cancel": "/wp-json/wcefp/v2/bookings/wcefp_123456/cancel",
    "ics": "/wp-json/wcefp/v2/bookings/wcefp_123456/ics"
  }
}
```

##### `GET /wcefp/v2/bookings`
List bookings with filtering.

**Permission:** `manage_wcefp_bookings`  
**Parameters:**
- `status` (string): Filter by status (confirmed, cancelled, completed)
- `customer_email` (string): Filter by customer email
- `experience_id` (int): Filter by experience
- `date_from`, `date_to` (string): Date range filter

##### `GET /wcefp/v2/bookings/{id}`
Get single booking details.

**Permission:** `manage_wcefp_bookings` or own booking

#### **System Endpoints**

##### `GET /wcefp/v2/system/status`
Get system health and status information.

**Permission:** `manage_wcefp_settings`  
**Response:**
```json
{
  "status": "healthy",
  "version": "2.2.0",
  "php_version": "8.3.6",
  "wordpress_version": "6.7.1",
  "woocommerce_version": "9.4.0",
  "database": {
    "status": "ok",
    "tables": ["wcefp_events", "wcefp_bookings"],
    "version": "8.0.35"
  },
  "checks": [
    {
      "name": "PHP Version",
      "status": "pass",
      "message": "PHP 8.3.6 (Required: 8.0+)"
    }
  ]
}
```

### **v1 Endpoints (Legacy/Deprecated)**

All v1 endpoints maintain backward compatibility but include deprecation headers:
```
X-WP-Deprecated: This API version is deprecated. Please use v2.
X-WP-Deprecated-New: /wp-json/wcefp/v2/endpoint
```

## AJAX Endpoints

### **Standardized AJAX Structure**

All AJAX actions follow the pattern: `wcefp_{action_name}`

#### **Frontend AJAX (Public + Authenticated)**

##### `wcefp_get_available_slots`
Get available time slots for a date.

**Action:** `wcefp_get_available_slots`  
**Method:** POST  
**Nonce:** `wcefp_frontend_nonce`  
**Data:**
```javascript
{
  action: 'wcefp_get_available_slots',
  experience_id: 123,
  date: '2024-08-30',
  _wpnonce: 'nonce_value'
}
```

##### `wcefp_calculate_booking_price`
Calculate total price for booking selection.

**Action:** `wcefp_calculate_booking_price`  
**Method:** POST  
**Nonce:** `wcefp_frontend_nonce`

#### **Admin AJAX (Authenticated Only)**

##### `wcefp_update_booking_status`
Update booking status from admin.

**Action:** `wcefp_update_booking_status`  
**Method:** POST  
**Capability:** `manage_wcefp_bookings`  
**Nonce:** `wcefp_admin_nonce`

## Error Handling

### **HTTP Status Codes**
- `200` - Success
- `201` - Created (for POST requests)
- `400` - Bad Request (validation errors)
- `401` - Unauthorized (authentication required)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `429` - Too Many Requests (rate limiting)
- `500` - Internal Server Error

### **Error Response Format**
```json
{
  "error": {
    "code": "invalid_experience_id",
    "message": "The specified experience ID does not exist.",
    "data": {
      "status": 404,
      "provided_id": 999
    }
  }
}
```

### **Common Error Codes**
| Code | HTTP | Description |
|------|------|-------------|
| `missing_required_param` | 400 | Required parameter missing |
| `invalid_permission` | 403 | Insufficient capabilities |
| `experience_not_found` | 404 | Experience doesn't exist |
| `booking_unavailable` | 409 | No availability for selected slot |
| `rate_limit_exceeded` | 429 | Too many requests |

## Rate Limiting

### **Limits by Endpoint Type**
- **Public endpoints**: 60 requests/minute per IP
- **Authenticated endpoints**: 300 requests/minute per user
- **Booking endpoints**: 10 bookings/minute per user

### **Rate Limit Headers**
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1693872000
```

## Implementation Status

### **T-04 Progress**
- [ ] **Phase 1**: Consolidate v1/v2 versioning strategy
- [ ] **Phase 2**: Standardize permission checks across all endpoints
- [ ] **Phase 3**: Implement consistent error handling
- [ ] **Phase 4**: Add rate limiting and security headers
- [ ] **Phase 5**: Update all AJAX endpoints to use standard format

---

*This API documentation will be updated as T-04 implementation progresses. All changes will maintain backward compatibility during the transition period.*