<?php
/**
 * MyStudio -- Admin Module
 *
 * Handles all MyStudio pages: Manage, Import, Export, Settings, Editor.
 * Registered as a top-level admin module via module_meta.php.
 *
 * @version 2.1.0
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

require_once MYBB_ROOT . 'inc/plugins/mystudio/core.php';
require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';

global $mybb, $db, $page, $lang, $cache, $plugins;

$ms = new MyStudio();

// Load Bootstrap Icons on all MyStudio pages
$page->extra_header .= '<link rel="stylesheet" href="../themes/mystudio-default/vendor/bootstrap-icons.min.css" />';

/* ====================================================================
   Toolbar Builder Renderer (graphical drag & drop for toolbar config)
   ==================================================================== */

function ms_render_toolbar_builder($id, $currentValue)
{
    // All available buttons with their Bootstrap Icons and labels
    $allButtons = array(
        'bold'          => array('icon' => 'bi-type-bold',          'label' => 'Bold'),
        'italic'        => array('icon' => 'bi-type-italic',        'label' => 'Italic'),
        'underline'     => array('icon' => 'bi-type-underline',     'label' => 'Underline'),
        'strikethrough' => array('icon' => 'bi-type-strikethrough', 'label' => 'Strikethrough'),
        'fontFamily'    => array('icon' => 'bi-fonts',              'label' => 'Font Family'),
        'fontSize'      => array('icon' => 'bi-text-paragraph',     'label' => 'Font Size'),
        'fontColor'     => array('icon' => 'bi-palette',            'label' => 'Text Color'),
        'highlight'     => array('icon' => 'bi-paint-bucket',       'label' => 'Highlight'),
        'alignLeft'     => array('icon' => 'bi-text-left',          'label' => 'Align Left'),
        'alignCenter'   => array('icon' => 'bi-text-center',        'label' => 'Center'),
        'alignRight'    => array('icon' => 'bi-text-right',         'label' => 'Align Right'),
        'alignJustify'  => array('icon' => 'bi-justify',            'label' => 'Justify'),
        'bulletList'    => array('icon' => 'bi-list-ul',            'label' => 'Bullet List'),
        'numberedList'  => array('icon' => 'bi-list-ol',            'label' => 'Numbered List'),
        'indent'        => array('icon' => 'bi-text-indent-left',   'label' => 'Indent'),
        'outdent'       => array('icon' => 'bi-text-indent-right',  'label' => 'Outdent'),
        'link'          => array('icon' => 'bi-link-45deg',         'label' => 'Link'),
        'image'         => array('icon' => 'bi-image',              'label' => 'Image'),
        'video'         => array('icon' => 'bi-camera-video',       'label' => 'Video'),
        'table'         => array('icon' => 'bi-table',              'label' => 'Table'),
        'emoji'         => array('icon' => 'bi-emoji-smile',        'label' => 'Emoji'),
        'gif'           => array('icon' => 'bi-filetype-gif',       'label' => 'GIF'),
        'quote'         => array('icon' => 'bi-chat-quote',         'label' => 'Quote'),
        'code'          => array('icon' => 'bi-code-slash',         'label' => 'Code'),
        'formula'       => array('icon' => 'bi-calculator',         'label' => 'Formula'),
        'hr'            => array('icon' => 'bi-dash-lg',            'label' => 'Horiz. Rule'),
        'removeFormat'  => array('icon' => 'bi-eraser',             'label' => 'Clear Format'),
        'undo'          => array('icon' => 'bi-arrow-counterclockwise', 'label' => 'Undo'),
        'redo'          => array('icon' => 'bi-arrow-clockwise',    'label' => 'Redo'),
        'saveDraft'     => array('icon' => 'bi-floppy',             'label' => 'Save Draft'),
        'source'        => array('icon' => 'bi-code-square',        'label' => 'Source'),
    );

    // Parse current value into active items
    $activeParts = array_map('trim', explode(',', $currentValue));
    $activeIds = array();
    foreach ($activeParts as $p) {
        if ($p !== '' && ($p === '|' || isset($allButtons[$p]))) {
            $activeIds[] = $p;
        }
    }

    // Available = all buttons NOT in active
    $usedIds = array_filter($activeIds, function ($x) { return $x !== '|'; });
    $availableIds = array();
    foreach ($allButtons as $btnId => $btn) {
        if (!in_array($btnId, $usedIds)) {
            $availableIds[] = $btnId;
        }
    }

    $fieldName = htmlspecialchars_uni('opt_' . $id);

    // Build the chip HTML helper
    $chipHtml = function ($btnId, $info = null) {
        if ($btnId === '|') {
            return '<span class="ms-tb-chip ms-tb-sep" draggable="true" data-id="|" title="Separator">'
                 . '<i class="bi bi-grip-vertical" style="opacity:.4"></i> |</span>';
        }
        if (!$info) return '';
        $icon  = htmlspecialchars_uni($info['icon']);
        $label = htmlspecialchars_uni($info['label']);
        $bid   = htmlspecialchars_uni($btnId);
        return '<span class="ms-tb-chip" draggable="true" data-id="' . $bid . '" title="' . $label . '">'
             . '<i class="bi ' . $icon . '"></i> ' . $label . '</span>';
    };

    $html = '<input type="hidden" name="' . $fieldName . '" id="ms-tb-value" value="' . htmlspecialchars_uni($currentValue) . '" />';

    // Styles
    $html .= '<style>
.ms-tb-builder{display:flex;gap:12px;margin-top:6px}
.ms-tb-panel{flex:1;border:1px solid #ddd;border-radius:6px;background:#fafafa;min-height:120px}
.ms-tb-panel-head{padding:6px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
.ms-tb-panel-body{padding:6px;display:flex;flex-wrap:wrap;gap:4px;min-height:80px}
.ms-tb-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#fff;border:1px solid #ccc;border-radius:4px;font-size:12px;cursor:grab;user-select:none;transition:background .12s,border-color .12s,box-shadow .12s}
.ms-tb-chip:hover{background:#e8f5e9;border-color:#0d9488}
.ms-tb-chip.ms-tb-sep{background:#f5f5f5;color:#999;font-weight:700}
.ms-tb-chip.ms-tb-dragover{border-color:#0d9488;box-shadow:-2px 0 0 #0d9488}
.ms-tb-chip i{font-size:14px}
.ms-tb-panel-body.ms-tb-dragover-zone{background:#e0f2f1;border-color:#0d9488}
.ms-tb-add-sep{background:none;border:1px dashed #aaa;border-radius:4px;padding:4px 10px;font-size:11px;color:#888;cursor:pointer;white-space:nowrap}
.ms-tb-add-sep:hover{border-color:#0d9488;color:#0d9488}
</style>';

    // Active panel
    $html .= '<div class="ms-tb-builder">';
    $html .= '<div class="ms-tb-panel">';
    $html .= '<div class="ms-tb-panel-head">Active Toolbar <button type="button" class="ms-tb-add-sep" id="ms-tb-add-sep" title="Add Separator">+ Separator</button></div>';
    $html .= '<div class="ms-tb-panel-body" id="ms-tb-active">';
    foreach ($activeIds as $aid) {
        if ($aid === '|') {
            $html .= $chipHtml('|');
        } elseif (isset($allButtons[$aid])) {
            $html .= $chipHtml($aid, $allButtons[$aid]);
        }
    }
    $html .= '</div></div>';

    // Available panel
    $html .= '<div class="ms-tb-panel">';
    $html .= '<div class="ms-tb-panel-head">Available Buttons</div>';
    $html .= '<div class="ms-tb-panel-body" id="ms-tb-available">';
    foreach ($availableIds as $aid) {
        $html .= $chipHtml($aid, $allButtons[$aid]);
    }
    $html .= '</div></div>';
    $html .= '</div>';

    // JavaScript for drag & drop
    $html .= '<script>
(function(){
    var activeEl = document.getElementById("ms-tb-active");
    var availEl  = document.getElementById("ms-tb-available");
    var hiddenInput = document.getElementById("ms-tb-value");
    var dragItem = null;

    function syncValue() {
        var chips = activeEl.querySelectorAll(".ms-tb-chip");
        var ids = [];
        chips.forEach(function(c){ ids.push(c.getAttribute("data-id")); });
        hiddenInput.value = ids.join(",");
    }

    function bindChip(chip) {
        chip.addEventListener("dragstart", function(e) {
            dragItem = chip;
            chip.style.opacity = ".4";
            e.dataTransfer.effectAllowed = "move";
        });
        chip.addEventListener("dragend", function() {
            chip.style.opacity = "1";
            dragItem = null;
            document.querySelectorAll(".ms-tb-dragover").forEach(function(el){ el.classList.remove("ms-tb-dragover"); });
            document.querySelectorAll(".ms-tb-dragover-zone").forEach(function(el){ el.classList.remove("ms-tb-dragover-zone"); });
        });
        chip.addEventListener("dragover", function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
            chip.classList.add("ms-tb-dragover");
        });
        chip.addEventListener("dragleave", function() {
            chip.classList.remove("ms-tb-dragover");
        });
        chip.addEventListener("drop", function(e) {
            e.preventDefault();
            chip.classList.remove("ms-tb-dragover");
            if (!dragItem || dragItem === chip) return;
            var parent = chip.parentNode;
            var chips = Array.from(parent.children);
            var dragIdx = chips.indexOf(dragItem);
            var dropIdx = chips.indexOf(chip);
            if (dragItem.parentNode !== parent) {
                parent.insertBefore(dragItem, chip);
            } else if (dragIdx < dropIdx) {
                parent.insertBefore(dragItem, chip.nextSibling);
            } else {
                parent.insertBefore(dragItem, chip);
            }
            syncValue();
        });
        // Double-click to move between panels
        chip.addEventListener("dblclick", function() {
            var currentPanel = chip.parentNode;
            if (currentPanel === activeEl) {
                if (chip.getAttribute("data-id") === "|") {
                    chip.remove();
                } else {
                    availEl.appendChild(chip);
                }
            } else {
                activeEl.appendChild(chip);
            }
            syncValue();
        });
    }

    function bindPanel(panel) {
        panel.addEventListener("dragover", function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
            panel.classList.add("ms-tb-dragover-zone");
        });
        panel.addEventListener("dragleave", function(e) {
            if (!panel.contains(e.relatedTarget)) {
                panel.classList.remove("ms-tb-dragover-zone");
            }
        });
        panel.addEventListener("drop", function(e) {
            e.preventDefault();
            panel.classList.remove("ms-tb-dragover-zone");
            if (!dragItem) return;
            // Only append if dropped on the panel itself (not on a chip)
            if (e.target === panel) {
                panel.appendChild(dragItem);
                syncValue();
            }
        });
    }

    document.querySelectorAll(".ms-tb-chip").forEach(bindChip);
    bindPanel(activeEl);
    bindPanel(availEl);

    document.getElementById("ms-tb-add-sep").addEventListener("click", function() {
        var sep = document.createElement("span");
        sep.className = "ms-tb-chip ms-tb-sep";
        sep.draggable = true;
        sep.setAttribute("data-id", "|");
        sep.title = "Separator";
        sep.innerHTML = \'<i class="bi bi-grip-vertical" style="opacity:.4"></i> |\';
        activeEl.appendChild(sep);
        bindChip(sep);
        syncValue();
    });
})();
</script>';

    return $html;
}

// Determine action: explicit query param > module routing > default
$action = $mybb->get_input('action');
if (empty($action)) {
    // MyBB admin passes the action via the module param (e.g. mystudio-plugin_settings)
    // but does NOT store it in $mybb->input['action']. Extract it from the module param.
    $moduleParts = explode('-', $mybb->get_input('module'), 2);
    if (!empty($moduleParts[1])) {
        $action = $moduleParts[1];
    }
}
if (empty($action)) {
    $action = 'manage';
}

/* ====================================================================
   Shared Styles (kept minimal â€” prefer MyBB native components)
   ==================================================================== */

/* ====================================================================
   Inline Help Box Helper
   ==================================================================== */

function ms_help_box($title, $body) {
    return '<div style="margin-top:16px;margin-bottom:16px;border:1px solid #3a7d76;border-radius:6px;background:linear-gradient(135deg,#f0faf9 0%,#f8fffe 100%);overflow:hidden">'
         . '<div style="display:flex;align-items:center;gap:8px;padding:10px 14px">'
         . '<i class="bi bi-info-circle" style="color:#0d9488;font-size:15px"></i>'
         . '<strong style="color:#0b7c72;font-size:13px">' . $title . '</strong></div>'
         . '<div style="padding:0 16px 14px;font-size:12.5px;line-height:1.7;color:#444">' . $body . '</div>'
         . '</div>';
}

/**
 * Return the master icon list as an associative array.
 * Keys are Bootstrap Icon class names, values are human-readable labels.
 * Grouped by category for the modal grid.
 */
function ms_get_icon_list()
{
    return array(
        // â”€â”€ Social / Brand â”€â”€
        'bi-discord' => 'Discord', 'bi-youtube' => 'YouTube', 'bi-twitch' => 'Twitch',
        'bi-twitter-x' => 'Twitter / X', 'bi-facebook' => 'Facebook', 'bi-instagram' => 'Instagram',
        'bi-tiktok' => 'TikTok', 'bi-reddit' => 'Reddit', 'bi-github' => 'GitHub',
        'bi-steam' => 'Steam', 'bi-linkedin' => 'LinkedIn', 'bi-whatsapp' => 'WhatsApp',
        'bi-telegram' => 'Telegram', 'bi-snapchat' => 'Snapchat', 'bi-pinterest' => 'Pinterest',
        'bi-paypal' => 'PayPal', 'bi-spotify' => 'Spotify', 'bi-mastodon' => 'Mastodon',
        'bi-threads' => 'Threads', 'bi-wechat' => 'WeChat',
        // â”€â”€ Navigation / UI â”€â”€
        'bi-house' => 'Home', 'bi-house-door' => 'Home Door', 'bi-search' => 'Search',
        'bi-list' => 'List', 'bi-grid' => 'Grid', 'bi-grid-3x3-gap' => 'Grid 3x3',
        'bi-three-dots' => 'More', 'bi-three-dots-vertical' => 'More Vert',
        'bi-arrow-left' => 'Arrow Left', 'bi-arrow-right' => 'Arrow Right',
        'bi-arrow-up' => 'Arrow Up', 'bi-arrow-down' => 'Arrow Down',
        'bi-chevron-left' => 'Chevron Left', 'bi-chevron-right' => 'Chevron Right',
        'bi-box-arrow-up-right' => 'External Link', 'bi-link-45deg' => 'Link',
        'bi-link' => 'Link Chain', 'bi-signpost' => 'Signpost',
        // â”€â”€ Communication â”€â”€
        'bi-chat' => 'Chat', 'bi-chat-dots' => 'Chat Dots', 'bi-chat-left-text' => 'Chat Text',
        'bi-chat-square' => 'Chat Square', 'bi-envelope' => 'Email', 'bi-envelope-open' => 'Email Open',
        'bi-telephone' => 'Phone', 'bi-telephone-fill' => 'Phone Fill',
        'bi-megaphone' => 'Megaphone', 'bi-broadcast' => 'Broadcast',
        'bi-bell' => 'Bell', 'bi-bell-fill' => 'Bell Fill',
        'bi-reply' => 'Reply', 'bi-send' => 'Send',
        // â”€â”€ Content / Media â”€â”€
        'bi-image' => 'Image', 'bi-images' => 'Images', 'bi-camera' => 'Camera',
        'bi-camera-video' => 'Video Camera', 'bi-film' => 'Film', 'bi-play-circle' => 'Play',
        'bi-music-note' => 'Music', 'bi-music-note-list' => 'Playlist',
        'bi-mic' => 'Microphone', 'bi-headphones' => 'Headphones',
        'bi-newspaper' => 'News', 'bi-journal-text' => 'Journal',
        'bi-book' => 'Book', 'bi-bookmark' => 'Bookmark', 'bi-bookmark-star' => 'Bookmark Star',
        'bi-file-earmark-text' => 'Document', 'bi-file-earmark-pdf' => 'PDF',
        'bi-file-earmark-code' => 'Code File', 'bi-file-earmark-zip' => 'ZIP File',
        'bi-folder' => 'Folder', 'bi-folder-fill' => 'Folder Fill',
        // â”€â”€ Actions â”€â”€
        'bi-download' => 'Download', 'bi-upload' => 'Upload',
        'bi-cloud-download' => 'Cloud Download', 'bi-cloud-upload' => 'Cloud Upload',
        'bi-pencil' => 'Edit', 'bi-pencil-square' => 'Edit Square',
        'bi-trash' => 'Delete', 'bi-trash3' => 'Delete Alt',
        'bi-plus-circle' => 'Add', 'bi-dash-circle' => 'Remove',
        'bi-plus-lg' => 'Plus', 'bi-x-lg' => 'Close',
        'bi-check-lg' => 'Check', 'bi-check-circle' => 'Check Circle',
        'bi-check2-all' => 'Double Check', 'bi-x-circle' => 'X Circle',
        'bi-eye' => 'View', 'bi-eye-slash' => 'Hide',
        'bi-clipboard' => 'Clipboard', 'bi-copy' => 'Copy',
        'bi-share' => 'Share', 'bi-share-fill' => 'Share Fill',
        'bi-pin' => 'Pin', 'bi-pin-angle' => 'Pin Angle',
        // â”€â”€ Commerce â”€â”€
        'bi-cart' => 'Cart', 'bi-cart-fill' => 'Cart Fill', 'bi-bag' => 'Bag',
        'bi-shop' => 'Shop', 'bi-shop-window' => 'Shop Window',
        'bi-coin' => 'Coin', 'bi-cash-stack' => 'Cash',
        'bi-credit-card' => 'Credit Card', 'bi-wallet' => 'Wallet',
        'bi-gift' => 'Gift', 'bi-gift-fill' => 'Gift Fill',
        'bi-receipt' => 'Receipt', 'bi-tag' => 'Tag', 'bi-tags' => 'Tags',
        // â”€â”€ People / Users â”€â”€
        'bi-person' => 'Person', 'bi-person-fill' => 'Person Fill',
        'bi-people' => 'People', 'bi-people-fill' => 'People Fill',
        'bi-person-plus' => 'Add User', 'bi-person-check' => 'Verified User',
        'bi-person-badge' => 'Badge User', 'bi-person-circle' => 'Avatar',
        // â”€â”€ Status / Info â”€â”€
        'bi-info-circle' => 'Info', 'bi-question-circle' => 'Help',
        'bi-exclamation-triangle' => 'Warning', 'bi-exclamation-circle' => 'Alert',
        'bi-shield-check' => 'Shield Check', 'bi-shield-lock' => 'Shield Lock',
        'bi-lock' => 'Lock', 'bi-unlock' => 'Unlock',
        'bi-key' => 'Key', 'bi-fingerprint' => 'Fingerprint',
        'bi-flag' => 'Flag', 'bi-flag-fill' => 'Flag Fill',
        'bi-patch-check' => 'Verified', 'bi-award' => 'Award',
        'bi-trophy' => 'Trophy', 'bi-trophy-fill' => 'Trophy Fill',
        'bi-star' => 'Star', 'bi-star-fill' => 'Star Fill',
        'bi-heart' => 'Heart', 'bi-heart-fill' => 'Heart Fill',
        'bi-hand-thumbs-up' => 'Thumbs Up', 'bi-hand-thumbs-down' => 'Thumbs Down',
        'bi-emoji-smile' => 'Smile', 'bi-emoji-heart-eyes' => 'Heart Eyes',
        // â”€â”€ Technology â”€â”€
        'bi-cpu' => 'CPU', 'bi-cpu-fill' => 'CPU Fill',
        'bi-gpu-card' => 'GPU', 'bi-motherboard' => 'Motherboard',
        'bi-code-slash' => 'Code', 'bi-terminal' => 'Terminal',
        'bi-bug' => 'Bug', 'bi-braces' => 'Braces',
        'bi-database' => 'Database', 'bi-server' => 'Server',
        'bi-hdd' => 'Hard Drive', 'bi-usb-drive' => 'USB',
        'bi-wifi' => 'WiFi', 'bi-bluetooth' => 'Bluetooth',
        'bi-globe' => 'Globe', 'bi-globe2' => 'Globe Alt',
        'bi-controller' => 'Controller', 'bi-joystick' => 'Joystick',
        'bi-headset' => 'Headset', 'bi-headset-vr' => 'VR Headset',
        'bi-phone' => 'Phone Device', 'bi-laptop' => 'Laptop', 'bi-display' => 'Monitor',
        'bi-printer' => 'Printer', 'bi-router' => 'Router',
        // â”€â”€ General / Misc â”€â”€
        'bi-gear' => 'Gear', 'bi-gear-fill' => 'Gear Fill',
        'bi-tools' => 'Tools', 'bi-wrench' => 'Wrench', 'bi-hammer' => 'Hammer',
        'bi-palette' => 'Palette', 'bi-brush' => 'Brush', 'bi-paint-bucket' => 'Paint',
        'bi-lightning' => 'Lightning', 'bi-lightning-fill' => 'Lightning Fill',
        'bi-fire' => 'Fire', 'bi-snow' => 'Snow',
        'bi-sun' => 'Sun', 'bi-moon' => 'Moon', 'bi-cloud' => 'Cloud',
        'bi-umbrella' => 'Umbrella', 'bi-droplet' => 'Droplet',
        'bi-calendar' => 'Calendar', 'bi-calendar-event' => 'Calendar Event',
        'bi-clock' => 'Clock', 'bi-alarm' => 'Alarm', 'bi-hourglass' => 'Hourglass',
        'bi-map' => 'Map', 'bi-geo-alt' => 'Location', 'bi-compass' => 'Compass',
        'bi-building' => 'Building', 'bi-hospital' => 'Hospital',
        'bi-rss' => 'RSS', 'bi-activity' => 'Activity',
        'bi-graph-up' => 'Graph Up', 'bi-bar-chart' => 'Bar Chart', 'bi-pie-chart' => 'Pie Chart',
        'bi-speedometer2' => 'Speedometer', 'bi-bullseye' => 'Bullseye',
        'bi-box' => 'Box', 'bi-archive' => 'Archive', 'bi-puzzle' => 'Puzzle',
        'bi-layers' => 'Layers', 'bi-stack' => 'Stack',
        'bi-aspect-ratio' => 'Aspect Ratio', 'bi-crop' => 'Crop',
        'bi-magic' => 'Magic', 'bi-scissors' => 'Scissors',
        'bi-paperclip' => 'Paperclip', 'bi-binder-clip' => 'Binder Clip'
    );
}

/**
 * Render a single theme option row into a FormContainer.
 * Handles all option types: text, textarea, yesno, select, color, numeric, image.
 */
function ms_render_option_row($form, $form_container, $key, $def, $values, $mybb)
{
    $title = isset($def['title']) ? $def['title'] : $key;
    $desc  = isset($def['description']) ? $def['description'] : '';
    $type  = isset($def['type']) ? $def['type'] : 'text';
    $val   = isset($values[$key]) ? $values[$key] : '';

    switch ($type) {
        case 'textarea':
            $input = $form->generate_text_area('opt_' . $key, $val, array('rows' => 4, 'style' => 'width:95%'));
            break;
        case 'yesno':
            $input = $form->generate_yes_no_radio('opt_' . $key, $val);
            break;
        case 'select':
            $opts = isset($def['options']) ? $def['options'] : array();
            $input = $form->generate_select_box('opt_' . $key, $opts, $val);
            break;
        case 'radio':
            $opts = isset($def['options']) ? $def['options'] : array();
            $radios = '';
            foreach ($opts as $optVal => $optLabel) {
                $checked = ($val === (string)$optVal) ? ' checked' : '';
                $id = 'opt_' . htmlspecialchars_uni($key) . '_' . htmlspecialchars_uni($optVal);
                $radios .= '<label style="display:inline-flex;align-items:center;gap:5px;margin-right:16px;cursor:pointer;font-size:13px">' 
                         . '<input type="radio" name="opt_' . htmlspecialchars_uni($key) . '" value="' . htmlspecialchars_uni($optVal) . '" id="' . $id . '"' . $checked . ' />' 
                         . $optLabel . '</label>';
            }
            $input = '<div style="display:flex;align-items:center;gap:4px">' . $radios . '</div>';
            break;
        case 'color':
            $input = '<input type="color" name="opt_' . htmlspecialchars_uni($key) . '" value="' . htmlspecialchars_uni($val) . '" />';
            break;
        case 'icon_chooser':
            $safeKey = htmlspecialchars_uni($key);
            $safeVal = $val ? htmlspecialchars_uni($val) : '';
            $previewIcon = $safeVal ?: 'bi-grid-3x3-gap';
            $input = '<div style="display:flex;align-items:center;gap:8px">'
                   . '<input type="hidden" name="opt_' . $safeKey . '" id="ms-icon-val-' . $safeKey . '" value="' . $safeVal . '" />'
                   . '<button type="button" class="ms-icon-pick-btn" data-target-input="ms-icon-val-' . $safeKey . '" data-target-preview="ms-icon-prev-' . $safeKey . '" data-target-label="ms-icon-lbl-' . $safeKey . '" '
                   . 'style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;font-size:13px;border:1px solid #ccc;border-radius:6px;background:#fafafa;cursor:pointer">'
                   . '<i class="bi ' . $previewIcon . '" id="ms-icon-prev-' . $safeKey . '" style="font-size:20px"></i> '
                   . '<span id="ms-icon-lbl-' . $safeKey . '">' . ($safeVal ?: 'Choose icon&hellip;') . '</span>'
                   . '</button>';
            if ($safeVal) {
                $input .= ' <button type="button" class="ms-icon-clear-btn" data-target-input="ms-icon-val-' . $safeKey . '" data-target-preview="ms-icon-prev-' . $safeKey . '" data-target-label="ms-icon-lbl-' . $safeKey . '" '
                        . 'style="font-size:11px;background:none;border:1px solid #ddd;border-radius:4px;padding:3px 8px;cursor:pointer;color:#888" title="Remove icon">&times; Clear</button>';
            }
            $input .= '</div>';
            break;
        case 'numeric':
            $input = $form->generate_numeric_field('opt_' . $key, $val, array('style' => 'width:150px'));
            break;
        case 'image':
            $input = '';
            if (!empty($val)) {
                $previewUrl = htmlspecialchars_uni($mybb->settings['bburl'] . '/' . $val);
                $input .= '<div style="margin-bottom:8px">'
                        . '<img src="' . $previewUrl . '" style="max-width:200px;max-height:100px;border:1px solid #ddd;border-radius:4px;padding:4px;background:#f9f9f9" />'
                        . '<br /><small style="color:#888">Current: ' . htmlspecialchars_uni($val) . '</small>'
                        . '</div>';
                $input .= '<label style="display:inline-flex;align-items:center;gap:4px;margin-bottom:8px;cursor:pointer">'
                        . '<input type="checkbox" name="opt_' . htmlspecialchars_uni($key) . '_remove" value="1" /> '
                        . '<span style="font-size:12px;color:#c0392b">Remove current image</span></label><br />';
            }
            $input .= '<input type="file" name="opt_' . htmlspecialchars_uni($key) . '_file" accept="image/*,.ico" />';
            $input .= '<br /><small style="color:#888">Max 5MB. Allowed: PNG, JPG, GIF, SVG, WebP, ICO</small>';
            if (!empty($def['has_dimensions'])) {
                $wVal = isset($values[$key . '_width'])  ? $values[$key . '_width']  : (isset($def['default_width'])  ? $def['default_width']  : '');
                $hVal = isset($values[$key . '_height']) ? $values[$key . '_height'] : (isset($def['default_height']) ? $def['default_height'] : '');
                $input .= '<div style="margin-top:8px;display:flex;align-items:center;gap:8px">'
                        . '<label style="font-size:12px">Width: <input type="number" name="opt_' . htmlspecialchars_uni($key) . '_width" value="' . htmlspecialchars_uni($wVal) . '" style="width:80px" min="0" /> px</label>'
                        . '<label style="font-size:12px">Height: <input type="number" name="opt_' . htmlspecialchars_uni($key) . '_height" value="' . htmlspecialchars_uni($hVal) . '" style="width:80px" min="0" /> px</label>'
                        . '<small style="color:#888">(0 or empty = auto)</small>'
                        . '</div>';
            }
            break;
        default:
            $input = $form->generate_text_box('opt_' . $key, $val, array('style' => 'width:95%'));
            break;
    }

    $form_container->output_row($title, $desc, $input);
}

/* ====================================================================
   Page Manager â€” API: Save Page (AJAX)
   ==================================================================== */

if ($action === 'pages_api') {
    header('Content-Type: application/json; charset=utf-8');
    verify_post_check($mybb->get_input('my_post_key'));

    $api_action = $mybb->get_input('api_action');

    // â”€â”€ Save page â”€â”€
    if ($api_action === 'save') {
        $pid = intval($mybb->get_input('pid'));
        $clean_slug = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $mybb->get_input('slug'));
        $clean_groups = preg_replace('/[^0-9,]/', '', $mybb->get_input('allowed_groups'));

        // Data for insert_query/update_query â€” do NOT pre-escape (these functions escape internally)
        $data = array(
            'title'            => $mybb->get_input('title'),
            'slug'             => $clean_slug,
            'content'          => $mybb->get_input('content'),
            'status'           => in_array($mybb->get_input('status'), array('draft','published')) ? $mybb->get_input('status') : 'draft',
            'meta_title'       => $mybb->get_input('meta_title'),
            'meta_description' => $mybb->get_input('meta_description'),
            'allowed_groups'   => $clean_groups,
            'custom_css'       => $mybb->get_input('custom_css'),
            'custom_js'        => $mybb->get_input('custom_js'),
            'updated_at'       => TIME_NOW,
        );

        // Check slug uniqueness (simple_select WHERE clause needs manual escaping)
        $slug_esc = $db->escape_string($clean_slug);
        $slugCheck = $db->simple_select('ms_pages', 'pid', "slug='" . $slug_esc . "'" . ($pid ? " AND pid != {$pid}" : ''));
        if ($db->num_rows($slugCheck)) {
            echo json_encode(array('error' => 'A page with this slug already exists.'));
            exit;
        }

        if ($pid > 0) {
            $db->update_query('ms_pages', $data, "pid={$pid}");
        } else {
            $data['author_uid'] = intval($mybb->user['uid']);
            $data['created_at'] = TIME_NOW;
            // Get next disporder
            $query = $db->simple_select('ms_pages', 'MAX(disporder) as maxd');
            $data['disporder'] = intval($db->fetch_field($query, 'maxd')) + 1;
            $pid = $db->insert_query('ms_pages', $data);
        }

        echo json_encode(array('success' => true, 'pid' => $pid));
        exit;
    }

    // â”€â”€ Get page data â”€â”€
    if ($api_action === 'get') {
        $pid = intval($mybb->get_input('pid'));
        $query = $db->simple_select('ms_pages', '*', "pid={$pid}");
        $row = $db->fetch_array($query);
        if (!$row) {
            echo json_encode(array('error' => 'Page not found.'));
        } else {
            echo json_encode(array('success' => true, 'page' => $row));
        }
        exit;
    }

    // â”€â”€ Reorder pages â”€â”€
    if ($api_action === 'reorder') {
        $order = @json_decode($mybb->get_input('order'), true);
        if (is_array($order)) {
            foreach ($order as $i => $pid) {
                $db->update_query('ms_pages', array('disporder' => intval($i)), "pid=" . intval($pid));
            }
        }
        echo json_encode(array('success' => true));
        exit;
    }

    // â”€â”€ Check slug availability â”€â”€
    if ($api_action === 'check_slug') {
        $slug = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $mybb->get_input('slug'));
        $pid = intval($mybb->get_input('pid'));
        if (!$slug) {
            echo json_encode(array('available' => false));
            exit;
        }
        $where = "slug='" . $db->escape_string($slug) . "'";
        if ($pid > 0) $where .= " AND pid != {$pid}";
        $check = $db->simple_select('ms_pages', 'pid', $where);
        if (!$db->num_rows($check)) {
            echo json_encode(array('available' => true));
        } else {
            // Find unique suggestion
            $base = preg_replace('/-\d+$/', '', $slug);
            $suggestion = '';
            for ($i = 2; $i <= 100; $i++) {
                $candidate = $base . '-' . $i;
                $cWhere = "slug='" . $db->escape_string($candidate) . "'";
                if ($pid > 0) $cWhere .= " AND pid != {$pid}";
                $cCheck = $db->simple_select('ms_pages', 'pid', $cWhere);
                if (!$db->num_rows($cCheck)) {
                    $suggestion = $candidate;
                    break;
                }
            }
            echo json_encode(array('available' => false, 'suggestion' => $suggestion));
        }
        exit;
    }

    // â”€â”€ Set front page â”€â”€
    if ($api_action === 'set_front_page') {
        $front_page_type = $mybb->get_input('front_page_type');
        $front_page_slug = '';

        if ($front_page_type === 'page') {
            $front_page_slug = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $mybb->get_input('front_page_slug'));
            // Verify page exists
            $check = $db->simple_select('ms_pages', 'pid', "slug='" . $db->escape_string($front_page_slug) . "'");
            if (!$db->num_rows($check)) {
                echo json_encode(array('error' => 'Selected page does not exist.'));
                exit;
            }
        } elseif ($front_page_type === 'portal') {
            // Portal â€” no slug needed
        } else {
            $front_page_type = 'default';
        }

        $cache->update('ms_front_page', array(
            'type' => $front_page_type,
            'slug' => $front_page_slug,
        ));

        echo json_encode(array('success' => true));
        exit;
    }

    echo json_encode(array('error' => 'Unknown API action.'));
    exit;
}

/* ====================================================================
   Page Manager â€” Delete Page
   ==================================================================== */

if ($action === 'pages_delete') {
    verify_post_check($mybb->get_input('my_post_key'));
    $pid = intval($mybb->get_input('pid'));
    if ($pid > 0) {
        $db->delete_query('ms_pages', "pid={$pid}");
        flash_message('Page deleted successfully.', 'success');
    }
    admin_redirect("index.php?module=mystudio-pages");
}

/* ====================================================================
   Page Manager â€” Add / Edit Page (HTML Editor)
   ==================================================================== */

if ($action === 'pages_add' || $action === 'pages_edit') {
    $pid = intval($mybb->get_input('pid'));
    $pageData = array();

    if ($action === 'pages_edit' && $pid > 0) {
        $query = $db->simple_select('ms_pages', '*', "pid={$pid}");
        $pageData = $db->fetch_array($query);
        if (!$pageData) {
            flash_message('Page not found.', 'error');
            admin_redirect("index.php?module=mystudio-pages");
        }
    }

    // Load usergroups for permission selector
    $usergroups = array();
    $query = $db->simple_select('usergroups', 'gid, title', '', array('order_by' => 'title'));
    while ($row = $db->fetch_array($query)) {
        $usergroups[] = $row;
    }

    $page->add_breadcrumb_item("MyStudio", "index.php?module=mystudio-manage");
    $page->add_breadcrumb_item("Page Manager", "index.php?module=mystudio-pages");
    $page->add_breadcrumb_item($action === 'pages_edit' ? "Edit Page" : "Add Page");

    $page->output_header("MyStudio - Page Editor");

    $pageDataJson = json_encode($pageData ?: new stdClass());
    $usergroupsJson = json_encode($usergroups);
    $bbnameJson = json_encode($mybb->settings['bbname']);
    $bburlJson = json_encode($mybb->settings['bburl']);
    $postKey = $mybb->post_code;

    echo <<<HTML
<link rel="stylesheet" href="../jscripts/mystudio/pagebuilder.css" />

<div id="pb-builder" data-pid="{$pid}" data-post-key="{$postKey}">
    <!-- Top Bar -->
    <div class="pb-topbar">
        <div class="pb-topbar-left">
            <a href="index.php?module=mystudio-pages" class="pb-topbar-btn" title="Back"><i class="bi bi-arrow-left"></i></a>
            <input type="text" id="pb-title" class="pb-title-input" placeholder="Page Title" />
            <input type="text" id="pb-slug" class="pb-slug-input" placeholder="page-slug" />
            <span class="pb-permalink"><i class="bi bi-link-45deg"></i> <span id="pb-permalink">&mdash;</span></span>
        </div>
        <div class="pb-topbar-right">
            <select id="pb-status" class="pb-status-select">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
            </select>
            <button type="button" id="pb-preview" class="pb-topbar-btn" title="Preview"><i class="bi bi-eye"></i> Preview</button>
            <button type="button" id="pb-save" class="pb-topbar-btn pb-btn-primary"><i class="bi bi-check-lg"></i> Save</button>
        </div>
    </div>

    <!-- Editor Body -->
    <div class="pb-editor-body">
        <div id="pb-monaco-container" class="pb-monaco-container"></div>
    </div>

    <!-- Page Settings (collapsible bottom) -->
    <div class="pb-settings-wrap">
        <button type="button" id="pb-settings-toggle" class="pb-settings-toggle"><i class="bi bi-chevron-up"></i> Page Settings</button>
        <div id="pb-settings" class="pb-settings" style="display:none">
            <div class="pb-settings-grid">
                <div class="pb-field"><label>Meta Title</label><input type="text" class="pb-input" id="pb-meta-title" placeholder="Optional SEO title" /></div>
                <div class="pb-field"><label>Meta Description</label><textarea class="pb-input" id="pb-meta-desc" rows="2" placeholder="Optional SEO description"></textarea></div>
                <div class="pb-field"><label>Allowed Groups <small>(empty = all)</small></label><div id="pb-groups"></div></div>
                <div class="pb-field"><label>Custom CSS</label><textarea class="pb-input pb-code" id="pb-custom-css" rows="3" placeholder="/* page-specific CSS */"></textarea></div>
                <div class="pb-field"><label>Custom JS</label><textarea class="pb-input pb-code" id="pb-custom-js" rows="3" placeholder="// page-specific JS"></textarea></div>
            </div>
        </div>
    </div>

    <!-- Variables Reference (below settings) -->
    <div class="pb-vars-wrap">
        <button type="button" id="pb-vars-toggle" class="pb-settings-toggle"><i class="bi bi-braces"></i> Available Variables</button>
        <div id="pb-vars" class="pb-vars-panel" style="display:none">
            <p class="pb-vars-note">Click a variable to insert it at the cursor position in the editor.</p>
            <div class="pb-vars-grid">
                <div class="pb-vars-group">
                    <h4>Page Shell</h4>
                    <code class="pb-var-item" data-var="{\$headerinclude}">{\$headerinclude}</code>
                    <code class="pb-var-item" data-var="{\$header}">{\$header}</code>
                    <code class="pb-var-item" data-var="{\$footer}">{\$footer}</code>
                </div>
                <div class="pb-vars-group">
                    <h4>User</h4>
                    <code class="pb-var-item" data-var="{\$mybb->user['username']}">{\$mybb->user['username']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->user['uid']}">{\$mybb->user['uid']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->user['avatar']}">{\$mybb->user['avatar']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->user['usergroup']}">{\$mybb->user['usergroup']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->user['postnum']}">{\$mybb->user['postnum']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->user['reputation']}">{\$mybb->user['reputation']}</code>
                </div>
                <div class="pb-vars-group">
                    <h4>Board</h4>
                    <code class="pb-var-item" data-var="{\$mybb->settings['bbname']}">{\$mybb->settings['bbname']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->settings['bburl']}">{\$mybb->settings['bburl']}</code>
                    <code class="pb-var-item" data-var="{\$mybb->settings['bbdesc']}">{\$mybb->settings['bbdesc']}</code>
                </div>
                <div class="pb-vars-group">
                    <h4>Global Templates</h4>
                    <code class="pb-var-item" data-var="{\$welcomeblock}">{\$welcomeblock}</code>
                    <code class="pb-var-item" data-var="{\$pm_notice}">{\$pm_notice}</code>
                    <code class="pb-var-item" data-var="{\$boardstats}">{\$boardstats}</code>
                    <code class="pb-var-item" data-var="{\$nav}">{\$nav}</code>
                    <code class="pb-var-item" data-var="{\$forums}">{\$forums}</code>
                    <code class="pb-var-item" data-var="{\$search}">{\$search}</code>
                </div>
                <div class="pb-vars-group">
                    <h4>Conditionals</h4>
                    <code class="pb-var-item" data-var="&lt;if \$mybb-&gt;user['uid'] then&gt;logged in&lt;else&gt;guest&lt;/if&gt;">if / else</code>
                    <code class="pb-var-item" data-var="&lt;if \$mybb-&gt;usergroup['cancp'] then&gt;admin content&lt;/if&gt;">if (admin)</code>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.50.0/min/vs/loader.js"></script>
<script>
window.MS_PAGE_DATA = {$pageDataJson};
window.MS_PAGE_USERGROUPS = {$usergroupsJson};
window.MS_PAGE_BBNAME = {$bbnameJson};
window.MS_PAGE_BBURL = {$bburlJson};
</script>
<script src="../jscripts/mystudio/pagebuilder.js"></script>
HTML;

    $page->output_footer();
    exit;
}

/* ====================================================================
   Page Manager â€” List Pages
   ==================================================================== */

if ($action === 'pages') {
    $page->add_breadcrumb_item("MyStudio", "index.php?module=mystudio-manage");
    $page->add_breadcrumb_item("Page Manager");

    $page->output_header("MyStudio - Page Manager");

    // List pages
    $pages = array();
    if ($db->table_exists('ms_pages')) {
        $query = $db->simple_select('ms_pages', '*', '', array('order_by' => 'disporder', 'order_dir' => 'ASC'));
        while ($row = $db->fetch_array($query)) {
            $pages[] = $row;
        }
    }

    // â”€â”€ Buttons at top â”€â”€
    $page->output_nav_tabs(array('pages' => array('title' => 'Page Manager', 'link' => 'index.php?module=mystudio-pages&action=pages')), 'pages');

    // â”€â”€ Front Page Selector â”€â”€
    $front_page_data = $cache->read('ms_front_page');
    $fp_type = is_array($front_page_data) ? ($front_page_data['type'] ?? 'default') : 'default';
    $fp_slug = is_array($front_page_data) ? ($front_page_data['slug'] ?? '') : '';

    $fpOptions = array('default' => 'Default (Forum Index)', 'portal' => 'Portal Page');
    foreach ($pages as $p) {
        if ($p['status'] === 'published') {
            $fpOptions['page:' . $p['slug']] = $p['title'] . ' (/' . $p['slug'] . ')';
        }
    }
    $fpCurrent = $fp_type;
    if ($fp_type === 'page') {
        $fpCurrent = 'page:' . $fp_slug;
    }

    $fpForm = new Form("index.php?module=mystudio-pages_api&action=pages_api", "post", "ms_front_page_form");
    echo $fpForm->generate_hidden_field('api_action', 'set_front_page');
    $fpContainer = new FormContainer("Front Page");
    $fpContainer->output_row(
        "Front Page",
        "Set which page visitors see at your forum's root URL. The forum list remains accessible at <code>index.php?forums</code>.",
        $fpForm->generate_select_box('front_page_selection', $fpOptions, $fpCurrent)
    );
    $fpContainer->end();
    $fpButtons = array(
        $fpForm->generate_submit_button("Save Front Page"),
        '<input type="button" class="submit_button" value="Add Page" onclick="window.location.href=\'index.php?module=mystudio-pages_add&action=pages_add\';" />'
    );
    $fpForm->output_submit_wrapper($fpButtons);
    $fpForm->end();

    echo '<script>
document.getElementById("ms_front_page_form").addEventListener("submit", function(e) {
    e.preventDefault();
    var sel = this.querySelector("[name=front_page_selection]");
    var val = sel.value, type = "default", slug = "";
    if (val === "portal") { type = "portal"; }
    else if (val.indexOf("page:") === 0) { type = "page"; slug = val.substring(5); }
    var fd = new FormData();
    fd.append("my_post_key", "' . $mybb->post_code . '");
    fd.append("api_action", "set_front_page");
    fd.append("front_page_type", type);
    fd.append("front_page_slug", slug);
    fetch("index.php?module=mystudio-pages_api&action=pages_api", { method: "POST", body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                $.jGrowl("Front page saved.", {theme: "jgrowl_success"});
            } else {
                $.jGrowl(data.error || "Error saving.", {theme: "jgrowl_error"});
            }
        });
});
</script>';

    // â”€â”€ Pages Table â”€â”€
    $table = new Table;
    $table->construct_header("Title");
    $table->construct_header("Slug", array('width' => '15%'));
    $table->construct_header("Status", array('class' => 'align_center', 'width' => '10%'));
    $table->construct_header("Author", array('class' => 'align_center', 'width' => '12%'));
    $table->construct_header("Last Updated", array('class' => 'align_center', 'width' => '15%'));
    $table->construct_header("Actions", array('class' => 'align_center', 'width' => '150'));

    if (empty($pages)) {
        $table->construct_cell('No pages created yet. <a href="index.php?module=mystudio-pages_add&action=pages_add">Create your first page</a>.', array('colspan' => 6, 'class' => 'align_center'));
        $table->construct_row();
    }

    // Pre-load all page authors in a single query
    $authorMap = array();
    $authorUids = array();
    foreach ($pages as $p) {
        if ($p['author_uid'] > 0) {
            $authorUids[] = intval($p['author_uid']);
        }
    }
    if (!empty($authorUids)) {
        $uidList = implode(',', array_unique($authorUids));
        $aquery = $db->simple_select('users', 'uid, username', "uid IN ({$uidList})");
        while ($arow = $db->fetch_array($aquery)) {
            $authorMap[(int)$arow['uid']] = $arow['username'];
        }
    }

    foreach ($pages as $p) {
        $authorName = isset($authorMap[(int)$p['author_uid']])
            ? htmlspecialchars_uni($authorMap[(int)$p['author_uid']])
            : 'System';

        $statusLabel = $p['status'] === 'published'
            ? '<strong>Published</strong>'
            : 'Draft';

        $updatedAt = $p['updated_at'] > 0 ? my_date('relative', $p['updated_at']) : my_date('relative', $p['created_at']);
        $viewUrl = $mybb->settings['bburl'] . '/' . htmlspecialchars_uni($p['slug']);

        $table->construct_cell('<strong>' . htmlspecialchars_uni($p['title']) . '</strong>');
        $table->construct_cell('<code>' . htmlspecialchars_uni($p['slug']) . '</code>');
        $table->construct_cell($statusLabel, array('class' => 'align_center'));
        $table->construct_cell($authorName, array('class' => 'align_center'));
        $table->construct_cell('<small>' . $updatedAt . '</small>', array('class' => 'align_center'));

        $actions = '<a href="index.php?module=mystudio-pages_edit&action=pages_edit&pid=' . intval($p['pid']) . '">Edit</a>'
                 . ' | <a href="' . $viewUrl . '" target="_blank">View</a>'
                 . ' | <a href="index.php?module=mystudio-pages_delete&action=pages_delete&pid=' . intval($p['pid']) . '&my_post_key=' . $mybb->post_code . '" onclick="return confirm(\'Delete this page?\');" style="color: red;">Delete</a>';
        $table->construct_cell($actions, array('class' => 'align_center'));
        $table->construct_row();
    }

    $table->output("Pages");

    $page->output_footer();
    exit;
}

if ($action === 'api_filetree') {
    header('Content-Type: application/json');
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $tree = $ms->getFileTree($slug);
    if ($tree === false) {
        echo json_encode(array('error' => 'Theme not found on disk.'));
    } else {
        echo json_encode($tree);
    }
    exit;
}

if ($action === 'api_readfile') {
    header('Content-Type: application/json');
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $data = $ms->readThemeFile($slug, $path);
    if ($data === false) {
        echo json_encode(array('error' => 'Cannot read file.'));
    } else {
        echo json_encode($data);
    }
    exit;
}

if ($action === 'api_savefile') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug    = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path    = $mybb->get_input('path');
    $content = $mybb->get_input('content');
    $ok = $ms->writeThemeFile($slug, $path, $content, true);
    echo json_encode(array('success' => $ok, 'time' => date('H:i:s'), 'errors' => $ms->getErrors()));
    exit;
}

if ($action === 'api_filelist') {
    header('Content-Type: application/json');
    $slug  = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $files = $ms->getFlatFileList($slug);
    echo json_encode(array('files' => $files !== false ? $files : array()));
    exit;
}

if ($action === 'api_createfile') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $ok   = $ms->createThemeFile($slug, $path);
    echo json_encode(array('success' => $ok, 'errors' => $ms->getErrors()));
    exit;
}

