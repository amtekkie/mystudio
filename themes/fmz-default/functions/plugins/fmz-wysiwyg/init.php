<?php
/**
 * FMZ WYSIWYG Editor — Mini Plugin Init
 *
 * Injects a configuration element into the page so the client-side JS
 * can read all plugin options. Also loads Google Fonts if configured,
 * and optionally loads highlight.js for code block rendering.
 *
 * @version 2.1.0
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

if (defined('IN_ADMINCP')) return;

global $plugins;

$plugins->add_hook('pre_output_page', 'fmz_wysiwyg_inject_config');
$plugins->add_hook('parse_message_end', 'fmz_wysiwyg_parse_custom_bbcodes');
$plugins->add_hook('postbit', 'fmz_wysiwyg_postbit_attachments');
$plugins->add_hook('postbit_prev', 'fmz_wysiwyg_postbit_attachments');
$plugins->add_hook('xmlhttp', 'fmz_wysiwyg_xmlhttp_upload');

function fmz_wysiwyg_inject_config(&$contents)
{
    global $mybb;

    // Load plugin options
    $opts = array();
    $slug = isset($mybb->fmz_active_slug) ? $mybb->fmz_active_slug : '';

    if ($slug) {
        require_once MYBB_ROOT . 'inc/plugins/fmzstudio/core.php';
        $fmzCore = new FMZStudio();
        $opts = $fmzCore->getMergedMiniPluginOptions($slug, 'fmz-wysiwyg');
    }

    $hasEditor = (strpos($contents, 'sceditor') !== false || strpos($contents, 'MyBBEditor') !== false);
    $hasMessageTextarea = (preg_match('/<textarea[^>]*name=["\']message["\'][^>]*>/i', $contents) === 1);
    $hasFmzWysiwyg = (strpos($contents, 'fmz-wysiwyg') !== false);

    // ── Google Fonts loading (needed both in editor and post pages) ──
    $fontFamilies = isset($opts['font_families']) ? $opts['font_families'] : '';
    $googleFonts = array();
    if (!empty($fontFamilies)) {
        $lines = explode("\n", $fontFamilies);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'google:') === 0) {
                $parts = explode('|', substr($line, 7), 2);
                $googleFonts[] = str_replace(' ', '+', trim($parts[0]));
            }
        }
    }

    $headInject = '';
    $bodyInject = '';

    // Load Google Fonts
    if (!empty($googleFonts)) {
        $headInject .= '<link rel="preconnect" href="https://fonts.googleapis.com" />' . "\n";
        $headInject .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />' . "\n";
        $headInject .= '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
                     . implode('&', array_map(function($f){ return 'family=' . $f . ':wght@400;700'; }, $googleFonts))
                     . '&display=swap" />' . "\n";
    }

    // ── Code highlighting for rendered posts ──
    $codeHighlight = !empty($opts['enable_code_highlight']);
    $codeCopy      = !empty($opts['enable_code_copy']);
    $codeLineNums  = !empty($opts['enable_code_linenumbers']);

    if ($codeHighlight || $codeCopy || $codeLineNums) {
        // Load highlight.js on all pages (for rendered posts)
        if ($codeHighlight) {
            $headInject .= '<link rel="stylesheet" href="' . $mybb->asset_url . '/themes/fmz-default/functions/plugins/fmz-wysiwyg/vendor/atom-one-dark.min.css" />' . "\n";
            $headInject .= '<script src="' . $mybb->asset_url . '/themes/fmz-default/functions/plugins/fmz-wysiwyg/vendor/highlight.min.js"></script>' . "\n";
        }

        // Post-render script for code blocks
        $bodyInject .= '<script>(function(){' . "\n";
        $bodyInject .= 'document.addEventListener("DOMContentLoaded",function(){' . "\n";
        $bodyInject .= 'var codeBlocks=document.querySelectorAll(".codeblock code, .post_body pre code, pre code");' . "\n";
        $bodyInject .= 'for(var i=0;i<codeBlocks.length;i++){' . "\n";
        $bodyInject .= '  var block=codeBlocks[i];' . "\n";
        $bodyInject .= '  var pre=block.parentElement;' . "\n";
        $bodyInject .= '  if(!pre||pre.tagName!=="PRE")continue;' . "\n";
        $bodyInject .= '  pre.style.position="relative";' . "\n";

        if ($codeHighlight) {
            $bodyInject .= '  if(typeof hljs!=="undefined")hljs.highlightElement(block);' . "\n";
        }

        if ($codeLineNums) {
            $bodyInject .= '  pre.classList.add("fmz-code-linenums");' . "\n";
            $bodyInject .= '  var lines=block.textContent.split("\\n");' . "\n";
            $bodyInject .= '  if(lines[lines.length-1]==="")lines.pop();' . "\n";
            $bodyInject .= '  var nums=document.createElement("span");nums.className="fmz-line-nums";' . "\n";
            $bodyInject .= '  for(var j=1;j<=lines.length;j++)nums.innerHTML+=j+"\\n";' . "\n";
            $bodyInject .= '  pre.insertBefore(nums,block);' . "\n";
        }

        if ($codeCopy) {
            $bodyInject .= '  var btn=document.createElement("button");btn.className="fmz-code-copy";btn.textContent="Copy";' . "\n";
            $bodyInject .= '  btn.addEventListener("click",function(){var c=this.parentElement.querySelector("code");' . "\n";
            $bodyInject .= '    navigator.clipboard.writeText(c.textContent).then(function(){btn.textContent="Copied!";setTimeout(function(){btn.textContent="Copy"},1500)});' . "\n";
            $bodyInject .= '  });pre.appendChild(btn);' . "\n";
        }

        $bodyInject .= '}});})();</script>' . "\n";

        // Code block styles — always dark
        $headInject .= '<style>' . "\n";
        $headInject .= '.codeblock{background:#1e1e1e!important;border:1px solid #333!important;border-radius:6px;overflow:hidden;margin:8px 0}' . "\n";
        $headInject .= '.codeblock .title{background:#2d2d2d!important;color:#ccc!important;font-size:12px;padding:4px 8px;border-bottom:1px solid #444}' . "\n";
        $headInject .= '.codeblock .body{background:#1e1e1e!important}' . "\n";
        $headInject .= '.codeblock .body pre,.codeblock .body code{background:#1e1e1e!important;color:#d4d4d4!important;font-family:\"Fira Code\",Consolas,\"Courier New\",monospace}' . "\n";
        $headInject .= '.codeblock .body pre{margin:0;padding:12px 16px;border:none}' . "\n";
        $headInject .= 'pre{position:relative}' . "\n";
        if ($codeLineNums) {
            $headInject .= '.fmz-code-linenums{padding-left:3.5em!important}' . "\n";
            $headInject .= '.fmz-line-nums{position:absolute;left:0;top:0;padding:1em .5em;color:#636d83;font-size:inherit;line-height:inherit;text-align:right;border-right:1px solid #444;user-select:none;pointer-events:none;white-space:pre}' . "\n";
        }
        if ($codeCopy) {
            $headInject .= '.fmz-code-copy{position:absolute;top:4px;right:4px;padding:2px 10px;background:#555;color:#eee;border:none;border-radius:3px;font-size:11px;cursor:pointer;opacity:.6;transition:opacity .2s}' . "\n";
            $headInject .= '.fmz-code-copy:hover{opacity:1}' . "\n";
        }
        $headInject .= '</style>' . "\n";
    }

    // ── Editor config (on pages with SCEditor, a message textarea, or .fmz-wysiwyg textareas) ──
    if ($hasEditor || $hasMessageTextarea || $hasFmzWysiwyg) {
        if ($hasEditor) {
            // Hide SCEditor immediately to prevent FOUC while FMZ editor loads
            $headInject .= '<style>.sceditor-container{display:none!important;visibility:hidden!important}</style>' . "\n";
        }

        $configJson = htmlspecialchars(json_encode($opts), ENT_QUOTES, 'UTF-8');
        $bodyInject .= '<div id="fmz-wysiwyg-config" data-config="' . $configJson . '" style="display:none"></div>' . "\n";
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
 * Custom BBCode parser for tags not natively supported by MyBB.
 * Runs at parse_message_end so standard BBCodes are already processed.
 */
