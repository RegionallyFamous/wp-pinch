# WP Pinch Documentation

**The AI agent plugin that grabs your WordPress site with both claws.**

WP Pinch bridges WordPress and [OpenClaw](https://github.com/nicepkg/openclaw), exposing your entire site as a set of AI-accessible tools through the Abilities API and Model Context Protocol (MCP) introduced in WordPress 6.9. Manage your site from WhatsApp, Telegram, Slack, Discord -- or any platform OpenClaw supports.

---

## Quick Links

| Page | What's Inside |
|---|---|
| [Abilities Reference](Abilities-Reference) | All 35 abilities across 10 categories, with parameters and examples |
| [Chat Block](Chat-Block) | SSE streaming, slash commands, public mode, agent overrides, accessibility |
| [Architecture](Architecture) | How the pieces fit together -- diagram and subsystem overview |
| [Configuration](Configuration) | Installation, OpenClaw connection, admin settings, feature flags |
| [Hooks & Filters](Hooks-and-Filters) | 12+ filters and 6+ actions with code examples |
| [Security](Security) | The full security model -- 20+ defense layers |
| [WP-CLI](WP-CLI) | Command reference with examples and output formats |
| [Developer Guide](Developer-Guide) | Contributing, local setup, testing, quality system |
| [FAQ](FAQ) | Common questions answered |

---

## Requirements

| Requirement | Minimum | Notes |
|---|---|---|
| WordPress | 6.9+ | For the Abilities API |
| PHP | 8.1+ | For type hints and enums |
| MCP Adapter plugin | Recommended | For full MCP integration |
| Action Scheduler | Required | Ships with WooCommerce, or install standalone |

---

## Getting Help

- **Bug reports and feature requests:** [GitHub Issues](https://github.com/RegionallyFamous/wp-pinch/issues)
- **Security vulnerabilities:** See [SECURITY.md](https://github.com/RegionallyFamous/wp-pinch/blob/main/SECURITY.md)
- **Contributing:** See [CONTRIBUTING.md](https://github.com/RegionallyFamous/wp-pinch/blob/main/CONTRIBUTING.md)
- **Website:** [wp-pinch.com](https://wp-pinch.com)
