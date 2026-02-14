=== WP Pinch ===
Contributors: regionallyfamous
Tags: ai, agent, openclaw, mcp, automation
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 2.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress in your pocket. Your AI assistant runs it from the chat you never leave — 38+ abilities, Pinch Chat block, webhooks, 8 governance tasks. Self-hosted. (We gave the AI the keys; we gave it a bouncer too.)

== Description ==

**Your AI assistant already lives in WhatsApp, Slack, Telegram — give it WordPress.**

One plugin. [OpenClaw](https://github.com/openclaw/openclaw) (or any MCP client) gets 38+ abilities: publish, Molt (one post → nine formats), PinchDrop, What do I know, daily Tide Report. Pinch Chat block, webhooks, 8 governance tasks. Self-hosted. Your data. No extra logins, no "I'll do it at my desk" — you talk; your assistant has the keys. [Install & connect →](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration)

= 38 AI Abilities =

Your assistant gets the keys to WordPress — posts, media, users, comments, settings, plugins, themes, WooCommerce — with capability checks, sanitization, and audit logging on every call. Plus PinchDrop, Ghost Writer, Molt, What do I know, site-digest, governance. 2 bonus WooCommerce abilities when your shop is active.

= Live Chat Block =

Drop an AI chat widget on any page with the Pinch Chat Gutenberg block. SSE streaming for real-time responses. Slash commands. Message feedback. Token tracking. Markdown rendering. Public chat mode for anonymous visitors. Per-block agent overrides — every page can have its own AI personality. Dark mode. WCAG 2.1 AA accessible. Mobile responsive.

= Autonomous Governance =

Eight background tasks patrol your site on autopilot: content freshness, SEO health, comment cleanup, broken link detection, security scanning, draft necromancy, spaced resurfacing, and Tide Report (daily digest). Findings get delivered to OpenClaw automatically. Set it and forget it.

= Real-Time Webhooks =

Post published? Comment posted? WooCommerce order shipped? Events fire to OpenClaw the moment they happen. HMAC-SHA256 signed. Retry with exponential backoff. Circuit breaker for when the gateway goes down. Two-way — OpenClaw can push ability requests back to your site.

= PinchDrop (Capture Anywhere) =

Drop rough ideas from any OpenClaw channel and WP Pinch turns them into a Draft Pack automatically. Signed captures hit `/wp-pinch/v1/pinchdrop/capture`, then `pinchdrop_generate` produces blog-post, product-update, changelog, and social drafts with request-level traceability.

= Molt (Content Repackager) =

One post, nine formats. Molt repackages a single post into social (Twitter, LinkedIn), email snippet, FAQ block, thread, summary, meta description, pull quote, key takeaways, and CTA variants. Use `/molt 123` in chat or the `wp-pinch/molt` ability. Enable via the `molt` feature flag.

= Built for Developers =

12+ filters and 6+ actions. Full WP-CLI support. Customize abilities, webhook payloads, governance schedules, role allowlists, and more. If you can hook it, you can pinch it. (We like wordplay. It's in the name. So is the lobster.)

= Production-Ready Security =

Capability checks on every operation. Input sanitization. Output escaping. Nonce verification. Prepared SQL. HMAC-SHA256 webhook signatures. Circuit breaker. Rate limiting. PHPStan Level 6. 160+ PHPUnit tests. See the [GitHub Wiki](https://github.com/RegionallyFamous/wp-pinch/wiki/Security) for the full security model.

== Installation ==

1. Upload the `wp-pinch` folder to `/wp-content/plugins/`.
2. Ensure WordPress 6.9+ is installed with the Abilities API active.
3. Install the MCP Adapter plugin for full MCP integration.
4. Activate WP Pinch from the Plugins page.
5. Go to the **WP Pinch** menu in your admin sidebar.
6. Enter your OpenClaw Gateway URL and API Token.
7. Click **Test Connection** to verify.
8. Configure webhook events and governance tasks to your liking.
9. Sit back and let the lobster do the work.

**Next:** [Configuration (wiki)](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration) for webhooks, governance, and Pinch Chat.

= Compatibility =

WP Pinch works with any MCP-compatible client. Connect OpenClaw (or similar) via the MCP URL below. Tested with WordPress 6.9+ and PHP 8.1+. For a minimal setup guide, see the wiki: [OpenClaw Quick Start](https://github.com/RegionallyFamous/wp-pinch/wiki/OpenClaw-Quick-Start).

= Connecting OpenClaw =

Point OpenClaw at your site's MCP endpoint:

`npx openclaw connect --mcp-url https://your-site.com/wp-json/wp-pinch/v1/mcp`

OpenClaw will discover the available abilities and begin routing messages from your configured channels to your WordPress site.

= From Source =

`git clone https://github.com/RegionallyFamous/wp-pinch.git`
`cd wp-pinch && composer install && npm install && npm run build`
`wp plugin activate wp-pinch`

== Frequently Asked Questions ==

= Does this require OpenClaw? =

OpenClaw is recommended for the full experience (webhooks, chat, governance delivery), but the Abilities API and MCP server work with any MCP-compatible client. WP Pinch is the lobster; OpenClaw is the ocean. You can technically have a lobster without an ocean, but it's way less impressive.

= What WordPress version is required? =

6.9 or later. That's when WordPress grew its Abilities API claws.

= Does this work with WooCommerce? =

Yes! WooCommerce order status changes trigger webhooks, and WP Pinch adds two bonus abilities for listing products and managing orders. Your AI agent can check inventory faster than a lobster can snap a rubber band.

= Can I add custom abilities? =

Absolutely. Use the `wp_pinch_register_abilities` action or the `wp_pinch_abilities` filter:

`add_filter( 'wp_pinch_abilities', function ( array $abilities ): array {
    $abilities['my_custom_ability'] = [ ... ];
    return $abilities;
} );`

If you can dream it, you can register it.

= Is the API token stored securely? =

It's stored in the WordPress options table with `show_in_rest => false`. For production hardening, we recommend using environment variables or a secrets manager. The token never appears in REST API responses and is never exposed to non-admin users.

= Is it production-ready? =

WP Pinch passes PHPCS (WordPress-Extra + Security), PHPStan Level 6, and 160+ PHPUnit tests. Every ability has security guards, every input is sanitized, every output is escaped. Ship it with confidence.

= Why lobster puns? =

Because the alternative was crab puns, and that felt a little... sideways. Plus, OpenClaw. Claw. Lobster. Pinch. It was destiny.

== Screenshots ==

1. Settings page — Connection tab with gateway URL, API token, and test connection button.
2. Settings page — Webhooks tab with event toggles and rate limit configuration.
3. Settings page — Governance tab with task toggles and autonomous mode settings.
4. Pinch Chat block in the Gutenberg editor with live preview.
5. Audit log showing recent ability executions, webhooks, and chat messages.

== Changelog ==

= 2.5.0 =
* New: Block Bindings API for Pinch Chat (agentId and placeholder bound to post meta or site options). Requires WordPress 6.5+.
* New: Default Chat Placeholder setting for site-wide placeholder text.
* New: Pinch Chat block supports typography.fontSize and dimensions.minHeight in the editor.
* New: wp_pinch_block_type_metadata filter for themes/plugins to modify block registration.
* Changed: REST route checks use str_starts_with (PHP 8). Ability cache flush wrapped in try-catch for object cache backends that don't support group flush.
* Docs: Recipes/FAQ/Configuration links, Limits (execute_batch cap, GET /abilities), Health & status endpoints, trace ID, execute_batch recipe, wizard "Try this first," OpenClaw-Skill as default path.
* Fixed: PHPCS empty catch in ability cache (no-op added for backends without group flush).

= 2.4.2 =
* New: First-run wizard — step indicator, copy buttons for MCP URL and CLI command, Test Connection spinner and a11y. Wizard CSS/JS in admin assets.
* New: Settings UI — Connection tab cards (Gateway & API, Webhook, Chat, PinchDrop). Audit log empty state, sticky table header. Design tokens in admin and block.
* New: Pinch Chat — focus-visible outlines, "Scroll to bottom" when new message arrives off-screen, same-sender message grouping, design tokens and dark-mode contrast.
* New: "Settings saved" admin notice after saving options.
* Changed: Inline styles removed from wizard and audit; abilities table and circuit status in CSS. Audit page clamped to at least 1.
* Security: Wizard loading state uses DOM APIs only (no innerHTML with translated text). Test Connection bound only when wpPinchAdmin.ajaxUrl exists.
* Fixed: Lint (PHPCS, ESLint, Stylelint) and escaping.

= 2.4.1 =
* New: CodeQL and dependency review workflows. CONTRIBUTING with E2E/load testing notes.
* Changed: Editorconfig, issue/PR templates, CODEOWNERS. PHPUnit 11, Composer PHP 8.2. PHPCS fixes.

= 2.4.0 =
* New: Quick Drop — PinchDrop option `save_as_note: true` creates a minimal post (title + body only). Lightweight capture from any channel.
* New: Memory Bait — ability `wp-pinch/site-digest` returns recent posts with title, excerpt, and taxonomy terms for agent memory or system prompt.
* New: Tide Report — daily governance task that bundles content freshness, SEO, comments, and (optionally) draft necromancer into one webhook payload.
* New: Echo Net — ability `wp-pinch/related-posts` returns backlinks and posts that share taxonomy terms for a given post ID.
* New: Weave — ability `wp-pinch/synthesize` searches posts and returns a payload for LLM synthesis (first draft; human refines).
* Governance: 7 tasks total; Tide Report added. Shared helpers for findings used by individual tasks and Tide Report.

= 2.3.1 =
* Doc: README and readme.txt now say "Six reasons to install WP Pinch" and list six governance tasks (including draft necromancy).

= 2.3.0 =
* New: Ghost Writer — AI voice profile engine that learns each author's writing style from their published posts. Tone, vocabulary, structural habits, quirks — all captured in a per-author profile.
* New: Ghost Writer — draft completion. Resurrects abandoned drafts in the original author's voice via OpenClaw. Your draft graveyard just got a necromancer.
* New: /ghostwrite slash command — type /ghostwrite in chat to see abandoned drafts, or /ghostwrite 123 to resurrect one.
* New: Draft Necromancer governance task — weekly scan for abandoned drafts worth resurrecting, delivered via webhook.
* New: 3 new abilities (analyze-voice, list-abandoned-drafts, ghostwrite) behind ghost_writer feature flag.
* New: ghost_writer feature flag (disabled by default) — enable to unlock the full Ghost Writer system.
* New: Ghost Writer threshold setting — configurable abandoned draft age (default 30 days).
* New: PinchDrop (Capture Anywhere) pipeline — signed inbound capture endpoint (`/wp-pinch/v1/pinchdrop/capture`) with idempotency by `request_id`, source allowlist support, and draft-pack generation.
* New: `pinchdrop_generate` ability to build structured draft packs and optionally persist draft posts with capture metadata (`source`, `author`, `request_id`, capture timestamp).
* New: PinchDrop settings (`wp_pinch_pinchdrop_enabled`, default outputs, auto-save drafts, allowed sources) and `pinchdrop_engine` feature flag.
* New: Per-block agent override — new agentId attribute lets individual chat blocks target different OpenClaw agents.
* New: Slash commands — /new, /reset, /status, and /compact in the chat input (behind slash_commands feature flag). For power users who type faster than a lobster snaps.
* New: Message feedback — thumbs up/down buttons on assistant messages.
* New: Token usage display — tracks and shows token consumption from X-Token-Usage headers (behind token_display feature flag). Know exactly how many tokens your lobster is eating.
* New: Session reset endpoint (/session/reset) for starting fresh conversations.
* New: Incoming webhook receiver (/hook) — lets OpenClaw push ability execution requests back to WordPress with HMAC-SHA256 verification. The trap now works both ways.
* New: SSE streaming in chat block — real-time character-by-character responses with animated cursor indicator.
* New: 3 new feature flags (public_chat, slash_commands, token_display) for 10 total.
* New: 14 new admin settings (agent ID, webhook channel/recipient/delivery/model/thinking/timeout, chat model/thinking/timeout, session idle minutes, and more).
* New: Model and thinking overrides on chat endpoints.
* New: Fetch retry with exponential backoff in the chat block.
* New: Session persistence via sessionStorage keyed by block ID.
* Security: Per-post capability checks on get-post-meta and update-post-meta abilities — now verifies current_user_can( 'edit_post', $post_id ).
* Security: Uninstall cleanup expanded — all 24 registered options now deleted on uninstall.
* Security: Public chat endpoint isolation with separate rate limiting and session key validation.
* Improved: WCAG 2.1 AA prefers-reduced-motion support — disables all animations when requested.
* Improved: WCAG 2.1 AA forced-colors (Windows High Contrast Mode) support with system color keywords.
* Improved: Full dark mode coverage for all new UI elements.
* Improved: Block editor sidebar controls for public mode toggle and agent ID override.
* Fixed: Chat block session key initialization for authenticated and public users.
* Fixed: Streaming endpoint properly scoped to authenticated users only.

= 2.1.0 =
* New: Circuit breaker for gateway calls — fails fast when gateway is down, auto-recovers with half-open probe.
* New: Feature flags system — 7 toggleable features with admin UI and filter override.
* New: SSE streaming chat endpoint for real-time responses (behind feature flag).
* New: Public health check endpoint (/wp-pinch/v1/health) — no auth required.
* New: HMAC-SHA256 webhook signatures with replay protection.
* New: Admin ability toggle — disable individual abilities from the UI.
* New: Admin feature flags tab with circuit breaker status.
* New: Audit log search, date filtering, and CSV export.
* New: Rate limit headers (X-RateLimit-Limit/Remaining/Reset) on all REST responses.
* New: Chat nonce auto-refresh on 403 — prevents stale-tab failures.
* New: Chat character counter (4,000 max) with visual warnings.
* New: Clear chat button to reset conversation.
* New: Copy-to-clipboard button on assistant messages.
* New: Markdown rendering in assistant replies (bold, italic, code, links).
* New: Typing indicator animation (bouncing dots) while waiting.
* New: Keyboard shortcuts — Escape to clear input.
* New: Admin notice when circuit breaker is open.
* New: WP-CLI --format=json/csv/yaml support for all list commands.
* New: Upgrade notice on Plugins page for major version bumps.
* New: Object cache support for ability caching (Redis/Memcached).
* New: i18n POT generation (make i18n).
* New: k6 load testing script.
* New: PHPUnit tests for Circuit_Breaker and Feature_Flags.
* New: React error boundary in chat block.
* Improved: Dark mode CSS with full coverage of all new UI elements.
* Fixed: PHP 8.2+ compatibility (mb_strtoupper, no dynamic properties).

= 2.0.0 =
* **Major security hardening release** — 38 fixes across 12 files addressing access control, privilege escalation, IDOR, SSRF, information disclosure, PII redaction, input validation, XSS, and rate limiting.
* Fixed: Chat block sessionStorage instability — messages now persist across page reloads via a stable `blockId` attribute.
* Security: Post-level capability checks added to update-post, delete-post, list-posts, and search-content.
* Security: Administrator role assignment unconditionally blocked; roles with dangerous capabilities (manage_options, edit_users, etc.) also blocked.
* Security: Administrator downgrade prevention.
* Security: Session key always derived from authenticated user — prevents cross-user session hijacking.
* Security: Arbitrary post deletion via menu item delete prevented with post type validation.
* Security: SSRF prevention in broken link checker (private IP blocking, DNS resolution, SSL verification).
* Security: Media upload restricted to HTTP/HTTPS URL schemes only.
* Security: Cron "run" action validates hook exists in cron array before firing; core cron hooks protected from deletion.
* Security: Hardcoded option denylist (auth salts, active_plugins, users_can_register, default_role, API token) enforced before filters.
* Security: User emails removed from all ability responses (list-users, get-user, export-data, WooCommerce orders).
* Security: WooCommerce order PII redacted (billing email, last name, payment method title, note authors).
* Security: Comment sweep PII redacted (author, content excerpt).
* Security: Webhook user_register payload no longer includes email.
* Security: API token masked on settings page; gateway errors logged server-side with generic client messages.
* Security: MCP server no longer exposes get-user-info or get-environment-info publicly.
* Security: Security scan no longer leaks version numbers for core/plugins/themes.
* Security: Message length limit (4,000 chars), nested array rejection in post meta, gateway reply XSS sanitization.
* Security: Retry-After headers on all 429 responses; status endpoint rate-limited; test connection cooldown; configurable rate limit.
* Security: Bulk delete now trashes posts instead of permanent deletion.
* Security: Constant redefinition guards, GDPR export completeness, multisite uninstall cleanup.
* Breaking: User email removed from ability responses. Bulk delete uses trash. admin_email removed from option read allowlist. wp_pinch_blocked_roles filter default changed.

= 1.0.2 =
* Fixed: Admin settings page 404 for admin.js/admin.css when running from source — documented build requirement (npm run build) in FAQ.
* Changed: Release process now documents `make zip` step so release packages include built assets.

= 1.0.1 =
* Fixed: Screen reader announcements now work on the frontend (wp-a11y enqueued for Pinch Chat block).
* Fixed: Session storage scoped per block instance — no more message cross-contamination with multiple chat blocks.
* Fixed: Focus ring visible in Windows High Contrast Mode (transparent outline trick).
* Fixed: Message ID collisions resolved with monotonic counter.
* Fixed: DOM queries scoped to block instance for scrollToBottom/focusInput.
* Fixed: Graceful handling of non-JSON server responses in chat.
* Fixed: Proper attribute escaping via wp_interactivity_data_wp_context().
* Fixed: Late escaping on chat input placeholder.
* Fixed: Added Update URI header to prevent update hijacking.
* Fixed: Numerous PHPCS and PHPStan violations (security, escaping, type safety).
* Changed: Comprehensive README and readme.txt rewrite with full documentation.
* Added: PHPStan Level 6 static analysis with WordPress stubs.
* Added: PHPCS configuration with WordPress-Extra and Security rulesets.
* Added: Pre-commit Git hook and make check quality gate.

= 1.0.0 =
* Initial release — the lobster has landed.
* 34 WordPress abilities across 9 categories (content, media, taxonomies, users, comments, settings, plugins/themes, analytics, advanced).
* 2 bonus WooCommerce abilities (product listing, order management).
* Custom MCP server registration with ability curation.
* Webhook dispatcher with exponential backoff retry (4 attempts) and fixed-duration rate limiting.
* Autonomous governance engine with 5 recurring tasks (content freshness, SEO health, comment sweep, broken links, security scanning).
* Pinch Chat Gutenberg block with Interactivity API, session persistence, screen reader support, dark mode, and high-contrast accessibility.
* WP-CLI commands for status, webhook testing, governance, audit log, and abilities.
* Comprehensive audit log with 90-day auto-retention and admin UI.
* GDPR-ready privacy tools (data export and erasure).
* Site Health integration (gateway connectivity + configuration checks).
* Full security suite: capability checks, input sanitization, output escaping, nonce verification, prepared SQL, option allowlists, role escalation prevention, self-deactivation guard, CSS injection prevention.
* 160+ PHPUnit tests, PHPStan Level 6, PHPCS WordPress-Extra + Security.
* 12+ developer filters and 6+ action hooks for extensibility.

== Upgrade Notice ==

= 2.5.0 =
Block Bindings for Pinch Chat (bind agentId/placeholder to post meta or options), default placeholder setting, block supports (font size, min height), and wp_pinch_block_type_metadata filter. Doc refresh: Recipes, Limits, health/status, trace ID, wizard "Try this first." No breaking changes.

= 2.4.2 =
UI polish: first-run wizard (step indicator, copy buttons, Test Connection spinner), settings cards and audit empty state, Pinch Chat focus and scroll-to-bottom. Lint and security hardening. No breaking changes.

= 2.4.1 =
CI and docs: CodeQL, dependency review, CONTRIBUTING. PHPUnit 11, PHPCS fixes. No breaking changes.

= 2.4.0 =
Feature release: Quick Drop (save as note), Memory Bait (site-digest), Tide Report (daily digest webhook), Echo Net (related-posts), Weave (synthesize). Five new capabilities; seven governance tasks. No breaking changes from 2.3.1.

= 2.3.1 =
Documentation update: "Six reasons" and six governance tasks in README/readme.txt. No code changes from 2.3.0.

= 2.3.0 =
Feature release: Ghost Writer learns your writing voice and resurrects abandoned drafts. New /ghostwrite slash command, Draft Necromancer governance task, 3 new abilities, and the ghost_writer feature flag. Also includes PinchDrop Capture Anywhere with signed inbound capture, idempotency, source allowlisting, and structured draft-pack generation. No breaking changes from 2.2.0.

= 2.1.0 =
Feature release: Circuit breaker, feature flags, webhook signatures, SSE streaming, health endpoint, admin ability toggle, audit log search/export, chat UX overhaul (character counter, clear chat, copy button, Markdown, typing indicator, nonce refresh, keyboard shortcuts), WP-CLI format support, rate limit headers, and much more. No breaking changes from 2.0.0.

= 2.0.0 =
Major security hardening: 38 fixes for access control, privilege escalation, SSRF, PII exposure, XSS, and more. Breaking changes: user emails removed from ability responses, bulk delete now trashes, administrator role unconditionally blocked. Please review the full changelog before upgrading.

= 1.0.2 =
Documentation fix: FAQ for admin.js/admin.css 404 when running from source, plus release process updates.

= 1.0.1 =
Bug fixes for accessibility, multi-block support, and security hardening. The lobster got sharper claws.

= 1.0.0 =
Initial release. Welcome to the lobster pot. Grab your claws and get started.

== Credits and Acknowledgments ==

WP Pinch is built on many open-source projects. We are grateful to all of their maintainers and contributors — the unsung lobsters of the open-source ocean.

= WordPress and Automattic =

* [WordPress](https://wordpress.org/) — The CMS that makes it all possible. A registered trademark of the WordPress Foundation.
* [Abilities API](https://developer.wordpress.org/) — The WordPress 6.9 API enabling plugins to register AI-accessible capabilities.
* [MCP Adapter](https://github.com/WordPress/mcp-adapter) — Bridges WordPress abilities to the Model Context Protocol.
* [Gutenberg](https://github.com/WordPress/gutenberg) and the Block Editor — Foundation for the Pinch Chat block.
* [Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/) — Powers the reactive chat interface.
* [Action Scheduler](https://actionscheduler.org/) by WooCommerce (Automattic) — Reliable background task execution. Licensed under GPL-3.0-or-later.
* [Jetpack Autoloader](https://github.com/Automattic/jetpack-autoloader) by Automattic — Prevents version conflicts. Licensed under GPL-2.0-or-later.
* [@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/), [@wordpress/env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/), [@wordpress/interactivity](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-interactivity/) — Build tools, development environment, and client-side runtime.

= Other Open-Source Projects =

* [OpenClaw](https://github.com/openclaw/openclaw) — The personal AI assistant (WhatsApp, Slack, Telegram, etc.) that uses WP Pinch as its WordPress tool.
* [WP-CLI](https://wp-cli.org/) — Command-line interface for WordPress. Licensed under MIT.
* [PHPUnit](https://phpunit.de/) — PHP testing framework. Licensed under BSD-3-Clause.
* [PHPStan](https://phpstan.org/) — Static analysis tool. Licensed under MIT.
* [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) — PHPCS rules for WordPress. Licensed under MIT.

= Links =

* [Website](https://wp-pinch.com) — Official plugin site with documentation and installation guides.
* [GitHub](https://github.com/RegionallyFamous/wp-pinch) — Source code, issues, and releases.
