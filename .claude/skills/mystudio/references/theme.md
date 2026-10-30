# Shipped theme `mystudio-default` and modules

## Theme files (`themes/mystudio-default/`)

- `theme.json`: `name` (matched to DB `themes.name`), `version` `"1839"`, `properties`
  (`editortheme, imgdir, tablespace, borderwidth, color`), `stylesheets[]`
  (`name`, `attachedto` `""` = all or `a.php|b.php`, `order`), `js[]` (informational).
- `css/`: `color-default.css` (`:root` vars `--tekbb-*`, accent `#2dd4bf`, dark only),
  `global.css` (bulk of the look), `editor.css`, `modcp.css`, `showthread.css`,
  `star_ratings.css`, `thread_status.css`, `usercp.css`; `posting-extras.css` is **not** in
  `theme.json` — `hooks.php` injects it directly.
- `templates/`: ~52 group folders; folder is organisation only (DB stores title). Custom
  templates with no prefix match (e.g. `pex_*`) re-export to `ungrouped/`. No `<if>` syntax —
  logic is pre-computed in PHP into `$GLOBALS` (`$ms_logo_html`, `$ms_pm_badge`,
  `$ms_buddy_badge`, `$ms_pex_*`, `$ms_sb_*`).
- `js/`: `main.js` (forces dark mode, collapse, clickable rows), `ms-pm-poller.js` (polls
  `inc/plugins/mystudio/themes/mystudio-default/ms_pm_count.php`), `posting-extras.js`,
  `quicksearch.js` (`xmlhttp.php?action=ms_quicksearch`), `selection-quote.js`,
  `user-autocomplete.js`. All deploy flat to `jscripts/`.
- `vendor/`: Bootstrap 5.3.8 CSS/JS, Bootstrap Icons 1.11.3 (+ fonts). Forum-icons ACP form
  parses `bootstrap-icons.min.css` for the picker.
- Options (schema in `admin/modules/mystudio/themes/mystudio-default/options.php`):
  `logo_icon` (icon_chooser), `logo_text`, `site_logo` (image + dimensions), `favicon`.

## Theme PHP (`inc/plugins/mystudio/themes/mystudio-default/`)

- `hooks.php` (global_intermediate): includes `posting-extras.php`; runs
  `msdefault_load_language()` (lang `en`, builds `$ms_logo_html`) and
  `msdefault_welcomeblock_badges()` directly; hooks `pre_output_page`
  (`msdefault_inject_custom_code`: favicon, loading bar, posting-extras assets),
  `member_profile_end` (avatar + stat modals), `index_end` (sidebar birthdays),
  `showthread_start` / `private_read` (quick-reply codebuttons).
- `posting-extras.php`: portal feed (`portal_start` → renders and **exits**), like buttons
  (`postbit`), page shell + sidebar (`pre_output_page`, `ms_pex_render_page_sidebar` — Mod CP
  gated by `canmodcp`, Admin CP by `cancp` + `hide_admin_links`), quick search (`xmlhttp`), AJAX
  `ms_action` (create/delete thread/reply, likes) via `PostDataHandler`. Table `ms_pex_likes`;
  uploads `uploads/posting_extras/`. New sidebar section = template + vars in
  `ms_pex_render_page_sidebar` + reference in `pex_sidebar_member` + lang key + full sync.
- `ms_pm_count.php`: standalone JSON endpoint (`NO_PLUGINS`) returning `pms_unread`.
- `lang/en/frontend.lang.php`: all `ms_*` strings.

## Modules (`inc/plugins/mystudio/modules/`)

| id | What | Hooks / notes |
|----|------|---------------|
| `ms-editor-extras` | SCEditor upgrades: BI toolbar, paste fixes, image upload/drag-drop, emoji/GIF (Tenor/GIPHY), tables, autosave, word count, mentions, highlight.js | `global_end` config, `parse_message_end` (`[table]`, `[icon]`), `xmlhttp` (`editorextras_upload`, `_proxy` with SSRF block, `_gif_search/_trending`). 16 options. |
| `ms-forum-display-extras` | Last-poster avatars, user card modal, subforum columns, card layout, group styling | `build_forumbits_forum`, `forumdisplay_thread`, `pre_output_page`, `xmlhttp` (`ms_fde_usercard`). 7 options. |
| `ms-forum-icons` | Bootstrap Icon / image per forum | Creates `ms_forum_icons` table on load; ACP `admin_forum_management_*` form + commit; frontend `pre_output_page` CSS. Uploads `uploads/forum_icons/`. |
| `ms-pagebuilder` | Custom pages (`ms_pages` table), clean URLs, front-page override | ACP creates table; `misc_start` (`?ms_page=`), `index_start` front page; `renderer.php` evaluates content as a template with its own sandboxed `<if>` parser. |
| `ms-profile-extras` | Profile banners (upload/solid/gradient), latest posts | Creates `ms_user_banners`; `member_profile_end`, `usercp_start` AJAX. Uploads `uploads/banners/`. Assets also linked from `member_profile.html`. |