function fmz_wysiwyg_parse_custom_bbcodes(&$message)
{
    // [table], [tr], [td], [th]
    $message = preg_replace('/\[table\]/i', '<table class="fmz-bbcode-table" cellspacing="0" cellpadding="4" style="border-collapse:collapse;border:1px solid #ccc;width:auto;margin:8px 0">', $message);
    $message = preg_replace('/\[\/table\]/i', '</table>', $message);
    $message = preg_replace('/\[tr\]/i', '<tr>', $message);
    $message = preg_replace('/\[\/tr\]/i', '</tr>', $message);
    $message = preg_replace('/\[td\]/i', '<td style="border:1px solid #ccc;padding:6px 10px">', $message);
    $message = preg_replace('/\[\/td\]/i', '</td>', $message);
    $message = preg_replace('/\[th\]/i', '<th style="border:1px solid #ccc;padding:6px 10px;background:#f2f2f2;font-weight:bold">', $message);
    $message = preg_replace('/\[\/th\]/i', '</th>', $message);

    // [highlight=color]...[/highlight]
    $message = preg_replace('/\[highlight=([a-zA-Z]*|#[\da-fA-F]{3,6})\](.*?)\[\/highlight\]/si',
        '<span style="background-color:$1;padding:1px 3px;border-radius:2px">$2</span>', $message);

    // [code=language]...[/code] — enhance code blocks with language label
    // MyBB's native [code] already renders, but [code=lang] does not.
    // We handle [code=lang] by converting to a styled pre/code block.
    $message = preg_replace_callback('/\[code=([a-zA-Z0-9+#]+)\]([\s\S]*?)\[\/code\]/i', function($m) {
        $lang = htmlspecialchars_uni(strtolower($m[1]));
        $code = $m[2];
        return '<div class="codeblock fmz-codeblock-lang">'
             . '<div class="title" style="font-size:12px;padding:4px 8px;background:#2d2d2d;color:#ccc;border-bottom:1px solid #444">' . strtoupper($lang) . '</div>'
             . '<div class="body"><pre style="margin:0"><code class="language-' . $lang . '">' . $code . '</code></pre></div>'
             . '</div>';
    }, $message);

    // Clean up stray <br> inside table structure (MyBB adds <br> for newlines)
    $message = preg_replace('/<table([^>]*)>\s*<br\s*\/?>/i', '<table$1>', $message);
    $message = preg_replace('/<\/tr>\s*<br\s*\/?>/i', '</tr>', $message);
    $message = preg_replace('/<\/td>\s*<br\s*\/?>/i', '</td>', $message);
    $message = preg_replace('/<\/th>\s*<br\s*\/?>/i', '</th>', $message);
    $message = preg_replace('/<br\s*\/?>\s*<tr>/i', '<tr>', $message);
    $message = preg_replace('/<br\s*\/?>\s*<\/table>/i', '</table>', $message);

    return $message;
}

