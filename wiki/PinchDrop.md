# PinchDrop (Capture Anywhere)

PinchDrop lets OpenClaw-forwarded ideas from any connected channel become structured WordPress Draft Packs automatically.

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
