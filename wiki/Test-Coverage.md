# Test Coverage & Gaps Addressed

This document summarizes test coverage added to make the codebase more robust.

## New Test Files

### `tests/test-prompt-sanitizer.php`

Covers the **Prompt_Sanitizer** security component (previously untested):

- `sanitize()` — redacts instruction-injection lines (ignore/disregard prior instructions, SYSTEM:, [INST], etc.)
- `sanitize_string()` — redacts short-string injection patterns in titles/names
- `sanitize_recursive()` — arrays, objects, depth limit, long vs short string handling
- `is_enabled()` — default and filter behavior
- Pattern filters — `wp_pinch_prompt_sanitizer_patterns`, `wp_pinch_prompt_sanitizer_enabled`
- Edge cases — empty content, whitespace-only, empty patterns (passthrough)

## Updated Test Files

### `tests/test-webhook-dispatcher.php`

**Webhook loop detection:**

- `test_loop_detection_skips_dispatch_when_flag_set` — when `set_skip_webhooks_this_request(true)`, dispatch returns false and `wp_pinch_before_webhook` does not fire
- `test_should_skip_webhooks_reflects_flag` — flag getter/setter behavior
- Tear down resets the skip flag to avoid cross-test pollution

### `tests/test-rest-controller.php`

Tests target the refactored REST handlers in `WP_Pinch\Rest\*`: **Auth** (permission/token checks), **Chat**, **Status**, **Incoming_Hook**, **Capture**, **Helpers** (e.g. `sanitize_gateway_reply`). Route registration is tested via `Rest_Controller::register_routes()`.

**Kill switch (API disabled):**

- `test_handle_incoming_hook_returns_503_when_api_disabled` — incoming hook returns 503 with `api_disabled` code
- `test_handle_chat_returns_503_when_api_disabled` — chat endpoint returns 503 when kill switch is on

**Read-only mode:**

- `test_execute_ability_blocks_write_when_read_only` — `update-option` returns error and does not mutate when `wp_pinch_read_only_mode` is set

Tear down now cleans `wp_pinch_api_disabled` and `wp_pinch_read_only_mode`.

### `tests/test-plugin.php`

**Plugin kill switch & read-only helpers:**

- `test_is_api_disabled_option` / `test_is_api_disabled_false_when_not_set`
- `test_is_read_only_mode_option` / `test_is_read_only_mode_false_when_not_set`

### `tests/test-abilities.php`

**Option denylist:**

- `test_get_option_denylist_home` — `home` is blocked
- `test_update_option_denylist_siteurl` — `siteurl` cannot be updated via ability

**Woo refactoring / trait composition:**

- `test_woo_ability_names_expand_when_woocommerce_active` — `get_ability_names()` includes all Woo slugs when WooCommerce is active, excludes them when it is not
- `test_woo_wrapper_methods_and_ability_slugs_stay_in_sync` — all slugs in the contract map have a matching `Abilities` wrapper method; when Woo is active, the slug list exactly matches `get_ability_names()` output
- `test_woo_abilities_return_deterministic_error_without_woocommerce` (data provider) — every Woo execute method returns a structured `error` (or `errors` for bulk) referencing WooCommerce when the class is absent
- `test_woo_cancel_order_safe_requires_confirmation_or_woocommerce` — `confirm=false` always returns an error; error message changes depending on Woo presence
- `test_woo_refund_structured_error_without_woocommerce` — `execute_woo_create_refund` always returns an `error` key
- `test_woo_risky_ability_schema_contracts_are_present` — `bulk-adjust-stock`, `cancel-order-safe`, `create-refund` schemas declare required guard fields
- `test_abilities_class_uses_expected_traits` — `class_uses(Abilities)` confirms `Ability_Names_Trait`, `Core_Passthrough_Trait`, and `Woo_Passthrough_Trait` are applied
- `test_woo_abilities_class_uses_expected_traits` — `Woo_Abilities` uses all 5 execution traits
- `test_analytics_abilities_class_uses_execute_trait` — `Analytics_Abilities` uses `Analytics_Execute_Trait`
- `test_ability_names_trait_provides_get_ability_names` — method is public + static (via reflection)
- `test_refactored_trait_files_exist` — all 17 refactored trait source files are present on disk (Analytics, QuickWin, MenuMeta, GEO, Woo execute/register traits, Settings split, and Abilities facade traits)

