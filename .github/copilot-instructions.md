# GitHub Copilot Instructions for WCEventsFP

## ⚠️ CRITICAL: READ THESE INSTRUCTIONS FIRST ⚠️

**ALWAYS follow these instructions before making ANY changes to this repository.** These instructions are mandatory and contain critical information about build timing, dependencies, and common issues.

## Repository Overview

WCEventsFP is an enterprise WordPress/WooCommerce booking platform plugin written primarily in PHP 7.4+ with JavaScript frontend components. This is a production WordPress plugin with complex build requirements.

### Key Technologies
- **Backend**: PHP 7.4+, WordPress/WooCommerce APIs
- **Frontend**: Vanilla JavaScript, CSS, webpack (build system has configuration issues)
- **Testing**: PHPUnit (PHP), Jest (JavaScript)
- **Linting**: PHP_CodeSniffer, ESLint (configuration missing), Stylelint
- **Build**: Composer (PHP), npm (JavaScript), webpack (currently broken)

## 🔧 Build System & Dependencies

### PHP Dependencies (Composer)
**⚠️ CRITICAL AUTH ISSUE**: Composer installation fails due to GitHub API rate limiting.

```bash
# Install PHP dependencies (EXPECT FAILURES due to GitHub API limits)
composer install --no-dev --optimize-autoloader  # Production
composer install                                  # Development (includes PHPUnit, PHPCS)

# Available Composer scripts:
composer lint        # PHP_CodeSniffer (requires full composer install)
composer lint:fix    # PHP Code Beautifier
composer analyze     # PHPStan static analysis (requires full composer install)
composer test        # PHPUnit tests (requires full composer install)
composer quality     # Run lint + analyze + test (requires full composer install)
```

**TIMING**: Composer install attempts take ~300 seconds (5 min) but FAIL due to authentication.

### JavaScript Dependencies (npm)

```bash
# Install Node.js dependencies (WORKING - requires --legacy-peer-deps or --force)
npm install --legacy-peer-deps  # REQUIRED due to deprecated WordPress configs
# OR
npm install --force

# Available npm scripts:
npm run build        # webpack production build (CURRENTLY BROKEN - no webpack config)
npm run dev          # webpack development + watch (CURRENTLY BROKEN)
npm run lint:js      # ESLint (FAILS - no .eslintrc config file)
npm run lint:css     # Stylelint (untested due to deprecated configs)
npm run format       # Prettier (untested)
npm run test:js      # Jest tests (WORKING - ~1.6s)
npm run analyze      # webpack-bundle-analyzer (BROKEN - no build output)
```

**TIMING**:
- `npm install --legacy-peer-deps`: ~54 seconds (1 min) - WORKING ✅
- `npm install --force`: ~4 seconds (fast) - WORKING ✅
- `npm run test:js`: ~1.6 seconds - WORKING ✅
- `npm run build`: FAILS immediately (no webpack config)

## ⏰ Build Timing & Timeouts

When running build commands, use these timeout values:

```bash
# NEVER CANCEL these commands - they require long timeouts:
composer install    # 300+ seconds (5+ min) - EXPECT AUTH FAILURES
npm install         # 60+ seconds (1+ min)
npm run test:js     # 10 seconds
php syntax checks   # <5 seconds per file
```

## 📁 Repository Structure

```
WCEventsFP/
├── .github/
│   ├── workflows/build-release.yml    # GitHub Actions (WordPress plugin packaging)
│   └── copilot-instructions.md        # This file
├── admin/                             # WordPress admin interface PHP files
├── assets/
│   ├── js/                           # JavaScript files (no build process currently)
│   └── css/                          # CSS files (no build process currently)  
├── includes/                         # PHP classes and utilities (PSR-4: WCEFP\)
├── tests/
│   ├── js/                          # Jest tests (WORKING)
│   └── unit/                        # PHPUnit tests (untested due to composer issues)
├── vendor/                          # Composer dependencies (incomplete)
├── node_modules/                    # npm dependencies (after install)
├── wceventsfp.php                   # Main plugin file (WordPress plugin header)
├── composer.json                    # PHP dependencies and scripts
├── package.json                     # Node.js dependencies and scripts
├── phpunit.xml                      # PHPUnit configuration
├── phpcs.xml                        # PHP_CodeSniffer rules
└── phpstan.neon                     # PHPStan static analysis config
```

## 🧪 Testing & Quality Assurance

