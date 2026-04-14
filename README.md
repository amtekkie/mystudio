# MyStudio

**A modular theme and extension manager for MyBB 1.8.**

MyStudio gives you a proper file-based workflow for building MyBB themes. Instead of editing templates through the database, you work with real files on disk: HTML templates, CSS stylesheets, JavaScript, JSON configs, and PHP hooks. MyStudio takes care of syncing everything to the database so MyBB can use it.

It comes with a built-in Monaco code editor (the same engine behind VS Code), a ZIP-based import/export system, a module architecture for theme-scoped extensions, and a Page Builder for creating standalone pages with clean URLs. The whole thing is open source.

## What's Included

**MyStudio Plugin** handles all the heavy lifting: file-to-database syncing, theme import/export, the Admin CP interface, the editor, and the module loader.

**MyStudio Default Theme** is a complete MyBB frontend theme built on Bootstrap 5.3.8 with Bootstrap Icons 1.11.3 (both self-hosted, no CDN needed). It ships with five modules:

- **Editor Extras** adds Bootstrap Icons to the toolbar, image paste/drag-drop upload, emoji picker, GIF search, syntax highlighted code blocks, auto-save, word count, and @mentions to SCEditor.
- **Forum Display Extras** shows last poster avatars, a user info modal on click, subforum column layouts, card-style forum listings, and usergroup color formatting.
- **Forum Icons** lets admins assign Bootstrap Icons or uploaded images to individual forums, with status-aware coloring (accent for new posts, muted for read).
- **Profile Extras** adds profile banner customization (upload, solid color, or gradient) plus a status update system with privacy levels.
- **Page Builder** creates standalone pages using a Monaco HTML editor, with clean URL routing, front page override, per-page CSS/JS, group permissions, and template variables.

## Requirements

- MyBB 1.8.38 or later
- PHP 8.0 or later
- MySQL 5.7+ or MariaDB 10.3+
- Apache 2.4+ with `mod_rewrite`, or Nginx
- PHP extensions: `zip`, `fileinfo`, `json`, `curl`
- Writable directories: `themes/`, `uploads/`, `cache/`, `jscripts/`

## Getting Started

1. Upload the following to your MyBB root:
   - `inc/plugins/ms.php`
   - `inc/plugins/mystudio/`
   - `admin/modules/mystudio/`
   - `jscripts/mystudio/`
   - `themes/mystudio-default/`

2. Make sure `themes/`, `uploads/`, `cache/themes/`, and `jscripts/` are writable by your web server.

3. Go to **Admin CP > Configuration > Plugins**, find **MyStudio**, and click **Install & Activate**.

4. Head to **MyStudio > Manage Themes** and click **Sync** next to MyStudio Default.

5. Go to **Admin CP > Themes** and set the synced theme as your board default. Do a hard refresh (Ctrl+Shift+R) in your browser.

6. Optionally, configure branding under **MyStudio > Studio Settings** (logo, favicon) and enable modules under **MyStudio > Manage Extensions**.

