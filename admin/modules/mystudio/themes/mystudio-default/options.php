<?php
/**
 * MyStudio Default — Theme Options
 *
 * Return an array of option definitions. Each key becomes the option name.
 * Values are stored in themes/mystudio-default/default.json
 * and available in templates via {$mybb->ms_theme_options['key']}.
 *
 * Supported types: text, textarea, yesno, select, color, color_presets, numeric, image
 */

return array(
    'theme_color' => array(
        'title'       => 'Theme Colour',
        'description' => 'Accent colour used for links, buttons, headings and highlights.',
        'type'        => 'color_presets',
        'default'     => '#2dd4bf',
        'page'        => 'studio_settings',
        'options'     => array(
            '#2dd4bf' => 'Default (Teal)',
            '#55efc4' => 'Mint',
            '#0984e3' => 'Blue',
            '#6c5ce7' => 'Purple',
            '#2d3436' => 'Charcoal',
            '#d63031' => 'Red',
            '#e84393' => 'Pink',
            '#fdcb6e' => 'Yellow',
            '#e17055' => 'Orange',
        ),
    ),

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
        'default'     => 'MyStudio',
        'page'        => 'studio_settings',
    ),

    'site_logo' => array(
        'title'          => 'Upload Logo Image',
        'description'    => 'Upload a logo image. When set, this replaces the icon/text logo. Supports PNG, JPG, GIF and WebP.',
        'type'           => 'image',
        'has_dimensions' => true,
        'default'        => '',
        'default_width'  => '200',
        'default_height' => '0',
        'page'           => 'studio_settings',
    ),

    'favicon' => array(
        'title'       => 'Favicon',
        'description' => 'Upload a favicon (.ico or .png). Displayed in browser tabs and bookmarks.',
        'type'        => 'image',
        'default'     => '',
        'page'        => 'studio_settings',
    ),
);
