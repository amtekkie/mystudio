# Create a theme with MyStudio

This guide takes you from nothing to a working theme on your forum. No PHP needed.

**Before you start:** MyStudio is installed and activated (see [Getting started](getting-started.md)), and you can reach your forum's files, either on your own computer or over FTP/SFTP.

If you haven't read it yet, [How the theme system works](theme-system.md) explains the ideas behind these steps in five minutes.

---

## Option A: copy the default theme (recommended)

This is the quickest way to get a complete, good-looking theme you can change bit by bit.

### 1. Copy the theme folder

Copy `themes/mystudio-default/` to a new folder with your theme's name, for example `themes/my-theme/`.

Use lowercase letters, numbers and hyphens for the folder name. It becomes the theme's **slug**, its short ID.

### 2. Give it a new name

Open `themes/my-theme/theme.json` and change the `name`:

```json
{
    "name": "My Theme",
    ...
}
```

The name must be different from every other theme. MyStudio uses it to tell themes apart.

### 3. Copy the theme's PHP and settings (optional)

The default theme has some PHP that adds extras: the sidebar, the logo, language strings and a settings form. If you want those in your theme too, copy these two folders as well:

| Copy from | To |
|-----------|----|
| `inc/plugins/mystudio/themes/mystudio-default/` | `inc/plugins/mystudio/themes/my-theme/` |
| `admin/modules/mystudio/themes/mystudio-default/` | `admin/modules/mystudio/themes/my-theme/` |

Skip this step if you want a theme that is HTML and CSS only.

### 4. Sync it

In the Admin CP, go to **MyStudio → Manage Themes**. Your theme shows up with the status **Not Synced**. Click its **Options** button and choose **Sync from Disk**.

### 5. Make it the forum's theme

On the same page, click your theme's **Options** button and choose **Set as Default**. Then open your forum and press **Ctrl+Shift+R** to reload without the browser cache.

You should now see your copy of the theme. 🎉

### 6. Start changing things

Open **MyStudio → Manage Themes → Edit Theme** for the built-in editor, or open the folder in your own code editor.

A good first change is the accent colour. Open `css/color-default.css` and change `--tekbb-accent`. Save, then reload the forum. (If you used your own editor, sync first, or turn on auto-sync.)

---

## Option B: start from scratch

Build a theme from the ground up when you want full control. MyBB fills in any template you don't provide with its built-in default, so you can start very small.

### 1. Create the folder and manifest

```
themes/my-theme/
├── theme.json
└── css/
    └── global.css
```

`theme.json`:

```json
{
    "name": "My Theme",
    "version": "1839",
    "stylesheets": [
        { "name": "global.css", "attachedto": "", "order": 1 }
    ]
}
```

`css/global.css`:

```css
body {
    background: #0f172a;
    color: #e2e8f0;
    font-family: system-ui, sans-serif;
}
a { color: #2dd4bf; }
```

### 2. Sync and activate

Same as Option A, steps 4 and 5. Your forum now uses MyBB's standard templates with your CSS on top.

### 3. Override templates one at a time

To change a template, create a file with **the template's exact MyBB name**:

```
themes/my-theme/templates/footer/footer.html
```

**Tip:** to get the original content, open **Templates & Style → Templates** in the Admin CP, find the template, and copy its code into your file. Or copy it from `themes/mystudio-default/templates/`.

Then run a **full sync**. A new file always needs one.

### 4. Add JavaScript (optional)

Put scripts in `themes/my-theme/js/`. A sync copies them to the forum's `jscripts/` folder. Load them from a template, usually `headerinclude`:

```html
<script src="{$mybb->asset_url}/jscripts/my-theme.js"></script>
```

Give your script files unique names. They all land in the same `jscripts/` folder as every other theme's.

---

## Add a settings form to your theme

Let forum admins change your theme without editing files.

1. Create `admin/modules/mystudio/themes/my-theme/options.php`:

   ```php
   <?php
   return array(
       'welcome_text' => array(
           'title'       => 'Welcome message',
           'description' => 'Shown at the top of the forum index.',
           'type'        => 'text',
           'default'     => 'Welcome to our community!',
       ),
   );
   ```

2. Use the value in any template:

   ```html
   <div class="welcome">{$mybb->ms_theme_options['welcome_text']}</div>
   ```

3. Admins change it in the Admin CP. The value is saved in `themes/my-theme/default.json`.

Available types: `text`, `textarea`, `yesno`, `select`, `radio`, `color`, `color_presets`, `numeric`, `image`, `icon_chooser`. For `select`, `radio` and `color_presets`, add an `options` list of `value => label` pairs.

## Add PHP to your theme

Sometimes a template needs data MyBB doesn't give it. Theme PHP goes in `inc/plugins/mystudio/themes/my-theme/hooks.php`. It's loaded on every page while your theme is active:

```php
<?php
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

global $plugins;
$plugins->add_hook('index_start', 'mytheme_index_greeting');

function mytheme_index_greeting()
{
    global $mybb, $mytheme_greeting;
    $name = $mybb->user['uid'] ? htmlspecialchars_uni($mybb->user['username']) : 'guest';
    $mytheme_greeting = "Hello, {$name}!";
}
```

Then use `{$mytheme_greeting}` in the `index` template. (MyBB only gives templates access to variables set as globals.)

Two safety rules:

- **Escape anything a user typed** with `htmlspecialchars_uni()` before it goes into HTML.
- **Never put PHP files inside `themes/`.** MyStudio blocks it for security.

## Share your theme

**MyStudio → Import / Export → Export** downloads your theme as a ZIP. It includes the theme files, its PHP and its options form. Anyone with MyStudio can import that ZIP on their forum.

## Troubleshooting

| Problem | Fix |
|---------|-----|
| My change doesn't show up | Sync, then hard-reload with **Ctrl+Shift+R**. |
| My new template is ignored | New files need a **full** sync, not just a save. Check the file name matches the MyBB template name exactly. |
| A second copy of my theme appeared | You changed `name` in `theme.json`. MyStudio treats a new name as a new theme. Change it back, or delete the old copy. |
| My CSS file isn't loading | Add it to `stylesheets` in `theme.json`, then sync. |
| "Failed the MyBB template security check" | The template contains `{${...}}` or similar syntax that would let it run PHP. MyBB blocks this in every template; remove it. Put logic in your theme's `hooks.php` instead. |
| Import says some files are "not allowed in a theme folder" | Theme ZIPs may only contain web assets (HTML, CSS, JS, JSON, images, fonts, media). PHP belongs in `functions/` or `lang/` inside the ZIP, and dotfiles like `.htaccess` are refused. |
| A setting shows `{$mybb->ms_theme_options[...]}` as text | The option key is misspelt, or the theme's `options.php` isn't in `admin/modules/mystudio/themes/{slug}/`. |

Next: [Create a module](create-a-module.md) to add features that work with any theme.
