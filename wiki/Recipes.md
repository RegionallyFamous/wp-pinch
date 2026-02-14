# Recipes: Outcome-First Workflows

You want to *do* something with your site from chat or from OpenClaw — not just "have abilities." These recipes are short, outcome-first flows. Each one uses WP Pinch abilities in a specific order so you can copy, paste, and adapt.

**New here?** [Configuration](Configuration) first (connect OpenClaw), then come back and try a recipe. For the full ability list, see [Abilities Reference](Abilities-Reference). For when/how the agent should use WP Pinch, see [OpenClaw Skill](OpenClaw-Skill).

---

## 1. Publish a post from chat

**Outcome:** You say in WhatsApp/Slack/Telegram: "Publish this as a post: [title and body]." A draft or published post appears in WordPress.

**Flow:**

1. Your assistant receives your message.
2. Assistant calls `create-post` (or `wp-pinch/create-post` via MCP) with `title`, `content`, and optionally `status` (`draft` or `publish`).
3. Post is created; assistant can confirm with the new post ID or link.

**Example (what you say):**  
*"Publish a post titled 'Q3 recap' with the content: [paste]. Make it a draft first."*

**Abilities:** `create-post`. Optional: `update-post` if you want to revise, then publish.

### 1b. Draft-first and preview-approve

**Outcome:** The AI creates or updates a post as a **draft**; you get a preview link to review, then you (or the assistant) approve to publish.

**Flow:**

1. Assistant calls `create-post` or `update-post`. WP Pinch saves the post as draft and returns **`preview_url`** and **`ai_generated: true`**.
2. You open `preview_url` in a browser to review the draft.
3. When ready, call **`POST /wp-json/wp-pinch/v1/preview-approve`** with body `{ "post_id": 123 }` (same auth as other REST). The post becomes published and the response includes the live URL.

**Example (what you say):**  
*"Draft a post titled 'Launch update' with this body. Send me the preview link; I'll tell you when to publish."*

