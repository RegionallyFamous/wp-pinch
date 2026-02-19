# Changelog

All notable changes to WP Pinch will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [3.0.3] - 2026-02-18

Why this matters: high-risk maintenance tasks are safer to run from chat, with clearer auditability and fewer release-pipeline surprises.

Key outcomes:
- Higher confidence when running destructive/admin workflows in production.
- More reliable release checks with fewer false negatives.

### Added
- **Safer admin operations from chat** — added system/admin abilities (transients, rewrites, maintenance mode, scoped DB replace, language packs, extension lifecycle, expanded user/comment flows, media thumbnail regeneration) so teams can handle real maintenance work without shell access or risky manual steps.

### Changed
- **Discoverability** — docs and counts now clearly reflect reality (`88` core, `94` total with WooCommerce + Ghost Writer + Molt), so agents and humans stop guessing and start shipping.
- **Guardrails got sharper claws** — destructive DB replace now needs explicit confirmation, user creation blocks dangerous-capability roles, and maintenance marker cleanup prefers safer core file handling.

### Fixed
- **CI/local parity** — stabilized E2E setup and plugin-check paths, and moved Composer `test` to wp-env so "works on my machine" and "works in CI" finally mean the same thing.
- **Noisy translation bootstrap warnings** — deferred translation calls that could run before `init`, reducing false-alarm noise for maintainers.

## [3.0.2] - 2026-02-11

Why this matters: content teams ship faster, governance signals are sharper, and quality gates are less noisy.

Key outcomes:
- Faster post repurposing with less hand-editing.
- More actionable governance findings with less alert fatigue.

### Added
- **Molt got more practical** — added `newsletter` and `sections` formats so one post can become publish-ready channel variants faster.
- **Higher-leverage content tools** — added `analytics-narratives`, `suggest-seo-improvements`, and `submit-conversational-form` to reduce repetitive editorial and reporting work.
- **Semantic freshness governance** — catches content that is "technically recent" but strategically stale; schedule hashing keeps task wiring accurate across setting/version changes.
- **AI dashboard tab** — gives admins an at-a-glance operational view instead of forcing settings-page treasure hunts.

### Changed
- **Linting became maintainable** — moved exception policy into rulesets (instead of inline ignores), which keeps standards enforceable and reviewable.
- **Test suite became trustworthy** — loaded Action Scheduler correctly and removed skipped-test drift so failures are meaningful, not polite fiction.

### Fixed
- **Cross-platform test setup** — fixed macOS bootstrap behavior and corrected expectation mismatches so contributors spend less time debugging tooling and more time fixing product issues.

## [3.0.0] - 2026-02-16

Why this matters: maintainability scales better with cleaner REST boundaries, simpler settings architecture, and fewer refactor traps.

Key outcomes:
- Lower request-handling coupling for safer iteration.
- Clearer configuration and test architecture for contributors.

### Added
- **REST architecture split by responsibility** — moved request handling into `includes/Rest/*` so auth/chat/status/hook/capture logic can evolve independently without one giant controller becoming a lobster-sized monolith.

### Changed
- **Settings registration is now data-driven** — reduced duplication and made option behavior easier to reason about and review.
- **Governance tests now target current task APIs** — aligns tests with real execution paths, reducing brittle legacy coupling.
- **Docs synced to the new architecture** — lowers onboarding friction and reduces "read docs, still confused" moments.

### Fixed
- **Broken governance tests after refactor** — switched to task class `run()` methods so the suite validates current code, not historical ghosts.

## [2.9.0] - 2026-02-15

Why this matters: automation becomes safer to trust in production through tighter blast-radius controls.

Key outcomes:
- Stronger least-privilege defaults for agent execution.
- Safer destructive operations through layered controls.

### Added
- **Security tier focused on real operational risk** — expanded prompt sanitization, loop detection, kill switch/read-only controls, safer token logging, and stronger option denylist to reduce blast radius when automation misbehaves.
- **Least privilege became the default posture** — added dedicated `openclaw_agent` role and stronger credential guidance so teams can stop running agents as overpowered admins.
- **Optional approval workflow for destructive actions** — lets teams introduce human checkpoints before high-impact changes.
- **Block-native Molt output** — `faq_blocks` made AI output directly usable in Gutenberg without copy/paste surgery.

