<?php
/**
 * MyStudio User Profile Extras â€” Mini Plugin Init
 *
 * Provides:
 *  - Profile banner customization (upload image, solid color, gradient)
 *
 * Auto-creates required database tables on first load.
 *
 * @version 1.1.0
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

if (defined('IN_ADMINCP')) return;

global $plugins, $db, $mybb;

// — Store options in globals for use in functions —
$GLOBALS['ms_pe_options'] = isset($ms_plugin_options) ? $ms_plugin_options : array();

// — Auto-install: create tables if missing (DDL checks only run until the
//   schema flag is stored in datacache — normal page views do no SHOW TABLES) —
ms_pe_schema_ready();

/** Max banners kept per user (older rows + their files are pruned). */
if (!defined('MS_PE_MAX_BANNERS')) {
    define('MS_PE_MAX_BANNERS', 12);
}

// — Hooks: Banner —
$plugins->add_hook('member_profile_end',  'ms_profile_extras_banner');
$plugins->add_hook('usercp_start',        'ms_profile_extras_usercp');

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   AUTO-INSTALL
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

function ms_profile_extras_install()
{
    global $db;

    // Banners table
    if (!$db->table_exists('ms_user_banners')) {
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "ms_user_banners (
                bid        INT UNSIGNED NOT NULL AUTO_INCREMENT,
                uid        INT UNSIGNED NOT NULL DEFAULT 0,
                type       VARCHAR(20)  NOT NULL DEFAULT 'solid',
                value      TEXT         NOT NULL,
                is_active  TINYINT(1)   NOT NULL DEFAULT 0,
                dateline   INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (bid),
                KEY uid (uid),
                KEY uid_active (uid, is_active)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
        ");
    }

    // Migration: add text_color / link_color columns if missing
    if ($db->table_exists('ms_user_banners') && !$db->field_exists('text_color', 'ms_user_banners')) {
        $db->write_query("ALTER TABLE " . TABLE_PREFIX . "ms_user_banners ADD COLUMN text_color VARCHAR(20) NOT NULL DEFAULT '' AFTER value");
        $db->write_query("ALTER TABLE " . TABLE_PREFIX . "ms_user_banners ADD COLUMN link_color VARCHAR(20) NOT NULL DEFAULT '' AFTER text_color");
    }
}

/**
 * Run ms_profile_extras_install() once per schema version; remembered in
 * datacache 'ms_pe_schema' (datacache is preloaded, so the check is free).
 */
function ms_pe_schema_ready()
{
    global $cache;

    $version = 2;
    $schema = $cache->read('ms_pe_schema');
    if (is_array($schema) && isset($schema['version']) && (int)$schema['version'] >= $version) {
        return;
    }

    ms_profile_extras_install();
    $cache->update('ms_pe_schema', array('version' => $version));
}

/** Module option with default. */
function ms_pe_option($key, $default)
{
    $opts = isset($GLOBALS['ms_pe_options']) && is_array($GLOBALS['ms_pe_options']) ? $GLOBALS['ms_pe_options'] : array();
    return (isset($opts[$key]) && $opts[$key] !== '') ? $opts[$key] : $default;
}

function ms_pe_banners_enabled()
{
    return (string)ms_pe_option('enable_banners', '1') !== '0';
}

/**
 * Public URL for a stored banner value: absolute http(s) URLs (URL banners)
 * are used as-is, local paths are prefixed with bburl.
 */
function ms_pe_banner_url($value)
{
    global $mybb;

    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    return $mybb->settings['bburl'] . '/' . ltrim($value, '/');
}

/**
 * Raw CSS for a banner (callers HTML-escape it for attribute context).
 */
function ms_pe_banner_css($type, $value)
{
    switch ($type) {
        case 'upload':
            $url = str_replace(
                array("'", '"', '\\', '(', ')', "\r", "\n"),
                array('%27', '%22', '%5C', '%28', '%29', '', ''),
                ms_pe_banner_url($value)
            );
            return "background-image:url('{$url}');background-size:cover;background-position:center;";
        case 'solid':
        case 'gradient':
            return "background:{$value};";
    }
    return '';
}

/**
 * Delete a locally uploaded banner file (never touches URL banners or
 * anything outside uploads/banners/).
 */
