# 0005 — GIPHY API key scrubbed from git history

**Date**: 2026-10-30
**Goal**: Remove the GIPHY API key (committed in `037fc13`, present through HEAD) from git history.

## Facts

- Key lived only in `themes/mystudio-default/functions/modules/ms-editor-extras/default.json`
  (shipped saved-option values; surfaced in Module Settings + frontend GIF search).
- Present in all 18 commits from `037fc13` onward; remote: `github.com/amtekkie/mystudio` (public).
- User removed it via Module Settings UI first (rewrites `default.json`), then asked for history scrub.

## Actions

1. Stashed all uncommitted work (`-u`) incl. devseed feature + docs.
2. `git branch backup/pre-scrub` → rollback point at `7ce5450`.
3. `git filter-branch --tree-filter` sed-replaced the key with empty string across main
   (new HEAD `21847ff`; all SHAs changed).
4. `git push --force-with-lease origin main` — done.
5. `git stash pop` — all pending work restored; key removal baked into history (no lingering diff).
6. Deleted `refs/original/*`, expired reflog, `gc --prune=now`. Verified: key in 0 commits
   (excluding the intentional `backup/pre-scrub` branch).

## Still exposed until user acts

- **`origin/Module-Library-Feature` branch** still on GitHub with the key — user to delete it
  (`git push origin --delete Module-Library-Feature` or GitHub UI); it is fully merged (PR #1).
- **PR #1 refs** (`refs/pull/1/*`) and cached commit pages keep the key until GitHub GC —
  optionally contact GitHub support to purge.
- **User is rotating the key** in the GIPHY dashboard regardless (the real fix).

## Follow-ups

- `backup/pre-scrub` still contains the key locally — delete with
  `git branch -D backup/pre-scrub` once GitHub looks right, then gc again.
