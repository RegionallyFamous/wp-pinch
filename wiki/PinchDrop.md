# PinchDrop (Capture Anywhere)

PinchDrop lets ideas from any OpenClaw-connected channel — Slack, Telegram, WhatsApp, you name it — become structured WordPress Draft Packs automatically. Think of it as your lobster antenna twitching every time someone has a good idea. Capture it. Structure it. Optionally save it as a draft.

---

## Flow

1. User sends an idea in Slack/Telegram/WhatsApp/etc.
2. OpenClaw forwards normalized text to WP Pinch.
3. WP Pinch receives `POST /wp-json/wp-pinch/v1/pinchdrop/capture`.
4. The endpoint calls the `wp-pinch/pinchdrop-generate` ability.
5. WP Pinch returns Draft Pack output and (optionally) creates draft posts.
6. Audit log records a `pinchdrop_capture` event.

---

## Endpoint

`POST /wp-json/wp-pinch/v1/pinchdrop/capture`

### Required fields

- `text` (string)

### Optional fields

- `source` (string, channel slug)
- `author` (string)
- `request_id` (string, idempotency key)
- `options.output_types` (array: `post`, `product_update`, `changelog`, `social`)
- `options.tone` (string)
- `options.audience` (string)
- `options.save_as_draft` (boolean)
- `options.save_as_note` (boolean) — **Quick Drop:** skip AI expansion; create a minimal post (title + body only). Ideal for quick captures from any channel.

---

## Authentication

PinchDrop uses the same trust model as inbound hooks:

- `Authorization: Bearer <token>` (or `X-OpenClaw-Token`)
- Optional HMAC replay-protected signature when enabled:
  - `X-WP-Pinch-Signature: v1=<hmac>`
  - `X-WP-Pinch-Timestamp: <unix-seconds>`

If webhook signatures are enabled, HMAC timestamp validation rejects stale requests.

---

## Idempotency

If `request_id` is provided, WP Pinch caches the result briefly and suppresses duplicate draft creation for repeated submissions with the same key.

Response includes:

- `deduplicated: false` on first request
- `deduplicated: true` on repeated request

---

## Draft Pack Output

`pinchdrop-generate` returns:

- `draft_pack.post` (`title`, `content`) when requested
- `draft_pack.product_update` (`title`, `content`) when requested
- `draft_pack.changelog` (`title`, `content`) when requested
- `draft_pack.social.snippets` (array) when requested
- `created_drafts` map when auto-save is enabled

When drafts are saved, each post stores trace metadata:

- `wp_pinch_generator = pinchdrop`
- `wp_pinch_pinchdrop_source`
- `wp_pinch_pinchdrop_author`
- `wp_pinch_pinchdrop_request_id`
- `wp_pinch_pinchdrop_created_at`

---

## Settings

In **WP Pinch -> Connection**:

- Enable PinchDrop
- Default output types
- Auto-save generated drafts
- Allowed capture sources (optional allowlist)

In **WP Pinch -> Features**:

- Enable `pinchdrop_engine` feature flag

Both the feature flag and settings toggle must be enabled for capture requests to run.

---

## Web Clipper (browser / bookmarklet capture)

Because sometimes the best idea hits you in a random tab and you don't want to open WhatsApp to save it. WP Pinch provides a **token-protected** one-shot capture endpoint for saving from the browser (e.g. a bookmarklet or browser extension), without going through OpenClaw.

**Endpoint:** `POST /wp-json/wp-pinch/v1/capture`

**Authentication:** Token in query param `token` or header `X-WP-Pinch-Capture-Token`, validated against the **Web Clipper capture token** set in **WP Pinch → Connection**. The token is stored in the option `wp_pinch_capture_token`; it should be long-lived and secret. Because bookmarklets often put the token in the URL, **do not share the capture URL**.

**Body (JSON):**

- `text` (string, required) — captured text (max 50,000 characters)
- `url` (string, optional) — source URL (prepended to content and can be used to derive title)
- `title` (string, optional) — post title; if omitted and `url` is set, hostname is used; otherwise "Captured note"
- `save_as_note` (boolean, optional, default true) — creates a minimal draft post (title + content only)

**Response:** `201` with `post_id` and `edit_url`. Rate limit: 30 requests/minute per IP. All captures are logged in the Audit Log.