function ms_pe_delete_banner_file($type, $value)
{
    if ($type !== 'upload' || !preg_match('#^uploads/banners/banner_\d+_\d+(_[a-f0-9]+)?\.(jpe?g|png|gif|webp)$#i', $value)) {
        return;
    }
    $path = realpath(MYBB_ROOT . $value);
    $base = realpath(MYBB_ROOT . 'uploads/banners');
    if ($path !== false && $base !== false && strpos($path, $base . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
        @unlink($path);
    }
}

/**
 * Keep only the newest MS_PE_MAX_BANNERS rows for a user; delete the rest and their files.
 */
function ms_pe_prune_banners($uid)
{
    global $db;

    $uid = (int)$uid;
    $query = $db->simple_select('ms_user_banners', 'bid, type, value, is_active', "uid={$uid}", array(
        'order_by' => 'dateline DESC, bid',
        'order_dir' => 'DESC',
        'limit_start' => MS_PE_MAX_BANNERS,
        'limit' => 1000,
    ));
    $bids = array();
    while ($row = $db->fetch_array($query)) {
        if ((int)$row['is_active'] === 1) {
            continue;
        }
        ms_pe_delete_banner_file($row['type'], $row['value']);
        $bids[] = (int)$row['bid'];
    }
    if ($bids) {
        $db->delete_query('ms_user_banners', 'bid IN (' . implode(',', $bids) . ')');
    }
}


/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   USERCP HANDLER â€” banner AJAX actions
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

function ms_profile_extras_usercp()
{
    global $mybb;

    $msAction = $mybb->get_input('ms_action');
    if (!empty($msAction)) {
        ms_profile_extras_ajax();
        return;
    }
}

function ms_profile_extras_banner()
{
    global $mybb, $db, $lang, $memprofile, $uid, $templates;

    // Banners switched off in the module options: no banner, no change UI
    if (!ms_pe_banners_enabled()) {
        $GLOBALS['banner_style'] = '';
        $GLOBALS['banner_change_overlay'] = '';
        $GLOBALS['banner_change_modal']   = '';
        ms_pe_inject_latest_posts();
        return;
    }

    // â”€â”€ Load active banner for this profile user â”€â”€
    $banner_style = '';
    $query = $db->simple_select('ms_user_banners', '*', "uid=" . (int)$uid . " AND is_active=1", array('limit' => 1));
    $banner = $db->fetch_array($query);

    if ($banner) {
        $banner_style = htmlspecialchars_uni(ms_pe_banner_css($banner['type'], $banner['value']));
        // Append custom text/link colors as CSS variables
        if (!empty($banner['text_color'])) {
            $banner_style .= "--banner-text-color:" . htmlspecialchars_uni($banner['text_color']) . ";";
        }
        if (!empty($banner['link_color'])) {
            $banner_style .= "--banner-link-color:" . htmlspecialchars_uni($banner['link_color']) . ";";
        }
    }
    $GLOBALS['banner_style'] = $banner_style;

    // â”€â”€ Banner change overlay + modal (own profile only) â”€â”€
    if ($mybb->user['uid'] <= 0 || $mybb->user['uid'] != $uid) {
        $GLOBALS['banner_change_overlay'] = '';
        $GLOBALS['banner_change_modal']   = '';
    } else {

    // Overlay (inside .profile-banner)
    $GLOBALS['banner_change_overlay'] = '<div class="profile-banner-change" data-bs-toggle="modal" data-bs-target="#ms_banner_modal"><i class="bi bi-image me-1"></i> ' . $lang->ms_change_banner . '</div>';

    // â”€â”€ Build previous banners gallery â”€â”€
    $galleryItems = '';
    $prevQuery = $db->simple_select('ms_user_banners', '*', "uid=" . (int)$uid, array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => MS_PE_MAX_BANNERS));
    while ($row = $db->fetch_array($prevQuery)) {
        $active = $row['is_active'] ? ' active' : '';
        $bid = (int)$row['bid'];
        $itemCss = ms_pe_banner_css($row['type'], $row['value']);
        if ($itemCss !== '') {
            $galleryItems .= '<div class="banner-gallery-item' . $active . '" data-bid="' . $bid . '" style="' . htmlspecialchars_uni($itemCss) . '"></div>';
        }
    }
    if (empty($galleryItems)) {
        $galleryItems = '<div class="text-muted small text-center py-2">' . $lang->ms_no_previous_banners . '</div>';
    }

    $postKey = htmlspecialchars_uni($mybb->post_code);
    $bburl   = $mybb->settings['bburl'];

    // â”€â”€ Gradient presets â”€â”€
    $gradientPresets = array(
        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)',
        'linear-gradient(135deg, #fccb90 0%, #d57eeb 100%)',
        'linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%)',
        'linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%)',
        'linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%)',
        'linear-gradient(135deg, #cc2b5e 0%, #753a88 100%)',
        'linear-gradient(135deg, #ee9ca7 0%, #ffdde1 100%)',
    );
    $gradientSwatches = '';
    foreach ($gradientPresets as $g) {
        $ge = htmlspecialchars($g, ENT_QUOTES);
        $gradientSwatches .= '<div class="gradient-preset-swatch" data-gradient="' . $ge . '" style="background:' . $ge . '"></div>';
    }

    // â”€â”€ Solid color presets â”€â”€
    $solidPresets = array('#0d9488','#0369a1','#4338ca','#7e22ce','#be123c','#b45309','#059669','#1e293b','#64748b','#dc2626','#d97706','#16a34a');
    $solidSwatches = '';
    foreach ($solidPresets as $c) {
        $ce = htmlspecialchars($c, ENT_QUOTES);
        $solidSwatches .= '<div class="solid-preset-swatch" data-color="' . $ce . '" style="background:' . $ce . '"></div>';
    }

    // â”€â”€ Current text/link colors for pre-populating pickers â”€â”€
    $curTextColor = ($banner && !empty($banner['text_color'])) ? htmlspecialchars_uni($banner['text_color']) : '';
    $curLinkColor = ($banner && !empty($banner['link_color'])) ? htmlspecialchars_uni($banner['link_color']) : '';
    $curTextColorPicker = $curTextColor ?: '#1f2937';
    $curLinkColorPicker = $curLinkColor ?: '#0d9488';
    $previewTextStyle = $curTextColor ? 'color:' . $curTextColor . ';' : '';
    $previewLinkStyle = $curLinkColor ? 'color:' . $curLinkColor . ';' : '';

    eval("\$GLOBALS['banner_change_modal'] = \"" . $templates->get("member_profile_banner_modal") . "\";");
    } // end else (own profile banner modal)

    // â”€â”€ Latest Posts Block â”€â”€
    ms_pe_inject_latest_posts();
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   PROFILE â€” Latest posts block (on member profile)
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

