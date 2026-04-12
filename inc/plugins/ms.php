<?php
/**
 * MyStudio is a Modular Themes & Extensions Manager for MyBB
 *
 * Allows importing and exporting MyBB themes as modular packages
 * (ZIP archives with separate template HTML files, CSS files, and a theme.json manifest).
 *
 * Installation:
 *   1. Upload inc/plugins/ms.php                → MYBB_ROOT/inc/plugins/ms.php
 *   2. Upload inc/plugins/mystudio/              → MYBB_ROOT/inc/plugins/mystudio/
 *   3. Upload admin/modules/style/mystudio.php   → MYBB_ROOT/admin/modules/style/mystudio.php
 *   4. Upload jscripts/mystudio/                 → MYBB_ROOT/jscripts/mystudio/
 *   5. Activate in ACP → Configuration → Plugins
 *   6. Navigate to ACP → Templates & Style → MyStudio
 *
 * Theme files live in MYBB_ROOT/themes/{slug}/ and remain editable via file manager.
 * Use the Sync tab to push file changes into the database.
 *
 * @version 2.1.0
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// No admin hooks needed — MyStudio registers itself as a standalone
// top-level module via admin/modules/mystudio/module_meta.php

// Frontend hooks
$plugins->add_hook('global_start', 'ms_global_start');
$plugins->add_hook('global_intermediate', 'ms_load_theme_extras');
$plugins->add_hook('pre_output_page', 'ms_inject_mini_plugin_assets');

// AJAX hook for dev auto-sync
$plugins->add_hook('xmlhttp', 'ms_xmlhttp_action');

// Admin-side: load mini-plugin init files so they can register admin hooks
if (defined('IN_ADMINCP')) {
    ms_load_admin_mini_plugins();
}

function ms_info()
{
    global $mybb;

    $name = 'MyStudio';

    // Link the title to the MyStudio page if the plugin is active
    if (isset($mybb->settings['ms_enabled']) && $mybb->settings['ms_enabled']) {
        $name = '<a href="index.php?module=mystudio-manage" style="text-decoration:none;color:inherit;font-weight:bold">MyStudio</a>';
    }

    return array(
        'name'          => $name,
        'description'   => 'MyStudio is a Modular Themes & Extensions Manager for MyBB',
        'website'       => '',
        'author'        => 'Tektove',
        'authorsite'    => 'https://tektove.com',
        'version'       => '2.1.0',
        'compatibility' => '18*',
        'codename'      => 'mystudio'
    );
}

function ms_install()
{
    global $db;

    // Remove any legacy settings group (settings are managed inside MyStudio admin)
    $db->delete_query('settinggroups', "name='mystudio'");

    // Remove old settings that might have a stale gid
    $db->delete_query('settings', "name LIKE 'ms_%'");

    // Insert settings with gid=0 so they don't appear in Configuration > Settings
    $settings = array(
        array(
            'name'        => 'ms_enabled',
            'title'       => 'Enable MyStudio',
            'description' => 'Master switch to enable or disable MyStudio.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 1,
            'gid'         => 0
        ),
        array(
            'name'        => 'ms_max_upload_mb',
            'title'       => 'Max Upload Size (MB)',
            'description' => 'Maximum allowed ZIP file size in megabytes.',
            'optionscode' => 'numeric',
            'value'       => '20',
            'disporder'   => 2,
            'gid'         => 0
        ),
        array(
            'name'        => 'ms_dev_auto_sync',
            'title'       => 'Auto Sync (Dev Mode)',
            'description' => 'Automatically sync theme files to the database when changes are detected. Only runs for admin users.',
            'optionscode' => 'yesno',
            'value'       => '0',
            'disporder'   => 3,
            'gid'         => 0
        ),
        array(
            'name'        => 'ms_dev_sync_interval',
            'title'       => 'Auto Sync Interval (seconds)',
            'description' => 'How often to check for file changes when Dev Mode Auto Sync is enabled.',
            'optionscode' => 'numeric',
            'value'       => '2',
            'disporder'   => 4,
            'gid'         => 0
        ),
        array(
            'name'        => 'ms_show_sidebar',
            'title'       => 'Enable Sidebar',
            'description' => 'Show a sidebar column on the forum index with stats, online users, and birthdays.',
            'optionscode' => 'yesno',
            'value'       => '0',
            'disporder'   => 5,
            'gid'         => 0
        ),
        array(
            'name'        => 'ms_loading_bar',
            'title'       => 'Page Loading Bar',
            'description' => 'Show an accent-colored progress bar at the top of the page during navigation.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 6,
            'gid'         => 0
        ),
    );

    foreach ($settings as $s) {
        $db->insert_query('settings', $s);
    }

    rebuild_settings();

    // Ensure directories exist
    @mkdir(MYBB_ROOT . 'inc/plugins/mystudio', 0755, true);
    @mkdir(MYBB_ROOT . 'themes', 0755, true);
    @mkdir(MYBB_ROOT . 'jscripts/mystudio', 0755, true);

    // Page Manager tables are handled by the ms_pagebuilder plugin
}

function ms_is_installed()
{
    global $db;
    $query = $db->simple_select('settings', 'name', "name='ms_enabled'");
    return (bool) $db->num_rows($query);
}

function ms_uninstall()
{
    global $db;

    $db->delete_query('settings',      "name LIKE 'ms_%'");
    $db->delete_query('settinggroups', "name='mystudio'");
    rebuild_settings();

    // Page Manager tables are handled by the ms_pagebuilder plugin

    // Delete all modular theme folders except mystudio-default
    $themesDir = MYBB_ROOT . 'themes';
    if (is_dir($themesDir)) {
        foreach (scandir($themesDir) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'mystudio-default') {
                continue;
            }
            $path = $themesDir . '/' . $entry;
            if (is_dir($path) && file_exists($path . '/theme.json')) {
                ms_rrmdir($path);
            }
        }
    }
}

/**
 * Recursively delete a directory and its contents.
 * Used by ms_uninstall() to clean up theme folders.
 */
