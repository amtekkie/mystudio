<?php
/**
 * FMZ Forum Icons — Mini Plugin Init
 *
 * Provides:
 *  - Admin: Icon picker in forum/category add/edit forms (Bootstrap Icons or image upload)
 *  - Frontend: Replaces default forum status icons with chosen icons
 *  - Status-aware: BI icons inherit theme accent/muted colors; images use grayscale filter
 *
 * Auto-creates required database table on first load.
 *
 * @version 1.0.0
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

global $plugins, $db, $mybb;

// ── Auto-install: create table if missing ──
fmz_forum_icons_install();

if (defined('IN_ADMINCP')) {
    // ── Admin Hooks ──
    $plugins->add_hook('admin_forum_management_add',         'fmz_fi_admin_form');
    $plugins->add_hook('admin_forum_management_edit',        'fmz_fi_admin_form');
    $plugins->add_hook('admin_forum_management_add_commit',  'fmz_fi_admin_save_add');
    $plugins->add_hook('admin_forum_management_edit_commit', 'fmz_fi_admin_save_edit');
} else {
    // ── Frontend Hooks ──
    $plugins->add_hook('pre_output_page', 'fmz_fi_inject_icon_css');
}

/* ═══════════════════════════════════════════════════════════════
   AUTO-INSTALL
   ═══════════════════════════════════════════════════════════════ */

