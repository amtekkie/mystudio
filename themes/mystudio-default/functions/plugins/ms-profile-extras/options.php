<?php
/**
 * MyStudio User Profile Extras — Options Definition
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
);