function ms_rrmdir($dir)
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            ms_rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function ms_activate()
{
    global $db;

    // Migration: remove settings group from Configuration if it exists (moved to MyStudio Settings page)
    $query = $db->simple_select('settinggroups', 'gid', "name='mystudio'");
    if ($db->num_rows($query)) {
        $gid = $db->fetch_field($query, 'gid');
        // Orphan the settings so they stay in $mybb->settings but don't show in Configuration
        $db->update_query('settings', array('gid' => 0), "gid='" . intval($gid) . "'");
        $db->delete_query('settinggroups', "name='mystudio'");
        rebuild_settings();
    }
}

function ms_deactivate()
{
    // Settings persist until uninstall
}

/**
 * Called on every frontend page load via global_start.
 *
 * PHP 8.3 compatibility — pre-initialises template variables that
 * MyBB only sets conditionally; avoids "Undefined variable" warnings.
 */
function ms_global_start()
{
    global $lang;

    $vars = [
        'portal_link', 'calendar_link',
        'title', 'content', 'buttons',
        'now', 'debugstuff',
        'comma',
        'awaitingusers',
        'quicklogin',
        // Profile extras
        'banner_style', 'banner_change_overlay', 'banner_change_modal',
        'profile_avatar_overlay', 'profile_avatar_modal',
        'profile_statuses',
    ];
    foreach ($vars as $v) {
        if (!isset($GLOBALS[$v])) {
            $GLOBALS[$v] = '';
        }
    }

    if (is_object($lang) && !isset($lang->bottomlinks_current_time)) {
        $lang->bottomlinks_current_time = 'Current time:';
    }
}

/**
 * Load mini-plugin init.php files in admin context so they can register
 * admin hooks (e.g. forum management form fields).
 * Called at plugin load time (top-level) when IN_ADMINCP is defined.
 */
