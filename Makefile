# WP Pinch — Build & Development Makefile
# =========================================

PLUGIN_SLUG  := wp-pinch
PLUGIN_VER   := $(shell grep 'Version:' wp-pinch.php | head -1 | sed 's/.*Version:[[:space:]]*//')
ZIP_NAME     := $(PLUGIN_SLUG)-$(PLUGIN_VER).zip
DIST_DIR     := dist

.DEFAULT_GOAL := build

# ---------------------------------------------------------------------------
# Dependencies
# ---------------------------------------------------------------------------

.PHONY: install
install: node_modules vendor ## Install all dependencies

node_modules: package.json
	npm install
	@touch node_modules

vendor: composer.json composer.lock
	composer install --no-dev --prefer-dist --optimize-autoloader
	@touch vendor

.PHONY: install-dev
install-dev: ## Install all dependencies including dev
	npm ci
	composer install --prefer-dist

# ---------------------------------------------------------------------------
# Build
# ---------------------------------------------------------------------------

.PHONY: build
build: node_modules ## Build JS/CSS assets
	npm run build

.PHONY: dev
dev: node_modules ## Start webpack in watch mode
	npm run start

# ---------------------------------------------------------------------------
# Lint & Test
# ---------------------------------------------------------------------------

.PHONY: lint
lint: lint-js lint-css lint-php ## Run all linters

.PHONY: lint-js
lint-js: node_modules ## Lint JavaScript
	npm run lint:js

.PHONY: lint-css
lint-css: node_modules ## Lint CSS
	npm run lint:css

.PHONY: lint-php
lint-php: vendor ## Lint PHP with PHPCS
	composer lint

.PHONY: lint-tests
lint-tests: vendor ## Lint test files (relaxed PHPCS rules)
	composer lint:tests

.PHONY: lint-fix
lint-fix: vendor ## Auto-fix PHPCS violations
	composer lint:fix

.PHONY: phpstan
phpstan: vendor ## Run PHPStan static analysis
	composer phpstan

.PHONY: mutation
mutation: vendor ## Run Infection mutation testing (requires PCOV/Xdebug + WP test env)
	composer infection

.PHONY: test
test: ## Run PHPUnit tests (requires WP test suite)
	vendor/bin/phpunit

.PHONY: test-wp-env
test-wp-env: node_modules vendor ## Run PHPUnit inside wp-env (Docker). Run 'make wp-env-start' first.
	@echo "Running PHPUnit via wp-env..."
	npx wp-env run cli --env-cwd=wp-content/plugins/wp-pinch vendor/bin/phpunit --testdox

.PHONY: test-coverage
test-coverage: ## Run PHPUnit with HTML coverage report (requires PCOV or Xdebug)
	@mkdir -p build
	vendor/bin/phpunit --coverage-html build/coverage
	@echo "Coverage report: build/coverage/index.html"

.PHONY: test-coverage-clover
test-coverage-clover: ## Run PHPUnit with Clover XML (for Codecov upload)
	@mkdir -p build/coverage
	vendor/bin/phpunit --coverage-clover build/coverage/clover.xml

.PHONY: check
check: lint lint-tests phpstan ## Run lint + PHPCS (tests) + PHPStan (no DB). Use 'make ci' for full CI parity including tests.
	@echo ""
	@echo "✓ Lint and PHPStan passed."

.PHONY: ci
ci: lint lint-tests phpstan test ## Run full CI locally: lint + PHPCS tests + PHPStan + PHPUnit (requires WP test env).
	@echo ""
	@echo "✓ All CI checks passed."

# ---------------------------------------------------------------------------
# i18n — POT file generation
# ---------------------------------------------------------------------------

.PHONY: i18n
i18n: ## Generate POT file for translations
	@mkdir -p languages
	@if command -v wp >/dev/null 2>&1; then \
		wp i18n make-pot . languages/wp-pinch.pot \
			--slug=wp-pinch \
			--domain=wp-pinch \
			--exclude=node_modules,vendor,tests,dist,.git,.github; \
		echo "Generated languages/wp-pinch.pot"; \
	else \
		echo "WP-CLI not found. Install it: https://wp-cli.org/#installing"; \
		exit 1; \
	fi

.PHONY: setup-hooks
setup-hooks: ## Install git pre-commit hook
	@cp bin/pre-commit .git/hooks/pre-commit
	@chmod +x .git/hooks/pre-commit
	@echo "Pre-commit hook installed."

# ---------------------------------------------------------------------------
# Package for distribution
# ---------------------------------------------------------------------------

.PHONY: zip
zip: i18n zip-dist ## Create distributable plugin ZIP (requires WP-CLI for i18n)

.PHONY: zip-dist
zip-dist: build vendor ## Create distributable plugin ZIP (skips i18n when WP-CLI unavailable)
	@echo "Packaging $(ZIP_NAME)..."
	@rm -rf $(DIST_DIR)
	@mkdir -p $(DIST_DIR)/$(PLUGIN_SLUG)
	@rsync -av --progress \
		--exclude='node_modules' \
		--exclude='vendor' \
		--exclude='$(DIST_DIR)' \
		--exclude='.git' \
		--exclude='.github' \
		--exclude='.vscode' \
		--exclude='.wp-env.json' \
		--exclude='.wp-env.override.json' \
		--exclude='phpunit.xml.dist' \
		--exclude='phpstan.neon.dist' \
		--exclude='phpcs.xml.dist' \
		--exclude='bin' \
		--exclude='tests' \
		--exclude='src' \
		--exclude='Makefile' \
		--exclude='webpack.config.js' \
		--exclude='package.json' \
		--exclude='package-lock.json' \
		--exclude='composer.json' \
		--exclude='composer.lock' \
		--exclude='SKILL.md' \
		--exclude='.DS_Store' \
		--exclude='*.log' \
		--exclude='.phpunit.result.cache' \
		--exclude='.gitignore' \
		--exclude='.cursor' \
		--exclude='.cursorrules' \
		--exclude='.editorconfig' \
		--exclude='CONTRIBUTING.md' \
		--exclude='*.zip' \
		. $(DIST_DIR)/$(PLUGIN_SLUG)/
	@# Copy production vendor (no dev dependencies)
	@composer install --no-dev --prefer-dist --optimize-autoloader --quiet
	@cp -R vendor $(DIST_DIR)/$(PLUGIN_SLUG)/vendor
	@cd $(DIST_DIR) && zip -r ../$(ZIP_NAME) $(PLUGIN_SLUG)/ -x '*.DS_Store'
	@rm -rf $(DIST_DIR)
	@echo ""
	@echo "Created: $(ZIP_NAME) ($$(du -h $(ZIP_NAME) | cut -f1))"

# ---------------------------------------------------------------------------
# Local development environment
# ---------------------------------------------------------------------------

.PHONY: wp-env-start
wp-env-start: node_modules build ## Start wp-env local environment
	npx wp-env start

.PHONY: wp-env-stop
wp-env-stop: ## Stop wp-env local environment
	npx wp-env stop

.PHONY: wp-env-clean
wp-env-clean: ## Destroy wp-env local environment
	npx wp-env destroy

# ---------------------------------------------------------------------------
# Housekeeping
# ---------------------------------------------------------------------------

.PHONY: clean
clean: ## Remove build artifacts
	rm -rf build node_modules vendor $(DIST_DIR)
	rm -f $(PLUGIN_SLUG)-*.zip

.PHONY: clean-build
clean-build: ## Remove only build output
	rm -rf build

.PHONY: help
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'
