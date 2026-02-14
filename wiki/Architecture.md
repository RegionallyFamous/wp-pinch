# Architecture

How the lobster trap is wired. WP Pinch sits inside WordPress and talks to OpenClaw; agents go in, do work, and report back.

## System Overview

```
┌──────────────────────────────────────────────────────────┐
│                        WordPress                          │
│                                                           │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────┐  │
│  │  Abilities   │  │  MCP Server  │  │   Governance   │  │
│  │  (38+ tools)  │──│  (endpoint)  │  │   (8 tasks)    │  │
│  └──────┬───────┘  └──────┬───────┘  └───────┬────────┘  │
│         │                 │                   │           │
│  ┌──────┴─────────────────┴───────────────────┴────────┐ │
│  │              Webhook Dispatcher                      │ │
│  │    (retry + rate limiting + HMAC signatures)         │ │
│  └──────────────────────┬──────────────────────────────┘ │
│                         │                                 │
│  ┌──────────────────────┴──────────────────────────────┐ │
│  │                  Audit Log                           │ │
│  │            (90-day retention)                        │ │
│  └─────────────────────────────────────────────────────┘ │
│                                                           │
│  ┌─────────────────────────────────────────────────────┐ │
│  │              Pinch Chat Block                        │ │
│  │   (Interactivity API + SSE streaming)                │ │
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

---

## Subsystems

### Abilities Engine (`class-abilities.php`)

The core of WP Pinch. Registers 38 WordPress abilities (plus WooCommerce abilities when available) and exposes them through both the MCP server and the incoming webhook receiver. Each ability is a self-contained function with:

- A capability requirement (e.g., `edit_posts`)
- Input parameter definitions with sanitization rules
- Existence validation (posts, terms, media must exist before modification)
- Audit logging on every execution
- Optional caching with object cache support (Redis/Memcached)

### MCP Server

Registers a `wp-pinch` MCP endpoint at `/wp-json/wp-pinch/v1/mcp`. This is the primary interface for MCP-compatible AI clients (including OpenClaw). Only abilities that are enabled via the admin UI and pass through the `wp_pinch_abilities` filter are discoverable.

### Webhook Dispatcher (`class-webhook-dispatcher.php`)

Fires events to OpenClaw when things happen on your site:

- Post published, updated, or trashed
- New comments
- User registration
- WooCommerce order status changes

Features:
- **Exponential backoff retry** -- up to 4 attempts (5min, 30min, 2hr, 12hr)
- **Fixed-duration rate limiting** -- prevents flooding the gateway
- **HMAC-SHA256 signatures** -- with timestamp replay protection
- **Circuit breaker** -- fails fast when the gateway is down, auto-recovers with half-open probe

### Incoming Webhook Receiver

The `/hooks/receive` endpoint lets OpenClaw push ability execution requests back to WordPress. HMAC-SHA256 verified, rate-limited, and fully logged.

### Governance Engine

Eight recurring background tasks run via Action Scheduler (even lobsters pace themselves):

| Task | What It Catches |
|---|---|
| **Content Freshness** | Posts that haven't been updated in ages |
| **SEO Health** | Missing meta descriptions, short titles, images without alt text |
| **Comment Sweep** | Spam, orphaned comments, and other bottom-feeders |
| **Broken Link Detection** | Dead links lurking in your content |
| **Security Scanning** | Suspicious plugin changes, available updates |
| **Draft Necromancer** | Abandoned drafts worth resurrecting (Ghost Writer) |
| **Spaced Resurfacing** | Notes not updated in N days — revisit list by category/tag |
| **Tide Report** | Daily digest — bundles content freshness, SEO, comments, and (optionally) draft necromancer into one webhook payload |

Findings are delivered via webhook to OpenClaw or processed server-side. Tasks can run on a schedule or be triggered manually via WP-CLI (`wp pinch governance run`). Set it and check it — like a lobster trap.

### Pinch Chat Block

A Gutenberg block built with the WordPress Interactivity API. See the [Chat Block](Chat-Block) page for full details.

### Audit Log (`class-audit-table.php`)

Every ability execution, webhook dispatch, governance finding, and chat message is logged to a custom database table. Nothing happens on your site without leaving a trail — even lobsters leave tracks on the ocean floor. Features:

- **90-day automatic retention** -- old entries are purged by a scheduled task
- **Admin UI** -- browse, search, filter by date, export as CSV
- **WP-CLI** -- `wp pinch audit list` with format support (table, json, csv, yaml)
- **GDPR integration** -- data export and erasure via WordPress's privacy tools

### Circuit Breaker (`class-circuit-breaker.php`)

Wraps outbound HTTP calls to the gateway. When the gateway is down, we fail fast instead of flailing — no point waving your claws at an empty ocean. Three states:

- **Closed** -- normal operation, all requests go through
- **Open** -- gateway is down, requests fail fast without attempting the call
- **Half-Open** -- one probe request is allowed to check if the gateway has recovered

Configurable failure threshold and recovery timeout. Admin notice when the circuit is open.

### Feature Flags (`class-feature-flags.php`)

12 boolean toggles for enabling/disabling features without code changes:

- `streaming_chat`, `webhook_signatures`, `circuit_breaker`, `ability_toggle`
- `webhook_dashboard`, `audit_search`, `health_endpoint`, `public_chat`
- `slash_commands`, `token_display`, `pinchdrop_engine`, `ghost_writer`

Toggle via the admin UI (WP Pinch > Features) or override with a filter. Your reef, your rules.

### REST Controller (`class-rest-controller.php`)

All REST API endpoints:

| Endpoint | Method | Auth | Purpose |
|---|---|---|---|
| `/wp-pinch/v1/chat` | POST | `edit_posts` | Send a chat message |
| `/wp-pinch/v1/chat/stream` | POST | `edit_posts` | SSE streaming chat |
| `/wp-pinch/v1/chat/public` | POST | Feature flag | Public (anonymous) chat |
| `/wp-pinch/v1/session/reset` | POST | `edit_posts` or flag | Mint a new session key |
| `/wp-pinch/v1/status` | GET | `manage_options` | Plugin status and health |
| `/wp-pinch/v1/health` | GET | None | Public health check |
| `/wp-pinch/v1/abilities` | GET | `edit_posts` | List abilities (name, title, description, input_schema) for discovery |
| `/wp-pinch/v1/hooks/receive` | POST | HMAC | Incoming webhook receiver (execute_ability, execute_batch, run_governance, ping) |
| `/wp-pinch/v1/mcp` | Varies | MCP | MCP server endpoint |
