# OpenClaw Skill for WP Pinch

**Part of [WP Pinch](https://wp-pinch.com)** — a WordPress plugin that connects your site to OpenClaw. [ClawHub](https://clawhub.ai/nickhamze/pinch-to-post) · [GitHub](https://github.com/RegionallyFamous/wp-pinch) · [Configuration](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration)

**Install via ClawHub** (easiest):

```bash
clawhub install nickhamze/pinch-to-post
```

Or copy this page into your OpenClaw workspace skills (e.g. `~/.openclaw/workspace/skills/wp-pinch/SKILL.md` or use as a tools/prompt snippet) so the agent knows when and how to use WP Pinch abilities.

---

## Which WordPress site?

Set **`WP_SITE_URL`** (e.g. `https://mysite.com`) on your OpenClaw instance — that's how you choose which WordPress site the skill uses. Also set `WP_USERNAME` and `WP_APP_PASSWORD` for REST auth. For multiple sites, use different workspaces or env configs. See [Configuration](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration) for the full setup.

---

## When to use WP Pinch

- User asks to **list, create, update, or delete posts** → use `list-posts`, `get-post`, `create-post`, `update-post`, `delete-post`.
- User asks to **find a post by ID or title** → use `list-posts` with search/params, then `get-post` with the returned ID.
- User wants to **capture an idea or link** into WordPress → use **PinchDrop**: send to `pinchdrop-generate` (or have the user use the Web Clipper / channel capture that hits `/wp-pinch/v1/pinchdrop/capture`).
- User wants **one post turned into many formats** (social, email, FAQ, meta description) → use **Molt**: ability `wp-pinch/molt` or slash `/molt <post_id>`.
- User asks **“what do I know about X?”** or **semantic-style search** → use `what-do-i-know` with a `query`.
- User wants **posts they haven’t touched in a while** → use `spaced-resurfacing` (or the daily governance / Tide Report).
- User wants **abandoned drafts completed in their voice** → use Ghost Writer: `list-abandoned-drafts`, then `ghostwrite` with a `post_id`.

---

## Ability naming

- **MCP tool names** are under the `wp-pinch/` namespace, e.g. `wp-pinch/list-posts`, `wp-pinch/get-post`, `wp-pinch/pinchdrop-generate`, `wp-pinch/molt`.
- **Skill usage:** Use MCP tools only. Do not execute raw HTTP, curl, or fetch requests. The skill operates exclusively through MCP.

---

## Quick reference

| Goal | Primary ability | Follow-up |
|------|-----------------|-----------|
| List posts (with filters) | `list-posts` | Use `get-post` with an ID for full content |
| Get one post | `get-post` | `post_id` required |
| Create/update/delete post | `create-post`, `update-post`, `delete-post` | |
| Capture idea → draft pack | `pinchdrop-generate` | Or use capture endpoint; then optionally create drafts |
| One post → many formats | `wp-pinch/molt` or `molt` | `post_id` + optional `output_types` |
| “What do I know about X?” | `what-do-i-know` | `query` |
| Stale posts | `spaced-resurfacing` | `days`, optional `category`, `tag`, `limit` |
| Complete abandoned draft | `list-abandoned-drafts` then `ghostwrite` | `post_id` for ghostwrite |
| Content health report | `content-health-report` | Structure, readability, etc. |
| Suggest taxonomy terms | `suggest-terms` | `post_id` or `content` |
| Media | `list-media`, `upload-media`, `delete-media` | |
| Users / comments / settings | `list-users`, `get-user`, `list-comments`, `moderate-comment`, `get-option`, `update-option` | See Abilities Reference |

---

## Errors

- **`rate_limited`** — Back off and retry after a short delay; respect `Retry-After` if present.
- **`validation_error`** or **`rest_invalid_param`** — Fix the request (e.g. required param, length limit); do not retry unchanged.
- **`capability_denied`** / **`rest_forbidden`** — User does not have permission; show a clear message, do not retry.
- **`post_not_found`** — Post ID invalid or deleted; suggest listing or searching.
- **`not_configured`** — Site has not set Gateway URL or API token; ask admin to configure WP Pinch.

See [Error Codes](Error-Codes) for the full list and recommended handling.
