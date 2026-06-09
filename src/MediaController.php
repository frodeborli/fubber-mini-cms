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
            if ($entry[0] === '.' || str_ends_with($entry, '.versions')) continue;
            $entryPath = $fullPath . '/' . $entry;
            $relPath = ($subpath ? $subpath . '/' : '') . $entry;

            if (is_dir($entryPath)) {
                $folders[] = ['name' => $entry, 'path' => $relPath];
            } else {
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                $files[] = [
                    'name' => $entry,
                    'path' => $relPath,
                    'url' => '/uploads/' . $relPath,
                    'size' => filesize($entryPath),
                    'modified' => date('c', filemtime($entryPath)),
                    'isImage' => in_array($ext, $this->imageExts),
                    'ext' => $ext,
                ];
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
