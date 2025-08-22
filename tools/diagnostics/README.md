# WCEventsFP Diagnostic Tools

This directory contains diagnostic and testing tools for WCEventsFP plugin.

## Tools Available

- `health-check.php` - Basic plugin health check
- `diagnostic-tool.php` - Advanced diagnostic information  
- `activation-test.php` - Test plugin activation safety
- `load-test.php` - Test plugin loading performance
- `server-monitor.php` - Server resource monitoring
- `installation-test.php` - Test installation process
- `test-plugin-loading.php` - Comprehensive loading tests

## Usage

These tools should only be used for debugging and development. Do not run them on production sites unless absolutely necessary.

To use any tool, run it from the WordPress root directory:

```bash
php wp-content/plugins/wceventsfp/tools/diagnostics/health-check.php
```

## Note

These tools have been moved from the plugin root directory to keep it clean and organized.