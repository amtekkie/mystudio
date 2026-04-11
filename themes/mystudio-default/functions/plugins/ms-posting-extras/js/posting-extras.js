/**
 * MyStudio Posting Extras — Client-side JavaScript
 * Handles reply, delete, and feed interactions on portal.php
 */
(function() {
    'use strict';

    // Exit if not on the posting extras feed page
    var pageConfig = typeof PEX_CONFIG !== 'undefined' ? PEX_CONFIG : {};
    var defaultBburl = pageConfig.bburl || '';
    var defaultPostKey = pageConfig.postKey || '';

    document.addEventListener('DOMContentLoaded', function() {
        initLikes();
        if (typeof PEX_CONFIG !== 'undefined') {
            initFeed();
        }
    });

    function initLikes() {
        document.addEventListener('click', function(e) {
            var likeToggle = e.target.closest('.pex-like-toggle');
            var likeCount = e.target.closest('.pex-like-count');

            if (likeToggle) {
                e.preventDefault();
                handleLikeToggle(likeToggle);
                return;
            }

            if (likeCount) {
                e.preventDefault();
                handleLikeCount(likeCount);
            }
        });
    }

    function handleLikeToggle(likeToggle) {
        var context = getLikeContext(likeToggle);
        var targetType = likeToggle.getAttribute('data-target-type');
        var targetId = likeToggle.getAttribute('data-target-id');

        if (!context.postKey) {
            alert('Unable to like this post right now.');
            return;
        }

        var fd = new FormData();
        fd.append('ms_action', 'toggle_like');
        fd.append('my_post_key', context.postKey);
        fd.append('target_type', targetType);
        fd.append('target_id', targetId);

        ajaxPost(context.bburl + '/portal.php', fd, function(data) {
            if (data.error) {
                alert(data.error);
                return;
            }

            var wrap = likeToggle.closest('.pex-like-wrap');
            var countNum = wrap ? wrap.querySelector('.pex-like-count-num') : null;
            var countLink = wrap ? wrap.querySelector('.pex-like-count') : null;
            var icon = likeToggle.querySelector('i');
            if (icon) {
                icon.className = 'bi ' + (data.liked ? 'bi-heart-fill' : 'bi-heart');
            }
            if (countNum && typeof data.count !== 'undefined') {
                countNum.textContent = data.count;
            }
            if (countLink) {
                countLink.classList.toggle('is-hidden', !data.count);
            }
            likeToggle.classList.toggle('is-liked', !!data.liked);
        });
    }

    function handleLikeCount(likeCount) {
        var targetType = likeCount.getAttribute('data-target-type');
        var targetId = likeCount.getAttribute('data-target-id');
        var wrap = likeCount.closest('.pex-like-wrap');
        var section = wrap ? wrap.querySelector('.pex-likes-section[data-target-type="' + targetType + '"][data-target-id="' + targetId + '"]') : null;
        var list = section ? section.querySelector('.pex-likes-list') : null;
        if (!section || !list) return;

        var context = getLikeContext(likeCount);
        var isHidden = section.style.display === 'none' || !section.style.display;
        if (isHidden) {
            section.style.display = '';
            if (!list.getAttribute('data-loaded')) {
                list.innerHTML = '<div class="pex-loading"></div>';
                ajaxGet(context.bburl + '/portal.php?ms_action=load_likes&target_type=' + encodeURIComponent(targetType) + '&target_id=' + encodeURIComponent(targetId), function(data) {
                    if (data.error) {
                        list.innerHTML = '<div class="text-muted small p-2">' + data.error + '</div>';
                        return;
                    }
                    list.innerHTML = data.html || '<div class="text-muted small p-2">No likes yet.</div>';
                    list.setAttribute('data-loaded', '1');
                });
            }
        } else {
            section.style.display = 'none';
        }
    }

    /* ═══════════════════════════════════════════════════════════
       FEED — Toggle replies, delete, etc.
       ═══════════════════════════════════════════════════════════ */

    function initFeed() {
        document.querySelectorAll('.pex-feed-item').forEach(function(item) {
            bindFeedItem(item);
        });
    }

    function bindFeedItem(item) {
        // Replies toggle — show/hide entire replies section
        var toggle = item.querySelector('.pex-replies-toggle');
        if (toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                var tid = toggle.getAttribute('data-tid');
                var section = item.querySelector('.pex-replies-section[data-tid="' + tid + '"]');
                if (!section) return;
                var list = section.querySelector('.pex-replies-list');

                if (section.style.display === 'none') {
                    section.style.display = '';
                    // Load replies if not already loaded
                    if (!list.getAttribute('data-loaded')) {
                        list.innerHTML = '<div class="pex-loading"></div>';
                        ajaxGet(bburl + '/portal.php?ms_action=load_replies&tid=' + tid, function(data) {
                            if (data.error) {
                                list.innerHTML = '<div class="text-muted small p-2">' + data.error + '</div>';
                                return;
                            }
                            list.innerHTML = data.html || '<div class="text-muted small p-2">No replies yet.</div>';
                            list.setAttribute('data-loaded', '1');
                            bindReplies(list, tid);
                        });
                    }
                } else {
                    section.style.display = 'none';
                }
            });
        }

        // Delete thread
        var deleteBtn = item.querySelector('.pex-delete-thread');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!confirm('Delete this post?')) return;
                var tid = deleteBtn.getAttribute('data-tid');

                var fd = new FormData();
                fd.append('ms_action', 'delete_thread');
                fd.append('my_post_key', postKey);
                fd.append('tid', tid);

                ajaxPost(bburl + '/portal.php', fd, function(data) {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    item.style.transition = 'opacity 0.3s';
                    item.style.opacity = '0';
                    setTimeout(function() { item.remove(); }, 300);
                });
            });
        }
    }

    /* ═══════════════════════════════════════════════════════════
       REPLIES — Compose, submit, delete
       ═══════════════════════════════════════════════════════════ */

    function bindReplies(list, tid) {
        // Reply compose
        var compose = list.querySelector('.pex-reply-compose[data-tid="' + tid + '"]');
        if (compose) {
            var textarea  = compose.querySelector('.pex-reply-input');
            var submitBtn = compose.querySelector('.pex-reply-submit-btn');
            var imageInput = compose.querySelector('.pex-reply-image-input');

            if (textarea && submitBtn) {
                textarea.addEventListener('input', function() {
                    submitBtn.disabled = !textarea.value.trim();
                    textarea.style.height = 'auto';
                    textarea.style.height = Math.min(textarea.scrollHeight, 200) + 'px';
                });

                submitBtn.addEventListener('click', function() {
                    if (submitBtn.disabled) return;
                    var message = textarea.value.trim();
                    if (!message && !(imageInput && imageInput.files && imageInput.files[0])) return;

                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Sending...';

                    var fd = new FormData();
                    fd.append('ms_action', 'create_reply');
                    fd.append('my_post_key', postKey);
                    fd.append('tid', tid);
                    fd.append('message', message);

                    if (imageInput && imageInput.files && imageInput.files[0]) {
                        fd.append('reply_image', imageInput.files[0]);
                    }

                    ajaxPost(bburl + '/portal.php', fd, function(data) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Reply';

                        if (data.error) {
                            alert(data.error);
                            return;
                        }

                        if (data.html) {
                            compose.insertAdjacentHTML('beforebegin', data.html);
                            // Bind delete on new reply
                            var newReply = compose.previousElementSibling;
                            if (newReply) bindReplyDelete(newReply);
                        }

                        // Update reply count
                        var toggle = document.querySelector('.pex-replies-toggle[data-tid="' + tid + '"]');
                        if (toggle) {
                            var countSpan = toggle.querySelector('.pex-reply-count');
                            if (countSpan) {
                                var c = parseInt(countSpan.textContent, 10) + 1;
                                countSpan.textContent = c;
                            }
                        }

                        textarea.value = '';
                        textarea.style.height = '';
                        if (imageInput) imageInput.value = '';
                    });
                });

                // MyCode toolbar for reply
                compose.querySelectorAll('.pex-reply-bb').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var tag = btn.getAttribute('data-bb');
                        if (tag) insertBBCode(textarea, tag);
                    });
                });
            }
        }

        // Bind delete on existing replies
        list.querySelectorAll('.pex-reply').forEach(function(reply) {
            bindReplyDelete(reply);
        });
    }

    function bindReplyDelete(reply) {
        var deleteBtn = reply.querySelector('.pex-delete-reply');
        if (!deleteBtn) return;

        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!confirm('Delete this reply?')) return;
            var pid = deleteBtn.getAttribute('data-pid');

            var fd = new FormData();
            fd.append('ms_action', 'delete_reply');
            fd.append('my_post_key', postKey);
            fd.append('pid', pid);

            ajaxPost(bburl + '/portal.php', fd, function(data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                reply.style.transition = 'opacity 0.3s';
                reply.style.opacity = '0';
                setTimeout(function() { reply.remove(); }, 300);
            });
        });
    }

    function getLikeContext(node) {
        var wrap = node.closest('.pex-like-wrap');
        var bburl = (wrap && wrap.getAttribute('data-bburl')) || defaultBburl || window.location.origin || '';
        var postKey = (wrap && wrap.getAttribute('data-post-key')) || defaultPostKey || '';

        return {
            bburl: bburl,
            postKey: postKey
        };
    }

    /* ═══════════════════════════════════════════════════════════
       HELPERS
       ═══════════════════════════════════════════════════════════ */

    function insertBBCode(textarea, tag) {
        var start = textarea.selectionStart;
        var end   = textarea.selectionEnd;
        var text  = textarea.value;
        var sel   = text.substring(start, end);

        var openTag, closeTag;
        if (tag === 'url') {
            var url = sel || prompt('Enter URL:');
            if (!url) return;
            openTag = '[url]';
            closeTag = '[/url]';
            if (sel) {
                textarea.value = text.substring(0, start) + '[url=' + url + ']' + sel + '[/url]' + text.substring(end);
            } else {
                textarea.value = text.substring(0, start) + '[url]' + url + '[/url]' + text.substring(end);
            }
        } else {
            openTag = '[' + tag + ']';
            closeTag = '[/' + tag + ']';
            textarea.value = text.substring(0, start) + openTag + sel + closeTag + text.substring(end);
        }

        textarea.focus();
        var cursorPos = start + (tag === 'url' ? textarea.value.length - text.length : openTag.length + sel.length + closeTag.length);
        textarea.setSelectionRange(cursorPos, cursorPos);
        textarea.dispatchEvent(new Event('input'));
    }

    function ajaxPost(url, formData, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.onload = function() {
            try {
                var data = JSON.parse(xhr.responseText);
                callback(data);
            } catch (e) {
                callback({ error: 'Unexpected response from server.' });
            }
        };
        xhr.onerror = function() {
            callback({ error: 'Network error.' });
        };
        xhr.send(formData);
    }

    function ajaxGet(url, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.onload = function() {
            try {
                var data = JSON.parse(xhr.responseText);
                callback(data);
            } catch (e) {
                callback({ error: 'Unexpected response from server.' });
            }
        };
        xhr.onerror = function() {
            callback({ error: 'Network error.' });
        };
        xhr.send();
    }

})();
