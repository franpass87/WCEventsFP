# WCEventsFP Development Makefile
# ==================================
# Automates setup, testing, and build processes
# Usage: make help

.PHONY: help setup test clean build lint fix analyze package

# Default target
.DEFAULT_GOAL := help

# Colors for output
GREEN := \033[32m
YELLOW := \033[33m
RED := \033[31m
BOLD := \033[1m
RESET := \033[0m

help: ## Show this help message
	@echo "$(BOLD)WCEventsFP Development Commands$(RESET)"
	@echo "=================================="
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-20s$(RESET) %s\n", $$1, $$2}'

setup: ## Setup development environment (install all dependencies)
	@echo "$(BOLD)Setting up development environment...$(RESET)"
	@echo "$(YELLOW)Installing Node.js dependencies...$(RESET)"
	npm install --legacy-peer-deps
	@echo "$(YELLOW)Installing PHP dependencies (may require GitHub token)...$(RESET)"
	composer install --ignore-platform-reqs || echo "$(RED)Composer install failed - GitHub token required$(RESET)"
	@echo "$(GREEN)Setup complete!$(RESET)"

setup-quick: ## Quick setup (npm only, skip composer)
	@echo "$(BOLD)Quick setup (npm dependencies only)...$(RESET)"
	npm install --legacy-peer-deps
	@echo "$(GREEN)Quick setup complete!$(RESET)"

test: ## Run all tests (Jest + PHP syntax)
	@echo "$(BOLD)Running all tests...$(RESET)"
	@echo "$(YELLOW)Running Jest tests...$(RESET)"
	npm run test:js
	@echo "$(YELLOW)Running PHP syntax checks...$(RESET)"
	@$(MAKE) test-php-syntax
	@echo "$(GREEN)All tests completed!$(RESET)"

test-js: ## Run JavaScript tests only
	@echo "$(YELLOW)Running Jest tests...$(RESET)"
	npm run test:js

test-php: ## Run PHP tests (requires composer dependencies)
	@echo "$(YELLOW)Running PHPUnit tests...$(RESET)"
	@if [ -f vendor/bin/phpunit ]; then \
		composer run test; \
	else \
		echo "$(RED)PHPUnit not available - run 'make setup' first$(RESET)"; \
	fi

test-php-syntax: ## Check PHP syntax across all files
	@echo "$(YELLOW)Checking PHP syntax...$(RESET)"
	@find includes/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors" | head -10 || echo "$(GREEN)No PHP syntax errors found$(RESET)"

lint: ## Run all linters (PHP + JavaScript)
	@echo "$(BOLD)Running linters...$(RESET)"
	@$(MAKE) lint-php
	@$(MAKE) lint-js

lint-php: ## Run PHP CodeSniffer
	@echo "$(YELLOW)Running PHP CodeSniffer...$(RESET)"
	@if [ -f vendor/bin/phpcs ]; then \
		composer run lint:phpcs; \
	else \
		echo "$(RED)PHPCS not available - run 'make setup' first$(RESET)"; \
	fi

lint-js: ## Run ESLint on JavaScript files
	@echo "$(YELLOW)Running ESLint...$(RESET)"
	npm run lint:js || echo "$(RED)ESLint configuration missing$(RESET)"

fix: ## Auto-fix code style issues
	@echo "$(BOLD)Fixing code style...$(RESET)"
	@if [ -f vendor/bin/phpcbf ]; then \
		composer run fix:phpcbf; \
	else \
		echo "$(RED)PHPCBF not available - run 'make setup' first$(RESET)"; \
	fi
	npm run format || echo "$(RED)Prettier not configured$(RESET)"

analyze: ## Run static analysis (PHPStan)
	@echo "$(YELLOW)Running PHPStan analysis...$(RESET)"
	@if [ -f vendor/bin/phpstan ]; then \
		composer run stan; \
	else \
		echo "$(RED)PHPStan not available - run 'make setup' first$(RESET)"; \
	fi

quality: ## Run full quality check (lint + analyze + test)
	@echo "$(BOLD)Running full quality check...$(RESET)"
	@$(MAKE) lint
	@$(MAKE) analyze
	@$(MAKE) test
	@echo "$(GREEN)Quality check completed!$(RESET)"

build: ## Build production assets
	@echo "$(BOLD)Building production assets...$(RESET)"
	npm run build || echo "$(RED)Build configuration missing$(RESET)"

package: ## Create distribution package
	@echo "$(BOLD)Creating distribution package...$(RESET)"
	@if [ -f build-distribution.sh ]; then \
		chmod +x build-distribution.sh && ./build-distribution.sh; \
	else \
		echo "$(RED)build-distribution.sh not found$(RESET)"; \
	fi
	@echo "$(GREEN)Package created!$(RESET)"

clean: ## Clean build artifacts and dependencies
	@echo "$(BOLD)Cleaning build artifacts...$(RESET)"
	rm -rf node_modules/
	rm -rf vendor/
	rm -rf coverage/
	rm -rf dist/
	rm -f *.zip
	@echo "$(GREEN)Clean completed!$(RESET)"

dev: ## Start development environment
	@echo "$(BOLD)Starting development environment...$(RESET)"
	npm run dev || echo "$(RED)Dev server not configured$(RESET)"

status: ## Show environment status
	@echo "$(BOLD)Environment Status$(RESET)"
	@echo "=================="
	@echo "$(YELLOW)Node.js version:$(RESET) $$(node --version 2>/dev/null || echo 'Not installed')"
	@echo "$(YELLOW)npm version:$(RESET) $$(npm --version 2>/dev/null || echo 'Not installed')"
	@echo "$(YELLOW)PHP version:$(RESET) $$(php --version 2>/dev/null | head -1 || echo 'Not installed')"
	@echo "$(YELLOW)Composer version:$(RESET) $$(composer --version 2>/dev/null || echo 'Not installed')"
	@echo "$(YELLOW)Node dependencies:$(RESET) $$(test -d node_modules && echo 'Installed' || echo 'Missing')"
	@echo "$(YELLOW)PHP dependencies:$(RESET) $$(test -d vendor && echo 'Installed' || echo 'Missing')"
	@echo "$(YELLOW)PHPUnit available:$(RESET) $$(test -f vendor/bin/phpunit && echo 'Yes' || echo 'No')"
	@echo "$(YELLOW)PHPCS available:$(RESET) $$(test -f vendor/bin/phpcs && echo 'Yes' || echo 'No')"

# Environment checks
check-node:
	@which node > /dev/null || (echo "$(RED)Node.js is required$(RESET)" && exit 1)

check-php:
	@which php > /dev/null || (echo "$(RED)PHP is required$(RESET)" && exit 1)

check-composer:
	@which composer > /dev/null || (echo "$(RED)Composer is required$(RESET)" && exit 1)

# Quick commands
install: setup ## Alias for setup
deps: setup ## Alias for setup
check: test ## Alias for test