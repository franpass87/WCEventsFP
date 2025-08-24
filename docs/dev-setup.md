# WCEventsFP - Development Setup Guide

> **Target Audience**: Developers contributing to WCEventsFP  
> **Prerequisites**: PHP 8.0+, Node.js 18+, Composer, Docker (optional)  
> **Setup Time**: ~30 minutes

---

## 🚀 Quick Start

```bash
# 1. Clone the repository
git clone https://github.com/franpass87/WCEventsFP.git
cd WCEventsFP

# 2. Install dependencies
composer install
npm install --legacy-peer-deps

# 3. Start development environment
npm run dev

# 4. Run tests
npm run test:js
composer run test
```

---

## 📋 Prerequisites

### Required Software
- **PHP**: 8.0+ (8.2+ recommended)
- **Node.js**: 18+ (20+ recommended)  
- **Composer**: Latest stable
- **WordPress**: 6.5+ for local development
- **WooCommerce**: 7.0+ plugin

### Optional Tools
- **Docker**: For containerized WordPress environment
- **WP-CLI**: Command-line WordPress management
- **Git**: Version control (obviously!)
- **VS Code**: With recommended extensions

### Recommended VS Code Extensions
```json
{
  "recommendations": [
    "bmewburn.vscode-intelephense-client",
    "bradlc.vscode-tailwindcss",
    "esbenp.prettier-vscode",
    "ms-vscode.vscode-typescript-next",
    "phpstan.vscode-phpstan",
    "wordpresstoolbox.wordpress-toolbox"
  ]
}
```

---

## 🏗️ Environment Setup

### Option 1: wp-env (Recommended)
Fast, Docker-based WordPress environment:

```bash
# Install wp-env globally
npm install -g @wordpress/env

# Create .wp-env.json configuration
cat > .wp-env.json << 'EOF'
{
  "core": "WordPress/WordPress#6.6",
  "plugins": [
    "https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip",
    "."
  ],
  "themes": [
    "https://downloads.wordpress.org/theme/twentytwentyfour.zip"
  ],
  "port": 8888,
  "env": {
    "development": {
      "WP_DEBUG": true,
      "WP_DEBUG_LOG": true,
      "WP_DEBUG_DISPLAY": true,
      "SCRIPT_DEBUG": true
    }
  }
}
EOF

# Start the environment
wp-env start

# Access your site
open http://localhost:8888
# Admin: http://localhost:8888/wp-admin (admin/password)
```

### Option 2: Local WordPress Installation

#### Using Local by Flywheel / Laravel Valet / XAMPP
1. Create a new WordPress site
2. Install WooCommerce plugin
3. Clone WCEventsFP into `wp-content/plugins/`
4. Activate both plugins

#### Manual Setup
```bash
# Download WordPress
wget https://wordpress.org/latest.tar.gz
tar -xzf latest.tar.gz
mv wordpress wceventsfp-dev

# Configure wp-config.php
cp wp-config-sample.php wp-config.php
# Edit database credentials and add:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
define('SCRIPT_DEBUG', true);
```

---

## 🔧 Dependency Management

### PHP Dependencies (Composer)

**⚠️ Known Issue**: Composer install may fail due to GitHub API rate limits.

#### Solution 1: GitHub Token
```bash
# Create GitHub personal access token
# https://github.com/settings/personal-access-tokens

# Configure Composer
composer config --global github-oauth.github.com YOUR_TOKEN

# Install dependencies
composer install
```

#### Solution 2: Alternative Installation
```bash
# Install without dev dependencies (if token unavailable)
composer install --no-dev --optimize-autoloader

# Or continue without Composer tools (basic development possible)
# PHP syntax checking still works:
find . -name "*.php" -exec php -l {} \;
```

### JavaScript Dependencies (npm)

```bash
# Install with legacy peer deps (required for WordPress configs)
npm install --legacy-peer-deps

# Alternative if issues persist
npm install --force

# Verify installation
npm run test:js
```

---

## 🧰 Development Tools

### Code Quality Tools