### Changed
- **Webhook execution identity** — uses designated OpenClaw agent user when available, improving traceability and reducing accidental privilege creep.
- **SKILL documentation clarified trust boundaries** — secrets stay in MCP server config, and agent instructions match real product behavior.

## [2.8.0] - 2026-02-14

Why this matters: WP Pinch evolves from isolated commands into a fuller "capture -> reason -> act" workflow.

Key outcomes:
- Better pre-write reasoning for agents before content changes.
- Smoother onboarding and fewer setup mismatches for new installs.

### Added
- **Gateway Phase A/B shipped for agent reliability** — manifest, draft-first workflow, write budgets, audit summaries, health diagnostics, content health reports, term suggestions, and stricter reply sanitization all target one outcome: more useful automation with fewer bad surprises.
- **Coverage expanded with new gateway features** — tests were added alongside behavior, not as an afterthought.

### Documentation
- **Docs normalized for onboarding** — ability counts, install paths, ClawHub flow, FAQs, and troubleshooting were aligned so setup feels less like archaeology.

## [2.7.0] - 2026-02-14

Why this matters: operational stability improves, especially on larger sites and mixed hosting stacks.

Key outcomes:
- Fewer environment-specific surprises in production.
- More predictable behavior across editor and hosting variations.

### Added
- **Operational resilience upgrades** — autoload cleanup, REST availability detection, optimistic locking, and clearer hosting troubleshooting were added to reduce hidden failure modes in production.
- **Editor compatibility improvements** — Classic Editor-aware output and Abilities API compatibility shims keep behavior predictable across WordPress setups.
- **Dashboard activity widget** — gives admins quick visibility into what the claws have been doing.

### Changed
- **Governance memory footprint reduced** — avoids expensive row counts on large sites for steadier scheduled runs.
- **Test environment parity improved** — fewer skipped tests means better confidence before release.

## [2.5.0] - 2026-02-13

Why this matters: teams can shape chat UX faster without custom forks or brittle overrides.

Key outcomes:
- Lower customization cost for site builders.
- Better compatibility across caching and route environments.

### Added
- **Chat block became more site-builder friendly** — Block Bindings, placeholder defaults, and editor supports reduce one-off customization code.
- **Extensibility hook for block metadata** — helps theme/plugin developers tailor behavior without patching core plugin files.

### Changed
- **Compatibility hardening** — improved cache flush behavior across object-cache implementations and modernized string checks.
- **Docs corrected to match shipped behavior** — fewer mismatched numbers, fewer confused users.

### Fixed
- **PHPCS noise in intentional no-op path** — fixed lint while preserving compatibility intent.

## [2.4.2] - 2026-02-13

Why this matters: onboarding gets smoother and everyday admin tasks feel less like a maze.

Key outcomes:
- Faster first-run success for new installs.
- Cleaner admin UX with safer interaction defaults.

### Added
- **Onboarding and UX polish pass** — first-run wizard, clearer settings layout, better chat ergonomics, and save feedback were added to reduce first-use friction.

### Changed
- **Admin code became easier to maintain** — moved inline styles to CSS and tightened pagination behavior.
- **Safer UI scripting defaults** — reduced risky DOM patterns in wizard interactions.

### Fixed
- **Lint cleanup** — removed avoidable style/tooling friction for contributors.

## [2.4.1] - 2026-02-13

Why this matters: more security and dependency checks happen before merge, where fixes are cheaper.

Key outcomes:
- Earlier detection of security and dependency risk.
- Fewer late-stage CI surprises for contributors.

### Added
- **Security checks shifted left** — CodeQL and dependency review workflows catch issues earlier in PRs.
- **Contributor guidance expanded** — clearer testing/perf expectations means fewer surprise CI failures.

### Changed
- **Project hygiene modernized** — templates, ownership, and testing docs improved team-level consistency.
- **Dependency/toolchain refresh** — keeps the project secure and maintainable as upstream evolves.

