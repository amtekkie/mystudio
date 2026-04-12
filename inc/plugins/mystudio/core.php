<?php
/**
 * MyStudio Core — Modular theme manager for MyBB.
 *
 * Theme packages live on disk at MYBB_ROOT/themes/{slug}/ and remain
 * editable via file manager.  The database is treated as a cache that
 * can be rebuilt from the files at any time ("Sync").
 *
 * Required structure inside a theme package:
 *   theme.json      — manifest (name, version, properties, stylesheets[], js[])
 *   templates/      — at least one .html template file (sub-folders allowed)
 *
 * Optional:
 *   css/            — stylesheets referenced in theme.json
 *   js/             — JavaScript files deployed to jscripts/
 *   images/         — theme images
 *   Any other folder the developer needs (fonts/, vendor/, etc.)
 *
 * @version 2.1.0
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

class MyStudio
{
    /** @var string[] Accumulated error messages */
    private $errors = array();

    /** @var string Temp dir used during ZIP creation for export */
    private $tempDir = '';

    /** Base directory where modular themes live (relative to MYBB_ROOT) */
    const THEMES_DIR = 'themes';

    /**
     * Allowed file extensions for editing/creating via the editor.
     * Executable extensions (php, phtml, phar, etc.) are blocked to prevent RCE.
     */
    const ALLOWED_EDIT_EXTENSIONS = array(
        'html', 'htm', 'css', 'js', 'json', 'txt', 'md',
        'xml', 'svg', 'ini', 'yml', 'yaml', 'less', 'scss',
        'map', 'csv', 'log', 'tpl', 'mustache', 'hbs',
    );

    /**
     * Check if a file extension is safe for editor operations.
     *
     * @param  string $path  File path or name
     * @return bool
     */
    private function isSafeExtension($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, self::ALLOWED_EDIT_EXTENSIONS);
    }

    /**
     * Import a theme from an uploaded ZIP file.
     *
     * 1. Validate & extract ZIP
     * 2. Validate structure (theme.json + templates/ with .html files)
     * 3. Copy to MYBB_ROOT/themes/{slug}/ (persistent)
     * 4. Sync to database (build XML → import)
     * 5. Deploy JS files to jscripts/
     *
     * @param  array $file      $_FILES element
     * @param  int   $parentTid Parent theme ID (default 1 = Master)
     * @return int|false        New theme TID or false on failure
     */
    public function importFromZip($file, $parentTid = 1)
    {
        // ── Validate upload ──
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = 'Upload failed (error code ' . (int) $file['error'] . ').';
            return false;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            $this->errors[] = 'Only .zip files are accepted.';
            return false;
        }

        global $mybb;
        $maxBytes = (isset($mybb->settings['ms_max_upload_mb'])
            ? (int) $mybb->settings['ms_max_upload_mb']
            : 20) * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            $this->errors[] = 'File exceeds the maximum upload size.';
            return false;
        }

        if (!class_exists('ZipArchive')) {
            $this->errors[] = 'PHP ZipArchive extension is required but not installed.';
            return false;
        }

        // ── Extract to temp ──
        $tmpDir = sys_get_temp_dir() . '/mystudio_' . uniqid();
        if (!@mkdir($tmpDir, 0755, true)) {
            $this->errors[] = 'Cannot create temporary directory.';
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            $this->errors[] = 'Cannot open ZIP archive.';
            $this->rrmdir($tmpDir);
            return false;
        }

        // Validate ZIP entries for path traversal (ZIP Slip prevention)
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            // Reject entries with path traversal sequences
            if (strpos($entryName, '..') !== false || strpos($entryName, '\\') !== false) {
                $this->errors[] = 'ZIP archive contains unsafe path entry: ' . $entryName;
                $zip->close();
                $this->rrmdir($tmpDir);
                return false;
            }
            // Reject null bytes (defense in depth)
            if (strpos($entryName, "\x00") !== false) {
                $this->errors[] = 'ZIP archive contains null byte in path entry.';
                $zip->close();
                $this->rrmdir($tmpDir);
                return false;
            }
            // Reject absolute paths
            if ($entryName[0] === '/') {
                $this->errors[] = 'ZIP archive contains absolute path entry: ' . $entryName;
                $zip->close();
                $this->rrmdir($tmpDir);
                return false;
            }
        }

        $zip->extractTo($tmpDir);
        $zip->close();

        // ── Locate theme root ──
        $themeRoot = $this->findThemeRoot($tmpDir);
        if (!$themeRoot) {
            $this->errors[] = 'theme.json not found in the archive (checked root and one level deep).';
            $this->rrmdir($tmpDir);
            return false;
        }

        // ── Validate mandatory structure ──
        $valid = $this->validateThemeDir($themeRoot);
        if (!$valid) {
            $this->rrmdir($tmpDir);
            return false;
        }

        // ── Read theme.json for name/slug ──
        $cfg  = json_decode(file_get_contents($themeRoot . '/theme.json'), true);
        $slug = $this->makeSlug($cfg['name']);

        // ── Copy to persistent location ──
        $destDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        if (is_dir($destDir)) {
            $this->rrmdir($destDir); // update in place
        }
        @mkdir(MYBB_ROOT . self::THEMES_DIR, 0755, true);

        if (!$this->rcopy($themeRoot, $destDir)) {
            $this->errors[] = 'Failed to copy theme files to ' . self::THEMES_DIR . '/' . $slug . '/';
            $this->rrmdir($tmpDir);
            return false;
        }

        $this->rrmdir($tmpDir);

        // ── Sync to DB ──
        $tid = $this->syncToDatabase($slug, $parentTid);
        if (!$tid) {
            return false;
        }

        // ── Deploy JS ──
        $this->deployJs($destDir);

        return $tid;
    }

    /**
     * Export a theme.  If the theme lives on disk (themes/{slug}/), package
     * that directory directly.  Otherwise, extract from DB first, then package.
     *
     * @param  int $tid  Theme ID
     * @return string|false  Path to generated ZIP, or false
     */
    public function exportTheme($tid)
    {
        global $db;

        $tid = (int) $tid;
        $query = $db->simple_select('themes', '*', "tid='{$tid}'");
        $theme = $db->fetch_array($query);
        if (!$theme) {
            $this->errors[] = 'Theme not found.';
            return false;
        }

        $slug     = $this->makeSlug($theme['name']);
        $themeDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;

        // If theme doesn't exist on disk yet, create it from DB
        if (!is_dir($themeDir) || !file_exists($themeDir . '/theme.json')) {
            $this->extractThemeToDisk($tid, $slug);
        }

        if (!is_dir($themeDir)) {
            $this->errors[] = 'Theme directory could not be created.';
            return false;
        }

        // ── Create ZIP ──
        if (!class_exists('ZipArchive')) {
            $this->errors[] = 'PHP ZipArchive extension is required.';
            return false;
        }

        $this->tempDir = sys_get_temp_dir() . '/mystudio_' . uniqid();
        @mkdir($this->tempDir, 0755, true);

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $theme['name']);
        $zipPath  = $this->tempDir . '/' . $safeName . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->errors[] = 'Failed to create ZIP archive.';
            return false;
        }

        $this->addDirToZip($zip, $themeDir, $safeName);
        $zip->close();

        return $zipPath;
    }

    /**
     * Extract a theme from the database to the themes/ directory on disk.
     *
     * @param  int         $tid   Theme ID
     * @param  string|null $slug  Override slug (auto-derived from theme name if null)
     * @return string|false       Path to theme directory, or false
     */
    public function extractThemeToDisk($tid, $slug = null)
    {
        global $db;

        $tid = (int) $tid;
        $query = $db->simple_select('themes', '*', "tid='{$tid}'");
        $theme = $db->fetch_array($query);
        if (!$theme) {
            $this->errors[] = 'Theme not found.';
            return false;
        }

        if ($slug === null) {
            $slug = $this->makeSlug($theme['name']);
        }

        $properties = my_unserialize($theme['properties']);
        $themeDir   = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;

        @mkdir($themeDir . '/css', 0755, true);
        @mkdir($themeDir . '/templates', 0755, true);

        // ── Stylesheets ──
        $ssMeta = array();
        $query  = $db->simple_select(
            'themestylesheets', '*',
            "tid='{$tid}'",
            array('order_by' => 'name')
        );
        $order = 1;
        while ($ss = $db->fetch_array($query)) {
            file_put_contents($themeDir . '/css/' . $ss['name'], $ss['stylesheet']);
            $ssMeta[] = array(
                'name'       => $ss['name'],
                'attachedto' => $ss['attachedto'],
                'order'      => $order++
            );
        }

        // ── Templates ──
        $sid = isset($properties['templateset']) ? (int) $properties['templateset'] : 0;
        $templates = array();

        if ($sid > 0) {
            $query = $db->simple_select('templates', 'title, template', "sid='{$sid}'");
            while ($t = $db->fetch_array($query)) {
                $templates[$t['title']] = $t['template'];
            }
        }

        // Fill in master templates for any not overridden
        $query = $db->simple_select('templates', 'title, template', "sid='-2'");
        while ($t = $db->fetch_array($query)) {
            if (!isset($templates[$t['title']])) {
                $templates[$t['title']] = $t['template'];
            }
        }

        // Group by MyBB template groups
        $folderMap = $this->getTemplateFolderMap(array_keys($templates));
        foreach ($templates as $name => $content) {
            $folder = isset($folderMap[$name]) ? $folderMap[$name] : 'ungrouped';
            $dir = $themeDir . '/templates/' . $folder;
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($dir . '/' . $name . '.html', $content);
        }

        // ── JS files ──
        $jsFiles   = array();
        $jsScanDir = MYBB_ROOT . 'jscripts';
        $jsLookFor = array('main.js', 'ms-main.js', 'tek-main.js');
        foreach ($jsLookFor as $jsName) {
            if (file_exists($jsScanDir . '/' . $jsName)) {
                @mkdir($themeDir . '/js', 0755, true);
                copy($jsScanDir . '/' . $jsName, $themeDir . '/js/' . $jsName);
                $jsFiles[] = $jsName;
                break;
            }
        }

        // ── theme.json ──
        $themeJson = array(
            'name'    => $theme['name'],
            'version' => isset($properties['version']) ? $properties['version'] : '1839',
            'properties' => array(
                'editortheme' => isset($properties['editortheme']) ? $properties['editortheme'] : 'default.css',
                'imgdir'      => isset($properties['imgdir'])      ? $properties['imgdir']      : 'images',
                'tablespace'  => isset($properties['tablespace'])  ? $properties['tablespace']  : '0',
                'borderwidth' => isset($properties['borderwidth']) ? $properties['borderwidth'] : '0',
                'color'       => isset($properties['color'])       ? $properties['color']       : ''
            ),
            'stylesheets' => $ssMeta,
            'js'          => $jsFiles
        );
        file_put_contents(
            $themeDir . '/theme.json',
            json_encode($themeJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $themeDir;
    }

    /**
     * Sync a theme's on-disk files back into the MyBB database.
     *
     * When the theme already exists in the DB this performs an **incremental
     * update** — only templates that actually changed on disk are written to
     * the database. The template-set SID is kept stable so that external
     * references (manual DB edits, etc.) are not invalidated.
     *
     * Stylesheets are still handled via MyBB's import_theme_xml because they
     * have a more complex cache-rebuilding flow. The existing SID is passed
     * through so that import_theme_xml doesn't create a new templateset.
     *
     * Only creates a brand-new theme when no DB record with the same name
     * exists.
     *
     * @param  string $slug      Theme directory name under themes/
     * @param  int    $parentTid Parent theme ID (default 1, only used for new imports)
     * @return int|false         Theme TID or false
     */
    public function syncToDatabase($slug, $parentTid = 1)
    {
        global $db;

        $themeDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;

        if (!is_dir($themeDir) || !file_exists($themeDir . '/theme.json')) {
            $this->errors[] = 'Theme directory not found: ' . self::THEMES_DIR . '/' . $slug;
            return false;
        }

        $valid = $this->validateThemeDir($themeDir);
        if (!$valid) {
            return false;
        }

        // Check if this theme already exists in the DB
        $cfg = json_decode(file_get_contents($themeDir . '/theme.json'), true);
        $themeName = $cfg['name'];
        $name_esc = $db->escape_string($themeName);
        $query = $db->simple_select('themes', 'tid, properties', "name='{$name_esc}' AND tid != 1", array('limit' => 1));
        $existing = $db->fetch_array($query);

        if ($existing) {
            // ── Incremental update: diff templates, reuse SID ──
            $existingTid = (int) $existing['tid'];
            $props = my_unserialize($existing['properties']);
            $sid   = !empty($props['templateset']) ? (int) $props['templateset'] : 0;

            if ($sid > 0) {
                // Incremental template sync — only update what changed
                $this->syncTemplatesIncremental($themeDir, $sid, $cfg);
            }

            // Build XML for stylesheets + properties update
            $xml = $this->buildThemeXml($themeDir);
            if (!$xml) {
                return false;
            }

            // Inject the existing SID into the XML's <templateset> so the
            // properties serialized by import_theme_xml preserve it.
            $xml = str_replace(
                '<templateset><![CDATA[]]></templateset>',
                '<templateset><![CDATA[' . $sid . ']]></templateset>',
                $xml
            );

            // Import stylesheets + properties only (skip templates since we
            // already handled them incrementally). Pass the existing SID so
            // import_theme_xml does not create a new templateset row.
            $tid = $this->importThemeXml($xml, $parentTid, array(
                'no_templates' => 1,
                'templateset'  => $sid,
            ));
        } else {
            // ── Brand new theme: full import ──
            $xml = $this->buildThemeXml($themeDir);
            if (!$xml) {
                return false;
            }
            $tid = $this->importThemeXml($xml, $parentTid);
        }

        if ($tid) {
            $this->deployJs($themeDir);

            // Clear old stylesheet cache and rebuild from DB
            @array_map('unlink', (array) glob(MYBB_ROOT . 'cache/themes/theme' . $tid . '/*'));
            $this->rebuildStylesheetCache($tid);
        }

        return $tid;
    }

    /**
     * Incrementally sync templates from disk to DB for a given SID.
     *
     * Compares every template file on disk with the DB row:
     *  - INSERT templates that exist on disk but not in DB
     *  - UPDATE templates whose content differs between disk and DB
     *  - DELETE templates that exist in DB but not on disk
     *
     * @param string $themeDir  Absolute path to the theme directory
     * @param int    $sid       The template-set SID to sync into
     * @param array  $cfg       Parsed theme.json config
     */
    private function syncTemplatesIncremental($themeDir, $sid, $cfg)
    {
        global $db;

        $tmplDir = $themeDir . '/templates';
        if (!is_dir($tmplDir)) return;

        $version = isset($cfg['version']) ? $cfg['version'] : '1839';

        // ── 1. Read all templates from disk ──
        $diskTemplates = array(); // name => content
        $templateFiles = $this->scanTemplateDir($tmplDir);
        foreach ($templateFiles as $relPath) {
            $name    = pathinfo($relPath, PATHINFO_FILENAME);
            $content = file_get_contents($tmplDir . '/' . $relPath);
            $diskTemplates[$name] = $content;
        }

        // ── 2. Read all templates from DB for this SID ──
        $dbTemplates = array(); // name => ['tid' => int, 'template' => string]
        $query = $db->simple_select('templates', 'tid, title, template', "sid='{$sid}'");
        while ($row = $db->fetch_array($query)) {
            $dbTemplates[$row['title']] = array(
                'tid'      => (int) $row['tid'],
                'template' => $row['template'],
            );
        }

        // ── 3. Diff and apply changes ──
        $inserted = 0;
        $updated  = 0;
        $deleted  = 0;

        // Templates on disk — insert or update
        foreach ($diskTemplates as $name => $content) {
            if (isset($dbTemplates[$name])) {
                // Exists in DB — update only if content changed
                if ($dbTemplates[$name]['template'] !== $content) {
                    $db->update_query('templates', array(
                        'template' => $db->escape_string($content),
                        'version'  => $db->escape_string($version),
                        'dateline' => TIME_NOW,
                    ), "tid='{$dbTemplates[$name]['tid']}'");
                    $updated++;
                }
                // Remove from DB list so we know what's left (= deleted from disk)
                unset($dbTemplates[$name]);
            } else {
                // New template — insert
                $db->insert_query('templates', array(
                    'title'    => $db->escape_string($name),
                    'template' => $db->escape_string($content),
                    'sid'      => $sid,
                    'version'  => $db->escape_string($version),
                    'dateline' => TIME_NOW,
                ));
                $inserted++;
            }
        }

        // Remaining DB templates were removed from disk — delete them
        foreach ($dbTemplates as $name => $data) {
            $db->delete_query('templates', "tid='{$data['tid']}'");
            $deleted++;
        }
    }

    /**
     * Get a list of all theme directories on disk.
     *
     * @return array  Array of ['slug', 'name', 'version', 'has_db', 'tid']
     */
    public function listThemesOnDisk()
    {
        $baseDir = MYBB_ROOT . self::THEMES_DIR;
        $result  = array();

        if (!is_dir($baseDir)) return $result;

        global $db;

        // Load all DB theme names for comparison
        $dbThemes = array();
        $query = $db->simple_select('themes', 'tid, name', "tid != 1");
        while ($t = $db->fetch_array($query)) {
            $dbThemes[strtolower(trim($t['name']))] = (int) $t['tid'];
        }

        foreach (scandir($baseDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $dir = $baseDir . '/' . $entry;
            if (!is_dir($dir) || !file_exists($dir . '/theme.json')) continue;

            $cfg = json_decode(file_get_contents($dir . '/theme.json'), true);
            if (!$cfg || !isset($cfg['name'])) continue;

            $nameKey = strtolower(trim($cfg['name']));
            $result[] = array(
                'slug'    => $entry,
                'name'    => $cfg['name'],
                'version' => isset($cfg['version']) ? $cfg['version'] : '?',
                'has_db'  => isset($dbThemes[$nameKey]),
                'tid'     => isset($dbThemes[$nameKey]) ? $dbThemes[$nameKey] : 0
            );
        }

        return $result;
    }

    /**
     * Set a theme as the default board theme.
     *
     * @param  int  $tid  Theme ID to make active
     * @return bool
     */
    public function activateTheme($tid)
    {
        global $db, $cache;
        $tid = (int) $tid;
        if ($tid < 2) {
            $this->errors[] = 'Cannot set Master Style as default.';
            return false;
        }
        $query = $db->simple_select('themes', 'tid, name', "tid='{$tid}'");
        $theme = $db->fetch_array($query);
        if (!$theme) {
            $this->errors[] = 'Theme not found in database.';
            return false;
        }
        $db->update_query('themes', array('def' => 0), "def='1'");
        $db->update_query('themes', array('def' => 1), "tid='{$tid}'");
        // Rebuild the default_theme datacache so the frontend picks it up
        $cache->update_default_theme();
        return true;
    }

    /**
     * Deactivate a theme — reset the board default to MyBB Default (tid=2 or lowest).
     *
     * @param  int  $tid  Theme ID to deactivate
     * @return bool
     */
    public function deactivateTheme($tid)
    {
        global $db, $cache;
        $tid = (int) $tid;
        $db->update_query('themes', array('def' => 0), "tid='{$tid}'");

        // Find a fallback theme (lowest tid that isn't 1 or the one we're deactivating)
        $query = $db->simple_select('themes', 'tid', "tid != 1 AND tid != '{$tid}'", array('order_by' => 'tid', 'limit' => 1));
        $fallback = $db->fetch_array($query);
        $fallbackTid = $fallback ? (int) $fallback['tid'] : 1;

        $db->update_query('themes', array('def' => 1), "tid='{$fallbackTid}'");
        // Rebuild the default_theme datacache so the frontend picks it up
        $cache->update_default_theme();
        return true;
    }

    /**
     * List all non-master themes in the database, including which ones exist on disk.
     *
     * @return array  Array of ['tid', 'name', 'is_default', 'has_disk', 'slug']
     */
    public function listDbThemes()
    {
        global $db;

        // Gather disk slugs for cross-reference
        $diskIndex = array(); // name(lower) => slug
        $baseDir = MYBB_ROOT . self::THEMES_DIR;
        if (is_dir($baseDir)) {
            foreach (scandir($baseDir) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $dir = $baseDir . '/' . $entry;
                if (!is_dir($dir) || !file_exists($dir . '/theme.json')) continue;
                $cfg = json_decode(file_get_contents($dir . '/theme.json'), true);
                if ($cfg && isset($cfg['name'])) {
                    $diskIndex[strtolower(trim($cfg['name']))] = $entry;
                }
            }
        }

        $result = array();
        $query = $db->simple_select('themes', 'tid, name, def, properties', "tid != 1", array('order_by' => 'name'));
        while ($t = $db->fetch_array($query)) {
            $nameKey = strtolower(trim($t['name']));
            $result[] = array(
                'tid'        => (int) $t['tid'],
                'name'       => $t['name'],
                'is_default' => (int) $t['def'] === 1,
                'has_disk'   => isset($diskIndex[$nameKey]),
                'slug'       => isset($diskIndex[$nameKey]) ? $diskIndex[$nameKey] : ''
            );
        }
        return $result;
    }

    /** @return string[] */
    public function getErrors()
    {
        return $this->errors;
    }

    /** Remove temp directory (used after export ZIP download) */
    public function cleanup()
    {
        if ($this->tempDir && is_dir($this->tempDir)) {
            $this->rrmdir($this->tempDir);
            $this->tempDir = '';
        }
    }

    /**
     * Validate that a theme directory has the mandatory structure.
     * Adds error messages and returns false if invalid.
     *
     * @param  string $dir  Path to theme root
     * @return bool
     */
    private function validateThemeDir($dir)
    {
        $ok = true;

        // theme.json — must exist and contain a "name"
        $jsonPath = $dir . '/theme.json';
        if (!file_exists($jsonPath)) {
            $this->errors[] = 'Missing required file: theme.json';
            return false;
        }
        $cfg = json_decode(file_get_contents($jsonPath), true);
        if (!$cfg || !is_array($cfg)) {
            $this->errors[] = 'theme.json is not valid JSON.';
            return false;
        }
        if (empty($cfg['name'])) {
            $this->errors[] = 'theme.json must contain a "name" field.';
            $ok = false;
        }

        // templates/ — must exist with at least one .html file
        $tmplDir = $dir . '/templates';
        if (!is_dir($tmplDir)) {
            $this->errors[] = 'Missing required directory: templates/';
            $ok = false;
        } else {
            $htmlFiles = $this->scanTemplateDir($tmplDir);
            if (empty($htmlFiles)) {
                $this->errors[] = 'templates/ directory must contain at least one .html template file.';
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Build a valid MyBB theme XML string from modular files on disk.
     */
    private function buildThemeXml($themeDir)
    {
        $raw = file_get_contents($themeDir . '/theme.json');
        $cfg = json_decode($raw, true);

        $themeName    = htmlspecialchars($cfg['name'], ENT_XML1, 'UTF-8');
        $themeVersion = isset($cfg['version']) ? htmlspecialchars($cfg['version'], ENT_XML1, 'UTF-8') : '1839';

        $props = isset($cfg['properties']) ? $cfg['properties'] : array();
        $editortheme = isset($props['editortheme']) ? $props['editortheme'] : 'default.css';
        $tablespace  = isset($props['tablespace'])  ? $props['tablespace']  : '0';
        $borderwidth = isset($props['borderwidth']) ? $props['borderwidth'] : '0';
        $color       = isset($props['color'])       ? $props['color']       : '';

        // imgdir — use theme.json value, or auto-detect, or MyBB default
        $slug = $this->makeSlug($cfg['name']);
        if (isset($props['imgdir']) && $props['imgdir'] !== '') {
            $imgdir = $props['imgdir'];
        } elseif (is_dir($themeDir . '/images')) {
            $imgdir = self::THEMES_DIR . '/' . $slug . '/images';
        } else {
            $imgdir = 'images';
        }

        // disporder
        $stylesheets = isset($cfg['stylesheets']) ? $cfg['stylesheets'] : array();
        $disporder = array();
        foreach ($stylesheets as $i => $ss) {
            $disporder[$ss['name']] = isset($ss['order']) ? (int) $ss['order'] : ($i + 1);
        }
        $disporderSer = serialize($disporder);

        // ── Build XML ──
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<theme name="' . $themeName . '" version="' . $themeVersion . '">' . "\n";

        $xml .= "\t<properties>\n";
        $xml .= "\t\t<templateset><![CDATA[]]></templateset>\n";
        $xml .= "\t\t<editortheme><![CDATA[" . $editortheme . "]]></editortheme>\n";
        $xml .= "\t\t<imgdir><![CDATA[" . $imgdir . "]]></imgdir>\n";
        $xml .= "\t\t<tablespace><![CDATA[" . $tablespace  . "]]></tablespace>\n";
        $xml .= "\t\t<borderwidth><![CDATA[" . $borderwidth . "]]></borderwidth>\n";
        $xml .= "\t\t<color><![CDATA[" . $color . "]]></color>\n";
        $xml .= "\t\t<disporder><![CDATA[" . $disporderSer . "]]></disporder>\n";
        $xml .= "\t</properties>\n";

        // Stylesheets
        $xml .= "\t<stylesheets>\n";
        $cssDir = $themeDir . '/css';
        if (is_dir($cssDir)) {
            foreach ($stylesheets as $ss) {
                $name       = $ss['name'];
                $attachedto = isset($ss['attachedto']) ? $ss['attachedto'] : '';
                $cssPath    = $cssDir . '/' . $name;
                if (!file_exists($cssPath)) continue;

                $cssContent = file_get_contents($cssPath);
                $cssContent = $this->escapeCdata($cssContent);

                $xml .= "\t\t<stylesheet name=\"" . htmlspecialchars($name, ENT_XML1, 'UTF-8')
                      . "\" attachedto=\"" . htmlspecialchars($attachedto, ENT_XML1, 'UTF-8')
                      . "\" version=\"" . $themeVersion
                      . "\"><![CDATA[" . $cssContent . "]]></stylesheet>\n";
            }
        }
        $xml .= "\t</stylesheets>\n";

        // Templates
        $xml .= "\t<templates>\n";
        $tmplDir = $themeDir . '/templates';
        if (is_dir($tmplDir)) {
            $templateFiles = $this->scanTemplateDir($tmplDir);
            sort($templateFiles);
            foreach ($templateFiles as $relPath) {
                $name    = pathinfo($relPath, PATHINFO_FILENAME);
                $content = file_get_contents($tmplDir . '/' . $relPath);
                $content = $this->escapeCdata($content);

                $xml .= "\t\t<template name=\"" . htmlspecialchars($name, ENT_XML1, 'UTF-8')
                      . "\" version=\"" . $themeVersion
                      . "\"><![CDATA[" . $content . "]]></template>\n";
            }
        }
        $xml .= "\t</templates>\n";

        $xml .= "</theme>\n";

        return $xml;
    }

    private function importThemeXml($xml, $parentTid = 1, $extraOptions = array())
    {
        // Resolve admin directory path
        $adminPath = '';
        if (defined('MYBB_ADMIN_DIR')) {
            $adminPath = MYBB_ADMIN_DIR;
        } else {
            global $config;
            $adminDir = !empty($config['admin_dir']) ? $config['admin_dir'] : 'admin';
            $adminPath = MYBB_ROOT . $adminDir . '/';
        }

        // Load admin functions needed by import_theme_xml (check_template, etc.)
        if (!function_exists('check_template')) {
            $adminFuncsFile = $adminPath . 'inc/functions.php';
            if (file_exists($adminFuncsFile)) {
                require_once $adminFuncsFile;
            }
        }

        if (!function_exists('import_theme_xml')) {
            $functionsFile = $adminPath . 'inc/functions_themes.php';
            if (file_exists($functionsFile)) {
                require_once $functionsFile;
            } else {
                $this->errors[] = 'Cannot locate MyBB theme functions (functions_themes.php).';
                return false;
            }
        }

        $options = array(
            'no_stylesheets' => 0,
            'no_templates'   => 0,
            'version_compat' => 1,
            'parent'         => (int) $parentTid,
            'name'           => ''
        );

        // Merge any caller-supplied overrides (e.g. no_templates, templateset)
        $options = array_merge($options, $extraOptions);

        $tid = import_theme_xml($xml, $options);

        if (!$tid) {
            $this->errors[] = 'MyBB import_theme_xml() returned no theme ID. The XML may be malformed.';
            return false;
        }

        return (int) $tid;
    }

    /**
     * Delete a theme from the database by name (before re-sync).
     */
    /**
     * Delete a theme from the database and optionally from disk.
     * Cannot delete the currently active (default) theme.
     *
     * @param int  $tid       Theme ID to delete
     * @param bool $deleteDisk Also remove the theme directory from disk
     * @return bool
     */
    public function deleteTheme($tid, $deleteDisk = true)
    {
        global $db, $mybb;

        $tid = (int) $tid;
        if ($tid <= 1) {
            $this->errors[] = 'Cannot delete the Master Style.';
            return false;
        }

        // Check if default theme
        if (isset($mybb->settings['mystudio_default_tid']) && (int)$mybb->settings['mystudio_default_tid'] === $tid) {
            $this->errors[] = 'Cannot delete the active default theme.';
            return false;
        }

        // Also check MyBB's own default theme setting
        $defaultTid = (int) $db->fetch_field($db->simple_select('settings', 'value', "name='mystudio_default_tid'"), 'value');
        if (!$defaultTid) {
            // Fallback: check themes table for def=1
            $defQuery = $db->simple_select('themes', 'tid', "def='1'");
            $defaultTid = (int) $db->fetch_field($defQuery, 'tid');
        }
        if ($tid === $defaultTid) {
            $this->errors[] = 'Cannot delete the active default theme.';
            return false;
        }

        // Fetch theme info
        $query = $db->simple_select('themes', 'name, properties', "tid='{$tid}'");
        $theme = $db->fetch_array($query);
        if (!$theme) {
            $this->errors[] = 'Theme not found.';
            return false;
        }

        $name = $theme['name'];
        $slug = $this->slug($name);

        // Delete from DB (templates, stylesheets, theme record, cache)
        $props = my_unserialize($theme['properties']);
        if (isset($props['templateset'])) {
            $sid = (int) $props['templateset'];
            $db->delete_query('templates', "sid='{$sid}'");
            $db->delete_query('templatesets', "sid='{$sid}'");
        }
        $db->delete_query('themestylesheets', "tid='{$tid}'");
        $db->delete_query('themes', "tid='{$tid}'");

        // Reassign any child themes to master
        $db->update_query('themes', array('pid' => 1), "pid='{$tid}'");

        // Clean cache
        @array_map('unlink', (array) glob(MYBB_ROOT . 'cache/themes/theme' . $tid . '/*'));
        @rmdir(MYBB_ROOT . 'cache/themes/theme' . $tid);

        // Delete disk files
        if ($deleteDisk && !empty($slug)) {
            $themeDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
            if (is_dir($themeDir)) {
                $this->rrmdir($themeDir);
            }
        }

        return true;
    }

    private function deleteThemeByName($name)
    {
        global $db;

        $name_esc = $db->escape_string($name);
        $query = $db->simple_select('themes', 'tid', "name='{$name_esc}' AND tid != 1");
        while ($t = $db->fetch_array($query)) {
            $tid = (int) $t['tid'];
            $tq = $db->simple_select('themes', 'properties', "tid='{$tid}'");
            $theme = $db->fetch_array($tq);
            if ($theme) {
                $props = my_unserialize($theme['properties']);
                if (isset($props['templateset'])) {
                    $sid = (int) $props['templateset'];
                    $db->delete_query('templates', "sid='{$sid}'");
                    $db->delete_query('templatesets', "sid='{$sid}'");
                }
            }
            $db->delete_query('themestylesheets', "tid='{$tid}'");
            $db->delete_query('themes', "tid='{$tid}'");

            @array_map('unlink', (array) glob(MYBB_ROOT . 'cache/themes/theme' . $tid . '/*'));
            @rmdir(MYBB_ROOT . 'cache/themes/theme' . $tid);
        }
    }

    private function deployJs($themeDir)
    {
        $jsDir = $themeDir . '/js';
        if (!is_dir($jsDir)) return;

        $destDir = MYBB_ROOT . 'jscripts';
        foreach (scandir($jsDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $src = $jsDir . '/' . $entry;
            if (is_file($src) && pathinfo($entry, PATHINFO_EXTENSION) === 'js') {
                copy($src, $destDir . '/' . $entry);
            }
        }
    }

    private function findThemeRoot($dir)
    {
        if (file_exists($dir . '/theme.json')) {
            return $dir;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $sub = $dir . '/' . $entry;
            if (is_dir($sub) && file_exists($sub . '/theme.json')) {
                return $sub;
            }
        }
        return false;
    }

    /**
     * Recursively scan a directory for .html template files.
     * Returns relative paths (e.g. 'postbit/postbit.html').
     */
    private function scanTemplateDir($dir, $prefix = '')
    {
        $results = array();
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            $rel  = ($prefix !== '') ? $prefix . '/' . $entry : $entry;
            if (is_dir($path)) {
                $results = array_merge($results, $this->scanTemplateDir($path, $rel));
            } elseif (pathinfo($entry, PATHINFO_EXTENSION) === 'html') {
                $results[] = $rel;
            }
        }
        return $results;
    }

    /**
     * Map template names to MyBB template group folder names.
     */
    private function getTemplateFolderMap($templateNames)
    {
        global $db;

        $groupPrefixes = array();
        if (isset($db) && $db->table_exists('templategroups')) {
            $query = $db->simple_select('templategroups', 'prefix');
            while ($row = $db->fetch_array($query)) {
                $groupPrefixes[] = $row['prefix'];
            }
        }

        if (empty($groupPrefixes)) {
            $groupPrefixes = array(
                'announcement', 'calendar', 'editpost', 'error', 'footer',
                'forumbit', 'forumdisplay', 'forumjump', 'global', 'header',
                'index', 'managegroup', 'member', 'memberlist', 'misc',
                'modcp', 'moderation', 'multipage', 'mycode', 'nav',
                'newreply', 'newthread', 'online', 'polls', 'portal',
                'post', 'postbit', 'posticons', 'printthread', 'private',
                'report', 'reputation', 'search', 'sendthread', 'showteam',
                'showthread', 'smilieinsert', 'stats', 'usercp', 'video',
                'warnings', 'xmlhttp'
            );
        }

        usort($groupPrefixes, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        $map = array();
        foreach ($templateNames as $name) {
            $matched = false;
            foreach ($groupPrefixes as $prefix) {
                if ($name === $prefix || strpos($name, $prefix . '_') === 0) {
                    $map[$name] = $prefix;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $map[$name] = 'ungrouped';
            }
        }

        return $map;
    }

    private function makeSlug($name)
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'unnamed-theme';
    }

    private function escapeCdata($str)
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $str);
    }

    /**
     * Recursively copy a directory.
     */
    private function rcopy($src, $dst)
    {
        if (!is_dir($src)) return false;
        if (!@mkdir($dst, 0755, true)) return false;

        foreach (scandir($src) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $s = $src . '/' . $entry;
            $d = $dst . '/' . $entry;
            if (is_dir($s)) {
                if (!$this->rcopy($s, $d)) return false;
            } else {
                if (!copy($s, $d)) return false;
            }
        }
        return true;
    }

    private function addDirToZip(ZipArchive $zip, $dir, $prefix)
    {
        $zip->addEmptyDir($prefix);
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path    = $dir . '/' . $entry;
            $zipPath = $prefix . '/' . $entry;
            if (is_dir($path)) {
                $this->addDirToZip($zip, $path, $zipPath);
            } else {
                $zip->addFile($path, $zipPath);
            }
        }
    }

    public function rrmdir($dir)
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * Update a single stylesheet row in the database.
     */
    private function updateStylesheet($tid, $name, $content)
    {
        global $db;
        $esc = $db->escape_string($name);
        $query = $db->simple_select('themestylesheets', 'sid',
            "tid='" . (int) $tid . "' AND name='{$esc}'");
        $row = $db->fetch_array($query);

        if ($row) {
            $db->update_query('themestylesheets', array(
                'stylesheet'   => $db->escape_string($content),
                'lastmodified' => TIME_NOW
            ), "sid='" . (int) $row['sid'] . "'");
        }
    }

    /**
     * Update a single template row in the database.
     */
    private function updateTemplate($sid, $name, $content)
    {
        global $db;
        $esc = $db->escape_string($name);
        $query = $db->simple_select('templates', 'tid',
            "sid='" . (int) $sid . "' AND title='{$esc}'");
        $row = $db->fetch_array($query);

        if ($row) {
            $db->update_query('templates', array(
                'template' => $db->escape_string($content),
                'dateline' => TIME_NOW,
                'version'  => '1839'
            ), "tid='" . (int) $row['tid'] . "'");
        }
    }

    /**
     * Write stylesheet cache files and update the theme's stylesheet list.
     */
    private function rebuildStylesheetCache($tid)
    {
        global $db;
        $tid = (int) $tid;

        // Write each stylesheet to the cache directory
        $cacheDir = MYBB_ROOT . 'cache/themes/theme' . $tid;
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $query = $db->simple_select('themestylesheets', 'name, stylesheet', "tid='{$tid}'");
        while ($ss = $db->fetch_array($query)) {
            @file_put_contents($cacheDir . '/' . $ss['name'], $ss['stylesheet']);
        }

        // Use MyBB admin function if available, otherwise skip
        if (!function_exists('update_theme_stylesheet_list')) {
            $adminDir = defined('MYBB_ADMIN_DIR')
                ? MYBB_ADMIN_DIR
                : (MYBB_ROOT . 'admin/');
            $funcsFile = $adminDir . 'inc/functions_themes.php';
            if (file_exists($funcsFile)) {
                @require_once $funcsFile;
            }
        }
        if (function_exists('update_theme_stylesheet_list')) {
            update_theme_stylesheet_list($tid);
        }
    }

    /**
     * Get the file tree for a theme directory.
     *
     * @param  string $slug
     * @return array|false  Tree structure or false
     */
    public function getFileTree($slug)
    {
        $themeDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        if (!is_dir($themeDir)) return false;
        return $this->buildFileTreeNode($themeDir, $slug);
    }

    /**
     * Read a file from a theme directory (path-traversal safe).
     *
     * @return array|false  {content, size, mtime} or false
     */
    public function readThemeFile($slug, $relativePath)
    {
        $themeDir = realpath(MYBB_ROOT . self::THEMES_DIR . '/' . $slug);
        if (!$themeDir) return false;

        $filePath = realpath($themeDir . '/' . $relativePath);
        if (!$filePath || strpos($filePath, $themeDir) !== 0) return false;
        if (!is_file($filePath)) return false;

        return array(
            'content' => file_get_contents($filePath),
            'size'    => filesize($filePath),
            'mtime'   => filemtime($filePath)
        );
    }

    /**
     * Write a file inside a theme directory (path-traversal safe).
     * Optionally does an immediate targeted sync for that one file.
     *
     * @return bool
     */
    public function writeThemeFile($slug, $relativePath, $content, $syncToDB = true)
    {
        // Block executable file extensions
        if (!$this->isSafeExtension($relativePath)) return false;

        $baseDir  = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        $realBase = realpath($baseDir);
        if (!$realBase) return false;

        // Normalise separator
        $relNorm  = str_replace('\\', '/', $relativePath);
        $fullPath = $realBase . DIRECTORY_SEPARATOR
                  . str_replace('/', DIRECTORY_SEPARATOR, $relNorm);

        // Ensure parent directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        // Verify target is inside the theme directory
        $realDir = realpath($dir);
        if (!$realDir || strpos($realDir, $realBase) !== 0) return false;

        // Reject hidden files
        if (basename($relativePath)[0] === '.') return false;

        $result = file_put_contents($fullPath, $content);
        if ($result === false) return false;

        if ($syncToDB) {
            $this->syncSingleFile($slug, $relNorm, $content);
        }
        return true;
    }

    /**
     * Sync a single changed file to the database (called after editor save).
     */
    private function syncSingleFile($slug, $relativePath, $content)
    {
        global $db;

        $themeDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        $cfg = @json_decode(file_get_contents($themeDir . '/theme.json'), true);
        if (!$cfg || !isset($cfg['name'])) return;

        $name_esc = $db->escape_string($cfg['name']);
        $query = $db->simple_select('themes', 'tid, properties',
            "name='{$name_esc}' AND tid != 1");
        $theme = $db->fetch_array($query);
        if (!$theme) return;

        $tid   = (int) $theme['tid'];
        $props = my_unserialize($theme['properties']);
        $sid   = isset($props['templateset']) ? (int) $props['templateset'] : 0;
        $ext   = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        if ($ext === 'css' && strpos($relativePath, 'css/') === 0) {
            $this->updateStylesheet($tid, basename($relativePath), $content);
            $this->rebuildStylesheetCache($tid);
        } elseif ($ext === 'html' && strpos($relativePath, 'templates/') === 0) {
            $tplName = pathinfo($relativePath, PATHINFO_FILENAME);
            $this->updateTemplate($sid, $tplName, $content);
        } elseif ($ext === 'js' && strpos($relativePath, 'js/') === 0) {
            $this->deployJs($themeDir);
        }
    }

    /**
     * Build a file tree node (recursive).
     */
    private function buildFileTreeNode($dir, $name)
    {
        $node = array(
            'name'     => $name,
            'type'     => 'directory',
            'children' => array()
        );

        $dirs  = array();
        $files = array();

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') continue;
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $dirs[] = $entry;
            } else {
                $files[] = $entry;
            }
        }

        sort($dirs);
        sort($files);

        foreach ($dirs as $d) {
            $node['children'][] = $this->buildFileTreeNode($dir . '/' . $d, $d);
        }
        foreach ($files as $f) {
            $node['children'][] = array(
                'name' => $f,
                'type' => 'file',
                'ext'  => strtolower(pathinfo($f, PATHINFO_EXTENSION)),
                'size' => filesize($dir . '/' . $f)
            );
        }

        return $node;
    }

    /**
     * Create a new file inside a theme directory.
     *
     * @param  string $slug         Theme slug
     * @param  string $relativePath Relative path for the new file
     * @param  string $content      Initial content (default empty)
     * @return bool
     */
    public function createThemeFile($slug, $relativePath, $content = '')
    {
        // Block executable file extensions
        if (!$this->isSafeExtension($relativePath)) {
            $this->errors[] = 'This file type is not allowed. Only theme assets (HTML, CSS, JS, JSON, etc.) can be created.';
            return false;
        }

        $baseDir  = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        $realBase = realpath($baseDir);
        if (!$realBase) {
            $this->errors[] = 'Theme directory not found.';
            return false;
        }

        $relNorm  = str_replace('\\', '/', $relativePath);
        if (basename($relNorm)[0] === '.') {
            $this->errors[] = 'Hidden files are not allowed.';
            return false;
        }

        $fullPath = $realBase . DIRECTORY_SEPARATOR
                  . str_replace('/', DIRECTORY_SEPARATOR, $relNorm);

        if (file_exists($fullPath)) {
            $this->errors[] = 'File already exists.';
            return false;
        }

        $dir = dirname($fullPath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $realDir = realpath($dir);
        if (!$realDir || strpos($realDir, $realBase) !== 0) {
            $this->errors[] = 'Invalid path.';
            return false;
        }

        $result = file_put_contents($fullPath, $content);
        return $result !== false;
    }

    /**
     * Create a new folder inside a theme directory.
     *
     * @param  string $slug         Theme slug
     * @param  string $relativePath Relative path for the new folder
     * @return bool
     */
    public function createThemeFolder($slug, $relativePath)
    {
        $baseDir  = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        $realBase = realpath($baseDir);
        if (!$realBase) {
            $this->errors[] = 'Theme directory not found.';
            return false;
        }

        $relNorm  = str_replace('\\', '/', $relativePath);
        $fullPath = $realBase . DIRECTORY_SEPARATOR
                  . str_replace('/', DIRECTORY_SEPARATOR, $relNorm);

        if (file_exists($fullPath) || is_dir($fullPath)) {
            $this->errors[] = 'Folder already exists.';
            return false;
        }

        // Verify parent is inside theme dir
        $parent = dirname($fullPath);
        if (!is_dir($parent)) @mkdir($parent, 0755, true);
        $realParent = realpath($parent);
        if (!$realParent || strpos($realParent, $realBase) !== 0) {
            $this->errors[] = 'Invalid path.';
            return false;
        }

        return @mkdir($fullPath, 0755, true);
    }

    /**
     * Delete a file inside a theme directory.
     *
     * @param  string $slug         Theme slug
     * @param  string $relativePath Relative path to the file
     * @return bool
     */
    public function deleteThemeFile($slug, $relativePath)
    {
        $baseDir  = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        $realBase = realpath($baseDir);
        if (!$realBase) return false;

        $relNorm  = str_replace('\\', '/', $relativePath);
        $fullPath = realpath($realBase . DIRECTORY_SEPARATOR
                  . str_replace('/', DIRECTORY_SEPARATOR, $relNorm));

        if (!$fullPath || strpos($fullPath, $realBase) !== 0) return false;
        if (!is_file($fullPath)) return false;

        return @unlink($fullPath);
    }

    /**
     * Delete a folder (and all contents) inside a theme directory.
     *
     * @param  string $slug         Theme slug
     * @param  string $relativePath Relative path to the folder
     * @return bool
     */
    public function deleteThemeFolder($slug, $relativePath)
    {
        $baseDir  = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        $realBase = realpath($baseDir);
        if (!$realBase) return false;

        $relNorm  = str_replace('\\', '/', $relativePath);
        $fullPath = realpath($realBase . DIRECTORY_SEPARATOR
                  . str_replace('/', DIRECTORY_SEPARATOR, $relNorm));

        if (!$fullPath || strpos($fullPath, $realBase) !== 0) return false;
        if ($fullPath === $realBase) return false; // Don't delete theme root
        if (!is_dir($fullPath)) return false;

        $this->rrmdir($fullPath);
        return !is_dir($fullPath);
    }

    /**
     * Rename/move a file or folder inside a theme directory.
     *
     * @param  string $slug    Theme slug
     * @param  string $oldPath Old relative path
     * @param  string $newPath New relative path
     * @return bool
     */
    public function renameThemePath($slug, $oldPath, $newPath)
    {
        // Block renaming to executable file extension
        $newExt = pathinfo($newPath, PATHINFO_EXTENSION);
        if ($newExt !== '' && !$this->isSafeExtension($newPath)) return false;

        $baseDir  = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        $realBase = realpath($baseDir);
        if (!$realBase) return false;

        $oldFull = realpath($realBase . DIRECTORY_SEPARATOR
                 . str_replace('/', DIRECTORY_SEPARATOR, $oldPath));
        if (!$oldFull || strpos($oldFull, $realBase) !== 0) return false;

        $newFull = $realBase . DIRECTORY_SEPARATOR
                 . str_replace('/', DIRECTORY_SEPARATOR, $newPath);

        // Ensure new parent exists
        $newDir = dirname($newFull);
        if (!is_dir($newDir)) @mkdir($newDir, 0755, true);
        $realNewDir = realpath($newDir);
        if (!$realNewDir || strpos($realNewDir, $realBase) !== 0) return false;

        return @rename($oldFull, $newFull);
    }

    /**
     * Get a flat list of all file paths in a theme directory (for search).
     *
     * @param  string $slug
     * @return array|false  Array of relative file paths, or false
     */
    public function getFlatFileList($slug)
    {
        $themeDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        if (!is_dir($themeDir)) return false;

        $files = array();
        $this->collectFiles($themeDir, '', $files);
        sort($files);
        return $files;
    }

    /**
     * Recursively collect all file paths.
     */
    private function collectFiles($dir, $prefix, &$results)
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') continue;
            $path = $dir . '/' . $entry;
            $rel  = ($prefix !== '') ? $prefix . '/' . $entry : $entry;
            if (is_dir($path)) {
                $this->collectFiles($path, $rel, $results);
            } else {
                $results[] = $rel;
            }
        }
    }

    /**
     * Read the theme options definition from functions/options.php.
     *
     * The file must return an associative array of option definitions:
     *   return array(
     *       'option_key' => array(
     *           'title'       => 'Display Title',
     *           'description' => 'Help text',
     *           'type'        => 'text|textarea|yesno|select|color|numeric',
     *           'default'     => 'default_value',
     *           'options'     => array('key' => 'Label', ...),  // for select type
     *       ),
     *       ...
     *   );
     *
     * @param  string $slug
     * @return array|false  Options definition array or false
     */
    public function getThemeOptions($slug)
    {
        $file = MYBB_ROOT . self::THEMES_DIR . '/' . $slug . '/functions/options.php';
        if (!file_exists($file)) return false;

        $options = @include $file;
        if (!is_array($options) || empty($options)) return false;
        return $options;
    }

    /**
     * Check if a theme has a functions/ directory with any PHP files.
     *
     * @param  string $slug
     * @return bool
     */
    public function themeHasFunctions($slug)
    {
        $dir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug . '/functions';
        if (!is_dir($dir)) return false;

        foreach (scandir($dir) as $e) {
            if (pathinfo($e, PATHINFO_EXTENSION) === 'php') return true;
        }
        return false;
    }

    /**
     * Get saved option values for a theme.
     * Values are stored in themes/{slug}/default.json.
     *
     * @param  string $slug
     * @return array  Key-value pairs
     */
    public function getThemeOptionValues($slug)
    {
        $file = MYBB_ROOT . self::THEMES_DIR . '/' . $slug . '/default.json';
        if (!file_exists($file)) return array();

        $data = @json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : array();
    }

    /**
     * Save option values for a theme.
     *
     * @param  string $slug
     * @param  array  $values Key-value pairs
     * @return bool
     */
    public function saveThemeOptionValues($slug, $values)
    {
        $themeDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        if (!is_dir($themeDir)) return false;

        $file = $themeDir . '/default.json';
        $json = json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return file_put_contents($file, $json) !== false;
    }

    /**
     * Get merged theme option values (defaults + saved overrides).
     *
     * @param  string $slug
     * @return array  Key-value pairs with defaults filled in
     */
    public function getMergedThemeOptions($slug)
    {
        $options = $this->getThemeOptions($slug);
        if (!$options) return array();

        $saved  = $this->getThemeOptionValues($slug);
        $merged = array();

        foreach ($options as $key => $def) {
            $merged[$key] = isset($saved[$key]) ? $saved[$key] : (isset($def['default']) ? $def['default'] : '');

            // For image fields with dimensions, also merge the sub-keys
            if (isset($def['type']) && $def['type'] === 'image' && !empty($def['has_dimensions'])) {
                $merged[$key . '_width']  = isset($saved[$key . '_width'])  ? $saved[$key . '_width']  : (isset($def['default_width'])  ? $def['default_width']  : '');
                $merged[$key . '_height'] = isset($saved[$key . '_height']) ? $saved[$key . '_height'] : (isset($def['default_height']) ? $def['default_height'] : '');
            }
        }

        return $merged;
    }

    /**
     * Load language files from the active theme's lang/ directory.
     *
     * Expected structure: themes/{slug}/lang/{language}/*.lang.php
     * Each file sets variables on the $l array (same convention as MyBB lang files):
     *   $l['my_theme_welcome'] = 'Welcome!';
     *
     * @param  string $slug     Theme slug
     * @param  string $langName MyBB language name (e.g. 'english')
     * @return array  Loaded language strings (merged into $lang)
     */
    public function loadThemeLang($slug, $langName)
    {
        $langDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug . '/lang/' . $langName;
        $strings = array();

        // Fallback to english if the requested language folder doesn't exist
        if (!is_dir($langDir)) {
            $langDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug . '/lang/english';
        }

        if (!is_dir($langDir)) return $strings;

        foreach (scandir($langDir) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') continue;
            if (substr($file, -9) !== '.lang.php' && substr($file, -4) === '.php') {
                // Accept both .lang.php and .php files
            }

            $l = array();
            @include $langDir . '/' . $file;
            if (!empty($l)) {
                $strings = array_merge($strings, $l);
            }
        }

        return $strings;
    }

    /**
     * Get the slug of the currently active (default) theme.
     *
     * @return string|false
     */
    public function getActiveThemeSlug()
    {
        global $db;

        $query = $db->simple_select('themes', 'name', "def='1'", array('limit' => 1));
        $theme = $db->fetch_array($query);
        if (!$theme) return false;

        return $this->makeSlug($theme['name']);
    }

    /**
     * makeSlug is private — expose it publicly for external use.
     */
    public function slug($name)
    {
        return $this->makeSlug($name);
    }

    /**
     * Compute a hash of all theme file modification times.
     *
     * Recursively scans the theme directory (templates, css, js, functions,
     * plugins, theme.json, etc.) and builds a hash from all file paths and
     * their last-modified timestamps. This allows cheap change detection
     * without reading file contents.
     *
     * @param  string $slug  Theme slug
     * @return string        MD5 hash representing the current file state
     */
    public function getThemeFilesHash($slug)
    {
        $themeDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        if (!is_dir($themeDir)) return '';

        $entries = array();
        $this->collectFileTimestamps($themeDir, '', $entries);
        sort($entries); // deterministic order
        return md5(implode("\n", $entries));
    }

    /**
     * Recursively collect file paths and their modification times.
     *
     * @param  string $dir      Absolute directory path
     * @param  string $prefix   Relative path prefix for deterministic hashing
     * @param  array  &$entries Accumulated entries (path|mtime)
     */
    private function collectFileTimestamps($dir, $prefix, &$entries)
    {
        $scan = @scandir($dir);
        if (!$scan) return;

        foreach ($scan as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            $rel  = ($prefix !== '') ? $prefix . '/' . $entry : $entry;

            if (is_dir($path)) {
                $this->collectFileTimestamps($path, $rel, $entries);
            } elseif (is_file($path)) {
                $entries[] = $rel . '|' . filemtime($path) . '|' . filesize($path);
            }
        }
    }

    /**
     * Discover mini plugins inside a theme's functions/plugins/ directory.
     *
     * Each mini plugin lives in its own sub-folder and must contain at
     * minimum a plugin.json manifest:
     *   {
     *     "name":        "My Plugin",
     *     "description": "What it does",
     *     "version":     "1.0.0",
     *     "author":      "Name"
     *   }
     *
     * Optional files:
     *   init.php     — loaded on every frontend page (registers hooks)
     *   options.php  — returns array of option definitions (like theme options)
     *   admin.php    — admin page content (rendered in ACP Plugins tab)
     *   js/          — JS assets injected into frontend
     *   css/         — CSS assets injected into frontend
     *
     * @param  string $slug  Theme slug
     * @return array         Array of plugin info arrays
     */
    public function listMiniPlugins($slug)
    {
        return $this->listModules($slug);
    }

    /**
     * List all built-in modules for a theme.
     * Scans themes/{slug}/functions/modules/ for subdirectories with a plugin.json.
     *
     * @param  string $slug  Theme slug
     * @return array         Array of module info arrays
     */
    public function listModules($slug)
    {
        $modulesDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug . '/functions/modules';
        $result = array();
        if (!is_dir($modulesDir)) return $result;

        foreach (scandir($modulesDir) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') continue;
            $dir = $modulesDir . '/' . $entry;
            if (!is_dir($dir)) continue;

            $manifestPath = $dir . '/plugin.json';
            if (!file_exists($manifestPath)) continue;

            $manifest = @json_decode(file_get_contents($manifestPath), true);
            if (!$manifest || !isset($manifest['name'])) continue;

            $result[] = array(
                'id'          => $entry,
                'name'        => $manifest['name'],
                'description' => isset($manifest['description']) ? $manifest['description'] : '',
                'version'     => isset($manifest['version']) ? $manifest['version'] : '1.0.0',
                'author'      => isset($manifest['author']) ? $manifest['author'] : '',
                'has_init'    => file_exists($dir . '/init.php'),
                'has_options' => file_exists($dir . '/options.php'),
                'has_admin'   => file_exists($dir . '/admin.php'),
                'has_js'      => is_dir($dir . '/js'),
                'has_css'     => is_dir($dir . '/css'),
                'dir'         => $dir,
            );
        }

        return $result;
    }

    /**
     * Get module option values. Stored per-module in default.json.
     *
     * @param  string $slug      Theme slug
     * @param  string $pluginId  Module directory name
     * @return array
     */
    public function getMiniPluginOptionValues($slug, $pluginId)
    {
        $file = MYBB_ROOT . self::THEMES_DIR . '/' . $slug
              . '/functions/modules/' . $pluginId . '/default.json';
        if (!file_exists($file)) return array();
        $data = @json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : array();
    }

    /**
     * Save module option values.
     *
     * @param  string $slug
     * @param  string $pluginId
     * @param  array  $values
     * @return bool
     */
    public function saveMiniPluginOptionValues($slug, $pluginId, $values)
    {
        $dir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug
             . '/functions/modules/' . $pluginId;
        if (!is_dir($dir)) return false;
        $file = $dir . '/default.json';
        return file_put_contents($file, json_encode($values, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Get module option definitions.
     *
     * @param  string $slug
     * @param  string $pluginId
     * @return array|false
     */
    public function getMiniPluginOptions($slug, $pluginId)
    {
        $file = MYBB_ROOT . self::THEMES_DIR . '/' . $slug
              . '/functions/modules/' . $pluginId . '/options.php';
        if (!file_exists($file)) return false;
        $options = @include $file;
        if (!is_array($options) || empty($options)) return false;
        return $options;
    }

    /**
     * Get merged module option values (defaults + saved).
     *
     * @param  string $slug
     * @param  string $pluginId
     * @return array
     */
    public function getMergedMiniPluginOptions($slug, $pluginId)
    {
        $options = $this->getMiniPluginOptions($slug, $pluginId);
        if (!$options) return array();

        $saved  = $this->getMiniPluginOptionValues($slug, $pluginId);
        $merged = array();
        foreach ($options as $def) {
            $id = isset($def['id']) ? $def['id'] : '';
            if (empty($id)) continue;
            $merged[$id] = isset($saved[$id]) ? $saved[$id]
                          : (isset($def['default']) ? $def['default'] : '');
        }
        return $merged;
    }

    /**
     * Load and initialise all built-in modules for a theme (frontend).
     * Called from ms_load_theme_extras().
     *
     * @param  string $slug
     */
    public function loadModules($slug)
    {
        $modules = $this->listModules($slug);

        foreach ($modules as $p) {
            if ($p['has_init']) {
                $ms_plugin_options = $this->getMergedMiniPluginOptions($slug, $p['id']);
                $ms_plugin_dir = $p['dir'];
                $ms_plugin_id = $p['id'];
                $ms_theme_slug = $slug;

                include_once $p['dir'] . '/init.php';
            }
        }
    }

    /**
     * Collect frontend assets (JS/CSS) for all built-in modules.
     *
     * @param  string $slug
     * @return array  ['js' => [...urls], 'css' => [...urls]]
     */
    public function getModuleAssets($slug)
    {
        $modules = $this->listModules($slug);
        $assets  = array('js' => array(), 'css' => array());

        foreach ($modules as $p) {
            $webBase = self::THEMES_DIR . '/' . $slug . '/functions/modules/' . $p['id'];

            if ($p['has_css']) {
                $cssDir = $p['dir'] . '/css';
                foreach (scandir($cssDir) as $f) {
                    if (pathinfo($f, PATHINFO_EXTENSION) === 'css') {
                        $mtime = filemtime($cssDir . '/' . $f);
                        $assets['css'][] = $webBase . '/css/' . $f . '?v=' . $mtime;
                    }
                }
            }
            if ($p['has_js']) {
                $jsDir = $p['dir'] . '/js';
                foreach (scandir($jsDir) as $f) {
                    if (pathinfo($f, PATHINFO_EXTENSION) === 'js') {
                        $mtime = filemtime($jsDir . '/' . $f);
                        $assets['js'][] = $webBase . '/js/' . $f . '?v=' . $mtime;
                    }
                }
            }
        }

        return $assets;
    }
}
