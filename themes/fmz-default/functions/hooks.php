<?php
/**
 * FMZ Default — Theme Hooks
 *
 * Register any MyBB hooks that the theme needs. This file is loaded on
 * every frontend page when FMZ Studio is active and this theme is the
 * default theme.
 *
 * Available globals: $mybb, $plugins, $lang, $theme
 * Theme options:     $mybb->fmz_theme_options['key']
 */

global $plugins;

// Inject custom CSS/JS from theme options into the page
$plugins->add_hook('pre_output_page', 'fmzdefault_inject_custom_code');

// Profile page — avatar change overlay + modal (own profile only)
$plugins->add_hook('member_profile_end', 'fmzdefault_profile_avatar_modal');

// Profile page — stat card modals (reputation, referrals)
$plugins->add_hook('member_profile_end', 'fmzdefault_profile_stat_modals');

// Index page — board-stats sidebar (runs just before the index template is eval'd)
$plugins->add_hook('index_end', 'fmzdefault_index_sidebar');

// Run language + template variable setup immediately (hooks.php is loaded at
// global_intermediate, so global_start has already fired — we must call directly)
fmzdefault_load_language();

// Set up welcome-block badge counts (before templates are eval'd)
fmzdefault_welcomeblock_badges();

/**
 * Compute welcome-block badge HTML for unread PMs and pending buddy requests.
 * Called at global_intermediate, before the welcomeblock templates are eval'd.
 */
function fmzdefault_welcomeblock_badges()
{
    global $mybb, $db;

    // Defaults — empty strings so template vars don't error
    $GLOBALS['fmz_pm_badge']    = '';
    $GLOBALS['fmz_buddy_badge'] = '';

    if (empty($mybb->user['uid'])) return;

    // Unread PMs badge (top-right offset)
    $unread = (int) $mybb->user['pms_unread'];
    if ($unread > 0 && $mybb->settings['enablepms'] != 0 && $mybb->usergroup['canusepms'] == 1) {
        $GLOBALS['fmz_pm_badge'] = '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px;z-index:1">' 
            . my_number_format($unread) . '</span>';
    }

    // Pending buddy requests badge (top-right offset)
    $uid = (int) $mybb->user['uid'];
    if ($db->table_exists('buddyrequests')) {
    $query = $db->simple_select('buddyrequests', 'COUNT(*) AS cnt', "touid='{$uid}'");
    $pending = (int) $db->fetch_field($query, 'cnt');
    if ($pending > 0) {
        $GLOBALS['fmz_buddy_badge'] = '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px;z-index:1">' 
            . my_number_format($pending) . '</span>';
    }
    } // end table_exists check
}

/**
 * Load the theme's language pack.
 * Files live in themes/fmz-default/lang/{langcode}/
 */
