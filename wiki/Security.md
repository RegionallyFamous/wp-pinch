# Security

WP Pinch takes security seriously — more seriously than a lobster takes its territory. (Have you ever tried to take a lobster's food? Don't.) This page documents every defense layer in the plugin. We gave the AI keys; we also gave it a very strict bouncer.

---

## Defense Layers

### Authentication & Authorization

- **Capability checks** on every ability execution, including **per-post verification** on meta operations (`current_user_can( 'edit_post', $post_id )`)
- **Per-post checks on revisions** — `list-revisions` and `restore-revision` verify `edit_post` on the (parent) post before listing or restoring
- **Per-attachment check on delete-media** — `delete-media` verifies `current_user_can( 'delete_post', $id )` before deleting the attachment
- **Nonce verification** on all REST and AJAX endpoints. The admin settings forms use WordPress’ Options API (`options.php` and `settings_fields()`), which verifies the option-group nonce on save.
- **Role escalation prevention** — AI agents cannot promote users to administrator or assign roles with dangerous capabilities (`manage_options`, `edit_users`, etc.)
- **Self-role-change prevention** — the current user cannot modify their own role via AI
- **Self-deactivation guard** — WP Pinch cannot be deactivated by its own abilities

### Input Validation

- **Input sanitization** with `sanitize_text_field()`, `sanitize_key()`, `absint()`, `esc_url_raw()` on all parameters
- **Message length limit** — 4,000 characters maximum for chat messages
- **Session key length limit** — 128 characters maximum to prevent transient/cache key abuse
- **Params DoS limit** — webhook and PinchDrop params capped at 100 keys per level and recursion depth 5
- **Nested array rejection** in post meta values
- **CSS injection prevention** on block attributes via regex validation
- **Option allowlists** prevent reading/writing sensitive options (`auth_key`, `auth_salt`, `active_plugins`, `users_can_register`, `default_role`, API token)
- **Option denylist** is hardcoded and runs *before* any filter — cannot be bypassed. Includes `siteurl`, `home`, `admin_email` (site breakage / account takeover risk), auth salts, `active_plugins`, tokens.

### Prompt Injection Defense

When the `prompt_sanitizer` feature flag is enabled (default: on), WordPress content is sanitized before being sent to the AI gateway. Lines matching instruction-injection patterns (e.g. "ignore previous instructions", "disregard prior instructions") are replaced with `[redacted]`.

**Coverage:** Molt (post content), Ghost Writer (draft + voice samples), synthesize/what-do-i-know (content, titles, excerpts), site-digest (titles, excerpts, tldr, taxonomy term names), related-posts (titles), project-assembly (titles and content), quote-bank (extracted quotes). **Governance findings** (content freshness, SEO health, draft necromancer, broken links, Tide Report) and **all webhook payloads** (post titles, comment content, author names, etc.) are recursively sanitized before delivery. Short strings (titles, slugs, names) use `TITLE_PATTERNS` (e.g. `SYSTEM:`, `[INST]`); longer content uses line-based patterns.

**Filters:** `wp_pinch_prompt_sanitizer_patterns` (multi-line content), `wp_pinch_prompt_sanitizer_title_patterns` (short strings), `wp_pinch_prompt_sanitizer_enabled`. Webhook payloads are sanitized before `wp_pinch_webhook_payload` runs. Governance findings pass through `wp_pinch_governance_findings` first; the data sent to the webhook is sanitized in dispatch.

### Output Security

- **Output escaping** with `esc_html()`, `esc_attr()`, `esc_url()` on all rendered content
- **Gateway reply XSS sanitization** via `wp_kses_post()` (non-streaming chat, fallback, and **SSE streaming** — each streamed `data:` line with a `reply` field is sanitized before forwarding)
- **Strict gateway reply sanitization** (optional, **WP Pinch → Connection**): when enabled, chat replies are stripped of HTML comments and instruction-like text, then sanitized with an allowlist that excludes iframe, object, embed, and form to reduce prompt-injection and XSS risk in gateway-returned content
- **Chat markdown** — frontend escapes HTML first; links restricted to `http:`/`https:` only
- **Comment author emails stripped** from all ability responses
- **User emails removed** from ability responses (list-users, get-user, export-data, WooCommerce)
- **WooCommerce PII redaction** -- billing email, last name, payment method title, note authors
- **Gateway URL hidden** from non-admin users in the status endpoint
- **API token masked** on the settings page
- **Security scan** does not leak version numbers for core, plugins, or themes

