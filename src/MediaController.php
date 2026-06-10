<?php

namespace MiniCms;

use mini\Controller\AbstractController;
use mini\Mini;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MediaController extends AbstractController
{
    private string $baseDir;
    private array $imageExts = ['jpg','jpeg','png','gif','webp','svg','ico'];
    private array $allowedExts = ['jpg','jpeg','png','gif','webp','svg','pdf','mp4','webm','ico'];

    public function __construct()
    {
        parent::__construct();

        $this->baseDir = Mini::$mini->root . '/uploads';
        if (!is_dir($this->baseDir)) mkdir($this->baseDir, 0755, true);

        $this->router->get('/', $this->list(...));
        $this->router->post('/mkdir/', $this->mkdir(...));
        $this->router->post('/upload/', $this->upload(...));
        $this->router->post('/move/', $this->move(...));
        $this->router->delete('/file/', $this->delete(...));
        $this->router->get('/versions/', $this->versions(...));
        $this->router->post('/crop/', $this->cropImage(...));
        $this->router->get('/meta/', $this->getMeta(...));
        $this->router->put('/meta/', $this->putMeta(...));
        $this->router->post('/fetch/', $this->fetchUrl(...));
    }

    public function list(): ResponseInterface
    {
        $this->requireAuth();
        [$subpath, $fullPath] = $this->resolvePath();

        if (!is_dir($fullPath)) {
            return $this->json(['error' => 'Directory not found'], 404);
        }

        $folders = [];
        $files = [];

        foreach (scandir($fullPath) as $entry) {
            if ($entry[0] === '.' || str_ends_with($entry, '.versions') || str_ends_with($entry, '.meta.json')) continue;
            $entryPath = $fullPath . '/' . $entry;
            $relPath = ($subpath ? $subpath . '/' : '') . $entry;

            if (is_dir($entryPath)) {
                $folders[] = ['name' => $entry, 'path' => $relPath];
            } else {
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                $file = [
                    'name' => $entry,
                    'path' => $relPath,
                    'url' => '/uploads/' . $relPath,
                    'size' => filesize($entryPath),
                    'modified' => date('c', filemtime($entryPath)),
                    'isImage' => in_array($ext, $this->imageExts),
                    'ext' => $ext,
                ];
                $meta = $this->readMeta($entryPath);
                if ($meta) {
                    $file['meta'] = $meta;
                }
                $files[] = $file;
            }
        }

        return $this->json(['path' => $subpath, 'folders' => $folders, 'files' => $files]);
    }

    public function mkdir(): ResponseInterface
    {
        $this->requireAuth();
        [$subpath, $fullPath] = $this->resolvePath();

        $name = trim($_GET['name'] ?? '');
        $name = preg_replace('/[^a-zA-Z0-9_\-. ]/', '', $name);
        if ($name === '') {
            return $this->json(['error' => 'Invalid folder name'], 400);
        }

        $target = $fullPath . '/' . $name;
        if (is_dir($target)) {
            return $this->json(['error' => 'Folder already exists'], 409);
        }

        \mkdir($target, 0755, true);
        return $this->json(['ok' => true, 'path' => ($subpath ? $subpath . '/' : '') . $name]);
    }

    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAuth();
        [$subpath, $fullPath] = $this->resolvePath();

        $uploadedFiles = $request->getUploadedFiles();
        $files = $uploadedFiles['files'] ?? [];
        if (!is_array($files)) $files = [$files];
        if (empty($files)) {
            return $this->json(['error' => 'No files uploaded'], 400);
        }

        if (!is_dir($fullPath)) \mkdir($fullPath, 0755, true);

        $results = [];
        foreach ($files as $file) {
            if ($file->getError() !== UPLOAD_ERR_OK) {
                $results[] = ['name' => $file->getClientFilename(), 'error' => 'Upload error ' . $file->getError()];
                continue;
            }

            $name = $file->getClientFilename();
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $this->allowedExts)) {
                $results[] = ['name' => $name, 'error' => 'Type not allowed'];
                continue;
            }

            $safeName = preg_replace('/[^a-zA-Z0-9_\-.]/', '', strtolower($name));
            $safeName = preg_replace('/\.ph(p\d?|tml|ar)(?=\.|$)/i', '', $safeName);
            if ($safeName === '' || $safeName[0] === '.') $safeName = uniqid() . '.' . $ext;

            $target = $fullPath . '/' . $safeName;
            if (file_exists($target)) {
                $base = pathinfo($safeName, PATHINFO_FILENAME);
                $n = 2;
                while (file_exists($fullPath . '/' . $base . '-' . $n . '.' . $ext)) $n++;
                $safeName = $base . '-' . $n . '.' . $ext;
                $target = $fullPath . '/' . $safeName;
            }

            $file->moveTo($target);
            $relPath = ($subpath ? $subpath . '/' : '') . $safeName;
            $results[] = [
                'name' => $safeName,
                'url' => '/uploads/' . $relPath,
                'size' => filesize($target),
            ];
        }

        return $this->json($results);
    }

    public function move(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAuth();

        $data = json_decode((string)$request->getBody(), true);
        $from = trim($data['from'] ?? '', '/');
        $to = trim($data['to'] ?? '', '/');

        if ($from === '') {
            return $this->json(['error' => 'Missing source path'], 400);
        }

        // Sanitize
        $from = preg_replace('#\.\.[\\/]#', '', $from);
        $to = preg_replace('#\.\.[\\/]#', '', $to);

        $sourceFull = $this->baseDir . '/' . $from;
        if (!file_exists($sourceFull)) {
            return $this->json(['error' => 'Source not found'], 404);
        }

        // Prevent moving a folder into itself
        $basename = basename($from);
        $destDir = $this->baseDir . ($to ? '/' . $to : '');
        if (!is_dir($destDir)) {
            return $this->json(['error' => 'Destination folder not found'], 404);
        }

        $destFull = $destDir . '/' . $basename;
        if ($sourceFull === $destFull) {
            return $this->json(['ok' => true, 'path' => ($to ? $to . '/' : '') . $basename]);
        }

        if (is_dir($sourceFull) && str_starts_with($destFull . '/', $sourceFull . '/')) {
            return $this->json(['error' => 'Cannot move a folder into itself'], 400);
        }

        if (file_exists($destFull)) {
            return $this->json(['error' => 'An item with that name already exists in the destination'], 409);
        }

        rename($sourceFull, $destFull);

        $sourceMeta = $sourceFull . '.meta.json';
        if (is_file($sourceMeta)) {
            rename($sourceMeta, $destFull . '.meta.json');
        }

        return $this->json(['ok' => true, 'path' => ($to ? $to . '/' : '') . $basename]);
    }

    public function delete(): ResponseInterface
    {
        $this->requireAuth();
        [$subpath, $fullPath] = $this->resolvePath();

        if (!is_file($fullPath)) {
            return $this->json(['error' => 'File not found'], 404);
        }

        unlink($fullPath);

        $metaFile = $fullPath . '.meta.json';
        if (is_file($metaFile)) {
            unlink($metaFile);
        }

        $processor = new ImageProcessor($this->baseDir);
        $processor->deleteVersions($subpath);

        return $this->json(['ok' => true]);
    }

    public function versions(): ResponseInterface
    {
        $this->requireAuth();
        [$subpath] = $this->resolvePath();

        $processor = new ImageProcessor($this->baseDir);
        return $this->json($processor->getVersions($subpath));
    }

    public function cropImage(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAuth();

        $data = json_decode((string)$request->getBody(), true);
        $imagePath = trim($data['image'] ?? '', '/');
        $aspect = $data['aspect'] ?? '';
        $crop = $data['crop'] ?? null;

        if (!$imagePath || !$aspect || !$crop) {
            return $this->json(['error' => 'Missing image, aspect, or crop data'], 400);
        }

        $imagePath = preg_replace('#\.\.[\\/]#', '', $imagePath);

        $processor = new ImageProcessor($this->baseDir);
        $widths = $processor->crop($imagePath, $aspect, $crop);

        return $this->json(['ok' => true, 'widths' => $widths]);
    }

    public function fetchUrl(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAuth();

        $data = json_decode((string)$request->getBody(), true);
        $url = $data['url'] ?? '';
        $targetPath = trim($data['path'] ?? '', '/');

        if (!$url || !preg_match('#^https?://#', $url)) {
            return $this->json(['error' => 'Invalid URL'], 400);
        }

        $targetPath = preg_replace('#\.\.[\\/]#', '', $targetPath);
        $destDir = $this->baseDir . ($targetPath ? '/' . $targetPath : '');
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);

        try {
            $client = new \mini\Http\Client\HttpClient(['timeout' => 15]);
            $response = $client->get($url);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Failed to download: ' . $e->getMessage()], 400);
        }

        if ($response->getStatusCode() >= 400) {
            return $this->json(['error' => 'Remote server returned ' . $response->getStatusCode()], 400);
        }

        $contentType = $response->getHeaderLine('Content-Type');
        $extMap = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
            'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'image/x-icon' => 'ico',
        ];

        $ext = null;
        foreach ($extMap as $mime => $e) {
            if (str_starts_with($contentType, $mime)) { $ext = $e; break; }
        }

        if (!$ext) {
            $pathExt = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
            if (in_array($pathExt, $this->allowedExts)) {
                $ext = $pathExt;
            }
        }

        if (!$ext || !in_array($ext, $this->allowedExts)) {
            return $this->json(['error' => 'Unsupported file type'], 400);
        }

        $urlPath = parse_url($url, PHP_URL_PATH) ?: '';
        $basename = pathinfo($urlPath, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower($basename));
        if (!$basename) $basename = 'image-' . substr(md5($url), 0, 8);

        $safeName = $basename . '.' . $ext;
        $target = $destDir . '/' . $safeName;
        if (file_exists($target)) {
            $n = 2;
            while (file_exists($destDir . '/' . $basename . '-' . $n . '.' . $ext)) $n++;
            $safeName = $basename . '-' . $n . '.' . $ext;
            $target = $destDir . '/' . $safeName;
        }

        file_put_contents($target, (string)$response->getBody());

        $relPath = ($targetPath ? $targetPath . '/' : '') . $safeName;
        return $this->json([
            'ok' => true,
            'name' => $safeName,
            'url' => '/uploads/' . $relPath,
            'size' => filesize($target),
        ]);
    }

    public function getMeta(): ResponseInterface
    {
        $this->requireAuth();
        [$subpath, $fullPath] = $this->resolvePath();
        $meta = $this->readMeta($fullPath);
        return $this->json($meta ?: new \stdClass());
    }

    public function putMeta(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAuth();
        [$subpath, $fullPath] = $this->resolvePath();

        if (!is_file($fullPath)) {
            return $this->json(['error' => 'File not found'], 404);
        }

        $data = json_decode((string)$request->getBody(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        $metaFile = $fullPath . '.meta.json';
        file_put_contents($metaFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        return $this->json(['ok' => true]);
    }

    private function readMeta(string $filePath): ?array
    {
        $metaFile = $filePath . '.meta.json';
        if (!is_file($metaFile)) return null;
        $data = json_decode(file_get_contents($metaFile), true);
        return is_array($data) ? $data : null;
    }

    private function requireAuth(): void
    {
        if (empty($_SESSION['cms_user'])) {
            throw new \mini\Exceptions\AuthenticationRequiredException();
        }
    }

    /** @return array{string, string} [subpath, fullPath] */
    private function resolvePath(): array
    {
        $subpath = trim($_GET['path'] ?? '', '/');
        $subpath = preg_replace('#\.\.[\\/]#', '', $subpath);
        $fullPath = $this->baseDir . ($subpath ? '/' . $subpath : '');
        return [$subpath, $fullPath];
    }
}
