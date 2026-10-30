# MyStudio ACP module (`admin/modules/mystudio/`)

## Navigation — `module_meta.php`

- Top-level ACP section (only when plugin `ms` is active); top-nav link → `mystudio-settings`.
  `mystudio.php` hides the core sidebar box title via `extra_header` CSS.
  Sidebar: Studio Settings (`settings`, first), Manage Themes (`manage`), Import / Export
  (`import_export`), Page Manager (`pages`, if `ms-pagebuilder` is active), one `plugin_<id>`
  item per active module with options/admin, Modules (`modules`). Hooks: `admin_mystudio_menu`,
  `admin_mystudio_action_handler`.
- `mystudio_action_handler` maps actions → `mystudio.php` + highlighted item. Empty action →
  `settings`; unknown → `manage`. `mystudio.php` also defaults an empty action to `settings`.
- Permissions: `manage`, `import_export`, `options`, `settings`, `pages`. `mystudio.php` never
  calls `check_admin_permissions` itself; editor/api actions ride on `manage`; `modules`/`api_toggle_plugin` on `modules`.

## `mystudio.php`

- The action comes from the `module` param (`mystudio-<action>`), not `$mybb->input['action']`.
- Each action is an `if ($action === ...) { ... exit; }` block; **Manage is the unconditional
  fallback at the end** — new blocks must go above it.
- Helpers: `ms_render_option_row`, `ms_render_toolbar_builder`, `ms_get_icon_list`,
  `ms_help_box`, `ms_save_theme_options`, `ms_output_icon_picker_modal`.

### Actions

| Group | Actions |
|-------|---------|
| Themes | `manage` (list + broken-theme detection), `activate`, `deactivate`, `delete_theme` (guards `mystudio-default`), `sync_theme`, `convert` (DB → disk) |
| Import/export | `import_export`, `import`, `export` |
| Editor (Monaco) | `editor` page + JSON `api_filetree`, `api_readfile`, `api_filelist` (GET); `api_savefile`, `api_createfile`, `api_createfolder`, `api_deletefile`, `api_deletefolder`, `api_rename`, `api_sync`, `api_upload_asset` (POST + `my_post_key`) |
| Options | `api_saveoptions` (theme, via `ms_save_theme_options`; preserves other `page` filters' values, validates colours), `plugin_settings`, `api_save_plugin_options` |
| Modules | `modules` (alias `plugins`): Core Modules table (always on) + Additional Modules table; `api_toggle_plugin` (GET link + `my_post_key`, refuses core/unknown ids) |
| Pages | `pages`, `pages_add`, `pages_edit`, `pages_delete`, `pages_api` (`save`, `get`, `reorder`, `check_slug`, `set_front_page`); `pages_components` registered but unhandled |
| Settings | `settings` — `output_tab_control` tabs `general`, `theme`, `developer`; each tab is its own form posting `tab=…` and only saves its own fields; redirect back to `#tab_<name>`. Theme tab (multipart) saves `ms_loading_bar` + theme options (`page=studio_settings`: Appearance incl. `theme_color`, Branding). Developer tab holds auto-sync + Demo Data: `seed_data`, `revert_default` (POST form: JS `confirm()` then `prompt()` for `RESET` fill hidden `confirm_wipe`/`confirm_text`, re-checked server-side + post check; GET still shows a fallback confirm page) via `inc/plugins/mystudio/devseed.php` |

### Security notes

- Theme slug sanitised `[^a-z0-9\-]`; `path` is passed through — containment lives in core.php.
- `api_upload_asset`: ext allow-list `png,jpg,jpeg,gif,svg,webp,ico`, `finfo` MIME check, 5 MB,
  filename forced to `<field>.<ext>`, `realpath` under `themes/`. `api_saveoptions` image upload
  lacks the MIME check (lower assurance).
- Page slugs `[a-zA-Z0-9\-_\/]`; `allowed_groups` `[^0-9,]`; module ids `[^a-z0-9\-_]`.
- `ms_pages` existence is only checked on the list page; save/edit/delete assume it exists.

## SPAs (`jscripts/mystudio/`)

- `editor.js`: Monaco 0.50.0 (jsDelivr) + emmet; config from `#msEditorConfig`
  (`data-base-url`, `data-post-key`, `data-slug`); all calls append `&action=api_*`. Multi-tab,
  dirty markers, Ctrl+S → `api_savefile` (writes + single-file sync), context-menu CRUD.
- `pagebuilder.js`: single Monaco HTML editor for `ms_pages` rows; `savePage` →
  `pages_api save`; auto-slug; preview `/<slug>?preview=1`; wraps legacy JSON content in a comment.
