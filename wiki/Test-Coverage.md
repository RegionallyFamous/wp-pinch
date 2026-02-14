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

## Quality tools (CI and local)

- **Coverage (Codecov)** — CI runs PHPUnit with PCOV and uploads Clover XML to Codecov. Run `make test-coverage-clover` locally to generate the same file.
- **Mutation testing (Infection)** — CI runs Infection on PHP 8.2 + WP latest (~25 min). Run `make mutation` locally (requires PCOV or Xdebug + WP test env). Mutates `includes/` and `wp-pinch.php`; config in `infection.json.dist`.
- **PHPCS on tests** — CI runs `composer lint:tests` (ruleset `phpcs-tests.xml.dist`). Run `make lint-tests` locally. Relaxed rules so test code is not forced to production style.

## Recommendations

1. **E2E tests** — Consider Playwright tests for critical flows (wizard, chat block, settings save).
2. **Integration tests** — Test full MCP tool invocation with read-only/kill switch if the Abilities API is available.
3. **PHPStan** — Keep running `composer phpstan` to catch type and logic issues.
4. **CI** — Run PHPUnit in CI with a MySQL service; ensure `WP_TESTS_DIR` is set.