**Abilities / endpoint:** `create-post` or `update-post` (draft); then `POST /wp-pinch/v1/preview-approve` with `post_id`. See [Abilities Reference](Abilities-Reference#draft-first-and-preview).

---

## 2. Turn one post into social + FAQ + meta (Molt)

**Outcome:** One blog post becomes a Twitter thread, LinkedIn post, meta description, FAQ block, and more — without leaving chat.

**Flow:**

1. You say: "Turn post 123 into social and meta" (or "Molt 123" if you use the slash command).
2. Assistant calls `wp-pinch/molt` (or `molt`) with `post_id: 123` and optional `output_types` (e.g. `twitter`, `linkedin`, `meta_description`, `faq`).
3. You get back the formatted snippets; you can copy-paste or ask the assistant to post them.

**Example (what you say):**  
*"Run Molt on post 456 and give me the Twitter and LinkedIn versions."*

**Abilities:** `wp-pinch/molt` (or `/molt &lt;post_id&gt;` in Pinch Chat). See [Molt](Molt) for all nine output types.

---

## 3. Capture an idea from Slack/WhatsApp → draft pack (PinchDrop)

**Outcome:** You paste a rough idea in your channel; a few minutes later you have a Draft Pack in WordPress (blog post, social snippets, etc.) or a minimal note (Quick Drop).

**Flow:**

1. You send the idea to your OpenClaw-connected channel (e.g. Slack). Optionally use the capture endpoint: `POST /wp-pinch/v1/pinchdrop/capture` with signed payload (see [PinchDrop](PinchDrop)).
2. OpenClaw (or your agent) calls `pinchdrop-generate` with the idea text. Optionally set `save_as_note: true` for Quick Drop (minimal post, no expansion).
3. WP Pinch returns structured drafts (blog, product update, changelog, social). The agent can then create draft posts via `create-post` with that content.

**Example (what you say):**  
*"We're launching the new pricing page next week — turn this into a draft pack for the blog and social."*

**Abilities:** `pinchdrop-generate`. Capture can be triggered by the agent when you send a message, or by the Web Clipper / REST capture. [PinchDrop](PinchDrop) has full details.

---

## 4. "What did I write about X?" (What do I know)

**Outcome:** You ask in natural language; you get an answer plus source post IDs.

**Flow:**

1. You ask: "What have I written about pricing?" (or "What do I know about onboarding?")
2. Assistant calls `what-do-i-know` with `query: "pricing"` (or the full question).
3. WP Pinch searches your content and (if the gateway is configured) can synthesize an answer. You get back a coherent reply and the relevant post IDs so you can open or cite them.

**Example (what you say):**  
*"What do I know about refunds?"*

**Abilities:** `what-do-i-know` with `query`. [Abilities Reference](Abilities-Reference#high-leverage-tools).

---

## 5. Save a webpage to WordPress (Web Clipper)

**Outcome:** You're on a random article or snippet; you hit a bookmarklet (or use an extension). The selection or URL is saved as a draft post. No need to open chat or wp-admin.

**Flow:**

1. You install the Web Clipper (bookmarklet or extension) and set your site URL + capture token (in WP Pinch settings).
2. On any webpage, you select text and click the bookmarklet (or use the extension). The client sends `POST /wp-pinch/v1/capture` with optional `url`, `title`, and body.
3. WP Pinch creates a draft post. Optionally, webhooks notify OpenClaw so your assistant can mention it in the next conversation.

**No chat required.** See [PinchDrop](PinchDrop#web-clipper) and [Security](Security) for token setup.

---

## 6. "What needs attention?" (Tide Report / governance)

**Outcome:** Once a day (or on schedule), your assistant gets a digest: stale posts, SEO gaps, comment queue, broken links, abandoned drafts. You see it in your channel.

**Flow:**

1. In WP Pinch settings, enable **Governance** tasks (content freshness, SEO, comments, broken links, draft necromancer, spaced resurfacing) and the **Tide Report** (daily bundle).
2. Webhook delivery is set to your OpenClaw channel. When the Tide Report runs, it sends one payload with findings to OpenClaw.
3. Your assistant (or a dedicated notification) shows you "Here's what needs attention" — e.g. "3 posts older than 90 days, 2 drafts untouched for 30 days."

**Example (what you say):**  
*"What did the Tide Report say today?"* (if your agent stores or reads the last webhook.)

**Setup:** [Configuration](Configuration) → Governance tab. Enable tasks and Tide Report; ensure webhooks are delivered to OpenClaw.

---

## 7. Resurrect an abandoned draft in your voice (Ghost Writer)

**Outcome:** You have half-written posts. You ask the assistant to list them, then "finish post 789 in my voice." You get back completed content (or a suggestion) that matches your writing style.

**Flow:**

1. Assistant calls `list-abandoned-drafts` to get drafts ranked by "resurrection potential."
2. You pick one (e.g. post ID 789). Assistant calls `ghostwrite` with `post_id: 789`.
3. WP Pinch (and the gateway, if used for synthesis) returns completed content. You can review and then `update-post` to replace the content or add it as a block.

**Example (what you say):**  
*"List my abandoned drafts" then "Finish draft 789 in my voice."*

**Abilities:** `list-abandoned-drafts`, `ghostwrite`. See [Ghost Writer](Ghost-Writer). Voice profiles are built from your published posts (`analyze-voice`).

---

## 8. Run multiple abilities in one request

**Outcome:** Call several abilities in a single webhook request (e.g. list posts and list media) and get both result sets back in one round trip.

**Flow:**

1. Send a request to `POST /wp-pinch/v1/hooks/receive` with `action: execute_batch` and `batch: [ { "ability": "...", "params": { ... } }, ... ]`.
2. WP Pinch runs each ability in order and returns results in the same order. Max 10 items per batch; see [Limits](Limits).
3. Use the response to feed your agent or dashboard with multiple data sets at once.

**Example:** One request that runs `list-posts` (e.g. `per_page: 5`) and `list-media` (e.g. `per_page: 5`) returns both result sets in a single `results` array.

**Abilities:** Any ability can be included in the batch. Incoming webhook must be authenticated (Bearer token or HMAC). See [Limits](Limits) for the 10-item batch cap.

---

## See also

- [Abilities Reference](Abilities-Reference) — Every ability and parameter
- [OpenClaw Skill](OpenClaw-Skill) — When and how the agent should use WP Pinch
- [Configuration](Configuration) — Connect OpenClaw, webhooks, governance
- [Integration and Value](Integration-and-Value) — Strategy: integration vs. value and what to build next
