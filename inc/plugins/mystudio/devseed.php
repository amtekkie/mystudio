<?php
/**
 * MyStudio -- Dev seed / revert helpers
 *
 * Provides two development-only utilities for the MyStudio Studio Settings page:
 *
 *   ms_devseed_seed_data()      -- wipe content and seed the board with dummy data
 *                                  (forumeze.com structure, users, threads, replies).
 *   ms_devseed_revert_default() -- wipe content and restore a default MyBB
 *                                  installation state ("My Category" / "My Forum",
 *                                  no posts, only admin users) with MyStudio intact.
 *
 * WARNING: both are destructive. They are dev tools; never run in production.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

/* ---------------------------------------------------------------- helpers */

function ms_devseed_table_cols($table)
{
    global $db;
    static $col_cache = array();
    if (!isset($col_cache[$table])) {
        $cols = array();
        $q = $db->query("SHOW COLUMNS FROM " . TABLE_PREFIX . $table);
        while ($r = $db->fetch_array($q)) { $cols[] = $r['Field']; }
        $col_cache[$table] = $cols;
    }
    return $col_cache[$table];
}

function ms_devseed_clean($v)
{
    global $db;
    return is_string($v) ? $db->escape_string($v) : $v;
}

function ms_devseed_insert($table, $data)
{
    global $db;
    $cols = array_flip(ms_devseed_table_cols($table));
    $data = array_map('ms_devseed_clean', array_intersect_key($data, $cols));
    return $db->insert_query($table, $data);
}

function ms_devseed_update($table, $data, $where)
{
    global $db;
    $cols = array_flip(ms_devseed_table_cols($table));
    $data = array_map('ms_devseed_clean', array_intersect_key($data, $cols));
    return $db->update_query($table, $data, $where);
}

function ms_devseed_pick($arr) { return $arr[array_rand($arr)]; }

function ms_devseed_rn($min, $max) { return mt_rand($min, $max); }

function ms_devseed_make_message($pool, $generic, $min, $max)
{
    shuffle($pool);
    $out = array_slice($pool, 0, ms_devseed_rn($min, min($max, count($pool))));
    if (mt_rand(0, 1)) $out[] = ms_devseed_pick($generic);
    if (mt_rand(0, 3) === 0) $out[] = ms_devseed_pick($generic);
    return implode("\n\n", array_unique($out));
}

function ms_devseed_build_parentlist($fid, $forum_pid)
{
    $chain = array($fid);
    $p = $forum_pid[$fid];
    while ($p) {
        array_unshift($chain, $p);
        $p = $forum_pid[$p];
    }
    return implode(',', $chain);
}

function ms_devseed_thread_time($NOW)
{
    // bias towards recent
    $days = mt_rand(0, 100);
    $days = (int)round($days * $days / 100); // 0..100 skewed low
    return $NOW - ms_devseed_rn(0, 90 + $days) * 86400 - ms_devseed_rn(0, 86399);
}

/* ------------------------------------------------- shared wipe + rebuilds */

/**
 * Wipe all forum content and non-admin users. Keeps uid 1 and the acting
 * admin (so the current ACP session keeps working).
 */
function ms_devseed_wipe_content()
{
    global $db, $mybb;

    $wipe_tables = array(
        'forums', 'threads', 'posts', 'polls', 'pollvotes', 'polloptions', 'announcements',
        'moderators', 'banned', 'attachments', 'threadratings', 'forumsread', 'threadsread',
        'forumsubscriptions', 'threadsubscriptions', 'reportedcontent', 'moderationqueue',
        'warnings', 'reputation', 'privatemessages', 'joinrequests',
    );
    foreach ($wipe_tables as $table) {
        if ($db->table_exists($table)) {
            $db->delete_query($table, '1=1');
        }
    }

    // Keep uid 1 and the currently logged-in admin
    $keep = array(1);
    if (!empty($mybb->user['uid'])) { $keep[] = (int)$mybb->user['uid']; }
    $keep_csv = implode(',', array_unique(array_map('intval', $keep)));

    $db->delete_query('users', "uid NOT IN ({$keep_csv})");
    if ($db->table_exists('userfields')) {
        $db->delete_query('userfields', "ufid NOT IN ({$keep_csv})");
    }
}

function ms_devseed_recount_and_rebuild($forum_pid)
{
    global $db, $cache;

    // forum counters + lastpost info
    $q = $db->query("SELECT t.fid, COUNT(t.tid) AS tc, MAX(t.lastpost) AS lp FROM " . TABLE_PREFIX . "threads t WHERE t.visible=1 GROUP BY t.fid");
    $fstats = array();
    while ($r = $db->fetch_array($q)) { $fstats[$r['fid']] = $r; }

    foreach (array_keys($forum_pid) as $fid) {
        $type_q = $db->simple_select('forums', 'type', "fid={$fid}");
        $type = $db->fetch_field($type_q, 'type');
        if ($type === 'c') { continue; }
        $s = isset($fstats[$fid]) ? $fstats[$fid] : null;
        $threads = $s ? (int)$s['tc'] : 0;
        $posts_q = $db->simple_select('posts', 'COUNT(pid) AS pc', "fid={$fid} AND visible=1");
        $posts = (int)$db->fetch_field($posts_q, 'pc');
        $upd = array('threads' => $threads, 'posts' => $posts);
        if ($s) {
            $tq = $db->simple_select('threads', 'tid, subject, lastposter, lastposteruid', "fid={$fid} AND visible=1 AND lastpost=" . (int)$s['lp'], array('limit' => 1));
            $lt = $db->fetch_array($tq);
            if ($lt) {
                $upd['lastpost'] = (int)$s['lp'];
                $upd['lastposttid'] = (int)$lt['tid'];
                $upd['lastpostsubject'] = $lt['subject'];
                $upd['lastposter'] = $lt['lastposter'];
                $upd['lastposteruid'] = (int)$lt['lastposteruid'];
            }
        }
        ms_devseed_update('forums', $upd, "fid={$fid}");
    }

    // user post/thread counts
    $uq = $db->simple_select('users', 'uid');
    while ($u = $db->fetch_array($uq)) {
        $uid = (int)$u['uid'];
        $pq = $db->simple_select('posts', 'COUNT(pid) AS pc', "uid={$uid} AND visible=1");
        $pc = (int)$db->fetch_field($pq, 'pc');
        $tq = $db->simple_select('threads', 'COUNT(tid) AS tc', "uid={$uid} AND visible=1");
        $tc = (int)$db->fetch_field($tq, 'tc');
        $db->update_query('users', array('postnum' => $pc, 'threadnum' => $tc), "uid={$uid}");
    }

    // caches
    $cache->update_forums();
    $cache->update_stats();
    $cache->update_statistics();
    $cache->update_most_replied_threads();
    $cache->update_most_viewed_threads();
    $cache->update_forumpermissions();
    $cache->update_usergroups();
}

