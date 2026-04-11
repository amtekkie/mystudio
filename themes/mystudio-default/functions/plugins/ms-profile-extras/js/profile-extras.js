/**
 * MyStudio Profile Extras â€” Client-Side JS
 *
 * Handles:
 *  - Banner modal interactions (upload preview, color/gradient pickers, save/remove)

 */

(function(){
'use strict';

var rootUrl = (typeof rootpath !== 'undefined') ? rootpath : '';
var postKey = (typeof my_post_key !== 'undefined') ? my_post_key : '';

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   BANNER MODAL
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

var bannerPreview  = document.getElementById('ms_banner_preview');
var bannerTypeEl   = document.getElementById('ms_banner_type');
var bannerValueEl  = document.getElementById('ms_banner_value');
var bannerFileEl   = document.getElementById('ms_banner_file');
var bannerUrlEl    = document.getElementById('ms_banner_url');
var bannerColorEl  = document.getElementById('ms_banner_color');
var bannerColorHex = document.getElementById('ms_banner_color_hex');
var gradType       = document.getElementById('ms_grad_type');
var gradAngle      = document.getElementById('ms_grad_angle');
var gradColor1     = document.getElementById('ms_grad_color1');
var gradColor2     = document.getElementById('ms_grad_color2');
var gradApply      = document.getElementById('ms_grad_apply');
var textColorEl    = document.getElementById('ms_banner_text_color');
var textColorHex   = document.getElementById('ms_banner_text_color_hex');
var linkColorEl    = document.getElementById('ms_banner_link_color');
var linkColorHex   = document.getElementById('ms_banner_link_color_hex');
var colorResetBtn  = document.getElementById('ms_color_reset');
var previewText    = document.getElementById('ms_preview_text');
var previewLink    = document.getElementById('ms_preview_link');

function setPreview(css) {
    if (!bannerPreview) return;
    bannerPreview.style.cssText = css + 'height:120px;border-radius:.5rem;border:1px solid var(--tekbb-border);display:flex;align-items:flex-end;justify-content:space-between;padding:8px 12px;position:relative;background-size:cover;background-position:center;';
}

function setSelection(type, value) {
    if (bannerTypeEl)  bannerTypeEl.value  = type;
    if (bannerValueEl) bannerValueEl.value = value;
}

// â”€â”€ File upload preview â”€â”€
if (bannerFileEl) {
    bannerFileEl.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                setPreview("background-image:url('" + e.target.result + "');");
                setSelection('upload', '__file__');
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
}

// â”€â”€ URL input preview â”€â”€
if (bannerUrlEl) {
    bannerUrlEl.addEventListener('input', function() {
        var url = this.value.trim();
        if (url) {
            setPreview("background-image:url('" + url + "');");
            setSelection('upload', url);
        }
    });
}

// â”€â”€ Solid color presets â”€â”€
document.querySelectorAll('.solid-preset-swatch').forEach(function(el) {
    el.addEventListener('click', function() {
        var color = this.dataset.color;
        document.querySelectorAll('.solid-preset-swatch').forEach(function(s){ s.classList.remove('active'); });
        this.classList.add('active');
        setPreview('background:' + color + ';');
        setSelection('solid', color);
        if (bannerColorEl)  bannerColorEl.value  = color;
        if (bannerColorHex) bannerColorHex.value = color;
    });
});

// â”€â”€ Solid custom color picker sync â”€â”€
if (bannerColorEl) {
    bannerColorEl.addEventListener('input', function() {
        if (bannerColorHex) bannerColorHex.value = this.value;
        setPreview('background:' + this.value + ';');
        setSelection('solid', this.value);
    });
}
if (bannerColorHex) {
    bannerColorHex.addEventListener('input', function() {
        if (/^#[0-9a-fA-F]{3,8}$/.test(this.value)) {
            if (bannerColorEl) bannerColorEl.value = this.value;
            setPreview('background:' + this.value + ';');
            setSelection('solid', this.value);
        }
    });
}

// â”€â”€ Gradient presets â”€â”€
document.querySelectorAll('.gradient-preset-swatch').forEach(function(el) {
    el.addEventListener('click', function() {
        var grad = this.dataset.gradient;
        document.querySelectorAll('.gradient-preset-swatch').forEach(function(s){ s.classList.remove('active'); });
        this.classList.add('active');
        setPreview('background:' + grad + ';');
        setSelection('gradient', grad);
    });
});

// â”€â”€ Custom gradient builder â”€â”€
if (gradApply) {
    gradApply.addEventListener('click', function() {
        buildCustomGradient();
    });
}
function buildCustomGradient() {
    if (!gradType || !gradColor1 || !gradColor2) return;
    var type  = gradType.value;
    var c1    = gradColor1.value;
    var c2    = gradColor2.value;
    var angle = gradAngle ? gradAngle.value : '135';
    var grad;
    if (type === 'radial') {
        grad = 'radial-gradient(circle, ' + c1 + ' 0%, ' + c2 + ' 100%)';
    } else {
        grad = 'linear-gradient(' + angle + 'deg, ' + c1 + ' 0%, ' + c2 + ' 100%)';
    }
    setPreview('background:' + grad + ';');
    setSelection('gradient', grad);
}

// â”€â”€ Text color picker sync â”€â”€
if (textColorEl) {
    textColorEl.addEventListener('input', function() {
        if (textColorHex) textColorHex.value = this.value;
        if (previewText) previewText.style.color = this.value;
    });
}
if (textColorHex) {
    textColorHex.addEventListener('input', function() {
        var v = this.value.trim();
        if (/^#[0-9a-fA-F]{3,8}$/.test(v)) {
            if (textColorEl) textColorEl.value = v;
            if (previewText) previewText.style.color = v;
        } else if (v === '') {
            if (previewText) previewText.style.color = '';
        }
    });
}

// â”€â”€ Link color picker sync â”€â”€
if (linkColorEl) {
    linkColorEl.addEventListener('input', function() {
        if (linkColorHex) linkColorHex.value = this.value;
        if (previewLink) previewLink.style.color = this.value;
    });
}
if (linkColorHex) {
    linkColorHex.addEventListener('input', function() {
        var v = this.value.trim();
        if (/^#[0-9a-fA-F]{3,8}$/.test(v)) {
            if (linkColorEl) linkColorEl.value = v;
            if (previewLink) previewLink.style.color = v;
        } else if (v === '') {
            if (previewLink) previewLink.style.color = '';
        }
    });
}

// â”€â”€ Reset text/link colors â”€â”€
if (colorResetBtn) {
    colorResetBtn.addEventListener('click', function() {
        if (textColorHex) textColorHex.value = '';
        if (textColorEl)  textColorEl.value  = '#1f2937';
        if (linkColorHex) linkColorHex.value = '';
        if (linkColorEl)  linkColorEl.value  = '#0d9488';
        if (previewText) previewText.style.color = '';
        if (previewLink) previewLink.style.color = '';
    });
}

// â”€â”€ Gallery items (previous banners) â”€â”€
document.querySelectorAll('.banner-gallery-item').forEach(function(el) {
    el.addEventListener('click', function() {
        var bid = this.dataset.bid;
        document.querySelectorAll('.banner-gallery-item').forEach(function(s){ s.classList.remove('active'); });
        this.classList.add('active');
        setSelection('activate', bid);
        // Preview from element's background
        setPreview(this.style.cssText);
    });
});

// â”€â”€ Save Banner â”€â”€
var saveBtn = document.getElementById('ms_banner_save');
if (saveBtn) {
    saveBtn.addEventListener('click', function() {
        var type  = bannerTypeEl  ? bannerTypeEl.value  : '';
        var value = bannerValueEl ? bannerValueEl.value : '';
        var tColor = textColorHex ? textColorHex.value.trim() : '';
        var lColor = linkColorHex ? linkColorHex.value.trim() : '';

        if (!type) {
            // No banner type selected â€” just update colors on existing banner
            ajaxPost({ms_action:'update_banner_colors', my_post_key:postKey, text_color:tColor, link_color:lColor}, function(resp) {
                if (resp.success) {
                    applyBannerToPage(resp.css, resp.text_color, resp.link_color);
                    closeModal('ms_banner_modal');
                } else {
                    alert(resp.error || 'Error updating colors.');
                }
            });
            return;
        }

        // Handle "activate previous" action
        if (type === 'activate') {
            ajaxPost({ms_action:'activate_banner', my_post_key:postKey, bid:value, text_color:tColor, link_color:lColor}, function(resp) {
                if (resp.success) {
                    applyBannerToPage(resp.css, resp.text_color, resp.link_color);
                    closeModal('ms_banner_modal');
                } else {
                    alert(resp.error || 'Error activating banner.');
                }
            });
            return;
        }

        // File upload requires FormData
        if (type === 'upload' && value === '__file__' && bannerFileEl && bannerFileEl.files[0]) {
            var fd = new FormData();
            fd.append('ms_action', 'save_banner');
            fd.append('my_post_key', postKey);
            fd.append('banner_type', 'upload');
            fd.append('banner_file', bannerFileEl.files[0]);
            fd.append('text_color', tColor);
            fd.append('link_color', lColor);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', rootUrl + '/usercp.php', true);
            xhr.onload = function() {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success) {
                        applyBannerToPage(resp.css, resp.text_color, resp.link_color);
                        closeModal('ms_banner_modal');
                    } else {
                        alert(resp.error || 'Error saving banner.');
                    }
                } catch(e) { alert('Unexpected response.'); }
            };
            xhr.send(fd);
            return;
        }

        // Non-file saves
        ajaxPost({ms_action:'save_banner', my_post_key:postKey, banner_type:type, banner_value:value, text_color:tColor, link_color:lColor}, function(resp) {
            if (resp.success) {
                applyBannerToPage(resp.css, resp.text_color, resp.link_color);
                closeModal('ms_banner_modal');
            } else {
                alert(resp.error || 'Error saving banner.');
            }
        });
    });
}