function ms_pe_inject_latest_posts()
{
    global $mybb, $db, $uid, $templates, $lang;

    $bburl = $mybb->settings['bburl'];
    $profileUid = (int)$uid;

    // Fetch latest 5 posts by this user (visible forums only)
    $unviewable = get_unviewable_forums(true);
    $inactiveforums = get_inactive_forums();
    $fidNot = '';
    $excludeForums = array();
    if ($unviewable) {
        $excludeForums = array_merge($excludeForums, explode(',', $unviewable));
    }
    if ($inactiveforums) {
        $excludeForums = array_merge($excludeForums, explode(',', $inactiveforums));
    }
    if (!empty($excludeForums)) {
        $excludeForums = array_unique(array_map('intval', $excludeForums));
        $fidNot = " AND p.fid NOT IN (" . implode(',', $excludeForums) . ")";
    }

    // Forums where the viewer may only see their own threads
    global $forum_cache;
    if (!is_array($forum_cache)) {
        cache_forums();
    }
    $onlyOwn = array();
    foreach ((array)$forum_cache as $ofid => $oforum) {
        $ofid = (int)$ofid;
        if (in_array($ofid, $excludeForums)) {
            continue;
        }
        $operms = forum_permissions($ofid);
        if (!empty($operms['canonlyviewownthreads'])) {
            $onlyOwn[] = $ofid;
        }
    }
    if ($onlyOwn) {
        $fidNot .= " AND (p.fid NOT IN (" . implode(',', $onlyOwn) . ") OR t.uid=" . (int)$mybb->user['uid'] . ")";
    }

    $query = $db->query("
        SELECT p.pid, p.tid, p.fid, p.subject, p.dateline, p.message,
               t.subject AS thread_subject, t.tid AS thread_tid,
               f.name AS forum_name
        FROM " . TABLE_PREFIX . "posts p
        LEFT JOIN " . TABLE_PREFIX . "threads t ON t.tid = p.tid
        LEFT JOIN " . TABLE_PREFIX . "forums f ON f.fid = p.fid
        WHERE p.uid={$profileUid} AND p.visible=1 AND t.visible=1{$fidNot}
        ORDER BY p.dateline DESC
        LIMIT 5
    ");

    $ms_lp_items = '';
    while ($post = $db->fetch_array($query)) {
        $ms_lp_thread_subject = htmlspecialchars_uni($post['thread_subject']);
        $ms_lp_forum_name     = htmlspecialchars_uni($post['forum_name']);
        $ms_lp_thread_url     = htmlspecialchars_uni($bburl . '/showthread.php?tid=' . (int)$post['thread_tid'] . '&pid=' . (int)$post['pid'] . '#pid' . (int)$post['pid']);
        $ms_lp_time_ago       = my_date('relative', $post['dateline']);

        // Excerpt: strip BBCode/HTML, truncate
        $ms_lp_excerpt = strip_tags(str_replace(array('[/quote]','[/code]'), '', $post['message']));
        $ms_lp_excerpt = preg_replace('/\[.*?\]/', '', $ms_lp_excerpt);
        $ms_lp_excerpt = trim($ms_lp_excerpt);
        if (my_strlen($ms_lp_excerpt) > 120) {
            $ms_lp_excerpt = my_substr($ms_lp_excerpt, 0, 120) . '...';
        }
        $ms_lp_excerpt = htmlspecialchars_uni($ms_lp_excerpt);

        eval("\$ms_lp_items .= \"" . $templates->get("member_profile_latest_posts_row") . "\";");
    }

    if (empty($ms_lp_items)) {
        $ms_lp_items = '<div class="text-center text-muted py-3"><i class="bi bi-file-text d-block mb-1" style="font-size:1.5rem"></i><span class="small">' . $lang->ms_no_posts_yet . '</span></div>';
    }

    $findPostsUrl = htmlspecialchars_uni($bburl . '/search.php?action=finduser&uid=' . $profileUid);
    $ms_lp_view_all_link = '<a href="' . $findPostsUrl . '" class="small text-muted text-decoration-none">' . $lang->ms_view_all . ' <i class="bi bi-arrow-right"></i></a>';

    eval("\$GLOBALS['profile_latest_posts'] = \"" . $templates->get("member_profile_latest_posts") . "\";");
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   AJAX HANDLER â€” usercp_start hook (ms_action parameter)
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

function ms_profile_extras_ajax()
{
    global $mybb, $db, $lang;

    $action = $mybb->get_input('ms_action');
    if (empty($action)) return;

    // Must be logged in
    if ($mybb->user['uid'] <= 0) {
        ms_profile_extras_json(array('error' => $lang->ms_error_not_logged_in), 403);
        return;
    }

    // Verify post key for all write actions (default-deny: only skip for explicitly read-only actions)
    $readOnlyActions = array('get_banner');
    if (!in_array($action, $readOnlyActions)) {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            ms_profile_extras_json(array('error' => $lang->ms_error_invalid_token), 403);
            return;
        }
    }

    // Banners switched off in the module options (removing an old one is still allowed)
    if (!ms_pe_banners_enabled() && $action !== 'remove_banner') {
        ms_profile_extras_json(array('error' => $lang->ms_error_banners_disabled));
        return;
    }

    switch ($action) {
        case 'save_banner':
            ms_pe_save_banner();
            break;
        case 'remove_banner':
            ms_pe_remove_banner();
            break;
        case 'activate_banner':
            ms_pe_activate_banner();
            break;
        case 'update_banner_colors':
            ms_pe_update_banner_colors();
            break;
    }
}


/* â”€â”€ Banner: Save â”€â”€ */
function ms_pe_save_banner()
{
    global $mybb, $db, $lang;

    $uid  = (int)$mybb->user['uid'];
    $type = $mybb->get_input('banner_type');
    if (!in_array($type, array('upload', 'solid', 'gradient'))) {
        ms_profile_extras_json(array('error' => $lang->ms_error_invalid_banner_type));
        return;
    }

    $value = '';
    if ($type === 'upload') {
        // Handle file upload
        if (!empty($_FILES['banner_file']['name'])) {
            $uploadDir = MYBB_ROOT . 'uploads/banners/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            // Prevent PHP execution in upload directory (also for dirs created elsewhere)
            $htaccess = $uploadDir . '.htaccess';
            if (is_dir($uploadDir) && !file_exists($htaccess)) {
                @file_put_contents($htaccess, "<FilesMatch \"\\.(php|phtml|phar|php[3-8]|shtml)$\">\nOrder Allow,Deny\nDeny from all\n</FilesMatch>\n");
            }

            if (!isset($_FILES['banner_file']['error']) || $_FILES['banner_file']['error'] !== UPLOAD_ERR_OK) {
                ms_profile_extras_json(array('error' => $lang->ms_error_invalid_upload));
                return;
            }

            $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');
            $ext = strtolower(pathinfo($_FILES['banner_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                ms_profile_extras_json(array('error' => $lang->ms_error_invalid_file_type . implode(', ', $allowed)));
                return;
            }

            // Validate MIME type
            $allowedMimes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detectedMime = finfo_file($finfo, $_FILES['banner_file']['tmp_name']);
                finfo_close($finfo);
                if ($detectedMime === false || !in_array($detectedMime, $allowedMimes)) {
                    ms_profile_extras_json(array('error' => $lang->ms_error_file_content_mismatch));
                    return;
                }
            }

            // Max size from the module option banner_max_size (KB)
            $maxKb = (int)ms_pe_option('banner_max_size', 2048);
            if ($maxKb <= 0) {
                $maxKb = 2048;
            }
            if ($_FILES['banner_file']['size'] > $maxKb * 1024) {
                ms_profile_extras_json(array('error' => $lang->sprintf($lang->ms_error_file_too_large_kb, my_number_format($maxKb))));
                return;
            }

            $filename = 'banner_' . $uid . '_' . TIME_NOW . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $uploadDir . $filename;

            // Validate resolved path stays within uploads directory
            $realUploadDir = realpath($uploadDir);
            $realBase = realpath(MYBB_ROOT . 'uploads');
            if ($realUploadDir === false || $realBase === false || strpos($realUploadDir, $realBase) !== 0) {
                ms_profile_extras_json(array('error' => $lang->ms_error_invalid_upload_dir));
                return;
            }

            if (!move_uploaded_file($_FILES['banner_file']['tmp_name'], $dest)) {
                ms_profile_extras_json(array('error' => $lang->ms_error_invalid_upload));
                return;
            }
            $value = 'uploads/banners/' . $filename;
        } elseif ($mybb->get_input('banner_url')) {
            // URL-based upload
            $url = trim($mybb->get_input('banner_url'));
            if (!my_validate_url($url)) {
                ms_profile_extras_json(array('error' => $lang->ms_error_invalid_url));
                return;
            }
            // Only allow http/https schemes
            $urlScheme = parse_url($url, PHP_URL_SCHEME);
            if (!in_array(strtolower($urlScheme), array('http', 'https'))) {
                ms_profile_extras_json(array('error' => $lang->ms_error_https_only));
                return;
            }
            $value = $url;
        } else {
            ms_profile_extras_json(array('error' => $lang->ms_error_no_file_or_url));
            return;
        }
    } elseif ($type === 'solid') {
        $value = $mybb->get_input('banner_value');
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
            ms_profile_extras_json(array('error' => $lang->ms_error_invalid_color));
            return;
        }
    } elseif ($type === 'gradient') {
        $value = $mybb->get_input('banner_value');
        // Basic sanity check for gradient CSS
        if (strpos($value, 'gradient') === false) {
            ms_profile_extras_json(array('error' => $lang->ms_error_invalid_gradient));
            return;
        }
        // Sanitize: only allow gradient-safe characters
        $value = preg_replace('/[^a-zA-Z0-9(),.\s%#\-]/', '', $value);
    }

    // Sanitize text/link colors (optional, hex only)
    $textColor = trim($mybb->get_input('text_color'));
    $linkColor = trim($mybb->get_input('link_color'));
    if ($textColor !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $textColor)) $textColor = '';
    if ($linkColor !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $linkColor)) $linkColor = '';

    // Deactivate current banners
    $db->update_query('ms_user_banners', array('is_active' => 0), "uid={$uid}");

    // Insert new banner
    $db->insert_query('ms_user_banners', array(
        'uid'        => $uid,
        'type'       => $db->escape_string($type),
        'value'      => $db->escape_string($value),
        'text_color' => $db->escape_string($textColor),
        'link_color' => $db->escape_string($linkColor),
        'is_active'  => 1,
        'dateline'   => TIME_NOW,
    ));

    // Keep at most MS_PE_MAX_BANNERS per user (old rows + files removed)
    ms_pe_prune_banners($uid);

    // Build CSS for response
    $css = ms_pe_banner_css($type, $value);

    ms_profile_extras_json(array('success' => true, 'css' => $css, 'text_color' => $textColor, 'link_color' => $linkColor));
}

