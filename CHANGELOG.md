# Changelog

All notable changes to WP Pinch will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.0.3] - 2026-02-18

### Added
- **New system/admin abilities** — transient CRUD (`get-transient`, `set-transient`, `delete-transient`), rewrite inspection/flush (`list-rewrite-rules`, `flush-rewrite-rules`), maintenance mode status/toggle (`maintenance-mode-status`, `set-maintenance-mode`), guarded scoped DB replace (`search-replace-db-scoped`), language pack management (`list-language-packs`, `install-language-pack`, `activate-language-pack`), plugin/theme lifecycle management (`manage-plugin-lifecycle`, `manage-theme-lifecycle`), expanded user/comment operations (`create-user`, `delete-user`, `reset-user-password`, `create-comment`, `update-comment`, `delete-comment`), and media thumbnail regeneration (`regenerate-media-thumbnails`).

### Changed
- **Ability docs and counts** — docs updated to reflect current ability inventory (`88` core abilities, `94` total when WooCommerce + Ghost Writer + Molt are enabled).
- **Security hardening** — destructive scoped DB search/replace now requires explicit confirmation when `dry_run` is false; user creation blocks roles with dangerous capabilities; maintenance marker removal prefers `wp_delete_file()` when available.

### Fixed
- **Testing and packaging reliability** — stabilized Playwright settings/chat flows for wizard and editor sidebar state, switched Composer `test` to the wp-env PHPUnit path, and hardened Plugin Check execution path used in CI and local runs.
- **Translation timing notice** — removed early `_load_textdomain_just_in_time` warnings by deferring translation calls that could fire before `init`.

## [3.0.2] - 2026-02-11

### Added
- **Molt output types** — `newsletter` (blog-to-newsletter: subject line + 2–4 paragraph body for email) and `sections` for content repackaging.
- **New abilities** — `analytics-narratives`, `suggest-seo-improvements`, and `submit-conversational-form` registered and exposed via abilities API and governance.
- **Governance** — **Semantic Content Freshness** task; governance schedule re-evaluated when settings or plugin version change (hash-based).
- **AI Dashboard** — New settings tab (first tab, default view) for at-a-glance AI and connection status.

### Changed
- **PHPCS** — All inline `phpcs:ignore` / disable tags removed; rule exclusions moved to `phpcs.xml.dist` (DB, nonce, file functions, escape output, etc.). Code adjusted for translator comments, Security_Scan variable, Helpers loop, empty catches. PHPCS test suite added; `tests/bootstrap.php` excluded.
- **Tests** — Action Scheduler added as dev dependency; `tests/bootstrap.php` loads Action Scheduler before WordPress. All `markTestSkipped` calls removed; suite runs 300 tests with 0 skipped. `E_USER_NOTICE` from Action Scheduler initialization suppressed in test run for clean output.

### Fixed
- **WordPress test env** — `bin/install-wp-tests.sh` uses portable `sed -i` for macOS. Site health test expects `wordpress` key (not `WordPress`). Governance tests expect 9 tasks and `semantic_content_freshness` in task list.

## [3.0.0] - 2026-02-16

### Added
- **REST handler namespace** — Request handling moved from `Rest_Controller` into `includes/Rest/`: `Auth`, `Chat`, `Status`, `Incoming_Hook`, `Capture`, `Ghostwrite`, `Molt`, `Preview_Approve`, `Schemas`, `Helpers`, `Write_Budget`. Route registration and security/rate-limit headers remain in `class-rest-controller.php`. `Rest_Controller::DEFAULT_RATE_LIMIT` kept for backward compatibility; canonical constant is `Rest\Helpers::DEFAULT_RATE_LIMIT`.

### Changed
- **Settings registration** — Data-driven option registration: `Settings::get_option_definitions()` returns all option configs; `register_settings()` loops over definitions. Reduces repetition and keeps option list in one place.
- **Governance tests** — Tests updated to call `Governance\Tasks\Content_Freshness::run()`, `SEO_Health::run()`, `Comment_Sweep::run()` directly instead of removed `Governance::task_*()` methods.
- **Documentation** — Architecture, Developer-Guide, Test-Coverage, AGENTS.md, Security.md, FAQ.md, CONTRIBUTING.md updated for REST structure, 300+ tests, and settings refactor. Security doc: "all settings" for show_in_rest (no fixed count).

