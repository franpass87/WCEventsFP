# WCEventsFP CI/CD Pipeline Documentation

## Overview

WCEventsFP uses a comprehensive CI/CD pipeline built on GitHub Actions to ensure code quality, security, and reliability across all environments. The pipeline implements enterprise-grade practices including automated testing, security scanning, performance monitoring, and deployment automation.

## Pipeline Architecture

### 🔄 Continuous Integration Workflows

1. **CI/CD Pipeline - Enterprise Grade** (`ci-cd-enterprise.yml`)
   - Full integration testing across PHP/WordPress matrix
   - Security scanning and performance benchmarks  
   - Build artifact generation and deployment

2. **Pull Request Quality Gates** (`pr-quality-gates.yml`)
   - Automated PR validation and triage
   - Targeted testing based on change analysis
   - Automated feedback and merge readiness checks

3. **Quality Assurance** (`quality-assurance.yml`) 
   - PHP quality checks across version matrix
   - JavaScript testing and linting
   - Accessibility and security validation

4. **Build & Release** (`build-release.yml`)
   - WordPress plugin packaging
   - Release artifact creation
   - GitHub release automation

## Workflow Triggers

### Automatic Triggers
- **Push to main/develop**: Full CI/CD pipeline
- **Pull Request**: Quality gates and targeted testing
- **Tag creation**: Release build and GitHub release
- **Weekly schedule**: Dependency and security scanning

### Manual Triggers
- **Workflow dispatch**: Manual pipeline execution
- **Release dispatch**: Manual release creation

## Testing Strategy

### 🧪 Test Matrix

#### PHP Testing
- **Versions**: PHP 8.0, 8.1, 8.2, 8.3
- **WordPress**: 6.5, 6.6, latest
- **WooCommerce**: Latest compatible versions
- **Database**: MySQL 8.0

#### JavaScript Testing  
- **Node.js**: 18.x
- **Test Framework**: Jest
- **Coverage**: Codecov integration
- **Tests**: 23 comprehensive test cases

### 🔍 Quality Gates

#### Automated Checks
- **Syntax validation** for all PHP/JS files
- **Security pattern detection** (XSS, SQL injection, dangerous functions)
- **Code style consistency** (PSR-4, WordPress standards)
- **Performance benchmarks** (memory usage, activation time)
- **Plugin activation testing** in clean WordPress environment

#### Manual Review Requirements
- **High complexity PRs** (>50 files changed)
- **Critical file changes** (main plugin file, composer.json, etc.)
- **Security-sensitive modifications**

## Environment Configuration

### Development Environment
```yaml
PHP_VERSION: '8.1'          # Primary development version
NODE_VERSION: '18'          # LTS Node.js version  
WP_VERSION: 'latest'        # Latest WordPress
MYSQL_VERSION: '8.0'        # Database version
```

### Production Environment
- **PHP**: 8.0+ (backward compatibility)
- **WordPress**: 6.5+ minimum
- **WooCommerce**: 8.0+ minimum
- **Memory limit**: 256MB recommended
- **Max execution time**: 60s recommended

## Security Implementation

### 🛡️ Security Scanning

#### Automated Security Checks
1. **ABSPATH validation** - WordPress security best practice
2. **Nonce verification** - CSRF protection implementation
3. **Capability checks** - User permission validation  
4. **Data escaping** - XSS prevention measures
5. **SQL injection prevention** - Prepared statement usage
6. **Superglobal output detection** - Direct variable output prevention

#### Dependency Security
- **npm audit** for Node.js dependencies
- **Composer security check** (when available)
- **Known vulnerability pattern detection**

### Security Score Requirements
- **Minimum score**: 80% to pass security gates
- **Critical issues**: Block deployment automatically  
- **Weekly security scans** via scheduled workflows

## Performance Monitoring

### 📊 Performance Benchmarks

#### Plugin Performance
- **Activation time**: < 2 seconds
- **Memory usage**: < 10MB additional
- **Database queries**: Optimized and monitored
- **Asset loading**: Conditional and optimized

#### Build Performance
- **Test execution**: < 5 minutes total
- **Build artifact**: < 2MB compressed
- **Deployment time**: < 30 seconds

### Performance Testing
```yaml
# Automated performance tests
- Plugin activation timing
- Memory usage monitoring  
- Database query analysis
- Asset optimization validation
```

## Build & Deployment

### 🏗️ Build Process

#### Distribution Package Creation
1. **Clean build environment**
2. **Exclude development files** (tests, configs, node_modules)
3. **Include essential dependencies** (vendor files)
4. **Create ZIP archive** with proper WordPress structure
5. **Validate package integrity**

#### Build Artifacts
- **Plugin ZIP**: Ready for WordPress installation
- **Version tagging**: Automatic from plugin file
- **Release notes**: Auto-generated from changelog
- **Retention**: 30 days for CI artifacts

### 🚀 Deployment Strategy

#### Staging Deployment
- **Trigger**: Successful main branch builds
- **Environment**: Staging environment validation
- **Tests**: Full functionality testing
- **Approval**: Automatic for non-breaking changes

#### Production Release
- **Trigger**: Git tag creation (v*)
- **Process**: GitHub release with packaged plugin
- **Distribution**: Direct WordPress installation ready
- **Rollback**: Previous version availability

