<?php
/**
 * FMZ Studio — Admin Module Meta
 *
 * Registers FMZ Studio as a top-level navigation item in the ACP
 * with sidebar sub-menu items: Manage, Import, Export, Theme Options.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

function fmzstudio_meta()
{
    global $page, $plugins, $cache;

    // Only show when the plugin is active
    $active_plugins = $cache->read('plugins');
    if (empty($active_plugins['active']) || !in_array('fmz', $active_plugins['active'])) {
        return false;
    }

    // Ensure Bootstrap Icons CSS is available on every ACP page (for top-nav icon)
    $page->extra_header .= '<link rel="stylesheet" href="../themes/fmz-default/vendor/bootstrap-icons.min.css" />';

    $sub_menu = array();
    $sub_menu['10'] = array("id" => "manage",        "title" => "<i class=\"bi bi-palette2\"></i> Manage Themes",   "link" => "index.php?module=fmzstudio-manage");
    $sub_menu['20'] = array("id" => "import_export", "title" => "<i class=\"bi bi-arrow-left-right\"></i> Import / Export", "link" => "index.php?module=fmzstudio-import_export");
    $sub_menu['40'] = array("id" => "options",       "title" => "<i class=\"bi bi-sliders\"></i> Global FMZ Options",   "link" => "index.php?module=fmzstudio-options");
    $sub_menu['42'] = array("id" => "options_header_footer", "title" => "<i class=\"bi bi-layout-text-window\"></i> Header & Footer", "link" => "index.php?module=fmzstudio-options_header_footer");

    // Page Manager — only show if fmz-pagebuilder mini plugin is enabled
    require_once MYBB_ROOT . 'inc/plugins/fmzstudio/core.php';
    $fmzCore = new FMZStudio();
    $pbSlug = $fmzCore->getActiveThemeSlug();
    if ($pbSlug) {
        $pbStates = $fmzCore->getMiniPluginStates($pbSlug);
        if (!empty($pbStates['fmz-pagebuilder'])) {
            $sub_menu['45'] = array("id" => "pages", "title" => "<i class=\"bi bi-file-earmark-richtext\"></i> Page Manager", "link" => "index.php?module=fmzstudio-pages");
        }

        // Dynamic side nav items for enabled plugins with options
        $allPlugins = $fmzCore->listMiniPlugins($pbSlug);
        $dispOrder = 51;
        foreach ($allPlugins as $p) {
            $isEnabled = !isset($pbStates[$p['id']]) || $pbStates[$p['id']];
            if ($isEnabled && ($p['has_options'] || $p['has_admin'])) {
                $sub_menu[(string)$dispOrder] = array(
                    "id"    => "plugin_" . $p['id'],
                    "title" => "<i class=\"bi bi-puzzle\"></i> " . $p['name'],
                    "link"  => "index.php?module=fmzstudio-plugin_settings&plugin=" . urlencode($p['id'])
                );
                $dispOrder++;
            }
        }
    }

    $sub_menu['70'] = array("id" => "plugins",       "title" => "<i class=\"bi bi-plug\"></i> Manage Plugins",  "link" => "index.php?module=fmzstudio-plugins");
    $sub_menu['80'] = array("id" => "settings",      "title" => "<i class=\"bi bi-gear\"></i> Studio Settings", "link" => "index.php?module=fmzstudio-settings");
    $sub_menu['85'] = array("id" => "license",       "title" => "<i class=\"bi bi-key\"></i> License",          "link" => "index.php?module=fmzstudio-license");

    $sub_menu = $plugins->run_hooks("admin_fmzstudio_menu", $sub_menu);

    $page->add_menu_item("<i class=\"bi bi-brush\"></i> FMZ Studio", "fmzstudio", "index.php?module=fmzstudio-manage", 50, $sub_menu);

    return true;
}

function fmzstudio_action_handler($action)
{
    global $page, $plugins;

    $page->active_module = "fmzstudio";

    $actions = array(
        'manage'        => array('active' => 'manage',        'file' => 'fmzstudio.php'),
        'import_export' => array('active' => 'import_export', 'file' => 'fmzstudio.php'),
        'import'        => array('active' => 'import_export', 'file' => 'fmzstudio.php'),
        'export'        => array('active' => 'import_export', 'file' => 'fmzstudio.php'),
        'options'       => array('active' => 'options',       'file' => 'fmzstudio.php'),
        'options_header_footer' => array('active' => 'options_header_footer', 'file' => 'fmzstudio.php'),
        'plugins'       => array('active' => 'plugins',       'file' => 'fmzstudio.php'),
        'plugin_settings' => array('active' => 'plugin_settings', 'file' => 'fmzstudio.php'),
        'settings'      => array('active' => 'settings',      'file' => 'fmzstudio.php'),
        'license'       => array('active' => 'license',       'file' => 'fmzstudio.php'),
        'editor'        => array('active' => 'manage',        'file' => 'fmzstudio.php'),
        'pages'         => array('active' => 'pages',         'file' => 'fmzstudio.php'),
        'pages_add'     => array('active' => 'pages',         'file' => 'fmzstudio.php'),
        'pages_edit'    => array('active' => 'pages',         'file' => 'fmzstudio.php'),
        'pages_delete'  => array('active' => 'pages',         'file' => 'fmzstudio.php'),
        'pages_components' => array('active' => 'pages',      'file' => 'fmzstudio.php'),
        'pages_api'     => array('active' => 'pages',         'file' => 'fmzstudio.php'),
    );

    $actions = $plugins->run_hooks("admin_fmzstudio_action_handler", $actions);

    if (isset($actions[$action])) {
        $page->active_action = $actions[$action]['active'];
        // Dynamic active state for plugin_settings — highlight the correct plugin in the sidebar
        if ($action === 'plugin_settings' && !empty($_GET['plugin'])) {
            $page->active_action = 'plugin_' . preg_replace('/[^a-z0-9\-_]/', '', $_GET['plugin']);
        }
        return $actions[$action]['file'];
    } else {
        $page->active_action = "manage";
        return "fmzstudio.php";
    }
}

function fmzstudio_admin_permissions()
{
    global $plugins;

    $admin_permissions = array(
        "manage"        => "Can manage themes",
        "import_export" => "Can import and export themes",
        "options"       => "Can manage theme options",
        "plugins"       => "Can manage theme plugins",
        "settings"      => "Can manage plugin settings",
        "pages"         => "Can manage custom pages",
    );

    $admin_permissions = $plugins->run_hooks("admin_fmzstudio_permissions", $admin_permissions);

    return array("name" => "FMZ Studio", "permissions" => $admin_permissions, "disporder" => 50);
}
