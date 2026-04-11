<?php
/**
 * FMZ Editor Extras — Options Definition
 *
 * @return array
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

return array(
    array(
        'id'          => 'bootstrap_icons',
        'label'       => 'Bootstrap Icons Toolbar',
        'description' => 'Replace default sprite-based toolbar icons with Bootstrap Icons.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'themed',
        'label'       => 'Glass Morphism Theme',
        'description' => 'Apply glass morphism styling to the editor container.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'paste_fix',
        'label'       => 'Fix Paste Formatting',
        'description' => 'Strip tiny font sizes and excessive inline styles when pasting.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'paste_strip_styles',
        'label'       => 'Strip All Inline Styles on Paste',
        'description' => 'Remove ALL inline styles from pasted content (aggressive).',
        'type'        => 'yesno',
        'default'     => '0',
    ),
    array(
        'id'          => 'image_upload',
        'label'       => 'Image Paste & Drag-Drop',
        'description' => 'Allow pasting/dragging images directly into the editor.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'image_max_width',
        'label'       => 'Max Image Width (px)',
        'description' => 'Maximum width for pasted/dropped images. 0 = no limit.',
        'type'        => 'text',
        'default'     => '800',
    ),
    array(
        'id'          => 'image_resize',
        'label'       => 'Image Resize Handles',
        'description' => 'Allow resizing images in the editor by dragging.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'emoji',
        'label'       => 'Emoji Picker',
        'description' => 'Replace the smiley button with a unicode emoji picker.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'gif',
        'label'       => 'GIF Picker',
        'description' => 'Add a GIF search picker (requires API key below).',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'gif_provider',
        'label'       => 'GIF Provider',
        'description' => 'Tenor or GIPHY.',
        'type'        => 'select',
        'options'     => array(
            'tenor' => 'Tenor',
            'giphy' => 'GIPHY',
        ),
        'default'     => 'tenor',
    ),
    array(
        'id'          => 'gif_api_key',
        'label'       => 'GIF API Key',
        'description' => 'API key for the selected GIF provider.',
        'type'        => 'text',
        'default'     => '',
    ),
    array(
        'id'          => 'table',
        'label'       => 'Enhanced Table Paste',
        'description' => 'Convert pasted HTML tables to BBCode tables.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'autosave',
        'label'       => 'Auto-Save Drafts',
        'description' => 'Auto-save editor content to localStorage every 30 seconds.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'word_count',
        'label'       => 'Word/Character Count',
        'description' => 'Show word and character counter below the editor.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'mention',
        'label'       => '@Mentions',
        'description' => 'Type @ followed by a username for autocomplete.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'syntax_highlight',
        'label'       => 'Code Syntax Highlighting',
        'description' => 'Enable highlight.js for code blocks with line numbers.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
);
