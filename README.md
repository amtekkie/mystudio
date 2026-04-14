# MyStudio — Theme Engine for MyBB 1.8

> **Version 2.1.0** — File-based theme management platform for MyBB 1.8.

**MyStudio** replaces MyBB's database-only theme editing with a modern file-based workflow. Themes live on disk as editable HTML templates, CSS, JavaScript, JSON config, and PHP hooks — synced to the database automatically.

**Key features:** Monaco code editor in Admin CP · automatic file→DB sync with live reload · JSON-driven theme options with color pickers/toggles/dropdowns · mini-plugin system for theme-scoped extensions · ZIP import/export · Page Builder with clean URLs · WYSIWYG editor replacement · AES-256-CBC encrypted licensing.

**Bundled theme:** MyStudio Default — built on Bootstrap 5.3.8 + Bootstrap Icons 1.11.3 (self-hosted). Ships with 5 mini-plugins: Forum Display Extras, Forum Icons, Profile Extras, WYSIWYG Editor, Page Builder.

---

## Requirements

| | Minimum |
|---|---|
| **MyBB** | 1.8.38+ |
| **PHP** | 8.0+ with `ext-zip`, `ext-fileinfo`, `ext-openssl`, `ext-json`, `ext-curl` |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Web Server** | Apache 2.4+ with `mod_rewrite`, or Nginx |
| **Writable dirs** | `themes/`, `uploads/`, `cache/`, `jscripts/` |

---

## Installation

1. **Upload** `inc/plugins/ms.php`, `inc/plugins/mystudio/`, `admin/modules/mystudio/`, `jscripts/mystudio/`, and `themes/mystudio-default/` to your MyBB root.
2. **Set permissions** — ensure `themes/`, `uploads/`, `cache/themes/`, and `jscripts/` are writable.
3. **Activate** — Admin CP → Configuration → Plugins → Install & Activate **MyStudio**.
4. **License** — MyStudio → License → enter key and activate.
5. **Sync theme** — MyStudio → Manage Themes → click **Sync** next to MyStudio Default.
6. **Set default** — Admin CP → Themes → set the synced theme as default → hard-refresh (Ctrl+Shift+R).
7. **Configure** (optional) — Global Options for colors/layout, Header & Footer for branding, Manage Plugins to enable mini-plugins.

For Page Builder clean URLs, add to `.htaccess`:
```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-zA-Z0-9\-_/]+)$ misc.php?ms_page=$1 [L,QSA]
```

---

## Architecture

```
MyBB Core
  └── MyStudio Plugin (inc/plugins/)
        ├── ms.php              entry point, global hooks, auto-sync
        ├── mystudio/core.php   import/export/sync/file ops/options/plugins
        └── mystudio/license.php AES-256-CBC licensing
              │
Admin CP ─── admin/modules/mystudio/
              ├── module_meta.php   menu & permissions
              └── mystudio.php      all ACP pages & API endpoints
              │
Theme Layer ─ themes/{slug}/
              ├── theme.json        manifest (name, stylesheets, JS)
              ├── css/              → DB themestylesheets + cache/themes/
              ├── templates/        .html → DB templates table
              ├── js/               → deployed to jscripts/
              ├── functions/
              │   ├── hooks.php     frontend hook implementations
              │   ├── options.php   theme option definitions
              │   └── plugins/      mini-plugin directories
              ├── lang/             language packs
              ├── vendor/           Bootstrap 5.3.8, Bootstrap Icons 1.11.3
              └── default.json      saved option values
```

### Sync Engine

The sync engine converts disk files to database records:

1. **Files → XML** — reads `theme.json`, `css/*.css`, `templates/**/*.html` and builds a MyBB-compatible XML string.
2. **XML → Database** — imports via MyBB's native `import_theme_xml()`. Incremental sync diff's only changed templates.
3. **Post-sync** — deploys JS to `jscripts/`, rebuilds stylesheet cache.

**Auto-sync (dev mode):** polls for file changes via MD5 hash comparison, triggers sync + page reload. Admin-only, disable in production.

### Plugin Loading Order

```
global_start       → pre-initialize template variables
global_intermediate → load language → theme options → hooks.php → mini-plugins
pre_output_page    → inject mini-plugin CSS/JS assets → theme hooks → plugin hooks
```

---

## Admin CP

MyStudio adds a **MyStudio** section under Configuration with these pages:

