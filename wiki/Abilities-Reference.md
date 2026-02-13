# Abilities Reference

WP Pinch provides **core abilities** (standard WordPress operations the AI can perform) and **tools** (PinchDrop and Ghost Writer workflows). You get **38 core abilities** across 10 categories, plus **PinchDrop** and **Ghost Writer** (4 tool abilities), and **10 WooCommerce abilities** when WooCommerce is active. Every ability has built-in security guards: capability checks, input sanitization, existence validation, and audit logging. We don't let AI agents run around your site like unsupervised lobsters in a kitchen.

---

## Core Abilities

Core abilities cover content, media, users, comments, settings, plugins/themes, analytics, menus, meta, revisions, bulk operations, and cron.

| Category | What It Does | Abilities |
|---|---|---|
| **Content** | Full CRUD on posts & pages | `list-posts`, `get-post`, `create-post`, `update-post`, `delete-post` |
| **Media** | Library management | `list-media`, `upload-media`, `delete-media` |
| **Taxonomies** | Terms and taxonomies | `list-taxonomies`, `manage-terms` |
| **Users** | User management with safety guards | `list-users`, `get-user`, `update-user-role` |
| **Comments** | Moderation and cleanup | `list-comments`, `moderate-comment` |
| **Settings** | Read and update options (allowlisted) | `get-option`, `update-option` |
| **Plugins & Themes** | Extension management | `list-plugins`, `toggle-plugin`, `list-themes`, `switch-theme` |
| **Analytics** | Site health, data export, context & discovery | `site-health`, `recent-activity`, `search-content`, `export-data`, `site-digest`, `related-posts`, `synthesize` |
| **Advanced** | Menus, meta, revisions, bulk ops, cron | `list-menus`, `manage-menu-item`, `get-post-meta`, `update-post-meta`, `list-revisions`, `restore-revision`, `bulk-edit-posts`, `list-cron-events`, `manage-cron` |
| **WooCommerce** | Shop abilities (when WooCommerce is active) | `woo-list-products`, `woo-manage-order`, `woo-create-product`, `woo-update-product`, `woo-manage-inventory`, `woo-list-orders`, `woo-list-customers`, `woo-list-coupons`, `woo-create-coupon`, `woo-revenue-summary` |

---

## Tools (PinchDrop, Ghost Writer, Molt)

Tools are WP Pinch–specific workflows that combine abilities, REST endpoints, and (where applicable) slash commands or governance tasks.

### PinchDrop

Capture rough ideas from any OpenClaw-connected channel and auto-generate a Draft Pack in WordPress. The **`pinchdrop-generate`** ability produces structured drafts (blog post, product update, changelog, social). Use **Quick Drop** (`options.save_as_note: true`) to skip AI expansion and create a minimal post (title + body only). Signed captures hit `POST /wp-pinch/v1/pinchdrop/capture`; the endpoint runs the ability and can save draft posts with full trace metadata (`source`, `request_id`, timestamp). Gated by the `pinchdrop_engine` feature flag.

**[PinchDrop (full guide) →](PinchDrop)**

### Ghost Writer

The AI that writes like *you*. Ghost Writer learns each author's writing voice from their published posts and can complete abandoned drafts in that voice. Three abilities: **`analyze-voice`** (build or refresh a per-author style profile), **`list-abandoned-drafts`** (rank drafts by resurrection potential), **`ghostwrite`** (return AI-completed content for a draft). The **`/wp-pinch/v1/ghostwrite`** REST endpoint powers the **`/ghostwrite`** slash command in chat (list drafts or resurrect by ID). A weekly Draft Necromancer governance task surfaces drafts worth saving. Gated by the `ghost_writer` feature flag.

**[Ghost Writer (full guide) →](Ghost-Writer)**

### Molt (Content Repackager)

*Lobsters molt to grow; your post sheds one form and emerges in many.* Molt repackages a single post into multiple output formats: social (Twitter 280, LinkedIn), email snippet, FAQ block, thread (array of tweets), summary, meta description (155 chars), pull quote, key takeaways, and CTA variants. Use the **`wp-pinch/molt`** ability or the **`/molt 123`** slash command in chat. REST endpoint: `POST /wp-pinch/v1/molt` with `post_id` and optional `output_types` array. Gated by the `molt` feature flag (disabled by default).

### Context & discovery (Memory Bait, Echo Net, Weave)

- **`site-digest` (Memory Bait)** — Compact export of recent posts (title, excerpt, taxonomy terms) for agent memory-core or system prompt.
- **`related-posts` (Echo Net)** — Given a post ID, returns posts that link to it (backlinks) or share taxonomy terms.
- **`synthesize` (Weave)** — Given a query, search posts and return a payload (title, excerpt, content snippet) for LLM synthesis; first draft, human refines.

---

## Security Guards

Every ability execution goes through these checks before any work is done. Think of it as a bouncer at a seafood restaurant — only the right claws get past the velvet rope:

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

WP Pinch registers a dedicated `wp-pinch` MCP endpoint that curates which abilities are exposed to AI agents. Only abilities you've enabled (via the admin UI or filters) are discoverable through MCP. Your site, your rules — and your lobster's guest list.

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
