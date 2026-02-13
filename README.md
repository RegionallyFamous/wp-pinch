# WP Pinch

**Your WordPress site. Your Slack. One very helpful lobster.**

**[wp-pinch.com](https://wp-pinch.com)** · [Wiki](https://github.com/RegionallyFamous/wp-pinch/wiki) · [Releases](https://github.com/RegionallyFamous/wp-pinch/releases)

[![WordPress 6.9+](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org/)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![CI](https://github.com/RegionallyFamous/wp-pinch/actions/workflows/ci.yml/badge.svg)](https://github.com/RegionallyFamous/wp-pinch/actions/workflows/ci.yml)

You're already in WhatsApp. Or Slack. Or Telegram. The last thing you want is another tab, another login, another "where did I put that draft?" WP Pinch gives your site a set of claws and connects it to the chat apps you actually use. Your AI can publish posts, upload media, tweak settings, and run your WooCommerce—from wherever you are. Self-hosted. No SaaS middlemen. Just you, your WordPress, and a lobster who actually does the work.

---

## What if...

**…you could publish a post by texting "ship the Q3 recap"?** WP Pinch exposes your site to AI agents through [OpenClaw](https://github.com/nicepkg/openclaw)—posts, media, users, settings, plugins, the works. One plugin. One lobster. Every group chat.

**…your site could fix itself while you sleep?** Seven background tasks run on a schedule you set: stale content, SEO gaps, comment queue, broken links, security checks, abandoned drafts, and a daily "here's what needs attention" digest. The lobster patrols. You get the report.

**…visitors could chat with an AI that knows your content?** Drop the Pinch Chat block on any page. Real-time streaming, dark mode, accessible, mobile-friendly. They get answers. You get to keep your afternoon.

---

## What the lobster can do

Your AI doesn't just have "access"—it can *do* things. Publish and update posts. Upload and manage media. Tweak options. List and moderate comments. Toggle plugins and themes. Export data. Run cron. When WooCommerce is active, it gets another ten abilities for products and orders. We gave it the keys. We also gave it a very strict bouncer: capability checks, sanitization, audit logging. The AI works *for* you, not *around* you.

[Full abilities reference →](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference)

---

## The fun stuff

**PinchDrop** — Send a rough idea from any connected channel ("we're launching X next week, need a blog post and some social snippets"). Your site turns it into a draft pack: blog post, product update, changelog, social blurbs. Need just a quick note? Quick Drop: title and body, no fluff. The lobster takes dictation. [How it works →](https://github.com/RegionallyFamous/wp-pinch/wiki/PinchDrop)

**Ghost Writer** — That draft you started in 2022? Ghost Writer learns each author's voice from their published posts and can finish abandoned drafts *in that voice*. List drafts, resurrect one by ID, or let the weekly Draft Necromancer task surface the ones worth saving. Even lobsters forget what they were writing. This one doesn't. [Ghost Writer guide →](https://github.com/RegionallyFamous/wp-pinch/wiki/Ghost-Writer)

**Memory Bait, Echo Net, Weave** — Give your agent a site digest for context. Find posts that link to a given post or share its topics. Search and get a payload ready for synthesis. [Abilities reference →](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference)

---

## Why WP Pinch?

**Run your site from the couch.** Yes, in your slippers. Thirty-eight abilities (plus ten for WooCommerce when you need them). Content, media, users, settings, plugins, themes, analytics, menus, meta, revisions, cron. Everything your AI needs to actually manage the site—with a full [reference](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference) when you want the nitty-gritty.

**A chat block that doesn't suck.** Streaming responses. Slash commands. Message feedback. Token tracking. Markdown. Public chat for anonymous visitors. Per-block agent overrides so your support page and your blog can have different personalities. WCAG 2.1 AA. [Chat block details →](https://github.com/RegionallyFamous/wp-pinch/wiki/Chat-Block)

**Governance that runs without you.** Stale content, SEO issues, comment backlog, broken links, security checks, draft graveyard, daily digest—seven tasks, one webhook or server-side delivery. Set the schedule. The lobster does the rest.

**Webhooks that go both ways.** Post published? Comment posted? Order shipped? WP Pinch fires to OpenClaw the moment it happens. Signed, retried, with a circuit breaker when the gateway is down. And OpenClaw can push ability requests *back* to your site. The trap works both ways.

**Tools that feel like magic.** PinchDrop and Ghost Writer (and the rest) turn "I'll do it when I get to my desk" into "done." [Tools & abilities →](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference#tools-pinchdrop--ghost-writer)

---

## Give your site claws in 60 seconds

```bash
wp plugin install https://github.com/RegionallyFamous/wp-pinch/releases/latest/download/wp-pinch.zip --activate
```

1. Open **WP Pinch** in your admin sidebar.
2. Enter your OpenClaw Gateway URL and API Token.
3. Click **Test Connection**.
4. Add a **Pinch Chat** block to any page.

That's it. We said 60 seconds. The lobster is punctual.

[Detailed setup →](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration)

---

## For developers who like to customize everything

Hooks. Lots of them. Remove an ability, change webhook payloads, tweak governance schedules, block roles—the lobster is configurable.

```php
add_filter( 'wp_pinch_abilities', function ( array $abilities ): array {
    unset( $abilities['delete_post'] );
    return $abilities;
} );
```

WP-CLI too: `wp pinch status`, `wp pinch audit list`, `wp pinch governance run`. Script it. Automate it.

[Hooks & filters →](https://github.com/RegionallyFamous/wp-pinch/wiki/Hooks-and-Filters) · [WP-CLI →](https://github.com/RegionallyFamous/wp-pinch/wiki/WP-CLI)

---

## We gave the AI keys. We also gave it a bouncer.

Capability checks on every operation. Input sanitized, output escaped. Nonces, prepared SQL, HMAC-signed webhooks, rate limiting, circuit breaker. We're not reckless. PHPStan Level 6, 160+ tests, WordPress coding standards. Security isn't optional—it's how we sleep at night.

[Full security model →](https://github.com/RegionallyFamous/wp-pinch/wiki/Security) · [Report a vulnerability →](SECURITY.md)

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.9+ (that's when WordPress grew claws) |
| PHP | 8.1+ |
| Action Scheduler | Required (ships with WooCommerce) |
| MCP Adapter plugin | Recommended |

---

## The fine print (and the actually useful docs)

Everything lives in the [GitHub Wiki](https://github.com/RegionallyFamous/wp-pinch/wiki):

- [Abilities Reference](https://github.com/RegionallyFamous/wp-pinch/wiki/Abilities-Reference) — What the lobster can do, in detail
- [Chat Block](https://github.com/RegionallyFamous/wp-pinch/wiki/Chat-Block) — Streaming, slash commands, public mode
- [Architecture](https://github.com/RegionallyFamous/wp-pinch/wiki/Architecture) — How the pieces fit together
- [Hooks & Filters](https://github.com/RegionallyFamous/wp-pinch/wiki/Hooks-and-Filters) — Make the lobster do your bidding
- [Security](https://github.com/RegionallyFamous/wp-pinch/wiki/Security) — The full model
- [Configuration](https://github.com/RegionallyFamous/wp-pinch/wiki/Configuration) — Installation and OpenClaw setup
- [PinchDrop](https://github.com/RegionallyFamous/wp-pinch/wiki/PinchDrop) — Capture-anywhere workflow
- [WP-CLI](https://github.com/RegionallyFamous/wp-pinch/wiki/WP-CLI) — Command reference
- [Developer Guide](https://github.com/RegionallyFamous/wp-pinch/wiki/Developer-Guide) — Contributing and testing
- [FAQ](https://github.com/RegionallyFamous/wp-pinch/wiki/FAQ) — Common questions

---

## License

[GPL-2.0-or-later](LICENSE). Built by [Nick Hamze](https://github.com/RegionallyFamous) with diet pepsi, crustacean puns, and an unreasonable number of PHPStan runs. No lobsters were harmed in the making of this plugin.

**[wp-pinch.com](https://wp-pinch.com)**
