# MyBB 1.8 Framework Reference

Reference for the host software. MyStudio is a plugin/theme system layered on top of MyBB,
so understanding this is required before touching the plugin.

- **Version**: MyBB 1.8.x (the `README.md` requires 1.8.38+).
- **PHP**: 8.0+ (the MyStudio plugin adds PHP 8.3 undefined-variable compat in `ms_global_start`).
- **DB**: MySQL 5.7+ / MariaDB 10.3+; abstracted through `$db` (see DB layer below).
- **Entry points**: top-level `.php` files (`index.php`, `forumdisplay.php`, `showthread.php`,
  `member.php`, `global.php`, `xmlhttp.php`, `misc.php`, …) plus `admin/index.php` for the ACP.

## Request lifecycle

1. A top-level script defines `IN_MYBB` and `THIS_SCRIPT`, then requires `global.php`.
2. `global.php` requires `inc/init.php`, which bootstraps the framework (see below).
3. `init.php` loads settings (`inc/settings.php`), connects `$db`, instantiates `$templates`,
   `$cache`, `$plugins`, `$lang`, loads the plugin system (`$plugins->load()` — requires every
   active plugin file in `inc/plugins/`), and defines URL constants.
4. `global.php` reads the theme, sets up `$lang`, the session/user, runs `global_start` /
   `global_intermediate` hooks, and renders the shell (headerinclude, header, footer).
5. The script does its work, renders templates via `eval($templates->get(...))`, and emits output.
6. `pre_output_page` hook fires on the final HTML before it's sent.

## Bootstrap: `inc/init.php`

Key globals created (in order):
- `$error_handler` — `errorHandler` (`inc/class_error.php`).
- `$mybb` — `MyBB` core object (`inc/class_core.php`). Holds `->settings`, `->user`, `->config`,
  `->input`, `->cookies`, `->cache`, and plugin-injected properties.
- `$db` — DB driver instance chosen by `$config['database']['type']`:
  `mysqli` (default), `mysql`, `mysql_pdo`, `pgsql`, `pgsql_pdo`, `sqlite`.
- `$templates` — `templates` object (`inc/class_templates.php`).
- `$cache` — `datacache` (`inc/class_datacache.php`).
- `$plugins` — `pluginSystem` (`inc/class_plugins.php`).
- `$lang` — `MyLanguage` (`inc/class_language.php`).

Constants: `MYBB_ROOT` (repo root with trailing slash), `TIME_NOW`, `TABLE_PREFIX`,
`THIS_SCRIPT`, and the `*_URL` / `*_URL_PAGED` SEO-URL constants (set per `seourls` setting).

Plugins load unless `NO_PLUGINS` is defined or `$mybb->settings['no_plugins'] == 1`.

## Plugin system (`inc/class_plugins.php`)

- **`pluginSystem::load()`** — reads the `plugins` cache (`active` list) and `require_once`s
  each `inc/plugins/{name}.php`. Active plugins register hooks at file load time (top-level
  `add_hook` calls) and/or in their `_activate()` function.
- **`add_hook($hook, $function, $priority=10, $file="")`** — `$function` may be a string function
  name or an `[object|class, 'method']` array. Lower priority runs earlier. `$file` is
  `require_once`'d before the callback. Duplicate (hook, function, priority) is a no-op.
- **`run_hooks($hook, &$arguments="")`** — runs callbacks sorted by priority. The argument is
  passed by reference. A callback that returns a truthy value **replaces** `$arguments`; one
  that mutates by reference doesn't need to return. This is the by-value-vs-by-reference
  distinction: check each hook's call site (`run_hooks('foo', $var)` → by value, must return;
  `run_hooks('foo', $array)` where callbacks take `&$arg` → by reference).
- **`is_compatible($plugin)`** — checks the `_info()['compatibility']` field against
  `$mybb->version_code` (wildcards `*` and CSV supported).

### Plugin file conventions

- File: `inc/plugins/{name}.php`. All required functions prefixed with `{name}_`.
- Guard: `if(!defined("IN_MYBB")) { die("Direct initialization of this file is not allowed."); }`
- **`{name}_info()`** (required) returns: `name, description, website, author, authorsite,
  version, guid, codename, compatibility`.
- Lifecycle: `_install()` (create tables/settings), `_is_installed()` (return bool), `_uninstall()`
  (reverse `_install`), `_activate()` (make visible — templates/etc., NO permanent structures),
  `_deactivate()` (reverse `_activate`, NO table drops). If active-then-uninstalled, `_deactivate`
  runs before `_uninstall`.

## Template system (`inc/class_templates.php`)

- **`$templates->cache($names)`** — bulk-load templates by CSV title list from the `templates`
  table for the current theme's templateset (+ master/global). Caches into `$templates->cache[]`.
- **`$templates->get($title, $eslashes=1, $htmlcomments=1)`** — fetch a template. `eslashes`
  addslashes-escapes (needed before `eval`). `htmlcomments` wraps in `<!-- start/end -->`.
- **`$templates->render($title)`** — returns `'return "...template...";'` ready for `eval`.
- Templates use `{$variable}` and `{$mybb->settings['x']}` interpolation, rendered via
  `eval("\$x = \"".$templates->get('name')."\";")`.