| Page | Description |
|------|-------------|
| **Manage Themes** | List/sync/edit/export/delete themes. Sync button imports disk files to DB. |
| **Import / Export** | Upload ZIP themes or download existing themes as ZIP. |
| **Global Options** | Color mode (light/dark), 32-color dual palette (16 light + 16 dark), 12 quick presets, layout & effects. |
| **Header & Footer** | Logo (image/icon/text), favicon, navigation links (visual builder), footer content. |
| **Page Manager** | Create/edit standalone pages (requires Page Builder plugin). |
| **Manage Plugins** | Enable/disable mini-plugins, access per-plugin settings. |
| **Theme Editor** | Monaco Editor v0.50.0 — file tree, multi-tab, Emmet, Ctrl+S save & sync. Requires valid license. |
| **Studio Settings** | Auto-sync toggle, sync interval, max upload size. |
| **License** | Activate/deactivate license key. |

---

## Theme Structure

### theme.json (manifest)

```json
{
    "name": "MyStudio Default",
    "version": "1839",
    "properties": { "editortheme": "default.css", "imgdir": "images", "tablespace": "0", "borderwidth": "0" },
    "stylesheets": [
        { "name": "global.css", "attachedto": "", "order": 1 },
        { "name": "showthread.css", "attachedto": "showthread.php", "order": 2 }
    ],
    "js": ["main.js"]
}
```

- `attachedto`: empty = all pages, `"file.php"` = page-specific, `"a.php|b.php"` = multiple pages.
- `js`: files from `js/` deployed to `jscripts/` on sync.

### Templates

Each `.html` file in `templates/` maps to one MyBB template. Filename (without `.html`) = template name. Subdirectory = template group. Use `{$variable}` syntax for dynamic content.

### Hooks (hooks.php)

Loaded at `global_intermediate`. Registers hooks via `$plugins->add_hook()`. The default theme's hooks handle: palette CSS injection, header/footer customization, logo/favicon, navigation, loading bar, profile avatar modal, stat modals, and index sidebar.

### Language Pack

PHP files in `lang/{code}/` populating `$l['key'] = "value"` entries. Loaded via hooks with fallback chain: user language → board default → `english` → `en`.

---

## Theme Options

Defined in `functions/options.php`, rendered as auto-generated admin forms, saved to `default.json`.

### Color System

- **Color mode:** light / dark (sets `data-theme` on `<html>`)
- **Palettes:** 16 CSS custom properties per mode — body bg/color, accent, surface, border, muted, links, buttons, nav, footer
- **Quick presets:** 12 one-click schemes (Teal, Ocean, Indigo, Purple, Rose, Amber, Emerald, Crimson, Sapphire, Coral, Slate, Pink)
- **CSS variables:** `--bs-body-bg`, `--bs-body-color`, `--tekbb-accent`, `--tekbb-surface`, `--tekbb-border`, `--tekbb-link`, `--tekbb-btn-bg`, `--tekbb-nav-bg`, `--tekbb-footer-bg`, etc.

### Layout & Effects

- `show_sidebar` — board stats sidebar on forum index (2-column layout)
- `loading_bar` — accent-colored navigation loading animation

### Supported Option Types

`text`, `textarea`, `yesno`, `select`, `radio`, `color`, `numeric`, `image`, `icon_chooser`, `nav_links`, `toolbar_builder`, `preset_swatches`

---

## Mini-Plugin System

Mini-plugins are theme-scoped extensions in `functions/plugins/{id}/`. They're auto-discovered, have their CSS/JS auto-injected, and support their own options + admin pages.

### Plugin Structure

```
functions/plugins/{id}/
├── plugin.json      required — { id, name, version, description, author }
├── init.php         required — hook registrations (standard $plugins->add_hook())
├── options.php      optional — option definitions array
├── default.json     optional — default/saved option values
├── admin.php        optional — custom admin page
├── css/             optional — auto-injected stylesheets
└── js/              optional — auto-injected scripts
```

### Lifecycle

1. **Discovery** — MyStudio scans `plugins/*/plugin.json`.
2. **Enable/Disable** — toggled in `plugins_enabled.json` via Admin CP.
3. **Runtime** — enabled plugins have options loaded to `$mybb->ms_plugin_options[id]`, then `init.php` is included.
4. **Assets** — CSS/JS files auto-injected via `<link>`/`<script>` tags.

### Available Globals in init.php

