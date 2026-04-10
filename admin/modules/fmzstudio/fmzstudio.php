<?php
/**
 * FMZ Studio -- Admin Module
 *
 * Handles all FMZ Studio pages: Manage, Import, Export, Global FMZ Options, Header & Footer, Editor.
 * Registered as a top-level admin module via module_meta.php.
 *
 * @version 2.1.0
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

require_once MYBB_ROOT . 'inc/plugins/fmzstudio/core.php';
require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';

global $mybb, $db, $page, $lang, $cache, $plugins;

$fmz = new FMZStudio();

// Load Bootstrap Icons on all FMZ Studio pages
$page->extra_header .= '<link rel="stylesheet" href="../themes/fmz-default/vendor/bootstrap-icons.min.css" />';

/* ====================================================================
   Toolbar Builder Renderer (graphical drag & drop for toolbar config)
   ==================================================================== */

function fmz_render_toolbar_builder($id, $currentValue)
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
            return '<span class="fmz-tb-chip fmz-tb-sep" draggable="true" data-id="|" title="Separator">'
                 . '<i class="bi bi-grip-vertical" style="opacity:.4"></i> |</span>';
        }
        if (!$info) return '';
        $icon  = htmlspecialchars_uni($info['icon']);
        $label = htmlspecialchars_uni($info['label']);
        $bid   = htmlspecialchars_uni($btnId);
        return '<span class="fmz-tb-chip" draggable="true" data-id="' . $bid . '" title="' . $label . '">'
             . '<i class="bi ' . $icon . '"></i> ' . $label . '</span>';
    };

    $html = '<input type="hidden" name="' . $fieldName . '" id="fmz-tb-value" value="' . htmlspecialchars_uni($currentValue) . '" />';

    // Styles
    $html .= '<style>
