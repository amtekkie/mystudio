<?php
/**
 * FMZ User Profile Extras — Mini Plugin Init
 *
 * Provides:
 *  - Profile banner customization (upload image, solid color, gradient)
 *  - Status updates with privacy levels (public / private / buddies-only)
 *  - Status feed page (misc.php?action=statusfeed)
 *
 * Auto-creates required database tables on first load.
 *
 * @version 1.0.0
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

if (defined('IN_ADMINCP')) return;

global $plugins, $db, $mybb;

// ── Auto-install: create tables if missing ──
fmz_profile_extras_install();

// ── Hooks ──
$plugins->add_hook('member_profile_end',  'fmz_profile_extras_banner');
$plugins->add_hook('usercp_start',        'fmz_profile_extras_usercp');

/* ═══════════════════════════════════════════════════════════════
   AUTO-INSTALL
   ═══════════════════════════════════════════════════════════════ */

function fmz_profile_extras_install()
{
    global $db;

    // Banners table
    if (!$db->table_exists('fmz_user_banners')) {
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "fmz_user_banners (
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
    if ($db->table_exists('fmz_user_banners') && !$db->field_exists('text_color', 'fmz_user_banners')) {
        $db->write_query("ALTER TABLE " . TABLE_PREFIX . "fmz_user_banners ADD COLUMN text_color VARCHAR(20) NOT NULL DEFAULT '' AFTER value");
        $db->write_query("ALTER TABLE " . TABLE_PREFIX . "fmz_user_banners ADD COLUMN link_color VARCHAR(20) NOT NULL DEFAULT '' AFTER text_color");
    }

    // Statuses table
    if (!$db->table_exists('fmz_user_statuses')) {
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "fmz_user_statuses (
                sid        INT UNSIGNED NOT NULL AUTO_INCREMENT,
                uid        INT UNSIGNED NOT NULL DEFAULT 0,
                message    TEXT         NOT NULL,
                privacy    ENUM('public','private','buddies') NOT NULL DEFAULT 'public',
                dateline   INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (sid),
                KEY uid (uid),
                KEY dateline (dateline),
                KEY privacy_date (privacy, dateline)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
        ");
    }

    // Status comments table
    if (!$db->table_exists('fmz_status_comments')) {
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "fmz_status_comments (
                cid        INT UNSIGNED NOT NULL AUTO_INCREMENT,
                sid        INT UNSIGNED NOT NULL DEFAULT 0,
                uid        INT UNSIGNED NOT NULL DEFAULT 0,
                message    TEXT         NOT NULL,
                dateline   INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (cid),
                KEY sid (sid),
                KEY uid (uid),
                KEY sid_date (sid, dateline)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci
        ");
    }
}

/* ═══════════════════════════════════════════════════════════════
   HELPERS — BBCode parser + comment rendering
   ═══════════════════════════════════════════════════════════════ */

/**
 * Parse a BBCode message through MyBB's parser (like forum posts).
 */
function fmz_pe_parse_message($message)
{
    require_once MYBB_ROOT . 'inc/class_parser.php';
    $parser = new postParser;
    return $parser->parse_message($message, array(
        'allow_html'       => 0,
        'allow_mycode'     => 1,
        'allow_smilies'    => 1,
        'allow_imgcode'    => 1,
        'allow_videocode'  => 1,
        'filter_badwords'  => 1,
        'nl2br'            => 1,
        'me_username'      => 0,
    ));
}

/**
 * Render a single comment HTML block.
 */
function fmz_pe_render_comment($comment)
{
    global $mybb;
    $bburl = $mybb->settings['bburl'];
    $cAvatar = !empty($comment['avatar']) ? htmlspecialchars_uni($comment['avatar']) : 'images/default_avatar.png';
    $cUsername = htmlspecialchars_uni($comment['username']);
    $cProfileUrl = htmlspecialchars_uni($bburl . '/member.php?action=profile&uid=' . (int)$comment['uid']);
    $cFormatted = format_name($comment['username'], $comment['usergroup'], $comment['displaygroup']);
    $cMessage = fmz_pe_parse_message($comment['message']);
    $cTime = my_date('relative', $comment['dateline']);
    $cid = (int)$comment['cid'];

    $deleteBtn = '';
    if ($mybb->user['uid'] == $comment['uid'] || $mybb->usergroup['canmodcp']) {
        $deleteBtn = '<a href="javascript:void(0)" class="fmz-comment-delete text-danger small ms-auto" data-cid="' . $cid . '"><i class="bi bi-x-lg"></i></a>';
    }

    return '<div class="status-comment" data-cid="' . $cid . '">'
        . '<div class="status-comment-avatar"><a href="' . $cProfileUrl . '"><img src="' . $cAvatar . '" onerror="if(this.src!=\'images/default_avatar.png\')this.src=\'images/default_avatar.png\';" alt="' . $cUsername . '" /></a></div>'
        . '<div class="status-comment-body">'
        . '<div class="status-comment-header"><a href="' . $cProfileUrl . '" class="status-comment-username">' . $cFormatted . '</a> <span class="status-comment-time">' . $cTime . '</span>' . $deleteBtn . '</div>'
        . '<div class="status-comment-message">' . $cMessage . '</div>'
        . '</div></div>';
}

/**
 * Render the comments block for a status (list + compose form).
 */
function fmz_pe_render_comments_block($sid, $statusUid)
{
    global $mybb, $db;
    $sid = (int)$sid;

    // Fetch comments
    $query = $db->query("
        SELECT c.*, u.username, u.usergroup, u.displaygroup, u.avatar
        FROM " . TABLE_PREFIX . "fmz_status_comments c
        LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = c.uid
        WHERE c.sid={$sid}
        ORDER BY c.dateline ASC
        LIMIT 50
    ");

    $comments = '';
    $count = 0;
    while ($row = $db->fetch_array($query)) {
        $comments .= fmz_pe_render_comment($row);
        $count++;
    }

    // Comment count
    if ($count === 0) {
        $countQuery = $db->simple_select('fmz_status_comments', 'COUNT(*) AS cnt', "sid={$sid}");
        $count = (int)$db->fetch_field($countQuery, 'cnt');
    }

    // Comment compose (only logged in)
    $composeForm = '';
    if ($mybb->user['uid'] > 0) {
        $cAvatar = htmlspecialchars_uni(!empty($mybb->user['avatar']) ? $mybb->user['avatar'] : 'images/default_avatar.png');
        $composeForm = '<div class="status-comment-compose">'
            . '<img src="' . $cAvatar . '" onerror="if(this.src!=\'images/default_avatar.png\')this.src=\'images/default_avatar.png\';" alt="You" class="status-comment-compose-avatar" />'
            . '<input type="text" class="form-control form-control-sm status-comment-input" placeholder="Write a comment..." data-sid="' . $sid . '" />'
            . '</div>';
    }

    return '<div class="status-comments-section" data-sid="' . $sid . '">'
        . '<a href="javascript:void(0)" class="status-comments-toggle small text-muted" data-sid="' . $sid . '"><i class="bi bi-chat me-1"></i><span class="comment-count">' . $count . '</span> comment' . ($count !== 1 ? 's' : '') . '</a>'
        . '<div class="status-comments-list" style="display:none">' . $comments . $composeForm . '</div>'
        . '</div>';
}

/* ═══════════════════════════════════════════════════════════════
   BANNER — Inject style + change modal on member profile
   ═══════════════════════════════════════════════════════════════ */

function fmz_profile_extras_banner()
{
    global $mybb, $db, $lang, $memprofile, $uid, $templates;

    // ── Load active banner for this profile user ──
    $banner_style = '';
    $query = $db->simple_select('fmz_user_banners', '*', "uid=" . (int)$uid . " AND is_active=1", array('limit' => 1));
    $banner = $db->fetch_array($query);

    if ($banner) {
        switch ($banner['type']) {
            case 'upload':
                $imgUrl = htmlspecialchars_uni($mybb->settings['bburl'] . '/' . $banner['value']);
                $banner_style = "background-image:url('{$imgUrl}');background-size:cover;background-position:center;";
                break;
            case 'solid':
                $banner_style = "background:" . htmlspecialchars_uni($banner['value']) . ";";
                break;
            case 'gradient':
                $banner_style = "background:" . htmlspecialchars_uni($banner['value']) . ";";
                break;
        }
        // Append custom text/link colors as CSS variables
        if (!empty($banner['text_color'])) {
            $banner_style .= "--banner-text-color:" . htmlspecialchars_uni($banner['text_color']) . ";";
        }
        if (!empty($banner['link_color'])) {
            $banner_style .= "--banner-link-color:" . htmlspecialchars_uni($banner['link_color']) . ";";
        }
    }
    $GLOBALS['banner_style'] = $banner_style;

    // ── Banner change overlay + modal (own profile only) ──
    if ($mybb->user['uid'] <= 0 || $mybb->user['uid'] != $uid) {
        $GLOBALS['banner_change_overlay'] = '';
        $GLOBALS['banner_change_modal']   = '';
    } else {

    // Overlay (inside .profile-banner)
    $GLOBALS['banner_change_overlay'] = '<div class="profile-banner-change" data-bs-toggle="modal" data-bs-target="#fmz_banner_modal"><i class="bi bi-image me-1"></i> Change Banner</div>';

    // ── Build previous banners gallery ──
    $galleryItems = '';
    $prevQuery = $db->simple_select('fmz_user_banners', '*', "uid=" . (int)$uid, array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 12));
    while ($row = $db->fetch_array($prevQuery)) {
        $active = $row['is_active'] ? ' active' : '';
        $bid = (int)$row['bid'];
        switch ($row['type']) {
            case 'upload':
                $thumbUrl = htmlspecialchars_uni($mybb->settings['bburl'] . '/' . $row['value']);
                $galleryItems .= '<div class="banner-gallery-item' . $active . '" data-bid="' . $bid . '" style="background-image:url(\'' . $thumbUrl . '\');background-size:cover;background-position:center"></div>';
                break;
            case 'solid':
                $color = htmlspecialchars_uni($row['value']);
                $galleryItems .= '<div class="banner-gallery-item' . $active . '" data-bid="' . $bid . '" style="background:' . $color . '"></div>';
                break;
            case 'gradient':
                $grad = htmlspecialchars_uni($row['value']);
                $galleryItems .= '<div class="banner-gallery-item' . $active . '" data-bid="' . $bid . '" style="background:' . $grad . '"></div>';
                break;
        }
    }
    if (empty($galleryItems)) {
        $galleryItems = '<div class="text-muted small text-center py-2">No previous banners yet.</div>';
    }

    $postKey = htmlspecialchars_uni($mybb->post_code);
    $bburl   = $mybb->settings['bburl'];

    // ── Gradient presets ──
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

    // ── Solid color presets ──
    $solidPresets = array('#0d9488','#0369a1','#4338ca','#7e22ce','#be123c','#b45309','#059669','#1e293b','#64748b','#dc2626','#d97706','#16a34a');
    $solidSwatches = '';
    foreach ($solidPresets as $c) {
        $ce = htmlspecialchars($c, ENT_QUOTES);
        $solidSwatches .= '<div class="solid-preset-swatch" data-color="' . $ce . '" style="background:' . $ce . '"></div>';
    }

    // ── Current text/link colors for pre-populating pickers ──
    $curTextColor = ($banner && !empty($banner['text_color'])) ? htmlspecialchars_uni($banner['text_color']) : '';
    $curLinkColor = ($banner && !empty($banner['link_color'])) ? htmlspecialchars_uni($banner['link_color']) : '';
    $curTextColorPicker = $curTextColor ?: '#1f2937';
    $curLinkColorPicker = $curLinkColor ?: '#0d9488';
    $previewTextStyle = $curTextColor ? 'color:' . $curTextColor . ';' : '';
    $previewLinkStyle = $curLinkColor ? 'color:' . $curLinkColor . ';' : '';

    $GLOBALS['banner_change_modal'] = <<<HTML
<div class="modal fade" id="fmz_banner_modal" tabindex="-1" aria-labelledby="fmz_banner_label" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg">
<div class="modal-content">
    <div class="modal-header">
        <h6 class="modal-title" id="fmz_banner_label"><i class="bi bi-image me-1"></i> Change Profile Banner</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
        <!-- Banner Preview -->
        <div id="fmz_banner_preview" class="banner-preview mb-3" style="{$banner_style}">
            <div class="banner-preview-text">
                <span id="fmz_preview_text" style="{$previewTextStyle}">Username</span>
                <a href="#" id="fmz_preview_link" onclick="return false" style="{$previewLinkStyle}">View Profile</a>
            </div>
            <span class="banner-preview-label">Preview</span>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs nav-fill mb-3" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#bannerUpload" role="tab"><i class="bi bi-upload me-1"></i> Upload</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#bannerGallery" role="tab"><i class="bi bi-collection me-1"></i> Previous</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#bannerSolid" role="tab"><i class="bi bi-palette me-1"></i> Solid</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#bannerGradient" role="tab"><i class="bi bi-rainbow me-1"></i> Gradient</a></li>
        </ul>

        <div class="tab-content">
            <!-- Upload Tab -->
            <div class="tab-pane fade show active" id="bannerUpload" role="tabpanel">
                <div class="mb-2">
                    <label class="form-label small fw-semibold"><i class="bi bi-cloud-upload me-1"></i> Upload Banner Image</label>
                    <input type="file" class="form-control form-control-sm" accept="image/*" id="fmz_banner_file" />
                    <div class="form-text">Recommended size: 1200 x 200 pixels. Max 2MB. JPG, PNG, GIF, WebP.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold"><i class="bi bi-link-45deg me-1"></i> Or Image URL</label>
                    <input type="text" class="form-control form-control-sm" id="fmz_banner_url" placeholder="https://example.com/banner.jpg" />
                </div>
            </div>

            <!-- Previous Banners Gallery -->
            <div class="tab-pane fade" id="bannerGallery" role="tabpanel">
                <div class="banner-gallery">{$galleryItems}</div>
            </div>

            <!-- Solid Color Tab -->
            <div class="tab-pane fade" id="bannerSolid" role="tabpanel">
                <div class="solid-presets mb-3">{$solidSwatches}</div>
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label small fw-semibold mb-0">Custom:</label>
                    <input type="color" class="form-control form-control-color" id="fmz_banner_color" value="#0d9488" style="width:40px;height:32px" />
                    <input type="text" class="form-control form-control-sm" id="fmz_banner_color_hex" value="#0d9488" style="width:100px" />
                </div>
            </div>

            <!-- Gradient Tab -->
            <div class="tab-pane fade" id="bannerGradient" role="tabpanel">
                <div class="gradient-presets mb-3">{$gradientSwatches}</div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Custom Gradient</label>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <select class="form-select form-select-sm" id="fmz_grad_type" style="width:auto">
                            <option value="linear">Linear</option>
                            <option value="radial">Radial</option>
                        </select>
                        <input type="number" class="form-control form-control-sm" id="fmz_grad_angle" value="135" min="0" max="360" style="width:70px" placeholder="Angle" />
                        <span class="small text-muted">&deg;</span>
                        <input type="color" class="form-control form-control-color" id="fmz_grad_color1" value="#667eea" style="width:36px;height:30px" />
                        <i class="bi bi-arrow-right"></i>
                        <input type="color" class="form-control form-control-color" id="fmz_grad_color2" value="#764ba2" style="width:36px;height:30px" />
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="fmz_grad_apply"><i class="bi bi-check-lg"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Text & Link Color Options -->
        <div class="mt-3 pt-3 border-top">
            <label class="form-label small fw-semibold mb-2"><i class="bi bi-fonts me-1"></i> Text &amp; Link Colors</label>
            <div class="d-flex gap-4 align-items-center flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted">Text:</span>
                    <input type="color" class="form-control form-control-color" id="fmz_banner_text_color" value="{$curTextColorPicker}" style="width:36px;height:30px" />
                    <input type="text" class="form-control form-control-sm" id="fmz_banner_text_color_hex" value="{$curTextColor}" placeholder="Default" style="width:90px" />
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted">Links:</span>
                    <input type="color" class="form-control form-control-color" id="fmz_banner_link_color" value="{$curLinkColorPicker}" style="width:36px;height:30px" />
                    <input type="text" class="form-control form-control-sm" id="fmz_banner_link_color_hex" value="{$curLinkColor}" placeholder="Default" style="width:90px" />
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="fmz_color_reset" title="Reset to defaults"><i class="bi bi-arrow-counterclockwise"></i></button>
            </div>
            <div class="form-text">Leave empty to use theme defaults.</div>
        </div>

        <!-- Hidden fields for submission -->
        <input type="hidden" id="fmz_banner_type" value="" />
        <input type="hidden" id="fmz_banner_value" value="" />
    </div>
    <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-sm btn-outline-danger" id="fmz_banner_remove"><i class="bi bi-trash me-1"></i> Remove Banner</button>
        <button type="button" class="btn btn-sm btn-primary" id="fmz_banner_save"><i class="bi bi-check-lg me-1"></i> Save Banner</button>
    </div>
</div>
</div>
</div>
HTML;
    } // end else (own profile banner modal)

    // ── Profile Status Block ──
    fmz_pe_inject_profile_status();

    // ── Latest Posts Block ──
    fmz_pe_inject_latest_posts();
}

/* ═══════════════════════════════════════════════════════════════
   PROFILE — Status update block (on member profile)
   ═══════════════════════════════════════════════════════════════ */

function fmz_pe_inject_profile_status()
{
    global $mybb, $db, $uid;

    $bburl   = $mybb->settings['bburl'];
    $postKey = htmlspecialchars_uni($mybb->post_code);

    // Fetch latest status for this profile user
    $profileUid = (int)$uid;

    // Determine privacy filter
    $privacyCond = "(s.privacy='public'";
    if ($mybb->user['uid'] == $profileUid) {
        $privacyCond .= " OR s.privacy='private' OR s.privacy='buddies'";
    } else {
        if ($mybb->user['uid'] > 0) {
            $buddyList = trim($mybb->user['buddylist']);
            if (!empty($buddyList)) {
                $buddyUids = array_map('intval', explode(',', $buddyList));
                if (in_array($profileUid, $buddyUids)) {
                    $privacyCond .= " OR s.privacy='buddies'";
                }
            }
        }
    }
    $privacyCond .= ")";

    $query = $db->query("
        SELECT s.*, u.username, u.usergroup, u.displaygroup, u.avatar
        FROM " . TABLE_PREFIX . "fmz_user_statuses s
        LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = s.uid
        WHERE s.uid={$profileUid} AND {$privacyCond}
        ORDER BY s.dateline DESC
        LIMIT 5
    ");

    $statusItems = '';
    while ($status = $db->fetch_array($query)) {
        $sAvatar     = !empty($status['avatar']) ? htmlspecialchars_uni($status['avatar']) : 'images/default_avatar.png';
        $sUsername    = htmlspecialchars_uni($status['username']);
        $sProfileUrl = htmlspecialchars_uni($bburl . '/member.php?action=profile&uid=' . (int)$status['uid']);
        $sFormattedName = format_name($status['username'], $status['usergroup'], $status['displaygroup']);
        $message     = fmz_pe_parse_message($status['message']);
        $timeAgo     = my_date('relative', $status['dateline']);

        $privacyIcon = '';
        if ($status['privacy'] === 'private') {
            $privacyIcon = '<i class="bi bi-lock-fill text-muted ms-1" title="Private"></i>';
        } elseif ($status['privacy'] === 'buddies') {
            $privacyIcon = '<i class="bi bi-people-fill text-muted ms-1" title="Buddies Only"></i>';
        }

        $deleteBtn = '';
        $editBtn = '';
        if ($mybb->user['uid'] == $status['uid'] || $mybb->usergroup['canmodcp']) {
            $sid = (int)$status['sid'];
            $rawMsg = htmlspecialchars($status['message'], ENT_QUOTES, 'UTF-8');
            $editBtn = '<a href="javascript:void(0)" class="fmz-status-edit text-muted small" data-sid="' . $sid . '" data-message="' . $rawMsg . '" title="Edit"><i class="bi bi-pencil"></i></a>';
            $deleteBtn = '<a href="javascript:void(0)" class="fmz-status-delete text-danger small" data-sid="' . $sid . '" title="Delete"><i class="bi bi-trash"></i></a>';
        }

        $commentsBlock = fmz_pe_render_comments_block($status['sid'], $status['uid']);

        $statusItems .= <<<ITEM
<div class="status-feed-item" data-sid="{$status['sid']}">
    <div class="status-feed-avatar">
        <a href="{$sProfileUrl}"><img src="{$sAvatar}" onerror="if(this.src!='images/default_avatar.png')this.src='images/default_avatar.png';" alt="{$sUsername}" /></a>
    </div>
    <div class="status-feed-body">
        <div class="status-feed-header">
            <a href="{$sProfileUrl}" class="status-feed-username">{$sFormattedName}</a>
            <span class="status-feed-time">{$timeAgo}{$privacyIcon}</span>
            <span class="ms-auto d-flex gap-2">{$editBtn}{$deleteBtn}</span>
        </div>
        <div class="status-feed-message">{$message}</div>
        {$commentsBlock}
    </div>
</div>
ITEM;
    }

    // Compose box (only for own profile or logged-in viewing own)
    $composeBox = '';
    if ($mybb->user['uid'] > 0 && $mybb->user['uid'] == $profileUid) {
        $curAvatar = htmlspecialchars_uni(!empty($mybb->user['avatar']) ? $mybb->user['avatar'] : 'images/default_avatar.png');
        $composeBox = <<<COMPOSE
<div class="status-compose-box mb-3">
    <div class="status-compose-avatar">
        <img src="{$curAvatar}" onerror="if(this.src!='images/default_avatar.png')this.src='images/default_avatar.png';" alt="You" />
    </div>
    <div class="status-compose-form">
        <textarea id="fmz_status_text" class="form-control form-control-sm fmz-wysiwyg" placeholder="What's on your mind?" rows="2"></textarea>
        <div class="status-compose-actions mt-2">
            <select id="fmz_status_privacy" class="form-select form-select-sm" style="width:auto">
                <option value="public">Public</option>
                <option value="buddies">Buddies Only</option>
                <option value="private">Private</option>
            </select>
            <button type="button" class="btn btn-sm btn-primary" id="fmz_status_submit"><i class="bi bi-send me-1"></i> Post</button>
        </div>
    </div>
</div>
COMPOSE;
    }

    if (empty($statusItems) && empty($composeBox)) {
        $statusItems = '<div class="text-center text-muted py-3"><i class="bi bi-chat-square-text d-block mb-1" style="font-size:1.5rem"></i><span class="small">No status updates yet.</span></div>';
    }

    $feedUrl = htmlspecialchars_uni($bburl . '/usercp.php?action=statusfeed&uid=' . $profileUid);
    $viewAllLink = '<a href="' . $feedUrl . '" class="small text-muted text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>';

    $GLOBALS['profile_status_block'] = <<<HTML
<div class="tborder profile-right-card mb-3">
    <div class="thead d-flex align-items-center justify-content-between">
        <strong><i class="bi bi-chat-square-text me-1"></i> Status Updates</strong>
        {$viewAllLink}
    </div>
    <div class="trow1" style="padding:1rem">
        {$composeBox}
        <div class="status-feed-list">{$statusItems}</div>
    </div>
</div>
HTML;
}

/* ═══════════════════════════════════════════════════════════════
   PROFILE — Latest posts block (on member profile)
   ═══════════════════════════════════════════════════════════════ */

function fmz_pe_inject_latest_posts()
{
    global $mybb, $db, $uid;

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

    $postItems = '';
    while ($post = $db->fetch_array($query)) {
        $threadSubject = htmlspecialchars_uni($post['thread_subject']);
        $forumName     = htmlspecialchars_uni($post['forum_name']);
        $threadUrl     = htmlspecialchars_uni($bburl . '/showthread.php?tid=' . (int)$post['thread_tid'] . '&pid=' . (int)$post['pid'] . '#pid' . (int)$post['pid']);
        $timeAgo       = my_date('relative', $post['dateline']);

        // Excerpt: strip BBCode/HTML, truncate
        $excerpt = strip_tags(str_replace(array('[/quote]','[/code]'), '', $post['message']));
        $excerpt = preg_replace('/\[.*?\]/', '', $excerpt);
        $excerpt = trim($excerpt);
        if (my_strlen($excerpt) > 120) {
            $excerpt = my_substr($excerpt, 0, 120) . '...';
        }
        $excerpt = htmlspecialchars_uni($excerpt);

        $postItems .= <<<POST
<div class="profile-latest-post">
    <div class="profile-latest-post-header">
        <a href="{$threadUrl}" class="profile-latest-post-title">{$threadSubject}</a>
        <span class="profile-latest-post-meta text-muted small">in {$forumName} &middot; {$timeAgo}</span>
    </div>
    <div class="profile-latest-post-excerpt text-muted small">{$excerpt}</div>
</div>
POST;
    }

    if (empty($postItems)) {
        $postItems = '<div class="text-center text-muted py-3"><i class="bi bi-file-text d-block mb-1" style="font-size:1.5rem"></i><span class="small">No posts yet.</span></div>';
    }

    $findPostsUrl = htmlspecialchars_uni($bburl . '/search.php?action=finduser&uid=' . $profileUid);
    $viewAllLink = '<a href="' . $findPostsUrl . '" class="small text-muted text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>';

    $GLOBALS['profile_latest_posts'] = <<<HTML
<div class="tborder profile-right-card mb-3">
    <div class="thead d-flex align-items-center justify-content-between">
        <strong><i class="bi bi-file-text me-1"></i> Latest Posts</strong>
        {$viewAllLink}
    </div>
    <div class="trow1" style="padding:0">
        {$postItems}
    </div>
</div>
HTML;
}

/* ═══════════════════════════════════════════════════════════════
   USERCP HANDLER — statusfeed page + AJAX actions
   ═══════════════════════════════════════════════════════════════ */

function fmz_profile_extras_usercp()
{
    global $mybb, $db;

    // ── AJAX: handle fmz_action requests first ──
    $fmzAction = $mybb->get_input('fmz_action');
    if (!empty($fmzAction)) {
        fmz_profile_extras_ajax();
        return;
    }

    // ── Page: statusfeed ──
    if ($mybb->get_input('action') === 'statusfeed') {
        fmz_profile_extras_statusfeed();
        return;
    }
}

/* ═══════════════════════════════════════════════════════════════
   STATUS FEED PAGE — usercp.php?action=statusfeed
   ═══════════════════════════════════════════════════════════════ */

function fmz_profile_extras_statusfeed()
{
    global $mybb, $db, $lang, $templates, $header, $headerinclude, $footer, $theme, $usercpnav;

    if ($mybb->user['uid'] <= 0) {
        error_no_permission();
    }

    $bburl = $mybb->settings['bburl'];
    $bbname = htmlspecialchars_uni($mybb->settings['bbname']);
    $uid = (int)$mybb->user['uid'];

    // ── Load user banner for profile hero ──
    $bannerStyle = '';
    $bannerQuery = $db->simple_select('fmz_user_banners', '*', "uid={$uid} AND is_active=1", array('limit' => 1));
    $bannerRow = $db->fetch_array($bannerQuery);
    if ($bannerRow) {
        switch ($bannerRow['type']) {
            case 'upload':
                $imgUrl = htmlspecialchars_uni($bburl . '/' . $bannerRow['value']);
                $bannerStyle = "background-image:url('{$imgUrl}');background-size:cover;background-position:center;";
                break;
            case 'solid':
                $bannerStyle = "background:" . htmlspecialchars_uni($bannerRow['value']) . ";";
                break;
            case 'gradient':
                $bannerStyle = "background:" . htmlspecialchars_uni($bannerRow['value']) . ";";
                break;
        }
        if (!empty($bannerRow['text_color'])) {
            $bannerStyle .= "--banner-text-color:" . htmlspecialchars_uni($bannerRow['text_color']) . ";";
        }
        if (!empty($bannerRow['link_color'])) {
            $bannerStyle .= "--banner-link-color:" . htmlspecialchars_uni($bannerRow['link_color']) . ";";
        }
    }

    // ── User info for hero ──
    $userAvatar = !empty($mybb->user['avatar']) ? htmlspecialchars_uni($mybb->user['avatar']) : 'images/default_avatar.png';
    $username = htmlspecialchars_uni($mybb->user['username']);
    $formattedName = format_name($mybb->user['username'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
    $profileUrl = htmlspecialchars_uni($bburl . '/member.php?action=profile&uid=' . $uid);
    $postCount = my_number_format($mybb->user['postnum']);

    // ── Filters ──
    $filterUid = $mybb->get_input('uid', MyBB::INPUT_INT);
    $page      = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $perPage   = 20;
    $start     = ($page - 1) * $perPage;

    // Build WHERE clause
    $where = array();
    if ($filterUid > 0) {
        $where[] = "s.uid=" . (int)$filterUid;
    }

    // Privacy: show public statuses, own private, buddy statuses if buddies
    $buddyList = !empty($mybb->user['buddylist']) ? $mybb->user['buddylist'] : '';
    $privacyCond = "(s.privacy='public'";
    $privacyCond .= " OR s.uid={$uid}";
    if (!empty($buddyList)) {
        $privacyCond .= " OR (s.privacy='buddies' AND s.uid IN ({$buddyList}))";
    }
    $privacyCond .= ")";
    $where[] = $privacyCond;

    $whereStr = implode(' AND ', $where);

    // Total count
    $countQuery = $db->query("SELECT COUNT(*) AS cnt FROM " . TABLE_PREFIX . "fmz_user_statuses s WHERE {$whereStr}");
    $total = (int)$db->fetch_field($countQuery, 'cnt');
    $totalPages = max(1, ceil($total / $perPage));

    // Fetch statuses
    $query = $db->query("
        SELECT s.*, u.username, u.usergroup, u.displaygroup, u.avatar, u.avatardimensions
        FROM " . TABLE_PREFIX . "fmz_user_statuses s
        LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = s.uid
        WHERE {$whereStr}
        ORDER BY s.dateline DESC
        LIMIT {$start}, {$perPage}
    ");

    $statusItems = '';
    while ($status = $db->fetch_array($query)) {
        $sAvatar = !empty($status['avatar']) ? htmlspecialchars_uni($status['avatar']) : 'images/default_avatar.png';
        $sUsername   = htmlspecialchars_uni($status['username']);
        $sProfileUrl = htmlspecialchars_uni($bburl . '/member.php?action=profile&uid=' . (int)$status['uid']);
        $sFormattedName = format_name($status['username'], $status['usergroup'], $status['displaygroup']);
        $message    = fmz_pe_parse_message($status['message']);
        $timeAgo    = my_date('relative', $status['dateline']);
        $privacyIcon = '';
        if ($status['privacy'] === 'private') {
            $privacyIcon = '<i class="bi bi-lock-fill text-muted ms-1" title="Private"></i>';
        } elseif ($status['privacy'] === 'buddies') {
            $privacyIcon = '<i class="bi bi-people-fill text-muted ms-1" title="Buddies Only"></i>';
        }

        $deleteBtn = '';
        $editBtn = '';
        if ($mybb->user['uid'] == $status['uid'] || $mybb->usergroup['canmodcp']) {
            $sid = (int)$status['sid'];
            $rawMsg = htmlspecialchars($status['message'], ENT_QUOTES, 'UTF-8');
            $editBtn = '<a href="javascript:void(0)" class="fmz-status-edit text-muted small" data-sid="' . $sid . '" data-message="' . $rawMsg . '" title="Edit"><i class="bi bi-pencil"></i></a>';
            $deleteBtn = '<a href="javascript:void(0)" class="fmz-status-delete text-danger small" data-sid="' . $sid . '"><i class="bi bi-trash"></i></a>';
        }

        // Comments block
        $commentsBlock = fmz_pe_render_comments_block($status['sid'], $status['uid']);

        $statusItems .= <<<ITEM
<div class="status-feed-item" data-sid="{$status['sid']}">
    <div class="status-feed-avatar">
        <a href="{$sProfileUrl}"><img src="{$sAvatar}" onerror="if(this.src!='images/default_avatar.png')this.src='images/default_avatar.png';" alt="{$sUsername}" /></a>
    </div>
    <div class="status-feed-body">
        <div class="status-feed-header">
            <a href="{$sProfileUrl}" class="status-feed-username">{$sFormattedName}</a>
            <span class="status-feed-time">{$timeAgo}{$privacyIcon}</span>
            <span class="ms-auto d-flex gap-2">{$editBtn}{$deleteBtn}</span>
        </div>
        <div class="status-feed-message">{$message}</div>
        {$commentsBlock}
    </div>
</div>
ITEM;
    }

    if (empty($statusItems)) {
        $statusItems = '<div class="text-center text-muted py-4"><i class="bi bi-chat-square-text fs-1 d-block mb-2"></i>No status updates yet.</div>';
    }

    // ── Status compose box ──
    $postKey = htmlspecialchars_uni($mybb->post_code);
    $curAvatar = htmlspecialchars_uni(!empty($mybb->user['avatar']) ? $mybb->user['avatar'] : 'images/default_avatar.png');
    $composeBox = <<<COMPOSE
<div class="status-compose-box mb-3">
    <div class="status-compose-avatar">
        <img src="{$curAvatar}" onerror="if(this.src!='images/default_avatar.png')this.src='images/default_avatar.png';" alt="You" />
    </div>
    <div class="status-compose-form">
        <textarea id="fmz_status_text" class="form-control form-control-sm fmz-wysiwyg" placeholder="What's on your mind?" rows="2"></textarea>
        <div class="status-compose-actions mt-2">
            <select id="fmz_status_privacy" class="form-select form-select-sm" style="width:auto">
                <option value="public"><i class="bi bi-globe"></i> Public</option>
                <option value="buddies">Buddies Only</option>
                <option value="private">Private</option>
            </select>
            <button type="button" class="btn btn-sm btn-primary" id="fmz_status_submit"><i class="bi bi-send me-1"></i> Post</button>
        </div>
    </div>
</div>
COMPOSE;

    // ── Pagination ──
    $pagination = '';
    if ($totalPages > 1) {
        $pagination = '<nav class="status-feed-pagination mt-3"><ul class="pagination pagination-sm justify-content-center">';
        $baseUrl = $bburl . '/usercp.php?action=statusfeed' . ($filterUid ? '&uid=' . $filterUid : '');
        for ($p = 1; $p <= $totalPages; $p++) {
            $activeClass = ($p == $page) ? ' active' : '';
            $pagination .= '<li class="page-item' . $activeClass . '"><a class="page-link" href="' . $baseUrl . '&page=' . $p . '">' . $p . '</a></li>';
        }
        $pagination .= '</ul></nav>';
    }

    // ── Filter sidebar (inside content area) ──
    $filterActive = $filterUid > 0 ? '' : ' active';
    $filterMine   = ($filterUid == $uid && $uid > 0) ? ' active' : '';
    $filterLinks  = '<div class="status-feed-filters mb-3">';
    $filterLinks .= '<div class="btn-group btn-group-sm" role="group">';
    $filterLinks .= '<a href="' . $bburl . '/usercp.php?action=statusfeed" class="btn btn-outline-secondary text-light' . ($filterActive ? ' active' : '') . '"><i class="bi bi-globe me-1"></i>All Updates</a>';
    $filterLinks .= '<a href="' . $bburl . '/usercp.php?action=statusfeed&uid=' . $uid . '" class="btn btn-outline-secondary text-light' . ($filterMine ? ' active' : '') . '"><i class="bi bi-person me-1"></i>My Updates</a>';
    $filterLinks .= '</div></div>';

    // ── Build page ──
    add_breadcrumb('User CP', 'usercp.php');
    add_breadcrumb($lang->fmz_statusfeed_title);

    $content = <<<PAGE
<html>
<head>
<title>{$bbname} - {$lang->fmz_statusfeed_title}</title>
{$headerinclude}
<link rel="stylesheet" href="{$bburl}/themes/fmz-default/functions/plugins/fmz-profile-extras/css/profile-extras.css" />
</head>
<body>
{$header}

<!-- Profile Hero -->
<div class="profile-hero mb-3">
  <div class="profile-banner trow1" style="{$bannerStyle}">
    <div class="profile-banner-left">
      <span class="profile-username largetext"><strong>{$formattedName}</strong></span>
    </div>
    <div class="profile-banner-right">
      <div class="profile-meta smalltext">
        <span>{$postCount} posts</span>
      </div>
    </div>
  </div>
  <div class="profile-hero-body">
    <div class="profile-avatar-wrap scaleimages"><img src="{$userAvatar}" onerror="if(this.src!='images/default_avatar.png')this.src='images/default_avatar.png';" alt="{$username}" /></div>
    <div class="profile-actions">
      <a href="{$profileUrl}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person me-1"></i>View Profile</a>
    </div>
  </div>
</div>

{$usercpnav}

<div class="col-sm-9">
    <div class="tborder">
        <div class="thead"><strong><i class="bi bi-chat-square-text me-1"></i> {$lang->fmz_statusfeed_heading}</strong></div>
        <div class="trow1" style="padding:1rem">
            {$filterLinks}
            {$composeBox}
            <div class="status-feed-list">{$statusItems}</div>
            {$pagination}
        </div>
    </div>
</div>

</div>
{$footer}
<script src="{$bburl}/themes/fmz-default/functions/plugins/fmz-profile-extras/js/profile-extras.js"></script>
</body>
</html>
PAGE;

    output_page($content);
    exit;
}

/* ═══════════════════════════════════════════════════════════════
   AJAX HANDLER — usercp_start hook (fmz_action parameter)
   ═══════════════════════════════════════════════════════════════ */

function fmz_profile_extras_ajax()
{
    global $mybb, $db;

    $action = $mybb->get_input('fmz_action');
    if (empty($action)) return;

    // Must be logged in
    if ($mybb->user['uid'] <= 0) {
        fmz_profile_extras_json(array('error' => 'Not logged in.'), 403);
        return;
    }

    // Verify post key for all write actions (default-deny: only skip for explicitly read-only actions)
    $readOnlyActions = array('get_statuses', 'get_comments', 'get_banner');
    if (!in_array($action, $readOnlyActions)) {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            fmz_profile_extras_json(array('error' => 'Invalid security token.'), 403);
            return;
        }
    }

    switch ($action) {
        case 'save_banner':
            fmz_pe_save_banner();
            break;
        case 'remove_banner':
            fmz_pe_remove_banner();
            break;
        case 'activate_banner':
            fmz_pe_activate_banner();
            break;
        case 'update_banner_colors':
            fmz_pe_update_banner_colors();
            break;
        case 'post_status':
            fmz_pe_post_status();
            break;
        case 'edit_status':
            fmz_pe_edit_status();
            break;
        case 'delete_status':
            fmz_pe_delete_status();
            break;
        case 'post_comment':
            fmz_pe_post_comment();
            break;
        case 'delete_comment':
            fmz_pe_delete_comment();
            break;
    }
}

/* ── Banner: Save ── */
function fmz_pe_save_banner()
{
    global $mybb, $db;

    $uid  = (int)$mybb->user['uid'];
    $type = $mybb->get_input('banner_type');
    if (!in_array($type, array('upload', 'solid', 'gradient'))) {
        fmz_profile_extras_json(array('error' => 'Invalid banner type.'));
        return;
    }

    $value = '';
    if ($type === 'upload') {
        // Handle file upload
        if (!empty($_FILES['banner_file']['name'])) {
            $uploadDir = MYBB_ROOT . 'uploads/banners/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
                // Prevent PHP execution in upload directory
                $htaccess = $uploadDir . '.htaccess';
                if (!file_exists($htaccess)) {
                    @file_put_contents($htaccess, "<FilesMatch \"\\.php$\">\nOrder Allow,Deny\nDeny from all\n</FilesMatch>\n");
                }
            }

            $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');
            $ext = strtolower(pathinfo($_FILES['banner_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                fmz_profile_extras_json(array('error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed)));
                return;
            }

            // Validate MIME type
            $allowedMimes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detectedMime = finfo_file($finfo, $_FILES['banner_file']['tmp_name']);
                finfo_close($finfo);
                if ($detectedMime === false || !in_array($detectedMime, $allowedMimes)) {
                    fmz_profile_extras_json(array('error' => 'File content does not match an allowed image type.'));
                    return;
                }
            }

            // Max 2MB
            if ($_FILES['banner_file']['size'] > 2 * 1024 * 1024) {
                fmz_profile_extras_json(array('error' => 'File too large. Max 2MB.'));
                return;
            }

            $filename = 'banner_' . $uid . '_' . TIME_NOW . '.' . $ext;
            $dest = $uploadDir . $filename;

            // Validate resolved path stays within uploads directory
            $realUploadDir = realpath($uploadDir);
            $realBase = realpath(MYBB_ROOT . 'uploads');
            if ($realUploadDir === false || $realBase === false || strpos($realUploadDir, $realBase) !== 0) {
                fmz_profile_extras_json(array('error' => 'Invalid upload directory.'));
                return;
            }

            if (!move_uploaded_file($_FILES['banner_file']['tmp_name'], $dest)) {
                fmz_profile_extras_json(array('error' => 'Upload failed.'));
                return;
            }
            $value = 'uploads/banners/' . $filename;
        } elseif ($mybb->get_input('banner_url')) {
            // URL-based upload
            $url = trim($mybb->get_input('banner_url'));
            if (!my_validate_url($url)) {
                fmz_profile_extras_json(array('error' => 'Invalid URL.'));
                return;
            }
            $value = $url;
        } else {
            fmz_profile_extras_json(array('error' => 'No file or URL provided.'));
            return;
        }
    } elseif ($type === 'solid') {
        $value = $mybb->get_input('banner_value');
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
            fmz_profile_extras_json(array('error' => 'Invalid color value.'));
            return;
        }
    } elseif ($type === 'gradient') {
        $value = $mybb->get_input('banner_value');
        // Basic sanity check for gradient CSS
        if (strpos($value, 'gradient') === false) {
            fmz_profile_extras_json(array('error' => 'Invalid gradient value.'));
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
    $db->update_query('fmz_user_banners', array('is_active' => 0), "uid={$uid}");

    // Insert new banner
    $db->insert_query('fmz_user_banners', array(
        'uid'        => $uid,
        'type'       => $db->escape_string($type),
        'value'      => $db->escape_string($value),
        'text_color' => $db->escape_string($textColor),
        'link_color' => $db->escape_string($linkColor),
        'is_active'  => 1,
        'dateline'   => TIME_NOW,
    ));

    // Build CSS for response
    $css = '';
    switch ($type) {
        case 'upload':
            $imgUrl = $mybb->settings['bburl'] . '/' . $value;
            $css = "background-image:url('{$imgUrl}');background-size:cover;background-position:center;";
            break;
        case 'solid':
            $css = "background:{$value};";
            break;
        case 'gradient':
            $css = "background:{$value};";
            break;
    }

    fmz_profile_extras_json(array('success' => true, 'css' => $css, 'text_color' => $textColor, 'link_color' => $linkColor));
}

/* ── Banner: Update Colors Only ── */
function fmz_pe_update_banner_colors()
{
    global $mybb, $db;
    $uid = (int)$mybb->user['uid'];

    // Find active banner
    $query = $db->simple_select('fmz_user_banners', '*', "uid={$uid} AND is_active=1", array('limit' => 1));
    $banner = $db->fetch_array($query);
    if (!$banner) {
        fmz_profile_extras_json(array('error' => 'No active banner to update.'));
        return;
    }

    $textColor = trim($mybb->get_input('text_color'));
    $linkColor = trim($mybb->get_input('link_color'));
    if ($textColor !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $textColor)) $textColor = '';
    if ($linkColor !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $linkColor)) $linkColor = '';

    $db->update_query('fmz_user_banners', array(
        'text_color' => $db->escape_string($textColor),
        'link_color' => $db->escape_string($linkColor),
    ), "bid=" . (int)$banner['bid']);

    // Rebuild CSS from existing banner
    $css = '';
    switch ($banner['type']) {
        case 'upload':
            $imgUrl = $mybb->settings['bburl'] . '/' . $banner['value'];
            $css = "background-image:url('{$imgUrl}');background-size:cover;background-position:center;";
            break;
        case 'solid':
            $css = "background:{$banner['value']};";
            break;
        case 'gradient':
            $css = "background:{$banner['value']};";
            break;
    }

    fmz_profile_extras_json(array('success' => true, 'css' => $css, 'text_color' => $textColor, 'link_color' => $linkColor));
}

