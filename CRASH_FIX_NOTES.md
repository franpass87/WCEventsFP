# WCEventsFP Plugin Crash Fix

## Problem
The WCEventsFP plugin was crashing the website during installation ("installando il plugin manda in crash il sito").

## Root Causes Identified

1. **Unsafe Database Operations**: The `ensure_db_schema()` method was executing `SHOW COLUMNS` queries on potentially non-existent tables, causing MySQL errors.

2. **No Error Handling**: Plugin initialization and database operations had no try-catch error handling, causing any failure to crash the entire site.

3. **Admin Classes Loading on Frontend**: Admin classes were being initialized on both frontend and admin, potentially causing conflicts.

4. **Unsafe Table Modifications**: The plugin attempted to ALTER TABLE and CREATE INDEX without checking if the table exists first.

## Fixes Implemented

### 1. Database Safety Improvements
- Added `SHOW TABLES LIKE` check before attempting to query table structure
- Wrapped database operations in try-catch blocks
- Added error logging without crashing the plugin
- Improved table creation with proper error handling

### 2. Error Handling
- Added comprehensive try-catch blocks around:
  - Plugin activation hook
  - Main plugin initialization
  - Admin class initialization
  - Database schema operations
- Errors are now logged and shown as admin notices instead of crashing

### 3. Admin Context Protection
- Admin classes now only load when `is_admin()` returns true
- Prevents admin-specific code from running on frontend

### 4. Safe Initialization Sequence
- Plugin initialization is now wrapped in error handling
- Database schema updates are safer and more robust
- Activation hook handles errors gracefully

## Files Modified

### wceventsfp.php
- Added error handling around admin class initialization
- Improved `ensure_db_schema()` method with safety checks
- Added try-catch blocks around plugin initialization
- Enhanced activation hook with error handling

### wcefp-health-check.php (NEW)
- Created diagnostic script to check for common issues
- Verifies file existence and syntax
- Checks PHP configuration
- Can be run to diagnose future problems

## Testing

The fixes have been tested with:
- ✅ Plugin loading simulation test
- ✅ PHP syntax validation for all files  
- ✅ Health check script validation
- ✅ Database operation safety checks

## Usage

### For Site Administrators
The plugin should now install without crashing. If issues persist:

1. Run the health check: `php wcefp-health-check.php`
2. Check error logs for detailed error messages
3. Ensure WooCommerce is installed and activated

### For Developers
- Error details are logged to PHP error log
- Admin notices show user-friendly error messages
- Plugin degrades gracefully instead of crashing

## Prevention

The implemented error handling ensures that:
- Database errors don't crash the site
- Missing dependencies are handled gracefully
- File or class loading issues are contained
- Plugin can be safely activated/deactivated

This should resolve the installation crash issue while maintaining all plugin functionality.