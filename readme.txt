=== WP Pinch ===
Contributors: regionallyfamous
Tags: ai, agent, openclaw, mcp, automation
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The AI agent plugin that grabs your WordPress site with both claws. OpenClaw + WordPress integration via MCP, with 34 abilities, autonomous governance, and a chat block.

== Description ==

**WP Pinch turns your WordPress site into an AI-powered crustacean of productivity.**

It bridges WordPress and [OpenClaw](https://github.com/nicepkg/openclaw) — an open-source AI agent framework — exposing your site as a set of AI-accessible tools through the Abilities API and Model Context Protocol (MCP) introduced in WordPress 6.9.

The result? Manage your WordPress site from **WhatsApp, Telegram, Slack, Discord**, or any messaging platform OpenClaw supports. All self-hosted. All under your control. No third-party SaaS middlemen getting their claws on your data.

= Why "Pinch"? =

Because OpenClaw needed a WordPress plugin with grip. And because every good lobster knows: you don't just *touch* things — you **pinch** them. Hard. With precision. And then you manage their post meta.

= 34 WordPress Abilities =

We didn't stop at the appetizer. WP Pinch registers **34 core abilities** across 9 categories, plus 2 bonus WooCommerce abilities if you're running a shop. That's more abilities than a lobster has legs. (Lobsters have 10. We have 36. We win.)

* **Content** — Full CRUD on posts and pages (list, create, update, delete)
* **Media** — Upload, list, and manage media library items
* **Taxonomies** — Manage terms and browse taxonomies
* **Users** — List users, update roles (with escalation guards), view profiles
* **Comments** — Moderate, approve, trash, list (with privacy-safe output)
* **Settings** — Read and update allowlisted site options
* **Plugins & Themes** — Activate, deactivate, switch, list installed extensions
* **Analytics** — Site health diagnostics, recent activity, content search, data export
* **Advanced** — Menus, post meta, revisions, bulk edits, cron management

Every ability has built-in security guards: capability checks, input sanitization, existence validation, and audit logging. We don't let AI agents run around your site like unsupervised lobsters in a kitchen.

= Custom MCP Server =

A dedicated `wp-pinch` MCP endpoint curates which abilities are exposed to AI agents. Like a bouncer at a seafood restaurant — only the abilities you approve get past the velvet rope.

= Webhook Dispatcher =

Fires real-time events to OpenClaw when posts are published, comments posted, users register, or WooCommerce orders change. Includes exponential backoff retry (4 attempts) and fixed-duration rate limiting so your site doesn't get overwhelmed. Even lobsters pace themselves.

= Autonomous Governance =

Five recurring background tasks via Action Scheduler keep your site healthy without lifting a claw:

1. **Content Freshness** — flags stale posts (staler than yesterday's catch)
2. **SEO Health** — missing meta descriptions, short titles, images without alt text
3. **Comment Sweep** — spam, orphaned comments, and other bottom-feeders
4. **Broken Link Detection** — dead links lurking in your content
5. **Security Scanning** — suspicious plugin changes and available updates

= Pinch Chat Block =

A Gutenberg block built with the Interactivity API. Drop a reactive, accessible chat interface on any page. Your visitors talk to your AI agent right on your site. Real-time messages, session persistence, screen reader support, dark mode, high-contrast mode accessible. It's like giving your website a little chat window with claws.

= WP-CLI Commands =

For the terminal-dwelling lobsters among us:

* `wp pinch status` — Connection status, abilities, gateway health
* `wp pinch webhook-test` — Fire a test webhook
* `wp pinch governance run` — Trigger governance tasks manually
* `wp pinch audit list` — Browse audit log entries
* `wp pinch abilities list` — See all registered abilities

= Comprehensive Audit Log =

Every ability execution, webhook dispatch, governance finding, and chat message is logged with automatic 90-day retention. Nothing happens without leaving a trail. Even lobsters leave tracks on the ocean floor.

= Developer Extensible =

12+ filters and 6+ actions for customizing abilities, webhook payloads, governance, role allowlists, and more. If you can hook it, you can pinch it.

= Production-Ready Security =

Capability checks, input sanitization, output escaping, nonce verification, prepared SQL statements, option allowlists, role escalation prevention, self-deactivation guards, CSS injection prevention, fixed-duration rate limiting, and `show_in_rest => false` on all settings. It's as battle-tested as a lobster that survived the tank at Red Lobster.

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

WP Pinch passes PHPCS (WordPress-Extra + Security), PHPStan Level 6, and 120+ PHPUnit tests. Every ability has security guards, every input is sanitized, every output is escaped. Ship it with confidence.

= Why lobster puns? =

Because the alternative was crab puns, and that felt a little... sideways. Plus, OpenClaw. Claw. Lobster. Pinch. It was destiny.

== Screenshots ==

1. Settings page — Connection tab with gateway URL, API token, and test connection button.
2. Settings page — Webhooks tab with event toggles and rate limit configuration.
3. Settings page — Governance tab with task toggles and autonomous mode settings.
4. Pinch Chat block in the Gutenberg editor with live preview.
5. Audit log showing recent ability executions, webhooks, and chat messages.

== Changelog ==

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
* 120+ PHPUnit tests, PHPStan Level 6, PHPCS WordPress-Extra + Security.
* 12+ developer filters and 6+ action hooks for extensibility.

== Upgrade Notice ==

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

* [OpenClaw](https://github.com/nicepkg/openclaw) — The AI agent framework that makes the magic happen.
* [WP-CLI](https://wp-cli.org/) — Command-line interface for WordPress. Licensed under MIT.
* [PHPUnit](https://phpunit.de/) — PHP testing framework. Licensed under BSD-3-Clause.
* [PHPStan](https://phpstan.org/) — Static analysis tool. Licensed under MIT.
* [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) — PHPCS rules for WordPress. Licensed under MIT.
