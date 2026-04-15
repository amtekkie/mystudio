<?php
/**
 * MyStudio Default — Theme Hooks
 *
 * Register any MyBB hooks that the theme needs. This file is loaded on
 * every frontend page when MyStudio is active and this theme is the
 * default theme.
 *
 * Available globals: $mybb, $plugins, $lang, $theme
 * Theme options:     $mybb->ms_theme_options['key']
 */

global $plugins;
include_once __DIR__ . '/posting-extras.php';

// Inject custom CSS/JS from theme options into the page
$plugins->add_hook('pre_output_page', 'msdefault_inject_custom_code');

// Profile page — avatar change overlay + modal (own profile only)
$plugins->add_hook('member_profile_end', 'msdefault_profile_avatar_modal');

// Profile page — stat card modals (reputation, referrals)
$plugins->add_hook('member_profile_end', 'msdefault_profile_stat_modals');

// Index page — board-stats sidebar (runs just before the index template is eval'd)
$plugins->add_hook('index_end', 'msdefault_index_sidebar');

// Quick-reply SCEditor — inject codebuttons for showthread & PM read
$plugins->add_hook('showthread_start', 'msdefault_quickreply_codebuttons_showthread');
$plugins->add_hook('private_read',     'msdefault_quickreply_codebuttons_pm');

// Run language + template variable setup immediately (hooks.php is loaded at
// global_intermediate, so global_start has already fired — we must call directly)
msdefault_load_language();

// Set up welcome-block badge counts (before templates are eval'd)
msdefault_welcomeblock_badges();

/**
 * Compute welcome-block badge HTML for unread PMs and pending buddy requests.
 * Called at global_intermediate, before the welcomeblock templates are eval'd.
 */
function msdefault_welcomeblock_badges()
{
    global $mybb, $db;

    // Defaults — empty strings so template vars don't error
    $GLOBALS['ms_pm_badge']    = '';
    $GLOBALS['ms_buddy_badge'] = '';

    if (empty($mybb->user['uid'])) return;

    // Unread PMs badge (top-right offset)
    $unread = (int) $mybb->user['pms_unread'];
    if ($unread > 0 && $mybb->settings['enablepms'] != 0 && $mybb->usergroup['canusepms'] == 1) {
        $GLOBALS['ms_pm_badge'] = '<span class="ms-pm-badge badge rounded-pill bg-danger ms-1" style="font-size:9px">' 
            . my_number_format($unread) . '</span>';
    }

    // Pending buddy requests badge (top-right offset)
    $uid = (int) $mybb->user['uid'];
    if ($db->table_exists('buddyrequests')) {
    $query = $db->simple_select('buddyrequests', 'COUNT(*) AS cnt', "touid='{$uid}'");
    $pending = (int) $db->fetch_field($query, 'cnt');
    if ($pending > 0) {
        $GLOBALS['ms_buddy_badge'] = '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px;z-index:1">' 
            . my_number_format($pending) . '</span>';
    }
    } // end table_exists check
}

/**
 * Load the theme's language pack.
 * Files live in themes/mystudio-default/lang/{langcode}/
 */