.fmz-tb-builder{display:flex;gap:12px;margin-top:6px}
.fmz-tb-panel{flex:1;border:1px solid #ddd;border-radius:6px;background:#fafafa;min-height:120px}
.fmz-tb-panel-head{padding:6px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
.fmz-tb-panel-body{padding:6px;display:flex;flex-wrap:wrap;gap:4px;min-height:80px}
.fmz-tb-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#fff;border:1px solid #ccc;border-radius:4px;font-size:12px;cursor:grab;user-select:none;transition:background .12s,border-color .12s,box-shadow .12s}
.fmz-tb-chip:hover{background:#e8f5e9;border-color:#0d9488}
.fmz-tb-chip.fmz-tb-sep{background:#f5f5f5;color:#999;font-weight:700}
.fmz-tb-chip.fmz-tb-dragover{border-color:#0d9488;box-shadow:-2px 0 0 #0d9488}
.fmz-tb-chip i{font-size:14px}
.fmz-tb-panel-body.fmz-tb-dragover-zone{background:#e0f2f1;border-color:#0d9488}
.fmz-tb-add-sep{background:none;border:1px dashed #aaa;border-radius:4px;padding:4px 10px;font-size:11px;color:#888;cursor:pointer;white-space:nowrap}
.fmz-tb-add-sep:hover{border-color:#0d9488;color:#0d9488}
</style>';

    // Active panel
    $html .= '<div class="fmz-tb-builder">';
    $html .= '<div class="fmz-tb-panel">';
    $html .= '<div class="fmz-tb-panel-head">Active Toolbar <button type="button" class="fmz-tb-add-sep" id="fmz-tb-add-sep" title="Add Separator">+ Separator</button></div>';
    $html .= '<div class="fmz-tb-panel-body" id="fmz-tb-active">';
    foreach ($activeIds as $aid) {
        if ($aid === '|') {
            $html .= $chipHtml('|');
        } elseif (isset($allButtons[$aid])) {
            $html .= $chipHtml($aid, $allButtons[$aid]);
        }
    }
    $html .= '</div></div>';

    // Available panel
    $html .= '<div class="fmz-tb-panel">';
    $html .= '<div class="fmz-tb-panel-head">Available Buttons</div>';
    $html .= '<div class="fmz-tb-panel-body" id="fmz-tb-available">';
    foreach ($availableIds as $aid) {
        $html .= $chipHtml($aid, $allButtons[$aid]);
    }
    $html .= '</div></div>';
    $html .= '</div>';

    // JavaScript for drag & drop
    $html .= '<script>
(function(){
    var activeEl = document.getElementById("fmz-tb-active");
    var availEl  = document.getElementById("fmz-tb-available");
    var hiddenInput = document.getElementById("fmz-tb-value");
    var dragItem = null;

    function syncValue() {
        var chips = activeEl.querySelectorAll(".fmz-tb-chip");
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
            document.querySelectorAll(".fmz-tb-dragover").forEach(function(el){ el.classList.remove("fmz-tb-dragover"); });
            document.querySelectorAll(".fmz-tb-dragover-zone").forEach(function(el){ el.classList.remove("fmz-tb-dragover-zone"); });
        });
        chip.addEventListener("dragover", function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = "move";
            chip.classList.add("fmz-tb-dragover");
        });
        chip.addEventListener("dragleave", function() {
            chip.classList.remove("fmz-tb-dragover");
        });
        chip.addEventListener("drop", function(e) {
            e.preventDefault();
            chip.classList.remove("fmz-tb-dragover");
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
            panel.classList.add("fmz-tb-dragover-zone");
        });
        panel.addEventListener("dragleave", function(e) {
            if (!panel.contains(e.relatedTarget)) {
                panel.classList.remove("fmz-tb-dragover-zone");
            }
        });
        panel.addEventListener("drop", function(e) {
            e.preventDefault();
            panel.classList.remove("fmz-tb-dragover-zone");
            if (!dragItem) return;
            // Only append if dropped on the panel itself (not on a chip)
            if (e.target === panel) {
                panel.appendChild(dragItem);
                syncValue();
            }
        });
    }

    document.querySelectorAll(".fmz-tb-chip").forEach(bindChip);
    bindPanel(activeEl);
    bindPanel(availEl);

    document.getElementById("fmz-tb-add-sep").addEventListener("click", function() {
        var sep = document.createElement("span");
        sep.className = "fmz-tb-chip fmz-tb-sep";
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
    // MyBB admin passes the action via the module param (e.g. fmzstudio-plugin_settings)
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
   License Gate â€” require a valid license before any other action
   ==================================================================== */

require_once MYBB_ROOT . 'inc/plugins/fmzstudio/license.php';
FMZLicense::ensureSettings();   // migration safety â€” creates rows if missing

if ($action !== 'license') {
    // Primary gate â€” encrypted license validation
    if (!FMZLicense::isValid()) {
        admin_redirect("index.php?module=fmzstudio-license");
        exit;
    }
    // Integrity gate â€” detect tampering with the license file itself
    $__fmz_ih = FMZLicense::integrityHash();
    if (empty($__fmz_ih)) {
        admin_redirect("index.php?module=fmzstudio-license");
        exit;
    }
}

/* ====================================================================
   Shared Styles (kept minimal â€” prefer MyBB native components)
   ==================================================================== */

/* ====================================================================
   Inline Help Box Helper
   ==================================================================== */

function fmz_help_box($title, $body) {
    return '<div style="margin-top:16px;margin-bottom:16px;border:1px solid #3a7d76;border-radius:6px;background:linear-gradient(135deg,#f0faf9 0%,#f8fffe 100%);overflow:hidden">'
         . '<div style="display:flex;align-items:center;gap:8px;padding:10px 14px">'
         . '<i class="bi bi-info-circle" style="color:#0d9488;font-size:15px"></i>'
         . '<strong style="color:#0b7c72;font-size:13px">' . $title . '</strong></div>'
         . '<div style="padding:0 16px 14px;font-size:12.5px;line-height:1.7;color:#444">' . $body . '</div>'
         . '</div>';
}

/**
 * Render a single nav-link repeater row (used in the admin UI).
 */
function fmz_nav_link_row($index, $link = array())
{
    $text = isset($link['text']) ? htmlspecialchars_uni($link['text']) : '';
    $url  = isset($link['url'])  ? htmlspecialchars_uni($link['url'])  : '';
    $icon = isset($link['icon']) ? htmlspecialchars_uni($link['icon']) : '';
    return '<tr class="fmz-nav-row">'
        . '<td><input type="text" class="fmz-nav-text" value="' . $text . '" placeholder="Link text" style="width:100%;padding:4px 6px;font-size:12px;border:1px solid #ddd;border-radius:3px" /></td>'
        . '<td><input type="text" class="fmz-nav-url" value="' . $url . '" placeholder="https://..." style="width:100%;padding:4px 6px;font-size:12px;border:1px solid #ddd;border-radius:3px" /></td>'
        . '<td>'
        . '<input type="hidden" class="fmz-nav-icon" value="' . $icon . '" />'
        . '<button type="button" class="fmz-icon-pick-btn" data-target-type="nav" style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;font-size:12px;border:1px solid #ddd;border-radius:4px;background:#fafafa;cursor:pointer">'
        . '<i class="bi ' . ($icon ?: 'bi-grid-3x3-gap') . ' fmz-nav-icon-preview"></i> <span class="fmz-icon-label">' . ($icon ?: 'Choose icon') . '</span></button>'
        . '</td>'
        . '<td style="text-align:center"><button type="button" class="fmz-nav-del" style="background:none;border:none;color:#e74c3c;cursor:pointer;font-size:14px" title="Remove"><i class="bi bi-trash"></i></button></td>'
        . '</tr>';
}

/**
 * Return the master icon list as an associative array.
 * Keys are Bootstrap Icon class names, values are human-readable labels.
 * Grouped by category for the modal grid.
 */
function fmz_get_icon_list()
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
 * Handles all option types: text, textarea, yesno, select, color, numeric, image, nav_links.
 */
function fmz_render_option_row($form, $form_container, $key, $def, $values, $mybb)
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
                   . '<input type="hidden" name="opt_' . $safeKey . '" id="fmz-icon-val-' . $safeKey . '" value="' . $safeVal . '" />'
                   . '<button type="button" class="fmz-icon-pick-btn" data-target-input="fmz-icon-val-' . $safeKey . '" data-target-preview="fmz-icon-prev-' . $safeKey . '" data-target-label="fmz-icon-lbl-' . $safeKey . '" '
                   . 'style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;font-size:13px;border:1px solid #ccc;border-radius:6px;background:#fafafa;cursor:pointer">'
                   . '<i class="bi ' . $previewIcon . '" id="fmz-icon-prev-' . $safeKey . '" style="font-size:20px"></i> '
                   . '<span id="fmz-icon-lbl-' . $safeKey . '">' . ($safeVal ?: 'Choose icon&hellip;') . '</span>'
                   . '</button>';
            if ($safeVal) {
                $input .= ' <button type="button" class="fmz-icon-clear-btn" data-target-input="fmz-icon-val-' . $safeKey . '" data-target-preview="fmz-icon-prev-' . $safeKey . '" data-target-label="fmz-icon-lbl-' . $safeKey . '" '
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
        $slugCheck = $db->simple_select('fmz_pages', 'pid', "slug='" . $slug_esc . "'" . ($pid ? " AND pid != {$pid}" : ''));
        if ($db->num_rows($slugCheck)) {
            echo json_encode(array('error' => 'A page with this slug already exists.'));
            exit;
        }

        if ($pid > 0) {
            $db->update_query('fmz_pages', $data, "pid={$pid}");
        } else {
            $data['author_uid'] = intval($mybb->user['uid']);
            $data['created_at'] = TIME_NOW;
            // Get next disporder
            $query = $db->simple_select('fmz_pages', 'MAX(disporder) as maxd');
            $data['disporder'] = intval($db->fetch_field($query, 'maxd')) + 1;
            $pid = $db->insert_query('fmz_pages', $data);
        }

        echo json_encode(array('success' => true, 'pid' => $pid));
        exit;
    }

    // â”€â”€ Get page data â”€â”€
    if ($api_action === 'get') {
        $pid = intval($mybb->get_input('pid'));
        $query = $db->simple_select('fmz_pages', '*', "pid={$pid}");
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
                $db->update_query('fmz_pages', array('disporder' => intval($i)), "pid=" . intval($pid));
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
        $check = $db->simple_select('fmz_pages', 'pid', $where);
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
                $cCheck = $db->simple_select('fmz_pages', 'pid', $cWhere);
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
            $check = $db->simple_select('fmz_pages', 'pid', "slug='" . $db->escape_string($front_page_slug) . "'");
            if (!$db->num_rows($check)) {
                echo json_encode(array('error' => 'Selected page does not exist.'));
                exit;
            }
        } elseif ($front_page_type === 'portal') {
            // Portal â€” no slug needed
        } else {
            $front_page_type = 'default';
        }

        $cache->update('fmz_front_page', array(
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
        $db->delete_query('fmz_pages', "pid={$pid}");
        flash_message('Page deleted successfully.', 'success');
    }
    admin_redirect("index.php?module=fmzstudio-pages");
}

/* ====================================================================
   Page Manager â€” Add / Edit Page (HTML Editor)
   ==================================================================== */

if ($action === 'pages_add' || $action === 'pages_edit') {
    // Secondary license validation â€” independent code path
    if (!FMZLicense::assertLicensed()) {
        flash_message('License validation failed. Please re-enter your license key.', 'error');
        admin_redirect("index.php?module=fmzstudio-license");
    }

    $pid = intval($mybb->get_input('pid'));
    $pageData = array();

    if ($action === 'pages_edit' && $pid > 0) {
        $query = $db->simple_select('fmz_pages', '*', "pid={$pid}");
        $pageData = $db->fetch_array($query);
        if (!$pageData) {
            flash_message('Page not found.', 'error');
            admin_redirect("index.php?module=fmzstudio-pages");
        }
    }

    // Load usergroups for permission selector
    $usergroups = array();
    $query = $db->simple_select('usergroups', 'gid, title', '', array('order_by' => 'title'));
    while ($row = $db->fetch_array($query)) {
        $usergroups[] = $row;
    }

    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Page Manager", "index.php?module=fmzstudio-pages");
    $page->add_breadcrumb_item($action === 'pages_edit' ? "Edit Page" : "Add Page");

    $page->output_header("FMZ Studio - Page Editor");

    $pageDataJson = json_encode($pageData ?: new stdClass());
    $usergroupsJson = json_encode($usergroups);
    $bbnameJson = json_encode($mybb->settings['bbname']);
    $bburlJson = json_encode($mybb->settings['bburl']);
    $postKey = $mybb->post_code;

    echo <<<HTML
<link rel="stylesheet" href="../jscripts/fmzstudio/pagebuilder.css" />

<div id="pb-builder" data-pid="{$pid}" data-post-key="{$postKey}">
    <!-- Top Bar -->
    <div class="pb-topbar">
        <div class="pb-topbar-left">
            <a href="index.php?module=fmzstudio-pages" class="pb-topbar-btn" title="Back"><i class="bi bi-arrow-left"></i></a>
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
window.FMZ_PAGE_DATA = {$pageDataJson};
window.FMZ_PAGE_USERGROUPS = {$usergroupsJson};
window.FMZ_PAGE_BBNAME = {$bbnameJson};
window.FMZ_PAGE_BBURL = {$bburlJson};
</script>
<script src="../jscripts/fmzstudio/pagebuilder.js"></script>
HTML;

    $page->output_footer();
    exit;
}

/* ====================================================================
   Page Manager â€” List Pages
   ==================================================================== */

if ($action === 'pages') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Page Manager");

    $page->output_header("FMZ Studio - Page Manager");

    // List pages
    $pages = array();
    if ($db->table_exists('fmz_pages')) {
        $query = $db->simple_select('fmz_pages', '*', '', array('order_by' => 'disporder', 'order_dir' => 'ASC'));
        while ($row = $db->fetch_array($query)) {
            $pages[] = $row;
        }
    }

    // â”€â”€ Buttons at top â”€â”€
    $page->output_nav_tabs(array('pages' => array('title' => 'Page Manager', 'link' => 'index.php?module=fmzstudio-pages&action=pages')), 'pages');

    // â”€â”€ Front Page Selector â”€â”€
    $front_page_data = $cache->read('fmz_front_page');
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

    $fpForm = new Form("index.php?module=fmzstudio-pages_api&action=pages_api", "post", "fmz_front_page_form");
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
        '<input type="button" class="submit_button" value="Add Page" onclick="window.location.href=\'index.php?module=fmzstudio-pages_add&action=pages_add\';" />'
    );
    $fpForm->output_submit_wrapper($fpButtons);
    $fpForm->end();

    echo '<script>
document.getElementById("fmz_front_page_form").addEventListener("submit", function(e) {
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
    fetch("index.php?module=fmzstudio-pages_api&action=pages_api", { method: "POST", body: fd })
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
        $table->construct_cell('No pages created yet. <a href="index.php?module=fmzstudio-pages_add&action=pages_add">Create your first page</a>.', array('colspan' => 6, 'class' => 'align_center'));
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

        $actions = '<a href="index.php?module=fmzstudio-pages_edit&action=pages_edit&pid=' . intval($p['pid']) . '">Edit</a>'
                 . ' | <a href="' . $viewUrl . '" target="_blank">View</a>'
                 . ' | <a href="index.php?module=fmzstudio-pages_delete&action=pages_delete&pid=' . intval($p['pid']) . '&my_post_key=' . $mybb->post_code . '" onclick="return confirm(\'Delete this page?\');" style="color: red;">Delete</a>';
        $table->construct_cell($actions, array('class' => 'align_center'));
        $table->construct_row();
    }

    $table->output("Pages");

    $page->output_footer();
    exit;
}

/* ====================================================================
   License Page — Activate / Deactivate license key
   ==================================================================== */

if ($action === 'license') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("License");

    $errors  = [];
    $success = '';

    // Handle POST actions
    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        $licAction = $mybb->get_input('lic_action');

        if ($licAction === 'activate') {
            $inputKey = trim($mybb->get_input('license_key'));
            if (empty($inputKey)) {
                $errors[] = 'Please enter a license key.';
            } else {
                $result = FMZLicense::activate($inputKey);
                if ($result['success']) {
                    $success = htmlspecialchars_uni($result['message']);
                } else {
                    $errors[] = htmlspecialchars_uni($result['message']);
                }
            }
        } elseif ($licAction === 'deactivate') {
            $result = FMZLicense::deactivate();
            if ($result['success']) {
                $success = htmlspecialchars_uni($result['message']);
            } else {
                $errors[] = htmlspecialchars_uni($result['message']);
            }
        }
    }

    $page->output_header("FMZ Studio - License");

    // Show errors / success via standard MyBB flash
    if (!empty($errors)) {
        $page->output_inline_error($errors);
    }
    if (!empty($success)) {
        flash_message($success, 'success');
    }

    // Current license state
    $currentKey    = FMZLicense::getKey();
    $currentStatus = FMZLicense::getStatus();
    $currentEmail  = FMZLicense::getEmail();
    $currentExpiry = FMZLicense::getExpiry();
    $currentDomain = FMZLicense::getDomain();
    $isValid       = FMZLicense::isValid();

    $statusLabels = [
        'valid'            => 'Active',
        'expired'          => 'Expired',
        'suspended'        => 'Suspended',
        'canceled'         => 'Canceled',
        'invalid_location' => 'Invalid Domain',
        'invalid_version'  => 'Invalid Version',
        'unknown'          => 'Unknown',
    ];

    if ($isValid) {
        // â”€â”€ Licensed: show info table + deactivation â”€â”€
        $statusLabel = $statusLabels[$currentStatus] ?? ucfirst($currentStatus);
        $maskedKey   = substr($currentKey, 0, 4) . str_repeat('*', max(0, strlen($currentKey) - 8)) . substr($currentKey, -4);
        $expiryText  = in_array($currentExpiry, ['lifetime', 'managed', ''], true) ? 'Managed by server' : date('F j, Y', strtotime($currentExpiry));

        $table = new Table;
        $table->construct_header("Field", ['width' => 180]);
        $table->construct_header("Value");

        $statusBadge = '<strong>' . htmlspecialchars_uni($statusLabel) . '</strong>';
        $table->construct_cell('<strong>Status</strong>');
        $table->construct_cell($statusBadge);
        $table->construct_row();

        $table->construct_cell('<strong>License Key</strong>');
        $table->construct_cell('<code>' . htmlspecialchars_uni($maskedKey) . '</code>');
        $table->construct_row();

        if ($currentEmail) {
            $table->construct_cell('<strong>Email</strong>');
            $table->construct_cell(htmlspecialchars_uni($currentEmail));
            $table->construct_row();
        }

        $table->construct_cell('<strong>Expires</strong>');
        $table->construct_cell(htmlspecialchars_uni($expiryText));
        $table->construct_row();

        $table->construct_cell('<strong>Domain</strong>');
        $table->construct_cell('<code>' . htmlspecialchars_uni($currentDomain) . '</code>');
        $table->construct_row();

        $table->output("License Information");

        // Deactivate form
        $deactivateForm = new Form("index.php?module=fmzstudio-license", "post");
        echo $deactivateForm->generate_hidden_field('lic_action', 'deactivate');

        $form_container = new FormContainer("Manage License");
        $form_container->output_row(
            "Clear License",
            "Remove the license key from this installation. To release the license for another domain, manage it through your client area. FMZ Studio will stop working here until a new key is entered.",
            $deactivateForm->generate_submit_button("Clear License", array('onclick' => "return confirm('Are you sure you want to clear this license? FMZ Studio will stop working until a new key is entered.')"))
        );
        $form_container->end();

        $deactivateForm->end();

    } else {
        // â”€â”€ Not licensed: show activation form â”€â”€
        if (!empty($currentKey) && empty($errors)) {
            $statusLabel = $statusLabels[$currentStatus] ?? 'Invalid';
            if (in_array($currentStatus, ['suspended', 'canceled', 'expired'], true)) {
                $page->output_inline_error(['Your license has been <strong>' . htmlspecialchars_uni($statusLabel) . '</strong>. Please enter a valid license key to continue using FMZ Studio.']);
            } else {
                $page->output_inline_error(['Your current license is <strong>' . htmlspecialchars_uni($statusLabel) . '</strong>. Please enter a valid license key to continue using FMZ Studio.']);
            }
        }

        $activateForm = new Form("index.php?module=fmzstudio-license", "post");
        echo $activateForm->generate_hidden_field('lic_action', 'activate');

        $form_container = new FormContainer("Activate License");

        $form_container->output_row(
            "License Key <em>*</em>",
            "Enter your FMZ Studio license key from your client area.",
            $activateForm->generate_text_box('license_key', '', ['style' => 'width:300px;font-family:monospace;letter-spacing:1px', 'placeholder' => 'XXXX-XXXX-XXXX-XXXX'])
        );

        $form_container->output_row(
            "Domain",
            "Your license will be bound to this domain.",
            '<code>' . htmlspecialchars_uni(FMZLicense::getSiteDomain()) . '</code>'
        );

        $form_container->end();

        $buttons = [$activateForm->generate_submit_button("Activate License")];
        $activateForm->output_submit_wrapper($buttons);

        $activateForm->end();

        // Help box
        echo fmz_help_box("Need help?",
            '<ul style="margin:0;padding-left:20px;line-height:1.8">'
            . '<li>You can find your license key in your client area under your active services.</li>'
            . '<li>Each license key is bound to one domain. To transfer, clear the license here and manage it from your client area.</li>'
            . '<li>Contact support if your key was lost or compromised.</li>'
            . '</ul>'
        );
    }

    $page->output_footer();
    exit;
}

/* ====================================================================
   File API Endpoints â€” secondary license check on all write operations
   ==================================================================== */

if (in_array($action, ['api_savefile', 'api_createfile', 'api_createfolder', 'api_deletefile', 'api_deletefolder', 'api_rename', 'api_sync'], true)) {
    if (!FMZLicense::assertLicensed()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'License validation failed.']);
        exit;
    }
}

if ($action === 'api_filetree') {
    header('Content-Type: application/json');
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $tree = $fmz->getFileTree($slug);
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
    $data = $fmz->readThemeFile($slug, $path);
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
    $ok = $fmz->writeThemeFile($slug, $path, $content, true);
    echo json_encode(array('success' => $ok, 'time' => date('H:i:s'), 'errors' => $fmz->getErrors()));
    exit;
}

if ($action === 'api_filelist') {
    header('Content-Type: application/json');
    $slug  = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $files = $fmz->getFlatFileList($slug);
    echo json_encode(array('files' => $files !== false ? $files : array()));
    exit;
}

if ($action === 'api_createfile') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $ok   = $fmz->createThemeFile($slug, $path);
    echo json_encode(array('success' => $ok, 'errors' => $fmz->getErrors()));
    exit;
}

if ($action === 'api_createfolder') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $ok   = $fmz->createThemeFolder($slug, $path);
    echo json_encode(array('success' => $ok, 'errors' => $fmz->getErrors()));
    exit;
}

if ($action === 'api_deletefile') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $ok   = $fmz->deleteThemeFile($slug, $path);
    echo json_encode(array('success' => $ok, 'errors' => $fmz->getErrors()));
    exit;
}

if ($action === 'api_deletefolder') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $path = $mybb->get_input('path');
    $ok   = $fmz->deleteThemeFolder($slug, $path);
    echo json_encode(array('success' => $ok, 'errors' => $fmz->getErrors()));
    exit;
}

