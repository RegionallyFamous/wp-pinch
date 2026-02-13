# Hooks & Filters Reference

WP Pinch provides over 12 filters and 6 actions for customizing every aspect of the plugin.

---

## Filters

| Filter | Description | Parameters |
|---|---|---|
| `wp_pinch_abilities` | Modify the registered abilities list | `array $abilities` |
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

---

## Actions

| Action | Description | Parameters |
|---|---|---|
| `wp_pinch_after_ability` | Fires after any ability executes | `string $ability, array $args, mixed $result` |
| `wp_pinch_governance_finding` | Fires when a governance finding is recorded | `string $task, array $finding` |
| `wp_pinch_activated` | Fires on plugin activation | -- |
| `wp_pinch_deactivated` | Fires on plugin deactivation | -- |
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
