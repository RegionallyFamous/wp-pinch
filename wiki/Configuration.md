# Configuration

Get your lobster in the water. This page covers installation, OpenClaw connection, and every setting in the admin. First time? Activate the plugin and you'll be whisked to a short onboarding wizard — connect, test, try it. No swimming in the dark.

## Installation

### The Quick Way (WP-CLI)

```bash
wp plugin install https://github.com/RegionallyFamous/wp-pinch/releases/latest/download/wp-pinch.zip --activate
```

> **Note:** WP Pinch is distributed via GitHub (not yet on wordpress.org). The zip above is built from source for each release.

### Upload via Admin

1. Download the latest `.zip` from the [Releases page](https://github.com/RegionallyFamous/wp-pinch/releases)
2. In WordPress admin: **Plugins > Add New > Upload Plugin**
3. Upload the zip and click **Install Now**
4. Activate

### From Source (Development)

```bash
cd wp-content/plugins
git clone https://github.com/RegionallyFamous/wp-pinch.git
cd wp-pinch
composer install
npm install && npm run build
wp plugin activate wp-pinch
```

> **Note:** If you install from source, you must run `npm run build` to generate the `build/` directory. Without it, admin assets will 404. A lobster without a shell is just a confused crustacean.

---

## Don't have OpenClaw yet?

WP Pinch needs an OpenClaw gateway to send webhooks and connect your chat channels. If you're new:

1. **Install OpenClaw** — See [OpenClaw docs](https://docs.openclaw.ai/) and [GitHub](https://github.com/openclaw/openclaw). Quick start: `npx openclaw@latest` (or follow the official install guide).
2. **Start the gateway** — Run OpenClaw and note your Gateway URL and API Token (or create one in the OpenClaw admin).
3. **Copy into WP Pinch** — Enter Gateway URL and API Token in **WP Pinch → Connection**, then **Test Connection**.

Without OpenClaw, the MCP server and abilities still work with any MCP-compatible client (e.g. another AI agent). Webhooks and chat channels require OpenClaw or a compatible gateway.

---

## Initial Setup

1. Navigate to **WP Pinch** in your admin sidebar
2. Enter your **OpenClaw Gateway URL** (e.g., `https://your-gateway.openclaw.ai`)
3. Enter your **API Token**
4. Click **Test Connection** to verify
5. Configure webhook events and governance tasks to your liking

---

## Connecting OpenClaw

Point OpenClaw at your site's MCP endpoint:

```bash
npx openclaw connect --mcp-url https://your-site.com/wp-json/wp-pinch/v1/mcp
```

OpenClaw will discover available abilities and begin routing messages from your configured channels (WhatsApp, Telegram, Slack, Discord, etc.) to your WordPress site. Two commands and you're pinching. For a ready-made skill (when to use which ability, example prompts), install from ClawHub: `clawhub install nickhamze/pinch-to-post` — or see [OpenClaw Skill](OpenClaw-Skill) to copy manually.

**Which WordPress site does the skill use?** Set environment variables on your OpenClaw instance: **`WP_SITE_URL`** (e.g. `https://mysite.com`) is the main one — that's how you choose which site to connect to. Also set `WP_USERNAME` and `WP_APP_PASSWORD` for REST auth. For multiple sites, use different workspaces or env configs with different `WP_SITE_URL` values. MCP users: the MCP server config (or `--mcp-url`) determines which site the tools connect to.

You can also add your Gateway URL directly in the WP Pinch settings for webhook-based integration — ideal for sites that want real-time push notifications when content changes. Your site and OpenClaw, holding claws.

For step-by-step workflows (publish from chat, Molt, PinchDrop, etc.), see [Recipes](Recipes).

**MCP command syntax** may vary by OpenClaw version. See [OpenClaw CLI docs](https://docs.openclaw.ai/cli) for the current command.

---

## Recommended features to enable after install

Several features are off by default and controlled via feature flags. After installing, consider enabling in **WP Pinch → Features**:

| Feature | What it unlocks |
|---------|-----------------|
| **Streaming** | Pinch Chat block streams responses in real time instead of waiting for full reply |
| **Slash commands** | `/molt 123`, `/pinchdrop`, etc. in chat |
| **Molt** | Turn one post into 10 formats (social, thread, FAQ, meta description, etc.) |
| **Ghost Writer** | Complete abandoned drafts in your writing voice |
| **Public chat** | Let visitors use the Pinch Chat block without logging in |

Without these, the Chat block and abilities still work — you just get a simpler experience. Enable what you need.

---

## Credentials & Security

**Use application passwords, not full admin credentials.** When OpenClaw or other MCP clients authenticate to WordPress:

- **Create a dedicated user** for the AI agent with the minimum capabilities it needs (or use the [OpenClaw role](#openclaw-role) when available).
- **Generate an application password** (Users → Profile → Application Passwords) instead of sharing your main password. Application passwords can be revoked individually.
- **Never store credentials in config files.** Use environment variables or a secret manager and reference them (e.g. `WP_APP_PASSWORD`, `WP_PINCH_API_TOKEN`). The OpenClaw config should point to secrets, not contain them.
- **Rotate tokens on a schedule.** API tokens and application passwords should be rotated periodically (e.g. every 90 days). Revoke old application passwords after rotation.
- **WP Pinch API token** (Connection tab) is used for outbound webhooks and incoming webhook verification. Treat it like a password — it is masked in the admin UI and should never be committed to version control.
- **Settings form security** — All WP Pinch settings forms post to `options.php` and use `settings_fields()`, so WordPress verifies the option-group nonce on save before updating any option.

See [Security](Security) for the full defense-in-depth overview.

### Multisite (Network)

On **WordPress Multisite**, WP Pinch adds **Network → Settings → WP Pinch**. Network admins can set:

- **Gateway URL** — Default gateway for all sites. Sites can use this when "Use network default" is enabled in their Connection tab.
- **API Token** — Network-wide API token. Sites can inherit this token or override per site.

Each site still has its own WP Pinch settings (WP Pinch in the site admin sidebar). The network page also shows a **Sites** table with links to each site's WP Pinch, and a **Cross-site audit** of the last 50 ability executions across all sites.

### OpenClaw role

WP Pinch provides a dedicated **OpenClaw Agent** role for least-privilege webhook execution. In **WP Pinch > Connection**, under **Agent identity**:

- **Webhook execution user** — Choose "Use first administrator" (default) or a user with the OpenClaw Agent role.
- **Create OpenClaw agent user** — Creates a new user `openclaw-agent` with the OpenClaw Agent role and sets them as the execution user. Then create an application password for that user in Users → Profile.
- **Capability groups** — Control what the OpenClaw Agent role can do: Content, Media, Taxonomies, Users, Comments, Settings, Plugins, Themes, Menus, Cron. Default: Content, Media, Taxonomies, Users, Comments. Enable Settings, Plugins, or Themes only if the agent needs those abilities.

---

## Admin Settings

### Connection Tab

| Setting | Description |
|---|---|
| **Gateway URL** | Your OpenClaw gateway endpoint |
| **API Token** | Authentication token for the gateway |
| **Safety controls** | **Disable API access** — when checked, all REST endpoints return 503. **Read-only mode** — when checked, write abilities are blocked. **Strict gateway reply sanitization** — when checked, chat replies are stripped of HTML comments and instruction-like text, and iframe/object/embed/form are removed to reduce prompt-injection and XSS risk. See [Security](Security). |
| **Rate Limit** | Maximum requests per minute for outbound webhooks (default: 30). REST uses a lower default (10/min per user) when unset. See [Limits](Limits) for full details. |
| **Daily write budget** | Max write operations per day (0 = no limit). When exceeded, write abilities return 429 until the next day. Optional: **Alert email when usage reaches** a percentage (e.g. 80%) and an **Email** address to receive the alert. Which operations count is filterable via `wp_pinch_write_abilities`. See [Limits](Limits) and [Error Codes](Error-Codes). |
| **Agent ID** | Default agent to route messages to |
| **Public chat & stream limits** | **Public chat rate limit** — requests per minute per IP for unauthenticated chat (default 3). **Max concurrent SSE streams per IP** — cap on simultaneous streaming connections per IP (default 5; 0 = no limit). **Max chat response length** — character cap on gateway reply (default 200,000; 0 = no limit). See [Limits](Limits). |
| **Ability cache** | **Cache TTL for read-heavy abilities** (seconds; default 300, 0 = disabled). Caches results for list-posts, search-content, list-media, list-taxonomies. Invalidated automatically on post save/delete. Filter `wp_pinch_cacheable_abilities` can change which abilities are cacheable. |

### Webhook Settings

| Setting | Description |
|---|---|
| **Webhook Channel** | Channel name for webhook events |
| **Webhook Recipient** | Recipient for webhook events |
| **Webhook Delivery** | Delivery mode for webhooks |
| **Webhook Model** | Model for webhook-triggered processing |
| **Webhook Thinking** | Thinking level for webhook processing |
| **Webhook Timeout** | Timeout for webhook processing (seconds) |

### Chat Settings

| Setting | Description |
|---|---|
| **Chat Model** | AI model for interactive chat (e.g., `anthropic/claude-sonnet-4-5`). Empty = gateway default |
| **Chat Thinking Level** | Off / Low / Medium / High. Empty = gateway decides |
| **Chat Timeout** | Request timeout in seconds (0-600). 0 = gateway default |
| **Default Chat Placeholder** | Default placeholder text for the Pinch Chat input. Empty = block default. Can be overridden per post via Block Bindings (post meta `wp_pinch_chat_placeholder`) |
| **Session Idle Timeout** | Minutes of inactivity before a new session starts. 0 = gateway default |

### PinchDrop Settings

| Setting | Description |
|---|---|
| **Enable PinchDrop** | Enables the `/wp-pinch/v1/pinchdrop/capture` endpoint |
| **Default output types** | Default Draft Pack outputs (`post`, `product_update`, `changelog`, `social`) |
| **Auto-save generated drafts** | Creates WordPress draft posts automatically from generated outputs |
| **Allowed capture sources** | Optional comma-separated allowlist (`slack,telegram,whatsapp`) |

### Feature Flags

Toggle features on/off without code changes:

| Flag | Default | Description |
|---|---|---|
| `streaming_chat` | Off | SSE streaming for chat responses |
| `webhook_signatures` | Off | HMAC-SHA256 signed webhooks |
| `circuit_breaker` | Off | Circuit breaker for gateway calls |
| `ability_toggle` | Off | Admin UI to enable/disable individual abilities |
| `webhook_dashboard` | Off | Webhook dashboard in admin |
| `audit_search` | Off | Search and date filtering in audit log |
| `health_endpoint` | Off | Public health check endpoint |
| `public_chat` | Off | Allow unauthenticated visitors to chat |
| `slash_commands` | Off | Enable /new, /status, /compact in chat |
| `prompt_sanitizer` | On | Mitigate instruction injection in content sent to LLMs (Molt, Ghost Writer, synthesize) |
| `approval_workflow` | Off | Queue destructive abilities (delete-post, toggle-plugin, etc.) for admin approval before execution |
| `token_display` | Off | Show token usage in chat footer |
| `pinchdrop_engine` | Off | Enable PinchDrop capture-anywhere pipeline |

---

## PinchDrop Capture Contract

Signed inbound capture endpoint:

```
POST /wp-json/wp-pinch/v1/pinchdrop/capture
```

Required payload:

- `text` (string, required)
- `source` (string, channel identifier)
- `request_id` (string, idempotency key)

Optional payload:

- `author` (string)
- `options.output_types` (array: `post`, `product_update`, `changelog`, `social`)
- `options.tone` (string)
- `options.audience` (string)
- `options.save_as_draft` (boolean)

Auth model matches hook receivers:

- Bearer token (`Authorization: Bearer ...`) or `X-OpenClaw-Token`
- Optional HMAC signature (`X-WP-Pinch-Signature` + `X-WP-Pinch-Timestamp`) when webhook signatures are enabled

### Governance Tab

Configure which governance tasks run and on what schedule:

- Content Freshness
- SEO Health
- Comment Sweep
- Broken Link Detection
- Security Scanning
- Draft Necromancer (abandoned drafts worth resurrecting; requires Ghost Writer)
- Tide Report (daily digest — bundles findings into one webhook)

### Token Efficiency (LLM costs)

Abilities that send content to the gateway (synthesize, what-do-i-know, site-digest) use filters to control how much text is sent. Tune these to reduce token usage:

- **`wp_pinch_synthesize_excerpt_words`** — Excerpt word count (default 40)
- **`wp_pinch_synthesize_content_snippet_words`** — Content snippet word count (default 75)
- **`wp_pinch_synthesize_per_page_max`** — Max posts per synthesize query (default 25)

See [Hooks & Filters](Hooks-and-Filters) for details.

### Abilities Tab

When the `ability_toggle` feature flag is enabled, you can enable/disable individual abilities from the admin UI without writing code.

### Audit Tab

When the `audit_search` feature flag is enabled, the Audit tab shows **search** (text filter) and **Event** (dropdown filter by event type). Each row includes a **Details** column with context (e.g. ability name, post ID, diff) when available. Export remains capped; see [Limits](Limits).

---

## Requirements

| Requirement | Minimum | Notes |
|---|---|---|
| WordPress | 6.9+ | For the Abilities API |
| PHP | 8.1+ | For type hints and enums |
| MCP Adapter plugin | Recommended | For full MCP integration |
| Action Scheduler | Optional | Required for: recurring governance tasks (content freshness, SEO, comment sweep, broken links, security scan, draft necromancer, spaced resurfacing), webhook retry on failure, and audit log cleanup. Without it, the plugin activates and runs; those features no-op. Install from [WooCommerce](https://wordpress.org/plugins/woocommerce/) or [standalone](https://github.com/woocommerce/action-scheduler/releases). A dismissible admin notice appears on the WP Pinch settings/dashboard when Action Scheduler is missing. |

---

## Site Health

WP Pinch adds two tests to the WordPress Site Health screen:

- **Gateway Connectivity** -- Can your site reach the OpenClaw gateway?
- **Configuration Check** -- Are all required settings filled in?

Both show green checks when properly configured.

### Health & status endpoints

REST endpoints for uptime and debugging:

- **Health** (`GET /wp-pinch/v1/health`, no auth): Returns `rate_limit.limit`, `circuit.last_failure_at`, and other public fields. Use for uptime checks and load balancers.
- **Status** (`GET /wp-pinch/v1/status`, requires `manage_options`): Same rate limit and circuit info plus operational details (e.g. circuit state). Use for admin dashboards and support.

Both responses include `rate_limit` and `circuit` (including `last_failure_at`) for debugging gateway connectivity and rate limiting.
