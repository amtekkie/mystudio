/**
 * FMZ Profile Extras — Client-Side JS
 *
 * Handles:
 *  - Banner modal interactions (upload preview, color/gradient pickers, save/remove)
 *  - Status compose + delete (AJAX)
 */

(function(){
'use strict';

var rootUrl = (typeof rootpath !== 'undefined') ? rootpath : '';
var postKey = (typeof my_post_key !== 'undefined') ? my_post_key : '';

/* ═══════════════════════════════════════════════
   BANNER MODAL
   ═══════════════════════════════════════════════ */

var bannerPreview  = document.getElementById('fmz_banner_preview');
var bannerTypeEl   = document.getElementById('fmz_banner_type');
var bannerValueEl  = document.getElementById('fmz_banner_value');
var bannerFileEl   = document.getElementById('fmz_banner_file');
var bannerUrlEl    = document.getElementById('fmz_banner_url');
var bannerColorEl  = document.getElementById('fmz_banner_color');
var bannerColorHex = document.getElementById('fmz_banner_color_hex');
var gradType       = document.getElementById('fmz_grad_type');
var gradAngle      = document.getElementById('fmz_grad_angle');
var gradColor1     = document.getElementById('fmz_grad_color1');
var gradColor2     = document.getElementById('fmz_grad_color2');
var gradApply      = document.getElementById('fmz_grad_apply');
var textColorEl    = document.getElementById('fmz_banner_text_color');
var textColorHex   = document.getElementById('fmz_banner_text_color_hex');
var linkColorEl    = document.getElementById('fmz_banner_link_color');
var linkColorHex   = document.getElementById('fmz_banner_link_color_hex');
var colorResetBtn  = document.getElementById('fmz_color_reset');
var previewText    = document.getElementById('fmz_preview_text');
var previewLink    = document.getElementById('fmz_preview_link');

function setPreview(css) {
    if (!bannerPreview) return;
    bannerPreview.style.cssText = css + 'height:120px;border-radius:.5rem;border:1px solid var(--tekbb-border);display:flex;align-items:flex-end;justify-content:space-between;padding:8px 12px;position:relative;background-size:cover;background-position:center;';
}

function setSelection(type, value) {
    if (bannerTypeEl)  bannerTypeEl.value  = type;
    if (bannerValueEl) bannerValueEl.value = value;
}

// ── File upload preview ──
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

// ── URL input preview ──
if (bannerUrlEl) {
    bannerUrlEl.addEventListener('input', function() {
        var url = this.value.trim();
        if (url) {
            setPreview("background-image:url('" + url + "');");
            setSelection('upload', url);
        }
    });
}

// ── Solid color presets ──
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

// ── Solid custom color picker sync ──
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

// ── Gradient presets ──
document.querySelectorAll('.gradient-preset-swatch').forEach(function(el) {
    el.addEventListener('click', function() {
        var grad = this.dataset.gradient;
        document.querySelectorAll('.gradient-preset-swatch').forEach(function(s){ s.classList.remove('active'); });
        this.classList.add('active');
        setPreview('background:' + grad + ';');
        setSelection('gradient', grad);
    });
});

// ── Custom gradient builder ──
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

// ── Text color picker sync ──
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

// ── Link color picker sync ──
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

// ── Reset text/link colors ──
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

// ── Gallery items (previous banners) ──
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

// ── Save Banner ──
var saveBtn = document.getElementById('fmz_banner_save');
if (saveBtn) {
    saveBtn.addEventListener('click', function() {
        var type  = bannerTypeEl  ? bannerTypeEl.value  : '';
        var value = bannerValueEl ? bannerValueEl.value : '';
        var tColor = textColorHex ? textColorHex.value.trim() : '';
        var lColor = linkColorHex ? linkColorHex.value.trim() : '';

        if (!type) {
            // No banner type selected — just update colors on existing banner
            ajaxPost({fmz_action:'update_banner_colors', my_post_key:postKey, text_color:tColor, link_color:lColor}, function(resp) {
                if (resp.success) {
                    applyBannerToPage(resp.css, resp.text_color, resp.link_color);
                    closeModal('fmz_banner_modal');
                } else {
                    alert(resp.error || 'Error updating colors.');
                }
            });
            return;
        }

        // Handle "activate previous" action
        if (type === 'activate') {
            ajaxPost({fmz_action:'activate_banner', my_post_key:postKey, bid:value, text_color:tColor, link_color:lColor}, function(resp) {
                if (resp.success) {
                    applyBannerToPage(resp.css, resp.text_color, resp.link_color);
                    closeModal('fmz_banner_modal');
                } else {
                    alert(resp.error || 'Error activating banner.');
                }
            });
            return;
        }

        // File upload requires FormData
        if (type === 'upload' && value === '__file__' && bannerFileEl && bannerFileEl.files[0]) {
            var fd = new FormData();
            fd.append('fmz_action', 'save_banner');
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
                        closeModal('fmz_banner_modal');
                    } else {
                        alert(resp.error || 'Error saving banner.');
                    }
                } catch(e) { alert('Unexpected response.'); }
            };
            xhr.send(fd);
            return;
        }

        // Non-file saves
        ajaxPost({fmz_action:'save_banner', my_post_key:postKey, banner_type:type, banner_value:value, text_color:tColor, link_color:lColor}, function(resp) {
            if (resp.success) {
                applyBannerToPage(resp.css, resp.text_color, resp.link_color);
                closeModal('fmz_banner_modal');
            } else {
                alert(resp.error || 'Error saving banner.');
            }
        });
    });
}

