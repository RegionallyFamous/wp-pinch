# Changelog

All notable changes to WP Pinch will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.1] - 2026-02-10

### Fixed
- Screen reader announcements now work on the frontend — `wp-a11y` script is enqueued when the Pinch Chat block renders outside the admin.
- Session storage is now scoped per block instance, preventing message cross-contamination when multiple Pinch Chat blocks appear on the same page.
- Focus ring on the chat input is now visible in Windows High Contrast Mode — replaced `outline: none` with `outline: 2px solid transparent` so the browser's forced-colors mode can restore the outline.
- Fixed indentation inconsistencies in `view.js` (misaligned `try`/`catch`/`finally` blocks from a prior edit).
- Added `Update URI: false` to the plugin header to prevent third-party update hijacking.
- Replaced `data-wp-context` manual JSON encoding with `wp_interactivity_data_wp_context()` for proper attribute escaping in `render.php`.
- Applied late escaping (`esc_attr()`) to the chat input placeholder at the point of output.
- Fixed message ID collisions in `view.js` by appending a monotonic counter to `Date.now()`.
- Scoped DOM queries in `scrollToBottom()` and `focusInput()` to the current block instance using `getElement()` + `closest()`, with a global fallback.
- Wrapped `response.json()` in a `try`/`catch` to gracefully handle non-JSON server responses.

### Changed
- Comprehensive README.md rewrite with full feature documentation, architecture diagram, security details, hooks reference, and development workflow.
- Comprehensive readme.txt rewrite for WordPress.org with complete feature descriptions, FAQ, and changelog.

### Security
- Fixed PHPCS violations: global variable prefixing in `uninstall.php`, missing translator comments, unescaped output casting, Yoda conditions.
- Fixed PHPStan Level 6 violations: type casting for `post_author`, `esc_html()` string coercion, `end()` by-reference constant access, unused closure variables, redundant `unset()`.
- Replaced `@unlink()` with `wp_delete_file()` in abilities handler.
- Added `show_in_rest => false` enforcement and `Update URI: false` header.
- Configured PHPCS with WordPress-Extra + WordPress.Security rulesets.
- Configured PHPStan Level 6 with WordPress stubs and bootstrap file.

### Added
- PHPStan static analysis (Level 6) with `szepeviktor/phpstan-wordpress` stubs.
- PHPCS configuration (`phpcs.xml.dist`) with WordPress-Extra and WordPress.Security rulesets.
- PHPStan bootstrap file (`tests/phpstan-bootstrap.php`) for constant resolution.
- Pre-commit Git hook for automated quality gates.
- `make check` target combining PHPCS + PHPStan.
- `make setup-hooks` target for installing the pre-commit hook.
- GDPR privacy tools (data export and erasure) documented.
- Site Health integration documented.

## [1.0.0] - 2026-02-10

### Added
- 25 WordPress abilities across 7 categories: content, media, users, comments, settings, plugins/themes, and analytics.
- Custom MCP server registration via WordPress 6.9 Abilities API.
- Webhook dispatcher with exponential backoff retry (4 attempts) and configurable rate limiting.
- Autonomous governance engine with 5 background tasks: content freshness, SEO health, comment sweep, broken link detection, and security scanning.
- Pinch Chat Gutenberg block using the Interactivity API.
- WP-CLI commands: status, webhook-test, governance run, audit list, abilities list.
- Comprehensive audit log with automatic 90-day retention.
- Admin settings page with connection testing, webhook configuration, and governance controls.
- GitHub Actions CI pipeline with PHPUnit, build verification, and plugin check.

[Unreleased]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/RegionallyFamous/wp-pinch/releases/tag/v1.0.0
