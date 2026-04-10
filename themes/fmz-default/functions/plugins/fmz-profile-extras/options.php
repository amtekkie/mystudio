<?php
/**
 * FMZ User Profile Extras — Options Definition
 *
 * @return array
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

return array(
    array(
        'id'          => 'enable_banners',
        'label'       => 'Enable Profile Banners',
        'description' => 'Allow users to upload or set a custom banner on their profile page.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'banner_max_size',
        'label'       => 'Max Banner File Size (KB)',
        'description' => 'Maximum file size for uploaded banner images in kilobytes.',
        'type'        => 'numeric',
        'default'     => '2048',
    ),
    array(
        'id'          => 'enable_statuses',
        'label'       => 'Enable Status Updates',
        'description' => 'Allow users to post status updates on their profile and the status feed page.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'status_max_length',
        'label'       => 'Max Status Length',
        'description' => 'Maximum number of characters allowed in a status update.',
        'type'        => 'numeric',
        'default'     => '1000',
    ),
    array(
        'id'          => 'statuses_per_page',
        'label'       => 'Statuses Per Page',
        'description' => 'Number of status updates to show per page on the feed.',
        'type'        => 'numeric',
        'default'     => '20',
    ),
);