/* â”€â”€ Banner: Update Colors Only â”€â”€ */
function ms_pe_update_banner_colors()
{
    global $mybb, $db, $lang;
    $uid = (int)$mybb->user['uid'];

    // Find active banner
    $query = $db->simple_select('ms_user_banners', '*', "uid={$uid} AND is_active=1", array('limit' => 1));
    $banner = $db->fetch_array($query);
    if (!$banner) {
        ms_profile_extras_json(array('error' => $lang->ms_error_no_active_banner));
        return;
    }

    $textColor = trim($mybb->get_input('text_color'));
    $linkColor = trim($mybb->get_input('link_color'));
    if ($textColor !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $textColor)) $textColor = '';
    if ($linkColor !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $linkColor)) $linkColor = '';

    $db->update_query('ms_user_banners', array(
        'text_color' => $db->escape_string($textColor),
        'link_color' => $db->escape_string($linkColor),
    ), "bid=" . (int)$banner['bid']);

    // Rebuild CSS from existing banner
    $css = ms_pe_banner_css($banner['type'], $banner['value']);

    ms_profile_extras_json(array('success' => true, 'css' => $css, 'text_color' => $textColor, 'link_color' => $linkColor));
}

/* â”€â”€ Banner: Remove â”€â”€ */
function ms_pe_remove_banner()
{
    global $mybb, $db;
    $uid = (int)$mybb->user['uid'];

    // Delete the active banner row and its uploaded file
    $query = $db->simple_select('ms_user_banners', 'bid, type, value', "uid={$uid} AND is_active=1");
    $bids = array();
    while ($row = $db->fetch_array($query)) {
        ms_pe_delete_banner_file($row['type'], $row['value']);
        $bids[] = (int)$row['bid'];
    }
    if ($bids) {
        $db->delete_query('ms_user_banners', 'bid IN (' . implode(',', $bids) . ") AND uid={$uid}");
    }

    ms_profile_extras_json(array('success' => true, 'css' => ''));
}

