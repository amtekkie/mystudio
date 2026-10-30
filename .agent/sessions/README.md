# Session Logs

Per-session notes for continuity across Claude Code sessions. **One file per session**,
numbered `NNNN-short-slug.md` (e.g. `0001-mystudio-study.md`, `0002-fix-editor-sync.md`).

## What goes in a session log

Keep it short — this is for orientation, not a full transcript (the transcript lives in Claude's
own session storage). Aim for:

- **Date** (absolute) and a one-line goal.
- **Studied**: files/areas read or explored.
- **Changed**: files created/edited, with a one-line reason each.
- **Decisions**: non-obvious choices made, with the why.
- **Gotchas found**: anything surprising worth remembering (link the matching `.agent/*.md` doc).
- **Follow-ups**: open questions, TODOs, or "next session should…".

## Convention

- Read `.agent/RULES.md` and the relevant `.agent/*.md` reference docs at the start of a session,
  then update them if you change something material.
- Append a log here at the end of (or during) each substantive session.
- Number sequentially; don't reuse numbers. If a session is a direct continuation, you may append
  to the previous session's file under a dated "## Continued" heading instead of a new file.
- Keep entries factual. If a follow-up was later resolved, edit it to note resolution (don't
  delete it — the history is useful).

## Index

- [0001-mystudio-study.md](0001-mystudio-study.md) — Initial study of MyBB + MyStudio; created the `.agent` knowledge base.
- [0002-orientation-check.md](0002-orientation-check.md) — Knowledge base health check; flagged untracked `themes/default/`.
- [0003-dev-seed.md](0003-dev-seed.md) — Seeded the DB with forumeze.com structure: 26 users, 204 threads, 1000 posts; gotcha: `insert_query` no longer escapes.
- [0004-dev-seed-feature.md](0004-dev-seed-feature.md) — Seed Dummy Data / Revert to Default buttons in Studio Settings via `inc/plugins/mystudio/devseed.php`; double-verified revert.
- [0005-giphy-key-scrub.md](0005-giphy-key-scrub.md) — Scrubbed leaked GIPHY API key from all 18 commits (filter-branch) and force-pushed; user rotating the key.