/* ── Banner: Remove ── */
function fmz_pe_remove_banner()
{
    global $mybb, $db;
    $uid = (int)$mybb->user['uid'];
    $db->update_query('fmz_user_banners', array('is_active' => 0), "uid={$uid}");
    fmz_profile_extras_json(array('success' => true, 'css' => ''));
}

/* ── Banner: Activate previous ── */
function fmz_pe_activate_banner()
{
    global $mybb, $db;
    $uid = (int)$mybb->user['uid'];
    $bid = $mybb->get_input('bid', MyBB::INPUT_INT);

    // Verify ownership
    $query = $db->simple_select('fmz_user_banners', '*', "bid={$bid} AND uid={$uid}", array('limit' => 1));
    $banner = $db->fetch_array($query);
    if (!$banner) {
        fmz_profile_extras_json(array('error' => 'Banner not found.'));
        return;
    }

    // Deactivate all, activate this one
    $db->update_query('fmz_user_banners', array('is_active' => 0), "uid={$uid}");
    $db->update_query('fmz_user_banners', array('is_active' => 1), "bid={$bid}");

    // Update text/link colors if provided
    $textColor = trim($mybb->get_input('text_color'));
    $linkColor = trim($mybb->get_input('link_color'));
    if ($textColor !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $textColor)) $textColor = '';
    if ($linkColor !== '' && !preg_match('/^#[0-9a-fA-F]{3,8}$/', $linkColor)) $linkColor = '';
    $db->update_query('fmz_user_banners', array(
        'text_color' => $db->escape_string($textColor),
        'link_color' => $db->escape_string($linkColor),
    ), "bid={$bid}");

    $css = '';
    switch ($banner['type']) {
        case 'upload':
            $imgUrl = $mybb->settings['bburl'] . '/' . $banner['value'];
            $css = "background-image:url('{$imgUrl}');background-size:cover;background-position:center;";
            break;
        case 'solid':
            $css = "background:{$banner['value']};";
            break;
        case 'gradient':
            $css = "background:{$banner['value']};";
            break;
    }

    fmz_profile_extras_json(array('success' => true, 'css' => $css, 'text_color' => $textColor, 'link_color' => $linkColor));
}

