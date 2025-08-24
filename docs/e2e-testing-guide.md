# WCEventsFP End-to-End Testing Guide

## Overview

This guide covers end-to-end (E2E) testing for WCEventsFP using modern testing frameworks. E2E tests verify complete user workflows from browser interaction to database persistence.

## Setup

### Prerequisites
- Node.js 18+
- WordPress test environment
- WooCommerce activated
- WCEventsFP plugin activated

### Installation

```bash
# Install E2E testing dependencies
npm install --save-dev @playwright/test
npm install --save-dev @wordpress/e2e-test-utils

# Initialize Playwright
npx playwright install
```

### Configuration

Create `playwright.config.js`:

```javascript
module.exports = {
  testDir: './tests/e2e',
  timeout: 30000,
  retries: 1,
  use: {
    baseURL: process.env.WP_BASE_URL || 'http://localhost:8080',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure'
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] }
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] }
    }
  ]
};
```

## Test Examples

### 1. Plugin Activation Test

```javascript
// tests/e2e/plugin-activation.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Plugin Activation', () => {
  test('should activate WCEventsFP successfully', async ({ page }) => {
    // Login as admin
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');

    // Navigate to plugins
    await page.goto('/wp-admin/plugins.php');

    // Find and activate WCEventsFP if not already active
    const wcefpRow = page.locator('[data-plugin="wceventsfp/wceventsfp.php"]');
    
    if (await wcefpRow.locator('.deactivate').count() === 0) {
      await wcefpRow.locator('.activate a').click();
      
      // Wait for activation success
      await expect(page.locator('.notice-success')).toBeVisible();
    }

    // Verify plugin is active
    await expect(wcefpRow.locator('.deactivate')).toBeVisible();
  });

  test('should create admin menu after activation', async ({ page }) => {
    await page.goto('/wp-admin');
    
    // Check for WCEventsFP menu
    const wcefpMenu = page.locator('#menu-posts-wcefp, #toplevel_page_wcefp');
    await expect(wcefpMenu).toBeVisible();
    
    // Check submenu items
    await wcefpMenu.hover();
    await expect(page.locator('text=Prenotazioni')).toBeVisible();
    await expect(page.locator('text=Voucher')).toBeVisible();
    await expect(page.locator('text=Chiusure')).toBeVisible();
    await expect(page.locator('text=Impostazioni')).toBeVisible();
  });
});
```

### 2. Event Creation Test

```javascript
// tests/e2e/event-creation.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Event Management', () => {
  test('should create new event successfully', async ({ page }) => {
    // Login and navigate to products
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    
    await page.goto('/wp-admin/post-new.php?post_type=product');

    // Fill basic product info
    await page.fill('#title', 'E2E Test Wine Tasting');
    await page.fill('#content_ifr', 'A wonderful wine tasting experience for E2E testing');
    
    // Set product type to simple
    await page.selectOption('#product-type', 'simple');
    
    // Navigate to WCEFP settings tab
    await page.click('.wcefp_tab a, [href="#wcefp_product_data"]');
    
    // Enable as event
    await page.check('#_wcefp_is_event');
    
    // Fill event details
    await page.fill('#_wcefp_capacity', '12');
    await page.fill('#_wcefp_duration', '120');
    await page.fill('#_wcefp_location', 'Test Vineyard Estate');
    
    // Set price in General tab
    await page.click('.general_tab a');
    await page.fill('#_regular_price', '45.00');
    
    // Publish product
    await page.click('#publish');
    
    // Wait for success message
    await expect(page.locator('.notice-success')).toBeVisible();
    await expect(page.locator('text=Product published')).toBeVisible();
    
    // Verify event settings saved
    await page.click('.wcefp_tab a');
    await expect(page.locator('#_wcefp_is_event')).toBeChecked();
    await expect(page.locator('#_wcefp_capacity')).toHaveValue('12');
  });
});
```

### 3. Booking Creation Test