- **Dev mode** (`$mybb->dev_mode`): templates read from `install/resources/mybb_theme.xml`.

### Template DB schema (`mysql_db_tables.php`)

- `templates` (`tid`, `title`, `template`, `sid`, `version`, `status`, `dateline`):
  - `sid` = templateset id. `-2` = MyBB Master, `-1` = Global, `>0` = a templateset.
  - `KEY (sid, title)` for lookup by title within a set.
- `templatesets` (`sid`, `title`) — named collections of templates.
- `templategroups` (`gid`, `prefix`, `title`, `isdefault`) — group labels keyed by prefix
  (e.g. prefix `postbit` → "Post Bit"). Used to organize templates in the ACP and to map a
  template title → its group folder on disk (longest-prefix match, see `mystudio-plugin.md`).

## Theme system

### Theme DB schema (`mysql_db_tables.php`)

- `themes` (`tid`, `name`, `pid`, `def`, `properties`, `stylesheets`, `allowedgroups`):
  - `tid` theme id; `pid` parent theme id (`tid=1` = Master); `def=1` = board default.
  - `properties` = serialized array (editortheme, imgdir, tablespace, borderwidth, color,
    templateset, disporder, …). Contains the **templateset SID** linking theme↔templates.
  - `stylesheets` = serialized map of which stylesheets attach to which pages.
- `themestylesheets` (`sid`, `name`, `tid`, `attachedto`, `stylesheet`, `cachefile`,
  `lastmodified`):
  - `attachedto` = which pages the sheet applies to (e.g. `global`, `showthread`, `forumdisplay`).
  - `cachefile` = name written to `cache/themes/theme{tid}/{name}`.

### Theme import (`admin/inc/functions_themes.php`)

- **`import_theme_xml($xml, $options)`** — parses a MyBB theme XML document and imports it into
  the `themes`/`templates`/`themestylesheets` tables. **This is the function MyStudio's sync
  engine calls** after building XML from disk files. Supports options like `force_name_check`,
  `tid` (update existing), `no_templates` (stylesheets/properties only), `templateset`.
  - If a theme with the same name exists, it updates that `tid` (unless `force_name_check` set).
- **`cache_stylesheet($tid, $filename, $stylesheet)`** — write CSS to `cache/themes/`.
- **`update_theme_stylesheet_list($tid)`** — rebuild the serialized `stylesheets` property on
  the `themes` row (MyStudio calls this after writing CSS cache files).

MyStudio couples to these admin functions — see `mystudio-plugin.md` §8 (sync depends on admin
dir).

## Settings system

- Tables: `settings` (`name`, `title`, `description`, `optionscode`, `value`, `disporder`,
  `gid`), `settinggroups` (`gid`, `name`, `title`, …).
- `optionscode` types: `text`, `numeric`, `textarea`, `yesno`, `onoff`, `select`, `forumselect`,
  `forumselectsingle`, `groupselect`, `groupselectsingle`, `radio`, `checkbox`, `language`,
  `adminlanguage`, `cpstyle`, `php`.
- Access values via `$mybb->settings['name']`. After changing settings, call `rebuild_settings()`
  to regenerate `inc/settings.php` (the file `init.php` loads at the top of every request).
- **MyStudio quirk**: its `ms_*` settings are inserted with `gid=0` so they don't appear under
  ACP → Configuration. They're managed in the MyStudio admin UI. See `RULES.md` §2.

## DB abstraction (`$db`)

Methods (selected): `query`, `simple_select($table, $fields, $conds, $opts)`, `insert_query`
(returns insert id), `update_query`, `delete_query`, `fetch_array`, `fetch_field`, `num_rows`,
`escape_string`, `escape_string_like`, `table_exists`, `field_exists`, `write_query`. Always
use the abstraction — never interpolate user input into SQL. Tables use the `TABLE_PREFIX`
(e.g. `mybb_templates`).

## Key hooks (commonly used)

- `global_start`, `global_intermediate` — every frontend page.
- `pre_output_page` — final HTML, before output (MyStudio injects module assets here).
- `xmlhttp` — AJAX endpoint dispatcher (MyStudio uses this for dev auto-sync + module AJAX).
- `postbit`, `forumdisplay_end`, `showthread_end`, `member_profile_end`, `index_end`,
  `portal_end` — page-specific render hooks.
- `admin_load`, `admin_config_*`, `admin_user_*`, `admin_style_*` — ACP hooks.
- Full list: search `run_hooks(` across the codebase; MyBB also publishes a hook reference.

## Security checklist

- `verify_post_check()` on every POST form handler; `my_post_key` for AJAX tokens.
- `$db->escape_string()` all SQL interpolation; prefer `simple_select` with conditions array.
- `htmlspecialchars_uni()` / `htmlspecialchars` for HTML output of untrusted strings.
- Admin permission checks via `$mybb->admin['permissions']` / ACP module permissions in
  `module_meta.php`.
- File path ops: `realpath` containment against an allowed root (MyStudio does this for the
  editor — see `RULES.md` §7).