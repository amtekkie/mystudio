# Working Rules — MyBB + MyStudio

Rules an agent MUST follow when editing this repository. Read at the start of every session.
These exist because the codebase has non-obvious conventions; ignoring them causes subtle bugs.

## 1. Disk is the source of truth for themes

`themes/{slug}/` files are canonical. The database (`themes`, `templates`, `themestylesheets`,
`templatesets`) is a **rebuildable cache** populated by the MyStudio sync engine. Never edit
theme content directly in the DB. Edit files on disk, then sync (Admin CP → MyStudio → Manage
Themes → **Sync**, or rely on dev auto-sync).

- Templates live as `templates/{group}/{name}.html`. Filename (minus `.html`) = template title;
  subdirectory = template group. `ungrouped/` is a real group.
- Stylesheets live in `css/`. They are cached to `cache/themes/theme{tid}/{name}`.
- Theme-level JS lives in `js/` and is **deployed flat** to `jscripts/` on sync (no subdirs).
- Module CSS/JS lives in `functions/modules/{id}/{css,js}/` and is served directly from the
  theme dir — it is **not** deployed to `jscripts/` or `cache/themes/`.

## 2. Never use MyBB settings group for MyStudio settings

All `ms_*` settings are inserted with `gid=0` so they don't show under ACP → Configuration →
Settings. They're managed inside the MyStudio admin UI. `ms_activate()` actively **orphans** any
legacy `mystudio` settinggroup to `gid=0` and deletes the group. Do NOT create a settinggroup
for MyStudio settings — it will be destroyed on next activation.

## 3. The plugin owns no DB tables

`ms_is_installed()` checks for the `ms_enabled` settings row, not a table. The Page Builder's
tables are owned by the separate `ms_pagebuilder` module/plugin. Don't add table-creation to
`ms_install()` without a matching `_is_installed()`/`_uninstall()` strategy.

## 4. Single-file sync cannot create new templates/stylesheets

`MyStudio::syncSingleFile()` (triggered by the editor's save) only `UPDATE`s existing rows by
title/name. A brand-new `.html` template or `.css` file saved via the editor will **not** appear
in the DB until a full `syncToDatabase()` run. If a task adds a new template file, run/trigger a
full Sync — don't assume the editor save propagated it.

## 5. Incremental sync keeps the templateset SID stable — by design

`syncToDatabase()` reuses the existing templateset SID and patches the theme XML's
`<templateset>` CDATA to preserve it. Only a brand-new import creates a new templateset. Don't
"fix" this by forcing new SIDs — external template references rely on the SID staying stable.

## 6. Modules: present = enabled (the Activate/Deactivate UI is broken)

At runtime, `MyStudio::loadModules()` (core.php:1889) loads **every** module that has an
`init.php` under `functions/modules/` — it does NOT consult any enabled/disabled state. So a module
is active iff its directory + `plugin.json` + `init.php` exist. To disable one, remove/rename the
folder. Don't rely on the admin "Activate/Deactivate" links to gate loading — they don't.

There IS an Activate/Deactivate UI in **MyStudio > Manage Extensions**, but it is **broken**:
`api_toggle_plugin` (mystudio.php:1611) and the extensions list page (mystudio.php:2179) call
`$ms->getMiniPluginStates()` / `saveMiniPluginStates()`, which are **not defined** on the
`MyStudio` class (no `__call`, no parent class). Viewing the page / clicking the link fatals with
"Call to undefined method". If you fix this, also wire `loadModules`/`getModuleAssets` to skip
disabled modules — currently state is written but never read.

Module `init.php` runs in both frontend and admin context. Globals injected into scope:
`$mybb`, `$plugins`, `$ms_plugin_options`, `$ms_plugin_dir`, `$ms_plugin_id`, `$ms_theme_slug`,
and `$mybb->ms_theme_options`.

## 7. Editor file operations are path-traversal hardened

`readThemeFile`, `writeThemeFile`, `createThemeFile`, `createThemeFolder`, `deleteThemeFile`,
`deleteThemeFolder`, `renameThemePath` all use `realpath` containment against the theme root.
Hidden files (leading dot) are rejected. `ALLOWED_EDIT_EXTENSIONS` is a blocklist: `php`, `phtml`,
`phar`, etc. are blocked. Any new editor file operation must preserve this containment check.
Never loosen the extension blocklist.

## 8. Sync depends on the MyBB admin directory

`importThemeXml()` requires `admin/inc/functions.php` and `admin/inc/functions_themes.php`
(for MyBB's `import_theme_xml()`). The admin dir is resolved via `MYBB_ADMIN_DIR` or
`$config['admin_dir']` (default `'admin'`). If the admin dir is renamed/misconfigured, sync fails
with a specific error. Don't assume the admin path is always `'admin'`.

## 9. MyBB conventions to respect

- Plugin functions are prefixed with the plugin filename: `ms_…` (file is `ms.php`).
- Plugin entry guard: `if(!defined("IN_MYBB")) { die("Direct initialization…"); }`.
- Hooks: `$plugins->add_hook($hook, $func, $priority=10, $file="")`. Value-passing hooks must
  `return` the value; reference-passing hooks (`&$arg`) mutate in place. Inspect the call site.
- Templates are rendered with `eval($templates->get('name'))` — they use `{$var}` interpolation.
- Settings live in the `settings`/`settinggroups` tables and `$mybb->settings['name']`.
  `rebuild_settings()` must be called after changing settings.
- Use `$db` abstraction: `insert_query`, `update_query`, `delete_query`, `simple_select`,
  `fetch_array`, `escape_string`, `table_exists`. Never raw SQL with unescaped input.
- CSRF: `verify_post_check()` on POST; `my_post_key` for AJAX tokens.

## 10. Keep the knowledge base in sync

When you change something material (new hook, new option type, new action, schema change,
convention change), update the matching `.agent/*.md` doc in the same change. A stale doc is
worse than none — fix it or note it.

## 11. Logging sessions

After a substantive working session, append a log to `.agent/sessions/` (see
`.agent/sessions/README.md`). Keep it short: what was studied/changed, decisions, follow-ups.
This is for continuity across sessions, not a full transcript.

## 12. Git discipline

- The default branch is `main`. Branch before committing unless told otherwise.
- `.gitignore` blocks everything (`*`) and allowlists specific paths. If you add a new tracked
  directory (like `.agent/`), you MUST add it to `.gitignore` or it won't be tracked.
- Don't commit runtime artifacts: `cache/`, `uploads/`, `themes/**/.ms_last_sync`.
- Commit messages end with `Co-Authored-By: Claude <noreply@anthropic.com>` (Claude Code default).