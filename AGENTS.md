# AGENTS.md — Guide for AI Assistants

This document is written for AI coding assistants (Cursor, Copilot, Claude, etc.) to understand WP Pinch, navigate the codebase, and extend or improve it effectively. Humans: you may find it useful too.

---

## What Is WP Pinch?

WP Pinch is a WordPress plugin that bridges WordPress with [OpenClaw](https://github.com/openclaw/openclaw), a self-hosted AI gateway. It exposes WordPress as tools (abilities) via the Model Context Protocol (MCP) so AI agents can manage content, run governance tasks, chat with the site, and more — from channels like WhatsApp, Telegram, Slack, and Discord.

**In one sentence:** WP Pinch makes your WordPress site programmable by AI agents through OpenClaw.

---

## Architecture at a Glance

```
Channels (WhatsApp, Telegram, etc.)
        ↓
OpenClaw (AI gateway, sessions, memory)
        ↓
MCP / REST API (WP Pinch endpoints)
        ↓
Abilities Engine (38+ tools) + Webhooks + Governance
        ↓
WordPress (posts, taxonomies, options, etc.)
```

**Outbound:** WordPress → Webhook Dispatcher → OpenClaw (when posts publish, comments arrive, etc.).  
**Inbound:** OpenClaw → MCP/REST → Abilities → WordPress.

---

## Key Files and Where to Look

| Purpose | File(s) | Notes |
|---------|---------|-------|
| **Abilities** (tools the AI can call) | `includes/class-abilities.php` | 38+ abilities; each has `execute_*` method and `register()` |
| **REST API** | `includes/class-rest-controller.php` + `includes/Rest/` | Routes and security headers in class; handlers in `Rest\Auth`, `Rest\Chat`, `Rest\Status`, `Rest\Incoming_Hook`, `Rest\Capture`, Ghostwrite, Molt, etc. |
| **Webhooks** (push events to OpenClaw) | `includes/class-webhook-dispatcher.php` | Configurable events, retry, rate limit, HMAC |
| **Governance** (scheduled tasks) | `includes/class-governance.php` | Content freshness, SEO, comments, broken links, security, drafts, Tide Report |
| **Feature flags** | `includes/class-feature-flags.php` | Toggle features without code changes |
| **MCP server** | `includes/class-mcp-server.php` | Registers `wp-pinch` MCP endpoint; curates abilities |
| **Chat block** | `src/blocks/pinch-chat/` | Interactivity API; view.js, render.php |
| **Settings** | `includes/class-settings.php` | Admin UI, options, wizard |
| **Audit log** | `includes/class-audit-table.php` | Logs ability runs, webhooks, governance |

**Wiki docs:** `wiki/` — Architecture, Abilities-Reference, Chat-Block, Configuration, Security, Hooks-and-Filters, Developer-Guide.

---

## How Abilities Work

Abilities are the core “tools” the AI uses. Each ability:

1. Is registered via `wp_register_ability()` (WordPress 6.9+ Abilities API)
2. Has a capability (e.g. `edit_posts`), input schema, output schema
3. Implements an `execute_*` method that does the work
4. Logs to the audit table
5. Can be cached (read-only abilities use object cache or transients)

**To add a new ability:**

1. Add a `private static function register_*()` in `class-abilities.php`
2. Call `self::register( $name, $title, $description, $input_schema, $output_schema, $capability, [ __CLASS__, 'execute_my_ability' ], $readonly )`
3. Implement `public static function execute_my_ability( array $input ): array`
4. Add the ability name to the MCP server or `CORE_ABILITIES` if it should be on the default server
5. Add a PHPUnit test in `tests/test-abilities.php`

**Conventions:**

- Input: sanitize with `sanitize_text_field()`, `absint()`, `sanitize_key()`, `esc_url_raw()`
- Output: arrays or `array( 'error' => '...' )` for failure
- Check `current_user_can()` and `current_user_can( 'edit_post', $post_id )` where appropriate

---

## How to Add a Governance Task

1. Add a task key and label in `Governance::get_available_tasks()`
2. Add a `task_*` method that fetches findings and calls `self::deliver_findings()`
3. Add the task to `DEFAULT_INTERVALS` with a cron interval
4. Wire the task in `Governance::schedule_tasks()` (or equivalent scheduler)
5. If it uses shared helpers, add `get_*_findings()` and use in both the task and Tide Report

---

## Hooks and Filters

WP Pinch exposes filters and actions for extensibility. See `wiki/Hooks-and-Filters.md`.

**Important filters for agents:**

- `wp_pinch_webhook_payload` — Modify payload before sending to OpenClaw
- `wp_pinch_abilities` — Modify registered abilities
- `wp_pinch_governance_findings` — Modify or suppress governance findings
- `wp_pinch_synthesize_excerpt_words` — Token savings: excerpt length for synthesize
- `wp_pinch_synthesize_content_snippet_words` — Token savings: snippet length sent to LLM
- `wp_pinch_synthesize_per_page_max` — Max posts per synthesize query

---

## Security Conventions

- **SQL:** Always use `$wpdb->prepare()` and `$wpdb->esc_like()` for LIKE
- **Output:** Escape with `esc_html()`, `esc_attr()`, `esc_url()`; gateway replies use `wp_kses_post()`
- **Input:** Sanitize + validate; REST args use `sanitize_callback` and `validate_callback`
- **URLs:** `wp_http_validate_url()` before outbound requests (SSRF prevention)
- **Options:** Use allowlists; sensitive options (API token, etc.) are denylisted
- **Token logging:** Never log full API/capture tokens. Use `Utils::mask_token( $token )` for debug/audit context.

See `wiki/Security.md` for full details.

---

## Code style (hand-coded feel)

We want the codebase to read like it was written by a human maintainer. That builds trust.

- **Comments:** Prefer "why" over "what". Add comments for non-obvious reasons (security, past bugs, constraints). Omit comments that only restate the method name or the next line. Keep docblocks lean; terse is fine. Private helpers can have no docblock if the code is clear.
- **Structure:** Allow some inconsistency between modules. Don't over-normalize when refactoring — not every class needs the same template. Some duplication for clarity is fine. Avoid the "perfect grid" where every class looks identical.
- **User-facing strings:** Specific over generic ("Gateway URL failed security validation" not "An error occurred"). Keep the existing voice ("Snatched!", "Claws at the ready!") where we have it.
- **Docs/CHANGELOG:** First person, concrete. Changelog entries and commit messages should sound human; prefer concrete over generic.
- **Don't:** Add docblocks to every method; make every class the same structure; use templated, formal prose everywhere.

See `.cursorrules` for the full guideline.

---

## Quality System

Before proposing changes, ensure:

- `composer phpstan` — 0 errors
- `composer lint` — 0 violations
- `composer lint:tests` — 0 violations (or `make lint-tests`)
- `npm run lint:js` — 0 errors
- `make check` — all pass
- PHPUnit tests for new abilities or governance tasks
- Optionally `make mutation` (Infection) for critical paths

Run `make setup-hooks` to install pre-commit checks.

---

## How Agents Can Improve WP Pinch

### Performance

- **N+1 queries:** Batch `get_post()` with `get_posts( array( 'post__in' => $ids ) )`
- **Taxonomy lookups:** Move `get_object_taxonomies()` outside loops when the post type is constant
- **Caching:** Read-only abilities already cache; consider caching expensive governance findings

### Token Savings (LLM Costs)

- Reduce excerpt/snippet lengths in abilities that send context to the gateway (`synthesize`, `what-do-i-know`, `site-digest`)
- Use the existing filters: `wp_pinch_synthesize_content_snippet_words`, `wp_pinch_synthesize_excerpt_words`
- Add similar filters for other token-heavy abilities if needed

### New Abilities

- Follow the pattern in `class-abilities.php`; use `self::register()` and an `execute_*` method
- Add schema for input/output (helps MCP clients)
- Consider whether the ability should be read-only (enables caching)

### Governance

- Add new `get_*_findings()` helpers that return structured data
- Bundle findings in Tide Report when appropriate
- Keep payloads compact — they go to the gateway and consume tokens

### Documentation

- Update `wiki/Abilities-Reference.md` when adding abilities
- Update `wiki/Hooks-and-Filters.md` when adding filters
- Update `CHANGELOG.md` for user-facing changes

---

## Common Tasks for Agents

### Add a new ability

1. Read `includes/class-abilities.php` — find a similar ability and copy its pattern
2. Add `register_my_ability()` and `execute_my_ability()`
3. Call `register_my_ability()` from the `register_abilities()` action
4. Add test in `tests/test-abilities.php`
5. Update `wiki/Abilities-Reference.md` and bump ability count in tests if documented

### Add a REST endpoint

1. In `class-rest-controller.php`, add `register_rest_route()` in `register_routes()` and wire the callback to the appropriate handler in `includes/Rest/` (e.g. `Rest\Chat::class`, `Rest\Status::class`).
2. In the handler class under `includes/Rest/`, implement the static handler method; define `args` with `sanitize_callback` and `validate_callback` in the route registration.
3. Use `Rest\Auth::check_permission()` or a custom permission callback (e.g. `check_hook_token`, `check_capture_token`).
4. Add schema via `Rest\Schemas::get_*_schema()` for discoverability.

### Add a governance task

1. Add to `get_available_tasks()` and `DEFAULT_INTERVALS`
2. Implement `task_*()` that calls `get_*_findings()` and `deliver_findings()`
3. Add `get_*_findings()` if reusable; consider Tide Report inclusion

### Fix a bug

1. Write a failing test first (see CONTRIBUTING.md)
2. Fix the code
3. Run `make check`

---

## Reference Map

| Topic | Location |
|-------|----------|
| Abilities list | `wiki/Abilities-Reference.md` |
| Architecture | `wiki/Architecture.md` |
| Chat block | `wiki/Chat-Block.md` |
| Configuration | `wiki/Configuration.md` |
| Developer setup | `wiki/Developer-Guide.md`, `CONTRIBUTING.md` |
| Hooks & filters | `wiki/Hooks-and-Filters.md` |
| Security | `wiki/Security.md` |
| Second brain vision | `wiki/Second-Brain-Vision.md`, `.cursor/plans/wordpress_second_brain_vision_*.plan.md` |
| Excellence audit | This file, Appendix: Excellence Audit Checklist |

---

## Appendix: Excellence Audit Checklist

Use this checklist when auditing WP Pinch for quality beyond speed, reliability, and security. Goal: **best OpenClaw integration ever**.

### Agent Experience (AX)

- [ ] **Schema quality** — Do ability input/output schemas clearly describe what the agent should send and expect?
- [ ] **Error messages** — Are errors actionable? (e.g., "Post 123 not found or you lack edit_post permission" vs "Error")
- [ ] **Consistency** — Do abilities follow consistent patterns for pagination (`per_page`, `page`), naming, error shapes?
- [ ] **Discoverability** — Can an agent infer when to use one ability vs another from descriptions alone?

### Token Efficiency

- [ ] **Minimal context** — Are excerpts/snippets sent to the gateway only as large as needed?
- [ ] **Configurable verbosity** — Can users reduce payload size via filters or options?
- [ ] **Schema descriptions** — Are descriptions concise enough that the agent doesn't need to "try things" to understand?

### Observability & Debugging

- [ ] **Audit trail** — Can we trace which ability ran, with what input, and what output?
- [ ] **Structured logs** — Are errors and key events machine-parseable for debugging?
- [ ] **Health signals** — Do status/health endpoints expose circuit breaker, rate limits, last failure?
- [ ] **Trace IDs** — Can we correlate a user request across abilities and external calls?

### Developer Experience (DX)

- [ ] **API clarity** — Is the contract for each ability obvious and stable?
- [ ] **Documentation** — Are examples, flows, and extension points documented?
- [ ] **Error taxonomy** — Do errors have consistent codes/types tools can handle programmatically?
- [ ] **Extensibility** — Are hooks placed where third-party code would need them?

### User Experience (UX)

- [ ] **Onboarding** — Does the setup wizard get users connected and confident quickly?
- [ ] **Feedback** — Do users get clear success/failure and next steps?
- [ ] **Discoverability** — Can users find what the plugin can do without reading the whole wiki?
- [ ] **Recovery** — When connection fails, is the path to fix it obvious?

### Resilience & Graceful Degradation

- [ ] **Partial failure** — If one ability fails, does the rest of the flow degrade gracefully?
- [ ] **Gateway outage** — Does the circuit breaker + retry logic prevent cascading failures?
- [ ] **Timeout handling** — Are long-running operations bounded and reported clearly?

### Privacy & Data Minimization

- [ ] **PII in payloads** — Is only necessary data sent to OpenClaw? (emails, names redacted where appropriate)
- [ ] **Scoping** — Do abilities respect the current user's permissions?
- [ ] **Auditability** — Can admins see what was sent in audit logs?

### Accessibility (UI)

- [ ] **Chat block** — Keyboard navigation, screen reader support, focus management?
- [ ] **Admin** — Forms, labels, and controls meet WCAG basics?

### Internationalization (i18n)

- [ ] **Strings** — Are all user-facing strings wrapped in `__()`, `esc_html__()`, etc.?
- [ ] **Locale** — Do dates, numbers, and messages respect site locale?
- [ ] **RTL** — Is the chat block and admin usable in RTL languages?

### Composability & Workflows

- [ ] **Chaining** — Can abilities be composed? (e.g., search → synthesize → create draft)
- [ ] **Batch operations** — Are there bulk abilities to avoid N round-trips?
- [ ] **Intermediate outputs** — Do abilities return data that other abilities can consume?

### Upgrade & Compatibility

- [ ] **Backward compatibility** — Are ability schema changes additive or versioned?
- [ ] **Migration** — When options/data format changes, is migration documented?
- [ ] **Deprecation** — Are deprecated features clearly signaled before removal?

---

## Version and Requirements

- **WordPress:** 6.9+ (Abilities API)
- **PHP:** 8.1+
- **Dependencies:** Action Scheduler, Jetpack Autoloader, MCP Adapter (WordPress)

Plugin version is in `wp-pinch.php` (`WP_PINCH_VERSION`).
