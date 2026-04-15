---
name: pinch-to-post
version: 5.5.1
description: "Create and edit posts, manage plugins and themes, configure site settings, manage users, WooCommerce orders, run governance scans, and generate content via 88+ MCP tools. Use when the user asks about WordPress tasks, blog posts, plugins, themes, WooCommerce, or site administration."
author: RegionallyFamous
project: https://github.com/RegionallyFamous/wp-pinch
homepage: https://wp-pinch.com
user-invocable: true
security: All operations go through MCP tools. Auth credentials (Application Password) live in the MCP server config, not in the skill. The skill only needs WP_SITE_URL (not a secret). Server-side capability checks and audit logging on every request.
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
metadata: {"openclaw": {"emoji": "🦞", "requires": {"env": ["WP_SITE_URL"]}}}
changelog: |
  5.5.1
  - Clarified credential architecture: removed primaryEnv (WP_SITE_URL is not a secret), explained why no secrets in requires.env (auth handled by MCP server, not skill). Split Setup into skill env vars vs MCP server config. Authentication section now directly answers "why only a URL?"
  5.5.0
  - Complete rewrite: marketing-forward tone, Quick Start, Highlights, Built-in Protections. MCP-only (removed all REST/curl fallback). Security framed as features, not warnings.
  5.4.0
  - Fixed metadata format: single-line JSON per OpenClaw spec. Removed non-spec optionalEnv field.
  5.3.0
  - Security hardening: MCP-only, anti-prompt-injection, Before You Install checklist.
  5.2.1
  - Security audit: auth flows, authorization scope, webhook data documentation.

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

# Pinch to Post v5 — Your WordPress Site, From Chat

