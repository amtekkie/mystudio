/**
 * Editor Extras — Theme-based SCEditor Enhancement
 * Self-contained in the MyStudio Default theme. No core file edits.
 *
 * Features:
 *  1. Bootstrap Icons toolbar (CSS mapping)
 *  2. Paste formatting fix
 *  3. Image paste / drag-drop upload
 *  4. Emoji picker / GIF picker
 *  5. Table support (BBCode, toolbar button, paste)
 *  6. Bootstrap Icon picker
 *  7. Code block enhancements (hljs, line numbers, copy button)
 *  8. Auto-save, word count, @mentions
 *  9. Dark/light mode sync into editor iframe
 */
(function($) {
"use strict";

var EE = window.EditorExtras || {};

/* =====================================================================
   SECTION A — CODE BLOCK ENHANCEMENTS
   Runs on ALL pages (thread view, search, etc.).
   =================================================================== */
$(function() {
	// 1) Run highlight.js if available
	if (EE.syntaxHighlight && typeof hljs !== 'undefined') {
		hljs.configure({ ignoreUnescapedHTML: true });
		// Add hljs class to bare <code> inside codeblocks so hljs picks them up
		$('.codeblock code').each(function() {
			if (!this.className) {
				$(this).addClass('hljs-code');
			}
		});
		document.querySelectorAll('.codeblock code.hljs-code, .codeblock code[class*=language-]').forEach(function(el) {
			hljs.highlightElement(el);
		});
	}

	// 2) Line numbers + copy button
	$('.codeblock').each(function() {
		var $block = $(this);
		var $codeEl = $block.find('code').first();
		var $title = $block.find('.title').first();
		if (!$codeEl.length || !$title.length) return;

		// --- Copy button ---
		var plainText = $codeEl.text();
		var $copyBtn = $('<button class="ee-code-copy" title="Copy code"><i class="bi bi-clipboard"></i></button>');
		$title.append($copyBtn);

		$copyBtn.on('click', function(e) {
			e.preventDefault();
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(plainText).then(function() {
					$copyBtn.html('<i class="bi bi-check-lg"></i>');
					setTimeout(function() { $copyBtn.html('<i class="bi bi-clipboard"></i>'); }, 2000);
				});
			} else {
				var $tmp = $('<textarea>').val(plainText).appendTo('body').select();
				document.execCommand('copy');
				$tmp.remove();
				$copyBtn.html('<i class="bi bi-check-lg"></i>');
				setTimeout(function() { $copyBtn.html('<i class="bi bi-clipboard"></i>'); }, 2000);
			}
		});

		// --- Line numbers via table ---
		var html = $codeEl.html();
		// Normalize: <br> → \n, then split
		html = html.replace(/<br\s*\/?>/gi, '\n');
		var lines = html.split('\n');
		if (lines.length > 1 && $.trim(lines[lines.length - 1]) === '') {
			lines.pop();
		}
		if (lines.length < 1) return;

		var tableHtml = '<table class="ee-code-table" cellpadding="0" cellspacing="0">';
		for (var i = 0; i < lines.length; i++) {
			var lineContent = lines[i] || '&nbsp;';
			tableHtml += '<tr>' +
				'<td class="ee-line-num">' + (i + 1) + '</td>' +
				'<td class="ee-line-code">' + lineContent + '</td>' +
				'</tr>';
		}
		tableHtml += '</table>';
		$codeEl.html(tableHtml);
	});
});


/* =====================================================================
   SECTION B — EDITOR FEATURES
   Only runs on pages with SCEditor.
   Deferred to DOM ready so SCEditor has time to load.
   =================================================================== */
$(function() {

if (typeof $.fn.sceditor === 'undefined') return;

	// Re-register table BBCode formats that MyBB removes.
	// The BBCode→HTML parser reads from the format dictionary at parse time,
	// so late registration works for the toHtml() direction (source → WYSIWYG).
	// HTML→BBCode is handled by our DOM patch in initTableEnhance().
	if (EE.table && $.sceditor && $.sceditor.formats && $.sceditor.formats.bbcode) {
		$.sceditor.formats.bbcode
			.set('table', {
				tags: { table: null },
				isInline: false,
				isHtmlInline: true,
				skipLastLineBreak: true,
				html: '<table>{0}</table>'
			})
			.set('tr', {
				tags: { tr: null },
				isInline: false,
				skipLastLineBreak: true,
				html: '<tr>{0}</tr>'
			})
			.set('td', {
				tags: { td: null },
				allowsEmpty: true,
				isInline: false,
				html: '<td>{0}</td>'
			})
			.set('th', {
				tags: { th: null },
				allowsEmpty: true,
				isInline: false,
				html: '<td>{0}</td>'
			});
	}

	if (typeof MyBBEditor !== 'undefined' && MyBBEditor) {
		var $textarea = null;
		$('textarea').each(function() {
			try {
				if ($(this).sceditor('instance') === MyBBEditor) {
					$textarea = $(this);
					return false;
				}
			} catch(e) {}
		});
		if ($textarea) {
			initEditorExtras($textarea);
			return;
		}
	}

	// Fallback: watch for SCEditor initialization
	var tries = 0;
	var checker = setInterval(function() {
		if (typeof MyBBEditor !== 'undefined' && MyBBEditor) {
			clearInterval(checker);
			$('textarea').each(function() {
				try {
					if ($(this).sceditor('instance')) {
						initEditorExtras($(this));
						return false;
					}
				} catch(e) {}
			});
			return;
		}
		if (++tries > 50) clearInterval(checker);
	}, 200);

function initEditorExtras($textarea) {
	var editor = $textarea.sceditor('instance');
	if (!editor) return;

	var $container = $textarea.nextAll('.sceditor-container').first();
	if (!$container.length) $container = $textarea.parent().find('.sceditor-container').first();
	if (!$container.length) $container = $('.sceditor-container').first();

	// Dark/light sync into iframe
	syncEditorTheme(editor);
	var observer = new MutationObserver(function() { syncEditorTheme(editor); });
	observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

	// Link/Unlink toggle + source button repositioning
	if (EE.bootstrapIcons) {
		initLinkToggle(editor, $container);
		var $toolbar = $container.find('.sceditor-toolbar');
		var $sourceBtn = $toolbar.find('.sceditor-button-source');
		if ($sourceBtn.length) {
			var $rightGroup = $('<div class="sceditor-group" style="margin-left:auto;float:right;"></div>');
			$sourceBtn.detach().appendTo($rightGroup);
			$toolbar.append($rightGroup);
		}
	}

	if (EE.pasteFix) initPasteFix(editor);
	if (EE.imageUpload) initImageUpload(editor, $container);
	if (EE.imageResize) initImageResize(editor);

	if (EE.emoji) {
		$container.find('.sceditor-button-emoticon').hide();
		initEmojiPicker(editor, $container);
	}

	if (EE.gif) initGifPicker(editor, $container);
	if (EE.table) initTableEnhance(editor, $container);
	if (EE.bootstrapIcons) initIconPicker(editor, $container);

	if (EE.mention) initMentions(editor, $container);
}

/* ── Dark/Light Mode Sync ── */
function syncEditorTheme(editor) {
	var isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
	var body = editor.getBody();
	if (!body) return;

	if (isDark) {
		body.style.backgroundColor = '#212529';
		body.style.color = '#dee2e6';
		body.style.caretColor = '#dee2e6';
	} else {
		body.style.backgroundColor = '#fff';
		body.style.color = '#333';
		body.style.caretColor = '#333';
	}

	// Inject <style> inside iframe for code blocks (existing + future)
	var doc = body.ownerDocument;
	var styleId = 'ee-theme-overrides';
	var styleEl = doc.getElementById(styleId);
	if (!styleEl) {
		styleEl = doc.createElement('style');
		styleEl.id = styleId;
		doc.head.appendChild(styleEl);
	}
	if (isDark) {
		styleEl.textContent = 'code { background: #161b22 !important; color: #e6edf3 !important; border-color: #30363d !important; }'
			+ ' blockquote { background: rgba(45,212,191,0.08) !important; border-left: 4px solid rgba(45,212,191,0.5) !important; }'
			+ ' blockquote blockquote { background: rgba(45,212,191,0.05) !important; border-left-color: rgba(45,212,191,0.3) !important; }'
			+ ' blockquote cite { color: #2dd4bf !important; border-bottom-color: #30363d !important; }';
	} else {
		styleEl.textContent = 'code { background: #f6f8fa !important; color: #1f2328 !important; border-color: #d1d9e0 !important; }'
			+ ' blockquote { background: rgba(13,148,136,0.06) !important; border-left: 4px solid rgba(13,148,136,0.5) !important; }'
			+ ' blockquote cite { color: rgba(13,148,136,1) !important; }';
	}
}

/* ── Link / Unlink Toggle ── */
function initLinkToggle(editor, $container) {
	var $linkBtn = $container.find('.sceditor-button-link');
	if (!$linkBtn.length) return;

	$linkBtn[0].addEventListener('mousedown', function(e) {
		var body = editor.getBody();
		var sel = body.ownerDocument.getSelection();
		if (!sel || !sel.rangeCount) return;

		var node = sel.anchorNode;
		var anchor = null;
		while (node && node !== body) {
			if (node.nodeType === 1 && node.tagName === 'A') { anchor = node; break; }
			node = node.parentNode;
		}

		if (anchor) {
			e.preventDefault();
			e.stopImmediatePropagation();
			var text = anchor.textContent;
			var textNode = body.ownerDocument.createTextNode(text);
			anchor.parentNode.replaceChild(textNode, anchor);
			$linkBtn.removeClass('ee-has-link');
			editor.updateOriginal();
		}
	}, true);

	$(editor.getBody()).on('mouseup keyup', function() {
		var sel = editor.getBody().ownerDocument.getSelection();
		if (!sel || !sel.rangeCount) return;
		var node = sel.anchorNode;
		var inLink = false;
		while (node && node !== editor.getBody()) {
			if (node.nodeType === 1 && node.tagName === 'A') { inLink = true; break; }
			node = node.parentNode;
		}
		$linkBtn.toggleClass('ee-has-link', inLink);
	});
}

/* ── 1. Paste Fix ── */
function initPasteFix(editor) {
	$(editor.getBody()).on('paste', function() {
		setTimeout(function() {
			var body = editor.getBody();
			$(body).find('[style]').each(function() {
				var style = this.getAttribute('style') || '';
				if (/font-size\s*:\s*(xx-small|x-small|[0-7]px|[0-7]pt)/i.test(style)) {
					this.style.fontSize = '';
				}
				// Convert modern CSS colors (lch, oklch, lab, oklab, color())
				// to hex so MyBB's [color] parser can handle them
				if (this.style.color && /lch|lab|okl|color\(/i.test(this.style.color)) {
					this.style.color = computedToHex(this);
				}
				if (this.style.backgroundColor && /lch|lab|okl|color\(/i.test(this.style.backgroundColor)) {
					this.style.backgroundColor = computedBgToHex(this);
				}
				if (EE.pasteStripStyles) {
					this.removeAttribute('style');
				}
			});
			// Also handle <font color="lch(...)"> attributes
			$(body).find('font[color]').each(function() {
				var c = this.getAttribute('color') || '';
				if (/lch|lab|okl|color\(/i.test(c)) {
					this.setAttribute('color', computedToHex(this));
				}
			});
			$(body).find('[class^="Mso"]').removeAttr('class');
			$(body).find('span').each(function() {
				if (!this.getAttribute('style') && !this.className && !this.id) {
					$(this).replaceWith(this.innerHTML);
				}
			});
			editor.updateOriginal();
		}, 50);
	});
}

// Convert an element's computed foreground color to #hex
function computedToHex(el) {
	var doc = el.ownerDocument || document;
	var w = doc.defaultView || window;
	var rgb = w.getComputedStyle(el).color;
	return rgbToHex(rgb);
}
function computedBgToHex(el) {
	var doc = el.ownerDocument || document;
	var w = doc.defaultView || window;
	var rgb = w.getComputedStyle(el).backgroundColor;
	return rgbToHex(rgb);
}
function rgbToHex(rgb) {
	if (!rgb) return '#000000';
	// getComputedStyle always returns rgb()/rgba() even for lch input
	var m = rgb.match(/rgba?\(\s*(\d+),\s*(\d+),\s*(\d+)/);
	if (!m) return '#000000';
	return '#' + ((1 << 24) + (parseInt(m[1]) << 16) + (parseInt(m[2]) << 8) + parseInt(m[3])).toString(16).slice(1);
}

/* ── 2. Image Paste & Drag-Drop ── */
function initImageUpload(editor, $container) {
	var body = editor.getBody();
	var doc = body.ownerDocument;
	var maxW = EE.imageMaxWidth || 800;

	// --- Paste: intercept image data and external-source HTML ---
	$(body).on('paste', function(e) {
		var cd = e.originalEvent.clipboardData || window.clipboardData;
		if (!cd) return;

		// Check for pasted image files (screenshots, copied images)
		if (cd.items) {
			for (var i = 0; i < cd.items.length; i++) {
				if (cd.items[i].type.indexOf('image') !== -1) {
					e.preventDefault();
					var file = cd.items[i].getAsFile();
					if (file) uploadAndInsert(editor, file);
					return;
				}
			}
		}

		// Check pasted HTML for external <img> tags
		var html = cd.getData('text/html');
		if (html) {
			var $tmp = $('<div>').html(html);
			var $imgs = $tmp.find('img[src]');
			var externalImgs = [];
			$imgs.each(function() {
				var src = $(this).attr('src') || '';
				// Skip data URIs, local attachment URLs, and empty
				if (!src || src.indexOf('data:') === 0) return;
				if (typeof mybb_url !== 'undefined' && src.indexOf(mybb_url) === 0) return;
				// Check if it's an external URL
				if (/^https?:\/\//i.test(src)) {
					externalImgs.push(src);
				}
			});
			if (externalImgs.length) {
				e.preventDefault();
				externalImgs.forEach(function(src) {
					proxyAndInsert(editor, src);
				});
				return;
			}
		}
	});

	// --- Drag overlay on container ---
	$container.on('dragover', function(e) {
		e.preventDefault(); e.stopPropagation();
		if (!$container.find('.ee-upload-overlay').length) {
			$container.css('position', 'relative').append(
				'<div class="ee-upload-overlay"><span><i class="bi bi-cloud-arrow-up"></i> Drop to upload</span></div>'
			);
		}
	});
	$container.on('dragleave', function(e) {
		e.preventDefault();
		$container.find('.ee-upload-overlay').remove();
	});
	$container.on('drop', function(e) {
		e.preventDefault(); e.stopPropagation();
		$container.find('.ee-upload-overlay').remove();
		handleDrop(e.originalEvent, editor);
	});

	// --- Drop inside iframe body ---
	$(body).on('drop', function(e) {
		var dt = e.originalEvent.dataTransfer;
		if (!dt) return;
		// If files are dropped, handle them
		if (dt.files && dt.files.length) {
			e.preventDefault();
			handleDrop(e.originalEvent, editor);
			return;
		}
		// If an image URL is dragged from another page/tab
		var url = dt.getData('text/uri-list') || dt.getData('text/plain') || '';
		if (url && /^https?:\/\/.+\.(jpe?g|png|gif|webp|bmp)/i.test(url)) {
			e.preventDefault();
			proxyAndInsert(editor, url);
		}
	});

	// --- Also intercept external images inserted via the editor's own image dialog ---
	// Watch for new img elements with external src
	var imgObserver = new MutationObserver(function(mutations) {
		mutations.forEach(function(m) {
			for (var i = 0; i < m.addedNodes.length; i++) {
				var node = m.addedNodes[i];
				if (node.nodeType === 1 && node.tagName === 'IMG') {
					maybeProxyImage(node, editor);
				}
				if (node.nodeType === 1) {
					var imgs = node.getElementsByTagName('img');
					for (var j = 0; j < imgs.length; j++) {
						maybeProxyImage(imgs[j], editor);
					}
				}
			}
		});
	});
	imgObserver.observe(body, { childList: true, subtree: true });
}

function handleDrop(e, editor) {
	var dt = e.dataTransfer;
	if (!dt || !dt.files) return;
	for (var i = 0; i < dt.files.length; i++) {
		if (dt.files[i].type.indexOf('image') !== -1) {
			uploadAndInsert(editor, dt.files[i]);
		}
	}
}

function maybeProxyImage(img, editor) {
	var src = img.getAttribute('src') || '';
	if (!src || src.indexOf('data:') === 0 || img.getAttribute('data-ee-local') === '1') return;
	// Skip local attachment URLs
	var bburl = (window.mybb_url || '');
	if (bburl && src.indexOf(bburl) === 0) return;
	if (src.indexOf('attachment.php') !== -1) return;
	if (!/^https?:\/\//i.test(src)) return;
	// Mark it so we don't re-process
	img.setAttribute('data-ee-local', '1');
	img.style.opacity = '0.5';
	proxyAndReplace(editor, img, src);
}

function proxyAndReplace(editor, imgEl, url) {
	var maxW = EE.imageMaxWidth || 800;
	$.ajax({
		url: 'xmlhttp.php',
		type: 'POST',
		data: {
			action: 'editorextras_proxy',
			my_post_key: my_post_key,
			url: url,
			posthash: $('input[name="posthash"]').val() || '',
			fid: $('input[name="fid"]').val() || '',
			tid: $('input[name="tid"]').val() || ''
		},
		dataType: 'json',
		success: function(r) {
			if (r && r.success) {
				imgEl.setAttribute('src', r.url);
				imgEl.setAttribute('data-ee-local', '1');
				if (!imgEl.getAttribute('width') || parseInt(imgEl.getAttribute('width')) > maxW) {
					imgEl.setAttribute('width', maxW);
					imgEl.removeAttribute('height');
				}
				imgEl.style.opacity = '';
				imgEl.style.maxWidth = maxW + 'px';
				if (r.posthash) updatePosthash(r.posthash);
				updateAttachmentList(r);
				editor.updateOriginal();
			} else {
				imgEl.style.opacity = '';
			}
		},
		error: function() {
			imgEl.style.opacity = '';
		}
	});
}

function proxyAndInsert(editor, url) {
	var maxW = EE.imageMaxWidth || 800;
	var placeholder = '[Uploading image...]';
	editor.insert(placeholder);

	$.ajax({
		url: 'xmlhttp.php',
		type: 'POST',
		data: {
			action: 'editorextras_proxy',
			my_post_key: my_post_key,
			url: url,
			posthash: $('input[name="posthash"]').val() || '',
			fid: $('input[name="fid"]').val() || '',
			tid: $('input[name="tid"]').val() || ''
		},
		dataType: 'json',
		success: function(r) {
			var current = editor.val();
			if (r && r.success) {
				current = current.replace(placeholder, '[img=' + maxW + 'xauto]' + r.url + '[/img]');
				if (r.posthash) updatePosthash(r.posthash);
				updateAttachmentList(r);
			} else {
				current = current.replace(placeholder, '[Upload failed: ' + (r && r.error ? r.error : 'Unknown') + ']');
			}
			editor.val(current);
		},
		error: function() {
			var current = editor.val();
			editor.val(current.replace(placeholder, '[Upload failed: Network error]'));
		}
	});
}

function uploadAndInsert(editor, file) {
	var maxW = EE.imageMaxWidth || 800;
	var filename = file.name || ('paste_' + Date.now() + '.png');
	var formData = new FormData();
	formData.append('upload', file, filename);
	formData.append('action', 'editorextras_upload');
	formData.append('my_post_key', my_post_key);
	formData.append('posthash', $('input[name="posthash"]').val() || '');
	formData.append('fid', $('input[name="fid"]').val() || '');
	formData.append('tid', $('input[name="tid"]').val() || '');

	var placeholder = '[Uploading ' + filename + '...]';
	editor.insert(placeholder);

	$.ajax({
		url: 'xmlhttp.php',
		type: 'POST',
		data: formData,
		processData: false,
		contentType: false,
		dataType: 'json',
		success: function(r) {
			var current = editor.val();
			if (r && r.success) {
				current = current.replace(placeholder, '[img=' + maxW + 'xauto]' + r.url + '[/img]');
				if (r.posthash) updatePosthash(r.posthash);
				updateAttachmentList(r);
			} else {
				current = current.replace(placeholder, '[Upload failed: ' + (r && r.error ? r.error : 'Unknown') + ']');
			}
			editor.val(current);
		},
		error: function() {
			var current = editor.val();
			editor.val(current.replace(placeholder, '[Upload failed: Network error]'));
		}
	});
}

/**
 * If the form didn't have a posthash yet (e.g. quick reply on thread page),
 * inject one so MyBB knows to associate the attachment with the post.
 */
function updatePosthash(hash) {
	var $ph = $('input[name="posthash"]');
	if ($ph.length) {
		if (!$ph.val()) $ph.val(hash);
	} else {
		$('form[method="post"]').first().append('<input type="hidden" name="posthash" value="' + hash + '" />');
	}
}

/**
 * Add the uploaded attachment to MyBB's native attachment list in the form
 * so it's properly associated with the post on submit.
 */
function updateAttachmentList(r) {
	// If the page has the native attachment container, add a hidden record
	var $container = $('#attachment_' + r.aid);
	if (!$container.length) {
		var $attachArea = $('.post_attachments, #attachments_container, .attachment_box').first();
		if ($attachArea.length) {
			$attachArea.append(
				'<div id="attachment_' + r.aid + '" class="ee-attachment-row" style="display:none;">' +
				'<input type="hidden" name="attachmentaid" value="' + r.aid + '" />' +
				'</div>'
			);
		}
	}
}

/* ── 3. Image Resize Handles ── */
function initImageResize(editor) {
	var body = editor.getBody();
	var doc = body.ownerDocument;
	var maxW = EE.imageMaxWidth || 800;
	var $overlay = null;
	var $handles = {};
	var selectedImg = null;

	// Inject resize CSS into the iframe
	var styleId = 'ee-resize-styles';
	if (!doc.getElementById(styleId)) {
		var s = doc.createElement('style');
		s.id = styleId;
		s.textContent =
			'.ee-img-selected { outline: 2px solid #0d9488; outline-offset: 2px; cursor: default; }' +
			'.ee-resize-handle { position: absolute; width: 10px; height: 10px; background: #0d9488; border: 1px solid #fff; z-index: 9999; }' +
			'.ee-resize-handle-se { cursor: nwse-resize; }' +
			'.ee-resize-handle-sw { cursor: nesw-resize; }' +
			'.ee-resize-handle-ne { cursor: nesw-resize; }' +
			'.ee-resize-handle-nw { cursor: nwse-resize; }' +
			'.ee-resize-handle-e { cursor: ew-resize; }' +
			'.ee-resize-handle-w { cursor: ew-resize; }' +
			'.ee-resize-info { position: absolute; background: rgba(0,0,0,.7); color: #fff; font-size: 11px; padding: 2px 6px; border-radius: 3px; pointer-events: none; z-index: 9999; white-space: nowrap; }';
		doc.head.appendChild(s);
	}

	// Click on image = select it
	$(body).on('mousedown', 'img', function(e) {
		if (e.button !== 0) return;
		e.preventDefault();
		selectImage(this);
	});

	// Click elsewhere = deselect
	$(body).on('mousedown', function(e) {
		if (e.target.tagName !== 'IMG' && selectedImg) {
			deselectImage();
		}
	});

	// Keyboard: delete selected image
	$(body).on('keydown', function(e) {
		if (selectedImg && (e.key === 'Delete' || e.key === 'Backspace')) {
			e.preventDefault();
			$(selectedImg).remove();
			deselectImage();
			editor.updateOriginal();
		}
	});

	function selectImage(img) {
		if (selectedImg === img) return;
		deselectImage();
		selectedImg = img;
		$(img).addClass('ee-img-selected');
		showHandles(img);
	}

	function deselectImage() {
		if (selectedImg) {
			$(selectedImg).removeClass('ee-img-selected');
			selectedImg = null;
		}
		removeHandles();
	}

	function showHandles(img) {
		removeHandles();
		var corners = ['se', 'sw', 'ne', 'nw', 'e', 'w'];
		corners.forEach(function(pos) {
			var h = doc.createElement('div');
			h.className = 'ee-resize-handle ee-resize-handle-' + pos;
			h.setAttribute('data-handle', pos);
			body.appendChild(h);
			$handles[pos] = h;
		});

		// Add size info label
		var info = doc.createElement('div');
		info.className = 'ee-resize-info';
		body.appendChild(info);
		$handles._info = info;

		positionHandles(img);
		attachHandleEvents(img);
	}

	function positionHandles(img) {
		var r = img.getBoundingClientRect();
		var scroll = { x: (doc.defaultView.pageXOffset || doc.documentElement.scrollLeft || 0), y: (doc.defaultView.pageYOffset || doc.documentElement.scrollTop || 0) };
		var t = r.top + scroll.y, l = r.left + scroll.x, w = r.width, h = r.height;
		var sz = 10, half = sz / 2;

		var positions = {
			'nw': { top: t - half, left: l - half },
			'ne': { top: t - half, left: l + w - half },
			'sw': { top: t + h - half, left: l - half },
			'se': { top: t + h - half, left: l + w - half },
			'e':  { top: t + h / 2 - half, left: l + w - half },
			'w':  { top: t + h / 2 - half, left: l - half }
		};
		for (var pos in positions) {
			if ($handles[pos]) {
				$handles[pos].style.top = positions[pos].top + 'px';
				$handles[pos].style.left = positions[pos].left + 'px';
			}
		}
		if ($handles._info) {
			$handles._info.textContent = Math.round(w) + ' × ' + Math.round(h);
			$handles._info.style.top = (t - 24) + 'px';
			$handles._info.style.left = l + 'px';
		}
	}

	function attachHandleEvents(img) {
		for (var pos in $handles) {
			if (pos === '_info') continue;
			(function(handle, pos) {
				$(handle).on('mousedown', function(e) {
					e.preventDefault();
					e.stopPropagation();
					startResize(img, pos, e);
				});
			})($handles[pos], pos);
		}
	}

	function startResize(img, handle, e) {
		var startX = e.clientX, startY = e.clientY;
		var startW = img.offsetWidth, startH = img.offsetHeight;
		var ratio = startW / startH;

		function onMove(ev) {
			var dx = ev.clientX - startX;
			var dy = ev.clientY - startY;
			var newW = startW, newH = startH;

			switch (handle) {
				case 'se': newW = startW + dx; newH = newW / ratio; break;
				case 'sw': newW = startW - dx; newH = newW / ratio; break;
				case 'ne': newW = startW + dx; newH = newW / ratio; break;
				case 'nw': newW = startW - dx; newH = newW / ratio; break;
				case 'e':  newW = startW + dx; newH = newW / ratio; break;
				case 'w':  newW = startW - dx; newH = newW / ratio; break;
			}

			newW = Math.max(30, Math.min(newW, maxW));
			newH = Math.max(30, newW / ratio);

			img.style.width = Math.round(newW) + 'px';
			img.style.height = Math.round(newH) + 'px';
			img.setAttribute('width', Math.round(newW));
			img.setAttribute('height', Math.round(newH));
			positionHandles(img);
		}

		function onUp() {
			$(doc).off('mousemove', onMove).off('mouseup', onUp);
			editor.updateOriginal();
		}

		$(doc).on('mousemove', onMove).on('mouseup', onUp);
	}

	function removeHandles() {
		for (var pos in $handles) {
			if ($handles[pos] && $handles[pos].parentNode) {
				$handles[pos].parentNode.removeChild($handles[pos]);
			}
		}
		$handles = {};
	}
}

/* ── 4. Emoji Picker ── */
var EMOJI_DATA = {
	'😀': ['😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😋','😎','🥳','😍','🥰','😘','😗','😙','😚','🤗','🤩','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','🤐','😯','😪','😫','🥱','😴','😌','😛','😜','🤪','😝','🤑','🤯','😳','🥺','😢','😭','😤','😠','😡','🤬','😈','💀','💩','🤡','👹','👻','👽','🤖'],
	'👋': ['👋','🤚','🖐','✋','🖖','👌','🤌','🤏','✌','🤞','🤟','🤘','🤙','👈','👉','👆','👇','☝','👍','👎','✊','👊','🤛','🤜','👏','🙌','🤲','🤝','🙏','💪','🦵','🦶','👂','👃','👀','👁','🧠','🫀','🫁','🦷','🦴','👅','👄'],
	'❤️': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣','💕','💞','💓','💗','💖','💘','💝','💟','🔥','⭐','🌟','✨','💫','💥','💢','💦','💨','🕊','🌈','☀️','🌙','⚡','☁️','🌊'],
	'🎉': ['🎉','🎊','🎈','🎁','🎀','🎗','🏆','🏅','🥇','🥈','🥉','⚽','🏀','🏈','⚾','🎾','🏐','🎱','🎮','🕹','🎯','🎲','🧩','🎭','🎨','🎬','🎤','🎧','🎵','🎶','🎹','🥁','🎷','🎺','🎸'],
	'🍕': ['🍕','🍔','🍟','🌭','🌮','🌯','🥗','🥘','🍝','🍜','🍲','🍛','🍣','🍱','🍤','🍙','🍚','🍘','🍥','🥮','🍡','🍧','🍨','🍦','🎂','🍰','🧁','🥧','🍮','🍭','🍬','🍫','🍿','🍩','🍪','🥛','☕','🍵','🧃','🥤','🍺','🍷','🥂','🍾'],
	'💻': ['💻','🖥','🖨','⌨','🖱','💾','💿','📱','📲','☎️','📞','📠','📺','📷','📹','🎥','📡','🔭','🔬','💡','🔦','📖','📚','📝','✏','📎','📌','📍','✂️','🗑','🔒','🔓','🔑','🔨','🛠','⚙️','🧲','🧪','🧫','🧬','💊','💉','🩺','🩹'],
	'🚗': ['🚗','🚕','🚙','🚌','🚎','🏎','🚓','🚑','🚒','🚐','🛻','🚚','🚛','🚜','🛵','🏍','🚲','🛴','🚆','🚇','🚈','🚂','🚀','✈️','🛩','🚁','🛸','🚢','⛵','🛶','🏠','🏗','🏭','🏢','🏬','🏥','🏦','🏛','⛪','🕌','🗼','🗽','🗿'],
	'🏁': ['🏁','🚩','🏴','🏳','✅','❌','❓','❗','⚠️','🛑','⛔','🚫','💯','🔴','🟠','🟡','🟢','🔵','🟣','⚫','⚪','🟤','🔶','🔷','🔸','🔹','▪️','▫️','◻️','◼️','◽','◾','⬛','⬜','🔲','🔳']
};

function initEmojiPicker(editor, $container) {
	var $toolbar = $container.find('.sceditor-toolbar');
	var $emojiBtn = $('<a class="sceditor-button sceditor-button-emoji" unselectable="on" title="Emoji Picker"><div unselectable="on"> </div></a>');
	var $emotBtn = $toolbar.find('.sceditor-button-emoticon');
	if ($emotBtn.length) $emotBtn.after($emojiBtn);
	else $toolbar.find('.sceditor-group').last().append($emojiBtn);

	var $picker = null;
	$emojiBtn.on('click', function(e) {
		e.preventDefault(); e.stopPropagation();
		if ($picker && $picker.is(':visible')) { $picker.remove(); $picker = null; return; }
		$picker = buildEmojiPicker(editor);
		$('body').append($picker);
		var btnOffset = $emojiBtn.offset();
		$picker.css({ top: btnOffset.top + $emojiBtn.outerHeight() + 4, left: Math.max(4, btnOffset.left - 120) });
		setTimeout(function() {
			$(document).one('click', function(ev) {
				if ($picker && !$(ev.target).closest('.ee-emoji-picker').length) { $picker.remove(); $picker = null; }
			});
		}, 100);
	});
}

function buildEmojiPicker(editor) {
	var categories = Object.keys(EMOJI_DATA);
	var $picker = $('<div class="ee-emoji-picker"></div>');
	var $search = $('<input type="text" class="ee-emoji-picker-search" placeholder="Search emoji..." />');
	$picker.append($search);
	var $cats = $('<div class="ee-emoji-picker-categories"></div>');
	categories.forEach(function(cat, idx) {
		var $btn = $('<button data-cat="' + idx + '">' + cat + '</button>');
		if (idx === 0) $btn.addClass('active');
		$cats.append($btn);
	});
	$picker.append($cats);
	var $grid = $('<div class="ee-emoji-picker-grid"></div>');
	$picker.append($grid);

	function showCategory(idx) {
		$grid.empty();
		EMOJI_DATA[categories[idx]].forEach(function(emoji) { $grid.append($('<span>' + emoji + '</span>')); });
		$cats.find('button').removeClass('active').filter('[data-cat="' + idx + '"]').addClass('active');
	}
	showCategory(0);
	$cats.on('click', 'button', function(e) { e.stopPropagation(); showCategory(parseInt($(this).attr('data-cat'))); });
	$grid.on('click', 'span', function(e) { e.stopPropagation(); editor.insert($(this).text()); $picker.remove(); });
	$search.on('input', function(e) {
		e.stopPropagation();
		if (!this.value.trim()) { showCategory(0); return; }
		$grid.empty();
		categories.forEach(function(cat) { EMOJI_DATA[cat].forEach(function(emoji) { $grid.append($('<span>' + emoji + '</span>')); }); });
	});
	$picker.on('click', function(e) { e.stopPropagation(); });
	return $picker;
}

/* ── 5. GIF Picker ── */
function initGifPicker(editor, $container) {
	var $toolbar = $container.find('.sceditor-toolbar');
	var $gifBtn = $('<a class="sceditor-button sceditor-button-gif" unselectable="on" title="GIF Picker"><div unselectable="on">GIF</div></a>');
	$gifBtn.find('div').css({ 'font-size':'10px','font-weight':'700','line-height':'18px','text-align':'center','font-family':'Arial,sans-serif' });
	var $emojiBtn = $toolbar.find('.sceditor-button-emoji');
	if ($emojiBtn.length) $emojiBtn.after($gifBtn);
	else $toolbar.find('.sceditor-group').last().append($gifBtn);

	var $picker = null;
	$gifBtn.on('click', function(e) {
		e.preventDefault(); e.stopPropagation();
		if ($picker && $picker.is(':visible')) { $picker.remove(); $picker = null; return; }
		$picker = buildGifPicker(editor);
		$('body').append($picker);
		var btnOffset = $gifBtn.offset();
		$picker.css({ top: btnOffset.top + $gifBtn.outerHeight() + 4, left: Math.max(4, btnOffset.left - 160) });
		loadGifs($picker, null, EE.gifProvider);
		setTimeout(function() {
			$(document).one('click', function(ev) {
				if ($picker && !$(ev.target).closest('.ee-gif-picker').length) { $picker.remove(); $picker = null; }
			});
		}, 100);
	});
}

function buildGifPicker(editor) {
	var $picker = $('<div class="ee-gif-picker"></div>');
	var $search = $('<input type="text" class="ee-gif-picker-search" placeholder="Search GIFs..." />');
	var $grid = $('<div class="ee-gif-picker-grid"><div class="ee-gif-loading">Loading...</div></div>');
	var $attr = $('<div class="ee-gif-attribution">Powered by ' + (EE.gifProvider === 'giphy' ? 'GIPHY' : 'Tenor') + '</div>');
	$picker.append($search, $grid, $attr);
	var searchTimer = null;
	$search.on('input', function() {
		var q = this.value.trim();
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function() {
			$grid.html('<div class="ee-gif-loading">Searching...</div>');
			loadGifs($picker, q || null, EE.gifProvider);
		}, 400);
	});
	$grid.on('click', 'img', function(e) { e.stopPropagation(); editor.insert('[img]' + $(this).data('full') + '[/img]'); $picker.remove(); });
	$picker.on('click', function(e) { e.stopPropagation(); });
	return $picker;
}

function loadGifs($picker, query, provider) {
	var $grid = $picker.find('.ee-gif-picker-grid');
	var action = query ? 'editorextras_gif_search' : 'editorextras_gif_trending';
	var params = { action: action };
	if (query) params.q = query;
	$.getJSON('xmlhttp.php', params, function(data) {
		$grid.empty();
		if (data.error) { $grid.html('<div class="ee-gif-loading">' + $('<span>').text(data.error).html() + '</div>'); return; }
		var results = [];
		if (provider === 'tenor' && data.results) {
			data.results.forEach(function(r) {
				var tiny = r.media_formats && r.media_formats.tinygif ? r.media_formats.tinygif.url : '';
				var full = r.media_formats && r.media_formats.gif ? r.media_formats.gif.url : tiny;
				if (tiny || full) results.push({ thumb: tiny || full, full: full || tiny });
			});
		} else if (provider === 'giphy' && data.data) {
			data.data.forEach(function(r) {
				var thumb = r.images && r.images.fixed_width_small ? r.images.fixed_width_small.url : '';
				var full = r.images && r.images.original ? r.images.original.url : thumb;
				if (thumb || full) results.push({ thumb: thumb || full, full: full || thumb });
			});
		}
		if (!results.length) { $grid.html('<div class="ee-gif-loading">No results found</div>'); return; }
		results.forEach(function(r) {
			$grid.append($('<img>').attr('src', r.thumb).attr('data-full', r.full).attr('loading', 'lazy').attr('alt', 'GIF'));
		});
	}).fail(function() { $grid.html('<div class="ee-gif-loading">Failed to load GIFs</div>'); });
}

/* ── 6. Table Support ── */
function initTableEnhance(editor, $container) {

	// MyBB removes SCEditor's built-in table BBCode formats, and the internal
	// tag→BBCode cache is built at init before we can re-add them. So we
	// handle table HTML ↔ BBCode conversion ourselves by patching the editor.

	// --- Getter: WYSIWYG → BBCode ---
	// Temporarily swap <table> elements for BBCode text nodes in the live DOM
	// BEFORE SCEditor clones and runs toSource(), then restore them.
	var origGetVal = editor.getWysiwygEditorValue.bind(editor);
	editor.getWysiwygEditorValue = function(filter) {
		var body = editor.getBody();
		var doc = body.ownerDocument;
		var tables = Array.from(body.querySelectorAll('table'));
		var saved = [];

		tables.forEach(function(tbl) {
			var bb = domTableToBBCode(tbl);
			var ph = doc.createTextNode(bb);
			tbl.parentNode.replaceChild(ph, tbl);
			saved.push({ ph: ph, tbl: tbl });
		});

		var result = origGetVal(filter);

		// Restore visual tables
		saved.forEach(function(s) {
			if (s.ph.parentNode) {
				s.ph.parentNode.replaceChild(s.tbl, s.ph);
			}
		});

		return result;
	};

	// --- Toolbar button (manual injection like emoji/gif) ---
	var $toolbar = $container.find('.sceditor-toolbar');
	var $tableBtn = $('<a class="sceditor-button sceditor-button-table" unselectable="on" title="Insert Table"><div unselectable="on"> </div></a>');
	// Insert before the last group (source button group)
	var $sourceGroup = $toolbar.find('.sceditor-button-source').closest('.sceditor-group');
	if ($sourceGroup.length) {
		var $tblGroup = $('<div class="sceditor-group"></div>').append($tableBtn);
		$sourceGroup.before($tblGroup);
	} else {
		$toolbar.find('.sceditor-group').last().append($tableBtn);
	}

	var $picker = null;
	$tableBtn.on('click', function(e) {
		e.preventDefault(); e.stopPropagation();
		if ($picker && $picker.is(':visible')) { $picker.remove(); $picker = null; return; }
		$picker = $('<div class="ee-table-picker"></div>');
		var $label = $('<div class="ee-table-picker-label">Insert Table</div>');
		var $grid = $('<div class="ee-table-picker-grid"></div>');
		var maxR = 8, maxC = 8;

		for (var r = 0; r < maxR; r++) {
			for (var c = 0; c < maxC; c++) {
				$grid.append($('<div class="ee-table-picker-cell" data-r="' + (r+1) + '" data-c="' + (c+1) + '"></div>'));
			}
		}

		$grid.on('mouseover', '.ee-table-picker-cell', function() {
			var hr = parseInt($(this).data('r'), 10);
			var hc = parseInt($(this).data('c'), 10);
			$grid.find('.ee-table-picker-cell').each(function() {
				var cr = parseInt($(this).data('r'), 10);
				var cc = parseInt($(this).data('c'), 10);
				$(this).toggleClass('highlight', cr <= hr && cc <= hc);
			});
			$label.text(hr + ' × ' + hc);
		});

		$grid.on('click', '.ee-table-picker-cell', function(ev) {
			ev.preventDefault();
			var rows = parseInt($(this).data('r'), 10);
			var cols = parseInt($(this).data('c'), 10);
			insertTable(editor, rows, cols);
			$picker.remove(); $picker = null;
		});

		$picker.append($label, $grid);
		$('body').append($picker);
		var btnOffset = $tableBtn.offset();
		$picker.css({ top: btnOffset.top + $tableBtn.outerHeight() + 4, left: Math.max(4, btnOffset.left - 60) });
		setTimeout(function() {
			$(document).one('click', function(ev) {
				if ($picker && !$(ev.target).closest('.ee-table-picker').length) { $picker.remove(); $picker = null; }
			});
		}, 100);
	});

	// --- Table editing inside the iframe ---
	var body = editor.getBody();
	var doc = body.ownerDocument;

	// Inject table styles into iframe
	var tblStyleId = 'ee-table-iframe-styles';
	if (!doc.getElementById(tblStyleId)) {
		var s = doc.createElement('style');
		s.id = tblStyleId;
		s.textContent =
			'table { border-collapse: collapse; width: 100%; margin: 8px 0; }' +
			'table td { border: 1px solid #ced4da; padding: 6px 10px; min-width: 40px; vertical-align: top; }' +
			'table td:focus { outline: 2px solid #0d9488; outline-offset: -2px; }' +
			'.ee-table-ctx { position: fixed; z-index: 99999; background: #fff; border: 1px solid #dee2e6; border-radius: 6px; box-shadow: 0 4px 16px rgba(0,0,0,.15); padding: 4px 0; min-width: 180px; }' +
			'.ee-table-ctx-item { padding: 6px 14px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 8px; }' +
			'.ee-table-ctx-item:hover { background: #f1f3f5; }' +
			'.ee-table-ctx-sep { border-top: 1px solid #dee2e6; margin: 4px 0; }';
		doc.head.appendChild(s);
	}

	// Keyboard handling inside table cells
	$(body).on('keydown', function(e) {
		// --- Tab navigation between cells ---
		if (e.key === 'Tab') {
			var node = doc.getSelection().anchorNode;
			var cell = findParent(node, ['TD','TH'], body);
			if (!cell) return;
			e.preventDefault();

			var row = cell.parentNode;
			var table = findParent(row, ['TABLE'], body);
			if (!table) return;

			var cells = Array.from(table.querySelectorAll('td, th'));
			var idx = cells.indexOf(cell);

			if (e.shiftKey) {
				// Move backward
				if (idx > 0) {
					focusCell(cells[idx - 1], doc);
				}
			} else {
				// Move forward
				if (idx < cells.length - 1) {
					focusCell(cells[idx + 1], doc);
				} else {
					// Last cell — add a new row
					var colCount = row.children.length;
					var newRow = doc.createElement('tr');
					for (var c = 0; c < colCount; c++) {
						var td = doc.createElement('td');
						td.innerHTML = '&nbsp;';
						newRow.appendChild(td);
					}
					var tbody = table.querySelector('tbody') || table;
					tbody.appendChild(newRow);
					focusCell(newRow.children[0], doc);
					editor.updateOriginal();
				}
			}
			return;
		}

		// --- Backspace on empty row → delete row ---
		if (e.key === 'Backspace') {
			var node = doc.getSelection().anchorNode;
			var cell = findParent(node, ['TD','TH'], body);
			if (!cell) return;

			// Check if ALL cells in this row are empty
			var row = cell.parentNode;
			var rowCells = row.children;
			var allEmpty = true;
			for (var i = 0; i < rowCells.length; i++) {
				var text = (rowCells[i].textContent || '').replace(/\s|\u00a0/g, '');
				if (text !== '') { allEmpty = false; break; }
			}
			if (!allEmpty) return;

			var table = findParent(row, ['TABLE'], body);
			if (!table) return;

			e.preventDefault();
			var allRows = table.querySelectorAll('tr');
			if (allRows.length <= 1) {
				// Only one row left — delete the whole table
				table.parentNode.removeChild(table);
			} else {
				// Focus cell above before deleting
				var cellIdx = Array.from(row.children).indexOf(cell);
				var prevRow = row.previousElementSibling;
				row.parentNode.removeChild(row);
				if (prevRow && prevRow.children[cellIdx]) {
					focusCell(prevRow.children[cellIdx], doc);
				}
			}
			editor.updateOriginal();
		}
	});

	// Right-click context menu on table cells
	$(body).on('contextmenu', 'td, th', function(e) {
		e.preventDefault();
		var cell = this;
		var row = cell.parentNode;
		var table = findParent(row, ['TABLE'], body);
		if (!table) return;

		removeCtxMenu(doc);
		var menu = doc.createElement('div');
		menu.className = 'ee-table-ctx';

		var items = [
			{ label: '↑ Insert Row Above', icon: 'arrow-bar-up', action: function() { insertRowAt(table, row, 'before', doc); } },
			{ label: '↓ Insert Row Below', icon: 'arrow-bar-down', action: function() { insertRowAt(table, row, 'after', doc); } },
			{ sep: true },
			{ label: '← Insert Column Left', icon: 'arrow-bar-left', action: function() { insertColAt(table, cell, 'before', doc); } },
			{ label: '→ Insert Column Right', icon: 'arrow-bar-right', action: function() { insertColAt(table, cell, 'after', doc); } },
			{ sep: true },
			{ label: '✕ Delete Row', icon: 'trash', action: function() { deleteRow(table, row); } },
			{ label: '✕ Delete Column', icon: 'trash', action: function() { deleteCol(table, cell); } },
			{ sep: true },
			{ label: '🗑 Delete Table', icon: 'x-circle', action: function() { table.parentNode.removeChild(table); } }
		];

		items.forEach(function(item) {
			if (item.sep) {
				var sep = doc.createElement('div');
				sep.className = 'ee-table-ctx-sep';
				menu.appendChild(sep);
				return;
			}
			var div = doc.createElement('div');
			div.className = 'ee-table-ctx-item';
			div.textContent = item.label;
			div.addEventListener('click', function() {
				item.action();
				removeCtxMenu(doc);
				editor.updateOriginal();
			});
			menu.appendChild(div);
		});

		menu.style.left = e.clientX + 'px';
		menu.style.top = e.clientY + 'px';
		body.appendChild(menu);

		// Close menu on click elsewhere
		setTimeout(function() {
			var closeHandler = function() {
				removeCtxMenu(doc);
				doc.removeEventListener('mousedown', closeHandler);
			};
			doc.addEventListener('mousedown', closeHandler);
		}, 50);
	});

	// Enhanced paste: intercept HTML table pastes and convert to BBCode
	$(body).on('paste', function(e) {
		var clipboardData = e.originalEvent.clipboardData;
		if (!clipboardData) return;
		var html = clipboardData.getData('text/html');
		if (html && /<table[\s>]/i.test(html)) {
			e.preventDefault();
			editor.insert(htmlTableToBBCode(html));
		}
	});

	// --- Column resize via right-edge drag ---
	var resizing = null;

	$(body).on('mousemove', 'td, th', function(e) {
		if (resizing) return;
		var rect = this.getBoundingClientRect();
		this.style.cursor = (e.clientX >= rect.right - 5) ? 'col-resize' : '';
	});

	$(body).on('mousedown', 'td, th', function(e) {
		var rect = this.getBoundingClientRect();
		if (e.clientX < rect.right - 5) return;

		e.preventDefault();
		var cell = this;
		var table = findParent(cell, ['TABLE'], body);
		if (!table) return;

		// Switch to fixed layout so widths are respected
		table.style.tableLayout = 'fixed';

		// Snapshot explicit widths on the first row so other columns stay put
		var firstRow = table.querySelector('tr');
		if (firstRow) {
			Array.from(firstRow.children).forEach(function(c) {
				if (!c.style.width) c.style.width = c.offsetWidth + 'px';
			});
		}

		var cellIdx = Array.from(cell.parentNode.children).indexOf(cell);
		resizing = { table: table, colIdx: cellIdx, startX: e.clientX, startWidth: cell.offsetWidth };
	});

	$(doc).on('mousemove', function(e) {
		if (!resizing) return;
		e.preventDefault();
		var newWidth = Math.max(40, resizing.startWidth + (e.clientX - resizing.startX));
		var firstRow = resizing.table.querySelector('tr');
		if (firstRow && firstRow.children[resizing.colIdx]) {
			firstRow.children[resizing.colIdx].style.width = newWidth + 'px';
		}
	});

	$(doc).on('mouseup', function() {
		if (resizing) {
			resizing = null;
			editor.updateOriginal();
		}
	});
}

function insertTable(editor, rows, cols) {
	var html = '<table>';
	for (var r = 0; r < rows; r++) {
		html += '<tr>';
		for (var c = 0; c < cols; c++) {
			html += '<td>\u00a0</td>';
		}
		html += '</tr>';
	}
	html += '</table><p>\u00a0</p>';
	editor.wysiwygEditorInsertHtml(html);
}

function findParent(node, tags, root) {
	while (node && node !== root) {
		if (node.nodeType === 1 && tags.indexOf(node.tagName) !== -1) return node;
		node = node.parentNode;
	}
	return null;
}

function focusCell(cell, doc) {
	cell.focus();
	var range = doc.createRange();
	range.selectNodeContents(cell);
	range.collapse(false);
	var sel = doc.getSelection();
	sel.removeAllRanges();
	sel.addRange(range);
}

function removeCtxMenu(doc) {
	var old = doc.querySelector('.ee-table-ctx');
	if (old) old.parentNode.removeChild(old);
}

function insertRowAt(table, refRow, position, doc) {
	var colCount = refRow.children.length;
	var newRow = doc.createElement('tr');
	for (var c = 0; c < colCount; c++) {
		var td = doc.createElement('td');
		td.innerHTML = '&nbsp;';
		newRow.appendChild(td);
	}
	if (position === 'before') {
		refRow.parentNode.insertBefore(newRow, refRow);
	} else {
		refRow.parentNode.insertBefore(newRow, refRow.nextSibling);
	}
}

function insertColAt(table, refCell, position, doc) {
	var cellIdx = Array.from(refCell.parentNode.children).indexOf(refCell);
	var rows = table.querySelectorAll('tr');
	rows.forEach(function(row) {
		var cells = row.children;
		var newCell = doc.createElement('td');
		newCell.innerHTML = '&nbsp;';
		if (position === 'before') {
			row.insertBefore(newCell, cells[cellIdx] || null);
		} else {
			row.insertBefore(newCell, cells[cellIdx + 1] || null);
		}
	});
}

function deleteRow(table, row) {
	if (table.querySelectorAll('tr').length <= 1) {
		table.parentNode.removeChild(table);
		return;
	}
	row.parentNode.removeChild(row);
}

function deleteCol(table, refCell) {
	var cellIdx = Array.from(refCell.parentNode.children).indexOf(refCell);
	var rows = table.querySelectorAll('tr');
	if (rows[0] && rows[0].children.length <= 1) {
		table.parentNode.removeChild(table);
		return;
	}
	rows.forEach(function(row) {
		if (row.children[cellIdx]) {
			row.removeChild(row.children[cellIdx]);
		}
	});
}

function htmlTableToBBCode(html) {
	var $tmp = $('<div>').html(html);
	var $table = $tmp.find('table').first();
	if (!$table.length) return '';
	var bbcode = '[table]\n';
	$table.find('tr').each(function() {
		bbcode += '[tr]';
		$(this).find('td, th').each(function() {
			bbcode += '[td]' + $(this).text().trim() + '[/td]';
		});
		bbcode += '[/tr]\n';
	});
	bbcode += '[/table]';
	return bbcode;
}

// Convert a live DOM <table> element to BBCode string
function domTableToBBCode(table) {
	var bb = '\n[table]\n';
	var rows = table.querySelectorAll('tr');
	for (var i = 0; i < rows.length; i++) {
		bb += '[tr]';
		var cells = rows[i].children;
		for (var j = 0; j < cells.length; j++) {
			var tag = cells[j].tagName;
			if (tag === 'TD' || tag === 'TH') {
				var text = cells[j].textContent.replace(/\u00a0/g, ' ').trim();
				bb += '[td]' + text + '[/td]';
			}
		}
		bb += '[/tr]\n';
	}
	bb += '[/table]\n';
	return bb;
}

/* ── 7. Bootstrap Icon Picker ── */
function initIconPicker(editor, $container) {
	var icons = [
		'house','person','gear','envelope','bell','chat','search','star','heart','hand-thumbs-up',
		'hand-thumbs-down','check-circle','x-circle','exclamation-triangle','info-circle','question-circle',
		'lock','unlock','key','shield-check','eye','eye-slash','pencil','trash','folder','file-earmark',
		'download','upload','cloud','link-45deg','pin','bookmark','flag','tag','calendar','clock',
		'alarm','globe','map','geo-alt','telephone','camera','image','film','music-note','volume-up',
		'mic','play-circle','pause-circle','stop-circle','lightning','fire','droplet','sun','moon','snow',
		'trophy','award','gift','bag','cart','credit-card','wallet','cash','percent','graph-up',
		'bar-chart','pie-chart','speedometer','cpu','database','hdd','terminal','code-slash','bug','tools',
		'wrench','hammer','palette','brush','rulers','grid','layers','columns','layout-text-window',
		'box','archive','clipboard','journal','book','mortarboard','lightbulb','rocket','airplane',
		'truck','bicycle','ev-front','fuel-pump','building','hospital','shop','bank','tree','flower1',
		'emoji-smile','emoji-frown','emoji-heart-eyes','emoji-laughing','emoji-neutral','people',
		'person-plus','person-check','person-x','chat-dots','chat-left-text','megaphone','broadcast',
		'rss','wifi','bluetooth','usb','printer','qr-code','fingerprint','shield-lock','patch-check',
		'arrow-up','arrow-down','arrow-left','arrow-right','chevron-up','chevron-down','chevron-left',
		'chevron-right','plus','dash','x-lg','check-lg','three-dots','list','grid-3x3-gap',
		'share','box-arrow-up-right','clipboard-check','signpost','compass','cursor','hand-index',
		'joystick','controller','headset','smartwatch','phone','laptop','display','tablet','mouse',
		'keyboard','earbuds','speaker','camera-video','projector','cast','router','modem'
	];

	// Manual toolbar button injection (same pattern as emoji/gif)
	var $toolbar = $container.find('.sceditor-toolbar');
	var $iconBtn = $('<a class="sceditor-button sceditor-button-biicon" unselectable="on" title="Insert Icon"><div unselectable="on"> </div></a>');
	var $sourceGroup = $toolbar.find('.sceditor-button-source').closest('.sceditor-group');
	if ($sourceGroup.length) {
		var $iconGroup = $('<div class="sceditor-group"></div>').append($iconBtn);
		$sourceGroup.before($iconGroup);
	} else {
		$toolbar.find('.sceditor-group').last().append($iconBtn);
	}

	var $picker = null;
	$iconBtn.on('click', function(e) {
		e.preventDefault(); e.stopPropagation();
		if ($picker && $picker.is(':visible')) { $picker.remove(); $picker = null; return; }

		$picker = $('<div class="ee-icon-picker"></div>');
		var $search = $('<input type="text" class="ee-icon-picker-search" placeholder="Search icons..." />');
		var $grid = $('<div class="ee-icon-picker-grid"></div>');

		function renderIcons(filter) {
			$grid.empty();
			var filtered = filter ? icons.filter(function(n) { return n.indexOf(filter) !== -1; }) : icons;
			filtered.forEach(function(name) {
				var $btn = $('<button type="button" class="ee-icon-picker-item" title="' + name + '"><i class="bi bi-' + name + '"></i></button>');
				$btn.on('click', function(ev) {
					ev.preventDefault(); ev.stopPropagation();
					editor.insert('[icon]' + name + '[/icon]');
					$picker.remove(); $picker = null;
				});
				$grid.append($btn);
			});
			if (!filtered.length) {
				$grid.append('<div class="ee-icon-picker-empty">No icons found</div>');
			}
		}

		var searchTimer = null;
		$search.on('input', function() {
			var q = this.value.trim().toLowerCase();
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function() { renderIcons(q); }, 150);
		});

		renderIcons(null);
		$picker.append($search, $grid);
		$('body').append($picker);
		var btnOffset = $iconBtn.offset();
		$picker.css({ top: btnOffset.top + $iconBtn.outerHeight() + 4, left: Math.max(4, btnOffset.left - 120) });
		$search.focus();

		setTimeout(function() {
			$(document).one('click', function(ev) {
				if ($picker && !$(ev.target).closest('.ee-icon-picker').length) { $picker.remove(); $picker = null; }
			});
		}, 100);
		$picker.on('click', function(ev) { ev.stopPropagation(); });
	});
}



/* ── 10. @Mentions ── */
function initMentions(editor, $container) {
	var body = editor.getBody();
	var $dropdown = null;
	var searchTimer = null;

	$(body).on('keyup', function(e) {
		if (e.key === 'Escape' && $dropdown) { closeMentionDropdown(); return; }
		var sel = body.ownerDocument.getSelection();
		if (!sel || !sel.rangeCount) return;
		var range = sel.getRangeAt(0);
		var textNode = range.startContainer;
		if (textNode.nodeType !== 3) return;
		var text = textNode.textContent.substring(0, range.startOffset);
		var atIdx = text.lastIndexOf('@');
		if (atIdx === -1 || (atIdx > 0 && /\S/.test(text.charAt(atIdx - 1)))) { closeMentionDropdown(); return; }
		var query = text.substring(atIdx + 1);
		if (query.length < 2) { closeMentionDropdown(); return; }
		clearTimeout(searchTimer);
		searchTimer = setTimeout(function() { searchUsers(query, editor, $container, textNode, atIdx, range.startOffset); }, 250);
	});

	function searchUsers(query, editor, $container, textNode, atIdx, cursorOffset) {
		$.getJSON('xmlhttp.php', { action: 'get_users', query: query }, function(data) {
			if (!data || !data.length) { closeMentionDropdown(); return; }
			showMentionDropdown(data, editor, $container, textNode, atIdx, cursorOffset);
		});
	}

	function showMentionDropdown(users, editor, $container, textNode, atIdx, cursorOffset) {
		closeMentionDropdown();
		$dropdown = $('<div class="ee-mention-dropdown"></div>');
		users.forEach(function(user, idx) {
			var displayName = $('<span>').text(user.text).html();
			var $item = $('<div class="ee-mention-item' + (idx === 0 ? ' active' : '') + '">' + displayName + '</div>');
			$item.on('click', function(e) { e.stopPropagation(); insertMention(user.text, editor, textNode, atIdx, cursorOffset); });
			$dropdown.append($item);
		});
		$('body').append($dropdown);
		var containerOffset = $container.offset();
		var sel = body.ownerDocument.getSelection();
		if (sel && sel.rangeCount) {
			var rect = sel.getRangeAt(0).getBoundingClientRect();
			var iframeOffset = $container.find('iframe').offset() || containerOffset;
			$dropdown.css({ top: iframeOffset.top + rect.bottom + 4, left: iframeOffset.left + rect.left });
		}
		setTimeout(function() {
			$(document).one('click', function() { closeMentionDropdown(); });
		}, 100);
	}

	function insertMention(username, editor, textNode, atIdx, cursorOffset) {
		closeMentionDropdown();
		var before = textNode.textContent.substring(0, atIdx);
		var after = textNode.textContent.substring(cursorOffset);
		textNode.textContent = before + '@' + username + ' ' + after;
		var range = body.ownerDocument.createRange();
		var sel = body.ownerDocument.getSelection();
		var newPos = atIdx + username.length + 2;
		range.setStart(textNode, Math.min(newPos, textNode.textContent.length));
		range.collapse(true);
		sel.removeAllRanges();
		sel.addRange(range);
		editor.updateOriginal();
	}

	function closeMentionDropdown() {
		if ($dropdown) { $dropdown.remove(); $dropdown = null; }
	}
}

}); // end editor features $(function)

})(jQuery);
