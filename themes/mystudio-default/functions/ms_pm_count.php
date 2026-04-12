<?php
// Minimal AJAX endpoint — returns current user's unread PM count as JSON.
define('IN_MYBB', 1);
define('NO_PLUGINS', 1);
require_once __DIR__ . '/../../../global.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex');

// Guest or no session → zero
if (empty($mybb->user['uid'])) {
    echo json_encode(array('pms_unread' => 0));
    exit;
}

echo json_encode(array('pms_unread' => (int) $mybb->user['pms_unread']));