/**
 * XMLHTTP handler — WYSIWYG editor image upload via MyBB's attachment system.
 *
 * Uses MyBB's uploads directory and attachments table, but enforces
 * FMZ WYSIWYG plugin settings (max size, image types) which supersede
 * the global attachment settings for images uploaded through this editor.
 *
 * POST xmlhttp.php?action=fmz_wysiwyg_upload
 *   - my_post_key  (CSRF token)
 *   - posthash     (links attachment to the draft post)
 *   - image        (uploaded file)
 *
 * Returns JSON: { success, aid, url, width, height } or { error }
 */
function fmz_wysiwyg_xmlhttp_upload()
{
    global $mybb, $db, $lang, $cache;

    if ($mybb->get_input('action') !== 'fmz_wysiwyg_upload') {
        return;
    }

    header('Content-Type: application/json; charset=UTF-8');

    // Must be POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(array('error' => 'Invalid request method.'));
        exit;
    }

    // Must be logged in
    if (!$mybb->user['uid']) {
        echo json_encode(array('error' => 'You must be logged in to upload images.'));
        exit;
    }

    // CSRF verification
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        echo json_encode(array('error' => 'Invalid security token. Please refresh the page.'));
        exit;
    }

    // Posthash is required to link the attachment to the draft post
    $posthash = $db->escape_string($mybb->get_input('posthash'));
    if (empty($posthash) || strlen($mybb->get_input('posthash')) !== 32) {
        echo json_encode(array('error' => 'Missing or invalid post hash.'));
        exit;
    }

    // Check upload
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = array(
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary folder missing.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        );
        $code = isset($_FILES['image']) ? $_FILES['image']['error'] : UPLOAD_ERR_NO_FILE;
        $msg  = isset($errorMessages[$code]) ? $errorMessages[$code] : 'Upload error.';
        echo json_encode(array('error' => $msg));
        exit;
    }

    $file = $_FILES['image'];

    // ── Load required MyBB upload/image helpers early ──
    require_once MYBB_ROOT . 'inc/functions_upload.php';
    require_once MYBB_ROOT . 'inc/functions_image.php';

    // ── Load FMZ WYSIWYG options (these supersede global attachment settings) ──
    $maxSizeKB  = 2048; // default 2 MB
    $maxImages  = 10;   // default 10 per post

    require_once MYBB_ROOT . 'inc/plugins/fmzstudio/core.php';
    $fmzCore = new FMZStudio();
    $slug = $fmzCore->getActiveThemeSlug();

    if ($slug) {
        $opts = $fmzCore->getMergedMiniPluginOptions($slug, 'fmz-wysiwyg');
        if (isset($opts['max_image_size_kb'])) {
            $maxSizeKB = (int) $opts['max_image_size_kb'];
        }
        if (isset($opts['max_images_per_post'])) {
            $maxImages = (int) $opts['max_images_per_post'];
        }
    }

    // ── Validate MIME type (images only) ──
    $allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        echo json_encode(array('error' => 'Server error: could not initialize file type detection.'));
        exit;
    }
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mime === false || !in_array($mime, $allowedTypes)) {
        echo json_encode(array('error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP, BMP.'));
        exit;
    }

    // ── Validate extension ──
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');
    if (!in_array($ext, $allowedExts)) {
        echo json_encode(array('error' => 'Invalid file extension.'));
        exit;
    }

    // ── Validate file size (FMZ setting supersedes attachment setting) ──
    if ($file['size'] > $maxSizeKB * 1024) {
        echo json_encode(array('error' => 'Image too large. Max size: ' . $maxSizeKB . ' KB (' . round($maxSizeKB / 1024, 1) . ' MB).'));
        exit;
    }

    // ── Verify it's actually an image ──
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        echo json_encode(array('error' => 'File is not a valid image.'));
        exit;
    }

    // ── Check images-per-post limit (FMZ setting) ──
    if ($maxImages > 0) {
        $query = $db->simple_select('attachments', 'COUNT(aid) AS cnt', "posthash='{$posthash}' AND uid='" . (int) $mybb->user['uid'] . "'");
        $count = (int) $db->fetch_field($query, 'cnt');
        if ($count >= $maxImages) {
            echo json_encode(array('error' => 'Maximum of ' . $maxImages . ' images per post reached.'));
            exit;
        }
    }

    // ── Upload to MyBB's attachment directory ──
    $uploadspath_abs = mk_path_abs($mybb->settings['uploadspath']);
    $month_dir = '';

    if ($mybb->safemode == false) {
        $month_dir = gmdate('Ym');
        if (!@is_dir($uploadspath_abs . '/' . $month_dir)) {
            @mkdir($uploadspath_abs . '/' . $month_dir, 0755, true);
            if (!@is_dir($uploadspath_abs . '/' . $month_dir)) {
                $month_dir = '';
            } else {
                create_attachment_index($uploadspath_abs . '/' . $month_dir);
            }
        }
    }

    $filename = 'post_' . (int) $mybb->user['uid'] . '_' . TIME_NOW . '_' . md5(random_str()) . '.attach';
    $file_data = upload_file($file, $uploadspath_abs . '/' . $month_dir, $filename);

    if (!empty($file_data['error']) && $month_dir) {
        $file_data = upload_file($file, $uploadspath_abs . '/', $filename);
        $month_dir = '';
    }

    if (!empty($file_data['error'])) {
        echo json_encode(array('error' => 'Failed to save uploaded image.'));
        exit;
    }

    $attachname = $month_dir ? $month_dir . '/' . $filename : $filename;

    if (!file_exists($uploadspath_abs . '/' . $attachname)) {
        echo json_encode(array('error' => 'Failed to save uploaded image.'));
        exit;
    }

    // ── Generate thumbnail ──
    $thumbnail = '';
    $thumbname = str_replace('.attach', '_thumb.' . $ext, $attachname);
    $thumb_result = generate_thumbnail(
        $uploadspath_abs . '/' . $attachname,
        $uploadspath_abs,
        $thumbname,
        $mybb->settings['attachthumbh'],
        $mybb->settings['attachthumbw']
    );
    if (!empty($thumb_result['filename'])) {
        $thumbnail = $thumb_result['filename'];
    } elseif (isset($thumb_result['code']) && $thumb_result['code'] == 4) {
        $thumbnail = 'SMALL';
    }

    // ── Insert attachment record ──
    $attacharray = array(
        'pid'            => 0,
        'posthash'       => $posthash,
        'uid'            => (int) $mybb->user['uid'],
        'filename'       => $db->escape_string(basename($file['name'])),
        'filetype'       => $db->escape_string($mime),
        'filesize'       => (int) $file['size'],
        'attachname'     => $attachname,
        'downloads'      => 0,
        'dateuploaded'   => TIME_NOW,
        'thumbnail'      => $thumbnail,
        'visible'        => 1,
    );

    $aid = $db->insert_query('attachments', $attacharray);

    echo json_encode(array(
        'success' => true,
        'aid'     => (int) $aid,
        'url'     => 'attachment.php?aid=' . (int) $aid,
        'width'   => $imageInfo[0],
        'height'  => $imageInfo[1],
    ));
    exit;
}

/**
 * Postbit hook — render any leftover [attachment=aid] as inline images.
 * Runs AFTER get_post_attachments() which replaces attachment BBCodes
 * for attachments it knows about. Any remaining ones (e.g. from preview
 * or when the attachment is on a different post) are rendered here.
 */
function fmz_wysiwyg_postbit_attachments(&$post)
{
    if (isset($post['message']) && stripos($post['message'], '[attachment=') !== false) {
        // [attachment=aid,WxH] — with dimensions
        $post['message'] = preg_replace(
            '/\[attachment=([0-9]+),([0-9]+)x([0-9]+)\]/i',
            '<img src="attachment.php?aid=$1" alt="Attachment" class="attachment fmz-attachment-img" style="width:$2px;height:$3px;max-width:100%" />',
            $post['message']
        );
        // [attachment=aid] — without dimensions (fallback for any still remaining)
        $post['message'] = preg_replace(
            '/\[attachment=([0-9]+)\]/i',
            '<img src="attachment.php?aid=$1" alt="Attachment" class="attachment fmz-attachment-img" style="max-width:100%" />',
            $post['message']
        );
    }
    return $post;
}