function ms_load_admin_mini_plugins()
{
    global $mybb, $db;

    if (empty($mybb->settings['ms_enabled'])) return;

    require_once MYBB_ROOT . 'inc/plugins/mystudio/core.php';
    $msCore = new MyStudio();
    $slug = $msCore->getActiveThemeSlug();
    if (!$slug) return;

    $modules = $msCore->listModules($slug);

    foreach ($modules as $p) {
        if ($p['has_init']) {
            $ms_plugin_options = $msCore->getMergedMiniPluginOptions($slug, $p['id']);
            $ms_plugin_dir = $p['dir'];
            $ms_plugin_id = $p['id'];
            $ms_theme_slug = $slug;
            include_once $p['dir'] . '/init.php';
        }
    }
}

/**
 * Called on global_intermediate (after theme & language are loaded).
 *
 * 1. Loads language strings from themes/{slug}/lang/{language}/*.lang.php
 * 2. Loads theme option values from themes/{slug}/default.json
 *
 * Language strings are merged into MyBB's $lang object.
 * Option values are available via $mybb->ms_theme_options['key'].
 *
 * In templates, theme developers can use {$mybb->ms_theme_options['key']}.
 */
function ms_load_theme_extras()
{
    global $mybb, $lang, $theme;

    if (defined('IN_ADMINCP')) return;
    if (empty($mybb->settings['ms_enabled'])) return;

    // $theme is available at global_intermediate
    if (empty($theme) || empty($theme['name'])) return;

    // Slugify the theme name
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($theme['name'])));
    $slug = trim($slug, '-');

    $themeDir = MYBB_ROOT . 'themes/' . $slug;
    if (!is_dir($themeDir)) return;

    /* ── 1. Load language files ── */
    $langName = $mybb->settings['bblanguage'] ?? 'english';
    if (isset($mybb->user['language']) && !empty($mybb->user['language'])) {
        $langName = $mybb->user['language'];
    }

    // Try full language name first, then short code fallback
    $shortCodes = array('english' => 'en', 'german' => 'de', 'french' => 'fr', 'spanish' => 'es');
    $langDir = $themeDir . '/lang/' . $langName;
    if (!is_dir($langDir) && isset($shortCodes[strtolower($langName)])) {
        $langDir = $themeDir . '/lang/' . $shortCodes[strtolower($langName)];
    }
    if (!is_dir($langDir)) {
        // Final fallback: try english, then en
        $langDir = $themeDir . '/lang/english';
        if (!is_dir($langDir)) {
            $langDir = $themeDir . '/lang/en';
        }
    }

    if (is_dir($langDir)) {
        foreach (scandir($langDir) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') continue;

            $l = array();
            @include $langDir . '/' . $file;

            if (!empty($l) && is_object($lang)) {
                foreach ($l as $k => $v) {
                    $lang->$k = $v;
                }
            }
        }
    }

    /* ── 2. Load theme option values ── */
    $optionsFile = $themeDir . '/default.json';
    if (file_exists($optionsFile)) {
        $values = @json_decode(file_get_contents($optionsFile), true);
        if (is_array($values) && !empty($values)) {
            $mybb->ms_theme_options = $values;
        }
    }

    // Ensure it's always an array so templates don't error
    if (!isset($mybb->ms_theme_options)) {
        $mybb->ms_theme_options = array();
    }

    /* -- 3. Load theme hooks file -- */
    $hooksFile = $themeDir . '/functions/hooks.php';
    if (file_exists($hooksFile)) {
        include_once $hooksFile;
    }

    /* -- 4. Load built-in modules -- */
    require_once MYBB_ROOT . 'inc/plugins/mystudio/core.php';
    $msCore = new MyStudio();
    $msCore->loadModules($slug);

    // Store slug for asset injection later
    $mybb->ms_active_slug = $slug;
}

/**
 * Called on pre_output_page to inject module CSS/JS into the page HTML.
 * This runs after the full page HTML is assembled so we can inject into <head>.
 *
 * @param  string &$contents  Full page HTML
 * @return string
 */
