<?php
/**
 * MyStudio Forum Display Extras — Mini Plugin Init
 *
 * Provides:
 *  - Last poster avatar in forumbit with user info modal
 *  - Subforum columns layout
 *  - Card-style forum listing option
 *
 * @version 1.0.0
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

global $plugins, $db, $mybb;

// Store options in a global for use in hooks
$GLOBALS['ms_fde_options'] = isset($ms_plugin_options) ? $ms_plugin_options : array();

if (!defined('IN_ADMINCP')) {
    $plugins->add_hook('build_forumbits_forum', 'ms_fde_enrich_forum');
    $plugins->add_hook('forumdisplay_thread',   'ms_fde_enrich_thread');
    $plugins->add_hook('pre_output_page',       'ms_fde_inject_output');
}
/**
 * Hook: build_forumbits_forum
 * Enrich the $forum array with last poster avatar data.
 * This runs for each forum inside build_forumbits().
 */
function ms_fde_enrich_forum($forum)
{
    global $db, $mybb, $templates;
    $opts = $GLOBALS['ms_fde_options'];
    static $template_swapped = false;
    if (!$template_swapped) {
        $template_swapped = true;
        $layout = isset($opts['forum_layout']) ? $opts['forum_layout'] : 'rows';
        if ($layout === 'cards') {
            // Force-load the card template into cache, then override the row template
            $templates->get('forumbit_depth2_forum_card');
            if (!empty($templates->cache['forumbit_depth2_forum_card'])) {
                $templates->cache['forumbit_depth2_forum'] = $templates->cache['forumbit_depth2_forum_card'];
            }
        }
    }
    if ((!isset($opts['enable_lastposter_avatar']) || $opts['enable_lastposter_avatar'])
        && !empty($forum['lastposteruid'])
    ) {
        // Batch-cache user avatars to avoid N+1 queries
        static $avatar_cache = null;

        if ($avatar_cache === null) {
            $avatar_cache = array();
            // Pre-load all lastposter avatars in one query
            $forum_cache = ms_fde_get_forum_cache();
            $uids = array();
            foreach ($forum_cache as $pid => $parents) {
                foreach ($parents as $parent) {
                    foreach ($parent as $f) {
                        if (!empty($f['lastposteruid'])) {
                            $uids[(int)$f['lastposteruid']] = true;
                        }
                    }
                }
            }
            if (!empty($uids)) {
                $uid_list = implode(',', array_keys($uids));
                $query = $db->simple_select(
                    'users',
                    'uid, username, avatar, avatardimensions, usergroup, displaygroup, postnum, reputation, regdate, lastactive',
                    "uid IN ({$uid_list})"
                );
                while ($u = $db->fetch_array($query)) {
                    $avatar_cache[(int)$u['uid']] = $u;
                }
            }
        }

        $uid = (int)$forum['lastposteruid'];
        if (isset($avatar_cache[$uid])) {
            $user = $avatar_cache[$uid];
            $av = format_avatar($user['avatar'], $user['avatardimensions'], '34|34');
            $forum['ms_lastposter_avatar']  = $av['image'];
            $forum['ms_lastposter_uid']     = $uid;
            $forum['ms_lastposter_name']    = htmlspecialchars_uni($user['username']);
            $forum['ms_lastposter_group']   = $user['usergroup'];
            $forum['ms_lastposter_dgroup']  = $user['displaygroup'];
            $forum['ms_lastposter_posts']   = (int)$user['postnum'];
            $forum['ms_lastposter_rep']     = (int)$user['reputation'];
            $forum['ms_lastposter_regdate'] = $user['regdate'];
            $forum['ms_lastposter_lastactive'] = $user['lastactive'];

            // Usergroup-styled profile link
            if (!isset($opts['enable_usergroup_style']) || $opts['enable_usergroup_style']) {
                $styled_name = format_name(
                    htmlspecialchars_uni($user['username']),
                    $user['usergroup'],
                    $user['displaygroup']
                );
                $forum['ms_lastposter_styled'] = build_profile_link($styled_name, $uid);
            } else {
                $forum['ms_lastposter_styled'] = build_profile_link(
                    htmlspecialchars_uni($user['username']),
                    $uid
                );
            }
        }
    }

    // Fallback: set plain profile link if not already set
    if (empty($forum['ms_lastposter_styled']) && !empty($forum['lastposteruid'])) {
        $forum['ms_lastposter_styled'] = build_profile_link(
            htmlspecialchars_uni($forum['lastposter']),
            (int)$forum['lastposteruid']
        );
    } elseif (empty($forum['ms_lastposter_styled'])) {
        $forum['ms_lastposter_styled'] = htmlspecialchars_uni($forum['lastposter'] ?? '');
    }

    // Fallback: ensure avatar field exists even for guests / missing users
    if (empty($forum['ms_lastposter_avatar'])) {
        $default_av = format_avatar('', '', '34|34');
        $forum['ms_lastposter_avatar'] = $default_av['image'];
    }

    return $forum;
}