#### PHP Tools
```bash
# Code linting (WordPress Coding Standards)
composer run lint:phpcs

# Auto-fix code style issues
composer run fix:phpcbf

# Static analysis
composer run stan

# Run all quality checks
composer run quality
```

#### JavaScript Tools
```bash
# ESLint (JavaScript linting)
npm run lint:js

# Stylelint (CSS linting)  
npm run lint:css

# Prettier (code formatting)
npm run format

# Run JavaScript tests
npm run test:js
```

### Build Tools

#### Asset Building
```bash
# Development build (watch mode)
npm run dev

# Production build
npm run build

# Analyze bundle size
npm run analyze
```

#### WordPress-specific Tools
```bash
# Install WP-CLI (if not already installed)
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

# Useful WP-CLI commands for development
wp plugin list
wp plugin activate wceventsfp
wp option get wceventsfp_version
wp db export backup.sql
```

---

## 🧪 Testing Setup

### PHP Testing (PHPUnit)

#### Initial Setup
```bash
# Install test dependencies
composer install

# Run PHP unit tests
composer run test

# Run with coverage (if available)
composer run test:coverage
```

#### Test Structure
```
tests/
├── unit/                      # Unit tests
│   ├── Core/                 # Core functionality tests
│   ├── Features/             # Feature-specific tests
│   └── bootstrap.php         # Test bootstrap
└── js/                       # JavaScript tests
    ├── setup.js              # Jest setup
    └── *.test.js             # Test files
```

### JavaScript Testing (Jest)

```bash
# Run JavaScript tests
npm run test:js

# Watch mode for development
npm run test:js -- --watch

# Coverage report
npm run test:js -- --coverage
```

---

## 🔍 Debugging

### Debug Configuration

#### WordPress Debug Settings
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
define('SCRIPT_DEBUG', true);

// WCEventsFP specific debugging
define('WCEFP_DEBUG', true);
define('WCEFP_DEBUG_HOOKS', true);
define('WCEFP_LOG_LEVEL', 'debug');
```

#### Debug Logs Location
```bash
# WordPress debug log
tail -f wp-content/debug.log

# WCEventsFP specific logs (if implemented)
tail -f wp-content/uploads/wcefp-logs/wcefp.log
```

### Debugging Tools

#### Built-in Diagnostic Tools
```bash
# Health check
php tools/diagnostics/wcefp-health-check.php

# Pre-activation test
php tools/diagnostics/wcefp-pre-activation-test.php

# Installation diagnostic
php tools/diagnostics/wcefp-installation-test.php
```

#### VS Code Debug Configuration
Create `.vscode/launch.json`:
```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Listen for XDebug",
      "type": "php",
      "request": "launch",
      "port": 9003,
      "pathMappings": {
        "/var/www/html/wp-content/plugins/wceventsfp": "${workspaceFolder}"
      }
    }
  ]
}
```

---

## 🌐 Local Development Workflow

### Daily Development Routine

1. **Start Environment**
   ```bash
   wp-env start  # or your local server
   ```

2. **Pull Latest Changes**
   ```bash
   git pull origin main
   composer install --no-dev
   npm install --legacy-peer-deps
   ```

3. **Development Work**
   - Make changes in your IDE
   - Use `npm run dev` for asset watching
   - Test changes in browser

4. **Quality Checks**
   ```bash
   # Before committing
   npm run test:js
   find . -name "*.php" -exec php -l {} \;
   composer run lint:phpcs  # if available
   ```

5. **Commit Changes**
   ```bash
   git add .
   git commit -m "feat: your descriptive commit message"
   git push origin your-branch-name
   ```

### Feature Development Workflow

1. **Create Feature Branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Development & Testing**
   - Implement feature
   - Add/update tests
   - Update documentation

3. **Quality Assurance**
   ```bash
   npm run test:js
   composer run quality  # if tools available
   ```

4. **Create Pull Request**
   - Push branch to GitHub
   - Create PR with detailed description
   - Ensure CI passes

---

## 🔧 Configuration Files

### Essential Configuration Files

#### `.wp-env.json` - WordPress Environment
```json
{
  "core": "WordPress/WordPress#6.6",
  "plugins": [
    "https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip",
    "."
  ],
  "themes": [
    "https://downloads.wordpress.org/theme/twentytwentyfour.zip"
  ],
  "port": 8888,
  "env": {
    "development": {
      "WP_DEBUG": true,
      "WP_DEBUG_LOG": true,
      "WCEFP_DEBUG": true
    }
  }
}
```

#### `phpcs.xml` - PHP Code Standards
```xml
<?xml version="1.0"?>
<ruleset name="WCEventsFP">
    <description>WCEventsFP coding standards</description>
    
    <file>wceventsfp.php</file>
    <file>includes</file>
    <file>admin</file>
    <file>uninstall.php</file>
    
    <rule ref="WordPress-Core"/>
    <rule ref="WordPress-Docs"/>
    <rule ref="WordPress-Extra"/>
    
    <config name="minimum_supported_wp_version" value="6.5"/>
