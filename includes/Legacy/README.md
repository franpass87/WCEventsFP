# Legacy Classes

This directory contains legacy class files that use the old WordPress coding style (class-wcefp-*).

These files are kept for backward compatibility but should be gradually refactored to use the new PSR-4 namespace structure.

## Migration Path

Old style: `class-wcefp-logger.php` → `class WCEFP_Logger`
New style: `Utils/Logger.php` → `namespace WCEFP\Utils; class Logger`

## Files

These files will be loaded automatically if needed for backward compatibility, but new development should use the namespace-based classes in the parent directories.