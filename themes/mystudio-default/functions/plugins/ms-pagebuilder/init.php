<?php
/**
 * MyStudio Page Builder — Mini Plugin Init
 *
 * Handles:
 *  1. Auto-creation of DB table (ms_pages) on first use
 *  2. Clean URL page routing (misc_start hook)
 *  3. Front page override (index_start hook)
 *
 * This file is loaded by MyStudio's mini plugin loader when the plugin is
 * enabled in MyStudio > Manage Plugins.
 *
 * Routing approach:
 *   .htaccess rewrites /slug → misc.php?ms_page=slug
 *   This plugin hooks misc_start to intercept and serve those requests.
 *
 * @version 4.0.0
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

global $plugins;

// Admin context — register admin hooks for DB table management
if (defined('IN_ADMINCP')) {
    $plugins->add_hook('admin_mystudio_action_handler', 'ms_pagebuilder_ensure_tables');
}

// Frontend hooks
if (!defined('IN_ADMINCP')) {
    // Page routing via misc.php clean URLs
    $plugins->add_hook('misc_start', 'ms_pb_misc_start');

    // Front page routing (only on index.php)
    if (defined('THIS_SCRIPT') && THIS_SCRIPT === 'index.php') {
        $plugins->add_hook('index_start', 'ms_pagebuilder_front_page');
    }
}

/* ══════════════════════════════════════════════════════════════
   MISC_START — Route handler for clean page URLs
   ══════════════════════════════════════════════════════════════ */

/**
 * Intercept misc.php requests to serve custom pages.
 * .htaccess rewrites /slug → misc.php?ms_page=slug
 */
function ms_pb_misc_start()
{
    global $mybb;

    if (!empty($mybb->input['ms_page'])) {
        require_once __DIR__ . '/renderer.php';
        ms_pb_serve_page($mybb->get_input('ms_page'));
        exit;
    }
}

/* ══════════════════════════════════════════════════════════════
   INDEX_START — Front page override
   ══════════════════════════════════════════════════════════════ */

/**
 * Serve a custom front page or portal instead of the forum index.
 *
 * Bypass with ?forums to access the normal forum index.
 * Setting stored in datacache as 'ms_front_page'.
 */
function ms_pagebuilder_front_page()
{
    global $mybb, $cache;

    // Bypass: ?forums shows normal forum index
    if (isset($mybb->input['forums'])) {
        return;
    }

    $fp = $cache->read('ms_front_page');
    if (empty($fp) || !is_array($fp) || empty($fp['type']) || $fp['type'] === 'default') {
        return;
    }

    // Portal redirect
    if ($fp['type'] === 'portal') {
        header('Location: ' . $mybb->settings['bburl'] . '/portal.php');
        exit;
    }

    // Custom MyStudio page
    if ($fp['type'] === 'page' && !empty($fp['slug'])) {
        require_once __DIR__ . '/renderer.php';
        ms_pb_serve_page($fp['slug']);
        exit;
    }
}

/**
 * Ensure the page builder DB tables exist when the admin module loads.
 * Called via admin_mystudio_action_handler hook.
 */
function ms_pagebuilder_ensure_tables($actions)
{
    global $db;

    // ── Pages table ──
    if (!$db->table_exists('ms_pages')) {
        $collation = $db->build_create_table_collation();
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "ms_pages (
                pid              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                title            VARCHAR(255) NOT NULL DEFAULT '',
                slug             VARCHAR(250) NOT NULL DEFAULT '',
                content          MEDIUMTEXT   NOT NULL,
                status           VARCHAR(20)  NOT NULL DEFAULT 'draft',
                meta_title       VARCHAR(255) NOT NULL DEFAULT '',
                meta_description TEXT         NOT NULL,
                allowed_groups   VARCHAR(500) NOT NULL DEFAULT '',
                custom_css       TEXT         NOT NULL,
                custom_js        TEXT         NOT NULL,
                author_uid       INT UNSIGNED NOT NULL DEFAULT 0,
                disporder        INT UNSIGNED NOT NULL DEFAULT 0,
                created_at       INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at       INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (pid),
                UNIQUE KEY slug (slug)
            ) ENGINE=MyISAM{$collation}
        ");
    }

    return $actions;
}