```javascript
// tests/e2e/booking-creation.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Booking Management', () => {
  let eventId;
  
  test.beforeEach(async ({ page }) => {
    // Create test event first
    await page.goto('/wp-admin');
    // ... login and create event logic
    // Store eventId for use in tests
  });

  test('should create booking from admin panel', async ({ page }) => {
    // Navigate to bookings page
    await page.goto('/wp-admin/admin.php?page=wcefp-bookings');
    
    // Click Add New Booking
    await page.click('text=Add New Booking, .page-title-action');
    
    // Fill booking form
    await page.selectOption('#event_id', eventId.toString());
    await page.fill('#customer_name', 'John Doe');
    await page.fill('#customer_email', 'john.doe@example.com');
    await page.fill('#customer_phone', '+1234567890');
    await page.fill('#booking_date', '2024-06-15');
    await page.fill('#booking_time', '10:00');
    await page.fill('#adults', '2');
    await page.fill('#children', '1');
    await page.fill('#special_requests', 'Vegetarian meals please');
    
    // Submit booking
    await page.click('#submit');
    
    // Verify booking created
    await expect(page.locator('.notice-success')).toBeVisible();
    await expect(page.locator('text=Booking created successfully')).toBeVisible();
    
    // Verify booking appears in list
    await page.goto('/wp-admin/admin.php?page=wcefp-bookings');
    await expect(page.locator('text=John Doe')).toBeVisible();
    await expect(page.locator('text=john.doe@example.com')).toBeVisible();
  });

  test('should validate booking form fields', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=wcefp-bookings&action=new');
    
    // Try to submit empty form
    await page.click('#submit');
    
    // Check validation messages
    const requiredFields = ['#customer_email', '#booking_date'];
    for (const field of requiredFields) {
      await expect(page.locator(`${field}:invalid`)).toBeVisible();
    }
    
    // Test email validation
    await page.fill('#customer_email', 'invalid-email');
    await page.click('#submit');
    await expect(page.locator('#customer_email:invalid')).toBeVisible();
    
    // Test date validation (past date)
    await page.fill('#booking_date', '2020-01-01');
    await page.blur('#booking_date');
    // Should show validation error or prevent submission
  });

  test('should update booking status', async ({ page }) => {
    // Create booking first
    // ... booking creation logic
    
    // Navigate to booking details
    await page.goto(`/wp-admin/admin.php?page=wcefp-bookings&action=view&id=${bookingId}`);
    
    // Change status
    await page.selectOption('#booking_status', 'confirmed');
    await page.click('#update_booking');
    
    // Verify status changed
    await expect(page.locator('.notice-success')).toBeVisible();
    await expect(page.locator('#booking_status')).toHaveValue('confirmed');
  });
});
```

### 4. Frontend Booking Test

```javascript
// tests/e2e/frontend-booking.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Frontend Booking Experience', () => {
  test('should complete booking flow from frontend', async ({ page }) => {
    // Navigate to events page (assuming shortcode on a page)
    await page.goto('/events');
    
    // Find and click on test event
    await page.click('text=E2E Test Wine Tasting');
    
    // Should see event details
    await expect(page.locator('h1, .event-title')).toContainText('Wine Tasting');
    await expect(page.locator('text=45.00, €45')).toBeVisible();
    
    // Find and click booking button
    await page.click('.booking-button, [data-action="book"], text=Book Now');
    
    // Fill booking form
    await page.fill('[name="customer_name"], #customer_name', 'Jane Smith');
    await page.fill('[name="customer_email"], #customer_email', 'jane@example.com');
    await page.fill('[name="customer_phone"], #customer_phone', '+0987654321');
    await page.selectOption('[name="participants"], #participants', '2');
    await page.fill('[name="special_requests"], #special_requests', 'Window table if possible');
    
    // Select date and time
    await page.click('[name="booking_date"], .date-picker');
    // Select future date from calendar widget
    await page.click('.available-date:not(.disabled):first-child');
    
    await page.selectOption('[name="booking_time"], #time_slot', '10:00:00');
    
    // Submit booking
    await page.click('#submit_booking, .submit-booking');
    
    // Verify success
    await expect(page.locator('.booking-success, .notice-success')).toBeVisible();
    await expect(page.locator('text=booking confirmed, reservation successful')).toBeVisible();
    
    // Should receive booking confirmation details
    await expect(page.locator('text=Booking Reference, Confirmation')).toBeVisible();
    await expect(page.locator('text=Jane Smith')).toBeVisible();
  });

  test('should handle booking conflicts', async ({ page }) => {
    // Assume event is at capacity
    await page.goto('/events/test-wine-tasting');
    
    // Try to book
    await page.click('.booking-button');
    
    // Should see fully booked message
    await expect(page.locator('.fully-booked, .sold-out')).toBeVisible();
    await expect(page.locator('text=fully booked, no availability')).toBeVisible();
  });

  test('should show real-time availability updates', async ({ page }) => {
    await page.goto('/events/test-wine-tasting');
    
    // Check initial availability
    const initialAvailable = await page.locator('.available-spots').textContent();
    
    // Complete a booking
    await page.click('.booking-button');
    // ... complete booking form and submit
    
    // Go back to event page
    await page.goto('/events/test-wine-tasting');
    
    // Verify availability decreased
    const updatedAvailable = await page.locator('.available-spots').textContent();
    expect(parseInt(updatedAvailable)).toBeLessThan(parseInt(initialAvailable));
  });
});
```

### 5. API Integration Test

