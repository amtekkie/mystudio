<?php
/**
 * MyStudio Editor Extras — Mini Plugin Init
 *
 * Provides:
 *  - Frontend: Injects EditorExtras config + highlight.js CDN into the page
 *  - Backend:  AJAX handlers for image upload and GIF search/trending
 *
 * JS/CSS assets are auto-loaded from this plugin's js/ and css/ directories.
 *
 * @version 2.0.0
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

global $plugins, $mybb;

// Store options globally for hooks
$GLOBALS['ms_ee_options'] = isset($ms_plugin_options) ? $ms_plugin_options : array();

if (!defined('IN_ADMINCP')) {
    $plugins->add_hook('global_end', 'ms_ee_inject_config');
    $plugins->add_hook('xmlhttp',    'ms_ee_xmlhttp');
    $plugins->add_hook('parse_message_end', 'ms_ee_parse_bbcode');
}
/**
 * Hook: global_end
 * Inject EditorExtras JS config and highlight.js CDN into <head>.
 */
function ms_ee_inject_config()
{
    global $headerinclude;

    $opts = $GLOBALS['ms_ee_options'];

    // Highlight.js CDN (light + dark themes)
    if (!isset($opts['syntax_highlight']) || $opts['syntax_highlight']) {
        $headerinclude .= "\n" . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css" media="(prefers-color-scheme: light)">';
        $headerinclude .= "\n" . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css" media="(prefers-color-scheme: dark)">';
        $headerinclude .= "\n" . '<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>';
    }

    // Build JS config from plugin options
    $config = array(
        'bootstrapIcons'  => (!isset($opts['bootstrap_icons']) || $opts['bootstrap_icons']),
        'themed'          => (!isset($opts['themed']) || $opts['themed']),
        'pasteFix'        => (!isset($opts['paste_fix']) || $opts['paste_fix']),
        'pasteStripStyles'=> (isset($opts['paste_strip_styles']) && $opts['paste_strip_styles']),
        'imageUpload'     => (!isset($opts['image_upload']) || $opts['image_upload']),
        'imageMaxWidth'   => (int)(isset($opts['image_max_width']) ? $opts['image_max_width'] : 800),
        'imageResize'     => (!isset($opts['image_resize']) || $opts['image_resize']),
        'emoji'           => (!isset($opts['emoji']) || $opts['emoji']),
        'gif'             => (!isset($opts['gif']) || $opts['gif']) && !empty($opts['gif_api_key']),
        'gifProvider'     => isset($opts['gif_provider']) ? $opts['gif_provider'] : 'tenor',
        'table'           => (!isset($opts['table']) || $opts['table']),
        'autosave'        => false,
        'wordCount'       => (!isset($opts['word_count']) || $opts['word_count']),
        'mention'         => (!isset($opts['mention']) || $opts['mention']),
        'syntaxHighlight' => (!isset($opts['syntax_highlight']) || $opts['syntax_highlight']),
    );

    $headerinclude .= "\n" . '<script type="text/javascript">window.EditorExtras = ' . json_encode($config) . ';</script>';

    // Add CSS classes synchronously so they exist before SCEditor renders (prevents FOUC)
    $classes = array();
    if ($config['bootstrapIcons']) {
        $classes[] = 'ee-bi-icons';
    }
    if ($config['themed']) {
        $classes[] = 'ee-themed';
    }
    if (!empty($classes)) {
        $headerinclude .= "\n" . '<script>document.documentElement.classList.add(' . implode(',', array_map(function($c){ return "'" . $c . "'"; }, $classes)) . ');</script>';
    }
}
/**
 * Hook: parse_message_end
 * Convert [table], [tr], [td], [th], and [icon] BBCode to HTML.
 */
function ms_ee_parse_bbcode($message)
{
    // Table BBCode → HTML
    $message = preg_replace('#\[table\]\s*#si', '<div class="mycode_table_wrapper"><table class="mycode_table">', $message);
    $message = preg_replace('#\s*\[/table\]#si', '</table></div>', $message);
    $message = preg_replace('#\[tr\]\s*#si', '<tr>', $message);
    $message = preg_replace('#\s*\[/tr\]#si', '</tr>', $message);
    $message = preg_replace('#\[th\](.*?)\[/th\]#si', '<th>$1</th>', $message);
    $message = preg_replace('#\[td\](.*?)\[/td\]#si', '<td>$1</td>', $message);

    // [icon]icon-name[/icon] → Bootstrap Icon <i> tag
    $message = preg_replace_callback(
        '#\[icon\]([a-z0-9\-]+)\[/icon\]#si',
        function ($m) {
            $name = preg_replace('/[^a-z0-9\-]/', '', strtolower($m[1]));
            return '<i class="bi bi-' . htmlspecialchars($name, ENT_QUOTES) . '"></i>';
        },
        $message
    );

    return $message;
}

