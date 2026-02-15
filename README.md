# WP Pinch

**WordPress in your pocket. Your AI assistant runs it from the chat you never leave.**

One plugin. Connect OpenClaw (or any MCP client), and your site is in the same chat — publish, Molt, PinchDrop, Tide Report, from WhatsApp, Slack, or Telegram.

[OpenClaw](https://github.com/openclaw/openclaw) is the personal AI on those channels; it *does* things. **WP Pinch is the WordPress tool:** 38+ abilities so it can manage your site from there.

**[wp-pinch.com](https://wp-pinch.com)** · [Wiki](https://github.com/RegionallyFamous/wp-pinch/wiki) · [Releases](https://github.com/RegionallyFamous/wp-pinch/releases) · **[Install in 60 seconds →](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration)**

[![WordPress 6.9+](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![CI](https://github.com/RegionallyFamous/wp-pinch/actions/workflows/ci.yml/badge.svg)](https://github.com/RegionallyFamous/wp-pinch/actions/workflows/ci.yml)

---

## Why it's awesome

**OpenClaw** = your assistant (WhatsApp, Telegram, Slack, Discord, and more). Memory, sessions, webhooks, and a **tools** layer — you connect MCP servers like WP Pinch.

**WP Pinch** = WordPress as tools for that assistant. So:

- *"Publish the Q3 recap."* → Done. No wp-admin.
- *"Turn post 123 into a Twitter thread, LinkedIn post, and meta description."* → Molt: one call, nine formats.
- Paste a rough idea in Slack → PinchDrop turns it into a draft pack (blog + social).
- *"What have I written about pricing?"* → What do I know: answer + source post IDs.
- Hit a bookmarklet on any webpage → Web Clipper saves it to WordPress. No chat needed.
- Your site runs governance on a schedule; Tide Report pings one daily summary to your channel (stale posts, SEO, comments, broken links, abandoned drafts).

No extra logins. No "I'll do it at my desk." You talk; your assistant has the keys. Your site isn't another tab — it's in the same chat. Self-hosted. Your data. (We gave the AI the keys; we gave it a bouncer too. More in [Security](https://github.com/RegionallyFamous/wp-pinch/wiki/Security).)

**Who it's for:** Solo creators, small teams, anyone who talks to an AI in chat and wants their WordPress site *there*. If you've ever thought "I'll publish that when I'm at my laptop," stop context-switching and start pinching. This is for you.

---

## The stack in plain English

**OpenClaw** ([openclaw.ai](https://openclaw.ai), [GitHub](https://github.com/openclaw/openclaw)): open-source personal AI. You run it. It connects to WhatsApp, Telegram, Slack, Discord, and more — and *does* things via MCP tools, skills, and code.

**WP Pinch** plugs WordPress in. Your site becomes an MCP server: 38+ abilities (posts, media, users, search, governance), plus PinchDrop, Molt (one post → nine formats), Ghost Writer, What do I know. Bonus: **Pinch Chat** block and **webhooks** (publish/comment → OpenClaw). One plugin.

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

Plus: 38+ core abilities (content, media, users, comments, settings, plugins, themes, menus, meta, revisions, cron) and 2 more for WooCommerce when active. [Full abilities reference →](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference)

---

## Give your site claws in 60 seconds

Yes, we said claws. The lobster theme is non-negotiable — and neither is the 60-second install.

```bash
wp plugin install https://github.com/RegionallyFamous/wp-pinch/releases/latest/download/wp-pinch.zip --activate
```

1. Open **WP Pinch** in your admin sidebar.
2. Enter your **OpenClaw Gateway URL** and **API Token**.
3. Click **Test Connection**.
4. In OpenClaw: `npx openclaw connect --mcp-url https://your-site.com/wp-json/wp-pinch/v1/mcp`

**Done.** Manage your site from WhatsApp, Slack, or Telegram — or add the **Pinch Chat** block so visitors can chat with an AI that knows your content. To give your agent WordPress-specific behavior (when to use which ability, example prompts), see the [OpenClaw Skill](https://github.com/RegionallyFamous/wp-pinch/wiki/OpenClaw-Skill) guide.

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
| Action Scheduler | Optional — required for recurring governance, webhook retries, and audit log cleanup. Install from [WooCommerce](https://wordpress.org/plugins/woocommerce/) or [standalone](https://github.com/woocommerce/action-scheduler/releases). |
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
