# 0003 — Dev content seeding (forumeze.com structure)

**Date**: 2026-10-30
**Goal**: Populate the local MyBB DB with the forumeze.com forum structure, random users across
usergroups, and threads/replies for theme development and testing.

## Studied

- Fetched https://forumeze.com index structure (5 categories, 15 forums + 4 subforums).
- `install/resources/mysql_db_tables.php` — schemas for forums/threads/posts/polls/announcements/banned/moderators.
- `inc/db_mysqli.php` — `insert_query`/`update_query` **do NOT escape string values** in this MyBB
  version (`quote_val()` only wraps in quotes). Callers must `$db->escape_string()` first — this
  differs from older MyBB releases. Gotcha worth remembering (see below).
- `inc/class_datacache.php` — rebuild helpers (`update_forums`, `update_stats`, …).
- `inc/datahandlers/user.php` — used `UserDataHandler('insert')` for users.

## Changed

- DB only. Wrote and ran `C:\laragon\tmp\seed_forumeze.php` (CLI, outside webroot; bootstraps
  `inc/init.php`). Rerunnable: wipes forums/threads/posts/polls/announcements/moderators/banned
  and users uid>1, then reseeds (mt_srand is fixed → deterministic-ish content).
- Pre-seed DB backup: `C:\laragon\tmp\forumeze_preseed_backup.sql`.
- Fixed category `disporder` 1-5 post-run via SQL.

## Result

- 26 seeded users (pwd `TestPass123!`): 1 admin (NimbleNora), 2 super mods, 2 mods
  (PatchPat/KernelKate, assigned forums via `moderators` rows), 2 awaiting activation,
  1 banned (SpammySteve + `banned` row), 18 registered. Admin `amtekkie` (uid 1) kept.
- 24 forums/categories matching forumeze.com (incl. Themes/Plugins subforums under MyBB & WP).
- "Collaboration & Services" left **empty** on purpose.
- 204 threads / 1000 posts; 23 threads with no replies; ~20% of replies use `[quote]`,
  some posts use `[code]`/`[list]`; 1 poll thread; 2 global announcements.
- Counters + lastpost columns recomputed; caches rebuilt; verified 200 renders on index,
  forumdisplay, showthread.

## Gotchas found

- **`insert_query`/`update_query` don't escape** (db_mysqli.php:966 `quote_val` — unlike older
  MyBB). Any script using `$db->insert_query` with strings must escape first.
- PHP CLI at `/c/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php.exe`; mysql client at
  `/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe`; site served at `http://forumeze.local`.
- Board URL setting: `http://forumeze.local` (cookie domain `.forumeze.local`).

## Follow-ups

- `themes/default/` remains untracked (see session 0002) — still pending a decision.
- Still open from 0001: broken Manage Extensions page (`getMiniPluginStates` undefined, RULES.md §6).
