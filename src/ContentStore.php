<?php

namespace MiniCms;

/**
 * Reads and writes component values from JSON content files.
 */
class ContentStore
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Read a value from a JSON content file.
     *
     * @param string $file Relative path to JSON file
     * @param string $path "Group|Field" path
     * @return mixed The stored value, or null if not set
     */
    public function read(string $file, string $path): mixed
    {
        $data = $this->loadFile($file);
        $parts = explode('|', $path, 2);
        $group = $parts[0];
        $field = $parts[1] ?? $parts[0];

        return $data[$group][$field]['value'] ?? null;
    }

    /**
     * Write a value to a JSON content file.
     *
     * @param string $file  Relative path to JSON file
     * @param string $path  "Group|Field" path
     * @param string $type  Component type
     * @param int    $pos   Position
     * @param mixed  $value The value to store
     */
    public function write(string $file, string $path, string $type, int $pos, mixed $value): void
    {
        $data = $this->loadFile($file);
        $parts = explode('|', $path, 2);
        $group = $parts[0];
        $field = $parts[1] ?? $parts[0];

        $data[$group][$field] = [
            'pos' => $pos,
            'type' => $type,
            'value' => $value,
        ];

        $this->saveFile($file, $data);
    }

    private function loadFile(string $file): array
    {
        $fullPath = $this->basePath . '/' . $file;
        if (!is_file($fullPath)) return [];
        $data = json_decode(file_get_contents($fullPath), true);
        return is_array($data) ? $data : [];
    }

    private function saveFile(string $file, array $data): void
    {
        $fullPath = $this->basePath . '/' . $file;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($fullPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }
}
