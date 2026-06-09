<?php

namespace MiniCms;

/**
 * Collects CMS component registrations during view rendering.
 *
 * When a view is rendered internally (to discover editable regions),
 * each cms_text(), cms_html(), etc. call registers itself here.
 * The admin shell then reads the collected components to build the edit panel.
 */
class ComponentCollector
{
    private static ?self $instance = null;
    private bool $collecting = false;

    /** @var array<string, array<string, array>> Grouped components: [group => [field => data]] */
    private array $components = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function startCollecting(): void
    {
        $this->collecting = true;
        $this->components = [];
    }

    public function stopCollecting(): array
    {
        $this->collecting = false;
        $result = $this->components;
        $this->components = [];
        return $result;
    }

    public function isCollecting(): bool
    {
        return $this->collecting;
    }

    /**
     * Register a component. Called by cms_text(), cms_html(), etc.
     *
     * @param string $file   JSON content file path (relative to _content/)
     * @param string $path   "Group|Field" path
     * @param int    $pos    Position in group for ordering
     * @param string $type   Component type: 'text', 'html', 'image', etc.
     * @param string $default Default value if not set in JSON
     * @param mixed  $value  Current value from JSON (or null)
     * @param array  $meta   Extra metadata (e.g. aspect ratio for images)
     */
    public function register(string $file, string $path, int $pos, string $type, string $default, mixed $value, array $meta = []): void
    {
        if (!$this->collecting) return;

        $parts = explode('|', $path, 2);
        $group = isset($parts[1]) ? $parts[0] : 'Page';
        $field = $parts[1] ?? $parts[0];

        $this->components[$group][$field] = [
            'file' => $file,
            'path' => $path,
            'pos' => $pos,
            'type' => $type,
            'default' => $default,
            'value' => $value,
        ] + $meta;
    }
}
