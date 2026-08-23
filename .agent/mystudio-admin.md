# MyStudio Admin CP Module

Reference for `admin/modules/mystudio/` (`module_meta.php` — ACP menu/permissions; `mystudio.php`
~134KB — all admin pages and AJAX endpoints) and the JS editor SPAs in `jscripts/mystudio/`.

## 1. ACP navigation (`module_meta.php`)

Registered as a **top-level ACP section**. Bootstrap Icons are force-loaded into ACP header
(`$page->extra_header`, from `themes/mystudio-default/vendor/bootstrap-icons.min.css`).

### Sidebar menu (`mystudio_meta()`, 13-64)
Only renders if plugin `ms` is active (reads `plugins` cache, line 19).

| Order | id | Title | Link | Conditional |
|------|----|-------|------|-----------|
| 10 | `manage` | Manage Themes | `index.php?module=mystudio-manage` | always |
| 20 | `import_export` | Import / Export | `index.php?module=mystudio-import_export` | always |
| 45 | `pages` | Page Manager | `index.php?module=mystudio-pages` | only if `ms-pagebuilder` module exists in active theme |
| 51+ | `plugin_<id>` | `<module name>` | `index.php?module=mystudio-plugin_settings&plugin=<id>` | dynamic, per module with `has_options`/`has_admin` (42-54) |
| 75 | `library` | Module Library | `index.php?module=mystudio-library` | always |
| 80 | `settings` | Studio Settings | `index.php?module=mystudio-settings` | always |

Top-nav entry added via `$page->add_menu_item(...)` at position **50**. Hook `admin_mystudio_menu`
lets plugins alter `$sub_menu`.

### Routing (`mystudio_action_handler`, 66-104)
Maps the action (after `mystudio-` in the `module` param) to the highlighted sidebar item. All
route to `mystudio.php`. Actions: `manage, import_export, import, export, plugin_settings,
settings, editor, pages, pages_add, pages_edit, pages_delete, pages_components, pages_api,
library, install_module, uninstall_module`.
- `editor` highlights `manage`. `import`/`export` highlight `import_export`. `pages_*` → `pages`.
- For `plugin_settings`, active item overridden to `plugin_<sanitized $_GET['plugin']>`.
- Unknown → `active_action="manage"`. Hook `admin_mystudio_action_handler`.

### Permissions (`mystudio_admin_permissions`, 106-121)
Section "MyStudio", disporder 50. Keys: `manage` (manage themes), `import_export` (import/export
themes), `options` (manage theme options), `settings` (manage extension settings), `pages`
(manage custom pages).
> `editor`, `library`, `install_module`, `uninstall_module`, and `api_*` endpoints are NOT
> separately permission-gated — they highlight `manage`/`library`. MyBB falls back to the parent
> module permission if a specific action permission is missing, so library access is gated by the
> user having *some* MyStudio permission. **mystudio.php never calls `check_admin_permissions`
> itself** — it relies on MyBB's `admin/index.php` gate.

## 2. `mystudio.php` bootstrap (1-488)

- `require_once inc/plugins/mystudio/core.php` + `admin/inc/functions_themes.php`; `$ms = new MyStudio()`.
- Re-inject Bootstrap Icons CSS into `$page->extra_header`.
- **Action resolution** (255-266): MyBB admin doesn't populate `$mybb->input['action']` for
  module-routed requests, so mystudio.php extracts it from the `module` param
  (`mystudio-<action>`); falls back to `manage`.
- Helpers: `ms_render_toolbar_builder` (29-252, drag-drop toolbar config for `toolbar_builder`
  options), `ms_help_box` (276-283), `ms_get_icon_list` (290-399, ~170 Bootstrap Icons → labels,
  for the icon-chooser modal), `ms_render_option_row` (405-488, renders one option row; types:
  text, textarea, yesno, select, radio, color, icon_chooser, numeric, image; image supports
  preview/remove/upload + optional `has_dimensions`).

