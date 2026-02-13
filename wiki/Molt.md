# Molt (Content Repackager)

*Lobsters molt to grow; your post sheds one form and emerges in many.*

Molt takes a single WordPress post and repackages it into multiple output formats. One blog post → social snippets, email snippet, FAQ block, thread, summary, meta description, pull quote, key takeaways, and CTA variants. All in one call.

---

## Output Formats

| Format | Description |
|--------|-------------|
| **social** | Twitter (280 chars), LinkedIn (~3000 chars) |
| **email_snippet** | 2–3 paragraphs for email |
| **faq_block** | Array of `{ question, answer }` |
| **thread** | Twitter thread (array of tweets) |
| **summary** | 2–3 sentences |
| **meta_description** | 155 characters |
| **pull_quote** | Single compelling sentence |
| **key_takeaways** | 3–5 bullet points |
| **cta_variants** | 2–3 call-to-action options |

---

## How to Use

### Slash command (chat)

Type `/molt 123` in the Pinch Chat block. Replace `123` with the post ID. You get all formats by default.

### REST API

```
POST /wp-json/wp-pinch/v1/molt
Content-Type: application/json

{
  "post_id": 123,
  "output_types": ["social", "faq_block", "summary"]  // optional; omit for all
}
```

Returns `{ output: {...}, reply: "..." }` where `reply` is formatted for chat display.

### Ability (MCP)

The `wp-pinch/molt` ability accepts `post_id` (required) and `output_types` (optional array). Call it via OpenClaw or any MCP client.

---

## Enabling Molt

Molt is gated by the `molt` feature flag (disabled by default).

1. Go to **WP Pinch > Features**
2. Enable **Molt**
3. Save

The `/molt` slash command and REST endpoint become available. The ability is discoverable via MCP.

---

## Permissions

- **Capability:** `edit_posts`
- **Per-post:** User must be able to read the post (`current_user_can('read_post', $post_id)`)

---

## How It Works

Molt sends the post title and content (truncated to ~8000 chars) to the OpenClaw gateway with a JSON prompt. The AI returns structured output; WP Pinch parses, sanitizes, and formats it for chat or API response. Uses the same circuit breaker and audit logging as Ghost Writer.

---

## Filters

- **`wp_pinch_molt_output_types`** — Modify the default list of format keys returned by `Molt::get_default_output_types()`.

---

## See Also

- [Abilities Reference](Abilities-Reference) — Full ability parameters
- [Chat Block](Chat-Block) — Slash commands
- [Second Brain Vision](Second-Brain-Vision) — Molt as Intermediate Packets in the Express pillar
