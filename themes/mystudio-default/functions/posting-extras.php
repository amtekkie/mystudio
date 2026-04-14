<?php
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

if (defined('IN_ADMINCP')) return;

global $plugins, $mybb;

if (defined('IN_PORTAL')) {
    $plugins->add_hook('portal_start', 'ms_posting_extras_start');
}

if (defined('IN_MYBB')) {
    $plugins->add_hook('postbit', 'ms_pex_postbit');
    $plugins->add_hook('pre_output_page', 'ms_pex_pre_output_page');
    $plugins->add_hook('xmlhttp', 'ms_pex_xmlhttp_quicksearch');
}

function ms_posting_extras_start()
{
    global $mybb;

    ms_pex_ensure_like_table();

    $msAction = $mybb->get_input('ms_action');
    if (!empty($msAction)) {
        ms_pex_ajax();
        return;
    }

    ms_pex_prepare_feed();
}

function ms_pex_prepare_feed()
{
    global $mybb, $db, $lang, $templates, $header, $headerinclude, $footer, $theme, $cache;

    $bburl   = $mybb->settings['bburl'];
    $uid     = (int)$mybb->user['uid'];
    $perPage = 20;

    $announcementFidsWhere = '';
    if (!empty($mybb->settings['portal_announcementsfid']) && $mybb->settings['portal_announcementsfid'] != -1) {
        $announcementFids = explode(',', (string)$mybb->settings['portal_announcementsfid']);
        if (is_array($announcementFids)) {
            foreach ($announcementFids as &$fid) {
                $fid = (int)$fid;
            }
            unset($fid);

            $announcementFids = implode(',', $announcementFids);
            if (!empty($announcementFids)) {
                $announcementFidsWhere = ' AND t.fid IN (' . $announcementFids . ')';
            }
        }
    }
    $page = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $start = ($page - 1) * $perPage;
    $unviewable = get_unviewable_forums(true);
    $inactive   = get_inactive_forums();
    $fidWhere   = '';
    if ($unviewable) {
        $fidWhere .= " AND t.fid NOT IN ({$unviewable})";
    }
    if ($inactive) {
        $fidWhere .= " AND t.fid NOT IN ({$inactive})";
    }
    $countQuery = $db->query("
        SELECT COUNT(*) AS cnt
        FROM " . TABLE_PREFIX . "threads t
        WHERE t.visible='1' AND t.closed NOT LIKE 'moved|%'{$fidWhere}{$announcementFidsWhere}
    ");
    $total      = (int)$db->fetch_field($countQuery, 'cnt');
    $totalPages = max(1, ceil($total / $perPage));
    $forum_cache = $cache->read('forums');
    require_once MYBB_ROOT . 'inc/class_parser.php';
    $query = $db->query("
        SELECT t.tid, t.fid, t.uid AS thread_uid, t.subject AS thread_subject, t.dateline AS thread_dateline, t.replies, t.views,
               t.lastpost, t.firstpost, t.closed, t.visible AS thread_visible,
               p.*, u.*, u.username AS userusername, eu.username AS editusername
        FROM " . TABLE_PREFIX . "threads t
        LEFT JOIN " . TABLE_PREFIX . "posts p ON p.pid = t.firstpost
        LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = p.uid
        LEFT JOIN " . TABLE_PREFIX . "users eu ON eu.uid = p.edituid
        WHERE t.visible='1' AND t.closed NOT LIKE 'moved|%'{$fidWhere}{$announcementFidsWhere}
        ORDER BY t.dateline DESC
        LIMIT {$start}, {$perPage}
    ");

    $feedItems = '';
    $postcounter = 0;
    $altbg = 'trow1';

    // Suppress quote/multiquote buttons on portal (no quick editor)
    global $templates;
    $savedQuote = $templates->cache['postbit_quote'] ?? '';
    $savedMultiquote = $templates->cache['postbit_multiquote'] ?? '';
    $templates->cache['postbit_quote'] = '';
    $templates->cache['postbit_multiquote'] = '';

    while ($thread = $db->fetch_array($query)) {
        // Check forum-level view permissions
        $fperms = forum_permissions($thread['fid']);
        if (isset($fperms['canonlyviewownthreads']) && $fperms['canonlyviewownthreads'] == 1 && (int)$thread['thread_uid'] != $uid) {
            continue;
        }

        $feedItems .= ms_pex_render_thread_postbit($thread, $forum_cache);
    }

    // Restore quote/multiquote templates
    $templates->cache['postbit_quote'] = $savedQuote;
    $templates->cache['postbit_multiquote'] = $savedMultiquote;

    if (empty($feedItems)) {
        $feedItems = '<div class="pex-empty-state"><i class="bi bi-chat-square-text"></i><p>No posts yet.</p><span>Be the first to share something!</span></div>';
    }
    $pex_pagination = '';
    if ($totalPages > 1) {
        $pex_pagination = '<nav class="pex-pagination"><ul class="pagination pagination-sm justify-content-center">';
        for ($p = 1; $p <= $totalPages; $p++) {
            $activeClass = ($p == $page) ? ' active' : '';
            $pex_pagination .= '<li class="page-item' . $activeClass . '"><a class="page-link" href="' . $bburl . '/portal.php?page=' . $p . '">' . $p . '</a></li>';
        }
        $pex_pagination .= '</ul></nav>';
    }
    $GLOBALS['pex_feed_items'] = $feedItems;
    $GLOBALS['pex_pagination'] = $pex_pagination;
    $GLOBALS['pex_sidebar']    = ms_pex_render_page_sidebar('portal.php');
    $GLOBALS['pex_post_key']   = htmlspecialchars($mybb->post_code, ENT_QUOTES);

    // Expose globals so the template eval can access them
    global $pex_feed_items, $pex_pagination, $pex_sidebar, $pex_post_key;
    $pex_feed_items = $GLOBALS['pex_feed_items'];
    $pex_pagination = $GLOBALS['pex_pagination'];
    $pex_sidebar    = $GLOBALS['pex_sidebar'];
    $pex_post_key   = $GLOBALS['pex_post_key'];

    // Evaluate the portal template and output — must exit to prevent
    // portal.php's default announcement code from running
    eval("\$portal = \"".$templates->get("portal")."\";");
    output_page($portal);
    exit;
}

function ms_pex_render_thread_postbit($threadRow, $forumCache = null)
{
    global $forum, $thread, $tid, $fid, $forumpermissions, $postcounter, $altbg, $ismod;

    $thread = array(
        'tid' => (int)$threadRow['tid'],
        'fid' => (int)$threadRow['fid'],
        'uid' => isset($threadRow['thread_uid']) ? (int)$threadRow['thread_uid'] : (isset($threadRow['uid']) ? (int)$threadRow['uid'] : 0),
        'closed' => isset($threadRow['closed']) ? $threadRow['closed'] : '',
        'visible' => isset($threadRow['thread_visible']) ? (int)$threadRow['thread_visible'] : 1,
        'subject' => isset($threadRow['thread_subject']) ? $threadRow['thread_subject'] : (isset($threadRow['subject']) ? $threadRow['subject'] : ''),
    );

    $tid = $thread['tid'];
    $forum = get_forum($thread['fid']);
    $fid = $thread['fid'];
    $forumpermissions = forum_permissions($fid);
    $ismod = false;

    if (!isset($postcounter)) {
        $postcounter = 0;
    }
    if (!isset($altbg) || !$altbg) {
        $altbg = 'trow1';
    }

    $post = $threadRow;
    $post['subject'] = $thread['subject'];
    $post['postnum'] = 1;
    $post['threadnum'] = (int)$threadRow['replies'] + 1;
    $post['visible'] = 1;
    $post['userusername'] = isset($post['userusername']) ? $post['userusername'] : (isset($post['username']) ? $post['username'] : '');
    if (empty($post['pid']) && !empty($threadRow['firstpost'])) {
        $post['pid'] = (int)$threadRow['firstpost'];
    }
    if (empty($post['dateline']) && !empty($threadRow['thread_dateline'])) {
        $post['dateline'] = (int)$threadRow['thread_dateline'];
    }

    return build_postbit($post);
}

function ms_pex_pre_output_page(&$page)
{
    global $mybb;

    $scriptName = basename($_SERVER['SCRIPT_NAME']);

    // member.php pages (except profile) have their own layout
    if ($scriptName === 'member.php' && $mybb->get_input('action') !== 'profile') {
        return;
    }

    $hasShell = strpos($page, 'ms-page-shell') !== false;

    // Extract breadcrumb from page (rendered by nav.html via build_breadcrumb)
    $breadcrumb = '';
    if (preg_match('/<div class="navigation">.*?<\/div>/s', $page, $m)) {
        $breadcrumb = $m[0];
        $page = str_replace($breadcrumb, '', $page);
    }

    if (!$hasShell && strpos($page, '<main class="container">') !== false) {
        $sidebar = ms_pex_render_page_sidebar($scriptName);
        if ($sidebar !== '') {
            $mainHeader = '<div class="pex-main-header">' . $breadcrumb . '</div>';

            $page = preg_replace(
                '/<main class="container">/',
                '<main class="container"><div class="ms-page-shell"><aside class="ms-page-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="msSidebarOffcanvas"><div class="offcanvas-header d-lg-none"><h6 class="offcanvas-title">Menu</h6><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#msSidebarOffcanvas" aria-label="Close"></button></div><div class="offcanvas-body">' . $sidebar . '</div></aside><div class="ms-page-content">' . $mainHeader,
                $page,
                1
            );
            $page = preg_replace('/<\/main>/', '</div></div></main>', $page, 1);
        }
    } elseif ($hasShell && !empty($breadcrumb)) {
        // Page already has shell (e.g. portal) — inject breadcrumb into existing pex-main-header
        $page = str_replace(
            '<div class="pex-main-header">',
            '<div class="pex-main-header">' . $breadcrumb,
            $page
        );
    }

    // Inject new-thread modal at body level for logged-in users
    if (!empty($mybb->user['uid'])) {
        $modal = ms_pex_render_newthread_modal($mybb->settings['bburl']);
        if ($modal !== '') {
            $page = str_replace('</body>', $modal . '</body>', $page);
        }
    }
}

function ms_pex_render_page_sidebar($scriptName)
{
    global $mybb, $lang, $foruminfo, $thread, $memprofile;

    $bburl = $mybb->settings['bburl'];
    $uid = (int)$mybb->user['uid'];

    $html = '';

    $html .= '<nav class="ms-sidebar-nav">';

    if ($uid > 0) {
        $userAvatar = !empty($mybb->user['avatar']) ? htmlspecialchars_uni($mybb->user['avatar']) : 'images/default_avatar.png';
        $username = htmlspecialchars_uni($mybb->user['username']);
        $profileUrl = htmlspecialchars_uni($bburl . '/member.php?action=profile&uid=' . $uid);
        $usercpUrl = htmlspecialchars_uni($bburl . '/usercp.php');
        $pmUrl = htmlspecialchars_uni($bburl . '/private.php');
        $logoutUrl = htmlspecialchars_uni($bburl . '/member.php?action=logout&logoutkey=' . $mybb->user['logoutkey']);

        $userTitle = !empty($mybb->user['usertitle']) ? htmlspecialchars_uni($mybb->user['usertitle']) : '';
        $groupImage = '';
        if (!empty($mybb->user['groupimage'])) {
            $groupImage = '<img src="' . htmlspecialchars_uni($mybb->user['groupimage']) . '" alt="" class="ms-sidebar-groupimage" />';
        }

        $html .= '<div class="ms-sidebar-card ms-sidebar-profile">'
            . '<div class="ms-sidebar-profile-top">'
            . '<a href="' . $profileUrl . '">'
            . '<img src="' . $userAvatar . '" alt="' . $username . '" class="ms-sidebar-avatar" />'
            . '</a>'
            . '<div class="ms-sidebar-profile-copy">'
            . '<span class="ms-sidebar-name">' . format_name($mybb->user['username'], $mybb->user['usergroup'], $mybb->user['displaygroup']) . '</span>'
            . ($userTitle ? '<span class="ms-sidebar-handle">' . $userTitle . '</span>' : '')
            . $groupImage
            . '</div>'
            . '</div>'
            . '</div>';

        $html .= '<a href="#" class="ms-sidebar-link ms-sidebar-link-primary" role="button" data-bs-toggle="modal" data-bs-target="#ms_newthread_modal"><i class="bi bi-plus-lg"></i><span>Post Thread</span></a>';

        $portalActive = ($scriptName === 'portal.php');
        $forumsActive = in_array($scriptName, array('index.php', 'forumdisplay.php', 'showthread.php'));

        $searchUrl = htmlspecialchars_uni($bburl . '/search.php');
        $searchActive = ($scriptName === 'search.php');
        $searchOpen = $searchActive ? ' open' : '';
        $searchAction = $mybb->get_input('action');

        $html .= '<a href="' . htmlspecialchars_uni($bburl . '/portal.php') . '" class="ms-sidebar-link' . ($portalActive ? ' active' : '') . '"><i class="bi bi-house-door"></i><span>Home</span></a>'
            . '<a href="' . htmlspecialchars_uni($bburl . '/index.php') . '" class="ms-sidebar-link' . ($forumsActive ? ' active' : '') . '"><i class="bi bi-grid-3x3-gap"></i><span>Forums</span></a>';

        $html .= '<details class="ms-sidebar-details"' . $searchOpen . '>'
            . '<summary class="ms-sidebar-link' . ($searchActive ? ' active' : '') . '"><a href="' . $searchUrl . '"><i class="bi bi-search"></i><span>Search</span></a><i class="bi bi-chevron-down ms-sidebar-chevron"></i></summary>'
            . '<div class="ms-sidebar-sub">'
            . '<a class="ms-sidebar-sublink' . ($searchActive && $searchAction === 'getnew' ? ' active' : '') . '" href="' . $searchUrl . '?action=getnew">New Posts</a>'
            . '<a class="ms-sidebar-sublink' . ($searchActive && $searchAction === 'getdaily' ? ' active' : '') . '" href="' . $searchUrl . '?action=getdaily">Today\'s Posts</a>'
            . '</div></details>';
        $ucpActive = ($scriptName === 'usercp.php');
        $ucpOpen = $ucpActive ? ' open' : '';
        $ucpAction = $mybb->get_input('action');
        $html .= '<details class="ms-sidebar-details"' . $ucpOpen . '>'
            . '<summary class="ms-sidebar-link' . ($ucpActive ? ' active' : '') . '"><a href="' . $usercpUrl . '"><i class="bi bi-gear"></i><span>User CP</span></a><i class="bi bi-chevron-down ms-sidebar-chevron"></i></summary>'
            . '<div class="ms-sidebar-sub">'
            . '<a class="ms-sidebar-sublink' . ($ucpActive && $ucpAction === '' ? ' active' : '') . '" href="' . $usercpUrl . '">Home</a>'
            . '<a class="ms-sidebar-sublink' . ($ucpActive && $ucpAction === 'profile' ? ' active' : '') . '" href="' . $usercpUrl . '?action=profile">Edit Profile</a>'
            . '<a class="ms-sidebar-sublink' . ($ucpActive && $ucpAction === 'changename' ? ' active' : '') . '" href="' . $usercpUrl . '?action=changename">Change Username</a>'
            . '<a class="ms-sidebar-sublink' . ($ucpActive && $ucpAction === 'password' ? ' active' : '') . '" href="' . $usercpUrl . '?action=password">Password</a>'
            . '<a class="ms-sidebar-sublink' . ($ucpActive && $ucpAction === 'email' ? ' active' : '') . '" href="' . $usercpUrl . '?action=email">Email</a>'
            . '<a class="ms-sidebar-sublink' . ($ucpActive && $ucpAction === 'avatar' ? ' active' : '') . '" href="' . $usercpUrl . '?action=avatar">Avatar</a>'
            . '<a class="ms-sidebar-sublink' . ($ucpActive && $ucpAction === 'editsig' ? ' active' : '') . '" href="' . $usercpUrl . '?action=editsig">Signature</a>'
            . '<a class="ms-sidebar-sublink' . ($ucpActive && $ucpAction === 'options' ? ' active' : '') . '" href="' . $usercpUrl . '?action=options">Edit Options</a>'
            . '</div></details>';
        $pmActive = ($scriptName === 'private.php');
        $pmOpen = $pmActive ? ' open' : '';
        $pmAction = $mybb->get_input('action');
        $pmFid = $mybb->get_input('fid', MyBB::INPUT_INT);
        $html .= '<details class="ms-sidebar-details"' . $pmOpen . '>'
            . '<summary class="ms-sidebar-link' . ($pmActive ? ' active' : '') . '"><a href="' . $pmUrl . '"><i class="bi bi-chat-dots"></i><span>Private Messages</span></a><i class="bi bi-chevron-down ms-sidebar-chevron"></i></summary>'
            . '<div class="ms-sidebar-sub">'
            . '<a class="ms-sidebar-sublink' . ($pmActive && $pmAction === 'send' ? ' active' : '') . '" href="' . $pmUrl . '?action=send">Compose</a>'
            . '<a class="ms-sidebar-sublink' . ($pmActive && $pmAction !== 'send' && $pmAction !== 'tracking' && $pmAction !== 'folders' && ($pmFid === 0 || $pmFid === 1) ? ' active' : '') . '" href="' . $pmUrl . '">Inbox</a>'
            . '<a class="ms-sidebar-sublink' . ($pmActive && $pmAction === '' && $pmFid === 4 ? ' active' : '') . '" href="' . $pmUrl . '?fid=4">Unread</a>'
            . '<a class="ms-sidebar-sublink' . ($pmActive && $pmAction === '' && $pmFid === 2 ? ' active' : '') . '" href="' . $pmUrl . '?fid=2">Sent</a>'
            . '<a class="ms-sidebar-sublink' . ($pmActive && $pmAction === '' && $pmFid === 3 ? ' active' : '') . '" href="' . $pmUrl . '?fid=3">Drafts</a>'
            . '<a class="ms-sidebar-sublink' . ($pmActive && $pmAction === '' && $pmFid === 5 ? ' active' : '') . '" href="' . $pmUrl . '?fid=5">Trash</a>'
            . '<a class="ms-sidebar-sublink' . ($pmActive && $pmAction === 'tracking' ? ' active' : '') . '" href="' . $pmUrl . '?action=tracking">Tracking</a>'
            . '<a class="ms-sidebar-sublink' . ($pmActive && $pmAction === 'folders' ? ' active' : '') . '" href="' . $pmUrl . '?action=folders">Edit Folders</a>'
            . '</div></details>';
        if ($mybb->usergroup['canmodcp'] == 1) {
            $modcpUrl = htmlspecialchars_uni($bburl . '/modcp.php');
            $mcpActive = ($scriptName === 'modcp.php');
            $mcpOpen = $mcpActive ? ' open' : '';
            $mcpAction = $mybb->get_input('action');
            $html .= '<details class="ms-sidebar-details"' . $mcpOpen . '>'
                . '<summary class="ms-sidebar-link' . ($mcpActive ? ' active' : '') . '"><a href="' . $modcpUrl . '"><i class="bi bi-shield-check"></i><span>Mod CP</span></a><i class="bi bi-chevron-down ms-sidebar-chevron"></i></summary>'
                . '<div class="ms-sidebar-sub">'
                . '<a class="ms-sidebar-sublink' . ($mcpActive && $mcpAction === '' ? ' active' : '') . '" href="' . $modcpUrl . '">Home</a>'
                . '<a class="ms-sidebar-sublink' . ($mcpActive && $mcpAction === 'modqueue' ? ' active' : '') . '" href="' . $modcpUrl . '?action=modqueue">Mod Queue</a>'
                . '<a class="ms-sidebar-sublink' . ($mcpActive && $mcpAction === 'reports' ? ' active' : '') . '" href="' . $modcpUrl . '?action=reports">Report Center</a>'
                . '<a class="ms-sidebar-sublink' . ($mcpActive && $mcpAction === 'modlogs' ? ' active' : '') . '" href="' . $modcpUrl . '?action=modlogs">Mod Logs</a>'
                . '<a class="ms-sidebar-sublink' . ($mcpActive && $mcpAction === 'announcements' ? ' active' : '') . '" href="' . $modcpUrl . '?action=announcements">Announcements</a>'
                . '<a class="ms-sidebar-sublink' . ($mcpActive && $mcpAction === 'finduser' ? ' active' : '') . '" href="' . $modcpUrl . '?action=finduser">Edit Profile</a>'
                . '<a class="ms-sidebar-sublink' . ($mcpActive && $mcpAction === 'banning' ? ' active' : '') . '" href="' . $modcpUrl . '?action=banning">Banning</a>'
                . '<a class="ms-sidebar-sublink' . ($mcpActive && $mcpAction === 'warninglogs' ? ' active' : '') . '" href="' . $modcpUrl . '?action=warninglogs">Warning Logs</a>'
                . '<a class="ms-sidebar-sublink' . ($mcpActive && $mcpAction === 'ipsearch' ? ' active' : '') . '" href="' . $modcpUrl . '?action=ipsearch">IP Search</a>'
                . '</div></details>';
        }

        $html .= '<a class="ms-sidebar-link" href="' . $logoutUrl . '"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>';
    } else {
        $html .= '<div class="ms-sidebar-card ms-sidebar-profile">'
            . '<div class="ms-sidebar-profile-top">'
            . '<img src="images/default_avatar.png" alt="' . htmlspecialchars_uni($lang->guest) . '" class="ms-sidebar-avatar" />'
            . '<div class="ms-sidebar-profile-copy">'
            . '<span class="ms-sidebar-name">Guest</span>'
            . '<span class="ms-sidebar-handle">Welcome Guest!</span>'
            . '</div>'
            . '</div>'
            . '</div>';

        $html .= '<a class="ms-sidebar-link ms-sidebar-link-primary" href="' . htmlspecialchars_uni($bburl . '/member.php?action=login') . '"><i class="bi bi-box-arrow-in-right"></i><span>Login</span></a>'
            . '<a class="ms-sidebar-link ms-sidebar-link-accent" href="' . htmlspecialchars_uni($bburl . '/member.php?action=register') . '"><i class="bi bi-person-plus"></i><span>Register</span></a>';

        $guestPortalActive = ($scriptName === 'portal.php');
        $guestForumsActive = in_array($scriptName, array('index.php', 'forumdisplay.php', 'showthread.php'));
        $html .= '<a href="' . htmlspecialchars_uni($bburl . '/portal.php') . '" class="ms-sidebar-link' . ($guestPortalActive ? ' active' : '') . '"><i class="bi bi-house-door"></i><span>Home</span></a>'
            . '<a href="' . htmlspecialchars_uni($bburl . '/index.php') . '" class="ms-sidebar-link' . ($guestForumsActive ? ' active' : '') . '"><i class="bi bi-grid-3x3-gap"></i><span>Forums</span></a>';

        $guestSearchUrl = htmlspecialchars_uni($bburl . '/search.php');
        $guestSearchActive = ($scriptName === 'search.php');
        $guestSearchOpen = $guestSearchActive ? ' open' : '';
        $guestSearchAction = $mybb->get_input('action');
        $html .= '<details class="ms-sidebar-details"' . $guestSearchOpen . '>'
            . '<summary class="ms-sidebar-link' . ($guestSearchActive ? ' active' : '') . '"><a href="' . $guestSearchUrl . '"><i class="bi bi-search"></i><span>Search</span></a><i class="bi bi-chevron-down ms-sidebar-chevron"></i></summary>'
            . '<div class="ms-sidebar-sub">'
            . '<a class="ms-sidebar-sublink' . ($guestSearchActive && $guestSearchAction === 'getnew' ? ' active' : '') . '" href="' . $guestSearchUrl . '?action=getnew">New Posts</a>'
            . '<a class="ms-sidebar-sublink' . ($guestSearchActive && $guestSearchAction === 'getdaily' ? ' active' : '') . '" href="' . $guestSearchUrl . '?action=getdaily">Today\'s Posts</a>'
            . '</div></details>';
    }

    $html .= '</nav>';

    return $html;
}

function ms_pex_render_newthread_modal($bburl)
{
    global $cache, $templates;

    $forums = $cache->read('forums');
    if (empty($forums) || !is_array($forums)) {
        return '';
    }

    // Build parent→children map
    $children = array();
    foreach ($forums as $f) {
        $pid = (int)$f['pid'];
        if (!isset($children[$pid])) {
            $children[$pid] = array();
        }
        $children[$pid][] = $f;
    }
    // Sort each group by disporder
    foreach ($children as &$group) {
        usort($group, function ($a, $b) { return (int)$a['disporder'] - (int)$b['disporder']; });
    }
    unset($group);

    $ms_pex_forum_list = ms_pex_render_forum_tree($children, 0, $bburl, 0);

    eval("\$m = \"" . $templates->get("pex_newthread_modal") . "\";");
    return $m;
}

function ms_pex_render_forum_tree($children, $pid, $bburl, $depth)
{
    if (empty($children[$pid])) {
        return '';
    }

    $html = '';
    foreach ($children[$pid] as $f) {
        $fid = (int)$f['fid'];
        $type = isset($f['type']) ? $f['type'] : 'f';
        $name = htmlspecialchars_uni($f['name']);
        $active = (int)(isset($f['active']) ? $f['active'] : 1);
        if (!$active) continue;

        // Check permissions
        $perms = forum_permissions($fid);
        if (isset($perms['canview']) && $perms['canview'] == 0) continue;

        $indent = $depth * 20;
        $style = $indent > 0 ? ' style="padding-left:' . (16 + $indent) . 'px"' : '';

        if ($type === 'c') {
            // Category header (not clickable)
            $html .= '<div class="list-group-item fw-semibold text-uppercase small text-muted border-0 py-1 px-3"' . $style . '>' . $name . '</div>';
            $html .= ms_pex_render_forum_tree($children, $fid, $bburl, $depth);
        } else {
            // Forum — check if user can post threads
            $canPost = !isset($perms['canpostthreads']) || $perms['canpostthreads'] != 0;
            $newthreadUrl = htmlspecialchars_uni($bburl . '/newthread.php?fid=' . $fid);

            if ($canPost) {
                $html .= '<a href="' . $newthreadUrl . '" class="list-group-item list-group-item-action py-2 px-3 border-0"' . $style . '>'
                    . '<i class="bi bi-chat-left-text me-2 text-muted"></i>' . $name
                    . '</a>';
            } else {
                $html .= '<div class="list-group-item py-2 px-3 border-0 text-muted"' . $style . '>'
                    . '<i class="bi bi-lock me-2"></i>' . $name
                    . '</div>';
            }
            // Render subforums
            $html .= ms_pex_render_forum_tree($children, $fid, $bburl, $depth + 1);
        }
    }

    return $html;
}
function ms_pex_handle_image_upload($fileKey, $prefix, $uid, &$message)
{
    global $mybb;

    if (empty($_FILES[$fileKey]['name'])) {
        return null;
    }

    $uploadDir = MYBB_ROOT . 'uploads/posting_extras/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
        $htaccess = $uploadDir . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "<FilesMatch \"\\.php$\">\nOrder Allow,Deny\nDeny from all\n</FilesMatch>\n");
        }
    }

    $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return array('error' => 'Invalid image type.');
    }

    $allowedMimes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $detectedMime = finfo_file($finfo, $_FILES[$fileKey]['tmp_name']);
        finfo_close($finfo);
        if ($detectedMime === false || !in_array($detectedMime, $allowedMimes)) {
            return array('error' => 'File content does not match an allowed image type.');
        }
    }

    if ($_FILES[$fileKey]['size'] > 5 * 1024 * 1024) {
        return array('error' => 'Image too large. Max 5MB.');
    }

    $filename = $prefix . '_' . $uid . '_' . TIME_NOW . '.' . $ext;
    $dest = $uploadDir . $filename;

    $realUploadDir = realpath($uploadDir);
    $realBase = realpath(MYBB_ROOT . 'uploads');
    if ($realUploadDir === false || $realBase === false || strpos($realUploadDir, $realBase) !== 0) {
        return array('error' => 'Invalid upload directory.');
    }

    if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dest)) {
        return array('error' => 'Image upload failed.');
    }

    $imgUrl = $mybb->settings['bburl'] . '/uploads/posting_extras/' . $filename;
    $message .= "\n[img]" . $imgUrl . "[/img]";
    return null;
}
function ms_pex_postbit($post)
{
    global $mybb;

    if (empty($post['pid']) || empty($post['tid'])) {
        return $post;
    }

    list($likeCount, $likedByUser) = ms_pex_get_like_state('post', (int)$post['pid'], (int)$mybb->user['uid']);
    $post['button_like'] = ms_pex_render_like_controls('post', (int)$post['pid'], $likeCount, $likedByUser, $mybb->settings['bburl'], $mybb->post_code);

    return $post;
}