## [2.4.0] - 2026-02-12

Why this matters: content workflows now support long-horizon operations (capture, memory, synthesis, reporting), not just one-off commands.

Key outcomes:
- Better continuity from idea capture to publishable output.
- Actionable daily governance summaries instead of alert noise.

### Added
- **Capture, memory, and synthesis workflow expansion** — Quick Drop, site-digest, Tide Report, related-posts, and synthesize were added to move from "single command tools" to "continuous editorial workflow."
- **Governance findings bundling** — Tide Report helps teams act on one daily summary instead of chasing separate alerts.

### Changed
- **Catalog and schema alignment** — documentation and payload contracts now reflect actual capture + synthesis behavior.

## [2.3.1] - 2026-02-12

Why this matters: documentation now matches shipped behavior, reducing onboarding confusion right when adoption accelerated.

Key outcomes:
- Less mismatch between docs and runtime behavior.
- Faster team onboarding with fewer "is this outdated?" moments.

### Changed
- **Documentation consistency pass** — aligned README/readme governance messaging with shipped feature set.

## [2.3.0] - 2026-02-12

Why this matters: abandoned drafts become recoverable assets instead of permanent content debt.

Key outcomes:
- More draft recovery and less wasted editorial effort.
- Safer launch posture for new capture and ghostwriting workflows.

### Added
- **Ghost Writer launched to rescue content debt** — voice profiling + draft resurrection helps teams revive stalled drafts instead of rewriting from scratch.
- **Draft Necromancer workflow** — gives governance a practical "finish what we started" loop (yes, we named it that on purpose).
- **PinchDrop capture pipeline** — signed, idempotent ingestion turns quick ideas into structured drafts without duplicate chaos.

### Security
- **Security controls added with feature launch** — role checks, per-post permissions, sanitization, rate limits, and signed capture boundaries keep creative automation from becoming creative incidents.

### Changed
- **Catalog and cleanup paths updated** — keeps discoverability and uninstall behavior aligned with new Ghost Writer/PinchDrop data.

## [2.2.0] - 2026-02-12

Why this matters: chat graduates from "cool demo" to safer, configurable production feature.

Key outcomes:
- Better day-to-day usability for real operator workflows.
- Stronger isolation and permission controls as exposure increased.

### Added
- **Chat product matured from demo to deployable** — public mode, streaming, slash commands, feedback, token visibility, retries, and per-block agent targeting were added for real-world usage patterns.
- **Bidirectional integrations expanded** — incoming webhook support means WordPress can now both call and receive task execution flows.

### Security
- **Security tightened where exposure increased** — added per-post meta authorization, stronger public-chat isolation, and complete uninstall cleanup to match expanded chat surface area.

### Improved
- **Accessibility and polish** — improved reduced-motion, high-contrast, dark-mode, and editor controls so the chat UI works for more users in more contexts.

### Fixed
- **Session and stream scoping bugs** — fixed state setup and auth boundaries for more predictable behavior.

## [2.1.0] - 2026-02-11

Why this matters: it lays the reliability and observability foundation later features build on.

Key outcomes:
- Faster incident detection and safer gateway failure behavior.
- Stronger operator controls and day-to-day chat ergonomics.

### Added
- **Reliability and observability foundation release** — circuit breaker, feature flags, signed webhooks, health endpoint, rate-limit headers, better audit tooling, and structured CLI output were added to make operations safe at scale.
- **Chat UX became production-ready** — streaming, retries, markdown, copy tools, char counters, keyboard shortcuts, and error boundaries improved day-to-day usability.
- **Performance and i18n tooling** — object cache support, load testing script, and POT generation improved release readiness.

### Changed
- **Dark-mode and internals cleanup** — improved visual consistency and made dispatch/registration/cache paths more deterministic.

### Fixed
- **Compatibility and CI stability fixes** — corrected hook timing, role-check ordering, static analysis edge cases, and test environment setup to reduce flaky release gates.

## [2.0.0] - 2026-02-11

Why this matters: this is the trust release - broad hardening before scaling usage.

