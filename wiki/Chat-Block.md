# Chat Block

The Pinch Chat block is a Gutenberg block built with the WordPress Interactivity API. It drops a reactive, accessible chat interface into any page or post — letting visitors talk to your AI agent directly on your site. It's like giving your website a little chat window with claws.

---

## Features

### SSE Streaming

Real-time character-by-character message delivery via Server-Sent Events. As the AI generates its response, text appears progressively with an animated cursor indicator. Watching the lobster type is oddly satisfying. Falls back to standard request/response if streaming is disabled or fails.

Enable via **WP Pinch > Features > Streaming Chat**.

### Public Chat Mode

Let unauthenticated visitors chat with your AI agent. Public chat uses a separate `/chat/public` endpoint with its own rate limiting and session isolation. Each browser gets a unique session key generated client-side via `crypto.randomUUID()`.

Enable via **WP Pinch > Features > Public Chat**.

### Per-Block Agent Override

Each Pinch Chat block can target a different OpenClaw agent. Set the **Agent ID** in the block's sidebar settings to override the global default. This lets you have a sales assistant on one page and a support agent on another.

**Block Bindings (WordPress 6.5+):** You can also bind `agentId` or `placeholder` to post meta or site options via the Block Bindings API. See [Hooks & Filters](Hooks-and-Filters#block-bindings-pinch-chat) for sources (`core/post-meta`, `wp-pinch/agent-id`, `wp-pinch/chat-placeholder`).

### Slash Commands

Power-user commands typed directly in the chat input:

| Command | Action |
|---|---|
| `/new` or `/reset` | Reset the conversation session |
| `/status` | Show plugin version, gateway status, circuit breaker state |
| `/compact` | Send a compaction request to the gateway |
| `/ghostwrite` | List abandoned drafts (Ghost Writer). Requires `ghost_writer` feature flag |
| `/ghostwrite 123` | Resurrect draft #123 in the author's voice |
| `/molt 123` | Repackage post #123 into social, FAQ, summary, and more formats. Requires `molt` feature flag |

Enable via **WP Pinch > Features > Slash Commands**. Ghost Writer and Molt require their respective feature flags.

### Message Feedback

Thumbs up/down buttons appear on hover for agent messages. Ratings are stored locally per session.

### Token Usage Display

After each response, the footer shows how many tokens were consumed (parsed from the `X-Token-Usage` response header).

Enable via **WP Pinch > Features > Token Display**.

### Markdown Rendering

Agent replies support bold, italic, inline code, code blocks, and links.

### Session Persistence

Chat history is stored in `sessionStorage`, scoped per block instance (using the stable `blockId` attribute). Multiple chat blocks on one page maintain separate conversations — like different tide pools. Sessions survive page reloads within the same browser tab.

### Fetch Retry with Backoff

Failed requests are retried with exponential backoff. The nonce is auto-refreshed on 403 errors (stale tab recovery).

---

## Accessibility

The Pinch Chat block targets WCAG 2.1 AA compliance:

- **ARIA labels** — Buttons (Send, New conversation, Copy, Clear, Scroll to bottom) have `aria-label`. The message list uses `role="log"` and `aria-live="polite"` for live region announcements.
- **Screen reader announcements** via `wp.a11y.speak` for new messages and errors
- **`prefers-reduced-motion`** -- all animations (typing indicator, streaming cursor, transitions) are disabled when the user prefers reduced motion
- **`forced-colors` (Windows High Contrast Mode)** -- uses system color keywords for borders, focus rings, and interactive elements
- **Dark mode** -- full coverage of every UI element, including streaming indicators, feedback buttons, and token display
- **Keyboard shortcuts** -- Escape to clear input
- **Focus management** -- input field is auto-focused after sending a message
- **Character counter** -- 4,000 character limit with visual warnings at thresholds
- **Mobile responsive** -- touch-friendly targets, responsive layout

---

## Block Settings

In the Gutenberg editor sidebar:

| Setting | Description |
|---|---|
| **Placeholder** | Custom placeholder text for the input field |
| **Show Header** | Toggle the chat header bar |
| **Max Height** | CSS height for the message area (e.g., `400px`, `50vh`) |
| **Public Mode** | Allow unauthenticated visitors to chat |
| **Agent ID** | Override the global agent for this specific block |

---

## Admin Settings

These settings affect all chat blocks site-wide. Configure them at **WP Pinch > Connection > Chat Settings**:

| Setting | Description |
|---|---|
| **Chat Model** | AI model to use (e.g., `anthropic/claude-sonnet-4-5`). Empty = gateway default |
| **Chat Thinking Level** | Off / Low / Medium / High. Empty = gateway decides |
| **Chat Timeout** | Request timeout in seconds (0-600). 0 = gateway default |
| **Session Idle Timeout** | Minutes of inactivity before a new session starts. 0 = gateway default |

---

## How It Works

1. User types a message in the chat input
2. The block POSTs to `/wp-pinch/v1/chat` (or `/chat/stream` for SSE, or `/chat/public` for anonymous users)
3. The REST controller forwards the message to the OpenClaw gateway
4. The gateway processes the message and returns a response (or streams it)
5. The block displays the response with Markdown rendering
6. The conversation is saved to `sessionStorage`

All communication is authenticated via WordPress nonces (or session key validation for public chat). Rate limiting, circuit breaker, and retry logic protect both sides.
