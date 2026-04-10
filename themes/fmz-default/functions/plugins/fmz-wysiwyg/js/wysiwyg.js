/**
 * FMZ WYSIWYG Editor v2.1
 *
 * A full-featured rich-text editor for MyBB. Replaces SCEditor with a modern
 * contentEditable-based editor supporting BBCode conversion, color themes,
 * emoji/GIF search, tables, video embeds, formula insertion, undo/redo,
 * draft saving, font customization, code blocks, and more.
 */
(function () {
    'use strict';

    /* ═════════════════════════════════════════════════════════════
       Configuration & Theme Presets
       ═════════════════════════════════════════════════════════════ */

    var THEME_PRESETS = {
        'teal':     { accent: '#0d9488', toolbarBg: '#f0fdfa', toolbarText: '#134e4a',  activeBg: '#ccfbf1', activeBorder: '#0d9488', darkToolbarBg: '#1a2e2b', darkToolbarText: '#5eead4' },
        'ocean':    { accent: '#0369a1', toolbarBg: '#f0f9ff', toolbarText: '#0c4a6e',  activeBg: '#e0f2fe', activeBorder: '#0369a1', darkToolbarBg: '#1a2533', darkToolbarText: '#7dd3fc' },
        'indigo':   { accent: '#4338ca', toolbarBg: '#eef2ff', toolbarText: '#312e81',  activeBg: '#e0e7ff', activeBorder: '#4338ca', darkToolbarBg: '#1e1b33', darkToolbarText: '#a5b4fc' },
        'purple':   { accent: '#7e22ce', toolbarBg: '#faf5ff', toolbarText: '#581c87',  activeBg: '#f3e8ff', activeBorder: '#7e22ce', darkToolbarBg: '#25202d', darkToolbarText: '#d8b4fe' },
        'rose':     { accent: '#be123c', toolbarBg: '#fff1f2', toolbarText: '#881337',  activeBg: '#ffe4e6', activeBorder: '#be123c', darkToolbarBg: '#2d2025', darkToolbarText: '#fda4af' },
        'amber':    { accent: '#b45309', toolbarBg: '#fffbeb', toolbarText: '#78350f',  activeBg: '#fef3c7', activeBorder: '#b45309', darkToolbarBg: '#2d2820', darkToolbarText: '#fcd34d' },
        'emerald':  { accent: '#059669', toolbarBg: '#ecfdf5', toolbarText: '#064e3b',  activeBg: '#d1fae5', activeBorder: '#059669', darkToolbarBg: '#1a2d22', darkToolbarText: '#6ee7b7' },
        'crimson':  { accent: '#dc2626', toolbarBg: '#fef2f2', toolbarText: '#7f1d1d',  activeBg: '#fee2e2', activeBorder: '#dc2626', darkToolbarBg: '#2d2020', darkToolbarText: '#fca5a5' },
        'sapphire': { accent: '#1d4ed8', toolbarBg: '#eff6ff', toolbarText: '#1e3a5f',  activeBg: '#dbeafe', activeBorder: '#1d4ed8', darkToolbarBg: '#1e2233', darkToolbarText: '#93bbfd' },
        'coral':    { accent: '#c2410c', toolbarBg: '#fff7ed', toolbarText: '#7c2d12',  activeBg: '#ffedd5', activeBorder: '#c2410c', darkToolbarBg: '#2d2520', darkToolbarText: '#fdba74' },
        'slate':    { accent: '#475569', toolbarBg: '#f8fafc', toolbarText: '#334155',  activeBg: '#f1f5f9', activeBorder: '#475569', darkToolbarBg: '#2a2d30', darkToolbarText: '#cbd5e1' },
        'pink':     { accent: '#db2777', toolbarBg: '#fdf2f8', toolbarText: '#831843',  activeBg: '#fce7f3', activeBorder: '#db2777', darkToolbarBg: '#2d2028', darkToolbarText: '#f9a8d4' },
        // Legacy aliases
        'default':  { accent: '#0d9488', toolbarBg: '#f0fdfa', toolbarText: '#134e4a',  activeBg: '#ccfbf1', activeBorder: '#0d9488', darkToolbarBg: '#1a2e2b', darkToolbarText: '#5eead4' },
        'forest':   { accent: '#059669', toolbarBg: '#ecfdf5', toolbarText: '#064e3b',  activeBg: '#d1fae5', activeBorder: '#059669', darkToolbarBg: '#1a2d22', darkToolbarText: '#6ee7b7' },
        'sunset':   { accent: '#c2410c', toolbarBg: '#fff7ed', toolbarText: '#7c2d12',  activeBg: '#ffedd5', activeBorder: '#c2410c', darkToolbarBg: '#2d2520', darkToolbarText: '#fdba74' },
        'lavender': { accent: '#7e22ce', toolbarBg: '#faf5ff', toolbarText: '#581c87',  activeBg: '#f3e8ff', activeBorder: '#7e22ce', darkToolbarBg: '#25202d', darkToolbarText: '#d8b4fe' },
        'midnight': { accent: '#4338ca', toolbarBg: '#eef2ff', toolbarText: '#312e81',  activeBg: '#e0e7ff', activeBorder: '#4338ca', darkToolbarBg: '#1e1b33', darkToolbarText: '#a5b4fc' }
    };

    /* ═════════════════════════════════════════════════════════════
       Full Toolbar Definition
       ═════════════════════════════════════════════════════════════ */

    var ALL_BUTTONS = {
        bold:          { icon: 'bi-type-bold',          title: 'Bold (Ctrl+B)',         cmd: 'bold' },
        italic:        { icon: 'bi-type-italic',        title: 'Italic (Ctrl+I)',       cmd: 'italic' },
        underline:     { icon: 'bi-type-underline',     title: 'Underline (Ctrl+U)',    cmd: 'underline' },
        strikethrough: { icon: 'bi-type-strikethrough', title: 'Strikethrough',         cmd: 'strikeThrough' },
        fontFamily:    { icon: 'bi-fonts',              title: 'Font Family',           cmd: 'fmz-fontfamily',   dropdown: true },
        fontSize:      { icon: 'bi-text-paragraph',     title: 'Font Size',             cmd: 'fmz-fontsize',     dropdown: true },
        fontColor:     { icon: 'bi-palette',            title: 'Text Color',            cmd: 'fmz-fontcolor',    dropdown: true, split: true },
        highlight:     { icon: 'bi-paint-bucket',       title: 'Highlight',             cmd: 'fmz-highlight',    dropdown: true, split: true },
        alignLeft:     { icon: 'bi-text-left',          title: 'Align Left',            cmd: 'justifyLeft' },
        alignCenter:   { icon: 'bi-text-center',        title: 'Align Center',          cmd: 'justifyCenter' },
        alignRight:    { icon: 'bi-text-right',         title: 'Align Right',           cmd: 'justifyRight' },
        alignJustify:  { icon: 'bi-justify',            title: 'Justify',               cmd: 'justifyFull' },
        bulletList:    { icon: 'bi-list-ul',            title: 'Bullet List',           cmd: 'insertUnorderedList' },
        numberedList:  { icon: 'bi-list-ol',            title: 'Numbered List',         cmd: 'insertOrderedList' },
        indent:        { icon: 'bi-text-indent-left',   title: 'Increase Indent',       cmd: 'indent' },
        outdent:       { icon: 'bi-text-indent-right',  title: 'Decrease Indent',       cmd: 'outdent' },
        link:          { icon: 'bi-link-45deg',         title: 'Insert Link',           cmd: 'fmz-link' },
        image:         { icon: 'bi-image',              title: 'Insert Image',          cmd: 'fmz-image' },
        video:         { icon: 'bi-camera-video',       title: 'Embed Video',           cmd: 'fmz-video' },
        table:         { icon: 'bi-table',              title: 'Insert Table',          cmd: 'fmz-table',        dropdown: true },
        emoji:         { icon: 'bi-emoji-smile',        title: 'Insert Emoji',          cmd: 'fmz-emoji',        dropdown: true },
        gif:           { icon: 'bi-filetype-gif',       title: 'Search GIF',            cmd: 'fmz-gif',          dropdown: true },
        quote:         { icon: 'bi-chat-quote',         title: 'Quote',                 cmd: 'fmz-quote' },
        code:          { icon: 'bi-code-slash',         title: 'Code Block',            cmd: 'fmz-code',         dropdown: true },
        formula:       { icon: 'bi-calculator',         title: 'Insert Formula',        cmd: 'fmz-formula' },
        hr:            { icon: 'bi-dash-lg',            title: 'Horizontal Rule',       cmd: 'insertHorizontalRule' },
        removeFormat:  { icon: 'bi-eraser',             title: 'Remove Formatting',     cmd: 'removeFormat' },
        undo:          { icon: 'bi-arrow-counterclockwise', title: 'Undo (Ctrl+Z)',     cmd: 'undo' },
        redo:          { icon: 'bi-arrow-clockwise',    title: 'Redo (Ctrl+Y)',         cmd: 'redo' },
        saveDraft:     { icon: 'bi-floppy',             title: 'Save Draft',            cmd: 'fmz-savedraft' },
        source:        { icon: 'bi-code-square',        title: 'Toggle Source',         cmd: 'fmz-source' }
    };

    var FULL_TOOLBAR = 'bold,italic,underline,strikethrough,|,fontFamily,fontSize,fontColor,highlight,|,alignLeft,alignCenter,alignRight,alignJustify,|,bulletList,numberedList,indent,outdent,|,link,image,video,table,|,emoji,gif,quote,code,formula,hr,|,removeFormat,undo,redo,saveDraft,source';
    var MINIMAL_TOOLBAR = 'bold,italic,underline,|,link,image,quote,code,|,undo,redo,source';

    /* ═════════════════════════════════════════════════════════════
       Emoji Data (common set)
       ═════════════════════════════════════════════════════════════ */

    var EMOJI_CATEGORIES = {
        'Smileys':  ['😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😋','😎','😍','🥰','😘','😗','😙','😚','🙂','🤗','🤩','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','🤐','😯','😪','😫','😴','😌','😛','😜','😝','🤤','😒','😓','😔','😕','🙃','🤑','😲','🙁','😖','😞','😟','😤','😢','😭','😦','😧','😨','😩','🤯','😬','😰','😱','🥵','🥶','😳','🤪','😵','🥴','😡','😠','🤬','😷','🤒','🤕','🤢','🤮','🥱','😇','🥺','🤡','🤠','🤥','🤫','🤭','🧐','🤓'],
        'Gestures': ['👍','👎','👌','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','👋','🤚','🖐️','✋','🖖','👏','🙌','👐','🤲','🙏','✍️','💪','🦾','🖤'],
        'Objects':  ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','💔','❣️','💕','💞','💓','💗','💖','💘','💝','⭐','🌟','✨','⚡','🔥','💥','🎉','🎊','🏆','🎯','💡','📌','📎','🔗','💻','📱','⌨️','🖨️','📷','🎬','🎵','🎶','🔔','📢'],
        'Symbols':  ['✅','❌','❓','❗','💯','🔴','🟠','🟡','🟢','🔵','🟣','⚫','⚪','🟤','⬛','⬜','◼️','◻️','▪️','▫️','🔶','🔷','🔸','🔹','➕','➖','➗','✖️','♻️','〰️','©️','®️','™️']
    };

    /* ═════════════════════════════════════════════════════════════
       Color Palette
       ═════════════════════════════════════════════════════════════ */

    var COLOR_PALETTE = [
        '#000000','#434343','#666666','#999999','#b7b7b7','#cccccc','#d9d9d9','#efefef','#f3f3f3','#ffffff',
        '#980000','#ff0000','#ff9900','#ffff00','#00ff00','#00ffff','#4a86e8','#0000ff','#9900ff','#ff00ff',
        '#e6b8af','#f4cccc','#fce5cd','#fff2cc','#d9ead3','#d0e0e3','#c9daf8','#cfe2f3','#d9d2e9','#ead1dc',
        '#dd7e6b','#ea9999','#f9cb9c','#ffe599','#b6d7a8','#a2c4c9','#a4c2f4','#9fc5e8','#b4a7d6','#d5a6bd',
        '#cc4125','#e06666','#f6b26b','#ffd966','#93c47d','#76a5af','#6d9eeb','#6fa8dc','#8e7cc3','#c27ba0',
        '#a61c00','#cc0000','#e69138','#f1c232','#6aa84f','#45818e','#3c78d8','#3d85c6','#674ea7','#a64d79',
        '#85200c','#990000','#b45f06','#bf9000','#38761d','#134f5c','#1155cc','#0b5394','#351c75','#741b47',
        '#5b0f00','#660000','#783f04','#7f6000','#274e13','#0c343d','#1c4587','#073763','#20124d','#4c1130'
    ];

    /* ═════════════════════════════════════════════════════════════
       BBCode ↔ HTML Conversion  (DOM-based for accuracy)
       ═════════════════════════════════════════════════════════════ */

    /**
     * Convert an rgb(r,g,b) or rgba string to #hex. Pass-through hex/named.
     */
    function rgbToHex(c) {
        if (!c) return '';
        c = c.trim();
        var m = c.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
        if (m) {
            return '#' + ((1 << 24) + (+m[1] << 16) + (+m[2] << 8) + +m[3]).toString(16).slice(1);
        }
        return c;
    }

    /**
     * Map px size to MyBB-compatible integer size (1-50, rendered as pt).
     * MyBB accepts: named sizes OR integer 1-50 via [size=N].
     * We convert px → approximate point size.
     */
    function pxToMybbSize(px) {
        var num = parseInt(px, 10);
        if (isNaN(num)) {
            // named size? pass through
            var named = {'xx-small':1,'x-small':1,'small':2,'medium':3,'large':4,'x-large':5,'xx-large':6};
            return named[px.toLowerCase()] || px;
        }
        // rough px→pt (1pt ≈ 1.333px)
        var pt = Math.round(num / 1.333);
        if (pt < 1) pt = 1;
        if (pt > 50) pt = 50;
        return pt;
    }

    /**
     * BBCode → HTML for editor display
     */
    function bbToHtml(bbcode) {
        if (!bbcode) return '';
        var html = bbcode;

        // Order matters: earliest replacements first
        // Quotes — iterative replacement to handle nested [quote] tags properly
        var quoteLimit = 10;
        while (quoteLimit-- > 0) {
            var before = html;
            html = html.replace(/\[quote='?(.*?)'?\s+pid=['"]?\d+['"]?[^\]]*\]([\s\S]*?)\[\/quote\]/gi,
                '<blockquote class="fmz-quote" data-author="$1"><cite>$1 wrote:</cite>$2</blockquote>');
            html = html.replace(/\[quote='?(.*?)'?\]([\s\S]*?)\[\/quote\]/gi,
                '<blockquote class="fmz-quote" data-author="$1"><cite>$1 wrote:</cite>$2</blockquote>');
            html = html.replace(/\[quote\]([\s\S]*?)\[\/quote\]/gi,
                '<blockquote class="fmz-quote">$1</blockquote>');
            if (html === before) break;
        }

        // Code blocks (with language, without)
        html = html.replace(/\[code=([a-zA-Z0-9+#]+)\]([\s\S]*?)\[\/code\]/gi, function(_, lang, code) {
            return '<pre class="fmz-code-block" data-lang="' + lang.toLowerCase() + '"><code class="language-' + lang.toLowerCase() + '">' + esc(code) + '</code></pre>';
        });
        html = html.replace(/\[code\]([\s\S]*?)\[\/code\]/gi, function(_, code) {
            return '<pre class="fmz-code-block"><code>' + esc(code) + '</code></pre>';
        });

        // Basic formatting
        html = html.replace(/\[b\]([\s\S]*?)\[\/b\]/gi, '<strong>$1</strong>');
        html = html.replace(/\[i\]([\s\S]*?)\[\/i\]/gi, '<em>$1</em>');
        html = html.replace(/\[u\]([\s\S]*?)\[\/u\]/gi, '<u>$1</u>');
        html = html.replace(/\[s\]([\s\S]*?)\[\/s\]/gi, '<s>$1</s>');

        // Links
        html = html.replace(/\[url=(.*?)\]([\s\S]*?)\[\/url\]/gi, '<a href="$1" target="_blank" rel="noopener">$2</a>');
        html = html.replace(/\[url\]([\s\S]*?)\[\/url\]/gi, '<a href="$1" target="_blank" rel="noopener">$1</a>');

        // Images (with dimensions and without)
        html = html.replace(/\[img=(\d+)x(\d+)\]([\s\S]*?)\[\/img\]/gi, '<img src="$3" alt="" style="width:$1px;height:$2px;max-width:100%" />');
        html = html.replace(/\[img\]([\s\S]*?)\[\/img\]/gi, '<img src="$1" alt="" style="max-width:100%" />');

        // Attachments (with dimensions and without)
        html = html.replace(/\[attachment=(\d+),(\d+)x(\d+)\]/gi, '<img src="attachment.php?aid=$1" alt="Attachment" class="fmz-attachment-img" data-aid="$1" style="width:$2px;height:$3px;max-width:100%" />');
        html = html.replace(/\[attachment=(\d+)\]/gi, '<img src="attachment.php?aid=$1" alt="Attachment" class="fmz-attachment-img" data-aid="$1" style="max-width:100%" />');

        // Color, size, font, highlight
        html = html.replace(/\[color=(.*?)\]([\s\S]*?)\[\/color\]/gi, '<span style="color:$1">$2</span>');
        html = html.replace(/\[size=(\d+)\]([\s\S]*?)\[\/size\]/gi, '<span style="font-size:$1pt">$2</span>');
        html = html.replace(/\[size=(.*?)\]([\s\S]*?)\[\/size\]/gi, '<span style="font-size:$1">$2</span>');
        html = html.replace(/\[font=(.*?)\]([\s\S]*?)\[\/font\]/gi, '<span style="font-family:$1">$2</span>');
        html = html.replace(/\[highlight=(.*?)\]([\s\S]*?)\[\/highlight\]/gi, '<span style="background-color:$1">$2</span>');

        // Lists
        html = html.replace(/\[list=1\]([\s\S]*?)\[\/list\]/gi, '<ol>$1</ol>');
        html = html.replace(/\[list\]([\s\S]*?)\[\/list\]/gi, '<ul>$1</ul>');
        html = html.replace(/\[\*\](.*?)(?=\[\*\]|\[\/list\])/gi, '<li>$1</li>');

        // Alignment
        html = html.replace(/\[align=(.*?)\]([\s\S]*?)\[\/align\]/gi, '<div style="text-align:$1">$2</div>');

        // HR
        html = html.replace(/\[hr\]/gi, '<hr />');

        // Video — extract video ID from URL for embed, store ID in data-id
        html = html.replace(/\[video=youtube\]([\s\S]*?)\[\/video\]/gi, function(_, content) {
            var id = content.trim();
            var m = id.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
            if (m) id = m[1];
            return '<div class="fmz-embed-video" data-type="youtube" data-id="' + id + '" contenteditable="false" style="width:560px;height:315px"><iframe src="https://www.youtube.com/embed/' + id + '" frameborder="0" allowfullscreen></iframe></div>';
        });
        html = html.replace(/\[video=vimeo\]([\s\S]*?)\[\/video\]/gi, function(_, content) {
            var id = content.trim();
            var m = id.match(/vimeo\.com\/(?:video\/)?(\d+)/);
            if (m) id = m[1];
            return '<div class="fmz-embed-video" data-type="vimeo" data-id="' + id + '" contenteditable="false" style="width:560px;height:315px"><iframe src="https://player.vimeo.com/video/' + id + '" frameborder="0" allowfullscreen></iframe></div>';
        });
        html = html.replace(/\[video=dailymotion\]([\s\S]*?)\[\/video\]/gi, function(_, content) {
            var id = content.trim();
            var m = id.match(/dailymotion\.com\/video\/([a-z0-9]+)/i);
            if (m) id = m[1];
            return '<div class="fmz-embed-video" data-type="dailymotion" data-id="' + id + '" contenteditable="false" style="width:560px;height:315px"><iframe src="https://www.dailymotion.com/embed/video/' + id + '" frameborder="0" allowfullscreen></iframe></div>';
        });

        // Tables
        html = html.replace(/\[table\]([\s\S]*?)\[\/table\]/gi, '<table class="fmz-table">$1</table>');
        html = html.replace(/\[tr\]([\s\S]*?)\[\/tr\]/gi, '<tr>$1</tr>');
        html = html.replace(/\[th\]([\s\S]*?)\[\/th\]/gi, '<th>$1</th>');
        html = html.replace(/\[td\]([\s\S]*?)\[\/td\]/gi, '<td>$1</td>');

        // Newlines → <br>
        html = html.replace(/\n/g, '<br>');

        return html;
    }

    /**
     * DOM-based HTML → BBCode conversion.
     * Walks the DOM tree recursively for accurate, nesting-safe output.
     */
    function htmlToBb(html) {
        if (!html) return '';
        var container = document.createElement('div');
        container.innerHTML = html;
        var bb = _nodeToBb(container);
        // Decode entities
        var ta = document.createElement('textarea');
        ta.innerHTML = bb;
        bb = ta.value;
        // Trim trailing whitespace only (preserve intentional multiple newlines for WYSIWYG fidelity)
        bb = bb.trim();
        return bb;
    }

    function _nodeToBb(node) {
        var out = '';
        for (var i = 0; i < node.childNodes.length; i++) {
            out += _nodeConvert(node.childNodes[i]);
        }
        return out;
    }

    function _nodeConvert(node) {
        // Text node
        if (node.nodeType === 3) {
            return node.textContent;
        }
        // Not an element
        if (node.nodeType !== 1) return '';

        var tag = node.tagName.toLowerCase();
        var inner = _nodeToBb(node);

        // Bootstrap icon <i> — strip entirely
        if (tag === 'i' && node.className && node.className.indexOf('bi') !== -1) return '';

        // Code blocks: <pre> containing <code>
        if (tag === 'pre') {
            var codeEl = node.querySelector('code');
            if (codeEl) {
                var lang = '';
                var cls = codeEl.className || '';
                var lm = cls.match(/language-(\S+)/);
                if (lm) lang = lm[1];
                var codeText = codeEl.textContent.replace(/\u200B/g, ''); // strip zero-width spaces
                if (!codeText.trim()) return ''; // skip empty code blocks
                return lang ? '[code=' + lang + ']' + codeText + '[/code]\n' : '[code]' + codeText + '[/code]\n';
            }
            var preText = node.textContent.replace(/\u200B/g, '');
            if (!preText.trim()) return '';
            return '[code]' + preText + '[/code]\n';
        }

        // Video embeds — output full URL so MyBB's native parser can handle it
        if (tag === 'div' && node.classList.contains('fmz-embed-video')) {
            var vtype = node.getAttribute('data-type') || '';
            var vid = node.getAttribute('data-id') || '';
            if (vtype && vid) {
                var videoUrl = vid;
                if (vtype === 'youtube') videoUrl = 'https://www.youtube.com/watch?v=' + vid;
                else if (vtype === 'vimeo') videoUrl = 'https://vimeo.com/' + vid;
                else if (vtype === 'dailymotion') videoUrl = 'https://www.dailymotion.com/video/' + vid;
                var videoBb = '[video=' + vtype + ']' + videoUrl + '[/video]\n';
                // Preserve alignment
                var ml = node.style.marginLeft, mr = node.style.marginRight;
                if (ml === 'auto' && mr === 'auto') {
                    videoBb = '[align=center]' + videoBb + '[/align]\n';
                } else if (ml === 'auto' && mr === '0px') {
                    videoBb = '[align=right]' + videoBb + '[/align]\n';
                }
                return videoBb;
            }
            return '';
        }

        // Blockquote (Quote)
        if (tag === 'blockquote') {
            var author = node.getAttribute('data-author') || '';
            // Remove cite element from inner content
            var cite = node.querySelector('cite');
            if (cite) cite.remove();
            inner = _nodeToBb(node);
            if (author) return "[quote='" + author + "']" + inner + "[/quote]\n";
            return '[quote]' + inner + '[/quote]\n';
        }

        // Table elements
        if (tag === 'table') return '[table]' + inner + '[/table]\n';
        if (tag === 'tbody' || tag === 'thead' || tag === 'tfoot') return inner;
        if (tag === 'tr') return '[tr]' + inner + '[/tr]\n';
        if (tag === 'th') return '[th]' + inner.trim() + '[/th]';
        if (tag === 'td') return '[td]' + inner.trim() + '[/td]';

        // Lists
        if (tag === 'ul') return '[list]' + inner + '[/list]\n';
        if (tag === 'ol') return '[list=1]' + inner + '[/list]\n';
        if (tag === 'li') return '[*]' + inner.trim() + '\n';

        // Inline formatting
        if (tag === 'strong' || tag === 'b') return '[b]' + inner + '[/b]';
        if (tag === 'em' || (tag === 'i' && (!node.className || node.className.indexOf('bi') === -1))) return '[i]' + inner + '[/i]';
        if (tag === 'u' || (tag === 'ins')) return '[u]' + inner + '[/u]';
        if (tag === 's' || tag === 'del' || tag === 'strike') return '[s]' + inner + '[/s]';
        if (tag === 'sub') return inner;
        if (tag === 'sup') return inner;

        // Links
        if (tag === 'a') {
            var href = node.getAttribute('href') || '';
            if (href && inner && inner !== href) return '[url=' + href + ']' + inner + '[/url]';
            if (href) return '[url]' + href + '[/url]';
            return inner;
        }

        // Images
        if (tag === 'img') {
            var src = node.getAttribute('src') || '';
            var aid = node.getAttribute('data-aid') || '';
            var w = parseInt(node.style.width, 10) || parseInt(node.getAttribute('width'), 10) || 0;
            var h = parseInt(node.style.height, 10) || parseInt(node.getAttribute('height'), 10) || 0;
            // Attachment image
            if (aid || (src.indexOf('attachment.php') !== -1)) {
                if (!aid) {
                    var aidMatch = src.match(/aid=(\d+)/);
                    if (aidMatch) aid = aidMatch[1];
                }
                if (aid) {
                    // Include dimensions if resized
                    if (w && h) return '[attachment=' + aid + ',' + w + 'x' + h + ']';
                    return '[attachment=' + aid + ']';
                }
            }
            if (!src || src.indexOf('data:') === 0 || src.indexOf('blob:') === 0) return ''; // skip base64/blob leftovers
            // Support dimensions [img=WxH]
            if (w && h) return '[img=' + w + 'x' + h + ']' + src + '[/img]';
            return '[img]' + src + '[/img]';
        }

        // HR
        if (tag === 'hr') return '[hr]\n';

        // BR
        if (tag === 'br') return '\n';

        // Font tag (generated by foreColor command)
        if (tag === 'font') {
            var fontColor = node.getAttribute('color');
            var fontFace = node.getAttribute('face');
            var fontSize = node.getAttribute('size');
            var result = inner;
            if (fontColor) result = '[color=' + rgbToHex(fontColor) + ']' + result + '[/color]';
            if (fontFace) result = '[font=' + fontFace + ']' + result + '[/font]';
            if (fontSize) result = '[size=' + fontSize + ']' + result + '[/size]';
            return result;
        }

        // Span — check inline styles
        if (tag === 'span') {
            var st = node.style;
            var result = inner;
            if (st.backgroundColor) result = '[highlight=' + rgbToHex(st.backgroundColor) + ']' + result + '[/highlight]';
            if (st.color) result = '[color=' + rgbToHex(st.color) + ']' + result + '[/color]';
            if (st.fontSize) result = '[size=' + pxToMybbSize(st.fontSize) + ']' + result + '[/size]';
            if (st.fontFamily) result = '[font=' + st.fontFamily.replace(/['"]/g, '') + ']' + result + '[/font]';
            if (st.textDecoration && st.textDecoration.indexOf('underline') !== -1) result = '[u]' + result + '[/u]';
            if (st.textDecoration && st.textDecoration.indexOf('line-through') !== -1) result = '[s]' + result + '[/s]';
            if (st.fontWeight && (st.fontWeight === 'bold' || parseInt(st.fontWeight, 10) >= 700)) result = '[b]' + result + '[/b]';
            if (st.fontStyle === 'italic') result = '[i]' + result + '[/i]';
            return result;
        }

        // Div — check for text-align
        if (tag === 'div') {
            // Empty div with only a placeholder <br> (browser Enter behavior) = single newline
            if (node.childNodes.length === 1 && node.firstChild.nodeName === 'BR') {
                return '\n';
            }
            var align = node.style.textAlign;
            if (align && /^(left|center|right|justify)$/i.test(align)) {
                return '[align=' + align + ']' + inner + '[/align]\n';
            }
            // Generic div = line break
            return inner + '\n';
        }

        // Paragraph
        if (tag === 'p') {
            // Empty paragraph with only a placeholder <br> = single newline
            if (node.childNodes.length === 1 && node.firstChild.nodeName === 'BR') {
                return '\n';
            }
            return inner + '\n';
        }

        // Headings — bold
        if (/^h[1-6]$/.test(tag)) return '[b]' + inner + '[/b]\n';

        // Anything else — just return inner text
        return inner;
    }

    /* ═════════════════════════════════════════════════════════════
       Utility functions
       ═════════════════════════════════════════════════════════════ */

    function esc(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function detectDarkMode(cfg) {
        var mode = cfg.color_mode || 'light';
        if (mode === 'dark') return true;
        return false;
        // Auto: detect from page attributes, classes, or OS preference
        var h = document.documentElement, b = document.body;
        if (h.getAttribute('data-theme') === 'dark' || b.getAttribute('data-theme') === 'dark') return true;
        if (h.getAttribute('data-bs-theme') === 'dark' || b.getAttribute('data-bs-theme') === 'dark') return true;
        if (h.classList.contains('dark') || b.classList.contains('dark')) return true;
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) return true;
        return false;
    }

    function getThemeColors(cfg, isDark) {
        var preset = cfg.color_theme || 'default';
        var colors;
        if (preset === 'custom') {
            colors = {
                accent: cfg.custom_accent || '#0d9488',
                toolbarBg: cfg.custom_toolbar_bg || '#f5f5f5',
                toolbarText: cfg.custom_toolbar_text || '#555',
                activeBg: cfg.custom_accent ? cfg.custom_accent + '22' : '#d0f5f0',
                activeBorder: cfg.custom_accent || '#0d9488'
            };
        } else {
            colors = Object.assign({}, THEME_PRESETS[preset] || THEME_PRESETS['default']);
        }
        // Dark mode: use themed dark colors instead of generic gray
        if (isDark) {
            if (preset === 'custom') {
                // Derive a light toolbar text from the accent for custom themes
                colors.toolbarBg = cfg.custom_toolbar_bg_dark || '#2d2d30';
                colors.toolbarText = cfg.custom_toolbar_text_dark || colors.accent;
            } else {
                colors.toolbarBg = colors.darkToolbarBg || '#2d2d30';
                colors.toolbarText = colors.darkToolbarText || '#ccc';
            }
            colors.activeBg = colors.accent + '30';
        }
        return colors;
    }

    /**
     * Trim nested [quote] BBCode beyond the maximum depth.
     * Depth 0 = strip all quotes, depth 3 = allow 3 levels of nesting.
     */
    function trimQuoteDepth(bbcode, maxDepth) {
        if (maxDepth === '' || maxDepth === undefined || maxDepth === null) return bbcode;
        maxDepth = parseInt(maxDepth, 10);
        if (isNaN(maxDepth) || maxDepth <= 0) return bbcode;

        var result = '';
        var depth = 0;
        var i = 0;
        var len = bbcode.length;

        while (i < len) {
            // Check for opening [quote...] tag
            var openMatch = bbcode.slice(i).match(/^\[quote(?:=[^\]]*?)?\]/i);
            if (openMatch) {
                depth++;
                if (depth <= maxDepth) {
                    result += openMatch[0];
                }
                i += openMatch[0].length;
                continue;
            }
            // Check for closing [/quote] tag
            var closeMatch = bbcode.slice(i).match(/^\[\/quote\]/i);
            if (closeMatch) {
                if (depth <= maxDepth) {
                    result += closeMatch[0];
                }
                depth--;
                i += closeMatch[0].length;
                continue;
            }
            // Regular character — include only if within allowed depth
            if (depth <= maxDepth) {
                result += bbcode[i];
            }
            i++;
        }
        return result;
    }

    function parseFontFamilies(str) {
        if (!str) return [];
        return str.split('\n').map(function (line) {
            line = line.trim();
            if (!line) return null;
            var isGoogle = line.indexOf('google:') === 0;
            if (isGoogle) line = line.substring(7);
            var parts = line.split('|');
            return { name: parts[0].trim(), css: (parts[1] || parts[0]).trim(), google: isGoogle };
        }).filter(Boolean);
    }

    function parseFontSizes(str) {
        if (!str) return [];
        return str.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    }

    function parseToolbarConfig(cfg) {
        var style = cfg.toolbar_style || 'full';
        var str;
        if (style === 'minimal') str = MINIMAL_TOOLBAR;
        else if (style === 'custom' && cfg.toolbar_buttons) str = cfg.toolbar_buttons;
        else str = FULL_TOOLBAR;
        return str.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    }

    function extractVideoId(url) {
        var m;
        // YouTube
        m = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
        if (m) return { type: 'youtube', id: m[1] };
        // Vimeo
        m = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
        if (m) return { type: 'vimeo', id: m[1] };
        // Dailymotion
        m = url.match(/dailymotion\.com\/video\/([a-z0-9]+)/i);
        if (m) return { type: 'dailymotion', id: m[1] };
        return null;
    }

    /* ═════════════════════════════════════════════════════════════
       Editor Class
       ═════════════════════════════════════════════════════════════ */

    function FMZWysiwyg(textarea, cfg) {
        this.textarea = textarea;
        textarea._fmzWysiwyg = this; // back-reference for external access
        this.cfg = cfg || {};
        this.isDark = detectDarkMode(this.cfg);
        this.colors = getThemeColors(this.cfg, this.isDark);
        this.isSource = false;
        this.fonts = parseFontFamilies(this.cfg.font_families || '');
        this.sizes = parseFontSizes(this.cfg.font_sizes || '');
        this.toolbarItems = parseToolbarConfig(this.cfg);
        this.autoSaveKey = 'fmz_wysiwyg_' + window.location.pathname;
        this.autoSaveTimer = null;
        this._activeDropdown = null;
        this._undoStack = [];
        this._redoStack = [];
        this._undoTimer = null;
        this._lastTextColor = this.cfg.default_text_color || '#e06666';
        this._lastHighlightColor = this.cfg.default_highlight_color || '#fff2cc';
        this._imageCount = 0;

        this.build();
        this.bindEvents();
        this.restoreDraft();
        this.watchColorScheme();
        this.pushUndo();
    }

    /* ── Build DOM ── */

    FMZWysiwyg.prototype.build = function () {
        var self = this;

        this.textarea.style.display = 'none';

        // Wrapper
        this.wrap = document.createElement('div');
        this.wrap.className = 'fmz-wysiwyg-wrap' + (this.isDark ? ' fmz-wysiwyg-dark' : '');
        this.applyThemeVars();
        this.textarea.parentNode.insertBefore(this.wrap, this.textarea.nextSibling);

        // Toolbar
        this.toolbar = document.createElement('div');
        this.toolbar.className = 'fmz-wysiwyg-toolbar';
        this.wrap.appendChild(this.toolbar);
        this.buildToolbar();

        // Content area
        this.content = document.createElement('div');
        this.content.className = 'fmz-wysiwyg-content';
        this.content.contentEditable = 'true';
        this.content.style.minHeight = (this.cfg.editor_height || 350) + 'px';
        if (this.cfg.editor_font_family) this.content.style.fontFamily = this.cfg.editor_font_family;
        if (this.cfg.editor_font_size) this.content.style.fontSize = this.cfg.editor_font_size;
        this.wrap.appendChild(this.content);

        // Source textarea
        this.source = document.createElement('textarea');
        this.source.className = 'fmz-wysiwyg-source';
        this.source.spellcheck = false;
        this.wrap.appendChild(this.source);

        // Status bar
        this.statusbar = document.createElement('div');
        this.statusbar.className = 'fmz-wysiwyg-statusbar';
        this.statusbar.innerHTML = '<span class="fmz-wysiwyg-label">FMZ WYSIWYG</span>' +
            '<span class="fmz-wysiwyg-draft-status"></span>' +
            '<span class="fmz-wysiwyg-wordcount"></span>';
        this.wrap.appendChild(this.statusbar);

        // Load initial content
        var initialBB = this.textarea.value || '';
        this.content.innerHTML = bbToHtml(initialBB);
        this.source.value = initialBB;
        this.updateWordCount();
    };

    FMZWysiwyg.prototype.applyThemeVars = function () {
        var c = this.colors;
        this.wrap.style.setProperty('--fmz-wys-accent', c.accent);
        this.wrap.style.setProperty('--fmz-wys-toolbar-bg', c.toolbarBg);
        this.wrap.style.setProperty('--fmz-wys-toolbar-text', c.toolbarText);
        this.wrap.style.setProperty('--fmz-wys-active-bg', c.activeBg);
        this.wrap.style.setProperty('--fmz-wys-active-border', c.activeBorder);
    };

    FMZWysiwyg.prototype.buildToolbar = function () {
        var self = this;
        this.toolbar.innerHTML = '';

        this.toolbarItems.forEach(function (id) {
            if (id === '|') {
                var sep = document.createElement('span');
                sep.className = 'fmz-wys-sep';
                self.toolbar.appendChild(sep);
                return;
            }

            var def = ALL_BUTTONS[id];
            if (!def) return;

            // Skip GIF if no API key
            if (id === 'gif' && !self.cfg.giphy_api_key) return;

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'fmz-wys-btn';
            btn.title = def.title;
            btn.setAttribute('data-cmd', def.cmd);
            btn.setAttribute('data-id', id);

            // Split button (color/highlight): icon + color bar on left click, dropdown arrow on right
            if (def.split) {
                btn.className = 'fmz-wys-btn fmz-wys-split-btn';
                btn.style.cssText = 'display:inline-flex;align-items:stretch;padding:0;gap:0;width:auto;overflow:hidden;';

                var mainPart = document.createElement('span');
                mainPart.className = 'fmz-wys-split-main';
                mainPart.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;padding:0 4px;cursor:pointer;position:relative;';
                var iconEl = document.createElement('i');
                iconEl.className = 'bi ' + def.icon;
                mainPart.appendChild(iconEl);
                // Color indicator bar
                var colorBar = document.createElement('span');
                colorBar.className = 'fmz-wys-color-bar';
                var barColor = (id === 'fontColor') ? self._lastTextColor : self._lastHighlightColor;
                colorBar.style.cssText = 'display:block;width:14px;height:3px;border-radius:1px;background:' + barColor + ';margin-top:1px;';
                mainPart.appendChild(colorBar);
                btn.appendChild(mainPart);

                var arrowPart = document.createElement('span');
                arrowPart.className = 'fmz-wys-split-arrow';
                arrowPart.style.cssText = 'display:flex;align-items:center;justify-content:center;width:12px;cursor:pointer;border-left:1px solid rgba(128,128,128,.2);font-size:8px;';
                arrowPart.innerHTML = '&#9662;';
                btn.appendChild(arrowPart);

                // Left part: apply last color directly
                mainPart.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.content.focus();
                    if (id === 'fontColor') {
                        document.execCommand('foreColor', false, self._lastTextColor);
                    } else {
                        self.wrapSelection('span', { style: 'background-color:' + self._lastHighlightColor });
                    }
                    self.syncToTextarea();
                });
                // Right part: open dropdown
                arrowPart.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.toggleDropdown(id, btn);
                });
            } else {
                btn.innerHTML = '<i class="bi ' + def.icon + '"></i>';

                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (def.dropdown) {
                        self.toggleDropdown(id, btn);
                    } else {
                        self.execCommand(def.cmd);
                    }
                });
            }

            // Drag & drop reordering
            btn.draggable = true;
            btn.addEventListener('dragstart', function (e) {
                e.dataTransfer.setData('text/plain', id);
                btn.classList.add('fmz-wys-dragging');
            });
            btn.addEventListener('dragend', function () {
                btn.classList.remove('fmz-wys-dragging');
            });
            btn.addEventListener('dragover', function (e) {
                e.preventDefault();
                btn.classList.add('fmz-wys-dragover');
            });
            btn.addEventListener('dragleave', function () {
                btn.classList.remove('fmz-wys-dragover');
            });
            btn.addEventListener('drop', function (e) {
                e.preventDefault();
                btn.classList.remove('fmz-wys-dragover');
                var draggedId = e.dataTransfer.getData('text/plain');
                var targetId = id;
                if (draggedId === targetId) return;
                var items = self.toolbarItems.slice();
                var fromIdx = items.indexOf(draggedId);
                var toIdx = items.indexOf(targetId);
                if (fromIdx === -1 || toIdx === -1) return;
                items.splice(fromIdx, 1);
                items.splice(toIdx, 0, draggedId);
                self.toolbarItems = items;
                self.buildToolbar();
            });

            self.toolbar.appendChild(btn);
        });
    };

    /* ── Dropdowns ── */

    FMZWysiwyg.prototype.toggleDropdown = function (id, btn) {
        this.closeDropdown();

        var dd = document.createElement('div');
        dd.className = 'fmz-wys-dropdown' + (this.isDark ? ' fmz-wysiwyg-dark' : '');
        this._activeDropdown = dd;

        switch (id) {
            case 'fontFamily':  this.buildFontFamilyDD(dd); break;
            case 'fontSize':   this.buildFontSizeDD(dd); break;
            case 'fontColor':  this.buildColorDD(dd, 'foreColor'); break;
            case 'highlight':  this.buildColorDD(dd, 'hiliteColor'); break;
            case 'table':      this.buildTableDD(dd); break;
            case 'emoji':      this.buildEmojiDD(dd); break;
            case 'gif':        this.buildGifDD(dd); break;
            case 'code':       this.buildCodeDD(dd); break;
            default: return;
        }

        // Position relative to button
        var rect = btn.getBoundingClientRect();
        var wrapRect = this.wrap.getBoundingClientRect();
        dd.style.top = (rect.bottom - wrapRect.top) + 'px';
        dd.style.left = Math.max(0, rect.left - wrapRect.left) + 'px';
        this.wrap.appendChild(dd);

        // Keep on screen
        requestAnimationFrame(function () {
            var ddRect = dd.getBoundingClientRect();
            if (ddRect.right > window.innerWidth) {
                dd.style.left = Math.max(0, window.innerWidth - ddRect.width - wrapRect.left - 8) + 'px';
            }
        });
    };

    FMZWysiwyg.prototype.closeDropdown = function () {
        if (this._activeDropdown) {
            this._activeDropdown.remove();
            this._activeDropdown = null;
        }
    };

    FMZWysiwyg.prototype.buildFontFamilyDD = function (dd) {
        var self = this;
        dd.style.maxHeight = '300px';
        dd.style.overflowY = 'auto';
        dd.style.width = '220px';

        // Save selection before focus moves to dropdown
        self._savedRange = self._saveSelection();

        this.fonts.forEach(function (f) {
            var item = document.createElement('div');
            item.className = 'fmz-wys-dd-item';
            item.textContent = f.name;
            item.style.fontFamily = f.css;
            item.addEventListener('mousedown', function (e) { e.preventDefault(); });
            item.addEventListener('click', function () {
                self._restoreSelection(self._savedRange);
                document.execCommand('fontName', false, f.css);
                self.closeDropdown();
                self.syncToTextarea();
            });
            dd.appendChild(item);
        });
    };

    FMZWysiwyg.prototype.buildFontSizeDD = function (dd) {
        var self = this;
        dd.style.maxHeight = '300px';
        dd.style.overflowY = 'auto';

        // Save selection before focus moves to dropdown
        self._savedRange = self._saveSelection();

        this.sizes.forEach(function (size) {
            var item = document.createElement('div');
            item.className = 'fmz-wys-dd-item';
            item.textContent = size;
            item.style.fontSize = size;
            item.addEventListener('mousedown', function (e) { e.preventDefault(); });
            item.addEventListener('click', function () {
                self._restoreSelection(self._savedRange);
                self.wrapSelection('span', { style: 'font-size:' + size });
                self.closeDropdown();
                self.syncToTextarea();
            });
            dd.appendChild(item);
        });
    };

    FMZWysiwyg.prototype.buildColorDD = function (dd, command) {
        var self = this;
        var isHighlight = (command === 'hiliteColor');
        dd.style.width = '232px';
        dd.style.padding = '8px';

        // Save the user's text selection NOW, before focus moves to the dropdown
        self._savedRange = self._saveSelection();

        // Helper: store chosen color and update the split-button color bar
        function setLastColor(color) {
            if (isHighlight) {
                self._lastHighlightColor = color;
                var bar = self.toolbar.querySelector('[data-id="highlight"] .fmz-wys-color-bar');
                if (bar) bar.style.background = color;
            } else {
                self._lastTextColor = color;
                var bar = self.toolbar.querySelector('[data-id="fontColor"] .fmz-wys-color-bar');
                if (bar) bar.style.background = color;
            }
        }

        var grid = document.createElement('div');
        grid.className = 'fmz-wys-color-grid';

        COLOR_PALETTE.forEach(function (color) {
            var swatch = document.createElement('div');
            swatch.className = 'fmz-wys-color-swatch';
            swatch.style.backgroundColor = color;
            swatch.title = color;
            swatch.addEventListener('mousedown', function (e) {
                e.preventDefault(); // Prevent swatch click from stealing focus
            });
            swatch.addEventListener('click', function (e) {
                e.preventDefault();
                self._restoreSelection(self._savedRange);
                if (isHighlight) {
                    self.wrapSelection('span', { style: 'background-color:' + color });
                } else {
                    document.execCommand('foreColor', false, color);
                }
                setLastColor(color);
                self.syncToTextarea();
                self.closeDropdown();
            });
            grid.appendChild(swatch);
        });
        dd.appendChild(grid);

        // Custom color input with Apply button
        var custom = document.createElement('div');
        custom.className = 'fmz-wys-color-custom';
        custom.style.cssText = 'display:flex;align-items:center;gap:6px;margin-top:6px;padding-top:6px;border-top:1px solid var(--fmz-border,#ddd)';
        var label = document.createElement('label');
        label.textContent = 'Custom:';
        label.style.fontSize = '12px';
        custom.appendChild(label);

        var inp = document.createElement('input');
        inp.type = 'color';
        inp.value = '#000000';
        inp.style.cssText = 'width:32px;height:26px;border:1px solid #ccc;border-radius:3px;padding:0;cursor:pointer;';
        custom.appendChild(inp);

        // Preview swatch shows selected custom color
        var preview = document.createElement('span');
        preview.style.cssText = 'display:inline-block;width:20px;height:20px;border-radius:3px;border:1px solid #ccc;background:#000000;vertical-align:middle;';
        custom.appendChild(preview);

        inp.addEventListener('input', function () {
            preview.style.backgroundColor = inp.value;
        });

        // Prevent color picker from closing dropdown
        inp.addEventListener('mousedown', function (e) { e.stopPropagation(); });
        inp.addEventListener('click', function (e) { e.stopPropagation(); });
        inp.addEventListener('change', function (e) { e.stopPropagation(); });

        var applyBtn = document.createElement('button');
        applyBtn.type = 'button';
        applyBtn.textContent = 'Apply';
        applyBtn.className = 'fmz-wys-color-apply-btn';
        applyBtn.style.cssText = 'padding:3px 10px;font-size:12px;border:1px solid #ccc;border-radius:3px;background:var(--fmz-accent,#4f8cf7);color:#fff;cursor:pointer;margin-left:auto;';
        applyBtn.addEventListener('mousedown', function (e) { e.preventDefault(); });
        applyBtn.addEventListener('click', function (e) {
            e.preventDefault();
            self._restoreSelection(self._savedRange);
            if (isHighlight) {
                self.wrapSelection('span', { style: 'background-color:' + inp.value });
            } else {
                document.execCommand('foreColor', false, inp.value);
            }
            setLastColor(inp.value);
            self.syncToTextarea();
            self.closeDropdown();
        });
        custom.appendChild(applyBtn);

        dd.appendChild(custom);
    };

    FMZWysiwyg.prototype.buildTableDD = function (dd) {
        var self = this;
        dd.style.padding = '8px';

        var label = document.createElement('div');
        label.className = 'fmz-wys-dd-label';
        label.textContent = 'Select table size';
        dd.appendChild(label);

        var grid = document.createElement('div');
        grid.style.cssText = 'display:grid;grid-template-columns:repeat(8,20px);gap:2px;margin:6px 0';

        var info = document.createElement('div');
        info.className = 'fmz-wys-dd-label';
        info.textContent = '0 × 0';
        info.style.textAlign = 'center';

        for (var r = 0; r < 8; r++) {
            for (var c = 0; c < 8; c++) {
                (function (row, col) {
                    var cell = document.createElement('div');
                    cell.className = 'fmz-wys-table-cell';
                    cell.setAttribute('data-r', row);
                    cell.setAttribute('data-c', col);
                    cell.addEventListener('mouseenter', function () {
                        info.textContent = (row + 1) + ' × ' + (col + 1);
                        var cells = grid.querySelectorAll('.fmz-wys-table-cell');
                        for (var i = 0; i < cells.length; i++) {
                            var cr = +cells[i].getAttribute('data-r');
                            var cc = +cells[i].getAttribute('data-c');
                            cells[i].classList.toggle('active', cr <= row && cc <= col);
                        }
                    });
                    cell.addEventListener('click', function () {
                        self.insertTable(row + 1, col + 1);
                        self.closeDropdown();
                    });
                    grid.appendChild(cell);
                })(r, c);
            }
        }
        dd.appendChild(grid);
        dd.appendChild(info);
    };

    FMZWysiwyg.prototype.buildEmojiDD = function (dd) {
        var self = this;

        // Save cursor position before focus moves to dropdown
        self._savedRange = self._saveSelection();

        dd.style.width = '320px';
        dd.style.maxHeight = '350px';
        dd.style.overflowY = 'auto';
        dd.style.padding = '8px';

        // Search
        var search = document.createElement('input');
        search.type = 'text';
        search.placeholder = 'Search emoji...';
        search.className = 'fmz-wys-dd-search';
        dd.appendChild(search);

        var container = document.createElement('div');
        dd.appendChild(container);

        function render(filter) {
            container.innerHTML = '';
            Object.keys(EMOJI_CATEGORIES).forEach(function (cat) {
                var emojis = EMOJI_CATEGORIES[cat];
                if (filter) {
                    // Simple filter — just show all when searching
                    emojis = emojis.filter(function () { return true; });
                }
                if (!emojis.length) return;

                var heading = document.createElement('div');
                heading.className = 'fmz-wys-dd-label';
                heading.textContent = cat;
                heading.style.marginTop = '6px';
                container.appendChild(heading);

                var grid = document.createElement('div');
                grid.style.cssText = 'display:flex;flex-wrap:wrap;gap:2px';

                emojis.forEach(function (em) {
                    var btn = document.createElement('span');
                    btn.className = 'fmz-wys-emoji-btn';
                    btn.textContent = em;
                    btn.title = em;
                    btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
                    btn.addEventListener('click', function () {
                        self._restoreSelection(self._savedRange);
                        self.insertAtCursor(em);
                        self.closeDropdown();
                    });
                    grid.appendChild(btn);
                });
                container.appendChild(grid);
            });
        }

        render('');
        search.addEventListener('input', function () {
            render(this.value.toLowerCase());
        });
    };

    FMZWysiwyg.prototype.buildGifDD = function (dd) {
        var self = this;
        var apiKey = this.cfg.giphy_api_key;
        if (!apiKey) return;

        // Save cursor position before focus moves to dropdown
        self._savedRange = self._saveSelection();

        dd.style.width = '350px';
        dd.style.maxHeight = '400px';
        dd.style.overflowY = 'auto';
        dd.style.padding = '8px';

        var search = document.createElement('input');
        search.type = 'text';
        search.placeholder = 'Search GIFs...';
        search.className = 'fmz-wys-dd-search';
        dd.appendChild(search);

        var results = document.createElement('div');
        results.style.cssText = 'display:grid;grid-template-columns:repeat(2,1fr);gap:4px;margin-top:6px';
        dd.appendChild(results);

        var timer;
        search.addEventListener('input', function () {
            clearTimeout(timer);
            var q = this.value.trim();
            if (!q) { results.innerHTML = ''; return; }
            timer = setTimeout(function () {
                fetch('https://api.giphy.com/v1/gifs/search?api_key=' + encodeURIComponent(apiKey) + '&q=' + encodeURIComponent(q) + '&limit=20&rating=g')
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        results.innerHTML = '';
                        if (!data.data) return;
                        data.data.forEach(function (gif) {
                            var img = document.createElement('img');
                            img.src = gif.images.fixed_height_small.url;
                            img.style.cssText = 'width:100%;border-radius:4px;cursor:pointer';
                            img.title = gif.title;
                            img.addEventListener('click', function () {
                                var fullUrl = gif.images.original.url;
                                self._restoreSelection(self._savedRange);
                                self.insertAtCursor('<img src="' + esc(fullUrl) + '" alt="' + esc(gif.title) + '" style="max-width:100%" />');
                                self.closeDropdown();
                                self.syncToTextarea();
                            });
                            results.appendChild(img);
                        });
                    });
            }, 400);
        });

        // Show trending on open
        fetch('https://api.giphy.com/v1/gifs/trending?api_key=' + encodeURIComponent(apiKey) + '&limit=12&rating=g')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.data) return;
                data.data.forEach(function (gif) {
                    var img = document.createElement('img');
                    img.src = gif.images.fixed_height_small.url;
                    img.style.cssText = 'width:100%;border-radius:4px;cursor:pointer';
                    img.title = gif.title;
                    img.addEventListener('click', function () {
                        self._restoreSelection(self._savedRange);
                        self.insertAtCursor('<img src="' + esc(gif.images.original.url) + '" alt="' + esc(gif.title) + '" style="max-width:100%" />');
                        self.closeDropdown();
                        self.syncToTextarea();
                    });
                    results.appendChild(img);
                });
            });
    };

    FMZWysiwyg.prototype.buildCodeDD = function (dd) {
        var self = this;
        dd.style.width = '180px';
        dd.style.maxHeight = '300px';
        dd.style.overflowY = 'auto';

        var langs = ['Plain','JavaScript','PHP','HTML','CSS','Python','Java','C','C++','C#','Ruby','Go','Rust','SQL','Bash','TypeScript','JSON','XML','YAML','Markdown'];

        langs.forEach(function (lang) {
            var item = document.createElement('div');
            item.className = 'fmz-wys-dd-item';
            item.textContent = lang;
            item.addEventListener('click', function () {
                var sel = window.getSelection();
                var text = sel.toString() || '';
                var langAttr = lang === 'Plain' ? '' : lang.toLowerCase();
                var pre = document.createElement('pre');
                pre.className = 'fmz-code-block';
                if (langAttr) pre.setAttribute('data-lang', langAttr);
                var code = document.createElement('code');
                if (langAttr) code.className = 'language-' + langAttr;
                // Use a zero-width space if empty so the cursor has a place to land
                code.textContent = text || '\u200B';
                pre.appendChild(code);
                self.content.focus();
                if (sel.rangeCount) {
                    var range = sel.getRangeAt(0);
                    range.deleteContents();
                    // Add a <br><p> after the pre so user can type below it
                    var afterBlock = document.createElement('p');
                    afterBlock.innerHTML = '<br>';
                    range.insertNode(afterBlock);
                    range.insertNode(pre);
                    // Place cursor inside the code element
                    var codeRange = document.createRange();
                    if (text) {
                        codeRange.selectNodeContents(code);
                    } else {
                        // Position after the zero-width space
                        codeRange.setStart(code.firstChild, 1);
                        codeRange.collapse(true);
                    }
                    sel.removeAllRanges();
                    sel.addRange(codeRange);
                }
                self.closeDropdown();
                self.syncToTextarea();
            });
            dd.appendChild(item);
        });
    };

    /* ── Command execution ── */

    FMZWysiwyg.prototype.execCommand = function (cmd) {
        var self = this;

        if (cmd === 'fmz-source') { this.toggleSource(); return; }
        if (cmd === 'fmz-savedraft') { this.saveDraft(); this.showDraftStatus('Draft saved!'); return; }
        if (cmd === 'undo') { this.undo(); return; }
        if (cmd === 'redo') { this.redo(); return; }

        if (cmd === 'fmz-link') {
            var url = prompt('Enter URL:', 'https://');
            if (url) {
                this.content.focus();
                document.execCommand('createLink', false, url);
            }
            this.syncToTextarea();
            return;
        }

        if (cmd === 'fmz-image') {
            var imgUrl = prompt('Enter image URL:', 'https://');
            if (imgUrl) {
                this.content.focus();
                document.execCommand('insertImage', false, imgUrl);
            }
            this.syncToTextarea();
            return;
        }

        if (cmd === 'fmz-video') {
            var videoUrl = prompt('Enter video URL (YouTube, Vimeo, Dailymotion):', 'https://');
            if (videoUrl) {
                var info = extractVideoId(videoUrl);
                if (info) {
                    var embedHtml = '<div class="fmz-embed-video" data-type="' + esc(info.type) + '" data-id="' + esc(info.id) + '" contenteditable="false" style="width:560px;height:315px">';
                    if (info.type === 'youtube') {
                        embedHtml += '<iframe src="https://www.youtube.com/embed/' + esc(info.id) + '" frameborder="0" allowfullscreen></iframe>';
                    } else if (info.type === 'vimeo') {
                        embedHtml += '<iframe src="https://player.vimeo.com/video/' + esc(info.id) + '" frameborder="0" allowfullscreen></iframe>';
                    } else if (info.type === 'dailymotion') {
                        embedHtml += '<iframe src="https://www.dailymotion.com/embed/video/' + esc(info.id) + '" frameborder="0" allowfullscreen></iframe>';
                    }
                    embedHtml += '</div><p><br></p>';
                    this.insertAtCursor(embedHtml);
                } else {
                    alert('Could not recognize video URL. Supported: YouTube, Vimeo, Dailymotion.');
                }
            }
            this.syncToTextarea();
            return;
        }

        if (cmd === 'fmz-quote') {
            var sel = window.getSelection();
            var text = sel.toString() || '';
            var quote = document.createElement('blockquote');
            quote.innerHTML = text || '<br>';
            this.content.focus();
            if (sel.rangeCount) {
                var range = sel.getRangeAt(0);
                range.deleteContents();
                range.insertNode(quote);
                range.setStartAfter(quote);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
            }
            this.syncToTextarea();
            return;
        }

        if (cmd === 'fmz-formula') {
            var formula = prompt('Enter LaTeX formula (e.g. E=mc^2):', '');
            if (formula) {
                var encoded = encodeURIComponent(formula);
                var img = '<img src="https://latex.codecogs.com/svg.image?' + encoded + '" alt="' + esc(formula) + '" class="fmz-formula" />';
                this.insertAtCursor(img);
            }
            this.syncToTextarea();
            return;
        }

        // HR — insert directly to avoid browser wrapping quirks
        if (cmd === 'insertHorizontalRule') {
            this.insertAtCursor('<hr /><p><br></p>');
            this.syncToTextarea();
            return;
        }

        // Standard execCommand
        this.content.focus();
        document.execCommand(cmd, false, null);
        this.syncToTextarea();
    };

    /* ── Table insertion ── */

    FMZWysiwyg.prototype.insertTable = function (rows, cols) {
        var html = '<table class="fmz-table"><tbody>';
        for (var r = 0; r < rows; r++) {
            html += '<tr>';
            for (var c = 0; c < cols; c++) {
                html += r === 0 ? '<th>&nbsp;</th>' : '<td>&nbsp;</td>';
            }
            html += '</tr>';
        }
        html += '</tbody></table><br>';
        this.insertAtCursor(html);
        this.syncToTextarea();
    };

    /* ── Undo/Redo ── */

    FMZWysiwyg.prototype.pushUndo = function () {
        var html = this.content.innerHTML;
        if (this._undoStack.length && this._undoStack[this._undoStack.length - 1] === html) return;
        this._undoStack.push(html);
        if (this._undoStack.length > 100) this._undoStack.shift();
        this._redoStack = [];
    };

    FMZWysiwyg.prototype.undo = function () {
        if (this._undoStack.length <= 1) return;
        this._redoStack.push(this._undoStack.pop());
        this.content.innerHTML = this._undoStack[this._undoStack.length - 1];
        this.syncToTextarea();
    };

    FMZWysiwyg.prototype.redo = function () {
        if (!this._redoStack.length) return;
        var html = this._redoStack.pop();
        this._undoStack.push(html);
        this.content.innerHTML = html;
        this.syncToTextarea();
    };

    /* ── Selection helpers ── */

    FMZWysiwyg.prototype._saveSelection = function () {
        var sel = window.getSelection();
        if (sel.rangeCount > 0) {
            return sel.getRangeAt(0).cloneRange();
        }
        return null;
    };

    FMZWysiwyg.prototype._restoreSelection = function (range) {
        if (!range) return;
        this.content.focus();
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    };

    FMZWysiwyg.prototype.wrapSelection = function (tag, attrs) {
        this.content.focus();
        var sel = window.getSelection();
        if (!sel.rangeCount) return;
        var range = sel.getRangeAt(0);
        var el = document.createElement(tag);
        for (var k in attrs) el.setAttribute(k, attrs[k]);
        try {
            range.surroundContents(el);
        } catch (e) {
            el.appendChild(range.extractContents());
            range.insertNode(el);
        }
        range.selectNodeContents(el);
        sel.removeAllRanges();
        sel.addRange(range);
        this.syncToTextarea();
    };

    FMZWysiwyg.prototype.insertAtCursor = function (html) {
        this.content.focus();
        var sel = window.getSelection();
        if (sel.rangeCount) {
            var range = sel.getRangeAt(0);
            range.collapse(false);
            var frag = range.createContextualFragment(html);
            var lastNode = frag.lastChild;
            range.insertNode(frag);
            if (lastNode) {
                range.setStartAfter(lastNode);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }
        this.syncToTextarea();
    };

    /**
     * Insert BBCode content — converts to HTML, trims quote depth, inserts at cursor.
     */
    FMZWysiwyg.prototype.insertBBCode = function (bbcode) {
        var maxDepth = parseInt(this.cfg.max_quote_depth, 10);
        if (maxDepth > 0) {
            bbcode = trimQuoteDepth(bbcode, maxDepth);
        }
        var html = bbToHtml(bbcode);
        this.insertAtCursor(html);
    };

    /**
     * Get or set the editor value as BBCode.
     */
    FMZWysiwyg.prototype.getBBCode = function () {
        if (this.isSource) {
            return this.source.value;
        }
        return htmlToBb(this.content.innerHTML);
    };

    FMZWysiwyg.prototype.setBBCode = function (bbcode) {
        this.content.innerHTML = bbToHtml(bbcode);
        this.source.value = bbcode;
        this.syncToTextarea();
    };

    /* ── Source toggle ── */

    FMZWysiwyg.prototype.toggleSource = function () {
        this.isSource = !this.isSource;
        if (this.isSource) {
            this.source.value = htmlToBb(this.content.innerHTML);
            this.content.style.display = 'none';
            this.source.style.display = 'block';
            this.source.focus();
        } else {
            this.content.innerHTML = bbToHtml(this.source.value);
            this.source.style.display = 'none';
            this.content.style.display = 'block';
            this.content.focus();
        }
        var btn = this.toolbar.querySelector('[data-cmd="fmz-source"]');
        if (btn) btn.classList.toggle('active', this.isSource);
    };

    /* ── Events ── */

    FMZWysiwyg.prototype.bindEvents = function () {
        var self = this;

        this.content.addEventListener('input', function () {
            self.syncToTextarea();
            self.updateWordCount();
            // Debounced undo push
            clearTimeout(self._undoTimer);
            self._undoTimer = setTimeout(function () { self.pushUndo(); }, 500);
        });

        this.source.addEventListener('input', function () {
            self.textarea.value = self.source.value;
        });

        // Keyboard shortcuts
        this.content.addEventListener('keydown', function (e) {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key.toLowerCase()) {
                    case 'b': e.preventDefault(); document.execCommand('bold', false, null); break;
                    case 'i': e.preventDefault(); document.execCommand('italic', false, null); break;
                    case 'u': e.preventDefault(); document.execCommand('underline', false, null); break;
                    case 'z':
                        e.preventDefault();
                        if (e.shiftKey) self.redo(); else self.undo();
                        break;
                    case 'y': e.preventDefault(); self.redo(); break;
                    case 's': e.preventDefault(); self.saveDraft(); self.showDraftStatus('Draft saved!'); break;
                }
            }
            // Enter — insert <br> instead of browser-default <div>/<p> for clean WYSIWYG newlines
            if (e.key === 'Enter' && !(e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                var sel = window.getSelection();
                if (sel.rangeCount) {
                    var range = sel.getRangeAt(0);
                    range.deleteContents();
                    var br = document.createElement('br');
                    range.insertNode(br);
                    range = document.createRange();
                    range.setStartAfter(br);
                    range.collapse(true);
                    // Ensure cursor is visible on new line at end of content
                    if (!br.nextSibling) {
                        var sentinel = document.createElement('br');
                        br.parentNode.appendChild(sentinel);
                    }
                    sel.removeAllRanges();
                    sel.addRange(range);
                    self.content.dispatchEvent(new Event('input'));
                }
                return;
            }
            // Tab / Shift+Tab indent / unindent
            if (e.key === 'Tab') {
                e.preventDefault();
                if (e.shiftKey) {
                    // Unindent: remove up to 4 leading spaces from current line
                    var sel = window.getSelection();
                    if (!sel.rangeCount) return;
                    var range = sel.getRangeAt(0);
                    var node = range.startContainer;
                    if (node.nodeType === 3) {
                        var text = node.textContent;
                        var offset = range.startOffset;
                        // Walk back to find the start of the current line
                        var lineStart = text.lastIndexOf('\n', offset - 1) + 1;
                        var linePrefix = text.substring(lineStart, lineStart + 4);
                        var spaces = 0;
                        for (var s = 0; s < linePrefix.length && linePrefix[s] === ' '; s++) spaces++;
                        if (spaces > 0) {
                            node.textContent = text.substring(0, lineStart) + text.substring(lineStart + spaces);
                            var newOffset = Math.max(lineStart, offset - spaces);
                            range.setStart(node, newOffset);
                            range.collapse(true);
                            sel.removeAllRanges();
                            sel.addRange(range);
                            self.syncToTextarea();
                        }
                    }
                } else {
                    document.execCommand('insertText', false, '    ');
                }
            }
        });

        // Image paste & drop
        this.content.addEventListener('paste', function (e) { self.handlePaste(e); });

        // Track drag enter/leave depth so nested children don't flicker the overlay
        self._dragCounter = 0;
        this.content.addEventListener('dragenter', function (e) {
            // Only react to external file drags, not toolbar button reordering
            if (!e.dataTransfer || !e.dataTransfer.types || e.dataTransfer.types.indexOf('Files') === -1) return;
            e.preventDefault();
            self._dragCounter++;
            if (self._dragCounter === 1) self.showUploadOverlay();
        });
        this.content.addEventListener('dragover', function (e) {
            if (!e.dataTransfer || !e.dataTransfer.types || e.dataTransfer.types.indexOf('Files') === -1) return;
            e.preventDefault();
        });
        this.content.addEventListener('dragleave', function (e) {
            self._dragCounter--;
            if (self._dragCounter <= 0) {
                self._dragCounter = 0;
                self.hideUploadOverlay();
            }
        });
        this.content.addEventListener('drop', function (e) {
            self._dragCounter = 0;
            // Only handle file drops (not toolbar drag/drop)
            if (e.dataTransfer.files && e.dataTransfer.files.length) {
                e.preventDefault();
                self.hideUploadOverlay();
                self.handleDrop(e);
            }
        });

        // Image & video click for resize handles
        this.content.addEventListener('click', function (e) {
            var img = e.target.closest('img');
            var video = e.target.closest('.fmz-embed-video');
            if (img) {
                e.preventDefault();
                self.showImageResize(img);
            } else if (video) {
                e.preventDefault();
                self.showVideoResize(video);
            } else {
                self.hideImageResize();
                self.hideVideoResize();
            }
        });

        // Close dropdown on click outside
        document.addEventListener('click', function (e) {
            if (self._activeDropdown && !self._activeDropdown.contains(e.target) && !e.target.closest('.fmz-wys-btn')) {
                self.closeDropdown();
            }
        });

        // Form submit sync
        var form = this.textarea.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                self.syncToTextarea();
                self.clearDraft();
            });
        }

        // Auto-save
        if (this.cfg.auto_save !== '0') {
            var interval = (parseInt(this.cfg.auto_save_interval, 10) || 30) * 1000;
            this.autoSaveTimer = setInterval(function () { self.saveDraft(); }, interval);
        }
    };

    /* ── Image Resize ── */

    FMZWysiwyg.prototype.showImageResize = function (img) {
        this.hideImageResize();

        var self = this;
        var wrap = document.createElement('span');
        wrap.className = 'fmz-img-resize-wrap';
        wrap.contentEditable = 'false';
        wrap.style.cssText = 'display:inline-block;position:relative;line-height:0;';

        img.parentNode.insertBefore(wrap, img);
        wrap.appendChild(img);

        // Resize handle (bottom-right corner)
        var handle = document.createElement('span');
        handle.className = 'fmz-img-resize-handle';
        handle.style.cssText = 'position:absolute;right:-4px;bottom:-4px;width:12px;height:12px;background:var(--fmz-accent,#4f8cf7);border:2px solid #fff;border-radius:2px;cursor:nwse-resize;z-index:10;box-shadow:0 1px 3px rgba(0,0,0,.3);';
        wrap.appendChild(handle);

        this._resizeWrap = wrap;
        this._resizeImg = img;

        var startX, startY, startW, startH, aspect;

        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            startX = e.clientX;
            startY = e.clientY;
            startW = img.offsetWidth;
            startH = img.offsetHeight;
            aspect = startW / startH;

            var onMove = function (ev) {
                var dx = ev.clientX - startX;
                var newW = Math.max(20, startW + dx);
                // Always lock aspect ratio for intuitive behavior
                var newH = Math.round(newW / aspect);
                img.style.width = newW + 'px';
                img.style.height = newH + 'px';
            };
            var onUp = function () {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                self.syncToTextarea();
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    };

    FMZWysiwyg.prototype.hideImageResize = function () {
        if (!this._resizeWrap) return;
        var wrap = this._resizeWrap;
        var img = this._resizeImg;
        if (img) {
            // Unwrap: move img back to parent
            if (wrap.parentNode) {
                wrap.parentNode.insertBefore(img, wrap);
                wrap.remove();
            }
        }
        this._resizeWrap = null;
        this._resizeImg = null;
    };

    /* ── Video Resize & Align ── */

    FMZWysiwyg.prototype.showVideoResize = function (videoDiv) {
        this.hideVideoResize();
        this.hideImageResize();

        var self = this;

        // Resize handle (bottom-right corner)
        var handle = document.createElement('span');
        handle.className = 'fmz-video-resize-handle';
        handle.contentEditable = 'false';
        handle.style.cssText = 'position:absolute;right:-4px;bottom:-4px;width:12px;height:12px;background:var(--fmz-accent,#4f8cf7);border:2px solid #fff;border-radius:2px;cursor:nwse-resize;z-index:10;box-shadow:0 1px 3px rgba(0,0,0,.3);';
        videoDiv.appendChild(handle);

        // Alignment toolbar
        var alignBar = document.createElement('div');
        alignBar.className = 'fmz-video-align-bar';
        alignBar.contentEditable = 'false';
        alignBar.style.cssText = 'position:absolute;top:-32px;left:50%;transform:translateX(-50%);display:flex;gap:2px;background:rgba(0,0,0,.75);border-radius:4px;padding:3px 4px;z-index:12;';

        var alignments = [
            { icon: 'bi-text-left', align: 'left', title: 'Align Left' },
            { icon: 'bi-text-center', align: 'center', title: 'Center' },
            { icon: 'bi-text-right', align: 'right', title: 'Align Right' }
        ];

        alignments.forEach(function (a) {
            var abtn = document.createElement('button');
            abtn.type = 'button';
            abtn.title = a.title;
            abtn.innerHTML = '<i class="bi ' + a.icon + '"></i>';
            abtn.style.cssText = 'background:none;border:none;color:#fff;font-size:13px;padding:2px 6px;cursor:pointer;border-radius:3px;';
            abtn.addEventListener('mousedown', function (e) { e.preventDefault(); e.stopPropagation(); });
            abtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var parent = videoDiv.parentElement;
                if (a.align === 'center') {
                    videoDiv.style.display = 'block';
                    videoDiv.style.marginLeft = 'auto';
                    videoDiv.style.marginRight = 'auto';
                } else if (a.align === 'left') {
                    videoDiv.style.display = 'inline-block';
                    videoDiv.style.marginLeft = '0';
                    videoDiv.style.marginRight = 'auto';
                } else if (a.align === 'right') {
                    videoDiv.style.display = 'inline-block';
                    videoDiv.style.marginLeft = 'auto';
                    videoDiv.style.marginRight = '0';
                }
                self.syncToTextarea();
            });
            alignBar.appendChild(abtn);
        });
        videoDiv.appendChild(alignBar);

        // Highlight border
        videoDiv.style.outline = '2px solid var(--fmz-accent, #4f8cf7)';
        videoDiv.style.outlineOffset = '2px';

        this._videoResizeEl = videoDiv;
        this._videoResizeHandle = handle;
        this._videoAlignBar = alignBar;

        var startX, startW, startH, aspect;

        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            startX = e.clientX;
            startW = videoDiv.offsetWidth;
            startH = videoDiv.offsetHeight;
            aspect = startW / startH;

            var onMove = function (ev) {
                var dx = ev.clientX - startX;
                var newW = Math.max(200, startW + dx);
                var newH = Math.round(newW / aspect);
                videoDiv.style.width = newW + 'px';
                videoDiv.style.height = newH + 'px';
            };
            var onUp = function () {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                self.syncToTextarea();
            };
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    };

    FMZWysiwyg.prototype.hideVideoResize = function () {
        if (!this._videoResizeEl) return;
        this._videoResizeEl.style.outline = '';
        this._videoResizeEl.style.outlineOffset = '';
        if (this._videoResizeHandle) this._videoResizeHandle.remove();
        if (this._videoAlignBar) this._videoAlignBar.remove();
        this._videoResizeEl = null;
        this._videoResizeHandle = null;
        this._videoAlignBar = null;
    };

    /* ── Destroy ── */

    FMZWysiwyg.prototype.destroy = function () {
        // Stop auto-save timer
        if (this.autoSaveTimer) clearTimeout(this.autoSaveTimer);
        // Remove the wrapper (toolbar + content + source + statusbar)
        if (this.wrap && this.wrap.parentNode) {
            this.wrap.parentNode.removeChild(this.wrap);
        }
        // Re-show the textarea
        this.textarea.style.display = '';
        // Remove back-reference
        delete this.textarea._fmzWysiwyg;
    };

    /* ── Sync, wordcount, drafts ── */

    FMZWysiwyg.prototype.syncToTextarea = function () {
        this.textarea.value = htmlToBb(this.content.innerHTML);
    };

    FMZWysiwyg.prototype.updateWordCount = function () {
        var text = this.content.textContent || '';
        var words = text.trim().split(/\s+/).filter(function (w) { return w.length > 0; }).length;
        var chars = text.length;
        var el = this.statusbar.querySelector('.fmz-wysiwyg-wordcount');
        if (el) el.textContent = words + ' words · ' + chars + ' chars';
    };

    FMZWysiwyg.prototype.showDraftStatus = function (msg) {
        var el = this.statusbar.querySelector('.fmz-wysiwyg-draft-status');
        if (el) {
            el.textContent = msg;
            el.style.opacity = '1';
            setTimeout(function () { el.style.opacity = '0'; }, 2000);
        }
    };

    FMZWysiwyg.prototype.handlePaste = function (e) {
        var clipboard = e.clipboardData || e.originalEvent.clipboardData;
        if (!clipboard) return;
        var items = clipboard.items;

        // Handle image paste
        if (items && this.cfg.enable_image_paste !== '0') {
            for (var i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') !== -1) {
                    e.preventDefault();
                    this.insertImageFile(items[i].getAsFile());
                    return;
                }
            }
        }

        // Clean pasted HTML from external sources
        var pastedHtml = clipboard.getData('text/html');
        if (pastedHtml) {
            e.preventDefault();
            var cleaned = this.cleanPastedHtml(pastedHtml);
            // Normalize through BBCode roundtrip so the pasted result matches
            // what will actually appear in the final post (strips styles that
            // have no BBCode equivalent and would be lost on source toggle).
            cleaned = bbToHtml(htmlToBb(cleaned));
            document.execCommand('insertHTML', false, cleaned);
            this.syncToTextarea();
        }
    };

    /**
     * Strip unwanted styles and wrapper elements from externally pasted HTML.
     * Keeps structural formatting (bold, italic, underline, links, lists, images)
     * but removes background colors, text colors, font families, and stray wrappers.
     */
    FMZWysiwyg.prototype.cleanPastedHtml = function (html) {
        var container = document.createElement('div');
        container.innerHTML = html;

        // Remove <style>, <script>, <meta>, <link>, <title> tags entirely
        var junkTags = container.querySelectorAll('style, script, meta, link, title, head, xml');
        for (var i = junkTags.length - 1; i >= 0; i--) {
            junkTags[i].parentNode.removeChild(junkTags[i]);
        }

        // Tags whose content we want to keep (unwrap the tag, keep children)
        var unwrapTags = ['ARTICLE', 'ASIDE', 'SECTION', 'HEADER', 'FOOTER', 'NAV',
                          'MAIN', 'FIGURE', 'FIGCAPTION', 'DETAILS', 'SUMMARY'];

        // Allowed tags — everything else gets unwrapped
        var allowedTags = ['B', 'STRONG', 'I', 'EM', 'U', 'S', 'STRIKE', 'DEL',
                           'A', 'IMG', 'BR', 'HR', 'P', 'DIV', 'SPAN',
                           'UL', 'OL', 'LI', 'BLOCKQUOTE', 'PRE', 'CODE',
                           'TABLE', 'THEAD', 'TBODY', 'TFOOT', 'TR', 'TH', 'TD',
                           'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'SUB', 'SUP', 'VIDEO', 'SOURCE'];

        // Style properties we allow to keep (includes color/font-size/background for WYSIWYG fidelity)
        var allowedStyles = ['font-weight', 'font-style', 'text-decoration',
                             'text-align', 'max-width', 'width', 'height',
                             'color', 'background-color', 'font-size', 'font-family'];

        var allEls = container.querySelectorAll('*');
        // Iterate backwards so removals don't shift indices
        for (var j = allEls.length - 1; j >= 0; j--) {
            var el = allEls[j];
            var tag = el.tagName;

            // Unwrap disallowed / semantic-wrapper tags
            if (unwrapTags.indexOf(tag) !== -1 ||
                (allowedTags.indexOf(tag) === -1 && tag !== 'HTML' && tag !== 'BODY')) {
                // Replace element with its children
                while (el.firstChild) {
                    el.parentNode.insertBefore(el.firstChild, el);
                }
                el.parentNode.removeChild(el);
                continue;
            }

            // Strip class and id attributes
            el.removeAttribute('class');
            el.removeAttribute('id');
            el.removeAttribute('data-ccp-props');

            // Clean inline styles: keep only allowed properties
            if (el.style && el.getAttribute('style')) {
                var kept = [];
                for (var s = 0; s < allowedStyles.length; s++) {
                    var val = el.style.getPropertyValue(allowedStyles[s]);
                    if (val) {
                        kept.push(allowedStyles[s] + ':' + val);
                    }
                }
                if (kept.length) {
                    el.setAttribute('style', kept.join(';'));
                } else {
                    el.removeAttribute('style');
                }
            }

            // Remove empty spans that served only as style wrappers
            if (tag === 'SPAN' && !el.getAttribute('style') && !el.attributes.length) {
                while (el.firstChild) {
                    el.parentNode.insertBefore(el.firstChild, el);
                }
                el.parentNode.removeChild(el);
            }
        }

        return container.innerHTML;
    };

    FMZWysiwyg.prototype.handleDrop = function (e) {
        if (this.cfg.enable_image_upload === '0') return;
        var files = e.dataTransfer.files;
        if (!files || !files.length) return;
        for (var i = 0; i < files.length; i++) {
            if (files[i].type.indexOf('image') !== -1) this.insertImageFile(files[i]);
        }
    };

    FMZWysiwyg.prototype.insertImageFile = function (file) {
        var self = this;
        var maxKB = parseInt(this.cfg.max_image_size_kb, 10) || 2048;
        if (file.size > maxKB * 1024) {
            alert('Image too large. Max size: ' + (maxKB >= 1024 ? (maxKB / 1024) + ' MB' : maxKB + ' KB'));
            return;
        }

        // Check images-per-post limit
        var maxImages = parseInt(this.cfg.max_images_per_post, 10);
        if (maxImages > 0 && this._imageCount >= maxImages) {
            alert('Maximum of ' + maxImages + ' images per post reached.');
            return;
        }

        // Get post key for CSRF verification
        var postKeyEl = document.querySelector('[name="my_post_key"]');
        if (!postKeyEl) {
            alert('Unable to upload: missing security token.');
            return;
        }

        // Get posthash from the posting form (links attachment to the draft)
        var posthashEl = document.querySelector('[name="posthash"]');
        if (!posthashEl || !posthashEl.value) {
            alert('Unable to upload: missing post hash. Please use the full reply page.');
            return;
        }

        // Show uploading placeholder
        var placeholderId = 'fmz-upload-' + Date.now();
        this.insertAtCursor('<span id="' + placeholderId + '" class="fmz-upload-placeholder" contenteditable="false" style="display:inline-block;padding:4px 12px;background:var(--fmz-bg-tertiary,#e0e0e0);border-radius:4px;font-size:12px;opacity:0.7;"><i class="bi bi-cloud-arrow-up"></i> Uploading...</span>');

        var fd = new FormData();
        fd.append('image', file, file.name || ('pasted-image-' + Date.now() + '.png'));
        fd.append('my_post_key', postKeyEl.value);
        fd.append('posthash', posthashEl.value);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'xmlhttp.php?action=fmz_wysiwyg_upload', true);
        xhr.onload = function () {
            var placeholder = self.content.querySelector('#' + placeholderId);
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success && resp.aid) {
                    self._imageCount++;
                    if (placeholder) {
                        placeholder.outerHTML = '<img src="' + esc(resp.url) + '" alt="Attachment" class="fmz-attachment-img" data-aid="' + resp.aid + '" style="max-width:100%" />';
                    }
                } else {
                    if (placeholder) placeholder.outerHTML = '';
                    alert('Upload failed: ' + (resp.error || 'Unknown error'));
                }
            } catch (e) {
                if (placeholder) placeholder.outerHTML = '';
                alert('Upload failed: could not parse server response.');
            }
            self.syncToTextarea();
        };
        xhr.onerror = function () {
            var placeholder = self.content.querySelector('#' + placeholderId);
            if (placeholder) placeholder.outerHTML = '';
            alert('Upload failed: network error.');
            self.syncToTextarea();
        };
        xhr.send(fd);
    };

    FMZWysiwyg.prototype.showUploadOverlay = function () {
        if (this._overlay) return;
        this._overlay = document.createElement('div');
        this._overlay.className = 'fmz-wysiwyg-upload-overlay';
        this._overlay.innerHTML = '<i class="bi bi-cloud-arrow-up" style="font-size:32px"></i><span>Drop image here</span>';
        // Append to content area so overlay only covers the editor, not the toolbar
        this.content.appendChild(this._overlay);
    };

    FMZWysiwyg.prototype.hideUploadOverlay = function () {
        if (this._overlay) { this._overlay.remove(); this._overlay = null; }
    };

    FMZWysiwyg.prototype.saveDraft = function () {
        try {
            // Save BBCode
            localStorage.setItem(this.autoSaveKey, this.textarea.value);
            // Also save the full editor HTML so resized images retain their dimensions
            localStorage.setItem(this.autoSaveKey + '_html', this.content.innerHTML);
        } catch (e) {}
    };

    FMZWysiwyg.prototype.restoreDraft = function () {
        try {
            var draft = localStorage.getItem(this.autoSaveKey);
            var draftHtml = localStorage.getItem(this.autoSaveKey + '_html');
            if (draft && !this.textarea.value.trim()) {
                this.textarea.value = draft;
                // Prefer stored HTML (retains inline image sizes) over re-parsing BBCode
                if (draftHtml) {
                    this.content.innerHTML = draftHtml;
                } else {
                    this.content.innerHTML = bbToHtml(draft);
                }
                this.source.value = draft;
                this.showDraftStatus('Draft restored');
            }
        } catch (e) {}
    };

    FMZWysiwyg.prototype.clearDraft = function () {
        try {
            localStorage.removeItem(this.autoSaveKey);
            localStorage.removeItem(this.autoSaveKey + '_html');
        } catch (e) {}
    };

    /* ── Color scheme watching ── */

    FMZWysiwyg.prototype.watchColorScheme = function () {
        var self = this;
        // No auto mode, skip color scheme watching
        return;

        var update = function () {
            var dark = detectDarkMode(self.cfg);
            if (dark !== self.isDark) {
                self.isDark = dark;
                self.colors = getThemeColors(self.cfg, dark);
                self.applyThemeVars();
                self.wrap.classList.toggle('fmz-wysiwyg-dark', dark);
            }
        };

        if (window.matchMedia) {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            if (mq.addEventListener) mq.addEventListener('change', update);
            else if (mq.addListener) mq.addListener(update);
        }

        if (typeof MutationObserver !== 'undefined') {
            var obs = new MutationObserver(update);
            obs.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'class'] });
            obs.observe(document.body, { attributes: true, attributeFilter: ['data-theme', 'class'] });
        }
    };

    /* ═════════════════════════════════════════════════════════════
       Initialization
       ═════════════════════════════════════════════════════════════ */

    function initWysiwyg() {
        var cfgEl = document.getElementById('fmz-wysiwyg-config');
        var cfg = {};
        if (cfgEl) {
            try { cfg = JSON.parse(cfgEl.getAttribute('data-config') || '{}'); } catch (e) {}
        }

        var targets = document.querySelectorAll('textarea[name="message"], textarea[name="signature"], textarea.fmz-wysiwyg');
        var quickReplyEnabled = cfg.enable_quick_reply_editor !== '0';
        var quickReplyHeight = parseInt(cfg.quick_reply_editor_height, 10) || 150;

        targets.forEach(function (textarea) {
            // Detect if this textarea is inside the quick reply form
            var isQuickReply = !!textarea.closest('#quick_reply_form') || !!textarea.closest('#quickreply_e');

            // Skip quick reply textarea if WYSIWYG is disabled for quick reply
            if (isQuickReply && !quickReplyEnabled) {
                return;
            }

            // Destroy SCEditor if present
            if (typeof jQuery !== 'undefined' && jQuery(textarea).sceditor) {
                try {
                    var inst = jQuery(textarea).sceditor('instance');
                    if (inst) { textarea.value = inst.val(); inst.destroy(); }
                } catch (e) {}
            }

            // Build config — override height and toolbar for quick reply
            var editorCfg = cfg;
            var isMiniEditor = textarea.classList.contains('fmz-wysiwyg') && textarea.name !== 'message' && textarea.name !== 'signature';

            if (isMiniEditor) {
                // Compact config for inline editors (e.g. status updates)
                editorCfg = Object.assign({}, cfg, {
                    editor_height: 100,
                    toolbar_style: 'custom',
                    toolbar_buttons: 'bold,italic,underline,|,link,image,emoji'
                });
            } else if (isQuickReply) {
                var qrOverrides = { editor_height: quickReplyHeight };
                var qrToolbarStyle = cfg.quick_reply_toolbar_style || 'same';
                if (qrToolbarStyle !== 'same') {
                    qrOverrides.toolbar_style = qrToolbarStyle;
                    if (qrToolbarStyle === 'custom' && cfg.quick_reply_toolbar_buttons) {
                        qrOverrides.toolbar_buttons = cfg.quick_reply_toolbar_buttons;
                    }
                }
                editorCfg = Object.assign({}, cfg, qrOverrides);
            }

            textarea.style.display = 'none';
            var instance = new FMZWysiwyg(textarea, editorCfg);

            // Expose MyBBEditor shim for the main message textarea so that
            // MyBB's Thread.multiQuotedLoaded / Post.multiQuotedLoaded and
            // other core JS that calls MyBBEditor.insert() / .val() works.
            if (textarea.name === 'message' && textarea.id === 'message') {
                window.MyBBEditor = {
                    /** Insert BBCode at cursor (used by multi-quote) */
                    insert: function (bbcode) {
                        instance.insertBBCode(bbcode);
                    },
                    /** Get / set BBCode value */
                    val: function (newVal) {
                        if (typeof newVal === 'undefined') {
                            return instance.getBBCode();
                        }
                        instance.setBBCode(newVal);
                        return this;
                    },
                    /** Event binding shim — supports 'valuechanged' */
                    bind: function (event, handler) {
                        if (event === 'valuechanged') {
                            instance.content.addEventListener('input', function () {
                                handler.call(window.MyBBEditor);
                            });
                        }
                        return this;
                    },
                    /** Source mode check */
                    sourceMode: function () {
                        return instance.isSource;
                    },
                    getSourceEditorValue: function () {
                        return instance.getBBCode();
                    },
                    setSourceEditorValue: function (val) {
                        instance.setBBCode(val);
                    },
                    getWysiwygEditorValue: function () {
                        return instance.content.innerHTML;
                    },
                    setWysiwygEditorValue: function (html) {
                        instance.content.innerHTML = html;
                        instance.syncToTextarea();
                    },
                    /** Reference to the FMZWysiwyg instance */
                    _fmz: instance
                };
            }
        });

        // Initialize quick edit WYSIWYG observer
        initQuickEditWysiwyg(cfg);
    }

    /* ═════════════════════════════════════════════════════════════
       Quick Edit WYSIWYG Integration
       ═════════════════════════════════════════════════════════════ */

    function initQuickEditWysiwyg(cfg) {
        if (cfg.enable_quick_edit_editor === '0') return;

        var qeHeight = parseInt(cfg.quick_edit_editor_height, 10) || 250;
        var qeToolbarStyle = cfg.quick_edit_toolbar_style || 'same';

        // Build quick-edit specific config overrides
        function buildQEConfig() {
            var overrides = { editor_height: qeHeight, auto_save: '0' };
            if (qeToolbarStyle !== 'same') {
                overrides.toolbar_style = qeToolbarStyle;
                if (qeToolbarStyle === 'custom' && cfg.quick_edit_toolbar_buttons) {
                    overrides.toolbar_buttons = cfg.quick_edit_toolbar_buttons;
                }
            }
            return Object.assign({}, cfg, overrides);
        }

        // Use MutationObserver to detect when jeditable inserts a textarea
        // inside a .post_body element (quick edit).
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;

                    // jeditable wraps the textarea in a <form>
                    var forms = [];
                    if (node.tagName === 'FORM') {
                        forms.push(node);
                    } else if (node.querySelectorAll) {
                        forms = Array.prototype.slice.call(node.querySelectorAll('form'));
                    }

                    forms.forEach(function (form) {
                        var postBody = form.closest('.post_body');
                        if (!postBody) return;

                        var textarea = form.querySelector('textarea');
                        if (!textarea || textarea._fmzWysiwyg) return;

                        // This is a quick-edit textarea — init WYSIWYG
                        var editorCfg = buildQEConfig();
                        var instance = new FMZWysiwyg(textarea, editorCfg);

                        // Hook into jeditable's form submit to sync BBCode back
                        var origSubmit = form.onsubmit;
                        form.addEventListener('submit', function () {
                            instance.syncToTextarea();
                        }, true);

                        // Hook into jeditable's cancel/reset to destroy WYSIWYG
                        var resetObserver = new MutationObserver(function (rMuts) {
                            // When the form is removed from DOM, the edit was canceled or submitted
                            if (!document.contains(form)) {
                                if (textarea._fmzWysiwyg) {
                                    textarea._fmzWysiwyg.destroy();
                                }
                                resetObserver.disconnect();
                            }
                        });
                        resetObserver.observe(postBody, { childList: true, subtree: true });
                    });
                });
            });
        });

        // Observe all existing post bodies and any future ones
        var container = document.getElementById('posts') || document.body;
        observer.observe(container, { childList: true, subtree: true });
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initWysiwyg, 50);
    } else {
        document.addEventListener('DOMContentLoaded', function () { setTimeout(initWysiwyg, 50); });
    }

})();
