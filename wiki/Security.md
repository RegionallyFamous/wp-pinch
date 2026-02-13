# Security

WP Pinch takes security seriously. This page documents every defense layer in the plugin.

---

## Defense Layers

### Authentication & Authorization

- **Capability checks** on every ability execution, including **per-post verification** on meta operations (`current_user_can( 'edit_post', $post_id )`)
- **Nonce verification** on all REST and AJAX endpoints
- **Role escalation prevention** -- AI agents cannot promote users to administrator or assign roles with dangerous capabilities (`manage_options`, `edit_users`, etc.)
- **Self-role-change prevention** -- the current user cannot modify their own role via AI
- **Self-deactivation guard** -- WP Pinch cannot be deactivated by its own abilities

### Input Validation

- **Input sanitization** with `sanitize_text_field()`, `sanitize_key()`, `absint()`, `esc_url_raw()` on all parameters
- **Message length limit** -- 4,000 characters maximum for chat messages
- **Nested array rejection** in post meta values
- **CSS injection prevention** on block attributes via regex validation
- **Option allowlists** prevent reading/writing sensitive options (`auth_key`, `auth_salt`, `active_plugins`, `users_can_register`, `default_role`, API token)
- **Option denylist** is hardcoded and runs *before* any filter -- cannot be bypassed

### Output Security

- **Output escaping** with `esc_html()`, `esc_attr()`, `esc_url()` on all rendered content
- **Gateway reply XSS sanitization** via `wp_kses_post()`
- **Comment author emails stripped** from all ability responses
- **User emails removed** from ability responses (list-users, get-user, export-data, WooCommerce)
- **WooCommerce PII redaction** -- billing email, last name, payment method title, note authors
- **Gateway URL hidden** from non-admin users in the status endpoint
- **API token masked** on the settings page
- **Security scan** does not leak version numbers for core, plugins, or themes

### Database

- **Prepared SQL statements** everywhere -- no raw queries, ever
- **`show_in_rest => false`** on all 24 settings to prevent REST API leakage
- **Existence checks** before modifying posts, comments, terms, and media

### Network & API

- **Fixed-duration rate limiting** that doesn't slide (no gaming the window)
- **Rate limit headers** (`X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`) on all REST responses
- **`Retry-After` headers** on all 429 responses
- **HMAC-SHA256 webhook signatures** with timestamp replay protection (5-minute window)
- **Circuit breaker** for gateway failures -- auto-recovery with half-open probe
- **SSRF prevention** in broken link checker -- private IP blocking, DNS resolution check, SSL verification
- **Media upload restricted** to HTTP/HTTPS URL schemes only
- **Public chat endpoint isolation** with separate rate limiting and session key validation

### Infrastructure

- **`Update URI: false`** to prevent third-party update hijacking
- **Complete uninstall cleanup** -- all options, transients, audit table, user meta, and cron jobs removed
- **Multisite cleanup** on uninstall
- **Constant redefinition guards**
- **GDPR-ready** -- full integration with WordPress privacy export and erasure

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
