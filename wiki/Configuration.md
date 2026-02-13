# Configuration

## Installation

### The Quick Way (WP-CLI)

```bash
wp plugin install https://github.com/RegionallyFamous/wp-pinch/releases/latest/download/wp-pinch.zip --activate
```

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

> **Note:** If you install from source, you must run `npm run build` to generate the `build/` directory. Without it, admin assets will 404.

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

OpenClaw will discover available abilities and begin routing messages from your configured channels (WhatsApp, Telegram, Slack, Discord, etc.) to your WordPress site.

You can also add your Gateway URL directly in the WP Pinch settings for webhook-based integration -- ideal for sites that want real-time push notifications when content changes.

---

## Admin Settings

### Connection Tab

| Setting | Description |
|---|---|
| **Gateway URL** | Your OpenClaw gateway endpoint |
| **API Token** | Authentication token for the gateway |
| **Rate Limit** | Maximum requests per minute (default: 30) |
| **Agent ID** | Default agent to route messages to |

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
| **Session Idle Timeout** | Minutes of inactivity before a new session starts. 0 = gateway default |

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
| `token_display` | Off | Show token usage in chat footer |

### Governance Tab

Configure which governance tasks run and on what schedule:

- Content Freshness
- SEO Health
- Comment Sweep
- Broken Link Detection
- Security Scanning

### Abilities Tab

When the `ability_toggle` feature flag is enabled, you can enable/disable individual abilities from the admin UI without writing code.

---

## Requirements

| Requirement | Minimum | Notes |
|---|---|---|
| WordPress | 6.9+ | For the Abilities API |
| PHP | 8.1+ | For type hints and enums |
| MCP Adapter plugin | Recommended | For full MCP integration |
| Action Scheduler | Required | Ships with WooCommerce, or install standalone |

---

## Site Health

WP Pinch adds two tests to the WordPress Site Health screen:

- **Gateway Connectivity** -- Can your site reach the OpenClaw gateway?
- **Configuration Check** -- Are all required settings filled in?

Both show green checks when properly configured.
