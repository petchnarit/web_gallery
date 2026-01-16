<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * ตัวอย่างใช้งาน
 * php -d memory_limit=2048M /volume2/web_gallery/spark gallery:process warm --root "/volume2/gallery" --cache "/volume2/web_gallery/public/gallery-cache" --subdir "" --force 0 --json 1 --verbose 1

 * php spark gallery:process warm --root=/volume2/gallery --cache=/volume2/web_gallery/public/gallery-cache --subdir="" --force=0 --json=1 --verbose=1 --memory=1G
 * php -d memory_limit=2048M /volume2/web_gallery/spark gallery:process warm --root=/volume2/gallery --cache=/volume2/web_gallery/public/gallery-cache --verbose=1
 * php spark gallery:process warm   --root=/volume2/gallery --cache=/volume2/web_gallery/public/gallery-cache --subdir="" --force=0 --verbose=1 --json=0 --memory=512M
 * php spark gallery:process check  --root=/volume2/gallery --cache=/volume2/web_gallery/public/gallery-cache --subdir="" --verbose=1
 * php spark gallery:process clear  --root=/volume2/gallery --cache=/volume2/web_gallery/public/gallery-cache --subdir="" --album="" --verbose=1
 */
class Processor extends BaseCommand
{
    protected $group       = 'Gallery';
    protected $name        = 'gallery:process';
    protected $description = 'Process VRU Gallery caches (warm/check/clear) with debug logs';

    protected $usage     = 'gallery:process <warm|check|clear> [options]';
    protected $arguments = [
        'action' => 'warm | check | clear',
    ];
    protected $options = [
        '--root'    => 'รากรูปภาพจริง (เช่น /volume2/gallery)',
        '--cache'   => 'รากโฟลเดอร์แคช WebP (เช่น /volume2/web_gallery/public/gallery-cache)',
        '--subdir'  => 'สแกนเฉพาะโฟลเดอร์ย่อยภายใต้ root (เช่น 2025/OpenHouse)',
        '--force'   => 'บังคับ regenerate (เฉพาะ warm) 0|1 (default 0)',
        '--json'    => 'เขียนไฟล์ JSON รายอัลบัม (เฉพาะ warm) 0|1 (default 0)',
        '--album'   => 'ใช้กับ clear: เลือกเคลียร์แคชเฉพาะอัลบัม (id 8 ตัว หรือ slug)',
        '--verbose' => 'แสดง log เพิ่มเติม 0|1 (default 0)',
        '--memory'  => 'เพิ่ม memory_limit ระหว่างรัน (เช่น 512M, 1G)',
    ];

    /* ===== Config คงที่ให้สอดคล้อง Controller ===== */
    private const ALLOWED_WIDTHS = [320, 2048];
    private const DEFAULT_Q      = 80;
    private const ALLOWED_EXT    = ['jpg', 'jpeg', 'png', 'webp'];

    /* ===== entry point ===== */
    public function run(array $params)
    {
        $action = $params[0] ?? null;
        if (!$action) {
            CLI::error('Missing action. Use: warm | check | clear');
            CLI::write($this->getHelpText());
            return;
        }

        $root   = rtrim((string) CLI::getOption('root'), '/');
        $cache  = rtrim((string) CLI::getOption('cache'), '/');
        $subdir = trim((string) (CLI::getOption('subdir') ?? ''), '/');
        $force  = (int) (CLI::getOption('force') ?? 0) === 1;
        $json   = (int) (CLI::getOption('json') ?? 0) === 1;
        $album  = trim((string) (CLI::getOption('album') ?? ''));
        $verbose = (int) (CLI::getOption('verbose') ?? 0) === 1;

        $memory = CLI::getOption('memory');
        if ($memory) {
            @ini_set('memory_limit', $memory);
            if ($verbose) {
                CLI::write('Set memory_limit to ' . ini_get('memory_limit'));
            }
        }

        if ($root === '' || $cache === '') {
            CLI::error('--root และ --cache จำเป็นต้องระบุ');
            return;
        }

        $scanRoot = $root . ($subdir ? DIRECTORY_SEPARATOR . $subdir : '');
        if (!is_dir($scanRoot)) {
            CLI::error("Root folder not found: {$scanRoot}");
            return;
        }

        switch (strtolower($action)) {
            case 'warm':
                $this->doWarm($scanRoot, $cache, $subdir, $force, $json, $verbose);
                break;
            case 'check':
                $this->doCheck($scanRoot, $cache, $subdir, $verbose);
                break;
            case 'clear':
                $this->doClear($scanRoot, $cache, $subdir, $album, $verbose);
                break;
            default:
                CLI::error("Unknown action: {$action}");
                CLI::write($this->getHelpText());
        }
    }