if ($action === 'api_rename') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug    = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $oldPath = $mybb->get_input('old_path');
    $newPath = $mybb->get_input('new_path');
    $ok = $fmz->renameThemePath($slug, $oldPath, $newPath);
    echo json_encode(array('success' => $ok, 'errors' => $fmz->getErrors()));
    exit;
}

if ($action === 'api_sync') {
    header('Content-Type: application/json');
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
    $tid  = $fmz->syncToDatabase($slug);
    echo json_encode(array(
        'success' => $tid !== false,
        'tid'     => $tid,
        'errors'  => $fmz->getErrors()
    ));
    exit;
}

/* ====================================================================
   Editor Page (full-page Monaco editor)
   ==================================================================== */

if ($action === 'editor') {
    // Secondary license validation â€” independent code path
    if (!FMZLicense::assertLicensed()) {
        flash_message('License validation failed. Please re-enter your license key.', 'error');
        admin_redirect("index.php?module=fmzstudio-license");
    }

    $slug = $mybb->get_input('slug');
    if (empty($slug)) {
        flash_message('No theme specified.', 'error');
        admin_redirect("index.php?module=fmzstudio-manage");
    }

    // Verify the theme directory exists
    $themeDir = MYBB_ROOT . 'themes/' . preg_replace('/[^a-z0-9\-]/', '', $slug);
    if (!is_dir($themeDir)) {
        flash_message('Theme directory not found on disk.', 'error');
        admin_redirect("index.php?module=fmzstudio-manage");
    }

    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Editor: " . htmlspecialchars_uni($slug));

    // Monaco loader CDN
    $page->extra_header .= '
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.50.0/min/vs/loader.min.js"></script>';

    $page->output_header("FMZ Studio - Editor");

    $post_key = $mybb->post_code;
    $base_url = "index.php?module=fmzstudio-manage";
    $safe_slug = htmlspecialchars_uni($slug);
    $bburl = $mybb->settings['bburl'];

    echo <<<HTML
<div id="fmzEditorConfig"
     data-base-url="{$base_url}"
     data-post-key="{$post_key}"
     data-slug="{$safe_slug}"
     style="display:none"></div>

<style>
/* -- FMZ Studio Editor Styles (Light) -- */
.fmz-editor-wrap{display:flex;height:75vh;background:#ffffff;border-radius:6px;overflow:hidden;position:relative;border:1px solid #dee2e6}
#fmz-sidebar{width:260px;min-width:160px;background:#f5f5f5;display:flex;flex-direction:column;border-right:1px solid #dee2e6;transition:width .2s}
#fmz-sidebar.collapsed{width:0;min-width:0;overflow:hidden;border-right:none}
#fmz-btn-collapse{background:#e8e8e8;border:none;color:#666;font-size:16px;cursor:pointer;padding:6px 3px;border-radius:0;line-height:1;display:flex;align-items:center;z-index:2;border-right:1px solid #dee2e6}
#fmz-btn-collapse:hover{background:#ddd;color:#333}
.fmz-sidebar-header{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;background:#e8e8e8;border-bottom:1px solid #dee2e6;gap:4px}
.fmz-sidebar-header .fmz-sidebar-title{font-size:11px;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;overflow:hidden}
.fmz-sidebar-btns{display:flex;gap:2px}
.fmz-sidebar-btns button{background:none;border:1px solid transparent;color:#666;font-size:14px;cursor:pointer;padding:2px 5px;border-radius:3px;line-height:1}
.fmz-sidebar-btns button:hover{background:#ddd;color:#333;border-color:#ccc}
.fmz-search-wrap{padding:6px 8px;border-bottom:1px solid #dee2e6}
#fmz-file-search{width:100%;background:#fff;border:1px solid #ccc;color:#333;padding:4px 8px;border-radius:3px;font-size:12px;outline:none;box-sizing:border-box}
#fmz-file-search:focus{border-color:#0d9488}
#fmz-file-tree{flex:1;overflow:auto;padding:4px 0;font-size:13px;font-family:Consolas,'Courier New',monospace}
.fmz-tree-item{display:flex;align-items:center;padding:3px 8px;cursor:pointer;color:#333;white-space:nowrap;gap:4px;user-select:none}
.fmz-tree-item:hover{background:#e8f0fe}
.fmz-tree-item.active{background:#d3e3fd;color:#1a1a1a}
.fmz-tree-arrow{font-size:10px;width:14px;text-align:center;color:#888;flex-shrink:0}
.fmz-tree-icon{font-size:14px;flex-shrink:0}
.fmz-tree-name{flex:1;overflow:hidden;text-overflow:ellipsis}
.fmz-tree-children{padding-left:16px}
.fmz-tree-folder-dirty{background:#e2b340;color:#fff;font-size:9px;font-weight:bold;padding:0 4px;border-radius:3px;margin-left:4px}
.fmz-loading,.fmz-error{padding:20px;color:#888;text-align:center;font-size:13px}
.fmz-error{color:#c0392b}
#fmz-resize-handle{width:4px;background:#dee2e6;cursor:col-resize;flex-shrink:0;transition:background .15s}
#fmz-resize-handle:hover,#fmz-resize-handle.active{background:#0d9488}
.fmz-main{flex:1;display:flex;flex-direction:column;min-width:0}
.fmz-tabs-bar{display:flex;background:#f5f5f5;border-bottom:1px solid #dee2e6;overflow-x:auto;flex-shrink:0;min-height:35px}
.fmz-tab{display:flex;align-items:center;padding:6px 12px;color:#666;font-size:12px;cursor:pointer;border-right:1px solid #dee2e6;white-space:nowrap;gap:6px;font-family:Consolas,monospace;max-width:180px}
.fmz-tab:hover{background:#e8f0fe;color:#333}
.fmz-tab.active{background:#ffffff;color:#1a1a1a;border-bottom:2px solid #0d9488}
.fmz-tab .fmz-tab-dirty{color:#b8860b;font-weight:bold}
.fmz-tab .fmz-tab-close{opacity:.5;font-size:14px;line-height:1}
.fmz-tab .fmz-tab-close:hover{opacity:1;color:#c0392b}
#fmz-monaco{flex:1;min-height:0}
.fmz-monaco-placeholder{display:flex;align-items:center;justify-content:center;height:100%;color:#999;font-size:15px;font-style:italic}
.fmz-statusbar{display:flex;align-items:center;justify-content:space-between;padding:2px 12px;background:#007acc;color:#fff;font-size:11px;flex-shrink:0}
.fmz-notify{position:fixed;top:20px;right:20px;padding:10px 20px;border-radius:6px;color:#fff;font-size:13px;z-index:99999;animation:fmzFadeIn .3s;transition:opacity .3s}
.fmz-notify-success{background:#0d9488}
.fmz-notify-error{background:#c0392b}
.fmz-notify-info{background:#007acc}
@keyframes fmzFadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.fmz-status-saving{color:#b8860b}
.fmz-status-saved{color:#0d9488}
.fmz-status-error{color:#c0392b}
.fmz-context-menu{position:fixed;background:#ffffff;border:1px solid #dee2e6;border-radius:4px;padding:4px 0;min-width:160px;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,.15);font-size:12px}
.fmz-context-menu-item{padding:6px 16px;color:#333;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:6px}
.fmz-context-menu-item:hover{background:#e8f0fe;color:#1a1a1a}
.fmz-context-menu-item.danger{color:#c0392b}
.fmz-context-menu-item.danger:hover{background:#fde8e8;color:#a02020}
.fmz-context-menu-icon{font-size:14px;flex-shrink:0}
.fmz-context-menu-sep{height:1px;background:#dee2e6;margin:4px 0}
</style>

<div class="fmz-editor-wrap">
    <div id="fmz-sidebar">
        <div class="fmz-sidebar-header">
            <span class="fmz-sidebar-title">Explorer</span>
            <div class="fmz-sidebar-btns">
                <button id="fmz-btn-newfile" title="New File"><i class="bi bi-file-earmark-plus"></i></button>
                <button id="fmz-btn-newfolder" title="New Folder"><i class="bi bi-folder-plus"></i></button>
                <button id="fmz-btn-savesync" title="Save &amp; Sync"><i class="bi bi-floppy"></i></button>
                <button id="fmz-btn-collapse-all" title="Collapse All Folders"><i class="bi bi-arrows-collapse"></i></button>
            </div>
        </div>
        <div class="fmz-search-wrap">
            <input type="text" id="fmz-file-search" placeholder="Search files..." />
        </div>
        <div id="fmz-file-tree"></div>
    </div>
    <button id="fmz-btn-collapse" title="Toggle Sidebar"><i class="bi bi-layout-sidebar-inset"></i></button>
    <div id="fmz-resize-handle"></div>
    <div class="fmz-main">
        <div class="fmz-tabs-bar" id="fmz-tab-bar"></div>
        <div id="fmz-monaco">
            <div class="fmz-monaco-placeholder">Select a file to begin editing</div>
        </div>
        <div class="fmz-statusbar">
            <span id="fmz-status-sync">Ready</span>
            <div style="display:flex;gap:16px">
                <span id="fmz-status-pos"></span>
                <span id="fmz-status-lang"></span>
            </div>
        </div>
    </div>
</div>

<div id="fmz-notifications" style="position:fixed;top:20px;right:20px;z-index:100000;display:flex;flex-direction:column;gap:8px"></div>

<script src="{$bburl}/jscripts/fmzstudio/editor.js"></script>
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
        $zipPath = $fmz->exportTheme($tid);

        if ($zipPath && file_exists($zipPath)) {
            $filename = basename($zipPath);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            $fmz->cleanup();
            exit;
        } else {
            $errors = $fmz->getErrors();
            flash_message(implode('<br>', $errors), 'error');
            admin_redirect("index.php?module=fmzstudio-import_export");
        }
    }
    // GET request on old /export URL â†’ redirect to combined page
    admin_redirect("index.php?module=fmzstudio-import_export");
}

/* ====================================================================
   Import Upload (POST handler â€” redirect back to combined page)
   ==================================================================== */

if ($action === 'import') {
    if ($mybb->request_method === 'post' && isset($_FILES['theme_zip'])) {
        verify_post_check($mybb->get_input('my_post_key'));

        $parentTid = $mybb->get_input('parent_tid', MyBB::INPUT_INT);
        if ($parentTid < 1) $parentTid = 1;

        $tid = $fmz->importFromZip($_FILES['theme_zip'], $parentTid);

        if ($tid) {
            flash_message('Theme imported successfully (TID: ' . $tid . ').', 'success');
            admin_redirect("index.php?module=fmzstudio-manage");
        } else {
            $errors = $fmz->getErrors();
            flash_message(implode('<br>', $errors), 'error');
            admin_redirect("index.php?module=fmzstudio-import_export");
        }
    }
    // GET request on old /import URL â†’ redirect to combined page
    admin_redirect("index.php?module=fmzstudio-import_export");
}

/* ====================================================================
   Import / Export â€” Combined Page
   ==================================================================== */

if ($action === 'import_export') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Import / Export");

    $page->output_header("FMZ Studio - Import / Export");

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    //  EXPORT SECTION
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $themes = $fmz->listDbThemes();

    $exportForm = new Form("index.php?module=fmzstudio-export", "post");

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
    $importForm = new Form("index.php?module=fmzstudio-import", "post", "import_form", 1);

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
    if ($fmz->activateTheme($tid)) {
        flash_message('Theme activated as default.', 'success');
    } else {
        flash_message(implode('<br>', $fmz->getErrors()), 'error');
    }
    admin_redirect("index.php?module=fmzstudio-manage");
}

if ($action === 'deactivate') {
    verify_post_check($mybb->get_input('my_post_key'));
    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
    if ($fmz->deactivateTheme($tid)) {
        flash_message('Theme deactivated.', 'success');
    } else {
        flash_message(implode('<br>', $fmz->getErrors()), 'error');
    }
    admin_redirect("index.php?module=fmzstudio-manage");
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
        if ($fmz->deleteTheme($tid, $deleteDisk)) {
            flash_message('Theme deleted successfully.', 'success');
        } else {
            flash_message(implode('<br>', $fmz->getErrors()), 'error');
        }
    } else {
        // Disk-only theme (no DB entry) â€” delete folder directly
        $slug = preg_replace('/[^a-z0-9\-]/', '', $mybb->get_input('slug'));
        if (!empty($slug) && $slug !== 'fmz-default') {
            $themeDir = MYBB_ROOT . 'themes/' . $slug;
            if (is_dir($themeDir)) {
                $fmz->rrmdir($themeDir);
                flash_message('Theme directory deleted: themes/' . htmlspecialchars_uni($slug) . '/', 'success');
            } else {
                flash_message('Theme directory not found.', 'error');
            }
        } else {
            flash_message('Invalid theme or cannot delete the default theme.', 'error');
        }
    }
    admin_redirect("index.php?module=fmzstudio-manage");
}

/* ====================================================================
   Sync Theme (disk -> database)
   ==================================================================== */

if ($action === 'sync_theme') {
    verify_post_check($mybb->get_input('my_post_key'));
    $slug = $mybb->get_input('slug');
    $tid  = $fmz->syncToDatabase($slug);
    if ($tid) {
        flash_message('Theme synced to database (TID: ' . $tid . ').', 'success');
    } else {
        flash_message(implode('<br>', $fmz->getErrors()), 'error');
    }
    admin_redirect("index.php?module=fmzstudio-manage");
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
        $slug = $fmz->slug($theme['name']);
        $result = $fmz->extractThemeToDisk($tid, $slug);
        if ($result) {
            flash_message('Theme extracted to themes/' . htmlspecialchars_uni($slug) . '/.', 'success');
        } else {
            flash_message(implode('<br>', $fmz->getErrors()), 'error');
        }
    } else {
        flash_message('Theme not found.', 'error');
    }
    admin_redirect("index.php?module=fmzstudio-manage");
}

/* ====================================================================
   Upload Theme Asset (logo, favicon, etc.)
   ==================================================================== */

if ($action === 'api_upload_asset') {
    header('Content-Type: application/json');

    if (!FMZLicense::assertLicensed()) {
        echo json_encode(array('error' => 'License validation failed.'));
        exit;
    }

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
   Save Theme Options (POST handler â€” shared by Global FMZ Options & Header & Footer)
   ==================================================================== */

if ($action === 'api_saveoptions') {
    verify_post_check($mybb->get_input('my_post_key'));

    $slug = $mybb->get_input('slug');
    $pageFilter = $mybb->get_input('page_filter');
    $redirectTo = $mybb->get_input('redirect_to');
    $options = $fmz->getThemeOptions($slug);

    if ($options) {
        $existing = $fmz->getThemeOptionValues($slug);
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
                $rawVal = $mybb->get_input('opt_' . $key);
                // Validate JSON for nav_links type
                if ($type === 'nav_links') {
                    if (!empty($rawVal)) {
                        $decoded = @json_decode($rawVal, true);
                        if (!is_array($decoded)) {
                            $rawVal = ''; // Invalid JSON, reset
                        } else {
                            // Sanitize each entry
                            $clean = array();
                            foreach ($decoded as $entry) {
                                if (!is_array($entry)) continue;
                                $text = isset($entry['text']) ? trim($entry['text']) : '';
                                $url  = isset($entry['url'])  ? trim($entry['url'])  : '';
                                if ($text === '' && $url === '') continue;
                                // Validate URL protocol — only allow http(s), relative, or anchor links
                                if ($url !== '' && !preg_match('~^(https?://|/|#)~i', $url)) {
                                    $url = '';
                                }
                                $clean[] = array(
                                    'text' => $text,
                                    'url'  => $url,
                                    'icon' => isset($entry['icon']) ? preg_replace('/[^a-z0-9\-]/', '', $entry['icon']) : '',
                                );
                            }
                            $rawVal = !empty($clean) ? json_encode($clean) : '';
                        }
                    }
                }
                $values[$key] = $rawVal;
            }
        }

        $fmz->saveThemeOptionValues($slug, $values);
        flash_message('Theme options saved.', 'success');
    } else {
        flash_message('No options found for this theme.', 'error');
    }
    $redirect = $redirectTo ? $redirectTo : "index.php?module=fmzstudio-options";
    admin_redirect($redirect);
}

/* ====================================================================
   Global FMZ Options Page
   ==================================================================== */

if ($action === 'options') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Global FMZ Options");

    $page->output_header("FMZ Studio - Global FMZ Options");

    $activeSlug = $fmz->getActiveThemeSlug();

    if (!$activeSlug) {
        echo '<p>No active theme found.</p>';
        $page->output_footer();
        exit;
    }

    $allOptions = $fmz->getThemeOptions($activeSlug);
    if (!$allOptions) {
        echo '<div class="alert"><p>The active theme does not provide any configurable options.</p></div>';
        $page->output_footer();
        exit;
    }

    $values = $fmz->getMergedThemeOptions($activeSlug);

    // Filter options for global page
    $globalOpts = array();
    $groups = array();
    foreach ($allOptions as $key => $def) {
        $optPage = isset($def['page']) ? $def['page'] : '';
        if ($optPage !== 'global') continue;
        if (!empty($def['group'])) {
            $groups[$def['group']][$key] = $def;
        } else {
            $globalOpts[$key] = $def;
        }
    }

    $form = new Form("index.php?module=fmzstudio-manage&action=api_saveoptions", "post", "", 1);
    echo $form->generate_hidden_field('slug', $activeSlug);
    echo $form->generate_hidden_field('page_filter', 'global');
    echo $form->generate_hidden_field('redirect_to', 'index.php?module=fmzstudio-options');

    // â”€â”€ Color Mode â”€â”€
    $form_container = new FormContainer("Global FMZ Options");
    foreach ($globalOpts as $key => $def) {
        // Render color_mode first, then layout/effects after palette
        if ($key !== 'color_mode') continue;
        fmz_render_option_row($form, $form_container, $key, $def, $values, $mybb);
    }
    $form_container->end();

    // â”€â”€ Palette CSS â”€â”€
    echo '<style>
.fmz-pal-table{width:100%;border-collapse:collapse;font-size:13px}
.fmz-pal-table th{text-align:left;padding:6px 8px;background:#f5f5f5;border-bottom:2px solid #ddd;font-size:12px;text-transform:uppercase;color:#666}
.fmz-pal-table td{padding:5px 8px;border-bottom:1px solid #eee;vertical-align:middle}
.fmz-pal-table tr:hover{background:#fafafa}
.fmz-pal-cell{display:flex;align-items:center;gap:6px}
.fmz-pal-cell input[type=color]{width:26px;height:26px;border:1px solid rgba(0,0,0,.15);padding:0;cursor:pointer;border-radius:4px;flex-shrink:0}
.fmz-pal-cell code{font-size:11px;color:#666;min-width:62px}
.fmz-pal-cell .fmz-reset-btn{font-size:10px;background:none;border:1px solid #ccc;border-radius:3px;padding:1px 5px;cursor:pointer;color:#888}
</style>';

    // â”€â”€ Quick Presets â”€â”€
    $presets = array(
        'teal' => array(
            'label' => 'Teal', 'swatch' => '#0d9488',
            'light' => array('accent'=>'#0d9488','accent_hover'=>'#0f766e','heading_bg'=>'#0d9488','border'=>'#ccfbf1','link'=>'#0d9488','link_hover'=>'#0f766e','btn_bg'=>'#0d9488','btn_hover'=>'#0f766e'),
            'dark'  => array('accent'=>'#2dd4bf','accent_hover'=>'#5eead4','heading_bg'=>'#0d9488','border'=>'#134e4a','link'=>'#2dd4bf','link_hover'=>'#5eead4','btn_bg'=>'#2dd4bf','btn_hover'=>'#5eead4'),
        ),
        'ocean' => array(
            'label' => 'Ocean', 'swatch' => '#0369a1',
            'light' => array('accent'=>'#0369a1','accent_hover'=>'#075985','heading_bg'=>'#0369a1','border'=>'#bae6fd','link'=>'#0369a1','link_hover'=>'#075985','btn_bg'=>'#0369a1','btn_hover'=>'#075985'),
            'dark'  => array('accent'=>'#38bdf8','accent_hover'=>'#7dd3fc','heading_bg'=>'#0369a1','border'=>'#0c4a6e','link'=>'#38bdf8','link_hover'=>'#7dd3fc','btn_bg'=>'#38bdf8','btn_hover'=>'#7dd3fc'),
        ),
        'indigo' => array(
            'label' => 'Indigo', 'swatch' => '#4338ca',
            'light' => array('accent'=>'#4338ca','accent_hover'=>'#3730a3','heading_bg'=>'#4338ca','border'=>'#c7d2fe','link'=>'#4338ca','link_hover'=>'#3730a3','btn_bg'=>'#4338ca','btn_hover'=>'#3730a3'),
            'dark'  => array('accent'=>'#818cf8','accent_hover'=>'#a5b4fc','heading_bg'=>'#4338ca','border'=>'#312e81','link'=>'#818cf8','link_hover'=>'#a5b4fc','btn_bg'=>'#818cf8','btn_hover'=>'#a5b4fc'),
        ),
        'purple' => array(
            'label' => 'Purple', 'swatch' => '#7e22ce',
            'light' => array('accent'=>'#7e22ce','accent_hover'=>'#6b21a8','heading_bg'=>'#7e22ce','border'=>'#e9d5ff','link'=>'#7e22ce','link_hover'=>'#6b21a8','btn_bg'=>'#7e22ce','btn_hover'=>'#6b21a8'),
            'dark'  => array('accent'=>'#c084fc','accent_hover'=>'#d8b4fe','heading_bg'=>'#7e22ce','border'=>'#581c87','link'=>'#c084fc','link_hover'=>'#d8b4fe','btn_bg'=>'#c084fc','btn_hover'=>'#d8b4fe'),
        ),
        'rose' => array(
            'label' => 'Rose', 'swatch' => '#be123c',
            'light' => array('accent'=>'#be123c','accent_hover'=>'#9f1239','heading_bg'=>'#be123c','border'=>'#fecdd3','link'=>'#be123c','link_hover'=>'#9f1239','btn_bg'=>'#be123c','btn_hover'=>'#9f1239'),
            'dark'  => array('accent'=>'#fb7185','accent_hover'=>'#fda4af','heading_bg'=>'#be123c','border'=>'#881337','link'=>'#fb7185','link_hover'=>'#fda4af','btn_bg'=>'#fb7185','btn_hover'=>'#fda4af'),
        ),
        'amber' => array(
            'label' => 'Amber', 'swatch' => '#b45309',
            'light' => array('accent'=>'#b45309','accent_hover'=>'#92400e','heading_bg'=>'#b45309','border'=>'#fde68a','link'=>'#b45309','link_hover'=>'#92400e','btn_bg'=>'#b45309','btn_hover'=>'#92400e'),
            'dark'  => array('accent'=>'#fbbf24','accent_hover'=>'#fcd34d','heading_bg'=>'#b45309','border'=>'#78350f','link'=>'#fbbf24','link_hover'=>'#fcd34d','btn_bg'=>'#fbbf24','btn_hover'=>'#fcd34d'),
        ),
        'emerald' => array(
            'label' => 'Emerald', 'swatch' => '#059669',
            'light' => array('accent'=>'#059669','accent_hover'=>'#047857','heading_bg'=>'#059669','border'=>'#a7f3d0','link'=>'#059669','link_hover'=>'#047857','btn_bg'=>'#059669','btn_hover'=>'#047857'),
            'dark'  => array('accent'=>'#34d399','accent_hover'=>'#6ee7b7','heading_bg'=>'#059669','border'=>'#064e3b','link'=>'#34d399','link_hover'=>'#6ee7b7','btn_bg'=>'#34d399','btn_hover'=>'#6ee7b7'),
        ),
        'crimson' => array(
            'label' => 'Crimson', 'swatch' => '#dc2626',
            'light' => array('accent'=>'#dc2626','accent_hover'=>'#b91c1c','heading_bg'=>'#dc2626','border'=>'#fecaca','link'=>'#dc2626','link_hover'=>'#b91c1c','btn_bg'=>'#dc2626','btn_hover'=>'#b91c1c'),
            'dark'  => array('accent'=>'#f87171','accent_hover'=>'#fca5a5','heading_bg'=>'#dc2626','border'=>'#7f1d1d','link'=>'#f87171','link_hover'=>'#fca5a5','btn_bg'=>'#f87171','btn_hover'=>'#fca5a5'),
        ),
        'sapphire' => array(
            'label' => 'Sapphire', 'swatch' => '#1d4ed8',
            'light' => array('accent'=>'#1d4ed8','accent_hover'=>'#1e40af','heading_bg'=>'#1d4ed8','border'=>'#bfdbfe','link'=>'#1d4ed8','link_hover'=>'#1e40af','btn_bg'=>'#1d4ed8','btn_hover'=>'#1e40af'),
            'dark'  => array('accent'=>'#60a5fa','accent_hover'=>'#93bbfd','heading_bg'=>'#1d4ed8','border'=>'#1e3a5f','link'=>'#60a5fa','link_hover'=>'#93bbfd','btn_bg'=>'#60a5fa','btn_hover'=>'#93bbfd'),
        ),
        'coral' => array(
            'label' => 'Coral', 'swatch' => '#c2410c',
            'light' => array('accent'=>'#c2410c','accent_hover'=>'#9a3412','heading_bg'=>'#c2410c','border'=>'#fed7aa','link'=>'#c2410c','link_hover'=>'#9a3412','btn_bg'=>'#c2410c','btn_hover'=>'#9a3412'),
            'dark'  => array('accent'=>'#fb923c','accent_hover'=>'#fdba74','heading_bg'=>'#c2410c','border'=>'#7c2d12','link'=>'#fb923c','link_hover'=>'#fdba74','btn_bg'=>'#fb923c','btn_hover'=>'#fdba74'),
        ),
        'slate' => array(
            'label' => 'Slate', 'swatch' => '#475569',
            'light' => array('accent'=>'#475569','accent_hover'=>'#334155','heading_bg'=>'#475569','border'=>'#cbd5e1','link'=>'#475569','link_hover'=>'#334155','btn_bg'=>'#475569','btn_hover'=>'#334155'),
            'dark'  => array('accent'=>'#94a3b8','accent_hover'=>'#cbd5e1','heading_bg'=>'#475569','border'=>'#334155','link'=>'#94a3b8','link_hover'=>'#cbd5e1','btn_bg'=>'#94a3b8','btn_hover'=>'#cbd5e1'),
        ),
        'pink' => array(
            'label' => 'Pink', 'swatch' => '#db2777',
            'light' => array('accent'=>'#db2777','accent_hover'=>'#be185d','heading_bg'=>'#db2777','border'=>'#fbcfe8','link'=>'#db2777','link_hover'=>'#be185d','btn_bg'=>'#db2777','btn_hover'=>'#be185d'),
            'dark'  => array('accent'=>'#f472b6','accent_hover'=>'#f9a8d4','heading_bg'=>'#db2777','border'=>'#831843','link'=>'#f472b6','link_hover'=>'#f9a8d4','btn_bg'=>'#f472b6','btn_hover'=>'#f9a8d4'),
        ),
    );
    $presetsJson = json_encode($presets);

    echo '<div style="margin:12px 0 16px;padding:14px 18px;background:#fff;border:1px solid #e5e7eb;border-radius:8px">';
    echo '<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">';
    echo '<strong style="font-size:13px;color:#374151"><i class="bi bi-palette me-1"></i> Quick Presets</strong>';
    echo '<span style="font-size:11px;color:#9ca3af">Click to apply a color scheme â€” all palette fields update instantly</span>';
    echo '</div>';
    echo '<div id="fmz-presets" style="display:flex;flex-wrap:wrap;gap:8px">';
    foreach ($presets as $id => $preset) {
        echo '<button type="button" class="fmz-preset-btn" data-preset="' . htmlspecialchars_uni($id) . '" '
           . 'style="display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 10px;border:2px solid transparent;border-radius:8px;background:#f9fafb;cursor:pointer;transition:all .15s" '
           . 'title="' . htmlspecialchars_uni($preset['label']) . '">'
           . '<span style="width:28px;height:28px;border-radius:50%;background:' . htmlspecialchars_uni($preset['swatch']) . ';border:2px solid rgba(0,0,0,.1);display:block"></span>'
           . '<span style="font-size:10px;color:#6b7280;font-weight:500">' . htmlspecialchars_uni($preset['label']) . '</span>'
           . '</button>';
    }
    echo '</div>';
    echo '</div>';

    // â”€â”€ Render palette groups (no preview strip) â”€â”€
    $groupLabels = array(
        'palette_light' => 'Color Palette â€” Light Mode',
        'palette_dark'  => 'Color Palette â€” Dark Mode',
    );

    $activeMode = isset($values['color_mode']) ? $values['color_mode'] : 'light';

    foreach (array('palette_light', 'palette_dark') as $groupId) {
        if (empty($groups[$groupId])) continue;

        $mode = ($groupId === 'palette_dark') ? 'dark' : 'light';
        $hideStyle = ($mode !== $activeMode) ? ' style="display:none"' : '';
        echo '<div class="fmz-palette-group" data-palette-mode="' . $mode . '"' . $hideStyle . '>';

        $label = isset($groupLabels[$groupId]) ? $groupLabels[$groupId] : $groupId;
        $modeIcon = ($groupId === 'palette_dark') ? '&#x1F319;' : '&#x2600;&#xFE0F;';
        $form_container = new FormContainer($modeIcon . ' ' . $label);

        // Compact table â€” no preview strip
        $tableHtml = '<table class="fmz-pal-table"><thead><tr><th>Color</th><th>Value</th><th>CSS Variable</th></tr></thead><tbody>';
        foreach ($groups[$groupId] as $key => $def) {
            $val = isset($values[$key]) ? $values[$key] : (isset($def['default']) ? $def['default'] : '');
            $defaultVal = isset($def['default']) ? $def['default'] : '';
            $cssVar = isset($def['css_var']) ? $def['css_var'] : '';

            $resetBtn = '';
            if ($defaultVal && strtolower($val) !== strtolower($defaultVal)) {
                $resetBtn = ' <button type="button" class="fmz-reset-btn" data-default="' . htmlspecialchars_uni($defaultVal) . '" title="Reset to ' . htmlspecialchars_uni($defaultVal) . '">&#x21A9;</button>';
            }

            $tableHtml .= '<tr>'
                        . '<td><strong>' . htmlspecialchars_uni($def['title']) . '</strong></td>'
                        . '<td><div class="fmz-pal-cell">'
                        . '<input type="color" name="opt_' . htmlspecialchars_uni($key) . '" value="' . htmlspecialchars_uni($val) . '" class="fmz-palette-input" data-group="' . $groupId . '" data-default="' . htmlspecialchars_uni($defaultVal) . '" />'
                        . '<code class="fmz-hex-label">' . htmlspecialchars_uni($val) . '</code>'
                        . $resetBtn
                        . '</div></td>'
                        . '<td><code style="font-size:11px;color:#999">' . htmlspecialchars_uni($cssVar) . '</code></td>'
                        . '</tr>';
        }
        $tableHtml .= '</tbody></table>';
        $form_container->output_row('', '', $tableHtml);

        $form_container->end();
        echo '</div>';
    }

    // â”€â”€ Layout & Effects â”€â”€
    $layoutOpts = array('show_sidebar', 'loading_bar');
    $hasLayout = false;
    foreach ($layoutOpts as $lk) {
        if (isset($globalOpts[$lk])) { $hasLayout = true; break; }
    }
    if ($hasLayout) {
        $form_container = new FormContainer("Layout & Effects");
        foreach ($layoutOpts as $lk) {
            if (isset($globalOpts[$lk])) {
                fmz_render_option_row($form, $form_container, $lk, $globalOpts[$lk], $values, $mybb);
            }
        }
        $form_container->end();
    }

    $buttons = array($form->generate_submit_button("Save Options"));
    $form->output_submit_wrapper($buttons);
    echo $form->end();

    echo '<script>
(function(){
  function initPaletteUI(){
    document.querySelectorAll(".fmz-palette-input").forEach(function(el){
      el.addEventListener("input",function(){
        var label=this.parentNode.querySelector(".fmz-hex-label");
        if(label) label.textContent=this.value;
        var defVal=this.getAttribute("data-default");
        var resetBtn=this.parentNode.querySelector(".fmz-reset-btn");
        if(resetBtn&&defVal){
          resetBtn.style.display=(this.value.toLowerCase()===defVal.toLowerCase())?"none":"inline";
        }
      });
    });
    document.querySelectorAll(".fmz-reset-btn").forEach(function(btn){
      btn.addEventListener("click",function(){
        var def=this.getAttribute("data-default");
        var cell=this.closest(".fmz-pal-cell");
        var input=cell?cell.querySelector("input[type=color]"):null;
        if(input&&def){input.value=def;input.dispatchEvent(new Event("input"));}
        this.style.display="none";
      });
    });
    document.querySelectorAll("[name=\'opt_color_mode\']").forEach(function(radio){
      radio.addEventListener("change",function(){
        var mode=this.value;
        document.querySelectorAll(".fmz-palette-group").forEach(function(g){
          g.style.display=(g.getAttribute("data-palette-mode")===mode)?"":"none";
        });
      });
    });
    var presets=' . $presetsJson . ';
    document.querySelectorAll(".fmz-preset-btn").forEach(function(btn){
      btn.addEventListener("click",function(){
        var id=this.getAttribute("data-preset");
        var preset=presets[id];
        if(!preset) return;
        ["light","dark"].forEach(function(mode){
          var colors=preset[mode];
          if(!colors) return;
          for(var key in colors){
            var input=document.querySelector("[name=\'opt_"+mode+"_"+key+"\']");
            if(input){
              input.value=colors[key];
              input.dispatchEvent(new Event("input"));
            }
          }
        });
        document.querySelectorAll(".fmz-preset-btn").forEach(function(b){
          b.style.borderColor="transparent";b.style.background="#f9fafb";
        });
        this.style.borderColor=preset.swatch;this.style.background="#f0fdf4";
      });
    });
  }
  document.addEventListener("DOMContentLoaded",function(){initPaletteUI();});
})();
</script>';



    $page->output_footer();
    exit;
}

/* ====================================================================
   Header & Footer Options Page
   ==================================================================== */

if ($action === 'options_header_footer') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Header & Footer");

    $page->output_header("FMZ Studio - Header & Footer");

    $activeSlug = $fmz->getActiveThemeSlug();

    if (!$activeSlug) {
        echo '<p>No active theme found.</p>';
        $page->output_footer();
        exit;
    }

    $allOptions = $fmz->getThemeOptions($activeSlug);
    if (!$allOptions) {
        echo '<div class="alert"><p>The active theme does not provide any configurable options.</p></div>';
        $page->output_footer();
        exit;
    }

    $values = $fmz->getMergedThemeOptions($activeSlug);

    // Filter options for header_footer page
    $hfOptions = array();
    foreach ($allOptions as $key => $def) {
        $optPage = isset($def['page']) ? $def['page'] : '';
        if ($optPage === 'header_footer') {
            $hfOptions[$key] = $def;
        }
    }

    if (empty($hfOptions)) {
        echo '<div class="alert"><p>No header/footer options available for this theme.</p></div>';
        $page->output_footer();
        exit;
    }

    echo '<style>
#fmz-nav-links-table{table-layout:fixed}
#fmz-nav-links-table th{font-size:11px;padding:5px 8px}
#fmz-nav-links-table td{padding:4px 6px}
#fmz-nav-links-table input[type=text]{width:100%;box-sizing:border-box;padding:5px 8px;font-size:12px;border:1px solid #ddd;border-radius:4px}
#fmz-nav-links-table .fmz-icon-pick-btn{width:100%;justify-content:center}
.fmz-pal-table{width:100%;border-collapse:collapse;font-size:13px}
.fmz-pal-table th{text-align:left;padding:6px 8px;background:#f5f5f5;border-bottom:2px solid #ddd;font-size:12px;text-transform:uppercase;color:#666}
.fmz-pal-table td{padding:5px 8px;border-bottom:1px solid #eee;vertical-align:middle}
</style>';

    $form = new Form("index.php?module=fmzstudio-manage&action=api_saveoptions", "post", "", 1);
    echo $form->generate_hidden_field('slug', $activeSlug);
    echo $form->generate_hidden_field('page_filter', 'header_footer');
    echo $form->generate_hidden_field('redirect_to', 'index.php?module=fmzstudio-options_header_footer');

    // â”€â”€ Header Options â”€â”€
    $headerKeys = array('header_style', 'logo_icon', 'logo_text', 'site_logo', 'favicon');
    $form_container = new FormContainer("Header");
    foreach ($headerKeys as $hk) {
        if (isset($hfOptions[$hk])) {
            fmz_render_option_row($form, $form_container, $hk, $hfOptions[$hk], $values, $mybb);
        }
    }
    $form_container->end();

    // â”€â”€ Navigation â”€â”€
    if (isset($hfOptions['custom_nav_links'])) {
        $form_container = new FormContainer("Navigation");
        $navDef = $hfOptions['custom_nav_links'];
        $navVal = isset($values['custom_nav_links']) ? $values['custom_nav_links'] : '';
        $navLinks = !empty($navVal) ? @json_decode($navVal, true) : array();
        if (!is_array($navLinks)) $navLinks = array();

        $navHtml = '<div id="fmz-nav-links-wrap">';
        $navHtml .= '<table class="fmz-pal-table" id="fmz-nav-links-table">'
                  . '<thead><tr><th style="width:25%">Link Text</th><th style="width:35%">URL</th><th style="width:25%">Icon</th><th style="width:15%"></th></tr></thead>'
                  . '<tbody>';
        foreach ($navLinks as $i => $link) {
            $navHtml .= fmz_nav_link_row($i, $link);
        }
        $navHtml .= '</tbody></table>';
        $navHtml .= '<button type="button" id="fmz-nav-add-btn" style="margin-top:8px;padding:4px 12px;font-size:12px;cursor:pointer;border:1px solid #0d9488;background:#f0fdfa;color:#0d9488;border-radius:4px">'
                  . '<i class="bi bi-plus-circle"></i> Add Link</button>';
        $navHtml .= '<input type="hidden" name="opt_custom_nav_links" id="fmz-nav-links-json" value="' . htmlspecialchars_uni($navVal) . '" />';
        $navHtml .= '</div>';

        $form_container->output_row(
            '',
            '',
            $navHtml
        );
        $form_container->end();
    }

    // â”€â”€ Footer Options â”€â”€
    $footerKeys = array('footer_text', 'footer_about_text');
    $hasFooter = false;
    foreach ($footerKeys as $fk) {
        if (isset($hfOptions[$fk])) { $hasFooter = true; break; }
    }
    if ($hasFooter) {
        $form_container = new FormContainer("Footer");
        foreach ($footerKeys as $fk) {
            if (isset($hfOptions[$fk])) {
                fmz_render_option_row($form, $form_container, $fk, $hfOptions[$fk], $values, $mybb);
            }
        }
        $form_container->end();
    }

    $buttons = array($form->generate_submit_button("Save Options"));
    $form->output_submit_wrapper($buttons);
    echo $form->end();

    echo '<script>
(function(){
  // â”€â”€ Nav Links Repeater â”€â”€
  function initNavLinks(){
    var wrap=document.getElementById("fmz-nav-links-wrap");
    if(!wrap) return;
    var tbody=document.querySelector("#fmz-nav-links-table tbody");
    var jsonInput=document.getElementById("fmz-nav-links-json");
    var addBtn=document.getElementById("fmz-nav-add-btn");

    function syncJson(){
      var rows=tbody.querySelectorAll(".fmz-nav-row");
      var links=[];
      rows.forEach(function(r){
        var t=r.querySelector(".fmz-nav-text").value.trim();
        var u=r.querySelector(".fmz-nav-url").value.trim();
        var ic=r.querySelector(".fmz-nav-icon").value;
        if(t||u) links.push({text:t,url:u,icon:ic});
      });
      jsonInput.value=links.length?JSON.stringify(links):"";
    }

    function bindRow(tr){
      tr.querySelector(".fmz-nav-text").addEventListener("input",syncJson);
      tr.querySelector(".fmz-nav-url").addEventListener("input",syncJson);
      var pickBtn=tr.querySelector(".fmz-icon-pick-btn");
      if(pickBtn) pickBtn.addEventListener("click",function(){
        var hiddenInput=tr.querySelector(".fmz-nav-icon");
        var preview=tr.querySelector(".fmz-nav-icon-preview");
        var label=tr.querySelector(".fmz-icon-label");
        FmzIconModal.open(hiddenInput.value,function(icon){
          hiddenInput.value=icon;
          preview.className="bi "+(icon||"bi-grid-3x3-gap")+" fmz-nav-icon-preview";
          if(label) label.textContent=icon||"Choose icon";
          syncJson();
        });
      });
      tr.querySelector(".fmz-nav-del").addEventListener("click",function(){
        tr.remove(); syncJson();
      });
    }

    tbody.querySelectorAll(".fmz-nav-row").forEach(bindRow);

    addBtn.addEventListener("click",function(){
      var tr=document.createElement("tr");
      tr.className="fmz-nav-row";
      tr.innerHTML=\'<td><input type="text" class="fmz-nav-text" value="" placeholder="Link text" style="width:100%;padding:4px 6px;font-size:12px;border:1px solid #ddd;border-radius:3px" /></td>\'
        +\'<td><input type="text" class="fmz-nav-url" value="" placeholder="https://..." style="width:100%;padding:4px 6px;font-size:12px;border:1px solid #ddd;border-radius:3px" /></td>\'
        +\'<td><input type="hidden" class="fmz-nav-icon" value="" />\'
        +\'<button type="button" class="fmz-icon-pick-btn" data-target-type="nav" style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;font-size:12px;border:1px solid #ddd;border-radius:4px;background:#fafafa;cursor:pointer">\'
        +\'<i class="bi bi-grid-3x3-gap fmz-nav-icon-preview"></i> <span class="fmz-icon-label">Choose icon</span></button></td>\'
        +\'<td style="text-align:center"><button type="button" class="fmz-nav-del" style="background:none;border:none;color:#e74c3c;cursor:pointer;font-size:14px" title="Remove"><i class="bi bi-trash"></i></button></td>\';
      tbody.appendChild(tr);
      bindRow(tr);
      tr.querySelector(".fmz-nav-text").focus();
    });
  }

  document.addEventListener("DOMContentLoaded",function(){initNavLinks();initIconPickers();});
})();
</script>';

    // â”€â”€ Icon Picker Modal â”€â”€
    $iconListJson = json_encode(fmz_get_icon_list());
    echo '<div id="fmz-icon-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.5);backdrop-filter:blur(2px)">
<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.3);width:640px;max-width:95vw;max-height:85vh;display:flex;flex-direction:column;overflow:hidden">
  <div style="display:flex;align-items:center;justify-content:between;padding:14px 20px;border-bottom:1px solid #eee;gap:10px;flex-shrink:0">
    <i class="bi bi-grid-3x3-gap" style="font-size:18px;color:#0d9488"></i>
    <strong style="font-size:14px;flex:1">Choose Icon</strong>
    <input type="text" id="fmz-icon-search" placeholder="Search icons..." style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;width:220px" />
    <button type="button" id="fmz-icon-modal-close" style="background:none;border:none;font-size:20px;cursor:pointer;color:#888;padding:0 4px">&times;</button>
  </div>
  <div id="fmz-icon-grid" style="padding:12px 16px;overflow-y:auto;flex:1"></div>
  <div style="padding:10px 20px;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center;flex-shrink:0">
    <span id="fmz-icon-count" style="font-size:12px;color:#888"></span>
    <button type="button" id="fmz-icon-clear-selected" style="font-size:12px;padding:4px 12px;border:1px solid #ddd;border-radius:4px;background:#fafafa;cursor:pointer;color:#888">&#x2715; No icon</button>
  </div>
</div>
</div>';

    echo '<style>
.fmz-ig{display:grid;grid-template-columns:repeat(auto-fill,minmax(64px,1fr));gap:6px}
.fmz-ig-item{display:flex;flex-direction:column;align-items:center;gap:3px;padding:8px 4px;border:2px solid transparent;border-radius:8px;cursor:pointer;transition:all .15s;background:#fafafa}
.fmz-ig-item:hover{background:#e0f2fe;border-color:#7dd3fc}
.fmz-ig-item.selected{background:#ccfbf1;border-color:#0d9488}
.fmz-ig-item i{font-size:22px;color:#333}
.fmz-ig-item span{font-size:9px;color:#888;text-align:center;line-height:1.1;word-break:break-word;max-width:60px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
</style>';

    echo '<script>
(function(){
  var icons=' . $iconListJson . ';
  var modal=document.getElementById("fmz-icon-modal");
  var grid=document.getElementById("fmz-icon-grid");
  var searchInput=document.getElementById("fmz-icon-search");
  var countEl=document.getElementById("fmz-icon-count");
  var closeBtn=document.getElementById("fmz-icon-modal-close");
  var clearBtn=document.getElementById("fmz-icon-clear-selected");
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
      html+=\'<div class="fmz-ig-item\'+sel+\'" data-icon="\'+cls+\'"><i class="bi \'+cls+\'"></i><span>\'+label+\'</span></div>\';
      count++;
    }
    if(!count) html=\'<div style="text-align:center;padding:30px;color:#aaa;font-size:13px">No icons match your search</div>\';
    grid.innerHTML=\'<div class="fmz-ig">\'+html+\'</div>\';
    countEl.textContent=count+" icon"+(count!==1?"s":"");
    grid.querySelectorAll(".fmz-ig-item").forEach(function(item){
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
    document.querySelectorAll(".fmz-icon-pick-btn:not([data-target-type=nav])").forEach(function(btn){
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
    document.querySelectorAll(".fmz-icon-clear-btn").forEach(function(btn){
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
})();
</script>';



    $page->output_footer();
    exit;
}

/* ====================================================================
   Save Mini Plugin Options (POST handler)
   ==================================================================== */

if ($action === 'api_save_plugin_options') {
    verify_post_check($mybb->get_input('my_post_key'));

    $activeSlug = $fmz->getActiveThemeSlug();
    $pluginId   = preg_replace('/[^a-z0-9\-_]/', '', $mybb->get_input('plugin_id'));

    if ($activeSlug && $pluginId) {
        $options = $fmz->getMiniPluginOptions($activeSlug, $pluginId);
        if ($options) {
            $values = array();
            foreach ($options as $def) {
                $id = isset($def['id']) ? $def['id'] : '';
                if (empty($id)) continue;
                $values[$id] = $mybb->get_input('opt_' . $id);
            }
            $fmz->saveMiniPluginOptionValues($activeSlug, $pluginId, $values);
            flash_message('Plugin options saved.', 'success');
        }
    }
    admin_redirect("index.php?module=fmzstudio-plugin_settings&plugin=" . urlencode($pluginId));
}

/* ====================================================================
   Toggle Mini Plugin (POST handler)
   ==================================================================== */

if ($action === 'api_toggle_plugin') {
    verify_post_check($mybb->get_input('my_post_key'));

    $activeSlug = $fmz->getActiveThemeSlug();
    $pluginId   = preg_replace('/[^a-z0-9\-_]/', '', $mybb->get_input('plugin_id'));
    $enable     = $mybb->get_input('enable', MyBB::INPUT_INT);

    if ($activeSlug && $pluginId) {
        $states = $fmz->getMiniPluginStates($activeSlug);
        $states[$pluginId] = (bool) $enable;
        $fmz->saveMiniPluginStates($activeSlug, $states);
        flash_message($enable ? 'Plugin enabled.' : 'Plugin disabled.', 'success');
    }
    admin_redirect("index.php?module=fmzstudio-plugins");
}

/* ====================================================================
   Plugin Settings Page (individual plugin options via side nav)
   ==================================================================== */

if ($action === 'plugin_settings') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");

    $activeSlug = $fmz->getActiveThemeSlug();
    $selectedPlugin = preg_replace('/[^a-z0-9\-_]/', '', $mybb->get_input('plugin'));

    if (!$activeSlug || empty($selectedPlugin)) {
        flash_message('Invalid plugin.', 'error');
        admin_redirect("index.php?module=fmzstudio-plugins");
    }

    $allPlugins = $fmz->listMiniPlugins($activeSlug);
    $pluginInfo = null;
    foreach ($allPlugins as $p) {
        if ($p['id'] === $selectedPlugin) {
            $pluginInfo = $p;
            break;
        }
    }

    if (!$pluginInfo) {
        flash_message('Plugin not found.', 'error');
        admin_redirect("index.php?module=fmzstudio-plugins");
    }

    $page->add_breadcrumb_item(htmlspecialchars_uni($pluginInfo['name']));
    $page->output_header("FMZ Studio - " . htmlspecialchars_uni($pluginInfo['name']));

    // Plugin options
    $options = $fmz->getMiniPluginOptions($activeSlug, $selectedPlugin);
    if ($options) {
        $values = $fmz->getMergedMiniPluginOptions($activeSlug, $selectedPlugin);

        $form = new Form("index.php?module=fmzstudio-manage&action=api_save_plugin_options", "post");
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
                    $input = fmz_render_toolbar_builder($id, $val);
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
                        $input .= '<button type="button" class="fmz-swatch-btn" data-value="' . htmlspecialchars_uni($swKey) . '" data-target="' . $hiddenId . '" '
                                . 'style="display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 10px;border:2px solid ' . $borderColor . ';border-radius:8px;background:' . $bgColor . ';cursor:pointer;transition:all .15s" '
                                . 'title="' . htmlspecialchars_uni($swLabel) . '">'
                                . '<span style="width:28px;height:28px;border-radius:50%;background:' . htmlspecialchars_uni($swColor) . ';border:2px solid rgba(0,0,0,.1);display:block"></span>'
                                . '<span style="font-size:10px;color:#6b7280;font-weight:500">' . htmlspecialchars_uni($swLabel) . '</span>'
                                . '</button>';
                    }
                    $input .= '</div>';
                    $input .= '<script>'
                            . 'document.addEventListener("click",function(e){'
                            . 'var btn=e.target.closest(".fmz-swatch-btn");'
                            . 'if(!btn)return;'
                            . 'e.preventDefault();'
                            . 'var target=btn.getAttribute("data-target");'
                            . 'var val=btn.getAttribute("data-value");'
                            . 'document.getElementById(target).value=val;'
                            . 'var wrap=btn.parentNode;'
                            . 'wrap.querySelectorAll(".fmz-swatch-btn").forEach(function(b){'
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
        echo '<p>This plugin has no configurable options.</p>';
    }

    // Include admin.php if it exists (custom admin content)
    if ($pluginInfo['has_admin']) {
        include $pluginInfo['dir'] . '/admin.php';
    }

    $page->output_footer();
    exit;
}

/* ====================================================================
   Plugins Page
   ==================================================================== */

if ($action === 'plugins') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Manage Plugins");

    $activeSlug = $fmz->getActiveThemeSlug();

    // Main plugins list
    $page->output_header("FMZ Studio - Manage Plugins");

    if (!$activeSlug) {
        echo '<p>No active theme found. Activate a theme on the Manage page first.</p>';
        $page->output_footer();
        exit;
    }

    $allPlugins = $fmz->listMiniPlugins($activeSlug);
    $states     = $fmz->getMiniPluginStates($activeSlug);
    $post_key   = $mybb->post_code;

    if (empty($allPlugins)) {
        $table = new Table;
        $table->construct_header("Plugin");
        $table->construct_header("Controls", array('class' => 'align_center', 'width' => 200));
        $table->construct_cell('No mini plugins found for the active theme.', array('colspan' => 2));
        $table->construct_row();
        $table->output("Theme Plugins: " . htmlspecialchars_uni($activeSlug));
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

    // Active plugins table
    $table = new Table;
    $table->construct_header("Plugin");
    $table->construct_header("Controls", array('colspan' => 2, 'class' => 'align_center', 'width' => 300));

    if (empty($activePlugins)) {
        $table->construct_cell('No active plugins.', array('colspan' => 3));
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

            $table->construct_cell('<a href="index.php?module=fmzstudio-manage&amp;action=api_toggle_plugin&amp;plugin_id=' . $pluginId . '&amp;enable=0&amp;my_post_key=' . $post_key . '">Deactivate</a>', array('class' => 'align_center', 'width' => 150));

            if ($p['has_options'] || $p['has_admin']) {
                $table->construct_cell('<a href="index.php?module=fmzstudio-plugin_settings&amp;plugin=' . $pluginId . '">Settings</a>', array('class' => 'align_center', 'width' => 150));
            } else {
                $table->construct_cell('&nbsp;', array('class' => 'align_center', 'width' => 150));
            }

            $table->construct_row();
        }
    }

    $table->output("Active Plugins");

    // Inactive plugins table
    $table = new Table;
    $table->construct_header("Plugin");
    $table->construct_header("Controls", array('colspan' => 2, 'class' => 'align_center', 'width' => 300));

    if (empty($inactivePlugins)) {
        $table->construct_cell('No inactive plugins.', array('colspan' => 3));
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

            $table->construct_cell('<a href="index.php?module=fmzstudio-manage&amp;action=api_toggle_plugin&amp;plugin_id=' . $pluginId . '&amp;enable=1&amp;my_post_key=' . $post_key . '">Activate</a>', array('class' => 'align_center', 'width' => 150));

            if ($p['has_options'] || $p['has_admin']) {
                $table->construct_cell('<a href="index.php?module=fmzstudio-plugin_settings&amp;plugin=' . $pluginId . '">Settings</a>', array('class' => 'align_center', 'width' => 150));
            } else {
                $table->construct_cell('&nbsp;', array('class' => 'align_center', 'width' => 150));
            }

            $table->construct_row();
        }
    }

    $table->output("Inactive Plugins");

    $page->output_footer();
    exit;
}

/* ====================================================================
   Settings Page â€” Plugin-level settings (moved from Configuration)
   ==================================================================== */

if ($action === 'settings') {
    $page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");
    $page->add_breadcrumb_item("Settings");

    // Ensure new settings exist (migration for existing installations)
    $newSettings = array(
        'fmz_dev_auto_sync'     => array('title' => 'Auto Sync (Dev Mode)', 'description' => 'Automatically sync theme files to the database when changes are detected.', 'optionscode' => 'yesno', 'value' => '0', 'disporder' => 3, 'gid' => 0),
        'fmz_dev_sync_interval' => array('title' => 'Auto Sync Interval (seconds)', 'description' => 'How often to check for file changes.', 'optionscode' => 'numeric', 'value' => '2', 'disporder' => 4, 'gid' => 0),
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
            'fmz_enabled'           => intval($mybb->get_input('fmz_enabled')),
            'fmz_max_upload_mb'     => max(1, intval($mybb->get_input('fmz_max_upload_mb'))),
            'fmz_dev_auto_sync'     => intval($mybb->get_input('fmz_dev_auto_sync')),
            'fmz_dev_sync_interval' => max(1, intval($mybb->get_input('fmz_dev_sync_interval'))),
        );

        foreach ($settingsToSave as $name => $value) {
            $db->update_query('settings', array('value' => $db->escape_string($value)), "name='" . $db->escape_string($name) . "'");
        }

        rebuild_settings();

        flash_message('Settings saved successfully.', 'success');
        admin_redirect("index.php?module=fmzstudio-settings");
    }

    // Read current values
    $currentSettings = array();
    $query = $db->simple_select('settings', 'name, value', "name LIKE 'fmz_%'");
    while ($row = $db->fetch_array($query)) {
        $currentSettings[$row['name']] = $row['value'];
    }

    $page->output_header("FMZ Studio - Studio Settings");

    $form = new Form("index.php?module=fmzstudio-settings", "post");

    $form_container = new FormContainer("Plugin Settings");

    $form_container->output_row(
        "Enable FMZ Studio",
        "Master switch to enable or disable FMZ Studio on the frontend. When disabled, themes will still be manageable from the admin panel but no FMZ features will load on the forum.",
        $form->generate_yes_no_radio('fmz_enabled', isset($currentSettings['fmz_enabled']) ? $currentSettings['fmz_enabled'] : 1)
    );

    $form_container->output_row(
        "Max Upload Size (MB)",
        "Maximum allowed ZIP file size in megabytes for theme imports.",
        $form->generate_numeric_field('fmz_max_upload_mb', isset($currentSettings['fmz_max_upload_mb']) ? $currentSettings['fmz_max_upload_mb'] : 20, array('min' => 1, 'max' => 500, 'style' => 'width:80px'))
    );

    $form_container->end();

    $form_container = new FormContainer("Developer Settings");

    $form_container->output_row(
        "Auto Sync (Dev Mode)",
        "When enabled, the forum will poll for file changes in the active theme directory every few seconds and automatically sync to the database. Only runs for admin users browsing the frontend. <strong>Disable in production.</strong>",
        $form->generate_yes_no_radio('fmz_dev_auto_sync', isset($currentSettings['fmz_dev_auto_sync']) ? $currentSettings['fmz_dev_auto_sync'] : 0)
    );

    $form_container->output_row(
        "Auto Sync Interval (seconds)",
        "How often to check for file changes. Lower = faster feedback, higher = less server load. Recommended: 2â€“5 seconds.",
        $form->generate_numeric_field('fmz_dev_sync_interval', isset($currentSettings['fmz_dev_sync_interval']) ? $currentSettings['fmz_dev_sync_interval'] : 2, array('min' => 1, 'max' => 60, 'style' => 'width:80px'))
    );

    $form_container->end();

    $buttons = array($form->generate_submit_button("Save Settings"));
    $form->output_submit_wrapper($buttons);

    $form->end();

    $page->output_footer();
    exit;
}

/* ====================================================================
   Manage Page (default)
   ==================================================================== */

$page->add_breadcrumb_item("FMZ Studio", "index.php?module=fmzstudio-manage");

$page->output_header("FMZ Studio - Manage");

$post_key = $mybb->post_code;

// -- Gather theme data --
$dbThemes   = $fmz->listDbThemes();
$diskThemes = $fmz->listThemesOnDisk();

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
                    $expectedSlug = $fmz->slug($cfg['name']);
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
            $nameCell = '<strong><a href="index.php?module=fmzstudio-manage&amp;action=editor&amp;slug=' . $slug . '">'
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
            $set_default = '<div class="float_right"><a href="index.php?module=fmzstudio-manage&amp;action=activate&amp;tid='
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
            $popup->add_item("Edit Theme", "index.php?module=fmzstudio-manage&amp;action=editor&amp;slug=" . $slug);
            $popup->add_item("Sync from Disk", "index.php?module=fmzstudio-manage&amp;action=sync_theme&amp;slug=" . $slug
                           . "&amp;my_post_key=" . $post_key, "return confirm('Sync this theme from disk to database?')");
        } else {
            $popup->add_item("Convert to Disk", "index.php?module=fmzstudio-manage&amp;action=convert&amp;tid="
                           . $t['tid'] . "&amp;my_post_key=" . $post_key, "return confirm('Extract this theme to disk?')");
        }

        if ($t['is_default']) {
            $popup->add_item("Deactivate", "index.php?module=fmzstudio-manage&amp;action=deactivate&amp;tid="
                           . $t['tid'] . "&amp;my_post_key=" . $post_key, "return confirm('Deactivate this theme?')");
        } else {
            $popup->add_item("Set as Default", "index.php?module=fmzstudio-manage&amp;action=activate&amp;tid="
                           . $t['tid'] . "&amp;my_post_key=" . $post_key);

            $deleteMsg = $t['has_disk']
                ? 'Delete this theme? This will remove it from the database AND delete all files from disk. This cannot be undone.'
                : 'Delete this theme from the database? This cannot be undone.';
            $popup->add_item("Delete Theme", "index.php?module=fmzstudio-manage&amp;action=delete_theme&amp;tid="
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

        $nameCell = '<strong><a href="index.php?module=fmzstudio-manage&amp;action=editor&amp;slug=' . $slug . '">'
                  . htmlspecialchars_uni($dt['name']) . '</a></strong>'
                  . ' <small style="color:#888">(disk only)</small>';
        $table->construct_cell($nameCell);

        $table->construct_cell('Not Synced', array('class' => 'align_center'));

        // Options popup
        $popup = new PopupMenu("theme_{$themeCounter}", "Options");
        $popup->add_item("Edit Theme", "index.php?module=fmzstudio-manage&amp;action=editor&amp;slug=" . $slug);
        $popup->add_item("Sync to Database", "index.php?module=fmzstudio-manage&amp;action=sync_theme&amp;slug=" . $slug
                       . "&amp;my_post_key=" . $post_key, "return confirm('Sync this theme from disk to database?')");
        $popup->add_item("Delete from Disk", "index.php?module=fmzstudio-manage&amp;action=delete_theme&amp;tid=0&amp;disk=1&amp;slug=" . $slug
                       . "&amp;my_post_key=" . $post_key,
                       "return confirm('Delete this theme from disk? All files in themes/" . $slug . "/ will be removed. This cannot be undone.')");

        $table->construct_cell($popup->fetch(), array('class' => 'align_center'));
        $table->construct_row();
    }
}

$table->output("Themes");

$page->output_footer();