### PHP Testing
```bash
# Basic syntax check (WORKING)
find . -name "*.php" -path "./includes/*" -exec php -l {} \;

# PHPUnit tests (UNTESTED due to composer dependency issues)
composer test
# OR manually: vendor/bin/phpunit --configuration=phpunit.xml

# PHP linting (FAILS due to missing vendor/bin/phpcs)
composer lint

# Static analysis (FAILS due to missing vendor/bin/phpstan)
composer analyze
```

### JavaScript Testing
```bash
# Jest tests (WORKING - passes 4/5 tests, 1 skipped)
npm run test:js  # ~1.6 seconds

# ESLint (FAILS - missing .eslintrc config)
npm run lint:js
```

### Manual Plugin Testing

Since automated builds are partially broken, always verify:

1. **Plugin Loading**: Check `wceventsfp.php` loads without PHP errors
2. **WordPress Integration**: Verify plugin activates in WordPress admin
3. **WooCommerce Integration**: Check booking functionality works
4. **Diagnostic Tools**: Test health check scripts:
   - `wcefp-health-check.php`
   - `wcefp-diagnostic-tool.php`
   - `wcefp-activation-diagnostic.php`

## 🚨 Known Issues & Limitations

### Critical Build Issues
1. **Composer Authentication**: GitHub API rate limiting prevents full dependency installation
2. **Missing webpack.config.js**: No webpack configuration file exists, breaking `npm run build`
3. **Missing ESLint config**: No `.eslintrc.*` file exists, breaking `npm run lint:js`
4. **Deprecated Dependencies**: WordPress linting configs are deprecated (warnings on install)

### Dependency Conflicts
- **Stylelint**: Version conflicts between stylelint@15.x and stylelint-config-wordpress@17.x
- **ESLint**: Uses deprecated eslint-config-wordpress@2.x (should upgrade to @wordpress/scripts)
- **webpack**: Dependencies installed but no configuration file

## 🛠️ Common Troubleshooting

### Dependency Installation Failures
```bash
# If npm install fails with ERESOLVE errors:
npm install --legacy-peer-deps
# OR
npm install --force

# If composer fails with auth errors:
# This is expected due to GitHub API limits
# Work with existing vendor/ directory or skip composer-dependent tasks
```

### Build Command Failures
```bash
# If webpack build fails:
# Expected - no webpack.config.js exists
# Manual workaround: copy/concatenate files from assets/ to production directory

# If PHP linting fails:
# Use basic PHP syntax checking instead:
find . -name "*.php" -exec php -l {} \;

# If ESLint fails:
# Expected - no .eslintrc config exists
# Manual review of JavaScript files in assets/js/
```

### Testing Failures
```bash
# If PHPUnit fails:
# Likely due to incomplete composer install
# Use basic PHP syntax checks instead

# If Jest tests fail:
# Should work - investigate specific test failures
# Tests located in tests/js/
```

## 🎯 Development Workflow

### For Code Changes
1. **ALWAYS** run dependency installs first (expect composer failures)
2. Test basic PHP syntax before complex linting
3. Run Jest tests (these work)
4. Manual plugin functionality testing is REQUIRED
5. Use GitHub Actions build-release.yml as reference for packaging

### For New Features
1. Check existing similar functionality in `includes/` directory
2. Follow WordPress plugin coding standards manually (linting may not work)
3. Add Jest tests in `tests/js/` (this works)
4. Update relevant diagnostic tools if needed

### For Debugging
1. Use diagnostic tools: `wcefp-health-check.php`, `wcefp-diagnostic-tool.php`
2. Check WordPress error logs
3. Basic PHP syntax checking with `php -l`
4. Manual browser testing (automated builds unreliable)

## 📚 Key Files for Copilot Context

- `wceventsfp.php`: Main plugin file, WordPress headers
- `includes/class-*.php`: Main plugin classes
- `admin/`: WordPress admin interface
- `assets/js/`: Frontend JavaScript (manual review needed)
- `tests/js/`: Jest tests (reliable testing)
- `composer.json`, `package.json`: Dependency definitions
- `DEVELOPMENT.md`, `README.md`: Additional context
- `CHANGELOG.md`: Version history

## 🔄 CI/CD & Deployment

- GitHub Actions: `.github/workflows/build-release.yml` (WordPress plugin ZIP packaging)
- Manual testing REQUIRED due to unreliable automated builds
- Plugin packaging excludes dev files, node_modules, vendor, tests

---

**Remember**: This is a production WordPress plugin. Build system issues are known and documented. Focus on manual testing and code quality over automated builds until dependency issues are resolved.