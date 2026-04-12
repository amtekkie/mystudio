<?php
/**
 * MyStudio Page Builder — Page Renderer
 *
 * Renders MyStudio pages created with the HTML code editor.
 * Evaluates MyBB template variables ({$header}, {$footer}, etc.)
 * and <if> conditionals in page content.
 *
 * Called by ms_pb_misc_start() when a page slug is detected,
 * or by ms_pagebuilder_front_page() for front page routing.
 *
 * @version 3.0.0
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}
/**
 * Look up a page by slug, evaluate template variables, and output.
 *
 * @param string $rawSlug  The page slug from the URL
 */
function ms_pb_serve_page($rawSlug)
{
    global $mybb, $db, $lang, $templates, $cache,
           $headerinclude, $header, $footer,
           $welcomeblock, $pm_notice;
    $slug = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $rawSlug);
    $slug = trim($slug, '/');

    if (empty($slug)) {
        error_no_permission();
    }
    $slug_esc = $db->escape_string($slug);

    // Allow admins to preview draft pages with ?preview=1
    $isPreview = isset($mybb->input['preview']) && $mybb->input['preview'] == '1';
    $isAdmin   = !empty($mybb->user['uid']) && (int)($mybb->usergroup['cancp'] ?? 0) === 1;

    if ($isPreview && $isAdmin) {
        $query = $db->simple_select('ms_pages', '*', "slug='{$slug_esc}'", array('limit' => 1));
    } else {
        $query = $db->simple_select('ms_pages', '*', "slug='{$slug_esc}' AND status='published'", array('limit' => 1));
    }
    $ms_page = $db->fetch_array($query);

    if (!$ms_page) {
        error($lang->error_invalid_page ?? 'The requested page could not be found.');
    }
    if (!empty($ms_page['allowed_groups'])) {
        $allowedGroups = array_map('intval', explode(',', $ms_page['allowed_groups']));
        $userGroups = array((int)$mybb->user['usergroup']);
        if (!empty($mybb->user['additionalgroups'])) {
            $userGroups = array_merge($userGroups, array_map('intval', explode(',', $mybb->user['additionalgroups'])));
        }
        if (!array_intersect($allowedGroups, $userGroups)) {
            error_no_permission();
        }
    }
    $GLOBALS['ms_current_page'] = $ms_page;
    $pageTitle = htmlspecialchars_uni($ms_page['title']);
    $metaTitle = !empty($ms_page['meta_title']) ? htmlspecialchars_uni($ms_page['meta_title']) : $pageTitle;
    $metaDesc  = !empty($ms_page['meta_description']) ? htmlspecialchars_uni($ms_page['meta_description']) : '';
    $bburl = $mybb->settings['bburl'];
    add_breadcrumb($pageTitle, $bburl . '/' . htmlspecialchars_uni($slug));
    $pageCustomCss = '';
    if (!empty($ms_page['custom_css'])) {
        // Strip </style> to prevent tag breakout
        $safeCss = str_ireplace('</style', '<\\/style', $ms_page['custom_css']);
        $pageCustomCss = '<style>' . $safeCss . '</style>';
    }
    $pageCustomJs = '';
    if (!empty($ms_page['custom_js'])) {
        // Strip </script> to prevent tag breakout
        $safeJs = str_ireplace('</script', '<\\/script', $ms_page['custom_js']);
        $pageCustomJs = '<script>' . $safeJs . '</script>';
    }
    $nav = build_breadcrumb();
    $rawContent = $ms_page['content'];
    ms_pb_build_globals_if_needed($rawContent);
    $pageContent = ms_pb_eval_template($rawContent);
    $GLOBALS['ms_page_title']   = $pageTitle;
    $GLOBALS['ms_page_content'] = $pageContent;
    $GLOBALS['ms_page_css']     = $pageCustomCss;
    $GLOBALS['ms_page_js']      = $pageCustomJs;
    $GLOBALS['ms_meta_title']   = $metaTitle;
    $GLOBALS['ms_meta_desc']    = $metaDesc;
    $hasHeaderInclude = (strpos($rawContent, '{$headerinclude}') !== false);
    $ms_color_mode = 'dark';
    $page_html = '<!DOCTYPE html>