/**
 * Get the global forum cache (fcache).
 */
function ms_fde_get_forum_cache()
{
    global $fcache;
    return is_array($fcache) ? $fcache : array();
}
/**
 * Hook: forumdisplay_thread
 * Set last poster avatar for each thread row in forumdisplay.
 * Batch-loads all avatars from $threadcache on first call.
 */
function ms_fde_enrich_thread()
{
    global $db, $mybb, $thread, $threadcache;
    $opts = $GLOBALS['ms_fde_options'];

    // Default — empty HTML so template var doesn't error
    $GLOBALS['ms_thread_lastposter_avatar'] = '';

    if (isset($opts['enable_thread_lastposter_avatar']) && !$opts['enable_thread_lastposter_avatar']) {
        return;
    }

    // Batch-cache avatars on first call
    static $thread_avatar_cache = null;
    if ($thread_avatar_cache === null) {
        $thread_avatar_cache = array();
        if (!empty($threadcache) && is_array($threadcache)) {
            $uids = array();
            foreach ($threadcache as $t) {
                if (!empty($t['lastposteruid'])) {
                    $uids[(int)$t['lastposteruid']] = true;
                }
            }
            if (!empty($uids)) {
                $uid_list = implode(',', array_keys($uids));
                $query = $db->simple_select(
                    'users',
                    'uid, username, avatar, avatardimensions, usergroup, displaygroup',
                    "uid IN ({$uid_list})"
                );
                while ($u = $db->fetch_array($query)) {
                    $thread_avatar_cache[(int)$u['uid']] = $u;
                }
            }
        }
    }

    $uid = (int)$thread['lastposteruid'];
    if ($uid > 0 && isset($thread_avatar_cache[$uid])) {
        $user = $thread_avatar_cache[$uid];
        $av = format_avatar($user['avatar'], $user['avatardimensions'], '28|28');
        $enableModal = !isset($opts['enable_user_modal']) || $opts['enable_user_modal'];
        $modalAttr = $enableModal ? ' data-ms-user-modal="' . $uid . '"' : '';
        $GLOBALS['ms_thread_lastposter_avatar'] = '<span class="ms-thread-lp-avatar"' . $modalAttr . '><img src="' . $av['image'] . '" alt="" /></span>';
    } else {
        // Guest or missing user — default avatar
        $av = format_avatar('', '', '28|28');
        $GLOBALS['ms_thread_lastposter_avatar'] = '<span class="ms-thread-lp-avatar"><img src="' . $av['image'] . '" alt="" /></span>';
    }
}

/**
 * Hook: pre_output_page
 * Inject CSS/JS and modify HTML output for all three features.
 */