    /* ===== WARM: generate webp cache (+ optional JSON) ===== */
    private function doWarm(string $scanRoot, string $cacheRoot, string $subdir, bool $force, bool $jsonAll, bool $verbose): void
    {
        $widths = self::ALLOWED_WIDTHS;
        $q      = self::DEFAULT_Q;

        $this->ensureDir($cacheRoot, $verbose, true);

        $stats = [
            'folders'     => 0,
            'files'       => 0,
            'generated'   => 0,
            'errors'      => 0,
            'jsonWritten' => 0,
            'jsonSkipped' => 0,
        ];

        if ($jsonAll) {
            $jsonDir = $this->jsonDirFromCacheRoot($cacheRoot);
            $this->ensureDir($jsonDir, $verbose, true);
        }

        foreach ($this->listSubfolders($scanRoot) as $folder) {
            $folderAbs = $scanRoot . DIRECTORY_SEPARATOR . $folder;
            $files     = $this->listImagesFlatOneLevel($folderAbs);
            if (empty($files)) {
                if ($verbose) CLI::write("Skip empty: {$folder}");
                continue;
            }

            $stats['folders']++;
            $albumIdShort = substr(md5(($subdir ? $subdir . '/' : '') . $folder), 0, 8);

            foreach ($files as $rel) {
                $stats['files']++;
                $abs = $folderAbs . DIRECTORY_SEPARATOR . $rel;

                foreach ($widths as $w) {
                    try {
                        $cachePath = $this->getCachePathForByAlbum($cacheRoot, $albumIdShort, $rel, $w);
                        if (!$force && is_file($cachePath)) {
                            if ($verbose) CLI::write("  ✓ exists {$cachePath}");
                            continue;
                        }
                        $this->generateWebp($abs, $cachePath, $w, $q);
                        $stats['generated']++;
                        if ($verbose) CLI::write("  + gen   {$cachePath}");
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        CLI::error("  ! error {$abs} [w={$w}] :: " . $e->getMessage());
                    }
                }
            }

            if ($jsonAll) {
                try {
                    $images = [];
                    foreach ($files as $rel) {
                        $abs = $folderAbs . DIRECTORY_SEPARATOR . $rel;
                        [$iw, $ih] = @getimagesize($abs) ?: [0, 0];
                        $ratio  = $this->aspectRatioFromWH($iw, $ih);
                        $ts       = $this->imageTimestamp($abs);
                        $modified = @filemtime($abs) ?: $ts;

                        $images[] = [
                            'filename'    => $rel,
                            'width'       => $iw,
                            'height'      => $ih,
                            'aspectRatio' => $ratio,
                            'captured'    => gmdate('c', $ts),
                            'modified'    => gmdate('c', $modified),
                        ];
                    }

                    $jsonPath = $this->albumJsonPath($cacheRoot, $albumIdShort);
                    $existing = $this->tryReadJson($jsonPath);
                    if ($this->shouldWriteAlbumJson($existing, count($images), $force)) {
                        $payload = [
                            'id'     => $albumIdShort,
                            'store'  => 'public',           // สำหรับ UI; หากมี multi-store ค่อยปรับเพิ่มภายหลัง
                            'subdir' => $subdir,
                            'title'  => $this->prettifyName($folder),
                            'images' => $images,
                        ];
                        $this->writeJsonAtomic($jsonPath, $payload);
                        $stats['jsonWritten']++;
                        if ($verbose) CLI::write("  + json  {$jsonPath}");
                    } else {
                        $stats['jsonSkipped']++;
                        if ($verbose) CLI::write("  ✓ json  {$jsonPath} (skip)");
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    CLI::error("  ! json  {$folder} :: " . $e->getMessage());
                }
            }
        }

        CLI::write('');
        CLI::write('== WARM DONE ==');
        CLI::write("folders   : {$stats['folders']}");
        CLI::write("files     : {$stats['files']}");
        CLI::write("generated : {$stats['generated']}");
        if ($jsonAll) {
            CLI::write("jsonWritten: {$stats['jsonWritten']}");
            CLI::write("jsonSkipped: {$stats['jsonSkipped']}");
        }
        if ($stats['errors'] > 0) {
            CLI::write(CLI::color("errors    : {$stats['errors']}", 'red'));
        } else {
            CLI::write(CLI::color("errors    : 0", 'green'));
        }
    }

    /* ===== CHECK: ตรวจ cache ครบไหม / รายงานที่หายไป ===== */
    private function doCheck(string $scanRoot, string $cacheRoot, string $subdir, bool $verbose): void
    {
        $missing = 0;
        $totalExpected = 0;

        foreach ($this->listSubfolders($scanRoot) as $folder) {
            $folderAbs = $scanRoot . DIRECTORY_SEPARATOR . $folder;
            $files     = $this->listImagesFlatOneLevel($folderAbs);
            if (empty($files)) continue;

            $albumIdShort = substr(md5(($subdir ? $subdir . '/' : '') . $folder), 0, 8);

            foreach ($files as $rel) {
                foreach (self::ALLOWED_WIDTHS as $w) {
                    $totalExpected++;
                    $cachePath = $this->getCachePathForByAlbum($cacheRoot, $albumIdShort, $rel, $w);
                    if (!is_file($cachePath)) {
                        $missing++;
                        CLI::write(CLI::color("[MISS] {$cachePath}", 'yellow'));
                    } elseif ($verbose) {
                        CLI::write("  ✓ {$cachePath}");
                    }
                }
            }

            // รายงาน JSON ถ้ามี
            $jsonPath = $this->albumJsonPath($cacheRoot, $albumIdShort);
            if (!is_file($jsonPath)) {
                CLI::write(CLI::color("[JSON MISS] {$jsonPath}", 'yellow'));
            } elseif ($verbose) {
                CLI::write("  ✓ json {$jsonPath}");
            }
        }

        CLI::write('');
        CLI::write('== CHECK DONE ==');
        CLI::write("expected files : {$totalExpected}");
        if ($missing > 0) {
            CLI::write(CLI::color("missing       : {$missing}", 'red'));
        } else {
            CLI::write(CLI::color("missing       : 0", 'green'));
        }
    }

    /* ===== CLEAR: ลบ cache ตามอัลบัมหรือทั้ง subdir ===== */
    private function doClear(string $scanRoot, string $cacheRoot, string $subdir, string $album, bool $verbose): void
    {
        $targets = [];

        if ($album !== '') {
            // หาโฟลเดอร์จริงที่แมตช์ id/slug เพื่อนำไปคำนวณ albumIdShort
            $matchFolder = null;
            foreach ($this->listSubfolders($scanRoot) as $folder) {
                $fullId  = md5(($subdir ? $subdir . '/' : '') . $folder);
                $shortId = substr($fullId, 0, 8);
                $slug    = $this->slugify($folder);
                if ($album === $shortId || $album === $fullId || $album === $slug) {
                    $matchFolder = $folder;
                    break;
                }
            }
            if (!$matchFolder) {
                CLI::error("Album not found in filesystem: {$album}");
                return;
            }
            $albumIdShort = substr(md5(($subdir ? $subdir . '/' : '') . $matchFolder), 0, 8);
            $targets[] = $cacheRoot . DIRECTORY_SEPARATOR . $albumIdShort;
        } else {
            // เคลียร์ทั้ง subdir: ลบเฉพาะโฟลเดอร์ที่เป็น albumIdShort ที่สัมพันธ์กับโฟลเดอร์จริง
            foreach ($this->listSubfolders($scanRoot) as $folder) {
                $albumIdShort = substr(md5(($subdir ? $subdir . '/' : '') . $folder), 0, 8);
                $targets[] = $cacheRoot . DIRECTORY_SEPARATOR . $albumIdShort;
            }
        }

        $deleted = 0;
        foreach ($targets as $dir) {
            if (is_dir($dir)) {
                $this->rrmdir($dir, $verbose);
                $deleted++;
                if ($verbose) CLI::write("x del {$dir}");
            } elseif ($verbose) {
                CLI::write("~ skip {$dir} (not found)");
            }

            // ลบ JSON ด้วย
            $albumId = basename($dir);
            $jsonPath = $this->albumJsonPath($cacheRoot, $albumId);
            if (is_file($jsonPath)) {
                @unlink($jsonPath);
                if ($verbose) CLI::write("x del {$jsonPath}");
            }
        }

        CLI::write('');
        CLI::write('== CLEAR DONE ==');
        CLI::write("removed album caches : {$deleted}");
    }

    /* ======= Helpers (filesystem & image) ======= */

    private function ensureDir(string $dir, bool $verbose, bool $createIndex = false): void
    {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true)) {
                throw new \RuntimeException("Cannot create directory: {$dir}");
            }
            if ($createIndex) {
                @file_put_contents($dir . DIRECTORY_SEPARATOR . 'index.html', '');
            }
            if ($verbose) CLI::write("mkdir {$dir}");
        }
    }

    private function listSubfolders(string $absRoot): array
    {
        $out = [];
        $items = @scandir($absRoot) ?: [];
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            if ($it[0] === '#' || $it[0] === '@' || strcasecmp($it, '#recycle') === 0) continue;
            $p = $absRoot . DIRECTORY_SEPARATOR . $it;
            if (is_dir($p)) $out[] = $it;
        }
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    private function listImagesFlatOneLevel(string $albumAbs): array
    {
        $relPaths = [];
        $items = @scandir($albumAbs) ?: [];

        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            if ($it[0] === '#' || $it[0] === '@' || strcasecmp($it, '#recycle') === 0) continue;
            $p = $albumAbs . DIRECTORY_SEPARATOR . $it;
            if (is_file($p)) {
                $ext = strtolower(pathinfo($it, PATHINFO_EXTENSION));
                if (in_array($ext, self::ALLOWED_EXT, true)) $relPaths[] = $it;
            }
        }

        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            if ($it[0] === '#' || $it[0] === '@' || strcasecmp($it, '#recycle') === 0) continue;
            $sub = $albumAbs . DIRECTORY_SEPARATOR . $it;
            if (!is_dir($sub)) continue;

            $subItems = @scandir($sub) ?: [];
            foreach ($subItems as $f) {
                if ($f === '.' || $f === '..') continue;
                $p = $sub . DIRECTORY_SEPARATOR . $f;
                if (!is_file($p)) continue;
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (in_array($ext, self::ALLOWED_EXT, true)) {
                    $relPaths[] = $it . '/' . $f;
                }
            }
        }

        sort($relPaths, SORT_NATURAL | SORT_FLAG_CASE);
        return $relPaths;
    }

    private function slugify(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('~[^\pL\d]+~u', '-', $s);
        $s = trim($s, '-');
        $s = mb_strtolower($s);
        $s = preg_replace('~[^-a-z0-9]+~', '', $s);
        return $s ?: 'n-a';
    }

    private function aspectRatioFromWH(int $w, int $h): string
    {
        if ($w <= 0 || $h <= 0) return '1/1';
        $ratio = $w / $h;
        $pairs = ['16/9' => 16 / 9, '3/2' => 3 / 2, '4/3' => 4 / 3, '1/1' => 1, '4/5' => 0.8, '2/3' => 2 / 3];
        $best = '1/1';
        $min = 10;
        foreach ($pairs as $k => $v) {
            $d = abs($ratio - $v);
            if ($d < $min) {
                $min = $d;
                $best = $k;
            }
        }
        return $best;
    }

    private function imageTimestamp(string $absFile): int
    {
        if (!function_exists('exif_read_data')) return @filemtime($absFile) ?: time();
        $ext = strtolower(pathinfo($absFile, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'tif', 'tiff'], true)) return @filemtime($absFile) ?: time();

        $exif = @exif_read_data($absFile, 'IFD0,EXIF', true, false);
        if (!$exif || !is_array($exif)) return @filemtime($absFile) ?: time();

        $cands = [$exif['EXIF']['DateTimeOriginal'] ?? null, $exif['EXIF']['CreateDate'] ?? null, $exif['IFD0']['DateTime'] ?? null];
        foreach ($cands as $raw) {
            if (is_string($raw) && ($raw = trim($raw)) !== '') {
                $dt = \DateTime::createFromFormat('Y:m:d H:i:s', $raw, new \DateTimeZone('UTC'));
                if ($dt !== false) return $dt->getTimestamp();
                try {
                    $dt = new \DateTime($raw, new \DateTimeZone('UTC'));
                    return $dt->getTimestamp();
                } catch (\Throwable $e) {
                }
            }
        }
        return @filemtime($absFile) ?: time();
    }

    private function sanitizeRelPath(string $rel): string
    {
        $rel = ltrim($rel, '/');
        $rel = str_replace(['..\\', '../'], '', $rel);
        return str_replace('\\', '/', $rel);
    }

    private function getCachePathForByAlbum(string $cacheRoot, string $albumIdShort, string $relPath, int $w): string
    {
        $relPath = $this->sanitizeRelPath($relPath);

        $relDir  = pathinfo($relPath, PATHINFO_DIRNAME);
        $relBase = pathinfo($relPath, PATHINFO_FILENAME);

        $base = rtrim($cacheRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $albumIdShort
            . DIRECTORY_SEPARATOR . $w;

        if ($relDir !== '.' && $relDir !== DIRECTORY_SEPARATOR) {
            $base .= DIRECTORY_SEPARATOR . $relDir;
        }

        if (!is_dir($base)) {
            if (!@mkdir($base, 0775, true)) {
                throw new \RuntimeException("Cannot create cache directory: {$base}");
            }
        }

        return $base . DIRECTORY_SEPARATOR . $relBase . '.webp';
    }

    private function pickAllowedWidth(int $w): int
    {
        if ($w <= 0) return 0;
        $best = self::ALLOWED_WIDTHS[0];
        $minDiff = PHP_INT_MAX;
        foreach (self::ALLOWED_WIDTHS as $cand) {
            $d = abs($cand - $w);
            if ($d < $minDiff) {
                $minDiff = $d;
                $best = $cand;
            }
        }
        return $best;
    }

    private function generateWebp(string $srcAbs, string $cachePath, int $w, int $q): void
    {
        $ext = strtolower(pathinfo($srcAbs, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            throw new \RuntimeException('Extension not allowed');
        }
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            throw new \RuntimeException('WebP not supported on server');
        }

        // อ่านภาพ
        $src = $this->openImageGD($srcAbs, $ext);
        if (!$src) throw new \RuntimeException('Cannot read image: ' . $srcAbs);

        // EXIF orientation (เฉพาะ JPEG)
        if ($ext === 'jpg' || $ext === 'jpeg') {
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($srcAbs);
                $orientation = (int)($exif['Orientation'] ?? 0);
                if ($orientation > 1) {
                    switch ($orientation) {
                        case 2:
                            imageflip($src, IMG_FLIP_HORIZONTAL);
                            break;
                        case 3:
                            $src = imagerotate($src, 180, 0);
                            break;
                        case 4:
                            imageflip($src, IMG_FLIP_VERTICAL);
                            break;
                        case 5:
                            imageflip($src, IMG_FLIP_VERTICAL);
                            $src = imagerotate($src, -90, 0);
                            break;
                        case 6:
                            $src = imagerotate($src, -90, 0);
                            break;
                        case 7:
                            imageflip($src, IMG_FLIP_HORIZONTAL);
                            $src = imagerotate($src, -90, 0);
                            break;
                        case 8:
                            $src = imagerotate($src, 90, 0);
                            break;
                    }
                }
            }
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $dst  = $src;

        $w = $this->pickAllowedWidth($w);
        if ($w > 0 && $w < $srcW) {
            $ratio = $srcH / $srcW;
            $dstW  = $w;
            $dstH  = (int) round($dstW * $ratio);
            $tmp = imagecreatetruecolor($dstW, $dstH);
            imagealphablending($tmp, true);
            imagesavealpha($tmp, true);
            imagecopyresampled($tmp, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
            imagedestroy($src);
            $dst = $tmp;
        }

        $dir = dirname($cachePath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            imagedestroy($dst);
            throw new \RuntimeException("Cannot create cache directory: {$dir}");
        }

        $tmpPath = $cachePath . '.tmp.' . getmypid() . '.' . mt_rand();
        if (!@imagewebp($dst, $tmpPath, $q)) {
            if (is_file($tmpPath)) @unlink($tmpPath);
            imagedestroy($dst);
            throw new \RuntimeException('imagewebp() failed');
        }
        imagedestroy($dst);

        @chmod($tmpPath, 0644);
        if (!@rename($tmpPath, $cachePath)) {
            if (!@copy($tmpPath, $cachePath)) {
                @unlink($tmpPath);
                throw new \RuntimeException('Cannot move cache file');
            }
            @unlink($tmpPath);
        }
    }

    private function openImageGD(string $abs, string $ext)
    {
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                return @imagecreatefromjpeg($abs);
            case 'png':
                $im = @imagecreatefrompng($abs);
                if ($im) {
                    imagepalettetotruecolor($im);
                    imagealphablending($im, true);
                    imagesavealpha($im, true);
                }
                return $im;
            case 'webp':
                return @imagecreatefromwebp($abs);
            default:
                return false;
        }
    }

    private function rrmdir(string $dir, bool $verbose): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        if ($items === false) return;

        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            $p = $dir . DIRECTORY_SEPARATOR . $it;
            if (is_dir($p)) {
                $this->rrmdir($p, $verbose);
            } else {
                @unlink($p);
                if ($verbose) CLI::write("  - file {$p}");
            }
        }
        @rmdir($dir);
    }

    /* ===== JSON helpers (เก็บไว้ใต้ {cacheRoot}/../gallery-json ) ===== */
    private function jsonDirFromCacheRoot(string $cacheRoot): string
    {
        // ให้สอดคล้องกับ Controller ที่ใช้ publicJsonDir แยกจาก cacheRoot
        // สมมุติโครงสร้าง: /.../public/gallery-cache  และ  /.../public/gallery-json
        return rtrim(dirname($cacheRoot), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'gallery-json';
    }

    private function albumJsonPath(string $cacheRoot, string $albumId): string
    {
        $jsonDir = $this->jsonDirFromCacheRoot($cacheRoot);
        return $jsonDir . DIRECTORY_SEPARATOR . $albumId . '.json';
    }

    private function tryReadJson(string $jsonPath): ?array
    {
        if (!is_file($jsonPath)) return null;
        $raw = @file_get_contents($jsonPath);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function shouldWriteAlbumJson(?array $existing, int $currentCount, bool $force): bool
    {
        if ($force) return true;
        if ($existing === null) return true;
        $oldCount = is_array($existing['images'] ?? null) ? count($existing['images']) : 0;
        return $currentCount > $oldCount;
    }

    private function writeJsonAtomic(string $jsonPath, array $payload): void
    {
        $dir = dirname($jsonPath);
        $this->ensureDir($dir, false, true);

        $tmp = $jsonPath . '.tmp.' . getmypid() . '.' . mt_rand();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (@file_put_contents($tmp, $json) === false) {
            if (is_file($tmp)) @unlink($tmp);
            throw new \RuntimeException("Cannot write temp json: {$tmp}");
        }
        @chmod($tmp, 0644);
        if (!@rename($tmp, $jsonPath)) {
            if (!@copy($tmp, $jsonPath)) {
                @unlink($tmp);
                throw new \RuntimeException('Cannot move json file: ' . $jsonPath);
            }
            @unlink($tmp);
        }
    }

    /* ===== misc ===== */
    private function getHelpText(): string
    {
        return <<<TXT
Usage:
  php spark gallery:process warm  --root=/path --cache=/path --subdir="" --force=0 --json=0 --verbose=1 --memory=512M
  php spark gallery:process check --root=/path --cache=/path --subdir="" --verbose=1
  php spark gallery:process clear --root=/path --cache=/path --subdir="" --album="" --verbose=1

Notes:
- โครงสร้างแคช: {cache}/{albumIdShort}/{width}/{relPath}.webp
- JSON path   : {dirname(cache)}/gallery-json/{albumIdShort}.json
- album อ้างอิงได้ทั้ง shortId(8), full md5, หรือ slug ของชื่อโฟลเดอร์
TXT;
    }
}