Key outcomes:
- Higher confidence in access, data, and traffic safety boundaries.
- Fewer high-impact failure modes during scale-up.

### BREAKING

- Chat block now requires stable `blockId` storage keys so multi-block sessions stay isolated and reliable.
- User email and sensitive order fields are removed from ability responses to minimize accidental data exposure.
- MCP no longer exposes sensitive core info abilities publicly.
- Bulk deletes now trash instead of hard-delete for safer recovery.
- `admin_email` removed from option read allowlist.
- Administrator assignment remains unconditionally blocked in role filtering paths.

### Security

- **Access and privilege controls** — tightened post/meta/action capability checks, blocked dangerous role escalation paths, and hardened cron/menu/session boundaries to reduce unauthorized actions.
- **Data and disclosure controls** — expanded option denylist and PII redaction, masked sensitive tokens, and reduced sensitive diagnostic leakage in gateway/security outputs.
- **Input, output, and traffic controls** — strengthened validation/sanitization, SSRF protections, and rate-limiting behavior so malformed or hostile input is less likely to become a production incident.
- **Lifecycle trust improvements** — improved uninstall/privacy cleanup paths so retention and erasure behavior better match administrator expectations.

### Fixed

- **Chat session stability** — switched to persistent block IDs so sessions survive reloads and multi-block pages stop cross-talking.

## [1.0.2] - 2026-02-11

Why this matters: local setup is less confusing and shipped artifacts are more consistent.

Key outcomes:
- Fewer source-install support issues.
- More consistent release artifact quality.

### Fixed
- **Source install confusion** — documented required build step to fix missing admin assets in local/dev installs.

### Changed
- **Release process clarity** — added `make zip` guidance so shipped packages include compiled assets.

## [1.0.1] - 2026-02-10

Why this matters: early post-launch accessibility, stability, and escaping gaps were closed quickly.

Key outcomes:
- Better chat accessibility and multi-block stability.
- Safer defaults across escaping and update paths.

### Fixed
- **Usability and accessibility fixes** — resolved frontend a11y announcements, multi-instance session isolation, high-contrast focus visibility, and robust chat runtime behavior.
- **Security and output safety** — tightened update URI handling and escaping/context generation paths.

### Changed
- **Docs became launch-ready** — README/readme.txt expanded to better explain capabilities, architecture, and setup expectations.

### Security
- **Foundational hardening pass** — cleaned static analysis and coding-standard violations, replaced risky file ops, and enforced safer REST/update defaults.

### Added
- **Quality tooling baseline** — introduced PHPCS/PHPStan and automation hooks so regressions are caught before merge.
- **Compliance and observability docs** — documented GDPR and Site Health integrations for admin confidence.

## [1.0.0] - 2026-02-10

Why this matters: it established the public foundation for AI-assisted WordPress operations via MCP.

Key outcomes:
- Practical AI-assisted site operations available from day one.
- Strong base for security, observability, and extensibility growth.

### Added
- **Initial launch** — shipped MCP-connected WordPress abilities, governance automation, chat block, CLI/admin controls, audit logging, and CI foundations to make AI-assisted site management practical from day one.

[Unreleased]: https://github.com/RegionallyFamous/wp-pinch/compare/v3.0.3...HEAD
[3.0.3]: https://github.com/RegionallyFamous/wp-pinch/compare/v3.0.2...v3.0.3
[3.0.2]: https://github.com/RegionallyFamous/wp-pinch/compare/v3.0.0...v3.0.2
[3.0.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.9.0...v3.0.0
[2.9.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.8.0...v2.9.0
[2.8.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.7.0...v2.8.0
[2.7.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.5.0...v2.7.0
[2.5.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.4.2...v2.5.0
[2.4.2]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.4.1...v2.4.2
[2.4.1]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.4.0...v2.4.1
[2.4.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.3.1...v2.4.0
[2.3.1]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.3.0...v2.3.1
[2.3.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.2...v2.0.0
[1.0.2]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/RegionallyFamous/wp-pinch/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/RegionallyFamous/wp-pinch/releases/tag/v1.0.0
