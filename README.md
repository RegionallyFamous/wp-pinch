# WP Pinch

### The AI agent plugin that grabs your WordPress site with both claws.

**[wp-pinch.com](https://wp-pinch.com)** | [Wiki](https://github.com/RegionallyFamous/wp-pinch/wiki) | [Releases](https://github.com/RegionallyFamous/wp-pinch/releases)

[![WordPress 6.9+](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![CI](https://github.com/RegionallyFamous/wp-pinch/actions/workflows/ci.yml/badge.svg)](https://github.com/RegionallyFamous/wp-pinch/actions/workflows/ci.yml)

Manage your WordPress site from WhatsApp, Slack, Telegram, Discord -- or any chat platform. Powered by AI. Self-hosted. No SaaS middlemen getting their claws on your data.

---

## What if you could...

**Publish a blog post by texting your site?** WP Pinch exposes 35 WordPress abilities to AI agents -- content, media, users, settings, plugins, WooCommerce -- so you can manage your site from any messaging platform [OpenClaw](https://github.com/nicepkg/openclaw) supports.

**Have your site fix itself?** Six autonomous governance tasks run in the background — content freshness, SEO health, comment sweep, broken links, security scan, and draft necromancy. You set the schedule. The lobster does the work.

**Let visitors chat with an AI that knows your site?** Drop the Pinch Chat block on any page. Real-time streaming responses, dark mode, accessibility baked in, works on mobile. Your visitors get answers. You get happy visitors.

---

## Abilities

Your AI agent gets **35 core abilities** across 10 categories: content (posts, pages), media, taxonomies, users, comments, settings, plugins & themes, analytics (including site context export for agent memory), and advanced (menus, meta, revisions, bulk ops, cron). Plus **10 WooCommerce abilities** when your shop is active. Every ability is locked down with capability checks, input sanitization, and audit logging. The AI works *for* you, not *around* you.

[Full abilities reference ->](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference)

---

## Tools

WP Pinch adds **tools** on top of core abilities -- workflows that combine abilities, endpoints, and (where applicable) slash commands or governance.

- **PinchDrop** — Send rough ideas from any OpenClaw-connected channel; signed captures hit `POST /wp-pinch/v1/pinchdrop/capture` and the `pinchdrop-generate` ability produces Draft Packs (blog post, product update, changelog, social). Optional draft persistence with trace metadata. Gated by `pinchdrop_engine`. [PinchDrop guide ->](https://github.com/RegionallyFamous/wp-pinch/wiki/PinchDrop)
- **Ghost Writer** — Learns each author's writing voice from published posts and completes abandoned drafts in that voice. Abilities: `analyze-voice`, `list-abandoned-drafts`, `ghostwrite`. Use `/ghostwrite` in chat to list drafts or `/ghostwrite 123` to resurrect one. Weekly Draft Necromancer governance task. Gated by `ghost_writer`. [Ghost Writer guide ->](https://github.com/RegionallyFamous/wp-pinch/wiki/Ghost-Writer)

---

## Five reasons to install WP Pinch

### 1. Abilities (above)

35 core abilities + 10 WooCommerce. Content, media, users, settings, plugins, themes, analytics (including site context export), menus, meta, revisions, cron. Full reference in the [wiki](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference).

### 2. Live Chat Block

A Gutenberg block that gives your site a brain. SSE streaming for real-time responses. Slash commands (`/new`, `/status`). Message feedback. Token tracking. Markdown rendering. Session persistence. Public chat mode for anonymous visitors. Per-block agent overrides so every page can have its own personality. WCAG 2.1 AA accessible.

[Chat block details ->](https://github.com/RegionallyFamous/wp-pinch/wiki/Chat-Block)

### 3. Autonomous Governance

Six background tasks patrol your site on autopilot: content freshness, SEO health, comment cleanup, broken link detection, security scanning, and draft necromancy. Findings get delivered to OpenClaw or processed server-side. Think of it as a site health monitor with claws.

### 4. Real-Time Webhooks

Post published? Comment posted? WooCommerce order shipped? WP Pinch fires events to OpenClaw the moment they happen. HMAC-SHA256 signed. Retry with exponential backoff. Circuit breaker for when the gateway goes down. Two-way: OpenClaw can push ability requests *back* to your site.

### 5. Tools (above)

PinchDrop and Ghost Writer. Capture-anywhere draft packs and AI that writes in your voice. See the [Tools](#tools) section and the [Abilities Reference](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference#tools-pinchdrop--ghost-writer) for details.

---

## Get started in 60 seconds

```bash
wp plugin install https://github.com/RegionallyFamous/wp-pinch/releases/latest/download/wp-pinch.zip --activate
```

1. Go to **WP Pinch** in your admin sidebar
2. Enter your OpenClaw Gateway URL and API Token
3. Click **Test Connection**
4. Add a **Pinch Chat** block to any page

That's it. Your site has claws now.

[Detailed setup guide ->](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration)

---

## Built for developers

12+ filters and 6+ actions let you customize everything. Remove abilities, modify webhook payloads, adjust governance schedules, block roles, suppress findings:

```php
// Remove an ability your AI shouldn't have
add_filter( 'wp_pinch_abilities', function ( array $abilities ): array {
    unset( $abilities['delete_post'] );
    return $abilities;
} );
```

Full WP-CLI support for scripting and automation:

```bash
wp pinch status            # Gateway health + abilities count
wp pinch audit list        # Browse the audit log
wp pinch governance run    # Trigger governance manually
```

[Hooks & filters reference ->](https://github.com/RegionallyFamous/wp-pinch/wiki/Hooks-and-Filters) | [WP-CLI commands ->](https://github.com/RegionallyFamous/wp-pinch/wiki/WP-CLI)

---

## Security is not optional

Capability checks on every operation. Input sanitization and output escaping everywhere. Nonce verification on all endpoints. Prepared SQL statements (no raw queries, ever). HMAC-SHA256 webhook signatures with replay protection. Circuit breaker. Rate limiting. Option allowlists. Role escalation prevention. PHPStan Level 6 static analysis. 160+ PHPUnit tests. PHPCS WordPress-Extra + Security.

[Full security model ->](https://github.com/RegionallyFamous/wp-pinch/wiki/Security) | [Report a vulnerability ->](SECURITY.md)

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 6.9+ |
| PHP | 8.1+ |
| Action Scheduler | Required (ships with WooCommerce) |
| MCP Adapter plugin | Recommended |

---

## Documentation

Everything lives in the [GitHub Wiki](https://github.com/RegionallyFamous/wp-pinch/wiki):

- [Abilities Reference](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference) -- All 35 abilities across 10 categories
- [Chat Block](https://github.com/RegionallyFamous/wp-pinch/wiki/Chat-Block) -- Streaming, slash commands, public mode, accessibility
- [Architecture](https://github.com/RegionallyFamous/wp-pinch/wiki/Architecture) -- How the pieces fit together
- [Hooks & Filters](https://github.com/RegionallyFamous/wp-pinch/wiki/Hooks-and-Filters) -- 12+ filters, 6+ actions
- [Security](https://github.com/RegionallyFamous/wp-pinch/wiki/Security) -- The full security model
- [Configuration](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration) -- Installation, OpenClaw setup, admin settings
- [PinchDrop](https://github.com/RegionallyFamous/wp-pinch/wiki/PinchDrop) -- Capture-anywhere payload contract and draft-pack workflow
- [WP-CLI](https://github.com/RegionallyFamous/wp-pinch/wiki/WP-CLI) -- Command reference
- [Developer Guide](https://github.com/RegionallyFamous/wp-pinch/wiki/Developer-Guide) -- Contributing, testing, quality system
- [FAQ](https://github.com/RegionallyFamous/wp-pinch/wiki/FAQ) -- Common questions answered

---

## License

[GPL-2.0-or-later](LICENSE). Built by [Nick Hamze](https://github.com/RegionallyFamous) with coffee, crustacean puns, and an unreasonable number of PHPStan runs.

**[wp-pinch.com](https://wp-pinch.com)**
