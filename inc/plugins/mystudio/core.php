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
     * Plugin-owned code (relative to MYBB_ROOT). Themes hold only templates,
     * CSS, JS, images and JSON; all PHP lives under the plugin/admin dirs:
     *   inc/plugins/mystudio/modules/{id}/        module runtime (init.php, assets, default.json)
     *   inc/plugins/mystudio/themes/{slug}/       theme hooks.php + lang/
     *   {admin}/modules/mystudio/modules/{id}/    module options.php (option schema)
     *   {admin}/modules/mystudio/themes/{slug}/   theme options.php (option schema)
     */
    const PLUGIN_DIR = 'inc/plugins/mystudio';

    /**
     * Absolute path to the MyBB admin directory (with trailing slash).
     *
     * @return string
     */
    public function getAdminPath()
    {
        if (defined('MYBB_ADMIN_DIR')) {
            return MYBB_ADMIN_DIR;
        }
        global $config;
        $adminDir = !empty($config['admin_dir']) ? $config['admin_dir'] : 'admin';
        return MYBB_ROOT . $adminDir . '/';
    }

    /** Absolute path to the modules directory. */
    public function getModulesDir()
    {
        return MYBB_ROOT . self::PLUGIN_DIR . '/modules';
    }

    /** Absolute path to a theme's PHP code directory (hooks.php, lang/). */
    public function getThemeCodeDir($slug)
    {
        return MYBB_ROOT . self::PLUGIN_DIR . '/themes/' . $slug;
    }

    /** Absolute path to a module's admin directory (options.php). */
    public function getModuleAdminDir($moduleId)
    {
        return $this->getAdminPath() . 'modules/mystudio/modules/' . $moduleId;
    }

    /** Absolute path to a theme's admin directory (options.php). */
    public function getThemeAdminDir($slug)
    {
        return $this->getAdminPath() . 'modules/mystudio/themes/' . $slug;
    }

    /**
     * Move PHP out of an imported theme directory into the plugin/admin dirs.
     * Accepts the legacy package layout (functions/, functions/modules/, lang/).
     *
     * @param string $themeDir Absolute path to themes/{slug}
     * @param string $slug
     */
    private function relocateThemeCode($themeDir, $slug)
    {
        $funcDir = $themeDir . '/functions';
        $codeDir = $this->getThemeCodeDir($slug);

        if (is_dir($funcDir . '/modules')) {
            foreach (scandir($funcDir . '/modules') as $id) {
                if ($id === '.' || $id === '..' || $id[0] === '.') continue;
                $src = $funcDir . '/modules/' . $id;
                if (!is_dir($src) || !preg_match('/^[a-z0-9\-_]+$/', $id)) continue;
                // A theme package must never replace a core module
                if (in_array($id, self::CORE_MODULES, true)) {
                    $this->errors[] = 'Skipped bundled module "' . htmlspecialchars_uni($id) . '": it would replace a core MyStudio module.';
                    continue;
                }
                $dst = $this->getModulesDir() . '/' . $id;
                // Keep the forum's saved settings when updating an installed module
                $savedValues = file_exists($dst . '/default.json') ? file_get_contents($dst . '/default.json') : null;
                $this->rrmdir($dst);
                $this->rcopy($src, $dst);
                if ($savedValues !== null) {
                    file_put_contents($dst . '/default.json', $savedValues);
                }
                $this->moveModuleOptionsToAdmin($id);
            }
            $this->rrmdir($funcDir . '/modules');
        }

        if (file_exists($funcDir . '/options.php')) {
            $adminDir = $this->getThemeAdminDir($slug);
            @mkdir($adminDir, 0755, true);
            @rename($funcDir . '/options.php', $adminDir . '/options.php');
        }

        if (is_dir($funcDir) || is_dir($themeDir . '/lang')) {
            $this->rrmdir($codeDir);
            if (is_dir($funcDir)) {
                $this->rcopy($funcDir, $codeDir);
                $this->rrmdir($funcDir);
            }
            if (is_dir($themeDir . '/lang')) {
                @mkdir($codeDir, 0755, true);
                $this->rcopy($themeDir . '/lang', $codeDir . '/lang');
                $this->rrmdir($themeDir . '/lang');
            }
        }
    }

    /**
     * Move a module's options.php (option schema) into the admin dir.
     *
     * @param string $moduleId
     */
    public function moveModuleOptionsToAdmin($moduleId)
    {
        $src = $this->getModulesDir() . '/' . $moduleId . '/options.php';
        if (!file_exists($src)) return;
        $adminDir = $this->getModuleAdminDir($moduleId);
        @mkdir($adminDir, 0755, true);
        @rename($src, $adminDir . '/options.php');
    }

    /**
     * Allowed file extensions for editing/creating via the editor.
     * Executable extensions (php, phtml, phar, etc.) are blocked to prevent RCE.
     */
    const ALLOWED_EDIT_EXTENSIONS = array(
        'html', 'htm', 'css', 'js', 'json', 'txt', 'md',
        'xml', 'svg', 'ini', 'yml', 'yaml', 'less', 'scss',
        'map', 'csv', 'log', 'tpl', 'mustache', 'hbs',
    );

    /** Binary asset types a theme may ship besides the editable text types. */
    const ALLOWED_ASSET_EXTENSIONS = array(
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'bmp',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'mp3', 'mp4', 'webm', 'ogg', 'wav', 'pdf',
    );

    /**
     * List files in an unpacked theme that must not end up in web-reachable themes/{slug}/:
     * anything that isn't a known text/asset type, and dotfiles (.htaccess, .user.ini …).
     * functions/ and lang/ are skipped — relocateThemeCode() moves them out of themes/.
     *
     * @param  string $themeRoot
     * @return string[] Relative paths of offending files
     */
    private function findDisallowedThemeFiles($themeRoot)
    {
        $bad = array();
        // .ini is editable in the editor but never shipped: a stray php.ini can change PHP behaviour
        $allowed = array_diff(array_merge(self::ALLOWED_EDIT_EXTENSIONS, self::ALLOWED_ASSET_EXTENSIONS), array('ini'));
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($themeRoot) + 1));
            $top = strtok($rel, '/');
            if ($top === 'functions' || $top === 'lang') continue;
            if ($file->isLink() || basename($rel)[0] === '.') {
                $bad[] = $rel;
                continue;
            }
            if ($file->isFile() && !in_array(strtolower($file->getExtension()), $allowed, true)) {
                $bad[] = $rel;
            }
        }
        return $bad;
    }

    /**
     * MyBB eval()s templates, so content must pass the same check_template()
     * MyBB applies to ACP template edits — otherwise {${...}} runs PHP.
     *
     * @param  string $content
     * @return bool   True if the template is unsafe
     */
    private function isUnsafeTemplate($content)
    {
        if (!function_exists('check_template')) {
            require_once $this->getAdminPath() . 'inc/functions.php';
        }
        return check_template($content);
    }

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

        // ── Extract to temp ──
        $tmpDir = sys_get_temp_dir() . '/mystudio_' . uniqid();
        if (!@mkdir($tmpDir, 0755, true)) {
            $this->errors[] = 'Cannot create temporary directory.';
            return false;
        }

        $zip = $this->newZip();
        if ($zip->open($file['tmp_name']) !== true) {
            $this->errors[] = 'Cannot open ZIP archive.' . (!empty($zip->error) ? ' ' . htmlspecialchars_uni($zip->error) : '');
            $this->rrmdir($tmpDir);
            return false;
        }

        // Zip-bomb guard: cap entry count and total uncompressed size
        if ($zip->numFiles > 5000) {
            $this->errors[] = 'ZIP archive contains too many files (max 5000).';
            $zip->close();
            $this->rrmdir($tmpDir);
            return false;
        }
        $totalSize = 0;

        // Validate ZIP entries for path traversal (ZIP Slip prevention)
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            $stat = $zip->statIndex($i);
            $totalSize += $stat ? (int) $stat['size'] : 0;
            if ($totalSize > $maxBytes * 10) {
                $this->errors[] = 'ZIP archive expands to more than ' . (int) ($maxBytes * 10 / 1048576) . ' MB.';
                $zip->close();
                $this->rrmdir($tmpDir);
                return false;
            }
            // Reject entries with path traversal sequences
            if (strpos($entryName, '..') !== false || strpos($entryName, '\\') !== false) {
                $this->errors[] = 'ZIP archive contains unsafe path entry: ' . htmlspecialchars_uni($entryName);
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
            if ($entryName === '' || $entryName[0] === '/' || strpos($entryName, ':') !== false) {
                $this->errors[] = 'ZIP archive contains absolute path entry: ' . htmlspecialchars_uni($entryName);
                $zip->close();
                $this->rrmdir($tmpDir);
                return false;
            }
        }

        if ($zip->extractTo($tmpDir) === false) {
            $this->errors[] = 'Could not extract the ZIP archive.' . (!empty($zip->error) ? ' ' . htmlspecialchars_uni($zip->error) : '');
            $zip->close();
            $this->rrmdir($tmpDir);
            return false;
        }
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

        // ── Only theme assets may land in the web-reachable themes/ dir ──
        // PHP is allowed solely in functions/ and lang/, which relocateThemeCode() moves out.
        $badFiles = $this->findDisallowedThemeFiles($themeRoot);
        if ($badFiles) {
            $this->errors[] = 'The theme contains files that are not allowed in a theme folder (only functions/ and lang/ may hold PHP): '
                . htmlspecialchars_uni(implode(', ', array_slice($badFiles, 0, 10))) . (count($badFiles) > 10 ? ', …' : '');
            $this->rrmdir($tmpDir);
            return false;
        }

        // ── Packages that bring PHP (functions/, lang/, bundled modules) install code that
        // runs on every page, so importing one is equivalent to server access: super admins only.
        global $mybb;
        if ((is_dir($themeRoot . '/functions') || is_dir($themeRoot . '/lang'))
            && !is_super_admin((int) $mybb->user['uid'])) {
            $this->errors[] = 'This theme package contains PHP code (functions/ or lang/), which would run on your server. '
                . 'Only a super administrator can import it. Review the code first, or import a package without PHP.';
            $this->rrmdir($tmpDir);
            return false;
        }

        // ── Read theme.json for name/slug ──
        $cfg  = json_decode(file_get_contents($themeRoot . '/theme.json'), true);
        $slug = $this->makeSlug($cfg['name']);
        if ($slug === '') {
            $this->errors[] = 'theme.json has no usable "name".';
            $this->rrmdir($tmpDir);
            return false;
        }

        // ── Copy to persistent location ──
        $destDir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        if (is_dir($destDir)) {
            // Updating in place is fine for the same theme; never clobber a different one whose name slugs alike
            $existingCfg = @json_decode(@file_get_contents($destDir . '/theme.json'), true);
            if (!empty($existingCfg['name']) && $existingCfg['name'] !== $cfg['name']) {
                $this->errors[] = 'Folder ' . self::THEMES_DIR . '/' . $slug . '/ already belongs to the theme "'
                    . htmlspecialchars_uni($existingCfg['name']) . '". Rename the imported theme.';
                $this->rrmdir($tmpDir);
                return false;
            }
        }
        @mkdir(MYBB_ROOT . self::THEMES_DIR, 0755, true);

        // Stage next to the destination, then swap, so a failed copy never loses the old theme
        $stageDir  = MYBB_ROOT . self::THEMES_DIR . '/.' . $slug . '.importing';
        $backupDir = MYBB_ROOT . self::THEMES_DIR . '/.' . $slug . '.previous';
        $this->rrmdir($stageDir);
        $this->rrmdir($backupDir);
        if (!$this->rcopy($themeRoot, $stageDir)) {
            $this->errors[] = 'Failed to copy theme files to ' . self::THEMES_DIR . '/' . $slug . '/';
            $this->rrmdir($stageDir);
            $this->rrmdir($tmpDir);
            return false;
        }
        $this->rrmdir($tmpDir);

        if (is_dir($destDir) && !@rename($destDir, $backupDir)) {
            $this->errors[] = 'Could not replace ' . self::THEMES_DIR . '/' . $slug . '/ (is a file in it open?).';
            $this->rrmdir($stageDir);
            return false;
        }
        if (!@rename($stageDir, $destDir)) {
            if (is_dir($backupDir)) @rename($backupDir, $destDir);
            $this->errors[] = 'Failed to move the imported theme into ' . self::THEMES_DIR . '/' . $slug . '/';
            $this->rrmdir($stageDir);
            return false;
        }
        $this->rrmdir($backupDir);

        // ── Move any PHP (functions/, lang/) out of the theme dir ──
        $this->relocateThemeCode($destDir, $slug);

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
        $this->tempDir = sys_get_temp_dir() . '/mystudio_' . uniqid();
        @mkdir($this->tempDir, 0755, true);

        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $theme['name']);
        $zipPath  = $this->tempDir . '/' . $safeName . '.zip';

        $zip = $this->newZip();
        if ($zip->open($zipPath, $zip instanceof ZipArchive ? ZipArchive::CREATE : MsZipArchive::CREATE) !== true) {
            $this->errors[] = 'Failed to create ZIP archive.';
            return false;
        }

        $this->addDirToZip($zip, $themeDir, $safeName);

        // Re-pack theme PHP in the portable package layout (functions/, lang/)
        $codeDir = $this->getThemeCodeDir($slug);
        if (is_dir($codeDir)) {
            foreach (scandir($codeDir) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $path = $codeDir . '/' . $entry;
                if ($entry === 'lang' && is_dir($path)) {
                    $this->addDirToZip($zip, $path, $safeName . '/lang');
                } elseif (is_dir($path)) {
                    $this->addDirToZip($zip, $path, $safeName . '/functions/' . $entry);
                } else {
                    $zip->addFile($path, $safeName . '/functions/' . $entry);
                }
            }
        }
        $optionsFile = $this->getThemeAdminDir($slug) . '/options.php';
        if (file_exists($optionsFile)) {
            $zip->addFile($optionsFile, $safeName . '/functions/options.php');
        }
        if ($zip->close() === false) {
            $this->errors[] = 'Failed to write the ZIP archive.';
            return false;
        }

        return $zipPath;
    }

    /**
     * ZIP handler for import/export: PHP's ZipArchive when the zip extension is
     * installed, otherwise MyStudio's pure-PHP MsZipArchive (same subset of methods).
     *
     * @return ZipArchive|MsZipArchive
     */
    private function newZip()
    {
        if (class_exists('ZipArchive') && !defined('MS_FORCE_PHP_ZIP')) {
            return new ZipArchive();
        }
        require_once MYBB_ROOT . self::PLUGIN_DIR . '/zip.php';
        return new MsZipArchive();
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
            // DB names become file names: never let one escape css/ or become e.g. evil.php
            if (!$this->isSafeFileName($ss['name']) || substr(strtolower($ss['name']), -4) !== '.css') {
                $this->errors[] = 'Skipped stylesheet "' . htmlspecialchars_uni($ss['name']) . '": not a plain .css file name.';
                continue;
            }
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
            if (!$this->isSafeFileName($name)) {
                $this->errors[] = 'Skipped template "' . htmlspecialchars_uni($name) . '": its title is not a safe file name.';
                continue;
            }
            $folder = isset($folderMap[$name]) ? $folderMap[$name] : 'ungrouped';
            if (!$this->isSafeFileName($folder)) $folder = 'ungrouped';
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

        if (!is_string($slug) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-]*$/', $slug)) {
            $this->errors[] = 'Invalid theme folder name.';
            return false;
        }
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
            if ($this->isUnsafeTemplate($content)) {
                // Leave the DB copy untouched (and don't delete it below)
                $this->errors[] = 'Template "' . htmlspecialchars_uni($name) . '" failed the MyBB template security check and was not synced.';
                unset($dbTemplates[$name]);
                continue;
            }
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

        // theme.json comes from (possibly third-party) ZIPs and these values are echoed
        // unescaped into templates ({$theme['editortheme']}, {$theme['imgdir']}), so whitelist them
        $props = isset($cfg['properties']) ? $cfg['properties'] : array();
        $editortheme = isset($props['editortheme']) && preg_match('/^[A-Za-z0-9_\-.]+\.css$/', $props['editortheme'])
            ? $props['editortheme'] : 'default.css';
        $tablespace  = (string) (isset($props['tablespace'])  ? (int) $props['tablespace']  : 0);
        $borderwidth = (string) (isset($props['borderwidth']) ? (int) $props['borderwidth'] : 0);
        $color       = isset($props['color']) && preg_match('/^[A-Za-z0-9_\- #]*$/', $props['color']) ? $props['color'] : '';

        // imgdir — use theme.json value, or auto-detect, or MyBB default
        $slug = $this->makeSlug($cfg['name']);
        if (isset($props['imgdir']) && preg_match('#^[A-Za-z0-9_\-./]+$#', $props['imgdir'])
            && !preg_match('#(^|/)\.\.(/|$)#', $props['imgdir'])) {
            $imgdir = $props['imgdir'];
        } elseif (is_dir($themeDir . '/images')) {
            $imgdir = self::THEMES_DIR . '/' . $slug . '/images';
        } else {
            $imgdir = 'images';
        }
        $imgdir = $this->escapeCdata($imgdir);

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
                $name       = isset($ss['name']) ? (string) $ss['name'] : '';
                $attachedto = isset($ss['attachedto']) ? (string) $ss['attachedto'] : '';
                // A plain "x.css" file name only: "../../inc/config.php" would otherwise be
                // read and published as a public stylesheet
                if (!$this->isSafeFileName($name) || substr(strtolower($name), -4) !== '.css') {
                    $this->errors[] = 'Skipped stylesheet "' . htmlspecialchars_uni($name) . '": names must be plain .css file names inside css/.';
                    continue;
                }
                $cssPath    = $cssDir . '/' . $name;
                if (!is_file($cssPath)) continue;

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
        $adminPath = $this->getAdminPath();

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

        // import_theme_xml() prefers $mybb->input['name'] over the XML's name — hide any
        // request "name" param so it can't rename or merge the theme.
        global $mybb;
        $inputName = isset($mybb->input['name']) ? $mybb->input['name'] : null;
        unset($mybb->input['name']);
        $tid = import_theme_xml($xml, $options);
        if ($inputName !== null) {
            $mybb->input['name'] = $inputName;
        }

        // Failure codes are negative (truthy) ints, not false
        if ((int) $tid < 1) {
            $reasons = array(
                -1 => 'The generated theme XML is malformed.',
                -2 => 'The theme version is incompatible.',
                -3 => 'A theme with this name already exists.',
                -4 => 'A template failed the MyBB template security check.',
            );
            $this->errors[] = 'MyBB could not import the theme: ' . (isset($reasons[(int) $tid]) ? $reasons[(int) $tid] : 'no theme ID returned.');
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
            // ACP-created child themes inherit the parent's template set; only drop it if unshared
            $shared = (int) $db->fetch_field($db->simple_select('themes', 'COUNT(*) AS c',
                "tid != '{$tid}' AND properties LIKE '%\"templateset\";i:{$sid};%'"), 'c');
            if ($sid > 0 && !$shared) {
                $db->delete_query('templates', "sid='{$sid}'");
                $db->delete_query('templatesets', "sid='{$sid}'");
            }
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

    private function deployJs($themeDir)
    {
        $jsDir = $themeDir . '/js';
        if (!is_dir($jsDir)) return;

        $destDir = MYBB_ROOT . 'jscripts';
        foreach (scandir($jsDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $src = $jsDir . '/' . $entry;
            if (is_file($src) && pathinfo($entry, PATHINFO_EXTENSION) === 'js') {
                // Theme JS shares jscripts/ with MyBB — never overwrite a core script
                if (in_array(strtolower($entry), self::CORE_JSCRIPTS, true)) {
                    $this->errors[] = 'Skipped js/' . htmlspecialchars_uni($entry) . ': it would overwrite a MyBB core script. Rename the file.';
                    continue;
                }
                copy($src, $destDir . '/' . $entry);
            }
        }
    }

    /** MyBB 1.8 core files in jscripts/ that theme JS must never replace. */
    const CORE_JSCRIPTS = array(
        'bbcodes_sceditor.js', 'captcha.js', 'general.js', 'inline_edit.js', 'inline_moderation.js',
        'inline_reports.js', 'jquery.js', 'jquery.plugins.js', 'jquery.plugins.min.js', 'post.js',
        'question.js', 'rating.js', 'regvalidator.js', 'report.js', 'thread.js', 'usercp.js',
    );

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
            if (is_link($s)) continue; // don't follow links out of the source tree
            if (is_dir($s)) {
                if (!$this->rcopy($s, $d)) return false;
            } else {
                if (!copy($s, $d)) return false;
            }
        }
        return true;
    }

    /** @param ZipArchive|MsZipArchive $zip */
    private function addDirToZip($zip, $dir, $prefix)
    {
        $zip->addEmptyDir($prefix);
        foreach (scandir($dir) as $entry) {
            // Skip dotfiles (.git, .env, .ms_last_sync …) and links — they don't belong in a package
            if ($entry[0] === '.' || is_link($dir . '/' . $entry)) continue;
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
        if (is_link($dir)) {
            // Remove the link itself — never recurse into its target
            @unlink($dir) || @rmdir($dir);
            return;
        }
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . '/' . $entry;
            if (is_link($path)) {
                @unlink($path) || @rmdir($path);
            } elseif (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
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

        if ($row && !$this->isUnsafeTemplate($content)) {
            global $mybb;
            $db->update_query('templates', array(
                'template' => $db->escape_string($content),
                'dateline' => TIME_NOW,
                'version'  => (int) $mybb->version_code
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
            $funcsFile = $this->getAdminPath() . 'inc/functions_themes.php';
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
        $themeDir = $this->themeBaseDir($slug);
        if (!$themeDir) return false;
        return $this->buildFileTreeNode($themeDir, $slug);
    }

    /**
     * Resolve a theme folder for editor file ops. The slug must be a plain folder name
     * holding a theme.json — an empty slug would otherwise resolve to themes/ itself.
     *
     * @return string|false realpath of themes/{slug}
     */
    private function themeBaseDir($slug)
    {
        if (!is_string($slug) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-]*$/', $slug)) return false;
        $dir = MYBB_ROOT . self::THEMES_DIR . '/' . $slug;
        if (!is_file($dir . '/theme.json')) return false;
        return realpath($dir);
    }

    /**
     * Lexically validate a theme-relative path before it touches the filesystem
     * (so mkdir never runs on an escaping path). Rejects absolute paths, drive
     * letters/ADS (":"), "." / ".." segments and hidden (dot) segments.
     *
     * @return string|false Normalised path with forward slashes
     */
    private function cleanRelPath($rel)
    {
        $rel = trim(str_replace('\\', '/', (string) $rel), '/');
        if ($rel === '' || strpos($rel, ':') !== false || strpos($rel, "\0") !== false) return false;
        foreach (explode('/', $rel) as $seg) {
            if ($seg === '' || $seg[0] === '.' || rtrim($seg, '. ') !== $seg) return false;
        }
        return $rel;
    }

    /** A single file name with no path parts: letters, digits, "_", "-", "." and not a dotfile. */
    private function isSafeFileName($name)
    {
        return is_string($name) && $name !== '' && $name[0] !== '.' && strlen($name) <= 120
            && preg_match('/^[A-Za-z0-9_\-.]+$/', $name) && rtrim($name, '.') === $name;
    }

    /** True if $path is $base or inside it (separator-aware, so themes/a never matches themes/ab). */
    private function isInside($path, $base)
    {
        return $path === $base || strpos($path, rtrim($base, '\\/') . DIRECTORY_SEPARATOR) === 0;
    }

    /**
     * Read a file from a theme directory (path-traversal safe).
     *
     * @return array|false  {content, size, mtime} or false
     */
    public function readThemeFile($slug, $relativePath)
    {
        $themeDir = $this->themeBaseDir($slug);
        $rel = $this->cleanRelPath($relativePath);
        if (!$themeDir || $rel === false) return false;

        $filePath = realpath($themeDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
        if (!$filePath || !$this->isInside($filePath, $themeDir)) return false;
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
        if (!$this->isSafeExtension($relativePath)) {
            $this->errors[] = 'This file type cannot be edited.';
            return false;
        }

        $realBase = $this->themeBaseDir($slug);
        $relNorm  = $this->cleanRelPath($relativePath);
        if (!$realBase || $relNorm === false) {
            $this->errors[] = 'Invalid theme or path.';
            return false;
        }

        // Templates are eval()'d by MyBB — refuse PHP-capable syntax before it reaches disk
        if (strpos($relNorm, 'templates/') === 0 && $this->isUnsafeTemplate($content)) {
            $this->errors[] = 'This template failed the MyBB template security check (it contains PHP-capable syntax such as {${…}}).';
            return false;
        }

        $fullPath = $realBase . DIRECTORY_SEPARATOR
                  . str_replace('/', DIRECTORY_SEPARATOR, $relNorm);

        // Ensure parent directory exists (path was checked lexically above)
        $dir = dirname($fullPath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        // Verify target is inside the theme directory
        $realDir = realpath($dir);
        if (!$realDir || !$this->isInside($realDir, $realBase)) return false;

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

        $realBase = $this->themeBaseDir($slug);
        if (!$realBase) {
            $this->errors[] = 'Theme directory not found.';
            return false;
        }

        $relNorm = $this->cleanRelPath($relativePath);
        if ($relNorm === false) {
            $this->errors[] = 'Invalid path (hidden files, ".." and absolute paths are not allowed).';
            return false;
        }
        if (strpos($relNorm, 'templates/') === 0 && $this->isUnsafeTemplate($content)) {
            $this->errors[] = 'This template failed the MyBB template security check.';
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
        if (!$realDir || !$this->isInside($realDir, $realBase)) {
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
        $realBase = $this->themeBaseDir($slug);
        if (!$realBase) {
            $this->errors[] = 'Theme directory not found.';
            return false;
        }

        $relNorm = $this->cleanRelPath($relativePath);
        if ($relNorm === false) {
            $this->errors[] = 'Invalid path (hidden folders, ".." and absolute paths are not allowed).';
            return false;
        }
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
        if (!$realParent || !$this->isInside($realParent, $realBase)) {
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
        $realBase = $this->themeBaseDir($slug);
        $relNorm  = $this->cleanRelPath($relativePath);
        if (!$realBase || $relNorm === false) return false;

        $fullPath = realpath($realBase . DIRECTORY_SEPARATOR
                  . str_replace('/', DIRECTORY_SEPARATOR, $relNorm));

        if (!$fullPath || !$this->isInside($fullPath, $realBase)) return false;
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
        $realBase = $this->themeBaseDir($slug);
        $relNorm  = $this->cleanRelPath($relativePath);
        if (!$realBase || $relNorm === false) return false;

        $fullPath = realpath($realBase . DIRECTORY_SEPARATOR
                  . str_replace('/', DIRECTORY_SEPARATOR, $relNorm));

        if (!$fullPath || !$this->isInside($fullPath, $realBase)) return false;
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
        $realBase = $this->themeBaseDir($slug);
        $oldRel   = $this->cleanRelPath($oldPath);
        $newRel   = $this->cleanRelPath($newPath);
        if (!$realBase || $oldRel === false || $newRel === false) {
            $this->errors[] = 'Invalid path (hidden files, ".." and absolute paths are not allowed).';
            return false;
        }

        $oldFull = realpath($realBase . DIRECTORY_SEPARATOR
                 . str_replace('/', DIRECTORY_SEPARATOR, $oldRel));
        if (!$oldFull || !$this->isInside($oldFull, $realBase) || $oldFull === $realBase) return false;

        // Files must keep a safe extension (an extensionless or "x.php." target is refused)
        if (is_file($oldFull) && !$this->isSafeExtension($newRel)) {
            $this->errors[] = 'This file type is not allowed.';
            return false;
        }

        $newFull = $realBase . DIRECTORY_SEPARATOR
                 . str_replace('/', DIRECTORY_SEPARATOR, $newRel);
        // rename() silently replaces an existing file on Windows
        if (file_exists($newFull)) {
            $this->errors[] = 'A file or folder with that name already exists.';
            return false;
        }

        // Ensure new parent exists
        $newDir = dirname($newFull);
        if (!is_dir($newDir)) @mkdir($newDir, 0755, true);
        $realNewDir = realpath($newDir);
        if (!$realNewDir || !$this->isInside($realNewDir, $realBase)) return false;

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
        $themeDir = $this->themeBaseDir($slug);
        if (!$themeDir) return false;

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
     * Read the theme options definition from {admin}/modules/mystudio/themes/{slug}/options.php.
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
        $file = $this->getThemeAdminDir($slug) . '/options.php';
        if (!file_exists($file)) return false;

        $options = @include $file;
        if (!is_array($options) || empty($options)) return false;
        return $options;
    }

    /**
     * Check if a theme has a PHP code directory (inc/plugins/mystudio/themes/{slug}) with any PHP files.
     *
     * @param  string $slug
     * @return bool
     */
    public function themeHasFunctions($slug)
    {
        $dir = $this->getThemeCodeDir($slug);
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
     * Expected structure: inc/plugins/mystudio/themes/{slug}/lang/{language}/*.lang.php
     * Each file sets variables on the $l array (same convention as MyBB lang files):
     *   $l['my_theme_welcome'] = 'Welcome!';
     *
     * @param  string $slug     Theme slug
     * @param  string $langName MyBB language name (e.g. 'english')
     * @return array  Loaded language strings (merged into $lang)
     */
    public function loadThemeLang($slug, $langName)
    {
        $langDir = $this->getThemeCodeDir($slug) . '/lang/' . $langName;
        $strings = array();

        // Fallback to english if the requested language folder doesn't exist
        if (!is_dir($langDir)) {
            $langDir = $this->getThemeCodeDir($slug) . '/lang/english';
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
     * Discover mini plugins (alias of listModules).
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
     * Scans inc/plugins/mystudio/modules/ for subdirectories with a plugin.json.
     *
     * @param  string $slug  Theme slug
     * @return array         Array of module info arrays
     */
    public function listModules($slug)
    {
        // Scanned once per request (loadModules + getModuleAssets both need it)
        if (self::$moduleListCache !== null) {
            return self::$moduleListCache;
        }

        $modulesDir = $this->getModulesDir();
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
                'has_options' => file_exists($this->getModuleAdminDir($entry) . '/options.php')
                               || file_exists($dir . '/options.php'),
                'has_admin'   => file_exists($dir . '/admin.php'),
                'has_js'      => is_dir($dir . '/js'),
                'has_css'     => is_dir($dir . '/css'),
                'dir'         => $dir,
                'core'        => $this->isCoreModule($entry),
                'enabled'     => $this->isModuleEnabled($entry),
            );
        }

        self::$moduleListCache = $result;
        return $result;
    }

    /** @var array|null Per-request cache for listModules(); reset when module state changes */
    private static $moduleListCache = null;

    /**
     * Modules that ship with MyStudio. They are always enabled and cannot be
     * switched off. Any other module is off until an admin activates it.
     */
    const CORE_MODULES = array(
        'ms-editor-extras',
        'ms-forum-display-extras',
        'ms-forum-icons',
        'ms-pagebuilder',
        'ms-profile-extras',
    );

    /** Datacache key holding the list of activated (non-core) module ids. */
    const MODULE_STATE_CACHE = 'ms_modules';

    /**
     * @param  string $moduleId
     * @return bool
     */
    public function isCoreModule($moduleId)
    {
        return in_array($moduleId, self::CORE_MODULES, true);
    }

    /**
     * Ids of non-core modules an admin has activated.
     *
     * @return array
     */
    public function getActivatedModuleIds()
    {
        global $cache;

        $data = $cache->read(self::MODULE_STATE_CACHE);
        return (is_array($data) && isset($data['enabled']) && is_array($data['enabled']))
            ? $data['enabled'] : array();
    }

    /**
     * Core modules are always enabled; others only once activated.
     *
     * @param  string $moduleId
     * @return bool
     */
    public function isModuleEnabled($moduleId)
    {
        return $this->isCoreModule($moduleId)
            || in_array($moduleId, $this->getActivatedModuleIds(), true);
    }

    /**
     * Activate or deactivate a non-core module.
     *
     * @param  string $moduleId
     * @param  bool   $enable
     * @return bool   false for core modules (they cannot be toggled)
     */
    public function setModuleEnabled($moduleId, $enable)
    {
        global $cache;

        if ($this->isCoreModule($moduleId)) return false;

        $ids = array_diff($this->getActivatedModuleIds(), array($moduleId));
        if ($enable) {
            $ids[] = $moduleId;
        }
        $cache->update(self::MODULE_STATE_CACHE, array('enabled' => array_values($ids)));
        self::$moduleListCache = null;
        return true;
    }

    /**
     * Enabled modules only (what actually loads at runtime).
     *
     * @param  string $slug
     * @return array
     */
    public function listActiveModules($slug)
    {
        return array_values(array_filter($this->listModules($slug), function ($m) {
            return $m['enabled'];
        }));
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
        $file = $this->getModulesDir() . '/' . $pluginId . '/default.json';
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
        $dir = $this->getModulesDir() . '/' . $pluginId;
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
        // Schema lives in the admin dir; fall back to the module dir for
        // modules that have not been relocated yet.
        $file = $this->getModuleAdminDir($pluginId) . '/options.php';
        if (!file_exists($file)) {
            $file = $this->getModulesDir() . '/' . $pluginId . '/options.php';
        }
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
        $modules = $this->listActiveModules($slug);

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
        $modules = $this->listActiveModules($slug);
        $assets  = array('js' => array(), 'css' => array());

        // Absolute URLs so assets also load on rewritten/sub-path pages (e.g. Page Builder URLs)
        global $mybb;
        $root = rtrim($mybb->settings['bburl'], '/') . '/';
        foreach ($modules as $p) {
            $webBase = $root . self::PLUGIN_DIR . '/modules/' . $p['id'];

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