function msdefault_load_language()
{
    global $lang, $mybb;

    // Determine language code (e.g. "english" -> "en", fallback "en")
    $langMap = array('english' => 'en', 'en' => 'en');
    $mybbLang = isset($mybb->settings['bblanguage']) ? strtolower($mybb->settings['bblanguage']) : 'english';
    $code = isset($langMap[$mybbLang]) ? $langMap[$mybbLang] : 'en';

    $langFile = MYBB_ROOT . 'themes/mystudio-default/lang/' . $code . '/frontend.lang.php';
    if (!file_exists($langFile)) {
        $langFile = MYBB_ROOT . 'themes/mystudio-default/lang/en/frontend.lang.php';
    }

    if (file_exists($langFile)) {
        // Load into $lang object
        $l = array();
        require $langFile;
        foreach ($l as $key => $val) {
            $lang->$key = $val;
        }
    }

    // Interpolate placeholders that need runtime values
    if (isset($lang->ms_footer_about_text)) {
        $lang->ms_footer_about_text = str_replace('{1}', $mybb->settings['bbname'], $lang->ms_footer_about_text);
    }

    // Load usercp lang strings for avatar modal (only when logged in)
    if ($mybb->user['uid'] > 0) {
        $lang->load("usercp");
    }

    // Load theme options
    $opts = isset($mybb->ms_theme_options) ? $mybb->ms_theme_options : array();

    // Build logo HTML from options
    $logoHtml = '';
    if (!empty($opts['site_logo'])) {
        // Image logo takes priority
        $logoUrl = htmlspecialchars_uni($mybb->settings['bburl'] . '/' . $opts['site_logo']);
        $w = !empty($opts['site_logo_width'])  ? (int) $opts['site_logo_width']  : 0;
        $h = !empty($opts['site_logo_height']) ? (int) $opts['site_logo_height'] : 0;
        $style = '';
        if ($w > 0) $style .= 'width:' . $w . 'px;';
        if ($h > 0) $style .= 'height:' . $h . 'px;';
        if (empty($style)) $style = 'max-height:60px;width:auto;';
        $logoHtml = '<img src="' . $logoUrl . '" alt="' . htmlspecialchars_uni($mybb->settings['bbname']) . '" style="' . $style . '" />';
    } else {
        // Icon + Text logo
        $icon = !empty($opts['logo_icon']) ? $opts['logo_icon'] : '';
        // Validate icon class format
        if ($icon !== '' && !preg_match('/^bi-[a-z0-9-]+$/', $icon)) {
            $icon = 'bi-brush';
        }
        $text = !empty($opts['logo_text']) ? htmlspecialchars_uni($opts['logo_text']) : '';
        if ($icon) {
            $logoHtml .= '<i class="bi ' . htmlspecialchars_uni($icon) . ' fs-4"></i>';
        }
        if ($text) {
            $logoHtml .= $text;
        }
        if (empty($logoHtml)) {
            // Fallback to board name
            $logoHtml = htmlspecialchars_uni($mybb->settings['bbname']);
        }
    }
    $GLOBALS['ms_logo_html'] = $logoHtml;

    // Defaults for index sidebar layout
    $GLOBALS['ms_index_col_class'] = 'col-12';
    $GLOBALS['ms_sidebar'] = '';
}

/**
 * Inject custom CSS and JS from theme options into the final page output.
 * Also handles logo replacement and favicon injection.
 */
