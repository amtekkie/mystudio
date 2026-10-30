---
name: mybb
description: MyBB 1.8 framework reference — plugins, hooks, templates, themes, settings, DB layer, language packs, xmlhttp AJAX, ACP modules and security conventions. Use when writing or debugging any PHP that runs inside MyBB (inc/plugins, admin/modules, hooks, templates, datahandlers) in this repo.
---

# MyBB 1.8 (this repo: 1.8.41, PHP 8.3)

Core source is in the repo — when unsure, grep it rather than guessing:
- Hook call sites: `grep -rn "run_hooks(\"hook_name\"" --include=*.php .` (≈1250 hooks).
- Function definitions: `inc/functions*.php`, ACP helpers in `admin/inc/`.

## Request lifecycle

1. Script defines `IN_MYBB`, `THIS_SCRIPT`, optional `$templatelist`, requires `global.php`.
2. `inc/init.php`: loads `inc/config.php` + `inc/settings.php`, creates `$mybb`, `$db`,
   `$cache`, `$plugins`, `$lang`, `$templates`, `$error_handler`; requires every active plugin
   (`$plugins->load()`) unless `NO_PLUGINS`.
3. `global.php`: session/user (`$mybb->user`, `$mybb->usergroup`), theme (`$theme`), language,
   hooks `global_start` → `global_intermediate` → builds `$header`, `$footer`,
   `$headerinclude`, `$welcomeblock` → `global_end`.
4. Script work, `eval('$page = "'.$templates->get('name').'";')`, `output_page($page)` →
   `pre_output_page` hook (by-ref `&$contents`).
- `xmlhttp.php` loads a lighter `global` (no `global_intermediate`) then fires `xmlhttp`; dispatch
  on `$mybb->get_input('action')`.
- ACP: `admin/index.php` defines `IN_ADMINCP`, `MYBB_ADMIN_DIR` (with trailing slash).

## Plugins (`inc/plugins/{name}.php`)

- Guard: `if(!defined("IN_MYBB")) die("Direct initialization of this file is not allowed.");`
- Prefix every function with `{name}_`. Required `{name}_info()` → `name, description, website,
  author, authorsite, version, codename, compatibility ("18*")`.
- Lifecycle: `_install` (tables/settings) / `_is_installed` (bool) / `_uninstall`;
  `_activate` (template edits via `inc/adminfunctions_templates.php` `find_replace_templatesets`)
  / `_deactivate`. Deactivate runs before uninstall.
- Hooks registered at file top level: `$plugins->add_hook('hook', 'func', $priority=10, $file='')`.
- `run_hooks($hook, &$args)`: callbacks receive args by reference; a **truthy return replaces**
  `$args`. By-value hooks must return the value; by-ref hooks mutate in place. Check the call site.

## Templates & themes

- Tables: `templates(tid,title,template,sid,version)` — `sid -2` master, `-1` global, `>0` set;
  `templatesets`, `templategroups(prefix,title)`; `themes(tid,name,pid,def,properties,stylesheets)`
  (`properties`/`stylesheets` are `serialize()`d; `properties['templateset']` links the SID);
  `themestylesheets(sid,name,tid,attachedto,stylesheet,cachefile,lastmodified)`.
- Render: `eval("\$x = \"".$templates->get('title')."\";");` — `{$var}`, `{$mybb->settings['k']}`,
  `{$lang->key}`. Core has **no** `<if>` conditionals in templates.
- Pre-cache with `$templatelist .= ',name'` before `global.php`, or `$templates->cache('a,b')`.
- Stylesheet `attachedto`: `""`/`global` = all pages, else `script.php|other.php` (optionally
  `script.php?action=x`). CSS cache: `cache/themes/theme{tid}/{name}`; rebuild list with
  `update_theme_stylesheet_list($tid)` (`admin/inc/functions_themes.php`).
- Theme XML import: `import_theme_xml($xml, $opts)` (`admin/inc/functions_themes.php`;
  needs `admin/inc/functions.php` for `check_template`).