### `tests/test-cli.php`

**WP-CLI command structure:**

- CLI bootstrap class exists and has `register`; all 11 command classes in `includes/CLI/` exist and have static `register()` and `run( $args, $assoc_args )` with correct signatures. Uses a WP-CLI stub (`tests/includes/wp-cli-stub.php`) when not running inside WP-CLI so the bootstrap can load.

### `tests/test-docs.php`

**Documentation consistency:**

- `test_docs_count_messaging_is_consistent` — asserts `README.md`, `readme.txt`, and `wiki/Abilities-Reference.md` all agree on "88 core abilities", "30 WooCommerce", and "122 total"
- `test_readme_includes_woo_why_section` — verifies the "Why the WooCommerce expansion matters" section exists with Fewer handoffs / Safer store ops bullets
- `test_readme_txt_includes_woo_why_messaging` — checks `readme.txt` carries matching why-focused messaging
- `test_changelog_unreleased_mentions_woo_expansion` — ensures the `[Unreleased]` CHANGELOG block documents the Woo expansion rationale

### `tests/test-maintainability.php`

**File size guardrails:**

- `test_includes_php_files_stay_within_line_budgets` — every PHP file under `includes/` must stay within its declared line budget (200 for the thin `Woo_Abilities` facade, 750 for `Analytics_Execute_Trait`, 900 for `Woo_Register_Trait`, 950 for `class-abilities.php`, 1000 for large multi-ability files). Prevents accidental hotspot growth without a conscious decision to raise the budget.

### `tests/test-utils.php`

**Token masking edge cases:**

- `test_mask_token_four_chars` — exactly 4 chars shows `****` + last 4
- `test_mask_token_three_chars` — 3 chars returns `****` only

## Running Tests

PHPUnit requires a WordPress test environment:

```bash
# Install WordPress test lib (if not already)
bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run all tests
./vendor/bin/phpunit -c phpunit.xml.dist

# Run only new/updated tests
./vendor/bin/phpunit -c phpunit.xml.dist --filter 'Prompt_Sanitizer|loop_detection|should_skip|api_disabled|read_only|denylist_home|denylist_siteurl|mask_token'
```

## Coverage Summary

| Area | Before | After |
|------|--------|-------|
| Prompt_Sanitizer | 0% | Full unit coverage |
| Webhook loop detection | 0% | Flag + dispatch behavior |
| Kill switch (REST) | 0% | Incoming hook + chat 503 |
| Read-only mode | 0% | Write ability blocked |
| Plugin is_api_disabled / is_read_only_mode | 0% | Option-based behavior |
| Option denylist (home, siteurl) | siteurl get only | home get, siteurl update |
| Utils::mask_token | Basic | + 3-char, 4-char edge cases |
| Woo trait composition + 30-ability parity | 0% | Full matrix + reflection guards |
| Docs count consistency | 0% | Automated via test-docs.php |
| File size budgets | 0% | Automated via test-maintainability.php |

## Quality tools (CI and local)

- **Coverage (Codecov)** — CI runs PHPUnit with PCOV and uploads Clover XML to Codecov. Run `make test-coverage-clover` locally to generate the same file.
- **Mutation testing (Infection)** — CI runs Infection on PHP 8.2 + WP latest (~25 min). Run `make mutation` locally (requires PCOV or Xdebug + WP test env). Mutates `includes/` and `wp-pinch.php`; config in `infection.json.dist`.
- **PHPCS on tests** — CI runs `composer lint:tests` (ruleset `phpcs-tests.xml.dist`). Run `make lint-tests` locally. Relaxed rules so test code is not forced to production style.

## Recommendations

1. **E2E coverage depth** — Keep expanding Playwright scenarios beyond happy paths (timeouts, denied permissions, and recovery UX).
2. **Integration tests** — Add more full-path MCP invocation tests (especially read-only/kill-switch/circuit-breaker combinations).
3. **PHPStan and PHPCS discipline** — Keep running `composer phpstan`, `composer lint`, and `composer lint:tests` on every PR.
4. **CI parity locally** — Use the same commands as CI: `composer test` (wp-env), `npm run test:e2e`, and `npm run test:plugin-check`.