function ms_fde_inject_output(&$contents)
{
    global $mybb;
    $opts = $GLOBALS['ms_fde_options'];

    $css = '';
    $js  = '';
    if (!isset($opts['enable_lastposter_avatar']) || $opts['enable_lastposter_avatar']) {
        $enableModal = !isset($opts['enable_user_modal']) || $opts['enable_user_modal'];

        $css .= <<<'CSS'
/* MyStudio Forum Display Extras — Last poster avatar */
.ms-lastposter-wrap { display: flex; align-items: flex-start; gap: 8px; }
.ms-lastposter-avatar { flex-shrink: 0; cursor: pointer; }
.ms-lastposter-avatar img { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 2px solid var(--tekbb-border); transition: border-color .2s; }
.ms-lastposter-avatar:hover img { border-color: var(--tekbb-accent); }
.ms-lastposter-info { min-width: 0; flex: 1; }
/* MyStudio Forum Display Extras — Thread last poster avatar */
.ms-thread-lp-avatar { flex-shrink: 0; cursor: pointer; display: inline-block; vertical-align: middle; margin-left: 6px; }
.ms-thread-lp-avatar img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 2px solid var(--tekbb-border); transition: border-color .2s; }
.ms-thread-lp-avatar:hover img { border-color: var(--tekbb-accent); }
CSS;

        if ($enableModal) {
            $bburl = $mybb->settings['bburl'];
            global $lang;
            $uc_posts = $lang->ms_usercard_posts;
            $uc_rep = $lang->ms_usercard_rep;
            $uc_joined = $lang->ms_usercard_joined;
            $uc_profile = $lang->ms_usercard_profile;
            $uc_pm = $lang->ms_usercard_pm;
            $uc_rate_user = $lang->ms_usercard_rate_user;
            $uc_failed_load = $lang->ms_error_failed_load;
            $uc_failed_load_rep = $lang->ms_error_failed_load_rep;
            $js .= <<<JSEOF
/* MyStudio Forum Display Extras — User Info Modal */
(function(){
  var modalId='msUserInfoModal';
  function ensureModal(){
    var el=document.getElementById(modalId);
    if(el) return el;
    document.body.insertAdjacentHTML('beforeend',
      '<div class="modal fade" id="'+modalId+'" tabindex="-1">'+
      '<div class="modal-dialog modal-sm modal-dialog-centered">'+
      '<div class="modal-content shadow">'+
      '<div class="modal-body p-0" id="'+modalId+'Body">'+
      '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>'+
      '</div></div></div></div>');
    return document.getElementById(modalId);
  }
  document.addEventListener('click',function(e){
    var trigger=e.target.closest('[data-ms-user-modal]');
    if(!trigger) return;
    e.preventDefault();
    var uid=trigger.getAttribute('data-ms-user-modal');
    if(!uid||uid==='0') return;
    var el=ensureModal();
    var body=el.querySelector('#'+modalId+'Body');
    body.innerHTML='<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    bootstrap.Modal.getOrCreateInstance(el).show();
    fetch('{$bburl}/xmlhttp.php?action=ms_fde_usercard&uid='+encodeURIComponent(uid),{credentials:'same-origin'})
      .then(function(r){return r.json();})
      .then(function(data){
        if(!data.success){body.innerHTML='<div class="alert alert-warning m-3">'+data.error+'</div>';return;}
        var u=data.user;
        var html='<div class="text-center position-relative">'
          +'<div style="height:80px;background:var(--tekbb-heading-bg);border-radius:.5rem .5rem 0 0"></div>'
          +'<button type="button" class="btn-close position-absolute top-0 end-0 m-2 btn-close-white" data-bs-dismiss="modal"></button>'
          +'<img src="'+u.avatar+'" class="rounded-circle border border-3 border-white" style="width:64px;height:64px;object-fit:cover;margin-top:-32px" alt="">'
          +'<h6 class="mt-2 mb-0">'+u.formatted_name+'</h6>'
          +'<div class="text-muted small">'+u.usertitle+'</div>'
          +'</div>'
          +'<div class="px-3 py-2">'
          +'<div class="row g-2 text-center small mb-2">'
          +'<div class="col-4"><div class="fw-semibold">'+u.postnum+'</div><div class="text-muted">{$uc_posts}</div></div>'
          +'<div class="col-4"><div class="fw-semibold">'+u.reputation+'</div><div class="text-muted">{$uc_rep}</div></div>'
          +'<div class="col-4"><div class="fw-semibold">'+u.regdate+'</div><div class="text-muted">{$uc_joined}</div></div>'
          +'</div>'
          +'<div class="d-flex gap-2 mt-2 mb-1">'
          +'<a href="'+u.profile_url+'" class="btn btn-sm btn-outline-primary flex-fill"><i class="bi bi-person me-1"></i>{$uc_profile}</a>';
        if(u.can_pm) html+='<a href="'+u.pm_url+'" class="btn btn-sm btn-outline-success flex-fill"><i class="bi bi-envelope me-1"></i>{$uc_pm}</a>';
        html+='</div>';
        if(u.can_rate) html+='<a href="javascript:void(0)" data-ms-rate-uid="'+u.uid+'" class="btn btn-sm btn-outline-warning w-100 mt-1 mb-1"><i class="bi bi-star me-1"></i>{$uc_rate_user}</a>';
        html+='</div>';
        body.innerHTML=html;
      })
      .catch(function(){body.innerHTML='<div class="alert alert-danger m-3">{$uc_failed_load}</div>';});
  });
  document.addEventListener('click',function(e){
    var rateBtn=e.target.closest('[data-ms-rate-uid]');
    if(!rateBtn) return;
    e.preventDefault();
    var ruid=rateBtn.getAttribute('data-ms-rate-uid');
    var el=document.getElementById(modalId);
    var body=el?el.querySelector('#'+modalId+'Body'):null;
    if(!body) return;
    body.innerHTML='<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    fetch('{$bburl}/reputation.php?action=add&uid='+ruid+'&pid=0&modal=1',{credentials:'same-origin'})
      .then(function(r){return r.text();})
      .then(function(html){
        // Strip the self-contained CSS links and outer modal/card wrappers — extract just the form
        var tmp=document.createElement('div');
        tmp.innerHTML=html;
        var form=tmp.querySelector('form');
        if(form){
          // Prevent the default MyBB JS submit handler, use standard form submit
          form.removeAttribute('onsubmit');
          body.innerHTML='<div class="p-3"><h6 class="mb-3 fw-bold"><i class="bi bi-star me-1"></i>{$uc_rate_user}</h6></div>';
          body.querySelector('div').appendChild(form);
        } else {
          // Error or unexpected content — show as-is
          var card=tmp.querySelector('.card-body')||tmp.querySelector('.alert')||tmp;
          body.innerHTML='<div class="p-3">'+card.innerHTML+'</div>';
        }
      })
      .catch(function(){body.innerHTML='<div class="alert alert-danger m-3">{$uc_failed_load_rep}</div>';});
  });
})();
JSEOF;
        }
    }
    $subCols = isset($opts['subforum_columns']) ? (int)$opts['subforum_columns'] : 0;
    if ($subCols == 1) {
        $css .= <<<CSS
/* MyStudio Forum Display Extras — Subforum list layout */
.ms-subforum-cols { display: flex; flex-direction: column; margin-top: 4px; }
.ms-subforum-cols .ms-sf-item { display: flex; align-items: center; gap: 4px; padding: 2px 0; }
CSS;
        // Rewrite subforum containers: strip commas, wrap icon+link pairs as list items
        $needle = '<div class="forumbit_subforums smalltext">';
        $offset = 0;
        while (($pos = strpos($contents, $needle, $offset)) !== false) {
            // Find the matching closing </div> accounting for one level of nested divs
            $start = $pos + strlen($needle);
            $depth = 1;
            $i = $start;
            $len = strlen($contents);
            while ($i < $len && $depth > 0) {
                if (substr($contents, $i, 4) === '<div') {
                    $depth++;
                } elseif (substr($contents, $i, 6) === '</div>') {
                    $depth--;
                    if ($depth === 0) break;
                }
                $i++;
            }
            $inner = substr($contents, $start, $i - $start);
            // Remove the "Subforums:" label text
            $inner = preg_replace('/^[^<]*/', '', $inner);
            // Remove all comma separators
            $inner = preg_replace('/,\s*/', '', $inner);
            // Wrap each icon+link pair: <div class="subforumicon...">...</div><a>...</a>
            $inner = preg_replace(
                '/(<div[^>]*class="subforumicon[^"]*"[^>]*>.*?<\/div>)\s*(<a\b[^>]*>.*?<\/a>)/s',
                '<div class="ms-sf-item">$1$2</div>',
                $inner
            );
            // Wrap standalone links (no icon)
            $inner = preg_replace(
                '/(?<!<\/div>)(<a\b[^>]*>.*?<\/a>)(?!<\/div>)/s',
                '<div class="ms-sf-item">$1</div>',
                $inner
            );
            $replacement = '<div class="forumbit_subforums smalltext ms-subforum-cols">' . $inner . '</div>';
            $contents = substr($contents, 0, $pos) . $replacement . substr($contents, $i + 6);
            $offset = $pos + strlen($replacement);
        }
    }
    $layout = isset($opts['forum_layout']) ? $opts['forum_layout'] : 'rows';
    $cardsPerRow = isset($opts['cards_per_row']) ? (int)$opts['cards_per_row'] : 3;
    if ($layout === 'cards') {
        $css .= <<<CSS
/* MyStudio Forum Display Extras — Card layout */
.ms-forum-cards { display: flex; flex-wrap: wrap; gap: 1rem; padding: .75rem; }
.ms-forum-cards > div:first-child:not(.ms-forum-card) { display: none !important; }
.ms-forum-card { flex: 0 0 calc(100% / {$cardsPerRow} - 1rem); min-width: 0; }
@media(max-width:991.98px){ .ms-forum-card { flex: 0 0 calc(50% - .5rem); } }
@media(max-width:575.98px){ .ms-forum-card { flex: 0 0 100%; } }
.ms-forum-card .card { height: 100%; border: 1px solid var(--tekbb-border); border-radius: .5rem; overflow: hidden; transition: box-shadow .2s; }
.ms-forum-card .card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.ms-forum-card .card-body { padding: .75rem; }
.ms-forum-card .ms-card-icon { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: .375rem; background: var(--tekbb-accent-subtle, rgba(var(--bs-primary-rgb),.08)); flex-shrink: 0; }
.ms-forum-card .ms-card-stats { font-size: .8rem; color: var(--tekbb-muted); }
.ms-forum-card .ms-card-lastpost { font-size: .78rem; color: var(--tekbb-muted); border-top: 1px solid var(--tekbb-border); padding: .5rem .75rem; background: var(--bs-body-bg); }
CSS;
        // Add card container class to category containers
        $contents = preg_replace(
            '/id="(cat_\d+_e)"/',
            'id="$1" class="ms-forum-cards"',
            $contents
        );
    }
    if (!empty($css)) {
        $styleTag = "<style>/* MyStudio Forum Display Extras */\n{$css}</style>\n";
        $contents = str_replace('</head>', $styleTag . '</head>', $contents);
    }
    if (!empty($js)) {
        $scriptTag = "<script>\n{$js}\n</script>\n";
        $contents = str_replace('</body>', $scriptTag . '</body>', $contents);
    }

    return $contents;
}
/**
 * Register the XMLHTTP action for user card data.
 */
