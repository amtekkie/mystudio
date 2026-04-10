# FMZ Studio Documentation

> **Version 2.1.0** — Comprehensive documentation for the FMZ Studio platform and FMZ Default Theme for MyBB 1.8.

---

## 1. Introduction

**FMZ Studio** is a file-based theme management platform for MyBB 1.8. It replaces the traditional database-only approach to theme development with a modern workflow where themes live on disk as editable files — HTML templates, CSS stylesheets, JavaScript, JSON configuration, and PHP hooks — that are synced to the MyBB database automatically.

### What FMZ Studio Provides

| Feature | Description |
|---------|-------------|
| **File-Based Themes** | Themes are directories on disk with human-readable files. Edit in any text editor, IDE, or the built-in Monaco editor. |
| **Automatic Sync** | File changes are detected and synced to the database — no manual XML import needed. |
| **Built-in Code Editor** | A full VS Code-like editor (Monaco Editor v0.50.0) in the Admin CP with multi-tab editing, file tree, search, and Emmet support. |
| **Theme Options System** | Define options in PHP arrays, render forms automatically, save values to JSON. Color pickers, dropdowns, toggles, icon choosers, navigation builders — all built in. |
| **Mini-Plugin Architecture** | Theme-scoped plugins with their own hooks, options, CSS, JS, and admin pages. Five bundled plugins included. |
| **Page Builder** | Create standalone pages with a Monaco HTML editor, clean URLs, SEO meta, template variables, conditional blocks, and user group permissions. |
| **WYSIWYG Editor** | A modern rich-text editor replacing SCEditor with image paste/upload, GIF search, syntax highlighting, and a customizable toolbar. |
| **Import / Export** | Package themes as ZIP files for distribution. Import ZIPs with one click. |
| **Color Palette System** | 32-color dual palette (16 light + 16 dark) with 12 quick presets and per-variable CSS override. |
| **Licensing** | AES-256-CBC + HMAC-SHA256 encrypted license system with server-side validation. |

### What is FMZ Default Theme

**FMZ Default** is the reference theme built on **Bootstrap 5.3.8** with **Bootstrap Icons 1.11.3** (self-hosted). It demonstrates every FMZ Studio feature and ships with five mini-plugins:

1. **Forum Display Extras** — avatar last posters, user modal, subforum columns, card layout
2. **Forum Icons** — custom Bootstrap Icons or uploaded images per forum
3. **Profile Extras** — profile banners, status updates with privacy levels
4. **WYSIWYG Editor** — rich-text editor with image upload, GIF search, code highlighting
5. **Page Builder** — standalone pages with Monaco editor and clean URL routing

---

## 2. System Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| **MyBB** | 1.8.38 | 1.8.38+ |
| **PHP** | 8.0 | 8.1+ |
| **PHP Extensions** | `ext-zip`, `ext-fileinfo`, `ext-openssl`, `ext-json`, `ext-curl` | Same |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ | MySQL 8.0+ |
| **Web Server** | Apache 2.4+ with `mod_rewrite` | Apache 2.4+ or Nginx |
| **Disk** | Writable `themes/`, `uploads/`, `cache/`, `jscripts/` directories | Same |
| **Browser (Admin CP)** | Chrome 80+, Firefox 78+, Edge 80+, Safari 14+ | Latest stable |

### Required PHP Extensions

| Extension | Used For |
|-----------|----------|
| `ext-zip` | Theme import/export (ZIP packaging) |
| `ext-fileinfo` | MIME type validation for image uploads |
| `ext-openssl` | AES-256-CBC encryption for licensing |
| `ext-json` | JSON encoding/decoding (theme config, API responses) |
| `ext-curl` | License API communication |

---

## 3. Installation

### Step 1: Upload Files

Upload the following to your MyBB root directory:

```
inc/plugins/fmz.php               → /mybb/inc/plugins/fmz.php
inc/plugins/fmzstudio/core.php     → /mybb/inc/plugins/fmzstudio/core.php
inc/plugins/fmzstudio/license.php  → /mybb/inc/plugins/fmzstudio/license.php
admin/modules/fmzstudio/           → /mybb/admin/modules/fmzstudio/
jscripts/fmzstudio/                → /mybb/jscripts/fmzstudio/
themes/fmz-default/                → /mybb/themes/fmz-default/
```

### Step 2: Set Permissions

Ensure these directories are writable by the web server (chmod 755 or 777):

```
themes/              — theme storage
uploads/             — image uploads
cache/themes/        — stylesheet cache
jscripts/            — deployed JS files
```

### Step 3: Activate Plugin

1. Go to **Admin CP → Configuration → Plugins**.
2. Find **FMZ Studio** and click **Install & Activate**.
3. The plugin creates 4 hidden settings (`fmz_enabled`, `fmz_max_upload_mb`, `fmz_dev_auto_sync`, `fmz_dev_sync_interval`) and required directories.

### Step 4: Activate License

1. Go to **Admin CP → FMZ Studio → License**.
2. Enter your license key and click **Activate**.
3. The license is validated against the Blesta License Manager server and stored encrypted in the database.

### Step 5: Sync & Activate Theme

1. Go to **Admin CP → FMZ Studio → Manage Themes**.
2. Click **Sync** next to **FMZ Default**.
3. Click **Set Default** to activate the theme.
4. Hard-refresh the frontend: **Ctrl+Shift+R**.

### Step 6: Configure .htaccess (Optional — for Page Builder)

If you plan to use the Page Builder with clean URLs, add these rules to your `.htaccess`:

```apache
# FMZ Page Builder — clean URLs
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-zA-Z0-9\-_/]+)$ misc.php?fmz_page=$1 [L,QSA]
```

---

## 4. Architecture Overview

FMZ Studio operates as a bridge between the filesystem and MyBB's database-driven theme system.

```
┌─────────────────────────────────────────────────────────────┐
│                      Admin Control Panel                     │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌───────────────┐  │
│  │  Manage  │ │  Editor  │ │ Options  │ │ Page Manager  │  │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘ └───────┬───────┘  │
│       │             │            │                │           │
│  ┌────▼─────────────▼────────────▼────────────────▼───────┐  │
│  │              FMZStudio Core Class (core.php)           │  │
│  │  Import │ Export │ Sync │ FileOps │ Options │ Plugins  │  │
│  └────┬───────────────────────────────────────────────────┘  │
└───────┼──────────────────────────────────────────────────────┘
        │
   ┌────▼────────────────────────────┐
   │      Sync Engine                │
   │  Files → XML → MyBB Database   │
   │  CSS → cache/themes/           │
   │  JS  → jscripts/               │
   └────┬────────────────────────────┘
        │
   ┌────▼────────────────────────────┐         ┌──────────────────────┐
   │   themes/{slug}/                │         │   MyBB Database      │
   │   ├── theme.json                │ ◄─────► │   ├── themes         │
   │   ├── css/*.css                 │  sync   │   ├── templates      │
   │   ├── templates/**/*.html       │         │   ├── templatesets   │
   │   ├── js/*.js                   │         │   └── themestylesheets│
   │   ├── functions/                │         └──────────────────────┘
   │   │   ├── hooks.php             │
   │   │   ├── options.php           │
   │   │   └── plugins/              │
   │   ├── lang/                     │
   │   ├── vendor/                   │
   │   ├── images/                   │
   │   └── default.json              │
   └─────────────────────────────────┘
```

### Data Flow

1. **Theme Files on Disk** — The source of truth. Templates (`.html`), stylesheets (`.css`), JavaScript (`.js`), configuration (`theme.json`, `options.php`), and hooks (`hooks.php`) are stored as files.

2. **Sync Engine** — Reads disk files, builds a MyBB-compatible theme XML document, and imports it into the database using MyBB's native `import_theme_xml()`. For incremental syncs, only changed templates are updated.

3. **MyBB Database** — Stores the compiled theme data (templates, stylesheets, theme properties). MyBB reads from the database at runtime.

4. **Stylesheet Cache** — CSS files are cached to `cache/themes/themeN/` for performance. Rebuilt automatically after sync.

5. **JS Deployment** — JavaScript files from `themes/{slug}/js/` are copied to `jscripts/` during sync.

6. **Frontend Loading** — At `global_intermediate`, FMZ Studio loads the active theme's language files, option values, hooks, and mini-plugins. At `pre_output_page`, mini-plugin CSS/JS assets are injected.

### Plugin Loading Order

```
global_start
  └── fmz_global_start()          → Pre-initialize template variables (PHP 8.3 compat)
global_intermediate
  └── fmz_load_theme_extras()     → Load in this order:
        1. Language files (lang/{language}/*.php)
        2. Theme options (default.json → $mybb->fmz_theme_options)
        3. Theme hooks (functions/hooks.php)
        4. Mini plugins (functions/plugins/*/init.php)
pre_output_page
  └── fmz_inject_mini_plugin_assets() → Inject CSS <link> and JS <script> tags
  └── Theme hooks (e.g. fmzdefault_inject_custom_code)
  └── Mini-plugin hooks (e.g. fmz_fde_inject_output)
```

---

## 5. File Structure Reference

### FMZ Studio Core Files

```
mybb/
├── inc/plugins/
│   ├── fmz.php                          # Plugin entry point (625 lines)
│   │                                     # Install/uninstall, global hooks, auto-sync
│   └── fmzstudio/
│       ├── core.php                     # FMZStudio class (2042 lines)
│       │                                 # Import, export, sync, file ops, options, plugins
│       └── license.php                  # FMZLicense class (503 lines)
│                                         # AES-256-CBC + HMAC encrypted licensing
│
├── admin/modules/fmzstudio/
│   ├── module_meta.php                  # ACP menu, sidebar, permissions (113 lines)
│   └── fmzstudio.php                   # ACP pages & API endpoints (3878 lines)
│
└── jscripts/fmzstudio/
    ├── editor.js                        # Monaco-based theme file editor
    ├── pagebuilder.js                   # Monaco-based page builder editor
    └── pagebuilder.css                  # Page builder UI styles
```

### Theme Directory Structure

```
themes/{slug}/
├── theme.json                           # REQUIRED — Theme manifest
├── default.json                         # Saved option values (auto-generated)
│
├── css/                                 # Stylesheets
│   ├── global.css                       # REQUIRED — Main stylesheet
│   ├── editor.css                       # Editor-specific styles
│   ├── modcp.css                        # Mod CP styles (attachedto: modcp.php)
│   ├── showthread.css                   # Thread view styles
│   ├── star_ratings.css                 # Star ratings (forumdisplay + showthread)
│   ├── thread_status.css                # Thread status icons
│   └── usercp.css                       # User CP + PM styles
│
├── templates/                           # REQUIRED — MyBB templates
│   ├── header/                          # Template group folders
│   │   └── header.html                  # Template name = filename without .html
│   ├── footer/
│   │   └── footer.html
│   ├── ungrouped/
│   │   ├── headerinclude.html
│   │   └── htmldoctype.html
│   ├── forumbit/                        # Forum listing templates
│   ├── forumdisplay/                    # Forum display page
│   ├── showthread/                      # Thread view
│   ├── postbit/                         # Post rendering
│   ├── member/                          # Profile pages
│   ├── usercp/                          # User control panel
│   ├── private/                         # Private messages
│   ├── index/                           # Forum index
│   └── ... (42 template groups total)
│
├── js/                                  # JavaScript files
│   └── main.js                          # Deployed to jscripts/ on sync
│
├── functions/                           # PHP logic
│   ├── hooks.php                        # Hook registrations & implementations
│   ├── options.php                      # Theme option definitions
│   └── plugins/                         # Mini-plugin directory
│       ├── plugins_enabled.json         # Enable/disable state per plugin
│       ├── fmz-forum-display-extras/
│       ├── fmz-forum-icons/
│       ├── fmz-profile-extras/
│       ├── fmz-wysiwyg/
│       └── fmz-pagebuilder/
│
├── vendor/                              # Third-party libraries
│   ├── bootstrap.min.css                # Bootstrap 5.3.8
│   ├── bootstrap.bundle.min.js
│   ├── bootstrap-icons.min.css          # Bootstrap Icons 1.11.3
│   └── fonts/                           # Icon font files
│
├── lang/                                # Language packs
│   └── en/
│       └── frontend.lang.php            # 80+ language strings
│
└── images/                              # Theme images
    └── uploads/                         # Uploaded logos, favicons
```