### Fixed
- **Governance PHPUnit** — Four tests no longer call undefined `Governance::task_content_freshness()`, `task_seo_health()`, `task_comment_sweep()`; they use the task class `run()` methods.

## [2.9.0] - 2026-02-15

### Added
- **Security hardening (Tier 1)** — Prompt sanitization extended to governance findings and webhook payloads; recursive sanitization for titles, excerpts, taxonomy terms. Webhook loop detection prevents infinite post-update loops. Kill switch (`wp_pinch_api_disabled`) and read-only mode (`wp_pinch_read_only_mode`); constants `WP_PINCH_DISABLED` and `WP_PINCH_READ_ONLY` in wp-config. Emergency mu-plugin drop-in example. Token logging hygiene: `Utils::mask_token()` for safe debug output. Option denylist expanded: `siteurl`, `home`, `admin_email` now blocked from get/update-option abilities.
- **Credential guidance** — Configuration and Security docs now recommend application passwords, secret reference pattern, and rotation. No full admin credentials.
- **OpenClaw role** — Dedicated `openclaw_agent` role with capability group picker. Create OpenClaw agent user; webhook execution uses designated user or first admin. Least-privilege by default.
- **Prompt sanitizer** — Feature flag `prompt_sanitizer` (default on) mitigates instruction injection in content sent to LLMs. Applied to Molt, Ghost Writer, synthesize. Filters: `wp_pinch_prompt_sanitizer_patterns`, `wp_pinch_prompt_sanitizer_enabled`.
- **Approval workflow** — Feature flag `approval_workflow` (default off). Destructive abilities (delete-post, toggle-plugin, switch-theme, etc.) queued for admin approval in WP Pinch → Approvals.
- **Block-native Molt** — New `faq_blocks` output type returns Gutenberg block markup for FAQs. create-post content schema documents block markup support.

### Changed
- **Incoming webhook** — Execution user is OpenClaw agent (if set) or first administrator. Migration 2.6.0 ensures role exists on upgrade.
- **SKILL.md v5.5.1** — Complete rewrite: marketing-forward tone with Quick Start, Highlights, and Built-in Protections. Fixed metadata format to single-line JSON per OpenClaw spec (resolves registry env var mismatch). Clarified credential architecture (auth secrets on MCP server, skill only needs WP_SITE_URL). MCP-only — removed all REST/curl fallback.

## [2.8.0] - 2026-02-14

### Added
- **OpenClaw Gateway Vision (Phase A)** — Capability manifest on GET `/abilities` (post types, taxonomies, plugins, features; filter `wp_pinch_manifest`). Audit enhancements: request/result summary and optional diff in audit context; audit UI event filter dropdown and Details column. Daily write budget: optional cap and email alert at threshold (Connection tab). Draft-first: `_wp_pinch_ai_generated` meta, `preview_url` in create/update-post responses, `POST /preview-approve` to publish from preview. Media in create-post: `featured_image_url`, `featured_image_base64`, `featured_image_alt`. Health/diagnostics in status (for admins): plugin/theme update counts, PHP version, DB size, disk, cron, error log tail.
- **OpenClaw Gateway Vision (Phase B)** — Content health report ability (`content-health-report`: missing alt, broken internal links, thin content, orphaned media). Suggest terms ability (`suggest-terms`: categories/tags for draft by content similarity). Block JSON in create/update-post: optional `blocks` array (blockName, attrs, innerContent, innerBlocks). Strict gateway reply sanitization option: strip HTML comments and instruction-like lines, disallow iframe/object/embed/form in chat replies.
- **Tests** — PHPUnit coverage for Phase A/B: manifest, daily write 429, preview-approve, sanitize_gateway_reply, content-health-report, suggest-terms, create/update preview_url and blocks, audit diff/context, governance content health helpers.

