# OpenClaw Skill for WP Pinch

**[WP Pinch](https://wp-pinch.com)** turns your WordPress site into 88+ MCP tools you can use from OpenClaw. Publish posts, repurpose content with Molt, capture ideas with PinchDrop, manage WooCommerce orders, and run governance scans — all from chat.

[ClawHub](https://clawhub.ai/nickhamze/pinch-to-post) · [GitHub](https://github.com/RegionallyFamous/wp-pinch) · [Configuration](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration)

**Install via ClawHub:**

```bash
clawhub install nickhamze/pinch-to-post
```

Or copy the SKILL.md into your OpenClaw workspace skills (e.g. `~/.openclaw/workspace/skills/wp-pinch/SKILL.md`).

---

## Quick Start

1. **Install the WP Pinch plugin** from [GitHub](https://github.com/RegionallyFamous/wp-pinch) or [wp-pinch.com](https://wp-pinch.com).
2. **Set `WP_SITE_URL`** in your OpenClaw environment (e.g. `https://mysite.com`).
3. **Point your MCP server** at `{WP_SITE_URL}/wp-json/wp-pinch/v1/mcp` with an Application Password.
4. **Start chatting** — say "list my recent posts" or "create a draft about..."

For multiple sites, use different workspaces or env configs. See [Configuration](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration) for the full setup.

---

## When to use WP Pinch

- User asks to **list, create, update, or delete posts** → `list-posts`, `get-post`, `create-post`, `update-post`, `delete-post`
- User asks to **find a post by ID or title** → `list-posts` with search, then `get-post`
- User wants to **capture an idea or link** → **PinchDrop**: `pinchdrop-generate` (or Web Clipper / bookmarklet)
- User wants **one post turned into many formats** → **Molt**: `wp-pinch/molt` with a `post_id`
- User asks **"what do I know about X?"** → `what-do-i-know` with a `query`
- User wants **stale posts surfaced** → `spaced-resurfacing` (or daily Tide Report)
- User wants **abandoned drafts completed in their voice** → Ghost Writer: `list-abandoned-drafts` then `ghostwrite`

---

## Tool naming

All MCP tools are namespaced `wp-pinch/*` — e.g. `wp-pinch/list-posts`, `wp-pinch/molt`, `wp-pinch/pinchdrop-generate`.

---

## Quick reference

| Goal | Primary ability | Follow-up |
|------|-----------------|-----------|
| List posts (with filters) | `list-posts` | Use `get-post` with an ID for full content |
| Get one post | `get-post` | `post_id` required |
| Create/update/delete post | `create-post`, `update-post`, `delete-post` | |
| Capture idea → draft pack | `pinchdrop-generate` | Or use capture endpoint; then optionally create drafts |
| One post → many formats | `wp-pinch/molt` | `post_id` + optional `output_types` |
| "What do I know about X?" | `what-do-i-know` | `query` |
| Stale posts | `spaced-resurfacing` | `days`, optional `category`, `tag`, `limit` |
| Complete abandoned draft | `list-abandoned-drafts` then `ghostwrite` | `post_id` for ghostwrite |
| Content health report | `content-health-report` | Structure, readability, etc. |
| Suggest taxonomy terms | `suggest-terms` | `post_id` or `content` |
| Media | `list-media`, `upload-media`, `delete-media` | |
| Users / comments / settings | `list-users`, `get-user`, `list-comments`, `moderate-comment`, `get-option`, `update-option` | See Abilities Reference |

---

## Errors

- **`rate_limited`** — Back off and retry; respect `Retry-After` if present.
- **`validation_error`** or **`rest_invalid_param`** — Fix the request (missing param, length limit); don't retry unchanged.
- **`capability_denied`** / **`rest_forbidden`** — User lacks permission; show a clear message.
- **`post_not_found`** — Post ID invalid or deleted; suggest listing or searching.
- **`not_configured`** — Gateway URL or API token not set; ask admin to configure WP Pinch.

See [Error Codes](Error-Codes) for the full list.
