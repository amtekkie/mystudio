<?php
/**
 * FMZ WYSIWYG Editor — Options Definition
 *
 * Defines configurable options for the WYSIWYG mini plugin.
 * These appear in FMZ Studio > Plugins > FMZ WYSIWYG Editor > Settings.
 *
 * @return array
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

return array(
    /* ── Appearance ── */
    array(
        'id'          => 'color_mode',
        'label'       => 'Editor Color Mode',
        'description' => 'Choose the editor color scheme.',
        'type'        => 'select',
        'options'     => array(
            'light' => 'Light',
            'dark'  => 'Dark',
        ),
        'default'     => 'light',
    ),
    array(
        'id'          => 'color_theme',
        'label'       => 'Quick Presets',
        'description' => 'Click a swatch to apply a color theme to the editor toolbar.',
        'type'        => 'preset_swatches',
        'options'     => array(
            'teal'     => array('label' => 'Teal',     'swatch' => '#0d9488'),
            'ocean'    => array('label' => 'Ocean',    'swatch' => '#0369a1'),
            'indigo'   => array('label' => 'Indigo',   'swatch' => '#4338ca'),
            'purple'   => array('label' => 'Purple',   'swatch' => '#7e22ce'),
            'rose'     => array('label' => 'Rose',     'swatch' => '#be123c'),
            'amber'    => array('label' => 'Amber',    'swatch' => '#b45309'),
            'emerald'  => array('label' => 'Emerald',  'swatch' => '#059669'),
            'crimson'  => array('label' => 'Crimson',  'swatch' => '#dc2626'),
            'sapphire' => array('label' => 'Sapphire', 'swatch' => '#1d4ed8'),
            'coral'    => array('label' => 'Coral',    'swatch' => '#c2410c'),
            'slate'    => array('label' => 'Slate',    'swatch' => '#475569'),
            'pink'     => array('label' => 'Pink',     'swatch' => '#db2777'),
        ),
        'default'     => 'teal',
    ),
    array(
        'id'          => 'default_text_color',
        'label'       => 'Default Toolbar Text Color Selection',
        'description' => 'The initial text color applied when clicking the color button directly (before choosing from palette).',
        'type'        => 'color',
        'default'     => '#e06666',
    ),
    array(
        'id'          => 'default_highlight_color',
        'label'       => 'Default Toolbar Highlight Color Selection',
        'description' => 'The initial highlight color applied when clicking the highlight button directly (before choosing from palette).',
        'type'        => 'color',
        'default'     => '#fff2cc',
    ),

    /* ── Toolbar Layout ── */
    array(
        'id'          => 'toolbar_style',
        'label'       => 'Toolbar Style',
        'description' => 'Which toolbar layout to use.',
        'type'        => 'select',
        'options'     => array(
            'full'    => 'Full (all formatting options)',
            'minimal' => 'Minimal (basic formatting only)',
            'custom'  => 'Custom (configure below)',
        ),
        'default'     => 'full',
    ),
    array(
        'id'          => 'toolbar_buttons',
        'label'       => 'Custom Toolbar Buttons',
        'description' => 'Drag buttons between Available and Active to customize. Drag to reorder. Separators ( | ) group buttons visually.',
        'type'        => 'toolbar_builder',
        'default'     => 'bold,italic,underline,strikethrough,|,fontFamily,fontSize,fontColor,highlight,|,alignLeft,alignCenter,alignRight,alignJustify,|,bulletList,numberedList,indent,outdent,|,link,image,video,table,|,emoji,gif,quote,code,formula,hr,|,removeFormat,undo,redo,saveDraft,source',
    ),

    /* ── Font Settings ── */
    array(
        'id'          => 'font_families',
        'label'       => 'Available Font Families',
        'description' => 'One per line: Display Name|font-family CSS. Prefix "google:" to auto-load from Google Fonts CDN.',
        'type'        => 'textarea',
        'default'     => "Arial|Arial, Helvetica, sans-serif\nGeorgia|Georgia, serif\nTimes New Roman|Times New Roman, serif\nCourier New|Courier New, monospace\nVerdana|Verdana, sans-serif\nTrebuchet MS|Trebuchet MS, sans-serif\ngoogle:Roboto|Roboto, sans-serif\ngoogle:Open Sans|Open Sans, sans-serif\ngoogle:Fira Code|Fira Code, monospace",
    ),
    array(
        'id'          => 'font_sizes',
        'label'       => 'Available Font Sizes',
        'description' => 'Comma-separated sizes with units.',
        'type'        => 'text',
        'default'     => '8px,9px,10px,12px,14px,16px,18px,20px,24px,28px,36px,48px,72px',
    ),

    /* ── Editor Dimensions ── */
    array(
        'id'          => 'editor_height',
        'label'       => 'Editor Height (px)',
        'description' => 'Default height of the editor content area.',
        'type'        => 'numeric',
        'default'     => '350',
    ),

    /* ── Quick Reply ── */
    array(
        'id'          => 'max_quote_depth',
        'label'       => 'Max Quote Nesting Depth',
        'description' => 'Maximum levels of nested [quote] tags allowed when inserting quotes. Content beyond this depth is stripped. Set 0 for unlimited.',
        'type'        => 'numeric',
        'default'     => '3',
    ),
    array(
        'id'          => 'enable_quick_reply_editor',
        'label'       => 'Enable WYSIWYG in Quick Reply',
        'description' => 'Show the FMZ WYSIWYG editor in the quick reply box. When disabled, quick reply uses the default plain textarea.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'quick_reply_editor_height',
        'label'       => 'Quick Reply Editor Height (px)',
        'description' => 'Height of the editor content area when used inside the quick reply box.',
        'type'        => 'numeric',
        'default'     => '150',
    ),
    array(
        'id'          => 'quick_reply_toolbar_style',
        'label'       => 'Quick Reply Toolbar Style',
        'description' => 'Which toolbar layout to use for quick reply. Choose a different style to keep quick reply compact.',
        'type'        => 'select',
        'options'     => array(
            'same'    => 'Same as main editor',
            'full'    => 'Full (all formatting options)',
            'minimal' => 'Minimal (basic formatting only)',
            'custom'  => 'Custom (configure below)',
        ),
        'default'     => 'minimal',
    ),
    array(
        'id'          => 'quick_reply_toolbar_buttons',
        'label'       => 'Quick Reply Custom Toolbar Buttons',
        'description' => 'Drag buttons between Available and Active to customize the quick reply toolbar. Only used when Quick Reply Toolbar Style is "Custom".',
        'type'        => 'toolbar_builder',
        'default'     => 'bold,italic,underline,|,link,image,emoji,quote,code,|,source',
    ),

    /* ── Quick Edit (Inline Post Edit) ── */
    array(
        'id'          => 'enable_quick_edit_editor',
        'label'       => 'Enable WYSIWYG in Quick Edit',
        'description' => 'Show the FMZ WYSIWYG editor when using inline quick edit on posts. When disabled, quick edit uses the default plain textarea.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'quick_edit_editor_height',
        'label'       => 'Quick Edit Editor Height (px)',
        'description' => 'Height of the editor content area when used inside inline quick edit.',
        'type'        => 'numeric',
        'default'     => '250',
    ),
    array(
        'id'          => 'quick_edit_toolbar_style',
        'label'       => 'Quick Edit Toolbar Style',
        'description' => 'Toolbar layout for inline quick edit.',
        'type'        => 'select',
        'options'     => array(
            'same'    => 'Same as main editor',
            'full'    => 'Full (all formatting options)',
            'minimal' => 'Minimal (basic formatting only)',
            'custom'  => 'Custom (configure below)',
        ),
        'default'     => 'minimal',
    ),
    array(
        'id'          => 'quick_edit_toolbar_buttons',
        'label'       => 'Quick Edit Custom Toolbar Buttons',
        'description' => 'Drag buttons between Available and Active to customize the quick edit toolbar. Only used when Quick Edit Toolbar Style is "Custom".',
        'type'        => 'toolbar_builder',
        'default'     => 'bold,italic,underline,strikethrough,|,link,image,quote,code,|,undo,redo,source',
    ),
    array(
        'id'          => 'editor_font_family',
        'label'       => 'Default Editor Font',
        'description' => 'Default font in the content area.',
        'type'        => 'text',
        'default'     => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
    ),
    array(
        'id'          => 'editor_font_size',
        'label'       => 'Default Editor Font Size',
        'description' => 'Default font size in the content area.',
        'type'        => 'text',
        'default'     => '14px',
    ),

    /* ── Feature Toggles ── */
    array(
        'id'          => 'enable_image_paste',
        'label'       => 'Enable Image Paste',
        'description' => 'Allow pasting images from clipboard.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'enable_image_upload',
        'label'       => 'Enable Image Drag & Drop',
        'description' => 'Allow dragging images into the editor.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'max_image_size_kb',
        'label'       => 'Max Image Size (KB)',
        'description' => 'Maximum image file size for paste/drop upload. Example: 2048 = 2 MB.',
        'type'        => 'numeric',
        'default'     => '2048',
    ),
    array(
        'id'          => 'max_images_per_post',
        'label'       => 'Max Images Per Post',
        'description' => 'Maximum number of pasted/dropped images allowed per post. 0 = unlimited.',
        'type'        => 'numeric',
        'default'     => '10',
    ),
    array(
        'id'          => 'enable_code_highlight',
        'label'       => 'Code Syntax Highlighting',
        'description' => 'Use highlight.js for code blocks in rendered posts.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'enable_code_copy',
        'label'       => 'Code Copy Button',
        'description' => 'Show a Copy button on rendered code blocks.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'enable_code_linenumbers',
        'label'       => 'Code Line Numbers',
        'description' => 'Show line numbers on rendered code blocks.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'show_source_toggle',
        'label'       => 'Show Source Toggle',
        'description' => 'Show button to switch WYSIWYG / BBCode source.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'auto_save',
        'label'       => 'Auto-Save Drafts',
        'description' => 'Periodically save content to localStorage.',
        'type'        => 'yesno',
        'default'     => '1',
    ),
    array(
        'id'          => 'auto_save_interval',
        'label'       => 'Auto-Save Interval (seconds)',
        'description' => 'How often to auto-save.',
        'type'        => 'numeric',
        'default'     => '30',
    ),

    /* ── API Keys ── */
    array(
        'id'          => 'giphy_api_key',
        'label'       => 'GIPHY API Key',
        'description' => 'For GIF search. Get one free at <a href="https://developers.giphy.com/" target="_blank">developers.giphy.com</a>. Leave blank to hide GIF button.',
        'type'        => 'text',
        'default'     => '',
    ),
);