`$mybb`, `$plugins`, `$ms_plugin_options` (this plugin's options), `$ms_plugin_dir`, `$ms_plugin_id`, `$ms_theme_slug`, `$mybb->ms_theme_options` (all theme options).

---

## Bundled Mini-Plugins

### Forum Display Extras (`ms-forum-display-extras`)

Enhances forum/thread listings: circular avatars on last posts, user info modal (AJAX), subforum column layout, card layout mode, usergroup color formatting.

**Options:** `enable_thread_avatars`, `enable_forum_avatars`, `enable_user_modal`, `subforum_columns`, `forum_layout` (rows/cards), `cards_per_row`, `enable_usergroup_style`.

### Forum Icons (`ms-forum-icons`)

Custom Bootstrap Icons or uploaded images per forum/category. Status-aware: accent color for "new posts", muted+opacity for "no new". Icons are managed in ACP forum edit form, stored in `ms_forum_icons` table.

### Profile Extras (`ms-profile-extras`)

Profile banner customization (upload/solid/gradient + text/link color overrides) and status updates (post/edit/delete, privacy levels: public/private/buddies, comments). AJAX actions via `usercp.php?ms_action=...`.

**DB tables:** `ms_user_banners`, `ms_user_statuses`, `ms_status_comments`.

### WYSIWYG Editor (`ms-wysiwyg`)

Replaces SCEditor with a modern rich-text editor. 31 options covering: appearance (color mode/theme), toolbar (full/minimal/custom with drag-and-drop builder), typography (font families including Google Fonts, sizes), editor size, quick reply/quick edit configs, image paste/upload, code blocks with syntax highlighting, auto-save, GIPHY integration.

**Custom BBCode:** `[table]`, `[th]`, `[highlight=color]`, `[code=language]`, `[align=direction]`.

**Image uploads** via `xmlhttp.php?action=ms_wysiwyg_upload`, stored as MyBB attachments.

### Page Builder (`ms-pagebuilder`)

Create standalone pages with Monaco HTML editor. Features: clean URL routing (`/page-slug`), front page override, per-page custom CSS/JS, user group permissions, template variables (`{$mybb->user['username']}`, `{$header}`, etc.), conditional blocks (`<if $condition then>...<else>...</if>`), SEO meta, draft/preview.

**DB table:** `ms_pages`.

---

## Sync Engine

### Full Sync

Files → XML → `import_theme_xml()` → deploy JS → rebuild CSS cache.

### Incremental Sync

When a theme already exists: diff templates (insert new / update changed / delete removed), reimport stylesheets, deploy JS, rebuild cache.

### Single-File Sync (editor save)

| File Type | Action |
|-----------|--------|
| `.css` | Update `themestylesheets` row, rebuild cache |
| `.html` | Update `templates` row (insert if new) |
| `.js` | Copy to `jscripts/` |

### Auto-Sync (Dev Mode)

Polling script computes MD5 hash of all file paths/mtimes/sizes. On change → sync → reload. Admin-only, configurable interval (default 2s).

---

## Licensing

AES-256-CBC encrypted license system validated against Blesta License Manager.

**Security layers:** AES-256-CBC encryption · HMAC-SHA256 integrity · site-bound key derivation (from DB credentials) · source file integrity check · 24-hour periodic re-validation.

**Types:** Standard (1 site) or Redistributable (unlimited sites).

**Flow:** request RSA public key → request signed license data → validate locally (domain, expiry, status) → store encrypted in DB. Re-validated every 24 hours.

---

## Developer Guide

### Creating a Theme

1. Create `themes/my-theme/` with a `theme.json` (minimum: `name` + `version`).
2. Add `css/global.css` and templates in `templates/` (minimum: `headerinclude.html`, `htmldoctype.html`, `header.html`, `footer.html`).
3. Sync in Admin CP → MyStudio → Manage → **Sync**.

Or: duplicate `themes/mystudio-default/`, edit `theme.json` name, sync. Or: import a ZIP.

### Creating a Mini-Plugin

1. Create `functions/plugins/my-plugin/` with `plugin.json` and `init.php`.
2. Register hooks in `init.php` using `$plugins->add_hook()`.
3. Add `options.php` for configurable settings (optional).
4. Add `css/` and `js/` for auto-injected assets (optional).
5. Enable in Admin CP → MyStudio → Manage Plugins.

### Adding Theme Options

Add entries to `functions/options.php`:

```php
'my_option' => [
    'title'       => 'My Option',
    'description' => 'What this controls.',
    'type'        => 'yesno',
    'default'     => '1',
    'page'        => 'global',
],
```

Access in hooks: `$mybb->ms_theme_options['my_option']`.

Access in templates: set `$GLOBALS['my_var'] = $value;` in hooks, use `{$my_var}` in templates.

---

## Security Notes

- **Editor:** CSRF token on all API calls, file extension whitelist (no PHP), path traversal prevention.
- **Image uploads:** MIME validation via `ext-fileinfo`, size limits, sanitized filenames.
- **AJAX endpoints:** permission checks, `verify_post_check()` for POST actions.
- **Licensing:** encrypted storage, HMAC integrity, site-bound keys.
- **CSS injection:** banner URLs escaped for CSS `url()` context.
- **SSRF:** image proxy validates DNS resolution against private IP ranges.