/**
 * Hook: xmlhttp
 * Handle image upload and GIF search/trending AJAX requests.
 */
function ms_ee_xmlhttp()
{
    global $mybb, $db, $lang, $charset;

    $action = $mybb->get_input('action');

    // --- Image Upload ---
    if ($action == 'editorextras_upload') {
        header('Content-type: application/json; charset=' . $charset);

        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            echo json_encode(array('error' => $lang->ms_error_invalid_post_key));
            exit;
        }

        if ($mybb->user['uid'] == 0) {
            echo json_encode(array('error' => $lang->ms_error_login_to_upload));
            exit;
        }

        if (!isset($_FILES['upload']) || $_FILES['upload']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array('error' => $lang->ms_error_no_file_received));
            exit;
        }

        require_once MYBB_ROOT . 'inc/functions_upload.php';

        $fid = $mybb->get_input('fid', MyBB::INPUT_INT);
        $forumpermissions = $fid ? forum_permissions($fid) : array('canpostattachments' => 1);

        if (empty($forumpermissions['canpostattachments'])) {
            echo json_encode(array('error' => $lang->ms_error_no_upload_permission));
            exit;
        }

        // Set up globals that upload_attachment() reads
        global $forum, $pid, $tid;
        $pid = 0;
        $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
        $forum = $fid ? get_forum($fid) : array('fid' => 0);

        // posthash is read from $mybb->input inside upload_attachment()
        if (!$mybb->get_input('posthash')) {
            $mybb->input['posthash'] = md5($mybb->user['uid'] . random_str());
        }

        $file = $_FILES['upload'];
        $attachment = upload_attachment($file, false);

        if (!empty($attachment['error'])) {
            echo json_encode(array('error' => $attachment['error']));
        } else {
            $url = $mybb->settings['bburl'] . '/attachment.php?aid=' . (int)$attachment['aid'];
            $thumb_url = '';
            // Check if a thumbnail was generated
            $att_row = $db->fetch_array($db->simple_select('attachments', 'thumbnail', "aid='" . (int)$attachment['aid'] . "'"));
            if (!empty($att_row['thumbnail']) && $att_row['thumbnail'] !== 'SMALL') {
                $thumb_url = $mybb->settings['bburl'] . '/attachment.php?thumbnail=' . (int)$attachment['aid'];
            }
            echo json_encode(array(
                'success'   => true,
                'aid'       => (int)$attachment['aid'],
                'url'       => $url,
                'thumb_url' => $thumb_url,
                'filename'  => htmlspecialchars_uni($file['name']),
                'posthash'  => $mybb->get_input('posthash')
            ));
        }
        exit;
    }

    // --- Proxy External Image (download + upload as attachment) ---
    if ($action == 'editorextras_proxy') {
        header('Content-type: application/json; charset=' . $charset);

        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            echo json_encode(array('error' => $lang->ms_error_invalid_post_key));
            exit;
        }

        if ($mybb->user['uid'] == 0) {
            echo json_encode(array('error' => $lang->ms_error_login_required));
            exit;
        }

        require_once MYBB_ROOT . 'inc/functions_upload.php';

        $fid = $mybb->get_input('fid', MyBB::INPUT_INT);
        $forumpermissions = $fid ? forum_permissions($fid) : array('canpostattachments' => 1);

        if (empty($forumpermissions['canpostattachments'])) {
            echo json_encode(array('error' => $lang->ms_error_no_upload_permission));
            exit;
        }

        $image_url = trim($mybb->get_input('url'));
        if (empty($image_url)) {
            echo json_encode(array('error' => $lang->ms_error_no_url_provided));
            exit;
        }

        // Validate URL scheme
        $parsed = parse_url($image_url);
        if (!$parsed || !isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), array('http', 'https'))) {
            echo json_encode(array('error' => $lang->ms_error_invalid_url));
            exit;
        }

        // Don't proxy our own attachment URLs
        if (strpos($image_url, $mybb->settings['bburl']) === 0) {
            echo json_encode(array('error' => $lang->ms_error_already_local));
            exit;
        }

        // Download the image
        $image_data = ms_ee_fetch_url_raw($image_url);
        if ($image_data === false || strlen($image_data) < 100) {
            echo json_encode(array('error' => $lang->ms_error_download_failed));
            exit;
        }

        // Max 10MB
        if (strlen($image_data) > 10 * 1024 * 1024) {
            echo json_encode(array('error' => $lang->ms_error_image_too_large_10mb));
            exit;
        }

        // Determine extension from content
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($image_data);
        $mime_map = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'image/bmp'  => 'bmp',
        );
        if (!isset($mime_map[$mime])) {
            echo json_encode(array('error' => $lang->ms_error_not_valid_image));
            exit;
        }

        $ext = $mime_map[$mime];
        $filename = 'image_' . substr(md5($image_url), 0, 8) . '.' . $ext;

        // Write to temp file
        $tmp = tempnam(sys_get_temp_dir(), 'ee_');
        file_put_contents($tmp, $image_data);

        // Set up globals for upload_attachment()
        global $forum, $pid, $tid;
        $pid = 0;
        $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
        $forum = $fid ? get_forum($fid) : array('fid' => 0);

        if (!$mybb->get_input('posthash')) {
            $mybb->input['posthash'] = md5($mybb->user['uid'] . random_str());
        }

        // Simulate $_FILES structure
        $file = array(
            'name'     => $filename,
            'type'     => $mime,
            'tmp_name' => $tmp,
            'error'    => 0,
            'size'     => strlen($image_data)
        );

        // upload_attachment requires is_uploaded_file() to pass — we need to use
        // MyBB's upload_file directly instead. Build the attachment record manually.
        $attachment = ms_ee_save_as_attachment($file, $mybb, $db);

        @unlink($tmp);

        if (isset($attachment['error'])) {
            echo json_encode(array('error' => $attachment['error']));
        } else {
            $url = $mybb->settings['bburl'] . '/attachment.php?aid=' . (int)$attachment['aid'];
            $thumb_url = '';
            $att_row = $db->fetch_array($db->simple_select('attachments', 'thumbnail', "aid='" . (int)$attachment['aid'] . "'"));
            if (!empty($att_row['thumbnail']) && $att_row['thumbnail'] !== 'SMALL') {
                $thumb_url = $mybb->settings['bburl'] . '/attachment.php?thumbnail=' . (int)$attachment['aid'];
            }
            echo json_encode(array(
                'success'   => true,
                'aid'       => (int)$attachment['aid'],
                'url'       => $url,
                'thumb_url' => $thumb_url,
                'filename'  => htmlspecialchars_uni($filename),
                'posthash'  => $mybb->get_input('posthash')
            ));
        }
        exit;
    }

    // --- GIF Search ---
    if ($action == 'editorextras_gif_search') {
        if ($mybb->user['uid'] <= 0) {
            echo json_encode(array('error' => $lang->ms_error_login_required));
            exit;
        }
        $opts = $GLOBALS['ms_ee_options'];
        $provider = isset($opts['gif_provider']) ? $opts['gif_provider'] : 'tenor';
        $api_key  = isset($opts['gif_api_key']) ? trim($opts['gif_api_key']) : '';
        $query    = urlencode($mybb->get_input('q'));
        $limit    = 20;

        header('Content-type: application/json; charset=' . $charset);

        if (empty($api_key)) {
            echo json_encode(array('error' => $lang->ms_error_gif_api_not_configured));
            exit;
        }

        if ($provider == 'tenor') {
            $url = "https://tenor.googleapis.com/v2/search?q={$query}&key={$api_key}&limit={$limit}&media_filter=gif,tinygif";
        } elseif ($provider == 'giphy') {
            $url = "https://api.giphy.com/v1/gifs/search?q={$query}&api_key={$api_key}&limit={$limit}&rating=g";
        } else {
            echo json_encode(array('error' => $lang->ms_error_invalid_gif_provider));
            exit;
        }

        echo ms_ee_fetch_url($url);
        exit;
    }

    // --- GIF Trending ---
    if ($action == 'editorextras_gif_trending') {
        if ($mybb->user['uid'] <= 0) {
            echo json_encode(array('error' => $lang->ms_error_login_required));
            exit;
        }
        $opts = $GLOBALS['ms_ee_options'];
        $provider = isset($opts['gif_provider']) ? $opts['gif_provider'] : 'tenor';
        $api_key  = isset($opts['gif_api_key']) ? trim($opts['gif_api_key']) : '';
        $limit    = 20;

        header('Content-type: application/json; charset=' . $charset);

        if (empty($api_key)) {
            echo json_encode(array('error' => $lang->ms_error_gif_api_not_configured));
            exit;
        }

        if ($provider == 'tenor') {
            $url = "https://tenor.googleapis.com/v2/featured?key={$api_key}&limit={$limit}&media_filter=gif,tinygif";
        } else {
            $url = "https://api.giphy.com/v1/gifs/trending?api_key={$api_key}&limit={$limit}&rating=g";
        }

        echo ms_ee_fetch_url($url);
        exit;
    }
}