<html lang="en" data-bs-theme="dark" data-theme="dark">
<head>
<title>' . $metaTitle . ' - ' . $mybb->settings['bbname'] . '</title>
' . ($hasHeaderInclude ? '' : $headerinclude) . '
' . $pageCustomCss . '
' . ($metaDesc ? '<meta name="description" content="' . $metaDesc . '" />' : '') . '
</head>
<body>
' . $pageContent . '
' . $pageCustomJs . '
</body>
</html>';

    output_page($page_html);
}
/**
 * Evaluate raw HTML content as a MyBB-style template.
 *
 * Processes <if $condition then>...<else>...</if> conditionals
 * first, then expands {$variable} references via PHP eval().
 *
 * @param  string $raw  Raw HTML with template variables
 * @return string       Fully evaluated HTML
 */
function ms_pb_eval_template($raw)
{
    global $mybb, $lang, $templates, $cache, $db,
           $headerinclude, $header, $footer,
           $welcomeblock, $pm_notice, $boardstats,
           $nav, $forums, $search;

    if (empty($raw)) {
        return '';
    }

    // Step 1: Process <if> conditionals (may be nested, so loop)
    $content = ms_pb_process_conditionals($raw);

    // Step 2: Escape for PHP double-quoted string evaluation
    $content = strtr($content, array('\\' => '\\\\', '"' => '\\"'));

    // Step 3: Evaluate — this expands {$variable} references
    $result = '';
    try {
        eval('$result = "' . $content . '";');
    } catch (\Throwable $e) {
        $result = '<!-- MyStudio template error: ' . htmlspecialchars($e->getMessage()) . ' -->';
    }

    return $result;
}

/**
 * Process <if $condition then>...<else>...</if> blocks.
 *
 * Evaluates each condition using a safe subset of allowed variables
 * and operators. Arbitrary PHP execution is blocked.
 *
 * @param  string $content  Raw template content
 * @return string           Content with conditionals resolved
 */
function ms_pb_process_conditionals($content)
{
    if (strpos($content, '<if ') === false) {
        return $content;
    }

    $maxPasses = 10;
    $pass = 0;

    while (strpos($content, '<if ') !== false && $pass < $maxPasses) {
        $before = $content;

        $content = preg_replace_callback(
            '/<if\s+(.+?)\s+then>((?:(?!<if\s).)*)(?:<else>((?:(?!<if\s).)*))?<\/if>/si',
            function ($m) {
                $condition = trim($m[1]);
                $thenPart  = $m[2];
                $elsePart  = isset($m[3]) ? $m[3] : '';

                $result = ms_pb_safe_eval_condition($condition);

                return $result ? $thenPart : $elsePart;
            },
            $content
        );

        if ($content === $before) {
            break;
        }

        $pass++;
    }

    return $content;
}

/**
 * Safely evaluate a template conditional expression.
 *
 * Only allows access to whitelisted MyBB variables and safe operators.
 * Blocks function calls, backticks, includes, and other dangerous constructs.
 *
 * @param  string $condition  The raw condition string from <if ... then>
 * @return bool               The evaluated result
 */
