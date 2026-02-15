# Release Procedure

A rock-solid checklist to cut a WP Pinch release. Nothing ships until every step passes.

---

## Prerequisites

- [ ] All changes merged to `main`
- [ ] Working directory clean (`git status`)
- [ ] Docker running (for `make wp-env-start` and tests)
- [ ] WP-CLI installed (optional; for `make i18n` — falls back to wp-env if not present)

---

## 1. Run the full gate

Nothing ships if the gate fails. Run:

```bash
make wp-env-start          # One-time: start WordPress + DB in Docker
make release-check-full    # Lint + PHPStan + PHPUnit (300+ tests)
```

- **release-check-full** runs: `make build` → `make check` → `make test-wp-env`
- If any step fails, fix it before continuing.

---

## 2. Version bump

- [ ] Bump version in **`wp-pinch.php`** (`Version:` header)
- [ ] Bump version in **`package.json`** (`version` field)
- [ ] Bump **`readme.txt`**:
  - `Stable tag: X.Y.Z`
  - Add `== Changelog ==` section for this version
  - Add `Upgrade notice` if relevant

---

## 3. Changelog

- [ ] Edit **`CHANGELOG.md`**:
  - Move entries from `[Unreleased]` into `[X.Y.Z] - YYYY-MM-DD`
  - Use sections: `### Added`, `### Changed`, `### Fixed`, `### Removed`
  - Clear `[Unreleased]` for the next cycle

---

## 4. Documentation

- [ ] **`wiki/`** — Ensure new features are documented (Configuration, Abilities-Reference, etc.)
- [ ] **`README.md`** — Update feature list, ability count, or version if needed
- [ ] **`AGENTS.md`** — Update if architecture or extension points changed

---

## 5. Create the ZIP

```bash
make release-prep    # Full gate + i18n + zip
# or, if you already ran release-check-full:
make zip             # i18n + zip-dist
```

- Produces **`wp-pinch-X.Y.Z.zip`** in the project root
- Verify: unzip and confirm `build/` and `vendor/` exist

---

## 6. Tag and push

```bash
git add -A
git status
git commit -m "Release X.Y.Z"
git tag -a vX.Y.Z -m "Release X.Y.Z"
git push origin main
git push origin vX.Y.Z
```

---

## 7. GitHub release

1. Go to **Releases** → **Draft a new release**
2. Choose tag **`vX.Y.Z`**
3. Title: **Release X.Y.Z**
4. Description: Paste the `[X.Y.Z]` changelog from `CHANGELOG.md`
5. Attach **`wp-pinch-X.Y.Z.zip`**
6. (Optional) Attach **`wp-pinch.zip`** (copy of same file) for `.../releases/latest/download/wp-pinch.zip` compatibility
7. Publish

---

## Quick reference

| Command | What it does |
|---------|--------------|
| `make release-check` | Lint + PHPStan + build (no tests) |
| `make release-check-full` | Lint + PHPStan + PHPUnit (requires wp-env) |
| `make release-prep` | Full gate + i18n + zip |
| `make zip` | i18n + zip only |

---

## Troubleshooting

- **`make test-wp-env` fails** — Ensure `make wp-env-start` ran and Docker is up.
- **`make zip` fails** — Ensure `make build` ran (or `make release-check-full`). Check `build/` exists.
- **i18n fails** — WP-CLI or `npx wp-env` required. `make i18n` uses whichever is available.
- **PHPUnit DB error** — `make test` uses local MySQL; `make test-wp-env` uses Docker. Prefer wp-env for releases.