### Documentation
- **Ability count** — Standardized to 48 core, 54 total (with WooCommerce, Ghost Writer, Molt) across README, wiki, SKILL, readme.txt.
- **SKILL v5.2** — ClawHub metadata (homepage, user-invocable, openclaw.emoji, changelog), Setup section (Which site? with WP_SITE_URL), error handling, security note, preview-approve.
- **ClawHub install** — `clawhub install nickhamze/pinch-to-post` added to README, Configuration, OpenClaw-Skill.
- **Don't have OpenClaw yet?** — 3-step quickstart in Configuration wiki.
- **Action Scheduler FAQ** — Why required, what works without it; link from Requirements.
- **Recommended features** — Section in Configuration (streaming, slash commands, Molt, Ghost Writer, public chat).
- **FAQ troubleshooting** — Connection test fails, Chat block no response, Governance not running, Webhooks not firing.
- **Install notes** — GitHub distribution (not wordpress.org); MCP command syntax may vary (link to OpenClaw CLI).

## [2.7.0] - 2026-02-14

### Added
- **Autoload audit** — Migration 2.7.0 sets `autoload=no` on all WP Pinch options to reduce options table bloat.
- **REST API disabled detection** — Rest_Availability class checks if the REST API is reachable (WP Pinch health endpoint); caches result for 5 minutes; shows admin notice when blocked. Added to Site Health status tests.
- **Activity feed dashboard widget** — WP Pinch Activity widget on the main dashboard shows the last 10 audit entries with a link to the full audit log.
- **Optimistic locking for update-post** — Ability accepts optional `post_modified` from get-post; rejects updates when the post has changed since last read. Returns `conflict: true` with current values for agent retry.
- **WAF/hosting troubleshooting doc** — New wiki page [Troubleshooting](wiki/Troubleshooting.md) covering REST API disabled, security plugins (Wordfence, Sucuri, iThemes), managed hosts, page cache exclusions, WAF whitelisting.
- **Classic Editor detection** — Filter `wp_pinch_preferred_content_format` returns `'blocks'` or `'html'`. Defaults to `'html'` when Classic Editor plugin is active and set to replace the block editor. Molt `faq_blocks` uses this so Classic Editor sites get HTML output.
- **Abilities API registration** — Registers `wp-pinch` category on `wp_abilities_api_categories_init` per [WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/). Polyfill for `wp_execute_ability` when WP 6.9 provides `wp_get_ability` but not `wp_execute_ability`.

### Changed
- **Memory-conscious governance** — All governance `get_posts()` calls now use `no_found_rows => true` (broken links, spaced resurfacing, content freshness, SEO health) to reduce memory usage on large sites.
- **Testing** — wp-env includes Action Scheduler; full suite (293 tests) runs without skips. New tests for Rest_Availability, Dashboard_Widget, Molt, OpenClaw_Role.

## [2.5.0] - 2026-02-13

### Added
- **Block Bindings API** — Pinch Chat block attributes `agentId` and `placeholder` can be bound to post meta (`wp_pinch_chat_agent_id`, `wp_pinch_chat_placeholder`) or site options via custom sources `wp-pinch/agent-id` and `wp-pinch/chat-placeholder`. Requires WordPress 6.5+.
- **Default Chat Placeholder** — New setting `wp_pinch_chat_placeholder` in Chat Settings for site-wide placeholder text.
- **Block supports** — Pinch Chat block now supports `typography.fontSize` and `dimensions.minHeight` in the editor.
- **`wp_pinch_block_type_metadata` filter** — Themes/plugins can modify Pinch Chat block registration args.

### Changed
- **PHP** — Replaced `strpos` with `str_starts_with` in REST controller for route checks (PHP 8).
- **Ability cache** — Wrapped `wp_cache_flush_group` in try-catch for object cache backends that do not support group flushing.
- **Docs** — WooCommerce ability count corrected to 2 (code matches); governance task count corrected to 8 (added spaced resurfacing); Developer Guide Future Enhancements section (DataViews); Recipes, Limits, Health/status, trace ID, execute_batch recipe, Integration-and-Value; wizard "Try this first" and OpenClaw-Skill links.

### Fixed
- **PHPCS** — Empty catch statement in ability cache flush; added explicit no-op so object-cache backends that don't support group flush are handled without triggering lint.

## [2.4.2] - 2026-02-13

