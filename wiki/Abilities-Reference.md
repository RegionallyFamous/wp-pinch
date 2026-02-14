# Abilities Reference

WP Pinch provides **core abilities** (standard WordPress operations the AI can perform) and **tools** (PinchDrop, Ghost Writer, Molt, and a whole claw-full of quick-win and high-leverage abilities). You get **38 core abilities** across 10 categories, plus PinchDrop, Ghost Writer, Molt, TL;DR, Link Suggester, Quote Bank, What do I know, Project Assembly, Spaced Resurfacing, Find Similar, Knowledge Graph — and **2 WooCommerce abilities** when WooCommerce is active. Every ability has built-in security guards: capability checks, input sanitization, existence validation, and audit logging. We don't let AI agents run around your site like unsupervised lobsters in a kitchen. *Someone* has to be the bouncer.

---

## Core Abilities

Core abilities cover content, media, users, comments, settings, plugins/themes, analytics, menus, meta, revisions, bulk operations, and cron.

| Category | What It Does | Abilities |
|---|---|---|
| **Content** | Full CRUD on posts & pages | `list-posts`, `get-post`, `create-post`, `update-post`, `delete-post` (optional **Block JSON**, **draft-first**, **featured image** — see below) |
| **Media** | Library management | `list-media`, `upload-media`, `delete-media` |
| **Taxonomies** | Terms and taxonomies | `list-taxonomies`, `manage-terms` |
| **Users** | User management with safety guards | `list-users`, `get-user`, `update-user-role` |
| **Comments** | Moderation and cleanup | `list-comments`, `moderate-comment` |
| **Settings** | Read and update options (allowlisted) | `get-option`, `update-option` |
| **Plugins & Themes** | Extension management | `list-plugins`, `toggle-plugin`, `list-themes`, `switch-theme` |
| **Analytics** | Site health, data export, context & discovery | `site-health`, `recent-activity`, `search-content`, `export-data`, `site-digest`, `related-posts`, `synthesize`, `content-health-report`, `suggest-terms` |
| **Advanced** | Menus, meta, revisions, bulk ops, cron | `list-menus`, `manage-menu-item`, `get-post-meta`, `update-post-meta`, `list-revisions`, `restore-revision`, `bulk-edit-posts`, `list-cron-events`, `manage-cron` |
| **WooCommerce** | Shop abilities (when WooCommerce is active) | `woo-list-products`, `woo-manage-order` |

### Block JSON (create-post / update-post)

You can send native block editor content by passing a **`blocks`** array instead of (or in addition to) `content`. Each block must have `blockName` (e.g. `core/paragraph`, `core/heading`), and optionally `attrs`, `innerContent` (array of strings), and `innerBlocks` (nested blocks). When `blocks` is provided, it takes precedence over `content`.

**Example — two paragraphs:**

```json
{
  "title": "My post",
  "blocks": [
    {
      "blockName": "core/paragraph",
      "attrs": {},
      "innerContent": ["Hello, this is the first paragraph."]
    },
    {
      "blockName": "core/paragraph",
      "attrs": {},
      "innerContent": ["And this is the second."]
    }
  ]
}
```

**Example — heading + paragraph:**

```json
{
  "title": "Getting started",
  "blocks": [
    {
      "blockName": "core/heading",
      "attrs": { "level": 2 },
      "innerContent": ["Getting started"]
    },
    {
      "blockName": "core/paragraph",
      "attrs": {},
      "innerContent": ["Follow these steps…"]
    }
  ]
}
```

Block names must match `namespace/name` (e.g. `core/list`, `core/image`). The plugin uses WordPress `serialize_blocks()` to convert the array into `post_content`.

### Draft-first and preview

When the AI creates or updates a post via `create-post` or `update-post`, the post is saved as **draft** and the response includes:

- **`preview_url`** — URL to view the draft (preview link). Use this so the user can review before publishing.
- **`ai_generated`** — `true`; the post is marked with `_wp_pinch_ai_generated` meta for audit and workflow.

To publish a draft after review, call **`POST /wp-json/wp-pinch/v1/preview-approve`** with JSON body `{ "post_id": 123 }`. The endpoint requires the same auth as other WP Pinch REST (e.g. application password). The user must have `edit_post` on that post. On success the post status becomes `publish` and the response includes the post URL.

### Featured image (create-post)

You can set the post’s featured image in one call:

- **`featured_image_url`** — URL of an image to download and set as featured image (HTTP/HTTPS only; validated to prevent SSRF).
- **`featured_image_base64`** — Base64-encoded image data (alternative to URL). Use one of these, not both.
- **`featured_image_alt`** — Alt text for the image (optional).

On success the response may include `featured_image_id` and `featured_image_url` (attachment URL).

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

