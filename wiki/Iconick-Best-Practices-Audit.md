# Iconick Best Practices Audit

*Comparison of WP Pinch against Iconick WordPress resources (user-iconick). Conducted to ensure compliance with WordPress best practices.*

---

## Executive Summary

WP Pinch largely aligns with Iconick/WordPress best practices. Key strengths: REST API (permission_callback, sanitize_callback, validate_callback), capabilities, sanitization, SQL injection prevention, rate limiting. Minor gaps and recommendations are noted below.

---

## 1. REST API Custom Endpoints

**Resource:** `wordpress://rest-api/custom-endpoints`

### Best practices (Iconick)

- `permission_callback` on every route
- `sanitize_callback` and `validate_callback` in `args` for each param
- Return `WP_Error` with appropriate status (400, 404, 500)
- Use `rest_ensure_response()` for success
- Rate limiting for sensitive endpoints

### WP Pinch compliance

| Check | Status | Notes |
|-------|--------|-------|
| permission_callback on all routes | Yes | `check_permission`, `check_hook_token`, or `__return_true` (public chat) |
| sanitize_callback on params | Yes | `sanitize_text_field`, `sanitize_key`, `absint`, custom callbacks |
| validate_callback where needed | Yes | Message length, non-empty text, enum validation |
| WP_Error with status | Yes | 400, 403, 404, 429 used appropriately |
| rest_ensure_response | Yes | Via WP_REST_Response |
| Rate limiting | Yes | `check_rate_limit()` on chat, ghostwrite, molt, hook |

### Recommendation

None. REST implementation matches best practices.

---

## 2. Capabilities

**Resource:** `wordpress://security/capabilities`

### Best practices (Iconick)

- Use `current_user_can()` before sensitive operations
- Prefer capabilities over role names
- Check per-item permissions (e.g. `edit_post`, $post_id)
- Verify nonce first, then capability
- Provide clear error messages for denied access

### WP Pinch compliance

| Check | Status | Notes |
|-------|--------|-------|
| Capability checks before operations | Yes | Every ability has `capability` and per-post checks where relevant |
| Specific capabilities | Yes | `edit_posts`, `manage_options`, `edit_post`, `read_post`, etc. |
| Per-post checks | Yes | `current_user_can( 'edit_post', $post_id )`, `read_post` |
| Clear error messages | Yes | "You do not have permission...", "Post not found" |
| No hardcoded role names | Yes | Uses capabilities throughout |

### Recommendation

None. Capability usage is correct.

---

## 3. Sanitization

**Resource:** `wordpress://security/sanitization`

### Best practices (Iconick)

- Sanitize all user input before storing
- Use type-specific functions (`sanitize_email`, `sanitize_text_field`, `absint`)
- Whitelist allowed values for enums/selections
- Use `wp_kses_post()` or `wp_kses()` for HTML content

### WP Pinch compliance

| Check | Status | Notes |
|-------|--------|-------|
| Sanitize all REST params | Yes | Via args `sanitize_callback` |
| Type-specific functions | Yes | `absint`, `sanitize_key`, `sanitize_text_field`, `sanitize_textarea_field`, `esc_url_raw` |
| HTML content | Yes | Molt uses `wp_kses_post` for HTML-containing fields |
| Whitelist for enums | Yes | `in_array( $value, $allowed, true )` patterns |
| Settings sanitization | Yes | `register_setting` with `sanitize_callback` |

### Recommendation

None. Sanitization is consistent with best practices.

---

## 4. Data Validation

**Resource:** `wordpress://security/data-validation`

### Best practices (Iconick)

- Validate required fields
- Validate types (int, email, URL)
- Validate ranges (min/max)
- Whitelist for selections
- Validate before processing

### WP Pinch compliance

| Check | Status | Notes |
|-------|--------|-------|
| validate_callback in REST args | Yes | Length limits, non-empty, format checks |
| Required params | Yes | `'required' => true` in args |
| Whitelist for enums | Yes | e.g. action in ghostwrite |
| Validate early | Yes | REST layer validates before handlers |

### Recommendation

None. Validation follows best practices.

---

## 5. Output Escaping

**Resource:** `wordpress://security/escaping`

### Best practices (Iconick)

- Escape at output (HTML, attribute, URL, JS)
- Use `esc_html()`, `esc_attr()`, `esc_url()` for correct context
- Use `wp_kses_post()` for rich content
- Use `wp_json_encode()` for JS data

### WP Pinch compliance

