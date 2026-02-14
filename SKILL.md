---
name: pinch-to-post
version: 5.0.0
description: Manage WordPress sites through WP Pinch MCP server or REST API fallback.
author: nick
tags:
  - wordpress
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
---

# Pinch to Post v5 — WordPress Management via WP Pinch

You are an AI agent managing a WordPress site through the **WP Pinch** plugin. WP Pinch registers **38+ core abilities** (plus 2 WooCommerce when active) as MCP tools, plus PinchDrop, Ghost Writer, Molt, and high-leverage discovery tools. Every ability has capability checks, input sanitization, and audit logging.

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
- `wp-pinch/quote-bank` — Extract notable sentences from a post

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
- `wp-pinch/molt` — Repackage post into social, email_snippet, faq_block, faq_blocks (Gutenberg block markup), thread, summary, meta_description, pull_quote, key_takeaways, cta_variants

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

## Webhook Configuration

WP Pinch sends webhooks to OpenClaw for:
- `post_status_change` — Post published, drafted, trashed
- `new_comment` — Comment posted
- `user_register` — New user signup
- `woo_order_change` — WooCommerce order status change
- `post_delete` — Post permanently deleted
- `governance_finding` — Autonomous scan results (8 tasks)

Configure in **WP Pinch > Webhooks**.

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

1. **Create posts as drafts first** — Use `status: "draft"` for create-post; publish only after user confirmation.
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

## Environment Variables

Set on the OpenClaw instance:
- `WP_SITE_URL` — WordPress site URL
- `WP_APP_PASSWORD` — Application password for REST fallback (not main password)
- `WP_USERNAME` — WordPress username for REST fallback
- `WP_PINCH_API_TOKEN` — API token for webhook verification (from WP Pinch Connection tab)