function fmzdefault_load_language()
{
    global $lang, $mybb;

    // Determine language code (e.g. "english" -> "en", fallback "en")
    $langMap = array('english' => 'en', 'en' => 'en');
    $mybbLang = isset($mybb->settings['bblanguage']) ? strtolower($mybb->settings['bblanguage']) : 'english';
    $code = isset($langMap[$mybbLang]) ? $langMap[$mybbLang] : 'en';

    $langFile = MYBB_ROOT . 'themes/fmz-default/lang/' . $code . '/frontend.lang.php';
    if (!file_exists($langFile)) {
        $langFile = MYBB_ROOT . 'themes/fmz-default/lang/en/frontend.lang.php';
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
    if (isset($lang->fmz_footer_about_text)) {
        $lang->fmz_footer_about_text = str_replace('{1}', $mybb->settings['bbname'], $lang->fmz_footer_about_text);
    }

    // Load usercp lang strings for avatar modal (only when logged in)
    if ($mybb->user['uid'] > 0) {
        $lang->load("usercp");
    }

    // Build custom navigation links HTML from JSON option
    $opts = isset($mybb->fmz_theme_options) ? $mybb->fmz_theme_options : array();

    // Footer about text: override language string when theme option is set
    if (!empty($opts['footer_about_text'])) {
        $lang->fmz_footer_about_text = str_replace('{boardname}', $mybb->settings['bbname'], $opts['footer_about_text']);
    }

    $fmz_custom_nav = '';
    if (!empty($opts['custom_nav_links'])) {
        $links = @json_decode($opts['custom_nav_links'], true);
        if (is_array($links)) {
            foreach ($links as $link) {
                if (empty($link['text']) || empty($link['url'])) continue;
                $text = htmlspecialchars_uni($link['text']);
                $rawUrl = $link['url'];
                // Block javascript: and data: URI schemes
                if (preg_match('/^\s*(javascript|data|vbscript)\s*:/i', $rawUrl)) continue;
                // Prepend bburl for relative URLs
                if (strpos($rawUrl, 'http') !== 0 && strpos($rawUrl, '//') !== 0 && strpos($rawUrl, '/') !== 0) {
                    $rawUrl = $mybb->settings['bburl'] . '/' . $rawUrl;
                }
                $url  = htmlspecialchars_uni($rawUrl);
                $icon = !empty($link['icon']) ? $link['icon'] : '';
                if ($icon !== '' && !preg_match('/^bi-[a-z0-9-]+$/', $icon)) {
                    $icon = '';
                }
                $icon = $icon ? '<i class="bi ' . htmlspecialchars_uni($icon) . ' me-1"></i>' : '';
                $target = (strpos($link['url'], 'http') === 0) ? ' target="_blank" rel="noopener"' : '';
                $fmz_custom_nav .= '<li class="nav-item"><a class="nav-link" href="' . $url . '"' . $target . '>' . $icon . $text . '</a></li>' . "\n";
            }
        }
    }
    $GLOBALS['fmz_custom_nav'] = $fmz_custom_nav;

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
            $icon = 'bi-chat-square';
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
    $GLOBALS['fmz_logo_html'] = $logoHtml;

    // Header style class
    $headerStyle = !empty($opts['header_style']) ? $opts['header_style'] : 'default';
    $GLOBALS['fmz_header_class'] = 'fmz-header-' . preg_replace('/[^a-z0-9\-]/', '', $headerStyle);

    // Footer custom text (appears below copyright bar)
    $GLOBALS['fmz_footer_text_row'] = '';
    if (!empty($opts['footer_text'])) {
        $GLOBALS['fmz_footer_text_row'] = '<div class="pb-3 small opacity-75 text-center">' . $opts['footer_text'] . '</div>';
    }

    // Show sidebar flag
    $GLOBALS['fmz_show_sidebar'] = !empty($opts['show_sidebar']) && $opts['show_sidebar'] !== '0';

    // Defaults for index sidebar layout (overridden in fmzdefault_index_sidebar)
    $GLOBALS['fmz_index_col_class'] = 'col-12';
    $GLOBALS['fmz_sidebar'] = '';
}

/**
 * Inject custom CSS and JS from theme options into the final page output.
 * Also handles logo replacement and favicon injection.
 */
function fmzdefault_inject_custom_code(&$contents)
{
    global $mybb;

    $opts = isset($mybb->fmz_theme_options) ? $mybb->fmz_theme_options : array();
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

    // ── Color Palette CSS variables ──
    // Build overrides for any palette colors that differ from defaults
    $paletteDefaults = array(
        'light' => array(
            'body_bg'      => '#f5f6fa',
            'body_color'   => '#1e293b',
            'accent'       => '#0d9488',
            'accent_hover' => '#0f766e',
            'heading_bg'   => '#0d9488',
            'surface'      => '#ffffff',
            'border'       => '#dbefed',
            'muted'        => '#64748b',
            'text_inv'     => '#ffffff',
            'link'         => '#0d9488',
            'link_hover'   => '#0f766e',
            'btn_bg'       => '#0d9488',
            'btn_hover'    => '#0f766e',
            'nav_bg'       => '#ffffff',
            'footer_bg'    => '#f1f5f9',
            'footer_color' => '#475569',
        ),
        'dark' => array(
            'body_bg'      => '#0f172a',
            'body_color'   => '#e2e8f0',
            'accent'       => '#2dd4bf',
            'accent_hover' => '#5eead4',
            'heading_bg'   => '#0d9488',
            'surface'      => '#1e293b',
            'border'       => '#122d3b',
            'muted'        => '#94a3b8',
            'text_inv'     => '#ffffff',
            'link'         => '#2dd4bf',
            'link_hover'   => '#5eead4',
            'btn_bg'       => '#2dd4bf',
            'btn_hover'    => '#5eead4',
            'nav_bg'       => '#1e293b',
            'footer_bg'    => '#1e293b',
            'footer_color' => '#94a3b8',
        ),
    );

    $cssVarMap = array(
        'body_bg'      => '--bs-body-bg',
        'body_color'   => '--bs-body-color',
        'accent'       => '--tekbb-accent',
        'accent_hover' => '--tekbb-accent-hover',
        'heading_bg'   => '--tekbb-heading-bg',
        'surface'      => '--tekbb-surface',
        'border'       => '--tekbb-border',
        'muted'        => '--tekbb-muted',
        'text_inv'     => '--tekbb-text-inv',
        'link'         => '--tekbb-link',
        'link_hover'   => '--tekbb-link-hover',
        'btn_bg'       => '--tekbb-btn-bg',
        'btn_hover'    => '--tekbb-btn-hover',
        'nav_bg'       => '--tekbb-nav-bg',
        'footer_bg'    => '--tekbb-footer-bg',
        'footer_color' => '--tekbb-footer-color',
    );

    $lightOverrides = '';
    $darkOverrides  = '';

    foreach ($cssVarMap as $shortKey => $cssVar) {
        // Light mode
        $optKey = 'light_' . $shortKey;
        $val = !empty($opts[$optKey]) ? $opts[$optKey] : '';
        $def = $paletteDefaults['light'][$shortKey];
        if ($val !== '' && strtolower($val) !== strtolower($def)) {
            $lightOverrides .= $cssVar . ':' . htmlspecialchars($val) . ';';
        }
        // Also generate accent-subtle from accent
        if ($shortKey === 'accent' && $val !== '' && strtolower($val) !== strtolower($def)) {
            $hex = ltrim($val, '#');
            if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
            $lightOverrides .= '--tekbb-accent-subtle:rgba(' . $r . ',' . $g . ',' . $b . ',.08);';
        }

        // Dark mode
        $optKey = 'dark_' . $shortKey;
        $val = !empty($opts[$optKey]) ? $opts[$optKey] : '';
        $def = $paletteDefaults['dark'][$shortKey];
        if ($val !== '' && strtolower($val) !== strtolower($def)) {
            $darkOverrides .= $cssVar . ':' . htmlspecialchars($val) . ';';
        }
        if ($shortKey === 'accent' && $val !== '' && strtolower($val) !== strtolower($def)) {
            $hex = ltrim($val, '#');
            if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
            $darkOverrides .= '--tekbb-accent-subtle:rgba(' . $r . ',' . $g . ',' . $b . ',.1);';
        }
    }

    if ($lightOverrides || $darkOverrides) {
        $css = '<style>';
        if ($lightOverrides) {
            $css .= ':root,:root[data-theme="light"]{' . $lightOverrides . '}';
        }
        if ($darkOverrides) {
            $css .= ':root[data-theme="dark"]{' . $darkOverrides . '}';
        }
        $css .= '</style>' . "\n";
        $headInject .= $css;
    }

    // @deprecated Legacy accent_color support — kept for backward compatibility with pre-v2.0 saved options
    if (!empty($opts['accent_color']) && $opts['accent_color'] !== '#0d9488' && empty($opts['light_accent'])) {
        $color = htmlspecialchars($opts['accent_color']);
        $hex = ltrim($opts['accent_color'], '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
        $hoverColor = sprintf('#%02x%02x%02x', max(0,(int)($r*0.85)), max(0,(int)($g*0.85)), max(0,(int)($b*0.85)));
        $subtle = "rgba({$r},{$g},{$b},.08)";
        $headInject .= '<style>:root,:root[data-theme="light"]{--tekbb-accent:'.$color.';--tekbb-accent-hover:'.$hoverColor.';--tekbb-accent-subtle:'.$subtle.';--tekbb-heading-bg:'.$color.';--tekbb-link:'.$color.';--tekbb-link-hover:'.$hoverColor.';--tekbb-btn-bg:'.$color.';--tekbb-btn-hover:'.$hoverColor.';--tekbb-border:rgba('.$r.','.$g.','.$b.',.15);}</style>' . "\n";
    }




    // ── Page-transition loading bar (XenForo-style) ──
    if (!isset($opts['loading_bar']) || $opts['loading_bar'] !== '0') {
    $headInject .= '<style>'
        . '#fmz-loader{position:fixed;top:0;left:0;width:0;height:3px;background:var(--tekbb-accent,#0d9488);z-index:99999;pointer-events:none;transition:none;box-shadow:0 0 8px var(--tekbb-accent,#0d9488);}'
        . '#fmz-loader.fmz-loading{transition:width .6s cubic-bezier(.1,.7,.3,1);width:85%;}'
        . '#fmz-loader.fmz-done{transition:width .15s ease-out, opacity .25s .15s ease;width:100%;opacity:0;}'
        . '</style>' . "\n";
    $bodyInject .= '<div id="fmz-loader"></div>' . "\n"
        . '<script>'
        . '(function(){'
        . 'var bar=document.getElementById("fmz-loader");'
        . 'if(!bar)return;'
        . 'if(sessionStorage.getItem("fmz-nav")){'
        .   'sessionStorage.removeItem("fmz-nav");'
        .   'bar.className="fmz-loading";'
        .   'window.addEventListener("load",function(){'
        .     'bar.className="fmz-done";'
        .     'setTimeout(function(){bar.className="";bar.style.width="0";},400);'
        .   '});'
        . '}'
        . 'function triggerNav(){'
        .   'sessionStorage.setItem("fmz-nav","1");'
        .   'bar.className="";bar.style.width="0";void bar.offsetWidth;bar.className="fmz-loading";'
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

    if ($headInject) {
        $contents = str_replace('</head>', $headInject . '</head>', $contents);
    }
    if ($bodyInject) {
        $contents = str_replace('</body>', $bodyInject . '</body>', $contents);
    }

    return $contents;
}

/**
 * Index page: build a stats sidebar and adjust column layout.
 * Hooked on index_end (runs just before the index template is eval'd).
 */
function fmzdefault_index_sidebar()
{
    global $mybb, $lang, $stats, $boardstats, $whosonline, $birthdays, $recordcount;

    // Defaults
    $GLOBALS['fmz_index_col_class'] = 'col-12';
    $GLOBALS['fmz_sidebar'] = '';

    if (empty($GLOBALS['fmz_show_sidebar'])) {
        return;
    }

    // Don't build sidebar if stats aren't loaded
    if (empty($stats) || !is_array($stats)) {
        return;
    }

    // Clear the full-width boardstats section
    $boardstats = '';

    $sb = '<div class="col-md-3">' . "\n";

    // ── Board Stats Card ──
    $sb .= '<div class="card border shadow-sm mb-3">' . "\n";
    $sb .= '<div class="card-header text-white py-2 d-flex justify-content-between align-items-center" style="background:var(--tekbb-heading-bg)">' . "\n";
    $sb .= '<span class="fw-bold"><i class="bi bi-bar-chart-fill me-2"></i>' . $lang->boardstats . '</span>' . "\n";
    $sb .= '<a href="stats.php" class="text-white-50 small">' . $lang->forumstats . ' <i class="bi bi-arrow-right"></i></a>' . "\n";
    $sb .= '</div>' . "\n";
    $sb .= '<ul class="list-group list-group-flush">' . "\n";
    $sb .= '<li class="list-group-item d-flex justify-content-between align-items-center py-2"><span class="small"><i class="bi bi-chat-square-dots-fill text-primary me-2"></i>' . $lang->fmz_stats_posts . '</span><span class="fw-bold small">' . $stats['numposts'] . '</span></li>' . "\n";
    $sb .= '<li class="list-group-item d-flex justify-content-between align-items-center py-2"><span class="small"><i class="bi bi-file-text-fill text-info me-2"></i>' . $lang->fmz_stats_threads . '</span><span class="fw-bold small">' . $stats['numthreads'] . '</span></li>' . "\n";
    $sb .= '<li class="list-group-item d-flex justify-content-between align-items-center py-2"><span class="small"><i class="bi bi-people-fill text-success me-2"></i>' . $lang->fmz_stats_members . '</span><span class="fw-bold small">' . $stats['numusers'] . '</span></li>' . "\n";
    $sb .= '<li class="list-group-item d-flex justify-content-between align-items-center py-2"><span class="small"><i class="bi bi-graph-up-arrow text-warning me-2"></i>' . $lang->fmz_stats_most_online . '</span><span class="fw-bold small">' . $recordcount . '</span></li>' . "\n";
    $sb .= '</ul>' . "\n";
    $sb .= '<div class="card-body border-top py-2 small text-muted text-center">' . $lang->stats_newestuser . '</div>' . "\n";
    $sb .= '</div>' . "\n";

    // ── Who's Online Card ──
    if ($mybb->settings['showwol'] != 0) {
        $sb .= '<div class="card border shadow-sm mb-3">' . "\n";
        $sb .= '<div class="card-header text-white py-2 d-flex justify-content-between align-items-center" style="background:var(--tekbb-heading-bg)">' . "\n";
        $sb .= '<span class="fw-bold"><i class="bi bi-people-fill me-2"></i>Online</span>' . "\n";
        $sb .= '<a href="online.php" class="text-white-50 small">' . $lang->complete_list . ' <i class="bi bi-arrow-right"></i></a>' . "\n";
        $sb .= '</div>' . "\n";
        $sb .= '<div class="card-body small text-muted">' . $lang->whos_online . '<br />' . $whosonline . '</div>' . "\n";
        $sb .= '</div>' . "\n";
    }

    // ── Birthdays Card ──
    if (!empty($birthdays)) {
        $cleanBdays = str_replace(' border-top', '', $birthdays);
        $sb .= '<div class="card border shadow-sm mb-3"><div class="card-body py-2">' . $cleanBdays . '</div></div>' . "\n";
    }

    $sb .= '</div>' . "\n";

    $GLOBALS['fmz_index_col_class'] = 'col-md-9';
    $GLOBALS['fmz_sidebar'] = $sb;
}

/**
 * Profile page: inject avatar-change overlay + modal for own profile.
 * Hooked on member_profile_end (runs just before the profile template is eval'd).
 */
function fmzdefault_profile_avatar_modal()
{
    global $mybb, $lang, $memprofile, $uid;

    // Only show on own profile, and only if logged in
    if ($mybb->user['uid'] <= 0 || $mybb->user['uid'] != $uid) {
        $GLOBALS['profile_avatar_overlay'] = '';
        $GLOBALS['profile_avatar_modal']   = '';
        return;
    }

    // Camera overlay (sits inside .profile-avatar-wrap)
    $GLOBALS['profile_avatar_overlay'] = '<div class="profile-avatar-overlay" data-bs-toggle="modal" data-bs-target="#fmz_profile_avatar_modal"><i class="bi bi-camera-fill"></i></div>';

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

    $GLOBALS['profile_avatar_modal'] = <<<HTML
<div class="modal fade" id="fmz_profile_avatar_modal" tabindex="-1" aria-labelledby="fmz_profile_avatar_label" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered" style="max-width:440px">
<div class="modal-content">
    <form enctype="multipart/form-data" action="{$bburl}/usercp.php" method="post" id="fmz_profile_avatar_form">
        <input type="hidden" name="my_post_key" value="{$postKey}" />
        <input type="hidden" name="action" value="do_avatar" />
        <div class="modal-header">
            <h6 class="modal-title" id="fmz_profile_avatar_label"><i class="bi bi-person-bounding-box me-1"></i> {$changeAvatarLang}</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="text-center mb-3">
                <img src="{$curAvatar}" onerror="if(this.src!='images/default_avatar.png')this.src='images/default_avatar.png';" id="fmz_profile_avatar_preview" class="rounded-circle" style="width:100px;height:100px;object-fit:cover;border:3px solid var(--tekbb-accent)" alt="Current Avatar" />
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold"><i class="bi bi-upload me-1"></i> {$uploadLang}</label>
                <input type="file" name="avatarupload" class="form-control form-control-sm" accept="image/*" id="fmz_profile_avatar_file" />
            </div>
            <div class="mb-2">
                <label class="form-label small fw-semibold"><i class="bi bi-link-45deg me-1"></i> {$urlLang}</label>
                <input type="text" name="avatarurl" class="form-control form-control-sm" placeholder="https://example.com/avatar.png" value="" />
                <div class="form-text" style="font-size:11px">{$urlTipLang}</div>
            </div>
        </div>
        <div class="modal-footer d-flex justify-content-between">
            <a href="{$removeUrl}" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i> {$removeLang}</a>
            <input type="submit" name="submit" class="btn btn-sm btn-primary" value="{$changeAvatarLang}" />
        </div>
    </form>
</div>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
    var fi=document.getElementById('fmz_profile_avatar_file');
    var pv=document.getElementById('fmz_profile_avatar_preview');
    if(fi&&pv){fi.addEventListener('change',function(){if(this.files&&this.files[0]){var r=new FileReader();r.onload=function(e){pv.src=e.target.result;};r.readAsDataURL(this.files[0]);}});}
});
</script>
HTML;
}

/**
 * Profile page: build stat-card modals for Reputation and Referrals.
 * Hooked on member_profile_end.
 */
function fmzdefault_profile_stat_modals()
{
    global $mybb, $lang, $memprofile, $uid, $memperms;

    // ── Reputation Modal ──
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
            $cantRate = isset($lang->add_yours) ? $lang->add_yours : "You cannot add to your own reputation.";
            $modalBody .= '<div class="text-muted small mt-3 mb-3"><i class="bi bi-info-circle me-1"></i>' . $cantRate . '</div>';
        } elseif ($mybb->user['uid'] > 0 && $mybb->usergroup['cangivereputations'] == 1
            && ($mybb->settings['posrep'] || $mybb->settings['neurep'] || $mybb->settings['negrep'])) {
            // Other user — can rate: open the BS5 rate form modal
            $rateLbl = isset($lang->reputation_vote) ? $lang->reputation_vote : 'Rate';
            $modalBody .= '<div class="mt-3 mb-3">'
                . '<a href="javascript:void(0)" onclick="bootstrap.Modal.getInstance(document.getElementById(\'statReputationModal\')).hide();var rm=new bootstrap.Modal(document.getElementById(\'fmzRateUserModal\'));rm.show();return false;" class="btn btn-sm btn-outline-primary"><i class="bi bi-star me-1"></i>' . $rateLbl . '</a>'
                . '</div>';

            // ── Build Rate Form Modal ──
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
                $options .= '<option value="0" class="text-muted"' . $sel . '>0 (Neutral)</option>';
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
            $voteTitle   = $existing_rep ? 'Update Reputation' : 'Add Reputation';
            $voteBtnLbl  = $existing_rep ? 'Update Vote' : 'Add Vote';
            $postCode    = $mybb->post_code;

            $GLOBALS['stat_rate_modal'] = <<<HTML
<div class="modal fade" id="fmzRateUserModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-sm">
<div class="modal-content">
  <div class="modal-header">
    <h6 class="modal-title"><i class="bi bi-star me-1"></i> {$voteTitle}</h6>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
  </div>
  <div class="modal-body">
    <form id="fmzRateForm">
      <input type="hidden" name="my_post_key" value="{$postCode}" />
      <input type="hidden" name="action" value="do_add" />
      <input type="hidden" name="uid" value="{$repUid}" />
      <input type="hidden" name="pid" value="0" />
      <input type="hidden" name="rid" value="{$rid}" />
      <input type="hidden" name="nomodal" value="1" />
      <div class="mb-3">
        <label class="form-label small text-muted">Power</label>
        <select name="reputation" class="form-select form-select-sm">{$options}</select>
      </div>
      <div class="mb-3">
        <label class="form-label small text-muted">Comments</label>
        <input type="text" class="form-control form-control-sm" name="comments" maxlength="250" value="{$existingComments}" />
      </div>
      <div id="fmzRateResult" class="mb-2"></div>
      <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-check-lg me-1"></i>{$voteBtnLbl}</button>
    </form>
  </div>
</div>
</div>
</div>
<script>
document.getElementById('fmzRateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var result = document.getElementById('fmzRateResult');
    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting...';
    fetch('reputation.php', { method: 'POST', body: new FormData(form) })
    .then(function(r) { return r.text(); })
    .then(function(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        var msg = tmp.querySelector('.trow1');
        var hdr = tmp.querySelector('.thead');
        if (hdr && hdr.textContent.indexOf('Board Message') !== -1) {
            result.innerHTML = '<div class="alert alert-danger small py-1 px-2 mb-0"><i class="bi bi-exclamation-circle me-1"></i>' + (msg ? msg.textContent.trim() : 'An error occurred.') + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>{$voteBtnLbl}';
        } else {
            result.innerHTML = '<div class="alert alert-success small py-1 px-2 mb-0"><i class="bi bi-check-circle me-1"></i>' + (msg ? msg.textContent.trim() : 'Reputation updated!') + '</div>';
            setTimeout(function() { location.reload(); }, 1200);
        }
    })
    .catch(function() {
        result.innerHTML = '<div class="alert alert-danger small py-1 px-2 mb-0">Network error.</div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>{$voteBtnLbl}';
    });
});
</script>
HTML;
        } else {
            // Guest or no permission
            $modalBody .= '<div class="mt-3"></div>';
        }

        // Always show Details link to reputation report page
        $modalBody .= '<a href="reputation.php?uid=' . $repUid . '" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bar-chart me-1"></i>' . $detailsLbl . '</a>';

        $GLOBALS['stat_reputation_modal'] = <<<HTML
<div class="modal fade" id="statReputationModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-sm">
<div class="modal-content">
  <div class="modal-header">
    <h6 class="modal-title"><i class="bi bi-award me-1"></i> {$repLabel}</h6>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
  </div>
  <div class="modal-body text-center">
    {$modalBody}
  </div>
</div>
</div>
</div>
HTML;
    }

    // ── Referrals Modal ──
    $GLOBALS['stat_referrals_modal'] = '';
    if ($mybb->settings['usereferrals'] == 1)
    {
        $refCount = my_number_format((int) $memprofile['referrals']);
        $refLabel = isset($lang->members_referred) ? $lang->members_referred : 'Members Referred';

        $GLOBALS['stat_referrals_modal'] = <<<HTML
<div class="modal fade" id="statReferralsModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-sm">
<div class="modal-content">
  <div class="modal-header">
    <h6 class="modal-title"><i class="bi bi-people me-1"></i> {$refLabel}</h6>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
  </div>
  <div class="modal-body text-center">
    <div class="stat-modal-value">{$refCount}</div>
  </div>
</div>
</div>
</div>
HTML;
    }
}