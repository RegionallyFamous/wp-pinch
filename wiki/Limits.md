# Rate Limits and Pagination

Stable limits so OpenClaw and other clients can rely on predictable behavior. All limits are configurable where noted.

---

## Rate limits

| Scope | Default | Configurable | Notes |
|-------|---------|--------------|--------|
| **REST (chat, status, abilities, Molt, Ghost Writer)** | 10 requests/min per user | Yes — **WP Pinch → Connection → Rate Limit** (`wp_pinch_rate_limit`) | Applies to authenticated REST (including GET /abilities); 429 with `rate_limited` when exceeded |
| **Public chat** (unauthenticated) | 3 requests/min per IP | No (hardcoded) | Stricter to limit abuse |
| **Web Clipper capture** | 30 requests/min per IP | No | Per `handle_web_clipper_capture` |
| **PinchDrop capture** | Endpoint-specific limit by source + IP | No | Lightweight per-endpoint throttle |
| **Outbound webhooks** (WordPress → OpenClaw) | 30/min (site-wide) | Yes — **WP Pinch → Connection → Rate Limit** (same option can affect UI label; webhook code uses `wp_pinch_rate_limit` for max per minute) | Actually webhook uses `get_option( 'wp_pinch_rate_limit', 30 )` in `check_rate_limit`; see class-webhook-dispatcher. So default 30/min. |
| **Daily write budget** | Per-day cap (0 = no limit) | Yes — **WP Pinch → Connection → Daily write budget** (`wp_pinch_daily_write_cap`) | When set &gt; 0, create/update/delete posts, media, options, etc. count toward the cap. When exceeded, requests return 429 (`daily_write_budget_exceeded`) until midnight (site time). Optional alert email at a threshold %. See [Error Codes](Error-Codes). |

Response headers when rate limit applies: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`, and `Retry-After` on 429.

---

## Pagination and list caps

List-style abilities accept `per_page` or `limit` and are capped as follows (defaults in parentheses):

| Ability / endpoint | Parameter | Default | Max |
|--------------------|-----------|---------|-----|
| list-posts | per_page | 20 | 100 |
| list-media | per_page | 20 | 100 |
| list-comments | per_page | 20 | 100 |
| list-users | per_page | 20 | 100 |
| list-taxonomies / terms | per_page | 20 | 100 |
| search-content | per_page | 20 | 100 |
| related-posts | limit | 20 | 50 |
| suggest-links | limit | 15 | 50 |
| quote-bank | max | 15 | — |
| what-do-i-know | per_page | 10 | 25 (synthesize cap) |
| synthesize | per_page | 10 | 25 (filterable) |
| spaced-resurfacing | limit | 50 | 200 |
| knowledge-graph | limit | 200 | 500 |
| Audit log export (CSV) | per_page | 50 | 5,000 (export cap) |

Exceeding max is not an error; the plugin clamps to the cap (e.g. `min( requested, 100 )` for list-posts).

---

## Message and payload sizes

| Item | Limit |
|------|--------|
| execute_batch (inbound webhook) | Max 10 items per batch |
| Chat message | 4,000 characters |
| Session key | 128 characters |
| Webhook/PinchDrop params | 100 keys per level, max depth 5 (DoS protection) |
| PinchDrop capture text | 20,000 characters |
| Web Clipper capture text | 50,000 characters |
| Molt post content (input) | 8,000 characters (truncated with ellipsis if over) |

See [Configuration](Configuration) and [Error Codes](Error-Codes) for validation errors when limits are exceeded.