If you want clean URLs for the Page Builder, add these rewrite rules to your `.htaccess`:

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-zA-Z0-9\-_/]+)$ misc.php?ms_page=$1 [L,QSA]
```

## How It Works

Themes live on disk as directories inside `themes/`. Each theme has a `theme.json` manifest, a `css/` folder for stylesheets, a `templates/` folder for MyBB templates (as `.html` files), a `js/` folder for scripts, and a `functions/` folder for PHP hooks and modules.

When you click **Sync** in the Admin CP (or use auto-sync during development), MyStudio reads all those files, builds a MyBB-compatible XML document, and imports it into the database using MyBB's own `import_theme_xml()`. Changed templates get an incremental update instead of a full reimport. CSS files get cached to `cache/themes/`, and JS files get deployed to `jscripts/`.

The sync engine also works in reverse: if you have a theme that only exists in the database, you can extract it to disk with one click.

### Auto-Sync (Dev Mode)

For development, you can enable auto-sync in **Studio Settings**. This injects a polling script on the frontend that checks for file changes every few seconds. When it detects a change, it triggers a sync and reloads the page. This only runs for admin users and should be turned off in production.

## Theme Directory Layout

```
themes/my-theme/
├── theme.json              manifest (name, version, stylesheets, JS list)
├── default.json            saved option values (auto-generated)
├── css/                    stylesheets (synced to DB + cached)
├── templates/              .html files (each one becomes a MyBB template)
│   ├── header/
│   │   └── header.html     template name: "header", group: "header"
│   ├── footer/
│   │   └── footer.html
│   └── ungrouped/
│       ├── headerinclude.html
│       └── htmldoctype.html
├── js/                     scripts (deployed to jscripts/ on sync)
├── functions/
│   ├── hooks.php           frontend hook registrations
│   ├── options.php         theme option definitions
│   └── modules/            theme-scoped extensions
│       └── my-module/
│           ├── plugin.json
│           ├── init.php
│           ├── options.php
│           ├── css/        auto-injected
│           └── js/         auto-injected
├── lang/                   language packs
│   └── en/
│       └── frontend.lang.php
└── vendor/                 third-party assets
```

Templates use standard MyBB `{$variable}` syntax. The filename without `.html` becomes the template name in the database, and the subdirectory becomes the template group.

## Admin CP Pages

MyStudio adds a top-level **MyStudio** section to the Admin CP:

| Page | What it does |
|------|-------------|
| **Manage Themes** | Lists all themes (synced and on-disk-only). Sync, edit, export, activate, deactivate, or delete. |
| **Import / Export** | Upload a theme ZIP to import, or download any theme as a ZIP. |
| **Page Manager** | Create and manage standalone pages (needs the Page Builder module). |
| **Module Settings** | Per-module settings pages, shown dynamically in the sidebar for each module that has options. |
| **Studio Settings** | General settings (enable/disable, upload size limit), theme settings (loading bar), branding (logo, favicon), and dev settings (auto-sync). |

The **Theme Editor** is accessible from Manage Themes. It opens a full-page Monaco editor with a file tree, multi-tab editing, Emmet support, and Ctrl+S to save and sync in one step.

## Modules

Modules are theme-scoped extensions that live inside `functions/modules/`. They're auto-discovered, loaded at runtime when enabled, and can register MyBB hooks, define their own options, bundle CSS/JS assets (auto-injected on frontend pages), and even provide custom admin pages.

### Creating a Module

1. Create a folder: `functions/modules/my-module/`

2. Add a `plugin.json` manifest:
   ```json
   {
       "id": "my-module",
       "name": "My Module",
       "version": "1.0.0",
       "description": "What this module does.",
       "author": "Your Name"
   }
   ```

3. Add an `init.php` that registers hooks:
   ```php
   <?php
   global $plugins;
   $plugins->add_hook('pre_output_page', 'my_module_output');

   function my_module_output(&$contents)
   {
       // your code here
       return $contents;
   }
   ```

4. Optionally, add `options.php` with an array of option definitions (supported types: `text`, `textarea`, `yesno`, `select`, `radio`, `color`, `numeric`, `image`, `icon_chooser`, `nav_links`, `toolbar_builder`).

5. Put any CSS/JS files in `css/` and `js/` folders. They'll be injected automatically on all frontend pages when the module is enabled.

6. Enable the module in **MyStudio > Manage Extensions**.

### Globals Available in init.php

Your module's `init.php` has access to `$mybb`, `$plugins`, `$ms_plugin_options` (this module's options), `$ms_plugin_dir` (path to this module's folder), `$ms_plugin_id`, `$ms_theme_slug`, and `$mybb->ms_theme_options` (all theme-level options).

## Creating a Theme

The quickest way is to duplicate the default theme:

1. Copy `themes/mystudio-default/` to `themes/my-theme/`.
2. Edit `theme.json` and change the `"name"` field.
3. Sync in Admin CP.
4. Customize templates, CSS, and hooks.

Or start fresh with a minimal `theme.json`:

```json
{
    "name": "My Theme",
    "version": "1839"
}
```

Add a `css/global.css` and at least these templates: `headerinclude.html`, `htmldoctype.html`, `header.html`, `footer.html`. Then sync.

You can also import themes as ZIP files through the Admin CP.

## File Structure

```
inc/plugins/
├── ms.php                          plugin entry point
└── mystudio/
    └── core.php                    MyStudio class (sync, import/export, options, modules)

admin/modules/mystudio/
├── module_meta.php                 ACP menu and permissions
└── mystudio.php                    all admin pages and API endpoints

jscripts/mystudio/
├── editor.js                       Monaco theme editor
├── pagebuilder.js                  Monaco page editor
└── pagebuilder.css                 page builder styles

themes/mystudio-default/
├── theme.json
├── css/                            9 stylesheets
├── templates/                      42 template groups
├── js/                             6 scripts (main, PM poller, posting extras, etc.)
├── functions/
│   ├── hooks.php                   frontend hooks
│   ├── options.php                 branding options (logo, favicon)
│   ├── posting-extras.php          portal feed, sidebar, quick search, likes
│   ├── ms_pm_count.php             unread PM count AJAX endpoint
│   └── modules/
│       ├── ms-editor-extras/       SCEditor enhancements
│       ├── ms-forum-display-extras/ avatars, user modal, card layout
│       ├── ms-forum-icons/         custom forum icons
│       ├── ms-pagebuilder/         standalone page builder
│       └── ms-profile-extras/      profile banners, status updates
├── lang/en/                        language strings
└── vendor/                         Bootstrap 5.3.8, Bootstrap Icons 1.11.3
```

## Contributing

MyStudio is open source and contributions are welcome! If you run into a bug, have a feature idea, or just want to improve something, here's how you can help:

- **Report bugs** by opening an issue on [GitHub](https://github.com/amtekkie/mystudio/issues). Include your MyBB version, PHP version, and steps to reproduce.
- **Suggest features** through GitHub issues. Describe what you'd like to see and why it would be useful.
- **Submit pull requests** for bug fixes, new modules, or improvements. Fork the repo, make your changes on a branch, and open a PR.
- **Write a module** and share it with the community. The module system is designed to be easy to extend.
- **Help with translations** by adding language files under `lang/` in your theme.

If you're not sure where to start, check the open issues or just reach out. All contributions, big or small, are appreciated.

## License

MyStudio is open source software. See the repository for license details.
