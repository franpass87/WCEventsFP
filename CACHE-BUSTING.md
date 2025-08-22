# WCEventsFP Cache Busting System

## Overview

The WCEventsFP plugin now includes an intelligent cache busting system that automatically handles cache invalidation during development while maintaining optimal performance in production.

## Problem Solved

Previously, when developers made changes to the plugin files, WordPress and the plugin's multi-tier caching system would continue serving cached versions of:
- CSS and JavaScript assets
- Plugin data and settings
- Performance optimizations
- Database query results

This made development frustrating as changes weren't immediately visible.

## Solution

The new `WCEFP_Cache_Manager` class provides:

### 1. Development Mode Detection
Automatically detects development environments based on:
- `WP_DEBUG` constant
- `SCRIPT_DEBUG` constant  
- `WCEFP_DEV_MODE` constant
- Local development domains (.local, localhost, .dev, .test)

### 2. Dynamic Version Generation
In development mode:
- Appends file modification timestamps to version numbers
- Monitors both main plugin file and includes directory
- Updates version when any PHP file is modified

Example:
- Production: `2.1.1`
- Development: `2.1.1.1755894843`

### 3. Automatic Cache Invalidation
- Detects when plugin files change
- Clears all caches when version changes
- Logs cache clearing activities

### 4. Manual Cache Control
- Admin bar "Clear WCEFP Cache" button
- AJAX endpoint for instant cache clearing
- Comprehensive cache clearing methods

## How It Works

### For Developers

1. **Enable Development Mode**:
   ```php
   define('WP_DEBUG', true);
   // or
   define('WCEFP_DEV_MODE', true);
   ```

2. **Automatic Cache Busting**:
   - CSS/JS files automatically get new version numbers when modified
   - Plugin data caches clear when code changes
   - No manual intervention required

3. **Manual Cache Clearing**:
   - Click "Clear WCEFP Cache" in admin bar
   - Or visit: `/wp-admin/admin-ajax.php?action=wcefp_clear_cache&nonce=...`

### For Production

- Static version numbers maintain optimal caching
- No performance impact from timestamp checking
- All caching benefits preserved

## Cache Types Cleared

1. **Memory Cache**: Runtime object cache
2. **Object Cache**: Redis/Memcached if available  
3. **Transient Cache**: WordPress database transients
4. **File Cache**: Custom file-based caches
5. **Performance Cache**: Multi-tier optimization caches

## API Reference

### WCEFP_Cache_Manager

```php
// Get instance
$cache_manager = WCEFP_Cache_Manager::get_instance();

// Check development mode
$is_dev = $cache_manager->is_development_mode();

// Get cache-busting version
$version = $cache_manager->get_cache_busting_version('2.1.1');

// Clear all caches
$success = $cache_manager->clear_all_caches();
```

### Plugin Version Methods

```php
// Get dynamic version (cache-busting in dev)
$version = WCEFP()->get_version();

// Force static version (for database storage)
$static = WCEFP()->get_version(true);
```

## Configuration

### Enable Development Mode

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WCEFP_DEV_MODE', true);
```

### Disable Admin Bar Button

```php
add_filter('wcefp_show_cache_clear_button', '__return_false');
```

## Testing

Run the included test:
```bash
php test-cache-busting.php
```

Expected output shows:
- Development mode detection: YES
- Version comparison: 2.1.1 vs 2.1.1.[timestamp]
- Cache busting: ACTIVE

## Benefits

1. **Faster Development**: Changes are immediately visible
2. **No Performance Impact**: Only active in development
3. **Automatic**: Works without developer intervention  
4. **Comprehensive**: Clears all cache types
5. **User-Friendly**: Simple admin bar integration

## Compatibility

- Works with all caching plugins
- Compatible with object caching (Redis/Memcached)
- Supports file-based caching
- No conflicts with WordPress caching

This system eliminates the common developer frustration of cached plugin files while maintaining production performance.