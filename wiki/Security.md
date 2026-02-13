# Security

WP Pinch takes security seriously — more seriously than a lobster takes its territory. This page documents every defense layer in the plugin.

---

## Defense Layers

### Authentication & Authorization

- **Capability checks** on every ability execution, including **per-post verification** on meta operations (`current_user_can( 'edit_post', $post_id )`)
- **Per-post checks on revisions** — `list-revisions` and `restore-revision` verify `edit_post` on the (parent) post before listing or restoring
- **Per-attachment check on delete-media** — `delete-media` verifies `current_user_can( 'delete_post', $id )` before deleting the attachment
- **Nonce verification** on all REST and AJAX endpoints
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
- **Option denylist** is hardcoded and runs *before* any filter — cannot be bypassed

### Output Security

- **Output escaping** with `esc_html()`, `esc_attr()`, `esc_url()` on all rendered content
- **Gateway reply XSS sanitization** via `wp_kses_post()` (non-streaming chat, fallback, and **SSE streaming** — each streamed `data:` line with a `reply` field is sanitized before forwarding)
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

### Network & API

- **Fixed-duration rate limiting** that doesn't slide (no gaming the window)
- **Rate limit headers** (`X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`) on all REST responses
- **`Retry-After` headers** on all 429 responses
- **HMAC-SHA256 webhook signatures** with timestamp replay protection (5-minute window)
- **Circuit breaker** for gateway failures — auto-recovery with half-open probe
- **SSRF prevention** — **Gateway URL validated** with `wp_http_validate_url()` before every outbound request (chat, status, webhook, Ghost Writer, Molt, admin test connection, stream). Internal/private URLs are rejected at request time
- **SSRF prevention** in broken link checker — private IP blocking, DNS resolution check, SSL verification
- **Media upload restricted** to HTTP/HTTPS URL schemes only; **upload-media** URL validated with `wp_http_validate_url()` before `download_url()` to prevent SSRF (no internal/private URLs)
- **Public chat endpoint isolation** with separate rate limiting and session key validation
- **REST security headers** — `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Resource-Policy: same-origin`, `Content-Security-Policy: frame-ancestors 'none'`, `Cache-Control: no-store`; **HSTS** (`Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`) when the request is HTTPS
- **CSV export** — `Cache-Control: no-store`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'`, nonce-verified

### Infrastructure

- **`Update URI: false`** to prevent third-party update hijacking
- **Complete uninstall cleanup** — all options, transients, audit table, user meta, and cron jobs removed (we leave no claw prints)
- **Multisite cleanup** on uninstall
- **Constant redefinition guards**
- **GDPR-ready** — full integration with WordPress privacy export and erasure

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
