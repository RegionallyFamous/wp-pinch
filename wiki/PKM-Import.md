# PKM Import (Roadmap)

WP Pinch can grow into a second brain that **captures** and **organizes** knowledge. Importing from existing PKM tools (Obsidian, Notion, etc.) is on the roadmap.

---

## Obsidian (priority)

**Goal:** Map an Obsidian vault (or a folder of markdown files) to WordPress posts while preserving structure and internal links.

**Spec:**

- **Input:** Path to a folder containing `.md` files (CLI: e.g. `wp pinch import-obsidian /path/to/vault` or an admin “Import” tool).
- **Mapping:** One markdown file → one WordPress post (draft or publish). Title from filename or first `# Heading`; body from file content.
- **Links:** Internal links `[[Note Title]]` or `[[Note Title|label]]` can be stored as-is, or optionally resolved to WordPress permalinks when a target post exists in the import set. Future: backfill links after all posts are created.
- **Metadata:** Optional YAML front matter (e.g. `tags`, `date`) mapped to post meta or taxonomies.
- **Idempotency:** Option to skip or update existing posts by a stable slug/filename key.

**Status:** Spec only. Implementation (CLI command or one-time admin importer) to follow in a later release.

---

## Notion (later)

**Goal:** Import from a Notion export (HTML or Markdown export).

**Notes:** Notion’s export format differs from Obsidian. A separate importer (or a unified “markdown/HTML import” that accepts Notion export structure) can be added after Obsidian is shipped. Document structure (blocks, nested pages) may map to posts and hierarchy (parent post / child pages).

**Status:** Documented for later; not yet scheduled.

---

## Contributing

If you want to implement Obsidian import, start with a small CLI command that reads one folder, creates one post per file, and stores `[[wikilinks]]` in content for later resolution.