## Monitoring & Notifications

### 📈 Build Monitoring

#### Success Metrics
- **Test pass rate**: Target 100%
- **Security score**: Target >90%
- **Performance benchmarks**: All passing
- **Build success rate**: Target >95%

#### Failure Handling  
- **Automatic retry**: Transient failures (1 retry)
- **Failure notifications**: Team alerts
- **Rollback capability**: Previous stable version
- **Issue tracking**: Automatic GitHub issue creation

### 🔔 Notification System

#### Build Status
```yaml
# Automated notifications
Success: ✅ All tests passed, artifacts ready
Failure: ❌ Pipeline failed, review required  
Warning: ⚠️ Non-critical issues detected
```

#### PR Feedback
- **Automated PR comments** with analysis summary
- **Change complexity assessment**
- **Test results and recommendations**
- **Merge readiness indicators**

## Developer Workflow

### 🔧 Local Development

#### Prerequisites
```bash
# Required tools
php >= 8.0
node >= 18
composer
npm  
wp-cli
```

#### Development Setup
```bash
# Install dependencies
composer install
npm install --legacy-peer-deps

# Run tests locally
npm run test:js
php tests/basic-test-runner.php

# Build assets (if available)
npm run build
```

### 📝 Contributing Guidelines

#### Pull Request Process
1. **Create feature branch** from develop
2. **Make focused changes** (single feature/fix)
3. **Write/update tests** for new functionality
4. **Ensure all tests pass** locally
5. **Create PR** with descriptive title and body
6. **Address automated feedback** 
7. **Request human review** after quality gates pass

#### Commit Message Format
```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

**Types**: feat, fix, docs, style, refactor, test, chore
**Scope**: module name or area affected
**Description**: Clear, concise change summary

### 🚦 Quality Standards

#### Code Quality Requirements
- **PHP**: WordPress coding standards compliance
- **JavaScript**: ES6+ with proper error handling
- **CSS**: BEM methodology, responsive design
- **Documentation**: Inline comments, README updates

#### Test Requirements
- **Unit tests**: New functionality covered
- **Integration tests**: API endpoints tested
- **E2E tests**: Critical user flows validated
- **Performance tests**: No regression introduced

## Troubleshooting

### 🔧 Common Issues

#### Composer Dependencies
```bash
# Issue: GitHub API rate limiting
# Solution: Expected behavior, tests continue with available tools
composer install --no-dev --optimize-autoloader || echo "Expected failure"
```

#### Node.js Dependencies  
```bash
# Issue: Deprecated WordPress configs
# Solution: Use legacy peer deps flag
npm install --legacy-peer-deps
```

#### Test Failures
```bash
# Check specific test output
npm run test:js -- --verbose
php tests/basic-test-runner.php

# Review automated PR feedback
# Address security patterns if flagged
# Verify plugin activation in clean environment
```

### 📊 Pipeline Debugging

#### GitHub Actions Debugging
- **Workflow runs**: Check GitHub Actions tab
- **Artifact downloads**: Available for 30 days
- **Log analysis**: Detailed step-by-step output
- **Re-run capability**: Full or failed jobs only

#### Local Testing
```bash
# Simulate CI environment
docker run --rm -it -v $(pwd):/app php:8.1-cli bash
cd /app && php tests/basic-test-runner.php

# Test plugin packaging  
./build-distribution.sh
unzip -t wceventsfp-*.zip
```

## Maintenance & Updates

### 🔄 Regular Maintenance

#### Weekly Tasks
- **Dependency updates**: npm audit fix, composer update  
- **Security scanning**: Automated via scheduled workflow
- **Performance monitoring**: Review metrics and trends
- **Documentation updates**: Keep current with changes

#### Monthly Tasks
- **Pipeline optimization**: Review execution times
- **Test coverage analysis**: Identify gaps
- **Security policy updates**: Latest best practices
- **Dependency cleanup**: Remove unused packages

### 📈 Continuous Improvement

#### Metrics Tracking
- **Build times**: Optimize slow steps
- **Test execution**: Parallelize where possible
- **Artifact sizes**: Monitor and optimize
- **Success rates**: Investigate failure patterns

#### Feature Enhancements
- **New test frameworks**: Playwright for E2E testing
- **Advanced security**: SAST/DAST integration  
- **Performance monitoring**: Real user metrics
- **Deployment automation**: Production pipeline

## Configuration Reference

### Environment Variables
```yaml
# Required for all workflows
PHP_VERSION: '8.1'
NODE_VERSION: '18' 
WP_VERSION: 'latest'
MYSQL_VERSION: '8.0'

# Optional customization
COMPOSER_MEMORY_LIMIT: '2G'
NODE_OPTIONS: '--max-old-space-size=4096'
```

### Secrets Configuration
```yaml
# GitHub repository secrets (if needed)
CODECOV_TOKEN: # Code coverage reporting
DEPLOY_KEY: # Staging deployment  
SLACK_WEBHOOK: # Notifications
```

### Workflow Permissions
```yaml
# Required permissions
contents: read          # Repository access
checks: write          # Check status updates  
pull-requests: write   # PR comments and labels
security-events: write # Security scanning
```

This comprehensive CI/CD system ensures WCEventsFP maintains enterprise-grade quality standards while enabling efficient development workflows and reliable deployments.