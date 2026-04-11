<?php
/**
 * MyStudio Posting Extras — Options Definition
 *
 * @return array
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

// Build dynamic forum list for select dropdown
global $cache;
$forumCache = $cache->read('forums');
$forumOptions = array('' => '— Select Forum —');
if (is_array($forumCache)) {
    foreach ($forumCache as $fid => $f) {
        if (!isset($f['type']) || $f['type'] !== 'f') continue;
        $catName = '';
        $pid = isset($f['pid']) ? (int)$f['pid'] : 0;
        if ($pid > 0 && isset($forumCache[$pid]) && $forumCache[$pid]['type'] === 'c') {
            $catName = $forumCache[$pid]['name'] . ' › ';
        }
        $forumOptions[(string)$fid] = $catName . $f['name'];
    }
}

return array(
    array(
        'id'          => 'default_forum',
        'label'       => 'Default Forum for Status Posts',
        'description' => 'Select the forum where quick status posts will be created as threads. Users can also choose a different forum when posting.',
        'type'        => 'select',
        'options'     => $forumOptions,
        'default'     => '',
    ),
    array(
        'id'          => 'posts_per_page',
        'label'       => 'Posts Per Page',
        'description' => 'Number of threads to display per page in the feed.',
        'type'        => 'numeric',
        'default'     => '20',
    ),
);