function ms_devseed_base_forum()
{
    return array(
        'name' => '', 'description' => '', 'linkto' => '', 'type' => 'f', 'pid' => 0, 'parentlist' => '',
        'disporder' => 0, 'active' => 1, 'open' => 1,
        'threads' => 0, 'posts' => 0, 'lastpost' => 0, 'lastposter' => '', 'lastposteruid' => 0,
        'lastposttid' => 0, 'lastpostsubject' => '',
        'allowhtml' => 0, 'allowmycode' => 1, 'allowsmilies' => 1, 'allowimgcode' => 1,
        'allowvideocode' => 1, 'allowpicons' => 1, 'allowtratings' => 1,
        'usepostcounts' => 1, 'usethreadcounts' => 1, 'requireprefix' => 0,
        'password' => '', 'showinjump' => 1, 'style' => 0, 'overridestyle' => 0,
        'rulestype' => 0, 'rulestitle' => '', 'rules' => '',
        'unapprovedthreads' => 0, 'unapprovedposts' => 0, 'deletedthreads' => 0, 'deletedposts' => 0,
        'defaultdatecut' => 0, 'defaultsortby' => '', 'defaultsortorder' => '',
    );
}

/* ------------------------------------------------------------------ SEED */

/**
 * Seed the board with dummy data mirroring the forumeze.com forum structure.
 *
 * @return array Stats: users, forums, threads, posts, no_reply, empty
 */
