<?php
/**
 * MyStudio — Admin Module Meta
 *
 * Registers MyStudio as a top-level navigation item in the ACP
 * with sidebar sub-menu items: Manage, Import, Export, Settings.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

function mystudio_meta()
{
    global $page, $plugins, $cache;

    // Only show when the plugin is active
    $active_plugins = $cache->read('plugins');
    if (empty($active_plugins['active']) || !in_array('ms', $active_plugins['active'])) {
        return false;
    }

    // Ensure Bootstrap Icons CSS is available on every ACP page (for top-nav icon)
    $page->extra_header .= '<link rel="stylesheet" href="../themes/mystudio-default/vendor/bootstrap-icons.min.css" />';

    $sub_menu = array();
    $sub_menu['10'] = array("id" => "manage",        "title" => "<i class=\"bi bi-palette2\"></i> Manage Themes",   "link" => "index.php?module=mystudio-manage");
    $sub_menu['20'] = array("id" => "import_export", "title" => "<i class=\"bi bi-arrow-left-right\"></i> Import / Export", "link" => "index.php?module=mystudio-import_export");

    // Page Manager — only show if ms-pagebuilder module exists
    require_once MYBB_ROOT . 'inc/plugins/mystudio/core.php';
    $msCore = new MyStudio();
    $pbSlug = $msCore->getActiveThemeSlug();
    if ($pbSlug) {
        $allModules = $msCore->listModules($pbSlug);
        foreach ($allModules as $p) {
            if ($p['id'] === 'ms-pagebuilder') {
                $sub_menu['45'] = array("id" => "pages", "title" => "<i class=\"bi bi-file-earmark-richtext\"></i> Page Manager", "link" => "index.php?module=mystudio-pages");
            }
        }

        // Dynamic side nav items for modules with options
        $dispOrder = 51;
        foreach ($allModules as $p) {
            if ($p['has_options'] || $p['has_admin']) {
                $sub_menu[(string)$dispOrder] = array(
                    "id"    => "plugin_" . $p['id'],
                    "title" => "<i class=\"bi bi-puzzle\"></i> " . $p['name'],
                    "link"  => "index.php?module=mystudio-plugin_settings&plugin=" . urlencode($p['id'])
                );
                $dispOrder++;
            }
        }
    }

    $sub_menu['75'] = array("id" => "library",       "title" => "<i class=\"bi bi-box-seam\"></i> Module Library",  "link" => "index.php?module=mystudio-library");
    $sub_menu['80'] = array("id" => "settings",      "title" => "<i class=\"bi bi-gear\"></i> Studio Settings", "link" => "index.php?module=mystudio-settings");

    $sub_menu = $plugins->run_hooks("admin_mystudio_menu", $sub_menu);

    $page->add_menu_item("<i class=\"bi bi-brush\"></i> MyStudio", "mystudio", "index.php?module=mystudio-manage", 50, $sub_menu);

    return true;
}

function mystudio_action_handler($action)
{
    global $page, $plugins;

    $page->active_module = "mystudio";

    $actions = array(
        'manage'        => array('active' => 'manage',        'file' => 'mystudio.php'),
        'import_export' => array('active' => 'import_export', 'file' => 'mystudio.php'),
        'import'        => array('active' => 'import_export', 'file' => 'mystudio.php'),
        'export'        => array('active' => 'import_export', 'file' => 'mystudio.php'),
        'plugin_settings' => array('active' => 'plugin_settings', 'file' => 'mystudio.php'),
        'settings'      => array('active' => 'settings',      'file' => 'mystudio.php'),
        'editor'        => array('active' => 'manage',        'file' => 'mystudio.php'),
        'pages'         => array('active' => 'pages',         'file' => 'mystudio.php'),
        'pages_add'     => array('active' => 'pages',         'file' => 'mystudio.php'),
        'pages_edit'    => array('active' => 'pages',         'file' => 'mystudio.php'),
        'pages_delete'  => array('active' => 'pages',         'file' => 'mystudio.php'),
        'pages_components' => array('active' => 'pages',      'file' => 'mystudio.php'),
        'pages_api'     => array('active' => 'pages',         'file' => 'mystudio.php'),
        'library'       => array('active' => 'library',        'file' => 'mystudio.php'),
        'install_module' => array('active' => 'library',       'file' => 'mystudio.php'),
        'uninstall_module' => array('active' => 'library',     'file' => 'mystudio.php'),
    );

    $actions = $plugins->run_hooks("admin_mystudio_action_handler", $actions);

    if (isset($actions[$action])) {
        $page->active_action = $actions[$action]['active'];
        // Dynamic active state for plugin_settings — highlight the correct plugin in the sidebar
        if ($action === 'plugin_settings' && !empty($_GET['plugin'])) {
            $page->active_action = 'plugin_' . preg_replace('/[^a-z0-9\-_]/', '', $_GET['plugin']);
        }
        return $actions[$action]['file'];
    } else {
        $page->active_action = "manage";
        return "mystudio.php";
    }
}

function mystudio_admin_permissions()
{
    global $plugins;

    $admin_permissions = array(
        "manage"        => "Can manage themes",
        "import_export" => "Can import and export themes",
        "options"       => "Can manage theme options",
        "settings"      => "Can manage extension settings",
        "pages"         => "Can manage custom pages",
    );

    $admin_permissions = $plugins->run_hooks("admin_mystudio_permissions", $admin_permissions);

    return array("name" => "MyStudio", "permissions" => $admin_permissions, "disporder" => 50);
}