function ms_inject_mini_plugin_assets(&$contents)
{
    global $mybb;

    if (defined('IN_ADMINCP')) return;
    if (empty($mybb->settings['ms_enabled'])) return;
    if (empty($mybb->ms_active_slug)) return;

    require_once MYBB_ROOT . 'inc/plugins/mystudio/core.php';
    $msCore = new MyStudio();
    $assets = $msCore->getModuleAssets($mybb->ms_active_slug);

    $inject = '';
    foreach ($assets['css'] as $css) {
        $inject .= '<link rel="stylesheet" href="' . htmlspecialchars($css) . '" type="text/css" />' . "\n";
    }
    foreach ($assets['js'] as $js) {
        $inject .= '<script type="text/javascript" src="' . htmlspecialchars($js) . '"></script>' . "\n";
    }

    if (!empty($inject)) {
        // Inject before </head>
        $contents = str_replace('</head>', $inject . '</head>', $contents);
    }

    // Inject dev auto-sync polling script for admin users
    $devSyncScript = ms_get_dev_sync_script();
    if (!empty($devSyncScript)) {
        $contents = str_replace('</body>', $devSyncScript . '</body>', $contents);
    }

    return $contents;
}

/**
 * Handle the ms_dev_sync AJAX action.
 * Checks if theme files have changed (via modification-time hash) and
 * triggers syncToDatabase when changes are detected.
 *
 * GET  xmlhttp.php?action=ms_dev_sync_check   → returns { changed: bool, hash: string }
 * POST xmlhttp.php?action=ms_dev_sync_run     → performs sync, returns { success: bool, ... }
 */
function ms_xmlhttp_action()
{
    global $mybb, $plugins;

    if (empty($mybb->settings['ms_enabled'])) return;

    $action = $mybb->get_input('action');

    /* ── Load module xmlhttp handlers ──
     * Modules are normally loaded on global_intermediate which xmlhttp.php
     * doesn't fire. We load them here and then re-run the xmlhttp hook so any
     * newly-registered handlers execute. A static guard prevents infinite
     * recursion since this function is itself an xmlhttp hook handler.
     */
    static $miniLoaded = false;
    if (!$miniLoaded) {
        $miniLoaded = true;
        require_once MYBB_ROOT . 'inc/plugins/mystudio/core.php';
        $msCore = new MyStudio();
        $slug = $msCore->getActiveThemeSlug();
        if ($slug) {
            // Load built-in posting-extras (quick-search handler)
            $pexFile = MYBB_ROOT . 'themes/' . $slug . '/functions/posting-extras.php';
            if (file_exists($pexFile)) {
                include_once $pexFile;
            }

            $msCore->loadModules($slug);
            // Re-run xmlhttp hooks so module handlers can fire.
            // The static guard above prevents this function from looping.
            $plugins->run_hooks('xmlhttp');
        }
    }

    // Only handle our own dev-sync actions below
    if ($action !== 'ms_dev_sync_check' && $action !== 'ms_dev_sync_run') {
        return;
    }

    header('Content-Type: application/json; charset=UTF-8');

    // Must be enabled
    if (empty($mybb->settings['ms_enabled']) || empty($mybb->settings['ms_dev_auto_sync'])) {
        echo json_encode(array('error' => 'Dev auto-sync is disabled.'));
        exit;
    }

    // Must be an administrator
    if (empty($mybb->user['uid']) || (int)$mybb->usergroup['cancp'] !== 1) {
        echo json_encode(array('error' => 'Admin access required.'));
        exit;
    }

    require_once MYBB_ROOT . 'inc/plugins/mystudio/core.php';
    $msCore = new MyStudio();

    $slug = $msCore->getActiveThemeSlug();
    if (!$slug) {
        echo json_encode(array('error' => 'No active MyStudio theme.'));
        exit;
    }

    if ($action === 'ms_dev_sync_check') {
        // Compute current file hash and compare to cached one
        $currentHash = $msCore->getThemeFilesHash($slug);
        $cachedHash  = isset($mybb->settings['ms_dev_last_hash']) ? $mybb->settings['ms_dev_last_hash'] : '';

        echo json_encode(array(
            'changed' => ($currentHash !== $cachedHash),
            'hash'    => $currentHash
        ));
        exit;
    }

    if ($action === 'ms_dev_sync_run') {
        // Verify CSRF token for POST actions
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            echo json_encode(array('error' => 'Invalid security token.'));
            exit;
        }

        // Compute hash before sync
        $currentHash = $msCore->getThemeFilesHash($slug);

        $tid = $msCore->syncToDatabase($slug);

        if ($tid !== false) {
            // Store the hash so we don't sync again until next change
            global $db;
            // Use the settings table to persist the hash (non-visible setting)
            $query = $db->simple_select('settings', 'name', "name='ms_dev_last_hash'");
            if ($db->num_rows($query)) {
                $db->update_query('settings', array('value' => $db->escape_string($currentHash)), "name='ms_dev_last_hash'");
            } else {
                $db->insert_query('settings', array(
                    'name'        => 'ms_dev_last_hash',
                    'title'       => 'Dev Sync Last Hash',
                    'description' => 'Internal hash for auto-sync change detection.',
                    'optionscode' => 'text',
                    'value'       => $db->escape_string($currentHash),
                    'disporder'   => 0,
                    'gid'         => 0
                ));
            }
            rebuild_settings();
        }

        echo json_encode(array(
            'success' => $tid !== false,
            'tid'     => $tid,
            'hash'    => $currentHash,
            'errors'  => $msCore->getErrors()
        ));
        exit;
    }
}