### Database

- **Prepared SQL statements** everywhere — no raw queries, ever
- **`show_in_rest => false`** on all 24 settings to prevent REST API leakage
- **Existence checks** before modifying posts, comments, terms, and media
- **Audit CSV export** capped at 5,000 rows (DoS mitigation); **Content-Disposition** filename stripped of CRLF and quotes to prevent header injection

### Web Clipper (token-protected capture)

- **Capture token** is stored in the option `wp_pinch_capture_token` (not exposed in REST). It should be a **long-lived secret**; treat it like a password. The **bookmarklet URL may contain the token** in the query string — do not share the URL. Use a strong random value (e.g. 32+ characters). Rate limit: 30 requests per minute per IP. All captures are audit-logged.

### Kill Switch and Read-Only Mode

- **Disable API access:** Set option `wp_pinch_api_disabled` or define `WP_PINCH_DISABLED` in wp-config.php. All REST endpoints (chat, status, hook, molt, ghostwrite, pinchdrop, capture) return 503. Use during incidents or maintenance.
- **Read-only mode:** Set option `wp_pinch_read_only_mode` or define `WP_PINCH_READ_ONLY`. All write abilities are blocked; read abilities and status remain available. Safe for exploring the API against production.
- **Emergency mu-plugin:** Copy `mu-plugin-examples/wp-pinch-emergency-disable.php` to `wp-content/mu-plugins/` for instant disable. Delete the file to re-enable.

### Webhook Loop Detection

When the incoming hook endpoint executes abilities (`execute_ability` or `execute_batch`), a request-scoped flag is set so that outbound webhooks triggered by those ability runs are suppressed. This prevents infinite loops: post publish → webhook → OpenClaw → update-post → post change → webhook.

### Network & API

- **Fixed-duration rate limiting** that doesn't slide (no gaming the window)
- **Rate limit headers** (`X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`) on all REST responses
- **`Retry-After` headers** on all 429 responses
- **`X-WP-Pinch-Trace-Id`** — REST and webhook responses may include this header for request/response correlation and support
- **HMAC-SHA256 webhook signatures** with timestamp replay protection (5-minute window)
- **Circuit breaker** for gateway failures — auto-recovery with half-open probe
- **SSRF prevention** — **Gateway URL validated** with `wp_http_validate_url()` before every outbound request (chat, status, webhook, Ghost Writer, Molt, admin test connection, stream). Internal/private URLs are rejected at request time. Webhooks use `wp_safe_remote_post()` for defense in depth.
- **SSRF prevention** in broken link checker — private IP blocking, DNS resolution check, SSL verification
- **Media upload restricted** to HTTP/HTTPS URL schemes only; **upload-media** URL validated with `wp_http_validate_url()` before `download_url()` to prevent SSRF (no internal/private URLs)
- **Public chat endpoint isolation** with separate rate limiting and session key validation. The public chat rate limit is configurable (default 3/min per IP). Concurrent SSE streams per IP are capped (configurable; default 5). Session keys are generated per connection; reconnecting or clearing client state effectively rotates the key. Chat responses are capped in length (configurable) to limit resource exhaustion.
- **REST security headers** — `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Resource-Policy: same-origin`, `Content-Security-Policy: frame-ancestors 'none'`, `Cache-Control: no-store`; **HSTS** (`Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`) when the request is HTTPS
- **CSV export** — `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'`, nonce-verified

### Infrastructure

- **`Update URI: false`** to prevent third-party update hijacking
- **Complete uninstall cleanup** — all options, transients, audit table, user meta, and cron jobs removed (we leave no claw prints)
- **Multisite cleanup** on uninstall
- **Constant redefinition guards**
- **GDPR-ready** — full integration with WordPress privacy export and erasure

### Token Logging Hygiene

Never log full API or capture tokens. Use `Utils::mask_token( $token )` for debugging — returns `****` + last 4 chars. The Site Health debug info uses masked tokens. Audit log context must never include raw tokens.