### Added
- **First-run wizard** — Step indicator (Step 1 of 3), copy buttons for MCP URL and CLI command with "Copied!" feedback, Test Connection loading state (spinner) and a11y (aria-live, aria-busy). Wizard CSS/JS moved into admin styles and script.
- **Settings UI** — Connection tab grouped into cards (Gateway & API, Webhook defaults, Chat Settings, PinchDrop). Audit log: friendly empty state, sticky table header, filters bar styles in CSS. "What can I do?" and MCP info box styles in admin.css. Design tokens (primary, radius, spacing) in admin and block.
- **Pinch Chat block** — Focus-visible outlines on interactive elements; "Scroll to bottom" control when user has scrolled up and a new message arrives; tighter gap between consecutive same-sender messages; design tokens and contrast tweaks for dark mode.
- **Save feedback** — "Settings saved" admin notice when returning from options.php.

### Changed
- **Admin** — Inline styles removed from wizard and audit in favor of CSS classes. Abilities table checkbox column and circuit status margin in CSS. Audit pagination uses `max(1, ...)` so page is never 0.
- **Security** — Wizard loading state no longer uses innerHTML with translated strings (DOM APIs only). Settings Test Connection only bound when wpPinchAdmin.ajaxUrl is present.

### Fixed
- Lint: PHPCS (translators comments, array alignment, escaped step indicator), ESLint (unused vars, navigator global, Prettier), Stylelint (selector order, duplicate selector, empty-line-before).

## [2.4.1] - 2026-02-13

### Added
- **CodeQL workflow** — SAST (static application security testing) on PHP for every push and PR.
- **Dependency review workflow** — PRs that add dependencies with known vulnerabilities are blocked.
- **CONTRIBUTING** — E2E (Playwright) and load testing (k6) instructions; dependencies and license compliance note; CI pipeline list updated.

### Changed
- **Best practices** — `.editorconfig`, issue/PR templates, CODEOWNERS, PHPUnit coverage docs, `make test-coverage`; `class-abilities.php` docblock updated (38+ core, 2 WooCommerce); npm audit step in CI documented as non-blocking.
- **Dependencies** — npm packages updated; PHPUnit 9 → 11, Composer platform PHP 8.2.
- **Lint** — PHPCS Yoda condition and quote fixes in `class-rest-controller.php` and `class-settings.php`.

## [2.4.0] - 2026-02-12

### Added
- **Quick Drop (save as note)** — PinchDrop option `options.save_as_note: true` skips AI expansion and creates a minimal post (title + body only, no blocks). Channel-accessible lightweight capture; fits the Capture pillar.
- **Memory Bait (site-digest)** — New ability `wp-pinch/site-digest`: compact export of recent N posts with title, excerpt, and key taxonomy terms. For OpenClaw memory-core or system prompt so the agent knows your site.
- **Tide Report** — New daily governance task that bundles findings (content freshness, SEO health, comment sweep, draft necromancer when Ghost Writer is on) into one webhook payload. Delivers "here's what needs attention" to Slack/Telegram.
- **Echo Net (related-posts)** — New ability `wp-pinch/related-posts`: given a post ID, returns posts that link to it (backlinks) or share taxonomy terms. Enables "you wrote about X before" and graph-like discovery.
- **Weave (synthesize)** — New ability `wp-pinch/synthesize`: given a query, search → fetch matching posts → return payload (title, excerpt, content snippet) for LLM synthesis. First-draft synthesis; human refines.
- Governance task count increased from 6 to 7 with `tide_report`; refactored content freshness, SEO health, comment sweep, and draft necromancer to use shared `get_*_findings()` helpers for Tide Report bundling.

### Changed
- Ability catalog now includes `wp-pinch/site-digest`, `wp-pinch/related-posts`, and `wp-pinch/synthesize`.
- PinchDrop capture payload accepts `options.save_as_note`; ability schema documents `save_as_note` for Quick Drop.

## [2.3.1] - 2026-02-12

### Changed
- Docs: README and readme.txt now say "Six reasons to install WP Pinch" and list six governance tasks (including draft necromancy) for consistency with the feature set.

## [2.3.0] - 2026-02-12

