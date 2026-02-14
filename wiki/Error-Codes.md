# API and MCP Error Codes

WP Pinch returns stable, machine-readable error codes in REST and MCP responses so OpenClaw and other clients can handle them consistently (retry, show message, or fix input).

---

## Response shape

- **REST:** On error, the JSON body includes `code` and `message`. For `WP_Error`, WordPress sends `code` from `get_error_code()` and `message` from `get_error_message()`; HTTP status is in the response status code (e.g. 400, 401, 403, 429, 502, 503).
- **Rate limiting:** When rate limit is exceeded, the response is HTTP 429 with body `{ "code": "rate_limited", "message": "..." }`. Use the `Retry-After` and `X-RateLimit-*` headers when present.

---

## Error codes and handling

| Code | HTTP | Meaning | Recommended handling |
|------|------|---------|----------------------|
| `rate_limited` | 429 | Too many requests in the window | Wait and retry; respect `Retry-After` and `X-RateLimit-Reset` |
| `validation_error` | 400 | Invalid or missing parameter (e.g. empty message, post_id &lt; 1, length over limit) | Fix the request (params, lengths); do not retry unchanged |
| `rest_invalid_param` | 400 | Same as validation_error (legacy) | Same as validation_error |
| `capability_denied` | 403 | Current user lacks permission (e.g. edit_posts) | Show message; do not retry |
| `rest_forbidden` | 401/403 | Invalid/missing auth token or insufficient permission | Check token/capability; do not retry with same token |
| `not_configured` | 503 | Gateway URL or API token not set | Ask site admin to configure WP Pinch |
| `invalid_gateway` | 502 | Gateway URL failed validation (e.g. internal/private URL) | Do not retry; configuration issue |
| `invalid_timestamp` | 400 | Webhook HMAC timestamp invalid or non-numeric | Do not retry; check signature flow |
| `gateway_error` | 502 | Outbound call to OpenClaw gateway failed or returned error | Retry with backoff for transient failures |
| `post_not_found` | 404 | Post ID does not exist or is not readable | Suggest listing or searching; do not retry same ID |
| `missing_ability` | 400 | execute_ability called without `ability` param | Send `ability` in request |
| `unknown_ability` | 400 | Ability name not registered | Use a valid ability name from the abilities list |
| `ability_disabled` | 400 | Ability is disabled in settings | Do not retry; feature may be toggled off |
| `abilities_unavailable` | 503 | WordPress Abilities API not available | Retry later or report to admin |
| `no_admin` | 503 | No administrator found to run the ability | Configuration/role issue; do not retry |
| `ability_error` | 502 | Ability ran but returned an error (message in response) | Log message; retry only if idempotent and message suggests transient failure |
| `missing_task` | 400 | run_governance without `task` param | Send `task` in request |
| `unknown_task` | 400 | Governance task name not recognized | Use a valid task name |
| `task_unavailable` | 503 | Governance task method not available | Do not retry |
| `unknown_action` | 400 | Incoming webhook action not ping / execute_ability / execute_batch / run_governance | Use a valid action |
| `capture_not_configured` | 503 | Web Clipper capture token not set | Admin must set token |
| `pinchdrop_disabled` | 503 | PinchDrop is disabled | Do not retry |
| `invalid_source` | 400 | PinchDrop source not in allowlist | Use an allowed source or ask admin to allowlist |
| `no_author` | 503 | No administrator to create post (e.g. capture) | Configuration issue |
| `create_failed` | 500 | Post creation failed (WordPress error) | Retry only if idempotent; check message |
| `missing_post_id` | 400 | Ghostwrite or Molt requires a post ID | Send post_id (e.g. /ghostwrite 123, /molt 123) |
| `invalid_action` | 400 | Ghostwrite/Molt action invalid (use list/write or valid action) | Fix action parameter |
| `forbidden` | 403 | User cannot read the specified post (e.g. Molt on another author’s private post) | Do not retry; permission issue |

---

## MCP

MCP tool errors are returned in the same way: the server includes a stable `code` (or equivalent) and a human-readable message. Use the same handling as above (e.g. `rate_limited` → back off; `validation_error` → fix input; `capability_denied` → show message).