function msdefault_inject_custom_code(&$contents)
{
    global $mybb;

    $opts = isset($mybb->ms_theme_options) ? $mybb->ms_theme_options : array();
    $bburl = $mybb->settings['bburl'];

    $headInject  = '';
    $bodyInject  = '';

    // Favicon
    if (!empty($opts['favicon'])) {
        $faviconUrl = htmlspecialchars($bburl . '/' . $opts['favicon']);
        $ext = strtolower(pathinfo($opts['favicon'], PATHINFO_EXTENSION));
        $mimeMap = array('ico' => 'image/x-icon', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'gif' => 'image/gif', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp');
        $mime = isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'image/x-icon';

        // Remove existing favicon links and add ours
        $contents = preg_replace('/<link[^>]*rel=["\'](?:shortcut )?icon["\'][^>]*>/i', '', $contents);
        $headInject .= '<link rel="icon" type="' . $mime . '" href="' . $faviconUrl . '" />' . "\n";
        $headInject .= '<link rel="shortcut icon" href="' . $faviconUrl . '" />' . "\n";
    }

    // Logo — handled via template variable now, no JS injection needed
    if (!isset($mybb->settings['ms_loading_bar']) || $mybb->settings['ms_loading_bar'] !== '0') {
    $headInject .= '<style>'
        . '#ms-loader{position:fixed;top:0;left:0;width:0;height:3px;background:var(--tekbb-accent,#0d9488);z-index:99999;pointer-events:none;transition:none;box-shadow:0 0 8px var(--tekbb-accent,#0d9488);}'
        . '#ms-loader.ms-loading{transition:width .6s cubic-bezier(.1,.7,.3,1);width:85%;}'
        . '#ms-loader.ms-done{transition:width .15s ease-out, opacity .25s .15s ease;width:100%;opacity:0;}'
        . '</style>' . "\n";
    $bodyInject .= '<div id="ms-loader"></div>' . "\n"
        . '<script>'
        . '(function(){'
        . 'var bar=document.getElementById("ms-loader");'
        . 'if(!bar)return;'
        . 'if(sessionStorage.getItem("ms-nav")){'
        .   'sessionStorage.removeItem("ms-nav");'
        .   'bar.className="ms-loading";'
        .   'window.addEventListener("load",function(){'
        .     'bar.className="ms-done";'
        .     'setTimeout(function(){bar.className="";bar.style.width="0";},400);'
        .   '});'
        . '}'
        . 'function triggerNav(){'
        .   'sessionStorage.setItem("ms-nav","1");'
        .   'bar.className="";bar.style.width="0";void bar.offsetWidth;bar.className="ms-loading";'
        . '}'
        . 'document.addEventListener("click",function(e){'
        .   'var a=e.target.closest("a");'
        .   'if(!a)return;'
        .   'var h=a.getAttribute("href");'
        .   'if(!h||h.charAt(0)==="#"||h.indexOf("javascript:")===0)return;'
        .   'if(a.target==="_blank"||e.ctrlKey||e.metaKey||e.shiftKey)return;'
        .   'if(a.hasAttribute("data-bs-toggle")||a.hasAttribute("onclick"))return;'
        .   'triggerNav();'
        . '});'
        . 'document.addEventListener("submit",function(){triggerNav();});'
        . '})();'
        . '</script>' . "\n";
    }
    $themeBase = $bburl . '/themes/mystudio-default';
    $headInject .= '<link rel="stylesheet" href="' . $themeBase . '/css/posting-extras.css" type="text/css" />' . "\n";
    $headInject .= '<script type="text/javascript" src="' . $themeBase . '/js/posting-extras.js"></script>' . "\n";
    $headInject .= '<script type="text/javascript" src="' . $themeBase . '/js/quicksearch.js"></script>' . "\n";

    if ($headInject) {
        $contents = str_replace('</head>', $headInject . '</head>', $contents);
    }
    if ($bodyInject) {
        $contents = str_replace('</body>', $bodyInject . '</body>', $contents);
    }

    return $contents;
}

/**
 * Index page: prepare birthdays card.
 * Hooked on index_end (runs just before the index template is eval'd).
 */
function msdefault_index_sidebar()
{
    global $birthdays, $birthdays_card;

    // Wrap birthdays in a card only if content exists
    $birthdays_card = '';
    if (!empty($birthdays)) {
        $birthdays_card = '<hr class="my-2" style="border-color: var(--glass-border)"><div class="small text-muted py-1">' . $birthdays . '</div>';
    }
}

/**
 * Profile page: inject avatar-change overlay + modal for own profile.
 * Hooked on member_profile_end (runs just before the profile template is eval'd).
 */
function msdefault_profile_avatar_modal()
{
    global $mybb, $lang, $memprofile, $uid, $templates;

    // Only show on own profile, and only if logged in
    if ($mybb->user['uid'] <= 0 || $mybb->user['uid'] != $uid) {
        $GLOBALS['profile_avatar_overlay'] = '';
        $GLOBALS['profile_avatar_modal']   = '';
        return;
    }

    // Camera overlay (sits inside .profile-avatar-wrap)
    $GLOBALS['profile_avatar_overlay'] = '<div class="profile-avatar-overlay" data-bs-toggle="modal" data-bs-target="#ms_profile_avatar_modal"><i class="bi bi-camera-fill"></i></div>';

    // Modal HTML
    $postKey  = htmlspecialchars_uni($mybb->post_code);
    $bburl    = $mybb->settings['bburl'];
    $curAvatar = htmlspecialchars_uni($mybb->user['avatar'] ?: 'images/default_avatar.png');
    $removeUrl = htmlspecialchars_uni($bburl . '/usercp.php?action=avatar&remove=1&my_post_key=' . $mybb->post_code);

    $changeAvatarLang = isset($lang->change_avatar) ? $lang->change_avatar : 'Change Avatar';
    $uploadLang       = isset($lang->avatar_upload) ? $lang->avatar_upload : 'Upload Avatar';
    $urlLang          = isset($lang->avatar_url)    ? $lang->avatar_url    : 'Avatar URL';
    $urlTipLang       = isset($lang->avatar_url_gravatar) ? $lang->avatar_url_gravatar : 'You can also use a Gravatar URL.';
    $removeLang       = isset($lang->remove_avatar) ? $lang->remove_avatar : 'Remove Avatar';

    // Set template variables
    $ms_avatar_post_key     = $postKey;
    $ms_avatar_cur          = $curAvatar;
    $ms_avatar_remove_url   = $removeUrl;
    $ms_avatar_change_lang  = $changeAvatarLang;
    $ms_avatar_upload_lang  = $uploadLang;
    $ms_avatar_url_lang     = $urlLang;
    $ms_avatar_url_tip_lang = $urlTipLang;
    $ms_avatar_remove_lang  = $removeLang;

    eval("\$GLOBALS['profile_avatar_modal'] = \"" . $templates->get("member_profile_avatar_modal") . "\";");
}

/**
 * Profile page: build stat-card modals for Reputation and Referrals.
 * Hooked on member_profile_end.
 */
function msdefault_profile_stat_modals()
{
    global $mybb, $lang, $memprofile, $uid, $memperms, $templates;
    $GLOBALS['stat_reputation_modal'] = '';
    $GLOBALS['stat_rate_modal'] = '';
    if (isset($memperms) && $memperms['usereputationsystem'] == 1 && $mybb->settings['enablereputation'] == 1)
    {
        $repValue   = get_reputation($memprofile['reputation']);
        $repUid     = (int) $memprofile['uid'];
        $repUser    = htmlspecialchars_uni($memprofile['username']);
        $repLabel   = isset($lang->reputation) ? $lang->reputation : 'Reputation';
        $detailsLbl = isset($lang->reputation_details) ? $lang->reputation_details : 'Details';

        $modalBody = '<div class="stat-modal-value">' . $repValue . '</div>';

        // Determine the action area
        if ($mybb->user['uid'] > 0 && $mybb->user['uid'] == $repUid) {
            // Viewing own profile — can't rate yourself
            $cantRate = isset($lang->add_yours) ? $lang->add_yours : $lang->ms_rep_cant_own;
            $modalBody .= '<div class="text-muted small mt-3 mb-3"><i class="bi bi-info-circle me-1"></i>' . $cantRate . '</div>';
        } elseif ($mybb->user['uid'] > 0 && $mybb->usergroup['cangivereputations'] == 1
            && ($mybb->settings['posrep'] || $mybb->settings['neurep'] || $mybb->settings['negrep'])) {
            // Other user — can rate: open the BS5 rate form modal
            $rateLbl = isset($lang->reputation_vote) ? $lang->reputation_vote : 'Rate';
            $modalBody .= '<div class="mt-3 mb-3">'
                . '<a href="javascript:void(0)" onclick="bootstrap.Modal.getInstance(document.getElementById(\'statReputationModal\')).hide();var rm=new bootstrap.Modal(document.getElementById(\'msRateUserModal\'));rm.show();return false;" class="btn btn-sm btn-outline-primary"><i class="bi bi-star me-1"></i>' . $rateLbl . '</a>'
                . '</div>';
            global $db;
            $existing_rep = null;
            $rid = 0;
            if ($mybb->settings['multirep'] != 1)
            {
                $query = $db->simple_select("reputation", "*", "adduid='" . (int)$mybb->user['uid'] . "' AND uid='{$repUid}' AND pid='0'");
                $existing_rep = $db->fetch_array($query);
                if ($existing_rep) {
                    $rid = (int) $existing_rep['rid'];
                }
            }

            $reputationpower = (int) $mybb->usergroup['reputationpower'];
            $options = '';

            // Positive options (highest first)
            if ($mybb->settings['posrep'])
            {
                for ($v = $reputationpower; $v >= 1; $v--)
                {
                    $sel = ($existing_rep && (int)$existing_rep['reputation'] === $v) ? ' selected' : '';
                    $options .= '<option value="' . $v . '" class="text-success"' . $sel . '>+' . $v . '</option>';
                }
            }

            // Neutral option
            if ($mybb->settings['neurep'])
            {
                $sel = ($existing_rep && (int)$existing_rep['reputation'] === 0) ? ' selected' : '';
                $options .= '<option value="0" class="text-muted"' . $sel . '>' . $lang->ms_rep_neutral . '</option>';
            }

            // Negative options
            if ($mybb->settings['negrep'])
            {
                for ($v = -1; $v >= -$reputationpower; $v--)
                {
                    $sel = ($existing_rep && (int)$existing_rep['reputation'] === $v) ? ' selected' : '';
                    $options .= '<option value="' . $v . '" class="text-danger"' . $sel . '>' . $v . '</option>';
                }
            }

            $existingComments = $existing_rep ? htmlspecialchars_uni($existing_rep['comments']) : '';
            $voteTitle   = $existing_rep ? $lang->ms_rep_update : $lang->ms_rep_add;
            $voteBtnLbl  = $existing_rep ? $lang->ms_rep_update_vote : $lang->ms_rep_add_vote;
            $postCode    = $mybb->post_code;

            // Set template variables
            $ms_rate_title      = $voteTitle;
            $ms_rate_btn_lbl    = $voteBtnLbl;
            $ms_rate_post_code  = $postCode;
            $ms_rate_uid        = $repUid;
            $ms_rate_rid        = $rid;
            $ms_rate_options    = $options;
            $ms_rate_existing_comments = $existingComments;

            eval("\$GLOBALS['stat_rate_modal'] = \"" . $templates->get("member_profile_rate_modal") . "\";");
        } else {
            // Guest or no permission
            $modalBody .= '<div class="mt-3"></div>';
        }

        // Always show Details link to reputation report page
        $modalBody .= '<a href="reputation.php?uid=' . $repUid . '" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bar-chart me-1"></i>' . $detailsLbl . '</a>';

        $ms_rep_label      = $repLabel;
        $ms_rep_modal_body = $modalBody;
        eval("\$GLOBALS['stat_reputation_modal'] = \"" . $templates->get("member_profile_stat_reputation_modal") . "\";");
    }
    $GLOBALS['stat_referrals_modal'] = '';
    if ($mybb->settings['usereferrals'] == 1)
    {
        $refCount = my_number_format((int) $memprofile['referrals']);
        $refLabel = isset($lang->members_referred) ? $lang->members_referred : 'Members Referred';

        $ms_ref_label = $refLabel;
        $ms_ref_count = $refCount;
        eval("\$GLOBALS['stat_referrals_modal'] = \"" . $templates->get("member_profile_stat_referrals_modal") . "\";");
    }
}

/**
 * Populate $codebuttons for the showthread quick-reply form.
 * Core MyBB does not call build_mycode_inserter() here, so the SCEditor
 * never initialises.  We set the global early (showthread_start) so it is
 * available when the showthread_quickreply template is eval'd.
 */
function msdefault_quickreply_codebuttons_showthread()
{
    global $mybb, $forum, $forumpermissions;

    if (
        $mybb->settings['bbcodeinserter'] != 0
        && isset($forum['allowmycode']) && $forum['allowmycode'] != 0
        && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0)
    ) {
        $GLOBALS['codebuttons'] = build_mycode_inserter(
            'message',
            isset($forum['allowsmilies']) ? $forum['allowsmilies'] : 1
        );
    }
}

/**
 * Populate $codebuttons for the PM read quick-reply form.
 * The "send" action already calls build_mycode_inserter(), but the "read"
 * action (which renders private_quickreply) does not.
 */
function msdefault_quickreply_codebuttons_pm()
{
    global $mybb;

    if (
        $mybb->settings['bbcodeinserter'] != 0
        && $mybb->settings['pmsallowmycode'] != 0
        && $mybb->user['showcodebuttons'] != 0
    ) {
        $GLOBALS['codebuttons'] = build_mycode_inserter(
            'message',
            $mybb->settings['pmsallowsmilies']
        );
    }
}