/* ── Status: Post ── */
function fmz_pe_post_status()
{
    global $mybb, $db;
    $uid = (int)$mybb->user['uid'];

    $message = trim($mybb->get_input('message'));
    if (empty($message)) {
        fmz_profile_extras_json(array('error' => 'Status message cannot be empty.'));
        return;
    }
    if (my_strlen($message) > 1000) {
        fmz_profile_extras_json(array('error' => 'Status message too long. Max 1000 characters.'));
        return;
    }

    $privacy = $mybb->get_input('privacy');
    if (!in_array($privacy, array('public', 'private', 'buddies'))) {
        $privacy = 'public';
    }

    $db->insert_query('fmz_user_statuses', array(
        'uid'      => $uid,
        'message'  => $db->escape_string($message),
        'privacy'  => $db->escape_string($privacy),
        'dateline' => TIME_NOW,
    ));

    $sid = $db->insert_id();

    // Return rendered status item
    $userAvatar = !empty($mybb->user['avatar']) ? htmlspecialchars_uni($mybb->user['avatar']) : 'images/default_avatar.png';
    $username   = htmlspecialchars_uni($mybb->user['username']);
    $profileUrl = htmlspecialchars_uni($mybb->settings['bburl'] . '/member.php?action=profile&uid=' . $uid);
    $formattedName = format_name($mybb->user['username'], $mybb->user['usergroup'], $mybb->user['displaygroup']);
    $msgHtml = fmz_pe_parse_message($message);
    $privacyIcon = '';
    if ($privacy === 'private') {
        $privacyIcon = '<i class="bi bi-lock-fill text-muted ms-1" title="Private"></i>';
    } elseif ($privacy === 'buddies') {
        $privacyIcon = '<i class="bi bi-people-fill text-muted ms-1" title="Buddies Only"></i>';
    }

    $commentsBlock = fmz_pe_render_comments_block($sid, $uid);

    $html = <<<ITEM
<div class="status-feed-item" data-sid="{$sid}">
    <div class="status-feed-avatar">
        <a href="{$profileUrl}"><img src="{$userAvatar}" onerror="if(this.src!='images/default_avatar.png')this.src='images/default_avatar.png';" alt="{$username}" /></a>
    </div>
    <div class="status-feed-body">
        <div class="status-feed-header">
            <a href="{$profileUrl}" class="status-feed-username">{$formattedName}</a>
            <span class="status-feed-time">Just now{$privacyIcon}</span>
            <a href="javascript:void(0)" class="fmz-status-delete text-danger small" data-sid="{$sid}"><i class="bi bi-trash"></i></a>
        </div>
        <div class="status-feed-message">{$msgHtml}</div>
        {$commentsBlock}
    </div>
</div>
ITEM;

    fmz_profile_extras_json(array('success' => true, 'html' => $html));
}

