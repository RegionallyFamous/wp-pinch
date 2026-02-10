---
name: pinch-to-post
version: 4.0.0
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

# Pinch to Post v4 — WordPress Management via WP Pinch

You are an AI agent managing a WordPress site through the **WP Pinch** plugin. WP Pinch registers 25+ WordPress Abilities as MCP tools, giving you full site management capabilities.

## Connection Methods (in order of preference)

### Method 1: MCP (Preferred)
If you have MCP access to the WordPress site, use the WP Pinch MCP tools directly. They are namespaced as `wp-pinch/*`:

- **Content**: `wp-pinch/list-posts`, `wp-pinch/get-post`, `wp-pinch/create-post`, `wp-pinch/update-post`, `wp-pinch/delete-post`, `wp-pinch/list-taxonomies`, `wp-pinch/manage-terms`
- **Media**: `wp-pinch/list-media`, `wp-pinch/upload-media`, `wp-pinch/delete-media`
- **Users**: `wp-pinch/list-users`, `wp-pinch/get-user`, `wp-pinch/update-user-role`
- **Comments**: `wp-pinch/list-comments`, `wp-pinch/moderate-comment`
- **Settings**: `wp-pinch/get-option`, `wp-pinch/update-option`
- **Plugins/Themes**: `wp-pinch/list-plugins`, `wp-pinch/toggle-plugin`, `wp-pinch/list-themes`, `wp-pinch/switch-theme`
- **Analytics**: `wp-pinch/site-health`, `wp-pinch/recent-activity`, `wp-pinch/search-content`, `wp-pinch/export-data`
- **Core**: `core/get-site-info`, `core/get-user-info`, `core/get-environment-info`

### Method 2: REST API Fallback
If MCP is not available, use the WordPress REST API with application passwords:

```bash
# Create a post
curl -X POST "https://SITE_URL/wp-json/wp/v2/posts" \
  -H "Authorization: Basic BASE64_CREDENTIALS" \
  -H "Content-Type: application/json" \
  -d '{"title": "My Post", "content": "Hello world", "status": "draft"}'

# Get site info
curl "https://SITE_URL/wp-json/" \
  -H "Authorization: Basic BASE64_CREDENTIALS"
```

### Method 3: WP Pinch Chat API
For quick interactions, use the WP Pinch chat endpoint:

```bash
curl -X POST "https://SITE_URL/wp-json/wp-pinch/v1/chat" \
  -H "X-WP-Nonce: NONCE" \
  -H "Content-Type: application/json" \
  -d '{"message": "Show me recent posts"}'
```

## Webhook Configuration

WP Pinch sends webhooks to your OpenClaw instance for these events:
- `post_status_change` — Post published, drafted, etc.
- `new_comment` — Comment posted
- `user_register` — New user signup
- `woo_order_change` — WooCommerce order status change
- `post_delete` — Post deleted
- `governance_finding` — Autonomous scan results

Configure in WordPress admin: **WP Pinch > Webhooks**

## Governance Tasks

WP Pinch runs autonomous background checks:
- **Content Freshness** — Flags posts not updated in 180+ days
- **SEO Health** — Checks titles, alt text, content length, featured images
- **Comment Sweep** — Pending moderation and spam counts
- **Broken Links** — Dead link detection (50 links/batch)
- **Security Scan** — Outdated software, debug mode, file editing

Findings are delivered via webhook or processed server-side.

## Best Practices

1. **Always create posts as drafts first**, then publish after user confirmation.
2. **Use MCP tools when available** — they're typed, permission-aware, and audit-logged.
3. **Check site health** before making significant changes.
4. **The option update ability has an allowlist** — only safe options can be modified.
5. **All actions are audit-logged** — check the audit log if something seems off.

## Environment Variables

Set these on your OpenClaw instance:
- `WP_SITE_URL` — Your WordPress site URL
- `WP_APP_PASSWORD` — Application password for REST API fallback
- `WP_USERNAME` — WordPress username for REST API fallback
