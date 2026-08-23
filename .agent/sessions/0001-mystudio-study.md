# 0001 — MyBB + MyStudio initial study & .agent knowledge base setup

**Date**: 2026-08-23
**Goal**: Study the MyStudio plugin and MyBB forum software, then create a `.agent/` folder with
rules and reference docs covering MyBB, the MyStudio plugin, and its theme structure. Set up the
folder to also store future sessions and context.

## Studied

- **Repo overview**: top-level `README.md` (MyStudio 2.1.0 — modular theme/extension manager for
  MyBB 1.8.8), `.gitignore` (allowlist-only: blocks `*`, allowlists MyStudio plugin/theme paths).
- **MyBB framework** (host software) — read directly:
  - `inc/init.php` (bootstrap, globals `$mybb,$db,$templates,$cache,$plugins,$lang`, URL constants).
  - `inc/class_plugins.php` (`add_hook`/`run_hooks` — by-value vs by-reference distinction).
  - `inc/class_templates.php` (`cache`/`get`/`render`; `sid` semantics -2 master/-1 global/>0 set).
  - `install/resources/mysql_db_tables.php` (schema: `themes`,`themestylesheets`,`templates`,
    `templatesets`,`templategroups`).
  - `admin/inc/functions_themes.php` (`import_theme_xml` — the MyBB function MyStudio's sync calls).
  - MyBB docs online: plugin basics (`_info`, lifecycle, hooks, settings, `optionscode` types).
- **MyStudio plugin** — studied via parallel subagents (general-purpose), reported back:
  - `inc/plugins/ms.php` (614 lines) + `inc/plugins/mystudio/core.php` (~1941 lines).
  - `admin/modules/mystudio/module_meta.php` + `admin/modules/mystudio/mystudio.php` (~134KB) +
    `jscripts/mystudio/editor.js` + `pagebuilder.js`.
  - `themes/mystudio-default/` full structure (theme.json, css, templates, js, functions, 5 modules,
    vendor, lang).

## Changed

Created the `.agent/` knowledge base (tracked in git — added allowlist entries to `.gitignore`):
- `.agent/README.md` — index + how to use the knowledge base.
- `.agent/RULES.md` — 12 working rules (disk=truth, gid=0 settings, no plugin tables,
  single-file sync can't create templates, SID stability, modules present=enabled, path-traversal
  hardening, admin-dir dependency, MyBB conventions, keep docs in sync, session logging, git discipline).
- `.agent/mybb-framework.md` — MyBB 1.8 architecture: lifecycle, bootstrap, plugin/template/theme
  systems, DB schema, hooks, security.
- `.agent/mystudio-plugin.md` — `ms.php` lifecycle + `MyStudio` class: full method inventory, sync
  engine internals, module loader, options system, constants/globals/naming, 19 gotchas.
- `.agent/mystudio-admin.md` — ACP navigation/routing/permissions, every action enumerated,
  full AJAX API table, editor + page-builder flows, security summary, 13 gotchas.
- `.agent/mystudio-theme.md` — on-disk theme package format, theme.json schema, CSS/templates/js/
  functions/modules layers, all 5 modules in detail, vendor, lang.
- `.agent/sessions/README.md` — session-log convention + this index.
- `.gitignore` — added `.agent/` allowlist.

## Decisions

- **`.agent/` is tracked in git**, not gitignored. The repo uses an allowlist `.gitignore` (`*`
  blocks everything), so I added `!.agent/` + `!.agent/**` to make it track. Rationale: the user
  asked to store rules/understanding/future sessions in `.agent` "somewhere" — committing it keeps
  it durable and versioned alongside the code. Runtime artifacts (`cache/`, `uploads/`,
  `themes/**/.ms_last_sync`) remain ignored.
- **Five reference docs, not one mega-doc.** Split MyBB / plugin / admin / theme so an agent can
  load only the relevant slice per task (cheaper context, easier to keep in sync).
- **Used parallel subagents** to read the three large code areas concurrently (plugin core,
  admin module, theme) rather than serially — kept my context lean and got thorough file:line
  references. I distilled their reports into the reference docs and cross-checked key claims
  against the source myself (class_plugins, class_templates, init.php, theme schema, import_theme_xml).

## Gotchas found (most important — full lists in each doc)

- **Editor save can't create new templates/CSS** — `syncSingleFile` only UPDATEs existing rows;
  a brand-new file needs a full Sync. (`RULES.md` §4, `mystudio-plugin.md` §8 #5.)
- **Incremental sync keeps the templateset SID stable** by string-patching the XML — don't
  "fix" it. (`RULES.md` §5, `mystudio-plugin.md` §8 #4.)
- **`ms_*` settings are `gid=0`** so they're hidden from ACP Configuration; `ms_activate`
  orphans any legacy group. Don't create a settinggroup. (`RULES.md` §2, `mystudio-plugin.md` §1.)
- **Plugin owns no tables**; `ms_is_installed` checks the `ms_enabled` settings row. Page tables
  belong to the `ms-pagebuilder` module. (`RULES.md` §3.)
- **`theme.json` `name` matches DB `themes.name`** (not slug) — renaming it makes a new DB theme.
  (`mystudio-plugin.md` §8 #19.)
- **Path traversal**: admin module sanitizes `slug` but passes `path` through to core.php, where
  `realpath` containment lives. Audit there. (`mystudio-admin.md` §4, `RULES.md` §7.)
- **Admin actions** aren't `elseif`-chained — Manage is the fallback; actions below it are dead.
  New actions must be registered in `module_meta.php` AND handled in `mystudio.php`.
  (`mystudio-admin.md` §10 #1-2.)
- **Dark-mode only** theme; no light theme shipped. (`mystudio-theme.md` cross-cutting.)
- **`ms_pages` table not guarded** in `pages_api` save/delete/edit — depends on the pagebuilder
  module creating it. (`mystudio-admin.md` §10 #11.)

## Follow-ups / next session

- **RESOLVED — class name**: `core.php` declares `class MyStudio` (line 26, no extends/implements,
  no traits). Agent 3's `MyStudio_Core` was a misread; all docs use the correct `MyStudio`.
- **RESOLVED + bug found — module enable/disable**: `MyStudio::loadModules` (core.php:1889)
  loads every module with `init.php` and does NOT consult any enabled/disabled state, so
  "present = enabled" is the true runtime contract. The admin "Manage Extensions" page
  (mystudio.php:2179) and `api_toggle_plugin` (1619,1621) call `getMiniPluginStates`/
  `saveMiniPluginStates`, which are **NOT defined** on `MyStudio` (no `__call`, no parent —
  confirmed by static analysis). **Viewing the extensions page or clicking Activate/Deactivate
  fatals** "Call to undefined method". Documented in RULES.md §6, mystudio-plugin.md §8 #20,
  mystudio-admin.md §7 & §10 #14. Not runtime-confirmed (no DB in this env) but static evidence is
  conclusive. **Next session: fix by defining `getMiniPluginStates`/`saveMiniPluginStates` on the
  class (read/write a states JSON) AND wire `loadModules`/`getModuleAssets` to skip disabled
  modules.** This is a candidate first real task.
- Consider running a quick MyBB instance to confirm the install/sync flow end-to-end (requires a
  configured `inc/config.php` + DB; not done this session — no DB available in this environment).
- If any source line numbers drift after edits, re-pin the file:line refs in the reference docs.