$plugins->add_hook('xmlhttp', 'ms_fde_xmlhttp_usercard');

function ms_fde_xmlhttp_usercard()
{
    global $mybb, $db, $charset, $lang;

    if ($mybb->get_input('action') !== 'ms_fde_usercard') {
        return;
    }

    // Ensure required function files are loaded (xmlhttp.php doesn't load them)
    require_once MYBB_ROOT . 'inc/functions_user.php';

    // Set JSON header
    header('Content-Type: application/json; charset=' . $charset);

    // Permission check
    if ($mybb->usergroup['canviewprofiles'] != 1) {
        echo json_encode(array('success' => false, 'error' => $lang->ms_error_no_permission));
        exit;
    }

    $uid = $mybb->get_input('uid', MyBB::INPUT_INT);
    if ($uid <= 0) {
        echo json_encode(array('success' => false, 'error' => $lang->ms_error_invalid_user_id));
        exit;
    }

    $query = $db->simple_select(
        'users',
        'uid, username, avatar, avatardimensions, usergroup, displaygroup, usertitle, postnum, reputation, regdate, lastactive, receivepms',
        "uid='{$uid}'",
        array('limit' => 1)
    );
    $user = $db->fetch_array($query);

    if (!$user) {
        echo json_encode(array('success' => false, 'error' => $lang->ms_error_user_not_found));
        exit;
    }

    $av = format_avatar($user['avatar'], $user['avatardimensions']);
    $formatted_name = format_name(
        htmlspecialchars_uni($user['username']),
        $user['usergroup'],
        $user['displaygroup']
    );

    // User title
    $usertitle = htmlspecialchars_uni($user['usertitle']);
    if (empty($usertitle)) {
        $usertitle = get_usertitle($uid);
    }

    // Registration date
    $regdate = my_date('M Y', $user['regdate']);

    // Permissions check
    $can_pm = ($mybb->settings['enablepms'] == 1 && $mybb->user['uid'] > 0
               && $mybb->user['uid'] != $uid && $user['receivepms'] == 1);
    $can_rate = ($mybb->user['uid'] > 0 && $mybb->user['uid'] != $uid);

    $bburl = $mybb->settings['bburl'];

    echo json_encode(array(
        'success'        => true,
        'user'           => array(
            'uid'            => (int)$user['uid'],
            'username'       => htmlspecialchars_uni($user['username']),
            'formatted_name' => $formatted_name,
            'avatar'         => $av['image'],
            'usertitle'      => $usertitle,
            'postnum'        => my_number_format($user['postnum']),
            'reputation'     => my_number_format($user['reputation']),
            'regdate'        => $regdate,
            'profile_url'    => $bburl . '/' . get_profile_link($uid),
            'pm_url'         => $bburl . '/private.php?action=send&uid=' . $uid,
            'rate_url'       => $bburl . '/reputation.php?action=add&uid=' . $uid . '&pid=0',
            'can_pm'         => $can_pm,
            'can_rate'       => $can_rate,
        ),
    ));
    exit;
}
