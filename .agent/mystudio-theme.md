# MyStudio Theme Package Format

Reference for on-disk theme packages at `themes/{slug}/`. The default theme is
`themes/mystudio-default/` (~1050 files). The MyStudio sync engine
(`inc/plugins/mystudio/core.php`, class `MyStudio`) two-way syncs these files with the MyBB
`themes`/`themestylesheets`/`templates`/`templatesets` tables. See `mystudio-plugin.md` §4 for
the sync mechanics.

> See `RULES.md` §1 — disk is the source of truth.

## Directory layout

```
themes/{slug}/
├── theme.json          manifest (name, version, properties, stylesheets[], js[])
├── default.json         saved theme option values (auto-generated)
├── css/                 stylesheets → themestylesheets rows, cached to cache/themes/theme{tid}/
├── templates/{group}/{name}.html   → templates rows (filename=title, subdir=group)
├── js/                  → deployed flat to jscripts/ on sync
├── functions/
│   ├── hooks.php        frontend hook registrations (included at global_intermediate)
│   ├── options.php      theme option definitions (returns array)
│   ├── posting-extras.php  portal feed, sidebar, quick search, likes (special-cased)
│   ├── ms_pm_count.php  unread-PM-count AJAX endpoint
│   └── modules/{id}/    theme-scoped extensions
│       ├── plugin.json  manifest
│       ├── init.php     hook registrations (globals injected)
│       ├── options.php  module option definitions (optional)
│       ├── admin.php    custom admin content (optional, included in plugin_settings)
│       ├── default.json saved module option values
│       ├── css/         auto-injected on every frontend page (served from theme dir)
│       └── js/          auto-injected on every frontend page (served from theme dir)
├── lang/{code}/*.lang.php   language packs (en/ shipped)
└── vendor/             self-hosted third-party assets (Bootstrap, BI)
```

## 1. theme.json manifest

```json
{
  "name": "MyStudio Default",        // required; matched to DB themes.name (NOT slug)
  "version": "1839",                 // MyBB version stamp; fallback "1839"
  "properties": { "editortheme":"default.css", "imgdir":"images",
                  "tablespace":"0", "borderwidth":"0", "color":"" },
  "stylesheets": [ {"name":"...","attachedto":"...","order":N}, ... ],
  "js": ["main.js"]
}
```

