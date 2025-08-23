# Contributing to WCEventsFP

Thank you for your interest in contributing to WCEventsFP! This document provides guidelines and information for contributors.

## Commit Message Guidelines

We use [Conventional Commits](https://www.conventionalcommits.org/) for automated changelog generation and semantic versioning. Please follow this format:

### Commit Types

- **feat**: New features (triggers minor version bump)
- **fix**: Bug fixes (triggers patch version bump)
- **perf**: Performance improvements (triggers patch version bump)
- **refactor**: Code refactoring without changing functionality (triggers patch version bump)
- **docs**: Documentation changes (triggers patch version bump)
- **chore**: Maintenance tasks, dependency updates (triggers patch version bump)
- **feat!** or **fix!**: Breaking changes (triggers major version bump)

### Examples

```
feat: add automated email notifications for bookings
fix: resolve WSOD issue with memory allocation
docs: update installation guide with new requirements
chore: update dependencies to latest versions
feat!: change booking API structure (breaking change)
```

### Format

```
<type>(<optional scope>): <description>

[optional body]

[optional footer(s)]
```

## Development Setup

### Prerequisites

- PHP 7.4 or higher
- WordPress 5.0+
- WooCommerce 4.0+
- Node.js 16+ (for frontend development)
- Composer (for PHP dependencies)

### Installation

1. Clone the repository:
```bash
git clone https://github.com/franpass87/WCEventsFP.git
cd WCEventsFP
```

2. Install PHP dependencies:
```bash
composer install
```

3. Install JavaScript dependencies:
```bash
npm install
```

## Development Workflow

### Code Quality

We use several tools to maintain code quality:

- **PHP CodeSniffer** for PHP code standards (WordPress Coding Standards)
- **PHPStan** for static analysis
- **ESLint** for JavaScript linting
- **Stylelint** for CSS linting

Run quality checks:
```bash
composer run lint         # PHP linting
composer run analyze      # Static analysis
npm run lint:js          # JavaScript linting
npm run lint:css         # CSS linting
```

### Testing

We have comprehensive test suites for both PHP and JavaScript:

```bash
composer run test         # Run PHP unit tests
composer run test:coverage # Run tests with coverage
npm run test:js          # Run JavaScript tests
```

### Building Assets

For frontend development:
```bash
npm run dev             # Watch mode for development
npm run build          # Production build
```

## Code Standards

### PHP

- Follow WordPress Coding Standards
- Use proper PHPDoc comments
- Use type hints where possible (PHP 7.4+ features)
- All classes should be autoloaded via PSR-4

Example:
```php
<?php
/**
 * Example class following WCEventsFP standards
 */
class WCEFP_Example_Class {
    
    /**
     * Example method with proper documentation
     * 
     * @param string $param Example parameter
     * @return bool Returns true on success
     */
    public function example_method(string $param): bool {
        // Implementation here
        return true;
    }
}
```

### JavaScript

- Use modern ES6+ syntax
- Follow WordPress JavaScript standards
- Document functions with JSDoc
- Use jQuery for WordPress compatibility

Example:
```javascript
/**
 * Example function following WCEventsFP standards
 * 
 * @param {string} param Example parameter
 * @returns {boolean} Returns true on success
 */
function exampleFunction(param) {
    // Implementation here
    return true;
}
```

### CSS

- Use BEM methodology for class naming
- Prefix all classes with `wcefp-`
- Use CSS custom properties (variables) where appropriate

Example:
```css
.wcefp-component {
    --wcefp-primary-color: #007cba;
    color: var(--wcefp-primary-color);
}

.wcefp-component__element {
    /* Styles here */
}

.wcefp-component__element--modifier {
    /* Modified styles here */
}
```

## Security Guidelines

- Always validate and sanitize user input
- Use WordPress nonces for form submissions
- Escape output using appropriate WordPress functions
- Log security events using WCEFP_Logger
- Follow OWASP guidelines for web security

Example:
```php
// Input validation
$validated_data = WCEFP_Validator::validate_bulk($_POST, [
    'product_id' => ['method' => 'validate_product_id', 'required' => true],
    'email' => ['method' => 'validate_email', 'required' => false]
]);

if ($validated_data === false) {
    wp_send_json_error(['msg' => __('Invalid input data', 'wceventsfp')]);
}

// Nonce verification
check_ajax_referer('wcefp_admin', 'nonce');

// Output escaping
echo '<div class="notice">' . esc_html($message) . '</div>';
```

## Feature Development

### Adding New Features

1. Create a feature branch: `git checkout -b feature/your-feature-name`
2. Implement the feature following our coding standards
3. Add tests for the new functionality
4. Update documentation if needed
5. Submit a pull request

### Database Changes

- Use WordPress database methods (`$wpdb`)
- Always use prepared statements
- Document schema changes in `CHANGELOG.md`
- Provide upgrade routines for existing installations

### AJAX Handlers

All AJAX handlers should follow this pattern:
```php
public static function ajax_example_handler() {
    try {
        check_ajax_referer('wcefp_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            WCEFP_Logger::warning('Unauthorized access attempt');
            wp_send_json_error(['msg' => __('No permission', 'wceventsfp')]);
        }

        $validated_data = WCEFP_Validator::validate_bulk($_POST, [
            'field_name' => ['method' => 'validate_text', 'required' => true]
        ]);

        if ($validated_data === false) {
            wp_send_json_error(['msg' => __('Invalid input', 'wceventsfp')]);
        }

        // Process the request
        $result = $this->process_data($validated_data);

        WCEFP_Logger::info('Operation completed successfully', $context_data);
        wp_send_json_success(['data' => $result]);

    } catch (Exception $e) {
        WCEFP_Logger::error('AJAX handler error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        wp_send_json_error(['msg' => __('Internal error', 'wceventsfp')]);
    }
}
```

## Pull Request Guidelines

### Before Submitting

- [ ] Code follows our style guides
- [ ] All tests pass (`composer run quality`)
- [ ] Documentation is updated
- [ ] CHANGELOG.md is updated
- [ ] No merge conflicts with main branch

### PR Description Template

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests added/updated
- [ ] Manual testing completed
- [ ] Cross-browser testing (if frontend changes)

## Screenshots (if applicable)

## Additional Notes
```

## Reporting Issues

When reporting bugs or requesting features:

1. Use the appropriate issue template
2. Provide detailed reproduction steps
3. Include environment information
4. Add screenshots/videos if relevant
5. Check for duplicate issues first

## Community Guidelines

- Be respectful and professional
- Help others learn and grow
- Focus on constructive feedback
- Follow the WordPress community guidelines

## Getting Help

- Check existing documentation
- Search closed issues for solutions
- Ask questions in discussions
- Join our community Slack (if available)

## Release Process

1. Feature branches merge to `develop`
2. Regular releases from `develop` to `main`
3. Semantic versioning (MAJOR.MINOR.PATCH)
4. Detailed release notes in CHANGELOG.md

## License

By contributing to WCEventsFP, you agree that your contributions will be licensed under the GPL-3.0 license.