</ruleset>
```

#### `phpstan.neon` - Static Analysis
```neon
parameters:
    level: 6
    paths:
        - includes
        - admin
        - wceventsfp.php
    
    bootstrapFiles:
        - phpstan-bootstrap.php
    
    ignoreErrors:
        - '#Unsafe usage of new static#'
```

---

## 🔀 Git Workflow

### Branch Strategy
- `main` - Production-ready code
- `develop` - Development branch (if used)
- `feature/*` - Feature branches
- `fix/*` - Bug fix branches
- `hotfix/*` - Critical fixes

### Commit Messages
Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add booking calendar widget
fix: resolve payment gateway timeout
docs: update API documentation
test: add unit tests for booking manager
refactor: simplify event creation logic
```

### Pull Request Template
```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Refactoring

## Testing
- [ ] Unit tests pass
- [ ] Manual testing completed
- [ ] Browser compatibility checked

## Checklist
- [ ] Code follows WordPress coding standards
- [ ] Self-review of code completed
- [ ] Documentation updated if needed
```

---

## 🚨 Troubleshooting

### Common Issues & Solutions

#### Composer Install Fails
```bash
# Issue: GitHub API rate limit
# Solution 1: Add GitHub token
composer config --global github-oauth.github.com YOUR_TOKEN

# Solution 2: Skip problematic packages
composer install --ignore-platform-reqs --no-dev
```

#### npm Install Issues
```bash
# Issue: Peer dependency conflicts
# Solution: Use legacy peer deps
npm install --legacy-peer-deps

# Issue: Cache problems
# Solution: Clear npm cache
npm cache clean --force
rm -rf node_modules package-lock.json
npm install --legacy-peer-deps
```

#### WordPress Environment Problems
```bash
# Issue: wp-env won't start
# Solution: Reset environment
wp-env destroy
wp-env start

# Issue: Plugin not loading
# Solution: Check error logs
wp-env logs
```

#### Build Tool Issues
```bash
# Issue: webpack build fails
# Solution: Check for missing webpack.config.js
ls -la webpack.config.js
npm run build -- --mode development

# Issue: PHPStan/PHPCS not working
# Solution: Verify Composer installation
ls -la vendor/bin/phpstan
composer install  # retry with token
```

### Debug Checklist
- [ ] PHP version 8.0+ confirmed
- [ ] Node.js version 18+ confirmed
- [ ] WordPress and WooCommerce activated
- [ ] Debug mode enabled in wp-config.php
- [ ] Error logs checked
- [ ] Dependencies installed correctly
- [ ] File permissions correct (644 for files, 755 for directories)

---

## 📚 Additional Resources

### Documentation
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WooCommerce Developer Documentation](https://woocommerce.github.io/code-reference/)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
- [Jest Testing Framework](https://jestjs.io/docs/getting-started)

### Code Quality Tools
- [WordPress Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/)
- [PHP_CodeSniffer Rules](https://github.com/WordPress/WordPress-Coding-Standards)
- [ESLint WordPress Config](https://www.npmjs.com/package/@wordpress/eslint-plugin)

### Community
- [WCEventsFP Issues](https://github.com/franpass87/WCEventsFP/issues)
- [Contributing Guidelines](CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)

---

*Happy coding! 🚀 Remember to run tests before pushing and keep documentation updated.*