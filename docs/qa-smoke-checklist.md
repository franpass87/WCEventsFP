# WCEventsFP QA Smoke Test Checklist

## Overview
This checklist covers essential functionality that should be tested before any release. All items should pass in a clean WordPress installation with WooCommerce active.

**Test Environment:**
- WordPress: 6.5+ 
- WooCommerce: 8.0+
- PHP: 8.0+
- Browser: Chrome/Firefox/Safari latest versions

## Pre-Test Setup

### Prerequisites
- [ ] Fresh WordPress installation 
- [ ] WooCommerce plugin activated
- [ ] WCEventsFP plugin activated
- [ ] Sample test data available
- [ ] Admin user account ready
- [ ] Customer user account ready

### Initial Configuration
- [ ] Plugin activation successful (no fatal errors)
- [ ] Database tables created (`wcefp_occorrenze`, `wcefp_bookings`, `wcefp_vouchers`, `wcefp_closures`)
- [ ] Admin menu appears under WooCommerce
- [ ] No PHP errors in debug.log
- [ ] Default settings loaded correctly

## Core Plugin Functionality

### 1. Plugin Activation & Setup
- [ ] Plugin activates without errors
- [ ] Database tables are created
- [ ] Default options are set
- [ ] Admin notices (if any) display correctly  
- [ ] Plugin can be deactivated cleanly
- [ ] Plugin can be reactivated successfully

### 2. Admin Interface Navigation
- [ ] Main WCEventsFP menu appears
- [ ] Top-level menu redirects to Bookings
- [ ] Submenu items load correctly:
  - [ ] Prenotazioni (Bookings)
  - [ ] Voucher 
  - [ ] Chiusure (Closures)
  - [ ] Impostazioni (Settings)
  - [ ] Meeting Points (if enabled)
  - [ ] Extras (if enabled)
- [ ] No "empty page" or broken menu items

### 3. Settings Management
- [ ] Settings page loads without errors
- [ ] All settings sections display:
  - [ ] General Settings
  - [ ] Email & Notifications  
  - [ ] Feature Flags
  - [ ] Integrations
- [ ] Settings save correctly
- [ ] Form validation works (invalid inputs rejected)
- [ ] Settings persist after save and page reload
- [ ] No JavaScript errors in browser console

### 4. Event (Product) Management

#### Event Creation
- [ ] Can create new WooCommerce product
- [ ] "Enable as Event" checkbox appears in product edit
- [ ] Event-specific fields appear when enabled:
  - [ ] Capacity
  - [ ] Duration
  - [ ] Location
  - [ ] Meeting Point (if enabled)
  - [ ] Difficulty Level
  - [ ] Age Restrictions
- [ ] Event settings save correctly
- [ ] Published events appear in events list

#### Event Configuration
- [ ] Capacity validation works (minimum 1)
- [ ] Duration accepts valid time formats
- [ ] Location field accepts text input
- [ ] Meeting point dropdown populated (if enabled)
- [ ] Pricing fields work correctly
- [ ] Gallery images upload and display

### 5. Booking Management

#### Booking Creation
- [ ] Bookings page loads list table
- [ ] Create new booking form works
- [ ] Required fields validation:
  - [ ] Event selection
  - [ ] Customer email
  - [ ] Booking date
  - [ ] Number of participants
- [ ] Booking saves to database correctly
- [ ] Customer information captured properly
- [ ] Pricing calculated correctly
- [ ] Booking appears in admin list

#### Booking Management
- [ ] Booking list displays correctly
- [ ] Filtering works:
  - [ ] By status
  - [ ] By date range
  - [ ] By event
  - [ ] By customer
- [ ] Search functionality works
- [ ] Pagination works with large datasets
- [ ] Booking details view loads
- [ ] Booking status can be changed
- [ ] Booking can be cancelled
- [ ] Export CSV function works

#### Booking Views
- [ ] Lista (List) view displays all bookings
- [ ] Calendar view loads without errors
- [ ] Calendar shows bookings on correct dates
- [ ] Calendar navigation works (month/week/day)
- [ ] Clicking calendar events shows details

### 6. Voucher System

#### Voucher Management
- [ ] Vouchers page loads correctly
- [ ] If feature disabled: shows onboarding message
- [ ] If enabled: shows voucher list table
- [ ] Voucher columns display properly:
  - [ ] Code
  - [ ] Order
  - [ ] Recipient  
  - [ ] Status
  - [ ] Send Date
  - [ ] Actions
- [ ] Action buttons work:
  - [ ] Regenerate
  - [ ] Resend
  - [ ] Cancel
- [ ] Bulk actions functional
- [ ] Success/error notices appear

#### Voucher Operations
- [ ] New vouchers can be created
- [ ] Voucher codes generate correctly
- [ ] Email sending works (or shows appropriate message)
- [ ] Voucher status updates correctly
- [ ] Expired vouchers handled properly
- [ ] Statistics display accurately

### 7. Closures Management

#### Closure CRUD Operations
- [ ] Closures page loads correctly
- [ ] Add new closure form works
- [ ] Date range picker functions
- [ ] Product selection dropdown populated
- [ ] "Global closure" option works
- [ ] Notes field accepts input
- [ ] Closure saves to database
- [ ] Closure appears in active closures list

