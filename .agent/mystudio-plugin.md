# MyStudio Plugin Internals

Reference for `inc/plugins/ms.php` (plugin lifecycle) and `inc/plugins/mystudio/core.php`
(the `MyStudio` class: sync engine, theme import/export, options, module loader). See
`mystudio-admin.md` for the Admin CP module, `mystudio-theme.md` for the on-disk theme format.

- **Plugin codename**: `mystudio`; **file**: `ms.php`; **version**: 2.1.0; author Tektove.
- **Compatibility**: `18*` (MyBB 1.8.x).
- **core.php is ~1941 lines.** `ms.php` is 614 lines. core.php registers **no hooks** — it's a pure
  class. All hook registration lives in `ms.php`.

## 1. Plugin lifecycle (`ms.php`)

- **`ms_info()`** (ms.php:42-63): name "MyStudio", version 2.1.0, codename `mystudio`, compatibility
  `18*`. When `ms_enabled` is on, the ACP plugin name links to `index.php?module=mystudio-manage`.
- **`ms_install()`** (ms.php:65-145): deletes any legacy `mystudio` settinggroup + `ms_%` settings,
  then inserts **6 settings with `gid=0`** (so they're hidden from ACP → Configuration):
  `ms_enabled` (yesno, 1), `ms_max_upload_mb` (numeric, 20), `ms_dev_auto_sync` (yesno, 0),
  `ms_dev_sync_interval` (numeric, 2), `ms_show_sidebar` (yesno, 0), `ms_loading_bar` (yesno, 1).
  Calls `rebuild_settings()`. mkdirs `inc/plugins/mystudio`, `themes`, `jscripts/mystudio`.
  **Creates NO DB tables.** Page-manager tables are owned by `ms_pagebuilder` (ms.php:144,162).
- **`ms_is_installed()`** (ms.php:147-152): true iff a `settings` row `name='ms_enabled'` exists.
- **`ms_uninstall()`** (ms.php:154-177): deletes all `ms_%` settings + `mystudio` group, rebuilds,
  then recursively deletes every `themes/*` dir with a `theme.json` **except `mystudio-default`**
  (protected). Uses local `ms_rrmdir()` (ms.php:183-196).
- **`ms_activate()`** (ms.php:198-211): one-shot migration — orphans any `mystudio` settinggroup's
  settings to `gid=0`, deletes the group, rebuilds. Idempotent.
- **`ms_deactivate()`** (ms.php:213-216): no-op.

> See `RULES.md` §2, §3 — settings are `gid=0` by design; the plugin owns no tables.

## 2. Hooks registered (all in `ms.php`)

| Hook | Handler | Line | Purpose |
|------|---------|------|---------|
| `global_start` | `ms_global_start` | 224-249 | Pre-init a curated list of template vars to `''` (PHP 8.3 undefined-var compat); `$lang->bottomlinks_current_time` fallback. Every page. |
| `global_intermediate` | `ms_load_theme_extras` | 291-370 | Frontend only. Slugify active theme name; load theme lang into `$lang`; load `default.json` into `$mybb->ms_theme_options` (always array); include `functions/hooks.php`; call `MyStudio::loadModules()`; store slug in `$mybb->ms_active_slug`. |
| `pre_output_page` | `ms_inject_mini_plugin_assets` | 379-411 | Frontend only. Inject module CSS `<link>`/JS `<script>` before `</head>`; inject dev auto-sync poller before `</body>` for admins. |
| `xmlhttp` | `ms_xmlhttp_action` | 421-536 | xmlhttp.php doesn't fire `global_intermediate`, so load modules here; re-run `xmlhttp` hook (static `$miniLoaded` recursion guard); handle own actions `ms_dev_sync_check` (GET → hash) and `ms_dev_sync_run` (POST, CSRF-checked → `syncToDatabase`, persist hash to `ms_dev_last_hash`). |

Admin-side: `ms_load_admin_mini_plugins()` (ms.php:256-278) runs at plugin load when
`IN_ADMINCP` is defined, so module `init.php` can register admin hooks.

## 3. `MyStudio` class public methods (`core.php`)

### Sync engine
- `importFromZip($file, $parentTid=1)` (72) — validate ZIP (anti-ZIP-slip), extract, copy to
  `themes/{slug}/`, `syncToDatabase`, `deployJs`.
- `syncToDatabase($slug, $parentTid=1)` (375) — disk → DB. Incremental update if theme exists
  (reuse SID, `no_templates`), full import if new. Then `deployJs` + clear cache + rebuild CSS.
- `getThemeFilesHash($slug)` (1705) — MD5 of all file paths+mtime+size (cheap change detection).

### Import / export / extraction
- `exportTheme($tid)` (196) — build a ZIP (from disk, or `extractThemeToDisk` first if DB-only).
- `extractThemeToDisk($tid, $slug=null)` (252) — reverse: write a DB theme's stylesheets,
  templates, JS + generated `theme.json` to `themes/{slug}/`.

### Theme management (DB)
- `listThemesOnDisk()` (534), `listDbThemes()` (626) — cross-referenced disk↔DB lists.
- `activateTheme($tid)` (577) — set `def=1`, rebuild `default_theme` cache.
- `deactivateTheme($tid)` (604) — unset default, fall back to lowest non-master tid.
- `deleteTheme($tid, $deleteDisk=true)` (873) — delete DB rows + cache (+disk); refuses active
  default; re-parents children to `tid=1`.

### File / editor ops (path-traversal safe — see `RULES.md` §7)
`getFileTree` (1180), `readThemeFile` (1192), `writeThemeFile` (1214, `$syncToDB` flag),
`createThemeFile` (1331), `createThemeFolder` (1380), `deleteThemeFile` (1417),
`deleteThemeFolder` (1440, refuses root), `renameThemePath` (1466, blocks unsafe ext),
`getFlatFileList` (1498).

### Theme options
`getThemeOptions` (1544, reads `functions/options.php`), `themeHasFunctions` (1560),
`getThemeOptionValues` (1578, `default.json`), `saveThemeOptionValues` (1594),
`getMergedThemeOptions` (1610, merges defaults, expands `image` `_width`/`_height`),
`loadThemeLang` (1642).

### Modules / mini-plugins
`listModules` (1775) / `listMiniPlugins` (1763, legacy alias) — discover via `plugin.json`.
`loadModules` (1889) — `include_once init.php` per module, injecting globals.
`getModuleAssets` (1911) — CSS/JS URLs with `?v={filemtime}` cache-bust.

### Module options
`getMiniPluginOptions` (1850), `getMiniPluginOptionValues` (1817, `default.json`),
`saveMiniPluginOptionValues` (1834), `getMergedMiniPluginOptions` (1867, keyed by def `id`).

### Helpers
`getErrors` (661), `cleanup` (667, rm export temp), `getActiveThemeSlug` (1675, queries `def=1`),
`slug` (1689), `rrmdir` (1090).

### Notable private methods
`isSafeExtension` (53), `validateThemeDir` (682), `buildThemeXml` (721), `importThemeXml`
(811, wraps MyBB's `import_theme_xml`), `deployJs` (940), `findThemeRoot` (955),
`scanTemplateDir` (974), `getTemplateFolderMap` (993, longest-prefix group match),
`makeSlug` (1041, fallback `unnamed-theme`), `escapeCdata` (1049), `rcopy` (1057),
`addDirToZip` (1075), `updateStylesheet` (1104), `updateTemplate` (1123),
`rebuildStylesheetCache` (1143), `buildFileTreeNode` (1284), `collectFiles` (1512),
`syncSingleFile` (1251), `syncTemplatesIncremental` (462), `collectFileTimestamps` (1723).

## 4. Sync engine — detailed behavior

### `syncToDatabase` (core.php:375-448)
Disk is source of truth. (1) validate `theme.json`+`templates/`. (2) look up existing DB theme
by **name** (not slug), excluding `tid=1` Master. (3a) **exists → incremental**: recover existing
templateset SID, `syncTemplatesIncremental`, build XML, **string-replace** the empty
`<templateset>` CDATA with the existing SID, call `importThemeXml` with
`['no_templates'=>1, 'templateset'=>$sid]` (stylesheets+properties only). (3b) **new → full
import** with templates. (4) `deployJs`, clear `cache/themes/theme{tid}/*`, `rebuildStylesheetCache`.

### `syncTemplatesIncremental` (462-527)
Read disk `.html` files → `title=>content`. Read DB rows for SID → `title=>[tid,template]`. For
each disk template: UPDATE if changed (bump version+dateline), INSERT if new. Leftover DB rows
→ DELETE. (See `RULES.md` §5 — SID stays stable by design.)

### `syncSingleFile` (1251-1279) — editor save path
`writeThemeFile($slug,$path,$content,true)` → dispatches on extension+path:
- `css/*.css` → `updateStylesheet` + `rebuildStylesheetCache`.
- `templates/*.html` → `updateTemplate` (**UPDATE only** — no INSERT for new templates).
- `js/*.js` → `deployJs`.
- Other (json,…) not synced.

> See `RULES.md` §4 — editor save CANNOT create new templates/stylesheets; full Sync required.

### `rebuildStylesheetCache` (1143-1172)
Write each `themestylesheets` row's CSS to `cache/themes/theme{tid}/{name}`, then call MyBB's
`update_theme_stylesheet_list($tid)` (from `admin/inc/functions_themes.php`) if available; else
silently skip the list rebuild (serialized `themes.properties` may go stale — gotcha #7).

### `deployJs` (940-953)
Copy `themes/{slug}/js/*.js` → `jscripts/` **flat** (no subdirs). Does NOT delete stale JS.
(See gotcha #14.)

### `extractThemeToDisk` (252-354) — reverse, for export of DB-only themes
Writes `css/{name}` (with attachedto/order metadata), `templates/{group}/{title}.html` (group via
`getTemplateFolderMap`, longest-prefix match on `templategroups` prefixes or a hardcoded ~38-prefix
list; unmatched → `ungrouped/`), `js/{name}` (looks for `main.js`/`ms-main.js`/`tek-main.js`),
and generated `theme.json` (`name`, `version`=1839 fallback, `properties`, `stylesheets`, `js`).

## 5. Module loader — detailed behavior

### Discovery (`listModules`, 1775-1808)
Scan `functions/modules/`. Each subdirectory needs a `plugin.json` with at least `name`. Returns
per-module: `id, name, description, version, author, has_init, has_options, has_admin, has_js,
has_css, dir`.

### Loading (`loadModules`, 1889-1903)
For each module with `has_init`, `include_once init.php` after injecting globals:
`$ms_plugin_options` (merged values), `$ms_plugin_dir` (abs path), `$ms_plugin_id` (dir name),
`$ms_theme_slug`. Same injection in admin via `ms_load_admin_mini_plugins()` (ms.php:256-278).

> See `RULES.md` §6 — **present = enabled** at runtime. `loadModules` does NOT consult any
> enabled/disabled state; it loads every module with `init.php`. Disable = remove/rename folder.
> The admin Activate/Deactivate UI calls `getMiniPluginStates`/`saveMiniPluginStates`, which are
> **not defined** on this class (no `__call`, no parent) → fatal. See gotcha #20.

### Asset auto-injection (`getModuleAssets`, 1911-1940)
CSS/JS URLs: `themes/{slug}/functions/modules/{id}/{css|js}/{file}?v={filemtime}`. Injected into
`<head>` before `</head>` by `ms_inject_mini_plugin_assets` (ms.php:379-411). Module assets are
served from the theme dir — **not** deployed to `jscripts/`/`cache/themes/`.

### Module options storage
Definitions from `options.php` (returns array, each entry has an `id` field as key). Values in
`functions/modules/{id}/default.json`. `getMergedMiniPluginOptions` keys by def `id`.

## 6. Options system

### Theme options
- Definitions: `functions/options.php` returns array (keys = option keys). Types in docblock
  (core.php:1532): `text|textarea|yesno|select|color|numeric`. `getMergedThemeOptions` also
  handles `image` with `has_dimensions`/`default_width`/`default_height` → expands
  `{key}_width`/`{key}_height` (de facto supported though unlisted).
- Storage: `themes/{slug}/default.json`.
- Runtime: `ms_load_theme_extras` (ms.php:348) loads into `$mybb->ms_theme_options` (always array).
  Templates use `{$mybb->ms_theme_options['key']}`.

### Module options
Definitions: `functions/modules/{id}/options.php` (entries keyed by `id`). Storage:
`functions/modules/{id}/default.json`. Runtime: injected as `$ms_plugin_options` into `init.php`.

| Type | Theme | Module |
|------|-------|--------|
| text, textarea, yesno, select, color, numeric | yes | yes |
| image (+dimensions) | yes | no |
| (admin-only: radio, icon_chooser, toolbar_builder, preset_swatches, nav_links) | rendered in admin module | rendered in admin module |

The option *rendering* lives in the admin module (`ms_render_option_row`, `ms_render_toolbar_builder`),
not in core.php. core.php only reads/writes definitions and values.

## 7. Constants, globals, naming

- `MyStudio::THEMES_DIR` = `'themes'`.
- `MyStudio::ALLOWED_EDIT_EXTENSIONS` (blocklist-safe): `html,htm,css,js,json,txt,md,xml,svg,ini,
  yml,yaml,less,scss,map,csv,log,tpl,mustache,hbs`. `php`,`phtml`,`phar` blocked.
- Settings: all `ms_*`, all `gid=0`. Hidden `ms_dev_last_hash` (created on first dev-sync,
  ms.php:515-523). Referenced (not created here): `mystudio_default_tid` (deleteTheme:884,890).
- `$mybb->settings['ms_*']`, `$mybb->ms_theme_options`, `$mybb->ms_active_slug`.
- Module init globals: `$ms_plugin_options`, `$ms_plugin_dir`, `$ms_plugin_id`, `$ms_theme_slug`.
- **Slug**: `[^a-z0-9]+`→`-`, trim trailing `-`, fallback `unnamed-theme` (`makeSlug`).
- Theme dir: `MYBB_ROOT/themes/{slug}/`. Manifest `theme.json` (`name` required; `version`,
  `properties{}`, `stylesheets[]` with `name`/`attachedto`/`order`, `js[]`).
- Templates: `templates/{group}/{title}.html` (filename=title, subdirs are organization only;
  `scanTemplateDir` flattens).
- CSS cache: `cache/themes/theme{tid}/{name}`. Theme JS deploy: `jscripts/` flat.
- Theme option values: `themes/{slug}/default.json`. Module manifest: `functions/modules/{id}/plugin.json`
  (named `plugin.json`, not `module.json`). Module option values: `functions/modules/{id}/default.json`.
- Default version string: `'1839'` (fallback throughout).
- `disporder` is `serialize()`'d (PHP), not JSON, in the XML CDATA (matches MyBB).
- `escapeCdata`: `]]>` → `]]]]><![CDATA[>`.

## 8. Gotchas & developer notes

1. **Disk = truth, DB = cache.** Sync pushes disk→DB; `extractThemeToDisk` is reverse (export only).
2. **Settings hidden (gid=0).** `ms_activate` actively orphans legacy groups. Don't create a group.
3. **No plugin tables.** `ms_is_installed` checks `ms_enabled` row, not a table.
4. **Incremental sync keeps SID stable** — only brand-new import creates a templateset.
5. **`syncSingleFile` can't create new templates/CSS** — only UPDATE existing rows.
6. **`importThemeXml` depends on admin files** — `admin/inc/functions.php` (for `check_template`)
   + `admin/inc/functions_themes.php` (for `import_theme_xml`). Admin dir resolved via
   `MYBB_ADMIN_DIR` / `$config['admin_dir']` (default `admin`). Renamed admin dir → sync fails.
7. **`rebuildStylesheetCache` depends on admin functions** — if `update_theme_stylesheet_list`
   unavailable (non-admin context), raw CSS files are written but the serialized list goes stale.
8. **Dev auto-sync polling** — when `ms_dev_auto_sync` on + admin (`cancp==1`), `ms_get_dev_sync_script`
   (ms.php:545-613) injects a JS poller hitting `xmlhttp.php?action=ms_dev_sync_check` every
   `ms_dev_sync_interval` s, comparing `getThemeFilesHash` vs `ms_dev_last_hash`; on change POSTs to
   `ms_dev_sync_run` (CSRF `my_post_key`) then force-reloads with `_mssync={ts}` cache-buster.
9. **ZIP-slip / traversal hardening** — `importFromZip` rejects entries with `..`, `\`, null bytes,
   abs paths (114-138). All editor ops use `realpath` containment. Hidden files rejected for
   write/create. `renameThemePath` blocks renaming to unsafe ext.
10. **`getTemplateFolderMap` longest-prefix match** — group prefixes sorted desc by length so
    `postbit_author_user` matches `postbit` not `post`. Unmatched → `ungrouped/`.
11. **`buildThemeXml` properties are a fixed set** — `templateset, editortheme, imgdir, tablespace,
    borderwidth, color, disporder`. Other DB theme properties are lost on a disk→DB→disk cycle.
12. **`deployJs` is flat + doesn't delete stale JS** — re-deploy leaves orphaned files in `jscripts/`.
13. **`deleteTheme` re-parents children to `tid=1`** (923); refuses active default (checks
    `mystudio_default_tid` setting then `def=1` row).
14. **`activateTheme`/`deactivateTheme` use only the `def` column** — they don't touch
    `mystudio_default_tid` (only `deleteTheme` consults it). Slight inconsistency.
15. **`posting-extras.php` is a hardcoded special-case** — included in `ms_xmlhttp_action` before
    module loading (ms.php:443-446), a built-in quick-search handler outside the generic loader.
16. **`listMiniPlugins` legacy alias** for `listModules`; option methods still use `MiniPlugin` naming.
17. **`ms_global_start` PHP 8.3 compat** — pre-sets template vars (`portal_link`, `calendar_link`,
    `title`, `content`, `buttons`, `now`, `debugstuff`, `comma`, `awaitingusers`, `quicklogin`,
    profile vars) to `''` if unset (228-244).
18. **`ms_uninstall` preserves `mystudio-default`** (ms.php:168) — don't store irreplaceables in
    other theme folders.
19. **`theme.json` `name` is matched against the DB theme name** (not slug) in `syncToDatabase`.
    Renaming a theme's `name` in `theme.json` creates a NEW DB theme instead of updating.
20. **Module Activate/Deactivate is broken** — the admin "Manage Extensions" page (mystudio.php
    :2179) and `api_toggle_plugin` (:1619,1621) call `$ms->getMiniPluginStates()` /
    `saveMiniPluginStates()`, which are **NOT defined** on the `MyStudio` class (no `__call`, no
    parent, no traits — confirmed by static analysis). Viewing the page or clicking Activate/
    Deactivate fatals with "Call to undefined method". `loadModules`/`getModuleAssets` don't read
    any state anyway, so even if these existed they wouldn't gate loading. Runtime truth:
    present = enabled.