## 3. Admin pages / actions — full enumeration

Each action block ends with `exit;` (no `elseif` chain). **Manage (2547-2841) is the fallback**
that runs when no block matched — the only action NOT inside an `if`.

### Page Manager
| Action | Type | Purpose |
|--------|------|---------|
| `pages_api` (494-630) | AJAX/JSON | Multi-endpoint API (§4) |
| `pages_delete` (636-644) | POST redirect | Delete page by `pid` |
| `pages_add` / `pages_edit` (650-781) | HTML | Full-page Monaco page editor |
| `pages` (787-920) | HTML | Pages list + Front Page selector |

### Theme Editor (Monaco) AJAX
| Action | Type | Purpose |
|--------|------|---------|
| `api_filetree` (922) | JSON | nested file tree |
| `api_readfile` (934) | JSON | read file |
| `api_savefile` (947) | JSON POST | save + sync |
| `api_filelist` (958) | JSON | flat list (search) |
| `api_createfile` (966) | JSON POST | create file |
| `api_createfolder` (976) | JSON POST | create folder |
| `api_deletefile` (986) | JSON POST | delete file |
| `api_deletefolder` (996) | JSON POST | delete folder |
| `api_rename` (1006) | JSON POST | rename |
| `api_sync` (1017) | JSON POST | force disk→DB sync |
| `editor` (1034-1165) | HTML | full-page Monaco editor |

### Import / Export
`export` (1171, binary download), `import` (1200, POST redirect), `import_export` (1226, combined page).

### Theme management (POST redirect)
`activate` (1306), `deactivate` (1317), `delete_theme` (1332), `sync_theme` (1366),
`convert` (1382, DB→disk), `api_upload_asset` (1406, AJAX image upload).

### Options / extensions
`api_saveoptions` (1499, POST, with page_filter isolation), `api_save_plugin_options` (1585),
`api_toggle_plugin` (1611), `plugin_settings` (2020, per-extension page), `plugins` (2163,
active/inactive tables), `settings` (2281, HTML+POST).

### Module Library
`install_module` (1631, from GitHub), `uninstall_module` (1695), `library` (1722, browse
`registry.json` from GitHub).

## 4. AJAX / JSON API endpoints

### Page Manager `pages_api` (494-630)
All return `Content-Type: application/json`; require `verify_post_check` (line 496). Sub-action by
`api_action`:

| api_action | Input | Response |
|---|---|---|
| `save` | pid,title,slug,content,status(draft/published),meta_title,meta_description,allowed_groups,custom_css,custom_js | `{success,pid}` or `{error}` |
| `get` | pid | `{success,page:<row>}` |
| `reorder` | order (JSON pid array) | `{success}` |
| `check_slug` | slug,pid | `{available:true}` or `{available:false,suggestion:"base-N"}` |
| `set_front_page` | front_page_type(default/portal/page), front_page_slug | `{success}` — writes `ms_front_page` cache |

### Theme Editor API (JSON)
| Action | Method | Input | Response |
|---|---|---|---|
| `api_filetree` | GET | `slug` (sanitized `[a-z0-9\-]`) | tree or `{error}` |
| `api_readfile` | GET | slug,path | content or `{error}` |
| `api_savefile` | POST | my_post_key,slug,path,content | `{success,time,errors}` |
| `api_filelist` | GET | slug | `{files}` |
| `api_createfile`/`api_createfolder` | POST | my_post_key,slug,path | `{success,errors}` |
| `api_deletefile`/`api_deletefolder` | POST | my_post_key,slug,path | `{success,errors}` |
| `api_rename` | POST | my_post_key,slug,old_path,new_path | `{success,errors}` |
| `api_sync` | POST | my_post_key,slug | `{success,tid,errors}` |
| `api_upload_asset` | POST | my_post_key,slug,field,file(multipart) | `{success,url,field}` or `{error}` |

