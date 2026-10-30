# 0004 — Dev seed/revert feature in Studio Settings

**Date**: 2026-10-30
**Goal**: Turn session 0003's CLI seeder into a permanent feature: two buttons on
MyStudio → Studio Settings — Seed Dummy Data and Revert to Default (double verification).

## Changed

- **`inc/plugins/mystudio/devseed.php`** (new): `ms_devseed_seed_data()` + `ms_devseed_revert_default()`
  shared helpers (`ms_devseed_wipe_content()` keeps uid 1 + acting admin; `ms_devseed_recount_and_rebuild()`
  recomputes forum/user counters + rebuilds datacaches). Seeder guarantees the Chit-Chat poll thread
  lands every run (shuffle-then-swap fix).
- **`admin/modules/mystudio/module_meta.php`**: registered `seed_data` + `revert_default` actions
  (both highlight `settings`).
- **`admin/modules/mystudio/mystudio.php`**: requires devseed.php; new action blocks before the Manage
  fallback (`seed_data` POST-only; `revert_default` GET confirm page — checkbox + typed `RESET` + post
  key — then POST runs the revert); "Demo Data" section added to the settings page.
- **`.agent/mystudio-admin.md`**: gotchas #15 (two-place action registration), #16 (no escaping in
  `insert_query`).

## Decisions

- Dev seed file lives in `inc/plugins/mystudio/` (git-tracked), functions prefixed `ms_devseed_`.
- Revert restores "My Category"/"My Forum" and deletes users uid>1 (except acting admin) — i.e. a
  functional default MyBB content state; MyStudio plugin/settings untouched. No file deletion in
  `uploads/` (only attachment DB rows).

## Gotchas found

- **User feedback: stock ACP UI only.** First iteration used icons + inline-styled buttons; user
  rejected — dev-tool buttons now use `generate_submit_button()` / `class="button"` /
  `class="error"` with plain labels. Default: match native MyBB ACP look, no custom styling.
- MyBB ACP `Form` auto-adds hidden `my_post_key` in its constructor — POST forms built with it pass
  `verify_post_check` for free.

## Testing

- `php -l` clean on all three files. CLI harness round-trip: revert → seed → revert verified
  (2 forums / 1 user after revert; 26 users incl. banned+mods, 24 forums, 1 empty forum, ~200
  threads, ~1k posts, 1 poll after seed; poll present across 3 random runs).
- Action routing verified headlessly (`mystudio_action_handler` → `mystudio.php`, active=settings).
- Button styling fix after user report (escaped labels) — switched to raw HTML buttons; lint clean.
  ACP page render itself not HTTP-tested (needs admin login) — user verifies in browser.

## Follow-ups

- If line numbers shifted, the `mystudio-admin.md` line refs for `settings` (~2281) are stale —
  re-pin when next editing that file.
- Still open: broken Manage Extensions page (RULES.md §6); untracked `themes/default/` decision.
