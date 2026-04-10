<?php
/**
 * FMZ Forum Display Extras — Options Definition
 *
 * @return array
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

return array(
    array(
        'id'          => 'enable_lastposter_avatar',
        'label'       => 'Show Last Poster Avatar (Forums)',
        'description' => 'Display the last poster\'s avatar next to the last post info in forum listings.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'enable_thread_lastposter_avatar',
        'label'       => 'Show Last Poster Avatar (Threads)',
        'description' => 'Display the last poster\'s avatar next to the last post info in thread listings (forumdisplay).',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'enable_user_modal',
        'label'       => 'User Info Modal on Avatar Click',
        'description' => 'Show a Bootstrap modal with user details and actions (PM, profile, rate) when clicking the last poster avatar.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'subforum_columns',
        'label'       => 'Subforum Layout',
        'description' => 'Display subforums inline (comma-separated) or as a vertical list (one per line).',
        'type'        => 'select',
        'options'     => array(
            '0' => 'Inline (default)',
            '1' => 'Column (list)',
        ),
        'default'     => '0',
    ),
    array(
        'id'          => 'forum_layout',
        'label'       => 'Forum Listing Layout',
        'description' => 'Choose how forums are displayed within each category.',
        'type'        => 'select',
        'options'     => array(
            'rows'  => 'Rows (default)',
            'cards' => 'Cards (grid)',
        ),
        'default'     => 'rows',
    ),
    array(
        'id'          => 'cards_per_row',
        'label'       => 'Cards Per Row',
        'description' => 'Number of forum cards per row when using card layout (2-4).',
        'type'        => 'select',
        'options'     => array(
            '2' => '2 Cards',
            '3' => '3 Cards',
            '4' => '4 Cards',
        ),
        'default'     => '3',
    ),
    array(
        'id'          => 'enable_usergroup_style',
        'label'       => 'Usergroup Styled Usernames',
        'description' => 'Display last poster usernames with their usergroup color/style in forum listings.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
);