> **Security**: `slug` is sanitized to `[a-z0-9\-]` here, but the **`path` parameter is passed
> through unchanged** to `MyStudio::*` methods in core.php. The path-traversal containment MUST
> be in core.php (it is — `realpath` checks; see `mystudio-plugin.md` §3, `RULES.md` §7).
> `api_upload_asset` is the only endpoint doing explicit `realpath()` containment in
> mystudio.php itself (1430-1435).

### `api_upload_asset` security (1406-1493)
- Sanitize slug + field. Explicit `realpath` containment under `themes/`.
- Ext allow-list: `png,jpg,jpeg,gif,svg,webp,ico`.
- **MIME cross-check** via `finfo_open(FILEINFO_MIME_TYPE)` (prevents disguised uploads).
- Max 5 MB. Filename forced to `<sanitized field>.<ext>` — user filename discarded.
- Returns relative URL `themes/<slug>/images/uploads/<safeName>`.

## 5. Theme editor (editor.js ↔ backend)

Served by `action=editor` (1034-1165); driven by `jscripts/mystudio/editor.js` (1056 lines).

- Requires `slug` param; sanitized before `is_dir` check (1042).
- Monaco AMD loader from `cdn.jsdelivr.net/npm/monaco-editor@0.50.0`.
- Hidden config div `#msEditorConfig` with `data-base-url="index.php?module=mystudio-manage"`,
  `data-post-key`, `data-slug`. **All editor AJAX appends `&action=api_*` to this base URL.**
- Inline `<style>` + HTML skeleton; loads `editor.js`.

### editor.js architecture
- IIFE; `CONFIG` (monacoVersion 0.50.0), `LANG_MAP` (ext→language), `ICON_MAP` (ext→Bootstrap
  Icon), `state` (editor, openTabs[], activeTab, fileTree, flatFiles, contextTarget).
- `setupMonaco` (119-211): custom `ms-light` theme; full editor options (minimap, ligatures,
  tabSize 4, wordWrap, bracket colorization, auto-close). `Ctrl/Cmd+S` → `saveCurrentFile`
  (**the Ctrl+S flow**), `Ctrl/Cmd+W` → `closeTab`. Emmet via `emmet-monaco-es` from CDN.
- `loadThemeTree` (349): two parallel fetches — `api_filetree` (tree) + `api_filelist` (flat).
- Multi-tab: `openFile`/`switchToTab`/`closeTab` (538-637); dirty tracking with `●` badges.
- **`saveCurrentFile` (639-687)**: POST `api_savefile` with FormData → backend
  `writeThemeFile($slug,$path,$content,true)` → writes disk **and syncs to DB** in one round-trip.
  Status "Saved & Synced (HH:MM:SS)"; toast. This is the implicit auto-sync (RULES.md §4 caveat
  re new templates still applies — `syncSingleFile` only UPDATEs).
- Context menu CRUD (837-1044): New File/Folder, Rename, Delete via the api_* endpoints.
- Sidebar: search (`#ms-file-search`, filters flat list), Collapse All, sidebar collapse toggle,
  drag-resize handle (160-600px). Status bar (Ln/Col/lang). Custom toasts.

## 6. Page Builder admin

### Storage — `ms_pages` table
Columns: pid, title, slug, content, status(draft/published), meta_title, meta_description,
allowed_groups (CSV gids), custom_css, custom_js, author_uid, disporder, created_at, updated_at.
**Front-page config is in the `ms_front_page` cache** (not a table): `{type,slug}` (619-622,806).
> `ms_pages` table existence is checked at runtime on the list page (795) but **NOT** guarded in
> `pages_api save`/`pages_delete`/`pages_edit` — if the table is missing those endpoints throw DB
> errors. The table is owned/created by the `ms-pagebuilder` module, not the plugin core.