### Mini-Plugin Directory Structure

```
functions/plugins/{plugin-id}/
├── plugin.json                          # REQUIRED — Plugin manifest
├── init.php                             # REQUIRED — Hook registrations & logic
├── options.php                          # OPTIONAL — Option definitions
├── default.json                         # OPTIONAL — Default option values
├── admin.php                            # OPTIONAL — Custom admin page
├── css/
│   └── *.css                            # OPTIONAL — Auto-injected stylesheets
└── js/
    └── *.js                             # OPTIONAL — Auto-injected scripts
```

---

## 6. Admin Control Panel

FMZ Studio adds a top-level **FMZ Studio** section to the Admin CP navigation (position 50) with a brush icon. It appears only when the FMZ plugin is active.

### Sidebar Navigation

| # | Menu Item | Route | Icon |
|---|-----------|-------|------|
| 1 | Manage Themes | `fmzstudio-manage` | `bi-palette2` |
| 2 | Import / Export | `fmzstudio-import_export` | `bi-arrow-left-right` |
| 3 | Global FMZ Options | `fmzstudio-options` | `bi-sliders` |
| 4 | Header & Footer | `fmzstudio-options_header_footer` | `bi-layout-text-window` |
| 5 | Page Manager | `fmzstudio-pages` | `bi-file-earmark-richtext` |
| 6 | *Plugin Settings* | `fmzstudio-plugin_settings` | *(per plugin)* |
| 7 | Manage Plugins | `fmzstudio-plugins` | `bi-plug` |
| 8 | Studio Settings | `fmzstudio-settings` | `bi-gear` |
| 9 | License | `fmzstudio-license` | `bi-key` |

**Note:** "Page Manager" only appears when the Page Builder mini-plugin is enabled. Plugin Settings entries are generated dynamically for each enabled mini-plugin that has an `options.php` or `admin.php`.

The sidebar fires the `admin_fmzstudio_menu` hook for extensibility.

---

### 6.1 Manage Themes

**Route:** `fmzstudio-manage`

The default landing page. Displays a table of all themes — both synced (in database) and unsynced (on disk only).

| Column | Description |
|--------|-------------|
| **Theme** | Theme name from `theme.json` (clickable — opens editor) |
| **Status** | Default, Installed, or Not Synced |
| **Options** | PopupMenu dropdown with available actions |

**Options Dropdown:**

| Action | Description |
|--------|-------------|
| **Edit Theme** | Opens the built-in Monaco editor for the theme |
| **Sync** | Syncs disk files to the database (templates, CSS, JS) |
| **Set Default** | Makes this theme the board default |
| **Deactivate** | Removes default status, falls back to lowest available theme |
| **Delete** | Removes theme from database and optionally from disk |
| **Convert to Disk** | Extracts a database-only theme to the `themes/` directory |

**Broken Theme Detection:** The page scans for themes with missing `theme.json`, empty template directories, JSON parse errors, or permission issues and displays warnings.

---

### 6.2 Import / Export

**Route:** `fmzstudio-import_export`

**Export Section:**
- Lists all synced themes with a **Download ZIP** button each.
- Clicking downloads the entire `themes/{slug}/` directory as a `.zip` file.
- If the theme exists only in the database, it is extracted to disk first via `extractThemeToDisk()`.

**Import Section:**
- **Upload a ZIP** — file input for `.zip` files (max size configurable in Studio Settings).
- **Parent Theme** — dropdown to select the parent theme (defaults to Master Style).
- Clicking **Import** extracts the ZIP, validates the theme structure, copies to `themes/{slug}/`, syncs to database, and deploys JS.

**ZIP Requirements:**
- Must contain a directory with a valid `theme.json` file.
- `theme.json` can be at the ZIP root or one folder deep.
- Must have a `templates/` directory with at least one `.html` file.

---

### 6.3 Global FMZ Options

**Route:** `fmzstudio-options`

Manages color mode, color palettes, layout, and effects for the active theme. Values are saved to `themes/{slug}/default.json`.

**Sections:**

