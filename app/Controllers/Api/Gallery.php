<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class Gallery extends ResourceController
{
    protected $format = 'json';

    protected array $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

    protected array $stores = [];
    protected string $storeKey = 'public';
    protected string $rootAbs  = '';

    protected string $publicCacheDir = '/volume2/web_gallery/public/gallery-cache';
    protected string $publicJsonDir  = '/volume2/web_gallery/public/gallery-json';

    private const ALLOWED_WIDTHS = [320, 2048];
    private const DEFAULT_Q      = 100;

    public function __construct()
    {
        $envRoot = rtrim(getenv('gallery_DIR') ?: '/volume1/gallery', '/');
        $this->stores = ['public' => ['abs' => $envRoot]];
        $this->applyStore($this->storeKey);
    }

    protected function applyStore(string $key): void
    {
        if (!isset($this->stores[$key])) {
            $keys = array_keys($this->stores);
            $key  = $keys[0] ?? 'public';
        }
        $this->storeKey = $key;
        $this->rootAbs  = rtrim($this->stores[$key]['abs'], DIRECTORY_SEPARATOR);
    }

    protected function pickAllowedWidth(int $w): int
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

    public function index()
    {
        $this->response->setHeader('Access-Control-Allow-Origin', '*');

        $store = (string) $this->request->getGet('store');
        if ($store) $this->applyStore($store);

        $subdir = trim((string) $this->request->getGet('subdir'), '/');
        $scanRoot = $this->rootAbs . ($subdir ? DIRECTORY_SEPARATOR . $subdir : '');
        if (!is_dir($scanRoot)) return $this->failNotFound("Root folder not found");

        $perPage = (int)($this->request->getGet('perPage') ?? 12);
        $page = max(1, (int)($this->request->getGet('page') ?? 1));

        $collections = [];

        foreach ($this->listSubfolders($scanRoot) as $folderName) {
            $folderAbs = $scanRoot . DIRECTORY_SEPARATOR . $folderName;

            // แทน listImagesFlatOneLevel
            $files = array_values(array_filter(
                scandir($folderAbs),
                fn($f) =>
                is_file($folderAbs . DIRECTORY_SEPARATOR . $f) &&
                    preg_match('/\.(jpe?g|png|webp)$/i', $f)
            ));
            if (!$files) continue;

            // เลือก cover แบบเร็ว
            $filesAssoc = array_flip($files);
            $coverCandidates = ['cover.jpg', 'cover.jpeg', 'cover.png', 'cover.webp'];
            $coverRel = null;
            foreach ($coverCandidates as $c) {
                if (isset($filesAssoc[$c])) {
                    $coverRel = $c;
                    break;
                }
            }
            $coverRel = $coverRel ?? $files[0];
            $coverAbs = $folderAbs . DIRECTORY_SEPARATOR . $coverRel;

            $idShort = substr(md5(($subdir ? $subdir . '/' : '') . $folderName), 0, 8);

            // อ่าน JSON cache เพื่อลด getimagesize
            $json = $this->tryReadAlbumJson($this->getAlbumJsonPath($idShort));
            if (is_array($json['images'] ?? null) && !empty($json['images'])) {
                $ratio = $json['images'][0]['aspectRatio'] ?? '4/3';
            } else {
                [$cw, $ch] = @getimagesize($coverAbs) ?: [0, 0];
                $ratio = $this->aspectRatioFromWH($cw, $ch);
            }

            $mtime = @filemtime($coverAbs) ?: time();
            $isoDate = gmdate('Y-m-d', $mtime);

            $v = $mtime; // cache-busting
            $coverUrl = $this->fileUrl($this->storeKey, $idShort, $coverRel, 320, self::DEFAULT_Q, ['v' => $v]);

            $collections[] = [
                'id' => $idShort,
                'title' => $this->prettifyName($folderName),
                'date' => $isoDate,
                'cover' => $coverUrl,
                'picturesCount' => count($files),
                'aspectRatio' => $ratio,
            ];
        }

        // sort by date (timestamp)
        usort($collections, fn($a, $b) => $b['date'] <=> $a['date']);

        $total = count($collections);
        $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($collections, $offset, $perPage);

        $debug = (int)($this->request->getGet('debug') ?? 0);

        return $this->respond([
            'ok' => true,
            'store' => $this->storeKey,
            'rootAbs' => $debug ? $this->rootAbs : null,
            'scanRoot' => $debug ? $scanRoot : null,
            'subdir' => $subdir,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
            'galleries' => $slice,
        ]);
    }


    public function show($id = null)
    {
        $this->response->setHeader('Access-Control-Allow-Origin', '*');

        $store = (string) $this->request->getGet('store');
        if ($store) $this->applyStore($store);

        $subdir   = trim((string) $this->request->getGet('subdir'), '/');
        $scanRoot = $this->rootAbs . ($subdir ? DIRECTORY_SEPARATOR . $subdir : '');
        if (!is_dir($scanRoot)) return $this->failNotFound("Root folder not found");

        $needle = is_string($id) ? trim($id) : '';
        if ($needle === '') return $this->failValidationErrors('Missing album id');

        $refresh = ((int) ($this->request->getGet('refresh') ?? 0)) === 1;

        // ----------- หาโฟลเดอร์จาก id -----------
        $matchFolder = null;
        foreach ($this->listSubfolders($scanRoot) as $folder) {
            $fullId = md5(($subdir ? $subdir . '/' : '') . $folder);
            $shortId = substr($fullId, 0, 8);
            $slug = $this->slugify($folder);
            if ($needle === $shortId || $needle === $fullId || $needle === $slug) {
                $matchFolder = $folder;
                break;
            }
        }
        if (!$matchFolder) return $this->failNotFound("Collection not found: {$needle}");

        $folderAbs    = $scanRoot . DIRECTORY_SEPARATOR . $matchFolder;
        $albumIdShort = substr(md5(($subdir ? $subdir . '/' : '') . $matchFolder), 0, 8);
        $jsonPath     = $this->getAlbumJsonPath($albumIdShort);
        $cached       = !$refresh && file_exists($jsonPath) ? $this->tryReadAlbumJson($jsonPath) : null;

        // ----------- เช็กจำนวนรูป -----------
        $files = array_values(array_filter(
            scandir($folderAbs),
            fn($f) =>
            is_file($folderAbs . DIRECTORY_SEPARATOR . $f) &&
                preg_match('/\.(jpe?g|png|webp)$/i', $f)
        ));

        $currentCount = count($files);
        $cachedCount = is_array($cached['images'] ?? null) ? count($cached['images']) : -1;

        if ($cached && $cachedCount === $currentCount) {
            // ส่ง cache ทันที
            $mtime = @filemtime($jsonPath) ?: time();
            $size  = @filesize($jsonPath) ?: 0;
            $etag = '"' . dechex($mtime) . '-' . dechex($size) . '"';
            $lastMod = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

            $this->response->setHeader('Cache-Control', 'public, max-age=0, immutable');
            $this->response->setHeader('ETag', $etag);
            $this->response->setHeader('Last-Modified', $lastMod);

            if ((($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) ||
                (($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '') === $lastMod)
            ) {
                return $this->response->setStatusCode(304);
            }

            return $this->respond([
                'ok' => true,
                'id' => $cached['id'] ?? $albumIdShort,
                'store' => $cached['store'] ?? $this->storeKey,
                'subdir' => $cached['subdir'] ?? $subdir,
                'title' => $cached['title'] ?? $this->prettifyName($matchFolder),
                'count' => $cachedCount,
                'images' => $cached['images'],
            ]);
        }

        // ----------- สร้าง images ใหม่ -----------
        $indexByName = [];
        if (is_array($cached['images'] ?? null)) {
            foreach ($cached['images'] as $it) $indexByName[$it['filename']] = $it;
        }

        $images = [];
        $coverFiles = ['cover.jpg', 'cover.jpeg', 'cover.png', 'cover.webp'];
        $coverIndex = [];

        foreach ($files as $rel) {
            $info = $indexByName[$rel] ?? null;
            if ($info) {
                $w = (int)($info['width'] ?? 0);
                $h = (int)($info['height'] ?? 0);
                $ratio = $info['aspectRatio'] ?? $this->aspectRatioFromWH($w, $h);
                $ts = strtotime($info['captured'] ?? 'now');
            } else {
                $abs = $folderAbs . DIRECTORY_SEPARATOR . $rel;
                [$w, $h] = @getimagesize($abs) ?: [0, 0];
                $ratio = $this->aspectRatioFromWH($w, $h);
                $ts = @filemtime($abs) ?: time();
            }

            $item = [
                'filename' => $rel,
                'width' => $w,
                'height' => $h,
                'aspectRatio' => $ratio,
                'captured' => gmdate('c', $ts)
            ];

            if (in_array(strtolower($rel), $coverFiles)) $coverIndex[] = $item;
            else $images[] = $item;
        }

        // cover ขึ้นหน้า
        $images = array_merge($coverIndex, $images);

        // ----------- เขียน JSON ใหม่ -----------
        try {
            $payload = [
                'id' => $albumIdShort,
                'store' => $this->storeKey,
                'subdir' => $subdir,
                'folder' => $matchFolder,
                'title' => $this->prettifyName($matchFolder),
                'images' => $images,
            ];
            $this->writeAlbumJson($jsonPath, $payload);
        } catch (\Throwable $e) {
            log_message('error', 'GALLERY: write json failed :: ' . $e->getMessage());
        }

        $this->response->setHeader('Cache-Control', 'public, max-age=0, immutable');

        return $this->respond([
            'ok' => true,
            'id' => $albumIdShort,
            'store' => $this->storeKey,
            'subdir' => $subdir,
            'title' => $this->prettifyName($matchFolder),
            'count' => count($images),
            'images' => $images,
        ]);
    }



    public function file()
    {
        $this->response->setHeader('Access-Control-Allow-Origin', '*');

        $store = (string) $this->request->getGet('store');
        if ($store) $this->applyStore($store);

        $album  = trim((string) $this->request->getGet('album'));
        $file   = (string) $this->request->getGet('f');
        $subdir = trim((string) $this->request->getGet('subdir'), '/');

        if ($album === '' || $file === '') return $this->failValidationErrors('Missing parameters: album or f');

        $scanRoot = $this->rootAbs . ($subdir ? DIRECTORY_SEPARATOR . $subdir : '');
        if (!is_dir($scanRoot)) return $this->failNotFound("Root folder not found: " . ($subdir ? "{$this->storeKey}/{$subdir}" : $this->storeKey));

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
        if (!$matchFolder) return $this->failNotFound("Collection not found: {$album}");

        $file = ltrim($file, '/');
        if (str_contains($file, '..') || str_starts_with($file, '/')) return $this->failForbidden('Invalid filename');

        $abs = realpath($scanRoot . DIRECTORY_SEPARATOR . $matchFolder . DIRECTORY_SEPARATOR . $file);
        if ($abs === false) return $this->failNotFound('File not found');

        $allowedRoot = realpath($scanRoot . DIRECTORY_SEPARATOR . $matchFolder);
        if ($allowedRoot === false || strncmp($abs, $allowedRoot . DIRECTORY_SEPARATOR, strlen($allowedRoot) + 1) !== 0) {
            return $this->failForbidden('Invalid path');
        }

        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExt, true)) return $this->failForbidden('Extension not allowed');

        $reqW = (int) ($this->request->getGet('w') ?? 0);
        $w    = $this->pickAllowedWidth($reqW);
        $q    = self::DEFAULT_Q;

        $albumIdShort = substr(md5(($subdir ? $subdir . '/' : '') . $matchFolder), 0, 8);

        try {
            $cachePath = $this->generateWebpCached($abs, $w, $q, false, $albumIdShort, $file);
        } catch (\Throwable $e) {
            log_message('error', 'GALLERY: generate failed :: ' . $e->getMessage());
            return $this->failServerError('Encode webp failed');
        }

        // ถ้าคอนฟิก Nginx ไว้: ส่งผ่าน X-Accel-Redirect ให้ Nginx เสิร์ฟไฟล์
        if ($this->tryStaticAccel($cachePath)) {
            return $this->respondAccel($cachePath);
        }

        return $this->respondFileGD($cachePath, 'webp');
    }

    public function raw()
    {
        $this->response->setHeader('Access-Control-Allow-Origin', '*');

        $store = (string) $this->request->getGet('store');
        if ($store) $this->applyStore($store);

        $album  = trim((string) $this->request->getGet('album'));
        $file   = (string) $this->request->getGet('f');
        $subdir = trim((string) $this->request->getGet('subdir'), '/');

        if ($album === '' || $file === '') return $this->failValidationErrors('Missing parameters: album or f');

        $scanRoot = $this->rootAbs . ($subdir ? DIRECTORY_SEPARATOR . $subdir : '');
        if (!is_dir($scanRoot)) return $this->failNotFound("Root folder not found: " . ($subdir ? "{$this->storeKey}/{$subdir}" : $this->storeKey));

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
        if (!$matchFolder) return $this->failNotFound("Collection not found: {$album}");

        $file = ltrim($file, '/');
        if (str_contains($file, '..') || str_starts_with($file, '/')) return $this->failForbidden('Invalid filename');

        $abs = realpath($scanRoot . DIRECTORY_SEPARATOR . $matchFolder . DIRECTORY_SEPARATOR . $file);
        if ($abs === false || !is_file($abs)) return $this->failNotFound('File not found');

        return $this->response->download($abs, null)->setFileName(basename($abs));
    }

    /* ===== Helpers ===== */

    /**
     * นับจำนวนไฟล์รูปภายในอัลบั้ม (รวม 1 ระดับย่อย) แบบเร็ว
     * - ไม่อ่าน getimagesize/EXIF
     * - ใช้เฉพาะนามสกุลใน $this->allowedExt
     */
    protected function fastCountImages(string $albumAbs): int
    {
        $count = 0;
        $items = @scandir($albumAbs) ?: [];

        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            if ($it[0] === '#' || $it[0] === '@' || strcasecmp($it, '#recycle') === 0) continue;
            $p = $albumAbs . DIRECTORY_SEPARATOR . $it;
            if (is_file($p)) {
                $ext = strtolower(pathinfo($it, PATHINFO_EXTENSION));
                if (in_array($ext, $this->allowedExt, true)) $count++;
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
                if (in_array($ext, $this->allowedExt, true)) $count++;
            }
        }

        return $count;
    }


    protected function listSubfolders(string $absRoot): array
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

    protected function listImagesFlatOneLevel(string $albumAbs): array
    {
        $relPaths = [];
        $items = @scandir($albumAbs) ?: [];

        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            if ($it[0] === '#' || $it[0] === '@' || strcasecmp($it, '#recycle') === 0) continue;
            $p = $albumAbs . DIRECTORY_SEPARATOR . $it;
            if (is_file($p)) {
                $ext = strtolower(pathinfo($it, PATHINFO_EXTENSION));
                if (in_array($ext, $this->allowedExt, true)) $relPaths[] = $it;
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
                if (in_array($ext, $this->allowedExt, true)) $relPaths[] = $it . '/' . $f;
            }
        }

        sort($relPaths, SORT_NATURAL | SORT_FLAG_CASE);
        return $relPaths;
    }

    protected function aspectRatioFromWH(int $w, int $h): string
    {
        if ($w <= 0 || $h <= 0) return '4/3';
        $ratio = $w / $h;
        $pairs = ['16/9' => 16 / 9, '3/2' => 3 / 2, '4/3' => 4 / 3, '1/1' => 1, '4/5' => 0.8, '2/3' => 2 / 3];
        $best = '4/3';
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

    protected function imageTimestamp(string $absFile): int
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

    protected function slugify(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('~[^\pL\d]+~u', '-', $s);
        $s = trim($s, '-');
        $s = mb_strtolower($s);
        $s = preg_replace('~[^-a-z0-9]+~', '', $s);
        return $s ?: 'n-a';
    }

    protected function prettifyName(string $folder): string
    {
        $folder = str_replace(['-', '_'], ' ', $folder);
        return preg_replace('/\s+/', ' ', ucwords(trim($folder)));
    }

    /** URL helper (เพิ่มเพื่อแก้ error fileUrl not found) */
    protected function fileUrl(
        string $storeKey,
        string $albumId,
        string $filename,
        int $w = 0,
        int $q = 80,
        array $extra = []
    ): string {
        $uri = $this->request->getUri();
        $basePath = rtrim($uri->getPath(), '/');
        $prefix = '';
        $pos = strpos($basePath, '/api/');
        if ($pos !== false) {
            $prefix = substr($basePath, 0, $pos);
        }
        $prefix = rtrim($prefix, '/');

        $schemeHost = $uri->getScheme() . '://' . $uri->getAuthority();
        $endpoint   = $schemeHost . $prefix . '/gallery/api/file';

        $params = [
            'store' => $storeKey,
            'album' => $albumId,
            'f'     => $filename,
        ];
        if ($w > 0)    $params['w'] = $w;
        if ($q !== 75) $params['q'] = $q;

        foreach ($extra as $k => $v) {
            if ($v !== null && $v !== '') $params[$k] = $v;
        }

        return $endpoint . '?' . http_build_query($params);
    }

    /* ---------- Fast file responses ---------- */

    protected function respondFileGD(string $path, string $ext = 'webp')
    {
        if (!is_file($path)) return $this->failNotFound("File not found: {$path}");

        $ext  = strtolower($ext);
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'        => 'image/png',
            'gif'        => 'image/gif',
            default      => 'image/webp',
        };

        $mtime   = filemtime($path) ?: time();
        $size    = filesize($path) ?: 0;
        $etag    = '"' . dechex($mtime) . '-' . dechex($size) . '"';
        $lastMod = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

        // ล้าง header cache เดิม
        $this->response->removeHeader('Cache-Control');
        $this->response->removeHeader('Pragma');
        $this->response->removeHeader('Expires');

        // บังคับ 14 วัน เสมอ
        $cacheCtl = 'public, max-age=1209600, immutable';

        $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Length', (string)$size)
            ->setHeader('Cache-Control', $cacheCtl)
            ->setHeader('ETag', $etag)
            ->setHeader('Last-Modified', $lastMod)
            ->setHeader('Accept-Ranges', 'bytes')
            ->setHeader('Vary', 'Accept');

        // Short-circuit 304
        $ifNoneMatch     = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        if (($ifNoneMatch && trim($ifNoneMatch) === $etag) ||
            ($ifModifiedSince && $ifModifiedSince === $lastMod)
        ) {
            return $this->response->setStatusCode(304);
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            log_message('error', "GALLERY: read cache failed {$path} :: " . json_encode(error_get_last()));
            return $this->failServerError('Read cache failed');
        }
        return $this->response->setStatusCode(200)->setBody($data);
    }


    // X-Accel-Redirect (ถ้าใช้ Nginx)
    protected function tryStaticAccel(string $absPath): bool
    {
        $prefix = getenv('ACCEL_PREFIX') ?: '';
        $root   = getenv('ACCEL_ROOT') ?: $this->publicCacheDir;
        if ($prefix === '' || !is_file($absPath)) return false;

        $absPath = realpath($absPath) ?: $absPath;
        $root    = rtrim(realpath($root) ?: $root, '/');

        if (!str_starts_with($absPath, $root . DIRECTORY_SEPARATOR)) return false;

        $internalPath = $prefix . substr($absPath, strlen($root));
        $this->response->setHeader('X-Accel-Redirect', $internalPath);
        return true;
    }

    protected function respondAccel(string $path)
    {
        $mime = 'image/webp';
        $mtime   = filemtime($path) ?: time();
        $size    = filesize($path) ?: 0;
        $etag    = '"' . dechex($mtime) . '-' . dechex($size) . '"';
        $lastMod = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

        // ใช้ cache-control แบบเดียวตลอด: 14 วัน (1209600 วินาที)
        $cacheCtl = 'public, max-age=1209600, immutable';

        $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Length', (string)$size)
            ->setHeader('Cache-Control', $cacheCtl)
            ->setHeader('ETag', $etag)
            ->setHeader('Last-Modified', $lastMod)
            ->setHeader('Accept-Ranges', 'bytes')
            ->setHeader('Vary', 'Accept');

        return $this->response->setStatusCode(200);
    }


    /* ===== JSON Cache Helpers ===== */

    protected function ensureJsonDir(): void
    {
        $dir = rtrim($this->publicJsonDir, DIRECTORY_SEPARATOR);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true)) {
                throw new \RuntimeException("Cannot create json directory: {$dir}");
            }
            @file_put_contents($dir . DIRECTORY_SEPARATOR . 'index.html', '');
        }
    }

    protected function getAlbumJsonPath(string $albumId): string
    {
        return rtrim($this->publicJsonDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $albumId . '.json';
    }

    protected function tryReadAlbumJson(string $jsonPath): ?array
    {
        if (!is_file($jsonPath)) return null;
        $raw = @file_get_contents($jsonPath);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    protected function shouldWriteAlbumJson(?array $existing, int $currentCount, bool $refresh): bool
    {
        if ($refresh) return true;
        if ($existing === null) return true;
        $oldCount = is_array($existing['images'] ?? null) ? count($existing['images']) : 0;
        return $currentCount > $oldCount;
    }

    protected function writeAlbumJson(string $jsonPath, array $payload): void
    {
        $this->ensureJsonDir();
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
                throw new \RuntimeException("Cannot move json file to: {$jsonPath}");
            }
            @unlink($tmp);
        }
    }

    /* ===== Legacy fallback (ไม่ใช้ในเวอร์ชันโครงสร้างโฟลเดอร์ใหม่) ===== */

    protected function getCachePathFor(string $abs, int $w, int $q): string
    {
        $this->ensureCacheDir();
        $mtime = @filemtime($abs) ?: 0;
        $name  = md5($abs . "|m={$mtime}|w={$w}|q={$q}") . '.webp';
        return rtrim($this->publicCacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    }

    protected function openImageGD(string $abs, string $ext)
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

    protected function sanitizeRelPath(string $rel): string
    {
        $rel = ltrim($rel, '/');
        $rel = str_replace(['..\\', '../'], '', $rel);
        $rel = str_replace('\\', '/', $rel);
        return $rel;
    }

    protected function getCachePathForByAlbum(string $albumIdShort, string $relPath, int $w): string
    {
        $this->ensureCacheDir();

        $relPath = $this->sanitizeRelPath($relPath);
        $relDir  = pathinfo($relPath, PATHINFO_DIRNAME);
        $relBase = pathinfo($relPath, PATHINFO_FILENAME);

        $base   = rtrim($this->publicCacheDir, DIRECTORY_SEPARATOR)
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

    protected function generateWebpCached(
        string $abs,
        int $w,
        int $q,
        bool $force = false,
        ?string $albumIdShort = null,
        ?string $relPath = null
    ): string {
        $w = $this->pickAllowedWidth($w);
        $q = self::DEFAULT_Q;

        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExt, true)) {
            throw new \RuntimeException('Extension not allowed');
        }
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            throw new \RuntimeException('WebP not supported on server');
        }

        $cachePath = ($albumIdShort !== null && $relPath !== null)
            ? $this->getCachePathForByAlbum($albumIdShort, $relPath, $w)
            : $this->getCachePathFor($abs, $w, $q);

        if (!$force && is_file($cachePath)) return $cachePath;

        $src = $this->openImageGD($abs, $ext);
        if (!$src) throw new \RuntimeException('Cannot read image: ' . $abs);

        if ($ext === 'jpg' || $ext === 'jpeg') {
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($abs);
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
        $dst = $src;

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
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true)) {
                imagedestroy($dst);
                throw new \RuntimeException("Cannot create cache directory: {$dir}");
            }
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

        return $cachePath;
    }

    protected function ensureCacheDir(): void
    {
        $cacheRoot = $this->publicCacheDir;
        if (!is_dir($cacheRoot)) {
            if (!@mkdir($cacheRoot, 0775, true)) throw new \RuntimeException("Cannot create cache directory: {$cacheRoot}");
            @file_put_contents($cacheRoot . DIRECTORY_SEPARATOR . 'index.html', '');
        }
    }
}
