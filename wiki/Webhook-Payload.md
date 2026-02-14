# Webhook Payload Reference

WP Pinch sends webhooks to the OpenClaw gateway when configured events occur. This page documents the **event types** and the **JSON payload shape** so you can consume them in OpenClaw or custom handlers.

---

## Endpoint

Outbound webhooks are sent to:

```
{gateway_url}/hooks/agent
```

Example: `https://your-gateway.openclaw.ai/hooks/agent`

- **Method:** POST  
- **Content-Type:** application/json  
- **Authorization:** Bearer {api_token}  
- Optional: **X-WP-Pinch-Signature** and **X-WP-Pinch-Timestamp** when HMAC webhook signatures are enabled (see [Security](Security)).

---

## Payload shape

Every webhook body has this structure:

```json
{
  "message": "[WordPress – {event}] {human-readable summary}",
  "sessionKey": "wp-pinch-{event}",
  "wakeMode": "always",
  "channel": "wp-pinch",
  "metadata": {
    "event": "{event}",
    "site_url": "https://your-site.com",
    "timestamp": "2025-02-12T12:00:00+00:00",
    "data": { ... }
  }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `message` | string | Prefixed with `[WordPress – {event}]`; summary for the agent |
| `sessionKey` | string | Stable key for this event type (e.g. `wp-pinch-post_status_change`) |
| `wakeMode` | string | `always` (configurable per event in settings) |
| `channel` | string | `wp-pinch` (configurable) |
| `metadata.event` | string | Event type (see below) |
| `metadata.site_url` | string | WordPress home URL |
| `metadata.timestamp` | string | ISO 8601 |
| `metadata.data` | object | Event-specific payload |

---

## Event types and `metadata.data`

### post_status_change

Fired when a post’s status changes (e.g. draft → publish).

```json
{
  "post_id": 123,
  "post_title": "My Post",
  "post_type": "post",
  "old_status": "draft",
  "new_status": "publish",
  "url": "https://your-site.com/my-post/",
  "author": "Jane Doe"
}
```

### new_comment

Fired when a new comment is posted.

```json
{
  "comment_id": 456,
  "post_id": 123,
  "author": "Commenter Name",
  "content": "First 50 words of comment...",
  "status": "approved"
}
```

### user_register

Fired when a new user registers.

```json
{
  "user_id": 2,
  "display_name": "New User",
  "roles": ["subscriber"]
}
```

### woo_order_change

Fired when a WooCommerce order’s status changes.

```json
{
  "order_id": 789,
  "old_status": "processing",
  "new_status": "completed"
}
```

### post_delete

Fired when a post is deleted.

```json
{
  "post_id": 123,
  "post_title": "Deleted Post",
  "post_type": "post"
}
```

### governance_finding

Fired when a governance task reports a finding (e.g. content freshness, SEO, Tide Report). Shape is task-dependent; typically includes a summary and identifiers (e.g. post IDs, task name).

---

## Filtering the payload

You can change the payload before it is sent:

```php
add_filter( 'wp_pinch_webhook_payload', function ( array $payload, string $event, array $data ) {
    $payload['metadata']['custom'] = 'value';
    return $payload;
}, 10, 3 );
```

---

## HMAC signature (optional)

When the **webhook_signatures** feature flag is enabled:

- **X-WP-Pinch-Signature:** `v1={hex(HMAC-SHA256(timestamp + "." + body, api_token))}`
- **X-WP-Pinch-Timestamp:** Unix timestamp used in the signature

Verify by recomputing HMAC-SHA256 of `{timestamp}.{raw_body}` with the API token and comparing to the header. Reject requests with timestamps older than 5 minutes (replay protection).

---

## Rate limiting and retries

- Outbound webhooks are rate-limited (default 30 per minute; configurable in **WP Pinch → Connection**).
- Failed deliveries are retried with exponential backoff (Action Scheduler). See [Configuration](Configuration) and [Security](Security).
