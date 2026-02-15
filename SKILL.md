---
name: pinch-to-post
version: 5.2.1
description: Manage WordPress sites through WP Pinch MCP server or REST API fallback. Part of WP Pinch (wp-pinch.com).
author: RegionallyFamous
project: https://github.com/RegionallyFamous/wp-pinch
homepage: https://wp-pinch.com
user-invocable: true
security: Auth via MCP config or env vars (WP_APP_PASSWORD, WP_PINCH_API_TOKEN). Never hardcode credentials. See Authentication, Authorization Scope, and Security & Usage.
tags:
  - wordpress
  - wp-pinch
  - cms
  - mcp
  - content-management
  - automation
category: productivity
triggers:
  - wordpress
  - wp
  - blog
  - publish
  - post
  - site management
metadata:
  openclaw:
    emoji: "🦞"
    primaryEnv: "WP_SITE_URL"
    requires:
      env: ["WP_SITE_URL"]
    optionalEnv:
      - "WP_APP_PASSWORD"
      - "WP_USERNAME"
      - "WP_PINCH_API_TOKEN"
changelog: |
  5.2.1
  - Security audit updates: Authentication section (MCP vs REST credential flow), Authorization Scope, external data flow (webhooks, digests), Security & Usage guidance. Declared optional env (WP_APP_PASSWORD, WP_USERNAME, WP_PINCH_API_TOKEN).

  5.2.0
  - Added Molt: repackage any post into 10 formats (social, thread, FAQ, email, meta description, and more)
  - Added Ghost Writer: analyze author voice, find abandoned drafts, complete them in your style
  - Added 10+ high-leverage tools: what-do-i-know, project-assembly, knowledge-graph, find-similar, spaced-resurfacing
  - Added quick-win tools: generate-tldr, suggest-links, suggest-terms, quote-bank, content-health-report
  - Added site-digest (Memory Bait), related-posts (Echo Net), synthesize (Weave)
  - PinchDrop Quick Drop mode for minimal note capture
  - Daily write budget with 429 + Retry-After support
  - Governance expanded to 8 tasks including Draft Necromancer and Spaced Resurfacing
  - Tide Report: daily digest bundling all governance findings into one webhook

  5.1.0
  - Added PinchDrop capture endpoint with idempotency via request_id
  - Web Clipper bookmarklet support
  - Webhook events: post_delete, governance_finding
  - WooCommerce abilities: woo-list-products, woo-manage-order

  5.0.0
  - Initial release on ClawHub
  - 38+ core MCP abilities across 10 categories
  - MCP-first with REST API fallback
  - Full capability checks, input sanitization, audit logging
  - Governance: content freshness, SEO health, comment sweep, broken links, security scan
  - Webhook integration for post, comment, user, and WooCommerce events
---

# Pinch to Post v5 — WordPress Management via WP Pinch