/* ── Status: Delete ── */
function fmz_pe_delete_status()
{
    global $mybb, $db;
    $uid = (int)$mybb->user['uid'];
    $sid = $mybb->get_input('sid', MyBB::INPUT_INT);

    // Verify ownership or mod
    $query = $db->simple_select('fmz_user_statuses', 'uid', "sid={$sid}", array('limit' => 1));
    $statusUid = (int)$db->fetch_field($query, 'uid');
    if ($statusUid !== $uid && !$mybb->usergroup['canmodcp']) {
        fmz_profile_extras_json(array('error' => 'Permission denied.'));
        return;
    }

    $db->delete_query('fmz_user_statuses', "sid={$sid}");
    fmz_profile_extras_json(array('success' => true));
}

/* ── Status: Edit ── */
function fmz_pe_edit_status()
{
    global $mybb, $db;
    $uid = (int)$mybb->user['uid'];
    $sid = $mybb->get_input('sid', MyBB::INPUT_INT);
    $message = trim($mybb->get_input('message'));

    if (empty($message)) {
        fmz_profile_extras_json(array('error' => 'Message cannot be empty.'));
        return;
    }
    if (my_strlen($message) > 5000) {
        fmz_profile_extras_json(array('error' => 'Message is too long.'));
        return;
    }

    // Verify ownership or mod
    $query = $db->simple_select('fmz_user_statuses', 'uid', "sid={$sid}", array('limit' => 1));
    $statusUid = (int)$db->fetch_field($query, 'uid');
    if ($statusUid !== $uid && !$mybb->usergroup['canmodcp']) {
        fmz_profile_extras_json(array('error' => 'Permission denied.'));
        return;
    }

    $db->update_query('fmz_user_statuses', array('message' => $db->escape_string($message)), "sid={$sid}");

    $parsed = fmz_pe_parse_message($message);
    fmz_profile_extras_json(array('success' => true, 'html' => $parsed));
}

