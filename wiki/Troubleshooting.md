# Troubleshooting

Common issues with WP Pinch and how to resolve them.

## REST API disabled or blocked

WP Pinch requires the WordPress REST API for MCP, chat, webhooks, and ability execution. If the REST API is disabled or blocked, WP Pinch will show an admin notice and chat/MCP will not work.

### Symptoms

- Admin notice: "The WordPress REST API appears to be disabled or blocked"
- Chat block does not respond
- MCP tools fail with connection errors
- Webhooks never reach your channel

### Re-enabling the REST API

1. **Check for plugins that disable the REST API**
   - "Disable REST API"
   - "Disable WP REST API"
   - Similar plugins that block `/wp-json/` requests
   - Deactivate and test

2. **Security plugins**
   - **Wordfence**: Check Firewall > Blocking for rules blocking REST requests. Whitelist `/wp-json/` or add an allow rule for WP Pinch.
   - **Sucuri**: Whitelist REST API paths in the WAF.
   - **iThemes Security**: Some modules block REST. Check REST API settings and allow access for authenticated users.

3. **Managed hosting**
   - **WP Engine**: REST API is usually enabled. Contact support if blocked.
   - **Kinsta**: REST API is enabled by default. Rate limits may apply.
   - **Pressable**: Similar to Kinsta; REST is available.

### Page cache exclusions

Exclude WP Pinch REST routes from page caches to avoid caching API responses:

- Path patterns to exclude: `/wp-json/wp-pinch/*`, `/wp-json/wp/v2/*` (if your block or custom code uses core endpoints)
- Most caches (LiteSpeed, WP Super Cache, W3 Total Cache, etc.) support path-based exclusions

### Verifying the REST API

- Visit `https://yoursite.com/wp-json/wp-pinch/v1/health` — you should get a JSON response, not a 404 or redirect.

---

## WAF and firewall rules

Web Application Firewalls (Cloudflare, Sucuri, Wordfence, etc.) can block legitimate REST API requests.

### What to whitelist

- **Path**: `/wp-json/wp-pinch/*`
- **Headers**: `Authorization`, `Content-Type`, `X-WP-Nonce` (for logged-in requests)
- **Methods**: `GET`, `POST`, `OPTIONS` (CORS preflight)

### Common false positives

- Rate limiting may trigger on bursty chat traffic. Increase limits for WP Pinch routes or exclude them.
- Bot detection may flag MCP/chat requests. Add an exception for requests with valid `Authorization` or `X-WP-Nonce`.

---

## Managed hosting considerations

- **Rate limits**: Some hosts limit requests per minute. High chat or webhook volume may hit limits.
- **Timeout**: REST requests can timeout on long-running abilities. Increase PHP `max_execution_time` or configure your gateway to use shorter operations.
- **Object cache**: WP Pinch works with Redis/Memcached. Ensure transients and options are not excluded.

---

## Further help

- [Configuration](Configuration.md) — gateway URL, API token, webhook setup
- [Error Codes](Error-Codes.md) — API error reference
- [FAQ](FAQ.md) — common questions