function fmz_forum_icons_install()
{
    global $db;

    if (!$db->table_exists('fmz_forum_icons')) {
        $db->write_query("
            CREATE TABLE " . TABLE_PREFIX . "fmz_forum_icons (
                fid        INT UNSIGNED NOT NULL,
                icon_type  VARCHAR(10)  NOT NULL DEFAULT 'bi',
                icon_value VARCHAR(255) NOT NULL DEFAULT '',
                PRIMARY KEY (fid)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4
        ");
    }

    // Ensure uploads directory exists
    $uploadDir = MYBB_ROOT . 'uploads/forum_icons';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
        @file_put_contents($uploadDir . '/index.html', '');
    }
}

/* ═══════════════════════════════════════════════════════════════
   ADMIN — FORM INJECTION
   ═══════════════════════════════════════════════════════════════ */

/**
 * Inject icon picker fields into the forum add/edit form.
 */
function fmz_fi_admin_form()
{
    global $mybb, $db, $page;

    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);

    // Load existing icon data
    $currentType  = 'none';
    $currentValue = '';
    if ($fid > 0) {
        $query = $db->simple_select('fmz_forum_icons', '*', "fid='{$fid}'", array('limit' => 1));
        $row = $db->fetch_array($query);
        if ($row) {
            $currentType  = $row['icon_type'];
            $currentValue = $row['icon_value'];
        }
    }

    // On POST with errors, use submitted values
    if ($mybb->request_method == 'post') {
        $postedType = $mybb->get_input('fmz_icon_type');
        if ($postedType) {
            $currentType = $postedType;
            if ($postedType === 'bi') {
                $currentValue = $mybb->get_input('fmz_icon_value_bi');
            }
        }
    }

    $bburl = $mybb->settings['bburl'];

    // Parse ALL icon class names from the Bootstrap Icons CSS file
    $allIcons = array();
    $cssPath = MYBB_ROOT . 'themes/fmz-default/vendor/bootstrap-icons.min.css';
    if (file_exists($cssPath)) {
        $cssContent = file_get_contents($cssPath);
        if (preg_match_all('/\.(bi-[a-z0-9-]+)::before/', $cssContent, $matches)) {
            $allIcons = array_unique($matches[1]);
            sort($allIcons);
        }
    }

    // Fallback if CSS parsing fails
    if (empty($allIcons)) {
        $allIcons = array(
            'bi-chat-left-fill', 'bi-chat-left-text-fill', 'bi-chat-dots-fill',
            'bi-chat-fill', 'bi-megaphone-fill', 'bi-newspaper',
            'bi-gear-fill', 'bi-tools', 'bi-shield-fill', 'bi-star-fill',
            'bi-heart-fill', 'bi-fire', 'bi-globe', 'bi-people-fill'
        );
    }

    $allIconsJson = json_encode(array_values($allIcons));

    // Build image preview
    $imagePreview = '';
    if ($currentType === 'image' && $currentValue) {
        $imagePreview = '<img src="' . htmlspecialchars_uni($bburl . '/' . $currentValue) . '" style="max-width:42px;max-height:42px;margin-top:6px;border-radius:4px" />';
    }

    $noneChecked  = ($currentType === 'none' || !$currentType) ? ' checked' : '';
    $biChecked    = ($currentType === 'bi')    ? ' checked' : '';
    $imageChecked = ($currentType === 'image') ? ' checked' : '';

    $biDisplay    = ($currentType === 'bi')    ? 'block' : 'none';
    $imageDisplay = ($currentType === 'image') ? 'block' : 'none';

    $safeValue = htmlspecialchars_uni($currentValue);
    $biFieldValue    = ($currentType === 'bi')    ? $safeValue : '';
    $imageFieldValue = ($currentType === 'image') ? $safeValue : '';

    $biPreview = '';
    if ($currentType === 'bi' && $currentValue) {
        $biPreview = '<i class="bi ' . $safeValue . '"></i>';
    }

    // Build form row HTML as a <tr> to inject into the General form_container table
    $formHtml = '<tr id="fmz_fi_row"><td>'
        . '<label>Forum Icon</label>'
        . '<div class="form_row">'
        . '<div style="margin-bottom:10px">'
        . '<label style="margin-right:16px;cursor:pointer"><input type="radio" name="fmz_icon_type" value="none" id="fmz_fi_type_none"' . $noneChecked . '> None</label> '
        . '<label style="margin-right:16px;cursor:pointer"><input type="radio" name="fmz_icon_type" value="bi" id="fmz_fi_type_bi"' . $biChecked . '> Bootstrap Icon</label> '
        . '<label style="cursor:pointer"><input type="radio" name="fmz_icon_type" value="image" id="fmz_fi_type_image"' . $imageChecked . '> Upload Image</label>'
        . '</div>'
        . '<div id="fmz_fi_bi_section" style="display:' . $biDisplay . '">'
        . '<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">'
        . '<input type="hidden" name="fmz_icon_value_bi" id="fmz_fi_value_bi" value="' . $biFieldValue . '" />'
        . '<button type="button" id="fmz_fi_pick_btn" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;font-size:13px;border:1px solid #ddd;border-radius:6px;background:#fafafa;cursor:pointer">'
        . '<i class="bi ' . ($biFieldValue ?: 'bi-grid-3x3-gap') . '" id="fmz_fi_pick_preview" style="font-size:20px;color:#0d9488"></i> '
        . '<span id="fmz_fi_pick_label">' . ($biFieldValue ?: 'Choose icon...') . '</span>'
        . '</button>'
        . '<button type="button" id="fmz_fi_clear_btn" style="padding:6px 12px;font-size:12px;border:1px solid #ddd;border-radius:4px;background:#fafafa;cursor:pointer;color:#888">&times; Clear</button>'
        . '</div>'
        . '<div id="fmz_fi_preview_bi" style="margin-top:4px;font-size:28px;color:#0d9488">' . $biPreview . '</div>'
        . '</div>'
        . '<div id="fmz_fi_image_section" style="display:' . $imageDisplay . '">'
        . '<input type="file" name="fmz_icon_upload" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp" style="margin-bottom:8px" />'
        . '<div class="smalltext" style="color:#666">Recommended: 42x42 px, PNG or SVG. Max 256 KB.</div>'
        . '<div id="fmz_fi_preview_image">' . $imagePreview . '</div>'
        . '<input type="hidden" name="fmz_icon_value_image" id="fmz_fi_value_image" value="' . $imageFieldValue . '" />'
        . '</div>'
        . '</div>'
        . '</td></tr>';

    $jsonHtml = json_encode($formHtml);

    // Inject Bootstrap Icons CSS, picker styles, and form data variable + icon list
    $page->extra_header .= '<link rel="stylesheet" href="../themes/fmz-default/vendor/bootstrap-icons.min.css" />' . "\n"
        . '<style>'
        . '#fmz_fi_modal{display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.5);backdrop-filter:blur(2px)}'
        . '#fmz_fi_modal_inner{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.3);width:680px;max-width:95vw;max-height:85vh;display:flex;flex-direction:column;overflow:hidden}'
        . '#fmz_fi_modal_header{display:flex;align-items:center;padding:14px 20px;border-bottom:1px solid #eee;gap:10px;flex-shrink:0}'
        . '#fmz_fi_modal_grid{padding:12px 16px;overflow-y:auto;flex:1}'
        . '.fmz-fi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(64px,1fr));gap:6px}'
        . '.fmz-fi-grid-item{display:flex;flex-direction:column;align-items:center;gap:3px;padding:8px 4px;border:2px solid transparent;border-radius:8px;cursor:pointer;transition:all .15s;background:#fafafa}'
        . '.fmz-fi-grid-item:hover{background:#e0f2fe;border-color:#7dd3fc}'
        . '.fmz-fi-grid-item.selected{background:#ccfbf1;border-color:#0d9488}'
        . '.fmz-fi-grid-item i{font-size:22px;color:#333}'
        . '.fmz-fi-grid-item span{font-size:9px;color:#888;text-align:center;line-height:1.1;word-break:break-word;max-width:60px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
        . '#fmz_fi_modal_footer{padding:10px 20px;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center;flex-shrink:0}'
        . '</style>' . "\n"
        . '<script type="text/javascript">var fmzFiFormHtml=' . $jsonHtml . ';var fmzFiAllIcons=' . $allIconsJson . ';</script>' . "\n";

    // Interaction JS (NOWDOC — no PHP interpolation)
    $page->extra_header .= <<<'JSEOF'
<script type="text/javascript">
document.addEventListener("DOMContentLoaded",function(){
    var form=document.querySelector("form[action*='forum-management']");
    if(form)form.setAttribute("enctype","multipart/form-data");

    // Find the first .form_container table and append our row to its tbody
    var table=document.querySelector("table.form_container");
    if(!table)return;
    var tbody=table.querySelector("tbody");
    if(!tbody)return;

    var temp=document.createElement("table");
    temp.innerHTML="<tbody>"+fmzFiFormHtml+"</tbody>";
    var newRow=temp.querySelector("tr");
    if(newRow)tbody.appendChild(newRow);

    // Build modal HTML
    var modalHtml='<div id="fmz_fi_modal">'
        +'<div id="fmz_fi_modal_inner">'
        +'<div id="fmz_fi_modal_header">'
        +'<i class="bi bi-grid-3x3-gap" style="font-size:18px;color:#0d9488"></i>'
        +'<strong style="font-size:14px;flex:1">Choose Icon</strong>'
        +'<input type="text" id="fmz_fi_modal_search" placeholder="Search icons..." style="padding:6px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px;width:240px" />'
        +'<button type="button" id="fmz_fi_modal_close" style="background:none;border:none;font-size:22px;cursor:pointer;color:#888;padding:0 6px">&times;</button>'
        +'</div>'
        +'<div id="fmz_fi_modal_grid"></div>'
        +'<div id="fmz_fi_modal_footer">'
        +'<span id="fmz_fi_modal_count" style="font-size:12px;color:#888"></span>'
        +'<button type="button" id="fmz_fi_modal_no_icon" style="font-size:12px;padding:4px 12px;border:1px solid #ddd;border-radius:4px;background:#fafafa;cursor:pointer;color:#888">&#x2715; No icon</button>'
        +'</div>'
        +'</div>'
        +'</div>';
    document.body.insertAdjacentHTML("beforeend",modalHtml);

    var modal=document.getElementById("fmz_fi_modal");
    var modalGrid=document.getElementById("fmz_fi_modal_grid");
    var modalSearch=document.getElementById("fmz_fi_modal_search");
    var modalCount=document.getElementById("fmz_fi_modal_count");
    var modalClose=document.getElementById("fmz_fi_modal_close");
    var modalNoIcon=document.getElementById("fmz_fi_modal_no_icon");
    var icons=fmzFiAllIcons||[];
    var currentSelected="";

    function renderModalGrid(filter){
        filter=(filter||"").toLowerCase();
        var html="";
        var count=0;
        for(var i=0;i<icons.length;i++){
            var cls=icons[i];
            var label=cls.replace("bi-","").replace(/-/g," ");
            if(filter && cls.indexOf(filter)===-1 && label.indexOf(filter)===-1) continue;
            var sel=(cls===currentSelected)?" selected":"";
            html+='<div class="fmz-fi-grid-item'+sel+'" data-icon="'+cls+'"><i class="bi '+cls+'"></i><span>'+label+'</span></div>';
            count++;
        }
        if(!count) html='<div style="text-align:center;padding:30px;color:#aaa;font-size:13px">No icons match your search</div>';
        modalGrid.innerHTML='<div class="fmz-fi-grid">'+html+'</div>';
        modalCount.textContent=count+" icon"+(count!==1?"s":"");
        modalGrid.querySelectorAll(".fmz-fi-grid-item").forEach(function(item){
            item.addEventListener("click",function(){
                selectIcon(this.getAttribute("data-icon"));
                closeModal();
            });
        });
    }

    function selectIcon(icon){
        currentSelected=icon;
        var hiddenInput=document.getElementById("fmz_fi_value_bi");
        var pickPreview=document.getElementById("fmz_fi_pick_preview");
        var pickLabel=document.getElementById("fmz_fi_pick_label");
        var previewBi=document.getElementById("fmz_fi_preview_bi");
        if(hiddenInput) hiddenInput.value=icon;
        if(pickPreview) pickPreview.className="bi "+(icon||"bi-grid-3x3-gap");
        if(pickLabel) pickLabel.textContent=icon||"Choose icon...";
        if(previewBi) previewBi.innerHTML=icon?'<i class="bi '+icon+'"></i>':"";
        if(icon){
            document.getElementById("fmz_fi_type_bi").checked=true;
            document.getElementById("fmz_fi_bi_section").style.display="block";
            document.getElementById("fmz_fi_image_section").style.display="none";
        }
    }

    function openModal(){
        currentSelected=document.getElementById("fmz_fi_value_bi").value||"";
        modalSearch.value="";
        renderModalGrid("");
        modal.style.display="block";
        setTimeout(function(){modalSearch.focus();},100);
    }

    function closeModal(){
        modal.style.display="none";
    }

    modalClose.addEventListener("click",closeModal);
    modal.addEventListener("click",function(e){if(e.target===modal)closeModal();});
    document.addEventListener("keydown",function(e){if(e.key==="Escape"&&modal.style.display==="block")closeModal();});
    modalSearch.addEventListener("input",function(){renderModalGrid(this.value);});
    modalNoIcon.addEventListener("click",function(){selectIcon("");closeModal();});

    // Radio toggle
    document.querySelectorAll("input[name='fmz_icon_type']").forEach(function(r){
        r.addEventListener("change",function(){
            document.getElementById("fmz_fi_bi_section").style.display=(this.value==="bi")?"block":"none";
            document.getElementById("fmz_fi_image_section").style.display=(this.value==="image")?"block":"none";
        });
    });

    // Pick button opens modal
    var pickBtn=document.getElementById("fmz_fi_pick_btn");
    if(pickBtn) pickBtn.addEventListener("click",openModal);

    // Clear button
    var clearBtn=document.getElementById("fmz_fi_clear_btn");
    if(clearBtn) clearBtn.addEventListener("click",function(){selectIcon("");});
});
</script>
JSEOF;
}