### API token storage

The WP Pinch API token is **encrypted at rest** in `wp_options` using `sodium_crypto_secretbox()` with a key derived from WordPress `AUTH_KEY` and `AUTH_SALT`. Legacy plaintext tokens are migrated to encrypted form on first read. If you rotate `AUTH_KEY` or `AUTH_SALT` in wp-config.php, re-enter and save the API token once in **WP Pinch → Connection** so it can be re-encrypted with the new key.

### Credential Management

- **Application passwords over full admin** — When OpenClaw or MCP clients connect to WordPress, use application passwords (Users → Profile → Application Passwords) scoped to the minimum capabilities needed. Never pass the main admin password.
- **Secret reference pattern** — Store API tokens and application passwords in environment variables or a secret manager. Config files should reference `WP_APP_PASSWORD`, `WP_PINCH_API_TOKEN`, etc., not contain plaintext secrets.
- **Rotation** — Rotate the WP Pinch API token and any application passwords on a schedule (e.g. every 90 days). Revoke old application passwords after rotation.
- **Least privilege** — Create a dedicated WordPress user (or use the OpenClaw role) with only the capabilities the agent needs. See [Configuration](Configuration#credentials--security) for setup guidance.

### OpenClaw integration (one-pager)

- **API token** — Stored in options; used for outbound webhooks (Bearer) and to validate incoming webhooks. Treat as a secret; mask in UI.
- **Incoming webhook** — Authenticated by Bearer token or (when enabled) HMAC-SHA256 signature with timestamp; abilities run as a plugin-chosen WordPress admin.
- **Audit log** — All ability runs, webhook sends/failures, and governance events are logged (source, event type, optional context). Use for compliance and debugging.
- **Rate limits** — REST per user (default 10/min), webhooks outbound (default 30/min), public chat per IP (3/min). Configurable in settings.
- **Circuit breaker** — When enabled, gateway failures trip a breaker and block further outbound calls until recovery; prevents cascade failures.
- **HMAC webhooks** — Optional `webhook_signatures` feature: outbound payloads signed with `X-WP-Pinch-Signature` and `X-WP-Pinch-Timestamp`; verify on the OpenClaw side to ensure payloads are from your site.

---

## Web Security Best Practices

WP Pinch applies standard web security practices:

- **Security headers** — All WP Pinch REST responses send `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `Cross-Origin-Resource-Policy`, `Content-Security-Policy: frame-ancestors 'none'`, and `Cache-Control`. When the request is HTTPS, **HSTS** is sent (`Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`). CSV export also sends `Content-Security-Policy: default-src 'none'` (download-only, no script).
- **Input limits** — Message length (4,000), session key (128), and params structure (100 keys/level, depth 5) prevent abuse and DoS from oversized or deeply nested payloads.
- **Rate limiting by IP** — When behind a reverse proxy, client IP is taken from `X-Forwarded-For` or similar. That header can be spoofed; rate limiting is best-effort. Use a proxy or WAF that validates and overwrites the header when possible.
- **Trust boundary** — The OpenClaw gateway is trusted for chat and webhook requests. Gateway replies are sanitized with `wp_kses_post()` before being returned (including **streaming SSE**: each `data:` line with a `reply` field is parsed, sanitized, and re-encoded). The chat frontend also escapes HTML and restricts links to `http`/`https` in markdown.

---

## Testing & Static Analysis

| Layer | Tool | What It Catches |
|---|---|---|
| **Static Analysis** | PHPStan Level 6 | Type mismatches, null access, undefined properties |
| **Coding Standards** | PHPCS (WordPress-Extra + Security) | Security violations, escaping, sanitization, naming |
| **Unit Tests** | PHPUnit (160+ tests) | Functional correctness, security guards, edge cases |
| **CI Pipeline** | GitHub Actions | All of the above on every push |
| **Pre-commit Hook** | PHPCS + PHPStan | Catches issues before they reach the repo |

---

## Reporting Vulnerabilities

If you find a security vulnerability, please report it responsibly. See [SECURITY.md](https://github.com/RegionallyFamous/wp-pinch/blob/main/SECURITY.md) for our disclosure policy and contact information.

Do not open a public GitHub issue for security vulnerabilities.