// ── Remove Banner ──
var removeBtn = document.getElementById('fmz_banner_remove');
if (removeBtn) {
    removeBtn.addEventListener('click', function() {
        if (!confirm('Remove your profile banner?')) return;
        ajaxPost({fmz_action:'remove_banner', my_post_key:postKey}, function(resp) {
            if (resp.success) {
                applyBannerToPage('');
                closeModal('fmz_banner_modal');
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
   STATUS UPDATES
   ═══════════════════════════════════════════════ */

// ── Post status ──
var statusSubmit = document.getElementById('fmz_status_submit');
if (statusSubmit) {
    statusSubmit.addEventListener('click', function() {
        var textEl    = document.getElementById('fmz_status_text');
        var privacyEl = document.getElementById('fmz_status_privacy');

        // Get content from WYSIWYG editor if active, else from textarea
        var message = '';
        if (textEl && textEl._fmzWysiwyg) {
            message = textEl._fmzWysiwyg.getBBCode().trim();
        } else if (textEl) {
            message = textEl.value.trim();
        }
        var privacy = privacyEl ? privacyEl.value : 'public';

        if (!message) {
            alert('Please enter a status message.');
            return;
        }

        statusSubmit.disabled = true;
        ajaxPost({fmz_action:'post_status', my_post_key:postKey, message:message, privacy:privacy}, function(resp) {
            statusSubmit.disabled = false;
            if (resp.success) {
                // Clear WYSIWYG or textarea
                if (textEl && textEl._fmzWysiwyg) {
                    textEl._fmzWysiwyg.setBBCode('');
                } else if (textEl) {
                    textEl.value = '';
                }
                var list = document.querySelector('.status-feed-list');
                if (list && resp.html) {
                    // Remove "no statuses" placeholder if present
                    var placeholder = list.querySelector('.text-center.text-muted');
                    if (placeholder) placeholder.remove();
                    list.insertAdjacentHTML('afterbegin', resp.html);
                    bindDeleteButtons();
                    bindComments();
                }
            } else {
                alert(resp.error || 'Error posting status.');
            }
        });
    });
}

// ── Delete status ──
function bindDeleteButtons() {
    document.querySelectorAll('.fmz-status-delete').forEach(function(el) {
        el.onclick = function(e) {
            e.preventDefault();
            var sid = this.dataset.sid;
            if (!confirm('Delete this status update?')) return;
            ajaxPost({fmz_action:'delete_status', my_post_key:postKey, sid:sid}, function(resp) {
                if (resp.success) {
                    var item = document.querySelector('.status-feed-item[data-sid="' + sid + '"]');
                    if (item) item.remove();
                } else {
                    alert(resp.error || 'Error deleting status.');
                }
            });
        };
    });
}
bindDeleteButtons();

// ── Edit status ──
function bindEditButtons() {
    document.querySelectorAll('.fmz-status-edit').forEach(function(el) {
        el.onclick = function(e) {
            e.preventDefault();
            var sid = this.dataset.sid;
            var rawMsg = this.dataset.message || '';
            var item = document.querySelector('.status-feed-item[data-sid="' + sid + '"]');
            if (!item) return;
            var msgEl = item.querySelector('.status-feed-message');
            if (!msgEl || msgEl.dataset.editing) return;
            msgEl.dataset.editing = '1';
            var origHtml = msgEl.innerHTML;

            msgEl.innerHTML = '<textarea class="form-control form-control-sm fmz-edit-textarea" rows="2" style="margin-bottom:6px"></textarea>'
                + '<div class="d-flex gap-2">'
                + '<button type="button" class="btn btn-sm btn-primary fmz-edit-save"><i class="bi bi-check-lg me-1"></i>Save</button>'
                + '<button type="button" class="btn btn-sm btn-outline-secondary fmz-edit-cancel">Cancel</button>'
                + '</div>';

            var ta = msgEl.querySelector('.fmz-edit-textarea');
            var tmp = document.createElement('textarea');
            tmp.innerHTML = rawMsg;
            ta.value = tmp.value;
            ta.focus();

            msgEl.querySelector('.fmz-edit-cancel').onclick = function() {
                msgEl.innerHTML = origHtml;
                delete msgEl.dataset.editing;
            };

            msgEl.querySelector('.fmz-edit-save').onclick = function() {
                var newMessage = ta.value.trim();
                if (!newMessage) { alert('Message cannot be empty.'); return; }
                this.disabled = true;
                ajaxPost({fmz_action:'edit_status', my_post_key:postKey, sid:sid, message:newMessage}, function(resp) {
                    if (resp.success) {
                        msgEl.innerHTML = resp.html;
                        delete msgEl.dataset.editing;
                        var editBtn = item.querySelector('.fmz-status-edit');
                        if (editBtn) editBtn.dataset.message = newMessage.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    } else {
                        alert(resp.error || 'Error saving edit.');
                        msgEl.innerHTML = origHtml;
                        delete msgEl.dataset.editing;
                    }
                });
            };
        };
    });
}
bindEditButtons();

/* ═══════════════════════════════════════════════
   COMMENTS
   ═══════════════════════════════════════════════ */

function bindComments() {
    // ── Toggle comments visibility ──
    document.querySelectorAll('.status-comments-toggle').forEach(function(el) {
        el.onclick = function(e) {
            e.preventDefault();
            var sid = this.dataset.sid;
            var section = document.querySelector('.status-comments-section[data-sid="' + sid + '"]');
            if (!section) return;
            var list = section.querySelector('.status-comments-list');
            if (!list) return;
            if (list.style.display === 'none') {
                list.style.display = 'block';
            } else {
                list.style.display = 'none';
            }
        };
    });

    // ── Post comment (Enter key) ──
    document.querySelectorAll('.status-comment-input').forEach(function(input) {
        input.onkeydown = function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var message = this.value.trim();
            var sid = this.dataset.sid;
            if (!message) return;

            var inputEl = this;
            inputEl.disabled = true;
            ajaxPost({fmz_action:'post_comment', my_post_key:postKey, sid:sid, message:message}, function(resp) {
                inputEl.disabled = false;
                if (resp.success) {
                    inputEl.value = '';
                    // Insert comment before the compose form
                    var section = document.querySelector('.status-comments-section[data-sid="' + sid + '"]');
                    if (section) {
                        var compose = section.querySelector('.status-comment-compose');
                        if (compose) {
                            compose.insertAdjacentHTML('beforebegin', resp.html);
                        } else {
                            var list = section.querySelector('.status-comments-list');
                            if (list) list.insertAdjacentHTML('beforeend', resp.html);
                        }
                        // Update count
                        var countEl = section.querySelector('.comment-count');
                        if (countEl) {
                            var cur = parseInt(countEl.textContent) || 0;
                            countEl.textContent = cur + 1;
                        }
                        // Make sure list is visible
                        var listEl = section.querySelector('.status-comments-list');
                        if (listEl) listEl.style.display = 'block';
                    }
                    bindCommentDelete();
                } else {
                    alert(resp.error || 'Error posting comment.');
                }
            });
        };
    });

    bindCommentDelete();
}

function bindCommentDelete() {
    document.querySelectorAll('.fmz-comment-delete').forEach(function(el) {
        el.onclick = function(e) {
            e.preventDefault();
            var cid = this.dataset.cid;
            if (!confirm('Delete this comment?')) return;
            ajaxPost({fmz_action:'delete_comment', my_post_key:postKey, cid:cid}, function(resp) {
                if (resp.success) {
                    var comment = document.querySelector('.status-comment[data-cid="' + cid + '"]');
                    if (comment) {
                        // Update count
                        var section = comment.closest('.status-comments-section');
                        if (section) {
                            var countEl = section.querySelector('.comment-count');
                            if (countEl) {
                                var cur = parseInt(countEl.textContent) || 0;
                                if (cur > 0) countEl.textContent = cur - 1;
                            }
                        }
                        comment.remove();
                    }
                } else {
                    alert(resp.error || 'Error deleting comment.');
                }
            });
        };
    });
}

bindComments();

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
