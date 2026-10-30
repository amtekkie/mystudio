# How the MyStudio theme system works

A normal MyBB theme lives in the database. You edit it one template at a time in a text box in the Admin CP. MyStudio turns that around: **your theme is a folder of ordinary files**, and the database is just a copy MyBB reads from.

This page explains how that works, what goes where, and why it makes building themes with AI coding assistants so much easier.

## The one idea to remember

> **The files on disk are the truth. The database is a cache.**

You edit files. MyStudio copies them into the database. This copying step is called a **sync**. If the files and the database ever disagree, the files win. The next sync puts the database right.

```
  You edit files            Sync                 MyBB reads the database
 ┌──────────────────┐    ┌──────────┐    ┌───────────────────────────────┐
 │ themes/my-theme/ │ ─▶ │ MyStudio │ ─▶ │ templates, stylesheets, theme │ ─▶ your forum
 └──────────────────┘    └──────────┘    └───────────────────────────────┘
```

## What a theme folder looks like

Every theme is a folder inside `themes/`. Here is the shipped theme, trimmed down:

```
themes/mystudio-default/
├── theme.json          the theme's name, version and list of stylesheets
├── default.json        saved option values (written for you by the Admin CP)
├── css/
│   ├── global.css      a stylesheet
│   └── ...
├── templates/
│   ├── header/
│   │   └── header.html          becomes the MyBB template called "header"
│   ├── forumdisplay/
│   │   └── forumdisplay_thread.html
│   └── ungrouped/
│       └── headerinclude.html
├── js/                 scripts, copied to jscripts/ when you sync
├── images/             theme images, logos, favicons
└── vendor/             third-party files (Bootstrap, Bootstrap Icons)
```

Three rules cover almost everything:

1. **One template = one `.html` file.** The file name (without `.html`) is the template's name in MyBB. `templates/header/header.html` becomes the template `header`.
2. **The sub-folder is only for tidiness.** `header/`, `forumdisplay/` and so on group related templates so you can find them. MyBB doesn't care which folder a template is in.
3. **Stylesheets must be listed in `theme.json`.** A CSS file in `css/` is only loaded if `theme.json` mentions it (see below).

### `theme.json`

```json
{
    "name": "MyStudio Default",
    "version": "1839",
    "properties": {
        "editortheme": "default.css",
        "imgdir": "images"
    },
    "stylesheets": [
        { "name": "global.css",     "attachedto": "",               "order": 1 },
        { "name": "showthread.css", "attachedto": "showthread.php", "order": 2 }
    ]
}
```

| Field | Meaning |
|-------|---------|
| `name` | The theme's name in MyBB. **Changing it creates a new theme** on the next sync, because MyStudio finds the database copy by name. |
| `version` | The MyBB template version, e.g. `1839`. |
| `properties` | MyBB theme properties: editor style, image folder, and so on. |
| `stylesheets` | Which CSS files to load and where. `attachedto: ""` means every page. `"showthread.php"` means only that page. Separate several pages with `\|`. |

## Where the PHP lives (and why it's not in the theme)

Themes contain **no PHP at all**. That's a security rule: anything in `themes/` can be uploaded as a ZIP, and you never want an uploaded theme to run code on your server.

When a theme needs PHP (to add variables to templates, for example), that code lives in fixed places outside the theme:

| What | Where |
|------|-------|
| Theme hooks (PHP that runs on every page) | `inc/plugins/mystudio/themes/{theme}/hooks.php` |
| Language strings | `inc/plugins/mystudio/themes/{theme}/lang/en/*.lang.php` |
| Theme option definitions (the settings form) | `admin/modules/mystudio/themes/{theme}/options.php` |

`{theme}` is the theme's folder name, for example `mystudio-default`.

If you import an older theme ZIP that still has PHP inside it (`functions/` or `lang/` folders), MyStudio moves those files to the right places for you. Export packs them back into the ZIP.

## Syncing: getting your changes onto the forum

