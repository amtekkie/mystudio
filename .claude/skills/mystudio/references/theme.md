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
- `js/`: `main.js` (forces dark mode, collapse, clickable rows), `ms-pm-poller.js` (members only —
  included from `header_welcomeblock_member`; polls `inc/plugins/mystudio/themes/mystudio-default/ms_pm_count.php`
  every 30 s, skipped while `document.hidden`), `posting-extras.js` (AJAX → `xmlhttp.php?action=ms_pex`),
  `quicksearch.js` (`xmlhttp.php?action=ms_quicksearch`), `selection-quote.js`,
  `user-autocomplete.js`. All deploy flat to `jscripts/`.
- `vendor/`: Bootstrap 5.3.8 CSS/JS, Bootstrap Icons 1.11.3 (+ fonts). Forum-icons ACP form
  parses `bootstrap-icons.min.css` for the picker.
- Options (schema in `admin/modules/mystudio/themes/mystudio-default/options.php`):
  `logo_icon` (icon_chooser), `logo_text`, `site_logo` (image + dimensions), `favicon`.

## Theme PHP (`inc/plugins/mystudio/themes/mystudio-default/`)

- `hooks.php` (global_intermediate): includes `posting-extras.php`; runs
  `msdefault_load_language()` (lang `en`, builds `$ms_logo_html`),
  `msdefault_welcomeblock_badges()` (badges + `$ms_user_avatar` via `format_avatar()` — never output
  `{$mybb->user['avatar']}` raw) and `msdefault_cache_templates()` (one `$templates->cache()` for the
  custom `pex_*` / `member_profile_*` templates — `$templatelist` is already consumed by
  global_intermediate; add new per-page custom templates there) directly; hooks `pre_output_page`
  (`msdefault_inject_custom_code`: favicon, loading bar, posting-extras assets),
  `member_profile_end` (avatar + stat modals), `index_end` (sidebar birthdays),
  `showthread_start` / `private_read` (quick-reply codebuttons).
- `posting-extras.php`: portal feed (`portal_start` → renders and **exits**), like buttons
  (`postbit`; like counts + viewer state for every pid on the page in one query via
  `ms_pex_prime_likes()`), page shell + sidebar (`pre_output_page`, `ms_pex_render_page_sidebar` — Mod CP
  gated by `canmodcp`, Admin CP by `cancp` + `hide_admin_links`), quick search
  (`xmlhttp.php?action=ms_quicksearch`: `cansearch`, unviewable/inactive/unsearchable forums,
  `canonlyviewownthreads`, 30 req/60 s per member/IP in `cache/ms_quicksearch_flood.json`), AJAX
  `xmlhttp.php?action=ms_pex&ms_action=…` (create/delete thread/reply, load_replies, likes; lang pack
  loaded by `ms_pex_load_lang()`). Guards mirror core: `ms_pex_can_view_thread()` (canview,
  canviewthreads, canonlyviewownthreads, forum passwords), `ms_pex_forum_post_error()` /
  `ms_pex_reply_error()` (newthread/newreply checks), `ms_pex_delete_error()` (forum moderator
  `is_moderator(fid, candelete*/cansoftdelete*)`, else owner + usergroup perm + open thread +
  `edittimelimit`; owners never hard-delete threads with others' replies; first post → thread rules).
  Images need `canpostattachments` and are saved only after `validate_thread/post()`. Table
  `ms_pex_likes` (created once, flag in datacache `ms_pex_schema`); uploads `uploads/posting_extras/`. New sidebar section = template + vars in
  `ms_pex_render_page_sidebar` + reference in `pex_sidebar_member` + lang key + full sync.
- `ms_pm_count.php`: standalone JSON endpoint (`NO_PLUGINS`, `NO_ONLINE` so polling doesn't keep the
  session online) returning `pms_unread`.
- `lang/en/frontend.lang.php`: all `ms_*` strings.

## Modules (`inc/plugins/mystudio/modules/`)

| id | What | Hooks / notes |
|----|------|---------------|
| `ms-editor-extras` | SCEditor upgrades: BI toolbar, paste fixes, image upload/drag-drop, emoji/GIF (Tenor/GIPHY), tables, autosave, word count, mentions, highlight.js | `global_end` config, `parse_message_end` (`[table]`, `[icon]`), `xmlhttp` (`editorextras_upload`, `_proxy` with SSRF block, `_gif_search/_trending`). 16 options. |
| `ms-forum-display-extras` | Last-poster avatars, user card modal, subforum columns, card layout, group styling | `build_forumbits_forum`, `forumdisplay_thread`, `pre_output_page`, `xmlhttp` (`ms_fde_usercard`). 7 options. |
| `ms-forum-icons` | Bootstrap Icon / image per forum | Table `ms_forum_icons` (DDL once, flag `ms_fi_schema`); icons cached in datacache `ms_forum_icons` (rebuilt by `ms_fi_save_icon_data`). ACP `admin_forum_management_*` form + commit — bi values must match `^bi-[a-z0-9-]+$`, images PNG/JPG/GIF/WebP ≤256 KB (no SVG); an invalid upload keeps the old icon and shows an error via admin-session key `ms_fi_error`. Frontend `pre_output_page` only on index/forumdisplay. Uploads `uploads/forum_icons/` (PHP-deny `.htaccess` always ensured). |
| `ms-pagebuilder` | Custom pages (`ms_pages` table), clean URLs, front-page override | ACP creates table; `misc_start` (`?ms_page=`), `index_start` front page; `renderer.php` evaluates content as a template with its own sandboxed `<if>` parser. |
| `ms-profile-extras` | Profile banners (upload/URL/solid/gradient), latest posts | Table `ms_user_banners` (DDL once, flag `ms_pe_schema`); `member_profile_end`, `usercp_start` AJAX. Options `enable_banners`, `banner_max_size` (KB) enforced. Max 12 banners/user (older rows + files pruned); remove deletes the row + file. URL banners stored absolute (not bburl-prefixed). Latest posts honour `canonlyviewownthreads`. Uploads `uploads/banners/`. Assets also linked from `member_profile.html`. |