function ms_pb_safe_eval_condition($condition)
{
    global $mybb, $lang, $cache;

    // Block dangerous patterns: function calls, backticks, shell execution, includes
    $dangerous = '/(`|\\beval\\b|\\bexec\\b|\\bsystem\\b|\\bpassthru\\b|\\bshell_exec\\b|\\bpopen\\b|\\bproc_open\\b|\\binclude\\b|\\brequire\\b|\\bfile_|\\bunlink\\b|\\bmkdir\\b|\\brmdir\\b|\\bcurl_|\\bfopen\\b|\\bfwrite\\b|\\bfread\\b|\\b\\$_(?:GET|POST|REQUEST|SERVER|FILES|COOKIE|SESSION|ENV)\\b|\\bpreg_replace\\b|\\bassert\\b|\\bcall_user_func|\\bcreate_function\\b|\\bextract\\b|\\bparse_str\\b|\\bputenv\\b|\\bgetenv\\b|\\bheader\\b|\\bsetcookie\\b)/i';
    if (preg_match($dangerous, $condition)) {
        return false;
    }

    // Block any function call syntax: word( or word (
    if (preg_match('/[a-zA-Z_]\w*\s*\(/', $condition)) {
        return false;
    }

    // Only allow safe tokens: variables ($mybb, $lang, etc.), operators, strings, numbers
    // Strip allowed patterns and see if anything dangerous remains
    $stripped = $condition;
    // Remove string literals (single and double quoted)
    $stripped = preg_replace('/(["\'])(?:\\\\.|(?!\\1).)*\\1/', '', $stripped);
    // Remove variable references ($mybb->..., $lang->..., $var)
    $stripped = preg_replace('/\$[a-zA-Z_]\w*(?:\s*->\s*[a-zA-Z_]\w*(?:\s*\[[^\]]*\])*)*/', '', $stripped);
    // Remove numbers
    $stripped = preg_replace('/\b\d+(?:\.\d+)?\b/', '', $stripped);
    // Remove safe operators and whitespace
    $stripped = preg_replace('/[\s\(\)!&|=<>.,\-+*\/%^]+/', '', $stripped);

    // If anything remains, the condition contains unsafe constructs
    if (strlen(trim($stripped)) > 0) {
        return false;
    }

    $result = false;
    try {
        eval('$result = (bool)(' . $condition . ');');
    } catch (\Throwable $e) {
        $result = false;
    }

    return $result;
}
/**
 * If the page content references certain global templates that
 * aren't automatically built (e.g. {$boardstats}, {$forums}),
 * build them so they're available during eval.
 *
 * @param string $rawContent  The raw page content to inspect
 */
function ms_pb_build_globals_if_needed($rawContent)
{
    global $mybb, $db, $cache, $templates, $lang;

    // {$boardstats} — forum statistics block
    if (strpos($rawContent, '{$boardstats}') !== false) {
        global $boardstats;
        if (empty($boardstats)) {
            $stats = $cache->read('stats');
            if ($stats) {
                $numusers   = my_number_format($stats['numusers']);
                $numthreads = my_number_format($stats['numthreads']);
                $numposts   = my_number_format($stats['numposts']);
                $newestmember = '';
                if ($stats['lastuid'] > 0) {
                    $newestmember = build_profile_link($stats['lastusername'], $stats['lastuid']);
                }
                eval('$boardstats = "' . $templates->get('index_boardstats') . '";');
            }
        }
    }

    // {$forums} — full forum listing
    if (strpos($rawContent, '{$forums}') !== false) {
        global $forums;
        if (empty($forums)) {
            require_once MYBB_ROOT . 'inc/functions_forumlist.php';
            $forums = build_forumbits();
        }
    }

    // {$search} — search box
    if (strpos($rawContent, '{$search}') !== false) {
        global $search;
        if (empty($search)) {
            eval('$search = "' . $templates->get('search') . '";');
        }
    }
}
/**
 * Remove the first element with the given id from an HTML string.
 *
 * @param  string $html  The HTML haystack
 * @param  string $id    The id attribute value to find and remove
 * @return string        HTML with the element removed (or unchanged if not found)
 */
function ms_pb_strip_element_by_id($html, $id)
{
    $marker = 'id="' . $id . '"';
    $start  = strpos($html, $marker);
    if ($start === false) {
        $marker = "id='" . $id . "'";
        $start  = strpos($html, $marker);
    }
    if ($start === false) return $html;

    $tagStart = strrpos(substr($html, 0, $start), '<div');
    if ($tagStart === false) return $html;

    $depth = 0;
    $pos   = $tagStart;
    $len   = strlen($html);
    while ($pos < $len) {
        if (substr($html, $pos, 4) === '<div') {
            $depth++;
            $pos += 4;
        } elseif (substr($html, $pos, 6) === '</div>') {
            $depth--;
            if ($depth === 0) {
                return substr($html, 0, $tagStart) . substr($html, $pos + 6);
            }
            $pos += 6;
        } else {
            $pos++;
        }
    }
    return $html;
}
