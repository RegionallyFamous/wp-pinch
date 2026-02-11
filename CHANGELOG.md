# Changelog

All notable changes to WP Pinch will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-02-11

### BREAKING

- Chat block now requires a `blockId` attribute for stable session storage keys. Existing blocks auto-migrate with a `wp_unique_id()` fallback, but re-saving the block in the editor assigns a permanent stable ID. Users of multiple chat blocks on one page should re-save those pages.
- User emails are no longer returned from any ability (`list-users`, `get-user`, `export-data`, WooCommerce orders). Code relying on `email` in ability responses must be updated.
- WooCommerce order responses no longer include billing email, full last name, or payment method title.
- The MCP server no longer exposes `core/get-user-info` or `core/get-environment-info` abilities publicly.
- Bulk `delete` action now trashes posts instead of permanently deleting them.
- `admin_email` removed from the default option read allowlist.
- The `wp_pinch_blocked_roles` filter now receives an empty array by default (administrator is always blocked unconditionally and cannot be unblocked via filter).

### Security

- **Access control — post-level capability checks**: Added `current_user_can( 'edit_post' )` to `update-post`, `current_user_can( 'delete_post' )` to `delete-post`, and post-type capability checks to `list-posts` and `search-content`.
- **Access control — private posts**: `search-content` now only includes `private` post status when `current_user_can( 'read_private_posts' )`.
- **Privilege escalation — administrator role hardcoded block**: `update-user-role` unconditionally blocks the `administrator` role regardless of filter output.
- **Privilege escalation — dangerous capabilities check**: Roles with any administrative capability (`manage_options`, `edit_users`, `activate_plugins`, `delete_users`, `create_users`, `unfiltered_html`, `update_core`) are blocked from assignment.
- **Privilege escalation — administrator downgrade prevention**: Cannot modify the role of an existing administrator.
- **IDOR — session key hijacking**: Chat session key is always derived from the authenticated user ID; client-supplied `session_key` parameter is ignored.
- **IDOR — arbitrary post deletion via menu item**: `manage-menu-item` delete action now validates the target is actually a `nav_menu_item` post type.
- **SSRF — broken link checker**: Added pre-request DNS resolution with `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` to block requests to internal/private/reserved IPs. Enabled SSL verification (`sslverify => true`).
- **SSRF — media upload URL scheme**: `upload-media` now rejects non-HTTP/HTTPS URL schemes (`ftp:`, `file:`, `data:`, etc.).
- **Code execution — arbitrary hook invocation**: `manage-cron` "run" action now verifies the hook exists in `_get_cron_array()` before calling `do_action()`.
- **Core cron protection**: `manage-cron` "delete" action blocks removal of critical WordPress core cron hooks (`wp_update_plugins`, `wp_update_themes`, `wp_version_check`, `wp_scheduled_delete`, etc.).
- **Options denylist**: Hardcoded `OPTION_DENYLIST` (auth salts, `active_plugins`, `users_can_register`, `default_role`, `wp_pinch_api_token`) enforced before any filter can override it in both `get-option` and `update-option`.
- **PII redaction — user data**: Removed `user_email` from `list-users`, `get-user`, and `export-data` ability responses.
- **PII redaction — WooCommerce orders**: Removed billing email, full last name, and payment method title from order data. Order notes no longer include `author`.
- **PII redaction — comment sweep**: Removed `comment_author` and `comment_content` excerpt from governance comment sweep findings.
- **PII redaction — webhook dispatch**: Removed `email` from the `user_register` webhook payload.
- **Information disclosure — API token masking**: Settings page now shows a masked placeholder (`••••••••`) instead of the real token. Submitting the placeholder preserves the existing token.
- **Information disclosure — MCP server**: Removed `core/get-user-info` and `core/get-environment-info` from the publicly exposed MCP ability list.
- **Information disclosure — security scan versions**: Governance security scan no longer reports exact version numbers for core, plugin, or theme updates — only availability/names/counts.
- **Information disclosure — gateway errors**: All `WP_Error` messages from the gateway are logged server-side via `Audit_Table` and replaced with generic messages in client responses (both AJAX and REST).
- **Information disclosure — test connection payload**: Test connection AJAX response no longer returns raw gateway response body.
- **Input validation — message length**: REST chat endpoint enforces `MAX_MESSAGE_LENGTH` (4,000 characters) via `validate_callback`.
- **Input validation — nested arrays**: `update-post-meta` now explicitly rejects nested arrays in meta values.
- **XSS — gateway reply sanitization**: Chat reply from the gateway is validated as a string and sanitized with `wp_kses_post()` before being returned.
- **Rate limiting — Retry-After headers**: All 429 responses now include a `Retry-After: 60` header.
- **Rate limiting — status endpoint**: `handle_status` REST endpoint now has rate limiting.
- **Rate limiting — test connection cooldown**: AJAX test connection endpoint has a 5-second per-user cooldown transient.
- **Rate limiting — configurable limit**: `check_rate_limit()` now reads from `wp_pinch_rate_limit` option instead of a hardcoded constant.
- **Rate limiting — broken link batch size**: `wp_pinch_broken_links_batch_size` filter output clamped to `max 200` via `min( absint(...), 200 )`.
- **Broken link checker — sslverify**: Changed `sslverify => false` to `sslverify => true`.
- **Bulk delete safety**: Bulk edit `delete` action now uses `wp_trash_post()` instead of `wp_delete_post( $id, true )`.
- **Constant redefinition guard**: All `define()` calls in `wp-pinch.php` wrapped with `defined() ||` guards.
- **GDPR data export**: Added missing `context` column to personal data export.
- **Multisite cleanup**: `uninstall.php` now iterates all sites on multisite networks via `get_sites()` / `switch_to_blog()`.

### Fixed

- **Chat block sessionStorage instability**: Replaced `wp_unique_id()` with a persistent `blockId` attribute that is generated once when the block is inserted in the editor and stored in post content, ensuring session messages survive page reloads.

## [1.0.2] - 2026-02-11

### Fixed
- Admin settings page 404 for admin.js/admin.css when running from source — added FAQ entry documenting the `npm run build` requirement.

### Changed
- Release process in CONTRIBUTING.md now documents the `make zip` step so release packages include built assets.

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

[Unreleased]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.2...v2.0.0
[1.0.2]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/RegionallyFamous/wp-pinch/releases/tag/v1.0.0