## Settings & cache

- `settings(name,title,description,optionscode,value,disporder,gid)` + `settinggroups`.
  Read `$mybb->settings['name']`; after writes call `rebuild_settings()`.
- `optionscode`: `text, numeric, textarea, yesno, onoff, select\nk=Label, radio, checkbox,
  forumselect(single), groupselect(single), language, php`.
- Datacache: `$cache->read('name')`, `$cache->update('name', $data)` (table `datacache`).

## DB (`$db`, `TABLE_PREFIX` auto-applied by helpers)

`simple_select($table, $fields, $where, ['order_by','order_dir','limit'])`, `fetch_array`,
`fetch_field($q,'col')`, `num_rows`, `insert_query` (returns id), `update_query($t,$arr,$where)`,
`delete_query($t,$where)`, `write_query`/`query` (raw — write `TABLE_PREFIX` yourself),
`table_exists`, `field_exists`, `add_column`, `escape_string`, `escape_string_like`.
**This build's `insert_query`/`update_query` do not escape string values** (`quote_val` only
wraps quotes) — always `$db->escape_string()` strings; cast ints with `(int)`.

## Language

`$lang->load('file')` reads `inc/languages/{lang}/file.lang.php` (`$l['key'] = '...';`); ACP
packs in `inc/languages/{lang}/admin/`. Use `$lang->sprintf($lang->key, $a)` for `{1}` args.

## Input, output, security

- Input: `$mybb->get_input('k', MyBB::INPUT_INT|INPUT_STRING|INPUT_ARRAY)`, never `$_GET`.
- CSRF: forms post `my_post_key` (`$mybb->post_code`); check `verify_post_check($key)`
  (2nd arg `true` = return bool instead of erroring, for AJAX).
- Escape output: `htmlspecialchars_uni()`. Redirects: `redirect($url, $msg)`; errors:
  `error($msg)`, `error_no_permission()`.
- Permissions: `forum_permissions($fid)`, `get_unviewable_forums()`, `$mybb->usergroup['cancp']`,
  `is_moderator($fid, 'perm')`.
- Posting: use `PostDataHandler` (`inc/datahandlers/post.php`) — `set_data`, `validate_thread()`,
  `insert_thread()`; same pattern for users (`UserDataHandler`).
- Uploads: validate extension allow-list + `finfo` MIME + size; write under `uploads/` with a
  PHP-deny `.htaccess`; `realpath` containment for any path from input.

## ACP modules (`admin/modules/{module}/`)

- `module_meta.php`: `{module}_meta()` adds sidebar items (`$page->add_menu_item`, hook
  `admin_{module}_menu`), `{module}_action_handler($action)` maps action → file + active item,
  `{module}_admin_permissions()` returns permission keys.
- Page files: `$page->add_breadcrumb_item`, `$page->output_header()`, `output_nav_tabs`,
  `new Form(url, 'post')` (auto-adds `my_post_key`), `new FormContainer`, `new Table`,
  `$form->generate_submit_button()`, `flash_message($msg,'success'|'error')` +
  `admin_redirect($url)`, `$page->output_footer()`.
- `new Form` echoes `<form>` immediately but `FormContainer` buffers rows until `end()`:
  always `$container->end()` **before** `$form->end()`, or the buttons render outside the form
  and silently do nothing. GET forms drop the action's query string — use POST.
- Admin hooks for core pages: `admin_{module}_{action}` + `_commit`, e.g.
  `admin_forum_management_edit`, `admin_forum_management_edit_commit`.

## Useful helpers

`build_postbit($post)`, `build_forumbits()`, `my_number_format`, `my_date('relative', $ts)`,
`format_name($name, $gid, $displaygid)`, `build_profile_link`, `get_user($uid)`,
`get_forum($fid)`, `get_thread($tid)`, `multipage($count,$perpage,$page,$url)`,
`build_mycode_inserter()` (editor toolbar), `output_page()`.