/* â”€â”€ Banner: Activate previous â”€â”€ */
function ms_pe_activate_banner()
{
    global $mybb, $db, $lang;
    $uid = (int)$mybb->user['uid'];
    $bid = $mybb->get_input('bid', MyBB::INPUT_INT);

    // Verify ownership
    $query = $db->simple_select('ms_user_banners', '*', "bid={$bid} AND uid={$uid}", array('limit' => 1));
    $banner = $db->fetch_array($query);
    if (!$banner) {
        ms_profile_extras_json(array('error' => $lang->ms_error_banner_not_found));
        return;
    }

    // Deactivate all, activate this one
    $db->update_query('ms_user_banners', array('is_active' => 0), "uid={$uid}");
    $db->update_query('ms_user_banners', array('is_active' => 1), "bid={$bid}");

    // Update text/link colors if provided
    $textColor = trim($mybb->get_input('text_color'));
    $linkColor = trim($mybb->get_input('link_color'));
    if ($textColor !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $textColor)) $textColor = '';
    if ($linkColor !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $linkColor)) $linkColor = '';
    $db->update_query('ms_user_banners', array(
        'text_color' => $db->escape_string($textColor),
        'link_color' => $db->escape_string($linkColor),
    ), "bid={$bid}");

    $css = ms_pe_banner_css($banner['type'], $banner['value']);

    ms_profile_extras_json(array('success' => true, 'css' => $css, 'text_color' => $textColor, 'link_color' => $linkColor));
}
function ms_profile_extras_json($data, $code = 200)
{
    if ($code === 403) {
        header('HTTP/1.1 403 Forbidden');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
