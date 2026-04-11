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

// â”€â”€ Store options in globals for use in functions â”€â”€
$GLOBALS['ms_pe_options'] = isset($ms_plugin_options) ? $ms_plugin_options : array();

// â”€â”€ Auto-install: create tables if missing â”€â”€
ms_profile_extras_install();

// â”€â”€ Hooks â”€â”€
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


/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   USERCP HANDLER â€” banner AJAX actions
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

function ms_profile_extras_usercp()
{
    global $mybb;

    // â”€â”€ AJAX: handle ms_action requests (banner operations) â”€â”€
    $msAction = $mybb->get_input('ms_action');
    if (!empty($msAction)) {
        ms_profile_extras_ajax();
        return;
    }
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   BANNER â€” Inject style + change modal on member profile
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

function ms_profile_extras_banner()
{
    global $mybb, $db, $lang, $memprofile, $uid, $templates;

    // â”€â”€ Load active banner for this profile user â”€â”€
    $banner_style = '';
    $query = $db->simple_select('ms_user_banners', '*', "uid=" . (int)$uid . " AND is_active=1", array('limit' => 1));
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

    // â”€â”€ Banner change overlay + modal (own profile only) â”€â”€
    if ($mybb->user['uid'] <= 0 || $mybb->user['uid'] != $uid) {
        $GLOBALS['banner_change_overlay'] = '';
        $GLOBALS['banner_change_modal']   = '';
    } else {

    // Overlay (inside .profile-banner)
    $GLOBALS['banner_change_overlay'] = '<div class="profile-banner-change" data-bs-toggle="modal" data-bs-target="#ms_banner_modal"><i class="bi bi-image me-1"></i> Change Banner</div>';

    // â”€â”€ Build previous banners gallery â”€â”€
    $galleryItems = '';
    $prevQuery = $db->simple_select('ms_user_banners', '*', "uid=" . (int)$uid, array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 12));
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

    $GLOBALS['banner_change_modal'] = <<<HTML
<div class="modal fade" id="ms_banner_modal" tabindex="-1" aria-labelledby="ms_banner_label" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg">
<div class="modal-content">
    <div class="modal-header">
        <h6 class="modal-title" id="ms_banner_label"><i class="bi bi-image me-1"></i> Change Profile Banner</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
        <!-- Banner Preview -->
        <div id="ms_banner_preview" class="banner-preview mb-3" style="{$banner_style}">
            <div class="banner-preview-text">
                <span id="ms_preview_text" style="{$previewTextStyle}">Username</span>
                <a href="#" id="ms_preview_link" onclick="return false" style="{$previewLinkStyle}">View Profile</a>
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
                    <input type="file" class="form-control form-control-sm" accept="image/*" id="ms_banner_file" />
                    <div class="form-text">Recommended size: 1200 x 200 pixels. Max 2MB. JPG, PNG, GIF, WebP.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold"><i class="bi bi-link-45deg me-1"></i> Or Image URL</label>
                    <input type="text" class="form-control form-control-sm" id="ms_banner_url" placeholder="https://example.com/banner.jpg" />
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
                    <input type="color" class="form-control form-control-color" id="ms_banner_color" value="#0d9488" style="width:40px;height:32px" />
                    <input type="text" class="form-control form-control-sm" id="ms_banner_color_hex" value="#0d9488" style="width:100px" />
                </div>
            </div>

            <!-- Gradient Tab -->
            <div class="tab-pane fade" id="bannerGradient" role="tabpanel">
                <div class="gradient-presets mb-3">{$gradientSwatches}</div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Custom Gradient</label>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <select class="form-select form-select-sm" id="ms_grad_type" style="width:auto">
                            <option value="linear">Linear</option>
                            <option value="radial">Radial</option>
                        </select>
                        <input type="number" class="form-control form-control-sm" id="ms_grad_angle" value="135" min="0" max="360" style="width:70px" placeholder="Angle" />
                        <span class="small text-muted">&deg;</span>
                        <input type="color" class="form-control form-control-color" id="ms_grad_color1" value="#667eea" style="width:36px;height:30px" />
                        <i class="bi bi-arrow-right"></i>
                        <input type="color" class="form-control form-control-color" id="ms_grad_color2" value="#764ba2" style="width:36px;height:30px" />
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ms_grad_apply"><i class="bi bi-check-lg"></i></button>
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
                    <input type="color" class="form-control form-control-color" id="ms_banner_text_color" value="{$curTextColorPicker}" style="width:36px;height:30px" />
                    <input type="text" class="form-control form-control-sm" id="ms_banner_text_color_hex" value="{$curTextColor}" placeholder="Default" style="width:90px" />
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted">Links:</span>
                    <input type="color" class="form-control form-control-color" id="ms_banner_link_color" value="{$curLinkColorPicker}" style="width:36px;height:30px" />
                    <input type="text" class="form-control form-control-sm" id="ms_banner_link_color_hex" value="{$curLinkColor}" placeholder="Default" style="width:90px" />
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="ms_color_reset" title="Reset to defaults"><i class="bi bi-arrow-counterclockwise"></i></button>
            </div>
            <div class="form-text">Leave empty to use theme defaults.</div>
        </div>

        <!-- Hidden fields for submission -->
        <input type="hidden" id="ms_banner_type" value="" />
        <input type="hidden" id="ms_banner_value" value="" />
    </div>
    <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-sm btn-outline-danger" id="ms_banner_remove"><i class="bi bi-trash me-1"></i> Remove Banner</button>
        <button type="button" class="btn btn-sm btn-primary" id="ms_banner_save"><i class="bi bi-check-lg me-1"></i> Save Banner</button>
    </div>
</div>
</div>
</div>
HTML;
    } // end else (own profile banner modal)

    // â”€â”€ Latest Posts Block â”€â”€
    ms_pe_inject_latest_posts();
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   PROFILE â€” Latest posts block (on member profile)
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

function ms_pe_inject_latest_posts()
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

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   AJAX HANDLER â€” usercp_start hook (ms_action parameter)
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

function ms_profile_extras_ajax()
{
    global $mybb, $db;

    $action = $mybb->get_input('ms_action');
    if (empty($action)) return;

    // Must be logged in
    if ($mybb->user['uid'] <= 0) {
        ms_profile_extras_json(array('error' => 'Not logged in.'), 403);
        return;
    }

    // Verify post key for all write actions (default-deny: only skip for explicitly read-only actions)
    $readOnlyActions = array('get_banner');
    if (!in_array($action, $readOnlyActions)) {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            ms_profile_extras_json(array('error' => 'Invalid security token.'), 403);
            return;
        }
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
    global $mybb, $db;

    $uid  = (int)$mybb->user['uid'];
    $type = $mybb->get_input('banner_type');
    if (!in_array($type, array('upload', 'solid', 'gradient'))) {
        ms_profile_extras_json(array('error' => 'Invalid banner type.'));
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
                ms_profile_extras_json(array('error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed)));
                return;
            }

            // Validate MIME type
            $allowedMimes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detectedMime = finfo_file($finfo, $_FILES['banner_file']['tmp_name']);
                finfo_close($finfo);
                if ($detectedMime === false || !in_array($detectedMime, $allowedMimes)) {
                    ms_profile_extras_json(array('error' => 'File content does not match an allowed image type.'));
                    return;
                }
            }

            // Max 2MB
            if ($_FILES['banner_file']['size'] > 2 * 1024 * 1024) {
                ms_profile_extras_json(array('error' => 'File too large. Max 2MB.'));
                return;
            }

            $filename = 'banner_' . $uid . '_' . TIME_NOW . '.' . $ext;
            $dest = $uploadDir . $filename;

            // Validate resolved path stays within uploads directory
            $realUploadDir = realpath($uploadDir);
            $realBase = realpath(MYBB_ROOT . 'uploads');
            if ($realUploadDir === false || $realBase === false || strpos($realUploadDir, $realBase) !== 0) {
                ms_profile_extras_json(array('error' => 'Invalid upload directory.'));
                return;
            }

            if (!move_uploaded_file($_FILES['banner_file']['tmp_name'], $dest)) {
                ms_profile_extras_json(array('error' => 'Upload failed.'));
                return;
            }
            $value = 'uploads/banners/' . $filename;
        } elseif ($mybb->get_input('banner_url')) {
            // URL-based upload
            $url = trim($mybb->get_input('banner_url'));
            if (!my_validate_url($url)) {
                ms_profile_extras_json(array('error' => 'Invalid URL.'));
                return;
            }
            $value = $url;
        } else {
            ms_profile_extras_json(array('error' => 'No file or URL provided.'));
            return;
        }
    } elseif ($type === 'solid') {
        $value = $mybb->get_input('banner_value');
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
            ms_profile_extras_json(array('error' => 'Invalid color value.'));
            return;
        }
    } elseif ($type === 'gradient') {
        $value = $mybb->get_input('banner_value');
        // Basic sanity check for gradient CSS
        if (strpos($value, 'gradient') === false) {
            ms_profile_extras_json(array('error' => 'Invalid gradient value.'));
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

    ms_profile_extras_json(array('success' => true, 'css' => $css, 'text_color' => $textColor, 'link_color' => $linkColor));
}

/* â”€â”€ Banner: Update Colors Only â”€â”€ */
function ms_pe_update_banner_colors()
{
    global $mybb, $db;
    $uid = (int)$mybb->user['uid'];

    // Find active banner
    $query = $db->simple_select('ms_user_banners', '*', "uid={$uid} AND is_active=1", array('limit' => 1));
    $banner = $db->fetch_array($query);
    if (!$banner) {
        ms_profile_extras_json(array('error' => 'No active banner to update.'));
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

    ms_profile_extras_json(array('success' => true, 'css' => $css, 'text_color' => $textColor, 'link_color' => $linkColor));
}

/* â”€â”€ Banner: Remove â”€â”€ */
function ms_pe_remove_banner()
{
    global $mybb, $db;
    $uid = (int)$mybb->user['uid'];
    $db->update_query('ms_user_banners', array('is_active' => 0), "uid={$uid}");
    ms_profile_extras_json(array('success' => true, 'css' => ''));
}

/* â”€â”€ Banner: Activate previous â”€â”€ */
function ms_pe_activate_banner()
{
    global $mybb, $db;
    $uid = (int)$mybb->user['uid'];
    $bid = $mybb->get_input('bid', MyBB::INPUT_INT);

    // Verify ownership
    $query = $db->simple_select('ms_user_banners', '*', "bid={$bid} AND uid={$uid}", array('limit' => 1));
    $banner = $db->fetch_array($query);
    if (!$banner) {
        ms_profile_extras_json(array('error' => 'Banner not found.'));
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

    ms_profile_extras_json(array('success' => true, 'css' => $css, 'text_color' => $textColor, 'link_color' => $linkColor));
}

/* ── JSON response helper ── */
function ms_profile_extras_json($data, $code = 200)
{
    if ($code === 403) {
        header('HTTP/1.1 403 Forbidden');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