### List (`pages`, 787-920)
- Reads pages ordered by `disporder`. Front Page selector (default/portal/published pages),
  hijacked by inline JS → `pages_api set_front_page`. Authors pre-loaded in one `users` query.
  Actions: Edit / View (`/<slug>`) / Delete (confirm + `my_post_key`).

### Editor (`pages_add`/`pages_edit`, 650-781)
Full-page shell with title/slug/permalink/status/Preview/Save; `#pb-monaco-container`; collapsible
Page Settings (meta title/desc, Allowed Groups chips, custom CSS/JS) and Available Variables
(`{$headerinclude}`, `{$header}`, `{$footer}`, `{$mybb->user['username']}`, `{$mybb->settings['bbname']}`,
`<if $mybb->user['uid'] then>...</if>`, …). Injects `window.MS_PAGE_DATA`,
`MS_PAGE_USERGROUPS`, `MS_PAGE_BBNAME`, `MS_PAGE_BBURL`. Loads Monaco + `pagebuilder.js`.

### pagebuilder.js (387 lines)
- `DEFAULT_HTML` skeleton; **legacy JSON-component-tree migration** (358-365): content starting
  with `[` (or `{` but not `{$`) is wrapped in an advisory comment — migration away from a JSON
  component tree to raw HTML.
- Custom `ms-light` Monaco theme. `Ctrl+S` + Save button → `savePage`.
- `savePage` (111-175): validate title+slug, collect active `.pb-group-chip.active` gids, POST
  `pages_api save`. On success update `pid`, clear dirty, `history.replaceState` to add `pid`.
- Auto-slug from title when `pid==0` and slug not manually edited. Variables insert at cursor.
  Preview opens `/<slug>?preview=1` (needs `pid>0`). beforeunload guard if dirty.

## 7. Module settings admin

### Dynamic sidebar
`mystudio_meta` (42-54): for each module with `has_options`/`has_admin`, a sidebar item
`plugin_<id>` → `plugin_settings&plugin=<id>`. Action handler (96-98) sets active to `plugin_<id>`.

### `plugin_settings` (2020-2157)
Sanitize `plugin` (2024). Look up via `listMiniPlugins` (2031). Render `Form` posting to
`api_save_plugin_options` with hidden `plugin_id`. Option types reuse the theme-option switch +
`toolbar_builder`, `preset_swatches`. **Custom admin pages**: if `has_admin`, `include
<dir>/admin.php` (2152) — module renders its own admin content below the options form.

### `plugins` (2163-2275)
Active/Inactive tables. Activate/Deactivate → `api_toggle_plugin` (`enable=0|1`+`my_post_key`).
Settings link → `plugin_settings` (only when `has_options||has_admin`).

> **BUG**: the extensions list page calls `$ms->getMiniPluginStates($activeSlug)` unconditionally
> (2179), and `api_toggle_plugin` calls `getMiniPluginStates`/`saveMiniPluginStates` (1619,1621).
> These methods are **NOT defined** on `MyStudio` (no `__call`, no parent — confirmed). Viewing the
> page or clicking Activate/Deactivate fatals "Call to undefined method". And `loadModules` doesn't
> read state anyway, so Activate/Deactivate wouldn't gate loading even if the methods existed.
> Runtime truth: present = enabled (RULES.md §6).

## 8. Security summary

- **CSRF**: `verify_post_check` on every state-changing endpoint (full list in agent notes).
  Read-only GET endpoints (`api_filetree`, `api_readfile`, `api_filelist`, `check_slug`, `get`)
  do NOT verify post key.
