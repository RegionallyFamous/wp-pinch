# WP Pinch

### The AI agent plugin that grabs your WordPress site with both claws.

[![WordPress 6.9+](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![CI](https://github.com/RegionallyFamous/wp-pinch/actions/workflows/ci.yml/badge.svg)](https://github.com/RegionallyFamous/wp-pinch/actions/workflows/ci.yml)

---

> *"In a world full of plugins that promise the ocean... this one actually delivers the whole lobster."*

---

## What is WP Pinch?

WP Pinch turns your WordPress site into an AI-powered crustacean of productivity. It bridges WordPress and [OpenClaw](https://github.com/nicepkg/openclaw) (an open-source AI agent framework), exposing your entire site as a set of AI-accessible tools through the **Abilities API** and **Model Context Protocol (MCP)** introduced in WordPress 6.9.

The result? You can manage your WordPress site from **WhatsApp, Telegram, Slack, Discord** -- or any messaging platform OpenClaw supports. All self-hosted. All under your control. No third-party SaaS middlemen getting their claws on your data.

Think of it as giving your WordPress site a brain, a mouth, and a pair of very capable pincers.

---

## Why "Pinch"?

Because OpenClaw needed a WordPress plugin with grip. And because every good lobster knows: you don't just *touch* things -- you **pinch** them. Hard. With precision. And then you manage their post meta.

Also, lobsters are immortal. Much like WordPress sites running on PHP 8.1.

---

## Features

### 34 WordPress Abilities (Yes, Thirty-Four)

We didn't stop at the appetizer. WP Pinch registers **34 core abilities** across 9 categories, plus 2 bonus WooCommerce abilities if you're running a shop. That's more abilities than a lobster has legs. (Lobsters have 10 legs. We have 36. We win.)

| Category | What It Does | Example Abilities |
|---|---|---|
| **Content** | Full CRUD on posts & pages | `list-posts`, `create-post`, `update-post`, `delete-post` |
| **Media** | Library management | `list-media`, `upload-media`, `delete-media` |
| **Taxonomies** | Terms and taxonomies | `list-taxonomies`, `manage-terms` |
| **Users** | User management with safety guards | `list-users`, `get-user`, `update-user-role` |
| **Comments** | Moderation and cleanup | `list-comments`, `moderate-comment` |
| **Settings** | Read and update options (allowlisted) | `get-option`, `update-option` |
| **Plugins & Themes** | Extension management | `list-plugins`, `toggle-plugin`, `list-themes`, `switch-theme` |
| **Analytics** | Site health and data export | `site-health`, `recent-activity`, `search-content`, `export-data` |
| **Advanced** | Menus, meta, revisions, bulk ops, cron | `list-menus`, `manage-menu-item`, `get-post-meta`, `update-post-meta`, `list-revisions`, `restore-revision`, `bulk-edit-posts`, `list-cron-events`, `manage-cron` |
| **WooCommerce** | Shop abilities (when WooCommerce is active) | `woo-list-products`, `woo-manage-order` |

Every ability has built-in **security guards**: capability checks, input sanitization, existence validation, and audit logging. We don't let AI agents run around your site like unsupervised lobsters in a kitchen.

### Custom MCP Server

A dedicated `wp-pinch` MCP endpoint that curates which abilities are exposed to AI agents. Like a bouncer at a seafood restaurant -- only the abilities you approve get past the velvet rope.

### Webhook Dispatcher

Fires real-time events to OpenClaw the moment things happen on your site:

- Post published, updated, or trashed
- New comments
- User registration
- WooCommerce order status changes

Includes **exponential backoff retry** (up to 4 attempts: 5min, 30min, 2hr, 12hr) and built-in **fixed-duration rate limiting** so your site doesn't get overwhelmed. Because even lobsters pace themselves.

### Autonomous Governance Engine

Five recurring background tasks run via Action Scheduler to keep your site healthy without you lifting a claw:

| Task | What It Catches |
|---|---|
| **Content Freshness** | Posts that haven't been updated in ages (staler than yesterday's catch) |
| **SEO Health** | Missing meta descriptions, short titles, images without alt text |
| **Comment Sweep** | Spam, orphaned comments, and other bottom-feeders |
| **Broken Link Detection** | Dead links lurking in your content |
| **Security Scanning** | Suspicious plugin changes, available updates |

Findings are delivered via webhook to OpenClaw or processed server-side with the WP AI Client API. You can run them on a schedule or trigger them manually like a lobster trap -- set it and check it.

### Pinch Chat Block

A Gutenberg block built with the **Interactivity API** that drops a reactive, accessible chat interface into any page or post. Your visitors can talk to your AI agent right on your site.

- Real-time message streaming
- Session persistence across page loads
- Per-block scoped storage (multiple chat blocks on one page? No problem.)
- Screen reader announcements via `wp.a11y.speak`
- Dark mode support
- High-contrast mode accessible
- Mobile responsive

It's like giving your website a little chat window with claws.

### WP-CLI Commands

For the terminal-dwelling lobsters among us:

```bash
wp pinch status            # Connection status, abilities, gateway health
wp pinch webhook-test      # Fire a test webhook
wp pinch governance run    # Trigger governance tasks manually
wp pinch audit list        # Browse audit log entries
wp pinch abilities list    # See all registered abilities
```

Pipe it, script it, cron it. Your shell, your rules.

### Comprehensive Audit Log

Every ability execution, webhook dispatch, governance finding, and chat message is logged to a custom database table with **automatic 90-day retention**. Browse it in the admin, query it via WP-CLI, or export it for compliance.

Nothing happens on your site without leaving a trail. Even lobsters leave tracks on the ocean floor.

### GDPR-Ready Privacy Tools

Full integration with WordPress's privacy export and erasure system. When a user requests their data, WP Pinch exports or deletes all audit log entries associated with their account. Because privacy isn't optional -- it's the law of the sea.

### Site Health Integration

WP Pinch adds two tests to the WordPress Site Health screen:

- **Gateway Connectivity** -- Can your site reach the OpenClaw gateway?
- **Configuration Check** -- Are all required settings filled in?

Green checks all the way down, like a perfectly cooked lobster.

### Developer Extensible

Over **12 filters** and **6 actions** let you bend WP Pinch to your will:

```php
// Remove an ability
add_filter( 'wp_pinch_abilities', function ( array $abilities ): array {
    unset( $abilities['delete_post'] );
    return $abilities;
} );

// Modify webhook payloads before dispatch
add_filter( 'wp_pinch_webhook_payload', function ( array $payload, string $event ): array {
    $payload['site_name'] = get_bloginfo( 'name' );
    return $payload;
}, 10, 2 );

// Block specific roles from being assigned by AI
add_filter( 'wp_pinch_blocked_roles', function ( array $roles ): array {
    $roles[] = 'editor'; // Protect editors too
    return $roles;
} );

// Suppress governance findings before delivery
add_filter( 'wp_pinch_governance_findings', function ( array $findings, string $task ): array {
    // Filter out anything you don't care about
    return $findings;
}, 10, 2 );

// Run code after any ability executes
add_action( 'wp_pinch_after_ability', function ( string $ability, array $args, mixed $result ): void {
    // Send to your logging service, trigger notifications, etc.
}, 10, 3 );
```

If you can hook it, you can pinch it.

---

## Security

WP Pinch takes security seriously. More seriously than a lobster takes its territory.

- **Capability checks** on every ability execution
- **Input sanitization** with `sanitize_text_field()`, `sanitize_key()`, `absint()`, `esc_url_raw()`
- **Output escaping** with `esc_html()`, `esc_attr()`, `esc_url()`
- **Nonce verification** on all REST and AJAX endpoints
- **Prepared SQL statements** everywhere (no raw queries, ever)
- **Option allowlists** prevent reading/writing sensitive options
- **Role escalation prevention** -- AI agents can't promote users to administrator
- **Self-deactivation guard** -- the plugin can't be turned off by its own abilities
- **Self-role-change prevention** -- the current user can't modify their own role
- **Existence checks** before modifying posts, comments, terms, and media
- **CSS injection prevention** on block attributes via regex validation
- **Fixed-duration rate limiting** that doesn't slide (no gaming the window)
- **Gateway URL hidden from non-admins** in the status endpoint
- **Comment author emails stripped** from ability responses
- **`show_in_rest => false`** on all settings to prevent REST API leakage
- **`Update URI: false`** to prevent third-party update hijacking

If you find a vulnerability, please report it responsibly. See [SECURITY.md](SECURITY.md) for details.

---

## Requirements

| Requirement | Minimum | Notes |
|---|---|---|
| WordPress | 6.9+ | For the Abilities API |
| PHP | 8.1+ | For type hints and enums |
| MCP Adapter plugin | Recommended | For full MCP integration |
| Action Scheduler | Required | Ships with WooCommerce, or install standalone |

---

## Installation

### The Quick Way

```bash
wp plugin install https://github.com/RegionallyFamous/wp-pinch/releases/latest/download/wp-pinch.zip --activate
```

### The Manual Way

1. Download the latest release from the [Releases page](https://github.com/RegionallyFamous/wp-pinch/releases).
2. In your WordPress admin, go to **Plugins > Add New > Upload Plugin**.
3. Upload the `.zip` file and click **Install Now**.
4. Activate.
5. Navigate to the **WP Pinch** top-level menu in your admin sidebar.
6. Enter your OpenClaw Gateway URL and API Token.
7. Click **Test Connection**.
8. Configure webhook events and governance tasks.
9. Sit back and let the lobster do the work.

### From Source (Development)

```bash
cd wp-content/plugins
git clone https://github.com/RegionallyFamous/wp-pinch.git
cd wp-pinch
composer install
npm install && npm run build
wp plugin activate wp-pinch
```

---

## Connecting OpenClaw

Once WP Pinch is configured, point OpenClaw at your site's MCP endpoint:

```bash
npx openclaw connect --mcp-url https://your-site.com/wp-json/wp-pinch/v1/mcp
```

OpenClaw will discover the available abilities and begin routing messages from your configured channels (WhatsApp, Telegram, Slack, Discord, etc.) to your WordPress site.

You can also add your Gateway URL directly in the WP Pinch settings for webhook-based integration -- ideal for sites that want real-time push notifications when content changes.

---

## Architecture

```
┌──────────────────────────────────────────────────────────┐
│                        WordPress                          │
│                                                           │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────┐  │
│  │  Abilities   │  │  MCP Server  │  │   Governance   │  │
│  │  (34 tools)  │──│  (endpoint)  │  │   (5 tasks)    │  │
│  └──────┬───────┘  └──────┬───────┘  └───────┬────────┘  │
│         │                 │                   │           │
│  ┌──────┴─────────────────┴───────────────────┴────────┐ │
│  │              Webhook Dispatcher                      │ │
│  │         (retry + rate limiting)                      │ │
│  └──────────────────────┬──────────────────────────────┘ │
│                         │                                 │
│  ┌──────────────────────┴──────────────────────────────┐ │
│  │                  Audit Log                           │ │
│  │            (90-day retention)                        │ │
│  └─────────────────────────────────────────────────────┘ │
└────────────────────────────┬──────────────────────────────┘
                             │
                    ┌────────┴─────────┐
                    │    OpenClaw       │
                    │  (AI Gateway)     │
                    └────────┬─────────┘
                             │
            ┌────────┬───────┴──────┬──────────┐
            │        │              │          │
         WhatsApp  Telegram      Slack     Discord
```

It's a lobster trap for AI agents. They go in, they do work, they report back.

---

## Development

### Prerequisites

- [Docker](https://www.docker.com/) (for `wp-env`)
- [Node.js](https://nodejs.org/) 18+
- [Composer](https://getcomposer.org/)

### Setup

```bash
git clone https://github.com/RegionallyFamous/wp-pinch.git
cd wp-pinch
composer install
npm install
npm run build
make setup-hooks   # Install pre-commit quality gates
npx wp-env start   # http://localhost:8888
```

### Quality System

WP Pinch uses a multi-layered quality system that catches bugs before they ever reach your codebase. Think of it as a lobster cage with multiple chambers -- nothing escapes.

| Layer | Tool | What It Catches |
|---|---|---|
| **Pre-commit hook** | PHPCS + PHPStan | Coding standard violations, type errors |
| **CI Pipeline** | GitHub Actions | PHPCS, PHPStan, PHPUnit, Build verification |
| **Static Analysis** | PHPStan Level 6 | Type mismatches, null access, undefined properties |
| **Coding Standards** | PHPCS (WordPress-Extra) | Security, escaping, sanitization, naming |
| **Unit Tests** | PHPUnit (120+ tests) | Functional correctness, security guards, edge cases |
| **Branch Protection** | GitHub | All checks must pass before merging to main |

```bash
# Run everything locally
make check         # PHPCS + PHPStan
make test          # PHPUnit

# Individual tools
composer lint      # PHPCS only
composer phpstan   # PHPStan only
composer lint:fix  # Auto-fix what PHPCBF can

# Build assets
npm run build      # Production build
npm run start      # Watch mode for development
```

### Bug Fix Workflow (Test-First)

We follow a "test-first" approach for bug fixes, because lobsters learn from their mistakes:

1. Write a failing test that reproduces the bug
2. Run the test to confirm it fails
3. Fix the bug
4. Run the test to confirm it passes
5. Run `make check` to ensure nothing else broke
6. Commit with a message that references the test

See [CONTRIBUTING.md](CONTRIBUTING.md) for full guidelines.

---

## Hooks & Filters Reference

### Filters

| Filter | Description | Parameters |
|---|---|---|
| `wp_pinch_abilities` | Modify registered abilities list | `array $abilities` |
| `wp_pinch_webhook_payload` | Modify webhook data before dispatch | `array $payload, string $event` |
| `wp_pinch_blocked_roles` | Roles that can't be assigned via AI | `array $roles` |
| `wp_pinch_option_allowlist` | Options readable via `get-option` | `array $keys` |
| `wp_pinch_option_write_allowlist` | Options writable via `update-option` | `array $keys` |
| `wp_pinch_governance_findings` | Suppress or modify governance findings | `array $findings, string $task` |
| `wp_pinch_governance_interval` | Adjust task schedule intervals | `int $seconds, string $task` |
| `wp_pinch_chat_response` | Modify chat response before returning | `array $result, array $data` |
| `wp_register_ability_args` | Modify ability registration args | `array $args` |

### Actions

| Action | Description | Parameters |
|---|---|---|
| `wp_pinch_after_ability` | Fires after any ability executes | `string $ability, array $args, mixed $result` |
| `wp_pinch_governance_finding` | Fires when a governance finding is recorded | `string $task, array $finding` |
| `wp_pinch_activated` | Fires on plugin activation | -- |
| `wp_pinch_deactivated` | Fires on plugin deactivation | -- |
| `wp_pinch_booted` | Fires after all subsystems initialize | -- |

---

## Frequently Asked Questions

**Does this require OpenClaw?**
OpenClaw is recommended for the full experience (webhooks, chat, governance delivery), but the Abilities API and MCP server work with any MCP-compatible client. WP Pinch is the lobster; OpenClaw is the ocean. You can technically have a lobster without an ocean, but it's way less impressive.

**What WordPress version is required?**
6.9 or later. That's when WordPress grew its Abilities API claws.

**Does this work with WooCommerce?**
Yes! WooCommerce order status changes trigger webhooks, and WP Pinch adds two bonus abilities for listing products and managing orders. Your AI agent can check inventory faster than a lobster can snap a rubber band.

**Can I add custom abilities?**
Absolutely. Use the `wp_pinch_register_abilities` action or the `wp_pinch_abilities` filter. If you can dream it, you can register it.

**Is it production-ready?**
WP Pinch passes PHPCS (WordPress-Extra + Security), PHPStan Level 6, and 120+ PHPUnit tests. Every ability has security guards, every input is sanitized, every output is escaped. It's as battle-tested as a lobster that survived the tank at Red Lobster.

**Is the API token stored securely?**
It's stored in the WordPress options table with `show_in_rest => false`. For production hardening, we recommend using environment variables or a secrets manager. The token never appears in REST API responses and is never exposed to non-admin users.

**Why lobster puns?**
Because the alternative was crab puns, and that felt a little... sideways. Plus, OpenClaw. Claw. Lobster. Pinch. It was destiny.

---

## License

WP Pinch is licensed under the [GPL-2.0-or-later](LICENSE).

```
Copyright (C) 2026 Nick Hamze

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

---

## Credits and Acknowledgments

WP Pinch is built on the shoulders of many open-source projects. We are grateful to the maintainers and contributors of every one of them -- the unsung lobsters of the open-source ocean.

### WordPress and Automattic

- **[WordPress](https://wordpress.org/)** -- The CMS that makes it all possible. A registered trademark of the [WordPress Foundation](https://wordpressfoundation.org/).
- **[Abilities API](https://developer.wordpress.org/)** -- The WordPress 6.9 API that lets plugins register AI-accessible capabilities.
- **[MCP Adapter](https://github.com/WordPress/mcp-adapter)** -- Bridges WordPress abilities to the Model Context Protocol.
- **[Gutenberg](https://github.com/WordPress/gutenberg)** -- The block editor foundation for Pinch Chat.
- **[Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/)** -- Powers the reactive chat interface.
- **[Action Scheduler](https://actionscheduler.org/)** by WooCommerce (Automattic) -- Reliable background task execution. Licensed under GPL-3.0-or-later.
- **[Jetpack Autoloader](https://github.com/Automattic/jetpack-autoloader)** by Automattic -- Prevents version conflicts. Licensed under GPL-2.0-or-later.
- **[@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)** -- Build toolchain (Webpack, Babel, ESLint, Stylelint).
- **[@wordpress/env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)** -- Docker-based local development environment.

### Other Open-Source Projects

- **[OpenClaw](https://github.com/nicepkg/openclaw)** -- The AI agent framework that makes the magic happen.
- **[WP-CLI](https://wp-cli.org/)** -- Command-line interface for WordPress. Licensed under MIT.
- **[PHPUnit](https://phpunit.de/)** -- Testing framework. Licensed under BSD-3-Clause.
- **[PHPStan](https://phpstan.org/)** -- Static analysis tool. Licensed under MIT.
- **[WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)** -- PHPCS rules for WordPress. Licensed under MIT.

### Author

Created and maintained by [Nick Hamze](https://github.com/RegionallyFamous).

Built with coffee, crustacean puns, and an unreasonable number of PHPStan runs.