*Lobsters molt to grow; your post sheds one form and emerges in many.* Molt repackages a single post into multiple output formats: social (Twitter 280, LinkedIn), email snippet, FAQ block (array), **faq_blocks** (Gutenberg block markup for FAQs), thread (array of tweets), summary, meta description (155 chars), pull quote, key takeaways, and CTA variants. Use **`faq_blocks`** for block-editor-native output. Use the **`wp-pinch/molt`** ability or the **`/molt 123`** slash command in chat. REST endpoint: `POST /wp-pinch/v1/molt` with `post_id` and optional `output_types` array. Gated by the `molt` feature flag (disabled by default).

### Context & discovery (Memory Bait, Echo Net, Weave)

- **`site-digest` (Memory Bait)** — Compact export of recent posts (title, excerpt, taxonomy terms, optional `tldr` when set) for agent memory-core or system prompt.
- **`related-posts` (Echo Net)** — Given a post ID, returns posts that link to it (backlinks) or share taxonomy terms.
- **`synthesize` (Weave)** — Given a query, search posts and return a payload (title, excerpt, content snippet) for LLM synthesis; first draft, human refines.

### Content health and term suggestions

- **`content-health-report`** — Returns a content health report: missing alt text on images, broken internal links, thin content (below word threshold), and orphaned media. Input: `limit` (max items per category, 1–100, default 50), `min_words` (minimum word count to not flag as thin, default 300). Output: `missing_alt`, `broken_internal_links`, `thin_content`, `orphaned_media` (each an array of items with post_id / url / details as applicable).
- **`suggest-terms`** — Given a draft post ID or raw content, returns suggested categories and tags (by content similarity and term match). Input: `post_id` (optional) or `content` (when post_id not provided), optional `limit` (default 15). Output: `suggested_categories`, `suggested_tags` (arrays of term names or objects).

### Quick-win tools (TL;DR, Link Suggester, Quote Bank)

- **`generate-tldr`** — Generate a 1–2 sentence summary for a post via Molt and store it in post meta (`wp_pinch_tldr`). Runs automatically when a post is published (if Molt is enabled). Input: `post_id`.
- **`suggest-links`** — Given a post ID or text query, return existing posts that are good link candidates (search + related-posts). Input: `post_id` or `query`, optional `limit` (default 15).
- **`quote-bank`** — Extract notable sentences from a post (heuristic: medium-length sentences, 40–300 chars). Returns a list of strings. Input: `post_id`, optional `max` (default 15).

### High-leverage tools (What do I know, Project Assembly, Spaced Resurfacing)

- **`what-do-i-know`** — Natural-language query → search + gateway synthesis → coherent answer plus source post IDs. Input: `query`, optional `per_page` (default 10). Flagship retrieval experience.
- **`project-assembly`** — Given `post_ids` and optional `prompt`, weave those posts into one draft with citations. Returns `draft`; optional `save_as_draft` creates a draft post.
- **`spaced-resurfacing`** — List posts not updated in N days (optionally by `category` or `tag`). Input: `days` (default 30), `category`, `tag`, `limit` (default 50). Also available as a daily governance task and in the Tide Report.

### Semantic search (MVP)

- **`find-similar`** — Given `post_id` or `query`, return related posts (by keyword search and taxonomy). MVP uses title/excerpt similarity and related-posts; **future:** full semantic search with embeddings.

### Knowledge Graph

- **`knowledge-graph`** — Returns nodes (posts, optionally terms) and edges (content links, shared tags). Input: `post_type` (default `post`), `limit` (default 200), `include_terms` (default true). Payload suitable for external graph visualization.

---

## Security Guards

Every ability execution goes through these checks before any work is done. Think of it as a bouncer at a seafood restaurant — only the right claws get past the velvet rope. (We take our crustacean security very seriously.)

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

### List abilities and site manifest (GET /abilities)

**`GET /wp-json/wp-pinch/v1/abilities`** (authenticated) returns:

- **`abilities`** — Array of `{ "name": "wp-pinch/..." }` for every enabled ability (respects admin toggles and filters).
- **`site`** — Capability manifest for agent discovery: **`post_types`** (public post type names), **`taxonomies`** (public taxonomy names), **`plugins`** (active plugin slugs, no versions), **`features`** (feature flag key → boolean).

Use this so clients know what content types and taxonomies exist and which features are on. Filter the manifest with **`wp_pinch_manifest`** (see [Hooks & Filters](Hooks-and-Filters)).

### Daily write budget

When a **daily write cap** is set in **WP Pinch → Connection** (e.g. 50 operations per day), every write ability (create-post, update-post, delete-post, upload-media, update-option, etc.) counts toward that cap. When the count exceeds the cap, the request returns **HTTP 429** with `code: "daily_write_budget_exceeded"`. The counter resets at midnight (site time). Optional: set an **alert email** and **threshold %** to receive a warning when usage reaches that percentage of the cap. Which abilities count is filterable via **`wp_pinch_write_abilities`** (see [Hooks & Filters](Hooks-and-Filters)).

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
