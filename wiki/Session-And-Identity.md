# Session and Identity (OpenClaw ↔ WordPress)

This page explains how OpenClaw’s gateway, tokens, and sessions map to WordPress users and permissions so you can reason about who is acting when abilities run or webhooks are delivered.

---

## MCP and REST: who runs abilities?

- **MCP:** When OpenClaw (or any MCP client) connects to `GET /wp-json/wp-pinch/mcp`, the request is authenticated by WordPress (cookie/session or application password). The **current WordPress user** for that request is the identity under which abilities run. That user must have at least `edit_posts` for most WP Pinch endpoints and abilities.
- **REST (e.g. Pinch Chat block):** Same as MCP — the logged-in WordPress user’s capabilities apply. Rate limiting is per user (and for public chat, per IP when `public_chat` is on).
- **Incoming webhook:** OpenClaw calls `POST /wp-json/wp-pinch/v1/hooks/receive` with a **Bearer token** (or HMAC signature). That token is the **API token** configured in WP Pinch (one per site). Abilities executed via the hook run as a **WordPress user chosen by the plugin** (typically an administrator), not “the OpenClaw user” — because the webhook request has no WordPress session. So: **one token ⇒ one site; abilities run as a single WP identity (e.g. first admin)**.

---

## Summary

| Channel | Authentication | WordPress identity |
|---------|----------------|-------------------|
| MCP (browser/app with WP login) | WordPress session or app password | Logged-in WP user |
| REST (Pinch Chat, status, etc.) | WordPress session or app password | Logged-in WP user |
| Incoming webhook (execute_ability, run_governance) | API token or HMAC | Plugin-chosen admin (no per-OpenClaw-user mapping) |

So: **one token does not mean one OpenClaw end-user**; it means “this site allows this gateway to trigger abilities as a fixed WP user.” Multi-identity (e.g. different OpenClaw users → different WP users) would require a different design (e.g. passing a user key in the webhook and mapping it to a WP user).

---

## Chat session key

- **Session key** (e.g. in `/chat` or Pinch Chat) is an opaque string used to group messages into one conversation (e.g. for the gateway). It is **not** a WordPress user ID and does not by itself change who runs abilities; the WordPress user is still determined by the authenticated request (cookie/app password).

---

## Webhook outbound (WordPress → OpenClaw)

- Outbound webhooks use the same **API token** for `Authorization: Bearer …` when sending to the gateway. They do not carry a “WordPress user ID” in the payload; they can include `metadata.site_url` and `metadata.data` (e.g. post author display name) so the agent can reason about the event. Identity for “who did this in WordPress” is in the event data (e.g. author), not in a separate user mapping.

---

## Practical implications

- **Single site, single bot:** One OpenClaw connection (MCP or webhook) and one API token; abilities and governance run as the configured WP identity. No extra setup.
- **Multi-user OpenClaw:** If multiple people use OpenClaw and you want abilities to run “as” different WordPress users, they must use MCP (or REST) with **WordPress authentication** (e.g. each user has a WordPress account and uses the Pinch Chat block or an MCP client that sends their WP session). The webhook path does not support per–OpenClaw-user WP identity today.
