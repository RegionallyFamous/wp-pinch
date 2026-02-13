# Ghost Writer

Ghost Writer learns each author's writing voice from their published posts and can complete abandoned drafts in that voice. The AI writes like *you* — tone, vocabulary, sentence structure, and quirks — so resurrected drafts stay on-brand.

---

## What It Does

- **Voice profile** — Analyzes an author's published posts and builds a per-author style profile (stored in user meta). Analyzing another author's voice requires `edit_others_posts`; by default you can only analyze your own.
- **Draft completion** — Completes an abandoned draft in the original author's voice via the `ghostwrite` ability. Does not auto-publish; the draft stays a draft unless you choose to apply and then publish.
- **Draft Necromancer** — A weekly governance task that scans for abandoned drafts worth resurrecting (age and completion %). Findings are delivered via webhook like other governance results.

---

## Feature Flag and Settings

- **Feature flag:** `ghost_writer` (disabled by default). Enable in **WP Pinch > Features**.
- **Setting:** `wp_pinch_ghost_writer_threshold` — minimum age in days for a draft to be considered "abandoned" (default **30**). Configured under **WP Pinch > Governance**.

---

## Abilities

All three abilities are gated by the `ghost_writer` feature flag.

| Ability | Purpose | Inputs | Outputs |
|--------|---------|--------|--------|
| **`analyze-voice`** | Build or refresh a per-author writing voice profile from their published posts. | `user_id` (optional, default: current user) | `user_id`, `post_count_analyzed`, `voice`, `metrics` |
| **`list-abandoned-drafts`** | List drafts ranked by resurrection potential (freshness + completion %). | `days` (optional, min age; 0 = use global threshold), `user_id` (optional, 0 = all authors) | `count`, `drafts` (array with id, title, author, age, completion, score, etc.) |
| **`ghostwrite`** | Return AI-completed content for a draft in the author's voice. Optionally save to the draft. | `post_id` (required), `apply` (optional, default false — if true, writes content to the draft) | Completed content and/or updated draft info; errors if no voice profile or permission denied |

Capability: `edit_posts`. Per-post `edit_post` is enforced for `ghostwrite`. Voice profiles are stored in user meta and are not exposed via the general REST API.

---

## REST Endpoint

`POST /wp-json/wp-pinch/v1/ghostwrite`

Powers the `/ghostwrite` slash command in the Pinch Chat block. Supports:

- **List** — No body or empty body: returns a list of abandoned drafts (and a hint to use `/ghostwrite [post_id]` to resurrect).
- **Resurrect** — Body with `post_id` (and optionally `apply`): runs Ghost Writer on that draft and returns the result; when invoked from the slash command, `apply` is typically `true` so the draft is updated.

Uses the same authentication as other WP Pinch REST routes (Bearer/OpenClaw token, session for chat).

---

## Slash Command

In the Pinch Chat block:

- **`/ghostwrite`** — Lists your abandoned drafts (and others' if you have `edit_others_posts`). Reply suggests using `/ghostwrite [post_id]` to resurrect one.
- **`/ghostwrite 123`** — Resurrects draft post ID 123 in the author's voice and applies the completed content to the draft.

---

## Back to the Reference

Ghost Writer is one of the **tools** documented in the [Abilities Reference](Abilities-Reference#tools-pinchdrop--ghost-writer). Core abilities (content, media, users, etc.) are listed there under [Core Abilities](Abilities-Reference#core-abilities).
