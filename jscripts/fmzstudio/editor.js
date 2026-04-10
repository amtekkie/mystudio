/**
 * FMZ Studio Editor — Monaco-based theme file editor
 *
 * Provides a VS Code-like editing experience inside MyBB Admin CP:
 *   • File tree with expand/collapse and search across ALL files
 *   • Right-click context menu (New File, New Folder, Rename, Delete)
 *   • Sidebar buttons for New File, New Folder, Sync
 *   • Tabbed editing with dirty indicators
 *   • Syntax highlighting for HTML, CSS, JS, JSON, PHP
 *   • Emmet abbreviations (HTML & CSS)
 *   • Auto-formatting, bracket matching, minimap
 *   • Ctrl+S saves & syncs the file to the MyBB database instantly
 *   • Resizable sidebar
 *
 * @version 2.1.0
 */
(function () {
    'use strict';

    /* ═══════════════════════════════════════════════
       Configuration
       ═══════════════════════════════════════════════ */

    var CONFIG = {
        monacoVersion: '0.50.0',
        baseUrl: '',        // set from PHP data attribute
        postKey: '',        // CSRF token
        editorTheme: 'vs-dark'
    };

    var LANG_MAP = {
        html: 'html', htm: 'html',
        css: 'css',
        js: 'javascript',
        json: 'json',
        php: 'php',
        xml: 'xml',
        md: 'markdown',
        txt: 'plaintext',
        svg: 'xml',
        yml: 'yaml', yaml: 'yaml'
    };

    var ICON_MAP = {
        html: '<i class="bi bi-filetype-html" style="color:#e44d26"></i>',
        htm:  '<i class="bi bi-filetype-html" style="color:#e44d26"></i>',
        css:  '<i class="bi bi-filetype-css" style="color:#2965f1"></i>',
        js:   '<i class="bi bi-filetype-js" style="color:#f0db4f"></i>',
        json: '<i class="bi bi-filetype-json" style="color:#5bb98b"></i>',
        php:  '<i class="bi bi-filetype-php" style="color:#777bb3"></i>',
        xml:  '<i class="bi bi-filetype-xml" style="color:#f1662a"></i>',
        svg:  '<i class="bi bi-filetype-svg" style="color:#ffb13b"></i>',
        png:  '<i class="bi bi-file-earmark-image" style="color:#26a69a"></i>',
        jpg:  '<i class="bi bi-file-earmark-image" style="color:#26a69a"></i>',
        gif:  '<i class="bi bi-file-earmark-image" style="color:#26a69a"></i>',
        webp: '<i class="bi bi-file-earmark-image" style="color:#26a69a"></i>',
        ico:  '<i class="bi bi-file-earmark-image" style="color:#26a69a"></i>',
        txt:  '<i class="bi bi-file-earmark-text" style="color:#999"></i>',
        md:   '<i class="bi bi-markdown" style="color:#519aba"></i>',
        lang: '<i class="bi bi-translate" style="color:#ce9178"></i>',
        directory:      '<i class="bi bi-folder-fill" style="color:#dcb67a"></i>',
        'directory-open': '<i class="bi bi-folder2-open" style="color:#e8c97a"></i>',
        _default: '<i class="bi bi-file-earmark" style="color:#888"></i>'
    };

    /* ═══════════════════════════════════════════════
       State
       ═══════════════════════════════════════════════ */

    var state = {
        editor: null,           // Monaco editor instance
        currentSlug: null,      // Selected theme slug
        openTabs: [],           // [{path, name, model, viewState, isDirty, originalContent}]
        activeTab: null,        // path of the active tab
        fileTree: null,         // cached tree data
        flatFiles: [],          // flat list of all file paths (for search)
        contextTarget: null     // {path, type} for right-click context menu
    };

    /* ═══════════════════════════════════════════════
       Bootstrap
       ═══════════════════════════════════════════════ */

    function init() {
        var cfgEl = document.getElementById('fmzEditorConfig');
        if (!cfgEl) return;

        CONFIG.baseUrl = cfgEl.getAttribute('data-base-url');
        CONFIG.postKey = cfgEl.getAttribute('data-post-key');
        CONFIG.slug    = cfgEl.getAttribute('data-slug') || '';

        setupMonaco();
        bindEvents();
        setupResizeHandle();

        // Right-click on tree background → root-level actions
        var treeEl = document.getElementById('fmz-file-tree');
        if (treeEl) {
            treeEl.addEventListener('contextmenu', function (e) {
                if (e.target === treeEl || e.target.closest('.fmz-file-tree') === treeEl && !e.target.closest('.fmz-tree-item')) {
                    e.preventDefault();
                    if (state.currentSlug) {
                        showContextMenu(e.clientX, e.clientY, '', 'directory');
                    }
                }
            });
        }

        // Auto-load the theme from data attribute (no dropdown)
        if (CONFIG.slug) {
            loadThemeTree(CONFIG.slug);
        }
    }

    /* ═══════════════════════════════════════════════
       Monaco Setup
       ═══════════════════════════════════════════════ */

    function setupMonaco() {
        // AMD loader from CDN is already in the page
        if (typeof require === 'undefined' || typeof require.config === 'undefined') {
            console.error('FMZ Editor: Monaco loader not found');
            return;
        }

        require.config({
            paths: {
                vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@' + CONFIG.monacoVersion + '/min/vs'
            }
        });

        require(['vs/editor/editor.main'], function () {

            // Custom light theme with teal accent
            monaco.editor.defineTheme('fmz-light', {
                base: 'vs',
                inherit: true,
                rules: [],
                colors: {
                    'editor.background': '#ffffff',
                    'editorCursor.foreground': '#0d9488',
                    'editor.selectionBackground': '#add6ff',
                    'editor.lineHighlightBackground': '#f5f5f5'
                }
            });

            state.editor = monaco.editor.create(document.getElementById('fmz-monaco'), {
                theme: 'fmz-light',
                automaticLayout: true,
                minimap: { enabled: true, maxColumn: 80 },
                fontSize: 14,
                fontFamily: "'Cascadia Code', 'Fira Code', 'JetBrains Mono', Consolas, 'Courier New', monospace",
                fontLigatures: true,
                tabSize: 4,
                insertSpaces: true,
                wordWrap: 'on',
                formatOnPaste: true,
                formatOnType: true,
                renderWhitespace: 'selection',
                bracketPairColorization: { enabled: true },
                guides: { bracketPairs: true, indentation: true },
                smoothScrolling: true,
                cursorSmoothCaretAnimation: 'on',
                cursorBlinking: 'smooth',
                padding: { top: 10 },
                suggest: { showWords: true, showSnippets: true },
                quickSuggestions: { other: true, comments: false, strings: true },
                linkedEditing: true,
                autoClosingBrackets: 'always',
                autoClosingQuotes: 'always',
                colorDecorators: true
            });

            // Clear the placeholder
            var placeholder = document.querySelector('.fmz-monaco-placeholder');
            if (placeholder) placeholder.style.display = 'none';

            // ── Keyboard shortcuts ──
            // Ctrl+S → Save
            state.editor.addCommand(
                monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS,
                function () { saveCurrentFile(); }
            );

            // Ctrl+W → Close tab
            state.editor.addCommand(
                monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyW,
                function () { if (state.activeTab) closeTab(state.activeTab); }
            );

            // Track cursor for status bar
            state.editor.onDidChangeCursorPosition(function () {
                updateStatusBar();
            });

            // Track dirty state
            state.editor.onDidChangeModelContent(function () {
                if (!state.activeTab) return;
                var tab = findTab(state.activeTab);
                if (tab && !tab.isDirty) {
                    tab.isDirty = true;
                    renderTabs();
                    updateDirtyIndicators();
                }
            });

            // Load Emmet support
            loadEmmet();
            updateStatusBar();
        });
    }

    function loadEmmet() {
        try {
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/emmet-monaco-es@latest/dist/emmet-monaco.min.js';
            script.onload = function () {
                if (typeof window.emmetMonaco !== 'undefined') {
                    window.emmetMonaco.emmetHTML(monaco);
                    window.emmetMonaco.emmetCSS(monaco);
                }
            };
            document.head.appendChild(script);
        } catch (e) {
            console.warn('FMZ Editor: Emmet could not be loaded', e);
        }
    }

    /* ═══════════════════════════════════════════════
       Event Binding
       ═══════════════════════════════════════════════ */

    function bindEvents() {
        // File search filter
        var search = document.getElementById('fmz-file-search');
        if (search) {
            search.addEventListener('input', function () {
                filterFileTree(this.value);
            });
        }

        // Global Ctrl+S prevention (browser save dialog)
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                if (state.editor && state.activeTab) saveCurrentFile();
            }
        });

        // Close context menu on click elsewhere
        document.addEventListener('click', function () {
            closeContextMenu();
        });

        // Sidebar buttons
        var btnNewFile = document.getElementById('fmz-btn-newfile');
        if (btnNewFile) {
            btnNewFile.addEventListener('click', function () {
                if (!state.currentSlug) return;
                promptCreateFile('');
            });
        }

        var btnNewFolder = document.getElementById('fmz-btn-newfolder');
        if (btnNewFolder) {
            btnNewFolder.addEventListener('click', function () {
                if (!state.currentSlug) return;
                promptCreateFolder('');
            });
        }

        // Save & Sync button
        var btnSaveSync = document.getElementById('fmz-btn-savesync');
        if (btnSaveSync) {
            btnSaveSync.addEventListener('click', function () {
                if (!state.currentSlug) return;
                saveCurrentFile();
            });
        }

        // Collapse All Folders button
        var btnCollapseAll = document.getElementById('fmz-btn-collapse-all');
        if (btnCollapseAll) {
            btnCollapseAll.addEventListener('click', function () {
                collapseAllFolders();
            });
        }

        // Sidebar collapse toggle
        var btnCollapse = document.getElementById('fmz-btn-collapse');
        if (btnCollapse) {
            btnCollapse.addEventListener('click', function () {
                var sidebar = document.getElementById('fmz-sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('collapsed');
                    var icon = btnCollapse.querySelector('i');
                    if (icon) {
                        if (sidebar.classList.contains('collapsed')) {
                            icon.className = 'bi bi-layout-sidebar';
                        } else {
                            icon.className = 'bi bi-layout-sidebar-inset';
                        }
                    }
                    if (state.editor) state.editor.layout();
                }
            });
        }
    }

    /* ═══════════════════════════════════════════════
       Sidebar Resize
       ═══════════════════════════════════════════════ */

    function setupResizeHandle() {
        var handle  = document.getElementById('fmz-resize-handle');
        var sidebar = document.getElementById('fmz-sidebar');
        if (!handle || !sidebar) return;

        var startX, startW;

        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            startX = e.clientX;
            startW = sidebar.offsetWidth;
            handle.classList.add('active');

            function onMove(e2) {
                var newW = startW + (e2.clientX - startX);
                if (newW >= 160 && newW <= 600) {
                    sidebar.style.width = newW + 'px';
                }
            }
            function onUp() {
                handle.classList.remove('active');
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
                if (state.editor) state.editor.layout();
            }

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    }

    /* ═══════════════════════════════════════════════
       File Tree
       ═══════════════════════════════════════════════ */

    function loadThemeTree(slug) {
        state.currentSlug = slug;
        var treeEl = document.getElementById('fmz-file-tree');
        treeEl.innerHTML = '<div class="fmz-loading">Loading…</div>';

        // Fetch tree + flat file list in parallel
        Promise.all([
            fetch(CONFIG.baseUrl + '&action=api_filetree&slug=' + encodeURIComponent(slug)).then(function (r) { return r.json(); }),
            fetch(CONFIG.baseUrl + '&action=api_filelist&slug=' + encodeURIComponent(slug)).then(function (r) { return r.json(); })
        ]).then(function (results) {
            var data     = results[0];
            var fileData = results[1];

            if (data.error) {
                treeEl.innerHTML = '<div class="fmz-error">' + escapeHtml(data.error) + '</div>';
                return;
            }
            state.fileTree = data;
            state.flatFiles = (fileData && fileData.files) ? fileData.files : [];
            treeEl.innerHTML = '';
            renderChildren(data.children || [], treeEl, '');
        }).catch(function (err) {
            treeEl.innerHTML = '<div class="fmz-error">Failed: ' + escapeHtml(err.message) + '</div>';
        });
    }

    function renderChildren(children, container, parentPath) {
        children.forEach(function (child) {
            var el = document.createElement('div');
            var childPath = parentPath ? parentPath + '/' + child.name : child.name;

            if (child.type === 'directory') {
                el.className = 'fmz-tree-dir';
                el.innerHTML =
                    '<div class="fmz-tree-item fmz-tree-folder" data-path="' + escapeAttr(childPath) + '" data-type="directory">' +
                    '  <span class="fmz-tree-arrow"><i class="bi bi-chevron-right"></i></span>' +
                    '  <span class="fmz-tree-icon">' + ICON_MAP.directory + '</span>' +
                    '  <span class="fmz-tree-name">' + escapeHtml(child.name) + '</span>' +
                    '  <span class="fmz-tree-folder-dirty" style="display:none">M</span>' +
                    '</div>' +
                    '<div class="fmz-tree-children" style="display:none"></div>';

                var header = el.querySelector('.fmz-tree-item');
                var childrenBox = el.querySelector('.fmz-tree-children');
                var rendered = false;

                // Right-click context menu
                (function (cPath) {
                    header.addEventListener('contextmenu', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        showContextMenu(e.clientX, e.clientY, cPath, 'directory');
                    });
                })(childPath);

                (function (ch, cBox, cPath) {
                    header.addEventListener('click', function () {
                        var arrow = this.querySelector('.fmz-tree-arrow');
                        var icon  = this.querySelector('.fmz-tree-icon');
                        var isOpen = cBox.style.display !== 'none';

                        if (isOpen) {
                            cBox.style.display = 'none';
                            arrow.innerHTML = '<i class="bi bi-chevron-right"></i>';
                            icon.innerHTML  = ICON_MAP.directory;
                        } else {
                            cBox.style.display = 'block';
                            arrow.innerHTML = '<i class="bi bi-chevron-down"></i>';
                            icon.innerHTML  = ICON_MAP['directory-open'];
                            if (!rendered && ch.children && ch.children.length) {
                                renderChildren(ch.children, cBox, cPath);
                                rendered = true;
                                updateDirtyIndicators();
                            }
                        }
                    });
                })(child, childrenBox, childPath);

            } else {
                el.className = 'fmz-tree-file';
                var icon = ICON_MAP[child.ext] || ICON_MAP._default;
                el.innerHTML =
                    '<div class="fmz-tree-item fmz-tree-file-item" ' +
                    'data-path="' + escapeAttr(childPath) + '" data-type="file" data-ext="' + (child.ext || '') + '">' +
                    '  <span class="fmz-tree-icon">' + icon + '</span>' +
                    '  <span class="fmz-tree-name">' + escapeHtml(child.name) + '</span>' +
                    '  <span class="fmz-tree-dirty" style="display:none">\u25CF</span>' +
                    '</div>';

                (function (path) {
                    var item = el.querySelector('.fmz-tree-item');
                    item.addEventListener('click', function () {
                        openFile(path);
                    });
                    item.addEventListener('contextmenu', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        showContextMenu(e.clientX, e.clientY, path, 'file');
                    });
                })(childPath);
            }

            container.appendChild(el);
        });
    }

    function filterFileTree(query) {
        var treeEl = document.getElementById('fmz-file-tree');
        var q = query.toLowerCase().trim();

        // No query — restore normal tree view
        if (!q) {
            if (state.fileTree) {
                treeEl.innerHTML = '';
                renderChildren(state.fileTree.children || [], treeEl, '');
            }
            return;
        }

        // Search against the flat file list (all files, even in collapsed dirs)
        var matches = [];
        for (var i = 0; i < state.flatFiles.length; i++) {
            var filePath = state.flatFiles[i];
            if (filePath.toLowerCase().indexOf(q) !== -1) {
                matches.push(filePath);
            }
        }

        // Render search results as a flat clickable list
        treeEl.innerHTML = '';
        if (matches.length === 0) {
            treeEl.innerHTML = '<div style="padding:12px;color:#888;font-size:12px">No files matching "' + escapeHtml(query) + '"</div>';
            return;
        }

        matches.forEach(function (filePath) {
            var ext  = filePath.split('.').pop().toLowerCase();
            var icon = ICON_MAP[ext] || ICON_MAP._default;
            var name = filePath.split('/').pop();
            var dir  = filePath.substring(0, filePath.length - name.length);

            var el = document.createElement('div');
            el.className = 'fmz-tree-file';
            el.innerHTML =
                '<div class="fmz-tree-item fmz-tree-file-item" data-path="' + escapeAttr(filePath) + '" data-type="file">' +
                '  <span class="fmz-tree-icon">' + icon + '</span>' +
                '  <span class="fmz-tree-name">' + escapeHtml(name) +
                '    <span style="color:#666;font-size:11px;margin-left:6px">' + escapeHtml(dir) + '</span>' +
                '  </span>' +
                '</div>';

            (function (p) {
                el.querySelector('.fmz-tree-item').addEventListener('click', function () {
                    openFile(p);
                });
            })(filePath);

            treeEl.appendChild(el);
        });
    }

    /* ═══════════════════════════════════════════════
       Collapse All Folders
       ═══════════════════════════════════════════════ */

    function collapseAllFolders() {
        var treeEl = document.getElementById('fmz-file-tree');
        if (!treeEl) return;

        // Find all expanded folder children containers and collapse them
        var childrenBoxes = treeEl.querySelectorAll('.fmz-tree-children');
        for (var i = 0; i < childrenBoxes.length; i++) {
            childrenBoxes[i].style.display = 'none';
        }

        // Reset all folder arrows and icons
        var folderItems = treeEl.querySelectorAll('.fmz-tree-folder');
        for (var j = 0; j < folderItems.length; j++) {
            var arrow = folderItems[j].querySelector('.fmz-tree-arrow');
            var icon  = folderItems[j].querySelector('.fmz-tree-icon');
            if (arrow) arrow.innerHTML = '<i class="bi bi-chevron-right"></i>';
            if (icon)  icon.innerHTML  = ICON_MAP.directory;
        }
    }

    /* ═══════════════════════════════════════════════
       File Operations
       ═══════════════════════════════════════════════ */

    function openFile(path) {
        // Already open?
        var existing = findTab(path);
        if (existing) { switchToTab(path); return; }

        // Only allow text files
        var ext  = path.split('.').pop().toLowerCase();
        var lang = LANG_MAP[ext] || 'plaintext';

        setTreeItemActive(path);

        fetch(CONFIG.baseUrl + '&action=api_readfile&slug=' + encodeURIComponent(state.currentSlug)
                             + '&path=' + encodeURIComponent(path))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) { showNotification(data.error, 'error'); return; }

                // Create Monaco model
                var uri   = monaco.Uri.parse('file:///' + state.currentSlug + '/' + path);
                var model = monaco.editor.getModel(uri);
                if (model) {
                    model.setValue(data.content);
                } else {
                    model = monaco.editor.createModel(data.content, lang, uri);
                }

                var tab = {
                    path: path,
                    name: path.split('/').pop(),
                    model: model,
                    viewState: null,
                    isDirty: false,
                    originalContent: data.content
                };

                state.openTabs.push(tab);
                switchToTab(path);
                renderTabs();
            })
            .catch(function (err) {
                showNotification('Failed to load: ' + err.message, 'error');
            });
    }

    function switchToTab(path) {
        // Save current view state
        if (state.activeTab && state.editor) {
            var current = findTab(state.activeTab);
            if (current) {
                current.viewState = state.editor.saveViewState();
            }
        }

        state.activeTab = path;
        var tab = findTab(path);
        if (tab && state.editor) {
            // Hide placeholder if still visible
            var ph = document.querySelector('.fmz-monaco-placeholder');
            if (ph) ph.style.display = 'none';

            state.editor.setModel(tab.model);
            if (tab.viewState) state.editor.restoreViewState(tab.viewState);
            state.editor.focus();
        }

        setTreeItemActive(path);
        renderTabs();
        updateStatusBar();
    }

    function closeTab(path, evt) {
        if (evt) evt.stopPropagation();

        var idx = -1;
        for (var i = 0; i < state.openTabs.length; i++) {
            if (state.openTabs[i].path === path) { idx = i; break; }
        }
        if (idx === -1) return;

        var tab = state.openTabs[idx];
        if (tab.isDirty && !confirm('"' + tab.name + '" has unsaved changes. Close anyway?')) return;

        if (tab.model) tab.model.dispose();
        state.openTabs.splice(idx, 1);

        if (state.activeTab === path) {
            if (state.openTabs.length > 0) {
                var nextIdx = Math.min(idx, state.openTabs.length - 1);
                switchToTab(state.openTabs[nextIdx].path);
            } else {
                state.activeTab = null;
                if (state.editor) state.editor.setModel(null);
                var ph = document.querySelector('.fmz-monaco-placeholder');
                if (ph) ph.style.display = '';
            }
        }

        renderTabs();
        updateDirtyIndicators();
    }

    function saveCurrentFile() {
        if (!state.activeTab) return;
        var tab = findTab(state.activeTab);
        if (!tab) return;

        var content  = tab.model.getValue();
        var statusEl = document.getElementById('fmz-status-sync');
        statusEl.textContent = 'Saving…';
        statusEl.className   = 'fmz-status-saving';

        var formData = new FormData();
        formData.append('my_post_key', CONFIG.postKey);
        formData.append('slug', state.currentSlug);
        formData.append('path', tab.path);
        formData.append('content', content);

        fetch(CONFIG.baseUrl + '&action=api_savefile', {
            method: 'POST',
            body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                tab.isDirty = false;
                tab.originalContent = content;
                statusEl.textContent = 'Saved & Synced (' + (data.time || '') + ')';
                statusEl.className   = 'fmz-status-saved';
                showNotification('Saved & synced to database', 'success');
                renderTabs();
                updateDirtyIndicators();

                setTimeout(function () {
                    if (statusEl.className === 'fmz-status-saved') {
                        statusEl.textContent = 'Ready';
                        statusEl.className   = '';
                    }
                }, 3000);
            } else {
                statusEl.textContent = 'Save failed';
                statusEl.className   = 'fmz-status-error';
                showNotification(data.error || 'Save failed', 'error');
            }
        })
        .catch(function (err) {
            statusEl.textContent = 'Save failed';
            statusEl.className   = 'fmz-status-error';
            showNotification('Network error: ' + err.message, 'error');
        });
    }

    /* ═══════════════════════════════════════════════
       Tab Bar
       ═══════════════════════════════════════════════ */

    function renderTabs() {
        var tabBar = document.getElementById('fmz-tab-bar');
        if (!tabBar) return;
        tabBar.innerHTML = '';

        state.openTabs.forEach(function (tab) {
            var el = document.createElement('div');
            el.className = 'fmz-tab' + (tab.path === state.activeTab ? ' active' : '');

            var ext  = tab.path.split('.').pop().toLowerCase();
            var icon = ICON_MAP[ext] || ICON_MAP._default;

            el.innerHTML =
                '<span class="fmz-tab-icon">' + icon + '</span>' +
                '<span class="fmz-tab-name">' + escapeHtml(tab.name) + '</span>' +
                (tab.isDirty ? '<span class="fmz-tab-dirty">\u25CF</span>' : '') +
                '<span class="fmz-tab-close" title="Close">\u00D7</span>';

            (function (p) {
                el.addEventListener('click', function () { switchToTab(p); });
                el.querySelector('.fmz-tab-close').addEventListener('click', function (e) { closeTab(p, e); });
            })(tab.path);

            tabBar.appendChild(el);
        });
    }

    /**
     * Update dirty indicators on the file tree sidebar.
     * Files with unsaved changes get a ● dot; parent folders get a [●] badge.
     */
    function updateDirtyIndicators() {
        // Collect all dirty file paths
        var dirtyPaths = {};
        var dirtyDirs  = {};

        state.openTabs.forEach(function (tab) {
            if (tab.isDirty) {
                dirtyPaths[tab.path] = true;
                // Mark all ancestor directories
                var parts = tab.path.split('/');
                for (var i = 1; i < parts.length; i++) {
                    dirtyDirs[parts.slice(0, i).join('/')] = true;
                }
            }
        });

        // Update file dirty dots
        var fileItems = document.querySelectorAll('.fmz-tree-file-item');
        for (var i = 0; i < fileItems.length; i++) {
            var path = fileItems[i].getAttribute('data-path');
            var dot  = fileItems[i].querySelector('.fmz-tree-dirty');
            if (dot) {
                dot.style.display = dirtyPaths[path] ? 'inline' : 'none';
            }
        }

        // Update folder dirty badges
        var folderItems = document.querySelectorAll('.fmz-tree-folder');
        for (var j = 0; j < folderItems.length; j++) {
            var fPath = folderItems[j].getAttribute('data-path');
            var badge = folderItems[j].querySelector('.fmz-tree-folder-dirty');
            if (badge) {
                badge.style.display = dirtyDirs[fPath] ? 'inline' : 'none';
            }
        }
    }

    /* ═══════════════════════════════════════════════
       Status Bar
       ═══════════════════════════════════════════════ */

    function updateStatusBar() {
        var posEl  = document.getElementById('fmz-status-pos');
        var langEl = document.getElementById('fmz-status-lang');
        if (!posEl || !langEl) return;

        if (state.editor && state.editor.getModel()) {
            var pos = state.editor.getPosition();
            posEl.textContent = 'Ln ' + pos.lineNumber + ', Col ' + pos.column;
            var langId = state.editor.getModel().getLanguageId();
            langEl.textContent = langId.charAt(0).toUpperCase() + langId.slice(1);
        } else {
            posEl.textContent  = '';
            langEl.textContent = '';
        }
    }

    /* ═══════════════════════════════════════════════
       Notifications
       ═══════════════════════════════════════════════ */

    function showNotification(message, type) {
        var container = document.getElementById('fmz-notifications');
        if (!container) return;

        var el = document.createElement('div');
        el.className = 'fmz-notify fmz-notify-' + (type || 'info');
        el.textContent = message;
        container.appendChild(el);

        setTimeout(function () {
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 300);
        }, 3000);
    }

    /* ═══════════════════════════════════════════════
       Helpers
       ═══════════════════════════════════════════════ */

    function findTab(path) {
        for (var i = 0; i < state.openTabs.length; i++) {
            if (state.openTabs[i].path === path) return state.openTabs[i];
        }
        return null;
    }

    function setTreeItemActive(path) {
        var all = document.querySelectorAll('.fmz-tree-item.active');
        for (var i = 0; i < all.length; i++) all[i].classList.remove('active');

        var item = document.querySelector('.fmz-tree-item[data-path="' + CSS.escape(path) + '"]');
        if (item) item.classList.add('active');
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return str.replace(/&/g, '&amp;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#39;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;');
    }

    /* ═══════════════════════════════════════════════
       Context Menu
       ═══════════════════════════════════════════════ */

    function showContextMenu(x, y, path, type) {
        closeContextMenu();
        state.contextTarget = { path: path, type: type };

        var menu = document.createElement('div');
        menu.className = 'fmz-context-menu';
        menu.id = 'fmz-context-menu';

        var items = [];

        if (type === 'directory') {
            items.push({ icon: '<i class="bi bi-file-earmark-plus"></i>', label: 'New File Here', action: 'newfile' });
            items.push({ icon: '<i class="bi bi-folder-plus"></i>', label: 'New Folder Here', action: 'newfolder' });
            items.push({ sep: true });
            items.push({ icon: '<i class="bi bi-pencil"></i>', label: 'Rename', action: 'rename' });
            items.push({ icon: '<i class="bi bi-trash"></i>', label: 'Delete Folder', action: 'deletefolder', danger: true });
        } else {
            items.push({ icon: '<i class="bi bi-file-earmark-plus"></i>', label: 'New File Here', action: 'newfile' });
            items.push({ icon: '<i class="bi bi-folder-plus"></i>', label: 'New Folder Here', action: 'newfolder' });
            items.push({ sep: true });
            items.push({ icon: '<i class="bi bi-pencil"></i>', label: 'Rename', action: 'rename' });
            items.push({ icon: '<i class="bi bi-trash"></i>', label: 'Delete File', action: 'deletefile', danger: true });
        }

        items.forEach(function (item) {
            if (item.sep) {
                var sep = document.createElement('div');
                sep.className = 'fmz-context-menu-sep';
                menu.appendChild(sep);
                return;
            }

            var el = document.createElement('div');
            el.className = 'fmz-context-menu-item' + (item.danger ? ' danger' : '');
            el.innerHTML = '<span class="fmz-context-menu-icon">' + item.icon + '</span>' + escapeHtml(item.label);

            (function (act) {
                el.addEventListener('click', function (e) {
                    e.stopPropagation();
                    closeContextMenu();
                    handleContextAction(act);
                });
            })(item.action);

            menu.appendChild(el);
        });

        // Position (keep on screen)
        document.body.appendChild(menu);
        var rect = menu.getBoundingClientRect();
        if (x + rect.width > window.innerWidth) x = window.innerWidth - rect.width - 8;
        if (y + rect.height > window.innerHeight) y = window.innerHeight - rect.height - 8;
        menu.style.left = x + 'px';
        menu.style.top  = y + 'px';
    }

    function closeContextMenu() {
        var existing = document.getElementById('fmz-context-menu');
        if (existing) existing.remove();
    }

    function handleContextAction(action) {
        var target = state.contextTarget;
        if (!target) return;

        // For files, get parent directory
        var parentDir = target.type === 'directory' ? target.path : target.path.substring(0, target.path.lastIndexOf('/'));

        switch (action) {
            case 'newfile':
                promptCreateFile(parentDir);
                break;
            case 'newfolder':
                promptCreateFolder(parentDir);
                break;
            case 'rename':
                promptRename(target.path, target.type);
                break;
            case 'deletefile':
                if (confirm('Delete "' + target.path.split('/').pop() + '"? This cannot be undone.')) {
                    apiDelete(target.path, 'file');
                }
                break;
            case 'deletefolder':
                if (confirm('Delete folder "' + target.path.split('/').pop() + '" and ALL its contents? This cannot be undone.')) {
                    apiDelete(target.path, 'folder');
                }
                break;
        }
    }

    /* ═══════════════════════════════════════════════
       File/Folder CRUD
       ═══════════════════════════════════════════════ */

    function promptCreateFile(parentDir) {
        var name = prompt('New file name:', '');
        if (!name || !name.trim()) return;

        var path = parentDir ? parentDir + '/' + name.trim() : name.trim();
        var formData = new FormData();
        formData.append('my_post_key', CONFIG.postKey);
        formData.append('slug', state.currentSlug);
        formData.append('path', path);

        fetch(CONFIG.baseUrl + '&action=api_createfile', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showNotification('File created: ' + name.trim(), 'success');
                    loadThemeTree(state.currentSlug);
                    // Auto-open the new file
                    setTimeout(function () { openFile(path); }, 500);
                } else {
                    showNotification((data.errors && data.errors[0]) || 'Failed to create file', 'error');
                }
            })
            .catch(function (err) { showNotification('Error: ' + err.message, 'error'); });
    }

    function promptCreateFolder(parentDir) {
        var name = prompt('New folder name:', '');
        if (!name || !name.trim()) return;

        var path = parentDir ? parentDir + '/' + name.trim() : name.trim();
        var formData = new FormData();
        formData.append('my_post_key', CONFIG.postKey);
        formData.append('slug', state.currentSlug);
        formData.append('path', path);

        fetch(CONFIG.baseUrl + '&action=api_createfolder', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showNotification('Folder created: ' + name.trim(), 'success');
                    loadThemeTree(state.currentSlug);
                } else {
                    showNotification((data.errors && data.errors[0]) || 'Failed to create folder', 'error');
                }
            })
            .catch(function (err) { showNotification('Error: ' + err.message, 'error'); });
    }

    function promptRename(oldPath, type) {
        var oldName = oldPath.split('/').pop();
        var newName = prompt('Rename "' + oldName + '" to:', oldName);
        if (!newName || !newName.trim() || newName.trim() === oldName) return;

        var parentDir = oldPath.substring(0, oldPath.lastIndexOf('/'));
        var newPath = parentDir ? parentDir + '/' + newName.trim() : newName.trim();

        var formData = new FormData();
        formData.append('my_post_key', CONFIG.postKey);
        formData.append('slug', state.currentSlug);
        formData.append('old_path', oldPath);
        formData.append('new_path', newPath);

        fetch(CONFIG.baseUrl + '&action=api_rename', { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showNotification('Renamed to: ' + newName.trim(), 'success');
                    // If the renamed file is in an open tab, update it
                    var tab = findTab(oldPath);
                    if (tab) {
                        closeTab(oldPath);
                    }
                    loadThemeTree(state.currentSlug);
                } else {
                    showNotification('Rename failed', 'error');
                }
            })
            .catch(function (err) { showNotification('Error: ' + err.message, 'error'); });
    }

    function apiDelete(path, type) {
        var action = type === 'folder' ? 'api_deletefolder' : 'api_deletefile';
        var formData = new FormData();
        formData.append('my_post_key', CONFIG.postKey);
        formData.append('slug', state.currentSlug);
        formData.append('path', path);

        fetch(CONFIG.baseUrl + '&action=' + action, { method: 'POST', body: formData })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showNotification('Deleted successfully', 'success');
                    // Close tab if this file was open
                    if (type === 'file') {
                        var tab = findTab(path);
                        if (tab) closeTab(path);
                    } else {
                        // Close all tabs for files inside this folder
                        var toClose = [];
                        state.openTabs.forEach(function (t) {
                            if (t.path.indexOf(path + '/') === 0 || t.path === path) {
                                toClose.push(t.path);
                            }
                        });
                        toClose.forEach(function (p) { closeTab(p); });
                    }
                    loadThemeTree(state.currentSlug);
                } else {
                    showNotification('Delete failed', 'error');
                }
            })
            .catch(function (err) { showNotification('Error: ' + err.message, 'error'); });
    }

    /* ═══════════════════════════════════════════════
       Init
       ═══════════════════════════════════════════════ */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
