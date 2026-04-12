/* Selection to Quote - floating button for selecting text in posts */
(function(){
    'use strict';

    // Wait for DOM
    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init(){
        // Only run on pages that have a quick-reply or reply textarea
        if(!document.getElementById('message')) return;

        var btn = createButton();
        var hideTimer;

        function createButton(){
            var b = document.createElement('button');
            b.type = 'button';
            b.id = 'ms-select-quote-btn';
            b.innerHTML = '<i class="bi bi-quote me-1"></i>Quote';
            b.style.cssText = 'position:absolute;display:none;z-index:9999;pointer-events:auto;box-shadow:0 2px 8px rgba(0,0,0,.25);';
            b.className = 'btn btn-sm btn-dark';
            document.body.appendChild(b);
            return b;
        }

        function getSelectionText(){
            var sel = window.getSelection();
            return sel && sel.toString().trim() ? sel.toString().trim() : '';
        }

        function getSelectionRect(){
            var sel = window.getSelection();
            if(!sel || !sel.rangeCount) return null;
            var range = sel.getRangeAt(0);
            var rects = range.getClientRects();
            if(rects.length) return rects[rects.length - 1]; // last rect = end of selection
            return range.getBoundingClientRect();
        }

        // Walk up from selection anchor to find the post container (div[id^="post_"])
        function findPost(node){
            var el = node;
            while(el && el !== document.body){
                if(el.nodeType === 1){
                    // Check for the post wrapper: id="post_123"
                    if(el.id && /^post_\d+$/.test(el.id)) return el;
                }
                el = el.parentNode;
            }
            return null;
        }

        function extractPid(postEl){
            if(!postEl || !postEl.id) return null;
            var m = postEl.id.match(/^post_(\d+)$/);
            return m ? m[1] : null;
        }

        function extractUsername(postEl){
            if(!postEl) return '';
            // Prefer the profile link in the post header (most reliable)
            var profileLink = postEl.querySelector('a[href*="member.php?action=profile"]');
            if(profileLink) return profileLink.textContent.trim();
            // Fallback: parse aria-label "Post #N by Username"
            var h1 = postEl.querySelector('h1[aria-label]');
            if(h1){
                var label = h1.getAttribute('aria-label') || '';
                var byIdx = label.lastIndexOf(' by ');
                if(byIdx !== -1) return label.substring(byIdx + 4).trim();
            }
            return '';
        }

        function insertIntoEditor(text){
            var textarea = document.getElementById('message');
            if(!textarea) return;

            // If SCEditor is active, use its API
            if(typeof MyBBEditor !== 'undefined'){
                try {
                    MyBBEditor.insert(text);
                    // Scroll to quick reply
                    var qr = document.getElementById('quick_reply_form') || document.getElementById('quickreply_e');
                    if(qr) qr.scrollIntoView({behavior: 'smooth', block: 'center'});
                    return;
                } catch(e){}
            }

            // Plain textarea fallback
            var start = textarea.selectionStart || 0;
            var end   = textarea.selectionEnd   || 0;
            var val   = textarea.value;
            textarea.value = val.substring(0, start) + text + val.substring(end);
            textarea.selectionStart = textarea.selectionEnd = start + text.length;
            textarea.focus();
            textarea.scrollIntoView({behavior: 'smooth', block: 'center'});
        }

        function hideButton(){ btn.style.display = 'none'; btn._postEl = null; }

        function showButton(){
            var text = getSelectionText();
            if(!text){ hideButton(); return; }
            var sel = window.getSelection();
            if(!sel.anchorNode) { hideButton(); return; }

            var postEl = findPost(sel.anchorNode);
            if(!postEl){ hideButton(); return; }

            // Make sure selection is inside post_body (the message area, not buttons/meta)
            var bodyEl = postEl.querySelector('.post_body');
            if(bodyEl && !bodyEl.contains(sel.anchorNode)){ hideButton(); return; }

            var rect = getSelectionRect();
            if(!rect || (rect.width === 0 && rect.height === 0)){ hideButton(); return; }

            var top  = rect.bottom + window.scrollY + 6;
            var left = rect.left + window.scrollX + (rect.width / 2) - 40;
            // Keep in viewport
            if(left < 8) left = 8;

            btn.style.top  = top + 'px';
            btn.style.left = left + 'px';
            btn.style.display = 'inline-block';
            btn._postEl = postEl;
        }

        // Listen for mouse-up on post bodies (more reliable than selectionchange on all browsers)
        document.addEventListener('mouseup', function(e){
            if(e.target === btn || btn.contains(e.target)) return;
            // Delay slightly so selection finalizes
            clearTimeout(hideTimer);
            setTimeout(showButton, 80);
        });

        // Hide on click elsewhere
        document.addEventListener('mousedown', function(e){
            if(e.target === btn || btn.contains(e.target)) return;
            hideButton();
        });

        // Hide on scroll
        document.addEventListener('scroll', function(){ hideButton(); }, true);

        // Click handler
        btn.addEventListener('mousedown', function(e){
            e.preventDefault(); // prevent losing selection
        });
        btn.addEventListener('click', function(e){
            e.preventDefault();
            var selText = getSelectionText();
            if(!selText){ hideButton(); return; }

            var postEl  = btn._postEl;
            var pid     = extractPid(postEl);
            var author  = extractUsername(postEl);

            // Build MyBB-style quote BBCode: [quote='username' pid='123' dateline='...']
            var attr = '';
            if(author) attr += "'" + author + "'";
            if(pid)    attr += " pid='" + pid + "'";
            var bbcode = '[quote' + (attr ? '=' + attr : '') + ']\n' + selText + '\n[/quote]\n';

            insertIntoEditor(bbcode);
            hideButton();
            try{ window.getSelection().removeAllRanges(); }catch(ex){}
        });
    }
})();