| Section | Options |
|---------|---------|
| **Color Mode** | Light / Dark radio toggle |
| **Quick Presets** | 12 one-click color schemes (see [Quick Presets](#83-quick-presets)) |
| **Light Palette** | 16 color pickers mapping to CSS custom properties |
| **Dark Palette** | 16 color pickers mapping to CSS custom properties |
| **Layout & Effects** | Stats sidebar toggle, loading bar toggle |

See [Theme Options System](#8-theme-options-system) for the complete option reference.

---

### 6.4 Header & Footer

**Route:** `fmzstudio-options_header_footer`

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| **Header Style** | Select | `default` | Default (logo left, nav right), Centered (logo above nav), Minimal (text-only) |
| **Logo Icon** | Icon Chooser | `bi-chat-square` | Bootstrap Icon for the logo (searchable grid) |
| **Logo Text** | Text | *(empty)* | Custom text next to the icon. Empty = board name. |
| **Upload Logo Image** | Image | *(empty)* | Upload PNG/JPG/GIF/SVG/WebP. When set, overrides icon + text. Supports width/height. |
| **Favicon** | Image | *(empty)* | Upload .ico/.png/.svg. Injected as `<link rel="icon">`. |
| **Custom Nav Links** | Nav Builder | *(empty)* | Repeater: text + URL + Bootstrap icon. Drag to reorder. |
| **Footer Text** | Textarea | *(empty)* | HTML content for footer. |
| **Footer About Text** | Textarea | *(empty)* | "About" section. Supports `{boardname}` placeholder. |

**Logo Priority:** Image logo → Icon + Text logo → Board name fallback.

---

### 6.5 Page Manager

**Route:** `fmzstudio-pages`

Requires the **Page Builder** mini-plugin to be enabled. See [Page Builder](#105-page-builder) for full documentation.

| Feature | Description |
|---------|-------------|
| **Page List** | Table with drag-to-reorder, showing title, slug, status, author, last updated |
| **Add Page** | Monaco HTML editor with sidebar for title, slug, status, meta, CSS, JS, permissions |
| **Edit Page** | Same editor pre-loaded with existing data |
| **Delete Page** | Confirmation dialog |
| **Front Page** | Dropdown: Default (Forum Index) / Portal / Any published page |
| **Clean URLs** | Pages accessible at `/page-slug` via mod_rewrite |

**Database Table:** `fmz_pages` — columns: `pid`, `title`, `slug`, `content`, `status`, `meta_title`, `meta_description`, `allowed_groups`, `custom_css`, `custom_js`, `author_uid`, `created_at`, `updated_at`, `disporder`.

---

### 6.6 Manage Plugins

**Route:** `fmzstudio-plugins`

Lists all mini-plugins discovered in `themes/{slug}/functions/plugins/`. Displayed as two separate tables:

**Active Plugins Table:**

| Column | Description |
|--------|-------------|
| **Plugin** | Plugin name, version, author, and description |
| **Controls** | Text links: Deactivate, Settings (if `options.php` exists) |

**Inactive Plugins Table:**

| Column | Description |
|--------|-------------|
| **Plugin** | Plugin name, version, author, and description |
| **Controls** | Text links: Activate, Settings (if `options.php` exists) |

**Activate / Deactivate** — Toggles the plugin in `plugins_enabled.json`. Enabled plugins have their `init.php` loaded at runtime.

**Settings** — Links to the plugin's settings page if `options.php` exists.

---

### 6.7 Plugin Settings

**Route:** `fmzstudio-plugin_settings&plugin={id}`

Auto-generated form from the plugin's `options.php` file. Supports all option types:

| Type | Control |
|------|---------|
| `text` | Single-line input |
| `textarea` | Multi-line textarea |
| `yesno` | Radio buttons (Yes/No) |
| `select` | Dropdown |
| `radio` | Radio group |
| `color` | Color picker |
| `numeric` | Number input |
| `image` | File upload with preview |
| `icon_chooser` | Searchable Bootstrap Icon grid |
| `nav_links` | Visual link builder |
| `toolbar_builder` | Drag-and-drop toolbar button builder |
| `preset_swatches` | Color preset selection |

Values are saved to the plugin's `default.json` file.

---

### 6.8 Theme Editor

**Route:** `fmzstudio-manage&action=editor&slug={slug}`

A full-page IDE powered by **Monaco Editor v0.50.0** (loaded from CDN). Requires a valid license.

**Features:**

| Feature | Description |
|---------|-------------|
| **File Tree** | Sidebar with search filter, collapsible folder tree |
| **Multi-Tab Editing** | Open multiple files in tabs with dirty indicator (•) |
| **Syntax Highlighting** | HTML, CSS, JavaScript, JSON, PHP, XML, Markdown |
| **Emmet Support** | HTML/CSS abbreviation expansion |
| **Autocomplete** | Context-aware code completion |
| **Find & Replace** | Ctrl+F / Ctrl+H |
| **Multiple Cursors** | Ctrl+D (select next occurrence), Alt+Click |
| **Resizable Sidebar** | Drag handle between file tree and editor |
| **Context Menu** | Right-click: New File, New Folder, Rename, Delete |
| **Status Bar** | VS Code-style blue bar with sync status, cursor position, language |
| **Toast Notifications** | Success/error/info feedback for operations |
| **Save & Sync** | Ctrl+S saves to disk AND syncs to database in one operation |

**Keyboard Shortcuts:**

| Shortcut | Action |
|----------|--------|
| Ctrl+S | Save & sync to database |
| Ctrl+W | Close current tab |
| Ctrl+Z / Ctrl+Shift+Z | Undo / Redo |
| Ctrl+F | Find |
| Ctrl+H | Find & Replace |
| Ctrl+D | Select next occurrence |
| Alt+↑/↓ | Move line up/down |

**Security:**
- All file operations go through JSON API endpoints with CSRF token verification.
- Write operations require a valid license.
- File extension whitelist blocks saving executable files (PHP, PHTML, PHAR).
- Path traversal is prevented by canonicalization.

**Allowed file extensions:** `html, htm, css, js, json, txt, md, xml, svg, ini, yml, yaml, less, scss, map, csv, log, tpl, mustache, hbs`

---

### 6.9 Studio Settings

**Route:** `fmzstudio-settings`

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| **Enable FMZ Studio** | Yes/No | Yes | Master on/off switch for the entire plugin |
| **Max Upload Size (MB)** | Numeric | 20 | Maximum ZIP file size for theme import (1–500 MB) |
| **Auto-Sync** | Yes/No | No | Automatically sync file changes to database on page load |
| **Sync Interval** | Numeric | 2 | File change check interval in seconds (1–60) |

**Auto-Sync** is a development feature. When enabled, a polling script checks for file modifications by computing an MD5 hash of all file paths, modification times, and sizes. On change detection, a full sync is triggered and the page reloads. This is intended for development only — disable in production.

---

### 6.10 License

**Route:** `fmzstudio-license`

Displays a standard MyBB admin form for license management.

**If no license is active:**
- Text input for license key.
- **Activate** button — validates against the licensing API and stores encrypted data.

**If license is active:**
- License information table: Key (masked), Status (badge), Domain, Type, Expiry.
- **Deactivate** button — releases the license from this domain.

License data is stored encrypted in the MyBB settings table. See [Licensing System](#12-licensing-system) for details.

---

## 7. FMZ Default Theme

The FMZ Default theme is a complete MyBB frontend theme built on Bootstrap 5.3.8 with 42 template groups, 7 stylesheets, and comprehensive hook-based customization.

### 7.1 theme.json — Theme Manifest

Every FMZ Studio theme requires a `theme.json` file at its root. This file defines the theme's identity, stylesheets, and JavaScript files.

```json
{
    "name": "FMZ Default",
    "version": "1839",
    "properties": {
        "editortheme": "default.css",
        "imgdir": "images",
        "tablespace": "0",
        "borderwidth": "0",
        "color": ""
    },
    "stylesheets": [
        { "name": "editor.css",        "attachedto": "",                                "order": 2 },
        { "name": "global.css",        "attachedto": "",                                "order": 3 },
        { "name": "modcp.css",         "attachedto": "modcp.php",                       "order": 4 },
        { "name": "showthread.css",    "attachedto": "showthread.php",                  "order": 5 },
        { "name": "star_ratings.css",  "attachedto": "forumdisplay.php|showthread.php", "order": 6 },
        { "name": "thread_status.css", "attachedto": "",                                "order": 7 },
        { "name": "usercp.css",        "attachedto": "usercp.php|private.php",          "order": 8 }
    ],
    "js": ["main.js"]
}
```

**Field Reference:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | **Yes** | Display name of the theme. Used as the identifier in the database. |
| `version` | string | **Yes** | Theme version. For MyBB themes, typically the MyBB version number (e.g., `"1839"` for 1.8.39). |
| `properties` | object | No | MyBB theme properties (editortheme, imgdir, tablespace, borderwidth, color). |
| `stylesheets` | array | No | Array of stylesheet objects. Each has `name` (filename in `css/`), `attachedto` (pipe-separated PHP filenames or empty for global), and `order` (load order). |
| `js` | array | No | Array of JS filenames from `js/` to deploy to `jscripts/` during sync. |

**Minimum valid theme.json:**

```json
{
    "name": "My Theme",
    "version": "1839"
}
```

---

### 7.2 Stylesheets

Stylesheets live in `css/` and are registered in `theme.json`. The `attachedto` field controls which pages load each stylesheet.

| Stylesheet | Attached To | Purpose |
|------------|-------------|---------|
| `global.css` | *(all pages)* | Main theme styles — layout, colors, typography, components |
| `editor.css` | *(all pages)* | Post editor styles |
| `modcp.css` | `modcp.php` | Moderator Control Panel specific styles |
| `showthread.css` | `showthread.php` | Thread view — postbit, quotes, code blocks |
| `star_ratings.css` | `forumdisplay.php`, `showthread.php` | Thread star rating system |
| `thread_status.css` | *(all pages)* | Thread status indicator icons |
| `usercp.css` | `usercp.php`, `private.php` | User CP and PM styles |

**`attachedto` syntax:**
- Empty string `""` = load on all pages (global)
- Single page: `"modcp.php"`
- Multiple pages: `"forumdisplay.php|showthread.php"` (pipe-separated)

After sync, stylesheets are cached to `cache/themes/themeN/` as optimized files.

---

### 7.3 Template System

Templates are stored as `.html` files in `templates/`. The directory structure maps to MyBB template groups:

```
templates/
├── header/
│   └── header.html           → template name: "header"
├── footer/
│   └── footer.html           → template name: "footer"
├── ungrouped/
│   ├── headerinclude.html    → template name: "headerinclude"
│   └── htmldoctype.html      → template name: "htmldoctype"
├── forumbit/
│   ├── forumbit_depth1_cat.html
│   ├── forumbit_depth1_forum.html
│   └── forumbit_depth2_forum.html
└── ...
```

**Template naming rules:**
- File name without `.html` = template name in the database.
- Subdirectory name = template group (organizational, used for ACP grouping).
- Use `{$variable}` syntax for dynamic content (standard MyBB template variables).
- Include other templates with `{$variable}` where the variable was set via `eval()` in PHP.

**Required templates (minimum for a functional theme):**
- `templates/ungrouped/headerinclude.html` — CSS/JS includes
- `templates/ungrouped/htmldoctype.html` — HTML document wrapper
- `templates/header/header.html` — Page header
- `templates/footer/footer.html` — Page footer

---

### 7.4 JavaScript Engine (main.js)

The FMZ Default theme includes a single `main.js` file deployed to `jscripts/` during sync. It defines the `TekBB` client-side module.

**Features:**
- Color mode switching (light/dark toggle with `localStorage` persistence)
- Smooth scroll positioning
- Dynamic UI enhancements
- Available as `window.TekBB` for mini-plugin extensions

---

### 7.5 Hooks (hooks.php)

The theme's `functions/hooks.php` (718 lines) registers four hooks and includes two direct-call functions:

| Hook | Function | Purpose |
|------|----------|---------|
| `pre_output_page` | `fmzdefault_inject_custom_code()` | Injects palette CSS overrides, header styles, logo/favicon, navigation links, footer content, loading bar, and avatar modal CSS/JS into the page |
| `member_profile_end` | `fmzdefault_profile_avatar_modal()` | Adds avatar change modal to profile pages (upload, URL, or gallery) |
| `member_profile_end` | `fmzdefault_profile_stat_modals()` | Adds reputation and referral stat modals with inline AJAX rating |
| `index_end` | `fmzdefault_index_sidebar()` | Renders stats sidebar (board stats, who's online, birthdays) when enabled |
| *(direct call)* | `fmzdefault_load_language()` | Loads language files for all frontend pages |

**How `fmzdefault_inject_custom_code()` works:**

1. Reads theme options from `$mybb->fmz_theme_options`.
2. Generates a `<style>` block with CSS custom property overrides for any color that differs from the default palette.
3. Injects header class (`fmz-header-default`, `fmz-header-centered`, `fmz-header-minimal`).
4. Processes logo (image > icon+text > board name) and favicon.
5. Builds custom navigation `<li>` elements from JSON link data.
6. Processes footer text (with `{boardname}` replacement).
7. Injects loading bar JS/CSS if enabled.
8. Inserts everything via `str_replace()` on `</head>` and template variable placeholders.

---

### 7.6 Language Pack

Language files live in `lang/{language_code}/` as PHP files that populate `$l` array entries.

**File:** `lang/en/frontend.lang.php` (78 lines, 80+ keys)

**Key categories:**

| Category | Example Keys |
|----------|-------------|
| Navigation | `fmz_nav_forums`, `fmz_nav_more`, `fmz_nav_toggle` |
| Welcome (Guest) | `fmz_welcome_subtitle`, placeholders for username/password |
| Footer | `fmz_footer_about`, `fmz_footer_quick_links`, `fmz_footer_home`, `fmz_footer_powered_by` |
| Profile | Avatar modal strings, stat modal strings |
| Sidebar | Board stats labels, who's online labels |

**Language loading fallback chain:** User language → Board default language → `english` → `en`

**Using language strings in templates:**

In `hooks.php`, set a global:
```php
$GLOBALS['fmz_my_text'] = $lang->fmz_my_custom_key;
```

In templates:
```html
<p>{$fmz_my_text}</p>
```

---

### 7.7 Vendor Dependencies

Self-hosted in `vendor/` to avoid CDN dependencies:

| File | Library | Version |
|------|---------|---------|
| `bootstrap.min.css` | Bootstrap CSS | 5.3.8 |
| `bootstrap.bundle.min.js` | Bootstrap JS (includes Popper) | 5.3.8 |
| `bootstrap-icons.min.css` | Bootstrap Icons | 1.11.3 |
| `fonts/` | Bootstrap Icons font files (WOFF2) | 1.11.3 |

Referenced in `templates/ungrouped/headerinclude.html`:
```html
<link rel="stylesheet" href="{$mybb->asset_url}/themes/fmz-default/vendor/bootstrap.min.css">
<link rel="stylesheet" href="{$mybb->asset_url}/themes/fmz-default/vendor/bootstrap-icons.min.css">
<script src="{$mybb->asset_url}/themes/fmz-default/vendor/bootstrap.bundle.min.js"></script>
```

---

## 8. Theme Options System

Theme options are defined in `functions/options.php` as a PHP array and rendered automatically as admin forms. Values are stored in `default.json`.

### 8.1 Color Mode

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `color_mode` | radio | `light` | Controls which palette is applied. Sets `data-theme` attribute on `<html>`. |

The theme's CSS uses `[data-theme="light"]` and `[data-theme="dark"]` selectors to switch between palettes.

---

### 8.2 Color Palettes

Each palette has 16 color variables. Only colors that differ from defaults generate CSS overrides.

**Light Palette (`palette_light` group):**

| Option Key | CSS Variable | Default | Purpose |
|------------|-------------|---------|---------|
| `light_body_bg` | `--bs-body-bg` | `#f5f6fa` | Page background |
| `light_body_color` | `--bs-body-color` | `#1e293b` | Default text color |
| `light_accent` | `--tekbb-accent` | `#0d9488` | Primary accent / brand color |
| `light_accent_hover` | `--tekbb-accent-hover` | `#0f766e` | Accent hover state |
| `light_heading_bg` | `--tekbb-heading-bg` | `#0d9488` | Table/card heading backgrounds |
| `light_surface` | `--tekbb-surface` | `#ffffff` | Card/panel backgrounds |
| `light_border` | `--tekbb-border` | `#dbefed` | Borders and dividers |
| `light_muted` | `--tekbb-muted` | `#64748b` | Secondary/muted text |
| `light_text_inv` | `--tekbb-text-inv` | `#ffffff` | Text on accent backgrounds |
| `light_link` | `--tekbb-link` | `#0d9488` | Link color |
| `light_link_hover` | `--tekbb-link-hover` | `#0f766e` | Link hover color |
| `light_btn_bg` | `--tekbb-btn-bg` | `#0d9488` | Button background |
| `light_btn_hover` | `--tekbb-btn-hover` | `#0f766e` | Button hover background |
| `light_nav_bg` | `--tekbb-nav-bg` | `#ffffff` | Navigation bar background |
| `light_footer_bg` | `--tekbb-footer-bg` | `#f1f5f9` | Footer background |
| `light_footer_color` | `--tekbb-footer-color` | `#475569` | Footer text color |

**Dark Palette (`palette_dark` group):**

| Option Key | CSS Variable | Default | Purpose |
|------------|-------------|---------|---------|
| `dark_body_bg` | `--bs-body-bg` | `#0f172a` | Page background |
| `dark_body_color` | `--bs-body-color` | `#e2e8f0` | Default text color |
| `dark_accent` | `--tekbb-accent` | `#2dd4bf` | Primary accent |
| `dark_accent_hover` | `--tekbb-accent-hover` | `#5eead4` | Accent hover |
| `dark_heading_bg` | `--tekbb-heading-bg` | `#0d9488` | Heading backgrounds |
| `dark_surface` | `--tekbb-surface` | `#1e293b` | Card/panel backgrounds |
| `dark_border` | `--tekbb-border` | `#122d3b` | Borders |
| `dark_muted` | `--tekbb-muted` | `#94a3b8` | Muted text |
| `dark_text_inv` | `--tekbb-text-inv` | `#ffffff` | Inverted text |
| `dark_link` | `--tekbb-link` | `#2dd4bf` | Links |
| `dark_link_hover` | `--tekbb-link-hover` | `#5eead4` | Link hover |
| `dark_btn_bg` | `--tekbb-btn-bg` | `#2dd4bf` | Buttons |
| `dark_btn_hover` | `--tekbb-btn-hover` | `#5eead4` | Button hover |
| `dark_nav_bg` | `--tekbb-nav-bg` | `#1e293b` | Navigation background |
| `dark_footer_bg` | `--tekbb-footer-bg` | `#1e293b` | Footer background |
| `dark_footer_color` | `--tekbb-footer-color` | `#94a3b8` | Footer text |

---

### 8.3 Quick Presets

12 one-click color schemes that set 8 accent-related colors for both light and dark palettes:

| Preset | Light Accent | Dark Accent | Applied To |
|--------|-------------|-------------|------------|
| **Teal** | `#0d9488` | `#2dd4bf` | accent, accent_hover, heading_bg, link, link_hover, btn_bg, btn_hover (×2 modes) |
| **Ocean** | `#0369a1` | `#38bdf8` | Same 8 properties |
| **Indigo** | `#4338ca` | `#818cf8` | Same |
| **Purple** | `#7e22ce` | `#c084fc` | Same |
| **Rose** | `#be123c` | `#fb7185` | Same |
| **Amber** | `#b45309` | `#fbbf24` | Same |
| **Emerald** | `#059669` | `#34d399` | Same |
| **Crimson** | `#dc2626` | `#f87171` | Same |
| **Sapphire** | `#1d4ed8` | `#60a5fa` | Same |
| **Coral** | `#c2410c` | `#fb923c` | Same |
| **Slate** | `#475569` | `#94a3b8` | Same |
| **Pink** | `#db2777` | `#f472b6` | Same |

---

### 8.4 Layout & Effects

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `show_sidebar` | Yes/No | No | Show stats sidebar on forum index (Board Stats, Who's Online, Birthdays) |
| `loading_bar` | Yes/No | Yes | Show animated loading bar during page navigation |

**Stats Sidebar:** When enabled, the forum index uses a two-column layout: left (75%) for forum listing, right (25%) for sidebar cards with accent-colored headers and Bootstrap Icons.

**Loading Bar:** A thin accent-colored bar animating across the top of the page during navigation. Only shows during page transitions, not initial load. If the accent color matches the background, the bar won't be visible.

---

### 8.5 Header & Footer Options

See [6.4 Header & Footer](#64-header--footer) for the complete reference.

---

### 8.6 CSS Custom Properties Reference

The theme uses CSS custom properties for all dynamic styling. These can be used in any custom CSS or template:

```css
/* Background & Text */
var(--bs-body-bg)           /* Page background */
var(--bs-body-color)        /* Default text color */

/* Accent Colors */
var(--tekbb-accent)         /* Primary brand color */
var(--tekbb-accent-hover)   /* Accent hover state */
var(--tekbb-heading-bg)     /* Table/card heading background */

/* Surfaces & Borders */
var(--tekbb-surface)        /* Card/panel background */
var(--tekbb-border)         /* Borders and dividers */
var(--tekbb-muted)          /* Secondary/muted text */
var(--tekbb-text-inv)       /* Text color on accent backgrounds */

/* Links */
var(--tekbb-link)           /* Link color */
var(--tekbb-link-hover)     /* Link hover color */

/* Buttons */
var(--tekbb-btn-bg)         /* Button background */
var(--tekbb-btn-hover)      /* Button hover background */

/* Navigation */
var(--tekbb-nav-bg)         /* Navbar background */

/* Footer */
var(--tekbb-footer-bg)      /* Footer background */
var(--tekbb-footer-color)   /* Footer text color */
```

**Using in custom CSS:**

```css
.my-custom-card {
    background: var(--tekbb-surface);
    border: 1px solid var(--tekbb-border);
    color: var(--bs-body-color);
}

.my-custom-card .card-header {
    background: var(--tekbb-heading-bg);
    color: var(--tekbb-text-inv);
}

.my-custom-card a {
    color: var(--tekbb-link);
}
.my-custom-card a:hover {
    color: var(--tekbb-link-hover);
}
```

---

## 9. Mini-Plugin System

### 9.1 Architecture

Mini-plugins are theme-scoped extensions that live inside a theme's `functions/plugins/` directory. Unlike traditional MyBB plugins (which are global), mini-plugins are tied to a specific theme and only load when that theme is active.

**Key properties:**
- **Self-contained** — each plugin is a directory with its own manifest, code, options, and assets.
- **Auto-discovered** — FMZ Studio scans `functions/plugins/*/plugin.json` to find plugins.
- **Auto-injected assets** — CSS and JS files in the plugin's `css/` and `js/` directories are automatically added to all frontend pages when the plugin is enabled. No manual registration needed.
- **Runtime hooks** — plugins register MyBB hooks in `init.php` and they work exactly like standard MyBB plugin hooks.
- **Options system** — define options in PHP, render forms automatically, save to JSON.
- **Admin pages** — plugins can define custom admin pages via `admin.php`.

### 9.2 Plugin Lifecycle

```
Discovery (ACP → Manage Plugins)
  └── Scan functions/plugins/*/plugin.json
  └── List with name, version, features, enable/disable

Enable
  └── Set plugin_id: true in plugins_enabled.json
  └── init.php is loaded on every page load

Runtime (every page load)
  └── fmz_load_theme_extras() calls loadMiniPlugins()
      └── For each enabled plugin:
          1. Read plugin's default.json → expose as $mybb->fmz_plugin_options[plugin_id]
          2. Set $fmz_plugin_dir, $fmz_plugin_id, $fmz_theme_slug globals
          3. Include init.php (which registers hooks)
  └── fmz_inject_mini_plugin_assets() injects CSS/JS <link>/<script> tags

Disable
  └── Set plugin_id: false in plugins_enabled.json
  └── init.php is no longer loaded
```

### 9.3 Plugin Manifest (plugin.json)

```json
{
    "id": "my-plugin",
    "name": "My Custom Plugin",
    "version": "1.0.0",
    "description": "A brief description of what this plugin does.",
    "author": "Your Name",
    "author_url": "https://example.com",
    "compatibility": "18*"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | string | **Yes** | Unique identifier. Matches the directory name. Used in option keys and state storage. |
| `name` | string | **Yes** | Display name shown in admin UI. |
| `version` | string | **Yes** | Semantic version number. |
| `description` | string | No | Brief description shown in the plugin list. |
| `author` | string | No | Author name. |
| `author_url` | string | No | Author website URL. |
| `compatibility` | string | No | MyBB version compatibility pattern (e.g., `"18*"` for all 1.8.x). |

### 9.4 Plugin Entry Point (init.php)

The `init.php` file is included at runtime when the plugin is enabled. It has access to all MyBB globals and can register hooks.

**Available globals when `init.php` is included:**

| Variable | Type | Description |
|----------|------|-------------|
| `$mybb` | MyBB object | MyBB core object with `$mybb->input`, `$mybb->user`, etc. |
| `$plugins` | pluginSystem | Hook registration object (`$plugins->add_hook()`) |
| `$fmz_plugin_options` | array | This plugin's merged option values |
| `$fmz_plugin_dir` | string | Absolute path to this plugin's directory |
| `$fmz_plugin_id` | string | This plugin's ID |
| `$fmz_theme_slug` | string | Active theme slug |
| `$mybb->fmz_theme_options` | array | All theme-level option values |
| `$mybb->fmz_plugin_options['plugin-id']` | array | Any plugin's option values by ID |

**Example init.php:**

```php
<?php
global $plugins;

$plugins->add_hook('pre_output_page', 'my_plugin_inject');
$plugins->add_hook('index_end', 'my_plugin_index');

function my_plugin_inject(&$contents)
{
    global $mybb;
    
    // Access this plugin's options
    $opts = isset($mybb->fmz_plugin_options['my-plugin']) 
          ? $mybb->fmz_plugin_options['my-plugin'] 
          : [];
    
    // Check if feature is enabled
    if (empty($opts['enable_feature'])) return $contents;
    
    $css = '<style>.my-class { color: var(--tekbb-accent); }</style>';
    $contents = str_replace('</head>', $css . '</head>', $contents);
    
    return $contents;
}

function my_plugin_index()
{
    global $mybb;
    $GLOBALS['my_custom_content'] = '<div>Hello World</div>';
}
```

### 9.5 Plugin Options (options.php)

Define options as a PHP array. FMZ Studio renders the form automatically.

```php
<?php
return [
    [
        'id'          => 'enable_feature',
        'label'       => 'Enable Feature',
        'description' => 'Toggle this feature on or off.',
        'type'        => 'yesno',
        'default'     => '1',
    ],
    [
        'id'          => 'custom_text',
        'label'       => 'Custom Text',
        'description' => 'Enter some custom text.',
        'type'        => 'text',
        'default'     => 'Hello World',
    ],
    [
        'id'          => 'layout_style',
        'label'       => 'Layout Style',
        'description' => 'Choose a layout.',
        'type'        => 'select',
        'default'     => 'grid',
        'options'     => [
            'grid' => 'Grid Layout',
            'list' => 'List Layout',
        ],
    ],
];
```

**Supported option types:**

| Type | PHP Array | UI Control | Stored Value |
|------|-----------|------------|--------------|
| `text` | `'type' => 'text'` | Single-line input | String |
| `textarea` | `'type' => 'textarea'` | Multi-line textarea | String |
| `yesno` | `'type' => 'yesno'` | Radio: Yes/No | `"1"` or `"0"` |
| `select` | `'type' => 'select', 'options' => [...]` | Dropdown | Selected key |
| `radio` | `'type' => 'radio', 'options' => [...]` | Radio group | Selected key |
| `color` | `'type' => 'color'` | Color picker | `"#rrggbb"` |
| `numeric` | `'type' => 'numeric'` | Number input | String number |
| `image` | `'type' => 'image'` | File upload with preview | Relative path |
| `icon_chooser` | `'type' => 'icon_chooser'` | Searchable Bootstrap Icons grid | Icon class |
| `nav_links` | `'type' => 'nav_links'` | Visual link builder (text+URL+icon repeater) | JSON string |
| `toolbar_builder` | `'type' => 'toolbar_builder'` | Drag-and-drop toolbar configurator | Comma-separated |
| `preset_swatches` | `'type' => 'preset_swatches'` | Color preset selection grid | Preset key |

### 9.6 Plugin Assets (CSS/JS)

Files in `css/` and `js/` subdirectories are automatically injected on all frontend pages when the plugin is enabled.

- **CSS files** → `<link rel="stylesheet">` tags before `</head>`
- **JS files** → `<script>` tags before `</head>`

No registration or configuration is needed. Just place your files in the right directories.

**Asset URL format:**
```
{$mybb->asset_url}/themes/{slug}/functions/plugins/{plugin-id}/css/styles.css
{$mybb->asset_url}/themes/{slug}/functions/plugins/{plugin-id}/js/script.js
```

### 9.7 Plugin Admin Panel (admin.php)

Plugins can define custom admin pages by creating an `admin.php` file. When present, a "Settings" link appears in the plugin list that loads this file within the FMZ Studio admin context.

---

## 10. Bundled Mini-Plugins

### 10.1 Forum Display Extras

**ID:** `fmz-forum-display-extras`  
**Version:** 1.0.0  
**Files:** `plugin.json`, `init.php`, `options.php`, `default.json`

Enhances forum and thread listings with avatars, user modals, layout options, and usergroup styling.

#### Options (6)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enable_thread_avatars` | Yes/No | Yes | Show circular avatar next to "Last Post" in thread listings |
| `enable_forum_avatars` | Yes/No | Yes | Show circular avatar next to "Last Post" in forum listings |
| `enable_user_modal` | Yes/No | Yes | Click avatar to open a Bootstrap 5 modal with user info, stats, and action buttons |
| `subforum_columns` | Select | `0` | Default inline or 2-column grid for subforums |
| `forum_layout` | Select | `rows` | Forum listing layout: Rows (traditional) or Cards (CSS grid) |
| `cards_per_row` | Select | `3` | Cards per row (2, 3, or 4) — only when `forum_layout` is `cards` |
| `enable_usergroup_style` | Yes/No | Yes | Display last poster usernames with usergroup color/style formatting |

#### User Modal Features

When clicking a last poster avatar, a modal shows:
- User avatar (large), formatted username (with usergroup colors), user title
- Post count, reputation score, registration date
- Action buttons: View Profile, Send PM (if permitted), Rate User (if permitted)
- Data fetched via AJAX: `xmlhttp.php?action=fmz_fde_usercard&uid=N`

#### Card Layout

When `forum_layout` is set to `cards`, forums display as CSS grid cards with:
- Forum icon, name, description, and stats
- The `forumbit` template is swapped to a card variant at runtime
- Grid columns controlled by `cards_per_row` via `grid-template-columns`

#### Hooks

| Hook | Function | Purpose |
|------|----------|---------|
| `build_forumbits_forum` | `fmz_fde_enrich_forum()` | Batch-loads avatars, formats usernames, optionally swaps to card template |
| `forumdisplay_thread` | `fmz_fde_enrich_thread()` | Batch-loads thread avatars, outputs `$GLOBALS['fmz_thread_lastposter_avatar']` |
| `pre_output_page` | `fmz_fde_inject_output()` | Injects inline CSS/JS for avatars, modal, subforum columns, card layout |
| `xmlhttp` | `fmz_fde_xmlhttp_usercard()` | AJAX endpoint returning JSON user card data with permission checks |

#### Security

- XMLHTTP user card endpoint checks `canviewprofiles` permission.
- Guest access denied if guest usergroup lacks profile viewing permission.

---

### 10.2 Forum Icons

**ID:** `fmz-forum-icons`  
**Version:** 1.0.0  
**Files:** `plugin.json`, `init.php`

Allows admins to assign custom Bootstrap Icons or uploaded images as forum/category icons with status-aware coloring.

#### How It Works

1. **Admin creates/edits a forum:** New section appears in ACP forum form:
   - **None** — use default MyBB forum icon
   - **Bootstrap Icon** — searchable grid of 63 popular icons or type any BI class name
   - **Upload Image** — upload PNG/JPG/GIF/SVG/WebP (max 256KB)

2. **Data stored** in `fmz_forum_icons` database table (auto-created):
   ```sql
   CREATE TABLE fmz_forum_icons (
       fid INT PRIMARY KEY,
       icon_type VARCHAR(10),   -- 'bi' or 'image'
       icon_value VARCHAR(255)  -- class name or filename
   );
   ```

3. **Frontend rendering** via `pre_output_page`:
   - **Bootstrap Icons:** Default `<i>` class replaced via regex. Status-aware: "on" (new posts) = accent color; "off" (no new) = muted + 50% opacity.
   - **Uploaded Images:** CSS injected to hide `<i>` tag, show `background-image`. Read/unread via grayscale + opacity filters.

**File Storage:** `uploads/forum_icons/forum_{fid}.{ext}`

**Auto-Installation:** Creates `fmz_forum_icons` table and `uploads/forum_icons/` directory on first load if they don't exist.

---

### 10.3 Profile Extras

**ID:** `fmz-profile-extras`  
**Version:** 1.0.0  
**Files:** `plugin.json`, `init.php`, `options.php`, `css/profile-extras.css`, `js/profile-extras.js`

Adds profile banner customization and a status updates system with privacy levels.

#### Options (5)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enable_banners` | Yes/No | Yes | Allow profile banner customization |
| `banner_max_size` | Numeric | `2048` | Max banner file size in KB |
| `enable_statuses` | Yes/No | Yes | Enable status updates system |
| `status_max_length` | Numeric | `1000` | Max characters per status update |
| `statuses_per_page` | Numeric | `20` | Statuses per page on the feed |

#### Profile Banners

Users customize banners via a Bootstrap 5 modal with four tabs:

| Tab | Function |
|-----|----------|
| **Upload** | Upload JPG/PNG/GIF/WebP. Saved to `uploads/profile_banners/`. |
| **Previous** | Gallery of previously uploaded banners. Click to re-activate. |
| **Solid Color** | Pick a single color for a solid background. |
| **Gradient** | Pick two colors + direction for a gradient background. |

Each banner supports **text color** and **link color** overrides applied as CSS custom properties.

#### Status Updates

A mini social feed system:
- **Post status** — text input with BBCode support (MyBB parser)
- **Privacy levels** — Public (everyone), Private (only the user), Buddies (buddy list members)
- **Comments** — other users can comment on statuses (max 500 chars)
- **Edit/Delete** — owner and moderators can edit/delete
- **Status Feed Page** — `usercp.php?action=statusfeed` with hero, filters, compose box, pagination

#### AJAX Actions

All routed through `usercp.php?fmz_action=...`:

| Action | Description |
|--------|-------------|
| `save_banner` | Upload/URL/solid/gradient banner + text/link colors |
| `remove_banner` | Deactivate all banners |
| `activate_banner` | Re-activate a previous banner by ID |
| `update_banner_colors` | Change text/link colors on active banner |
| `post_status` | Create a new status update |
| `edit_status` | Edit an existing status (owner or mod) |
| `delete_status` | Delete a status (owner or mod) |
| `post_comment` | Add a comment to a status |
| `delete_comment` | Delete a comment (comment/status owner or mod) |

#### Database Tables (Auto-Created)

```sql
CREATE TABLE fmz_user_banners (
    bid INT AUTO_INCREMENT PRIMARY KEY,
    uid INT, type VARCHAR(20), value TEXT,
    text_color VARCHAR(7), link_color VARCHAR(7),
    is_active TINYINT DEFAULT 0, dateline INT
);

CREATE TABLE fmz_user_statuses (
    sid INT AUTO_INCREMENT PRIMARY KEY,
    uid INT, message TEXT,
    privacy ENUM('public','private','buddies') DEFAULT 'public',
    dateline INT
);

CREATE TABLE fmz_status_comments (
    cid INT AUTO_INCREMENT PRIMARY KEY,
    sid INT, uid INT, message TEXT, dateline INT
);
```

---

### 10.4 WYSIWYG Editor

**ID:** `fmz-wysiwyg`  
**Version:** 1.0.0  
**Files:** `plugin.json`, `init.php`, `options.php`, `default.json`, `css/wysiwyg.css`, `js/wysiwyg.js`, `vendor/highlight.min.js`, `vendor/atom-one-dark.min.css`

Replaces MyBB's SCEditor with a modern rich-text editor featuring image paste/upload, clean HTML output, and a customizable toolbar.

#### Options (31)

##### Appearance (4)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `color_mode` | Select | `light` | Editor color scheme (Light / Dark) |
| `color_theme` | Preset Swatches | `teal` | Toolbar accent preset (12 choices) |
| `default_text_color` | Color | `#e06666` | Default text color button color |
| `default_highlight_color` | Color | `#fff2cc` | Default highlight button color |

**Color theme presets:** Teal (#0d9488), Ocean (#0369a1), Indigo (#4338ca), Purple (#7e22ce), Rose (#be123c), Amber (#b45309), Emerald (#059669), Crimson (#dc2626), Sapphire (#1d4ed8), Coral (#c2410c), Slate (#475569), Pink (#db2777)

##### Toolbar (2)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `toolbar_style` | Select | `full` | Full (all buttons), Minimal (essentials), Custom (drag-and-drop) |
| `toolbar_buttons` | Toolbar Builder | *(full set)* | Drag-and-drop visual toolbar configurator |

**Default toolbar:**
```
bold,italic,underline,strikethrough,|,fontFamily,fontSize,fontColor,highlight,|,
alignLeft,alignCenter,alignRight,alignJustify,|,bulletList,numberedList,indent,outdent,|,
link,image,video,table,|,emoji,gif,quote,code,formula,hr,|,removeFormat,undo,redo,saveDraft,source
```

##### Typography (4)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `font_families` | Textarea | *(9 families)* | One per line: `Display Name\|CSS font-family`. Prefix `google:` for auto-loading. |
| `font_sizes` | Text | `8px,9px,...,72px` | Comma-separated size options |
| `editor_font_family` | Text | System fonts | Default editor content font |
| `editor_font_size` | Text | `14px` | Default editor content font size |

**Default font families:**
```
Arial|Arial, Helvetica, sans-serif
Georgia|Georgia, serif
Times New Roman|Times New Roman, serif
Courier New|Courier New, monospace
Verdana|Verdana, sans-serif
Trebuchet MS|Trebuchet MS, sans-serif
google:Roboto|Roboto, sans-serif
google:Open Sans|Open Sans, sans-serif
google:Fira Code|Fira Code, monospace
```

Fonts prefixed with `google:` trigger automatic Google Fonts CDN `<link>` loading.

##### Editor Size (2)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `editor_height` | Numeric | `350` | Main editor height in pixels |
| `max_quote_depth` | Numeric | `3` | Maximum `[quote]` nesting (0 = unlimited) |

##### Quick Reply (4)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enable_quick_reply_editor` | Yes/No | Yes | Enable WYSIWYG in quick reply |
| `quick_reply_editor_height` | Numeric | `150` | Quick reply editor height |
| `quick_reply_toolbar_style` | Select | `minimal` | Toolbar style for quick reply |
| `quick_reply_toolbar_buttons` | Toolbar Builder | *(minimal set)* | Quick reply toolbar buttons |

##### Quick Edit (4)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enable_quick_edit_editor` | Yes/No | Yes | Enable WYSIWYG in inline quick edit |
| `quick_edit_editor_height` | Numeric | `250` | Quick edit editor height |
| `quick_edit_toolbar_style` | Select | `minimal` | Toolbar style for quick edit |
| `quick_edit_toolbar_buttons` | Toolbar Builder | *(minimal set)* | Quick edit toolbar buttons |

##### Image Handling (4)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enable_image_paste` | Yes/No | Yes | Allow clipboard image pasting |
| `enable_image_upload` | Yes/No | Yes | Allow drag-and-drop image upload |
| `max_image_size_kb` | Numeric | `2048` | Max image file size in KB |
| `max_images_per_post` | Numeric | `10` | Max images per post (0 = unlimited) |

Images are uploaded via `xmlhttp.php?action=fmz_wysiwyg_upload` and stored as MyBB attachments. FMZ WYSIWYG plugin settings (max size, max images per post) supersede global attachment settings.

##### Code Blocks (3)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enable_code_highlight` | Yes/No | Yes | Syntax highlighting via highlight.js |
| `enable_code_copy` | Yes/No | Yes | Copy-to-clipboard button on code blocks |
| `enable_code_linenumbers` | Yes/No | Yes | Line numbers on code blocks |

##### Miscellaneous (4)

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `show_source_toggle` | Yes/No | Yes | BBCode source view toggle button |
| `auto_save` | Yes/No | Yes | Auto-save to localStorage |
| `auto_save_interval` | Numeric | `30` | Auto-save interval in seconds |
| `giphy_api_key` | Text | *(empty)* | GIPHY API key for GIF search |

#### Custom BBCode Extensions

Parsed via `parse_message_end` hook:

| BBCode | HTML Output | Description |
|--------|------------|-------------|
| `[table][tr][td]...[/td][/tr][/table]` | `<table>` | Full table support |
| `[th]...[/th]` | `<th>` | Table header cells |
| `[highlight=color]...[/highlight]` | `<span style="background:color">` | Text highlighting |
| `[code=language]...[/code]` | `<pre><code class="language-xxx">` | Syntax-highlighted code |
| `[align=center]...[/align]` | `<div style="text-align:center">` | Content alignment |

#### Toolbar Buttons Reference

| Button ID | Icon | Function |
|-----------|------|----------|
| `bold` | **B** | Bold text |
| `italic` | *I* | Italic text |
| `underline` | U̲ | Underline |
| `strikethrough` | S̶ | Strikethrough |
| `fontFamily` | Font dropdown | Change font family |
| `fontSize` | Size dropdown | Change font size |
| `fontColor` | A (colored) | Text color picker |
| `highlight` | Highlighter | Background highlight |
| `alignLeft` / `alignCenter` / `alignRight` / `alignJustify` | ≡ | Text alignment |
| `bulletList` | • | Unordered list |
| `numberedList` | 1. | Ordered list |
| `indent` / `outdent` | →/← | Indentation |
| `link` | 🔗 | Insert/edit hyperlink |
| `image` | 🖼 | Insert image (URL, upload, paste) |
| `video` | ▶ | Embed video (YouTube, Vimeo, Dailymotion) |
| `table` | Grid | Insert table (visual grid builder) |
| `emoji` | 😊 | Emoji picker (4 categories, 200+ emoji) |
| `gif` | GIF | GIPHY search (requires API key) |
| `quote` | " | Insert quote block |
| `code` | `<>` | Insert code block (with language selector) |
| `formula` | Σ | Insert math formula |
| `hr` | ── | Horizontal rule |
| `removeFormat` | T̸ | Remove all formatting |
| `undo` / `redo` | ↩/↪ | Undo / Redo |
| `saveDraft` | 💾 | Save draft to localStorage |
| `source` | `{}` | Toggle BBCode source view |

---

### 10.5 Page Builder

**ID:** `fmz-pagebuilder`  
**Version:** 1.0.0  
**Files:** `plugin.json`, `init.php`, `renderer.php`

Creates standalone pages on your forum with a Monaco HTML editor, clean URL routing, SEO meta, and user group permissions.

#### Key Features

| Feature | Description |
|---------|-------------|
| **Monaco HTML Editor** | Syntax highlighting, Emmet, autocomplete, multiple cursors |
| **Clean URLs** | Pages at `yourdomain.com/forum/page-slug` via `mod_rewrite` |
| **Front Page Override** | Designate any page as the forum's front page |
| **Custom CSS & JS** | Per-page custom stylesheet and JavaScript |
| **User Group Permissions** | Restrict page visibility to specific groups |
| **Template Variables** | Use `{$mybb->user['username']}`, `{$header}`, `{$footer}`, `{$boardstats}` |
| **Conditional Blocks** | `<if $condition then>...<else>...</if>` tags |
| **Draft & Preview** | Save as draft, preview via secure admin-only URL |
| **SEO Meta** | Custom meta title and description per page |

#### How to Use

1. Enable the **Page Builder** plugin in **FMZ Studio → Manage Plugins**.
2. Go to **FMZ Studio → Page Manager** to create, edit, and manage pages.
3. Click **Add Page** — Monaco editor opens with a template variable file tree.
4. Write page HTML using standard HTML/CSS plus MyBB template variables.
5. Set status to **Published** and save.
6. Access at `yourdomain.com/forum/your-page-slug`.

#### URL Routing

Requires Apache `mod_rewrite` or Nginx equivalent:

**Apache (.htaccess):**
```apache
# FMZ Page Builder — clean URLs
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-zA-Z0-9\-_/]+)$ misc.php?fmz_page=$1 [L,QSA]
```

**Nginx:**
```nginx
location / {
    try_files $uri $uri/ /misc.php?fmz_page=$uri&$args;
}
```

#### Front Page Override

1. Go to **FMZ Studio → Page Manager**.
2. Select a published page from the **Front Page** dropdown (options: Default / Portal / published pages).
3. Click **Save** — the selected page replaces the forum index.
4. Stored in cache as `fmz_front_page`.

#### Rendering Engine (renderer.php)

The renderer processes page content through these steps:
1. Load page data from `fmz_pages` table by slug.
2. Check user group permissions against `allowed_groups`.
3. Evaluate MyBB template variables (`{$variable}` syntax).
4. Process conditional blocks (`<if>...<else>...</if>`).
5. Inject custom CSS and JS.
6. Wrap in page layout template with `{$header}`, `{$footer}`.

Hooks used:
- `misc_start` — intercept slug-based page requests
- `index_start` — intercept front page override

---

## 11. Sync Engine

### 11.1 How Sync Works

The sync engine converts disk files to MyBB database records in three stages:

```
Stage 1: Files → XML
  Read theme.json, css/*.css, templates/**/*.html
  Build MyBB-compatible theme XML string (via buildThemeXml())

Stage 2: XML → Database
  Import via MyBB's native import_theme_xml()
  Creates/updates: theme row, template set, templates, stylesheets

Stage 3: Post-Sync
  Deploy JS files from js/ to jscripts/
  Rebuild stylesheet cache (cache/themes/themeN/)
```

### 11.2 Auto-Sync (Dev Mode)

When enabled in **Studio Settings**, auto-sync provides live reload during development:

1. **Polling Script** — Injected before `</body>` on frontend pages. Polls `xmlhttp.php?action=fmz_dev_sync_check` at the configured interval.

2. **Change Detection** — Computes MD5 hash of all `path|mtime|size` entries in the theme directory. Compared against cached `fmz_dev_last_hash`.

3. **Sync Trigger** — When hash changes, sends POST to `xmlhttp.php?action=fmz_dev_sync_run`, which calls `syncToDatabase()`.

4. **Page Reload** — After successful sync, the polling script reloads the page.

**Requirements:**
- Admin user (`cancp=1`) must be logged in.
- `fmz_dev_auto_sync` setting must be enabled.

**Performance Note:** Auto-sync is designed for development only. The polling interval defaults to 2 seconds. Disable in production environments.

### 11.3 Incremental Sync

When a theme already exists in the database, `syncToDatabase()` uses incremental sync instead of full re-import:

1. **Diff Templates** — Compare disk templates (`.html` files) against database templates:
   - **New files** → INSERT template rows
   - **Changed files** → UPDATE template content
   - **Deleted files** → DELETE template rows

2. **Reimport Stylesheets** — CSS files are re-imported via XML to handle `attachedto` changes correctly.

3. **Deploy JS** — Copy JS files from `js/` to `jscripts/`.

4. **Rebuild Cache** — Write stylesheet cache files and call `update_theme_stylesheet_list()`.

### 11.4 Single-File Sync

When saving a single file via the editor (`api_savefile`), `syncSingleFile()` handles the sync based on file type:

| File Type | Sync Action |
|-----------|-------------|
| `.css` in `css/` | Update the `themestylesheets` row, rebuild stylesheet cache |
| `.html` in `templates/` | Update the `templates` row (INSERT if new) |
| `.js` in `js/` | Copy to `jscripts/` |
| Other | Write to disk only (no DB sync) |

---

## 12. Licensing System

### 12.1 Overview

FMZ Studio uses an encrypted license system to validate installations. License data is stored in the MyBB database as encrypted blobs — no plaintext keys are stored.

Write-operation API endpoints in the admin module (editor save, option save, theme sync, etc.) require a valid license. Read-only operations (browsing themes) do not.

### 12.2 Security Architecture

Five layers of protection:

| Layer | Mechanism | Purpose |
|-------|-----------|---------|
| 1 | **AES-256-CBC Encryption** | Stored data in the database is encrypted. DB values are opaque blobs. |
| 2 | **HMAC-SHA256 Integrity** | Each blob includes an HMAC tag. Any tampering (bit flip, truncation) is detected. |
| 3 | **Site-Bound Key Derivation** | Encryption key derived from database credentials + salt. Encrypted blobs are not portable between installations. |
| 4 | **File Integrity Check** | Source code self-verification. Tampering with `license.php` is detected. |
| 5 | **Periodic Re-Validation** | Cached token expires every 24 hours. The license is re-checked against the server via HTTPS. |

**Key Derivation:**
```php
$key = hash('sha256', $db_hostname . $db_username . $db_password . $db_name . DERIVATION_SALT);
```

### 12.3 License Types

| Type | Behavior |
|------|----------|
| **Standard** | 1 website at a time. Must deactivate before re-activating elsewhere. |
| **Redistributable** | Unlimited simultaneous websites. Surcharge at checkout. |

### 12.4 Validation Flow

License validation is handled by **Blesta License Manager** at `https://tektove.com/plugin/license_manager/validate/`.

**Activation sequence:**
1. Client requests the RSA public key from the Blesta server using the license key.
2. Client requests signed license data, encrypted with the public key.
3. License data is validated locally (domain binding, expiry, status).
4. Valid license data is stored as an AES-256-CBC encrypted blob in the MyBB settings table.

**Periodic re-validation:**
- Cached license data expires every 24 hours (`LICENSE_TTL`).
- On expiry, the client re-requests license data from the server.
- If the server is unreachable, the cached data remains valid for its TTL.

**Deactivation:**
- Clears the encrypted license blob and check timestamp from the database.
- The license key becomes available for activation on another domain.

**Returned statuses:** `valid`, `invalid`, `expired`, `suspended`, `unknown`

### 12.5 Subscription Management

License subscriptions and renewals are managed through the Blesta billing platform. Subscription periods, payment processing, and renewal scheduling are handled server-side.

**Customer cancellation:** Via HMAC-SHA256 signed links with 30-day validity.

---

## 13. Developer Guide

### 13.1 Creating a New Theme

#### Method 1: From Scratch

1. **Create the theme directory:**
   ```
   themes/my-theme/
   ```

2. **Create `theme.json`** (minimum required):
   ```json
   {
       "name": "My Theme",
       "version": "1839",
       "properties": {
           "editortheme": "default.css",
           "imgdir": "images",
           "tablespace": "0",
           "borderwidth": "0"
       },
       "stylesheets": [
           { "name": "global.css", "attachedto": "", "order": 1 }
       ],
       "js": []
   }
   ```

3. **Create `css/global.css`** with your styles.

4. **Create `templates/`** with at least one `.html` file. A minimal setup needs:
   - `templates/ungrouped/headerinclude.html`
   - `templates/ungrouped/htmldoctype.html`
   - `templates/header/header.html`
   - `templates/footer/footer.html`

5. **Sync** in Admin CP → FMZ Studio → Manage → click **Sync**.

6. **Set as Default** to activate.

#### Method 2: Copy an Existing Theme

1. **Duplicate:** `cp -r themes/fmz-default/ themes/my-custom/`
2. **Edit `theme.json`** — change the `"name"` field.
3. **Sync** the new theme.
4. **Customize** templates, CSS, and options.

#### Method 3: Import a ZIP

1. Go to **Admin CP → FMZ Studio → Import**.
2. Upload a `.zip` file containing a theme directory with `theme.json`.
3. Select a parent theme.
4. The theme is extracted, synced, and ready to use.

---

### 13.2 Editing an Existing Theme

#### Method 1: Built-in Monaco Editor

1. Go to **FMZ Studio → Manage** → click **Edit**.
2. Monaco opens with a file tree on the left.
3. Click any file to open in a tab.
4. Press **Ctrl+S** to save and sync.

#### Method 2: External Editor (VS Code, Sublime, etc.)

1. Open `themes/{slug}/` in your IDE.
2. Edit files and save.
3. If **Auto-Sync** is enabled, changes sync on next page load.
4. Otherwise, click **Sync** in FMZ Studio → Manage.

#### Method 3: MyBB ACP Template Editor

1. Go to **ACP → Templates & Style → Templates**.
2. Select the theme's template set.
3. Edit templates using MyBB's built-in editor.

> **Note:** ACP template edits are database-only. Use FMZ Studio's reverse sync or manually update `.html` files to persist to disk.

#### Editing CSS

- Edit files in `css/` and sync, or use the built-in editor.
- Stylesheets are compiled to `cache/themes/themeN/` as cache files.
- After CSS changes via auto-sync, cache is automatically rebuilt.

#### Editing Templates

- Each `.html` file = one MyBB template.
- Use `{$variable}` syntax for dynamic content.
- File name (without `.html`) = template name in database.
- Subdirectory = template group (organizational only).

---

### 13.3 Deleting a Theme

#### Via Admin CP

1. **FMZ Studio → Manage** → click **Delete**.
2. Theme is removed from database.
3. Optionally delete `themes/{slug}/` from disk.

#### Manual Deletion

1. Delete from database: **ACP → Themes** → delete.
2. Delete from disk: remove `themes/{slug}/`.
3. Clean up JS: remove deployed files from `jscripts/`.
4. Clean up cache: delete `cache/themes/themeN/`.

> **Warning:** Never delete the currently active/default theme. Set another as default first.

---

### 13.4 Creating a Mini-Plugin

#### Step 1: Create Plugin Directory

```
themes/fmz-default/functions/plugins/my-plugin/
```

#### Step 2: Create plugin.json

```json
{
    "id": "my-plugin",
    "name": "My Custom Plugin",
    "version": "1.0.0",
    "description": "A brief description.",
    "author": "Your Name",
    "author_url": "https://example.com",
    "compatibility": "18*"
}
```

#### Step 3: Create init.php

```php
<?php
global $plugins;

$plugins->add_hook('pre_output_page', 'my_plugin_inject');
$plugins->add_hook('index_end', 'my_plugin_index');

function my_plugin_inject(&$contents)
{
    global $mybb;
    
    $opts = isset($mybb->fmz_plugin_options['my-plugin']) 
          ? $mybb->fmz_plugin_options['my-plugin'] 
          : [];
    
    $css = '<style>.my-custom-class { color: red; }</style>';
    $contents = str_replace('</head>', $css . '</head>', $contents);
    
    return $contents;
}

function my_plugin_index()
{
    global $mybb;
    $GLOBALS['my_index_content'] = '<div class="alert alert-info">Custom content</div>';
}
```

#### Step 4: Create options.php (Optional)

```php
<?php
return [
    [
        'id'          => 'enable_feature',
        'label'       => 'Enable Feature',
        'description' => 'Toggle this feature on or off.',
        'type'        => 'yesno',
        'default'     => '1',
    ],
    [
        'id'          => 'custom_text',
        'label'       => 'Custom Text',
        'description' => 'Enter some custom text.',
        'type'        => 'text',
        'default'     => 'Hello World',
    ],
    [
        'id'          => 'layout_style',
        'label'       => 'Layout Style',
        'description' => 'Choose a layout.',
        'type'        => 'select',
        'default'     => 'grid',
        'options'     => [
            'grid' => 'Grid Layout',
            'list' => 'List Layout',
        ],
    ],
];
```

#### Step 5: Create default.json (Optional)

```json
{
    "enable_feature": "1",
    "custom_text": "Hello World",
    "layout_style": "grid"
}
```

#### Step 6: Add CSS/JS Assets (Optional)

Create `css/styles.css` and/or `js/script.js`. These are automatically injected on all frontend pages when the plugin is enabled.

#### Step 7: Enable the Plugin

1. **FMZ Studio → Manage Plugins**.
2. Your plugin appears in the list.
3. Click **Enable**.
4. Click **Settings** to configure options.

---

### 13.5 Editing a Mini-Plugin

#### Editing Code

- Open `init.php` in your IDE or the FMZ Studio editor.
- Changes take effect immediately if auto-sync is enabled, or on next page load.

#### Editing Options

- Modify `options.php` to add, change, or remove option definitions.
- New options appear automatically in the settings page.
- Removed options are cleaned up.

#### Editing CSS/JS

- Edit files in the plugin's `css/` or `js/` directories.
- Assets are re-injected on the next page load.

#### Adding a Database Table

```php
// In init.php, add auto-install logic:
function my_plugin_install()
{
    global $db;
    if (!$db->table_exists('my_plugin_data')) {
        $db->write_query("CREATE TABLE " . TABLE_PREFIX . "my_plugin_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uid INT NOT NULL,
            value TEXT,
            dateline INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
my_plugin_install(); // Run on load
```

---

### 13.6 Deleting a Mini-Plugin

1. **Disable** in FMZ Studio → Manage Plugins.
2. **Delete** the plugin directory: `functions/plugins/my-plugin/`
3. **Clean up database** if the plugin created tables:
   ```sql
   DROP TABLE IF EXISTS mybb_my_plugin_data;
   ```
4. Option values in `default.json` are automatically cleaned up when the directory is deleted.

---

### 13.7 Adding Theme Options

#### Step 1: Edit options.php

Open `themes/{slug}/functions/options.php` and add a new entry:

```php
'my_new_option' => [
    'title'       => 'My New Option',
    'description' => 'Description of what this option controls.',
    'type'        => 'yesno',
    'default'     => '1',
    'page'        => 'global',     // 'global' or 'header_footer'
    'group'       => 'my_group',   // optional grouping
],
```

#### Step 2: Use in hooks.php

```php
$opts = isset($mybb->fmz_theme_options) ? $mybb->fmz_theme_options : [];
$value = !empty($opts['my_new_option']) ? $opts['my_new_option'] : '1';

if ($value === '1') {
    // Feature is enabled
}
```

#### Step 3: Use in Templates

Theme options aren't directly available as template variables. Set a global in `hooks.php`:

```php
$GLOBALS['my_template_var'] = $opts['my_new_option'] === '1' ? 'enabled' : 'disabled';
```

Then use `{$my_template_var}` in your `.html` templates.

#### Option Fields Reference

| Field | Required | Description |
|-------|----------|-------------|
| `title` | Yes | Display label in the settings form |
| `description` | Yes | Help text below the control |
| `type` | Yes | One of: `text`, `textarea`, `yesno`, `select`, `radio`, `color`, `numeric`, `image`, `icon_chooser`, `nav_links`, `toolbar_builder`, `preset_swatches` |
| `default` | Yes | Default value |
| `page` | No | Which settings page to show on: `'global'` or `'header_footer'` |
| `group` | No | Group key for visual grouping |
| `css_var` | No | CSS custom property to generate override for (e.g., `'--tekbb-accent'`) |
| `options` | Conditional | Required for `select` and `radio` types. Array of `'key' => 'Label'`. |
| `has_dimensions` | No | For `image` type. Adds `_width` and `_height` sub-fields. |

---

### 13.8 Adding Custom Hooks

#### Step 1: Choose a MyBB Hook Point

Common hooks:

| Hook | When It Fires |
|------|--------------|
| `global_start` | Very early, before anything loads |
| `global_intermediate` | After settings/cache loaded |
| `global_end` | After page content generated |
| `pre_output_page` | Just before HTML is sent to browser |
| `index_start` / `index_end` | Forum index page |
| `forumdisplay_start` / `forumdisplay_end` | Forum display page |
| `showthread_start` / `showthread_end` | Thread view page |
| `member_profile_start` / `member_profile_end` | User profile page |
| `postbit` | Each post rendering |
| `newreply_start` / `newthread_start` | New reply/thread forms |
| `xmlhttp` | AJAX requests |

Full hook reference: [MyBB Plugin Hooks](https://docs.mybb.com/1.8/development/plugins/hooks/)

#### Step 2: Register the Hook

In `hooks.php` (before any function definitions):

```php
$plugins->add_hook('showthread_end', 'my_custom_showthread');
```

#### Step 3: Write the Function

```php
function my_custom_showthread()
{
    global $mybb, $thread, $lang;
    
    $opts = isset($mybb->fmz_theme_options) ? $mybb->fmz_theme_options : [];
    
    $GLOBALS['my_custom_content'] = '<div class="alert alert-info">Custom content</div>';
}
```

#### Step 4: Use in Templates

Add `{$my_custom_content}` to the appropriate template file.

#### Example: Inject Custom CSS

```php
$plugins->add_hook('pre_output_page', 'my_inject_css');

function my_inject_css(&$contents)
{
    $css = '<style>.custom-class { border: 2px solid var(--tekbb-accent); }</style>';
    $contents = str_replace('</head>', $css . '</head>', $contents);
    return $contents;
}
```

#### Example: AJAX Endpoint

```php
$plugins->add_hook('xmlhttp', 'my_ajax_handler');

function my_ajax_handler()
{
    global $mybb;
    if ($mybb->get_input('action') !== 'my_custom_action') return;
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => 'Hello']);
    exit;
}
```

---

### 13.9 Customizing Colors

#### Method 1: Theme Options (Recommended)

1. **FMZ Studio → Global FMZ Options**.
2. Scroll to **Color Palette Light** or **Color Palette Dark**.
3. Click any swatch to open the color picker.
4. **Save** — only changed colors generate CSS overrides.

#### Method 2: Quick Presets

1. **FMZ Studio → Global FMZ Options**.
2. Click any preset swatch (Teal, Ocean, Indigo, etc.).
3. All accent-related colors are set automatically for both palettes.
4. Click **Save**.

#### Method 3: Custom CSS

```css
:root, :root[data-theme="light"] {
    --tekbb-accent: #3b82f6;
    --tekbb-accent-hover: #2563eb;
    --tekbb-heading-bg: #3b82f6;
    --tekbb-link: #3b82f6;
    --tekbb-link-hover: #2563eb;
}
```

#### Color Relationships

For visual consistency:
- `accent` and `heading_bg` should match (or complement)
- `accent_hover` should be slightly darker than `accent`
- `link` should match `accent`
- `btn_bg` should match `accent` unless you want distinct buttons
- `surface` should contrast with `body_bg`

---

### 13.10 Customizing the Header

#### Changing Header Layout

1. **FMZ Studio → Header & Footer → Header Style**.
2. Choose: Default, Centered, or Minimal.

The header element gets a CSS class:
- `fmz-header-default` — logo left, nav right
- `fmz-header-centered` — logo centered above nav
- `fmz-header-minimal` — text-only compact header

#### Custom Header CSS

```css
.fmz-header-centered .navbar-brand {
    font-size: 2rem;
}
```

#### Editing the Header Template

Edit `templates/header/header.html`. Key template variables:
- `{$fmz_logo_html}` — the logo (image/icon/text)
- `{$fmz_header_class}` — CSS class for header style
- `{$fmz_custom_nav}` — custom navigation `<li>` items

---

### 13.11 Adding Navigation Links

#### Method 1: Theme Options (No Code)

1. **FMZ Studio → Header & Footer → Custom Navigation Links**.
2. Click **Add Link**.
3. Fill in: **Text** (display text), **URL** (destination), **Icon** (optional BI class).
4. Drag to reorder. Click × to remove.
5. Click **Save**.

#### Method 2: Edit Template

Edit `templates/header/header.html` and add `<li>` items:

```html
<li class="nav-item">
    <a class="nav-link" href="custom-page.php">
        <i class="bi bi-star me-1"></i>Custom Page
    </a>
</li>
```

---

### 13.12 Uploading Logo & Favicon

#### Logo

1. **FMZ Studio → Header & Footer → Upload Logo Image**.
2. Upload PNG/JPG/SVG/WebP/GIF.
3. Set **Width** and **Height** (pixels, 0 = auto).
4. Click **Save**.

Stored in `themes/{slug}/images/uploads/`.

**Logo priority:** Image logo → Icon + Text logo → Board name

#### Favicon

1. **Header & Footer → Favicon**.
2. Upload .ico/.png/.svg.
3. Click **Save**.

Injected as `<link rel="icon">` and `<link rel="shortcut icon">`. Existing favicon tags are removed first.

---

### 13.13 Enabling the Stats Sidebar

1. **FMZ Studio → Global FMZ Options → Layout**.
2. Set **Show Stats as Sidebar** to **Yes**.
3. Click **Save**.

The forum index uses a two-column layout:
- **Left (75%):** Forum listing
- **Right (25%):** Board Stats, Who's Online, Birthdays cards

---

### 13.14 Importing & Exporting Themes

#### Importing

1. **FMZ Studio → Import / Export**.
2. Upload a `.zip` file.
3. Select parent theme.
4. Click **Import**.
5. ZIP is extracted to `themes/{slug}/`, synced, JS deployed.

**ZIP requirements:**
- Must contain a directory with valid `theme.json`.
- `theme.json` at root or one level deep.
- Must have `templates/` with at least one `.html` file.

#### Exporting

1. **FMZ Studio → Import / Export**.
2. Click **Download ZIP** next to any theme.
3. Download the complete `themes/{slug}/` directory as a ZIP.

#### Manual ZIP Creation

1. Create a folder (e.g., `my-theme/`).
2. Add `theme.json` with at least `"name"`.
3. Add `templates/` with `.html` files.
4. Add `css/`, `js/`, `images/` as needed.
5. ZIP and upload via Import.

---

## 14. FMZStudio Core API Reference

The `FMZStudio` class (`inc/plugins/fmzstudio/core.php`, 2042 lines) provides all theme management functionality.

**Instantiation:**
```php
require_once MYBB_ROOT . 'inc/plugins/fmzstudio/core.php';
$fmzCore = new FMZStudio();
```

### 14.1 Import / Export Methods

#### `importFromZip($file, $parentTid = 1): int|false`

Import a theme from a ZIP file.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$file` | array | PHP `$_FILES` array element |
| `$parentTid` | int | Parent theme ID (default: 1 = Master Style) |

**Returns:** New theme TID on success, `false` on failure.

**Flow:** Validate upload → extract ZIP → find theme root → validate structure → copy to `themes/{slug}/` → sync to DB → deploy JS.

#### `exportTheme($tid): string|false`

Export a theme as a ZIP file.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$tid` | int | Theme ID |

**Returns:** Path to generated ZIP file, or `false`.

If the theme only exists in the database, `extractThemeToDisk()` is called first.

#### `extractThemeToDisk($tid, $slug = null): string|false`

Extract a database-only theme to disk.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$tid` | int | Theme ID |
| `$slug` | string|null | Override slug (default: derived from theme name) |

**Returns:** Path to extracted theme directory, or `false`.

**Creates:** CSS files, templates (grouped into subdirectories), JS files, `theme.json`.

---

### 14.2 Theme Management Methods

#### `syncToDatabase($slug, $parentTid = 1): int|false`

Sync disk files to the database. Uses incremental sync for existing themes, full import for new ones.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$slug` | string | Theme directory name |
| `$parentTid` | int | Parent theme ID |

**Returns:** Theme TID on success, `false` on failure.

#### `deleteTheme($tid, $deleteDisk = true): bool`

Delete a theme entirely.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$tid` | int | Theme ID |
| `$deleteDisk` | bool | Also delete the disk directory |

**Returns:** `true` on success.

**Blocks:** Deletion of Master Style or the currently active default theme. Reassigns child themes to Master Style.

#### `listThemesOnDisk(): array`

Returns array of `[{slug, name, version, has_db, tid}, ...]` for every `themes/*/theme.json`.

#### `listDbThemes(): array`

Returns array of `[{tid, name, is_default, has_disk, slug}, ...]` for all non-master database themes.

#### `activateTheme($tid): bool`

Set a theme as the board default.

#### `deactivateTheme($tid): bool`

Clear the default flag. Falls back to the lowest available theme.

---

### 14.3 Editor File Operations

All file operations include path traversal protection and file extension whitelisting.

**Allowed extensions:** `html, htm, css, js, json, txt, md, xml, svg, ini, yml, yaml, less, scss, map, csv, log, tpl, mustache, hbs`

#### `getFileTree($slug): array|false`

Returns recursive tree: `[{name, type: 'file'|'dir', children: [...]}, ...]`

#### `readThemeFile($slug, $relativePath): array|false`

Returns `{content, size, mtime}`. Path traversal safe.

#### `writeThemeFile($slug, $relativePath, $content, $syncToDB = true): bool`

Write file and optionally sync to database.

#### `createThemeFile($slug, $relativePath, $content = ''): bool`

Create a new file. Blocks hidden files and executable extensions.

#### `createThemeFolder($slug, $relativePath): bool`

Create a new directory.

#### `deleteThemeFile($slug, $relativePath): bool`

Delete a file.

#### `deleteThemeFolder($slug, $relativePath): bool`

Recursively delete a folder. Cannot delete the theme root.

#### `renameThemePath($slug, $oldPath, $newPath): bool`

Rename or move a file/folder. Blocks rename to executable extensions.

#### `getFlatFileList($slug): array|false`

Returns flat list of all relative file paths in the theme.

---

### 14.4 Theme Options Methods

#### `getThemeOptions($slug): array|false`

Read option definitions from `functions/options.php`.

#### `themeHasFunctions($slug): bool`

Check if `functions/` directory has any PHP files.

#### `getThemeOptionValues($slug): array`

Read saved values from `default.json`.

#### `saveThemeOptionValues($slug, $values): bool`

Write values to `default.json`.

#### `getMergedThemeOptions($slug): array`

Merge default values with saved values. Image options include `_width` and `_height` sub-keys.

---

### 14.5 Mini-Plugin Methods

#### `listMiniPlugins($slug): array`

Discover all plugins in `functions/plugins/*/plugin.json`. Returns `[{id, name, description, version, author, has_init, has_options, has_admin, has_js, has_css, dir}, ...]`.

#### `getMiniPluginStates($slug): array`

Read `functions/plugins_enabled.json`. Returns `{pluginId: bool, ...}`.

#### `saveMiniPluginStates($slug, $states): bool`

Save enable/disable states to `plugins_enabled.json`.

#### `getMiniPluginOptions($slug, $pluginId): array|false`

Read option definitions from the plugin's `options.php`.

#### `getMiniPluginOptionValues($slug, $pluginId): array`

Read saved values from the plugin's `default.json`.

#### `saveMiniPluginOptionValues($slug, $pluginId, $values): bool`

Save option values to the plugin's `default.json`.

#### `getMergedMiniPluginOptions($slug, $pluginId): array`

Merge plugin defaults with saved values.

#### `loadMiniPlugins($slug): void`

Load all enabled mini-plugins' `init.php` files. Sets globals: `$fmz_plugin_options`, `$fmz_plugin_dir`, `$fmz_plugin_id`, `$fmz_theme_slug`.

#### `getMiniPluginAssets($slug): array`

Collect `{js: [...urls], css: [...urls]}` for all enabled plugins.

---

### 14.6 Dev Mode & Utilities

#### `getActiveThemeSlug(): string|false`

Get the slug of the currently active (default) theme.

#### `slug($name): string`

Convert a theme name to a URL-safe slug. Lowercase, non-alphanumeric characters replaced with `-`.

#### `getThemeFilesHash($slug): string`

Compute MD5 hash of all `path|mtime|size` entries. Used for change detection in auto-sync.

#### `getErrors(): string[]`

Returns accumulated error messages from the last operation.

#### `cleanup(): void`

Remove temporary directory after export operations.

---

## 15. Editor API Endpoints

All endpoints are accessed through the FMZ Studio admin module. Base URL: `admin/index.php?module=fmzstudio-{action}`.

All write endpoints require CSRF token (`my_post_key`) and valid license.

| Endpoint | Method | Parameters | Response |
|----------|--------|------------|----------|
| `api_filetree&slug=X` | GET | `slug` | JSON file tree array |
| `api_readfile&slug=X&path=Y` | GET | `slug`, `path` | JSON `{content, size, mtime}` |
| `api_savefile` | POST | `slug`, `path`, `content`, `my_post_key` | JSON `{success, time, errors}` |
| `api_filelist&slug=X&path=Y` | GET | `slug`, `path` | JSON `{files: [...]}` |
| `api_createfile` | POST | `slug`, `path`, `content`, `my_post_key` | JSON `{success, errors}` |
| `api_createfolder` | POST | `slug`, `path`, `my_post_key` | JSON `{success, errors}` |
| `api_deletefile` | POST | `slug`, `path`, `my_post_key` | JSON `{success, errors}` |
| `api_deletefolder` | POST | `slug`, `path`, `my_post_key` | JSON `{success, errors}` |
| `api_rename` | POST | `slug`, `old_path`, `new_path`, `my_post_key` | JSON `{success, errors}` |
| `api_sync` | POST | `slug`, `my_post_key` | JSON `{success, time}` |
| `api_upload_asset` | POST | `file` (multipart), `field`, `my_post_key` | JSON `{success, url, field}` |

---

## 16. Image Upload API

**Endpoint:** `xmlhttp.php?action=fmz_wysiwyg_upload`  
**Method:** POST (multipart/form-data)

**Validation checks:**
1. User must be logged in
2. Valid CSRF token (`my_post_key`)
3. Valid posthash (links attachment to draft post)
4. MIME type must be image (jpg, png, gif, webp, bmp)
5. File extension must match MIME
6. File size must be within FMZ WYSIWYG limit (supersedes global attachment settings)
7. Images-per-post limit enforced from FMZ WYSIWYG settings

**Save path:** MyBB's attachment directory (`uploads/YYYYMM/{filename}.attach`)

**Response:**
```json
{
    "success": true,
    "aid": 42,
    "url": "attachment.php?aid=42",
    "width": 800,
    "height": 600
}
```

Images are stored as standard MyBB attachments, served via `attachment.php`, and referenced in posts as `[attachment=aid]` BBCode.

---

## 17. Page Builder API

All Page Builder API calls go through `admin/index.php?module=fmzstudio-pages_api`.

| Sub-Action | Method | Parameters | Description |
|------------|--------|------------|-------------|
| `save` | POST | `title`, `slug`, `content`, `status`, `meta_title`, `meta_description`, `allowed_groups`, `custom_css`, `custom_js` | Create or update a page |
| `get` | GET | `pid` | Get page data by ID |
| `reorder` | POST | `order[]` (array of PIDs) | Update page display order |
| `check_slug` | GET | `slug`, `pid` (optional) | Check slug uniqueness, returns suggested alternatives |
| `set_front_page` | POST | `front_page` (PID, `default`, or `portal`) | Set the forum front page |

---

## 18. Security Model

### File Extension Whitelist

The editor and file operations block all executable file types. Only these extensions are allowed:

```
html, htm, css, js, json, txt, md, xml, svg, ini, yml, yaml,
less, scss, map, csv, log, tpl, mustache, hbs
```

Blocked extensions include: `php, phtml, phar, php3, php4, php5, php7, phps, cgi, pl, py, rb, sh, bat, cmd, exe, com`

### Path Traversal Protection

All file operations (read, write, create, delete, rename) canonicalize paths and verify they remain within the theme directory. Relative path components (`..`, `./`) are resolved before the check.

### CSRF Protection

All write operations (save, create, delete, rename, sync, upload) require a valid `my_post_key` CSRF token.

### License Gating

Write operations in the admin module require a valid license. Read-only operations (browsing themes) do not.

### Upload Validation

Image uploads via `xmlhttp.php?action=fmz_wysiwyg_upload` and `api_upload_asset`:
- Login required
- CSRF token validated
- MIME type checked via `ext-fileinfo`
- File extension must match detected MIME
- File size enforced (configurable, default 5MB for assets, max 20MB for ZIP imports)

### Permission System

The admin module defines 6 permission keys checked via `admin_fmzstudio_permissions()`:
- `manage` — Manage themes (sync, activate, delete)
- `import_export` — Import and export themes
- `options` — Edit theme options
- `plugins` — Manage mini-plugins
- `settings` — Edit studio settings
- `pages` — Manage pages

Extensible via the `admin_fmzstudio_permissions` hook.

---

## 19. Troubleshooting

### Theme not showing changes after editing files

1. Check **Auto-Sync** is enabled: **ACP → FMZ Studio → Studio Settings**.
2. If disabled, click **Sync** in **FMZ Studio → Manage**.
3. Clear theme cache: delete files inside `cache/themes/`.
4. Hard-refresh: **Ctrl+Shift+R**.

### Theme Options not saving

1. Check `options.php` for PHP syntax errors.
2. Verify `default.json` is writable by the web server.
3. Ensure option keys are unique — duplicates silently overwrite.

### Mini Plugin not loading

1. Confirm the plugin is **enabled** in **FMZ Studio → Manage Plugins**.
2. Verify `plugin.json` has a valid `"id"` field.
3. Verify `init.php` exists and has no syntax errors.
4. Check PHP error log for details.

### WYSIWYG Editor not appearing

1. Ensure the **FMZ WYSIWYG Editor** plugin is enabled.
2. Check browser console for JavaScript errors.
3. Verify `headerinclude.html` loads jQuery and Bootstrap before WYSIWYG scripts.
4. Make sure SCEditor is not conflicting — WYSIWYG hides it via CSS.

### Images not uploading in WYSIWYG

1. Ensure the MyBB `uploads/` directory exists and is writable.
2. Check PHP `upload_max_filesize` and `post_max_size` in `php.ini`.
3. Check the WYSIWYG plugin's **Max File Size** setting.
4. Verify the posting form contains a valid `posthash` hidden field.

### Page Builder 404 errors

1. Verify `.htaccess` rewrite rules are in place (see [URL Routing](#url-routing)).
2. Check `mod_rewrite` is enabled: `apache2ctl -M | grep rewrite`.
3. Ensure `AllowOverride All` is set for the MyBB directory.
4. For Nginx, add `try_files` rule.

### Front page not working

1. Ensure the selected page is **Published** (not draft).
2. Go to **FMZ Studio → Page Manager** and re-select the front page.
3. Clear MyBB cache: **ACP → Tools & Maintenance → Cache Manager → Rebuild All**.

### Broken layout after import

1. Re-sync: **FMZ Studio → Manage → Sync**.
2. Check Bootstrap vendor files exist in `vendor/`.
3. Verify `headerinclude.html` references correct vendor paths.
4. Set the theme as default: **ACP → Themes**.

### Color changes not applying

1. Colors only override when they differ from defaults.
2. Ensure you're editing the correct palette (Light vs Dark) matching your `color_mode`.
3. Hard-refresh: **Ctrl+Shift+R** to bypass browser cache.

### Loading bar not visible

1. Ensure **Loading Bar** is **Yes** in **Theme Options → Layout & Effects**.
2. If accent color matches background, the bar won't be visible.
3. The bar only shows during page navigation (not initial load).

### Forum icons not showing

1. **ACP → Forums & Posts → Forum Management** → edit the forum.
2. Check the icon section.
3. If using uploaded image, verify `uploads/forum_icons/` exists and is writable.

### Status updates not working

1. Enable **Status Updates** in **FMZ Studio → Manage Plugins → Profile Extras → Settings**.
2. Check `fmz_user_statuses` table exists in database.
3. Verify user is logged in (guests cannot post statuses).

### Editor not loading

1. Check browser console for errors (Monaco CDN may be blocked).
2. Verify license is active — editor requires valid license.
3. Try a different browser (Monaco requires modern browser).
4. Check network tab — Monaco loads from `cdn.jsdelivr.net`.

### Auto-Sync not detecting changes

1. Verify **Auto-Sync** is enabled in **Studio Settings**.
2. Must be logged in as an admin (`cancp=1`).
3. Check browser console for polling errors.
4. Increase sync interval if server is slow.

### Plugin settings page showing wrong content

1. This was a known routing issue (fixed in v2.0.0).
2. Ensure `module_meta.php` is up to date.
3. Action is extracted from the module parameter: `explode('-', $mybb->get_input('module'), 2)`.

---

*Built with ❤️ by [Tektove](https://tektove.com)*
