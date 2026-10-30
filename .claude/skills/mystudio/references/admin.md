# MyStudio ACP module (`admin/modules/mystudio/`)

## Navigation — `module_meta.php`

- Top-level ACP section (only when plugin `ms` is active); top-nav link → `mystudio-settings`.
  `mystudio.php` hides the core sidebar box title via `extra_header` CSS.
  Sidebar: Studio Settings (`settings`, first), Manage Themes (`manage`), Import / Export
  (`import_export`), Page Manager (`pages`, if `ms-pagebuilder` is active), one `plugin_<id>`
  item per active module with options/admin, Modules (`modules`). Hooks: `admin_mystudio_menu`,
  `admin_mystudio_action_handler`.
- `mystudio_action_map()` (hookable via `admin_mystudio_action_handler`, cached) maps action →
  `active` (permission key + highlighted item); `mystudio_action_handler` uses it. Empty action →
  `settings`; unknown → `manage`. `mystudio.php` also defaults an empty action to `settings`.
- Permissions (`mystudio_admin_permissions`): `manage`, `import_export`, `settings`, `pages`,
  `modules` (activate/deactivate), `plugin_settings` (module settings) — `ms_activate()` grants
  exactly these to the admin group. `check_admin_permissions` only denies **declared** keys.
- Because `&action=` can differ from the module param (`module=mystudio-manage&action=pages_api`),
  `mystudio.php` re-runs `check_admin_permissions` for the action it will actually run, via
  `mystudio_action_map()` (unknown → `manage`). Editor api_*, theme actions → `manage`;
  `modules`/`plugins`/`api_toggle_plugin` → `modules`; `plugin_settings` and
  `api_save_plugin_options` → `plugin_settings` (the `plugin_settings` page then sets
  `$page->active_action = 'plugin_<id>'` before `output_header` for the sidebar highlight).

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
| Editor (Monaco) | `editor` page + JSON `api_filetree`, `api_readfile`, `api_filelist` (GET); `api_savefile`, `api_createfile`, `api_createfolder`, `api_deletefile`, `api_deletefolder`, `api_rename`, `api_sync`, `api_upload_asset` (POST + `my_post_key`). Slug via `ms_api_theme_slug()` (must have `themes/{slug}/theme.json`, else JSON error). Mutations return `success`, `error` (string, `implode(' ', getErrors())`) and `errors[]`; `editor.js` `apiError()` reads `error` then `errors` |
| Options | Theme options are saved by the Settings → Theme tab via `ms_save_theme_options` (preserves other `page` filters' values, validates colours). `plugin_settings`, `api_save_plugin_options` — both only for **active** modules (`ms_find_active_module` → `listActiveModules`) |
| Modules | `modules` (alias `plugins`): Core Modules table (always on) + Additional Modules table; `api_toggle_plugin` (GET link + `my_post_key`, refuses core/unknown ids) |
| Pages | `pages`, `pages_add`, `pages_edit`, `pages_delete`, `pages_api` (`save`, `get`, `reorder`, `check_slug`, `set_front_page`); `pages_components` registered but unhandled |
| Settings | `settings` — `output_tab_control` tabs `general`, `theme`, `developer`; each tab is its own form posting `tab=…` and only saves its own fields; redirect back to `#tab_<name>`. Theme tab (multipart) saves `ms_loading_bar` + theme options (`page=studio_settings`: Appearance incl. `theme_color`, Branding). Developer tab holds auto-sync + Demo Data: `seed_data`, `revert_default` (POST form: JS `confirm()` then `prompt()` for `RESET` fill hidden `confirm_wipe`/`confirm_text`, re-checked server-side + post check; GET still shows a fallback confirm page) via `inc/plugins/mystudio/devseed.php`. Missing `ms_*` settings are only inserted (+ `rebuild_settings()`) when absent from `$mybb->settings` |

### Security notes

- Theme slug sanitised `[^a-z0-9\-]` and must have `theme.json` (`ms_valid_theme_slug`); `path`
  is passed through — containment lives in core.php.
- Image uploads (`api_upload_asset` and theme-option `image` fields) share
  `ms_validate_image_upload()`: `png,jpg,jpeg,gif,webp,ico` (**no SVG** — script on the forum
  origin), `finfo` MIME must match the extension, 5 MB; filename forced to `<field>.<ext>`.
- Demo Data (`seed_data`, `revert_default`, and the `ms_devseed_*` functions themselves) require
  `ms_devseed_allowed()`: `is_super_admin` **and** a dev environment — `bburl` host `localhost`,
  `127.0.0.1`, `*.local`, `*.test`, `*.localhost`, or `$config['ms_dev_mode'] = true`. Otherwise
  the forms are replaced by a note. The wipe keeps uid 1, the acting user and every user in a
  `cancp=1` group (primary or additional).
- `pages_api save` escapes every string (`insert_query`/`update_query` don't) and rejects
  content failing `check_template()` (raw and with `<if>/<else>/</if>` tags stripped).
  The renderer re-checks before `eval()` (frontend copy `ms_pb_check_template`) and `<if>`
  conditions go through a whitelist tokenizer (`ms_pb_tokenize_condition`: vars incl.
  `->prop`/`['key']`, literals, comparison/logical ops, parens not after an operand).
- Page slugs `[a-zA-Z0-9\-_\/]`; `allowed_groups` `[^0-9,]`; module ids `[^a-z0-9\-_]`.
- `ms_pages` existence is only checked on the list page; save/edit/delete assume it exists.

## SPAs (`jscripts/mystudio/`)

- `editor.js`: Monaco 0.50.0 (jsDelivr) + emmet; config from `#msEditorConfig`
  (`data-base-url`, `data-post-key`, `data-slug`); all calls append `&action=api_*`. Multi-tab,
  dirty markers, Ctrl+S → `api_savefile` (writes + single-file sync), context-menu CRUD.
- `pagebuilder.js`: single Monaco HTML editor for `ms_pages` rows; `savePage` →
  `pages_api save`; auto-slug; preview `/<slug>?preview=1`; wraps legacy JSON content in a comment.