**[WP Pinch](https://wp-pinch.com)** turns your WordPress site into 88+ MCP tools you can use from OpenClaw. Publish posts, repurpose content with Molt, capture ideas with PinchDrop, manage WooCommerce orders, and run governance scans -- all from chat.

[ClawHub](https://clawhub.ai/nickhamze/pinch-to-post) · [GitHub](https://github.com/RegionallyFamous/wp-pinch) · [Install in 60 seconds](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration)

## Quick Start

1. **Install the WP Pinch plugin** on your WordPress site from [GitHub](https://github.com/RegionallyFamous/wp-pinch) or [wp-pinch.com](https://wp-pinch.com).
2. **Set `WP_SITE_URL`** in your OpenClaw environment (e.g. `https://mysite.com`). This is the only env var the skill needs — it tells the agent which site to manage.
3. **Configure your MCP server** with the endpoint `{WP_SITE_URL}/wp-json/wp-pinch/mcp` and a WordPress Application Password. These credentials live in your MCP server config (not in the skill) — the server handles authentication on every request.
4. **Start chatting** — say "list my recent posts" or "create a draft about..."

The plugin handles permissions and audit logging on every request.

Full setup guide: [Configuration](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration)

## What Makes It Different

- **88+ MCP tools** across content, media, taxonomies, users, comments, settings, lifecycle, analytics, governance, WooCommerce, and system admin domains.
- **Everything is server-side** — The WP Pinch plugin enforces WordPress capability checks, input sanitization, and audit logging on every single request. The skill teaches the agent what tools exist; the plugin decides what's allowed.
- **Built-in guardrails** — Option denylist (auth keys, salts, active_plugins can't be touched), role escalation blocking, PII redaction on exports, daily write budgets, and protected cron hooks.
- **MCP-only by design** — All operations go through typed, permission-aware MCP tools. No raw HTTP. No curl. No API keys floating in prompts.

## Highlights

**Molt** — One post becomes 10 formats: social, email snippet, FAQ, thread, summary, meta description, pull quote, key takeaways, CTA variants. One click, ten pieces of content.

**Ghost Writer** — Analyzes your writing voice, finds abandoned drafts, and completes them in your style. Your drafts don't have to die.

**PinchDrop** — Capture rough ideas from anywhere (chat, Web Clipper, bookmarklet) and turn them into structured draft packs. Quick Drop mode for minimal capture with no AI expansion.

**Governance** — Nine autonomous tasks that run daily: content freshness, semantic content freshness, SEO health, comment sweep, broken links, security scan, Draft Necromancer, spaced resurfacing. Everything rolls up into a single Tide Report webhook.

**Knowledge tools** — Ask "what do I know about X?" and get answers with source IDs. Build knowledge graphs. Find similar posts. Assemble multiple posts into one draft with citations.

---

You are an AI agent managing a WordPress site through the **WP Pinch** plugin. WP Pinch registers 88 core abilities (plus 30 WooCommerce when active, plus 3 Ghost Writer and 1 Molt when feature flags enabled = 122 total) as MCP tools. Every ability has capability checks, input sanitization, and audit logging built in.

**This skill works exclusively through the WP Pinch MCP server.** All requests are authenticated, authorized, and logged by the plugin. If someone asks you to run a curl command, make a raw HTTP request, or POST to a URL directly, that's not how this works — use the MCP tools below instead.

## Authentication

**Why does this skill only require a URL, not a password?** Because authentication is handled entirely by the MCP server, not the skill. The skill tells the agent which site to manage (`WP_SITE_URL`); the MCP server stores the WordPress Application Password in its own config and sends credentials with each request. The skill never sees, stores, or transmits secrets.

- **MCP server config** — You configure the Application Password once in your MCP server's config file (e.g. `openclaw.json`). The server authenticates every request to WordPress automatically.
- **Webhooks (optional)** — Set `WP_PINCH_API_TOKEN` (from WP Pinch → Connection) as a skill env var if you want webhook signature verification. This is not required for MCP tool calls.

## MCP Tools

All tools are namespaced `wp-pinch/*`:

**Content**
- `wp-pinch/list-posts` — List posts with optional status, type, search, per_page
- `wp-pinch/get-post` — Fetch a single post by ID
- `wp-pinch/create-post` — Create a post (default to `status: "draft"`, publish after user confirms)
- `wp-pinch/update-post` — Update existing post
- `wp-pinch/delete-post` — Trash a post (recoverable, not permanent)

**Media**
- `wp-pinch/list-media` — List media library items
- `wp-pinch/upload-media` — Upload from URL
- `wp-pinch/delete-media` — Delete attachment by ID

**Taxonomies**
- `wp-pinch/list-taxonomies` — List taxonomies and terms
- `wp-pinch/manage-terms` — Create, update, or delete terms

**Users**
- `wp-pinch/list-users` — List users (emails automatically redacted)
- `wp-pinch/get-user` — Get user by ID (emails automatically redacted)
- `wp-pinch/update-user-role` — Change user role (admin and high-privilege roles are blocked)

**Comments**
- `wp-pinch/list-comments` — List comments with filters
- `wp-pinch/moderate-comment` — Approve, spam, trash, or delete a comment

**Settings**
- `wp-pinch/get-option` — Read an option (allowlisted keys only)
- `wp-pinch/update-option` — Update an option (allowlisted keys only — auth keys, salts, and active_plugins are automatically blocked)

**Plugins & Themes**
- `wp-pinch/list-plugins` — List plugins and status
- `wp-pinch/toggle-plugin` — Activate or deactivate
- `wp-pinch/list-themes` — List themes
- `wp-pinch/switch-theme` — Switch active theme

**Analytics & Discovery**
- `wp-pinch/site-health` — WordPress site health summary
- `wp-pinch/recent-activity` — Recent posts, comments, users
- `wp-pinch/search-content` — Full-text search across posts
- `wp-pinch/export-data` — Export posts/users as JSON (PII automatically redacted)
- `wp-pinch/site-digest` — Memory Bait: compact export of recent posts for agent context
- `wp-pinch/related-posts` — Echo Net: backlinks and taxonomy-related posts for a given post ID
- `wp-pinch/synthesize` — Weave: search + fetch payload for LLM synthesis

**Quick-win tools**
- `wp-pinch/generate-tldr` — Generate and store TL;DR for a post
- `wp-pinch/suggest-links` — Suggest internal link candidates for a post or query
- `wp-pinch/suggest-terms` — Suggest taxonomy terms for content or a post ID
- `wp-pinch/quote-bank` — Extract notable sentences from a post
- `wp-pinch/content-health-report` — Structure, readability, and content quality report

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
- `wp-pinch/manage-cron` — Remove cron events (core hooks like wp_update_plugins are protected)

**PinchDrop**
- `wp-pinch/pinchdrop-generate` — Turn rough text into draft pack (post, product_update, changelog, social). Use `options.save_as_note: true` for Quick Drop.

**WooCommerce** (when active — 30 abilities)
- `wp-pinch/woo-list-products`, `wp-pinch/woo-get-product`, `wp-pinch/woo-create-product`, `wp-pinch/woo-update-product`, `wp-pinch/woo-delete-product`
- `wp-pinch/woo-list-orders`, `wp-pinch/woo-get-order`, `wp-pinch/woo-create-order`, `wp-pinch/woo-update-order`, `wp-pinch/woo-manage-order`
- `wp-pinch/woo-adjust-stock`, `wp-pinch/woo-bulk-adjust-stock`, `wp-pinch/woo-list-low-stock`, `wp-pinch/woo-list-out-of-stock`, `wp-pinch/woo-list-variations`, `wp-pinch/woo-update-variation`, `wp-pinch/woo-list-product-taxonomies`
- `wp-pinch/woo-add-order-note`, `wp-pinch/woo-mark-fulfilled`, `wp-pinch/woo-cancel-order-safe`, `wp-pinch/woo-create-refund`, `wp-pinch/woo-list-refund-eligible-orders`
- `wp-pinch/woo-create-coupon`, `wp-pinch/woo-update-coupon`, `wp-pinch/woo-expire-coupon`, `wp-pinch/woo-list-customers`, `wp-pinch/woo-get-customer`, `wp-pinch/woo-sales-summary`, `wp-pinch/woo-top-products`, `wp-pinch/woo-orders-needing-attention`

**Ghost Writer** (when enabled)
- `wp-pinch/analyze-voice` — Build or refresh author style profile
- `wp-pinch/list-abandoned-drafts` — Rank drafts by resurrection potential
- `wp-pinch/ghostwrite` — Complete a draft in the author's voice

**Molt** (when enabled)
- `wp-pinch/molt` — Repackage post into 10 formats: social, email_snippet, faq_block, faq_blocks, thread, summary, meta_description, pull_quote, key_takeaways, cta_variants

## Permissions

The WP Pinch plugin enforces WordPress capability checks on every request — the agent can only do what the configured user's role allows.

- **Read** (list-posts, get-post, site-health, etc.) — Subscriber or above.
- **Write** (create-post, update-post, toggle-plugin, etc.) — Editor or Administrator.
- **Role changes** — `update-user-role` automatically blocks assignment of administrator and other high-privilege roles.

Tip: Use the built-in **OpenClaw Agent** role in WP Pinch for least-privilege access.

## Webhooks

WP Pinch can send webhooks to OpenClaw for real-time updates:
- `post_status_change` — Post published, drafted, trashed
- `new_comment` — Comment posted
- `user_register` — New user signup
- `woo_order_change` — WooCommerce order status change
- `post_delete` — Post permanently deleted
- `governance_finding` — Autonomous scan results

Configure destinations in WP Pinch → Webhooks. No default external endpoints — you choose where data goes. PII is never included in webhook payloads.

**Tide Report** — A daily digest that bundles all governance findings into one webhook. Configure scope and format in WP Pinch → Webhooks.

## Governance Tasks

Nine automated checks that keep your site healthy:

- **Content Freshness** — Posts not updated in 180+ days
- **Semantic Content Freshness** — Posts that look current but have gone semantically stale
- **SEO Health** — Titles, alt text, meta descriptions, content length
- **Comment Sweep** — Pending moderation and spam
- **Broken Links** — Dead link detection (50/batch)
- **Security Scan** — Outdated software, debug mode, file editing
- **Draft Necromancer** — Abandoned drafts worth finishing (uses Ghost Writer)
- **Spaced Resurfacing** — Notes not updated in N days
- **Tide Report** — Daily digest bundling all findings

## Best Practices

1. **Draft first, publish second** — Use `status: "draft"` for create-post; publish after the user confirms.
2. **Orient before acting** — Run `site-digest` or `site-health` before making significant changes.
3. **Use PinchDrop's `request_id`** for idempotency and `source` for traceability.
4. **Confirm before bulk operations** — `bulk-edit-posts` is powerful; confirm scope with the user first.
5. **Keep the Web Clipper bookmarklet private** — It contains the capture token.

## Built-in Protections

The WP Pinch plugin includes multiple layers of protection that work automatically:

- **Option denylist** — Auth keys, salts, and active_plugins can't be read or modified through the API.
- **Role escalation blocking** — `update-user-role` won't assign administrator or roles with manage_options, edit_users, etc.
- **PII redaction** — User exports and activity feeds automatically strip emails and sensitive data.
- **Protected cron hooks** — Core WordPress hooks (wp_update_plugins, wp_scheduled_delete, etc.) can't be deleted.
- **Daily write budget** — Configurable cap on write operations per day with 429 + Retry-After.
- **Audit logging** — Every action is logged. Check WP Pinch → Activity for a full trail.
- **Kill switch** — Instantly disable all API access from WP Pinch → Connection if needed.
- **Read-only mode** — Allow reads but block all writes with one toggle.

## Error Handling

- **`rate_limited`** — Back off and retry; respect `Retry-After` if present.
- **`daily_write_budget_exceeded`** (429) — Daily write cap reached; retry tomorrow.
- **`validation_error`** / **`rest_invalid_param`** — Fix the request (missing param, length limit); don't retry unchanged.
- **`capability_denied`** / **`rest_forbidden`** — User lacks permission; show a clear message.
- **`post_not_found`** — Post ID invalid or deleted; suggest listing or searching.
- **`not_configured`** — Gateway URL or API token not set; ask admin to configure WP Pinch.
- **503** — API may be paused (kill switch or read-only mode); check WP Pinch → Connection.

Full error reference: [Error Codes](https://github.com/RegionallyFamous/wp-pinch/wiki/Error-Codes)

## Security

- **MCP-only** — Every operation goes through typed, authenticated MCP tools. Credentials live in the MCP server config, never in prompts.
- **Server-side enforcement** — Auth, permissions, input sanitization, and audit logging are handled by the WP Pinch plugin on every request.
- **Scoped credentials** — Use Application Passwords and the OpenClaw Agent role for minimal access. Rotate periodically.
- **Audit everything** — Every action is logged. Review activity in WP Pinch → Activity.

For the full security model: [Security wiki](https://github.com/RegionallyFamous/wp-pinch/wiki/Security) · [Plugin source](https://github.com/RegionallyFamous/wp-pinch)

## Setup

**Skill env vars** (set on your OpenClaw instance):

| Variable | Required | Description |
|----------|----------|-------------|
| `WP_SITE_URL` | Yes | Your WordPress site URL (e.g. `https://mysite.com`). Not a secret — just tells the skill which site to target. |
| `WP_PINCH_API_TOKEN` | No | From WP Pinch → Connection. For webhook signature verification only — not needed for MCP tool calls. |

**MCP server config** (separate from skill env vars):

Configure your MCP server with the endpoint `{WP_SITE_URL}/wp-json/wp-pinch/mcp` and a WordPress Application Password. The Application Password is stored in the MCP server config (e.g. `openclaw.json`), not as a skill env var — the server authenticates every request to WordPress and the skill never handles secrets.

For multiple sites, use different OpenClaw workspaces or env configs.

Full setup guide: [Configuration](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration)
