---
name: mystudio
description: MyStudio plugin internals for this repo — theme sync engine (disk ↔ DB), theme package layout, theme/module PHP locations, module loader, option schemas, import/export, and the ACP module. Use for any change to inc/plugins/ms.php, inc/plugins/mystudio/, admin/modules/mystudio/, jscripts/mystudio/ or themes/.
---

# MyStudio

Layout table + top rules are in `CLAUDE.md`. Detail files (read only when needed):
- `references/admin.md` — ACP actions, AJAX endpoints, editor/page-builder SPAs, admin gotchas.
- `references/theme.md` — the shipped `mystudio-default` theme and its 5 modules.

## Plugin (`inc/plugins/ms.php`)

- Lifecycle: `ms_install` inserts 6 `ms_*` settings with `gid=0` (`ms_enabled`,
  `ms_max_upload_mb`, `ms_dev_auto_sync`, `ms_dev_sync_interval`, `ms_show_sidebar`,
  `ms_loading_bar`); `ms_is_installed` = `ms_enabled` row exists; `ms_uninstall` removes settings
  and every `themes/*` with `theme.json` except `mystudio-default`; `ms_activate` orphans any
  legacy `mystudio` settinggroup.
- Hooks: `global_start` → `ms_global_start` (pre-sets template vars for PHP 8.3);
  `global_intermediate` → `ms_load_theme_extras` (slug from `$theme['name']`, load
  `inc/plugins/mystudio/themes/{slug}/lang/`, `themes/{slug}/default.json` →
  `$mybb->ms_theme_options`, include theme `hooks.php`, `loadModules`, set
  `$mybb->ms_active_slug`); `pre_output_page` → `ms_inject_mini_plugin_assets` (module CSS/JS
  before `</head>`, dev-sync poller for admins); `xmlhttp` → `ms_xmlhttp_action` (loads theme
  `posting-extras.php` + modules, re-runs `xmlhttp` behind a static guard; handles
  `ms_dev_sync_check`/`ms_dev_sync_run`).
- ACP: `ms_load_admin_mini_plugins()` includes every module `init.php` when `IN_ADMINCP`.

## `MyStudio` class (`inc/plugins/mystudio/core.php`, no hooks)

- Paths: `getAdminPath()`, `getModulesDir()`, `getThemeCodeDir($slug)`,
  `getModuleAdminDir($id)`, `getThemeAdminDir($slug)`; consts `THEMES_DIR`, `PLUGIN_DIR`.
- Sync: `syncToDatabase($slug)` — finds DB theme by **`theme.json` name** (renaming creates a new
  theme). Existing → `syncTemplatesIncremental` (UPDATE/INSERT/DELETE vs disk) + XML import with
  `no_templates` and the **existing SID patched in** (keep SID stable). New → full import.
  Then `deployJs` (copies `js/*.js` flat to `jscripts/`, never deletes stale), clears + rebuilds
  `cache/themes/theme{tid}/`.
- Editor save: `writeThemeFile(...,true)` → `syncSingleFile` — UPDATE-only for `css/*.css`,
  `templates/**/*.html`; deploys `js/`. New files need full sync.
- Import/export: `importFromZip` (ZIP-slip checks → `themes/{slug}` → `relocateThemeCode` →
  sync). `exportTheme` zips the theme dir and re-adds theme PHP as `functions/` + `lang/` and
  the schema as `functions/options.php`. Modules are global and are **not** exported.
- `extractThemeToDisk($tid)` — DB → disk (template group via longest `templategroups.prefix`
  match; unmatched → `templates/ungrouped/`). `buildThemeXml` round-trips only `templateset,
  editortheme, imgdir, tablespace, borderwidth, color, disporder`.
- Editor file ops (`readThemeFile`, `writeThemeFile`, `create*`, `delete*`, `renameThemePath`):
  `realpath` containment in `themes/{slug}`, dotfiles rejected, `ALLOWED_EDIT_EXTENSIONS`
  (no php/phtml/phar).
- `getThemeFilesHash($slug)` — md5 of path|mtime|size for dev auto-sync.

## Options

- Theme: schema `admin/modules/mystudio/themes/{slug}/options.php` returns
  `['key' => [title, description, type, default, page]]`; values `themes/{slug}/default.json`;
  templates read `{$mybb->ms_theme_options['key']}`. `getMergedThemeOptions` fills defaults and
  expands `image` + `has_dimensions` into `{key}_width/_height`.
- Module: schema `admin/modules/mystudio/modules/{id}/options.php` returns a **list** of
  `['id' => ..., 'type' => ..., 'default' => ...]` (falls back to the module dir if not yet
  relocated); values `inc/plugins/mystudio/modules/{id}/default.json`.
- Types rendered by `ms_render_option_row` (admin): text, textarea, yesno, select, radio, color,
  color_presets (`options` = hex → label swatches + custom input), numeric, image, icon_chooser;
  plus `toolbar_builder`, `preset_swatches`, `nav_links` on module settings pages.
- Theme options with `page => studio_settings` render in Studio Settings → Theme Settings
  (`logo_icon/logo_text/site_logo/favicon` under Branding, the rest under Appearance) and save
  through `ms_save_theme_options()`. `theme_color` → `msdefault_theme_color_css()` in the theme's
  `hooks.php` overrides the `--tekbb-accent*` / link / button / heading vars.

## Modules (`inc/plugins/mystudio/modules/{id}/`)

- Discovered by `listModules()` (needs `plugin.json` with `name`; each entry has `core` and
  `enabled`). State: `CORE_MODULES` always on; others via `setModuleEnabled()` → datacache
  `ms_modules` (`{enabled: [ids]}`), default off. `listActiveModules()` feeds `loadModules`,
  `getModuleAssets`, the ACP loader and the ACP sidebar.
- `loadModules()` includes `init.php` with `$ms_plugin_options`, `$ms_plugin_dir`, `$ms_plugin_id`, `$ms_theme_slug` in
  scope. `init.php` runs in frontend **and** ACP — guard with `defined('IN_ADMINCP')`.
- `css/*.css` and `js/*.js` auto-inject on every frontend page from
  `inc/plugins/mystudio/modules/{id}/…?v={mtime}`.
- Optional `admin.php` is included below the options form on the module's settings page.
- There is no remote module install; devs add a folder, then activate it in ACP → Modules.

## Adding things — checklists

- **New template**: create `themes/{slug}/templates/{group}/{title}.html` → full sync.
- **New theme hook**: add to `inc/plugins/mystudio/themes/{slug}/hooks.php` (loaded at
  `global_intermediate`; `global_start` has already fired — call setup functions directly).
- **New lang string**: `inc/plugins/mystudio/themes/{slug}/lang/en/frontend.lang.php`, use
  `{$lang->key}`.
- **New module**: dir + `plugin.json` + `init.php` (+ schema in the admin dir, `default.json`),
  then activate it in ACP → Modules (or add to `CORE_MODULES` only if it ships with MyStudio).
- **New ACP action**: register in `module_meta.php` `$actions` **and** add an
  `if ($action === 'x')` block in `mystudio.php` above the Manage fallback.
