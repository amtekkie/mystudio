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

    /* ── Header ── */

    'logo_icon' => array(
        'title'       => 'Logo Icon',
        'description' => 'Choose a Bootstrap Icon for the logo. Leave empty for no icon.',
        'type'        => 'icon_chooser',
        'default'     => 'bi-brush',
        'page'        => 'header_footer',
    ),

    'logo_text' => array(
        'title'       => 'Logo Text',
        'description' => 'Custom text for the logo. Leave empty to use the board name.',
        'type'        => 'text',
        'default'     => 'My Studio',
        'page'        => 'header_footer',
    ),

    'site_logo' => array(
        'title'          => 'Upload Logo Image',
        'description'    => 'Upload a logo image. When set, this replaces the icon/text logo. Supports PNG, JPG, GIF, SVG, WebP.',
        'type'           => 'image',
        'has_dimensions' => true,
        'default'        => '',
        'default_width'  => '200',
        'default_height' => '0',
        'page'           => 'header_footer',
    ),

    'favicon' => array(
        'title'       => 'Favicon',
        'description' => 'Upload a favicon (.ico, .png, .svg). Displayed in browser tabs and bookmarks.',
        'type'        => 'image',
        'default'     => '',
        'page'        => 'header_footer',
    ),

    /* ── Navigation ── */
    'custom_nav_links' => array(
        'title'       => 'Custom Navigation Links',
        'description' => 'Add custom links to the main navigation bar.',
        'type'        => 'nav_links',
        'default'     => '',
        'page'        => 'header_footer',
    ),

    /* ── Footer ── */
    'footer_text' => array(
        'title'       => 'Footer Custom Text',
        'description' => 'Additional text displayed below the copyright bar in the footer. HTML allowed.',
        'type'        => 'textarea',
        'default'     => '',
        'page'        => 'header_footer',
    ),

    'footer_about_text' => array(
        'title'       => 'Footer About Text',
        'description' => 'Text shown in the "About" section of the footer. Leave empty to use the default language string. Supports HTML. Use <code>{boardname}</code> as a placeholder for the board name.',
        'type'        => 'textarea',
        'default'     => '',
        'page'        => 'header_footer',
    ),
);
