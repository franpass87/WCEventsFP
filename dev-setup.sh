#!/bin/bash
# WCEventsFP Development Environment Setup
# =======================================
# Automated setup script for development environment

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Functions
print_header() {
    echo -e "${BOLD}${BLUE}$1${NC}"
    echo "=================================="
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

check_command() {
    if command -v $1 &> /dev/null; then
        print_success "$1 is installed"
        return 0
    else
        print_error "$1 is not installed"
        return 1
    fi
}

print_header "WCEventsFP Development Environment Setup"

# Check prerequisites
echo -e "${BOLD}Checking prerequisites...${NC}"
missing_deps=0

if ! check_command "node"; then
    print_error "Node.js is required. Please install Node.js 18+ from https://nodejs.org/"
    missing_deps=1
fi

if ! check_command "npm"; then
    print_error "npm is required (usually comes with Node.js)"
    missing_deps=1
fi

if ! check_command "php"; then
    print_error "PHP is required. Please install PHP 8.0+ from https://php.net/"
    missing_deps=1
fi

if ! check_command "composer"; then
    print_error "Composer is required. Please install from https://getcomposer.org/"
    missing_deps=1
fi

if [ $missing_deps -eq 1 ]; then
    echo ""
    print_error "Please install missing dependencies before continuing."
    exit 1
fi

# Show versions
echo ""
echo -e "${BOLD}Environment versions:${NC}"
echo "Node.js: $(node --version)"
echo "npm: $(npm --version)"
echo "PHP: $(php --version | head -1)"
echo "Composer: $(composer --version)"

# Install Node.js dependencies
echo ""
print_header "Installing Node.js Dependencies"
echo "This may take 1-2 minutes..."
if npm install --legacy-peer-deps --silent; then
    print_success "Node.js dependencies installed successfully"
else
    print_warning "Node.js dependencies installation had issues, trying with --force"
    if npm install --force --silent; then
        print_success "Node.js dependencies installed with --force"
    else
        print_error "Failed to install Node.js dependencies"
        exit 1
    fi
fi

# Install PHP dependencies
echo ""
print_header "Installing PHP Dependencies"
echo "This may take 5+ minutes and might require GitHub token..."
if composer install --ignore-platform-reqs --quiet; then
    print_success "PHP dependencies installed successfully"
else
    print_warning "PHP dependencies installation failed (likely GitHub API rate limit)"
    print_warning "You can continue with limited functionality"
    print_warning "To resolve: create GitHub token at https://github.com/settings/tokens"
fi

# Run initial tests
echo ""
print_header "Running Initial Tests"

# Test JavaScript
echo "Running Jest tests..."
if npm run test:js --silent; then
    print_success "JavaScript tests passed"
else
    print_warning "JavaScript tests failed"
fi

# Test PHP syntax
echo "Checking PHP syntax..."
if find includes/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors" | head -5; then
    print_error "PHP syntax errors found"
else
    print_success "PHP syntax check passed"
fi

# Check if dev tools are available
echo ""
print_header "Checking Development Tools"

if [ -f "vendor/bin/phpunit" ]; then
    print_success "PHPUnit is available"
else
    print_warning "PHPUnit not available (composer install needed)"
fi

if [ -f "vendor/bin/phpcs" ]; then
    print_success "PHP CodeSniffer is available"
else
    print_warning "PHP CodeSniffer not available (composer install needed)"
fi

if [ -f "vendor/bin/phpstan" ]; then
    print_success "PHPStan is available"
else
    print_warning "PHPStan not available (composer install needed)"
fi

# Setup complete
echo ""
print_header "Setup Complete!"
echo ""
echo -e "${BOLD}Quick Commands:${NC}"
echo "  make test       - Run all tests"
echo "  make lint       - Run linters"
echo "  make build      - Build production assets"
echo "  make package    - Create distribution ZIP"
echo "  make help       - Show all available commands"
echo ""
echo -e "${BOLD}Development Workflow:${NC}"
echo "  1. Make your changes"
echo "  2. Run 'make test' to verify"
echo "  3. Run 'make lint' to check code style"
echo "  4. Run 'make package' to create distribution"
echo ""
print_success "Development environment is ready!"