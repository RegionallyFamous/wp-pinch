# WordPress + OpenClaw: Second Brain Vision

WP Pinch positions WordPress as a **durable knowledge store** and OpenClaw as the **conversational, multi-channel brain**. This page documents how the plugin maps to the second-brain workflow (CODE/PARA) and what exists today.

**References:** Forte Labs (CODE, PARA, Progressive Summarization, Intermediate Packets), OpenClaw (memory-core, sessions, tool use). See the [vision plan](https://github.com/RegionallyFamous/wp-pinch/blob/main/.cursor/plans/) for the full roadmap.

---

## Why WordPress + OpenClaw?

- **You already publish there** — your blog or site is the canonical store. No sync, no export.
- **Self-hosted, you own it** — WordPress + OpenClaw run on your hardware.
- **Multi-channel access** — capture and query from WhatsApp, Slack, Telegram, Discord.
- **Familiar CMS** — taxonomies, revisions, REST API. Extensible without learning a new system.
- **OpenClaw is agent-native** — tool use, sessions, memory. WP Pinch exposes WordPress as MCP abilities.

**Audience:** Solo bloggers, small teams, or anyone who wants their WordPress site to double as a personal/small-team second brain.

---

## CODE (Workflow)

**CODE** (Tiago Forte): **Capture** → **Organize** → **Distill** → **Express**.

| Pillar | What it means |
|--------|----------------|
| **Capture** | Collect what resonates; low friction. |
| **Organize** | Structure with PARA or similar (Projects, Areas, Resources, Archives). |
| **Distill** | Progressive summarization; extract insights. Forte: distill is personal; AI can assist, not replace judgment. |
| **Express** | Create outputs. Intermediate Packets = small, reusable pieces for larger projects. |

**PARA** (structure): Projects (short-term), Areas (ongoing), Resources (learning), Archives (inactive). WordPress categories and tags can map to PARA.

---

## How WP Pinch Maps to CODE

| CODE pillar | WP Pinch today | Gap / nuance |
|-------------|-----------------|--------------|
| **Capture** | [PinchDrop](PinchDrop) (ideas → Draft Packs from channels); **Quick Drop** (`save_as_note: true`) = minimal post (title + body, no AI expansion). | Quick Drop makes capture channel-accessible. WordPress Quick Draft is admin-only. |
| **Organize** | Posts, pages, taxonomies, meta; [search-content](Abilities-Reference), [export-data](Abilities-Reference), [recent-activity](Abilities-Reference). | PARA via categories. [Echo Net (related-posts)](Abilities-Reference) adds backlinks + shared-taxonomy discovery. |
| **Distill** | [Molt](Abilities-Reference) (post → social, FAQ, summary, etc.); [Ghost Writer](Ghost-Writer) (finish drafts); Governance (stale, SEO, drafts). | Molt = first pass; human refinement still valuable. Progressive Summarization (bold/highlight layers) is manual. |
| **Express** | Create/update abilities; Molt outputs = **Intermediate Packets**; [Pinch Chat](Chat-Block); multi-channel. [Weave (synthesize)](Abilities-Reference): search → payload for LLM synthesis (first draft; human refines). | Strong fit. |

**Agent context:** [Memory Bait (site-digest)](Abilities-Reference) gives OpenClaw memory-core a compact snapshot (recent posts, key terms). [Tide Report](Configuration) bundles governance findings into one daily webhook so the agent knows "what needs attention."

---

## Architecture (Data Flow)

```
Channels (WhatsApp, Telegram, Slack, …) → OpenClaw → MCP/abilities → WordPress
WordPress (publish, comment, …) → Webhook Dispatcher → OpenClaw
```

WP Pinch is the bridge: abilities read/write the store; webhooks push events to the agent; PinchDrop and governance feed the loop.

---

## What We're Not Building

- **Obsidian replacement** — No local vaults, graph UI, or `[[wiki]]` links. WordPress is the store.
- **New note-taking app** — Capture stays within WordPress (posts/notes).
- **Full automation of Distill** — AI assists; human has final say.

---

## See Also

- [Abilities Reference](Abilities-Reference) — All abilities, including Memory Bait, Echo Net, Weave
- [PinchDrop](PinchDrop) — Capture and Quick Drop
- [Ghost Writer](Ghost-Writer) — Voice and draft completion
- [Configuration](Configuration) — Governance tasks (including Tide Report)