/**
 * Fetch a URL via cURL or file_get_contents.
 */
function ms_ee_fetch_url($url)
{
    global $lang;
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response ?: json_encode(array('error' => $lang->ms_error_request_failed));
    } elseif (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(array('http' => array('timeout' => 10)));
        $response = @file_get_contents($url, false, $ctx);
        return $response ?: json_encode(array('error' => $lang->ms_error_request_failed));
    }
    return json_encode(array('error' => $lang->ms_error_no_http_client));
}

/**
 * Fetch URL and return raw binary data (for image proxy).
 * Validates against SSRF by blocking private/reserved IP ranges.
 */
function ms_ee_fetch_url_raw($url)
{
    // SSRF protection: block private/reserved IP ranges and localhost
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        return false;
    }
    $lower_host = strtolower($host);
    if (in_array($lower_host, array('localhost', 'localhost.localdomain', '[::1]'))) {
        return false;
    }
    // Resolve hostname to IP and check against private/reserved ranges
    $ip = gethostbyname($host);
    if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
        // DNS resolution failed
        return false;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MyBB/EditorExtras');
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($http_code >= 200 && $http_code < 300) ? $response : false;
    } elseif (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(array(
            'http' => array(
                'timeout' => 15,
                'user_agent' => 'MyBB/EditorExtras',
                'follow_location' => true,
                'max_redirects' => 3,
            )
        ));
        $response = @file_get_contents($url, false, $ctx);
        return $response !== false ? $response : false;
    }
    return false;
}

