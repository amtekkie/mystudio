<?php
/**
 * MyStudio Default — Theme Options
 *
 * Return an array of option definitions. Each key becomes the option name.
 * Values are stored in themes/mystudio-default/default.json
 * and available in templates via {$mybb->ms_theme_options['key']}.
 *
 * Supported types: text, textarea, yesno, select, color, numeric, image
 */

return array(
'logo_icon' => array(
        'title'       => 'Logo Icon',
        'description' => 'Choose a Bootstrap Icon for the logo. Leave empty for no icon.',
        'type'        => 'icon_chooser',
        'default'     => 'bi-brush',
        'page'        => 'studio_settings',
    ),

    'logo_text' => array(
        'title'       => 'Logo Text',
        'description' => 'Custom text for the logo. Leave empty to use the board name.',
        'type'        => 'text',
        'default'     => 'My Studio',
        'page'        => 'studio_settings',
    ),

    'site_logo' => array(
        'title'          => 'Upload Logo Image',
        'description'    => 'Upload a logo image. When set, this replaces the icon/text logo. Supports PNG, JPG, GIF, SVG, WebP.',
        'type'           => 'image',
        'has_dimensions' => true,
        'default'        => '',
        'default_width'  => '200',
        'default_height' => '0',
        'page'           => 'studio_settings',
    ),

    'favicon' => array(
        'title'       => 'Favicon',
        'description' => 'Upload a favicon (.ico, .png, .svg). Displayed in browser tabs and bookmarks.',
        'type'        => 'image',
        'default'     => '',
        'page'        => 'studio_settings',
    ),
);