/* ═══════════════════════════════════════════════════════════════
   ADMIN — SAVE HANDLERS
   ═══════════════════════════════════════════════════════════════ */

function fmz_fi_admin_save_add()
{
    global $fid;
    fmz_fi_save_icon_data((int) $fid);
}

function fmz_fi_admin_save_edit()
{
    global $fid;
    fmz_fi_save_icon_data((int) $fid);
}

/**
 * Common save logic for forum icon data.
 */
function fmz_fi_save_icon_data($fid)
{
    global $mybb, $db;

    if ($fid <= 0) return;

    $iconType  = $mybb->get_input('fmz_icon_type');
    $iconValue = '';

    if ($iconType === 'bi') {
        $iconValue = trim($mybb->get_input('fmz_icon_value_bi'));
        if (empty($iconValue)) {
            $iconType = 'none';
        }
    } elseif ($iconType === 'image') {
        // Check for new file upload
        if (!empty($_FILES['fmz_icon_upload']['name']) && $_FILES['fmz_icon_upload']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['fmz_icon_upload'];
            $allowed = array('png', 'jpg', 'jpeg', 'gif', 'svg', 'webp');
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (in_array($ext, $allowed) && $file['size'] <= 256 * 1024) {
                $uploadDir = MYBB_ROOT . 'uploads/forum_icons';
                if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                $filename = 'forum_' . $fid . '.' . $ext;
                // Remove old images for this forum
                foreach (glob($uploadDir . '/forum_' . $fid . '.*') as $old) {
                    @unlink($old);
                }
                if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                    $iconValue = 'uploads/forum_icons/' . $filename;
                } else {
                    $iconType = 'none';
                }
            } else {
                $iconType = 'none';
            }
        } else {
            // No new upload — keep existing image path
            $iconValue = trim($mybb->get_input('fmz_icon_value_image'));
            if (empty($iconValue)) {
                $iconType = 'none';
            }
        }
    } else {
        $iconType = 'none';
    }

    if ($iconType === 'none') {
        $db->delete_query('fmz_forum_icons', "fid='{$fid}'");
    } else {
        $query = $db->simple_select('fmz_forum_icons', 'fid', "fid='{$fid}'", array('limit' => 1));
        if ($db->num_rows($query)) {
            $db->update_query('fmz_forum_icons', array(
                'icon_type'  => $db->escape_string($iconType),
                'icon_value' => $db->escape_string($iconValue),
            ), "fid='{$fid}'");
        } else {
            $db->insert_query('fmz_forum_icons', array(
                'fid'        => $fid,
                'icon_type'  => $db->escape_string($iconType),
                'icon_value' => $db->escape_string($iconValue),
            ));
        }
    }
}

