# Release 2.4.0 — Prep Checklist

Release prep is complete. Use this checklist to cut the release.

## Done

- [x] All linting (PHP, JS, CSS) — passing
- [x] Version bumped to **2.4.0** in `wp-pinch.php`, `package.json`
- [x] `CHANGELOG.md` — [2.4.0] added with Quick Drop, Memory Bait, Tide Report, Echo Net, Weave
- [x] `readme.txt` — Stable tag, changelog, upgrade notice, governance count (seven tasks)
- [x] `README.md` — Seven governance tasks, Quick Drop, Memory Bait / Echo Net / Weave in Tools
- [x] `wiki/Abilities-Reference.md` — 38 core abilities, Analytics row updated, Quick Drop + Context & discovery section
- [x] `npm run build` — assets built successfully

## You do

1. **Create distributable ZIP**  
   - With WP-CLI installed: `make zip` (creates `dist/wp-pinch-2.4.0.zip`)  
   - Without: ensure `build/` is committed or run `npm run build` before zipping; zip the plugin directory (exclude `node_modules`, `vendor`, `.git`, `.github`, tests, dev config).

2. **Tag and push**  
   ```bash
   git add -A && git status
   git commit -m "Release 2.4.0 — Quick Drop, Memory Bait, Tide Report, Echo Net, Weave"
   git tag -a v2.4.0 -m "Release 2.4.0"
   git push origin main && git push origin v2.4.0
   ```

3. **GitHub release**  
   - Draft a new release for tag `v2.4.0`.  
   - Paste the [2.4.0] changelog from `CHANGELOG.md`.  
   - Attach `wp-pinch-2.4.0.zip` (and optionally `wp-pinch.zip` for “latest” URLs).