| You changed… | What to do |
|--------------|------------|
| An existing template or stylesheet, **in the Admin CP editor** (pictured below) | Nothing. Pressing **Ctrl+S** saves and updates the database straight away. |
| An existing file, **in your own editor** (VS Code etc.) | Run a sync: **MyStudio → Manage Themes → Options → Sync from Disk**. |
| **Added a new** template or stylesheet (any editor) | Run a full sync. Quick saves only update files the database already knows about. |
| `theme.json` | Run a full sync. |
| Theme PHP (`hooks.php`, language files) | Nothing. PHP is read fresh on every page load. |

![The built-in theme editor with the footer template open](screenshots/acp-theme-editor.png)

### Auto-sync while you develop

Turn on **MyStudio → Studio Settings → Developer Settings → Auto-sync**. While you're logged in as an admin, the forum checks your theme files every few seconds. When something changes, it syncs and reloads the page for you. Save in your editor, glance at the browser, done.

Turn auto-sync **off** on a live forum. It's a development tool.

### Syncing from the command line

Handy for scripts and AI assistants:

```bash
php -r "define('IN_MYBB',1); require 'inc/init.php'; require MYBB_ROOT.'inc/plugins/mystudio/core.php'; (new MyStudio)->syncToDatabase('my-theme');"
```

Run it from the forum's root folder. Replace `my-theme` with your theme's folder name.

## Theme options

Options let forum admins change a theme without touching code: a logo, an accent colour, a toggle.

1. **Define** them in `admin/modules/mystudio/themes/{theme}/options.php`:

   ```php
   <?php
   return array(
       'show_banner' => array(
           'title'       => 'Show welcome banner',
           'description' => 'Display a banner above the forum list.',
           'type'        => 'yesno',
           'default'     => '1',
       ),
   );
   ```

2. **Admins set** them in the Admin CP. Values are saved to `themes/{theme}/default.json`.
3. **Templates read** them as `{$mybb->ms_theme_options['show_banner']}`.

Option types: `text`, `textarea`, `yesno`, `select`, `radio`, `color`, `color_presets`, `numeric`, `image`, `icon_chooser`.

## Why files make AI-assisted development work

AI coding assistants such as Claude Code, GitHub Copilot and Cursor work with **files**. They search them, read them, edit them and show you a diff. A theme locked away in a database is invisible to them. A theme made of files is something they can work on straight away.

Here's what that gives you:

- **Everything is findable.** Ask "make the thread list rows taller" and the assistant can search the theme, find `templates/forumdisplay/forumdisplay_thread.html` and the matching CSS, and edit both. No copy-and-paste out of the Admin CP.
- **Every change is a diff you can review.** Changes are plain text in Git. You see exactly what the assistant changed, line by line, before you accept it. If you don't like it, revert it.
- **Mistakes stay small.** Themes hold no PHP, so an assistant restyling your forum can't break server code by accident. Theme PHP lives in one known place, which makes it easy to review separately.
- **The rules are written down.** This repository ships a `CLAUDE.md` file and skill files in `.claude/skills/`. They tell an assistant how MyStudio works and which mistakes to avoid. It reads them before it starts.
- **Instant feedback.** With auto-sync on, every save shows up in the browser within seconds. The assistant can also run the command-line sync above to check its own work.
- **Predictable names.** A template's file name is its MyBB name. An assistant that knows MyBB already knows where `postbit_classic` or `header_welcomeblock_member` live.

### A worked example

You type:

> Add a "Back to top" link to the footer, styled like the other footer links.

A file-aware assistant will:

1. Search `themes/my-theme/templates/` and find `footer/footer.html`.
2. Add the link next to the existing ones.
3. Find the footer styles in `css/global.css` and reuse the same class.
4. Run a sync (or let auto-sync do it).
5. Show you a two-file diff to approve.

No database, no copy-and-paste, nothing hidden.

### Tips for prompting

- **Name the theme folder** ("in `themes/my-theme`") so the assistant edits the right copy.
- **Say "and sync"** if you aren't using auto-sync.
- **Ask for a diff first** for big changes. Review it, then let the assistant apply it.
- **Keep PHP requests separate** ("add a hook in the theme's `hooks.php` that…"). It keeps reviews easy.

## Next steps

- [Create your own theme](create-a-theme.md)
- [Create a module (plugin)](create-a-module.md)
