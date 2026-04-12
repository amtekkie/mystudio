/**
 * MyStudio Quick Search — Header autocomplete
 */
(function() {
    'use strict';

    var BBURL, hdr, btn, close, input, ac, timer, xhr, idx = -1, groups = [];

    document.addEventListener('DOMContentLoaded', function() {
        btn   = document.getElementById('msSearchBtn');
        hdr   = document.getElementById('msHeader');
        close = document.getElementById('msSearchClose');
        input = document.getElementById('msSearchInput');
        ac    = document.getElementById('msAcDropdown');

        if (!btn || !hdr) return;

        BBURL = btn.getAttribute('data-bburl') || '';

        btn.addEventListener('click', openSearch);
        close.addEventListener('click', closeSearch);

        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && !isTyping(e)) { e.preventDefault(); openSearch(); }
            if (e.key === 'Escape' && hdr.classList.contains('search-open')) closeSearch();
        });

        input.addEventListener('input', onInput);
        input.addEventListener('keydown', onKeydown);

        document.addEventListener('click', function(e) {
            if (hdr.classList.contains('search-open') && !hdr.contains(e.target)) closeSearch();
        });
    });

    function isTyping(e) {
        var t = e.target.tagName;
        return t === 'INPUT' || t === 'TEXTAREA' || e.target.isContentEditable;
    }

    function openSearch() {
        hdr.classList.add('search-open');
        input.focus();
    }

    function closeSearch() {
        hdr.classList.remove('search-open');
        input.value = '';
        hideAc();
    }

    function hideAc() {
        ac.style.display = 'none';
        ac.innerHTML = '';
        idx = -1;
        groups = [];
    }

    function onInput() {
        var q = input.value.trim();
        if (q.length < 3) { hideAc(); return; }
        clearTimeout(timer);
        timer = setTimeout(function() { doSearch(q); }, 280);
    }

    function doSearch(q) {
        if (xhr) xhr.abort();
        xhr = new XMLHttpRequest();
        xhr.open('GET', BBURL + '/xmlhttp.php?action=ms_quicksearch&q=' + encodeURIComponent(q));
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            if (xhr.status !== 200) return;
            try { var data = JSON.parse(xhr.responseText); } catch (e) { return; }
            renderAc(data, q);
        };
        xhr.send();
    }

    function escapeRe(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function hl(text, q) {
        var re = new RegExp('(' + escapeRe(q) + ')', 'gi');
        return text.replace(re, '<mark>$1</mark>');
    }

    function renderAc(data, q) {
        var html = '';
        groups = [];
        var sections = [
            { key: 'threads',       icon: 'bi-chat-left-text', label: 'Threads' },
            { key: 'posts',         icon: 'bi-reply',          label: 'Posts' },
            { key: 'users',         icon: 'bi-people',         label: 'Members' },
            { key: 'announcements', icon: 'bi-megaphone',      label: 'Announcements' }
        ];

        sections.forEach(function(sec) {
            var items = data[sec.key];
            if (!items || !items.length) return;

            html += '<div class="ms-ac-group">';
            html += '<div class="ms-ac-group-label"><i class="bi ' + sec.icon + '"></i> ' + sec.label + '</div>';

            items.forEach(function(item) {
                var i = groups.length;
                html += '<a href="' + item.url + '" class="ms-ac-item" data-idx="' + i + '">';
                if (item.avatar) {
                    html += '<img src="' + item.avatar + '" class="ms-ac-avatar" alt="" />';
                } else {
                    html += '<span class="ms-ac-dot"></span>';
                }
                html += '<span class="ms-ac-text">';
                html += '<span class="ms-ac-title">' + hl(item.title, q) + '</span>';
                if (item.sub) html += '<span class="ms-ac-sub">' + item.sub + '</span>';
                html += '</span></a>';
                groups.push(item);
            });

            html += '</div>';
        });

        if (!html) {
            html = '<div class="ms-ac-empty"><i class="bi bi-inbox"></i> No results for \u201c' + q.replace(/</g, '&lt;') + '\u201d</div>';
        } else {
            html += '<a href="' + BBURL + '/search.php?action=do_search&keywords=' + encodeURIComponent(q) + '&postthread=1" class="ms-ac-viewall">';
            html += '<i class="bi bi-arrow-right-circle"></i> View all results</a>';
        }

        ac.innerHTML = html;
        ac.style.display = 'block';
        idx = -1;
    }

    function onKeydown(e) {
        if (ac.style.display !== 'block') return;
        var items = ac.querySelectorAll('.ms-ac-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            idx = Math.min(idx + 1, items.length - 1);
            highlight(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            idx = Math.max(idx - 1, -1);
            highlight(items);
        } else if (e.key === 'Enter' && idx >= 0) {
            e.preventDefault();
            items[idx].click();
        }
    }

    function highlight(items) {
        items.forEach(function(el, i) {
            el.classList.toggle('is-active', i === idx);
            if (i === idx) el.scrollIntoView({ block: 'nearest' });
        });
    }
})();