- **Slugs**: `preg_replace('/[^a-z0-9\-]/','',$slug)` for theme paths.
- **Page slugs**: broader `[^a-zA-Z0-9\-_\/]` (allows nested paths + uppercase).
- **allowed_groups**: `[^0-9,]`. **plugin ids**: `[^a-z0-9\-_]`.
- **Path traversal**: `slug` sanitized here; `path` passed through to core.php (containment there).
  `api_upload_asset` does explicit `realpath` containment here. `delete_theme` guards
  `mystudio-default`. Module library downloader skips names with `..`,`/`,`\` (1964).
  `install_module` won't overwrite existing dir + cleans up on failure.
- **Uploads**: `api_upload_asset` — ext allow-list + MIME cross-check + 5MB + safe filename.
  `api_saveoptions` image handling (1528-1566) — same ext list + 5MB but **no MIME cross-check, no
  realpath** (lower assurance; relies on sanitized slug for path). `import` passes ZIP to
  `importFromZip` (unzip defense in core.php).

## 9. Templates & output methods

MyStudio's admin UI does **NOT** use the MyBB front-end template table. It uses ACP primitives:
`$page->extra_header`, `add_breadcrumb_item`, `output_header`/`output_footer`, `output_nav_tabs`,
`Form`/`FormContainer`, `Table`, `PopupMenu`, `flash_message` + `admin_redirect`,
`htmlspecialchars_uni`. The editor and page builder are single-page apps emitted as raw HTML via
heredocs with injected JSON config.

## 10. Gotchas

1. **Action resolution quirk** — new actions must be registered in `module_meta.php`'s `$actions`
   AND handled by an `if($action===...)` block here.
2. **No `elseif` chain** — Manage is the fallback (2547-2841); actions below it are unreachable.
3. **`pages_components` registered but never handled** — falls through to Manage (legacy
   component-builder placeholder; pagebuilder.js migrated to raw HTML).
4. **Two Monaco SPAs**: `editor.js` (theme file editor, multi-tab) and `pagebuilder.js` (single
   HTML editor for `ms_pages`). Both define `ms-light`, same CDN, distinct state.
5. **`api_savefile` auto-syncs** via `writeThemeFile(...,true)` (RULES.md §4 caveat re new files).
6. **`api_saveoptions` page isolation** (1511-1525): saving one page-filter preserves other pages'
   values from existing stored values. `redirect_to` controls post-save landing.
7. **Settings auto-migration** (2286-2298): `settings` action upserts `ms_dev_auto_sync`,
   `ms_dev_sync_interval`, `ms_loading_bar` then `rebuild_settings()` — runs on every visit.
8. **Broken-theme detection** (2570-2724): Manage scans `themes/`, validates each `theme.json`
   (JSON parse, name/slug match, referenced css/js exist, ≥1 `.html` in templates/, sync status,
   file perms). Real issues shown in "Broken Themes Detected" table.
9. **`install_module`/`uninstall_module` forms POST to `module=mystudio-manage`** (misleading;
   works because `action` param takes precedence). `pages_components` & the `plugins` redirect
   (1624 → `mystudio-plugins`) highlight `manage` not the intended item (sidebar highlight bug,
   cosmetically wrong).
10. **Hardcoded library repo** (1730): `https://github.com/amtekkie/mystudio-modules`,
    `registry.json` from `main` raw URL. Update = "uninstall then reinstall" (no in-place upgrade).
11. **`ms_pages` not guarded** in `pages_api save`/delete/edit — depends on the module creating it.
12. **`save` disporder = MAX+1** — minor race on concurrent page creation (acceptable for admin tool).
13. **Branding form + icon-chooser modal** (2396-2535) emitted only when active theme has
    `logo_icon`/`logo_text`/`site_logo`/`favicon` options. Modal exposes `window.FmzIconModal`
    (legacy "Fmz" prefix) + `window.initIconPickers`.
14. **Manage Extensions is broken** — `plugins` page (2179) and `api_toggle_plugin` (1619,1621)
    call `MyStudio::getMiniPluginStates`/`saveMiniPluginStates`, which are **undefined** (no
    `__call`, no parent). Fatal on view/click. See RULES.md §6 and `mystudio-plugin.md` §8 #20.