function ms_devseed_seed_data()
{
    global $mybb, $db, $cache;

    require_once MYBB_ROOT . 'inc/datahandlers/user.php';

    $NOW = time();
    $PASSWORD = 'TestPass123!';

    ms_devseed_wipe_content();

    /* ------------------------------------------------------------- users */

    $user_defs = array(
        // username, primary gid, additional groups (csv)
        array('NimbleNora',    4, ''),    // Administrator
        array('ServerSage',    3, ''),    // Super Moderator
        array('ZenZara',       3, ''),
        array('PatchPat',      6, ''),    // Moderator (assigned to forums below)
        array('KernelKate',    6, ''),
        array('ByteBruce',     5, ''),    // Awaiting Activation
        array('QuietQuokka',   5, ''),
        array('SpammySteve',   2, '7'),   // will be banned
        array('PixelatedPaul', 2, ''),
        array('CodeWizard',    2, '3'),   // Registered + Super Mods additional
        array('RetroRex',      2, ''),
        array('GamerGhoul',    2, ''),
        array('SonicSam',      2, ''),
        array('TechTress',     2, ''),
        array('MovieMaven',    2, ''),
        array('DevDan',        2, ''),
        array('LunarLily',     2, ''),
        array('BinaryBen',     2, ''),
        array('CraftyCathy',   2, ''),
        array('EchoEzra',      2, ''),
        array('NeonNia',       2, ''),
        array('TurboTom',      2, ''),
        array('VinylVera',     2, ''),
        array('WidgetWill',    2, ''),
        array('DoodleDom',     2, ''),
        array('FluxFiona',     2, ''),
    );

    $users = array(1 => 'amtekkie'); // uid => username, keep admin fallback label
    if (!empty($mybb->user['uid']) && (int)$mybb->user['uid'] !== 1) {
        $users[(int)$mybb->user['uid']] = (string)$mybb->user['username'];
    }

    foreach ($user_defs as $def) {
        list($name, $gid, $addl) = $def;
        // skip if the acting admin happens to have this username
        if (in_array($name, $users, true)) { continue; }

        $handler = new UserDataHandler('insert');
        $handler->set_data(array(
            'username'         => $name,
            'password'         => $PASSWORD,
            'password2'        => $PASSWORD,
            'email'            => strtolower($name) . '@forumeze.test',
            'email2'           => strtolower($name) . '@forumeze.test',
            'usergroup'        => $gid,
            'additionalgroups' => $addl,
        ));
        if (!$handler->validate_user()) { continue; }
        $result = $handler->insert_user();
        $uid = isset($result['uid']) ? (int)$result['uid'] : (int)$db->insert_id();
        $users[$uid] = $name;

        // backdate registration / activity
        $db->update_query('users', array(
            'regdate'    => $NOW - ms_devseed_rn(10, 400) * 86400,
            'lastactive' => ms_devseed_rn(0, 4) ? $NOW - ms_devseed_rn(0, 30) * 86400 : $NOW - ms_devseed_rn(31, 200) * 86400,
            'lastvisit'  => $NOW - ms_devseed_rn(0, 60) * 86400,
        ), "uid={$uid}");
    }

    // Ban SpammySteve
    $banned_uid = array_search('SpammySteve', $users, true);
    if ($banned_uid) {
        $db->update_query('users', array('usergroup' => 7), "uid={$banned_uid}");
        ms_devseed_insert('banned', array(
            'uid' => $banned_uid, 'gid' => 7, 'oldgroup' => 2, 'oldadditionalgroups' => '',
            'olddisplaygroup' => 0, 'admin' => 1, 'dateline' => $NOW - 86400 * 5,
            'bantime' => '---', 'lifted' => 0, 'reason' => 'Drive-by spamming',
        ));
    }

    /* ------------------------------------------------------------ forums */

    // name => [desc, subs([name => desc]), empty]
    $structure = array(
        'Front Desk' => array('desc' => 'Start here — rules, announcements and your first posts.', 'forums' => array(
            'Rules & Announcements' => array('desc' => 'Forum rules, official announcements and important notices.'),
            'Introduction & Feedback' => array('desc' => 'New here? Introduce yourself and tell us what you think of the place.'),
        )),
        'Products & Services' => array('desc' => 'Everything about our products and the platforms we support.', 'forums' => array(
            'MyBB' => array('desc' => 'General discussion about MyBB forum software.', 'subs' => array(
                'Themes' => 'Share, request and discuss MyBB themes.',
                'Plugins' => 'Share, request and discuss MyBB plugins.',
            )),
            'WordPress' => array('desc' => 'General discussion about WordPress.', 'subs' => array(
                'Themes' => 'WordPress theme talk — releases, customisations, requests.',
                'Plugins' => 'WordPress plugin talk — must-haves, reviews, requests.',
            )),
        )),
        'Webmaster' => array('desc' => 'Running a website: build it, grow it, monetise it.', 'forums' => array(
            'Website Management' => array('desc' => 'Hosting, domains, uptime, backups and day-to-day site administration.'),
            'Design & Development' => array('desc' => 'Front-end design, back-end code and everything in between.'),
            'Marketing & Monetization' => array('desc' => 'SEO, social media, ads and making your site pay for itself.'),
            'Collaboration & Services' => array('desc' => 'Offer your skills, hire talent or find a partner for your project.', 'empty' => true),
            'Website Showcase' => array('desc' => 'Show off your website and get honest feedback.'),
        )),
        'Tech & Games' => array('desc' => 'Hardware, gadgets and the games we play on them.', 'forums' => array(
            'Personal Computers' => array('desc' => 'PC builds, upgrades, benchmarks and troubleshooting.'),
            'Servers, Networking' => array('desc' => 'Servers, hosting stacks, routers, NAS and home labs.'),
            'Mobiles, Handhelds & Wearables' => array('desc' => 'Phones, tablets, handheld consoles, smart watches and other wearables.'),
            'Gaming Discussions' => array('desc' => 'News, reviews and chatter about games on every platform.'),
        )),
        'Off Topic' => array('desc' => 'Anything that does not fit anywhere else.', 'forums' => array(
            'Chit-Chat' => array('desc' => 'Random talk about pretty much anything.'),
            'Movies, TV, Music & Videos' => array('desc' => 'What are you watching or listening to?'),
        )),
    );

    $base_forum = ms_devseed_base_forum();

    $fid_map = array();   // key => fid
    $forum_pid = array(); // fid => pid
    $empty_forums = array();
    $cat_disp = 1;

    foreach ($structure as $cat => $cdata) {
        $cid = ms_devseed_insert('forums', array_merge($base_forum, array(
            'name' => $cat, 'description' => $cdata['desc'], 'type' => 'c',
            'disporder' => $cat_disp++,
            'usepostcounts' => 0, 'usethreadcounts' => 0,
        )));
        $forum_pid[$cid] = 0;
        $fid_map[$cat] = $cid;
        $disp = 1;
        foreach ($cdata['forums'] as $fname => $fdata) {
            $fid = ms_devseed_insert('forums', array_merge($base_forum, array(
                'name' => $fname, 'description' => $fdata['desc'], 'pid' => $cid, 'disporder' => $disp++,
            )));
            $fid_map[$fname] = $fid;
            $forum_pid[$fid] = $cid;
            if (!empty($fdata['empty'])) { $empty_forums[] = $fid; }
            $sub_disp = 1;
            if (!empty($fdata['subs'])) {
                foreach ($fdata['subs'] as $sname => $sdesc) {
                    $sub_key = $fname . ' / ' . $sname;
                    $sfid = ms_devseed_insert('forums', array_merge($base_forum, array(
                        'name' => $sname, 'description' => $sdesc, 'pid' => $fid, 'disporder' => $sub_disp++,
                    )));
                    $fid_map[$sub_key] = $sfid;
                    $forum_pid[$sfid] = $fid;
                }
            }
        }
    }

    // parentlist = "grandcat,cat,fid" order
    foreach ($forum_pid as $fid => $pid) {
        ms_devseed_update('forums', array('parentlist' => ms_devseed_build_parentlist($fid, $forum_pid)), "fid={$fid}");
    }

    // moderator assignments
    $mod_forums = array(
        'PatchPat' => array('Rules & Announcements', 'Introduction & Feedback', 'Website Showcase'),
        'KernelKate' => array('Gaming Discussions', 'Chit-Chat', 'Movies, TV, Music & Videos'),
    );
    foreach ($mod_forums as $uname => $flist) {
        $uid = array_search($uname, $users, true);
        if (!$uid) { continue; }
        foreach ($flist as $fname) {
            if (!isset($fid_map[$fname])) { continue; }
            ms_devseed_insert('moderators', array(
                'fid' => $fid_map[$fname], 'id' => $uid, 'isgroup' => 0,
                'caneditposts' => 1, 'cansoftdeleteposts' => 1, 'canrestoreposts' => 1,
                'candeleteposts' => 1, 'cansoftdeletethreads' => 1, 'canrestorethreads' => 1,
                'candeletethreads' => 1, 'canviewips' => 1, 'canviewunapprove' => 1,
                'canviewdeleted' => 1, 'canopenclosethreads' => 1, 'canstickunstickthreads' => 1,
                'canapproveunapprovethreads' => 1, 'canapproveunapproveposts' => 1,
                'canapproveunapproveattachs' => 1, 'canmanagethreads' => 1, 'canmanagepolls' => 1,
                'canpostannouncements' => 1, 'canmanagereportedcontent' => 1, 'canviewmodlog' => 1,
            ));
        }
    }
    $cache->update_moderators();

    /* -------------------------------------------------------- content pools */

    $generic_first = array(
        'I did a bit of searching but could not find a recent thread on this, so here we go.',
        'Curious what everyone else here thinks about it.',
        'Would love to hear how you all handle this.',
        'Maybe this has been discussed before, but I could not find anything recent.',
        'Any tips, experiences or strong opinions welcome.',
    );
    $generic_reply = array(
        'Good point, I had not thought of it that way.',
        'That has been my experience as well.',
        'Interesting — I will have to try that.',
        'Thanks for sharing, saved me a lot of time.',
        'I disagree slightly, but I can see where you are coming from.',
        'Bumping this because I ran into the same thing today.',
        'Nice thread, following for the replies.',
        'Could not agree more.',
    );

    // forum name (or "Parent / Sub") => [titles[], firstpost sentences[], reply sentences[]]
    $content = array(
        'Rules & Announcements' => array(
            't' => array('Welcome to Forumeze!', 'Forum rules — please read before posting', 'Scheduled maintenance window this weekend', 'New theme went live today', 'How to report posts and contact staff', 'Avatar and signature guidelines', 'Double posting and thread bumping policy', 'We hit 100 members — thank you!', 'Search tips: find answers faster', 'Private message etiquette'),
            'f' => array('Welcome aboard! Forumeze is a general-purpose community for webmasters, gamers and tinkerers.', 'Please take a minute to read the rules — they are short and mostly common sense.', 'We ask everyone to keep discussions civil and on-topic.', 'Use the report button rather than back-seat moderating.', 'Signatures may contain one link and must stay under three lines.'),
            'r' => array('Welcome, glad to be here!', 'Rules look fair to me.', 'Nice to see a clearly written ruleset for once.', 'Thanks for the heads-up on the maintenance window.', 'The new theme looks clean, good job.'),
        ),
        'Introduction & Feedback' => array(
            't' => array('Hello from a longtime lurker', 'New member checking in', 'Feedback: the dark theme is great', 'Suggestion: a dedicated marketplace section', 'Hi all — webmaster from Norway', 'Just joined, here for the MyBB content', 'My first impressions of the forum', 'Suggestion: dark/light toggle button', 'Hello everyone!', 'Feedback on the mobile layout', 'Returning after a long break', 'Intro: sysadmin and hobbyist gamer'),
            'f' => array('I have been lurking for a few weeks and finally made an account.', 'I run a small blog and a MyBB forum of my own, so this place is right up my alley.', 'Overall the forum feels fast and the layout is easy on the eyes.', 'One small suggestion: a toggle between light and dark mode would be lovely.', 'Looking forward to learning from everyone here.'),
            'r' => array('Welcome aboard!', 'Great to have you here.', 'We have all been the new person — ask away.', 'Good suggestion, I will pass it on.', 'Welcome! What kind of sites do you run?'),
        ),
        'MyBB' => array(
            't' => array('Upgrading from 1.8.33 to 1.8.38 — anything to watch out for?', 'Best practice for backing up a MyBB forum', 'MyBB vs phpBB in 2026', 'How do you handle spam registrations?', 'Moving MyBB to a new host checklist', 'Recommended PHP version for MyBB 1.8', 'Using Redis for the MyBB cache store', 'Merge system experiences?', 'SEO-friendly URLs worth enabling?', 'MyBB task system not running — cron workaround', 'Custom profile fields: add to postbit?', 'Mass mail to all members safely', 'Cleaning up old attachments'),
            'f' => array('[code=php]define(\'IN_MYBB\', 1);[/code] — always check for this guard in your own plugin files.', 'Make sure you take both a files and a database backup before upgrading.', 'The upgrade script is reliable but a backup costs you nothing.', 'I switched the cache store to Redis and the ACP feels snappier now.', 'Spam registrations dropped to nearly zero after enabling a good Q&A challenge.'),
            'r' => array('The upgrade went smoothly for me, just re-check your templates afterwards.', 'Do not forget to disable plugins before the upgrade.', 'Cron-based task runs fixed my missed backups as well.', 'Redis works great, but file cache is fine for small boards.', 'Q&A captcha beat every image captcha I tried.'),
        ),
        'MyBB / Themes' => array(
            't' => array('MyStudio Default theme — first impressions', 'Editing templates on disk vs in the database', 'Released: minimal dark theme for 1.8', 'How to make theme CSS cache-bust correctly', 'Bootstrap 5 in a MyBB theme — pitfalls', 'Where does global.css end up after sync?', 'Showcase: my custom gaming theme', 'Making the postbit responsive', 'Font pairing suggestions for forums', 'How to add a theme footer credit line', 'Template conditionals — which plugin?', 'Converting an old 1.6 theme'),
            'f' => array('I have been rebuilding my forum theme with a file-based workflow and it is so much nicer than clicking through the ACP.', 'Keeping templates in Git finally lets me diff changes sanely.', 'Remember to hard-refresh after syncing, the CSS cache is aggressive.', 'Bootstrap 5 works fine inside MyBB as long as you namespace your overrides.', 'A lot of old 1.6-era themes only need template tweaks to render correctly.'),
            'r' => array('The on-disk workflow changed how I build themes completely.', 'Try the incremental sync, it only touches changed templates.', 'I namespace everything under a .mybb wrapper class to avoid collisions.', 'Responsive postbit is doable with a couple of flex rules.', 'Great release, installed it on my test board.'),
        ),
        'MyBB / Plugins' => array(
            't' => array('Must-have plugins for a new MyBB forum', 'Writing your first plugin: hooks explained', 'Plugin settings with gid=0 — hidden settings trick', 'Is there a working likes/thanks plugin?', 'Plugin compatibility with PHP 8.3', 'How to debug a plugin that whitescreens', 'Released: simple shoutbox plugin', 'Datahandlers vs raw queries in plugins', 'Plugin that adds user awards?', 'AJAX in plugins via xmlhttp.php', 'Uninstall routines — what should they clean up?', 'Rate limiting a plugin endpoint'),
            'f' => array('Hooks are the heart of MyBB plugins — [code=php]$plugins->add_hook(\'pre_output_page\', \'my_func\');[/code] is where everything starts.', 'Remember: _activate() should be reversible, _install() owns permanent structures.', 'Returning true from a by-value hook replaces the argument; by-reference hooks mutate in place — check the call site.', 'I keep a list of essential plugins and thought I would share it here.', 'xmlhttp.php is the cleanest way to add AJAX endpoints to MyBB.'),
            'r' => array('That hooks explanation finally made it click for me.', 'Always call rebuild_settings() after changing settings programmatically.', 'The gid=0 trick keeps plugin settings out of the ACP — neat.', 'Whitelist-based hooks debugging in class_plugins.php helped me a lot.', 'Nice plugin, testing it on my dev board now.'),
        ),
        'WordPress' => array(
            't' => array('WordPress 6.7 — worth upgrading yet?', 'Classic editor vs block editor in 2026', 'How many plugins is too many?', 'Managed WP hosting vs plain VPS', 'Fixing the white screen of death', 'Child themes: still the right way?', 'Moving WP to a new domain without losing SEO', 'WP-Cron is unreliable — real cron setup', 'Hardening wp-login.php', 'Best backup strategy for WooCommerce', 'Headless WordPress experiences?', 'Database cleanup: transients and revisions'),
            'f' => array('After juggling a dozen WP sites for clients, I have opinions on pretty much everything WordPress.', 'My rule of thumb: if a plugin has not been updated in a year, it is a liability.', 'wp-cron only fires on page views, so low-traffic sites should switch to system cron.', 'Child themes are still the correct way to override a theme you do not control.', 'The block editor is fine now, but I still install Classic Editor on client sites.'),
            'r' => array('System cron fixed my missed scheduled posts too.', 'I cap client sites at 15 plugins, anything above that needs justification.', 'Managed hosting saves me enough time to be worth the markup.', 'Transient cleanup shaved 200MB off one of my databases.', 'Headless WP is fun but a lot of moving parts for a blog.'),
        ),
        'WordPress / Themes' => array(
            't' => array('Best lightweight WP themes this year', 'Block themes vs classic themes', 'GeneratePress vs Astra — which one?', 'Building a theme from scratch: starter templates', 'Customising WooCommerce templates', 'Where do you buy premium themes?', 'Full-site editing — ready for clients?', 'Dark mode support in WP themes', 'Removing unused theme bloat', 'Theme translation with .po files'),
            'f' => array('I have been test-driving lightweight themes and the speed difference is dramatic.', 'Full-site editing is powerful but clients keep breaking their own layouts with it.', 'My current stack is GeneratePress plus a small custom plugin for tweaks.', 'Bought a theme lately? Share where — marketplaces vary wildly in quality.', 'Dark mode via prefers-color-scheme is easier than I expected.'),
            'r' => array('GeneratePress + hooks is my go-to as well.', 'Astra’s starter templates saved me days on a client job.', 'I disable FSE for clients with a one-liner and never look back.', 'prefers-color-scheme plus a manual toggle covers everyone.', 'Agreed, keep the parent theme untouched and override in the child.'),
        ),
        'WordPress / Plugins' => array(
            't' => array('Plugins I install on every single WP site', 'Security plugin recommendations?', 'Page builders: still worth it?', 'Caching plugins compared', 'Contact form plugins that do not suck', 'Image optimisation plugin showdown', 'Membership plugins — experiences?', 'SMTP plugin or transactional email service?', 'SEO plugin: Yoast vs Rank Math', 'Redirect manager recommendations'),
            'f' => array('Every build of mine starts with the same six plugins and grows from there.', 'Caching is the single biggest performance win on cheap shared hosting.', 'I stopped using page builders once the block editor matured.', 'Never let WordPress send mail natively on a VPS — use SMTP or a transactional service.', 'Rank Math won me over after Yoast nagged me one time too many.'),
            'r' => array('My baseline list is almost identical to yours.', 'Cloudflare plus a light cache plugin is enough for most small sites.', 'SMTP with a transactional service fixed all my contact-form complaints.', 'Rank Math here too — the schema options alone are worth it.', 'Page builders still have their place for landing pages, just not whole sites.'),
        ),
        'Website Management' => array(
            't' => array('Uptime monitoring — what do you use?', 'How often do you actually test your backups?', 'Shared hosting to VPS migration story', 'Registrar recommendations in 2026', 'Let\'s Encrypt automation setups', 'Managing client sites from one dashboard', 'CDN for a small forum — overkill?', 'DNS propagation horror stories', 'Staging sites: how do you do them?', 'Decommissioning an old server gracefully', 'Log rotation before the disk fills up'),
            'f' => array('Learned the hard way that an untested backup is a Schrödinger backup.', 'Moving from shared hosting to a VPS was the best decision for my sites.', 'I put every site behind the same free CDN tier and forgot about DDoS worries.', 'My monitoring setup alerts me within a minute of downtime and costs nothing.', 'Staging is just a subdomain plus a database copy — nothing fancy, works fine.'),
            'r' => array('Test restores monthly — it has saved me twice now.', 'Free CDN tier is more than enough for a forum.', 'Same registrar here, never had a problem in six years.', 'certbot with the nginx plugin has been flawless for me.', 'A simple rsync cron is my entire backup strategy and I sleep fine.'),
        ),
        'Design & Development' => array(
            't' => array('Tailwind vs Bootstrap for a new project', 'Designing in the browser — still a thing?', 'Flexbox patterns I use constantly', 'Dark mode theming with CSS variables', 'Figma plugins worth installing', 'Accessible dropdown menus without JS', 'CSS masonry layout options in 2026', 'SVG icon systems compared', 'Container queries in production yet?', 'My VS Code extension list', 'Refactoring legacy CSS without breaking everything', 'CSS :has() use cases you actually use'),
            'f' => array('[code]:root { --accent: #4f7cff; }[/code] — CSS variables made my theming life so much easier.', 'I design in the browser almost exclusively now; mockups only for client sign-off.', ':has() finally lets me style parents without JavaScript, and support is good now.', 'Container queries changed how I build card layouts — the component adapts to its slot, not the viewport.', 'Dark mode is a one-liner media query once your colours live in variables.'),
            'r' => array('CSS variables + one class toggle is my whole dark mode stack.', ':has() feels like cheating compared to what we used to do.', 'I still reach for Bootstrap when a client needs to maintain it afterwards.', 'Container queries are great but I still add a media-query fallback.', 'Refactoring legacy CSS one component at a time is the only sane way.'),
        ),
        'Marketing & Monetization' => array(
            't' => array('Adsense alternatives that actually pay', 'SEO wins that took under an hour', 'Growing a forum from zero members', 'Email list building for forum owners', 'Affiliate links without being spammy', 'Site speed as an SEO factor — real results', 'Social media that actually drives forum traffic', 'Sponsored posts: what to charge?', 'Core Web Vitals checklist', 'Newsletter vs RSS for community updates', 'Analytics without Google'),
            'f' => array('The hardest part of a new forum is the first hundred real members — everything before that is setup.', 'My best-performing affiliate content is honest comparisons, not listicles.', 'Site speed moved my average position more than any backlink campaign ever did.', 'I ditched GA for a lightweight self-hosted analytics tool and lost nothing I actually used.', '[list][*]Speed first[*]Content people search for[*]Consistent posting[/list] — my entire SEO strategy.'),
            'r' => array('The empty-room problem is real — seed quality discussions early.', 'Comparisons convert way better than reviews for me too.', 'Core Web Vitals compliance took me a weekend and showed results in a month.', 'Self-hosted analytics is so much calmer to work with.', 'Charging flat monthly for sponsorships worked better than CPM for my niche.'),
        ),
        'Website Showcase' => array(
            't' => array('Showcase: my retro gaming blog', 'Rate my portfolio site redesign', 'Showcase: recipe site built on WP', 'Feedback wanted: SaaS landing page', 'My MyBB forum is finally live', 'Showcase: local business directory', 'Redesigned my blog — before/after screenshots', 'Showcase: photography portfolio', 'Roast my homepage copy', 'New side project: price tracker', 'Showcase: tech tutorial channel hub'),
            'f' => array('After six months of evenings and weekends, it is finally ready to show people.', 'Be brutal — I would rather hear it here than from a client.', 'Everything is custom except the CMS, and the CSS is all variables so theming is trivial.', 'Traffic is tiny so far but the feedback from early users has been great.', 'I rebuilt the whole front page based on what users actually clicked.'),
            'r' => array('Clean design — the whitespace does a lot of work.', 'The hero section loads fast, nice job.', 'One nit: the contrast on the footer links is a bit low.', 'Impressive for a solo build.', 'Your copy is good, the call-to-action could be higher up.'),
        ),
        'Personal Computers' => array(
            't' => array('Show me your battlestations', 'GPU upgrade: is now a good time?', 'Air cooling vs AIO for a quiet build', 'Windows 11 debloating — what do you remove?', 'SFF build log: 10 litres of power', 'RAM speed — does it actually matter?', 'Best budget mechanical keyboards', 'Thermal paste application methods', 'Undervolting results: temps vs performance', 'How long do your SSDs actually last?', 'Linux as a daily driver in 2026', 'Monitor recommendations: 1440p high refresh'),
            'f' => array('Finally finished the rebuild after months of collecting parts one sale at a time.', 'My old GPU still holds up, but the new titles are starting to push it.', 'Went from air to an AIO and the noise difference at load is night and day.', 'I debloat Windows with a small script and a restore point — never had an issue.', 'Undervolting dropped my temps 10°C with zero performance loss.'),
            'r' => array('Clean cable management in that build.', '1440p 165Hz is the sweet spot, agree completely.', 'RAM speed matters more on Ryzen than Intel, in my experience.', 'My NVMe is five years old at 89% health — they last longer than people fear.', 'Linux daily drives my work machine; games stay on the Windows box.'),
        ),
        'Servers, Networking' => array(
            't' => array('Home lab: what are you running?', 'Reverse proxy: nginx vs Caddy vs Traefik', 'Pi-hole still worth it with DoH everywhere?', 'Self-hosting email in 2026 — brave or foolish?', 'NAS builds: TrueNAS vs Unraid', '10GbE home networking on a budget', 'WireGuard setup notes', 'Docker vs LXC for home services', 'Monitoring your home lab', 'ISP upgrade: from 100/20 to gigabit', 'VLANs for isolating IoT devices'),
            'f' => array('My little home lab started as a Pi and is now a full rack shelf — send help.', 'Caddy won me over with automatic HTTPS; nginx when I need fine control.', 'WireGuard took ten minutes to set up and replaced every port-forward I had.', 'IoT devices live on their own VLAN with no internet access and I sleep better.', 'Self-hosted email is the one thing I refuse to take on — deliverability is a minefield.'),
            'r' => array('Caddy’s config reads like a postcard compared to nginx.', 'Unraid for mixed workloads, TrueNAS if storage is the priority.', 'The VLAN isolation tip is underrated.', 'I monitor with just uptime-kuma and it covers 90% of my needs.', 'Gigabit spoiled me — I can never go back.'),
        ),
        'Mobiles, Handhelds & Wearables' => array(
            't' => array('Flagship phone or midrange + upgrade more often?', 'Handheld PC gaming — which one did you pick?', 'Smart watch battery life thread', 'Android launchers you still use', 'Tablets: laptop replacement yet?', 'Best wireless earbuds under 100', 'E-ink readers — worth it?', 'Phone photography tips', 'Custom ROMs in 2026 — still a thing?', 'Fitness trackers: accuracy experiments', 'Foldables — owners, how is it holding up?'),
            'f' => array('After years of flagships I switched to a midrange phone and honestly barely notice the difference.', 'Handheld PCs turned my commute into actual gaming time instead of doom-scrolling.', 'My e-ink reader gets more use than my tablet — the battery lasts weeks.', 'Smart watch battery is my deciding factor now; everything else is good enough.', 'Mobile photo quality is 90% lighting and 10% sensor, fight me.'),
            'r' => array('Midrange + faster upgrade cycle is the best value play, agreed.', 'The handheld I picked has replaced my console entirely.', 'Two-week battery or nothing — daily charging killed watches for me.', 'Custom ROMs gave my old phone three extra years of life.', 'Lighting comment is painfully accurate.'),
        ),
        'Gaming Discussions' => array(
            't' => array('What are you playing right now?', 'Your most replayed games of all time', 'Backlog confessions', 'Best indie games this year', 'Single player games with the best stories', 'Games you love that everyone else hated', 'Retro collecting — latest pickups', 'Controller vs mouse and keyboard', 'Best co-op games for two players', 'Game soundtracks worth listening to outside the game', 'Difficulty settings: who here plays on easy?', 'Anticipated releases this year', 'Mods that turned a good game into a great one', 'Local co-op gems', 'The game that got you into gaming'),
            'f' => array('My backlog is a museum of good intentions at this point.', 'I have replayed this game six times and I will absolutely do it again.', 'Some of my favourite gaming memories are janky co-op sessions with one friend and a shared pizza.', 'Soundtrack vinyl collecting is my side hobby — game OSTs are incredible value.', 'I play almost everything on easy now and enjoy games far more than when I chased achievements.'),
            'r' => array('Adding half of these to the backlog, thanks a lot.', 'That game deserved better reviews, it was ahead of its time.', 'Co-op pick is perfect for two players, we finished it twice.', 'The soundtrack of that game lives permanently in my playlist.', 'No shame in easy mode — the story is the point.'),
        ),
        'Chit-Chat' => array(
            't' => array('How do you take your coffee?', 'What did you have for lunch today?', 'Weekend plans thread', 'Your unpopular opinion', 'Emoji you use the most', 'Morning person or night owl?', 'What is your most-used app?', 'Small wins thread — share yours', 'Desk setup photos', 'Languages you speak', 'Your first ever username', 'Best purchase under 50 this year'),
            'f' => array('Caffeine thread because Friday mornings require teamwork.', 'I will start: reheated pasta and zero regrets.', 'Mine is simple — sleeping until noon and not feeling guilty about it.', 'Go on, share yours. Rules: no politics, keep it fun.', 'This is a judgement-free zone. Mostly.'),
            'r' => array('Black, no sugar. We are not the same.', 'Night owl for life, mornings are a scam.', 'This thread is the wholesomeness I needed today.', 'Respectfully disagree but I will hear you out.', 'Mine is embarrassing enough that it stays in the drafts.'),
        ),
        'Movies, TV, Music & Videos' => array(
            't' => array('What are you watching this week?', 'Movies you have seen more than five times', 'Albums you can play front to back', 'Cancelled shows that deserved better', 'Concert experiences — best live act you have seen', 'Documentaries that changed your mind', 'Your comfort rewatch series', 'Vinyl collection photos', 'YouTube channels you actually subscribe to', 'Movie night picks for this weekend', 'Underrated soundtracks', 'Podcasts you binge'),
            'f' => array('Started three series this month and finished none of them — a new personal record.', 'There is a special category of album that just works front to back, no skips.', 'The cancellation still stings years later.', 'Live music is one of the few things I will happily overspend on.', 'My vinyl shelf is small but every record earns its place.'),
            'r' => array('That documentary absolutely changed how I think about it too.', 'Front-to-back albums are getting rare, good call.', 'Still salty about that cancellation.', 'Great pick for movie night, adding it to the list.', 'Your shelf has taste.'),
        ),
    );

    /* ------------------------------------------------------------ generate */

    $author_pool = array_keys($users); // includes admin uid 1

    $quote_chance = 20;
    $closed_chance = 6;
    $tot_threads = 0; $tot_posts = 0; $no_reply = 0;
    $poll_done = false;

    foreach ($fid_map as $key => $fid) {
        if ($forum_pid[$fid] === 0) { continue; } // categories
        if (in_array($fid, $empty_forums)) { continue; }
        if (!isset($content[$key])) { continue; }

        $pool = $content[$key];
        $titles = $pool['t'];
        shuffle($titles);
        $n_threads = min(ms_devseed_rn(10, 18), count($titles));

        // guarantee the poll thread lands in the draw for Chit-Chat
        if (!$poll_done && $key === 'Chit-Chat') {
            $pos = array_search('How do you take your coffee?', $titles, true);
            if ($pos !== false && $pos >= $n_threads) {
                $tmp = $titles[0];
                $titles[0] = $titles[$pos];
                $titles[$pos] = $tmp;
            }
        }

        for ($i = 0; $i < $n_threads; $i++) {
            $subject = $titles[$i];
            $uid = ms_devseed_pick($author_pool);
            $username = $users[$uid];
            $tstart = ms_devseed_thread_time($NOW);
            $first_msg = ms_devseed_make_message($pool['f'], $generic_first, 2, 4);

            // insert thread (firstpost fixed after post insert)
            $tid = ms_devseed_insert('threads', array(
                'fid' => $fid, 'subject' => $subject, 'prefix' => 0, 'icon' => 0, 'poll' => 0,
                'uid' => $uid, 'username' => $username, 'dateline' => $tstart, 'firstpost' => 0,
                'lastpost' => $tstart, 'lastposter' => $username, 'lastposteruid' => $uid,
                'views' => ms_devseed_rn(15, 2400), 'replies' => 0,
                'closed' => (mt_rand(1, 100) <= $closed_chance ? '1' : ''),
                'sticky' => ($i === 0 && isset($fid_map['Rules & Announcements']) && $fid === $fid_map['Rules & Announcements'] ? 1 : (mt_rand(1, 100) <= 8 ? 1 : 0)),
                'numratings' => 0, 'totalratings' => 0, 'notes' => '', 'visible' => 1,
                'unapprovedposts' => 0, 'deletedposts' => 0, 'attachmentcount' => 0, 'deletetime' => 0,
            ));

            $ip = function_exists('my_inet_pton') ? my_inet_pton('127.0.0.1') : '';
            $fpid = ms_devseed_insert('posts', array(
                'tid' => $tid, 'replyto' => 0, 'fid' => $fid, 'subject' => $subject, 'icon' => 0,
                'uid' => $uid, 'username' => $username, 'dateline' => $tstart, 'message' => $first_msg,
                'ipaddress' => $ip, 'includesig' => 1, 'smilieoff' => 0,
                'edituid' => 0, 'edittime' => 0, 'editreason' => '', 'visible' => 1,
            ));
            $db->update_query('threads', array('firstpost' => $fpid), "tid={$tid}");

            $tot_threads++; $tot_posts++;

            // replies: 0-8, but ~14% of threads left without any reply
            $n_replies = (mt_rand(1, 100) <= 14) ? 0 : ms_devseed_rn(1, 8);
            if ($n_replies === 0) { $no_reply++; }
            $prev_time = $tstart;
            $prev_post = array('pid' => $fpid, 'uid' => $uid, 'username' => $username, 'message' => $first_msg, 'dateline' => $tstart);

            for ($r = 0; $r < $n_replies; $r++) {
                $ruid = ms_devseed_pick($author_pool);
                $rname = $users[$ruid];
                $rtime = min($NOW, $prev_time + ms_devseed_rn(600, 86400 * 2));

                $msg = ms_devseed_make_message($pool['r'], $generic_reply, 1, 2);

                // occasionally quote the previous post (great for theme testing)
                if (mt_rand(1, 100) <= $quote_chance && $prev_post) {
                    $quoted = strtok($prev_post['message'], "\n");
                    if (strlen($quoted) > 160) { $quoted = substr($quoted, 0, 157) . '...'; }
                    $msg = "[quote='{$prev_post['username']}' pid='{$prev_post['pid']}' dateline='{$prev_post['dateline']}']" . $quoted . "[/quote]\n" . $msg;
                }

                $pid2 = ms_devseed_insert('posts', array(
                    'tid' => $tid, 'replyto' => $prev_post['pid'], 'fid' => $fid, 'subject' => '', 'icon' => 0,
                    'uid' => $ruid, 'username' => $rname, 'dateline' => $rtime, 'message' => $msg,
                    'ipaddress' => $ip, 'includesig' => 1, 'smilieoff' => 0,
                    'edituid' => 0, 'edittime' => 0, 'editreason' => '', 'visible' => 1,
                ));
                $tot_posts++;
                $prev_post = array('pid' => $pid2, 'uid' => $ruid, 'username' => $rname, 'message' => $msg, 'dateline' => $rtime);
                $prev_time = $rtime;
            }

            ms_devseed_update('threads', array(
                'replies' => $n_replies,
                'lastpost' => $prev_time,
                'lastposter' => $prev_post['username'],
                'lastposteruid' => $prev_post['uid'],
            ), "tid={$tid}");

            // one poll thread in Chit-Chat
            if (!$poll_done && isset($fid_map['Chit-Chat']) && $fid === $fid_map['Chit-Chat'] && $subject === 'How do you take your coffee?') {
                $opts = array('Black, no sugar', 'Milk and sugar', 'Espresso only', 'Tea, actually');
                $votes = array();
                $total = 0;
                foreach ($opts as $o) { $v = ms_devseed_rn(1, 12); $votes[] = $v; $total += $v; }
                $pollid = ms_devseed_insert('polls', array(
                    'tid' => $tid, 'question' => $subject, 'dateline' => $tstart,
                    'options' => serialize($opts), 'votes' => serialize(array_map('strval', $votes)),
                    'numoptions' => count($opts), 'numvotes' => $total, 'timeout' => 0,
                    'closed' => 0, 'multiple' => 0, 'public' => 0, 'maxoptions' => 0,
                ));
                $db->update_query('threads', array('poll' => $pollid), "tid={$tid}");
                $poll_done = true;
            }
        }
    }

    /* ------------------------------------------------------ announcements */

    ms_devseed_insert('announcements', array(
        'fid' => -1, 'uid' => 1, 'subject' => 'Welcome to Forumeze!',
        'message' => "Welcome to the new Forumeze community.\n\nPlease take a moment to read the forum rules and introduce yourself in the Introduction & Feedback forum.",
        'startdate' => $NOW - 30 * 86400, 'enddate' => 0, 'allowhtml' => 0, 'allowmycode' => 1, 'allowsmilies' => 1,
    ));
    ms_devseed_insert('announcements', array(
        'fid' => -1, 'uid' => array_search('NimbleNora', $users, true) ?: 1, 'subject' => 'Scheduled maintenance this weekend',
        'message' => "The forum will be briefly offline this Saturday between 02:00 and 04:00 UTC for server maintenance.\n\nSorry for any inconvenience!",
        'startdate' => $NOW - 3 * 86400, 'enddate' => $NOW + 7 * 86400,
        'allowhtml' => 0, 'allowmycode' => 1, 'allowsmilies' => 1,
    ));

    /* ---------------------------------------------------- recount & rebuild */

    ms_devseed_recount_and_rebuild($forum_pid);

    return array(
        'users'   => count($users) - 1,
        'forums'  => count($forum_pid),
        'threads' => $tot_threads,
        'posts'   => $tot_posts,
        'no_reply' => $no_reply,
        'empty'   => count($empty_forums),
    );
}

/* ---------------------------------------------------------------- REVERT */

/**
 * Wipe all content and restore a default MyBB installation state:
 * one "My Category" containing one "My Forum", no threads/posts,
 * no non-admin users. MyStudio plugin and settings are left untouched.
 */
function ms_devseed_revert_default()
{
    global $db;

    ms_devseed_wipe_content();

    // reset admin counters (uid 1 + any other surviving admin)
    $db->update_query('users', array('postnum' => 0, 'threadnum' => 0));

    $base_forum = ms_devseed_base_forum();

    $cid = ms_devseed_insert('forums', array_merge($base_forum, array(
        'name' => 'My Category', 'type' => 'c', 'disporder' => 1,
        'usepostcounts' => 0, 'usethreadcounts' => 0,
    )));
    ms_devseed_update('forums', array('parentlist' => (string)$cid), "fid={$cid}");

    $fid = ms_devseed_insert('forums', array_merge($base_forum, array(
        'name' => 'My Forum', 'description' => '', 'pid' => $cid, 'disporder' => 1,
    )));
    ms_devseed_update('forums', array('parentlist' => $cid . ',' . $fid), "fid={$fid}");

    ms_devseed_recount_and_rebuild(array($cid => 0, $fid => $cid));
}
