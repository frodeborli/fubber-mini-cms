<?php

namespace MiniCms;

use mini\Util\Path;

class ContentStore
{
    private Path $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = new Path($basePath);
    }

    public function getBasePath(): string
    {
        return (string) $this->basePath;
    }

    /**
     * Read a text or image value from widgets.json in the given context directory.
     */
    public function readWidget(string $contextPath, string $slug): mixed
    {
        $data = $this->loadWidgets($contextPath);
        return $data[$slug] ?? null;
    }

    /**
     * Write a text or image value to widgets.json in the given context directory.
     */
    public function writeWidget(string $contextPath, string $slug, mixed $value): void
    {
        $data = $this->loadWidgets($contextPath);
        $data[$slug] = $value;
        $this->saveWidgets($contextPath, $data);
    }

    /**
     * Read HTML content from a .html file in the given context directory.
     */
    public function readHtml(string $contextPath, string $slug): ?string
    {
        $file = (string) Path::create($this->basePath, $contextPath, $slug . '.html');
        if (!is_file($file)) return null;
        return file_get_contents($file);
    }

    /**
     * Write HTML content to a .html file in the given context directory.
     */
    public function writeHtml(string $contextPath, string $slug, string $value): void
    {
        $dir = (string) Path::create($this->basePath, $contextPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file = (string) Path::create($dir, $slug . '.html');
        file_put_contents($file, $value);
    }

    // -- Legacy methods for backward compatibility during migration --

    public function read(string $file, string $path): mixed
    {
        $data = $this->loadFile($file);
        $parts = explode('|', $path, 2);
        $group = $parts[0];
        $field = $parts[1] ?? $parts[0];
        return $data[$group][$field]['value'] ?? null;
    }

    public function write(string $file, string $path, string $type, int $pos, mixed $value): void
    {
        $data = $this->loadFile($file);
        $parts = explode('|', $path, 2);
        $group = $parts[0];
        $field = $parts[1] ?? $parts[0];
        $data[$group][$field] = ['pos' => $pos, 'type' => $type, 'value' => $value];
        $this->saveFile($file, $data);
    }

    // -- Internal --

    private function loadWidgets(string $contextPath): array
    {
        $file = (string) Path::create($this->basePath, $contextPath, 'widgets.json');
        if (!is_file($file)) return [];
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private function saveWidgets(string $contextPath, array $data): void
    {
        $dir = (string) Path::create($this->basePath, $contextPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $file = (string) Path::create($dir, 'widgets.json');
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }

    private function loadFile(string $file): array
    {
        $fullPath = (string) Path::create($this->basePath, $file);
        if (!is_file($fullPath)) return [];
        $data = json_decode(file_get_contents($fullPath), true);
        return is_array($data) ? $data : [];
    }

    private function saveFile(string $file, array $data): void
    {
        $fullPath = (string) Path::create($this->basePath, $file);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($fullPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }
}
