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
    // Table BBCode → HTML. Only complete [table]…[/table] blocks are converted and the
    // HTML is rebuilt from matched rows/cells, so stray tags can never emit unbalanced markup.
    $message = preg_replace_callback('#\[table\](.*?)\[/table\]#si', function ($t) {
        $rows = '';
        preg_match_all('#\[tr\](.*?)\[/tr\]#si', $t[1], $trs);
        foreach ($trs[1] as $tr) {
            preg_match_all('#\[(td|th)\](.*?)\[/\1\]#si', $tr, $cells, PREG_SET_ORDER);
            $row = '';
            foreach ($cells as $c) {
                $tag = strtolower($c[1]);
                $row .= '<' . $tag . '>' . trim($c[2]) . '</' . $tag . '>';
            }
            if ($row !== '') {
                $rows .= '<tr>' . $row . '</tr>';
            }
        }
        if ($rows === '') {
            return $t[0];
        }
        return '<div class="mycode_table_wrapper"><table class="mycode_table">' . $rows . '</table></div>';
    }, $message);

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

    // Load language file needed by upload_attachment() for error strings
    $lang->load("messages");

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

        $error = ms_ee_upload_context();
        if ($error !== true) {
            echo json_encode(array('error' => $error));
            exit;
        }

        $file = $_FILES['upload'];
        $attachment = upload_attachment($file, false);

        if (!empty($attachment['error']) || empty($attachment['aid'])) {
            $error = !empty($attachment['error']) ? $attachment['error'] : 'Upload failed';
            echo json_encode(array('error' => $error));
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

        $error = ms_ee_upload_context();
        if ($error !== true) {
            echo json_encode(array('error' => $error));
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

        if (isset($attachment['error']) || empty($attachment['aid'])) {
            $error = isset($attachment['error']) ? $attachment['error'] : 'Upload failed';
            echo json_encode(array('error' => $error));
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
 * Resolve where an editor upload will be attached and check permissions.
 * Sets the $forum/$pid/$tid globals that upload_attachment() reads.
 * The forum is always derived server-side from pid/tid, never trusted from the client.
 *
 * @return true|string True on success, otherwise an error message.
 */
function ms_ee_upload_context()
{
    global $mybb, $lang, $forum, $pid, $tid;

    $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);

    if ($pid) {
        // Editing a post: attach straight to it, as editpost.php does
        $post = get_post($pid);
        if (!$post) {
            return $lang->ms_error_upload_no_context;
        }
        $fid = (int)$post['fid'];
        $tid = (int)$post['tid'];
        $forumpermissions = forum_permissions($fid);
        if (!is_moderator($fid, 'caneditposts') && ($post['uid'] != $mybb->user['uid'] || empty($forumpermissions['caneditposts']))) {
            return $lang->ms_error_no_upload_permission;
        }
    } else {
        if ($tid) {
            $thread = get_thread($tid);
            if (!$thread) {
                return $lang->ms_error_upload_no_context;
            }
            $fid = (int)$thread['fid'];
        }
        // Without a forum and posthash the attachment can never be linked to a post,
        // and MyBB only shows unlinked attachments to their uploader (PMs, signatures…)
        if (!$fid || !$mybb->get_input('posthash')) {
            return $lang->ms_error_upload_no_context;
        }
        $forumpermissions = forum_permissions($fid);
    }

    $forum = get_forum($fid);
    if (!$forum || empty($forumpermissions['canview']) || empty($forumpermissions['canpostattachments'])) {
        return $lang->ms_error_no_upload_permission;
    }

    return true;
}

/**
 * Fetch URL and return raw binary data (for image proxy).
 * SSRF-safe: every hop (including redirects) is resolved, checked against
 * private/reserved ranges and pinned to the vetted IP. Requires cURL.
 */
function ms_ee_fetch_url_raw($url)
{
    if (!function_exists('curl_init')) {
        return false;
    }

    for ($hop = 0; $hop <= 3; $hop++) {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || !isset($parts['scheme'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        // IPv4 only: IPv6 literals are rejected rather than range-checked
        $host = $parts['host'];
        if (strpos($host, ':') !== false || strpos($host, '[') !== false) {
            return false;
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_MAXFILESIZE    => 10 * 1024 * 1024,
            CURLOPT_USERAGENT      => 'MyBB/EditorExtras',
            // Pin the vetted IP so cURL cannot re-resolve the host to an internal address
            CURLOPT_RESOLVE        => array($host . ':' . $port . ':' . $ip),
        ));
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        if ($http_code >= 300 && $http_code < 400 && $redirect) {
            $url = $redirect;
            continue;
        }
        return ($http_code >= 200 && $http_code < 300) ? $response : false;
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
            // generate_thumbnail() always returns an array; code 4 (image too small) has no filename
            if (!empty($thumb_result['filename'])) {
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
