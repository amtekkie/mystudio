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

    // Defence in depth: the ACP rejects unsafe content on save, but rows can also arrive
    // via SQL/imports — never eval() content that fails MyBB's template security check.
    if (ms_pb_check_template($raw)) {
        return '<!-- MyStudio: page content failed the template security check and was not rendered. -->';
    }

    // Step 1: Process <if> conditionals (may be nested, so loop)
    $content = ms_pb_process_conditionals($raw);

    // Re-check what will actually be eval()'d: removing <if> tags can join "{" and "$..."
    if (ms_pb_check_template($content)) {
        return '<!-- MyStudio: page content failed the template security check and was not rendered. -->';
    }

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
 * Frontend copy of MyBB's check_template() (admin/inc/functions.php), so the
 * renderer does not have to load the ACP function library.
 *
 * @param  string $template
 * @return bool   true when the content is unsafe to eval()
 */
function ms_pb_check_template($template)
{
    if (function_exists('check_template')) {
        return check_template($template);
    }

    // Database credentials
    if (preg_match('#\$config\[(([\'|"]database[\'|"])|([^\'"].*?))\]\[(([\'|"](database|hostname|password|table_prefix|username)[\'|"])|([^\'"].*?))\]#i', $template) !== 0) {
        return true;
    }

    // ${...} complex interpolation (arbitrary expressions)
    if (preg_match('#\$\s*\{#', $template) !== 0) {
        return true;
    }

    // Any {$...} that is not a plain variable / property / array-key reference
    $allowed = preg_replace('~\\{\\$+[a-zA-Z_][a-zA-Z_0-9]*((?:-\\>|\\:\\:)\\$*[a-zA-Z_][a-zA-Z_0-9]*|\\[\s*\\$*([\'"]?)[a-zA-Z_ 0-9 ]+\\2\\]\s*)*\\}~', '', $template);
    if ($allowed === null || preg_match("~\\{\\$.+?\\}~s", $allowed) !== 0) {
        return true;
    }

    return false;
}

/**
 * Process <if $condition then>...<else>...</if> blocks.
 *
 * Each condition is checked against a strict whitelist grammar (variables,
 * literals, comparison/logical operators, parentheses) before evaluation;
 * see ms_pb_safe_eval_condition().
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
            // Innermost <if> first; lazy branches so <else> / the nearest </if> end them
            // (greedy ones swallowed <else>), and the condition cannot span into a nested <if>
            '/<if\s+((?:(?!<if\s).)+?)\s+then>((?:(?!<if\s).)*?)(?:<else>((?:(?!<if\s).)*?))?<\/if>/si',
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
 * Whitelist, not blacklist: the condition is tokenised and may only contain
 *   - variable references: $var / {$var}, with ->prop and ['key'] / ["key"] / [0] chains
 *     ($GLOBALS, $config and superglobals are refused)
 *   - string literals ('...' or "..." without $, { or \) and numbers
 *   - comparison / logical operators: == != === !== < > <= >= && || and or xor !
 *   - parentheses (never directly after an operand, so no calls like $f('x') or ("a"."b")())
 * Anything else (identifiers, function calls, backticks, concatenation, ::) makes the
 * condition false. The expression is rebuilt from the validated tokens before eval().
 *
 * @param  string $condition  The raw condition string from <if ... then>
 * @return bool               The evaluated result
 */
function ms_pb_safe_eval_condition($condition)
{
    global $mybb, $lang, $cache;

    $expr = ms_pb_tokenize_condition($condition);
    if ($expr === false) {
        return false;
    }

    $result = false;
    try {
        eval('$result = (bool)(' . $expr . ');');
    } catch (\Throwable $e) {
        $result = false;
    }

    return $result;
}

/**
 * Validate a condition against the whitelist grammar.
 *
 * @param  string       $condition
 * @return string|false Normalised expression ({$var} braces stripped), or false if rejected
 */
function ms_pb_tokenize_condition($condition)
{
    $var = '\$([a-zA-Z_]\w*)(?:\s*->\s*[a-zA-Z_]\w*|\s*\[\s*(?:\'[^\'\\\\]*\'|"[^"\\\\$\{]*"|\d+)\s*\])*';
    $patterns = array(
        'ws'     => '\s+',
        'bvar'   => '\{\s*(' . $var . ')\s*\}',
        'var'    => $var,
        'string' => '\'(?:[^\'\\\\]|\\\\.)*\'|"[^"\\\\$\{`]*"',
        'number' => '\d+(?:\.\d+)?',
        'op'     => '===|!==|==|!=|<=|>=|&&|\|\||<|>|!|(?i:and|or|xor)\b',
        'open'   => '\(',
        'close'  => '\)',
    );

    $out   = array();
    $prev  = '';   // type of the previous significant token
    $depth = 0;
    $pos   = 0;
    $len   = strlen($condition);

    while ($pos < $len) {
        $matched = false;
        foreach ($patterns as $type => $re) {
            if (!preg_match('/\G(?:' . $re . ')/', $condition, $m, 0, $pos)) {
                continue;
            }
            $tok = $m[0];
            $pos += strlen($tok);
            $matched = true;

            if ($type === 'ws') {
                break;
            }

            $isOperand = in_array($prev, array('var', 'string', 'number', 'close'), true);

            if ($type === 'bvar' || $type === 'var') {
                $varName = $type === 'bvar' ? $m[2] : $m[1];
                if ($varName === 'GLOBALS' || $varName === 'config' || $varName === 'this' || $varName[0] === '_') {
                    return false;
                }
                if ($isOperand) {
                    return false;
                }
                $out[] = $type === 'bvar' ? $m[1] : $tok;
                $prev = 'var';
            } elseif ($type === 'string' || $type === 'number') {
                if ($isOperand) {
                    return false;
                }
                $out[] = $tok;
                $prev = $type;
            } elseif ($type === 'open') {
                // "(" after an operand would be a call: $f(...), "sys"(...), (...)(...)
                if ($isOperand) {
                    return false;
                }
                $depth++;
                $out[] = $tok;
                $prev = 'open';
            } elseif ($type === 'close') {
                if (--$depth < 0) {
                    return false;
                }
                $out[] = $tok;
                $prev = 'close';
            } else {
                $out[] = $tok;
                $prev = 'op';
            }
            break;
        }

        if (!$matched) {
            return false;
        }
    }

    if ($depth !== 0 || empty($out)) {
        return false;
    }

    return implode(' ', $out);
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
