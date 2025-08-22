/**
 * Jest setup file for WCEventsFP JavaScript tests
 */

// Mock jQuery globally
global.$ = global.jQuery = require('jquery');

// Mock WordPress admin globals
global.ajaxurl = '/wp-admin/admin-ajax.php';

// Mock browser APIs that might not be available in test environment
global.IntersectionObserver = class IntersectionObserver {
    constructor() {}
    observe() {}
    unobserve() {}
    disconnect() {}
};

// Mock performance API
global.performance = {
    now: () => Date.now(),
    getEntriesByType: () => []
};

// Mock console methods for tests
global.console.warn = jest.fn();
global.console.error = jest.fn();