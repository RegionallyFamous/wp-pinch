# Abilities Reference

WP Pinch registers **35 core abilities** across 10 categories, plus 10 WooCommerce abilities when WooCommerce is active. Every ability has built-in security guards: capability checks, input sanitization, existence validation, and audit logging.

---

## Abilities by Category

| Category | What It Does | Abilities |
|---|---|---|
| **Content** | Full CRUD on posts & pages | `list-posts`, `create-post`, `update-post`, `delete-post` |
| **Media** | Library management | `list-media`, `upload-media`, `delete-media` |
| **Taxonomies** | Terms and taxonomies | `list-taxonomies`, `manage-terms` |
| **Users** | User management with safety guards | `list-users`, `get-user`, `update-user-role` |
| **Comments** | Moderation and cleanup | `list-comments`, `moderate-comment` |
| **Settings** | Read and update options (allowlisted) | `get-option`, `update-option` |
| **Plugins & Themes** | Extension management | `list-plugins`, `toggle-plugin`, `list-themes`, `switch-theme` |
| **Analytics** | Site health and data export | `site-health`, `recent-activity`, `search-content`, `export-data`, `export-site-context` |
| **Advanced** | Menus, meta, revisions, bulk ops, cron | `list-menus`, `manage-menu-item`, `get-post-meta`, `update-post-meta`, `list-revisions`, `restore-revision`, `bulk-edit-posts`, `list-cron-events`, `manage-cron` |
| **WooCommerce** | Shop abilities (when WooCommerce is active) | `woo-list-products`, `woo-manage-order`, `woo-create-product`, `woo-update-product`, `woo-manage-inventory`, `woo-list-orders`, `woo-list-customers`, `woo-list-coupons`, `woo-create-coupon`, `woo-revenue-summary` |

---

## Security Guards

Every ability execution goes through these checks before any work is done:

1. **Capability check** -- Does the current user have permission? (e.g., `edit_posts`, `manage_options`)
2. **Per-post verification** -- For meta operations, verifies `current_user_can( 'edit_post', $post_id )`
3. **Input sanitization** -- All parameters sanitized with `sanitize_text_field()`, `sanitize_key()`, `absint()`, `esc_url_raw()`
4. **Existence validation** -- Posts, comments, terms, and media are verified to exist before modification
5. **Audit logging** -- Every execution is recorded in the audit log with user ID, ability name, and parameters

---

## Customizing Abilities

### Remove an ability

```php
add_filter( 'wp_pinch_abilities', function ( array $abilities ): array {
    unset( $abilities['delete_post'] );
    return $abilities;
} );
```

### Add a custom ability

```php
add_filter( 'wp_pinch_abilities', function ( array $abilities ): array {
    $abilities['my_custom_ability'] = array(
        'label'       => 'My Custom Ability',
        'description' => 'Does something custom.',
        'callback'    => 'my_custom_ability_callback',
        'category'    => 'custom',
        'capability'  => 'manage_options',
    );
    return $abilities;
} );
```

### Disable abilities from the admin UI

Navigate to **WP Pinch > Abilities** in your admin sidebar. Toggle individual abilities on or off without writing code.

---

## MCP Server

WP Pinch registers a dedicated `wp-pinch` MCP endpoint that curates which abilities are exposed to AI agents. Only abilities you've enabled (via the admin UI or filters) are discoverable through MCP.

The MCP endpoint is available at:

```
https://your-site.com/wp-json/wp-pinch/v1/mcp
```

Connect OpenClaw:

```bash
npx openclaw connect --mcp-url https://your-site.com/wp-json/wp-pinch/v1/mcp
```

---

## Option Allowlists

The `get-option` and `update-option` abilities only work with explicitly allowed option keys. Sensitive options (auth salts, `active_plugins`, `users_can_register`, `default_role`, the API token) are blocked by a hardcoded denylist that runs *before* any filter.

Extend the allowlists:

```php
// Allow reading additional options
add_filter( 'wp_pinch_option_allowlist', function ( array $keys ): array {
    $keys[] = 'my_custom_option';
    return $keys;
} );

// Allow writing additional options
add_filter( 'wp_pinch_option_write_allowlist', function ( array $keys ): array {
    $keys[] = 'my_custom_option';
    return $keys;
} );
```

---

## Role Safety

AI agents cannot:

- Promote any user to **administrator**
- Assign roles with dangerous capabilities (`manage_options`, `edit_users`, etc.)
- Modify the current user's own role
- Deactivate the WP Pinch plugin itself

Customize blocked roles:

```php
add_filter( 'wp_pinch_blocked_roles', function ( array $roles ): array {
    $roles[] = 'editor'; // Protect editors too
    return $roles;
} );
```