- **`name`** — matched to DB `themes.name` in `syncToDatabase` (excludes `tid=1` Master). Renaming
  it creates a NEW DB theme instead of updating (gotcha, `mystudio-plugin.md` §8 #19).
- **`version`** — `"1839"` for MyBB 1.8.x. Written to theme/stylesheet/template XML + `version` col.
- **`properties`** — map to MyBB theme properties: `editortheme` (SCEditor iframe CSS),
  `imgdir` (theme images dir), `tablespace`/`borderwidth` (legacy), `color` (variant key, ""=default).
  Sync also synthesizes a serialized `disporder` array from `stylesheets[].order`. Only this fixed
  set is round-tripped (gotcha #11 — extra DB properties lost on disk→DB→disk).
- **`stylesheets[]`** — `name` (filename in `css/`), `attachedto` (MyBB page selector; "" = all
  pages; else `|`-pipe list like `"forumdisplay.php|showthread.php"`), `order` (1-based disporder).
- **`js[]`** — informational; `deployJs` scans the whole `js/` dir and copies `.js` → `jscripts/`
  flat (no subdirs; doesn't delete stale — gotchas #12).

## 2. CSS layer (`css/`, 9 files)

| File | attachedto | Controls |
|------|-----------|----------|
| `color-default.css` | all | `:root` CSS custom properties — the color system. All `--tekbb-*` + `--bs-body-*` vars (accent `#2dd4bf` teal), surfaces, borders, text, links, buttons, nav/footer, shadows, "liquid glass" (`--glass-*`). `color-scheme: dark`. Order 1. |
| `editor.css` | all | SCEditor/MyBB editor container + codebuttons styling. |
| `global.css` | all | Main 58KB stylesheet. Applies `:root` to `body`, overrides BS primary buttons to accent, restyles all MyBB structural classes (`thead`,`tcat`,`trow1/2`,`tfoot`,`.tborder`), layout, nav, welcome block, postbit, forum rows, modals, glass surfaces. Bulk of visual identity. |
| `modcp.css` | `modcp.php` | Mod CP only. |
| `showthread.css` | `showthread.php` | Show-thread overrides. |
| `star_ratings.css` | `forumdisplay.php\|showthread.php` | Star rating widget. |
| `thread_status.css` | all | Thread/subforum read-unread icon classes (`forum_on/off`, `subforum_on/off`) — consumed by Forum Icons module. |
| `usercp.css` | `usercp.php\|private.php` | User CP + PMs. |
| `posting-extras.css` | (NOT in `theme.json`!) | Feed/sidebar/likes styles. Bypasses the MyBB stylesheet system — injected as a `<link>` by `hooks.php` `msdefault_inject_custom_code()` (see §5). Always emitted on every page. |

The 8 listed files sync to `themestylesheets` rows with `attachedto` selectors; MyBB emits them
only on matching pages. `posting-extras.css` is the exception (hook-injected, direct path).

## 3. Templates layer (`templates/`, 52 subdirs)

- **Filename (minus `.html`) = template title** in the `templates` table.
- **Subdir = template group**, resolved by `templategroups.prefix` longest-prefix match
  (`getTemplateFolderMap`, `mystudio-plugin.md` §8 #10). `postbit_author_user` → `postbit`.
- **`ungrouped/`** = templates matching no prefix (e.g. `headerinclude.html`, `htmldoctype.html`,
  `loginbox.html`, `modal.html`, `codebuttons.html`, `gobutton.html`, `redirect.html`).
- Subdirs are organization only on disk — `scanTemplateDir` flattens; the folder is NOT stored in
  the DB. Round-trip export re-runs prefix match, so a custom template with no prefix match (e.g.
  `pex_reply`) always re-exports to `ungrouped/` even if shipped under `posting/`.
- Top groups by count: usercp 121, modcp 110, member 84, postbit 56, showthread 55, forumdisplay 54,
  private 53, calendar 50, moderation 48, misc 32, search 26, post 23, portal 21, ungrouped 20,
  warnings 19, forumbit 18, header 16, reputation 15, global 14, newreply 13, memberlist 12,
  managegroup 12, video 10, multipage 10, index 9, … (see `mystudio-theme.md` full list in agent notes).

### Syntax used
- `{$var}`, `{$mybb->user['avatar']}`, `{$mybb->settings['bburl']}`, `{$lang->key}`,
  `{$mybb->post_code}` — arbitrary object/array access inside `{$...}`.
- Sub-template globals pre-eval'd by hooks: `{$header}`, `{$footer}`, `{$headerinclude}`,
  `{$welcomeblock}`, `{$profile_avatar_modal}`, `{$ms_logo_html}`, `{$ms_pm_badge}`,
  `{$ms_buddy_badge}`, `{$ms_pex_*}`, `{$ms_sb_*}`.
- Standard expansion idiom: `eval("\$x = \"".$templates->get('name')."\";");`.
- `<if cond then>...</else>...</if>` is **NOT** used in on-disk theme templates (PHP-side pre-eval
  instead). The pagebuilder renderer implements its OWN `<if>` parser for page content (§6.4).
- Plain `<!-- ... -->` HTML comments are just HTML.

## 4. JS layer (`js/`, 6 files)

| File | Purpose |
|------|---------|
| `main.js` (3.1KB) | `TekBB` IIFE. Forces `data-theme="dark"`+`data-bs-theme="dark"` on `<html>`, dark bg for SCEditor iframes, scroll-to-top (`.top_button`), expand/collapse (`.expcolimage` + MyBB `collapsed` cookie), clickable forum rows (`.forumbit-row[data-href]`). |
| `ms-pm-poller.js` | Polls `ms_pm_count.php` → updates `{$ms_pm_badge}`. |
| `posting-extras.js` (13.9KB) | Feed/reply/likes front-end; backed by `posting-extras.php` AJAX. |
| `quicksearch.js` (5.5KB) | Live quick-search via `ms_quicksearch` xmlhttp action. |
| `selection-quote.js` | Text-selection-to-quote on showthread. |
| `user-autocomplete.js` | @mention / username autocomplete. |

`main.js` listed in `theme.json` but all 6 deploy to `jscripts/`. `posting-extras.js`+`quicksearch.js`
are also hook-injected directly (`hooks.php`). The rest are included via `headerinclude.html`.

## 5. `functions/` layer

### `hooks.php` (19KB)
Loaded at `global_intermediate` on every frontend page. Registers hooks (global_start already
fired, so some setup functions are called directly):
- `pre_output_page` → `msdefault_inject_custom_code` — favicon (strip existing, add theme-option
  favicon), loading-bar `#ms-loader` CSS+JS, inject `posting-extras.css/.js` + `quicksearch.js`
  before `</head>`/`</body>`.
- `member_profile_end` → `msdefault_profile_avatar_modal` (own-profile avatar-change modal),
  `msdefault_profile_stat_modals` (reputation + rate + referrals modals).
- `index_end` → `msdefault_index_sidebar` (wrap `{$birthdays}` into a card).
- `showthread_start` → `msdefault_quickreply_codebuttons_showthread` (set `$GLOBALS['codebuttons']`
  via `build_mycode_inserter` so quick-reply SCEditor inits).
- `private_read` → `msdefault_quickreply_codebuttons_pm` (same for PM quick-reply).
- Direct: `msdefault_load_language()` (load `lang/en/frontend.lang.php`, interpolate `{1}` in
  `ms_footer_about_text` with board name, build `$GLOBALS['ms_logo_html']` from `site_logo`/
  `logo_icon`/`logo_text`, set sidebar defaults), `msdefault_welcomeblock_badges()` (`$ms_pm_badge`
  from `pms_unread`, `$ms_buddy_badge` from `buddyrequests` count).

### `options.php` (1.6KB) — theme option definitions
Stored in `default.json`, exposed as `{$mybb->ms_theme_options['key']}`. Types: `text`, `textarea`,
`yesno`, `select`, `color`, `numeric`, `image`, `icon_chooser`. Each has `title`,`description`,
`type`,`default`,`page` (`studio_settings`).

| Key | Type | Default |
|-----|------|---------|
| `logo_icon` | icon_chooser | `bi-brush` |
| `logo_text` | text | `My Studio` |
| `site_logo` | image (has_dimensions, default_width=200) | `""` |
| `favicon` | image | `""` |

### `posting-extras.php` (43KB) — special-cased module
Included before module loading in `ms_xmlhttp_action` (ms.php:443). Implements the portal feed,
page sidebar, quick search, likes. Frontend only (skips admin).
- `portal_start` → `ms_posting_extras_start` — ensure `ms_pex_likes` table; dispatch AJAX (`ms_action`
  GET) or prepare feed.
- `postbit` → `ms_pex_postbit` — attach like-button HTML to `$post['button_like']`.
- `pre_output_page` → `ms_pex_pre_output_page` — wrap content in `.ms-page-shell` sidebar layout
  (extract breadcrumb, inject offcanvas `<aside>`, inject new-thread modal for logged-in users).
- `xmlhttp` → `ms_pex_xmlhttp_quicksearch` — `action=ms_quicksearch` returns
  threads/posts/users/announcements JSON (respects forum view/search perms).
- `ms_pex_prepare_feed()` — query threads (visible, non-moved, respecting unviewable/inactive
  forums + `portal_announcementsfid`), render first-post via `build_postbit`, paginate 20/page,
  eval `portal` template, `output_page()` + **exit** (bypasses core portal).
- `ms_pex_render_page_sidebar($scriptName)` — member/guest/modcp/admincp sidebar, dozens of `$ms_sb_*` globals.
  Mod CP section gated by `canmodcp`, **Admin CP section gated by `cancp` + `hide_admin_links`** (uses
  `$config['admin_dir']`, session 0006). Both eval'd into section templates (`pex_sidebar_modcp`,
  `pex_sidebar_admincp`) referenced from `pex_sidebar_member`.
- `ms_pex_render_newthread_modal()` + `ms_pex_render_forum_tree()` — recursive forum tree with
  `canview`/`canpostthreads` checks.
- AJAX (`ms_pex_ajax`, post-key verified except `load_replies`/`load_likes`): `create_thread`,
  `create_reply`, `load_replies`, `delete_thread`, `delete_reply`, `toggle_like`, `load_likes`.
  Uses `PostDataHandler` for create; `Moderation` for soft-delete when enabled. Image upload to
  `uploads/posting_extras/` (`.htaccess` PHP-deny).
- `ms_pex_ensure_like_table()` — `ms_pex_likes(lid,target_type,target_id,uid,dateline; unique target_user)`.

### `ms_pm_count.php` (542B)
Standalone AJAX endpoint: `define('IN_MYBB',1); define('NO_PLUGINS',1);` then require
`../../../global.php`. Emits `{"pms_unread": N}` with `no-store` + `X-Robots-Tag: noindex`.
Called by `ms-pm-poller.js`. Bypasses plugin system for speed.

## 6. Modules (`functions/modules/`, 5 shipped)

> See `RULES.md` §6, `mystudio-plugin.md` §5. Present = enabled. Globals injected into `init.php`:
> `$ms_plugin_options`, `$ms_plugin_dir`, `$ms_plugin_id`, `$ms_theme_slug`. Options stored in
> each module's `default.json`, mirrored to `$GLOBALS['ms_<xx>_options']`.

### 6.1 ms-editor-extras (Editor Extras v2.0.0)
SCEditor enhancements: Bootstrap Icons toolbar, paste fixes, image paste/drag-drop upload, emoji
+ GIF picker, syntax highlighting, auto-save, word count, @mentions.
- Hooks: `global_end`→`ms_ee_inject_config` (highlight.js 11.9.0 CDN light+dark, `window.EditorExtras`
  config, FOUC-prevention classes); `parse_message_end`→`ms_ee_parse_bbcode` (`[table]/[tr]/[td]/[th]`
  → HTML tables, `[icon]name[/icon]` → `<i class="bi bi-name">`); `xmlhttp`→`ms_ee_xmlhttp`
  (`editorextras_upload` via `upload_attachment()`, `editorextras_proxy` download+save external
  image with **SSRF protection** blocking private/reserved IPs, `editorextras_gif_search`/`_trending`
  via Tenor/GIPHY).
- options.php (16, mostly yesno): `bootstrap_icons, themed, paste_fix, paste_strip_styles,
  image_upload, image_max_width(text,800), image_resize, emoji, gif, gif_provider(select
  tenor/giphy), gif_api_key(text), table, autosave, word_count, mention, syntax_highlight`.
  Ships a populated GIPHY key in `default.json`.
- Assets: `css/editor-extras.css`, `js/editor-extras.js` (auto-loaded from module dir).

### 6.2 ms-forum-display-extras (Forum Display Extras v1.0.0)
Last poster avatars + user info modal, column subforum layout, card-style forum listing,
usergroup color formatting.
- Hooks: `build_forumbits_forum`→`ms_fde_enrich_forum` (batch-load last-poster avatars — single
  `users` query for all `lastposteruid` in `$fcache`; swap `forumbit_depth2_forum`→`..._card` when
  `forum_layout=cards`); `forumdisplay_thread`→`ms_fde_enrich_thread` (batch thread last-poster
  avatars from `$threadcache`); `pre_output_page`→`ms_fde_inject_output` (inline CSS+JS, user
  modal fetching `xmlhttp.php?action=ms_fde_usercard&uid=N`, subforum column rewrite,
  `ms-forum-cards` class); `xmlhttp`→`ms_fde_xmlhttp_usercard` (`ms_fde_usercard` → user JSON
  after `canviewprofiles` check).
- options.php (7): `enable_lastposter_avatar, enable_thread_lastposter_avatar, enable_user_modal,
  subforum_columns(select 0/1), forum_layout(select rows/cards), cards_per_row(select 2/3/4),
  enable_usergroup_style`.
- No bundled asset files (injected inline).

### 6.3 ms-forum-icons (Forum Icons v1.0.0)
Assign Bootstrap Icons or uploaded images as forum/category icons; status-aware coloring/grayscale.
- `init.php` calls `ms_forum_icons_install()` every load → `ms_forum_icons(fid, icon_type,
  icon_value)` table + `uploads/forum_icons/` dir.
- Admin (`IN_ADMINCP`): `admin_forum_management_add/_edit`→`ms_fi_admin_form` (icon-picker row
  in the forum form, parses all `bi-*` classes from `vendor/bootstrap-icons.min.css`, picker
  modal with search); `_add_commit/_edit_commit`→`ms_fi_save_icon_data` (BI value or image upload
  to `uploads/forum_icons/forum_<fid>.<ext>`, MIME validation, 256KB cap, `.htaccess`; icons not
  saved for categories `type='c'`).
- Frontend: `pre_output_page`→`ms_fi_inject_icon_css` (BI icons replace class in `ficons_<fid>`;
  image icons set `background:url()` + grayscale/opacity for inactive).
- No options.php (per-forum admin config).

### 6.4 ms-pagebuilder (MyStudio Page Builder v2.1.0)
Standalone pages via Monaco HTML editor, clean URL routing, front-page override, per-page CSS/JS,
group permissions, template variables.
- `init.php`: admin `admin_mystudio_action_handler`→`ms_pagebuilder_ensure_tables` (creates
  `ms_pages` table — see below); frontend `misc_start`→`ms_pb_misc_start` (if `$_GET['ms_page']`
  present, require `renderer.php` + serve + exit); `index_start` (when `THIS_SCRIPT==='index.php'`)
  →`ms_pagebuilder_front_page` (read `ms_front_page` cache; `?forums` bypass; `type=portal`→portal.php;
  `type=page`+slug→serve via renderer).
- `renderer.php` — `ms_pb_serve_page($rawSlug)`:
  - **Routing**: `.htaccess` rewrites `/slug` → `misc.php?ms_page=slug`; also accepts
    `misc.php?ms_page=slug` directly. Slug sanitized to `[a-zA-Z0-9\-_\/]`.
  - **Lookup**: published pages only; admins can `?preview=1` for drafts.
  - **Group perms**: `allowed_groups` (CSV gids) — user's `usergroup`+`additionalgroups` must
    intersect, else `error_no_permission()`.
  - **Per-page CSS/JS**: `custom_css`/`custom_js` wrapped in `<style>`/`<script>` with `</style`/
    `</script>` sequences escaped (`<\/style`) to prevent tag breakout.
  - **Template variables**: page content evaluated as a MyBB template via `ms_pb_eval_template()`
    — first `<if cond then>...</else>...</if>` conditionals resolved (≤10 passes, nested-safe
    regex), then PHP `eval` to expand `{$var}` against whitelisted globals (`$mybb,$lang,$templates,
    $cache,$db,$headerinclude,$header,$footer,$welcomeblock,$pm_notice,$boardstats,$nav,$forums,
    $search`).
  - **Conditional sandbox** (`ms_pb_safe_eval_condition`): blocks function calls, backticks,
    `eval/exec/system/include/require/file_/unlink/curl_/fopen/$_GET…` via regex.
  - **Lazy global builders**: if content references `{$boardstats}`/`{$forums}`/`{$search}` and
    they're empty, build them on demand.
  - **Front page override**: `ms_front_page.type=page` replaces the forum index (bypass `?forums`).
  - Output assembles a full HTML doc with `data-bs-theme="dark"`/`data-theme="dark"`, per-page
    CSS, meta, content, per-page JS. Sets `$ms_current_page`,`$ms_page_title`,`$ms_page_content`,
    `$ms_page_css`,`$ms_page_js`,`$ms_meta_title`,`$ms_meta_desc`.
- `ms_pages` table: `pid, title, slug UNIQUE, content MEDIUMTEXT, status, meta_title,
  meta_description, allowed_groups, custom_css, custom_js, author_uid, disporder, created_at,
  updated_at`. Pages stored as DB rows rendered through the eval engine (no on-disk templates).
- No options.php (admin UI in MyStudio admin). No bundled assets.

### 6.5 ms-profile-extras (User Profile Extras v1.0.0)
Profile banner customization (upload/solid/gradient) + status updates with privacy + feed.
- `init.php` calls `ms_profile_extras_install()` → `ms_user_banners(bid, uid, type, value,
  is_active, dateline, text_color, link_color)`.
- Hooks: `member_profile_end`→`ms_profile_extras_banner` (load active banner, build
  `$GLOBALS['banner_style']` CSS: upload→`background-image:url()`, solid→`background:color`,
  gradient→`background:...`; `--banner-text-color`/`--banner-link-color` vars; on own profile
  build change-overlay + `member_profile_banner_modal` with previous-banner gallery, 12 gradient
  + 12 solid presets, color pickers; then `ms_pe_inject_latest_posts()` — 5 recent visible posts);
  `usercp_start`→`ms_profile_extras_usercp` (dispatch `ms_action` AJAX: `save_banner` upload to
  `uploads/banners/` MIME+size+`.htaccess` or URL-validate or solid hex or gradient sanitize,
  `remove_banner`, `activate_banner`, `update_banner_colors`; `get_banner` read-only).
- options.php (2): `enable_banners(yesno,1)`, `banner_max_size(numeric,2048 KB)`.
- Assets: `css/profile-extras.css`, `js/profile-extras.js`.

## 7. vendor/ (self-hosted)

| File | What |
|------|------|
| `bootstrap.min.css` (232KB) | Bootstrap 5.3.8 |
| `bootstrap.bundle.min.js` (80KB) | BS 5.3.8 JS + Popper |
| `bootstrap-icons.min.css` (86KB) | Bootstrap Icons 1.11.3 |
| `fonts/bootstrap-icons.woff` (176KB) | BI font |
| `fonts/bootstrap-icons.woff2` (130KB) | BI font |

Self-hosted, no CDN for Bootstrap/BI. `bootstrap-icons.min.css` references `./fonts/*.woff2/.woff`
relatively. Included by `headerinclude.html` via `<link>`/`<script>` relative to the theme dir.
The forum-icons admin form also references + parses this CSS to enumerate `bi-*` classes for the
picker. The ONE external CDN dep is highlight.js 11.9.0 (editor-extras, when `syntax_highlight` on).

## 8. lang/ (`lang/en/frontend.lang.php`, 9.3KB)

Loaded by `hooks.php` `msdefault_load_language()` every frontend page. Code mapping
`english`/`en`→`en` (fallback `en`). Defines `$l['ms_*']` copied onto `$lang` → usable as
`{$lang->ms_key}`. Sections: header/nav, welcome block (guest), footer (incl. `{1}` placeholder
in `ms_footer_about_text`), board stats, registration, member list, attachments, drafts, groups,
polls, reply prefixes, Mod CP, profile mod/admin, sidebar (~30 `ms_sidebar_*` keys), portal/feed,
likes, error messages (posting-extras, profile-extras, editor-extras, forum-display user cards),
reputation modals, birthdays.

## Cross-cutting notes

- **Theme options**: `functions/options.php` → `default.json` → `$mybb->ms_theme_options['key']`.
  Module options: each module's `default.json` → `$ms_plugin_options` / `$GLOBALS['ms_<xx>_options']`.
- **DB tables auto-created by the theme/modules** (all use `TABLE_PREFIX`): `ms_pex_likes`
  (posting-extras), `ms_forum_icons` (forum-icons), `ms_pages` (pagebuilder), `ms_user_banners`
  (profile-extras).
- **Upload dirs** (with `.htaccess` PHP-exec deny): `uploads/posting_extras/`,
  `uploads/forum_icons/`, `uploads/banners/`.
- **Security**: post-key verification on all write AJAX; SSRF protection in editor image-proxy;
  MIME validation on all uploads; `realpath` containment within `uploads/`; pagebuilder
  conditional sandbox blocks function calls + superglobals.
- **Dark-mode only**: `main.js` forces `data-bs-theme="dark"`; `color-default.css` sets
  `color-scheme: dark`; pagebuilder hardcodes `data-bs-theme="dark"`. No light theme shipped.