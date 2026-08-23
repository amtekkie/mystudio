# MyStudio — Agent Knowledge Base

This folder is the long-term working memory for AI agents (Claude Code) operating on this
MyBB + MyStudio codebase. It is **tracked in git** (see `.gitignore` allowlist). Keep it
accurate and up to date as the code changes.

## What's here

| File | Contents |
|------|----------|
| `RULES.md` | Working rules & conventions an agent MUST follow when editing this repo. |
| `mybb-framework.md` | MyBB 1.8 architecture: request lifecycle, globals, plugin/template/theme systems, DB schema. |
| `mystudio-plugin.md` | MyStudio plugin internals: `ms.php` lifecycle + `core.php` `MyStudio` class (sync engine, modules, options). |
| `mystudio-admin.md` | MyStudio Admin CP module (`admin/modules/mystudio/`): pages, actions, AJAX endpoints, security. |
| `mystudio-theme.md` | On-disk theme package format: `theme.json`, css/templates/js/functions/modules layout, the 5 shipped modules. |
| `sessions/` | Per-session logs. One file per working session, numbered (`0001-…`, `0002-…`). See `sessions/README.md`. |

## How to use this

- At the start of a session, read `RULES.md` and the reference doc(s) relevant to the task.
- The reference docs are the **distilled truth** about how the system works. The source code is
  still authoritative — if a doc and the code disagree, the code wins; fix the doc.
- Cite `file:line` references where possible. When you change something material, update the
  matching doc in the same change.

## Source of truth hierarchy

1. The running code (what actually executes).
2. These `.agent/*.md` docs (kept in sync with the code).
3. The top-level `README.md` (user-facing overview, less detailed).

If you find a doc out of date, update it. Don't let the knowledge base rot.