/* ── Comment: Post ── */
function fmz_pe_post_comment()
{
    global $mybb, $db;
    $uid = (int)$mybb->user['uid'];
    $sid = $mybb->get_input('sid', MyBB::INPUT_INT);

    // Verify status exists and user can see it
    $query = $db->query("
        SELECT s.*, u.buddylist
        FROM " . TABLE_PREFIX . "fmz_user_statuses s
        LEFT JOIN " . TABLE_PREFIX . "users u ON u.uid = s.uid
        WHERE s.sid={$sid}
        LIMIT 1
    ");
    $status = $db->fetch_array($query);
    if (!$status) {
        fmz_profile_extras_json(array('error' => 'Status not found.'));
        return;
    }

    // Privacy check: can this user comment on this status?
    $statusUid = (int)$status['uid'];
    if ($status['privacy'] === 'private' && $statusUid !== $uid && !$mybb->usergroup['canmodcp']) {
        fmz_profile_extras_json(array('error' => 'You cannot comment on this status.'));
        return;
    }
    if ($status['privacy'] === 'buddies' && $statusUid !== $uid && !$mybb->usergroup['canmodcp']) {
        $buddyList = !empty($status['buddylist']) ? explode(',', $status['buddylist']) : array();
        if (!in_array((string)$uid, $buddyList)) {
            fmz_profile_extras_json(array('error' => 'You cannot comment on this status.'));
            return;
        }
    }

    $message = trim($mybb->get_input('message'));
    if (empty($message)) {
        fmz_profile_extras_json(array('error' => 'Comment cannot be empty.'));
        return;
    }
    if (my_strlen($message) > 500) {
        fmz_profile_extras_json(array('error' => 'Comment too long. Max 500 characters.'));
        return;
    }

    $db->insert_query('fmz_status_comments', array(
        'sid'      => $sid,
        'uid'      => $uid,
        'message'  => $db->escape_string($message),
        'dateline' => TIME_NOW,
    ));

    $cid = $db->insert_id();

    // Build rendered comment
    $comment = array(
        'cid'          => $cid,
        'sid'          => $sid,
        'uid'          => $uid,
        'message'      => $message,
        'dateline'     => TIME_NOW,
        'username'     => $mybb->user['username'],
        'usergroup'    => $mybb->user['usergroup'],
        'displaygroup' => $mybb->user['displaygroup'],
        'avatar'       => $mybb->user['avatar'],
    );

    $html = fmz_pe_render_comment($comment);
    fmz_profile_extras_json(array('success' => true, 'html' => $html, 'cid' => $cid));
}

/* ── Comment: Delete ── */
function fmz_pe_delete_comment()
{
    global $mybb, $db;
    $uid = (int)$mybb->user['uid'];
    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);

    // Verify ownership or mod
    $query = $db->simple_select('fmz_status_comments', 'uid, sid', "cid={$cid}", array('limit' => 1));
    $comment = $db->fetch_array($query);
    if (!$comment) {
        fmz_profile_extras_json(array('error' => 'Comment not found.'));
        return;
    }

    $commentUid = (int)$comment['uid'];
    // Also allow status owner to delete comments on their status
    $statusQuery = $db->simple_select('fmz_user_statuses', 'uid', "sid=" . (int)$comment['sid'], array('limit' => 1));
    $statusOwnerUid = (int)$db->fetch_field($statusQuery, 'uid');

    if ($commentUid !== $uid && $statusOwnerUid !== $uid && !$mybb->usergroup['canmodcp']) {
        fmz_profile_extras_json(array('error' => 'Permission denied.'));
        return;
    }

    $db->delete_query('fmz_status_comments', "cid={$cid}");
    fmz_profile_extras_json(array('success' => true));
}

/* ── JSON response helper ── */
function fmz_profile_extras_json($data, $code = 200)
{
    if ($code === 403) {
        header('HTTP/1.1 403 Forbidden');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