### Added
- **Ghost Writer — AI voice profile engine** — analyzes an author's published posts to learn their writing style (tone, vocabulary, structural habits, quirks) and stores a per-author voice profile in user meta. "You started this post 8 months ago. You were going somewhere good."
- **Ghost Writer — draft completion** — completes abandoned drafts in the original author's voice via OpenClaw. The `ghostwrite` ability sends the draft + voice profile to the gateway and returns AI-completed content. Does not auto-publish — the draft stays a draft.
- **Ghost Writer — abandoned draft scanner** — `list-abandoned-drafts` ability ranks drafts by resurrection potential (freshness + completion %) so the AI knows which ones are worth saving.
- **`/ghostwrite` slash command** — type `/ghostwrite` in chat to see your abandoned drafts, or `/ghostwrite 123` to resurrect a specific draft. The spirits are cooperative.
- **Draft Necromancer governance task** — weekly scan for abandoned drafts worth resurrecting, delivered via webhook like all other governance findings. Because even lobsters forget what they started writing.
- **3 new abilities** — `analyze-voice`, `list-abandoned-drafts`, `ghostwrite` (all gated behind `ghost_writer` feature flag).
- **`ghost_writer` feature flag** — ships disabled by default. Enable it to unlock the Ghost Writer system.
- **Ghost Writer REST endpoint** (`/wp-pinch/v1/ghostwrite`) — serves the `/ghostwrite` slash command with list and write actions.
- **Ghost Writer threshold setting** — configurable abandoned draft age threshold (`wp_pinch_ghost_writer_threshold`, default 30 days).
- **PinchDrop capture endpoint** — new signed inbound channel-agnostic endpoint at `/wp-pinch/v1/pinchdrop/capture` with payload validation, source allowlisting, and endpoint-specific rate limiting.
- **Idempotency controls** — duplicate suppression on PinchDrop captures via `request_id` caching; repeated requests return a deduplicated response without creating duplicate drafts.
- **`pinchdrop_generate` ability** — structured Draft Pack generation for `post`, `product_update`, `changelog`, and `social` output types with optional draft persistence.
- **PinchDrop settings and feature flag** — new settings for enable toggle, default outputs, auto-save drafts, and allowed sources; shipped behind `pinchdrop_engine`.
- **PinchDrop docs** — new wiki page (`PinchDrop`) and configuration/docs updates with payload contract and integration notes.

### Security
- Ghost Writer voice analysis requires `edit_others_posts` to analyze another author's voice — can only analyze your own by default.
- Ghostwrite ability enforces per-post `current_user_can( 'edit_post', $post_id )` before touching any draft.
- Voice profiles stored in user meta (not exposed via REST API).
- AI-generated content sanitized with `wp_kses_post()` before storage.
- Ghost Writer endpoint reuses existing rate limiting.
- Reused authenticated hook trust model (Bearer/OpenClaw token + optional HMAC timestamp signatures) for PinchDrop captures.
- Enforced source allowlist and bounded input sizes for capture payloads.

### Changed
- Ability catalog now includes `wp-pinch/analyze-voice`, `wp-pinch/list-abandoned-drafts`, `wp-pinch/ghostwrite`, and `wp-pinch/pinchdrop-generate`.
- Feature flag count increased from 11 to 12 with `ghost_writer`.
- Governance task count increased from 5 to 6 with `draft_necromancer`.
- Uninstall cleanup expanded to include `wp_pinch_ghost_writer_threshold` option and `wp_pinch_voice_profile` user meta.

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

## [2.1.0] - 2026-02-11

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

Comprehensive security hardening pass before public release.

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

[Unreleased]: https://github.com/RegionallyFamous/wp-pinch/compare/v3.0.3...HEAD
[3.0.3]: https://github.com/RegionallyFamous/wp-pinch/compare/v3.0.2...v3.0.3
[3.0.2]: https://github.com/RegionallyFamous/wp-pinch/compare/v3.0.0...v3.0.2
[3.0.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.9.0...v3.0.0
[2.9.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.8.0...v2.9.0
[2.8.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.7.0...v2.8.0
[2.7.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.5.0...v2.7.0
[2.5.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.4.2...v2.5.0
[2.4.2]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.4.1...v2.4.2
[2.4.1]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.4.0...v2.4.1
[2.4.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.3.1...v2.4.0
[2.3.1]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.3.0...v2.3.1
[2.3.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.2...v2.0.0
[1.0.2]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/RegionallyFamous/wp-pinch/releases/tag/v1.0.0