/* ═══════════════════════════════════════════════════════════════
   FRONTEND — ICON CSS INJECTION
   ═══════════════════════════════════════════════════════════════ */

/**
 * Inject per-forum icon overrides into the page output.
 * Hooks on pre_output_page.
 *
 * For BI icons: Replace the icon class in HTML via regex (ficons_{fid} targets)
 * For Images:   Hide <i>, set background-image on the container via CSS
 * Status:       BI icons inherit .forum_on/.forum_off color rules from theme CSS
 *               Images use grayscale + opacity for inactive states
 */
function fmz_fi_inject_icon_css(&$contents)
{
    global $db, $mybb;

    $query = $db->simple_select('fmz_forum_icons', '*');
    $css = '';
    $hasIcons = false;

    while ($row = $db->fetch_array($query)) {
        $fid  = (int) $row['fid'];
        $type = $row['icon_type'];
        $val  = $row['icon_value'];
        $hasIcons = true;

        if ($type === 'bi') {
            $escapedVal = htmlspecialchars($val, ENT_QUOTES);

            // Replace icon classes within elements that have the ficons_{fid} class.
            // Matches: ficons_{fid}" ... <i class="bi bi-whatever-icon">
            // The [^<]* bridging allows for attributes between the container and the <i>.
            // Uses ([\s"]) at end to handle extra CSS classes after the icon name (e.g. "fs-5").
            $pattern = '/(<[^>]*\bficons_' . $fid . '\b[^>]*>[^<]*<i\s+class="bi\s+)bi-[\w-]+([\s"])/s';
            $contents = preg_replace($pattern, '${1}' . $escapedVal . '${2}', $contents);

        } elseif ($type === 'image') {
            $imgUrl = htmlspecialchars($mybb->settings['bburl'] . '/' . $val);

            // Hide the <i> icon and show background image on the container
            $css .= ".ficons_{$fid} i.bi{display:none!important}\n";
            $css .= ".ficons_{$fid}{background:url('{$imgUrl}') center/contain no-repeat!important}\n";

            // Mobile/inline span icon
            $css .= "span.ficons_{$fid} i.bi{display:none!important}\n";
            $css .= "span.ficons_{$fid}{display:inline-block!important;width:20px;height:20px;background:url('{$imgUrl}') center/contain no-repeat!important;vertical-align:middle}\n";

            // Depth3 subforum icon
            $css .= ".subforumicon.ficons_{$fid} i.bi{display:none!important}\n";
            $css .= ".subforumicon.ficons_{$fid}{display:inline-block;width:16px;height:16px;background:url('{$imgUrl}') center/contain no-repeat!important}\n";

            // Status states — grayscale for inactive
            $css .= ".forum_off.ficons_{$fid},.forum_offclose.ficons_{$fid},.forum_offlink.ficons_{$fid}{filter:grayscale(1);opacity:.45}\n";
            $css .= ".forum_on.ficons_{$fid}{filter:none;opacity:1}\n";
            $css .= ".subforum_off.ficons_{$fid},.subforum_minioff.ficons_{$fid}{filter:grayscale(1);opacity:.45}\n";
            $css .= ".subforum_on.ficons_{$fid},.subforum_minion.ficons_{$fid}{filter:none;opacity:1}\n";
        }
    }

    if ($hasIcons && !empty($css)) {
        $styleTag = "<style>/* FMZ Forum Icons */\n{$css}</style>\n";
        $contents = str_replace('</head>', $styleTag . '</head>', $contents);
    }

    return $contents;
}