```javascript
// tests/e2e/api-integration.spec.js
const { test, expect } = require('@playwright/test');

test.describe('REST API Integration', () => {
  test('should access API endpoints with authentication', async ({ page }) => {
    // Login to get authentication
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    
    // Get API response
    const response = await page.request.get('/wp-json/wcefp/v1/system/status', {
      headers: {
        'X-WP-Nonce': await page.evaluate(() => window.wpApiSettings?.nonce)
      }
    });
    
    expect(response.ok()).toBeTruthy();
    const data = await response.json();
    
    expect(data).toHaveProperty('plugin_version');
    expect(data).toHaveProperty('wordpress_version');
    expect(data).toHaveProperty('database');
  });

  test('should export data via API', async ({ page, request }) => {
    // Authenticate
    await page.goto('/wp-admin');
    // ... login logic
    
    // Request export
    const exportResponse = await request.get('/wp-json/wcefp/v1/export/bookings?format=csv', {
      headers: {
        'X-WP-Nonce': await page.evaluate(() => window.wpApiSettings?.nonce)
      }
    });
    
    expect(exportResponse.ok()).toBeTruthy();
    
    const exportData = await exportResponse.json();
    expect(exportData).toHaveProperty('filename');
    expect(exportData).toHaveProperty('content');
    expect(exportData.filename).toMatch(/\.csv$/);
  });
});
```

## Test Execution

### Local Development

```bash
# Run all E2E tests
npx playwright test

# Run specific test file
npx playwright test tests/e2e/booking-creation.spec.js

# Run tests in headed mode (visible browser)
npx playwright test --headed

# Run tests with debugging
npx playwright test --debug

# Generate test report
npx playwright show-report
```

### CI/CD Integration

```yaml
# .github/workflows/e2e-tests.yml
name: E2E Tests

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  e2e:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        ports:
          - 3306:3306

    steps:
    - uses: actions/checkout@v3
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        
    - name: Install dependencies
      run: npm ci
    
    - name: Setup WordPress
      run: |
        # Setup WordPress test environment
        # Install and configure WordPress
        # Activate WooCommerce and WCEventsFP
        
    - name: Install Playwright
      run: npx playwright install --with-deps
    
    - name: Run E2E tests
      run: npx playwright test
      env:
        WP_BASE_URL: http://localhost:8080
        
    - name: Upload test results
      uses: actions/upload-artifact@v3
      if: failure()
      with:
        name: playwright-report
        path: playwright-report/
```

## Test Data Management

### Setup Test Data

```javascript
// tests/e2e/helpers/test-data.js
class TestDataHelper {
  static async createTestEvent(page, eventData = {}) {
    const defaultEvent = {
      title: 'Test Event ' + Date.now(),
      capacity: 10,
      price: 25.00,
      duration: 120,
      location: 'Test Location'
    };
    
    const event = { ...defaultEvent, ...eventData };
    
    // Create event via admin interface
    await page.goto('/wp-admin/post-new.php?post_type=product');
    // ... event creation logic
    
    return event;
  }
  
  static async cleanupTestData(page) {
    // Remove test events, bookings, etc.
    await page.goto('/wp-admin/edit.php?post_type=product');
    // ... cleanup logic
  }
}

module.exports = TestDataHelper;
```

### Database Cleanup

```javascript
// tests/e2e/global-teardown.js
module.exports = async () => {
  // Clean up test database
  const mysql = require('mysql2/promise');
  
  const connection = await mysql.createConnection({
    host: 'localhost',
    user: 'root',
    password: 'root',
    database: 'wordpress_test'
  });
  
  // Clean up test data
  await connection.execute('DELETE FROM wp_posts WHERE post_title LIKE "Test Event %"');
  await connection.execute('DELETE FROM wp_wcefp_occorrenze WHERE nome LIKE "Test%"');
  
  await connection.end();
};
```

## Best Practices

### 1. Test Isolation
- Each test should be independent
- Use test-specific data
- Clean up after each test

### 2. Realistic User Flows
- Test complete user journeys
- Include error scenarios
- Test responsive design

### 3. Performance Testing
- Monitor page load times
- Test with realistic data volumes
- Verify API response times

### 4. Cross-Browser Testing
- Test on major browsers
- Include mobile viewports
- Test different screen sizes

### 5. Continuous Integration
- Run tests on every commit
- Parallelize test execution
- Generate comprehensive reports

## Debugging Failed Tests

### Screenshots and Videos
Playwright automatically captures screenshots on failures and can record videos:

```javascript
test('should create booking', async ({ page }) => {
  // Take screenshot at specific point
  await page.screenshot({ path: 'booking-form.png' });
  
  // Test logic here
});
```

### Network Monitoring
```javascript
test('should load without network errors', async ({ page }) => {
  const responses = [];
  
  page.on('response', response => {
    if (response.status() >= 400) {
      responses.push({
        url: response.url(),
        status: response.status()
      });
    }
  });
  
  await page.goto('/events');
  
  expect(responses).toHaveLength(0);
});
```

This comprehensive E2E testing approach ensures that WCEventsFP works correctly from a user's perspective across different browsers and scenarios.