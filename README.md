# WP Pinch

**WordPress in your pocket. Your AI assistant runs it from the chat you never leave.**

One plugin. Connect OpenClaw (or any MCP client), and your site is in the same chat — publish, Molt, PinchDrop, Tide Report, from WhatsApp, Slack, or Telegram.

[OpenClaw](https://github.com/openclaw/openclaw) is the personal AI on those channels; it *does* things. **WP Pinch is the WordPress tool:** 48 core abilities (plus 2 WooCommerce when active; plus Ghost Writer and Molt when feature flags enabled = 54 total).

**[wp-pinch.com](https://wp-pinch.com)** · [Wiki](https://github.com/RegionallyFamous/wp-pinch/wiki) · [Releases](https://github.com/RegionallyFamous/wp-pinch/releases) · [ClawHub](https://clawhub.ai/nickhamze/pinch-to-post) · **[Install in 60 seconds →](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration)**

[![WordPress 6.9+](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![CI](https://github.com/RegionallyFamous/wp-pinch/actions/workflows/ci.yml/badge.svg)](https://github.com/RegionallyFamous/wp-pinch/actions/workflows/ci.yml)

**[Try WP Pinch in 30 seconds](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/RegionallyFamous/wp-pinch/main/blueprint.json)** — No install. No signup. Experience the admin UI, toggle abilities, and explore the Pinch Chat block in your browser.

---

## Why it's awesome

**OpenClaw** = your assistant (WhatsApp, Telegram, Slack, Discord, and more). Memory, sessions, webhooks, and a **tools** layer — you connect MCP servers like WP Pinch.

**WP Pinch** = WordPress as tools for that assistant. Key features:

- **Molt** — Turn one post into 10 formats: social (Twitter, LinkedIn), thread, FAQ block, email snippet, meta description, pull quote, key takeaways, CTA variants. One call, many outputs.
- **PinchDrop** — Turn rough text into a draft pack (post, product_update, changelog, social). Quick Drop mode for minimal notes. Send an idea from Slack; get a draft back.
- **What do I know** — Natural-language query → search + synthesis → answer with source post IDs. *"What have I written about pricing?"* → answer + sources.
- **Ghost Writer** — Complete abandoned drafts in your writing voice. List drafts ranked by resurrection potential, then `ghostwrite` to finish in your style.
- **Tide Report** — Governance runs on a schedule (stale posts, SEO health, comments, broken links, abandoned drafts); Tide Report bundles findings into one daily webhook to your channel.
- **Web Clipper** — Token-protected bookmarklet. Hit any webpage; save snippet to WordPress as draft. No chat needed.

So: *"Publish the Q3 recap."* → Done. *"Turn post 123 into a Twitter thread and meta description."* → Molt. Paste an idea in Slack → PinchDrop.

No extra logins. No "I'll do it at my desk." You talk; your assistant has the keys. Your site isn't another tab — it's in the same chat. Self-hosted. Your data. (We gave the AI the keys; we gave it a bouncer too. More in [Security](https://github.com/RegionallyFamous/wp-pinch/wiki/Security).)

**Who it's for:** Solo creators, small teams, anyone who talks to an AI in chat and wants their WordPress site *there*. If you've ever thought "I'll publish that when I'm at my laptop," stop context-switching and start pinching. This is for you.

---

## The stack in plain English

**OpenClaw** ([openclaw.ai](https://openclaw.ai), [GitHub](https://github.com/openclaw/openclaw)): open-source personal AI. You run it. It connects to WhatsApp, Telegram, Slack, Discord, and more — and *does* things via MCP tools, skills, and code.

**WP Pinch** plugs WordPress in. Your site becomes an MCP server: 48 core abilities across 12 categories (content, media, users, comments, settings, plugins, themes, menus, meta, revisions, cron), plus 2 WooCommerce when active, plus PinchDrop, Molt, Ghost Writer when feature flags enabled. Bonus: **Pinch Chat** block and **webhooks** (publish/comment → OpenClaw). One plugin.

---

## What you can do (concrete)

| You want to… | How it works |
|--------------|--------------|
| Publish or update a post from chat | Your assistant calls `create-post` / `update-post`. Draft-first: posts are saved as draft with a **preview URL**; use **preview-approve** to publish after review. |
| Turn one post into social + FAQ + meta | Use Molt: one ability call or `/molt 123` in chat. Nine output formats. |
| Capture an idea from WhatsApp/Slack without opening WP | PinchDrop: send the idea to your assistant; it hits the PinchDrop capture endpoint and creates a draft pack (or Quick Drop for a minimal note). |
| Save a webpage snippet to WordPress from the browser | Web Clipper: token-protected REST endpoint. Bookmarklet or extension calls it; post is created as draft. |
| Ask "what did I write about X?" and get an answer with sources | What do I know: natural-language query → search + synthesis → answer plus post IDs. |
| Resurrect an old draft in your writing voice | Ghost Writer: list abandoned drafts, then `ghostwrite` to get completed content in your style. |
| Have your site report what needs attention (stale posts, SEO, comments) | Governance tasks run on a schedule; Tide Report bundles findings into one daily webhook to OpenClaw. You see it in your channel. |
| Let visitors chat with an AI that knows your content | Pinch Chat block on any page. Streaming, slash commands, optional public mode. |

Plus: 48 core abilities (content, media, users, comments, settings, plugins, themes, menus, meta, revisions, cron), 2 WooCommerce when active, and Ghost Writer (3) + Molt (1) when feature flags enabled — 54 total. [Full abilities reference →](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference)

---

## Give your site claws in 60 seconds

Yes, we said claws. The lobster theme is non-negotiable — and neither is the 60-second install.

```bash
wp plugin install https://github.com/RegionallyFamous/wp-pinch/releases/latest/download/wp-pinch.zip --activate
```

> **Note:** WP Pinch is distributed via GitHub (not yet on wordpress.org). The zip above is built from source for each release.

1. Open **WP Pinch** in your admin sidebar.
2. Enter your **OpenClaw Gateway URL** and **API Token**.
3. Click **Test Connection**.
4. In OpenClaw: connect to the MCP endpoint. Command syntax may vary by version — see [OpenClaw CLI docs](https://docs.openclaw.ai/cli). Example: `npx openclaw connect --mcp-url https://your-site.com/wp-json/wp-pinch/v1/mcp`

**Done.** Manage your site from WhatsApp, Slack, or Telegram — or add the **Pinch Chat** block so visitors can chat with an AI that knows your content. To give your agent WordPress-specific behavior, install the skill from ClawHub: `clawhub install nickhamze/pinch-to-post` — or see the [OpenClaw Skill](https://github.com/RegionallyFamous/wp-pinch/wiki/OpenClaw-Skill) guide.

**Next:** [Configuration](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration) (webhooks, governance) · [Abilities Reference](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference)

---

## For developers

Hooks and filters for abilities, webhooks, governance. WP-CLI: `wp pinch status`, `wp pinch audit list`, `wp pinch governance run`.  
→ [Hooks & filters](https://github.com/RegionallyFamous/wp-pinch/wiki/Hooks-and-Filters) · [WP-CLI](https://github.com/RegionallyFamous/wp-pinch/wiki/WP-CLI)

---

## Security

Capability checks, sanitization, audit logging, HMAC webhooks, rate limiting, circuit breaker.  
→ [Security model](https://github.com/RegionallyFamous/wp-pinch/wiki/Security) · [Report a vulnerability](SECURITY.md)

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.9+ (Abilities API) |
| PHP | 8.1+ |
| Action Scheduler | Required for governance (scheduled tasks, webhook retries). Abilities and Chat block work without it. Install from [WooCommerce](https://wordpress.org/plugins/woocommerce/) or [standalone](https://github.com/woocommerce/action-scheduler/releases). See [FAQ](https://github.com/RegionallyFamous/wp-pinch/wiki/FAQ#why-do-i-need-action-scheduler). |
| OpenClaw | For chat/channel integration; any MCP client can use the abilities |

**Multisite:** On WordPress Multisite, network admins get **Network → Settings → WP Pinch** to set shared Gateway URL and API Token. Sites can inherit network defaults or override per site. See [Configuration](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration#multisite-network).

**Quick answers:** Don't have OpenClaw yet? The abilities and MCP server work with any MCP-compatible client. Your data stays on your server; we don't send content to third parties. [FAQ](https://github.com/RegionallyFamous/wp-pinch/wiki/FAQ)

---

## What we're not building

We're not a replacement for WordPress or a hosted AI SaaS. We're the **bridge**: your site stays in WordPress; your assistant gets a full toolkit. The lobster runs the trap; you run the conversation. PKM import (Obsidian → posts) is on the roadmap. [PKM Import →](https://github.com/RegionallyFamous/wp-pinch/wiki/PKM-Import)

---

## Docs (wiki)

**AI agents and coding assistants:** See [AGENTS.md](AGENTS.md) for architecture, extension points, and how to improve WP Pinch.

| Start here | Full docs |
|------------|------------|
| [Configuration](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration) — Connect OpenClaw, webhooks, governance | [Recipes](https://github.com/RegionallyFamous/wp-pinch/wiki/Recipes) — Outcome-first workflows (publish, Molt, PinchDrop, What do I know, Tide Report, Ghost Writer) |
| [Recipes](https://github.com/RegionallyFamous/wp-pinch/wiki/Recipes) — What you can do, step-by-step | [Abilities Reference](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference) · [Chat Block](https://github.com/RegionallyFamous/wp-pinch/wiki/Chat-Block) · [Security](https://github.com/RegionallyFamous/wp-pinch/wiki/Security) · [FAQ](https://github.com/RegionallyFamous/wp-pinch/wiki/FAQ) |

---

## License

[GPL-2.0-or-later](LICENSE). Built by [Nick Hamze](https://github.com/RegionallyFamous). Your site, your assistant, one conversation — and no lobsters were harmed.

**[wp-pinch.com](https://wp-pinch.com)**
