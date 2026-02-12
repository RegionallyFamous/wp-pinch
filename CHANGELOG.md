# Changelog

All notable changes to WP Pinch will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.2.0] - 2026-02-12

### Added
- **Public chat mode** — unauthenticated visitors can chat with your AI agent. New `/chat/public` REST endpoint with strict rate limiting, gated behind the `public_chat` feature flag. Because even lobsters believe in open doors (as long as there's a bouncer).
- **Per-block agent override** — new `agentId` block attribute lets you point individual chat blocks at different OpenClaw agents. One block for sales, one for support, one for existential crustacean philosophy.
- **Slash commands** — `/new`, `/reset`, `/status`, and `/compact` in the chat input (gated behind `slash_commands` feature flag). For the power users who type faster than a lobster snaps.
- **Message feedback** — thumbs up/down buttons on assistant messages for collecting response quality signals.
- **Token usage display** — tracks and displays token consumption from `X-Token-Usage` response headers (gated behind `token_display` feature flag). Know exactly how many tokens your lobster is eating.
- **Session reset endpoint** (`/session/reset`) — generates a fresh session key for starting new conversations.
- **Incoming webhook receiver** (`/hook`) — lets OpenClaw push ability execution requests back to WordPress with HMAC-SHA256 verification. The lobster trap now works in both directions.
- **SSE streaming in chat block** — real-time character-by-character message streaming with animated cursor indicator. Watching AI type is oddly satisfying.
- **3 new feature flags** — `public_chat`, `slash_commands`, `token_display` join the existing 7 for 10 total toggleable features.
- **14 new settings** — agent ID, webhook channel/recipient/delivery toggle/model/thinking/timeout, chat model/thinking/timeout, session idle minutes, and more. All with `sanitize_callback` and `show_in_rest => false`.
- **Model and thinking overrides** — per-request `model` and `thinking` parameters on chat endpoints, plus global defaults in the admin.
- **Chat: Fetch retry with backoff** — `fetchWithRetry()` wrapper for resilient gateway communication.
- **Chat: Session persistence** — conversations survive page reloads via sessionStorage keyed by block ID.

### Security
- **Per-post capability checks on meta operations** — `execute_get_post_meta` and `execute_update_post_meta` now verify `current_user_can( 'edit_post', $post_id )` before reading or writing meta. Previously only checked `edit_posts` globally, which could allow cross-post meta access. (Flagged by `wordpress://security/capabilities` MCP resource.)
- **Uninstall data cleanup** — added 16 missing options to `uninstall.php` deletion list, ensuring complete data removal on plugin deletion. All 24 registered options are now cleaned up, along with transients, the audit table, user meta, and Action Scheduler jobs. (Flagged by `wordpress://plugins/structure` and `wordpress://tools/plugin-check` MCP resources.)
- **Public chat endpoint isolation** — separate endpoint with its own rate limiting, session key prefix (`wp-pinch-public-`), and regex validation to prevent session key injection.

### Improved
- **WCAG 2.1 AA: `prefers-reduced-motion` support** — all animations (pulse, typing bounce, streaming blink) and transitions are disabled when the user prefers reduced motion. Even lobsters respect vestibular preferences.
- **WCAG 2.1 AA: `forced-colors` (Windows High Contrast Mode)** — proper system color keywords (`ButtonText`, `Canvas`, `CanvasText`, `Highlight`, `ButtonFace`) for full visibility in high contrast mode. The CSS header already claimed this — now it actually delivers.
- **Dark mode coverage** — streaming cursor, feedback buttons, token usage display, and all new UI elements fully styled for dark mode.
- **Block editor controls** — new sidebar controls for public mode toggle and agent ID override.

### Fixed
- Chat block now correctly initializes session keys for both authenticated and public users.
- Streaming endpoint properly scoped to authenticated users only (not available in public chat mode).

## [2.1.0] - 2026-02-12

### Added
- **Circuit breaker** for gateway calls — fails fast when gateway is down, auto-recovers after 60s cooldown with half-open probe. Prevents hammering a dead gateway.
- **Feature flags system** — boolean toggle for 7 features (`streaming_chat`, `webhook_signatures`, `circuit_breaker`, `ability_toggle`, `webhook_dashboard`, `audit_search`, `health_endpoint`). Stored in a single option, overridable via `wp_pinch_feature_flag` filter.
- **SSE streaming chat endpoint** (`/wp-pinch/v1/chat/stream`) — Server-Sent Events streaming for real-time chat responses. Gated behind `streaming_chat` feature flag.
- **Public health endpoint** (`/wp-pinch/v1/health`) — lightweight, no-auth health check returning version, config status, and circuit breaker state.
- **HMAC-SHA256 webhook signatures** — every outbound webhook includes `X-WP-Pinch-Signature` (v1=hex) and `X-WP-Pinch-Timestamp` headers with 5-minute replay protection.
- **Ability toggle admin tab** — disable individual abilities from the admin UI; disabled abilities are not registered or exposed via MCP.
- **Feature flags admin tab** — toggle features on/off from the admin UI with circuit breaker status display.
- **Audit log search and filtering** — full-text search, event type filter, source filter, date range picker.
- **Audit log CSV export** — download filtered audit entries as CSV (up to 10,000 rows).
- **Rate limit response headers** — `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` on all REST responses.
- **Chat: nonce auto-refresh** — on 403, the frontend fetches a fresh nonce and retries once, preventing stale-tab failures.
- **Chat: character counter** — shows remaining characters (out of 4,000) with warning at 200 and red at 0.
- **Chat: clear chat button** — resets conversation and session storage.
- **Chat: copy-to-clipboard** — hover copy button on assistant messages.
- **Chat: Markdown rendering** — basic safe subset (bold, italic, code, links, newlines) in assistant replies.
- **Chat: typing indicator** — animated bouncing dots while waiting for a response.
- **Chat: keyboard shortcuts** — Escape clears the input field.
- **Admin notice** when circuit breaker is open/half-open — warns administrators with retry countdown.
- **WP-CLI structured output** — `--format=json/csv/yaml` on `status`, `abilities list`, `governance list`, and `audit list`. Status now shows circuit breaker state; abilities show enabled/disabled status.
- **Upgrade notice** on Plugins page — warns about breaking changes before major version updates.
- **Object cache support** for ability caching — uses `wp_cache_get`/`wp_cache_set` with `wp-pinch-abilities` group when Redis/Memcached is available.
- **i18n POT generation** — `make i18n` target generates translation template via WP-CLI.
- **k6 load testing script** (`tests/load/k6-chat.js`) — ramp-up, sustained, spike, and ramp-down stages with p95 thresholds.
- **PHPUnit tests** for `Circuit_Breaker` (14 tests) and `Feature_Flags` (17 tests).
- **React error boundary** in chat block — catches unhandled JS errors and shows a friendly fallback.
- **Languages directory** with `.gitkeep` for translation files.

### Changed
- Chat widget dark mode significantly improved — full coverage of all new UI elements (footer, copy button, typing dots, char counter, clear button, login notice).
- Webhook `dispatch()` now builds headers as an array and injects signature headers conditionally.
- Ability `register()` method checks `is_disabled()` before registration, skipping disabled abilities entirely.
- Ability cache now uses object cache group `wp-pinch-abilities` with `wp_cache_flush_group()` for invalidation when a persistent backend is available.
- `make zip` now includes `make i18n` step for translation-ready releases.
- `strtoupper()` replaced with `mb_strtoupper()` in audit table query for PHP 8.2+ compatibility.

### Fixed
- Register abilities on `wp_abilities_api_init` hook instead of `init` (required by WordPress 6.9 Abilities API).
- Reorder self-check before dangerous capabilities check in `update-user-role` for correct error messaging.
- Fix PHPUnit test failures: use non-privileged role in role-change tests, correct `DEFAULT_RATE_LIMIT` constant name, suppress expected `register_rest_route` notice, test API token sanitize callback behavior.
- Ignore PHPStan false positive for `build/admin.asset.php` (build artifact guarded by `file_exists()`).
- Pin Composer platform to PHP 8.1 for consistent cross-version dependency resolution in CI.
- Install subversion in CI for WordPress test suite installation.

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

[Unreleased]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.2.0...HEAD
[2.2.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.2...v2.0.0
[1.0.2]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/RegionallyFamous/wp-pinch/releases/tag/v1.0.0
