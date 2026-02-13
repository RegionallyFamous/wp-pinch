# Developer Guide

Contributing to WP Pinch means joining a project built with coffee, crustacean puns, and an unreasonable number of PHPStan runs. This guide gets you from clone to merge.

## Prerequisites

- [Docker](https://www.docker.com/) (for `wp-env`)
- [Node.js](https://nodejs.org/) 20+
- [Composer](https://getcomposer.org/)

---

## Local Setup

```bash
git clone https://github.com/RegionallyFamous/wp-pinch.git
cd wp-pinch
composer install
npm install
npm run build
make setup-hooks   # Install pre-commit quality gates
npx wp-env start   # http://localhost:8888 (admin/password)
```

---

## Build Commands

```bash
npm run build      # Production build (Webpack via @wordpress/scripts)
npm run start      # Watch mode for development
npm run lint:js    # ESLint (wp-scripts lint-js)
npm run lint:css   # Stylelint (wp-scripts lint-style)
```

---

## Quality System

WP Pinch uses a multi-layered quality system. Think of it as a lobster cage with multiple chambers -- nothing escapes.

| Layer | Tool | What It Catches |
|---|---|---|
| **Pre-commit hook** | PHPCS + PHPStan | Coding standard violations, type errors |
| **CI Pipeline** | GitHub Actions | PHPCS, PHPStan, PHPUnit, ESLint, Stylelint, Build |
| **Static Analysis** | PHPStan Level 6 | Type mismatches, null access, undefined properties |
| **Coding Standards** | PHPCS (WordPress-Extra + Security) | Security, escaping, sanitization, naming |
| **Unit Tests** | PHPUnit (160+ tests) | Functional correctness, security guards, edge cases |
| **JS Lint** | ESLint (wp-scripts) | JavaScript errors, Prettier formatting |
| **CSS Lint** | Stylelint (wp-scripts) | CSS errors, specificity issues |
| **Branch Protection** | GitHub | All checks must pass before merging to main |

### Running Checks Locally

```bash
# Run everything
make check         # PHPCS + PHPStan

# PHP
composer lint      # PHPCS only
composer phpstan   # PHPStan only
composer lint:fix  # Auto-fix what PHPCBF can

# JavaScript / CSS
npm run lint:js    # ESLint
npm run lint:css   # Stylelint

# Tests
make test          # PHPUnit
npm test           # Jest (frontend tests)
```

---

## Bug Fix Workflow (Test-First)

We follow a test-first approach for bug fixes — because lobsters learn from their mistakes:

1. Write a failing test that reproduces the bug
2. Run the test to confirm it fails
3. Fix the bug
4. Run the test to confirm it passes
5. Run `make check` to ensure nothing else broke
6. Commit with a message that references the test

---

## Project Structure

```
wp-pinch/
├── includes/                    # PHP classes
│   ├── class-abilities.php      # 35 WordPress abilities
│   ├── class-audit-table.php    # Audit log database table
│   ├── class-circuit-breaker.php
│   ├── class-cli.php            # WP-CLI commands
│   ├── class-feature-flags.php  # Feature toggle system
│   ├── class-plugin.php         # Core plugin singleton
│   ├── class-rest-controller.php # REST API endpoints
│   ├── class-settings.php       # Admin settings pages
│   └── class-webhook-dispatcher.php
├── src/
│   ├── admin/                   # Admin JS/CSS source
│   └── blocks/
│       └── pinch-chat/          # Chat block
│           ├── block.json       # Block metadata
│           ├── edit.js          # Editor component
│           ├── index.js         # Block registration
│           ├── view.js          # Frontend (Interactivity API)
│           ├── render.php       # Server-side rendering
│           ├── style.css        # Frontend + editor styles
│           └── editor.css       # Editor-only styles
├── build/                       # Compiled assets (generated)
├── tests/                       # PHPUnit tests
├── languages/                   # Translation files
├── wiki/                        # GitHub Wiki source files
├── wp-pinch.php                 # Plugin entry point
├── uninstall.php                # Cleanup on uninstall
├── Makefile                     # Build and quality targets
├── phpcs.xml.dist               # PHPCS configuration
├── phpstan.neon.dist            # PHPStan configuration
└── .wp-env.json                 # wp-env configuration
```

---

## Making a Release

```bash
make zip           # Build, generate i18n, package as wp-pinch.zip
```

The zip includes compiled assets, Composer production dependencies, and translation files. It does not include dev dependencies, tests, or source files.

---

## Contributing

See [CONTRIBUTING.md](https://github.com/RegionallyFamous/wp-pinch/blob/main/CONTRIBUTING.md) for full guidelines on pull requests, commit messages, and code style.

---

## Credits

WP Pinch is built on many open-source projects:

### WordPress and Automattic

- [WordPress](https://wordpress.org/) -- The CMS that makes it all possible
- [Abilities API](https://developer.wordpress.org/) -- WordPress 6.9 AI capability registration
- [MCP Adapter](https://github.com/WordPress/mcp-adapter) -- Bridges abilities to Model Context Protocol
- [Gutenberg](https://github.com/WordPress/gutenberg) -- Block editor foundation
- [Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/) -- Reactive frontend runtime
- [Action Scheduler](https://actionscheduler.org/) -- Background task execution (GPL-3.0-or-later)
- [Jetpack Autoloader](https://github.com/Automattic/jetpack-autoloader) -- Version conflict prevention (GPL-2.0-or-later)
- [@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/) -- Build toolchain
- [@wordpress/env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) -- Docker dev environment

### Other Projects

- [OpenClaw](https://github.com/nicepkg/openclaw) -- AI agent framework
- [WP-CLI](https://wp-cli.org/) -- Command-line interface (MIT)
- [PHPUnit](https://phpunit.de/) -- Testing framework (BSD-3-Clause)
- [PHPStan](https://phpstan.org/) -- Static analysis (MIT)
- [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) -- PHPCS rules (MIT)

Created and maintained by [Nick Hamze](https://github.com/RegionallyFamous). Built with coffee, crustacean puns, and an unreasonable number of PHPStan runs.
