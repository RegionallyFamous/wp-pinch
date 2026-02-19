# OpenClaw + WordPress in 5 Minutes

Get OpenClaw talking to your WordPress site with WP Pinch in a few steps. This is the page to link from any “WordPress” or “WP Pinch” integration doc in OpenClaw’s docs.

---

## 1. Install WP Pinch

**WP-CLI (fastest):**

```bash
wp plugin install https://github.com/RegionallyFamous/wp-pinch/releases/latest/download/wp-pinch.zip --activate
```

Or upload the plugin from the [Releases](https://github.com/RegionallyFamous/wp-pinch/releases) page and activate it in **Plugins**.

---

## 2. Configure connection (optional for MCP-only)

If you want **webhooks** (WordPress → OpenClaw) or **incoming hook** (OpenClaw → WordPress abilities):

1. Go to **WP Pinch** in the admin sidebar.
2. Enter your **OpenClaw Gateway URL** (e.g. `https://your-gateway.openclaw.ai`).
3. Enter your **API Token**.
4. Click **Test Connection**.

For **MCP-only** (OpenClaw calls your site’s abilities over MCP), you can skip this and only need the MCP URL in step 3.

---

## 3. Connect OpenClaw to your site

Point OpenClaw at your site’s MCP endpoint:

```bash
npx openclaw connect --mcp-url https://your-site.com/wp-json/wp-pinch/mcp
```

Replace `https://your-site.com` with your WordPress site URL. OpenClaw will discover WP Pinch’s abilities and you can start using them from your channels.

---

## 4. Try one command

In the chat you connected (e.g. Slack, Telegram, or the OpenClaw UI), try:

- **“List my last 5 posts”** — the agent will use `list-posts` (and may follow up with `get-post` for details).
- **“What do I know about [topic]?”** — uses `what-do-i-know`.
- **“Capture this: [your idea]”** — can use PinchDrop (`pinchdrop-generate` or the capture endpoint) if enabled.

If you configured the gateway URL and token, you can also trigger abilities from OpenClaw’s side via the incoming webhook (`POST …/wp-pinch/v1/hooks/receive` with `action: execute_ability`).

---

## Next steps

- [Configuration](Configuration) — webhooks, governance, rate limits, feature flags.
- [Abilities Reference](Abilities-Reference) — full list of abilities and tools.
- [OpenClaw Skill](OpenClaw-Skill) — copy this into your OpenClaw workspace so the agent knows when to use which ability.
