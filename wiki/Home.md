# WP Pinch Documentation

**WordPress in your pocket. Your AI assistant runs it from the chat you never leave.**

One plugin. Connect [OpenClaw](https://github.com/openclaw/openclaw) (or any MCP client); your site is in the same chat — 48 core abilities (plus 2 WooCommerce, plus Ghost Writer and Molt when feature flags enabled = 54 total), Molt, PinchDrop, What do I know, daily Tide Report (8 governance tasks), Pinch Chat block, webhooks. Self-hosted. Your data.

**One line:** Your site isn't another tab — it's in the same chat. You talk; your assistant has the keys. [Install & connect →](Configuration)

This wiki goes deeper than the README—start with [Configuration](Configuration) or [Recipes](Recipes).

---

## Why WP Pinch?

- **You're already in the chat.** Your assistant lives in WhatsApp, Slack, Telegram. WP Pinch gives it the keys to WordPress — no tab-switching to wp-admin.
- **Real actions, not just "access."** Publish, Molt (one post → nine formats), PinchDrop (idea → draft pack), Ghost Writer (finish drafts in your voice), What do I know (query your content), governance (stale, SEO, comments, Tide Report). [Abilities Reference](Abilities-Reference)
- **Capture anywhere.** PinchDrop from any channel; Web Clipper from the browser (token-protected). Ideas land in WordPress without opening the admin.
- **Your site can chat.** Pinch Chat block: visitors talk to an AI that knows your content. Streaming, slash commands, optional public mode.
- **Governance on autopilot.** Stale posts, SEO gaps, comments, broken links, abandoned drafts — Tide Report bundles one daily summary to your channel.

**Who it's for:** Solo creators, small teams, anyone who talks to an AI in chat and wants their WordPress site *there* — not in another app.

**What we're not:** A replacement for WordPress, a hosted AI product, or a full PKM app. We're the bridge — the lobster runs the trap; you run the conversation. Your data stays on your server; any MCP client can use the abilities. For the knowledge-store / second-brain framing (CODE, PARA), see [Second Brain Vision](Second-Brain-Vision).

---

## Quick Links

**New here?** [Configuration](Configuration) (install, connect OpenClaw, wizard) then [Recipes](Recipes) (outcome-first workflows) or [Abilities Reference](Abilities-Reference).

| Page | What's Inside |
|------|----------------|
| [Recipes](Recipes) | **Start here for value.** Outcome-first flows: publish from chat, Molt, PinchDrop, What do I know, Web Clipper, Tide Report, Ghost Writer |
| [Abilities Reference](Abilities-Reference) | Every ability and tool: core (content, media, users, …), PinchDrop, Ghost Writer, Molt, What do I know, Project Assembly, Spaced Resurfacing, Find Similar, Knowledge Graph, Web Clipper |
| [Configuration](Configuration) | Installation, OpenClaw connection, onboarding wizard, settings |
| [PinchDrop](PinchDrop) | Capture from channels + Web Clipper (browser bookmarklet) |
| [Chat Block](Chat-Block) | Streaming chat on your site, slash commands, public mode |
| [Molt](Molt) | One post → nine formats |
| [Ghost Writer](Ghost-Writer) | Voice profiles, abandoned drafts, Draft Necromancer |
| [Architecture](Architecture) | How the pieces fit together |
| [Second Brain Vision](Second-Brain-Vision) | CODE/PARA, knowledge store, capture → distill → express |
| [Security](Security) | Trust model, hardening, Web Clipper token |
| [OpenClaw Quick Start](OpenClaw-Quick-Start) | OpenClaw + WordPress in 5 minutes |
| [OpenClaw Skill](OpenClaw-Skill) | Agent prompt/skill: when to use which ability |
| [Webhook Payload](Webhook-Payload) | Event types and JSON shape (WordPress → OpenClaw) |
| [Session and Identity](Session-And-Identity) | How gateway/token/session map to WordPress users |
| [Error Codes](Error-Codes) | REST/MCP error codes and how to handle them |
| [Troubleshooting](Troubleshooting) | REST API blocked, WAF, security plugins, caching, managed hosting |
| [Limits](Limits) | Rate limits, pagination, and max sizes |
| [Hooks & Filters](Hooks-and-Filters) | Extend and customize |
| [WP-CLI](WP-CLI) | Commands and output formats |
| [FAQ](FAQ) | Common questions |
| [PKM Import](PKM-Import) | Roadmap: Obsidian / Notion import |
| [Integration and Value](Integration-and-Value) | Strategy: integration vs. value, what to build next |

---

## Requirements

| Requirement | Minimum | Notes |
|-------------|---------|-------|
| WordPress | 6.9+ | Abilities API |
| PHP | 8.1+ | Type hints, enums |
| Action Scheduler | Required for governance | Ships with WooCommerce or install standalone. Needed for scheduled tasks and webhook retries; abilities and Chat block work without it. See [FAQ](FAQ#why-do-i-need-action-scheduler). |
| OpenClaw (or MCP client) | For chat/channel integration | Abilities work with any MCP client |

---

## Getting Help

- **Issues and features:** [GitHub Issues](https://github.com/RegionallyFamous/wp-pinch/issues)
- **Security:** [SECURITY.md](https://github.com/RegionallyFamous/wp-pinch/blob/main/SECURITY.md)
- **Contributing:** [CONTRIBUTING.md](https://github.com/RegionallyFamous/wp-pinch/blob/main/CONTRIBUTING.md)
- **Website:** [wp-pinch.com](https://wp-pinch.com)