| Check | Status | Notes |
|-------|--------|-------|
| esc_html in settings/admin | Yes | Settings pages use `esc_html`, `esc_attr`, `esc_url` |
| esc_attr for form values | Yes | Input value, placeholder |
| esc_url for links | Yes | Gateway URL, MCP URL, nav tabs |
| wp_kses_post for chat/gateway | Yes | Gateway reply sanitized before display |
| wp_json_encode for JS | Yes | wp_interactivity_state, localized data |

### Recommendation

Audit block render output (e.g. `render.php`) for any dynamic values. Placeholder, maxHeight, and blockId should be escaped. Quick scan: `esc_attr()` is used for `$placeholder`, `$max_height`, `$unique_id`, `$block_id`—confirmed.

---

## 6. Nonces

**Resource:** `wordpress://security/nonces`

### Best practices (Iconick)

- Create nonce for forms/AJAX
- Verify before processing
- Use unique action names
- Combine with capability checks

### WP Pinch compliance

| Check | Status | Notes |
|-------|--------|-------|
| REST API nonce | Yes | `wp_create_nonce('wp_rest')` for chat; WordPress REST uses cookie auth + nonce |
| Verify before processing | Yes | `check_permission` relies on WordPress REST auth (includes nonce) |
| Capability + nonce | Yes | REST endpoints require `edit_posts` or similar |

### Recommendation

None. REST auth and nonce handling align with WordPress conventions.

---

## 7. SQL Injection Prevention

**Resource:** `wordpress://security/sql-injection`

### Best practices (Iconick)

- Never concatenate user input into SQL
- Use `$wpdb->prepare()` with `%d`, `%s`, `%f`
- Use `$wpdb->esc_like()` for LIKE patterns
- Whitelist ORDER BY, LIMIT when prepare can't be used

### WP Pinch compliance

| Check | Status | Notes |
|-------|--------|-------|
| $wpdb->prepare for user input | Yes | Audit table, abilities, privacy, plugin, uninstall |
| esc_like for LIKE | Yes | `$wpdb->esc_like( $args['search'] )` in audit query |
| Whitelist ORDER BY | Yes | `$allowed_orderby` in Audit_Table::query() |
| No raw concatenation | Yes | All queries use prepare or whitelisted identifiers |

### Recommendation

None. SQL usage is safe.

---

## 8. Block Registration

**Resource:** `wordpress://blocks/block-registration`

### Best practices (Iconick)

- Use block.json with proper schema
- Escape output in render_callback
- Use `register_block_type( __DIR__ )` for file-based registration

### WP Pinch compliance

| Check | Status | Notes |
|-------|--------|-------|
| block.json | Yes | Pinch Chat has block.json with attributes |
| Escape in render | Yes | esc_attr for dynamic values |
| register_block_type | Yes | From Plugin class |

### Recommendation

None. Block registration is correct.

---

## 9. MCP Server Security

**Resource:** `wordpress://security/mcp-server-security`

### Best practices (Iconick)

- Input validation and sanitization
- Rate limiting
- Authentication/authorization
- Secure error responses (no internal details)
- Audit logging

### WP Pinch compliance

| Check | Status | Notes |
|-------|--------|-------|
| Input validation | Yes | Abilities sanitize/validate input |
| Rate limiting | Yes | REST endpoints use check_rate_limit |
| Authentication | Yes | check_permission, Bearer/OpenClaw token for hooks |
| Secure errors | Yes | Generic messages; no stack traces to client |
| Audit logging | Yes | Audit_Table::insert for ability runs, webhooks, chat |

### Recommendation

None. MCP-related security is in place.

---

## 10. Gaps and Recommendations

### Minor

1. **REST schema** — Iconick custom-endpoints recommends a `schema` for GET endpoints. WP Pinch does not define `schema` on routes. Optional; adds OpenAPI-style documentation.

2. **validate_callback consistency** — A few REST args may rely only on sanitize_callback. Adding validate_callback for numeric ranges (e.g. per_page 1–500) would align with Iconick data-validation guidance.

### Already Addressed

- **Capability checklist** — Iconick: "Verify nonce first, then capability." WP Pinch REST uses WordPress core auth (cookie + nonce); capability is enforced in permission_callback. Correct.
- **Sanitization flow** — Iconick: "User Input → Validate → Sanitize → Store → Escape on Output." WP Pinch follows this in REST and abilities.

---

## Conclusion

WP Pinch conforms to Iconick/WordPress best practices for REST API, capabilities, sanitization, validation, escaping, nonces, SQL safety, block registration, and MCP security. No critical gaps. Optional improvements: add REST schema for documentation, and add validate_callback where only sanitize_callback exists for numeric params.