**This skill is part of [WP Pinch](https://wp-pinch.com)** — a WordPress plugin that exposes your site as MCP tools for OpenClaw and other AI assistants. [ClawHub](https://clawhub.ai/nickhamze/pinch-to-post) · [GitHub](https://github.com/RegionallyFamous/wp-pinch) · [Install in 60 seconds](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration)

*Manage your WordPress site from chat — publish, Molt, PinchDrop, and 38+ abilities without leaving your messaging app.*

You are an AI agent managing a WordPress site through the **WP Pinch** plugin. WP Pinch registers **38+ core abilities** (plus 2 WooCommerce when active) as MCP tools, plus PinchDrop, Ghost Writer, Molt, and high-leverage discovery tools. Every ability has capability checks, input sanitization, and audit logging.

## Authentication

Admin-level actions (create/update/delete posts, toggle plugins, update options, manage users, export data) **require authentication**. Credentials are never hardcoded.

- **MCP (preferred):** The WP Pinch MCP server handles auth. Configure your MCP server with the site URL and credentials (Application Password or API token). The MCP server sends credentials with each request; the skill does not store them.
- **REST fallback:** Set `WP_SITE_URL`, `WP_USERNAME`, and `WP_APP_PASSWORD` (Application Passwords from Users → Profile → Application Passwords). Use a dedicated user with minimum required capabilities.
- **Webhooks:** Set `WP_PINCH_API_TOKEN` from WP Pinch → Connection for webhook signature verification. Webhook destinations are configured in WP Pinch → Webhooks.

Do not point this skill at a production site until auth is configured. Test on staging first.

## Connection Methods (in order of preference)

### Method 1: MCP (Preferred)

Use the WP Pinch MCP tools directly. All tools are namespaced `wp-pinch/*`:

**Content**
- `wp-pinch/list-posts` — List posts with optional status, type, search, per_page
- `wp-pinch/get-post` — Fetch a single post by ID
- `wp-pinch/create-post` — Create a post (prefer `status: "draft"`, publish after confirmation)
- `wp-pinch/update-post` — Update existing post
- `wp-pinch/delete-post` — Trash a post (not permanent delete)

**Media**
- `wp-pinch/list-media` — List media library items
- `wp-pinch/upload-media` — Upload from URL
- `wp-pinch/delete-media` — Delete attachment by ID

**Taxonomies**
- `wp-pinch/list-taxonomies` — List taxonomies and terms
- `wp-pinch/manage-terms` — Create, update, or delete terms

**Users**
- `wp-pinch/list-users` — List users (emails redacted)
- `wp-pinch/get-user` — Get user by ID (emails redacted)
- `wp-pinch/update-user-role` — Change user role (cannot assign admin or dangerous roles)

**Comments**
- `wp-pinch/list-comments` — List comments with filters
- `wp-pinch/moderate-comment` — Approve, spam, trash, or delete a comment

**Settings**
- `wp-pinch/get-option` — Read option (allowlisted keys only)
- `wp-pinch/update-option` — Update option (allowlisted keys only; auth keys, salts, active_plugins are denylisted)

**Plugins & Themes**
- `wp-pinch/list-plugins` — List plugins and status
- `wp-pinch/toggle-plugin` — Activate or deactivate
- `wp-pinch/list-themes` — List themes
- `wp-pinch/switch-theme` — Switch active theme

**Analytics & Discovery**
- `wp-pinch/site-health` — WordPress site health summary
- `wp-pinch/recent-activity` — Recent posts, comments, users
- `wp-pinch/search-content` — Full-text search across posts
- `wp-pinch/export-data` — Export posts/users as JSON (PII redacted)
- `wp-pinch/site-digest` — Memory Bait: compact export of recent posts for agent context
- `wp-pinch/related-posts` — Echo Net: backlinks and taxonomy-related posts for a given post ID
- `wp-pinch/synthesize` — Weave: search + fetch payload for LLM synthesis

**Quick-win tools**
- `wp-pinch/generate-tldr` — Generate and store TL;DR for a post (post meta)
- `wp-pinch/suggest-links` — Suggest internal link candidates for a post or query
- `wp-pinch/suggest-terms` — Suggest taxonomy terms for content or a post ID
- `wp-pinch/quote-bank` — Extract notable sentences from a post
- `wp-pinch/content-health-report` — Execute content health report (structure, readability, etc.)

**High-leverage tools**
- `wp-pinch/what-do-i-know` — Natural-language query → search + synthesis → answer with source IDs
- `wp-pinch/project-assembly` — Weave multiple posts into one draft with citations
- `wp-pinch/spaced-resurfacing` — Posts not updated in N days (by category/tag)
- `wp-pinch/find-similar` — Find posts similar to a post or query
- `wp-pinch/knowledge-graph` — Graph of posts and links for visualization

**Advanced**
- `wp-pinch/list-menus` — List navigation menus
- `wp-pinch/manage-menu-item` — Add, update, delete menu items
- `wp-pinch/get-post-meta` — Read post meta
- `wp-pinch/update-post-meta` — Write post meta (per-post capability check)
- `wp-pinch/list-revisions` — List revisions for a post
- `wp-pinch/restore-revision` — Restore a revision
- `wp-pinch/bulk-edit-posts` — Bulk update post status, terms
- `wp-pinch/list-cron-events` — List scheduled cron events
- `wp-pinch/manage-cron` — Delete cron events (protected hooks cannot be deleted)

**PinchDrop**
- `wp-pinch/pinchdrop-generate` — Turn rough text into draft pack (post, product_update, changelog, social). Use `options.save_as_note: true` for Quick Drop (minimal post, no AI expansion).

**WooCommerce** (when active)
- `wp-pinch/woo-list-products` — List products
- `wp-pinch/woo-manage-order` — Update order status, add notes

**Ghost Writer** (when `ghost_writer` feature flag enabled)
- `wp-pinch/analyze-voice` — Build or refresh author style profile
- `wp-pinch/list-abandoned-drafts` — Rank drafts by resurrection potential
- `wp-pinch/ghostwrite` — Complete a draft in the author's voice

**Molt** (when `molt` feature flag enabled)
- `wp-pinch/molt` — Repackage post into 10 formats: social, email_snippet, faq_block, faq_blocks (Gutenberg block markup), thread, summary, meta_description, pull_quote, key_takeaways, cta_variants

### Method 2: REST API Fallback

If MCP is not available, use the WordPress REST API with **application passwords** (never use the main admin password):

```bash
# Create a post (prefer draft)
curl -X POST "https://SITE_URL/wp-json/wp/v2/posts" \
  -H "Authorization: Basic BASE64_APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"title": "My Post", "content": "Hello world", "status": "draft"}'

# Get site info
curl "https://SITE_URL/wp-json/" \
  -H "Authorization: Basic BASE64_APP_PASSWORD"
```

### Method 3: WP Pinch Chat API

For interactive chat:

```bash
curl -X POST "https://SITE_URL/wp-json/wp-pinch/v1/chat" \
  -H "X-WP-Nonce: NONCE" \
  -H "Content-Type: application/json" \
  -d '{"message": "Show me recent posts"}'
```

## Authorization Scope

- **Read operations** (list-posts, get-post, site-health, etc.): Require at least Subscriber or equivalent.
- **Write operations** (create-post, update-post, toggle-plugin, update-option, etc.): Require Editor or Administrator.
- The WP Pinch plugin enforces WordPress capability checks on every request. The agent must not bypass or override these; all operations are subject to the configured user's role and capabilities.
- **Role changes:** `update-user-role` blocks assignment of administrator and other high-privilege roles.

## Webhook Configuration

WP Pinch sends webhooks to OpenClaw for:
- `post_status_change` — Post published, drafted, trashed
- `new_comment` — Comment posted
- `user_register` — New user signup
- `woo_order_change` — WooCommerce order status change
- `post_delete` — Post permanently deleted
- `governance_finding` — Autonomous scan results (8 tasks)

**Configuration:** WP Pinch → Webhooks. Webhook URLs are user-configured; no default external endpoints. Destinations must be explicitly set by the site admin.

**Data sent:** Event payloads (post ID, status, user ID, etc.). PII (emails, passwords) is not included. `site-digest` and `export-data` can include post content and user metadata; PII is redacted per [Security](https://github.com/RegionallyFamous/wp-pinch/wiki/Security).

**Daily digests (Tide Report, Memory Bait):** Bundled governance findings sent to the configured webhook URL. Scope and format are documented in WP Pinch → Webhooks.

## Governance Tasks (8)

- **Content Freshness** — Posts not updated in 180+ days
- **SEO Health** — Titles, alt text, meta descriptions, content length
- **Comment Sweep** — Pending moderation and spam
- **Broken Links** — Dead link detection (50/batch)
- **Security Scan** — Outdated software, debug mode, file editing
- **Draft Necromancer** — Abandoned drafts (requires Ghost Writer)
- **Spaced Resurfacing** — Notes not updated in N days
- **Tide Report** — Daily digest bundling all findings

Findings delivered via webhook or processed server-side.

## Best Practices

1. **Create posts as drafts first** — Use `status: "draft"` for create-post; use `POST /wp-pinch/v1/preview-approve` with `{ "post_id": N }` to publish after user confirms the preview.
2. **Use MCP tools when available** — Typed, permission-aware, audit-logged. Prefer over raw REST when possible.
3. **Check site health** before significant changes — Use `site-digest` or `site-health` to orient the agent.
4. **Option update has an allowlist** — Only safe options (blogname, timezone, etc.) can be modified. Auth keys and active_plugins are denylisted.
5. **All actions are audit-logged** — Check the audit log if something seems off.
6. **Do not assign admin or dangerous roles** — `update-user-role` blocks administrator and roles with manage_options, edit_users, etc.
7. **PinchDrop captures** — Use `request_id` for idempotency; include `source` for traceability.

## What Not to Do

- **Do not use full admin password** — Use application passwords scoped to the minimum capabilities needed.
- **Do not store credentials in config** — Use environment variables or a secret manager.
- **Do not skip the draft step** for user-facing content — Publish only after explicit confirmation.
- **Do not bulk-delete** without confirmation — `bulk-edit-posts` can trash many posts at once.
- **Do not delete cron events** for core hooks (wp_update_plugins, wp_scheduled_delete, etc.) — They are protected.
- **Do not share the Web Clipper bookmarklet URL** — It contains the capture token; treat it like a password.

## Error Handling

- **`rate_limited`** — Back off and retry; respect `Retry-After` if present.
- **`daily_write_budget_exceeded`** (429) — Site has a daily write cap and it was reached; do not retry until the next day.
- **`validation_error`** / **`rest_invalid_param`** — Fix the request (required param, length limit); do not retry unchanged.
- **`capability_denied`** / **`rest_forbidden`** — User lacks permission; show a clear message.
- **`post_not_found`** — Post ID invalid or deleted; suggest listing or searching.
- **`not_configured`** — Site has not set Gateway URL or API token; ask admin to configure WP Pinch.
- **503 (Service Unavailable)** — API may be disabled (WP_PINCH_DISABLED or read-only mode); ask admin to check WP Pinch → Connection.

See [Error Codes](https://github.com/RegionallyFamous/wp-pinch/wiki/Error-Codes) for the full list. Full security details: [Security](https://github.com/RegionallyFamous/wp-pinch/wiki/Security).

## Security & Usage

- **Test on staging first** — Do not point this skill at a production site until you understand auth and permissions.
- **Use minimal credentials** — Prefer a dedicated WordPress user with limited capabilities (e.g., Editor instead of Administrator) when possible.
- **Rotate credentials** — Regenerate Application Passwords and API tokens periodically.
- **Review the plugin** — Confirm MCP endpoint auth and denylisted options: [WP Pinch source](https://github.com/RegionallyFamous/wp-pinch), [Security wiki](https://github.com/RegionallyFamous/wp-pinch/wiki/Security).
- **Monitor activity** — WP Pinch audit logs record all actions; check them if something seems off.
- **MCP endpoint security** — Confirm what the WP Pinch plugin exposes and whether any endpoints are public. Write operations should require authentication.

## Setup: Which WordPress Site?

Set these **environment variables** on your OpenClaw instance to choose which WordPress site the skill uses. For multiple sites, use different workspaces or env configs.

| Variable | Required | Description |
|----------|----------|-------------|
| `WP_SITE_URL` | Yes | WordPress site URL (e.g. `https://mysite.com`) — **this is how you select which site to use** |
| `WP_APP_PASSWORD` | For REST fallback | Application password (Users → Profile → Application Passwords). Required for REST fallback; not needed if using MCP only. |
| `WP_USERNAME` | For REST fallback | WordPress username. Required with `WP_APP_PASSWORD` for REST fallback. |
| `WP_PINCH_API_TOKEN` | For webhooks | From WP Pinch → Connection tab, for webhook signature verification. |

**Example** (add to your OpenClaw `.env` or environment):

```bash
WP_SITE_URL=https://mysite.com
WP_USERNAME=admin
WP_APP_PASSWORD=xxxx xxxx xxxx xxxx xxxx xxxx
```

**MCP users:** Configure your MCP server to point at the same site URL (e.g. `https://mysite.com/wp-json/wp-pinch/v1/mcp`). The MCP config determines which site the tools connect to.

Full setup guide: [Configuration](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration).