/**
 * Injects auto-sync polling JavaScript into the page for admin users
 * when Dev Mode Auto Sync is enabled.
 *
 * This is called from ms_inject_mini_plugin_assets (pre_output_page hook)
 * so we hook into the existing injection mechanism.
 */
function ms_get_dev_sync_script()
{
    global $mybb;

    // Only inject for admin users with dev mode on
    if (empty($mybb->settings['ms_dev_auto_sync'])) return '';
    if (empty($mybb->user['uid']) || (int)$mybb->usergroup['cancp'] !== 1) return '';

    $interval = max(1, (int)($mybb->settings['ms_dev_sync_interval'] ?? 2)) * 1000;
    $postCode = $mybb->post_code;

    return '<script type="text/javascript">
(function(){
    var _msSyncBusy = false;
    var _msSyncInterval = ' . $interval . ';
    var _msPostKey = ' . json_encode($postCode) . ';

    function msDevSyncCheck() {
        if (_msSyncBusy) return;
        _msSyncBusy = true;

        var xhr = new XMLHttpRequest();
        xhr.open("GET", "xmlhttp.php?action=ms_dev_sync_check", true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            _msSyncBusy = false;
            if (xhr.status !== 200) return;
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.changed) {
                    msDevSyncRun();
                }
            } catch(e) {}
        };
        xhr.send();
    }

    function msDevSyncRun() {
        if (_msSyncBusy) return;
        _msSyncBusy = true;

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "xmlhttp.php?action=ms_dev_sync_run", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            _msSyncBusy = false;
            if (xhr.status !== 200) return;
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    console.log("[MyStudio Dev Sync] Theme synced successfully (TID: " + data.tid + ")");
                    // Reload the page with cache-busting to show updated CSS
                    var url = window.location.href.replace(/[?&]_mssync=\d+/, "");
                    var sep = url.indexOf("?") !== -1 ? "&" : "?";
                    window.location.href = url + sep + "_mssync=" + Date.now();
                } else if (data.errors && data.errors.length) {
                    console.warn("[MyStudio Dev Sync] Sync failed:", data.errors);
                }
            } catch(e) {}
        };
        xhr.send("my_post_key=" + encodeURIComponent(_msPostKey));
    }

    setInterval(msDevSyncCheck, _msSyncInterval);
    console.log("[MyStudio Dev Sync] Active — checking every " + (_msSyncInterval/1000) + "s");
})();
</script>
';
}
