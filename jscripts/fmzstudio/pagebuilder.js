/**
 * FMZ Studio — Page Editor (HTML + Monaco)
 *
 * Simple HTML editor for FMZ pages using Monaco Editor.
 * Supports MyBB template variables, Ctrl+S save, preview,
 * auto-slug from title, and an insertable variables reference.
 *
 * @version 3.0.0
 */
(function () {
    'use strict';

    /* ═══════════════════════════════════════════════════════════
       State
       ═══════════════════════════════════════════════════════════ */

    let editor = null;   // Monaco instance
    let pid    = 0;
    let postKey = '';
    let dirty  = false;

    const $ = (sel) => document.querySelector(sel);
    const $$ = (sel) => document.querySelectorAll(sel);

    /* ═══════════════════════════════════════════════════════════
       Default page template (for new pages)
       ═══════════════════════════════════════════════════════════ */

    const DEFAULT_HTML = `{$headerinclude}
{$header}

<section>
    <h1>Page Title</h1>
    <p>Start writing your page content here.</p>
</section>

{$footer}`;

    /* ═══════════════════════════════════════════════════════════
       Monaco Initialisation
       ═══════════════════════════════════════════════════════════ */

    function initMonaco(initialContent) {
        require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.50.0/min/vs' } });

        require(['vs/editor/editor.main'], function () {
            // Define FMZ light theme
            monaco.editor.defineTheme('fmz-light', {
                base: 'vs',
                inherit: true,
                rules: [
                    { token: 'comment',    foreground: '008000' },
                    { token: 'keyword',    foreground: '0000FF' },
                    { token: 'string',     foreground: 'A31515' },
                    { token: 'tag',        foreground: '800000' },
                    { token: 'attribute.name',  foreground: 'FF0000' },
                    { token: 'attribute.value', foreground: '0451A5' },
                    { token: 'delimiter',       foreground: '333333' },
                ],
                colors: {
                    'editor.background':                '#ffffff',
                    'editor.foreground':                '#333333',
                    'editorLineNumber.foreground':      '#999999',
                    'editorLineNumber.activeForeground':'#333333',
                    'editor.selectionBackground':       '#add6ff',
                    'editor.lineHighlightBackground':   '#f5f5f5',
                    'editorCursor.foreground':          '#0d9488',
                    'editorIndentGuide.background':     '#e0e0e0',
                    'editorIndentGuide.activeBackground':'#c0c0c0',
                }
            });

            editor = monaco.editor.create($('#pb-monaco-container'), {
                value: initialContent,
                language: 'html',
                theme: 'fmz-light',
                automaticLayout: true,
                fontSize: 13,
                fontFamily: "'JetBrains Mono', 'Fira Code', 'Cascadia Code', Consolas, monospace",
                fontLigatures: true,
                lineNumbers: 'on',
                minimap: { enabled: true, scale: 1 },
                scrollBeyondLastLine: false,
                wordWrap: 'on',
                tabSize: 4,
                insertSpaces: true,
                bracketPairColorization: { enabled: true },
                guides: { bracketPairs: true, indentation: true },
                renderWhitespace: 'selection',
                smoothScrolling: true,
                cursorSmoothCaretAnimation: 'on',
                padding: { top: 12 },
            });

            // Ctrl+S to save
            editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function () {
                savePage();
            });

            // Track dirty state
            editor.onDidChangeModelContent(() => {
                dirty = true;
            });
        });
    }

    /* ═══════════════════════════════════════════════════════════
       Save Page
       ═══════════════════════════════════════════════════════════ */

    function savePage() {
        const title = $('#pb-title').value.trim();
        const slug  = $('#pb-slug').value.trim();

        if (!title) {
            showToast('Please enter a page title.', 'error');
            $('#pb-title').focus();
            return;
        }
        if (!slug) {
            showToast('Please enter a page slug.', 'error');
            $('#pb-slug').focus();
            return;
        }

        const content = editor ? editor.getValue() : '';

        // Collect allowed groups
        const groupChips = $$('#pb-groups .pb-group-chip.active');
        const allowedGroups = Array.from(groupChips).map(c => c.dataset.gid).join(',');

        const formData = new FormData();
        formData.append('my_post_key', postKey);
        formData.append('api_action', 'save');
        formData.append('pid', pid);
        formData.append('title', title);
        formData.append('slug', slug);
        formData.append('content', content);
        formData.append('status', $('#pb-status').value);
        formData.append('meta_title', $('#pb-meta-title').value);
        formData.append('meta_description', $('#pb-meta-desc').value);
        formData.append('allowed_groups', allowedGroups);
        formData.append('custom_css', $('#pb-custom-css').value);
        formData.append('custom_js', $('#pb-custom-js').value);

        const saveBtn = $('#pb-save');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';

        fetch('index.php?module=fmzstudio-pages_api&action=pages_api', {
            method: 'POST',
            body: formData,
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                showToast(data.error, 'error');
            } else {
                pid = data.pid;
                $('#pb-builder').dataset.pid = pid;
                dirty = false;
                showToast('Page saved successfully!', 'success');
                updatePermalink();
                // Update URL so future saves hit the same pid
                if (!window.location.search.includes('pid=')) {
                    history.replaceState(null, '', 'index.php?module=fmzstudio-pages_edit&action=pages_edit&pid=' + pid);
                }
            }
        })
        .catch(() => showToast('Network error. Please try again.', 'error'))
        .finally(() => {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-lg"></i> Save';
        });
    }

    /* ═══════════════════════════════════════════════════════════
       Load Page Data
       ═══════════════════════════════════════════════════════════ */

    function loadPage(data) {
        if (!data || !data.pid) return;

        pid = parseInt(data.pid) || 0;

        $('#pb-title').value         = data.title || '';
        $('#pb-slug').value          = data.slug || '';
        $('#pb-status').value        = data.status || 'draft';
        $('#pb-meta-title').value    = data.meta_title || '';
        $('#pb-meta-desc').value     = data.meta_description || '';
        $('#pb-custom-css').value    = data.custom_css || '';
        $('#pb-custom-js').value     = data.custom_js || '';

        // Allowed groups
        if (data.allowed_groups) {
            const gids = data.allowed_groups.split(',');
            gids.forEach(gid => {
                const chip = document.querySelector(`#pb-groups .pb-group-chip[data-gid="${gid}"]`);
                if (chip) chip.classList.add('active');
            });
        }

        // Content → Monaco
        const content = data.content || '';
        if (editor) {
            editor.setValue(content);
        }

        updatePermalink();
    }

    /* ═══════════════════════════════════════════════════════════
       UI Helpers
       ═══════════════════════════════════════════════════════════ */

    function updatePermalink() {
        const slug = $('#pb-slug').value.trim();
        if (slug) {
            const url = window.location.origin + '/' + slug;
            $('#pb-permalink').textContent = url;
        } else {
            $('#pb-permalink').textContent = '\u2014';
        }
    }

    function showToast(msg, type) {
        let toast = $('#pb-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'pb-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.className = 'pb-toast pb-toast-' + (type || 'info');
        toast.style.display = 'block';
        setTimeout(() => { toast.style.display = 'none'; }, 3000);
    }

    function autoSlug(title) {
        return title
            .toLowerCase()
            .replace(/[^a-z0-9\s\-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
    }

    /* ═══════════════════════════════════════════════════════════
       Groups UI
       ═══════════════════════════════════════════════════════════ */

    function initGroups() {
        const container = $('#pb-groups');
        if (!container) return;
        const groups = window.FMZ_PAGE_USERGROUPS || [];
        groups.forEach(g => {
            const chip = document.createElement('span');
            chip.className = 'pb-group-chip';
            chip.dataset.gid = g.gid;
            chip.textContent = g.title;
            chip.addEventListener('click', () => chip.classList.toggle('active'));
            container.appendChild(chip);
        });
    }

    /* ═══════════════════════════════════════════════════════════
       Variables Panel — Insert on Click
       ═══════════════════════════════════════════════════════════ */

    function initVariables() {
        $$('.pb-var-item').forEach(el => {
            el.addEventListener('click', () => {
                if (!editor) return;
                const varText = el.dataset.var;
                const selection = editor.getSelection();
                editor.executeEdits('fmz-insert-var', [{
                    range: selection,
                    text: varText,
                    forceMoveMarkers: true,
                }]);
                editor.focus();
            });
        });
    }

    /* ═══════════════════════════════════════════════════════════
       Preview
       ═══════════════════════════════════════════════════════════ */

    function openPreview() {
        const slug = $('#pb-slug').value.trim();
        if (slug && pid > 0) {
            const bburl = window.FMZ_PAGE_BBURL || '';
            window.open(bburl + '/' + slug + '?preview=1', '_blank');
        } else {
            showToast('Save the page first to preview it.', 'info');
        }
    }

    /* ═══════════════════════════════════════════════════════════
       Init
       ═══════════════════════════════════════════════════════════ */

    function init() {
        const builder = $('#pb-builder');
        if (!builder) return;

        pid = parseInt(builder.dataset.pid) || 0;
        postKey = builder.dataset.postKey || '';

        // Init groups
        initGroups();

        // Auto slug from title
        let slugManual = false;
        $('#pb-slug').addEventListener('input', () => { slugManual = true; updatePermalink(); });
        $('#pb-title').addEventListener('input', () => {
            if (!slugManual && !pid) {
                $('#pb-slug').value = autoSlug($('#pb-title').value);
                updatePermalink();
            }
        });

        // Settings toggle
        $('#pb-settings-toggle').addEventListener('click', () => {
            const panel = $('#pb-settings');
            const isOpen = panel.style.display !== 'none';
            panel.style.display = isOpen ? 'none' : 'block';
            $('#pb-settings-toggle').querySelector('i').className = isOpen ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        });

        // Variables toggle
        $('#pb-vars-toggle').addEventListener('click', () => {
            const panel = $('#pb-vars');
            const isOpen = panel.style.display !== 'none';
            panel.style.display = isOpen ? 'none' : 'block';
        });

        // Save button
        $('#pb-save').addEventListener('click', savePage);

        // Preview button
        $('#pb-preview').addEventListener('click', openPreview);

        // Warn on unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (dirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Determine initial content
        const pageData = window.FMZ_PAGE_DATA || {};
        let initialContent = DEFAULT_HTML;

        if (pageData.content) {
            const trimmed = (pageData.content || '').trim();
            // Detect legacy JSON component tree (starts with [ or { but NOT {$ which is a template variable)
            if ((trimmed.startsWith('[') || (trimmed.startsWith('{') && !trimmed.startsWith('{$')))) {
                initialContent = '<!-- This page was created with the old Page Builder.\n     The JSON component tree is preserved below.\n     Replace it with HTML to use the new editor. -->\n\n' + pageData.content;
            } else {
                initialContent = pageData.content;
            }
        }

        // Init Monaco editor
        initMonaco(initialContent);

        // Load page data into form fields
        if (pageData.pid) {
            loadPage(pageData);
        }

        // Init variable insert buttons
        initVariables();

        updatePermalink();
    }

    // Boot
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