/**
 * Save a non-uploaded file as a MyBB attachment (bypasses is_uploaded_file check).
 * Used for proxied external images.
 */
function ms_ee_save_as_attachment($file, $mybb, $db)
{
    global $cache, $forum, $pid, $lang;

    if (!is_object($lang)) {
        require_once MYBB_ROOT . 'inc/class_language.php';
        $lang = new MyLanguage();
        $lang->set_path(MYBB_ROOT . 'inc/languages');
        $lang->set_language($mybb->settings['bblanguage']);
        $lang->load('messages');
    }

    $attachtypes = (array)$cache->read('attachtypes');
    $ext = get_extension($file['name']);

    // Filter allowed types for current user/forum
    foreach ($attachtypes as $e => $t) {
        if (!is_member($t['groups']) || ($t['forums'] != -1 && strpos(',' . $t['forums'] . ',', ',' . $forum['fid'] . ',') === false)) {
            unset($attachtypes[$e]);
        }
    }

    if (!isset($attachtypes[$ext])) {
        return array('error' => $lang->ms_error_file_type_not_allowed . htmlspecialchars_uni($ext) . ').');
    }

    $attachtype = $attachtypes[$ext];

    if ($file['size'] > $attachtype['maxsize'] * 1024 && $attachtype['maxsize'] != '') {
        return array('error' => $lang->ms_error_file_too_large_max . $attachtype['maxsize'] . 'KB).');
    }

    $posthash = $db->escape_string($mybb->get_input('posthash'));

    // Generate file path
    $month_dir = date('Ym');
    $upload_path = $mybb->settings['uploadspath'];
    if (!is_dir($upload_path . '/' . $month_dir)) {
        @mkdir($upload_path . '/' . $month_dir, 0777, true);
    }

    $filename_internal = 'post_' . $mybb->user['uid'] . '_' . TIME_NOW . '_' . md5(random_str()) . '.attach';
    $filepath = $upload_path . '/' . $month_dir . '/' . $filename_internal;

    if (!@copy($file['tmp_name'], $filepath)) {
        return array('error' => $lang->ms_error_save_failed);
    }
    @chmod($filepath, 0644);

    // Generate thumbnail for images
    $thumbnail = '';
    $image_exts = array('gif', 'png', 'jpg', 'jpeg', 'jpe', 'webp', 'bmp');
    if (in_array($ext, $image_exts)) {
        require_once MYBB_ROOT . 'inc/functions_image.php';
        $thumb_w = (int)$mybb->settings['attachthumbw'];
        $thumb_h = (int)$mybb->settings['attachthumbh'];
        if ($thumb_w > 0 && $thumb_h > 0) {
            $thumbname = str_replace('.attach', '_thumb.' . $ext, $filename_internal);
            $thumb_result = generate_thumbnail($filepath, $upload_path . '/' . $month_dir, $thumbname, $thumb_h, $thumb_w);
            if ($thumb_result) {
                $thumbnail = $month_dir . '/' . $thumbname;
            }
        }
        if (empty($thumbnail)) {
            $thumbnail = 'SMALL';
        }
    }

    // Insert DB record
    $insert = array(
        'pid'            => (int)$pid,
        'posthash'       => $posthash,
        'uid'            => (int)$mybb->user['uid'],
        'filename'       => $db->escape_string($file['name']),
        'filetype'       => $db->escape_string($file['type']),
        'filesize'       => (int)$file['size'],
        'attachname'     => $month_dir . '/' . $filename_internal,
        'downloads'      => 0,
        'dateuploaded'   => TIME_NOW,
        'visible'        => 1,
        'thumbnail'      => $thumbnail,
    );

    $aid = $db->insert_query('attachments', $insert);

    return array('aid' => $aid);
}
