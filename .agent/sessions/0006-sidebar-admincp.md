# 0006 — Sidebar Admin CP link + lastpost avatar crop fix

**Date**: 2026-10-30
**Goal**: (a) fix lastposter avatar in forum rows being cropped on the right; (b) add an Admin CP
link to the theme sidebar nav, admin-group only.

## Changed (theme files, then full MyStudio sync, TID 3)

- `themes/mystudio-default/css/global.css` — removed `.ms-lastposter-info { min-width: 180px !important }`
  (added by commit 3359cff "Lastpost Width Fix", bumped from 167px). The lastpost cell is fixed at
  200px; 180px info + 8px gap + 38px avatar = 226px overflowed into `.forumbit-cat`'s
  `overflow: hidden` and clipped the avatar. Module CSS (`ms-forum-display-extras/init.php`:
  `min-width:0; flex:1`) now governs; left an explanatory comment. Margin/padding would NOT fix it —
  the cell was already over-stuffed.
- `templates/portal/pex_sidebar_admincp.html` (new) — single Admin CP sidebar link
  (`bi-gear-wide-connected`, `$l['ms_sidebar_admincp']`).
- `templates/portal/pex_sidebar_member.html` — references `{$ms_sb_admincp_section}` after modcp.
- `functions/posting-extras.php` (`ms_pex_render_page_sidebar`) — builds `$ms_sb_admincp_section`
  gated by `cancp == 1 && hide_admin_links != 1`, URL via `$config['admin_dir']` (RULES §8);
  added `$config` to globals.
- `lang/en/frontend.lang.php` — `ms_sidebar_admincp = 'Admin CP'`.

## Verified

- `header_welcomeblock_member` / `header_welcomeblock_member_admin` / `{$admincplink}` chain was
  already complete (core global.php:514-518 builds `$admincplink`; theme dropdown references it).
  Note: the whole `{$welcomeblock}` is inside `<div class="d-none">` in `header.html` — dropdown
  is dormant by design; sidebar is the real nav.
- Live role-gating (curl login as seeded users, password TestPass123!): admin sees sidebar Admin CP;
  moderator sees Mod CP but no Admin CP; member sees neither.
- New template reached DB only via **full sync** (RULES §4 — id 1971 in templates).

## Gotchas

- Theme sidebar is PHP-built (`posting-extras.php` pre_output), not template-list — new sidebar
  sections need: section template + vars in `ms_pex_render_page_sidebar` + reference in
  `pex_sidebar_member` + lang key + full sync.
- `.forumbit-cat { overflow: hidden }` (global.css:470) clips any overflowing row content.