if ($action === 'api_createfolder') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $ok   = $ms->createThemeFolder($slug, $path);
    echo json_encode(array('success' => $ok, 'errors' => $ms->getErrors()));
    exit;
}

if ($action === 'api_deletefile') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $ok   = $ms->deleteThemeFile($slug, $path);
    echo json_encode(array('success' => $ok, 'errors' => $ms->getErrors()));
    exit;
}

if ($action === 'api_deletefolder') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $ok   = $ms->deleteThemeFolder($slug, $path);
    echo json_encode(array('success' => $ok, 'errors' => $ms->getErrors()));
    exit;
}

if ($action === 'api_rename') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug    = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $oldPath = $mybb->get_input('old_path');
    $newPath = $mybb->get_input('new_path');
    $ok = $ms->renameThemePath($slug, $oldPath, $newPath);
    echo json_encode(array('success' => $ok, 'errors' => $ms->getErrors()));
    exit;
}

if ($action === 'api_sync') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $tid  = $ms->syncToDatabase($slug);
    echo json_encode(array(
        'success' => $tid !== false,
        'tid'     => $tid,
        'errors'  => $ms->getErrors()
    ));
    exit;
}

/* ====================================================================
   Editor Page (full-page Monaco editor)
   ==================================================================== */

if ($action === 'editor') {
    $slug = $mybb->get_input('slug');
    if (empty($slug)) {
        flash_message('No theme specified.', 'error');
        admin_redirect("index.php?module=mystudio-manage");
    }

    // Verify the theme directory exists
    $themeDir = MYBB_ROOT . 'themes/' . preg_replace('/[^a-z0-9\-]/', '', $slug);
    if (!is_dir($themeDir)) {
        flash_message('Theme directory not found on disk.', 'error');
        admin_redirect("index.php?module=mystudio-manage");
    }

    $page->add_breadcrumb_item("MyStudio", "index.php?module=mystudio-manage");
    $page->add_breadcrumb_item("Editor: " . htmlspecialchars_uni($slug));

    // Monaco loader CDN
    $page->extra_header .= '
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.50.0/min/vs/loader.min.js"></script>';

    $page->output_header("MyStudio - Editor");

    $post_key = $mybb->post_code;
    $base_url = "index.php?module=mystudio-manage";
    $safe_slug = htmlspecialchars_uni($slug);
    $bburl = $mybb->settings['bburl'];

    echo <<<HTML
<div id="msEditorConfig"
     data-base-url="{$base_url}"
     data-post-key="{$post_key}"
     data-slug="{$safe_slug}"
     style="display:none"></div>

<style>
/* -- MyStudio Editor Styles (Light) -- */
.ms-editor-wrap{display:flex;height:75vh;background:#ffffff;border-radius:6px;overflow:hidden;position:relative;border:1px solid #dee2e6}
#ms-sidebar{width:260px;min-width:160px;background:#f5f5f5;display:flex;flex-direction:column;border-right:1px solid #dee2e6;transition:width .2s}
#ms-sidebar.collapsed{width:0;min-width:0;overflow:hidden;border-right:none}
#ms-btn-collapse{background:#e8e8e8;border:none;color:#666;font-size:16px;cursor:pointer;padding:6px 3px;border-radius:0;line-height:1;display:flex;align-items:center;z-index:2;border-right:1px solid #dee2e6}
#ms-btn-collapse:hover{background:#ddd;color:#333}
.ms-sidebar-header{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;background:#e8e8e8;border-bottom:1px solid #dee2e6;gap:4px}
.ms-sidebar-header .ms-sidebar-title{font-size:11px;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;overflow:hidden}
.ms-sidebar-btns{display:flex;gap:2px}
.ms-sidebar-btns button{background:none;border:1px solid transparent;color:#666;font-size:14px;cursor:pointer;padding:2px 5px;border-radius:3px;line-height:1}
.ms-sidebar-btns button:hover{background:#ddd;color:#333;border-color:#ccc}
.ms-search-wrap{padding:6px 8px;border-bottom:1px solid #dee2e6}
#ms-file-search{width:100%;background:#fff;border:1px solid #ccc;color:#333;padding:4px 8px;border-radius:3px;font-size:12px;outline:none;box-sizing:border-box}
#ms-file-search:focus{border-color:#0d9488}
#ms-file-tree{flex:1;overflow:auto;padding:4px 0;font-size:13px;font-family:Consolas,'Courier New',monospace}
.ms-tree-item{display:flex;align-items:center;padding:3px 8px;cursor:pointer;color:#333;white-space:nowrap;gap:4px;user-select:none}
.ms-tree-item:hover{background:#e8f0fe}
.ms-tree-item.active{background:#d3e3fd;color:#1a1a1a}
.ms-tree-arrow{font-size:10px;width:14px;text-align:center;color:#888;flex-shrink:0}
.ms-tree-icon{font-size:14px;flex-shrink:0}
.ms-tree-name{flex:1;overflow:hidden;text-overflow:ellipsis}
.ms-tree-children{padding-left:16px}
.ms-tree-folder-dirty{background:#e2b340;color:#fff;font-size:9px;font-weight:bold;padding:0 4px;border-radius:3px;margin-left:4px}
.ms-loading,.ms-error{padding:20px;color:#888;text-align:center;font-size:13px}
.ms-error{color:#c0392b}
#ms-resize-handle{width:4px;background:#dee2e6;cursor:col-resize;flex-shrink:0;transition:background .15s}
#ms-resize-handle:hover,#ms-resize-handle.active{background:#0d9488}
.ms-main{flex:1;display:flex;flex-direction:column;min-width:0}
.ms-tabs-bar{display:flex;background:#f5f5f5;border-bottom:1px solid #dee2e6;overflow-x:auto;flex-shrink:0;min-height:35px}
.ms-tab{display:flex;align-items:center;padding:6px 12px;color:#666;font-size:12px;cursor:pointer;border-right:1px solid #dee2e6;white-space:nowrap;gap:6px;font-family:Consolas,monospace;max-width:180px}
.ms-tab:hover{background:#e8f0fe;color:#333}
.ms-tab.active{background:#ffffff;color:#1a1a1a;border-bottom:2px solid #0d9488}
.ms-tab .ms-tab-dirty{color:#b8860b;font-weight:bold}
.ms-tab .ms-tab-close{opacity:.5;font-size:14px;line-height:1}
.ms-tab .ms-tab-close:hover{opacity:1;color:#c0392b}
#ms-monaco{flex:1;min-height:0}
.ms-monaco-placeholder{display:flex;align-items:center;justify-content:center;height:100%;color:#999;font-size:15px;font-style:italic}
.ms-statusbar{display:flex;align-items:center;justify-content:space-between;padding:2px 12px;background:#007acc;color:#fff;font-size:11px;flex-shrink:0}
.ms-notify{position:fixed;top:20px;right:20px;padding:10px 20px;border-radius:6px;color:#fff;font-size:13px;z-index:99999;animation:msFadeIn .3s;transition:opacity .3s}
.ms-notify-success{background:#0d9488}
.ms-notify-error{background:#c0392b}
.ms-notify-info{background:#007acc}
@keyframes msFadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.ms-status-saving{color:#b8860b}
.ms-status-saved{color:#0d9488}
.ms-status-error{color:#c0392b}
.ms-context-menu{position:fixed;background:#ffffff;border:1px solid #dee2e6;border-radius:4px;padding:4px 0;min-width:160px;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,.15);font-size:12px}
.ms-context-menu-item{padding:6px 16px;color:#333;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:6px}
.ms-context-menu-item:hover{background:#e8f0fe;color:#1a1a1a}
.ms-context-menu-item.danger{color:#c0392b}
.ms-context-menu-item.danger:hover{background:#fde8e8;color:#a02020}
.ms-context-menu-icon{font-size:14px;flex-shrink:0}
.ms-context-menu-sep{height:1px;background:#dee2e6;margin:4px 0}
</style>

<div class="ms-editor-wrap">
    <div id="ms-sidebar">
        <div class="ms-sidebar-header">
            <span class="ms-sidebar-title">Explorer</span>
            <div class="ms-sidebar-btns">
                <button id="ms-btn-newfile" title="New File"><i class="bi bi-file-earmark-plus"></i></button>
                <button id="ms-btn-newfolder" title="New Folder"><i class="bi bi-folder-plus"></i></button>
                <button id="ms-btn-savesync" title="Save &amp; Sync"><i class="bi bi-floppy"></i></button>
                <button id="ms-btn-collapse-all" title="Collapse All Folders"><i class="bi bi-arrows-collapse"></i></button>
            </div>
        </div>
        <div class="ms-search-wrap">
            <input type="text" id="ms-file-search" placeholder="Search files..." />
        </div>
        <div id="ms-file-tree"></div>
    </div>
    <button id="ms-btn-collapse" title="Toggle Sidebar"><i class="bi bi-layout-sidebar-inset"></i></button>
    <div id="ms-resize-handle"></div>
    <div class="ms-main">
        <div class="ms-tabs-bar" id="ms-tab-bar"></div>
        <div id="ms-monaco">
            <div class="ms-monaco-placeholder">Select a file to begin editing</div>
        </div>
        <div class="ms-statusbar">
            <span id="ms-status-sync">Ready</span>
            <div style="display:flex;gap:16px">
                <span id="ms-status-pos"></span>
                <span id="ms-status-lang"></span>
            </div>
        </div>
    </div>
</div>

<div id="ms-notifications" style="position:fixed;top:20px;right:20px;z-index:100000;display:flex;flex-direction:column;gap:8px"></div>

<script src="{$bburl}/jscripts/mystudio/editor.js"></script>
HTML;

    $page->output_footer();
    exit;
}

/* ====================================================================
   Export Download (POST handler â€” keeps working via form submit)
   ==================================================================== */

if ($action === 'export') {
    if ($mybb->request_method === 'post' && !empty($mybb->input['tid'])) {
        verify_post_check($mybb->get_input('my_post_key'));

        $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
        $zipPath = $ms->exportTheme($tid);

        if ($zipPath && file_exists($zipPath)) {
            $filename = basename($zipPath);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            $ms->cleanup();
            exit;
        } else {
            $errors = $ms->getErrors();
            flash_message(implode('<br>', $errors), 'error');
            admin_redirect("index.php?module=mystudio-import_export");
        }
    }
    // GET request on old /export URL â†’ redirect to combined page
    admin_redirect("index.php?module=mystudio-import_export");
}

/* ====================================================================
   Import Upload (POST handler â€” redirect back to combined page)
   ==================================================================== */

if ($action === 'import') {
    if ($mybb->request_method === 'post' && isset($_FILES['theme_zip'])) {
        verify_post_check($mybb->get_input('my_post_key'));

        $parentTid = $mybb->get_input('parent_tid', MyBB::INPUT_INT);
        if ($parentTid < 1) $parentTid = 1;

        $tid = $ms->importFromZip($_FILES['theme_zip'], $parentTid);

        if ($tid) {
            flash_message('Theme imported successfully (TID: ' . $tid . ').', 'success');
            admin_redirect("index.php?module=mystudio-manage");
        } else {
            $errors = $ms->getErrors();
            flash_message(implode('<br>', $errors), 'error');
            admin_redirect("index.php?module=mystudio-import_export");
        }
    }
    // GET request on old /import URL â†’ redirect to combined page
    admin_redirect("index.php?module=mystudio-import_export");
}

/* ====================================================================
   Import / Export â€” Combined Page
   ==================================================================== */

if ($action === 'import_export') {
    $page->add_breadcrumb_item("MyStudio", "index.php?module=mystudio-manage");
    $page->add_breadcrumb_item("Import / Export");

    $page->output_header("MyStudio - Import / Export");

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  EXPORT SECTION
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $themes = $ms->listDbThemes();

    $exportForm = new Form("index.php?module=mystudio-export", "post");

    $table = new Table;
    $table->construct_header("Theme");
    $table->construct_header("Status", array('width' => 130, 'class' => 'align_center'));
    $table->construct_header("Action", array('width' => 160, 'class' => 'align_center'));

    if (empty($themes)) {
        $table->construct_cell("No themes found in the database.", array('colspan' => 3));
        $table->construct_row();
    } else {
        foreach ($themes as $t) {
            $nameCell = htmlspecialchars_uni($t['name']);
            if ($t['is_default']) {
                $nameCell .= ' <strong>(Default)</strong>';
            }
            $table->construct_cell($nameCell);

            $status = $t['has_disk'] ? 'On Disk' : 'DB Only';
            $table->construct_cell($status, array('class' => 'align_center'));

            $table->construct_cell('<button type="submit" name="tid" value="' . $t['tid'] . '" class="submit_button">Download ZIP</button>', array('class' => 'align_center'));
            $table->construct_row();
        }
    }

    $table->output("Export Theme");
    echo $exportForm->end();

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  IMPORT SECTION
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $importForm = new Form("index.php?module=mystudio-import", "post", "import_form", 1);

    $form_container = new FormContainer("Import Theme from ZIP");
    $form_container->output_row(
        "Theme ZIP File",
        "Upload a <code>.zip</code> theme package containing <code>theme.json</code> and <code>templates/</code>.",
        $importForm->generate_file_upload_box('theme_zip')
    );

    // Parent theme selector
    $parentOptions = array(1 => 'Master Style');
    $query = $db->simple_select('themes', 'tid, name', "tid != 1", array('order_by' => 'name'));
    while ($t = $db->fetch_array($query)) {
        $parentOptions[(int) $t['tid']] = htmlspecialchars_uni($t['name']);
    }
    $form_container->output_row(
        "Parent Theme",
        "Select the parent theme for this import.",
        $importForm->generate_select_box('parent_tid', $parentOptions, 1)
    );

    $form_container->end();

    $buttons = array($importForm->generate_submit_button("Import Theme"));
    $importForm->output_submit_wrapper($buttons);
    echo $importForm->end();



    $page->output_footer();
    exit;
}

/* ====================================================================
   Activate / Deactivate Theme
   ==================================================================== */

if ($action === 'activate') {
    verify_post_check($mybb->get_input('my_post_key'));
    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
    if ($ms->activateTheme($tid)) {
        flash_message('Theme activated as default.', 'success');
    } else {
        flash_message(implode('<br>', $ms->getErrors()), 'error');
    }
    admin_redirect("index.php?module=mystudio-manage");
}

if ($action === 'deactivate') {
    verify_post_check($mybb->get_input('my_post_key'));
    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
    if ($ms->deactivateTheme($tid)) {
        flash_message('Theme deactivated.', 'success');
    } else {
        flash_message(implode('<br>', $ms->getErrors()), 'error');
    }
    admin_redirect("index.php?module=mystudio-manage");
}

/* ====================================================================
   Delete Theme (DB + disk)
   ==================================================================== */

if ($action === 'delete_theme') {
    verify_post_check($mybb->get_input('my_post_key'));
    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
    $deleteDisk = $mybb->get_input('disk', MyBB::INPUT_INT) ? true : false;

    if ($tid > 0) {
        // DB theme (may also delete disk)
        if ($ms->deleteTheme($tid, $deleteDisk)) {
            flash_message('Theme deleted successfully.', 'success');
        } else {
            flash_message(implode('<br>', $ms->getErrors()), 'error');
        }
    } else {
        // Disk-only theme (no DB entry) â€” delete folder directly
        $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
        if (!empty($slug) && $slug !== 'mystudio-default') {
            $themeDir = MYBB_ROOT . 'themes/' . $slug;
            if (is_dir($themeDir)) {
                $ms->rrmdir($themeDir);
                flash_message('Theme directory deleted: themes/' . htmlspecialchars_uni($slug) . '/', 'success');
            } else {
                flash_message('Theme directory not found.', 'error');
            }
        } else {
            flash_message('Invalid theme or cannot delete the default theme.', 'error');
        }
    }
    admin_redirect("index.php?module=mystudio-manage");
}

/* ====================================================================
   Sync Theme (disk -> database)
   ==================================================================== */

if ($action === 'sync_theme') {
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = $mybb->get_input('slug');
    $tid  = $ms->syncToDatabase($slug);
    if ($tid) {
        flash_message('Theme synced to database (TID: ' . $tid . ').', 'success');
    } else {
        flash_message(implode('<br>', $ms->getErrors()), 'error');
    }
    admin_redirect("index.php?module=mystudio-manage");
}

/* ====================================================================
   Convert DB Theme to Disk
   ==================================================================== */

if ($action === 'convert') {
    verify_post_check($mybb->get_input('my_post_key'));
    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);

    $query = $db->simple_select('themes', 'name', "tid='{$tid}'");
    $theme = $db->fetch_array($query);
    if ($theme) {
        $slug = $ms->slug($theme['name']);
        $result = $ms->extractThemeToDisk($tid, $slug);
        if ($result) {
            flash_message('Theme extracted to themes/' . htmlspecialchars_uni($slug) . '/.', 'success');
        } else {
            flash_message(implode('<br>', $ms->getErrors()), 'error');
        }
    } else {
        flash_message('Theme not found.', 'error');
    }
    admin_redirect("index.php?module=mystudio-manage");
}

/* ====================================================================
   Upload Theme Asset (logo, favicon, etc.)
   ==================================================================== */

if ($action === 'api_upload_asset') {
    header('Content-Type: application/json');

    verify_post_check($mybb->get_input('my_post_key'));

    $slug  = $mybb->get_input('slug');
    $field = $mybb->get_input('field'); // e.g. 'site_logo', 'favicon'

    if (empty($slug) || empty($field)) {
        echo json_encode(array('error' => 'Missing parameters.'));
        exit;
    }

    // Sanitise slug
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $uploadDir = MYBB_ROOT . 'themes/' . $slug . '/images/uploads';
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            echo json_encode(array('error' => 'Failed to create upload directory.'));
            exit;
        }
    }

    // Validate the resolved path stays within themes directory
    $realUploadDir = realpath($uploadDir);
    $realThemeBase = realpath(MYBB_ROOT . 'themes');
    if ($realUploadDir === false || $realThemeBase === false || strpos($realUploadDir, $realThemeBase) !== 0) {
        echo json_encode(array('error' => 'Invalid upload directory.'));
        exit;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(array('error' => 'No file uploaded or upload error.'));
        exit;
    }

    $file = $_FILES['file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Allowed image extensions
    $allowed = array('png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico');
    if (!in_array($ext, $allowed)) {
        echo json_encode(array('error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed)));
        exit;
    }

    // Validate MIME type matches extension (prevent disguised uploads)
    $allowedMimes = array(
        'png'  => array('image/png'),
        'jpg'  => array('image/jpeg'),
        'jpeg' => array('image/jpeg'),
        'gif'  => array('image/gif'),
        'svg'  => array('image/svg+xml', 'text/xml', 'application/xml'),
        'webp' => array('image/webp'),
        'ico'  => array('image/x-icon', 'image/vnd.microsoft.icon'),
    );
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        echo json_encode(array('error' => 'Server error: could not initialize file type detection.'));
        exit;
    }
    $detectedMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($detectedMime === false || (isset($allowedMimes[$ext]) && !in_array($detectedMime, $allowedMimes[$ext]))) {
        echo json_encode(array('error' => 'File MIME type does not match extension.'));
        exit;
    }

    // Max 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(array('error' => 'File too large. Max 5MB.'));
        exit;
    }

    // Generate safe filename
    $safeName = preg_replace('/[^a-z0-9\-_]/', '', $field) . '.' . $ext;
    $destPath = $uploadDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(array('error' => 'Failed to save file.'));
        exit;
    }

    // Return relative URL path
    $relUrl = 'themes/' . $slug . '/images/uploads/' . $safeName;
    echo json_encode(array('success' => true, 'url' => $relUrl, 'field' => $field));
    exit;
}

/* ====================================================================
   Save Theme Options (POST handler â€” shared by Global MyStudio Options & Header & Footer)
   ==================================================================== */

if ($action === 'api_saveoptions') {
    verify_post_check($mybb->get_input('my_post_key'));

    $slug = $mybb->get_input('slug');
    $pageFilter = $mybb->get_input('page_filter');
    $redirectTo = $mybb->get_input('redirect_to');
    $options = $ms->getThemeOptions($slug);

    if ($options) {
        $existing = $ms->getThemeOptionValues($slug);
        $values = array();

        foreach ($options as $key => $def) {
            // Only process options belonging to the submitted page
            $optPage = isset($def['page']) ? $def['page'] : '';
            if ($pageFilter && $optPage !== $pageFilter) {
                // Preserve existing value for options on other pages
                if (isset($existing[$key])) {
                    $values[$key] = $existing[$key];
                    // Preserve dimension fields too
                    if (!empty($def['has_dimensions'])) {
                        $values[$key . '_width']  = isset($existing[$key . '_width'])  ? $existing[$key . '_width']  : '';
                        $values[$key . '_height'] = isset($existing[$key . '_height']) ? $existing[$key . '_height'] : '';
                    }
                }
                continue;
            }
            $type = isset($def['type']) ? $def['type'] : 'text';

            if ($type === 'image') {
                // Handle file upload for image fields
                $fileKey = 'opt_' . $key . '_file';
                if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$fileKey];
                    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed = array('png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico');

                    if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
                        $safeSlug = preg_replace('/[^a-z0-9\-]/', '', $slug);
                        $uploadDir = MYBB_ROOT . 'themes/' . $safeSlug . '/images/uploads';
                        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                        $safeName = preg_replace('/[^a-z0-9\-_]/', '', $key) . '.' . $ext;
                        $destPath = $uploadDir . '/' . $safeName;

                        if (move_uploaded_file($file['tmp_name'], $destPath)) {
                            $values[$key] = 'themes/' . $safeSlug . '/images/uploads/' . $safeName;
                        } else {
                            $values[$key] = isset($existing[$key]) ? $existing[$key] : '';
                        }
                    } else {
                        $values[$key] = isset($existing[$key]) ? $existing[$key] : '';
                    }
                } else {
                    // Check if "remove" was requested
                    $removeKey = 'opt_' . $key . '_remove';
                    if ($mybb->get_input($removeKey)) {
                        $values[$key] = '';
                    } else {
                        $values[$key] = isset($existing[$key]) ? $existing[$key] : '';
                    }
                }

                // Save dimension fields if they exist in the option definition
                if (!empty($def['has_dimensions'])) {
                    $values[$key . '_width']  = $mybb->get_input('opt_' . $key . '_width');
                    $values[$key . '_height'] = $mybb->get_input('opt_' . $key . '_height');
                }
            } else {
                $values[$key] = $mybb->get_input('opt_' . $key);
            }
        }

        $ms->saveThemeOptionValues($slug, $values);
        flash_message('Theme options saved.', 'success');
    } else {
        flash_message('No options found for this theme.', 'error');
    }
    $redirect = $redirectTo ? $redirectTo : "index.php?module=mystudio-settings";
    admin_redirect($redirect);
}

/* ====================================================================
   Save Extension Options (POST handler)
   ==================================================================== */

if ($action === 'api_save_plugin_options') {
    verify_post_check($mybb->get_input('my_post_key'));

    $activeSlug = $ms->getActiveThemeSlug();
    $pluginId   = preg_replace('/[^a-z0-9\-_]/', '', $mybb->get_input('plugin_id'));

    if ($activeSlug && $pluginId) {
        $options = $ms->getMiniPluginOptions($activeSlug, $pluginId);
        if ($options) {
            $values = array();
            foreach ($options as $def) {
                $id = isset($def['id']) ? $def['id'] : '';
                if (empty($id)) continue;
                $values[$id] = $mybb->get_input('opt_' . $id);
            }
            $ms->saveMiniPluginOptionValues($activeSlug, $pluginId, $values);
            flash_message('Extension options saved.', 'success');
        }
    }
    admin_redirect("index.php?module=mystudio-plugin_settings&plugin=" . urlencode($pluginId));
}

/* ====================================================================
   Toggle Extension (POST handler)
   ==================================================================== */

if ($action === 'api_toggle_plugin') {
    verify_post_check($mybb->get_input('my_post_key'));

    $activeSlug = $ms->getActiveThemeSlug();
    $pluginId   = preg_replace('/[^a-z0-9\-_]/', '', $mybb->get_input('plugin_id'));
    $enable     = $mybb->get_input('enable', MyBB::INPUT_INT);

    if ($activeSlug && $pluginId) {
        $states = $ms->getMiniPluginStates($activeSlug);
        $states[$pluginId] = (bool) $enable;
        $ms->saveMiniPluginStates($activeSlug, $states);
        flash_message($enable ? 'Extension enabled.' : 'Extension disabled.', 'success');
    }
    admin_redirect("index.php?module=mystudio-plugins");
}

/* ====================================================================
   Module Library — Browse & Install modules from GitHub repository
   ==================================================================== */

if ($action === 'install_module') {
    verify_post_check($mybb->get_input('my_post_key'));

    $activeSlug = $ms->getActiveThemeSlug();
    if (!$activeSlug) {
        flash_message('No active theme found. Activate a theme first.', 'error');
        admin_redirect("index.php?module=mystudio-library");
    }

    $moduleId = preg_replace('/[^a-z0-9\-_]/', '', $mybb->get_input('module_id'));
    $repoUrl  = trim($mybb->get_input('repo_url'));
    $modulePath = trim($mybb->get_input('module_path'));

    if (empty($moduleId) || empty($repoUrl) || empty($modulePath)) {
        flash_message('Invalid module data.', 'error');
        admin_redirect("index.php?module=mystudio-library");
    }

    // Parse owner/repo from GitHub URL
    $repoParts = array();
    if (preg_match('#github\.com/([^/]+)/([^/]+?)(?:\.git)?$#i', $repoUrl, $repoParts)) {
        $owner = $repoParts[1];
        $repo  = $repoParts[2];
    } else {
        flash_message('Invalid GitHub repository URL.', 'error');
        admin_redirect("index.php?module=mystudio-library");
    }

    $targetDir = MYBB_ROOT . 'themes/' . $activeSlug . '/functions/modules/' . $moduleId;

    // Don't overwrite existing module
    if (is_dir($targetDir)) {
        flash_message('Module "' . htmlspecialchars_uni($moduleId) . '" is already installed. Uninstall it first to reinstall.', 'error');
        admin_redirect("index.php?module=mystudio-library");
    }

    // Fetch file list from GitHub API
    $apiUrl = 'https://api.github.com/repos/' . urlencode($owner) . '/' . urlencode($repo) . '/contents/' . $modulePath;
    $files  = ms_library_github_fetch($apiUrl);

    if ($files === false) {
        flash_message('Failed to fetch module file list from GitHub. Check the repository URL and try again.', 'error');
        admin_redirect("index.php?module=mystudio-library");
    }

    // Download all files recursively
    if (!@mkdir($targetDir, 0755, true)) {
        flash_message('Failed to create module directory. Check file permissions.', 'error');
        admin_redirect("index.php?module=mystudio-library");
    }

    $success = ms_library_download_directory($files, $targetDir, $owner, $repo);

    if ($success) {
        flash_message('Module "' . htmlspecialchars_uni($moduleId) . '" installed successfully.', 'success');
    } else {
        // Cleanup on failure
        ms_library_rmdir($targetDir);
        flash_message('Failed to download module files. Please try again.', 'error');
    }

    admin_redirect("index.php?module=mystudio-library");
}

if ($action === 'uninstall_module') {
    verify_post_check($mybb->get_input('my_post_key'));

    $activeSlug = $ms->getActiveThemeSlug();
    if (!$activeSlug) {
        flash_message('No active theme found.', 'error');
        admin_redirect("index.php?module=mystudio-library");
    }

    $moduleId = preg_replace('/[^a-z0-9\-_]/', '', $mybb->get_input('module_id'));
    if (empty($moduleId)) {
        flash_message('Invalid module.', 'error');
        admin_redirect("index.php?module=mystudio-library");
    }

    $targetDir = MYBB_ROOT . 'themes/' . $activeSlug . '/functions/modules/' . $moduleId;

    if (!is_dir($targetDir)) {
        flash_message('Module is not installed.', 'error');
        admin_redirect("index.php?module=mystudio-library");
    }

    ms_library_rmdir($targetDir);
    flash_message('Module "' . htmlspecialchars_uni($moduleId) . '" has been uninstalled.', 'success');
    admin_redirect("index.php?module=mystudio-library");
}

if ($action === 'library') {
    $page->add_breadcrumb_item("MyStudio", "index.php?module=mystudio-manage");
    $page->add_breadcrumb_item("Module Library");
    $page->output_header("MyStudio - Module Library");

    $activeSlug = $ms->getActiveThemeSlug();

    // Hardcoded library repo
    $libraryRepo = 'https://github.com/amtekkie/mystudio-modules';
    $owner = 'amtekkie';
    $repo  = 'mystudio-modules';
    $registryModules = array();
    $fetchError = '';

    // Fetch registry.json from GitHub raw
    $registryUrl = 'https://raw.githubusercontent.com/' . urlencode($owner) . '/' . urlencode($repo) . '/main/registry.json';
    $registryRaw = ms_library_http_get($registryUrl);

    if ($registryRaw === false) {
        $fetchError = 'Could not fetch registry from GitHub. Ensure registry.json exists on the <code>main</code> branch.';
    } else {
        $registryData = @json_decode($registryRaw, true);
        if (!is_array($registryData) || !isset($registryData['modules'])) {
            $fetchError = 'Invalid registry.json format. Expected a JSON object with a "modules" array.';
        } else {
            $registryModules = $registryData['modules'];
        }
    }

    // Get installed modules for comparison
    $installedModules = array();
    if ($activeSlug) {
        $installed = $ms->listModules($activeSlug);
        foreach ($installed as $m) {
            $installedModules[$m['id']] = $m;
        }
    }

    if ($fetchError) {
        echo '<div style="margin-bottom:16px;padding:12px 16px;border:1px solid #e74c3c;border-radius:6px;background:#fdf0ef;color:#c0392b;font-size:13px">'
           . '<i class="bi bi-exclamation-triangle"></i> ' . $fetchError . '</div>';
    }

    // Display modules
    if (!empty($registryModules)) {
        $post_key = $mybb->post_code;

        $table = new Table;
        $table->construct_header("Module", array('width' => '30%'));
        $table->construct_header("Description");
        $table->construct_header("Version", array('width' => '8%', 'class' => 'align_center'));
        $table->construct_header("Author", array('width' => '12%', 'class' => 'align_center'));
        $table->construct_header("Action", array('width' => '14%', 'class' => 'align_center'));

        foreach ($registryModules as $mod) {
            $modId   = isset($mod['id']) ? $mod['id'] : '';
            $modName = isset($mod['name']) ? htmlspecialchars_uni($mod['name']) : $modId;
            $modDesc = isset($mod['description']) ? htmlspecialchars_uni($mod['description']) : '';
            $modVer  = isset($mod['version']) ? htmlspecialchars_uni($mod['version']) : '-';
            $modAuth = isset($mod['author']) ? htmlspecialchars_uni($mod['author']) : '-';
            $modAuthUrl = isset($mod['author_url']) ? $mod['author_url'] : '';
            $modPath = isset($mod['path']) ? $mod['path'] : 'modules/' . $modId;
            $modCompat = isset($mod['compatibility']) ? htmlspecialchars_uni($mod['compatibility']) : '';

            if (empty($modId)) continue;

            // Name cell
            $nameCell = '<strong>' . $modName . '</strong>';
            if ($modCompat) {
                $nameCell .= ' <small style="color:#888">(' . $modCompat . ')</small>';
            }

            $table->construct_cell($nameCell);
            $table->construct_cell('<span style="font-size:12.5px;color:#555">' . $modDesc . '</span>');
            $table->construct_cell($modVer, array('class' => 'align_center'));

            // Author cell
            if ($modAuthUrl) {
                $authorCell = '<a href="' . htmlspecialchars_uni($modAuthUrl) . '" target="_blank" rel="noopener">' . $modAuth . '</a>';
            } else {
                $authorCell = $modAuth;
            }
            $table->construct_cell($authorCell, array('class' => 'align_center'));

            // Action cell
            $isInstalled = isset($installedModules[$modId]);
            if ($isInstalled) {
                $installedVer = $installedModules[$modId]['version'];
                $hasUpdate = version_compare($modVer, $installedVer, '>');

                if ($hasUpdate) {
                    // Show update button — uninstall first, then reinstall
                    $actionCell = '<span style="color:#27ae60;font-size:12px"><i class="bi bi-check-circle"></i> v' . htmlspecialchars_uni($installedVer) . '</span><br />'
                                . '<form method="post" action="index.php?module=mystudio-manage&amp;action=uninstall_module" style="display:inline;margin-top:4px">'
                                . '<input type="hidden" name="my_post_key" value="' . $post_key . '" />'
                                . '<input type="hidden" name="module_id" value="' . htmlspecialchars_uni($modId) . '" />'
                                . '<button type="submit" class="submit_button" style="font-size:11px;padding:3px 10px" '
                                . 'onclick="return confirm(\'Uninstall to update? You can then reinstall the latest version.\')"><i class="bi bi-arrow-repeat"></i> Update</button>'
                                . '</form>';
                } else {
                    $actionCell = '<span style="color:#27ae60;font-size:12px"><i class="bi bi-check-circle"></i> Installed</span><br />'
                                . '<form method="post" action="index.php?module=mystudio-manage&amp;action=uninstall_module" style="display:inline;margin-top:4px">'
                                . '<input type="hidden" name="my_post_key" value="' . $post_key . '" />'
                                . '<input type="hidden" name="module_id" value="' . htmlspecialchars_uni($modId) . '" />'
                                . '<button type="submit" class="submit_button" style="font-size:11px;padding:3px 10px;background:#e74c3c;border-color:#c0392b" '
                                . 'onclick="return confirm(\'Uninstall this module? Its files will be deleted.\')"><i class="bi bi-trash"></i> Uninstall</button>'
                                . '</form>';
                }
            } else {
                $actionCell = '<form method="post" action="index.php?module=mystudio-manage&amp;action=install_module">'
                            . '<input type="hidden" name="my_post_key" value="' . $post_key . '" />'
                            . '<input type="hidden" name="module_id" value="' . htmlspecialchars_uni($modId) . '" />'
                            . '<input type="hidden" name="repo_url" value="' . htmlspecialchars_uni($libraryRepo) . '" />'
                            . '<input type="hidden" name="module_path" value="' . htmlspecialchars_uni($modPath) . '" />'
                            . '<button type="submit" class="submit_button" style="font-size:11px;padding:3px 10px"><i class="bi bi-download"></i> Install</button>'
                            . '</form>';
            }

            $table->construct_cell($actionCell, array('class' => 'align_center'));
            $table->construct_row();
        }

        $table->output("Available Modules" . (!empty($owner) ? ' <small style="font-weight:normal;color:#888">from ' . htmlspecialchars_uni($owner . '/' . $repo) . '</small>' : ''));
    } elseif (!empty($libraryRepo) && empty($fetchError)) {
        echo '<p style="padding:16px;color:#888;text-align:center">No modules found in the library.</p>';
    }

    // Show installed modules not in registry
    if ($activeSlug && !empty($installedModules)) {
        $localOnly = array();
        $registryIds = array();
        foreach ($registryModules as $mod) {
            if (isset($mod['id'])) $registryIds[] = $mod['id'];
        }
        foreach ($installedModules as $id => $m) {
            if (!in_array($id, $registryIds)) {
                $localOnly[$id] = $m;
            }
        }

        if (!empty($localOnly)) {
            $table2 = new Table;
            $table2->construct_header("Module", array('width' => '30%'));
            $table2->construct_header("Description");
            $table2->construct_header("Version", array('width' => '8%', 'class' => 'align_center'));

            foreach ($localOnly as $id => $m) {
                $table2->construct_cell('<strong>' . htmlspecialchars_uni($m['name']) . '</strong>');
                $table2->construct_cell('<span style="font-size:12.5px;color:#555">' . htmlspecialchars_uni($m['description']) . '</span>');
                $table2->construct_cell(htmlspecialchars_uni($m['version']), array('class' => 'align_center'));
                $table2->construct_row();
            }

            $table2->output("Locally Installed Modules <small style=\"font-weight:normal;color:#888\">(not in library)</small>");
        }
    }

    $page->output_footer();
    exit;
}

/* ====================================================================
   Module Library — Helper Functions
   ==================================================================== */

/**
 * Make an HTTP GET request (supports cURL and file_get_contents fallback).
 *
 * @param  string       $url
 * @return string|false Response body or false on failure
 */
function ms_library_http_get($url)
{
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'MyStudio/2.1',
            CURLOPT_HTTPHEADER     => array('Accept: application/vnd.github.v3+json'),
            CURLOPT_SSL_VERIFYPEER => true,
        ));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $response;
        }
        return false;
    }

    // Fallback to file_get_contents
    $context = stream_context_create(array(
        'http' => array(
            'method'  => 'GET',
            'header'  => "User-Agent: MyStudio/2.1\r\nAccept: application/vnd.github.v3+json\r\n",
            'timeout' => 15,
        ),
        'ssl' => array(
            'verify_peer' => true,
        ),
    ));

    $response = @file_get_contents($url, false, $context);
    return $response !== false ? $response : false;
}

/**
 * Fetch a GitHub API endpoint returning JSON.
 *
 * @param  string      $apiUrl
 * @return array|false Decoded JSON array or false
 */
function ms_library_github_fetch($apiUrl)
{
    $raw = ms_library_http_get($apiUrl);
    if ($raw === false) return false;
    $data = @json_decode($raw, true);
    return is_array($data) ? $data : false;
}

/**
 * Recursively download a GitHub directory to a local path.
 *
 * @param  array  $items    Array of GitHub API content items
 * @param  string $localDir Local target directory
 * @param  string $owner    GitHub repo owner
 * @param  string $repo     GitHub repo name
 * @return bool
 */
function ms_library_download_directory($items, $localDir, $owner, $repo)
{
    foreach ($items as $item) {
        $type = isset($item['type']) ? $item['type'] : '';
        $name = isset($item['name']) ? $item['name'] : '';
        $downloadUrl = isset($item['download_url']) ? $item['download_url'] : '';
        $apiPath = isset($item['path']) ? $item['path'] : '';

        // Security: prevent directory traversal
        if (strpos($name, '..') !== false || strpos($name, '/') !== false || strpos($name, '\\') !== false) {
            continue;
        }

        if ($type === 'file' && $downloadUrl) {
            $content = ms_library_http_get($downloadUrl);
            if ($content === false) return false;

            $localPath = $localDir . '/' . $name;
            if (@file_put_contents($localPath, $content) === false) {
                return false;
            }
        } elseif ($type === 'dir') {
            $subDir = $localDir . '/' . $name;
            if (!is_dir($subDir) && !@mkdir($subDir, 0755, true)) {
                return false;
            }

            // Fetch subdirectory contents
            $subApiUrl = 'https://api.github.com/repos/' . urlencode($owner) . '/' . urlencode($repo) . '/contents/' . $apiPath;
            $subItems = ms_library_github_fetch($subApiUrl);
            if ($subItems === false) return false;

            if (!ms_library_download_directory($subItems, $subDir, $owner, $repo)) {
                return false;
            }
        }
    }
    return true;
}

/**
 * Recursively remove a directory.
 *
 * @param string $dir
 */
function ms_library_rmdir($dir)
{
    if (!is_dir($dir)) return;
    $entries = scandir($dir);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . '/' . $entry;
        if (is_dir($path)) {
            ms_library_rmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

/* ====================================================================
   Extension Settings Page (individual extension options via side nav)
   ==================================================================== */

if ($action === 'plugin_settings') {
    $page->add_breadcrumb_item("MyStudio", "index.php?module=mystudio-manage");

    $activeSlug = $ms->getActiveThemeSlug();
    $selectedPlugin = preg_replace('/[^a-z0-9\-_]/', '', $mybb->get_input('plugin'));

    if (!$activeSlug || empty($selectedPlugin)) {
        flash_message('Invalid extension.', 'error');
        admin_redirect("index.php?module=mystudio-plugins");
    }

    $allPlugins = $ms->listMiniPlugins($activeSlug);
    $pluginInfo = null;
    foreach ($allPlugins as $p) {
        if ($p['id'] === $selectedPlugin) {
            $pluginInfo = $p;
            break;
        }
    }

    if (!$pluginInfo) {
        flash_message('Extension not found.', 'error');
        admin_redirect("index.php?module=mystudio-plugins");
    }

    $page->add_breadcrumb_item(htmlspecialchars_uni($pluginInfo['name']));
    $page->output_header("MyStudio - " . htmlspecialchars_uni($pluginInfo['name']));

    // Extension options
    $options = $ms->getMiniPluginOptions($activeSlug, $selectedPlugin);
    if ($options) {
        $values = $ms->getMergedMiniPluginOptions($activeSlug, $selectedPlugin);

        $form = new Form("index.php?module=mystudio-manage&action=api_save_plugin_options", "post");
        echo $form->generate_hidden_field('plugin_id', $selectedPlugin);

        $form_container = new FormContainer("Settings");

        foreach ($options as $def) {
            $id    = isset($def['id']) ? $def['id'] : '';
            if (empty($id)) continue;
            $title = isset($def['label']) ? $def['label'] : $id;
            $desc  = isset($def['description']) ? $def['description'] : '';
            $type  = isset($def['type']) ? $def['type'] : 'text';
            $val   = isset($values[$id]) ? $values[$id] : (isset($def['default']) ? $def['default'] : '');

            switch ($type) {
                case 'yesno':
                    $input = $form->generate_yes_no_radio('opt_' . $id, $val);
                    break;
                case 'select':
                    $opts = isset($def['options']) ? $def['options'] : array();
                    $input = $form->generate_select_box('opt_' . $id, $opts, $val);
                    break;
                case 'radio':
                    $opts = isset($def['options']) ? $def['options'] : array();
                    $radios = '';
                    foreach ($opts as $optVal => $optLabel) {
                        $checked = ($val === (string)$optVal) ? ' checked' : '';
                        $rid = 'opt_' . htmlspecialchars_uni($id) . '_' . htmlspecialchars_uni($optVal);
                        $radios .= '<label style="display:inline-flex;align-items:center;gap:5px;margin-right:16px;cursor:pointer;font-size:13px">' 
                                 . '<input type="radio" name="opt_' . htmlspecialchars_uni($id) . '" value="' . htmlspecialchars_uni($optVal) . '" id="' . $rid . '"' . $checked . ' />' 
                                 . $optLabel . '</label>';
                    }
                    $input = '<div style="display:flex;align-items:center;gap:4px">' . $radios . '</div>';
                    break;
                case 'color':
                    $input = '<input type="color" name="opt_' . htmlspecialchars_uni($id) . '" value="' . htmlspecialchars_uni($val) . '" />';
                    break;
                case 'numeric':
                    $input = $form->generate_numeric_field('opt_' . $id, $val, array('style' => 'width:150px'));
                    break;
                case 'textarea':
                    $input = $form->generate_text_area('opt_' . $id, $val, array('rows' => 4, 'style' => 'width:95%'));
                    break;
                case 'toolbar_builder':
                    $input = ms_render_toolbar_builder($id, $val);
                    break;
                case 'preset_swatches':
                    $swatchOpts = isset($def['options']) ? $def['options'] : array();
                    $hiddenId = 'opt_' . htmlspecialchars_uni($id);
                    $input = '<input type="hidden" name="' . $hiddenId . '" id="' . $hiddenId . '" value="' . htmlspecialchars_uni($val) . '" />';
                    $input .= '<div style="display:flex;flex-wrap:wrap;gap:8px">';
                    foreach ($swatchOpts as $swKey => $swDef) {
                        $swLabel = isset($swDef['label']) ? $swDef['label'] : $swKey;
                        $swColor = isset($swDef['swatch']) ? $swDef['swatch'] : '#888';
                        $isActive = ($val === (string)$swKey);
                        $borderColor = $isActive ? htmlspecialchars_uni($swColor) : 'transparent';
                        $bgColor = $isActive ? '#eef2ff' : '#f9fafb';
                        $input .= '<button type="button" class="ms-swatch-btn" data-value="' . htmlspecialchars_uni($swKey) . '" data-target="' . $hiddenId . '" '
                                . 'style="display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 10px;border:2px solid ' . $borderColor . ';border-radius:8px;background:' . $bgColor . ';cursor:pointer;transition:all .15s" '
                                . 'title="' . htmlspecialchars_uni($swLabel) . '">'
                                . '<span style="width:28px;height:28px;border-radius:50%;background:' . htmlspecialchars_uni($swColor) . ';border:2px solid rgba(0,0,0,.1);display:block"></span>'
                                . '<span style="font-size:10px;color:#6b7280;font-weight:500">' . htmlspecialchars_uni($swLabel) . '</span>'
                                . '</button>';
                    }
                    $input .= '</div>';
                    $input .= '<script>'
                            . 'document.addEventListener("click",function(e){'
                            . 'var btn=e.target.closest(".ms-swatch-btn");'
                            . 'if(!btn)return;'
                            . 'e.preventDefault();'
                            . 'var target=btn.getAttribute("data-target");'
                            . 'var val=btn.getAttribute("data-value");'
                            . 'document.getElementById(target).value=val;'
                            . 'var wrap=btn.parentNode;'
                            . 'wrap.querySelectorAll(".ms-swatch-btn").forEach(function(b){'
                            . 'b.style.borderColor="transparent";b.style.background="#f9fafb";'
                            . '});'
                            . 'btn.style.borderColor=btn.querySelector("span").style.background;'
                            . 'btn.style.background="#eef2ff";'
                            . '});'
                            . '</script>';
                    break;
                default:
                    $input = $form->generate_text_box('opt_' . $id, $val, array('style' => 'width:95%'));
                    break;
            }

            $form_container->output_row($title, $desc, $input);
        }

        $form_container->end();
        $buttons = array($form->generate_submit_button("Save Settings"));
        $form->output_submit_wrapper($buttons);
        echo $form->end();
    } else {
        echo '<p>This extension has no configurable options.</p>';
    }

    // Include admin.php if it exists (custom admin content)
    if ($pluginInfo['has_admin']) {
        include $pluginInfo['dir'] . '/admin.php';
    }

    $page->output_footer();
    exit;
}

/* ====================================================================
   Extensions Page
   ==================================================================== */

if ($action === 'plugins') {
    $page->add_breadcrumb_item("MyStudio", "index.php?module=mystudio-manage");
    $page->add_breadcrumb_item("Manage Extensions");

    $activeSlug = $ms->getActiveThemeSlug();

    // Main extensions list
    $page->output_header("MyStudio - Manage Extensions");

    if (!$activeSlug) {
        echo '<p>No active theme found. Activate a theme on the Manage page first.</p>';
        $page->output_footer();
        exit;
    }

    $allPlugins = $ms->listMiniPlugins($activeSlug);
    $states     = $ms->getMiniPluginStates($activeSlug);
    $post_key   = $mybb->post_code;

    if (empty($allPlugins)) {
        $table = new Table;
        $table->construct_header("Extension");
        $table->construct_header("Controls", array('class' => 'align_center', 'width' => 200));
        $table->construct_cell('No extensions found for the active theme.', array('colspan' => 2));
        $table->construct_row();
        $table->output("Theme Extensions: " . htmlspecialchars_uni($activeSlug));
        $page->output_footer();
        exit;
    }

    // Split into active/inactive like MyBB does
    $activePlugins = array();
    $inactivePlugins = array();
    foreach ($allPlugins as $p) {
        $isEnabled = !isset($states[$p['id']]) || $states[$p['id']];
        if ($isEnabled) {
            $activePlugins[] = $p;
        } else {
            $inactivePlugins[] = $p;
        }
    }

    // Active extensions table
    $table = new Table;
    $table->construct_header("Extension");
    $table->construct_header("Controls", array('colspan' => 2, 'class' => 'align_center', 'width' => 300));

    if (empty($activePlugins)) {
        $table->construct_cell('No active extensions.', array('colspan' => 3));
        $table->construct_row();
    } else {
        foreach ($activePlugins as $p) {
            $pluginId = htmlspecialchars_uni($p['id']);
            $nameCell = '<strong>' . htmlspecialchars_uni($p['name']) . '</strong> (' . htmlspecialchars_uni($p['version']) . ')';
            if ($p['description']) {
                $nameCell .= '<br /><small>' . htmlspecialchars_uni($p['description']) . '</small>';
            }
            if ($p['author']) {
                $nameCell .= '<br /><i><small>Created by ' . htmlspecialchars_uni($p['author']) . '</small></i>';
            }
            $table->construct_cell($nameCell);

            $table->construct_cell('<a href="index.php?module=mystudio-manage&amp;action=api_toggle_plugin&amp;plugin_id=' . $pluginId . '&amp;enable=0&amp;my_post_key=' . $post_key . '">Deactivate</a>', array('class' => 'align_center', 'width' => 150));

            if ($p['has_options'] || $p['has_admin']) {
                $table->construct_cell('<a href="index.php?module=mystudio-plugin_settings&amp;plugin=' . $pluginId . '">Settings</a>', array('class' => 'align_center', 'width' => 150));
            } else {
                $table->construct_cell('&nbsp;', array('class' => 'align_center', 'width' => 150));
            }

            $table->construct_row();
        }
    }

    $table->output("Active Extensions");

    // Inactive extensions table
    $table = new Table;
    $table->construct_header("Extension");
    $table->construct_header("Controls", array('colspan' => 2, 'class' => 'align_center', 'width' => 300));

    if (empty($inactivePlugins)) {
        $table->construct_cell('No inactive extensions.', array('colspan' => 3));
        $table->construct_row();
    } else {
        foreach ($inactivePlugins as $p) {
            $pluginId = htmlspecialchars_uni($p['id']);
            $nameCell = '<strong>' . htmlspecialchars_uni($p['name']) . '</strong> (' . htmlspecialchars_uni($p['version']) . ')';
            if ($p['description']) {
                $nameCell .= '<br /><small>' . htmlspecialchars_uni($p['description']) . '</small>';
            }
            if ($p['author']) {
                $nameCell .= '<br /><i><small>Created by ' . htmlspecialchars_uni($p['author']) . '</small></i>';
            }
            $table->construct_cell($nameCell);

            $table->construct_cell('<a href="index.php?module=mystudio-manage&amp;action=api_toggle_plugin&amp;plugin_id=' . $pluginId . '&amp;enable=1&amp;my_post_key=' . $post_key . '">Activate</a>', array('class' => 'align_center', 'width' => 150));

            if ($p['has_options'] || $p['has_admin']) {
                $table->construct_cell('<a href="index.php?module=mystudio-plugin_settings&amp;plugin=' . $pluginId . '">Settings</a>', array('class' => 'align_center', 'width' => 150));
            } else {
                $table->construct_cell('&nbsp;', array('class' => 'align_center', 'width' => 150));
            }

            $table->construct_row();
        }
    }

    $table->output("Inactive Extensions");

    $page->output_footer();
    exit;
}

/* ====================================================================
   Settings Page â€” Extension-level settings
   ==================================================================== */

if ($action === 'settings') {
    $page->add_breadcrumb_item("MyStudio", "index.php?module=mystudio-manage");
    $page->add_breadcrumb_item("Settings");

    // Ensure new settings exist (migration for existing installations)
    $newSettings = array(
        'ms_dev_auto_sync'     => array('title' => 'Auto Sync (Dev Mode)', 'description' => 'Automatically sync theme files to the database when changes are detected.', 'optionscode' => 'yesno', 'value' => '0', 'disporder' => 3, 'gid' => 0),
        'ms_dev_sync_interval' => array('title' => 'Auto Sync Interval (seconds)', 'description' => 'How often to check for file changes.', 'optionscode' => 'numeric', 'value' => '2', 'disporder' => 4, 'gid' => 0),
        'ms_loading_bar'       => array('title' => 'Page Loading Bar', 'description' => 'Show an accent-colored progress bar at the top of the page during navigation.', 'optionscode' => 'yesno', 'value' => '1', 'disporder' => 6, 'gid' => 0),
    );
    foreach ($newSettings as $name => $def) {
        $check = $db->simple_select('settings', 'name', "name='" . $db->escape_string($name) . "'");
        if (!$db->num_rows($check)) {
            $def['name'] = $name;
            $db->insert_query('settings', $def);
        }
    }
    rebuild_settings();

    // Handle save
    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        // Define our settings and their sanitisation
        $settingsToSave = array(
            'ms_enabled'           => intval($mybb->get_input('ms_enabled')),
            'ms_max_upload_mb'     => max(1, intval($mybb->get_input('ms_max_upload_mb'))),
            'ms_dev_auto_sync'     => intval($mybb->get_input('ms_dev_auto_sync')),
            'ms_dev_sync_interval' => max(1, intval($mybb->get_input('ms_dev_sync_interval'))),
            'ms_loading_bar'       => intval($mybb->get_input('ms_loading_bar')),
        );

        foreach ($settingsToSave as $name => $value) {
            $db->update_query('settings', array('value' => $db->escape_string($value)), "name='" . $db->escape_string($name) . "'");
        }

        rebuild_settings();

        flash_message('Settings saved successfully.', 'success');
        admin_redirect("index.php?module=mystudio-settings");
    }

    // Read current values
    $currentSettings = array();
    $query = $db->simple_select('settings', 'name, value', "name LIKE 'ms_%'");
    while ($row = $db->fetch_array($query)) {
        $currentSettings[$row['name']] = $row['value'];
    }

    $page->output_header("MyStudio - Studio Settings");

    $form = new Form("index.php?module=mystudio-settings", "post");

    $form_container = new FormContainer("General");

    $form_container->output_row(
        "Enable MyStudio",
        "Master switch to enable or disable MyStudio on the frontend. When disabled, themes will still be manageable from the admin panel but no MyStudio features will load on the forum.",
        $form->generate_yes_no_radio('ms_enabled', isset($currentSettings['ms_enabled']) ? $currentSettings['ms_enabled'] : 1)
    );

    $form_container->output_row(
        "Max Upload Size (MB)",
        "Maximum allowed ZIP file size in megabytes for theme imports.",
        $form->generate_numeric_field('ms_max_upload_mb', isset($currentSettings['ms_max_upload_mb']) ? $currentSettings['ms_max_upload_mb'] : 20, array('min' => 1, 'max' => 500, 'style' => 'width:80px'))
    );

    $form_container->end();

    $form_container = new FormContainer("Theme Settings");

    $form_container->output_row(
        "Page Loading Bar",
        "Show an accent-colored progress bar at the top of the page during navigation.",
        $form->generate_yes_no_radio('ms_loading_bar', isset($currentSettings['ms_loading_bar']) ? $currentSettings['ms_loading_bar'] : 1)
    );

    $form_container->end();

    $form_container = new FormContainer("Developer Settings");

    $form_container->output_row(
        "Auto Sync (Dev Mode)",
        "When enabled, the forum will poll for file changes in the active theme directory every few seconds and automatically sync to the database. Only runs for admin users browsing the frontend. <strong>Disable in production.</strong>",
        $form->generate_yes_no_radio('ms_dev_auto_sync', isset($currentSettings['ms_dev_auto_sync']) ? $currentSettings['ms_dev_auto_sync'] : 0)
    );

    $form_container->output_row(
        "Auto Sync Interval (seconds)",
        "How often to check for file changes. Lower = faster feedback, higher = less server load. Recommended: 2â€“5 seconds.",
        $form->generate_numeric_field('ms_dev_sync_interval', isset($currentSettings['ms_dev_sync_interval']) ? $currentSettings['ms_dev_sync_interval'] : 2, array('min' => 1, 'max' => 60, 'style' => 'width:80px'))
    );

    $form_container->end();

    $buttons = array($form->generate_submit_button("Save Settings"));
    $form->output_submit_wrapper($buttons);

    $form->end();

    // ── Branding (theme options: logo, favicon) ──
    $activeSlug = $ms->getActiveThemeSlug();
    if ($activeSlug) {
        $allOptions = $ms->getThemeOptions($activeSlug);
        $themeValues = $ms->getMergedThemeOptions($activeSlug);

        $brandingKeys = array('logo_icon', 'logo_text', 'site_logo', 'favicon');
        $hasBranding = false;
        if ($allOptions) {
            foreach ($brandingKeys as $bk) {
                if (isset($allOptions[$bk])) { $hasBranding = true; break; }
            }
        }

        if ($hasBranding) {
            $brandForm = new Form("index.php?module=mystudio-manage&action=api_saveoptions", "post", "", 1);
            echo $brandForm->generate_hidden_field('slug', $activeSlug);
            echo $brandForm->generate_hidden_field('page_filter', 'studio_settings');
            echo $brandForm->generate_hidden_field('redirect_to', 'index.php?module=mystudio-settings');

            $form_container = new FormContainer("Branding");
            foreach ($brandingKeys as $bk) {
                if (isset($allOptions[$bk])) {
                    ms_render_option_row($brandForm, $form_container, $bk, $allOptions[$bk], $themeValues, $mybb);
                }
            }
            $form_container->end();

            $buttons = array($brandForm->generate_submit_button("Save Branding"));
            $brandForm->output_submit_wrapper($buttons);
            echo $brandForm->end();

            // Icon picker modal + JS (needed for logo_icon chooser)
            $iconListJson = json_encode(ms_get_icon_list());
            echo '<div id="ms-icon-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.5);backdrop-filter:blur(2px)">
<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.3);width:640px;max-width:95vw;max-height:85vh;display:flex;flex-direction:column;overflow:hidden">
  <div style="display:flex;align-items:center;justify-content:between;padding:14px 20px;border-bottom:1px solid #eee;gap:10px;flex-shrink:0">
    <i class="bi bi-grid-3x3-gap" style="font-size:18px;color:#0d9488"></i>
    <strong style="font-size:14px;flex:1">Choose Icon</strong>
    <input type="text" id="ms-icon-search" placeholder="Search icons..." style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;width:220px" />
    <button type="button" id="ms-icon-modal-close" style="background:none;border:none;font-size:20px;cursor:pointer;color:#888;padding:0 4px">&times;</button>
  </div>
  <div id="ms-icon-grid" style="padding:12px 16px;overflow-y:auto;flex:1"></div>
  <div style="padding:10px 20px;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center;flex-shrink:0">
    <span id="ms-icon-count" style="font-size:12px;color:#888"></span>
    <button type="button" id="ms-icon-clear-selected" style="font-size:12px;padding:4px 12px;border:1px solid #ddd;border-radius:4px;background:#fafafa;cursor:pointer;color:#888">&#x2715; No icon</button>
  </div>
</div>
</div>';

            echo '<style>
.ms-ig{display:grid;grid-template-columns:repeat(auto-fill,minmax(64px,1fr));gap:6px}
.ms-ig-item{display:flex;flex-direction:column;align-items:center;gap:3px;padding:8px 4px;border:2px solid transparent;border-radius:8px;cursor:pointer;transition:all .15s;background:#fafafa}
.ms-ig-item:hover{background:#e0f2fe;border-color:#7dd3fc}
.ms-ig-item.selected{background:#ccfbf1;border-color:#0d9488}
.ms-ig-item i{font-size:22px;color:#333}
.ms-ig-item span{font-size:9px;color:#888;text-align:center;line-height:1.1;word-break:break-word;max-width:60px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
</style>';

            echo '<script>
(function(){
  var icons=' . $iconListJson . ';
  var modal=document.getElementById("ms-icon-modal");
  var grid=document.getElementById("ms-icon-grid");
  var searchInput=document.getElementById("ms-icon-search");
  var countEl=document.getElementById("ms-icon-count");
  var closeBtn=document.getElementById("ms-icon-modal-close");
  var clearBtn=document.getElementById("ms-icon-clear-selected");
  var onSelect=null;
  var currentValue="";

  function renderGrid(filter){
    filter=(filter||"").toLowerCase();
    var html="";
    var count=0;
    for(var cls in icons){
      var label=icons[cls];
      if(filter && cls.indexOf(filter)===-1 && label.toLowerCase().indexOf(filter)===-1) continue;
      var sel=(cls===currentValue)?" selected":"";
      html+=\'<div class="ms-ig-item\'+sel+\'" data-icon="\'+cls+\'"><i class="bi \'+cls+\'"></i><span>\'+label+\'</span></div>\';
      count++;
    }
    if(!count) html=\'<div style="text-align:center;padding:30px;color:#aaa;font-size:13px">No icons match your search</div>\';
    grid.innerHTML=\'<div class="ms-ig">\'+html+\'</div>\';
    countEl.textContent=count+" icon"+(count!==1?"s":"");
    grid.querySelectorAll(".ms-ig-item").forEach(function(item){
      item.addEventListener("click",function(){
        var icon=this.getAttribute("data-icon");
        if(onSelect) onSelect(icon);
        close();
      });
    });
  }

  function open(val,callback){
    currentValue=val||"";
    onSelect=callback;
    renderGrid("");
    searchInput.value="";
    modal.style.display="block";
    setTimeout(function(){searchInput.focus();},100);
  }

  function close(){
    modal.style.display="none";
    onSelect=null;
  }

  closeBtn.addEventListener("click",close);
  modal.addEventListener("click",function(e){if(e.target===modal) close();});
  document.addEventListener("keydown",function(e){if(e.key==="Escape"&&modal.style.display==="block") close();});
  searchInput.addEventListener("input",function(){renderGrid(this.value);});
  clearBtn.addEventListener("click",function(){if(onSelect) onSelect("");close();});

  window.FmzIconModal={open:open,close:close};

  window.initIconPickers=function(){
    document.querySelectorAll(".ms-icon-pick-btn:not([data-target-type=nav])").forEach(function(btn){
      if(btn.dataset.bound) return;
      btn.dataset.bound="1";
      btn.addEventListener("click",function(){
        var inputId=btn.getAttribute("data-target-input");
        var previewId=btn.getAttribute("data-target-preview");
        var labelId=btn.getAttribute("data-target-label");
        var input=inputId?document.getElementById(inputId):null;
        FmzIconModal.open(input?input.value:"",function(icon){
          if(input) input.value=icon;
          var prev=previewId?document.getElementById(previewId):null;
          if(prev) prev.className="bi "+(icon||"bi-grid-3x3-gap");
          var lbl=labelId?document.getElementById(labelId):null;
          if(lbl) lbl.textContent=icon||"Choose icon\u2026";
        });
      });
    });
    document.querySelectorAll(".ms-icon-clear-btn").forEach(function(btn){
      if(btn.dataset.bound) return;
      btn.dataset.bound="1";
      btn.addEventListener("click",function(){
        var inputId=btn.getAttribute("data-target-input");
        var previewId=btn.getAttribute("data-target-preview");
        var labelId=btn.getAttribute("data-target-label");
        var input=inputId?document.getElementById(inputId):null;
        if(input) input.value="";
        var prev=previewId?document.getElementById(previewId):null;
        if(prev) prev.className="bi bi-grid-3x3-gap";
        var lbl=labelId?document.getElementById(labelId):null;
        if(lbl) lbl.textContent="Choose icon\u2026";
        btn.style.display="none";
      });
    });
  };

  document.addEventListener("DOMContentLoaded",function(){initIconPickers();});
})();
</script>';
        }
    }

    $page->output_footer();
    exit;
}

/* ====================================================================
   Manage Page (default)
   ==================================================================== */

$page->add_breadcrumb_item("MyStudio", "index.php?module=mystudio-manage");

$page->output_header("MyStudio - Manage");

$post_key = $mybb->post_code;

// -- Gather theme data --
$dbThemes   = $ms->listDbThemes();
$diskThemes = $ms->listThemesOnDisk();

// Build lookup maps
$dbByName = array();
foreach ($dbThemes as $t) {
    $dbByName[strtolower(trim($t['name']))] = $t;
}
$diskBySlug = array();
foreach ($diskThemes as $t) {
    $diskBySlug[$t['slug']] = $t;
}

/* ----------------------------------------------------------------
   Broken Theme Detection
   ---------------------------------------------------------------- */

$brokenThemes = array();
$baseDir = MYBB_ROOT . 'themes';

if (is_dir($baseDir)) {
    foreach (scandir($baseDir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $dir = $baseDir . '/' . $entry;
        if (!is_dir($dir)) continue;

        $issues = array();
        $themeName = $entry; // fallback display name

        // Check theme.json
        $jsonPath = $dir . '/theme.json';
        if (!file_exists($jsonPath)) {
            $issues[] = 'Missing <strong>theme.json</strong> manifest file.';
        } else {
            $raw = @file_get_contents($jsonPath);
            $cfg = @json_decode($raw, true);

            if ($raw === false || $raw === '') {
                $issues[] = '<strong>theme.json</strong> is empty or unreadable.';
            } elseif ($cfg === null && json_last_error() !== JSON_ERROR_NONE) {
                $issues[] = '<strong>theme.json</strong> contains invalid JSON: '
                          . htmlspecialchars_uni(json_last_error_msg());
            } else {
                if (empty($cfg['name'])) {
                    $issues[] = '<strong>theme.json</strong> is missing the required <code>"name"</code> field.';
                } else {
                    $themeName = $cfg['name'];
                }

                // Check if name conflicts with a different slug
                if (!empty($cfg['name'])) {
                    $expectedSlug = $ms->slug($cfg['name']);
                    if ($expectedSlug !== $entry) {
                        $issues[] = 'Directory name <code>' . htmlspecialchars_uni($entry)
                                  . '</code> does not match the expected slug <code>'
                                  . htmlspecialchars_uni($expectedSlug)
                                  . '</code> (from theme name "' . htmlspecialchars_uni($cfg['name']) . '").';
                    }
                }

                // Validate stylesheets references
                if (!empty($cfg['stylesheets']) && is_array($cfg['stylesheets'])) {
                    foreach ($cfg['stylesheets'] as $ss) {
                        if (!empty($ss['name'])) {
                            $cssFile = $dir . '/css/' . $ss['name'];
                            if (!file_exists($cssFile)) {
                                $issues[] = 'Stylesheet <code>css/' . htmlspecialchars_uni($ss['name'])
                                          . '</code> referenced in theme.json but file is missing.';
                            }
                        }
                    }
                }

                // Validate JS references
                if (!empty($cfg['js']) && is_array($cfg['js'])) {
                    foreach ($cfg['js'] as $jsFile) {
                        $jsPath = $dir . '/js/' . $jsFile;
                        if (!file_exists($jsPath)) {
                            $issues[] = 'JavaScript file <code>js/' . htmlspecialchars_uni($jsFile)
                                      . '</code> referenced in theme.json but file is missing.';
                        }
                    }
                }
            }
        }

        // Check templates directory
        $tmplDir = $dir . '/templates';
        if (!is_dir($tmplDir)) {
            $issues[] = 'Missing <strong>templates/</strong> directory.';
        } else {
            // Check for at least one .html template file
            $hasHtml = false;
            $scan = function ($d) use (&$scan, &$hasHtml) {
                foreach (scandir($d) as $e) {
                    if ($e === '.' || $e === '..') continue;
                    $p = $d . '/' . $e;
                    if (is_dir($p)) {
                        $scan($p);
                    } elseif (pathinfo($e, PATHINFO_EXTENSION) === 'html') {
                        $hasHtml = true;
                        return;
                    }
                    if ($hasHtml) return;
                }
            };
            $scan($tmplDir);
            if (!$hasHtml) {
                $issues[] = '<strong>templates/</strong> directory contains no <code>.html</code> template files.';
            }
        }

        // Check CSS directory (optional but warn if referenced)
        if (file_exists($jsonPath) && isset($cfg['stylesheets']) && !empty($cfg['stylesheets'])) {
            if (!is_dir($dir . '/css')) {
                $issues[] = 'theme.json references stylesheets but the <strong>css/</strong> directory is missing.';
            }
        }

        // Check DB sync status
        $nameKey = strtolower(trim($themeName));
        if (!isset($dbByName[$nameKey]) && file_exists($jsonPath) && isset($cfg) && is_array($cfg) && !empty($cfg['name'])) {
            $issues[] = 'Theme exists on disk but is <strong>not synced</strong> to the database. Click "Sync" to import.';
        }

        // Check file permissions
        if (!is_readable($dir)) {
            $issues[] = 'Theme directory is not readable (permission issue).';
        }
        if (file_exists($jsonPath) && !is_readable($jsonPath)) {
            $issues[] = '<strong>theme.json</strong> is not readable (permission issue).';
        }

        // Only report if there are actual structural issues (not just unsynced)
        if (!empty($issues)) {
            // Skip directories already listed as valid disk themes with no real issues
            // (an "unsynced" warning alone is informational, not broken)
            $realIssues = array_filter($issues, function ($msg) {
                return strpos($msg, 'not synced') === false;
            });

            $brokenThemes[] = array(
                'slug'       => $entry,
                'name'       => $themeName,
                'issues'     => $issues,
                'is_broken'  => !empty($realIssues),
            );
        }
    }
}

// Display broken themes warning if any have real issues
$reallyBroken = array_filter($brokenThemes, function ($t) { return $t['is_broken']; });
if (!empty($reallyBroken)) {
    $brokenTable = new Table;
    $brokenTable->construct_header("Theme");
    $brokenTable->construct_header("Issues");

    foreach ($reallyBroken as $bt) {
        $brokenTable->construct_cell('<strong>' . htmlspecialchars_uni($bt['name']) . '</strong><br /><small>themes/' . htmlspecialchars_uni($bt['slug']) . '/</small>');
        $issueList = '<ul style="margin:0;padding-left:20px">';
        foreach ($bt['issues'] as $issue) {
            $issueList .= '<li>' . $issue . '</li>';
        }
        $issueList .= '</ul>';
        $brokenTable->construct_cell($issueList);
        $brokenTable->construct_row();
    }

    $brokenTable->output("Broken Themes Detected");
}

/* ----------------------------------------------------------------
   Theme Table
   ---------------------------------------------------------------- */

$table = new Table;
$table->construct_header("Theme");
$table->construct_header("Status", array('width' => '15%', 'class' => 'align_center'));
$table->construct_header("Options", array('width' => '10%', 'class' => 'align_center'));

if (empty($dbThemes) && empty($diskThemes)) {
    $table->construct_cell("No themes found. Import a theme to get started.", array('colspan' => 3));
    $table->construct_row();
} else {
    // Counter for unique popup IDs
    $themeCounter = 0;

    // Show DB themes
    foreach ($dbThemes as $t) {
        $themeCounter++;
        $slug = htmlspecialchars_uni($t['slug']);

        // Theme name â€” clickable link to editor if disk-based
        if ($t['has_disk'] && $t['slug']) {
            $nameCell = '<strong><a href="index.php?module=mystudio-manage&amp;action=editor&amp;slug=' . $slug . '">'
                      . htmlspecialchars_uni($t['name']) . '</a></strong>';
        } else {
            $nameCell = '<strong>' . htmlspecialchars_uni($t['name']) . '</strong>';
        }

        // Add DB-only badge if no disk files
        if (!$t['has_disk']) {
            $nameCell .= ' <small style="color:#888">(database only)</small>';
        }

        // Default icon (like MyBB does)
        $set_default = '';
        if ($t['is_default']) {
            $set_default = '<div class="float_right"><img src="styles/' . $page->style . '/images/icons/default.png" alt="Default" title="Default Theme" style="vertical-align:middle" /></div>';
        } else {
            $set_default = '<div class="float_right"><a href="index.php?module=mystudio-manage&amp;action=activate&amp;tid='
                         . $t['tid'] . '&amp;my_post_key=' . $post_key . '"><img src="styles/' . $page->style
                         . '/images/icons/make_default.png" alt="Set as Default" title="Set as Default" style="vertical-align:middle" /></a></div>';
        }

        $table->construct_cell($set_default . $nameCell);

        // Status
        if ($t['is_default']) {
            $table->construct_cell('<strong>Default</strong>', array('class' => 'align_center'));
        } elseif (!$t['has_disk']) {
            $table->construct_cell('Not Synced', array('class' => 'align_center'));
        } else {
            $table->construct_cell('Installed', array('class' => 'align_center'));
        }

        // Options popup
        $popup = new PopupMenu("theme_{$themeCounter}", "Options");

        if ($t['has_disk'] && $t['slug']) {
            $popup->add_item("Edit Theme", "index.php?module=mystudio-manage&amp;action=editor&amp;slug=" . $slug);
            $popup->add_item("Sync from Disk", "index.php?module=mystudio-manage&amp;action=sync_theme&amp;slug=" . $slug
                           . "&amp;my_post_key=" . $post_key, "return confirm('Sync this theme from disk to database?')");
        } else {
            $popup->add_item("Convert to Disk", "index.php?module=mystudio-manage&amp;action=convert&amp;tid="
                           . $t['tid'] . "&amp;my_post_key=" . $post_key, "return confirm('Extract this theme to disk?')");
        }

        if ($t['is_default']) {
            $popup->add_item("Deactivate", "index.php?module=mystudio-manage&amp;action=deactivate&amp;tid="
                           . $t['tid'] . "&amp;my_post_key=" . $post_key, "return confirm('Deactivate this theme?')");
        } else {
            $popup->add_item("Set as Default", "index.php?module=mystudio-manage&amp;action=activate&amp;tid="
                           . $t['tid'] . "&amp;my_post_key=" . $post_key);

            $deleteMsg = $t['has_disk']
                ? 'Delete this theme? This will remove it from the database AND delete all files from disk. This cannot be undone.'
                : 'Delete this theme from the database? This cannot be undone.';
            $popup->add_item("Delete Theme", "index.php?module=mystudio-manage&amp;action=delete_theme&amp;tid="
                           . $t['tid'] . "&amp;disk=" . ($t['has_disk'] ? '1' : '0')
                           . "&amp;my_post_key=" . $post_key, "return confirm('" . addslashes($deleteMsg) . "')");
        }

        $table->construct_cell($popup->fetch(), array('class' => 'align_center'));
        $table->construct_row();
    }

    // Show disk-only themes (not in DB)
    foreach ($diskThemes as $dt) {
        if ($dt['has_db']) continue; // already shown above
        $themeCounter++;
        $slug = htmlspecialchars_uni($dt['slug']);

        $nameCell = '<strong><a href="index.php?module=mystudio-manage&amp;action=editor&amp;slug=' . $slug . '">'
                  . htmlspecialchars_uni($dt['name']) . '</a></strong>'
                  . ' <small style="color:#888">(disk only)</small>';
        $table->construct_cell($nameCell);

        $table->construct_cell('Not Synced', array('class' => 'align_center'));

        // Options popup
        $popup = new PopupMenu("theme_{$themeCounter}", "Options");
        $popup->add_item("Edit Theme", "index.php?module=mystudio-manage&amp;action=editor&amp;slug=" . $slug);
        $popup->add_item("Sync to Database", "index.php?module=mystudio-manage&amp;action=sync_theme&amp;slug=" . $slug
                       . "&amp;my_post_key=" . $post_key, "return confirm('Sync this theme from disk to database?')");
        $popup->add_item("Delete from Disk", "index.php?module=mystudio-manage&amp;action=delete_theme&amp;tid=0&amp;disk=1&amp;slug=" . $slug
                       . "&amp;my_post_key=" . $post_key,
                       "return confirm('Delete this theme from disk? All files in themes/" . $slug . "/ will be removed. This cannot be undone.')");

        $table->construct_cell($popup->fetch(), array('class' => 'align_center'));
        $table->construct_row();
    }
}

$table->output("Themes");

$page->output_footer();
