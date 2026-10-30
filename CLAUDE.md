# forumeze — MyBB 1.8.41 + MyStudio

MyBB forum running **MyStudio** (`inc/plugins/ms.php`): a file-based theme + module manager.
Load the `mybb` skill for MyBB framework work and the `mystudio` skill for anything touching
MyStudio, themes or modules. Code wins over docs; fix the skill file in the same change.

## Layout (where things live)

| Path | Holds |
|------|-------|
| `themes/{slug}/` | Theme only: `theme.json`, `default.json` (option values), `css/`, `templates/{group}/{title}.html`, `js/`, `images/`, `vendor/`. **No PHP.** |
| `inc/plugins/ms.php` | Plugin entry: lifecycle + hooks |
| `inc/plugins/mystudio/core.php` | `MyStudio` class: sync engine, import/export, options, module loader, path helpers |
| `inc/plugins/mystudio/themes/{slug}/` | Theme PHP: `hooks.php`, `posting-extras.php`, `ms_pm_count.php`, `lang/{code}/*.lang.php` |
| `inc/plugins/mystudio/modules/{id}/` | Modules: `plugin.json`, `init.php`, `default.json` (values), `css/`, `js/` |
| `admin/modules/mystudio/` | ACP module (`module_meta.php`, `mystudio.php`) |
| `admin/modules/mystudio/themes/{slug}/options.php` | Theme option schema |
| `admin/modules/mystudio/modules/{id}/options.php` | Module option schema |
| `jscripts/mystudio/` | ACP SPAs (`editor.js`, `pagebuilder.js`) |

## Rules that bite

1. Disk is truth for themes; DB is a cache rebuilt by Sync. Editor save only UPDATEs existing
   templates/CSS — a **new** template/CSS file needs a full `syncToDatabase()`.
2. Never put PHP in `themes/`. Imported ZIPs in the legacy layout (`functions/`, `lang/`) are
   relocated by `MyStudio::relocateThemeCode()`; export re-packs them.
3. `ms_*` settings use `gid=0` (no settinggroup — `ms_activate()` deletes it). The core plugin owns
   no tables; modules do (`ms_pages`, `ms_forum_icons`, `ms_user_banners`, `ms_pex_likes`).
4. Modules: `MyStudio::CORE_MODULES` (the 5 shipped ones) are always on. Any other module is
   off until activated in ACP → MyStudio → Modules (state in datacache `ms_modules`). Only
   `listActiveModules()` load, inject assets or show in the ACP sidebar.
5. Resolve the admin dir with `MyStudio::getAdminPath()` — never hardcode `admin/`.
6. `$db->insert_query`/`update_query` do **not** escape in this MyBB build — `escape_string` first.
7. Editor file ops: keep `themeBaseDir`/`cleanRelPath` + `realpath` containment + the PHP
   extension block. Anything that writes template content (sync, editor, Page Builder) must pass
   MyBB's `check_template()` — templates are `eval()`'d.
8. ACP UI: stock MyBB primitives only (no custom icons/inline styling).
9. `.gitignore` is an allowlist — new tracked paths must be added there.

## Dev environment

- Site: `http://forumeze.local` · PHP CLI: `/c/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php.exe`
  (add `-d extension_dir=<php>/ext -d extension=zip` for ZIP) · MySQL CLI:
  `/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe`.
- CLI bootstrap: `define('IN_MYBB',1); chdir(root); require 'inc/init.php';` then
  `require MYBB_ROOT.'inc/plugins/mystudio/core.php'; (new MyStudio)->syncToDatabase($slug);`
- Dev seed users (ACP → MyStudio → Studio Settings → Developer Settings → Demo Data) share
  password `TestPass123!`.

## Open items

- `themes/default/` is untracked (undecided).
- Local branch `backup/pre-scrub` still holds a leaked GIPHY key — delete once confirmed.