// â”€â”€ Remove Banner â”€â”€
var removeBtn = document.getElementById('ms_banner_remove');
if (removeBtn) {
    removeBtn.addEventListener('click', function() {
        if (!confirm('Remove your profile banner?')) return;
        ajaxPost({ms_action:'remove_banner', my_post_key:postKey}, function(resp) {
            if (resp.success) {
                applyBannerToPage('');
                closeModal('ms_banner_modal');
            } else {
                alert(resp.error || 'Error removing banner.');
            }
        });
    });
}

function applyBannerToPage(css, textColor, linkColor) {
    var banner = document.getElementById('profile_banner');
    if (banner) {
        banner.style.cssText = css;
        if (textColor) banner.style.setProperty('--banner-text-color', textColor);
        if (linkColor) banner.style.setProperty('--banner-link-color', linkColor);
    }
}


/* ═══════════════════════════════════════════════
   HELPERS
   ═══════════════════════════════════════════════ */
function ajaxPost(params, callback) {
    var body = Object.keys(params).map(function(k){ return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', rootUrl + '/usercp.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        try { callback(JSON.parse(xhr.responseText)); }
        catch(e) { callback({error: 'Unexpected response.'}); }
    };
    xhr.onerror = function() { callback({error: 'Network error.'}); };
    xhr.send(body);
}

function closeModal(id) {
    var modalEl = document.getElementById(id);
    if (modalEl && typeof bootstrap !== 'undefined') {
        var inst = bootstrap.Modal.getInstance(modalEl);
        if (inst) inst.hide();
    }
}

})();