function ms_pex_parse_message($message, $smilieoff = 0)
{
    require_once MYBB_ROOT . 'inc/class_parser.php';
    $parser = new postParser;
    return $parser->parse_message($message, array(
        'allow_html'      => 0,
        'allow_mycode'    => 1,
        'allow_smilies'   => ($smilieoff ? 0 : 1),
        'allow_imgcode'   => 1,
        'allow_videocode' => 1,
        'filter_badwords' => 1,
        'nl2br'           => 1,
    ));
}

function ms_pex_render_reply($post)
{
    global $mybb, $templates;
    $bburl   = $mybb->settings['bburl'];
    $uid     = (int)$mybb->user['uid'];
    $ms_pex_reply_avatar = !empty($post['avatar']) ? htmlspecialchars_uni($post['avatar']) : 'images/default_avatar.png';
    $ms_pex_reply_username = htmlspecialchars_uni($post['username'] ?: 'Guest');
    $ms_pex_reply_profile_url = htmlspecialchars_uni($bburl . '/member.php?action=profile&uid=' . (int)$post['uid']);
    $ms_pex_reply_formatted = format_name(htmlspecialchars_uni($post['username'] ?: 'Guest'), $post['usergroup'], $post['displaygroup']);
    $ms_pex_reply_message = ms_pex_parse_message($post['message'], isset($post['smilieoff']) ? $post['smilieoff'] : 0);
    $ms_pex_reply_time = my_date('relative', $post['dateline']);
    $ms_pex_pid = (int)$post['pid'];

    $ms_pex_reply_delete_btn = '';
    if ($uid > 0 && ($uid == (int)$post['uid'] || $mybb->usergroup['canmodcp'])) {
        $ms_pex_reply_delete_btn = '<a href="javascript:void(0)" class="pex-delete-reply text-danger" data-pid="' . $ms_pex_pid . '" title="Delete reply"><i class="bi bi-trash"></i></a>';
    }

    eval("\$html = \"" . $templates->get("pex_reply") . "\";");
    return $html;
}
function ms_pex_ajax()
{
    global $mybb;

    $action = $mybb->get_input('ms_action');
    if (empty($action)) return;

    // Guests may view liker lists, but all write actions require login.
    if ($mybb->user['uid'] <= 0 && $action !== 'load_likes') {
        ms_pex_json(array('error' => 'Not logged in.'), 403);
        return;
    }

    // Verify post key for write actions
    $readOnly = array('load_replies', 'load_likes');
    if (!in_array($action, $readOnly)) {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            ms_pex_json(array('error' => 'Invalid security token.'), 403);
            return;
        }
    }

    switch ($action) {
        case 'create_thread':
            ms_pex_create_thread();
            break;
        case 'create_reply':
            ms_pex_create_reply();
            break;
        case 'load_replies':
            ms_pex_load_replies();
            break;
        case 'delete_thread':
            ms_pex_delete_thread();
            break;
        case 'delete_reply':
            ms_pex_delete_reply();
            break;
        case 'toggle_like':
            ms_pex_toggle_like();
            break;
        case 'load_likes':
            ms_pex_load_likes();
            break;
    }
}
function ms_pex_create_thread()
{
    global $mybb, $db, $cache;

    $uid     = (int)$mybb->user['uid'];
    $fid     = $mybb->get_input('fid', MyBB::INPUT_INT);
    $message = trim($mybb->get_input('message'));

    if (empty($message)) {
        ms_pex_json(array('error' => 'Message cannot be empty.'));
        return;
    }

    if ($fid <= 0) {
        ms_pex_json(array('error' => 'Please select a forum.'));
        return;
    }

    // Verify user can post in this forum
    $fperms = forum_permissions($fid);
    if (empty($fperms['canpostthreads'])) {
        ms_pex_json(array('error' => 'You do not have permission to post in this forum.'));
        return;
    }

    // Handle optional image upload
    $uploadErr = ms_pex_handle_image_upload('post_image', 'post', $uid, $message);
    if ($uploadErr !== null) {
        ms_pex_json($uploadErr);
        return;
    }

    // Auto-generate subject from message
    $subject = trim(strip_tags(preg_replace('/\[.*?\]/', '', $message)));
    if (my_strlen($subject) > 80) {
        $subject = my_substr($subject, 0, 80) . '...';
    }
    if (empty($subject)) {
        $subject = 'Status Update';
    }

    // Use PostDataHandler to create thread
    require_once MYBB_ROOT . 'inc/datahandlers/post.php';
    $posthandler = new PostDataHandler('insert');
    $posthandler->action = 'thread';

    $threadData = array(
        'fid'      => $fid,
        'subject'  => $subject,
        'uid'      => $uid,
        'username' => $mybb->user['username'],
        'message'  => $message,
        'options'  => array(
            'signature'      => 1,
            'disablesmilies' => 0,
        ),
    );

    $posthandler->set_data($threadData);

    if (!$posthandler->validate_thread()) {
        $errors = $posthandler->get_friendly_errors();
        ms_pex_json(array('error' => implode(', ', $errors)));
        return;
    }

    $threadInfo = $posthandler->insert_thread();
    $tid = (int)$threadInfo['tid'];

    // Query the newly created thread for rendering
    $forum_cache = $cache->read('forums');
    $query = $db->query("
        SELECT t.tid, t.fid, t.uid AS thread_uid, t.subject AS thread_subject, t.dateline AS thread_dateline, t.replies, t.views,
               t.lastpost, t.firstpost, t.closed, t.visible AS thread_visible,
               p.*, u.*, u.username AS userusername, eu.username AS editusername
        FROM " . TABLE_PREFIX . "threads t
        LEFT JOIN " . TABLE_PREFIX . "posts p ON p.pid = t.firstpost
        LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = p.uid
        LEFT JOIN " . TABLE_PREFIX . "users eu ON eu.uid = p.edituid
        WHERE t.tid={$tid}
        LIMIT 1
    ");
    $thread = $db->fetch_array($query);

    if (!$thread) {
        ms_pex_json(array('success' => true, 'html' => '', 'tid' => $tid));
        return;
    }

    $html = ms_pex_render_thread_postbit($thread, $forum_cache);

    ms_pex_json(array('success' => true, 'html' => $html, 'tid' => $tid));
}
function ms_pex_create_reply()
{
    global $mybb, $db;

    $uid     = (int)$mybb->user['uid'];
    $tid     = $mybb->get_input('tid', MyBB::INPUT_INT);
    $message = trim($mybb->get_input('message'));

    if (empty($message)) {
        ms_pex_json(array('error' => 'Reply cannot be empty.'));
        return;
    }

    if ($tid <= 0) {
        ms_pex_json(array('error' => 'Invalid thread.'));
        return;
    }

    // Get thread info
    $query = $db->simple_select('threads', 'tid, fid, subject, closed', "tid={$tid} AND visible='1'", array('limit' => 1));
    $thread = $db->fetch_array($query);
    if (!$thread) {
        ms_pex_json(array('error' => 'Thread not found.'));
        return;
    }

    // Check permissions
    $fperms = forum_permissions($thread['fid']);
    if (empty($fperms['canpostreplys'])) {
        ms_pex_json(array('error' => 'You do not have permission to reply.'));
        return;
    }

    // Handle optional image upload
    $uploadErr = ms_pex_handle_image_upload('reply_image', 'reply', $uid, $message);
    if ($uploadErr !== null) {
        ms_pex_json($uploadErr);
        return;
    }

    // Use PostDataHandler to create reply
    require_once MYBB_ROOT . 'inc/datahandlers/post.php';
    $posthandler = new PostDataHandler('insert');
    $posthandler->action = 'post';

    $postData = array(
        'tid'      => $tid,
        'replyto'  => 0,
        'fid'      => (int)$thread['fid'],
        'subject'  => 'RE: ' . $thread['subject'],
        'uid'      => $uid,
        'username' => $mybb->user['username'],
        'message'  => $message,
        'options'  => array(
            'signature'      => 1,
            'disablesmilies' => 0,
        ),
    );

    $posthandler->set_data($postData);

    if (!$posthandler->validate_post()) {
        $errors = $posthandler->get_friendly_errors();
        ms_pex_json(array('error' => implode(', ', $errors)));
        return;
    }

    $postInfo = $posthandler->insert_post();
    $pid = (int)$postInfo['pid'];

    // Build rendered reply
    $reply = array(
        'pid'          => $pid,
        'uid'          => $uid,
        'message'      => $message,
        'dateline'     => TIME_NOW,
        'username'     => $mybb->user['username'],
        'usergroup'    => $mybb->user['usergroup'],
        'displaygroup' => $mybb->user['displaygroup'],
        'avatar'       => $mybb->user['avatar'],
        'smilieoff'    => 0,
    );

    $html = ms_pex_render_reply($reply);
    ms_pex_json(array('success' => true, 'html' => $html, 'pid' => $pid));
}
function ms_pex_load_replies()
{
    global $mybb, $db, $templates;

    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
    if ($tid <= 0) {
        ms_pex_json(array('error' => 'Invalid thread.'));
        return;
    }

    // Get thread & verify visibility
    $tQuery = $db->simple_select('threads', 'tid, fid, firstpost', "tid={$tid} AND visible='1'", array('limit' => 1));
    $thread = $db->fetch_array($tQuery);
    if (!$thread) {
        ms_pex_json(array('error' => 'Thread not found.'));
        return;
    }

    $firstPost = (int)$thread['firstpost'];

    // Get replies (excluding first post)
    $query = $db->query("
        SELECT p.pid, p.uid, p.message, p.dateline, p.smilieoff,
               u.username, u.usergroup, u.displaygroup, u.avatar
        FROM " . TABLE_PREFIX . "posts p
        LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = p.uid
        WHERE p.tid={$tid} AND p.visible='1' AND p.pid != {$firstPost}
        ORDER BY p.dateline ASC
        LIMIT 50
    ");

    $html = '';
    $count = 0;
    while ($post = $db->fetch_array($query)) {
        $html .= ms_pex_render_reply($post);
        $count++;
    }

    // Reply compose form (logged in only)
    $composeHtml = '';
    if ($mybb->user['uid'] > 0) {
        $ms_pex_compose_avatar = htmlspecialchars_uni(!empty($mybb->user['avatar']) ? $mybb->user['avatar'] : 'images/default_avatar.png');
        $ms_pex_compose_tid = $tid;
        eval("\$composeHtml = \"" . $templates->get("pex_reply_compose") . "\";");
    }

    ms_pex_json(array('success' => true, 'html' => $html . $composeHtml, 'count' => $count));
}
function ms_pex_delete_thread()
{
    global $mybb, $db;

    $uid = (int)$mybb->user['uid'];
    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);

    $query = $db->simple_select('threads', 'uid, fid', "tid={$tid}", array('limit' => 1));
    $thread = $db->fetch_array($query);
    if (!$thread) {
        ms_pex_json(array('error' => 'Thread not found.'));
        return;
    }

    // Verify ownership or mod
    if ((int)$thread['uid'] !== $uid && !$mybb->usergroup['canmodcp']) {
        ms_pex_json(array('error' => 'Permission denied.'));
        return;
    }

    require_once MYBB_ROOT . 'inc/class_moderation.php';
    $moderation = new Moderation;

    if ($mybb->settings['soft_delete'] == 1 || is_moderator((int)$thread['fid'], 'cansoftdeletethreads')) {
        $moderation->soft_delete_threads(array($tid));
    } else {
        $moderation->delete_thread($tid);
    }

    $db->delete_query('ms_pex_likes', "target_type='post' AND target_id IN (SELECT pid FROM " . TABLE_PREFIX . "posts WHERE tid={$tid})");

    ms_pex_json(array('success' => true));
}
function ms_pex_delete_reply()
{
    global $mybb, $db;

    $uid = (int)$mybb->user['uid'];
    $pid = $mybb->get_input('pid', MyBB::INPUT_INT);

    $query = $db->simple_select('posts', 'uid, tid, fid', "pid={$pid}", array('limit' => 1));
    $post = $db->fetch_array($query);
    if (!$post) {
        ms_pex_json(array('error' => 'Reply not found.'));
        return;
    }

    // Verify ownership or mod
    if ((int)$post['uid'] !== $uid && !$mybb->usergroup['canmodcp']) {
        ms_pex_json(array('error' => 'Permission denied.'));
        return;
    }

    // Soft delete
    require_once MYBB_ROOT . 'inc/class_moderation.php';
    $moderation = new Moderation;
    $moderation->soft_delete_posts(array($pid));

    ms_pex_json(array('success' => true));
}
function ms_pex_ensure_like_table()
{
    global $db;
    static $done = false;
    if ($done) {
        return;
    }

    if (!$db->table_exists('ms_pex_likes')) {
        $db->write_query("CREATE TABLE " . TABLE_PREFIX . "ms_pex_likes (
            lid int unsigned NOT NULL auto_increment,
            target_type varchar(20) NOT NULL default '',
            target_id int unsigned NOT NULL default 0,
            uid int unsigned NOT NULL default 0,
            dateline int unsigned NOT NULL default 0,
            PRIMARY KEY (lid),
            UNIQUE KEY target_user (target_type, target_id, uid),
            KEY target_lookup (target_type, target_id),
            KEY uid (uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $done = true;
}

function ms_pex_get_like_state($targetType, $targetId, $uid = 0)
{
    global $db;

    ms_pex_ensure_like_table();

    $targetType = $db->escape_string($targetType);
    $targetId = (int)$targetId;
    $countQuery = $db->simple_select('ms_pex_likes', 'COUNT(lid) AS like_count', "target_type='{$targetType}' AND target_id={$targetId}");
    $likeCount = (int)$db->fetch_field($countQuery, 'like_count');

    $liked = false;
    if ($uid > 0) {
        $likedQuery = $db->simple_select('ms_pex_likes', 'lid', "target_type='{$targetType}' AND target_id={$targetId} AND uid=" . (int)$uid, array('limit' => 1));
        $liked = (bool)$db->fetch_field($likedQuery, 'lid');
    }

    return array($likeCount, $liked);
}

function ms_pex_render_like_controls($targetType, $targetId, $likeCount, $likedByUser = false, $bburl = '', $postKey = '')
{
    $targetType = htmlspecialchars_uni($targetType);
    $targetId = (int)$targetId;
    $likeCount = (int)$likeCount;
    $likedClass = $likedByUser ? ' is-liked' : '';
    $heartIcon = $likedByUser ? 'bi-heart-fill' : 'bi-heart';
    $countClass = $likeCount > 0 ? '' : ' is-hidden';
    $bburl = htmlspecialchars_uni((string)$bburl);
    $postKey = htmlspecialchars_uni((string)$postKey);

    return '<div class="pex-like-wrap" data-target-type="' . $targetType . '" data-target-id="' . $targetId . '" data-bburl="' . $bburl . '" data-post-key="' . $postKey . '">'
        . '<a href="javascript:void(0)" class="pex-action-btn pex-like-toggle' . $likedClass . '" data-target-type="' . $targetType . '" data-target-id="' . $targetId . '" title="Like"><i class="bi ' . $heartIcon . '"></i></a>'
        . '<a href="javascript:void(0)" class="pex-like-count' . $countClass . '" data-target-type="' . $targetType . '" data-target-id="' . $targetId . '" title="View likes"><span class="pex-like-count-num">' . $likeCount . '</span></a>'
        . '<div class="pex-likes-section" data-target-type="' . $targetType . '" data-target-id="' . $targetId . '" style="display:none"><div class="pex-likes-list"></div></div>'
        . '</div>';
}

function ms_pex_toggle_like()
{
    global $mybb, $db;

    ms_pex_ensure_like_table();

    $uid = (int)$mybb->user['uid'];
    if ($uid <= 0) {
        ms_pex_json(array('error' => 'Not logged in.'), 403);
        return;
    }

    $targetType = trim($mybb->get_input('target_type'));
    $targetId = $mybb->get_input('target_id', MyBB::INPUT_INT);
    if ($targetType !== 'post' || $targetId <= 0) {
        ms_pex_json(array('error' => 'Invalid target.'));
        return;
    }

    $targetType = $db->escape_string($targetType);
    $existingQuery = $db->simple_select('ms_pex_likes', 'lid', "target_type='{$targetType}' AND target_id={$targetId} AND uid={$uid}", array('limit' => 1));
    $existing = (int)$db->fetch_field($existingQuery, 'lid');

    if ($existing > 0) {
        $db->delete_query('ms_pex_likes', "lid={$existing}");
        $liked = false;
    } else {
        $db->insert_query('ms_pex_likes', array(
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'uid'         => $uid,
            'dateline'    => TIME_NOW,
        ));
        $liked = true;
    }

    list($likeCount) = ms_pex_get_like_state('post', $targetId, $uid);
    ms_pex_json(array(
        'success' => true,
        'liked' => $liked,
        'count' => $likeCount,
    ));
}

function ms_pex_load_likes()
{
    global $mybb, $db;

    ms_pex_ensure_like_table();

    $targetType = trim($mybb->get_input('target_type'));
    $targetId = $mybb->get_input('target_id', MyBB::INPUT_INT);
    if ($targetType !== 'post' || $targetId <= 0) {
        ms_pex_json(array('error' => 'Invalid target.'));
        return;
    }

    $bburl = $mybb->settings['bburl'];
    $query = $db->query("\n        SELECT l.uid, l.dateline, u.username, u.usergroup, u.displaygroup, u.avatar\n        FROM " . TABLE_PREFIX . "ms_pex_likes l\n        LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = l.uid\n        WHERE l.target_type='" . $db->escape_string($targetType) . "' AND l.target_id={$targetId}\n        ORDER BY l.dateline DESC\n        LIMIT 50\n    ");

    $html = '';
    while ($row = $db->fetch_array($query)) {
        $avatar = !empty($row['avatar']) ? htmlspecialchars_uni($row['avatar']) : 'images/default_avatar.png';
        $profileUrl = htmlspecialchars_uni($bburl . '/member.php?action=profile&uid=' . (int)$row['uid']);
        $username = htmlspecialchars_uni($row['username'] ?: $mybb->lang->guest);
        $formatted = format_name($username, $row['usergroup'], $row['displaygroup']);
        $html .= '<div class="pex-like-user"><a href="' . $profileUrl . '"><img src="' . $avatar . '" alt="' . $username . '" class="pex-like-user-avatar" /></a><a href="' . $profileUrl . '" class="pex-like-user-name">' . $formatted . '</a></div>';
    }

    if ($html === '') {
        $html = '<div class="text-muted small p-2">No likes yet.</div>';
    }

    ms_pex_json(array('success' => true, 'html' => $html));
}
function ms_pex_json($data, $code = 200)
{
    if ($code === 403) {
        header('HTTP/1.1 403 Forbidden');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
function ms_pex_xmlhttp_quicksearch()
{
    global $mybb, $db, $charset;

    if ($mybb->get_input('action') !== 'ms_quicksearch') {
        return;
    }

    header('Content-Type: application/json; charset=' . $charset);

    $q = trim($mybb->get_input('q'));
    if (mb_strlen($q) < 3) {
        echo json_encode(array('threads' => array(), 'posts' => array(), 'users' => array(), 'announcements' => array()));
        exit;
    }

    $bburl = $mybb->settings['bburl'];
    $escaped = $db->escape_string_like($q);
    $safe_q  = $db->escape_string($q);

    // Build list of forums user can search
    require_once MYBB_ROOT . 'inc/functions_search.php';
    $visible_fids = array();
    $forums = $db->simple_select('forums', 'fid', "active=1 AND type='f'");
    while ($f = $db->fetch_array($forums)) {
        $perms = forum_permissions((int)$f['fid']);
        if (!empty($perms['canview']) && !empty($perms['cansearch'])) {
            $visible_fids[] = (int)$f['fid'];
        }
    }

    $result = array();
    if (!empty($visible_fids)) {
        $fid_list = implode(',', $visible_fids);
        $query = $db->query("
            SELECT t.tid, t.subject, t.replies, t.views, t.dateline, u.username
            FROM " . TABLE_PREFIX . "threads t
            LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = t.uid
            WHERE t.visible = 1
              AND t.fid IN ({$fid_list})
              AND t.subject LIKE '%{$escaped}%'
            ORDER BY t.dateline DESC
            LIMIT 5
        ");
        $threads = array();
        while ($row = $db->fetch_array($query)) {
            $threads[] = array(
                'title' => htmlspecialchars_uni($row['subject']),
                'url'   => $bburl . '/' . get_thread_link($row['tid']),
                'sub'   => htmlspecialchars_uni($row['username']) . ' &middot; ' . my_number_format($row['replies']) . ' replies',
            );
        }
        $result['threads'] = $threads;
        $query = $db->query("
            SELECT p.pid, p.tid, p.message, p.dateline, t.subject, u.username
            FROM " . TABLE_PREFIX . "posts p
            INNER JOIN " . TABLE_PREFIX . "threads t ON t.tid = p.tid
            LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = p.uid
            WHERE p.visible = 1
              AND t.visible = 1
              AND p.fid IN ({$fid_list})
              AND p.message LIKE '%{$escaped}%'
            ORDER BY p.dateline DESC
            LIMIT 5
        ");
        $posts = array();
        while ($row = $db->fetch_array($query)) {
            // Extract snippet around the match
            $plain = strip_tags(str_replace(array('[', ']'), array('<', '>'), $row['message']));
            $plain = preg_replace('/\s+/', ' ', $plain);
            $pos = mb_stripos($plain, $q);
            $start = max(0, $pos - 40);
            $snippet = mb_substr($plain, $start, 100);
            if ($start > 0) $snippet = '...' . $snippet;
            if (mb_strlen($plain) > $start + 100) $snippet .= '...';

            $posts[] = array(
                'title' => htmlspecialchars_uni($snippet),
                'url'   => $bburl . '/' . get_post_link($row['pid'], $row['tid']) . '#pid' . $row['pid'],
                'sub'   => 'in ' . htmlspecialchars_uni($row['subject']),
            );
        }
        $result['posts'] = $posts;
    } else {
        $result['threads'] = array();
        $result['posts'] = array();
    }
    if ($mybb->usergroup['canviewprofiles'] != 0) {
        $query = $db->query("
            SELECT uid, username, avatar, postnum
            FROM " . TABLE_PREFIX . "users
            WHERE username LIKE '%{$escaped}%'
            ORDER BY postnum DESC
            LIMIT 5
        ");
        $users = array();
        while ($row = $db->fetch_array($query)) {
            $av = !empty($row['avatar']) ? htmlspecialchars_uni($row['avatar']) : $bburl . '/images/default_avatar.png';
            $users[] = array(
                'title'  => htmlspecialchars_uni($row['username']),
                'url'    => $bburl . '/' . get_profile_link($row['uid']),
                'avatar' => $av,
                'sub'    => my_number_format($row['postnum']) . ' posts',
            );
        }
        $result['users'] = $users;
    } else {
        $result['users'] = array();
    }
    $query = $db->query("
        SELECT aid, subject, startdate
        FROM " . TABLE_PREFIX . "announcements
        WHERE startdate <= " . TIME_NOW . "
          AND (enddate = 0 OR enddate >= " . TIME_NOW . ")
          AND subject LIKE '%{$escaped}%'
        ORDER BY startdate DESC
        LIMIT 3
    ");
    $announcements = array();
    while ($row = $db->fetch_array($query)) {
        $announcements[] = array(
            'title' => htmlspecialchars_uni($row['subject']),
            'url'   => $bburl . '/announcements.php?aid=' . (int)$row['aid'],
            'sub'   => my_date('M j, Y', $row['startdate']),
        );
    }
    $result['announcements'] = $announcements;

    echo json_encode($result);
    exit;
}