#### Closure Impact
- [ ] Closure affects booking availability
- [ ] Closed dates not bookable
- [ ] Calendar view reflects closures
- [ ] Product-specific closures work correctly
- [ ] Global closures affect all events
- [ ] Closure deletion works
- [ ] Cache invalidation after changes

### 8. Meeting Points (if enabled)

#### Meeting Point Management
- [ ] Meeting Points CPT exists
- [ ] Can create new meeting point
- [ ] Meta fields work:
  - [ ] Address
  - [ ] GPS Coordinates
  - [ ] Contact Information
- [ ] Meeting points appear in product dropdowns
- [ ] Frontend display works correctly

### 9. Extras Management (if enabled)

#### Extras Configuration
- [ ] Extras repeater interface works
- [ ] Can add/remove extra services
- [ ] Fields save correctly:
  - [ ] Name
  - [ ] Description
  - [ ] Price
  - [ ] Quantity limits
  - [ ] Required/optional flag
- [ ] Price calculation works
- [ ] Frontend display functional

### 10. Frontend Functionality

#### Shortcodes
- [ ] `[wcefp_events]` displays events grid
- [ ] `[wcefp_event]` shows single event
- [ ] `[wcefp_booking_form]` renders booking form
- [ ] `[wcefp_search]` shows search interface
- [ ] All shortcodes load required CSS/JS
- [ ] Responsive design works on mobile
- [ ] No JavaScript errors in console

#### User Experience
- [ ] Pages load quickly (<3 seconds)
- [ ] Forms submit without errors
- [ ] Success messages display clearly
- [ ] Error messages are helpful
- [ ] Navigation is intuitive
- [ ] Mobile experience is usable

### 11. REST API

#### API Endpoints
- [ ] `/wp-json/wcefp/v1/events` returns events
- [ ] `/wp-json/wcefp/v1/bookings` requires authentication
- [ ] System status endpoint works
- [ ] Export endpoints function correctly
- [ ] Error responses include proper status codes
- [ ] API documentation accessible

#### Authentication
- [ ] WordPress authentication works
- [ ] API key authentication works (if configured)
- [ ] Permission checks enforce correctly
- [ ] Unauthorized access blocked properly

### 12. Performance & Compatibility

#### Performance
- [ ] Plugin doesn't significantly impact site speed
- [ ] Database queries are optimized
- [ ] Large datasets handle gracefully
- [ ] Memory usage within reasonable limits
- [ ] No N+1 query issues

#### Browser Compatibility
- [ ] Chrome: All features work
- [ ] Firefox: All features work  
- [ ] Safari: All features work
- [ ] Edge: All features work
- [ ] Mobile browsers: Basic functionality works

#### WordPress Compatibility
- [ ] Compatible with current WP version
- [ ] Works with popular themes
- [ ] No conflicts with common plugins
- [ ] Multisite compatibility (if claimed)

### 13. Security

#### Data Protection
- [ ] SQL injection protection works
- [ ] XSS prevention in place
- [ ] CSRF protection via nonces
- [ ] User input sanitized properly
- [ ] File upload security (if applicable)
- [ ] Capability checks enforce correctly

#### Access Control
- [ ] Admin functions require proper permissions
- [ ] Customer data protected appropriately
- [ ] API endpoints secured properly
- [ ] No sensitive data exposed to frontend

### 14. Error Handling

#### Graceful Degradation
- [ ] Missing WooCommerce handled gracefully
- [ ] Database connection failures handled
- [ ] File permission issues reported clearly
- [ ] Invalid configuration doesn't break site
- [ ] JavaScript errors don't break functionality

#### User Feedback
- [ ] Success messages are clear
- [ ] Error messages are helpful
- [ ] Loading states shown appropriately
- [ ] Form validation provides guidance
- [ ] Debug information available (when enabled)

## Test Results

### Environment Details
- WordPress Version: ________________
- WooCommerce Version: _____________
- PHP Version: ____________________
- Browser: _______________________
- Test Date: _____________________
- Tester: _______________________

### Critical Issues Found
*List any issues that would block release:*

1. ________________________________
2. ________________________________
3. ________________________________

### Minor Issues Found
*List non-critical issues for future resolution:*

1. ________________________________
2. ________________________________
3. ________________________________

### Overall Assessment
- [ ] **PASS** - Ready for release
- [ ] **CONDITIONAL** - Can release with minor issues noted
- [ ] **FAIL** - Critical issues must be resolved before release

### Additional Notes
_Any other observations or recommendations:_

____________________________________________
____________________________________________
____________________________________________

---

**Checklist Completed By:** ________________  
**Date:** ________________  
**Signature:** ________________

## Automated Test Execution

For automated testing, run these commands:

```bash
# PHP Unit Tests
composer test

# JavaScript Tests  
npm run test:js

# Linting
composer lint
npm run lint:js
npm run lint:css

# Static Analysis
composer analyze

# Full Quality Check
composer quality
```

Expected Results:
- [ ] All PHPUnit tests pass
- [ ] All Jest tests pass
- [ ] No linting errors
- [ ] No critical static analysis issues
- [ ] Overall quality score > 90%