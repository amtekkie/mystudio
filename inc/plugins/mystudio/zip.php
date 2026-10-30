<?php
/**
 * MyStudio — pure-PHP ZIP fallback.
 *
 * Used for theme import/export when the PHP "zip" extension (ZipArchive) is not
 * installed, which is common on shared hosting. Implements only the subset of the
 * ZipArchive API that MyStudio uses:
 *
 *   read:  open($path), $numFiles, getNameIndex($i), statIndex($i), extractTo($dir), close()
 *   write: open($path, MsZipArchive::CREATE), addEmptyDir($name), addFile($src, $name),
 *          addFromString($name, $data), close()
 *
 * Supports stored (0) and deflated (8) entries. Deflate needs zlib (gzdeflate /
 * gzinflate), which nearly every PHP build has; without it, archives are written
 * uncompressed and deflated archives can't be read. ZIP64, encryption and
 * multi-disk archives are not supported (themes never need them).
 *
 * @version 2.1.0
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

class MsZipArchive
{
    const CREATE = 1;

    /** @var int Number of entries (read mode), mirrors ZipArchive::$numFiles */
    public $numFiles = 0;

    /** @var string Last error, for callers that want a reason */
    public $error = '';

    private $path = '';
    private $writing = false;

    // Read mode
    private $data = '';
    private $entries = array(); // [name, method, crc, csize, size, offset, flags]

    // Write mode
    private $fh = null;
    private $central = '';
    private $count = 0;
    private $offset = 0;

    /**
     * @param  string $path
     * @param  int    $flags  MsZipArchive::CREATE to write a new archive
     * @return true|int       true on success, non-true on failure (like ZipArchive)
     */
    public function open($path, $flags = 0)
    {
        $this->path = $path;

        if ($flags & self::CREATE) {
            $this->fh = @fopen($path, 'wb');
            if (!$this->fh) {
                $this->error = 'Cannot create the ZIP file.';
                return 1;
            }
            $this->writing = true;
            return true;
        }

        $this->data = @file_get_contents($path);
        if ($this->data === false || !$this->readCentralDirectory()) {
            if ($this->error === '') $this->error = 'Not a valid ZIP archive.';
            return 1;
        }
        return true;
    }

    /* ── Read ─────────────────────────────────────────────────── */

    private function readCentralDirectory()
    {
        $len = strlen($this->data);
        // EOCD record is 22 bytes plus an optional comment of up to 65535 bytes
        $eocd = strrpos(substr($this->data, max(0, $len - 65557)), "PK\x05\x06");
        if ($eocd === false) return false;
        $eocd += max(0, $len - 65557);

        $e = unpack('vdisk/vcddisk/vdiskentries/ventries/Vcdsize/Vcdoffset', substr($this->data, $eocd + 4, 16));
        if ($e['entries'] === 0xFFFF || $e['cdoffset'] === 0xFFFFFFFF) {
            $this->error = 'ZIP64 archives are not supported. Re-save the archive as a standard ZIP.';
            return false;
        }
        if ($e['disk'] !== 0 || $e['entries'] !== $e['diskentries']) {
            $this->error = 'Multi-part ZIP archives are not supported.';
            return false;
        }

        $pos = $e['cdoffset'];
        for ($i = 0; $i < $e['entries']; $i++) {
            if (substr($this->data, $pos, 4) !== "PK\x01\x02") return false;
            $h = unpack('vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnamelen/vextralen/vcommentlen/vdisk/vintattr/Vextattr/Voffset',
                substr($this->data, $pos + 8, 38));
            $this->entries[] = array(
                'name'   => substr($this->data, $pos + 46, $h['namelen']),
                'method' => $h['method'],
                'crc'    => $h['crc'],
                'csize'  => $h['csize'],
                'size'   => $h['size'],
                'offset' => $h['offset'],
                'flags'  => $h['flags'],
            );
            $pos += 46 + $h['namelen'] + $h['extralen'] + $h['commentlen'];
        }
        $this->numFiles = count($this->entries);
        return true;
    }

    public function getNameIndex($i)
    {
        return isset($this->entries[$i]) ? $this->entries[$i]['name'] : false;
    }

    public function statIndex($i)
    {
        if (!isset($this->entries[$i])) return false;
        $e = $this->entries[$i];
        return array('name' => $e['name'], 'index' => $i, 'size' => $e['size'], 'comp_size' => $e['csize'], 'crc' => $e['crc']);
    }

    /**
     * Extract every entry under $dir. Callers validate names first (MyStudio's
     * import rejects "..", absolute paths and drive letters); this re-checks anyway.
     *
     * @return bool
     */
    public function extractTo($dir)
    {
        $dir = rtrim($dir, '/\\');
        foreach ($this->entries as $e) {
            $name = str_replace('\\', '/', $e['name']);
            if ($name === '' || $name[0] === '/' || strpos($name, ':') !== false
                || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                $this->error = 'Unsafe path in archive.';
                return false;
            }

            $target = $dir . '/' . $name;
            if (substr($name, -1) === '/') {
                @mkdir($target, 0755, true);
                continue;
            }

            $content = $this->readEntry($e);
            if ($content === false) return false;

            @mkdir(dirname($target), 0755, true);
            if (file_put_contents($target, $content) === false) {
                $this->error = 'Could not write ' . $name . '.';
                return false;
            }
        }
        return true;
    }

    private function readEntry($e)
    {
        if ($e['flags'] & 1) {
            $this->error = 'Encrypted ZIP entries are not supported.';
            return false;
        }
        $p = $e['offset'];
        if (substr($this->data, $p, 4) !== "PK\x03\x04") {
            $this->error = 'Corrupt ZIP archive.';
            return false;
        }
        $l = unpack('vnamelen/vextralen', substr($this->data, $p + 26, 4));
        $raw = substr($this->data, $p + 30 + $l['namelen'] + $l['extralen'], $e['csize']);

        if ($e['method'] === 0) {
            $content = $raw;
        } elseif ($e['method'] === 8) {
            if (!function_exists('gzinflate')) {
                $this->error = 'This archive is compressed and PHP\'s zlib extension is not available.';
                return false;
            }
            $content = @gzinflate($raw);
        } else {
            $this->error = 'Unsupported ZIP compression method (' . (int) $e['method'] . ').';
            return false;
        }

        if ($content === false || (crc32($content) & 0xFFFFFFFF) !== $e['crc']) {
            $this->error = 'Corrupt ZIP entry: ' . $e['name'];
            return false;
        }
        return $content;
    }

    /* ── Write ────────────────────────────────────────────────── */

    public function addEmptyDir($name)
    {
        return $this->writeEntry(rtrim($name, '/') . '/', '', true);
    }

    public function addFile($src, $name)
    {
        $data = @file_get_contents($src);
        if ($data === false) return false;
        return $this->writeEntry($name, $data, false, @filemtime($src));
    }

    public function addFromString($name, $data)
    {
        return $this->writeEntry($name, (string) $data, false);
    }

    private function writeEntry($name, $data, $isDir, $mtime = null)
    {
        if (!$this->writing) return false;

        $name = str_replace('\\', '/', $name);
        $crc  = crc32($data) & 0xFFFFFFFF;
        $size = strlen($data);

        $method = 0;
        $body   = $data;
        if (!$isDir && $size > 0 && function_exists('gzdeflate')) {
            $deflated = gzdeflate($data, 6);
            if ($deflated !== false && strlen($deflated) < $size) {
                $method = 8;
                $body   = $deflated;
            }
        }

        list($dosTime, $dosDate) = $this->dosTime($mtime ?: TIME_NOW);
        $flags = 0x0800; // names are UTF-8

        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, $flags, $method, $dosTime, $dosDate,
            $crc, strlen($body), $size, strlen($name), 0) . $name;
        if (fwrite($this->fh, $local . $body) === false) return false;

        $this->central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, $flags, $method, $dosTime, $dosDate,
            $crc, strlen($body), $size, strlen($name), 0, 0, 0, 0, $isDir ? 0x10 : 0, $this->offset) . $name;

        $this->offset += strlen($local) + strlen($body);
        $this->count++;
        return true;
    }

    private function dosTime($ts)
    {
        $d = getdate($ts);
        if ($d['year'] < 1980) {
            $d = array('year' => 1980, 'mon' => 1, 'mday' => 1, 'hours' => 0, 'minutes' => 0, 'seconds' => 0);
        }
        return array(
            ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2),
            (($d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'],
        );
    }

    /** @return bool */
    public function close()
    {
        if ($this->writing && $this->fh) {
            $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, $this->count, $this->count,
                strlen($this->central), $this->offset, 0);
            $ok = fwrite($this->fh, $this->central . $eocd) !== false;
            fclose($this->fh);
            $this->fh = null;
            $this->writing = false;
            return $ok;
        }
        $this->data = '';
        return true;
    }
}
