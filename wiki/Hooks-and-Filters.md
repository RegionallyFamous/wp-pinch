# Hooks & Filters Reference

WP Pinch provides over 12 filters and 6 actions for customizing every aspect of the plugin. If you can hook it, you can pinch it.

---

## Filters

| Filter | Description | Parameters |
|---|---|---|
| `wp_pinch_abilities` | Modify the registered abilities list | `array $abilities` |
| `wp_pinch_manifest` | Modify the site capability manifest (post types, taxonomies, plugins, features) returned with GET `/abilities` | `array $manifest` |
| `wp_pinch_write_abilities` | Modify which ability names count toward the daily write budget (create/update/delete). Default list includes create-post, update-post, delete-post, upload-media, etc. | `array $ability_names` |
| `wp_pinch_webhook_payload` | Modify webhook data before dispatch | `array $payload, string $event` |
| `wp_pinch_blocked_roles` | Roles that can't be assigned via AI | `array $roles` |
| `wp_pinch_option_allowlist` | Options readable via `get-option` | `array $keys` |
| `wp_pinch_option_write_allowlist` | Options writable via `update-option` | `array $keys` |
| `wp_pinch_governance_findings` | Suppress or modify governance findings | `array $findings, string $task` |
| `wp_pinch_governance_interval` | Adjust task schedule intervals | `int $seconds, string $task` |
| `wp_pinch_chat_response` | Modify chat response before returning | `array $result, array $data` |
| `wp_pinch_chat_payload` | Modify chat payload before sending to gateway | `array $payload, WP_REST_Request $request` |
| `wp_register_ability_args` | Modify ability registration args | `array $args` |
| `wp_pinch_feature_flags` | Override feature flag values | `array $flags` |
| `wp_pinch_synthesize_excerpt_words` | Excerpt word count for synthesize/what-do-i-know (default 40) | `int $words` |
| `wp_pinch_synthesize_content_snippet_words` | Content snippet word count sent to LLM (default 75, was 150) | `int $words` |
| `wp_pinch_synthesize_per_page_max` | Max posts per synthesize query (default 25) | `int $max` |
| `wp_pinch_block_type_metadata` | Modify Pinch Chat block registration args | `array $args, string $block_type` |
| `wp_pinch_prompt_sanitizer_patterns` | Regex patterns for instruction-injection detection (multi-line content) | `array $patterns` |
| `wp_pinch_prompt_sanitizer_title_patterns` | Regex patterns for short strings (titles, slugs, names) | `array $patterns` |
| `wp_pinch_prompt_sanitizer_enabled` | Enable/disable prompt sanitization | `bool $enabled` |
| `wp_pinch_preferred_content_format` | Preferred format for block-style output: `'blocks'` (Gutenberg markup) or `'html'` (classic HTML). Defaults to `'html'` when Classic Editor plugin is active and set to replace the block editor. Used by Molt `faq_blocks` so Classic Editor sites get HTML output. | `string $format` |

---

## Block Bindings (Pinch Chat)

The Pinch Chat block supports the Block Bindings API (WordPress 6.5+). You can bind `agentId` and `placeholder` to:

- **core/post-meta** — Per-post overrides: `wp_pinch_chat_agent_id`, `wp_pinch_chat_placeholder`
- **wp-pinch/agent-id** — Site option `wp_pinch_agent_id`
- **wp-pinch/chat-placeholder** — Site option `wp_pinch_chat_placeholder`

In the block editor: select the Pinch Chat block → Settings sidebar → Block bindings → connect an attribute to a source.

---

## Actions

| Action | Description | Parameters |
|---|---|---|
| `wp_pinch_after_ability` | Fires after any ability executes | `string $ability, array $args, mixed $result` |
| `wp_pinch_governance_finding` | Fires when a governance finding is recorded | `string $task, array $finding` |
| `wp_pinch_activated` | Fires on plugin activation | -- |
| `wp_pinch_deactivated` | Fires on plugin deactivation | -- |
| `wp_pinch_openclaw_agent_user_created` | Fires after OpenClaw agent user is created | `int $user_id` |
| `wp_pinch_booted` | Fires after all subsystems initialize | -- |

---

## Examples

### Remove a dangerous ability

```php
add_filter( 'wp_pinch_abilities', function ( array $abilities ): array {
    unset( $abilities['delete_post'] );
    return $abilities;
} );
```

### Add site name to every webhook payload

```php
add_filter( 'wp_pinch_webhook_payload', function ( array $payload, string $event ): array {
    $payload['site_name'] = get_bloginfo( 'name' );
    return $payload;
}, 10, 2 );
```

### Protect editor role from AI assignment

```php
add_filter( 'wp_pinch_blocked_roles', function ( array $roles ): array {
    $roles[] = 'editor';
    return $roles;
} );
```

### Suppress governance findings you don't care about

```php
add_filter( 'wp_pinch_governance_findings', function ( array $findings, string $task ): array {
    if ( 'content_freshness' === $task ) {
        // Ignore posts in the "archive" category
        $findings = array_filter( $findings, function ( $finding ) {
            return ! has_category( 'archive', $finding['post_id'] );
        } );
    }
    return $findings;
}, 10, 2 );
```

### Log every ability execution to an external service

```php
add_action( 'wp_pinch_after_ability', function ( string $ability, array $args, mixed $result ): void {
    wp_remote_post( 'https://my-logging-service.com/api/log', array(
        'body' => wp_json_encode( array(
            'ability' => $ability,
            'args'    => $args,
            'user_id' => get_current_user_id(),
            'time'    => current_time( 'mysql' ),
        ) ),
        'headers' => array( 'Content-Type' => 'application/json' ),
    ) );
}, 10, 3 );
```

### Override feature flags programmatically

```php
add_filter( 'wp_pinch_feature_flags', function ( array $flags ): array {
    // Always enable streaming on this site
    $flags['streaming_chat'] = true;
    return $flags;
} );
```

### Extend the capability manifest (GET /abilities)

```php
add_filter( 'wp_pinch_manifest', function ( array $manifest ): array {
    // Add a custom "profile" key for agent discovery
    $manifest['profile'] = array( 'cms' => 'wordpress', 'locale' => get_locale() );
    return $manifest;
} );
```

### Change which abilities count toward the daily write budget

```php
add_filter( 'wp_pinch_write_abilities', function ( array $names ): array {
    // Exclude update-option from the daily cap
    return array_filter( $names, function ( $name ) {
        return $name !== 'wp-pinch/update-option';
    } );
} );
```
